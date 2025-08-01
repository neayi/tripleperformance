<?php

# This file was automatically generated by the MediaWiki 1.32.0
# installer. If you make manual changes, please keep track in case you
# need to recreate them later.
#
# See includes/DefaultSettings.php for all configurable settings
# and their default values, but don't forget to make changes in _this_
# file, not there.
#
# Further documentation for configuration settings may be found at:
# https://www.mediawiki.org/wiki/Manual:Configuration_settings

# Protect against web entry
if ( !defined( 'MEDIAWIKI' ) ) {
	exit;
}

// =================================================================

$env = getenv('ENV', true) ?: 'prod'; // dev, prod, preprod
$debug = false === getenv('DEBUG', true) ? 'dev' === $env : 'true' === strtolower((string) getenv('DEBUG', true)); // true, false
$domainName = getDomainName($env);
$useHttps = false;
$domainUrl = ($useHttps ? 'https' : 'http') . '://' . $domainName;
$emailSender = 'no-reply@tripleperformance.com';

$wiki_language_prefix = strtoupper(getWikiLanguage());

// =================================================================

## Uncomment this to disable output compression
$wgDisableOutputCompression = !$debug;

$wgSitename = "Triple Performance";
$wgMetaNamespace = "Triple_Performance";

## The URL base path to the directory containing the wiki;
## defaults for all runtime URL paths are based off of this.
## For more information on customizing the URLs
## (like /w/index.php/Page_title to /wiki/Page_title) please see:
## https://www.mediawiki.org/wiki/Manual:Short_URL
$wgScriptPath = "";

$wgScriptExtension = ".php";
$wgArticlePath = "/wiki/$1";
$wgUsePathInfo = true;
$wgForceHTTPS = $useHttps;
$wgCanonicalServer = 'https://' . $domainName;

## The protocol and server name to use in fully-qualified URLs
$wgServer = '//' . $domainName;

## The URL path to static resources (images, scripts, etc.)
$wgResourceBasePath = $wgScriptPath;

## The URL path to the logo.  Make sure you change this from the default,
## or else you'll overwrite your logo when you upgrade!
$wgLogo = "/skins/skin-neayi/favicon/logo-triple-performance.svg";
$wgAppleTouchIcon = "/skins/skin-neayi/favicon/apple-touch-icon.png";
$wgFavicon = "/skins/skin-neayi/favicon/favicon.ico";

## UPO means: this is also a user preference option

$wgEnableEmail = true;
$wgEnableUserEmail = true; # UPO
if ($env == 'preprod')
    $wgEnableEmail = false; // Disable emails on preprod please.
$wgAllowHTMLEmail = true;

$wgEmergencyContact = "bertrand.gorge@neayi.com";
$wgPasswordSender = $emailSender;

$wgEnotifUserTalk = false;
$wgEnotifWatchlist = true;
$wgEmailAuthentication = true;

## Database settings
$wgDBtype = "mysql";

## Database settings
$wgDBserver = getenv('MYSQL_HOST', true);
$wgDBname = getenv($wiki_language_prefix . '_MYSQL_DB', true);
$wgDBuser = getenv('MYSQL_USER', true);
$wgDBpassword = getenv('MYSQL_PASSWORD', true);

$wiki_language = getWikiLanguage();

function getWikiLanguage()
{
    $forcedWikiLanguage = getenv('WIKI_LANGUAGE', true);

    if (empty($forcedWikiLanguage) && isset($_SERVER['HTTP_HOST']))
        $hostparts = explode('.', $_SERVER['HTTP_HOST']);
    else
        $hostparts = [ $forcedWikiLanguage ];

    foreach ($hostparts as $chunk)
    {
        switch ($chunk) {
            case 'fr':
            case 'demo':
                return 'fr';

            case 'de':
            case 'en':
            case 'es':
            case 'it':
            case 'ar':                
            case 'nl':
            case 'pl':
            case 'el':
            case 'hu':
            case 'fi':
            case 'pt':                
                return $chunk;
        }
    }

    return 'fr';
}

function getDomainName($env)
{
    $language = getWikiLanguage();

    switch ($env) {
        case 'dev':
            if ($language === 'fr')
                return "wiki.dev.tripleperformance.fr";

            return "$language.dev.tripleperformance.ag";

        case 'preprod':
            if ($language === 'fr')
                return "wiki.preprod.tripleperformance.fr";

            return "$language.preprod.tripleperformance.ag";
        case 'prod':
            if ($language === 'fr')
                return "wiki.tripleperformance.fr";

            return "$language.tripleperformance.ag";

        default:
            throw new Exception("Unrecognized environment", 1);
    }
}

# MySQL specific settings
$wgDBprefix = '';

// @see https://www.mediawiki.org/wiki/Manual:$wgSecretKey
$wgSecretKey = getenv($wiki_language_prefix . '_SECRET_KEY', true);

