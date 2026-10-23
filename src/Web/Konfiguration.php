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

use RuntimeException;

/**
 * Die Konfiguration der Installation (config/cfg.txt, dazu config/<addon>/cfg.txt je Addon) mit
 * typisierten Gettern. Ersetzt die globalen Variablen, die lmo-cfgload.php per extract() anlegt.
 */
final class Konfiguration
{
    /**
     * Anzeige-Optionen, die ein Aufruf fuer sich ueberschreiben darf (lmo.php?file=...&tabonres=2),
     * etwa wenn der LMO in eine andere Seite eingebunden ist. Alle anderen Werte kommen nur aus der Datei.
     */
    public const ANZEIGEOPTIONEN = ['tabonres', 'tabpkt'];

    /** @var array<string, mixed> */
    private array $werte;

    /**
     * @param array<string, mixed> $werte Schluessel => Wert, wie $cfgarray in lmo-cfgload.php
     *        (die Werte der Addons als Array unter ihrem Ordnernamen)
     */
    public function __construct(array $werte)
    {
        $this->werte = $werte;
    }

    /**
     * Liest config/cfg.txt und die cfg.txt der Addons in den Unterordnern, wie lmo-cfgload.php.
     *
     * @throws RuntimeException wenn config/cfg.txt fehlt oder unlesbar ist
     */
    public static function lies(string $ordner): self
    {
        $datei = is_file($ordner . '/cfg.txt') ? parse_ini_file($ordner . '/cfg.txt') : false;
        if ($datei === false) {
            throw new RuntimeException('Konfiguration nicht lesbar: ' . $ordner . '/cfg.txt');
        }
        $werte = [];
        foreach ($datei as $schluessel => $wert) {
            $werte[(string) $schluessel] = $wert;
        }
        // Addons in fester (alphabetischer) Reihenfolge
        foreach (scandir($ordner) ?: [] as $addon) {
            $datei = $ordner . '/' . $addon . '/cfg.txt';
            if ($addon != '.' && $addon != '..' && is_file($datei)) {
                $werte[$addon] = parse_ini_file($datei, false, INI_SCANNER_RAW) ?: [];
            }
        }

        return new self($werte);
    }

    /**
     * Alle Werte, wie sie gespeichert werden: die Hauptkonfiguration, die der Addons als Array unter
     * ihrem Ordnernamen.
     *
     * @return array<string, mixed>
     */
    public function alle(): array
    {
        return $this->werte;
    }

    /**
     * Dieselbe Konfiguration mit geaenderten Werten; die eines Addons stehen als Array unter seinem
     * Namen und ersetzen nur die genannten Schluessel.
     *
     * @param array<string, mixed> $aenderungen
     */
    public function mit(array $aenderungen): self
    {
        $werte = $this->werte;
        foreach ($aenderungen as $schluessel => $wert) {
            $werte[$schluessel] = is_array($wert) && is_array($werte[$schluessel] ?? null) ? array_replace($werte[$schluessel], $wert) : $wert;
        }

        return new self($werte);
    }

    /** Dieselbe Konfiguration ohne die genannten Werte der Hauptkonfiguration. */
    public function ohne(string ...$schluessel): self
    {
        return new self(array_diff_key($this->werte, array_flip($schluessel)));
    }

    /**
     * Ein Schalter eines Addons (Lmo\Addon\Schalter): an, wenn der Wert in config/<addon>/cfg.txt
     * 1 ist. Fehlt er dort, gilt der in config/cfg.txt (dort stand er bis 4.5), sonst die Vorgabe.
     */
    public function addonSchalter(string $addon, string $schluessel, bool $vorgabe): bool
    {
        $werte = $this->werte[$addon] ?? null;
        if (is_array($werte) && array_key_exists($schluessel, $werte)) {
            return $werte[$schluessel] == 1;
        }
        if (array_key_exists($schluessel, $this->werte) && !is_array($this->werte[$schluessel])) {
            return $this->schalter($schluessel);
        }

        return $vorgabe;
    }

