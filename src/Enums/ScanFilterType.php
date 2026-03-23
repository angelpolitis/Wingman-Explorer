<?php
    /**
     * Project Name:    Wingman Explorer - Scan Filter Type
     * Created by:      Angel Politis
     * Creation Date:   Dec 21 2025
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2025-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer.Enums namespace.
    namespace Wingman\Explorer\Enums;

    /**
     * Represents a scan filter type.
     * @package Wingman\Explorer\Enums
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    enum ScanFilterType {
        /**
         * The default scan filter type (no special filtering).
         */
        case NONE;

        /**
         * Filters by file extension.
         */
        case EXTENSION;
        
        /**
         * Filters by exact name.
         */
        case NAME;

        /**
         * Filters by regular expression.
         */
        case REGEX;
        
        /**
         * Filters by the owner of the file or directory.
         */
        case OWNER;
        
        /**
         * Filters by the group of the file or directory.
         */
        case GROUP;
        
        /**
         * Filters by size greater than a specified value.
         */
        case SIZE_GREATER;

        /**
         * Filters by size less than a specified value.
         */
        case SIZE_LESS;

        /**
         * Filters by last access date before a specified date.
         */
        case ACCESSED_BEFORE;

        /**
         * Filters by last access date after a specified date.
         */
        case ACCESSED_AFTER;

        /**
         * Filters by creation date before a specified date.
         */
        case CREATED_BEFORE;

        /**
         * Filters by creation date after a specified date.
         */
        case CREATED_AFTER;

        /**
         * Filters by modification date before a specified date.
         */
        case MODIFIED_BEFORE;

        /**
         * Filters by modification date after a specified date.
         */
        case MODIFIED_AFTER;

        /**
         * Filters by readability.
         */
        case IS_READABLE;

        /**
         * Filters by writability.
         */
        case IS_WRITABLE;

        /**
         * Filters by permissions.
         */
        case PERMISSIONS;

        /**
         * Filters by a glob pattern matched against the file or directory name.
         */
        case GLOB;

        /**
         * Gets the scope of a scan filter type.
         * @return ScanFilterScope The scope of the scan filter type.
         */
        public function getScope () : ScanFilterScope {
            return match ($this) {
                self::EXTENSION,
                self::SIZE_GREATER,
                self::SIZE_LESS => ScanFilterScope::FILES,
                default => ScanFilterScope::BOTH
            };
        }
    }
?>