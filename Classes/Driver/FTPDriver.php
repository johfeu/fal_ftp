<?php
namespace AdGrafik\FalFtp\Driver;

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

use AdGrafik\FalFtp\FTPClient\Exception;
use AdGrafik\FalFtp\FTPClient\Exception\ExistingResourceException;
use AdGrafik\FalFtp\FTPClient\Exception\FTPConnectionException;
use AdGrafik\FalFtp\FTPClient\Exception\InvalidAttributeException;
use AdGrafik\FalFtp\FTPClient\Exception\InvalidConfigurationException;
use AdGrafik\FalFtp\FTPClient\Exception\ResourceDoesNotExistException;
use AdGrafik\FalFtp\FTPClient\FTP;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Charset\CharsetConverter;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\Resource\Capabilities;
use TYPO3\CMS\Core\Resource\Driver\AbstractHierarchicalFilesystemDriver;
use TYPO3\CMS\Core\Resource\Exception\ExistingTargetFileException;
use TYPO3\CMS\Core\Resource\Exception\ExistingTargetFileNameException;
use TYPO3\CMS\Core\Resource\Exception\ExistingTargetFolderException;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Resource\Exception\FolderDoesNotExistException;
use TYPO3\CMS\Core\Resource\Exception\InvalidFileNameException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;

/**
 * Driver for FTP clients.
 *
 * @author Arno Dudek <webmaster@adgrafik.at>
 * @author Nicole Cordes <typo3@cordes.co>
 */
class FTPDriver extends AbstractHierarchicalFilesystemDriver
{
    /**
     * @var string
     */
    const UNSAFE_FILENAME_CHARACTER_EXPRESSION = '\\x00-\\x2C\\/\\x3A-\\x3F\\x5B-\\x60\\x7B-\\xBF';

    /**
     * A list of all supported hash algorithms, written all lower case and
     * without any dashes etc. (e.g. sha1 instead of SHA-1)
     * Be sure to set this in inherited classes!
     *
     * @var array
     */
    protected array $supportedHashAlgorithms = ['sha1', 'md5'];

    /**
     * @var array
     */
    protected $extensionConfiguration;

    /**
     * The $directoryCache caches all files including file info which are loaded via FTP.
     * This cache get refreshed only when an user action is done or file is processed.
     *
     * @var array
     */
    protected $directoryCache;

    /**
     * In this stack all created temporary files are cached. Sometimes a temporary file
     * already exist. In this case use the file which was downloaded already.
     *
     * @var array
     */
    protected $temporaryFileStack;

    /**
     * Limit Thumbnails Rendering: This option can be used to reduce file rendering
     * in the backend. Usually if a thumbnail is created it have to be downloaded first,
     * generated and then uploaded again. With this option it'p possible to define
     * a maximum file size where thumbnails created. If set a local image will be
     * taken as placeholder. Set this option to "0" will deactivate this function.
     *
     * @var int
     */
    protected $createThumbnailsUpToSize;

    /**
     * Default Thumbnails: Path to thumbnail image which is displayed
     * when "createThumbnailsUpToSize" is set.
     *
     * @var string
     */
    protected $defaultThumbnail;

    /**
     * Fetch Real Modification Time: By default the modification time is generated at listing
     * and depends on what FTP server returns. Usually this is enough information.
     * If this feature ist set, the modification time is fetched by the function ftp_mdtm
     * and overwrite the time of the list if it is available. But not all servers support
     * this feature and it will slow down the file listing.
     *
     * @var string
     */
    protected $exactModificationTime;

    /**
     * Enable Remote Service: If this option is set, a service file is uploaded
     * to the FTP server which handles some operations to avoid too much downloading.
     *
     * @var string
     */
    protected $remoteService;

    /**
     * Encryption key for remote service.
     *
     * @var string
     */
    protected $remoteServiceEncryptionKey;

    /**
     * Encryption key for remote service.
     *
     * @var string
     */
    protected $remoteServiceFileName;

    /**
     * Additional header to send with cUrl.
     *
     * @var string
     */
    protected $remoteServiceAdditionalHeaders;

    /**
     * The base path defined in the FTP settings. Must not be the absolute path!
     *
     * @var string
     */
    protected $basePath;

    /**
     * The public URL from the FTP server
     *
     * @var array
     */
    protected $publicUrl;

    /**
     * @var FTP
     */
    protected $ftpClient;

    public function __construct(array $configuration = [])
    {
        parent::__construct($configuration);

        // The capabilities default of this driver. See CAPABILITY_* constants for possible values
        $this->capabilities = new Capabilities(
            Capabilities::CAPABILITY_BROWSABLE |
            Capabilities::CAPABILITY_PUBLIC |
            Capabilities::CAPABILITY_WRITABLE
        );

        // Get and set extension configuration.
        $this->extensionConfiguration = (array)@GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('fal_ftp');
        $this->directoryCache = [];
        $this->temporaryFileStack = [];
    }

    /**
     * Initializes this object. This is called by the storage after the driver
     * has been attached.
     *
     * @return void
     */
    public function __destruct()
    {
        // Delete all temporary files after processing.
        $temporaryPattern = Environment::getPublicPath() . 'typo3temp/fal-ftp-tempfile-*';
        array_map('unlink', glob($temporaryPattern));
    }

    /**
     * Merges the capabilites merged by the user at the storage
     * configuration into the actual capabilities of the driver
     * and returns the result.
     */
    public function mergeConfigurationCapabilities(Capabilities $capabilities): Capabilities
    {
        $this->capabilities->and($capabilities);

        return $this->capabilities;
    }

