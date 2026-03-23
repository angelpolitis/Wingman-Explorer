<?php
    /**
     * Project Name:    Wingman Explorer - Text Exporter
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
    use Wingman\Explorer\IO\Importers\TextImporter;
    use Wingman\Explorer\IO\IOManager;
    use Wingman\Explorer\Traits\CanProcess;

    /**
     * A fallback exporter for plain-text files.
     *
     * Serialises data to a plain-text file, falling back to {@see print_r()}
     * for non-string values. Because almost any data can be rendered as text,
     * this exporter reports an extremely low confidence score (0.01) and claims
     * to support all extensions and MIME types, making it the last-resort
     * choice in the negotiation chain. It is intended to be registered as the
     * {@see ExportManager} fallback rather than as a normal candidate.
     *
     * @package Wingman\Explorer\IO\Exporters
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class TextExporter implements ExporterInterface, ReversibleIOInterface {
        use CanProcess;
        
        /**
         * Exports data to a plain-text file at the given path.
         * @param mixed $data The data to export.
         * @param string $path The path to the file.
         * @param array $options Additional options for exporting.
         * @throws ExportException If the file cannot be written.
         * @return static The exporter instance.
         */
        public function export (mixed $data, string $path, array $options = []) : static {
            $content = $this->prepare($data, $options);

            if (file_put_contents($path, $content) === false) {
                throw new ExportException("Unable to write text file '$path'.");
            }

            return $this;
        }

        /**
         * Gets the confidence level of the exporter for a given dataset and hint.
         *
         * Always returns 0.01 so that any more specialised exporter takes
         * precedence during negotiation.
         * @param mixed $data The data to evaluate.
         * @param string|null $extension The file extension (optional).
         * @param string|null $mime The MIME type of the file (optional).
         * @return float A confidence score between 0.0 and 1.0.
         */
        public function getConfidence (mixed $data, ?string $extension = null, ?string $mime = null) : float {
            # Always very low confidence; only wins if nothing else matches.
            return 0.01;
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
            return IOManager::getTwinImporter(static::class) ?? new TextImporter();
        }

        /**
         * Serialises data to a plain-text string.
         * @param mixed $data The data to serialise.
         * @param array $options Additional options for serialising.
         * @return string The text representation.
         */
        public function prepare (mixed $data, array $options = []) : string {
            $content = $this->preprocess($data, $options);
            $content = is_string($content) ? $content : print_r($content, true);
            $content = $this->postprocess($content, $options);
            return $content;
        }
        
        /**
         * Checks whether this exporter can handle the given data and hints.
         *
         * Always returns true as plain text is the universal last resort.
         * @param mixed $data The data to evaluate.
         * @param string|null $ext The file extension (optional).
         * @param string|null $mime The MIME type of the file (optional).
         * @return bool Whether the exporter supports the data.
         */
        public function supports (mixed $data, ?string $ext = null, ?string $mime = null) : bool {
            return true;
        }

        /**
         * Checks whether this exporter supports the given file extension.
         *
         * Always returns true as plain text can be written to any file.
         * @param string $extension The file extension.
         * @return bool Whether the extension is supported.
         */
        public function supportsExtension (string $extension) : bool {
            return true;
        }

        /**
         * Checks whether this exporter supports the given MIME type.
         *
         * Always returns true as plain text is universally accepted.
         * @param string $mime The MIME type.
         * @return bool Whether the MIME type is supported.
         */
        public function supportsMime (string $mime) : bool {
            return true;
        }
    }
?>