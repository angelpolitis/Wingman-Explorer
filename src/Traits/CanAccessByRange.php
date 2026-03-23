<?php
    /**
     * Project Name:    Wingman Explorer - Can Access By Range
     * Created by:      Angel Politis
     * Creation Date:   Mar 22 2026
     * Last Modified:   Mar 23 2026
     * 
     * Copyright (c) 2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer.Traits namespace.
    namespace Wingman\Explorer\Traits;

    # Import the following classes to the current scope.
    use Wingman\Explorer\Enums\StreamMode;
    use Wingman\Explorer\IO\Stream;

    /**
     * Provides byte-range read and mutation operations for `LocalFile` instances
     * backed by a physical path.
     *
     * Classes using this trait must:
     * - Declare (or inherit) `protected bool $dirty`, `protected ?string $buffer`,
     *   `protected ?string $tempPath`, and `protected string $path`.
     * - Expose `getContentStream(): Stream` (satisfied by any `FileResource`
     *   implementation).
     * - Implement the protected `openTempStream(): array` and
     *   `rotateTempPath(string $newTempPath): void` infrastructure methods.
     *
     * All range indices are zero-based byte offsets. `$start` is inclusive,
     * `$end` is exclusive.
     *
     * @package Wingman\Explorer\Traits
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    trait CanAccessByRange {
        /**
         * Creates a temporary file inside the parent directory and returns its path
         * alongside a write stream opened on it.
         * @return array{string, Stream} A tuple of [ temp path, write stream ].
         */
        abstract protected function openTempStream () : array;

        /**
         * Atomically promotes a newly written temp file to the active pending temp path,
         * removing any previously staged temp file.
         * @param string $newTempPath The path of the newly written temp file.
         */
        abstract protected function rotateTempPath (string $newTempPath) : void;

        /**
         * Removes bytes `[$start, $end)` from the file by delegating to
         * `replaceRange()` with an empty replacement string.
         * @param int $start The start byte offset (inclusive).
         * @param int $end The end byte offset (exclusive).
         * @return static The file.
         */
        public function deleteRange (int $start, int $end) : static {
            return $this->replaceRange($start, $end, "");
        }

        /**
         * Reads and returns bytes `[$start, $end)` without loading the rest of the file into memory.
         * @param int $start The start byte offset (inclusive).
         * @param int $end The end byte offset (exclusive).
         * @return string The bytes in the requested range.
         */
        public function readRange (int $start, int $end) : string {
            $remaining = $end - $start;
            if ($remaining <= 0) return "";

            $stream = $this->getContentStream();
            if (!$stream->isOpen()) $stream->open();
            $stream->setReaderAt($start);
            $result = "";

            while ($remaining > 0) {
                $chunk = $stream->read(min(4096, $remaining));
                if ($chunk === null) break;
                $result .= $chunk;
                $remaining -= strlen($chunk);
            }

            $stream->close();

            return $result;
        }

        /**
         * Replaces the byte range `[$start, $end)` with `$replacement` using a stream,
         * avoiding full file reads.
         * If a string buffer is already pending the splice is performed on it directly.
         * Otherwise bytes `[0, $start)` are streamed to a temp file, followed by the
         * replacement, and then bytes `[$end, EOF)` from the source.
         * @param int $start The start byte offset (inclusive).
         * @param int $end The end byte offset (exclusive).
         * @param string $replacement The replacement content.
         * @return static The file.
         */
        public function replaceRange (int $start, int $end, string $replacement) : static {
            if ($this->dirty && $this->buffer !== null) {
                $this->buffer = substr($this->buffer, 0, $start) . $replacement . substr($this->buffer, $end);
                return $this;
            }

            $source = $this->dirty && $this->tempPath !== null ? $this->tempPath : $this->path;
            [$temp, $out] = $this->openTempStream();
            $in = Stream::from($source, StreamMode::READ_BINARY);
            $in->copyTo($out, $start);
            $out->write($replacement);
            $in->setReaderAt($end)->copyTo($out);
            $in->close();
            $out->close();
            $this->rotateTempPath($temp);

            return $this;
        }
    }
?>