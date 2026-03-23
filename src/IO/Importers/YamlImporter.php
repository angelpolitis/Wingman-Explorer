<?php
    /**
     * Project Name:    Wingman Explorer - Yaml Importer
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
    use Wingman\Explorer\Exceptions\DecodingException;
    use Wingman\Explorer\Exceptions\MissingDependencyException;
    use Wingman\Locator\Asserter;
    use Wingman\Explorer\Interfaces\IO\ExporterInterface;
    use Wingman\Explorer\Interfaces\IO\ImporterInterface;
    use Wingman\Explorer\Interfaces\IO\StreamableImporterInterface;
    use Wingman\Explorer\IO\Exporters\YamlExporter;
    use Wingman\Explorer\IO\IOManager;
    use Wingman\Explorer\IO\Stream;
    use Wingman\Explorer\Traits\CanProcess;

    /**
     * A class useful for importing YAML files.
     *
     * Requires either the <code>symfony/yaml</code> Composer package or the
     * <code>yaml</code> PECL extension. If neither is available a
     * {@see RuntimeException} is thrown at import time.
     *
     * @package Wingman\Explorer\IO\Importers
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class YamlImporter implements ImporterInterface, StreamableImporterInterface {
        use CanProcess;

        /**
         * The MIME types supported by this importer.
         * @var string[]
         */
        private const MIME_TYPES = ["application/x-yaml", "text/yaml", "text/x-yaml"];

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

            # YAML documents commonly start with a triple-dash document marker.
            if (preg_match('/^---/m', $sample)) {
                $score += 0.2;
            }

            # Key: value pattern is a strong indicator.
            if (preg_match('/^\w[\w\s]*:/m', $sample)) {
                $score += 0.1;
            }

            return min($score, 1.0);
        }

        /**
         * Gets the exporter counterpart for this importer.
         * @return ExporterInterface The twin exporter.
         */
        public function getExporter () : ExporterInterface {
            return IOManager::getTwinExporter(static::class) ?? new YamlExporter();
        }

        /**
         * Gets this importer.
         * @return ImporterInterface This importer.
         */
        public function getImporter () : ImporterInterface {
            return $this;
        }

        /**
         * Imports and parses a YAML file.
         * @param string $path The path to the file.
         * @param array $options Additional options for importing.
         * @throws DecodingException If parsing fails.
         * @throws MissingDependencyException If no YAML parser is available.
         * @return mixed The parsed content.
         */
        public function import (string $path, array $options = []) : mixed {
            Asserter::requireFileAt($path);

            $content = file_get_contents($path);
            $content = $this->preprocess($content, $options);

            if (class_exists('Symfony\Component\Yaml\Yaml')) {
                /**
                 * @disregard
                 * @psalm-suppress
                 * @noinspection
                 */
                $data = \Symfony\Component\Yaml\Yaml::parse($content);
            }
            elseif (extension_loaded("yaml")) {
                /**
                 * @disregard
                 * @psalm-suppress
                 * @noinspection
                 */
                $data = yaml_parse($content);

                if ($data === false) {
                    throw new DecodingException("Failed to parse YAML content in: {$path}");
                }
            }
            else {
                throw new MissingDependencyException(
                    "YAML support requires either the symfony/yaml package or the yaml PECL extension."
                );
            }

            return $this->postprocess($data, $options);
        }

        /**
         * Imports and parses YAML data from a stream.
         * @param Stream $stream The source stream to read from.
         * @param array $options Additional options for importing.
         * @throws DecodingException If parsing fails.
         * @throws MissingDependencyException If no YAML parser is available.
         * @return mixed The parsed content.
         */
        public function importStream (Stream $stream, array $options = []) : mixed {
            $content = $stream->readAll();
            $content = $this->preprocess($content, $options);

            if (class_exists('Symfony\Component\Yaml\Yaml')) {
                /**
                 * @disregard
                 * @psalm-suppress
                 * @noinspection
                 */
                $data = \Symfony\Component\Yaml\Yaml::parse($content);
            }
            elseif (extension_loaded("yaml")) {
                /**
                 * @disregard
                 * @psalm-suppress
                 * @noinspection
                 */
                $data = yaml_parse($content);

                if ($data === false) {
                    throw new DecodingException("Failed to parse YAML content from stream.");
                }
            }
            else {
                throw new MissingDependencyException("YAML support requires either the symfony/yaml package or the yaml PECL extension.");
            }

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
            return in_array(strtolower($extension), ["yaml", "yml"], true);
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