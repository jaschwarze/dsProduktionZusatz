<?php

include "settings/config.php";

function is_valid_picture_file($file_name) {
    if(is_file($file_name) && (strstr($file_name, ".png") !== false || strstr($file_name, ".PNG") !== false
            || strstr($file_name, ".jpg") !== false || strstr($file_name, ".jpeg") !== false
            || strstr($file_name, ".JPG") !== false || strstr($file_name, ".JPEG") !== false)) {
        return true;
    }

    return false;
}

function get_directory_infos($path) {
    $path = realpath($path);
    $bytes_total = 0;
    $image_counter = 0;
    $file_counter = 0;
    $image_paths = array();

    if($path !== false && $path != "" && file_exists($path)) {
        foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path,FilesystemIterator::SKIP_DOTS)) as $object) {
            $bytes_total += $object->getSize();
            $file_counter++;

            if(is_valid_picture_file($object)) {
                $image_counter++;
                $image_paths[] = $object->getRealPath();
            }
        }
    }

    return array($bytes_total, $image_counter, $image_paths, $file_counter);
}

function check_directory_changing($dir_path) {
    $dir_infos_old = get_directory_infos($dir_path);
    sleep(3);
    $dir_infos_new = get_directory_infos($dir_path);

    if($dir_infos_old[0] != $dir_infos_new[0]) {
        return true;
    }

    if($dir_infos_old[1] != $dir_infos_new[1]) {
        return true;
    }

    if($dir_infos_old[3] != $dir_infos_new[3]) {
        return true;
    }

    return false;
}

function get_workflow_for_order($ordernumber, $abteilung) {
    try {
        $sql = "SELECT partnerID FROM ds WHERE barcode = $ordernumber;";
        $result = mysql_query($sql);
        if(!$result) {
            throw new Exception("Konnte die Partner-ID nicht abfragen");
        }
        $row = mysql_fetch_row($result);
        if(!$row) {
            throw new Exception("Konnte die Partner-ID nicht auslesen");
        }
        $partner_id = $row[0];
        $sql = "SELECT id FROM produktionsworkflows WHERE (partnerID = $partner_id OR partnerID = 0) AND abteilung = '$abteilung' ORDER BY partnerID DESC;";
        $result = mysql_query($sql);
        if(!$result) {
            throw new Exception("Konnte die Produktionsworkflows nicht abfragen");
        }

        $workflow_ids = array();
        while ($row = mysql_fetch_row($result)) {
            $workflow_ids[] = $row[0];
        }

        if(!count($workflow_ids)) {
            throw new Exception("Für diesen Partner wurde kein Workflow gefunden");
        }

        $sql = "SELECT * FROM orderpos WHERE barcode = $ordernumber AND stornoDate = '';";
        $result = mysql_query($sql);
        if(!$result) {
            throw new Exception("Konnte die Auftragspositionen nicht abfragen");
        }

        $orderpos = array();
        while ($row = mysql_fetch_assoc($result)) {
            $orderpos[] = $row["artNr"];
        }

        $sql = "SELECT * FROM produktionsworkflowsartikelpositionen";
        $result = mysql_query($sql);
        if(!$result) {
            throw new Exception("Konnte die Workflow-Artikelpositionen nicht abfragen");
        }

        $workflow_positions = array();
        foreach($workflow_ids as $workflow_id) {
            $sql = "SELECT * FROM produktionsworkflowsartikelpositionen WHERE workflowID = $workflow_id;";
            $result = mysql_query($sql);
            if(!$result) {
                throw new Exception("Konnte die Artikelpositionen für einen Workflow nicht abfragen");
            }
            while ($row = mysql_fetch_assoc($result)) {
                $workflow_positions[$workflow_id][] = array(
                    "artNr" => $row["artNr"],
                    "spezialPosition" => $row["spezialPosition"]
                );
            }
        }

        $workflow_candidates = array();

        foreach($workflow_positions as $workflow_id => $workflow_position_array) {
            $normal_positions = array();
            $spezial_positions = array();

            foreach($workflow_position_array as $position) {
                if($position["spezialPosition"] == 1) {
                    $spezial_positions[] = $position["artNr"];
                } else {
                    $normal_positions[] = $position["artNr"];
                }
            }

            if(count($spezial_positions) == 0) {
                $contains_all_special = false;
            } else {
                $contains_all_special = !array_diff($spezial_positions, $orderpos);
            }

            $has_normal = count(array_intersect($normal_positions, $orderpos)) > 0;

            if($contains_all_special && $has_normal) {
                return $workflow_id;
            }

            if($has_normal) {
                $workflow_candidates[] = $workflow_id;
            }
        }

        // Priorisierung: Zuerst partnerspezifische Workflows (partnerID != 0) zurückgeben
        if(count($workflow_candidates) >= 1) {
            foreach($workflow_candidates as $candidate_id) {
                $sql = "SELECT partnerID FROM produktionsworkflows WHERE id = $candidate_id;";
                $result = mysql_query($sql);
                if($result && $row = mysql_fetch_row($result)) {
                    $candidate_partner_id = $row[0];

                    if($candidate_partner_id != 0) {
                        return $candidate_id;
                    }
                }
            }

            // Falls keine partnerspezifischen Workflows gefunden wurden, gib den ersten allgemeinen Workflow zurück
            return $workflow_candidates[0];
        }

        return false;
    } catch(Exception $e) {
        return false;
    }
}

function recurse_copy($src, $dst, $only_copy = false) {
    if (empty($src)) {
        return false;
    }

    $moved_files = array();
    $moved_dirs = array();

    $result = recurse_move_with_tracking($src, $dst, $only_copy, $moved_files, $moved_dirs);

    if (!$result) {
        rollback_moves($moved_files, $moved_dirs, $dst);
        return false;
    }

    if(!$only_copy) {
        rrmdir($src);
    }

    return true;
}

