<?php

declare(strict_types=1);

namespace Lmo\Web;

use Lmo\Addon\Addons;

/**
 * Alles, was ein Controller fuer seine Seite bekommt: die Anfrage, die gewaehlte Seite (action),
 * die geladene Liga, die Umgebung der Installation und ihre Addons. Ersetzt die globalen Variablen.
 */
final class Kontext
{
    /**
     * @param string $action gewaehlte Seite, z. B. "results"
     * @param GeladeneLiga|null $liga die geladene Liga, null ohne
     * @param array<int, string> $text Sprachtexte (fuer die Liga angepasst, Sprachtexte::fuerLiga())
     * @param string $adresse Adresse des LMO fuer Links ($_SERVER['PHP_SELF'])
     * @param array<string, array<int, string>> $weitereTexte Texte der Unterordner von lang/ (texteVon())
     */
    public function __construct(
        public readonly Anfrage $anfrage,
        public readonly string $action,
        public readonly ?GeladeneLiga $liga,
        public readonly Konfiguration $konfiguration,
        public readonly array $text,
        public readonly string $adresse,
        public readonly Pfade $pfade,
        public readonly Darstellung $darstellung,
        public readonly Addons $addons = new Addons(),
        public readonly array $weitereTexte = [],
    ) {
    }

    /**
     * Die Texte eines Unterordners von lang/: "date" (Wochentage und Monate) oder die eines
     * Addons ($kontext->texteVon($this->name())); [] ohne.
     *
     * @return array<int, string>
     */
    public function texteVon(string $name): array
    {
        return $this->weitereTexte[$name] ?? [];
    }
}
