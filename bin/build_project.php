#!/usr/local/bin/php
<?php

try {
	defineRootWeb();

	if (empty($argv))
		echoUsageAndExit();
	
	$GLOBALS['make'] = 'update';
	$GLOBALS['dry_run'] = false;
	$GLOBALS['env'] = 'dev';
	
	foreach ($argv as $cmd)
	{
		switch ($cmd) {
			case '--update':
				$GLOBALS['make'] = 'update';
				break;
	
			case '--create-env':
			case '--create_env':
				$GLOBALS['make'] = 'create_env';
				break;
	
			case '--status':
				$GLOBALS['make'] = 'status';
				break;
	
			case '--prod':
				$GLOBALS['env'] = 'prod';
				break;
	
			case '--dry-run':
				$GLOBALS['dry_run'] = true;
				break;
		}
	}
	
	$GLOBALS['component'] = 'wiki';
	
	switch ($GLOBALS['component']) {
		case 'pratiques':
		case 'wiki':
			break;
	
		default:
			echoUsageAndExit();
	}
	
	switch ($GLOBALS['make']) {
		case 'update':
			update();
			break;
	
		case 'create_env':
			create_env();
			break;
	
		case 'status':
			status();
			break;
	
		default:
			# code...
			break;
	}
	
} catch (\Throwable $th) {
	echo 'Exception: ',  $th->getMessage(), "\n";
	echo "Stopping build.\n\n";
	exit(1); // Important: throw an error in order to stop the build
}

exit(0); // Success.

// ================================================================= //

function create_env($component = 'wiki')
{
	switch ($component) {
		case 'pratiques':
		case 'wiki':
			create_wiki_env();
			break;

		default:
			# code...
			break;
	}
}

function update($component = 'wiki')
{
	switch ($component) {
		case 'pratiques':
		case 'wiki':
			update_wiki_env();
			break;

		default:
			# code...
			break;
	}
}

function status($component = 'wiki')
{
	switch ($component) {
		case 'pratiques':
		case 'wiki':
			status_wiki_env();
			break;

		default:
			# code...
			break;
	}
}

function create_wiki_env()
{
	echo "\nInstalling Wiki\n\n";

	// checkout the wiki
	$wiki = getWiki();
    checkout_project($wiki);

    linkWikiSettings();

	// checkout submodules, using gerrit on docker, github on OVH.
	checkout_wiki_submodules();

	// add chameleon
	add_chameleon_in_composer();

	$components = getWikiComponents();
	foreach ($components as $aComponent)
	{
		// git clone each component
		// Remove existing folders in case we have our own extension
		checkout_project($aComponent, true);

		// run composer when required
		run_composer_for_project($aComponent);
	}

	$wiki_install_dir = getInstallDir();

	// Now update the environment with composer (must be installed - see https://getcomposer.org/)
	run_composer('update', $wiki_install_dir);

	// if we are in dev environment, add the images too
	$wikiImagesDir = $wiki_install_dir . '/images';
	if (!is_dir($wikiImagesDir))
		mkdir($wikiImagesDir);

	$tempImageDir = $wikiImagesDir . '/temp';
	if (!is_dir($tempImageDir))
		mkdir($tempImageDir);

	if (getEnvironment() === 'dev' && is_dir(root_web .'/config/images'))
	{
		$src = root_web . '/config/images/*';

		$cmd = 'cp -r ' . $src . ' ' . $wikiImagesDir;
		runCommand($cmd);
	}

	createElasticSearchScript();

	setOwner();

	echo "\n-- Wiki Done --\n\n";
}

