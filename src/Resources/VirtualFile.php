<?php
    /**
     * Project Name:    Wingman Explorer - Virtual File
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
    use Wingman\Explorer\Interfaces\Resources\VirtualResource;
    use Wingman\Explorer\IO\Stream;

    /**
     * Represents a virtual file.
     * @package Wingman\Explorer\Resources
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    abstract class VirtualFile extends File implements VirtualResource {
        /**
         * The metadata of the virtual file.
         * @var array
         */
        protected array $metadata = [];

        /**
         * Checks whether the virtual file exists.
         * @return true
         */
        public function exists () : bool {
            return true;
        }

        /**
         * Returns a readable stream over the file's current in-memory content.
         * The stream is fully rewound and ready for reading.
         * @return Stream A readable stream containing the file's content.
         */
        public function getContentStream () : Stream {
            $stream = Stream::from("php://memory", StreamMode::WRITE_READ_BINARY);
            $stream->write($this->getContent());
            $stream->rewindReader();
            return $stream;
        }

        /**
         * Gets the last modified date of a virtual file.
         * @return DateTimeImmutable The last modified date from metadata, or the Unix epoch if unset.
         */
        public function getLastModified () : DateTimeImmutable {
            $modified = $this->metadata["last_modified"] ?? null;
            if ($modified instanceof DateTimeImmutable) {
                return $modified;
            }
            return new DateTimeImmutable("@0");
        }

        /**
         * Gets the metadata of a virtual file.
         * @return array The metadata.
         */
        public function getMetadata () : array {
            return $this->metadata;
        }

        /**
         * Gets the size of a virtual file.
         * @return int The size of the virtual file in bytes.
         */
        abstract public function getSize () : int;

        /**
         * Replaces the whole in-memory content of the file with `$content`.
         * For generated files, this freezes the content and stops invoking the generator.
         * @param string $content The new content.
         * @return static The file.
         */
        public function write (string $content) : static {
            $this->content = $content;
            return $this;
        }
    }
?>