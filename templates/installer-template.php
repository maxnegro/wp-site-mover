<?php
/**
 * SiteMover Standalone Restoration Installer
 * Auto-generated single-file installer script.
 */

// Disable memory limit restrictions if permitted
@ini_set('memory_limit', '512M');
@set_time_limit(300);

// Manifest configuration placeholder (injected at package creation)
$manifest = json_decode('{{SITEMOVER_MANIFEST_JSON}}', true);

// =========================================================================
// AJAX ENDPOINTS HANDLER (Executed when POST request is sent to installer.php)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');

    $action = $_POST['action'];

    if ($action === 'check_requirements') {
        $checks = array(
            'php_version' => array(
                'label'   => 'Versione PHP (>= 7.4)',
                'pass'    => version_compare(PHP_VERSION, '7.4.0', '>='),
                'details' => 'Versione corrente: ' . PHP_VERSION,
            ),
            'mysqli' => array(
                'label'   => 'Estensione MySQLi',
                'pass'    => extension_loaded('mysqli'),
                'details' => extension_loaded('mysqli') ? 'Disponibile' : 'Mancante',
            ),
            'zip' => array(
                'label'   => 'Estensione ZipArchive / Zlib',
                'pass'    => class_exists('ZipArchive'),
                'details' => class_exists('ZipArchive') ? 'Disponibile' : 'Mancante',
            ),
            'writable' => array(
                'label'   => 'Permessi Scrittura Root Server',
                'pass'    => is_writable(__DIR__),
                'details' => is_writable(__DIR__) ? 'Scrivibile' : 'Non Scrivibile',
            ),
        );

        $archive_file = isset($manifest['archive_filename']) ? $manifest['archive_filename'] : '';
        $archive_exists = file_exists(__DIR__ . '/' . $archive_file);

        $checks['archive'] = array(
            'label'   => "Presenza Archivio ({$archive_file})",
            'pass'    => $archive_exists,
            'details' => $archive_exists ? 'Trovato' : 'Non trovato nella directory corrente',
        );

        $all_passed = true;
        foreach ($checks as $c) {
            if (!$c['pass']) $all_passed = false;
        }

        echo json_encode(array(
            'success' => $all_passed,
            'checks'  => $checks,
            'manifest' => $manifest
        ));
        exit;
    }

    if ($action === 'test_db') {
        $host = isset($_POST['db_host']) ? trim($_POST['db_host']) : 'localhost';
        $user = isset($_POST['db_user']) ? trim($_POST['db_user']) : '';
        $pass = isset($_POST['db_pass']) ? trim($_POST['db_pass']) : '';
        $name = isset($_POST['db_name']) ? trim($_POST['db_name']) : '';

        $conn = @mysqli_connect($host, $user, $pass);
        if (!$conn) {
            echo json_encode(array('success' => false, 'message' => 'Impossibile connettersi a MySQL: ' . mysqli_connect_error()));
            exit;
        }

        $select_db = @mysqli_select_db($conn, $name);
        if (!$select_db) {
            // Try to create DB if it doesn't exist
            $created = @mysqli_query($conn, "CREATE DATABASE IF NOT EXISTS `" . mysqli_real_escape_string($conn, $name) . "` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            if (!$created) {
                echo json_encode(array('success' => false, 'message' => "Database '{$name}' non trovato e impossibile crearlo: " . mysqli_error($conn)));
                exit;
            }
        }

        echo json_encode(array('success' => true, 'message' => 'Connessione al database riuscita!'));
        exit;
    }

    if ($action === 'unzip_archive') {
        $archive_file = __DIR__ . '/' . $manifest['archive_filename'];
        if (!file_exists($archive_file)) {
            echo json_encode(array('success' => false, 'message' => 'File archivio ZIP non trovato.'));
            exit;
        }

        $zip = new ZipArchive();
        if ($zip->open($archive_file) === true) {
            $zip->extractTo(__DIR__);
            $zip->close();
            echo json_encode(array('success' => true, 'message' => 'Estrazione archivio completata con successo!'));
        } else {
            echo json_encode(array('success' => false, 'message' => 'Impossibile aprire ed estrarre il file ZIP.'));
        }
        exit;
    }

    if ($action === 'import_and_replace') {
        $host     = isset($_POST['db_host']) ? trim($_POST['db_host']) : 'localhost';
        $user     = isset($_POST['db_user']) ? trim($_POST['db_user']) : '';
        $pass     = isset($_POST['db_pass']) ? trim($_POST['db_pass']) : '';
        $name     = isset($_POST['db_name']) ? trim($_POST['db_name']) : '';
        $new_url  = isset($_POST['new_url']) ? rtrim(trim($_POST['new_url']), '/') : '';
        $old_url  = rtrim($manifest['site_url'], '/');

        $conn = @mysqli_connect($host, $user, $pass, $name);
        if (!$conn) {
            echo json_encode(array('success' => false, 'message' => 'Connessione al database fallita.'));
            exit;
        }

        mysqli_set_charset($conn, 'utf8mb4');

        // 1. Import database.sql
        $sql_file = __DIR__ . '/database.sql';
        if (file_exists($sql_file)) {
            $sql_content = file_get_contents($sql_file);
            $queries = explode(";\n", $sql_content);

            @mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 0");
            foreach ($queries as $q) {
                $q = trim($q);
                if (!empty($q)) {
                    @mysqli_query($conn, $q);
                }
            }
            @mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1");
            @unlink($sql_file);
        }

        // 2. Perform Deep Serialized Search & Replace if URL changed
        if (!empty($new_url) && $new_url !== $old_url) {
            sitemover_db_search_replace($conn, $old_url, $new_url);
        }

        // 3. Update or Rewrite wp-config.php
        sitemover_update_wp_config($host, $name, $user, $pass, $manifest['db_prefix']);

        echo json_encode(array('success' => true, 'message' => 'Ripristino database e riconfigurazione del sito completati!'));
        exit;
    }

    if ($action === 'cleanup') {
        // Cleanup installer files
        $archive_file = __DIR__ . '/' . $manifest['archive_filename'];
        @unlink($archive_file);
        @unlink(__DIR__ . '/manifest.json');
        
        // Schedule self deletion
        register_shutdown_function(function() {
            @unlink(__FILE__);
        });

        echo json_encode(array('success' => true, 'message' => 'Pulizia completata. Il tuo nuovo sito è pronto!'));
        exit;
    }
}

