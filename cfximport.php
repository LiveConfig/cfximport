#!/usr/bin/php
<?php
  /** _    _          ___           __ _     (R)
   * | |  (_)_ _____ / __|___ _ _  / _(_)__ _
   * | |__| \ V / -_) (__/ _ \ ' \|  _| / _` |
   * |____|_|\_/\___|\___\___/_||_|_| |_\__, |
   *                                    |___/
   * $Id: cfximport.php 128 2013-11-14 16:04:12Z kk $
   * @package cfximport
   * @author Keppler IT GmbH <info@liveconfig.com>
   * @copyright Copyright (c) 2009-2013 Keppler IT GmbH.
   * @version 1.8
   * --------------------------------------------------------------------------
   * DIESE SOFTWARE WIRD "WIE SIE IST" UND AUSDRUECKLICH OHNE JEGLICHE
   * EXPLIZITE ODER IMPLIZITE ZUSICHERUNGEN BEZUEGLICH IHRER FUNKTION,
   * KORREKTHEIT ODER FEHLERFREIHEIT BEREITGESTELLT.
   * JEGLICHE GEWAEHRLEISTUNG FUER DIREKTE ODER INDIREKTE SCHAEDEN -
   * INSBESONDERE FUER SCHAEDEN AN ANDERER SOFTWARE, SCHAEDEN AN HARDWARE,
   * SCHAEDEN DURCH NUTZUNGSAUSFALL ODER DURCH NOTWENDIGE
   * DATENWIEDERHERSTELLUNG - WIRD AUSDRUECKLICH ABGELEHNT.
   *
   * Diese Software wurde mit groesster Sorgfalt erstellt, eventuelle Fehler
   * koennen jedoch nie ausgeschlossen werden. Es kann kann daher keine Gewaehr
   * fuer Ihre Daten uebernommen werden.
   *
   * Auf den Quell-Server ("Confixx") greift diese Software ausschliesslich
   * LESEND zu. Auf den Ziel-Server ("LiveConfig") wird ueber dessen SOAP-API
   * SCHREIBEND zugegriffen. Erzeugen Sie vorab also ein BACKUP des
   * Zielsystems, oder testen Sie die Migration vorab auf einem "leeren"
   * Server.
   *
   * Eine "in-place"-Migration wird ausdruecklich NICHT empfohlen ! ! !
   *
   * --------------------------------------------------------------------------
   * Migrations-Tool zum Umzug der Benutzerdaten von Confixx® zu LiveConfig®
   * --------------------------------------------------------------------------
   * Die neueste Version dieser Software sowie eine ausfuehrliche Anleitung
   * finden Sie unter
   *
   *    http://www.liveconfig.com/de/kb/5
   * 
   * Hilfestellungen zur Verwendung erhalten Sie im LiveConfig-Forum unter
   *    http://www.liveconfig.com/de/forum
   *
   *
   * Aufruf:  php cfximport.php -c | -h | --check
   *          php cfximport.php <kunde> [ <kunde> ... | --all ]
   *                              [ --webserver <server> ]
   *                              [ --mailserver <server> ]
   *                              [ --dbserver <server> ]
   * 
   * <kunde> [ <kunde> ...] [ -a, --all ]
   *                   Angabe der Reseller bzw. Kunden, die importiert werden
   *                   sollen (Beispiele: siehe unten)
   *                   Mit "-a" (oder "--all") werden alle Kunden eines
   *                   Resellers mit importiert.
   *
   * Optionen:
   * -h, --help        Hilfe ausgeben
   * -c, --config      Confixx-Konfiguration wird ausgelesen (aus
   *                   /root/confixx/confixx_main.conf, falls nicht vorhanden
   *                   werden die entsprechenden Daten abgefragt).
   *                   Anschliessend werden die Daten fuer den LiveConfig-Server
   *                   abgefragt, und in der Datei "cfximport.conf" gespeichert
   * --check           Konfiguration pruefen (Verbindungsaufbau zur Confixx-
   *                   Datenbank und zur LiveConfig-SOAP-API testen)
   * -i                Interaktiv: beim Anlegen eines neuen Kunden/Vertrags
   *                   nach der neuen Kunden-/Vertragsnummer fragen
   * --webserver <server>
   * --mailserver <server>
   * --dbserver <server>
   *                   Mit diesen Optionen kann ein alternativer Zielserver
   *                   fuer Webspace, E-Mails oder Datenbanken angegeben
   *                   werden. Der Zielserver muss bereits in LiveConfig
   *                   eingerichtet und zur Verwaltung aktiviert sein
   *                   (benoetigt Business-Lizenz).
   *
   * --importlocked    Angabe, ob auch gesperrte Kunden importiert werden sollen
   * --newreseller=<R> Importierte Kunden werden in LiveConfig dem angegebenen
   *                   Wiederverkäufer zugeordnet
   * --importplans     Nur relevant wenn --newreseller verwendet wird:
   *                   Importiere auch die Hosting-Angebote des "Quell-Resellers"
   *                   in den Account des LiveConfig-Ziel-Resellers
   * --htdocs=<PFAD>   setze <PFAD> als 'htdocs'-Verzeichnis (z.B. 'html')
   * --kdnr            auch die Kundennummer aus Confixx importieren
   * --fixmailquota    ueberbuchte Mailbox-Quota beruecksichtigen (die neue Mail-
   *                   Quota wird so angepasst, dass alle Postfaecher importiert
   *                   werden koennen)
   * --verbose         Ausfuehrlichere Informationen waehrend des Imports ausgeben
   *
   * DEBUG=1 schaltet zusaetzliche Debugausgaben an
   *
   * Beim Import von E-Mail-Postfächern aus Confixx wird automatisch eine Datei
   * namens "cfx-mail.log" angelegt, in der Einträge in folgendem Format
   * erzeugt werden:
   * <Confixx_Postfachname><TAB><LiveConfig-Postfach-Verzeichnis>
   * Beispiel:
   *   web9p3 /var/mail/web9/3
   * Somit können nach dem Import der Postfächer z.B. mittels 'rsync' auch die
   * Inhalte kopiert werden.
   *
   * --------------------------------------------------------------------------
   * BEISPIELE:
   *
   * Import des Resellers "res1" (ohne dessen Kunden):
   *   php cfximport.php res1
   *
   * Import der Kunden web1 und web23 (deren zugehoeriger Reseller wird
   * automatisch herausgesucht, und diesen dann auch jeweils auf dem
   * LiveConfig-Server zugeordnet; falls diese dort noch nicht existieren, wird
   * eine Fehlermeldung ausgegeben):
   *  php cfximport.php web1 web23
   *
   * Import des Resellers "res1" und all dessen Endkunden:
   *   php cfximport.php res1 --all
   *
   * --------------------------------------------------------------------------
   * "Confixx" ist eine eingetragene Marke der SWsoft Holdings Ltd.
   * (n.d.Ges.d. Staates Bermuda), Herndon Va., US
   * "LiveConfig" ist eine eingetragene Marke der Keppler IT GmbH, Erlangen, DE
   * --------------------------------------------------------------------------
   */

  $CONFIG           = array();
  $CFX_CONFIG       = array();
  $CONFIG_FILE      = "cfximport.conf";
  $CFX_CONFIG_FILE  = "/root/confixx/confixx_main.conf";
  $OPTS             = array();  # Array mit Kommandozeilen-Optionen

  $LOGGING = getenv('DEBUG');

  # kein Parameter angegeben, dann Hilfe ausgeben
  if ($argc == 1) {
    help_message();
    exit(1);
  }

  # Parsen der angegebenen Optionen und Parameter
  $OPTS = parseParameters(array('h', 'help', 'check', 'c', 'config', 'a', 'all', 'i', 'importlocked', 'importplans', 'kdnr', 'fixmailquota', 'verbose'));
  $action = 'import';

  foreach ($OPTS as $key => $value) {
    switch ("$key") {
        # Hilfe ausgeben
        case 'h':
        case "help":
          help_message();
          exit(0);
        
        # Serververbindung testen:
        case 'check':
          if ($action != 'import') {
            die("Ungueltige Verwendung von --check!\n");
          }
          $action = "check";
          break;

        # Konfiguration einlesen
        case 'c':
        case 'config':
          # Falls schon check aufgerufen wurde, abbrechen
          if ($action != 'import') {
            die("Ungueltige Verwendung von --check!\n");
          }
          $action="config";
          break;

        case 'a':
          $OPTS['all'] = $value;
          break;
    }
    if (is_int($key))  {
      $OPTS['customers'][] = $value;
      unset($OPTS[$key]);
    }
  }
  if (!isset($OPTS['webserver'])) $OPTS['webserver'] = 'localhost';
  if (!isset($OPTS['mailserver'])) $OPTS['mailserver'] = 'localhost';
  if (!isset($OPTS['dbserver'])) $OPTS['dbserver'] = 'localhost';
  if (!isset($OPTS['all'])) $OPTS['all'] = false;
  if (!isset($OPTS['i'])) $OPTS['i'] = false;
  if (!isset($OPTS['importlocked'])) $OPTS['importlocked'] = false;
  if (!isset($OPTS['importplans'])) $OPTS['importplans'] = false;
  if (!isset($OPTS['customers'])) $OPTS['customers'] = array();
  if (!isset($OPTS['htdocs'])) $OPTS['htdocs'] = 'htdocs';
  if (!isset($OPTS['kdnr'])) $OPTS['kdnr'] = false;
  if (!isset($OPTS['fixmailquota'])) $OPTS['fixmailquota'] = false;
  if (!isset($OPTS['verbose'])) $OPTS['verbose'] = false;
  $OPTS['defaultmailquota'] = -1;   # falls kein Mailquota beim Anbieter gesetzt ist

  # Ab hier erfolgt die Unterscheidung, welche Aktion durchgefuehrt werden soll

  # --------------------------------------------------------------------------
  # Konfiguration der Zugangsdaten zu den Servern (Confixx und Liveconfig)
  # Option: -c oder --config
  #
  # Zuerst wird geschaut, ob es eine Configurationsdatei von confixx gibt.
  # Falls ja, werden Daten daraus geleseen
  # Falls nein, alle Daten werden abgefragt
  # --------------------------------------------------------------------------
  
  if ($action == "config") {
    if (file_exists($CFX_CONFIG_FILE)) {
      # Confixx-Konfiguration aus confixx.conf laden
      $CONFIG = _read_config($CFX_CONFIG_FILE);
    } else {
      # Confixx-Konfiguration manuell abfragen
      $CONFIG = _get_config($CONFIG_FILE);
    }
    # LiveConfig-Konfiguration abfragen
    _get_LC_config();

    # Daten pruefen und ggf. in Konfigurationsdatei schreiben
    _check_config($CONFIG, $CONFIG_FILE);
    exit(0);
  }
  
  # --------------------------------------------------------------------------
  # Verbindungsaufbau  mit den Servern 
  # LiveConfig: SOAP-Funktion "TestSayHello()" aufrufen
  # Confixx:    Verbindung mit der Datenbank und Lesen der Anzahl der Kunden
  # Option: --check
  # --------------------------------------------------------------------------

  if ($action == "check") {
    # Zuerst alle Konfigurationsdaten aus $CONFIG_FILE lesen
    if (file_exists($CONFIG_FILE)) {
      # Verbindungsdaten aus der Datei lesen
      $CONFIG = _read_config($CONFIG_FILE);
    } else {
      print "Fehler: Configfile <" . $CONFIG_FILE . "> existiert nicht! Bitte rufen sie $argv[0] --config auf!\n";
      exit (1);
    }

    # Verbindung zum LiveConfig-Server
    $client = _connectLC();

    # Testen der Erreichbarkeit von LiveConfig
    $status = _checkLCServer();
    if ($status) {
      print "Verbindung zum LiveConfig-Server erfolgreich getestet.\n";
    } else {
      print "Der LiveConfig-Server ist fehlerhaft angegeben oder nicht erreichbar!\n";
      exit(1);
    }

    # Testen der Erreichbarkeit von Confixx
    $status = _checkCFXServer($CONFIG);
    if ($status) {
      print "Verbindung zur Confixx-Datenbank erfolgreich getestet.\n";
    } else {
      print "Der Confixx-Server ist fehlerhaft angegeben, bitte rufen Sie --config erneut auf!\n";
      exit(1);
    }
    exit(0);
  }

  # --------------------------------------------------------------------------
  # Importieren von Kunden und Resellern
  # Option: --all  bedeutet, dass alle Kunden dieses Resellers importiert werden sollen
  # keine Option: Auflistung aller einzelnen Kunden,Reseller zum importieren
  # Vorgehen:
  # 1. ist ein Reseller  mit dem Namen vorhanden?
  # - ja: Falls --all, dann Reseller importieren und anschliessend Schleife ueber alle Kunden
  # - nein: ist ein Kunde mit dem Namen vorhanden?
  # 2. Reseller des Kunden heraussuchen und importieren
  # 3. Fehlermeldung wenn weder Kunde noch Reseller vorhanden
  # --------------------------------------------------------------------------

  if ($action == "import") {
    echo '# cfximport.php - $Revision: 128 $', "\n";
    # Verbindungsdaten aus der Datei lesen
    $CONFIG = _read_config($CONFIG_FILE);

    # Verbindung zum LiveConfig-Server (SOAP-Client)
    $client = _connectLC();

    # Verbindung zur Confixx-Datenbank
    $connection = mysql_connect($CONFIG['dbServer'], $CONFIG['dbUser'], $CONFIG['dbPw']) or die ("Verbindung zur MySQL-Datenbank fehlgeschlagen");
    mysql_select_db($CONFIG['dbDB'], $connection) or die("Konnte die Datenbank nicht waehlen.");
    mysql_set_charset('utf8');

    $anbieter = '';
    $result = array ();
    $kunde = array();
    $maillog = NULL;

    # Standard-Werte für Postfach-Quota auslesen:
    $sql = "SELECT popmaxkb FROM admin";
    $res = mysql_query($sql) or die("Anfrage 'admin' nicht erfolgreich");
    if ($row = mysql_fetch_assoc($res)) {
      if (isset($row['popmaxkb'])) {
        $OPTS['defaultmailquota'] = $row['popmaxkb'];
        print "# admin.popmaxkb=${row['popmaxkb']}\n";
      } else {
        print "# admin.popmaxkb=UNKNOWN\n";
      }
    }
    mysql_free_result($res);

    # Falls Option --all gesetzt ist und keine Reseller angegeben wurden
    # Liste der Reseller auslesen  und an foreach übergeben
    if ($OPTS['all'] && count($OPTS['customers']) == 0) {
      print "Alle Reseller sollen importiert werden\n";
      $sql = "SELECT anbieter FROM anbieter";
      $cust_query = mysql_query($sql) or die("Anfrage 'Anbieter' nicht erfolgreich");
      while($row = mysql_fetch_assoc($cust_query)) {
        $OPTS['customers'][] = $row['anbieter'];
      }
      mysql_free_result($cust_query);
    }
    # Importieren des/der Kunden/Reseller
    # Im $OPTS['customers'] sind alle angegebenen zu importierenden Kunden gespeichert,
    # deshalbe eine Schleife ueber das Array
    foreach ($OPTS['customers'] as $cust){
      # Zuerst schauen, ob ein Reseller vorhanden ist
      logprint ("Reseller $cust suchen");
      $sql = "SELECT * FROM anbieter WHERE anbieter='" . mysql_real_escape_string($cust) ."'";
      $result_query = mysql_query($sql) or die("Anfrage 'Anbieter' nicht erfolgreich");
      $rows = mysql_num_rows($result_query);
      # Falls ein Reseller vorhanden ist wird der gesamte Datensatz gespeichert
      if ($rows > 0) {
        $result = mysql_fetch_assoc($result_query);
        # Da hier schon eine Datenbankabfrage erfolgte, koennen wir auch gleich den 
        # Datensatz fuer den Reseller speichern
        $anbieter = $result;
      } else {
        # Es ist kein Reseller vorhanden, also schauen, ob ein Kunde vorhanden ist
        logprint("Kunden $cust suchen");
        $sql = "select * from kunden where kunde='" . mysql_real_escape_string($cust) ."'";
        $result_query = mysql_query($sql) or die("Anfrage 'kunde' nicht erfolgreich");
        $rows = mysql_num_rows($result_query);
        # Falls ein Kunde vorhanden ist, dann kann man den Anbieter auslesen
        if ($rows == 1) {
          # Kunde vorhanden 
          logprint("Reseller fuer Kunden $cust suchen");
          $kunde = mysql_fetch_assoc($result_query);
          # Daten fuer den Reseller des Kunden heraussuchen aus der Datenbank
          $sql = "select * from anbieter where anbieter='" . $kunde['anbieter'] ."';";
          $result_query = mysql_query($sql) or die("Anfrage 'Reseller des Kunden' war nicht erfolgreich");
          # Speichern des Datensatzes in $anbieter
          $anbieter = mysql_fetch_assoc($result_query);
          # Datensatz Reseller/Kunde ist ermittelt
          print "Kunde: " . $kunde['kunde'] . " Reseller: " . $anbieter['anbieter'] ."\n";
        } else {
          # Wenn man hier ankommmt, gibt es weder  Kunde noch Anbieter - ueberspringen
          print "Fehler: Es gibt weder Kunde noch Reseller: $cust\n";
          continue;
        }
      }
      ################################################
      # Ab hier erfolgt der Import nach Liveconfig
      ################################################
      if (isset($OPTS['newreseller'])) {
        # einem anderen Reseller (oder dem Admin) in LiveConfig zuordnen
        $newreseller = $OPTS['newreseller'];
      } else {
        # 1:1 dem entsprechenden Reseller in LiveConfig zuordnen:
        $newreseller = $anbieter['anbieter'];
      }
      if ($newreseller == 'admin') {
        $rcustomer_id = NULL;
      } else  {
        # Zunaechst schauen, ob es den Reseller schon in LiveConfig gibt
        $response = _getHostingSubscriptionGet($newreseller);
        if ($response == null) {
          # nein, Resellervertrag existiert noch nicht, also anlegen.
          print "Importiere Reseller: ". $anbieter['anbieter'] . " ... ";
          list($rcustomer_id, $rcontact_id) = _importCustomer($anbieter);
          print "ok.\n";

          # nun einen ersten Benutzer für den Reseller anlegen (res#)
          try {
            $params = array('auth'      => createToken('UserAdd', NULL),
                            'customer'  => $rcustomer_id,
                            'contact'   => $rcontact_id,
                            'login'     => $anbieter['anbieter'],
                            'password'  => $anbieter['longpw']
                           );
            $response = $client->UserAdd($params);
            $user_id = $response->id;
          } catch (SoapFault $soapFault) {
            _traceSoapException();
            die("Fehler beim Aufruf von UserAdd(): " . $soapFault->faultstring . "\n");
          }
          print "\tUser erfolgreich angelegt\n";
          logprint("User-ID: $user_id\n");

          # Hostingvertrag mit in Confixx gespeicherten Daten anlegen
          $hostingpaket = _setHostingPlanAddData($anbieter);
          # weitere Daten fuer Soapaufruf fuellen
          $hostingpaket['customerid']       = $rcustomer_id;
          # im Falle eines Resellers muss dieser Wert ungleich NULL sein
          $hostingpaket['maxcustomers']     = $anbieter['maxkunden'];
          $hostingpaket['subscriptionname'] = $anbieter['anbieter'];
          $hostingpaket['password']         = $anbieter['longpw'];
#          if (isset($anbieter['maxwebapp'])) {
#            $hostingpaket['apps'] = $anbieter['maxwebapp'];
#          }
          # Angaben zu den Servern (Mail, Web, Datenbank-Server)
          $hostingpaket['webserver']        = $OPTS['webserver'];
          $hostingpaket['mailserver']       = $OPTS['mailserver'];
          $hostingpaket['dbserver']         = $OPTS['dbserver'];

          # wenn interaktiver Modus, dann nach (neuer) Vertragsnummer fragen:
          if ($OPTS['i']) {
            print "Vertragsnummer [" . $hostingpaket['subscriptionname'] . "]: ";
            $tmp = trim(fgets(STDIN));
            if ($tmp != "") {
              $hostingpaket['subscriptionname'] = $tmp;
            }
          }

          # Reseller-Vertrag anlegen:
          $hostingpaket['auth'] = createToken('HostingSubscriptionAdd');
          $response = $client->HostingSubscriptionAdd($hostingpaket);

          # Diese ID wird fuer das spaetere Anlegen des Kunden benoetigt
          $contract_id = $response->id;

        } else {
          # Reseller ist schon importiert
          logprint("Es gibt den Reseller schon!");
          $rcustomer_id = $response->customerid;
        }
        logprint ("Contract-ID Reseller $newreseller: $rcustomer_id");
      }

      if (!isset($OPTS['newreseller']) || $OPTS['importplans']) {
        # wenn der Vertrag *keinem* anderen Reseller zugewiesen wird, *oder* wenn die
        # Angebote ausdrücklich importiert werden sollen (--importplans), dann tu' das:

        $sql = "SELECT * FROM angebote where anbieter='" . $anbieter['anbieter'] . "';";
        $angebote_query = mysql_query($sql) or die("Anfrage 'Angebote' nicht erfolgreich");

        while ($angebot = mysql_fetch_array($angebote_query)){
          # Angebot anlegen
          # Nachsehen, ob es das Angebot $angebot['name'] schon in Liveconfig gibt
          $params = array('auth' => createToken('HostingPlanGet', $rcustomer_id),
                          'name' => $angebot['name']
                         );
          $response = $client->HostingPlanGet($params);
          $count = count((array)$response->plans);
          if ($count == 1) {
            # Angebot existiert schon
            logprint("Angebot '" . $response->plans->HostingPlanDetails->name . "' existiert schon!");
            continue;
          } else {
            $data = _setHostingPlanAddData($angebot);
            $data['auth']=createToken('HostingPlanAdd',$rcustomer_id);
            $response = $client->HostingPlanAdd($data);
            $hosting_id = $response->id;
            logprint("Hostingplan-ID: $hosting_id");
            print "Angebot: ". $angebot['name'] . " erfolgreich importiert\n";
          }
        }
        mysql_free_result($angebote_query);
      }

      # Auslesen aller Hostingpakete des Resellers (eigene und die von Confixx)
      # fuer die Ermittlung des am besten passenden Hostingpaketes
      $params = array('auth' => createToken('HostingPlanGet', $rcustomer_id),
                      'name' => NULL
                     );
      $response = $client->HostingPlanGet($params);
      $plans = $response->plans;
      if ((count((array)$plans)) == 0) {
        print "Reseller hat kein Angebot\n";
        $plans = NULL;
      } else {
        if (is_array($response->plans->HostingPlanDetails)) {
          $plans = $response->plans->HostingPlanDetails;
        }
        logprint("Anzahl der Gesamt-Hostingangebote: " . count((array)$plans));
        foreach ($plans as $plan) {
          print "- Name: " . $plan->name . "\n";
        }
      }

      ######################################################
      # Kunde(n) importieren nach LiveConfig
      ######################################################
      # Daten fuer den Kunden aus Confixx lesen (Tabelle kunden)
      # Falls --all gesetzt ist dann alle Kunden des Anbieters auslesen, sonst nur den einen
      # Falls --importlocked gesetzt ist dann auch "gesperrte" Kunden importieren
      $sql = "SELECT * FROM kunden WHERE anbieter='" . mysql_real_escape_string($anbieter['anbieter']) . "'";
      if (isset($kunde['kunde'])) {
        $sql .= " AND kunde='" . mysql_real_escape_string($kunde['kunde']) . "'";
      }
      if (!$OPTS['importlocked']) {
        $sql .= " AND gesperrt=0";
      }
      $sql .= " ORDER BY anbieter, kunde";
      $result_query = mysql_query($sql) or die("Anfrage 'Kunden des Resellers' nicht erfolgreich");
      while ($result = mysql_fetch_assoc($result_query)) {
        # Testen, ob es schon einen Vertrag fuer den $result['kunden'] gibt
        $subscriptionname = $result['kunde'];

        # BEISPIEL: basieren auf Kundennummer automatisch setzen:
        # $subscriptionname = 'k' . $result['kundennummer'];

        # wenn interaktiver Modus, dann nach (neuer) Vertragsnummer fragen:
        if ($OPTS['i']) {
          print "Neue Vertragsnummer [" . $subscriptionname . "]: ";
          $tmp = trim(fgets(STDIN));
          if ($tmp != "") {
            $subscriptionname = $tmp;
          }
        }

        $response = _getHostingSubscriptionGet($subscriptionname, $rcustomer_id);
        if ($response == null) {
          print "Importe Kunde: " . $result['kunde'] . " ... "; 
          list($customer_id, $contact_id) =_importCustomer($result, $rcustomer_id);
          print "ok\n";
          ###############################
          # User anlegen
          ###############################
          try {
            $params = array('auth'      => createToken('UserAdd', $rcustomer_id),
                            'customer'  => $customer_id,
                            'contact'   => $contact_id,
                            'login'     => $subscriptionname,
                            'password'  => $result['longpw']
                           );
            $response = $client->UserAdd($params);
            $user_id = $response->id;
          } catch (SoapFault $soapFault) {
            _traceSoapException();
            die("Fehler beim Soapaufruf UserAdd: " . $soapFault->faultstring . "\n");
          }
          logprint("User-ID: $user_id\n");
          print "\tUser angelegt\n";
        
          ###############################
          # Hosting Vertrag anlegen
          ###############################
          # Falls kein oder ein ungueltiges Quota angelegt ist, dann auf "unbegrenzt" setzen
          if (isset($result['popmaxkb']) && $result['popmaxkb'] == 0) {
            $result['popmaxkb'] = -1;
          }
          # Postfach-Quota ggf. anpassen:
          if ($OPTS['fixmailquota'] && $result['popmaxkb'] > -1) {
            $sql = "SELECT SUM(maxkb) AS MBQUOTA FROM pop3 WHERE kunde='" . $result['kunde'] . "'";
            $res = mysql_query($sql);
            if ($row = mysql_fetch_assoc($res)) {
              if ($row['MBQUOTA'] > $result['popmaxkb']) {
                $result['popmaxkb'] = $row['MBQUOTA'];
              }
            }
            mysql_free_result($res);
          }
          # Hostingangebot finden
          $hostingpaket = _findBestHostingPlan($plans, $result);
          # Vertrag anlegen
          $hostingpaket['subscriptionname'] = $subscriptionname;
          $hostingpaket['password']         = $result['longpw'];
          if ($newreseller == 'admin') {
            # Server explizit wählen:
            $hostingpaket['webserver']  = $OPTS['webserver'];
            $hostingpaket['mailserver'] = $OPTS['mailserver'];
            $hostingpaket['dbserver']   = $OPTS['dbserver'];
          } else {
            # Resellervertrag angeben
            $hostingpaket['resalecontract'] = $newreseller;
          }
          $hostingpaket['customerid']       = $customer_id;
          if (isset($result['awstats']) && $result['awstats'] == 1) {
            $hostingpaket['webstats'] = 2;    # AWStats einrichten
          }

          $hostingpaket['auth'] = createToken('HostingSubscriptionAdd', $rcustomer_id);
          $response = $client->HostingSubscriptionAdd($hostingpaket);
          $subscriptionid = $response->id;
          logprint("Subscription-ID: $subscriptionid");
          print "\tVertrag angelegt (Owner: $newreseller, Angebot: " . (isset($hostingpaket['plan']) ? $hostingpaket['plan'] : '-individuell-') . ")\n";

          ###########################
          # Domains einrichten
          ###########################
          
          # Alle Domains auslesen fuer diesen Kunden
          $sql = "SELECT domain, pfad, richtigedomain, kunde FROM domains WHERE kunde='".$result['kunde']."' AND richtigedomain = 1";
          $res = mysql_query($sql) or die("Anfrage 'Domains des Kunden' nicht erfolgreich");
          $stddomain = NULL;
          while ($row = mysql_fetch_array($res)){
            if ($stddomain == NULL) $stddomain = $row['domain'];
            $d_data['subscription']=$subscriptionname;
            $d_data['domain']=$row['domain'];
            $d_data['mail']=1;
            $d_data['web']=$row['pfad'];
            $d_data['auth']=createToken('HostingDomainAdd',$rcustomer_id);
            # -----------------------------
            # Soapaufruf HostingDomainAdd
            # -----------------------------
            try {
              $response = $client->HostingDomainAdd($d_data);
              $domain_id = $response->id;
            } catch (SoapFault $soapFault) {
              _traceSoapException();
              die("Error while calling Web Service HostingDomainAdd: " . $soapFault->faultstring . "\n");
            }
            print "\tDomain: " . $row['domain'] . " angelegt.\n";
          }
          mysql_free_result($res);

          #################################
          # Subomains einrichten
          #################################

          # Alle Subomains auslesen fuer diesen Kunden
          $sql = "SELECT domain, pfad, richtigedomain, kunde FROM domains WHERE kunde='". $result['kunde'] ."' AND (richtigedomain = 0 OR richtigedomain = 2)";
          $res = mysql_query($sql) or die("Anfrage 'Subdomains des Kunden' nicht erfolgreich");
          while ($row = mysql_fetch_array($res)){
            $d_data['subscription']=$subscriptionname;
            $d_data['subdomain']=$row['domain'];
            # pruefen, ob diese Subdomain in irgendeiner Mailadresse genutzt wird; falls ja, dann auch
            # für E-Mails aktivieren:
            $sql = "SELECT domain FROM email WHERE kunde='". $result['kunde'] ."' AND domain='" . mysql_real_escape_string($row['domain']) . "' LIMIT 1";
            $res2 = mysql_query($sql) or die("Anfrage fehlgeschlagen: $sql");
            if ($row2 = mysql_fetch_array($res2)) {
              # ja, mindestens eine Adresse gefunden...
              $d_data['mail']=1;
            } else {
              $d_data['mail']=0;
            }
            mysql_free_result($res2);
            $d_data['web']=$row['pfad'];
            $d_data['auth']=createToken('HostingSubdomainAdd',$rcustomer_id);
            # ---------------------------------
            # SOAP-Aufruf HostingSubdomainAdd()
            # ---------------------------------
            try {
              $response = $client->HostingSubdomainAdd($d_data);
              $subdomain_id = $response->id;
            } catch (SoapFault $soapFault) {
              _traceSoapException();
              die("Error while calling Web Service HostingSubdomainAdd: " . $soapFault->faultstring . "\n");
            }
            print "\tSubDomain: " . $row['domain'] . " angelegt.\n";
          }
          mysql_free_result($res);

          ###################################
          # Cronjobs einrichten
          ###################################
          # Cronjobfile auslesen fuer diesen Kunden
          $sql = "SELECT cronfile FROM cronjobs WHERE kunde='" . $result['kunde'] ."'";
          $res = mysql_query($sql) or die("Anfrage 'Cronjobs des Kunden' nicht erfolgreich");
          if ($row = mysql_fetch_assoc($res)) {
            $data = preg_split("/\r?\n/", $row['cronfile'], NULL, PREG_SPLIT_NO_EMPTY);
            foreach ($data as $line) {
              # Kommentare und leere Zeilen weglassen
              if (preg_match('/^\s*(#|$)/', $line)) continue;
              list($min, $hour, $dom, $month, $dow, $command) = preg_split("/\s+/", trim($line), 6);
              # Verzeichnisnamen von $command anpassen: /var/www/web#/html/ -> /var/www/web#/htdocs/
              while (preg_match('/\/var\/www\/([^\/]+)\/html(\/.*)?$/', $command)) {
                $command = preg_replace('/\/var\/www\/([^\/]+)\/html(\/.*)?$/', '/var/www/$1/' . $OPTS['htdocs'] . '$2', $command);
              }
              $cron['minute']  = trim($min);
              $cron['hour']    = trim($hour);
              $cron['day']     = trim($dom);
              $cron['month']   = trim($month);
              $cron['weekday'] = trim($dow);
              $cron['command'] = trim($command);
              $cron['active']  = 1;
              $cron['subscription'] = $subscriptionname;
              $cron['auth'] = createToken('HostingCronAdd', $rcustomer_id);
              # -----------------------------
              # SOAP-Aufruf HostingCronAdd()
              # -----------------------------
              try {
                $response = $client->HostingCronAdd($cron);
                $cronjob_status = $response->status;
              } catch (SoapFault $soapFault) {
                _traceSoapException();
                die("Error while calling Web Service HostingCronAdd: " . $soapFault->faultstring . "\n");
              }
              print "\tCronjob eingerichtet\n";
            }
          }
          mysql_free_result($res);
           
          #############################
          # Datenbanken einrichten
          #############################
          # Einrichten der Datenbank zum Vertrag
          # erst MySQL-Passwort auslesen:
          $pwd = NULL;
          $sql = "SELECT Password from mysql.user where User='" . $result['kunde'] . "' LIMIT 1";
          $res = mysql_query($sql) or die ("Fehler beim Auslesen des Datenbankpassworts: " . mysql_error());
          if ($row = mysql_fetch_assoc($res)) {
            $pwd = $row['Password'];
            if (strlen($pwd) == 16) {
              # vor-gehashtes MySQL 4.x-Passwort
              $pwd = "MYSQL:" . $pwd;
            }
          }
          mysql_free_result($res);
          # dann Liste aller MySQL-Datenbanken dieses Kunden auslesen:
          $sql = "select dbname, dbext, kommentar from mysql_datenbanken where kunde ='". $result['kunde'] ."'";
          $res = mysql_query($sql) or die("Anfrage 'Datenbanken des Kunden' nicht erfolgreich");
          while ($row = mysql_fetch_assoc($res)){
            if ($pwd == NULL) {
              print "\tFEHLER: kann MySQL-Datenbank '${row['dbname']}' nicht importieren: MySQL-Benutzer ${result['kunde']} nicht gefunden!\n";
              continue;
            }
            $dbase['subscription']=$subscriptionname;
            $dbase['name']=$row['dbname'];
            # OPTIONAL: Datenbank beim Import umbenennen:
            # $dbase['name']=preg_replace('/^usr_web\d+_(.*)$/', "${subscriptionname}db$1", $dbase['name']);
            $dbase['login']=$subscriptionname;
            $dbase['create']=1;
            $dbase['extern']=$row['dbext'];
            $dbase['comment']=$row['kommentar'];
            $dbase['password']=$pwd;
            $dbase['auth']=createToken('HostingDatabaseAdd',$rcustomer_id);
            # ---------------------------------
            # SOAP-Aufruf HostingDatabaseAdd()
            # ---------------------------------
            try {
              $response = $client->HostingDatabaseAdd($dbase);
              $db_id = $response->id;
            } catch (SoapFault $soapFault) {
              _traceSoapException();
              die("Error while calling Web Service HostingDatabaseAdd: " . $soapFault->faultstring . "\n");
            }
            print "\tDatenbank: ". $dbase['name']." angelegt\n";
          }
          mysql_free_result($res);

          #############################
          # Postfaecher einrichten
          #############################
          if ($stddomain != NULL) {
            # alle POP3-Postfächer auslesen und anlegen:
            $sql = "SELECT account, longpw, maxkb FROM pop3 WHERE kunde='" . $result['kunde'] . "'";
            $res = mysql_query($sql);
            while ($row = mysql_fetch_assoc($res)) {
              $mbox['subscription']   = $subscriptionname;
              $mbox['name']           = $row['account'];
              $mbox['domain']         = $stddomain;
              $mbox['mailbox']        = 1;
              $mbox['password']       = $row['longpw'];
              $mbox['quota']          = $row['maxkb'] / 1024;
              if ($mbox['quota'] == 0) $mbox['quota'] = $OPTS['defaultmailquota'];
              $mbox['autoresponder']  = 0;
              $mbox['auth']           = createToken('HostingMailboxAdd', $rcustomer_id);
              # ---------------------------------
              # SOAP-Aufruf HostingMailboxAdd()
              # ---------------------------------
              try {
                $response = $client->HostingMailboxAdd($mbox);
                $mbox_id = $response->id;
                $mbox_folder = $response->folder;
              } catch (SoapFault $soapFault) {
                _traceSoapException();
                die("Error while calling Web Service HostingMailboxAdd: " . $soapFault->faultstring . "\n");
              }
              print "\tPostfach: ". $row['account'] . "@$stddomain angelegt (/var/mail/" . $subscriptionname . "/$mbox_folder/)\n";
              if ($maillog === NULL) {
                $maillog = fopen("cfx-mail.log", "a");
              }
              fwrite($maillog, $row['account'] . "\t/var/mail/$subscriptionname/$mbox_folder\n");
            }
            mysql_free_result($res);

            # E-Mail-Adressen (Weiterleitungen) anlegen:
            $sql = "SELECT email.ident, email.prefix, email.domain, emailbetreff, emailtext "
                  ."FROM email LEFT JOIN autoresponder ON (email.ident=autoresponder.ident) "
                  ."WHERE email.kunde='" . $result['kunde'] . "'";
            $res = mysql_query($sql);
            while ($row = mysql_fetch_assoc($res)) {
              $fwd = array();
              $fwd['subscription']    = $subscriptionname;
              $fwd['name']            = $row['prefix'];
              $fwd['domain']          = $row['domain'];
              $fwd['mailbox']         = 0;
              if (isset($row['emailbetreff'])) {
                $fwd['autoresponder'] = 1;
                $fwd['autosubject']   = $row['emailbetreff'];
                # "emailtext" ist eine BLOB-Spalte -> daher manuell in UTF8 konvertieren!
                $fwd['automessage']   = utf8_encode($row['emailtext']);
              } else {
                $fwd['autoresponder'] = 0;
              }
              $fwd['auth']  = createToken('HostingMailboxAdd', $rcustomer_id);
              $dest = array();
              # für jede E-Mail-Adresse die Liste der Ziele auslesen:
              $sql = "SELECT pop3 FROM email_forward WHERE email_ident=" . $row['ident'];
              $res2 = mysql_query($sql);
              while ($row2 = mysql_fetch_assoc($res2)) {
                $addr = trim($row2['pop3']);  # Confixx erlaubt führende Leerzeichen bei Weiterleitungs-Zielen %-|
                if (!strstr($addr, '@')) {
                  # lokales Postfach: Standard-Domain anfügen
                  $addr .= '@' . $stddomain;
                }
                array_push($dest, $addr);
              }
              mysql_free_result($res2);
              if (count($dest) == 0) continue;
              $fwd['forward'] = $dest;
              # ---------------------------------
              # SOAP-Aufruf HostingMailboxAdd()
              # ---------------------------------
              try {
                $response = $client->HostingMailboxAdd($fwd);
                $mbox_id = $response->id;
              } catch (SoapFault $soapFault) {
                _traceSoapException();
                die("Error while calling Web Service HostingMailboxAdd: " . $soapFault->faultstring . "\n");
              }
              print "\tAdresse: ". $fwd['name'] . "@" . $fwd['domain'] . " (Ziele: " . implode('/', $dest) . ") angelegt\n";
            }
            mysql_free_result($res);
          } # if ($stddomain != NULL) ...

          #############################
          # Verzeichnis-Passwortschutz
          #############################
          # alle geschützten Verzeichnisse durchgehen:
          $sql = "SELECT pfad, bereich, login, longpw FROM pwschutz, users WHERE pwschutz.kunde='". $result['kunde'] ."' AND pwschutz.ident=users.parent";
          $res = mysql_query($sql) or die("Fehler bei Datenbankabfrage");
          while ($row = mysql_fetch_assoc($res)){
            # .htpasswd-Benutzer anlegen:
            $dbase['subscription']=$subscriptionname;
            $dbase['login']=$row['login'];
            $dbase['password']=$row['longpw'];
            $dbase['auth']=createToken('HostingPasswordUserAdd',$rcustomer_id);
            # ---------------------------------
            # SOAP-Aufruf HostingPasswordUserAdd()
            # ---------------------------------
            try {
              $response = $client->HostingPasswordUserAdd($dbase);
            } catch (SoapFault $soapFault) {
              _traceSoapException();
              die("Error while calling Web Service HostingPasswordUserAdd: " . $soapFault->faultstring . "\n");
            }
            print "\t.htpasswd-Benutzer: ". $row['login']." angelegt\n";

            # und Verzeichnisschutz einrichten:
            $dbase['subscription']=$subscriptionname;
            $dbase['path']='/' . $OPTS['htdocs'] . $row['pfad'];
            $dbase['title']=$row['bereich'];
            $dbase['login']=$row['login'];
            $dbase['auth']=createToken('HostingPasswordPathAdd',$rcustomer_id);
            # ---------------------------------
            # SOAP-Aufruf HostingPasswordPathAdd()
            # ---------------------------------
            try {
              $response = $client->HostingPasswordPathAdd($dbase);
            } catch (SoapFault $soapFault) {
              _traceSoapException();
              die("Error while calling Web Service HostingPasswordPathAdd: " . $soapFault->faultstring . "\n");
            }
            print "\t -> Verzeichnis: ". $row['pfad']." hinzugefuegt\n";
          }
          mysql_free_result($res);

          ###################################
          # FTP-Accounts
          ###################################
          $sql = "SELECT account, longpw, pfad FROM ftp WHERE kunde='" . $result['kunde'] ."' AND gesperrt=0";
          $res = mysql_query($sql) or die("Fehler bei Datenbankabfrage");
          while ($row = mysql_fetch_assoc($res)) {
            $dbase['subscription']=$subscriptionname;
            $dbase['login']=$row['account'];
            $dbase['password']=$row['longpw'];
            $dbase['path']=$OPTS['htdocs'] . '/' . $row['pfad'];
            $dbase['auth']=createToken('HostingFtpAdd',$rcustomer_id);
            # ---------------------------------
            # SOAP-Aufruf HostingFtpAdd()
            # ---------------------------------
            try {
              $response = $client->HostingFtpAdd($dbase);
            } catch (SoapFault $soapFault) {
              _traceSoapException();
              die("Error while calling Web Service HostingFtpAdd: " . $soapFault->faultstring . "\n");
            }
            print "\tFTP-Benutzer: ". $row['account']." angelegt\n";

          }
          mysql_free_result($res);

        } else {
          # Der Kunde ist schon importiert
          logprint("Es gibt den Kunden " . $subscriptionname . " schon!");
          continue;
        }
      }
      $anbieter = NULL;
    }
    mysql_close($connection);
    print "Import erfolgreich beendet!\n";
  } else {
    print "Fehler: unbekannte Aktion!\n";
  }

  ######################################################################
  #
  # Hilfs-Funktionen fuer Soapaufrufe
  #
  ######################################################################


  /**
   * Finde aus allen Hostingangeboten eines Resellers das am besten passende Angebot
   *
   * @param array $angebote alle Angebote des Resellers
   * @param array $cpaketdata Kundendaten
   * @global array Programmoptionen
   * @return array Paketdaten
   */
  function _findBestHostingPlan ($angebote, $cpaketdata) {
    global $OPTS;
    $paket = array ();

    $mailaddrs = $cpaketdata['maxemail'];
    $mailboxes = $cpaketdata['maxpop'];
    # in Confixx zaehlen Postfächer nicht als einzelne Adressen; in LiveConfig muss aber
    # zwangsweise jedes Postfach als eigene Mailadresse angelegt werden. Also Limit erhoehen:
    if ($mailaddrs != -1 && $mailboxes != -1) $mailaddrs += $mailboxes;

    if ($OPTS['verbose']) {
      print "  Suche Angebot fuer: webspace=${cpaketdata['maxkb']}, mailboxes=$mailboxes, mailaddrs=$mailaddrs, databases=${cpaketdata['maxmysql']}, ftp=${cpaketdata['maxftp']}\n";
    }

    if ($angebote == NULL) {
      $paket = array (
                      'webspace'    => $cpaketdata['maxkb'] == -1 ? -1 : (int)($cpaketdata['maxkb']/1024),
                      'traffic'     => $cpaketdata['maxtransfer'] == -1 ? -1 : (int)($cpaketdata['maxtransfer']/1024),
                      'subdomains'  => $cpaketdata['maxsubdomains'],
                      'mailboxes'   => $mailboxes,
                      'mailaddrs'   => $mailaddrs,
                      'mailquota'   => (!isset($cpaketdata['popmaxkb']) || ($cpaketdata['popmaxkb'] == -1)) ? -1 : (int)($cpaketdata['popmaxkb']/1024),
                      'cgi'         => $cpaketdata['perl'],
                      'php'         => $cpaketdata['php'],
                      'ssi'         => $cpaketdata['ssi'],
                      'databases'   => $cpaketdata['maxmysql'],
                      'ftpaccounts' => $cpaketdata['maxftp'] == -1 ? -1 : ($cpaketdata['maxftp']+1), # +1, da Confixx hier die Anzahl *zusätzlicher* FTP-Accounts verwaltet
                      'shellaccess' => 0,
                      'cronjobs'    => $cpaketdata['maxcronjobs'],
                      'maxusers'    => 1
                     );
      if (isset($cpaketdata['maxwebapp'])) $paket['apps'] = $cpaketdata['maxwebapp'];
      if ($OPTS['verbose']) {
        print "  -> nehme individuelles Angebot (keine Angebote vorhanden)\n";
      }
    } else {
      # Schleife ueber alle Angebote
      $best_paket = NULL;
      $best_matches=-1;
      foreach($angebote as $angebot) {
        $anzahl= 0;
        $paket = array();
        if (isset($angebot->maxcustomers)) continue;
        # Vergleich ob Webspace uebereinstimmt
        if ((int)($cpaketdata['maxkb']/1024) == $angebot->webspace || ($cpaketdata['maxkb'] == -1 && $angebot->webspace == -1)) {
          $anzahl++;
        } else {
          $paket['webspace'] = $cpaketdata['maxkb'] == -1 ? -1 : (int)($cpaketdata['maxkb']/1024);
        }
        # Vergleich des Traffics
        if ( (int)($cpaketdata['maxtransfer']/1024) == $angebot->traffic || ($cpaketdata['maxtransfer'] == -1 && $angebot->traffic == -1) ) {
          $anzahl++;
        } else {
          $paket['traffic'] = $cpaketdata['maxtransfer'] == -1 ? -1 : (int)($cpaketdata['maxtransfer']/1024);
        }

        # Vergleich der Anzahl der maximalen Subdomains
        if ( $cpaketdata['maxsubdomains'] == $angebot->subdomains ) {
          $anzahl++;
        } else {
          $paket['subdomains']=$cpaketdata['maxsubdomains'];
        }

        # Vergleich der Anzahl der E-Mail-Postfaecher
        if ( $mailboxes == $angebot->mailboxes) {
          $anzahl++;
        } else {
          $paket['mailboxes']=$mailboxes;
        }

        # Vergleich der Anzahl der E-Mail-Adressen
        if ( $mailaddrs == $angebot->mailaddrs) {
          $anzahl++;
        } else {
          $paket['mailaddrs']=$mailaddrs;
        }

        # Vergleich der Postfachgroessen:
        if ( !isset($cpaketdata['popmaxkb']) ||
             ($cpaketdata['popmaxkb'] > 0 && $cpaketdata['popmaxkb'] == $angebot->mailquota * 1024) ||
             ($cpaketdata['popmaxkb'] == -1 && $angebot->mailquota == -1)
           ) {
          $anzahl++;
        } else {
          $paket['mailquota'] = $cpaketdata['popmaxkb'] == -1 ? -1 : (int)($cpaketdata['popmaxkb']/1024);
        }

        # Vergleich der Anzahl der maximalen FTP Accounts
        if ( ($cpaketdata['maxftp'] == -1 && $angebot->ftpaccounts == -1) || ($cpaketdata['maxftp']+1 == $angebot->ftpaccounts)) {
          $anzahl++;
        } else {
          $paket['ftpaccounts']=$cpaketdata['maxftp'] == -1 ? -1 : ($cpaketdata['maxftp']+1);   # +1, da Confixx hier die Anzahl *zusätzlicher* FTP-Accounts verwaltet
        }

        # Vergleich der Anzahl der MySQL-Datenbanken
        if ( $cpaketdata['maxmysql'] == $angebot->databases) {
          $anzahl++;
        } else {
          $paket['databases']=$cpaketdata['maxmysql'];
        }

        # Vergleich der Anzahl der Cron-Jobs
        if ( $cpaketdata['maxcronjobs'] == $angebot->cronjobs) {
          $anzahl++;
        } else {
          $paket['cronjobs']=$cpaketdata['maxcronjobs'];
        }

        # Anzahl Apps
        if (isset($cpaketdata['maxwebapp'])) {
          if ($cpaketdata['maxwebapp'] == $angebot->apps) {
            $anzahl++;
          } else {
            $paket['apps']=$cpaketdata['maxwebapp'];
          }
        }

        # Vergleich verschiedener Flags

        # Vergleich ob cgi ja/nein
        if ($cpaketdata['perl'] == $angebot->cgi) {
          $anzahl++;
        } else {
          $paket['cgi']=$cpaketdata['perl'];
        }

        # Vergleich ob php ja/nein
        if (($cpaketdata['php'] == 0 && $angebot->php == 0) ||
            ($cpaketdata['php'] == 1 && $angebot->php > 0)) {
          $anzahl++;
        } else {
          $paket['php']=$cpaketdata['php'];
        }
        # Vergleich ob ssi ja/nein
        if ($cpaketdata['ssi'] == $angebot->ssi) {
          $anzahl++;
        } else {
          $paket['ssi']=$cpaketdata['ssi'];
        }
        # Confixx hat 2 Felder dafuer: Shell 0/1 mit ja/nein,
        # wenn Shell auf 0 und scponly auf 1, dann scponly Zugriff
        # Vergleich ob shellacces ja/nein/scponly
        if ($cpaketdata['shell']==1) {
          $shell=2;
        } else {
          if ($cpaketdata['scponly']==1) {
            $shell=1;
          } else {
            $shell=0;
          }
        }

        if ($shell == $angebot->shellaccess) {
          $anzahl++;
        } else {
          $paket['shellacces'] = $shell;
        }      

        $paket['plan'] = $angebot->name;

        if ($OPTS['verbose']) {
          print "  -> Angebot '${paket['plan']}': $anzahl Treffer\n";
        }

        if ($anzahl > $best_matches) {
          #print $paket['plan']. "\n";
          $best_matches = $anzahl;
          $best_paket = $paket;
        }
      }
      $paket = $best_paket;
      if ($OPTS['verbose']) {
        print "  -> waehle Paket '${paket['plan']}'\n";
      }
    }

    return $paket;
  }

  #########################################################################################
  #
  #       Hilfsfunktionen zum Konfigurieren, Checken und Importieren
  #
  #########################################################################################

  #----------------------------------------------------
  # logprint($text) 
  #
  # Falls LOGGING auf 1 gesetzt ist, erfolgen Log print 
  # ausgeben
  #----------------------------------------------------
  function logprint($text) {
    global $LOGGING;
    if ($LOGGING == 1) {
      print $text . "\n";
    } 
  }


  /**
   * SOAP-Client zur Kommunikation mit LiveConfig erzeugen
   *
   * @global array globale Konfiguration
   * @return SoapClient SOAP-Client-Objekt
   */
  function _connectLC() {
    global $CONFIG;

    # Verbindung zum LiveConfig Server herstellen
    print "Verbindung zum LiveConfig-Server ... ";
    ini_set("soap.wsdl_cache_enabled", "0");
    # WSDL URL erstellen
    $wsdl_url = $CONFIG['url']
           .'?wsdl'
           .'&l=' . urlencode($CONFIG['user'])
           .'&p=' . urlencode($CONFIG['pass']);

    # SOAP-Client erzeugen:
    try {
      $client = new SoapClient($wsdl_url,
                         array('style'    => SOAP_DOCUMENT,
                               'use'      => SOAP_LITERAL,
                               'trace'    => 1,
                              )
                        );
    } catch (Exception $e) {
      print ("Fehler: " . $e->faultstring . "\n");
      exit (1);
    }

    # Versionsnummer pruefen
    try {
      $params = array('auth' => createToken('LiveConfigVersion', NULL),
                     );
      $response = $client->LiveConfigVersion($params);
      if ($response->revision < 1930) {
        print ("LiveConfig-Server hat Version " . $response->version . "-" . $response->revision . " - benoetigt wird mindestens 1.5.3-r1930!\n");
        exit(1);
      }
    } catch (Exception $e) {
      _traceSoapException();
      print ("Fehler beim Abrufen der Versionsnummer: " . $e->faultstring . "\nSie benoetigen mindestens LiveConfig 1.3.3!\n");
      exit(1);
    }

    print "erfolgreich aufgebaut! (Version " . $response->version . "-r" . $response->revision . ")\n";

    return $client;
  }


  /**
   * Verbindung zum LiveConfig-Server (SOAP-API) prüfen
   *
   * @global SoapClient SOAP-Client-Instanz
   * @return bool Verbindungsstatus
   */
  function _checkLCServer() {
    global $client;
  
    try {
      $params = array('auth'        => createToken('TestSayHello', NULL),
                      'firstname'   => 'Max',
                      'lastname'    => 'Mustermann'
                     );
      $response = $client->TestSayHello($params);
    } catch (Exception $e) {
      print ("Fehler: " . $e->faultstring . "\n");
      return false;
    }

    if ($response->greeting != 'Hello, Max Mustermann') {
      print ("Fehler: unerwartete Antwort bei Testfunktion: '" . $response->greeting . "'\n");
      return false;
    }

    return true;
  }


  /**
   * Verbindung zur Confixx-Datenbank prüfen
   *
   * @global array globale Konfiguration
   * @return bool Verbindungsstatus
   */
  function _checkCFXServer() {
    global $CONFIG;
    $status = false;

    $dbh = mysql_connect($CONFIG['dbServer'], $CONFIG['dbUser'], $CONFIG['dbPw']) or die ("Verbindung fehlgeschlagen!");
    mysql_select_db($CONFIG['dbDB'], $dbh) or die("Konnte die Confixx-Datenbank nicht waehlen.");
    mysql_set_charset('utf8');
    $res = mysql_query("SELECT COUNT(*) FROM kunden", $dbh);
    if (!$res) {
      print("Fehler bei Datenbankabfrage: " . mysql_error() . "\n");
    } else {
      $row = mysql_fetch_row($res) or die (mysql_error());; 
      print "Kundenanzahl: $row[0]\n";
      mysql_free_result($res);
      $status = true;
    }

    # pruefen, ob Benutzerdaten aus der MySQL-Datenbank ausgelesen werden können:
    $res = mysql_query("SELECT COUNT(DISTINCT User) FROM mysql.user");
    if (!$res) {
      $status = false;
      print("Zugriff auf mysql.user nicht moeglich: " . mysql_error() . "\n");
      print("Tipp: GRANT SELECT(User, Password) ON mysql.user to 'confixx'@'127.0.0.1';\n");
    } else {
      # alles ok.
    }

    mysql_close($dbh);
    return($status);
  }

  # --------------------------------------------------------------------------
  # _getHostingSubscriptionGet
  #
  # Liest die Details zu einem vorhandenen Hosting-Vertrag aus
  # --------------------------------------------------------------------------
  function _getHostingSubscriptionGet($subscription, $cust_id=NULL) {

    global $client;

    try {
      $params = array('auth' => createToken('HostingSubscriptionGet', $cust_id),
                      'subscriptionname' => $subscription
                     );
      $response = $client->HostingSubscriptionGet($params);
    } catch (Exception $e) {
      # print ("Fehler " . $e->faultstring . ":$subscription\n");
      return(null);
    }
    return $response;
  }


  /**
   * Kundendatensatz aus Confixx in LiveConfig importieren
   * (legt Kontakt- und Kundendatensatz in LiveConfig an)
   *
   * @param array $custdata Kundendaten
   * @param string $cust_id ID des Resellers, dem dieser Kunde zugeordnet werden soll
   * @global SoapClient SOAP-Client-Instanz
   * @global array Programmoptionen
   * @return array bei Erfolg: Liste aus (Kunden_ID, Kontakt_ID)
   */
  function _importCustomer($custdata, $cust_id=NULL) {
    global $client;
    global $OPTS;
    $contact_id = 0;

    # Kontakt anlegen:
    $contact_data = _setContactAddData($custdata);
    try {
      $contact_data['auth'] = createToken('ContactAdd', $cust_id);
      $response = $client->ContactAdd($contact_data);
      $contact_id = $response->id;
    } catch (SoapFault $soapFault) {
      _traceSoapException();
      die("Error while calling Web Service ContactAdd: " . $soapFault->faultstring . "\n");
    }
    logprint("Contact-ID: $contact_id\n");

    $data = array('auth'      => createToken('CustomerAdd', $cust_id),
                  'owner_c'   => $contact_id,
                  'admin_c'   => $contact_id,
                  'tech_c'    => $contact_id,
                  'billing_c' => $contact_id,
                  'locked'    => $custdata['gesperrt']
                   );

    # wenn interaktiver Modus, dann nach (neuer) Kundennummer fragen:
    if ($OPTS['i']) {
      if ($OPTS['kdnr']) {
        $kdnr = $custdata['kundennummer'];
        print "Kundennummer [$kdnr]: ";
        $tmp = trim(fgets(STDIN));
        if ($tmp != "") {
          $data['cid'] = $tmp;
        } else {
          $data['cid'] = $kdnr;
        }
      } else {
        print "Kundennummer [automatisch]: ";
        $tmp = trim(fgets(STDIN));
        if ($tmp != "") {
          $data['cid'] = $tmp;
        }
      }
    }

    # Kunden anlegen:
    try {
      $response = $client->CustomerAdd($data);
      $customer_id = $response->id;
    } catch (SoapFault $soapFault) {
      _traceSoapException();
      die("Error while calling Web Service CustomerAdd: " . $soapFault->faultstring . "\n");
    }
    logprint("Customer-ID: $customer_id\n");
    
    return array($customer_id, $contact_id);
  }


  #-------------------------------------
  # _setContactAddData {
  #-------------------------------------
  function _setContactAddData ($contact) {

    $firstname = $contact['firstname'];
    $lastname = $contact['name'];
    $gender = $contact['gender'];
    $land = $contact['land'];

    # Firstname ist ein Pflichtfeld
    if ($firstname == '') {
        $firstname = "--";
    }

    # Lastname ist ein Pflichtfeld
    if ($lastname == '') {
        $lastname = "--";
    }

    if ($gender == "m") {
        $salutation = 0;
    } else{
        $salutation = 1;
    }

    # Land ist ein Pflichtfeld und wird auf DE gesetzt, falls es leer ist
    if ($land == '' || "Deutschland") {
        $land = 'DE';
    }
    $contactdata = array (
                        'salutation' => $salutation,
                        'firstname'  => $firstname,
                        'lastname'   => $lastname,
                        'company'    => $contact['firma'],
                        'address1'   => $contact['anschrift'],
                        'zipcode'    => $contact['plz'],
                        'city'       => $contact['plzort'],
                        'country'    => $land,
                        'phone'      => $contact['telefon'],
                        'fax'        => $contact['fax'],
                        'email'      => $contact['emailadresse'],
                       );

    return($contactdata);
  } 

  #-----------------------------------------------------
  # _setHostingPlanAddData
  # Setzt die Daten fuer das Anlegen eines HostingPlans
  #-----------------------------------------------------
  function _setHostingPlanAddData($angebot) {

    # Testen ob keine, bash oder scp als shell erlaubt ist
    if ($angebot['shell'] == 0) {
       $shellaccess = 0;
    } 
    if ($angebot['shell'] == 1) {
        $shellaccess = 2;
    } 
    if ($angebot['scponly'] == 0) {
        $shellaccess = 1;
    }

    $mailaddrs = $angebot['maxemail'];
    $mailboxes = $angebot['maxpop'];
    # in Confixx zaehlen Postfächer nicht als einzelne Adressen; in LiveConfig muss aber
    # zwangsweise jedes Postfach als eigene Mailadresse angelegt werden. Also Limit erhoehen:
    if ($mailaddrs != -1 && $mailboxes != -1) $mailaddrs += $mailboxes;

    # Erzeugen des Datenhashs fuer Liveconfig Soap Aufruf
    $paket_data = array (
                'name'        => $angebot['name'],
                'webspace'    => $angebot['maxkb'] == -1 ? -1 : (int)($angebot['maxkb']/1024),
                'traffic'     => $angebot['maxtransfer'] == -1 ? -1 : (int)($angebot['maxtransfer']/1024),
                'subdomains'  => $angebot['maxsubdomains'],
                'mailboxes'   => $mailboxes,
                'mailaddrs'   => $mailaddrs,
                'mailquota'   => (!isset($angebot['popmaxkb']) || ($angebot['popmaxkb'] <= 0)) ? -1 : (int)($angebot['popmaxkb']/1024),
                'cgi'         => $angebot['perl'],
                'php'         => $angebot['php'],
                'ssi'         => $angebot['ssi'],
                'databases'   => $angebot['maxmysql'],
                'ftpaccounts' => $angebot['maxftp'] == -1 ? -1 : ($angebot['maxftp'] + 1),      # +1, da Confixx hier die Anzahl *zusätzlicher* FTP-Accounts verwaltet
                'shellaccess' => $shellaccess,
                'cronjobs'    => $angebot['maxcronjobs'],
                'maxusers'    => 1
                );

    return($paket_data);
  } #_setHostingPlanAddData


  # ------------------------------------------------------
  # help_message();
  #
  # Hier wird die Hilfe zum Programmaufruf ausgegeben
  # ------------------------------------------------------
  function help_message() {
    echo <<<EOT
 _    _          ___           __ _     (R)
| |  (_)_ _____ / __|___ _ _  / _(_)__ _
| |__| \ V / -_) (__/ _ \ ' \|  _| / _` |
|____|_|\_/\___|\___\___/_||_|_| |_\__, |_____________________________________
                                   |___/
Verwendung: php cfximport.php -c | -h | --check
            php cfximport.php <kunde> [ <kunde> ... | --all ]
                                [ --webserver <server> ]
                                [ --mailserver <server> ]
                                [ --dbserver <server> ]

  <kunde> [ <kunde> ...] [ -a, --all ]
                    Angabe der Reseller bzw. Kunden, die importiert werden
                    sollen. Mit "-a" (oder "--all") werden alle Kunden eines
                    Resellers mit importiert.
  
  Optionen:
  -h, --help        Hilfe ausgeben
  -c, --config      Confixx-Konfiguration wird ausgelesen (aus
                    /root/confixx/confixx_main.conf, falls nicht vorhanden
                    werden die entsprechenden Daten abgefragt).
                    Anschliessend werden die Daten fuer den LiveConfig-Server
                    abgefragt, und in der Datei "cfximport.conf" gespeichert
  --check           Konfiguration pruefen (Verbindungsaufbau zur Confixx-
                    Datenbank und zur LiveConfig-SOAP-API testen)
  -i                Interaktiv: beim Anlegen eines neuen Kunden/Vertrags nach
                    dessen neuer Kunden-/Vertragsnummer fragen
  --webserver <server>
  --mailserver <server>
  --dbserver <server>
                    Mit diesen Optionen kann ein alternativer Zielserver
                    fuer Webspace, E-Mails oder Datenbanken angegeben
                    werden. Der Zielserver muss bereits in LiveConfig
                    eingerichtet und zur Verwaltung aktiviert sein
                    (benoetigt Business-Lizenz).
  --importlocked    Angabe, ob auch gesperrte Kunden importiert werden sollen
  --newreseller=<R> Importierte Kunden werden in LiveConfig dem angegebenen
                    Wiederverkäufer zugeordnet
  --importplans     Nur relevant wenn --newreseller verwendet wird:
                    Importiere auch die Hosting-Angebote des "Quell-Resellers"
                    in den Account des LiveConfig-Ziel-Resellers
  --htdocs=<PFAD>   setze <PFAD> als 'htdocs'-Verzeichnis (z.B. 'html')
  --kdnr            auch die Kundennummer aus Confixx importieren
  --fixmailquota    ueberbuchte Mailbox-Quota beruecksichtigen (die neue Mail-
                    Quota wird so angepasst, dass alle Postfaecher importiert
                    werden koennen)
  --verbose         Ausfuehrlichere Informationen waehrend des Imports ausgeben

ANLEITUNG UND NEUESTE VERSION: http://www.liveconfig.com/de/kb/5
______________________________________________________________________________
Copyright (c) 2009-2013 Keppler IT GmbH.             http://www.liveconfig.com

EOT;
# '
  }

  # --------------------------------------------------------------------------
  # Alternative (und brauchbare! ;) Implementierung von "getopts"
  # Quelle: http://php.net/manual/en/function.getopt.php
  # Autor: mbirth at webwriters dot de (24-May-2008 01:41)
  # --------------------------------------------------------------------------
  function parseParameters($noopt = array()) {
      $result = array();
      $params = $GLOBALS['argv'];
      // could use getopt() here (since PHP 5.3.0), but it doesn't work relyingly
      array_shift($params); // drop own filename (argv[0]) from array
      while (list($tmp, $p) = each($params)) {
          if ($p{0} == '-') {
              $pname = substr($p, 1);
              $value = true;
              if ($pname{0} == '-') {
                  // long-opt (--<param>)
                  $pname = substr($pname, 1);
                  if (strpos($p, '=') !== false) {
                      // value specified inline (--<param>=<value>)
                      list($pname, $value) = explode('=', substr($p, 2), 2);
                  }
              }
              // check if next parameter is a descriptor or a value
              $nextparm = current($params);
              if (!in_array($pname, $noopt) && $value === true && $nextparm !== false && $nextparm{0} != '-') list($tmp, $value) = each($params);
              $result[$pname] = $value;
          } else {
              // param doesn't belong to any option
              $result[] = $p;
          }
      }
      return $result;
  }


  /**
   * Token fuer SOAP-Aufruf erstellen
   *
   * @param string $fn Name der aufzurufenden SOAP-Funktion
   * @param string $cust ID des Kunden, unter dem der Aufruf erfolgen soll (optional)
   * @global array globale Konfiguration
   * @return array Authentifizierungstoken
   */
  function createToken($fn, $cust = NULL) {
    # Construct SOAP token:
    global $CONFIG;

    $ts = gmdate("Y-m-d") . "T" . gmdate("H:i:s") . ".000Z";
    $token = base64_encode(hash_hmac('sha1',
                                     'LiveConfig' . $CONFIG['user'] . $fn . $ts . $cust,
                                     $CONFIG['pass'],
                                     true
                                    )
                          );
    $auth = array('login'     => $CONFIG['user'],
                  'timestamp' => $ts,
                  'token'     => $token,
                  'customer'  => $cust);
    return($auth);
  }

  /**
   * Ausfuehrliche Fehlerinformationen nach SOAP-Exception ausgeben
   *
   * @global SoapClient SOAP-Client-Instanz
   */
  function _traceSoapException() {
    global $client;
    if ($client != NULL) {
      print "----------SOAP-Request:----------\n" . $client->__getLastRequest() . "\n";
      print "----------SOAP-Response:----------\n" . $client->__getLastResponse() . "\n";
    }
  }

  # --------------------------------------------------------------------------
  # _write_config
  #
  # Schreiben der Konfigurationsdaten von cfximport.php in eine eigene
  # Konfigurationsdatei (cfximport.conf)
  # --------------------------------------------------------------------------
  function _write_config($config, $file) {
    global $CONFIG;
    $handle = fopen($file, "w");
    # Inhalt in Config-Datei schreiben
    foreach (array('dbServer', 'dbUser', 'dbPw', 'dbDB', 'url', 'user', 'pass') as $key) {
      fwrite($handle, $key . "=" . $CONFIG[$key] . "\n");
    }
    fclose($handle);
  }

  # --------------------------------------------------------------------------
  # _read_config
  #
  # Konfiguration fuer cfximport.php aus Datei (cfximport.conf) auslesen
  # --------------------------------------------------------------------------
  function _read_config($file) {
    $vars = array ();
    $data = file($file, FILE_SKIP_EMPTY_LINES);
    if ($data == false) {
      print "Error while reading config file!\n";
      exit(1);
    } else {
      foreach($data as $line) {
        $line = trim($line);
        if(!strpos($line, "=")) {
          continue;
        }
        list($varname, $value) = explode("=", $line);
        $varname = trim($varname);
        $value = trim($value);
        $varname = preg_replace('/^\$/', "", $varname);
        $value = preg_replace('/;$/', "", $value);
        $value = preg_replace('/\'/', "", $value);
        $vars[$varname] = $value;
      }
    }

    return $vars;
  }

  # --------------------------------------------------------------------------
  # _get_config
  #
  # Alle Konfigurationsdaten via Konsole abfragen
  # --------------------------------------------------------------------------
  function _get_config($file) {

    $file = $file;
    $config = array ();

    print "Nachfolgend koennen Sie alle notwendigen Konfigurationsdaten manuell erfassen.\nZum Start druecken Sie bitte <Enter>:\n";
    while (1) {
        $start = trim(fgets(STDIN));
        if ($start == '') break;
    }

    # Abfrage der Daten fuer die Confixx-Datenbank
    # mysqlhost
    print "Zugangsdaten zur Confixx-Datenbank (MySQL):\n";
    print "Geben Sie bitte den Datenbank-Servernamen oder IP Adresse an (ggf. inklusive Port): ";
    $config['dbServer'] = trim(fgets(STDIN));
    # mysqluser
    print "Geben Sie bitte das Datenbank-Login an: ";
    $config['dbUser'] = trim(fgets(STDIN));
    # mysqlpwd
    print "Geben Sie bitte das Datenbank-Passwort an: ";
    $config['dbPw'] = trim(fgets(STDIN));
    # mysqldb
    print "Geben Sie bitte den Namen der Confixx-Datenbank an: ";
    $config['dbDB'] = trim(fgets(STDIN));

    return $config;
  }

  # --------------------------------------------------------------------------
  # _check_config
  #
  # Kontrolliert ob alle Daten richtig sind
  # --------------------------------------------------------------------------
  function _check_config($config, $config_file) {
  echo <<<EOT
 _    _          ___           __ _     (R)
| |  (_)_ _____ / __|___ _ _  / _(_)__ _
| |__| \ V / -_) (__/ _ \ ' \|  _| / _` |
|____|_|\_/\___|\___\___/_||_|_| |_\__, |_____________________________________
                                   |___/
Bitte ueberpruefen Sie Ihre Angaben:

   Datenbank-Host: ${config['dbServer']}
  Datenbank-Login: ${config['dbUser']}
   Datenbank-Name: ${config['dbDB']}

  LiveConfig SOAP API: ${config['url']}
           SOAP-Login: ${config['user']}


EOT;
# '
    while (1) {      
      print "Sind diese Angaben richtig?\nBitte bestaetigen Sie oder kehren Sie zur Konfiguration zurueck! ['j,y,yes,n,no'] <y>";
      $confirm = trim(fgets(STDIN));
      if ($confirm == ''||$confirm == 'j'||$confirm == 'y' ||$confirm == 'yes') {
        $finish_config = 1;
        print "OK, dann koennen Sie  mit dem Import beginnen!\n";
        break;
      } elseif ($confirm == 'n'||$confirm == 'no') {
        print "OK, geben Sie die Daten erneut ein!\n";
        break;
      }
    }
    if ($finish_config == 1) {
      # Schreiben der Configdaten in die Config-Datei
      _write_config($config, $config_file);
    }
  }


  /**
   * Einlesen der Konfigurationsdaten fuer den LiveConfig-Server
   *
   * @global array globale Konfiguration
   */
  function _get_LC_config() {
    global $CONFIG;

    # Abfrage der Daten fuer den SOAP-Import
    # wsdl_url
    print "Geben Sie bitte den LiveConfig Server an (localhost oder IP): ";
    $lc_server = trim(fgets(STDIN));
    print "Geben Sie bitte den Port auf dem LiveConfig laeuft an: ";
    $lc_port = trim(fgets(STDIN));
    if ($lc_port == 443 || $lc_port == 8443) {
       $CONFIG['url'] = "https://" . $lc_server . ":" . $lc_port . "/liveconfig/soap";
    } else {
       $CONFIG['url'] = "http://" . $lc_server . ":" . $lc_port . "/liveconfig/soap";
    }
    
    # soap_login
    print "Geben Sie bitte das SOAP-Login an: ";
    $CONFIG['user'] = trim(fgets(STDIN));
    # soap_password
    print "Geben Sie bitte das SOAP-Passwort an: ";
    $CONFIG['pass'] = trim(fgets(STDIN));
  }

  # <EOF>---------------------------------------------------------------------
?>