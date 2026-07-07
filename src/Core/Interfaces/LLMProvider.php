<?php

namespace App\Core\Interfaces;

interface LLMProvider
{
    public function generate(string $prompt): string;
}
