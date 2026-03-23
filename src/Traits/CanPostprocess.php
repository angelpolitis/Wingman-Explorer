<?php
    /**
     * Project Name:    Wingman Explorer - Can Postprocess Trait
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

    /**
     * A trait for classes that can postprocess data.
     * @package Wingman\Explorer\Traits
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    trait CanPostprocess {
        /**
         * The post-processors to apply.
         * @var PostprocessorInterface[]
         */
        protected array $postprocessors = [];

        /**
         * Applies all post-processors to the given data.
         * @param mixed $data The data to process.
         * @param array $context The context for processing.
         * @return mixed The processed data.
         */
        protected function postprocess (mixed $data, array $context = []) : mixed {
            foreach ($this->postprocessors as $p) {
                $data = $p->process($data, $context);
            }
            return $data;
        }

        /**
         * Adds a postprocessor.
         * @param PostprocessorInterface $processor The postprocessor to add.
         * @return static The current instance.
         */
        public function addPostprocessor (PostprocessorInterface $processor) : static {
            $this->postprocessors[] = $processor;
            return $this;
        }
    }
?>