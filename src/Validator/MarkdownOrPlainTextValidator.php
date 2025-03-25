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

        $environmentStrip = new Environment([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
        $environmentStrip->addExtension(new CommonMarkCoreExtension());
        $converterStrip = new MarkdownConverter($environmentStrip);

        $environmentAllow = new Environment([
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
        ]);
        $environmentAllow->addExtension(new CommonMarkCoreExtension());
        $converterAllow = new MarkdownConverter($environmentAllow);

        try {
            if ($converterStrip->convert($value)->getContent() === $converterAllow->convert($value)->getContent()) {
                return;
            }
        } catch (CommonMarkException) {
        }

        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ value }}', $value)
            ->addViolation();
    }
}
