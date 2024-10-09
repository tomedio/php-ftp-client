<?php

namespace FtpClient;

use Countable;
use Exception;
use FTP\Connection;
use RuntimeException;

/**
 * The FTP and SSL-FTP client for PHP.
 *
 * @method bool alloc(int $filesize, string &$result = null) Allocates space for a file to be uploaded
 * @method bool append(string $remote_file, Connection $handle, int $mode) Append the contents of a file to another file on the FTP server
 * @method bool cdup() Changes to the parent directory
 * @method bool chdir(string $directory) Changes the current directory on a FTP server
 * @method int chmod(int $mode, string $filename) Set permissions on a file via FTP
 * @method bool delete(string $path) Deletes a file on the FTP server
 * @method bool exec(string $command) Requests execution of a command on the FTP server
 * @method bool fget(Connection|resource $handle, string $remote_file, int $mode, int $resumepos = 0) Downloads a file from the FTP server and saves to an open file
 * @method bool fput(string $remote_file, Connection|resource $handle, int $mode, int $startpos = 0) Uploads from an open file to the FTP server
 * @method bool get(string $local_file, string $remote_file, int $mode, int $resumepos = 0) Downloads a file from the FTP server
 * @method mixed get_option(int $option) Retrieves various runtime behaviours of the current FTP stream
 * @method int mdtm(string $remote_file) Returns the last modified time of the given file
 * @method array mlsd(string $remote_dir) Returns a list of files in the given directory
 * @method int nb_continue() Continues retrieving/sending a file (non-blocking)
 * @method int nb_fget(Connection|resource $handle, string $remote_file, int $mode, int $resumepos = 0) Retrieves a file from the FTP server and writes it to an open file (non-blocking)
 * @method int nb_fput(string $remote_file, Connection|resource $handle, int $mode, int $startpos = 0) Stores a file from an open file to the FTP server (non-blocking)
 * @method int nb_get(string $local_file, string $remote_file, int $mode, int $resumepos = 0) Retrieves a file from the FTP server and writes it to a local file (non-blocking)
 * @method int nb_put(string $remote_file, string $local_file, int $mode, int $startpos = 0) Stores a file on the FTP server (non-blocking)
 * @method bool pasv(bool $pasv) Turns passive mode on or off
 * @method bool put(string $remote_file, string $local_file, int $mode, int $startpos = 0) Uploads a file to the FTP server
 * @method string|false pwd() Returns the current directory name
 * @method array raw(string $command) Sends an arbitrary command to an FTP server
 * @method bool rename(string $oldname, string $newname) Renames a file or a directory on the FTP server
 * @method bool set_option(int $option, mixed $value) Set miscellaneous runtime FTP options
 * @method bool site(string $command) Sends a SITE command to the server
 * @method int size(string $remote_file) Returns the size of the given file
 * @method string systype() Returns the system type identifier of the remote FTP server
 */
class FtpClient implements Countable
{
    private FtpWrapper $ftp;

    /**
     * @throws FtpException  If FTP extension is not loaded.
     */
    public function __construct(?Connection $connection = null)
    {
        if (!extension_loaded('ftp')) {
            throw new FtpException('FTP extension is not loaded!');
        }

        $this->ftp = new FtpWrapper($connection);
    }

    /**
     * Close the connection when the object is destroyed.
     */
    public function __destruct()
    {
        $this->ftp->close();
    }

    /**
     * Call an internal method or an FTP method handled by the wrapper.
     *
     * Wrap the FTP PHP functions to call as method of FtpClient object.
     * The connection is automatically passed to the FTP PHP functions.
     *
     * @param mixed[] $arguments
     * @throws FtpException When the function is not valid
     */
    public function __call(string $method, array $arguments)
    {
        return $this->ftp->$method(...$arguments);
    }

    /**
     * Get the help information of the remote FTP server.
     */
    public function help(): array
    {
        return $this->ftp->raw('help');
    }

