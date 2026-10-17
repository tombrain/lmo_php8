<?php

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
