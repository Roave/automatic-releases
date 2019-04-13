<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Github;

use Assert\Assert;
use Doctrine\AutomaticReleases\Environment\Variables;
use Doctrine\AutomaticReleases\Github\Api\Hook\VerifyRequestSignature;
use Doctrine\AutomaticReleases\Github\Event\MilestoneClosedEvent;
use Zend\Diactoros\ServerRequest;
use function assert;
use function is_array;

final class HandleMilestoneClosedEvent
{
    public function __invoke(ServerRequest $request, Variables $environment) : ?MilestoneClosedEvent
    {
        (new VerifyRequestSignature())->__invoke($request, $environment->githubHookSecret());

        if (! MilestoneClosedEvent::appliesToRequest($request)) {
            return null;
        }

        $postData = $request->getParsedBody();

        assert(is_array($postData));

        Assert::that($postData)
              ->keyExists('payload');

        Assert::that($postData['payload'])
            ->isJsonString();

        return MilestoneClosedEvent::fromEventJson($postData['payload']);
    }
}
