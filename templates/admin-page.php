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
                <p class="subtitle">Backup, Migrazione e Clonazione a Zero-Downtime per WordPress</p>
            </div>
        </div>
    </div>

    <!-- Main Container -->
    <div class="sitemover-grid">
        <!-- Left Panel: Create Backup Package -->
        <div class="sitemover-card sitemover-card-primary">
            <div class="card-header">
                <h2><span class="dashicons dashicons-plus-alt2"></span> Crea Nuovo Pacchetto</h2>
                <p>Genera un archivio ZIP autonomo con file, database e script installer.php</p>
            </div>
            <div class="card-body">
                <div id="sitemover-idle-view">
                    <div class="info-box">
                        <div class="info-item">
                            <span class="dashicons dashicons-admin-site"></span>
                            <strong>Sito Sorgente:</strong> <?php echo esc_html(get_bloginfo('name')); ?> (<?php echo esc_html(get_option('siteurl')); ?>)
                        </div>
                        <div class="info-item">
                            <span class="dashicons dashicons-wordpress"></span>
                            <strong>Versione WP:</strong> <?php echo esc_html(get_bloginfo('version')); ?>
                        </div>
                    </div>

                    <button type="button" id="sitemover-start-btn" class="button button-primary button-hero">
                        <span class="dashicons dashicons-cloud-upload"></span> Avvia Creazione Pacchetto
                    </button>
                </div>

                <!-- Progress Section (Hidden initially) -->
                <div id="sitemover-progress-view" style="display:none;">
                    <div class="progress-steps">
                        <div class="step-item" id="step-init">
                            <span class="step-num">1</span>
                            <span class="step-label">Inizializzazione</span>
                        </div>
                        <div class="step-item" id="step-db">
                            <span class="step-num">2</span>
                            <span class="step-label">Dump DB</span>
                        </div>
                        <div class="step-item" id="step-scan">
                            <span class="step-num">3</span>
                            <span class="step-label">Scansione File</span>
                        </div>
                        <div class="step-item" id="step-zip">
                            <span class="step-num">4</span>
                            <span class="step-label">Compressione ZIP</span>
                        </div>
                        <div class="step-item" id="step-finalize">
                            <span class="step-num">5</span>
                            <span class="step-label">Installer</span>
                        </div>
                    </div>

                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" id="sitemover-progress-fill" style="width: 0%;"></div>
                    </div>

                    <div class="status-message" id="sitemover-status-msg">
                        Inizializzazione in corso...
                    </div>

                    <div class="log-output-box" id="sitemover-log-box"></div>
                </div>
            </div>
        </div>

        <!-- Right Panel: Instructions -->
        <div class="sitemover-card sitemover-card-secondary">
            <div class="card-header">
                <h2><span class="dashicons dashicons-welcome-learn-more"></span> Come Migrare su Nuovo Server</h2>
            </div>
            <div class="card-body">
                <ol class="instructions-list">
                    <li>
                        <strong>Crea il pacchetto:</strong> Fai clic su <em>"Avvia Creazione Pacchetto"</em> ed attendi la fine del processo.
                    </li>
                    <li>
                        <strong>Scarica i due file:</strong> Scarica l'archivio ZIP (`archive_pkg_....zip`) ed il file `installer.php`.
                    </li>
                    <li>
                        <strong>Carica sull'hosting di destinazione:</strong> Carica entrambi i file nella root del tuo nuovo server/hosting (funziona anche su server vuoto senza WordPress).
                    </li>
                    <li>
                        <strong>Esegui l'installazione:</strong> Apri il browser all'indirizzo `https://tuo-nuovo-dominio.com/installer.php` e segui la procedura guidata a 5 passaggi!
                    </li>
                </ol>
            </div>
        </div>
    </div>

    <!-- Existing Packages Section -->
    <div class="sitemover-card sitemover-packages-list-card">
        <div class="card-header">
            <h2><span class="dashicons dashicons-archive"></span> Pacchetti e Backup Esistenti</h2>
        </div>
        <div class="card-body">
            <?php if (empty($packages)): ?>
                <div class="empty-packages">
                    <span class="dashicons dashicons-info"></span> Nessun pacchetto trovato. Crea il tuo primo backup sopra.
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped sitemover-table">
                    <thead>
                        <tr>
                            <th>Data Creazione</th>
                            <th>ID Pacchetto</th>
                            <th>Dettagli Sito</th>
                            <th>Dimensione Archivio</th>
                            <th>Chiave Sicurezza</th>
                            <th style="width: 250px;">Azioni</th>
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
                                            <a href="<?php echo esc_url($installer_url); ?>" class="button button-small button-secondary" title="Scarica Installer">
                                                <span class="dashicons dashicons-download"></span> Installer
                                            </a>
                                        <?php endif; ?>

                                        <?php if ($pkg['has_zip']): 
                                            $zip_url = wp_nonce_url(
                                                admin_url('admin-post.php?action=sitemover_download_file&package_id=' . $pkg['package_id'] . '&file_type=zip'),
                                                'sitemover_download_' . $pkg['package_id']
                                            );
                                        ?>
                                            <a href="<?php echo esc_url($zip_url); ?>" class="button button-small button-primary" title="Scarica Archivio ZIP">
                                                <span class="dashicons dashicons-archive"></span> ZIP
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
