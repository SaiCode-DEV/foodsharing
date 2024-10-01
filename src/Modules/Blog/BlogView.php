<?php

namespace Foodsharing\Modules\Blog;

use Foodsharing\Lib\Session;
use Foodsharing\Lib\View\Utils;
use Foodsharing\Modules\Core\View;
use Foodsharing\Modules\Unit\CurrentUserUnitsInterface;
use Foodsharing\Permissions\BlogPermissions;
use Foodsharing\Utility\DataHelper;
use Foodsharing\Utility\IdentificationHelper;
use Foodsharing\Utility\ImageHelper;
use Foodsharing\Utility\NumberHelper;
use Foodsharing\Utility\PageHelper;
use Foodsharing\Utility\RouteHelper;
use Foodsharing\Utility\Sanitizer;
use Foodsharing\Utility\TimeHelper;
use Foodsharing\Utility\TranslationHelper;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class BlogView extends View
{
    private readonly BlogPermissions $blogPermissions;

    // picture size on the blog post page and the upload form
    private const PICTURE_FULL_WIDTH = 528;
    private const PICTURE_FULL_HEIGHT = 285;

    public function __construct(
        Environment $twig,
        Session $session,
        Utils $viewUtils,
        BlogPermissions $blogPermissions,
        DataHelper $dataHelper,
        IdentificationHelper $identificationHelper,
        ImageHelper $imageService,
        NumberHelper $numberHelper,
        PageHelper $pageHelper,
        RouteHelper $routeHelper,
        Sanitizer $sanitizerService,
        TimeHelper $timeHelper,
        TranslationHelper $translationHelper,
        TranslatorInterface $translator,
        CurrentUserUnitsInterface $currentUserUnits,
    ) {
        parent::__construct(
            $twig,
            $session,
            $viewUtils,
            $dataHelper,
            $identificationHelper,
            $imageService,
            $numberHelper,
            $pageHelper,
            $routeHelper,
            $sanitizerService,
            $timeHelper,
            $translationHelper,
            $translator,
            $currentUserUnits,
        );
        $this->blogPermissions = $blogPermissions;
    }

    public function blogpostOverview(array $data): string
    {
        return $this->vueComponent('vue-blog-overview', 'BlogOverview', [
            'mayAdministrateBlog' => $this->blogPermissions->mayAdministrateBlog(),
            'managedRegions' => $this->currentUserUnits->getMyAmbassadorRegionIds(),
            'blogList' => $data,
        ]);
    }

    public function newsPost(int $blogId): string
    {
        return $this->vueComponent('blog-post', 'BlogPost', [
            'id' => $blogId
        ]);
    }

    public function blog_entry_form(array $regions, array $data = null): string
    {
        if (count($regions) < 1) {
            // TODO this is not supposed to happen, handle better
            return '';
        }

        if (is_null($data)) {
            $title = $this->translator->trans('blog.new');
        } else {
            $title = $this->translator->trans('blog.edit');
        }

        $bezirkchoose = '';
        if (count($regions) === 1) {
            // Automatically select this region
            $bezirk = end($regions);
            $title = $this->translator->trans('blog.newTitle', ['{region}' => $bezirk['name']]);
            $bezirkchoose = $this->v_utils->v_form_hidden('bezirk_id', $bezirk['id']);
        } else {
            $bezirkchoose = $this->v_utils->v_form_select('bezirk_id', ['values' => $regions]);
        }

        // create picture upload component
        $initialValue = '';
        if (!is_null($data) && !empty($data['picture'])) {
            if (str_starts_with((string)$data['picture'], '/api/uploads/')) {
                // path for pictures uploaded with the new API
                $initialValue = $data['picture'];
            } else {
                // backward compatible path for old pictures
                $initialValue = 'images/' . $data['picture'];
            }
        }
        $uploadForm = $this->vueComponent('image-upload', 'file-upload-v-form', [
            'inputName' => 'picture',
            'isImage' => true,
            'initialValue' => $initialValue,
            'imgHeight' => self::PICTURE_FULL_WIDTH,
            'imgWidth' => self::PICTURE_FULL_HEIGHT
        ]);

        $this->dataHelper->setEditData($data);

        return $this->v_utils->v_form('test', [
            $this->v_utils->v_field(
                $this->v_utils->v_info($this->translator->trans('blog.publish-info'))
                . $bezirkchoose
                . $this->v_utils->v_form_text('name')
                . $this->v_utils->v_form_textarea('teaser', [
                    'style' => 'height:75px;',
                ])
                . $uploadForm,
                $title,
                ['class' => 'ui-padding']
            ),
            $this->v_utils->v_field($this->v_utils->v_form_tinymce('body', [
                'nowrapper' => true,
                'public_content' => true,
                'label' => $this->translator->trans('blog.content'),
            ]), $this->translator->trans('blog.content')),
            '<a class="button btn btn-primary" onclick="_addBlogPost();return false;">' . $this->translator->trans('button.save') . '</a>'
        ], [
            'submit' => false,
            'action' => '#'
        ]);
    }
}