# Site upgrade key. Must be set to a string (default provided) to turn on the
# web installer while LocalSettings.php is in place
// @see https://www.mediawiki.org/wiki/Manual:$wgUpgradeKey
$wgUpgradeKey = getenv($wiki_language_prefix . '_UPGRADE_KEY', true);

// To configure ElasticSearch passwords, see:
// @see https://www.elastic.co/fr/blog/getting-started-with-elasticsearch-security
// @see https://discuss.elastic.co/t/setting-xpack-security-enabled-true/182791/7
// ./bin/elasticsearch-setup-passwords auto -u "http://localhost:9200"
$wgCirrusSearchServers = array_filter([ getenv('ELASTICSEARCH_SERVER', true) ]);

# MySQL table options to use during installation or update
$wgDBTableOptions = "ENGINE=InnoDB, DEFAULT CHARSET=binary";

## Shared memory settings
// Store cache objects in Redis
$wgObjectCaches['redis'] = [
    'class' => 'RedisBagOStuff',
    'servers' => [
            'redis:6379'
    ],
    'persistent' => true,
 ];
 $wgMainCacheType = 'redis';
 $wgMemCachedServers = [];
 $wgSessionCacheType = 'redis';

## To enable image uploads, make sure the 'images' directory
## is writable, then set this to true:
$wgEnableUploads = true;
$wgUseImageMagick = true; // disable on OVH https://www.mediawiki.org/wiki/Topic:Uysful50s28egg8a
$wgImageMagickConvertCommand = "/usr/bin/convert";
$wgFileExtensions[] = 'svg';
$wgFileExtensions[] = 'webp';
$wgSVGConverter = 'ImageMagick';

$wgAllowExternalImages = true;

$wgGroupPermissions['*']['upload_by_url'] = true;
$wgAllowCopyUploads = true;
$wgVerifyMimeType = false;

// Maximum amount of virtual memory available to shell processes under Linux, in KiB.
$wgMaxShellMemory = 614400;

// Disable runing jobs as part of the apache request, they will be run with runJobs.php and cron
$wgJobRunRate = 0;

// Allow PDF
$wgFileExtensions[] = 'pdf';
$wgUploadDirectory = "{$IP}/images/$wiki_language";
$wgUploadPath = "{$wgScriptPath}/images/$wiki_language";

if ($wiki_language != 'fr')
{
    // Allow getting images from other languages wiki:
    $wgForeignFileRepos[] = [
        'class' => ForeignDBRepo::class,
        'name' => '3perf_fr',
        'url' => 'https://wiki.tripleperformance.fr/images/fr',
        'scriptDirUrl' => 'https://wiki.tripleperformance.fr/',
        // 'apibase' => 'https://wiki.tripleperformance.fr/api.php', // For ForeignAPIRepo only
        'hashLevels' => 2,
        'fetchDescription' => true,
        'descriptionCacheExpiry' => 43200,
        'apiThumbCacheExpiry' => 86400,

        'directory' => "{$IP}/images/fr",     //   A path to MediaWiki's media directory local to the server, such as /var/www/wiki/images.
        'dbType' => $wgDBtype, //        equivalent to the corresponding member of $wgDBservers
        'dbServer' => $wgDBserver,
        'dbUser' => $wgDBuser,
        'dbPassword' => $wgDBpassword,
        'dbName' => getenv('FR_MYSQL_DB', true),
        'dbFlags' => ( $wgDebugDumpSql ? DBO_DEBUG : 0 ) | DBO_DEFAULT,
        'tablePrefix' => $wgDBprefix,
        'hasSharedCache' => true, //         True if the wiki's shared cache is accessible via the local $wgMemc

        'thumbScriptUrl' => $wgSharedThumbnailScriptPath,
        'transformVia404' => !$wgGenerateThumbnailOnParse,

        'hasSharedCache' => $wgCacheSharedUploads,
        'descBaseUrl' => $wgRepositoryBaseUrl,
        'fetchDescription' => $wgFetchCommonsDescriptions
    ];
}

$wgTmpDirectory = "{$wgUploadDirectory}/temp";
$wgImageMagickTempDir = "{$wgUploadDirectory}/temp";
$wgAttemptFailureEpoch = 30;

## To enable image uploads, make sure the 'images' directory
## is writable, then set this to true:
$wgGenerateThumbnailOnParse = true;

# InstantCommons allows wiki to use images from https://commons.wikimedia.org
$wgUseInstantCommons = true;

# Open external links in new windows
$wgExternalLinkTarget = '_blank';

# Periodically send a pingback to https://www.mediawiki.org/ with basic data
# about this MediaWiki instance. The Wikimedia Foundation shares this data
# with MediaWiki developers to help guide future development efforts.
$wgPingback = false;

