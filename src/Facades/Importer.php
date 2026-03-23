<?php
    /**
     * Project Name:    Wingman Explorer - Importer Facade
     * Created by:      Angel Politis
     * Creation Date:   Dec 18 2025
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2025-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer.Facades namespace.
    namespace Wingman\Explorer\Facades;

    # Import the following classes to the current scope.
    use Wingman\Explorer\Exceptions\UnsupportedImportTypeException;
    use Wingman\Explorer\Interfaces\IO\ImporterInterface;
    use Wingman\Explorer\IO\ImportManager;
    use Wingman\Explorer\IO\IOManager;

    /**
     * A facade used to manage importers and import files.
     * @package Wingman\Explorer\Facades
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    final class Importer {
        /**
         * Gets the import manager.
         * @return ImportManager The import manager.
         */
        private static function getManager () : ImportManager {
            return IOManager::getImportManager();
        }
    
        /**
         * Selects an importer by type.
         * @param string $extension The file extension/type.
         * @return PreselectedImporter The preselected importer.
         * @throws UnsupportedImportTypeException If no importer supports the given type.
         */
        public static function forType (string $extension) : PreselectedImporter {
            $manager = self::getManager();
            $importer = $manager->getByType($extension);

            if ($importer !== null) {
                return new PreselectedImporter($importer);
            }

            if ($manager->getFallback() !== null) {
                return new PreselectedImporter($manager->getFallback());
            }

            throw new UnsupportedImportTypeException("No importer supports type '{$extension}'.");
        }

        /**
         * Selects an importer by MIME type.
         * @param string $mime The MIME type.
         * @return PreselectedImporter The preselected importer.
         * @throws UnsupportedImportTypeException If no importer supports the given MIME type.
         */
        public static function forMime (string $mime) : PreselectedImporter {
            $manager = self::getManager();
            $importer = $manager->getByMime($mime);

            if ($importer !== null) {
                return new PreselectedImporter($importer);
            }

            if ($manager->getFallback() !== null) {
                return new PreselectedImporter($manager->getFallback());
            }

            throw new UnsupportedImportTypeException("No importer supports MIME '{$mime}'.");
        }

        /**
         * Gets a registered importer by its class name.
         * @param string $class The class name of the importer.
         * @return ImporterInterface|null The importer, or `null` if not found.
         */
        public static function get (string $class) : ?ImporterInterface {
            return self::getManager()->get($class);
        }

        /**
         * Gets all registered importers.
         * @return ImporterInterface[] The registered importers.
         */
        public static function getAll () : array {
            return self::getManager()->getAll();
        }
    
        /**
         * Gets the best matching importer for a given file path.
         * @param string $path The file path.
         * @return ImporterInterface|null The best matching importer, or `null` if none found.
         */
        public static function getBestMatch (string $path) : ?ImporterInterface {
            return self::getManager()->getBestMatch($path);
        }
        /**
         * Checks if an importer is registered by its class name.
         * @param string $class The class name of the importer.
         * @return bool Whether the importer is registered.
         */
        public static function has (string $class) : bool {
            return self::getManager()->has($class);
        }
    
        /**
         * Imports a file.
         * @param string $path The path to the file.
         * @param array $options Additional options for importing.
         * @return mixed The imported content.
         */
        public static function import (string $file, array $options = []) : mixed {
            return self::getManager()->import($file, $options);
        }
    
        /**
         * Registers an importer.
         * @param ImporterInterface $importer The importer to register.
         * @return static The import manager.
         */
        public static function register (ImporterInterface $importer) : ImportManager {
            return self::getManager()->register($importer);
        }

        /**
         * Sets the fallback importer.
         * @param ImporterInterface $importer The fallback importer.
         * @return static The import manager.
         */
        public static function setFallback (ImporterInterface $importer) : ImportManager {
            return self::getManager()->setFallback($importer);
        }

        /**
         * Unregisters an importer by class name.
         * @param string $class The class name of the importer to unregister.
         * @return static The import manager.
         */
        public static function unregister (string $class) : ImportManager {
            return self::getManager()->unregister($class);
        }
    }
?>