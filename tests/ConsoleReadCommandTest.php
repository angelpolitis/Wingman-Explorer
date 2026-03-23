<?php
    /**
     * Project Name:    Wingman Explorer - Console Read Command Tests
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
    use Wingman\Explorer\Bridge\Console\Commands\ReadCommand;

    /**
     * Tests for Explorer's Console-backed read command.
     */
    #[Requires(type: "class", value: Console::class, message: "Wingman Console is required for command bridge tests.")]
    class ConsoleReadCommandTest extends Test {
        /**
         * The temporary sandbox directory used for command tests.
         * @var string
         */
        private string $sandboxPath;

        /**
         * Creates a structured sandbox directory tree before each test.
         */
        protected function setUp () : void {
            $this->sandboxPath = dirname(__DIR__) . "/temp/explorer_console_read_command_" . uniqid();
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
         * Executes the read command and captures its textual output.
         * @param string $arguments The command argument string.
         * @return array{code: int, output: string} The exit code and captured output.
         */
        private function executeCommand (string $arguments) : array {
            $command = new ReadCommand($arguments);
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
        #[Define(name: "Explorer Read Command — Defaults To Full File Read", description: "The explorer:read command reads the entire file when no specific mode is selected.")]
        public function testExplorerReadCommandDefaultsToFullFileRead () : void {
            $result = $this->executeCommand($this->sandboxPath . "/alpha.txt");

            $this->assertEquals(0, $result["code"], "The command must succeed when reading the full file by default.");
            $this->assertEquals("alpha\nbeta\ngamma\n", $result["output"], "The default mode must emit the full file content unchanged.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Read Command — Reads A Single Line", description: "The explorer:read command can read a single one-based line.")]
        public function testExplorerReadCommandReadsSingleLine () : void {
            $result = $this->executeCommand($this->sandboxPath . "/alpha.txt --line=2");

            $this->assertEquals(0, $result["code"], "The command must succeed when reading a valid line.");
            $this->assertEquals("beta\n", $result["output"], "The single-line mode must preserve the line ending when present.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Read Command — Reads An Inclusive Line Range", description: "The explorer:read command can read an inclusive one-based line range.")]
        public function testExplorerReadCommandReadsInclusiveLineRange () : void {
            $result = $this->executeCommand($this->sandboxPath . "/alpha.txt --lines=2:3");

            $this->assertEquals(0, $result["code"], "The command must succeed when reading a valid line range.");
            $this->assertEquals("beta\ngamma\n", $result["output"], "The line-range mode must emit the requested inclusive lines.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Read Command — Reads An Exclusive Byte Range", description: "The explorer:read command can read a zero-based byte range with an exclusive end offset.")]
        public function testExplorerReadCommandReadsExclusiveByteRange () : void {
            $result = $this->executeCommand($this->sandboxPath . "/alpha.txt --range=0:5");

            $this->assertEquals(0, $result["code"], "The command must succeed when reading a valid byte range.");
            $this->assertEquals("alpha", $result["output"], "The byte-range mode must honour Explorer's inclusive-start, exclusive-end semantics.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Read Command — Streams Full File Content", description: "The explorer:read command can stream the full file to stdout.")]
        public function testExplorerReadCommandStreamsFullFileContent () : void {
            $result = $this->executeCommand($this->sandboxPath . "/alpha.txt --stream");

            $this->assertEquals(0, $result["code"], "The command must succeed when streaming a file.");
            $this->assertEquals("alpha\nbeta\ngamma\n", $result["output"], "The stream mode must emit the full file content unchanged.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Read Command — Rejects Mixed Modes", description: "The explorer:read command returns a validation error when multiple read modes are requested together.")]
        public function testExplorerReadCommandRejectsMixedModes () : void {
            $result = $this->executeCommand($this->sandboxPath . "/alpha.txt --line=1 --range=0:5");

            $this->assertEquals(2, $result["code"], "Mixed read modes must return validation exit code 2.");
            $this->assertTrue(str_contains($result["output"], "mutually exclusive"), "The command must explain the conflicting read modes.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Read Command — Rejects Invalid Adapter", description: "The explorer:read command returns a validation error for unsupported adapter values.")]
        public function testExplorerReadCommandRejectsInvalidAdapter () : void {
            $result = $this->executeCommand($this->sandboxPath . "/alpha.txt --adapter=s3");

            $this->assertEquals(2, $result["code"], "An unsupported adapter value must return validation exit code 2.");
            $this->assertTrue(str_contains($result["output"], "--adapter option must currently be local"), "The command must explain the unsupported adapter value.");
        }
    }
?>