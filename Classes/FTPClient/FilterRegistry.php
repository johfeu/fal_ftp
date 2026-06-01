<?php

namespace AdGrafik\FalFtp\FTPClient;

/***************************************************************
 * Copyright notice
 *
 * (c) 2014 Arno Dudek <webmaster@adgrafik.at>
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
use AdGrafik\FalFtp\FTPClient\Filter\FilterInterface;
use TYPO3\CMS\Core\SingletonInterface;

readonly class FilterRegistry implements SingletonInterface
{
    /**
     * @var FilterInterface[]
     */
    public array $filters;

    public function __construct(iterable $filters)
    {
        $this->filters = iterator_to_array($filters);
    }
}
