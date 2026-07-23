document.addEventListener('DOMContentLoaded', function() {
    
    function switchPanel(fromStep, toStep) {
        document.getElementById('panel-step-' + fromStep).classList.remove('active');
        document.getElementById('wstep-' + fromStep).classList.remove('active');
        document.getElementById('wstep-' + fromStep).classList.add('completed');

        document.getElementById('panel-step-' + toStep).classList.add('active');
        document.getElementById('wstep-' + toStep).classList.add('active');
    }

    // Step 1: Run System Requirements Check automatically
    fetch('installer.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=check_requirements'
    })
    .then(response => response.json())
    .then(data => {
        const reqList = document.getElementById('requirements-list');
        reqList.innerHTML = '';

        let allPassed = data.success;

        for (const [key, item] of Object.entries(data.checks)) {
            const div = document.createElement('div');
            div.className = 'req-item';
            div.innerHTML = `
                <div class="req-label">
                    ${item.label}<br>
                    <small style="color:#64748b">${item.details}</small>
                </div>
                <div class="${item.pass ? 'badge-pass' : 'badge-fail'}">
                    ${item.pass ? 'PASS' : 'FAIL'}
                </div>
            `;
            reqList.appendChild(div);
        }

        if (allPassed) {
            document.getElementById('btn-to-step-2').disabled = false;
        }
    })
    .catch(err => {
        document.getElementById('requirements-list').innerHTML = '<div class="msg-box error">Errore durante la verifica dei requisiti.</div>';
    });

    // Step 1 -> Step 2
    document.getElementById('btn-to-step-2').addEventListener('click', function() {
        switchPanel(1, 2);
    });

    // Step 2: Test Database Connection
    document.getElementById('btn-test-db').addEventListener('click', function() {
        const msgBox = document.getElementById('db-test-msg');
        msgBox.className = 'msg-box';
        msgBox.innerText = 'Verifica connessione al database in corso...';

        const body = new URLSearchParams({
            action: 'test_db',
            db_host: document.getElementById('db_host').value,
            db_name: document.getElementById('db_name').value,
            db_user: document.getElementById('db_user').value,
            db_pass: document.getElementById('db_pass').value
        });

        fetch('installer.php', {
            method: 'POST',
            body: body
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                msgBox.className = 'msg-box success';
                msgBox.innerText = data.message;
                document.getElementById('btn-to-step-3').disabled = false;
            } else {
                msgBox.className = 'msg-box error';
                msgBox.innerText = data.message;
            }
        })
        .catch(err => {
            msgBox.className = 'msg-box error';
            msgBox.innerText = 'Errore di connessione.';
        });
    });

    // Step 2 -> Step 3
    document.getElementById('btn-to-step-3').addEventListener('click', function() {
        switchPanel(2, 3);
    });

    // Step 3: Unzip Archive
    document.getElementById('btn-start-unzip').addEventListener('click', function() {
        const btn = this;
        btn.disabled = true;
        
        const statusMsg = document.getElementById('unzip-status-msg');
        const progressBar = document.getElementById('unzip-progress');

        statusMsg.className = 'msg-box';
        statusMsg.innerText = 'Estrazione file in corso... Attendere...';
        progressBar.style.width = '50%';

        fetch('installer.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=unzip_archive'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                progressBar.style.width = '100%';
                statusMsg.className = 'msg-box success';
                statusMsg.innerText = data.message;

                setTimeout(function() {
                    switchPanel(3, 4);
                }, 1500);
            } else {
                statusMsg.className = 'msg-box error';
                statusMsg.innerText = data.message;
                btn.disabled = false;
            }
        })
        .catch(err => {
            statusMsg.className = 'msg-box error';
            statusMsg.innerText = 'Errore durante l\'estrazione dell\'archivio.';
            btn.disabled = false;
        });
    });

    // Step 4: Run DB Import & Search Replace
    document.getElementById('btn-run-import').addEventListener('click', function() {
        const btn = this;
        btn.disabled = true;

        const statusMsg = document.getElementById('import-status-msg');
        statusMsg.className = 'msg-box';
        statusMsg.innerText = 'Importazione database e sostituzione URL in corso...';

        const body = new URLSearchParams({
            action: 'import_and_replace',
            db_host: document.getElementById('db_host').value,
            db_name: document.getElementById('db_name').value,
            db_user: document.getElementById('db_user').value,
            db_pass: document.getElementById('db_pass').value,
            new_url: document.getElementById('new_site_url').value
        });

        fetch('installer.php', {
            method: 'POST',
            body: body
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                statusMsg.className = 'msg-box success';
                statusMsg.innerText = data.message;

                setTimeout(function() {
                    switchPanel(4, 5);
                }, 1500);
            } else {
                statusMsg.className = 'msg-box error';
                statusMsg.innerText = data.message;
                btn.disabled = false;
            }
        })
        .catch(err => {
            statusMsg.className = 'msg-box error';
            statusMsg.innerText = 'Errore durante il ripristino del database.';
            btn.disabled = false;
        });
    });

    // Step 5: Cleanup & Redirect
    document.getElementById('btn-cleanup-site').addEventListener('click', function() {
        const btn = this;
        btn.disabled = true;

        fetch('installer.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=cleanup'
        })
        .then(response => response.json())
        .then(data => {
            alert(data.message);
            const newUrl = document.getElementById('new_site_url').value;
            window.location.href = newUrl;
        })
        .catch(err => {
            window.location.href = '/';
        });
    });
});
