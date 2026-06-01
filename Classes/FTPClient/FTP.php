<?php

namespace AdGrafik\FalFtp\FTPClient;

/***************************************************************
 * Copyright notice
 *
 * (c) 2014 Arno Dudek <webmaster@adgrafik.at>
 * (c) 2015 Jonas Temmen <jonas.temmen@artundweise.de>
 * (c) 2015 Nicole Cordes <typo3@cordes.co>
 * (c) 2020 Remo Schneider <remo.schneider@gmx.de>
 * (c) 2023 Johannes Feustel <s@feustel.eu>
 * (c) 2026 Alexander Schnitzler <git@alexanderschnitzler.de>
 * (c) 2026 niho <n@maxdoom.com>
 * All rights reserved
 *
 * Some parts of FTP handling as special parsing the list results
 * was adapted from net2ftp by David Gartner.
 * @see https://www.net2ftp.com
 *
 * This script is part of the TYPO3 project. The TYPO3 project is
 * free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * The GNU General Public License can be found at
 * http://www.gnu.org/copyleft/gpl.html.
 * A copy is found in the textfile GPL.txt and important notices to the license
 * from the author is found in LICENSE.txt distributed with these scripts.
 *
 *
 * This script is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/
use AdGrafik\FalFtp\Extractor\ImageDimensionExtractor;
use AdGrafik\FalFtp\FTPClient\Exception\ExistingResourceException;
use AdGrafik\FalFtp\FTPClient\Exception\FileOperationErrorException;
use AdGrafik\FalFtp\FTPClient\Exception\FTPConnectionException;
use AdGrafik\FalFtp\FTPClient\Exception\InvalidAttributeException;
use AdGrafik\FalFtp\FTPClient\Exception\InvalidConfigurationException;
use AdGrafik\FalFtp\FTPClient\Exception\InvalidDirectoryException;
use AdGrafik\FalFtp\FTPClient\Exception\ResourceDoesNotExistException;
use FTP\Connection;
use FTP\Connection as FTPConnection;
use TYPO3\CMS\Core\Resource\Index\ExtractorRegistry;

/**
 * FTP client.
 *
 * @author Arno Dudek <webmaster@adgrafik.at>
 * @author Jonas Temmen <jonas.temmen@artundweise.de>
 */
class FTP extends AbstractFTP implements FTPInterface
{
    /**
     * @var bool
     */
    public const MODE_ACTIVE = false;

    /**
     * @var bool
     */
    public const MODE_PASSIV = true;

    /**
     * @var int
     */
    public const TRANSFER_ASCII = FTP_ASCII;

    /**
     * @var int
     */
    public const TRANSFER_BINARY = FTP_BINARY;

    protected bool $isConnected = false;

    protected string $host;

    protected int $port;

    protected string $username;

    protected string $password;

    protected bool $ssl;

    protected int $timeout;

    protected bool $passiveMode;

    /** @var int<1,2> */
    protected int $transferMode;

    public string $basePath = '/';

    protected FTPConnection|null $connection = null;

    public function __construct(
        private readonly ParserRegistry $parserRegistry,
        private readonly FilterRegistry $filterRegistry,
        private readonly ExtractorRegistry $extractorRegistry,
    ) {
        $this->extractorRegistry->registerExtractionService(ImageDimensionExtractor::class);
    }

    /**
     * @throws InvalidConfigurationException
     */
    public function initialize(array $settings): void
    {
        $this->host = urldecode(trim((string)$settings['host'], '/') ?: '');
        $this->port = (int)$settings['port'] ?: 21;
        $this->username = $settings['username'] ?? '';
        $this->password = $settings['password'] ?? '';
        $this->ssl = (bool)$settings['ssl'];
        $this->timeout = (int)$settings['timeout'] ?: 90;
        $this->passiveMode = isset($settings['passiveMode']) ? (bool)$settings['passiveMode'] : self::MODE_PASSIV;
        $transferMode = (int)($settings['transferMode'] ?? self::TRANSFER_BINARY);

        if (!in_array($transferMode, [FTP_ASCII, FTP_BINARY], true)) {
            throw new InvalidConfigurationException('Invalid transfer mode "' . $transferMode . '".', 1408550515);
        }

        $this->basePath = '/' . (trim((string)$settings['basePath'], '/') ?: '');
    }

