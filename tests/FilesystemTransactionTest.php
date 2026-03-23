<?php
    /**
     * Project Name:    Wingman Explorer - Filesystem Transaction Tests
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
    use Wingman\Explorer\Adapters\LocalAdapter;
    use Wingman\Explorer\FilesystemTransaction;

    /**
     * Tests for FilesystemTransaction — commit, rollback, and adapter interface guards.
     */
    class FilesystemTransactionTest extends Test {
        /**
         * The temporary sandbox used for all filesystem operations.
         * @var string
         */
        private string $sandboxPath;

        /**
         * The local adapter pointed at the sandbox.
         * @var LocalAdapter
         */
        private LocalAdapter $adapter;

        /**
         * Creates the sandbox directory and a local adapter before each test.
         */
        protected function setUp () : void {
            $this->sandboxPath = dirname(__DIR__) . "/temp/explorer_tx_test_" . uniqid();
            mkdir($this->sandboxPath, 0775, true);
            $this->adapter = new LocalAdapter();
        }

        /**
         * Cleans up the sandbox directory after each test.
         */
        protected function tearDown () : void {
            if (is_dir($this->sandboxPath)) {
                $this->removeDirectory($this->sandboxPath);
            }
        }

        /**
         * Recursively removes a directory and its contents.
         * @param string $path The directory path to remove.
         */
        private function removeDirectory (string $path) : void {
            foreach (scandir($path) as $entry) {
                if ($entry === '.' || $entry === '..') continue;

                $full = "$path/$entry";

                is_dir($full) ? $this->removeDirectory($full) : @unlink($full);
            }

            @rmdir($path);
        }

        // ─── CreateFile ────────────────────────────────────────────────────────

        #[Group("FilesystemTransaction")]
        #[Define(name: "CreateFile — File Exists After Commit", description: "After committing a createFile operation, the file exists on disk with the expected content.")]
        public function testCreateFileExistsAfterCommit () : void {
            $path = $this->sandboxPath . "/created.txt";
            $tx = new FilesystemTransaction($this->adapter);

            $tx->createFile($path, "initial content");
            $tx->commit();

            $this->assertTrue(file_exists($path), "The file must exist after committing a createFile operation.");
            $this->assertTrue(file_get_contents($path) === "initial content", "The file content must match what was passed to createFile().");
        }

        #[Group("FilesystemTransaction")]
        #[Define(name: "CreateFile — Rollback Removes Created File", description: "Rolling back after a createFile operation removes the newly created file.")]
        public function testCreateFileRollbackRemovesFile () : void {
            $path = $this->sandboxPath . "/rollback.txt";
            $tx = new FilesystemTransaction($this->adapter);

            $tx->createFile($path, "temp content");
            $tx->rollback();

            $this->assertTrue(!file_exists($path), "Rolling back a createFile operation must delete the created file.");
        }

        // ─── WriteFile ─────────────────────────────────────────────────────────

        #[Group("FilesystemTransaction")]
        #[Define(name: "WriteFile — Updates File Content After Commit", description: "After committing a writeFile operation, the file contains the new content.")]
        public function testWriteFileUpdatesContentAfterCommit () : void {
            $path = $this->sandboxPath . "/write.txt";
            file_put_contents($path, "original");

            $tx = new FilesystemTransaction($this->adapter);
            $tx->writeFile($path, "updated content");
            $tx->commit();

            $this->assertTrue(file_get_contents($path) === "updated content", "The file must contain the updated content after committing writeFile().");
        }

        #[Group("FilesystemTransaction")]
        #[Define(name: "WriteFile — Rollback Restores Original Content", description: "Rolling back a writeFile operation restores the file to its original content.")]
        public function testWriteFileRollbackRestoresOriginalContent () : void {
            $path = $this->sandboxPath . "/restore.txt";
            file_put_contents($path, "original content");

            $tx = new FilesystemTransaction($this->adapter);
            $tx->writeFile($path, "new content");
            $tx->rollback();

            $this->assertTrue(file_get_contents($path) === "original content", "Rolling back a writeFile must restore the original content.");
        }

        // ─── DeleteFile ────────────────────────────────────────────────────────

        #[Group("FilesystemTransaction")]
        #[Define(name: "DeleteFile — File Is Gone After Commit", description: "After committing a deleteFile operation, the file no longer exists on disk.")]
        public function testDeleteFileRemovedAfterCommit () : void {
            $path = $this->sandboxPath . "/delete.txt";
            file_put_contents($path, "to be deleted");

            $tx = new FilesystemTransaction($this->adapter);
            $tx->deleteFile($path);
            $tx->commit();

            $this->assertTrue(!file_exists($path), "The file must not exist after committing a deleteFile operation.");
        }

        #[Group("FilesystemTransaction")]
        #[Define(name: "DeleteFile — Rollback Restores Deleted File", description: "Rolling back a deleteFile operation recreates the file with its original content.")]
        public function testDeleteFileRollbackRestoresFile () : void {
            $path = $this->sandboxPath . "/deleterestore.txt";
            file_put_contents($path, "restorable content");

            $tx = new FilesystemTransaction($this->adapter);
            $tx->deleteFile($path);
            $tx->rollback();

            $this->assertTrue(file_exists($path), "Rolling back a deleteFile must recreate the file.");
            $this->assertTrue(file_get_contents($path) === "restorable content", "Rolling back a deleteFile must restore the original content.");
        }

        // ─── CreateDirectory ───────────────────────────────────────────────────

        #[Group("FilesystemTransaction")]
        #[Define(name: "CreateDirectory — Directory Exists After Commit", description: "After committing a createDirectory operation, the directory exists on disk.")]
        public function testCreateDirectoryExistsAfterCommit () : void {
            $path = $this->sandboxPath . "/newdir";
            $tx = new FilesystemTransaction($this->adapter);

            $tx->createDirectory($path);
            $tx->commit();

            $this->assertTrue(is_dir($path), "The directory must exist after committing a createDirectory operation.");
        }

        // ─── CopyFile ──────────────────────────────────────────────────────────

        #[Group("FilesystemTransaction")]
        #[Define(name: "CopyFile — Destination File Exists After Commit", description: "After committing a copyFile operation, the destination file exists and has the same content as the source.")]
        public function testCopyFileDestinationExistsAfterCommit () : void {
            $source = $this->sandboxPath . "/source.txt";
            $destination = $this->sandboxPath . "/destination.txt";
            file_put_contents($source, "copy me");

            $tx = new FilesystemTransaction($this->adapter);
            $tx->copyFile($source, $destination);
            $tx->commit();

            $this->assertTrue(file_exists($destination), "The destination file must exist after committing a copyFile operation.");
            $this->assertTrue(file_get_contents($destination) === "copy me", "The destination file must have the same content as the source.");
        }

        #[Group("FilesystemTransaction")]
        #[Define(name: "CopyFile — Rollback Removes Copied File", description: "Rolling back a copyFile operation removes the file that was copied to the destination.")]
        public function testCopyFileRollbackRemovesDestination () : void {
            $source = $this->sandboxPath . "/src.txt";
            $destination = $this->sandboxPath . "/dst.txt";
            file_put_contents($source, "content");

            $tx = new FilesystemTransaction($this->adapter);
            $tx->copyFile($source, $destination);
            $tx->rollback();

            $this->assertTrue(!file_exists($destination), "Rolling back a copyFile must remove the copied destination file.");
        }

        // ─── MoveFile ──────────────────────────────────────────────────────────

        #[Group("FilesystemTransaction")]
        #[Define(name: "MoveFile — Source Is Gone and Destination Exists After Commit", description: "After committing a moveFile operation, the source file is gone and the destination file exists.")]
        public function testMoveFileAfterCommit () : void {
            $source = $this->sandboxPath . "/movesrc.txt";
            $destination = $this->sandboxPath . "/movedst.txt";
            file_put_contents($source, "move me");

            $tx = new FilesystemTransaction($this->adapter);
            $tx->moveFile($source, $destination);
            $tx->commit();

            $this->assertTrue(!file_exists($source), "The source file must no longer exist after a committed moveFile.");
            $this->assertTrue(file_exists($destination), "The destination file must exist after a committed moveFile.");
        }

        #[Group("FilesystemTransaction")]
        #[Define(name: "MoveFile — Rollback Moves File Back to Source", description: "Rolling back a moveFile operation moves the file back to its original location.")]
        public function testMoveFileRollbackRestoresSource () : void {
            $source = $this->sandboxPath . "/originsrc.txt";
            $destination = $this->sandboxPath . "/origindst.txt";
            file_put_contents($source, "original");

            $tx = new FilesystemTransaction($this->adapter);
            $tx->moveFile($source, $destination);
            $tx->rollback();

            $this->assertTrue(file_exists($source), "Rolling back a moveFile must restore the file to the source path.");
        }

        // ─── Commit Clears Queue ───────────────────────────────────────────────

        #[Group("FilesystemTransaction")]
        #[Define(name: "Commit — Clears the Operations Queue", description: "After a successful commit, calling commit() again performs no additional operations.")]
        public function testCommitClearsOperationsQueue () : void {
            $path = $this->sandboxPath . "/once.txt";
            $tx = new FilesystemTransaction($this->adapter);

            $tx->createFile($path, "once");
            $tx->commit();

            @unlink($path);

            $threw = false;

            try {
                $tx->commit(); # Should do nothing — queue is empty.
            }
            catch (\Throwable) {
                $threw = true;
            }

            $this->assertTrue(!file_exists($path), "The file must not be recreated by a second commit on an empty queue.");
            $this->assertTrue(!$threw, "A second commit on an empty queue must not throw.");
        }

        // ─── Chaining ─────────────────────────────────────────────────────────

        #[Group("FilesystemTransaction")]
        #[Define(name: "Fluent Chaining — All Builder Methods Return the Transaction", description: "createFile(), writeFile(), deleteFile(), copyFile(), and moveFile() all return the transaction instance for chaining.")]
        public function testBuilderMethodsReturnTransaction () : void {
            $path = $this->sandboxPath . "/chain.txt";
            file_put_contents($path, "chain");

            $tx = new FilesystemTransaction($this->adapter);

            $returned = $tx->createFile($path . "2", "a")
                ->writeFile($path, "b")
                ->copyFile($path . "2", $path . "3");

            $this->assertTrue($returned === $tx, "Builder methods must return the same FilesystemTransaction instance.");

            $tx->rollback(); # Clean up.
        }
    }
?>