<?php

namespace Foodsharing\Modules\Legal;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\RestApi\Models\Legal\LegalAcknowledge;
use Foodsharing\RestApi\Models\Legal\LegalPageData;
use Foodsharing\Utility\EmailHelper;
use Foodsharing\Utility\RouteHelper;

class LegalTransactions
{
    private readonly string $privacyPolicyDate;
    private readonly string $privacyNoticeDate;

    public function __construct(
        private readonly LegalGateway $legalGateway,
        private readonly FoodsaverGateway $foodsaverGateway,
        private readonly EmailHelper $emailHelper,
        private readonly Session $session,
        private readonly RouteHelper $routeHelper
    ) {
        $this->privacyPolicyDate = $this->legalGateway->getPpVersion();
        $this->privacyNoticeDate = $this->legalGateway->getPnVersion();
    }

    public function updateLegalAcknowledge(int $userId, LegalAcknowledge $legalAcknowledge): void
    {
        if ($legalAcknowledge->policy === true) {
            $this->legalGateway->agreeToPrivacyPolicy($userId, $this->privacyPolicyDate);
        }

        if ($legalAcknowledge->notice !== null) {
            if ($legalAcknowledge->notice === true) {
                $this->legalGateway->agreeToPrivacyNotice($userId, $this->privacyNoticeDate);
                $userEmail = $this->foodsaverGateway->getEmailAddress($userId);
                $this->emailHelper->tplMail('user/privacy_notice', $userEmail, ['vorname' => $this->session->user('name')]);
            } elseif ($legalAcknowledge->notice === false) {
                $this->legalGateway->downgradeToFoodsaver($userId);
            }
        }

        try {
            $this->session->refreshFromDatabase();
        } catch (\Exception) {
            $this->routeHelper->goPageAndExit('logout');
        }
    }

    public function getLegalPageData(?int $userId): LegalPageData
    {
        if ($userId) {
            $privacyNoticeNeccessary = $this->session->mayRole(Role::STORE_MANAGER);
            $privacyPolicyAcknowledged = $this->session->user('privacy_policy_accepted_date') == $this->privacyPolicyDate;
            $privacyNoticeAcknowledged = $this->session->user('privacy_notice_accepted_date') == $this->privacyNoticeDate;
        } else {
            $privacyNoticeNeccessary = false;
            $privacyPolicyAcknowledged = false;
            $privacyNoticeAcknowledged = true;
        }

        return LegalPageData::createFrom(
            $privacyNoticeNeccessary,
            $privacyPolicyAcknowledged,
            $privacyNoticeAcknowledged
        );
    }
}
