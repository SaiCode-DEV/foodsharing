<?php

namespace Foodsharing\Validator;

use InvalidArgumentException;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Exception\CommonMarkException;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class MarkdownOrPlainTextValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof MarkdownOrPlainText) {
            throw new InvalidArgumentException('Expected constraint of type MarkdownOrPlainText');
        }

        if (null === $value || '' === $value) {
            return;
        }

        $environment = new Environment([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $converter = new MarkdownConverter($environment);

        try {
            $converter->convert($value);

            return;
        } catch (CommonMarkException) {
            if ($value === strip_tags((string)$value)) {
                return;
            }
        }

        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ value }}', $value)
            ->addViolation();
    }
}
