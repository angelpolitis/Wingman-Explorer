<?php
    /**
     * Project Name:    Wingman Explorer - Yaml Exporter
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
    use Wingman\Explorer\Exceptions\MissingDependencyException;
    use Wingman\Explorer\Interfaces\IO\ExporterInterface;
    use Wingman\Explorer\Interfaces\IO\ImporterInterface;
    use Wingman\Explorer\Interfaces\IO\ReversibleIOInterface;
    use Wingman\Explorer\IO\Importers\YamlImporter;
    use Wingman\Explorer\IO\IOManager;
    use Wingman\Explorer\Traits\CanProcess;

    /**
     * A class useful for exporting data as YAML files.
     *
     * Requires either the <code>symfony/yaml</code> Composer package or the
     * <code>yaml</code> PECL extension. If neither is available a
     * {@see RuntimeException} is thrown at export time.
     *
     * @package Wingman\Explorer\IO\Exporters
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class YamlExporter implements ExporterInterface, ReversibleIOInterface {
        use CanProcess;

        /**
         * The MIME types supported by this exporter.
         * @var string[]
         */
        private const MIME_TYPES = ["application/x-yaml", "text/yaml", "text/x-yaml"];

        /**
         * Exports data to a YAML file at the given path.
         * @param mixed $data The data to export.
         * @param string $path The path to the file.
         * @param array $options Additional options for exporting.
         * @throws ExportException If the file cannot be written.
         * @return static The exporter instance.
         */
        public function export (mixed $data, string $path, array $options = []) : static {
            $yaml = $this->prepare($data, $options);

            if (file_put_contents($path, $yaml) === false) {
                throw new ExportException("Unable to write YAML file '{$path}'.");
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

            return is_array($data) || is_object($data) ? 0.4 : 0.1;
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
            return IOManager::getTwinImporter(static::class) ?? new YamlImporter();
        }

        /**
         * Serialises data to a YAML string.
         * @param mixed $data The data to serialise.
         * @param array $options Additional options.
         * @throws EncodingException If the YAML serialiser fails.
         * @throws MissingDependencyException If no YAML serialiser is available.
         * @return string The YAML representation of the data.
         */
        public function prepare (mixed $data, array $options = []) : string {
            $data = $this->preprocess($data, $options);

            if (class_exists('Symfony\Component\Yaml\Yaml')) {
                $inline = $options["inline"] ?? 10;
                $indent = $options["indent"] ?? 2;
                /**
                 * @disregard
                 * @psalm-suppress
                 * @noinspection
                 */
                $yaml = \Symfony\Component\Yaml\Yaml::dump($data, $inline, $indent);
            }
            elseif (extension_loaded("yaml")) {
                /**
                 * @disregard
                 * @psalm-suppress
                 * @noinspection
                 */
                $yaml = yaml_emit($data);

                if ($yaml === false) {
                    throw new EncodingException("Failed to serialise data as YAML.");
                }
            }
            else {
                throw new MissingDependencyException("YAML support requires either the symfony/yaml package or the yaml PECL extension.");
            }

            return $this->postprocess($yaml, $options);
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
            return in_array(strtolower($extension), ["yaml", "yml"], true);
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