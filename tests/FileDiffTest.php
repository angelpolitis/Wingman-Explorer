<?php
    /**
     * Project Name:    Wingman Explorer - FileDiff Tests
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
    use Wingman\Explorer\FileDiff;
    use Wingman\Explorer\Resources\InlineFile;

    /**
     * Tests for the FileDiff LCS-based line-by-line diff algorithm.
     */
    class FileDiffTest extends Test {
        /**
         * Helper method to build an InlineFile from a multi-line string.
         * @param string $content The file content.
         * @return InlineFile The inline file resource.
         */
        private function inlineFile (string $content) : InlineFile {
            return new InlineFile($content);
        }

        #[Group("FileDiff")]
        #[Define(name: "Identical Files — Only Unchanged Hunks", description: "When both files are identical, every hunk must have operation='unchanged'.")]
        public function testIdenticalFilesProduceOnlyUnchangedHunks () : void {
            $content = "line one\nline two\nline three";
            $result = FileDiff::compare($this->inlineFile($content), $this->inlineFile($content));

            $this->assertTrue(isset($result["hunks"]), "Result must include a 'hunks' key.");

            foreach ($result["hunks"] as $hunk) {
                $this->assertTrue($hunk["operation"] === "unchanged", "Every hunk for identical files must have operation='unchanged'.");
            }
        }

        #[Group("FileDiff")]
        #[Define(name: "Added Lines — Reported as 'added'", description: "Lines present in file B but absent in file A appear as hunks with operation='added'.")]
        public function testAddedLinesReportedAsAdded () : void {
            $a = $this->inlineFile("line one\nline two");
            $b = $this->inlineFile("line one\nline two\nline three");

            $result = FileDiff::compare($a, $b);
            $operations = array_column($result["hunks"], "operation");

            $this->assertTrue(in_array("added", $operations), "An 'added' operation must appear when file B has lines absent from file A.");
        }

        #[Group("FileDiff")]
        #[Define(name: "Removed Lines — Reported as 'removed'", description: "Lines present in file A but absent in file B appear as hunks with operation='removed'.")]
        public function testRemovedLinesReportedAsRemoved () : void {
            $a = $this->inlineFile("line one\nline two\nline three");
            $b = $this->inlineFile("line one\nline two");

            $result = FileDiff::compare($a, $b);
            $operations = array_column($result["hunks"], "operation");

            $this->assertTrue(in_array("removed", $operations), "A 'removed' operation must appear when file B is missing lines that existed in file A.");
        }

        #[Group("FileDiff")]
        #[Define(name: "Modified Line — Appears as Remove Plus Add", description: "A changed line is represented as a 'removed' hunk for the old content and an 'added' hunk for the new content.")]
        public function testModifiedLineAppearsAsRemoveAndAdd () : void {
            $a = $this->inlineFile("hello world");
            $b = $this->inlineFile("hello earth");

            $result = FileDiff::compare($a, $b);
            $operations = array_column($result["hunks"], "operation");

            $this->assertTrue(in_array("removed", $operations), "A modified line must produce a 'removed' hunk for the old content.");
            $this->assertTrue(in_array("added", $operations), "A modified line must produce an 'added' hunk for the new content.");
        }

        #[Group("FileDiff")]
        #[Define(name: "Both Empty Files — Empty Hunk List", description: "Comparing two empty files produces an empty hunk list.")]
        public function testBothEmptyFilesProduceEmptyHunkList () : void {
            $result = FileDiff::compare($this->inlineFile(""), $this->inlineFile(""));

            $this->assertTrue(isset($result["hunks"]), "Result must include a 'hunks' key.");
            $this->assertCount(0, $result["hunks"], "Two empty files must produce an empty hunk list.");
        }

        #[Group("FileDiff")]
        #[Define(name: "Empty File A vs Non-Empty B — All Added", description: "When file A is empty and file B has lines, all hunks have operation='added'.")]
        public function testEmptyFileAVsNonEmptyBProducesAllAdded () : void {
            $a = $this->inlineFile("");
            $b = $this->inlineFile("only in b");

            $result = FileDiff::compare($a, $b);
            $nonAdded = array_filter($result["hunks"], fn($h) => $h["operation"] !== "added");

            $this->assertTrue(count($result["hunks"]) > 0, "Non-empty file B must produce at least one hunk.");
            $this->assertCount(0, $nonAdded, "Every hunk must be 'added' when file A is empty.");
        }

        #[Group("FileDiff")]
        #[Define(name: "Non-Empty File A vs Empty B — All Removed", description: "When file A has lines and file B is empty, all hunks have operation='removed'.")]
        public function testNonEmptyFileAVsEmptyBProducesAllRemoved () : void {
            $a = $this->inlineFile("only in a");
            $b = $this->inlineFile("");

            $result = FileDiff::compare($a, $b);
            $nonRemoved = array_filter($result["hunks"], fn($h) => $h["operation"] !== "removed");

            $this->assertTrue(count($result["hunks"]) > 0, "Non-empty file A must produce at least one hunk.");
            $this->assertCount(0, $nonRemoved, "Every hunk must be 'removed' when file B is empty.");
        }

        #[Group("FileDiff")]
        #[Define(name: "Hunk Structure — Contains Required Keys", description: "Every hunk contains the keys 'operation', 'lineA', 'lineB', and 'content'.")]
        public function testHunksContainRequiredKeys () : void {
            $result = FileDiff::compare(
                $this->inlineFile("a\nb"),
                $this->inlineFile("a\nc")
            );

            foreach ($result["hunks"] as $hunk) {
                $this->assertArrayHasKey("operation", $hunk, "Hunk must have an 'operation' key.");
                $this->assertArrayHasKey("lineA", $hunk, "Hunk must have a 'lineA' key.");
                $this->assertArrayHasKey("lineB", $hunk, "Hunk must have a 'lineB' key.");
                $this->assertArrayHasKey("content", $hunk, "Hunk must have a 'content' key.");
            }
        }

        #[Group("FileDiff")]
        #[Define(name: "Added Hunk — lineA Is Null", description: "An 'added' hunk has lineA=null because the line has no corresponding line in file A.")]
        public function testAddedHunkHasNullLineA () : void {
            $a = $this->inlineFile("existing");
            $b = $this->inlineFile("existing\nnew line");

            $result = FileDiff::compare($a, $b);
            $addedHunks = array_filter($result["hunks"], fn($h) => $h["operation"] === "added");

            foreach ($addedHunks as $hunk) {
                $this->assertTrue($hunk["lineA"] === null, "An 'added' hunk must have lineA=null.");
            }
        }

        #[Group("FileDiff")]
        #[Define(name: "Removed Hunk — lineB Is Null", description: "A 'removed' hunk has lineB=null because the line has no corresponding line in file B.")]
        public function testRemovedHunkHasNullLineB () : void {
            $a = $this->inlineFile("old line\nexisting");
            $b = $this->inlineFile("existing");

            $result = FileDiff::compare($a, $b);
            $removedHunks = array_filter($result["hunks"], fn($h) => $h["operation"] === "removed");

            foreach ($removedHunks as $hunk) {
                $this->assertTrue($hunk["lineB"] === null, "A 'removed' hunk must have lineB=null.");
            }
        }

        #[Group("FileDiff")]
        #[Define(name: "Max Lines Exceeded — Throws RuntimeException", description: "compare() throws RuntimeException when either file exceeds the maxLines limit.")]
        public function testExceedingMaxLinesThrowsRuntimeException () : void {
            $manyLines = implode("\n", range(1, 100));
            $thrown = false;

            try {
                FileDiff::compare(
                    $this->inlineFile($manyLines),
                    $this->inlineFile($manyLines),
                    maxLines: 10
                );
            }
            catch (RuntimeException) {
                $thrown = true;
            }

            $this->assertTrue($thrown, "compare() must throw RuntimeException when a file exceeds maxLines.");
        }

        #[Group("FileDiff")]
        #[Define(name: "Single-Line Files — Correct Operation Assignment", description: "Comparing two single-line files correctly classifies unchanged, added, and removed content.")]
        public function testSingleLineFilesProduceCorrectOperations () : void {
            $same = FileDiff::compare($this->inlineFile("same"), $this->inlineFile("same"));
            $this->assertTrue($same["hunks"][0]["operation"] === "unchanged", "Identical single-line files must produce an 'unchanged' hunk.");

            $different = FileDiff::compare($this->inlineFile("old"), $this->inlineFile("new"));
            $ops = array_column($different["hunks"], "operation");
            $this->assertTrue(in_array("removed", $ops) && in_array("added", $ops), "Different single-line files must produce 'removed' and 'added' hunks.");
        }

        #[Group("FileDiff")]
        #[Define(name: "Windows Line Endings — Handled Correctly", description: "Files with CRLF line endings are handled correctly; lines split at \\r\\n boundaries.")]
        public function testWindowsLineEndingsAreHandled () : void {
            $a = $this->inlineFile("line one\r\nline two");
            $b = $this->inlineFile("line one\r\nline two");

            $result = FileDiff::compare($a, $b);

            foreach ($result["hunks"] as $hunk) {
                $this->assertTrue($hunk["operation"] === "unchanged", "CRLF line endings must not cause false differences.");
            }
        }
    }
?>