/**
 * Deep Recursive Search & Replace Function for WordPress MySQL Database
 */
function sitemover_db_search_replace($conn, $search, $replace) {
    $tables = array();
    $res = mysqli_query($conn, "SHOW TABLES");
    while ($row = mysqli_fetch_row($res)) {
        $tables[] = $row[0];
    }

    foreach ($tables as $table) {
        // Get primary key or unique column if available
        $pk = null;
        $pk_res = mysqli_query($conn, "SHOW KEYS FROM `{$table}` WHERE Key_name = 'PRIMARY'");
        if ($pk_row = mysqli_fetch_assoc($pk_res)) {
            $pk = $pk_row['Column_name'];
        }

        // Get text/varchar columns
        $cols_res = mysqli_query($conn, "SHOW COLUMNS FROM `{$table}`");
        $cols = array();
        while ($col_row = mysqli_fetch_assoc($cols_res)) {
            $type = strtolower($col_row['Type']);
            if (strpos($type, 'char') !== false || strpos($type, 'text') !== false) {
                $cols[] = $col_row['Field'];
            }
        }

        if (empty($cols) || !$pk) continue;

        $cols_sql = implode('`, `', $cols);
        $rows_res = mysqli_query($conn, "SELECT `{$pk}`, `{$cols_sql}` FROM `{$table}`");

        while ($row = mysqli_fetch_assoc($rows_res)) {
            $row_id = $row[$pk];
            $updates = array();

            foreach ($cols as $col) {
                $val = $row[$col];
                if (empty($val) || strpos($val, $search) === false) continue;

                $new_val = sitemover_recursive_replace($search, $replace, $val);
                if ($new_val !== $val) {
                    $updates[] = "`{$col}` = '" . mysqli_real_escape_string($conn, $new_val) . "'";
                }
            }

            if (!empty($updates)) {
                $sql = "UPDATE `{$table}` SET " . implode(', ', $updates) . " WHERE `{$pk}` = '" . mysqli_real_escape_string($conn, $row_id) . "'";
                mysqli_query($conn, $sql);
            }
        }
    }
}

