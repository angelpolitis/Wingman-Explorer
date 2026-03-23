<?php
    /**
     * Project Name:    Wingman Explorer - CSV Exporter
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
    use Wingman\Explorer\IO\Importers\CsvImporter;
    use Wingman\Explorer\IO\IOManager;
    use Wingman\Explorer\Traits\CanProcess;

    /**
     * An exporter for CSV files.
     *
     * Accepts an iterable of arrays (rows) and writes them as comma-separated
     * values. When the row arrays are associative, the keys are automatically
     * written as a header row. Supports configurable delimiter, enclosure, and
     * escape characters via the options array.
     *
     * Supported options:
     * - <code>delimiter</code> (string, default <code>","</code>) — field delimiter.
     * - <code>enclosure</code> (string, default <code>'"'</code>) — field enclosure character.
     * - <code>escape</code> (string, default <code>"\\"</code>) — escape character.
     *
     * @package Wingman\Explorer\IO\Exporters
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class CsvExporter implements ExporterInterface, ReversibleIOInterface {
        use CanProcess;

        /**
         * Writes data to a CSV file handle.
         * @param mixed $data The data to write.
         * @param resource $handle The file handle.
         * @param array $options Additional options.
         * @throws ExportException If the data rows are not arrays.
         */
        protected function write (mixed $data, $handle, array $options = []) : void {
            $rows = is_iterable($data) ? $data : [$data];
            $headerWritten = false;
            $headers = null;
        
            $delimiter = $options["delimiter"] ?? ',';
            $enclosure = $options["enclosure"] ?? '"';
            $escape = $options["escape"] ?? '\\';
        
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new ExportException("The CSV exporter expects rows to be arrays.");
                }
        
                if (!$headerWritten) {
                    $headers = array_keys($row);
        
                    # Detect associative array.
                    $isAssoc = $headers !== range(0, count($headers) - 1);
        
                    if ($isAssoc) {
                        fputcsv($handle, $headers, $delimiter, $enclosure, $escape);
                    }
        
                    $headerWritten = true;
                }
        
                # Ensure column order matches header and discard any extra keys.
                if ($headers) {
                    $row = array_replace(array_flip($headers), array_intersect_key($row, array_flip($headers)));
                }
        
                fputcsv($handle, array_values($row), $delimiter, $enclosure, $escape);
            }
        }

        /**
         * Exports data to a CSV file at the given path.
         * @param mixed $data An iterable of arrays (rows) to write.
         * @param string $path The path to the file.
         * @param array $options Additional options for exporting.
         * @throws ExportException If the file cannot be opened for writing.
         * @return static The exporter instance.
         */
        public function export (mixed $data, string $path, array $options = []) : static {
            $handle = fopen($path, 'w');

            if ($handle === false) {
                throw new ExportException("Unable to open file '{$path}' for writing.");
            }

            $data = $this->preprocess($data, $options);

            $this->write($data, $handle, $options);

            fclose($handle);
            return $this;
        }

        /**
         * Gets the confidence level of the exporter for a given dataset and hint.
         * @param mixed $data The data to evaluate.
         * @param string|null $extension The file extension (optional).
         * @param string|null $mime The MIME type of the file (optional).
         * @return float A confidence score between 0.0 and 1.0.
         */
        public function getConfidence (mixed $data, ?string $extension, ?string $mime) : float {
            if (!is_iterable($data)) {
                return 0.0;
            }
        
            $rows = [];
            foreach ($data as $row) {
                $rows[] = $row;
                if (count($rows) >= 3) break;
            }
        
            if (empty($rows)) {
                return 0.0;
            }
        
            $assoc = array_keys($rows[0]) !== range(0, count($rows[0]) - 1);
        
            foreach ($rows as $row) {
                if (!is_array($row)) return 0.0;
                if ($assoc && array_keys($row) !== array_keys($rows[0])) {
                    return 0.3;
                }
            }
        
            return $assoc ? 0.95 : 0.7;
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
            return IOManager::getTwinImporter(static::class) ?? new CsvImporter();
        }

        /**
         * Serialises data to a CSV string using an in-memory buffer.
         * @param mixed $data An iterable of arrays (rows) to serialise.
         * @param array $options Additional options for serialising.
         * @return string The CSV-formatted string.
         */
        public function prepare (mixed $data, array $options = []) : string {
            $handle = fopen("php://memory", "r+");
            
            $this->write($data, $handle, $options);
        
            rewind($handle);
            $csv = stream_get_contents($handle);
            fclose($handle);
        
            $csv = $csv !== false ? $csv : "";

            return $this->postprocess($csv, $options);
        }

        /**
         * Checks whether this exporter can handle the given data and hints.
         * @param mixed $data The data to evaluate.
         * @param string|null $extension The file extension (optional).
         * @param string|null $mime The MIME type of the file (optional).
         * @return bool Whether the exporter supports the data.
         */
        public function supports (mixed $data, ?string $extension = null, ?string $mime = null) : bool {
            if ($extension && !$this->supportsExtension($extension)) {
                return false;
            }

            if ($mime && !$this->supportsMime($mime)) {
                return false;
            }

            if (isset($data)) {
                if (!is_array($data) && !is_iterable($data)) {
                    return false;
                }
            
                foreach ($data as $row) {
                    return is_array($row);
                }
            }
        
            return true;
        }

        /**
         * Checks whether this exporter supports the given file extension.
         * @param string $extension The file extension.
         * @return bool Whether the extension is supported.
         */
        public function supportsExtension (string $extension) : bool {
            return strtolower($extension) === "csv";
        }

        /**
         * Checks whether this exporter supports the given MIME type.
         * @param string $mime The MIME type.
         * @return bool Whether the MIME type is supported.
         */
        public function supportsMime (string $mime) : bool {
            return in_array(strtolower($mime), ["text/csv", "application/csv"], true);
        }
    }
?>