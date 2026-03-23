<?php
    /**
     * Project Name:    Wingman Explorer - Scan Event
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
     * Presents a scan event.
     * @package Wingman\Explorer\Enums
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    enum ScanEvent {
        /**
         * Event triggered when a scan starts.
         */
        case SCAN_STARTED;

        /**
         * Event triggered when a scan completes.
         */
        case SCAN_COMPLETED;

        /**
         * Event triggered when an error occurs during scanning.
         */
        case SCAN_ERROR;

        /**
         * Event triggered when a file or directory is found during scanning.
         */
        case FOUND;

        /**
         * Event triggered when a file is found during scanning.
         */
        case FILE_FOUND;

        /**
         * Event triggered when a directory is found during scanning.
         */
        case DIRECTORY_FOUND;

        /**
         * Event triggered when a file or directory is skipped during scanning.
         */
        case SKIPPED;

        /**
         * Event triggered when a file is skipped during scanning.
         */
        case FILE_SKIPPED;

        /**
         * Event triggered when a directory is skipped during scanning.
         */
        case DIRECTORY_SKIPPED;
    }
?>