<?php

use App\Services\DepsPlanner;

beforeEach(function () {
    $this->tmp = sys_get_temp_dir().'/deps-cli-plan-'.bin2hex(random_bytes(4));
    mkdir($this->tmp, 0777, true);
});

afterEach(function () {
    $files = glob($this->tmp.'/*') ?: [];
    foreach ($files as $file) {
        unlink($file);
    }
    rmdir($this->tmp);
});

it('returns no steps for an empty directory', function () {
    expect((new DepsPlanner)->plan($this->tmp))->toBe([]);
});

it('plans composer install and post-update-cmd when composer.json exists', function () {
    file_put_contents($this->tmp.'/composer.json', '{}');

    $steps = (new DepsPlanner)->plan($this->tmp);

    expect(array_map(fn ($s) => $s->command, $steps))->toBe([
        'composer install',
        'composer run post-update-cmd',
    ]);
});

it('plans npm install only when package-lock.json is present', function () {
    file_put_contents($this->tmp.'/package.json', '{}');
    file_put_contents($this->tmp.'/package-lock.json', '{}');

    $steps = (new DepsPlanner)->plan($this->tmp);

    expect(array_map(fn ($s) => $s->command, $steps))->toBe(['npm install']);
});

it('plans npm run build only when package.json declares a build script', function () {
    file_put_contents($this->tmp.'/package.json', json_encode(['scripts' => ['build' => 'vite build']]));

    $steps = (new DepsPlanner)->plan($this->tmp);

    expect(array_map(fn ($s) => $s->command, $steps))->toBe(['npm run build']);
});

it('plans pnpm install and build when pnpm-lock.yaml is present', function () {
    file_put_contents($this->tmp.'/pnpm-lock.yaml', 'lockfileVersion: 6');

    $steps = (new DepsPlanner)->plan($this->tmp);

    expect(array_map(fn ($s) => $s->command, $steps))->toBe([
        'pnpm install',
        'pnpm run build',
    ]);
});

it('combines composer, npm, and pnpm steps in order', function () {
    file_put_contents($this->tmp.'/composer.json', '{}');
    file_put_contents($this->tmp.'/package.json', json_encode(['scripts' => ['build' => 'vite build']]));
    file_put_contents($this->tmp.'/package-lock.json', '{}');
    file_put_contents($this->tmp.'/pnpm-lock.yaml', 'lockfileVersion: 6');

    $steps = (new DepsPlanner)->plan($this->tmp);

    expect(array_map(fn ($s) => $s->command, $steps))->toBe([
        'composer install',
        'composer run post-update-cmd',
        'npm install',
        'npm run build',
        'pnpm install',
        'pnpm run build',
    ]);
});

it('leaves out steps that are in the skip list', function () {
    file_put_contents($this->tmp.'/composer.json', '{}');

    $steps = (new DepsPlanner)->plan($this->tmp, skip: ['composer.post-update-cmd']);

    expect(array_map(fn ($s) => $s->command, $steps))->toBe(['composer install']);
});

it('appends extra run commands after the detected steps', function () {
    file_put_contents($this->tmp.'/composer.json', '{}');

    $steps = (new DepsPlanner)->plan($this->tmp, extraRun: ['php artisan key:generate']);

    expect(array_map(fn ($s) => $s->command, $steps))->toBe([
        'composer install',
        'composer run post-update-cmd',
        'php artisan key:generate',
    ]);
});
