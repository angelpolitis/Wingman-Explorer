<?php
    /**
     * Project Name:    Wingman Explorer - Permission
     * Created by:      Angel Politis
     * Creation Date:   Mar 19 2026
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer.Enums namespace.
    namespace Wingman\Explorer\Enums;

    /**
     * Represents an atomic filesystem permission bit.
     * @package Wingman\Explorer\Enums
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    enum Permission : int {
        /**
         * No permissions.
         * This bit is represented by the integer value 0, which corresponds to no permissions in Unix-style file modes. When this bit is set, it indicates that the file has no read, write, or execute permissions. In symbolic notation, this permission is represented by the character '-'.
         * @var int
         */
        case NONE = 0;

        /**
         * The execute permission bit.
         * This bit is represented by the integer value 1, which corresponds to the execute permission in Unix-style file modes. When this bit is set, it indicates that the file can be executed as a program or script. In symbolic notation, this permission is represented by the character 'x'.
         * @var int
         */
        case EXECUTE = 1;

        /**
         * The write permission bit.
         * This bit is represented by the integer value 2, which corresponds to the write permission in Unix-style file modes. When this bit is set, it indicates that the file can be written to. In symbolic notation, this permission is represented by the character 'w'.
         * @var int
         */
        case WRITE = 2;

        /**
         * The read permission bit.
         * This bit is represented by the integer value 4, which corresponds to the read permission in Unix-style file modes. When this bit is set, it indicates that the file can be read. In symbolic notation, this permission is represented by the character 'r'.
         * @var int
         */
        case READ = 4;

        /**
         * Resolves a permission from a string or returns the existing instance.
         * @param static|string $permission The permission to resolve.
         * @return static The resolved instance.
         */
        public static function resolve (self|string $permission) : static {
            return $permission instanceof static ? $permission : static::from($permission);
        }
    }
?>