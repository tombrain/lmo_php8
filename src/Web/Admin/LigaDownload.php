<?php
/** Liga Manager Online 4
  *
  * http://lmo.sourceforge.net/
  *
  * Umgebaut fuer PHP 8 im Rahmen von lmo_php8 (https://github.com/henshingly/lmo_php8).
  *
  * SPDX-License-Identifier: GPL-2.0-or-later
  *
  * REMOVING OR CHANGING THE COPYRIGHT NOTICES IS NOT ALLOWED!
  */

declare(strict_types=1);

namespace Lmo\Web\Admin;

use Closure;
use Lmo\Web\Meldung;
use ZipArchive;

/**
 * Ligen herunterladen (lmo-admindownload.php, aus Administration > Herunterladen): eine Liga als
 * .l98 (down = Nummer in der sortierten Liste ab 1) oder alle als ligen.zip (down = -1). Nur fuer
 * Admins und Hilfsadmins, diese nur mit ihren Ligen.
 */
final class LigaDownload
{
    /**
     * @param string $ligenordner Ordner der Ligen, mit / am Ende
     * @param string $ausgabeordner Ordner fuer das Zip, mit / am Ende
     * @param array<int|string, mixed> $text Sprachtexte
     * @param Closure(string): bool $darf darf die Liga heruntergeladen werden (Hilfsadmins: nur ihre)
     * @param string $bilder Adresse des Bilderordners (URL_TO_IMGDIR)
     */
    public function __construct(
        private string $ligenordner,
        private string $ausgabeordner,
        private array $text,
        private Closure $darf,
        private string $bilder
    ) {
    }

    /**
     * Die Antwort; null, wenn es nichts gibt.
     *
     * @param array<string, mixed> $anfrage die Parameter der Adresse (action, todo, down)
     * @param int $rolle lmouserok: 2 Admin, 1 Hilfsadmin
     * @return array{kopf: list<string>, inhalt: string}|null
     */
    public function antwort(array $anfrage, int $rolle): ?array
    {
        $wert = static fn (string $name): string => isset($anfrage[$name]) && is_string($anfrage[$name]) ? $anfrage[$name] : '';
        if ($wert('action') != 'admin' || $wert('todo') != 'download' || ($rolle != 1 && $rolle != 2)) {
            return null;
        }
        $down = (int) $wert('down');
        $dateien = Ligendateien::in($this->ligenordner);
        if ($down > 0) {
            $datei = $dateien[$down - 1] ?? null;
            if ($datei === null || !($this->darf)($datei)) {
                return null;
            }

            return [
                'kopf' => ['Content-Type: text/x-lmo4', 'Content-Disposition: attachment; filename="' . $datei . '"'],
                'inhalt' => (string) file_get_contents($this->ligenordner . $datei),
            ];
        }
        if ($down != -1) {
            return null;
        }
        if (!class_exists(ZipArchive::class)) {
            // ohne die PHP-Erweiterung zip lassen sich die Ligen nicht packen
            $fehler = $this->text[314] ?? '';

            return [
                'kopf' => ['Content-Type: text/html; charset=utf-8'],
                'inhalt' => Meldung::html($this->bilder, (is_scalar($fehler) ? (string) $fehler : '') . ': PHP extension "zip" is missing.', true),
            ];
        }
        if ($dateien === []) {
            return null;
        }
        // im Ausgabeordner packen: das Programmverzeichnis muss nicht beschreibbar sein
        $zip = $this->ausgabeordner . 'ligen.zip';
        $archiv = new ZipArchive();
        $archiv->open($zip, ZipArchive::CREATE);
        foreach ($dateien as $datei) {
            if (($this->darf)($datei)) {
                $archiv->addFile($this->ligenordner . $datei, $datei);
            }
        }
        $archiv->close();
        // ohne erlaubte Liga legt ZipArchive keine Datei an
        $inhalt = is_file($zip) ? (string) file_get_contents($zip) : '';
        if (is_file($zip)) {
            unlink($zip);
        }

        return [
            'kopf' => ['Content-Type: application/zip', 'Content-Disposition: attachment; filename="ligen.zip"'],
            'inhalt' => $inhalt,
        ];
    }
}
