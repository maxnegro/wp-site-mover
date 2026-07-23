# SiteMover - WordPress Backup, Migration & Cloning Plugin

**SiteMover** è un plugin professionale per WordPress ideato per il backup completo, la migrazione a zero downtime e il clonaggio/ripristino di siti WordPress di qualsiasi dimensione (compresi siti complessi con file pesanti >1GB e dimensioni complessive >20GB).

---

## 1. Analisi Tecnica e Architettura del Sistema

### 1.1 Il Problema dei Backup e Migrazioni su Hosting Condivisi
La maggior parte dei plugin di migrazione fallisce su siti di grandi dimensioni a causa di:
1. **PHP Execution Timeout (`max_execution_time`)**: I processi lunghi vengono interrotti dal server HTTP o dal processore FastCGI.
2. **PHP Memory Limit (`memory_limit`)**: L'allocazione dell'intero database o dell'elenco dei file in memoria genera un crash `Fatal Error: Allowed memory size exhausted`.
3. **Corruzione dei Dati Serializzati**: La semplice sostituzione stringa di URL nel database corrompe i dati serializzati in WordPress (`s:length:"string"`), rendendo il sito inaccessibile (es. widget persi, impostazioni temi corrotte).
4. **Dipendenza da WordPress sul Target**: Molti plugin richiedono che WordPress sia già installato ed operativo sul server di destinazione.

### 1.2 Soluzione Architetturale di SiteMover

SiteMover risolve questi limiti attraverso tre pilastri architetturali:

```
+-------------------------------------------------------------------+
|                        SITE MOVER ENGINE                          |
+---------------------------------+---------------------------------+
|          Plugin Engine          |       Standalone Installer      |
|    (WordPress Admin Context)    |   (No WP Required on Target)    |
+---------------------------------+---------------------------------+
| - Batch DB Exporter (Stream)    | - Standalone PHP Wizard App     |
| - Chunked Archive Builder       | - Step-by-Step Ajax Extractor   |
| - State Machine AJAX Progress   | - Deep Serialized Search/Replace|
| - Manifest Generator + Token    | - Auto wp-config.php Rewriter   |
+---------------------------------+---------------------------------+
```

---

## 2. Componenti del Sistema

### 2.1 Plugin WordPress (`site-mover.php` e moduli `includes/`)
- **`SiteMover_Admin`**: Interfaccia di amministrazione moderna ed intuitiva per configurare, avviare e gestire i pacchetti.
- **`SiteMover_DB_Exporter`**: Esportatore MySQL ottimizzato che legge le tabelle a blocchi (batching) e scrive direttamente in streaming nel file `.sql` senza saturare la RAM.
- **`SiteMover_Archive_Builder`**: Generatore di archivi ZIP basato su iteratori a bassa memoria (`DirectoryIterator` + `ZipArchive`) con supporto al frazionamento/chunking e all'esclusione di directory di cache/log/temp.
- **`SiteMover_Package_Manager`**: Gestisce lo storage dei pacchetti (`wp-content/uploads/site-mover-packages/`), la sicurezza con file `.htaccess`/`index.php` e la generazione del `manifest.json`.

### 2.2 Installer Indipendente (`installer.php`)
Lo script `installer.php` è un'applicazione PHP standalone (autonoma) con interfaccia web AJAX a 5 fasi:
1. **Requisiti di Sistema**: Verifica versione PHP (7.4+ / 8.x), estensioni necessarie (`mysqli`, `zip`, `zlib`, `json`), permessi di scrittura e spazio disco.
2. **Database Config**: Inserimento credenziali MySQL del nuovo hosting, verifica connessione e creazione database/tabelle.
3. **Estrazione Pacchetto**: Decompressione incrementale a blocchi (batch unzipping) dell'archivio `.zip` direttamente nella root del server.
4. **Sostituzione URL e Percorsi (Search & Replace)**:
   - Sostituzione profonda di Old URL -> New URL e Old Path -> New Path.
   - Algoritmo di ricorsione sui dati serializzati (PHP `unserialize` / regex fix / `serialize` ricorsivo) per garantire la validità delle lunghezze delle stringhe `s:N:"..."`.
   - Aggiornamento / rigenerazione di `wp-config.php` (credenziali DB, prefisso tabelle, chiavi di protezione salate).
5. **Completamento & Pulizia**: Rimozione automatica o manuale di `installer.php`, `archive.zip` e file temporanei di installazione per prevenire falle di sicurezza.

---

## 3. Gestione di Siti di Grandi Dimensioni (1GB+ File, 20GB+ Totali)

Per garantire stabilità su qualsiasi server senza crash per timeout o memory limit:

| Sfida Tecnica | Soluzione Adottata in SiteMover |
| :--- | :--- |
| **Timeout durante il Dump DB** | Lettura paginata delle tabelle (`SELECT * FROM table LIMIT step OFFSET offset`) e scrittura immediata con buffer flush. |
| **Timeout durante la Zippatura** | Archiviazione a micro-batch guidata da AJAX (`step_archive` con file index offset). |
| **Limiti di memoria PHP** | Utilizzo di generatori/iteratori PHP in lettura streaming ed eliminazione delle strutture dati monolitiche in RAM. |
| **Estrazione di file > 1GB** | Estrazione guidata da blocchi di N file per richiesta AJAX nell'installer. |

---

## 4. Algoritmo di Search & Replace per Dati Serializzati

Nel database di WordPress molte impostazioni (in `wp_options`, `wp_postmeta`, `wp_usermeta`) sono memorizzate in formato PHP serializzato:
`a:2:{s:4:"link";s:22:"http://vecchiosito.com";s:4:"text";s:4:"Home";}`

Se il nuovo dominio ha una lunghezza diversa (es. `https://nuovosito.it` -> 20 caratteri vs 22), una normale query SQL `REPLACE()` romperebbe la serializzazione rendendo il valore illeggibile per PHP.

SiteMover implementa un engine di **Recursive Serialized Engine**:
1. Analizza ogni record campo per campo.
2. Se il dato è una stringa serializzata, esegue il de-serializzatore sicuro.
3. Effettua il rimpiazzo ricorsivo su array, oggetti e proprietà.
4. Riserializza il dato ricalcolando accuratamente le lunghezze dei byte delle stringhe UTF-8.
5. In caso di stringhe malformate, applica il fix tramite espressioni regolari sulla lunghezza `s:\d+:"..."`.

---

## 5. Sicurezza e Protezione

- **Token di Autenticazione Pacchetto**: Ogni archivio e il rispettivo installer contengono un hash crittografico unico (`archive_key`). L'installer richiede la chiave prima di eseguire la sovrascrittura o l'importazione.
- **Protezione Directory dei Backup**: I pacchetti salvati sul server sorgente sono protetti tramite `.htaccess` (`Deny from all`) e file `index.php` vuoti per prevenire il download non autorizzato via URL diretto.
- **WP Nonce & Capability Checks**: Tutti gli endpoint AJAX del plugin verificano le autorizzazioni dell'utente (`manage_options`) e i token anti-CSRF (`check_ajax_referral`).
