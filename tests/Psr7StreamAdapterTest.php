<?php
    /**
     * Project Name:    Wingman Explorer - PSR-7 Stream Adapter Tests
     * Created by:      Angel Politis
     * Creation Date:   Mar 22 2026
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer.Tests namespace.
    namespace Wingman\Explorer\Tests;

    # Import the following framework classes unconditionally (no autoloading is triggered).
    use Wingman\Argus\Attributes\Define;
    use Wingman\Argus\Attributes\Group;
    use Wingman\Argus\Test;
    use Wingman\Explorer\Enums\StreamMode;
    use Wingman\Explorer\Exceptions\StreamNotReadableException;
    use Wingman\Explorer\Exceptions\StreamNotWritableException;
    use Wingman\Explorer\Exceptions\UnseekableStreamException;
    use Wingman\Explorer\IO\Stream;

    # Import Psr7StreamAdapter as a compile-time alias only — autoloading is deferred
    # to runtime and only triggered when the class is actually instantiated inside
    # the conditionally-defined test class below.
    use Wingman\Explorer\Bridge\Psr\Psr7StreamAdapter;

    /**
     * This file conditionally defines the Psr7StreamAdapterTest class only when
     * the <code>psr/http-message</code> package is available. If the package is
     * absent, the class is not registered and Argus will discover no tests from
     * this file — no exceptions will be thrown.
     *
     * To run these tests, install the optional PSR-7 dependency:
     * <pre>composer require psr/http-message</pre>
     */
    if (interface_exists('Psr\Http\Message\StreamInterface')) {
        /**
         * Tests for the Psr7StreamAdapter bridge class.
         *
         * Each test uses a real {@see Stream} backed by <code>php://memory</code>,
         * so no real files are created or touched. The adapter must implement every
         * method of the PSR-7 StreamInterface by delegating to the underlying Stream.
         */
        class Psr7StreamAdapterTest extends Test {
            /**
             * Creates a fresh read-write in-memory stream for each test.
             * @return Stream A new in-memory stream.
             */
            private function makeStream (string $initialContent = "") : Stream {
                $stream = Stream::from("php://memory", StreamMode::WRITE_READ_BINARY);

                if ($initialContent !== "") {
                    $stream->open();
                    $stream->write($initialContent);
                    $stream->rewindReader();
                }

                return $stream;
            }

            // ─── ToString ────────────────────────────────────────────────────

            #[Group("Psr7StreamAdapter")]
            #[Define(name: "__toString — Returns Full Stream Content", description: "__toString() rewinds the stream and returns all content as a string.")]
            public function testToStringReturnsFullStreamContent () : void {
                $stream = $this->makeStream("hello world");
                $adapter = new Psr7StreamAdapter($stream);

                $this->assertEquals("hello world", (string) $adapter);
            }

            #[Group("Psr7StreamAdapter")]
            #[Define(name: "__toString — Returns Empty String After Detach", description: "__toString() returns an empty string once the adapter has been detached.")]
            public function testToStringReturnsEmptyStringAfterDetach () : void {
                $stream = $this->makeStream("data");
                $adapter = new Psr7StreamAdapter($stream);
                $adapter->detach();

                $this->assertEquals("", (string) $adapter);
            }

            // ─── Close ───────────────────────────────────────────────────────

            #[Group("Psr7StreamAdapter")]
            #[Define(name: "Close — GetSize Returns Null After Closing", description: "After close(), getSize() returns null because the underlying stream is released.")]
            public function testCloseMakesGetSizeReturnNull () : void {
                $stream = $this->makeStream("content");
                $adapter = new Psr7StreamAdapter($stream);

                $adapter->close();

                $this->assertNull($adapter->getSize());
            }

            #[Group("Psr7StreamAdapter")]
            #[Define(name: "Close — IsReadable Returns False After Closing", description: "isReadable() returns false once the adapter has been closed.")]
            public function testCloseIsNotReadableAfterClose () : void {
                $stream = $this->makeStream("content");
                $adapter = new Psr7StreamAdapter($stream);

                $adapter->close();

                $this->assertFalse($adapter->isReadable());
            }

            // ─── Detach ───────────────────────────────────────────────────────

            #[Group("Psr7StreamAdapter")]
            #[Define(name: "Detach — Returns Null (Explorer Streams Hide Raw Resource)", description: "detach() always returns null because Explorer streams do not expose their underlying PHP resource.")]
            public function testDetachReturnsNull () : void {
                $stream = $this->makeStream();
                $adapter = new Psr7StreamAdapter($stream);

                $this->assertNull($adapter->detach());
            }

            #[Group("Psr7StreamAdapter")]
            #[Define(name: "Detach — IsReadable Returns False After Detach", description: "isReadable() returns false after the adapter has been detached.")]
            public function testDetachMakesAdapterUnreadable () : void {
                $stream = $this->makeStream("data");
                $adapter = new Psr7StreamAdapter($stream);
                $adapter->detach();

                $this->assertFalse($adapter->isReadable());
            }

            // ─── GetSize ─────────────────────────────────────────────────────

            #[Group("Psr7StreamAdapter")]
            #[Define(name: "GetSize — Returns Byte Count of Contents", description: "getSize() returns the number of bytes written into the underlying stream.")]
            public function testGetSizeReturnsByteCount () : void {
                $stream = $this->makeStream("twelve bytes");
                $adapter = new Psr7StreamAdapter($stream);

                $this->assertEquals(12, $adapter->getSize());
            }

            // ─── Tell / EOF ───────────────────────────────────────────────────

            #[Group("Psr7StreamAdapter")]
            #[Define(name: "Tell — Returns Zero at Stream Start", description: "tell() returns 0 immediately after creation before any read has occurred.")]
            public function testTellReturnsZeroAtStreamStart () : void {
                $stream = $this->makeStream("data");
                $adapter = new Psr7StreamAdapter($stream);

                $this->assertEquals(0, $adapter->tell());
            }

            #[Group("Psr7StreamAdapter")]
            #[Define(name: "EOF — Returns False At Stream Start", description: "eof() returns false when the stream pointer has not yet reached the end.")]
            public function testEofReturnsFalseAtStreamStart () : void {
                $stream = $this->makeStream("data");
                $adapter = new Psr7StreamAdapter($stream);

                $this->assertFalse($adapter->eof());
            }

            // ─── Seek / Rewind ────────────────────────────────────────────────

            #[Group("Psr7StreamAdapter")]
            #[Define(name: "IsSeekable — Returns True for Memory Stream", description: "isSeekable() returns true for a php://memory backed stream.")]
            public function testIsSeekableReturnsTrueForMemoryStream () : void {
                $stream = $this->makeStream("data");
                $adapter = new Psr7StreamAdapter($stream);

                $this->assertTrue($adapter->isSeekable());
            }

            #[Group("Psr7StreamAdapter")]
            #[Define(name: "Seek — SEEK_SET Positions Read Pointer at Absolute Offset", description: "seek() with SEEK_SET moves the read pointer to the given absolute byte offset.")]
            public function testSeekSetPositionsReadPointerAtAbsoluteOffset () : void {
                $stream = $this->makeStream("abcdefgh");
                $adapter = new Psr7StreamAdapter($stream);

                $adapter->seek(4, SEEK_SET);

                $this->assertEquals("efgh", $adapter->getContents());
            }

            #[Group("Psr7StreamAdapter")]
            #[Define(name: "Seek — SEEK_CUR Positions Read Pointer Relative to Current", description: "seek() with SEEK_CUR advances the pointer by the given number of bytes relative to the current position.")]
            public function testSeekCurPositionsReadPointerRelativeToCurrentPosition () : void {
                $stream = $this->makeStream("abcdefgh");
                $adapter = new Psr7StreamAdapter($stream);

                $adapter->read(2);
                $adapter->seek(2, SEEK_CUR);

                $this->assertEquals("efgh", $adapter->getContents());
            }

            #[Group("Psr7StreamAdapter")]
            #[Define(name: "Seek — SEEK_END Positions Read Pointer Relative to End", description: "seek() with SEEK_END and a negative offset positions the pointer counting backwards from the end of the stream.")]
            public function testSeekEndPositionsReadPointerRelativeToEnd () : void {
                $stream = $this->makeStream("abcdefgh");
                $adapter = new Psr7StreamAdapter($stream);

                $adapter->seek(-4, SEEK_END);

                $this->assertEquals("efgh", $adapter->getContents());
            }

            #[Group("Psr7StreamAdapter")]
            #[Define(name: "Rewind — Moves Read Pointer Back to Start", description: "rewind() is equivalent to seek(0) and allows re-reading from the beginning of the stream.")]
            public function testRewindMovesReadPointerBackToStart () : void {
                $stream = $this->makeStream("hello");
                $adapter = new Psr7StreamAdapter($stream);

                $adapter->read(5);
                $adapter->rewind();

                $this->assertEquals("hello", $adapter->getContents());
            }

            // ─── Write ───────────────────────────────────────────────────────

            #[Group("Psr7StreamAdapter")]
            #[Define(name: "IsWritable — Returns True for Read-Write Stream", description: "isWritable() returns true for a php://memory stream opened in read-write mode.")]
            public function testIsWritableReturnsTrueForReadWriteStream () : void {
                $stream = $this->makeStream();
                $adapter = new Psr7StreamAdapter($stream);

                $this->assertTrue($adapter->isWritable());
            }

            #[Group("Psr7StreamAdapter")]
            #[Define(name: "Write — Appends Data and Returns Byte Count", description: "write() writes the string to the stream and returns the number of bytes written.")]
            public function testWriteAppendsDataAndReturnsByteCount () : void {
                $stream = $this->makeStream();
                $adapter = new Psr7StreamAdapter($stream);

                $written = $adapter->write("hello world");

                $this->assertEquals(11, $written);
            }

            #[Group("Psr7StreamAdapter")]
            #[Define(name: "Write — Throws When Stream Is Not Writable", description: "write() throws StreamNotWritableException when the underlying stream does not support writes.")]
            public function testWriteThrowsWhenStreamIsNotWritable () : void {
                $stream = Stream::from("php://memory", StreamMode::READ_BINARY);
                $adapter = new Psr7StreamAdapter($stream);

                $this->assertThrows(StreamNotWritableException::class, function () use ($adapter) {
                    $adapter->write("data");
                });
            }

            // ─── Read ────────────────────────────────────────────────────────

            #[Group("Psr7StreamAdapter")]
            #[Define(name: "IsReadable — Returns True for Read-Write Stream", description: "isReadable() returns true for a php://memory stream opened in read-write mode.")]
            public function testIsReadableReturnsTrueForReadWriteStream () : void {
                $stream = $this->makeStream("data");
                $adapter = new Psr7StreamAdapter($stream);

                $this->assertTrue($adapter->isReadable());
            }

            #[Group("Psr7StreamAdapter")]
            #[Define(name: "Read — Returns Specified Number of Bytes", description: "read() reads the specified number of bytes from the current pointer position.")]
            public function testReadReturnsSpecifiedNumberOfBytes () : void {
                $stream = $this->makeStream("hello world");
                $adapter = new Psr7StreamAdapter($stream);

                $this->assertEquals("hello", $adapter->read(5));
                $this->assertEquals(" world", $adapter->read(6));
            }

            #[Group("Psr7StreamAdapter")]
            #[Define(name: "Read — Throws When Stream Is Not Readable", description: "read() throws StreamNotReadableException when the underlying stream does not support reads.")]
            public function testReadThrowsWhenStreamIsNotReadable () : void {
                $stream = Stream::from("php://memory", StreamMode::WRITE_BINARY);
                $adapter = new Psr7StreamAdapter($stream);

                $this->assertThrows(StreamNotReadableException::class, function () use ($adapter) {
                    $adapter->read(4);
                });
            }

            // ─── GetContents ─────────────────────────────────────────────────

            #[Group("Psr7StreamAdapter")]
            #[Define(name: "GetContents — Returns Remaining Data From Current Position", description: "getContents() returns all bytes from the current pointer position to the end of the stream.")]
            public function testGetContentsReturnsRemainingDataFromCurrentPosition () : void {
                $stream = $this->makeStream("hello world");
                $adapter = new Psr7StreamAdapter($stream);

                $adapter->read(6);

                $this->assertEquals("world", $adapter->getContents());
            }

            #[Group("Psr7StreamAdapter")]
            #[Define(name: "GetContents — Returns Full Content When Not Advanced", description: "getContents() returns the entire stream content when the pointer is at position zero.")]
            public function testGetContentsReturnsFullContentWhenNotAdvanced () : void {
                $stream = $this->makeStream("full content");
                $adapter = new Psr7StreamAdapter($stream);

                $this->assertEquals("full content", $adapter->getContents());
            }

            // ─── GetMetadata ─────────────────────────────────────────────────

            #[Group("Psr7StreamAdapter")]
            #[Define(name: "GetMetadata — Returns Full Metadata Array When No Key Given", description: "getMetadata() without arguments returns the raw stream_get_meta_data() array.")]
            public function testGetMetadataReturnsFullArrayWhenNoKeyGiven () : void {
                $stream = $this->makeStream("data");
                $adapter = new Psr7StreamAdapter($stream);

                $meta = $adapter->getMetadata();

                $this->assertType("array", $meta);
                $this->assertArrayHasKey("seekable", $meta);
            }

            #[Group("Psr7StreamAdapter")]
            #[Define(name: "GetMetadata — Returns Specific Value for Known Key", description: "getMetadata('seekable') returns the boolean seekable flag of the underlying stream.")]
            public function testGetMetadataReturnsSpecificValueForKnownKey () : void {
                $stream = $this->makeStream("data");
                $adapter = new Psr7StreamAdapter($stream);

                $this->assertType("boolean", $adapter->getMetadata("seekable"));
            }

            #[Group("Psr7StreamAdapter")]
            #[Define(name: "GetMetadata — Returns Null for Unknown Key", description: "getMetadata() returns null when the requested key does not exist in the stream metadata.")]
            public function testGetMetadataReturnsNullForUnknownKey () : void {
                $stream = $this->makeStream("data");
                $adapter = new Psr7StreamAdapter($stream);

                $this->assertNull($adapter->getMetadata("nonexistent_key_xyz"));
            }

            #[Group("Psr7StreamAdapter")]
            #[Define(name: "GetMetadata — Returns Empty Array After Detach", description: "getMetadata() returns an empty array once the adapter has been detached from its stream.")]
            public function testGetMetadataReturnsEmptyArrayAfterDetach () : void {
                $stream = $this->makeStream("data");
                $adapter = new Psr7StreamAdapter($stream);
                $adapter->detach();

                $this->assertEquals([], $adapter->getMetadata());
            }
        }
    }
?>