<?php
    /**
     * Project Name:    Wingman Explorer - Filesystem Transactions Demo
     * Created by:      Angel Politis
     * Creation Date:   Mar 22 2026
     * Last Modified:   Mar 22 2026
     *
     * Copyright (c) 2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Import the following classes to the current scope.
    use Wingman\Explorer\Adapters\LocalAdapter;
    use Wingman\Explorer\Exceptions\FilesystemException;
    use Wingman\Explorer\FilesystemTransaction;

    require_once __DIR__ . "/../autoload.php";

    # --------------------------------------------------------------------------------------
    # FILESYSTEM TRANSACTION OVERVIEW
    #
    # FilesystemTransaction queues a sequence of write operations and applies them
    # atomically when commit() is called.  If any operation throws during commit, all
    # previously completed steps are rolled back in reverse order.
    #
    # The adapter is checked for each operation type:
    #   createFile, writeFile      — requires WritableFilesystemAdapterInterface
    #   deleteFile, copyFile,
    #   moveFile                   — requires MovableFilesystemAdapterInterface
    #   createDirectory            — requires WritableFilesystemAdapterInterface
    #
    # Operations queue until commit(); rollback() can be called manually at any time.
    #
    # API:
    #   ->createFile(string $path, string $content = "")
    #   ->writeFile(string $path, string $content)
    #   ->deleteFile(string $path)
    #   ->copyFile(string $source, string $destination)
    #   ->moveFile(string $source, string $destination)
    #   ->createDirectory(string $path, bool $recursive, int $permissions)
    #   ->commit()        — execute all queued ops; auto-rollback on failure
    #   ->rollback(?int $limit) — undo the last $limit ops (null = all)
    # --------------------------------------------------------------------------------------

    $tmpDir = sys_get_temp_dir() . "/wm_explorer_tx_demo_" . uniqid();
    mkdir($tmpDir, 0775, true);

    $adapter = new LocalAdapter();

    echo "=== SUCCESSFUL TRANSACTION ===\n\n";

    # --------------------------------------------------------------------------
    # All queued operations are applied only after commit() is called.  Nothing
    # is written to disk while chaining the builder methods.
    # --------------------------------------------------------------------------
    $tx = new FilesystemTransaction($adapter);

    $tx->createDirectory("$tmpDir/releases",      recursive: true)
       ->createDirectory("$tmpDir/releases/v1.0", recursive: true)
       ->createFile("$tmpDir/releases/v1.0/app.php",    "<?php\n// v1.0 entry point\n")
       ->createFile("$tmpDir/releases/v1.0/config.json", '{"version":"1.0","debug":false}')
       ->createFile("$tmpDir/releases/CHANGELOG.md",    "## v1.0.0\n- Initial release\n")
       ->copyFile("$tmpDir/releases/v1.0/config.json", "$tmpDir/releases/v1.0/config.backup.json")
       ->writeFile("$tmpDir/releases/CHANGELOG.md", "## v1.0.0\n- Initial release\n\n## Pending\n- TBD\n");

    $tx->commit();
    echo "Committed successfully.\n";

    $committed = [
        "$tmpDir/releases/v1.0/app.php",
        "$tmpDir/releases/v1.0/config.json",
        "$tmpDir/releases/v1.0/config.backup.json",
        "$tmpDir/releases/CHANGELOG.md",
    ];

    foreach ($committed as $path) {
        $exists = is_file($path) ? "exists" : "MISSING";
        echo "  " . basename($path) . ": $exists\n";
    }

    echo "\nCHANGELOG.md content after writeFile override:\n";
    echo file_get_contents("$tmpDir/releases/CHANGELOG.md") . "\n";

    echo "=== COPY & MOVE IN A TRANSACTION ===\n\n";

    # --------------------------------------------------------------------------
    # moveFile is a renaming operation on disk; it is rolled back by reversing
    # the rename.  copyFile's rollback deletes the destination copy.
    # --------------------------------------------------------------------------
    $txMove = new FilesystemTransaction($adapter);

    $txMove->createFile("$tmpDir/releases/v1.0/notes.txt", "Release notes\n")
           ->moveFile("$tmpDir/releases/v1.0/notes.txt", "$tmpDir/releases/v1.0/NOTES.txt")
           ->copyFile("$tmpDir/releases/v1.0/NOTES.txt",  "$tmpDir/releases/NOTES.txt");

    $txMove->commit();

    echo "notes.txt exists after moveFile:         " . (is_file("$tmpDir/releases/v1.0/notes.txt")  ? "yes (BAD)" : "no (expected)") . "\n";
    echo "NOTES.txt exists (dest of moveFile):     " . (is_file("$tmpDir/releases/v1.0/NOTES.txt") ? "yes" : "no") . "\n";
    echo "Top-level NOTES.txt exists (copyFile):   " . (is_file("$tmpDir/releases/NOTES.txt") ? "yes" : "no") . "\n\n";

    echo "=== AUTOMATIC ROLLBACK ON FAILURE ===\n\n";

    # --------------------------------------------------------------------------
    # If any operation throws during commit() the transaction automatically calls
    # rollback() and re-throws the originating exception.
    #
    # Here we provoke a failure by asking the transaction to copy a file that
    # does not exist.  The files created before the failure must be absent
    # afterwards.
    # --------------------------------------------------------------------------
    $markerA = "$tmpDir/rollback_a.txt";
    $markerB = "$tmpDir/rollback_b.txt";

    $txFail = new FilesystemTransaction($adapter);
    $txFail->createFile($markerA, "Marker A\n")
           ->createFile($markerB, "Marker B\n")
           ->copyFile("$tmpDir/does_not_exist.txt", "$tmpDir/impossible_copy.txt");

    try {
        $txFail->commit();
        echo "Commit succeeded — this should not happen.\n";
    }
    catch (FilesystemException $e) {
        echo "Commit failed as expected: " . $e->getMessage() . "\n\n";
    }

    echo "Marker A exists after rollback: " . (is_file($markerA) ? "yes (BAD)" : "no (rolled back)") . "\n";
    echo "Marker B exists after rollback: " . (is_file($markerB) ? "yes (BAD)" : "no (rolled back)") . "\n\n";

    echo "=== MANUAL PARTIAL ROLLBACK ===\n\n";

    # --------------------------------------------------------------------------
    # rollback() accepts an optional $limit to undo only the last N completed
    # steps instead of the entire history.
    # --------------------------------------------------------------------------
    $fileP = "$tmpDir/partial_p.txt";
    $fileQ = "$tmpDir/partial_q.txt";
    $fileR = "$tmpDir/partial_r.txt";

    $txPartial = new FilesystemTransaction($adapter);
    $txPartial->createFile($fileP, "Step P\n")
              ->createFile($fileQ, "Step Q\n")
              ->createFile($fileR, "Step R\n");

    $txPartial->commit();
    echo "After commit P, Q, R all exist:\n";
    echo "  P: " . (is_file($fileP) ? "yes" : "no") . "\n";
    echo "  Q: " . (is_file($fileQ) ? "yes" : "no") . "\n";
    echo "  R: " . (is_file($fileR) ? "yes" : "no") . "\n\n";

    # Roll back only the last 1 operation (R).
    $txPartial->rollback(limit: 1);

    echo "After rollback(limit: 1) only R is reverted:\n";
    echo "  P: " . (is_file($fileP) ? "yes" : "no") . "\n";
    echo "  Q: " . (is_file($fileQ) ? "yes" : "no") . "\n";
    echo "  R: " . (is_file($fileR) ? "yes (BAD)" : "no (rolled back)") . "\n\n";

    # Roll back the remaining history (P and Q).
    $txPartial->rollback();

    echo "After full rollback() P and Q are reverted:\n";
    echo "  P: " . (is_file($fileP) ? "yes (BAD)" : "no (rolled back)") . "\n";
    echo "  Q: " . (is_file($fileQ) ? "yes (BAD)" : "no (rolled back)") . "\n";

    echo "\n=== DELETEFILEFILE WITHIN A TRANSACTION ===\n\n";

    # --------------------------------------------------------------------------
    # deleteFile() performs an unlink.  Its rollback re-creates the file by
    # re-writing the original content that was read before deletion.
    # --------------------------------------------------------------------------
    $toDelete = "$tmpDir/delete_me.txt";
    file_put_contents($toDelete, "This file will be deleted and restored.\n");

    $txDelete = new FilesystemTransaction($adapter);
    $txDelete->deleteFile($toDelete);

    # Intentionally roll back before committing to verify the file is preserved.
    $txDelete->commit();
    echo "File deleted by transaction: " . (is_file($toDelete) ? "still exists" : "gone") . "\n";

    # --------------------------------------------------------------------------
    # Cleanup
    # --------------------------------------------------------------------------
    $cleanupWalk = function (string $dir) use (&$cleanupWalk) : void {
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === "..") continue;
            $path = "$dir/$entry";
            is_dir($path) ? ($cleanupWalk($path) ?: rmdir($path)) : unlink($path);
        }
        rmdir($dir);
    };

    $cleanupWalk($tmpDir);
?>