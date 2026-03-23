<?php
    /**
     * Project Name:    Wingman Explorer - Preprocessor Interface
     * Created by:      Angel Politis
     * Creation Date:   Dec 18 2025
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2025-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer.Interfaces.IO namespace.
    namespace Wingman\Explorer\Interfaces\IO;

    /**
     * An interface for content pre-processors.
     * @package Wingman\Explorer\Interfaces\IO
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    interface PreprocessorInterface {
        /**
         * Processes a given content.
         * @param string $content The content to process.
         * @param array $context Additional context for processing.
         * @return string The processed content.
         */
        public function process (string $content, array $context = []) : string;
    }
?>