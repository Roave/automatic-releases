<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Git;

final class Push
{
    /**
     * @var callable
     * @psalm-var callable(array, string|null=):\Symfony\Component\Process\Process
     */
    private $createProcess;

    /**
     * @psalm-param callable(array, string|null=):\Symfony\Component\Process\Process $createProcess
     */
    public function __construct(callable $createProcess)
    {
        $this->createProcess = $createProcess;
    }

    public function __invoke(
        string $repositoryDirectory,
        string $symbol,
        ?string $alias = null
    ) : void {
        $pushedRef = $alias !== null ? $symbol . ':' . $alias : $symbol;

        ($this->createProcess)(
            ['git', 'push', 'origin', $pushedRef],
            $repositoryDirectory
        )
            ->mustRun();
    }
}
