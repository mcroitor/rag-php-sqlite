<?php

namespace App\Engine\Core\Interfaces;

interface LLMProvider
{
    public function generate(string $prompt): string;
}
