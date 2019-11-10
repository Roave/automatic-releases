<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Git\Value;

use Assert\Assert;
use function array_filter;
use function array_search;
use function array_values;
use function assert;
use function end;
use function is_int;
use function Safe\usort;

final class MergeTargetCandidateBranches
{
    /** @var BranchName[] */
    private $sortedBranches;

    private function __construct()
    {
    }

    public static function fromAllBranches(BranchName ...$branches) : self
    {
        $mergeTargetBranches = array_filter($branches, static function (BranchName $branch) : bool {
            return $branch->isReleaseBranch()
                || $branch->isNextMajor();
        });

        Assert::that($mergeTargetBranches)
              ->notEmpty();

        usort($mergeTargetBranches, static function (BranchName $a, BranchName $b) : int {
            if ($a->isNextMajor()) {
                return 1;
            }

            if ($b->isNextMajor()) {
                return -1;
            }

            return $a->majorAndMinor() <=> $b->majorAndMinor();
        });

        $instance = new self();

        $instance->sortedBranches = $mergeTargetBranches;

        return $instance;
    }

    public function targetBranchFor(SemVerVersion $version) : ?BranchName
    {
        return array_values(array_filter(
            $this->sortedBranches,
            static function (BranchName $branch) use ($version) : bool {
                    return ! $branch->isNextMajor()
                        && $branch->majorAndMinor() === [$version->major(), $version->minor()];
            }
        ))[0] ?? $this->nextMajorBranch();
    }

    private function nextMajorBranch() : ?BranchName
    {
        return array_values(array_filter(
            $this->sortedBranches,
            static function (BranchName $branch) : bool {
                return $branch->isNextMajor();
            }
        ))[0] ?? null;
    }

    public function branchToMergeUp(SemVerVersion $version) : ?BranchName
    {
        $targetBranch = $this->targetBranchFor($version);

        if ($targetBranch === null) {
            // There's no branch where we can merge this, so we can't merge up either
            return null;
        }

        $lastBranch = end($this->sortedBranches);

        assert($lastBranch instanceof BranchName);

        $targetBranchKey = array_search($targetBranch, $this->sortedBranches, true);

        $branch = is_int($targetBranchKey)
            ? ($this->sortedBranches[$targetBranchKey + 1] ?? $lastBranch)
            : $lastBranch;

        // If the target branch and the merge-up branch are the same, no merge-up is needed
        return $branch === $targetBranch
            ? null
            : $branch;
    }
}
