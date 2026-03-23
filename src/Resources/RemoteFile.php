<?php
    /**
     * Project Name:    Wingman Explorer - Remote File
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
    use Wingman\Explorer\Enums\StreamMode;
    use Wingman\Explorer\Exceptions\StreamException;
    use Wingman\Explorer\Interfaces\Adapters\ReadableFilesystemAdapterInterface;
    use Wingman\Explorer\Interfaces\Resources\DirectoryResource;
    use Wingman\Explorer\Interfaces\Resources\RemoteFileResource;
    use Wingman\Explorer\IO\Stream;
    use Wingman\Locator\Objects\URI;

    /**
     * Represents a remote file whose I/O is delegated to a pluggable filesystem adapter.
     *
     * The concrete transport layer (HTTP, S3, FTP, etc.) is transparent to consumers; all
     * reading operations are performed through the injected {@see ReadableFilesystemAdapterInterface}.
     * @package Wingman\Explorer\Resources
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class RemoteFile extends File implements RemoteFileResource {
        /**
         * The filesystem adapter used to read this file.
         * @var ReadableFilesystemAdapterInterface
         */
        protected ReadableFilesystemAdapterInterface $adapter;

        /**
         * Creates a new remote file.
         * @param string $url The remote URL or canonical path of the file.
         * @param ReadableFilesystemAdapterInterface $adapter The adapter used to read the file.
         * @param DirectoryResource|null $parent The parent directory resource, if any.
         */
        public function __construct (string $url, ReadableFilesystemAdapterInterface $adapter, ?DirectoryResource $parent = null) {
            parent::__construct($url, $parent);
            $this->adapter = $adapter;
        }

        /**
         * Checks whether the remote file exists via the adapter.
         * @return bool Whether the file exists.
         */
        public function exists () : bool {
            return $this->adapter->exists($this->path);
        }

        /**
         * Reads and returns the full content of the remote file via the adapter.
         * @return string The file content.
         */
        public function getContent () : string {
            return $this->adapter->read($this->path);
        }

        /**
         * Returns the content of the remote file as a readable in-memory stream.
         * @return Stream A stream positioned at the start of the file content.
         * @throws StreamException If the stream cannot be created.
         */
        public function getContentStream () : Stream {
            $stream = Stream::from("php://temp", StreamMode::WRITE_READ_BINARY);
            $stream->write($this->adapter->read($this->path));
            $stream->rewindReader();
            return $stream;
        }

        /**
         * Gets the metadata of the remote file as reported by the adapter.
         * @return array The metadata array.
         */
        public function getMetadata () : array {
            return $this->adapter->getMetadata($this->path);
        }

        /**
         * Gets the last modified date of the remote file as reported by the adapter.
         * @return DateTimeImmutable The last modified date, or the Unix epoch if the adapter does not provide it.
         */
        public function getLastModified () : DateTimeImmutable {
            $modified = $this->adapter->getMetadata($this->path, ["modified"])["modified"] ?? null;
            return new DateTimeImmutable('@' . (is_int($modified) || is_string($modified) ? (int) $modified : 0));
        }

        /**
         * Gets the size of the remote file in bytes as reported by the adapter metadata.
         * @return int The size in bytes, or 0 if the adapter does not provide it.
         */
        public function getSize () : int {
            return (int) ($this->adapter->getMetadata($this->path, ["size"])["size"] ?? 0);
        }

        /**
         * Gets a parsed URI object representing the remote file's path.
         * @return URI The URI.
         */
        public function getUri () : URI {
            return URI::from($this->path);
        }
    }
?>