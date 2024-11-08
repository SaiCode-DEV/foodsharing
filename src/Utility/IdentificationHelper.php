<?php

namespace Foodsharing\Utility;

use Symfony\Component\HttpFoundation\Request;

class IdentificationHelper
{
    private array $ids = [];

    public function getAction(Request $request, $a): bool
    {
        return $request->query->has('a') && $request->query->get('a') == $a;
    }

    public function getActionId(Request $request, $a): bool|int
    {
        if ($this->getAction($request, $a) && $request->query->has('id')) {
            $id = (int)$request->query->get('id');
            if ($id > 0) {
                return $id;
            }
        }

        return false;
    }

    public function makeId($text, $ids = false)
    {
        $text = strtolower((string)$text);
        str_replace(
            ['ä', 'ö', 'ü', 'ß', ' '],
            ['ae', 'oe', 'ue', 'ss', '_'],
            $text
        );
        $out = preg_replace('/[^a-z0-9_]/', '', $text);

        if ($ids !== false && isset($ids[$out])) {
            $id = $out;
            $i = 0;
            while (isset($ids[$id])) {
                ++$i;
                $id = $out . '-' . $i;
            }
            $out = $id;
        }

        return $out;
    }

    public function id($name)
    {
        $id = $this->makeId($name, $this->ids);

        $this->ids[$id] = true;

        return $id;
    }
}
