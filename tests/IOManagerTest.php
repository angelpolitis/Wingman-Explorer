<?php
    /**
     * Project Name:    Wingman Explorer - IO Manager Tests
     * Created by:      Angel Politis
     * Creation Date:   Mar 21 2026
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer.Tests namespace.
    namespace Wingman\Explorer\Tests;

    # Import the following classes to the current scope.
    use Wingman\Argus\Attributes\Define;
    use Wingman\Argus\Attributes\Group;
    use Wingman\Argus\Test;
    use Wingman\Explorer\IO\Exporters\CsvExporter;
    use Wingman\Explorer\IO\Exporters\IniExporter;
    use Wingman\Explorer\IO\Exporters\JsonExporter;
    use Wingman\Explorer\IO\Exporters\JsonLinesExporter;
    use Wingman\Explorer\IO\ExportManager;
    use Wingman\Explorer\IO\ImportManager;
    use Wingman\Explorer\IO\Importers\CsvImporter;
    use Wingman\Explorer\IO\Importers\IniImporter;
    use Wingman\Explorer\IO\Importers\JsonImporter;
    use Wingman\Explorer\IO\Importers\JsonLinesImporter;
    use Wingman\Explorer\IO\IOManager;

    /**
     * Tests for IOManager — init, bind, unbind, and twin resolution.
     */
    class IOManagerTest extends Test {
        /**
         * Clears all twin bindings before each test to ensure a clean state.
         */
        protected function setUp () : void {
            IOManager::unbind();
        }

        // ─── Initialisation ────────────────────────────────────────────────────

        #[Group("IOManager")]
        #[Define(name: "Init — Creates Import and Export Managers", description: "After init(), getImportManager() and getExportManager() return valid manager instances.")]
        public function testInitCreatesManagers () : void {
            IOManager::init(false);

            $this->assertTrue(IOManager::getImportManager() instanceof ImportManager, "getImportManager() must return an ImportManager after init().");
            $this->assertTrue(IOManager::getExportManager() instanceof ExportManager, "getExportManager() must return an ExportManager after init().");
        }

        #[Group("IOManager")]
        #[Define(name: "Init — Lazy Initialisation on First Access", description: "Accessing either manager without calling init() explicitly triggers automatic initialisation.")]
        public function testLazyInitialisationOnAccessWithoutExplicitInit () : void {
            $importManager = IOManager::getImportManager();
            $exportManager = IOManager::getExportManager();

            $this->assertTrue($importManager instanceof ImportManager, "getImportManager() must return an ImportManager on lazy init.");
            $this->assertTrue($exportManager instanceof ExportManager, "getExportManager() must return an ExportManager on lazy init.");
        }

        // ─── Default Registrations ─────────────────────────────────────────────

        #[Group("IOManager")]
        #[Define(name: "RegisterDefaults — Installs Built-in Twin Bindings", description: "registerDefaults() registers all built-in importer/exporter twin pairs.")]
        public function testRegisterDefaultsInstallsTwinBindings () : void {
            IOManager::registerDefaults();

            $jsonExporter = IOManager::getTwinExporter(JsonImporter::class);
            $jsonImporter = IOManager::getTwinImporter(JsonExporter::class);

            $this->assertTrue($jsonExporter instanceof JsonExporter, "The twin exporter for JsonImporter must be a JsonExporter after registerDefaults().");
            $this->assertTrue($jsonImporter instanceof JsonImporter, "The twin importer for JsonExporter must be a JsonImporter after registerDefaults().");
        }

        #[Group("IOManager")]
        #[Define(name: "RegisterDefaults — CSV Twin Binding", description: "After registerDefaults(), the twin pair for CSV importers and exporters is resolvable.")]
        public function testRegisterDefaultsInstallsCsvTwinBinding () : void {
            IOManager::registerDefaults();

            $csvExporter = IOManager::getTwinExporter(CsvImporter::class);
            $csvImporter = IOManager::getTwinImporter(CsvExporter::class);

            $this->assertTrue($csvExporter instanceof CsvExporter, "The twin exporter for CsvImporter must be a CsvExporter.");
            $this->assertTrue($csvImporter instanceof CsvImporter, "The twin importer for CsvExporter must be a CsvImporter.");
        }

        #[Group("IOManager")]
        #[Define(name: "RegisterDefaults — JSON Lines Twin Binding", description: "After registerDefaults(), the twin pair for JSON Lines is resolvable.")]
        public function testRegisterDefaultsInstallsJsonLinesTwinBinding () : void {
            IOManager::registerDefaults();

            $exporter = IOManager::getTwinExporter(JsonLinesImporter::class);
            $importer = IOManager::getTwinImporter(JsonLinesExporter::class);

            $this->assertTrue($exporter instanceof JsonLinesExporter, "The twin exporter for JsonLinesImporter must be a JsonLinesExporter.");
            $this->assertTrue($importer instanceof JsonLinesImporter, "The twin importer for JsonLinesExporter must be a JsonLinesImporter.");
        }

        // ─── Bind / Unbind ─────────────────────────────────────────────────────

        #[Group("IOManager")]
        #[Define(name: "Bind — Creates Bidirectional Twin Mapping", description: "bind() stores both directions of the twin mapping so each side can resolve the other.")]
        public function testBindCreatesBidirectionalMapping () : void {
            IOManager::bind(JsonImporter::class, JsonExporter::class);

            $exporter = IOManager::getTwinExporter(JsonImporter::class);
            $importer = IOManager::getTwinImporter(JsonExporter::class);

            $this->assertTrue($exporter instanceof JsonExporter, "getTwinExporter() must return a JsonExporter after binding.");
            $this->assertTrue($importer instanceof JsonImporter, "getTwinImporter() must return a JsonImporter after binding.");
        }

        #[Group("IOManager")]
        #[Define(name: "Unbind — Removes Specific Pair by Importer", description: "unbind(importer) removes only that importer's twin mapping, leaving other bindings intact.")]
        public function testUnbindByImporterRemovesOnlyThatPair () : void {
            IOManager::bind(JsonImporter::class, JsonExporter::class);
            IOManager::bind(CsvImporter::class, CsvExporter::class);

            IOManager::unbind(JsonImporter::class);

            $jsonExporter = IOManager::getTwinExporter(JsonImporter::class);
            $csvExporter = IOManager::getTwinExporter(CsvImporter::class);

            $this->assertTrue($jsonExporter === null, "getTwinExporter() for JsonImporter must return null after unbinding.");
            $this->assertTrue($csvExporter instanceof CsvExporter, "The CsvImporter twin binding must not be affected by unbinding a different importer.");
        }

        #[Group("IOManager")]
        #[Define(name: "Unbind — Removes Specific Pair by Exporter", description: "unbind(null, exporter) removes only that exporter's twin mapping.")]
        public function testUnbindByExporterRemovesThatPair () : void {
            IOManager::bind(IniImporter::class, IniExporter::class);

            IOManager::unbind(null, IniExporter::class);

            $importer = IOManager::getTwinImporter(IniExporter::class);

            $this->assertTrue($importer === null, "getTwinImporter() for IniExporter must return null after unbinding by exporter.");
        }

        #[Group("IOManager")]
        #[Define(name: "Unbind — No Args Clears All Bindings", description: "unbind() with no arguments removes all twin mappings.")]
        public function testUnbindWithNoArgsRemovesAllBindings () : void {
            IOManager::bind(JsonImporter::class, JsonExporter::class);
            IOManager::bind(CsvImporter::class, CsvExporter::class);

            IOManager::unbind();

            $jsonExporter = IOManager::getTwinExporter(JsonImporter::class);
            $csvExporter = IOManager::getTwinExporter(CsvImporter::class);

            $this->assertTrue($jsonExporter === null, "All bindings must be removed after unbind() with no args.");
            $this->assertTrue($csvExporter === null, "All bindings must be removed after unbind() with no args.");
        }

        // ─── Twin Resolution ───────────────────────────────────────────────────

        #[Group("IOManager")]
        #[Define(name: "GetTwinExporter — Returns Null for Unbound Importer", description: "getTwinExporter() returns null when no twin has been registered for the given importer.")]
        public function testGetTwinExporterReturnsNullForUnboundImporter () : void {
            $result = IOManager::getTwinExporter(JsonImporter::class);

            $this->assertTrue($result === null, "getTwinExporter() must return null when no twin is registered for the importer.");
        }

        #[Group("IOManager")]
        #[Define(name: "GetTwinImporter — Returns Null for Unbound Exporter", description: "getTwinImporter() returns null when no twin has been registered for the given exporter.")]
        public function testGetTwinImporterReturnsNullForUnboundExporter () : void {
            $result = IOManager::getTwinImporter(JsonExporter::class);

            $this->assertTrue($result === null, "getTwinImporter() must return null when no twin is registered for the exporter.");
        }

        #[Group("IOManager")]
        #[Define(name: "Bind — Accepts Object Instances as Well as Class Names", description: "bind() accepts live instances of importers and exporters, not just class name strings.")]
        public function testBindAcceptsObjectInstances () : void {
            $importer = new JsonImporter();
            $exporter = new JsonExporter();

            IOManager::bind($importer, $exporter);

            $twin = IOManager::getTwinExporter(JsonImporter::class);

            $this->assertTrue($twin instanceof JsonExporter, "bind() must accept object instances and resolve correctly via class name.");
        }
    }
?>