    /**
     * Schreibt die Konfiguration wie bis 4.5 lmo-savecfg.php: die Hauptkonfiguration nach
     * <ordner>/cfg.txt, die eines Addons nach <ordner>/<addon>/cfg.txt, je Zeile "Schluessel=Wert"
     * in der Reihenfolge der Werte. Ist timezone leer, kommt wie bisher die eingestellte Zeitzone
     * hinein (lmo-cfgload.php setzt sie). false, wenn eine Datei nicht geschrieben werden konnte.
     */
    public function speichere(string $ordner): bool
    {
        $werte = $this->werte;
        if (array_key_exists('timezone', $werte) && empty($werte['timezone'])) {
            $werte['timezone'] = date_default_timezone_get();
        }
        $haupt = '';
        $ok = true;
        foreach ($werte as $schluessel => $wert) {
            if (is_array($wert)) {
                $addon = '';
                foreach ($wert as $name => $addonWert) {
                    $addon .= $name . '=' . (is_scalar($addonWert) ? (string) $addonWert : '') . "\n";
                }
                $ok = @file_put_contents($ordner . '/' . $schluessel . '/cfg.txt', $addon, LOCK_EX) !== false && $ok;
            } else {
                $haupt .= $schluessel . '=' . (is_scalar($wert) ? (string) $wert : '') . "\n";
            }
        }

        return @file_put_contents($ordner . '/cfg.txt', $haupt, LOCK_EX) !== false && $ok;
    }

    /** Die Konfiguration mit den Anzeige-Optionen (ANZEIGEOPTIONEN), die die Anfrage mitbringt. */
    public function mitAnzeigeoptionen(Anfrage $anfrage): self
    {
        $werte = $this->werte;
        foreach (self::ANZEIGEOPTIONEN as $schluessel) {
            if ($anfrage->gesetzt($schluessel)) {
                $werte[$schluessel] = $anfrage->text($schluessel);
            }
        }

        return new self($werte);
    }

    /** Ein Wert wie in der Datei; fehlt er: die Vorgabe. */
    public function wert(string $schluessel, string $vorgabe = ''): string
    {
        $wert = $this->werte[$schluessel] ?? null;

        return is_scalar($wert) ? (string) $wert : $vorgabe;
    }

    /** Ein Wert als ganze Zahl; fehlt er: die Vorgabe. */
    public function zahl(string $schluessel, int $vorgabe = 0): int
    {
        $wert = $this->werte[$schluessel] ?? null;

        return is_scalar($wert) && $wert !== '' ? (int) $wert : $vorgabe;
    }

    /** Ein Schalter: an, wenn der Wert 1 ist. */
    public function schalter(string $schluessel): bool
    {
        return $this->wert($schluessel) == 1;
    }

    /**
     * Die Konfiguration eines Addons (config/<addon>/cfg.txt).
     *
     * @return array<string, string>
     */
    public function addon(string $name): array
    {
        $werte = $this->werte[$name] ?? [];
        $addon = [];
        foreach (is_array($werte) ? $werte : [] as $schluessel => $wert) {
            if (is_scalar($wert)) {
                $addon[(string) $schluessel] = (string) $wert;
            }
        }

        return $addon;
    }

    /** dirliga: Ordner der Ligadateien relativ zum LMO, mit "/" am Ende */
    public function ligenordner(): string
    {
        return $this->wert('dirliga', 'ligen/');
    }

    /** diroutput: Ordner fuer erzeugte Dateien relativ zum LMO */
    public function ausgabeordner(): string
    {
        return $this->wert('diroutput', 'output/');
    }

    /** defdateformat: Datumsformat in date()-Schreibweise */
    public function datumsformat(): string
    {
        return $this->wert('defdateformat', 'd.m.Y H:i');
    }

    /** deflang: Standardsprache (Name der Sprachdatei lang-<Name>.txt) */
    public function sprache(): string
    {
        return $this->wert('deflang', 'Deutsch');
    }

    /** tabonres: 0 Ergebnisse und Tabelle getrennt, 1 zusammen, 2 zusammen mit Heim- und Auswaertsbilanz */
    public function tabonres(): int
    {
        return $this->zahl('tabonres');
    }

    /** tabpkt: Punktespalte der Tabelle, 0 in der Mitte, 1 am rechten Rand */
    public function tabpkt(): int
    {
        return $this->zahl('tabpkt');
    }

    /** einzutore: Torstatistik des Spieltags unter den Ergebnissen */
    public function torstatistikErgebnisse(): bool
    {
        return $this->schalter('einzutore');
    }

    /** einzutoretab: Torstatistik der Saison unter der Tabelle */
    public function torstatistikTabelle(): bool
    {
        return $this->schalter('einzutoretab');
    }

    /** einspielfrei: spielfreie Mannschaften unter den Ergebnissen nennen */
    public function spielfreiNennen(): bool
    {
        return $this->schalter('einspielfrei');
    }
}
