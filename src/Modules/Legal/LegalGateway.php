<?php

namespace Foodsharing\Modules\Legal;

use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\DBConstants\Content\ContentId;

class LegalGateway extends BaseGateway
{
    public function agreeToPrivacyNotice($userId)
    {
        $pnVersion = $this->db->fetchValue('SELECT `last_mod` FROM fs_content WHERE id = :content_id', [':content_id' => ContentId::PRIVACY_NOTICE_CONTENT]);
        $this->db->update('fs_foodsaver', ['privacy_notice_accepted_date' => $pnVersion], ['id' => $userId]);
    }

    public function agreeToPrivacyPolicy($userId)
    {
        $pnVersion = $this->db->fetchValue('SELECT `last_mod` FROM fs_content WHERE id = :content_id', [':content_id' => ContentId::PRIVACY_POLICY_CONTENT]);
        $this->db->update('fs_foodsaver', ['privacy_policy_accepted_date' => $pnVersion], ['id' => $userId]);
    }
}
