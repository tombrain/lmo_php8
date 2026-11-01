<#
Spiegelt vendor/ aus dem Docker-Volume ins vendor/ des Hosts, damit die IDE die Pakete kennt.

Verwendung (im Projektordner):
  .\docker\sync_vendor.ps1           # nur PHP-Dateien spiegeln
  .\docker\sync_vendor.ps1 -Force    # alle Dateien spiegeln

Im Container liegt vendor/ im Docker-Volume 'lmo_php8_vendor' (siehe docker-compose.yml), nicht
im eingebundenen Projektordner; das vendor/ auf dem Host ist nur eine Kopie fuer die IDE.
Composer spiegelt nach jedem Autoload-Dump selbst (composer.json, post-autoload-dump). Von Hand
braucht man dieses Skript nur fuer -Force oder nach einem Lauf ohne Composer.
#>
Param(
    [switch]$Force
)

$forceArg = @()
if ($Force) { $forceArg = @('--force') }

docker compose run --rm --entrypoint php tests docker/sync_vendor.php @forceArg

exit $LASTEXITCODE
