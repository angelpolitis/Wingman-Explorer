<?php
    /**
     * Project Name:    Wingman Explorer - File
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
    use Wingman\Locator\Exceptions\NonexistentFileException;
    use Wingman\Explorer\Interfaces\Resources\DirectoryResource;
    use Wingman\Explorer\Interfaces\Resources\FileResource;
    use Wingman\Explorer\IO\Stream;

    /**
     * Represents a file.
     * @package Wingman\Explorer\Resources
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    abstract class File implements FileResource {
        /**
         * The base name of a file.
         * @var string
         */
        protected string $baseName;

        /**
         * The content of a file.
         * @var string|null
         */
        protected ?string $content = null;

        /**
         * The extension of a file.
         * @var string|null
         */
        protected ?string $extension = null;

        /**
         * The path of a file.
         * @var string
         */
        protected string $path;

        /**
         * The name of a file.
         * @var string|null
         */
        protected ?string $name = null;

        /**
         * The parent directory of a file.
         * @var ?DirectoryResource
         */
        protected ?DirectoryResource $parent;

        /**
         * The parent directory of a file.
         * @var string|null
         */
        protected ?string $parentDirectory = null;

        /**
         * Creates a new file.
         * @param string $path The path of the file.
         */
        public function __construct (string $path, ?DirectoryResource $parent = null) {
            $this->baseName = basename($path);
            $this->extension = pathinfo($path, PATHINFO_EXTENSION);
            $this->name = pathinfo($path, PATHINFO_FILENAME);
            $this->parentDirectory = dirname($path);
            $this->path = $path;
            $this->parent = $parent;
        }

        public static function at (string $path) : static {
            return new static($path);
        }

        public function getBaseName () : string {
            return $this->baseName;
        }

        public function getContent () : string {
            return $this->getContentStream()->readAll();
        }

        public function getContentStream () : Stream {
            if (!$this->exists()) {
                throw new NonexistentFileException("The file '{$this->path}' no longer exists.");
            }
            return new Stream($this->path);
        }

        public function getExtension () : ?string {
            return $this->extension;
        }

        public function getName () : ?string {
            return $this->name;
        }

        public function getParent () : ?DirectoryResource {
            return $this->parent;
        }

        public function getParentDirectory () : ?string {
            return $this->parentDirectory;
        }
        
        public function getPath () : string {
            return $this->path;
        }

        public function render () : string {
            return $this->getContent();
        }
    }
?>