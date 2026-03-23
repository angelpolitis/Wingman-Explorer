<?php
    /**
     * Project Name:    Wingman Explorer - Xml Exporter
     * Created by:      Angel Politis
     * Creation Date:   Mar 20 2026
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer.IO.Exporters namespace.
    namespace Wingman\Explorer\IO\Exporters;

    # Import the following classes to the current scope.
    use Wingman\Explorer\Exceptions\EncodingException;
    use Wingman\Explorer\Exceptions\ExportException;
    use DOMDocument;
    use DOMElement;
    use Wingman\Explorer\Interfaces\IO\ExporterInterface;
    use Wingman\Explorer\Interfaces\IO\ImporterInterface;
    use Wingman\Explorer\Interfaces\IO\ReversibleIOInterface;
    use Wingman\Explorer\IO\Importers\XmlImporter;
    use Wingman\Explorer\IO\IOManager;
    use Wingman\Explorer\Traits\CanProcess;

    /**
     * A class useful for exporting data as XML files.
     *
     * Uses PHP's built-in {@see DOMDocument} — no external dependencies required.
     *
     * @package Wingman\Explorer\IO\Exporters
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class XmlExporter implements ExporterInterface, ReversibleIOInterface {
        use CanProcess;

        /**
         * The MIME types supported by this exporter.
         * @var string[]
         */
        private const MIME_TYPES = ["application/xml", "text/xml"];

        /**
         * Recursively appends array data as child elements to a DOM node.
         * @param array $data The data to append.
         * @param DOMElement $parent The parent DOM element.
         * @param DOMDocument $doc The owning document.
         * @param int $depth The current recursion depth.
         * @throws EncodingException If the maximum nesting depth is exceeded.
         */
        private function appendToElement (array $data, DOMElement $parent, DOMDocument $doc, int $depth = 0) : void {
            if ($depth > 512) {
                throw new EncodingException("XmlExporter: maximum nesting depth (512) exceeded.");
            }

            foreach ($data as $key => $value) {
                $tag = is_int($key) ? "item" : preg_replace('/[^a-zA-Z0-9_\-.]/', '_', (string) $key);

                if (is_array($value)) {
                    $child = $doc->createElement($tag);

                    if (is_int($key)) {
                        $child->setAttribute("index", (string) $key);
                    }

                    $parent->appendChild($child);
                    $this->appendToElement($value, $child, $doc, $depth + 1);
                }
                else {
                    $child = $doc->createElement($tag);
                    if ($value === null) {
                        $child->setAttribute("xsi:nil", "true");
                    }
                    else {
                        $child->appendChild($doc->createTextNode((string) $value));
                    }
                    $parent->appendChild($child);
                }
            }
        }

        /**
         * Exports data to an XML file at the given path.
         * @param mixed $data The data to export.
         * @param string $path The path to the file.
         * @param array $options Additional options for exporting.
         * @throws ExportException If the file cannot be written.
         * @return static The exporter instance.
         */
        public function export (mixed $data, string $path, array $options = []) : static {
            $xml = $this->prepare($data, $options);

            if (file_put_contents($path, $xml) === false) {
                throw new ExportException("Unable to write XML file '{$path}'.");
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
            if ($this->supportsExtension($extension ?? '')) {
                return 1.0;
            }

            if ($mime && $this->supportsMime($mime)) {
                return 1.0;
            }

            return is_array($data) || is_object($data) ? 0.3 : 0.1;
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
            return IOManager::getTwinImporter(static::class) ?? new XmlImporter();
        }

        /**
         * Serialises data to an XML string using a DOMDocument.
         * @param mixed $data The data to serialise.
         * @param array $options Additional options.
         * @throws ExportException If the data is not an array or object.
         * @throws EncodingException If the serialisation fails.
         * @return string The XML document as a string.
         */
        public function prepare (mixed $data, array $options = []) : string {
            $data = $this->preprocess($data, $options);

            if (!is_array($data) && !is_object($data)) {
                throw new ExportException("XmlExporter expects array or object data.");
            }

            $data = is_object($data) ? (array) $data : $data;
            $rootTag = $options["root"] ?? "root";
            $rootTag = preg_replace('/[^a-zA-Z0-9_\-.]/', '_', $rootTag);
            $version = $options["version"] ?? "1.0";
            $encoding = $options["encoding"] ?? "UTF-8";

            $doc = new DOMDocument($version, $encoding);
            $doc->formatOutput = true;

            $root = $doc->createElement($rootTag);
            $doc->appendChild($root);

            $this->appendToElement($data, $root, $doc);

            $xml = $doc->saveXML();

            if ($xml === false) {
                throw new EncodingException("Failed to serialise data as XML.");
            }

            return $this->postprocess($xml, $options);
        }

        /**
         * Determines whether this exporter supports a given dataset and hint.
         * @param mixed $data The data to evaluate.
         * @param string|null $extension The file extension (optional).
         * @param string|null $mime The MIME type of the file (optional).
         * @return bool Whether the exporter supports the file.
         */
        public function supports (mixed $data, ?string $extension = null, ?string $mime = null) : bool {
            if ($extension !== null && $this->supportsExtension($extension)) {
                return true;
            }

            if ($mime !== null && $this->supportsMime($mime)) {
                return true;
            }

            return false;
        }

        /**
         * Determines whether this exporter supports a given file extension.
         * @param string $extension The file extension.
         * @return bool Whether the exporter supports the extension.
         */
        public function supportsExtension (string $extension) : bool {
            return strtolower($extension) === "xml";
        }

        /**
         * Determines whether this exporter supports a given MIME type.
         * @param string $mime The MIME type.
         * @return bool Whether the exporter supports the MIME type.
         */
        public function supportsMime (string $mime) : bool {
            return in_array(strtolower($mime), self::MIME_TYPES, true);
        }
    }
?>