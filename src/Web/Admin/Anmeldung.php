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

use Lmo\Web\Vorlagen;

/**
 * Die Anmeldung in der Administration (bis 4.5 lmo-adminauth.php, lmo-admincheck_auth.php und
 * lmo-loadauth.php): prueft Name und Kennwort gegen die Konten (config/lmo-auth.php), stellt dabei
 * ein Kennwort, das noch nicht nach dem aktuellen Verfahren gehasht ist, still um (Silent Upgrade,
 * Dietmar Kersting 15.02.2026), und zeigt das Formular. Vorlage admin_anmeldung.html.twig.
 */
final class Anmeldung
{
    /** @param string $kontendatei config/lmo-auth.php */
    public function __construct(private string $kontendatei)
    {
    }

    /**
     * Meldet an.
     *
     * @return array{rang: string, ligen: string, erweitert: string}|null das Konto; null, wenn Name
     *         oder Kennwort nicht stimmen
     */
    public function meldeAn(string $name, string $kennwort): ?array
    {
        if (!$this->pruefe($name, $kennwort)) {
            return null;
        }
        // die Daten frisch lesen, mit dem neuen Hash (wie bis 4.5 lmo-loadauth.php)
        foreach (AdminKonten::lies($this->kontendatei) ?? [] as $konto) {
            if ($name == $konto[0]) {
                return ['rang' => $konto[2] ?? '', 'ligen' => $konto[3] ?? '', 'erweitert' => $konto[4] ?? '0'];
            }
        }

        return null;
    }

    /**
     * Stimmen Name und Kennwort? Ein Kennwort, das noch nicht nach dem aktuellen Verfahren gehasht
     * ist (auch im Klartext), wird dabei umgestellt; die uebrigen Zeilen bleiben, wie sie sind.
     */
    private function pruefe(string $name, string $kennwort): bool
    {
        if (!file_exists($this->kontendatei)) {
            return false;
        }
        $zeilen = file($this->kontendatei, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $verfahren = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
        $geaendert = false;
        $angemeldet = false;
        foreach ($zeilen as $i => $zeile) {
            // die Sperre gegen den Direktaufruf
            if (strpos($zeile, '<?php') !== false) {
                continue;
            }
            $felder = explode('|', $zeile);
            if (count($felder) < 2 || $felder[0] !== $name) {
                continue;
            }
            // gehasht oder im Klartext
            if (password_verify($kennwort, $felder[1]) || $kennwort === $felder[1]) {
                $angemeldet = true;
                if (password_needs_rehash($felder[1], $verfahren) || !password_get_info($felder[1])['algo']) {
                    $felder[1] = password_hash($kennwort, $verfahren);
                    $zeilen[$i] = implode('|', $felder);
                    $geaendert = true;
                }
            }
            break;
        }
        if ($geaendert) {
            file_put_contents($this->kontendatei, implode(PHP_EOL, $zeilen) . PHP_EOL, LOCK_EX);
        }

        return $angemeldet;
    }

    /**
     * Das Formular zur Anmeldung.
     *
     * @param array<int|string, mixed> $text Sprachtexte
     * @param string $selbst Adresse der Seite (PHP_SELF)
     * @param string $name der zuletzt eingegebene Name
     * @param string $sprachwahl die Sprachwahl ('' ohne)
     * @param string $adresse Adresse des LMO (URL_TO_LMO)
     */
    public function formular(array $text, string $selbst, string $name, string $sprachwahl, string $adresse, string $vorlagen): string
    {
        return Vorlagen::zeige($vorlagen, 'admin_anmeldung.html.twig', [
            'text' => $text,
            'selbst' => $selbst,
            'name' => $name,
            'sprachwahl' => $sprachwahl,
            'adresse' => $adresse,
        ]);
    }
}
