<?php
    /**
     * Project Name:    Wingman Explorer - Generated File
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
    use Exception;
    use JsonSerializable;
    use Wingman\Explorer\Interfaces\Resources\EditableFileResource;
    use Wingman\Explorer\Traits\CanAccessByRangeInMemory;
    use Wingman\Explorer\Traits\CanEditContent;
    use Wingman\Explorer\Traits\CanEditLinesInMemory;
    use Wingman\Explorer\Traits\CanIterateContent;
    use Wingman\Explorer\Traits\CanReadLines;
    use Wingman\Explorer\Traits\CanReplaceContent;
    use Wingman\Explorer\Traits\CanSearchContent;

    /**
     * Represents a generated file.
     * @package Wingman\Explorer\Resources
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class GeneratedFile extends VirtualFile implements EditableFileResource, JsonSerializable {
        use CanAccessByRangeInMemory;
        use CanEditContent;
        use CanEditLinesInMemory;
        use CanIterateContent;
        use CanReadLines;
        use CanReplaceContent;
        use CanSearchContent;
        
        /**
         * The generator function.
         * @var callable
         */
        protected $generator;

        /**
         * Creates a new generated file.
         * @param callable $generator The generator function.
         * @param array $metadata The metadata of the file.
         */
        public function __construct (callable $generator, array $metadata = []) {
            $this->generator = $generator;
            $this->metadata = $metadata;
        }

        /**
         * Returns the callable-resolved content and metadata for serialisation.
         *
         * The callable is invoked at this point so that the serialised form always
         * captures the generated value; callables themselves cannot be serialised.
         * @return array The serialised data.
         */
        public function __serialize () : array {
            return ["content" => $this->getContent(), "metadata" => $this->metadata];
        }

        /**
         * Restores the object's state from the given serialised data.
         *
         * The frozen content string is wrapped in a static closure to satisfy the
         * callable contract expected by {@see getContent()}.
         * @param array $data The serialised data.
         */
        public function __unserialize (array $data) : void {
            $content = $data["content"];
            $this->generator = static fn() => $content;
            $this->metadata = $data["metadata"];
        }

        /**
         * Gets the content of a generated file by invoking the generator function.
         * @return string The content of the generated file.
         */
        public function getContent () : string {
            if ($this->content !== null) return $this->content;

            try {
                return call_user_func($this->generator);
            }
            catch (Exception $e) {
                return "";
            }
        }

        /**
         * Gets the size of a generated file in bytes.
         * @return int The size of the generated file in bytes.
         */
        public function getSize () : int {
            $content = $this->getContent();
            return strlen($content);
        }

        /**
         * Returns a JSON-serialisable representation of this generated file.
         * @return mixed The JSON-serialisable data.
         */
        public function jsonSerialize () : mixed {
            return array_merge(["type" => "generated_file", "content" => $this->getContent()], $this->metadata);
        }
    }
?>