<?php
    /**
     * Project Name:    Wingman Explorer - Pipeline Exporter
     * Created by:      Angel Politis
     * Creation Date:   Dec 19 2025
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2025-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer.IO.Exporters namespace.
    namespace Wingman\Explorer\IO\Exporters;

    # Import the following classes to the current scope.
    use Wingman\Explorer\Exceptions\ExportException;
    use Wingman\Explorer\Interfaces\IO\ExporterInterface;

    /**
     * An exporter that chains multiple exporters in sequence.
     *
     * Each step's {@see ExporterInterface::prepare()} output is fed as input
     * to the next step, forming a transformation pipeline. The final output
     * is written to the target file. Confidence is reported as the arithmetic
     * mean across all registered steps.
     *
     * @package Wingman\Explorer\IO\Exporters
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class PipelineExporter implements ExporterInterface {
        /**
         * The steps in the pipeline.
         * @var ExporterInterface[]
         */
        protected array $steps = [];

        /**
         * Creates a new pipeline exporter.
         * @param ExporterInterface[] $steps The steps in the pipeline.
         */
        public function __construct (array $steps = []) {
            $this->steps = $steps;
        }

        /**
         * Adds a step to a pipeline.
         * @param ExporterInterface $exporter The exporter to add.
         * @return static The pipeline exporter.
         */
        public function addStep (ExporterInterface $exporter) : static {
            $this->steps[] = $exporter;
            return $this;
        }

        /**
         * Runs the pipeline and writes the final output to a file.
         * @param mixed $data The data to export.
         * @param string $path The path to the file.
         * @param array $options Additional options passed to each step.
         * @return static The pipeline exporter instance.
         * @throws ExportException If the file cannot be written.
         */
        public function export (mixed $data, string $path, array $options = []) : static {
            $content = $this->prepare($data, $options);
            if (file_put_contents($path, $content) === false) {
                throw new ExportException("Unable to write pipeline output to '{$path}'.");
            }
            return $this;
        }

        /**
         * Gets the average confidence score across all registered steps.
         * @param mixed $data The data to evaluate.
         * @param string|null $extension The file extension (optional).
         * @param string|null $mime The MIME type of the file (optional).
         * @return float The average confidence score between 0.0 and 1.0.
         */
        public function getConfidence (mixed $data, ?string $extension = null, ?string $mime = null) : float {
            # Average confidence across all steps.
            $sum = 0;
            foreach ($this->steps as $exporter) {
                $sum += $exporter->getConfidence($data, $extension, $mime);
            }
            return $sum / max(1, count($this->steps));
        }

        /**
         * Passes data through each step's prepare() method in sequence.
         * @param mixed $data The data to transform.
         * @param array $options Additional options passed to each step.
         * @return string The final transformed output.
         */
        public function prepare (mixed $data, array $options = []) : string {
            $currentData = $data;

            foreach ($this->steps as $exporter) {
                $currentData = $exporter->prepare($currentData, $options);
            }

            return $currentData;
        }

        /**
         * Checks whether all steps in the pipeline support the given data and hints.
         * @param mixed $data The data to evaluate.
         * @param string|null $extension The file extension (optional).
         * @param string|null $mime The MIME type of the file (optional).
         * @return bool Whether all steps support the data.
         */
        public function supports (mixed $data, ?string $extension = null, ?string $mime = null) : bool {
            foreach ($this->steps as $exporter) {
                if (!$exporter->supports($data, $extension, $mime)) {
                    return false;
                }
            }
            return true;
        }

        /**
         * Checks whether all steps in the pipeline support the given file extension.
         * @param string $extension The file extension.
         * @return bool Whether all steps support the extension.
         */
        public function supportsExtension (string $extension) : bool {
            foreach ($this->steps as $exporter) {
                if (!$exporter->supportsExtension($extension)) {
                    return false;
                }
            }
            return true;
        }

        /**
         * Checks whether all steps in the pipeline support the given MIME type.
         * @param string $mime The MIME type.
         * @return bool Whether all steps support the MIME type.
         */
        public function supportsMime (string $mime) : bool {
            foreach ($this->steps as $exporter) {
                if (!$exporter->supportsMime($mime)) {
                    return false;
                }
            }
            return true;
        }

        /**
         * Creates a pipeline exporter with a single step.
         * @param ExporterInterface $step The step to add.
         * @return static The pipeline exporter.
         */
        public static function withStep (ExporterInterface $step) : static {
            return new static([$step]);
        }

        /**
         * Creates a pipeline exporter with multiple steps.
         * @param ExporterInterface ...$steps The steps to add.
         * @return static The pipeline exporter.
         */
        public static function withSteps (ExporterInterface ...$steps) : static {
            return new static($steps);
        }
    }
?>