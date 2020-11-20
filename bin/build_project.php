#!/usr/local/bin/php
<?php

define('root_web', dirname(__DIR__)); // /var/www

if (empty($argv[1]))
	echoUsageAndExit();

try {
	$GLOBALS['dry_run'] = false;

	switch ($argv[1]) 
	{
		case '--update':
			update();
			break;

		case '--create-env':
			create_env();
			break;

		case '--status':
			status();
			break;

		case '--initElasticSearch':
			initElasticSearch();
			break;

		default:
			echoUsageAndExit();
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
	
	$wiki_install_dir = getInstallDir();

	initSubModules($wiki_install_dir);

	$components = getWikiComponents();
	foreach ($components as $aComponent)
	{
		if (isset($aComponent['composer']))
			addComponentToComposer($aComponent);
		else
			checkout_project($aComponent, true); // Remove existing folders in case we have our own extension
	}

	updateComposer();

	// if we are in dev environment, add the images too
	$wikiImagesDir = $wiki_install_dir . '/images';
	if (!is_dir($wikiImagesDir))
		mkdir($wikiImagesDir);

	$tempImageDir = $wikiImagesDir . '/temp';
	if (!is_dir($tempImageDir))
		mkdir($tempImageDir);

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

	linkWikiSettings();

	$components = getWikiComponents();
	foreach ($components as $aComponent)
	{
		if (isset($aComponent['composer']))
			addComponentToComposer($aComponent);
		else
			pull_project($aComponent);
	}

	updateComposer();

	// Upgrade the wiki
	upgradeWiki();

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

function upgradeWiki()
{
	$wiki_install_dir = getInstallDir();
	changeDir($wiki_install_dir);

	$cmd = 'php maintenance/update.php';
	runCommand($cmd);
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
 * Launch the elasticSearch setup scripts
 */
function initElasticSearch()
{
	$maintenance_dir = getInstallDir() . '/maintenance';
	changeDir($maintenance_dir);

	$cmd = './setupElasticSearch.sh';
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

	// Composer components
	$components[] = array(	'composer' => 'mediawiki/chameleon-skin' );
	$components[] = array(	'composer' => 'mediawiki/semantic-media-wiki' );

	// Regular Mediawiki extensions

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
							'postinstall' => 'composer',
							'branch' => $wiki_version);

	$components[] = array(	'dest' => $wiki_thirdparties_dir . '/CirrusSearch',
							'html' => '--branch '.$wiki_version.' https://github.com/wikimedia/mediawiki-extensions-CirrusSearch.git',
							'link' => $wiki_install_dir . '/extensions/CirrusSearch',
							'postinstall' => 'composer',
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

	$components[] = array(	'dest' => $wiki_thirdparties_dir . '/PluggableAuth',
							'html' => '--branch '.$wiki_version.' https://github.com/wikimedia/mediawiki-extensions-PluggableAuth.git',
							'link' => $wiki_install_dir . '/extensions/PluggableAuth',
							'branch' => $wiki_version);

	$components[] = array(	'dest' => $wiki_thirdparties_dir . '/Echo',
							'html' => '--branch '.$wiki_version.' https://github.com/wikimedia/mediawiki-extensions-Echo.git',
							'link' => $wiki_install_dir . '/extensions/Echo',
							'branch' => $wiki_version);

	$components[] = array(	'dest' => $wiki_thirdparties_dir . '/DeleteBatch',
							'html' => '--branch '.$wiki_version.' https://github.com/wikimedia/mediawiki-extensions-DeleteBatch.git',
							'link' => $wiki_install_dir . '/extensions/DeleteBatch',
							'postinstall' => 'composer',
							'branch' => $wiki_version);

	$components[] = array(	'dest' => $wiki_thirdparties_dir . '/VisualEditor',
							'html' => '--branch '.$wiki_version.' https://github.com/wikimedia/mediawiki-extensions-VisualEditor.git',
							'link' => $wiki_install_dir . '/extensions/VisualEditor',
							'postinstall' => 'submodules',
							'branch' => $wiki_version);

	$components[] = array(	'dest' => $wiki_thirdparties_dir . '/Disambiguator',
							'html' => '--branch '.$wiki_version.' https://github.com/wikimedia/mediawiki-extensions-Disambiguator.git',
							'link' => $wiki_install_dir . '/extensions/Disambiguator',
							'branch' => $wiki_version);

	$components[] = array(	'dest' => $wiki_thirdparties_dir . '/HitCounters',
							'html' => '--branch '.$wiki_version.' https://github.com/wikimedia/mediawiki-extensions-HitCounters.git',
							'link' => $wiki_install_dir . '/extensions/HitCounters',
							'branch' => $wiki_version);

	$components[] = array(	'dest' => $wiki_thirdparties_dir . '/MassEditRegex',
							'html' => '--branch '.$latest_wiki_version .' https://github.com/wikimedia/mediawiki-extensions-MassEditRegex.git',
							'link' => $wiki_install_dir . '/extensions/MassEditRegex',
							'branch' => $latest_wiki_version);				

	$components[] = array(	'dest' => $wiki_thirdparties_dir . '/Realnames',
							'html' => 'https://github.com/ofbeaton/mediawiki-realnames.git',
							'link' => $wiki_install_dir . '/extensions/Realnames');	

	// Neayi extensions and forks

	$components[] = array(	'dest' => $wiki_thirdparties_dir . '/PDFDownloadCard',
							'html' => 'https://github.com/neayi/PDFDownloadCard.git',
							'link' => $wiki_install_dir . '/extensions/PDFDownloadCard');

	$components[] = array(	'dest' => $wiki_thirdparties_dir . '/Carousel',
							'html' => 'https://github.com/neayi/ext-carousel.git',
							'link' => $wiki_install_dir . '/extensions/Carousel');

	$components[] = array(	'dest' => $wiki_thirdparties_dir . '/ChangeAuthor',
							'html' => '--branch '.$neayi_wiki_version.' https://github.com/neayi/mediawiki-extensions-ChangeAuthor.git',
							'link' => $wiki_install_dir . '/extensions/ChangeAuthor',
							'branch' => $neayi_wiki_version);

	$components[] = array(	'dest' => $wiki_thirdparties_dir . '/NeayiAuth',
							'html' => 'https://github.com/neayi/NeayiAuth.git',
							'link' => $wiki_install_dir . '/extensions/NeayiAuth',
							'postinstall' => 'composer');

	$components[] = array(	'dest' => $wiki_thirdparties_dir . '/CommentStreams',
							'html' => '--branch Neayi https://github.com/neayi/mediawiki-extensions-CommentStreams.git',
							'link' => $wiki_install_dir . '/extensions/CommentStreams',
							'branch' => 'Neayi');

	$components[] = array(	'dest' => $wiki_thirdparties_dir . '/InputBox',
							'html' => '--branch '.$neayi_wiki_version.' https://github.com/neayi/mediawiki-extensions-InputBox.git',
							'link' => $wiki_install_dir . '/extensions/InputBox',
							'branch' => $neayi_wiki_version);

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
		switchToTag($aComponent);

	// run post install steps when required
	if (!empty($aComponent['postinstall']))
	{
		switch ($aComponent['postinstall']) {
			case 'composer':
				installComposer($aComponent);
				break;

			case 'submodules':
				initSubModules(root_web . $aComponent['dest']);
				break;

			default:
				# code...
				break;
		}
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
		switchToBranch($aComponent);		

	// Make sure we are on the right tag:
	if (!empty($aComponent['tag']))
		switchToTag($aComponent);

	// run post install steps when required
	if (!empty($aComponent['postinstall']))
	{
		switch ($aComponent['postinstall']) {
			case 'composer':
				installComposer($aComponent);
				break;

			case 'submodules':
				initSubModules(root_web . $aComponent['dest']);
				break;
				
			default:
				# code...
				break;
		}
	}	
}

/**
 * Make sure the component is on the right branch
 */
function switchToBranch($aComponent)
{
	changeDir(root_web . $aComponent['dest']);

	$cmd = 'git checkout -q ' . $aComponent['branch'];
	runCommand($cmd);
}

/**
 * Make sure the component is on the right tag
 */
function switchToTag($aComponent)
{
	changeDir(root_web . $aComponent['dest']);

	$cmd = 'git checkout -q tags/' . $aComponent['tag'];
	runCommand($cmd);
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
 * Add a component to the main composer file. 
 * Requires a composer update after that.
 */
function addComponentToComposer($aComponent)
{
	$wiki_install_dir = getInstallDir();

	$cmd = "composer require --no-interaction --no-update " . $aComponent['composer'];

	changeDir($wiki_install_dir);
	runCommand($cmd);
}

/**
 * Updates composer
 */
function updateComposer()
{
	$wiki_install_dir = getInstallDir();

	if (empty($_ENV['ENV']) || $_ENV['ENV'] == 'dev')
		$cmd = "composer update --no-interaction";
	else
		$cmd = "composer update --no-interaction --no-dev";

	echo "\nUpdating components with composer in $dir\n";

	changeDir($wiki_install_dir);
	runCommand($cmd);
}

function installComposer($aComponent)
{
	if (!file_exists(root_web . $aComponent['link'] . '/composer.json'))
	{
		throw new \RuntimeException(root_web . $aComponent['link'] . "/composer.json not found - could not run composer");
	}

	$dir = root_web . $aComponent['link'];

	echo "\nInstalling components with composer in $dir\n";

	if (empty($_ENV['ENV']) || $_ENV['ENV'] == 'dev')
		$cmd = "composer install --no-interaction";
	else
		$cmd = "composer install --no-interaction --no-dev";

	changeDir($dir);
	runCommand($cmd);	
}

function initSubModules($dir)
{
	changeDir(root_web . $aComponent['dest']);

	$cmd = 'git submodule update --init';
	runCommand($cmd);	
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

function echoUsageAndExit()
{
	echo "Usage: build_project.php [--update] [--create_env] [--status] [--initElasticSearch]\n";
	exit(0);
}

function setOwner()
{
	$install_dir = getInstallDir();

	changeDir($install_dir);
	$cmd = 'chown -R www-data:www-data .';
	runCommand($cmd);
}
