<?php

declare(strict_types=1);

namespace Lmo\Web;

/**
 * Die Werte der Sitzung (Session), etwa wer im Tippspiel angemeldet ist. Controller lesen und
 * schreiben sie hierueber statt ueber $_SESSION; im Test genuegt ein Array.
 */
final class Sitzung
{
    /** @var array<mixed> */
    private array $werte;

    /** @param array<string, mixed> $werte Name => Wert */
    public function __construct(array $werte = [])
    {
        $this->werte = $werte;
    }

    /** Die Sitzung des laufenden Aufrufs: Aenderungen gehen in $_SESSION; ohne gestartete Sitzung leer. */
    public static function ausGlobalen(): self
    {
        $sitzung = new self();
        if (isset($_SESSION)) {
            $sitzung->werte = &$_SESSION;
        }

        return $sitzung;
    }

    /** Ist der Wert da (wie isset())? */
    public function gesetzt(string $name): bool
    {
        return isset($this->werte[$name]);
    }

    /** Der Wert als ganze Zahl; fehlt er oder ist er kein Text bzw. keine Zahl: die Vorgabe. */
    public function zahl(string $name, int $vorgabe = 0): int
    {
        $wert = $this->werte[$name] ?? null;

        return is_scalar($wert) && $wert !== '' ? (int) $wert : $vorgabe;
    }

    /** Der Wert als Text; fehlt er oder ist er kein Text bzw. keine Zahl: die Vorgabe. */
    public function text(string $name, string $vorgabe = ''): string
    {
        $wert = $this->werte[$name] ?? null;

        return is_scalar($wert) ? (string) $wert : $vorgabe;
    }

    public function setze(string $name, int|string $wert): void
    {
        $this->werte[$name] = $wert;
    }
}
