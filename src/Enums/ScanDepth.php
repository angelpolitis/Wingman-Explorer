<?php
    /**
     * Project Name:    Wingman Explorer - Scan Option
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
     * Presents a standard scan depth.
     * @package Wingman\Explorer\Enums
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    enum ScanDepth {
        /**
         * Scan only the specified directory (no recursion).
         * @var int
         */
        case SHALLOW;

        /**
         * Scan the specified directory and its immediate subdirectories (1 level deep).
         * @var int
         */
        case DEFAULT;

        /**
         * Scan the specified directory and all its subdirectories (full recursion).
         * @var int
         */
        case DEEP;
    }
?>