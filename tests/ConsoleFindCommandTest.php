<?php
    /**
     * Project Name:    Wingman Explorer - Console Find Command Tests
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
    use Wingman\Explorer\Bridge\Console\Commands\FindCommand;

    /**
     * Tests for Explorer's Console-backed find command.
     */
    #[Requires(type: "class", value: Console::class, message: "Wingman Console is required for command bridge tests.")]
    class ConsoleFindCommandTest extends Test {
        /**
         * The temporary sandbox directory used for command tests.
         * @var string
         */
        private string $sandboxPath;

        /**
         * Creates a structured sandbox directory tree before each test.
         */
        protected function setUp () : void {
            $this->sandboxPath = dirname(__DIR__) . "/temp/explorer_console_find_command_" . uniqid();
            mkdir($this->sandboxPath . "/sub", 0775, true);
            mkdir($this->sandboxPath . "/logs", 0775, true);
            file_put_contents($this->sandboxPath . "/alpha.txt", "alpha");
            file_put_contents($this->sandboxPath . "/beta.php", "beta");
            file_put_contents($this->sandboxPath . "/sub/gamma.txt", "gamma");
            file_put_contents($this->sandboxPath . "/sub/delta.php", "delta");
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
         * Executes the find command and captures its textual output.
         * @param string $arguments The command argument string.
         * @return array{code: int, output: string} The exit code and captured output.
         */
        private function executeCommand (string $arguments) : array {
            $command = new FindCommand($arguments);
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
        #[Define(name: "Explorer Find Command — Defaults To Local Adapter", description: "The explorer:find command uses the local adapter contract when the adapter option is omitted.")]
        public function testExplorerFindCommandDefaultsToLocalAdapter () : void {
            $result = $this->executeCommand($this->sandboxPath . " *.php");
            $lines = array_values(array_filter(array_map("trim", explode(PHP_EOL, trim($result["output"])))));

            $this->assertEquals(0, $result["code"], "The command must default to the local adapter when no adapter option is supplied.");
            $this->assertEquals([
                $this->sandboxPath . "/beta.php",
                $this->sandboxPath . "/sub/delta.php"
            ], $lines, "The default adapter must search the local filesystem recursively.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Find Command — Emits JSON Results", description: "The explorer:find command emits structured JSON output for matching resources.")]
        public function testExplorerFindCommandEmitsJsonResults () : void {
            $result = $this->executeCommand($this->sandboxPath . " *.txt --adapter=local --format=json");
            $decoded = json_decode(trim($result["output"]), true);

            $this->assertEquals(0, $result["code"], "The command must succeed for valid JSON output.");
            $this->assertTrue(is_array($decoded), "The JSON output must decode into an array.");
            $this->assertEquals(["alpha.txt", "gamma.txt"], array_column($decoded, "name"), "The JSON payload must include the expected matching file names.");
            $this->assertEquals(["file", "file"], array_column($decoded, "type"), "The JSON payload must classify file results correctly.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Find Command — Includes Directories When Requested", description: "The explorer:find command includes matching directories when the dirs flag is supplied.")]
        public function testExplorerFindCommandIncludesDirectoriesWhenRequested () : void {
            $result = $this->executeCommand($this->sandboxPath . " * --adapter=local --dirs --format=json");
            $decoded = json_decode(trim($result["output"]), true);
            $names = array_column($decoded, "name");

            $this->assertEquals(0, $result["code"], "The command must succeed when directories are requested.");
            $this->assertContains("logs", $names, "The result set must include matching directories when --dirs is supplied.");
            $this->assertContains("sub", $names, "Nested directory results must remain eligible when --dirs is supplied.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Find Command — Rejects Invalid Adapter", description: "The explorer:find command returns a validation error for unsupported adapter values.")]
        public function testExplorerFindCommandRejectsInvalidAdapter () : void {
            $result = $this->executeCommand($this->sandboxPath . " *.php --adapter=s3");

            $this->assertEquals(2, $result["code"], "An unsupported adapter value must return validation exit code 2.");
            $this->assertTrue(str_contains($result["output"], "--adapter option must currently be local"), "The command must explain the unsupported adapter value.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Find Command — Rejects Invalid Format", description: "The explorer:find command returns a validation error for unsupported output formats.")]
        public function testExplorerFindCommandRejectsInvalidFormat () : void {
            $result = $this->executeCommand($this->sandboxPath . " *.php --format=table");

            $this->assertEquals(2, $result["code"], "An unsupported format must return validation exit code 2.");
            $this->assertTrue(str_contains($result["output"], "--format option must be paths or json"), "The command must explain the unsupported format.");
        }
    }
?>