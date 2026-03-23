<?php
    /**
     * Project Name:    Wingman Explorer - Console Read Command
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
    use Wingman\Explorer\Exceptions\NonexistentLineException;
    use Wingman\Explorer\Resources\LocalFile;

    /**
     * Reads file content using Explorer's full-content, line-based, or byte-range capabilities.
     *
     * The current implementation accepts an explicit `--adapter=local` contract, defaulting to
     * `local` when omitted, so the command surface remains stable before broader adapter
     * resolution is introduced for the Console bridge.
     *
     * Read modes are intentionally mutually exclusive. The command defaults to a full read, but
     * callers may explicitly select a single line, an inclusive line range, an exclusive byte
     * range, or streamed full-file output.
     *
     * @package Wingman\Explorer\Bridge\Console\Commands
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    #[Cmd(name: "explorer:read", description: "Reads file content using Explorer's file APIs.")]
    class ReadCommand extends Command {
        use ResolvesLocalExplorerResources;

        /**
         * The filesystem adapter to use.
         * @var string
         */
        #[Option(name: "adapter", description: "The filesystem adapter to use; currently only local is supported")]
        protected string $adapter = "local";

        /**
         * A one-based line number to read.
         * @var string|null
         */
        #[Option(name: "line", description: "Read one line by its one-based line number")]
        protected ?string $line = null;

        /**
         * An inclusive one-based line range expressed as from:to.
         * @var string|null
         */
        #[Option(name: "lines", description: "Read an inclusive line range expressed as from:to")]
        protected ?string $lines = null;

        /**
         * The file to read.
         * @var string
         */
        #[Argument(index: 0, description: "The file to read")]
        protected string $path;

        /**
         * A zero-based byte range expressed as start:end, where end is exclusive.
         * @var string|null
         */
        #[Option(name: "range", description: "Read a zero-based byte range expressed as start:end")]
        protected ?string $range = null;

        /**
         * Whether the full file should be streamed to stdout.
         * @var bool
         */
        #[Flag(name: "stream", description: "Stream the full file to stdout")]
        protected bool $stream = false;

        /**
         * Counts the explicitly selected read modes and rejects conflicting combinations.
         * @throws InvalidArgumentException If more than one explicit read mode is selected.
         * @return string The resolved read mode.
         */
        private function getReadMode () : string {
            $modes = array_values(array_filter([
                $this->line !== null && trim($this->line) !== "" ? "line" : null,
                $this->lines !== null && trim($this->lines) !== "" ? "lines" : null,
                $this->range !== null && trim($this->range) !== "" ? "range" : null,
                $this->stream ? "stream" : null
            ]));

            if (count($modes) > 1) {
                throw new InvalidArgumentException("The --line, --lines, --range, and --stream modes are mutually exclusive.");
            }

            return $modes[0] ?? "full";
        }

        /**
         * Parses a one-based positive integer line number.
         * @param string $value The raw option value.
         * @throws InvalidArgumentException If the line number is not a positive integer.
         * @return int The parsed line number.
         */
        private function parseLineNumber (string $value) : int {
            if (!preg_match('/^[1-9]\d*$/', $value)) {
                throw new InvalidArgumentException("The --line option must be a positive integer.");
            }

            return (int) $value;
        }

        /**
         * Parses an inclusive one-based line range.
         * @param string $value The raw range expression.
         * @throws InvalidArgumentException If the range expression is malformed.
         * @return array{0: int, 1: int} The parsed from/to line numbers.
         */
        private function parseLineRange (string $value) : array {
            if (!preg_match('/^([1-9]\d*):([1-9]\d*)$/', $value, $matches)) {
                throw new InvalidArgumentException("The --lines option must use the form from:to with positive integers.");
            }

            $from = (int) $matches[1];
            $to = (int) $matches[2];

            if ($from > $to) {
                throw new InvalidArgumentException("The --lines option must use an ascending inclusive range.");
            }

            return [$from, $to];
        }

        /**
         * Parses a zero-based byte range where the end offset is exclusive.
         * @param string $value The raw range expression.
         * @throws InvalidArgumentException If the range expression is malformed.
         * @return array{0: int, 1: int} The parsed start/end offsets.
         */
        private function parseByteRange (string $value) : array {
            if (!preg_match('/^(\d+):(\d+)$/', $value, $matches)) {
                throw new InvalidArgumentException("The --range option must use the form start:end with non-negative integers.");
            }

            $start = (int) $matches[1];
            $end = (int) $matches[2];

            if ($start > $end) {
                throw new InvalidArgumentException("The --range option must use an ascending range where end is not less than start.");
            }

            return [$start, $end];
        }

        /**
         * Reads an inclusive line range from a file while preserving line endings.
         * @param LocalFile $file The file to read from.
         * @param int $from The starting one-based line number.
         * @param int $to The ending one-based line number.
         * @throws NonexistentLineException If the requested line range exceeds the file length.
         * @return string The requested line range.
         */
        private function readLineRange (LocalFile $file, int $from, int $to) : string {
            $stream = $file->getContentStream();
            $current = 1;
            $content = "";

            while (($line = $stream->readLine()) !== null) {
                if ($current >= $from && $current <= $to) {
                    $content .= $line;
                }

                if ($current === $to) {
                    $stream->close();
                    return $content;
                }

                $current++;
            }

            $stream->close();

            throw new NonexistentLineException("The requested line range {$from}:{$to} does not exist in '{$file->getPath()}'.");
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
         * Streams the full file directly to stdout.
         * @param LocalFile $file The file to stream.
         */
        private function streamFile (LocalFile $file) : void {
            $stream = $file->getContentStream();

            foreach ($stream->readChunks() as $chunk) {
                if ($chunk === "") {
                    break;
                }

                echo $chunk;
            }

            $stream->close();
        }

        /**
         * Executes the read command.
         * @return int The exit code of the command.
         */
        public function run () : int {
            try {
                $file = $this->resolveFile();

                switch ($this->getReadMode()) {
                    case "line":
                        echo $file->getLine($this->parseLineNumber((string) $this->line));
                        break;

                    case "lines":
                        [$from, $to] = $this->parseLineRange((string) $this->lines);
                        echo $this->readLineRange($file, $from, $to);
                        break;

                    case "range":
                        [$start, $end] = $this->parseByteRange((string) $this->range);
                        echo $file->readRange($start, $end);
                        break;

                    case "stream":
                        $this->streamFile($file);
                        break;

                    default:
                        echo $file->getContent();
                        break;
                }

                return 0;
            }
            catch (InvalidArgumentException|NonexistentLineException $e) {
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