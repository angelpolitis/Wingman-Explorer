<?php
    /**
     * Project Name:    Wingman Explorer - File Utilities Tests
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
    use InvalidArgumentException;
    use RuntimeException;
    use Wingman\Argus\Attributes\Define;
    use Wingman\Argus\Attributes\Group;
    use Wingman\Argus\Test;
    use Wingman\Explorer\Enums\FileVariant;
    use Wingman\Explorer\FileUtils;

    /**
     * Tests for FileUtils — getMD5, getSHA1, getReadableSize, and getVariant.
     */
    class FileUtilsTest extends Test {
        /**
         * The temporary sandbox and a file with known content used for hash tests.
         * @var string
         */
        private string $sandboxPath;

        /**
         * Creates the sandbox and pre-populates a file with known content that produces a deterministic hash.
         */
        protected function setUp () : void {
            $this->sandboxPath = dirname(__DIR__) . "/temp/explorer_fileutils_test_" . uniqid();
            mkdir($this->sandboxPath, 0775, true);
        }

        /**
         * Removes the sandbox directory after each test.
         */
        protected function tearDown () : void {
            if (is_dir($this->sandboxPath)) {
                foreach (glob($this->sandboxPath . "/*") ?: [] as $file) {
                    @unlink($file);
                }
                @rmdir($this->sandboxPath);
            }
        }

        // ─── getMD5 ────────────────────────────────────────────────────────────

        #[Group("FileUtils")]
        #[Define(name: "GetMD5 — Returns Correct Hash for Known Content", description: "getMD5() returns the MD5 hex digest of the file, which must match the expected value for a known input.")]
        public function testGetMD5ReturnsCorrectHash () : void {
            $path = $this->sandboxPath . "/md5.txt";
            file_put_contents($path, "hello");

            $expected = md5_file($path);
            $actual = FileUtils::getMD5($path);

            $this->assertTrue($actual === $expected, "getMD5() must return the MD5 hex digest of the file.");
        }

        #[Group("FileUtils")]
        #[Define(name: "GetMD5 — Returns Raw Binary When Binary Flag Is True", description: "getMD5(\$path, true) returns a 16-byte raw binary string.")]
        public function testGetMD5BinaryModeReturnsSixteenBytes () : void {
            $path = $this->sandboxPath . "/md5bin.txt";
            file_put_contents($path, "binary hash test");

            $raw = FileUtils::getMD5($path, true);

            $this->assertTrue(strlen($raw) === 16, "getMD5() in binary mode must return a 16-byte string.");
        }

        #[Group("FileUtils")]
        #[Define(name: "GetMD5 — Throws RuntimeException for Nonexistent File", description: "getMD5() throws RuntimeException when the specified file does not exist.")]
        public function testGetMD5ThrowsForNonexistentFile () : void {
            $threw = false;

            try {
                FileUtils::getMD5($this->sandboxPath . "/missing.txt");
            }
            catch (RuntimeException) {
                $threw = true;
            }

            $this->assertTrue($threw, "getMD5() must throw RuntimeException for a nonexistent path.");
        }

        // ─── getSHA1 ───────────────────────────────────────────────────────────

        #[Group("FileUtils")]
        #[Define(name: "GetSHA1 — Returns Correct Hash for Known Content", description: "getSHA1() returns the SHA1 hex digest of the file, which must match the expected value for a known input.")]
        public function testGetSHA1ReturnsCorrectHash () : void {
            $path = $this->sandboxPath . "/sha1.txt";
            file_put_contents($path, "hello");

            $expected = sha1_file($path);
            $actual = FileUtils::getSHA1($path);

            $this->assertTrue($actual === $expected, "getSHA1() must return the SHA1 hex digest of the file.");
        }

        #[Group("FileUtils")]
        #[Define(name: "GetSHA1 — Returns Raw Binary When Binary Flag Is True", description: "getSHA1(\$path, true) returns a 20-byte raw binary string.")]
        public function testGetSHA1BinaryModeReturnsTwentyBytes () : void {
            $path = $this->sandboxPath . "/sha1bin.txt";
            file_put_contents($path, "binary sha1 test");

            $raw = FileUtils::getSHA1($path, true);

            $this->assertTrue(strlen($raw) === 20, "getSHA1() in binary mode must return a 20-byte string.");
        }

        #[Group("FileUtils")]
        #[Define(name: "GetSHA1 — Throws RuntimeException for Nonexistent File", description: "getSHA1() throws RuntimeException when the specified file does not exist.")]
        public function testGetSHA1ThrowsForNonexistentFile () : void {
            $threw = false;

            try {
                FileUtils::getSHA1($this->sandboxPath . "/missing.txt");
            }
            catch (RuntimeException) {
                $threw = true;
            }

            $this->assertTrue($threw, "getSHA1() must throw RuntimeException for a nonexistent path.");
        }

        // ─── getReadableSize ───────────────────────────────────────────────────

        #[Group("FileUtils")]
        #[Define(name: "GetReadableSize — Returns '0 B' for Zero Bytes", description: "getReadableSize(0) returns '0 B' as a special case.")]
        public function testGetReadableSizeZeroBytes () : void {
            $result = FileUtils::getReadableSize(0);

            $this->assertTrue($result === "0 B", "getReadableSize(0) must return '0 B'.");
        }

        #[Group("FileUtils")]
        #[Define(name: "GetReadableSize — Returns Byte Suffix for Values Below 1 KB", description: "getReadableSize() uses the 'B' suffix for values that are less than 1 KB.")]
        public function testGetReadableSizeReturnsByteSuffix () : void {
            $result = FileUtils::getReadableSize(512);

            $this->assertTrue(str_contains($result, "B"), "getReadableSize() must use the 'B' suffix for values below 1 KB.");
        }

        #[Group("FileUtils")]
        #[Define(name: "GetReadableSize — Returns KB Suffix for 1024 Bytes", description: "getReadableSize(1024) returns a string with the 'KB' suffix.")]
        public function testGetReadableSizeReturnsKilobytesSuffix () : void {
            $result = FileUtils::getReadableSize(1024);

            $this->assertTrue(str_contains($result, "KB"), "getReadableSize(1024) must use the 'KB' suffix.");
        }

        #[Group("FileUtils")]
        #[Define(name: "GetReadableSize — Returns MB Suffix for 1 MB", description: "getReadableSize() returns a string with the 'MB' suffix for one megabyte (1048576 bytes).")]
        public function testGetReadableSizeReturnsMegabytesSuffix () : void {
            $result = FileUtils::getReadableSize(1024 * 1024);

            $this->assertTrue(str_contains($result, "MB"), "getReadableSize(1048576) must use the 'MB' suffix.");
        }

        #[Group("FileUtils")]
        #[Define(name: "GetReadableSize — Throws InvalidArgumentException for Negative Bytes", description: "getReadableSize() throws InvalidArgumentException when the byte count is negative.")]
        public function testGetReadableSizeThrowsForNegativeBytes () : void {
            $threw = false;

            try {
                FileUtils::getReadableSize(-1);
            }
            catch (InvalidArgumentException) {
                $threw = true;
            }

            $this->assertTrue($threw, "getReadableSize() must throw InvalidArgumentException for a negative byte count.");
        }

        #[Group("FileUtils")]
        #[Define(name: "GetReadableSize — Throws InvalidArgumentException for Negative Decimals", description: "getReadableSize() throws InvalidArgumentException when the decimal places count is negative.")]
        public function testGetReadableSizeThrowsForNegativeDecimals () : void {
            $threw = false;

            try {
                FileUtils::getReadableSize(1024, -1);
            }
            catch (InvalidArgumentException) {
                $threw = true;
            }

            $this->assertTrue($threw, "getReadableSize() must throw InvalidArgumentException for negative decimal places.");
        }

        #[Group("FileUtils")]
        #[Define(name: "GetReadableSize — Respects Custom Decimal Places", description: "The number of decimal places in the output matches the value supplied as the second argument.")]
        public function testGetReadableSizeRespectsCustomDecimalPlaces () : void {
            $result = FileUtils::getReadableSize(1536, 1); # 1.5 KB

            $this->assertTrue(str_contains($result, "1.5"), "getReadableSize(1536, 1) must produce a value of 1.5 KB.");
        }

        // ─── getVariant ────────────────────────────────────────────────────────

        #[Group("FileUtils")]
        #[Define(name: "GetVariant — AsIs Returns the Original Name Unchanged", description: "FileVariant::AsIs returns the name exactly as provided.")]
        public function testGetVariantAsIsReturnsUnchangedName () : void {
            $result = FileUtils::getVariant("MyFile.TXT", FileVariant::AsIs);

            $this->assertTrue($result === "MyFile.TXT", "FileVariant::AsIs must return the name without any transformation.");
        }

        #[Group("FileUtils")]
        #[Define(name: "GetVariant — Uppercase Transforms Name to All Caps", description: "FileVariant::Uppercase transforms every character in the name to upper case.")]
        public function testGetVariantUppercaseTransformsAllCaps () : void {
            $result = FileUtils::getVariant("myfile.txt", FileVariant::Uppercase);

            $this->assertTrue($result === "MYFILE.TXT", "FileVariant::Uppercase must convert the entire name to upper case.");
        }

        #[Group("FileUtils")]
        #[Define(name: "GetVariant — Lowercase Transforms Name to All Lower Case", description: "FileVariant::Lowercase transforms every character in the name to lower case.")]
        public function testGetVariantLowercaseTransformsAllLower () : void {
            $result = FileUtils::getVariant("MYFILE.TXT", FileVariant::Lowercase);

            $this->assertTrue($result === "myfile.txt", "FileVariant::Lowercase must convert the entire name to lower case.");
        }

        #[Group("FileUtils")]
        #[Define(name: "GetVariant — Capitalised Uppercases the First Character Only", description: "FileVariant::Capitalised lowercases the whole name and then uppercases the first character.")]
        public function testGetVariantCapitalisedUppercasesFirstCharacter () : void {
            $result = FileUtils::getVariant("hELLO WORLD.txt", FileVariant::Capitalised);

            $this->assertTrue($result === "Hello world.txt", "FileVariant::Capitalised must produce a string with only the first character uppercased.");
        }

        #[Group("FileUtils")]
        #[Define(name: "GetVariant — WordsCapitalised Uppercases the First Character of Each Word", description: "FileVariant::WordsCapitalised lowercases the whole name and then title-cases every word.")]
        public function testGetVariantWordsCapitalisedUppercasesEachWord () : void {
            $result = FileUtils::getVariant("hello world.txt", FileVariant::WordsCapitalised);

            $this->assertTrue($result === "Hello World.txt", "FileVariant::WordsCapitalised must title-case every word in the name.");
        }
    }
?>