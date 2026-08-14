<?php

namespace App\Commands\Config;

use App\Concerns\ResolvesRepoPath;
use App\Services\DepsConfigService;
use LaravelZero\Framework\Commands\Command;

class UnskipCommand extends Command
{
    use ResolvesRepoPath;

    protected $signature = 'config:unskip
        {step : Step to stop skipping}
        {path? : Path to the project directory (defaults to the current directory)}
        {--global : Write to the global config instead of this repo}';

    protected $description = 'Remove a step from the skip list (global or per-repo)';

    public function handle(DepsConfigService $config): int
    {
        $step = (string) $this->argument('step');
        $cwd = $this->resolveCwd();
        $global = (bool) $this->option('global');

        if (! $config->removeSkip($cwd, $step, $global)) {
            $this->components->warn("Not skipped: {$step}");

            return self::SUCCESS;
        }

        $this->components->task('No longer skipping <comment>'.$step.'</comment> ('.($global ? 'global' : 'this repo').')');

        return self::SUCCESS;
    }
}
