<?php
    /**
     * Project Name:    Wingman Explorer - Virtual Files Demo
     * Created by:      Angel Politis
     * Creation Date:   Mar 22 2026
     * Last Modified:   Mar 22 2026
     *
     * Copyright (c) 2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Import the following classes to the current scope.
    use Wingman\Explorer\Resources\GeneratedFile;
    use Wingman\Explorer\Resources\InlineFile;
    use Wingman\Explorer\Resources\ProxyFile;
    use Wingman\Explorer\Resources\VirtualDirectory;
    use Wingman\Explorer\VirtualTreeCompiler;

    require_once __DIR__ . "/../autoload.php";

    # --------------------------------------------------------------------------------------
    # VIRTUAL FILES & DIRECTORIES OVERVIEW
    #
    # Virtual resources are in-memory objects with no physical filesystem backing.
    # They are always considered to "exist" and are useful for building in-memory
    # document trees, staging content for export, and testing code that consumes
    # the standard FileResource / DirectoryResource contracts.
    #
    # Three concrete VirtualFile subtypes:
    #   InlineFile($content)     — wraps a fixed string
    #   GeneratedFile($callable) — produces content lazily on first access
    #   ProxyFile($sourcePath)   — delegates reads to a real file on disk
    #
    # VirtualDirectory   — an in-memory tree node that can contain any Resource
    # VirtualTreeCompiler — compiles a plain-array definition into a tree
    # --------------------------------------------------------------------------------------

    $tmpDir = sys_get_temp_dir() . "/wm_explorer_virtual_demo_" . uniqid();
    mkdir($tmpDir, 0775, true);

    echo "=== InlineFile ===\n\n";

    # --------------------------------------------------------------------------
    # InlineFile holds a fixed string.  It supports the full editing trait set:
    # replace(), replacePattern(), getContent(), getContentStream(), etc.
    # Mutations (write/replace) are applied in memory — nothing touches the disk.
    # --------------------------------------------------------------------------
    $inline = new InlineFile("Hello, World!\nThis is an inline virtual file.\n");

    echo "exists():     " . ($inline->exists() ? "yes" : "no") . "\n";
    echo "getSize():    " . $inline->getSize() . " bytes\n";
    echo "getContent():\n" . $inline->getContent() . "\n";

    # InlineFile supports search and replace just like a LocalFile.
    $inline->replace("World", "Explorer");
    echo "After replace('World' → 'Explorer'):\n" . $inline->getContent() . "\n";

    # Serialisation preserves the current content.
    $serialised   = serialize($inline);
    $deserialised = unserialize($serialised);
    echo "Round-trip serialisation matches: " . ($inline->getContent() === $deserialised->getContent() ? "yes" : "no") . "\n\n";

    echo "=== GeneratedFile ===\n\n";

    # --------------------------------------------------------------------------
    # GeneratedFile accepts a callable that is invoked only when getContent()
    # is first called.  Once invoked (or once write() is called), the value is
    # frozen and the generator is no longer consulted.
    # --------------------------------------------------------------------------
    $callCount = 0;

    $generated = new GeneratedFile(function () use (&$callCount) {
        $callCount++;
        return "Generated at " . date("H:i:s") . " (call #$callCount)\n";
    });

    echo "Before first access — call count: $callCount\n";
    echo "First  getContent(): " . $generated->getContent();
    echo "Second getContent(): " . $generated->getContent();
    echo "Generator call count (should be 1): $callCount\n\n";

    # write() freezes the content to the new string; the callable is discarded.
    $generated->write("Frozen content after write().\n");
    echo "After write(), getContent(): " . $generated->getContent() . "\n\n";

    echo "=== ProxyFile ===\n\n";

    # --------------------------------------------------------------------------
    # ProxyFile delegates getContent() to a real file on disk via file_get_contents.
    # Metadata (size, last modified, permissions) is also read from the real file.
    # Useful for including real files in a VirtualDirectory tree.
    # --------------------------------------------------------------------------
    $realPath = "$tmpDir/real-config.json";
    file_put_contents($realPath, json_encode(["debug" => true, "version" => "1.0.0"], JSON_PRETTY_PRINT));

    $proxy = new ProxyFile($realPath);

    echo "getSource():  " . $proxy->getSource() . "\n";
    echo "getSize():    " . $proxy->getSize() . " bytes\n";
    echo "getContent():\n" . $proxy->getContent() . "\n\n";

    # Updating the underlying file is immediately reflected by the proxy.
    file_put_contents($realPath, json_encode(["debug" => false, "version" => "1.1.0"], JSON_PRETTY_PRINT));
    echo "getContent() after underlying file changes:\n" . $proxy->getContent() . "\n\n";

    echo "=== VirtualDirectory ===\n\n";

    # --------------------------------------------------------------------------
    # VirtualDirectory is an in-memory tree node.  Children are keyed by name
    # and can be any mix of FileResource and DirectoryResource instances.
    # --------------------------------------------------------------------------
    $config  = new InlineFile('{"debug":false}');
    $readme  = new InlineFile("# My Package\nSee docs/ for details.\n");
    $docsDir = new VirtualDirectory("docs", [
        "api.md"       => new InlineFile("## API Reference\n"),
        "guide.md"     => new InlineFile("## Getting Started\n"),
    ]);

    $root = new VirtualDirectory("project", [
        "README.md"   => $readme,
        "config.json" => $config,
        "docs"        => $docsDir,
    ]);

    echo "Root directory name:  " . $root->getBaseName() . "\n";
    echo "Root child count:     " . count($root->getContents()) . "\n";

    echo "\nChildren:\n";
    foreach ($root->getContents() as $name => $child) {
        $type = $child instanceof VirtualDirectory ? "dir " : "file";
        echo "  [$type] $name\n";
    }

    echo "\nDive into docs/:\n";
    $docsNode = $root->getDirectory("docs");
    foreach ($docsNode->getContents() as $name => $child) {
        echo "  $name — " . strlen($child->getContent()) . " bytes\n";
    }

    echo "\nSearch for *.md in root (non-recursive):\n";
    foreach ($root->search("*.md") as $match) {
        echo "  $match\n";
    }

    echo "\nSearch for *.md recursively:\n";
    foreach ($root->search("*.md", recursive: true) as $match) {
        echo "  $match\n";
    }

    echo "\n=== VirtualTreeCompiler ===\n\n";

    # --------------------------------------------------------------------------
    # VirtualTreeCompiler::compile() turns a plain associative-array definition
    # into an equivalent VirtualDirectory tree.  Two file node variants:
    #   "content" → InlineFile  (literal string content)
    #   "source"  → ProxyFile   (delegation to a real file on disk)
    # --------------------------------------------------------------------------
    $tree = VirtualTreeCompiler::compile([
        "type"    => "directory",
        "content" => [
            "README.md" => [
                "type"    => "file",
                "content" => "# Welcome to Explorer\n",
            ],
            "config.json" => [
                "type"    => "file",
                "source"  => $realPath,
            ],
            "src" => [
                "type"    => "directory",
                "content" => [
                    "App.php" => [
                        "type"    => "file",
                        "content" => "<?php\n// Application entry\n",
                    ],
                    "Router.php" => [
                        "type"    => "file",
                        "content" => "<?php\n// URL router\n",
                    ],
                ],
            ],
        ],
    ]);

    echo "Compiled tree root children:\n";
    foreach ($tree->getContents() as $name => $child) {
        $type = $child instanceof VirtualDirectory ? "dir " : "file";
        echo "  [$type] $name\n";
    }

    echo "\nREADME.md content:\n" . $tree->getFile("README.md")->getContent() . "\n";
    echo "config.json (ProxyFile) content:\n" . $tree->getFile("config.json")->getContent() . "\n";

    $srcNode = $tree->getDirectory("src");
    echo "src/ children:\n";
    foreach ($srcNode->getContents() as $name => $child) {
        echo "  $name\n";
    }

    echo "\n=== SERIALISE A VIRTUAL TREE ===\n\n";

    # --------------------------------------------------------------------------
    # VirtualDirectory and InlineFile / GeneratedFile are all PHP-serialisable.
    # ProxyFile serialises by capturing the in-memory snapshot at the time of
    # serialisation and restoring it as a frozen InlineFile on unserialise.
    # --------------------------------------------------------------------------
    $serial      = serialize($root);
    $restored    = unserialize($serial);

    echo "Serialised → unserialised root name:          " . $restored->getBaseName() . "\n";
    echo "README.md content survived round-trip:        " . ($restored->getFile("README.md")->getContent() === $readme->getContent() ? "yes" : "no") . "\n";

    # --------------------------------------------------------------------------
    # Cleanup
    # --------------------------------------------------------------------------
    array_map("unlink", glob("$tmpDir/*") ?: []);
    rmdir($tmpDir);
?>