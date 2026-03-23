<?php
    /**
     * Project Name:    Wingman Explorer - Console Export Command Tests
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
    use Wingman\Explorer\Bridge\Console\Commands\ExportCommand;
    use Wingman\Explorer\IO\IOManager;

    /**
     * Tests for Explorer's Console-backed export command.
     */
    #[Requires(type: "class", value: Console::class, message: "Wingman Console is required for command bridge tests.")]
    class ConsoleExportCommandTest extends Test {
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

            $this->sandboxPath = dirname(__DIR__) . "/temp/explorer_console_export_command_" . uniqid();
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
         * Executes the export command and captures its textual output.
         * @param string $arguments The command argument string.
         * @return array{code: int, output: string} The exit code and captured output.
         */
        private function executeCommand (string $arguments) : array {
            $command = new ExportCommand($arguments);
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
         * Executes the export command in a subprocess so stdin can be piped in.
         * @param string $arguments The command argument string.
         * @param string $stdin The stdin payload.
         * @return array{code: int, output: string} The exit code and captured output.
         */
        private function executeCommandWithStdin (string $arguments, string $stdin) : array {
            $script = <<<'PHP'
require dirname(__DIR__, 2) . "/_temp_bootstrap.php";
require __DIR__ . "/tests/ConsoleExportCommandTest.php";
require __DIR__ . "/src/Bridge/Console/Commands/ExportCommand.php";

$command = new Wingman\Explorer\Bridge\Console\Commands\ExportCommand($argv[1]);
$command->setConsole(new Wingman\Console\Console([
    "coloursEnabled" => false,
    "iconsEnabled" => false,
    "logging" => false,
    "verbose" => false
]));

ob_start();
$code = $command->run();
$output = (string) ob_get_clean();

echo "CODE=" . $code . PHP_EOL;
echo $output;
PHP;

            $process = proc_open(
                ["php", "-r", $script, $arguments],
                [
                    0 => ["pipe", "r"],
                    1 => ["pipe", "w"],
                    2 => ["pipe", "w"]
                ],
                $pipes,
                dirname(__DIR__)
            );

            fwrite($pipes[0], $stdin);
            fclose($pipes[0]);

            $stdout = stream_get_contents($pipes[1]) ?: "";
            fclose($pipes[1]);

            $stderr = stream_get_contents($pipes[2]) ?: "";
            fclose($pipes[2]);

            $code = proc_close($process);

            return [
                "code" => $code,
                "output" => $stdout . $stderr
            ];
        }

        #[Group("Console")]
        #[Define(name: "Explorer Export Command — Exports Inline JSON", description: "The explorer:export command treats inline input as JSON and exports it using inferred destination format.")]
        public function testExplorerExportCommandExportsInlineJson () : void {
            $destination = $this->sandboxPath . "/config.json";
            $result = $this->executeCommand($destination . " '{\"name\":\"alpha\",\"enabled\":true}'");

            $this->assertEquals(0, $result["code"], "The command must succeed for valid inline JSON input.");
            $this->assertTrue(str_contains($result["output"], "Exported data to {$destination}."), "The command must report the destination on success.");
            $this->assertEquals(["name" => "alpha", "enabled" => true], json_decode((string) file_get_contents($destination), true), "The inferred JSON exporter must serialise the decoded payload.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Export Command — Forces INI Exporter", description: "The explorer:export command can force a specific registered exporter type.")]
        public function testExplorerExportCommandForcesIniExporter () : void {
            $destination = $this->sandboxPath . "/config.out";
            $result = $this->executeCommand($destination . " '{\"app\":{\"enabled\":true,\"count\":3}}' --format=ini");
            $content = (string) file_get_contents($destination);

            $this->assertEquals(0, $result["code"], "The command must succeed when forcing a registered exporter.");
            $this->assertTrue(str_contains($content, "[app]"), "The forced INI exporter must emit section headers.");
            $this->assertTrue(str_contains($content, "enabled = true"), "The forced INI exporter must emit scalar values.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Export Command — Forces Text Fallback", description: "The explorer:export command can force the fallback text exporter through text aliases.")]
        public function testExplorerExportCommandForcesTextFallback () : void {
            $destination = $this->sandboxPath . "/note.txt";
            $result = $this->executeCommand($destination . " '\"plain text\"' --format=text");

            $this->assertEquals(0, $result["code"], "The command must succeed when forcing the fallback text exporter.");
            $this->assertEquals("plain text", (string) file_get_contents($destination), "The text exporter must write the decoded string content unchanged.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Export Command — Reads JSON From Stdin", description: "The explorer:export command can read its JSON payload from stdin.")]
        public function testExplorerExportCommandReadsJsonFromStdin () : void {
            $destination = $this->sandboxPath . "/stdin.json";
            $result = $this->executeCommandWithStdin($destination . " --stdin --quiet", '{"name":"stdin"}');

            $this->assertEquals(0, $result["code"], "The command must succeed when reading a valid JSON payload from stdin.");
            $this->assertFalse(str_contains($result["output"], "Exported data to"), "Quiet mode must suppress the success summary.");
            $this->assertEquals(["name" => "stdin"], json_decode((string) file_get_contents($destination), true), "The stdin payload must be decoded and exported.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Export Command — Rejects Ambiguous Inputs", description: "The explorer:export command returns a validation error when inline input and stdin are requested together.")]
        public function testExplorerExportCommandRejectsAmbiguousInputs () : void {
            $destination = $this->sandboxPath . "/config.json";
            $result = $this->executeCommand($destination . " '{}' --stdin");

            $this->assertEquals(2, $result["code"], "Ambiguous input sources must return validation exit code 2.");
            $this->assertTrue(str_contains($result["output"], "either inline data or --stdin"), "The command must explain conflicting input sources.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Export Command — Rejects Invalid Json", description: "The explorer:export command returns a validation error when the payload is not valid JSON.")]
        public function testExplorerExportCommandRejectsInvalidJson () : void {
            $destination = $this->sandboxPath . "/config.json";
            $result = $this->executeCommand($destination . " '{invalid}'");

            $this->assertEquals(2, $result["code"], "Malformed JSON input must return validation exit code 2.");
            $this->assertTrue(str_contains($result["output"], "must be valid JSON"), "The command must explain malformed JSON input.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Export Command — Rejects Invalid Format", description: "The explorer:export command returns a validation error for unsupported forced exporter types.")]
        public function testExplorerExportCommandRejectsInvalidFormat () : void {
            $destination = $this->sandboxPath . "/config.out";
            $result = $this->executeCommand($destination . " '{}' --format=yaml");

            $this->assertEquals(2, $result["code"], "An unsupported format must return validation exit code 2.");
            $this->assertTrue(str_contains($result["output"], "--format option must be one of"), "The command must explain the unsupported exporter type.");
        }
    }
?>