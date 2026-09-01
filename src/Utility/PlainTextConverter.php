<?php

declare(strict_types=1);

namespace Foodsharing\Utility;

use Html2Text\Html2Text;

/**
 * Html to plain text conversion that writes inline links as "text url".
 *
 * The library writes them as "text [url]" and mail clients tend to include the closing
 * bracket - and whatever punctuation follows it - in the link they detect. Angle brackets
 * would be the conventional delimiter, but they are not an option here: the library runs
 * strip_tags() over the converted text right after the links have been inserted.
 */
class PlainTextConverter extends Html2Text
{
    protected function buildlinkList($link, $display, $linkOverride = null)
    {
        $method = $linkOverride ?: $this->options['do_links'];
        if ($method !== 'inline') {
            return parent::buildlinkList($link, $display, $linkOverride);
        }

        $display = trim($display);
        if (preg_match('!^(javascript:|mailto:|#)!i', html_entity_decode($link, $this->htmlFuncFlags, self::ENCODING))) {
            return $display;
        }

        $url = $this->absoluteUrl($link);
        if ($display === '' || $display === $url) {
            return $url;
        }

        return $display . ' ' . $url;
    }

    private function absoluteUrl(string $link): string
    {
        if (preg_match('!^([a-z][a-z0-9.+-]+:)!i', $link)) {
            return $link;
        }

        $url = $this->baseurl;
        if (mb_substr($link, 0, 1) !== '/') {
            $url .= '/';
        }

        return $url . $link;
    }
}
