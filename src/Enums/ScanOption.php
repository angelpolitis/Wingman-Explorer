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
     * Represents a namespace notation.
     * @package Wingman\Explorer\Enums
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    enum ScanOption {
        /**
         * The default scan option (no special options).
         */
        case DEFAULT;

        /**
         * An option that includes only paths (no metadata).
         * @var int
         */
        case PATHS_ONLY;

        /**
         * An option that collapses directories, including only their files.
         * @var int
         */
        case COLLAPSE_DIRS;

        /**
         * An option that skips errors during scanning.
         * @var int
         */
        case SKIP_ERRORS;
    }
?>