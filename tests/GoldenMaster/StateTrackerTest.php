<?php

namespace Lmo\Tests\GoldenMaster;

use Lmo\Tests\GoldenMaster\Support\StateTracker;
use PHPUnit\Framework\TestCase;

/**
 * Auf einem Windows-Checkout (core.autocrlf) haben die Vorlagen CRLF, die Anwendung schreibt LF.
 * Das darf nicht als Aenderung erscheinen, sonst weichen die Snapshots von Linux-Laeufen (GitHub) ab.
 */
final class StateTrackerTest extends TestCase
{
    public function testLineEndingOnlyChangeIsNotReported(): void
    {
        $before = ['config/cfg.txt' => "a=1\r\nb=2\r\n"];
        $after = ['config/cfg.txt' => "a=1\nb=2\n"];

        self::assertSame('', StateTracker::diff($before, $after, '/tmp/x'));
    }

    public function testRealChangeIsStillReported(): void
    {
        $before = ['config/cfg.txt' => "a=1\r\nb=2\r\n"];
        $after = ['config/cfg.txt' => "a=1\nb=3\n"];

        self::assertStringStartsWith("GEAENDERT config/cfg.txt\n", StateTracker::diff($before, $after, '/tmp/x'));
    }

    public function testInstancePathOnlyChangeIsNotReported(): void
    {
        $before = ['config/cfg.txt' => "pfad={LMO_PATH}/ligen\n"];
        $after = ['config/cfg.txt' => "pfad=/tmp/x/ligen\n"];

        self::assertSame('', StateTracker::diff($before, $after, '/tmp/x'));
    }
}
