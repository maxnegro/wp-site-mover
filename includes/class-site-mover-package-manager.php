<?php
if (!defined('ABSPATH')) {
    exit;
}

class SiteMover_Package_Manager {

    /**
     * Initializes storage directory and protects it with security files.
     */
    public static function init_storage_dir() {
        $dir = SITEMOVER_UPLOADS_DIR;

        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }

        // Add .htaccess to block direct browser directory listing and PHP execution inside package folder
        $htaccess_file = $dir . '/.htaccess';
        if (!file_exists($htaccess_file)) {
            $htaccess_content = "# Protect SiteMover Package Directory\n";
            $htaccess_content .= "<IfModule mod_authz_core.c>\n";
            $htaccess_content .= "    Require all granted\n";
            $htaccess_content .= "</IfModule>\n";
            $htaccess_content .= "<IfModule !mod_authz_core.c>\n";
            $htaccess_content .= "    Order allow,deny\n";
            $htaccess_content .= "    Allow from all\n";
            $htaccess_content .= "</IfModule>\n";
            $htaccess_content .= "Options -Indexes\n";
            @file_put_contents($htaccess_file, $htaccess_content);
        }

        // Add index.php safety file
        $index_file = $dir . '/index.php';
        if (!file_exists($index_file)) {
            @file_put_contents($index_file, '<?php // Silence is golden.');
        }
    }

    /**
     * Creates a unique package ID and directory folder.
     */
    public static function create_package_dir($package_id) {
        self::init_storage_dir();
        $path = SITEMOVER_UPLOADS_DIR . '/' . sanitize_file_name($package_id);
        if (!file_exists($path)) {
            wp_mkdir_p($path);
        }
        return $path;
    }

    /**
     * Generates cryptographic package key.
     */
    public static function generate_package_key() {
        return wp_generate_password(32, false);
    }

    /**
     * Writes manifest JSON for a package.
     */
    public static function save_manifest($package_id, $data) {
        $path = self::get_package_dir($package_id) . '/manifest.json';
        file_put_contents($path, wp_json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * Gets manifest JSON data for a package.
     */
    public static function get_manifest($package_id) {
        $path = self::get_package_dir($package_id) . '/manifest.json';
        if (file_exists($path)) {
            $content = file_get_contents($path);
            return json_decode($content, true);
        }
        return null;
    }

    /**
     * Returns full filesystem path for a package folder.
     */
    public static function get_package_dir($package_id) {
        return SITEMOVER_UPLOADS_DIR . '/' . sanitize_file_name($package_id);
    }

    /**
     * Lists all existing packages with their stats.
     */
    public static function list_packages() {
        self::init_storage_dir();
        $packages = array();

        if (!file_exists(SITEMOVER_UPLOADS_DIR)) {
            return $packages;
        }

        $dirs = glob(SITEMOVER_UPLOADS_DIR . '/*', GLOB_ONLYDIR);
        if (!$dirs) {
            return $packages;
        }

        foreach ($dirs as $dir) {
            $package_id = basename($dir);
            $manifest = self::get_manifest($package_id);

            if ($manifest) {
                $zip_file = $dir . '/' . $manifest['archive_filename'];
                $installer_file = $dir . '/installer.php';

                $packages[] = array(
                    'package_id'       => $package_id,
                    'created_at'       => isset($manifest['created_at']) ? $manifest['created_at'] : '',
                    'site_name'        => isset($manifest['site_name']) ? $manifest['site_name'] : '',
                    'site_url'         => isset($manifest['site_url']) ? $manifest['site_url'] : '',
                    'wp_version'       => isset($manifest['wp_version']) ? $manifest['wp_version'] : '',
                    'archive_filename' => isset($manifest['archive_filename']) ? $manifest['archive_filename'] : '',
                    'archive_size'     => file_exists($zip_file) ? filesize($zip_file) : 0,
                    'has_zip'          => file_exists($zip_file),
                    'has_installer'    => file_exists($installer_file),
                    'package_key'      => isset($manifest['package_key']) ? $manifest['package_key'] : '',
                    'tables_count'     => isset($manifest['tables_count']) ? $manifest['tables_count'] : 0,
                    'files_count'      => isset($manifest['files_count']) ? $manifest['files_count'] : 0,
                );
            }
        }

        // Sort newest first
        usort($packages, function($a, $b) {
            return strcmp($b['created_at'], $a['created_at']);
        });

        return $packages;
    }

    /**
     * Deletes a package directory completely.
     */
    public static function delete_package($package_id) {
        $dir = self::get_package_dir($package_id);
        if (!file_exists($dir) || !is_dir($dir)) {
            return false;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                @rmdir($file->getRealPath());
            } else {
                @unlink($file->getRealPath());
            }
        }

        return @rmdir($dir);
    }
}
