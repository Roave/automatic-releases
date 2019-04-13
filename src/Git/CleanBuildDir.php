<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Git;

use Symfony\Component\Process\Process;

final class CleanBuildDir
{
    public function __invoke(string $buildDir) : void
    {
        (new Process(['rm', '-rf', $buildDir]))
            ->mustRun();

        (new Process(['mkdir', $buildDir]))
            ->mustRun();
    }
}
