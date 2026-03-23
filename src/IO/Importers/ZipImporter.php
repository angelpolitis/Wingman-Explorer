<?php
    /**
     * Project Name:    Wingman Explorer - Zip Importer
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
    use Wingman\Explorer\Exceptions\ImportException;
    use Wingman\Explorer\Exceptions\MissingDependencyException;
    use ZipArchive;
    use Wingman\Locator\Asserter;
    use Wingman\Explorer\Interfaces\IO\ExporterInterface;
    use Wingman\Explorer\Interfaces\IO\ImporterInterface;
    use Wingman\Explorer\IO\Exporters\ZipExporter;
    use Wingman\Explorer\IO\IOManager;
    use Wingman\Explorer\Traits\CanProcess;

    /**
     * A class useful for importing ZIP archives.
     *
     * Requires the <code>ext-zip</code> PHP extension.
     * The extracted data is returned as an array mapping each entry's internal
     * path to its binary content.
     *
     * @package Wingman\Explorer\IO\Importers
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class ZipImporter implements ImporterInterface {
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

            if ($this->supportsExtension($extension ?? '')) {
                $score += 0.5;
            }

            if ($this->supportsMime($mime ?? '')) {
                $score += 0.3;
            }

            # PK magic bytes at start of file are a reliable signal.
            if (str_starts_with($sample, "PK\x03\x04") || str_starts_with($sample, "PK\x05\x06")) {
                $score += 0.2;
            }

            return min($score, 1.0);
        }

        /**
         * Gets the exporter counterpart for this importer.
         * @return ExporterInterface The twin exporter.
         */
        public function getExporter () : ExporterInterface {
            return IOManager::getTwinExporter(static::class) ?? new ZipExporter();
        }

        /**
         * Gets this importer.
         * @return ImporterInterface This importer.
         */
        public function getImporter () : ImporterInterface {
            return $this;
        }

        /**
         * Extracts a ZIP archive and returns all file entries as a path-to-content map.
         * @param string $path The path to the ZIP file.
         * @param array $options Additional options for importing.
         * @throws MissingDependencyException If the ext-zip extension is missing.
         * @throws ImportException If the archive cannot be opened.
         * @return array<string, string> A map of internal entry paths to their binary content.
         */
        public function import (string $path, array $options = []) : array {
            Asserter::requireFileAt($path);

            if (!extension_loaded('zip')) {
                throw new MissingDependencyException(
                    "ZIP support requires the ext-zip PHP extension."
                );
            }

            $zip = new ZipArchive();
            $result = $zip->open($path);

            if ($result !== true) {
                throw new ImportException(
                    "Failed to open ZIP archive '{$path}' (error code: {$result})."
                );
            }

            $entries = [];

            try {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $name = $zip->getNameIndex($i);

                    # Skip directory entries.
                    if (str_ends_with($name, '/')) {
                        continue;
                    }

                    $content = $zip->getFromIndex($i);

                    if ($content === false) {
                        continue;
                    }

                    $entries[$name] = $content;
                }
            }
            finally {
                $zip->close();
            }

            $entries = $this->preprocess($entries, $options);

            return $this->postprocess($entries, $options);
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
            return strtolower($extension) === "zip";
        }

        /**
         * Determines whether this importer supports a given MIME type.
         * @param string $mime The MIME type.
         * @return bool Whether the importer supports the MIME type.
         */
        public function supportsMime (string $mime) : bool {
            return in_array(strtolower($mime), ["application/zip", "application/x-zip-compressed"], true);
        }
    }
?>