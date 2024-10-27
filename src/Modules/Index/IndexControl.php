<?php

namespace Foodsharing\Modules\Index;

use Foodsharing\Modules\Content\ContentGateway;
use Foodsharing\Modules\Core\Control;
use Foodsharing\Modules\Core\DBConstants\Content\ContentId;

class IndexControl extends Control
{
    public function __construct(
        private readonly IndexView $view,
        private readonly ContentGateway $contentGateway
    ) {
        parent::__construct();
    }

    public function index()
    {
        $this->pageHelper->addTitle($this->translator->trans('savewithus'));

        $host = $_SERVER['HTTP_HOST'] ?? BASE_URL;
        if (str_contains((string)$host, 'foodsharing.at')) {
            $contentIds = [ContentId::STARTPAGE_BLOCK1_AT, ContentId::STARTPAGE_BLOCK2_AT, ContentId::STARTPAGE_BLOCK3_AT];
        } elseif (str_contains((string)$host, 'foodsharingschweiz.ch')) {
            $contentIds = [ContentId::STARTPAGE_BLOCK1_CH, ContentId::STARTPAGE_BLOCK2_CH, ContentId::STARTPAGE_BLOCK3_CH];
        } elseif (str_contains((string)$host, 'beta.foodsharing.de')) {
            $contentIds = [ContentId::STARTPAGE_BLOCK1_BETA, ContentId::STARTPAGE_BLOCK2_BETA, ContentId::STARTPAGE_BLOCK3_BETA];
        } else {
            $contentIds = [ContentId::STARTPAGE_BLOCK1_DE, ContentId::STARTPAGE_BLOCK2_DE, ContentId::STARTPAGE_BLOCK3_DE];
        }

        $page_content_blocks = $this->contentGateway->getMultiple($contentIds);
        $this->pageHelper->addContent($this->view->index(
            $page_content_blocks[0]['body'],
            $page_content_blocks[1]['body'],
            $page_content_blocks[2]['body']
        ), CNT_MAIN);
    }
}