    /**
     * processes the configuration, should be overridden by subclasses
     *
     * @return void
     */
    public function processConfiguration(): void
    {
        // Throw deprecation message if hooks defined.
        if (isset($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['fal_ftp/Classes/Hook/ListParser.php'])) {
            trigger_error('Hook for fal_ftp parser "$GLOBALS[\'TYPO3_CONF_VARS\'][\'SC_OPTIONS\'][\'fal_ftp/Classes/Hook/ListParser.php\']" is deprecated. Use "AdGrafik\\FalFtp\\FTPClient\\ParserRegistry->registerParser" instead.', E_USER_DEPRECATED);
        }

        $this->createThumbnailsUpToSize = (int)@$this->extensionConfiguration['ftpDriver']['createThumbnailsUpToSize'];
        $this->defaultThumbnail = GeneralUtility::getFileAbsFileName(@$this->extensionConfiguration['ftpDriver']['defaultThumbnail'] ?: 'EXT:fal_ftp/Resources/Public/Images/default_image.png');
        $this->exactModificationTime = (isset($this->extensionConfiguration['ftpDriver']['exactModificationTime']) && $this->extensionConfiguration['ftpDriver']['exactModificationTime']);
        $this->remoteService = (isset($this->extensionConfiguration['remoteService']['enable']) && $this->extensionConfiguration['remoteService']['enable']);
        $this->remoteServiceEncryptionKey = md5((string)(@$this->extensionConfiguration['remoteService']['encryptionKey'] ?: $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey']));
        $this->remoteServiceFileName = '/' . trim((string)(@$this->extensionConfiguration['remoteService']['fileName'] ?: '.FalFtpRemoteService.php'));
        $this->remoteServiceAdditionalHeaders = GeneralUtility::trimExplode(';', (string)@$this->extensionConfiguration['remoteService']['additionalHeaders']);

        // Check if Driver is writable.
        if ($this->remoteService && !$this->hasCapability(\TYPO3\CMS\Core\Resource\ResourceStorageInterface::CAPABILITY_WRITABLE)) {
            $this->addFlashMessage('remoteService is activated in the extension configuration but storage is not set as writable');
            $this->remoteService = false;
        }

        // Set driver configuration.
        $this->basePath = '/' . trim((string)$this->configuration['basePath'], '/');
        $this->publicUrl = trim((string)$this->configuration['publicUrl'], '/');

        $this->configuration['timeout'] = (int)@$this->extensionConfiguration['ftpDriver']['timeout'] ?: 90;
        $this->configuration['ssl'] = (isset($this->configuration['ssl']) && $this->configuration['ssl']);
        // Configuration parameter "mode" deprecated. Use passiveMode instead.
        if (isset($this->configuration['mode']) && isset($this->configuration['passiveMode']) === false) {
            $this->configuration['passiveMode'] = ($this->configuration['mode'] === 'passiv');
        }

        // Connect to FTP server.
        $this->ftpClient = GeneralUtility::makeInstance(\AdGrafik\FalFtp\FTPClient\FTP::class, $this->configuration);
        $registryObject = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Registry::class);
        $storageIdentifier = 'sys_file_storage-' . $this->storageUid . '-' . sha1(serialize($this->configuration)) . '-fal_ftp-configuration-check';
        $configurationChecked = $registryObject->get('fal_ftp', $storageIdentifier, 0);
        if (!$configurationChecked) {
            try {
                $this->ftpClient->connect();
                $registryObject->set('fal_ftp', $storageIdentifier, 1);
            } catch (Exception $exception) {
                $this->addFlashMessage('FTP error: ' . $exception->getMessage());
            }
        }
    }

    /**
     * Initializes this object. This is called by the storage after the driver
     * has been attached.
     *
     * @return void
     */
    public function initialize(): void
    {
    }

    /**
     * Get ftpClient
     *
     * @return resource
     */
    public function getFtpClient()
    {
        return $this->ftpClient;
    }

    /**
     * Returns the public URL to a file.
     */
    public function getPublicUrl(string $identifier): ?string
    {
        return $this->publicUrl . $identifier;
    }

    /**
     * Returns the identifier of the root level folder of the storage.
     */
    public function getRootLevelFolder(): string
    {
        return '/';
    }

    /**
     * Checks if a folder exists
     */
    public function folderExists(string $folderIdentifier): bool
    {
        $folderIdentifier = $this->canonicalizeAndCheckFolderIdentifier($folderIdentifier);

        return $this->ftpClient->directoryExists($folderIdentifier);
    }

    /**
     * Returns the identifier of the default folder new files should be put into.
     */
    public function getDefaultFolder(): string
    {
        $folderIdentifier = '/user_upload/';
        if ($this->folderExists($folderIdentifier) === false) {
            try {
                $folderIdentifier = $this->createFolder('user_upload', '/');
            } catch (\RuntimeException $e) {
                /** @var StorageRepository $storageRepository */
                $storageRepository = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Resource\StorageRepository::class);
                $storage = $storageRepository->findByUid($this->storageUid);
                if ($storage->isWritable()) {
                    throw $e;
                }
            }
        }

        return $folderIdentifier;
    }

    /**
     * Checks if a folder inside a folder exists.
     */
    public function folderExistsInFolder(string $folderName, string $folderIdentifier): bool
    {
        return $this->ftpClient->directoryExists($folderIdentifier . $folderName);
    }

    /**
     * Checks if a given identifier is within a container, e.g. if
     * a file or folder is within another folder. It will also return
     * TRUE if both canonicalized identifiers are equal.
     *
     * @return bool TRUE if $content is within or matches $folderIdentifier
     */
    public function isWithin(string $folderIdentifier, string $identifier): bool
    {
        $folderIdentifier = $this->canonicalizeAndCheckFolderIdentifier($folderIdentifier);
        $entryIdentifier = $this->canonicalizeAndCheckFileIdentifier($identifier);
        if ($folderIdentifier === $entryIdentifier) {
            return true;
        }

        return \str_starts_with($entryIdentifier, $folderIdentifier);
    }

