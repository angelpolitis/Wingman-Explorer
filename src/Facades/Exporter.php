<?php
    /**
     * Project Name:    Wingman Explorer - Exporter Facade
     * Created by:      Angel Politis
     * Creation Date:   Dec 19 2025
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2025-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer.Facades namespace.
    namespace Wingman\Explorer\Facades;

    # Import the following classes to the current scope.
    use Wingman\Explorer\Exceptions\UnsupportedExportTypeException;
    use Wingman\Explorer\Interfaces\IO\ExporterInterface;
    use Wingman\Explorer\IO\ExportManager;
    use Wingman\Explorer\IO\IOManager;

    /**
     * A facade used to manage exporters and export files.
     * @package Wingman\Explorer\Facades
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    final class Exporter {
        /**
         * Gets the export manager.
         * @return ExportManager The export manager.
         */
        private static function getManager () : ExportManager {
            return IOManager::getExportManager();
        }
    
        /**
         * Selects an exporter by type.
         * @param string $extension The file extension/type.
         * @return PreselectedExporter The preselected exporter.
         * @throws UnsupportedExportTypeException If no exporter supports the given type.
         */
        public static function forType (string $extension) : PreselectedExporter {
            $manager = self::getManager();
            $exporter = $manager->getByType($extension);

            if ($exporter !== null) {
                return new PreselectedExporter($exporter);
            }

            if ($manager->getFallback() !== null) {
                return new PreselectedExporter($manager->getFallback());
            }

            throw new UnsupportedExportTypeException("No exporter supports type '{$extension}'.");
        }

        /**
         * Selects an exporter by MIME type.
         * @param string $mime The MIME type.
         * @return PreselectedExporter The preselected exporter.
         * @throws UnsupportedExportTypeException If no exporter supports the given MIME type.
         */
        public static function forMime (string $mime) : PreselectedExporter {
            $manager = self::getManager();
            $exporter = $manager->getByMime($mime);

            if ($exporter !== null) {
                return new PreselectedExporter($exporter);
            }

            if ($manager->getFallback() !== null) {
                return new PreselectedExporter($manager->getFallback());
            }

            throw new UnsupportedExportTypeException("No exporter supports MIME '{$mime}'.");
        }

        /**
         * Gets a registered exporter by its class name.
         * @param string $class The class name of the exporter.
         * @return ExporterInterface|null The exporter, or `null` if not found.
         */
        public static function get (string $class) : ?ExporterInterface {
            return self::getManager()->get($class);
        }

        /**
         * Gets all registered exporters.
         * @return ExporterInterface[] The registered exporters.
         */
        public static function getAll () : array {
            return self::getManager()->getAll();
        }
    
        /**
         * Gets the best matching exporter for a given file path.
         * @param string $path The file path.
         * @return ExporterInterface|null The best matching exporter, or `null` if none found.
         */
        public static function getBestMatch (string $path) : ?ExporterInterface {
            $extension = pathinfo($path, PATHINFO_EXTENSION) ?: null;
            return self::getManager()->getBestMatch(null, $extension);
        }
        /**
         * Checks if an exporter is registered by its class name.
         * @param string $class The class name of the exporter.
         * @return bool Whether the exporter is registered.
         */
        public static function has (string $class) : bool {
            return self::getManager()->has($class);
        }
    
        /**
         * Exports data to a file using the best matching exporter.
         * @param mixed $data The data to export.
         * @param string $path The path to the file.
         * @param array $options Additional options for exporting.
         * @return mixed The exported content.
         */
        public static function export (mixed $data, string $file, array $options = []) : mixed {
            return self::getManager()->export($data, $file, $options);
        }
    
        /**
         * Registers an exporter.
         * @param ExporterInterface $exporter The exporter to register.
         * @return ExportManager The export manager.
         */
        public static function register (ExporterInterface $exporter) : ExportManager {
            return self::getManager()->register($exporter);
        }

        /**
         * Sets the fallback exporter.
         * @param ExporterInterface $exporter The fallback exporter.
         * @return ExportManager The export manager.
         */
        public static function setFallback (ExporterInterface $exporter) : ExportManager {
            return self::getManager()->setFallback($exporter);
        }

        /**
         * Unregisters an exporter by class name.
         * @param string $class The class name of the exporter to unregister.
         * @return ExportManager The export manager.
         */
        public static function unregister (string $class) : ExportManager {
            return self::getManager()->unregister($class);
        }
    }
?>