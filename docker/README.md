# Docker für Entwickler

`docker-compose.yml` im Projektordner stellt drei Dienste bereit. Alle Befehle im Projektordner ausführen.

| Dienst | Zweck | Zugriff |
|---|---|---|
| `web` | LMO im Browser ausprobieren | http://localhost:8082/lmo/ |
| `web` | Addons mit Beispielligen ausprobieren | http://localhost:8082/demo/ |
| `mail` | Postfach für alle Mails, die LMO verschickt | http://localhost:8025/ |
| `tests` | automatische Tests (PHPUnit) | nur Kommandozeile, siehe [TESTING.md](../TESTING.md) |

## Starten und stoppen

```bash
docker compose up -d web          # startet web und mail
docker compose stop               # anhalten, Daten bleiben
docker compose down               # Container entfernen, Daten bleiben
docker compose down -v            # alles entfernen, auch Konfiguration, Ligen und Tipps
docker compose up -d --build web  # nach Änderungen an docker/web.Dockerfile
```

## LMO im Browser (`web`)

- Ligaseite: http://localhost:8082/lmo/
- Admin-Bereich: http://localhost:8082/lmo/lmoadmin.php  
  Beim ersten Aufruf legt LMO die Konfiguration an und fragt nach einem Admin-Konto.
- Der Ordner `lmo/` ist live eingebunden: Änderungen am Code wirken sofort, ohne Neustart.
- PHP-Fehler und -Warnungen werden im Browser angezeigt.

### Wo die Daten liegen

Konfiguration, Ligen und alles, was LMO zur Laufzeit schreibt, liegen in Docker-Volumes und nicht im
Projektordner. So landet nichts davon versehentlich im Git.

| Ordner im Container | Volume | Inhalt |
|---|---|---|
| `lmo/config` | `web-config` | Konfiguration, Admin-Konto |
| `lmo/ligen` | `web-ligen` | Ligadateien (`.l98`) |
| `lmo/output` | `web-output` | erzeugte Dateien, Caches |
| `lmo/addon/tipp/tipps` | `web-tipps` | Tippspiel |
| `lmo/addon/spieler/stats` | `web-stats` | Spielerstatistik |

Beim ersten Start werden die Volumes mit dem Stand aus dem Projektordner gefüllt (Beispielligen).
Später im Projektordner hinzugefügte Ligen erscheinen nicht von selbst im Container.

```bash
# Datei in den Container kopieren, z.B. eine Liga
docker compose cp meine-liga.l98 web:/var/www/html/lmo/ligen/
docker compose exec web chown www-data:www-data /var/www/html/lmo/ligen/meine-liga.l98

# Datei aus dem Container holen
docker compose cp web:/var/www/html/lmo/ligen/meine-liga.l98 .

# Shell im Container
docker compose exec web bash

# Ausgaben des Webservers
docker compose logs -f web
```

## Addons ausprobieren (`/demo/`)

http://localhost:8082/demo/ zeigt alle Addons auf einer Seite, jeweils eingebettet und mit dem
direkten Link: Minitabelle, Mini-Spielplan, Viewer (nach Spieltag und nach Datum), Ticker und
Spielerstatistik. Oben lässt sich zwischen zwei Beispielligen umschalten.

Die Seite und ihre Daten liegen in `docker/demo/` und gehören nicht zu LMO; sie werden nicht mit
ausgeliefert. Beim Start des Containers werden fehlende Dateien in die Volumes kopiert, vorhandene
bleiben unangetastet:

| Datei in `docker/demo/` | Ziel im Container | Inhalt |
|---|---|---|
| `ligen/demo-laufend.l98` | `lmo/ligen/` | 1. Bundesliga 2026/27, Stand 5. Spieltag |
| `ligen/demo-beendet.l98` | `lmo/ligen/` | 1. Bundesliga 2025/26, komplett |
| `viewer/demo-*.view` | `lmo/config/viewer/` | zwei Viewer-Ansichten über beide Ligen |
| `stats/demo-laufend.stat` | `lmo/addon/spieler/stats/` | erfundene Spielerstatistik |

Die Ergebnisse der Beispielligen stammen von OpenLigaDB. Die Termine sind erfunden: ein Spieltag pro
Woche, alle Spiele samstags 15:30 Uhr. Wer eine Beispieldatei im Container verändert hat und den
Ausgangsstand zurückhaben will, löscht sie dort und startet den Container neu.

## Mails (`mail`)

LMO verschickt im Container keine echten Mails. Alles, was über PHPs `mail()` gesendet wird (z.B.
„Liga per E-Mail“ im Admin-Bereich), landet im Postfach unter http://localhost:8025/ – egal an
welche Adresse. Das Postfach ist nach einem Neustart des Dienstes leer.

## Einstellungen

Als Umgebungsvariable vor dem Befehl oder in einer Datei `.env` im Projektordner:

| Variable | Standard | Bedeutung |
|---|---|---|
| `WEB_PORT` | `8082` | Port von LMO im Browser |
| `MAIL_PORT` | `8025` | Port des Postfachs |
| `PHP_VERSION` | `8.3` | PHP-Version der Images, danach mit `--build` neu bauen |

```bash
WEB_PORT=9000 docker compose up -d web
```

## Tests (`tests`)

```bash
docker compose run --rm tests                          # alle Tests
docker compose run --rm tests --testsuite unit         # nur eine Suite: unit, real-data, golden-master
docker compose run --rm tests --filter LigaTableTest   # nur eine Testklasse
```

Die Tests laufen auf eigenen Kopien von LMO und berühren die Daten des Dienstes `web` nicht.
Einzelheiten stehen in [TESTING.md](../TESTING.md).
