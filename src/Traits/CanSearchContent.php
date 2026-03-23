<?php
    /**
     * Project Name:    Wingman Explorer - Can Search Content
     * Created by:      Angel Politis
     * Creation Date:   Mar 22 2026
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer.Traits namespace.
    namespace Wingman\Explorer\Traits;

    /**
     * Provides read-only string and pattern search operations for file resources.
     *
     * Classes using this trait must expose:
     * - `getContentStream(): Stream` — for chunked string and line searches.
     * - `getContent(): string` — for pattern searches that require the full
     *   string to compute correct byte offsets via `PREG_OFFSET_CAPTURE`.
     *
     * String search methods use a sliding-window approach to stream the file in
     * chunks without loading everything into memory. Pattern search methods load
     * the full content once, which is necessary for accurate byte offsets.
     * Line-targeting pattern searches resume streaming.
     *
     * @package Wingman\Explorer\Traits
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    trait CanSearchContent {
        /**
         * Whether the file contains the needle anywhere.
         * @param string $needle The string to search for.
         * @return bool Whether the needle was found.
         */
        public function contains (string $needle) : bool {
            return $this->findFirst($needle) !== null;
        }

        /**
         * Whether the file content matches the given pattern anywhere.
         * @param string $pattern The regex pattern to test.
         * @return bool Whether the pattern matched.
         */
        public function containsPattern (string $pattern) : bool {
            return (bool) preg_match($pattern, $this->getContent());
        }

        /**
         * Returns all byte offsets where the needle occurs.
         * @param string $needle The string to search for.
         * @return int[] The byte offsets of every occurrence.
         */
        public function find (string $needle) : array {
            if ($needle === "") return [];

            $stream = $this->getContentStream();
            $needleLen = strlen($needle);
            $carry = "";
            $fileOffset = 0;
            $results = [];

            foreach ($stream->readChunks() as $chunk) {
                $data = $carry . $chunk;
                $dataStart = $fileOffset - strlen($carry);
                $searchFrom = 0;

                while (($pos = strpos($data, $needle, $searchFrom)) !== false) {
                    $results[] = $pos + $dataStart;
                    $searchFrom = $pos + 1;
                }

                $fileOffset += strlen($chunk);
                $carry = $needleLen > 1 ? substr($chunk, -($needleLen - 1)) : "";
            }

            $stream->close();

            return $results;
        }

        /**
         * Returns all matches of `$pattern` with their byte offsets.
         * Each entry is a two-element array `[string $match, int $offset]`.
         * @param string $pattern The regex pattern to match.
         * @return array[] All matches with byte offsets.
         */
        public function findPattern (string $pattern) : array {
            preg_match_all($pattern, $this->getContent(), $matches, PREG_OFFSET_CAPTURE);
            return $matches[0];
        }

        /**
         * Returns the byte offset of the first occurrence of the needle, or
         * `null` if it is not found.
         * @param string $needle The string to search for.
         * @return int|null The byte offset, or `null`.
         */
        public function findFirst (string $needle) : ?int {
            if ($needle === "") return null;

            $stream = $this->getContentStream();
            $needleLen = strlen($needle);
            $carry = "";
            $fileOffset = 0;
            $found = null;

            foreach ($stream->readChunks() as $chunk) {
                $data = $carry . $chunk;
                $dataStart = $fileOffset - strlen($carry);
                $pos = strpos($data, $needle);

                if ($pos !== false) {
                    $found = $pos + $dataStart;
                    break;
                }

                $fileOffset += strlen($chunk);
                $carry = $needleLen > 1 ? substr($chunk, -($needleLen - 1)) : "";
            }

            $stream->close();

            return $found;
        }

        /**
         * Returns the first match of `$pattern` with its byte offset as a
         * two-element array `[string $match, int $offset]`, or `null` if there
         * is no match.
         * @param string $pattern The regex pattern to match.
         * @return array|null The first match with its byte offset, or `null`.
         */
        public function findFirstPattern (string $pattern) : ?array {
            $count = preg_match_all($pattern, $this->getContent(), $matches, PREG_OFFSET_CAPTURE);
            if (!$count) return null;
            $entry = $matches[0][0] ?? null;
            return is_array($entry) ? $entry : null;
        }

        /**
         * Returns the byte offset of the last occurrence of the needle, or
         * `null` if it is not found.
         * @param string $needle The string to search for.
         * @return int|null The byte offset, or `null`.
         */
        public function findLast (string $needle) : ?int {
            if ($needle === "") return null;

            $stream = $this->getContentStream();
            $needleLen = strlen($needle);
            $carry = "";
            $fileOffset = 0;
            $last = null;

            foreach ($stream->readChunks() as $chunk) {
                $data = $carry . $chunk;
                $dataStart = $fileOffset - strlen($carry);
                $searchFrom = 0;

                while (($pos = strpos($data, $needle, $searchFrom)) !== false) {
                    $last = $pos + $dataStart;
                    $searchFrom = $pos + 1;
                }

                $fileOffset += strlen($chunk);
                $carry = $needleLen > 1 ? substr($chunk, -($needleLen - 1)) : "";
            }

            $stream->close();

            return $last;
        }

        /**
         * Returns the one-based line number of the first line containing the
         * needle, or `null` if none match.
         * @param string $needle The string to search for within each line.
         * @return int|null The line number, or `null`.
         */
        public function findLine (string $needle) : ?int {
            if ($needle === "") return null;

            $stream = $this->getContentStream();
            $lineNumber = 1;

            while (($line = $stream->readLine()) !== null) {
                if (str_contains($line, $needle)) {
                    $stream->close();
                    return $lineNumber;
                }
                $lineNumber++;
            }

            $stream->close();

            return null;
        }

        /**
         * Returns the one-based line number of the first line matching
         * `$pattern`, or `null` if none match.
         * @param string $pattern The regex pattern to test against each line.
         * @return int|null The line number, or `null`.
         */
        public function findLineByPattern (string $pattern) : ?int {
            $stream = $this->getContentStream();
            $lineNumber = 1;

            while (($line = $stream->readLine()) !== null) {
                if (preg_match($pattern, $line)) {
                    $stream->close();
                    return $lineNumber;
                }
                $lineNumber++;
            }

            $stream->close();

            return null;
        }

        /**
         * Returns the one-based line numbers of all lines containing the needle.
         * @param string $needle The string to search for within each line.
         * @return int[] The line numbers of all matching lines.
         */
        public function findLines (string $needle) : array {
            if ($needle === "") return [];

            $stream = $this->getContentStream();
            $results = [];
            $lineNumber = 1;

            while (($line = $stream->readLine()) !== null) {
                if (str_contains($line, $needle)) {
                    $results[] = $lineNumber;
                }
                $lineNumber++;
            }

            $stream->close();

            return $results;
        }

        /**
         * Returns the one-based line numbers of all lines matching `$pattern`.
         * @param string $pattern The regex pattern to test against each line.
         * @return int[] The line numbers of all matching lines.
         */
        public function findLinesByPattern (string $pattern) : array {
            $stream = $this->getContentStream();
            $results = [];
            $lineNumber = 1;

            while (($line = $stream->readLine()) !== null) {
                if (preg_match($pattern, $line)) {
                    $results[] = $lineNumber;
                }
                $lineNumber++;
            }

            $stream->close();

            return $results;
        }
    }
?>