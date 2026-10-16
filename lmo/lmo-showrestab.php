<?php
/** Liga Manager Online 4
  *
  * http://lmo.sourceforge.net/
  *
  * This program is free software; you can redistribute it and/or
  * modify it under the terms of the GNU General Public License as
  * published by the Free Software Foundation; either version 2 of
  * the License, or (at your option) any later version.
  *
  * This program is distributed in the hope that it will be useful,
  * but WITHOUT ANY WARRANTY; without even the implied warranty of
  * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
  * General Public License for more details.
  *
  * REMOVING OR CHANGING THE COPYRIGHT NOTICES IS NOT ALLOWED!
  *
  */
if (!defined('PATH_TO_LMO')) {
    exit;  // kein Direktaufruf: diese Datei wird nur ueber LMO eingebunden
}

/**
 * Diese Datei wird eingebunden und erwartet:
 *
 * @var \Lmo\Liga\Rohdaten\Liga $lmo_liga Rohdaten der geladenen Liga (lmo-openfile.php)
 * @var array<int|string, mixed> $text Sprachtexte
 * @var int|string $st gewaehlter Spieltag
 * @var string $action gewaehlte Seite (Aufrufparameter)
 * @var array<int|string, mixed> $array leeres Array als Vorlage fuer array_pad()
 *
 * Einstellungen:
 * @var int|string $tabonres Einstellung aus cfg.txt
 *
 * Weitere Variablen des einbindenden Codes:
 * @var mixed $atore
 * @var mixed $dtore
 * @var mixed $etore
 * @var mixed $negativ
 * @var mixed $nieder
 * @var mixed $punkte
 * @var mixed $siege
 * @var mixed $spiele
 * @var array<int|string, mixed> $tab0
 * @var mixed $tabdat
 * @var mixed $unent
 *
 * Werden auch in dieser Datei gesetzt, aber nicht auf jedem Weg (koennen von aussen kommen):
 * @var mixed $endtab
 */


if (isset($file) && $file != "") {
  $tabtype=isset($_GET['tabtype'])?$_GET['tabtype']:0;
  if (!empty($_GET['st'])) { $endtab = $st;}
  // Ursprünglichen Wert sichern: die Tabellenberechnung konnte $endtab bei Hin-/Rückrunden-Tabellen (tabtype 3/4) intern ueberschreiben, daher
  // reicht ein einfaches $endtab--/$endtab++ nicht aus, um ihn nach dem require() wieder zuverlaessig herzustellen.
  $endtab_original = $endtab;
  $addp = $_SERVER['PHP_SELF']."?action=program&amp;file=".$file."&amp;selteam=";
  $addr = $_SERVER['PHP_SELF']."?action=$action&amp;tabtype=$tabtype&amp;file=".$file."&amp;st=";
  // Einstellungen der Liga mit Vorgabewerten (Lmo\Liga\Einstellungen)
  $lmo_einstellungen = $lmo_liga->einstellungen();
  $endtab = $endtab_original;
  if ($tabdat == "") {
    $addt1 = $_SERVER['PHP_SELF']."?action=$action&amp;file=".$file."&amp;endtab=".$endtab."&amp;st=".$st."&amp;tabtype=";
  } else {
    $addt1 = $_SERVER['PHP_SELF']."?action=$action&amp;file=".$file."&amp;endtab=".$endtab."&amp;st=".$st."&amp;tabtype=";
  }
  $addt2 = $_SERVER['PHP_SELF']."?action=$action&amp;file=".$file."&amp;tabtype=".$tabtype."&amp;st=".$st."&amp;endtab=";

?>

<table class="lmoMiddle" cellspacing="0" cellpadding="0" border="0"><?php

  /** Spieltagsauswahl*/?>
  <tr>
    <td align="center"><?php  include(PATH_TO_LMO."/lmo-spieltagsmenu.php")?></td>
  </tr><?php

  /** Ergebnisse*/
  if ($tabonres >= 1 || $action=="results") {?>
  <tr>
    <td align="center"><?php  include(PATH_TO_LMO."/lmo-showresults.php")?></td>
  </tr><?php
  }

  /** Vor und Zurück-Pfeile*/?>
  <tr>
    <td>
      <table width="100%" cellspacing="0" cellpadding="0" border="0">
        <tr><?php
   $st0 = $st-1;
   if ($st > 1) {?>
          <td align="left">&nbsp;<a href="<?php echo $addr.$st0?>" title="<?php echo $text[6]?>"><?php echo $text[5]?> <?php echo $text[6]?></a>&nbsp;</td><?php
   }
   $st0 = $st+1;
   if ($st < $lmo_einstellungen->spieltage()) {?>
          <td align="right">&nbsp;<a href="<?php echo $addr.$st0?>" title="<?php echo $text[8]?>"><?php echo $text[8]?> <?php echo $text[7]?></a>&nbsp;</td><?php
   }?>
        </tr>
      </table>
    </td>
  </tr><?php

  /** Tabelle*/
  if ($tabonres >= 1 || $action=="table") {?>
  <tr>
    <td align="center"><?php  include(PATH_TO_LMO."/lmo-showtable.php")?></td>
  </tr><?php
  }?>

</table><?php
}?>