function update_wiki_env()
{
	echo "\nUpdating Wiki\n\n";

	// build the envs/project/ dir structure
	if (!file_exists(root_web . '/html/composer.json'))
	{
		throw new \RuntimeException("The project dir does not exist - please use create_env to set it up first.");
	}

	// pull the wiki
	$wiki = getWiki();

	// Before pulling the wiki, lets restore composer.json
	restore_composer();

	pull_project($wiki);

	// update chameleon
	add_chameleon_in_composer();

	linkWikiSettings();

	$components = getWikiComponents();
	foreach ($components as $aComponent)
	{
		// git clone each component
		pull_project($aComponent);

		// Create links for each of our newly installed extensions
		//make_link($aComponent);

		// run composer when required
		run_composer_for_project($aComponent);
	}

	$wiki_install_dir = getInstallDir();

	// Now update the environment with composer (must be installed - see https://getcomposer.org/)
	run_composer('update', $wiki_install_dir);

	// if we are in dev environment, add the images too
	changeDir($wiki_install_dir);
	$cmd = 'php maintenance/update.php';
	runCommand($cmd);

	$tempImageDir = $wiki_install_dir . '/images/temp';
	if (!is_dir($tempImageDir))
		mkdir($tempImageDir);

	createElasticSearchScript();

	setOwner();

	echo "\n-- Wiki updated --\n\n";
}

function status_wiki_env()
{
	echo "\nGetting Git status for Wiki\n\n";

	// build the envs/project/ dir structure
	if (!is_dir(root_web . '/html/'))
	{
		throw new \RuntimeException("The project dir does not exist - please use create_env to set it up first. Exiting.");
	}

	$components = getWikiComponents();
	foreach ($components as $aComponent)
	{
		// git clone each component
		git_status_project($aComponent);
	}

	echo "\n-- Wiki Status Done --\n\n";
}

/**
 * Create a small bash script to reset the ElasticSearch index
 */
function createElasticSearchScript()
{
	$scriptPath =   root_web . '/html/maintenance/setupElasticSearch.sh';

	$lines = array();
	$lines[] = "export MW_INSTALL_PATH=" . root_web . "/html/\n";
	$lines[] = "php \$MW_INSTALL_PATH/extensions/CirrusSearch/maintenance/updateSearchIndexConfig.php\n";
	$lines[] = "php \$MW_INSTALL_PATH/extensions/CirrusSearch/maintenance/forceSearchIndex.php --skipLinks --indexOnSkip\n";
	$lines[] = "php \$MW_INSTALL_PATH/extensions/CirrusSearch/maintenance/forceSearchIndex.php --skipParse\n";
	$lines[] = "\n";

	file_put_contents($scriptPath, $lines);
	chmod($scriptPath, 0755);  // notation octale : valeur du mode correcte
}

/**
 * Checkouts all submodules for mediawiki.
 *
 * $force_clone_from_github true if we should get the submodules from github. If false, will get
 *                          the modules from the usual origin on gerrit
 */
function checkout_wiki_submodules($force_clone_from_github = false)
{
	$wiki_install_dir = getInstallDir();

	changeDir($wiki_install_dir);

	// OVH used to block gerrit, so we replaced gerrit with GitHub.
	// This is not required anymore, thanks to their support:
	// https://community.ovh.com/t/impossible-de-cloner-certains-repos-avec-git-ok-pour-github-pas-ok-pour-gerrit/25512/5
	if ($force_clone_from_github)
	{
		$gitmodulesfiles = $wiki_install_dir . '/.gitmodules';

		// Make a copy of .gitmodules :
		if (!file_exists($gitmodulesfiles . '.bak'))
			copy($gitmodulesfiles, $gitmodulesfiles . '.bak');

		$gitmoduleLines = file($gitmodulesfiles);

		foreach ($gitmoduleLines as $k => $aLine)
		{
			$aLine = preg_replace('@https://gerrit.wikimedia.org/r/mediawiki/extensions/(.*)$@', 'https://github.com/wikimedia/mediawiki-extensions-$1.git', $aLine);
			$aLine = preg_replace('@https://gerrit.wikimedia.org/r/mediawiki/skins/(.*)$@', 'https://github.com/wikimedia/mediawiki-skins-$1.git', $aLine);
			$aLine = preg_replace('@https://gerrit.wikimedia.org/r/mediawiki/vendor$@', 'https://github.com/wikimedia/mediawiki-vendor.git', $aLine);
			$gitmoduleLines[$k] = $aLine;
		}

		file_put_contents($gitmodulesfiles, implode('', $gitmoduleLines));

		$cmd = 'git submodule sync';
		runCommand($cmd);
	}

	// @see https://www.mediawiki.org/wiki/Download_from_Git#Fetch_external_libraries

	$cmd = 'git submodule update --init';
	runCommand($cmd);
}