    /**
     * Connect to the FTP server.
     *
     * @throws InvalidConfigurationException
     */
    public function connect(string $username = '', string $password = ''): Connection
    {
        if ($this->connection instanceof Connection) {
            return $this->connection;
        }

        $connection = $this->ssl
            ? @ftp_ssl_connect($this->host, $this->port, $this->timeout)
            : @ftp_connect($this->host, $this->port, $this->timeout);

        if ($connection instanceof Connection === false) {
            throw new InvalidConfigurationException('Couldn\'t connect to host "' . $this->host . ':' . $this->port . '".', 1408550516);
        }

        $this->connection = $connection;

        $this->isConnected = true;

        if (!empty($username)) {
            $this->username = $username;
            $this->password = $password;
        }
        if ($this->username) {
            $this->login($this->username, $this->password)->setPassiveMode($this->passiveMode);
        }

        return $this->connection;
    }

    /**
     * Close the FTP connection.
     *
     * @throws InvalidConfigurationException
     */
    public function disconnect(): static
    {
        $result = @ftp_close($this->connect());
        if ($result === false) {
            throw new InvalidConfigurationException('Closeing connection faild.', 1408550517);
        }

        return $this;
    }

    /**
     * Logs in to the FTP connection.
     *
     * @throws InvalidConfigurationException
     */
    public function login(string $username = '', string $password = ''): static
    {
        $username = $username ? urldecode($username) : 'anonymous';

        $result = @ftp_login($this->connect(), $username, $password);
        if ($result === false) {
            throw new InvalidConfigurationException('Couldn\'t connect with username "' . $this->username . '".', 1408550518);
        }

        return $this;
    }

    /**
     * Turns passive mode on or off.
     *
     * @throws FTPConnectionException thrown at FTP error
     */
    public function setPassiveMode(bool $passiveMode): static
    {
        $result = @ftp_pasv($this->connect(), $this->passiveMode);
        if ($result === false) {
            throw new FTPConnectionException('Setting passive mode faild.', 1408550519);
        }
        $this->passiveMode = (bool)$passiveMode;

        return $this;
    }

    /**
     * Returns TRUE if given directory or file exists.
     *
     * @param string $resource remote directory or file, relative path from basePath
     */
    public function resourceExists(string $resource): bool
    {
        if ($this->directoryExists($resource) === false) {
            return $this->fileExists($resource);
        }

        return true;
    }

    /**
     * Returns the last modified time of the given file (or directory some times).
     *
     * @param string $resource remote directory or file, relative path from basePath
     *
     * @throws FTPConnectionException thrown at FTP error
     */
    public function getModificationTime(string $resource): int
    {
        $result = @ftp_mdtm($this->connect(), $this->getAbsolutePath($resource));
        if ($result === -1) {
            throw new FTPConnectionException('Getting modification time of resource "' . $resource . '" failed.', 1408550520);
        }

        return $result;
    }

    /**
     * Renames a directory or file on the FTP server.
     *
     * @param string $sourceResource source remote directory or file, relative path from basePath
     * @param string $targetResource target remote directory or file, relative path from basePath
     *
     * @throws ExistingResourceException
     * @throws FTPConnectionException thrown at FTP error
     */
    public function renameResource(string $sourceResource, string $targetResource, bool $overwrite = false): static
    {
        if ($overwrite === false && $this->resourceExists($targetResource)) {
            throw new ExistingResourceException('Resource "' . $sourceResource . '" already exists.', 1408550521);
        }

        $result = @ftp_rename($this->connect(), $this->getAbsolutePath($sourceResource), $this->getAbsolutePath($targetResource));
        if ($result === false) {
            throw new FTPConnectionException('Renaming resource "' . $sourceResource . '" to "' . $targetResource . '" failed.', 1408550522);
        }

        return $this;
    }

    /**
     * Returns TRUE if given directory exists.
     *
     * @param string $directory remote directory, relative path from basePath
     */
    public function directoryExists(string $directory): bool
    {
        $result = @ftp_chdir($this->connect(), $this->getAbsolutePath($directory));

        return $result;
    }

    /**
     * Changes the current directory to the specified one.
     *
     * @param string $directory remote directory, relative path from basePath
     *
     * @throws InvalidDirectoryException
     */
    public function changeDirectory(string $directory): static
    {
        $result = @ftp_chdir($this->connect(), $this->getAbsolutePath($directory));
        if ($result === false) {
            throw new InvalidDirectoryException('Changing directory "' . $directory . '" faild.', 1408550523);
        }

        return $this;
    }

    /**
     * Changes the current directory to the parent directory.
     *
     * @param string $directory remote directory, relative path from basePath
     *
     * @throws InvalidDirectoryException
     */
    public function changeToParentDirectory(string $directory): static
    {
        $result = @ftp_cdup($this->connect());
        if ($result === false) {
            throw new InvalidDirectoryException('Changing to parent directory from "' . $directory . '" faild.', 1408550524);
        }

        return $this;
    }

