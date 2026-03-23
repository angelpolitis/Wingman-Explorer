<?php
    /**
     * Project Name:    Wingman Explorer - Importer Negotiation Strategy Interface
     * Created by:      Angel Politis
     * Creation Date:   Dec 19 2025
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2025-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer.Interfaces.IO namespace.
    namespace Wingman\Explorer\Interfaces\IO;

    /**
     * An interface for importer negotiation strategies.
     * @package Wingman\Explorer\Interfaces\IO
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    interface ImporterNegotiationStrategyInterface {
        /**
         * Selects the most appropriate importer from a list based on the file's characteristics.
         * @param ImporterInterface[] $importers The list of available importers.
         * @param string $path The path to the file.
         * @param string|null $mime The MIME type of the file (optional).
         * @param string|null $extension The file extension (optional).
         * @param string|null $sample A sample of the file's content (optional).
         * @return ImporterInterface|null The selected importer, or `null` if none is suitable.
         */
        public function select (array $importers, string $path, ?string $mime, ?string $extension, ?string $sample = null) : ?ImporterInterface;
    }
?>