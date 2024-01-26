<?php

namespace Foodsharing\Utility;

/**
 * @deprecated please use the Request object's request property instead
 */
class PostHelper
{
    public function getPostDate($name): bool|int
    {
        if ($date = $this->getPostString($name)) {
            $date = explode(' ', $date);
            $date = trim($date[0]);
            if (!empty($date)) {
                $date = explode('-', $date);
                if (count($date) == 3 && (int)$date[0] > 0 && (int)$date[1] > 0 && (int)$date[2] > 0) {
                    return mktime(0, 0, 0, (int)$date[1], (int)$date[2], (int)$date[0]);
                }
            }
        }

        return false;
    }

    public function getPostTime($name): array|bool
    {
        if (isset($_POST[$name]['hour'], $_POST[$name]['min'])) {
            return [
                'hour' => (int)$_POST[$name]['hour'],
                'min' => (int)$_POST[$name]['min']
            ];
        }

        return false;
    }

    public function getPostString($name): bool|string
    {
        if ($val = $this->getPost($name)) {
            $val = strip_tags((string)$val);
            $val = trim($val);

            if (!empty($val)) {
                return $val;
            }
        }

        return false;
    }

    public function getPostInt($name): bool|int
    {
        if ($val = $this->getPost($name)) {
            $val = trim((string)$val);

            return (int)$val;
        }

        return false;
    }

    public function getPost($name)
    {
        if (!empty($_POST[$name])) {
            return $_POST[$name];
        }

        return false;
    }
}
