<?php

namespace Lmo\Web;

/**
 * Alles, was ein Controller fuer seine Seite bekommt: die Anfrage, die gewaehlte Seite (action),
 * die geladene Liga und die Umgebung der Installation. Ersetzt die globalen Variablen.
 */
final class Kontext
{
    /**
     * @param string $action gewaehlte Seite, z. B. "results"
     * @param GeladeneLiga|null $liga die geladene Liga, null ohne
     * @param array<int|string, mixed> $text Sprachtexte (fuer die Liga angepasst, Sprachtexte::fuerLiga())
     * @param string $adresse Adresse des LMO fuer Links ($_SERVER['PHP_SELF'])
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
    ) {
    }
}
