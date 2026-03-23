<?php
    /**
     * Project Name:    Wingman Explorer - CSV Importer
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
    use Wingman\Locator\Asserter;
    use Wingman\Explorer\Interfaces\IO\ExporterInterface;
    use Wingman\Explorer\Interfaces\IO\ImporterInterface;
    use Wingman\Explorer\Interfaces\IO\ReversibleIOInterface;
    use Wingman\Explorer\Interfaces\IO\StreamableImporterInterface;
    use Wingman\Explorer\IO\Exporters\CsvExporter;
    use Wingman\Explorer\IO\IOManager;
    use Wingman\Explorer\IO\Stream;
    use Wingman\Explorer\Traits\CanProcess;

    /**
     * An importer for CSV files.
     *
     * Reads rows line-by-line, optionally treating the first row as a header
     * to produce associative arrays for each subsequent record. Supports
     * configurable separator, enclosure, and escape characters. Also implements
     * {@see StreamableImporterInterface} for streaming ingestion.
     *
     * Supported options:
     * - <code>header</code> (bool, default <code>true</code>) — treat first row as column headers.
     * - <code>separator</code> (string, default <code>","</code>) — field delimiter.
     * - <code>enclosure</code> (string, default <code>'"'</code>) — field enclosure character.
     * - <code>escape</code> (string, default <code>"\\"</code>) — escape character.
     *
     * @package Wingman\Explorer\IO\Importers
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class CsvImporter implements ImporterInterface, ReversibleIOInterface, StreamableImporterInterface {
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

            # File extension.
            if ($this->supportsExtension($extension ?? '')) {
                $score += 0.5;
            }

            # MIME type hint.
            if ($this->supportsMime($mime ?? '')) {
                $score += 0.2;
            }

            # Sample: count commas (at least 2 in sample indicates CSV).
            if (substr_count($sample, ',') >= 2) {
                $score += 0.2;
            }

            # Optional: first line split check (weak).
            $lines = preg_split("/\r?\n/", $sample);
            if (!empty($lines) && str_contains($lines[0], ',')) {
                $score += 0.1;
            }

            # CSV never reaches full certainty.
            return min($score, 0.7);
        }

        /**
         * Gets the exporter counterpart for this importer.
         * @return ExporterInterface The twin exporter.
         */
        public function getExporter () : ExporterInterface {
            return IOManager::getTwinExporter(static::class) ?? new CsvExporter();
        }

        /**
         * Gets this importer.
         * @return ImporterInterface This importer.
         */
        public function getImporter () : ImporterInterface {
            return $this;
        }

        /**
         * Imports and parses rows from a CSV file.
         * @param string $path The path to the file.
         * @param array $options Additional options for importing.
         * @return array|null The parsed rows, or null if the file is empty.
         */
        public function import (string $path, array $options = []) : array|null {
            Asserter::requireFileAt($path);

            $header = $options["header"] ?? true;
            $separator = $options["separator"] ?? ',';
            $enclosure = $options["enclosure"] ?? '"';
            $escape = $options["escape"] ?? '\\';

            $handle = fopen($path, 'r');
            if (!$handle) return null;

            $rows = [];

            if ($header) {
                $rawHeader = fgets($handle);
                if ($rawHeader === false) return null;

                $rawHeader = $this->preprocess($rawHeader, $options);
                $keys = str_getcsv($rawHeader, $separator, $enclosure, $escape);

                while (($line = fgets($handle)) !== false) {
                    $line = $this->preprocess($line, $options);
                    $row  = str_getcsv($line, $separator, $enclosure, $escape);
                    $rows[] = $this->postprocess(array_combine($keys, $row) ?: $row, $options);
                }
            }
            else {
                while (($line = fgets($handle)) !== false) {
                    $line = $this->preprocess($line, $options);
                    $rows[] = $this->postprocess(
                        str_getcsv($line, $separator, $enclosure, $escape),
                        $options
                    );
                }
            }

            fclose($handle);
            return $rows;
        }

        /**
         * Imports and parses CSV data from a stream.
         * @param Stream $stream The source stream to read from.
         * @param array $options Additional options for importing.
         * @return array|null The parsed rows, or null if the stream is empty.
         */
        public function importStream (Stream $stream, array $options = []) : array|null {
            $header = $options["header"] ?? true;
            $separator = $options["separator"] ?? ',';
            $enclosure = $options["enclosure"] ?? '"';
            $escape = $options["escape"] ?? '\\';

            $rows = [];

            if ($header) {
                $rawHeader = $stream->readLine();
                if ($rawHeader === null) return null;

                $rawHeader = $this->preprocess($rawHeader, $options);
                $keys = str_getcsv($rawHeader, $separator, $enclosure, $escape);

                while (($line = $stream->readLine()) !== null) {
                    $line = $this->preprocess($line, $options);
                    $row = str_getcsv($line, $separator, $enclosure, $escape);
                    $rows[] = $this->postprocess(array_combine($keys, $row) ?: $row, $options);
                }
            }
            else {
                while (($line = $stream->readLine()) !== null) {
                    $line = $this->preprocess($line, $options);
                    $rows[] = $this->postprocess(
                        str_getcsv($line, $separator, $enclosure, $escape),
                        $options
                    );
                }
            }

            return $rows;
        }

        /**
         * Checks whether this importer can handle the given file.
         * @param string $path The path to the file.
         * @param string|null $extension The file extension (optional).
         * @param string|null $mime The MIME type of the file (optional).
         * @return bool Whether the importer supports the file.
         */
        public function supports (string $path, ?string $extension = null, ?string $mime = null) : bool {
            if ($extension !== null && !$this->supportsExtension($extension)) {
                return false;
            }
            if ($mime !== null && !$this->supportsMime($mime)) {
                return false;
            }
            return true;
        }

        /**
         * Checks whether this importer supports the given file extension.
         * @param string $extension The file extension.
         * @return bool Whether the extension is supported.
         */
        public function supportsExtension (string $extension) : bool {
            return strtolower($extension) === "csv";
        }

        /**
         * Checks whether this importer supports the given MIME type.
         * @param string $mime The MIME type.
         * @return bool Whether the MIME type is supported.
         */
        public function supportsMime (string $mime) : bool {
            return $mime === "text/csv";
        }
    }
?>