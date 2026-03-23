<?php
    /**
     * Project Name:    Wingman Explorer - Xml Importer
     * Created by:      Angel Politis
     * Creation Date:   Mar 20 2026
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer.IO.Importers namespace.
    namespace Wingman\Explorer\IO\Importers;

    # Import the following classes to the current scope.
    use LibXMLError;
    use Wingman\Explorer\Exceptions\DecodingException;
    use Wingman\Locator\Asserter;
    use Wingman\Explorer\Interfaces\IO\ExporterInterface;
    use Wingman\Explorer\Interfaces\IO\ImporterInterface;
    use Wingman\Explorer\Interfaces\IO\StreamableImporterInterface;
    use Wingman\Explorer\IO\Exporters\XmlExporter;
    use Wingman\Explorer\IO\IOManager;
    use Wingman\Explorer\IO\Stream;
    use Wingman\Explorer\Traits\CanProcess;

    /**
     * A class useful for importing XML files.
     *
     * Uses PHP's built-in {@see SimpleXMLElement} and {@see json_encode}/{@see json_decode}
     * to convert XML into a nested associative array — no external dependencies required.
     *
     * @package Wingman\Explorer\IO\Importers
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class XmlImporter implements ImporterInterface, StreamableImporterInterface {
        use CanProcess;

        /**
         * The MIME types supported by this importer.
         * @var string[]
         */
        private const MIME_TYPES = ["application/xml", "text/xml"];

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

            if ($this->supportsExtension($extension ?? '')) {
                $score += 0.4;
            }

            if ($this->supportsMime($mime ?? '')) {
                $score += 0.3;
            }

            # XML declaration is a strong indicator.
            if (preg_match('/<\?xml\b/i', $sample)) {
                $score += 0.2;
            }

            # First non-whitespace character being '<' is also indicative.
            if (ltrim($sample)[0] === '<') {
                $score += 0.1;
            }

            return min($score, 1.0);
        }

        /**
         * Gets the exporter counterpart for this importer.
         * @return ExporterInterface The twin exporter.
         */
        public function getExporter () : ExporterInterface {
            return IOManager::getTwinExporter(static::class) ?? new XmlExporter();
        }

        /**
         * Gets this importer.
         * @return ImporterInterface This importer.
         */
        public function getImporter () : ImporterInterface {
            return $this;
        }

        /**
         * Imports and parses an XML file into an associative array.
         * @param string $path The path to the file.
         * @param array $options Additional options for importing.
         * @throws DecodingException If the file cannot be parsed.
         * @return array|null The parsed content.
         */
        public function import (string $path, array $options = []) : array|null {
            Asserter::requireFileAt($path);

            $content = file_get_contents($path);
            $content = $this->preprocess($content, $options);

            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($content, "SimpleXMLElement", LIBXML_NOCDATA);

            $errors = libxml_get_errors();
            libxml_clear_errors();

            if ($xml === false || !empty($errors)) {
                $messages = array_map(
                    fn (LibXMLError $error) => trim($error->message),
                    $errors
                );
                throw new DecodingException(
                    "Failed to parse XML: " . implode("; ", $messages)
                );
            }

            $data = json_decode(json_encode($xml), true);

            return $this->postprocess($data, $options);
        }

        /**
         * Imports and parses XML data from a stream into an associative array.
         * @param Stream $stream The source stream to read from.
         * @param array $options Additional options for importing.
         * @throws DecodingException If the stream content cannot be parsed.
         * @return array|null The parsed content.
         */
        public function importStream (Stream $stream, array $options = []) : array|null {
            $content = $stream->readAll();
            $content = $this->preprocess($content, $options);

            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($content, "SimpleXMLElement", LIBXML_NOCDATA);

            $errors = libxml_get_errors();
            libxml_clear_errors();

            if ($xml === false || !empty($errors)) {
                $messages = array_map(
                    fn (LibXMLError $error) => trim($error->message),
                    $errors
                );
                throw new DecodingException(
                    "Failed to parse XML: " . implode("; ", $messages)
                );
            }

            $data = json_decode(json_encode($xml), true);

            return $this->postprocess($data, $options);
        }

        /**
         * Checks whether this importer supports a given file.
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
         * Determines whether this importer supports a given file extension.
         * @param string $extension The file extension.
         * @return bool Whether the importer supports the extension.
         */
        public function supportsExtension (string $extension) : bool {
            return strtolower($extension) === "xml";
        }

        /**
         * Determines whether this importer supports a given MIME type.
         * @param string $mime The MIME type.
         * @return bool Whether the importer supports the MIME type.
         */
        public function supportsMime (string $mime) : bool {
            return in_array(strtolower($mime), self::MIME_TYPES, true);
        }
    }
?>