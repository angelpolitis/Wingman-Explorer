<?php
    /**
     * Project Name:    Wingman Explorer - Scanner
     * Created by:      Angel Politis
     * Creation Date:   Dec 21 2025
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2025-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer namespace.
    namespace Wingman\Explorer;

    # Import the following classes to the current scope.
    use Throwable;
    use Wingman\Explorer\Bridge\Cortex\Attributes\Configurable;
    use Wingman\Explorer\Bridge\Cortex\Configuration;
    use Wingman\Explorer\Enums\ScanDepth;
    use Wingman\Explorer\Enums\ScanEvent;
    use Wingman\Explorer\Enums\ScanFilterScope;
    use Wingman\Explorer\Enums\ScanFilterType;
    use Wingman\Explorer\Enums\ScanOption;
    use Wingman\Explorer\Enums\ScanOrder;
    use Wingman\Explorer\Enums\ScanSortOption;
    use Wingman\Explorer\Enums\ScanTarget;
    use Wingman\Explorer\Exceptions\ScannerConfigurationException;
    use Wingman\Explorer\Interfaces\ScannerInterface;
    use Wingman\Locator\Facades\Path;
    use Wingman\Explorer\Interfaces\Adapters\DirectoryFilesystemAdapterInterface;
    use Wingman\Explorer\Interfaces\Adapters\ReadableFilesystemAdapterInterface;
    use Wingman\Explorer\Bridge\Corvus\Emitter;
    use Wingman\Explorer\Enums\Signal;

    /**
     * A filesystem scanner with a fluent builder API.
     *
     * Scans directories using an injected {@see FilesystemAdapterInterface} and supports
     * configurable depth, scan target, event callbacks, result filtering, and sorting.
     * Results are returned as full metadata arrays by default, or as plain path strings
     * when the {@see ScanOption::PATHS_ONLY} option is enabled.
     *
     * Typical usage:
     * <code>
     * $results = Scanner::withAdapter($adapter)
     *     ->setDepth(ScanDepth::DEEP)
     *     ->setTarget(ScanTarget::FILE)
     *     ->filterBy(ScanFilterType::EXTENSION, 'php')
     *     ->sortBy(ScanSortOption::NAME)
     *     ->scan('/var/www');
     * </code>
     *
     * @package Wingman\Explorer
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class Scanner implements ScannerInterface {
        /**
         * The adapter used for filesystem operations.
         * @var DirectoryFilesystemAdapterInterface
         */
        protected DirectoryFilesystemAdapterInterface $adapter;

        /**
         * The scan depth of a scanner.
         * @var ScanDepth
         */
        protected ScanDepth $depth = ScanDepth::DEFAULT;

        /**
         * The shared emitter bound to this scanner instance.
         * @var Emitter
         */
        protected Emitter $emitter;

        /**
         * The sort option of a scanner.
         * @var ?ScanSortOption
         */
        protected ?ScanSortOption $sortBy = null;

        /**
         * The sort order of a scanner.
         * @var ScanOrder
         */
        protected ScanOrder $sortOrder = ScanOrder::ASCENDING;

        /**
         * The scan target of a scanner.
         * @var ScanTarget
         */
        protected ScanTarget $target = ScanTarget::ANY;

        /**
         * The event listeners of a scanner.
         * @var array<ScanEvent, callable[]>
         */
        protected array $events = [];

        /**
         * The filters of a scanner.
         * @var array<array{type: ScanFilterType, value: mixed}> The configured filters.
         */
        protected array $filters = [];
        
        /**
         * The scan options of a scanner.
         * @var ScanOption[]
         */
        protected array $options = [];

        /**
         * Whether to return paths instead of full metadata arrays.
         * When `true`, the {@see ScanOption::PATHS_ONLY} option is enabled automatically at construction.
         * Settable via configuration key `explorer.scanner.pathsOnly`.
         * @var bool
         */
        #[Configurable("explorer.scanner.pathsOnly", "Whether to return item paths instead of full metadata arrays.")]
        protected bool $pathsOnly = false;

        /**
         * Whether to silently skip errors encountered during scanning rather than propagating them.
         * When `true`, the {@see ScanOption::SKIP_ERRORS} option is enabled automatically at construction.
         * Settable via configuration key `explorer.scanner.skipErrors`.
         * @var bool
         */
        #[Configurable("explorer.scanner.skipErrors", "Whether to skip errors encountered during scanning instead of propagating them.")]
        protected bool $skipErrors = false;

        /**
         * Creates a new scanner, applies any settings from `$config`, and binds a shared emitter.
         *
         * Boolean properties annotated with `#[Configurable]` are hydrated automatically and
         * translated to the corresponding {@see ScanOption} flags before the first scan.
         * @param array|Configuration $config A flat dot-notation configuration map or a Cortex
         * `Configuration` instance. Supported keys: `explorer.scanner.skipErrors`,
         * `explorer.scanner.pathsOnly`.
         */
        public function __construct (array|Configuration $config = []) {
            Configuration::hydrate($this, $config);
            $this->emitter = Emitter::for($this);
            if ($this->skipErrors) $this->addOption(ScanOption::SKIP_ERRORS);
            if ($this->pathsOnly) $this->addOption(ScanOption::PATHS_ONLY);
        }

        /**
         * Evaluates a single filter against a file or directory's metadata.
         * @param ScanFilterType $type The filter type.
         * @param mixed $value The filter value.
         * @param array $info The file or directory metadata.
         * @return bool Whether the filter is passed.
         */
        protected function evaluateFilter (ScanFilterType $type, mixed $value, array $info): bool {
            switch ($type) {
                case ScanFilterType::NONE:
                    break;
                case ScanFilterType::EXTENSION:
                    $extension = strtolower(pathinfo($info["name"], PATHINFO_EXTENSION));
                    if (!in_array($extension, (array) $value)) return false;
                    break;
                case ScanFilterType::NAME:
                    if ($info["name"] !== $value) return false;
                    break;
                case ScanFilterType::REGEX:
                    if (!preg_match($value, $info["name"])) return false;
                    break;
                case ScanFilterType::OWNER:
                    if (($info["owner"] ?? null) !== $value) return false;
                    break;
                case ScanFilterType::GROUP:
                    if (($info["group"] ?? null) !== $value) return false;
                    break;
                case ScanFilterType::SIZE_GREATER:
                    if (($info["size"] ?? 0) <= $value) return false;
                    break;
                case ScanFilterType::SIZE_LESS:
                    if (($info["size"] ?? 0) >= $value) return false;
                    break;
                case ScanFilterType::ACCESSED_BEFORE:
                    if (($info["accessed"] ?? 0) >= $value) return false;
                    break;
                case ScanFilterType::ACCESSED_AFTER:
                    if (($info["accessed"] ?? 0) <= $value) return false;
                    break;
                case ScanFilterType::CREATED_BEFORE:
                    if (($info["created"] ?? 0) >= $value) return false;
                    break;
                case ScanFilterType::CREATED_AFTER:
                    if (($info["created"] ?? 0) <= $value) return false;
                    break;
                case ScanFilterType::MODIFIED_BEFORE:
                    if (($info["modified"] ?? 0) >= $value) return false;
                    break;
                case ScanFilterType::MODIFIED_AFTER:
                    if (($info["modified"] ?? 0) <= $value) return false;
                    break;
                case ScanFilterType::IS_READABLE:
                    if ($value && (is_file($info["path"]) || is_dir($info["path"])) && !is_readable($info["path"])) return false;
                    break;
                case ScanFilterType::IS_WRITABLE:
                    if ($value && (is_file($info["path"]) || is_dir($info["path"])) && !is_writable($info["path"])) return false;
                    break;
                case ScanFilterType::PERMISSIONS:
                    if (($info["permissions"] ?? null) !== $value) return false;
                    break;
                case ScanFilterType::GLOB:
                    if (!fnmatch($value, $info["name"])) return false;
                    break;
            }
            return true;
        }

        /**
         * Determines if a file or directory matches the scan target.
         * @param array $info The file or directory metadata.
         * @return bool Whether the item matches the scan target.
         */
        protected function matchesTarget (array $info) : bool {
            $name = $info["name"];
            $type = $info["type"];
            return match($this->target) {
                ScanTarget::ANY => true,
                ScanTarget::DIR => $type === "dir" && !str_starts_with($name, '.'),
                ScanTarget::FILE => $type === "file" && !str_starts_with($name, '.'),
                ScanTarget::HIDDEN => str_starts_with($name, '.'),
                ScanTarget::HIDDEN_DIR => $type === "dir" && str_starts_with($name, '.'),
                ScanTarget::HIDDEN_FILE => $type === "file" && str_starts_with($name, '.'),
            };
        }
        
        /**
         * Determines if a file or directory passes all configured filters.
         * @param array $info The file or directory metadata.
         * @return bool Whether all filters are passed.
         */
        protected function passesFilters (array $info): bool {
            foreach ($this->filters as ["type" => $type, "value" => $value]) {
                # Skip filters that do not apply to this target.
                if (
                    $type->getScope() === ScanFilterScope::FILES && $info["type"] !== "file" ||
                    $type->getScope() === ScanFilterScope::DIRECTORIES && $info["type"] !== "dir"
                ) {
                    continue;
                }

                if (!$this->evaluateFilter($type, $value, $info)) {
                    return false;
                }
            }

            return true;
        }
        
        /**
         * Recursively scans a directory and retrieves its contents.
         * @param string $path The path to scan.
         * @param ScanDepth|int $depth The scan depth.
         * @return array The scan results as full metadata arrays.
         */
        protected function recursiveScan (string $path, ScanDepth|int $depth) : array {
            $results = [];

            # Normalize the depth to an integer.
            $depthValue = $depth instanceof ScanDepth ? match($depth) {
                ScanDepth::SHALLOW => 0,
                ScanDepth::DEFAULT => 1,
                ScanDepth::DEEP => -1
            } : $depth;
    
            foreach ($this->adapter->list($path) as $itemPath) {
                try {
                    $info = $this->adapter->getMetadata($itemPath);
                    $isDir = $info["type"] === "dir";

                    /* -----------------------------
                     * Recurse FIRST (structural)
                     * ----------------------------- */
                    if ($isDir && ($depthValue === -1 || $depthValue > 0)) {
                        $nextDepth = $depthValue === -1 ? -1 : $depthValue - 1;
            
                        $children = $this->recursiveScan($info["path"], $nextDepth);
            
                        if ($this->hasOption(ScanOption::COLLAPSE_DIRS)) {
                            $results = array_merge($results, $children);
                            continue;
                        }

                        $info["contents"] = $children;
                    }
            
                    /* -----------------------------
                     * Target matching
                     * ----------------------------- */
                    if (!$this->matchesTarget($info)) {
                        $this->triggerEvent(
                            $isDir ? ScanEvent::DIRECTORY_SKIPPED : ScanEvent::FILE_SKIPPED,
                            $info
                        );
                        continue;
                    }
            
                    /* -----------------------------
                     * Filter matching (scope-aware)
                     * ----------------------------- */
                    if (!$this->passesFilters($info)) {
                        $this->triggerEvent(
                            $isDir ? ScanEvent::DIRECTORY_SKIPPED : ScanEvent::FILE_SKIPPED,
                            $info
                        );
                        continue;
                    }
            
                    /* -----------------------------
                     * Discovery events
                     * ----------------------------- */
                    $this->triggerEvent(ScanEvent::FOUND, $info);
            
                    $this->triggerEvent(
                        $isDir ? ScanEvent::DIRECTORY_FOUND : ScanEvent::FILE_FOUND,
                        $info
                    );

                    $this->emitter
                        ->with(path: $info["path"], name: $info["name"])
                        ->emit($isDir ? Signal::DIRECTORY_FOUND : Signal::FILE_FOUND);
            
                    $results[] = $info;
                }
                catch (Throwable $e) {
                    if (!$this->hasOption(ScanOption::SKIP_ERRORS)) {
                        throw $e;
                    }
                    $this->triggerEvent(ScanEvent::SCAN_ERROR, $e);
                }
            }
        
            return $results;
        }

        /**
         * Sorts the scan results based on the specified sort option and order.
         * Returns the contents unchanged when no sort option is configured.
         * @param array $contents The scan results to sort.
         * @return array The sorted scan results.
         */
        protected function sortResults (array $contents) : array {
            if ($this->sortBy === null || $this->sortBy === ScanSortOption::NONE) {
                return $contents;
            }

            $key = match($this->sortBy) {
                ScanSortOption::CREATION_DATE => "created",
                ScanSortOption::GROUP => "group",
                ScanSortOption::LAST_ACCESSED => "accessed",
                ScanSortOption::LAST_MODIFIED => "modified",
                ScanSortOption::NAME => "name",
                ScanSortOption::NONE => "name",
                ScanSortOption::OWNER => "owner",
                ScanSortOption::SIZE => "size",
                ScanSortOption::TYPE => "type",
            };

            usort($contents, fn($a, $b) => 
                ($this->sortOrder === ScanOrder::ASCENDING ? 1 : -1) * 
                (($a[$key] ?? '') <=> ($b[$key] ?? ''))
            );

            return $contents;
        }
        
        /**
         * Triggers an event and calls all associated callbacks.
         * @param ScanEvent $event The scan event to trigger.
         * @param mixed $payload The payload to pass to the event callbacks.
         * @return static The scanner.
         */
        protected function triggerEvent (ScanEvent $event, mixed $payload) : static {
            if (!empty($this->events[$event->name])) {
                foreach ($this->events[$event->name] as $callback) {
                    $callback($payload);
                }
            }
            return $this;
        }

        /**
         * Adds a scan option to the scanner without replacing existing options.
         * @param ScanOption $option The scan option to add.
         * @return static The scanner.
         */
        public function addOption (ScanOption $option) : static {
            if (!$this->hasOption($option)) {
                $this->options[] = $option;
            }
            return $this;
        }

        /**
         * Removes all configured filters from the scanner.
         * @return static The scanner.
         */
        public function clearFilters () : static {
            $this->filters = [];
            return $this;
        }

        /**
         * Creates a new scanner, optionally pre-configured from a Cortex configuration source
         * or a flat dot-notation array.
         * @param array|Configuration $config A flat dot-notation configuration map or a Cortex
         * `Configuration` instance. Supported keys: `explorer.scanner.skipErrors`,
         * `explorer.scanner.pathsOnly`.
         * @return static The new scanner.
         */
        public static function create (array|Configuration $config = []) : static {
            return new static($config);
        }

        /**
         * Adds a filter to a scanner.
         * @param ScanFilterType $type The filter type.
         * @param mixed $value The filter value.
         * @return static The scanner.
         */
        public function filterBy (ScanFilterType $type, mixed $value) : static {
            $this->filters[] = ["type" => $type, "value" => $value];
            return $this;
        }

        /**
         * Gets the current scan depth.
         * @return ScanDepth The scan depth.
         */
        public function getDepth () : ScanDepth {
            return $this->depth;
        }

        /**
         * Gets the configured filters.
         * @return array The configured filters.
         */
        public function getFilters () : array {
            return $this->filters;
        }

        /**
         * Gets the current scan options.
         * @return ScanOption[] The current scan options.
         */
        public function getOptions () : array {
            return $this->options;
        }

        /**
         * Gets the current sort option.
         * @return ScanSortOption|null The sort option, or null if no sorting is configured.
         */
        public function getSortBy () : ?ScanSortOption {
            return $this->sortBy;
        }

        /**
         * Gets the current sort order.
         * @return ScanOrder The sort order.
         */
        public function getSortOrder () : ScanOrder {
            return $this->sortOrder;
        }

        /**
         * Gets the current scan target.
         * @return ScanTarget The scan target.
         */
        public function getTarget () : ScanTarget {
            return $this->target;
        }

        /**
         * Checks if a scanner has a specific option enabled.
         * @param ScanOption $option The scan option.
         * @return bool Whether the option is enabled.
         */
        public function hasOption (ScanOption $option) : bool {
            return in_array($option, $this->options);
        }

        /**
         * Removes a scan option from the scanner.
         * @param ScanOption $option The scan option to remove.
         * @return static The scanner.
         */
        public function removeOption (ScanOption $option) : static {
            $this->options = array_values(array_filter(
                $this->options,
                fn(ScanOption $o) => $o !== $option
            ));
            return $this;
        }

        /**
         * Scans a specified path.
         * @param string $path The path to scan.
         * @return array The scan results.
         * @throws ScannerConfigurationException If the scanner is misconfigured.
         */
        public function scan (string $path) : array {
            if (!isset($this->adapter)) {
                throw new ScannerConfigurationException("No filesystem adapter has been configured.");
            }

            if (!empty($this->filters) &&
                !$this->adapter instanceof ReadableFilesystemAdapterInterface) {
                throw new ScannerConfigurationException("Filters require readable filesystem.");
            }

            $path = Path::for($path);

            $this->triggerEvent(ScanEvent::SCAN_STARTED, $path);

            $this->emitter
                ->with(root: $path)
                ->emit(Signal::SCAN_STARTED);

            try {
                $results = $this->recursiveScan($path, $this->depth);
                $results = $this->sortResults($results);

                if ($this->hasOption(ScanOption::PATHS_ONLY)) {
                    $results = array_map(fn(array $item) => $item["path"], $results);
                }
            }
            catch (Throwable $e) {
                $this->triggerEvent(ScanEvent::SCAN_ERROR, $e);

                $this->emitter
                    ->with(root: $path, error: $e->getMessage())
                    ->emit(Signal::SCAN_ERROR);

                if (!$this->hasOption(ScanOption::SKIP_ERRORS)) {
                    throw $e;
                }

                return [];
            }

            $this->triggerEvent(ScanEvent::SCAN_COMPLETED, $results);

            $this->emitter
                ->with(root: $path, count: count($results))
                ->emit(Signal::SCAN_COMPLETED);

            return $results;
        }

        /**
         * Sets the filesystem adapter a scanner.
         * @param DirectoryFilesystemAdapterInterface $adapter The filesystem adapter.
         * @return static The scanner.
         */
        public function setAdapter (DirectoryFilesystemAdapterInterface $adapter) : static {
            $this->adapter = $adapter;
            return $this;
        }

        /**
         * Sets the scan depth of a scanner.
         * @param ScanDepth $depth The scan depth.
         * @return static The scanner.
         */
        public function setDepth (ScanDepth $depth) : static {
            $this->depth = $depth;
            return $this;
        }
        
        /**
         * Sets an event listener for a scan event.
         * @param ScanEvent $event The scan event.
         * @param callable $callback The event callback.
         * @return static The scanner.
         */
        public function setEvent (ScanEvent $event, callable $callback) : static {
            $this->events[$event->name][] = $callback;
            return $this;
        }

        /**
         * Sets the scan options of a scanner.
         * @param ScanOption ...$options The scan options.
         * @return static The scanner.
         */
        public function setOptions (ScanOption ...$options) : static {
            $this->options = $options;
            return $this;
        }

        /**
         * Sets the scan target of a scanner.
         * @param ScanTarget $target The scan target.
         * @return static The scanner.
         */
        public function setTarget (ScanTarget $target) : static {
            $this->target = $target;
            return $this;
        }

        /**
         * Sets the sort option and order of a scanner.
         * @param ScanSortOption $sortBy The sort option.
         * @param ScanOrder $order The sort order (default: ascending).
         * @return static The scanner.
         */
        public function sortBy (ScanSortOption $sortBy, ScanOrder $order = ScanOrder::ASCENDING) : static {
            $this->sortBy = $sortBy;
            $this->sortOrder = $order;
            return $this;
        }

        /**
         * Creates a new scanner with the specified filesystem adapter, optionally pre-configured
         * from a Cortex configuration source or a flat dot-notation array.
         * @param DirectoryFilesystemAdapterInterface $adapter The filesystem adapter.
         * @param array|Configuration $config A flat dot-notation configuration map or a Cortex
         * `Configuration` instance. Supported keys: `explorer.scanner.skipErrors`,
         * `explorer.scanner.pathsOnly`.
         * @return static The new scanner.
         */
        public static function withAdapter (DirectoryFilesystemAdapterInterface $adapter, array|Configuration $config = []) : static {
            return (new static($config))->setAdapter($adapter);
        }
    }
?>