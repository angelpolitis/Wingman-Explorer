<?php
    /**
     * Project Name:    Wingman Explorer - Console Search Command
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
    use Wingman\Explorer\Bridge\Console\Traits\ResolvesLocalExplorerResources;
    use Wingman\Explorer\Resources\LocalFile;

    /**
     * Searches within a file using Explorer's string and pattern search APIs.
     *
     * The current implementation accepts an explicit `--adapter=local` contract, defaulting to
     * `local` when omitted, so the command surface remains stable before broader adapter
     * resolution is introduced for the Console bridge.
     *
     * The command keeps its surface intentionally narrow: first-match behaviour is the default,
     * `--all` expands the result set, `--regex` switches to pattern search, and output can be
     * shaped around matched content, line numbers, offsets, or JSON.
     *
     * @package Wingman\Explorer\Bridge\Console\Commands
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    #[Cmd(name: "explorer:search", description: "Searches within a file using Explorer's content APIs.")]
    class SearchCommand extends Command {
        use ResolvesLocalExplorerResources;

        /**
         * Whether all matches should be returned.
         * @var bool
         */
        #[Flag(name: "all", description: "Return all matches instead of only the first match")]
        protected bool $all = false;

        /**
         * The filesystem adapter to use.
         * @var string
         */
        #[Option(name: "adapter", description: "The filesystem adapter to use; currently only local is supported")]
        protected string $adapter = "local";

        /**
         * Whether JSON output should be emitted.
         * @var bool
         */
        #[Flag(name: "json", description: "Emit JSON output")]
        protected bool $json = false;

        /**
         * Whether line numbers should be returned.
         * @var bool
         */
        #[Flag(name: "line-numbers", description: "Return one-based line numbers for matches")]
        protected bool $lineNumbers = false;

        /**
         * The search term or pattern.
         * @var string
         */
        #[Argument(index: 1, description: "The search term or regex pattern")]
        protected string $needle;

        /**
         * Whether byte offsets should be returned.
         * @var bool
         */
        #[Flag(name: "offsets", description: "Return zero-based byte offsets for matches")]
        protected bool $offsets = false;

        /**
         * The file to search.
         * @var string
         */
        #[Argument(index: 0, description: "The file to search")]
        protected string $path;

        /**
         * Whether the needle should be interpreted as a regex pattern.
         * @var bool
         */
        #[Flag(name: "regex", description: "Interpret the needle as a regex pattern")]
        protected bool $regex = false;

        /**
         * Builds the plain-text output rows for the current flags.
         * @param array<int, array{match: string, line: int|null, offset: int|null}> $matches The resolved match payloads.
         * @return array<int, string> The output lines.
         */
        private function buildPlainRows (array $matches) : array {
            if ($this->lineNumbers && $this->offsets) {
                return array_map(fn (array $match) : string => $match["line"] . ":" . $match["offset"], $matches);
            }

            if ($this->lineNumbers) {
                return array_map(fn (array $match) : string => (string) $match["line"], $matches);
            }

            if ($this->offsets) {
                return array_map(fn (array $match) : string => (string) $match["offset"], $matches);
            }

            return array_map(fn (array $match) : string => $match["match"], $matches);
        }

        /**
         * Builds the JSON payload exposed by this command.
         * @param array<int, array{match: string, line: int|null, offset: int|null}> $matches The resolved match payloads.
         * @return array<string, mixed> The JSON payload.
         */
        private function buildJsonPayload (array $matches) : array {
            $mode = match (true) {
                $this->lineNumbers && $this->offsets => "line-offsets",
                $this->lineNumbers => "lines",
                $this->offsets => "offsets",
                default => "matches"
            };

            $results = match ($mode) {
                "line-offsets" => array_map(fn (array $match) : array => ["line" => $match["line"], "offset" => $match["offset"]], $matches),
                "lines" => array_map(fn (array $match) : int => (int) $match["line"], $matches),
                "offsets" => array_map(fn (array $match) : int => (int) $match["offset"], $matches),
                default => array_map(fn (array $match) : string => $match["match"], $matches)
            };

            return [
                "path" => $this->path,
                "needle" => $this->needle,
                "regex" => $this->regex,
                "all" => $this->all,
                "mode" => $mode,
                "results" => $results
            ];
        }

        /**
         * Calculates a one-based line number from a zero-based byte offset.
         * @param string $content The full file content.
         * @param int $offset The byte offset.
         * @return int The one-based line number.
         */
        private function calculateLineNumber (string $content, int $offset) : int {
            return substr_count(substr($content, 0, $offset), "\n") + 1;
        }

        /**
         * Emits the resolved results in the configured output mode.
         * @param array<int, array{match: string, line: int|null, offset: int|null}> $matches The resolved match payloads.
         * @throws RuntimeException If the JSON payload cannot be encoded.
         */
        private function emitResults (array $matches) : void {
            if ($this->json) {
                $json = json_encode($this->buildJsonPayload($matches), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

                if ($json === false) {
                    throw new RuntimeException("Failed to encode search results as JSON.");
                }

                echo $json . PHP_EOL;
                return;
            }

            $rows = $this->buildPlainRows($matches);

            if (empty($rows)) {
                return;
            }

            echo implode(PHP_EOL, $rows) . PHP_EOL;
        }

        /**
         * Ensures the search needle is valid for the selected mode.
         * @throws InvalidArgumentException If the needle is empty or the regex pattern is invalid.
         */
        private function validateNeedle () : void {
            if (trim($this->needle) === "") {
                throw new InvalidArgumentException("The search needle must not be empty.");
            }

            if ($this->regex && @preg_match($this->needle, "") === false) {
                throw new InvalidArgumentException("The --regex flag requires a valid PCRE pattern.");
            }
        }

        /**
         * Resolves the file resource for the command.
         * @throws InvalidArgumentException If the configured adapter is unsupported.
         * @throws RuntimeException If the target path does not resolve to a file.
         * @return LocalFile The resolved file resource.
         */
        private function resolveFile () : LocalFile {
            return $this->resolveExistingLocalFile($this->adapter, $this->path);
        }

        /**
         * Resolves the search results into a uniform internal payload.
         * @param LocalFile $file The file to search.
         * @return array<int, array{match: string, line: int|null, offset: int|null}> The resolved match payloads.
         */
        private function resolveMatches (LocalFile $file) : array {
            if ($this->regex) {
                return $this->resolvePatternMatches($file);
            }

            return $this->resolveStringMatches($file);
        }

        /**
         * Resolves regex search results into a uniform internal payload.
         * @param LocalFile $file The file to search.
         * @return array<int, array{match: string, line: int|null, offset: int|null}> The resolved match payloads.
         */
        private function resolvePatternMatches (LocalFile $file) : array {
            $entries = $this->all
                ? $file->findPattern($this->needle)
                : array_filter([$file->findFirstPattern($this->needle)]);

            if (empty($entries)) {
                return [];
            }

            $content = ($this->lineNumbers || $this->offsets) ? $file->getContent() : null;

            return array_map(function (array $entry) use ($content) : array {
                $match = (string) ($entry[0] ?? "");
                $offset = isset($entry[1]) ? (int) $entry[1] : null;

                return [
                    "match" => $match,
                    "line" => ($this->lineNumbers && $offset !== null && $content !== null) ? $this->calculateLineNumber($content, $offset) : null,
                    "offset" => $this->offsets ? $offset : null
                ];
            }, array_values($entries));
        }

        /**
         * Resolves plain-string search results into a uniform internal payload.
         * @param LocalFile $file The file to search.
         * @return array<int, array{match: string, line: int|null, offset: int|null}> The resolved match payloads.
         */
        private function resolveStringMatches (LocalFile $file) : array {
            if ($this->all) {
                $offsets = $file->find($this->needle);
            }
            else {
                $first = $file->findFirst($this->needle);
                $offsets = $first === null ? [] : [$first];
            }

            if (empty($offsets)) {
                return [];
            }

            $content = ($this->lineNumbers || $this->offsets) ? $file->getContent() : null;

            return array_map(function (int $offset) use ($content) : array {
                return [
                    "match" => $this->needle,
                    "line" => ($this->lineNumbers && $content !== null) ? $this->calculateLineNumber($content, $offset) : null,
                    "offset" => $this->offsets ? $offset : null
                ];
            }, $offsets);
        }

        /**
         * Executes the search command.
         * @return int The exit code of the command.
         */
        public function run () : int {
            try {
                $this->validateNeedle();
                $this->emitResults($this->resolveMatches($this->resolveFile()));

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