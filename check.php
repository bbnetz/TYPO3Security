#!/usr/bin/php
<?php
/**
 * TYPO3Security Class
 * By Bastian Bringenberg <mail@bastian-bringenberg.de>
 *
 * #########
 * # USAGE #
 * #########
 *
 * See Readme File
 *
 * ###########
 * # Licence #
 * ###########
 *
 * See License File
 *
 * ##############
 * # Repository #
 * ##############
 *
 * Fork me on GitHub
 * https://github.com/bbnetz/TYPO3Security
 *
 *
 */

/**
 * Class TYPO3Security
 * @author Bastian Bringenberg <mail@bastian-bringenberg.de>
 * @link https://github.com/bbnetz/TYPO3Security
 *
 * @todo ignoreExtension perParam
 */
class TYPO3Security {

	/**
	 * @var string $extensionXmlFile temporary file for extensions.xml
	 */
	protected $extensionsXmlFile = '/tmp/t3xutils.extensions.temp.xml';

	/**
	 * @var object $xml the XML Object with the extensions
	 */
	protected $xml = null;

	/**
	 * @var string $path the path where the TYPO3 instances are hidden
	 */
	protected $path = '/var/www/';

	/**
	 * @var int $depth the depth to lock for TYPO3 instances
	 */
	protected $depth = 2;

	/**
	 * @var array<'extensionName' => array()> $insecureExtensions containing all insecureExtensions
	 */
	protected $insecureExtensions = array();

	/**
	 * @var array<'extensionName' => versionNumber> $inewestExtensions HelperArray to proxy the newest version of an extension
	 */
	protected $newestExtensions = array();

	/**
	 * @var array $inTer<'extensionName' => boolean> HelperArray to proxy if extension is in TER
	 */
	protected $inTer = array();

	/**
	 * @var array $ignoreExtensions<'extensionName' => array('versionNumber', ...)>
	 */
	protected $ignoreExtensions = array();

	/**
	 * @var boolean $searchInsecure if enabled will search all insecureExtensions
	 */
	protected $searchInsecure = false;

	/**
	 * @var boolean $searchOutdated if enabled will search all outdatedExtensions
	 */
	protected $searchOutdated = false;

	/**
	 * @var boolean $warnModified if enabled will warn modifiedExtensions
	 */
	protected $warnModified = false;

	/**
	 * @var boolean $ignoreModified if enabled will ignore all modifiedExtensions in insecureSearch and outdatedSearch
	 */
	protected $ignoreModified = false;

	/**
	 * @var boolean $checkModificationOnlyFoundInTer if enabled will check if extension is in TER before warning modified
	 */
	protected $checkModificationOnlyFoundInTer = false;

	/**
	 * function Constructor
	 *
	 * @return TYPO3Security
	 */
	public function __construct() {
		$ops = getopt('',
			array(
				'',
				'path:',
				'pathLevel:',
				'searchOutdated::',
				'searchInsecure::',
				'ignoreModified::',
				'warnModified::',
				'checkModificationOnlyFoundInTer::',
				'ignoreExtensions::'
			)
		);
		if(!isset($ops['path']))
			die('No Path entered. Please read README file.');
		if(!isset($ops['pathLevel']))
			die('No pathLevel entered. Please read README file.');
		$this->path = realpath($ops['path']);
		$this->depth = intval($ops['pathLevel']);

		if(isset($ops['searchOutdated']))
			$this->searchOutdated = true;
		if(isset($ops['searchInsecure']))
			$this->searchInsecure = true;
		if(isset($ops['ignoreModified']))
			$this->ignoreModified = true;
		if(isset($ops['warnModified']))
			$this->warnModified = true;
		if(isset($ops['checkModificationOnlyFoundInTer']))
			$this->checkModificationOnlyFoundInTer = true;
		if(isset($ops['ignoreExtensions']))
			$this->ignoreExtensions = $this->fetchIgnoreExtensions($ops['ignoreExtensions']);
	}

	/**
	 * function start
	 * doing all the work for a run
	 *
	 * @return void
	 */
	public function start() {
		$this->getExtensionXml();
		$founds = $this->getTYPO3Instances();
		if($this->searchInsecure) {
			$this->fetchInsecureExtensions();
		 	$this->getInsecureExtension($founds);
		}
		if($this->searchOutdated) {
			$this->getOutDatedExtensions($founds);
		}
	}

	/**
	 * function getExtensionXml
	 * Downloading TER, creating XML Object and removing tmp-file
	 *
	 * @return void
	 */
	protected function getExtensionXml() {
		$url = 'http://typo3.org/fileadmin/ter/extensions.xml.gz';
		exec('wget "' . $url . '" -q -O - | gunzip > ' . $this->extensionsXmlFile);
		$doc = new DOMDocument();
		$doc->loadXML(file_get_contents($this->extensionsXmlFile));
		$this->xml = new DOMXpath($doc);
		unlink($this->extensionsXmlFile);
	}

