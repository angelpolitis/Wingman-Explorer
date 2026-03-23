<?php
    /**
     * Project Name:    Wingman Explorer - GZip Importer
     * Created by:      Angel Politis
     * Creation Date:   Dec 19 2025
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2025-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer.IO.Importers namespace.
    namespace Wingman\Explorer\IO\Importers;

    # Import the following classes to the current scope.
    use Wingman\Explorer\Exceptions\DecodingException;
    use Wingman\Explorer\Exceptions\ImportException;
    use Wingman\Locator\Asserter;
    use Wingman\Explorer\Interfaces\IO\ExporterInterface;
    use Wingman\Explorer\Interfaces\IO\ImporterInterface;
    use Wingman\Explorer\Interfaces\IO\ReversibleIOInterface;
    use Wingman\Explorer\IO\Exporters\GZipExporter;
    use Wingman\Explorer\IO\IOManager;
    use Wingman\Explorer\Traits\CanProcess;

    /**
     * An importer for GZip-compressed files.
     *
     * Reads the raw file, decompresses it using PHP's built-in
     * {@see gzdecode()}, and returns the decompressed string. Supports
     * <code>.gz</code> and <code>.gzip</code> extensions and the standard
     * GZip MIME types. Because GZip is a container format, the decompressed
     * output is always returned as a raw string for further processing.
     *
     * @package Wingman\Explorer\IO\Importers
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class GZipImporter implements ImporterInterface, ReversibleIOInterface {
        use CanProcess;
        
        /**
         * Gets the confidence level of the importer for a given file.
         *
         * Always returns 0.01; extension and MIME matching is handled by
         * {@see supports()}, which is checked first during negotiation.
         * @param string $path The path to the file.
         * @param string|null $ext The file extension (optional).
         * @param string|null $mime The MIME type of the file (optional).
         * @param string $sample A sample of the file's content.
         * @return float A confidence score between 0.0 and 1.0.
         */
        public function getConfidence (string $path, ?string $ext, ?string $mime, string $sample) : float {
            # Always very low confidence; only wins if nothing else matches.
            return 0.01;
        }

        /**
         * Gets the exporter counterpart for this importer.
         * @return ExporterInterface The twin exporter.
         */
        public function getExporter () : ExporterInterface {
            return IOManager::getTwinExporter(static::class) ?? new GZipExporter();
        }

        /**
         * Gets this importer.
         * @return ImporterInterface This importer.
         */
        public function getImporter () : ImporterInterface {
            return $this;
        }
        
        /**
         * Decompresses and returns the content of a GZip file.
         * @param string $path The path to the file.
         * @param array $options Additional options for importing.
         * @throws ImportException If the file cannot be read.
         * @throws DecodingException If decompression fails.
         * @return string The decompressed content.
         */
        public function import (string $path, array $options = []) : string {
            Asserter::requireFileAt($path);
            $content = file_get_contents($path);
            if ($content === false) {
                throw new ImportException("Failed to read file at path: $path");
            }
            $content = $this->preprocess($content, $options);
            $decompressed = gzdecode($content);
            if ($decompressed === false) {
                throw new DecodingException("gzdecode() failed to decompress the content from: $path");
            }
            return $this->postprocess($decompressed, $options);
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
            return in_array(strtolower($extension), ["gz", "gzip"], true);
        }

        /**
         * Checks whether this importer supports the given MIME type.
         * @param string $mime The MIME type.
         * @return bool Whether the MIME type is supported.
         */
        public function supportsMime (string $mime) : bool {
            return in_array(strtolower($mime), ["application/gzip", "application/x-gzip"], true);
        }
    }
?>