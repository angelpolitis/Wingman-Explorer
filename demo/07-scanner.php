<?php
    /**
     * Project Name:    Wingman Explorer - Scanner Demo
     * Created by:      Angel Politis
     * Creation Date:   Mar 22 2026
     * Last Modified:   Mar 22 2026
     *
     * Copyright (c) 2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Import the following classes to the current scope.
    use Wingman\Explorer\Adapters\LocalAdapter;
    use Wingman\Explorer\Enums\ScanDepth;
    use Wingman\Explorer\Enums\ScanEvent;
    use Wingman\Explorer\Enums\ScanFilterType;
    use Wingman\Explorer\Enums\ScanOption;
    use Wingman\Explorer\Enums\ScanOrder;
    use Wingman\Explorer\Enums\ScanSortOption;
    use Wingman\Explorer\Enums\ScanTarget;
    use Wingman\Explorer\Scanner;

    require_once __DIR__ . "/../autoload.php";

    # --------------------------------------------------------------------------------------
    # SCANNER OVERVIEW
    #
    # Scanner provides a fluent API for walking a directory tree.  It is backed by any
    # DirectoryFilesystemAdapterInterface, allowing the same scanning logic to work over
    # local disk, S3, SFTP, etc.
    #
    # Key builder methods:
    #   ::withAdapter($adapter)              — static factory
    #   ->setDepth(ScanDepth $depth)         — SHALLOW (1 level) | DEFAULT (configurable) | DEEP (unlimited)
    #   ->setTarget(ScanTarget $target)      — FILE | DIR | ANY | HIDDEN | HIDDEN_FILE | HIDDEN_DIR
    #   ->filterBy(ScanFilterType $type, $v) — apply a filter condition (chainable)
    #   ->sortBy(ScanSortOption $opt, $order)— sort results
    #   ->addOption(ScanOption $option)      — PATHS_ONLY | SKIP_ERRORS | COLLAPSE_DIRS
    #   ->setEvent(ScanEvent $event, $cb)    — register event listener
    #   ->scan(string $rootPath)             — execute and return results
    # --------------------------------------------------------------------------------------

    $tmpDir = sys_get_temp_dir() . "/wm_explorer_scanner_demo_" . uniqid();

    # Build a realistic directory fixture.
    $dirs = [
        "$tmpDir/app",
        "$tmpDir/app/Controllers",
        "$tmpDir/app/Models",
        "$tmpDir/app/Services",
        "$tmpDir/config",
        "$tmpDir/public",
        "$tmpDir/tests",
        "$tmpDir/vendor",
    ];

    foreach ($dirs as $dir) mkdir($dir, 0775, true);

    $files = [
        "$tmpDir/app/Controllers/UserController.php"    => "<?php\nclass UserController {}",
        "$tmpDir/app/Controllers/OrderController.php"   => "<?php\nclass OrderController {}",
        "$tmpDir/app/Models/User.php"                   => "<?php\nclass User {}",
        "$tmpDir/app/Models/Order.php"                  => "<?php\nclass Order {}",
        "$tmpDir/app/Services/AuthService.php"          => "<?php\nclass AuthService {}",
        "$tmpDir/config/app.json"                       => '{"env":"production","debug":false}',
        "$tmpDir/config/database.ini"                   => "[db]\nhost=localhost\nport=5432",
        "$tmpDir/public/index.php"                      => "<?php\nrequire '../vendor/autoload.php';",
        "$tmpDir/public/style.css"                      => "body { margin: 0; }",
        "$tmpDir/public/app.js"                         => "console.log('Hello');",
        "$tmpDir/tests/UserTest.php"                    => "<?php\nclass UserTest {}",
        "$tmpDir/tests/scaffold.json"                   => '{"bootstrap":"tests/bootstrap.php"}',
        "$tmpDir/.env"                                  => "APP_ENV=production",
        "$tmpDir/composer.json"                         => '{"name":"demo/app"}',
        "$tmpDir/README.md"                             => "# Demo App",
    ];

    # Write files of varying sizes so filters produce visible differences.
    foreach ($files as $path => $content) {
        file_put_contents($path, str_repeat($content, random_int(1, 5)));
    }

    $adapter = new LocalAdapter();

    echo "=== SHALLOW SCAN (1 LEVEL, ALL TYPES) ===\n\n";

    # --------------------------------------------------------------------------
    # ScanDepth::SHALLOW only descends one directory level from the root.
    # ScanTarget::ANY includes both files and directories.
    # --------------------------------------------------------------------------
    $shallowResults = Scanner::withAdapter($adapter)
        ->setDepth(ScanDepth::SHALLOW)
        ->setTarget(ScanTarget::ANY)
        ->scan($tmpDir);

    echo "Found " . count($shallowResults) . " entries at root level:\n";
    foreach ($shallowResults as $entry) {
        $type = is_dir($entry["path"]) ? "dir " : "file";
        echo "  [$type] " . basename($entry["path"]) . "\n";
    }

    echo "\n=== DEEP SCAN — PHP FILES ONLY ===\n\n";

    # --------------------------------------------------------------------------
    # ScanDepth::DEEP traverses the entire tree.  ScanTarget::FILE restricts
    # results to files.  filterBy(EXTENSION, …) matches by extension string.
    # --------------------------------------------------------------------------
    $phpFiles = Scanner::withAdapter($adapter)
        ->setDepth(ScanDepth::DEEP)
        ->setTarget(ScanTarget::FILE)
        ->filterBy(ScanFilterType::EXTENSION, "php")
        ->sortBy(ScanSortOption::NAME, ScanOrder::ASCENDING)
        ->scan($tmpDir);

    echo "PHP files (sorted by name):\n";
    foreach ($phpFiles as $entry) {
        echo "  " . str_replace($tmpDir . "/", "", $entry["path"]) . "\n";
    }

    echo "\n=== FILTER BY MULTIPLE EXTENSIONS ===\n\n";

    # --------------------------------------------------------------------------
    # Pass an array to filterBy(EXTENSION, …) to match multiple extensions.
    # --------------------------------------------------------------------------
    $configFiles = Scanner::withAdapter($adapter)
        ->setDepth(ScanDepth::DEEP)
        ->setTarget(ScanTarget::FILE)
        ->filterBy(ScanFilterType::EXTENSION, ["json", "ini"])
        ->scan($tmpDir);

    echo "Config files (json + ini):\n";
    foreach ($configFiles as $entry) {
        echo "  " . str_replace($tmpDir . "/", "", $entry["path"]) . "\n";
    }

    echo "\n=== FILTER BY REGEX PATTERN ===\n\n";

    # --------------------------------------------------------------------------
    # ScanFilterType::REGEX matches the full path against a regular expression.
    # Here we find all *Controller.php and *Test.php files.
    # --------------------------------------------------------------------------
    $patterns = Scanner::withAdapter($adapter)
        ->setDepth(ScanDepth::DEEP)
        ->setTarget(ScanTarget::FILE)
        ->filterBy(ScanFilterType::REGEX, "/(Controller|Test)\.php$/")
        ->scan($tmpDir);

    echo "Files matching /(Controller|Test)\\.php$/:\n";
    foreach ($patterns as $entry) {
        echo "  " . str_replace($tmpDir . "/", "", $entry["path"]) . "\n";
    }

    echo "\n=== FILTER BY SIZE ===\n\n";

    # --------------------------------------------------------------------------
    # SIZE_GREATER / SIZE_LESS filter by byte count.  Combine them to express
    # a range.  Threshold values are in bytes.
    # --------------------------------------------------------------------------
    $smallFiles = Scanner::withAdapter($adapter)
        ->setDepth(ScanDepth::DEEP)
        ->setTarget(ScanTarget::FILE)
        ->filterBy(ScanFilterType::SIZE_LESS, 100)
        ->sortBy(ScanSortOption::SIZE, ScanOrder::ASCENDING)
        ->scan($tmpDir);

    echo "Files smaller than 100 bytes:\n";
    foreach ($smallFiles as $entry) {
        echo "  " . str_replace($tmpDir . "/", "", $entry["path"]) . "  (" . $entry["size"] . " B)\n";
    }

    echo "\n=== PATHS-ONLY OPTION ===\n\n";

    # --------------------------------------------------------------------------
    # ScanOption::PATHS_ONLY makes scan() return plain path strings instead of
    # info arrays — useful when you only need the file paths.
    # --------------------------------------------------------------------------
    $paths = Scanner::withAdapter($adapter)
        ->setDepth(ScanDepth::DEEP)
        ->setTarget(ScanTarget::FILE)
        ->filterBy(ScanFilterType::EXTENSION, "php")
        ->addOption(ScanOption::PATHS_ONLY)
        ->scan($tmpDir);

    echo "Paths-only result (first 3):\n";
    foreach (array_slice($paths, 0, 3) as $path) {
        echo "  $path\n";
    }

    echo "\n=== HIDDEN FILE SCAN ===\n\n";

    # --------------------------------------------------------------------------
    # ScanTarget::HIDDEN_FILE surfaces dot-prefixed files that are skipped by
    # the other targets.
    # --------------------------------------------------------------------------
    $hidden = Scanner::withAdapter($adapter)
        ->setDepth(ScanDepth::SHALLOW)
        ->setTarget(ScanTarget::HIDDEN_FILE)
        ->scan($tmpDir);

    echo "Hidden files at root:\n";
    foreach ($hidden as $entry) {
        echo "  " . basename($entry["path"]) . "\n";
    }

    echo "\n=== DIRECTORIES ONLY ===\n\n";

    $dirResults = Scanner::withAdapter($adapter)
        ->setDepth(ScanDepth::DEEP)
        ->setTarget(ScanTarget::DIR)
        ->sortBy(ScanSortOption::NAME, ScanOrder::ASCENDING)
        ->scan($tmpDir);

    echo "All subdirectories (sorted):\n";
    foreach ($dirResults as $entry) {
        echo "  " . str_replace($tmpDir . "/", "", $entry["path"]) . "/\n";
    }

    echo "\n=== EVENT LISTENERS ===\n\n";

    # --------------------------------------------------------------------------
    # setEvent() registers a callback for a specific scan lifecycle event.
    # Useful for logging, progress reporting, or early-exit logic.
    #
    # Available events: SCAN_STARTED, FILE_FOUND, DIRECTORY_FOUND, FOUND (both),
    #                   SKIPPED, FILE_SKIPPED, DIRECTORY_SKIPPED, SCAN_COMPLETED,
    #                   SCAN_ERROR
    # --------------------------------------------------------------------------
    $foundCount = 0;

    $scanner = Scanner::withAdapter($adapter)
        ->setDepth(ScanDepth::DEEP)
        ->setTarget(ScanTarget::FILE)
        ->filterBy(ScanFilterType::EXTENSION, "php")
        ->setEvent(ScanEvent::SCAN_STARTED, function (string $root) {
            echo "Scan started at: $root\n";
        })
        ->setEvent(ScanEvent::FILE_FOUND, function (array $info) use (&$foundCount) {
            $foundCount++;
            echo "  Found: " . basename($info["path"]) . "\n";
        })
        ->setEvent(ScanEvent::SCAN_COMPLETED, function (int $total) {
            echo "Scan complete — total items: $total\n";
        });

    $scanner->scan($tmpDir);
    echo "Event-listener found count: $foundCount\n";

    # --------------------------------------------------------------------------
    # Cleanup
    # --------------------------------------------------------------------------
    $cleanupWalk = function (string $dir) use (&$cleanupWalk) : void {
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === "..") continue;
            $path = "$dir/$entry";
            is_dir($path) ? ($cleanupWalk($path) ?: rmdir($path)) : unlink($path);
        }
        rmdir($dir);
    };

    $cleanupWalk($tmpDir);
?>