<?php

include "settings/config.php";

function set_production_done($ordernumber) {
    try {
        $time = time();
        $sql = "UPDATE ds SET produktionAbgeschlossen = from_unixtime($time) WHERE barcode = $ordernumber;";
        $result = mysql_query($sql);
        if(!$result) {
            throw new Exception("Fehler beim Eintragen des Produktionsabschlusses");
        }

        return true;
    } catch(Exception $e) {
        return false;
    }
}

$script_id = 92;
$userid = 619;

try {
    if(!checkScriptcontrol($script_id)) {
        exit("Der letzte Aufruf arbeitet noch!");
    }
    setScriptControlArbeitet($script_id);

    $partner_ids_to_skip = array();

    $sql = "SELECT ID from partner WHERE bekommtDVDArtikel = 0;";
    $result = mysql_query($sql);
    if(!$result) {
        throw new Exception("Konnte die Partner-IDs nicht abfragen");
    }

    while ($row = mysql_fetch_array($result)) {
        $partner_ids_to_skip[] = $row[0];
    }

    $sql = "SELECT NAS_IP, DVD_LOG_PATH FROM autohotfoldereinstellungen;";
    $result = mysql_query($sql);
    if(!$result) {
        throw new Exception("Konnte die NAS-IP nicht abfragen");
    }

    if(mysql_num_rows($result) != 1) {
        throw new Exception("Konnte die NAS-IP nicht eindeutig feststellen");
    }

    $row = mysql_fetch_assoc($result);
    if(!$row) {
        throw new Exception("Konnte die NAS-IP nicht auslesen");
    }

    $nas_IP = $row["NAS_IP"];
    $log_path = $row["DVD_LOG_PATH"];
    $dvd_log_path = str_replace("/share/CACHEDEV1_DATA", "", $log_path);
    $dvd_log_path = str_replace("/", "\\", $dvd_log_path);
    $dvd_log_path = "\\\\".$nas_IP.$dvd_log_path;

    if(!is_dir($dvd_log_path)) {
        throw new Exception("Ordner $dvd_log_path für die LOG-Files nicht gefunden");
    }

    $files = glob($dvd_log_path."/dvd-log-*.txt");

    foreach ($files as $file) {
        $content = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($content === false) {
            continue;
        }

        if (count($content) !== 1) {
            continue;
        }

        $delete = false;
        $line = trim($content[0]);

        if (preg_match('/^(\d{10}) (\d+)$/', trim($line), $matches)) {
            $ordernumber = $matches[1];
            $dvd_amount = $matches[2];
            $time = time();

            $sql = "SELECT partnerID, rechnungsdatum FROM ds WHERE barcode = $ordernumber;";
            $result = mysql_query($sql);
            if(!$result) {
                throw new Exception("Konnte die Partner-ID nicht abfragen");
            }

            if (mysql_num_rows($result) != 1) {
                throw new Exception("Der Auftrag $ordernumber hat keinen DS-Eintrag");
            }

            $row = mysql_fetch_array($result);
            if(!$row) {
                throw new Exception("Fehler beim Auslesen der Partner-ID");
            }

            $partner_id = intval($row["partnerID"]);
            $rechnungsdatum = $row["rechnungsdatum"];

            if(in_array($partner_id, $partner_ids_to_skip)) {
                $delete = true;
            } else {
                if($rechnungsdatum != "") {
                    throw new Exception("Der Auftrag $ordernumber ist bereits abgerechnet");
                }

                $sql = "
				INSERT INTO workinglog 
					(mitarbeiterid, auftragsBarcode, start, ende) 
				VALUES 
					($userid, '$ordernumber', from_unixtime($time), from_unixtime($time+1));";

                $result = mysql_query($sql);
                if(!$result) {
                    throw new Exception("Konnte den Eintrag für die DVDs nicht machen");
                }

                $sql = "SELECT LAST_INSERT_ID() as lid;";

                $result = mysql_query($sql);
                if(!$result) {
                    throw new Exception("Fehler beim Eintragen der DVDs");
                }

                $row = mysql_fetch_array($result);
                if(!$row) {
                    throw new Exception("Fehler beim Eintragen der DVDs");
                }

                $last_id = $row["lid"];

                $sql = "SELECT soll FROM artikelarbeitsschritte WHERE artikel = 'dvd' AND arbeitsschritt = 4";
                $result = querySQL($sql, 1, 1, __LINE__, __FILE__, 1);
                $row = mysql_fetch_array($result);
                if(!$row) {
                    throw new Exception("Konnte die Abfrage nicht auslesen");
                }

                $soll = $row["soll"];
                $sql = "
				INSERT INTO workinglogpositions 
					(workingLogID, artNr, amount, bemerkung, tagesleistung, arbeitsschritt, anzahlUnits, Vorgabe) 
				VALUES 
					($last_id, 'dvd', $dvd_amount, 'Durch automaitschen DVD-Hotfolder eingetragen', 1, 4, $dvd_amount, $soll);";
                $result = mysql_query($sql);
                if(!$result) {
                    throw new Exception("Fehler beim Eintragen der Arbeitsschritte");
                }

                // Bereits produzierte Anzahl DVDs bestimmen
                $sql = "SELECT ID from workinglog WHERE auftragsBarcode = '$ordernumber';";
                $result = mysql_query($sql);
                $working_log_ids = array();
                while ($row = mysql_fetch_assoc($result)) {
                    $working_log_ids[] = $row["ID"];
                }

                $current_dvd_amount = 0;
                foreach ($working_log_ids as $working_log_id) {
                    $sql = "SELECT amount FROM workinglogpositions WHERE workingLogID = $working_log_id AND artNr = 'dvd';";
                    $result = mysql_query($sql);
                    if(!$result) {
                        throw new Exception("Fehler beim Auslesen der DVD-Menge");
                    }

                    if(mysql_num_rows($result) > 1) {
                        throw new Exception("Fehler beim Auslesen der DVD-Menge");
                    }

                    if(mysql_num_rows($result) == 0) {
                        continue;
                    }

                    $row = mysql_fetch_assoc($result);
                    if(!$row) {
                        throw new Exception("Fehler beim Auslesen der DVD-Menge");
                    }

                    $current_dvd_amount += $row["amount"];
                }
                $dvd_amount += $current_dvd_amount;

                $sql = "UPDATE orderpos SET amount = $dvd_amount WHERE barcode = $ordernumber AND artNr = 'dvd';";
                $result = mysql_query($sql);
                if(!$result) {
                    throw new Exception("Konnte die Auftragspostion für die DVDs nicht anpassen");
                }

                if (mysql_affected_rows() == 0) {
                    $sql = "INSERT INTO orderpos (artNr, barcode, amount, time) VALUES ('dvd', '$ordernumber', $dvd_amount, unix_timestamp());";
                    $result = mysql_query($sql);
                    if(!$result) {
                        throw new Exception("Fehler beim Anpassen der Auftragsposition");
                    }
                }

                $result = gibSollIstAbgleichDerProduktionEinesAuftrages($ordernumber, true);
                if($result) {
                    $erg = set_production_done($ordernumber);
                    if(!$erg) {
                        throw new Exception("Konnte die Produktion nicht abschließen");
                    }

                    $client_ip = $_SERVER["REMOTE_ADDR"];

                    $sql = "INSERT INTO log (UserID, barcode, time, text, manuell, IP, typ) VALUES ('$userid', '$ordernumber', unix_timestamp(), 'Die Produktion wurde vom automatischen DVD-Hotfolder abgeschlossen.', 0, '$client_ip', 0);";
                    $result = mysql_query($sql);
                    if(!$result) {
                        throw new Exception("Fehler beim Erstellen des History-Eintrags");
                    }
                }

                echo "Corrected $ordernumber with $dvd_amount DVDs";
                $delete = true;
            }
        }

        if($delete) {
            if(!unlink($file)) {
                throw new Exception("Konnte die Datei $file nach der Eintragung nicht löschen");
            } else {
                echo "Deleted $file";
            }
        }
    }

    setScriptControlFertig($script_id);
}catch(Exception $e) {
    mailAnIT("Fehler auf höchster Ebene beim korrigieren der DVD-Einträge vom Hotfolder in hotfolderDVDCorrect.php: " . $e->getMessage() . "<br/>\n", "Fehler beim Korrigieren der DVD-Einträge!");
    setScriptControlFertig($script_id);
    echo $e->getMessage();
}