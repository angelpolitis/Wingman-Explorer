<?php
    /**
     * Project Name:    Wingman Explorer - Readable Filesystem Adapter Interface
     * Created by:      Angel Politis
     * Creation Date:   Dec 21 2025
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
     * Defines the contract for readable file system operations.
     * @package Wingman\Explorer\Interfaces\Adapters
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    interface ReadableFilesystemAdapterInterface extends FilesystemAdapterInterface {
        /**
         * Reads and returns the raw content of the file.
         * @param string $path The canonical path to the file.
         * @return string The raw file content.
         * @throws FilesystemException If the file cannot be read or does not exist.
         */
        public function read (string $path) : string;
    }
?>