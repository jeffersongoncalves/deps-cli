<?php

beforeEach(function () {
    $this->tmp = sys_get_temp_dir().'/deps-cli-install-'.bin2hex(random_bytes(4));
    mkdir($this->tmp, 0777, true);
});

afterEach(function () {
    $files = glob($this->tmp.'/*') ?: [];
    foreach ($files as $file) {
        unlink($file);
    }
    rmdir($this->tmp);
});

it('reports nothing to do for an empty directory', function () {
    $this->artisan('install', ['path' => $this->tmp])
        ->expectsOutputToContain('Nothing to do')
        ->assertExitCode(0);
});

it('dry-run lists the detected steps without running them', function () {
    file_put_contents($this->tmp.'/composer.json', json_encode(['scripts' => ['post-update-cmd' => 'SomeClass::postUpdate']]));
    file_put_contents($this->tmp.'/package.json', json_encode(['scripts' => ['build' => 'vite build']]));
    file_put_contents($this->tmp.'/pnpm-lock.yaml', 'lockfileVersion: 6');

    $this->artisan('install', ['path' => $this->tmp, '--dry-run' => true])
        ->expectsOutputToContain('composer install')
        ->expectsOutputToContain('composer run post-update-cmd')
        ->expectsOutputToContain('pnpm install')
        ->expectsOutputToContain('pnpm run build')
        ->assertExitCode(0);
});

it('prompts for a package manager when package.json has no lockfile', function () {
    file_put_contents($this->tmp.'/package.json', '{}');

    $this->artisan('install', ['path' => $this->tmp, '--dry-run' => true])
        ->expectsQuestion('No lockfile found for package.json — which package manager?', 'npm')
        ->expectsOutputToContain('npm install')
        ->assertExitCode(0);
});

it('skips the prompt when --package-manager is given', function () {
    file_put_contents($this->tmp.'/package.json', '{}');

    $this->artisan('install', ['path' => $this->tmp, '--dry-run' => true, '--package-manager' => 'pnpm'])
        ->doesntExpectOutputToContain('which package manager?')
        ->expectsOutputToContain('pnpm install')
        ->assertExitCode(0);
});

it('rejects an invalid --package-manager value', function () {
    file_put_contents($this->tmp.'/package.json', '{}');

    $this->artisan('install', ['path' => $this->tmp, '--dry-run' => true, '--package-manager' => 'deno'])
        ->expectsOutputToContain("Invalid --package-manager 'deno'")
        ->assertExitCode(1);
});

it('fails for a non-existent directory', function () {
    $this->artisan('install', ['path' => $this->tmp.'/ghost'])
        ->expectsOutputToContain('Not a directory')
        ->assertExitCode(1);
});
