<?php
namespace AdGrafik\FalFtp\FTPClient;

/***************************************************************
 * Copyright notice
 *
 * (c) 2014 Arno Dudek <webmaster@adgrafik.at>
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
use AdGrafik\FalFtp\FTPClient\Filter\DotsFilter;
use AdGrafik\FalFtp\FTPClient\Filter\StringTotalFilter;
use AdGrafik\FalFtp\FTPClient\Parser\AS400Parser;
use AdGrafik\FalFtp\FTPClient\Parser\LessStrictRulesParser;
use AdGrafik\FalFtp\FTPClient\Parser\NetwareParser;
use AdGrafik\FalFtp\FTPClient\Parser\StrictRulesParser;
use AdGrafik\FalFtp\FTPClient\Parser\TitanParser;
use AdGrafik\FalFtp\FTPClient\Parser\WindowsParser;
use TYPO3\CMS\Core\Resource\Index\ExtractorRegistry;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * FTP client.
 *
 * @author Arno Dudek <webmaster@adgrafik.at>
 * @author Jonas Temmen <jonas.temmen@artundweise.de>
 */
class FTP extends AbstractFTP
{
    /**
     * @var bool
     */
    const MODE_ACTIVE = false;

    /**
     * @var bool
     */
    const MODE_PASSIV = true;

    /**
     * @var bool
     */
    const TRANSFER_ASCII = FTP_ASCII;

    /**
     * @var bool
     */
    const TRANSFER_BINARY = FTP_BINARY;

    /**
     * @var bool
     */
    protected $isConnected = false;

    /**
     * @var string
     */
    protected $host;

    /**
     * @var int
     */
    protected $port;

    /**
     * @var string
     */
    protected $username;

    /**
     * @var string
     */
    protected $password;

    /**
     * @var bool
     */
    protected $ssl;

    /**
     * @var int
     */
    protected $timeout;

    /**
     * @var bool
     */
    protected $passiveMode;

    /**
     * @var bool
     */
    protected $transferMode;

    /**
     * @var string
     */
    protected $basePath;

    /**
     * Constructor
     *
     * @param array $settings
     */
    public function __construct(array $settings)
    {
        $this->parserRegistry = GeneralUtility::makeInstance(ParserRegistry::class);
        if ($this->parserRegistry->hasParser() === false) {
            $this->parserRegistry->registerParser([StrictRulesParser::class, LessStrictRulesParser::class, WindowsParser::class, NetwareParser::class, AS400Parser::class, TitanParser::class]);
        }

        $this->filterRegistry = GeneralUtility::makeInstance(FilterRegistry::class);
        if ($this->filterRegistry->hasFilter() === false) {
            $this->filterRegistry->registerFilter([DotsFilter::class, StringTotalFilter::class]);
        }

        $extractorRegistry = GeneralUtility::makeInstance(ExtractorRegistry::class);
        $extractorRegistry->registerExtractionService(ImageDimensionExtractor::class);

        $this->host = urldecode(trim((string)$settings['host'], '/') ?: '');
        $this->port = (int)$settings['port'] ?: 21;
        $this->username = $settings['username'];
        $this->password = $settings['password'];
        $this->ssl = (bool)$settings['ssl'];
        $this->timeout = (int)$settings['timeout'] ?: 90;
        $this->passiveMode = isset($settings['passiveMode']) ? (bool)$settings['passiveMode'] : self::MODE_PASSIV;
        $this->transferMode = $settings['transferMode'] ?? self::TRANSFER_BINARY;
        $this->basePath = '/' . (trim((string)$settings['basePath'], '/') ?: '');
    }

    /**
     * Connect to the FTP server.
     *
     * @param string $username
     * @param string $password
     * @throws InvalidConfigurationException
     * @return FTP
     */
    public function connect($username = '', $password = '')
    {
        if ($this->isConnected) {
            return $this;
        }

        $this->stream = $this->ssl
            ? @ftp_ssl_connect($this->host, $this->port, $this->timeout)
            : @ftp_connect($this->host, $this->port, $this->timeout);

        if ($this->stream === false) {
            throw new InvalidConfigurationException('Couldn\'t connect to host "' . $this->host . ':' . $this->port . '".', 1408550516);
        }

        $this->isConnected = true;

        if (!empty($username)) {
            $this->username = $username;
            $this->password = $password;
        }
        if ($this->username) {
            $this->login($this->username, $this->password)->setPassiveMode($this->passiveMode);
        }

        return $this;
    }