## If you use ImageMagick (or any other shell command) on a
## Linux server, this will need to be set to the name of an
## available UTF-8 locale
$wgShellLocale = "C.UTF-8";

## Set $wgCacheDirectory to a writable directory on the web server
## to make your wiki go slightly faster. The directory should not
## be publically accessible from the web.
$wgCacheDirectory = "{$wgUploadDirectory}/temp/wiki";

# Site language code, should be one of the list in ./languages/data/Names.php
$wgLanguageCode = $wiki_language;

# Changing this will log out all existing sessions.
$wgAuthenticationTokenVersion = "1";

## For attaching licensing metadata to pages, and displaying an
## appropriate copyright notice / icon. GNU Free Documentation
## License and Creative Commons licenses are supported so far.
$wgRightsPage = ""; # Set to the title of a wiki page that describes your license/copyright
$wgRightsUrl = "";
$wgRightsText = "";
$wgRightsIcon = "";

# Path to the GNU diff3 utility. Used for conflict resolution.
$wgDiff3 = "/usr/bin/diff3";

# Define custome namespaces
define("NS_STRUCTURE", 3000); // This MUST be even.
define("NS_STRUCTURE_TALK", 3001); // This MUST be the following odd integer.
define("NS_TRAINING", 3002); // This MUST be even.
define("NS_TRAINING_TALK", 3003); // This MUST be the following odd integer.
define("NS_IFRAME", 3004); // This MUST be even.
define("NS_IFRAME_TALK", 3005); // This MUST be the following odd integer.

// Add namespaces.
$wgExtraNamespaces[NS_STRUCTURE] = "Structure";
$wgExtraNamespaces[NS_STRUCTURE_TALK] = "Structure_talk"; // Note underscores in the namespace name.
$wgExtraNamespaces[NS_TRAINING] = "Formation";
$wgExtraNamespaces[NS_TRAINING_TALK] = "Formation_talk"; // Note underscores in the namespace name.
$wgExtraNamespaces[NS_IFRAME] = "Iframe";
$wgExtraNamespaces[NS_IFRAME_TALK] = "IFrame_talk"; // Note underscores in the namespace name.

// Those namespace are seen as content for extensions
$wgContentNamespaces[] = NS_STRUCTURE;
$wgContentNamespaces[] = NS_TRAINING;
$wgContentNamespaces[] = NS_CATEGORY;
$wgContentNamespaces[] = NS_PROJECT;
$wgContentNamespaces[] = NS_HELP;
$wgContentNamespaces[] = NS_IFRAME;

$wgNamespacesToBeSearchedDefault[NS_STRUCTURE] = true;
$wgNamespacesToBeSearchedDefault[NS_TRAINING] = true;
$wgNamespacesToBeSearchedDefault[NS_CATEGORY] = true;

# The following permissions were set based on your choice in the installer
$wgGroupPermissions['*']['createaccount'] = true;
$wgGroupPermissions['*']['edit'] = false;

# Enabled extensions. Most of the extensions are enabled by adding
# wfLoadExtensions('ExtensionName');
# to LocalSettings.php. Check specific extension documentation for more details.
# The following extensions were automatically enabled:
wfLoadExtension( 'MultimediaViewer' );
wfLoadExtension( 'ParserFunctions' );
$wgPFEnableStringFunctions = true;
$wgPFStringLengthLimit = 1500;

wfLoadExtension( 'Link_Attributes' );

# PDFHandler in order to build thumbnails for PDFs
wfLoadExtension( 'PdfHandler' );
wfLoadExtension( 'PDFEmbed' );
$wgGroupPermissions['*']['embed_pdf'] = true;
$wgGroupPermissions['user']['embed_pdf'] = true;
$wgGroupPermissions['bot']['embed_pdf'] = true;

# End of automatically generated settings.
# Add more configuration options below.

// Semantic Mediawiki
wfLoadExtension( 'SemanticMediaWiki' );
enableSemantics( $domainName );
$smwgConfigFileDir = $wgUploadDirectory;
$smwgNamespacesWithSemanticLinks[NS_STRUCTURE] = true;
$smwgNamespacesWithSemanticLinks[NS_TRAINING] = true;

$smwgDefaultStore = 'SMWElasticStore';
$elastic_parts = preg_split("/[:@]/", getenv('ELASTICSEARCH_SERVER', true));
$smwgElasticsearchEndpoints = [ [ 'host' => $elastic_parts[2],
                                  'port' => 9200,
                                  'scheme' => 'http',
                                  'user' => $elastic_parts[0],
                                  'pass' => $elastic_parts[1] ] ];

$smwgElasticsearchCredentials = [
    'host' => $elastic_parts[2],
    'port' => 9200,
    'scheme' => 'http',
    'user' => $elastic_parts[0],
    'pass' => $elastic_parts[1],
];

