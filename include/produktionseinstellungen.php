<?php

if(!isset($included)) {
    $url = explode("/",$_SERVER["REQUEST_URI"]);
    $url = $url[count($url)-1];
    if($url!="index.php")
        die("Fehler");
}

checkLogin();
checkRight(21);

echo "<a href='?section=settingsOverview'>zurück</a><br/><br/>\n";

$action = isset($_POST["action"]) ? $_POST["action"] : (isset($_GET["action"]) ? $_GET["action"] : "");
$message = "";

switch ($action) {
    case "update_hotfolder_settings":
        $message = updateHotfolderSettings();
        break;
    case "update_general_settings":
        $message = updateGeneralSettings();
        break;
    case "add_workflow":
        $message = addWorkflow();
        break;
    case "update_workflow":
        $message = updateWorkflow();
        break;
    case "delete_workflow":
        $message = deleteWorkflow();
        break;
}

function getHotfolderSettings() {
    $query = "SELECT * FROM autohotfoldereinstellungen LIMIT 1";
    $result = mysql_query($query);
    return mysql_fetch_assoc($result);
}

function getGeneralSettings() {
    $query = "SELECT * FROM produktionsworkflowseinstellungen LIMIT 1";
    $result = mysql_query($query);
    return mysql_fetch_assoc($result);
}

function updateHotfolderSettings() {
    $fields = array(
            "NAS_IP", "INPUT_PATH", "OUTPUT_PATH", "WORKING_PATH", "ERROR_PATH",
            "WAITING_PATH", "CAPTURING_PATH", "BATCHEN_PATH", "FINISHED_PATH",
            "RAW_PATH", "PRODUCTION_PATH", "REMOTE_FINISHED_PATH", "DVD_LOG_PATH",
            "BURNER_NAME", "BURNER_PATH", "DVD_SPACE_FOR_USE", "DVD_MAX_IMAGES"
    );

    $setParts = array();
    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            $value = mysql_real_escape_string($_POST[$field]);
            $setParts[] = "`$field` = '$value'";
        }
    }

    if (!empty($setParts)) {
        $query = "UPDATE autohotfoldereinstellungen SET " . implode(', ', $setParts) . " LIMIT 1";
        if (mysql_query($query)) {
            return "Einstellungen für automatischen Hotfolder erfolgreich aktualisiert.";
        } else {
            return "Fehler beim Aktualisieren: " . mysql_error();
        }
    }
    return "Keine Änderungen vorgenommen.";
}

/**
 * @throws Exception
 */
function updateGeneralSettings() {
    $fields = array(
            "NAS_IP", "FILMOMAT_IP"
    );

    $setParts = array();
    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            $value = mysql_real_escape_string($_POST[$field]);
            $setParts[] = "`$field` = '$value'";
        }
    }

    if (!empty($setParts)) {
        $query = "UPDATE produktionsworkflowseinstellungen SET " . implode(', ', $setParts) . " LIMIT 1";
        if (mysql_query($query)) {
            $success_message = "Allgemeine Produktionseinstellungen erfolgreich aktualisiert.";

            // Partner DVD-Artikel Einstellungen aktualisieren
            if (isset($_POST["partnersOhneDVD"]) && is_array($_POST["partnersOhneDVD"])) {
                $partnersOhneDVD = $_POST["partnersOhneDVD"];

                // Alle Partner zurücksetzen auf bekommtDVDArtikel = 1
                $resetQuery = "UPDATE partner SET bekommtDVDArtikel = 1";
                $result = mysql_query($resetQuery);
                if(!$result) {
                    throw new Exception("Fehler beim Aktualisieren: " . mysql_error());
                }

                // Ausgewählte Partner auf bekommtDVDArtikel = 0 setzen
                if (!empty($partnersOhneDVD)) {
                    $partnerIds = array();
                    foreach ($partnersOhneDVD as $partnerId) {
                        $partnerIds[] = intval($partnerId);
                    }
                    if (!empty($partnerIds)) {
                        $updateQuery = "UPDATE partner SET bekommtDVDArtikel = 0 WHERE ID IN (" . implode(',', $partnerIds) . ")";
                        $result = mysql_query($updateQuery);
                        if(!$result) {
                            throw new Exception("Fehler beim Aktualisieren: " . mysql_error());
                        }
                    }
                }

                $success_message .= " Partner DVD-Artikel Einstellungen wurden ebenfalls aktualisiert.";
            }

            return $success_message;
        } else {
            return "Fehler beim Aktualisieren: " . mysql_error();
        }
    }
    return "Keine Änderungen vorgenommen.";
}

function getWorkflows() {
    $query = "SELECT * FROM produktionsworkflows ORDER BY name";
    $result = mysql_query($query);
    $workflows = array();
    while ($row = mysql_fetch_assoc($result)) {
        $workflows[] = $row;
    }
    return $workflows;
}

function getAllPartners() {
    $query = "SELECT ID, name, bekommtDVDArtikel FROM partner WHERE aktiv = 1 ORDER BY name";
    $result = mysql_query($query);
    $partners = array();
    while ($row = mysql_fetch_assoc($result)) {
        $partners[] = $row;
    }
    return $partners;
}

function getPartnersOhneDVD() {
    $query = "SELECT ID, name FROM partner WHERE bekommtDVDArtikel = 0 AND aktiv = 1 ORDER BY name";
    $result = mysql_query($query);
    $partners = array();
    while ($row = mysql_fetch_assoc($result)) {
        $partners[] = $row;
    }
    return $partners;
}

