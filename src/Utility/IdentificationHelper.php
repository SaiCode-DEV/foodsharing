<?php

namespace Foodsharing\Utility;

use Symfony\Component\HttpFoundation\Request;

class IdentificationHelper
{
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
}
