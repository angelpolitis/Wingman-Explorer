<?php
    /**
     * Project Name:    Wingman Explorer - Virtual Tree Compiler Tests
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
    use Wingman\Explorer\Resources\InlineFile;
    use Wingman\Explorer\Resources\ProxyFile;
    use Wingman\Explorer\Resources\VirtualDirectory;
    use Wingman\Explorer\VirtualTreeCompiler;

    /**
     * Tests for VirtualTreeCompiler::compile().
     */
    class VirtualTreeCompilerTest extends Test {
        /**
         * The temporary sandbox used for ProxyFile source paths.
         * @var string
         */
        private string $sandboxPath;

        /**
         * Creates the sandbox directory before each test.
         */
        protected function setUp () : void {
            $this->sandboxPath = dirname(__DIR__) . "/temp/explorer_vtc_test_" . uniqid();
            mkdir($this->sandboxPath, 0775, true);
        }

        /**
         * Cleans up the sandbox directory after each test.
         */
        protected function tearDown () : void {
            if (is_dir($this->sandboxPath)) {
                foreach (glob($this->sandboxPath . "/*") ?: [] as $file) {
                    @unlink($file);
                }
                @rmdir($this->sandboxPath);
            }
        }

        // ─── Happy Paths ───────────────────────────────────────────────────────

        #[Group("VirtualTreeCompiler")]
        #[Define(name: "Compile — Empty Root Directory", description: "Compiling a root directory with no children returns a VirtualDirectory with no contents.")]
        public function testCompileEmptyRootDirectory () : void {
            $result = VirtualTreeCompiler::compile(["type" => "directory"]);

            $this->assertTrue($result instanceof VirtualDirectory, "compile() must return a VirtualDirectory instance.");
            $this->assertTrue(count($result->getContents()) === 0, "An empty directory definition must produce a VirtualDirectory with no contents.");
        }

        #[Group("VirtualTreeCompiler")]
        #[Define(name: "Compile — Single Inline File Child", description: "A directory with one inline file child produces a VirtualDirectory containing an InlineFile.")]
        public function testCompileSingleInlineFileChild () : void {
            $definition = [
                "type" => "directory",
                "content" => [
                    "readme.txt" => ["type" => "file", "content" => "Hello, world!"]
                ]
            ];

            $result = VirtualTreeCompiler::compile($definition);

            $this->assertArrayHasKey("readme.txt", $result->getContents(), "The compiled directory must contain a 'readme.txt' key.");
            $this->assertTrue($result->getContents()["readme.txt"] instanceof InlineFile, "The child must be an InlineFile instance.");
        }

        #[Group("VirtualTreeCompiler")]
        #[Define(name: "Compile — Inline File Content Is Preserved", description: "The content of an inline file child is preserved in the compiled result.")]
        public function testCompileInlineFileContentPreserved () : void {
            $definition = [
                "type" => "directory",
                "content" => [
                    "data.txt" => ["type" => "file", "content" => "expected content"]
                ]
            ];

            $result = VirtualTreeCompiler::compile($definition);

            /** @var InlineFile $file */
            $file = $result->getContents()["data.txt"];

            $this->assertTrue($file->getContent() === "expected content", "The inline file content must match the definition.");
        }

        #[Group("VirtualTreeCompiler")]
        #[Define(name: "Compile — Proxy File From Source", description: "A file definition with a 'source' key produces a ProxyFile pointing to that source.")]
        public function testCompileProxyFileFromSource () : void {
            $sourcePath = $this->sandboxPath . "/asset.txt";
            file_put_contents($sourcePath, "asset data");

            $definition = [
                "type" => "directory",
                "content" => [
                    "asset.txt" => ["type" => "file", "source" => $sourcePath]
                ]
            ];

            $result = VirtualTreeCompiler::compile($definition);

            /** @var ProxyFile $file */
            $file = $result->getContents()["asset.txt"];

            $this->assertTrue($file instanceof ProxyFile, "A file definition with 'source' must produce a ProxyFile.");
            $this->assertTrue($file->getSource() === $sourcePath, "The ProxyFile must point to the configured source path.");
        }

        #[Group("VirtualTreeCompiler")]
        #[Define(name: "Compile — Nested Directory", description: "A nested directory definition compiles recursively into nested VirtualDirectory instances.")]
        public function testCompileNestedDirectory () : void {
            $definition = [
                "type" => "directory",
                "content" => [
                    "sub" => [
                        "type" => "directory",
                        "content" => [
                            "inner.txt" => ["type" => "file", "content" => "inner"]
                        ]
                    ]
                ]
            ];

            $result = VirtualTreeCompiler::compile($definition);

            $this->assertArrayHasKey("sub", $result->getContents(), "The root must contain the 'sub' key.");

            $sub = $result->getContents()["sub"];

            $this->assertTrue($sub instanceof VirtualDirectory, "'sub' must be a VirtualDirectory.");
            $this->assertArrayHasKey("inner.txt", $sub->getContents(), "The nested directory must contain 'inner.txt'.");
        }

        // ─── Sad Paths ─────────────────────────────────────────────────────────

        #[Group("VirtualTreeCompiler")]
        #[Define(name: "Compile — Throws When Root Is Not a Directory", description: "compile() throws RuntimeException when the root node type is not 'directory'.")]
        public function testCompileThrowsWhenRootIsNotDirectory () : void {
            $threw = false;

            try {
                VirtualTreeCompiler::compile(["type" => "file", "content" => "oops"]);
            }
            catch (RuntimeException) {
                $threw = true;
            }

            $this->assertTrue($threw, "compile() must throw RuntimeException when the root node is not a directory.");
        }

        #[Group("VirtualTreeCompiler")]
        #[Define(name: "Compile — Throws for Unknown Child Type", description: "compile() throws RuntimeException when a child node has an unrecognised type.")]
        public function testCompileThrowsForUnknownChildType () : void {
            $threw = false;

            $definition = [
                "type" => "directory",
                "content" => [
                    "weird" => ["type" => "symlink"]
                ]
            ];

            try {
                VirtualTreeCompiler::compile($definition);
            }
            catch (RuntimeException) {
                $threw = true;
            }

            $this->assertTrue($threw, "compile() must throw RuntimeException for an unknown child type.");
        }

        #[Group("VirtualTreeCompiler")]
        #[Define(name: "Compile — Throws When File Has Both Source and Content", description: "compile() throws RuntimeException when a file definition has both 'source' and 'content' keys.")]
        public function testCompileThrowsWhenFilHasBothSourceAndContent () : void {
            $threw = false;

            $definition = [
                "type" => "directory",
                "content" => [
                    "conflict.txt" => ["type" => "file", "source" => "/some/path", "content" => "inline"]
                ]
            ];

            try {
                VirtualTreeCompiler::compile($definition);
            }
            catch (RuntimeException) {
                $threw = true;
            }

            $this->assertTrue($threw, "compile() must throw RuntimeException when a file has both 'source' and 'content'.");
        }

        #[Group("VirtualTreeCompiler")]
        #[Define(name: "Compile — Throws When File Has Neither Source Nor Content", description: "compile() throws RuntimeException when a file definition has neither 'source' nor 'content'.")]
        public function testCompileThrowsWhenFileHasNeitherSourceNorContent () : void {
            $threw = false;

            $definition = [
                "type" => "directory",
                "content" => [
                    "empty.txt" => ["type" => "file"]
                ]
            ];

            try {
                VirtualTreeCompiler::compile($definition);
            }
            catch (RuntimeException) {
                $threw = true;
            }

            $this->assertTrue($threw, "compile() must throw RuntimeException when a file has neither 'source' nor 'content'.");
        }

        #[Group("VirtualTreeCompiler")]
        #[Define(name: "Compile — Throws When Directory Content Is Not an Array", description: "compile() throws RuntimeException when a directory's 'content' key is not an associative array.")]
        public function testCompileThrowsWhenDirectoryContentIsNotArray () : void {
            $threw = false;

            $definition = [
                "type" => "directory",
                "content" => "not an array"
            ];

            try {
                VirtualTreeCompiler::compile($definition);
            }
            catch (RuntimeException) {
                $threw = true;
            }

            $this->assertTrue($threw, "compile() must throw RuntimeException when a directory's 'content' is not an array.");
        }

        #[Group("VirtualTreeCompiler")]
        #[Define(name: "Compile — Throws When Child Node Has No Type", description: "compile() throws RuntimeException when a child node is missing the 'type' key altogether.")]
        public function testCompileThrowsWhenChildNodeHasNoType () : void {
            $threw = false;

            $definition = [
                "type" => "directory",
                "content" => [
                    "unnamed" => ["content" => "something"]
                ]
            ];

            try {
                VirtualTreeCompiler::compile($definition);
            }
            catch (RuntimeException) {
                $threw = true;
            }

            $this->assertTrue($threw, "compile() must throw RuntimeException when a child node is missing a 'type' key.");
        }
    }
?>