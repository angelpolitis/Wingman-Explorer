<?php
    /**
     * Project Name:    Wingman Explorer - Can Read Lines
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

    # Import the following classes to the current scope.
    use Wingman\Explorer\Exceptions\NonexistentLineException;

    /**
     * Provides line-level read operations for file resources.
     *
     * Classes using this trait must expose `getContentStream(): Stream`
     * (satisfied by any `FileResource` implementation). All operations are
     * streaming and never load the entire file into memory.
     *
     * Line numbers are one-based. Each line string includes its trailing `\n`
     * where present.
     *
     * @package Wingman\Explorer\Traits
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    trait CanReadLines {
        /**
         * Returns the content of line N, including its trailing newline.
         * @param int $line The one-based line number to retrieve.
         * @throws NonexistentLineException If the file has fewer than `$line` lines.
         * @return string The content of the requested line.
         */
        public function getLine (int $line) : string {
            $stream = $this->getContentStream();
            $current = 1;

            while (($raw = $stream->readLine()) !== null) {
                if ($current === $line) {
                    $stream->close();
                    return $raw;
                }
                $current++;
            }

            $stream->close();

            throw new NonexistentLineException("Line $line does not exist in '{$this->getPath()}'.");
        }

        /**
         * Counts all lines in the file without loading the content into memory.
         * @return int The total number of lines.
         */
        public function getLineCount () : int {
            $stream = $this->getContentStream();
            $count = 0;

            while ($stream->readLine() !== null) {
                $count++;
            }

            $stream->close();

            return $count;
        }

        /**
         * Returns all lines as an array, each string including its trailing newline
         * where present.
         * @return string[] The lines of the file.
         */
        public function getLines () : array {
            $stream = $this->getContentStream();
            $lines = [];

            while (($raw = $stream->readLine()) !== null) {
                $lines[] = $raw;
            }

            $stream->close();

            return $lines;
        }
    }
?>