function getInstallDir()
{
	return root_web . '/html';
}

function restore_composer()
{
	$wiki_install_dir = getInstallDir();
	changeDir($wiki_install_dir);

	$cmd = 'git checkout -- composer.json';
	runCommand($cmd);
}

/**
 * Add Chameleon to mediawiki's composer
 * @see https://github.com/cmln/chameleon/blob/master/docs/installation.md
 * @see https://www.mediawiki.org/wiki/Skin:Chameleon
 */
function add_chameleon_in_composer()
{
	$wiki_install_dir = getInstallDir();

	$composer_file = $wiki_install_dir . '/composer.json';
	$contents = file_get_contents($composer_file);

	$json = json_decode($contents, true);

	$json['require']['mediawiki/chameleon-skin'] = "~3.1";

	file_put_contents($composer_file, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ));
}

function getWiki()
{
	// Mediawiki
	$wiki_install_dir = '/html';
	$wiki_version = 'REL1_35';

	return array(	'html' => '--branch '.$wiki_version.' https://github.com/wikimedia/mediawiki.git',
					'dest' => $wiki_install_dir,
					'branch' => $wiki_version);
}

function getWikiComponents()
{
	// Mediawiki
	$wiki_install_dir = '/html';
	$wiki_thirdparties_dir = '/html/extensions';
	$wiki_skins_dir = '/html/skins';
	$wiki_version = 'REL1_35';
	$neayi_wiki_version = 'REL1_34'; // Since we have cloned a few repos, we have our changes in an old branch
	$latest_wiki_version = 'REL1_35'; // For some extensions we are happy to just take the latest stable

	$components = array();

	// https://github.com/neayi/PDFDownloadCard
	$components[] = array(	'dest' => $wiki_thirdparties_dir . '/PDFDownloadCard',
							'html' => 'https://github.com/neayi/PDFDownloadCard.git',
							'link' => $wiki_install_dir . '/extensions/PDFDownloadCard');

	// https://github.com/neayi/ext-carousel
	$components[] = array(	'dest' => $wiki_thirdparties_dir . '/Carousel',
							'html' => 'https://github.com/neayi/ext-carousel.git',
							'link' => $wiki_install_dir . '/extensions/Carousel');

	// https://www.mediawiki.org/wiki/Extension:Google_Analytics_Integration
	$components[] = array(	'dest' => $wiki_thirdparties_dir . '/googleAnalytics',
							'html' => '--branch '.$wiki_version.' https://github.com/wikimedia/mediawiki-extensions-googleAnalytics.git',
							'link' => $wiki_install_dir . '/extensions/googleAnalytics',
							'branch' => $wiki_version);

	// https://www.mediawiki.org/wiki/Extension:DynamicPageList3
	$components[] = array(	'dest' => $wiki_thirdparties_dir . '/DynamicPageList',
							'html' => '--branch master https://gitlab.com/hydrawiki/extensions/DynamicPageList.git',
							'tag' => "3.3.3",
							'link' => $wiki_install_dir . '/extensions/DynamicPageList');

	// https://github.com/neayi/skin-neayi
	$components[] = array(	'dest' => $wiki_skins_dir . '/skin-neayi',
							'html' => 'https://github.com/neayi/skin-neayi.git',
							'link' => $wiki_install_dir . '/skins/skin-neayi');

	$components[] = array(	'dest' => $wiki_thirdparties_dir . '/Elastica',
							'html' => '--branch '.$wiki_version.' https://github.com/wikimedia/mediawiki-extensions-Elastica.git',
							'link' => $wiki_install_dir . '/extensions/Elastica',
							'composer' => 'install',
							'branch' => $wiki_version);

	$components[] = array(	'dest' => $wiki_thirdparties_dir . '/CirrusSearch',
							'html' => '--branch '.$wiki_version.' https://github.com/wikimedia/mediawiki-extensions-CirrusSearch.git',
							'link' => $wiki_install_dir . '/extensions/CirrusSearch',
							'composer' => 'install',
							'branch' => $wiki_version);

	$components[] = array(	'dest' => $wiki_thirdparties_dir . '/EmbedVideo',
							'html' => 'https://gitlab.com/hydrawiki/extensions/EmbedVideo.git',
							'link' => $wiki_install_dir . '/extensions/EmbedVideo');

	$components[] = array(	'dest' => $wiki_thirdparties_dir . '/RelatedArticles',
							'html' => '--branch '.$wiki_version.' https://github.com/wikimedia/mediawiki-extensions-RelatedArticles.git',
							'link' => $wiki_install_dir . '/extensions/RelatedArticles',
							'branch' => $wiki_version);

	$components[] = array(	'dest' => $wiki_thirdparties_dir . '/Popups',
							'html' => '--branch '.$wiki_version.' https://github.com/wikimedia/mediawiki-extensions-Popups.git',
							'link' => $wiki_install_dir . '/extensions/Popups',
							'branch' => $wiki_version);

	$components[] = array(	'dest' => $wiki_thirdparties_dir . '/Description2',
							'html' => '--branch '.$wiki_version.' https://github.com/wikimedia/mediawiki-extensions-Description2.git',
							'link' => $wiki_install_dir . '/extensions/Description2',
							'branch' => $wiki_version);

	$components[] = array(	'dest' => $wiki_thirdparties_dir . '/ChangeAuthor',
							'html' => '--branch '.$neayi_wiki_version.' https://github.com/neayi/mediawiki-extensions-ChangeAuthor.git',
							'link' => $wiki_install_dir . '/extensions/ChangeAuthor',
							'branch' => $neayi_wiki_version);

	$components[] = array(	'dest' => $wiki_thirdparties_dir . '/AutoSitemap',
							'html' => 'https://github.com/dolfinus/AutoSitemap.git',
							'link' => $wiki_install_dir . '/extensions/AutoSitemap');

	$components[] = array(	'dest' => $wiki_thirdparties_dir . '/Loops',
							'html' => '--branch '.$wiki_version.' https://github.com/wikimedia/mediawiki-extensions-Loops.git',
							'link' => $wiki_install_dir . '/extensions/Loops',
							'branch' => $wiki_version);

	$components[] = array(	'dest' => $wiki_thirdparties_dir . '/Variables',
							'html' => '--branch '.$wiki_version.' https://github.com/wikimedia/mediawiki-extensions-Variables.git',
							'link' => $wiki_install_dir . '/extensions/Variables',
							'branch' => $wiki_version);

	// OAuth
	$components[] = array(	'dest' => $wiki_thirdparties_dir . '/PluggableAuth',
							'html' => '--branch '.$wiki_version.' https://github.com/wikimedia/mediawiki-extensions-PluggableAuth.git',
							'link' => $wiki_install_dir . '/extensions/PluggableAuth',
							'branch' => $wiki_version);

	$components[] = array(	'dest' => $wiki_thirdparties_dir . '/NeayiAuth',
							'html' => 'https://github.com/neayi/NeayiAuth.git',
							'link' => $wiki_install_dir . '/extensions/NeayiAuth',
							'composer' => 'install');

	$components[] = array(	'dest' => $wiki_thirdparties_dir . '/CommentStreams',
							'html' => '--branch Neayi https://github.com/neayi/mediawiki-extensions-CommentStreams.git',
							'link' => $wiki_install_dir . '/extensions/CommentStreams',
							'branch' => 'Neayi');

	$components[] = array(	'dest' => $wiki_thirdparties_dir . '/Echo',
							'html' => '--branch '.$wiki_version.' https://github.com/wikimedia/mediawiki-extensions-Echo.git',
							'link' => $wiki_install_dir . '/extensions/Echo',
							'branch' => $wiki_version);

	$components[] = array(	'dest' => $wiki_thirdparties_dir . '/DeleteBatch',
							'html' => '--branch '.$wiki_version.' https://github.com/wikimedia/mediawiki-extensions-DeleteBatch.git',
							'link' => $wiki_install_dir . '/extensions/DeleteBatch',
							'composer' => 'install',
							'branch' => $wiki_version);

	$components[] = array(	'dest' => $wiki_thirdparties_dir . '/VisualEditor',
							'html' => '--branch '.$wiki_version.' https://github.com/wikimedia/mediawiki-extensions-VisualEditor.git',
							'link' => $wiki_install_dir . '/extensions/VisualEditor',
							'submodules' => true,
							'branch' => $wiki_version);

	$components[] = array(	'dest' => $wiki_thirdparties_dir . '/Disambiguator',
							'html' => '--branch '.$wiki_version.' https://github.com/wikimedia/mediawiki-extensions-Disambiguator.git',
							'link' => $wiki_install_dir . '/extensions/Disambiguator',
							'branch' => $wiki_version);

	$components[] = array(	'dest' => $wiki_thirdparties_dir . '/HitCounters',
							'html' => '--branch '.$wiki_version.' https://github.com/wikimedia/mediawiki-extensions-HitCounters.git',
							'link' => $wiki_install_dir . '/extensions/HitCounters',
							'branch' => $wiki_version);

	$components[] = array(	'dest' => $wiki_thirdparties_dir . '/InputBox',
							'html' => '--branch '.$neayi_wiki_version.' https://github.com/neayi/mediawiki-extensions-InputBox.git',
							'link' => $wiki_install_dir . '/extensions/InputBox',
							'branch' => $neayi_wiki_version);

	$components[] = array(	'dest' => $wiki_thirdparties_dir . '/MassEditRegex',
							'html' => '--branch '.$latest_wiki_version .' https://github.com/wikimedia/mediawiki-extensions-MassEditRegex.git',
							'link' => $wiki_install_dir . '/extensions/MassEditRegex',
							'branch' => $latest_wiki_version);							
							
	$components[] = array(	'dest' => $wiki_thirdparties_dir . '/Realnames',
							'html' => 'https://github.com/ofbeaton/mediawiki-realnames.git',
							'link' => $wiki_install_dir . '/extensions/Realnames');							
														
	return $components;
}

