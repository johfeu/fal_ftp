<?php

namespace AdGrafik\FalFtp\FTPClient;

use AdGrafik\FalFtp\FTPClient\Exception\ExistingResourceException;
use AdGrafik\FalFtp\FTPClient\Exception\FileOperationErrorException;
use AdGrafik\FalFtp\FTPClient\Exception\FTPConnectionException;
use AdGrafik\FalFtp\FTPClient\Exception\InvalidAttributeException;
use AdGrafik\FalFtp\FTPClient\Exception\InvalidConfigurationException;
use AdGrafik\FalFtp\FTPClient\Exception\InvalidDirectoryException;
use AdGrafik\FalFtp\FTPClient\Exception\ResourceDoesNotExistException;
use FTP\Connection;

/***************************************************************
 * Copyright notice
 *
 * (c) 2014 Arno Dudek <webmaster@adgrafik.at>
 * (c) 2023 Johannes Feustel <s@feustel.eu>
 * (c) 2026 Alexander Schnitzler <git@alexanderschnitzler.de>
 * (c) 2026 niho <n@maxdoom.com>
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
    public function initialize(array $settings): void;

    /**
     * Connect to the FTP server.
     *
     * @throws InvalidConfigurationException
     */
    public function connect(string $username = '', string $password = ''): Connection;

    /**
     * Close the FTP connection.
     *
     * @throws InvalidConfigurationException
     */
    public function disconnect(): FTPInterface;

    /**
     * Logs in to the FTP connection.
     *
     * @throws InvalidConfigurationException
     */
    public function login(string $username, string $password): FTPInterface;

    /**
     * Returns TRUE if given directory or file exists.
     *
     * @param string $resource remote directory or file, relative path from basePath
     */
    public function resourceExists(string $resource): bool;

    /**
     * Renames a directory or file on the FTP server.
     *
     * @param string $sourceResource source remote directory or file, relative path from basePath
     * @param string $targetResource target remote directory or file, relative path from basePath
     *
     * @throws ExistingResourceException
     * @throws FTPConnectionException thrown at FTP error
     */
    public function renameResource(string $sourceResource, string $targetResource, bool $overwrite = false): FTPInterface;

    /**
     * Returns TRUE if given directory exists.
     *
     * @param string $directory remote directory, relative path from basePath
     */
    public function directoryExists(string $directory): bool;

    /**
     * Changes the current directory to the specified one.
     *
     * @param string $directory remote directory, relative path from basePath
     *
     * @throws InvalidDirectoryException
     */
    public function changeDirectory(string $directory): FTPInterface;

    /**
     * Changes the current directory to the parent directory.
     *
     * @param string $directory remote directory, relative path from basePath
     *
     * @throws InvalidDirectoryException
     */
    public function changeToParentDirectory(string $directory): FTPInterface;

    /**
     * Creates a directory.
     *
     * @param string $directory remote directory, relative path from basePath
     *
     * @throws FTPConnectionException thrown at FTP error
     */
    public function createDirectory(string $directory): FTPInterface;

    /**
     * Renames a directory on the FTP server.
     * Alias of renameResource().
     *
     * @param string $sourceDirectory source remote directory, relative path from basePath
     * @param string $targetDirectory target remote directory, relative path from basePath
     */
    public function renameDirectory(string $sourceDirectory, string $targetDirectory, bool $overwrite = false): FTPInterface;

    /**
     * Moves a directory on the FTP server.
     * Alias of renameResource().
     *
     * @param string $sourceDirectory source remote directory, relative path from basePath
     * @param string $targetDirectory target remote directory, relative path from basePath
     */
    public function moveDirectory(string $sourceDirectory, string $targetDirectory, bool $overwrite = false): FTPInterface;

    /**
     * Copy a directory on the FTP server.
     *
     * @param string $sourceDirectory source remote directory, relative path from basePath
     * @param string $targetDirectory target remote directory, relative path from basePath
     *
     * @throws ExistingResourceException
     */
    public function copyDirectory(string $sourceDirectory, string $targetDirectory, bool $overwrite = false): FTPInterface;

    /**
     * Moves a directory on the FTP server.
     *
     * @param string $directory remote directory, relative path from basePath
     *
     * @throws FTPConnectionException thrown at FTP error
     */
    public function deleteDirectory(string $directory, bool $recursively = true): FTPInterface;

    /**
     * Returns TRUE if given file exists.
     *
     * @param string $file remote file, relative path from basePath
     */
    public function fileExists(string $file): bool;

    /**
     * Returns the size of the given file.
     *
     * @param string $file remote file, relative path from basePath
     *
     * @throws FileOperationErrorException
     */
    public function getFileSize(string $file): int;

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
    public function uploadFile(string $targetFile, mixed $sourceFileOrResource, bool $overwrite = false): FTPInterface;

    /**
     * Download a file to a temporary file.
     *
     * @param string $sourceFile target remote file, relative path from basePath
     * @param mixed $targetFileOrResource local target file or file resource, absolute path
     *
     * @throws ResourceDoesNotExistException
     * @throws FTPConnectionException thrown at FTP error
     */
    public function downloadFile(string $sourceFile, mixed $targetFileOrResource): FTPInterface;

    /**
     * Set the contents of a file.
     *
     * @param string $file remote file, relative path from basePath
     *
     * @throws FileOperationErrorException thrown if writing temporary file fails
     */
    public function setFileContents(string $file, string $contents): int;

    /**
     * Get the contents of a file.
     *
     * @param string $file remote file, relative path from basePath
     *
     * @throws FTPConnectionException thrown at FTP error
     */
    public function getFileContents(string $file): string;

    /**
     * Create a file on the FTP server.
     *
     * @param string $file remote file, relative path from basePath
     *
     * @throws ExistingResourceException
     * @throws FTPConnectionException thrown at FTP error
     */
    public function createFile(string $file, bool $overwrite = false): FTPInterface;

    /**
     * Replace a file to the FTP server.
     * Alias of uploadFile().
     *
     * @param string $targetFile target remote file, relative path from basePath
     * @param mixed $sourceFileOrResource local source file or file resource, absolute path
     */
    public function replaceFile(string $targetFile, mixed $sourceFileOrResource): FTPInterface;

    /**
     * Renames a file on the FTP server.
     * Alias of renameResource().
     *
     * @param string $sourceFile source remote file, relative path from basePath
     * @param string $targetFile target remote file, relative path from basePath
     */
    public function renameFile(string $sourceFile, string $targetFile, bool $overwrite = false): FTPInterface;

    /**
     * Moves a file on the FTP server.
     * Alias of renameResource().
     *
     * @param string $sourceFile source remote file, relative path from basePath
     * @param string $targetFile target remote file, relative path from basePath
     */
    public function moveFile(string $sourceFile, string $targetFile, bool $overwrite = false): FTPInterface;

    /**
     * Copy a file on the FTP server.
     *
     * @param string $sourceFile source remote file, relative path from basePath
     * @param string $targetFile target remote file, relative path from basePath
     */
    public function copyFile(string $sourceFile, string $targetFile, bool $overwrite = false): FTPInterface;

    /**
     * Deletes a file on the FTP server.
     *
     * @param string $file remote file, relative path from basePath
     *
     * @throws FTPConnectionException thrown at FTP error
     */
    public function deleteFile(string $file): FTPInterface;

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
    public function fetchDirectoryList(string $directory, mixed $resourceInfoParserCallback = null, string $sort = 'strnatcasecmp'): array;

    public function getMimeType(string $fileName): string;
}
