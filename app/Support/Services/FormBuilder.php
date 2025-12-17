<?php

namespace App\Support\Services;

use FormBuilder\Factory\Iview as Form;
// use FormBuilder\Factory\Elm as Form;

/**
 * Form Builder
 * Class FormBuilder
 *
 * @package App\Support\Services
 */
class FormBuilder extends Form
{
    public static function setOptions($call)
    {
        if (is_array($call)) {
            return $call;
        } else {
            return $call();
        }
    }
}
