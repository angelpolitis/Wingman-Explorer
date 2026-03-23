<?php
    /**
     * Project Name:    Wingman Explorer - Tar Exporter
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
    use Wingman\Explorer\Exceptions\ExportException;
    use BadMethodCallException;
    use PharData;
    use RuntimeException;
    use UnexpectedValueException;
    use Wingman\Explorer\Interfaces\IO\ExporterInterface;
    use Wingman\Explorer\Interfaces\IO\ImporterInterface;
    use Wingman\Explorer\Interfaces\IO\ReversibleIOInterface;
    use Wingman\Explorer\IO\Importers\TarImporter;
    use Wingman\Explorer\IO\IOManager;
    use Wingman\Explorer\Traits\CanProcess;

    /**
     * A class useful for exporting data as TAR archives.
     *
     * Accepts an <code>array&lt;string, string&gt;</code> whose keys are the
     * internal entry paths and whose values are the binary file content.
     * Uses PHP's built-in <code>PharData</code> class; no external dependencies
     * are required.
     *
     * @package Wingman\Explorer\IO\Exporters
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class TarExporter implements ExporterInterface, ReversibleIOInterface {
        use CanProcess;

        /**
         * Exports a path-to-content map to a TAR archive file.
         * @param mixed $data An array mapping internal entry paths to binary content.
         * @param string $path The path of the TAR file to create.
         * @param array $options Additional options for exporting.
         * @throws ExportException If the archive cannot be created.
         * @return static The exporter instance.
         */
        public function export (mixed $data, string $path, array $options = []) : static {
            $data = $this->preprocess($data, $options);

            if (!is_array($data)) {
                throw new ExportException("TarExporter expects an array mapping entry paths to content.");
            }

            $this->buildArchive($data, $path);

            return $this;
        }

        /**
         * Gets the confidence level of this exporter for a given dataset and hint.
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

            return is_array($data) ? 0.3 : 0.0;
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
            return IOManager::getTwinImporter(static::class) ?? new TarImporter();
        }

        /**
         * Serialises a path-to-content map to a TAR archive binary string.
         *
         * Builds the archive in a temporary file and returns the raw bytes.
         *
         * @param mixed $data An array mapping internal entry paths to binary content.
         * @param array $options Additional options.
         * @throws ExportException If the archive cannot be built.
         * @return string The binary content of the TAR archive.
         */
        public function prepare (mixed $data, array $options = []) : string {
            $data = $this->preprocess($data, $options);

            if (!is_array($data)) {
                throw new ExportException("TarExporter expects an array mapping entry paths to content.");
            }

            $tempBase = tempnam(sys_get_temp_dir(), "wingman_tar_");
            @unlink($tempBase);
            $tempPath = $tempBase . ".tar";

            try {
                $this->buildArchive($data, $tempPath);

                $binary = file_get_contents($tempPath);

                if ($binary === false) {
                    throw new ExportException("Failed to read temporary TAR archive.");
                }
            }
            finally {
                @unlink($tempPath);
            }

            return $this->postprocess($binary, $options);
        }

        /**
         * Determines whether this exporter supports a given dataset and hint.
         * @param mixed $data The data to evaluate.
         * @param string|null $extension The file extension (optional).
         * @param string|null $mime The MIME type of the file (optional).
         * @return bool Whether the exporter supports the data.
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
            return in_array(strtolower($extension), ["tar", "tar.gz", "tgz", "tar.bz2", "tbz2"], true);
        }

        /**
         * Determines whether this exporter supports a given MIME type.
         * @param string $mime The MIME type.
         * @return bool Whether the exporter supports the MIME type.
         */
        public function supportsMime (string $mime) : bool {
            return in_array(strtolower($mime), [
                "application/x-tar",
                "application/x-gtar",
                "application/gzip",
                "application/x-bzip2",
            ], true);
        }

        /**
         * Builds a TAR archive at the given path from a path-to-content map.
         * @param array<string, string> $entries The entries to add to the archive.
         * @param string $path The destination path of the TAR archive.
         * @throws ExportException If the archive cannot be created.
         */
        private function buildArchive (array $entries, string $path) : void {
            try {
                $phar = new PharData($path);
            }
            catch (BadMethodCallException | RuntimeException | UnexpectedValueException $e) {
                throw new ExportException("Failed to create TAR archive '{$path}': " . $e->getMessage(), 0, $e);
            }

            foreach ($entries as $entryPath => $content) {
                $phar->addFromString((string) $entryPath, (string) $content);
            }
        }
    }
?>