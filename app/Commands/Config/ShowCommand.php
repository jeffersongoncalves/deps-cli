<?php

namespace App\Commands\Config;

use App\Concerns\ResolvesRepoPath;
use App\Services\DepsConfigService;
use LaravelZero\Framework\Commands\Command;

class ShowCommand extends Command
{
    use ResolvesRepoPath;

    protected $signature = 'config:show
        {path? : Path to the project directory (defaults to the current directory)}';

    protected $description = 'Show the global, per-repo, and effective (resolved) skip/run config';

    public function handle(DepsConfigService $config): int
    {
        $cwd = $this->resolveCwd();

        $this->components->info('Slug: <comment>'.$config->repoSlug($cwd).'</comment>');

        $this->newLine();
        $this->components->info('Global: <comment>'.$config->globalPath().'</comment>');
        $this->renderList('  skip', $config->globalSkip());
        $this->renderList('  run', $config->globalRun());

        $this->newLine();
        $this->components->info('Repo: <comment>'.$config->repoPath($cwd).'</comment>');
        $this->renderList('  skip', $config->repoSkip($cwd), $config->repoDefinesSkip($cwd) ? null : 'inherits global');
        $this->renderList('  run', $config->repoRun($cwd), $config->repoDefinesRun($cwd) ? null : 'inherits global');

        $this->newLine();
        $this->components->info('Effective:');
        $this->renderList('  skip', $config->resolveSkip($cwd));
        $this->renderList('  run', $config->resolveRun($cwd));

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $values
     */
    private function renderList(string $label, array $values, ?string $emptyHint = null): void
    {
        if ($values === []) {
            $this->line("{$label}: <comment>".($emptyHint ?? 'none').'</comment>');

            return;
        }

        $this->line("{$label}: <comment>".implode(', ', $values).'</comment>');
    }
}
