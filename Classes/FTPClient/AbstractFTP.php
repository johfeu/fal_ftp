<?php
namespace AdGrafik\FalFtp\FTPClient;

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

use \TYPO3\CMS\Core\Utility\PathUtility;
use \TYPO3\CMS\Core\Utility\GeneralUtility;
use \AdGrafik\FalFtp\FTPClient\FTPInterface;

/**
 * Abstract driver for FTP clients.
 *
 * @author Arno Dudek <webmaster@adgrafik.at>
 * @author Nicole Cordes <typo3@cordes.co>
 */
abstract class AbstractFTP implements FTPInterface {

	/**
	 * @var resource $stream
	 */
	protected $stream;

	/**
  * @var ParserRegistry $parserRegistry
  */
 protected $parserRegistry;

	/**
  * @var ParserRegistry $parserRegistry
  */
 protected $filterRegistry;

	/**
  * Get parserRegistry
  *
  * @return ParserRegistry
  */
 public function getParserRegistry() {
		return $this->parserRegistry;
	}

	/**
  * Get filterRegistry
  *
  * @return ParserRegistry
  */
 public function getFilterRegistry() {
		return $this->filterRegistry;
	}

	/**
	 * Get stream
	 *
	 * @return resource
	 */
	public function getStream() {
		$this->connect();
		return $this->stream;
	}

