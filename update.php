#!/usr/bin/php
<?php
/**
 * TYPO3Updater Class
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
 * https://github.com/bbnetz/TYPO3Updater
 *
 *
 */

/**
 * Class TYPO3Updater
 * @author Bastian Bringenberg <mail@bastian-bringenberg.de>
 * @link https://github.com/bbnetz/TYPO3Updater
 *
 */
class TYPO3Updater {

	/**
	 * @param string $jsonVersionFile The path where the TYPO3 JSON Versions File is located
	 */
	protected $jsonVersionFile = 'http://get.typo3.org/json';

	/**
	 * @param string $templatePath The path to the default TYPO3 Versions
	 */
	protected $templatePath = '/var/www/TYPO3Templates/';

	/**
	 * @param string $path The basic path.
	 */
	protected $path = '/var/www/';

	/**
	 * @param boolean if true will ignore outDatedWarnings 
	 */
	protected $suppressOutDated = false;

	/**
	 * @param int $depth The depth to watch. Eg 2 adds 2 folders to the basic path
	 */
	protected $depth = 2;

	/**
	 * @param boolean $dryRun if set no cmd on the local TYPO3 instances will be fired
	 * But will create and download TYPO3 Versions from get.typo3.org
	 */
	protected $dryRun = false;

	/**
	 * @param boolean $useGlobalOwner if set $globalOwner will be used as owner everywhere
	 */
	protected $useGlobalOwner = false;

	/**
	 * @param string $globalOwner will be used to the the owner of the TYPO3 instances
	 */
	protected $globalOwner = 'www-data:www-data';

	/**
	 * @param string $templateOwner will be used to set the Templates Owner
	 */
	protected $templateOwner = 'www-data:www-data';

	/**
	 * @param int $work the method to update all the instances
	 */
	protected $work = TYPO3Updater::TEMPLATE_USE_CURRENT;

	/**
	 * @param boolean $forceUpdate makes update even if unneeded
	 */
	protected $forceUpdate = false;

	/**
	 * @param array<versionName => versionNumber>
	 */
	protected $maximumVersionNumbers = array(
		'latest_stable' => '0.0.0',
		'latest_old_stable' => '0.0.0',
		'latest_lts' => '0.0.0',
		'latest_deprecated' => '0.0.0'
		);

	/**
	 * @param int TEMPLATE_COPY
	 * If set: will copy all nessesary files from $templatePath
	 */
	const TEMPLATE_COPY = 0;

	/**
	 * @param int TEMPLATE_SYMLINK_GLOBAL
	 * If set: will use global symlinking from $templatePath
	 */
	const TEMPLATE_SYMLINK_GLOBAL = 1;

	/**
	 * @param int TEMPLATE_SYMLINK_COPY
	 * If set: will copy from $templatePath and use a symlink to a local copy
	 */
	const TEMPLATE_SYMLINK_COPY = 2;

	/**
	 * @param int TEMPLATE_USE_CURRENT
	 * If set: will try to find out what is used right now and update
	 */
	const TEMPLATE_USE_CURRENT = 3;

	/**
	 * Constructor
	 *
	 * Collection all CLI Params
	 * Setting local settings from CLI params
	 * @throws Exception if Params are missing or setted false
	 *
	 * @return TYPO3Update
	 */
	public function __construct() {

		// VersionOwner
		$ops = getopt('',
			array(
				'',
				'templatePath:',
				'templateOwner:',
				'path:',
				'pathLevel:',
				'workMode:',
				'dryRun::',
				'forceUpdate::',
				'pathOwner::',
				'suppressOutDated::'
			)
			);

		if(!isset($ops['templatePath']))
			throw new Exception('No templatePath set. Please read Readme.md');
		$this->templatePath = realpath($ops['templatePath']).DIRECTORY_SEPARATOR;

		if(!isset($ops['templateOwner']))
			throw new Exception('No templateOwner set. Please read Readme.md');
		$this->templateOwner = $ops['templateOwner'];

		if(!isset($ops['path']))
			throw new Exception('No path set. Please read Readme.md');
		$this->path = realpath($ops['path']).DIRECTORY_SEPARATOR;

		if(!isset($ops['pathLevel']))
			throw new Exception('No pathLevel set. Please read Readme.md');
		$this->depth = intval($ops['pathLevel']);

		if(!isset($ops['workMode']))
			throw new Exception('No workMode set. Please read Readme.md');

		switch($ops['workMode']) {
			case 'copy':
				$this->work = TYPO3Updater::TEMPLATE_COPY;
				break;

			case 'current':
				$this->work = TYPO3Updater::TEMPLATE_USE_CURRENT;
				break;

			case 'symlink':
				$this->work = TYPO3Updater::TEMPLATE_SYMLINK_GLOBAL;
				break;

			case 'symlink_copy':
				$this->work = TYPO3Updater::TEMPLATE_SYMLINK_COPY;
				break;

			default:
				throw new Exception('Wrong workMode set. Please read Readme.md');
				break;
		}

		if(isset($ops['dryRun']))
			$this->dryRun = true;

		if(isset($ops['forceUpdate']))
			$this->forceUpdate = true;

		if(isset($ops['pathOwner'])) {
			$this->useGlobalOwner = true;
			$this->globalOwner = $ops['pathOwner'];
		}

		if(isset($ops['suppressOutDated']))
			$this->suppressOutDated = true;

		if(posix_getuid() != 0) {
			$this->dryRun = true;
			echo "\033[0;31m# Running the Script without ROOT access is not possible. The script will run in dry mode.".PHP_EOL;
		}
	}

