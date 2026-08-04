<?php

namespace Foodsharing\Utility;

use Sentry\Event;
use Sentry\EventHint;

class SentryScrubber
{
    private const SENSITIVE_FIELDS = [
        'msg',
        'email',
        'b',
        'nachname',
        'db_pass',
        'google_api_key',
        'bounce_imap_pass',
        'password',
        'passwd',
        'pass',
        'pw'
    ];

    // sentry's before_send option takes a service reference to an invokable
    public function __invoke(Event $event, ?EventHint $hint): ?Event
    {
        return self::scrub($event, $hint);
    }

    public static function scrub(Event $event, ?EventHint $hint): ?Event
    {
        $request = $event->getRequest();
        if (!empty($request)) {
            $event->setRequest(self::scrubData($request));
        }

        $contexts = $event->getContexts();
        if (!empty($contexts)) {
            foreach ($contexts as $name => $data) {
                if (is_array($data)) {
                    $event->setContext($name, self::scrubData($data));
                }
            }
        }

        $extra = $event->getExtra();
        if (!empty($extra)) {
            $event->setExtra(self::scrubData($extra));
        }

        $breadcrumbs = $event->getBreadcrumbs();
        if (!empty($breadcrumbs)) {
            $scrubbedBreadcrumbs = [];
            foreach ($breadcrumbs as $breadcrumb) {
                $data = $breadcrumb->getMetadata();
                if (!empty($data)) {
                    $scrubbedData = self::scrubData($data);
                    // Re-add all keys
                    foreach ($scrubbedData as $key => $value) {
                        $breadcrumb = $breadcrumb->withMetadata((string)$key, $value);
                    }
                }
                $scrubbedBreadcrumbs[] = $breadcrumb;
            }
            $event->setBreadcrumb($scrubbedBreadcrumbs);
        }

        return $event;
    }

    private static function scrubData(mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }

        $scrubbed = [];
        foreach ($data as $key => $value) {
            $lowerKey = strtolower((string)$key);

            if (in_array($lowerKey, self::SENSITIVE_FIELDS, true)) {
                $scrubbed[$key] = '[Filtered]';
            } else {
                $scrubbed[$key] = self::scrubData($value);
            }
        }

        return $scrubbed;
    }
}
