<?php

namespace App\Modifiers;

use Statamic\Modifiers\Modifier;

class AreaWidth extends Modifier
{
    public function index($value, $params, $context)
    {
        $width = $value->width;
        $height = $value->height;
        $ratio = $height / $width;
        $area = $params[0];

        return sqrt($area / $ratio);
    }
}
