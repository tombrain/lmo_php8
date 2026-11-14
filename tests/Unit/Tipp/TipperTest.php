<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Tipp;

use Lmo\Addon\Tipp\Tipper;
use PHPUnit\Framework\TestCase;

/**
 * Ein Tipper aus der Tipperdatei.
 */
class TipperTest extends TestCase
{
    public function testTeileName(): void
    {
        self::assertSame(['Anna', 'Alt'], Tipper::teileName('Anna Alt'));
        self::assertSame(['Anna', 'Alt'], Tipper::teileName('Anna&nbsp;Alt'), 'so schrieb die Registrierung bis 4.5');
        self::assertSame(['', 'Madonna'], Tipper::teileName('Madonna'), 'bis 4.5 "adonna"');
        self::assertSame(['', ''], Tipper::teileName('&nbsp;'), 'ein leerer Name aus der Registrierung');
        self::assertSame(['', ''], Tipper::teileName(''));
        self::assertSame(['Anna', 'von Alt'], Tipper::teileName('Anna von Alt'), 'am ersten Leerzeichen');
    }
}
