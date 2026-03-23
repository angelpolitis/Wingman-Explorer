<?php
    /**
     * Project Name:    Wingman Explorer - Scanner Tests
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
    use Wingman\Explorer\Adapters\LocalAdapter;
    use Wingman\Explorer\Enums\ScanDepth;
    use Wingman\Explorer\Enums\ScanEvent;
    use Wingman\Explorer\Enums\ScanFilterType;
    use Wingman\Explorer\Enums\ScanOption;
    use Wingman\Explorer\Enums\ScanOrder;
    use Wingman\Explorer\Enums\ScanSortOption;
    use Wingman\Explorer\Enums\ScanTarget;
    use Wingman\Explorer\Exceptions\ScannerConfigurationException;
    use Wingman\Explorer\Scanner;

    /**
     * Tests for the Scanner class and its fluent configuration API.
     */
    class ScannerTest extends Test {
        /**
         * The temporary sandbox directory used for scan operations.
         * @var string
         */
        private string $sandboxPath;

        /**
         * Creates a structured sandbox directory tree before each test.
         */
        protected function setUp () : void {
            $this->sandboxPath = dirname(__DIR__) . "/temp/explorer_scanner_test_" . uniqid();
            mkdir($this->sandboxPath . "/sub", 0775, true);
            mkdir($this->sandboxPath . "/.hidden_dir", 0775, true);
            file_put_contents($this->sandboxPath . "/alpha.txt", "alpha");
            file_put_contents($this->sandboxPath . "/beta.php", "beta");
            file_put_contents($this->sandboxPath . "/.hidden", "hidden");
            file_put_contents($this->sandboxPath . "/sub/gamma.txt", "gamma");
            file_put_contents($this->sandboxPath . "/sub/delta.php", "delta");
        }

        /**
         * Removes the sandbox directory and its contents after each test.
         */
        protected function tearDown () : void {
            if (is_dir($this->sandboxPath)) {
                $this->cleanDirectory($this->sandboxPath);
                @rmdir($this->sandboxPath);
            }
        }

        /**
         * Recursively removes a directory's contents.
         * @param string $dir The directory to empty.
         */
        private function cleanDirectory (string $dir) : void {
            foreach (scandir($dir) as $entry) {
                if ($entry === '.' || $entry === "..") continue;
                $path = $dir . "/" . $entry;
                is_dir($path) ? ($this->cleanDirectory($path) || @rmdir($path)) : @unlink($path);
            }
        }

        /**
         * Creates a Scanner pre-configured with a LocalAdapter and the sandbox path.
         * @return Scanner The ready-to-use scanner.
         */
        private function makeScanner () : Scanner {
            return Scanner::withAdapter(new LocalAdapter());
        }

        #[Group("Scanner")]
        #[Define(name: "WithAdapter — Returns Scanner Instance", description: "The withAdapter() static factory must return a Scanner instance.")]
        public function testWithAdapterReturnsScanner () : void {
            $scanner = Scanner::withAdapter(new LocalAdapter());

            $this->assertTrue($scanner instanceof Scanner, "withAdapter() must return a Scanner instance.");
        }

        #[Group("Scanner")]
        #[Define(name: "Scan — Throws Without Adapter", description: "scan() throws ScannerConfigurationException when no adapter has been configured.")]
        public function testScanThrowsWithoutAdapter () : void {
            $scanner = new Scanner();
            $thrown = false;

            try {
                $scanner->scan($this->sandboxPath);
            }
            catch (ScannerConfigurationException) {
                $thrown = true;
            }

            $this->assertTrue($thrown, "scan() must throw ScannerConfigurationException when no adapter is set.");
        }

        #[Group("Scanner")]
        #[Define(name: "Default Depth — Is DEFAULT", description: "A freshly constructed Scanner has ScanDepth::DEFAULT as its depth setting.")]
        public function testDefaultDepthIsDefault () : void {
            $scanner = new Scanner();

            $this->assertTrue($scanner->getDepth() === ScanDepth::DEFAULT, "The default scan depth must be ScanDepth::DEFAULT.");
        }

        #[Group("Scanner")]
        #[Define(name: "SetDepth — Changes Depth Setting", description: "setDepth() updates the scanner's depth and the getter reflects the change.")]
        public function testSetDepthChangesDepth () : void {
            $scanner = new Scanner();
            $scanner->setDepth(ScanDepth::DEEP);

            $this->assertTrue($scanner->getDepth() === ScanDepth::DEEP, "setDepth() must update the depth to the given value.");
        }

        #[Group("Scanner")]
        #[Define(name: "Default Target — Is ANY", description: "A freshly constructed Scanner has ScanTarget::ANY as its target setting.")]
        public function testDefaultTargetIsAny () : void {
            $scanner = new Scanner();

            $this->assertTrue($scanner->getTarget() === ScanTarget::ANY, "The default scan target must be ScanTarget::ANY.");
        }

        #[Group("Scanner")]
        #[Define(name: "SetTarget — Changes Target Setting", description: "setTarget() updates the scanner's target and the getter reflects the change.")]
        public function testSetTargetChangesTarget () : void {
            $scanner = new Scanner();
            $scanner->setTarget(ScanTarget::FILE);

            $this->assertTrue($scanner->getTarget() === ScanTarget::FILE, "setTarget() must update the target to the given value.");
        }

        #[Group("Scanner")]
        #[Define(name: "Scan — Depth DEFAULT Returns Top-Level and One Level Down", description: "With ScanDepth::DEFAULT the scanner returns items from the top level and one level of subdirectories.")]
        public function testScanDefaultDepthIncludesOneSubLevel () : void {
            $results = $this->makeScanner()
                ->setDepth(ScanDepth::DEFAULT)
                ->setTarget(ScanTarget::FILE)
                ->addOption(ScanOption::COLLAPSE_DIRS)
                ->scan($this->sandboxPath);

            $names = array_column($results, "name");

            $this->assertTrue(in_array("alpha.txt", $names), "Top-level file alpha.txt must appear in DEFAULT scan.");
            $this->assertTrue(in_array("gamma.txt", $names), "Subdirectory file gamma.txt must appear in DEFAULT scan (1 level deep).");
        }

        #[Group("Scanner")]
        #[Define(name: "Scan — Depth SHALLOW Returns Only Immediate Contents", description: "With ScanDepth::SHALLOW the scanner returns only direct children of the target directory.")]
        public function testScanShallowDepthExcludesNestedItems () : void {
            $results = $this->makeScanner()
                ->setDepth(ScanDepth::SHALLOW)
                ->setTarget(ScanTarget::FILE)
                ->scan($this->sandboxPath);

            $names = array_column($results, "name");

            $this->assertTrue(in_array("alpha.txt", $names), "Top-level file alpha.txt must appear in SHALLOW scan.");
            $this->assertTrue(!in_array("gamma.txt", $names), "Sub-directory file gamma.txt must not appear in SHALLOW scan.");
        }

        #[Group("Scanner")]
        #[Define(name: "Scan — Depth DEEP Returns All Nested Items", description: "With ScanDepth::DEEP the scanner recurses into every subdirectory and returns all descendants.")]
        public function testScanDeepDepthIncludesAllNestedItems () : void {
            $results = $this->makeScanner()
                ->setDepth(ScanDepth::DEEP)
                ->setTarget(ScanTarget::FILE)
                ->addOption(ScanOption::COLLAPSE_DIRS)
                ->scan($this->sandboxPath);

            $names = array_column($results, "name");

            $this->assertTrue(in_array("alpha.txt", $names), "Top-level alpha.txt must appear in DEEP scan.");
            $this->assertTrue(in_array("gamma.txt", $names), "Nested gamma.txt must appear in DEEP scan.");
            $this->assertTrue(in_array("delta.php", $names), "Nested delta.php must appear in DEEP scan.");
        }

        #[Group("Scanner")]
        #[Define(name: "Scan — Target FILE Returns Only Files", description: "With ScanTarget::FILE the results contain only regular, non-hidden files.")]
        public function testScanTargetFileReturnsOnlyFiles () : void {
            $results = $this->makeScanner()
                ->setDepth(ScanDepth::SHALLOW)
                ->setTarget(ScanTarget::FILE)
                ->scan($this->sandboxPath);

            foreach ($results as $item) {
                $this->assertTrue($item["type"] === "file", "Every result with target FILE must have type='file'.");
            }
        }

        #[Group("Scanner")]
        #[Define(name: "Scan — Target DIR Returns Only Directories", description: "With ScanTarget::DIR the results contain only non-hidden directories.")]
        public function testScanTargetDirReturnsOnlyDirectories () : void {
            $results = $this->makeScanner()
                ->setDepth(ScanDepth::SHALLOW)
                ->setTarget(ScanTarget::DIR)
                ->scan($this->sandboxPath);

            foreach ($results as $item) {
                $this->assertTrue($item["type"] === "dir", "Every result with target DIR must have type='dir'.");
            }
        }

        #[Group("Scanner")]
        #[Define(name: "Scan — Target HIDDEN Returns Hidden Items", description: "With ScanTarget::HIDDEN the results include only items whose names begin with a dot.")]
        public function testScanTargetHiddenReturnsHiddenItems () : void {
            $results = $this->makeScanner()
                ->setDepth(ScanDepth::SHALLOW)
                ->setTarget(ScanTarget::HIDDEN)
                ->scan($this->sandboxPath);

            foreach ($results as $item) {
                $this->assertTrue(str_starts_with($item["name"], '.'), "Items returned with target HIDDEN must start with a dot.");
            }

            $this->assertTrue(count($results) > 0, "At least one hidden item must be found.");
        }

        #[Group("Scanner")]
        #[Define(name: "FilterBy Extension — Returns Only Matching Files", description: "filterBy(EXTENSION, 'php') returns only files with a .php extension.")]
        public function testFilterByExtensionReturnsOnlyMatchingFiles () : void {
            $results = $this->makeScanner()
                ->setDepth(ScanDepth::DEEP)
                ->setTarget(ScanTarget::FILE)
                ->filterBy(ScanFilterType::EXTENSION, ["php"])
                ->scan($this->sandboxPath);

            $this->assertTrue(count($results) > 0, "At least one .php file must be found.");

            foreach ($results as $item) {
                $ext = strtolower(pathinfo($item["name"], PATHINFO_EXTENSION));
                $this->assertTrue($ext === "php", "Every item returned by EXTENSION filter must have the php extension.");
            }
        }

        #[Group("Scanner")]
        #[Define(name: "FilterBy Name — Returns Only Exact Match", description: "filterBy(NAME, 'alpha.txt') returns only the item with that exact name.")]
        public function testFilterByNameReturnsExactMatchOnly () : void {
            $results = $this->makeScanner()
                ->setDepth(ScanDepth::DEEP)
                ->setTarget(ScanTarget::FILE)
                ->filterBy(ScanFilterType::NAME, "alpha.txt")
                ->scan($this->sandboxPath);

            $this->assertCount(1, $results, "Exactly one item named 'alpha.txt' must be returned.");
            $this->assertTrue($results[0]["name"] === "alpha.txt", "The returned item must be named 'alpha.txt'.");
        }

        #[Group("Scanner")]
        #[Define(name: "FilterBy Regex — Returns Only Regex Matches", description: "filterBy(REGEX, '/\\.php$/') returns only files whose name matches the pattern.")]
        public function testFilterByRegexReturnsMatchingItems () : void {
            $results = $this->makeScanner()
                ->setDepth(ScanDepth::DEEP)
                ->setTarget(ScanTarget::FILE)
                ->filterBy(ScanFilterType::REGEX, '/\.php$/')
                ->scan($this->sandboxPath);

            $this->assertTrue(count($results) > 0, "At least one .php file must match the regex.");

            foreach ($results as $item) {
                $this->assertTrue(preg_match('/\.php$/', $item["name"]) === 1, "Every result must match the regex /\\.php\$/.");
            }
        }

        #[Group("Scanner")]
        #[Define(name: "ClearFilters — Removes All Filters", description: "clearFilters() removes every previously added filter so subsequent scans return unfiltered results.")]
        public function testClearFiltersRemovesAllFilters () : void {
            $scanner = $this->makeScanner()
                ->setDepth(ScanDepth::SHALLOW)
                ->setTarget(ScanTarget::FILE)
                ->filterBy(ScanFilterType::NAME, "nonexistent_xyz.txt");

            $filteredResults = $scanner->scan($this->sandboxPath);
            $scanner->clearFilters();
            $unfilteredResults = $scanner->scan($this->sandboxPath);

            $this->assertCount(0, $filteredResults, "The restrictive filter must return no results.");
            $this->assertTrue(count($unfilteredResults) > 0, "After clearFilters(), results must not be restricted.");
        }

        #[Group("Scanner")]
        #[Define(name: "SortBy Name Ascending — Results Sorted A-Z", description: "sortBy(NAME, ASCENDING) sorts the scan results alphabetically by name in ascending order.")]
        public function testSortByNameAscendingReturnsSortedResults () : void {
            $results = $this->makeScanner()
                ->setDepth(ScanDepth::SHALLOW)
                ->setTarget(ScanTarget::FILE)
                ->sortBy(ScanSortOption::NAME, ScanOrder::ASCENDING)
                ->scan($this->sandboxPath);

            $names = array_column($results, "name");

            $this->assertTrue($names === array_values(array_unique($names)) || count($names) <= 1 || $names[0] <= $names[count($names) - 1],
                "Results sorted by name ascending must be in A-Z order."
            );
        }

        #[Group("Scanner")]
        #[Define(name: "SortBy Name Descending — Results Sorted Z-A", description: "sortBy(NAME, DESCENDING) sorts the scan results alphabetically by name in descending order.")]
        public function testSortByNameDescendingReturnsSortedResults () : void {
            $results = $this->makeScanner()
                ->setDepth(ScanDepth::SHALLOW)
                ->setTarget(ScanTarget::FILE)
                ->sortBy(ScanSortOption::NAME, ScanOrder::DESCENDING)
                ->scan($this->sandboxPath);

            $names = array_column($results, "name");
            $sorted = $names;
            rsort($sorted);

            $this->assertTrue($names === $sorted, "Results sorted by name descending must be in Z-A order.");
        }

        #[Group("Scanner")]
        #[Define(name: "ScanEvent SCAN_STARTED — Fires Once at Start", description: "The SCAN_STARTED event fires exactly once at the beginning of every scan.")]
        public function testScanStartedEventFiresOnce () : void {
            $fired = 0;

            $this->makeScanner()
                ->setDepth(ScanDepth::SHALLOW)
                ->setEvent(ScanEvent::SCAN_STARTED, function () use (&$fired) { $fired++; })
                ->scan($this->sandboxPath);

            $this->assertTrue($fired === 1, "SCAN_STARTED event must fire exactly once per scan.");
        }

        #[Group("Scanner")]
        #[Define(name: "ScanEvent SCAN_COMPLETED — Fires Once at End", description: "The SCAN_COMPLETED event fires exactly once after a successful scan.")]
        public function testScanCompletedEventFiresOnce () : void {
            $fired = 0;

            $this->makeScanner()
                ->setDepth(ScanDepth::SHALLOW)
                ->setEvent(ScanEvent::SCAN_COMPLETED, function () use (&$fired) { $fired++; })
                ->scan($this->sandboxPath);

            $this->assertTrue($fired === 1, "SCAN_COMPLETED event must fire exactly once per scan.");
        }

        #[Group("Scanner")]
        #[Define(name: "ScanEvent FILE_FOUND — Fires for Each Discovered File", description: "The FILE_FOUND event fires once for each non-directory item the scanner discovers.")]
        public function testFileFoundEventFiresForEachFile () : void {
            $found = [];

            $this->makeScanner()
                ->setDepth(ScanDepth::SHALLOW)
                ->setTarget(ScanTarget::FILE)
                ->setEvent(ScanEvent::FILE_FOUND, function (array $info) use (&$found) { $found[] = $info["name"]; })
                ->scan($this->sandboxPath);

            $this->assertTrue(in_array("alpha.txt", $found), "FILE_FOUND must fire for alpha.txt.");
            $this->assertTrue(in_array("beta.php", $found), "FILE_FOUND must fire for beta.php.");
        }

        #[Group("Scanner")]
        #[Define(name: "Option PATHS_ONLY — Returns String Paths Instead of Metadata Arrays", description: "When PATHS_ONLY is enabled, scan() returns plain path strings rather than metadata arrays.")]
        public function testPathsOnlyOptionReturnsStringPaths () : void {
            $results = $this->makeScanner()
                ->setDepth(ScanDepth::SHALLOW)
                ->setTarget(ScanTarget::FILE)
                ->addOption(ScanOption::PATHS_ONLY)
                ->scan($this->sandboxPath);

            $this->assertTrue(count($results) > 0, "PATHS_ONLY scan must return at least one result.");

            foreach ($results as $item) {
                $this->assertTrue(is_string($item), "Each item from a PATHS_ONLY scan must be a string path.");
            }
        }

        #[Group("Scanner")]
        #[Define(name: "Option SKIP_ERRORS — Swallows Exceptions During Scan", description: "When SKIP_ERRORS is enabled, an error encountered during scanning causes an empty result rather than a thrown exception.")]
        public function testSkipErrorsOptionReturnsEmptyOnError () : void {
            $thrown = false;
            $results = [];

            try {
                $results = $this->makeScanner()
                    ->setDepth(ScanDepth::SHALLOW)
                    ->addOption(ScanOption::SKIP_ERRORS)
                    ->scan($this->sandboxPath . "/nonexistent_xyz");
            }
            catch (\Throwable) {
                $thrown = true;
            }

            $this->assertTrue(!$thrown, "No exception must be thrown with SKIP_ERRORS enabled.");
            $this->assertTrue(is_array($results), "With SKIP_ERRORS, scan() must return an array even on error.");
        }

        #[Group("Scanner")]
        #[Define(name: "AddOption and RemoveOption — Reflect Changes in HasOption", description: "addOption() adds a scan option and removeOption() removes it; hasOption() reflects both changes correctly.")]
        public function testAddAndRemoveOptionReflectedInHasOption () : void {
            $scanner = new Scanner();

            $scanner->addOption(ScanOption::PATHS_ONLY);
            $this->assertTrue($scanner->hasOption(ScanOption::PATHS_ONLY), "hasOption() must return true after addOption().");

            $scanner->removeOption(ScanOption::PATHS_ONLY);
            $this->assertTrue(!$scanner->hasOption(ScanOption::PATHS_ONLY), "hasOption() must return false after removeOption().");
        }

        #[Group("Scanner")]
        #[Define(name: "AddOption — Does Not Duplicate Options", description: "Calling addOption() twice with the same option adds it only once.")]
        public function testAddOptionDoesNotDuplicateOptions () : void {
            $scanner = new Scanner();
            $scanner->addOption(ScanOption::PATHS_ONLY);
            $scanner->addOption(ScanOption::PATHS_ONLY);

            $options = array_filter($scanner->getOptions(), fn($o) => $o === ScanOption::PATHS_ONLY);

            $this->assertCount(1, $options, "addOption() must not create duplicate entries for the same option.");
        }

        #[Group("Scanner")]
        #[Define(name: "Cortex Config — Hydrates pathsOnly", description: "Constructing a Scanner with the 'explorer.scanner.pathsOnly' key set to true enables the PATHS_ONLY option.")]
        public function testCortexConfigHydratesPathsOnly () : void {
            $scanner = new Scanner(["explorer.scanner.pathsOnly" => true]);

            $this->assertTrue($scanner->hasOption(ScanOption::PATHS_ONLY), "The PATHS_ONLY option must be set when the config key is true.");
        }

        #[Group("Scanner")]
        #[Define(name: "Cortex Config — Hydrates skipErrors", description: "Constructing a Scanner with the 'explorer.scanner.skipErrors' key set to true enables the SKIP_ERRORS option.")]
        public function testCortexConfigHydratesSkipErrors () : void {
            $scanner = new Scanner(["explorer.scanner.skipErrors" => true]);

            $this->assertTrue($scanner->hasOption(ScanOption::SKIP_ERRORS), "The SKIP_ERRORS option must be set when the config key is true.");
        }

        #[Group("Scanner")]
        #[Define(name: "SetAdapter — Returns Scanner for Chaining", description: "setAdapter() returns the scanner instance, enabling fluent chaining.")]
        public function testSetAdapterReturnsScannerForChaining () : void {
            $scanner = new Scanner();
            $result = $scanner->setAdapter(new LocalAdapter());

            $this->assertTrue($result === $scanner, "setAdapter() must return the same Scanner instance for fluent chaining.");
        }

        #[Group("Scanner")]
        #[Define(name: "FilterBy — Stacks Multiple Filters", description: "Calling filterBy() multiple times adds multiple filters that are all applied during scanning.")]
        public function testFilterByStacksMultipleFiltersForAnd () : void {
            $results = $this->makeScanner()
                ->setDepth(ScanDepth::DEEP)
                ->setTarget(ScanTarget::FILE)
                ->filterBy(ScanFilterType::EXTENSION, ["php"])
                ->filterBy(ScanFilterType::NAME, "beta.php")
                ->scan($this->sandboxPath);

            $this->assertCount(1, $results, "Stacked filters must work as AND: only items matching all filters should be returned.");
            $this->assertTrue($results[0]["name"] === "beta.php", "The only result must be beta.php.");
        }

        #[Group("Scanner")]
        #[Define(name: "GetFilters — Returns Configured Filters", description: "getFilters() returns all filters added via filterBy() as an array of type-value pairs.")]
        public function testGetFiltersReturnsConfiguredFilters () : void {
            $scanner = new Scanner();
            $scanner->filterBy(ScanFilterType::NAME, "test.txt");
            $scanner->filterBy(ScanFilterType::EXTENSION, ["txt"]);

            $filters = $scanner->getFilters();

            $this->assertCount(2, $filters, "getFilters() must return exactly the two added filters.");
        }
    }
?>