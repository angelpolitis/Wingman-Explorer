<?php
    /**
     * Project Name:    Wingman Explorer - Console Line Command
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
     * Performs a single line-based edit within a file using Explorer's line mutation APIs.
     *
     * The current implementation accepts an explicit `--adapter=local` contract, defaulting to
     * `local` when omitted, so the command surface remains stable before broader adapter
     * resolution is introduced for the Console bridge.
     *
     * Exactly one mutation mode is allowed per invocation. Dry-run mode applies the edit to the
     * in-memory file state, previews the affected lines, and then exits without persisting.
     *
     * @package Wingman\Explorer\Bridge\Console\Commands
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    #[Cmd(name: "explorer:line", description: "Performs a single line-based edit within a file.")]
    class LineCommand extends Command {
        use ResolvesLocalExplorerResources;

        /**
         * The filesystem adapter to use.
         * @var string
         */
        #[Option(name: "adapter", description: "The filesystem adapter to use; currently only local is supported")]
        protected string $adapter = "local";

        /**
         * Append a new line to the end of the file.
         * @var string|null
         */
        #[Option(name: "append", description: "Append a new line to the end of the file")]
        protected ?string $append = null;

        /**
         * Delete one line or an inclusive line range.
         * @var string|null
         */
        #[Option(name: "delete", description: "Delete one line or an inclusive line range")]
        protected ?string $delete = null;

        /**
         * Whether the edit should be previewed without saving.
         * @var bool
         */
        #[Flag(name: "dry-run", description: "Show a preview without saving")]
        protected bool $dryRun = false;

        /**
         * Insert text before a one-based line number using line:text form.
         * @var string|null
         */
        #[Option(name: "insert", description: "Insert text before a line using line:text form")]
        protected ?string $insert = null;

        /**
         * The file to update.
         * @var string
         */
        #[Argument(index: 0, description: "The file to update")]
        protected string $path;

        /**
         * Prepend a new line to the start of the file.
         * @var string|null
         */
        #[Option(name: "prepend", description: "Prepend a new line to the start of the file")]
        protected ?string $prepend = null;

        /**
         * Replace a line using line:text form.
         * @var string|null
         */
        #[Option(name: "replace", description: "Replace a line using line:text form")]
        protected ?string $replace = null;

        /**
         * Whether normal success output should be suppressed.
         * @var bool
         */
        #[Flag(name: "quiet", description: "Suppress success output")]
        protected bool $quiet = false;

        /**
         * Applies the selected line edit to the file and returns preview metadata.
         * @param LocalFile $file The file to edit.
         * @param string[] $originalLines The original file lines.
         * @return array{action: string, label: string, previewLines: array<int, string>} The operation metadata for preview or summary output.
         */
        private function applyLineEdit (LocalFile $file, array $originalLines) : array {
            [$action, $value] = $this->getEffectiveOperation();

            return match ($action) {
                "append" => $this->applyAppend($file, (string) $value, $originalLines),
                "delete" => $this->applyDelete($file, (string) $value, $originalLines),
                "insert" => $this->applyInsert($file, (string) $value),
                "prepend" => $this->applyPrepend($file, (string) $value),
                default => $this->applyReplace($file, (string) $value)
            };
        }

        /**
         * Applies an append operation.
         * @param LocalFile $file The file to edit.
         * @param string $value The line content.
         * @param string[] $originalLines The original file lines.
         * @return array{action: string, label: string, previewLines: array<int, string>} The operation metadata.
         */
        private function applyAppend (LocalFile $file, string $value, array $originalLines) : array {
            $file->appendLine($value);
            $lineNumber = count($originalLines) + 1;

            return [
                "action" => "append",
                "label" => "Append",
                "previewLines" => [$lineNumber . ": " . $value]
            ];
        }

        /**
         * Applies a delete operation.
         * @param LocalFile $file The file to edit.
         * @param string $value The delete expression.
         * @param string[] $originalLines The original file lines.
         * @return array{action: string, label: string, previewLines: array<int, string>} The operation metadata.
         */
        private function applyDelete (LocalFile $file, string $value, array $originalLines) : array {
            [$from, $to] = $this->parseDeletePayload($value);

            if ($from === $to) {
                $file->deleteLine($from);
            }
            else {
                $file->deleteLines($from, $to);
            }

            $previewLines = [];

            for ($line = $from; $line <= $to; $line++) {
                $previewLines[] = $line . ": " . rtrim($originalLines[$line - 1] ?? "", "\r\n");
            }

            return [
                "action" => "delete",
                "label" => "Delete",
                "previewLines" => $previewLines
            ];
        }

        /**
         * Applies an insert operation.
         * @param LocalFile $file The file to edit.
         * @param string $value The insert expression.
         * @return array{action: string, label: string, previewLines: array<int, string>} The operation metadata.
         */
        private function applyInsert (LocalFile $file, string $value) : array {
            [$line, $content] = $this->parseLineTextPayload($value, "insert");
            $file->insertLine($line, $content);

            return [
                "action" => "insert",
                "label" => "Insert",
                "previewLines" => [$line . ": " . $content]
            ];
        }

        /**
         * Applies a prepend operation.
         * @param LocalFile $file The file to edit.
         * @param string $value The line content.
         * @return array{action: string, label: string, previewLines: array<int, string>} The operation metadata.
         */
        private function applyPrepend (LocalFile $file, string $value) : array {
            $file->prependLine($value);

            return [
                "action" => "prepend",
                "label" => "Prepend",
                "previewLines" => ["1: " . $value]
            ];
        }

        /**
         * Applies a replace operation.
         * @param LocalFile $file The file to edit.
         * @param string $value The replace expression.
         * @return array{action: string, label: string, previewLines: array<int, string>} The operation metadata.
         */
        private function applyReplace (LocalFile $file, string $value) : array {
            [$line, $content] = $this->parseLineTextPayload($value, "replace");
            $file->replaceLine($line, $content);

            return [
                "action" => "replace",
                "label" => "Replace",
                "previewLines" => [$line . ": " . $content]
            ];
        }

        /**
         * Emits preview or success output for the selected operation.
         * @param array{action: string, label: string, previewLines: array<int, string>} $operation The operation metadata.
         */
        private function emitOutput (array $operation) : void {
            if ($this->dryRun) {
                echo "Would " . strtolower($operation["label"]) . " in {$this->path}." . PHP_EOL;

                if (!empty($operation["previewLines"])) {
                    echo implode(PHP_EOL, $operation["previewLines"]) . PHP_EOL;
                }

                return;
            }

            if ($this->quiet) {
                return;
            }

            $verb = match ($operation["action"]) {
                "append" => "Appended",
                "delete" => "Deleted",
                "insert" => "Inserted",
                "prepend" => "Prepended",
                default => "Replaced"
            };

            echo $verb . " line content in {$this->path}." . PHP_EOL;
        }

        /**
         * Resolves the single active mutation operation.
         * @throws InvalidArgumentException If zero or multiple mutation modes are selected.
         * @return array{0: string, 1: string} The active operation name and its payload.
         */
        private function getEffectiveOperation () : array {
            $operations = array_values(array_filter([
                $this->append !== null ? ["append", $this->append] : null,
                $this->delete !== null ? ["delete", $this->delete] : null,
                $this->insert !== null ? ["insert", $this->insert] : null,
                $this->prepend !== null ? ["prepend", $this->prepend] : null,
                $this->replace !== null ? ["replace", $this->replace] : null
            ]));

            if (count($operations) !== 1) {
                throw new InvalidArgumentException("Exactly one of --insert, --replace, --delete, --append, or --prepend must be supplied.");
            }

            return $operations[0];
        }

        /**
         * Parses a delete payload of either n or from:to.
         * @param string $value The raw payload.
         * @throws InvalidArgumentException If the payload is malformed.
         * @return array{0: int, 1: int} The inclusive line range.
         */
        private function parseDeletePayload (string $value) : array {
            if (preg_match('/^[1-9]\d*$/', $value)) {
                $line = (int) $value;
                return [$line, $line];
            }

            if (!preg_match('/^([1-9]\d*):([1-9]\d*)$/', $value, $matches)) {
                throw new InvalidArgumentException("The --delete option must use either n or from:to with positive integers.");
            }

            $from = (int) $matches[1];
            $to = (int) $matches[2];

            if ($from > $to) {
                throw new InvalidArgumentException("The --delete option must use an ascending inclusive range.");
            }

            return [$from, $to];
        }

        /**
         * Parses a line:text payload where the line number is one-based.
         * @param string $value The raw payload.
         * @param string $optionName The option name for validation messages.
         * @throws InvalidArgumentException If the payload is malformed.
         * @return array{0: int, 1: string} The parsed line number and text.
         */
        private function parseLineTextPayload (string $value, string $optionName) : array {
            $parts = explode(":", $value, 2);

            if (count($parts) !== 2 || !preg_match('/^[1-9]\d*$/', $parts[0])) {
                throw new InvalidArgumentException("The --{$optionName} option must use the form line:text with a positive line number.");
            }

            return [(int) $parts[0], $parts[1]];
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
         * Executes the line command.
         * @return int The exit code of the command.
         */
        public function run () : int {
            try {
                $file = $this->resolveFile();
                $originalLines = $file->getLines();
                $operation = $this->applyLineEdit($file, $originalLines);

                if (!$this->dryRun) {
                    $file->save();
                }

                $this->emitOutput($operation);

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