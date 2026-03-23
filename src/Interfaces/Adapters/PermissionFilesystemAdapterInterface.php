<?php
    /**
     * Project Name:    Wingman Explorer - Permission Filesystem Adapter Interface
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
     * Defines the contract for filesystem adapters that support permission management.
     *
     * Adapters implementing this interface can change the mode (chmod),
     * owning user (chown), and owning group (chgrp) of filesystem entries.
     * @package Wingman\Explorer\Interfaces\Adapters
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    interface PermissionFilesystemAdapterInterface extends FilesystemAdapterInterface {
        /**
         * Sets the permission mode of the file or directory at the given path.
         * @param string $path The canonical path to the file or directory.
         * @param int $permissions The Unix permission mode as an integer (e.g. 0755).
         * @throws FilesystemException If the operation fails.
         */
        public function chmod (string $path, int $permissions) : void;

        /**
         * Changes the owning user of the file or directory at the given path.
         * @param string $path The canonical path to the file or directory.
         * @param int|string $owner The new owner, as a user name or numeric UID.
         * @throws FilesystemException If the operation fails.
         */
        public function chown (string $path, int|string $owner) : void;

        /**
         * Changes the owning group of the file or directory at the given path.
         * @param string $path The canonical path to the file or directory.
         * @param int|string $group The new group, as a group name or numeric GID.
         * @throws FilesystemException If the operation fails.
         */
        public function chgrp (string $path, int|string $group) : void;
    }
?>