unset($elastic_parts);
$smwgElasticsearchConfig["indexer"]["raw.text"] = true;

//SMWResultPrinter::$maxRecursionDepth = 4;

// https://github.com/SemanticMediaWiki/SemanticExtraSpecialProperties/blob/master/docs/configuration.md
wfLoadExtension( 'SemanticExtraSpecialProperties' );
$sespgEnabledPropertyList = [
    '_PAGEID',
    '_CUSER',
    '_EUSER',
    '_VIEWS',
//    '_DESCRIPTION',
    '_PAGELGTH',
    '_NREV',
    '_PAGEIMG',
    '_DESCRIPTION'
];

$sespgLocalDefinitions['_DESCRIPTION'] = [
	'id'    => '___DESCRIPTION',
	'type'  => '_txt',
	'alias' => 'sesp-property-description',
	'label' => 'Description',
    'callback'  => static function(\SESP\AppFactory $appFactory, \SMW\DIProperty $property, \SMW\SemanticData $semanticData ) {
        $title = $semanticData->getSubject()->getTitle();
        $pageProps = MediaWiki\MediaWikiServices::getInstance()->getPageProps();

        $propertyNames = [ 'description' ]; // Replace with your property name
        $properties = $pageProps->getProperties( [ $title ], $propertyNames );
        $pageId = $title->getArticleID();
        $value = $properties[$pageId]['description'] ?? null;

        return new \SMWDIBlob( $value );
    }
];

$sespgUseFixedTables = true;
$sespgExcludeBotEdits = true;

// SEO and Sitemap
$wgSitemapNamespaces = [
    0, // main
    2, // users
    4, // Triple Performance
    6, // Files and images
    // 8, // Mediawiki
    // 10, // Templates
    12, // Help
    14, // Categories
    // 102, // Attributes
    // 106, // Forms
    // 108, // Concepts
    // 112, // Schemas
    // 828, // Modules
    // 844, // Special
    3000, // Structures
    3002 // Training
    // 3004 // Iframes
];

$wgSitemapNamespacesPriorities = [
    2 => '0.9',
    6 => '0.9',
    14 => '0.9',
    3002 => '0.9'
];

wfLoadExtension( 'HeadScript' );

// Add some color to the browser (in mobile mode)
$wgHeadScriptCode = '<meta name="theme-color" content="#15A072">';

// Add the Triple Performance icon font
$wgHeadScriptCode .= '<link rel="stylesheet" href="https://neayi.github.io/tripleperformance-icon-font/style.css" />';

if('prod' === $env) {
    $wgEnableCanonicalServerLink = true;

    // https://www.mediawiki.org/wiki/Extension:GTag
    // https://mwusers.org/files/file/4-gtag/
    // https://github.com/SkizNet/mediawiki-GTag
    // wfLoadExtension( 'GTag' );
    // $wgGTagAnalyticsId  = 'G-FXVTX5HGV7'; // 'UA-116409512-5';

    // // If true, insert tracking code into sensitive pages such as Special:UserLogin and Special:Preferences. If false, no tracking code is added to these pages.
    // $wgGTagTrackSensitivePages = true;

    // // Use 'gtag-exempt' permission to exclude specific user groups from web analytics, e.g.
    // $wgGroupPermissions['sysop']['gtag-exempt'] = true;
    // $wgGroupPermissions['bot']['gtag-exempt'] = true;
    // $wgGroupPermissions['bureaucrat']['gtag-exempt'] = true;

    switch (getWikiLanguage()) {
        case 'en':
            $matomoSiteId = 2;
            break;
        case 'fr':
        default:
            $matomoSiteId = 1;
            break;
    }

    $wgHeadScriptCode .= <<<START_END_MARKER
    <!-- Matomo -->
    <script>
    var _paq = window._paq = window._paq || [];
    /* tracker methods like "setCustomDimension" should be called before "trackPageView" */
    _paq.push(['trackPageView']);
    _paq.push(['enableLinkTracking']);
    (function() {
        var u="//matomo.tripleperformance.fr/";
        _paq.push(['setTrackerUrl', u+'matomo.php']);
        _paq.push(['setSiteId', '$matomoSiteId']);
        var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];
        g.async=true; g.src=u+'matomo.js'; s.parentNode.insertBefore(g,s);
    })();
    </script>
    <!-- Facebook Pixel Code -->
    <script>
      !function(f,b,e,v,n,t,s)
      {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
      n.callMethod.apply(n,arguments):n.queue.push(arguments)};
      if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
      n.queue=[];t=b.createElement(e);t.async=!0;
      t.src=v;s=b.getElementsByTagName(e)[0];
      s.parentNode.insertBefore(t,s)}(window, document,'script',
      'https://connect.facebook.net/en_US/fbevents.js');
      fbq('init', '705673526999195');
      fbq('track', 'PageView');
    </script>
    <noscript><img height="1" width="1" style="display:none"
      src="https://www.facebook.com/tr?id=705673526999195&ev=PageView&noscript=1"
    /></noscript>
    <!-- End Facebook Pixel Code -->
    <script type="text/javascript">
    _linkedin_partner_id = "2661170";
    window._linkedin_data_partner_ids = window._linkedin_data_partner_ids || [];
    window._linkedin_data_partner_ids.push(_linkedin_partner_id);
    </script><script type="text/javascript">
    (function(){var s = document.getElementsByTagName("script")[0];
    var b = document.createElement("script");
    b.type = "text/javascript";b.async = true;
    b.src = "https://snap.licdn.com/li.lms-analytics/insight.min.js";
    s.parentNode.insertBefore(b, s);})();
    </script>
    <noscript>
    <img height="1" width="1" style="display:none;" alt="" src="https://px.ads.linkedin.com/collect/?pid=2661170&fmt=gif" />
    </noscript>
