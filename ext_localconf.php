<?php

declare(strict_types=1);

defined('TYPO3') or exit('Access to file "' . basename(__FILE__) . '" denied.');

$registerDriver = TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(TYPO3\CMS\Core\Resource\Driver\DriverRegistry::class);
$registerDriver->registerDriverClass(
    AdGrafik\FalFtp\Driver\FTPDriver::class,
    'FTP',
    'FTP filesystem',
    'FILE:EXT:fal_ftp/Configuration/FlexForm/FTPDriver.xml'
);
$registerDriver->registerDriverClass(
    AdGrafik\FalFtp\Driver\FTPSDriver::class,
    'FTPS',
    'FTP-SSL filesystem',
    'FILE:EXT:fal_ftp/Configuration/FlexForm/FTPDriver.xml'
);
