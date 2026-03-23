<?php
    /**
     * Project Name:    Wingman Explorer - Writable Resource
     * Created by:      Angel Politis
     * Creation Date:   Dec 14 2025
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2025-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer.Interfaces.Resources namespace.
    namespace Wingman\Explorer\Interfaces\Resources;

    # Import the following classes to the current scope.
    use Wingman\Explorer\IO\Stream;

    /**
     * Represents a resource that can be created/written into.
     * @package Wingman\Explorer\Resources
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    interface WritableResource extends CreatableResource {
        /**
         * Discards buffered changes.
         * @return WritableResource The resource.
         */
        public function discard () : WritableResource;

        /**
         * Persists buffered changes atomically, replacing the original resource on success.
         * @return WritableResource The resource.
         */
        public function save () : WritableResource;

        /**
         * Writes data to an internal buffer (not persisted).
         * @return WritableResource The resource.
         */
        public function write (string $content) : WritableResource;

        /**
         * Writes data from a stream to an internal buffer.
         * @return WritableResource The resource.
         */
        public function writeStream (Stream $stream) : WritableResource;

    }
?>