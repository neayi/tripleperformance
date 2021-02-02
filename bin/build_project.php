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
			update_wiki_env();
			break;

		case '--create-env':
			create_wiki_env();
			break;

		case '--status':
			status_wiki_env();
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

	$env = getenv('ENV');
	if ($env == 'dev')
	{
		foreach ($components as $aComponent)
		{
			if (preg_match('/:neayi/', $aComponent['git']))
				setOwner(root_web . $aComponent['dest'], '1000');
		}
	}

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

	$env = getenv('ENV');
	if ($env == 'dev')
	{
		foreach ($components as $aComponent)
		{
			if (preg_match('/:neayi/', $aComponent['git']))
				setOwner(root_web . $aComponent['dest'], '1000');
		}
	}

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
	$lines[] = "php " . root_web . "/html/extensions/CirrusSearch/maintenance/UpdateSearchIndexConfig.php\n";
	$lines[] = "php " . root_web . "/html/extensions/CirrusSearch/maintenance/ForceSearchIndex.php --skipLinks --indexOnSkip\n";
	$lines[] = "php " . root_web . "/html/extensions/CirrusSearch/maintenance/ForceSearchIndex.php --skipParse\n";
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

	return array(	'git' => '--branch '.$wiki_version.' https://github.com/wikimedia/mediawiki.git',
					'dest' => $wiki_install_dir,
					'branch' => $wiki_version);
}