    /**
     * Close the FTP connection.
     *
     * @throws InvalidConfigurationException
     * @return FTP
     */
    public function disconnect()
    {
        $result = @ftp_close($this->getStream());
        if ($result === false) {
            throw new InvalidConfigurationException('Closeing connection faild.', 1408550517);
        }

        return $this;
    }

    /**
     * Logs in to the FTP connection.
     *
     * @param string $username
     * @param string $password
     * @throws InvalidConfigurationException
     * @return FTP
     */
    public function login($username = '', $password = '')
    {
        $username = $username ? urldecode($username) : 'anonymous';

        $result = @ftp_login($this->getStream(), $username, $password);
        if ($result === false) {
            throw new InvalidConfigurationException('Couldn\'t connect with username "' . $this->username . '".', 1408550518);
        }

        return $this;
    }

    /**
     * Turns passive mode on or off.
     *
     * @param bool $passiveMode
     * @throws FTPConnectionException thrown at FTP error
     * @return FTP
     */
    public function setPassiveMode($passiveMode)
    {
        $result = @ftp_pasv($this->getStream(), $this->passiveMode);
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
     * @return bool
     */
    public function resourceExists($resource)
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
     * @throws FTPConnectionException thrown at FTP error
     * @return int
     */
    public function getModificationTime($resource)
    {
        $result = @ftp_mdtm($this->getStream(), $this->getAbsolutePath($resource));
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
     * @param bool $overwrite
     * @throws ExistingResourceException
     * @throws FTPConnectionException thrown at FTP error
     * @return FTP
     */
    public function renameResource($sourceResource, $targetResource, $overwrite = false)
    {
        if ($overwrite === false && $this->resourceExists($targetResource)) {
            throw new ExistingResourceException('Resource "' . $sourceResource . '" already exists.', 1408550521);
        }

        $result = @ftp_rename($this->getStream(), $this->getAbsolutePath($sourceResource), $this->getAbsolutePath($targetResource));
        if ($result === false) {
            throw new FTPConnectionException('Renaming resource "' . $sourceResource . '" to "' . $targetResource . '" failed.', 1408550522);
        }

        return $this;
    }

    /**
     * Returns TRUE if given directory exists.
     *
     * @param string $directory remote directory, relative path from basePath
     * @return bool
     */
    public function directoryExists($directory)
    {
        $result = @ftp_chdir($this->getStream(), $this->getAbsolutePath($directory));

        return $result;
    }

    /**
     * Changes the current directory to the specified one.
     *
     * @param string $directory remote directory, relative path from basePath
     * @throws InvalidDirectoryException
     * @return FTP
     */
    public function changeDirectory($directory)
    {
        $result = @ftp_chdir($this->getStream(), $this->getAbsolutePath($directory));
        if ($result === false) {
            throw new InvalidDirectoryException('Changing directory "' . $directory . '" faild.', 1408550523);
        }

        return $this;
    }

    /**
     * Changes the current directory to the parent directory.
     *
     * @param string $directory remote directory, relative path from basePath
     * @throws InvalidDirectoryException
     * @return FTP
     */
    public function changeToParentDirectory($directory)
    {
        $result = @ftp_cdup($this->getStream());
        if ($result === false) {
            throw new InvalidDirectoryException('Changing to parent directory from "' . $directory . '" faild.', 1408550524);
        }

        return $this;
    }

    /**
     * Creates a directory.
     *
     * @param string $directory remote directory, relative path from basePath
     * @throws FTPConnectionException thrown at FTP error
     * @return FTP
     */
    public function createDirectory($directory)
    {
        $result = @ftp_mkdir($this->getStream(), $this->getAbsolutePath($directory));
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
     * @param bool $overwrite
     * @return FTP
     */
    public function renameDirectory($sourceDirectory, $targetDirectory, $overwrite = false)
    {
        return $this->renameResource($sourceDirectory, $targetDirectory, $overwrite);
    }

    /**
     * Moves a directory on the FTP server.
     * Alias of renameResource().
     *
     * @param string $sourceDirectory source remote directory, relative path from basePath
     * @param string $targetDirectory target remote directory, relative path from basePath
     * @param bool $overwrite
     * @return FTP
     */
    public function moveDirectory($sourceDirectory, $targetDirectory, $overwrite = false)
    {
        return $this->renameResource($sourceDirectory, $targetDirectory, $overwrite);
    }

    /**
     * Copy a directory on the FTP server.
     *
     * @param string $sourceDirectory source remote directory, relative path from basePath
     * @param string $targetDirectory target remote directory, relative path from basePath
     * @param bool $overwrite
     * @throws ExistingResourceException
     * @return FTP
     */
    public function copyDirectory($sourceDirectory, $targetDirectory, $overwrite = false)
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
     * @param bool $recursively
     * @throws FTPConnectionException thrown at FTP error
     * @return FTP
     */
    public function deleteDirectory($directory, $recursively = true)
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

        $result = @ftp_rmdir($this->getStream(), $this->getResourceName($directory));
        if ($result === false) {
            throw new FTPConnectionException('Deleting directory ' . $directory . ' failed.', 1408550527);
        }

        return $result;
    }

    /**
     * Returns TRUE if given file exists.
     *
     * @param string $file remote file, relative path from basePath
     * @return bool
     */
    public function fileExists($file)
    {
        $result = @ftp_size($this->getStream(), $this->getAbsolutePath($file));

        return $result !== -1;
    }

    /**
     * Returns the size of the given file.
     *
     * @param string $file remote file, relative path from basePath
     * @throws FileOperationErrorException
     * @return int
     */
    public function getFileSize($file)
    {
        $result = @ftp_size($this->getStream(), $this->getAbsolutePath($file));
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
     * @param bool $overwrite
     * @throws ResourceDoesNotExistException
     * @throws ExistingResourceException
     * @throws FTPConnectionException thrown at FTP error
     * @return FTP
     */
    public function uploadFile($targetFile, $sourceFileOrResource, $overwrite = false)
    {
        if (is_resource($sourceFileOrResource) === false && @is_file($sourceFileOrResource) === false) {
            throw new ResourceDoesNotExistException('File "' . $sourceFileOrResource . '" not exists.', 1408550529);
        }

        if ($overwrite === false && $this->resourceExists($targetFile)) {
            throw new ExistingResourceException('File "' . $targetFile . '" already exists.', 1408550530);
        }

        if (is_resource($sourceFileOrResource)) {
            rewind($sourceFileOrResource);
            $result = @ftp_fput($this->getStream(), $this->getAbsolutePath($targetFile), $sourceFileOrResource, $this->transferMode);
        } else {
            $result = @ftp_put($this->getStream(), $this->getAbsolutePath($targetFile), $sourceFileOrResource, $this->transferMode);
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
     * @throws ResourceDoesNotExistException
     * @throws FTPConnectionException thrown at FTP error
     * @return FTP
     */
    public function downloadFile($sourceFile, $targetFileOrResource)
    {
        if (is_resource($targetFileOrResource) === false && @is_file($targetFileOrResource) === false) {
            throw new ResourceDoesNotExistException('File "' . $targetFileOrResource . '" not exists.', 1408550532);
        }

        if (is_resource($targetFileOrResource)) {
            $result = @ftp_fget($this->getStream(), $targetFileOrResource, $this->getAbsolutePath($sourceFile), $this->transferMode);
            rewind($targetFileOrResource);
        } else {
            $result = @ftp_get($this->getStream(), $targetFileOrResource, $this->getAbsolutePath($sourceFile), $this->transferMode);
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
     * @param string $contents
     * @throws FileOperationErrorException thrown if writing temporary file fails
     * @return int
     */
    public function setFileContents($file, $contents)
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
     * @throws FTPConnectionException thrown at FTP error
     * @return string
     */
    public function getFileContents($file)
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
     * @param bool $overwrite
     * @throws ExistingResourceException
     * @throws FTPConnectionException thrown at FTP error
     * @return FTP
     */
    public function createFile($file, $overwrite = false)
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
     * @return FTP
     */
    public function replaceFile($targetFile, $sourceFileOrResource)
    {
        return $this->uploadFile($targetFile, $sourceFileOrResource, true);
    }

    /**
     * Renames a file on the FTP server.
     * Alias of renameResource().
     *
     * @param string $sourceFile source remote file, relative path from basePath
     * @param string $targetFile target remote file, relative path from basePath
     * @param bool $overwrite
     * @return FTP
     */
    public function renameFile($sourceFile, $targetFile, $overwrite = false)
    {
        return $this->renameResource($sourceFile, $targetFile, $overwrite);
    }

    /**
     * Moves a file on the FTP server.
     * Alias of renameResource().
     *
     * @param string $sourceFile source remote file, relative path from basePath
     * @param string $targetFile target remote file, relative path from basePath
     * @param bool $overwrite
     * @return FTP
     */
    public function moveFile($sourceFile, $targetFile, $overwrite = false)
    {
        return $this->renameResource($sourceFile, $targetFile, $overwrite);
    }

    /**
     * Copy a file on the FTP server.
     *
     * @param string $sourceFile source remote file, relative path from basePath
     * @param string $targetFile target remote file, relative path from basePath
     * @param bool $overwrite
     * @return FTP
     */
    public function copyFile($sourceFile, $targetFile, $overwrite = false)
    {
        $temporaryFile = tmpfile();

        $this->downloadFile($sourceFile, $temporaryFile)
             ->uploadFile($targetFile, $temporaryFile, $overwrite);

        fclose($temporaryFile);

        return $this;
    }

    /**
     * Deletes a file on the FTP server.
     *
     * @param string $file remote file, relative path from basePath
     * @throws FTPConnectionException thrown at FTP error
     * @return FTP
     */
    public function deleteFile($file)
    {
        $result = @ftp_delete($this->getStream(), $this->getAbsolutePath($file));
        if ($result === false) {
            throw new FTPConnectionException('Deleting file "' . $file . '" faild.', 1408550537);
        }

        return $this;
    }

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
    public function fetchDirectoryList($directory, $resourceInfoParserCallback = null, $sort = 'strnatcasecmp')
    {
        $this->changeDirectory($directory);

        // The -a option is used to show the hidden files as well on some FTP servers.
        $result = @ftp_rawlist($this->getStream(), '-a ');
        if ($result === false) {
            throw new FTPConnectionException('Fetching directory "' . $directory . '" faild.', 1408550538);
        }
        // Some servers do not return anything when using -a, so in that case try again without the -a option.
        if (count($result) <= 1) {
            $result = @ftp_rawlist($this->getStream(), '');
            if ($result === false) {
                throw new FTPConnectionException('Fetching directory "' . $directory . '" faild.', 1408550539);
            }
        }

        $resourceList = [];
        foreach ($result as &$resource) {
            $resourceInfo = ['path' => $directory, 'isDirectory' => null, 'name' => null, 'size' => null, 'owner' => null, 'group' => null, 'mode' => null, 'mimetype' => null, 'mtime' => 0];

            foreach ($this->parserRegistry->getParser() as $parserClass) {
                $parserObject = GeneralUtility::makeInstance($parserClass);
                if ($parseResult = $parserObject->parse($resourceInfo, $resource, $this)) {
                    $resourceInfo['parseClass'] = $parserClass;
                    break;
                }
            }

            // If nothing match throw exception.
            if ($parseResult === false) {
                throw new InvalidConfigurationException('FTP format not supported.', 1408550540);
            }

            foreach ($this->filterRegistry->getFilter() as $filterClass) {
                $filterObject = GeneralUtility::makeInstance($filterClass);
                if ($filterObject->filter($resourceInfo, $resource, $this)) {
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
}
