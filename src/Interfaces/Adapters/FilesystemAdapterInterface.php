<?php
    /**
     * Project Name:    Wingman Explorer - Filesystem Adapter Interface
     * Created by:      Angel Politis
     * Creation Date:   Dec 13 2025
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2025-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */
    
    # Use the Explorer.Interfaces.Adapters namespace.
    namespace Wingman\Explorer\Interfaces\Adapters;

    # Import the following classes to the current scope.
    use Wingman\Explorer\Exceptions\FilesystemException;

    /**
     * Defines the contract for all physical file system operations.
     * @package Wingman\Explorer\Interfaces\Adapters
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    interface FilesystemAdapterInterface {
        /**
         * Checks if a file or directory exists at the canonical path.
         * @param string $path The canonical path.
         * @return bool Whether the path exists.
         */
        public function exists (string $path) : bool;

        /**
         * Retrieves metadata for a file or directory.
         * @param string $path The canonical path.
         * @param array|null $properties Specific metadata properties to retrieve, or `null` for all.
         * @return array An associative array of metadata.
         * @throws FilesystemException If the path does not exist.
         */
        public function getMetadata (string $path, ?array $properties = null) : array;
    }
?>