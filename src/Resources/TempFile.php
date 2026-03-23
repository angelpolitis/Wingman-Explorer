<?php
    /**
     * Project Name:    Wingman Explorer - Temporary File
     * Created by:      Angel Politis
     * Creation Date:   Dec 20 2025
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2025-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer.Resources namespace.
    namespace Wingman\Explorer\Resources;
    
    # Import the following classes to the current scope.
    use JsonSerializable;
    use Wingman\Explorer\Exceptions\InvalidContentTypeException;
    use Wingman\Explorer\Exceptions\TempFileException;
    use Throwable;
    use Wingman\Explorer\IO\Stream;
    use Wingman\Explorer\Enums\StreamMode;

    /**
     * Represents a temporary file.
     * @package Wingman\Explorer\Resources
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class TempFile implements JsonSerializable {
        /**
         * The name of the target file.
         * @var string
         */
        protected string $targetName;

        /**
         * The mime type of the target file.
         * @var string
         */
        protected string $type;

        /**
         * The path of a temporary file.
         * @var string|null
         */
        protected ?string $path = null;

        /**
         * The size of a temporary file.
         * @var int
         */
        protected int $size = 0;

        /**
         * The error code of a temporary file.
         * @var int
         */
        protected int $error = UPLOAD_ERR_OK;

        /**
         * Private constructor to enforce factory creation.
         * @param string $targetName The name of the target file.
         * @param string $type The MIME type.
         */
        private function __construct (string $targetName, string $type) {
            $this->targetName = $targetName;
            $this->type = $type;
        }

        /**
         * Destroys a temporary file.
         */
        public function __destruct () {
            $this->delete();
        }

        /**
         * Creates a new temporary file.
         * @param string $targetName The name of the target file.
         * @param string $type The MIME type.
         * @param string|resource|Stream|callable $content The content or a stream or callable that returns the content.
         * @return static A new temporary file.
         */
        public static function create (string $targetName, string $type, mixed $content) : static {
            $file = new static($targetName, $type);

            $tempDir = sys_get_temp_dir();
            if (empty($tempDir)) {
                $file->error = UPLOAD_ERR_NO_TMP_DIR;
                return $file;
            }

            $path = tempnam($tempDir, "wingman_explorer_");
            $file->path = $path;

            try {
                $stream = Stream::from($path)->open(StreamMode::WRITE_BINARY);

                if (is_callable($content)) {
                    $content = $content();
                }

                if (is_resource($content) && get_resource_type($content) === "stream") {
                    $content = Stream::for($content);
                }

                if ($content instanceof Stream) {
                    $content->rewind();
                    while (!$content->hasReachedEnd()) {
                        $stream->write($content->read(8192));
                    }
                }
                elseif (is_string($content)) {
                    $stream->write($content);
                }
                else throw new InvalidContentTypeException("Content must be string, Stream, resource, or callable returning one of these.");

            }
            catch (Throwable $e) {
                $file->error = UPLOAD_ERR_CANT_WRITE;
                return $file;
            }
            finally {
                $stream->close();
            }

            $file->size = filesize($path) ?: 0;

            return $file;
        }
        
        /**
         * Deletes a temporary file.
         */
        public function delete () : void {
            if (file_exists($this->path)) {
                @unlink($this->path);
            }
        }
        
        /**
         * Specifies data which should be serialized to JSON.
         * @return array{name:string,type:string,tmp_name:string|null,size:int,error:int}
         */
        public function jsonSerialize () : array {
            return $this->toArray();
        }
        
        /**
         * Moves a temporary file to its final destination.
         * @param string $targetDirectory The target directory.
         */
        public function moveTo (string $targetDirectory) : LocalFile {
            if (!file_exists($this->path)) {
                throw new TempFileException("Temporary file does not exist: {$this->targetName}");
            }

            if (is_dir($targetDirectory)) {
                if (!is_writable($targetDirectory)) {
                    throw new TempFileException("Target directory is not writable: {$targetDirectory}");
                }
            }
            else if (!@mkdir($targetDirectory, 0775, true)) {
                throw new TempFileException("Failed to create target directory: {$targetDirectory}");
            }

            $target = $targetDirectory . DIRECTORY_SEPARATOR . $this->targetName;

            if (!@rename($this->path, $target)) {
                throw new TempFileException("Failed to move temporary file to {$target}");
            }

            $this->path = $target;

            return new LocalFile($target);
        }

        /**
         * Converts a temporary file into an array compatible with PHP uploads.
         * @return array{name:string,type:string,tmp_name:string|null,size:int,error:int}
         */
        public function toArray () : array {
            return [
                "name" => $this->targetName,
                "type" => $this->type,
                "tmp_name" => $this->path,
                "size" => $this->size,
                "error" => $this->error
            ];
        }
    }
?>