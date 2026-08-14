<?php

namespace App\Commands\Config;

use App\Concerns\ResolvesRepoPath;
use App\Enums\DepsStepKey;
use App\Services\DepsConfigService;
use LaravelZero\Framework\Commands\Command;

class SkipCommand extends Command
{
    use ResolvesRepoPath;

    protected $signature = 'config:skip
        {step : Step to skip (composer.install, composer.post-update-cmd, npm.install, npm.build, pnpm.install, pnpm.build)}
        {path? : Path to the project directory (defaults to the current directory)}
        {--global : Write to the global config instead of this repo}';

    protected $description = 'Add a step to the skip list (global or per-repo)';

    public function handle(DepsConfigService $config): int
    {
        $step = (string) $this->argument('step');

        if (DepsStepKey::tryFrom($step) === null) {
            $valid = implode(', ', array_map(fn (DepsStepKey $k): string => $k->value, DepsStepKey::cases()));
            $this->components->error("Unknown step '{$step}'. Valid steps: {$valid}");

            return self::FAILURE;
        }

        $cwd = $this->resolveCwd();
        $global = (bool) $this->option('global');

        if (! $config->addSkip($cwd, $step, $global)) {
            $this->components->warn("Already skipped: {$step}");

            return self::SUCCESS;
        }

        $this->components->task('Skipping <comment>'.$step.'</comment> ('.($global ? 'global' : 'this repo').')');

        return self::SUCCESS;
    }
}
