=== WP SiteMover ===
Contributors: @maxnegro
Tags: backup, migration, clone, website, restore
Requires at least: 5.2
Tested up to: 7.2
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

High-performance WordPress backup, zero-downtime migration, and site cloning plugin. Supports large sites (20GB+) and large files (1GB+). Includes an independent standalone installer for clean hosting.

== Description ==

WP SiteMover is a professional WordPress plugin designed for complete backup, zero-downtime migration, and cloning/restoration of WordPress sites of any size (including complex sites with large files over 1GB and total sizes over 20GB).

= Why WP SiteMover? =

Most migration plugins fail on large sites due to:

* **PHP Execution Timeout (`max_execution_time`)**: Long-running processes are interrupted by the HTTP server or FastCGI processor.
* **PHP Memory Limit (`memory_limit`)**: Allocating the entire database or file list in memory causes a `Fatal Error: Allowed memory size exhausted` crash.
* **Serialized Data Corruption**: Simple string replacement of URLs in the database corrupts serialized WordPress data (`s:length:"string"`), making the site inaccessible (e.g., lost widgets, corrupted theme settings).
* **Target WordPress Dependency**: Many plugins require WordPress to already be installed and operating on the destination server.

= How It Works =

WP SiteMover uses a dual-engine architecture:

* **Plugin Engine (WordPress Admin Context)**: Batch DB exporter, chunked archive builder, state machine AJAX progress, manifest generator.
* **Standalone Installer (No WP Required on Target)**: 5-step wizard web app, AJAX extractor, deep serialized search/replace, auto wp-config.php rewriter.

= Key Features =

* **Chunked Database Export**: Reads database tables in batches (`LIMIT/OFFSET`) and streams directly to `.sql` without exhausting RAM.
* **Chunked ZIP Archive Builder**: Uses low-memory iterators (`DirectoryIterator` + `ZipArchive`) with micro-batch AJAX progress tracking.
* **Deep Serialized Search & Replace**: Recursively handles PHP serialized data (`unserialize`/`serialize`) and regex-based length fixes for safe URL/path replacement across all tables.
* **Standalone Installer (`site-installer.php`)**: A self-contained PHP wizard that runs on any hosting without WordPress. Extracts archives, imports databases, and rewrites `wp-config.php` automatically.
* **Large File Support**: Handles files over 1GB and total site sizes over 20GB through streaming and chunked operations.
* **Security Tokens**: Each package includes a unique cryptographic hash (`archive_key`). The installer requires this key before executing any overwrite or import.
* **Backup Directory Protection**: Packages are protected via `.htaccess` (`Deny from all`) and empty `index.php` files to prevent unauthorized direct URL downloads.
* **i18n Ready**: Full internationalization support with text domain `wp-site-mover`, POT template, Italian and English translations, and JS translation JSONs.

= System Requirements =

* PHP 7.4 or higher (8.x recommended)
* MySQL 5.6+ or MariaDB
* WordPress 5.2+
* PHP extensions: `mysqli`, `zip`, `zlib`, `json`

== Installation ==

1. Upload the `wp-site-mover` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to **SiteMover** in the admin menu to create your first backup package.

= How to Migrate to a New Server =

1. In the WP SiteMover admin page, click **Start Package Creation** and wait for the process to complete.
2. Download the two files: the ZIP archive (`archive_pkg_....zip`) and the `site-installer.php` file.
3. Upload both files to the root of your new server/hosting (works even on empty servers without WordPress).
4. Open your browser at `https://your-new-domain.com/site-installer.php` and follow the 5-step guided procedure!

== Frequently Asked Questions ==

= Does this work on shared hosting? =

Yes. WP SiteMover is designed to work within PHP execution time and memory limits by processing data in small batches via AJAX. However, very large sites may require adjusting `max_execution_time` and `memory_limit` in php.ini if your host allows it.

= Can I migrate to an empty server without WordPress installed? =

Yes. The generated `site-installer.php` is a standalone PHP application that creates the database, extracts files, and configures WordPress automatically.

= Are serialized data and settings preserved? =

Yes. WP SiteMover implements a recursive serialized data engine that safely replaces URLs and paths while maintaining PHP serialization string lengths.

= Is the installer file safe to keep after migration? =

For security, the installer and archive files can be automatically removed after successful completion. We recommend deleting them from the destination server once the migration is verified.

== Changelog ==

= 1.0.1 =

- docs(changelog): rimuovere intestazione Added superflua in release 1.0.0 (33ddfbc)
- Fix sync-readme-changelog: extract all versions from CHANGELOG.md (caf573a)
- Fix readme sync: avoid duplicating Upgrade Notice section (d134951)
- docs(readme): aggiornare contributor e versione WordPress testata (8669a01)
- fix(git-hooks): prevenire duplicazione nella sezione Upgrade Notice (4a826a8)
- Fix sync-readme-changelog: prevent duplication and escape issues (feee6bf)
- Add git-flow hooks and project files (302b05c)
- chore(i18n): aggiungere .gitignore ed eliminare file .mo di traduzione compilati (f7a5ae5)
- Rename plugin to WP SiteMover and add i18n support (dedd006)
- style(admin): sostituire 'Zippatura' con 'Compressione' nel messaggio di avanzamento (7e4cbfe)

= 1.0.0 =

- Initial release.
- Chunked database export and ZIP archive builder.
- Standalone `site-installer.php` with 5-step wizard.
- Deep serialized search and replace engine.
- Admin dashboard with AJAX progress tracking.
- Internationalization (i18n) support with Italian and English translations.

== Upgrade Notice ==

= 1.0.1 =
Some cleaning up

= 1.0.0 =
First stable release.

