<?php
/**
 * Laedt echte Ligen von OpenLigaDB (https://www.openligadb.de) und speichert je Saison eine
 * kompakte Datei unter tests/RealData/fixtures/openligadb/<liga>-<saison>.json:
 * Teams, alle Spiele mit Endergebnis und die Tabelle von OpenLigaDB.
 *
 * Aufruf (im Container):  php tests/RealData/bin/fetch_openligadb.php [liga ...]
 * Ohne Argumente werden alle Ligen aus LEAGUES geladen. Rohdaten landen in tests/RealData/.cache.
 */

const API = 'https://api.openligadb.de';
// Kuerzel => Sportart; alle bei OpenLigaDB vorhandenen Saisons werden geladen
const LEAGUES = [
    'bl1' => 'Fussball', 'bl2' => 'Fussball', 'bl3' => 'Fussball', 'fbl1' => 'Fussball',
    'PL' => 'Fussball', 'SA' => 'Fussball', 'PD' => 'Fussball',
    'hbl' => 'Handball',
];

$root = dirname(__DIR__);
$cacheDir = $root . '/.cache';
$outDir = $root . '/fixtures/openligadb';
@mkdir($cacheDir, 0777, true);
@mkdir($outDir, 0777, true);

function fetchJson(string $path, string $cacheDir, bool $refresh = false): array
{
    $file = $cacheDir . '/' . preg_replace('/[^A-Za-z0-9_-]+/', '_', trim($path, '/')) . '.json';
    if ($refresh || !is_file($file)) {
        $context = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 60]]);
        foreach ([1, 5, 20, 60] as $wait) {
            sleep($wait);  // freundlich zur API, bei Fehlern laenger warten
            $json = @file_get_contents(API . $path, false, $context);
            $status = $http_response_header[0] ?? 'keine Antwort';
            if ($json !== false && str_contains($status, ' 200') && json_decode($json) !== null) {
                break;
            }
            fwrite(STDERR, "  $path: $status, neuer Versuch\n");
            $json = false;
        }
        if ($json === false) {
            throw new RuntimeException("Abruf fehlgeschlagen: $path");
        }
        file_put_contents($file, $json);
    }
    return json_decode(file_get_contents($file), true) ?? [];
}

/** Endergebnis einer Partie oder null, wenn nicht gespielt. */
function finalResult(array $match): ?array
{
    if (empty($match['matchIsFinished'])) {
        return null;
    }
    $results = $match['matchResults'] ?? [];
    foreach ($results as $r) {
        if (($r['resultTypeID'] ?? null) == 2 || ($r['resultName'] ?? '') === 'Endergebnis') {
            return [(int)$r['pointsTeam1'], (int)$r['pointsTeam2']];
        }
    }
    // aeltere Saisons: Ergebnis mit der hoechsten resultOrderID ist das Endergebnis
    usort($results, static fn ($a, $b) => ($b['resultOrderID'] ?? 0) <=> ($a['resultOrderID'] ?? 0));
    return $results ? [(int)$results[0]['pointsTeam1'], (int)$results[0]['pointsTeam2']] : null;
}

$wanted = array_slice($argv, 1) ?: array_keys(LEAGUES);
$available = fetchJson('/getavailableleagues', $cacheDir, true);
$today = date('Y-m-d');

foreach ($wanted as $shortcut) {
    $seasons = [];
    foreach ($available as $l) {
        if ($l['leagueShortcut'] === $shortcut) {
            $seasons[(int)$l['leagueSeason']] = $l['leagueName'];
        }
    }
    ksort($seasons);
    foreach ($seasons as $season => $name) {
        try {
            // laufende Saison immer neu laden, abgeschlossene aus dem Cache
            $current = $season >= (int)date('Y') - 1;
            $matches = fetchJson("/getmatchdata/$shortcut/$season", $cacheDir, $current);
            $table = fetchJson("/getbltable/$shortcut/$season", $cacheDir, $current);
        } catch (RuntimeException $e) {
            fwrite(STDERR, $e->getMessage() . "\n");
            continue;
        }
        if (!$matches || !$table) {
            printf("%-6s %d: keine Daten\n", $shortcut, $season);
            continue;
        }

        $teams = [];
        $rows = [];
        $played = 0;
        foreach ($matches as $m) {
            foreach (['team1', 'team2'] as $side) {
                $teams[$m[$side]['teamId']] ??= ['id' => $m[$side]['teamId'], 'name' => $m[$side]['teamName'], 'short' => $m[$side]['shortName']];
            }
            $result = finalResult($m);
            $played += $result !== null;
            $rows[] = [(int)$m['group']['groupOrderID'], $m['team1']['teamId'], $m['team2']['teamId'], $result[0] ?? null, $result[1] ?? null];
        }
        usort($rows, static fn ($a, $b) => $a <=> $b);
        ksort($teams);

        $data = [
            'source' => 'OpenLigaDB, https://www.openligadb.de, abgerufen ' . $today,
            'league' => $shortcut,
            'season' => $season,
            'name' => $name,
            'sport' => LEAGUES[$shortcut] ?? 'Fussball',
            'finished' => $played === count($rows),
            'teams' => array_values($teams),
            'matches' => $rows,
            'table' => array_map(static fn ($t) => [
                'id' => $t['teamInfoId'], 'points' => $t['points'], 'matches' => $t['matches'],
                'won' => $t['won'], 'draw' => $t['draw'], 'lost' => $t['lost'],
                'goals' => $t['goals'], 'against' => $t['opponentGoals'],
            ], $table),
        ];
        $file = sprintf('%s/%s-%d.json', $outDir, $shortcut, $season);
        file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
        printf("%-6s %d: %2d Teams, %3d/%3d Spiele gespielt\n", $shortcut, $season, count($teams), $played, count($rows));
    }
}