    /**
     * Checks if a folder contains files and (if supported) other folders.
     *
     * @return bool TRUE if there are no files and folders within $folder
     */
    public function isFolderEmpty(string $folderIdentifier): bool
    {
        $this->fetchDirectoryList($folderIdentifier, true);

        return count($this->directoryCache[$folderIdentifier]) === 0;
    }

    /**
     * Returns information about a folder.
     *
     * @throws FolderDoesNotExistException
     */
    public function getFolderInfoByIdentifier(string $folderIdentifier): array
    {
        $folderIdentifier = $this->canonicalizeAndCheckFolderIdentifier($folderIdentifier);
        if (!$this->folderExists($folderIdentifier)) {
            throw new FolderDoesNotExistException('File ' . $folderIdentifier . ' does not exist.', 1314516810);
        }

        return ['identifier' => $folderIdentifier, 'name' => $this->getNameFromIdentifier($folderIdentifier), 'storage' => $this->storageUid];
    }

    /**
     * Returns the identifier of a folder inside the folder
     */
    public function getFolderInFolder(string $folderName, string $folderIdentifier): string
    {
        return $folderIdentifier . $folderName;
    }

    /**
     * Returns a list of folders inside the specified path
     *
     * @param array $folderNameFilterCallbacks callbacks for filtering the items
     * @param string $sort Property name used to sort the items.
     *                     Among them may be: '' (empty, no sorting), name,
     *                     fileext, size, tstamp and rw.
     *                     If a driver does not support the given property, it
     *                     should fall back to "name".
     * @param bool $sortRev TRUE to indicate reverse sorting (last to first)
     */
    public function getFoldersInFolder(string $folderIdentifier, int $start = 0, int $numberOfItems = 0, bool $recursive = false, array $folderNameFilterCallbacks = [], string $sort = '', bool $sortRev = false): array
    {
        return $this->getDirectoryItemList($folderIdentifier, $start, $numberOfItems, $folderNameFilterCallbacks, false, true, $recursive);
    }

    /**
     * Returns the number of files inside the specified path
     *
     * @param array   $filenameFilterCallbacks callbacks for filtering the items
     * @throws \RuntimeException
     */
    public function countFilesInFolder(string $folderIdentifier, bool $recursive = false, array $filenameFilterCallbacks = []): int
    {
        return count($this->getDirectoryItemList($folderIdentifier, 0, 0, $filenameFilterCallbacks, true, false, $recursive));
    }

    /**
     * Returns the number of folders inside the specified path
     *
     * @param array   $folderNameFilterCallbacks callbacks for filtering the items
     * @throws \RuntimeException
     */
    public function countFoldersInFolder(string $folderIdentifier, bool $recursive = false, array $folderNameFilterCallbacks = []): int
    {
        return count($this->getDirectoryItemList($folderIdentifier, 0, 0, $folderNameFilterCallbacks, false, true, $recursive));
    }

    /**
     * Creates a folder, within a parent folder.
     * If no parent folder is given, a root level folder will be created
     *
     * @throws \RuntimeException thrown at FTP error
     */
    public function createFolder(string $newFolderName, string $parentFolderIdentifier = '', bool $recursive = false): string
    {
        $newFolderName = $this->sanitizeFileName($newFolderName);
        $folderIdentifier = $parentFolderIdentifier . $newFolderName . '/';

        try {
            $this->ftpClient->createDirectory($folderIdentifier);
        } catch (FTPConnectionException) {
            throw new \RuntimeException('Creating folder "' . $folderIdentifier . '" faild.', 1408550550);
        }

        $this->fetchDirectoryList($parentFolderIdentifier, true);

        return $folderIdentifier;
    }

    /**
     * Renames a folder in this storage.
     *
     * @throws ExistingTargetFolderException
     * @throws \RuntimeException thrown at FTP error
     * @return array A map of old to new file identifiers of all affected resources
     */
    public function renameFolder(string $folderIdentifier, string $newName): array
    {
        $newFolderIdentifier = $this->getParentFolderIdentifierOfIdentifier($folderIdentifier) . $this->sanitizeFileName($newName) . '/';

        // Create a mapping from old to new identifiers
        $identifierMap = $this->createIdentifierMap($folderIdentifier, $newFolderIdentifier);

        try {
            $this->ftpClient->renameDirectory($folderIdentifier, $newFolderIdentifier);
        } catch (ExistingResourceException) {
            throw new ExistingTargetFolderException('Folder "' . $folderIdentifier . '" already exists.', 1408550551);
        } catch (FTPConnectionException) {
            throw new \RuntimeException('Renaming folder "' . $folderIdentifier . '" faild.', 1408550552);
        }

        return $identifierMap;
    }

    /**
     * Removes a folder from this storage.
     *
     * @throws \RuntimeException
     */
    public function deleteFolder(string $folderIdentifier, bool $deleteRecursively = false): bool
    {
        try {
            $this->ftpClient->deleteDirectory($folderIdentifier, $deleteRecursively);
        } catch (FTPConnectionException) {
            throw new \RuntimeException('Deleting folder "' . $folderIdentifier . '" faild.', 1408550553);
        }

        return true;
    }

    /**
     * Folder equivalent to moveFileWithinStorage().
     *
     * @throws ExistingTargetFolderException
     * @throws \RuntimeException
     */
    public function moveFolderWithinStorage(string $sourceFolderIdentifier, string $targetFolderIdentifier, string $newFolderName): array
    {
        $newIdentifier = $this->canonicalizeAndCheckFolderIdentifier($targetFolderIdentifier . $newFolderName);

        // Create a mapping from old to new identifiers
        $identifierMap = $this->createIdentifierMap($sourceFolderIdentifier, $newIdentifier);

        try {
            $this->ftpClient->moveDirectory($sourceFolderIdentifier, $newIdentifier);
        } catch (ExistingResourceException) {
            throw new ExistingTargetFolderException('Folder "' . $newIdentifier . '" already exists.', 1408550554);
        } catch (FTPConnectionException) {
            throw new \RuntimeException('Moving folder "' . $sourceFolderIdentifier . '" faild.', 1408550555);
        }

        return $identifierMap;
    }

