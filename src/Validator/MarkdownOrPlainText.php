<?php

namespace Foodsharing\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute] class MarkdownOrPlainText extends Constraint
{
    public string $message = 'The value must be either valid Markdown or plain text.';
}
