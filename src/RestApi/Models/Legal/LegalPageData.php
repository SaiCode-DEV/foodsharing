<?php

namespace Foodsharing\RestApi\Models\Legal;

use Symfony\Component\Validator\Constraints as Assert;

class LegalPageData
{
    #[Assert\Type('boolean')]
    #[Assert\IsTrue(message: 'legal.must_accept_pp')]
    public bool $showPrivacyNotice = false;

    public static function createFrom(bool $showPrivacyNotice): LegalPageData
    {
        $model = new LegalPageData();
        $model->showPrivacyNotice = $showPrivacyNotice;

        return $model;
    }
}
