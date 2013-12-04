<?php

namespace Crhayes\Validation;

class Facade extends \Illuminate\Support\Facades\Facade
{
    protected static function getFacadeAccessor()
    {
        return 'groupedValidator';
    }
}
