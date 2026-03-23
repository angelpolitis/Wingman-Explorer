<?php
    /**
     * Project Name:    Wingman Explorer - Console Scan Command
     * Created by:      Angel Politis
     * Creation Date:   Mar 23 2026
     * Last Modified:   Mar 23 2026
     * 
     * Copyright (c) 2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer.Bridge.Console.Commands namespace.
    namespace Wingman\Explorer\Bridge\Console\Commands;

    # Import the following classes to the current scope.
    use InvalidArgumentException;
    use RuntimeException;
    use Throwable;
    use Wingman\Console\Attributes\Argument;
    use Wingman\Console\Attributes\Command as Cmd;
    use Wingman\Console\Attributes\Flag;
    use Wingman\Console\Attributes\Option;
    use Wingman\Console\Command;
    use Wingman\Console\Style;
    use Wingman\Explorer\Bridge\Console\Traits\ResolvesLocalExplorerResources;
    use Wingman\Explorer\Enums\ScanDepth;
    use Wingman\Explorer\Enums\ScanFilterType;
    use Wingman\Explorer\Enums\ScanOption;
    use Wingman\Explorer\Enums\ScanOrder;
    use Wingman\Explorer\Enums\ScanSortOption;
    use Wingman\Explorer\Enums\ScanTarget;
    use Wingman\Explorer\FileUtils;
    use Wingman\Explorer\Scanner;

    /**
     * Scans a directory using Explorer's fluent scanner and renders the results in table, path, or JSON form.
     *
     * The current implementation uses Explorer's LocalAdapter directly because the Console bridge
     * does not yet define a stable adapter-resolution contract for credentials, endpoints, buckets,
     * containers, or service-specific configuration. The command surface is intentionally modelled
     * around Explorer's Scanner API so that adapter selection can be generalised later without
     * redesigning the filtering and rendering flow.
     *
     * Supported capabilities include depth selection, target selection, extension/name/regex/size
     * filters, sorting, path-only mode, and best-effort scanning via `--skip-errors`.
     *
     * @package Wingman\Explorer\Bridge\Console\Commands
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    #[Cmd(name: "explorer:scan", description: "Scans a directory using Explorer's fluent scanner.")]
    class ScanCommand extends Command {
        use ResolvesLocalExplorerResources;

        /**
         * The filesystem adapter to use.
         * @var string
         */
        #[Option(name: "adapter", description: "The filesystem adapter to use; currently only local is supported")]
        protected string $adapter = "local";

        /**
         * The requested scan depth.
         * @var string
         */
        #[Option(name: "depth", description: "The scan depth: shallow, default, or deep")]
        protected string $depth = "default";

        /**
         * A comma-separated list of file extensions to include.
         * @var string|null
         */
        #[Option(name: "filter-extension", description: "A comma-separated list of file extensions to include")]
        protected ?string $filterExtension = null;

        /**
         * The maximum file size in bytes.
         * @var string|null
         */
        #[Option(name: "filter-max-size", description: "The maximum file size in bytes")]
        protected ?string $filterMaxSize = null;

        /**
         * The minimum file size in bytes.
         * @var string|null
         */
        #[Option(name: "filter-min-size", description: "The minimum file size in bytes")]
        protected ?string $filterMinSize = null;

        /**
         * An exact file or directory name to include.
         * @var string|null
         */
        #[Option(name: "filter-name", description: "An exact file or directory name to include")]
        protected ?string $filterName = null;

        /**
         * A PCRE pattern applied to item names.
         * @var string|null
         */
        #[Option(name: "filter-regex", description: "A PCRE pattern applied to file and directory names")]
        protected ?string $filterRegex = null;

        /**
         * The desired output format.
         * @var string
         */
        #[Option(name: "format", description: "The output format: table, paths, or json", alias: "F")]
        protected string $format = "table";

        /**
         * The sort order.
         * @var string
         */
        #[Option(name: "order", description: "The sort order: asc or desc")]
        protected string $order = "asc";

        /**
         * The root directory to scan.
         * @var string
         */
        #[Argument(index: 0, description: "The directory to scan")]
        protected string $path;

        /**
         * Whether to return only paths rather than full metadata arrays.
         * @var bool
         */
        #[Flag(name: "paths-only", description: "Return only paths rather than full metadata arrays")]
        protected bool $pathsOnly = false;

        /**
         * Whether to continue when scan errors occur.
         * @var bool
         */
        #[Flag(name: "skip-errors", description: "Continue when unreadable entries trigger scanner errors")]
        protected bool $skipErrors = false;

        /**
         * The sort field.
         * @var string|null
         */
        #[Option(name: "sort", description: "The sort field: name, size, modified, accessed, type, owner, group, or created")]
        protected ?string $sort = null;

        /**
         * The scan target.
         * @var string
         */
        #[Option(name: "target", description: "The scan target: any, file, dir, hidden, hidden-file, or hidden-dir")]
        protected string $target = "any";

        /**
         * Applies the configured filters to a scanner instance.
         * @param Scanner $scanner The scanner to configure.
         * @throws InvalidArgumentException If any filter option is malformed.
         * @return Scanner The configured scanner.
         */
        private function applyFilters (Scanner $scanner) : Scanner {
            if ($this->filterExtension !== null && trim($this->filterExtension) !== "") {
                $extensions = array_values(array_filter(array_map(
                    fn (string $extension) : string => strtolower(trim($extension)),
                    explode(",", $this->filterExtension)
                )));

                if (empty($extensions)) {
                    throw new InvalidArgumentException("The --filter-extension option must contain at least one extension.");
                }

                $scanner->filterBy(ScanFilterType::EXTENSION, $extensions);
            }

            if ($this->filterName !== null && $this->filterName !== "") {
                $scanner->filterBy(ScanFilterType::NAME, $this->filterName);
            }

            if ($this->filterRegex !== null && $this->filterRegex !== "") {
                if (@preg_match($this->filterRegex, "") === false) {
                    throw new InvalidArgumentException("The --filter-regex option must be a valid PCRE pattern.");
                }

                $scanner->filterBy(ScanFilterType::REGEX, $this->filterRegex);
            }

            if ($this->filterMinSize !== null) {
                $scanner->filterBy(ScanFilterType::SIZE_GREATER, $this->parseNonNegativeInteger($this->filterMinSize, "filter-min-size"));
            }

            if ($this->filterMaxSize !== null) {
                $scanner->filterBy(ScanFilterType::SIZE_LESS, $this->parseNonNegativeInteger($this->filterMaxSize, "filter-max-size"));
            }

            return $scanner;
        }

        /**
         * Applies the configured scanner options.
         * @param Scanner $scanner The scanner to configure.
         * @throws InvalidArgumentException If the configured output format is invalid.
         * @return Scanner The configured scanner.
         */
        private function applyOptions (Scanner $scanner) : Scanner {
            if ($this->skipErrors) {
                $scanner->addOption(ScanOption::SKIP_ERRORS);
            }

            if ($this->pathsOnly || $this->getEffectiveFormat() === "paths") {
                $scanner->addOption(ScanOption::PATHS_ONLY);
            }

            return $scanner;
        }

        /**
         * Applies sorting configuration to a scanner when a sort field has been provided.
         * @param Scanner $scanner The scanner to configure.
         * @throws InvalidArgumentException If the sort field or order is invalid.
         * @return Scanner The configured scanner.
         */
        private function applySorting (Scanner $scanner) : Scanner {
            if ($this->sort === null || trim($this->sort) === "") {
                return $scanner;
            }

            $scanner->sortBy($this->resolveSortOption($this->sort), $this->resolveSortOrder($this->order));

            return $scanner;
        }

        /**
         * Builds and configures the scanner for the current command invocation.
         * @throws InvalidArgumentException If any command option cannot be resolved to a valid scanner configuration.
         * @return Scanner The configured scanner.
         */
        private function buildScanner () : Scanner {
            $scanner = Scanner::withAdapter($this->resolveLocalAdapter($this->adapter))
                ->setDepth($this->resolveDepth($this->depth))
                ->setTarget($this->resolveTarget($this->target));

            $scanner = $this->applyFilters($scanner);
            $scanner = $this->applyOptions($scanner);

            return $this->applySorting($scanner);
        }

        /**
         * Formats a UNIX timestamp or arbitrary scalar value for human-readable output.
         * @param mixed $value The value to format.
         * @return string The formatted value.
         */
        private function formatMetadataValue (mixed $value) : string {
            if ($value === null) {
                return "-";
            }

            if (is_bool($value)) {
                return $value ? "true" : "false";
            }

            if (is_int($value) && $value > 0 && $value <= 4102444800) {
                return date("Y-m-d H:i:s", $value);
            }

            return (string) $value;
        }

        /**
         * Gets the effective output format after normalising case.
         * @throws InvalidArgumentException If the format is unsupported.
         * @return string The effective output format.
         */
        private function getEffectiveFormat () : string {
            $format = strtolower(trim($this->format));

            return match ($format) {
                "table", "paths", "json" => $format,
                default => throw new InvalidArgumentException("The --format option must be table, paths, or json."),
            };
        }

        /**
         * Parses a non-negative integer option value.
         * @param string $value The raw option value.
         * @param string $optionName The option name for error reporting.
         * @throws InvalidArgumentException If the provided value is not a non-negative integer.
         * @return int The parsed integer.
         */
        private function parseNonNegativeInteger (string $value, string $optionName) : int {
            if (!preg_match('/^\d+$/', $value)) {
                throw new InvalidArgumentException("The --{$optionName} option must be a non-negative integer.");
            }

            return (int) $value;
        }

        /**
         * Renders JSON output.
         * @param array $results The scan results.
         * @throws RuntimeException If the result set cannot be encoded as JSON.
         */
        private function renderJson (array $results) : void {
            $json = json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            if ($json === false) {
                throw new RuntimeException("Failed to encode scan results as JSON.");
            }

            echo $json . PHP_EOL;
        }

        /**
         * Renders one path per line.
         * @param array $results The scan results.
         */
        private function renderPaths (array $results) : void {
            foreach ($results as $item) {
                echo (is_array($item) ? ($item["path"] ?? "") : (string) $item) . PHP_EOL;
            }
        }

        /**
         * Renders results in tabular form.
         * @param array $results The scan results.
         */
        private function renderTable (array $results) : void {
            $headers = ["Path", "Type", "Size", "Modified"];
            $rows = [];

            foreach ($results as $item) {
                if (!is_array($item)) {
                    $rows[] = [(string) $item, "-", "-", "-"];
                    continue;
                }

                $rows[] = [
                    (string) ($item["path"] ?? ""),
                    (string) ($item["type"] ?? "-"),
                    isset($item["size"]) && is_int($item["size"]) ? FileUtils::getReadableSize($item["size"]) : "-",
                    $this->formatMetadataValue($item["modified"] ?? null)
                ];
            }

            $this->console->style(function (Style $style) use ($headers, $rows, $results) {
                if (empty($rows)) {
                    yield $style->format("No results found.", "warning") . PHP_EOL;
                    return;
                }

                yield $style->renderTable($headers, $rows);
                yield $style->format("Found " . count($results) . " result(s).", "info") . PHP_EOL;
            });
        }

        /**
         * Resolves a textual depth option to its enum counterpart.
         * @param string $depth The requested depth.
         * @throws InvalidArgumentException If the depth value is unsupported.
         * @return ScanDepth The resolved depth.
         */
        private function resolveDepth (string $depth) : ScanDepth {
            return match (strtolower(trim($depth))) {
                "shallow" => ScanDepth::SHALLOW,
                "default" => ScanDepth::DEFAULT,
                "deep" => ScanDepth::DEEP,
                default => throw new InvalidArgumentException("The --depth option must be shallow, default, or deep.")
            };
        }

        /**
         * Resolves a textual sort option to its enum counterpart.
         * @param string $sort The requested sort option.
         * @throws InvalidArgumentException If the sort value is unsupported.
         * @return ScanSortOption The resolved sort option.
         */
        private function resolveSortOption (string $sort) : ScanSortOption {
            return match (strtolower(trim($sort))) {
                "name" => ScanSortOption::NAME,
                "size" => ScanSortOption::SIZE,
                "modified" => ScanSortOption::LAST_MODIFIED,
                "accessed" => ScanSortOption::LAST_ACCESSED,
                "type", "extension" => ScanSortOption::TYPE,
                "owner" => ScanSortOption::OWNER,
                "group" => ScanSortOption::GROUP,
                "created" => ScanSortOption::CREATION_DATE,
                default => throw new InvalidArgumentException("The --sort option must be name, size, modified, accessed, type, owner, group, or created.")
            };
        }

        /**
         * Resolves a textual order option to its enum counterpart.
         * @param string $order The requested order.
         * @throws InvalidArgumentException If the order value is unsupported.
         * @return ScanOrder The resolved order.
         */
        private function resolveSortOrder (string $order) : ScanOrder {
            return match (strtolower(trim($order))) {
                "asc" => ScanOrder::ASCENDING,
                "desc" => ScanOrder::DESCENDING,
                default => throw new InvalidArgumentException("The --order option must be asc or desc.")
            };
        }

        /**
         * Resolves a textual target option to its enum counterpart.
         * @param string $target The requested target.
         * @throws InvalidArgumentException If the target value is unsupported.
         * @return ScanTarget The resolved target.
         */
        private function resolveTarget (string $target) : ScanTarget {
            return match (strtolower(trim($target))) {
                "any" => ScanTarget::ANY,
                "dir" => ScanTarget::DIR,
                "file" => ScanTarget::FILE,
                "hidden" => ScanTarget::HIDDEN,
                "hidden-dir" => ScanTarget::HIDDEN_DIR,
                "hidden-file" => ScanTarget::HIDDEN_FILE,
                default => throw new InvalidArgumentException("The --target option must be any, dir, file, hidden, hidden-dir, or hidden-file.")
            };
        }

        /**
         * Executes the scan command.
         * @return int The exit code of the command.
         */
        public function run () : int {
            try {
                $scanner = $this->buildScanner();
                $results = $scanner->scan($this->path);

                match ($this->getEffectiveFormat()) {
                    "json" => $this->renderJson($results),
                    "paths" => $this->renderPaths($results),
                    default => $this->renderTable($results)
                };

                return 0;
            }
            catch (InvalidArgumentException $e) {
                $this->console->error($e->getMessage());
                return 2;
            }
            catch (Throwable $e) {
                $this->console->error($e->getMessage());
                return 1;
            }
        }
    }
?>