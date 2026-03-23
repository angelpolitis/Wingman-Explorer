<?php
    /**
     * Project Name:    Wingman Explorer - Console Replace Command Tests
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
    use Wingman\Explorer\Bridge\Console\Commands\ReplaceCommand;

    /**
     * Tests for Explorer's Console-backed replace command.
     */
    #[Requires(type: "class", value: Console::class, message: "Wingman Console is required for command bridge tests.")]
    class ConsoleReplaceCommandTest extends Test {
        /**
         * The temporary sandbox directory used for command tests.
         * @var string
         */
        private string $sandboxPath;

        /**
         * Creates a structured sandbox directory tree before each test.
         */
        protected function setUp () : void {
            $this->sandboxPath = dirname(__DIR__) . "/temp/explorer_console_replace_command_" . uniqid();
            mkdir($this->sandboxPath, 0775, true);
            file_put_contents($this->sandboxPath . "/alpha.txt", "alpha beta beta\nbeta gamma\n");
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
         * Executes the replace command and captures its textual output.
         * @param string $arguments The command argument string.
         * @return array{code: int, output: string} The exit code and captured output.
         */
        private function executeCommand (string $arguments) : array {
            $command = new ReplaceCommand($arguments);
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
        #[Define(name: "Explorer Replace Command — Defaults To Replace All", description: "The explorer:replace command replaces all plain-string matches when no scope flag is supplied.")]
        public function testExplorerReplaceCommandDefaultsToReplaceAll () : void {
            $file = $this->sandboxPath . "/alpha.txt";
            $result = $this->executeCommand($file . " beta zeta");

            $this->assertEquals(0, $result["code"], "The command must succeed for a valid replace-all operation.");
            $this->assertTrue(str_contains($result["output"], "Replaced 3 occurrence(s)"), "The command must report the number of replacements applied.");
            $this->assertEquals("alpha zeta zeta\nzeta gamma\n", file_get_contents($file), "The default scope must replace all matching plain strings and persist the file.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Replace Command — Supports Regex First Replacement", description: "The explorer:replace command can replace only the first regex match.")]
        public function testExplorerReplaceCommandSupportsRegexFirstReplacement () : void {
            $file = $this->sandboxPath . "/alpha.txt";
            $result = $this->executeCommand($file . " '/beta/' theta --regex --first");

            $this->assertEquals(0, $result["code"], "The command must succeed for a valid first regex replacement.");
            $this->assertEquals("alpha theta beta\nbeta gamma\n", file_get_contents($file), "The first regex scope must replace only the first match and persist the file.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Replace Command — Supports Regex Last Replacement", description: "The explorer:replace command can replace only the last regex match.")]
        public function testExplorerReplaceCommandSupportsRegexLastReplacement () : void {
            $file = $this->sandboxPath . "/alpha.txt";
            $result = $this->executeCommand($file . " '/beta/' theta --regex --last");

            $this->assertEquals(0, $result["code"], "The command must succeed for a valid last regex replacement.");
            $this->assertEquals("alpha beta beta\ntheta gamma\n", file_get_contents($file), "The last regex scope must replace only the final match and persist the file.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Replace Command — Supports Dry Run", description: "The explorer:replace command can preview replacements without persisting them.")]
        public function testExplorerReplaceCommandSupportsDryRun () : void {
            $file = $this->sandboxPath . "/alpha.txt";
            $original = file_get_contents($file);
            $result = $this->executeCommand($file . " beta zeta --dry-run");

            $this->assertEquals(0, $result["code"], "The command must succeed for a valid dry-run replacement.");
            $this->assertTrue(str_contains($result["output"], "Would replace 3 occurrence(s)"), "Dry-run mode must describe the pending replacements.");
            $this->assertEquals($original, file_get_contents($file), "Dry-run mode must not persist any change to disk.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Replace Command — Respects Quiet Mode", description: "The explorer:replace command suppresses success output when quiet mode is enabled.")]
        public function testExplorerReplaceCommandRespectsQuietMode () : void {
            $file = $this->sandboxPath . "/alpha.txt";
            $result = $this->executeCommand($file . " beta zeta --quiet --first");

            $this->assertEquals(0, $result["code"], "The command must succeed for a valid quiet replacement.");
            $this->assertEquals("", $result["output"], "Quiet mode must suppress normal success output.");
            $this->assertEquals("alpha zeta beta\nbeta gamma\n", file_get_contents($file), "Quiet mode must still persist the requested replacement.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Replace Command — Rejects Conflicting Scopes", description: "The explorer:replace command returns a validation error when multiple scope flags are active.")]
        public function testExplorerReplaceCommandRejectsConflictingScopes () : void {
            $file = $this->sandboxPath . "/alpha.txt";
            $result = $this->executeCommand($file . " beta zeta --first --all");

            $this->assertEquals(2, $result["code"], "Conflicting scope flags must return validation exit code 2.");
            $this->assertTrue(str_contains($result["output"], "Exactly one of --first, --last, or --all"), "The command must explain the conflicting scope flags.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Replace Command — Rejects Invalid Adapter", description: "The explorer:replace command returns a validation error for unsupported adapter values.")]
        public function testExplorerReplaceCommandRejectsInvalidAdapter () : void {
            $file = $this->sandboxPath . "/alpha.txt";
            $result = $this->executeCommand($file . " beta zeta --adapter=s3");

            $this->assertEquals(2, $result["code"], "An unsupported adapter value must return validation exit code 2.");
            $this->assertTrue(str_contains($result["output"], "--adapter option must currently be local"), "The command must explain the unsupported adapter value.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Replace Command — Rejects Invalid Regex", description: "The explorer:replace command returns a validation error for malformed regex patterns.")]
        public function testExplorerReplaceCommandRejectsInvalidRegex () : void {
            $file = $this->sandboxPath . "/alpha.txt";
            $result = $this->executeCommand($file . " '/beta' zeta --regex");

            $this->assertEquals(2, $result["code"], "A malformed regex pattern must return validation exit code 2.");
            $this->assertTrue(str_contains($result["output"], "valid PCRE pattern"), "The command must explain invalid regex input.");
        }
    }
?>