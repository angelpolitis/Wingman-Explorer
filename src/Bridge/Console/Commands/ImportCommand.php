<?php
    /**
     * Project Name:    Wingman Explorer - Console Import Command
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
    use Wingman\Explorer\Interfaces\IO\ImporterInterface;
    use Wingman\Explorer\IO\IOManager;

    /**
     * Imports a supported file and emits the imported value for inspection or piping.
     *
     * The command initialises Explorer's IO manager on demand, then either lets the
     * import manager auto-negotiate the best importer for the supplied file or forces
     * a specific importer when `--format` is provided. Structured data can be rendered
     * either as human-readable PHP serialisation or as JSON, while plain-text imports
     * are emitted as raw text by default.
     *
     * @package Wingman\Explorer\Bridge\Console\Commands
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    #[Cmd(name: "explorer:import", description: "Imports a supported file and emits the imported value.")]
    class ImportCommand extends Command {
        /**
         * The forced importer type.
         * @var string|null
         */
        #[Option(name: "format", description: "Force a specific importer type")]
        protected ?string $format = null;

        /**
         * Whether JSON output should be emitted.
         * @var bool
         */
        #[Flag(name: "json", description: "Emit the imported value as JSON")]
        protected bool $json = false;

        /**
         * The source file to import.
         * @var string
         */
        #[Argument(index: 0, description: "The source file to import")]
        protected string $path;

        /**
         * Whether structured output should be pretty-printed.
         * @var bool
         */
        #[Flag(name: "pretty", description: "Pretty-print structured output")]
        protected bool $pretty = false;

        /**
         * Emits the imported value in the configured output mode.
         * @param mixed $value The imported value.
         * @throws RuntimeException If JSON encoding fails.
         */
        private function emitImportedValue (mixed $value) : void {
            if ($this->json) {
                $this->emitJson($value);
                return;
            }

            $this->emitHumanReadableValue($value);
        }

        /**
         * Emits a human-readable representation of the imported value.
         * @param mixed $value The imported value.
         */
        private function emitHumanReadableValue (mixed $value) : void {
            if (is_string($value)) {
                echo $value;
                return;
            }

            if (is_bool($value)) {
                echo ($value ? "true" : "false") . PHP_EOL;
                return;
            }

            if ($value === null) {
                echo "null" . PHP_EOL;
                return;
            }

            if (is_int($value) || is_float($value)) {
                echo (string) $value . PHP_EOL;
                return;
            }

            $output = $this->pretty
                ? print_r($value, true)
                : var_export($value, true);

            echo rtrim($output) . PHP_EOL;
        }

        /**
         * Emits the imported value as JSON.
         * @param mixed $value The imported value.
         * @throws RuntimeException If JSON encoding fails.
         */
        private function emitJson (mixed $value) : void {
            $flags = JSON_UNESCAPED_SLASHES;

            if ($this->pretty) {
                $flags |= JSON_PRETTY_PRINT;
            }

            $json = json_encode($value, $flags);

            if ($json === false) {
                throw new RuntimeException("Failed to encode imported value as JSON.");
            }

            echo $json . PHP_EOL;
        }

        /**
         * Imports the configured file, optionally forcing a specific importer type.
         * @throws InvalidArgumentException If the forced format is unsupported.
         * @return mixed The imported value.
         */
        private function importValue () : mixed {
            IOManager::init();

            if ($this->format === null || trim($this->format) === "") {
                return IOManager::getImportManager()->import($this->path);
            }

            return $this->resolveImporterByFormat((string) $this->format)->import($this->path);
        }

        /**
         * Determines whether a forced format should resolve to the fallback text importer.
         * @param string $format The normalised format.
         * @return bool Whether the format should use the fallback text importer.
         */
        private function isTextFormat (string $format) : bool {
            return in_array($format, ["text", "txt"], true);
        }

        /**
         * Resolves the source file path for the command.
         * @throws RuntimeException If the target path does not resolve to a file.
         */
        private function resolveFilePath () : void {
            if (!is_file($this->path)) {
                throw new RuntimeException("The path '{$this->path}' does not exist or is not a file.");
            }
        }

        /**
         * Resolves a forced format to a registered importer instance.
         * @param string $format The requested importer type.
         * @throws InvalidArgumentException If the format is unsupported.
         * @return ImporterInterface The resolved importer.
         */
        private function resolveImporterByFormat (string $format) : ImporterInterface {
            $normalisedFormat = strtolower(trim($format));
            $importManager = IOManager::getImportManager();
            $importer = $importManager->getByType($normalisedFormat);

            if ($importer !== null) {
                return $importer;
            }

            if ($this->isTextFormat($normalisedFormat) && $importManager->getFallback() !== null) {
                return $importManager->getFallback();
            }

            throw new InvalidArgumentException("The --format option must be one of json, jsonc, jsonl, jsonlines, ini, csv, php, txt, or text.");
        }

        /**
         * Executes the import command.
         * @return int The exit code of the command.
         */
        public function run () : int {
            try {
                $this->resolveFilePath();
                $this->emitImportedValue($this->importValue());

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