	/**
	 * function fetchInsecureExtensions
	 * Collecting all the insecureExtensions and Versions
	 *
	 * @return void
	 */
	protected function fetchInsecureExtensions() {
		$insecureExtension = $this->xml->query('//extension/version[reviewstate=-1]/..');
		$return = array();
		for($i = 0; $i < $insecureExtension->length; $i++) {
			$tmp = $this->fetchInsecureExtension($insecureExtension->item($i));
			$return[$tmp['name']] = $tmp['versions'];
		}
		$this->insecureExtensions += $return;
	}

	/**
	 * function fetchInsecureExtension
	 * Combining a single insecure Extension to get extensionName and all insecureVersions
	 *
	 * @param DomElement $item the singleItemToCheck for insecureVersions and Name
	 * @return array('name' => name, 'versions' => versions)
	 */
	protected function fetchInsecureExtension($item) {
		$return['name'] = $item->getAttribute('extensionkey');
		$return['versions'] = array();
		$versions = $this->xml->query('//extension[@extensionkey=\''.$return['name'].'\']/version[reviewstate=-1]', $item);
		for($i = 0; $i < $versions->length; $i++) {
			$return['versions'][] = array($this->calcVersion($versions->item($i)->getAttribute('version')), $versions->item($i)->getAttribute('version'));
		}
		return $return;
	}

	/**
	 * function calcVersion
	 * Renders an $extensionVersionNumber to a compareable version
	 *
	 * @param string $versionNumber
	 * @return int a mathVersion of the version to compare
	 */
	protected function calcVersion($versionNumber) {
		$version = explode('.', $versionNumber);
		if(!isset($version[1]) || !isset($version[2])) return false;
		$number = 0;
		$number += intval($version[0]) * 10000000;
		$number += intval($version[1]) * 1000;
		$number += intval($version[2]);
		return $number;
	}

	/**
	 * function getInsecureExtension
	 * Itterates through all Extensions of all Versions to check if there is an insecure one
	 *
	 * @param array $instances the instances to itterate over
	 * @return void
	 */
	protected function getInsecureExtension($instances) {
		foreach($instances as $path => $instance) {
			foreach($instance as $extensionKey => $extensionVersion) {
				if($this->ignoreModified && $extensionVersion[2])
					continue;
				if(isset($this->insecureExtensions[$extensionKey])) {
					foreach($this->insecureExtensions[$extensionKey] as $insecureVersion) {
						if($insecureVersion[0] >= $extensionVersion[0]) {
							if(!$this->isIgnored($extensionKey, $extensionVersion)) {
								echo 'Insecure Extension: '.$extensionKey.' ('.$extensionVersion[1].') found in '.$path.PHP_EOL;
								break;
							}
						}
					}
				}
			}
		}
	}

	/**
	 * function getOutDatedExtension
	 * Itterates through all Extensions of all Versions to check if there is an outdated one
	 *
	 * @param array $instances
	 * @return void
	 */
	protected function getOutDatedExtensions($instances) {
		foreach($instances as $path => $instance) {
			foreach($instance as $extensionKey => $extensionVersion) {
				if($this->ignoreModified && $extensionVersion[2])
					continue;
				if(!isset($this->newestExtensions[$extensionKey])) {
					$this->fetchNewestExtensionVersion($extensionKey);
				}
				$newest = $this->newestExtensions[$extensionKey];
				if($newest === false) continue;
				if($extensionVersion[0] < $newest[0] && !$this->isIgnored($extensionKey, $extensionVersion))
					echo 'Old Extension: '.$extensionKey.' ('.$extensionVersion[1].' / '.$newest[1].') found in '.$path.PHP_EOL;
			}
		}
	}

	/**
	 * function getTYPO3Instances
	 * going to find all TYPO3 instances under given $path and $depth
	 *
	 * @return array with all found TYPO3 instances
	 */
	protected function getTYPO3Instances() {
		$path = $this->path;
		for($i = 0; $i < $this->depth; $i++)
			$path .= '*/';
		$path .= 'typo3conf';
		$founds = glob($path, GLOB_ONLYDIR);
		if(count($founds) == 0) throw new Exception('No Instances found');
		$return = array();
		for($i = 0; $i < count($founds); $i++){
			$tmpName = str_replace('typo3conf', '', $founds[$i]);
			$return[$tmpName] = $this->findExtensions($tmpName);
		}
		return $return;
	}

