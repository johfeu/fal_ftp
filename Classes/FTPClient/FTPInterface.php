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
     * Constructor
     *
     * @param array $settings
     */
    public function __construct(array $settings);

    /**
     * Connect to the FTP server.
     *
     * @param string $username
     * @param string $password
     * @throws InvalidConfigurationException
     * @return \AdGrafik\FalFtp\FTPClient\FTPClient
     */
    public function connect($username = '', $password = '');

    /**
     * Close the FTP connection.
     *
     * @throws InvalidConfigurationException
     * @return \AdGrafik\FalFtp\FTPClient\FTPClient
     */
    public function disconnect();

    /**
     * Logs in to the FTP connection.
     *
     * @param string $username
     * @param string $password
     * @throws InvalidConfigurationException
     * @return \AdGrafik\FalFtp\FTPClient\FTPClient
     */
    public function login($username, $password);

    /**
     * Returns TRUE if given directory or file exists.
     *
     * @param string $resource remote directory or file, relative path from basePath
     * @return bool
     */
    public function resourceExists($resource);

    /**
     * Renames a directory or file on the FTP server.
     *
     * @param string $sourceResource source remote directory or file, relative path from basePath
     * @param string $targetResource target remote directory or file, relative path from basePath
     * @param bool $overwrite
     * @throws ExistingResourceException
     * @throws FTPConnectionException thrown at FTP error
     * @return \AdGrafik\FalFtp\FTPClient\FTPClient
     */
    public function renameResource($sourceResource, $targetResource, $overwrite = false);

    /**
     * Returns TRUE if given directory exists.
     *
     * @param string $directory remote directory, relative path from basePath
     * @return bool
     */
    public function directoryExists($directory);

    /**
     * Changes the current directory to the specified one.
     *
     * @param string $directory remote directory, relative path from basePath
     * @throws InvalidDirectoryException
     * @return \AdGrafik\FalFtp\FTPClient\FTPClient
     */
    public function changeDirectory($directory);

    /**
     * Changes the current directory to the parent directory.
     *
     * @param string $directory remote directory, relative path from basePath
     * @throws InvalidDirectoryException
     * @return \AdGrafik\FalFtp\FTPClient\FTPClient
     */
    public function changeToParentDirectory($directory);

    /**
     * Creates a directory.
     *
     * @param string $directory remote directory, relative path from basePath
     * @throws FTPConnectionException thrown at FTP error
     * @return \AdGrafik\FalFtp\FTPClient\FTPClient
     */
    public function createDirectory($directory);

    /**
     * Renames a directory on the FTP server.
     * Alias of renameResource().
     *
     * @param string $sourceDirectory source remote directory, relative path from basePath
     * @param string $targetDirectory target remote directory, relative path from basePath
     * @param bool $overwrite
     * @return \AdGrafik\FalFtp\FTPClient\FTPClient
     */
    public function renameDirectory($sourceDirectory, $targetDirectory, $overwrite = false);

    /**
     * Moves a directory on the FTP server.
     * Alias of renameResource().
     *
     * @param string $sourceDirectory source remote directory, relative path from basePath
     * @param string $targetDirectory target remote directory, relative path from basePath
     * @param bool $overwrite
     * @return \AdGrafik\FalFtp\FTPClient\FTPClient
     */
    public function moveDirectory($sourceDirectory, $targetDirectory, $overwrite = false);

    /**
     * Copy a directory on the FTP server.
     *
     * @param string $sourceDirectory source remote directory, relative path from basePath
     * @param string $targetDirectory target remote directory, relative path from basePath
     * @param bool $overwrite
     * @throws ExistingResourceException
     * @return \AdGrafik\FalFtp\FTPClient\FTPClient
     */
    public function copyDirectory($sourceDirectory, $targetDirectory, $overwrite = false);

    /**
     * Moves a directory on the FTP server.
     *
     * @param string $directory remote directory, relative path from basePath
     * @param bool $recursively
     * @throws FTPConnectionException thrown at FTP error
     * @return \AdGrafik\FalFtp\FTPClient\FTPClient
     */
    public function deleteDirectory($directory, $recursively = true);

    /**
     * Returns TRUE if given file exists.
     *
     * @param string $file remote file, relative path from basePath
     * @return bool
     */
    public function fileExists($file);

    /**
     * Returns the size of the given file.
     *
     * @param string $file remote file, relative path from basePath
     * @throws FileOperationErrorException
     * @return int
     */
    public function getFileSize($file);

    /**
     * Uploads a file to the FTP server.
     *
     * @param string $targetFile target remote file, relative path from basePath
     * @param mixed $sourceFileOrResource local source file or file resource, absolute path
     * @param bool $overwrite
     * @throws ResourceDoesNotExistException
     * @throws ExistingResourceException
     * @throws FTPConnectionException thrown at FTP error
     * @return \AdGrafik\FalFtp\FTPClient\FTPClient
     */
    public function uploadFile($targetFile, mixed $sourceFileOrResource, $overwrite = false);

    /**
     * Download a file to a temporary file.
     *
     * @param string $sourceFile target remote file, relative path from basePath
     * @param mixed $targetFileOrResource local target file or file resource, absolute path
     * @throws ResourceDoesNotExistException
     * @throws FTPConnectionException thrown at FTP error
     * @return \AdGrafik\FalFtp\FTPClient\FTPClient
     */
    public function downloadFile($sourceFile, mixed $targetFileOrResource);

    /**
     * Set the contents of a file.
     *
     * @param string $file remote file, relative path from basePath
     * @param string $contents
     * @throws FileOperationErrorException thrown if writing temporary file fails
     * @return int
     */
    public function setFileContents($file, $contents);

    /**
     * Get the contents of a file.
     *
     * @param string $file remote file, relative path from basePath
     * @throws FTPConnectionException thrown at FTP error
     * @return string
     */
    public function getFileContents($file);

    /**
     * Create a file on the FTP server.
     *
     * @param string $file remote file, relative path from basePath
     * @param bool $overwrite
     * @throws ExistingResourceException
     * @throws FTPConnectionException thrown at FTP error
     * @return \AdGrafik\FalFtp\FTPClient\FTPClient
     */
    public function createFile($file, $overwrite = false);

    /**
     * Replace a file to the FTP server.
     * Alias of uploadFile().
     *
     * @param string $targetFile target remote file, relative path from basePath
     * @param mixed $sourceFileOrResource local source file or file resource, absolute path
     * @return \AdGrafik\FalFtp\FTPClient\FTPClient
     */
    public function replaceFile($targetFile, mixed $sourceFileOrResource);

    /**
     * Renames a file on the FTP server.
     * Alias of renameResource().
     *
     * @param string $sourceFile source remote file, relative path from basePath
     * @param string $targetFile target remote file, relative path from basePath
     * @param bool $overwrite
     * @return \AdGrafik\FalFtp\FTPClient\FTPClient
     */
    public function renameFile($sourceFile, $targetFile, $overwrite = false);

    /**
     * Moves a file on the FTP server.
     * Alias of renameResource().
     *
     * @param string $sourceFile source remote file, relative path from basePath
     * @param string $targetFile target remote file, relative path from basePath
     * @param bool $overwrite
     * @return \AdGrafik\FalFtp\FTPClient\FTPClient
     */
    public function moveFile($sourceFile, $targetFile, $overwrite = false);

    /**
     * Copy a file on the FTP server.
     *
     * @param string $sourceFile source remote file, relative path from basePath
     * @param string $targetFile target remote file, relative path from basePath
     * @param bool $overwrite
     * @return \AdGrafik\FalFtp\FTPClient\FTPClient
     */
    public function copyFile($sourceFile, $targetFile, $overwrite = false);

    /**
     * Deletes a file on the FTP server.
     *
     * @param string $file remote file, relative path from basePath
     * @throws FTPConnectionException thrown at FTP error
     * @return \AdGrafik\FalFtp\FTPClient\FTPClient
     */
    public function deleteFile($file);

    /**
     * Scans an ftp_rawlist line string and returns its parts (directory/file, name, size,...) using preg_match()
     *
     * @param string $directory remote directory, relative path from basePath
     * @param mixed $resourceInfoParserCallback either an array of object and method name or a function name
     * @param string $sort
     * @throws FTPConnectionException thrown at FTP error
     * @throws InvalidConfigurationException
     * @throws InvalidAttributeException
     * @return array
     */
    public function fetchDirectoryList($directory, mixed $resourceInfoParserCallback = null, $sort = 'strnatcasecmp');
}
