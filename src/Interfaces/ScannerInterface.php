<?php
    /**
     * Project Name:    Wingman Explorer - Scanner Interface
     * Created by:      Angel Politis
     * Creation Date:   Mar 19 2026
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer.Interfaces namespace.
    namespace Wingman\Explorer\Interfaces;

    # Import the following classes to the current scope.
    use Wingman\Explorer\Enums\ScanDepth;
    use Wingman\Explorer\Enums\ScanEvent;
    use Wingman\Explorer\Enums\ScanFilterType;
    use Wingman\Explorer\Enums\ScanOption;
    use Wingman\Explorer\Enums\ScanOrder;
    use Wingman\Explorer\Enums\ScanSortOption;
    use Wingman\Explorer\Enums\ScanTarget;
    use Wingman\Explorer\Interfaces\Adapters\DirectoryFilesystemAdapterInterface;

    /**
     * Defines the contract for a filesystem scanner.
     *
     * Implementations must support adapter injection, depth and target configuration,
     * event-driven observation, result filtering and sorting, and execution of the scan itself.
     * @package Wingman\Explorer\Interfaces
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    interface ScannerInterface {
        /**
         * Adds a scan option to the scanner without replacing existing options.
         * @param ScanOption $option The scan option to add.
         * @return static The scanner.
         */
        public function addOption (ScanOption $option) : static;

        /**
         * Removes all configured filters from the scanner.
         * @return static The scanner.
         */
        public function clearFilters () : static;

        /**
         * Adds a filter to the scanner.
         * @param ScanFilterType $type The type of filter to apply.
         * @param mixed $value The value to filter against.
         * @return static The scanner.
         */
        public function filterBy (ScanFilterType $type, mixed $value) : static;

        /**
         * Gets the current scan depth.
         * @return ScanDepth The scan depth.
         */
        public function getDepth () : ScanDepth;

        /**
         * Gets the configured filters.
         * @return array The configured filters.
         */
        public function getFilters () : array;

        /**
         * Gets the current scan options.
         * @return ScanOption[] The current scan options.
         */
        public function getOptions () : array;

        /**
         * Gets the current sort option.
         * @return ScanSortOption|null The sort option, or null if no sorting is configured.
         */
        public function getSortBy () : ?ScanSortOption;

        /**
         * Gets the current sort order.
         * @return ScanOrder The sort order.
         */
        public function getSortOrder () : ScanOrder;

        /**
         * Gets the current scan target.
         * @return ScanTarget The scan target.
         */
        public function getTarget () : ScanTarget;

        /**
         * Checks whether a specific scan option is currently enabled.
         * @param ScanOption $option The option to check.
         * @return bool Whether the option is enabled.
         */
        public function hasOption (ScanOption $option) : bool;

        /**
         * Removes a scan option from the scanner.
         * @param ScanOption $option The scan option to remove.
         * @return static The scanner.
         */
        public function removeOption (ScanOption $option) : static;

        /**
         * Executes the scan at the specified path and returns the results.
         * @param string $path The path to scan.
         * @return array The scan results.
         */
        public function scan (string $path) : array;

        /**
         * Sets the filesystem adapter used for all scan operations.
         * @param DirectoryFilesystemAdapterInterface $adapter The filesystem adapter.
         * @return static The scanner.
         */
        public function setAdapter (DirectoryFilesystemAdapterInterface $adapter) : static;

        /**
         * Sets the scan depth.
         * @param ScanDepth $depth The depth to scan to.
         * @return static The scanner.
         */
        public function setDepth (ScanDepth $depth) : static;

        /**
         * Registers a callback for a specific scan lifecycle event.
         * @param ScanEvent $event The event to listen to.
         * @param callable $callback The callback to invoke when the event fires.
         * @return static The scanner.
         */
        public function setEvent (ScanEvent $event, callable $callback) : static;

        /**
         * Replaces all current scan options with the given set.
         * @param ScanOption ...$options The options to enable.
         * @return static The scanner.
         */
        public function setOptions (ScanOption ...$options) : static;

        /**
         * Sets the resource type to target during scanning.
         * @param ScanTarget $target The target type.
         * @return static The scanner.
         */
        public function setTarget (ScanTarget $target) : static;

        /**
         * Configures the sort field and order applied to results after scanning.
         * @param ScanSortOption $sortBy The field to sort by.
         * @param ScanOrder $order The sort direction (default: ascending).
         * @return static The scanner.
         */
        public function sortBy (ScanSortOption $sortBy, ScanOrder $order = ScanOrder::ASCENDING) : static;

        /**
         * Creates a scanner pre-configured with the given filesystem adapter.
         * @param DirectoryFilesystemAdapterInterface $adapter The filesystem adapter.
         * @return static The new scanner.
         */
        public static function withAdapter (DirectoryFilesystemAdapterInterface $adapter) : static;
    }
?>