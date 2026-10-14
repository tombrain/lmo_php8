<?php
if (!defined('PATH_TO_LMO')) {
    exit;  // kein Direktaufruf: diese Datei wird nur ueber LMO eingebunden
}

/**
 * Diese Datei wird eingebunden und erwartet:
 *
 * @var \Lmo\Liga\Rohdaten\Liga|null $lmo_liga Rohdaten der geladenen Liga; fehlt, wenn keine geladen ist
 * @var array<int|string, mixed> $text Sprachtexte
 * @var int|string $st gewaehlter Spieltag
 *
 * @var string $tabdat Stand der Tabelle als Text; nicht leer: der gewaehlte Spieltag ist nicht verlinkt
 * @var string $todo "tabs" im Adminbereich: auch der gewaehlte Spieltag ist verlinkt (mit $addb)
 * @var string $addb Link auf einen Spieltag im Adminbereich, es fehlt nur die Nummer
 * @var string $addr Link auf einen Spieltag, es fehlt nur die Nummer
 */

// die Auswahl der Spieltage (Lmo\Web\Seiten\SpieltagsMenue mit der Vorlage vorlagen/spieltagsmenu.html.twig)
$lmo_menu_tabs = isset($todo) && $todo == 'tabs';
echo (new \Lmo\Web\Seiten\SpieltagsMenue($text, PATH_TO_LMO . '/vorlagen'))->zeige(
    $lmo_liga ?? null,
    (int)$st,
    $lmo_menu_tabs ? $addb : $addr,
    $lmo_menu_tabs && empty($tabdat)
);
