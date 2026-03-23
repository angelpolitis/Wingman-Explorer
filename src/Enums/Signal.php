<?php
    /**
     * Project Name:    Wingman Explorer - Signal
     * Created by:      Angel Politis
     * Creation Date:   Mar 21 2026
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer.Enums namespace.
    namespace Wingman\Explorer\Enums;

    /**
     * Represents a signal emitted by Explorer during its lifecycle operations.
     *
     * Each case maps to a dot-notation string identifier consumed by Corvus listeners.
     * Cases can be passed directly to `emit()` — coercion to their string value is automatic.
     *
     * @package Wingman\Explorer\Enums
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    enum Signal : string {
        /**
         * Emitted when a directory scan begins.
         * Payload: `root` (string) — the path being scanned.
         */
        case SCAN_STARTED = "explorer.scan.started";

        /**
         * Emitted after a directory scan finishes successfully.
         * Payload: `root` (string), `count` (int) — number of matched items returned.
         */
        case SCAN_COMPLETED = "explorer.scan.completed";

        /**
         * Emitted when an unhandled exception occurs during a scan.
         * Payload: `root` (string), `error` (string) — the exception message.
         */
        case SCAN_ERROR = "explorer.scan.error";

        /**
         * Emitted each time a file passes all target and filter checks during a scan.
         * Payload: `path` (string), `name` (string).
         */
        case FILE_FOUND = "explorer.scan.file.found";

        /**
         * Emitted each time a directory passes all target and filter checks during a scan.
         * Payload: `path` (string), `name` (string).
         */
        case DIRECTORY_FOUND = "explorer.scan.directory.found";

        /**
         * Emitted after all queued filesystem operations have been applied successfully.
         * Payload: `operations` (int) — number of operations committed.
         */
        case TRANSACTION_COMMITTED = "explorer.transaction.committed";

        /**
         * Emitted after a filesystem transaction has been rolled back.
         * Payload: `operations` (int) — number of compensating actions executed.
         */
        case TRANSACTION_ROLLED_BACK = "explorer.transaction.rolled_back";

        /**
         * Emitted when an individual rollback step throws during a transaction rollback.
         * Payload: `step` (int) — zero-based index of the failing step, `error` (string) — exception message.
         * The rollback continues despite the failure; all steps are attempted regardless.
         */
        case ROLLBACK_STEP_FAILED = "explorer.transaction.rollback.step.failed";

        /**
         * Emitted when a deleted file cannot be restored during rollback because
         * no previous content was captured (adapter was not readable at queue time).
         * Payload: `path` (string) — the path that could not be restored.
         */
        case ROLLBACK_RESTORE_IMPOSSIBLE = "explorer.transaction.rollback.restore.impossible";

        /**
         * Emitted after a file has been successfully imported.
         * Payload: `path` (string), `importer` (string) — fully-qualified class name of the chosen importer.
         */
        case IMPORT_COMPLETED = "explorer.import.completed";

        /**
         * Emitted when no registered importer matched and the fallback importer is used instead.
         * Payload: `path` (string).
         */
        case IMPORT_FALLBACK = "explorer.import.fallback";

        /**
         * Emitted after data has been successfully exported to a file.
         * Payload: `path` (string), `exporter` (string) — fully-qualified class name of the chosen exporter.
         */
        case EXPORT_COMPLETED = "explorer.export.completed";

        /**
         * Emitted when no registered exporter matched and the fallback exporter is used instead.
         * Payload: `path` (string).
         */
        case EXPORT_FALLBACK = "explorer.export.fallback";
    }
?>