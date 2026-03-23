<?php
    /**
     * Project Name:    Wingman Explorer - FTP Adapter
     * Created by:      Angel Politis
     * Creation Date:   Dec 21 2025
     * Last Modified:   Mar 22 2026
     * 
     * Copyright (c) 2025-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */
    
    # Use the Explorer.Adapters namespace.
    namespace Wingman\Explorer\Adapters;

    # Import the following classes to the current scope.
    use Wingman\Explorer\Exceptions\FilesystemException;
    use Wingman\Explorer\Interfaces\Adapters\DirectoryFilesystemAdapterInterface;
    use Wingman\Explorer\Interfaces\Adapters\MovableFilesystemAdapterInterface;
    use Wingman\Explorer\Interfaces\Adapters\ReadableFilesystemAdapterInterface;
    use Wingman\Explorer\Interfaces\Adapters\WritableFilesystemAdapterInterface;

    /**
     * Represents an FTP filesystem adapter.
     * @package Wingman\Explorer\Adapters
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class FTPAdapter implements
        ReadableFilesystemAdapterInterface,
        WritableFilesystemAdapterInterface,
        MovableFilesystemAdapterInterface,
        DirectoryFilesystemAdapterInterface
    {
        /**
         * The active FTP connection handle.
         * @var \FTP\Connection|false|null
         */
        protected $connection;

        /**
         * The FTP server hostname or IP address.
         * @var string
         */
        protected string $host;

        /**
         * The FTP login username.
         * @var string
         */
        protected string $username;

        /**
         * The FTP login password.
         * @var string
         */
        protected string $password;

        /**
         * The FTP server port.
         * @var int
         */
        protected int $port;

        /**
         * Whether to use passive mode for FTP transfers.
         * @var bool
         */
        protected bool $passive;

        /**
         * Whether to use FTPS (FTP over SSL/TLS) instead of plain FTP.
         * @var bool
         */
        protected bool $ssl;

        /**
         * Creates a new FTP adapter and establishes the server connection.
         * @param string $host The FTP server hostname or IP address.
         * @param string $username The FTP login username.
         * @param string $password The FTP login password.
         * @param int $port The FTP server port.
         * @param bool $passive Whether to use passive mode for transfers.
         * @param bool $ssl Whether to use FTPS (FTP over SSL/TLS).
         * @throws FilesystemException If the connection or login fails.
         */
        public function __construct(
            string $host,
            string $username,
            string $password,
            int $port = 21,
            bool $passive = true,
            bool $ssl = false
        ) {
            $this->host = $host;
            $this->username = $username;
            $this->password = $password;
            $this->port = $port;
            $this->passive = $passive;
            $this->ssl = $ssl;

            $this->connect();
        }

        /**
         * Establishes an FTP connection.
         * @throws FilesystemException If the connection or login fails.
         */
        protected function connect () : void {
            $conn = $this->ssl
                ? @ftp_ssl_connect($this->host, $this->port)
                : @ftp_connect($this->host, $this->port);
            if (!$conn) {
                throw new FilesystemException("Failed to connect to FTP server {$this->host}:{$this->port}");
            }

            if (!@ftp_login($conn, $this->username, $this->password)) {
                throw new FilesystemException("FTP login failed for user {$this->username}");
            }

            ftp_pasv($conn, $this->passive);
            $this->connection = $conn;
        }

        /**
         * Recursively deletes a directory and all its contents.
         * @param string $dir The path of the directory to delete.
         * @return bool Whether the directory was deleted.
         */
        protected function deleteDirectoryRecursive (string $dir) : bool {
            $items = $this->list($dir);
            foreach ($items as $item) {
                $info = $this->getMetadata($item, ["type"]);
                if ($info["type"] === "dir") {
                    $this->deleteDirectoryRecursive($item);
                } else {
                    @ftp_delete($this->connection, $item);
                }
            }

            return @ftp_rmdir($this->connection, $dir);
        }

        /**
         * Copies a file from one remote path to another.
         * @param string $source The source path.
         * @param string $destination The destination path.
         * @throws FilesystemException If the read or write operation fails.
         */
        public function copy (string $source, string $destination) : void {
            $content = $this->read($source);
            $this->write($destination, $content);
        }

        /**
         * Creates a file at the given path with optional initial content.
         * @param string $path The path of the file to create.
         * @param string $content The initial content of the file.
         * @throws FilesystemException If the file cannot be written.
         */
        public function create (string $path, string $content = "") : void {
            $this->write($path, $content);
        }
        
        /**
         * Creates a directory at the given remote path.
         * @param string $path The path of the directory to create.
         * @param bool $recursive Whether to create all parent directories as needed.
         * @param int $permissions Unused; accepted for interface compatibility.
         * @throws FilesystemException If the directory cannot be created.
         * @return bool Whether the directory was successfully created.
         */
        public function createDirectory (string $path, bool $recursive = false, int $permissions = 0775) : bool {
            if (!$recursive) {
                if (!@ftp_mkdir($this->connection, $path)) {
                    throw new FilesystemException("Failed to create remote directory: $path");
                }
                return true;
            }

            $parts = explode('/', trim($path, '/'));
            $current = "";

            foreach ($parts as $part) {
                $current .= '/' . $part;
                if (!$this->exists($current)) {
                    if (!@ftp_mkdir($this->connection, $current)) {
                        throw new FilesystemException("Failed to create remote directory: $current");
                    }
                }
            }

            return true;
        }

        /**
         * Deletes a file or directory at the given remote path.
         * @param string $path The path of the resource to delete.
         * @return bool Whether the resource was successfully deleted.
         */
        public function delete (string $path) : bool {
            $info = $this->getMetadata($path, ["type"]);
            if ($info["type"] === "dir") {
                return $this->deleteDirectoryRecursive($path);
            }

            return @ftp_delete($this->connection, $path);
        }

        /**
         * Checks whether a resource exists at the given remote path.
         * @param string $path The path to check.
         * @return bool Whether the resource exists.
         */
        public function exists (string $path) : bool {
            $listing = @ftp_nlist($this->connection, dirname($path));
            if ($listing === false) return false;
            $names = array_map("basename", $listing);
            return in_array(basename($path), $names, true);
        }

        /**
         * Returns metadata about a remote resource.
         * @param string $path The path of the resource.
         * @param string[]|null $properties The specific properties to retrieve, or null for all defaults.
         * @return array<string, mixed> The requested metadata.
         */
        public function getMetadata (string $path, ?array $properties = null) : array {
            $properties ??= ["path", "name", "type", "size", "modified"];

            $info = [];
            $basename = basename($path);

            foreach ($properties as $prop) {
                switch ($prop) {
                    case "path":
                        $info["path"] = $path;
                        break;
                    case "name":
                        $info["name"] = $basename;
                        break;
                    case "type":
                        $raw = @ftp_raw($this->connection, "LIST " . $path);
                        $info["type"] = $raw && str_starts_with($raw[0], 'd') ? "dir" : "file";
                        break;
                    case "size":
                        $size = @ftp_size($this->connection, $path);
                        $info["size"] = $size !== -1 ? $size : null;
                        break;
                    case "modified":
                        $mtime = @ftp_mdtm($this->connection, $path);
                        $info["modified"] = $mtime !== -1 ? $mtime : null;
                        break;
                    default:
                        $info[$prop] = null;
                }
            }

            return $info;
        }

        /**
         * Lists the contents of a remote directory.
         * @param string $path The path of the directory to list.
         * @throws FilesystemException If the directory cannot be listed.
         * @return iterable<string> An iterable of child resource paths.
         */
        public function list (string $path) : iterable {
            $items = @ftp_nlist($this->connection, $path);
            if ($items === false) {
                throw new FilesystemException("Failed to list directory: $path");
            }

            foreach ($items as $item) {
                if ($item === '.' || $item === "..") continue;
                yield $item;
            }
        }

        /**
         * Moves a resource from one remote path to another using FTP rename.
         * @param string $source The source path.
         * @param string $destination The destination path.
         * @throws FilesystemException If the move operation fails.
         */
        public function move (string $source, string $destination) : void {
            if (!@ftp_rename($this->connection, $source, $destination)) {
                throw new FilesystemException("Failed to move $source to $destination");
            }
        }

        /**
         * Reads and returns the full contents of a remote file.
         * @param string $path The path of the file to read.
         * @throws FilesystemException If the file cannot be read.
         * @return string The file contents.
         */
        public function read (string $path) : string {
            $tmp = tmpfile();
            $meta = stream_get_meta_data($tmp);
            $tmpPath = $meta["uri"];

            if (!@ftp_get($this->connection, $tmpPath, $path, FTP_BINARY)) {
                fclose($tmp);
                throw new FilesystemException("Failed to read remote file: $path");
            }

            $content = file_get_contents($tmpPath);
            fclose($tmp);
            return $content;
        }

        /**
         * Renames a resource by delegating to {@see move()}.
         * @param string $source The current path.
         * @param string $destination The new path.
         * @throws FilesystemException If the underlying move fails.
         */
        public function rename (string $source, string $destination) : void {
            $this->move($source, $destination);
        }

        /**
         * Writes content to a remote file, creating it if it does not exist.
         * @param string $path The path of the file to write.
         * @param string $content The content to write.
         * @throws FilesystemException If the file cannot be written.
         */
        public function write (string $path, string $content) : void {
            $tmp = tmpfile();
            $meta = stream_get_meta_data($tmp);
            $tmpPath = $meta["uri"];
            if (file_put_contents($tmpPath, $content) === false) {
                fclose($tmp);
                throw new FilesystemException("Failed to write content to temporary buffer for: $path");
            }

            $dir = dirname($path);
            if (!$this->exists($dir)) {
                $this->createDirectory($dir, true);
            }

            if (!@ftp_put($this->connection, $path, $tmpPath, FTP_BINARY)) {
                fclose($tmp);
                throw new FilesystemException("Failed to write remote file: $path");
            }

            fclose($tmp);
        }
    }
?>