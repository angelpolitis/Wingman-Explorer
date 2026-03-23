<?php
    /**
     * Project Name:    Wingman Explorer - Console Scan Command Tests
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
    use Wingman\Explorer\Bridge\Console\Commands\ScanCommand;

    /**
     * Tests for Explorer's Console-backed scan command.
     */
    #[Requires(type: "class", value: Console::class, message: "Wingman Console is required for command bridge tests.")]
    class ConsoleScanCommandTest extends Test {
        /**
         * The temporary sandbox directory used for command tests.
         * @var string
         */
        private string $sandboxPath;

        /**
         * Creates a structured sandbox directory tree before each test.
         */
        protected function setUp () : void {
            $this->sandboxPath = dirname(__DIR__) . "/temp/explorer_console_scan_command_" . uniqid();
            mkdir($this->sandboxPath . "/sub", 0775, true);
            mkdir($this->sandboxPath . "/.hidden_dir", 0775, true);
            file_put_contents($this->sandboxPath . "/alpha.txt", "alpha");
            file_put_contents($this->sandboxPath . "/beta.php", "beta");
            file_put_contents($this->sandboxPath . "/.hidden", "hidden");
            file_put_contents($this->sandboxPath . "/sub/gamma.txt", "gamma");
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
         * Executes the scan command and captures its textual output.
         * @param string $arguments The command argument string.
         * @return array{code: int, output: string} The exit code and captured output.
         */
        private function executeCommand (string $arguments) : array {
            $command = new ScanCommand($arguments);
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
        #[Define(name: "Explorer Scan Command — Emits JSON Results", description: "The explorer:scan command emits structured JSON results for shallow file scans.")]
        public function testExplorerScanCommandEmitsJsonResults () : void {
            $result = $this->executeCommand($this->sandboxPath . " --adapter=local --format=json --target=file --depth=shallow --sort=name");
            $decoded = json_decode(trim($result["output"]), true);

            $this->assertEquals(0, $result["code"], "The command must succeed for a valid JSON scan.");
            $this->assertTrue(is_array($decoded), "The JSON output must decode into an array.");
            $this->assertCount(2, $decoded, "A shallow non-hidden file scan must return the two visible top-level files.");
            $this->assertEquals(["alpha.txt", "beta.php"], array_column($decoded, "name"), "The JSON payload must include the expected file names in sorted order.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Scan Command — Defaults To Local Adapter", description: "The explorer:scan command uses the local adapter when the adapter option is omitted.")]
        public function testExplorerScanCommandDefaultsToLocalAdapter () : void {
            $result = $this->executeCommand($this->sandboxPath . " --format=paths --target=file --depth=shallow --sort=name");
            $lines = array_values(array_filter(array_map("trim", explode(PHP_EOL, trim($result["output"])))));

            $this->assertEquals(0, $result["code"], "The command must default to the local adapter when no adapter option is supplied.");
            $this->assertEquals([
                $this->sandboxPath . "/alpha.txt",
                $this->sandboxPath . "/beta.php"
            ], $lines, "The default adapter must scan the local filesystem.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Scan Command — Emits Paths Output", description: "The explorer:scan command emits one path per line when format=paths is requested.")]
        public function testExplorerScanCommandEmitsPathsOutput () : void {
            $result = $this->executeCommand($this->sandboxPath . " --adapter=local --format=paths --target=file --depth=shallow --sort=name");
            $lines = array_values(array_filter(array_map("trim", explode(PHP_EOL, trim($result["output"])))));

            $this->assertEquals(0, $result["code"], "The command must succeed for path output.");
            $this->assertEquals([
                $this->sandboxPath . "/alpha.txt",
                $this->sandboxPath . "/beta.php"
            ], $lines, "The command must emit one matching path per line.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Scan Command — Rejects Invalid Adapter", description: "The explorer:scan command returns a validation error for unsupported adapter values.")]
        public function testExplorerScanCommandRejectsInvalidAdapter () : void {
            $result = $this->executeCommand($this->sandboxPath . " --adapter=s3");

            $this->assertEquals(2, $result["code"], "An unsupported adapter value must return validation exit code 2.");
            $this->assertTrue(str_contains($result["output"], "--adapter option must currently be local"), "The command must explain the unsupported adapter value.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Scan Command — Rejects Invalid Depth", description: "The explorer:scan command returns a validation error for unsupported depth values.")]
        public function testExplorerScanCommandRejectsInvalidDepth () : void {
            $result = $this->executeCommand($this->sandboxPath . " --depth=sideways");

            $this->assertEquals(2, $result["code"], "An unsupported depth value must return validation exit code 2.");
            $this->assertTrue(str_contains($result["output"], "--depth option must be shallow, default, or deep"), "The command must explain the invalid depth value.");
        }
    }
?>