	/**
	 * function run
	 * Doing the main work
	 * Checking if local templates are up-to-date
	 * Finding all TYPO3 Instances
	 * Checking TYPO3 Versions
	 * Updateing if required
	 *
	 * @return void
	 */
	public function run() {
		$this->checkLocalTYPO3Copies();
		$founds = $this->getTYPO3Instances();
		$founds = $this->checkFoundVersions($founds);
		$founds = $this->updateFoundVersions($founds);
	}

	/**
	 * function checkLocalTYPO3Copies
	 * Downloads $jsonVersionFile and checks if newest versions are locally available
	 * If not downloads und extracts them into $templatePath
	 *
	 * @return void
	 */
	protected function checkLocalTYPO3Copies() {
		$versions = file_get_contents($this->jsonVersionFile);
		if($versions === FALSE) throw new Exception('Could not get Version File: '.$this->jsonVersionFile);
		$versions = json_decode($versions);
		if(!$this->checkVersion($versions->latest_stable, 'latest_stable')) $this->downloadVersion($versions->latest_stable);
		if(!$this->checkVersion($versions->latest_old_stable, 'latest_old_stable')) $this->downloadVersion($versions->latest_old_stable);
		if(!$this->checkVersion($versions->latest_lts, 'latest_lts')) $this->downloadVersion($versions->latest_lts);
		if(!$this->checkVersion($versions->latest_deprecated, 'latest_deprecated')) $this->downloadVersion($versions->latest_deprecated);
		exec('chown -R '.$this->templateOwner.' '.$this->templatePath);
	}

	/**
	 * function checkVersion
	 * Checks if needed folders are created;
	 * Creates Folders if not found
	 * Checks if version already is installed
	 *
	 * @param $version string the version number
	 * @param $versionTitle string the name of the version eg 'latest_lts'
	 * @return boolean true if version already exists
	 */
	protected function checkVersion($version, $versionTitle) {
		$this->maximumVersionNumbers[$versionTitle] = $version;
		if(!$this->dryRun) {
			$versions = explode('.', $version);
			if(!file_exists($this->templatePath.$versions[0]))
				mkdir($this->templatePath.$versions[0]);
			if(!file_exists($this->templatePath.$versions[0].'/'.$versions[1].'-'.$versions[2])) {
				mkdir($this->templatePath.$versions[0].'/'.$versions[1].'-'.$versions[2]);
				return false;
			}
		}
		return true;
	}

	/**
	 * function downloadVersion
	 * downloads TYPO3 Version to local template path
	 *
	 * @param $version string the version number to download
	 * @return void
	 */
	protected function downloadVersion($version) {
		$versions = explode('.', $version);
		$tarFile = $this->templatePath.$versions[0].'/'.$versions[0].'_'.$versions[1].'_'.$versions[2].'.tar.gz';
		$path = $this->templatePath.$versions[0].'/'.$versions[1].'-'.$versions[2];
		echo "\033[0;31mNeed to download new TYPO3 Version: ".$version.PHP_EOL;
		$ch = curl_init('http://get.typo3.org/'.$versions[0].'.'.$versions[1].'.'.$versions[2]);
		$fp = fopen($tarFile, 'w');

		curl_setopt($ch, CURLOPT_FILE, $fp);
		curl_setopt($ch, CURLOPT_HEADER, 0);

		curl_exec($ch);
		curl_close($ch);
		fclose($fp);
		exec('tar xfz '.$tarFile.' -C '.$path);
		unlink($tarFile);
	}

	/**
	 * function getTYPO3Instances
	 * Builds SearchPath from basic path and depth
	 * Uses typo3conf as TYPO3 identifier
	 *
	 * @throws Exception if no instances found
	 * @return array<string> Pathes to the TYPO3 instances
	 */
	protected function getTYPO3Instances() {
		$path = $this->path;
		for($i = 0; $i < $this->depth; $i++)
			$path .= '*/';
		$path .= 'typo3conf';
		$founds = glob($path, GLOB_ONLYDIR);
		if(count($founds) == 0) throw new Exception('No Instances found');
		for($i = 0; $i < count($founds); $i++){
			$founds[$i] = str_replace('typo3conf', '', $founds[$i]);
		}
		return $founds;
	}

