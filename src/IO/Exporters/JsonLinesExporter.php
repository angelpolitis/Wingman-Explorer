<?php
    /**
     * Project Name:    Wingman Explorer - Json Lines Exporter
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
    use Wingman\Explorer\Exceptions\EncodingException;
    use Wingman\Explorer\Exceptions\ExportException;
    use JsonException;
    use Wingman\Explorer\Interfaces\IO\ExporterInterface;
    use Wingman\Explorer\Interfaces\IO\ImporterInterface;
    use Wingman\Explorer\Interfaces\IO\ReversibleIOInterface;
    use Wingman\Explorer\IO\Importers\JsonLinesImporter;
    use Wingman\Explorer\IO\IOManager;
    use Wingman\Explorer\Traits\CanProcess;

    /**
     * An exporter for JSON Lines (NDJSON) files.
     *
     * Iterates over the supplied data and writes each item as a single line of
     * JSON followed by a newline character, producing a valid newline-delimited
     * JSON file. The {@see json_encode()} flags can be customised via the
     * options array.
     *
     * Supported options:
     * - <code>flags</code> (int, default <code>JSON_UNESCAPED_SLASHES</code>) — {@see json_encode()} flags.
     *
     * @package Wingman\Explorer\IO\Exporters
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class JsonLinesExporter implements ExporterInterface, ReversibleIOInterface {
        use CanProcess;

        /**
         * Writes data to a JSON Lines file handle.
         * @param mixed $data The data to write.
         * @param resource $handle The file handle.
         * @param array $options Additional options.
         * @throws EncodingException If JSON encoding fails.
         */
        protected function write (mixed $data, $handle, array $options = []) : void {
            $data = $this->preprocess($data, $options);

            $flags = $options["flags"] ?? JSON_UNESCAPED_SLASHES;
            
            foreach ($data as $line) {
                try {
                    $json = json_encode($line, $flags | JSON_THROW_ON_ERROR);
                }
                catch (JsonException $e) {
                    throw new EncodingException("Failed to encode line as JSON: " . $e->getMessage());
                }

                fwrite($handle, $json . "\n");
            }
        }
        
        /**
         * Exports data to a JSON Lines file at the given path.
         * @param mixed $data An iterable of JSON-encodable items.
         * @param string $path The path to the file.
         * @param array $options Additional options for exporting.
         * @throws ExportException If the file cannot be opened for writing.
         * @return static The exporter instance.
         */
        public function export (mixed $data, string $path, array $options = []) : static {
            $file = fopen($path, 'w');

            if ($file === false) {
                throw new ExportException("Unable to open file '$path' for writing.");
            }

            $this->write($data, $file, $options);

            fclose($file);

            return $this;
        }

        /**
         * Gets the confidence level of the exporter for a given dataset and hint.
         * @param mixed $data The data to evaluate.
         * @param string|null $extension The file extension (optional).
         * @param string|null $mime The MIME type of the file (optional).
         * @return float A confidence score between 0.0 and 1.0.
         */
        public function getConfidence (mixed $data, ?string $extension = null, ?string $mime = null) : float {
            $ext = strtolower($extension ?? "");
            if (in_array($ext, ["jsonl", "jsonlines"], true)) {
                return 1.0;
            }

            if ($mime && in_array(strtolower($mime), ["application/jsonl", "application/x-ndjson"], true)) {
                return 1.0;
            }

            # Data-based heuristics.
            if (!is_iterable($data)) return 0.0;

            $sample = [];
            foreach ($data as $item) {
                $sample[] = $item;

                # Only take a few lines for sampling.
                if (count($sample) >= 3) break;
            }

            if (empty($sample)) return 0.0;

            # Check whether each line is a scalar, array, or object.
            foreach ($sample as $item) {
                if (!is_scalar($item) && !is_array($item) && !is_object($item)) {
                    # The item is unusual, so have low confidence.
                    return 0.3;
                }
            }

            # Mixed content: arrays/objects in lines.
            $assocCount = 0;
            foreach ($sample as $item) {
                if (is_array($item) && array_keys($item) !== range(0, count($item) - 1)) {
                    $assocCount++;
                }
            }

            # For all data being associative arrays, have high confidence.
            if ($assocCount === count($sample)) return 0.95;
            
            # For some associative arrays, have medium confidence.
            if ($assocCount > 0) return 0.7;

            # For plain arrays or scalars, have moderate confidence.
            return 0.6;
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
            return IOManager::getTwinImporter(static::class) ?? new JsonLinesImporter();
        }

        /**
         * Serialises data to a JSON Lines string using an in-memory buffer.
         * @param mixed $data An iterable of JSON-encodable items.
         * @param array $options Additional options for serialising.
         * @return string The NDJSON-formatted string.
         */
        public function prepare (mixed $data, array $options = []) : string {
            $handle = fopen("php://memory", 'r+');
            
            $this->write($data, $handle, $options);
        
            rewind($handle);
            $json = stream_get_contents($handle);
            fclose($handle);

            return $this->postprocess($json === false ? "" : $json, $options);
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

            return true;
        }

        /**
         * Checks whether this exporter supports the given file extension.
         * @param string $extension The file extension.
         * @return bool Whether the extension is supported.
         */
        public function supportsExtension (string $extension) : bool {
            $extension = strtolower($extension);
            return in_array($extension, ["jsonl", "jsonlines"], true);
        }

        /**
         * Checks whether this exporter supports the given MIME type.
         * @param string $mime The MIME type.
         * @return bool Whether the MIME type is supported.
         */
        public function supportsMime (string $mime) : bool {
            return in_array(strtolower($mime), ["application/jsonl", "application/x-ndjson"], true);
        }
    }
?>