<?php
    /**
     * Project Name:    Wingman Explorer - Console Resource Resolution Trait
     * Created by:      Angel Politis
     * Creation Date:   Mar 23 2026
     * Last Modified:   Mar 23 2026
     *
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer.Bridge.Console.Traits namespace.
    namespace Wingman\Explorer\Bridge\Console\Traits;

    # Import the following classes to the current scope.
    use InvalidArgumentException;
    use RuntimeException;
    use Wingman\Explorer\Adapters\LocalAdapter;
    use Wingman\Explorer\Resources\LocalDirectory;
    use Wingman\Explorer\Resources\LocalFile;

    /**
     * Resolves the current Console bridge's temporary local-only adapter contract.
     *
     * The Explorer Console bridge currently exposes `--adapter=local` on several
     * commands so the command surface stays stable while broader adapter support
     * is still being designed. This trait centralises the validation and local
     * resource resolution used across those commands.
     *
     * @package Wingman\Explorer\Bridge\Console\Traits
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    trait ResolvesLocalExplorerResources {
        /**
         * Resolves a directory path to a local Explorer directory resource.
         * @param string $adapter The requested adapter.
         * @param string $path The directory path.
         * @throws InvalidArgumentException If the adapter value is unsupported.
         * @throws RuntimeException If the path does not resolve to a directory.
         * @return LocalDirectory The resolved directory resource.
         */
        protected function resolveExistingLocalDirectory (string $adapter, string $path) : LocalDirectory {
            $this->resolveLocalAdapter($adapter);

            if (!is_dir($path)) {
                throw new RuntimeException("The path '{$path}' does not exist or is not a directory.");
            }

            return LocalDirectory::at($path);
        }

        /**
         * Resolves a file path to a local Explorer file resource.
         * @param string $adapter The requested adapter.
         * @param string $path The file path.
         * @throws InvalidArgumentException If the adapter value is unsupported.
         * @throws RuntimeException If the path does not resolve to a file.
         * @return LocalFile The resolved file resource.
         */
        protected function resolveExistingLocalFile (string $adapter, string $path) : LocalFile {
            $this->resolveLocalAdapter($adapter);

            if (!is_file($path)) {
                throw new RuntimeException("The path '{$path}' does not exist or is not a file.");
            }

            return LocalFile::at($path);
        }

        /**
         * Resolves a filesystem path to either a local file or directory resource.
         * @param string $adapter The requested adapter.
         * @param string $path The resource path.
         * @throws InvalidArgumentException If the adapter value is unsupported.
         * @throws RuntimeException If the path does not resolve to a supported resource.
         * @return LocalFile|LocalDirectory The resolved resource.
         */
        protected function resolveExistingLocalResource (string $adapter, string $path) : LocalFile|LocalDirectory {
            $this->resolveLocalAdapter($adapter);

            if (is_file($path)) {
                return LocalFile::at($path);
            }

            if (is_dir($path)) {
                return LocalDirectory::at($path);
            }

            throw new RuntimeException("The path '{$path}' does not exist or is not a supported resource.");
        }

        /**
         * Resolves the current local-only adapter contract to an Explorer adapter instance.
         * @param string $adapter The requested adapter.
         * @throws InvalidArgumentException If the adapter value is unsupported.
         * @return LocalAdapter The resolved adapter instance.
         */
        protected function resolveLocalAdapter (string $adapter) : LocalAdapter {
            return match (strtolower(trim($adapter))) {
                "local" => new LocalAdapter(),
                default => throw new InvalidArgumentException("The --adapter option must currently be local.")
            };
        }
    }
?>