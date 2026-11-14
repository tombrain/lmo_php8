<?php
/** Liga Manager Online 4
  *
  * http://lmo.sourceforge.net/
  *
  * Umgebaut fuer PHP 8 im Rahmen von lmo_php8 (https://github.com/henshingly/lmo_php8).
  *
  * SPDX-License-Identifier: GPL-2.0-or-later
  *
  * REMOVING OR CHANGING THE COPYRIGHT NOTICES IS NOT ALLOWED!
  */

declare(strict_types=1);

namespace Lmo\Addon\Spieler;

use DivisionByZeroError;
use UnexpectedValueException;

/**
 * Eine Formel einer Formelspalte der Spielerstatistik, z. B. "TORE/SPIELE" oder "MAX(A,B)*2":
 * Namen anderer Spalten, Zahlen, + - * / ( ) und MAX oder MIN mit mindestens zwei Werten, Punkt
 * vor Strich. Das Ergebnis ist auf zwei Stellen gerundet. Bis 4.5 wurde die Formel als PHP-Code
 * mit eval() ausgefuehrt; dieser Rechner rechnet dasselbe ohne eval().
 */
final class Formel
{
    /** Platzhalter einer Spalte im Ausdruck: \x01<Nummer>\x02 */
    private const VON = "\x01";
    private const BIS = "\x02";

    /** @var list<string> */
    private array $teile = [];
    private int $pos = 0;
    /** @var array<int, string> */
    private array $werte = [];

    private function __construct(private string $ausdruck)
    {
    }

    /**
     * Uebersetzt eine Formel: die Namen der uebrigen Spalten (laengste zuerst, ohne Ruecksicht auf
     * Gross- und Kleinschreibung) werden zu Verweisen. null: die Formel enthaelt noch anderes als
     * Zahlen, Rechenzeichen, Klammern, Kommas und hoechstens MAX oder MIN.
     *
     * @param array<int, string> $spalten Namen der Spalten
     */
    public static function aus(string $formel, array $spalten, int $eigene): ?self
    {
        uasort($spalten, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        $ausdruck = strtoupper($formel);
        $rest = $ausdruck;
        foreach ($spalten as $nr => $name) {
            if ($nr != $eigene && $name !== '') {
                $rest = str_replace(strtoupper($name), '', $rest);
                $ausdruck = str_replace(strtoupper($name), self::VON . $nr . self::BIS, $ausdruck);
            }
        }
        $rest = trim(strtr($rest, '+-*/0123456789.(),', '                  '));

        return $rest === '' || $rest == 'MAX' || $rest == 'MIN' ? new self($ausdruck) : null;
    }

    /**
     * Rechnet die Formel fuer eine Zeile.
     *
     * @param array<int, string> $werte Werte der Zeile nach Spalte
     * @throws DivisionByZeroError bei Division durch 0
     * @throws UnexpectedValueException bei einer unvollstaendigen Formel oder einem Wert, der keine Zahl ist
     */
    public function rechne(array $werte): float
    {
        $this->werte = $werte;
        $this->teile = self::zerlege($this->ausdruck);
        $this->pos = 0;
        $wert = $this->summe();
        if ($this->pos < count($this->teile)) {
            throw new UnexpectedValueException('unerwartet: ' . $this->teile[$this->pos]);
        }

        return round($wert, 2);
    }

    /** @return list<string> Zahlen, Verweise, Rechenzeichen, Klammern, Kommas und MAX/MIN */
    private static function zerlege(string $ausdruck): array
    {
        $muster = '/\s*(' . self::VON . '\d+' . self::BIS . '|\d+\.?\d*|\.\d+|MAX|MIN|[-+*\/(),])/A';
        $teile = [];
        $pos = 0;
        $laenge = strlen($ausdruck);
        while ($pos < $laenge) {
            if (trim(substr($ausdruck, $pos)) === '') {
                break;
            }
            if (!preg_match($muster, $ausdruck, $treffer, 0, $pos)) {
                throw new UnexpectedValueException('unbekannt ab ' . $pos);
            }
            $teile[] = $treffer[1];
            $pos += strlen($treffer[0]);
        }

        return $teile;
    }

    private function naechstes(): ?string
    {
        return $this->teile[$this->pos] ?? null;
    }

    private function erwarte(string $teil): void
    {
        if ($this->naechstes() !== $teil) {
            throw new UnexpectedValueException('erwartet: ' . $teil);
        }
        $this->pos++;
    }

    /** Summe: Produkt (+|- Produkt)* */
    private function summe(): float
    {
        $wert = $this->produkt();
        while (in_array($this->naechstes(), ['+', '-'], true)) {
            $zeichen = $this->teile[$this->pos++];
            $rechts = $this->produkt();
            $wert = $zeichen === '+' ? $wert + $rechts : $wert - $rechts;
        }

        return $wert;
    }

    /** Produkt: Faktor (*|/ Faktor)* */
    private function produkt(): float
    {
        $wert = $this->faktor();
        while (in_array($this->naechstes(), ['*', '/'], true)) {
            $zeichen = $this->teile[$this->pos++];
            $rechts = $this->faktor();
            if ($zeichen === '/' && $rechts == 0) {
                throw new DivisionByZeroError('Division durch 0');
            }
            $wert = $zeichen === '*' ? $wert * $rechts : $wert / $rechts;
        }

        return $wert;
    }

    /** Faktor: Vorzeichen, Zahl, Verweis, MAX/MIN(...) oder (Summe) */
    private function faktor(): float
    {
        $teil = $this->naechstes();
        if ($teil === null) {
            throw new UnexpectedValueException('Formel endet zu frueh');
        }
        $this->pos++;
        if ($teil === '-' || $teil === '+') {
            $wert = $this->faktor();

            return $teil === '-' ? -$wert : $wert;
        }
        if ($teil === '(') {
            $wert = $this->summe();
            $this->erwarte(')');

            return $wert;
        }
        if ($teil === 'MAX' || $teil === 'MIN') {
            $this->erwarte('(');
            $liste = [$this->summe()];
            while ($this->naechstes() === ',') {
                $this->pos++;
                $liste[] = $this->summe();
            }
            $this->erwarte(')');
            if (count($liste) < 2) {
                throw new UnexpectedValueException($teil . ' braucht mindestens zwei Werte');
            }

            return $teil === 'MAX' ? max($liste) : min($liste);
        }
        if ($teil[0] === self::VON) {
            $wert = $this->werte[(int) substr($teil, 1, -1)] ?? '';
            if (!is_numeric($wert)) {
                throw new UnexpectedValueException('keine Zahl: ' . $wert);
            }

            return (float) $wert;
        }
        if (is_numeric($teil) || preg_match('/^\d+\.$/', $teil)) {
            return (float) $teil;
        }
        throw new UnexpectedValueException('unerwartet: ' . $teil);
    }
}
