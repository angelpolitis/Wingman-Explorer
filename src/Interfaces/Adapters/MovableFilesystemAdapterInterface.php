<?php
    /**
     * Project Name:    Wingman Explorer - Moveable Filesystem Adapter Interface
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
     * Defines the contract for movable file system operations.
     * @package Wingman\Explorer\Interfaces\Adapters
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    interface MovableFilesystemAdapterInterface extends FilesystemAdapterInterface {
        /**
         * Copies a file from one location to another.
         * @param string $source The source path.
         * @param string $destination The destination path.
         * @throws FilesystemException If the copy operation fails.
         */
        public function copy (string $source, string $destination) : void;

        /**
         * Moves (renames) a file or directory.
         * @param string $source The source path.
         * @param string $destination The destination path.
         * @throws FilesystemException If the move operation fails.
         */
        public function move (string $source, string $destination) : void;

        /**
         * Renames a file or directory.
         * @param string $currentBaseName The current base name.
         * @param string $newBaseName The new base name.
         * @throws FilesystemException If the rename operation fails.
         */
        public function rename (string $currentBaseName, string $newBaseName) : void;
    }
?>