    /**
     * Folder equivalent to copyFileWithinStorage().
     *
     * @throws FolderDoesNotExistException
     * @throws ExistingTargetFolderException
     * @throws \RuntimeException
     */
    public function copyFolderWithinStorage(string $sourceFolderIdentifier, string $targetFolderIdentifier, string $newFolderName): bool
    {
        $targetIdentifier = $targetFolderIdentifier . $newFolderName . '/';

        try {
            $this->ftpClient->copyDirectory($sourceFolderIdentifier, $targetIdentifier);
        } catch (ResourceDoesNotExistException) {
            throw new FolderDoesNotExistException('Source folder "' . $sourceFolderIdentifier . '" not exists', 1408550556);
        } catch (ExistingResourceException) {
            throw new ExistingTargetFolderException('Target folder "' . $targetIdentifier . '" already exists', 1408550557);
        } catch (FTPConnectionException) {
            throw new \RuntimeException('Copying folder "' . $sourceFolderIdentifier . '" faild.', 1408550558);
        }

        return true;
    }

    /**
     * Checks if a file exists.
     */
    public function fileExists(string $fileIdentifier): bool
    {
        return $this->ftpClient->fileExists($fileIdentifier);
    }

    /**
     * Checks if a file inside a folder exists
     */
    public function fileExistsInFolder(string $fileName, string $folderIdentifier): bool
    {
        return $this->fileExists($folderIdentifier . $fileName);
    }

    /**
     * Returns information about a file.
     *
     * @param array $propertiesToExtract Array of properties which are be extracted. If empty all will be extracted
     */
    public function getFileInfoByIdentifier(string $fileIdentifier, array $propertiesToExtract = []): array
    {
        $folderIdentifier = $this->getParentFolderIdentifierOfIdentifier($fileIdentifier);

        if (isset($this->directoryCache[$folderIdentifier][$fileIdentifier]) === false) {
            // If not found try to load again.
            $fileName = $this->getNameFromIdentifier($fileIdentifier);
            $this->fetchDirectoryList($folderIdentifier, true);

            if (isset($this->directoryCache[$folderIdentifier][$fileIdentifier]) === false) {
                $this->directoryCache[$folderIdentifier][$fileIdentifier] = [];
            }
        }

        $returnValues = [];
        if (count($propertiesToExtract) > 0) {
            foreach ($propertiesToExtract as $property) {
                if ($this->directoryCache[$folderIdentifier][$fileIdentifier][$property]) {
                    array_push($returnValues, $this->directoryCache[$folderIdentifier][$fileIdentifier][$property]);
                }
            }

            return $returnValues;
        }

        // Return all
        return $this->directoryCache[$folderIdentifier][$fileIdentifier];
    }

    /**
     * Returns the identifier of a file inside the folder
     */
    public function getFileInFolder(string $fileName, string $folderIdentifier): string
    {
        return $folderIdentifier . $fileName;
    }

    /**
     * Returns a list of files inside the specified path
     *
     * @param array $filenameFilterCallbacks callbacks for filtering the items
     * @param string $sort Property name used to sort the items.
     *                     Among them may be: '' (empty, no sorting), name,
     *                     fileext, size, tstamp and rw.
     *                     If a driver does not support the given property, it
     *                     should fall back to "name".
     * @param bool $sortRev TRUE to indicate reverse sorting (last to first)
     */
    public function getFilesInFolder(string $folderIdentifier, int $start = 0, int $numberOfItems = 0, bool $recursive = false, array $filenameFilterCallbacks = [], string $sort = '', bool $sortRev = false): array
    {
        return $this->getDirectoryItemList($folderIdentifier, $start, $numberOfItems, $filenameFilterCallbacks, true, false, $recursive);
    }

    /**
     * Adds a file from the local server hard disk to a given path in TYPO3s
     * virtual file system. This assumes that the local file exists, so no
     * further check is done here! After a successful the original file must
     * not exist anymore.
     *
     * @param string $localFilePath (within PATH_site)
     * @param string $targetFolderIdentifier
     * @param string $newFileName optional, if not given original name is used
     * @param bool $removeOriginal if set the original file will be removed after successful operation
     * @throws FileDoesNotExistException
     * @throws ExistingTargetFolderException
     * @throws \RuntimeException
     */
    public function addFile(string $localFilePath, string $targetFolderIdentifier, string $newFileName = '', bool $removeOriginal = true): string
    {
        $newFileIdentifier = $targetFolderIdentifier . $this->sanitizeFileName($newFileName);

        try {
            $this->ftpClient->uploadFile($newFileIdentifier, $localFilePath);
        } catch (ResourceDoesNotExistException) {
            throw new FileDoesNotExistException('Source file "' . $localFilePath . '" not exists', 1408550561);
        } catch (ExistingResourceException) {
            throw new ExistingTargetFolderException('Target file "' . $newFileIdentifier . '" already exists', 1408550562);
        } catch (FTPConnectionException) {
            throw new \RuntimeException('Adding file "' . $newFileIdentifier . '" faild.', 1408550563);
        }

        if ($removeOriginal) {
            unlink($localFilePath);
        }

        $this->fetchDirectoryList($targetFolderIdentifier, true);

        return $newFileIdentifier;
    }

