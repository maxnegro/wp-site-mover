<?php
if (!defined('ABSPATH')) {
    exit;
}

class SiteMover_Admin {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_post_sitemover_download_file', array($this, 'handle_download_file'));

        // AJAX Hooks for Package Building
        add_action('wp_ajax_sitemover_init_package', array($this, 'ajax_init_package'));
        add_action('wp_ajax_sitemover_dump_db_chunk', array($this, 'ajax_dump_db_chunk'));
        add_action('wp_ajax_sitemover_scan_files', array($this, 'ajax_scan_files'));
        add_action('wp_ajax_sitemover_build_archive_chunk', array($this, 'ajax_build_archive_chunk'));
        add_action('wp_ajax_sitemover_finalize_package', array($this, 'ajax_finalize_package'));
        add_action('wp_ajax_sitemover_delete_package', array($this, 'ajax_delete_package'));
    }

    public function add_admin_menu() {
        add_menu_page(
            'SiteMover Backup & Migration',
            'SiteMover',
            'manage_options',
            'site-mover',
            array($this, 'render_admin_page'),
            'dashicons-download',
            75
        );
    }

    public function enqueue_assets($hook) {
        if ($hook !== 'toplevel_page_site-mover') {
            return;
        }

        wp_enqueue_style('sitemover-admin-css', SITEMOVER_URL . 'assets/css/admin.css', array(), SITEMOVER_VERSION);
        wp_enqueue_script('sitemover-admin-js', SITEMOVER_URL . 'assets/js/admin.js', array('jquery'), SITEMOVER_VERSION, true);

        wp_localize_script('sitemover-admin-js', 'SiteMoverVars', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('sitemover_admin_nonce'),
        ));
    }

    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Non hai i permessi sufficienti per accedere a questa pagina.'));
        }

        $packages = SiteMover_Package_Manager::list_packages();
        require_once SITEMOVER_PATH . 'templates/admin-page.php';
    }

    private function verify_nonce() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sitemover_admin_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed. Nonce invalid.'));
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions.'));
        }
    }

    /**
     * AJAX Step 1: Initialize Package Run
     */
    public function ajax_init_package() {
        $this->verify_nonce();

        $package_id = 'pkg_' . date('Ymd_His') . '_' . wp_generate_password(6, false, false);
        $package_dir = SiteMover_Package_Manager::create_package_dir($package_id);
        $package_key = SiteMover_Package_Manager::generate_package_key();

        $manifest = array(
            'package_id'       => $package_id,
            'created_at'       => date('Y-m-d H:i:s'),
            'site_name'        => get_bloginfo('name'),
            'site_url'         => get_option('siteurl'),
            'home_url'         => get_option('home'),
            'wp_version'       => get_bloginfo('version'),
            'db_prefix'        => $GLOBALS['wpdb']->prefix,
            'archive_filename' => 'archive_' . $package_id . '.zip',
            'package_key'      => $package_key,
            'tables_count'     => 0,
            'files_count'      => 0,
        );

        SiteMover_Package_Manager::save_manifest($package_id, $manifest);

        // Prepare Database Export Header
        $db_file = $package_dir . '/database.sql';
        SiteMover_DB_Exporter::write_header($db_file);

        // Fetch tables list
        $tables = SiteMover_DB_Exporter::get_tables();

        // Save state file
        $state = array(
            'package_id'    => $package_id,
            'tables'        => $tables,
            'current_table' => 0,
            'table_offset'  => 0,
            'files'         => array(),
            'file_index'    => 0,
        );

        file_put_contents($package_dir . '/state.json', wp_json_encode($state));

        wp_send_json_success(array(
            'package_id'   => $package_id,
            'total_tables' => count($tables),
            'message'      => 'Inizializzazione completata. Preparazione dump database...',
        ));
    }

    /**
     * AJAX Step 2: Dump Database Chunk
     */
    public function ajax_dump_db_chunk() {
        $this->verify_nonce();

        $package_id = isset($_POST['package_id']) ? sanitize_text_field($_POST['package_id']) : '';
        $package_dir = SiteMover_Package_Manager::get_package_dir($package_id);
        $state_file = $package_dir . '/state.json';

        if (!file_exists($state_file)) {
            wp_send_json_error(array('message' => 'State file missing for package.'));
        }

        $state = json_decode(file_get_contents($state_file), true);
        $tables = $state['tables'];
        $current_table_idx = $state['current_table'];
        $table_offset = $state['table_offset'];
        $db_file = $package_dir . '/database.sql';

        if ($current_table_idx >= count($tables)) {
            // DB Dump completed!
            SiteMover_DB_Exporter::write_footer($db_file);

            wp_send_json_success(array(
                'completed' => true,
                'message'   => 'Dump database completato. Scansione file del sito in corso...',
            ));
        }

        $table_name = $tables[$current_table_idx];

        // If offset is 0, write table schema first
        if ($table_offset === 0) {
            SiteMover_DB_Exporter::dump_schema($db_file, $table_name);
        }

        // Dump data chunk
        $res = SiteMover_DB_Exporter::dump_data_chunk($db_file, $table_name, $table_offset, 1500);

        if ($res['has_more']) {
            $state['table_offset'] = $res['next_offset'];
        } else {
            // Move to next table
            $state['current_table'] = $current_table_idx + 1;
            $state['table_offset'] = 0;
        }

        file_put_contents($state_file, wp_json_encode($state));

        $progress_pct = round((($current_table_idx + 1) / max(1, count($tables))) * 100);

        wp_send_json_success(array(
            'completed'    => false,
            'table_name'   => $table_name,
            'rows_dumped'  => $res['rows_dumped'],
            'progress_pct' => $progress_pct,
            'message'      => "Esportazione tabella {$table_name} ({$res['rows_dumped']} righe)...",
        ));
    }

    /**
     * AJAX Step 3: Scan Site Files
     */
    public function ajax_scan_files() {
        $this->verify_nonce();

        $package_id = isset($_POST['package_id']) ? sanitize_text_field($_POST['package_id']) : '';
        $package_dir = SiteMover_Package_Manager::get_package_dir($package_id);
        $state_file = $package_dir . '/state.json';

        if (!file_exists($state_file)) {
            wp_send_json_error(array('message' => 'State file missing.'));
        }

        $state = json_decode(file_get_contents($state_file), true);

        // Scan files
        $files = SiteMover_Archive_Builder::scan_files();
        $state['files'] = $files;
        $state['file_index'] = 0;

        file_put_contents($state_file, wp_json_encode($state));

        // Update manifest
        $manifest = SiteMover_Package_Manager::get_manifest($package_id);
        if ($manifest) {
            $manifest['tables_count'] = count($state['tables']);
            $manifest['files_count']  = count($files);
            SiteMover_Package_Manager::save_manifest($package_id, $manifest);
        }

        wp_send_json_success(array(
            'total_files' => count($files),
            'message'     => "Trovati " . count($files) . " file. Avvio creazione archivio ZIP...",
        ));
    }

    /**
     * AJAX Step 4: Build Archive Chunk
     */
    public function ajax_build_archive_chunk() {
        $this->verify_nonce();

        $package_id = isset($_POST['package_id']) ? sanitize_text_field($_POST['package_id']) : '';
        $package_dir = SiteMover_Package_Manager::get_package_dir($package_id);
        $state_file = $package_dir . '/state.json';

        if (!file_exists($state_file)) {
            wp_send_json_error(array('message' => 'State file missing.'));
        }

        $state = json_decode(file_get_contents($state_file), true);
        $manifest = SiteMover_Package_Manager::get_manifest($package_id);

        $zip_path = $package_dir . '/' . $manifest['archive_filename'];
        $files = $state['files'];
        $start_index = $state['file_index'];

        if (empty($files) || $start_index >= count($files)) {
            wp_send_json_success(array(
                'completed' => true,
                'message'   => 'File aggiunti all\'archivio. Generazione installer...',
            ));
        }

        // Add chunk of 300 files
        $res = SiteMover_Archive_Builder::add_files_chunk($zip_path, $files, $start_index, 300);

        if (isset($res['error'])) {
            wp_send_json_error(array('message' => $res['error']));
        }

        $state['file_index'] = $res['next_index'];
        file_put_contents($state_file, wp_json_encode($state));

        $total_files = count($files);
        $pct = round(($res['next_index'] / max(1, $total_files)) * 100);

        wp_send_json_success(array(
            'completed'    => $res['completed'],
            'processed'    => $res['processed'],
            'next_index'   => $res['next_index'],
            'total_files'  => $total_files,
            'progress_pct' => $pct,
            'message'      => "Compressione in corso: {$res['next_index']}/{$total_files} file ({$pct}%)...",
        ));
    }

    /**
     * AJAX Step 5: Finalize Package & Generate Standalone Installer
     */
    public function ajax_finalize_package() {
        $this->verify_nonce();

        $package_id = isset($_POST['package_id']) ? sanitize_text_field($_POST['package_id']) : '';
        $package_dir = SiteMover_Package_Manager::get_package_dir($package_id);
        $manifest = SiteMover_Package_Manager::get_manifest($package_id);

        $db_file = $package_dir . '/database.sql';
        $zip_path = $package_dir . '/' . $manifest['archive_filename'];

        // Add database.sql into ZIP package
        if (file_exists($db_file)) {
            SiteMover_Archive_Builder::add_single_file($zip_path, $db_file, 'database.sql');
            @unlink($db_file); // remove uncompressed sql from package folder
        }

        // Add manifest.json into ZIP package
        $manifest_file = $package_dir . '/manifest.json';
        if (file_exists($manifest_file)) {
            SiteMover_Archive_Builder::add_single_file($zip_path, $manifest_file, 'manifest.json');
        }

        // Generate Standalone installer.php
        SiteMover_Installer_Generator::create_installer($package_id, $manifest);

        // Remove state file
        @unlink($package_dir . '/state.json');

        wp_send_json_success(array(
            'package_id'  => $package_id,
            'package_key' => $manifest['package_key'],
            'message'     => 'Pacchetto creato con successo! Pronti per la migrazione/backup.',
        ));
    }

    /**
     * AJAX Action: Delete Package
     */
    public function ajax_delete_package() {
        $this->verify_nonce();

        $package_id = isset($_POST['package_id']) ? sanitize_text_field($_POST['package_id']) : '';
        $res = SiteMover_Package_Manager::delete_package($package_id);

        if ($res) {
            wp_send_json_success(array('message' => 'Pacchetto eliminato con successo.'));
        } else {
            wp_send_json_error(array('message' => 'Impossibile eliminare il pacchetto.'));
        }
    }

    /**
     * Admin Post Action: Secure Direct Download for Installer and Package ZIP
     */
    public function handle_download_file() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Non hai i permessi sufficienti per scaricare questo file.'));
        }

        $package_id = isset($_GET['package_id']) ? sanitize_text_field($_GET['package_id']) : '';
        $file_type  = isset($_GET['file_type']) ? sanitize_text_field($_GET['file_type']) : '';
        $nonce      = isset($_GET['_wpnonce']) ? sanitize_text_field($_GET['_wpnonce']) : '';

        if (!wp_verify_nonce($nonce, 'sitemover_download_' . $package_id)) {
            wp_die(__('Verifica di sicurezza fallita (nonce non valido).'));
        }

        $manifest = SiteMover_Package_Manager::get_manifest($package_id);
        if (!$manifest) {
            wp_die(__('Pacchetto non trovato.'));
        }

        $package_dir = SiteMover_Package_Manager::get_package_dir($package_id);

        if ($file_type === 'installer') {
            $file_path = $package_dir . '/installer.php';
            $download_name = 'installer.php';
            $mime = 'application/x-httpd-php';
        } elseif ($file_type === 'zip') {
            $file_path = $package_dir . '/' . $manifest['archive_filename'];
            $download_name = $manifest['archive_filename'];
            $mime = 'application/zip';
        } else {
            wp_die(__('Tipo di file non valido.'));
        }

        if (!file_exists($file_path) || !is_readable($file_path)) {
            wp_die(__('File non trovato sul server.'));
        }

        // Clean any output buffer
        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Description: File Transfer');
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . basename($download_name) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file_path));

        readfile($file_path);
        exit;
    }
}
