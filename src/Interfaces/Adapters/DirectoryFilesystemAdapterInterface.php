<?php
    /**
     * Project Name:    Wingman Explorer - Directory Filesystem Adapter Interface
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
     * Defines the contract for directory-specific file system operations.
     * @package Wingman\Explorer\Interfaces\Adapters
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    interface DirectoryFilesystemAdapterInterface extends FilesystemAdapterInterface {
        /**
         * Creates a directory, optionally including parent directories.
         * @param string $path The directory path to create.
         * @param bool $recursive Create parents recursively.
         * @param int $permissions The permissions to set on the created directory.
         * @return bool Whether the operation was successful.
         * @throws FilesystemException If creation fails.
         */
        public function createDirectory (string $path, bool $recursive = false, int $permissions = 0775) : bool;

        /**
         * Scans a directory for files and/or subdirectories.
         * @param string $path The directory path.
         * @return iterable An iterable of file and/or directory paths.
         * @throws FilesystemException If the directory cannot be scanned.
         */
        public function list (string $path) : iterable;
    }
?>