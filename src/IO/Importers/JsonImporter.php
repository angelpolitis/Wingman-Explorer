<?php
    /**
     * Project Name:    Wingman Explorer - Json Importer
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
    use Wingman\Explorer\Exceptions\DecodingException;
    use Wingman\Locator\Asserter;
    use Wingman\Explorer\Interfaces\IO\ExporterInterface;
    use Wingman\Explorer\Interfaces\IO\ImporterInterface;
    use Wingman\Explorer\Interfaces\IO\ReversibleIOInterface;
    use Wingman\Explorer\Interfaces\IO\StreamableImporterInterface;
    use Wingman\Explorer\IO\Exporters\JsonExporter;
    use Wingman\Explorer\IO\IOManager;
    use Wingman\Explorer\IO\Stream;
    use Wingman\Explorer\Traits\CanProcess;

    /**
     * An importer for JSON and JSONC files.
     *
     * Parses standard JSON (<code>.json</code>) as well as JSON with Comments
     * (<code>.jsonc</code>). Single-line (<code>//</code>, <code>#</code>) and
     * multi-line (<code>/* … *\/</code>) comments are stripped before parsing
     * so that lightly annotated configuration files are handled transparently.
     * Also implements {@see StreamableImporterInterface} for in-memory stream
     * ingestion via {@see importStream()}.
     *
     * @package Wingman\Explorer\IO\Importers
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class JsonImporter implements ImporterInterface, ReversibleIOInterface, StreamableImporterInterface {
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
                $score += 0.4;
            }

            # Medium match on MIME type.
            if ($this->supportsMime($mime ?? '')) {
                $score += 0.3;
            }

            # Sample content check (first non-whitespace character).
            $trimmed = ltrim($sample);
            if ($trimmed !== '' && ($trimmed[0] === '{' || $trimmed[0] === '[')) {
                $score += 0.2;
            }

            # Optional: structural validation of small sample (lightweight).
            try {
                json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
                $score += 0.2;
            }
            catch (JsonException) {
                # Not valid JSON — no additional score.
            }

            return min($score, 1.0);
        }

        /**
         * Gets the exporter counterpart for this importer.
         * @return ExporterInterface The twin exporter.
         */
        public function getExporter () : ExporterInterface {
            return IOManager::getTwinExporter(static::class) ?? new JsonExporter();
        }

        /**
         * Gets this importer.
         * @return ImporterInterface This importer.
         */
        public function getImporter () : ImporterInterface {
            return $this;
        }

        /**
         * Imports and parses data from a JSON or JSONC file.
         * @param string $path The path to the file.
         * @param array $options Additional options for importing.
         * @return array|null The parsed data, or null on failure.
         */
        public function import (string $path, array $options = []) : array|null {
            Asserter::requireFileAt($path);

            $jsonc = file_get_contents($path);
            $jsonc = $this->preprocess($jsonc, $options);

            # Strip comments (JSONC).
            $jsonc = preg_replace(
                '~(" (?:\\\\. | [^"])*+ ") | \# [^\v]*+ | // [^\v]*+ | /\* .*? \*/~xs',
                '$1',
                $jsonc
            );

            try {
                $data = json_decode($jsonc, true, 512, JSON_THROW_ON_ERROR);
            }
            catch (JsonException $e) {
                throw new DecodingException("Failed to parse JSON: " . $e->getMessage(), 0, $e);
            }

            return $this->postprocess($data, $options);
        }

        /**
         * Imports and parses JSON data from a stream.
         * @param Stream $stream The source stream to read from.
         * @param array $options Additional options for importing.
         * @return array|null The parsed content.
         */
        public function importStream (Stream $stream, array $options = []) : array|null {
            $jsonc = $stream->readAll();
            $jsonc = $this->preprocess($jsonc, $options);

            # Strip comments (JSONC).
            $jsonc = preg_replace(
                '~(" (?:\\\\. | [^"])*+ ") | \# [^\v]*+ | // [^\v]*+ | /\* .*? \*/~xs',
                '$1',
                $jsonc
            );

            try {
                $data = json_decode($jsonc, true, 512, JSON_THROW_ON_ERROR);
            }
            catch (JsonException $e) {
                throw new DecodingException("Failed to parse JSON: " . $e->getMessage(), 0, $e);
            }

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
            $ext = strtolower($extension);
            return in_array($ext, ["json", "jsonc"], true);
        }

        /**
         * Checks whether this importer supports the given MIME type.
         * @param string $mime The MIME type.
         * @return bool Whether the MIME type is supported.
         */
        public function supportsMime (string $mime) : bool {
            return $mime === "application/json";
        }
    }
?>