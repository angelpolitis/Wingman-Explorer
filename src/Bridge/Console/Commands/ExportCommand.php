<?php
    /**
     * Project Name:    Wingman Explorer - Console Export Command
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
    use JsonException;
    use Throwable;
    use Wingman\Console\Attributes\Argument;
    use Wingman\Console\Attributes\Command as Cmd;
    use Wingman\Console\Attributes\Flag;
    use Wingman\Console\Attributes\Option;
    use Wingman\Console\Command;
    use Wingman\Explorer\Interfaces\IO\ExporterInterface;
    use Wingman\Explorer\IO\IOManager;

    /**
     * Serialises structured input to a destination file using Explorer exporters.
     *
     * The command initialises Explorer's IO manager on demand, accepts exactly one
     * input source, and treats both inline data and stdin input as JSON.
     * Exporter selection is either inferred from the destination path or forced via
     * `--format`, with `text` / `txt` aliases mapped to Explorer's fallback text
     * exporter.
     *
     * @package Wingman\Explorer\Bridge\Console\Commands
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    #[Cmd(name: "explorer:export", description: "Serialises structured input to a destination file.")]
    class ExportCommand extends Command {
        /**
         * The inline serialised input.
         * @var mixed
         */
        #[Argument(index: 1, description: "Inline serialised input, treated as JSON")]
        protected mixed $data = null;

        /**
         * The output destination path.
         * @var string
         */
        #[Argument(index: 0, description: "The output file path")]
        protected string $destination;

        /**
         * The forced exporter type.
         * @var string|null
         */
        #[Option(name: "format", description: "Force a specific exporter type")]
        protected ?string $format = null;

        /**
         * Whether success output should be suppressed.
         * @var bool
         */
        #[Flag(name: "quiet", description: "Suppress success output")]
        protected bool $quiet = false;

        /**
         * Whether the input should be read from stdin.
         * @var bool
         */
        #[Flag(name: "stdin", description: "Read the input payload from stdin")]
        protected bool $readFromStdin = false;

        /**
         * Emits the success summary unless quiet mode is enabled.
         */
        private function emitSuccessSummary () : void {
            if ($this->quiet) {
                return;
            }
            echo "Exported data to {$this->destination}." . PHP_EOL;
        }

        /**
         * Executes the export for the resolved payload.
         * @param mixed $payload The decoded export payload.
         */
        private function exportPayload (mixed $payload) : void {
            IOManager::init();

            if ($this->format === null || trim($this->format) === "") {
                IOManager::getExportManager()->export($payload, $this->destination);
                return;
            }

            $this->resolveExporterByFormat((string) $this->format)->export($payload, $this->destination);
        }

        /**
         * Determines whether a forced format should resolve to the fallback text exporter.
         * @param string $format The normalised format.
         * @return bool Whether the format should use the fallback text exporter.
         */
        private function isTextFormat (string $format) : bool {
            return in_array($format, ["text", "txt"], true);
        }

        /**
         * Reads the raw stdin payload.
         * @return string The raw stdin content.
         */
        private function readStdinPayload () : string {
            $input = file_get_contents("php://stdin");
            return $input === false ? "" : $input;
        }

        /**
         * Resolves a forced format to a registered exporter instance.
         * @param string $format The requested exporter type.
         * @throws InvalidArgumentException If the format is unsupported.
         * @return ExporterInterface The resolved exporter.
         */
        private function resolveExporterByFormat (string $format) : ExporterInterface {
            $normalisedFormat = strtolower(trim($format));
            $exportManager = IOManager::getExportManager();
            $exporter = $exportManager->getByType($normalisedFormat);

            if ($exporter !== null) {
                return $exporter;
            }

            if ($this->isTextFormat($normalisedFormat) && $exportManager->getFallback() !== null) {
                return $exportManager->getFallback();
            }

            throw new InvalidArgumentException("The --format option must be one of json, jsonc, jsonl, jsonlines, ini, csv, txt, or text.");
        }

        /**
         * Reads and decodes the configured export payload.
         * @throws InvalidArgumentException If the input contract is invalid or the JSON is malformed.
         * @return mixed The decoded payload.
         */
        private function resolvePayload () : mixed {
            $hasInlineData = array_key_exists(1, $this->args);

            if ($hasInlineData && $this->readFromStdin) {
                throw new InvalidArgumentException("Provide either inline data or --stdin, but not both.");
            }

            if (!$hasInlineData && !$this->readFromStdin) {
                throw new InvalidArgumentException("Provide inline data or enable --stdin.");
            }

            if (!$this->readFromStdin) {
                $inlineInput = $this->resolveRawArgument(1);

                if (!is_string($inlineInput)) {
                    return $this->data;
                }

                if (trim($inlineInput) === "") {
                    throw new InvalidArgumentException("The export payload must not be empty.");
                }

                try {
                    return json_decode($inlineInput, true, 512, JSON_THROW_ON_ERROR);
                }
                catch (JsonException $e) {
                    throw new InvalidArgumentException("The export payload must be valid JSON.", 0, $e);
                }
            }

            $input = $this->readStdinPayload();

            if (trim($input) === "") {
                throw new InvalidArgumentException("The export payload must not be empty.");
            }

            try {
                return json_decode($input, true, 512, JSON_THROW_ON_ERROR);
            }
            catch (JsonException $e) {
                throw new InvalidArgumentException("The export payload must be valid JSON.", 0, $e);
            }
        }

        /**
         * Resolves the raw, undecoded positional argument at the requested index.
         * @param int $index The positional argument index.
         * @return string|null The raw positional token when available.
         */
        private function resolveRawArgument (int $index) : ?string {
            $position = 0;
            $inputCount = count($this->input);

            for ($i = 0; $i < $inputCount; $i++) {
                $token = $this->input[$i];

                if (!is_string($token)) {
                    if ($position === $index) {
                        return null;
                    }

                    $position++;
                    continue;
                }

                if (str_starts_with($token, "--")) {
                    if (!str_contains($token, "=")) {
                        $next = $this->input[$i + 1] ?? null;

                        if (is_string($next) && !str_starts_with($next, "-")) {
                            $i++;
                        }
                    }

                    continue;
                }

                if (str_starts_with($token, "-")) {
                    $content = substr($token, 1);

                    if (!str_contains($content, "=") && strlen($content) === 1) {
                        $next = $this->input[$i + 1] ?? null;

                        if (is_string($next) && !str_starts_with($next, "-")) {
                            $i++;
                        }
                    }

                    continue;
                }

                if ($position === $index) {
                    return $token;
                }

                $position++;
            }

            return null;
        }

        /**
         * Executes the export command.
         * @return int The exit code of the command.
         */
        public function run () : int {
            try {
                $this->exportPayload($this->resolvePayload());
                $this->emitSuccessSummary();

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