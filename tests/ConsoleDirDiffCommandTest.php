<?php
    /**
     * Project Name:    Wingman Explorer - Console Directory Diff Command Tests
     * Created by:      Angel Politis
     * Creation Date:   Mar 23 2026
     * Last Modified:   Mar 23 2026
     *
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
    use Wingman\Explorer\Bridge\Console\Commands\DirDiffCommand;

    /**
     * Tests for Explorer's Console-backed directory diff command.
     */
    #[Requires(type: "class", value: Console::class, message: "Wingman Console is required for command bridge tests.")]
    class ConsoleDirDiffCommandTest extends Test {
        /**
         * The base directory used for comparisons.
         * @var string
         */
        private string $basePath;

        /**
         * The comparison directory used for comparisons.
         * @var string
         */
        private string $comparisonPath;

        /**
         * Creates the directory trees used by each test.
         */
        protected function setUp () : void {
            $root = dirname(__DIR__) . "/temp/explorer_console_dir_diff_command_" . uniqid();
            $this->basePath = $root . "/base";
            $this->comparisonPath = $root . "/comparison";

            mkdir($this->basePath . "/nested", 0775, true);
            mkdir($this->comparisonPath . "/nested", 0775, true);

            file_put_contents($this->basePath . "/shared.txt", "alpha\n");
            file_put_contents($this->comparisonPath . "/shared.txt", "alpha\n");
            file_put_contents($this->basePath . "/removed.txt", "removed\n");
            file_put_contents($this->comparisonPath . "/added.txt", "added\n");
            file_put_contents($this->basePath . "/nested/changed.txt", "before\n");
            file_put_contents($this->comparisonPath . "/nested/changed.txt", "after\n");
        }

        /**
         * Removes the directory trees after each test.
         */
        protected function tearDown () : void {
            $root = dirname($this->basePath);

            if (is_dir($root)) {
                $this->cleanDirectory($root);
                @rmdir($root);
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
         * Executes the directory diff command and captures its output.
         * @param string $arguments The command argument string.
         * @return array{code: int, output: string} The exit code and captured output.
         */
        private function executeCommand (string $arguments) : array {
            $command = new DirDiffCommand($arguments);
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
        #[Define(name: "Explorer Dir Diff Command — Defaults To Recursive Local Comparison", description: "The explorer:dir-diff command defaults to the local adapter contract and surfaces nested differences recursively.")]
        public function testExplorerDirDiffCommandDefaultsToRecursiveLocalComparison () : void {
            $result = $this->executeCommand($this->basePath . " " . $this->comparisonPath);

            $this->assertEquals(0, $result["code"], "The command must default to a successful local recursive comparison.");
            $this->assertTrue(str_contains($result["output"], "Added"), "Human output must contain an Added section.");
            $this->assertTrue(str_contains($result["output"], "Removed"), "Human output must contain a Removed section.");
            $this->assertTrue(str_contains($result["output"], "Modified"), "Human output must contain a Modified section.");
            $this->assertTrue(str_contains($result["output"], "added.txt"), "Added resources must be listed in human output.");
            $this->assertTrue(str_contains($result["output"], "removed.txt"), "Removed resources must be listed in human output.");
            $this->assertTrue(str_contains($result["output"], "changed.txt"), "Default recursive output must include nested modified resources.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Dir Diff Command — Emits Json Groups", description: "The explorer:dir-diff command can emit grouped JSON output.")]
        public function testExplorerDirDiffCommandEmitsJsonGroups () : void {
            $result = $this->executeCommand($this->basePath . " " . $this->comparisonPath . " --format=json");
            $decoded = json_decode(trim($result["output"]), true);

            $this->assertEquals(0, $result["code"], "The command must succeed for valid JSON output.");
            $this->assertEquals([$this->comparisonPath . "/added.txt"], array_column($decoded["added"], "path"), "The JSON output must expose added resources.");
            $this->assertEquals([$this->basePath . "/removed.txt"], array_column($decoded["removed"], "path"), "The JSON output must expose removed resources.");
            $this->assertEquals([$this->comparisonPath . "/nested/changed.txt"], array_column($decoded["modified"], "path"), "The JSON output must expose modified resources from the comparison tree.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Dir Diff Command — Rejects Invalid Format", description: "The explorer:dir-diff command returns a validation error for unsupported output formats.")]
        public function testExplorerDirDiffCommandRejectsInvalidFormat () : void {
            $result = $this->executeCommand($this->basePath . " " . $this->comparisonPath . " --format=unified");

            $this->assertEquals(2, $result["code"], "An unsupported format must return validation exit code 2.");
            $this->assertTrue(str_contains($result["output"], "--format option must be table or json"), "The command must explain the unsupported format.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Dir Diff Command — Rejects Invalid Adapter", description: "The explorer:dir-diff command returns a validation error for unsupported adapter values.")]
        public function testExplorerDirDiffCommandRejectsInvalidAdapter () : void {
            $result = $this->executeCommand($this->basePath . " " . $this->comparisonPath . " --adapter=s3");

            $this->assertEquals(2, $result["code"], "An unsupported adapter value must return validation exit code 2.");
            $this->assertTrue(str_contains($result["output"], "--adapter option must currently be local"), "The command must explain the unsupported adapter value.");
        }

        #[Group("Console")]
        #[Define(name: "Explorer Dir Diff Command — Rejects Non Directory Paths", description: "The explorer:dir-diff command fails cleanly when one of the inputs is not a directory.")]
        public function testExplorerDirDiffCommandRejectsNonDirectoryPaths () : void {
            $result = $this->executeCommand($this->basePath . "/shared.txt " . $this->comparisonPath);

            $this->assertEquals(1, $result["code"], "A non-directory path must return command failure exit code 1.");
            $this->assertTrue(str_contains($result["output"], "does not exist or is not a directory"), "The command must explain the unsupported input path.");
        }
    }
?>