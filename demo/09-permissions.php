<?php
    /**
     * Project Name:    Wingman Explorer - Permissions Demo
     * Created by:      Angel Politis
     * Creation Date:   Mar 22 2026
     * Last Modified:   Mar 22 2026
     *
     * Copyright (c) 2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Import the following classes to the current scope.
    use Wingman\Explorer\Adapters\LocalAdapter;
    use Wingman\Explorer\Enums\Permission;
    use Wingman\Explorer\Objects\PermissionsMode;
    use Wingman\Explorer\Resources\LocalDirectory;
    use Wingman\Explorer\Resources\LocalFile;

    require_once __DIR__ . "/../autoload.php";

    # --------------------------------------------------------------------------------------
    # PERMISSIONS OVERVIEW
    #
    # Explorer ships two value objects for working with Unix-style permissions:
    #
    #   Permission (enum) — represents a single atomic permission bit:
    #     Permission::NONE    — 0
    #     Permission::EXECUTE — 1 (x)
    #     Permission::WRITE   — 2 (w)
    #     Permission::READ    — 4 (r)
    #
    #   PermissionsMode (class) — wraps a full 9-bit mode (owner/group/other).
    #     ->octal              — the raw PHP integer (e.g. 493 for 0755)
    #     ->has($bit, $scope)  — checks one bit for "owner", "group", or "other"
    #     ->toString()         — produces the shorthand octal string (e.g. "755")
    #     ::resolve($input)    — parses int, octal-digit string, or symbolic string
    #
    # WHERE THEY APPEAR IN EXPLORER
    #
    # These value objects are not yet returned directly by Explorer's methods —
    # the package currently surfaces raw integers and strings instead:
    #
    #   LocalFile::getMetadata()["permissions"]  — raw PHP int (mode & 0x1FF)
    #   LocalDirectory::getMetadata()["permissions"] — raw PHP int (mode & 0777)
    #   LocalAdapter::scan() result["permissions"]   — 4-digit octal string ("0755")
    #   LocalAdapter::chmod($path, int $permissions) — accepts raw PHP int
    #
    # PermissionsMode::resolve() bridges all three surface forms into one object,
    # and Permission::has() lets you inspect individual bits without bit-shifting.
    # --------------------------------------------------------------------------------------

    $tmpDir = sys_get_temp_dir() . "/wm_explorer_permissions_demo_" . uniqid();
    mkdir($tmpDir, 0775, true);

    echo "=== Permissions Demo ===" . PHP_EOL . PHP_EOL;


    # --------------------------------------------------------------------------------------
    # 1. CREATING A PermissionsMode
    #
    # resolve() accepts three forms so you can construct from whatever the
    # surrounding code already holds.
    # --------------------------------------------------------------------------------------

    echo "--- 1. Creating a PermissionsMode ---" . PHP_EOL;

    # From a PHP octal literal — the form used in PHP source code (0755 = 493).
    $fromLiteral = PermissionsMode::resolve(0755);
    echo "From PHP octal literal (0755):    octal={$fromLiteral->octal}, toString={$fromLiteral->toString()}" . PHP_EOL;

    # From an octal shorthand string — convenient for user-facing input.
    $fromShorthand = PermissionsMode::resolve("644");
    echo "From octal shorthand ('644'):     octal={$fromShorthand->octal}, toString={$fromShorthand->toString()}" . PHP_EOL;

    # From a 4-digit octal string — the exact form returned by LocalAdapter::scan().
    $fromScanString = PermissionsMode::resolve("0644");
    echo "From scan string ('0644'):        octal={$fromScanString->octal}, toString={$fromScanString->toString()}" . PHP_EOL;

    # From a symbolic string — the familiar "rwxr-xr-x" notation.
    $fromSymbolic = PermissionsMode::resolve("rwxr-xr-x");
    echo "From symbolic ('rwxr-xr-x'):      octal={$fromSymbolic->octal}, toString={$fromSymbolic->toString()}" . PHP_EOL;

    # Symbolic strings that lead with a file-type prefix (like ls -l output) work too.
    $fromLsOutput = PermissionsMode::resolve("-rw-r--r--");
    echo "From ls output ('-rw-r--r--'):    octal={$fromLsOutput->octal}, toString={$fromLsOutput->toString()}" . PHP_EOL;

    # Direct constructor — when you already have a raw integer from PHP's fileperms().
    $raw = fileperms($tmpDir) & 0x1FF;
    $direct = new PermissionsMode($raw);
    echo "Direct constructor from fileperms: octal={$direct->octal}, toString={$direct->toString()}" . PHP_EOL;

    echo PHP_EOL;


    # --------------------------------------------------------------------------------------
    # 2. QUERYING PERMISSION BITS
    #
    # has() accepts a Permission case and an optional scope ("owner" by default).
    # All nine Unix permission bits can be interrogated without manual bit-shifting.
    # --------------------------------------------------------------------------------------

    echo "--- 2. Querying permission bits ---" . PHP_EOL;

    $mode = PermissionsMode::resolve("rwxr-x---");

    $table = [
        ["owner", Permission::READ,    "r--"],
        ["owner", Permission::WRITE,   "-w-"],
        ["owner", Permission::EXECUTE, "--x"],
        ["group", Permission::READ,    "r--"],
        ["group", Permission::WRITE,   "-w-"],
        ["group", Permission::EXECUTE, "--x"],
        ["other", Permission::READ,    "r--"],
        ["other", Permission::WRITE,   "-w-"],
        ["other", Permission::EXECUTE, "--x"],
    ];

    echo "Mode: rwxr-x--- (resolved to " . $mode->toString() . ")" . PHP_EOL;

    foreach ($table as [$scope, $bit, $label]) {
        $result = $mode->has($bit, $scope) ? "yes" : "no";
        echo "  has({$bit->name}, '{$scope}'): {$result}" . PHP_EOL;
    }

    echo PHP_EOL;


    # --------------------------------------------------------------------------------------
    # 3. BRIDGING LocalFile::getMetadata()
    #
    # getMetadata()["permissions"] is a raw integer (stat["mode"] & 0x1FF).
    # Wrap it in PermissionsMode::resolve() to query it without manual bit-ops.
    # --------------------------------------------------------------------------------------

    echo "--- 3. Bridging LocalFile::getMetadata() ---" . PHP_EOL;

    $filePath = $tmpDir . "/example.txt";
    $file = LocalFile::at($filePath)->create();
    $file->write("hello")->save();

    $metadata = $file->getMetadata();
    $rawPermissions = $metadata["permissions"];

    echo "Raw permissions int from getMetadata(): {$rawPermissions}" . PHP_EOL;

    $fileMode = PermissionsMode::resolve($rawPermissions);

    echo "Resolved to octal string: " . $fileMode->toString() . PHP_EOL;
    echo "  Owner can read:  " . ($fileMode->has(Permission::READ, "owner")    ? "yes" : "no") . PHP_EOL;
    echo "  Owner can write: " . ($fileMode->has(Permission::WRITE, "owner")   ? "yes" : "no") . PHP_EOL;
    echo "  Group can read:  " . ($fileMode->has(Permission::READ, "group")    ? "yes" : "no") . PHP_EOL;
    echo "  Other can write: " . ($fileMode->has(Permission::WRITE, "other")   ? "yes" : "no") . PHP_EOL;

    echo PHP_EOL;


    # --------------------------------------------------------------------------------------
    # 4. BRIDGING LocalDirectory::getMetadata()
    #
    # Same as LocalFile — permissions come back as a raw int, wrapping with
    # PermissionsMode gives you the full querying API.
    # --------------------------------------------------------------------------------------

    echo "--- 4. Bridging LocalDirectory::getMetadata() ---" . PHP_EOL;

    $subDirPath = $tmpDir . "/subdir";
    $subDir = LocalDirectory::at($subDirPath)->create();

    $dirMeta = $subDir->getMetadata();
    $dirMode = PermissionsMode::resolve($dirMeta["permissions"]);

    echo "Directory mode: " . $dirMode->toString() . PHP_EOL;
    echo "  Owner can execute (traverse): " . ($dirMode->has(Permission::EXECUTE, "owner") ? "yes" : "no") . PHP_EOL;
    echo "  Group can read (list):        " . ($dirMode->has(Permission::READ, "group")    ? "yes" : "no") . PHP_EOL;

    echo PHP_EOL;


    # --------------------------------------------------------------------------------------
    # 5. CHANGING PERMISSIONS VIA LocalAdapter
    #
    # LocalAdapter::chmod() is the only Explorer surface that mutates permissions.
    # It speaks raw integers, so pass $mode->octal to keep everything consistent.
    # --------------------------------------------------------------------------------------

    echo "--- 5. Changing permissions via LocalAdapter ---" . PHP_EOL;

    $adapter = new LocalAdapter();

    $before = PermissionsMode::resolve($file->getMetadata()["permissions"]);
    echo "Before chmod: " . $before->toString() . PHP_EOL;

    # Apply a restrictive mode: owner read+write, group read, others nothing.
    $newMode = PermissionsMode::resolve("rw-r-----");
    $adapter->chmod($filePath, $newMode->octal);

    $after = PermissionsMode::resolve($file->getMetadata()["permissions"]);
    echo "After  chmod: " . $after->toString() . PHP_EOL;
    echo "  Owner can write: " . ($after->has(Permission::WRITE, "owner")   ? "yes" : "no") . PHP_EOL;
    echo "  Group can write: " . ($after->has(Permission::WRITE, "group")   ? "yes" : "no") . PHP_EOL;
    echo "  Other can read:  " . ($after->has(Permission::READ, "other")    ? "yes" : "no") . PHP_EOL;

    echo PHP_EOL;


    # --------------------------------------------------------------------------------------
    # 6. BRIDGING LocalAdapter::getMetadata() RESULTS
    #
    # LocalAdapter::getMetadata() returns "permissions" as a 4-digit octal
    # string like "0644".  PermissionsMode::resolve() accepts that form directly
    # since ctype_digit("0644") is true — the leading zero is treated as part of
    # the octal shorthand, so resolve("0644") is equivalent to resolve("644").
    # --------------------------------------------------------------------------------------

    echo "--- 6. Bridging LocalAdapter::getMetadata() results ---" . PHP_EOL;

    # Restore write permission so we can create a second file inside the directory.
    $adapter->chmod($filePath, PermissionsMode::resolve("rw-r--r--")->octal);

    $secondPath = $tmpDir . "/readme.md";
    LocalFile::at($secondPath)->create()->write("# README")->save();

    $paths = [$filePath, $secondPath];

    foreach ($paths as $path) {
        $meta = $adapter->getMetadata($path, ["name", "permissions"]);
        $scannedMode = PermissionsMode::resolve($meta["permissions"]);
        $ownerRead    = $scannedMode->has(Permission::READ,    "owner") ? "r" : "-";
        $ownerWrite   = $scannedMode->has(Permission::WRITE,   "owner") ? "w" : "-";
        $ownerExecute = $scannedMode->has(Permission::EXECUTE, "owner") ? "x" : "-";
        $groupRead    = $scannedMode->has(Permission::READ,    "group") ? "r" : "-";
        $groupWrite   = $scannedMode->has(Permission::WRITE,   "group") ? "w" : "-";
        $groupExecute = $scannedMode->has(Permission::EXECUTE, "group") ? "x" : "-";
        $otherRead    = $scannedMode->has(Permission::READ,    "other") ? "r" : "-";
        $otherWrite   = $scannedMode->has(Permission::WRITE,   "other") ? "w" : "-";
        $otherExecute = $scannedMode->has(Permission::EXECUTE, "other") ? "x" : "-";

        $symbolic = "{$ownerRead}{$ownerWrite}{$ownerExecute}{$groupRead}{$groupWrite}{$groupExecute}{$otherRead}{$otherWrite}{$otherExecute}";
        echo "  {$meta['name']}: {$meta['permissions']} → {$symbolic}" . PHP_EOL;
    }

    echo PHP_EOL;


    # --------------------------------------------------------------------------------------
    # CLEANUP
    # --------------------------------------------------------------------------------------

    $adapter->delete($tmpDir);

    echo "=== Done ===" . PHP_EOL;
?>