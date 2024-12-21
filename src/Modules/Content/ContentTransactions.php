<?php

namespace Foodsharing\Modules\Content;

use Foodsharing\Lib\Db\Mem;
use Foodsharing\Modules\Core\DBConstants\Content\ContentId;
use Foodsharing\RestApi\Models\Content\ContentEntry;

class ContentTransactions
{
    public function __construct(
        private readonly ContentGateway $contentGateway,
        private readonly Mem $mem,
    ) {
    }

    /**
     * Updates the content entry in the database and runs post-processing, in case that is necessary for the entry.
     */
    public function update(int $contentId, ContentEntry $content): void
    {
        $this->contentGateway->update($contentId, $content);

        // Privacy policy and privacy notice are stored in Redis
        switch ($contentId) {
            case ContentId::PRIVACY_POLICY_CONTENT:
                $privacyPolicy = $this->contentGateway->getContent(ContentId::PRIVACY_POLICY_CONTENT);
                $this->mem->set(Mem::PRIVATE_POLICY_REDIS_KEY, $privacyPolicy->lastModified->format('Y-m-d H:i:s'));
                break;
            case ContentId::PRIVACY_NOTICE_CONTENT:
                $privacyNotice = $this->contentGateway->getContent(ContentId::PRIVACY_NOTICE_CONTENT);
                $this->mem->set(Mem::PRIVATE_NOTICE_REDIS_KEY, $privacyNotice->lastModified->format('Y-m-d H:i:s'));
                break;
        }
    }
}
