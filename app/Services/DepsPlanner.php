<?php

namespace App\Services;

use App\DTOs\DepsStep;

class DepsPlanner
{
    /**
     * Detect composer.json/package.json/lockfiles in $cwd and return the
     * ordered list of install/build steps to run.
     *
     * @return list<DepsStep>
     */
    public function plan(string $cwd): array
    {
        $steps = [];

        if (is_file($cwd.'/composer.json')) {
            $steps[] = new DepsStep('composer install', 'composer install');
            $steps[] = new DepsStep('composer run post-update-cmd', 'composer run post-update-cmd');
        }

        if (is_file($cwd.'/package.json')) {
            if (is_file($cwd.'/package-lock.json')) {
                $steps[] = new DepsStep('npm install', 'npm install');
            }

            if ($this->hasBuildScript($cwd.'/package.json')) {
                $steps[] = new DepsStep('npm run build', 'npm run build');
            }
        }

        if (is_file($cwd.'/pnpm-lock.yaml')) {
            $steps[] = new DepsStep('pnpm install', 'pnpm install');
            $steps[] = new DepsStep('pnpm run build', 'pnpm run build');
        }

        return $steps;
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