    /**
     * Creates a new (empty) file and returns the identifier.
     *
     * @throws InvalidFileNameException
     * @throws ExistingTargetFileNameException
     * @throws \RuntimeException
     */
    public function createFile(string $fileName, string $parentFolderIdentifier): string
    {
        if ($this->isValidFilename($fileName) === false) {
            throw new InvalidFileNameException('Invalid characters in fileName "' . $fileName . '"', 1408550564);
        }

        $fileIdentifier = $parentFolderIdentifier . $this->sanitizeFileName($fileName);

        try {
            $this->ftpClient->createFile($fileIdentifier);
        } catch (ExistingResourceException) {
            throw new ExistingTargetFileNameException('File "' . $fileIdentifier . '" already exists', 1408550565);
        } catch (FTPConnectionException) {
            throw new \RuntimeException('Creating file "' . $fileIdentifier . '" faild.', 1408550566);
        }

        return $fileIdentifier;
    }

    /**
     * Renames a file in this storage.
     *
     * @param string $fileIdentifier
     * @param string $newName The target path (including the file name!)
     * @throws ExistingTargetFileNameException
     * @throws \RuntimeException if renaming the file failed
     */
    public function renameFile(string $fileIdentifier, string $newName): string
    {
        $newFileIdentifier = $this->getParentFolderIdentifierOfIdentifier($fileIdentifier) . $this->sanitizeFileName($newName);

        try {
            $this->ftpClient->renameFile($fileIdentifier, $newFileIdentifier);
        } catch (ExistingResourceException) {
            throw new ExistingTargetFileNameException('File "' . $fileIdentifier . '" already exists', 1408550567);
        } catch (FTPConnectionException) {
            throw new \RuntimeException('Renaming file "' . $fileIdentifier . '" faild.', 1408550568);
        }

        return $newFileIdentifier;
    }

    /**
     * Replaces the contents (and file-specific metadata) of a file object with a local file.
     *
     * @throws FileDoesNotExistException
     * @throws \RuntimeException
     */
    public function replaceFile(string $fileIdentifier, string $localFilePath): bool
    {
        try {
            $this->ftpClient->replaceFile($fileIdentifier, $localFilePath);
        } catch (ResourceDoesNotExistException) {
            throw new FileDoesNotExistException('Source file "' . $localFilePath . '" not exists', 1408550569);
        } catch (FTPConnectionException) {
            throw new \RuntimeException('Replacing file "' . $fileIdentifier . '" faild.', 1408550570);
        }

        return true;
    }

    /**
     * Removes a file from the filesystem. This does not check if the file is
     * still used or if it is a bad idea to delete it for some other reason
     * this has to be taken care of in the upper layers (e.g. the Storage)!
     *
     * @throws \RuntimeException
     */
    public function deleteFile(string $fileIdentifier): bool
    {
        try {
            $this->ftpClient->deleteFile($fileIdentifier);
        } catch (FTPConnectionException) {
            throw new \RuntimeException('Deleting file "' . $fileIdentifier . '" faild.', 1408550571);
        }

        return true;
    }

    /**
     * Sets the contents of a file to the specified value.
     *
     * @throws \RuntimeException
     * @return int The number of bytes written to the file
     */
    public function setFileContents(string $fileIdentifier, string $contents): int
    {
        try {
            $bytes = $this->ftpClient->setFileContents($fileIdentifier, $contents);
        } catch (Exception) {
            throw new \RuntimeException('Setting file contents of file "' . $fileIdentifier . '" faild.', 1408550572);
        }

        return $bytes;
    }

    /**
     * Returns the contents of a file. Beware that this requires to load the
     * complete file into memory and also may require fetching the file from an
     * external location. So this might be an expensive operation (both in terms
     * of processing resources and money) for large files.
     *
     * @throws \RuntimeException
     */
    public function getFileContents(string $fileIdentifier): string
    {
        try {
            $contents = $this->ftpClient->getFileContents($fileIdentifier);
        } catch (Exception) {
            throw new \RuntimeException('Setting file contents of file "' . $fileIdentifier . '" faild.', 1408550573);
        }

        return $contents;
    }

    /**
     * Returns a path to a local copy of a file for processing it. When changing the
     * file, you have to take care of replacing the current version yourself!
     *
     * @param string $fileIdentifier
     * @param bool $writable Set this to FALSE if you only need the file for read operations. This might speed up things,
     *                       e.g. by using a cached local version. Never modify the file if you have set this flag!
     * @throws FileDoesNotExistException
     * @throws \RuntimeException
     */
    public function getFileForLocalProcessing(string $fileIdentifier, bool $writable = true): string
    {
        $temporaryFile = $this->getTemporaryPathForFile($fileIdentifier);

        // Prevent creating thumbnails if file size greater than the defined in the extension configuration.
        if (($GLOBALS['TYPO3_REQUEST'] ?? null) instanceof ServerRequestInterface && ApplicationType::fromRequest($GLOBALS['TYPO3_REQUEST'])->isBackend() && $this->createThumbnailsUpToSize) {
            $fileInfo = $this->getFileInfoByIdentifier($fileIdentifier);
            if ($fileInfo['size'] > $this->createThumbnailsUpToSize) {
                copy($this->defaultThumbnail, $temporaryFile);

                return $temporaryFile;
            }
        }

        try {
            $this->ftpClient->downloadFile($fileIdentifier, $temporaryFile);
        } catch (ResourceDoesNotExistException) {
            throw new FileDoesNotExistException('Source file "' . $temporaryFile . '" not exists', 1408550574);
        } catch (FTPConnectionException) {
            throw new \RuntimeException('Copying file "' . $fileIdentifier . '" to temporary file faild.', 1408550575);
        }

        return $temporaryFile;
    }

    /**
     * Directly output the contents of the file to the output
     * buffer. Should not take care of header files or flushing
     * buffer before. Will be taken care of by the Storage.
     *
     * @param string $identifier
     *
     * @return void
     */
    public function dumpFileContents($identifier): void
    {
        echo $this->getFileContents($identifier);
    }

