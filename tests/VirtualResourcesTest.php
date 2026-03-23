<?php
    /**
     * Project Name:    Wingman Explorer - Virtual Resources Tests
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
    use Wingman\Explorer\Resources\GeneratedFile;
    use Wingman\Explorer\Resources\InlineFile;
    use Wingman\Explorer\Resources\ProxyFile;
    use Wingman\Explorer\Resources\VirtualDirectory;

    /**
     * Tests for virtual resource classes: VirtualDirectory, InlineFile, GeneratedFile, ProxyFile.
     */
    class VirtualResourcesTest extends Test {
        /**
         * The temporary sandbox used for ProxyFile tests.
         * @var string
         */
        private string $sandboxPath;

        /**
         * Creates the sandbox directory before each test.
         */
        protected function setUp () : void {
            $this->sandboxPath = dirname(__DIR__) . "/temp/explorer_virtual_test_" . uniqid();
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

        // ─── VirtualDirectory ──────────────────────────────────────────────────

        #[Group("VirtualDirectory")]
        #[Define(name: "Exists — Always Returns True", description: "VirtualDirectory::exists() always returns true because virtual directories are in-memory constructs.")]
        public function testVirtualDirectoryAlwaysExists () : void {
            $dir = new VirtualDirectory("mydir");

            $this->assertTrue($dir->exists(), "VirtualDirectory::exists() must always return true.");
        }

        #[Group("VirtualDirectory")]
        #[Define(name: "GetBaseName — Returns Configured Name", description: "getBaseName() returns the name provided to the constructor.")]
        public function testVirtualDirectoryGetBaseNameReturnsConfiguredName () : void {
            $dir = new VirtualDirectory("foobar");

            $this->assertTrue($dir->getBaseName() === "foobar", "getBaseName() must return the name given at construction.");
        }

        #[Group("VirtualDirectory")]
        #[Define(name: "Add — Adds Resource to Contents", description: "add() places the given resource in the directory and it is retrievable via getContents().")]
        public function testVirtualDirectoryAddStoresResource () : void {
            $dir = new VirtualDirectory("container");
            $file = new InlineFile("hello");

            $dir->add($file, "greet.txt");

            $contents = $dir->getContents();

            $this->assertArrayHasKey("greet.txt", $contents, "The added resource must be accessible by name via getContents().");
        }

        #[Group("VirtualDirectory")]
        #[Define(name: "Add — Returns the Added Resource", description: "add() returns the resource that was just added.")]
        public function testVirtualDirectoryAddReturnsResource () : void {
            $dir = new VirtualDirectory("container");
            $file = new InlineFile("data");

            $returned = $dir->add($file, "data.txt");

            $this->assertTrue($returned === $file, "add() must return the same resource object that was added.");
        }

        #[Group("VirtualDirectory")]
        #[Define(name: "AdoptFile — Registers File Under Its Own BaseName", description: "adoptFile() without an explicit name registers the file using its own base name.")]
        public function testVirtualDirectoryAdoptFileUsesFileBaseName () : void {
            $dir = new VirtualDirectory("container");
            $file = new InlineFile("content");

            $dir->adoptFile($file, "adopted.txt");

            $contents = $dir->getContents();

            $this->assertArrayHasKey("adopted.txt", $contents, "adoptFile() must store the file under the provided name.");
        }

        #[Group("VirtualDirectory")]
        #[Define(name: "Serialization — Reconstructs Name and Contents", description: "Serialising and deserialising a VirtualDirectory preserves its name and contents.")]
        public function testVirtualDirectorySerialisationRoundTrip () : void {
            $file = new InlineFile("payload");
            $dir = new VirtualDirectory("mydir", ["file.txt" => $file]);

            $serialised = serialize($dir);
            /** @var VirtualDirectory $restored */
            $restored = unserialize($serialised);

            $this->assertTrue($restored->getBaseName() === "mydir", "The name must be preserved after serialisation.");
            $this->assertArrayHasKey("file.txt", $restored->getContents(), "Contents must be preserved after serialisation.");
        }

        // ─── InlineFile ────────────────────────────────────────────────────────

        #[Group("InlineFile")]
        #[Define(name: "GetContent — Returns Provided Content", description: "getContent() returns the exact string passed to the constructor.")]
        public function testInlineFileGetContentReturnsContent () : void {
            $file = new InlineFile("exact content string");

            $this->assertTrue($file->getContent() === "exact content string", "getContent() must return the content provided at construction.");
        }

        #[Group("InlineFile")]
        #[Define(name: "GetSize — Returns Byte Length of Content", description: "getSize() returns the number of bytes in the inline content string.")]
        public function testInlineFileGetSizeReturnsByteLength () : void {
            $content = "hello"; # 5 bytes in UTF-8
            $file = new InlineFile($content);

            $this->assertTrue($file->getSize() === 5, "getSize() must return the byte length of the content string.");
        }

        #[Group("InlineFile")]
        #[Define(name: "GetSize — Empty Content Returns Zero", description: "getSize() returns 0 for an InlineFile with empty content.")]
        public function testInlineFileGetSizeZeroForEmptyContent () : void {
            $file = new InlineFile("");

            $this->assertTrue($file->getSize() === 0, "getSize() must return 0 for an InlineFile with empty content.");
        }

        #[Group("InlineFile")]
        #[Define(name: "JsonSerialize — Contains Type and Content Keys", description: "jsonSerialize() returns an array with at least a 'type' key set to 'inline_file' and a 'content' key.")]
        public function testInlineFileJsonSerializeContainsTypeAndContent () : void {
            $file = new InlineFile("json payload");
            $data = $file->jsonSerialize();

            $this->assertArrayHasKey("type", $data, "jsonSerialize() must include a 'type' key.");
            $this->assertTrue($data["type"] === "inline_file", "The 'type' key must be 'inline_file'.");
            $this->assertArrayHasKey("content", $data, "jsonSerialize() must include a 'content' key.");
        }

        #[Group("InlineFile")]
        #[Define(name: "Serialization — Round Trip Preserves Content", description: "Serialising and deserialising an InlineFile restores the same content.")]
        public function testInlineFileSerialisationPreservesContent () : void {
            $file = new InlineFile("preserved content");

            $serialised = serialize($file);
            /** @var InlineFile $restored */
            $restored = unserialize($serialised);

            $this->assertTrue($restored->getContent() === "preserved content", "Content must be preserved after serialisation.");
        }

        // ─── GeneratedFile ─────────────────────────────────────────────────────

        #[Group("GeneratedFile")]
        #[Define(name: "GetContent — Invokes Generator Callable", description: "getContent() calls the generator function each time and returns its output.")]
        public function testGeneratedFileInvokesGeneratorCallable () : void {
            $file = new GeneratedFile(fn() => "generated content");

            $this->assertTrue($file->getContent() === "generated content", "getContent() must return the result of invoking the generator callable.");
        }

        #[Group("GeneratedFile")]
        #[Define(name: "GetContent — Returns Empty String on Generator Exception", description: "getContent() silently returns an empty string when the generator throws an exception.")]
        public function testGeneratedFileSilentlyHandlesGeneratorException () : void {
            $file = new GeneratedFile(fn() => throw new \RuntimeException("generator failed"));

            $content = $file->getContent();

            $this->assertTrue($content === "", "getContent() must return '' when the generator callable throws.");
        }

        #[Group("GeneratedFile")]
        #[Define(name: "GetSize — Returns Byte Length of Generated Content", description: "getSize() returns the byte length of the content produced by the generator.")]
        public function testGeneratedFileGetSizeReturnsByteLength () : void {
            $file = new GeneratedFile(fn() => "12345"); # 5 bytes

            $this->assertTrue($file->getSize() === 5, "getSize() must return the byte length of the generated content.");
        }

        #[Group("GeneratedFile")]
        #[Define(name: "Serialization — Resolves Generator and Preserves Output", description: "Serialising a GeneratedFile captures the generated value; after deserialisation getContent() returns the same value.")]
        public function testGeneratedFileSerialisationCapturesOutput () : void {
            $file = new GeneratedFile(fn() => "frozen value");

            $serialised = serialize($file);
            /** @var GeneratedFile $restored */
            $restored = unserialize($serialised);

            $this->assertTrue($restored->getContent() === "frozen value", "The generated value must survive a serialisation round-trip.");
        }

        #[Group("GeneratedFile")]
        #[Define(name: "JsonSerialize — Contains Type 'generated_file'", description: "jsonSerialize() returns an array with a 'type' key set to 'generated_file'.")]
        public function testGeneratedFileJsonSerializeContainsType () : void {
            $file = new GeneratedFile(fn() => "data");
            $json = $file->jsonSerialize();

            $this->assertArrayHasKey("type", $json, "jsonSerialize() must include a 'type' key.");
            $this->assertTrue($json["type"] === "generated_file", "The 'type' key must be 'generated_file'.");
        }

        // ─── ProxyFile ─────────────────────────────────────────────────────────

        #[Group("ProxyFile")]
        #[Define(name: "GetContent — Reads From Source File", description: "getContent() returns the content of the file at the configured source path.")]
        public function testProxyFileGetContentReadsFromSourcePath () : void {
            $sourcePath = $this->sandboxPath . "/source.txt";
            file_put_contents($sourcePath, "source content");

            $proxy = new ProxyFile($sourcePath);

            $this->assertTrue($proxy->getContent() === "source content", "getContent() must return the content of the source file.");
        }

        #[Group("ProxyFile")]
        #[Define(name: "GetSize — Returns Source File Size", description: "getSize() returns the byte size of the file at the source path.")]
        public function testProxyFileGetSizeReturnsSourceFileSize () : void {
            $sourcePath = $this->sandboxPath . "/sized.txt";
            file_put_contents($sourcePath, "12345"); # 5 bytes

            $proxy = new ProxyFile($sourcePath);

            $this->assertTrue($proxy->getSize() === 5, "getSize() must return the size in bytes of the source file.");
        }

        #[Group("ProxyFile")]
        #[Define(name: "GetSource — Returns the Configured Source Path", description: "getSource() returns exactly the path that was supplied to the constructor.")]
        public function testProxyFileGetSourceReturnsConfiguredPath () : void {
            $sourcePath = "/some/path/file.txt";
            $proxy = new ProxyFile($sourcePath);

            $this->assertTrue($proxy->getSource() === "/some/path/file.txt", "getSource() must return the original source path.");
        }

        #[Group("ProxyFile")]
        #[Define(name: "GetContent — Returns Empty String for Missing Source", description: "getContent() returns an empty string when the source file does not exist.")]
        public function testProxyFileGetContentReturnsEmptyForMissingSource () : void {
            $proxy = new ProxyFile($this->sandboxPath . "/missing.txt");

            $content = $proxy->getContent();

            $this->assertTrue($content === "", "getContent() must return '' when the source file does not exist.");
        }
    }
?>