<?php

namespace Lmo\Tests\RealData\Support;

use RuntimeException;

/**
 * Eine echte Liga aus tests/RealData/fixtures (siehe bin/fetch_openligadb.php und
 * bin/fetch_wikipedia.php) und ihre Umwandlung in eine LMO-Ligadatei.
 *
 * Pruefmassstab ist die offizielle Abschlusstabelle aus Wikipedia ("official"), sonst die von
 * OpenLigaDB selbst berechnete Tabelle ("table"). Diese wertet keinen direkten Vergleich und
 * bricht vollstaendigen Gleichstand beliebig.
 *
 * Teams bekommen die Nummern 1..n in der Reihenfolge der Datei. Die Punkteregel (3/1/0 oder 2/1/0)
 * wird aus der Tabelle abgeleitet, Abweichungen davon (Punktabzuege) werden als Strafpunkte (SP)
 * eingetragen.
 *
 * Konstruierte Ligen (fixtures/konstruiert) decken Faelle ab, die echte Ligen nicht liefern. Sie
 * duerfen zusaetzlich enthalten:
 *   options    LMO-Optionen fuer [Options], z.B. {"Kegel": 1, "MinusPoints": 2}
 *   penalties  Team-ID => Werte fuer [TeamN] wie in der Ligadatei: SP, SM, TOR1, TOR2, STDA
 *              (TOR1/TOR2 negativ = Bonustore, so speichert lmo-adminteams.php "+x")
 *   matches    6. Element je Partie: weitere Schluessel der Partie, z.B. {"SP": 2} (n.V.)
 *   official   je Team optional "points" und "minus", sonst aus der Punkteregel berechnet
 *   ties_free  true: Teams mit identischen Werten sind in der Reihenfolge austauschbar
 */
final class RealLeague
{
    /** Ligen, die bei Punktgleichheit zuerst den direkten Vergleich werten */
    private const DIRECT = ['SA', 'PD'];

    /**
     * Saisons, deren offizielle Reihenfolge eine Regel braucht, die LMO nicht abbildet.
     * Die Werte (Spiele, Punkte, Tore) werden trotzdem geprueft, nur die Reihenfolge nicht.
     */
    private const RULE_VARIANTS = [
        'PD-2010' => 'La Liga: Saragossa, Levante und Sociedad je 45 Punkte. Im Dreiervergleich sind Levante und '
            . 'Sociedad gleichauf (4 Pkt, -1); La Liga entscheidet danach ueber das Duell der beiden bzw. die '
            . 'Gesamt-Tordifferenz (Levante), LMO ueber die im direkten Vergleich erzielten Tore (Sociedad).',
        // Abnahmetest: Eintrag entfernen, sobald LMO Schritt 4 der Bundesliga-Reihenfolge umsetzt
        'dfl-schritt4-1' => 'Bundesliga Schritt 4: Alpha und Beta gleich nach Punkten, Tordifferenz und Toren, '
            . 'Alpha gewinnt den direkten Vergleich. LMO kennt diesen Schritt nicht (Direct=0) und sortiert nach '
            . 'Teamnummer (Kern) bzw. Teamname (classlib).',
    ];

    private array $data;
    /** @var array<int,int> Team-ID der Quelle => LMO-Teamnummer */
    private array $nr = [];
    /** @var array<int,array> Tabelle des Pruefmassstabs: id, won, draw, lost, goals, against, matches, points */
    private array $table;
    private array $points;

    private function __construct(array $data)
    {
        $this->data = $data;
        foreach ($data['teams'] as $i => $team) {
            $this->nr[$team['id']] = $i + 1;
        }
        if (isset($data['official'])) {
            $options = $data['options'] ?? [];
            $this->points = [
                (int)($options['PointsForWin'] ?? ($data['sport'] === 'Handball' ? 2 : 3)),
                (int)($options['PointsForDraw'] ?? 1),
                (int)($options['PointsForLost'] ?? 0),
            ];
            [$win, $draw, $lost] = $this->points;
            $this->table = array_map(static fn ($row) => $row + [
                'matches' => $row['won'] + $row['draw'] + $row['lost'],
                'points' => $row['won'] * $win + $row['draw'] * $draw + $row['lost'] * $lost + $row['adjust'],
            ], $data['official']['table']);
        } else {
            $this->table = $data['table'];
            $this->points = $this->detectPoints();
        }
    }

    public static function load(string $file): self
    {
        $data = json_decode((string)file_get_contents($file), true);
        if (!is_array($data) || empty($data['teams']) || (empty($data['table']) && empty($data['official']))) {
            throw new RuntimeException("Keine Ligadaten in $file");
        }
        return new self($data);
    }

    /** @return string[] alle Fixture-Dateien */
    public static function files(): array
    {
        $files = glob(dirname(__DIR__) . '/fixtures/*/*.json') ?: [];
        sort($files);
        return $files;
    }

