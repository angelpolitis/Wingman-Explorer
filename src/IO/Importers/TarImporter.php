<?php
    /**
     * Project Name:    Wingman Explorer - Tar Importer
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
    use BadMethodCallException;
    use PharData;
    use RuntimeException;
    use Wingman\Explorer\Exceptions\ImportException;
    use UnexpectedValueException;
    use Wingman\Locator\Asserter;
    use Wingman\Explorer\Interfaces\IO\ExporterInterface;
    use Wingman\Explorer\Interfaces\IO\ImporterInterface;
    use Wingman\Explorer\IO\Exporters\TarExporter;
    use Wingman\Explorer\IO\IOManager;
    use Wingman\Explorer\Traits\CanProcess;

    /**
     * A class useful for importing TAR archives.
     *
     * Supports uncompressed <code>.tar</code> as well as compressed
     * <code>.tar.gz</code> and <code>.tar.bz2</code> variants via PHP's
     * built-in <code>PharData</code> class (requires the <code>phar</code>
     * extension, which is enabled by default).
     *
     * The extracted data is returned as an array mapping each entry's internal
     * path to its binary content.
     *
     * @package Wingman\Explorer\IO\Importers
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class TarImporter implements ImporterInterface {
        use CanProcess;

        /**
         * The file extensions supported by this importer.
         * @var string[]
         */
        private const EXTENSIONS = ["tar", "tar.gz", "tgz", "tar.bz2", "tbz2"];

        /**
         * The MIME types supported by this importer.
         * @var string[]
         */
        private const MIME_TYPES = [
            "application/x-tar",
            "application/x-gtar",
            "application/gzip",
            "application/x-bzip2",
        ];

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

            # POSIX ustar magic at offset 257.
            if (strlen($sample) > 262 && substr($sample, 257, 5) === "ustar") {
                $score += 0.2;
            }

            return min($score, 1.0);
        }

        /**
         * Gets the exporter counterpart for this importer.
         * @return ExporterInterface The twin exporter.
         */
        public function getExporter () : ExporterInterface {
            return IOManager::getTwinExporter(static::class) ?? new TarExporter();
        }

        /**
         * Gets this importer.
         * @return ImporterInterface This importer.
         */
        public function getImporter () : ImporterInterface {
            return $this;
        }

        /**
         * Extracts a TAR archive and returns all file entries as a path-to-content map.
         * @param string $path The path to the TAR archive.
         * @param array $options Additional options for importing.
         * @throws ImportException If the archive cannot be opened or a file cannot be read.
         * @return array<string, string> A map of internal entry paths to their binary content.
         */
        public function import (string $path, array $options = []) : array {
            Asserter::requireFileAt($path);

            try {
                $phar = new PharData($path);
            }
            catch (BadMethodCallException | RuntimeException | UnexpectedValueException $e) {
                throw new ImportException("Failed to open TAR archive '{$path}': " . $e->getMessage(), 0, $e);
            }

            $entries = [];

            foreach (new \RecursiveIteratorIterator($phar) as $file) {
                /** @var \PharFileInfo $file */
                if ($file->isDir()) {
                    continue;
                }

                $internalPath = $file->getPathname();

                # Strip the phar:// wrapper to get a clean relative path.
                $realPath = realpath($path);
                $internalPath = preg_replace(
                    '/^phar:\/\/' . preg_quote($realPath !== false ? $realPath : $path, '/') . '\/?/',
                    '',
                    $internalPath
                );

                $content = file_get_contents($file->getPathname());

                if ($content === false) {
                    continue;
                }

                $entries[$internalPath] = $content;
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
            return in_array(strtolower($extension), self::EXTENSIONS, true);
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