function linkWikiSettings()
{
	// Mediawiki
	$wiki_settings_dir = '/config/';
	$wiki_install_dir = '/html/';

	// config
	$LocalSettings = array(	'dest' => $wiki_settings_dir . 'LocalSettings.php',
							'link' => $wiki_install_dir . 'LocalSettings.php');

	make_link($LocalSettings);
}

/**
 * Checkout a project if they don't exist yet
 */
function checkout_project($aComponent, $bForceRemoveFolder = false)
{
	if (!isset($aComponent['html']))
		return;

	if (is_dir(root_web . $aComponent['dest'])) {
		if ($bForceRemoveFolder)
		{
			// remove the existing folder before cloning
			$cmd = 'rm -r ' . root_web . $aComponent['dest'];
			runCommand($cmd);			
		}
		else
			throw new \RuntimeException('The dest dir already exists: '. root_web . $aComponent['dest']);
    }

	$cmd = 'git clone -q ' . $aComponent['html'] . ' ' . root_web . $aComponent['dest'];
	runCommand($cmd);

	if (!empty($aComponent['tag']))
	{
		changeDir(root_web . $aComponent['dest']);

		$cmd = 'git checkout -q tags/' . $aComponent['tag'];
		runCommand($cmd);
	}

	if (!empty($aComponent['submodules']))
	{
		changeDir(root_web . $aComponent['dest']);

		$cmd = 'git submodule update --init';
		runCommand($cmd);
	}
}

