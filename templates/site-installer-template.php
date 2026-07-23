<?php
/**
 * SiteMover Standalone Restoration Installer
 * Auto-generated single-file installer script.
 * Generated filename: site-installer.php
 */

// Disable memory limit restrictions if permitted
@ini_set('memory_limit', '512M');
@set_time_limit(300);

// Minimal translation fallback for standalone usage (no WordPress loaded)
if (!function_exists('__')) {
    function __($text, $domain = 'wp-site-mover') {
        return $text;
    }
}

// Manifest configuration placeholder (injected at package creation)
$manifest = json_decode('{{SITEMOVER_MANIFEST_JSON}}', true);

// =========================================================================
// AJAX ENDPOINTS HANDLER (Executed when POST request is sent to site-installer.php)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');

    $action = $_POST['action'];

    if ($action === 'check_requirements') {
        $checks = array(
            'php_version' => array(
                'label'   => __('PHP Version (>= 7.4)', 'wp-site-mover'),
                'pass'    => version_compare(PHP_VERSION, '7.4.0', '>='),
                'details' => sprintf(__('Current version: %s', 'wp-site-mover'), PHP_VERSION),
            ),
            'mysqli' => array(
                'label'   => __('MySQLi Extension', 'wp-site-mover'),
                'pass'    => extension_loaded('mysqli'),
                'details' => extension_loaded('mysqli') ? __('Available', 'wp-site-mover') : __('Missing', 'wp-site-mover'),
            ),
            'zip' => array(
                'label'   => __('ZipArchive / Zlib Extension', 'wp-site-mover'),
                'pass'    => class_exists('ZipArchive'),
                'details' => class_exists('ZipArchive') ? __('Available', 'wp-site-mover') : __('Missing', 'wp-site-mover'),
            ),
            'writable' => array(
                'label'   => __('Root Server Write Permissions', 'wp-site-mover'),
                'pass'    => is_writable(__DIR__),
                'details' => is_writable(__DIR__) ? __('Writable', 'wp-site-mover') : __('Not Writable', 'wp-site-mover'),
            ),
        );

        $archive_file = isset($manifest['archive_filename']) ? $manifest['archive_filename'] : '';
        $archive_exists = file_exists(__DIR__ . '/' . $archive_file);

        $checks['archive'] = array(
            'label'   => sprintf(__('Archive Presence (%s)', 'wp-site-mover'), $archive_file),
            'pass'    => $archive_exists,
            'details' => $archive_exists ? __('Found', 'wp-site-mover') : __('Not found in current directory', 'wp-site-mover'),
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
            echo json_encode(array('success' => false, 'message' => sprintf(__('Unable to connect to MySQL: %s', 'wp-site-mover'), mysqli_connect_error())));
            exit;
        }

        $select_db = @mysqli_select_db($conn, $name);
        if (!$select_db) {
            // Try to create DB if it doesn't exist
            $created = @mysqli_query($conn, "CREATE DATABASE IF NOT EXISTS `" . mysqli_real_escape_string($conn, $name) . "` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            if (!$created) {
                echo json_encode(array('success' => false, 'message' => sprintf(__("Database '%s' not found and cannot be created: %s", 'wp-site-mover'), $name, mysqli_error($conn))));
                exit;
            }
        }

        echo json_encode(array('success' => true, 'message' => __('Database connection successful!', 'wp-site-mover')));
        exit;
    }

    if ($action === 'unzip_archive') {
        $archive_file = __DIR__ . '/' . $manifest['archive_filename'];
        if (!file_exists($archive_file)) {
            echo json_encode(array('success' => false, 'message' => __('ZIP archive file not found.', 'wp-site-mover')));
            exit;
        }

        $zip = new ZipArchive();
        if ($zip->open($archive_file) === true) {
            $zip->extractTo(__DIR__);
            $zip->close();
            echo json_encode(array('success' => true, 'message' => __('Archive extraction completed successfully!', 'wp-site-mover')));
        } else {
            echo json_encode(array('success' => false, 'message' => __('Unable to open and extract the ZIP file.', 'wp-site-mover')));
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
            echo json_encode(array('success' => false, 'message' => __('Database connection failed.', 'wp-site-mover')));
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

        echo json_encode(array('success' => true, 'message' => __('Database restoration and site configuration completed!', 'wp-site-mover')));
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

        echo json_encode(array('success' => true, 'message' => __('Cleanup completed. Your new site is ready!', 'wp-site-mover')));
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
<html lang="en-US">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php _e('SiteMover Installer - Restoration & Migration', 'wp-site-mover'); ?></title>
    <style>
        {{SITEMOVER_INSTALLER_CSS}}
    </style>
</head>
<body>
    <div class="installer-container">
        <div class="installer-header">
            <h1><?php _e('SiteMover Standalone Installer', 'wp-site-mover'); ?></h1>
            <p><?php _e('Guided procedure for site restoration and migration', 'wp-site-mover'); ?></p>
        </div>

        <!-- STEP WIZARD NAV -->
        <div class="wizard-steps">
            <div class="wizard-step active" id="wstep-1"><?php _e('1. Requirements', 'wp-site-mover'); ?></div>
            <div class="wizard-step" id="wstep-2"><?php _e('2. Database', 'wp-site-mover'); ?></div>
            <div class="wizard-step" id="wstep-3"><?php _e('3. Extraction', 'wp-site-mover'); ?></div>
            <div class="wizard-step" id="wstep-4"><?php _e('4. Configuration', 'wp-site-mover'); ?></div>
            <div class="wizard-step" id="wstep-5"><?php _e('5. Completed', 'wp-site-mover'); ?></div>
        </div>

        <div class="installer-card">
            <!-- STEP 1: System Requirements -->
            <div class="step-panel active" id="panel-step-1">
                <h2><?php _e('1. System Requirements Check', 'wp-site-mover'); ?></h2>
                <p><?php _e('Analysis of the target hosting configuration to ensure proper operation:', 'wp-site-mover'); ?></p>
                <div id="requirements-list" class="req-list">
                    <div class="loading"><?php _e('Checking requirements in progress...', 'wp-site-mover'); ?></div>
                </div>
                <div class="panel-actions">
                    <button type="button" class="btn btn-primary" id="btn-to-step-2" disabled><?php _e('Next: Database Configuration', 'wp-site-mover'); ?> &rarr;</button>
                </div>
            </div>

            <!-- STEP 2: Database Configuration -->
            <div class="step-panel" id="panel-step-2">
                <h2><?php _e('2. Database Configuration', 'wp-site-mover'); ?></h2>
                <p><?php _e('Enter the database connection details of your new hosting:', 'wp-site-mover'); ?></p>
                <form id="db-config-form">
                    <div class="form-group">
                        <label><?php _e('Database Host:', 'wp-site-mover'); ?></label>
                        <input type="text" id="db_host" value="localhost" required>
                    </div>
                    <div class="form-group">
                        <label><?php _e('Database Name:', 'wp-site-mover'); ?></label>
                        <input type="text" id="db_name" required placeholder="<?php _e('e.g. my_new_wp_db', 'wp-site-mover'); ?>">
                    </div>
                    <div class="form-group">
                        <label><?php _e('Database User:', 'wp-site-mover'); ?></label>
                        <input type="text" id="db_user" required placeholder="<?php _e('e.g. db_user', 'wp-site-mover'); ?>">
                    </div>
                    <div class="form-group">
                        <label><?php _e('Database Password:', 'wp-site-mover'); ?></label>
                        <input type="password" id="db_pass" placeholder="<?php _e('MySQL Password', 'wp-site-mover'); ?>">
                    </div>
                    <div class="form-group">
                        <label><?php _e('Original Table Prefix:', 'wp-site-mover'); ?></label>
                        <input type="text" id="db_prefix" value="<?php echo esc_attr($manifest['db_prefix']); ?>" readonly>
                    </div>
                </form>
                <div id="db-test-msg" class="msg-box"></div>
                <div class="panel-actions">
                    <button type="button" class="btn btn-secondary" id="btn-test-db"><?php _e('Test DB Connection', 'wp-site-mover'); ?></button>
                    <button type="button" class="btn btn-primary" id="btn-to-step-3" disabled><?php _e('Next: Extract Files', 'wp-site-mover'); ?> &rarr;</button>
                </div>
            </div>

            <!-- STEP 3: Unzip Archive -->
            <div class="step-panel" id="panel-step-3">
                <h2><?php _e('3. Site Archive Extraction', 'wp-site-mover'); ?></h2>
                <p><?php printf(__('Extracting files from archive %s...', 'wp-site-mover'), esc_html($manifest['archive_filename'])); ?></p>
                <div class="progress-bar-container">
                    <div class="progress-bar-fill" id="unzip-progress" style="width: 0%;"></div>
                </div>
                <div id="unzip-status-msg" class="msg-box"><?php _e('Waiting to start...', 'wp-site-mover'); ?></div>
                <div class="panel-actions">
                    <button type="button" class="btn btn-primary" id="btn-start-unzip"><?php _e('Start File Extraction', 'wp-site-mover'); ?></button>
                </div>
            </div>

            <!-- STEP 4: DB Import & Search Replace -->
            <div class="step-panel" id="panel-step-4">
                <h2><?php _e('4. DB Restoration & URL Configuration', 'wp-site-mover'); ?></h2>
                <p><?php _e('Verify the new site URL for automatic database update:', 'wp-site-mover'); ?></p>
                <div class="form-group">
                    <label><?php _e('Old Site URL (Source):', 'wp-site-mover'); ?></label>
                    <input type="text" value="<?php echo esc_attr($manifest['site_url']); ?>" readonly>
                </div>
                <div class="form-group">
                    <label><?php _e('New Site URL (Destination):', 'wp-site-mover'); ?></label>
                    <input type="text" id="new_site_url" value="<?php echo 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST']; ?>">
                </div>
                <div id="import-status-msg" class="msg-box"></div>
                <div class="panel-actions">
                    <button type="button" class="btn btn-primary" id="btn-run-import"><?php _e('Execute DB Restoration & URL Replacement', 'wp-site-mover'); ?></button>
                </div>
            </div>

            <!-- STEP 5: Finalization -->
            <div class="step-panel" id="panel-step-5">
                <h2><?php _e('5. Migration Completed!', 'wp-site-mover'); ?></h2>
                <p class="success-text">🎉 <?php _e('Your WordPress site has been successfully restored!', 'wp-site-mover'); ?></p>
                <div class="info-box">
                    <p><?php _e('Click the button below to delete temporary installation files for security.', 'wp-site-mover'); ?></p>
                </div>
                <div class="panel-actions">
                    <button type="button" class="btn btn-success" id="btn-cleanup-site"><?php _e('Complete & Clean Installer Files', 'wp-site-mover'); ?></button>
                </div>
            </div>
        </div>
    </div>

    <script>
        {{SITEMOVER_INSTALLER_JS}}
    </script>
</body>
</html>