	/**
	 * function checkFoundVersions
	 * Checks for each TYPO3 Installation the current version number
	 *
	 * @return array<array<string>> Pathes to TYPO3 instance and Version Number
	 */
	protected function checkFoundVersions($founds) {
		$tmp = array();
		foreach($founds as $found) {
			$state = $this->getSingleVersion($found);
			$versionCheck = $this->isOutdated($found, $state);
			$tmp[] = array(
				'path' => $found,
				'version' => $state,
				'outdated' => $versionCheck[0],
				'requestedVersion' => $versionCheck[1],
				'owner' => $this->getOwner($found)
				);
		}
		return $tmp;
	}

	/**
	 * function getOwner
	 * Tries to get the owner of the instances path or returns $globalOwner
	 *
	 * @param $found string the owner of the updated instances
	 * @return string the owner parameter
	 */
	protected function getOwner($found) {
		if($this->useGlobalOwner)
			return $this->globalOwner;
		$fileOwner = posix_getpwuid(fileowner($found));
		$groupOwner = posix_getgrgid(filegroup($found));
		return $fileOwner['name'].':'.$groupOwner['name'];
	}

	/**
	 * function getSingleVersion
	 * Checks for each TYPO3 Installation the current version number
	 *
	 * @return string Version Number of current Path
	 */
	protected function getSingleVersion($found) {
		if(file_exists($found.'/t3lib/config_default.php')) {
			$content = file_get_contents($found.'/t3lib/config_default.php');
		}elseif(file_exists($found.'/typo3/sysext/core/Classes/Core/SystemEnvironmentBuilder.php')){
			$content = file_get_contents($found.'/typo3/sysext/core/Classes/Core/SystemEnvironmentBuilder.php');
		}else{
			throw new Exception('Version not found for '.$found);
		}
		if(preg_match("/TYPO_VERSION\s*=\s*'(.*)';/", $content, $match) != 1) {
			if(preg_match("/define\('TYPO3_version', (.*)\)/", $content, $match) != 1)
				throw new Exception('Version not found for '.$found);
		}
		$version = trim($match[1]);
		$version = str_replace("'", '', $version);

		return $version;
	}

	/**
	 * function isOutdated
	 * Checks if TYPO3 instance is old
	 * Echos if not a recommend version anymore
	 *
	 * @param $path string the path to the local TYPO3 instance
	 * @param $state string the version number of the TYPO3 instance from $path
	 * @return array(boolean, string) true if updade is needed and available
	 */
	protected function isOutdated($path, $state) {
		$state = explode('.', $state);
		foreach($this->maximumVersionNumbers as $version) {
			$version = explode('.', $version);
			if($version[0] == $state[0] && $version[1] == $state[1]) {
				if($version[2] == $state[2] && !$this->forceUpdate)
					return array(false, implode('.', $version));
				return array(true, implode('.', $version));
			}
		}
		if(!$this->suppressOutDated)
			echo "\033[0;31mVersion ".implode('.', $state).' of instance: '.$path. ' Is outdated! Please Update!'.PHP_EOL;
		return false; //so that this script is not trying to update
	}

	/**
	 * function updateFoundVersions
	 * itterates through TYPO3 instances and forwards if isOutdated
	 *
	 * @param $founds array the local found instances
	 * @return void
	 */
	protected function updateFoundVersions($founds) {
		foreach($founds as $found) {
			if($found['outdated']) {
				$this->updateFoundVersion($found);
			}
		}
	}

	/**
	 * function updateFoundVersion
	 *
	 * @param $found array a Found Version including Path, Version and Outdated and RequestedVersion
	 * @return void
	 */
	protected function updateFoundVersion($found) {
		$task = $this->work;
		if($task == TYPO3Updater::TEMPLATE_USE_CURRENT)
			$task = $this->findCurrentSolution($found);
		switch($task) {
			case TYPO3Updater::TEMPLATE_COPY:
				$this->updateFoundVersionCopy($found);
				break;

			case TYPO3Updater::TEMPLATE_SYMLINK_GLOBAL:
				$this->updateFoundVersionSymlinkGlobal($found);
				break;

			case TYPO3Updater::TEMPLATE_SYMLINK_COPY:
				$this->updateFoundVersionSymlinkCopy($found);
				break;
		}
	}

	/**
	 * function findCurrentSolution
	 * Trys to figgure out what solution currently is used before updating
	 *
	 * @param $found array a Found Version including Path, Version and Outdated and RequestedVersion
	 * @return int the taskType
	 */
	protected function findCurrentSolution($found) {
		if(!is_link($found['path'].'index.php'))
			return TYPO3Updater::TEMPLATE_COPY;
		if(!is_link($found['path'].'typo3src'))
			return TYPO3Updater::TEMPLATE_SYMLINK_COPY;
		return TYPO3Updater::TEMPLATE_SYMLINK_GLOBAL;
	}

