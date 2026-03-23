<?php
    /**
     * Project Name:    Wingman Explorer - StreamInput
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
    
    /**
     * Represents an immutable chunk of stream input.
     * @package Wingman\Explorer\IO
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class StreamInput {
        /**
         * The content of a stream input.
         * @var string
         */
        protected string $content;

        /**
         * The length of a stream input.
         * @var int
         */
        protected int $length;

        /**
         * The offset of a stream input.
         * @var int
         */
        protected int $offset;

        /**
         * Creates a new stream input.
         * @param array $data The data of the stream input.
         */
        public function __construct (array $data) {
            foreach ($data as $key => $value)
                if (property_exists($this, $key))
                    $this->{$key} = $value;
        }

        /**
         * Gets the content of a stream input.
         * @return string The content.
         */
        public function getContent () : string {
            return $this->content;
        }

        /**
         * Gets the length of a stream input.
         * @return int The length.
         */
        public function getLength () : int {
            return $this->length;
        }

        /**
         * Gets the offset of a stream input.
         * @return int The offset.
         */
        public function getOffset () : int {
            return $this->offset;
        }
    }
?>