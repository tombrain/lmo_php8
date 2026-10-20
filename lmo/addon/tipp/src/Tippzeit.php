<?php

declare(strict_types=1);

namespace Lmo\Addon\Tipp;

/**
 * Bis wann ein Spiel getippt werden kann: bis zum Anstoss, ohne Anstosszeit bis zum ersten
 * (sonst letzten) Tag des Spieltags, um die Uhrzeit aus der Konfiguration (deftime) oder 0 Uhr,
 * jeweils abzueglich tippBis Minuten. Bis 4.5 tippaenderbar() und zeit() mit globalen Variablen
 * in lmo-tippaenderbar.php.
 */
final class Tippzeit
{
    /**
     * @param int $tippBis Minuten vor dem Anstoss, bis zu denen getippt werden kann (tipp/cfg.txt tippBis)
     * @param bool $ohneAnstoss Spiele ohne Anstosszeit zur Uhrzeit $standardzeit (tipp/cfg.txt tippohne)
     * @param string $standardzeit Uhrzeit HH:MM fuer Spiele ohne Anstosszeit (cfg.txt deftime)
     * @param int $imVoraus wie viele Spieltage nach dem aktuellen getippt werden koennen, -1 = alle (tipp/cfg.txt imvorraus)
     */
    public function __construct(
        private int $tippBis,
        private bool $ohneAnstoss,
        private string $standardzeit,
        private int $imVoraus,
    ) {
    }

    /**
     * Kann der Tipp noch abgegeben oder geaendert werden?
     *
     * @param int $anstoss Anstoss des Spiels als Zeitstempel, 0 = ohne
     * @param string $ersterTag erster Tag des Spieltags TT.MM.JJJJ ('' ohne)
     * @param string $letzterTag letzter Tag des Spieltags TT.MM.JJJJ ('' ohne)
     * @param int|string|null $spieltag gezeigter Spieltag
     * @param int $aktuellerSpieltag aktueller Spieltag der Liga (0 ohne Liga)
     * @param int $plus zusaetzliche Minuten Sicherheitsabstand (1 zwischen Seitenaufruf und Speichern)
     * @param int|null $jetzt Zeitstempel fuer "jetzt" (null: time())
     */
    public function aenderbar(int $anstoss, string $ersterTag, string $letzterTag, int|string|null $spieltag, int $aktuellerSpieltag, int $plus, ?int $jetzt = null): bool
    {
        if ($this->imVoraus >= 0 && $spieltag > ($aktuellerSpieltag + $this->imVoraus)) {
            return false;
        }
        $grenze = (int) strtotime('+' . ($this->tippBis + $plus) . ' minute', $jetzt ?? time());
        if ($anstoss > 0) {
            return $grenze < $anstoss;
        }
        if ($ersterTag != '') {
            return $grenze < $this->tag($ersterTag);
        }
        if ($letzterTag != '') {
            return $grenze < $this->tag($letzterTag);
        }

        return false;
    }

    /**
     * Bis wann das Spiel getippt werden kann, als Zeitstempel; null ohne Anstoss und Tag.
     *
     * @param int $anstoss Anstoss des Spiels als Zeitstempel, 0 = ohne
     * @param string $ersterTag erster Tag des Spieltags TT.MM.JJJJ ('' ohne)
     * @param string $letzterTag letzter Tag des Spieltags TT.MM.JJJJ ('' ohne)
     */
    public function bis(int $anstoss, string $ersterTag, string $letzterTag): ?int
    {
        if ($anstoss > 0) {
            $zeit = $anstoss;
        } elseif ($ersterTag != '') {
            $zeit = $this->tag($ersterTag);
        } elseif ($letzterTag != '') {
            $zeit = $this->tag($letzterTag);
        } else {
            return null;
        }

        return $zeit - $this->tippBis * 60;
    }

    /** Der Tag TT.MM.JJJJ um die Uhrzeit fuer Spiele ohne Anstoss, sonst um 0 Uhr. */
    private function tag(string $datum): int
    {
        if ($this->ohneAnstoss && $this->standardzeit > 0) {
            return (int) mktime((int) substr($this->standardzeit, 0, 2), (int) substr($this->standardzeit, 3, 2), 0, (int) substr($datum, 3, 2), (int) substr($datum, 0, 2), (int) substr($datum, -4));
        }

        return (int) mktime(0, 0, 0, (int) substr($datum, 3, 2), (int) substr($datum, 0, 2), (int) substr($datum, -4));
    }
}
