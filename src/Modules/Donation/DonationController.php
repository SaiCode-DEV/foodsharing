<?php

namespace Foodsharing\Modules\Donation;

use Foodsharing\Lib\FoodsharingController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DonationController extends FoodsharingController
{
    public function __construct()
    {
        parent::__construct();
    }

    #[Route('/donation/admin')]
    public function adminIndex(): Response
    {
        $this->pageHelper->addTitle($this->translator->trans('donation_admin_page.title'));

        $donationPage = $this->prepareVueComponent('vue-donation-admin-page', 'DonationAdminPage');
        $this->pageHelper->addContent($donationPage);

        return $this->renderGlobal();
    }

    /**
     * The different routes are distinguished in the frontend.
     */
    #[Route('/donation', name: 'donation_page')]
    #[Route('/donation/{type}', name: 'donation_page_type', requirements: ['type' => 'selfservice|campaign|onetime|friendship_circle'])]
    public function index(): Response
    {
        $this->pageHelper->addTitle($this->translator->trans('donation_page.title'));

        $donationPage = $this->prepareVueComponent('vue-donation-page', 'DonationPage');
        $this->pageHelper->addContent($donationPage);

        return $this->renderGlobal();
    }
}
