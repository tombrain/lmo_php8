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

namespace Lmo\Web;

/**
 * Was init.php beim Start liest (bis 4.5 lmo-cfgload.php, lmo-updateoptions.php und
 * lmo-langload.php): die Konfiguration, die Zeitzone, die Sprachen. init.php legt daraus die
 * globalen Variablen an, die alter Code, die Classlib und eingebundene Seiten erwarten.
 */
final class Start
{
    /** Die Sprachen des LMO und ihre Locales fuer setlocale() */
    public const SPRACHEN = [
        'Bosanski'    => ['bs_BA', 'bos'],
        'Cestina'     => ['cs_CS'],
        'Dansk'       => ['da_DK', 'dan'],
        'Deutsch'     => ['de_DE@euro', 'de_DE', 'de', 'ge', 'german', 'de_DE.UTF-8'],
        'English'     => ['en_EN', 'en_GB.UTF-8', 'en_US'],
        'Espanol'     => ['es_ES', 'es_ES.UTF-8', 'es_ES@euro'],
        'Francais'    => ['fr_FR'],
        'Hrvatski'    => ['hr_HR', 'hrv'],
        'Italiano'    => ['it_IT'],
        'Magyar'      => ['hu_HU'],
        'Nederlands'  => ['nl_NL'],
        'Norsk'       => ['no_NO'],
        'Polski'      => ['pl_PL', 'pol'],
        'Portugues'   => ['pt_BR'],
        'Romanian'    => ['ro_RO'],
        'Slovenskega' => ['sl_SL'],
        'Suomalainen' => ['fi_FI'],
        'Svenska'     => ['sv_SE'],
        'Türkce'      => ['tr_TR'],
        'Українська'  => ['uk_UA', 'ukr'],
    ];

    /**
     * Die Hauptkonfiguration (config/cfg.txt); null, wenn es sie nicht gibt.
     *
     * @return array<string, mixed>|null
     */
    public static function hauptkonfiguration(string $datei): ?array
    {
        $werte = file_exists($datei) ? parse_ini_file($datei) : false;

        return $werte === false ? null : self::mitNamen($werte);
    }

    /**
     * Die Werte unter ihrem Namen (wie parse_ini_file() sie liefert; nur fuer die Typen).
     *
     * @param array<mixed> $werte
     * @return array<string, mixed>
     */
    private static function mitNamen(array $werte): array
    {
        $mitNamen = [];
        foreach ($werte as $name => $wert) {
            $mitNamen[(string) $name] = $wert;
        }

        return $mitNamen;
    }

    /**
     * Die Konfigurationen der Addons (config/<addon>/cfg.txt), in fester (alphabetischer) Reihenfolge.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function addonKonfigurationen(string $ordner): array
    {
        $addons = [];
        foreach (scandir($ordner) ?: [] as $name) {
            $datei = $ordner . '/' . $name . '/cfg.txt';
            if ($name != '.' && $name != '..' && is_dir($ordner . '/' . $name) && file_exists($datei)) {
                $werte = parse_ini_file($datei, false, INI_SCANNER_RAW);
                $addons[$name] = $werte === false ? [] : self::mitNamen($werte);
            }
        }

        return $addons;
    }

    /** Die Zeitzone: die eingestellte, sonst die des Servers */
    public static function zeitzone(mixed $eingestellt): string
    {
        if (!empty($eingestellt) && is_string($eingestellt)) {
            return $eingestellt;
        }
        $zeitzone = date_default_timezone_get();
        if ($zeitzone == 'System/Localtime') {
            // kein Name der Zeitzone verfuegbar: aus der Abkuerzung der Ortszeit
            $zeitzone = (string) timezone_name_from_abbr(date('T'));
        }

        return $zeitzone;
    }

    /**
     * Die Standardsprache und die des Besuchers, nur bekannte: eine unbekannte Standardsprache wird
     * Deutsch, eine unbekannte des Besuchers die Standardsprache. Beide kommen auch aus der Anfrage
     * und werden in Dateinamen verwendet.
     *
     * @return array{string, string|null, bool} Standardsprache, Sprache des Besuchers (null ohne)
     *         und ob die des Besuchers ersetzt wurde
     */
    public static function sprachen(mixed $standard, mixed $besucher): array
    {
        $standard = is_string($standard) && isset(self::SPRACHEN[$standard]) ? $standard : 'Deutsch';
        if ($besucher === null) {
            return [$standard, null, false];
        }
        if (!is_string($besucher) || !isset(self::SPRACHEN[$besucher])) {
            return [$standard, $standard, true];
        }

        return [$standard, $besucher, false];
    }
}
