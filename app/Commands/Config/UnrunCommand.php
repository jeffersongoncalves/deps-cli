<?php

namespace App\Commands\Config;

use App\Concerns\ResolvesRepoPath;
use App\Services\DepsConfigService;
use LaravelZero\Framework\Commands\Command;

class UnrunCommand extends Command
{
    use ResolvesRepoPath;

    protected $signature = 'config:unrun
        {cmd : Extra command to remove}
        {path? : Path to the project directory (defaults to the current directory)}
        {--global : Write to the global config instead of this repo}';

    protected $description = 'Remove an extra run command (global or per-repo)';

    public function handle(DepsConfigService $config): int
    {
        $command = trim((string) $this->argument('cmd'));
        $cwd = $this->resolveCwd();
        $global = (bool) $this->option('global');

        if (! $config->removeRun($cwd, $command, $global)) {
            $this->components->warn("Not configured: {$command}");

            return self::SUCCESS;
        }

        $this->components->task('Removed run command <comment>'.$command.'</comment> ('.($global ? 'global' : 'this repo').')');

        return self::SUCCESS;
    }
}
