<?php
    /**
     * Project Name:    Wingman Explorer - Console Directory Diff Command
     * Created by:      Angel Politis
     * Creation Date:   Mar 23 2026
     * Last Modified:   Mar 23 2026
     *
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
    use Wingman\Explorer\DirectoryDiff;
    use Wingman\Explorer\Interfaces\Resources\DirectoryResource;
    use Wingman\Explorer\Interfaces\Resources\FileResource;

    /**
     * Compares two directories and groups the added, removed, and modified resources.
     *
     * The command currently accepts the same temporary `--adapter=local` contract as
     * the rest of the Explorer Console bridge, defaulting to `local` when omitted.
     * Recursive comparison is enabled by default so nested filesystem changes are
     * surfaced without requiring extra flags in the common case.
     *
     * @package Wingman\Explorer\Bridge\Console\Commands
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    #[Cmd(name: "explorer:dir-diff", description: "Compares two directories and groups the changed resources.")]
    class DirDiffCommand extends Command {
        use ResolvesLocalExplorerResources;

        /**
         * The filesystem adapter to use.
         * @var string
         */
        #[Option(name: "adapter", description: "The filesystem adapter to use; currently only local is supported")]
        protected string $adapter = "local";

        /**
         * The base directory.
         * @var string
         */
        #[Argument(index: 0, description: "The base directory")]
        protected string $dirA;

        /**
         * The comparison directory.
         * @var string
         */
        #[Argument(index: 1, description: "The comparison directory")]
        protected string $dirB;

        /**
         * The output format.
         * @var string
         */
        #[Option(name: "format", description: "The output format: table or json", alias: "F")]
        protected string $format = "table";

        /**
         * Whether matching subdirectories should be compared recursively.
         * @var bool
         */
        #[Flag(name: "recursive", description: "Recurse into matching subdirectories; this is enabled by default")]
        protected bool $recursive = true;

        /**
         * Creates a new command and preserves recursive comparison when the flag is omitted.
         * @param array|string $command A command-line expression or an array of command-line arguments.
         */
        public function __construct (array|string $command) {
            parent::__construct($command);

            if (!$this->hasFlag("recursive")) {
                $this->recursive = true;
            }
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
                default => throw new InvalidArgumentException("The --format option must be table or json.")
            };
        }

        /**
         * Normalises a resource into the payload exposed by this command.
         * @param FileResource|DirectoryResource $resource The resource to normalise.
         * @return array{type: string, name: string, path: string} The normalised resource payload.
         */
        private function normaliseResource (FileResource|DirectoryResource $resource) : array {
            return [
                "type" => $resource instanceof DirectoryResource ? "dir" : "file",
                "name" => $resource->getBaseName(),
                "path" => $resource->getPath()
            ];
        }

        /**
         * Renders the diff as JSON.
         * @param array{added: array<int, FileResource|DirectoryResource>, removed: array<int, FileResource|DirectoryResource>, modified: array<int, FileResource|DirectoryResource>} $diff The diff result.
         * @throws RuntimeException If the diff cannot be encoded as JSON.
         */
        private function renderJson (array $diff) : void {
            $payload = [];

            foreach (["added", "removed", "modified"] as $section) {
                $payload[$section] = array_map(fn (FileResource|DirectoryResource $resource) : array => $this->normaliseResource($resource), $diff[$section]);
            }

            $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            if ($json === false) {
                throw new RuntimeException("Failed to encode directory diff results as JSON.");
            }

            echo $json . PHP_EOL;
        }

        /**
         * Renders the diff in grouped human-readable table output.
         * @param array{added: array<int, FileResource|DirectoryResource>, removed: array<int, FileResource|DirectoryResource>, modified: array<int, FileResource|DirectoryResource>} $diff The diff result.
         */
        private function renderTable (array $diff) : void {
            $sections = [
                "added" => "Added",
                "removed" => "Removed",
                "modified" => "Modified"
            ];
            $isFirstSection = true;

            foreach ($sections as $section => $label) {
                if (!$isFirstSection) {
                    echo PHP_EOL;
                }

                echo $label . PHP_EOL;

                if (empty($diff[$section])) {
                    echo "None" . PHP_EOL;
                    $isFirstSection = false;
                    continue;
                }

                $rows = array_map(function (FileResource|DirectoryResource $resource) : array {
                    $payload = $this->normaliseResource($resource);

                    return [
                        $payload["type"],
                        $payload["name"],
                        $payload["path"]
                    ];
                }, $diff[$section]);

                $this->console->style(fn (Style $style) => yield $style->renderTable(["Type", "Name", "Path"], $rows));
                $isFirstSection = false;
            }
        }

        /**
         * Resolves both directory resources for the current command invocation.
         * @throws InvalidArgumentException If the configured adapter is unsupported.
         * @throws RuntimeException If either path does not resolve to a directory.
         * @return array{0: DirectoryResource, 1: DirectoryResource} The resolved directories.
         */
        private function resolveDirectories () : array {
            return [
                $this->resolveExistingLocalDirectory($this->adapter, $this->dirA),
                $this->resolveExistingLocalDirectory($this->adapter, $this->dirB)
            ];
        }

        /**
         * Executes the directory diff command.
         * @return int The exit code of the command.
         */
        public function run () : int {
            try {
                [$dirA, $dirB] = $this->resolveDirectories();
                $diff = DirectoryDiff::compare($dirA, $dirB, $this->recursive);

                match ($this->getEffectiveFormat()) {
                    "json" => $this->renderJson($diff),
                    default => $this->renderTable($diff)
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