    /**
     * Creates a directory.
     *
     * @param string $directory remote directory, relative path from basePath
     *
     * @throws FTPConnectionException thrown at FTP error
     */
    public function createDirectory(string $directory): static
    {
        $result = @ftp_mkdir($this->connect(), $this->getAbsolutePath($directory));
        if ($result === false) {
            throw new FTPConnectionException('Creating directory "' . $directory . '" faild.', 1408550525);
        }

        return $this;
    }

    /**
     * Renames a directory on the FTP server.
     * Alias of renameResource().
     *
     * @param string $sourceDirectory source remote directory, relative path from basePath
     * @param string $targetDirectory target remote directory, relative path from basePath
     */
    public function renameDirectory(string $sourceDirectory, string $targetDirectory, bool $overwrite = false): static
    {
        return $this->renameResource($sourceDirectory, $targetDirectory, $overwrite);
    }

    /**
     * Moves a directory on the FTP server.
     * Alias of renameResource().
     *
     * @param string $sourceDirectory source remote directory, relative path from basePath
     * @param string $targetDirectory target remote directory, relative path from basePath
     */
    public function moveDirectory(string $sourceDirectory, string $targetDirectory, bool $overwrite = false): static
    {
        return $this->renameResource($sourceDirectory, $targetDirectory, $overwrite);
    }

    /**
     * Copy a directory on the FTP server.
     *
     * @param string $sourceDirectory source remote directory, relative path from basePath
     * @param string $targetDirectory target remote directory, relative path from basePath
     *
     * @throws ExistingResourceException
     */
    public function copyDirectory(string $sourceDirectory, string $targetDirectory, bool $overwrite = false): static
    {
        // If $overwrite is set to FALSE check only for the first directory. On recursion this parameter is by default TRUE.
        if ($overwrite === false && $this->resourceExists($targetDirectory)) {
            throw new ExistingResourceException('Directory "' . $targetDirectory . '" already exists.', 1408550526);
        }

        $this->createDirectory($targetDirectory);

        $directoryList = $this->fetchDirectoryList($sourceDirectory);
        foreach ($directoryList as &$resourceInfo) {
            if ($resourceInfo['isDirectory']) {
                $this->copyDirectory($sourceDirectory . $resourceInfo['name'] . '/', $targetDirectory . $resourceInfo['name'] . '/', true);
            } else {
                $this->copyFile($sourceDirectory . $resourceInfo['name'], $targetDirectory . $resourceInfo['name'], true);
            }
        }

        return $this;
    }

    /**
     * Moves a directory on the FTP server.
     *
     * @param string $directory remote directory, relative path from basePath
     *
     * @throws FTPConnectionException thrown at FTP error
     */
    public function deleteDirectory(string $directory, bool $recursively = true): static
    {
        $directoryList = $this->fetchDirectoryList($directory);

        foreach ($directoryList as &$resourceInfo) {
            if ($resourceInfo['isDirectory'] === false) {
                $this->deleteFile($resourceInfo['path'] . $resourceInfo['name']);
            } elseif ($recursively) {
                $this->deleteDirectory($resourceInfo['path'] . $resourceInfo['name'] . '/', $recursively);
            }
        }

        // The ftp_rmdir may not work with all FTP servers. Solution: to delete /dir/parent/dirtodelete
        // 1. chdir to the parent directory  /dir/parent
        // 2. delete the subdirectory, but use only its name (dirtodelete), not the full path (/dir/parent/dirtodelete)
        $parentDirectory = $this->getParentDirectory($directory);
        $this->changeDirectory($parentDirectory);

        $result = @ftp_rmdir($this->connect(), $this->getResourceName($directory));
        if ($result === false) {
            throw new FTPConnectionException('Deleting directory ' . $directory . ' failed.', 1408550527);
        }

        return $this;
    }

    /**
     * Returns TRUE if given file exists.
     *
     * @param string $file remote file, relative path from basePath
     */
    public function fileExists(string $file): bool
    {
        $result = @ftp_size($this->connect(), $this->getAbsolutePath($file));

        return $result !== -1;
    }

    /**
     * Returns the size of the given file.
     *
     * @param string $file remote file, relative path from basePath
     *
     * @throws FileOperationErrorException
     */
    public function getFileSize(string $file): int
    {
        $result = @ftp_size($this->connect(), $this->getAbsolutePath($file));
        if ($result === -1) {
            throw new FileOperationErrorException('Fetching file size of "' . $file . '" faild.', 1408550528);
        }

        return $result;
    }

