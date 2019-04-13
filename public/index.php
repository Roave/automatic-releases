<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\WebApplication;

use Doctrine\AutomaticReleases\Environment\Variables;
use Doctrine\AutomaticReleases\Github\HandleMilestoneClosedEvent;
use Doctrine\AutomaticReleases\Github\ReleaseMilestone;
use ErrorException;
use Zend\Diactoros\ServerRequestFactory;
use const E_NOTICE;
use const E_STRICT;
use const E_WARNING;
use function set_error_handler;

(static function () : void {
    require_once __DIR__ . '/../vendor/autoload.php';

    set_error_handler(
        static function ($errorCode, $message = '', $file = '', $line = 0) : void {
            throw new ErrorException($message, 0, $errorCode, $file, $line);
        },
        E_STRICT | E_NOTICE | E_WARNING
    );

    $buildDir = __DIR__ . '/../build';

    $request     = ServerRequestFactory::fromGlobals();
    $environment = Variables::fromEnvironment();

    $milestone = (new HandleMilestoneClosedEvent())
        ->__invoke($request, $environment);

    if ($milestone === null) {
        echo 'Event does not apply.';

        return;
    }

    $releaseUrl = (new ReleaseMilestone())
        ->__invoke($environment, $milestone, $buildDir);

    echo 'Released: ' . $releaseUrl->__toString();
})();
