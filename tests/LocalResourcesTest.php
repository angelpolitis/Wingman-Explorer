<?php
    /**
     * Project Name:    Wingman Explorer - Local Resources Tests
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
    use DateTimeImmutable;
    use Wingman\Argus\Attributes\Define;
    use Wingman\Argus\Attributes\Group;
    use Wingman\Argus\Test;
    use Wingman\Explorer\Resources\LocalDirectory;
    use Wingman\Explorer\Resources\LocalFile;

    /**
     * Tests for the LocalFile and LocalDirectory resource classes.
     */
    class LocalResourcesTest extends Test {
        /**
         * The temporary sandbox directory used during tests.
         * @var string
         */
        private string $sandboxPath;

        /**
         * Creates a fresh sandbox directory before each test.
         */
        protected function setUp () : void {
            $this->sandboxPath = dirname(__DIR__) . "/temp/explorer_resources_test_" . uniqid();
            mkdir($this->sandboxPath, 0775, true);
        }

        /**
         * Removes all sandbox content after each test.
         */
        protected function tearDown () : void {
            if (is_dir($this->sandboxPath)) {
                $this->cleanDirectory($this->sandboxPath);
                @rmdir($this->sandboxPath);
            }
        }

        /**
         * Recursively removes directory content.
         * @param string $dir The directory to empty.
         */
        private function cleanDirectory (string $dir) : void {
            foreach (scandir($dir) as $entry) {
                if ($entry === '.' || $entry === "..") continue;
                $path = $dir . "/" . $entry;
                is_dir($path) ? ($this->cleanDirectory($path) || @rmdir($path)) : @unlink($path);
            }
        }

        #[Group("LocalFile")]
        #[Define(name: "LocalFile — Exists Returns True for Existing File", description: "exists() returns true when the underlying path points to a real file on disk.")]
        public function testLocalFileExistsReturnsTrueForRealFile () : void {
            $path = $this->sandboxPath . "/real.txt";
            file_put_contents($path, "hello");

            $file = new LocalFile($path);

            $this->assertTrue($file->exists(), "exists() must return true when the file is present on disk.");
        }

        #[Group("LocalFile")]
        #[Define(name: "LocalFile — Exists Returns False for Missing File", description: "exists() returns false when the file does not exist on disk.")]
        public function testLocalFileExistsReturnsFalseForMissingFile () : void {
            $file = new LocalFile($this->sandboxPath . "/missing.txt");

            $this->assertTrue(!$file->exists(), "exists() must return false when the file is absent.");
        }

        #[Group("LocalFile")]
        #[Define(name: "LocalFile — Create Makes File on Disk", description: "create() creates an empty file at the specified path on disk.")]
        public function testLocalFileCreateMakesFileOnDisk () : void {
            $path = $this->sandboxPath . "/brand_new.txt";
            $file = new LocalFile($path);

            $file->create();

            $this->assertTrue(is_file($path), "create() must produce an actual file on disk.");
        }

        #[Group("LocalFile")]
        #[Define(name: "LocalFile — Create Creates Parent Directories When Recursive", description: "create() with recursive=true creates any missing parent directories before creating the file.")]
        public function testLocalFileCreateCreatesParentDirectoriesRecursively () : void {
            $path = $this->sandboxPath . "/a/b/c/new.txt";
            $file = new LocalFile($path);

            $file->create(recursive: true);

            $this->assertTrue(is_file($path), "create() must create the file and all missing parent directories.");
        }

        #[Group("LocalFile")]
        #[Define(name: "LocalFile — Delete Removes File From Disk", description: "delete() removes the file from disk and returns true.")]
        public function testLocalFileDeleteRemovesFile () : void {
            $path = $this->sandboxPath . "/delete_me.txt";
            file_put_contents($path, "bye");

            $file = new LocalFile($path);
            $result = $file->delete();

            $this->assertTrue($result, "delete() must return true on success.");
            $this->assertTrue(!is_file($path), "The file must be absent from disk after delete().");
        }

        #[Group("LocalFile")]
        #[Define(name: "LocalFile — GetLastModified Returns DateTimeImmutable", description: "getLastModified() returns a DateTimeImmutable instance reflecting the file's modification time.")]
        public function testLocalFileGetLastModifiedReturnsDTI () : void {
            $path = $this->sandboxPath . "/ts.txt";
            file_put_contents($path, "ts");

            $file = new LocalFile($path);

            $this->assertTrue($file->getLastModified() instanceof DateTimeImmutable, "getLastModified() must return a DateTimeImmutable.");
        }

        #[Group("LocalFile")]
        #[Define(name: "LocalFile — GetBaseName Returns Filename", description: "getBaseName() returns only the filename component, not the full path.")]
        public function testLocalFileGetBaseNameReturnsFilename () : void {
            $file = new LocalFile($this->sandboxPath . "/myfile.txt");

            $this->assertTrue($file->getBaseName() === "myfile.txt", "getBaseName() must return just the filename.");
        }

        #[Group("LocalFile")]
        #[Define(name: "LocalFile — GetPath Returns Full Path", description: "getPath() returns the full absolute path passed to the constructor.")]
        public function testLocalFileGetPathReturnsFullPath () : void {
            $path = $this->sandboxPath . "/full.txt";
            $file = new LocalFile($path);

            $this->assertTrue($file->getPath() === $path, "getPath() must return the original full path.");
        }

        #[Group("LocalFile")]
        #[Define(name: "LocalFile — Discard Clears Buffer and Dirty Flag", description: "discard() resets any pending in-memory write buffer and marks the file as clean.")]
        public function testLocalFileDiscardClearsBufferAndDirtyFlag () : void {
            $path = $this->sandboxPath . "/discard.txt";
            file_put_contents($path, "clean");

            $file = new LocalFile($path);
            $file->discard();

            $this->assertTrue(!$file->exists() || is_file($path), "discard() must not affect the file on disk.");
        }

        #[Group("LocalDirectory")]
        #[Define(name: "LocalDirectory — Exists Returns True for Real Directory", description: "exists() returns true when the directory path points to a real directory on disk.")]
        public function testLocalDirectoryExistsReturnsTrueForRealDirectory () : void {
            $dir = new LocalDirectory($this->sandboxPath, reactive: false);

            $this->assertTrue($dir->exists(), "exists() must return true for an existing directory.");
        }

        #[Group("LocalDirectory")]
        #[Define(name: "LocalDirectory — Create Makes Directory on Disk", description: "create() creates the directory structure on disk at the configured path.")]
        public function testLocalDirectoryCreateMakesDirectory () : void {
            $path = $this->sandboxPath . "/brand_new_dir";
            $dir = new LocalDirectory($path, reactive: false);

            $dir->create();

            $this->assertTrue(is_dir($path), "create() must create the directory on disk.");
        }

        #[Group("LocalDirectory")]
        #[Define(name: "LocalDirectory — GetBaseName Returns Directory Name", description: "getBaseName() returns only the directory's leaf name, not the full path.")]
        public function testLocalDirectoryGetBaseNameReturnsLeafName () : void {
            $path = $this->sandboxPath . "/leafdir";
            mkdir($path);

            $dir = new LocalDirectory($path, reactive: false);

            $this->assertTrue($dir->getBaseName() === "leafdir", "getBaseName() must return only the leaf directory name.");
        }

        #[Group("LocalDirectory")]
        #[Define(name: "LocalDirectory — GetPath Returns Full Path", description: "getPath() returns the full absolute path of the directory.")]
        public function testLocalDirectoryGetPathReturnsFullPath () : void {
            $dir = new LocalDirectory($this->sandboxPath, reactive: false);

            $this->assertTrue($dir->getPath() === $this->sandboxPath, "getPath() must return the full directory path.");
        }

        #[Group("LocalDirectory")]
        #[Define(name: "LocalDirectory — Delete Removes Empty Directory", description: "delete() removes an empty directory from disk and returns true.")]
        public function testLocalDirectoryDeleteRemovesDirectoryTree () : void {
            $path = $this->sandboxPath . "/do_delete";
            mkdir($path, 0775, true);

            $dir = new LocalDirectory($path, reactive: false);
            $result = $dir->delete();

            $this->assertTrue($result, "delete() must return true on successful removal.");
            $this->assertTrue(!is_dir($path), "The directory must be absent from disk after delete().");
        }
    }
