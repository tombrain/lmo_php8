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

namespace Lmo\Addon\Tipp;

/**
 * Ein Tipper, wie er in der Tipperdatei steht (eine Zeile von lmo-tippauth.txt):
 * nick|kennwort|status|name|email|verein|strasse|plz|ort|newsletter|erinnerung|EOL.
 * Das Kennwort ist ein Hash (password_hash()); status 5 = freigeschaltet.
 */
final class Tipper
{
    /** @param list<string> $felder die Felder der Zeile, wie sie in der Datei stehen */
    private function __construct(public readonly array $felder)
    {
    }

    public static function ausZeile(string $zeile): self
    {
        return new self(explode('|', $zeile));
    }

    public function zeile(): string
    {
        return implode('|', $this->felder);
    }

    public function nick(): string
    {
        return $this->feld(0);
    }

    public function kennwort(): string
    {
        return $this->feld(1);
    }

    /** Vor- und Nachname, mit Leerzeichen getrennt (bis 4.5 schrieb die Registrierung &nbsp; dazwischen) */
    public function name(): string
    {
        return $this->feld(3);
    }

    /**
     * Vor- und Nachname aus dem gespeicherten Namen: getrennt am ersten Leerzeichen oder &nbsp;
     * (so schrieb die Registrierung bis 4.5); ohne Trennzeichen ist alles der Nachname, nur &nbsp;
     * ein leerer Name. Bis 4.5 fehlte ohne Leerzeichen das erste Zeichen.
     *
     * @return array{0: string, 1: string}
     */
    public static function teileName(string $name): array
    {
        $name = str_replace('&nbsp;', ' ', $name);
        $leer = strpos($name, ' ');

        return $leer === false ? ['', $name] : [substr($name, 0, $leer), substr($name, $leer + 1)];
    }

    public function email(): string
    {
        return $this->feld(4);
    }

    public function verein(): string
    {
        return $this->feld(5);
    }

    /** Passt das Kennwort zum gespeicherten Hash? */
    public function pruefeKennwort(string $kennwort): bool
    {
        return password_verify($kennwort, $this->kennwort());
    }

    /** Derselbe Tipper mit neuem Kennwort (als Hash gespeichert). */
    public function mitKennwort(string $kennwort): self
    {
        $felder = $this->felder;
        $felder[1] = password_hash($kennwort, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT);

        return new self(array_values($felder));
    }

    /** Ein Feld der Zeile nach seiner Nummer (0 = Nick); '' fehlt es. */
    public function feld(int $nr): string
    {
        return $this->felder[$nr] ?? '';
    }
}
