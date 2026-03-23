<?php
    /**
     * Project Name:    Wingman Explorer - Console Search Command Tests
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
    use Wingman\Explorer\Bridge\Console\Commands\SearchCommand;

    /**
     * Tests for Explorer's Console-backed search command.
     */
    #[Requires(type: "class", value: Console::class, message: "Wingman Console is required for command bridge tests.")]
    class ConsoleSearchCommandTest extends Test {
        /**
         * The temporary sandbox directory used for command tests.
         * @var string
         */
        private string $sandboxPath;

        /**
         * Creates a structured sandbox directory tree before each test.
         */
        protected function setUp () : void {
            $this->sandboxPath = dirname(__DIR__) . "/temp/explorer_console_search_command_" . uniqid();
            mkdir($this->sandboxPath, 0775, true);
            file_put_contents($this->sandboxPath . "/alpha.txt", "alpha beta\nbeta gamma\nbeta\n");
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
         * Executes the search command and captures its textual output.
         * @param string $arguments The command argument string.
         * @return array{code: int, output: string} The exit code and captured output.
         */
        private function executeCommand (string $arguments) : array {
            $command = new SearchCommand($arguments);
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
        #[Define(name: "Explorer Search Command — Defaults To Local Adapter", description: "The explorer:search command uses the local adapter contract when the adapter option is omitted.")]
        public function testExplorerSearchCommandDefaultsToLocalAdapter () : void {
            $result = $this->executeCommand($this->sandboxPath . "/alpha.txt beta");

            $this->assertEquals(0, $result["code"], "The command must default to the local adapter when no adapter option is supplied.");
            $this->assertEquals("beta\n", $result["output"], "The default search mode must emit the first matching string.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Search Command — Emits All Regex Matches As JSON", description: "The explorer:search command emits structured JSON results for regex matches.")]
        public function testExplorerSearchCommandEmitsAllRegexMatchesAsJson () : void {
            $result = $this->executeCommand($this->sandboxPath . "/alpha.txt '/beta|gamma/' --regex --all --json");
            $decoded = json_decode(trim($result["output"]), true);

            $this->assertEquals(0, $result["code"], "The command must succeed for valid regex JSON output.");
            $this->assertEquals("matches", $decoded["mode"], "The JSON payload must describe the effective output mode.");
            $this->assertEquals(["beta", "beta", "gamma", "beta"], $decoded["results"], "The JSON payload must include all regex matches in order.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Search Command — Emits All Matching Line Numbers", description: "The explorer:search command can emit all matching one-based line numbers.")]
        public function testExplorerSearchCommandEmitsAllMatchingLineNumbers () : void {
            $result = $this->executeCommand($this->sandboxPath . "/alpha.txt beta --all --line-numbers");

            $this->assertEquals(0, $result["code"], "The command must succeed when emitting matching line numbers.");
            $this->assertEquals("1\n2\n3\n", $result["output"], "The line-number mode must emit every matching one-based line number.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Search Command — Emits Line And Offset Results", description: "The explorer:search command can combine line numbers and offsets in JSON output.")]
        public function testExplorerSearchCommandEmitsLineAndOffsetResults () : void {
            $result = $this->executeCommand($this->sandboxPath . "/alpha.txt beta --all --line-numbers --offsets --json");
            $decoded = json_decode(trim($result["output"]), true);

            $this->assertEquals(0, $result["code"], "The command must succeed when emitting combined line and offset results.");
            $this->assertEquals("line-offsets", $decoded["mode"], "The JSON payload must describe the combined line-offset mode.");
            $this->assertEquals([
                ["line" => 1, "offset" => 6],
                ["line" => 2, "offset" => 11],
                ["line" => 3, "offset" => 22]
            ], $decoded["results"], "The combined payload must preserve both line numbers and byte offsets for every match.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Search Command — Rejects Invalid Regex", description: "The explorer:search command returns a validation error for malformed regex patterns.")]
        public function testExplorerSearchCommandRejectsInvalidRegex () : void {
            $result = $this->executeCommand($this->sandboxPath . "/alpha.txt '/beta' --regex");

            $this->assertEquals(2, $result["code"], "A malformed regex pattern must return validation exit code 2.");
            $this->assertTrue(str_contains($result["output"], "valid PCRE pattern"), "The command must explain invalid regex input.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Search Command — Rejects Invalid Adapter", description: "The explorer:search command returns a validation error for unsupported adapter values.")]
        public function testExplorerSearchCommandRejectsInvalidAdapter () : void {
            $result = $this->executeCommand($this->sandboxPath . "/alpha.txt beta --adapter=s3");

            $this->assertEquals(2, $result["code"], "An unsupported adapter value must return validation exit code 2.");
            $this->assertTrue(str_contains($result["output"], "--adapter option must currently be local"), "The command must explain the unsupported adapter value.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Search Command — Emits Empty Json Results For No Match", description: "The explorer:search command emits an empty result set instead of failing when no match is found.")]
        public function testExplorerSearchCommandEmitsEmptyJsonResultsForNoMatch () : void {
            $result = $this->executeCommand($this->sandboxPath . "/alpha.txt zeta --all --json");
            $decoded = json_decode(trim($result["output"]), true);

            $this->assertEquals(0, $result["code"], "A valid search with no matches must still succeed.");
            $this->assertEquals([], $decoded["results"], "The JSON payload must contain an empty result set when nothing matches.");
        }
    }
?>