<?php
    /**
     * Project Name:    Wingman Explorer - Stream Tests
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
    use Wingman\Explorer\Enums\StreamMode;
    use Wingman\Explorer\Exceptions\NotAStreamException;
    use Wingman\Explorer\Exceptions\StreamNotWritableException;
    use Wingman\Explorer\Exceptions\UnseekableStreamException;
    use Wingman\Explorer\IO\Stream;

    /**
     * Tests for the Stream class — open, read, write, seek, close and exception paths.
     */
    class StreamTest extends Test {
        /**
         * The temporary sandbox used for file-backed Stream tests.
         * @var string
         */
        private string $sandboxPath;

        /**
         * Creates the sandbox directory before each test.
         */
        protected function setUp () : void {
            $this->sandboxPath = dirname(__DIR__) . "/temp/explorer_stream_test_" . uniqid();
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

        // ─── Construction ──────────────────────────────────────────────────────

        #[Group("Stream")]
        #[Define(name: "Constructor — No Path Creates a Temp File", description: "When no path is given, Stream creates a temp file path automatically.")]
        public function testConstructorWithNoPathCreatesTempFile () : void {
            $stream = new Stream();

            $path = $stream->getPath();

            $this->assertTrue(is_string($path) && $path !== "", "A Stream without an explicit path must receive a non-empty temp file path.");
        }

        #[Group("Stream")]
        #[Define(name: "From — Creates Stream for Existing File Path", description: "Stream::from() creates a Stream instance whose path matches the given file.")]
        public function testFromCreatesStreamForGivenPath () : void {
            $filePath = $this->sandboxPath . "/from.txt";
            file_put_contents($filePath, "existing");

            $stream = Stream::from($filePath);

            $this->assertTrue($stream->getPath() === $filePath, "Stream::from() must set the path to the given file path.");
        }

        #[Group("Stream")]
        #[Define(name: "Create — Factory Returns New Stream", description: "Stream::create() returns a new Stream instance backed by a temp file.")]
        public function testCreateFactoryReturnsNewStream () : void {
            $stream = Stream::create();

            $this->assertTrue($stream instanceof Stream, "Stream::create() must return a Stream instance.");
        }

        // ─── Open / Close / IsOpen ─────────────────────────────────────────────

        #[Group("Stream")]
        #[Define(name: "Open — Stream Becomes Open After Open()", description: "Calling open() transitions the stream to an open state.")]
        public function testOpenMakesStreamOpen () : void {
            $stream = new Stream();
            $stream->open();

            $this->assertTrue($stream->isOpen(), "isOpen() must return true after calling open().");
        }

        #[Group("Stream")]
        #[Define(name: "Close — Stream Becomes Closed After Close()", description: "Calling close() after open() makes isOpen() return false.")]
        public function testCloseClosesStream () : void {
            $stream = new Stream();
            $stream->open();
            $stream->close();

            $this->assertTrue(!$stream->isOpen(), "isOpen() must return false after calling close().");
        }

        #[Group("Stream")]
        #[Define(name: "IsOpen — Returns False Before Opening", description: "isOpen() returns false when the stream has not been opened.")]
        public function testIsOpenReturnsFalseBeforeOpening () : void {
            $stream = new Stream();

            $this->assertTrue(!$stream->isOpen(), "isOpen() must return false before the stream has been opened.");
        }

        // ─── Write / Read ──────────────────────────────────────────────────────

        #[Group("Stream")]
        #[Define(name: "Write — Stores Content That Can Be Retrieved via ReadAll", description: "Writing a string to the stream and then calling readAll() returns the same string.")]
        public function testWriteAndReadAllRoundTrip () : void {
            $stream = new Stream();
            $stream->open();
            $stream->write("hello world");

            $stream->rewind();

            $content = $stream->readAll();

            $this->assertTrue($content === "hello world", "readAll() must return what was previously written.");
        }

        #[Group("Stream")]
        #[Define(name: "Write — Updates Stream Size", description: "The stream size reflects the number of bytes written.")]
        public function testWriteUpdatesStreamSize () : void {
            $stream = new Stream();
            $stream->open();
            $stream->write("12345"); # 5 bytes

            $this->assertTrue($stream->getSize() === 5, "getSize() must return the number of bytes written.");
        }

        #[Group("Stream")]
        #[Define(name: "Append — Adds Content to End of Stream", description: "append() adds content after the existing bytes without overwriting them.")]
        public function testAppendAddsContentAtEnd () : void {
            $stream = new Stream();
            $stream->open();
            $stream->write("first");
            $stream->append(" second");

            $stream->rewind();

            $content = $stream->readAll();

            $this->assertTrue($content === "first second", "append() must add content to the end of the stream.");
        }

        #[Group("Stream")]
        #[Define(name: "Read — Returns Requested Number of Bytes", description: "read(n) returns exactly n bytes from the current read position.")]
        public function testReadReturnsRequestedNumberOfBytes () : void {
            $filePath = $this->sandboxPath . "/chunk.txt";
            file_put_contents($filePath, "ABCDEFGH");

            $stream = Stream::from($filePath);
            $stream->open(StreamMode::READ);

            $chunk = $stream->read(4);

            $this->assertTrue($chunk === "ABCD", "read(4) must return the first 4 bytes.");
        }

        // ─── Writable / Readable ───────────────────────────────────────────────

        #[Group("Stream")]
        #[Define(name: "IsWritable — Returns True After Opening in Write Mode", description: "isWritable() returns true when the stream is opened in a writable mode.")]
        public function testIsWritableInWriteMode () : void {
            $stream = new Stream();
            $stream->open(StreamMode::WRITE_READ);

            $this->assertTrue($stream->isWritable(), "isWritable() must return true when opened in a writable mode.");
        }

        #[Group("Stream")]
        #[Define(name: "IsReadable — Returns True After Opening in Read Mode", description: "isReadable() returns true when the stream is opened in a readable mode.")]
        public function testIsReadableInReadMode () : void {
            $filePath = $this->sandboxPath . "/readable.txt";
            file_put_contents($filePath, "data");

            $stream = Stream::from($filePath);
            $stream->open(StreamMode::READ);

            $this->assertTrue($stream->isReadable(), "isReadable() must return true when opened in a readable mode.");
        }

        // ─── Seek ──────────────────────────────────────────────────────────────

        #[Group("Stream")]
        #[Define(name: "Seek — Moves Read Pointer to Given Position", description: "After seek(), subsequent reads start from the specified byte position.")]
        public function testSeekMovesReadPointer () : void {
            $filePath = $this->sandboxPath . "/seek.txt";
            file_put_contents($filePath, "0123456789");

            $stream = Stream::from($filePath);
            $stream->open(StreamMode::READ_WRITE);

            $stream->setReaderAt(5);

            $chunk = $stream->read(3);

            $this->assertTrue($chunk === "567", "Reading after seek to position 5 must return bytes starting at position 5.");
        }

        #[Group("Stream")]
        #[Define(name: "Seek — Throws UnseekableStreamException on Unopened Stream", description: "seek() throws UnseekableStreamException when called on a stream that has not been opened (seekable defaults to false).")]
        public function testSeekThrowsOnUnopenedStream () : void {
            $stream = new Stream();

            $threw = false;

            try {
                $stream->seek(0);
            }
            catch (UnseekableStreamException) {
                $threw = true;
            }

            $this->assertTrue($threw, "seek() must throw UnseekableStreamException when the stream has not been opened.");
        }

        // ─── Exception Paths ───────────────────────────────────────────────────

        #[Group("Stream")]
        #[Define(name: "For — Throws NotAStreamException for Non-Resource", description: "Stream::for() throws NotAStreamException when the given value is not a stream resource.")]
        public function testForThrowsNotAStreamExceptionForNonResource () : void {
            $threw = false;

            try {
                Stream::for("not a resource");
            }
            catch (NotAStreamException) {
                $threw = true;
            }

            $this->assertTrue($threw, "Stream::for() must throw NotAStreamException when given a non-resource value.");
        }

        #[Group("Stream")]
        #[Define(name: "Write — Throws StreamNotWritableException on Read-Only Stream", description: "write() throws StreamNotWritableException when the stream is opened in a read-only mode.")]
        public function testWriteThrowsStreamNotWritableExceptionOnReadOnlyStream () : void {
            $filePath = $this->sandboxPath . "/readonly.txt";
            file_put_contents($filePath, "content");

            $stream = Stream::from($filePath);
            $stream->open(StreamMode::READ);

            $threw = false;

            try {
                $stream->write("new data");
            }
            catch (StreamNotWritableException) {
                $threw = true;
            }

            $this->assertTrue($threw, "write() must throw StreamNotWritableException when the stream mode is read-only.");
        }

        #[Group("Stream")]
        #[Define(name: "ToString — Returns Full Content Without Throwing", description: "__toString() returns the full stream content as a string without raising an exception.")]
        public function testToStringReturnsFullContent () : void {
            $stream = new Stream();
            $stream->open();
            $stream->write("string content");
            $stream->rewind();

            $result = (string) $stream;

            $this->assertTrue($result === "string content", "__toString() must return the full content of the stream.");
        }

        #[Group("Stream")]
        #[Define(name: "ReadLine — Reads a Single Line", description: "readLine() reads up to the next newline character and returns that line.")]
        public function testReadLineReturnsSingleLine () : void {
            $filePath = $this->sandboxPath . "/lines.txt";
            file_put_contents($filePath, "first\nsecond\nthird");

            $stream = Stream::from($filePath);
            $stream->open(StreamMode::READ);

            $line = $stream->readLine();

            $this->assertTrue(str_starts_with($line, "first"), "readLine() must return the first line of the stream.");
        }
    }
?>