    /**
     * Moves a file *within* the current storage.
     * Note that this is only about an inner-storage move action,
     * where a file is just moved to another folder in the same storage.
     *
     * @throws \TYPO3\CMS\Core\Resource\Exception\ExistingTargetFileException
     * @throws \RuntimeException
     * @return array A map of old to new file identifiers
     */
    public function moveFileWithinStorage(string $fileIdentifier, string $targetFolderIdentifier, string $newFileName): string
    {
        $targetFileIdentifier = $this->canonicalizeAndCheckFileIdentifier($targetFolderIdentifier . $newFileName);

        try {
            $this->ftpClient->moveFile($fileIdentifier, $targetFileIdentifier);
        } catch (ExistingResourceException) {
            throw new ExistingTargetFileException('File "' . $targetFileIdentifier . '" already exists.', 1408550576);
        } catch (FTPConnectionException) {
            throw new \RuntimeException('Moving file "' . $fileIdentifier . '" faild.', 1408550577);
        }

        $this->fetchDirectoryList($this->getParentFolderIdentifierOfIdentifier($fileIdentifier), true);
        $this->fetchDirectoryList($targetFolderIdentifier, true);

        return $targetFileIdentifier;
    }

    /**
     * Copies a file *within* the current storage.
     * Note that this is only about an inner storage copy action,
     * where a file is just copied to another folder in the same storage.
     *
     * @throws FileDoesNotExistException
     * @throws \TYPO3\CMS\Core\Resource\Exception\ExistingTargetFileException
     * @throws \RuntimeException
     */
    public function copyFileWithinStorage(string $fileIdentifier, string $targetFolderIdentifier, string $fileName): string
    {
        $targetFileIdentifier = $targetFolderIdentifier . $fileName;

        try {
            $this->ftpClient->copyFile($fileIdentifier, $targetFileIdentifier);
        } catch (ResourceDoesNotExistException) {
            throw new FileDoesNotExistException('Source file "' . $fileIdentifier . '" not exists', 1408550578);
        } catch (ExistingResourceException) {
            throw new ExistingTargetFileException('Target file "' . $targetFileIdentifier . '" already exists', 1408550579);
        } catch (FTPConnectionException) {
            throw new \RuntimeException('Copying file "' . $fileIdentifier . '" faild.', 1408550580);
        }

        return $targetFileIdentifier;
    }

    /**
     * Returns the permissions of a file/folder as an array (keys r, w) of boolean flags
     *
     * @throws \RuntimeException
     */
    public function getPermissions(string $identifier): array
    {
        if (str_ends_with($identifier, '/')) {
            $resourceInfo = $this->getFolderInfoByIdentifier($identifier);
        } else {
            $resourceInfo = $this->getFileInfoByIdentifier($identifier);
        }

        if (isset($resourceInfo['mode']) && is_array($resourceInfo['mode'])) {
            return $resourceInfo['mode'];
        }

        return ['r' => true, 'w' => true];
    }

    /**
     * Creates a hash for a file.
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function hash(string $fileIdentifier, string $hashAlgorithm): string
    {
        if (!in_array($hashAlgorithm, $this->supportedHashAlgorithms)) {
            throw new \InvalidArgumentException('Hash algorithm "' . $hashAlgorithm . '" is not supported.', 1408550581);
        }

        if ($this->remoteService) {
            $request = ['action' => 'hashFile', 'parameters' => ['fileIdentifier' => $fileIdentifier, 'hashAlgorithm' => $hashAlgorithm]];
            $response = $this->sendRemoteService($request);

            if ($response['result'] === false) {
                throw new \RuntimeException($response['message'], 1408550682);
            }

            $hash = $response['hash'];
        } else {
            $temporaryFile = $this->getFileForLocalProcessing($fileIdentifier);

            $hash = match ($hashAlgorithm) {
                'sha1' => sha1_file($temporaryFile),
                'md5' => md5_file($temporaryFile),
                default => throw new \RuntimeException('Hash algorithm ' . $hashAlgorithm . ' is not implemented.', 1408550582),
            };
        }

        return $hash;
    }

    /**
     * Returns a string where any character not matching [.a-zA-Z0-9_-] is
     * substituted by '_'
     * Trailing dots are removed
     *
     * Previously in \TYPO3\CMS\Core\Utility\File\BasicFileUtility::cleanFileName()
     *
     * @param string $fileName Input string, typically the body of a fileName
     * @param string $charset Charset of the a fileName (defaults to current charset; depending on context)
     * @throws InvalidFileNameException
     * @return string Output string with any characters not matching [.a-zA-Z0-9_-] is substituted by '_' and trailing dots removed
     */
    public function sanitizeFileName($fileName, $charset = ''): string
    {
        // Handle UTF-8 characters
        if ($GLOBALS['TYPO3_CONF_VARS']['SYS']['UTF8filesystem']) {
            // Allow ".", "-", 0-9, a-z, A-Z and everything beyond U+C0 (latin capital letter a with grave)
            $cleanFileName = preg_replace('/[' . self::UNSAFE_FILENAME_CHARACTER_EXPRESSION . ']/u', '_', trim($fileName));
        } else {
            // Define character set
            if (!$charset) {
                // Breaking #73794: Charset is now always utf-8
                $charset = 'utf-8';
            }
            // If a charset was found, convert fileName
            if ($charset) {
                /** @var CharsetConverter $charsetConverter */
                $charsetConverter = GeneralUtility::makeInstance(CharsetConverter::class);
                $fileName = $charsetConverter->conv($fileName, $charset, 'utf-8');
            }
            // Replace unwanted characters by underscores
            $cleanFileName = preg_replace('/[' . self::UNSAFE_FILENAME_CHARACTER_EXPRESSION . '\\xC0-\\xFF]/', '_', trim($fileName));
        }
        // Strip trailing dots and return
        $cleanFileName = preg_replace('/\\.*$/', '', (string)$cleanFileName);
        if (!$cleanFileName) {
            throw new InvalidFileNameException(
                'File name ' . $cleanFileName . ' is invalid.',
                1320288991
            );
        }

        return $cleanFileName;
    }

