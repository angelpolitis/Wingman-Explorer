<?php
    /**
     * Project Name:    Wingman Explorer - Can Preprocess Trait
     * Created by:      Angel Politis
     * Creation Date:   Dec 18 2025
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2025-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */
    
    # Use the Explorer.Traits namespace.
    namespace Wingman\Explorer\Traits;

    # Import the following classes to the current scope.
    use Wingman\Explorer\Interfaces\IO\PreprocessorInterface;

    /**
     * A trait for classes that can preprocess data.
     * @package Wingman\Explorer\Traits
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    trait CanPreprocess {
        /**
         * The preprocessors to apply.
         * @var PreprocessorInterface[]
         */
        protected array $preprocessors = [];

        /**
         * Applies all pre-processors to the given content.
         * @param mixed $content The content to process.
         * @param array $context The context for processing.
         * @return mixed The processed content.
         */
        protected function preprocess (mixed $content, array $context = []) : mixed {
            foreach ($this->preprocessors as $p) {
                $content = $p->process($content, $context);
            }
            return $content;
        }

        /**
         * Adds a preprocessor.
         * @param PreprocessorInterface $processor The preprocessor to add.
         * @return static The current instance.
         */
        public function addPreprocessor (PreprocessorInterface $processor) : static {
            $this->preprocessors[] = $processor;
            return $this;
        }
    }
?>