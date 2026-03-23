<?php
    /**
     * Project Name:    Wingman Explorer - Scan Target
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
     * Presents a scan target.
     * @package Wingman\Explorer\Enums
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    enum ScanTarget {
        /**
         * The scan target is a non-hidden file or directory.
         * @var int
         */
        case ANY;

        /**
         * The scan target is a non-hidden directory.
         * @var int
         */
        case DIR;

        /**
         * The scan target is a non-hidden file.
         * @var int
         */
        case FILE;

        /**
         * The scan target is a hidden file or directory.
         * @var int
         */
        case HIDDEN;

        /**
         * The scan target is a hidden directory.
         * @var int
         */
        case HIDDEN_DIR;

        /**
         * The scan target is a hidden file.
         * @var int
         */
        case HIDDEN_FILE;
    }
?>