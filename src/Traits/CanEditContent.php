<?php
    /**
     * Project Name:    Wingman Explorer - Can Edit Content
     * Created by:      Angel Politis
     * Creation Date:   Mar 21 2026
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer.Traits namespace.
    namespace Wingman\Explorer\Traits;

    /**
     * Provides in-memory implementations of `append()` and `prepend()` for
     * file resources whose content is held as a string in `$this->content`.
     *
     * Classes using this trait must:
     * - Declare (or inherit) a `protected ?string $content` property.
     * - Implement `getContent(): string` so that the trait can resolve the
     *   current content even before a first mutation has occurred.
     *
     * @package Wingman\Explorer\Traits
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    trait CanEditContent {
        /**
         * Appends content to the end of the file's in-memory string.
         * @param string $content The content to append.
         * @return static The file.
         */
        public function append (string $content) : static {
            $this->content = $this->getContent() . $content;
            return $this;
        }

        /**
         * Prepends content to the beginning of the file's in-memory string.
         * @param string $content The content to prepend.
         * @return static The file.
         */
        public function prepend (string $content) : static {
            $this->content = $content . $this->getContent();
            return $this;
        }
    }
?>