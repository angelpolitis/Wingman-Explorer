<?php
    /**
     * Project Name:    Wingman Explorer - Stream
     * Created by:      Angel Politis
     * Creation Date:   Oct 30 2022
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2022-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */
    
    # Use the Explorer.IO namespace.
    namespace Wingman\Explorer\IO;

    # Import the following classes to the current scope.
    use Wingman\Explorer\Enums\StreamMode;
    use Wingman\Explorer\Exceptions\NotAStreamException;
    use Wingman\Explorer\Exceptions\StreamNotWritableException;
    use Wingman\Explorer\Exceptions\StreamException;
    use Wingman\Explorer\Exceptions\UnseekableStreamException;
    use Wingman\Explorer\Traits\Configurable as Configurable;

    /**
     * A class used to facilitate streaming of text.
     * @package Wingman\Explorer\IO
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class Stream {
        use Configurable;

        /**
         * The common prefix of configurations.
         * @var string
         */
        protected static string $configPrefix = "io.stream.";

        /**
         * The Environment variable for the default size of a chunk in bytes.
         * @var string
         */
        public const CHUNK_SIZE_VAR = "chunkSize";

        /**
         * The Environment variable for the maximum number of entries that can be stored in a stream's cache.
         * @var string
         */
        public const CACHE_SIZE_VAR = "cacheSize";

        /**
         * The Environment variable for the direction where temporary files are saved.
         * @var string
         */
        public const TEMP_DIR_VAR = "tempDir";

        /**
         * The Environment variable for whether text mode is enabled.
         * @var string
         */
        public const TEXT_MODE_ENABLED_VAR = "textModeEnabled";

        /**
         * The cache of a stream where read data are stored.
         * @var StreamInput[]
         */
        protected array $cache = [];

        /**
         * The number of elements a stream keeps to memory before flushing.
         * @var int
         */
        protected int $cacheSize;

        /**
         * The size of a chunk in bytes when reading from or writing to a stream.
         * @var int
         */
        protected int $chunkSize;

        /**
         * The last action taken last (can be 'read' or 'write').
         * @var string
         */
        protected string $lastAction;

        /**
         * The mode a stream is opened in.
         * @var StreamMode
         */
        protected StreamMode $mode = StreamMode::READ_WRITE_BINARY;

        /**
         * The path to a file.
         * @var string
         */
        protected string $path;

        /**
         * The pointer of a stream.
         * @var resource|null
         */
        protected $pointer = null;

        /**
         * Determines whether a stream has reached the end of a file.
         * @var bool
         */
        protected bool $reachedEndOfFile = false;

        /**
         * The offset the reading pointer of a stream is currently placed at.
         * @var int
         */
        protected int $readingOffset = 0;

        /**
         * Determines whether a stream is seekable (i.e. the offset of the pointer can be altered).
         * @var bool
         */
        protected bool $seekable = false;

        /**
         * The size of the file of a stream in bytes.
         * @var int
         */
        protected int $size = 0;

        /**
         * The offset the writing pointer of a stream is currently placed at.
         * @var int
         */
        protected int $writingOffset = 0;

        #########################################
        # ---------- Magic Functions ---------- #
        #########################################

        /**
         * Creates a new stream.
         * @param string|null $path The path to a file.
         * @param StreamMode|string|null $mode A mode.
         * @param array|null $config The configurations.
         */
        public function __construct (?string $path = null, string|StreamMode|null $mode = null, ?array $config = []) {
            $this->useConfig($config);

            if (!isset($path)) $path = tempnam($this->getTempDir(), "php_upl");

            $this->path = $path;

            if (isset($mode)) {
                $this->mode = $mode instanceof StreamMode ? $mode : StreamMode::from($mode);
            }

            $this->cacheSize = $this->getConfig(static::CACHE_SIZE_VAR);
            $this->chunkSize = $this->getConfig(static::CHUNK_SIZE_VAR);
        }

        /**
         * Destroys a stream.
         */
        public function __destruct () {
            if ($this->pointer === null) return;

            $this->flush();
            
            $this->close();

            if (str_starts_with($this->path, $this->getTempDir())) {
                @unlink($this->path);
            }
        }

        /**
         * Turns a stream into a string, essentially fetching its full content.
         * @return string The content of the string.
         */
        public function __toString () : string {
            # Since __toString cannot throw exceptions, return an empty string if there's an error.
            try {
                return $this->readAll();
            }
            catch (\Throwable $e) {
                return '';
            }
        }

        ###########################################
        # ---------- Private Functions ---------- #
        ###########################################

        /**
         * Get the directory where temporary files are stored.
         * @return string The temporary files directory.
         */
        private function getTempDir () : string {
            return $this->getConfig(static::TEMP_DIR_VAR);
        }

        /**
         * Initialises an action by preparing the pointer of a stream to point at the right offset.
         * @param string $action The action to initialise.
         */
        private function initialiseAction (string $action) : void {
            # Terminate the function if the current and last actions match.
            if (($this->lastAction ?? null) == $action) return;
            
            # Save the action as the last action.
            $this->lastAction = $action;

            # Open the resource if it's not been opened.
            if (!$this->isOpen()) $this->open();

            # Terminate the function if the stream isn't seekable.
            if (!$this->seekable) return;

            # Determine the offset to use based on the current action.
            $offset = ($action == "read") ? $this->readingOffset : $this->writingOffset;

            # Set the pointer at the offset.
            fseek($this->pointer, $offset, SEEK_SET);
        }

        /**
         * Normalises the mode so it's always binary-safe unless specifically requested otherwise.
         * @param string|StreamMode $mode A mode.
         * @return StreamMode The binary-safe mode.
         */
        private static function normaliseMode (string|StreamMode $mode) : StreamMode {
            $mode = $mode instanceof StreamMode ? $mode->value : $mode;

            # If binary or text modes haven't been explicitly requested, default to binany.
            if (!str_contains($mode, 'b') && !str_contains($mode, 't')) {
                $mode = "{$mode}b";
            }

            return StreamMode::fromNormalised($mode);
        }


        /**
         * Sets an offset to a specified index.
         * @param int $index An index (if negative, it's assumed index from the end).
         * @param bool $relative Whether the index is relative to the current index.
         * @return static The stream.
         */
        private function setOffset (int &$offset, int $index, bool $relative = false) : static {
            # Don't track meaningless offsets internally.
            if (!$this->seekable) {
                $offset = 0;

                return $this;
            }

            # Add the index to the offset if the placement is relative.
            if ($relative) $offset += $index;

            # Use the size of the stream to calculate the offset from the end if the index is negative.
            elseif ($index < 0) $offset = $this->size + $index;

            # Set the reading offset to the given index.
            else $offset = $index;

            # Bind the offset between 0 and the size of the file.
            $offset = min($this->size, max(0, $offset));

            # Return the context.
            return $this;
        }

        #############################################
        # ---------- Protected Functions ---------- #
        #############################################

        /**
         * Stores an input to the cache of a stream.
         * @param StreamInput $input An input.
         * @return static The stream.
         */
        protected function storeInput (StreamInput $input) : static {
            $this->cache[] = $input;

            # If the cache has overflown, remove the first element from the cache.
            if (sizeof($this->cache) > $this->cacheSize) {
                array_shift($this->cache);
            }

            # Return the context.
            return $this;
        }

        ##########################################
        # ---------- Public Functions ---------- #
        ##########################################

        /**
         * Writes an input at the end of the file of a stream.
         * @param string|static $input A text or stream.
         * @return static The stream.
         */
        public function append (string|self $input) : static {
            $this->initialiseAction("write");

            $this->setWriterAt($this->size);

            return $this->write($input);
        }

        /**
         * Closes a stream.
         * @return bool Whether the stream was successfully closed.
         */
        public function close () : bool {
            $result = !is_resource($this->pointer) || fclose($this->pointer);
            $this->pointer = null;
            return $result;
        }

        /**
         * Copies the content of a stream to another.
         * @param static $target A target stream.
         * @param int|null $maxBytes The maximum number of bytes to copy.
         * @return int The number of bytes copied.
         */
        public function copyTo (self $target, ?int $maxBytes = null) : int {
            $bytesCopied = 0;
            $chunkSize = $this->chunkSize;

            while (!$this->hasReachedEnd() && ($maxBytes === null || $bytesCopied < $maxBytes)) {
                $toRead = $chunkSize;

                if ($maxBytes !== null) {
                    $remaining = $maxBytes - $bytesCopied;
                    $toRead = min($toRead, $remaining);
                }

                $chunk = $this->read($toRead);

                if ($chunk === null) break;

                $target->write($chunk);
                
                $bytesCopied += strlen($chunk);
            }

            return $bytesCopied;
        }

        /**
         * Creates a stream.
         * @param array|null $config The configurations.
         * @return static A new stream.
         */
        public static function create (?array $config = []) : static {
            return new static(null, null, $config);
        }

        /**
         * Deletes a range of bytes from the file of a stream.
         * @param int $start An index to start deleting from (if negative, it's assumed index from the end).
         * @param int $length The number of bytes to delete.
         * @return static The stream.
         * @throws UnseekableStreamException If the stream isn't seekable.
         * @throws StreamException If the operation fails.
         */
        public function deleteRange (int $start, int $length) : static {
            if (!$this->isOpen()) $this->open();

            if (!$this->seekable) {
                throw new UnseekableStreamException("Cannot delete range on non-seekable stream.");
            }

            # There's nothing to delete if the length isn't positive and the start index isn't before
            # the end of the file.
            if ($length <= 0 || $start > $this->size) return $this;

            # If the start index is so negative that reaches or exceeds the beginning from the end,
            # we assume that it should start from the beginning.
            if ($start <= -$this->size) $start = 0;

            # If the start is negative, then its absolute value is the length and its actual value
            # is that many places counting from the end of the file.
            if ($start < 0) {
                $length = -$start;
                $start = $this->size + $start;
            }

            $end = $start + $length;

            if (!$this->lock(true)) {
                throw new StreamException("Failed to acquire exclusive lock for deleteRange.");
            }

            try {
                while (true) {
                    $this->setReaderAt($end);
    
                    $content = $this->read($this->chunkSize, function (StreamInput $input) use (&$start, &$end) {
                        $this->setWriterAt($start);
    
                        $readLength = $input->getLength();
    
                        $this->write($input->getContent(), $input->getLength());
    
                        $start += $readLength;
                        $end += $readLength;
                    });
    
                    if ($content === "" || $content === null) break;
                }
    
                $newSize = $end - $length;
    
                $this->truncate($newSize);
    
                $this->size = $newSize;
                $this->writingOffset = $newSize;
                $this->readingOffset = $newSize;
            
                fseek($this->pointer, 0, SEEK_END);
            }
            finally {
                $this->unlock();
            }
        
            return $this;
        }

        /**
         * Flushes the output to the file of a stream.
         * @return bool Whether the operation succeeded.
         */
        public function flush () : bool {
            return fflush($this->pointer);
        }

        /**
         * Creates a stream for an existing pointer.
         * @param resource|null $pointer A pointer to a file.
         * @return static A new stream.
         * @throws NotAStreamException If the pointer isn't a stream resource.
         */
        public static function for ($pointer) : static {
            # Throw an exception if the given argument isn't a stream.
            if (!is_resource($pointer) || get_resource_type($pointer) !== "stream") {
                throw new NotAStreamException();
            }

            # Get the meta data of the pointer.
            $data = stream_get_meta_data($pointer);

            # Create a new stream from the URI of the pointer.
            $stream = new static($data["uri"]);

            # Store the pointer into the stream.
            $stream->pointer = $pointer;

            # Store the mode into the stream.
            $stream->mode = static::normaliseMode($data["mode"]);

            # Return the stream.
            return $stream;
        }

        /**
         * Creates a stream from a path.
         * @param string|null $path The path to a file.
         * @param StreamMode|string|null $mode A mode.
         * @param array|null $config The configurations.
         * @return static A new stream.
         */
        public static function from (string $path, string|StreamMode|null $mode = null, ?array $config = []) : static {
            return new static($path, $mode, $config);
        }

        /**
         * Gets the cache size of a stream.
         * @return int The number of entries the cache can fit.
         */
        public function getCacheSize () : int {
            return $this->cacheSize;
        }

        /**
         * Gets the chunk size of a stream.
         * @return int The chunk size of a stream.
         */
        public function getChunkSize () : int {
            return $this->chunkSize;
        }

        /**
         * Gets the default configuration of the class.
         * @return array The default configuration.
         */
        public static function getDefaultConfig () : array {
            return [
                static::TEMP_DIR_VAR => sys_get_temp_dir(),
                static::TEXT_MODE_ENABLED_VAR => false,
                static::CHUNK_SIZE_VAR => 2 ** 12,
                static::CACHE_SIZE_VAR => 4
            ];
        }

        /**
         * Gets the metadata for a stream.
         * @param string|null $key An optional metadata key; when provided, returns only the value for that key.
         * @return mixed The value of the requested metadata key, or the full metadata array when no key is given.
         */
        public function getMetadata (?string $key = null) : mixed {
            if (isset($key)) {
                return null === $this->pointer ? null : (stream_get_meta_data($this->pointer)[$key] ?? null);
            }

            return null === $this->pointer ? [] : stream_get_meta_data($this->pointer);
        }

        /**
         * Returns the path to the file of a stream.
         * @return string The path.
         */
        public function getPath () : string {
            return $this->path;
        }

        /**
         * Returns the reading offset of a stream.
         * @return int The reading offset.
         */
        public function getReadingOffset () : int {
            return $this->readingOffset;
        }

        /**
         * Gets the size of the file of a stream.
         * @return int The size in bytes.
         */
        public function getSize () : int {
            return $this->size;
        }

        /**
         * Returns the writing offset of a stream.
         * @return int The writing offset.
         */
        public function getWritingOffset () : int {
            return $this->writingOffset;
        }

        /**
         * Returns whether a stream's pointer is currently located at the end of the file.
         * @return bool Whether the stream's pointer is located at the end of the file.
         */
        public function hasReachedEnd () : bool {
            if ($this->pointer === null) return false;

            return feof($this->pointer);
        }

        /**
         * Returns whether a stream is open.
         * @return bool Whether a stream is open.
         */
        public function isOpen () : bool {
            return is_resource($this->pointer) && get_resource_type($this->pointer) == "stream";
        }

        /**
         * Returns whether a stream is readable.
         * @return bool Whether the stream is readable.
         */
        public function isReadable () : bool {
            if (!$this->isOpen()) return false;

            return $this->mode->isReadable();
        }

        /**
         * Returns whether a stream is seekable.
         * @return bool Whether the stream is seekable.
         */
        public function isSeekable () : bool {
            if (!$this->isOpen()) return false;

            return $this->seekable;
        }

        /**
         * Returns whether a stream is writable.
         * @return bool Whether the stream is writable.
         */
        public function isWritable () : bool {
            if (!$this->isOpen()) return false;

            return $this->mode->isWritable();
        }

        /**
         * Locks the file of a stream.
         * @param bool $exclusive Whether the lock should be exclusive.
         * @param bool $block Whether the lock should be blocking.
         * @return bool Whether the locking was successful.
         * @throws NotAStreamException If the stream hasn't been opened.
         */
        public function lock (bool $exclusive = false, bool $block = false) : bool {
            if ($this->pointer === null) throw new NotAStreamException();

            return flock($this->pointer, ($exclusive ? LOCK_EX : LOCK_SH) | ($block ? 0 : LOCK_NB));
        }

        /**
         * Opens a stream.
         * @param StreamMode $mode A mode.
         * @return static The stream.
         * @throws StreamException If the operation fails.
         */
        public function open (string|StreamMode|null $mode = null) : static {
            $mode = $this->normaliseMode($mode ?? $this->mode);
            $mode = ($mode === StreamMode::READ_WRITE_BINARY && !file_exists($this->path))
                ? StreamMode::WRITE_READ_BINARY
                : $mode;

            # Store the mode.
            $this->mode = $mode;

            # Open a stream for the file.
            $this->pointer = fopen($this->path, $mode->value);

            # Throw an exception if the stream couldn't be opened.
            if ($this->pointer === false) throw new StreamException("Cannot open stream.");

            # Check whether the path is in fact the input stream of PHP.
            if ($this->path === "php://input") {
                # Cache the length of the content.
                $this->size = (int) ($_SERVER["CONTENT_LENGTH"] ?? 0);
            }
            else {
                # Cache the size of the file of the stream.
                $this->size = fstat($this->pointer)["size"];
            }

            # Cache whether the stream is seekable.
            $this->seekable = stream_get_meta_data($this->pointer)["seekable"];

            return $this;
        }

        /**
         * Reads a number of bytes from a stream.
         * @param int $bytes The maximum number of bytes to read.
         * @param callable $function A function to execute after reading.
         * @return string|null The read content, or `null` if the end of file has been reached.
         */
        public function read (int $bytes, ?callable $function = null) : ?string {
            # Initialise the action.
            $this->initialiseAction("read");
            
            # Read text from the stream up to the specified number of bytes.
            $text = $bytes > 0 ? fread($this->pointer, $bytes) : "";

            if ($text === "" && feof($this->pointer)) {
                $this->reachedEndOfFile = true;

                return null;
            }

            if ($text === false) {
                $this->reachedEndOfFile = true;
                return null;
            }

            $length = strlen($text);

            $input = new StreamInput([
                "content" => $text,
                "length" => $length,
                "offset" => $this->readingOffset
            ]);

            $this->readingOffset += $length;

            $this->storeInput($input);

            if (!is_null($function)) $function($input, $text);

            return $text;
        }

        /**
         * Reads the entire content of a stream.
         * @return string The read content.
         */
        public function readAll () : string {
            $this->initialiseAction("read");
            
            $content = "";

            while (!$this->hasReachedEnd()) {
                $chunk = $this->read($this->chunkSize);

                if ($chunk === null) break;

                $content .= $chunk;
            }

            if ($this->seekable) {
                $this->rewindReader();
            }

            return $content;
        }

        /**
         * Reads the entire content of a stream as chunks.
         * @param int|null $size The maximum size of the each chunk.
         * @return iterable The read content in an iterable format.
         */
        public function readChunks (?int $size = null) : iterable {
            $size ??= $this->chunkSize;

            while (($chunk = $this->read($size)) !== null) yield $chunk;
        }

        /**
         * Reads a line or a number of bytes until the end of a line.
         * @param callable|int|null $function A function to execute after reading a line, or the next argument.
         * @param int $bytes The maximum number of bytes to read.
         * @return string|null The line or `null` if the end of the file has been reached.
         */
        public function readLine (callable|int|null $function = null, ?int $bytes = null) : ?string {
            # Initialise the action.
            $this->initialiseAction("read");

            # Omit the first argument if it's not callable.
            if (!is_callable($function)) {
                $bytes = $function;
                $function = null;
            }

            $line = "";
            $remaining = $bytes;
            $chunkSize = $this->chunkSize;

            while (true) {
                # If we have a max byte limit, limit the next chunk to remaining bytes.
                $readLength = $remaining !== null ? min($chunkSize, $remaining) : $chunkSize;

                # We add 1 extra byte as fgets reads up to n-1 bytes.
                $part = fgets($this->pointer, $readLength + 1);

                # Break the loop if the end of the line has been reached.
                if ($part === false || $part === "" && feof($this->pointer)) {
                    $this->reachedEndOfFile = true;
                    break;
                }

                $line .= $part;
                $length = strlen($part);

                if (str_ends_with($part, "\n")) {
                    break;
                }

                # Decrease the remaining counter if applicable.
                if ($remaining !== null) {
                    $remaining -= $length;

                    if ($remaining <= 0) break;
                }
            }

            if ($line === "") return null;
            
            $length = strlen($line);

            $input = new StreamInput([
                "content" => $line,
                "length" => $length,
                "offset" => $this->readingOffset
            ]);

            $this->readingOffset += $length;

            $this->storeInput($input);

            if (!is_null($function)) $function($input, $line);

            return $line;
        }

        /**
         * Reads all lines of a stream and runs a callback for each one.
         * @param callable $function A function to execute after reading a line.
         * @param int $bytes The maximum number of bytes to read
         * @return static The stream.
         */
        public function readLines (callable $function, ?int $bytes = null) : static {
            $i = 0;

            # Fetch a line of text from the stream repeatedly until there are none left.
            while (($line = $this->readLine($bytes)) !== null) {
                $input = end($this->cache);

                $result = $function($input, $i, $line);
                
                if ($result === false) break;

                $i++;
            }

            return $this;
        }

        /**
         * Rewinds the position of the pointer of a stream. Both the reading and writing offsets
         * are set to `0`.
         * @return static The stream.
         */
        public function rewind () : static {
            if (is_resource($this->pointer) === false) return $this;
            
            # Rewind the position of the pointer.
            rewind($this->pointer);

            # Reset the offsets.
            $this->rewindReader();
            $this->rewindWriter();

            return $this;
        }

        /**
         * Sets the reading offset to `0`.
         * @return static The stream.
         */
        public function rewindReader () : static {
            return $this->setReaderAt(0);
        }

        /**
         * Sets the reading offset to `0`.
         * @return static The stream.
         */
        public function rewindWriter () : static {
            return $this->setWriterAt(0);
        }

        /**
         * Reads formatted input from a stream using a format string.
         * @param string $format The format (like in fscanf).
         * @param mixed ...$vars Variables passed by reference to store scanned values.
         * @return int|false The number of items successfully read, or `false` on error.
         */
        public function scanf (string $format, &...$vars) : int|false {
            $this->initialiseAction("read");

            $result = fscanf($this->pointer, $format, ...$vars);

            if ($result === false) {
                $this->reachedEndOfFile = feof($this->pointer);
                return false;
            }

            return $result;
        }

        /**
         * Sets a stream's pointer for both reading and writing at a specified index.
         * @param int $index An index (if negative, it's assumed index from the end).
         * @param bool $relative Whether the index is relative to the current index.
         * @return static The stream.
         * @throws UnseekableStreamException If the stream isn't seekable.
         */
        public function seek (int $index, bool $relative = false) : static {
            # Throw an exception if the stream isn't seekable.
            if (!$this->seekable) throw new UnseekableStreamException();

            # Set the offsets to the given index.
            $this->setReaderAt($index, $relative);
            $this->setWriterAt($index, $relative);

            # Return the context.
            return $this;
        }

        /**
         * Set the cache size of a stream.
         * @param int $size The number of entries the cache can fit.
         * @return static The stream.
         */
        public function setCacheSize (int $size) : static {
            $this->cacheSize = $size;
            
            return $this;
        }

        /**
         * Set the chunk size of a stream.
         * @param int $bytes The chunk size in bytes.
         * @return static The stream.
         */
        public function setChunkSize (int $bytes) : static {
            $this->chunkSize = $bytes;
            
            return $this;
        }

        /**
         * Sets a stream's reading pointer at a specified index.
         * @param int $index An index (if negative, it's assumed index from the end).
         * @param bool $relative Whether the index is relative to the current index.
         * @return static The stream.
         * @throws UnseekableStreamException If the stream isn't seekable.
         */
        public function setReaderAt (int $index, bool $relative = false) : static {
            # Throw an exception if the stream isn't seekable.
            if (!$this->seekable) throw new UnseekableStreamException();

            # Set the reading offset to the given index.
            $this->setOffset($this->readingOffset, $index, $relative);

            fseek($this->pointer, $this->readingOffset, SEEK_SET);

            return $this;
        }

        /**
         * Sets a stream's writing pointer at a specified index.
         * @param int $index An index (if negative, it's assumed index from the end).
         * @param bool $relative Whether the index is relative to the current index.
         * @return static The stream.
         * @throws UnseekableStreamException If the stream isn't seekable.
         */
        public function setWriterAt (int $index, bool $relative = false) : static {
            # Throw an exception if the stream isn't seekable.
            if (!$this->seekable) throw new UnseekableStreamException();
            
            # Set the writing offset to the given index.
            return $this->setOffset($this->writingOffset, $index, $relative);
        }

        /**
         * Returns information about a stream.
         * @return array|false An array similar to fstat(), or `false` on failure.
         */
        public function stat () : array|false {
            if (!$this->isOpen()) $this->open();

            return fstat($this->pointer);
        }

        /**
         * Synchronises the in-memory buffer of a stream with the underlying storage (disk).
         * @return bool Whether the operation was successful.
         */
        public function sync () : bool {
            if (!$this->isOpen()) return false;

            if (function_exists("fsync")) return fsync($this->pointer);

            return fflush($this->pointer);
        }

        /**
         * Gets the current position of a stream's pointer.
         * @return int|null The current position of a stream's pointer.
         */
        public function tell () : ?int {
            # Throw an exception if the stream isn't seekable.
            if (!$this->seekable) throw new UnseekableStreamException();

            $i = ftell($this->pointer);

            if ($i === false) return null;

            return $i;
        }

        /**
         * Truncates a file to a given length.
         * @param int $bytes The number of bytes to truncate to; omit to truncate to zero (empty the stream).
         * @return static The stream.
         * @throws StreamException If the truncation fails.
         */
        public function truncate (int $bytes = 0) : static {
            # Initialise as a write action (this ensures the pointer and offset are in sync).
            $this->initialiseAction("write");

            # Perform the truncation.
            if (!ftruncate($this->pointer, $bytes)) {
                throw new StreamException("Failed to truncate stream at $bytes bytes.");
            }

            # Adjust internal state.
            $this->size = $bytes;
            $this->writingOffset = min($this->writingOffset, $bytes);

            # If the pointer is beyond the new end, move it to EOF.
            if (ftell($this->pointer) > $bytes) {
                fseek($this->pointer, 0, SEEK_END);
            }

            return $this;
        }

        /**
         * Unlocks the file of a stream.
         * @return bool Whether the unlocking was successful.
         */
        public function unlock () : bool {
            return flock($this->pointer, LOCK_UN);
        }

        /**
         * Writes into a stream.
         * @param string|static $input A text or stream.
         * @param int $bytes The maximum number of bytes to write.
         * @return static The stream.
         * @throws StreamNotWritableException If the stream cannot be written into.
         */
        public function write (string|self $input, ?int $bytes = null) : static {
            # Initialise the action.
            $this->initialiseAction("write");
            
            # Throw an exception if the mode doesn't contain writing permissions.
            if (!$this->isWritable()) throw new StreamNotWritableException();

            # Cache the number of bytes written in total.
            $bytesWritten = 0;

            # Check whether the input is a string.
            if (is_string($input)) {
                # Write the given number of bytes from the input into the stream.
                $bytesWritten = fwrite($this->pointer, $input, $bytes);

                # Throw an exception if the result is false.
                if ($bytesWritten === false) throw new StreamNotWritableException();
            }
            else {
                # Determine the chunk size.
                $chunkSize = min($this->chunkSize, $bytes ?? $this->chunkSize);

                # Cache the reading offset of the input stream.
                $offset = $input->readingOffset;
                
                # Rewind the stream and read all lines.
                $input->rewindReader()->readLines(function ($input) use (&$bytesWritten, $chunkSize) {
                    # Write the given number of bytes from the input into the stream.
                    $result = fwrite($this->pointer, $input->getContent(), $chunkSize);
                    
                    # Throw an exception if the result is false.
                    if ($result === false) throw new StreamNotWritableException();
                    
                    # Increment the total bytes written.
                    $bytesWritten += $result;
                });

                # Restore the reading offset of the input stream.
                $input->readingOffset = $offset;
            }

            # Increment the writing offset of the stream.
            $this->writingOffset += $bytesWritten;

            # Update the size only when writing extends past the current end.
            $this->size = max($this->size, $this->writingOffset);

            return $this;
        }
    }
?>