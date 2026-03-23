<?php
    /**
     * Project Name:    Wingman Explorer - Preselected Exporter Facade
     * Created by:      Angel Politis
     * Creation Date:   Dec 19 2025
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2025-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer.Facades namespace.
    namespace Wingman\Explorer\Facades;

    # Import the following classes to the current scope.
    use Wingman\Explorer\Interfaces\IO\ExporterInterface;

    /**
     * A facade used to export files using a pre-selected exporter.
     * @package Wingman\Explorer\Facades
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    final class PreselectedExporter {
        /**
         * Creates a new pre-selected exporter facade.
         * @param ExporterInterface $exporter The pre-selected exporter.
         */
        public function __construct (private ExporterInterface $exporter) {}

        /**
         * Import a file using a pre-selected exporter.
         * @param mixed $data The data to be exported.
         * @param string $file The path to the file.
         * @param array $options Additional options for exporting.
         * @return mixed The exported content.
         */
        public function export (mixed $data, string $file, array $options = []) : mixed {
            return $this->exporter->export($data, $file, $options);
        }

        /**
         * Prepare data for export using a pre-selected exporter.
         * @param mixed $data The data to be prepared.
         * @param array $options Additional options for preparing.
         * @return string The prepared data as a string.
         */
        public function prepare (mixed $data, array $options = []) : string {
            return $this->exporter->prepare($data, $options);
        }
    }
?>