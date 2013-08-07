#!/usr/bin/php
<?php
/**
 * TYPO3Security_Crawler Class
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
 * Class TYPO3Security_Crawler
 * @author Bastian Bringenberg <mail@bastian-bringenberg.de>
 * @link https://github.com/bbnetz/TYPO3Security
 *
 */
class TYPO3Security_Crawler {

	/**
	 * @var string $basePath the Path where the crawling should start
	 */
	private $basePath = "http://typo3.org/teams/security/security-bulletins/";

	/**
	 * @var string $baseUrl The prefix for each call.
	 */
	private $baseUrl = 'http://typo3.org';

	/**
	 * @var array $foundPages a list of pages from the security bulletins
	 */
	private $foundPages = array();

	/**
	 * @var array $allPages contains all links so no link will be visited twice
	 */
	private $allPages = array();

	/**
	 * @var array $foundArticles lists all vulveral articles
	 */
	private $foundArticles = array();

	/**
	 * @var array $foundExtensions lists all insecure extensions.
	 * Format:  array(extensionName => extensionVersion )
	 */
	private $foundExtensions = array();

	/**
	 * @var boolean $debug if set will printDebug Informations
	 */
	private $debug = false;

	/**
	 * @var outputFile $string a json file where the insecure extension list will be stored
	 */
	private $outputFile = './insecure.json';

	/**
	 * function __construct
	 * Constructor
	 *
	 * @return TYPO3Security_Crawler
	 */
	public function __construct() {
		$this->foundPages[] = $this->basePath;
		$ops = getopt('',
			array(
				'',
				'basePath::',
				'baseUrl::',
				'debug::',
				'outputFile::',
			)
		);

		if(isset($ops['basePath']))
			$this->basePath = $ops['basePath'];
		if(isset($ops['baseUrl']))
			$this->baseUrl = $ops['baseUrl'];
		if(isset($ops['debug']))
			$this->debug = true;
		if(isset($ops['outputFile']))
			$this->outputFile = $ops['outputFile'];
	}

	/**
	 * function start
	 * Doing all the collective work in row
	 *
	 * @return void
	 */
	public function start() {
		$this->fetchPages();
		$this->readArticles();
		$this->writeFile();
	}

	/**
	 * function fetchPages
	 * Running through all Pages and starting to read pages
	 * 
	 * @return void
	 */
	protected function fetchPages() {
		do{
			$page = array_pop($this->foundPages);
			$this->readPage($page);
		}while(!empty($this->foundPages));
	}

