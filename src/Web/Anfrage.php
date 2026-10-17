<?php

namespace Lmo\Web;

/**
 * Die Parameter eines Aufrufs (Adresse und Formular), mit typisierten Gettern. Controller lesen
 * hieraus statt aus $_GET, $_POST oder globalen Variablen.
 */
final class Anfrage
{
    /** @var array<string, mixed> */
    private array $werte;

    /** @param array<string, mixed> $werte Name => Wert */
    public function __construct(array $werte)
    {
        $this->werte = $werte;
    }

    /** Die Parameter des laufenden Aufrufs; wie $_REQUEST gilt das Formular vor der Adresse. */
    public static function ausGlobalen(): self
    {
        return new self($_POST + $_GET);
    }

    /**
     * Alle Parameter, wie sie kamen (fuer Addons nach alter Art, die sie als Variablen erwarten).
     *
     * @return array<string, mixed>
     */
    public function alle(): array
    {
        return $this->werte;
    }

    /** Ist der Parameter da, auch leer (wie isset())? */
    public function gesetzt(string $name): bool
    {
        return isset($this->werte[$name]);
    }

    /** Ist der Parameter gesetzt und nicht leer (wie empty(): "" und "0" gelten als leer)? */
    public function hat(string $name): bool
    {
        return !empty($this->werte[$name]);
    }

    /** Der Parameter als ganze Zahl; fehlt er oder ist er kein Text bzw. keine Zahl: die Vorgabe. */
    public function zahl(string $name, int $vorgabe = 0): int
    {
        $wert = $this->werte[$name] ?? null;

        return is_scalar($wert) && $wert !== '' ? (int) $wert : $vorgabe;
    }

    /** Der Parameter als Text; fehlt er oder ist er kein Text bzw. keine Zahl: die Vorgabe. */
    public function text(string $name, string $vorgabe = ''): string
    {
        $wert = $this->werte[$name] ?? null;

        return is_scalar($wert) ? (string) $wert : $vorgabe;
    }
}