START_END_MARKER;
}
else
{
    // Avoid being indexed on non production environments
    $wgDefaultRobotPolicy = 'noindex,nofollow';
}


if ('dev' === $env) {
    
    $wgHeadScriptCode .= <<<START_END_MARKER
    <!-- Matomo -->
    <script>
    var _paq = window._paq = window._paq || [];
    /* tracker methods like "setCustomDimension" should be called before "trackPageView" */
    _paq.push(['trackPageView']);
    _paq.push(['enableLinkTracking']);
    (function() {
        var u="//matomo.dev.tripleperformance.fr/";
        _paq.push(['setTrackerUrl', u+'matomo.php']);
        _paq.push(['setSiteId', '$matomoSiteId']);
        var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];
        g.async=true; g.src=u+'matomo.js'; s.parentNode.insertBefore(g,s);
    })();
    </script>
START_END_MARKER;

}
// Cookies
$wgCookieExpiration = 180 * 86400; // 180 days
$wgExtendedLoginCookieExpiration = null;
$wgDefaultUserOptions['rememberpassword'] = 1;
$wgHooks['AuthChangeFormFields'][] = function ($requests, $fieldInfo, &$formDescriptor, $action) {
    $formDescriptor['rememberMe'] = ['type' => 'check', 'default' => true];
    return true;
  };

// https://www.mediawiki.org/wiki/Manual:$wgFixDoubleRedirects
$wgFixDoubleRedirects = true;

// Allow to change the title of a page : https://www.mediawiki.org/wiki/Display_title
$wgAllowDisplayTitle = true; // defaults to true
$wgRestrictDisplayTitle = false; // defaults to true

// Chameleon
wfLoadExtension( 'Bootstrap' );
wfLoadSkin( 'chameleon' );
$wgDefaultSkin='chameleon';

$egChameleonLayoutFile = dirname(MW_CONFIG_FILE) . '/skins/skin-neayi/layout.'.$wiki_language.'.xml';
$egChameleonExternalStyleModules = array(
    dirname(MW_CONFIG_FILE) . '/skins/skin-neayi/chameleon-tripleperformance-variables.scss' => 'afterVariables',// 'afterVariables',
    dirname(MW_CONFIG_FILE) . '/skins/skin-neayi/chameleon-neayi.scss' => 'afterMain'
);

if (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'demo') !== false) {
    $egChameleonExternalStyleModules[dirname(MW_CONFIG_FILE) . '/skins/skin-neayi/clients/demo/skin_variables.scss'] = 'afterVariables';
    $egChameleonExternalStyleModules[dirname(MW_CONFIG_FILE) . '/skins/skin-neayi/clients/demo/skin.scss'] = 'afterMain';
}

// Allow custom CSS on Special Pages :
$wgAllowSiteCSSOnRestrictedPages = true;

if($debug) {
    \Bootstrap\BootstrapManager::getInstance()->addCacheTriggerFile(dirname(MW_CONFIG_FILE) . '/skins/skin-neayi/chameleon-tripleperformance-variables.scss');
    \Bootstrap\BootstrapManager::getInstance()->addCacheTriggerFile(dirname(MW_CONFIG_FILE) . '/skins/skin-neayi/chameleon-neayi.scss');
    \Bootstrap\BootstrapManager::getInstance()->addCacheTriggerFile(dirname(MW_CONFIG_FILE) . '/skins/skin-neayi/_caracteristiques_exploitation.scss');
}

$egChameleonExternalStyleVariables = [
    'primary' => '#15A072'
];

// Scripting and parsing
wfLoadExtension( 'Loops' );
wfLoadExtension( 'Variables' );
wfLoadExtension( 'Scribunto' );
$wgScribuntoDefaultEngine = 'luastandalone';

wfLoadExtension( 'SemanticScribunto' );

