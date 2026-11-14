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

use Closure;
use Lmo\Liga\LigaDatei;
use Lmo\Liga\Rohdaten\Liga;

/**
 * Eine Liga zum Bearbeiten laden (bis 4.5 lmo-openfile.php), fuer die Administration und die
 * Classlib: die Rohdaten, der Spieltag (gewaehlt oder der aktuelle), der Stand der Datei und die
 * Sprachtexte mit den Bezeichnungen der Liga fuer Punkte und Tore. Gibt es die Datei nicht oder
 * darf sie nicht bearbeitet werden, gilt keine Datei und es kommt eine Meldung.
 */
final class LigaOeffnen
{
    /**
     * @param string $datei die Ligadatei relativ zum Ligenordner, '' ohne
     * @param Liga|null $liga die Rohdaten; null ohne (auch bei einer Datei ohne .l98)
     * @param mixed $st der Spieltag: der gewaehlte, sonst Actual der Liga, sonst 1
     * @param mixed $stats der Statistik-Schalter der Liga (Options/stats), sonst 0
     * @param string|null $stand Zeit der letzten Aenderung der Datei
     * @param array<int|string, mixed> $text die Sprachtexte
     */
    private function __construct(
        public readonly string $meldung,
        public readonly string $datei,
        public readonly ?Liga $liga,
        public readonly mixed $st,
        public readonly mixed $stats,
        public readonly ?string $stand,
        public readonly array $text
    ) {
    }

    /**
     * @param string $ligenordner Ordner der Ligen, mit / am Ende
     * @param string $datei die Ligadatei relativ zum Ligenordner
     * @param mixed $st der gewaehlte Spieltag; null ohne
     * @param Closure(string): bool $darf darf die Liga bearbeitet werden (check_hilfsadmin)
     * @param string $datumsformat fuer den Stand (defdateformat)
     * @param string $bilder Adresse des Bilderordners (URL_TO_IMGDIR) fuer die Meldung
     * @param array<int|string, mixed> $text die Sprachtexte
     */
    public static function oeffne(string $ligenordner, string $datei, mixed $st, Closure $darf, string $datumsformat, string $bilder, array $text): self
    {
        $fehler = static fn (): string => Meldung::html($bilder, is_scalar($text[224] ?? null) ? (string) $text[224] : '', true);
        $pfad = $ligenordner . $datei;
        if ($datei === '' || !file_exists($pfad) || !$darf($datei)) {
            // wie bis 4.5 ohne Datei weiter, damit niemand mit halb gesetzten Werten fortfaehrt
            return new self($datei !== '' ? $fehler() : '', '', null, $st, null, null, $text);
        }
        if (substr($datei, -4) != '.l98') {
            return new self('', $datei, null, $st, null, null, $text);
        }
        $zeilen = file($pfad);
        if ($zeilen === false || $zeilen === []) {
            return new self($fehler(), $datei, null, $st, null, null, $text);
        }
        $eintraege = LigaDatei::zerlege($zeilen);
        // $st und $stats gehoeren nicht zur Liga: gewaehlter Spieltag, Statistik-Schalter
        $stats = null;
        foreach ($eintraege as [$abschnitt, $schluessel, $wert]) {
            if ($abschnitt == 'Options' && $schluessel == 'Actual' && $st === null) {
                $st = $wert;
            }
            if ($abschnitt == 'Options' && $schluessel == 'stats') {
                $stats = $wert;
            }
        }
        $liga = Liga::ausEintraegen($eintraege);
        if (!$liga->istPokal()) {
            // eigene Bezeichnungen der Liga fuer Punkte und Tore ersetzen die Sprachtexte
            if (($liga->optionen['namePkt'] ?? '') != '') {
                $text[37] = $liga->optionen['namePkt'];
            }
            if (($liga->optionen['nameTor'] ?? '') != '') {
                $text[38] = $liga->optionen['nameTor'];
            }
        }

        return new self('', $datei, $liga, $st ?? 1, $stats ?? 0, date($datumsformat, (int) filemtime($pfad)), $text);
    }
}
