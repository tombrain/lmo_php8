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
use Lmo\Web\Vorlagen;
use PHPMailer\PHPMailer\PHPMailer;
use ZipArchive;

/**
 * "Liga per E-Mail" (lmo-adminmimesend.php, ein eigenes Fenster aus Administration > Herunterladen):
 * ohne Adresse ein Formular dafuer, mit Adresse geht eine Liga (down = Nummer in der sortierten
 * Liste ab 1) oder gehen alle (down = -1) als Zip-Anhang hinaus. Nur fuer Admins und Hilfsadmins,
 * diese nur mit ihren Ligen. Vorlage admin_ligamail.html.twig.
 */
final class LigaMailSeite
{
    /**
     * @param string $ligenordner Ordner der Ligen, mit / am Ende
     * @param string $ausgabeordner Ordner fuer das Zip, mit / am Ende
     * @param array<int|string, mixed> $text Sprachtexte
     * @param Closure(string): bool $darf darf die Liga verschickt werden (Hilfsadmins: nur ihre)
     * @param Closure(): PHPMailer $mailer eine vorbereitete Mail (lmo_mailer())
     * @param string $adresse Adresse des LMO (URL_TO_LMO)
     * @param string $bilder Adresse des Bilderordners (URL_TO_IMGDIR)
     */
    public function __construct(
        private string $ligenordner,
        private string $ausgabeordner,
        private array $text,
        private Closure $darf,
        private Closure $mailer,
        private string $adresse,
        private string $bilder,
        private string $vorlagen
    ) {
    }

    /**
     * @param array<string, mixed> $anfrage die Parameter der Adresse (action, todo, down, madr)
     * @param int $rolle lmouserok: 2 Admin, 1 Hilfsadmin
     * @param string $selbst Adresse dieser Seite (PHP_SELF) fuer das Formular
     */
    public function html(array $anfrage, int $rolle, string $selbst): string
    {
        $wert = static fn (string $name, string $vorgabe = ''): string => isset($anfrage[$name]) && is_string($anfrage[$name]) ? $anfrage[$name] : $vorgabe;
        $down = $wert('down', '0');
        $madr = $wert('madr');
        $daten = [
            'text' => $this->text,
            'adresse' => $this->adresse,
            'erlaubt' => $wert('action') == 'admin' && $wert('todo') == 'email' && ($rolle == 1 || $rolle == 2),
            'formular' => null,
            'meldung' => '',
            'schliessen' => false,
        ];
        if ($daten['erlaubt']) {
            if ($down != 0 && $madr != '') {
                [$daten['meldung'], $daten['schliessen']] = $this->verschicke((int) $down, $madr);
            } else {
                $daten['formular'] = ['ziel' => $selbst, 'down' => $down];
            }
        }

        return Vorlagen::zeige($this->vorlagen, 'admin_ligamail.html.twig', $daten);
    }

    /**
     * Packt und verschickt.
     *
     * @return array{string, bool} die Meldung und ob der Link zum Schliessen folgt
     */
    private function verschicke(int $down, string $madr): array
    {
        $dateien = Ligendateien::in($this->ligenordner);
        $zip = $this->ausgabeordner . 'ligen.zip';
        $meldung = ['', false];
        if (!class_exists(ZipArchive::class)) {
            // ohne die PHP-Erweiterung zip laesst sich der Anhang nicht packen
            $meldung = [Meldung::html($this->bilder, self::sprachtext($this->text, 550) . ' Details: PHP extension "zip" is missing.', true), false];
        } elseif ($down > 0) {
            $datei = $dateien[$down - 1] ?? '';
            if ($datei != '' && ($this->darf)($datei)) {
                $this->packe($zip, [$datei]);
                $meldung = [$this->sende($madr, self::sprachtext($this->text, 342), $zip, $datei . '.zip'), true];
            }
        } elseif ($down == -1 && $dateien !== []) {
            $this->packe($zip, array_values(array_filter($dateien, $this->darf)));
            $meldung = [$this->sende($madr, self::sprachtext($this->text, 330), $zip, ''), true];
        }
        if (file_exists($zip)) {
            unlink($zip);
        }

        return $meldung;
    }

    /** @param list<string> $dateien */
    private function packe(string $zip, array $dateien): void
    {
        $archiv = new ZipArchive();
        $archiv->open($zip, ZipArchive::CREATE);
        foreach ($dateien as $datei) {
            $archiv->addFile($this->ligenordner . $datei, $datei);
        }
        $archiv->close();
    }

    /** Die Mail mit dem Zip; zurueck kommt die Meldung. */
    private function sende(string $madr, string $text, string $zip, string $anhang): string
    {
        $mail = ($this->mailer)();
        $mail->addAddress($madr);
        $mail->Subject = self::sprachtext($this->text, 341);
        $mail->Body = $text;
        $mail->addAttachment($zip, $anhang);

        return $mail->send()
            ? Meldung::html($this->bilder, self::sprachtext($this->text, 348))
            : Meldung::html($this->bilder, self::sprachtext($this->text, 550) . ' Details: ' . $mail->ErrorInfo, true);
    }

    /**
     * Ein Sprachtext; '' ohne.
     *
     * @param array<int|string, mixed> $text
     */
    private static function sprachtext(array $text, int $nr): string
    {
        $wert = $text[$nr] ?? '';

        return is_scalar($wert) ? (string) $wert : '';
    }
}
