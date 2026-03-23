<?php
    /**
     * Project Name:    Wingman Explorer - IO Manager
     * Created by:      Angel Politis
     * Creation Date:   Dec 19 2025
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2025-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer.IO namespace.
    namespace Wingman\Explorer\IO;

    # Import the following classes to the current scope.
    use Wingman\Explorer\Interfaces\IO\ImporterInterface;
    use Wingman\Explorer\Interfaces\IO\ExporterInterface;
    use Wingman\Explorer\IO\Exporters\CsvExporter;
    use Wingman\Explorer\IO\Exporters\IniExporter;
    use Wingman\Explorer\IO\Exporters\JsonExporter;
    use Wingman\Explorer\IO\Exporters\JsonLinesExporter;
    use Wingman\Explorer\IO\Exporters\TextExporter;
    use Wingman\Explorer\IO\Importers\CsvImporter;
    use Wingman\Explorer\IO\Importers\IniImporter;
    use Wingman\Explorer\IO\Importers\JsonImporter;
    use Wingman\Explorer\IO\Importers\JsonLinesImporter;
    use Wingman\Explorer\IO\Importers\PhpImporter;
    use Wingman\Explorer\IO\Importers\TextImporter;

    /**
     * A static facade that coordinates the import and export sub-systems.
     *
     * Lazily initialises an {@see ImportManager} and an {@see ExportManager} on
     * first use and registers a suite of built-in importers and exporters for
     * the most common file formats. Maintains a bidirectional twin-map so that
     * any importer or exporter can resolve its counterpart via
     * {@see getTwinExporter()} and {@see getTwinImporter()}.
     *
     * @package Wingman\Explorer\IO
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class IOManager {
        /**
         * The import manager.
         * @var ImportManager|null
         */
        protected static ?ImportManager $importManager = null;

        /**
         * The export manager.
         * @var ExportManager|null
         */
        protected static ?ExportManager $exportManager = null;

        /**
         * Twin maps: Importer class => Exporter class
         * @var array<string, string>
         */
        protected static array $importerToExporter = [];

        /**
         * Twin maps: Exporter class => Importer class
         * @var array<string, string>
         */
        protected static array $exporterToImporter = [];

        /**
         * Ensures that both managers are initialised, triggering a full default initialisation
         * on first access so that explicit calls to {@see init()} are never required.
         */
        private static function ensureInitialised () : void {
            if (self::$importManager === null || self::$exportManager === null) {
                self::init();
            }
        }

        /**
         * Binds an importer class to an exporter class.
         * @param class-string|ImporterInterface $importer An importer or importer class.
         * @param class-string|ExporterInterface $exporter An exporter or exporter class.
         */
        public static function bind (string|ImporterInterface $importer, string|ExporterInterface $exporter) : void {
            $importerClass = is_object($importer) ? get_class($importer) : $importer;
            $exporterClass = is_object($exporter) ? get_class($exporter) : $exporter;

            self::$importerToExporter[$importerClass] = $exporterClass;
            self::$exporterToImporter[$exporterClass] = $importerClass;
        }

        /**
         * Initialises managers if not already.
         * @param bool $registerDefaults Whether to register default importers/exporters.
         * @param array $importerConfig Configuration for the import manager.
         * @param array $exporterConfig Configuration for the export manager.
         */
        public static function init (bool $registerDefaults = true, array $importerConfig = [], array $exporterConfig = []) : void {
            if (self::$importManager === null) {
                self::$importManager = new ImportManager($importerConfig);
            }
            if (self::$exportManager === null) {
                self::$exportManager = new ExportManager($exporterConfig);
            }
            if ($registerDefaults) {
                self::registerDefaults();
            }
        }

        /**
         * Registers default importers/exporters.
         */
        public static function registerDefaults () : void {
            self::$importManager
                ->register(new JsonImporter())
                ->register(new JsonLinesImporter())
                ->register(new IniImporter())
                ->register(new CsvImporter())
                ->register(new PhpImporter())
                ->setFallback(new TextImporter());
            
            self::$exportManager
                ->register(new JsonExporter())
                ->register(new JsonLinesExporter())
                ->register(new IniExporter())
                ->register(new CsvExporter())
                ->setFallback(new TextExporter());

            self::bind(JsonImporter::class, JsonExporter::class);
            self::bind(JsonLinesImporter::class, JsonLinesExporter::class);
            self::bind(IniImporter::class, IniExporter::class);
            self::bind(CsvImporter::class, CsvExporter::class);
            self::bind(TextImporter::class, TextExporter::class);
        }

        /**
         * Unbinds a specific importer/exporter pair or all bindings if none provided.
         * @param string|ImporterInterface|null $importer An importer or importer class.
         * @param string|ExporterInterface|null $exporter An exporter or exporter class.
         */
        public static function unbind (string|ImporterInterface|null $importer = null, string|ExporterInterface|null $exporter = null) : void {
            if ($importer !== null) {
                $importerClass = is_object($importer) ? get_class($importer) : $importer;
                $exporterClass = self::$importerToExporter[$importerClass] ?? null;
                unset(self::$importerToExporter[$importerClass]);
                if ($exporterClass !== null) {
                    unset(self::$exporterToImporter[$exporterClass]);
                }
            }
            elseif ($exporter !== null) {
                $exporterClass = is_object($exporter) ? get_class($exporter) : $exporter;
                $importerClass = self::$exporterToImporter[$exporterClass] ?? null;
                unset(self::$exporterToImporter[$exporterClass]);
                if ($importerClass !== null) {
                    unset(self::$importerToExporter[$importerClass]);
                }
            }
            else {
                self::$importerToExporter = [];
                self::$exporterToImporter = [];
            }
        }

        /**
         * Gets the import manager.
         * @return ImportManager The import manager.
         */
        public static function getImportManager () : ImportManager {
            self::ensureInitialised();
            return self::$importManager;
        }

        /**
         * Gets the export manager.
         * @return ExportManager The export manager.
         */
        public static function getExportManager () : ExportManager {
            self::ensureInitialised();
            return self::$exportManager;
        }

        /**
         * Gets the twin IO (importer/exporter) for a given IO (importer/exporter).
         * @param class-string|ImporterInterface|ExporterInterface $io An importer/exporter class name or instance.
         * @return ImporterInterface|ExporterInterface|null The twin IO, or `null` if none found.
         */
        public static function getTwin (string|ImporterInterface|ExporterInterface $io) : ImporterInterface|ExporterInterface|null {
            if (is_string($io)) {
                if (is_a($io, ImporterInterface::class, true)) {
                    return self::getTwinExporter($io);
                }
                elseif (is_a($io, ExporterInterface::class, true)) {
                    return self::getTwinImporter($io);
                }
                return null;
            }
            if ($io instanceof ImporterInterface) {
                return self::getTwinExporter($io);
            }
            elseif ($io instanceof ExporterInterface) {
                return self::getTwinImporter($io);
            }
            return null;
        }

        /**
         * Gets the twin exporter for an importer (class or instance).
         * @param class-string|ImporterInterface $importer An importer class name or instance.
         * @return ExporterInterface|null The twin exporter, or `null` if none found.
         */
        public static function getTwinExporter (string|ImporterInterface $importer) : ?ExporterInterface {
            self::ensureInitialised();
            $importerClass = is_object($importer) ? get_class($importer) : $importer;
            $exporterClass = self::$importerToExporter[$importerClass] ?? null;

            if ($exporterClass === null) {
                return null;
            }

            if (self::$exportManager->has($exporterClass)) {
                return self::$exportManager->get($exporterClass);
            }

            if (class_exists($exporterClass)) {
                return new $exporterClass();
            }

            return null;
        }

        /**
         * Gets the twin importer for an exporter (class or instance).
         * @param class-string|ExporterInterface $exporter An exporter class name or instance.
         * @return ImporterInterface|null The twin importer, or `null` if none found.
         */
        public static function getTwinImporter (string|ExporterInterface $exporter) : ?ImporterInterface {
            self::ensureInitialised();
            $exporterClass = is_object($exporter) ? get_class($exporter) : $exporter;
            $importerClass = self::$exporterToImporter[$exporterClass] ?? null;

            if ($importerClass === null) {
                return null;
            }

            if (self::$importManager->has($importerClass)) {
                return self::$importManager->get($importerClass);
            }

            if (class_exists($importerClass)) {
                return new $importerClass();
            }

            return null;
        }
    }
?>