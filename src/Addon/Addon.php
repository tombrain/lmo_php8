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

namespace Lmo\Addon;

/**
 * Ein Addon des LMO: ein Ordner addon/<name>/ mit einer Datei addon.php, die ein Objekt dieser
 * Schnittstelle zurueckgibt. Was das Addon beitraegt, sagen die Erweiterungspunkte, die es
 * zusaetzlich implementiert (MitSeiten, MitZusatzJeSpiel, ...).
 *
 * Konfiguration und Sprachtexte liegen wie bisher unter config/<name>/cfg.txt und
 * lang/<name>/lang-<Sprache>.txt (Konfiguration::addon(), Sprachtexte unter dem Namen).
 */
interface Addon
{
    /** Name des Addons, gleich dem Namen seines Ordners (z. B. "spieler") */
    public function name(): string;
}
