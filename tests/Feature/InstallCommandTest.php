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
    file_put_contents($this->tmp.'/composer.json', '{}');
    file_put_contents($this->tmp.'/pnpm-lock.yaml', 'lockfileVersion: 6');

    $this->artisan('install', ['path' => $this->tmp, '--dry-run' => true])
        ->expectsOutputToContain('composer install')
        ->expectsOutputToContain('composer run post-update-cmd')
        ->expectsOutputToContain('pnpm install')
        ->expectsOutputToContain('pnpm run build')
        ->assertExitCode(0);
});

it('fails for a non-existent directory', function () {
    $this->artisan('install', ['path' => $this->tmp.'/ghost'])
        ->expectsOutputToContain('Not a directory')
        ->assertExitCode(1);
});
