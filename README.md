# tomedio/php-ftp-client

[![Latest Stable Version](https://poser.pugx.org/tomedio/php-ftp-client/v/stable)](https://packagist.org/packages/tomedio/php-ftp-client)
[![Total Downloads](https://poser.pugx.org/tomedio/php-ftp-client/downloads)](https://packagist.org/packages/tomedio/php-ftp-client)
[![License](https://poser.pugx.org/tomedio/php-ftp-client/license)](https://packagist.org/packages/tomedio/php-ftp-client)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-8892BF.svg)](https://php.net/)

A modern FTP and SSL-FTP client for PHP, offering easy-to-use helpers for managing remote files.

> Note: This library requires PHP >=8.1.

## Installation

To get started with PHP FTP Client in your project, you can install it via Composer:
```bash
composer require tomedio/php-ftp-client
```

After installation, you can use the library in your PHP project to connect to an FTP server and perform various operations.

## Getting Started

Connect to an FTP server:
```php
$ftp = new \FtpClient\FtpClient();
$ftp->connect($host);
$ftp->login($login, $password);
```

OR

Connect to an FTP server via SSL (on port 990 or another port):
```php
$ftp = new \FtpClient\FtpClient();
$ftp->connect($host, true, 990);
$ftp->login($login, $password);
```

Note: The connection is implicitly closed at the end of script execution (when the object is destroyed). Therefore, it is unnecessary to call `$ftp->close()`, except for an explicit re-connection.

### Usage

Upload all files and directories:
```php
// Upload with the BINARY mode
$ftp->putAll($sourceDirectory, $targetDirectory);

// Is equal to
$ftp->putAll($sourceDirectory, $targetDirectory, FTP_BINARY);

// Or upload with the ASCII mode
$ftp->putAll($sourceDirectory, $targetDirectory, FTP_ASCII);
```

*Note: FTP_ASCII and FTP_BINARY are predefined PHP internal constants.*

Get a directory size:
```php
// Size of the current directory
$size = $ftp->dirSize();

// Size of a given directory
$size = $ftp->dirSize('/path/of/directory');
```

Count the items in a directory:
```php
// Count in the current directory
$total = $ftp->countItems();

// Or alias
$total = $ftp->count();
```

Detailed list of all files and directories in a directory:
```php
$items = $ftp->scanDir();

// scan the current directory (recursive) and returns the details of each item
var_dump($ftp->scanDir('.', true));
```

Results:
```text
'directory#public' =>
    array (size=10)
      'permissions' => string 'drwxr-xr-x' (length=10)
      'number'      => string '2' (length=1)
      'owner'       => string '1000' (length=4)
      'group'       => string 'staff' (length=5)
      'size'        => string '4096' (length=4)
      'month'       => string 'Dec' (length=3)
      'day'         => string '12' (length=2)
      'time'        => string '10:15' (length=5)
      'name'        => string 'public' (length=6)
      'type'        => string 'directory' (length=9)

'link#public/logo.png' =>
    array (size=11)
      'permissions' => string 'lrwxrwxrwx' (length=10)
      'number'      => string '1' (length=1)
      'owner'       => string '1000' (length=4)
      'group'       => string 'staff' (length=5)
      'size'        => string '20' (length=2)
      'month'       => string 'Dec' (length=3)
      'day'         => string '10' (length=2)
      'time'        => string '09:30' (length=5)
      'name'        => string 'logo.png' (length=8)
      'type'        => string 'link' (length=4)
      'target'      => string '/var/www/shared/logo.png' (length=24)

'file#public/index.php' =>
    array (size=10)
      'permissions' => string '-rw-r--r--' (length=10)
      'number'      => string '1' (length=1)
      'owner'       => string '1000' (length=4)
      'group'       => string 'staff' (length=5)
      'size'        => string '1234' (length=4)
      'month'       => string 'Dec' (length=3)
      'day'         => string '12' (length=2)
      'time'        => string '10:15' (length=5)
      'name'        => string 'index.php' (length=9)
      'type'        => string 'file' (length=4)
```

Upload a file to the FTP server:
```php
$ftp->put($localFile, $remoteFile);
```

Download a file from the FTP server:
```php
$ftp->get($remoteFile, $localFile);
```

Delete a file from the FTP server:
```php
$ftp->delete($file);
```

Delete a directory from the FTP server:
```php
$ftp->deleteDir($directory);
```

Create a directory on the FTP server:
```php
$ftp->makeDir($directory);
```

Rename a file or directory on the FTP server:
```php
$ftp->rename($oldName, $newName);
```

Change the permissions of a file or directory on the FTP server:
```php
$ftp->chmod($mode, $file);
```

Change the owner of a file or directory on the FTP server:
```php
$ftp->chown($owner, $file);
```

Change the group of a file or directory on the FTP server:
```php
$ftp->chgrp($group, $file);
```

Get the last modified time of a file or directory on the FTP server:
```php
$ftp->mdtm($file);
```

Get the size of a file on the FTP server:
```php
$ftp->size($file);
```

Get the system type of the FTP server:
```php
$ftp->systype();
```

Get the current working directory on the FTP server:
```php
$ftp->pwd();
```

Change the current working directory on the FTP server:
```php
$ftp->chdir($directory);
```

List the contents of the current directory on the FTP server:
```php
$ftp->nlist();
```

List the contents of a directory on the FTP server:
```php
$ftp->nlist($directory);
```

### Error Handling

The library throws exceptions when an error occurs. You can catch exceptions to handle errors in your application:
```php
try {
    // Code that may throw an exception
} catch (\FtpClient\FtpException $e) {
    // Handle the exception
    echo 'An error occurred: ' . $e->getMessage();
}
```

## License

PHP FTP Client is open-source software licensed under the [MIT license](https://opensource.org/licenses/MIT).
```
