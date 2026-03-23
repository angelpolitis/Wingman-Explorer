<?php
    /**
     * Project Name:    Wingman Explorer - Configurable Trait
     * Created by:      Angel Politis
     * Creation Date:   Nov 10 2025
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2025-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer.Traits namespace.
    namespace Wingman\Explorer\Traits;

    # Import the following classes to the current scope.
    use Wingman\Cortex\Configuration as Cfg;

    /**
     * A trait that provides support for default configuration via the Environment.
     * @package Wingman\Explorer\Traits
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    trait Configurable {
        /**
         * The configurations.
         * @var array
         */
        protected array $config = [];
        
        /**
         * Gets the default configuration of a subclass.
         * @return array The default configuration.
         */
        abstract protected static function getDefaultConfig () : array;

        /**
         * Configures an instance using a provided configurer.
         * @param callable $configurer A callable function that returns the value to add to a configuration.
         */
        private function configure (array $config, callable $configurer) : void {
            foreach (static::getDefaultConfig() as $variable => $value) {
                $prefixedVariable = static::getConfigPrefix() . $variable;

                $config[$variable] ??= $configurer($prefixedVariable, $value);
            }

            $this->config = $config;
        }

        /**
         * Configures an instance using the default configurations.
         * 
         * If the Environment class is available, it looks to the Environment for each configuration 
         * and falls back to the value already saved in the class, if the respective configuration 
         * isn't defined.
         * 
         * @param array|null $config The configuration to use, if any.
         * 
         */
        protected function useConfig (?array $config = null) : void {
            # Normalise the configuration array with the priority "config > environment > default".
            $config ??= [];

            # Decide on the configurer to use based on whether the Configuration class is available.
            $configurer = class_exists(Cfg::class)
                ? fn ($name, $value) => Cfg::find()->isSet($name) ? Cfg::find()->get($name) : $value
                : fn ($name, $value) => $value;
                
            $this->configure($config, $configurer);
        }
        
        /**
         * Gets the configurations of a configurable.
         * @param string $name The name of a specific configuration.
         * @return mixed One or all configurations of a configurable.
         */
        public function getConfig (?string $name = null) : mixed {
            if (isset($name)) {
                return $this->config[$name] ?? null;
            }

            return $this->config;
        }
        
        /**
         * Gets the configuration prefix of a configurable.
         * @return string The configuration prefix of a configurable.
         */
        public static function getConfigPrefix () : string {
            /** @disregard P1014 */
            return property_exists(static::class, "configPrefix") ? static::$configPrefix : "";
        }
    }
?>