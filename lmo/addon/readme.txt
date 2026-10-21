Ausführlich mit Ordnerstruktur und allen Erweiterungspunkten: doku/ordnerstruktur.md im Paket
(https://github.com/henshingly/lmo_php8/blob/master/doku/ordnerstruktur.md).

Addons:

- Ein Verzeichnis = Ein Addon
- Wenn benötigt eine Vorlage der Konfiguration addon/[Addonname]/config-default/cfg.txt; der LMO legt
  daraus config/[Addonname]/cfg.txt an (Format wie config/cfg.txt)
- Wenn benötigt Sprachtexte addon/[Addonname]/lang/lang-[LMO-Sprache].txt
  (Addons nach alter Art: weiterhin lang/[Addonname]/lang-[LMO-Sprache].txt)
- Daten, die beim Betrieb entstehen, nach daten/[Addonname]/ (PATH_TO_DATADIR), nicht in den
  Ordner des Addons: der wird bei Updates ersetzt
- An den Anfang der Startdatei(en) des Addons diese Zeile einfügen

  require(dirname(__FILE__).'/../../init.php');

  Jetzt stehen zur Verfügung:

  # Die Konstanten
    PATH_TO_LMO,
    PATH_TO_ADDONDIR,
    PATH_TO_TEMPLATEDIR,
    PATH_TO_IMGDIR,
    PATH_TO_LANGDIR,
    PATH_TO_CONFIGDIR,
    PATH_TO_DATADIR,
    PATH_TO_JSDIR

    URL_TO_LMO
    URL_TO_ADDONDIR
    URL_TO_TEMPLATEDIR
    URL_TO_IMGDIR
    URL_TO_LANGDIR
    URL_TO_CONFIGDIR
    URL_TO_JSDIR
      Diese sind bei allen Pfadangaben zu benutzen!
      z.B. include('lmo-openfile.php'); wird zu include(PATH_TO_LMO.'/lmo-openfile.php');
      z.B. require('lmo-addondatei.php'); wird zu require(PATH_TO_ADDONDIR.'/addon/lmo-addondatei.php');

    # Alle Konfigurationsvariablen als Variablen der Form $<Addonname>_<Variablenname>
      z.B. der Wert foo=bar in der cfg-Datei des Addons brain steht als $brain_foo mit dem Wert bar zur Verfügung

    # Alle Textvariablen als Variablen der Form $text[<Addonname>][<Textnummer>]
      z.B. der Wert 001=Hallo in der lang-Datei des Addons brain steht als $text['brain'][1] zur Verfügung

    # Die globalen Konfigurationsvariablen des LMO aus der config/cfg.txt

    # Die globale Sprache des LMO (das $text[xxx]-Array]

- Das Ändern der Konfigurationsvariablen geschieht im Adminbereich des LMO oder (besser) mittels
  einer eigenen Konfigurationsoberfläche - das Speichern der Konfigurationsvariablen übernimmt dabei
  immer die Datei lmo/savecfg.php (per include einzubinden)


Addons nach neuer Art:

- Eine Datei addon/[Addonname]/addon.php gibt ein Objekt zurück, das Lmo\Addon\Addon implementiert:

    <?php
    return new class implements \Lmo\Addon\MitZusatzJeSpiel {
        public function name(): string { return 'brain'; }
        public function zusatzJeSpiel(\Lmo\Liga\Spiel $spiel, \Lmo\Web\Kontext $kontext): string {
            return '...';   // HTML am Ende der Ergebniszeile
        }
    };

- name() ist der Name des Ordners; Konfiguration und Sprachtexte wie oben beschrieben
  ($kontext->konfiguration->addon('brain'), $kontext->texteVon('brain')), Daten in daten/brain/.

- Was ein Addon beiträgt, sagen die Erweiterungspunkte, die es implementiert:

    # Lmo\Addon\MitSeiten          controller(): eigene Seiten im öffentlichen Teil (Lmo\Web\Controller\Controller,
                                   z.B. für action=brain); die Controller des LMO werden zuerst gefragt
    # Lmo\Addon\MitZusatzJeSpiel   zusatzJeSpiel(): HTML am Ende jeder Zeile in Ergebnissen und Spielplan
    # Lmo\Addon\MitZusatzUnterSeite zusatzUnterSeite(): HTML unter einer Seite ($kontext->action sagt, welche)
    # Lmo\Addon\MitRahmenteilen   rahmenteile(): Teile des Rahmens, z.B. ['Tippspiel' => '<a ...>'] als Menuepunkt
    # Lmo\Addon\MitAdminMenue    adminMenue(), ligaMenue(), startseite(): Punkte in den Menues der Administration
    # Lmo\Addon\MitAdminSeiten   adminController(): eigene Seiten in der Administration
    # Lmo\Addon\MitSchaltern     schalter(): Schalter in den Optionen, z.B. das Addon ein- und ausschalten

  Weitere Erweiterungspunkte (Ereignisse wie "Liga gespeichert") folgen.

- Ein Controller bekommt alles, was er braucht, im Lmo\Web\Kontext (Anfrage, geladene Liga,
  Konfiguration, Sprachtexte, Pfade, Addons) und braucht keine globalen Variablen.

- Ein Addon liegt vollstaendig in seinem Ordner:

    addon/[Addonname]/addon.php     gibt das Addon zurueck
    addon/[Addonname]/src/          Klassen (Namensraum Lmo\Addon\[Addonname]\, declare(strict_types=1))
    addon/[Addonname]/vorlagen/     Twig-Vorlagen (\Lmo\Web\Vorlagen::zeige(dirname(__DIR__) . '/vorlagen', ...))
    addon/[Addonname]/lang/         Sprachtexte
    addon/[Addonname]/config-default/cfg.txt  Vorlage der Konfiguration

  Die Klassen laedt der LMO selbst (Lmo\Addon\Autoloader, in init.php registriert): eine Klasse
  Lmo\Addon\Brain\Teil\Klasse liegt in addon/brain/src/Teil/Klasse.php. Ein Eintrag in
  composer.json ist nicht noetig; das Addon funktioniert, sobald sein Ordner da ist.

- Beispiele: das Ticker-Addon (addon/ticker/, Teil des Rahmens) und das Spieler-Addon
  (addon/spieler/, eigene Seite mit Menuepunkt).
