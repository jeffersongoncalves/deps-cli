<?php

namespace App\Services;

use App\DTOs\DepsStep;
use App\Enums\DepsStepKey;
use Symfony\Component\Process\Process;

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

        $envStep = $this->envCopyStep($cwd);

        if ($envStep !== null && ! in_array(DepsStepKey::EnvCopy->value, $skip, true)) {
            $steps[] = $envStep;
        }

        if (is_file($cwd.'/composer.json')) {
            $this->push($steps, $skip, DepsStepKey::ComposerInstall, 'composer install', 'composer install');

            if ($this->hasComposerScript($cwd.'/composer.json', 'post-update-cmd')) {
                $this->push($steps, $skip, DepsStepKey::ComposerPostUpdate, 'composer run post-update-cmd', 'composer run post-update-cmd');
            }
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

    /**
     * If $cwd is a linked git worktree and the main worktree has a .env,
     * return the step that copies it in before install commands run.
     */
    private function envCopyStep(string $cwd): ?DepsStep
    {
        $mainRoot = $this->mainWorktreePath($cwd);

        if ($mainRoot === null) {
            return null;
        }

        $source = $mainRoot.'/.env';

        if (! is_file($source)) {
            return null;
        }

        return new DepsStep("copy .env from main worktree ({$mainRoot})", "copy {$source} -> {$cwd}/.env", DepsStepKey::EnvCopy, $source);
    }

    /**
     * Copy $source (main worktree's .env) into $destDir/.env, rewriting APP_URL's
     * host to match the worktree's folder name — same logic as worktree-env-plugin:
     * keep scheme/port/path, replace only the subdomain, keep the TLD after the
     * first dot, fall back to http://{folder}.test when APP_URL is missing/unparsable.
     */
    public function applyEnvCopy(string $source, string $destDir): bool
    {
        if (! is_file($source)) {
            return false;
        }

        $lines = file($source, FILE_IGNORE_NEW_LINES);

        if ($lines === false) {
            return false;
        }

        $worktreeFolder = basename(rtrim($destDir, '/\\'));
        $currentUrl = $this->readEnvValue($lines, 'APP_URL');
        $newAppUrl = strtolower($currentUrl !== null
            ? $this->replaceUrlHost($currentUrl, $worktreeFolder)
            : "http://{$worktreeFolder}.test");

        $updated = array_map(
            fn (string $line): string => str_starts_with($line, 'APP_URL=') ? "APP_URL={$newAppUrl}" : $line,
            $lines
        );

        return file_put_contents($destDir.'/.env', implode("\n", $updated)) !== false;
    }

    /**
     * @param  list<string>  $lines
     */
    private function readEnvValue(array $lines, string $key): ?string
    {
        foreach ($lines as $line) {
            if (str_starts_with($line, "{$key}=")) {
                return trim(substr($line, strlen($key) + 1), " \"'");
            }
        }

        return null;
    }

    private function replaceUrlHost(string $url, string $newHost): string
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['host'])) {
            return "http://{$newHost}.test";
        }

        $dot = strpos($parts['host'], '.');
        $newHostname = $dot === false ? $newHost : $newHost.substr($parts['host'], $dot);

        $result = ($parts['scheme'] ?? 'http').'://'.$newHostname;

        if (isset($parts['port'])) {
            $result .= ':'.$parts['port'];
        }

        if (isset($parts['path'])) {
            $result .= $parts['path'];
        }

        return $result;
    }

    /**
     * Resolve the main worktree root when $cwd is a linked git worktree,
     * or null when it's not a worktree (or not a git repo at all).
     */
    private function mainWorktreePath(string $cwd): ?string
    {
        $gitDir = $this->git($cwd, ['rev-parse', '--git-dir']);
        $commonDir = $this->git($cwd, ['rev-parse', '--git-common-dir']);

        if ($gitDir === null || $commonDir === null) {
            return null;
        }

        $gitDirReal = realpath($cwd.'/'.$gitDir) ?: realpath($gitDir);
        $commonDirReal = realpath($cwd.'/'.$commonDir) ?: realpath($commonDir);

        if ($gitDirReal === false || $commonDirReal === false || $gitDirReal === $commonDirReal) {
            return null;
        }

        return dirname($commonDirReal);
    }

    /**
     * @param  list<string>  $args
     */
    private function git(string $cwd, array $args): ?string
    {
        $process = new Process(['git', ...$args], $cwd);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        return trim($process->getOutput());
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

    private function hasComposerScript(string $composerJsonPath, string $scriptName): bool
    {
        $contents = file_get_contents($composerJsonPath);

        if ($contents === false) {
            return false;
        }

        $decoded = json_decode($contents, true);

        return is_array($decoded) && isset($decoded['scripts'][$scriptName]);
    }
}
