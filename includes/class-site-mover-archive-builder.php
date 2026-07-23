<?php
if (!defined('ABSPATH')) {
    exit;
}

class SiteMover_Archive_Builder {

    /**
     * Scans site directory recursively and returns array of relative file paths.
     * Memory optimized scanning.
     */
    public static function scan_files($exclusions = array()) {
        $root_dir = ABSPATH;
        $files = array();

        $default_exclusions = array(
            'wp-content/uploads/site-mover-packages',
            'node_modules',
            '.git',
            '.svn',
            'wp-content/cache'
        );

        $merged_exclusions = array_merge($default_exclusions, $exclusions);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root_dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            $path = $file->getRealPath();
            $relative_path = ltrim(str_replace('\\', '/', substr($path, strlen($root_dir))), '/');

            // Skip matches with excluded paths
            $excluded = false;
            foreach ($merged_exclusions as $ex) {
                if (empty($ex)) continue;
                $ex = trim(str_replace('\\', '/', $ex), '/');
                if (strpos($relative_path, $ex) === 0) {
                    $excluded = true;
                    break;
                }
            }

            if ($excluded) {
                continue;
            }

            if ($file->isFile()) {
                $files[] = $relative_path;
            }
        }

        return $files;
    }

    /**
     * Adds a batch of files into ZipArchive.
     *
     * @param string $zip_path Absolute path to target ZIP file
     * @param array $files_list Array of relative file paths to compress
     * @param int $start_index Index to start processing from
     * @param int $batch_size Number of files to process per chunk
     * @return array [ 'processed' => int, 'next_index' => int, 'completed' => bool ]
     */
    public static function add_files_chunk($zip_path, $files_list, $start_index = 0, $batch_size = 300) {
        if (!class_exists('ZipArchive')) {
            return array('error' => 'PHP ZipArchive extension is missing.');
        }

        $zip = new ZipArchive();
        $res = $zip->open($zip_path, ZipArchive::CREATE);
        if ($res !== true) {
            return array('error' => 'Failed to open ZIP archive. Code: ' . $res);
        }

        $total_files = count($files_list);
        $end_index = min($start_index + $batch_size, $total_files);
        $processed = 0;

        for ($i = $start_index; $i < $end_index; $i++) {
            $relative_path = $files_list[$i];
            $absolute_path = ABSPATH . $relative_path;

            if (file_exists($absolute_path) && is_readable($absolute_path)) {
                // Large file streaming safety: ZipArchive::addFile uses stream pointers
                $zip->addFile($absolute_path, $relative_path);
                $processed++;
            }
        }

        $zip->close();

        $next_index = $end_index;
        $completed = ($next_index >= $total_files);

        return array(
            'processed'  => $processed,
            'next_index' => $next_index,
            'completed'  => $completed
        );
    }

    /**
     * Adds extra file (e.g. database dump or manifest) to ZIP archive.
     */
    public static function add_single_file($zip_path, $source_abs_path, $zip_internal_name) {
        if (!class_exists('ZipArchive')) return false;
        $zip = new ZipArchive();
        if ($zip->open($zip_path, ZipArchive::CREATE) === true) {
            $zip->addFile($source_abs_path, $zip_internal_name);
            $zip->close();
            return true;
        }
        return false;
    }
}
