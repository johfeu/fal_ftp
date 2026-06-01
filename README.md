# TYPO3 FAL FTP Driver

Provides a FTP and FTP-SSL driver for the TYPO3 File Abstraction Layer (FAL), allowing you to manage files on remote FTP servers directly through the TYPO3 Filelist and other FAL-integrated components.

## Features

- **FTP Driver**: Standard FTP connection support.
- **FTP-SSL Driver**: Secure FTP (FTPS) connection support.
- **Multiple Parsers**: Includes parsers for various FTP server types (AS400, Netware, Titan, Windows, etc.).
- **FAL Integration**: Seamlessly integrates with TYPO3's File Abstraction Layer.
- **Extensible**: Custom filters and parsers can be registered.

## Requirements

- PHP 8.2, 8.3, 8.4 or 8.5
- PHP extension `ext-ftp`
- TYPO3 13.4 or 14.3

## Installation

Install via composer:

```bash
composer require adgrafik/fal-ftp
```

After installation, you can create a new File Storage in the TYPO3 Backend and select "FTP" or "FTP-SSL" as the driver.

## History

The project was started in August 2014 by Arno Dudek to provide FTP capabilities to the then-new TYPO3 File Abstraction Layer. Over the years, it has been maintained and updated by the community to support newer TYPO3 and PHP versions.

## Authors

This project has been made possible by many contributors over the years:

- **Arno Dudek** (Initial Author)
- Nicole Cordes
- Jonas Temmen
- Remo Schneider
- Johannes Feustel
- Helmut Hummel
- niho
- Alexander Schnitzler
- and others

## License

This project is licensed under the GPL-2.0-or-later License. See the `LICENSE` file for details.
