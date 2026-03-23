<?php
    /**
     * Project Name:    Wingman Explorer - Ignore Pattern Builder
     * Created by:      Angel Politis
     * Creation Date:   Dec 18 2025
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2025-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer namespace.
    namespace Wingman\Explorer;

    # Import the following classes to the current scope.
    use Wingman\Explorer\Exceptions\FilesystemException;
    use Wingman\Locator\Asserter;

    /**
     * A class useful for building ignore patterns from 'ignore' files.
     * @package Wingman\Explorer
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class IgnorePatternBuilder {
        /**
         * The base name of an 'ignore' file.
         * @var string
         */
        public const IGNORE_FILE = ".ignore";

        /**
         * Converts a gitignore-style pattern to a regular expression fragment.
         *
         * Wildcards are translated as follows:
         * - `**` becomes `.*` (matches zero or more path components, including directory separators);
         * - `*` becomes `[^/\\]*` (matches anything except a path separator);
         * - `?` becomes `[^/\\]` (matches exactly one character except a path separator).
         *
         * All other characters are quoted for safe embedding in a regex.
         * @param string $pattern The gitignore-style pattern to convert.
         * @param string $delimiter The regex delimiter used for quoting.
         * @return string The regex fragment wrapped in a non-capturing group.
         */
        private function convertToRegex (string $pattern, string $delimiter) : string {
            $parts = preg_split('/([?]|[*]{2}|[*])/', $pattern, -1, PREG_SPLIT_DELIM_CAPTURE);

            $regex = implode("", array_map(function (string $part) use ($delimiter) : string {
                return match ($part) {
                    "**" => ".*",
                    "*" => "[^\\/\\\\]*",
                    "?" => "[^\\/\\\\]",
                    default => preg_quote($part, $delimiter),
                };
            }, $parts));

            return "(?:{$regex})";
        }

        /**
         * Assembles a regular expression composed of all rules in all 'ignore' files provided.
         * @param string[] $paths One or multiple paths to existing files.
         * @throws FilesystemException If an ignore file cannot be read.
         * @return string The regular expression.
         */
        public function build (array $paths) : string {
            $delimiter = '/';
            $emptyPattern = "{$delimiter}\$^{$delimiter}";

            if (count($paths) === 0) return $emptyPattern;

            $patterns = [];

            foreach ($paths as $path) {
                Asserter::requireFileAt($path);

                if (strtolower(basename($path)) !== static::IGNORE_FILE) {
                    continue;
                }

                $raw = file_get_contents($path);
                if ($raw === false) {
                    throw new FilesystemException("Failed to read ignore file at: $path");
                }
                $lines = preg_split("/\r?\n/", $raw);

                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '' || str_starts_with($line, '#')) continue; 
                    $patterns[$line] = $this->convertToRegex($line, $delimiter);
                }
            }
            
            return empty($patterns)
                ? $emptyPattern
                : $delimiter . '^(?:' . implode('|', $patterns) . ')$' . $delimiter;
        }
    }
?>