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

namespace Lmo\Web;

/**
 * Die Statistikdatei einer Liga (output/<liga>.l98.php, geschrieben von lmo-zustat.php): PHP-Code,
 * der Variablen setzt. Hier wird sie in einem eigenen Bereich eingebunden, statt die Variablen
 * global zu verteilen.
 */
final class Statistikdatei
{
    /** @var array<string, mixed> */
    private array $werte;

    /** @param array<string, mixed> $werte Name der Variablen => Wert */
    public function __construct(array $werte)
    {
        $this->werte = $werte;
    }

    /** Die Datei lesen; null, wenn es sie nicht gibt. */
    public static function lies(string $pfad): ?self
    {
        if (!is_file($pfad)) {
            return null;
        }

        return new self((static function (string $lmo_statistikdatei): array {
            require $lmo_statistikdatei;
            unset($lmo_statistikdatei);

            return get_defined_vars();
        })($pfad));
    }

    /** Setzt die Datei diese Variable? */
    public function hat(string $name): bool
    {
        return isset($this->werte[$name]);
    }

    /** Eine Variable der Datei als Zahl; 0, wenn sie fehlt oder keine Zahl ist. */
    public function zahl(string $name): int|float
    {
        $wert = $this->werte[$name] ?? 0;

        return is_int($wert) || is_float($wert) ? $wert : (is_numeric($wert) ? $wert + 0 : 0);
    }

    /** Eine Variable der Datei als Text (z. B. Namen von Mannschaften); '', wenn sie fehlt. */
    public function text(string $name): string
    {
        $wert = $this->werte[$name] ?? '';

        return is_scalar($wert) ? (string) $wert : '';
    }

    /** Tore der ganzen Saison ($gzutore); '' ohne. */
    public function toreSaison(): int|float|string
    {
        return $this->hat('gzutore') ? $this->zahl('gzutore') : '';
    }

    /** Tore je Spiel der ganzen Saison ($gdstore); '' ohne. */
    public function toreJeSpielSaison(): int|float|string
    {
        return $this->hat('gdstore') ? $this->zahl('gdstore') : '';
    }

    /** Tore eines Spieltags ($zutore); '' ohne. */
    public function toreSpieltag(int $spieltag): int|float|string
    {
        return $this->jeSpieltag('zutore', $spieltag);
    }

    /** Tore je Spiel eines Spieltags ($dstore); '' ohne. */
    public function toreJeSpielSpieltag(int $spieltag): int|float|string
    {
        return $this->jeSpieltag('dstore', $spieltag);
    }

    /** Ein Wert aus einem Array je Spieltag der Datei, als Zahl; '' ohne. */
    private function jeSpieltag(string $name, int $spieltag): int|float|string
    {
        $werte = $this->werte[$name] ?? null;
        $wert = is_array($werte) ? ($werte[$spieltag] ?? null) : null;

        return is_int($wert) || is_float($wert) ? $wert : (is_numeric($wert) ? $wert + 0 : '');
    }
}
