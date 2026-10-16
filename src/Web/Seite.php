<?php

namespace Lmo\Web;

/** Was ein Controller liefert: das HTML des Hauptteils und ob es dazu eine Druckansicht gibt. */
final class Seite
{
    /**
     * @param string $html Hauptteil der Seite
     * @param bool $druckbar Links zur Druckansicht (lmo-savehtml.php) unter der Seite zeigen
     */
    public function __construct(
        public readonly string $html,
        public readonly bool $druckbar = false,
    ) {
    }
}
