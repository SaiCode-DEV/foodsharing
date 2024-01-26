<?php

namespace Foodsharing\Modules\Core\DBConstants\Mailbox;

/**
 * IDs for mailbox folders. Column `folder` in table `fs_mailbox_message`.
 */
class MailboxFolder
{
    final public const FOLDER_INBOX = 1;
    final public const FOLDER_SENT = 2;
    final public const FOLDER_TRASH = 3;
}
