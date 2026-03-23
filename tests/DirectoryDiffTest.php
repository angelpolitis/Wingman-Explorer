<?php
    /**
     * Project Name:    Wingman Explorer - DirectoryDiff Tests
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
    use Wingman\Argus\Attributes\Define;
    use Wingman\Argus\Attributes\Group;
    use Wingman\Argus\Test;
    use Wingman\Explorer\DirectoryDiff;
    use Wingman\Explorer\Resources\InlineFile;
    use Wingman\Explorer\Resources\VirtualDirectory;

    /**
     * Tests for the DirectoryDiff tree comparison utility.
     */
    class DirectoryDiffTest extends Test {
        /**
         * Builds a VirtualDirectory with the given named file contents.
         * @param array<string, string> $files Filename-to-content mapping.
         * @param string $name The directory name.
         * @return VirtualDirectory The virtual directory resource.
         */
        private function makeDir (array $files, string $name = "root") : VirtualDirectory {
            $contents = [];

            foreach ($files as $filename => $content) {
                $contents[$filename] = new InlineFile($content);
            }

            return new VirtualDirectory($name, $contents);
        }

        #[Group("DirectoryDiff")]
        #[Define(name: "Added Files — Detected as 'added'", description: "Files present in directory B but absent in directory A are listed in the 'added' key.")]
        public function testAddedFilesDetected () : void {
            $a = $this->makeDir(["shared.txt" => "same"]);
            $b = $this->makeDir(["shared.txt" => "same", "only_in_b.txt" => "new"]);

            $result = DirectoryDiff::compare($a, $b);

            $this->assertCount(1, $result["added"], "A file present only in B must appear in the 'added' list.");
            $this->assertCount(0, $result["removed"], "No files should be in 'removed' when A is a subset of B.");
        }

        #[Group("DirectoryDiff")]
        #[Define(name: "Removed Files — Detected as 'removed'", description: "Files present in directory A but absent in directory B are listed in the 'removed' key.")]
        public function testRemovedFilesDetected () : void {
            $a = $this->makeDir(["shared.txt" => "same", "only_in_a.txt" => "old"]);
            $b = $this->makeDir(["shared.txt" => "same"]);

            $result = DirectoryDiff::compare($a, $b);

            $this->assertCount(1, $result["removed"], "A file present only in A must appear in the 'removed' list.");
            $this->assertCount(0, $result["added"], "No files should be in 'added' when B is a subset of A.");
        }

        #[Group("DirectoryDiff")]
        #[Define(name: "Modified Files — Detected by Size Change", description: "Files with the same name but different sizes are listed in the 'modified' key.")]
        public function testModifiedFilesDetectedBySizeChange () : void {
            $a = $this->makeDir(["file.txt" => "short"]);
            $b = $this->makeDir(["file.txt" => "significantly longer content"]);

            $result = DirectoryDiff::compare($a, $b);

            $this->assertCount(1, $result["modified"], "A file whose size changed must appear in the 'modified' list.");
            $this->assertCount(0, $result["added"], "No files should be in 'added' when both directories share the same file names.");
        }

        #[Group("DirectoryDiff")]
        #[Define(name: "Unchanged Files — Not in Any Diff Key", description: "Files with the same name and identical size are not included in any of the diff buckets.")]
        public function testUnchangedFilesNotInAnyDiffKey () : void {
            $a = $this->makeDir(["same.txt" => "exact content"]);
            $b = $this->makeDir(["same.txt" => "exact content"]);

            $result = DirectoryDiff::compare($a, $b);

            $this->assertCount(0, $result["added"], "Unchanged files must not appear in 'added'.");
            $this->assertCount(0, $result["removed"], "Unchanged files must not appear in 'removed'.");
            $this->assertCount(0, $result["modified"], "Unchanged files must not appear in 'modified'.");
        }

        #[Group("DirectoryDiff")]
        #[Define(name: "Empty Directories — Produce Empty Diff", description: "Comparing two empty directories results in empty 'added', 'removed', and 'modified' lists.")]
        public function testEmptyDirectoriesProduceEmptyDiff () : void {
            $a = new VirtualDirectory("empty_a");
            $b = new VirtualDirectory("empty_b");

            $result = DirectoryDiff::compare($a, $b);

            $this->assertCount(0, $result["added"], "Two empty directories must have no added items.");
            $this->assertCount(0, $result["removed"], "Two empty directories must have no removed items.");
            $this->assertCount(0, $result["modified"], "Two empty directories must have no modified items.");
        }

        #[Group("DirectoryDiff")]
        #[Define(name: "Type Mismatch — Same Name, Different Types Treated as Modified", description: "When the same name exists in both directories but one is a file and the other is a directory, the item is listed as modified.")]
        public function testTypeMismatchTreatedAsModified () : void {
            $fileInA = new InlineFile("content");
            $dirInB = new VirtualDirectory("conflict");

            $a = new VirtualDirectory("root", ["conflict" => $fileInA]);
            $b = new VirtualDirectory("root", ["conflict" => $dirInB]);

            $result = DirectoryDiff::compare($a, $b);

            $this->assertCount(1, $result["modified"], "A name that changes type between A and B must be reported as modified.");
            $this->assertCount(0, $result["added"], "Type-mismatch items must not appear in 'added'.");
        }

        #[Group("DirectoryDiff")]
        #[Define(name: "Recursive Diff — Detects Changes in Nested Directories", description: "When recursive=true, differences in matching subdirectories are included in the result.")]
        public function testRecursiveDiffDetectsChangesInSubdirectories () : void {
            $subA = new VirtualDirectory("sub", ["inner.txt" => new InlineFile("old")]);
            $subB = new VirtualDirectory("sub", ["inner.txt" => new InlineFile("completely different and longer")]);

            $a = new VirtualDirectory("root", ["sub" => $subA]);
            $b = new VirtualDirectory("root", ["sub" => $subB]);

            $result = DirectoryDiff::compare($a, $b, recursive: true);

            $this->assertTrue(count($result["modified"]) > 0, "Recursive diff must detect changes in nested directories.");
        }

        #[Group("DirectoryDiff")]
        #[Define(name: "Non-Recursive Diff — Does Not Descend Into Subdirectories", description: "When recursive=false, nested subdirectory contents are not compared independently.")]
        public function testNonRecursiveDiffDoesNotDescendIntoSubdirectories () : void {
            $subA = new VirtualDirectory("sub", ["inner.txt" => new InlineFile("old")]);
            $subB = new VirtualDirectory("sub", ["new_file.txt" => new InlineFile("new")]);

            $a = new VirtualDirectory("root", ["sub" => $subA]);
            $b = new VirtualDirectory("root", ["sub" => $subB]);

            $result = DirectoryDiff::compare($a, $b, recursive: false);

            $this->assertCount(0, $result["added"], "Non-recursive diff must not report nested additions as top-level adds.");
            $this->assertCount(0, $result["removed"], "Non-recursive diff must not report nested removals as top-level removes.");
        }

        #[Group("DirectoryDiff")]
        #[Define(name: "Result Structure — Has Required Keys", description: "The compare() result array always contains 'added', 'removed', and 'modified' keys.")]
        public function testResultStructureHasRequiredKeys () : void {
            $a = new VirtualDirectory("a");
            $b = new VirtualDirectory("b");

            $result = DirectoryDiff::compare($a, $b);

            $this->assertArrayHasKey("added", $result, "Result must contain an 'added' key.");
            $this->assertArrayHasKey("removed", $result, "Result must contain a 'removed' key.");
            $this->assertArrayHasKey("modified", $result, "Result must contain a 'modified' key.");
        }

        #[Group("DirectoryDiff")]
        #[Define(name: "Multiple Files — All Added When A Is Empty", description: "When directory A is empty and directory B has multiple files, all files appear in 'added'.")]
        public function testAllFilesAddedWhenAIsEmpty () : void {
            $a = new VirtualDirectory("empty");
            $b = $this->makeDir(["one.txt" => "1", "two.txt" => "2", "three.txt" => "3"]);

            $result = DirectoryDiff::compare($a, $b);

            $this->assertCount(3, $result["added"], "All files in B must be reported as added when A is empty.");
            $this->assertCount(0, $result["removed"], "There must be no removed items when A is empty.");
        }

        #[Group("DirectoryDiff")]
        #[Define(name: "Multiple Files — All Removed When B Is Empty", description: "When directory B is empty and directory A has multiple files, all files appear in 'removed'.")]
        public function testAllFilesRemovedWhenBIsEmpty () : void {
            $a = $this->makeDir(["one.txt" => "1", "two.txt" => "2"]);
            $b = new VirtualDirectory("empty");

            $result = DirectoryDiff::compare($a, $b);

            $this->assertCount(0, $result["added"], "There must be no added items when B is empty.");
            $this->assertCount(2, $result["removed"], "All files from A must appear in 'removed' when B is empty.");
        }
    }
?>