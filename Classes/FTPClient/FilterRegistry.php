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
use AdGrafik\FalFtp\FTPClient\Exception\InvalidConfigurationException;
use AdGrafik\FalFtp\FTPClient\Filter\FilterInterface;
use TYPO3\CMS\Core\SingletonInterface;

class FilterRegistry implements SingletonInterface
{
    /**
     * @var array<FilterInterface>
     */
    protected $filter;

    /**
     * Initialize object.
     *
     * @return void
     */
    public function initialize(): void
    {
        $this->filter = [];
    }

    /**
     * Register filter classes.
     *
     * @throws InvalidConfigurationException
     * @return FilterRegistry
     */
    public function registerFilter(mixed $filters)
    {
        if (is_array($filters) === false) {
            $filters = [$filters];
        }
        foreach ($filters as &$filter) {
            $this->filter[] = $filter;
        }

        return $this;
    }

    /**
     * Has filter
     *
     * @return bool
     */
    public function hasFilter()
    {
        return $this->filter !== null;
    }

    /**
     * Set filter
     *
     * @param array $filter
     * @return FilterRegistry
     */
    public function setFilter(array $filter)
    {
        $this->filter = $filter;

        return $this;
    }

    /**
     * Get filter
     *
     * @return array
     */
    public function getFilter()
    {
        return $this->filter;
    }
}
