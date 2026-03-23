<?php
    /**
     * Project Name:    Wingman Explorer - Inline File
     * Created by:      Angel Politis
     * Creation Date:   Dec 16 2025
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
    use Wingman\Explorer\Interfaces\Resources\EditableFileResource;
    use Wingman\Explorer\Traits\CanAccessByRangeInMemory;
    use Wingman\Explorer\Traits\CanEditContent;
    use Wingman\Explorer\Traits\CanEditLinesInMemory;
    use Wingman\Explorer\Traits\CanIterateContent;
    use Wingman\Explorer\Traits\CanReadLines;
    use Wingman\Explorer\Traits\CanReplaceContent;
    use Wingman\Explorer\Traits\CanSearchContent;

    /**
     * Represents an inline file.
     * @package Wingman\Explorer\Resources
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class InlineFile extends VirtualFile implements EditableFileResource, JsonSerializable {
        use CanAccessByRangeInMemory;
        use CanEditContent;
        use CanEditLinesInMemory;
        use CanIterateContent;
        use CanReadLines;
        use CanReplaceContent;
        use CanSearchContent;
        /**
         * Creates a new inline file.
         * @param string $content The content of the file.
         * @param array $metadata The metadata of the file.
         */
        public function __construct (string $content, array $metadata = []) {
            $this->content = $content;
            $this->metadata = $metadata;
        }

        /**
         * Returns the data that should be serialised for this object.
         * @return array The serialised data.
         */
        public function __serialize () : array {
            return ["content" => $this->content, "metadata" => $this->metadata];
        }

        /**
         * Restores the object's state from the given serialised data.
         * @param array $data The serialised data.
         */
        public function __unserialize (array $data) : void {
            $this->__construct($data["content"], $data["metadata"]);
        }
    
        /**
         * Gets the content of an inline file.
         * @return string The content of the inline file.
         */
        public function getContent () : string {
            return $this->content;
        }

        /**
         * Gets the size of an inline file in bytes.
         * @return int The size of the inline file in bytes.
         */
        public function getSize () : int {
            $content = $this->getContent();
            return strlen($content);
        }

        /**
         * Returns a JSON-serialisable representation of this inline file.
         * @return mixed The JSON-serialisable data.
         */
        public function jsonSerialize () : mixed {
            return array_merge(["type" => "inline_file", "content" => $this->content], $this->metadata);
        }
    }
?>