<?php
    /**
     * Project Name:    Wingman Explorer - Console List Command Tests
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
    use Wingman\Explorer\Bridge\Console\Commands\ListCommand;

    /**
     * Tests for Explorer's Console-backed list command.
     */
    #[Requires(type: "class", value: Console::class, message: "Wingman Console is required for command bridge tests.")]
    class ConsoleListCommandTest extends Test {
        /**
         * The temporary sandbox directory used for command tests.
         * @var string
         */
        private string $sandboxPath;

        /**
         * Creates a structured sandbox directory tree before each test.
         */
        protected function setUp () : void {
            $this->sandboxPath = dirname(__DIR__) . "/temp/explorer_console_list_command_" . uniqid();
            mkdir($this->sandboxPath . "/sub/deeper", 0775, true);
            mkdir($this->sandboxPath . "/logs", 0775, true);
            file_put_contents($this->sandboxPath . "/alpha.txt", "alpha");
            file_put_contents($this->sandboxPath . "/beta.php", "beta");
            file_put_contents($this->sandboxPath . "/sub/gamma.txt", "gamma");
            file_put_contents($this->sandboxPath . "/sub/deeper/delta.json", "delta");
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
         * Executes the list command and captures its textual output.
         * @param string $arguments The command argument string.
         * @return array{code: int, output: string} The exit code and captured output.
         */
        private function executeCommand (string $arguments) : array {
            $command = new ListCommand($arguments);
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
        #[Define(name: "Explorer List Command — Defaults To Local Adapter", description: "The explorer:list command uses the local adapter contract when the adapter option is omitted.")]
        public function testExplorerListCommandDefaultsToLocalAdapter () : void {
            $result = $this->executeCommand($this->sandboxPath . " --format=json");
            $decoded = json_decode(trim($result["output"]), true);
            $names = array_column($decoded, "name");

            $this->assertEquals(0, $result["code"], "The command must default to the local adapter when no adapter option is supplied.");
            $this->assertEquals(["logs", "sub", "alpha.txt", "beta.php"], $names, "The default listing must include the immediate child resources in Explorer's sorted order.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer List Command — Filters Files Only", description: "The explorer:list command can restrict results to files only.")]
        public function testExplorerListCommandFiltersFilesOnly () : void {
            $result = $this->executeCommand($this->sandboxPath . " --adapter=local --files-only --format=json");
            $decoded = json_decode(trim($result["output"]), true);

            $this->assertEquals(0, $result["code"], "The command must succeed when filtering to files only.");
            $this->assertEquals(["alpha.txt", "beta.php"], array_column($decoded, "name"), "The result set must include only immediate file children when --files-only is supplied.");
            $this->assertEquals(["file", "file"], array_column($decoded, "type"), "The filtered result set must classify resources as files.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer List Command — Flattens Descendant Files", description: "The explorer:list command recursively flattens descendant files when requested.")]
        public function testExplorerListCommandFlattensDescendantFiles () : void {
            $result = $this->executeCommand($this->sandboxPath . " --adapter=local --flatten --format=json");
            $decoded = json_decode(trim($result["output"]), true);

            $this->assertEquals(0, $result["code"], "The command must succeed when flattening descendant files.");
            $this->assertEquals(["alpha.txt", "beta.php", "gamma.txt", "delta.json"], array_column($decoded, "name"), "The flattened result set must include descendant files recursively in traversal order.");
            $this->assertEquals(["file", "file", "file", "file"], array_column($decoded, "type"), "Flattened results must currently contain files only because Explorer's flatten API returns files.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer List Command — Rejects Conflicting Filters", description: "The explorer:list command returns a validation error when mutually exclusive filters are combined.")]
        public function testExplorerListCommandRejectsConflictingFilters () : void {
            $result = $this->executeCommand($this->sandboxPath . " --files-only --dirs-only --format=json");

            $this->assertEquals(2, $result["code"], "Mutually exclusive resource filters must return validation exit code 2.");
            $this->assertTrue(str_contains($result["output"], "cannot be combined"), "The command must explain the conflicting filter combination.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer List Command — Rejects Invalid Adapter", description: "The explorer:list command returns a validation error for unsupported adapter values.")]
        public function testExplorerListCommandRejectsInvalidAdapter () : void {
            $result = $this->executeCommand($this->sandboxPath . " --adapter=s3 --format=json");

            $this->assertEquals(2, $result["code"], "An unsupported adapter value must return validation exit code 2.");
            $this->assertTrue(str_contains($result["output"], "--adapter option must currently be local"), "The command must explain the unsupported adapter value.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer List Command — Rejects Invalid Format", description: "The explorer:list command returns a validation error for unsupported output formats.")]
        public function testExplorerListCommandRejectsInvalidFormat () : void {
            $result = $this->executeCommand($this->sandboxPath . " --format=paths");

            $this->assertEquals(2, $result["code"], "An unsupported format must return validation exit code 2.");
            $this->assertTrue(str_contains($result["output"], "--format option must be table or json"), "The command must explain the unsupported format.");
        }
    }
?>