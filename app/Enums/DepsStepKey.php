<?php

namespace App\Enums;

enum DepsStepKey: string
{
    case ComposerInstall = 'composer.install';
    case ComposerPostUpdate = 'composer.post-update-cmd';
    case NpmInstall = 'npm.install';
    case NpmBuild = 'npm.build';
    case PnpmInstall = 'pnpm.install';
    case PnpmBuild = 'pnpm.build';
}
