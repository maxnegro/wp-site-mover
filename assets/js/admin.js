jQuery(document).ready(function($) {
    var packageId = '';

    function logMsg(msg) {
        var box = $('#sitemover-log-box');
        box.append('<div>[' + new Date().toLocaleTimeString() + '] ' + msg + '</div>');
        box.scrollTop(box[0].scrollHeight);
    }

    function setStep(stepId, state) {
        var el = $('#' + stepId);
        if (state === 'active') {
            $('.step-item').removeClass('active');
            el.addClass('active');
        } else if (state === 'completed') {
            el.addClass('completed');
        }
    }

    function updateProgress(pct, msg) {
        $('#sitemover-progress-fill').css('width', pct + '%');
        if (msg) {
            $('#sitemover-status-msg').text(msg);
            logMsg(msg);
        }
    }

    // Handle Start Package Creation
    $('#sitemover-start-btn').on('click', function(e) {
        e.preventDefault();

        $('#sitemover-idle-view').hide();
        $('#sitemover-progress-view').show();
        $('#sitemover-log-box').empty();

        setStep('step-init', 'active');
        updateProgress(5, 'Inizializzazione sessione di backup...');

        // Step 1: Init Package
        $.post(SiteMoverVars.ajax_url, {
            action: 'sitemover_init_package',
            nonce: SiteMoverVars.nonce
        }, function(res) {
            if (!res.success) {
                alert('Errore: ' + (res.data ? res.data.message : 'Inizializzazione fallita'));
                return;
            }

            packageId = res.data.package_id;
            setStep('step-init', 'completed');
            setStep('step-db', 'active');
            updateProgress(15, 'Pacchetto creato (' + packageId + '). Avvio dump DB...');

            // Proceed to Step 2: DB Chunk
            runDbDumpChunk();
        });
    });

    // Step 2 Loop: Dump Database
    function runDbDumpChunk() {
        $.post(SiteMoverVars.ajax_url, {
            action: 'sitemover_dump_db_chunk',
            nonce: SiteMoverVars.nonce,
            package_id: packageId
        }, function(res) {
            if (!res.success) {
                alert('Errore Dump Database: ' + res.data.message);
                return;
            }

            if (res.data.completed) {
                setStep('step-db', 'completed');
                setStep('step-scan', 'active');
                updateProgress(35, res.data.message);
                runFileScan();
            } else {
                var pct = 15 + Math.round((res.data.progress_pct / 100) * 20);
                updateProgress(pct, res.data.message);
                runDbDumpChunk();
            }
        });
    }

    // Step 3: Scan Files
    function runFileScan() {
        $.post(SiteMoverVars.ajax_url, {
            action: 'sitemover_scan_files',
            nonce: SiteMoverVars.nonce,
            package_id: packageId
        }, function(res) {
            if (!res.success) {
                alert('Errore Scansione File: ' + res.data.message);
                return;
            }

            setStep('step-scan', 'completed');
            setStep('step-zip', 'active');
            updateProgress(40, res.data.message);

            runArchiveChunk();
        });
    }

    // Step 4 Loop: Build ZIP Archive
    function runArchiveChunk() {
        $.post(SiteMoverVars.ajax_url, {
            action: 'sitemover_build_archive_chunk',
            nonce: SiteMoverVars.nonce,
            package_id: packageId
        }, function(res) {
            if (!res.success) {
                alert('Errore Archiviazione ZIP: ' + res.data.message);
                return;
            }

            if (res.data.completed) {
                setStep('step-zip', 'completed');
                setStep('step-finalize', 'active');
                updateProgress(90, res.data.message);
                runFinalizePackage();
            } else {
                var pct = 40 + Math.round((res.data.progress_pct / 100) * 50);
                updateProgress(pct, res.data.message);
                runArchiveChunk();
            }
        });
    }

    // Step 5: Finalize & Generate Installer
    function runFinalizePackage() {
        $.post(SiteMoverVars.ajax_url, {
            action: 'sitemover_finalize_package',
            nonce: SiteMoverVars.nonce,
            package_id: packageId
        }, function(res) {
            if (!res.success) {
                alert('Errore Finalizzazione: ' + res.data.message);
                return;
            }

            setStep('step-finalize', 'completed');
            updateProgress(100, res.data.message);

            setTimeout(function() {
                window.location.reload();
            }, 2000);
        });
    }

    // Handle Delete Package
    $('.sitemover-delete-btn').on('click', function(e) {
        e.preventDefault();
        var id = $(this).data('id');

        if (!confirm('Sei sicuro di voler eliminare il pacchetto ' + id + '?')) {
            return;
        }

        $.post(SiteMoverVars.ajax_url, {
            action: 'sitemover_delete_package',
            nonce: SiteMoverVars.nonce,
            package_id: id
        }, function(res) {
            if (res.success) {
                window.location.reload();
            } else {
                alert(res.data.message);
            }
        });
    });
});
