<?php
if (!defined('ABSPATH')) {
    exit;
}

class SiteMover_DB_Exporter {

    /**
     * Retrieves all WordPress database tables.
     */
    public static function get_tables() {
        global $wpdb;
        return $wpdb->get_col("SHOW TABLES LIKE '{$wpdb->prefix}%'");
    }

    /**
     * Appends SQL string directly to output file stream.
     */
    private static function write($handle, $string) {
        fwrite($handle, $string . "\n");
    }

    /**
     * Writes database header information.
     */
    public static function write_header($file_path) {
        global $wpdb;
        $handle = fopen($file_path, 'w');
        if (!$handle) {
            return false;
        }

        self::write($handle, "-- =========================================================");
        self::write($handle, "-- SiteMover Database Export");
        self::write($handle, "-- Exported: " . date('Y-m-d H:i:s'));
        self::write($handle, "-- WordPress Version: " . get_bloginfo('version'));
        self::write($handle, "-- Site URL: " . get_option('siteurl'));
        self::write($handle, "-- Table Prefix: " . $wpdb->prefix);
        self::write($handle, "-- =========================================================\n");
        self::write($handle, "SET FOREIGN_KEY_CHECKS = 0;");
        self::write($handle, "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';");
        self::write($handle, "SET time_zone = '+00:00';\n");

        fclose($handle);
        return true;
    }

    /**
     * Writes footer to finalize database SQL file.
     */
    public static function write_footer($file_path) {
        $handle = fopen($file_path, 'a');
        if (!$handle) {
            return false;
        }
        self::write($handle, "\nSET FOREIGN_KEY_CHECKS = 1;");
        self::write($handle, "-- End of SiteMover DB Export");
        fclose($handle);
        return true;
    }

    /**
     * Dumps schema (CREATE TABLE) for a table.
     */
    public static function dump_schema($file_path, $table) {
        global $wpdb;
        $handle = fopen($file_path, 'a');
        if (!$handle) {
            return false;
        }

        self::write($handle, "\n-- ---------------------------------------------------------");
        self::write($handle, "-- Table Structure for `{$table}`");
        self::write($handle, "-- ---------------------------------------------------------");
        self::write($handle, "DROP TABLE IF EXISTS `{$table}`;");

        $create_query = $wpdb->get_row("SHOW CREATE TABLE `{$table}`", ARRAY_N);
        if (isset($create_query[1])) {
            self::write($handle, $create_query[1] . ";\n");
        }

        fclose($handle);
        return true;
    }

    /**
     * Dumps a chunk of rows for a table using LIMIT $offset, $limit.
     *
     * @return array [ 'rows_dumped' => int, 'has_more' => bool, 'next_offset' => int ]
     */
    public static function dump_data_chunk($file_path, $table, $offset = 0, $limit = 1000) {
        global $wpdb;

        $handle = fopen($file_path, 'a');
        if (!$handle) {
            return array('rows_dumped' => 0, 'has_more' => false, 'next_offset' => $offset);
        }

        // Fetch total rows count for offset check
        $total_rows = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");
        if ($total_rows === 0 || $offset >= $total_rows) {
            fclose($handle);
            return array('rows_dumped' => 0, 'has_more' => false, 'next_offset' => $offset);
        }

        $rows = $wpdb->get_results("SELECT * FROM `{$table}` LIMIT {$offset}, {$limit}", ARRAY_A);
        if (empty($rows)) {
            fclose($handle);
            return array('rows_dumped' => 0, 'has_more' => false, 'next_offset' => $offset);
        }

        self::write($handle, "-- Dumping data for table `{$table}` (offset: {$offset})");

        $columns = array_keys($rows[0]);
        $escaped_cols = array_map(function($col) {
            return "`" . str_replace("`", "``", $col) . "`";
        }, $columns);
        $col_names = implode(', ', $escaped_cols);

        $values_list = array();
        foreach ($rows as $row) {
            $vals = array();
            foreach ($columns as $col) {
                $val = $row[$col];
                if (is_null($val)) {
                    $vals[] = 'NULL';
                } else {
                    $vals[] = "'" . esc_sql($val) . "'";
                }
            }
            $values_list[] = "(" . implode(', ', $vals) . ")";
        }

        if (!empty($values_list)) {
            $insert_query = "INSERT INTO `{$table}` ({$col_names}) VALUES\n" . implode(",\n", $values_list) . ";";
            self::write($handle, $insert_query);
        }

        fclose($handle);

        $dumped_count = count($rows);
        $next_offset = $offset + $dumped_count;
        $has_more = ($next_offset < $total_rows);

        return array(
            'rows_dumped' => $dumped_count,
            'has_more'    => $has_more,
            'next_offset' => $next_offset
        );
    }
}
