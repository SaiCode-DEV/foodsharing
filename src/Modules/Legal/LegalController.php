<?php

namespace Foodsharing\Modules\Legal;

use Foodsharing\Lib\FoodsharingController;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Service\Attribute\Required;

class LegalController extends FoodsharingController
{
    private FormFactoryInterface $formFactory;

    public function __construct(
        private readonly LegalGateway $gateway,
        private readonly FoodsaverGateway $foodsaverGateway,
    ) {
        parent::__construct();
    }

    #[Required]
    public function setFormFactory(FormFactoryInterface $formFactory)
    {
        $this->formFactory = $formFactory;
    }

    /**
     * @throws \Exception
     */
    #[Route('/legal', name: 'legal')]
    public function index(Request $request): Response
    {
        $privacyPolicyDate = $this->gateway->getPpVersion();
        $privacyNoticeDate = $this->gateway->getPnVersion();

        if ($this->session->id()) {
            $privacyNoticeNeccessary = $this->session->mayRole(Role::STORE_MANAGER);
            $privacyPolicyAcknowledged = $this->session->user('privacy_policy_accepted_date') == $privacyPolicyDate;
            $privacyNoticeAcknowledged = $this->session->user('privacy_notice_accepted_date') == $privacyNoticeDate;
            $data = new LegalData($privacyPolicyAcknowledged, $privacyNoticeNeccessary ? $privacyNoticeAcknowledged : true);
        } else {
            $privacyNoticeNeccessary = false;
            $data = new LegalData(false, true);
        }
        $form = $this->formFactory->create(LegalForm::class, $data);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->gateway->agreeToPp($this->session->id(), $privacyPolicyDate);
            if ($privacyNoticeNeccessary) {
                if ($data->isPrivacyNoticeAcknowledged()) {
                    $this->gateway->agreeToPn($this->session->id(), $privacyNoticeDate);
                    $userEmail = $this->foodsaverGateway->getEmailAddress($this->session->id());
                    $this->emailHelper->tplMail('user/privacy_notice', $userEmail, ['vorname' => $this->session->user('name')]);
                } else {
                    $this->gateway->downgradeToFoodsaver($this->session->id());
                }
            }

            try {
                $this->session->refreshFromDatabase();
                $this->routeHelper->goSelfAndExit();
            } catch (\Exception) {
                $this->routeHelper->goPageAndExit('logout');
            }
        }

        $legalPageData = [
            'privacyPolicyContent' => $this->gateway->getPp(),
            'privacyNoticeContent' => $this->gateway->getPn(),
            'showPrivacyNotice' => $privacyNoticeNeccessary,
            'loggedIn' => $this->session->mayRole(),
            'form' => $form->createView()
        ];

        return $this->renderGlobal('pages/Legal/page.twig', $legalPageData);
    }
}
