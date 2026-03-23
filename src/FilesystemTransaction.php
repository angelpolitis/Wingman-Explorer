<?php
    /**
     * Project Name:    Wingman Explorer - Filesystem Transaction
     * Created by:      Angel Politis
     * Creation Date:   Mar 20 2026
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer namespace.
    namespace Wingman\Explorer;

    # Import the following classes to the current scope.
    use Closure;
    use Throwable;
    use Wingman\Explorer\Exceptions\UnsupportedAdapterOperationException;
    use Wingman\Explorer\Bridge\Corvus\Emitter;
    use Wingman\Explorer\Enums\Signal;
    use Wingman\Explorer\Interfaces\Adapters\DirectoryFilesystemAdapterInterface;
    use Wingman\Explorer\Interfaces\Adapters\FilesystemAdapterInterface;
    use Wingman\Explorer\Interfaces\Adapters\MovableFilesystemAdapterInterface;
    use Wingman\Explorer\Interfaces\Adapters\ReadableFilesystemAdapterInterface;
    use Wingman\Explorer\Interfaces\Adapters\WritableFilesystemAdapterInterface;

    /**
     * Collects filesystem operations and executes them atomically, rolling back
     * completed steps if any operation fails.
     *
     * Operations are queued via the fluent builder methods and only applied when
     * {@see commit()} is called. On failure, {@see rollback()} is invoked
     * automatically in reverse order.
     *
     * Operations that require capabilities not present on the configured adapter
     * (e.g. copy/move on a read-only adapter) will throw a {@see UnsupportedAdapterOperationException}
     * at queue time, before any filesystem changes are made.
     *
     * @package Wingman\Explorer
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class FilesystemTransaction {
        /**
         * The filesystem adapter used to execute all operations.
         * @var FilesystemAdapterInterface
         */
        private FilesystemAdapterInterface $adapter;

        /**
         * The shared emitter bound to this transaction instance.
         * @var Emitter
         */
        private Emitter $emitter;

        /**
         * The ordered list of operations to execute on commit.
         * @var Closure[]
         */
        private array $operations = [];

        /**
         * The compensating actions used to undo completed operations on rollback.
         * @var Closure[]
         */
        private array $rollbacks = [];

        /**
         * Creates a new filesystem transaction backed by the given adapter.
         * @param FilesystemAdapterInterface $adapter The adapter to use for all operations.
         */
        public function __construct (FilesystemAdapterInterface $adapter) {
            $this->adapter = $adapter;
            $this->emitter = Emitter::for($this);
        }

        /**
         * Asserts that the adapter implements a given interface.
         * @param string $interface The fully-qualified interface name.
         * @param string $operation A human-readable operation name for the error message.
         * @throws UnsupportedAdapterOperationException If the adapter does not implement the interface.
         */
        private function requireAdapter (string $interface, string $operation) : void {
            if (!($this->adapter instanceof $interface)) {
                throw new UnsupportedAdapterOperationException("The configured adapter does not support '{$operation}' operations (must implement $interface)."
                );
            }
        }

        /**
         * Executes all queued operations in order.
         * If any operation throws, {@see rollback()} is called automatically and the original exception is rethrown.
         * @throws Throwable If any queued operation fails.
         */
        public function commit () : void {
            $executed = 0;

            try {
                foreach ($this->operations as $operation) {
                    $operation();
                    $executed++;
                }
            }
            catch (Throwable $e) {
                $this->rollback($executed);
                throw $e;
            }

            $this->operations = [];
            $this->rollbacks = [];

            $this->emitter
                ->with(operations: $executed)
                ->emit(Signal::TRANSACTION_COMMITTED);
        }

        /**
         * Queues a file copy operation.
         * On rollback the copied file is deleted from the destination, or moved
         * back to the source if the adapter does not support direct deletion.
         * @param string $source The path to copy from.
         * @param string $destination The path to copy to.
         * @throws UnsupportedAdapterOperationException If the adapter does not support copy operations.
         * @return static The transaction for chaining.
         */
        public function copyFile (string $source, string $destination) : static {
            $this->requireAdapter(MovableFilesystemAdapterInterface::class, "copy");

            /** @var MovableFilesystemAdapterInterface $adapter */
            $adapter = $this->adapter;

            $this->operations[] = static fn () => $adapter->copy($source, $destination);
            $this->rollbacks[] = static function () use ($adapter, $source, $destination) : void {
                if ($adapter instanceof WritableFilesystemAdapterInterface) {
                    $adapter->delete($destination);
                }
                else {
                    $adapter->move($destination, $source);
                }
            };

            return $this;
        }

        /**
         * Queues a directory creation operation.
         * On rollback the created directory is removed.
         * @param string $path The path of the directory to create.
         * @param bool $recursive Whether to create parent directories recursively.
         * @param int $permissions The permissions to apply to the directory.
         * @throws UnsupportedAdapterOperationException If the adapter does not support directory operations.
         * @return static The transaction for chaining.
         */
        public function createDirectory (string $path, bool $recursive = false, int $permissions = 0775) : static {
            $this->requireAdapter(DirectoryFilesystemAdapterInterface::class, "createDirectory");

            /** @var DirectoryFilesystemAdapterInterface&WritableFilesystemAdapterInterface $adapter */
            $adapter = $this->adapter;

            $this->operations[] = static fn () => $adapter->createDirectory($path, $recursive, $permissions);
            $this->rollbacks[] = static fn () => $adapter instanceof WritableFilesystemAdapterInterface
                ? $adapter->delete($path)
                : null;

            return $this;
        }

        /**
         * Queues a file creation operation.
         * On rollback the created file is deleted.
         * @param string $path The path at which the file will be created.
         * @param string $content The initial content of the file.
         * @throws UnsupportedAdapterOperationException If the adapter does not support write operations.
         * @return static The transaction for chaining.
         */
        public function createFile (string $path, string $content = "") : static {
            $this->requireAdapter(WritableFilesystemAdapterInterface::class, "create");

            /** @var WritableFilesystemAdapterInterface $adapter */
            $adapter = $this->adapter;

            $this->operations[] = static fn () => $adapter->create($path, $content);
            $this->rollbacks[] = static fn () => $adapter->delete($path);

            return $this;
        }

        /**
         * Queues a file deletion operation.
         * The original file content is read before queuing so it can be restored
         * on rollback. If the adapter is not readable, rollback will emit {@see Signal::ROLLBACK_RESTORE_IMPOSSIBLE}
         * rather than attempting a restore.
         * @param string $path The path of the file to delete.
         * @throws UnsupportedAdapterOperationException If the adapter does not support write operations.
         * @return static The transaction for chaining.
         */
        public function deleteFile (string $path) : static {
            $this->requireAdapter(WritableFilesystemAdapterInterface::class, "delete");

            /** @var WritableFilesystemAdapterInterface $adapter */
            $adapter = $this->adapter;

            $originalContent = null;

            if ($adapter instanceof ReadableFilesystemAdapterInterface && $adapter->exists($path)) {
                $originalContent = $adapter->read($path);
            }

            $this->operations[] = static fn () => $adapter->delete($path);

            if ($originalContent !== null) {
                $this->rollbacks[] = static fn () => $adapter->write($path, $originalContent);
            }
            else {
                $this->rollbacks[] = function () use ($path) : void {
                    $this->emitter
                        ->with(path: $path)
                        ->emit(Signal::ROLLBACK_RESTORE_IMPOSSIBLE);
                };
            }

            return $this;
        }

        /**
         * Queues a file or directory move operation.
         * On rollback the resource is moved back to its original location.
         * @param string $source The current path.
         * @param string $destination The destination path.
         * @throws UnsupportedAdapterOperationException If the adapter does not support move operations.
         * @return static The transaction for chaining.
         */
        public function moveFile (string $source, string $destination) : static {
            $this->requireAdapter(MovableFilesystemAdapterInterface::class, "move");

            /** @var MovableFilesystemAdapterInterface $adapter */
            $adapter = $this->adapter;

            $this->operations[] = static fn () => $adapter->move($source, $destination);
            $this->rollbacks[] = static fn () => $adapter->move($destination, $source);

            return $this;
        }

        /**
         * Reverses already-executed operations in reverse order.
         * Rollback errors are caught and dispatched as {@see Signal::ROLLBACK_STEP_FAILED} signals
         * rather than being silently suppressed, so that attached listeners (e.g. loggers) can
         * record each failure. All compensating actions are attempted regardless.
         * @param int|null $limit The number of operations already executed (rollback up to that index).
         */
        public function rollback (?int $limit = null) : void {
            $rollbackSlice = $limit !== null
                ? array_slice($this->rollbacks, 0, $limit)
                : $this->rollbacks;

            $count = count($rollbackSlice);

            foreach (array_reverse($rollbackSlice) as $index => $rollback) {
                try {
                    $rollback();
                }
                catch (Throwable $e) {
                    $this->emitter
                        ->with(step: $index, error: $e->getMessage())
                        ->emit(Signal::ROLLBACK_STEP_FAILED);
                }
            }

            $this->operations = [];
            $this->rollbacks = [];

            $this->emitter
                ->with(operations: $count)
                ->emit(Signal::TRANSACTION_ROLLED_BACK);
        }

        /**
         * Queues a file write operation.
         * The original file content is read before queuing so it can be restored
         * on rollback. If the file does not exist, rollback will delete it.
         * @param string $path The path to the file to write.
         * @param string $content The new content to write.
         * @throws UnsupportedAdapterOperationException If the adapter does not support write operations.
         * @return static The transaction for chaining.
         */
        public function writeFile (string $path, string $content) : static {
            $this->requireAdapter(WritableFilesystemAdapterInterface::class, "write");

            /** @var WritableFilesystemAdapterInterface $adapter */
            $adapter = $this->adapter;

            $originalContent = null;
            $existed = false;

            if ($adapter instanceof ReadableFilesystemAdapterInterface && $adapter->exists($path)) {
                $originalContent = $adapter->read($path);
                $existed = true;
            }

            $this->operations[] = static fn () => $adapter->write($path, $content);

            if ($existed) {
                $this->rollbacks[] = static fn () => $adapter->write($path, $originalContent);
            }
            else {
                $this->rollbacks[] = static fn () => $adapter->delete($path);
            }

            return $this;
        }
    }
?>