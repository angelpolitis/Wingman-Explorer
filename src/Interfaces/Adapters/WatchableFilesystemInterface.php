<?php
    /**
     * Project Name:    Wingman Explorer - Watchable Filesystem Adapter Interface
     * Created by:      Angel Politis
     * Creation Date:   Dec 21 2025
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2025-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */
    
    # Use the Explorer.Interfaces.Adapters namespace.
    namespace Wingman\Explorer\Interfaces\Adapters;

    # Import the following classes to the current scope.
    use Wingman\Explorer\Exceptions\FilesystemException;

    /**
     * Defines the contract for all physical file system operations.
     * @package Wingman\Explorer\Interfaces\Adapters
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    interface WatchableFilesystemInterface extends FilesystemAdapterInterface {
        /**
         * Watches a file or directory for changes and triggers a callback on change.
         * @param string $path The canonical path to watch.
         * @param callable $callback The callback to execute on change.
         * @throws FilesystemException If watching fails.
         */
        public function watch (string $path, callable $callback) : void;
    }
?>