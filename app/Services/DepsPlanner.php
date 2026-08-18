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
     * @param  'npm'|'pnpm'|'yarn'|'bun'|null  $packageManager  which manager to use when
     *                                                          package.json has no lockfile from any manager
     * @return list<DepsStep>
     */
    public function plan(string $cwd, array $skip = [], array $extraRun = [], ?string $packageManager = null): array
    {
        $steps = [];

        if (is_file($cwd.'/composer.json')) {
            $this->push($steps, $skip, DepsStepKey::ComposerInstall, 'composer install', 'composer install');
            $this->push($steps, $skip, DepsStepKey::ComposerPostUpdate, 'composer run post-update-cmd', 'composer run post-update-cmd');
        }

        $hasPackageJson = is_file($cwd.'/package.json');
        $hasBuildScript = $hasPackageJson && $this->hasBuildScript($cwd.'/package.json');

        $detected = array_keys(array_filter([
            'npm' => is_file($cwd.'/package-lock.json'),
            'pnpm' => is_file($cwd.'/pnpm-lock.yaml'),
            'yarn' => is_file($cwd.'/yarn.lock'),
            'bun' => is_file($cwd.'/bun.lockb') || is_file($cwd.'/bun.lock'),
        ]));

        if ($detected === [] && $hasPackageJson && $packageManager !== null) {
            $detected = [$packageManager];
        }

        foreach ($detected as $manager) {
            [$installKey, $buildKey] = match ($manager) {
                'npm' => [DepsStepKey::NpmInstall, DepsStepKey::NpmBuild],
                'pnpm' => [DepsStepKey::PnpmInstall, DepsStepKey::PnpmBuild],
                'yarn' => [DepsStepKey::YarnInstall, DepsStepKey::YarnBuild],
                'bun' => [DepsStepKey::BunInstall, DepsStepKey::BunBuild],
            };

            $this->push($steps, $skip, $installKey, "{$manager} install", "{$manager} install");

            if ($hasBuildScript) {
                $this->push($steps, $skip, $buildKey, "{$manager} run build", "{$manager} run build");
            }
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
