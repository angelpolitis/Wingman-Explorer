<?php
    /**
     * Project Name:    Wingman Explorer - Console List Command
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
    use DateTimeInterface;
    use InvalidArgumentException;
    use Throwable;
    use Wingman\Console\Attributes\Argument;
    use Wingman\Console\Attributes\Command as Cmd;
    use Wingman\Console\Attributes\Flag;
    use Wingman\Console\Attributes\Option;
    use Wingman\Console\Command;
    use Wingman\Console\Style;
    use Wingman\Explorer\Bridge\Console\Traits\ResolvesLocalExplorerResources;
    use Wingman\Explorer\FileUtils;
    use Wingman\Explorer\Interfaces\Resources\DirectoryResource;
    use Wingman\Explorer\Interfaces\Resources\FileResource;
    use Wingman\Explorer\Resources\LocalDirectory;

    /**
     * Lists directory contents through Explorer resources and renders them in table or JSON form.
     *
     * The current implementation accepts an explicit `--adapter=local` contract, defaulting to
     * `local` when omitted, so the command surface remains stable before broader adapter
     * resolution is introduced for the Console bridge.
     *
     * Immediate children are listed by default. When `--flatten` is supplied the command expands
     * descendant files recursively via Explorer's `flatten()` API, which currently returns files
     * only and therefore intentionally omits nested directories.
     *
     * @package Wingman\Explorer\Bridge\Console\Commands
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    #[Cmd(name: "explorer:list", description: "Lists directory contents through Explorer resources.")]
    class ListCommand extends Command {
        use ResolvesLocalExplorerResources;

        /**
         * The filesystem adapter to use.
         * @var string
         */
        #[Option(name: "adapter", description: "The filesystem adapter to use; currently only local is supported")]
        protected string $adapter = "local";

        /**
         * Whether only directories should be returned.
         * @var bool
         */
        #[Flag(name: "dirs-only", description: "Show only directories")]
        protected bool $dirsOnly = false;

        /**
         * Whether only files should be returned.
         * @var bool
         */
        #[Flag(name: "files-only", description: "Show only files")]
        protected bool $filesOnly = false;

        /**
         * Whether descendant files should be flattened recursively.
         * @var bool
         */
        #[Flag(name: "flatten", description: "Recursively flatten descendant files")]
        protected bool $flatten = false;

        /**
         * The desired output format.
         * @var string
         */
        #[Option(name: "format", description: "The output format: table or json", alias: "F")]
        protected string $format = "table";

        /**
         * The directory to inspect.
         * @var string|null
         */
        #[Argument(index: 0, description: "The directory to inspect; defaults to the current working directory")]
        protected ?string $path = null;

        /**
         * Filters the resources according to the configured flags.
         * @param array<int, FileResource|DirectoryResource> $resources The resources to filter.
         * @throws InvalidArgumentException If the selected flags form an invalid combination.
         * @return array<int, FileResource|DirectoryResource> The filtered resources.
         */
        private function filterResources (array $resources) : array {
            if ($this->filesOnly && $this->dirsOnly) {
                throw new InvalidArgumentException("The --files-only and --dirs-only flags cannot be combined.");
            }

            if ($this->filesOnly) {
                return array_values(array_filter($resources, fn (FileResource|DirectoryResource $resource) : bool => $resource instanceof FileResource));
            }

            if ($this->dirsOnly) {
                return array_values(array_filter($resources, fn (FileResource|DirectoryResource $resource) : bool => $resource instanceof DirectoryResource));
            }

            return $resources;
        }

        /**
         * Formats metadata values for human-readable output.
         * @param mixed $value The value to format.
         * @return string The formatted value.
         */
        private function formatMetadataValue (mixed $value) : string {
            if ($value instanceof DateTimeInterface) {
                return $value->format("Y-m-d H:i:s");
            }

            if ($value === null) {
                return "-";
            }

            if (is_bool($value)) {
                return $value ? "true" : "false";
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
                "table", "json" => $format,
                default => throw new InvalidArgumentException("The --format option must be table or json."),
            };
        }

        /**
         * Gets the resources that should be listed for the current invocation.
         * @param LocalDirectory $directory The root directory.
         * @return array<int, FileResource|DirectoryResource> The resources to list.
         */
        private function getResources (LocalDirectory $directory) : array {
            if ($this->flatten) {
                return $directory->flatten();
            }

            return $directory->getContents();
        }

        /**
         * Normalises a resource into the JSON structure exposed by this command.
         * @param FileResource|DirectoryResource $resource The resource to normalise.
         * @return array{type: string, name: string, path: string, size: int, modified: string} The normalised resource payload.
         */
        private function normaliseResource (FileResource|DirectoryResource $resource) : array {
            $metadata = $resource->getMetadata();
            $isDirectory = $resource instanceof DirectoryResource;
            $modified = $isDirectory ? ($metadata["last_modified"] ?? null) : ($metadata["modified"] ?? null);

            return [
                "type" => $isDirectory ? "dir" : "file",
                "name" => $resource->getBaseName(),
                "path" => $resource->getPath(),
                "size" => (int) ($metadata["size"] ?? 0),
                "modified" => $modified instanceof DateTimeInterface ? $modified->format(DateTimeInterface::ATOM) : $this->formatMetadataValue($modified)
            ];
        }

        /**
         * Renders JSON output.
         * @param array<int, FileResource|DirectoryResource> $resources The resources to render.
         * @throws InvalidArgumentException If the results cannot be encoded as JSON.
         */
        private function renderJson (array $resources) : void {
            $payload = array_map(fn (FileResource|DirectoryResource $resource) : array => $this->normaliseResource($resource), $resources);
            $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            if ($json === false) {
                throw new InvalidArgumentException("Failed to encode list results as JSON.");
            }

            echo $json . PHP_EOL;
        }

        /**
         * Renders table output.
         * @param array<int, FileResource|DirectoryResource> $resources The resources to render.
         */
        private function renderTable (array $resources) : void {
            $rows = array_map(function (FileResource|DirectoryResource $resource) : array {
                $metadata = $resource->getMetadata();
                $isDirectory = $resource instanceof DirectoryResource;
                $modified = $isDirectory ? ($metadata["last_modified"] ?? null) : ($metadata["modified"] ?? null);

                return [
                    $isDirectory ? "dir" : "file",
                    $resource->getBaseName(),
                    $resource->getPath(),
                    FileUtils::getReadableSize((int) ($metadata["size"] ?? 0)),
                    $this->formatMetadataValue($modified)
                ];
            }, $resources);

            $this->console->style(fn (Style $style) => yield $style->renderTable(["Type", "Name", "Path", "Size", "Modified"], $rows));
        }

        /**
         * Resolves the root directory resource for the command.
         * @throws InvalidArgumentException If the configured adapter is unsupported.
         * @return LocalDirectory The resolved root directory.
         */
        private function resolveRootDirectory () : LocalDirectory {
            return $this->resolveExistingLocalDirectory($this->adapter, $this->path === null || trim($this->path) === "" ? getcwd() : $this->path);
        }

        /**
         * Executes the list command.
         * @return int The exit code of the command.
         */
        public function run () : int {
            try {
                $resources = $this->filterResources($this->getResources($this->resolveRootDirectory()));

                match ($this->getEffectiveFormat()) {
                    "json" => $this->renderJson($resources),
                    default => $this->renderTable($resources)
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