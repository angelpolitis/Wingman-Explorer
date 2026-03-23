<?php
    /**
     * Project Name:    Wingman Explorer - Postprocessor Interface
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
     * An interface for content post-processors.
     * @package Wingman\Explorer\Interfaces\IO
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    interface PostprocessorInterface {
        /**
         * Processes given data.
         * @param mixed $data The data to process.
         * @param array $context Additional context for processing.
         * @return mixed The processed data.
         */
        public function process (mixed $data, array $context = []) : mixed;
    }
?>