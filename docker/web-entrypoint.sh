#!/bin/sh
# Die Datenordner des Web-Containers liegen in Docker-Volumes (siehe docker-compose.yml),
# damit Konfiguration, Ligen und Tipps nicht im Arbeitsverzeichnis landen.
# Beim ersten Start werden sie mit dem Stand aus dem Repo befuellt (Beispielligen,
# .htaccess-Schutz, leere Ordner); Laufzeitdateien aus dem Repo-Ordner werden nicht uebernommen.
set -e
src=/srv/lmo-src
app=/var/www/html/lmo
for dir in config ligen output addon/tipp/tipps addon/spieler/stats; do
    if [ -z "$(ls -A "$app/$dir" 2>/dev/null)" ] && [ -d "$src/$dir" ]; then
        cp -a "$src/$dir/." "$app/$dir/"
        find "$app/$dir" \( -name cfg.txt -o -name lmo-auth.php -o -name init-parameters.php -o -name .defaults-stamp \) -delete
    fi
    chown -R www-data:www-data "$app/$dir"
done
exec docker-php-entrypoint "$@"