function addWorkflow() {
    $name = mysql_real_escape_string($_POST["name"]);
    $partnerID = intval($_POST["partnerID"]);
    $arbeitsschrittID = intval($_POST["arbeitsschrittID"]);
    $bekommtStaffel = isset($_POST["bekommtStaffel"]) ? 1 : 0;
    $bekommtUnterordner = isset($_POST["bekommtUnterordner"]) ? 1 : 0;
    $bekommtDigiSonderartikel = isset($_POST["bekommtDigiSonderartikel"]) ? 1 : 0;
    $batchID = mysql_real_escape_string($_POST["batchID"]);
    $zielpfad = mysql_real_escape_string($_POST["zielpfad"]);
    $abteilung = mysql_real_escape_string($_POST["abteilung"]);
    $bekommtViesus = isset($_POST["bekommtViesus"]) ? 1 : 0;
    $bekommtMoebius = isset($_POST["bekommtMoebius"]) ? 1 : 0;
    $zielSpeichermedium = mysql_real_escape_string($_POST["zielSpeichermedium"]);

    $query = "INSERT INTO produktionsworkflows (name, partnerID, arbeitsschrittID, bekommtStaffel, bekommtUnterordner, bekommtDigiSonderartikel, batchID, zielpfad, abteilung, bekommtViesus, bekommtMoebius, zielSpeichermedium) 
              VALUES ('$name', $partnerID, $arbeitsschrittID, $bekommtStaffel, $bekommtUnterordner, $bekommtDigiSonderartikel, '$batchID', '$zielpfad', '$abteilung', $bekommtViesus, $bekommtMoebius, '$zielSpeichermedium')";

    if (mysql_query($query)) {
        $workflowID = mysql_insert_id();

        // Artikel hinzufügen falls vorhanden
        if (isset($_POST["articles"]) && is_array($_POST["articles"])) {
            foreach ($_POST["articles"] as $article) {
                $artNr = mysql_real_escape_string($article["artNr"]);
                $spezialPosition = intval($article["spezialPosition"]);
                $zusatzPosition = intval($article["zusatzPosition"]);
                $staffelStart = intval($article["staffelStart"]);
                $staffelEnde = intval($article["staffelEnde"]);
                $eintrageModus = ($zusatzPosition == 1 && isset($article["eintrageModus"])) ?
                        mysql_real_escape_string($article["eintrageModus"]) : "anzahl";

                $articleQuery = "INSERT INTO produktionsworkflowsartikelpositionen (workflowID, artNr, spezialPosition, zusatzPosition, staffelStart, staffelEnde, eintrageModus) 
                                VALUES ($workflowID, '$artNr', $spezialPosition, $zusatzPosition, $staffelStart, $staffelEnde, '$eintrageModus')";
                mysql_query($articleQuery);
            }
        }

        return "Workflow erfolgreich erstellt.";
    } else {
        return "Fehler beim Erstellen: " . mysql_error();
    }
}

function updateWorkflow() {
    $id = intval($_POST["id"]);
    $name = mysql_real_escape_string($_POST["name"]);
    $partnerID = intval($_POST["partnerID"]);
    $arbeitsschrittID = intval($_POST["arbeitsschrittID"]);
    $bekommtStaffel = isset($_POST["bekommtStaffel"]) ? 1 : 0;
    $bekommtUnterordner = isset($_POST["bekommtUnterordner"]) ? 1 : 0;
    $bekommtDigiSonderartikel = isset($_POST["bekommtDigiSonderartikel"]) ? 1 : 0;
    $batchID = mysql_real_escape_string($_POST["batchID"]);
    $zielpfad = mysql_real_escape_string($_POST["zielpfad"]);
    $abteilung = mysql_real_escape_string($_POST["abteilung"]);
    $bekommtViesus = isset($_POST["bekommtViesus"]) ? 1 : 0;
    $bekommtMoebius = isset($_POST["bekommtMoebius"]) ? 1 : 0;
    $zielSpeichermedium = mysql_real_escape_string($_POST["zielSpeichermedium"]);

    $query = "UPDATE produktionsworkflows SET 
              name = '$name', 
              partnerID = $partnerID, 
              arbeitsschrittID = $arbeitsschrittID, 
              bekommtStaffel = $bekommtStaffel, 
			  bekommtUnterordner = $bekommtUnterordner,
			  bekommtDigiSonderartikel = $bekommtDigiSonderartikel, 
              batchID = '$batchID', 
              zielpfad = '$zielpfad', 
              abteilung = '$abteilung', 
              bekommtViesus = $bekommtViesus, 
              bekommtMoebius = $bekommtMoebius, 
              zielSpeichermedium = '$zielSpeichermedium' 
              WHERE id = $id";

    if (mysql_query($query)) {
        // Alle bisherigen Artikel löschen
        $deleteQuery = "DELETE FROM produktionsworkflowsartikelpositionen WHERE workflowID = $id";
        mysql_query($deleteQuery);

        // Neue Artikel hinzufügen
        if (isset($_POST["articles"]) && is_array($_POST["articles"])) {
            foreach ($_POST["articles"] as $article) {
                $artNr = mysql_real_escape_string($article["artNr"]);
                $spezialPosition = intval($article["spezialPosition"]);
                $zusatzPosition = intval($article["zusatzPosition"]);
                $staffelStart = intval($article["staffelStart"]);
                $staffelEnde = intval($article["staffelEnde"]);
                $eintrageModus = ($zusatzPosition == 1 && isset($article["eintrageModus"])) ?
                        mysql_real_escape_string($article["eintrageModus"]) : "anzahl";

                $articleQuery = "INSERT INTO produktionsworkflowsartikelpositionen (workflowID, artNr, spezialPosition, zusatzPosition, staffelStart, staffelEnde, eintrageModus) 
                                VALUES ($id, '$artNr', $spezialPosition, $zusatzPosition, $staffelStart, $staffelEnde, '$eintrageModus')";
                mysql_query($articleQuery);
            }
        }

        return "Workflow $name erfolgreich aktualisiert.";
    } else {
        return "Fehler beim Aktualisieren: " . mysql_error();
    }
}

function deleteWorkflow() {
    $id = intval($_POST["id"]);

    $query = "DELETE FROM produktionsworkflowsartikelpositionen WHERE workflowID = $id";
    mysql_query($query);

    $query = "DELETE FROM produktionsworkflows WHERE id = $id";
    if (mysql_query($query)) {
        return "Workflow erfolgreich gelöscht.";
    } else {
        return "Fehler beim Löschen: " . mysql_error();
    }
}

function getArticles() {
    $query = "SELECT artNr, title FROM articles ORDER BY artNr";
    $result = mysql_query($query);
    $articles = array();
    while ($row = mysql_fetch_assoc($result)) {
        $articles[] = $row;
    }
    return $articles;
}

