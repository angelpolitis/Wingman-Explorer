<?php
    /**
     * Project Name:    Wingman Explorer - Console Transaction Command Tests
     * Created by:      Angel Politis
     * Creation Date:   Mar 23 2026
     * Last Modified:   Mar 23 2026
     * 
     * Copyright (c) 2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer.Tests namespace.
    namespace Wingman\Explorer\Tests;

    # Import the following classes to the current scope.
    use Wingman\Argus\Attributes\Define;
    use Wingman\Argus\Attributes\Group;
    use Wingman\Argus\Attributes\Requires;
    use Wingman\Argus\Test;
    use Wingman\Console\Console;
    use Wingman\Explorer\Bridge\Console\Commands\TransactionCommand;

    /**
     * Tests for Explorer's Console-backed transaction command.
     */
    #[Requires(type: "class", value: Console::class, message: "Wingman Console is required for command bridge tests.")]
    class ConsoleTransactionCommandTest extends Test {
        /**
         * The temporary sandbox directory used for command tests.
         * @var string
         */
        private string $sandboxPath;

        /**
         * Recursively removes a directory's contents.
         * @param string $directory The directory to empty.
         */
        private function cleanDirectory (string $directory) : void {
            foreach (scandir($directory) as $entry) {
                if ($entry === "." || $entry === "..") continue;

                $path = $directory . "/" . $entry;

                if (is_dir($path)) {
                    $this->cleanDirectory($path);
                    @rmdir($path);
                    continue;
                }

                @unlink($path);
            }
        }

        /**
         * Creates a Console instance suitable for deterministic command testing.
         * @return Console The configured console instance.
         */
        private function createConsole () : Console {
            return new Console([
                "coloursEnabled" => false,
                "iconsEnabled" => false,
                "logging" => false,
                "verbose" => false
            ]);
        }

        /**
         * Executes the transaction command and captures its textual output.
         * @param string $arguments The command argument string.
         * @return array{code: int, output: string} The exit code and captured output.
         */
        private function executeCommand (string $arguments) : array {
            $command = new TransactionCommand($arguments);
            $command->setConsole($this->createConsole());

            ob_start();
            $code = $command->run();
            $output = (string) ob_get_clean();

            return [
                "code" => $code,
                "output" => $output
            ];
        }

        /**
         * Creates a plan file in the sandbox.
         * @param array<mixed> $plan The plan payload.
         * @param string $name The file name.
         * @return string The absolute plan path.
         */
        private function writePlan (array $plan, string $name = "plan.json") : string {
            $path = $this->sandboxPath . "/" . $name;
            file_put_contents($path, json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return $path;
        }

        /**
         * Creates the sandbox directory before each test.
         */
        protected function setUp () : void {
            $this->sandboxPath = dirname(__DIR__) . "/temp/explorer_console_transaction_command_" . uniqid();
            mkdir($this->sandboxPath, 0775, true);
        }

        /**
         * Removes the sandbox directory and its contents after each test.
         */
        protected function tearDown () : void {
            if (is_dir($this->sandboxPath)) {
                $this->cleanDirectory($this->sandboxPath);
                @rmdir($this->sandboxPath);
            }
        }

        #[Group("Console")]
        #[Define(name: "Explorer Transaction Command — Executes Valid Plan", description: "The explorer:transaction command commits a validated transaction plan atomically.")]
        public function testExplorerTransactionCommandExecutesValidPlan () : void {
            $directory = $this->sandboxPath . "/nested";
            $file = $directory . "/config.txt";
            $planPath = $this->writePlan([
                ["operation" => "createDirectory", "path" => $directory, "recursive" => true],
                ["operation" => "createFile", "path" => $file, "content" => "alpha"],
                ["operation" => "writeFile", "path" => $file, "content" => "beta"]
            ]);

            $result = $this->executeCommand($planPath);

            $this->assertEquals(0, $result["code"], "The command must succeed for a valid transaction plan.");
            $this->assertTrue(str_contains($result["output"], "Committed transaction with 3 step(s)."), "The command must report the committed step count.");
            $this->assertTrue(is_dir($directory), "The transaction must create directories from the plan.");
            $this->assertEquals("beta", (string) file_get_contents($file), "The transaction must commit queued file mutations in order.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Transaction Command — Dry Run Does Not Mutate", description: "The explorer:transaction command validates and renders its plan without mutating the filesystem when dry-run is enabled.")]
        public function testExplorerTransactionCommandDryRunDoesNotMutate () : void {
            $directory = $this->sandboxPath . "/preview";
            $file = $directory . "/config.txt";
            $planPath = $this->writePlan([
                ["operation" => "createDirectory", "path" => $directory, "recursive" => true],
                ["operation" => "createFile", "path" => $file, "content" => "alpha"]
            ]);

            $result = $this->executeCommand($planPath . " --dry-run");

            $this->assertEquals(0, $result["code"], "Dry-run must still succeed for a valid transaction plan.");
            $this->assertTrue(str_contains($result["output"], "Validated transaction plan with 2 step(s)."), "Dry-run must report the validated step count.");
            $this->assertTrue(str_contains($result["output"], "Step 1: createDirectory {$directory}"), "Dry-run text output must render normalised step summaries.");
            $this->assertTrue(!file_exists($file), "Dry-run must not create files from the plan.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Transaction Command — Renders Json Result", description: "The explorer:transaction command can emit a machine-readable JSON result payload.")]
        public function testExplorerTransactionCommandRendersJsonResult () : void {
            $file = $this->sandboxPath . "/config.txt";
            $planPath = $this->writePlan([
                ["operation" => "createFile", "path" => $file, "content" => "alpha"]
            ]);

            $result = $this->executeCommand($planPath . " --dry-run --format=json");
            $payload = json_decode($result["output"], true);

            $this->assertEquals(0, $result["code"], "JSON dry-run output must still return success for a valid plan.");
            $this->assertTrue(is_array($payload), "The JSON output must decode to an array.");
            $this->assertTrue(($payload["dryRun"] ?? false) === true, "The JSON payload must report dry-run mode.");
            $this->assertTrue(($payload["operations"] ?? null) === 1, "The JSON payload must report the normalised operation count.");
            $this->assertTrue(($payload["success"] ?? false) === true, "The JSON payload must report success.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Transaction Command — Verbose Execution Prints Steps", description: "The explorer:transaction command renders each step in text mode when verbose output is enabled.")]
        public function testExplorerTransactionCommandVerboseExecutionPrintsSteps () : void {
            $source = $this->sandboxPath . "/source.txt";
            $destination = $this->sandboxPath . "/copied.txt";
            file_put_contents($source, "copy me");

            $planPath = $this->writePlan([
                ["operation" => "copyFile", "source" => $source, "destination" => $destination]
            ]);

            $result = $this->executeCommand($planPath . " --verbose");

            $this->assertEquals(0, $result["code"], "Verbose execution must not change command success.");
            $this->assertTrue(str_contains($result["output"], "Step 1: copyFile {$source} -> {$destination}"), "Verbose text output must include each executed step summary.");
            $this->assertEquals("copy me", (string) file_get_contents($destination), "Verbose execution must still commit the transaction.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Transaction Command — Rejects Malformed Json", description: "The explorer:transaction command returns a validation error when the plan file is not valid JSON.")]
        public function testExplorerTransactionCommandRejectsMalformedJson () : void {
            $planPath = $this->sandboxPath . "/invalid.json";
            file_put_contents($planPath, "{invalid}");

            $result = $this->executeCommand($planPath);

            $this->assertEquals(2, $result["code"], "Malformed JSON plans must return validation exit code 2.");
            $this->assertTrue(str_contains($result["output"], "must contain valid JSON"), "The command must explain malformed JSON plans.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Transaction Command — Rejects Unsupported Operation", description: "The explorer:transaction command returns a validation error when a plan step uses an unsupported operation.")]
        public function testExplorerTransactionCommandRejectsUnsupportedOperation () : void {
            $planPath = $this->writePlan([
                ["operation" => "renameDirectory", "path" => $this->sandboxPath . "/dir"]
            ]);

            $result = $this->executeCommand($planPath);

            $this->assertEquals(2, $result["code"], "Unsupported operations must return validation exit code 2.");
            $this->assertTrue(str_contains($result["output"], "unsupported operation"), "The command must explain unsupported plan operations.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Transaction Command — Rolls Back On Failure", description: "The explorer:transaction command rolls back earlier mutations when a later transaction step fails.")]
        public function testExplorerTransactionCommandRollsBackOnFailure () : void {
            $created = $this->sandboxPath . "/created.txt";
            $planPath = $this->writePlan([
                ["operation" => "createFile", "path" => $created, "content" => "alpha"],
                ["operation" => "moveFile", "source" => $this->sandboxPath . "/missing.txt", "destination" => $this->sandboxPath . "/moved.txt"]
            ]);

            $result = $this->executeCommand($planPath);

            $this->assertEquals(1, $result["code"], "A failing transaction step must return command exit code 1.");
            $this->assertTrue(str_contains($result["output"], "Move failed"), "The command must surface the underlying transaction failure.");
            $this->assertTrue(!file_exists($created), "A failed transaction must roll back earlier committed mutations.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Transaction Command — Rejects Invalid Format", description: "The explorer:transaction command returns a validation error for unsupported output formats.")]
        public function testExplorerTransactionCommandRejectsInvalidFormat () : void {
            $planPath = $this->writePlan([
                ["operation" => "createFile", "path" => $this->sandboxPath . "/file.txt", "content" => "alpha"]
            ]);

            $result = $this->executeCommand($planPath . " --format=table");

            $this->assertEquals(2, $result["code"], "Unsupported output formats must return validation exit code 2.");
            $this->assertTrue(str_contains($result["output"], "must be either text or json"), "The command must explain unsupported output formats.");
        }
    }
?>