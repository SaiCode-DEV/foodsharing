<?php

namespace Foodsharing\Modules\Legal;

use Foodsharing\Lib\FoodsharingController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LegalController extends FoodsharingController
{
    public function __construct(
    ) {
        parent::__construct();
    }

    /**
     * @throws \Exception
     */
    #[Route('/legal', name: 'legal')]
    public function index(): Response
    {
        $legalPage = $this->prepareVueComponent('legal-page', 'LegalPage');
        $this->pageHelper->addContent($legalPage);

        return $this->renderGlobal();
    }
}