// Neayi's extensions
wfLoadExtension( 'Carousel' );

// More parser functions
wfLoadExtension( 'EmbedVideo' );
$wgEmbedVideoDefaultWidth = 640;
$wgEmbedVideoRequireConsent = false;

// Related Articles (shows related articles at the bottom of the page)
wfLoadExtension( 'RelatedArticles' );
$wgRelatedArticlesFooterWhitelistedSkins = ['chameleon'];
$wgRelatedArticlesUseCirrusSearch = false;
$wgRelatedArticlesDescriptionSource = 'pagedescription';

// OpenGraph extensions:
wfLoadExtension( 'PageImages' );
$wgPageImagesBlacklist = array(
	// Page on local wiki
	array(
		'type' => 'db',
		'page' => 'MediaWiki:Pageimages-blacklist',
		'db' => false,
	),
);
// Bump the score of the first image
$wgPageImagesScores['position'] = [ 99, 6, 4, 3 ];
$wgPageImagesNamespaces = [NS_MAIN, NS_STRUCTURE, NS_TRAINING, NS_CATEGORY];

wfLoadExtension( 'TextExtracts' );
wfLoadExtension( 'Description2' );
$wgEnableMetaDescriptionFunctions = true;

wfLoadExtension( 'OpenGraphMeta' );
wfLoadExtension( 'NativeSvgHandler' );

wfLoadExtension( 'UrlShortener' );
$wgUrlShortenerTemplate = '/r/$1';
$wgUrlShortenerServer = "3perf.fr";
$wgUrlShortenerAllowedDomains = array(
	'(.*\.)?tripleperformance\.fr',
    '(.*\.)?tripleperformance\.ag'
);

// Popups (shows a preview of the page on hover)
wfLoadExtension( 'Popups' );
$wgPopupsHideOptInOnPreferencesPage = true;
$wgPopupsOptInDefaultState = '1';
$wgPopupsReferencePreviewsBetaFeature = false;

// Allow to change the Author of an article
wfLoadExtension( 'ChangeAuthor' );
$wgGroupPermissions['sysop']['changeauthor'] = true; // Only sysops can use ChangeAuthor. This is the recommended setup
$wgGroupPermissions['bureaucrat']['changeauthor'] = true; // Only bureaucrats can use ChangeAuthor


// References and citations
wfLoadExtension( 'Cite' );

// Neayi Extensions
wfLoadExtension( 'DiscourseIntegration' );
$wgInsightsRootAPIURL = getenv('INSIGHT_API_URL', true) . '/';
$wgDiscourseAPIKey = getenv($wiki_language_prefix . '_DISCOURSE_API_KEY', true);
$wgDiscourseHost = getenv($wiki_language_prefix . '_DISCOURSE_API_HOST', true);
$wgDiscourseURL = getenv($wiki_language_prefix . '_DISCOURSE_ROOT_URL', true);
$wgDiscourseDefaultCategoryId = getenv($wiki_language_prefix . '_DISCOURSE_DEFAULT_CATEGORY', true);
$wgDiscourseDefaultTagGroupId = getenv($wiki_language_prefix . '_DISCOURSE_DEFAULT_TAGGROUP', true);

wfLoadExtension( 'NeayiInteractions' );
wfLoadExtension( 'NeayiNavbar' );
wfLoadExtension( 'NeayiIntroJS' );
$wgInsightsRootURL = getenv('INSIGHT_URL', true) . '/';

// Echo
wfLoadExtension( 'Echo' );
$wgEchoUseJobQueue = true;
$wgEchoEmailFooterAddress = "<div style=\"padding: 100px 0 0 0; text-align:center\"><img src=\"https://wiki.tripleperformance.fr/images/1/1a/Logo_Triple_Performance.png\" width=\"300\"></div>";

$wgEnotifMinorEdits = false;
$wgEnotifUseRealName = true;

// Neayi login
wfLoadExtension( 'PluggableAuth' );
wfLoadExtension( 'NeayiAuth' );

$wgOAuthRedirectUri = 'https://' . $domainName . "/index.php/Special:PluggableAuthLogin";
$wgPluggableAuth_EnableAutoLogin = false;
$wgPluggableAuth_EnableLocalLogin = false;

$wgPluggableAuth_Config['Log in using Triple Performance'] = [
	'plugin' => 'NeayiAuth'
];

$wgPasswordAttemptThrottle = [];
$wgAccountCreationThrottle = 0;

$wgOAuthUri = getenv('INSIGHT_URL', true) . '/register?&';

if ($env == 'preprod')
    $wgOAuthUserApiByToken = 'http://insights_preprod/api/user?&';
else
    $wgOAuthUserApiByToken = 'http://insights/api/user?&';

$wgGroupPermissions['*']['autocreateaccount'] = true;
$wgUseCombinedLoginLink = true;
$wgAvatarsBaseUri = getenv('INSIGHT_URL', true) . '/storage/users/';

