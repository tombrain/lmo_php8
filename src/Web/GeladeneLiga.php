<?php

namespace Lmo\Web;

use Lmo\Liga\Rohdaten\Liga;

/** Eine geladene Liga mit ihrer Datei und dem Stand (Zeit der letzten Aenderung der Datei). */
final class GeladeneLiga
{
    /**
     * @param string $datei Ligadatei relativ zum Ligenordner
     * @param string $stand Zeit der letzten Aenderung, im Datumsformat der Installation
     */
    public function __construct(
        public readonly string $datei,
        public readonly Liga $liga,
        public readonly string $stand,
    ) {
    }
}
