<?php
    /**
     * Project Name:    Wingman Explorer - Console Convert Command Tests
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
    use Wingman\Explorer\Bridge\Console\Commands\ConvertCommand;
    use Wingman\Explorer\IO\IOManager;

    /**
     * Tests for Explorer's Console-backed convert command.
     */
    #[Requires(type: "class", value: Console::class, message: "Wingman Console is required for command bridge tests.")]
    class ConsoleConvertCommandTest extends Test {
        /**
         * The temporary sandbox directory used for command tests.
         * @var string
         */
        private string $sandboxPath;

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
         * Executes the convert command and captures its textual output.
         * @param string $arguments The command argument string.
         * @return array{code: int, output: string} The exit code and captured output.
         */
        private function executeCommand (string $arguments) : array {
            $command = new ConvertCommand($arguments);
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
         * Creates a structured sandbox directory tree before each test.
         */
        protected function setUp () : void {
            IOManager::init();

            $this->sandboxPath = dirname(__DIR__) . "/temp/explorer_console_convert_command_" . uniqid();
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

        #[Group("Console")]
        #[Define(name: "Explorer Convert Command — Converts JSON To INI", description: "The explorer:convert command can infer both source and destination formats through Explorer's import and export managers.")]
        public function testExplorerConvertCommandConvertsJsonToIni () : void {
            $source = $this->sandboxPath . "/config.json";
            $destination = $this->sandboxPath . "/config.ini";

            file_put_contents($source, '{"app":{"enabled":true,"count":3}}');

            $result = $this->executeCommand($source . " " . $destination);
            $content = (string) file_get_contents($destination);

            $this->assertEquals(0, $result["code"], "The command must succeed when both formats can be inferred.");
            $this->assertTrue(str_contains($result["output"], "Converted {$source} to {$destination}."), "The command must report the converted paths on success.");
            $this->assertTrue(str_contains($content, "[app]"), "The inferred INI exporter must emit section headers.");
            $this->assertTrue(str_contains($content, "enabled = true"), "The inferred INI exporter must emit scalar values.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Convert Command — Forces Source Format", description: "The explorer:convert command can force a specific importer type when the source format should not be inferred.")]
        public function testExplorerConvertCommandForcesSourceFormat () : void {
            $source = $this->sandboxPath . "/payload.data";
            $destination = $this->sandboxPath . "/payload.json";

            file_put_contents($source, "plain text");

            $result = $this->executeCommand($source . " " . $destination . " --from=text");

            $this->assertEquals(0, $result["code"], "The command must succeed when forcing a supported importer.");
            $this->assertEquals("plain text", json_decode((string) file_get_contents($destination), true), "The forced text importer must feed the inferred JSON exporter with the raw string value.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Convert Command — Forces Destination Format", description: "The explorer:convert command can force a specific exporter type when the destination extension should not drive export selection.")]
        public function testExplorerConvertCommandForcesDestinationFormat () : void {
            $source = $this->sandboxPath . "/config.json";
            $destination = $this->sandboxPath . "/report.out";

            file_put_contents($source, '{"name":"alpha"}');

            $result = $this->executeCommand($source . " " . $destination . " --to=text");
            $content = (string) file_get_contents($destination);

            $this->assertEquals(0, $result["code"], "The command must succeed when forcing a supported exporter.");
            $this->assertTrue(str_contains($content, "[name] => alpha"), "The forced text exporter must serialise structured data as plain text.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Convert Command — Suppresses Success Output", description: "The explorer:convert command suppresses its success summary when quiet mode is enabled.")]
        public function testExplorerConvertCommandSuppressesSuccessOutput () : void {
            $source = $this->sandboxPath . "/config.json";
            $destination = $this->sandboxPath . "/copy.json";

            file_put_contents($source, '{"name":"alpha"}');

            $result = $this->executeCommand($source . " " . $destination . " --quiet");

            $this->assertEquals(0, $result["code"], "Quiet mode must not prevent successful conversion.");
            $this->assertFalse(str_contains($result["output"], "Converted "), "Quiet mode must suppress the success summary.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Convert Command — Rejects Invalid Source Format", description: "The explorer:convert command returns a validation error for unsupported forced importer types.")]
        public function testExplorerConvertCommandRejectsInvalidSourceFormat () : void {
            $source = $this->sandboxPath . "/config.json";
            $destination = $this->sandboxPath . "/config.ini";

            file_put_contents($source, '{"name":"alpha"}');

            $result = $this->executeCommand($source . " " . $destination . " --from=yaml");

            $this->assertEquals(2, $result["code"], "An unsupported importer format must return validation exit code 2.");
            $this->assertTrue(str_contains($result["output"], "--from option must be one of"), "The command must explain the unsupported importer type.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Convert Command — Rejects Invalid Destination Format", description: "The explorer:convert command returns a validation error for unsupported forced exporter types.")]
        public function testExplorerConvertCommandRejectsInvalidDestinationFormat () : void {
            $source = $this->sandboxPath . "/config.json";
            $destination = $this->sandboxPath . "/config.out";

            file_put_contents($source, '{"name":"alpha"}');

            $result = $this->executeCommand($source . " " . $destination . " --to=yaml");

            $this->assertEquals(2, $result["code"], "An unsupported exporter format must return validation exit code 2.");
            $this->assertTrue(str_contains($result["output"], "--to option must be one of"), "The command must explain the unsupported exporter type.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Convert Command — Rejects Missing Source", description: "The explorer:convert command returns a command failure when the source file does not exist.")]
        public function testExplorerConvertCommandRejectsMissingSource () : void {
            $source = $this->sandboxPath . "/missing.json";
            $destination = $this->sandboxPath . "/config.ini";

            $result = $this->executeCommand($source . " " . $destination);

            $this->assertEquals(1, $result["code"], "A missing source file must return command exit code 1.");
            $this->assertTrue(str_contains($result["output"], "does not exist or is not a file"), "The command must explain the missing source file.");
        }
    }
?>