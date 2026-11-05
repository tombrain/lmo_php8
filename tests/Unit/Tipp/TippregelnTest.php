<?php

declare(strict_types=1);

namespace Lmo\Tests\Unit\Tipp;

use Lmo\Addon\Tipp\Tippregeln;
use PHPUnit\Framework\TestCase;

/**
 * Die Tippregeln: Sprache und Werte aus den Einstellungen.
 */
class TippregelnTest extends TestCase
{
    public function testSpracheUndWerte(): void
    {
        $html = Tippregeln::html(['jokertipp' => '1', 'jokertippmulti' => '3', 'tippBis' => '15', 'rergebnis' => '1', 'rremis' => '1'], 'Deutsch');

        self::assertStringContainsString('<h1>Anleitung für das Tippspiel</h1>', $html);
        self::assertStringContainsString('15 Minuten vor Anpfiff.', $html);
        self::assertMatchesRegularExpression('~<span class="unit">Punkt</span>\s*<span class="value">1</span>~', $html, 'ein Punkt: Einzahl');
        self::assertStringContainsString('x&nbsp;3', $html);
    }

    public function testRemisBonusUndJokerUnabhaengig(): void
    {
        $ohneJoker = Tippregeln::html(['jokertipp' => '0', 'jokertippmulti' => '2', 'rremis' => '2'], 'Deutsch');
        self::assertStringContainsString('Bonus für richtigen Unentschieden-Tipp', $ohneJoker);
        self::assertStringNotContainsString('Jokermultiplikator', $ohneJoker, 'bis 4.5 vertauscht: Joker statt Remis-Bonus');

        $ohneRemis = Tippregeln::html(['jokertipp' => '1', 'jokertippmulti' => '2', 'rremis' => '0'], 'Deutsch');
        self::assertStringContainsString('Jokermultiplikator', $ohneRemis);
        self::assertStringNotContainsString('Bonus für richtigen Unentschieden-Tipp', $ohneRemis);
    }

    public function testOhneEigeneTexteEnglisch(): void
    {
        self::assertStringContainsString('<h1>Instructions for the Prediction Game</h1>', Tippregeln::html([], 'Magyar'));
    }
}
