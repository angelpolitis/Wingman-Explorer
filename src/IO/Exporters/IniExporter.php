<?php
    /**
     * Project Name:    Wingman Explorer - INI Exporter
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
    use Wingman\Explorer\IO\Importers\IniImporter;
    use Wingman\Explorer\IO\IOManager;
    use Wingman\Explorer\Traits\CanProcess;

    /**
     * An exporter for INI files.
     *
     * Serialises a PHP array to an INI-formatted string. Supports optional
     * section headers (associative sub-arrays become <code>[section]</code>
     * blocks), PHP-style array notation (<code>key[] = value</code>), key
     * flattening with a configurable separator, and a strict mode that rejects
     * structures that cannot be represented natively in INI.
     *
     * Supported options:
     * - <code>sections</code> (bool, default <code>true</code>) — emit <code>[section]</code> headers for associative sub-arrays.
     * - <code>arrays</code> (bool, default <code>false</code>) — use PHP array notation for sequential sub-arrays.
     * - <code>strict</code> (bool, default <code>false</code>) — throw on un-representable nested structures.
     * - <code>sectionsFirst</code> / <code>sections_first</code> (bool, default <code>true</code>) — output section blocks before root-level keys.
     * - <code>quoteStrings</code> / <code>quote_strings</code> (bool, default <code>true</code>) — wrap string values in double quotes.
     * - <code>flatten</code> (string, default <code>"."</code>) — separator for flattened nested keys.
     *
     * @package Wingman\Explorer\IO\Exporters
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class IniExporter implements ExporterInterface, ReversibleIOInterface {
        use CanProcess;

        /**
         * Emits PHP-style INI array definitions.
         *
         * Examples:
         *   formats[] = json
         *   formats[csv][enabled] = false
         *
         * @param array $lines The lines array to append to.
         * @param string $key The base key.
         * @param array $data The data to emit.
         * @param bool $quoteStrings Whether to quote string values [default: `false`].
         */
        protected function emitIniArray (array &$lines, string $key, array $data, bool $quoteStrings = false) : void {
            foreach ($data as $k => $value) {
                $compoundKey = is_int($k)
                    ? "{$key}[]"
                    : "{$key}[{$k}]";

                if (is_array($value)) {
                    $this->emitIniArray($lines, $compoundKey, $value, $quoteStrings);
                    continue;
                }

                $lines[] = $this->formatLine($compoundKey, $value, $quoteStrings);
            }
        }

        /**
         * Flattens a multi-dimensional array into INI lines.
         * @param array $lines The lines array to append to.
         * @param string $prefix The key prefix.
         * @param array $data The data to flatten.
         * @param string $separator The separator to use between keys.
         * @param bool $quoteStrings Whether to quote string values [default: `false`].
         */
        protected function flatten (array &$lines, string $prefix, array $data, string $separator, bool $quoteStrings = false) : void {
            foreach ($data as $key => $value) {
                $fullKey = "{$prefix}{$separator}{$key}";

                if (is_array($value)) {
                    $this->flatten($lines, $fullKey, $value, $separator, $quoteStrings);
                    continue;
                }

                $lines[] = $this->formatLine($fullKey, $value, $quoteStrings);
            }
        }

        /**
         * Formats an INI line.
         * @param string $key The key.
         * @param mixed $value The value.
         * @param bool $quoteStrings Whether to quote string values [default: `false`].
         * @return string The formatted line.
         */
        protected function formatLine (string $key, mixed $value, bool $quoteStrings = false) : string {
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
            elseif (is_numeric($value)) ;
            elseif ($quoteStrings) {
                $value = '"' . addslashes((string) $value) . '"';
            }
        
            return "{$key} = {$value}";
        }

        /**
         * Exports data to an INI file at the given path.
         * @param mixed $data The data to export.
         * @param string $path The path to the file.
         * @param array $options Additional options for exporting.
         * @return static The exporter instance.
         */
        public function export (mixed $data, string $path, array $options = []) : static {
            $content = $this->prepare($data, $options);
            file_put_contents($path, $content);
            return $this;
        }

        /**
         * Gets the confidence level of the exporter for a given dataset and hint.
         * @param mixed $data The data to evaluate.
         * @param string|null $extension The file extension (optional).
         * @param string|null $mime The MIME type of the file (optional).
         * @return float A confidence score between 0.0 and 1.0.
         */
        public function getConfidence (mixed $data, ?string $extension, ?string $mime) : float {
            return strtolower($extension ?? "") === "ini" ? 1.0 : 0.0;
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
            return IOManager::getTwinImporter(static::class) ?? new IniImporter();
        }

        /**
         * @throws ExportException If strict mode is enabled and nested arrays are found without sections or arrays enabled.
         */
        public function prepare (mixed $data, array $options = []) : string {
            $data = $this->preprocess($data, $options);
        
            $sections = $options["sections"] ?? true;
            $arrays = $options["arrays"] ?? false;
            $strict = $options["strict"] ?? false;
            $sectionsFirst = $options["sectionsFirst"] ?? $options["sections_first"] ?? true;
            $quoteStrings = $options["quoteStrings"] ?? $options["quote_strings"] ?? true;
            $separator = $options["flatten"] ?? '.';
        
            $scalarLines    = [];
            $rootArrayLines = [];
            $sectionLines   = [];
        
            foreach ($data as $key => $value) {
                if (!is_array($value)) {
                    $scalarLines[] = $this->formatLine($key, $value, $quoteStrings);
                    continue;
                }
        
                $isAssoc = array_keys($value) !== range(0, count($value) - 1);
        
                if ($sections && $isAssoc) {
                    $sectionLines[] = "[{$key}]";
        
                    foreach ($value as $k => $v) {
                        if (!is_array($v)) {
                            $sectionLines[] = $this->formatLine($k, $v, $quoteStrings);
                            continue;
                        }
        
                        if ($arrays) {
                            $this->emitIniArray($sectionLines, $k, $v, $quoteStrings);
                            continue;
                        }
        
                        if ($strict) {
                            throw new ExportException("Nested array '{$k}' inside section '{$key}' is not allowed in strict mode.");
                        }
        
                        $this->flatten($sectionLines, $k, $v, $separator, $quoteStrings);
                    }
        
                    $sectionLines[] = "";
                    continue;
                }
        
                if ($arrays) {
                    $this->emitIniArray($rootArrayLines, $key, $value, $quoteStrings);
                    continue;
                }

                if ($strict) {
                    throw new ExportException("Cannot export nested array '{$key}' without sections or arrays enabled.");
                }
        
                $this->flatten($rootArrayLines, $key, $value, $separator, $quoteStrings);
            }

            if ($sections) {
                if (!$sectionsFirst) {
                    if (!empty($scalarLines) && !empty($sectionLines)) {
                        array_unshift($sectionLines, "");
                    }
                    if (!empty($sectionLines)) {
                        array_pop($sectionLines);
                    }
                }
                else {
                    if (empty($scalarLines)) {
                        array_pop($sectionLines);
                    }
                }
            }

            $lines = $sectionsFirst
                ? array_merge($sectionLines, $rootArrayLines, $scalarLines)
                : array_merge($rootArrayLines, $scalarLines, $sectionLines);

            return $this->postprocess(implode(PHP_EOL, $lines), $options);
        }

        /**
         * Checks whether this exporter can handle the given data and hints.
         * @param mixed $data The data to evaluate.
         * @param string|null $extension The file extension (optional).
         * @param string|null $mime The MIME type of the file (optional).
         * @return bool Whether the exporter supports the data.
         */
        public function supports (mixed $data, ?string $extension = null, ?string $mime = null) : bool {
            if ($extension && !$this->supportsExtension($extension)) {
                return false;
            }

            if ($mime && !$this->supportsMime($mime)) {
                return false;
            }

            return true;
        }

        /**
         * Checks whether this exporter supports the given file extension.
         * @param string $extension The file extension.
         * @return bool Whether the extension is supported.
         */
        public function supportsExtension (string $extension) : bool {
            return strtolower($extension) === "ini";
        }

        /**
         * Checks whether this exporter supports the given MIME type.
         * @param string $mime The MIME type.
         * @return bool Whether the MIME type is supported.
         */
        public function supportsMime (string $mime) : bool {
            return strtolower($mime) === "text/plain";
        }
    }
?>