/**
 * Handles PHP Serialized Data Search & Replace Recursively
 */
function sitemover_recursive_replace($search, $replace, $data) {
    if (is_string($data)) {
        // Check if string is PHP serialized
        if (is_serialized($data)) {
            $unserialized = @unserialize($data);
            if ($unserialized !== false || $data === 'b:0;') {
                $replaced = sitemover_recursive_replace($search, $replace, $unserialized);
                return serialize($replaced);
            }
        }
        return str_replace($search, $replace, $data);
    } elseif (is_array($data)) {
        $tmp = array();
        foreach ($data as $key => $val) {
            $new_key = sitemover_recursive_replace($search, $replace, $key);
            $tmp[$new_key] = sitemover_recursive_replace($search, $replace, $val);
        }
        return $tmp;
    } elseif (is_object($data)) {
        $tmp = $data;
        $props = get_object_vars($data);
        foreach ($props as $key => $val) {
            $new_key = sitemover_recursive_replace($search, $replace, $key);
            $tmp->$new_key = sitemover_recursive_replace($search, $replace, $val);
        }
        return $tmp;
    }
    return $data;
}

/**
 * Check if string is PHP serialized format
 */
function is_serialized($data) {
    if (!is_string($data)) return false;
    $data = trim($data);
    if ('N;' == $data) return true;
    if (!preg_match('/^([adObis]:[0-9]+:|b:[01];)/s', $data)) return false;
    return @unserialize($data) !== false;
}

/**
 * Creates or Updates wp-config.php with target DB settings
 */
