<?php
if (!defined('ABSPATH')) {
    exit;
}

class SiteMover_Installer_Generator {

    /**
     * Builds and writes installer.php into package directory.
     */
    public static function create_installer($package_id, $manifest) {
        $package_dir = SiteMover_Package_Manager::get_package_dir($package_id);
        $installer_file = $package_dir . '/installer.php';

        $template_path = SITEMOVER_PATH . 'templates/installer-template.php';
        if (!file_exists($template_path)) {
            return false;
        }

        $css_path = SITEMOVER_PATH . 'assets/css/installer.css';
        $js_path  = SITEMOVER_PATH . 'assets/js/installer.js';

        $css_code = file_exists($css_path) ? file_get_contents($css_path) : '';
        $js_code  = file_exists($js_path) ? file_get_contents($js_path) : '';

        $template_content = file_get_contents($template_path);

        // Inject configuration variables and embedded assets into template
        $replacements = array(
            '{{SITEMOVER_MANIFEST_JSON}}' => json_encode($manifest, JSON_PRETTY_PRINT),
            '{{SITEMOVER_INSTALLER_CSS}}' => $css_code,
            '{{SITEMOVER_INSTALLER_JS}}'  => $js_code,
        );

        $final_content = str_replace(array_keys($replacements), array_values($replacements), $template_content);

        return file_put_contents($installer_file, $final_content) !== false;
    }
}
