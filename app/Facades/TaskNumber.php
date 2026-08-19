<?php

namespace App\Facades;

use App\Services\TaskNumberGenerator;
use Illuminate\Support\Facades\Facade;

class TaskNumber extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return TaskNumberGenerator::class;
    }
}
