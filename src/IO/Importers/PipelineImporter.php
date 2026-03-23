<?php
    /**
     * Project Name:    Wingman Explorer - Pipeline Importer
     * Created by:      Angel Politis
     * Creation Date:   Mar 22 2026
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer.IO.Importers namespace.
    namespace Wingman\Explorer\IO\Importers;

    # Import the following classes to the current scope.
    use Wingman\Explorer\Exceptions\ImportException;
    use Wingman\Explorer\Interfaces\IO\ImporterInterface;

    /**
     * An importer that chains multiple importers in sequence.
     *
     * The first step reads the file at the supplied path and produces an
     * intermediate result. Each subsequent step receives that result: if it is
     * a string, it is written to a temporary file so that the next importer can
     * consume it via its normal `import($path)` contract. The temporary file is
     * deleted immediately after each step, regardless of success or failure.
     *
     * A non-string intermediate value may not be passed to a subsequent step
     * because importers operate on file paths. If such a situation arises, an
     * `ImportException` is thrown.
     *
     * Confidence, support, extension, and MIME queries are all delegated to the
     * first step, since that step is the one reading the original file.
     *
     * @package Wingman\Explorer\IO\Importers
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class PipelineImporter implements ImporterInterface {
        /**
         * The steps in the pipeline.
         * @var ImporterInterface[]
         */
        protected array $steps = [];

        /**
         * Creates a new pipeline importer.
         * @param ImporterInterface[] $steps The steps in the pipeline.
         */
        public function __construct (array $steps = []) {
            $this->steps = $steps;
        }

        /**
         * Adds a step to the pipeline.
         * @param ImporterInterface $importer The importer to add.
         * @return static The pipeline importer.
         */
        public function addStep (ImporterInterface $importer) : static {
            $this->steps[] = $importer;
            return $this;
        }

        /**
         * Gets the confidence level of the pipeline for a given file.
         * Delegates entirely to the first step, which is the step responsible
         * for reading the original file.
         * @param string $path The path to the file.
         * @param string|null $extension The file extension (optional).
         * @param string|null $mime The MIME type of the file (optional).
         * @param string $sample A sample of the file's content.
         * @return float A confidence score between 0.0 and 1.0, or 0.0 if the pipeline is empty.
         */
        public function getConfidence (string $path, ?string $extension, ?string $mime, string $sample) : float {
            if (empty($this->steps)) {
                return 0.0;
            }

            return $this->steps[0]->getConfidence($path, $extension, $mime, $sample);
        }

        /**
         * Runs the pipeline and returns the final output.
         * The first step reads from the original file path. Each subsequent step
         * receives the previous step's string output written to a temporary file.
         * @param string $path The path to the file.
         * @param array $options Additional options passed to each step.
         * @return mixed The final imported result.
         * @throws ImportException If the pipeline has no steps, or if a non-string intermediate result
         *                         would need to be passed to a subsequent importer.
         */
        public function import (string $path, array $options = []) : mixed {
            if (empty($this->steps)) {
                throw new ImportException("PipelineImporter has no steps configured.");
            }

            $result = $this->steps[0]->import($path, $options);

            foreach (array_slice($this->steps, 1) as $index => $step) {
                if (!is_string($result)) {
                    $stepNumber = $index + 2;
                    throw new ImportException(
                        "PipelineImporter: step $stepNumber expects a string intermediate result (to write to a" .
                        " temporary file), but received " . gettype($result) . ". Only string values can be piped" .
                        " between importers."
                    );
                }

                $tempPath = tempnam(sys_get_temp_dir(), "wingman_pipeline_");

                try {
                    file_put_contents($tempPath, $result);
                    $result = $step->import($tempPath, $options);
                }
                finally {
                    @unlink($tempPath);
                }
            }

            return $result;
        }

        /**
         * Checks whether the pipeline can handle the given file.
         * Delegates to the first step.
         * @param string $path The path to the file.
         * @param string|null $extension The file extension (optional).
         * @param string|null $mime The MIME type of the file (optional).
         * @return bool Whether the pipeline supports the file.
         */
        public function supports (string $path, ?string $extension = null, ?string $mime = null) : bool {
            if (empty($this->steps)) {
                return false;
            }

            return $this->steps[0]->supports($path, $extension, $mime);
        }

        /**
         * Checks whether the pipeline supports the given file extension.
         * Delegates to the first step.
         * @param string $extension The file extension.
         * @return bool Whether the extension is supported.
         */
        public function supportsExtension (string $extension) : bool {
            if (empty($this->steps)) {
                return false;
            }

            return $this->steps[0]->supportsExtension($extension);
        }

        /**
         * Checks whether the pipeline supports the given MIME type.
         * Delegates to the first step.
         * @param string $mime The MIME type.
         * @return bool Whether the MIME type is supported.
         */
        public function supportsMime (string $mime) : bool {
            if (empty($this->steps)) {
                return false;
            }

            return $this->steps[0]->supportsMime($mime);
        }

        /**
         * Creates a pipeline importer with a single step.
         * @param ImporterInterface $step The step to add.
         * @return static The pipeline importer.
         */
        public static function withStep (ImporterInterface $step) : static {
            return new static([$step]);
        }

        /**
         * Creates a pipeline importer with multiple steps.
         * @param ImporterInterface ...$steps The steps to add.
         * @return static The pipeline importer.
         */
        public static function withSteps (ImporterInterface ...$steps) : static {
            return new static($steps);
        }
    }
?>