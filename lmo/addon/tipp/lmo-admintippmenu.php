<?php
if (!defined('PATH_TO_LMO')) {
    exit;  // kein Direktaufruf: diese Datei wird nur ueber LMO eingebunden
}
// die Seiten der Administration des Tippspiels (bis 4.5 setzte die Adressen lmo-adminmain.php)
$tipp_addr_auswertung = $_SERVER['PHP_SELF'] . '?action=admin&amp;todo=tipp';
$tipp_addr_email = $_SERVER['PHP_SELF'] . '?action=admin&amp;todo=tippemail';
$tipp_addr_user = $_SERVER['PHP_SELF'] . '?action=admin&amp;todo=tippuser';
$tipp_addr_optionen = $_SERVER['PHP_SELF'] . '?action=admin&amp;todo=tippoptions';
?><table class="lmoSubmenu" width="99%" cellspacing="0" cellpadding="0" border="0">
  <tr>
    <td align="center"><?php 
if ($todo!='tipp') {?>
      <a href='<?php echo $tipp_addr_auswertung?>' onclick="return chklmolink();" title="<?php echo $text['tipp'][63]?>"><?php echo $text['tipp'][63]?></a><?php 
} else {
  echo $text['tipp'][63];
}?> </td>
    <td align="center"><?php 
if ($_SESSION['lmouserok']==2){
  if ($todo!='tippemail') {?>
      <a href='<?php echo $tipp_addr_email?>' onClick="return chklmolink();" title="<?php echo $text['tipp'][165]?>"><?php echo $text['tipp'][165]?></a><?php 
  } else {
    echo $text['tipp'][165];
  }?> 
    </td>
    <td align="center"><?php 
  if ($todo!='tippuser' && $todo!='tippuseredit') {?>
      <a href='<?php echo $tipp_addr_user?>' onClick="return chklmolink();" title="<?php echo $text['tipp'][114]?>"><?php echo $text['tipp'][114]?></a><?php 
  } else {
    echo $text['tipp'][114];
  }?> 
    </td>
    <td align="center"><?php 
  if ($todo!='tippoptions') {?>
      <a href='<?php echo $tipp_addr_optionen?>' onClick="return chklmolink();" title="<?php echo $text['tipp'][55]?>"><?php echo $text[86]?></a><?php 
  } else {
    echo $text[86];
  }?> 
    </td><?php 
}?>
  </tr>
</table>