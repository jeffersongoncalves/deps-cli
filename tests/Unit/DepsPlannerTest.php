<?php

use App\Enums\DepsStepKey;
use App\Services\DepsPlanner;
use Symfony\Component\Process\Process;

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

it('plans composer install only when composer.json has no post-update-cmd script', function () {
    file_put_contents($this->tmp.'/composer.json', '{}');

    $steps = (new DepsPlanner)->plan($this->tmp);

    expect(array_map(fn ($s) => $s->command, $steps))->toBe([
        'composer install',
    ]);
});

it('plans composer install and post-update-cmd when composer.json defines the script', function () {
    file_put_contents($this->tmp.'/composer.json', json_encode(['scripts' => ['post-update-cmd' => 'SomeClass::postUpdate']]));

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

it('plans nothing when package.json has no lockfile and no manager is given', function () {
    file_put_contents($this->tmp.'/package.json', json_encode(['scripts' => ['build' => 'vite build']]));

    $steps = (new DepsPlanner)->plan($this->tmp);

    expect($steps)->toBe([]);
});

it('plans install and build with the given manager when package.json has no lockfile', function () {
    file_put_contents($this->tmp.'/package.json', json_encode(['scripts' => ['build' => 'vite build']]));

    $steps = (new DepsPlanner)->plan($this->tmp, packageManager: 'npm');

    expect(array_map(fn ($s) => $s->command, $steps))->toBe(['npm install', 'npm run build']);
});

it('plans pnpm install when pnpm-lock.yaml is present without a package.json', function () {
    file_put_contents($this->tmp.'/pnpm-lock.yaml', 'lockfileVersion: 6');

    $steps = (new DepsPlanner)->plan($this->tmp);

    expect(array_map(fn ($s) => $s->command, $steps))->toBe(['pnpm install']);
});

it('plans pnpm install and build when pnpm-lock.yaml and a build script are present', function () {
    file_put_contents($this->tmp.'/package.json', json_encode(['scripts' => ['build' => 'vite build']]));
    file_put_contents($this->tmp.'/pnpm-lock.yaml', 'lockfileVersion: 6');

    $steps = (new DepsPlanner)->plan($this->tmp);

    expect(array_map(fn ($s) => $s->command, $steps))->toBe([
        'pnpm install',
        'pnpm run build',
    ]);
});

it('plans yarn install and build when yarn.lock is present', function () {
    file_put_contents($this->tmp.'/package.json', json_encode(['scripts' => ['build' => 'vite build']]));
    file_put_contents($this->tmp.'/yarn.lock', '# yarn lockfile v1');

    $steps = (new DepsPlanner)->plan($this->tmp);

    expect(array_map(fn ($s) => $s->command, $steps))->toBe([
        'yarn install',
        'yarn run build',
    ]);
});

it('plans bun install and build when bun.lock is present', function () {
    file_put_contents($this->tmp.'/package.json', json_encode(['scripts' => ['build' => 'vite build']]));
    file_put_contents($this->tmp.'/bun.lock', '{}');

    $steps = (new DepsPlanner)->plan($this->tmp);

    expect(array_map(fn ($s) => $s->command, $steps))->toBe([
        'bun install',
        'bun run build',
    ]);
});

it('combines composer, npm, and pnpm steps in order', function () {
    file_put_contents($this->tmp.'/composer.json', json_encode(['scripts' => ['post-update-cmd' => 'SomeClass::postUpdate']]));
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
    file_put_contents($this->tmp.'/composer.json', json_encode(['scripts' => ['post-update-cmd' => 'SomeClass::postUpdate']]));

    $steps = (new DepsPlanner)->plan($this->tmp, skip: ['composer.post-update-cmd']);

    expect(array_map(fn ($s) => $s->command, $steps))->toBe(['composer install']);
});

it('adds an env-copy step before install steps when run from a linked git worktree', function () {
    $main = sys_get_temp_dir().'/deps-cli-wt-main-'.bin2hex(random_bytes(4));
    $worktree = sys_get_temp_dir().'/deps-cli-wt-linked-'.bin2hex(random_bytes(4));
    mkdir($main, 0777, true);

    $run = fn (array $args, string $cwd) => (new Process($args, $cwd))->mustRun();

    $run(['git', 'init'], $main);
    $run(['git', 'config', 'user.email', 'test@example.com'], $main);
    $run(['git', 'config', 'user.name', 'Test'], $main);
    file_put_contents($main.'/.env', 'APP_KEY=secret');
    file_put_contents($main.'/composer.json', '{}');
    $run(['git', 'add', '.'], $main);
    $run(['git', 'commit', '-m', 'init'], $main);
    $run(['git', 'worktree', 'add', $worktree, '-b', 'feature'], $main);

    file_put_contents($worktree.'/composer.json', '{}');

    $steps = (new DepsPlanner)->plan($worktree);

    expect($steps)->toHaveCount(2)
        ->and($steps[0]->key)->toBe(DepsStepKey::EnvCopy)
        ->and(realpath($steps[0]->source))->toBe(realpath($main.'/.env'))
        ->and($steps[1]->command)->toBe('composer install');

    $run(['git', 'worktree', 'remove', $worktree, '--force'], $main);
    (new Process(['rm', '-rf', $main]))->run();
});

it('rewrites APP_URL host to the worktree folder name when copying .env', function () {
    $main = sys_get_temp_dir().'/deps-cli-env-main-'.bin2hex(random_bytes(4));
    $worktree = sys_get_temp_dir().'/my-feature-'.bin2hex(random_bytes(4));
    mkdir($main, 0777, true);
    mkdir($worktree, 0777, true);
    file_put_contents($main.'/.env', "APP_NAME=App\nAPP_URL=http://myapp.test:8080/api\nDB_DATABASE=app\n");

    expect((new DepsPlanner)->applyEnvCopy($main.'/.env', $worktree))->toBeTrue();

    expect(file_get_contents($worktree.'/.env'))
        ->toBe("APP_NAME=App\nAPP_URL=http://".basename($worktree).".test:8080/api\nDB_DATABASE=app");

    (new Process(['rm', '-rf', $main]))->run();
    (new Process(['rm', '-rf', $worktree]))->run();
});

it('falls back to http://{folder}.test when the source .env has no APP_URL', function () {
    $main = sys_get_temp_dir().'/deps-cli-env-main2-'.bin2hex(random_bytes(4));
    $worktree = sys_get_temp_dir().'/my-feature2-'.bin2hex(random_bytes(4));
    mkdir($main, 0777, true);
    mkdir($worktree, 0777, true);
    file_put_contents($main.'/.env', "DB_DATABASE=app\n");

    (new DepsPlanner)->applyEnvCopy($main.'/.env', $worktree);

    expect(file_get_contents($worktree.'/.env'))->toBe('DB_DATABASE=app');

    (new Process(['rm', '-rf', $main]))->run();
    (new Process(['rm', '-rf', $worktree]))->run();
});

it('appends extra run commands after the detected steps', function () {
    file_put_contents($this->tmp.'/composer.json', json_encode(['scripts' => ['post-update-cmd' => 'SomeClass::postUpdate']]));

    $steps = (new DepsPlanner)->plan($this->tmp, extraRun: ['php artisan key:generate']);

    expect(array_map(fn ($s) => $s->command, $steps))->toBe([
        'composer install',
        'composer run post-update-cmd',
        'php artisan key:generate',
    ]);
});
