<?php

declare(strict_types=1);

namespace Foodsharing\Modules\Calendar\DTO;

enum FormattingType: string
{
    case ALT = 'alt';
    case HTML = 'html';
    case TEXT = 'text';
}
