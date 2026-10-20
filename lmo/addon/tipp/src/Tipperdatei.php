<?php

declare(strict_types=1);

namespace Lmo\Addon\Tipp;

/**
 * Die Tipperdatei (addon/tipp/lmo-tippauth.txt, tipp/cfg.txt tippauthtxt): eine Schutzzeile, dann
 * je Tipper eine Zeile (Lmo\Addon\Tipp\Tipper). Bis 4.5 lasen und schrieben die Seiten des
 * Tippspiels sie jede selbst (lmo-tippsaveauth.php).
 */
final class Tipperdatei
{
    /** Erste Zeile der Datei: Wird sie als PHP aufgerufen, gibt sie nichts aus. */
    public const SCHUTZZEILE = '<?php exit; ?>';

    /** @param list<string> $zeilen die Zeilen der Datei ohne Zeilenende und ohne leere */
    private function __construct(private string $pfad, private array $zeilen)
    {
    }

    /** Liest die Datei; fehlt sie, ist sie leer (bis auf die Schutzzeile). */
    public static function lies(string $pfad): self
    {
        $zeilen = [];
        foreach (is_readable($pfad) ? (file($pfad) ?: []) : [self::SCHUTZZEILE] as $zeile) {
            $zeile = trim($zeile);
            if ($zeile != '') {
                $zeilen[] = $zeile;
            }
        }

        return new self($pfad, $zeilen);
    }

    /**
     * Alle Tipper in der Reihenfolge der Datei.
     *
     * @return list<Tipper>
     */
    public function alle(): array
    {
        $tipper = [];
        foreach ($this->zeilen as $zeile) {
            if ($zeile != self::SCHUTZZEILE) {
                $tipper[] = Tipper::ausZeile($zeile);
            }
        }

        return $tipper;
    }

    /** Der Tipper mit diesem Nick; null, wenn es ihn nicht gibt. */
    public function tipper(string $nick): ?Tipper
    {
        foreach ($this->zeilen as $zeile) {
            if ($zeile != self::SCHUTZZEILE && explode('|', $zeile, 2)[0] == $nick) {
                return Tipper::ausZeile($zeile);
            }
        }

        return null;
    }

    /** Der (erste) Tipper mit dieser E-Mail-Adresse; null, wenn es keinen gibt. */
    public function tipperMitEmail(string $email): ?Tipper
    {
        foreach ($this->zeilen as $zeile) {
            $tipper = $zeile != self::SCHUTZZEILE ? Tipper::ausZeile($zeile) : null;
            if ($tipper !== null && str_contains($tipper->email(), '@') && $tipper->email() == $email) {
                return $tipper;
            }
        }

        return null;
    }

    /** Dieselbe Datei mit geaendertem Tipper (gleicher Nick, an seiner Stelle). */
    public function mit(Tipper $tipper): self
    {
        $zeilen = $this->zeilen;
        foreach ($zeilen as $nr => $zeile) {
            if ($zeile != self::SCHUTZZEILE && explode('|', $zeile, 2)[0] == $tipper->nick()) {
                $zeilen[$nr] = $tipper->zeile();
            }
        }

        return new self($this->pfad, $zeilen);
    }

    /**
     * Die Vereine (Teams) der Tipper in der Reihenfolge ihres ersten Tippers, mit der Zahl ihrer Tipper.
     *
     * @return array<int|string, int> Verein => Tipper (ein Verein aus Ziffern wird ein int-Schluessel)
     */
    public function vereine(): array
    {
        $vereine = [];
        foreach ($this->zeilen as $zeile) {
            $verein = $zeile != self::SCHUTZZEILE ? Tipper::ausZeile($zeile)->verein() : '';
            if ($verein != '') {
                $vereine[$verein] = ($vereine[$verein] ?? 0) + 1;
            }
        }

        return $vereine;
    }

    /** Dieselbe Datei mit einem neuen Tipper am Ende. */
    public function plus(Tipper $tipper): self
    {
        $zeilen = $this->zeilen;
        $zeilen[] = $tipper->zeile();

        return new self($this->pfad, $zeilen);
    }

    /** Dieselbe Datei ohne den Tipper mit diesem Nick. */
    public function ohne(string $nick): self
    {
        $zeilen = [];
        foreach ($this->zeilen as $zeile) {
            if ($zeile == self::SCHUTZZEILE || explode('|', $zeile, 2)[0] != $nick) {
                $zeilen[] = $zeile;
            }
        }

        return new self($this->pfad, $zeilen);
    }

    /** Schreibt die Datei; false, wenn das nicht ging. */
    public function speichere(): bool
    {
        $inhalt = '';
        foreach ($this->zeilen as $zeile) {
            $inhalt .= $zeile . "\n";
        }

        return @file_put_contents($this->pfad, $inhalt, LOCK_EX) !== false;
    }
}
