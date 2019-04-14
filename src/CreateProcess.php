<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases;

use Symfony\Component\Process\Process;

final class CreateProcess
{
    /**
     * @param string[] $command
     */
    public function __invoke(array $command, ?string $cwd = null) : Process
    {
        return new Process($command, $cwd);
    }
}
