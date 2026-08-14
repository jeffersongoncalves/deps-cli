<?php

namespace App\DTOs;

final readonly class DepsStep
{
    public function __construct(
        public string $label,
        public string $command,
    ) {}
}
