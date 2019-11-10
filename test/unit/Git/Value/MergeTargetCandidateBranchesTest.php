<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Test\Unit\Git\Value;

use Doctrine\AutomaticReleases\Git\Value\BranchName;
use Doctrine\AutomaticReleases\Git\Value\MergeTargetCandidateBranches;
use Doctrine\AutomaticReleases\Git\Value\SemVerVersion;
use PHPUnit\Framework\TestCase;

final class MergeTargetCandidateBranchesTest extends TestCase
{
    public function test() : void
    {
        $branches = MergeTargetCandidateBranches::fromAllBranches(
            BranchName::fromName('1.1'),
            BranchName::fromName('1.4'),
            BranchName::fromName('1.2'),
            BranchName::fromName('master'),
            BranchName::fromName('1.0'),
            BranchName::fromName('a/b/c'), // filtered out
            BranchName::fromName('1.5')
        );

        self::assertEquals(
            BranchName::fromName('master'),
            $branches->targetBranchFor(SemVerVersion::fromMilestoneName('1.99.0'))
        );
        self::assertNull($branches->branchToMergeUp(SemVerVersion::fromMilestoneName('1.99.0')));

        self::assertEquals(
            BranchName::fromName('master'),
            $branches->targetBranchFor(SemVerVersion::fromMilestoneName('2.0.0'))
        );
        self::assertNull($branches->branchToMergeUp(SemVerVersion::fromMilestoneName('2.0.0')));

        self::assertEquals(
            BranchName::fromName('1.2'),
            $branches->targetBranchFor(SemVerVersion::fromMilestoneName('1.2.3'))
        );
        self::assertEquals(
            BranchName::fromName('1.4'), // note: there is no 1.3
            $branches->branchToMergeUp(SemVerVersion::fromMilestoneName('1.2.3'))
        );

        self::assertEquals(
            BranchName::fromName('1.5'),
            $branches->targetBranchFor(SemVerVersion::fromMilestoneName('1.5.99'))
        );
        self::assertEquals(
            BranchName::fromName('master'),
            $branches->branchToMergeUp(SemVerVersion::fromMilestoneName('1.5.99'))
        );
        self::assertEquals(
            BranchName::fromName('master'),
            $branches->targetBranchFor(SemVerVersion::fromMilestoneName('1.6.0'))
        );

        self::assertEquals(
            BranchName::fromName('1.0'),
            $branches->targetBranchFor(SemVerVersion::fromMilestoneName('1.0.1'))
        );
        self::assertEquals(
            BranchName::fromName('1.1'),
            $branches->branchToMergeUp(SemVerVersion::fromMilestoneName('1.0.1'))
        );
    }

    public function testCannotGetNextMajorBranchIfNoneExists() : void
    {
        $branches = MergeTargetCandidateBranches::fromAllBranches(
            BranchName::fromName('1.1'),
            BranchName::fromName('1.2'),
            BranchName::fromName('potato')
        );

        self::assertNull(
            $branches->targetBranchFor(SemVerVersion::fromMilestoneName('1.6.0')),
            'Cannot release next minor, since next minor branch does not exist'
        );
        self::assertNull(
            $branches->branchToMergeUp(SemVerVersion::fromMilestoneName('1.6.0')),
            'Cannot merge up next minor, since no next branch exists'
        );
        self::assertNull(
            $branches->targetBranchFor(SemVerVersion::fromMilestoneName('2.0.0')),
            'Cannot release next major, since next major branch does not exist'
        );
        self::assertNull(
            $branches->branchToMergeUp(SemVerVersion::fromMilestoneName('2.0.0')),
            'Cannot merge up next major, since no next branch exists'
        );
        self::assertNull(
            $branches->branchToMergeUp(SemVerVersion::fromMilestoneName('1.2.1')),
            'Cannot merge up: no master branch exists'
        );
    }

    public function testWillPickNewMajorReleaseBranchIfNoCurrentReleaseBranchExists() : void
    {
        $branches = MergeTargetCandidateBranches::fromAllBranches(
            BranchName::fromName('1.1'),
            BranchName::fromName('1.2'),
            BranchName::fromName('master')
        );

        self::assertEquals(
            BranchName::fromName('1.2'),
            $branches->targetBranchFor(SemVerVersion::fromMilestoneName('1.2.31')),
            'Next patch release will be tagged from active minor branch'
        );
        self::assertEquals(
            BranchName::fromName('master'),
            $branches->branchToMergeUp(SemVerVersion::fromMilestoneName('1.2.31')),
            '1.2.x will be merged into master'
        );
        self::assertEquals(
            BranchName::fromName('master'),
            $branches->targetBranchFor(SemVerVersion::fromMilestoneName('1.3.0')),
            'Next minor release will be tagged from active master branch'
        );
        self::assertNull(
            $branches->branchToMergeUp(SemVerVersion::fromMilestoneName('1.3.0')),
            '1.3.0 won\'t be merged up, since there\'s no further branches to merge to'
        );
        self::assertEquals(
            BranchName::fromName('master'),
            $branches->targetBranchFor(SemVerVersion::fromMilestoneName('2.0.0')),
            'Next major release will be tagged from active master branch'
        );
        self::assertNull(
            $branches->branchToMergeUp(SemVerVersion::fromMilestoneName('2.0.0')),
            '2.0.0 won\'t be merged up, since there\'s no further branches to merge to'
        );
    }
}
