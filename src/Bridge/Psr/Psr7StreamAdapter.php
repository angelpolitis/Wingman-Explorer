<?php
    /**
     * Project Name:    Wingman Explorer - PSR-7 Stream Adapter
     * Created by:      Angel Politis
     * Creation Date:   Mar 20 2026
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer.Bridge.Psr namespace.
    namespace Wingman\Explorer\Bridge\Psr;

    # Import the following classes to the current scope.
    use Throwable;
    use Wingman\Explorer\Exceptions\MissingDependencyException;
    use Wingman\Explorer\Exceptions\StreamException;
    use Wingman\Explorer\Exceptions\StreamNotReadableException;
    use Wingman\Explorer\Exceptions\StreamNotWritableException;
    use Wingman\Explorer\Exceptions\UnseekableStreamException;
    use Wingman\Explorer\IO\Stream;

    if (!interface_exists('Psr\Http\Message\StreamInterface')) {
        throw new MissingDependencyException("PSR-7 support requires the psr/http-message package. Install it with: composer require psr/http-message");
    }

    /**
     * Adapts a {@see Stream} instance to the PSR-7 <code>Psr\Http\Message\StreamInterface</code>
     * contract, allowing Explorer streams to be used wherever a PSR-7 stream is expected.
     *
     * This class implements the PSR-7 StreamInterface methods by delegating to the
     * underlying {@see Stream}. The PSR HTTP Message package is an optional dependency;
     * if the interface is not loaded a {@see MissingDependencyException} is thrown at construction.
     *
     * @package Wingman\Explorer\Bridge\Psr
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     * 
     * @disregard P1009 \Psr\Http\Message\StreamInterface is an optional dependency and may not be present.
     * @psalm-suppress UndefinedClass
     * @phpstan-ignore-next-line
     */
    class Psr7StreamAdapter implements \Psr\Http\Message\StreamInterface {
        /**
         * Whether this adapter has been detached from its underlying stream.
         * @var bool
         */
        private bool $detached = false;

        /**
         * The underlying Explorer stream.
         * @var Stream|null
         */
        private ?Stream $stream;

        /**
         * Creates a new PSR-7 stream adapter wrapping the given stream.
         * @param Stream $stream The stream to adapt.
         * @throws MissingDependencyException If the PSR-7 StreamInterface is not available.
         */
        public function __construct (Stream $stream) {
            if (!interface_exists('Psr\Http\Message\StreamInterface')) {
                throw new MissingDependencyException("PSR-7 support requires the psr/http-message package. Install it with: composer require psr/http-message");
            }

            $this->stream = $stream;
        }

        /**
         * Reads all data from the stream, rewinding first if seekable.
         * @return string All data in the stream.
         */
        public function __toString () : string {
            if ($this->detached || $this->stream === null) {
                return "";
            }

            try {
                if ($this->stream->isSeekable()) {
                    $this->stream->rewindReader();
                }

                return $this->stream->readAll();
            }
            catch (Throwable) {
                return "";
            }
        }

        /**
         * Closes the stream and releases any underlying resources.
         */
        public function close () : void {
            if ($this->stream !== null) {
                $this->stream->close();
                $this->stream = null;
            }

            $this->detached = true;
        }

        /**
         * Separates the underlying resource from the stream.
         *
         * After detachment this adapter becomes unusable. Because the Explorer
         * {@see Stream} does not expose its raw PHP resource, this method always
         * returns <code>null</code>.
         *
         * @return resource|null Always null.
         */
        public function detach () {
            $this->detached = true;
            $this->stream = null;

            return null;
        }

        /**
         * Gets the size of the stream in bytes, or null if unknown.
         * @return int|null The stream size in bytes, or null if unknown.
         */
        public function getSize () : ?int {
            if ($this->detached || $this->stream === null) {
                return null;
            }

            return $this->stream->getSize();
        }

        /**
         * Returns the current position of the read pointer.
         * @throws StreamException If the stream is detached or the position cannot be determined.
         * @return int The current pointer position.
         */
        public function tell () : int {
            $this->requireAttached();

            try {
                return $this->stream->tell() ?? $this->stream->getReadingOffset();
            }
            catch (UnseekableStreamException) {
                return $this->stream->getReadingOffset();
            }
        }

        /**
         * Returns whether the stream pointer is at the end of the stream.
         * @return bool Whether the end of stream has been reached.
         */
        public function eof () : bool {
            if ($this->detached || $this->stream === null) {
                return true;
            }

            return $this->stream->hasReachedEnd();
        }

        /**
         * Returns whether the stream is seekable.
         * @return bool Whether the stream is seekable.
         */
        public function isSeekable () : bool {
            if ($this->detached || $this->stream === null) {
                return false;
            }

            return $this->stream->isSeekable();
        }

        /**
         * Seeks to a position in the stream.
         * @param int $offset Stream offset.
         * @param int $whence How the cursor position is calculated; one of SEEK_SET, SEEK_CUR, SEEK_END.
         * @throws UnseekableStreamException If the stream is not seekable.
         * @throws StreamException If the stream is detached or the whence value is invalid.
         */
        public function seek (int $offset, int $whence = SEEK_SET) : void {
            $this->requireAttached();

            if (!$this->stream->isSeekable()) {
                throw new UnseekableStreamException("Stream is not seekable.");
            }

            try {
                match ($whence) {
                    SEEK_SET => $this->stream->seek($offset, false),
                    SEEK_CUR => $this->stream->seek($offset, true),
                    SEEK_END => $this->stream->seek($this->stream->getSize() + $offset, false),
                    default => throw new StreamException("Invalid whence value: $whence.")
                };
            }
            catch (UnseekableStreamException $e) {
                throw new UnseekableStreamException("Stream is not seekable.", 0, $e);
            }
        }

        /**
         * Seeks to the beginning of the stream.
         */
        public function rewind () : void {
            $this->seek(0);
        }

        /**
         * Returns whether the stream is writable.
         * @return bool Whether the stream is writable.
         */
        public function isWritable () : bool {
            if ($this->detached || $this->stream === null) {
                return false;
            }

            return $this->stream->isWritable();
        }

        /**
         * Writes data to the stream.
         * @param string $string The string to write.
         * @throws StreamNotWritableException If the stream is not writable.
         * @return int The number of bytes written.
         */
        public function write (string $string) : int {
            $this->requireAttached();

            if (!$this->stream->isWritable()) {
                throw new StreamNotWritableException("Stream is not writable.");
            }

            $this->stream->write($string);

            return strlen($string);
        }

        /**
         * Returns whether the stream is readable.
         * @return bool Whether the stream is readable.
         */
        public function isReadable () : bool {
            if ($this->detached || $this->stream === null) {
                return false;
            }

            return $this->stream->isReadable();
        }

        /**
         * Reads up to the specified number of bytes from the stream.
         * @param int $length The maximum number of bytes to read.
         * @throws StreamNotReadableException If the stream is not readable.
         * @return string The bytes read from the stream.
         */
        public function read (int $length) : string {
            $this->requireAttached();

            if (!$this->stream->isReadable()) {
                throw new StreamNotReadableException("Stream is not readable.");
            }

            return $this->stream->read($length) ?? "";
        }

        /**
         * Returns the remaining contents of the stream as a string.
         * @throws StreamNotReadableException If the stream is not readable.
         * @return string The remaining stream contents.
         */
        public function getContents () : string {
            $this->requireAttached();

            if (!$this->stream->isReadable()) {
                throw new StreamNotReadableException("Stream is not readable.");
            }

            return $this->stream->readAll();
        }

        /**
         * Gets the stream metadata or a specific metadata key.
         * @param string|null $key The specific metadata key, or null for all metadata.
         * @return mixed The metadata array, the value of the requested key, or null.
         */
        public function getMetadata (?string $key = null) : mixed {
            if ($this->detached || $this->stream === null) {
                return $key === null ? [] : null;
            }

            $metadata = $this->stream->getMetadata();

            if ($key === null) {
                return $metadata;
            }

            return $metadata[$key] ?? null;
        }

        /**
         * Asserts that the adapter has not been detached.
         */
        private function requireAttached () : void {
            if ($this->detached || $this->stream === null) {
                throw new StreamException("Stream has been detached and is no longer usable.");
            }
        }
    }
?>