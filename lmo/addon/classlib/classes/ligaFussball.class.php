<?php
/**
 *
 * Bildet eine Fussball Liga als Objekt ab
 *
 * @package   classLib
 * @since classlib V2.7
 * @version  liga.class.php,v 2.7 2005/10/12 13:34:43
 */
class ligaFussball extends liga {

    /**
    * Sortiert die errechnete Tabelle und gibt diese als Array zurück
    *   - Pkt - (Anzahl Spiele) - Differenz Tore - geschossene Tore -
    *     - das Gesamtergebnis aus Hin- und Rückspiel im direkten Vergleich
    *     - die Anzahl der auswärts erzielten Tore im direkten Vergleich
    *     - die Anzahl aller auswärts erzielten Tore
    *
    * @access protected
    * @param  array $tableArray Die zu sortierende Tabelle
    * @return array
    */
    function sortTable($tableArray) {
        if (empty($tableArray)) {
            return $tableArray;
        }
        $sort_pPkt = $sort_spiele = $sort_dTor = $sort_pTor = array();
        foreach($tableArray as $table) {
            $sort_pPkt[] = $table['pPkt'];
            $sort_spiele[] = $table['spiele'];
            $sort_dTor[] = $table['dTor'];
            $sort_pTor[] = $table['pTor'];
        }
        // ASC = auf-, DESC = absteigend
        array_multisort($sort_pPkt, SORT_DESC, $sort_spiele, SORT_ASC, $sort_dTor, SORT_DESC, $sort_pTor, SORT_DESC, $tableArray, SORT_DESC);
        $count = count($tableArray);
        for ($i = 0; $i + 1 < $count; $i++) {
            $a = $tableArray[$i]['team'];
            $b = $tableArray[$i + 1]['team'];
            if ($sort_pPkt[$i] != $sort_pPkt[$i + 1] || $sort_spiele[$i] != $sort_spiele[$i + 1] || $sort_dTor[$i] != $sort_dTor[$i + 1] || $sort_pTor[$i] != $sort_pTor[$i + 1]) {
                continue;
            }
            // Partien für den Direkten Vergleich
            $tore = array($a->nr => 0, $b->nr => 0);
            $gastTore = array($a->nr => 0, $b->nr => 0);
            foreach ((array) $this->allPartieForTeams($a, $b, true) as $partie) {
                $tore[$partie->heim->nr] += max(0, (int) $partie->hTore);
                $tore[$partie->gast->nr] += max(0, (int) $partie->gTore);
                $gastTore[$partie->gast->nr] += max(0, (int) $partie->gTore);
            }
            // das Gesamtergebnis aus Hin- und Rückspiel im direkten Vergleich
            if ($tore[$a->nr] == $tore[$b->nr]) {
                if ($gastTore[$a->nr] != $gastTore[$b->nr]) {
                    // die Anzahl der auswärts erzielten Tore im direkten Vergleich
                    $tore = $gastTore;
                }
                else {
                    // die Anzahl aller auswärts erzielten Tore
                    $tore = array($a->nr => 0, $b->nr => 0);
                    foreach ($this->spieltage as $spieltag) {
                        foreach ($spieltag->partien as $partie) {
                            if (isset($tore[$partie->gast->nr])) {
                                $tore[$partie->gast->nr] += max(0, (int) $partie->gTore);
                            }
                        }
                    }
                }
            }
            if ($tore[$a->nr] < $tore[$b->nr]) {
                $row = $tableArray[$i];
                $tableArray[$i] = $tableArray[$i + 1];
                $tableArray[$i + 1] = $row;
                $i++;  // getauschtes Paar ist entschieden
            }
        }
        for ($i = 0; $i < $count; $i++) {
            $tableArray[$i]['pos'] = $i + 1;
        }
        return $tableArray;
    } // END function sortTable

    function sortDirectTable($tableArray) {
        return liga::sortTable($tableArray);
    }

} // END class FussballLiga

?>