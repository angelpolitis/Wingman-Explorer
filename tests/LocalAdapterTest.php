<?php
    /**
     * Project Name:    Wingman Explorer - Local Adapter Tests
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
    use Wingman\Explorer\Adapters\LocalAdapter;
    use Wingman\Explorer\Exceptions\FilesystemException;

    /**
     * Tests for the LocalAdapter filesystem adapter.
     */
    class LocalAdapterTest extends Test {
        /**
         * The temporary directory used as sandbox for all filesystem operations.
         * @var string
         */
        private string $sandboxPath;

        /**
         * The adapter under test.
         * @var LocalAdapter
         */
        private LocalAdapter $adapter;

        /**
         * Creates a fresh sandbox directory before each test.
         */
        protected function setUp () : void {
            $this->sandboxPath = dirname(__DIR__) . "/temp/explorer_adapter_test_" . uniqid();
            mkdir($this->sandboxPath, 0775, true);
            $this->adapter = new LocalAdapter();
        }

        /**
         * Removes the sandbox directory and all its contents after each test.
         */
        protected function tearDown () : void {
            if (is_dir($this->sandboxPath)) {
                $this->cleanDirectory($this->sandboxPath);
                @rmdir($this->sandboxPath);
            }
        }

        /**
         * Recursively removes a directory's content, used for tearDown.
         * @param string $dir The directory to clean.
         */
        private function cleanDirectory (string $dir) : void {
            foreach (scandir($dir) as $entry) {
                if ($entry === '.' || $entry === "..") continue;
                $path = $dir . "/" . $entry;
                is_dir($path) ? ($this->cleanDirectory($path) || @rmdir($path)) : @unlink($path);
            }
        }

        #[Group("LocalAdapter")]
        #[Define(name: "Exists — True for Existing File", description: "exists() returns true when the target path is an existing file.")]
        public function testExistsReturnsTrueForExistingFile () : void {
            $path = $this->sandboxPath . "/exists.txt";
            file_put_contents($path, "hello");

            $this->assertTrue($this->adapter->exists($path), "exists() must return true for an existing file.");
        }

        #[Group("LocalAdapter")]
        #[Define(name: "Exists — False for Missing Path", description: "exists() returns false when the path does not exist on disk.")]
        public function testExistsReturnsFalseForMissingPath () : void {
            $path = $this->sandboxPath . "/nonexistent.txt";

            $this->assertTrue(!$this->adapter->exists($path), "exists() must return false for a missing path.");
        }

        #[Group("LocalAdapter")]
        #[Define(name: "Create — Writes Content to File", description: "create() creates a file on disk containing the given initial content.")]
        public function testCreateWritesContentToFile () : void {
            $path = $this->sandboxPath . "/created.txt";
            $this->adapter->create($path, "init content");

            $this->assertTrue(is_file($path), "create() should produce an actual file on disk.");
            $this->assertTrue(file_get_contents($path) === "init content", "The file must contain the content passed to create().");
        }

        #[Group("LocalAdapter")]
        #[Define(name: "Read — Returns File Content", description: "read() returns the full content of an existing file.")]
        public function testReadReturnsFileContent () : void {
            $path = $this->sandboxPath . "/readable.txt";
            file_put_contents($path, "read me");

            $content = $this->adapter->read($path);

            $this->assertTrue($content === "read me", "read() must return the file's full content.");
        }

        #[Group("LocalAdapter")]
        #[Define(name: "Read — Throws for Missing File", description: "read() throws FilesystemException when the file does not exist.")]
        public function testReadThrowsForMissingFile () : void {
            $thrown = false;

            try {
                $this->adapter->read($this->sandboxPath . "/missing.txt");
            }
            catch (FilesystemException) {
                $thrown = true;
            }

            $this->assertTrue($thrown, "read() must throw FilesystemException for a non-existent file.");
        }

        #[Group("LocalAdapter")]
        #[Define(name: "Write — Creates File With Content", description: "write() creates a file when it does not yet exist and writes the given content.")]
        public function testWriteCreatesFileWithContent () : void {
            $path = $this->sandboxPath . "/written.txt";
            $this->adapter->write($path, "written content");

            $this->assertTrue(is_file($path), "write() should create the file if it does not exist.");
            $this->assertTrue(file_get_contents($path) === "written content", "The created file must contain the written content.");
        }

        #[Group("LocalAdapter")]
        #[Define(name: "Write — Overwrites Existing Content", description: "write() atomically replaces a file's content when the file already exists.")]
        public function testWriteOverwritesExistingContent () : void {
            $path = $this->sandboxPath . "/overwrite.txt";
            file_put_contents($path, "old content");
            $this->adapter->write($path, "new content");

            $this->assertTrue(file_get_contents($path) === "new content", "write() must replace the file's existing content.");
        }

        #[Group("LocalAdapter")]
        #[Define(name: "Delete — Removes an Existing File", description: "delete() removes the file from disk and returns true.")]
        public function testDeleteRemovesExistingFile () : void {
            $path = $this->sandboxPath . "/to_delete.txt";
            file_put_contents($path, "bye");

            $result = $this->adapter->delete($path);

            $this->assertTrue($result, "delete() must return true on success.");
            $this->assertTrue(!file_exists($path), "The file must be gone after delete().");
        }

        #[Group("LocalAdapter")]
        #[Define(name: "Delete — Returns True for Non-Existent Path", description: "delete() returns true without error when the path does not exist.")]
        public function testDeleteReturnsTrueForNonExistentPath () : void {
            $result = $this->adapter->delete($this->sandboxPath . "/ghost.txt");

            $this->assertTrue($result, "delete() should return true when the target does not exist.");
        }

        #[Group("LocalAdapter")]
        #[Define(name: "Delete — Recursively Removes a Directory", description: "delete() removes a directory and all of its contents recursively.")]
        public function testDeleteRecursivelyRemovesDirectory () : void {
            $dir = $this->sandboxPath . "/nested";
            mkdir($dir . "/deep", 0775, true);
            file_put_contents($dir . "/deep/file.txt", "content");

            $result = $this->adapter->delete($dir);

            $this->assertTrue($result, "delete() must return true after removing a directory tree.");
            $this->assertTrue(!is_dir($dir), "The entire directory tree must be removed.");
        }

        #[Group("LocalAdapter")]
        #[Define(name: "Copy — Duplicates a File", description: "copy() creates a copy of the source file at the destination path.")]
        public function testCopyDuplicatesFile () : void {
            $src = $this->sandboxPath . "/source.txt";
            $dst = $this->sandboxPath . "/copy.txt";
            file_put_contents($src, "copy me");

            $this->adapter->copy($src, $dst);

            $this->assertTrue(is_file($dst), "copy() must create the destination file.");
            $this->assertTrue(file_get_contents($dst) === "copy me", "The copied file must have the same content as the source.");
        }

        #[Group("LocalAdapter")]
        #[Define(name: "Copy — Throws FilesystemException on Failure", description: "copy() throws FilesystemException when the source file does not exist.")]
        public function testCopyThrowsOnFailure () : void {
            $thrown = false;

            try {
                $this->adapter->copy($this->sandboxPath . "/no_src.txt", $this->sandboxPath . "/dst.txt");
            }
            catch (FilesystemException) {
                $thrown = true;
            }

            $this->assertTrue($thrown, "copy() must throw FilesystemException when the source file does not exist.");
        }

        #[Group("LocalAdapter")]
        #[Define(name: "Move — Renames a File", description: "move() moves the file from source to destination, leaving no trace at the source.")]
        public function testMoveRenamesFile () : void {
            $src = $this->sandboxPath . "/original.txt";
            $dst = $this->sandboxPath . "/moved.txt";
            file_put_contents($src, "move me");

            $this->adapter->move($src, $dst);

            $this->assertTrue(!is_file($src), "The source must no longer exist after move().");
            $this->assertTrue(is_file($dst), "The destination file must exist after move().");
            $this->assertTrue(file_get_contents($dst) === "move me", "The moved file must preserve its content.");
        }

        #[Group("LocalAdapter")]
        #[Define(name: "Move — Throws FilesystemException on Failure", description: "move() throws FilesystemException when the source does not exist.")]
        public function testMoveThrowsOnFailure () : void {
            $thrown = false;

            try {
                $this->adapter->move($this->sandboxPath . "/no_src.txt", $this->sandboxPath . "/dst.txt");
            }
            catch (FilesystemException) {
                $thrown = true;
            }

            $this->assertTrue($thrown, "move() must throw FilesystemException when the source does not exist.");
        }

        #[Group("LocalAdapter")]
        #[Define(name: "CreateDirectory — Creates a Single-Level Directory", description: "createDirectory() creates the given path when no parent creation is needed.")]
        public function testCreateDirectoryCreatesSingleLevelPath () : void {
            $path = $this->sandboxPath . "/newdir";

            $result = $this->adapter->createDirectory($path);

            $this->assertTrue($result, "createDirectory() must return true on success.");
            $this->assertTrue(is_dir($path), "The directory must exist on disk after createDirectory().");
        }

        #[Group("LocalAdapter")]
        #[Define(name: "CreateDirectory — Creates Nested Path Recursively", description: "createDirectory() creates all intermediate directories when the recursive flag is set.")]
        public function testCreateDirectoryCreatesNestedPathRecursively () : void {
            $path = $this->sandboxPath . "/a/b/c";

            $result = $this->adapter->createDirectory($path, recursive: true);

            $this->assertTrue($result, "createDirectory() must return true when creating recursively.");
            $this->assertTrue(is_dir($path), "All intermediate directories must be created when recursive is true.");
        }

        #[Group("LocalAdapter")]
        #[Define(name: "CreateDirectory — Returns True for Existing Directory", description: "createDirectory() returns true without error when called on a path that already exists.")]
        public function testCreateDirectoryReturnsTrueForExistingDirectory () : void {
            $path = $this->sandboxPath;

            $result = $this->adapter->createDirectory($path);

            $this->assertTrue($result, "createDirectory() must return true when the directory already exists.");
        }

        #[Group("LocalAdapter")]
        #[Define(name: "GetMetadata — Returns Expected Keys", description: "getMetadata() returns an associative array containing at minimum path, name, type, and size keys.")]
        public function testGetMetadataReturnsExpectedKeys () : void {
            $path = $this->sandboxPath . "/meta.txt";
            file_put_contents($path, "meta");

            $meta = $this->adapter->getMetadata($path);

            $this->assertArrayHasKey("path", $meta, "Metadata must include the 'path' key.");
            $this->assertArrayHasKey("name", $meta, "Metadata must include the 'name' key.");
            $this->assertArrayHasKey("type", $meta, "Metadata must include the 'type' key.");
            $this->assertArrayHasKey("size", $meta, "Metadata must include the 'size' key.");
        }

        #[Group("LocalAdapter")]
        #[Define(name: "GetMetadata — Type Is 'file' for a File", description: "getMetadata() returns type='file' for a regular file path.")]
        public function testGetMetadataReturnsFileType () : void {
            $path = $this->sandboxPath . "/typed.txt";
            file_put_contents($path, "x");

            $meta = $this->adapter->getMetadata($path);

            $this->assertTrue($meta["type"] === "file", "getMetadata() must report type='file' for a regular file.");
        }

        #[Group("LocalAdapter")]
        #[Define(name: "GetMetadata — Type Is 'dir' for a Directory", description: "getMetadata() returns type='dir' for a directory path.")]
        public function testGetMetadataReturnsDirType () : void {
            $meta = $this->adapter->getMetadata($this->sandboxPath);

            $this->assertTrue($meta["type"] === "dir", "getMetadata() must report type='dir' for a directory.");
        }

        #[Group("LocalAdapter")]
        #[Define(name: "GetMetadata — Throws for Non-Existent Path", description: "getMetadata() throws FilesystemException when the path does not exist.")]
        public function testGetMetadataThrowsForNonExistentPath () : void {
            $thrown = false;

            try {
                $this->adapter->getMetadata($this->sandboxPath . "/ghost.txt");
            }
            catch (FilesystemException) {
                $thrown = true;
            }

            $this->assertTrue($thrown, "getMetadata() must throw FilesystemException for a non-existent path.");
        }

        #[Group("LocalAdapter")]
        #[Define(name: "GetMetadata — Selective Properties", description: "getMetadata() returns only the properties explicitly requested when a property list is provided.")]
        public function testGetMetadataReturnsOnlyRequestedProperties () : void {
            $path = $this->sandboxPath . "/selective.txt";
            file_put_contents($path, "sel");

            $meta = $this->adapter->getMetadata($path, ["name", "type"]);

            $this->assertArrayHasKey("name", $meta, "Requested property 'name' must be present.");
            $this->assertArrayHasKey("type", $meta, "Requested property 'type' must be present.");
            $this->assertTrue(!array_key_exists("size", $meta), "Unrequested property 'size' must not be present.");
        }

        #[Group("LocalAdapter")]
        #[Define(name: "List — Yields Paths of Directory Contents", description: "list() yields each immediate child path when given a valid directory path.")]
        public function testListYieldsDirectoryContents () : void {
            file_put_contents($this->sandboxPath . "/a.txt", "a");
            file_put_contents($this->sandboxPath . "/b.txt", "b");

            $paths = iterator_to_array($this->adapter->list($this->sandboxPath), false);

            $this->assertCount(2, $paths, "list() must yield exactly two entries matching the two files created.");
        }

        #[Group("LocalAdapter")]
        #[Define(name: "List — Throws for Non-Directory Path", description: "list() throws FilesystemException when given a non-directory path.")]
        public function testListThrowsForNonDirectoryPath () : void {
            $path = $this->sandboxPath . "/file.txt";
            file_put_contents($path, "x");

            $thrown = false;

            try {
                iterator_to_array($this->adapter->list($path));
            }
            catch (FilesystemException) {
                $thrown = true;
            }

            $this->assertTrue($thrown, "list() must throw FilesystemException when given a file path instead of a directory.");
        }

        #[Group("LocalAdapter")]
        #[Define(name: "Rename — Delegates to Move", description: "rename() moves the file to the new path, leaving the original absent.")]
        public function testRenameMovesFile () : void {
            $src = $this->sandboxPath . "/rename_src.txt";
            $dst = $this->sandboxPath . "/rename_dst.txt";
            file_put_contents($src, "rename me");

            $this->adapter->rename($src, $dst);

            $this->assertTrue(!is_file($src), "The source must not exist after rename().");
            $this->assertTrue(is_file($dst), "The destination must exist after rename().");
        }
    }
?>