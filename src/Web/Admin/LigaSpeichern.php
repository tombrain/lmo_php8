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

namespace Lmo\Web\Admin;

use Closure;
use Lmo\Liga\Normalform;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Web\Konfiguration;
use Lmo\Web\Meldung;

/**
 * Speichert eine Liga aus der Administration: in der vollstaendigen Form, wie sie in der Datei
 * steht (Normalform), mit dem gewaehlten Spieltag als aktuellem; dazu, wenn eingeschaltet, die
 * Statistik (einzutore, einzutoretab, einzustats) und die Druckansicht als HTML (einsavehtml,
 * nicht im Pokal). Bis 4.5 lmo-saveliga.php, im globalen Bereich eingebunden.
 */
final class LigaSpeichern
{
    /**
     * @param string $ligenordner Ligenordner mit / am Ende
     * @param string $ausgabeordner Ordner fuer erzeugte Dateien mit / am Ende
     * @param array<int|string, mixed> $text Sprachtexte
     * @param string $bilder Adresse des Bilderordners (fuer die Meldungen)
     * @param bool $darf ein Admin oder Hilfsadmin ist angemeldet
     * @param Closure(Liga, string): void $statistik schreibt die Statistik (Liga, Datei relativ zum Ligenordner)
     * @param Closure(Liga, string, string): void $druckansicht schreibt die Druckansicht (Liga, Spieltag, Datei)
     */
    public function __construct(
        private string $ligenordner,
        private string $ausgabeordner,
        private Konfiguration $konfiguration,
        private array $text,
        private string $bilder,
        private bool $darf,
        private Closure $statistik,
        private Closure $druckansicht,
    ) {
    }

    /**
     * Speichert die Liga.
     *
     * @param string $datei Ligadatei relativ zum Ligenordner
     * @param string $st der gewaehlte Spieltag ('' oder 0: der aktuelle der Liga)
     * @return array{string, Liga} Meldungen und die Liga, wie sie gespeichert ist
     */
    public function speichere(Liga $liga, string $datei, string $st): array
    {
        if (!$this->darf || $datei == '' || substr($datei, -4) != '.l98') {
            return ['', $liga];
        }
        $k = $this->konfiguration;
        $ausgabe = '';
        // alles, was der LMO in die Datei schreibt; als aktueller Spieltag gilt der gewaehlte
        $liga = Normalform::von($liga, (string) ($this->text[54] ?? ''), $st > 0 ? $st : $liga->einstellungen()->aktuellerSpieltag(), $k->datumsformat());
        if ($k->wert('einzutore') == 1 || $k->wert('einzutoretab') == 1 || $k->wert('einzustats') == 1) {
            $ausgabe .= self::ausgabe(fn () => ($this->statistik)($liga, $datei));
        }
        if (@file_put_contents($this->ligenordner . $datei, $liga->alsText(), LOCK_EX) === false) {
            clearstatcache();

            return [$ausgabe . Meldung::html($this->bilder, (string) ($this->text[283] ?? ''), true), $liga];
        }
        $ausgabe .= Meldung::html($this->bilder, (string) ($this->text[138] ?? ''));
        // der Zaehler des Viewer-Caches einer gleichnamigen Ansicht (wie bis 4.5)
        $zaehler = $this->ausgabeordner . '/viewer_' . substr($datei, 0, -4) . '_count.txt';
        if (file_exists($zaehler)) {
            unlink($zaehler);
        }
        // Druckansicht: Spieltag mit Tabelle (<liga>-st.html) und Spielplan (<liga>-sp.html)
        if ($k->wert('einsavehtml') == 1 && !$liga->istPokal()) {
            $ausgabe .= self::ausgabe(fn () => ($this->druckansicht)($liga, $st, $datei));
        }
        clearstatcache();

        return [$ausgabe, $liga];
    }

    /** Was der alte Code dabei ausgibt */
    private static function ausgabe(Closure $aufruf): string
    {
        ob_start();
        $aufruf();

        return (string) ob_get_clean();
    }
}