    /**
     * Uploads a file to the FTP server.
     *
     * @param string $targetFile target remote file, relative path from basePath
     * @param mixed $sourceFileOrResource local source file or file resource, absolute path
     *
     * @throws ResourceDoesNotExistException
     * @throws ExistingResourceException
     * @throws FTPConnectionException thrown at FTP error
     */
    public function uploadFile(string $targetFile, mixed $sourceFileOrResource, bool $overwrite = false): static
    {
        if (is_resource($sourceFileOrResource) === false && @is_file($sourceFileOrResource) === false) {
            throw new ResourceDoesNotExistException('File "' . $sourceFileOrResource . '" not exists.', 1408550529);
        }

        if ($overwrite === false && $this->resourceExists($targetFile)) {
            throw new ExistingResourceException('File "' . $targetFile . '" already exists.', 1408550530);
        }

        if (is_resource($sourceFileOrResource)) {
            rewind($sourceFileOrResource);
            $result = @ftp_fput($this->connect(), $this->getAbsolutePath($targetFile), $sourceFileOrResource, $this->transferMode);
        } else {
            $result = @ftp_put($this->connect(), $this->getAbsolutePath($targetFile), $sourceFileOrResource, $this->transferMode);
        }

        if ($result === false) {
            throw new FTPConnectionException('Upload file "' . $targetFile . '" faild.', 1408550531);
        }

        return $this;
    }

    /**
     * Download a file to a temporary file.
     *
     * @param string $sourceFile target remote file, relative path from basePath
     * @param mixed $targetFileOrResource local target file or file resource, absolute path
     *
     * @throws ResourceDoesNotExistException
     * @throws FTPConnectionException thrown at FTP error
     */
    public function downloadFile(string $sourceFile, mixed $targetFileOrResource): static
    {
        if (is_resource($targetFileOrResource) === false && @is_file($targetFileOrResource) === false) {
            throw new ResourceDoesNotExistException('File "' . $targetFileOrResource . '" not exists.', 1408550532);
        }

        if (is_resource($targetFileOrResource)) {
            $result = @ftp_fget($this->connect(), $targetFileOrResource, $this->getAbsolutePath($sourceFile), $this->transferMode);
            rewind($targetFileOrResource);
        } else {
            $result = @ftp_get($this->connect(), $targetFileOrResource, $this->getAbsolutePath($sourceFile), $this->transferMode);
        }

        if ($result === false) {
            throw new FTPConnectionException('Download file "' . $sourceFile . '" faild.', 1408550533);
        }

        return $this;
    }

    /**
     * Set the contents of a file.
     *
     * @param string $file remote file, relative path from basePath
     *
     * @throws FileOperationErrorException thrown if writing temporary file fails
     */
    public function setFileContents(string $file, string $contents): int
    {
        $temporaryFile = tmpfile();

        $result = fwrite($temporaryFile, $contents);
        if ($result === false) {
            throw new FileOperationErrorException('Writing temporary file for "' . $file . '" faild.', 1408550534);
        }

        $this->uploadFile($file, $temporaryFile, true);

        fclose($temporaryFile);

        return $result;
    }

    /**
     * Get the contents of a file.
     *
     * @param string $file remote file, relative path from basePath
     *
     * @throws FTPConnectionException thrown at FTP error
     */
    public function getFileContents(string $file): string
    {
        $temporaryFile = tmpfile();

        $this->downloadFile($file, $temporaryFile);

        $result = stream_get_contents($temporaryFile);
        if ($result === false) {
            throw new FileOperationErrorException('Reading temporary file for "' . $file . '" faild.', 1408550535);
        }

        fclose($temporaryFile);

        return $result;
    }

    /**
     * Create a file on the FTP server.
     *
     * @param string $file remote file, relative path from basePath
     *
     * @throws ExistingResourceException
     * @throws FTPConnectionException thrown at FTP error
     */
    public function createFile(string $file, bool $overwrite = false): static
    {
        if ($overwrite === false && $this->resourceExists($file)) {
            throw new ExistingResourceException('File "' . $file . '" already exists.', 1408550536);
        }

        $this->setFileContents($file, '');

        return $this;
    }

    /**
     * Replace a file to the FTP server.
     * Alias of uploadFile().
     *
     * @param string $targetFile target remote file, relative path from basePath
     * @param mixed $sourceFileOrResource local source file or file resource, absolute path
     */
    public function replaceFile(string $targetFile, mixed $sourceFileOrResource): static
    {
        return $this->uploadFile($targetFile, $sourceFileOrResource, true);
    }

