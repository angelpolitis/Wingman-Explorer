<?php
    /**
     * Project Name:    Wingman Explorer - Json Lines Importer
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
    use JsonException;
    use Wingman\Explorer\Exceptions\ImportException;
    use Wingman\Locator\Asserter;
    use Wingman\Explorer\Interfaces\IO\ExporterInterface;
    use Wingman\Explorer\Interfaces\IO\ImporterInterface;
    use Wingman\Explorer\Interfaces\IO\ReversibleIOInterface;
    use Wingman\Explorer\Interfaces\IO\StreamableImporterInterface;
    use Wingman\Explorer\IO\Exporters\JsonLinesExporter;
    use Wingman\Explorer\IO\IOManager;
    use Wingman\Explorer\IO\Stream;
    use Wingman\Explorer\Traits\CanProcess;

    /**
     * An importer for JSON Lines (NDJSON) files.
     *
     * Reads a newline-delimited file where each non-empty line must be a valid
     * JSON value. Lines that fail to parse are stored as <code>null</code> in
     * the output array without interrupting the rest of the import. Also
     * implements {@see StreamableImporterInterface} for streaming ingestion.
     *
     * Supported options:
     * - <code>limit</code> (int|null, default <code>null</code>) — maximum number of lines to import.
     *
     * @package Wingman\Explorer\IO\Importers
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class JsonLinesImporter implements ImporterInterface, ReversibleIOInterface, StreamableImporterInterface {
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
        
            # Extension match.
            if ($this->supportsExtension($extension ?? '')) {
                $score += 0.4;
            }
        
            # MIME type hint (optional).
            if ($this->supportsMime($mime ?? '')) {
                $score += 0.2;
            }
        
            # Split sample into lines (first few lines only).
            $lines = preg_split("/\r?\n/", $sample);
            
            # Inspect up to 5 lines.
            $lines = array_slice($lines, 0, 5);
        
            $validLines = 0;
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if ($trimmed === '') continue;
        
                json_decode($trimmed);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $validLines++;
                }
            }
        
            $lineScore = count($lines) > 0 ? ($validLines / count($lines)) * 0.4 : 0.0;
            $score += $lineScore;
        
            return min($score, 1.0);
        }

        /**
         * Gets the exporter counterpart for this importer.
         * @return ExporterInterface The twin exporter.
         */
        public function getExporter () : ExporterInterface {
            return IOManager::getTwinExporter(static::class) ?? new JsonLinesExporter();
        }

        /**
         * Gets this importer.
         * @return ImporterInterface This importer.
         */
        public function getImporter () : ImporterInterface {
            return $this;
        }

        /**
         * Imports and parses records from a JSON Lines file.
         * @param string $path The path to the file.
         * @param array $options Additional options for importing.
         * @throws ImportException If the file cannot be opened.
         * @return array The parsed records.
         */
        public function import (string $path, array $options = []) : array {
            Asserter::requireFileAt($path);

            $limit = $options["limit"] ?? null;
            $data = [];
            $lineNo = 0;

            $handle = fopen($path, 'r');
            if (!$handle) {
                throw new ImportException("Unable to open file '{$path}'");
            }

            while (($line = fgets($handle)) !== false) {
                if ($limit !== null && ++$lineNo > $limit) break;

                $line = $this->preprocess($line, $options);

                try {
                    $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                }
                catch (JsonException) {
                    $decoded = null;
                }

                $data[] = $this->postprocess($decoded, $options);
            }

            fclose($handle);
            return $data;
        }

        /**
         * Imports and parses JSON Lines data from a stream.
         * @param Stream $stream The source stream to read from.
         * @param array $options Additional options for importing.
         * @return array The parsed records.
         */
        public function importStream (Stream $stream, array $options = []) : array {
            $limit = $options["limit"] ?? null;
            $data = [];
            $lineNo = 0;

            while (($line = $stream->readLine()) !== null) {
                if ($limit !== null && ++$lineNo > $limit) break;

                $line = $this->preprocess($line, $options);

                try {
                    $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                }
                catch (JsonException) {
                    $decoded = null;
                }

                $data[] = $this->postprocess($decoded, $options);
            }

            return $data;
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
            return in_array(strtolower($extension), ["jsonl", "jsonlines"], true);
        }

        /**
         * Checks whether this importer supports the given MIME type.
         * @param string $mime The MIME type.
         * @return bool Whether the MIME type is supported.
         */
        public function supportsMime (string $mime) : bool {
            return in_array($mime, ["application/jsonl", "application/x-ndjson"], true);
        }
    }
?>