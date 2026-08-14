<?php

use App\Services\DepsConfigService;

beforeEach(function () {
    $this->tmp = sys_get_temp_dir().'/deps-cli-cfg-'.bin2hex(random_bytes(4));
    mkdir($this->tmp, 0777, true);

    $this->home = $this->tmp.'/home';
    mkdir($this->home, 0777, true);
    $this->xdg = $this->tmp.'/xdg';
    mkdir($this->xdg, 0777, true);
    $this->repoDir = $this->tmp.'/repo';
    mkdir($this->repoDir, 0777, true);

    $this->prevHome = getenv('HOME');
    $this->prevXdg = getenv('XDG_CONFIG_HOME');
    putenv('HOME='.$this->home);
    putenv('XDG_CONFIG_HOME='.$this->xdg);

    $this->service = new DepsConfigService;
});

afterEach(function () {
    putenv($this->prevHome === false ? 'HOME' : 'HOME='.$this->prevHome);
    putenv($this->prevXdg === false ? 'XDG_CONFIG_HOME' : 'XDG_CONFIG_HOME='.$this->prevXdg);

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($this->tmp, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }

    rmdir($this->tmp);
});

it('falls back to basename plus hash when there is no git remote', function () {
    expect($this->service->repoSlug($this->repoDir))->toMatch('/^repo-[0-9a-f]{6}$/');
});

it('returns empty skip/run when nothing is configured', function () {
    expect($this->service->resolveSkip($this->repoDir))->toBe([])
        ->and($this->service->resolveRun($this->repoDir))->toBe([]);
});

it('falls back to the global skip/run when the repo defines none', function () {
    $this->service->addSkip($this->repoDir, 'npm.build', global: true);
    $this->service->addRun($this->repoDir, 'php artisan key:generate', global: true);

    expect($this->service->resolveSkip($this->repoDir))->toBe(['npm.build'])
        ->and($this->service->resolveRun($this->repoDir))->toBe(['php artisan key:generate']);
});

it('lets the repo config override the global one entirely', function () {
    $this->service->addSkip($this->repoDir, 'npm.build', global: true);
    $this->service->addSkip($this->repoDir, 'pnpm.build', global: false);

    expect($this->service->resolveSkip($this->repoDir))->toBe(['pnpm.build']);
});

it('is idempotent when adding the same skip twice', function () {
    expect($this->service->addSkip($this->repoDir, 'npm.build', global: false))->toBeTrue()
        ->and($this->service->addSkip($this->repoDir, 'npm.build', global: false))->toBeFalse();

    expect($this->service->repoSkip($this->repoDir))->toBe(['npm.build']);
});

it('removes a skipped step', function () {
    $this->service->addSkip($this->repoDir, 'npm.build', global: false);

    expect($this->service->removeSkip($this->repoDir, 'npm.build', global: false))->toBeTrue()
        ->and($this->service->removeSkip($this->repoDir, 'npm.build', global: false))->toBeFalse();

    expect($this->service->repoSkip($this->repoDir))->toBe([]);
});

it('adds and removes extra run commands', function () {
    expect($this->service->addRun($this->repoDir, 'composer install', global: false))->toBeTrue();
    expect($this->service->repoRun($this->repoDir))->toBe(['composer install']);

    expect($this->service->removeRun($this->repoDir, 'composer install', global: false))->toBeTrue();
    expect($this->service->repoRun($this->repoDir))->toBe([]);
});