    /**
     * Generic wrapper for extracting a list of items from a path.
     *
     * @param string $folderIdentifier
     * @param int $start The position to start the listing; if not set, start from the beginning
     * @param int $numberOfItems The number of items to list; if set to zero, all items are returned
     * @param array $filterMethods The filter methods used to filter the directory items
     * @param bool $includeFiles
     * @param bool $includeDirs
     * @param bool $recursive
     *
     * @throws \InvalidArgumentException
     * @return array
     */
    protected function getDirectoryItemList($folderIdentifier, $start = 0, $numberOfItems = 0, array $filterMethods = [], $includeFiles = true, $includeDirs = true, $recursive = false)
    {
        if ($this->folderExists($folderIdentifier) === false) {
            throw new \InvalidArgumentException('Cannot list items in directory ' . $folderIdentifier . ' - does not exist or is no directory', 1314349666);
        }

        $this->fetchDirectoryList($folderIdentifier);

        if ($start > 0) {
            $start--;
        }

        $iterator = new \ArrayIterator($this->directoryCache[$folderIdentifier]);
        if ($iterator->count() === 0) {
            return [];
        }
        $iterator->seek($start);

        // $c is the counter for how many items we still have to fetch (-1 is unlimited)
        $c = $numberOfItems > 0 ? $numberOfItems : - 1;
        $items = [];
        while ($iterator->valid() && ($numberOfItems === 0 || $c > 0)) {
            $iteratorItem = $iterator->current();
            $identifier = $iterator->key();

            // go on to the next iterator item now as we might skip this one early
            $iterator->next();

            if ($includeDirs === false && $iteratorItem['isDirectory'] || $includeFiles === false && $iteratorItem['isDirectory'] === false) {
                continue;
            }

            if ($this->applyFilterMethodsToDirectoryItem($filterMethods, $iteratorItem['name'], $identifier, $this->getParentFolderIdentifierOfIdentifier($identifier)) === false) {
                continue;
            }

            $items[$identifier] = $identifier;
            // Decrement item counter to make sure we only return $numberOfItems
            // we cannot do this earlier in the method (unlike moving the iterator forward) because we only add the
            // item here
            --$c;
        }

        return $items;
    }

    /**
     * Applies a set of filter methods to a file name to find out if it should be used or not. This is e.g. used by
     * directory listings.
     *
     * @param array $filterMethods The filter methods to use
     * @param string $itemName
     * @param string $itemIdentifier
     * @param string $parentIdentifier
     * @throws \RuntimeException
     * @return bool
     */
    protected function applyFilterMethodsToDirectoryItem(array $filterMethods, $itemName, $itemIdentifier, $parentIdentifier)
    {
        foreach ($filterMethods as $filter) {
            if (is_array($filter)) {
                $result = call_user_func($filter, $itemName, $itemIdentifier, $parentIdentifier, [], $this);
                // We have to use -1 as the â€ždon't includeâ€œ return value, as call_user_func() will return FALSE
                // If calling the method succeeded and thus we can't use that as a return value.
                if ($result === -1) {
                    return false;
                } elseif ($result === false) {
                    throw new \RuntimeException('Could not apply file/folder name filter ' . $filter[0] . '::' . $filter[1], 5865295059);
                }
            }
        }

        return true;
    }

    /**
     * This function scans a ftp_rawlist line string and returns its parts.
     *
     * @param string $folderIdentifier
     * @param bool $resetCache
     * @throws InvalidAttributeException
     * @throws InvalidConfigurationException
     * @throws FTPConnectionException
     * @return array
     */
    protected function fetchDirectoryList(string $folderIdentifier, bool $resetCache = false): array
    {
        if ($resetCache === false && isset($this->directoryCache[$folderIdentifier]) && is_array($this->directoryCache[$folderIdentifier])) {
            return $this->directoryCache[$folderIdentifier];
        }
        $this->directoryCache[$folderIdentifier] = [];

        return $this->ftpClient->fetchDirectoryList($folderIdentifier, $this->fetchDirectoryList_itemCallback(...));
    }

    /**
     * Callback function of line parsing. Adds additional file information.
     *
     * @param array $resourceInfo
     * @param FTP $parentObject
     * @return void
     */
    public function fetchDirectoryList_itemCallback($resourceInfo, $parentObject): void
    {
        if ($resourceInfo['isDirectory']) {
            $identifier = $this->canonicalizeAndCheckFolderIdentifier($resourceInfo['path'] . $resourceInfo['name']);
        } else {
            $identifier = $this->canonicalizeAndCheckFileIdentifier($resourceInfo['path'] . $resourceInfo['name']);
        }

        $resourceInfo['storage'] = $this->storageUid;
        $resourceInfo['identifier'] = $identifier;
        $resourceInfo['identifier_hash'] = $this->hashIdentifier($identifier);
        $resourceInfo['folder_hash'] = $this->hashIdentifier($this->getParentFolderIdentifierOfIdentifier($identifier));
        $resourceInfo['ctime'] = 0;
        $resourceInfo['atime'] = 0;
        $resourceInfo['mode'] = ['r' => (@$resourceInfo['mode'][0] == 'r'), 'w' => (@$resourceInfo['mode'][1] == 'w')];

        if ($this->exactModificationTime) {
            try {
                $resourceInfo['mtime'] = $this->ftpClient->getModificationTime($identifier);
            } catch (FTPConnectionException) {
                // Ignore on failure.
            }
        }

        $this->directoryCache[$resourceInfo['path']][$identifier] = $resourceInfo;
    }

