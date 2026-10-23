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
 * Alles Weitere liegt ebenfalls im Ordner des Addons: die Sprachtexte in
 * addon/<name>/lang/lang-<Sprache>.txt (Sprachtexte unter dem Namen), die Vorgabe der
 * Konfiguration in addon/<name>/config-default/cfg.txt. Die Konfiguration der Installation steht
 * in config/<name>/cfg.txt (Konfiguration::addon()), damit ein Update des Addons sie nicht ersetzt.
 */
interface Addon
{
    /** Name des Addons, gleich dem Namen seines Ordners (z. B. "spieler") */
    public function name(): string;
}
