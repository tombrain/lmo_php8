<?php

declare(strict_types=1);

namespace Lmo\Addon\Tipp;

/**
 * Die Tipps eines Tippers fuer eine Liga (addon/tipp/tipps/<liga>_<nick>.tip): je Spieltag ein
 * Abschnitt [RoundN] mit dem Joker (@Spiel@) und den Tipps GA<Paarung>/GB<Paarung>, im Pokal
 * GA<Paarung><Spiel>. Bis 4.5 lmo-tippopenfile.php (lesen) und lmo-tippsavefile.php (schreiben).
 *
 * Dasselbe Format hat die Einsicht eines Spieltags (tipps/einsicht/<liga>_<st>.ein), dort mit
 * einem Abschnitt [<nick>] je Tipper (bis 4.5 lmo-tippsaveeinsicht1.php).
 */
final class Tippdatei
{
    /** Kein Tipp abgegeben */
    public const KEIN_TIPP = '_';

    /** @param list<string> $zeilen die Zeilen der Datei, ohne Zeilenende und ohne leere */
    private function __construct(private array $zeilen)
    {
    }

    /** Eine Tippdatei ohne Tipps (gespeichert eine Leerzeile, wie bis 4.5 eine neu angelegte). */
    public static function leer(): self
    {
        return new self([]);
    }

    /** Liest die Datei; fehlt sie, ist sie leer. */
    public static function lies(string $pfad): self
    {
        $zeilen = [];
        foreach (is_readable($pfad) ? (file($pfad) ?: []) : [] as $zeile) {
            $zeile = trim($zeile);
            if ($zeile != '') {
                $zeilen[] = $zeile;
            }
        }

        return new self($zeilen);
    }

    /**
     * Die Tipps eines Spieltags: Paarung (ab 0) => Tore Heim und Gast, im Pokal je Spiel (ab 0).
     * Fehlt ein Tipp, steht dort KEIN_TIPP.
     *
     * @param int $plaetze Paarungen des Spieltags
     * @param int|null $spiele Spiele je Paarung im Pokal; null in der Liga
     * @return array{tipps: array<int, mixed>, joker: string} tipps: [Paarung => [Heim, Gast]] bzw. [Paarung => [Spiel => [Heim, Gast]]]
     */
    public function runde(int $runde, int $plaetze, ?int $spiele): array
    {
        $leer = [self::KEIN_TIPP, self::KEIN_TIPP];
        $tipps = [];
        for ($i = 0; $i < $plaetze; $i++) {
            $tipps[$i] = $spiele === null ? $leer : array_fill(0, $spiele, $leer);
        }
        $abschnitt = '';
        // der Joker gilt, bis die naechste @-Zeile kommt, auch ueber den Abschnitt hinaus (wie bis 4.5)
        $jokerZeile = '';
        $joker = '';
        foreach ($this->zeilen as $zeile) {
            if (str_starts_with($zeile, '@') && str_ends_with($zeile, '@')) {
                $jokerZeile = trim(substr($zeile, 1, -1));
            } elseif (str_starts_with($zeile, '[') && str_ends_with($zeile, ']')) {
                $abschnitt = trim(substr($zeile, 1, -1));
            } elseif (str_contains($zeile, '=') && !str_starts_with($zeile, ';')) {
                if (substr($abschnitt, 0, 5) != 'Round' || (int) substr($abschnitt, 5) != $runde) {
                    continue;
                }
                $joker = $jokerZeile;
                $schluessel = trim(substr($zeile, 0, (int) strpos($zeile, '=')));
                $wert = trim(substr($zeile, (int) strpos($zeile, '=') + 1));
                $seite = substr($schluessel, 0, 2) == 'GA' ? 0 : (substr($schluessel, 0, 2) == 'GB' ? 1 : null);
                if ($seite === null) {
                    continue;
                }
                if ($wert == '' || $wert == '-1') {
                    $wert = self::KEIN_TIPP;
                } elseif ($wert == '-2' && $spiele === null) {
                    $wert = '0';
                }
                if ($spiele === null) {
                    $tipps[(int) substr($schluessel, 2) - 1][$seite] = $wert;
                } else {
                    $tipps[(int) substr($schluessel, 2, -1) - 1][(int) substr($schluessel, -1) - 1][$seite] = $wert;
                }
            }
        }

        return ['tipps' => $tipps, 'joker' => $joker];
    }

