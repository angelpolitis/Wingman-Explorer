<?php
    /**
     * Project Name:    Wingman Explorer - Console Replace Command
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
     * Performs safe content replacement within a file using string or regex matching.
     *
     * The current implementation accepts an explicit `--adapter=local` contract, defaulting to
     * `local` when omitted, so the command surface remains stable before broader adapter
     * resolution is introduced for the Console bridge.
     *
     * Scope flags are mutually exclusive. When no scope flag is supplied the command defaults to
     * replacing all matches. Mutations are buffered through Explorer's `LocalFile` APIs and are
     * only persisted when `--dry-run` is not active.
     *
     * @package Wingman\Explorer\Bridge\Console\Commands
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    #[Cmd(name: "explorer:replace", description: "Replaces content within a file using Explorer's mutation APIs.")]
    class ReplaceCommand extends Command {
        use ResolvesLocalExplorerResources;

        /**
         * Whether all matches should be replaced.
         * @var bool
         */
        #[Flag(name: "all", description: "Replace all matches")]
        protected bool $all = false;

        /**
         * The filesystem adapter to use.
         * @var string
         */
        #[Option(name: "adapter", description: "The filesystem adapter to use; currently only local is supported")]
        protected string $adapter = "local";

        /**
         * Whether to preview the replacement without persisting it.
         * @var bool
         */
        #[Flag(name: "dry-run", description: "Show what would change without persisting it")]
        protected bool $dryRun = false;

        /**
         * Whether only the first match should be replaced.
         * @var bool
         */
        #[Flag(name: "first", description: "Replace only the first match")]
        protected bool $first = false;

        /**
         * Whether only the last match should be replaced.
         * @var bool
         */
        #[Flag(name: "last", description: "Replace only the last match")]
        protected bool $last = false;

        /**
         * The file to update.
         * @var string
         */
        #[Argument(index: 0, description: "The file to update")]
        protected string $path;

        /**
         * Whether success output should be suppressed.
         * @var bool
         */
        #[Flag(name: "quiet", description: "Suppress success output")]
        protected bool $quiet = false;

        /**
         * The replacement value.
         * @var string
         */
        #[Argument(index: 2, description: "The replacement value")]
        protected string $replacement;

        /**
         * Whether regex replacement should be used.
         * @var bool
         */
        #[Flag(name: "regex", description: "Use regex replacement")]
        protected bool $regex = false;

        /**
         * The search term or regex pattern.
         * @var string
         */
        #[Argument(index: 1, description: "The search term or regex pattern")]
        protected string $search;

        /**
         * Applies a regex last-match replacement because the base trait only exposes all-pattern and limited first-pattern replacement.
         * @param LocalFile $file The file to update.
         * @param string $pattern The regex pattern.
         * @param string $replacement The replacement value.
         * @return int The number of replacements applied.
         */
        private function applyLastPatternReplacement (LocalFile $file, string $pattern, string $replacement) : int {
            $content = $file->getContent();
            preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE);
            $entries = $matches[0] ?? [];

            if (empty($entries)) {
                return 0;
            }

            $entry = $entries[array_key_last($entries)];
            $matched = (string) ($entry[0] ?? "");
            $offset = (int) ($entry[1] ?? 0);
            $replaced = preg_replace($pattern, $replacement, $matched, 1);

            if ($replaced === null) {
                return 0;
            }

            $content = substr_replace($content, $replaced, $offset, strlen($matched));
            $file->write($content);

            return 1;
        }

        /**
         * Applies the requested replacement strategy to the file.
         * @param LocalFile $file The file to update.
         * @return int The number of replacements applied.
         */
        private function applyReplacement (LocalFile $file) : int {
            return $this->regex
                ? $this->applyRegexReplacement($file)
                : $this->applyStringReplacement($file);
        }

        /**
         * Applies the requested regex replacement strategy.
         * @param LocalFile $file The file to update.
         * @return int The number of replacements applied.
         */
        private function applyRegexReplacement (LocalFile $file) : int {
            $count = $this->countPatternMatches($file->getContent(), $this->search);

            if ($count === 0) {
                return 0;
            }

            match ($this->getEffectiveScope()) {
                "first" => $file->replacePattern($this->search, $this->replacement, 1),
                "last" => $this->applyLastPatternReplacement($file, $this->search, $this->replacement),
                default => $file->replacePattern($this->search, $this->replacement)
            };

            return match ($this->getEffectiveScope()) {
                "all" => $count,
                default => 1
            };
        }

        /**
         * Applies the requested plain-string replacement strategy.
         * @param LocalFile $file The file to update.
         * @return int The number of replacements applied.
         */
        private function applyStringReplacement (LocalFile $file) : int {
            $content = $file->getContent();
            $count = substr_count($content, $this->search);

            if ($count === 0) {
                return 0;
            }

            match ($this->getEffectiveScope()) {
                "first" => $file->replaceFirst($this->search, $this->replacement),
                "last" => $file->replaceLast($this->search, $this->replacement),
                default => $file->replace($this->search, $this->replacement)
            };

            return match ($this->getEffectiveScope()) {
                "all" => $count,
                default => 1
            };
        }

        /**
         * Counts regex matches in the current content.
         * @param string $content The content to inspect.
         * @param string $pattern The regex pattern.
         * @return int The number of full matches.
         */
        private function countPatternMatches (string $content, string $pattern) : int {
            return preg_match_all($pattern, $content, $matches) ?: 0;
        }

        /**
         * Emits a summary message when the command is not quiet or when a dry-run is requested.
         * @param int $replacements The number of replacements applied.
         */
        private function emitSummary (int $replacements) : void {
            if ($this->quiet && !$this->dryRun) {
                return;
            }

            $verb = $this->dryRun ? "Would replace" : "Replaced";
            echo $verb . " {$replacements} occurrence(s) in {$this->path}." . PHP_EOL;
        }

        /**
         * Resolves the effective replacement scope.
         * @throws InvalidArgumentException If more than one scope flag is active.
         * @return string The effective scope.
         */
        private function getEffectiveScope () : string {
            $scopes = array_values(array_filter([
                $this->first ? "first" : null,
                $this->last ? "last" : null,
                $this->all ? "all" : null
            ]));

            if (count($scopes) > 1) {
                throw new InvalidArgumentException("Exactly one of --first, --last, or --all may be active.");
            }

            return $scopes[0] ?? "all";
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
         * Validates the command inputs before mutation.
         * @throws InvalidArgumentException If the search input is malformed.
         */
        private function validateInput () : void {
            if ($this->search === "") {
                throw new InvalidArgumentException("The search value must not be empty.");
            }

            if ($this->regex && @preg_match($this->search, "") === false) {
                throw new InvalidArgumentException("The --regex flag requires a valid PCRE pattern.");
            }

            $this->getEffectiveScope();
        }

        /**
         * Executes the replace command.
         * @return int The exit code of the command.
         */
        public function run () : int {
            try {
                $this->validateInput();
                $file = $this->resolveFile();
                $replacements = $this->applyReplacement($file);

                if (!$this->dryRun) {
                    $file->save();
                }

                $this->emitSummary($replacements);

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