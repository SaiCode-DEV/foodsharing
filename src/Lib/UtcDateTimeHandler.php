<?php

namespace Foodsharing\Lib;

use Carbon\Carbon;
use JMS\Serializer\GraphNavigatorInterface;
use JMS\Serializer\Handler\SubscribingHandlerInterface;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\Visitor\SerializationVisitorInterface;

/**
 * Serializes every datetime in API responses as UTC with a Z suffix, no matter which
 * timezone the PHP object carries (#2760). Before this, the offset in the response
 * depended on how each code path constructed its datetime (Berlin, UTC-labelled or
 * preformatted SQL strings), so clients saw mixed formats for the same instant.
 * Deserialization is not subscribed and stays with the default JMS handler.
 */
final class UtcDateTimeHandler implements SubscribingHandlerInterface
{
    public static function getSubscribingMethods(): array
    {
        return array_map(fn (string $type) => [
            'direction' => GraphNavigatorInterface::DIRECTION_SERIALIZATION,
            'type' => $type,
            'format' => 'json',
            'method' => 'serializeToUtc',
            // The handlers compiler pass sorts descending by priority and lets the last
            // entry win per (direction, type, format), so beating the bundle's default
            // DateHandler (priority 0) requires a priority below zero.
            'priority' => -100,
        ], ['DateTime', 'DateTimeImmutable', 'DateTimeInterface', 'Carbon\Carbon', 'Carbon\CarbonImmutable']);
    }

    public function serializeToUtc(SerializationVisitorInterface $visitor, \DateTimeInterface $date, array $type, SerializationContext $context)
    {
        // Properties with an explicit format (e.g. #[Type("DateTime<'Y-m-d'>")] for
        // date-only fields) keep that format and the object's own timezone - converting
        // a Berlin midnight to UTC would shift date-only values to the previous day.
        if (isset($type['params'][0])) {
            return $visitor->visitString($date->format($type['params'][0]), $type);
        }

        // Carbon converts to UTC itself and renders the Z suffix, so the offset is
        // derived from the value instead of asserted by an escaped format literal.
        return $visitor->visitString(Carbon::instance($date)->toIso8601ZuluString(), $type);
    }
}
