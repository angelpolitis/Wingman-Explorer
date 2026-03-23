<?php
    /**
     * Project Name:    Wingman Explorer - Import / Export Negotiation Demo
     * Created by:      Angel Politis
     * Creation Date:   Mar 22 2026
     * Last Modified:   Mar 22 2026
     *
     * Copyright (c) 2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Import the following classes to the current scope.
    use Wingman\Explorer\Facades\Exporter;
    use Wingman\Explorer\Facades\Importer;
    use Wingman\Explorer\IO\IOManager;

    require_once __DIR__ . "/../autoload.php";

    # --------------------------------------------------------------------------------------
    # NEGOTIATION OVERVIEW
    #
    # When you call Exporter::export() or Importer::import() without picking a specific
    # driver, IOManager runs a confidence-scoring pass across all registered exporters /
    # importers.  Each driver returns a float (0.0–1.0) from getConfidence(); the highest
    # score wins.  Ties fall back to the registered fallback driver (TextExporter /
    # TextImporter by default).
    #
    # IOManager is lazy – it boots and registers defaults automatically on the first call,
    # so you never need to initialise it manually.
    #
    # Default import drivers:  JsonImporter, JsonLinesImporter, IniImporter, CsvImporter,
    #                          PhpImporter  (fallback: TextImporter)
    # Default export drivers:  JsonExporter, JsonLinesExporter, IniExporter, CsvExporter
    #                          (fallback: TextExporter)
    # --------------------------------------------------------------------------------------

    $tmpDir = sys_get_temp_dir() . "/wm_explorer_neg_demo_" . uniqid();
    mkdir($tmpDir, 0775, true);

    # File paths used throughout this demo.
    $jsonPath    = "$tmpDir/users.json";
    $jsonlPath   = "$tmpDir/events.jsonl";
    $csvPath     = "$tmpDir/report.csv";
    $iniPath     = "$tmpDir/config.ini";
    $unknownPath = "$tmpDir/mystery.bin";

    echo "=== EXPORT BY NEGOTIATION ===\n\n";

    # --------------------------------------------------------------------------
    # JSON  — extension ".json" gives JsonExporter a perfect confidence of 1.0.
    # --------------------------------------------------------------------------
    $users = [
        ["id" => 1, "name" => "Alice",   "role" => "admin"],
        ["id" => 2, "name" => "Bob",     "role" => "editor"],
        ["id" => 3, "name" => "Charlie", "role" => "viewer"],
    ];

    Exporter::export($users, $jsonPath);
    echo "users.json\n" . file_get_contents($jsonPath) . "\n";

    # --------------------------------------------------------------------------
    # JSON Lines  — ".jsonl" routes to JsonLinesExporter; one object per line.
    # --------------------------------------------------------------------------
    $events = [
        ["ts" => "2026-03-22T09:00:00Z", "event" => "login",  "user" => 1],
        ["ts" => "2026-03-22T09:05:00Z", "event" => "view",   "user" => 1],
        ["ts" => "2026-03-22T09:12:00Z", "event" => "logout", "user" => 1],
    ];

    Exporter::export($events, $jsonlPath);
    echo "events.jsonl\n" . file_get_contents($jsonlPath) . "\n";

    # --------------------------------------------------------------------------
    # CSV  — ".csv" routes to CsvExporter; associative rows produce a header row.
    # --------------------------------------------------------------------------
    Exporter::export($users, $csvPath);
    echo "report.csv\n" . file_get_contents($csvPath) . "\n";

    # --------------------------------------------------------------------------
    # INI  — ".ini" routes to IniExporter; expects a flat or sectioned array.
    # --------------------------------------------------------------------------
    $config = [
        "database" => ["host" => "localhost", "port" => "5432", "name" => "app_db"],
        "cache"    => ["driver" => "redis",   "ttl"  => "300"],
    ];

    Exporter::export($config, $iniPath);
    echo "config.ini\n" . file_get_contents($iniPath) . "\n";

    # --------------------------------------------------------------------------
    # Unknown extension  — no driver supports ".bin", so the TextExporter
    # fallback is used and a Signal::EXPORT_FALLBACK event is emitted.
    # --------------------------------------------------------------------------
    Exporter::export("Raw payload for demonstration.", $unknownPath);
    echo "mystery.bin (TextExporter fallback)\n" . file_get_contents($unknownPath) . "\n\n";

    echo "=== IMPORT BY NEGOTIATION ===\n\n";

    $importedUsers  = Importer::import($jsonPath);
    $importedEvents = Importer::import($jsonlPath);
    $importedRows   = Importer::import($csvPath);
    $importedConfig = Importer::import($iniPath);

    echo "Imported users.json:\n";
    print_r($importedUsers);

    echo "\nImported events.jsonl:\n";
    print_r($importedEvents);

    echo "\nImported report.csv (header row consumed as keys):\n";
    print_r($importedRows);

    echo "\nImported config.ini (sections as nested arrays):\n";
    print_r($importedConfig);

    echo "\n=== INSPECT WITHOUT RUNNING ===\n\n";

    # getBestMatch() lets you see which driver would be chosen without actually
    # performing the export or import — useful for debugging negotiation results.

    $bestExporter = IOManager::getExportManager()->getBestMatch($users, "json", null);
    $bestImporter = IOManager::getImportManager()->getBestMatch($jsonPath, "json", "application/json");

    echo "Best exporter for array + .json:             " . get_class($bestExporter) . "\n";
    echo "Best importer for .json + application/json: " . get_class($bestImporter) . "\n";

    # The fallback is activated when nothing else matches.
    $fallbackExporter = IOManager::getExportManager()->getFallback();
    $fallbackImporter = IOManager::getImportManager()->getFallback();

    echo "Export fallback driver: " . get_class($fallbackExporter) . "\n";
    echo "Import fallback driver: " . get_class($fallbackImporter) . "\n";

    # --------------------------------------------------------------------------
    # Cleanup
    # --------------------------------------------------------------------------
    array_map("unlink", glob("$tmpDir/*") ?: []);
    rmdir($tmpDir);
?>