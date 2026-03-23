<?php
    /**
     * Project Name:    Wingman Explorer - Can Edit Lines In Memory
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
     * Provides line-level mutation operations for virtual file resources whose
     * content is held entirely in memory (e.g. `GeneratedFile`, `InlineFile`).
     *
     * Classes using this trait must:
     * - Expose `getContentStream(): Stream` returning a readable stream over
     *   the current content (satisfied by `VirtualFile::getContentStream()`).
     * - Expose `write(string $content): static` to commit the mutated content
     *   back to the in-memory store (satisfied by `VirtualFile::write()`).
     * - Expose `append(string $content): static` and
     *   `prepend(string $content): static` (satisfied by `CanEditContent`).
     *
     * Unlike `CanEditLines`, this trait builds the edited content as a string
     * in memory before committing — appropriate for virtual resources where the
     * content is already fully available in RAM.
     *
     * Line numbers are one-based. Replacement strings passed to write methods
     * do NOT include a trailing newline; the trait appends one automatically,
     * preserving the original line's newline style where applicable.
     *
     * @package Wingman\Explorer\Traits
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    trait CanEditLinesInMemory {
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
            $result = '';
            $lineNumber = 1;

            while (($raw = $source->readLine()) !== null) {
                if ($lineNumber < $from || $lineNumber > $to) {
                    $result .= $raw;
                }
                $lineNumber++;
            }

            $source->close();
            $this->write($result);

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
            $result = '';
            $lineNumber = 1;
            $inserted = false;

            while (($raw = $source->readLine()) !== null) {
                if ($lineNumber === $before) {
                    $result .= $content . PHP_EOL;
                    $inserted = true;
                }
                $result .= $raw;
                $lineNumber++;
            }

            if (!$inserted) {
                $result .= $content . PHP_EOL;
            }

            $source->close();
            $this->write($result);

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
         * line ended with a newline, the replacement does too.
         * @param int $line The one-based line number to replace.
         * @param string $content The replacement content, without a trailing newline.
         * @return static The file.
         */
        public function replaceLine (int $line, string $content) : static {
            $source = $this->getContentStream();
            $result = '';
            $lineNumber = 1;

            while (($raw = $source->readLine()) !== null) {
                if ($lineNumber === $line) {
                    $result .= $content . (str_ends_with($raw, PHP_EOL) ? PHP_EOL : '');
                } else {
                    $result .= $raw;
                }
                $lineNumber++;
            }

            $source->close();
            $this->write($result);

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
            $result = '';
            $lineNumber = 1;
            $written = false;

            while (($raw = $source->readLine()) !== null) {
                if ($lineNumber === $from) {
                    $result .= $content . PHP_EOL;
                    $written = true;
                } elseif ($lineNumber < $from || $lineNumber > $to) {
                    $result .= $raw;
                }
                $lineNumber++;
            }

            if (!$written) {
                $result .= $content . PHP_EOL;
            }

            $source->close();
            $this->write($result);

            return $this;
        }
    }
?>