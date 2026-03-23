<?php
    /**
     * Project Name:    Wingman Explorer - Symlink Filesystem Adapter Interface
     * Created by:      Angel Politis
     * Creation Date:   Mar 19 2026
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer.Interfaces.Adapters namespace.
    namespace Wingman\Explorer\Interfaces\Adapters;

    # Import the following classes to the current scope.
    use Wingman\Explorer\Exceptions\FilesystemException;

    /**
     * Defines the contract for filesystem adapters that support symbolic links.
     *
     * Adapters implementing this interface can create new symbolic links, resolve the
     * target of an existing link, and query whether a given path is itself a symbolic link.
     * @package Wingman\Explorer\Interfaces\Adapters
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    interface SymlinkFilesystemAdapterInterface extends FilesystemAdapterInterface {
        /**
         * Creates a symbolic link pointing from the given link path to the target.
         * @param string $target The existing path the link should point to.
         * @param string $link The path at which the symbolic link will be created.
         * @throws FilesystemException If the link cannot be created.
         */
        public function symlink (string $target, string $link) : void;

        /**
         * Reads and returns the resolved target of a symbolic link.
         * @param string $path The path of the symbolic link.
         * @return string The resolved target path.
         * @throws FilesystemException If the path is not a symbolic link, or cannot be resolved.
         */
        public function readlink (string $path) : string;

        /**
         * Checks whether the given path is a symbolic link.
         * @param string $path The path to check.
         * @return bool Whether the path is a symbolic link.
         */
        public function isSymlink (string $path) : bool;
    }
?>