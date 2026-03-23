<?php
    /**
     * Project Name:    Wingman Explorer - Local File
     * Created by:      Angel Politis
     * Creation Date:   Dec 14 2025
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2025-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer.Resources namespace.
    namespace Wingman\Explorer\Resources;

    # Import the following classes to the current scope.
    use DateTimeImmutable;
    use DateTimeZone;
    use Wingman\Explorer\Exceptions\AtomicReplaceException;
    use Wingman\Explorer\Exceptions\FilesystemException;
    use Wingman\Explorer\Exceptions\HashComputationException;
    use Wingman\Explorer\Exceptions\TempFileException;
    use Wingman\Locator\Exceptions\NonexistentFileException;
    use Wingman\Explorer\Enums\StreamMode;
    use Wingman\Explorer\Interfaces\Resources\EditableFileResource;
    use Wingman\Explorer\Interfaces\Resources\HashableResource;
    use Wingman\Explorer\Interfaces\Resources\LocalFileResource;
    use Wingman\Explorer\IO\Stream;
    use Wingman\Explorer\Traits\CanAccessByRange;
    use Wingman\Explorer\Traits\CanEditLines;
    use Wingman\Explorer\Traits\CanIterateContent;
    use Wingman\Explorer\Traits\CanReadLines;
    use Wingman\Explorer\Traits\CanReplaceContent;
    use Wingman\Explorer\Traits\CanSearchContent;

    /**
     * Represents a local file.
     * @package Wingman\Explorer\Resources
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class LocalFile extends File implements EditableFileResource, HashableResource, LocalFileResource {
        use CanAccessByRange;
        use CanEditLines;
        use CanIterateContent;
        use CanReadLines;
        use CanReplaceContent;
        use CanSearchContent;

        /**
         * The in-memory write buffer holding pending content before a `save()` call.
         * @var string|null
         */
        protected ?string $buffer = null;

        /**
         * Whether the file has unsaved changes.
         * @var bool
         */
        protected bool $dirty = false;

        /**
         * The path of a temporary file used during atomic stream writes.
         * @var string|null
         */
        protected ?string $tempPath = null;

        /**
         * The prefix used when creating temporary write files.
         */
        private const TEMP_PREFIX = ".wm_";

        /**
         * Creates a temporary file inside the parent directory and returns its path alongside
         * a write stream opened on it.
         * @throws TempFileException If the temp file cannot be created.
         * @return array{string, Stream} A tuple of [ temp path, write stream ].
         */
        protected function openTempStream () : array {
            $dir = $this->parentDirectory;
            if (!is_dir($dir)) LocalDirectory::at($dir)->create();

            $temp = tempnam($dir, self::TEMP_PREFIX);
            if ($temp === false) throw new TempFileException("Unable to create temp file.");

            return [$temp, Stream::from($temp, StreamMode::WRITE_BINARY)];
        }

        /**
         * Atomically promotes a newly written temp file to the active pending temp path,
         * removing any previously staged temp file.
         * @param string $newTempPath The path of the newly written temp file.
         */
        protected function rotateTempPath (string $newTempPath) : void {
            $old = $this->tempPath;
            $this->tempPath = $newTempPath;
            $this->dirty = true;
            if ($old && is_file($old)) unlink($old);
        }

        /**
         * Appends content to the file without reading the existing content into memory.
         * If a string buffer is already pending, the content is concatenated onto it.
         * If a stream temp file is pending, the content is appended to it directly.
         * In all other cases the content is written to disk immediately without buffering.
         * @param string $content The content to append.
         * @return static The file.
         */
        public function append (string $content) : static {
            if ($this->dirty && $this->buffer !== null) {
                $this->buffer .= $content;
                return $this;
            }

            $target = $this->dirty && $this->tempPath !== null ? $this->tempPath : $this->path;
            file_put_contents($target, $content, FILE_APPEND);
            return $this;
        }

        /**
         * Creates the file on disk, optionally creating parent directories.
         * @param bool $recursive Whether to create parent directories as needed [default: `true`].
         * @param int $permissions The permissions for any created directories [default: `0755`].
         * @return static The file.
         */
        public function create (bool $recursive = true, int $permissions = 0755) : static {
            if (!is_dir($this->parentDirectory)) {
                LocalDirectory::at($this->parentDirectory)->create($recursive, $permissions);
            }
            file_put_contents($this->path, '');
            return $this;
        }

        /**
         * Deletes this file from disk.
         * @return bool Whether the deletion was successful.
         */
        public function delete () : bool {
            return unlink($this->path);
        }

        /**
         * Discards any pending in-memory changes and removes any temporary write files.
         * @return static The file.
         */
        public function discard () : static {
            $this->buffer = null;
            $this->dirty = false;

            if ($this->tempPath && is_file($this->tempPath)) {
                unlink($this->tempPath);
            }

            $this->tempPath = null;

            return $this;
        }
        
        /**
         * Gets the current effective content of the file.
         * When unsaved changes are pending the content is resolved from the in-memory
         * buffer or the staging temp file rather than from disk.
         * @throws TempFileException If the temp file cannot be read.
         * @return string The current effective content.
         */
        public function getContent () : string {
            if ($this->dirty && $this->buffer !== null) return $this->buffer;

            if ($this->dirty && $this->tempPath !== null) {
                $content = file_get_contents($this->tempPath);
                if ($content === false) throw new TempFileException("Unable to read temp file: {$this->tempPath}");
                return $content;
            }

            return parent::getContent();
        }

        /**
         * Returns a readable stream over the current effective content of the file.
         * When unsaved changes are pending the stream is opened over the staging
         * temp file or constructed from the in-memory buffer rather than the
         * on-disk file.
         * @return Stream A readable stream positioned at the start of the content.
         */
        public function getContentStream () : Stream {
            if ($this->dirty && $this->tempPath !== null) {
                return Stream::from($this->tempPath, StreamMode::READ_BINARY);
            }

            if ($this->dirty && $this->buffer !== null) {
                $stream = Stream::from("php://memory", StreamMode::WRITE_READ_BINARY);
                $stream->write($this->buffer);
                $stream->rewindReader();
                return $stream;
            }

            return parent::getContentStream();
        }

        /**
         * Checks whether this file exists on disk.
         * @return bool Whether the file exists.
         */
        public function exists () : bool {
            $path = $this->path;
            return $path && is_file($path);
        }

        /**
         * Gets the last modified time of the file as a DateTimeImmutable.
         * The time is returned in the default timezone.
         * @return DateTimeImmutable The last modified time.
         * @throws FilesystemException If unable to read the last modified time.
         */
        public function getLastModified () : DateTimeImmutable {
            $time = filemtime($this->path);
    
            if ($time === false) {
                throw new FilesystemException("Unable to read mtime for file: {$this->path}");
            }
    
            return (new DateTimeImmutable('@' . $time))
                ->setTimezone(new DateTimeZone(date_default_timezone_get()));
        }

        /**
         * Gets an array of file metadata including inode, device, size, timestamps, permissions, ownership, and link count.
         * @return array An associative array of file metadata.
         * Keys include: `inode`, `device`, `size`, `accessed`, `modified`, `created`, `permissions`, `owner`, `group`, and `links`.
         * @throws FilesystemException If unable to read the file metadata.
         */
        public function getMetadata () : array {
            $path = $this->path;
            $stat = stat($path);

            if ($stat === false) {
                throw new FilesystemException("Unable to read file metadata: $path");
            }

            $timezone = new DateTimeZone(date_default_timezone_get());

            return [
                "inode" => $stat["ino"],
                "device" => $stat["dev"],
                "size" => $stat["size"],
                "accessed" => (new DateTimeImmutable('@' . $stat["atime"]))->setTimezone($timezone),
                "modified" => (new DateTimeImmutable('@' . $stat["mtime"]))->setTimezone($timezone),
                "created" => (new DateTimeImmutable('@' . $stat["ctime"]))->setTimezone($timezone),
                "permissions" => $stat["mode"] & 0x1FF,
                "owner" => $stat["uid"],
                "group" => $stat["gid"],
                "links" => $stat["nlink"]
            ];
        }

        /**
         * Returns the MD5 hash of the file.
         * @param bool $binary Whether to return raw binary output instead of a hex string.
         * @return string The MD5 hash.
         * @throws HashComputationException If the hash cannot be computed.
         */
        public function getMD5 (bool $binary = false) : string {
            $path = $this->path;
            $hash = hash_file('md5', $path, $binary);
            if ($hash === false) {
                throw new HashComputationException("Unable to compute MD5 for $path");
            }
            return $hash;
        }

        /**
         * Returns the SHA1 hash of the file.
         * @param bool $binary Whether to return raw binary output instead of a hex string.
         * @return string The SHA1 hash.
         * @throws HashComputationException If the hash cannot be computed.
         */
        public function getSHA1 (bool $binary = false) : string {
            $path = $this->path;
            $hash = hash_file('sha1', $path, $binary);
            if ($hash === false) {
                throw new HashComputationException("Unable to compute SHA1 for $path");
            }
            return $hash;
        }

        /**
         * @throws FilesystemException If unable to determine the file size.
         */
        public function getSize () : int {
            $path = $this->path;

            clearstatcache(true, $path);

            $size = filesize($path);

            if ($size !== false) return $size;

            $stat = stat($path);
            if ($stat !== false) return $stat["size"];

            throw new FilesystemException("Unable to determine file size: $path");
        }

        /**
         * Returns the parent directory of this file.
         * @throws NonexistentFileException If the file no longer exists.
         * @return LocalDirectory|null The parent directory, or `null` if there is none.
         */
        public function getParent () : ?LocalDirectory {
            if (!$this->exists()) {
                throw new NonexistentFileException("The file '{$this->path}' no longer exists.");
            }

            if (!$this->parentDirectory) return null;
            
            return new LocalDirectory($this->parentDirectory);
        }

        /**
         * Prepends content to the file using a stream, avoiding full file reads.
         * If a string buffer is already pending, the content is prepended to it.
         * Otherwise, the original file (or pending temp file) is streamed chunk by chunk
         * into a new temp file, preceded by the given content.
         * @param string $content The content to prepend.
         * @return static The file.
         * @throws TempFileException If the temp file cannot be created or opened.
         */
        public function prepend (string $content) : static {
            if ($this->dirty && $this->buffer !== null) {
                $this->buffer = $content . $this->buffer;
                return $this;
            }

            $source = $this->dirty && $this->tempPath !== null ? $this->tempPath : $this->path;
            [$temp, $out] = $this->openTempStream();
            $out->write($content);
            if (is_file($source)) Stream::from($source, StreamMode::READ_BINARY)->copyTo($out);
            $out->close();
            $this->rotateTempPath($temp);

            return $this;
        }

        /**
         * Persists any pending in-memory changes to disk.
         * Uses an atomic rename strategy when a stream write has been buffered.
         * @throws AtomicReplaceException If the atomic replace fails.
         * @return static The file.
         */
        public function save () : static {
            if (!$this->dirty) {
                return $this;
            }

            $target = $this->path;

            if ($this->tempPath !== null) {
                if (!rename($this->tempPath, $target)) {
                    throw new AtomicReplaceException("Atomic replace failed.");
                }

                $this->tempPath = null;
            }
            else {
                file_put_contents($target, $this->buffer);
            }

            $this->dirty = false;
            $this->buffer = null;

            return $this;
        }
        
        /**
         * Buffers a new string content to be written to disk on the next `save()` call.
         * @param string $content The content to write.
         * @return static The file.
         */
        public function write (string $content) : static {
            $this->buffer = $content;
            $this->dirty = true;
            return $this;
        }

        /**
         * Drains a stream into a temporary file to be atomically moved on the next `save()` call.
         * @param Stream $stream The stream to read from.
         * @return static The file.
         */
        public function writeStream (Stream $stream) : static {
            [$temp, $out] = $this->openTempStream();
            $stream->rewind()->copyTo($out);
            $out->close();
            $this->rotateTempPath($temp);
            return $this;
        }
    }
?>