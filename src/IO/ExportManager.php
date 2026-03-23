<?php
    /**
     * Project Name:    Wingman Explorer - Export Manager
     * Created by:      Angel Politis
     * Creation Date:   Dec 19 2025
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2025-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */
    
    # Use the Explorer.IO namespace.
    namespace Wingman\Explorer\IO;

    # Import the following classes to the current scope.
    use Wingman\Explorer\Exceptions\UnsupportedExportTypeException;
    use Wingman\Explorer\Bridge\Corvus\Emitter;
    use Wingman\Explorer\Enums\Signal;
    use Wingman\Explorer\Interfaces\IO\ExporterInterface;
    use Wingman\Explorer\Interfaces\IO\ExporterNegotiationStrategyInterface;

    /**
     * Manages a registry of exporters that can serialise and write data to files.
     *
     * Exporters are selected via confidence-based negotiation: each registered
     * exporter is scored by the data type, file extension, and MIME type, and
     * the highest-scoring candidate is chosen. A custom
     * {@see ExporterNegotiationStrategyInterface} can override the selection
     * entirely. A fallback exporter handles the case where no candidate matches.
     *
     * @package Wingman\Explorer\IO
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class ExportManager {
        /**
         * The shared emitter bound to this export manager instance.
         * @var Emitter
         */
        protected Emitter $emitter;

        /**
         * The fallback exporter.
         * @var ExporterInterface|null
         */
        protected ?ExporterInterface $fallback = null;

        /**
         * The registered exporters.
         * @var ExporterInterface[]
         */
        protected array $exporters = [];

        /**
         * The negotiation strategy.
         * @var ExporterNegotiationStrategyInterface|null
         */
        protected ?ExporterNegotiationStrategyInterface $strategy = null;

        /**
         * Creates a new export manager and binds a shared emitter to this instance.
         */
        public function __construct () {
            $this->emitter = Emitter::for($this);
        }

        /**
         * Exports data to a file using the best matching exporter.
         * @param mixed $data The data to export.
         * @param string $path The path to the file.
         * @param array $options Additional options for exporting.
         * @return mixed The exported content.
         * @throws UnsupportedExportTypeException If no exporter is registered for the file.
         */
        public function export (mixed $data, string $path, array $options = []) : mixed {
            $extension = pathinfo($path, PATHINFO_EXTENSION) ?: null;

            $exporter = $this->getBestMatch($data, $extension);

            if ($exporter) {
                $result = $exporter->export($data, $path, $options);

                $this->emitter
                    ->with(path: $path, exporter: get_class($exporter))
                    ->emit(Signal::EXPORT_COMPLETED);

                return $result;
            }

            if ($this->fallback) {
                $this->emitter
                    ->with(path: $path)
                    ->emit(Signal::EXPORT_FALLBACK);

                return $this->fallback->export($data, $path, $options);
            }

            throw new UnsupportedExportTypeException("No exporter registered for file: {$path}");
        }

        /**
         * Gets a registered exporter by its class name.
         * @param string $class The class name of the exporter.
         * @return ExporterInterface|null The exporter, or `null` if not found.
         */
        public function get (string $class) : ?ExporterInterface {
            return $this->exporters[$class] ?? null;
        }

        /**
         * Gets all registered exporters.
         * @return ExporterInterface[] The registered exporters.
         */
        public function getAll () : array {
            return $this->exporters;
        }

        /**
         * Gets the best matching exporter for a given dataset and hints.
         * @param mixed $data The data to be exported.
         * @param string|null $extension The file extension (optional).
         * @param string|null $mime The MIME type (optional).
         * @return ExporterInterface|null The best matching exporter, or `null` if none found.
         */
        public function getBestMatch (mixed $data, ?string $extension = null, ?string $mime = null) : ?ExporterInterface {
            $best = null;
            $bestScore = 0.0;

            if ($this->strategy !== null) {
                return $this->strategy->select($this->exporters, '', $extension, $mime);
            }

            foreach ($this->exporters as $exporter) {
                if (!$exporter->supports($data, $extension, $mime)) {
                    continue;
                }

                $score = max(0.0, min(1.0, $exporter->getConfidence($data, $extension, $mime)));

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $exporter;
                }
            }

            return $best;
        }

        /**
         * Gets an exporter by file extension.
         * @param string $extension The file extension.
         * @return ExporterInterface|null The matching exporter, or `null` if none found.
         */
        public function getByType (string $extension) : ?ExporterInterface {
            return $this->getBestMatch(null, $extension, null);
        }

        /**
         * Gets an exporter by MIME type.
         * @param string $mime The MIME type.
         * @return ExporterInterface|null The matching exporter, or `null` if none found.
         */
        public function getByMime (string $mime) : ?ExporterInterface {
            return $this->getBestMatch(null, null, $mime);
        }

        /**
         * Gets the fallback exporter.
         * @return ExporterInterface|null The fallback exporter, or `null` if none is set.
         */
        public function getFallback () : ?ExporterInterface {
            return $this->fallback;
        }

        /**
         * Checks if an exporter is registered by its class name.
         * @param string $class The class name of the exporter.
         * @return bool Whether the exporter is registered.
         */
        public function has (string $class) : bool {
            return isset($this->exporters[$class]);
        }

        /**
         * Registers an exporter.
         * @param ExporterInterface $exporter The exporter to register.
         * @return static The export manager.
         */
        public function register (ExporterInterface $exporter) : static {
            $this->exporters[get_class($exporter)] = $exporter;
            return $this;
        }

        /**
         * Sets the fallback exporter.
         * @param ExporterInterface $exporter The fallback exporter.
         * @return static The export manager.
         */
        public function setFallback (ExporterInterface $exporter) : static {
            $this->fallback = $exporter;
            return $this;
        }

        /**
         * Sets the negotiation strategy of an export manager.
         * @param ExporterNegotiationStrategyInterface|callable $strategy The negotiation strategy or a callable.
         * @return static The export manager.
         */
        public function setNegotiationStrategy (ExporterNegotiationStrategyInterface|callable $strategy) : static {
            if (is_callable($strategy)) {
                $this->strategy = new class($strategy) implements ExporterNegotiationStrategyInterface {
                    protected $callable;

                    public function __construct (callable $callable) {
                        $this->callable = $callable;
                    }

                    public function select (array $exporters, string $path, ?string $extension, ?string $mime) : ?ExporterInterface {
                        return call_user_func($this->callable, $exporters, $path, $extension, $mime);
                    }
                };
            }
            else $this->strategy = $strategy;
            
            return $this;
        }

        /**
         * Unregisters an exporter by class name.
         * @param string $class The class name of the exporter to unregister.
         * @return static The export manager.
         */
        public function unregister (string $class) : static {
            unset($this->exporters[$class]);
            return $this;
        }
    }
?>