    /**
     * Open an FTP connection.
     *
     * @param string $host
     * @param bool $ssl
     * @param int $port
     * @param int $timeout
     *
     * @return FtpClient
     * @throws FtpException If unable to connect
     */
    public function connect(string $host, bool $ssl = false, int $port = 21, int $timeout = 120): self
    {
        if ($ssl) {
            $connected = $this->ftp->ssl_connect($host, $port, $timeout);
        } else {
            $connected = $this->ftp->connect($host, $port, $timeout);
        }

        if (!$connected) {
            throw new FtpException('Unable to connect');
        }

        return $this;
    }

    /**
     * Closes the current FTP connection.
     */
    public function close(): bool
    {
        return $this->ftp->close();
    }

    /**
     * Logs in to an FTP connection.
     *
     * @param string $username
     * @param string $password
     *
     * @return FtpClient
     * @throws FtpException If the login is incorrect
     */
    public function login(string $username = 'anonymous', string $password = ''): self
    {
        $result = $this->ftp->login($username, $password);

        if ($result === false) {
            throw new FtpException('Login incorrect');
        }

        return $this;
    }

    /**
     * Returns the last modified time of the given file. Returns -1 on error.
     */
    public function modifiedTime(string $remoteFile, ?string $format = null): int
    {
        $time = $this->ftp->mdtm($remoteFile);

        if ($time !== -1 && $format !== null) {
            return date($format, $time);
        }

        return $time;
    }

    /**
     * Changes to the parent directory.
     *
     * @throws FtpException If unable to get parent folder
     */
    public function up(): self
    {
        $result = $this->ftp->cdup();

        if ($result === false) {
            throw new FtpException('Unable to get parent folder');
        }

        return $this;
    }

    /**
     * Returns a list of files in the given directory.
     *
     * @param string $directory The directory, by default is "." the current directory
     * @param bool $recursive
     * @param callable|string $filter A callable to filter the result, by default is asort() PHP function.
     *
     * @return string[]
     * @throws FtpException If unable to list the directory
     */
    public function nlist(string $directory = '.', bool $recursive = false, callable|string $filter = 'sort'): array
    {
        if (!$this->isDir($directory)) {
            throw new FtpException('"' . $directory . '" is not a directory');
        }

        $files = $this->ftp->nlist($directory);

        if ($files === false) {
            throw new FtpException('Unable to list directory');
        }

        $result = [];
        $dir_len = strlen($directory);

        // if it's the current
        if (false !== ($kdot = array_search('.', $files, true))) {
            unset($files[$kdot]);
        }

        // if it's the parent
        if (false !== ($kdot = array_search('..', $files, true))) {
            unset($files[$kdot]);
        }

        if (!$recursive) {
            $result = $files;

            // working with the reference (behavior of several PHP sorting functions)
            $filter($result);

            return $result;
        }

        // utils for recursion
        $flatten = static function (array $arr) use (&$flatten) {
            $flat = [];

            foreach ($arr as $v) {
                if (is_array($v)) {
                    /** @noinspection SlowArrayOperationsInLoopInspection */
                    $flat = array_merge($flat, $flatten($v));
                } else {
                    $flat[] = $v;
                }
            }

            return $flat;
        };

        foreach ($files as $file) {
            $file = $directory . '/' . $file;

            // if contains the root path (behavior of the recursive)
            if (0 === strpos($file, $directory, $dir_len)) {
                $file = substr($file, $dir_len);
            }

            $result[] = $file;
            if ($this->isDir($file)) {
                $items = $flatten($this->nlist($file, true, $filter));

                foreach ($items as $item) {
                    $result[] = $item;
                }
            }
        }

        $result = array_unique($result);
        $filter($result);

        return $result;
    }

    /**
     * Creates a new directory.
     *
     * @throws FtpException If unable to create the directory
     */
    public function mkdir(string $directory, bool $recursive = false): bool
    {
        if (!$recursive || $this->isDir($directory)) {
            return $this->ftp->mkdir($directory);
        }

        $result = false;
        $pwd = $this->ftp->pwd();
        $parts = explode('/', $directory);

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            if (!@$this->ftp->chdir($part)) {
                $result = $this->ftp->mkdir($part);
                $this->ftp->chdir($part);
            }
        }

        $this->ftp->chdir($pwd);

