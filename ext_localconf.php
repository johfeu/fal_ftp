<?php
if (!defined ('TYPO3')) die ('Access denied.');

$registerDriver = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Resource\Driver\DriverRegistry::class);
$registerDriver->registerDriverClass(
	\AdGrafik\FalFtp\Driver\FTPDriver::class,
	'FTP',
	'FTP filesystem',
	'FILE:EXT:fal_ftp/Configuration/FlexForm/FTPDriver.xml'
);
$registerDriver->registerDriverClass(
	\AdGrafik\FalFtp\Driver\FTPSDriver::class,
	'FTPS',
	'FTP-SSL filesystem',
	'FILE:EXT:fal_ftp/Configuration/FlexForm/FTPDriver.xml'
);

?>