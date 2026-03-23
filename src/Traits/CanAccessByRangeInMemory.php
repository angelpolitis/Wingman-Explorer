<?php
    /**
     * Project Name:    Wingman Explorer - Can Access By Range In Memory
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
     * Provides in-memory byte-range read and mutation operations for virtual
     * file resources whose content lives entirely in `$this->content`.
     *
     * Classes using this trait must:
     * - Declare (or inherit) a `protected ?string $content` property.
     * - Implement `getContent(): string` so that the trait resolves the
     *   current content even before a first mutation has occurred.
     *
     * All range indices are zero-based byte offsets. `$start` is inclusive,
     * `$end` is exclusive.
     *
     * @package Wingman\Explorer\Traits
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    trait CanAccessByRangeInMemory {
        /**
         * Removes bytes `[$start, $end)` from the file by delegating to
         * `replaceRange()` with an empty replacement string.
         * @param int $start The start byte offset (inclusive).
         * @param int $end The end byte offset (exclusive).
         * @return static The file.
         */
        public function deleteRange (int $start, int $end) : static {
            return $this->replaceRange($start, $end, '');
        }

        /**
         * Reads and returns bytes `[$start, $end)` from the in-memory content
         * without altering it.
         * @param int $start The start byte offset (inclusive).
         * @param int $end The end byte offset (exclusive).
         * @return string The bytes in the requested range.
         */
        public function readRange (int $start, int $end) : string {
            return substr($this->getContent(), $start, $end - $start);
        }

        /**
         * Replaces the byte range `[$start, $end)` with the given replacement.
         * @param int $start The start byte offset (inclusive).
         * @param int $end The end byte offset (exclusive).
         * @param string $replacement The replacement content.
         * @return static The file.
         */
        public function replaceRange (int $start, int $end, string $replacement) : static {
            $current = $this->getContent();
            $this->content = substr($current, 0, $start) . $replacement . substr($current, $end);
            return $this;
        }
    }
?>
