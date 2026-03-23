<?php
    /**
     * Project Name:    Wingman Explorer - Streamable Importer Interface
     * Created by:      Angel Politis
     * Creation Date:   Mar 20 2026
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer.Interfaces.IO namespace.
    namespace Wingman\Explorer\Interfaces\IO;

    # Import the following classes to the current scope.
    use Wingman\Explorer\IO\Stream;

    /**
     * Implemented by importers that can consume data from a {@see Stream} object
     * rather than requiring a file path on disk.
     *
     * Streaming importers allow large sources to be processed incrementally or
     * to operate on in-memory streams without creating temporary files.
     *
     * @package Wingman\Explorer\Interfaces\IO
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    interface StreamableImporterInterface {
        /**
         * Imports data from the given stream.
         * @param Stream $stream The source stream to read from.
         * @param array $options Format-specific options, identical in semantics to {@see ImporterInterface::import()}.
         * @return mixed The parsed data, following the same contract as the corresponding {@see ImporterInterface::import()} method.
         */
        public function importStream (Stream $stream, array $options = []) : mixed;
    }
?>