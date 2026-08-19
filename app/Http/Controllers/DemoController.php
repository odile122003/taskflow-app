<?php

namespace App\Http\Controllers;

use App\Facades\TaskNumber;
use App\Services\TaskNumberGenerator;

class DemoController extends Controller
{
    public function taskNumber(TaskNumberGenerator $generator)
    {
        return [
            'via_injection_1' => $generator->next(),
            'via_injection_2' => $generator->next(),
            'via_facade_1' => TaskNumber::next(),
            'via_facade_2' => TaskNumber::next(),
        ];
    }
}
