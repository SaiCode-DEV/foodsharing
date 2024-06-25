<?php

namespace Foodsharing\Modules\Blog;

use Foodsharing\Lib\FoodsharingController;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Core\DBConstants\Uploads\UploadUsage;
use Foodsharing\Modules\Uploads\UploadsGateway;
use Foodsharing\Permissions\BlogPermissions;
use Foodsharing\Utility\IdentificationHelper;
use Foodsharing\Utility\TimeHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class BlogController extends FoodsharingController
{
    public function __construct(
        private readonly BlogView $view,
        private readonly BlogGateway $blogGateway,
        private readonly BlogPermissions $blogPermissions,
        private readonly IdentificationHelper $identificationHelper,
        private readonly TimeHelper $timeHelper,
        private readonly UploadsGateway $uploadsGateway,
    ) {
        parent::__construct();
    }

    #[Route(path: '/blog', name: 'blog')]
    #[Route(path: '/news', name: 'news')]
    public function index(Request $request): Response
    {
        $this->common();

        if (!$request->query->has('sub')) {
            $this->listNews($request);
        } else {
            match ($request->query->get('sub')) {
                'listNews' => $this->listNews($request),
                'read' => $this->read((int)$request->get('id')),
                'manage' => $this->manage(),
                'post' => $this->post($request),
                'add' => $this->add(),
                'edit' => $this->edit($request),
                default => throw $this->createNotFoundException()
            };
        }

        return $this->renderGlobal();
    }

    #[Route(path: '/blog/{id}', name: 'blog_id', requirements: ['id' => '\d+'])]
    public function blogById(Request $request, int $id): Response
    {
        $this->common();
        $this->read($id);

        return $this->renderGlobal();
    }

    private function listNews(Request $request): void
    {
        $this->pageHelper->addContent($this->view->vueComponent('blog-post-list', 'BlogPostList'));
    }

    private function read(int $id): void
    {
        if ($news = $this->blogGateway->getPost($id)) {
            $this->pageHelper->addBread($news->title);
            $this->pageHelper->addContent($this->view->newsPost($news->id));
        }
    }

    private function manage(): void
    {
        if ($this->blogPermissions->mayAdministrateBlog()) {
            $this->pageHelper->addBread($this->translator->trans('blog.manage'));

            if ($data = $this->blogGateway->getBlogpostList()) {
                $this->pageHelper->addContent($this->view->blogpostOverview($data));
            } else {
                $this->flashMessageHelper->info($this->translator->trans('blog.empty'));
            }
        }
    }

    private function post(Request $request): void
    {
        if (!$this->blogPermissions->mayAdministrateBlog() || !$request->query->has('id')) {
            return;
        }
        $post = $this->blogGateway->getOne_blog_entry((int)$request->query->get('id'));

        if (!$post || $post['active'] != 1) {
            return;
        }
        $this->pageHelper->addTitle($post['name']);
        $this->pageHelper->addBread($post['name'], '/blog?post=' . (int)$post['id']);

        $when = $this->timeHelper->niceDate($post['time_ts']);
        $this->pageHelper->addContent($this->view->topbar($post['name'], $when));
        $this->pageHelper->addContent($this->v_utils->v_field($post['body'], $post['name'], [
            'class' => 'ui-padding',
        ]));
    }

    private function add(): void
    {
        if ($this->blogPermissions->mayAdministrateBlog()) {
            $this->handle_add();

            $this->pageHelper->addBread($this->translator->trans('blog.new'));

            $regions = $this->currentUserUnits->getRegions();
            if (!$this->session->mayRole(Role::ORGA)) {
                $bot_ids = $this->currentUserUnits->getMyAmbassadorRegionIds();
                foreach ($regions as $k => $v) {
                    if (!UnitType::isGroup($v['type']) || !in_array($v['id'], $bot_ids)) {
                        unset($regions[$k]);
                    }
                }
            }

            $this->pageHelper->addContent($this->view->blog_entry_form($regions));

            $this->pageHelper->addContent($this->v_utils->v_field($this->v_utils->v_menu([
                ['href' => '/blog', 'name' => $this->translator->trans('bread.backToOverview')]
            ]), $this->translator->trans('blog.actions')), CNT_LEFT);
        } else {
            $this->flashMessageHelper->info($this->translator->trans('blog.permissions.new'));
            $this->routeHelper->goPageAndExit();
        }
    }

    private function handle_add(): void
    {
        global $g_data;

        if ($this->blogPermissions->mayAdministrateBlog() && !empty($_POST)) {
            $g_data['foodsaver_id'] = $this->session->id();
            $g_data['time'] = date('Y-m-d H:i:s');

            if (!$this->blogPermissions->mayAdd()) {
                $this->flashMessageHelper->error($this->translator->trans('blog.failure.new'));
            } else {
                $postId = $this->blogGateway->add_blog_entry($g_data);
                if ($postId) {
                    if (!empty($g_data['picture'])) {
                        $uuid = substr($g_data['picture'], 13);
                        $this->uploadsGateway->setUsage([$uuid], UploadUsage::BLOG_POST, $postId);
                    }

                    $this->flashMessageHelper->success($this->translator->trans('blog.success.new'));
                    $this->routeHelper->goPageAndExit();
                } else {
                    $this->flashMessageHelper->error($this->translator->trans('blog.failure.new'));
                }
            }
        }
    }

    private function edit(Request $request): void
    {
        $blogId = $request->query->get('id');
        if ($this->blogPermissions->mayAdministrateBlog() && $this->blogPermissions->mayEdit($blogId) && ($data = $this->blogGateway->getOne_blog_entry($blogId))) {
            $this->handle_edit($request);

            $this->pageHelper->addBread($this->translator->trans('blog.all'), '/blog?sub=manage');
            $this->pageHelper->addBread($this->translator->trans('blog.edit'));

            $regions = $this->currentUserUnits->getRegions();

            $this->pageHelper->addContent($this->view->blog_entry_form($regions, $data));
        } else {
            $this->flashMessageHelper->info($this->translator->trans('blog.permissions.edit'));
            $this->routeHelper->goPageAndExit();
        }
    }

    private function handle_edit(Request $request): void
    {
        global $g_data;
        if ($this->blogPermissions->mayAdministrateBlog() && !empty($_POST)) {
            $id = $request->query->get('id');
            $data = $this->blogGateway->getOne_blog_entry($id);

            $g_data['foodsaver_id'] = $data['foodsaver_id'];
            $g_data['time'] = $data['time'];

            if ($this->blogGateway->update_blog_entry($id, $g_data)) {
                if (!empty($g_data['picture'])) {
                    $uuid = substr($g_data['picture'], 13);
                    $this->uploadsGateway->setUsage([$uuid], UploadUsage::BLOG_POST, $id);
                }

                $this->flashMessageHelper->success($this->translator->trans('blog.success.edit'));
                $this->routeHelper->goPageAndExit('blog', ['sub' => 'manage']);
            } else {
                $this->flashMessageHelper->error($this->translator->trans('blog.failure.edit'));
            }
        }
    }

    private function common(): void
    {
        if ($id = $this->identificationHelper->getActionId('delete')) {
            if ($this->blogPermissions->mayEdit($id)) {
                if ($this->blogGateway->del_blog_entry($id)) {
                    $this->flashMessageHelper->success($this->translator->trans('blog.success.delete'));
                } else {
                    $this->flashMessageHelper->error($this->translator->trans('blog.failure.delete'));
                }
            } else {
                $this->flashMessageHelper->info($this->translator->trans('blog.permissions.delete'));
            }
            $this->routeHelper->goPageAndExit();
        }
        $this->pageHelper->addBread($this->translator->trans('blog.bread'), '/blog');
        $this->pageHelper->addTitle($this->translator->trans('blog.bread'));
    }
}
