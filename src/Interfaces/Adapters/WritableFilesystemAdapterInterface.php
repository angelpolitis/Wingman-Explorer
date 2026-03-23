<?php
    /**
     * Project Name:    Wingman Explorer - Writable Filesystem Adapter Interface
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
     * Defines the contract for writable file system operations.
     * @package Wingman\Explorer\Interfaces\Adapters
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    interface WritableFilesystemAdapterInterface extends FilesystemAdapterInterface {
        /**
         * Creates a new file at the specified path with optional initial content.
         * @param string $path The canonical path where the file will be created.
         * @param string $content The initial content of the file.
         * @throws FilesystemException If the file cannot be created.
         */
        public function create (string $path, string $content = "") : void;

        /**
         * Deletes the file or directory at the specified path.
         * @param string $path The canonical path to the file or directory.
         * @return bool True on success, false on failure.
         */
        public function delete (string $path) : bool;

        /**
         * Atomically writes the content to the specified path.
         * @param string $path The canonical path where the content will be saved.
         * @param string $content The data to write.
         * @throws FilesystemException If the write operation fails.
         */
        public function write (string $path, string $content) : void;
    }
?>