    /**
     * Die Tipps aller Spieltage einer Liga (bis 4.5 lmo-tippopenfileall.php): Spieltag (ab 0) =>
     * Paarung (ab 0) => Tore Heim und Gast. Ein leerer Tipp oder -1 ist KEIN_TIPP, andere Werte
     * bleiben, wie sie in der Datei stehen; fehlende Tipps fehlen auch hier.
     *
     * @return array<int, array<int, array{0: string, 1: string}>>
     */
    public function alleRunden(): array
    {
        $tipps = [];
        $abschnitt = '';
        foreach ($this->zeilen as $zeile) {
            if (str_starts_with($zeile, '@') && str_ends_with($zeile, '@')) {
                continue;
            }
            if (str_starts_with($zeile, '[') && str_ends_with($zeile, ']')) {
                $abschnitt = trim(substr($zeile, 1, -1));
            } elseif (str_contains($zeile, '=') && !str_starts_with($zeile, ';') && substr($abschnitt, 0, 5) == 'Round') {
                $schluessel = trim(substr($zeile, 0, (int) strpos($zeile, '=')));
                $seite = substr($schluessel, 0, 2) == 'GA' ? 0 : (substr($schluessel, 0, 2) == 'GB' ? 1 : null);
                if ($seite === null) {
                    continue;
                }
                $wert = trim(substr($zeile, (int) strpos($zeile, '=') + 1));
                $runde = (int) substr($abschnitt, 5) - 1;
                $paarung = (int) substr($schluessel, 2) - 1;
                $tipps[$runde][$paarung] ??= [self::KEIN_TIPP, self::KEIN_TIPP];
                $tipps[$runde][$paarung][$seite] = $wert == '' || $wert == '-1' ? self::KEIN_TIPP : $wert;
            }
        }

        return $tipps;
    }

    /**
     * Dieselbe Datei mit neuen Tipps fuer einen Spieltag: Sein Abschnitt kommt ans Ende, die
     * anderen bleiben, wie sie sind.
     *
     * @param array<int, mixed> $tipps wie runde(): [Paarung => [Heim, Gast]] bzw. im Pokal [Paarung => [Spiel => [Heim, Gast]]]
     * @param string|null $joker Spiel mit Joker; null ohne Jokertipp (keine @-Zeile)
     */
    public function mitRunde(int $runde, array $tipps, ?string $joker, bool $pokal): self
    {
        return $this->mitAbschnitt('Round' . $runde, $tipps, $joker, $pokal);
    }

    /**
     * Dieselbe Datei mit neuem Abschnitt (Spieltag "RoundN" oder in der Einsicht der Nick des Tippers)
     * am Ende; die anderen Abschnitte bleiben, wie sie sind.
     *
     * @param array<int, mixed> $tipps wie mitRunde()
     */
    public function mitAbschnitt(string $name, array $tipps, ?string $joker, bool $pokal): self
    {
        $zeilen = [];
        $abschnitt = '';
        foreach ($this->zeilen as $zeile) {
            if (str_starts_with($zeile, '[') && str_ends_with($zeile, ']')) {
                $abschnitt = substr($zeile, 1, -1);
            }
            if ($abschnitt != $name) {
                $zeilen[] = $zeile;
            }
        }
        $zeilen[] = '[' . $name . ']';
        if ($joker !== null) {
            $zeilen[] = '@' . $joker . '@';
        }
        $wert = static fn (mixed $tipp): string => $tipp === self::KEIN_TIPP || $tipp === '' || !is_scalar($tipp) ? '-1' : (string) $tipp;
        foreach ($tipps as $i => $paarung) {
            foreach ($pokal ? (is_array($paarung) ? $paarung : []) : [$paarung] as $n => $spiel) {
                $nr = ($i + 1) . ($pokal ? (string) ($n + 1) : '');
                $zeilen[] = 'GA' . $nr . '=' . $wert(is_array($spiel) ? ($spiel[0] ?? '') : '');
                $zeilen[] = 'GB' . $nr . '=' . $wert(is_array($spiel) ? ($spiel[1] ?? '') : '');
            }
        }

        return new self($zeilen);
    }

    /**
     * Schreibt die Datei wie bis 4.5: eine Leerzeile, die uebrigen Abschnitte, eine Leerzeile vor
     * dem zuletzt geschriebenen Spieltag. false, wenn das nicht ging.
     */
    public function speichere(string $pfad): bool
    {
        $inhalt = "\n";
        $letzter = $this->letzterAbschnitt();
        foreach ($this->zeilen as $nr => $zeile) {
            $inhalt .= ($nr === $letzter ? "\n" : '') . $zeile . "\n";
        }

        return @file_put_contents($pfad, $inhalt, LOCK_EX) !== false;
    }

    /** Die Zeile, mit der der letzte Abschnitt beginnt; null ohne Abschnitt. */
    private function letzterAbschnitt(): ?int
    {
        $letzter = null;
        foreach ($this->zeilen as $nr => $zeile) {
            if (str_starts_with($zeile, '[')) {
                $letzter = $nr;
            }
        }

        return $letzter;
    }
}