function recurse_move_with_tracking($src, $dst, $only_copy, &$moved_files, &$moved_dirs) {
    $dir = opendir($src);
    if (!$dir) {
        return false;
    }

    if (!@mkdir($dst) && !is_dir($dst)) {
        closedir($dir);
        return false;
    }

    $moved_dirs[] = array("src" => $src, "dst" => $dst);

    while (false !== ($file = readdir($dir))) {
        if ($file !== "." && $file !== "..") {
            $srcFile = $src . "/" . $file;
            $dstFile = $dst . "/" . $file;

            if (is_dir($srcFile)) {
                if (!recurse_move_with_tracking($srcFile, $dstFile, $only_copy, $moved_files, $moved_dirs)) {
                    closedir($dir);
                    return false;
                }
            } else {
                if ($only_copy) {
                    if (!copy($srcFile, $dstFile)) {
                        closedir($dir);
                        return false;
                    }
                } else {
                    if (!rename($srcFile, $dstFile)) {
                        closedir($dir);
                        return false;
                    }

                    $moved_files[] = array("src" => $srcFile, "dst" => $dstFile);
                }
            }
        }
    }

    closedir($dir);
    return true;
}

function rollback_moves($moved_files, $moved_dirs, $original_dst) {
    for ($i = count($moved_files) - 1; $i >= 0; $i--) {
        $file = $moved_files[$i];
        @rename($file["dst"], $file["src"]);
    }

    for ($i = count($moved_dirs) - 1; $i >= 0; $i--) {
        $dir = $moved_dirs[$i];
        @rmdir($dir["dst"]);
    }

    if (is_dir($original_dst)) {
        rrmdir($original_dst);
    }
}

function rrmdir($dir) {
    if (!is_dir($dir)) {
        return false;
    }

    $files = array_diff(scandir($dir), array(".", ".."));
    foreach ($files as $file) {
        $filePath = $dir . "/" . $file;
        if (is_dir($filePath)) {
            rrmdir($filePath);
        } else {
            unlink($filePath);
        }
    }

    return rmdir($dir);
}

$script_id = 93;

try {
    if(!checkScriptcontrol($script_id)) {
        exit("Der letzte Aufruf arbeitet noch!");
    }
    setScriptControlArbeitet($script_id);

    $sql = "SELECT NAS_IP, FILMOMAT_IP FROM produktionsworkflowseinstellungen;";
    $result = mysql_query($sql);
    if(!$result) {
        throw new Exception("Konnte die NAS- und Filmomat-IP nicht abfragen");
    }

    if(mysql_num_rows($result) != 1) {
        throw new Exception("Konnte die NAS- und Filmomat-IP nicht eindeutig feststellen");
    }

    $row = mysql_fetch_assoc($result);
    if(!$row) {
        throw new Exception("Konnte die NAS- und Filmomat-IP nicht auslesen");
    }

    $nas_IP = $row["NAS_IP"];
    $filmomat_ip = $row["FILMOMAT_IP"];

    $filmomat_output_path = "\\\\$filmomat_ip\\Hotfolder\\04_Ausgang";
    if(!is_dir($filmomat_output_path)) {
        throw new Exception("Filmomat-Ausgangsordner $filmomat_output_path nicht gefunden");
    }

    $filomat_output_handle = opendir($filmomat_output_path);
    if(!$filomat_output_handle) {
        throw new Exception("Filmomat-Ausgangsordner $filmomat_output_path konnte nicht geöffnet werden");
    }

    while(false !== ($current_entry = readdir($filomat_output_handle))) {
        $current_entry_path = $filmomat_output_path . "\\" . $current_entry;

        if ($current_entry == "." || $current_entry == ".." || !is_dir($current_entry_path)) {
            continue;
        }

        if(check_directory_changing($current_entry_path)) {
            continue;
        }

        $parts = explode("-", $current_entry);
        if(count($parts) < 7) {
            continue;
        }

        //Auftragsordner müssen mit einer 10-stelligen Nummer enden
        if(!preg_match("/-\d{10}$/", $current_entry)) {
            continue;
        }

        $ordernumber = end($parts);

        $workflow_id = get_workflow_for_order($ordernumber, "negativ");
        if(!$workflow_id) {
            throw new Exception("Konnte den Workflow für Ordner $current_entry nicht bestimmen");
        }

        $sql = "SELECT batchID FROM produktionsworkflows WHERE id = $workflow_id;";
        $result = mysql_query($sql);
        if(!$result) {
            throw new Exception("Konnte die Batch-ID für Auftrag $ordernumber nicht abfragen");
        }
        $row = mysql_fetch_assoc($result);
        if(!$row) {
            throw new Exception("Konnte die Batch-ID für Auftrag $ordernumber nicht auslesen");
        }a

        $batch_id = $row["batchID"];
        $queue_path = "\\\\$nas_IP\Diaproduktion\Capturing\Warteschlange_Batch_$batch_id"."_01";
        $queue_order_path = $queue_path."\\".$current_entry;

        if(!recurse_copy($current_entry_path, $queue_order_path)) {
            throw new Exception("Konnte Ordner $current_entry_path nicht in Warteschlange verschieben");
        }
    }

    closedir($filomat_output_handle);

    setScriptControlFertig($script_id);
}catch(Exception $e) {
    mailAnIT("Fehler auf höchster Ebene beim korrigieren der DVD-Einträge vom Hotfolder in hotfolderDVDCorrect.php: " . $e->getMessage() . "<br/>\n", "Fehler beim Korrigieren der DVD-Einträge!");
    setScriptControlFertig($script_id);
    echo $e->getMessage();
}