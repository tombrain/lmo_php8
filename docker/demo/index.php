<?php
// Uebersichtsseite der Docker-Entwicklungsumgebung: alle Addons mit den Beispielligen zum Ausprobieren.
// Liegt ausserhalb von lmo/ und wird nicht mit LMO ausgeliefert (siehe docker/README.md).
$ligen = [
    'demo-laufend.l98' => '1. Bundesliga 2026/27 (laufend, 5. Spieltag)',
    'demo-beendet.l98' => '1. Bundesliga 2025/26 (beendet)',
    'demo-aelter.l98' => '1. Bundesliga 2024/25 (beendet)',
];
$liga = isset($_GET['liga'], $ligen[$_GET['liga']]) ? $_GET['liga'] : 'demo-laufend.l98';
$q = rawurlencode($liga);
$alle = implode(',', array_keys($ligen));
$lmo = '/lmo';
// Abschnitt => Kacheln [Titel, Beschreibung, Adresse, Hoehe]
$abschnitte = [
    'Eine Liga' => [
        ['Minitabelle', 'Tabellenausschnitt zum Einbinden in eine eigene Seite. Rechnet mit der classlib.',
            "$lmo/addon/mini/lmo-minitab.php?mini_liga=$q&mini_template=standard&mini_platz=5&mini_ueber=4&mini_unter=4", 330],
        ['Spielerstatistik', 'Öffentliche Seite. Spieler und Spalten pflegt man im Admin-Bereich der Liga. Beispieldaten gibt es nur für die laufende Liga.',
            "$lmo/lmo.php?file=$q&action=spieler", 330],
    ],
    'Eine Liga und mehrere Ligen im Vergleich' => [
        ['Ticker: eine Liga', 'Lauftext auf der Ligaseite, eingeschaltet über „ticker=1“ in der Ligadatei.',
            "$lmo/lmo.php?file=$q&action=results", 420],
        ['Ticker: drei Ligen', 'Direkt aufgerufen mit einer kommagetrennten Liste von Ligen (tickerligen). Jede Liga bringt die Ergebnisse ihres aktuellen Spieltags mit.',
            "$lmo/addon/ticker/ticker.php?tickerligen=$alle", 420],
        ['Viewer: eine Liga', 'Ansicht „demo-1liga“: Spiele nach Spieltag. Ansichten bearbeitet man im Admin-Bereich unter den Viewer-Optionen.',
            "$lmo/addon/viewer/viewer.php?multi=demo-1liga", 420],
        ['Viewer: drei Ligen', 'Ansicht „demo-3ligen“: dieselbe Darstellung über alle drei Beispielligen.',
            "$lmo/addon/viewer/viewer.php?multi=demo-3ligen", 420],
        ['Mini-Spielplan: eine Liga', 'Nächstes Spiel Leverkusen gegen Dortmund und die bisherigen Begegnungen, nur aus der laufenden Liga (mini_withArchiv=0).',
            "$lmo/addon/mini/lmo-mininext.php?file=demo-laufend.l98&a=1&b=2&mini_withArchiv=0", 300],
        ['Mini-Spielplan: drei Ligen', 'Dieselbe Paarung, die bisherigen Begegnungen zusätzlich aus den zwei beendeten Saisons im Archivordner (folder=demo-archiv).',
            "$lmo/addon/mini/lmo-mininext.php?file=demo-laufend.l98&a=1&b=2&folder=demo-archiv", 300],
    ],
    'Nach Datum' => [
        ['Viewer nach Datum: drei Ligen', 'Ansicht „demo-3ligen-datum“: Spiele 14 Tage vor und nach heute. Die Termine der Beispielligen sind fest, die Liste wird also mit der Zeit leer.',
            "$lmo/addon/viewer/viewer.php?multi=demo-3ligen-datum", 420],
    ],
];
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>LMO Addons ausprobieren</title>
<style>
  body { font: 15px/1.5 system-ui, sans-serif; margin: 0; background: #f4f5f7; color: #1d2330; }
  header { background: #1d2330; color: #fff; padding: 16px 24px; }
  header h1 { margin: 0 0 4px; font-size: 20px; }
  header a { color: #9ecbff; }
  nav { padding: 12px 24px; background: #fff; border-bottom: 1px solid #d9dce3; }
  nav a { margin-right: 16px; }
  nav a.aktiv { font-weight: 600; color: #1d2330; text-decoration: none; }
  h2.abschnitt { margin: 20px 24px 0; font-size: 17px; }
  main { padding: 12px 24px 8px; display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(420px, 1fr)); }
  section { background: #fff; border: 1px solid #d9dce3; border-radius: 6px; padding: 12px 16px 16px; min-width: 0; }
  section h2 { margin: 0; font-size: 16px; }
  section p { margin: 4px 0 8px; color: #55607a; font-size: 13px; }
  iframe { width: 100%; border: 1px solid #d9dce3; background: #fff; }
  .link { font-size: 13px; word-break: break-all; }
  @media (max-width: 480px) { main { grid-template-columns: 1fr; padding: 16px; } }
</style>
</head>
<body>
<header>
  <h1>LMO Addons ausprobieren</h1>
  Entwicklungsumgebung:
  <a href="<?= $lmo ?>/">Ligaseite</a> ·
  <a href="<?= $lmo ?>/lmoadmin.php">Admin-Bereich</a> ·
  <a href="<?= $lmo ?>/lmo.php?action=tipp">Tippspiel</a> ·
  <a href="http://localhost:<?= htmlspecialchars(getenv('MAIL_PORT') ?: '8025') ?>/">Postfach</a>
</header>
<nav>
  Beispielliga für die Kacheln mit einer Liga:
  <?php foreach ($ligen as $datei => $name): ?>
    <a href="?liga=<?= rawurlencode($datei) ?>"<?= $datei === $liga ? ' class="aktiv"' : '' ?>><?= htmlspecialchars($name) ?></a>
  <?php endforeach; ?>
</nav>
<?php foreach ($abschnitte as $abschnitt => $kacheln): ?>
<h2 class="abschnitt"><?= htmlspecialchars($abschnitt) ?></h2>
<main>
<?php foreach ($kacheln as [$titel, $beschreibung, $url, $hoehe]): ?>
  <section>
    <h2><?= htmlspecialchars($titel) ?></h2>
    <p><?= htmlspecialchars($beschreibung) ?></p>
    <iframe src="<?= htmlspecialchars($url) ?>" height="<?= $hoehe ?>" title="<?= htmlspecialchars($titel) ?>"></iframe>
    <div class="link"><a href="<?= htmlspecialchars($url) ?>" target="_blank"><?= htmlspecialchars($url) ?></a></div>
  </section>
<?php endforeach; ?>
</main>
<?php endforeach; ?>
</body>
</html>