    public function id(): string
    {
        return $this->data['league'] . '-' . $this->data['season'];
    }

    public function name(): string
    {
        return $this->data['name'];
    }

    /** Herkunft der Vergleichstabelle */
    public function source(): string
    {
        return isset($this->data['official']) ? $this->data['official']['source'] : $this->data['source'];
    }

    public function official(): bool
    {
        return isset($this->data['official']);
    }

    /** Liga wertet bei Punktgleichheit zuerst den direkten Vergleich. */
    private function direct(): bool
    {
        return (bool)($this->data['options']['Direct'] ?? in_array($this->data['league'], self::DIRECT, true));
    }

    /** Teams mit identischen Werten duerfen in beliebiger Reihenfolge stehen. */
    public function tiesFree(): bool
    {
        return !$this->official() || !empty($this->data['ties_free']);
    }

    /** Begruendung, wenn die Reihenfolge einer Regel folgt, die LMO nicht kennt, sonst null. */
    public function ruleVariant(): ?string
    {
        return self::RULE_VARIANTS[$this->id()] ?? null;
    }

    public function usable(): bool
    {
        return $this->problem() === null;
    }

    /**
     * Warum die Liga nicht als Pruefmassstab taugt, oder null. Geprueft wird nur, ob Tabelle und
     * Spiele zueinander passen (Spiele und Tore je Team), nicht die Rechnung selbst.
     */
    public function problem(): ?string
    {
        if (!$this->official() && $this->direct()) {
            return 'direkter Vergleich, aber nur die OpenLigaDB-Tabelle (ohne direkten Vergleich) vorhanden';
        }
        $count = [];
        foreach ($this->data['matches'] as $match) {
            [, $home, $away, $hg, $ag] = $match;
            if ($hg === null || $hg == -1) {
                continue;
            }
            // -2 = Gruener Tisch: zaehlt als Spiel, ohne Tore fuer diese Seite
            [$hg, $ag] = [max(0, $hg), max(0, $ag)];
            // ET=3 = beidseitiges Ergebnis: beide Teams bekommen das Ergebnis aus Sicht der Heimmannschaft
            $both = ($match[5]['ET'] ?? 0) == 3;
            foreach ([[$home, $hg, $ag], [$away, $both ? $hg : $ag, $both ? $ag : $hg]] as [$team, $for, $against]) {
                $count[$team] ??= [0, 0, 0];
                $count[$team][0]++;
                $count[$team][1] += $for;
                $count[$team][2] += $against;
            }
        }
        if (!$count) {
            return 'keine gespielten Partien';
        }
        // Bonus-/Straftore (TOR1/TOR2 wie in der Ligadatei: negativ = mehr Tore)
        foreach ($this->data['penalties'] ?? [] as $team => $keys) {
            $count[$team][1] -= (int)($keys['TOR1'] ?? 0);
            $count[$team][2] -= (int)($keys['TOR2'] ?? 0);
        }
        if (count($this->table) !== count($this->data['teams'])) {
            return 'Tabelle und Spiele haben unterschiedlich viele Teams';
        }
        foreach ($this->table as $row) {
            if (!isset($this->nr[$row['id']])) {
                return "Team {$row['id']} der Tabelle spielt nicht mit";
            }
            $own = $count[$row['id']] ?? [0, 0, 0];
            if ($own !== [$row['matches'], $row['goals'], $row['against']]) {
                return sprintf('%s: Tabelle %d Sp %d:%d, Spiele ergeben %d Sp %d:%d', $this->teamName($this->nr[$row['id']]),
                    $row['matches'], $row['goals'], $row['against'], ...$own);
            }
        }
        return null;
    }

    public function rounds(): int
    {
        return max(array_column($this->data['matches'], 0));
    }

    /** Letzter Spieltag mit mindestens einem Ergebnis. */
    public function lastPlayedRound(): int
    {
        $rounds = array_column(array_filter($this->data['matches'], static fn ($m) => $m[3] !== null), 0);
        return $rounds ? max($rounds) : 1;
    }

    /** @return array{0:int,1:int,2:int} Punkte fuer Sieg, Unentschieden, Niederlage */
    public function pointRule(): array
    {
        return $this->points;
    }

    /** @return array<int,int> LMO-Teamnummer => Strafpunkte (positiv = Abzug) */
    public function deductions(): array
    {
        if (isset($this->data['penalties'])) {
            return [];  // konstruierte Liga: Strafen stehen ausdruecklich in "penalties"
        }
        [$win, $draw, $lost] = $this->points;
        $result = [];
        foreach ($this->table as $row) {
            $diff = $row['won'] * $win + $row['draw'] * $draw + $row['lost'] * $lost - $row['points'];
            if ($diff != 0) {
                $result[$this->nr[$row['id']]] = $diff;
            }
        }
        return $result;
    }