// Realnames
wfLoadExtension( 'Realnames' );
$wgRealnamesLinkStyle = 'replace';

// HidePrefix
require_once( "$IP/extensions/HidePrefix/HidePrefix.php" );

// Delete several pages in one shot
wfLoadExtension( 'DeleteBatch' );
$wgGroupPermissions['sysop']['deletebatch'] = true;

wfLoadExtension( 'Parsoid', "$IP/vendor/wikimedia/parsoid/extension.json" );

// VisualEditor
wfLoadExtension( 'VisualEditor' );
$wgVisualEditorTabMessages['editsource'] = null;
$wgVisualEditorTabMessages['createsource'] = null;

// Enable by default for everybody
$wgDefaultUserOptions['visualeditor-enable'] = 1;

$wgVisualEditorEnableWikitext = true;
$wgDefaultUserOptions['visualeditor-newwikitext'] = 1;
$wgHiddenPrefs[] = 'visualeditor-newwikitext';

$wgVirtualRestConfig['modules']['parsoid'] = array(
    'url' => 'http://' . $domainName . '/rest.php'
);

// Page forms and template data
wfLoadExtension( 'TemplateData' );
wfLoadExtension( 'PageForms' );
$wgPageFormsLinkAllRedLinksToForms = true;
$wgPageFormsAutocompleteOnAllChars = true;

$wgPageFormsRenameEditTabs = false; // renames the "edit with form" tab to "edit", and the "edit" tab to "edit source" (in whatever language the wiki is being viewed in)
$wgPageFormsRenameMainEditTab = true; // renames only the "edit" tab to "edit source" (in whatever language the wiki is being viewed in)

wfLoadExtension( 'UploadWizard' );
$wgUseInstantCommons = true;
$wgUploadNavigationUrl = '/wiki/Special:UploadWizard';
$wgUploadWizardConfig = array(
    'autoAdd' => array(
        //  'wikitext' => array(
        //     'This file was uploaded with the UploadWizard extension.'
        //     ),
            'categories' => array(
                "Fichier chargé avec l'assistant UploadWizard"
                ),
        ), // Should be localised to the language of your wiki instance
//        'feedbackPage' => 'Feedback about UploadWizard',
//    'altUploadForm' => 'Special:Upload',
    'feedbackLink' => false, // Disable the link for feedback (default: points to Commons)
    'alternativeUploadToolsPage' => false, // Disable the link to alternative upload tools (default: points to Commons)
    'enableFormData' => true, // Enable FileAPI uploads be used on supported browsers
    'enableMultipleFiles' => true,
    'enableMultiFileSelect' => false,
    'uwLanguages' => array(
        'fr' => 'Français',
        'en' => 'English',
        'de' => 'Deutsch',
        'pl' => 'Polski',
        'ar' => 'الدارجة',        
        'nl' => 'Nederlands',
        'it' => 'Italiano',
        'es' => 'Español',
        'el' => 'Ελληνικά',
        'hu' => 'Magyar',
        'fi' => 'Suomi',
        'pt' => 'Português'
        ), // Selectable languages for file descriptions - defaults to 'en'
    'tutorial' => array(
            'skip' => true
        ), // Skip the tutorial
    'maxUploads' => 15, // Number of uploads with one form - defaults to 50
    'fileExtensions' => $wgFileExtensions // omitting this may cause errors
    );


// https://www.mediawiki.org/wiki/Extension:VEForAll
wfLoadExtension( 'VEForAll' );

// Disambiguator
wfLoadExtension( 'Disambiguator' );

wfLoadExtension( 'AdminLinks' );

// Translation
// wfLoadExtension( 'UniversalLanguageSelector' );

// wfLoadExtension( 'Translate' );
// $wgGroupPermissions['user']['translate'] = true;
// $wgGroupPermissions['user']['translate-messagereview'] = true;
// $wgGroupPermissions['sysop']['pagetranslation'] = true;
// $wgTranslateTranslationServices['TTMServer'] = array(
//     'type' => 'ttmserver',
//     'class' => 'ElasticSearchTTMServer',
//     'cutoff' => 0.75
// );

// Hit counter
wfLoadExtension( 'HitCounters' );

// InputBox to have a search input on the home page
wfLoadExtension( 'InputBox' );

// https://www.mediawiki.org/wiki/Extension:Replace_Text
wfLoadExtension( 'ReplaceText' );

// Load the geo localisation SMW extension:
// https://maps.extension.wiki/wiki/Installation
wfLoadExtension( 'Maps' );
$egMapsDefaultService = 'leaflet';

wfLoadExtension( 'SemanticResultFormats' );

wfLoadExtension( 'CategoryTree' );
$wgCategoryTreeMaxDepth = 4;

