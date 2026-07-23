<?php
if (!defined('ABSPATH')) {
    exit;
}

class SiteMover {

    protected $admin;

    public function __construct() {
        $this->load_dependencies();
    }

    private function load_dependencies() {
        if (is_admin()) {
            $this->admin = new SiteMover_Admin();
        }
    }

    public function run() {
        // Run hooks if needed
    }
}
