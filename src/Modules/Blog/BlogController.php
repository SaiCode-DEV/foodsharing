<?php

namespace Foodsharing\Modules\Blog;

use Foodsharing\Lib\FoodsharingController;
use Foodsharing\Permissions\BlogPermissions;
use Foodsharing\Utility\IdentificationHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BlogController extends FoodsharingController
{
    public function __construct(
        private readonly BlogGateway $blogGateway,
        private readonly BlogPermissions $blogPermissions,
        private readonly IdentificationHelper $identificationHelper,
    ) {
        parent::__construct();
    }

    #[Route(path: '/blog', name: 'blog')]
    #[Route(path: '/news', name: 'news')]
    public function index(Request $request): Response
    {
        $this->common($request);

        if (!$request->query->has('sub')) {
            $this->listNews();
        } else {
            match ($request->query->get('sub')) {
                'listNews' => $this->listNews(),
                'read' => $this->read((int)$request->get('id')),
                'manage' => $this->manage(),
                'add', 'edit' => $this->addOrEdit($request),
                default => throw $this->createNotFoundException()
            };
        }

        return $this->renderGlobal();
    }

    #[Route(path: '/blog/{id}', name: 'blog_id', requirements: ['id' => '\d+'])]
    public function blogById(Request $request, int $id): Response
    {
        $this->common($request);
        $this->read($id);

        return $this->renderGlobal();
    }

    private function listNews(): void
    {
        $this->pageHelper->addContent($this->prepareVueComponent('blog-post-list', 'BlogPostList'));
    }

    private function read(int $id): void
    {
        if ($news = $this->blogGateway->getPost($id)) {
            $this->pageHelper->addBread($news->title);
            $this->pageHelper->addContent(
                $this->prepareVueComponent('blog-post', 'BlogPost', [
                'id' => $news->id,
            ]));
        }
    }

    private function manage(): void
    {
        if (!$this->blogPermissions->mayAdministrateBlog()) {
            $this->handleAccessDenied('blog.permissions.new');

            return;
        }

        $this->pageHelper->addBread($this->translator->trans('blog.manage'));
        $data = $this->blogGateway->getBlogpostList();
        if (!$data) {
            $this->flashMessageHelper->info($this->translator->trans('blog.empty'));

            return;
        }

        $this->pageHelper->addContent(
            $this->prepareVueComponent('vue-blog-overview', 'BlogOverview', [
                'mayAdministrateBlog' => $this->blogPermissions->mayAdministrateBlog(),
                'managedRegions' => $this->currentUserUnits->getMyAmbassadorRegionIds(),
                'blogList' => $data,
            ])
        );
    }

    private function addOrEdit(Request $request): void
    {
        if (!($this->blogPermissions->mayAdministrateBlog() && $this->blogPermissions->mayAdd())) {
            $this->handleAccessDenied('blog.permissions.new');

            return;
        }
        if ($request->query->get('sub') !== 'add') {
            $blogId = $request->query->getInt('id');
            $data = $this->blogGateway->getOne_blog_entry($blogId);
            if (!$data) {
                $this->handleAccessDenied('blog.permissions.edit');

                return;
            }

            $componentParams = [
                'blogId' => $blogId,
                'regionId' => $data['bezirk_id'],
            ];
        }

        $blogEditForm = $this->prepareVueComponent('blog-edit-form', 'BlogEditForm', $componentParams ?? []);

        $this->pageHelper->addBread($this->translator->trans('blog.all'), '/blog?sub=manage');
        if ($request->query->get('sub') === 'add') {
            $this->pageHelper->addBread($this->translator->trans('blog.new'));
        } else {
            $this->pageHelper->addBread($this->translator->trans('blog.edit'));
        }

        $this->pageHelper->addContent($blogEditForm);

        return;
    }

    private function common(Request $request): void
    {
        if ($id = $this->identificationHelper->getActionId($request, 'delete')) {
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

    private function handleAccessDenied(string $messageKey): void
    {
        $this->flashMessageHelper->info($this->translator->trans($messageKey));
        $this->routeHelper->goPageAndExit();
    }
}
