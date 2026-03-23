<?php
    /**
     * Project Name:    Wingman Explorer - Console Stat Command
     * Created by:      Angel Politis
     * Creation Date:   Mar 23 2026
     * Last Modified:   Mar 23 2026
     * 
     * Copyright (c) 2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Explorer.Bridge.Console.Commands namespace.
    namespace Wingman\Explorer\Bridge\Console\Commands;

    # Import the following classes to the current scope.
    use DateTimeInterface;
    use InvalidArgumentException;
    use RuntimeException;
    use Throwable;
    use Wingman\Console\Attributes\Argument;
    use Wingman\Console\Attributes\Command as Cmd;
    use Wingman\Console\Attributes\Flag;
    use Wingman\Console\Attributes\Option;
    use Wingman\Console\Command;
    use Wingman\Console\Style;
    use Wingman\Explorer\Bridge\Console\Traits\ResolvesLocalExplorerResources;
    use Wingman\Explorer\FileUtils;
    use Wingman\Explorer\Resources\LocalDirectory;
    use Wingman\Explorer\Resources\LocalFile;

    /**
     * Prints structured metadata for a file or directory resource.
     *
     * The current implementation accepts an explicit `--adapter=local` contract, defaulting to
     * `local` when omitted, so the command surface remains stable before broader adapter
     * resolution is introduced for the Console bridge.
     *
     * Hash output is currently supported for files only because Explorer exposes hash operations
     * on file resources rather than directories. Attempting to combine `--hashes` with a
     * directory target returns a validation error.
     *
     * @package Wingman\Explorer\Bridge\Console\Commands
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    #[Cmd(name: "explorer:stat", description: "Prints structured metadata for a file or directory.")]
    class StatCommand extends Command {
        use ResolvesLocalExplorerResources;

        /**
         * The filesystem adapter to use.
         * @var string
         */
        #[Option(name: "adapter", description: "The filesystem adapter to use; currently only local is supported")]
        protected string $adapter = "local";

        /**
         * The desired output format.
         * @var string
         */
        #[Option(name: "format", description: "The output format: table or json", alias: "F")]
        protected string $format = "table";

        /**
         * Whether file hashes should be included.
         * @var bool
         */
        #[Flag(name: "hashes", description: "Include MD5 and SHA1 hashes for files")]
        protected bool $hashes = false;

        /**
         * The resource path to inspect.
         * @var string
         */
        #[Argument(index: 0, description: "The file or directory path to inspect")]
        protected string $path;

        /**
         * Formats metadata keys for table output.
         * @param string $key The key to format.
         * @return string The formatted key label.
         */
        private function formatKey (string $key) : string {
            return ucwords(str_replace("_", " ", $key));
        }

        /**
         * Formats metadata values for human-readable output.
         * @param mixed $value The value to format.
         * @param string $key The key associated with the value.
         * @return string The formatted value.
         */
        private function formatValue (mixed $value, string $key) : string {
            if ($value instanceof DateTimeInterface) {
                return $value->format("Y-m-d H:i:s");
            }

            if ($key === "size" && is_int($value)) {
                return FileUtils::getReadableSize($value) . " ({$value} bytes)";
            }

            if ($key === "permissions" && is_int($value)) {
                return sprintf("0%o", $value);
            }

            if ($value === null) {
                return "-";
            }

            if (is_bool($value)) {
                return $value ? "true" : "false";
            }

            return (string) $value;
        }

        /**
         * Gets the effective output format after normalising case.
         * @throws InvalidArgumentException If the format is unsupported.
         * @return string The effective output format.
         */
        private function getEffectiveFormat () : string {
            $format = strtolower(trim($this->format));

            return match ($format) {
                "table", "json" => $format,
                default => throw new InvalidArgumentException("The --format option must be table or json.")
            };
        }

        /**
         * Normalises a directory resource into the payload exposed by this command.
         * @param LocalDirectory $directory The directory resource.
         * @throws InvalidArgumentException If file hashes are requested for a directory.
         * @return array<string, mixed> The normalised payload.
         */
        private function normaliseDirectory (LocalDirectory $directory) : array {
            if ($this->hashes) {
                throw new InvalidArgumentException("The --hashes flag can only be used with files.");
            }

            $metadata = $directory->getMetadata();

            return [
                "type" => "dir",
                "name" => $directory->getBaseName(),
                "path" => $directory->getPath(),
                "size" => (int) ($metadata["size"] ?? 0),
                "permissions" => $metadata["permissions"] ?? null,
                "owner" => $metadata["owner"] ?? null,
                "group" => $metadata["group"] ?? null,
                "accessed" => $metadata["last_accessed"] ?? null,
                "modified" => $metadata["last_modified"] ?? null,
                "created" => $metadata["created"] ?? null
            ];
        }

        /**
         * Normalises a file resource into the payload exposed by this command.
         * @param LocalFile $file The file resource.
         * @return array<string, mixed> The normalised payload.
         */
        private function normaliseFile (LocalFile $file) : array {
            $metadata = $file->getMetadata();
            $payload = [
                "type" => "file",
                "name" => $file->getBaseName(),
                "path" => $file->getPath(),
                "size" => (int) ($metadata["size"] ?? 0),
                "permissions" => $metadata["permissions"] ?? null,
                "owner" => $metadata["owner"] ?? null,
                "group" => $metadata["group"] ?? null,
                "accessed" => $metadata["accessed"] ?? null,
                "modified" => $metadata["modified"] ?? null,
                "created" => $metadata["created"] ?? null,
                "inode" => $metadata["inode"] ?? null,
                "device" => $metadata["device"] ?? null,
                "links" => $metadata["links"] ?? null
            ];

            if ($this->hashes) {
                $payload["md5"] = $file->getMD5();
                $payload["sha1"] = $file->getSHA1();
            }

            return $payload;
        }

        /**
         * Normalises the selected resource into the payload exposed by this command.
         * @param LocalFile|LocalDirectory $resource The resource to normalise.
         * @throws InvalidArgumentException If file hashes are requested for a directory.
         * @return array<string, mixed> The normalised payload.
         */
        private function normaliseResource (LocalFile|LocalDirectory $resource) : array {
            if ($resource instanceof LocalFile) {
                return $this->normaliseFile($resource);
            }

            return $this->normaliseDirectory($resource);
        }

        /**
         * Renders JSON output.
         * @param array<string, mixed> $payload The payload to render.
         * @throws InvalidArgumentException If the payload cannot be encoded as JSON.
         */
        private function renderJson (array $payload) : void {
            $normalised = array_map(function (mixed $value) : mixed {
                if ($value instanceof DateTimeInterface) {
                    return $value->format(DateTimeInterface::ATOM);
                }

                return $value;
            }, $payload);
            $json = json_encode($normalised, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            if ($json === false) {
                throw new InvalidArgumentException("Failed to encode stat results as JSON.");
            }

            echo $json . PHP_EOL;
        }

        /**
         * Renders table output.
         * @param array<string, mixed> $payload The payload to render.
         */
        private function renderTable (array $payload) : void {
            $rows = [];

            foreach ($payload as $key => $value) {
                $rows[] = [
                    $this->formatKey($key),
                    $this->formatValue($value, $key)
                ];
            }

            $this->console->style(fn (Style $style) => yield $style->renderTable(["Field", "Value"], $rows));
        }

        /**
         * Resolves the target resource for the command.
         * @throws InvalidArgumentException If the configured adapter is unsupported.
         * @throws RuntimeException If the target path does not resolve to a file or directory.
         * @return LocalFile|LocalDirectory The resolved resource.
         */
        private function resolveResource () : LocalFile|LocalDirectory {
            return $this->resolveExistingLocalResource($this->adapter, $this->path);
        }

        /**
         * Executes the stat command.
         * @return int The exit code of the command.
         */
        public function run () : int {
            try {
                $payload = $this->normaliseResource($this->resolveResource());

                match ($this->getEffectiveFormat()) {
                    "json" => $this->renderJson($payload),
                    default => $this->renderTable($payload)
                };

                return 0;
            }
            catch (InvalidArgumentException $e) {
                $this->console->error($e->getMessage());
                return 2;
            }
            catch (Throwable $e) {
                $this->console->error($e->getMessage());
                return 1;
            }
        }
    }
?>