	/**
	 * Returns the mime type of given file extension.
	 *
	 * @param string $fileName
	 * @return string
	 */
	public function getMimeType($fileName) {

		$extension = strtolower(PathUtility::pathinfo($fileName, PATHINFO_EXTENSION));

		$mimeType = match ($extension) {
      'ai', 'eps', 'ps' => 'application/postscript',
      'aif', 'aifc', 'aiff' => 'audio/x-aiff',
      'asc', 'txt' => 'text/plain',
      'atom' => 'application/atom+xml',
      'au', 'snd' => 'audio/basic',
      'avi' => 'video/x-msvideo',
      'bcpio' => 'application/x-bcpio',
      'bin', 'class', 'dll', 'dmg', 'dms', 'exe', 'lha', 'lzh', 'so' => 'application/octet-stream',
      'bmp' => 'image/bmp',
      'cdf', 'nc' => 'application/x-netcdf',
      'cgm' => 'image/cgm',
      'cpio' => 'application/x-cpio',
      'cpt' => 'application/mac-compactpro',
      'csh' => 'application/x-csh',
      'css' => 'text/css',
      'dcr', 'dir', 'dxr' => 'application/x-director',
      'dif', 'dv' => 'video/x-dv',
      'djv', 'djvu' => 'image/vnd.djvu',
      'doc' => 'application/msword',
      'dtd' => 'application/xml-dtd',
      'dvi' => 'application/x-dvi',
      'etx' => 'text/x-setext',
      'ez' => 'application/andrew-inset',
      'gif' => 'image/gif',
      'gram' => 'application/srgs',
      'grxml' => 'application/srgs+xml',
      'gtar' => 'application/x-gtar',
      'hdf' => 'application/x-hdf',
      'hqx' => 'application/mac-binhex40',
      'htm', 'html' => 'text/html',
      'ice' => 'x-conference/x-cooltalk',
      'ico' => 'image/x-icon',
      'ics', 'ifb' => 'text/calendar',
      'ief' => 'image/ief',
      'iges', 'igs' => 'model/iges',
      'jnlp' => 'application/x-java-jnlp-file',
      'jp2' => 'image/jp2',
      'jpe', 'jpeg', 'jpg' => 'image/jpeg',
      'js' => 'application/x-javascript',
      'kar', 'mid', 'midi' => 'audio/midi',
      'latex' => 'application/x-latex',
      'm3u' => 'audio/x-mpegurl',
      'm4a', 'm4b', 'm4p' => 'audio/mp4a-latm',
      'm4u', 'mxu' => 'video/vnd.mpegurl',
      'm4v' => 'video/x-m4v',
      'mac', 'pnt', 'pntg' => 'image/x-macpaint',
      'man' => 'application/x-troff-man',
      'mathml' => 'application/mathml+xml',
      'me' => 'application/x-troff-me',
      'mesh', 'msh', 'silo' => 'model/mesh',
      'mif' => 'application/vnd.mif',
      'mov', 'qt' => 'video/quicktime',
      'movie' => 'video/x-sgi-movie',
      'mp2', 'mp3' => 'audio/mpeg',
      'mpga' => 'audio/mpeg',
      'mp4' => 'video/mp4',
      'mpe', 'mpeg', 'mpg' => 'video/mpeg',
      'ms' => 'application/x-troff-ms',
      'oda' => 'application/oda',
      'ogg' => 'application/ogg',
      'pbm' => 'image/x-portable-bitmap',
      'pct', 'pic', 'pict' => 'image/pict',
      'pdb' => 'chemical/x-pdb',
      'pdf' => 'application/pdf',
      'pgm' => 'image/x-portable-graymap',
      'pgn' => 'application/x-chess-pgn',
      'png' => 'image/png',
      'pnm' => 'image/x-portable-anymap',
      'ppm' => 'image/x-portable-pixmap',
      'ppt' => 'application/vnd.ms-powerpoint',
      'qti', 'qtif' => 'image/x-quicktime',
      'ra', 'ram' => 'audio/x-pn-realaudio',
      'ras' => 'image/x-cmu-raster',
      'rdf' => 'application/rdf+xml',
      'rgb' => 'image/x-rgb',
      'rm' => 'application/vnd.rn-realmedia',
      'roff', 't', 'tr' => 'application/x-troff',
      'rtf' => 'text/rtf',
      'rtx' => 'text/richtext',
      'sgm', 'sgml' => 'text/sgml',
      'sh' => 'application/x-sh',
      'shar' => 'application/x-shar',
      'sit' => 'application/x-stuffit',
      'skd', 'skm', 'skp', 'skt' => 'application/x-koan',
      'smi', 'smil' => 'application/smil',
      'spl' => 'application/x-futuresplash',
      'src' => 'application/x-wais-source',
      'sv4cpio' => 'application/x-sv4cpio',
      'sv4crc' => 'application/x-sv4crc',
      'svg' => 'image/svg+xml',
      'swf' => 'application/x-shockwave-flash',
      'tar' => 'application/x-tar',
      'tcl' => 'application/x-tcl',
      'tex' => 'application/x-tex',
      'texi', 'texinfo' => 'application/x-texinfo',
      'tif', 'tiff' => 'image/tiff',
      'tsv' => 'text/tab-separated-values',
      'ustar' => 'application/x-ustar',
      'vcd' => 'application/x-cdlink',
      'vrml', 'wrl' => 'model/vrml',
      'vxml' => 'application/voicexml+xml',
      'wav' => 'audio/x-wav',
      'wbmp' => 'image/vnd.wap.wbmp',
      'wbmxl' => 'application/vnd.wap.wbxml',
      'wml' => 'text/vnd.wap.wml',
      'wmlc' => 'application/vnd.wap.wmlc',
      'wmls' => 'text/vnd.wap.wmlscript',
      'wmlsc' => 'application/vnd.wap.wmlscriptc',
      'xbm' => 'image/x-xbitmap',
      'xht', 'xhtml' => 'application/xhtml+xml',
      'xls' => 'application/vnd.ms-excel',
      'xml', 'xsl' => 'application/xml',
      'xpm' => 'image/x-xpixmap',
      'xslt' => 'application/xslt+xml',
      'xul' => 'application/vnd.mozilla.xul+xml',
      'xwd' => 'image/x-xwindowdump',
      'xyz' => 'chemical/x-xyz',
      'zip' => 'application/zip',
      default => 'application/octet-stream',
  };

		return $mimeType;
	}

	/**
	 * Returns the absolute path of the FTP remote directory or file.
	 *
	 * @param string $relativeDirectoryOrFilePath
	 * @return string
	 */
	protected function getAbsolutePath($relativeDirectoryOrFilePath) {
		return $this->basePath . $relativeDirectoryOrFilePath;
	}

	/**
	 * Returns the identifier of the folder the file resides in
	 *
	 * @param string $directoryOrFile
	 * @return mixed
	 */
	protected function getParentDirectory($directoryOrFile) {
		$parentDirectory = PathUtility::dirname($directoryOrFile);
		if ($parentDirectory === '/') {
			return $parentDirectory;
		}
		return $parentDirectory . '/';
	}

	/**
	 * Returns the identifier of the folder the file resides in
	 *
	 * @param string $directoryOrFile
	 * @return mixed
	 */
	protected function getResourceName($directoryOrFile) {
		return trim(PathUtility::basename($directoryOrFile), '/');
	}

}

?>