    /**
     * Renames a file on the FTP server.
     * Alias of renameResource().
     *
     * @param string $sourceFile source remote file, relative path from basePath
     * @param string $targetFile target remote file, relative path from basePath
     */
    public function renameFile(string $sourceFile, string $targetFile, bool $overwrite = false): static
    {
        return $this->renameResource($sourceFile, $targetFile, $overwrite);
    }

    /**
     * Moves a file on the FTP server.
     * Alias of renameResource().
     *
     * @param string $sourceFile source remote file, relative path from basePath
     * @param string $targetFile target remote file, relative path from basePath
     */
    public function moveFile(string $sourceFile, string $targetFile, bool $overwrite = false): static
    {
        return $this->renameResource($sourceFile, $targetFile, $overwrite);
    }

    /**
     * Copy a file on the FTP server.
     *
     * @param string $sourceFile source remote file, relative path from basePath
     * @param string $targetFile target remote file, relative path from basePath
     */
    public function copyFile(string $sourceFile, string $targetFile, bool $overwrite = false): static
    {
        $temporaryFile = tmpfile();

        $this->downloadFile($sourceFile, $temporaryFile)
             ->uploadFile($targetFile, $temporaryFile, $overwrite)
        ;

        fclose($temporaryFile);

        return $this;
    }

    /**
     * Deletes a file on the FTP server.
     *
     * @param string $file remote file, relative path from basePath
     *
     * @throws FTPConnectionException thrown at FTP error
     */
    public function deleteFile(string $file): static
    {
        $result = @ftp_delete($this->connect(), $this->getAbsolutePath($file));
        if ($result === false) {
            throw new FTPConnectionException('Deleting file "' . $file . '" faild.', 1408550537);
        }

        return $this;
    }

    /**
     * Scans an ftp_rawlist line string and returns its parts (directory/file, name, size,...) using preg_match().
     *
     * @param string $directory remote directory, relative path from basePath
     * @param mixed $resourceInfoParserCallback either an array of object and method name or a function name
     *
     * @throws FTPConnectionException thrown at FTP error
     * @throws InvalidConfigurationException
     * @throws InvalidAttributeException
     */
    public function fetchDirectoryList(string $directory, mixed $resourceInfoParserCallback = null, string $sort = 'strnatcasecmp'): array
    {
        $this->changeDirectory($directory);

        // The -a option is used to show the hidden files as well on some FTP servers.
        $result = @ftp_rawlist($this->connect(), '-a ');
        if ($result === false) {
            throw new FTPConnectionException('Fetching directory "' . $directory . '" faild.', 1408550538);
        }
        // Some servers do not return anything when using -a, so in that case try again without the -a option.
        if (count($result) <= 1) {
            $result = @ftp_rawlist($this->connect(), '');
            if ($result === false) {
                throw new FTPConnectionException('Fetching directory "' . $directory . '" faild.', 1408550539);
            }
        }

        $resourceList = [];
        foreach ($result as &$resource) {
            $resourceInfo = ['path' => $directory, 'isDirectory' => null, 'name' => null, 'size' => null, 'owner' => null, 'group' => null, 'mode' => null, 'mimetype' => null, 'mtime' => 0];

            $parseResult = false;
            foreach ($this->parserRegistry->parsers as $parser) {
                if ($parseResult = $parser->parse($resourceInfo, $resource, $this)) {
                    $resourceInfo['parseClass'] = $parser;
                    break;
                }
            }

            // If nothing match throw exception.
            if ($parseResult === false) {
                throw new InvalidConfigurationException('FTP format not supported.', 1408550540);
            }

            foreach ($this->filterRegistry->filters as $filter) {
                if ($filter->filter($resourceInfo, $resource, $this)) {
                    continue 2;
                }
            }

            if ($resourceInfo['isDirectory'] === null) {
                throw new InvalidAttributeException('FTP resource attribute "isDirectory" can not be NULL.', 1408550541);
            }
            if ($resourceInfo['name'] === null || empty($resourceInfo['name'])) {
                throw new InvalidAttributeException('FTP resource attribute "name" can not be NULL or empty.', 1408550542);
            }

            if ($resourceInfoParserCallback) {
                $resourceInfoReference = &$resourceInfo;
                call_user_func($resourceInfoParserCallback, $resourceInfoReference, $this);
            }

            $resourceList[] = $resourceInfo;
        }

        if ($sort) {
            uksort($resourceList, $sort);
        }

        return $resourceList;
    }

    protected function getBasePath(): string
    {
        return $this->basePath;
    }
}
