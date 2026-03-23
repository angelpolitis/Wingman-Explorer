<?php
    /**
     * Project Name:    Wingman Explorer - Preselected Importer Facade
     * Created by:      Angel Politis
     * Creation Date:   Dec 18 2025
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2025-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer.Facades namespace.
    namespace Wingman\Explorer\Facades;

    # Import the following classes to the current scope.
    use Wingman\Explorer\Interfaces\IO\ImporterInterface;

    /**
     * A facade used to import files using a pre-selected importer.
     * @package Wingman\Explorer\Facades
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    final class PreselectedImporter {
        /**
         * Creates a new pre-selected importer facade.
         * @param ImporterInterface $importer The pre-selected importer.
         */
        public function __construct (private ImporterInterface $importer) {}

        /**
         * Import a file using a pre-selected importer.
         * @param string $file The path to the file.
         * @param array $options Additional options for importing.
         * @return mixed The imported content.
         */
        public function import (string $file, array $options = []) : mixed {
            return $this->importer->import($file, $options);
        }
    }
?>