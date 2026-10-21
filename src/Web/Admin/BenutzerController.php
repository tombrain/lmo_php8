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

use Lmo\Liga\LigaDatei;
use Lmo\Web\Anfrage;
use Lmo\Web\Controller\Controller;
use Lmo\Web\Kontext;
use Lmo\Web\Meldung;
use Lmo\Web\Seite;
use Lmo\Web\Vorlagen;

/**
 * Die Benutzer der Administration (todo=user, nur der Hauptadmin): links die Konten, rechts das
 * gewaehlte (show, Nummer ab 0) zum Bearbeiten, unten ein neues. save=<Nummer> speichert ein Konto,
 * save=-1 legt eines an, del=<Nummer> loescht eines. Bis 4.5 lmo-adminuser.php.
 */
final class BenutzerController implements Controller
{
    /** @param string $kontendatei config/lmo-auth.php (AdminKonten) */
    public function __construct(private string $kontendatei)
    {
    }

    public function zustaendig(Kontext $kontext): bool
    {
        return $kontext->anfrage->text('todo') == 'user' && $kontext->sitzung->zahl('lmouserok') == 2;
    }

    public function zeige(Kontext $kontext): Seite
    {
        $anfrage = $kontext->anfrage;
        $text = $kontext->text;
        $bilder = $kontext->pfade->bilder;
        $del = $anfrage->zahl('del', -1);
        $save = $anfrage->zahl('save', -2);
        $show = $anfrage->zahl('show', -1);

        $konten = AdminKonten::lies($this->kontendatei);
        if ($konten === null) {
            return new Seite(Meldung::html($bilder, $text[283] ?? '', true));
        }

        $meldung = '';
        $speichern = false;
        if ($save >= 0) {
            if (isset($konten[$save]) && $anfrage->gesetzt('xadmin_name' . $save) && $anfrage->gesetzt('xadmin_pass' . $save) && $anfrage->gesetzt('xadmin_rang' . $save)) {
                $konten[$save] = $this->bearbeitet($kontext, $konten[$save], $save);
            }
            // der Hauptadmin bleibt vorn, die anderen nach Namen
            $hauptadmin = array_slice($konten, 0, 1);
            $andere = array_slice($konten, 1);
            usort($andere, static fn (array $a, array $b): int => strnatcasecmp($a[0], $b[0]));
            $konten = array_merge($hauptadmin, $andere);
            $speichern = true;
        } elseif ($del >= 1) {  // User löschen, nie den Hauptadmin
            unset($konten[$del]);
            $konten = array_values($konten);
            $speichern = true;
        } elseif ($save == -1) {  // Neuen User anlegen
            $name = trim($anfrage->text('xadmin_name'));
            $doppelt = false;
            foreach ($konten as $konto) {
                if ($name == $konto[0]) {
                    $doppelt = true;
                    break;
                }
            }
            if (!$doppelt && $anfrage->hat('xadmin_name') && $anfrage->hat('xadmin_pass')) {
                // Passwort für neuen User sofort sicher hashen
                $konten[] = [$name, self::hash(trim($anfrage->text('xadmin_pass'))), '1', '', ''];
                $speichern = true;
                $show = count($konten) - 1;
            } else {
                $meldung = Meldung::html($bilder, $text[567] ?? '', true);
                $show = -1;
            }
        }
        if ($speichern) {
            $meldung = AdminKonten::schreibe($this->kontendatei, $konten)
                ? Meldung::html($bilder, $text[138] ?? '')
                : Meldung::html($bilder, $text[283] ?? '', true);
            $konten = AdminKonten::lies($this->kontendatei) ?? $konten;
        }

        return new Seite($meldung . Vorlagen::zeige($kontext->pfade->vorlagen, 'admin_benutzer.html.twig', [
            'text' => $text,
            'adresse' => $kontext->adresse,
            'bilder' => $bilder,
            'konten' => $konten,
            'show' => $show,
            'ligen' => isset($konten[$show]) && $konten[$show][2] == 1 ? $this->ligen($kontext, explode(',', $konten[$show][3] ?? '')) : [],
        ]));
    }

    /**
     * Das Konto mit den Feldern des Formulars.
     *
     * @param list<string> $konto
     * @return list<string>
     */
    private function bearbeitet(Kontext $kontext, array $konto, int $nr): array
    {
        $anfrage = $kontext->anfrage;
        $passwort = trim($anfrage->text('xadmin_pass' . $nr));
        $rang = trim($anfrage->text('xadmin_rang' . $nr));
        // Nur neu hashen, wenn das Passwort-Feld geändert wurde und kein Hash ist
        $hash = $passwort !== '' && $passwort !== '0' && $passwort !== $konto[1] ? self::hash($passwort) : $konto[1];
        $konto = [trim($anfrage->text('xadmin_name' . $nr)), $hash, $rang];
        $konto[3] = $rang == 1 ? implode(',', $this->helferLigen($kontext, $anfrage, $nr)) : '';
        // Erweiterter Hilfsadmin
        $konto[4] = $rang == 1 && $anfrage->gesetzt('xadmin_erweitert' . $nr) ? '1' : '0';

        return $konto;
    }

    /**
     * Die angekreuzten Ligen eines Hilfsadmins: Dateinamen ohne Ordner und .l98, nur vorhandene.
     *
     * @return list<string>
     */
    private function helferLigen(Kontext $kontext, Anfrage $anfrage, int $nr): array
    {
        $ordner = $kontext->pfade->lmo . '/' . $kontext->konfiguration->ligenordner();
        $ligen = [];
        foreach ($anfrage->werte('xhelfer_ligen' . $nr) as $liga) {
            if (substr($liga, -4) == '.l98') {
                $liga = substr($liga, 0, -4);  // l98-Endung entfernen
            }
            $liga = substr((string) strrchr('/' . $liga, '/'), 1);
            if (file_exists($ordner . $liga . '.l98')) {  // Datei existiert
                $ligen[] = $liga;
            }
        }

        return $ligen;
    }

    /**
     * Die Ligen im Ligenordner zum Ankreuzen.
     *
     * @param list<string> $freigegeben
     * @return list<array{datei: string, name: string, frei: bool}>
     */
    private function ligen(Kontext $kontext, array $freigegeben): array
    {
        $ordner = $kontext->pfade->lmo . '/' . $kontext->konfiguration->ligenordner();
        $ligen = [];
        foreach (scandir($ordner) ?: [] as $datei) {
            if (substr($datei, -4) == '.l98') {
                $name = substr($datei, 0, -4);
                $ligen[] = [
                    'datei' => $name,
                    // Name der Liga aus dem Abschnitt [Options]
                    'name' => stripslashes(LigaDatei::liesAbschnitt($ordner . $datei, 'Options')['Name'] ?? 'No Name'),
                    'frei' => in_array($name, $freigegeben),
                ];
            }
        }

        return $ligen;
    }

    /** Sicherer Hashing-Algorithmus: Argon2id, wenn vorhanden, sonst bcrypt. */
    private static function hash(string $passwort): string
    {
        return password_hash($passwort, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT);
    }
}