function sitemover_update_wp_config($host, $name, $user, $pass, $prefix) {
    $config_file = __DIR__ . '/wp-config.php';
    if (!file_exists($config_file)) {
        $sample = __DIR__ . '/wp-config-sample.php';
        if (file_exists($sample)) {
            copy($sample, $config_file);
        } else {
            return false;
        }
    }

    $content = file_get_contents($config_file);
    $content = preg_replace("/define\(\s*'DB_NAME'\s*,\s*'.*?'\s*\);/", "define('DB_NAME', '" . addslashes($name) . "');", $content);
    $content = preg_replace("/define\(\s*'DB_USER'\s*,\s*'.*?'\s*\);/", "define('DB_USER', '" . addslashes($user) . "');", $content);
    $content = preg_replace("/define\(\s*'DB_PASSWORD'\s*,\s*'.*?'\s*\);/", "define('DB_PASSWORD', '" . addslashes($pass) . "');", $content);
    $content = preg_replace("/define\(\s*'DB_HOST'\s*,\s*'.*?'\s*\);/", "define('DB_HOST', '" . addslashes($host) . "');", $content);
    $content = preg_replace("/\\\$table_prefix\s*=\s*'.*?';/", "\$table_prefix = '" . addslashes($prefix) . "';", $content);

    file_put_contents($config_file, $content);
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SiteMover Installer - Ripristino & Migrazione</title>
    <style>
        {{SITEMOVER_INSTALLER_CSS}}
    </style>
</head>
<body>
    <div class="installer-container">
        <div class="installer-header">
            <h1>SiteMover Standalone Installer</h1>
            <p>Procedura guidata per il ripristino e la migrazione del sito</p>
        </div>

        <!-- STEP WIZARD NAV -->
        <div class="wizard-steps">
            <div class="wizard-step active" id="wstep-1">1. Requisiti</div>
            <div class="wizard-step" id="wstep-2">2. Database</div>
            <div class="wizard-step" id="wstep-3">3. Estrazione</div>
            <div class="wizard-step" id="wstep-4">4. Configurazione</div>
            <div class="wizard-step" id="wstep-5">5. Completato</div>
        </div>

        <div class="installer-card">
            <!-- STEP 1: System Requirements -->
            <div class="step-panel active" id="panel-step-1">
                <h2>1. Verifica Requisiti di Sistema</h2>
                <p>Analisi della configurazione dell'hosting bersaglio per garantire il corretto funzionamento:</p>
                <div id="requirements-list" class="req-list">
                    <div class="loading">Verifica requisiti in corso...</div>
                </div>
                <div class="panel-actions">
                    <button type="button" class="btn btn-primary" id="btn-to-step-2" disabled>Avanti: Configura Database &rarr;</button>
                </div>
            </div>

            <!-- STEP 2: Database Configuration -->
            <div class="step-panel" id="panel-step-2">
                <h2>2. Configurazione Database</h2>
                <p>Inserisci i dati di connessione al database del tuo nuovo hosting:</p>
                <form id="db-config-form">
                    <div class="form-group">
                        <label>Host Database:</label>
                        <input type="text" id="db_host" value="localhost" required>
                    </div>
                    <div class="form-group">
                        <label>Nome Database:</label>
                        <input type="text" id="db_name" required placeholder="es. my_new_wp_db">
                    </div>
                    <div class="form-group">
                        <label>Utente Database:</label>
                        <input type="text" id="db_user" required placeholder="es. db_user">
                    </div>
                    <div class="form-group">
                        <label>Password Database:</label>
                        <input type="password" id="db_pass" placeholder="Password MySQL">
                    </div>
                    <div class="form-group">
                        <label>Prefisso Tabelle Originario:</label>
                        <input type="text" id="db_prefix" value="<?php echo esc_attr($manifest['db_prefix']); ?>" readonly>
                    </div>
                </form>
                <div id="db-test-msg" class="msg-box"></div>
                <div class="panel-actions">
                    <button type="button" class="btn btn-secondary" id="btn-test-db">Test Connessione DB</button>
                    <button type="button" class="btn btn-primary" id="btn-to-step-3" disabled>Avanti: Estrai File &rarr;</button>
                </div>
            </div>

            <!-- STEP 3: Unzip Archive -->
            <div class="step-panel" id="panel-step-3">
                <h2>3. Estrazione Archivio Sito</h2>
                <p>Estrazione dei file dall'archivio <code><?php echo esc_html($manifest['archive_filename']); ?></code>...</p>
                <div class="progress-bar-container">
                    <div class="progress-bar-fill" id="unzip-progress" style="width: 0%;"></div>
                </div>
                <div id="unzip-status-msg" class="msg-box">In attesa di avvio...</div>
                <div class="panel-actions">
                    <button type="button" class="btn btn-primary" id="btn-start-unzip">Avvia Estrazione File</button>
                </div>
            </div>

            <!-- STEP 4: DB Import & Search Replace -->
            <div class="step-panel" id="panel-step-4">
                <h2>4. Ripristino DB & Configurazione URL</h2>
                <p>Verifica l'URL del nuovo sito per l'aggiornamento automatico nel database:</p>
                <div class="form-group">
                    <label>URL Vecchio Sito (Sorgente):</label>
                    <input type="text" value="<?php echo esc_attr($manifest['site_url']); ?>" readonly>
                </div>
                <div class="form-group">
                    <label>URL Nuovo Sito (Destinazione):</label>
                    <input type="text" id="new_site_url" value="<?php echo 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST']; ?>">
                </div>
                <div id="import-status-msg" class="msg-box"></div>
                <div class="panel-actions">
                    <button type="button" class="btn btn-primary" id="btn-run-import">Esegui Ripristino DB & Sostituzione URL</button>
                </div>
            </div>

            <!-- STEP 5: Finalization -->
            <div class="step-panel" id="panel-step-5">
                <h2>5. Migrazione Completata!</h2>
                <p class="success-text">🎉 Il tuo sito WordPress è stato ripristinato con successo!</p>
                <div class="info-box">
                    <p>Fai clic sul pulsante sottostante per eliminare i file di installazione temporanei per sicurezza.</p>
                </div>
                <div class="panel-actions">
                    <button type="button" class="btn btn-success" id="btn-cleanup-site">Completa & Pulisci File Installer</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        {{SITEMOVER_INSTALLER_JS}}
    </script>
</body>
</html>
