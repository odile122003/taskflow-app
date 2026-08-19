<?php

namespace App\Services;

class TaskNumberGenerator
{
    private int $counter = 0;

    public function next(): string
    {
        $this->counter++;

        return sprintf('TASK-%04d', $this->counter);
    }
}
