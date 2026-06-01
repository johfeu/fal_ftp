<?php

namespace AdGrafik\FalFtp\FTPClient;

use AdGrafik\FalFtp\FTPClient\Exception\ExistingResourceException;
use AdGrafik\FalFtp\FTPClient\Exception\FileOperationErrorException;
use AdGrafik\FalFtp\FTPClient\Exception\FTPConnectionException;
use AdGrafik\FalFtp\FTPClient\Exception\InvalidAttributeException;
use AdGrafik\FalFtp\FTPClient\Exception\InvalidConfigurationException;
use AdGrafik\FalFtp\FTPClient\Exception\InvalidDirectoryException;
use AdGrafik\FalFtp\FTPClient\Exception\ResourceDoesNotExistException;

/***************************************************************
 * Copyright notice
 *
 * (c) 2014 Arno Dudek <webmaster@adgrafik.at>
 * All rights reserved
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
interface FTPInterface
{
    /**
     * Constructor.
     */
    public function __construct(array $settings);

    /**
     * Connect to the FTP server.
     *
     * @param string $username
     * @param string $password
     *
     * @throws InvalidConfigurationException
     */
    public function connect($username = '', $password = ''): FTPInterface;

    /**
     * Close the FTP connection.
     *
     * @throws InvalidConfigurationException
     */
    public function disconnect(): FTPInterface;

    /**
     * Logs in to the FTP connection.
     *
     * @param string $username
     * @param string $password
     *
     * @throws InvalidConfigurationException
     */
    public function login($username, $password): FTPInterface;

    /**
     * Returns TRUE if given directory or file exists.
     *
     * @param string $resource remote directory or file, relative path from basePath
     *
     * @return bool
     */
    public function resourceExists($resource);

    /**
     * Renames a directory or file on the FTP server.
     *
     * @param string $sourceResource source remote directory or file, relative path from basePath
     * @param string $targetResource target remote directory or file, relative path from basePath
     * @param bool $overwrite
     *
     * @throws ExistingResourceException
     * @throws FTPConnectionException thrown at FTP error
     */
    public function renameResource($sourceResource, $targetResource, $overwrite = false): FTPInterface;

    /**
     * Returns TRUE if given directory exists.
     *
     * @param string $directory remote directory, relative path from basePath
     *
     * @return bool
     */
    public function directoryExists($directory);

    /**
     * Changes the current directory to the specified one.
     *
     * @param string $directory remote directory, relative path from basePath
     *
     * @throws InvalidDirectoryException
     */
    public function changeDirectory($directory): FTPInterface;

    /**
     * Changes the current directory to the parent directory.
     *
     * @param string $directory remote directory, relative path from basePath
     *
     * @throws InvalidDirectoryException
     */
    public function changeToParentDirectory($directory): FTPInterface;

    /**
     * Creates a directory.
     *
     * @param string $directory remote directory, relative path from basePath
     *
     * @throws FTPConnectionException thrown at FTP error
     */
    public function createDirectory($directory): FTPInterface;

    /**
     * Renames a directory on the FTP server.
     * Alias of renameResource().
     *
     * @param string $sourceDirectory source remote directory, relative path from basePath
     * @param string $targetDirectory target remote directory, relative path from basePath
     * @param bool $overwrite
     */
    public function renameDirectory($sourceDirectory, $targetDirectory, $overwrite = false): FTPInterface;

    /**
     * Moves a directory on the FTP server.
     * Alias of renameResource().
     *
     * @param string $sourceDirectory source remote directory, relative path from basePath
     * @param string $targetDirectory target remote directory, relative path from basePath
     * @param bool $overwrite
     */
    public function moveDirectory($sourceDirectory, $targetDirectory, $overwrite = false): FTPInterface;

    /**
     * Copy a directory on the FTP server.
     *
     * @param string $sourceDirectory source remote directory, relative path from basePath
     * @param string $targetDirectory target remote directory, relative path from basePath
     * @param bool $overwrite
     *
     * @throws ExistingResourceException
     */
    public function copyDirectory($sourceDirectory, $targetDirectory, $overwrite = false): FTPInterface;

    /**
     * Moves a directory on the FTP server.
     *
     * @param string $directory remote directory, relative path from basePath
     * @param bool $recursively
     *
     * @throws FTPConnectionException thrown at FTP error
     */
    public function deleteDirectory($directory, $recursively = true): FTPInterface;

    /**
     * Returns TRUE if given file exists.
     *
     * @param string $file remote file, relative path from basePath
     *
     * @return bool
     */
    public function fileExists($file);

    /**
     * Returns the size of the given file.
     *
     * @param string $file remote file, relative path from basePath
     *
     * @return int
     *
     * @throws FileOperationErrorException
     */
    public function getFileSize($file);

    /**
     * Uploads a file to the FTP server.
     *
     * @param string $targetFile target remote file, relative path from basePath
     * @param mixed $sourceFileOrResource local source file or file resource, absolute path
     * @param bool $overwrite
     *
     * @throws ResourceDoesNotExistException
     * @throws ExistingResourceException
     * @throws FTPConnectionException thrown at FTP error
     */
    public function uploadFile($targetFile, mixed $sourceFileOrResource, $overwrite = false): FTPInterface;

    /**
     * Download a file to a temporary file.
     *
     * @param string $sourceFile target remote file, relative path from basePath
     * @param mixed $targetFileOrResource local target file or file resource, absolute path
     *
     * @throws ResourceDoesNotExistException
     * @throws FTPConnectionException thrown at FTP error
     */
    public function downloadFile($sourceFile, mixed $targetFileOrResource): FTPInterface;

    /**
     * Set the contents of a file.
     *
     * @param string $file remote file, relative path from basePath
     * @param string $contents
     *
     * @return int
     *
     * @throws FileOperationErrorException thrown if writing temporary file fails
     */
    public function setFileContents($file, $contents);

    /**
     * Get the contents of a file.
     *
     * @param string $file remote file, relative path from basePath
     *
     * @return string
     *
     * @throws FTPConnectionException thrown at FTP error
     */
    public function getFileContents($file);

    /**
     * Create a file on the FTP server.
     *
     * @param string $file remote file, relative path from basePath
     * @param bool $overwrite
     *
     * @throws ExistingResourceException
     * @throws FTPConnectionException thrown at FTP error
     */
    public function createFile($file, $overwrite = false): FTPInterface;

    /**
     * Replace a file to the FTP server.
     * Alias of uploadFile().
     *
     * @param string $targetFile target remote file, relative path from basePath
     * @param mixed $sourceFileOrResource local source file or file resource, absolute path
     */
    public function replaceFile($targetFile, mixed $sourceFileOrResource): FTPInterface;

    /**
     * Renames a file on the FTP server.
     * Alias of renameResource().
     *
     * @param string $sourceFile source remote file, relative path from basePath
     * @param string $targetFile target remote file, relative path from basePath
     * @param bool $overwrite
     */
    public function renameFile($sourceFile, $targetFile, $overwrite = false): FTPInterface;

    /**
     * Moves a file on the FTP server.
     * Alias of renameResource().
     *
     * @param string $sourceFile source remote file, relative path from basePath
     * @param string $targetFile target remote file, relative path from basePath
     * @param bool $overwrite
     */
    public function moveFile($sourceFile, $targetFile, $overwrite = false): FTPInterface;

    /**
     * Copy a file on the FTP server.
     *
     * @param string $sourceFile source remote file, relative path from basePath
     * @param string $targetFile target remote file, relative path from basePath
     * @param bool $overwrite
     */
    public function copyFile($sourceFile, $targetFile, $overwrite = false): FTPInterface;

    /**
     * Deletes a file on the FTP server.
     *
     * @param string $file remote file, relative path from basePath
     *
     * @throws FTPConnectionException thrown at FTP error
     */
    public function deleteFile($file): FTPInterface;

    /**
     * Scans an ftp_rawlist line string and returns its parts (directory/file, name, size,...) using preg_match().
     *
     * @param string $directory remote directory, relative path from basePath
     * @param mixed $resourceInfoParserCallback either an array of object and method name or a function name
     * @param string $sort
     *
     * @return array
     *
     * @throws FTPConnectionException thrown at FTP error
     * @throws InvalidConfigurationException
     * @throws InvalidAttributeException
     */
    public function fetchDirectoryList($directory, mixed $resourceInfoParserCallback = null, $sort = 'strnatcasecmp');

    public function getMimeType(string $fileName): string;
}
