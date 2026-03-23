<?php
    /**
     * Project Name:    Wingman Explorer - Ignore Pattern Builder Tests
     * Created by:      Angel Politis
     * Creation Date:   Mar 21 2026
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer.Tests namespace.
    namespace Wingman\Explorer\Tests;

    # Import the following classes to the current scope.
    use RuntimeException;
    use Wingman\Argus\Attributes\Define;
    use Wingman\Argus\Attributes\Group;
    use Wingman\Argus\Test;
    use Wingman\Explorer\IgnorePatternBuilder;

    /**
     * Tests for IgnorePatternBuilder::build().
     */
    class IgnorePatternBuilderTest extends Test {
        /**
         * The temporary sandbox where .ignore files are created.
         * @var string
         */
        private string $sandboxPath;

        /**
         * The builder instance under test.
         * @var IgnorePatternBuilder
         */
        private IgnorePatternBuilder $builder;

        /**
         * Creates the sandbox directory and the builder instance before each test.
         */
        protected function setUp () : void {
            $this->sandboxPath = dirname(__DIR__) . "/temp/explorer_ignore_test_" . uniqid();
            mkdir($this->sandboxPath, 0775, true);
            $this->builder = new IgnorePatternBuilder();
        }

        /**
         * Removes the sandbox directory after each test.
         */
        protected function tearDown () : void {
            if (is_dir($this->sandboxPath)) {
                foreach (array_diff(scandir($this->sandboxPath) ?: [], [".", ".."]) as $file) {
                    @unlink($this->sandboxPath . "/" . $file);
                }
                @rmdir($this->sandboxPath);
            }
        }

        /**
         * Creates a .ignore file in the sandbox with the given content and returns its path.
         * @param string $content The content to write to the file.
         * @return string The absolute path to the created .ignore file.
         */
        private function createIgnoreFile (string $content) : string {
            $path = $this->sandboxPath . "/.ignore";
            file_put_contents($path, $content);

            return $path;
        }

        // ─── Empty Inputs ──────────────────────────────────────────────────────

        #[Group("IgnorePatternBuilder")]
        #[Define(name: "Build — Empty Paths Array Returns Never-Matching Pattern", description: "build([]) returns the canonical never-matching regex '/$^/' when no file paths are provided.")]
        public function testBuildWithEmptyPathsReturnsNeverMatchingPattern () : void {
            $result = $this->builder->build([]);

            $this->assertTrue($result === "/$^/", "build([]) must return '/$^/' when no paths are provided.");
        }

        #[Group("IgnorePatternBuilder")]
        #[Define(name: "Build — Non-.ignore Files Are Silently Skipped", description: "Files whose base name is not exactly '.ignore' are skipped; the result is still a never-matching pattern.")]
        public function testBuildSkipsNonIgnoreFiles () : void {
            $path = $this->sandboxPath . "/gitignore";
            file_put_contents($path, "*.log\n");

            $result = $this->builder->build([$path]);

            $this->assertTrue($result === "/$^/", "build() must skip files whose base name is not '.ignore'.");
        }

        // ─── Content Parsing ───────────────────────────────────────────────────

        #[Group("IgnorePatternBuilder")]
        #[Define(name: "Build — Comment Lines Are Ignored", description: "Lines that start with '#' are treated as comments and must not appear in the resulting regex.")]
        public function testBuildIgnoresCommentLines () : void {
            $path = $this->createIgnoreFile("# This is a comment\n*.log\n");

            $result = $this->builder->build([$path]);

            $this->assertTrue(!str_contains($result, "comment"), "Comment lines must not contribute patterns to the built regex.");
        }

        #[Group("IgnorePatternBuilder")]
        #[Define(name: "Build — Empty Lines Are Ignored", description: "Blank lines in the .ignore file are skipped and do not affect the resulting regex.")]
        public function testBuildIgnoresEmptyLines () : void {
            $path = $this->createIgnoreFile("\n\n*.log\n\n");
            $result = $this->builder->build([$path]);

            $pathWithLog = $this->createIgnoreFile("*.log");
            $resultWithoutBlanks = $this->builder->build([$pathWithLog]);

            $this->assertTrue($result === $resultWithoutBlanks, "Empty lines must not produce additional patterns in the regex.");
        }

        #[Group("IgnorePatternBuilder")]
        #[Define(name: "Build — Produces a Regex That Matches Patterns in the File", description: "build() returns a regex which matches strings described by the patterns in the .ignore file.")]
        public function testBuildProducesMatchingRegexForIgnoredPaths () : void {
            $path = $this->createIgnoreFile("*.log\n");

            $regex = $this->builder->build([$path]);

            $this->assertTrue(preg_match($regex, "error.log") === 1, "The built regex must match 'error.log' when '*.log' is in the ignore file.");
        }

        #[Group("IgnorePatternBuilder")]
        #[Define(name: "Build — Regex Does Not Match Unlisted Paths", description: "build() returns a regex that does not match paths not covered by any ignore rule.")]
        public function testBuildRegexDoesNotMatchUnlistedPaths () : void {
            $path = $this->createIgnoreFile("*.log\n");

            $regex = $this->builder->build([$path]);

            $this->assertTrue(preg_match($regex, "readme.txt") === 0, "The built regex must not match 'readme.txt' when only '*.log' is in the ignore file.");
        }

        // ─── Wildcard Translations ─────────────────────────────────────────────

        #[Group("IgnorePatternBuilder")]
        #[Define(name: "Wildcard — Single Star Does Not Cross Directory Separators", description: "A single '*' matches characters within a single path segment but not across directory separators.")]
        public function testSingleStarDoesNotCrossDirectorySeparators () : void {
            $path = $this->createIgnoreFile("*.log\n");

            $regex = $this->builder->build([$path]);

            $this->assertTrue(preg_match($regex, "logs/error.log") === 0, "A single '*' must not match across directory separators.");
        }

        #[Group("IgnorePatternBuilder")]
        #[Define(name: "Wildcard — Double Star Matches Across Directories", description: "A '**' wildcard matches any combination of characters including path separators.")]
        public function testDoubleStarMatchesAcrossDirectories () : void {
            $path = $this->createIgnoreFile("logs/**/*.log\n");

            $regex = $this->builder->build([$path]);

            $this->assertTrue(preg_match($regex, "logs/any/path/error.log") === 1, "A '**' wildcard must match across directory separators.");
        }

        #[Group("IgnorePatternBuilder")]
        #[Define(name: "Wildcard — Question Mark Matches Exactly One Character", description: "A '?' wildcard matches exactly one character that is not a path separator.")]
        public function testQuestionMarkMatchesExactlyOneCharacter () : void {
            $path = $this->createIgnoreFile("file?.txt\n");

            $regex = $this->builder->build([$path]);

            $this->assertTrue(preg_match($regex, "file1.txt") === 1, "A '?' wildcard must match exactly one character.");
            $this->assertTrue(preg_match($regex, "file.txt") === 0, "'?' must not match zero characters.");
            $this->assertTrue(preg_match($regex, "file12.txt") === 0, "'?' must not match more than one character.");
        }

        // ─── Error Handling ────────────────────────────────────────────────────

        #[Group("IgnorePatternBuilder")]
        #[Define(name: "Build — Throws RuntimeException for Nonexistent File", description: "build() throws RuntimeException when one of the provided paths does not point to an existing file.")]
        public function testBuildThrowsForNonexistentFile () : void {
            $threw = false;

            try {
                $this->builder->build([$this->sandboxPath . "/does_not_exist.txt"]);
            }
            catch (RuntimeException|\Throwable) {
                $threw = true;
            }

            $this->assertTrue($threw, "build() must throw when a provided path does not exist.");
        }

        #[Group("IgnorePatternBuilder")]
        #[Define(name: "Build — All Rules Contribute to a Single Combined Regex", description: "Multiple patterns across one or more .ignore files are combined into a single alternation regex.")]
        public function testBuildCombinesMultiplePatternsIntoSingleRegex () : void {
            $path = $this->createIgnoreFile("*.log\n*.tmp\n");

            $regex = $this->builder->build([$path]);

            $this->assertTrue(preg_match($regex, "error.log") === 1, "The built regex must match '*.log' patterns.");
            $this->assertTrue(preg_match($regex, "session.tmp") === 1, "The built regex must match '*.tmp' patterns.");
        }
    }
?>