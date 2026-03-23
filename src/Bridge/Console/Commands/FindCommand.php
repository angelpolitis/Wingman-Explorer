<?php
    /**
     * Project Name:    Wingman Explorer - Console Find Command
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
    use Throwable;
    use Wingman\Console\Attributes\Argument;
    use Wingman\Console\Attributes\Command as Cmd;
    use Wingman\Console\Attributes\Flag;
    use Wingman\Console\Attributes\Option;
    use Wingman\Console\Command;
    use Wingman\Explorer\Bridge\Console\Traits\ResolvesLocalExplorerResources;
    use Wingman\Explorer\Interfaces\Resources\DirectoryResource;
    use Wingman\Explorer\Interfaces\Resources\FileResource;
    use Wingman\Explorer\Resources\LocalDirectory;

    /**
     * Searches a directory for resources matching a glob pattern and emits path or JSON output.
     *
     * The current implementation accepts an explicit `--adapter=local` contract, defaulting to
     * `local` when omitted, so the command surface is stable before broader adapter resolution is
     * introduced for the Console bridge.
     *
     * Recursive search is enabled by default to keep the command useful for common developer
     * workflows. The `--recursive` flag is still accepted so callers can state intent explicitly,
     * even though it is currently idempotent.
     *
     * @package Wingman\Explorer\Bridge\Console\Commands
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    #[Cmd(name: "explorer:find", description: "Finds filesystem resources matching a glob pattern.")]
    class FindCommand extends Command {
        use ResolvesLocalExplorerResources;

        /**
         * The filesystem adapter to use.
         * @var string
         */
        #[Option(name: "adapter", description: "The filesystem adapter to use; currently only local is supported")]
        protected string $adapter = "local";

        /**
         * Whether directories should be included in the result set alongside files.
         * @var bool
         */
        #[Flag(name: "dirs", description: "Include matching directories in the result set")]
        protected bool $dirs = false;

        /**
         * The desired output format.
         * @var string
         */
        #[Option(name: "format", description: "The output format: paths or json", alias: "F")]
        protected string $format = "paths";

        /**
         * The root directory to search.
         * @var string
         */
        #[Argument(index: 0, description: "The root directory to search")]
        protected string $path;

        /**
         * The glob pattern to match against resource base names.
         * @var string
         */
        #[Argument(index: 1, description: "The glob pattern to match against resource base names")]
        protected string $pattern;

        /**
         * Whether the search should recurse into nested directories.
         * @var bool
         */
        #[Flag(name: "recursive", description: "Search recursively; this is enabled by default")]
        protected bool $recursive = true;

        /**
         * Creates a new command and restores the default recursive behaviour when the flag is omitted.
         * @param array|string $command A command-line expression or an array of command-line arguments.
         */
        public function __construct (array|string $command) {
            parent::__construct($command);

            if (!$this->hasFlag("recursive")) {
                $this->recursive = true;
            }
        }

        /**
         * Filters the raw search results based on the configured flags.
         * @param array<int, FileResource|DirectoryResource> $results The raw search results.
         * @return array<int, FileResource|DirectoryResource> The filtered results.
         */
        private function filterResults (array $results) : array {
            if ($this->dirs) {
                return $results;
            }

            return array_values(array_filter($results, fn (FileResource|DirectoryResource $resource) : bool => !$resource instanceof DirectoryResource));
        }

        /**
         * Gets the effective output format after normalising case.
         * @throws InvalidArgumentException If the format is unsupported.
         * @return string The effective output format.
         */
        private function getEffectiveFormat () : string {
            $format = strtolower(trim($this->format));

            return match ($format) {
                "paths", "json" => $format,
                default => throw new InvalidArgumentException("The --format option must be paths or json."),
            };
        }

        /**
         * Normalises a resource into the JSON structure exposed by this command.
         * @param FileResource|DirectoryResource $resource The resource to normalise.
         * @return array{type: string, path: string, name: string} The normalised resource payload.
         */
        private function normaliseResource (FileResource|DirectoryResource $resource) : array {
            $isDirectory = $resource instanceof DirectoryResource;

            return [
                "type" => $isDirectory ? "dir" : "file",
                "path" => $resource->getPath(),
                "name" => $resource->getBaseName()
            ];
        }

        /**
         * Renders JSON output.
            * @param array<int, FileResource|DirectoryResource> $results The search results.
         * @throws InvalidArgumentException If the results cannot be encoded as JSON.
         */
        private function renderJson (array $results) : void {
                $payload = array_map(fn (FileResource|DirectoryResource $resource) : array => $this->normaliseResource($resource), $results);
            $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            if ($json === false) {
                throw new InvalidArgumentException("Failed to encode find results as JSON.");
            }

            echo $json . PHP_EOL;
        }

        /**
         * Renders one path per line.
         * @param array<int, FileResource|DirectoryResource> $results The search results.
         */
        private function renderPaths (array $results) : void {
            foreach ($results as $resource) {
                echo $resource->getPath() . PHP_EOL;
            }
        }

        /**
         * Resolves the root directory resource for the command.
         * @throws InvalidArgumentException If the configured adapter is unsupported.
         * @return LocalDirectory The resolved root directory.
         */
        private function resolveRootDirectory () : LocalDirectory {
            return $this->resolveExistingLocalDirectory($this->adapter, $this->path);
        }

        /**
         * Executes the find command.
         * @return int The exit code of the command.
         */
        public function run () : int {
            try {
                $directory = $this->resolveRootDirectory();
                $results = $this->filterResults($directory->search($this->pattern, $this->recursive));

                match ($this->getEffectiveFormat()) {
                    "json" => $this->renderJson($results),
                    default => $this->renderPaths($results)
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