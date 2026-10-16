<?php

namespace Lmo\Web;

/** Ordner und Adressen der Installation, die die Seiten brauchen. */
final class Pfade
{
    /**
     * @param string $lmo Ordner des LMO (PATH_TO_LMO)
     * @param string $vorlagen Ordner der Twig-Vorlagen
     * @param string $bilder Adresse des Bilderordners (URL_TO_IMGDIR)
     */
    public function __construct(
        public readonly string $lmo,
        public readonly string $vorlagen,
        public readonly string $bilder,
    ) {
    }

    /** Pfad einer Datei im Ordner fuer erzeugte Dateien (Konfiguration diroutput), z. B. die Statistikdatei. */
    public function ausgabe(Konfiguration $konfiguration, string $datei): string
    {
        return $this->lmo . '/' . basename($konfiguration->ausgabeordner()) . '/' . $datei;
    }
}