function getWikiComponents()
{
	// Mediawiki
	$wiki_install_dir = '/html';
	$wiki_extensions_dir = '/html/extensions';
	$wiki_skins_dir = '/html/skins';
	$wiki_version = 'REL1_35';
	$neayi_wiki_version = 'REL1_34'; // Since we have cloned a few repos, we have our changes in an old branch
	$latest_wiki_version = 'REL1_35'; // For some extensions we are happy to just take the latest stable

	$components = array();

	// Composer components
	$components[] = array(	'composer' => 'mediawiki/chameleon-skin' );
	$components[] = array(	'composer' => 'mediawiki/semantic-media-wiki' );
	$components[] = array(	'composer' => 'mediawiki/maps' );

	// Regular Mediawiki extensions

	// https://www.mediawiki.org/wiki/Extension:GTag
	$components[] = array(	'dest' => $wiki_extensions_dir . '/GTag',
							'git' => 'https://github.com/SkizNet/mediawiki-GTag.git');

	https://www.mediawiki.org/wiki/Extension:HeadScript
	$components[] = array(	'dest' => $wiki_extensions_dir . '/HeadScript',
							'git' => '--branch '.$wiki_version.' https://github.com/wikimedia/mediawiki-extensions-HeadScript.git',
							'branch' => $wiki_version);

	// https://www.mediawiki.org/wiki/Extension:DynamicPageList3
	$components[] = array(	'dest' => $wiki_extensions_dir . '/DynamicPageList',
							'git' => '--branch master https://gitlab.com/hydrawiki/extensions/DynamicPageList.git',
							'tag' => "3.3.3");

	$components[] = array(	'dest' => $wiki_extensions_dir . '/Elastica',
							'git' => '--branch '.$wiki_version.' https://github.com/wikimedia/mediawiki-extensions-Elastica.git',
							'postinstall' => 'composer',
							'branch' => $wiki_version);

	$components[] = array(	'dest' => $wiki_extensions_dir . '/CirrusSearch',
							'git' => '--branch '.$wiki_version.' https://github.com/wikimedia/mediawiki-extensions-CirrusSearch.git',
							'postinstall' => 'composer',
							'branch' => $wiki_version);

	$components[] = array(	'dest' => $wiki_extensions_dir . '/EmbedVideo',
							'git' => 'https://gitlab.com/hydrawiki/extensions/EmbedVideo.git');

	$components[] = array(	'dest' => $wiki_extensions_dir . '/RelatedArticles',
							'git' => '--branch '.$wiki_version.' https://github.com/wikimedia/mediawiki-extensions-RelatedArticles.git',
							'branch' => $wiki_version);

	$components[] = array(	'dest' => $wiki_extensions_dir . '/Popups',
							'git' => '--branch '.$wiki_version.' https://github.com/wikimedia/mediawiki-extensions-Popups.git',
							'branch' => $wiki_version);

	$components[] = array(	'dest' => $wiki_extensions_dir . '/Description2',
							'git' => '--branch '.$wiki_version.' https://github.com/wikimedia/mediawiki-extensions-Description2.git',
							'branch' => $wiki_version);

	$components[] = array(	'dest' => $wiki_extensions_dir . '/AutoSitemap',
							'git' => 'https://github.com/dolfinus/AutoSitemap.git');

	$components[] = array(	'dest' => $wiki_extensions_dir . '/Loops',
							'git' => '--branch '.$wiki_version.' https://github.com/wikimedia/mediawiki-extensions-Loops.git',
							'branch' => $wiki_version);

	$components[] = array(	'dest' => $wiki_extensions_dir . '/Variables',
							'git' => '--branch '.$wiki_version.' https://github.com/wikimedia/mediawiki-extensions-Variables.git',
							'branch' => $wiki_version);

	$components[] = array(	'dest' => $wiki_extensions_dir . '/PluggableAuth',
							'git' => '--branch '.$wiki_version.' https://github.com/wikimedia/mediawiki-extensions-PluggableAuth.git',
							'branch' => $wiki_version);

	$components[] = array(	'dest' => $wiki_extensions_dir . '/Echo',
							'git' => '--branch '.$wiki_version.' https://github.com/wikimedia/mediawiki-extensions-Echo.git',
							'branch' => $wiki_version);

	$components[] = array(	'dest' => $wiki_extensions_dir . '/DeleteBatch',
							'git' => '--branch '.$wiki_version.' https://github.com/wikimedia/mediawiki-extensions-DeleteBatch.git',
							'postinstall' => 'composer',
							'branch' => $wiki_version);

	$components[] = array(	'dest' => $wiki_extensions_dir . '/VisualEditor',
							'git' => '--branch '.$wiki_version.' https://github.com/wikimedia/mediawiki-extensions-VisualEditor.git',
							'postinstall' => 'submodules',
							'branch' => $wiki_version);

	$components[] = array(	'dest' => $wiki_extensions_dir . '/Disambiguator',
							'git' => '--branch '.$wiki_version.' https://github.com/wikimedia/mediawiki-extensions-Disambiguator.git',
							'branch' => $wiki_version);

	$components[] = array(	'dest' => $wiki_extensions_dir . '/HitCounters',
							'git' => '--branch '.$wiki_version.' https://github.com/wikimedia/mediawiki-extensions-HitCounters.git',
							'branch' => $wiki_version);

	$components[] = array(	'dest' => $wiki_extensions_dir . '/VEForAll',
							'git' => '--branch '.$wiki_version.' https://github.com/wikimedia/mediawiki-extensions-VEForAll.git',
							'branch' => $wiki_version);

	$components[] = array(	'dest' => $wiki_extensions_dir . '/MassEditRegex',
							'git' => '--branch '.$latest_wiki_version .' https://github.com/wikimedia/mediawiki-extensions-MassEditRegex.git',
							'branch' => $latest_wiki_version);				

	$components[] = array(	'dest' => $wiki_extensions_dir . '/Realnames',
							'git' => 'https://github.com/ofbeaton/mediawiki-realnames.git');	

	// https://www.mediawiki.org/wiki/Extension:EditNotify
	$components[] = array(	'dest' => $wiki_extensions_dir . '/EditNotify',
							'git' => '--branch '.$wiki_version.' https://github.com/wikimedia/mediawiki-extensions-EditNotify',
							'branch' => $wiki_version);
							

	// Neayi extensions and forks
	$components[] = array(	'dest' => $wiki_skins_dir . '/skin-neayi',
							'git' => 'https://github.com/neayi/skin-neayi.git');

	$components[] = array(	'dest' => $wiki_extensions_dir . '/PDFDownloadCard',
							'git' => 'https://github.com/neayi/PDFDownloadCard.git');

	$components[] = array(	'dest' => $wiki_extensions_dir . '/Carousel',
							'git' => 'https://github.com/neayi/ext-carousel.git');

	$components[] = array(	'dest' => $wiki_extensions_dir . '/ChangeAuthor',
							'git' => '--branch '.$wiki_version.' https://github.com/neayi/mediawiki-extensions-ChangeAuthor.git',
							'branch' => $wiki_version);

	$components[] = array(	'dest' => $wiki_extensions_dir . '/NeayiAuth',
							'git' => 'https://github.com/neayi/NeayiAuth.git',
							'postinstall' => 'composer');

	$components[] = array(	'dest' => $wiki_extensions_dir . '/CommentStreams',
							'git' => '--branch Neayi https://github.com/neayi/mediawiki-extensions-CommentStreams.git',
							'branch' => 'Neayi');

	$components[] = array(	'dest' => $wiki_extensions_dir . '/InputBox',
							'git' => '--branch '.$neayi_wiki_version.' https://github.com/neayi/mediawiki-extensions-InputBox.git',
							'branch' => $neayi_wiki_version);

	$components[] = array(	'dest' => $wiki_extensions_dir . '/OpenGraph',
							'git' => 'https://github.com/neayi/mediawiki-extensions-OpenGraph.git');	
						   
	$env = getenv('ENV');
	if ($env == 'dev')
	{
		foreach ($components as $k => $aComponent)
			$components[$k]['git'] = str_replace('https://github.com/neayi', 'git@github.com:neayi', $aComponent['git']);

		// Copy the files in ~/.ssh from /ssh 
		if (!file_exists('/ssh'))
		{
			echo "\nCould not copy ssh keys from host. Please run build_project with the following:\n\n";
			echo "docker-compose run --rm -v ~/.ssh:/ssh web php bin/build_project.php --update\n";
			throw new \RuntimeException("Wrong command in dev environment");
		}

		runCommand('cp -r /ssh ~/.ssh');
		runCommand('chmod 600 ~/.ssh/*');
	}
						
	return $components;
}