$hotfolder_settings = getHotfolderSettings();
$general_settings = getGeneralSettings();
$workflows = getWorkflows();
$articles = getArticles();
$all_partners = getAllPartners();
$partners_ohne_dvd = getPartnersOhneDVD();
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produktionsworkflow Einstellungen</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .section { border: 1px solid #ccc; margin: 20px 0; padding: 20px; border-radius: 5px; }
        .section h2 { margin-top: 0; color: #333; }
        .form-group { margin: 10px 0; }
        .form-group label { display: inline-block; width: 200px; font-weight: bold; }
        .form-group input, .form-group select, .form-group textarea { width: 450px; padding: 5px; }
        .checkbox-group { margin: 10px 0; }
        .checkbox-group label { width: auto; margin-right: 20px; }
        .radio-group { margin: 10px 0; }
        .radio-group label { width: auto; margin-right: 20px; }
        .btn { background: #007cba; color: white; padding: 10px 15px; border: none; border-radius: 3px; cursor: pointer; margin: 5px; text-decoration: none; display: inline-block; }
        .btn:hover { background: #005a87; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        .btn:disabled { cursor: not-allowed !important; background-color: #6c757d !important; }
        .btn:disabled:hover { background-color: #6c757d !important; }
        .message { padding: 10px; margin: 10px 0; border-radius: 3px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .workflow-list { list-style: none; padding: 0; }
        .workflow-item { background: #f8f9fa; margin: 5px 0; padding: 10px; border-radius: 3px; }
        .article-list { margin: 10px 0; }
        .article-item { background: #e9ecef; margin: 3px 0; padding: 8px; border-radius: 3px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 5px; text-align: center; }
        th { background-color: #f2f2f2; }

        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4); }
        .modal-content { background-color: #fefefe; margin: 5% auto; padding: 0; border: none; border-radius: 8px; width: 80%; max-width: 1000px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .modal-header { padding: 20px; background-color: #007cba; color: white; border-radius: 8px 8px 0 0; }
        .modal-header h3 { margin: 0; }
        .modal-body { padding: 20px; max-height: 70vh; overflow-y: auto; }
        .modal-footer { padding: 15px 20px; border-top: 1px solid #dee2e6; text-align: right; }
        .close { color: white; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close:hover { opacity: 0.7; }

        .tab-container { margin-bottom: 20px; }
        .tab-buttons { border-bottom: 1px solid #ddd; margin-bottom: 20px; }
        .tab-button { background: none; border: none; padding: 10px 20px; cursor: pointer; border-bottom: 2px solid transparent; }
        .tab-button.active { border-bottom-color: #007cba; color: #007cba; font-weight: bold; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        .workflow-articles-section { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; }

        input:disabled, select:disabled {
            background-color: #f5f5f5;
            color: #6c757d;
            cursor: not-allowed;
        }

        .position-radio-group {
            display: flex;
            gap: 15px;
            margin: 10px 0;
            flex-wrap: wrap;
        }

        .position-radio-group label {
            display: flex;
            align-items: center;
            gap: 5px;
            width: auto;
            white-space: nowrap;
            margin: 0;
        }

        .zusatz-positions-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }

        .position-radio-group input[type="radio"] {
            margin: 0;
            width: auto;
        }

        .zusatz-positions-section h4 {
            color: #666;
        }

        .partner-dvd-section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }

        .partner-selection-container {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            background-color: #f9f9f9;
            margin: 10px 0;
        }

        .partner-lists {
            display: flex;
            gap: 20px;
            margin-top: 15px;
        }

        .partner-list {
            flex: 1;
            border: 1px solid #ccc;
            border-radius: 5px;
            background-color: white;
        }

        .partner-list h4 {
            margin: 0;
            padding: 10px;
            background-color: #f8f9fa;
            border-bottom: 1px solid #ccc;
            border-radius: 5px 5px 0 0;
        }

        .partner-list-content {
            max-height: 200px;
            overflow-y: auto;
            padding: 10px;
        }

        .partner-item {
            padding: 8px;
            margin: 3px 0;
            background-color: #e9ecef;
            border-radius: 3px;
            display: flex;
            justify-content: between;
            align-items: center;
            cursor: pointer;
        }

        .partner-item:hover {
            background-color: #dee2e6;
        }

        .partner-item.selected {
            background-color: #cce5ff;
            border: 1px solid #007cba;
        }

        .partner-controls {
            text-align: center;
            padding: 10px;
        }

        .partner-controls button {
            margin: 5px;
            width: 120px;
        }

        .eintrage-modus-section {
            border-top: 1px solid #ddd;
        }

        .eintrage-modus-section h5 {
            color: #666;
            margin-bottom: 5px;
        }

        .eintrage-modus-group {
            display: flex;
            gap: 15px;
            margin: 10px 0;
        }

        .eintrage-modus-group label {
            display: flex;
            align-items: center;
            gap: 5px;
            width: auto;
            margin: 0;
        }

        .eintrage-modus-group input[type="radio"] {
            margin: 0;
            width: auto;
        }

        .eintrage-modus-section.disabled {
            opacity: 0.5;
        }

        .eintrage-modus-section.disabled input[type="radio"] {
            cursor: not-allowed;
        }
    </style>
</head>
<body>

<h1>Automatische Produktionsabläufe - Einstellungen</h1>

<?php if ($message): ?>
    <div class="message <?php echo strpos($message, 'Fehler') !== false ? 'error' : 'success'; ?>">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<div class="section">
    <h2>Automatischer Hotfolder</h2>
    <form method="post">
        <input type="hidden" name="action" value="update_hotfolder_settings">

        <div class="form-group">
            <label for="NAS_IP">NAS-IP:</label>
            <input type="text" id="NAS_IP" name="NAS_IP" value="<?php echo htmlspecialchars($hotfolder_settings['NAS_IP']); ?>">
        </div>

        <div class="form-group">
            <label for="INPUT_PATH">Eingangspfad:</label>
            <input type="text" id="INPUT_PATH" name="INPUT_PATH" value="<?php echo htmlspecialchars($hotfolder_settings['INPUT_PATH']); ?>">
        </div>

        <div class="form-group">
            <label for="OUTPUT_PATH">Ausgangspfad:</label>
            <input type="text" id="OUTPUT_PATH" name="OUTPUT_PATH" value="<?php echo htmlspecialchars($hotfolder_settings['OUTPUT_PATH']); ?>">
        </div>

        <div class="form-group">
            <label for="WORKING_PATH">Arbeitet-Pfad:</label>
            <input type="text" id="WORKING_PATH" name="WORKING_PATH" value="<?php echo htmlspecialchars($hotfolder_settings['WORKING_PATH']); ?>">
        </div>

        <div class="form-group">
            <label for="ERROR_PATH">Fehler-Pfad:</label>
            <input type="text" id="ERROR_PATH" name="ERROR_PATH" value="<?php echo htmlspecialchars($hotfolder_settings['ERROR_PATH']); ?>">
        </div>

        <div class="form-group">
            <label for="WAITING_PATH">Wartet-Pfad:</label>
            <input type="text" id="WAITING_PATH" name="WAITING_PATH" value="<?php echo htmlspecialchars($hotfolder_settings['WAITING_PATH']); ?>">
        </div>

        <div class="form-group">
            <label for="CAPTURING_PATH">Capturing-Pfad:</label>
            <input type="text" id="CAPTURING_PATH" name="CAPTURING_PATH" value="<?php echo htmlspecialchars($hotfolder_settings['CAPTURING_PATH']); ?>">
        </div>

        <div class="form-group">
            <label for="BATCHEN_PATH">Batchen-Pfad:</label>
            <input type="text" id="BATCHEN_PATH" name="BATCHEN_PATH" value="<?php echo htmlspecialchars($hotfolder_settings['BATCHEN_PATH']); ?>">
        </div>

        <div class="form-group">
            <label for="FINISHED_PATH">Finished-Pfad:</label>
            <input type="text" id="FINISHED_PATH" name="FINISHED_PATH" value="<?php echo htmlspecialchars($hotfolder_settings['FINISHED_PATH']); ?>">
        </div>

        <div class="form-group">
            <label for="RAW_PATH">RAW-Pfad:</label>
            <input type="text" id="RAW_PATH" name="RAW_PATH" value="<?php echo htmlspecialchars($hotfolder_settings['RAW_PATH']); ?>">
        </div>

        <div class="form-group">
            <label for="PRODUCTION_PATH">Production-Pfad:</label>
            <input type="text" id="PRODUCTION_PATH" name="PRODUCTION_PATH" value="<?php echo htmlspecialchars($hotfolder_settings['PRODUCTION_PATH']); ?>">
        </div>

        <div class="form-group">
            <label for="REMOTE_FINISHED_PATH">Remote-Finished-Pfad:</label>
            <input type="text" id="REMOTE_FINISHED_PATH" name="REMOTE_FINISHED_PATH" value="<?php echo htmlspecialchars($hotfolder_settings['REMOTE_FINISHED_PATH']); ?>">
        </div>

        <div class="form-group">
            <label for="DVD_LOG_PATH">DVD-Log-Pfad:</label>
            <input type="text" id="DVD_LOG_PATH" name="DVD_LOG_PATH" value="<?php echo htmlspecialchars($hotfolder_settings['DVD_LOG_PATH']); ?>">
        </div>

        <div class="form-group">
            <label for="BURNER_NAME">Name des Brenners:</label>
            <input type="text" id="BURNER_NAME" name="BURNER_NAME" value="<?php echo htmlspecialchars($hotfolder_settings['BURNER_NAME']); ?>">
        </div>

        <div class="form-group">
            <label for="BURNER_PATH">Brenner-Pfad:</label>
            <input type="text" id="BURNER_PATH" name="BURNER_PATH" value="<?php echo htmlspecialchars($hotfolder_settings['BURNER_PATH']); ?>">
        </div>

        <div class="form-group">
            <label for="DVD_SPACE_FOR_USE">DVD-Speicherplatz (Byte):</label>
            <input type="number" id="DVD_SPACE_FOR_USE" name="DVD_SPACE_FOR_USE" value="<?php echo htmlspecialchars($hotfolder_settings['DVD_SPACE_FOR_USE']); ?>">
        </div>

        <div class="form-group">
            <label for="DVD_MAX_IMAGES">DVD Max. Bilder:</label>
            <input type="number" id="DVD_MAX_IMAGES" name="DVD_MAX_IMAGES" value="<?php echo htmlspecialchars($hotfolder_settings['DVD_MAX_IMAGES']); ?>">
        </div>

        <button type="submit" class="btn">Einstellungen speichern</button>
    </form>
</div>

<div class="section">
    <h2>Allgemeine Produktionseinstellungen</h2>
    <form method="post" id="generalSettingsForm">
        <input type="hidden" name="action" value="update_general_settings">

        <div class="form-group">
            <label for="NAS_IP_general">NAS-IP:</label>
            <input type="text" id="NAS_IP_general" name="NAS_IP" value="<?php echo htmlspecialchars($general_settings['NAS_IP']); ?>">
        </div>

        <div class="form-group">
            <label for="FILMOMAT_IP">FILMOMAT-IP:</label>
            <input type="text" id="FILMOMAT_IP" name="FILMOMAT_IP" value="<?php echo htmlspecialchars($general_settings['FILMOMAT_IP']); ?>">
        </div>

        <div class="partner-dvd-section">
            <h3>Partner ohne DVD-Artikel</h3>
            <p>Hier können Sie festlegen, welche Partner nicht den normalen DVD-Artikel erhalten sollen, damit diese nicht vom automatischen DVD-Hotfolder eingetragen werden.</p>

            <div class="partner-selection-container">
                <div class="partner-lists">
                    <div class="partner-list">
                        <h4>Verfügbare Partner</h4>
                        <div class="partner-list-content" id="availablePartners">
                            <?php foreach ($all_partners as $partner): ?>
                                <?php if ($partner["bekommtDVDArtikel"] == 1): ?>
                                    <div class="partner-item" data-partner-id="<?php echo $partner["ID"]; ?>" onclick="togglePartnerSelection(this, 'available')">
                                        <?php echo $partner["name"]; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="partner-controls">
                        <button type="button" class="btn" onclick="movePartnersToWithoutDVD()">Hinzufügen</button>
                        <button type="button" class="btn" onclick="movePartnersToAvailable()">Entfernen</button>
                    </div>

                    <div class="partner-list">
                        <h4>Partner ohne DVD-Artikel</h4>
                        <div class="partner-list-content" id="partnersWithoutDVD">
                            <?php foreach ($partners_ohne_dvd as $partner): ?>
                                <div class="partner-item" data-partner-id="<?php echo $partner["ID"]; ?>" onclick="togglePartnerSelection(this, 'without')">
                                    <?php echo $partner["name"]; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" class="btn">Einstellungen speichern</button>
    </form>
</div>

<div class="section">
    <h2>Produktionsworkflows</h2>

    <div style="margin-bottom: 20px;">
        <button type="button" class="btn btn-success" onclick="openWorkflowModal()">Neuen Workflow erstellen</button>
    </div>

    <h3>Bestehende Workflows</h3>
    <?php if (empty($workflows)): ?>
        <p>Keine Workflows vorhanden.</p>
    <?php else: ?>
        <?php
        $groupedWorkflows = [];
        foreach ($workflows as $workflow) {
            $abteilung = ucfirst(htmlspecialchars($workflow["abteilung"]));
            $groupedWorkflows[$abteilung][] = $workflow;
        }

        // Abteilungen alphabetisch sortieren
        ksort($groupedWorkflows);

        $isFirstGroup = true;
        ?>

        <?php foreach ($groupedWorkflows as $abteilung => $abteilungWorkflows): ?>
            <?php if (!$isFirstGroup): ?>
                <hr style="margin: 30px 0; border: 1px solid #ddd;">
            <?php endif; ?>

            <div style="margin-bottom: 30px;">
                <h4 style="color: #333; margin-bottom: 15px; padding: 8px; background-color: #f5f5f5; border-left: 4px solid #007cba;">
                    Abteilung: <?php echo $abteilung; ?> (<?php echo count($abteilungWorkflows); ?> Workflow<?php echo count($abteilungWorkflows) != 1 ? 's' : ''; ?>)
                </h4>

                <table style="width: 100%; margin-bottom: 20px;">
                    <tr>
                        <th>Name</th>
                        <th>Partner-ID</th>
                        <th>Arbeitsschritt-ID</th>
                        <th>Staffelartikel?</th>
                        <th>Unterordner?</th>
                        <th>Batch-ID</th>
                        <th>Ziel-Speichermedium</th>
                        <th>Aktionen</th>
                    </tr>
                    <?php foreach ($abteilungWorkflows as $workflow): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($workflow["name"]); ?></td>
                            <td><?php if ($workflow["partnerID"] == 0) {echo "Kein fester Partner";} else {echo htmlspecialchars($workflow["partnerID"]);}?></td>
                            <td><?php echo htmlspecialchars($workflow["arbeitsschrittID"]); ?></td>
                            <td><?php if(htmlspecialchars($workflow["bekommtStaffel"])) {echo "Ja";} else {echo "Nein";}?></td>
                            <td><?php if(htmlspecialchars($workflow["bekommtUnterordner"])) {echo "Ja";} else {echo "Nein";}?></td>
                            <td><?php echo htmlspecialchars($workflow["batchID"]); ?></td>
                            <td><?php echo ucfirst(htmlspecialchars($workflow["zielSpeichermedium"])); ?></td>
                            <td>
                                <button type="button" class="btn" onclick="editWorkflow(<?php echo $workflow["id"]; ?>)">Bearbeiten</button>
                                <form method="post" style="display: inline;" onsubmit="return confirm('Workflow wirklich löschen?');">
                                    <input type="hidden" name="action" value="delete_workflow">
                                    <input type="hidden" name="id" value="<?php echo $workflow['id']; ?>">
                                    <button type="submit" class="btn btn-danger">Löschen</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>

            <?php $isFirstGroup = false; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>


<div id="workflowModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <span class="close" onclick="closeWorkflowModal()">&times;</span>
            <h3 id="modalTitle">Workflow erstellen</h3>
        </div>
        <div class="modal-body">
            <div class="tab-container">
                <div class="tab-buttons">
                    <button type="button" class="tab-button active" onclick="showTab('workflow-tab')">Workflow Details</button>
                    <button type="button" class="tab-button" id="articles-tab-button" onclick="showTab('articles-tab')" style="display: none;">Artikel verwalten</button>
                </div>

                <div id="workflow-tab" class="tab-content active">
                    <form id="workflowForm" method="post">
                        <input type="hidden" name="action" id="formAction" value="add_workflow">
                        <input type="hidden" name="id" id="workflowId" value="">

                        <div class="form-group">
                            <label for="modal_name">Name:</label>
                            <input type="text" id="modal_name" name="name" required style="width: 100%;" placeholder="Name des Workflows">
                        </div>

                        <div class="form-group">
                            <label for="modal_partnerID">Partner-ID:</label>
                            <input type="number" id="modal_partnerID" name="partnerID" min="0" required style="width: 100%;" placeholder="Zugehörige Partner-ID (0, falls mehrere Partner den Workflow nutzen)">
                        </div>

                        <div class="form-group">
                            <label for="modal_arbeitsschrittID">Arbeitsschritt-ID:</label>
                            <input type="number" id="modal_arbeitsschrittID" name="arbeitsschrittID" required style="width: 100%;" placeholder="ID des Arbeitsschritts (1 = scannen, 2 = batchen, ...)">
                        </div>

                        <div class="form-group">
                            <label for="modal_batchID">Batch-ID:</label>
                            <input type="text" id="modal_batchID" name="batchID" required maxlength="2" style="width: 100%;" placeholder="BatchID auf dem NAS bestehend aus 2 Zeichen">
                        </div>

                        <div class="form-group">
                            <label for="modal_zielpfad">Zielpfad:</label>
                            <input type="text" id="modal_zielpfad" name="zielpfad" required style="width: 100%;" placeholder="Adresse, wohin die Auftragsordner geschoben werden (Platzhalter: NAS_IP, FILMOMAT_IP, $batchID)">
                        </div>

                        <div class="form-group">
                            <label for="modal_abteilung">Abteilung:</label>
                            <select id="modal_abteilung" name="abteilung" required style="width: 100%;">
                                <option value="">Bitte wählen...</option>
                                <option value="dia">Dia</option>
                                <option value="foto">Foto</option>
                                <option value="negativ">Negativ</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="modal_zielSpeichermedium">Ziel-Speichermedium:</label>
                            <select id="modal_zielSpeichermedium" name="zielSpeichermedium" required style="width: 100%;">
                                <option value="">Bitte wählen...</option>
                                <option value="dvd">DVD</option>
                                <option value="usb">USB</option>
                                <option value="cloud">Cloud</option>
                                <option value="batchen">Batchen</option>
                            </select>
                        </div>

                        <div class="checkbox-group" style="margin-top: 30px;">
                            <label>
                                <input type="checkbox" name="bekommtStaffel" id="modal_bekommtStaffel">
                                Bekommt Staffel
                            </label>

                            <label>
                                <input type="checkbox" name="bekommtUnterordner" id="modal_bekommtUnterordner">
                                Bekommt Unterordner
                            </label>

                            <label>
                                <input type="checkbox" name="bekommtDigiSonderartikel" id="modal_bekommtDigiSonderartikel">
                                Bekommt Digi-Sonderartikel
                            </label>

                            <label>
                                <input type="checkbox" name="bekommtViesus" id="modal_bekommtViesus">
                                Bekommt Viesus
                            </label>

                            <label>
                                <input type="checkbox" name="bekommtMoebius" id="modal_bekommtMoebius">
                                Bekommt Moebius
                            </label>
                        </div>
                    </form>
                </div>


                <div id="articles-tab" class="tab-content">
                    <div class="workflow-articles-section">
                        <h4>Zugewiesene Artikel</h4>
                        <div id="assignedArticles">
                            <!-- Will be populated via JavaScript -->
                        </div>

                        <div class="zusatz-positions-section">
                            <h4>Zusatzpositionen</h4>
                            <h6><i>Werden automatisch eingetragen, falls Aufträge passende Auftragsposition haben</i></h6>
                            <div id="zusatzPositions">
                                <!-- Will be populated via JavaScript -->
                            </div>
                        </div>

                        <h4>Artikel hinzufügen</h4>
                        <div style="border: 1px solid #ddd; padding: 15px; border-radius: 5px; background: #f9f9f9;">
                            <div class="form-group">
                                <label for="modal_artNr">Artikel:</label>
                                <select id="modal_artNr" required style="width: 100%;">
                                    <option value="">Artikel wählen...</option>
                                    <?php foreach ($articles as $article): ?>
                                        <option value="<?php echo htmlspecialchars($article["artNr"]); ?>"><?php echo htmlspecialchars($article["artNr"]); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Position:</label>
                                <div class="position-radio-group">
                                    <label>
                                        <input type="radio" name="positionType" value="normal" id="modal_normalPosition" checked>
                                        Normale Position
                                    </label>
                                    <label>
                                        <input type="radio" name="positionType" value="spezial" id="modal_spezialPosition">
                                        Spezial Position
                                    </label>
                                    <label>
                                        <input type="radio" name="positionType" value="zusatz" id="modal_zusatzPosition">
                                        Zusatz Position
                                    </label>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="modal_staffelStart">Staffel Start:</label>
                                <input type="number" id="modal_staffelStart" value="0" style="width: 100%;" disabled>
                            </div>

                            <div class="form-group">
                                <label for="modal_staffelEnde">Staffel Ende:</label>
                                <div style="margin-top: 5px; margin-bottom: 5px; font-size: 12px;">(Bitte 0 eintragen, falls Position unbegrenzt nach oben gilt)</div>
                                <input type="number" id="modal_staffelEnde" value="0" style="width: 100%;" disabled>
                            </div>

                            <div class="eintrage-modus-section disabled" id="eintrageModusSection">
                                <h5>Eintrage-Modus (nur für Zusatzpositionen)</h5>
                                <div class="eintrage-modus-group">
                                    <label>
                                        <input type="radio" name="eintrageModus" value="anzahl" id="modal_eintrageModusAnzahl" checked>
                                        Nach Bildanzahl
                                    </label>
                                    <label>
                                        <input type="radio" name="eintrageModus" value="1" id="modal_eintrageModus1">
                                        1x
                                    </label>
                                </div>
                            </div>

                            <button type="button" class="btn" onclick="addArticleToList()">Artikel hinzufügen</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn" onclick="closeWorkflowModal()">Abbrechen</button>
            <button type="button" class="btn btn-success" onclick="submitWorkflowForm()" id="saveButton">Speichern</button>
        </div>
    </div>
</div>

<script>
    let currentWorkflowId = null;
    let isEditMode = false;
    let localArticles = []; // Lokale Artikel-Liste für das Modal

    function validateWorkflowArticles() {
        const bekommtStaffelCheckbox = document.getElementById("modal_bekommtStaffel");
        const saveButton = document.getElementById("saveButton");

        // Zähle normale Positionen (nicht Spezial- oder Zusatzpositionen)
        const normalArticles = localArticles.filter(article =>
            article.spezialPosition !== 1 && article.zusatzPosition !== 1
        );

        // Wenn bekommtStaffel nicht gesetzt ist und mehr als 1 normaler Artikel vorhanden
        if (!bekommtStaffelCheckbox.checked && normalArticles.length > 1) {
            saveButton.disabled = true;
            saveButton.style.opacity = "0.5";
            saveButton.title = "Workflows ohne Staffel dürfen nur einen normalen Artikel enthalten";
        } else {
            saveButton.disabled = false;
            saveButton.style.opacity = "1";
            saveButton.title = "";
        }
    }

    // Partner DVD Management Functions
    function togglePartnerSelection(element, listType) {
        element.classList.toggle("selected");
    }

    function selectAllAvailable() {
        const items = document.querySelectorAll("#availablePartners .partner-item");
        items.forEach(item => item.classList.add("selected"));
    }

    function deselectAllAvailable() {
        const items = document.querySelectorAll("#availablePartners .partner-item");
        items.forEach(item => item.classList.remove("selected"));
    }

    function selectAllWithoutDVD() {
        const items = document.querySelectorAll("#partnersWithoutDVD .partner-item");
        items.forEach(item => item.classList.add("selected"));
    }

    function deselectAllWithoutDVD() {
        const items = document.querySelectorAll("#partnersWithoutDVD .partner-item");
        items.forEach(item => item.classList.remove("selected"));
    }

    function movePartnersToWithoutDVD() {
        const selectedItems = document.querySelectorAll("#availablePartners .partner-item.selected");
        const targetContainer = document.getElementById("partnersWithoutDVD");

        selectedItems.forEach(item => {
            item.classList.remove("selected");
            item.setAttribute("onclick", "togglePartnerSelection(this, 'without')");
            targetContainer.appendChild(item);
        });

        updateHiddenPartnerInputs();
    }

    function movePartnersToAvailable() {
        const selectedItems = document.querySelectorAll("#partnersWithoutDVD .partner-item.selected");
        const targetContainer = document.getElementById("availablePartners");

        selectedItems.forEach(item => {
            item.classList.remove("selected");
            item.setAttribute("onclick", "togglePartnerSelection(this, 'available')");
            targetContainer.appendChild(item);
        });

        updateHiddenPartnerInputs();
    }

    function updateHiddenPartnerInputs() {
        // Remove existing hidden inputs
        const existingInputs = document.querySelectorAll("input[name^='partnersOhneDVD']");
        existingInputs.forEach(input => input.remove());

        // Add new hidden inputs for partners without DVD
        const partnersWithoutDVD = document.querySelectorAll("#partnersWithoutDVD .partner-item");
        const form = document.getElementById("generalSettingsForm");

        partnersWithoutDVD.forEach((item, index) => {
            const input = document.createElement("input");
            input.type = "hidden";
            input.name = `partnersOhneDVD[${index}]`;
            input.value = item.getAttribute("data-partner-id");
            form.appendChild(input);
        });
    }

    // Initialize hidden inputs on page load
    document.addEventListener("DOMContentLoaded", function() {
        updateHiddenPartnerInputs();

        // Update inputs whenever form is submitted
        document.getElementById("generalSettingsForm").addEventListener("submit", function() {
            updateHiddenPartnerInputs();
        });
    });

    function openWorkflowModal() {
        isEditMode = false;
        currentWorkflowId = null;
        localArticles = [];

        document.getElementById("modalTitle").textContent = "Neuen Workflow erstellen";
        document.getElementById("formAction").value = "add_workflow";
        document.getElementById("workflowId").value = "";
        document.getElementById("articles-tab-button").style.display = "none";

        // Reset form
        document.getElementById("workflowForm").reset();

        // Show workflow tab
        showTab("workflow-tab");

        // Reset article form
        resetArticleForm();

        document.getElementById("workflowModal").style.display = "block";
    }

    function editWorkflow(workflowId) {
        isEditMode = true;
        currentWorkflowId = workflowId;
        localArticles = [];

        document.getElementById("modalTitle").textContent = "Workflow bearbeiten";
        document.getElementById("formAction").value = "update_workflow";
        document.getElementById("workflowId").value = workflowId;
        document.getElementById("articles-tab-button").style.display = "inline-block";

        // Load workflow data via AJAX
        fetch("/ajax/produktworkflows.php?action=get_workflow&id=" + workflowId)
            .then(response => response.json())
            .then(data => {
                const workflow = data.workflow;
                const articles = data.articles;

                // Populate form fields
                document.getElementById("modal_name").value = workflow.name || "";
                document.getElementById("modal_partnerID").value = workflow.partnerID || "";
                document.getElementById("modal_arbeitsschrittID").value = workflow.arbeitsschrittID || "";
                document.getElementById("modal_batchID").value = workflow.batchID || "";
                document.getElementById("modal_zielpfad").value = workflow.zielpfad || "";
                document.getElementById("modal_abteilung").value = workflow.abteilung || "";
                document.getElementById("modal_zielSpeichermedium").value = workflow.zielSpeichermedium || "";

                // Set checkboxes
                document.getElementById("modal_bekommtStaffel").checked = workflow.bekommtStaffel == 1;
                document.getElementById("modal_bekommtUnterordner").checked = workflow.bekommtUnterordner == 1;
                document.getElementById("modal_bekommtDigiSonderartikel").checked = workflow.bekommtDigiSonderartikel == 1;
                document.getElementById("modal_bekommtViesus").checked = workflow.bekommtViesus == 1;
                document.getElementById("modal_bekommtMoebius").checked = workflow.bekommtMoebius == 1;

                // Load articles into local array
                localArticles = articles.map(article => ({
                    id: article.id || null,
                    artNr: article.artNr,
                    spezialPosition: parseInt(article.spezialPosition),
                    zusatzPosition: parseInt(article.zusatzPosition || 0),
                    staffelStart: parseInt(article.staffelStart),
                    staffelEnde: parseInt(article.staffelEnde),
                    eintrageModus: article.eintrageModus || "anzahl",
                    isNew: false
                }));

                // Populate articles display
                updateArticlesDisplay();

                // Reset article form
                resetArticleForm();
            })
            .catch(error => {
                console.error("Error loading workflow data:", error);
                alert("Fehler beim Laden der Workflow-Daten");
            });

        document.getElementById("workflowModal").style.display = "block";
    }

    function closeWorkflowModal() {
        document.getElementById("workflowModal").style.display = "none";
        currentWorkflowId = null;
        isEditMode = false;
        localArticles = [];
    }

    function showTab(tabId) {
        // Hide all tab contents
        const tabContents = document.querySelectorAll(".tab-content");
        tabContents.forEach(content => content.classList.remove("active"));

        // Remove active class from all tab buttons
        const tabButtons = document.querySelectorAll(".tab-button");
        tabButtons.forEach(button => button.classList.remove("active"));

        // Show selected tab content
        document.getElementById(tabId).classList.add("active");

        // Add active class to corresponding button
        const activeButton = document.querySelector(`[onclick="showTab('${tabId}')"]`);
        if (activeButton) {
            activeButton.classList.add("active");
        }

        updateStaffelFields();
        updateEintrageModusFields();
        validateWorkflowArticles();
    }

    function addArticleToList() {
        const artNr = document.getElementById("modal_artNr").value;
        const positionType = document.querySelector("input[name='positionType']:checked").value;
        const staffelStart = parseInt(document.getElementById("modal_staffelStart").value) || 0;
        const staffelEnde = parseInt(document.getElementById("modal_staffelEnde").value) || 0;
        const eintrageModus = document.querySelector("input[name='eintrageModus']:checked").value;

        if (!artNr) {
            alert("Bitte wählen Sie einen Artikel aus.");
            return;
        }

        // Prüfen ob Artikel bereits existiert
        if (localArticles.some(article => article.artNr === artNr)) {
            alert("Dieser Artikel ist bereits zugewiesen.");
            return;
        }

        // Artikel zur lokalen Liste hinzufügen
        const newArticle = {
            id: null, // Neue Artikel haben noch keine ID
            artNr: artNr,
            spezialPosition: positionType === "spezial" ? 1 : 0,
            zusatzPosition: positionType === "zusatz" ? 1 : 0,
            staffelStart: staffelStart,
            staffelEnde: staffelEnde,
            eintrageModus: positionType === "zusatz" ? eintrageModus : "anzahl",
            isNew: true
        };

        localArticles.push(newArticle);

        // Anzeige aktualisieren
        updateArticlesDisplay();

        // Formular zurücksetzen
        resetArticleForm();
    }

    function removeArticleFromList(index) {
        if (confirm("Artikel wirklich aus der Liste entfernen?")) {
            localArticles.splice(index, 1);
            updateArticlesDisplay();
        }
    }

    function updateArticlesDisplay() {
        updateNormalArticlesDisplay();
        updateZusatzPositionsDisplay();
        validateWorkflowArticles();
    }

    function updateNormalArticlesDisplay() {
        const container = document.getElementById("assignedArticles");
        const normalArticles = localArticles.filter(article => article.zusatzPosition !== 1);

        if (normalArticles.length === 0) {
            container.innerHTML = "<p>Keine normalen Artikel zugewiesen.</p>";
            return;
        }

        let html = "<table><tr><th>Artikel Nr.</th><th>Spezial Position</th><th>Staffel Start</th><th>Staffel Ende</th><th>Status</th><th>Aktionen</th></tr>";

        const shouldSort = normalArticles.some(item => item.staffelStart !== 0 && item.staffelEnde !== 0);
        if (shouldSort) {
            normalArticles.sort((a, b) => a.staffelStart - b.staffelStart);
        } else {
            normalArticles.sort((a, b) => a.artNr.localeCompare(b.artNr));
        }

        normalArticles.forEach((article) => {
            const index = localArticles.indexOf(article);
            const statusText = article.isNew ? "<span style='color: green;'>Neu</span>" : "<span style='color: blue;'>Vorhanden</span>";
            html += `<tr>
            <td>${escapeHtml(article.artNr)}</td>
            <td>${article.spezialPosition === 1 ? "Ja" : "Nein"}</td>
            <td>${article.staffelStart}</td>
            <td>${article.staffelEnde}</td>
            <td>${statusText}</td>
            <td>
                <button type="button" class="btn btn-danger" onclick="removeArticleFromList(${index})">Entfernen</button>
            </td>
        </tr>`;
        });

        html += "</table>";
        container.innerHTML = html;
    }

    function updateZusatzPositionsDisplay() {
        const container = document.getElementById("zusatzPositions");
        const zusatzArticles = localArticles.filter(article => article.zusatzPosition === 1);

        if (zusatzArticles.length === 0) {
            container.innerHTML = "<p>Keine Zusatzpositionen zugewiesen.</p>";
            return;
        }

        let html = "<table><tr><th>Artikel Nr.</th><th>Eintrage-Modus</th><th>Status</th><th>Aktionen</th></tr>";

        zusatzArticles.sort((a, b) => a.artNr.localeCompare(b.artNr));

        zusatzArticles.forEach((article) => {
            const index = localArticles.indexOf(article);
            const statusText = article.isNew ? "<span style='color: green;'>Neu</span>" : "<span style='color: blue;'>Vorhanden</span>";
            const eintrageModusText = article.eintrageModus === "1" ? "1x" : "Bildanzahl";
            html += `<tr>
            <td>${escapeHtml(article.artNr)}</td>
            <td>${eintrageModusText}</td>
            <td>${statusText}</td>
            <td>
                <button type="button" class="btn btn-danger" onclick="removeArticleFromList(${index})">Entfernen</button>
            </td>
        </tr>`;
        });

        html += "</table>";
        container.innerHTML = html;
    }

    function resetArticleForm() {
        document.getElementById("modal_artNr").value = "";
        document.getElementById("modal_staffelStart").value = "0";
        document.getElementById("modal_staffelEnde").value = "0";
        document.getElementById("modal_normalPosition").checked = true;
        document.getElementById("modal_eintrageModusAnzahl").checked = true;

        updateStaffelFields();
        updateEintrageModusFields();
    }

    function submitWorkflowForm() {
        const requiredFields = ["modal_name", "modal_partnerID", "modal_arbeitsschrittID", "modal_batchID", "modal_zielpfad", "modal_abteilung", "modal_zielSpeichermedium"];
        let isValid = true;

        requiredFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (!field.value.trim()) {
                field.style.borderColor = "red";
                isValid = false;
            } else {
                field.style.borderColor = "";
            }
        });

        if (!isValid) {
            alert("Bitte füllen Sie alle Pflichtfelder aus.");
            return;
        }

        const batchIDField = document.getElementById("modal_batchID");
        if (batchIDField.value.length !== 2) {
            batchIDField.style.borderColor = "red";
            isValid = false;
        } else {
            batchIDField.style.borderColor = "";
        }

        if (!isValid) {
            alert("Die Batch-ID muss aus genau zwei Zeichen bestehen");
            return;
        }

        const bekommtStaffelCheckbox = document.getElementById("modal_bekommtStaffel");
        const normalArticles = localArticles.filter(article =>
            article.spezialPosition !== 1 && article.zusatzPosition !== 1
        );

        if (!bekommtStaffelCheckbox.checked && normalArticles.length > 1) {
            alert("Workflows ohne Staffel dürfen nur einen normalen Artikel enthalten. Bitte entfernen Sie überschüssige normale Artikel oder aktivieren Sie 'Bekommt Staffel'.");
            return;
        }

        // Erstelle versteckte Inputs für die Artikel
        const form = document.getElementById("workflowForm");

        // Entferne eventuell vorhandene Artikel-Inputs
        const existingArticleInputs = form.querySelectorAll("input[name^='articles']");
        existingArticleInputs.forEach(input => input.remove());

        // Füge neue Artikel-Inputs hinzu
        localArticles.forEach((article, index) => {
            const fields = ["artNr", "spezialPosition", "zusatzPosition", "staffelStart", "staffelEnde", "eintrageModus"];
            fields.forEach(field => {
                const input = document.createElement("input");
                input.type = "hidden";
                input.name = `articles[${index}][${field}]`;
                input.value = article[field];
                form.appendChild(input);
            });
        });

        form.submit();
    }

    function escapeHtml(text) {
        const map = {
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#039;"
        };
        return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // Close modal when clicking outside of it
    window.onclick = function(event) {
        const modal = document.getElementById("workflowModal");
        if (event.target === modal) {
            closeWorkflowModal();
        }
    }

    // Handle escape key to close modal
    document.addEventListener("keydown", function(event) {
        if (event.key === "Escape") {
            closeWorkflowModal();
        }
    });

    function updateStaffelFields() {
        const bekommtStaffelCheckbox = document.getElementById("modal_bekommtStaffel");
        const staffelStartField = document.getElementById("modal_staffelStart");
        const staffelEndeField = document.getElementById("modal_staffelEnde");

        if (bekommtStaffelCheckbox && bekommtStaffelCheckbox.checked) {
            staffelStartField.disabled = false;
            staffelEndeField.disabled = false;
        } else {
            staffelStartField.disabled = true;
            staffelEndeField.disabled = true;
            staffelStartField.value = "0";
            staffelEndeField.value = "0";
        }
    }

    function updateEintrageModusFields() {
        const positionType = document.querySelector("input[name='positionType']:checked");
        const eintrageModusSection = document.getElementById("eintrageModusSection");
        const eintrageModusRadios = document.querySelectorAll("input[name='eintrageModus']");

        if (positionType && positionType.value === "zusatz") {
            // Eintrage-Modus aktivieren für Zusatzpositionen
            eintrageModusSection.classList.remove("disabled");
            eintrageModusRadios.forEach(radio => {
                radio.disabled = false;
            });
        } else {
            // Eintrage-Modus deaktivieren für normale und Spezial-Positionen
            eintrageModusSection.classList.add("disabled");
            eintrageModusRadios.forEach(radio => {
                radio.disabled = true;
            });
            // Zurück auf "anzahl" setzen
            document.getElementById("modal_eintrageModusAnzahl").checked = true;
        }
    }

    // Event Listeners für Radio Buttons hinzufügen
    document.addEventListener("DOMContentLoaded", function() {
        const bekommtStaffelCheckbox = document.getElementById("modal_bekommtStaffel");
        const positionRadios = document.querySelectorAll("input[name='positionType']");

        if (bekommtStaffelCheckbox) {
            bekommtStaffelCheckbox.addEventListener("change", function() {
                updateStaffelFields();
                validateWorkflowArticles();
            });
        }

        // Event Listener für Position Radio Buttons
        positionRadios.forEach(radio => {
            radio.addEventListener("change", function() {
                const staffelStartField = document.getElementById("modal_staffelStart");
                const staffelEndeField = document.getElementById("modal_staffelEnde");

                // Bei Zusatzposition keine Staffel-Felder aktivieren
                if (this.value === "zusatz" || this.value === "spezial") {
                    staffelStartField.disabled = true;
                    staffelEndeField.disabled = true;
                    staffelStartField.value = "0";
                    staffelEndeField.value = "0";
                } else {
                    // Bei anderen Positionen normale Logik anwenden
                    updateStaffelFields();
                }

                // Eintrage-Modus Felder aktualisieren
                updateEintrageModusFields();
            });
        });

        // Initial state für Eintrage-Modus setzen
        updateEintrageModusFields();
    });
</script>

</body>
</html>