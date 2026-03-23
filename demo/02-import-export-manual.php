<?php
    /**
     * Project Name:    Wingman Explorer - Import / Export Manual Selection Demo
     * Created by:      Angel Politis
     * Creation Date:   Mar 22 2026
     * Last Modified:   Mar 22 2026
     *
     * Copyright (c) 2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Import the following classes to the current scope.
    use Wingman\Explorer\Exceptions\ImportException;
    use Wingman\Explorer\Facades\Exporter;
    use Wingman\Explorer\Facades\Importer;
    use Wingman\Explorer\IO\Exporters\GZipExporter;
    use Wingman\Explorer\IO\Exporters\JsonExporter;
    use Wingman\Explorer\IO\Exporters\PipelineExporter;
    use Wingman\Explorer\IO\Exporters\TextExporter;
    use Wingman\Explorer\IO\Importers\CsvImporter;
    use Wingman\Explorer\IO\Importers\JsonImporter;
    use Wingman\Explorer\IO\Importers\TextImporter;
    use Wingman\Explorer\IO\IOManager;

    require_once __DIR__ . "/../autoload.php";

    # --------------------------------------------------------------------------------------
    # MANUAL SELECTION OVERVIEW
    #
    # When you know the exact format of a file you can bypass negotiation entirely:
    #
    #   Importer::forType('json')                — select importer by file extension
    #   Importer::forMime('application/json')    — select importer by MIME type
    #   Exporter::forType('csv')                 — select exporter by file extension
    #   Exporter::forMime('text/csv')            — select exporter by MIME type
    #
    # Both return a PreselectedImporter / PreselectedExporter facade that exposes
    # import() and export() / prepare() without any scoring overhead.
    # --------------------------------------------------------------------------------------

    $tmpDir = sys_get_temp_dir() . "/wm_explorer_manual_demo_" . uniqid();
    mkdir($tmpDir, 0775, true);

    $data = [
        ["country" => "Greece",      "capital" => "Athens",     "population" => 10_000_000],
        ["country" => "Spain",       "capital" => "Madrid",     "population" => 47_000_000],
        ["country" => "Netherlands", "capital" => "Amsterdam",  "population" => 17_000_000],
    ];

    echo "=== MANUAL EXPORT BY TYPE ===\n\n";

    # --------------------------------------------------------------------------
    # forType() — explicitly select by extension string.
    # The method throws UnsupportedExportTypeException when no driver matches.
    # --------------------------------------------------------------------------
    $csvPath  = "$tmpDir/countries.csv";
    $jsonPath = "$tmpDir/countries.json";

    Exporter::forType("csv")->export($data, $csvPath);
    echo "countries.csv:\n" . file_get_contents($csvPath) . "\n";

    Exporter::forType("json")->export($data, $jsonPath);
    echo "countries.json:\n" . file_get_contents($jsonPath) . "\n";

    echo "=== MANUAL IMPORT BY TYPE ===\n\n";

    # --------------------------------------------------------------------------
    # Importing the same files with explicit drivers — no auto-detection at all.
    # --------------------------------------------------------------------------
    $fromCsv  = Importer::forType("csv")->import($csvPath);
    $fromJson = Importer::forType("json")->import($jsonPath);

    echo "Imported from CSV:\n";
    print_r($fromCsv);

    echo "\nImported from JSON:\n";
    print_r($fromJson);

    echo "\n=== MANUAL SELECT BY MIME TYPE ===\n\n";

    # --------------------------------------------------------------------------
    # forMime() — useful when you receive a Content-Type header from a remote
    # source and want to route directly to the right driver.
    # --------------------------------------------------------------------------
    $jsonFromMime = Importer::forMime("application/json")->import($jsonPath);
    echo "Imported via MIME 'application/json' (first record):\n";
    print_r($jsonFromMime[0]);

    $csvFromMime = Importer::forMime("text/csv")->import($csvPath);
    echo "\nImported via MIME 'text/csv' (first record):\n";
    print_r($csvFromMime[0]);

    echo "\n=== PREPARE WITHOUT WRITING ===\n\n";

    # --------------------------------------------------------------------------
    # prepare() serialises data to a string without touching the filesystem —
    # handy for HTTP responses, caching, or piping into another system.
    # --------------------------------------------------------------------------
    $jsonString = Exporter::forType("json")->prepare($data);
    echo "Prepared JSON string (not written to disk):\n$jsonString\n\n";

    echo "=== CSV IMPORT OPTIONS ===\n\n";

    # --------------------------------------------------------------------------
    # CsvImporter accepts options:
    #   header    (bool, default true)  — treat first row as column names
    #   separator (string, default ",") — field delimiter
    #   enclosure (string, default '"') — quoting character
    # --------------------------------------------------------------------------
    $rawCsvPath = "$tmpDir/raw.csv";
    file_put_contents($rawCsvPath, "Alice;30;admin\nBob;25;editor\n");

    $rows = Importer::forType("csv")->import($rawCsvPath, [
        "header"    => false,
        "separator" => ";",
    ]);

    echo "CSV imported without header (semicolon delimiter):\n";
    print_r($rows);

    echo "\n=== PIPELINE EXPORTER ===\n\n";

    # --------------------------------------------------------------------------
    # PipelineExporter chains multiple exporters in sequence.  Each step's
    # prepare() output is fed as the input to the next step, and the final
    # result is written to disk.
    #
    # Here we serialise data to JSON first, then compress it with GZip.
    # --------------------------------------------------------------------------
    $gzPath = "$tmpDir/data.json.gz";

    $pipeline = new PipelineExporter([
        new JsonExporter(),
        new GZipExporter(),
    ]);

    $pipeline->export($data, $gzPath);
    echo "Pipeline (JSON → GZip) written: $gzPath (" . filesize($gzPath) . " bytes)\n";

    $decompressed = gzdecode(file_get_contents($gzPath));
    echo "Decompressed content:\n$decompressed\n\n";

    echo "=== CUSTOM IMPORTER REGISTRATION ===\n\n";

    # --------------------------------------------------------------------------
    # You can register any class that implements ImporterInterface.  Here we
    # create a simple anonymous importer for ".log" files that returns lines as
    # an array, then register it so that auto-negotiation can find it.
    #
    # register() / unregister() accept an instance or a class name string.
    # --------------------------------------------------------------------------
    $logImporter = new class implements \Wingman\Explorer\Interfaces\IO\ImporterInterface {
        use \Wingman\Explorer\Traits\CanProcess;

        public function import (string $path, array $options = []) : mixed {
            return array_values(array_filter(
                file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []
            ));
        }

        public function getConfidence (string $path, ?string $extension, ?string $mime, string $sample) : float {
            return $extension === "log" ? 0.9 : 0.0;
        }

        public function supports (string $path, ?string $extension = null, ?string $mime = null) : bool {
            return $extension === "log";
        }

        public function supportsExtension (string $extension) : bool {
            return $extension === "log";
        }

        public function supportsMime (string $mime) : bool {
            return false;
        }
    };

    $logPath = "$tmpDir/app.log";
    file_put_contents($logPath, "[INFO]  Server started\n[WARN]  Disk space low\n[ERROR] Connection reset\n");

    Importer::register($logImporter);

    $logLines = Importer::import($logPath);
    echo "Custom .log importer result:\n";
    print_r($logLines);

    Importer::unregister(get_class($logImporter));

    echo "\n=== IOManager TWIN PAIRS ===\n\n";

    # --------------------------------------------------------------------------
    # IOManager::bind() creates a twin pair so that any importer or exporter can
    # resolve its counterpart via the ReversibleIOInterface.
    #
    # The built-in drivers (JsonImporter ↔ JsonExporter, etc.) are pre-bound.
    # bind() / unbind() let you map your own custom drivers.
    # --------------------------------------------------------------------------
    $twinExporter = IOManager::getTwinExporter(JsonImporter::class);
    $twinImporter = IOManager::getTwinImporter(JsonExporter::class);

    echo "Twin exporter for JsonImporter: " . get_class($twinExporter) . "\n";
    echo "Twin importer for JsonExporter: " . get_class($twinImporter) . "\n";

    # --------------------------------------------------------------------------
    # Bind a custom pair.
    # --------------------------------------------------------------------------
    IOManager::bind(TextImporter::class, TextExporter::class);

    $textTwin = IOManager::getTwinExporter(TextImporter::class);
    echo "Twin exporter for TextImporter (after bind): " . get_class($textTwin) . "\n";

    IOManager::unbind(TextImporter::class, TextExporter::class);

    echo "\n=== CUSTOM NEGOTIATION STRATEGY ===\n\n";

    # --------------------------------------------------------------------------
    # You can replace the default scoring algorithm entirely with a custom
    # callable.  The callable receives ($data, $extension, $mime) and must
    # return one of the registered ExporterInterface instances or null.
    # --------------------------------------------------------------------------
    $exportManager = IOManager::getExportManager();

    $exportManager->setNegotiationStrategy(function (mixed $data, ?string $extension, ?string $mime) use ($exportManager) {
        # Custom rule: always prefer JsonExporter.
        return $exportManager->get(JsonExporter::class);
    });

    $overridePath = "$tmpDir/forced.csv";
    Exporter::export($data, $overridePath);
    echo "Forced export to .csv via custom strategy (driver used: JsonExporter):\n";
    echo file_get_contents($overridePath) . "\n";

    # --------------------------------------------------------------------------
    # Cleanup
    # --------------------------------------------------------------------------
    array_map("unlink", glob("$tmpDir/*") ?: []);
    rmdir($tmpDir);
?>