<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Git;

use Doctrine\AutomaticReleases\Git\Value\BranchName;
use Doctrine\AutomaticReleases\Git\Value\MergeTargetCandidateBranches;
use Symfony\Component\Process\Process;
use function array_filter;
use function array_map;
use function explode;
use function Safe\preg_replace;
use function trim;

final class GetBranches
{
    public function __invoke(string $repositoryDirectory) : MergeTargetCandidateBranches
    {
        (new Process(['git', 'fetch'], $repositoryDirectory))
            ->mustRun();

        $branches = array_filter(explode(
            "\n",
            (new Process(['git', 'branch', '-r'], $repositoryDirectory))
                ->mustRun()
                ->getOutput()
        ));

        return MergeTargetCandidateBranches::fromAllBranches(...array_map(static function (string $branch) : BranchName {
            /** @var string $sanitizedBranch */
            $sanitizedBranch = preg_replace(
                '~^(?:remotes/)?origin/~',
                '',
                trim($branch, "* \t\n\r\0\x0B")
            );

            return BranchName::fromName($sanitizedBranch);
        }, $branches));
    }
}
