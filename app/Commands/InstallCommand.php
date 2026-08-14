<?php

namespace App\Commands;

use App\Services\DepsConfigService;
use App\Services\DepsPlanner;
use JeffersonGoncalves\LaravelZero\Console\ResolvesPath;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Process\Process;

class InstallCommand extends Command
{
    use ResolvesPath;

    protected $signature = 'install
        {path? : Path to the project directory (defaults to the current directory)}
        {--dry-run : Show the detected steps without running them}
        {--no-config : Ignore the global/per-repo config (skip + run) for this run}';

    protected $description = 'Detect composer.json/package.json/pnpm-lock.yaml and run install + build steps';

    public function handle(DepsPlanner $planner, DepsConfigService $config): int
    {
        $cwd = $this->resolvePath($this->argument('path'));

        if (! is_dir($cwd)) {
            $this->components->error("Not a directory: {$cwd}");

            return self::FAILURE;
        }

        $useConfig = ! $this->option('no-config');
        $skip = $useConfig ? $config->resolveSkip($cwd) : [];
        $extraRun = $useConfig ? $config->resolveRun($cwd) : [];

        $steps = $planner->plan($cwd, $skip, $extraRun);

        if ($steps === []) {
            $this->components->info('Nothing to do — no composer.json, package.json, or pnpm-lock.yaml found.');

            return self::SUCCESS;
        }

        $this->components->info("Directory: <comment>{$cwd}</comment>");

        if ($this->option('dry-run')) {
            foreach ($steps as $step) {
                $this->line("  - <comment>{$step->command}</comment>");
            }

            return self::SUCCESS;
        }

        foreach ($steps as $step) {
            $this->newLine();
            $this->components->info($step->label);

            $process = Process::fromShellCommandline($step->command, $cwd);
            $process->setTimeout(null);
            $process->run(function (string $type, string $buffer): void {
                $this->output->write($buffer);
            });

            if (! $process->isSuccessful()) {
                $this->components->error("Failed: {$step->label}");

                return self::FAILURE;
            }
        }

        $this->newLine();
        $this->components->info('Done.');

        return self::SUCCESS;
    }
}
