<?php
    /**
     * Project Name:    Wingman Explorer - Text Importer
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
    use Wingman\Explorer\IO\Exporters\TextExporter;
    use Wingman\Explorer\IO\IOManager;
    use Wingman\Explorer\IO\Stream;
    use Wingman\Explorer\Traits\CanPostprocess;

    /**
     * A fallback importer for plain-text files.
     *
     * Returns the raw file contents as a string with no parsing. Because almost
     * any file can be treated as text, this importer deliberately reports an
     * extremely low confidence score (0.01) so that more specialised importers
     * always take precedence. It is intended to be registered as the
     * {@see ImportManager} fallback rather than as a normal candidate.
     *
     * @package Wingman\Explorer\IO\Importers
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class TextImporter implements ImporterInterface, ReversibleIOInterface, StreamableImporterInterface {
        use CanPostprocess;
        
        /**
         * Gets the confidence level of the importer for a given file.
         *
         * Always returns 0.01 so that any more specialised importer takes
         * precedence during negotiation.
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
            return IOManager::getTwinExporter(static::class) ?? new TextExporter();
        }

        /**
         * Gets this importer.
         * @return ImporterInterface This importer.
         */
        public function getImporter () : ImporterInterface {
            return $this;
        }
        
        /**
         * Imports the raw text content of a file.
         * @param string $path The path to the file.
         * @param array $options Additional options for importing.
         * @return string The file content.
         */
        public function import (string $path, array $options = []) : string {
            Asserter::requireFileAt($path);
            $content = file_get_contents($path);
            return $this->postprocess($content === false ? "" : $content, $options);
        }

        /**
         * Imports text data from a stream.
         * @param Stream $stream The source stream to read from.
         * @param array $options Additional options for importing.
         * @return string The stream content.
         */
        public function importStream (Stream $stream, array $options = []) : string {
            return $this->postprocess($stream->readAll(), $options);
        }

        /**
         * Checks whether this importer can handle the given file.
         * @param string $path The path to the file.
         * @param string|null $ext The file extension (optional).
         * @param string|null $mime The MIME type of the file (optional).
         * @return bool Whether the importer supports the file.
         */
        public function supports (string $path, ?string $ext = null, ?string $mime = null) : bool {
            if ($ext !== null && $this->supportsExtension($ext)) {
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
            return $extension === "" || $extension === "txt";
        }

        /**
         * Checks whether this importer supports the given MIME type.
         * @param string $mime The MIME type.
         * @return bool Whether the MIME type is supported.
         */
        public function supportsMime (?string $mime) : bool {
            return $mime === "text/plain";
        }
    }
?>