function pull_project($aComponent)
{
	if (!is_dir(root_web . $aComponent['dest']))
	{
		checkout_project($aComponent);
		return;
	}

	changeDir(root_web . $aComponent['dest']);

	if (!empty($aComponent['tag']))
	{
		// If we are using a tag, then we need to checkout to master first in order to avoid an error
		$cmd = 'git checkout -q master'; // todo : change master with the right branch name
		runCommand($cmd);
	}	

	$cmd = 'git pull -q';
	runCommand($cmd);

	// Make sure we are on the right branch:
	if (!empty($aComponent['branch']))
	{
		$cmd = 'git checkout -q ' . $aComponent['branch'];
		runCommand($cmd);		
	}

	// Make sure we are on the right tag:
	if (!empty($aComponent['tag']))
	{
		$cmd = 'git checkout -q tags/' . $aComponent['tag'];
		runCommand($cmd);
	}	
}

function git_status_project($aComponent)
{
	if (!is_dir(root_web . $aComponent['dest']))
		return;

	changeDir(root_web . $aComponent['dest']);

	$cmd = 'git config core.filemode false';
	runCommand($cmd);

	$cmd = 'git status';
	runCommand($cmd);
}

/**
 * Run composer in the given directory.
 * NB : Composer must already be installed, @see https://getcomposer.org/
 */
