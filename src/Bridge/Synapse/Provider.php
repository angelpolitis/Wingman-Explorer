<?php
    /**
     * Project Name:    Wingman Explorer - Synapse Provider
     * Created by:      Angel Politis
     * Creation Date:   Mar 21 2026
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer.Bridge.Synapse namespace.
    namespace Wingman\Explorer\Bridge\Synapse;

    # Import the following classes to the current scope.
    use Wingman\Explorer\IO\ExportManager;
    use Wingman\Explorer\IO\ImportManager;
    use Wingman\Explorer\Interfaces\Adapters\DirectoryFilesystemAdapterInterface;
    use Wingman\Explorer\Scanner;
    use Wingman\Synapse\Provider as BaseProvider;

    /**
     * Registers Explorer's core services with the Synapse DI container.
     *
     * Services bound:
     * - {@see ImportManager} — singleton, aliased to `"importer"`
     * - {@see ExportManager} — singleton, aliased to `"exporter"`
     * - {@see Scanner} — transient (fresh instance per resolution), aliased to `"scanner"`
     *
     * When a {@see DirectoryFilesystemAdapterInterface} binding is already present in the
     * container, it is automatically forwarded to each freshly resolved {@see Scanner}.
     * If no adapter is registered the scanner is returned without one and will throw a
     * {@see ScannerConfigurationException} at scan time.
     *
     * {@see FilesystemTransaction} is intentionally not registered here because it
     * requires a concrete adapter at construction time with no sensible application-level default.
     *
     * @package Wingman\Explorer\Bridge\Synapse
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class Provider extends BaseProvider {
        /**
         * Registers the Explorer services with the container.
         */
        public function register () : void {
            $this->container->bindSingleton(ImportManager::class, fn () => new ImportManager());
            $this->container->alias(ImportManager::class, "importer");

            $this->container->bindSingleton(ExportManager::class, fn () => new ExportManager());
            $this->container->alias(ExportManager::class, "exporter");

            $this->container->bindTransient(Scanner::class, function ($container) {
                $scanner = new Scanner();

                if ($container->has(DirectoryFilesystemAdapterInterface::class)) {
                    $scanner->setAdapter($container->make(DirectoryFilesystemAdapterInterface::class));
                }

                return $scanner;
            });

            $this->container->alias(Scanner::class, "scanner");
        }
    }
?>