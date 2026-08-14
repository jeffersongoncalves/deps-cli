<?php

namespace App\Commands\Config;

use App\Concerns\ResolvesRepoPath;
use App\Services\DepsConfigService;
use LaravelZero\Framework\Commands\Command;

class RunCommand extends Command
{
    use ResolvesRepoPath;

    protected $signature = 'config:run
        {cmd : Extra command to run after the detected steps}
        {path? : Path to the project directory (defaults to the current directory)}
        {--global : Write to the global config instead of this repo}';

    protected $description = 'Add an extra command to run after the detected steps (global or per-repo)';

    public function handle(DepsConfigService $config): int
    {
        $command = trim((string) $this->argument('cmd'));

        if ($command === '') {
            $this->components->error('Command is required.');

            return self::FAILURE;
        }

        $cwd = $this->resolveCwd();
        $global = (bool) $this->option('global');

        if (! $config->addRun($cwd, $command, $global)) {
            $this->components->warn("Already configured: {$command}");

            return self::SUCCESS;
        }

        $this->components->task('Added run command <comment>'.$command.'</comment> ('.($global ? 'global' : 'this repo').')');

        return self::SUCCESS;
    }
}
