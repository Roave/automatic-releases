<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Git;

use Psr\Http\Message\UriInterface;
use Symfony\Component\Process\Process;

final class CloneRepository
{
    public function __invoke(
        UriInterface $repositoryUri,
        string $targetPath,
        string $gitAuthorName,
        string $gitAuthorEmail
    ) : void {
        (new Process(['git', 'clone', $repositoryUri->__toString(), $targetPath]))
            ->mustRun();

        (new Process(['git', 'config', 'user.email', $gitAuthorEmail], $targetPath))
            ->mustRun();

        (new Process(['git', 'config', 'user.name', $gitAuthorName], $targetPath))
            ->mustRun();
    }
}
