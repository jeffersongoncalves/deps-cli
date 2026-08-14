<?php

namespace App\DTOs;

use App\Enums\DepsStepKey;

final readonly class DepsStep
{
    public function __construct(
        public string $label,
        public string $command,
        public ?DepsStepKey $key = null,
    ) {}
}
