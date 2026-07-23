<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap sitemover-wrap">
    <div class="sitemover-header">
        <div class="sitemover-brand">
            <div class="sitemover-logo-icon">
                <span class="dashicons dashicons-database-export"></span>
            </div>
            <div>
                <h1>SiteMover <span class="badge">v<?php echo SITEMOVER_VERSION; ?></span></h1>
                <p class="subtitle"><?php _e('Zero-Downtime Backup, Migration and Cloning for WordPress', 'wp-site-mover'); ?></p>
            </div>
        </div>
    </div>

    <!-- Main Container -->
    <div class="sitemover-grid">
        <!-- Left Panel: Create Backup Package -->
        <div class="sitemover-card sitemover-card-primary">
            <div class="card-header">
                <h2><span class="dashicons dashicons-plus-alt2"></span> <?php _e('Create New Package', 'wp-site-mover'); ?></h2>
                <p><?php _e('Generate a standalone ZIP archive with files, database, and site-installer.php script', 'wp-site-mover'); ?></p>
            </div>
            <div class="card-body">
                <div id="sitemover-idle-view">
                    <div class="info-box">
                        <div class="info-item">
                            <span class="dashicons dashicons-admin-site"></span>
                            <strong><?php _e('Source Site:', 'wp-site-mover'); ?></strong> <?php echo esc_html(get_bloginfo('name')); ?> (<?php echo esc_html(get_option('siteurl')); ?>)
                        </div>
                        <div class="info-item">
                            <span class="dashicons dashicons-wordpress"></span>
                            <strong><?php _e('WP Version:', 'wp-site-mover'); ?></strong> <?php echo esc_html(get_bloginfo('version')); ?>
                        </div>
                    </div>

                    <button type="button" id="sitemover-start-btn" class="button button-primary button-hero">
                        <span class="dashicons dashicons-cloud-upload"></span> <?php _e('Start Package Creation', 'wp-site-mover'); ?>
                    </button>
                </div>

                <!-- Progress Section (Hidden initially) -->
                <div id="sitemover-progress-view" style="display:none;">
                    <div class="progress-steps">
                        <div class="step-item" id="step-init">
                            <span class="step-num">1</span>
                            <span class="step-label"><?php _e('Initialization', 'wp-site-mover'); ?></span>
                        </div>
                        <div class="step-item" id="step-db">
                            <span class="step-num">2</span>
                            <span class="step-label"><?php _e('Database Dump', 'wp-site-mover'); ?></span>
                        </div>
                        <div class="step-item" id="step-scan">
                            <span class="step-num">3</span>
                            <span class="step-label"><?php _e('File Scan', 'wp-site-mover'); ?></span>
                        </div>
                        <div class="step-item" id="step-zip">
                            <span class="step-num">4</span>
                            <span class="step-label"><?php _e('ZIP Compression', 'wp-site-mover'); ?></span>
                        </div>
                        <div class="step-item" id="step-finalize">
                            <span class="step-num">5</span>
                            <span class="step-label"><?php _e('Installer', 'wp-site-mover'); ?></span>
                        </div>
                    </div>

                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" id="sitemover-progress-fill" style="width: 0%;"></div>
                    </div>

                    <div class="status-message" id="sitemover-status-msg">
                        <?php _e('Initialization in progress...', 'wp-site-mover'); ?>
                    </div>

                    <div class="log-output-box" id="sitemover-log-box"></div>
                </div>
            </div>
        </div>

        <!-- Right Panel: Instructions -->
        <div class="sitemover-card sitemover-card-secondary">
            <div class="card-header">
                <h2><span class="dashicons dashicons-welcome-learn-more"></span> <?php _e('How to Migrate to a New Server', 'wp-site-mover'); ?></h2>
            </div>
            <div class="card-body">
                <ol class="instructions-list">
                    <li>
                        <strong><?php _e('Create the package:', 'wp-site-mover'); ?></strong> <?php _e('Click on "Start Package Creation" and wait for the process to complete.', 'wp-site-mover'); ?>
                    </li>
                    <li>
                        <strong><?php _e('Download the two files:', 'wp-site-mover'); ?></strong> <?php _e('Download the ZIP archive (`archive_pkg_....zip`) and the `site-installer.php` file.', 'wp-site-mover'); ?>
                    </li>
                    <li>
                        <strong><?php _e('Upload to the target hosting:', 'wp-site-mover'); ?></strong> <?php _e('Upload both files to the root of your new server/hosting (also works on empty servers without WordPress).', 'wp-site-mover'); ?>
                    </li>
                    <li>
                        <strong><?php _e('Run the installation:', 'wp-site-mover'); ?></strong> <?php _e('Open your browser at `https://your-new-domain.com/site-installer.php` and follow the 5-step guided procedure!', 'wp-site-mover'); ?>
                    </li>
                </ol>
            </div>
        </div>
    </div>

    <!-- Existing Packages Section -->
    <div class="sitemover-card sitemover-packages-list-card">
        <div class="card-header">
                <h2><span class="dashicons dashicons-archive"></span> <?php _e('Existing Packages and Backups', 'wp-site-mover'); ?></h2>
        </div>
        <div class="card-body">
            <?php if (empty($packages)): ?>
                <div class="empty-packages">
                    <span class="dashicons dashicons-info"></span> <?php _e('No packages found. Create your first backup above.', 'wp-site-mover'); ?>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped sitemover-table">
                    <thead>
                        <tr>
                            <th><?php _e('Creation Date', 'wp-site-mover'); ?></th>
                            <th><?php _e('Package ID', 'wp-site-mover'); ?></th>
                            <th><?php _e('Site Details', 'wp-site-mover'); ?></th>
                            <th><?php _e('Archive Size', 'wp-site-mover'); ?></th>
                            <th><?php _e('Security Key', 'wp-site-mover'); ?></th>
                            <th style="width: 250px;"><?php _e('Actions', 'wp-site-mover'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($packages as $pkg): ?>
                            <tr>
                                <td><strong><?php echo esc_html($pkg['created_at']); ?></strong></td>
                                <td><code><?php echo esc_html($pkg['package_id']); ?></code></td>
                                <td>
                                    <?php echo esc_html($pkg['site_name']); ?><br>
                                    <small class="text-muted"><?php echo esc_html($pkg['tables_count']); ?> tabelle | <?php echo esc_html($pkg['files_count']); ?> file</small>
                                </td>
                                <td>
                                    <?php echo esc_html(size_format($pkg['archive_size'], 2)); ?>
                                </td>
                                <td>
                                    <code class="key-badge"><?php echo esc_html($pkg['package_key']); ?></code>
                                </td>
                                <td>
                                    <div class="actions-group">
                                        <?php if ($pkg['has_installer']): 
                                            $installer_url = wp_nonce_url(
                                                admin_url('admin-post.php?action=sitemover_download_file&package_id=' . $pkg['package_id'] . '&file_type=installer'),
                                                'sitemover_download_' . $pkg['package_id']
                                            );
                                        ?>
                                            <a href="<?php echo esc_url($installer_url); ?>" class="button button-small button-secondary" title="<?php esc_attr_e('Download Installer', 'wp-site-mover'); ?>">
                                                <span class="dashicons dashicons-download"></span> <?php _e('Installer', 'wp-site-mover'); ?>
                                            </a>
                                        <?php endif; ?>

                                        <?php if ($pkg['has_zip']): 
                                            $zip_url = wp_nonce_url(
                                                admin_url('admin-post.php?action=sitemover_download_file&package_id=' . $pkg['package_id'] . '&file_type=zip'),
                                                'sitemover_download_' . $pkg['package_id']
                                            );
                                        ?>
                                            <a href="<?php echo esc_url($zip_url); ?>" class="button button-small button-primary" title="<?php esc_attr_e('Download ZIP Archive', 'wp-site-mover'); ?>">
                                                <span class="dashicons dashicons-archive"></span> <?php _e('ZIP', 'wp-site-mover'); ?>
                                            </a>
                                        <?php endif; ?>

                                        <button type="button" class="button button-small button-link-delete sitemover-delete-btn" data-id="<?php echo esc_attr($pkg['package_id']); ?>">
                                            <span class="dashicons dashicons-trash"></span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