    /**
     * Creates a map of old and new file/folder identifiers after renaming or
     * moving a folder. The old identifier is used as the key, the new one as the value.
     *
     * @param string $oldIdentifier
     * @param string $newIdentifier
     * @param array $identifierMap
     * @throws \RuntimeException
     * @return array
     */
    protected function createIdentifierMap($oldIdentifier, $newIdentifier, &$identifierMap = [])
    {
        if ($this->ftpClient->directoryExists($oldIdentifier) === false) {
            $identifierMap[$oldIdentifier] = $newIdentifier;

            return $identifierMap;
        }

        // If is a directory, make valid identifier.
        $oldIdentifier = rtrim($oldIdentifier, '/') . '/';
        $newIdentifier = rtrim($newIdentifier, '/') . '/';
        $identifierMap[$oldIdentifier] = $newIdentifier;

        try {
            $directoryList = $this->ftpClient->fetchDirectoryList($oldIdentifier);
        } catch (Exception) {
            throw new \RuntimeException('Fetching list of directory "' . $oldIdentifier . '" faild.', 1408550584);
        }

        foreach ($directoryList as &$resourceInfo) {
            $this->createIdentifierMap($oldIdentifier . $resourceInfo['name'], $newIdentifier . $resourceInfo['name'], $identifierMap);
        }

        return $identifierMap;
    }

    /**
     * Returns the absolute path of the FTP remote directory or file.
     *
     * @param string $identifier
     * @return array
     */
    protected function getAbsolutePath($identifier)
    {
        return $this->basePath . '/' . ltrim($identifier, '/');
    }

    /**
     * Returns the cache identifier for a given path.
     *
     * @param string $identifier
     * @return string
     */
    protected function getNameFromIdentifier($identifier)
    {
        return trim(PathUtility::basename($identifier), '/');
    }

    /**
     * Communication function for the remote service.
     *
     * @param array $request
     * @param bool $createOnFail
     * @throws \RuntimeException
     * @return array
     */
    protected function sendRemoteService($request = [], $createOnFail = true)
    {
        $request['encryptionKey'] = $this->remoteServiceEncryptionKey;
        $requestUrl = $this->getPublicUrl($this->remoteServiceFileName) . '?' . http_build_query($request);
        $headers = count($this->remoteServiceAdditionalHeaders) ? $this->remoteServiceAdditionalHeaders : false;

        $response = GeneralUtility::getUrl($requestUrl);
        $response = @json_decode($response, true);

        if (is_array($response) === false && isset($response['result']) === false) {
            // Define default error message before.
            $response = ['result' => false, 'message' => 'Remote service communication faild.'];
            // If fails, renew the remote service and try again.
            if ($createOnFail) {
                $remoteServiceContents = file_get_contents(GeneralUtility::getFileAbsFileName('EXT:fal_ftp/Resources/Private/Script/.FalFtpRemoteService.php'));
                $remoteServiceContents = str_replace('###ENCRYPTION_KEY###', $this->remoteServiceEncryptionKey, $remoteServiceContents);
                $this->ftpClient->setFileContents('/.FalFtpRemoteService.php', $remoteServiceContents);
                $response = $this->sendRemoteService($request, false);
            }
        }

        return $response;
    }

    /**
     * Add flash message to message queue.
     *
     * @param string $message
     * @param int $severity
     * @return void
     */
    protected function addFlashMessage($message, $severity = \TYPO3\CMS\Core\Type\ContextualFeedbackSeverity::ERROR)
    {
        if (PHP_SAPI === 'cli') {
            return;
        }
        $flashMessage = GeneralUtility::makeInstance(
            \TYPO3\CMS\Core\Messaging\FlashMessage::class,
            $message,
            '',
            $severity,
            true
        );
        /** @var $defaultFlashMessageQueue \TYPO3\CMS\Core\Messaging\FlashMessageQueue */
        $defaultFlashMessageQueue = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Messaging\FlashMessageService::class)->getMessageQueueByIdentifier();
        $defaultFlashMessageQueue->enqueue($flashMessage);
    }

    /**
     * Gets the charset conversion object.
     *
     * @return CharsetConverter
     */
    protected function getCharsetConversion()
    {
        if (!isset($this->charsetConversion)) {
            if (ApplicationType::fromRequest($GLOBALS['TYPO3_REQUEST'])->isFrontend()) {
                $this->charsetConversion = $GLOBALS['TSFE']->csConvObj;
            } elseif (is_object($GLOBALS['LANG'])) {
                // BE assumed:
                $this->charsetConversion = GeneralUtility::makeInstance(CharsetConverter::class);
            } else {
                // The object may not exist yet, so we need to create it now. Happens in the Install Tool for example.
                $this->charsetConversion = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Charset\CharsetConverter::class);
            }
        }

        return $this->charsetConversion;
    }

    /**
     * Returns a temporary path for a given file, including the file extension.
     *
     * @param string $fileIdentifier
     * @return string
     */
    protected function getTemporaryPathForFile(string $fileIdentifier): string
    {
        // Sometimes a temporary file already exist. In this case use the file which was downloaded already.
        $hash = sha1($this->storageUid . ':' . $fileIdentifier);

        return $this->temporaryFileStack[$hash] ?? ($this->temporaryFileStack[$hash] = parent::getTemporaryPathForFile($fileIdentifier));
        //\TYPO3\CMS\Extbase\Utility\DebuggerUtility::var_dump($this, 'getFileInfoByIdentifier');
        //\TYPO3\CMS\Core\Utility\DebugUtility::debug(__FUNCTION__, 'Method');
    }
}
