<?php

namespace Foodsharing\Modules\Index;

use Foodsharing\Lib\FoodsharingController;
use Foodsharing\Lib\LegacyRoutes;
use Foodsharing\Modules\Content\ContentGateway;
use Foodsharing\Modules\Core\DBConstants\Content\ContentId;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

class IndexController extends FoodsharingController
{
    public function __construct(
        private readonly ContentGateway $contentGateway,
        private readonly UrlHelper $urlHelper,
    ) {
        parent::__construct();
    }

    #[Route('/', name: 'index')]
    public function index(Request $request, #[MapQueryParameter] ?string $page): Response
    {
        if ($page === null) {
            return $this->indexPage($request);
        }

        if (LegacyRoutes::isValidLegacyPageName($page)) {
            return $this->doPortedRedirect($page, $request);
        }

        throw $this->createNotFoundException();
    }

    private function indexPage(Request $request): Response
    {
        $this->pageHelper->addTitle($this->translator->trans('savewithus'));

        $host = (string)$request->server->get('HTTP_HOST', BASE_URL);
        if (str_contains($host, 'foodsharing.at')) {
            $contentIds = [ContentId::STARTPAGE_BLOCK1_AT, ContentId::STARTPAGE_BLOCK2_AT, ContentId::STARTPAGE_BLOCK3_AT];
        } elseif (str_contains($host, 'foodsharingschweiz.ch')) {
            $contentIds = [ContentId::STARTPAGE_BLOCK1_CH, ContentId::STARTPAGE_BLOCK2_CH, ContentId::STARTPAGE_BLOCK3_CH];
        } elseif (str_contains($host, 'beta.foodsharing.de')) {
            $contentIds = [ContentId::STARTPAGE_BLOCK1_BETA, ContentId::STARTPAGE_BLOCK2_BETA, ContentId::STARTPAGE_BLOCK3_BETA];
        } else {
            $contentIds = [ContentId::STARTPAGE_BLOCK1_DE, ContentId::STARTPAGE_BLOCK2_DE, ContentId::STARTPAGE_BLOCK3_DE];
        }

        $page_content_blocks = $this->contentGateway->getMultiple($contentIds);
        $this->pageHelper->addContent($this->prepareVueComponent('index', 'Index', [
            'contentBlock1' => $page_content_blocks[0]['body'],
            'contentBlock2' => $page_content_blocks[1]['body'],
            'contentBlock3' => $page_content_blocks[2]['body']
        ]));

        return $this->renderGlobal();
    }

    // because the highest level routing parameter is always 'page',
    // we can easily port almost all controllers without thought
    // by turning that parameter into a root level path
    // e.g. /?page=content&a=b&c=1 becomes /content?a=b&c=1
    private function doPortedRedirect(string $page, Request $request): Response
    {
        $request->query->remove('page');

        $page = LegacyRoutes::getPortedName($page);
        $newUrl = '/' . $page . '?' . http_build_query($request->query->all());

        // use 307 here because it is guaranteed not to change anything about the request otherwise
        // (could also use 308 here)
        return new RedirectResponse($this->urlHelper->getAbsoluteUrl($newUrl), Response::HTTP_TEMPORARY_REDIRECT);
    }
}
