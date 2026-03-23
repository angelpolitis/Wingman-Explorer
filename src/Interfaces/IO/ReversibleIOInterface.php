<?php
    /**
     * Project Name:    Wingman Explorer - Reversible IO Interface
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
     * A reversible IO interface for classes that links importers and exporters.
     * @package Wingman\Explorer\Interfaces\IO
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    interface ReversibleIOInterface {
        /**
         * Gets an exporter counterpart for an importer.
         * @return ExporterInterface
         */
        public function getExporter () : ExporterInterface;

        /**
         * Gets an importer counterpart for an exporter.
         * @return ImporterInterface
         */
        public function getImporter () : ImporterInterface;
    }
?>