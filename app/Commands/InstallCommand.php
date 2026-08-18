<?php

namespace App\Commands;

use App\Services\DepsConfigService;
use App\Services\DepsPlanner;
use JeffersonGoncalves\LaravelZero\Console\ResolvesPath;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class InstallCommand extends Command
{
    use ResolvesPath;

    protected $signature = 'install
        {path? : Path to the project directory (defaults to the current directory)}
        {--dry-run : Show the detected steps without running them}
        {--no-config : Ignore the global/per-repo config (skip + run) for this run}
        {--package-manager= : npm|pnpm|yarn|bun — used when package.json has no lockfile, skips the prompt}';

    protected $description = 'Detect composer.json/package.json + lockfile (npm/pnpm/yarn/bun) and run install + build steps';

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

        $packageManager = null;
        $hasKnownLock = is_file($cwd.'/package-lock.json')
            || is_file($cwd.'/pnpm-lock.yaml')
            || is_file($cwd.'/yarn.lock')
            || is_file($cwd.'/bun.lockb')
            || is_file($cwd.'/bun.lock');

        if (is_file($cwd.'/package.json') && ! $hasKnownLock) {
            $packageManager = $this->option('package-manager');

            if ($packageManager !== null && ! in_array($packageManager, ['npm', 'pnpm', 'yarn', 'bun'], true)) {
                $this->components->error("Invalid --package-manager '{$packageManager}'. Use npm, pnpm, yarn, or bun.");

                return self::FAILURE;
            }

            if ($packageManager === null) {
                $default = $this->availablePackageManagers()[0] ?? 'npm';
                $packageManager = $this->choice('No lockfile found for package.json — which package manager?', ['npm', 'pnpm', 'yarn', 'bun'], $default);
            }
        }

        $steps = $planner->plan($cwd, $skip, $extraRun, $packageManager);

        if ($steps === []) {
            $this->components->info('Nothing to do — no composer.json, package.json, or JS lockfile found.');

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

    /**
     * @return list<string> package managers found on PATH, checked npm/pnpm/yarn/bun in order
     */
    private function availablePackageManagers(): array
    {
        $finder = new ExecutableFinder;

        return array_values(array_filter(
            ['npm', 'pnpm', 'yarn', 'bun'],
            fn (string $manager): bool => $finder->find($manager) !== null
        ));
    }
}
