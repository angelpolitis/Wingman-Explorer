<?php
    /**
     * Project Name:    Wingman Explorer - Console Stat Command Tests
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
    use Wingman\Explorer\Bridge\Console\Commands\StatCommand;

    /**
     * Tests for Explorer's Console-backed stat command.
     */
    #[Requires(type: "class", value: Console::class, message: "Wingman Console is required for command bridge tests.")]
    class ConsoleStatCommandTest extends Test {
        /**
         * The temporary sandbox directory used for command tests.
         * @var string
         */
        private string $sandboxPath;

        /**
         * Creates a structured sandbox directory tree before each test.
         */
        protected function setUp () : void {
            $this->sandboxPath = dirname(__DIR__) . "/temp/explorer_console_stat_command_" . uniqid();
            mkdir($this->sandboxPath . "/sub", 0775, true);
            file_put_contents($this->sandboxPath . "/alpha.txt", "alpha");
            file_put_contents($this->sandboxPath . "/sub/beta.json", '{"beta":true}');
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
         * Executes the stat command and captures its textual output.
         * @param string $arguments The command argument string.
         * @return array{code: int, output: string} The exit code and captured output.
         */
        private function executeCommand (string $arguments) : array {
            $command = new StatCommand($arguments);
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
        #[Define(name: "Explorer Stat Command — Defaults To Local Adapter", description: "The explorer:stat command uses the local adapter contract when the adapter option is omitted.")]
        public function testExplorerStatCommandDefaultsToLocalAdapter () : void {
            $result = $this->executeCommand($this->sandboxPath . "/alpha.txt --format=json");
            $decoded = json_decode(trim($result["output"]), true);

            $this->assertEquals(0, $result["code"], "The command must default to the local adapter when no adapter option is supplied.");
            $this->assertEquals("file", $decoded["type"], "The payload must classify file resources correctly.");
            $this->assertEquals("alpha.txt", $decoded["name"], "The payload must include the inspected file name.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Stat Command — Emits File Hashes", description: "The explorer:stat command includes file hashes when the hashes flag is supplied.")]
        public function testExplorerStatCommandEmitsFileHashes () : void {
            $result = $this->executeCommand($this->sandboxPath . "/alpha.txt --adapter=local --hashes --format=json");
            $decoded = json_decode(trim($result["output"]), true);

            $this->assertEquals(0, $result["code"], "The command must succeed when hashes are requested for a file.");
            $this->assertEquals(md5("alpha"), $decoded["md5"], "The payload must include the file MD5 hash.");
            $this->assertEquals(sha1("alpha"), $decoded["sha1"], "The payload must include the file SHA1 hash.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Stat Command — Emits Directory Metadata", description: "The explorer:stat command emits structured metadata for directory resources.")]
        public function testExplorerStatCommandEmitsDirectoryMetadata () : void {
            $result = $this->executeCommand($this->sandboxPath . "/sub --adapter=local --format=json");
            $decoded = json_decode(trim($result["output"]), true);

            $this->assertEquals(0, $result["code"], "The command must succeed when inspecting a directory.");
            $this->assertEquals("dir", $decoded["type"], "The payload must classify directory resources correctly.");
            $this->assertArrayHasKey("modified", $decoded, "The payload must include the normalised modified timestamp.");
            $this->assertTrue(!isset($decoded["md5"]) && !isset($decoded["sha1"]), "Directory payloads must not include file hashes by default.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Stat Command — Rejects Directory Hashes", description: "The explorer:stat command returns a validation error when hashes are requested for a directory.")]
        public function testExplorerStatCommandRejectsDirectoryHashes () : void {
            $result = $this->executeCommand($this->sandboxPath . "/sub --hashes --format=json");

            $this->assertEquals(2, $result["code"], "Requesting hashes for a directory must return validation exit code 2.");
            $this->assertTrue(str_contains($result["output"], "can only be used with files"), "The command must explain that hashes are file-only.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Stat Command — Rejects Invalid Adapter", description: "The explorer:stat command returns a validation error for unsupported adapter values.")]
        public function testExplorerStatCommandRejectsInvalidAdapter () : void {
            $result = $this->executeCommand($this->sandboxPath . "/alpha.txt --adapter=s3 --format=json");

            $this->assertEquals(2, $result["code"], "An unsupported adapter value must return validation exit code 2.");
            $this->assertTrue(str_contains($result["output"], "--adapter option must currently be local"), "The command must explain the unsupported adapter value.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Stat Command — Rejects Invalid Format", description: "The explorer:stat command returns a validation error for unsupported output formats.")]
        public function testExplorerStatCommandRejectsInvalidFormat () : void {
            $result = $this->executeCommand($this->sandboxPath . "/alpha.txt --format=paths");

            $this->assertEquals(2, $result["code"], "An unsupported format must return validation exit code 2.");
            $this->assertTrue(str_contains($result["output"], "--format option must be table or json"), "The command must explain the unsupported format.");
        }
    }
?>