function run_composer($command, $dir)
{
	echo "\nRunning composer in $dir\n";

	// if ($GLOBALS['environment'] == DOCKER_DEV ||
	// 	$GLOBALS['environment'] == OVH_VPS)
	$cmd = "composer $command --no-dev";
	// else
	// $cmd = "php  ~/composer/composer.phar $command --no-dev";

	changeDir($dir);
	runCommand($cmd);
}

function run_composer_for_project($aComponent)
{
	if (!isset($aComponent['composer']))
		return;

	if (!file_exists(root_web . $aComponent['link'] . '/composer.json'))
	{
		throw new \RuntimeException(root_web . $aComponent['link'] . "/composer.json not found - could not run composer");
	}

	run_composer($aComponent['composer'], root_web . $aComponent['link']);
}

function runCommand($cmd)
{
	echo $cmd . "\n";

	if ($GLOBALS['dry_run'])
		return '';
	
	$return_var = 0;
	passthru($cmd, $return_var);

	if ($return_var !== 0)
	{
		$cwd = getcwd();
		throw new \RuntimeException("Error while executing: $cmd in $cwd");
	}
}

function changeDir($newWorkingDir)
{
	echo "Working in $newWorkingDir\n";
	chdir($newWorkingDir);
}

/**
 * Create symbolic links for each component
 * Can be called several times, even when the link already exist.
 * Will recreate the link if not pointing to the right destination
 */
function make_link($aComponent)
{
	if (!isset($aComponent['link']))
		return;

	$src = root_web . $aComponent['dest'];
	$dst = root_web . $aComponent['link'];

	if (!file_exists($src))
	{
		throw new \RuntimeException("could not create symlink from $src");
	}

	$currentLink = @readlink($dst);

	if (!empty($currentLink) && $currentLink != $src)
	{
		// link exists but points to the wrong source
		unlink($dst);
		$currentLink = '';
	}

	if (empty($currentLink) && file_exists($dst))
	{
		// Destination exists but is not a link (may occur with change of underlying filesystems)
		unlink($dst);
		$currentLink = '';
	}

	if (empty($currentLink))
	{
		$cmd = 'ln -s ' . $src . ' ' . $dst;
		runCommand($cmd);
	}

	// Touch the destination file so that the date is updated in any case.
	// Useful for the LocalSettings.php file, it tells Chameleon to check for changes
	// and eventually update the CSS out of the SCSS.
	touch($dst);
}

function getEnvironment()
{
    return $GLOBALS['env'];
}

function echoUsageAndExit()
{
	echo "Usage: build_project.php [--removelinks --update --create_env --status --dry-run --prod]\n";
	echo "  when no option passed, performs an update.\n";
	exit(0);
}

function defineRootWeb()
{
	define('root_web', dirname(__DIR__)); // /var/www
/*
	switch ($GLOBALS['environment']) {
		case DOCKER_DEV:
		case OVH_VPS:
			define('root_web', dirname(__DIR__)); // /var/www
			break;

		case OVH_PREPROD:
		case OVH_PROD:
			define('root_web', str_replace('/home/', '/homez.171/', dirname(__DIR__))); // /var/www
			break;

		default:
			echo "Unrecognized environment\n";
			exit(1);
	}
	*/
}

function setOwner()
{
	$install_dir = getInstallDir();

	changeDir($install_dir);
	$cmd = 'chown -R www-data:www-data .';
	runCommand($cmd);
}
