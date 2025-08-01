<?php

namespace Foodsharing\Modules\Content;

use Foodsharing\Lib\FoodsharingController;
use Foodsharing\Modules\Core\DBConstants\Content\ContentId;
use Foodsharing\Modules\Core\DBConstants\Region\RegionIDs;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\Region\RegionTransactions;
use Foodsharing\Permissions\ContentPermissions;
use Foodsharing\Utility\IdentificationHelper;
use Parsedown;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class ContentController extends FoodsharingController
{
    private const array SUB_TO_ID = [
        'presse' => ContentId::PRESS,
        'forderungen' => ContentId::DEMANDS,
        'academy' => ContentId::ACADEMY,
        'festival' => ContentId::FESTIVAL,
        'transparency' => ContentId::TRANSPARENCY,
        'leeretonne' => ContentId::PAST_CAMPAIGNS,
        'foodSharePointRescue' => ContentId::RESCUE_FOOD_SHARE_POINT,
        'impressum' => ContentId::IMPRINT,
        'about' => ContentId::ABOUT,
        'fuer_unternehmen' => ContentId::FOR_COMPANIES,
        'fsstaedte' => ContentId::FOODSHARING_CITIES,
        'workshops' => ContentId::WORKSHOPS,
        'security' => ContentId::SECURITY_PAGE,
    ];

    private const array REDIRECT = [
        'communitiesGermany' => 'communities',
        'communitiesAustria' => 'communities',
        'communitiesSwitzerland' => 'communities',
        'international' => 'communities',
    ];

    public function __construct(
        private readonly ContentView $view,
        private readonly ContentGateway $contentGateway,
        private readonly IdentificationHelper $identificationHelper,
        private readonly ContentPermissions $contentPermissions,
        private readonly RegionTransactions $regionTransactions,
        private readonly RegionGateway $regionGateway,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    #[Route(path: '/content/{name}', name: 'content_show', requirements: ['name' => '[A-Za-z_]+'])]
    public function show(Request $request, string $name): Response
    {
        return $this->viewContent($request, $name);
    }

    #[Route(path: '/content', name: 'content_index')]
    public function index(Request $request): Response
    {
        if ($sub = $request->query->get('sub')) {
            return $this->viewContent($request, $sub);
        }

        if (!$this->contentPermissions->mayEditContent()) {
            return $this->redirect('/');
        }

        if ($this->identificationHelper->getAction($request, 'new')) {
            $this->pageHelper->addBread($this->translator->trans('content.bread'), '/content');
            $this->pageHelper->addBread($this->translator->trans('content.new'));

            $this->pageHelper->addContent($this->prepareVueComponent('content-edit', 'ContentEdit'));
        } elseif ($id = $this->identificationHelper->getActionId($request, 'delete')) {
            if ($this->contentGateway->delete($id)) {
                $this->flashMessageHelper->success($this->translator->trans('content.delete_success'));
                $this->routeHelper->goPageAndExit();
            }
        } elseif ($id = $this->identificationHelper->getActionId($request, 'edit')) {
            if (!$this->contentPermissions->mayEditContentId((int)$request->query->get('id'))) {
                return $this->redirect('/content');
            }
            $this->pageHelper->addBread($this->translator->trans('content.bread'), '/content');
            $this->pageHelper->addBread($this->translator->trans('content.edit'));

            $this->pageHelper->addContent(
                $this->prepareVueComponent('content-edit', 'ContentEdit', ['contentId' => $id])
            );
        } elseif ($id = $this->identificationHelper->getActionId($request, 'view')) {
            $this->addContent($id);
        } elseif ($request->query->has('id')) {
            return $this->redirect('/content?a=edit&id=' . (int)$request->query->get('id'));
        } else {
            $this->pageHelper->addBread($this->translator->trans('content.public'), '/content');

            $this->pageHelper->addContent($this->prepareVueComponent('content-list', 'ContentList', [
                'mayEditContent' => $this->contentPermissions->mayEditContent(),
                'mayCreateContent' => $this->contentPermissions->mayCreateContent(),
            ]));
        }

        return $this->renderGlobal();
    }

    #[Route(path: '/partner', name: 'partner')]
    public function partnerPage(Request $request): Response
    {
        $host = (string)$request->server->get('HTTP_HOST', BASE_URL);
        $contentId = ContentId::PARTNER_PAGE_93;
        if (str_contains($host, 'foodsharing.at')) {
            $contentId = ContentId::PARTNER_PAGE_AT_94;
        }
        $this->pageHelper->addTitle($this->translator->trans('footer.our_partner'));
        $this->pageHelper->addContent($this->prepareVueComponent('content-partner', 'Partner',
            ['contentId' => $contentId]
        ));

        return $this->renderGlobal();
    }

    public function joininfo(): Response
    {
        $this->pageHelper->addBread($this->translator->trans('startpage.join'));
        $this->pageHelper->addTitle($this->translator->trans('startpage.join_rules'));

        $this->pageHelper->addContent($this->prepareVueComponent('vue-join-info', 'JoinInfo'));

        return $this->renderGlobal();
    }

    public function contact(): Response
    {
        $this->pageHelper->addBread($this->translator->trans('contact_page.bread'));
        $this->pageHelper->addTitle($this->translator->trans('contact_page.title'));

        $this->pageHelper->addContent($this->prepareVueComponent('vue-contact-page', 'ContactPage'));

        return $this->renderGlobal();
    }

    public function releaseNotes(): Response
    {
        $releaseIds = [
            '2025-08',
            '2024-12',
            '2024-08',
            '2024-04',
            '2024-01',
            '2023-09',
            '2022-12',
            '2022-05',
            '2022-01',
            '2021-09',
            '2021-03',
            '2020-12',
            '2020-10',
            '2020-08',
            '2020-05',
        ];
        $releaseList = array_map(fn ($id) => [
            'id' => $id,
            'title' => $this->translator->trans('releases.' . $id),
            'markdown' => $this->parseGitlabLinks($this->getNotes($id)),
            'visible' => false,
        ], $releaseIds);
        $releaseList[0]['visible'] = true;

        $this->pageHelper->addContent($this->prepareVueComponent('vue-release-notes', 'ReleaseNotes', [
            'releaseList' => $releaseList,
        ]));

        return $this->renderGlobal();
    }

    public function changelog(): Response
    {
        $this->pageHelper->addBread($this->translator->trans('content.changelog'));
        $this->pageHelper->addTitle($this->translator->trans('content.changelog'));

        $markdown = $this->parseGitlabLinks(file_get_contents($this->projectDir . '/CHANGELOG.md') ?: '');
        $Parsedown = new Parsedown();
        $cl['title'] = $this->translator->trans('content.changelog');
        $cl['body'] = $Parsedown->parse($markdown);
        $this->pageHelper->addContent($this->view->simple($cl));

        return $this->renderGlobal();
    }

    public function communities(): Response
    {
        $this->pageHelper->addTitle($this->translator->trans('content.communities.title'));

        $regionsList = $this->regionGateway->getRegionsForCommunitiesContacts();
        $regionHierarchie = $this->regionTransactions->buildRegionHierarchie($regionsList, RegionIDs::EUROPE);
        $this->pageHelper->addContent($this->prepareVueComponent('vue-communities', 'communities', [
            'rootRegion' => $regionHierarchie,
        ]));

        return $this->renderGlobal();
    }

    private function getNotes(string $filename): string
    {
        return file_get_contents($this->projectDir . '/release-notes/' . $filename . '.md') ?: '';
    }

    private function parseGitlabLinks($markdown)
    {
        $markdown = preg_replace('/\W@(\S+)/', ' [@\1](https://gitlab.com/\1)', (string)$markdown) ?? $markdown;
        $markdown = preg_replace(
            '/(android)!([0-9]+)/',
            '[\1!\2](https://gitlab.com/foodsharing-dev/foodsharing-android/merge_requests/\2)',
            (string)$markdown
        ) ?? $markdown;
        $markdown = preg_replace(
            '/(android)#([0-9]+)/',
            '[\1#\2](https://gitlab.com/foodsharing-dev/foodsharing-android/issues/\2))',
            (string)$markdown
        ) ?? $markdown;
        $markdown = preg_replace(
            '/\W!([0-9]+)/',
            ' [!\1](https://gitlab.com/foodsharing-dev/foodsharing/merge_requests/\1)',
            (string)$markdown
        ) ?? $markdown;
        $markdown = preg_replace(
            '/\W#([0-9]+)/',
            ' [#\1](https://gitlab.com/foodsharing-dev/foodsharing/issues/\1)',
            (string)$markdown
        ) ?? $markdown;

        return $markdown;
    }

    private function addContent(int $contentId): void
    {
        $content = $this->contentGateway->getContent($contentId);
        if ($content) {
            $this->pageHelper->addTitle($content->title);
            $this->pageHelper->addContent($this->prepareVueComponent('vue-content', 'ContentEntry', [
                'id' => $contentId
            ]));
        }
    }

    private function viewContent(Request $request, string $name): Response
    {
        if (key_exists($name, self::SUB_TO_ID)) {
            $this->addContent(self::SUB_TO_ID[$name]);

            return $this->renderGlobal();
        } elseif (is_callable([$this, $name])) {
            return $this->$name($request);
        } elseif (key_exists($name, self::REDIRECT)) {
            return $this->redirect('/content?sub=' . self::REDIRECT[$name]);
        } else {
            throw new NotFoundHttpException();
        }
    }
}
