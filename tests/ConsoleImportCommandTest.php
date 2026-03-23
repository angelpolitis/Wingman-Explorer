<?php
    /**
     * Project Name:    Wingman Explorer - Console Import Command Tests
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
    use Wingman\Explorer\Bridge\Console\Commands\ImportCommand;
    use Wingman\Explorer\IO\IOManager;

    /**
     * Tests for Explorer's Console-backed import command.
     */
    #[Requires(type: "class", value: Console::class, message: "Wingman Console is required for command bridge tests.")]
    class ConsoleImportCommandTest extends Test {
        /**
         * The temporary sandbox directory used for command tests.
         * @var string
         */
        private string $sandboxPath;

        /**
         * Creates a structured sandbox directory tree before each test.
         */
        protected function setUp () : void {
            IOManager::init();

            $this->sandboxPath = dirname(__DIR__) . "/temp/explorer_console_import_command_" . uniqid();
            mkdir($this->sandboxPath, 0775, true);
            file_put_contents($this->sandboxPath . "/config.json", '{"name":"alpha","enabled":true}');
            file_put_contents($this->sandboxPath . "/settings.ini", "[app]\nenabled=true\ncount=3\n");
            file_put_contents($this->sandboxPath . "/note.txt", "plain text\nsecond line\n");
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
         * Executes the import command and captures its textual output.
         * @param string $arguments The command argument string.
         * @return array{code: int, output: string} The exit code and captured output.
         */
        private function executeCommand (string $arguments) : array {
            $command = new ImportCommand($arguments);
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
        #[Define(name: "Explorer Import Command — Auto Detects JSON", description: "The explorer:import command auto-detects JSON files and emits a human-readable structured payload.")]
        public function testExplorerImportCommandAutoDetectsJson () : void {
            $result = $this->executeCommand($this->sandboxPath . "/config.json");

            $this->assertEquals(0, $result["code"], "The command must succeed when auto-detecting a supported JSON file.");
            $this->assertTrue(str_contains($result["output"], "'name' => 'alpha'"), "Human-readable output must include the imported JSON fields.");
            $this->assertTrue(str_contains($result["output"], "'enabled' => true"), "Human-readable output must preserve imported scalar values.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Import Command — Emits JSON Output", description: "The explorer:import command can emit the imported value as JSON.")]
        public function testExplorerImportCommandEmitsJsonOutput () : void {
            $result = $this->executeCommand($this->sandboxPath . "/config.json --json");
            $decoded = json_decode(trim($result["output"]), true);

            $this->assertEquals(0, $result["code"], "The command must succeed for JSON output.");
            $this->assertEquals(["name" => "alpha", "enabled" => true], $decoded, "JSON output must contain the imported value unchanged.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Import Command — Pretty Prints JSON", description: "The explorer:import command pretty-prints structured JSON output when requested.")]
        public function testExplorerImportCommandPrettyPrintsJson () : void {
            $result = $this->executeCommand($this->sandboxPath . "/config.json --json --pretty");

            $this->assertEquals(0, $result["code"], "The command must succeed for pretty JSON output.");
            $this->assertTrue(str_contains($result["output"], PHP_EOL . "    \"name\""), "Pretty JSON output must contain indentation for structured values.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Import Command — Forces INI Importer", description: "The explorer:import command can force a specific registered importer type.")]
        public function testExplorerImportCommandForcesIniImporter () : void {
            $result = $this->executeCommand($this->sandboxPath . "/settings.ini --format=ini --json");
            $decoded = json_decode(trim($result["output"]), true);

            $this->assertEquals(0, $result["code"], "The command must succeed when forcing a registered importer.");
            $this->assertEquals(["app" => ["enabled" => true, "count" => 3]], $decoded, "The forced importer must shape the imported INI payload correctly.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Import Command — Forces Text Fallback", description: "The explorer:import command can force the fallback text importer through text aliases.")]
        public function testExplorerImportCommandForcesTextFallback () : void {
            $result = $this->executeCommand($this->sandboxPath . "/note.txt --format=text");

            $this->assertEquals(0, $result["code"], "The command must succeed when forcing the fallback text importer.");
            $this->assertEquals("plain text\nsecond line\n", $result["output"], "The text importer must emit the raw imported text unchanged.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Import Command — Rejects Invalid Format", description: "The explorer:import command returns a validation error for unsupported forced importer types.")]
        public function testExplorerImportCommandRejectsInvalidFormat () : void {
            $result = $this->executeCommand($this->sandboxPath . "/config.json --format=yaml");

            $this->assertEquals(2, $result["code"], "An unsupported format must return validation exit code 2.");
            $this->assertTrue(str_contains($result["output"], "--format option must be one of"), "The command must explain the unsupported importer type.");
        }
    }
?>