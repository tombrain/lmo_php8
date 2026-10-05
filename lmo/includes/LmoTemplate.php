<?php
/**
 * LMO-Anpassungen fuer pear/html_template_it (per Composer).
 *
 * Die frueher mitgelieferte Kopie (includes/IT.php) war geaendert; diese Klasse stellt
 * dasselbe Verhalten auf Basis des Originalpakets her:
 * - Platzhalter im Format <!--Name--> statt {Name}
 * - unbekannte Platzhalter bleiben stehen (sonst verschwinden auch normale HTML-Kommentare)
 * - Variablen werden auch ohne passenden Platzhalter angenommen
 * - toString() liefert die geladene Templatedatei unveraendert
 */
class LMO_Template extends HTML_Template_IT
{
    var $openingDelimiter = '<!--';
    var $closingDelimiter = '-->';
    var $blocknameRegExp = '[0-9A-Za-z_-]+';
    var $variablenameRegExp = '[0-9A-Za-z_-]+';
    var $removeUnknownVariables = false;

    function __construct($root = '', $options = null)
    {
        $this->_options['preserve_input'] = false;
        $this->_options['check_placeholder_exists'] = false;
        parent::__construct($root, $options);
    }

    function loadTemplatefile($filename, $removeUnknownVariables = false, $removeEmptyBlocks = true)
    {
        return parent::loadTemplatefile($filename, $removeUnknownVariables, $removeEmptyBlocks);
    }

    function toString()
    {
        return $this->getFile($this->lastTemplatefile);
    }
}
