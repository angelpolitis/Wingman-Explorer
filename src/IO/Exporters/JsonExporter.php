<?php
    /**
     * Project Name:    Wingman Explorer - Json Exporter
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
    use Wingman\Explorer\IO\Importers\JsonImporter;
    use Wingman\Explorer\IO\IOManager;
    use Wingman\Explorer\Traits\CanProcess;

    /**
     * An exporter for JSON files.
     *
     * Serialises data to a standard JSON file. By default the output is
     * pretty-printed with unescaped slashes. The encoding flags can be
     * customised via the options array.
     *
     * Supported options:
     * - <code>flags</code> (int, default <code>JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR</code>) — {@see json_encode()} flags.
     *
     * @package Wingman\Explorer\IO\Exporters
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class JsonExporter implements ExporterInterface, ReversibleIOInterface {
        use CanProcess;

        /**
         * Exports data to a JSON file at the given path.
         * @param mixed $data The data to export.
         * @param string $path The path to the file.
         * @param array $options Additional options for exporting.
         * @throws ExportException If the file cannot be written.
         * @return static The exporter instance.
         */
        public function export (mixed $data, string $path, array $options = []) : static {
            $json = $this->prepare($data, $options);

            if (file_put_contents($path, $json) === false) {
                throw new ExportException("Unable to write JSON file '{$path}'.");
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
        public function getConfidence (mixed $data, ?string $extension = null, ?string $mime = null) : float {
            $ext = strtolower($extension ?? "");
            if (in_array($ext, ["json", "jsonc"], true)) {
                return 1.0;
            }

            if ($mime && strtolower($mime) === "application/json") {
                return 1.0;
            }

            # Data-based heuristics.
            if (is_iterable($data)) {
                $sample = [];
                foreach ($data as $item) {
                    $sample[] = $item;
                    if (count($sample) >= 3) break;
                }

                if (empty($sample)) return 0.3;

                $assoc = is_array($sample[0]) && array_keys($sample[0]) !== range(0, count($sample[0]) - 1);

                foreach ($sample as $item) {
                    if (!is_array($item) && !is_object($item)) return 0.5;
                }

                return $assoc ? 0.95 : 0.7;
            }

            # Scalar top-level values — still valid JSON, but less confidence.
            return is_scalar($data) ? 0.4 : 0.0;
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
            return IOManager::getTwinImporter(static::class) ?? new JsonImporter();
        }

        /**
         * Serialises data to a JSON string.
         * @param mixed $data The data to serialise.
         * @param array $options Additional options for serialising.
         * @throws EncodingException If the data cannot be encoded.
         * @return string The JSON-encoded string.
         */
        public function prepare (mixed $data, array $options = []) : string {
            $data = $this->preprocess($data);

            $flags = $options["flags"] ?? (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

            try {
                $json = json_encode($data, $flags, 512);
            }
            catch (JsonException $e) {
                throw new EncodingException("Failed to encode data as JSON: " . $e->getMessage(), 0, $e);
            }

            return $this->postprocess($json, $options);
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
            return in_array($extension, ["json", "jsonc"], true);
        }

        /**
         * Checks whether this exporter supports the given MIME type.
         * @param string $mime The MIME type.
         * @return bool Whether the MIME type is supported.
         */
        public function supportsMime (string $mime) : bool {
            return strtolower($mime) === "application/json";
        }
    }
?>