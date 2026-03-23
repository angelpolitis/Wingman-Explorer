<?php
    /**
     * Project Name:    Wingman Explorer - Console Line Command Tests
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
    use Wingman\Explorer\Bridge\Console\Commands\LineCommand;

    /**
     * Tests for Explorer's Console-backed line command.
     */
    #[Requires(type: "class", value: Console::class, message: "Wingman Console is required for command bridge tests.")]
    class ConsoleLineCommandTest extends Test {
        /**
         * The temporary sandbox directory used for command tests.
         * @var string
         */
        private string $sandboxPath;

        /**
         * Creates a structured sandbox directory tree before each test.
         */
        protected function setUp () : void {
            $this->sandboxPath = dirname(__DIR__) . "/temp/explorer_console_line_command_" . uniqid();
            mkdir($this->sandboxPath, 0775, true);
            file_put_contents($this->sandboxPath . "/alpha.txt", "alpha\nbeta\ngamma\n");
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
         * Executes the line command and captures its textual output.
         * @param string $arguments The command argument string.
         * @return array{code: int, output: string} The exit code and captured output.
         */
        private function executeCommand (string $arguments) : array {
            $command = new LineCommand($arguments);
            $command->setConsole($this->createConsole());

            ob_start();
            $code = $command->run();
            $output = (string) ob_get_clean();

            return [
                "code" => $code,
                "output" => $output
            ];
        }

        #[Group("Console")]
        #[Define(name: "Explorer Line Command — Inserts Before A Line", description: "The explorer:line command can insert text before a one-based line number.")]
        public function testExplorerLineCommandInsertsBeforeALine () : void {
            $file = $this->sandboxPath . "/alpha.txt";
            $result = $this->executeCommand($file . " --insert='2:theta'");

            $this->assertEquals(0, $result["code"], "The command must succeed for a valid insert operation.");
            $this->assertEquals("alpha\ntheta\nbeta\ngamma\n", file_get_contents($file), "The insert mode must add the new line before the requested one-based line number.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Line Command — Replaces A Line", description: "The explorer:line command can replace a single one-based line.")]
        public function testExplorerLineCommandReplacesALine () : void {
            $file = $this->sandboxPath . "/alpha.txt";
            $result = $this->executeCommand($file . " --replace='2:theta'");

            $this->assertEquals(0, $result["code"], "The command must succeed for a valid line replacement.");
            $this->assertEquals("alpha\ntheta\ngamma\n", file_get_contents($file), "The replace mode must update only the requested one-based line.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Line Command — Deletes A Line Range", description: "The explorer:line command can delete an inclusive line range.")]
        public function testExplorerLineCommandDeletesALineRange () : void {
            $file = $this->sandboxPath . "/alpha.txt";
            $result = $this->executeCommand($file . " --delete=2:3");

            $this->assertEquals(0, $result["code"], "The command must succeed for a valid delete operation.");
            $this->assertEquals("alpha\n", file_get_contents($file), "The delete mode must remove the requested inclusive line range.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Line Command — Supports Append And Quiet", description: "The explorer:line command can append a line and suppress success output.")]
        public function testExplorerLineCommandSupportsAppendAndQuiet () : void {
            $file = $this->sandboxPath . "/alpha.txt";
            $result = $this->executeCommand($file . " --append='theta' --quiet");

            $this->assertEquals(0, $result["code"], "The command must succeed for a valid append operation.");
            $this->assertEquals("", $result["output"], "Quiet mode must suppress normal success output.");
            $this->assertEquals("alpha\nbeta\ngamma\ntheta\n", file_get_contents($file), "The append mode must add a new trailing line.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Line Command — Supports Dry Run Preview", description: "The explorer:line command can preview affected lines without persisting the change.")]
        public function testExplorerLineCommandSupportsDryRunPreview () : void {
            $file = $this->sandboxPath . "/alpha.txt";
            $original = file_get_contents($file);
            $result = $this->executeCommand($file . " --prepend='theta' --dry-run");

            $this->assertEquals(0, $result["code"], "The command must succeed for a valid dry-run line edit.");
            $this->assertTrue(str_contains($result["output"], "Would prepend"), "Dry-run mode must describe the pending line operation.");
            $this->assertTrue(str_contains($result["output"], "1: theta"), "Dry-run mode must preview the affected line content.");
            $this->assertEquals($original, file_get_contents($file), "Dry-run mode must not persist any change to disk.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Line Command — Rejects Multiple Mutation Modes", description: "The explorer:line command returns a validation error when multiple mutation modes are supplied.")]
        public function testExplorerLineCommandRejectsMultipleMutationModes () : void {
            $file = $this->sandboxPath . "/alpha.txt";
            $result = $this->executeCommand($file . " --append='theta' --prepend='omega'");

            $this->assertEquals(2, $result["code"], "Multiple mutation modes must return validation exit code 2.");
            $this->assertTrue(str_contains($result["output"], "Exactly one of --insert, --replace, --delete, --append, or --prepend"), "The command must explain conflicting mutation modes.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Line Command — Rejects Invalid Adapter", description: "The explorer:line command returns a validation error for unsupported adapter values.")]
        public function testExplorerLineCommandRejectsInvalidAdapter () : void {
            $file = $this->sandboxPath . "/alpha.txt";
            $result = $this->executeCommand($file . " --append='theta' --adapter=s3");

            $this->assertEquals(2, $result["code"], "An unsupported adapter value must return validation exit code 2.");
            $this->assertTrue(str_contains($result["output"], "--adapter option must currently be local"), "The command must explain the unsupported adapter value.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Line Command — Rejects Invalid Insert Payload", description: "The explorer:line command returns a validation error for malformed line:text payloads.")]
        public function testExplorerLineCommandRejectsInvalidInsertPayload () : void {
            $file = $this->sandboxPath . "/alpha.txt";
            $result = $this->executeCommand($file . " --insert='theta'");

            $this->assertEquals(2, $result["code"], "A malformed insert payload must return validation exit code 2.");
            $this->assertTrue(str_contains($result["output"], "--insert option must use the form line:text"), "The command must explain the malformed insert payload.");
        }
    }
?>