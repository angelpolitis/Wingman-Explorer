<?php
    /**
     * Project Name:    Wingman Explorer - Local Directories Demo
     * Created by:      Angel Politis
     * Creation Date:   Mar 22 2026
     * Last Modified:   Mar 22 2026
     *
     * Copyright (c) 2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Import the following classes to the current scope.
    use Wingman\Explorer\FileUtils;
    use Wingman\Explorer\Resources\LocalDirectory;
    use Wingman\Explorer\Resources\LocalFile;

    require_once __DIR__ . "/../autoload.php";

    # --------------------------------------------------------------------------------------
    # LOCAL DIRECTORY OVERVIEW
    #
    # LocalDirectory models a physical directory.  It features reactive change detection
    # via inotify on Linux (falling back to MD5-signature polling on other platforms), but
    # in this demo we disable reactivity to keep things simple.
    #
    # Common methods:
    #   ::at(string $path)                      — create the PHP object (does not touch disk)
    #   new LocalDirectory($path, reactive: false)
    #   ->create(bool $recursive, int $perms)   — create the directory on disk
    #   ->createFile(string $name)              — create a LocalFile inside this directory
    #   ->add(Resource $item, ?string $newName) — adopt an existing resource into the dir
    #   ->remove($item)                         — delete a child by instance / index / name
    #   ->getContents()                         — dirs first, then files, sorted alpha
    #   ->getFiles()                            — LocalFile[] only
    #   ->getDirectories()                      — LocalDirectory[] only
    #   ->getFile(int|string $key)              — find by index or base name
    #   ->getDirectory(int|string $key)         — find by index or base name
    #   ->search(string $pattern, bool $recursive)
    #   ->flatten()                             — all descendants as a flat LocalFile[]
    #   ->copy(string $destination)             — copy directory tree
    #   ->move(string $destination)             — rename / move directory tree
    #   ->delete()                              — delete empty directory
    #   ->deleteRecursive()                     — delete directory and all contents
    #   ->isEmpty()                             — whether the directory has no children
    #   ->refresh()                             — re-read contents from disk
    #   ->getSize()                             — total occupied bytes (recursive)
    #   ->getLastModified()                     — DateTimeImmutable
    #   ->getMetadata()                         — permissions, owner, timestamps, …
    # --------------------------------------------------------------------------------------

    $tmpDir = sys_get_temp_dir() . "/wm_explorer_local_dirs_demo_" . uniqid();
    mkdir($tmpDir, 0775, true);

    echo "=== CREATE ===\n\n";

    # --------------------------------------------------------------------------
    # Create a nested directory tree.  reactive: false prevents inotify/polling
    # setup which is unnecessary for short-lived scripts.
    # --------------------------------------------------------------------------
    $projectDir = new LocalDirectory("$tmpDir/project", reactive: false);
    $projectDir->create(recursive: true);

    $srcDir  = new LocalDirectory("$tmpDir/project/src",     reactive: false);
    $testDir = new LocalDirectory("$tmpDir/project/tests",   reactive: false);
    $docsDir = new LocalDirectory("$tmpDir/project/docs",    reactive: false);

    $srcDir->create();
    $testDir->create();
    $docsDir->create();

    echo "Directory exists: " . ($projectDir->exists() ? "yes" : "no") . "\n";
    echo "Initially empty:  " . ($projectDir->isEmpty() ? "yes" : "no") . "\n\n";

    echo "=== CREATE FILES INSIDE A DIRECTORY ===\n\n";

    # --------------------------------------------------------------------------
    # createFile() creates an empty LocalFile under this directory and returns it.
    # --------------------------------------------------------------------------
    $readme   = $projectDir->createFile("README.md");
    $makefile = $projectDir->createFile("Makefile");

    $readme->write("# My Project\nA demo project.\n")->save();
    $makefile->write("build:\n\tphp -l src/\n")->save();

    echo "Files created via createFile():\n";
    echo " - " . $readme->getBaseName() . "\n";
    echo " - " . $makefile->getBaseName() . "\n\n";

    # Add several source files.
    $srcDir->createFile("App.php")->write("<?php\n// Application entry point\n")->save();
    $srcDir->createFile("Router.php")->write("<?php\n// URL router\n")->save();
    $srcDir->createFile("Config.php")->write("<?php\n// Config loader\n")->save();

    $testDir->createFile("AppTest.php")->write("<?php\n// Tests for App\n")->save();
    $testDir->createFile("RouterTest.php")->write("<?php\n// Tests for Router\n")->save();

    $docsDir->createFile("api.md")->write("## API Reference\n")->save();

    echo "=== LIST CONTENTS ===\n\n";

    # --------------------------------------------------------------------------
    # getContents() returns an ordered array: directories first, then files.
    # getFiles() / getDirectories() give typed subsets.
    # --------------------------------------------------------------------------
    $projectDir->refresh();

    echo "Direct children of /project:\n";
    foreach ($projectDir->getContents() as $name => $resource) {
        $type = $resource instanceof LocalFile ? "file" : "dir";
        echo "  [{$type}] $name\n";
    }

    echo "\nFiles at root only: ";
    echo implode(", ", array_map(fn ($f) => $f->getBaseName(), $projectDir->getFiles())) . "\n";

    echo "\nSubdirectories: ";
    echo implode(", ", array_map(fn ($d) => $d->getBaseName(), $projectDir->getDirectories())) . "\n\n";

    echo "=== FIND BY NAME / INDEX ===\n\n";

    # --------------------------------------------------------------------------
    # getFile() and getDirectory() accept either the base name or a 0-based index.
    # --------------------------------------------------------------------------
    $foundFile = $projectDir->getFile("README.md");
    echo "Found README.md content:\n" . $foundFile->getContent() . "\n";

    $foundDir = $projectDir->getDirectory("src");
    echo "Found src/ with " . count($foundDir->getFiles()) . " files\n\n";

    echo "=== SEARCH ===\n\n";

    # --------------------------------------------------------------------------
    # search() does a glob-style pattern match.  recursive:true descends into
    # all subdirectories.
    # --------------------------------------------------------------------------
    $phpFiles = $projectDir->search("*.php", recursive: true);

    echo "*.php files (recursive):\n";
    foreach ($phpFiles as $path) {
        echo "  $path\n";
    }

    $mdFiles = $projectDir->search("*.md", recursive: true);
    echo "\n*.md files (recursive):\n";
    foreach ($mdFiles as $path) {
        echo "  $path\n";
    }

    echo "\n=== FLATTEN ===\n\n";

    # --------------------------------------------------------------------------
    # flatten() returns every descendant file as a flat LocalFile[] — no
    # directory entries, regardless of nesting depth.
    # --------------------------------------------------------------------------
    $all = $projectDir->flatten();

    echo "All " . count($all) . " descendant files:\n";
    foreach ($all as $file) {
        echo "  " . $file->getPath() . "\n";
    }

    echo "\n=== SIZE & METADATA ===\n\n";

    echo "Total size: " . FileUtils::getReadableSize($projectDir->getSize()) . "\n";
    echo "Last mod:   " . $projectDir->getLastModified()->format("D d M Y H:i:s") . "\n";

    $meta = $projectDir->getMetadata();
    echo "Permissions: " . decoct($meta["permissions"] & 0777) . " (octal)\n\n";

    echo "=== COPY ===\n\n";

    # --------------------------------------------------------------------------
    # copy() produces a full recursive clone at the destination path and
    # returns a LocalDirectory pointing to it.
    # --------------------------------------------------------------------------
    $copyDest = "$tmpDir/project_backup";
    $backup   = $projectDir->copy($copyDest);

    echo "Backup created at: " . $backup->getPath() . "\n";
    echo "Backup has "  . count($backup->flatten()) . " files\n\n";

    echo "=== REMOVE A CHILD ===\n\n";

    # --------------------------------------------------------------------------
    # remove() deletes a direct child (by instance, base name, or index).
    # --------------------------------------------------------------------------
    $projectDir->remove("Makefile");
    $projectDir->refresh();

    $remainingFiles = array_map(fn ($f) => $f->getBaseName(), $projectDir->getFiles());
    echo "Root files after removing Makefile: " . implode(", ", $remainingFiles) . "\n\n";

    echo "=== MOVE / RENAME ===\n\n";

    # --------------------------------------------------------------------------
    # move() renames the directory on disk and updates the internal path so the
    # object remains usable.
    # --------------------------------------------------------------------------
    $movedDest = "$tmpDir/project_v2";
    $projectDir->move($movedDest);

    echo "Directory after move:  " . $projectDir->getPath() . "\n";
    echo "Still exists:          " . ($projectDir->exists() ? "yes" : "no") . "\n\n";

    echo "=== DELETE RECURSIVE ===\n\n";

    $projectDir->deleteRecursive();
    echo "After deleteRecursive(), exists: " . ($projectDir->exists() ? "yes" : "no") . "\n";

    # --------------------------------------------------------------------------
    # Cleanup
    # --------------------------------------------------------------------------
    $cleanDir = new LocalDirectory($tmpDir, reactive: false);
    $cleanDir->deleteRecursive();
    @rmdir($tmpDir);
?>