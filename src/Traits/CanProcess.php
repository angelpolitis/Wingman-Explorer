<?php
    /**
     * Project Name:    Wingman Explorer - Processing Aware Trait
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
    use Wingman\Explorer\Interfaces\IO\PostprocessorInterface;
    use Wingman\Explorer\Interfaces\IO\PreprocessorInterface;

    /**
     * A trait for classes that can preprocess and postprocess data.
     * @package Wingman\Explorer\Traits
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    trait CanProcess {
        use CanPreprocess, CanPostprocess;

        /**
         * Adds a processor (preprocessor or postprocessor).
         * @param PreprocessorInterface|PostprocessorInterface $processor The processor to add.
         * @return static The current instance.
         */
        public function addProcessor (PreprocessorInterface|PostprocessorInterface $processor) : static {
            if ($processor instanceof PreprocessorInterface) {
                return $this->addPreprocessor($processor);
            }
            
            return $this->addPostprocessor($processor);
        }
    }
?>