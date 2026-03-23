<?php
    /**
     * Project Name:    Wingman Explorer - Local Files Demo
     * Created by:      Angel Politis
     * Creation Date:   Mar 22 2026
     * Last Modified:   Mar 22 2026
     *
     * Copyright (c) 2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Import the following classes to the current scope.
    use Wingman\Explorer\FileDiff;
    use Wingman\Explorer\FileUtils;
    use Wingman\Explorer\Resources\LocalFile;

    require_once __DIR__ . "/../autoload.php";

    # --------------------------------------------------------------------------------------
    # LOCAL FILE OVERVIEW
    #
    # LocalFile represents a physical file on the local filesystem.  All write
    # operations (write, prepend, replace, …) are buffered in memory and only
    # committed to disk when save() is called.  save() performs an atomic
    # rename so partial writes never corrupt the existing file.
    #
    # Common methods:
    #   ::at(string $path)                — create the PHP object (does not touch disk)
    #   ->create(bool $recursive)         — create an empty file on disk
    #   ->write(string $content)          — buffer a full-content replacement
    #   ->append(string $content)         — append directly to disk (or to buffer)
    #   ->prepend(string $content)        — prepend to current content
    #   ->save()                          — flush the buffer atomically to disk
    #   ->discard()                       — abandon unsaved buffer
    #   ->getContent()                    — read from buffer/temp if dirty, else disk
    #   ->getContentStream()              — same but returns a Stream
    #   ->replace(string $search, $repl)  — in-buffer search-and-replace (save needed)
    #   ->replacePattern(string $pattern) — regex variant
    #   ->delete()                        — remove the file from disk
    #   ->exists()                        — test presence on disk
    #   ->getSize()                       — file size in bytes
    #   ->getLastModified()               — DateTimeImmutable
    #   ->getMetadata()                   — inode, owner, permissions, timestamps, …
    #   ->getMD5() / ->getSHA1()          — content hashes
    # --------------------------------------------------------------------------------------

    $tmpDir = sys_get_temp_dir() . "/wm_explorer_local_files_demo_" . uniqid();
    mkdir($tmpDir, 0775, true);

    echo "=== CREATE ===\n\n";

    # --------------------------------------------------------------------------
    # ::at() just resolves the path — nothing on disk happens yet.
    # create() writes an empty file and creates missing parent directories
    # (recursive: true is the default).
    # --------------------------------------------------------------------------
    $notesPath = "$tmpDir/notes/daily.txt";
    $notesFile = LocalFile::at($notesPath)->create();

    echo "File exists after create(): " . ($notesFile->exists() ? "yes" : "no") . "\n";
    echo "Size of empty file:         " . $notesFile->getSize() . " bytes\n\n";

    echo "=== WRITE & SAVE ===\n\n";

    # --------------------------------------------------------------------------
    # write() stages the content in an in-memory buffer; save() atomically
    # persists it.  Nothing is written to disk between the two calls.
    # --------------------------------------------------------------------------
    $notesFile
        ->write("Meeting notes\n==============\n- Discuss roadmap\n- Review PRs\n")
        ->save();

    echo "Content after write + save:\n" . $notesFile->getContent() . "\n";

    echo "=== APPEND ===\n\n";

    # --------------------------------------------------------------------------
    # append() writes immediately to disk when the buffer is clean.  If there
    # is a pending buffered change it appends to the buffer instead.
    # --------------------------------------------------------------------------
    $notesFile->append("- Deploy staging\n");
    echo "Content after append:\n" . $notesFile->getContent() . "\n";

    echo "=== PREPEND ===\n\n";

    $notesFile->prepend("# Daily standup — 22 Mar 2026\n\n")->save();
    echo "Content after prepend + save:\n" . $notesFile->getContent() . "\n";

    echo "=== SEARCH & REPLACE ===\n\n";

    # --------------------------------------------------------------------------
    # replace() and replacePattern() stage the change in the buffer; call
    # save() afterwards to commit.
    # --------------------------------------------------------------------------
    $notesFile->replace("Review PRs", "Review & merge PRs")->save();
    echo "After replacing 'Review PRs':\n" . $notesFile->getContent() . "\n";

    $notesFile->replacePattern('/\bDeploy\b/', "Ship")->save();
    echo "After regex replacing 'Deploy' → 'Ship':\n" . $notesFile->getContent() . "\n";

    echo "=== DISCARD ===\n\n";

    # --------------------------------------------------------------------------
    # discard() abandons any in-memory buffer and removes a staging temp file
    # if one exists.  The file on disk is left untouched.
    # --------------------------------------------------------------------------
    $notesFile->write("This change will be abandoned.");
    echo "Content while dirty (before discard): " . substr($notesFile->getContent(), 0, 40) . "…\n";

    $notesFile->discard();
    echo "Content after discard (buffer gone):  " . substr($notesFile->getContent(), 0, 40) . "…\n\n";

    echo "=== STREAM ACCESS ===\n\n";

    # --------------------------------------------------------------------------
    # getContentStream() returns a readable Stream positioned at byte 0.
    # ---------------------------------------------------------------------------
    $stream = $notesFile->getContentStream();
    $firstLine = $stream->readLine();
    echo "First line via stream: $firstLine\n\n";

    echo "=== METADATA ===\n\n";

    # --------------------------------------------------------------------------
    # getSize(), getLastModified(), and getMetadata() read live data from disk.
    # --------------------------------------------------------------------------
    echo "Size:          " . FileUtils::getReadableSize($notesFile->getSize()) . "\n";
    echo "Last modified: " . $notesFile->getLastModified()->format("D d M Y H:i:s") . "\n";
    echo "MD5:           " . $notesFile->getMD5() . "\n";
    echo "SHA-1:         " . $notesFile->getSHA1() . "\n";

    $meta = $notesFile->getMetadata();
    echo "Owner (uid):   " . $meta["owner"] . "\n";
    echo "Permissions:   " . decoct($meta["permissions"] & 0777) . " (octal)\n\n";

    echo "=== FILE DIFF ===\n\n";

    # --------------------------------------------------------------------------
    # FileDiff::compare() performs a line-level LCS diff between any two
    # FileResource instances.  Use it to see what changed between two versions.
    # --------------------------------------------------------------------------
    $versionA = LocalFile::at("$tmpDir/v1.txt")->write("Line one\nLine two\nLine three\n")->save();
    $versionB = LocalFile::at("$tmpDir/v2.txt")->write("Line one\nLine TWO (modified)\nLine three\nLine four\n")->save();

    $diff = FileDiff::compare($versionA, $versionB);

    foreach ($diff["hunks"] as $hunk) {
        $prefix = match ($hunk["operation"]) {
            "added"     => "+ ",
            "removed"   => "- ",
            default     => "  ",
        };
        echo $prefix . rtrim($hunk["content"]) . "\n";
    }

    echo "\n=== COPY / MOVE ===\n\n";

    # --------------------------------------------------------------------------
    # LocalFile does not expose copy/move directly — use the adapter layer or
    # PHP's built-in functions with LocalFile paths.  For atomic multi-step
    # operations see the filesystem-transactions demo.
    # --------------------------------------------------------------------------
    $originalPath = "$tmpDir/original.txt";
    $copyPath     = "$tmpDir/copy.txt";

    LocalFile::at($originalPath)->write("Original content.\n")->save();

    copy($originalPath, $copyPath);
    echo "Copied to: $copyPath  — content: " . LocalFile::at($copyPath)->getContent() . "\n";

    echo "=== DELETE ===\n\n";

    $result = $notesFile->delete();
    echo "delete() returned: " . ($result ? "true" : "false") . "\n";
    echo "File exists after delete: " . ($notesFile->exists() ? "yes" : "no") . "\n";

    # --------------------------------------------------------------------------
    # Cleanup
    # --------------------------------------------------------------------------
    foreach (glob("$tmpDir/*") ?: [] as $f) {
        is_dir($f) ? rmdir($f) : unlink($f);
    }

    foreach (glob("$tmpDir/notes") ?: [] as $d) rmdir($d);
    @rmdir("$tmpDir/notes");
    @rmdir($tmpDir);
?>