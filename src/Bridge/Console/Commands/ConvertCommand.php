<?php
    /**
     * Project Name:    Wingman Explorer - Console Convert Command
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
    use Wingman\Explorer\Interfaces\IO\ExporterInterface;
    use Wingman\Explorer\Interfaces\IO\ImporterInterface;
    use Wingman\Explorer\IO\IOManager;

    /**
     * Converts one supported file into another through Explorer's IO pipeline.
     *
     * The command initialises Explorer's IO manager on demand, imports the
     * source file either through normal negotiation or a forced importer, then
     * exports the imported value either through destination inference or a
     * forced exporter. Text aliases map to the fallback importer or exporter so
     * that extensionless text workflows remain predictable.
     *
     * @package Wingman\Explorer\Bridge\Console\Commands
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    #[Cmd(name: "explorer:convert", description: "Converts one supported file into another.")]
    class ConvertCommand extends Command {
        /**
         * The output destination path.
         * @var string
         */
        #[Argument(index: 1, description: "The output file path")]
        protected string $destination;

        /**
         * The forced importer type.
         * @var string|null
         */
        #[Option(name: "from", description: "Force a specific importer type")]
        protected ?string $from = null;

        /**
         * Whether success output should be suppressed.
         * @var bool
         */
        #[Flag(name: "quiet", description: "Suppress success output")]
        protected bool $quiet = false;

        /**
         * The source file path.
         * @var string
         */
        #[Argument(index: 0, description: "The source file to convert")]
        protected string $source;

        /**
         * The forced exporter type.
         * @var string|null
         */
        #[Option(name: "to", description: "Force a specific exporter type")]
        protected ?string $to = null;

        /**
         * Emits the success summary unless quiet mode is enabled.
         */
        private function emitSuccessSummary () : void {
            if ($this->quiet) {
                return;
            }

            echo "Converted {$this->source} to {$this->destination}." . PHP_EOL;
        }

        /**
         * Exports the imported value to the configured destination.
         * @param mixed $value The imported value to export.
         */
        private function exportValue (mixed $value) : void {
            if ($this->to === null || trim($this->to) === "") {
                IOManager::getExportManager()->export($value, $this->destination);
                return;
            }

            $this->resolveExporterByFormat((string) $this->to)->export($value, $this->destination);
        }

        /**
         * Imports the configured source file.
         * @throws InvalidArgumentException If the forced importer format is unsupported.
         * @return mixed The imported value.
         */
        private function importValue () : mixed {
            if ($this->from === null || trim($this->from) === "") {
                return IOManager::getImportManager()->import($this->source);
            }

            return $this->resolveImporterByFormat((string) $this->from)->import($this->source);
        }

        /**
         * Determines whether a forced format should resolve to the fallback text IO.
         * @param string $format The normalised format.
         * @return bool Whether the format should use the fallback text IO.
         */
        private function isTextFormat (string $format) : bool {
            return in_array($format, ["text", "txt"], true);
        }

        /**
         * Resolves a forced exporter format to a registered exporter instance.
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

            throw new InvalidArgumentException("The --to option must be one of json, jsonc, jsonl, jsonlines, ini, csv, txt, or text.");
        }

        /**
         * Resolves a forced importer format to a registered importer instance.
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

            throw new InvalidArgumentException("The --from option must be one of json, jsonc, jsonl, jsonlines, ini, csv, php, txt, or text.");
        }

        /**
         * Validates that the configured source path resolves to a file.
         * @throws RuntimeException If the source does not resolve to a file.
         */
        private function resolveSourcePath () : void {
            if (!is_file($this->source)) {
                throw new RuntimeException("The path '{$this->source}' does not exist or is not a file.");
            }
        }

        /**
         * Executes the convert command.
         * @return int The exit code of the command.
         */
        public function run () : int {
            try {
                IOManager::init();
                $this->resolveSourcePath();
                $this->exportValue($this->importValue());
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