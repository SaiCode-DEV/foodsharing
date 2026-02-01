<?php

namespace Foodsharing\Utility;

/**
 * A collection of universal regular-expression constants to use as route parameter requirements.
 *
 * @see Symfony\Component\Routing\Requirement\Requirement
 */
final readonly class Requirement
{
    public const string ISO_DATE_TIME = '[0-9]{4}-((0[13578]|1[02])-(0[1-9]|[12][0-9]|3[01])|(0[469]|11)-(0[1-9]|[12][0-9]|30)|(02)-(0[1-9]|[12][0-9]))T(0[0-9]|1[0-9]|2[0-3]):(0[0-9]|[1-5][0-9]):(0[0-9]|[1-5][0-9])\.[0-9]{3,6}Z';

    public const string USER_ID = '[1-9]\d*|current';
}
