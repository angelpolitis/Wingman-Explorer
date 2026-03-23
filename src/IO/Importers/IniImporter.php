<?php
    /**
     * Project Name:    Wingman Explorer - INI Importer
     * Created by:      Angel Politis
     * Creation Date:   Dec 18 2025
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2025-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer.IO.Importers namespace.
    namespace Wingman\Explorer\IO\Importers;

    # Import the following classes to the current scope.
    use Wingman\Explorer\Exceptions\ImportException;
    use Wingman\Locator\Asserter;
    use Wingman\Explorer\Interfaces\IO\ExporterInterface;
    use Wingman\Explorer\Interfaces\IO\ImporterInterface;
    use Wingman\Explorer\Interfaces\IO\ReversibleIOInterface;
    use Wingman\Explorer\IO\Exporters\IniExporter;
    use Wingman\Explorer\IO\IOManager;
    use Wingman\Explorer\Traits\CanProcess;

    /**
     * An importer for INI files.
     *
     * Parses INI content using PHP's built-in {@see parse_ini_string()} with
     * typed scanning enabled (<code>INI_SCANNER_TYPED</code>), so numeric and
     * boolean values are automatically cast to their native PHP types. Section
     * parsing can be toggled via the options array.
     *
     * Supported options:
     * - <code>sections</code> (bool, default <code>true</code>) — whether to parse section headers.
     *
     * @package Wingman\Explorer\IO\Importers
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class IniImporter implements ImporterInterface, ReversibleIOInterface {
        use CanProcess;
        
        /**
         * Gets the confidence level of the importer for a given file.
         * @param string $path The path to the file.
         * @param string|null $extension The file extension (optional).
         * @param string|null $mime The MIME type of the file (optional).
         * @param string $sample A sample of the file's content.
         * @return float A confidence score between 0.0 and 1.0.
         */
        public function getConfidence (string $path, ?string $extension, ?string $mime, string $sample) : float {
            $score = 0.0;
        
            # Strong match on file extension.
            if ($this->supportsExtension($extension ?? '')) {
                $score += 0.5;
            }
        
            # Check for basic INI line patterns.
            if (preg_match('/^\s*[a-zA-Z0-9_.-]+\s*=/m', $sample)) {
                $score += 0.3;
            }
        
            # Optional: parse sample to verify.
            if (@parse_ini_string($sample) !== false) {
                $score += 0.2;
            }
        
            return min($score, 1.0);
        }

        /**
         * Gets the exporter counterpart for this importer.
         * @return ExporterInterface The twin exporter.
         */
        public function getExporter () : ExporterInterface {
            return IOManager::getTwinExporter(static::class) ?? new IniExporter();
        }

        /**
         * Gets this importer.
         * @return ImporterInterface This importer.
         */
        public function getImporter () : ImporterInterface {
            return $this;
        }

        /**
         * Imports and parses data from an INI file.
         * @param string $path The path to the file.
         * @param array $options Additional options for importing.
         * @return array The parsed data.
         */
        public function import (string $path, array $options = []) : array {
            Asserter::requireFileAt($path);

            $sections = $options["sections"] ?? true;

            $content = file_get_contents($path);
            if ($content === false) {
                throw new ImportException("Failed to read file at path: $path");
            }
            $content = $this->preprocess($content, $options);

            $data = parse_ini_string($content, $sections, INI_SCANNER_TYPED) ?: [];

            return $this->postprocess($data, $options);
        }

        /**
         * Checks whether this importer can handle the given file.
         * @param string $path The path to the file.
         * @param string|null $extension The file extension (optional).
         * @param string|null $mime The MIME type of the file (optional).
         * @return bool Whether the importer supports the file.
         */
        public function supports (string $path, ?string $extension = null, ?string $mime = null) : bool {
            if ($extension !== null && $this->supportsExtension($extension)) {
                return true;
            }
            if ($mime !== null && $this->supportsMime($mime)) {
                return true;
            }
            return false;
        }

        /**
         * Checks whether this importer supports the given file extension.
         * @param string $extension The file extension.
         * @return bool Whether the extension is supported.
         */
        public function supportsExtension (string $extension) : bool {
            return strtolower($extension) === "ini";
        }

        /**
         * Checks whether this importer supports the given MIME type.
         * @param string $mime The MIME type.
         * @return bool Whether the MIME type is supported.
         */
        public function supportsMime (string $mime) : bool {
            return in_array($mime, ["text/plain"], true);
        }
    }
?>