	/**
	 * function updateFoundVersionSymlinkGlobal
	 *
	 * @param $found array a Found Version including Path, Version and Outdated and RequestedVersion
	 * @return void
	 */
	protected function updateFoundVersionSymlinkGlobal($found) {
		$targetVersion = explode('.', $found['requestedVersion']);
		$this->execCommand('rm -rf '.$found['path'].'index.php '.$found['path'].'t3lib '.$found['path'].'typo3 '.$found['path'].'typo3src');
		$this->execCommand('ln -s '.$this->templatePath.$targetVersion[0].'/'.$targetVersion[1].'-'.$targetVersion[2].'/typo3_src-* '.$found['path'].'typo3src' );
		$this->execCommand('ln -s '.$found['path'].'typo3src/typo3 '.$found['path'].'typo3');
		$this->execCommand('ln -s '.$found['path'].'typo3src/t3lib '.$found['path'].'t3lib');
		$this->execCommand('ln -s '.$found['path'].'typo3src/index.php '.$found['path'].'index.php');
		$this->execCommand('chown -R '.$found['owner'].' '.$found['path'].'typo3src '.$found['path'].'t3lib '.$found['path'].'typo3 '.$found['path'].'index.php');
		echo "\033[0;31m# ".$found['path'].' updated with GlobalSymlink to '.$found['requestedVersion'].PHP_EOL;
	}

	/**
	 * function updateFoundVersionCopy
	 *
	 * @param $found array a Found Version including Path, Version and Outdated and RequestedVersion
	 * @return void
	 */
	protected function updateFoundVersionCopy($found) {
		$targetVersion = explode('.', $found['requestedVersion']);
		$this->execCommand('rm -rf '.$found['path'].'index.php '.$found['path'].'t3lib '.$found['path'].'typo3 '.$found['path'].'typo3src');
		$this->execCommand('cp -R '.$this->templatePath.$targetVersion[0].'/'.$targetVersion[1].'-'.$targetVersion[2].'/typo3_src-*/typo3 '.$found['path']);
		$this->execCommand('cp -R '.$this->templatePath.$targetVersion[0].'/'.$targetVersion[1].'-'.$targetVersion[2].'/typo3_src-*/t3lib '.$found['path']);
		$this->execCommand('cp -R '.$this->templatePath.$targetVersion[0].'/'.$targetVersion[1].'-'.$targetVersion[2].'/typo3_src-*/index.php '.$found['path']);
		$this->execCommand('chown -R '.$found['owner'].' '.$found['path'].'t3lib '.$found['path'].'typo3 '.$found['path'].'index.php');
		echo "\033[0;31m# ".$found['path'].' updated with Copy to '.$found['requestedVersion'].PHP_EOL;
	}

	/**
	 * function updateFoundVersionSymlinkCopy
	 *
	 * @param $found array a Found Version including Path, Version and Outdated and RequestedVersion
	 * @return void
	 */
	protected function updateFoundVersionSymlinkCopy($found) {
		$targetVersion = explode('.', $found['requestedVersion']);
		$this->execCommand('rm -rf '.$found['path'].'index.php '.$found['path'].'t3lib '.$found['path'].'typo3 '.$found['path'].'typo3src');
		$this->execCommand('cp -R '.$this->templatePath.$targetVersion[0].'/'.$targetVersion[1].'-'.$targetVersion[2].'/typo3_src-* '.$found['path'].'typo3src' );
		$this->execCommand('ln -s '.$found['path'].'typo3src/typo3 '.$found['path'].'typo3');
		$this->execCommand('ln -s '.$found['path'].'typo3src/t3lib '.$found['path'].'t3lib');
		$this->execCommand('ln -s '.$found['path'].'typo3src/index.php '.$found['path'].'index.php');
		$this->execCommand('chown -R '.$found['owner'].' '.$found['path'].'typo3src '.$found['path'].'t3lib '.$found['path'].'typo3 '.$found['path'].'index.php');
		echo "\033[0;31m# ".$found['path'].' updated with SymlinkCopy to '.$found['requestedVersion'].PHP_EOL;
	}

	/**
	 * function execCommand
	 * Runs $cmd if $this->dryRun is false
	 * Echos $cmd if $this->dryRun is true
	 *
	 * @param $cmd string the string to execute
	 * @return void
	 */
	protected function execCommand($cmd) {
		if($this->dryRun) {
			echo "\033[0m".$cmd.PHP_EOL;
		}else{
			exec($cmd);
		}
	}

}

$run = new TYPO3Updater();
$run->run();

?>