function linkWikiSettings()
{
	$src = root_web . '/config/LocalSettings.php';
	$dst = root_web . '/html/LocalSettings.php';

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

/**
 * Checkout a project if they don't exist yet
 */
function checkout_project($aComponent, $bForceRemoveFolder = false)
{
	if (!isset($aComponent['git']))
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

	$cmd = 'git clone -q ' . $aComponent['git'] . ' ' . root_web . $aComponent['dest'];
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

	$env = getenv('ENV');
	if ($env == 'dev')
		$cmd = "composer update --no-progress --no-interaction";
	else
		$cmd = "composer update --no-progress --no-interaction --no-dev";

	echo "\nUpdating components with composer in $dir\n";

	changeDir($wiki_install_dir);
	runCommand($cmd);
}

function installComposer($aComponent)
{
	if (!file_exists(root_web . $aComponent['dest'] . '/composer.json'))
	{
		throw new \RuntimeException(root_web . $aComponent['dest'] . "/composer.json not found - could not run composer");
	}

	$dir = root_web . $aComponent['dest'];

	echo "\nInstalling components with composer in $dir\n";

	$env = getenv('ENV');
	if ($env == 'dev')
		$cmd = "composer install --no-progress --no-interaction";
	else
		$cmd = "composer install --no-progress --no-interaction --no-dev";

	changeDir($dir);
	runCommand($cmd);	
}

function initSubModules($dir)
{
	changeDir($dir);

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

function echoUsageAndExit()
{
	echo "Usage: build_project.php [--update] [--create_env] [--status] [--initElasticSearch]\n";
	exit(0);
}

function setOwner($dir = '', $owner = 'www-data')
{
	if (empty($dir))
		$dir = getInstallDir();

	changeDir($dir);
	$cmd = "chown -R $owner:$owner .";
	runCommand($cmd);
}
