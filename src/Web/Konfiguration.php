<?php

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
        $werte = is_file($ordner . '/cfg.txt') ? parse_ini_file($ordner . '/cfg.txt') : false;
        if ($werte === false) {
            throw new RuntimeException('Konfiguration nicht lesbar: ' . $ordner . '/cfg.txt');
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

        return is_array($werte) ? $werte : [];
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
