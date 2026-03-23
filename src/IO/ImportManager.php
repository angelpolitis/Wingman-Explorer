<?php
    /**
     * Project Name:    Wingman Explorer - Import Manager
     * Created by:      Angel Politis
     * Creation Date:   Dec 18 2025
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2025-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */
    
    # Use the Explorer.IO namespace.
    namespace Wingman\Explorer\IO;

    # Import the following classes to the current scope.
    use Wingman\Explorer\Exceptions\UnsupportedImportTypeException;
    use Wingman\Explorer\Bridge\Corvus\Emitter;
    use Wingman\Explorer\Enums\Signal;
    use Wingman\Locator\Asserter;
    use Wingman\Explorer\Interfaces\IO\ImporterInterface;
    use Wingman\Explorer\Interfaces\IO\ImporterNegotiationStrategyInterface;

    /**
     * Manages a registry of importers that can parse and load content from files.
     *
     * Importers are selected via confidence-based negotiation: each registered
     * importer is scored against the file extension, MIME type, and a content
     * sample, and the highest-scoring candidate is chosen. A custom
     * {@see ImporterNegotiationStrategyInterface} can override the selection
     * entirely. A fallback importer handles the case where no candidate matches.
     *
     * Content samples are internally cached per path to avoid redundant reads
     * during a single negotiation pass.
     *
     * @package Wingman\Explorer\IO
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class ImportManager {
        /**
         * Cached samples for files.
         * @var array<string, string>
         */
        protected array $samples = [];

        /**
         * Cached confidence scores.
         * Format: [filePath => [importerClass => score]]
         * @var array<string, array<string, float>>
         */
        protected array $confidenceCache = [];

        /**
         * The shared emitter bound to this import manager instance.
         * @var Emitter
         */
        protected Emitter $emitter;

        /**
         * The fallback importer.
         * @var ImporterInterface|null
         */
        protected ?ImporterInterface $fallback = null;

        /**
         * The registered importers.
         * @var ImporterInterface[]
         */
        protected array $importers = [];

        /**
         * The negotiation strategy.
         * @var ImporterNegotiationStrategyInterface|null
         */
        protected ?ImporterNegotiationStrategyInterface $strategy = null;

        /**
         * Creates a new import manager and binds a shared emitter to this instance.
         */
        public function __construct () {
            $this->emitter = Emitter::for($this);
        }

        /**
         * Clears the import manager's cache.
         * @return static The import manager.
         */
        protected function clearCache () : static {
            $this->samples = [];
            $this->confidenceCache = [];
            return $this;
        }

        /**
         * Gets a sample of a file's content.
         * @param string $path The path to the file.
         * @param int $size The number of bytes to read for the sample.
         * @return string The file sample.
         */
        protected function getSample (string $path, int $size = 1024) : string {
            return $this->samples[$path] ??= file_get_contents($path, false, null, 0, $size) ?: '';
        }

        /**
         * Gets a registered importer by its class name.
         * @param string $class The class name of the importer.
         * @return ImporterInterface|null The importer, or `null` if not found.
         */
        public function get (string $class) : ?ImporterInterface {
            return $this->importers[$class] ?? null;
        }

        /**
         * Gets all registered importers.
         * @return ImporterInterface[] The registered importers.
         */
        public function getAll () : array {
            return $this->importers;
        }

        /**
         * Gets the best matching importer for a file.
         * @param string $path The path to the file.
         * @param string|null $extension The file extension (optional).
         * @param string|null $mime The MIME type (optional).
         * @return ImporterInterface|null The best matching importer, or `null` if none found.
         */
        public function getBestMatch (string $path, ?string $extension = null, ?string $mime = null) : ?ImporterInterface {
            if ($path !== '') {
                Asserter::requireFileAt($path);
            }

            if ($extension === null) {
                $extension = $path !== '' ? (pathinfo($path, PATHINFO_EXTENSION) ?: null) : null;
            }
            if ($mime === null && $path !== '') {
                static $finfo = null;
                $finfo ??= finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $path) ?: null;
            }

            $best = null;
            $bestScore = 0.0;
            $sample = $path !== '' ? $this->getSample($path) : '';

            if ($this->strategy !== null) {
                return $this->strategy->select($this->importers, $path, $extension, $mime, $sample);
            }

            foreach ($this->importers as $importer) {
                if (!$importer->supports($path, $extension, $mime)) continue;

                $class = get_class($importer);

                # Use cached confidence if available.
                $cacheKey = $path . '|' . ($extension ?? '') . '|' . ($mime ?? '');
                $score = $this->confidenceCache[$cacheKey][$class] ??= $importer->getConfidence($path, $extension, $mime, $sample);

                # Clamp the score to 0..1.
                $score = max(0.0, min(1.0, $score));

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $importer;
                }
            }

            return $best;
        }

        /**
         * Gets an importer by file extension.
         * @param string $extension The file extension.
         * @return ImporterInterface|null The matching importer, or `null` if none found.
         */
        public function getByType (string $extension) : ?ImporterInterface {
            return $this->getBestMatch("", $extension, null);
        }

        /**
         * Gets an importer by MIME type.
         * @param string $mime The MIME type.
         * @return ImporterInterface|null The matching importer, or `null` if none found.
         */
        public function getByMime (string $mime) : ?ImporterInterface {
            return $this->getBestMatch("", null, $mime);
        }

        /**
         * Gets the fallback importer.
         * @return ImporterInterface|null The fallback importer, or `null` if none is set.
         */
        public function getFallback () : ?ImporterInterface {
            return $this->fallback;
        }

        /**
         * Checks if an importer is registered by its class name.
         * @param string $class The class name of the importer.
         * @return bool Whether the importer is registered.
         */
        public function has (string $class) : bool {
            return isset($this->importers[$class]);
        }

        /**
         * Imports and parses content from a file.
         * @param string $path The path to the file.
         * @param array $options Additional options for importing.
         * @return mixed The imported content.
         * @throws UnsupportedImportTypeException If no importer is registered for the file.
         */
        public function import (string $path, array $options = []) : mixed {
            $bestMatch = $this->getBestMatch($path);

            if ($bestMatch) {
                $result = $bestMatch->import($path, $options);

                $this->emitter
                    ->with(path: $path, importer: get_class($bestMatch))
                    ->emit(Signal::IMPORT_COMPLETED);

                return $result;
            }

            if ($this->fallback === null) {
                throw new UnsupportedImportTypeException("No importer registered for file: {$path}");
            }

            $this->emitter
                ->with(path: $path)
                ->emit(Signal::IMPORT_FALLBACK);
            
            return $this->fallback->import($path, $options);
        }

        /**
         * Registers an importer.
         * @param ImporterInterface $importer The importer to register.
         * @return static The import manager.
         */
        public function register (ImporterInterface $importer) : static {
            $this->importers[get_class($importer)] = $importer;
            return $this;
        }

        /**
         * Sets the fallback importer.
         * @param ImporterInterface $importer The fallback importer.
         * @return static The import manager.
         */
        public function setFallback (ImporterInterface $importer) : static {
            $this->fallback = $importer;
            return $this;
        }

        /**
         * Sets the negotiation strategy of an import manager.
         * @param ImporterNegotiationStrategyInterface|callable $strategy The negotiation strategy or a callable.
         * @return static The import manager.
         */
        public function setNegotiationStrategy (ImporterNegotiationStrategyInterface|callable $strategy) : static {
            if (is_callable($strategy)) {
                $this->strategy = new class($strategy) implements ImporterNegotiationStrategyInterface {
                    protected $callable;

                    public function __construct (callable $callable) {
                        $this->callable = $callable;
                    }

                    public function select (array $importers, string $path, ?string $extension, ?string $mime, ?string $sample = null) : ?ImporterInterface {
                        return call_user_func($this->callable, $importers, $path, $extension, $mime, $sample);
                    }
                };
            }
            else $this->strategy = $strategy;
            
            return $this;
        }

        /**
         * Unregisters an importer by class name and clears the confidence cache.
         * @param string $class The class name of the importer to unregister.
         * @return static The import manager.
         */
        public function unregister (string $class) : static {
            unset($this->importers[$class]);
            $this->clearCache();
            return $this;
        }
    }
?>