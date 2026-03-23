<?php
    /**
     * Project Name:    Wingman Explorer - PHP Importer
     * Created by:      Angel Politis
     * Creation Date:   Dec 18 2025
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2025-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer.IO.Importers namespace.
    namespace Wingman\Explorer\IO\Importers;

    # Import the following classes to the current scope.
    use Wingman\Explorer\Interfaces\IO\ImporterInterface;
    use Wingman\Explorer\Traits\CanPostprocess;

    /**
     * An importer for PHP files.
     *
     * Executes the target PHP file in an isolated scope via an anonymous class,
     * captures its output buffer, and returns the result as a string. Variables
     * can be injected into the execution scope via the <code>scope</code>
     * option, and the file can be included with <code>require</code> semantics
     * (fatal on missing) instead of the default <code>include</code> semantics.
     *
     * Supported options:
     * - <code>scope</code> (array, default <code>[]</code>) — variables to extract into the execution scope.
     * - <code>require</code> (bool, default <code>false</code>) — use <code>require</code> instead of <code>include</code>.
     *
     * **Security notice:** this importer executes arbitrary PHP code. Only use it
     * with files from trusted sources — never with content derived from user input,
     * external API responses, or any other untrusted data. Importing a file from an
     * attacker-controlled path is equivalent to remote code execution.
     *
     * @package Wingman\Explorer\IO\Importers
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class PhpImporter implements ImporterInterface {
        use CanPostprocess;
        
        /**
         * Gets the confidence level of the importer for a given file.
         * @param string $path The path to the file.
         * @param string|null $extension The file extension (optional).
         * @param string|null $mime The MIME type of the file (optional).
         * @param string $sample A sample of the file's content.
         * @return float A confidence score between 0.0 and 1.0.
         */
        public function getConfidence (string $path, ?string $extension, ?string $mime, string $sample) : float {
            # Strong extension-based confidence.
            if ($this->supportsExtension($extension ?? "")) return 1.0;
        
            # MIME type hint.
            if ($this->supportsMime($mime)) return 0.9;
        
            return 0.0;
        }

        /**
         * Executes a PHP file in an isolated scope and returns its output.
         * @param string $path The path to the file.
         * @param array $options Additional options for importing.
         * @return string The captured output buffer.
         */
        public function import (string $path, array $options = []) : string {
            $scope   = $options["scope"] ?? [];
            $require = $options["require"] ?? false;

            ob_start();

            (function () use ($path, $scope, $require) {
				return new class($scope ?? [], $path, $require) {
					function __construct () {
						extract(func_get_arg(0));
                        func_get_arg(2) ? require func_get_arg(1) : include func_get_arg(1);
					}
				};
			})();

            $output = ob_get_clean();

            return $this->postprocess($output, $options);
        }

        /**
         * Checks whether this importer can handle the given file.
         * @param string $path The path to the file.
         * @param string|null $extension The file extension (optional).
         * @param string|null $mime The MIME type of the file (optional).
         * @return bool Whether the importer supports the file.
         */
        public function supports (string $path, ?string $extension = null, ?string $mime = null) : bool {
            if ($extension !== null && $this->supportsExtension($extension)) {
                return true;
            }
            if ($mime !== null && $this->supportsMime($mime)) {
                return true;
            }
            return false;
        }

        /**
         * Checks whether this importer supports the given file extension.
         * @param string $extension The file extension.
         * @return bool Whether the extension is supported.
         */
        public function supportsExtension (string $extension) : bool {
            return strtolower($extension) === "php";
        }

        /**
         * Checks whether this importer supports the given MIME type.
         * @param string $mime The MIME type.
         * @return bool Whether the MIME type is supported.
         */
        public function supportsMime (string $mime) : bool {
            return $mime === "application/x-php";
        }
    }
?>