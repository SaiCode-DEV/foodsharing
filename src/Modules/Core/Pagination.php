<?php

namespace Foodsharing\Modules\Core;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class Pagination
{
    final public const int DEFAULT_LIMIT = 30;

    /**
     * Count of item per page.
     */
    public int $limit;

    /**
     * Offset to start.
     */
    public int $offset;

    /**
     * @throws BadRequestHttpException
     */
    public static function create(?int $limit = null, ?int $offset = null): self
    {
        $pagination = new Pagination();
        $pagination->limit = self::assertMinValueWithDefault($limit, 1, self::DEFAULT_LIMIT);
        $pagination->offset = self::assertMinValueWithDefault($offset, 0, 0);

        return $pagination;
    }

    private static function assertMinValueWithDefault(?int $value, int $min, int $default): int
    {
        if (is_null($value)) {
            return $default;
        }
        if ($value < $min) {
            throw new BadRequestHttpException('Pagination parameter required to be at least ' . $min);
        }

        return $value;
    }
}
