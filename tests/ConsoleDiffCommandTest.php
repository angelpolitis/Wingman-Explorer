<?php
    /**
     * Project Name:    Wingman Explorer - Console Diff Command Tests
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
    use Wingman\Explorer\Bridge\Console\Commands\DiffCommand;

    /**
     * Tests for Explorer's Console-backed diff command.
     */
    #[Requires(type: "class", value: Console::class, message: "Wingman Console is required for command bridge tests.")]
    class ConsoleDiffCommandTest extends Test {
        /**
         * The temporary sandbox directory used for command tests.
         * @var string
         */
        private string $sandboxPath;

        /**
         * Creates a structured sandbox directory tree before each test.
         */
        protected function setUp () : void {
            $this->sandboxPath = dirname(__DIR__) . "/temp/explorer_console_diff_command_" . uniqid();
            mkdir($this->sandboxPath, 0775, true);
            file_put_contents($this->sandboxPath . "/a.txt", "alpha\nbeta\ngamma\n");
            file_put_contents($this->sandboxPath . "/b.txt", "alpha\ntheta\ngamma\n");
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
         * Executes the diff command and captures its textual output.
         * @param string $arguments The command argument string.
         * @return array{code: int, output: string} The exit code and captured output.
         */
        private function executeCommand (string $arguments) : array {
            $command = new DiffCommand($arguments);
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
        #[Define(name: "Explorer Diff Command — Defaults To Local Adapter", description: "The explorer:diff command uses the local adapter contract when the adapter option is omitted.")]
        public function testExplorerDiffCommandDefaultsToLocalAdapter () : void {
            $result = $this->executeCommand($this->sandboxPath . "/a.txt " . $this->sandboxPath . "/b.txt");

            $this->assertEquals(0, $result["code"], "The command must default to the local adapter when no adapter option is supplied.");
            $this->assertTrue(str_contains($result["output"], "--- " . $this->sandboxPath . "/a.txt"), "Unified output must include the base file header.");
            $this->assertTrue(str_contains($result["output"], "+++ " . $this->sandboxPath . "/b.txt"), "Unified output must include the comparison file header.");
            $this->assertTrue(str_contains($result["output"], "-beta"), "Unified output must include removed lines with a minus prefix.");
            $this->assertTrue(str_contains($result["output"], "+theta"), "Unified output must include added lines with a plus prefix.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Diff Command — Emits Raw Json Hunks", description: "The explorer:diff command can emit the raw hunk structure as JSON.")]
        public function testExplorerDiffCommandEmitsRawJsonHunks () : void {
            $result = $this->executeCommand($this->sandboxPath . "/a.txt " . $this->sandboxPath . "/b.txt --format=json");
            $decoded = json_decode(trim($result["output"]), true);

            $this->assertEquals(0, $result["code"], "The command must succeed for valid JSON diff output.");
            $this->assertTrue(isset($decoded["hunks"]) && is_array($decoded["hunks"]), "The JSON output must expose a hunks array.");
            $this->assertEquals(["unchanged", "removed", "added", "unchanged", "unchanged"], array_column($decoded["hunks"], "operation"), "The JSON hunk sequence must reflect Explorer's ordered diff structure.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Diff Command — Rejects Invalid Format", description: "The explorer:diff command returns a validation error for unsupported output formats.")]
        public function testExplorerDiffCommandRejectsInvalidFormat () : void {
            $result = $this->executeCommand($this->sandboxPath . "/a.txt " . $this->sandboxPath . "/b.txt --format=table");

            $this->assertEquals(2, $result["code"], "An unsupported format must return validation exit code 2.");
            $this->assertTrue(str_contains($result["output"], "--format option must be unified or json"), "The command must explain the unsupported format.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Diff Command — Rejects Invalid Adapter", description: "The explorer:diff command returns a validation error for unsupported adapter values.")]
        public function testExplorerDiffCommandRejectsInvalidAdapter () : void {
            $result = $this->executeCommand($this->sandboxPath . "/a.txt " . $this->sandboxPath . "/b.txt --adapter=s3");

            $this->assertEquals(2, $result["code"], "An unsupported adapter value must return validation exit code 2.");
            $this->assertTrue(str_contains($result["output"], "--adapter option must currently be local"), "The command must explain the unsupported adapter value.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Diff Command — Respects Max Lines Limit", description: "The explorer:diff command fails cleanly when the configured in-memory line limit is exceeded.")]
        public function testExplorerDiffCommandRespectsMaxLinesLimit () : void {
            $result = $this->executeCommand($this->sandboxPath . "/a.txt " . $this->sandboxPath . "/b.txt --max-lines=1");

            $this->assertEquals(1, $result["code"], "Exceeding the configured line limit must return command failure exit code 1.");
            $this->assertTrue(str_contains($result["output"], "file too large for in-memory diff"), "The command must surface the underlying line-limit failure.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Diff Command — Rejects Invalid Max Lines", description: "The explorer:diff command returns a validation error for malformed max-lines values.")]
        public function testExplorerDiffCommandRejectsInvalidMaxLines () : void {
            $result = $this->executeCommand($this->sandboxPath . "/a.txt " . $this->sandboxPath . "/b.txt --max-lines=0");

            $this->assertEquals(2, $result["code"], "A malformed max-lines value must return validation exit code 2.");
            $this->assertTrue(str_contains($result["output"], "--max-lines option must be a positive integer"), "The command must explain the malformed max-lines value.");
        }
    }
?>