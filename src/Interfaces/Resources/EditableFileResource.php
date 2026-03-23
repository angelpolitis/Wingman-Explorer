<?php
    /**
     * Project Name:    Wingman Explorer - Editable File Resource
     * Created by:      Angel Politis
     * Creation Date:   Mar 21 2026
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer.Interfaces.Resources namespace.
    namespace Wingman\Explorer\Interfaces\Resources;

    /**
     * Represents a file resource whose content can be mutated in-place using
     * stream-efficient or in-memory operations.
     * @package Wingman\Explorer\Interfaces\Resources
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    interface EditableFileResource extends FileResource {
        /**
         * Appends content to the end of the file.
         * @param string $content The content to append.
         * @return static The file.
         */
        public function append (string $content) : static;

        /**
         * Prepends content to the beginning of the file.
         * @param string $content The content to prepend.
         * @return static The file.
         */
        public function prepend (string $content) : static;

        /**
         * Replaces the byte range `[$start, $end)` with the given replacement.
         * @param int $start The start byte offset (inclusive).
         * @param int $end The end byte offset (exclusive).
         * @param string $replacement The replacement content.
         * @return static The file.
         */
        public function replaceRange (int $start, int $end, string $replacement) : static;
    }
?>