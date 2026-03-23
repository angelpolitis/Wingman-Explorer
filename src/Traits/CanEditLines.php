<?php
    /**
     * Project Name:    Wingman Explorer - Can Edit Lines
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
     * Provides line-level mutation operations for `LocalFile`.
     *
     * Classes using this trait must:
     * - Expose `getContentStream(): Stream` (satisfied by any `FileResource`
     *   implementation).
     * - Expose `append(string $content): static` and `prepend(string $content): static`.
     * - Implement the protected `openTempStream(): array` and
     *   `rotateTempPath(string $newTempPath): void` infrastructure methods.
     *
     * All multi-line operations are streaming: they read the source line by line
     * and write to a new temporary file, which is then atomically promoted on the
     * next `save()` call.
     *
     * Line numbers are one-based. Replacement strings passed to write methods do
     * NOT include a trailing newline — the trait appends one automatically,
     * preserving the original line's newline style where applicable.
     *
     * @package Wingman\Explorer\Traits
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    trait CanEditLines {
        /**
         * Creates a temporary file inside the parent directory and returns its
         * path alongside a write stream opened on it.
         * @return array{string, \Wingman\Explorer\IO\Stream} A tuple of [temp path, write stream].
         */
        abstract protected function openTempStream () : array;

        /**
         * Atomically promotes a newly written temp file to the active pending
         * temp path, removing any previously staged temp file.
         * @param string $newTempPath The path of the newly written temp file.
         */
        abstract protected function rotateTempPath (string $newTempPath) : void;

        /**
         * Appends `$content` followed by a newline to the file.
         * @param string $content The line content, without a trailing newline.
         * @return static The file.
         */
        public function appendLine (string $content) : static {
            return $this->append($content . PHP_EOL);
        }

        /**
         * Deletes line N from the file.
         * @param int $line The one-based line number to delete.
         * @return static The file.
         */
        public function deleteLine (int $line) : static {
            return $this->deleteLines($line, $line);
        }

        /**
         * Deletes lines N through M (inclusive) from the file.
         * @param int $from The one-based line number of the first line to delete.
         * @param int $to The one-based line number of the last line to delete.
         * @return static The file.
         */
        public function deleteLines (int $from, int $to) : static {
            $source = $this->getContentStream();
            [$temp, $out] = $this->openTempStream();
            $lineNumber = 1;

            while (($raw = $source->readLine()) !== null) {
                if ($lineNumber < $from || $lineNumber > $to) {
                    $out->write($raw);
                }
                $lineNumber++;
            }

            $source->close();
            $out->close();
            $this->rotateTempPath($temp);

            return $this;
        }

        /**
         * Inserts a new line containing `$content` immediately before line N.
         * If `$before` exceeds the total line count, the new line is appended at
         * the end.
         * @param int $before The one-based line number before which to insert.
         * @param string $content The line content, without a trailing newline.
         * @return static The file.
         */
        public function insertLine (int $before, string $content) : static {
            $source = $this->getContentStream();
            [$temp, $out] = $this->openTempStream();
            $lineNumber = 1;

            while (($raw = $source->readLine()) !== null) {
                if ($lineNumber === $before) {
                    $out->write($content . PHP_EOL);
                }
                $out->write($raw);
                $lineNumber++;
            }

            if ($before >= $lineNumber) {
                $out->write($content . PHP_EOL);
            }

            $source->close();
            $out->close();
            $this->rotateTempPath($temp);

            return $this;
        }

        /**
         * Prepends `$content` followed by a newline to the file.
         * @param string $content The line content, without a trailing newline.
         * @return static The file.
         */
        public function prependLine (string $content) : static {
            return $this->prepend($content . PHP_EOL);
        }

        /**
         * Replaces line N with `$content`.
         * The trailing newline of the original line is preserved: if the original
         * line ended with `\n`, the replacement does too; the last line of a file
         * without a trailing newline will also have none after replacement.
         * @param int $line The one-based line number to replace.
         * @param string $content The replacement content, without a trailing newline.
         * @return static The file.
         */
        public function replaceLine (int $line, string $content) : static {
            $source = $this->getContentStream();
            [$temp, $out] = $this->openTempStream();
            $lineNumber = 1;

            while (($raw = $source->readLine()) !== null) {
                if ($lineNumber === $line) {
                    $out->write($content . (str_ends_with($raw, PHP_EOL) ? PHP_EOL : ""));
                } else {
                    $out->write($raw);
                }
                $lineNumber++;
            }

            $source->close();
            $out->close();
            $this->rotateTempPath($temp);

            return $this;
        }

        /**
         * Replaces all lines from N to M (inclusive) with the single string
         * `$content` followed by a newline.
         * @param int $from The one-based line number of the first line to replace.
         * @param int $to The one-based line number of the last line to replace.
         * @param string $content The replacement content, without a trailing newline.
         * @return static The file.
         */
        public function replaceLines (int $from, int $to, string $content) : static {
            $source = $this->getContentStream();
            [$temp, $out] = $this->openTempStream();
            $lineNumber = 1;
            $written = false;

            while (($raw = $source->readLine()) !== null) {
                if ($lineNumber === $from) {
                    $out->write($content . PHP_EOL);
                    $written = true;
                }
                elseif ($lineNumber < $from || $lineNumber > $to) {
                    $out->write($raw);
                }
                $lineNumber++;
            }

            if (!$written) {
                $out->write($content . PHP_EOL);
            }

            $source->close();
            $out->close();
            $this->rotateTempPath($temp);

            return $this;
        }
    }
?>