	/**
	 * function findExtensions
	 * finds all Extensions from one TYPO3 installation
	 *
	 * @param string $path
	 * @return array all extensions of one installation
	 */
	protected function findExtensions($path) {
		$extensions = glob($path.'typo3conf/ext/*/ext_emconf.php');
		$return = array();
		foreach($extensions as $extFile) {
			$content = file_get_contents($extFile);
			preg_match('/\'version\'\s*=>\s*\'(.*?)\'/', $content, $found);
			$extensionName = str_replace($path.'typo3conf/ext/', '', str_replace('/ext_emconf.php', '', $extFile));
			$extensionVersion = $this->calcVersion($found[1]);
			preg_match('/\'_md5_values_when_last_written\'\s*=>\s*\'(.*?)\'/', $content, $foundMd5);
			if(isset($foundMd5[1])) {
				$extensionMd5 = $foundMd5[1];
			}else{
				$extensionMd5 = false;
			}
			if($extensionVersion !== false) {
				$return[$extensionName] = array($extensionVersion, $found[1], $this->checkMd5($extFile, $extensionMd5));
				if($this->warnModified && $return[$extensionName][2] && !$this->isIgnored($extensionName, $extensionVersion))
					echo 'Modified Extension '.$extensionName.' found in '.$path.PHP_EOL;
			}
		}
		return $return;
	}

	/**
	 * function checkMd5
	 * Compares MD5 Versions of all files of one extension if needed
	 * returns at first found
	 *
	 * @param string $ext the path for each extension
	 * @param string $md5 the md5 serializedObject
	 * @return boolean true if extension is changed
	 */
	protected function checkMd5($ext, $md5) {
		if((!$this->warnModified && !$this->ignoreModified) || $md5 === false)
			return false;
		if($this->checkModificationOnlyFoundInTer && !$this->isInTer($ext))
			return false;
		$md5 = unserialize($md5);
		$ext = str_replace('ext_emconf.php', '', $ext);
		foreach($md5 as $file => $hash) {
			if(file_exists($ext.$file) && $hash != substr(md5(file_get_contents($ext.$file)), 0, 4)) {
			 return true;
			}
		}
		return false;

	}

	/**
	 * function isInTer
	 * checks if extension is in ter.
	 * uses proxy $inTer for speed improvements
	 *
	 * @param string $extension
	 * @return boolean true if extension is in Ter
	 */
	protected function isInTer($extension) {
		$extension = explode('/', $extension);
		$extension = $extension[count($extension)-2];
		if(isset($this->inTer[$extension]))
			return true;
		$ext = $this->xml->query('//extension[@extensionkey=\''.$extension.'\']');
		if($ext ->length == 0)
			return false;
		$this->inTer[$extension] = true;
		return true;
	}

	/**
	 * function fetchNewestExtensionVersion
	 * Searches for $extensionKeys newest Version
	 *
	 * @param string $extensionKey the extensionKey to check for newest version
	 * @return void
	 */
	protected function fetchNewestExtensionVersion($extensionKey) {
		$version = $this->xml->query('//extension[@extensionkey=\''.$extensionKey.'\']/version[last()]');
		if($version->length == 0){
		 	$this->newestExtensions[$extensionKey] = false;
		 	return;
		}
		$version = $version->item(0)->getAttribute('version');
		$this->newestExtensions[$extensionKey] = array($this->calcVersion($version), $version);
	}

	/**
         * function fetchIgnoreExtensions
         * Itterates through given string and builds ignorableExtension array
         *
         * @param string $extensionList the usersInput to ignore
         * @return array $ignoreExtensions<'extensionName' => array('versionNumber', ...)>
         */
        protected function fetchIgnoreExtensions($extensionList) {
                $return =  array();
                $extensions = explode(',',$extensionList);
                foreach($extensions as $extension) {
                        $tmp = explode('=', $extension);
                        if(isset($return[trim($tmp[0])])) {
                                if(isset($tmp[1])) {
                                        $return[trim($tmp[0])][] = trim($tmp[1]);
                                }else{
                                        $return[trim($tmp[0])][] = false;
                                }
                        }else{
                                if(isset($tmp[1])){
                                        $tmpArray = array(trim($tmp[1]));
                                }else{
                                        $tmpArray = array(false);
                                }
                                $return[trim($tmp[0])] = $tmpArray;
                        }
                }
                return $return;
        }

	/**
	 * function isIgnored
	 *
	 *
	 * @param string $extensionKey the extensionKey to check
	 * @param array $extensionVersion the version of this extension
	 * @return boolean true if
	 */
	protected function isIgnored($extensionKey, $extensionVersion) {
		if(!isset($this->ignoreExtensions[$extensionKey]))
			return false;
		foreach($this->ignoreExtensions[$extensionKey] as $version) {
			if($version === false) return true;
			if($version == $extensionVersion[1]) return true;
		}
		return false;
	}
}

$typo3security = new TYPO3Security();
$typo3security -> start();