$slackWebHook = getenv('SLACK_WEBHOOK', true);
if (!empty($slackWebHook))
{
    // https://github.com/kulttuuri/SlackNotifications
    wfLoadExtension( 'SlackNotifications' );
    $wgSlackIncomingWebhookUrl = $slackWebHook;
    $wgSlackFromName = "Triple Performance";
    $wgSlackNotificationWikiUrl = 'https:' . $wgServer . "/";
    $wgSlackNotificationWikiUrlEnding = "index.php?title=";
    $wgSlackIncludePageUrls = true;
    $wgSlackIncludeUserUrls = false;
    $wgSlackIgnoreMinorEdits = true;
    $wgSlackEmoji = ":tripleperformance:";
    $wgSlackExcludedPermission = "bot"; // bots and admin
}

// ROTTEN LINKS IS NOT COMPATIBLE WITH 1.39 - REACTIVATE WITH 1.40

// https://www.mediawiki.org/wiki/Extension:RottenLinks
// wfLoadExtension( 'RottenLinks' );


// https://www.mediawiki.org/wiki/Extension:LinkTitles
wfLoadExtension( 'LinkTitles' );
$wgLinkTitlesParseOnEdit = false;
$wgLinkTitlesParseOnRender = false;
$wgLinkTitlesSmartMode = true; // Case insensitive
$wgLinkTitlesSourceNamespaces = [NS_MAIN, NS_STRUCTURE, NS_TRAINING, NS_CATEGORY];
$wgLinkTitlesTargetNamespaces = [NS_MAIN, NS_STRUCTURE, NS_TRAINING, NS_CATEGORY, NS_USER];
$wgLinkTitlesSamenamespace = true;
$wgLinkTitlesSkipTemplates = true;
$wgLinkTitlesFirstOnly = true;
$wgLinkTitlesMinimumTitleLength = 3;
$wgLinkTitlesMaximumTitleLength = 25;
$wgLinkTitlesEnableNoTargetMagicWord = true;
$wgLinkTitlesCheckRedirect = false;

// https://github.com/neayi/mw-Piwigo
wfLoadExtension( 'Piwigo' );
$wgPiwigoURL = 'https://' . str_replace('wiki', 'photos', $domainName);
$wgPiwigoGalleryLayout = 'fluid'; // one of the four: fluid (default), grid, thumbnails, clean

// WikiSearch
wfLoadExtension( 'WikiSearch' );
$wgWikiSearchElasticSearchHosts	= [getenv('ELASTICSEARCH_SERVER', true)]; // ["localhost:9200"]	Sets the list of ElasticSearch hosts to use.
$wgWikiSearchAPIRequiredRights = ["read", "wssearch-execute-api"];
// $wgWikiSearchSearchFieldOverride = 'Search';

wfLoadExtension( 'WikiSearchFront' );

// https://github.com/neayi/mw-WikiSearchLink
wfLoadExtension( 'WikiSearchLink' );

// https://www.mediawiki.org/wiki/Extension:Graph
// wfLoadExtension( 'TemplateStyles' );
// wfLoadExtension( 'JsonConfig' );
// wfLoadExtension( 'Graph' );


wfLoadExtension( 'ECharts' );

require_once("$IP/extensions/UploadConvert/UploadConvert/UploadConvert.php");
extUploadConvert::filterByExtention('bmp','png','/usr/bin/convert bmp:%from% png:%to%', 'mandatory');
extUploadConvert::filterByExtention('tiff','png','/usr/bin/convert tiff:%from% png:%to%');
extUploadConvert::filterByExtention('webp','jpg','/usr/bin/convert webp:%from% jpg:%to%', 'mandatory');
extUploadConvert::filterByExtention('jpg','jpg','/usr/bin/convert %from% -resize 1200x1200\> jpg:%to%', 'mandatory');
extUploadConvert::filterByExtention('jpeg','jpeg','/usr/bin/convert %from% -resize 1200x1200\> jpg:%to%', 'mandatory');

wfLoadExtension( 'IFrameTag' );
$iFrameOnWikiConfig = true;
// See https://wiki.tripleperformance.fr/wiki/MediaWiki:Iframe-cfg.json

wfLoadExtension( 'FleurAgroecologie' );

wfLoadExtension( 'ConvertPDF2Wiki' );

wfLoadExtension( 'NeayiRelatedPages' );

wfLoadExtension( 'Math' );

// Debug and error reporting :

if ($debug) {
// error_reporting( -1 );
// ini_set( 'display_errors', 1 );
// $wgDebugLogFile = __DIR__ . '/debug.log';
// $wgShowDebug = true;

    $wgShowExceptionDetails = true;
    $wgDebugToolbar = false;
    $wgResourceLoaderDebug = true;
    $wgWikiSearchEnableDebugMode = true;
    $wgJobRunRate = 1;
}
