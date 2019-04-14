<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Github;

use Doctrine\AutomaticReleases\Environment\Variables;
use Doctrine\AutomaticReleases\Git;
use Doctrine\AutomaticReleases\Git\Value\BranchName;
use Doctrine\AutomaticReleases\Github\Api\GraphQL\Query\GetMilestoneChangelog;
use Doctrine\AutomaticReleases\Github\Api\GraphQL\RunGraphQLQuery;
use Doctrine\AutomaticReleases\Github\Api\V3\CreatePullRequest;
use Doctrine\AutomaticReleases\Github\Api\V3\CreateRelease;
use Doctrine\AutomaticReleases\Github\Event\MilestoneClosedEvent;
use Doctrine\AutomaticReleases\Github\Value\RepositoryName;
use Doctrine\AutomaticReleases\Gpg;
use Http\Discovery\HttpClientDiscovery;
use Http\Discovery\Psr17FactoryDiscovery;
use Psr\Http\Message\UriInterface;
use RuntimeException;
use function Safe\sprintf;
use function uniqid;

final class ReleaseMilestone
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
        Variables $environment,
        MilestoneClosedEvent $milestone,
        string $buildDir
    ) : UriInterface {
        $repositoryName = $milestone->repository();

        $repositoryName->assertMatchesOwner($environment->githubOrganisation()); // @TODO limit via ENV?

        $releasedRepositoryLocalPath = $this->buildReleaseRepositoryLocalPath($repositoryName, $buildDir);

        $importedKey = (new Gpg\ImportGpgKey())->__invoke($environment->signingSecretKey());

        $this->prepareLocalReleaseRepository($buildDir, $releasedRepositoryLocalPath, $repositoryName, $environment);

        $candidates = (new Git\GetBranches())->__invoke($releasedRepositoryLocalPath);

        $releaseVersion = $milestone->version();

        $milestoneChangelog = (new GetMilestoneChangelog(new RunGraphQLQuery(
            Psr17FactoryDiscovery::findRequestFactory(),
            HttpClientDiscovery::find(),
            $environment->githubToken()
        )))->__invoke(
            $repositoryName,
            $milestone->milestoneNumber()
        );

        $milestoneChangelog->assertAllIssuesAreClosed();

        $releaseBranch = $candidates->targetBranchFor($milestone->version());

        if ($releaseBranch === null) {
            throw new RuntimeException(sprintf(
                'No valid release branch found for version %s',
                $milestone->version()->fullReleaseName()
            ));
        }

        $changelog = (new CreateChangelogText(JwageGenerateChangelog::create(
            Psr17FactoryDiscovery::findRequestFactory(),
            HttpClientDiscovery::find(),
        )))
            ->__invoke(
                $milestoneChangelog,
                $milestone->repository(),
                $milestone->version()
            );

        $tagName = $releaseVersion->fullReleaseName();

        (new Git\CreateTag())->__invoke(
            $releasedRepositoryLocalPath,
            $releaseBranch,
            $releaseVersion->fullReleaseName(),
            $changelog,
            $importedKey
        );

        $mergeUpTarget = $candidates->branchToMergeUp($milestone->version());

        $mergeUpBranch = $this->buildMergeUpBranchName($releaseBranch, $mergeUpTarget);

        (new Git\Push($this->createProcess))->__invoke(
            $releasedRepositoryLocalPath,
            $tagName
        );

        (new Git\Push($this->createProcess))->__invoke(
            $releasedRepositoryLocalPath,
            $releaseBranch->name(),
            $mergeUpBranch->name()
        );

        $releaseUrl = (new CreateRelease(
            Psr17FactoryDiscovery::findRequestFactory(),
            HttpClientDiscovery::find(),
            $environment->githubToken()
        ))->__invoke(
            $repositoryName,
            $releaseVersion,
            $changelog
        );

        (new CreatePullRequest(
            Psr17FactoryDiscovery::findRequestFactory(),
            HttpClientDiscovery::find(),
            $environment->githubToken()
        ))->__invoke(
            $repositoryName,
            $mergeUpBranch,
            $mergeUpTarget,
            'Merge release ' . $tagName . ' into ' . $mergeUpTarget->name(),
            $changelog
        );

        return $releaseUrl;
    }

    private function buildReleaseRepositoryLocalPath(RepositoryName $repositoryName, string $buildDir) : string
    {
        return $buildDir . '/' . $repositoryName->name();
    }

    private function prepareLocalReleaseRepository(
        string $buildDir,
        string $releasedRepositoryLocalPath,
        RepositoryName $repositoryName,
        Variables $environment
    ) : void {
        (new Git\CleanBuildDir())->__invoke($buildDir);

        (new Git\CloneRepository())->__invoke(
            $repositoryName->uriWithTokenAuthentication($environment->githubToken()),
            $releasedRepositoryLocalPath,
            $environment->gitAuthorName(),
            $environment->gitAuthorEmail()
        );
    }

    private function buildMergeUpBranchName(
        BranchName $releaseBranch,
        BranchName $mergeUpTarget
    ) : BranchName {
        return BranchName::fromName(
            $releaseBranch->name()
            . '-merge-up-into-'
            . $mergeUpTarget->name()
            . uniqid('_', true) // This is to ensure that a new merge-up pull request is created even if one already exists
        );
    }
}
