<?php
    /**
     * Project Name:    Wingman Explorer - Scan Filter Scope
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
     * Represents a scan filter scope.
     * @package Wingman\Explorer\Enums
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    enum ScanFilterScope {
        /**
         * The filter applies to files only.
         */
        case FILES;

        /**
         * The filter applies to directories only.
         */
        case DIRECTORIES;

        /**
         * The filter applies to both files and directories.
         */
        case BOTH;
    }
?>