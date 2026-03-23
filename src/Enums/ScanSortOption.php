<?php
    /**
     * Project Name:    Wingman Explorer - Scan Sort Option
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
     * Represents a scan sort option.
     * @package Wingman\Explorer\Enums
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    enum ScanSortOption {
        /**
         * No sorting.
         */
        case NONE;

        /**
         * Sort by name.
         */
        case NAME;

        /**
         * Sort by size.
         */
        case SIZE;

        /**
         * Sort by last modified date.
         */
        case LAST_MODIFIED;

        /**
         * Sort by access date.
         */
        case LAST_ACCESSED;

        /**
         * Sort by type (file extension).
         */
        case TYPE;

        /**
         * Sort by owner.
         */
        case OWNER;

        /**
         * Sort by group.
         */
        case GROUP;

        /**
         * Sort by creation date.
         */
        case CREATION_DATE;
    }
?>