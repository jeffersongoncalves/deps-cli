<?php

namespace App\Services;

use App\DTOs\DepsStep;
use App\Enums\DepsStepKey;

class DepsPlanner
{
    /**
     * Detect composer.json/package.json/lockfiles in $cwd and return the
     * ordered list of install/build steps to run.
     *
     * @param  list<string>  $skip  DepsStepKey values to leave out
     * @param  list<string>  $extraRun  extra commands appended at the end, always run
     * @return list<DepsStep>
     */
    public function plan(string $cwd, array $skip = [], array $extraRun = []): array
    {
        $steps = [];

        if (is_file($cwd.'/composer.json')) {
            $this->push($steps, $skip, DepsStepKey::ComposerInstall, 'composer install', 'composer install');
            $this->push($steps, $skip, DepsStepKey::ComposerPostUpdate, 'composer run post-update-cmd', 'composer run post-update-cmd');
        }

        if (is_file($cwd.'/package.json')) {
            if (is_file($cwd.'/package-lock.json')) {
                $this->push($steps, $skip, DepsStepKey::NpmInstall, 'npm install', 'npm install');
            }

            if ($this->hasBuildScript($cwd.'/package.json')) {
                $this->push($steps, $skip, DepsStepKey::NpmBuild, 'npm run build', 'npm run build');
            }
        }

        if (is_file($cwd.'/pnpm-lock.yaml')) {
            $this->push($steps, $skip, DepsStepKey::PnpmInstall, 'pnpm install', 'pnpm install');
            $this->push($steps, $skip, DepsStepKey::PnpmBuild, 'pnpm run build', 'pnpm run build');
        }

        foreach ($extraRun as $command) {
            $steps[] = new DepsStep("run: {$command}", $command);
        }

        return $steps;
    }

    /**
     * @param  list<DepsStep>  $steps
     * @param  list<string>  $skip
     */
    private function push(array &$steps, array $skip, DepsStepKey $key, string $label, string $command): void
    {
        if (in_array($key->value, $skip, true)) {
            return;
        }

        $steps[] = new DepsStep($label, $command, $key);
    }

    private function hasBuildScript(string $packageJsonPath): bool
    {
        $contents = file_get_contents($packageJsonPath);

        if ($contents === false) {
            return false;
        }

        $decoded = json_decode($contents, true);

        return is_array($decoded) && isset($decoded['scripts']['build']);
    }
}
