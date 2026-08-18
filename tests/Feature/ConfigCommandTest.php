<?php

use App\Services\DepsConfigService;

beforeEach(function () {
    $this->tmp = sys_get_temp_dir().'/deps-cli-cfgcmd-'.bin2hex(random_bytes(4));
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

it('rejects an unknown step', function () {
    $this->artisan('config:skip', ['step' => 'deno.install', 'path' => $this->repoDir])
        ->expectsOutputToContain("Unknown step 'deno.install'")
        ->assertExitCode(1);
});

it('skips and unskips a step for this repo', function () {
    $this->artisan('config:skip', ['step' => 'npm.build', 'path' => $this->repoDir])
        ->expectsOutputToContain('Skipping')
        ->assertExitCode(0);

    expect((new DepsConfigService)->repoSkip(realpath($this->repoDir)))->toBe(['npm.build']);

    $this->artisan('config:unskip', ['step' => 'npm.build', 'path' => $this->repoDir])
        ->expectsOutputToContain('No longer skipping')
        ->assertExitCode(0);

    expect((new DepsConfigService)->repoSkip(realpath($this->repoDir)))->toBe([]);
});

it('adds a skip to the global config with --global', function () {
    $this->artisan('config:skip', ['step' => 'pnpm.build', 'path' => $this->repoDir, '--global' => true])
        ->assertExitCode(0);

    expect((new DepsConfigService)->globalSkip())->toBe(['pnpm.build'])
        ->and((new DepsConfigService)->repoSkip(realpath($this->repoDir)))->toBe([]);
});

it('adds and removes an extra run command', function () {
    $this->artisan('config:run', ['cmd' => 'composer install', 'path' => $this->repoDir])
        ->expectsOutputToContain('Added run command')
        ->assertExitCode(0);

    expect((new DepsConfigService)->repoRun(realpath($this->repoDir)))->toBe(['composer install']);

    $this->artisan('config:unrun', ['cmd' => 'composer install', 'path' => $this->repoDir])
        ->expectsOutputToContain('Removed run command')
        ->assertExitCode(0);

    expect((new DepsConfigService)->repoRun(realpath($this->repoDir)))->toBe([]);
});

it('shows global, repo, and effective config', function () {
    $this->artisan('config:skip', ['step' => 'npm.build', 'path' => $this->repoDir, '--global' => true])->assertExitCode(0);

    $this->artisan('config:show', ['path' => $this->repoDir])
        ->expectsOutputToContain('npm.build')
        ->expectsOutputToContain('inherits global')
        ->assertExitCode(0);
});
