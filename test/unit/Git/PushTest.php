<?php

declare(strict_types=1);

namespace Doctrine\AutomaticReleases\Test\Unit\Git\Value;

use Doctrine\AutomaticReleases\Git\Push;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class PushTest extends TestCase
{
    public function testPush() : void
    {
        // todo functionally test this by actually pushing to git

        $createProcess = static function (array $command, ?string $cwd = null) : Process {
            // this is wrong
            self::assertSame([
                'git',
                'push',
                'origin',
                'symbol:alias',
            ], $command);

            self::assertSame('/tmp', $cwd);

            return new Process([]);
        };

        $push = new Push($createProcess);
        $push->__invoke('/tmp', 'symbol', 'alias');
    }
}
