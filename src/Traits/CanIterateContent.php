<?php
    /**
     * Project Name:    Wingman Explorer - Can Iterate Content
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
     * Provides streaming iteration operations over file content.
     *
     * Classes using this trait must expose `getContentStream(): Stream`
     * (satisfied by any `FileResource` implementation). All operations are
     * streaming and never load the entire file into memory.
     *
     * @package Wingman\Explorer\Traits
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    trait CanIterateContent {
        /**
         * Calls `$callback(string $char, int $offset)` for every character in
         * the file, streaming the file in chunks and iterating characters within
         * each chunk.
         * @param callable $callback The callback to invoke for each character.
         * @return static The file.
         */
        public function forEachChar (callable $callback) : static {
            $stream = $this->getContentStream();
            $offset = 0;

            foreach ($stream->readChunks() as $chunk) {
                $len = strlen($chunk);
                for ($i = 0; $i < $len; $i++) {
                    $callback($chunk[$i], $offset++);
                }
            }

            $stream->close();

            return $this;
        }

        /**
         * Calls `$callback(string $chunk, int $offset)` for every chunk of
         * `$size` bytes in the file.
         * @param callable $callback The callback to invoke for each chunk.
         * @param int $size The chunk size in bytes.
         * @return static The file.
         */
        public function forEachChunk (callable $callback, int $size) : static {
            $stream = $this->getContentStream();
            $offset = 0;

            while (($chunk = $stream->read($size)) !== null) {
                $callback($chunk, $offset);
                $offset += strlen($chunk);
            }

            $stream->close();

            return $this;
        }

        /**
         * Calls `$callback(string $line, int $lineNumber)` for every line in
         * the file. Each line string includes its trailing newline where present.
         * @param callable $callback The callback to invoke for each line. Returning
         * `false` stops iteration early.
         * @return static The file.
         */
        public function forEachLine (callable $callback) : static {
            $stream = $this->getContentStream();
            $lineNumber = 1;

            while (($line = $stream->readLine()) !== null) {
                if ($callback($line, $lineNumber++) === false) break;
            }

            $stream->close();

            return $this;
        }
    }
?>