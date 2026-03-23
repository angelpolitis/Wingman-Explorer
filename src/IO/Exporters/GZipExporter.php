<?php
    /**
     * Project Name:    Wingman Explorer - GZip Exporter
     * Created by:      Angel Politis
     * Creation Date:   Dec 19 2025
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2025-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer.IO.Exporters namespace.
    namespace Wingman\Explorer\IO\Exporters;

    # Import the following classes to the current scope.
    use Wingman\Explorer\Exceptions\ExportException;
    use Wingman\Explorer\Interfaces\IO\ExporterInterface;
    use Wingman\Explorer\Interfaces\IO\ImporterInterface;
    use Wingman\Explorer\Interfaces\IO\ReversibleIOInterface;
    use Wingman\Explorer\IO\Importers\GZipImporter;
    use Wingman\Explorer\IO\IOManager;
    use Wingman\Explorer\Traits\CanProcess;

    /**
     * An exporter for GZip-compressed files.
     *
     * Accepts a string payload and compresses it with PHP's built-in
     * {@see gzencode()}. Because GZip is a container format, the input is
     * expected to already be serialised text; non-string data is rejected by
     * {@see supports()}. The compressed output is written directly to the
     * target file.
     *
     * @package Wingman\Explorer\IO\Exporters
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class GZipExporter implements ExporterInterface, ReversibleIOInterface {
        use CanProcess;
        
        /**
         * Exports a GZip-compressed file at the given path.
         * @param mixed $data The string content to compress.
         * @param string $path The path to the file.
         * @param array $options Additional options for exporting.
         * @return static The exporter instance.
         */
        public function export (mixed $data, string $path, array $options = []) : static {
            $content = $this->prepare($data, $options);
            if (file_put_contents($path, $content) === false) {
                throw new ExportException("Unable to write GZip file '$path'.");
            }
            return $this;
        }

        /**
         * Gets the confidence level of the exporter for a given dataset and hint.
         * @param mixed $data The data to evaluate.
         * @param string|null $extension The file extension (optional).
         * @param string|null $mime The MIME type of the file (optional).
         * @return float A confidence score between 0.0 and 1.0.
         */
        public function getConfidence (mixed $data, ?string $extension = null, ?string $mime = null): float {
            $score = 0.0;

            if ($this->supports($data, $extension, $mime)) {
                $score += 0.5;
            }

            if ($extension && $this->supportsExtension($extension)) {
                $score += 0.25;
            }

            if ($mime && $this->supportsMime($mime)) {
                $score += 0.25;
            }

            return $score;
        }

        /**
         * Gets this exporter.
         * @return ExporterInterface This exporter.
         */
        public function getExporter () : ExporterInterface {
            return $this;
        }

        /**
         * Gets the importer counterpart for this exporter.
         * @return ImporterInterface The twin importer.
         */
        public function getImporter () : ImporterInterface {
            return IOManager::getTwinImporter(static::class) ?? new GZipImporter();
        }

        /**
         * Compresses data to a GZip string.
         * @param mixed $data The string content to compress.
         * @param array $options Additional options for serialising.
         * @return string The GZip-compressed string.
         * @throws ExportException If compression fails.
         */
        public function prepare (mixed $data, array $options = []) : string {
            $content = $this->preprocess($data, $options);
            $compressed = gzencode($content);
            if ($compressed === false) {
                throw new ExportException("gzencode() failed to compress the given content.");
            }
            return $this->postprocess($compressed, $options);
        }

        /**
         * Checks whether this exporter can handle the given data and hints.
         * @param mixed $data The data to evaluate.
         * @param string|null $extension The file extension (optional).
         * @param string|null $mime The MIME type of the file (optional).
         * @return bool Whether the exporter supports the data.
         */
        public function supports (mixed $data, ?string $extension = null, ?string $mime = null) : bool {
            return is_string($data);
        }

        /**
         * Checks whether this exporter supports the given file extension.
         * @param string $extension The file extension.
         * @return bool Whether the extension is supported.
         */
        public function supportsExtension (string $extension) : bool {
            return in_array(strtolower($extension), ["gz", "gzip"], true);
        }

        /**
         * Checks whether this exporter supports the given MIME type.
         * @param string $mime The MIME type.
         * @return bool Whether the MIME type is supported.
         */
        public function supportsMime (string $mime) : bool {
            return in_array(strtolower($mime), ["application/gzip", "application/x-gzip"], true);
        }
    }
?>