    /** @return int[] LMO-Teamnummern in der Reihenfolge der Vergleichstabelle */
    public function expectedOrder(): array
    {
        return array_map(fn ($row) => $this->nr[$row['id']], $this->table);
    }

    /** @return array<int,array{points:int,goals:int,against:int,matches:int}> Tabellenwerte je LMO-Teamnummer */
    public function expectedRows(): array
    {
        $rows = [];
        foreach ($this->table as $row) {
            $rows[$this->nr[$row['id']]] = [
                'points' => $row['points'], 'goals' => $row['goals'], 'against' => $row['against'], 'matches' => $row['matches'],
            ] + (isset($row['minus']) ? ['minus' => $row['minus']] : []);
        }
        return $rows;
    }

    public function teamName(int $nr): string
    {
        return $this->data['teams'][$nr - 1]['name'];
    }

    public function toL98(): string
    {
        [$win, $draw, $lost] = $this->points;
        $byRound = [];
        foreach ($this->data['matches'] as $match) {
            [$round, $home, $away, $hg, $ag] = $match;
            $byRound[$round][] = [$this->nr[$home], $this->nr[$away], $hg ?? -1, $ag ?? -1, $match[5] ?? []];
        }
        $teams = count($this->data['teams']);
        $options = [
            'Title' => 'LMO', 'Name' => $this->data['name'], 'Type' => 0, 'Teams' => $teams,
            'Rounds' => $this->rounds(), 'Matches' => max(array_map('count', $byRound)),
            'Actual' => $this->lastPlayedRound(),
            'PointsForWin' => $win, 'PointsForDraw' => $draw, 'PointsForLost' => $lost,
            'Kegel' => 0, 'HandS' => 0, 'Spez' => 0, 'HideDraw' => 0, 'OnRun' => 0,
            'MinusPoints' => 1, 'Direct' => $this->direct() ? 1 : 0,
            'Champ' => 1, 'CL' => 0, 'CK' => 0, 'UC' => 0, 'AR' => 0, 'AB' => 0,
            'namePkt' => 'Pkt.', 'nameTor' => 'Tore', 'DatF' => 'd.m.Y H:i',
        ];
        $options = array_merge($options, $this->data['options'] ?? []);
        $out = "[Options]\n";
        foreach ($options as $key => $value) {
            $out .= "$key=$value\n";
        }
        $out .= "\n[Teams]\n";
        foreach ($this->data['teams'] as $i => $team) {
            $out .= ($i + 1) . '=' . $team['name'] . "\n";
        }
        $out .= "\n[Teamk]\n";
        foreach ($this->data['teams'] as $i => $team) {
            $out .= ($i + 1) . '=' . ($team['short'] ?: $team['name']) . "\n";
        }
        $deductions = $this->deductions();
        foreach ($this->data['teams'] as $i => $team) {
            $nr = $i + 1;
            $keys = ($this->data['penalties'][$team['id']] ?? []) + ['SP' => $deductions[$nr] ?? 0, 'SM' => 0, 'TOR1' => 0, 'TOR2' => 0, 'STDA' => 0];
            $out .= "\n[Team$nr]\n";
            foreach (['SP', 'SM', 'TOR1', 'TOR2', 'STDA'] as $key) {
                $out .= "$key={$keys[$key]}\n";
            }
            $out .= "URL=\nNOT=\n";
        }
        for ($round = 1; $round <= $this->rounds(); $round++) {
            $out .= "\n[Round$round]\nD1=\nD2=\n";
            foreach ($byRound[$round] ?? [] as $i => [$home, $away, $hg, $ag, $extra]) {
                $n = $i + 1;
                $out .= "TA$n=$home\nTB$n=$away\nGA$n=$hg\nGB$n=$ag\n";
                foreach ($extra as $key => $value) {
                    $out .= "$key$n=$value\n";
                }
                $out .= "NT$n=\nBE$n=\nAT$n=0\n";
            }
        }
        return $out . "\n";
    }

    /**
     * Punkteregel, die zur OpenLigaDB-Tabelle passt: die Regel, bei der die meisten Teams ohne
     * Abzug aufgehen (Abzuege betreffen immer nur einzelne Teams).
     */
    private function detectPoints(): array
    {
        $best = null;
        foreach ([[3, 1, 0], [2, 1, 0]] as $rule) {
            $fits = count(array_filter($this->table, static fn ($row) =>
                $row['won'] * $rule[0] + $row['draw'] * $rule[1] + $row['lost'] * $rule[2] == $row['points']));
            if ($best === null || $fits > $best[1]) {
                $best = [$rule, $fits];
            }
        }
        return $best[0];
    }
}
