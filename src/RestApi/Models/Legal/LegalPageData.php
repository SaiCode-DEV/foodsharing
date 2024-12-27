<?php

namespace Foodsharing\RestApi\Models\Legal;

use Symfony\Component\Validator\Constraints as Assert;

class LegalPageData
{
    #[Assert\Type('boolean')]
    #[Assert\IsTrue(message: 'legal.must_accept_pp')]
    public bool $showPrivacyNotice = false;

    #[Assert\Type('boolean')]
    public bool $privacyPolicyAcknowledged = false;

    #[Assert\Type('boolean')]
    public bool $privacyNoticeAcknowledged = false;

    public static function createFrom(
        bool $showPrivacyNotice,
        bool $privacyPolicyAcknowledged,
        bool $privacyNoticeAcknowledged
    ): LegalPageData {
        $model = new LegalPageData();
        $model->showPrivacyNotice = $showPrivacyNotice;
        $model->privacyPolicyAcknowledged = $privacyPolicyAcknowledged;
        $model->privacyNoticeAcknowledged = $privacyNoticeAcknowledged;

        return $model;
    }
}