	/**
	 * function readPage
	 * Method to get PageConent and run findPages and findArticles
	 *
	 * @return void
	 */
	protected function readPage($page) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $page);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);
		$this->findPages($output);
		$this->findArticles($output);
	}

	/**
	 * function findPages
	 * Filters a page for pagebrowser links
	 * 
	 * @param string $text the content of a news page
	 * @return void
	 */
	protected function findPages($text) {
		preg_match_all('/<div class="tx-pagebrowse-pi1">(.*?)<\/div>/s', $text, $pageBrowser);
		preg_match_all('/<a href="(.*?)">/', $pageBrowser[1][0], $links);
		for($i=0; $i < count($links[1]); $i++) {
			$this->addPage($links[1][$i]);
		}
	}

	/**
	 * function findArticles
	 * Filters a page for news articles
	 *
	 * @param string $text the content of a news page
	 * @return void
	 */
	protected function findArticles($text) {
		preg_match_all('/<div class="articles">(.*?)<!--TYPO3SEARCH_end-->/s', $text, $articles);
		preg_match_all('/<h2><a href="(.*?)" .*?>(.*?)<\/a>/', $articles[1][0], $links);
		for($i=0; $i < count($links[1]); $i++) {
			if(strpos($links[2][$i], 'TYPO3-EXT') !== FALSE)
				$this->addArticle($links[1][$i]);
		}
	}

	/**
	 * function addPage
	 * attaches pages url to $foundPages if not found in $allPages
	 *
	 * @param string $page the path to an page
	 * @return void
	 */
	protected function addPage($page) {
		if(!in_array($this->baseUrl.$page, $this->allPages)) {
			$this->foundPages[] =  $this->baseUrl.$page;
			$this->allPages[] =  $this->baseUrl.$page;
		}
	}

	/**
	 * function addArticle
	 * attaches articles url to $foundArticles if not found in $allPages
	 *
	 * @param string $article the path to an article
	 * @return void
	 */
	protected function addArticle($article) {
		if(!in_array($this->baseUrl.$article, $this->allPages)) {
			$this->foundArticles[] =  $this->baseUrl.$article;
			$this->allPages[] =  $this->baseUrl.$article;
		}
	}

	/**
	 * function readArticles
	 * itterated through $readArticle and calls readArticle for each
	 *
	 * @return void
	 */
	protected function readArticles() {
		do{
			$article = array_pop($this->foundArticles);
			$this->readArticle($article);
		}while(!empty($this->foundArticles));
	}

	/**
	 * function readArticle
	 * downloads all articles and calls interpredArticle
	 *
	 * @param string $article the url of an article
	 * @return void
	 */
	protected function readArticle($article) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $article);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);
		$this->interpredArticle($output);
	}

	/**
	 * function interpredArticle
	 * Trying to interpred given page content as news article and collection all extensions and versions
	 * 
	 * @param string $text the content of an article page
	 * @return void
	 */
	protected function interpredArticle($text) {
		preg_match_all('/<h1>(.*?)<\/h1>/', $text, $headLine);
		$headLine = $headLine[1][0];
		$this->debug('=== Headline: '.$headLine.PHP_EOL);
		if(strpos($headLine, 'Several') && !strpos($headLine, '(')) {
			/**
			 * try working as a group
			 */
			preg_match_all('/<strong.*?>Extension:<\/strong>.*? \((.*?)\)/', $text, $extensionName);
			preg_match_all('/<p.*?><strong.*?>Affected Versions?:<\/strong>(.*?)<\/p>/s', $text, $extensionVersion);
			for($i = 0; $i < count($extensionName[1]); $i++) {
				$version = 'NOT FOUND';
				if(isset($extensionVersion[1][$i]))
					$version = $extensionVersion[1][$i];
				$this->addExtension($extensionName[1][$i], $version);
			}
		}else{
			/**
			 * Only one extension mentioned
			 */
			preg_match_all('/\((.*?)\)/', $headLine, $extensionName);
			preg_match_all('/<p.*?><strong.*?>Affected Versions?:<\/strong>(.*?)<\/p>/s', $text, $extensionVersion);
			$version = 'NOT FOUND';
			if(isset($extensionVersion[1][0]))
				$version = $extensionVersion[1][0];
			$this->addExtension($extensionName[1][0], $version);
		}
	}

	/**
	 * function addExtension
	 * Adding Extension to local $foundExtensions array. Uses never version if already used.
	 *
	 * @param string $extension the real extension key
	 * @param string $version the real extension version
	 * @return void
	 */
	protected function addExtension($extension, $version) {
		$extension = trim($extension);
		$version = $this->formatVersion($version);
		if(isset($this->foundExtensions[$extension])) {
			if( intval(str_replace('.', '', $this->foundExtensions[$extension])) < intval(str_replace('.', '', $version)) )
				$this->foundExtensions[$extension] = $version;
		}else{
			$this->foundExtensions[$extension] = $version;
		}
		$this->debug('FOUND EXTENSION: '.$extension.' ( '. $this->foundExtensions[$extension].' ) '.PHP_EOL);
	}

	/**
	 * function formatVersion
	 * formatting a possible version number to a real one.
	 *
	 * @param string $version the found $version number.
	 * @return string the formatted Version containing only ##.##.##
	 */
	protected function formatVersion($version) {
		if(preg_match('/(\d+\.\d+\.\d+)/', $version, $found) == 1)
			return $found[1];
		$this->debug('### ');
		return '';
	}

	/**
	 * function debug
	 * Helps Debuging
	 *
	 * @param string $str the string to be echoed if $debug == true
	 * @return void
	 */
	protected function debug($str) {
		if($this->debug)
			echo $str;
	}

	/**
	 * function writeFile
	 * writes json of $foundExtensions to $outputFile
	 *
	 * @return void
	 */
	protected function writeFile() {
		file_put_contents($this->outputFile, json_encode($this->foundExtensions));
	}

}

$crawler = new TYPO3Security_Crawler();
$crawler->start();
