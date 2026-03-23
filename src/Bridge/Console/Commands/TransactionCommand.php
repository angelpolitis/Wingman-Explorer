<?php
    /**
     * Project Name:    Wingman Explorer - Console Transaction Command
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
    use RuntimeException;
    use Throwable;
    use Wingman\Console\Attributes\Argument;
    use Wingman\Console\Attributes\Command as Cmd;
    use Wingman\Console\Attributes\Flag;
    use Wingman\Console\Attributes\Option;
    use Wingman\Console\Command;
    use Wingman\Explorer\Adapters\LocalAdapter;
    use Wingman\Explorer\FilesystemTransaction;

    /**
     * Executes a validated filesystem transaction plan through Explorer.
     *
     * The v1 command accepts JSON plan files only. It validates the complete
     * plan before any filesystem mutations are queued, then either renders the
     * normalised steps in dry-run mode or commits them atomically through
     * FilesystemTransaction. Execution uses Explorer's local adapter directly
     * because the Console bridge has not yet generalised adapter configuration
     * for transactional workflows.
     *
     * @package Wingman\Explorer\Bridge\Console\Commands
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    #[Cmd(name: "explorer:transaction", description: "Executes a validated filesystem transaction plan.")]
    class TransactionCommand extends Command {
        /**
         * Whether the plan should only be validated and rendered.
         * @var bool
         */
        #[Flag(name: "dry-run", description: "Validate and print the plan without executing it")]
        protected bool $dryRun = false;

        /**
         * The output format.
         * @var string
         */
        #[Option(name: "format", description: "The output format: text or json")]
        protected string $format = "text";

        /**
         * The transaction plan file.
         * @var string
         */
        #[Argument(index: 0, description: "The JSON transaction plan file")]
        protected string $planFile;

        /**
         * Whether each step should be printed in text output.
         * @var bool
         */
        #[Flag(name: "verbose", description: "Print each step as it executes")]
        protected bool $verbose = false;

        /**
         * Applies a normalised step to a transaction builder.
         * @param FilesystemTransaction $transaction The transaction builder.
         * @param array<string, mixed> $step The normalised step definition.
         */
        private function applyStep (FilesystemTransaction $transaction, array $step) : void {
            match ($step["operation"]) {
                "copyFile" => $transaction->copyFile((string) $step["source"], (string) $step["destination"]),
                "createDirectory" => $transaction->createDirectory((string) $step["path"], (bool) $step["recursive"], (int) $step["permissions"]),
                "createFile" => $transaction->createFile((string) $step["path"], (string) $step["content"]),
                "deleteFile" => $transaction->deleteFile((string) $step["path"]),
                "moveFile" => $transaction->moveFile((string) $step["source"], (string) $step["destination"]),
                "writeFile" => $transaction->writeFile((string) $step["path"], (string) $step["content"])
            };
        }

        /**
         * Builds and optionally commits the filesystem transaction.
         * @param array<int, array<string, mixed>> $steps The validated steps.
         */
        private function executeTransaction (array $steps) : void {
            $transaction = new FilesystemTransaction(new LocalAdapter());

            foreach ($steps as $step) {
                $this->applyStep($transaction, $step);
            }

            if ($this->dryRun) {
                return;
            }

            $transaction->commit();
        }

        /**
         * Resolves the effective output format.
         * @throws InvalidArgumentException If the format is unsupported.
         * @return string The normalised output format.
         */
        private function getEffectiveFormat () : string {
            $format = strtolower(trim($this->format));

            if (!in_array($format, ["json", "text"], true)) {
                throw new InvalidArgumentException("The --format option must be either text or json.");
            }

            return $format;
        }

        /**
         * Reads and decodes the JSON transaction plan file.
         * @throws InvalidArgumentException If the file does not contain valid JSON.
         * @throws RuntimeException If the file does not exist or cannot be read.
         * @return mixed The decoded plan payload.
         */
        private function loadPlanPayload () : mixed {
            if (!is_file($this->planFile)) {
                throw new RuntimeException("The path '{$this->planFile}' does not exist or is not a file.");
            }

            $content = file_get_contents($this->planFile);

            if ($content === false) {
                throw new RuntimeException("The plan file '{$this->planFile}' could not be read.");
            }

            if (trim($content) === "") {
                throw new InvalidArgumentException("The transaction plan file must not be empty.");
            }

            try {
                return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            }
            catch (JsonException $e) {
                throw new InvalidArgumentException("The transaction plan file must contain valid JSON.", 0, $e);
            }
        }

        /**
         * Normalises a boolean field from a step payload.
         * @param array<string, mixed> $step The raw step.
         * @param string $field The field name.
         * @param bool $default The default value.
         * @param int $index The step index.
         * @throws InvalidArgumentException If the field is not boolean-like.
         * @return bool The normalised boolean value.
         */
        private function normaliseBooleanField (array $step, string $field, bool $default, int $index) : bool {
            if (!array_key_exists($field, $step)) {
                return $default;
            }

            $value = $step[$field];

            if (is_bool($value)) {
                return $value;
            }

            if (is_string($value)) {
                return match (strtolower(trim($value))) {
                    "0", "false", "no" => false,
                    "1", "true", "yes" => true,
                    default => throw new InvalidArgumentException("Transaction step " . ($index + 1) . " field '{$field}' must be boolean.")
                };
            }

            throw new InvalidArgumentException("Transaction step " . ($index + 1) . " field '{$field}' must be boolean.");
        }

        /**
         * Normalises the raw plan payload into an ordered step list.
         * @param mixed $payload The decoded plan payload.
         * @throws InvalidArgumentException If the plan structure is invalid.
         * @return array<int, array<string, mixed>> The normalised step list.
         */
        private function normalisePlan (mixed $payload) : array {
            if (!is_array($payload)) {
                throw new InvalidArgumentException("The transaction plan must decode to an array or an object with a steps array.");
            }

            $steps = array_key_exists("steps", $payload) ? $payload["steps"] : $payload;

            if (!is_array($steps) || $steps === []) {
                throw new InvalidArgumentException("The transaction plan must define at least one step.");
            }

            $normalised = [];

            foreach ($steps as $index => $step) {
                $normalised[] = $this->normaliseStep($step, (int) $index);
            }

            return $normalised;
        }

        /**
         * Normalises a permissions field from a step payload.
         * @param array<string, mixed> $step The raw step.
         * @param string $field The field name.
         * @param int $default The default permissions.
         * @param int $index The step index.
         * @throws InvalidArgumentException If the field is not an integer or octal-like string.
         * @return int The normalised permissions value.
         */
        private function normalisePermissionsField (array $step, string $field, int $default, int $index) : int {
            if (!array_key_exists($field, $step)) {
                return $default;
            }

            $value = $step[$field];

            if (is_int($value)) {
                return $value;
            }

            if (is_string($value) && preg_match('/^[0-7]{3,4}$/', trim($value)) === 1) {
                return octdec(trim($value));
            }

            throw new InvalidArgumentException("Transaction step " . ($index + 1) . " field '{$field}' must be an integer or an octal permission string.");
        }

        /**
         * Normalises an optional string field from a step payload.
         * @param array<string, mixed> $step The raw step.
         * @param string $field The field name.
         * @param string $default The default value.
         * @param int $index The step index.
         * @throws InvalidArgumentException If the field exists but is not a string.
         * @return string The normalised string value.
         */
        private function normaliseStringField (array $step, string $field, string $default, int $index) : string {
            if (!array_key_exists($field, $step)) {
                return $default;
            }

            if (!is_string($step[$field])) {
                throw new InvalidArgumentException("Transaction step " . ($index + 1) . " field '{$field}' must be a string.");
            }

            return $step[$field];
        }

        /**
         * Normalises a single transaction step.
         * @param mixed $step The raw step payload.
         * @param int $index The zero-based step index.
         * @throws InvalidArgumentException If the step is malformed.
         * @return array<string, mixed> The normalised step.
         */
        private function normaliseStep (mixed $step, int $index) : array {
            if (!is_array($step)) {
                throw new InvalidArgumentException("Transaction step " . ($index + 1) . " must be an object.");
            }

            $operation = $step["operation"] ?? $step["op"] ?? null;

            if (!is_string($operation) || trim($operation) === "") {
                throw new InvalidArgumentException("Transaction step " . ($index + 1) . " must define an operation.");
            }

            return match ($operation) {
                "copyFile" => [
                    "operation" => "copyFile",
                    "source" => $this->requireStringField($step, "source", $index),
                    "destination" => $this->requireStringField($step, "destination", $index)
                ],
                "createDirectory" => [
                    "operation" => "createDirectory",
                    "path" => $this->requireStringField($step, "path", $index),
                    "recursive" => $this->normaliseBooleanField($step, "recursive", false, $index),
                    "permissions" => $this->normalisePermissionsField($step, "permissions", 0775, $index)
                ],
                "createFile" => [
                    "operation" => "createFile",
                    "path" => $this->requireStringField($step, "path", $index),
                    "content" => $this->normaliseStringField($step, "content", "", $index)
                ],
                "deleteFile" => [
                    "operation" => "deleteFile",
                    "path" => $this->requireStringField($step, "path", $index)
                ],
                "moveFile" => [
                    "operation" => "moveFile",
                    "source" => $this->requireStringField($step, "source", $index),
                    "destination" => $this->requireStringField($step, "destination", $index)
                ],
                "writeFile" => [
                    "operation" => "writeFile",
                    "path" => $this->requireStringField($step, "path", $index),
                    "content" => $this->requireStringField($step, "content", $index)
                ],
                default => throw new InvalidArgumentException("Transaction step " . ($index + 1) . " uses an unsupported operation '{$operation}'.")
            };
        }

        /**
         * Renders a machine-readable transaction result.
         * @param bool $success Whether the command succeeded.
         * @param array<int, array<string, mixed>> $steps The normalised steps.
         * @param string|null $error The failure message when present.
         */
        private function renderJsonResult (bool $success, array $steps, ?string $error = null) : void {
            echo json_encode([
                "dryRun" => $this->dryRun,
                "operations" => count($steps),
                "planFile" => $this->planFile,
                "steps" => $steps,
                "success" => $success,
                "error" => $error
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        }

        /**
         * Renders the transaction result in text form.
         * @param array<int, array<string, mixed>> $steps The normalised steps.
         */
        private function renderTextResult (array $steps) : void {
            $count = count($steps);

            if ($this->dryRun) {
                echo "Validated transaction plan with {$count} step(s)." . PHP_EOL;
                foreach ($steps as $index => $step) {
                    echo "Step " . ($index + 1) . ": " . $this->summariseStep($step) . PHP_EOL;
                }
                return;
            }

            if ($this->verbose) {
                foreach ($steps as $index => $step) {
                    echo "Step " . ($index + 1) . ": " . $this->summariseStep($step) . PHP_EOL;
                }
            }

            echo "Committed transaction with {$count} step(s)." . PHP_EOL;
        }

        /**
         * Resolves a required string field from a step payload.
         * @param array<string, mixed> $step The raw step.
         * @param string $field The field name.
         * @param int $index The step index.
         * @throws InvalidArgumentException If the field is missing or invalid.
         * @return string The normalised string value.
         */
        private function requireStringField (array $step, string $field, int $index) : string {
            $value = $step[$field] ?? null;

            if (!is_string($value) || trim($value) === "") {
                throw new InvalidArgumentException("Transaction step " . ($index + 1) . " must define a non-empty '{$field}' field.");
            }

            return $value;
        }

        /**
         * Summarises a normalised step in one text line.
         * @param array<string, mixed> $step The normalised step.
         * @return string The step summary.
         */
        private function summariseStep (array $step) : string {
            return match ($step["operation"]) {
                "copyFile" => "copyFile " . $step["source"] . " -> " . $step["destination"],
                "createDirectory" => "createDirectory " . $step["path"],
                "createFile" => "createFile " . $step["path"],
                "deleteFile" => "deleteFile " . $step["path"],
                "moveFile" => "moveFile " . $step["source"] . " -> " . $step["destination"],
                "writeFile" => "writeFile " . $step["path"]
            };
        }

        /**
         * Executes the transaction command.
         * @return int The exit code of the command.
         */
        public function run () : int {
            $steps = [];
            $format = "text";

            try {
                $format = $this->getEffectiveFormat();
                $steps = $this->normalisePlan($this->loadPlanPayload());
                $this->executeTransaction($steps);

                if ($format === "json") {
                    $this->renderJsonResult(true, $steps);
                }
                else {
                    $this->renderTextResult($steps);
                }

                return 0;
            }
            catch (InvalidArgumentException $e) {
                if ($format === "json") {
                    $this->renderJsonResult(false, $steps, $e->getMessage());
                }
                else {
                    $this->console->error($e->getMessage());
                }
                return 2;
            }
            catch (Throwable $e) {
                if ($format === "json") {
                    $this->renderJsonResult(false, $steps, $e->getMessage());
                }
                else {
                    $this->console->error($e->getMessage());
                }
                return 1;
            }
        }
    }
?>