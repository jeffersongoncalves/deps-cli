<?php

namespace App\Services;

use JeffersonGoncalves\LaravelZero\Git\GitRemoteParser;
use JeffersonGoncalves\LaravelZero\JsonConfig\JsonConfigService;
use JeffersonGoncalves\LaravelZero\JsonConfig\Scopes\GlobalScope;
use JeffersonGoncalves\LaravelZero\JsonConfig\Scopes\PerRepoScope;
use Symfony\Component\Process\Process;

class DepsConfigService
{
    public function globalPath(): string
    {
        return $this->globalStore()->path();
    }

    public function repoPath(string $cwd): string
    {
        return $this->repoStore($cwd)->path();
    }

    /**
     * Stable, human-readable slug identifying the directory.
     * Derived from the origin remote (owner-repo); falls back to the
     * directory basename plus a short hash of its absolute path.
     */
    public function repoSlug(string $cwd): string
    {
        $url = $this->gitOutput($cwd, ['config', '--get', 'remote.origin.url']);

        if ($url !== null && $url !== '') {
            $slug = GitRemoteParser::slug($url);

            if ($slug !== null) {
                return $slug;
            }
        }

        $base = $this->sanitize(basename($cwd));
        $hash = substr(sha1($cwd), 0, 6);

        return ($base !== '' ? $base : 'repo').'-'.$hash;
    }

    /**
     * Skip list for this directory: the repo's own list when it defines
     * one, otherwise the global default.
     *
     * @return list<string>
     */
    public function resolveSkip(string $cwd): array
    {
        $repo = $this->repoStore($cwd);

        if ($repo->has('skip')) {
            return $this->cleanList($repo->get('skip', []));
        }

        return $this->cleanList($this->globalStore()->get('skip', []));
    }

    /**
     * Extra commands for this directory: the repo's own list when it
     * defines one, otherwise the global default.
     *
     * @return list<string>
     */
    public function resolveRun(string $cwd): array
    {
        $repo = $this->repoStore($cwd);

        if ($repo->has('run')) {
            return $this->cleanList($repo->get('run', []));
        }

        return $this->cleanList($this->globalStore()->get('run', []));
    }

    /**
     * @return list<string>
     */
    public function globalSkip(): array
    {
        return $this->cleanList($this->globalStore()->get('skip', []));
    }

    /**
     * @return list<string>
     */
    public function globalRun(): array
    {
        return $this->cleanList($this->globalStore()->get('run', []));
    }

    public function repoDefinesSkip(string $cwd): bool
    {
        return $this->repoStore($cwd)->has('skip');
    }

    public function repoDefinesRun(string $cwd): bool
    {
        return $this->repoStore($cwd)->has('run');
    }

    /**
     * @return list<string>
     */
    public function repoSkip(string $cwd): array
    {
        return $this->cleanList($this->repoStore($cwd)->get('skip', []));
    }

    /**
     * @return list<string>
     */
    public function repoRun(string $cwd): array
    {
        return $this->cleanList($this->repoStore($cwd)->get('run', []));
    }

    public function addSkip(string $cwd, string $step, bool $global): bool
    {
        $store = $global ? $this->globalStore() : $this->repoStore($cwd);
        $list = $this->cleanList($store->get('skip', []));

        if (in_array($step, $list, true)) {
            return false;
        }

        $store->set('skip', [...$list, $step]);

        return true;
    }

    public function removeSkip(string $cwd, string $step, bool $global): bool
    {
        $store = $global ? $this->globalStore() : $this->repoStore($cwd);
        $list = $this->cleanList($store->get('skip', []));
        $filtered = array_values(array_filter($list, static fn (string $s): bool => $s !== $step));

        if (count($filtered) === count($list)) {
            return false;
        }

        $store->set('skip', $filtered);

        return true;
    }

    public function addRun(string $cwd, string $command, bool $global): bool
    {
        $store = $global ? $this->globalStore() : $this->repoStore($cwd);
        $list = $this->cleanList($store->get('run', []));

        if (in_array($command, $list, true)) {
            return false;
        }

        $store->set('run', [...$list, $command]);

        return true;
    }

    public function removeRun(string $cwd, string $command, bool $global): bool
    {
        $store = $global ? $this->globalStore() : $this->repoStore($cwd);
        $list = $this->cleanList($store->get('run', []));
        $filtered = array_values(array_filter($list, static fn (string $c): bool => $c !== $command));

        if (count($filtered) === count($list)) {
            return false;
        }

        $store->set('run', $filtered);

        return true;
    }

    private function repoStore(string $cwd): JsonConfigService
    {
        return new JsonConfigService(new PerRepoScope('deps-cli', $this->repoSlug($cwd)));
    }

    private function globalStore(): JsonConfigService
    {
        return new JsonConfigService(new GlobalScope('deps-cli'));
    }

    /**
     * @return list<string>
     */
    private function cleanList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map(static fn ($v): string => trim((string) $v), $values),
            static fn (string $v): bool => $v !== '',
        )));
    }

    private function sanitize(string $value): string
    {
        $value = strtolower($value);
        $value = preg_replace('#[^a-z0-9._-]+#', '-', $value) ?? $value;

        return trim($value, '-');
    }

    /**
     * @param  list<string>  $args
     */
    private function gitOutput(string $cwd, array $args): ?string
    {
        $process = new Process(['git', ...$args], $cwd);
        $process->setTimeout(30);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        $out = trim($process->getOutput());

        return $out === '' ? null : $out;
    }
}
