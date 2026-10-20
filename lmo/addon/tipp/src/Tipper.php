<?php

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

    /** Vor- und Nachname, mit Leerzeichen getrennt */
    public function name(): string
    {
        return $this->feld(3);
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

        return new self($felder);
    }

    private function feld(int $nr): string
    {
        return $this->felder[$nr] ?? '';
    }
}
