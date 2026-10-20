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