        return $result;
    }

    /**
     * Remove a directory.
     *
     * @param bool $recursive Forces deletion if the directory is not empty
     * @throws FtpException If unable to list the directory to remove
     */
    public function rmdir(string $directory, bool $recursive = true): bool
    {
        if ($recursive) {
            $files = $this->nlist($directory, false, 'rsort');

            // remove children
            foreach ($files as $file) {
                $this->remove($this->joinPaths($directory, $file), true);
            }
        }

        // remove the directory
        return $this->ftp->rmdir($directory);
    }

    /**
     * Make the directory to be empty.
     *
     * @throws FtpException If unable to list the directory to clean
     */
    public function cleanDir(string $directory): bool
    {
        if (!$files = $this->nlist($directory)) {
            return $this->isEmpty($directory);
        }

        // remove children
        foreach ($files as $file) {
            $this->remove($this->joinPaths($directory, $file), true);
        }

        return $this->isEmpty($directory);
    }

    /**
     * Remove a file or a directory.
     *
     * @param string $path The path of the file or directory to remove
     * @param bool $recursive Is effective only if $path is a directory
     */
    public function remove(string $path, bool $recursive = false): bool
    {
        if ($path === '.' || $path === '..') {
            return false;
        }

        try {
            if (@$this->ftp->delete($path)
                || ($this->isDir($path)
                    && $this->rmdir($path, $recursive))) {
                return true;
            }

            // in special cases delete operation can fail
            $newPath = preg_replace('/[^A-Za-z0-9\/]/', '', $path);
            if ($this->rename($path, $newPath)) {
                if (@$this->ftp->delete($newPath)
                    || ($this->isDir($newPath)
                        && $this->rmdir($newPath, $recursive))) {
                    return true;
                }
            }

            return false;
        } catch (Exception) {
            return false;
        }
    }

    /**
     * Check if a directory exist.
     *
     * @throws FtpException If unable to resolve the current directory
     */
    public function isDir(string $directory): bool
    {
        $pwd = $this->ftp->pwd();

        if ($pwd === false) {
            throw new FtpException('Unable to resolve the current directory');
        }

        if (@$this->ftp->chdir($directory)) {
            $this->ftp->chdir($pwd);
            return true;
        }

        $this->ftp->chdir($pwd);

        return false;
    }

    /**
     * Check if a directory is empty.
     *
     * @throws FtpException If unable to list the directory
     */
    public function isEmpty(string $directory): bool
    {
        return $this->countItems($directory, null, false) === 0;
    }

    /**
     * Scan a directory and returns the details of each item.
     *
     * @return array<string, array<string, string>>
 *
     * @throws FtpException If unable to list the directory
     */
    public function scanDir(string $directory = '.', bool $recursive = false): array
    {
        return $this->parseRawList($this->rawlist($directory, $recursive));
    }

    /**
     * Returns the total size of the given directory in bytes.
     *
     * @param string $directory The directory, by default is the current directory.
     * @param bool $recursive true by default
     * @return int    The size in bytes.
     *
     * @throws FtpException If unable to list the directory
     */
    public function dirSize(string $directory = '.', bool $recursive = true): int
    {
        $items = $this->scanDir($directory, $recursive);
        $size = 0;

        foreach ($items as $item) {
            $size += (int)$item['size'];
        }

        return $size;
    }

    /**
     * Count the items (file, directory, link, unknown).
     *
     * @param string $directory The directory, by default is the current directory.
     * @param string|null $type The type of item to count (file, directory, link, unknown)
     * @param bool $recursive true by default
     *
     * @throws FtpException If unable to list the directory
     */
    public function countItems(string $directory = '.', ?string $type = null, bool $recursive = true): int
    {
        $items = (null === $type ? $this->nlist($directory, $recursive)
            : $this->scanDir($directory, $recursive));

        $count = 0;
        foreach ($items as $item) {
            if (null === $type || $item['type'] === $type) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Count the items (file, directory, link, unknown).
     *
     * @throws FtpException If unable to list the directory
     */
    public function count(): int
    {
        return $this->countItems();
    }

    /**
     * Downloads a file from the FTP server into a string
     */
    public function getContent($remoteFile, int $mode = FTP_BINARY, int $resumepos = 0): ?string
    {
        $handle = fopen('php://temp', 'rb+');

        if ($this->ftp->fget($handle, $remoteFile, $mode, $resumepos)) {
            rewind($handle);
            return stream_get_contents($handle);
        }

        return null;
    }

    /**
     * Uploads a file to the server from a string.
     *
     * @throws FtpException When the transfer fails
     */
    public function putFromString(string $remoteFile, string $content): self
    {
        $handle = fopen('php://temp', 'wb');

        fwrite($handle, $content);
        rewind($handle);

        if ($this->ftp->fput($remoteFile, $handle, FTP_BINARY)) {
            return $this;
        }

        throw new FtpException('Unable to put the file "' . $remoteFile . '"');
    }

    /**
     * Uploads a file to the server.
     *
     * @throws FtpException When the transfer fails
     */
    public function putFromPath($localFile): self
    {
        $remoteFile = basename($localFile);
        $handle = fopen($localFile, 'rb');

        if ($this->ftp->fput($remoteFile, $handle, FTP_BINARY)) {
            rewind($handle);
            return $this;
        }

        throw new FtpException(
            'Unable to put the remote file from the local file "' . $localFile . '"'
        );
    }

    /**
     * Upload files.
     *
     * @throws FtpException When the transfer fails
     */
    public function putAll(string $sourceDirectory, $targetDirectory, int $mode = FTP_BINARY): self
    {
        $d = dir($sourceDirectory);

        // do this for each file in the directory
        while ($file = $d->read()) {
            // to prevent an infinite loop
            if ($file !== "." && $file !== "..") {
                // do the following if it is a directory
                if (is_dir($sourceDirectory . '/' . $file)) {
                    if (!$this->isDir($targetDirectory . '/' . $file)) {
                        // create directories that do not yet exist
                        $this->ftp->mkdir($targetDirectory . '/' . $file);
                    }

                    // recursive part
                    $this->putAll(
                        $sourceDirectory . '/' . $file,
                        $targetDirectory . '/' . $file,
                        $mode
                    );
                } else {
                    // put the files
                    $this->ftp->put(
                        $targetDirectory . '/' . $file,
                        $sourceDirectory . '/' . $file,
                        $mode
                    );
                }
            }
        }

        $d->close();

        return $this;
    }

    /**
     * Downloads all files from remote FTP directory
     *
     * @throws FtpException When the transfer fails
     * @throws RuntimeException If the local directory was not created
     */
    public function getAll(string $sourceDirectory, string $targetDirectory, int $mode = FTP_BINARY): self
    {
        if ($sourceDirectory !== ".") {
            if ($this->ftp->chdir($sourceDirectory) === false) {
                throw new FtpException("Unable to change directory: " . $sourceDirectory);
            }

            if (!(is_dir($targetDirectory)) && !mkdir($targetDirectory) && !is_dir($targetDirectory)) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $targetDirectory));
            }

            chdir($targetDirectory);
        }

        $contents = $this->ftp->nlist(".");

        foreach ($contents as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $this->ftp->get($targetDirectory . "/" . $file, $file, $mode);
        }

        $this->ftp->chdir("..");
        chdir("..");

        return $this;
    }

    /**
     * Returns a detailed list of files in the given directory.
     *
     * @param string $directory The directory, by default is the current directory
     * @return string[]
     *
     * @throws FtpException If unable to list the directory
     */
    public function rawlist(string $directory = '.', bool $recursive = false): array
    {
        if (!$this->isDir($directory)) {
            throw new FtpException('"' . $directory . '" is not a directory.');
        }

        if (strpos($directory, " ") > 0) {
            $ftpRoot = $this->ftp->pwd();
            $this->ftp->chdir($directory);
            $list = $this->ftp->rawlist("");
            $this->ftp->chdir($ftpRoot);
        } else {
            $list = $this->ftp->rawlist($directory);
        }

        $items = [];

        if (!$list) {
            return $items;
        }

        if (!$recursive) {
            foreach ($list as $item) {
                $chunks = preg_split("/\s+/", $item);

                // if not "name"
                if (!isset($chunks[8]) || $chunks[8] === '' || $chunks[8] === '.' || $chunks[8] === '..') {
                    continue;
                }

                $path = $directory . '/' . $chunks[8];

                if (isset($chunks[9])) {
                    $nbChunks = count($chunks);

                    for ($i = 9; $i < $nbChunks; $i++) {
                        $path .= ' ' . $chunks[$i];
                    }
                }


                if (str_starts_with($path, './')) {
                    $path = substr($path, 2);
                }

                $items[$this->rawToType($item) . '#' . $path] = $item;
            }

            return $items;
        }

        foreach ($list as $item) {
            $len = strlen($item);

            if (!$len

                // "."
                || (($item[$len - 1] === '.' && $item[$len - 2] === ' ')

                    // ".."
                    || ($item[$len - 1] === '.' && $item[$len - 2] === '.' && $item[$len - 3] === ' '))
            ) {
                continue;
            }

            $chunks = preg_split("/\s+/", $item);

            // if not "name"
            if (!isset($chunks[8]) || $chunks[8] === '' || $chunks[8] === '.' || $chunks[8] === '..') {
                continue;
            }

            $path = $directory . '/' . $chunks[8];

            if (isset($chunks[9])) {
                $nbChunks = count($chunks);

                for ($i = 9; $i < $nbChunks; $i++) {
                    $path .= ' ' . $chunks[$i];
                }
            }

            if (str_starts_with($path, './')) {
                $path = substr($path, 2);
            }

            $items[$this->rawToType($item) . '#' . $path] = $item;

            if ($item[0] === 'd') {
                $sublist = $this->rawlist($path, true);

                foreach ($sublist as $subPath => $subitem) {
                    $items[$subPath] = $subitem;
                }
            }
        }

        return $items;
    }

    /**
     * Parse raw list.
     *
     * @param string[] $rawList
     * @return array<string, array<string, string>>
     */
    public function parseRawList(array $rawList): array
    {
        $items = [];
        $path = '';

        foreach ($rawList as $key => $child) {
            $chunks = preg_split("/\s+/", $child, 9);

            if (isset($chunks[8]) && ($chunks[8] === '.' || $chunks[8] === '..')) {
                continue;
            }

            if (count($chunks) === 1) {
                $len = strlen($chunks[0]);

                if ($len && $chunks[0][$len - 1] === ':') {
                    $path = substr($chunks[0], 0, -1);
                }

                continue;
            }

            // Prepare for filename that has space
            $nameSlices = array_slice($chunks, 8, true);

            $item = [
                'permissions' => $chunks[0],
                'number' => $chunks[1],
                'owner' => $chunks[2],
                'group' => $chunks[3],
                'size' => $chunks[4],
                'month' => $chunks[5],
                'day' => $chunks[6],
                'time' => $chunks[7],
                'name' => implode(' ', $nameSlices),
                'type' => $this->rawToType($chunks[0]),
            ];

            if ($item['type'] === 'link' && isset($chunks[10])) {
                $item['target'] = $chunks[10]; // 9 is "->"
            }

            // if the key is not the path, behavior of ftp_rawlist() PHP function
            if (is_int($key) || !str_contains($key, $item['name'])) {
                array_splice($chunks, 0, 8);

                $key = $item['type'] . '#'
                    . ($path ? $path . '/' : '')
                    . implode(' ', $chunks);

                if ($item['type'] === 'link') {
                    // get the first part of 'link#the-link.ext -> /path/of/the/source.ext'
                    $exp = explode(' ->', $key);
                    $key = rtrim($exp[0]);
                }
            }
            $items[$key] = $item;
        }

        return $items;
    }

    /**
     * Convert raw info (drwx---r-x ...) to type (file, directory, link, unknown).
     * Only the first char is used for resolving.
     *
     * @param string $permission Example : drwx---r-x
     * @return string The file type (file, directory, link, unknown)
     */
    public function rawToType(string $permission): string
    {
        if (empty($permission[0])) {
            return 'unknown';
        }

        return match ($permission[0]) {
            '-' => 'file',
            'd' => 'directory',
            'l' => 'link',
            default => 'unknown',
        };
    }

    /**
     * Returns an original FTP connection if connected
     */
    public function getConnection(): ?Connection
    {
        return $this->ftp->getConnection();
    }

    /**
     * Join two parts of a path.
     */
    private function joinPaths(string $part1, string $part2): string
    {
        return rtrim($part1, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($part2, DIRECTORY_SEPARATOR);
    }
}
