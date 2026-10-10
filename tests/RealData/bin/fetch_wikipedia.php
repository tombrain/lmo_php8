<?php
/**
 * Ergaenzt die Fixtures um die offizielle Abschlusstabelle aus der englischen Wikipedia
 * (Vorlage {{#invoke:sports table}} im Saisonartikel). Gespeichert wird sie unter "official":
 * Reihenfolge und Bilanz je Team, Zuordnung zu den OpenLigaDB-Teams ueber die Bilanz
 * (Siege, Unentschieden, Niederlagen, Tore, Gegentore), weil die Namen abweichen.
 *
 * Nur abgeschlossene Saisons. Aufruf (im Container): php tests/RealData/bin/fetch_wikipedia.php
 */

// OpenLigaDB-Kuerzel => Artikeltitel; %s = Saison wie "2013–14"
const TITLES = [
    'bl1' => '%s Bundesliga',
    'bl2' => '%s 2. Bundesliga',
    'bl3' => '%s 3. Liga',
    'PL' => '%s Premier League',
    'SA' => '%s Serie A',
    'PD' => '%s La Liga',
    'hbl' => '%s Handball-Bundesliga',
];

$root = dirname(__DIR__);
$cacheDir = $root . '/.cache';
@mkdir($cacheDir, 0777, true);

function wikitext(string $title, string $cacheDir): ?string
{
    $file = $cacheDir . '/wiki_' . preg_replace('/[^A-Za-z0-9_-]+/', '_', $title) . '.txt';
    if (!is_file($file)) {
        sleep(1);
        $url = 'https://en.wikipedia.org/w/index.php?action=raw&title=' . rawurlencode(str_replace(' ', '_', $title));
        $context = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 60,
            'header' => "User-Agent: lmo-php8-tests/1.0 (Testdaten fuer Liga Manager Online)\r\n"]]);
        $text = @file_get_contents($url, false, $context);
        if ($text === false || !str_contains($http_response_header[0] ?? '', ' 200')) {
            return null;
        }
        file_put_contents($file, $text);
    }
    return file_get_contents($file);
}

/**
 * Erste Ligatabelle im Artikel: Reihenfolge und Bilanz je Kuerzel.
 *
 * @return array<int,array{code:string,won:int,draw:int,lost:int,goals:int,against:int,adjust:int}>|null
 */
function parseTable(string $text): ?array
{
    if (!preg_match('/\{\{#invoke:\s*sports table\s*\|\s*main(.*?)\n\}\}/is', $text, $m)) {
        return null;
    }
    $body = $m[1];
    // Kuerzel koennen Umlaute enthalten (KÖL), mehrere Eintraege stehen oft in einer Zeile
    preg_match_all('/\|\s*team(\d+)\s*=\s*([^\s|}]+)/u', $body, $order, PREG_SET_ORDER);
    // Kurzform: |team_order=MUN, LEI, DOR, ...
    if (!$order && preg_match('/\|\s*team_order\s*=\s*([^|}]+)/u', $body, $list)) {
        foreach (preg_split('/\s*,\s*/u', trim(preg_replace('/<!--.*?-->/s', '', $list[1]))) as $i => $code) {
            $order[] = [null, $i + 1, $code];
        }
    }
    if (!$order) {
        return null;
    }
    $value = static function (string $field, string $code) use ($body): ?int {
        return preg_match('/\|\s*' . $field . '_' . preg_quote($code, '/') . '\s*=\s*(-?\d+)/u', $body, $v) ? (int)$v[1] : null;
    };
    usort($order, static fn ($a, $b) => (int)$a[1] <=> (int)$b[1]);
    $rows = [];
    foreach ($order as [, , $code]) {
        $row = ['code' => $code];
        foreach (['win' => 'won', 'draw' => 'draw', 'loss' => 'lost', 'gf' => 'goals', 'ga' => 'against'] as $field => $key) {
            $row[$key] = $value($field, $code);
            if ($row[$key] === null) {
                return null;
            }
        }
        $row['adjust'] = (int)($value('adjust_points', $code) ?? 0) + (int)($value('startpoints', $code) ?? 0);
        $rows[] = $row;
    }
    return $rows;
}

foreach (glob($root . '/fixtures/openligadb/*.json') as $file) {
    $data = json_decode(file_get_contents($file), true);
    $id = $data['league'] . '-' . $data['season'];
    if (!isset(TITLES[$data['league']]) || !$data['finished']) {
        continue;
    }
    $season = sprintf('%d–%02d', $data['season'], ($data['season'] + 1) % 100);
    $title = sprintf(TITLES[$data['league']], $season);
    $text = wikitext($title, $cacheDir);
    $table = $text === null ? null : parseTable($text);
    // neuere Artikel binden die Tabelle als Vorlage ein: {{2020–21 Bundesliga table}}
    if ($table === null && $text !== null && preg_match('/\{\{\s*([^{}|]*\btable)\s*\}\}/iu', $text, $include)) {
        $title = 'Template:' . trim($include[1]);
        $text = wikitext($title, $cacheDir);
        $table = $text === null ? null : parseTable($text);
    }
    if ($table === null) {
        printf("%-9s %s: keine Tabelle gefunden\n", $id, $title);
        continue;
    }

    // Bilanz je Team aus den Spielen
    $stats = [];
    foreach ($data['matches'] as [, $home, $away, $hg, $ag]) {
        if ($hg === null) {
            continue;
        }
        foreach ([[$home, $hg, $ag], [$away, $ag, $hg]] as [$team, $for, $against]) {
            $stats[$team] ??= [0, 0, 0, 0, 0];
            $stats[$team][$for > $against ? 0 : ($for == $against ? 1 : 2)]++;
            $stats[$team][3] += $for;
            $stats[$team][4] += $against;
        }
    }
    $byStats = [];
    foreach ($stats as $team => $s) {
        $byStats[implode('/', $s)][] = $team;
    }

    $official = [];
    $missing = [];
    foreach ($table as $row) {
        $key = implode('/', [$row['won'], $row['draw'], $row['lost'], $row['goals'], $row['against']]);
        if (count($byStats[$key] ?? []) !== 1) {
            $missing[] = $row['code'] . ' ' . $key;
            continue;
        }
        $official[] = ['id' => $byStats[$key][0]] + array_diff_key($row, ['code' => 1]);
    }
    if ($missing || count($official) !== count($data['teams'])) {
        printf("%-9s %s: %d von %d Teams zugeordnet, offen: %s\n", $id, $title, count($official), count($data['teams']), implode(', ', $missing));
        continue;
    }
    $data['official'] = [
        'source' => 'Wikipedia, https://en.wikipedia.org/wiki/' . str_replace(' ', '_', $title) . ', abgerufen ' . date('Y-m-d'),
        'table' => $official,
    ];
    file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
    printf("%-9s %s: offizielle Tabelle uebernommen\n", $id, $title);
}
