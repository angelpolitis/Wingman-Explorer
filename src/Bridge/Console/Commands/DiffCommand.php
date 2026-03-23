<?php
    /**
     * Project Name:    Wingman Explorer - Console Diff Command
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
    use Wingman\Console\Attributes\Option;
    use Wingman\Console\Command;
    use Wingman\Explorer\Bridge\Console\Traits\ResolvesLocalExplorerResources;
    use Wingman\Explorer\FileDiff;
    use Wingman\Explorer\Resources\LocalFile;

    /**
     * Compares two files using Explorer's native file diff implementation.
     *
     * The current implementation accepts an explicit `--adapter=local` contract, defaulting to
     * `local` when omitted, so the command surface remains stable before broader adapter
     * resolution is introduced for the Console bridge.
     *
     * Unified output is intentionally human-friendly rather than a byte-perfect GNU diff clone.
     * It renders the ordered hunk list with file headers and per-line prefixes for unchanged,
     * removed, and added lines.
     *
     * @package Wingman\Explorer\Bridge\Console\Commands
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    #[Cmd(name: "explorer:diff", description: "Compares two files using Explorer's native file diff implementation.")]
    class DiffCommand extends Command {
        use ResolvesLocalExplorerResources;

        /**
         * The filesystem adapter to use.
         * @var string
         */
        #[Option(name: "adapter", description: "The filesystem adapter to use; currently only local is supported")]
        protected string $adapter = "local";

        /**
         * The base file.
         * @var string
         */
        #[Argument(index: 0, description: "The base file")]
        protected string $fileA;

        /**
         * The comparison file.
         * @var string
         */
        #[Argument(index: 1, description: "The comparison file")]
        protected string $fileB;

        /**
         * The output format.
         * @var string
         */
        #[Option(name: "format", description: "The output format: unified or json", alias: "F")]
        protected string $format = "unified";

        /**
         * The maximum number of lines per file allowed for in-memory diffing.
         * @var string|null
         */
        #[Option(name: "max-lines", description: "The maximum number of lines per file allowed for in-memory diffing")]
        protected ?string $maxLines = null;

        /**
         * Gets the effective output format after normalising case.
         * @throws InvalidArgumentException If the format is unsupported.
         * @return string The effective output format.
         */
        private function getEffectiveFormat () : string {
            $format = strtolower(trim($this->format));

            return match ($format) {
                "unified", "json" => $format,
                default => throw new InvalidArgumentException("The --format option must be unified or json.")
            };
        }

        /**
         * Parses the maximum line count option.
         * @throws InvalidArgumentException If the configured value is not a positive integer.
         * @return int The parsed line limit.
         */
        private function getMaxLines () : int {
            if ($this->maxLines === null) {
                return 50000;
            }

            if (!preg_match('/^[1-9]\d*$/', $this->maxLines)) {
                throw new InvalidArgumentException("The --max-lines option must be a positive integer.");
            }

            return (int) $this->maxLines;
        }

        /**
         * Renders raw JSON output.
         * @param array{hunks: list<array{operation: string, lineA: int|null, lineB: int|null, content: string}>} $diff The diff result.
         * @throws RuntimeException If the diff cannot be encoded as JSON.
         */
        private function renderJson (array $diff) : void {
            $json = json_encode($diff, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            if ($json === false) {
                throw new RuntimeException("Failed to encode diff results as JSON.");
            }

            echo $json . PHP_EOL;
        }

        /**
         * Renders a human-friendly unified diff view.
         * @param array{hunks: list<array{operation: string, lineA: int|null, lineB: int|null, content: string}>} $diff The diff result.
         */
        private function renderUnified (array $diff) : void {
            echo "--- {$this->fileA}" . PHP_EOL;
            echo "+++ {$this->fileB}" . PHP_EOL;

            foreach ($diff["hunks"] as $hunk) {
                $prefix = match ($hunk["operation"]) {
                    "added" => "+",
                    "removed" => "-",
                    default => " "
                };

                echo $prefix . $hunk["content"] . PHP_EOL;
            }
        }

        /**
         * Resolves both file resources for the diff command.
         * @throws InvalidArgumentException If the configured adapter is unsupported.
         * @throws RuntimeException If either path does not resolve to a file.
         * @return array{0: LocalFile, 1: LocalFile} The resolved files.
         */
        private function resolveFiles () : array {
            return [
                $this->resolveExistingLocalFile($this->adapter, $this->fileA),
                $this->resolveExistingLocalFile($this->adapter, $this->fileB)
            ];
        }

        /**
         * Executes the diff command.
         * @return int The exit code of the command.
         */
        public function run () : int {
            try {
                [$fileA, $fileB] = $this->resolveFiles();
                $diff = FileDiff::compare($fileA, $fileB, $this->getMaxLines());

                match ($this->getEffectiveFormat()) {
                    "json" => $this->renderJson($diff),
                    default => $this->renderUnified($diff)
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