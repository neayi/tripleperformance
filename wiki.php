<?php

/**
 * This script will exectute maintenance scripts on the right instance
 */

array_shift($argv); // wiki.php

if (empty($argv))
	echoUsageAndExit();

try {

    $targetEnv = '';
    $targetLanguage = 'fr';
    $script = '';

    $argsCopy = $argv;

    foreach ($argsCopy as $argument)
    {
        if (isTargetEnv($argument))
        {
            $targetEnv = getTargetEnv($argument);
            array_shift($argv);
            continue;
        }

        if (isLanguage($argument))
        {
            $targetLanguage = getLanguage($argument);
            array_shift($argv);
            continue;
        }

        if (empty($targetEnv))
            $targetEnv = getTargetEnv();

        $script = $argument;
        array_shift($argv);

        break;
    }

    $scriptPath = getScriptPath($script);
    $targetLanguages = array();
    if ($targetLanguage == 'all-languages')
        $targetLanguages = getAllLanguages();
    else
        $targetLanguages[] = $targetLanguage;

    $commandLines = array();

    if ($script == 'mysql')
    {
        if (!file_exists(__DIR__ . '/backup/.mysql.cnf'))
            throw new Exception("Please create a ./backup/.mysql.cnf file with \n[client]\npassword=somepassword", 1);

        $sqlFile = array_shift($argv);

        if(empty($sqlFile)) {
            // No file specified, open a mysql shell
            $commandLines[] = getMysqlCommandLine($targetEnv, $targetLanguage, $scriptPath);
        } elseif (strpos($sqlFile, '.sql') !== false) {
            // A file is specified, import it
            $commandLines[] = getMysqlCommandLine($targetEnv, $targetLanguage, $scriptPath, $sqlFile);
        } else {
            throw new Exception("please import a SQL file. Got $sqlFile", 1);
        }
    }
    else if ($script == 'mysqldump')
    {
        if (!file_exists(__DIR__ . '/backup/.mysql.cnf'))
            throw new Exception("Please create a ./backup/.mysql.cnf file with \n[client]\npassword=somepassword", 1);

        $sqlFile = array_shift($argv);
        if (strpos($sqlFile, '.sql') === false)
            throw new Exception("please export a SQL file. Got $sqlFile", 1);

        $commandLines[] = getMysqlCommandLine($targetEnv, $targetLanguage, $scriptPath, $sqlFile);
    }
    else if ($script == 'importDump.php')
    {
        // docker compose -f docker-compose.prod.yml run --rm -v ~/youtube:/out web_preprod php /var/www/html/maintenance/importDump.php /out/$file
        while ($arg = array_shift($argv))
        {
            if (strpos($arg, '.xml') === false)
                throw new Exception("Please import only xml files. $arg given.", 1);

            $volume = '-v '.dirname($arg).':/out';
            $fullScript = $scriptPath . ' /out/' . basename($arg);
            foreach ($targetLanguages as $targetLanguage)
                $commandLines[] = getCommandLine($targetEnv, $targetLanguage, $fullScript, $volume, false);
        }

        // You might want to run rebuildrecentchanges.php to regenerate RecentChanges,
        // and initSiteStats.php to update page and revision counts
        foreach ($targetLanguages as $targetLanguage)
        {
            $commandLines[] = getCommandLine($targetEnv, $targetLanguage, getScriptPath('rebuildrecentchanges.php'), '', false);
            $commandLines[] = getCommandLine($targetEnv, $targetLanguage, getScriptPath('initSiteStats.php'), '', false);
        }
    }
    else if ($script == 'initElasticSearch.php')
    {
        foreach ($targetLanguages as $targetLanguage)
        {
            $commandLines[] = getCommandLine($targetEnv, $targetLanguage, getScriptPath('UpdateSearchIndexConfig.php') . ' --startOver', '', false);
            $commandLines[] = getCommandLine($targetEnv, $targetLanguage, getScriptPath('ForceSearchIndex.php') . ' --skipLinks --indexOnSkip', '', false);
            $commandLines[] = getCommandLine($targetEnv, $targetLanguage, getScriptPath('ForceSearchIndex.php') . ' --skipParse', '', false);
            $commandLines[] = getCommandLine($targetEnv, $targetLanguage, getScriptPath('runJobs.php'), '', false);
            $commandLines[] = getCommandLine($targetEnv, $targetLanguage, getScriptPath('rebuildElasticIndex.php'), '', false);
            $commandLines[] = getCommandLine($targetEnv, $targetLanguage, getScriptPath('runJobs.php'), '', false);
        }
    }
    else if ($script == 'buildSitemap.php')
    {
        $commandLines[] = getExecCommandLine($targetEnv, getScriptPath('buildSitemap.php'), true);
    }
    else if ($script == 'frequent_jobs')
    {
        foreach ($targetLanguages as $targetLanguage)
            $commandLines[] = getCommandLine($targetEnv, $targetLanguage, getScriptPath('runJobs.php') . ' --maxtime=1000 --memory-limit=256M', '', false, true);
    }
    else if ($script == 'daily_jobs')
    {
        foreach ($targetLanguages as $targetLanguage)
        {
            $commandLines[] = getCommandLine($targetEnv, $targetLanguage, getScriptPath('generateSitemap.php') . ' --memory-limit=50M --fspath=html/images/'.$targetLanguage.'/sitemap/ --identifier='.$targetLanguage.' --urlpath=/images/'.$targetLanguage.'/sitemap/ --compress=no --skip-redirects', '', false, true);
            $commandLines[] = getCommandLine($targetEnv, $targetLanguage, getScriptPath('rebuildData.php') . ' --quiet --shallow-update', '', false, true);
            $commandLines[] = getCommandLine($targetEnv, $targetLanguage, getScriptPath('disposeOutdatedEntities.php') . ' --quiet', '', false, true);
            $commandLines[] = getCommandLine($targetEnv, $targetLanguage, getScriptPath('rebuildPropertyStatistics.php') . ' --quiet', '', false, true);
            $commandLines[] = getCommandLine($targetEnv, $targetLanguage, getScriptPath('rebuildConceptCache.php') . ' --quiet --update --create', '', false, true);
        }

        $commandLines[] = getExecCommandLine($targetEnv, getScriptPath('buildSitemap.php'), true);
    }
    else if ($script == 'weekly_jobs')
    {
        foreach ($targetLanguages as $targetLanguage)
        {
            $commandLines[] = getCommandLine($targetEnv, $targetLanguage, getScriptPath('rebuildElasticIndex.php'), '', false, true);

            $commandLines[] = getCommandLine($targetEnv, $targetLanguage, getScriptPath('rebuildData.php') . ' -p --quiet -d 50', '', false, true);

            $size = 5000;
            $startId = 0;
            while ($startId < 101000)
            {
                $endId = $startId + $size;
//                $commandLines[] = getCommandLine($targetEnv, $targetLanguage, getScriptPath('rebuildData.php') . "  -s $startId -e $endId --quiet -d 50", '', false);
                $commandLines[] = getCommandLine($targetEnv, $targetLanguage, getScriptPath('rebuildData.php') . "  --startidfile=/var/www/html/images/'.$targetLanguage.'/rebuildDataIndex.txt -n $size --quiet -d 50", '', false, true);
                $startId = $endId + 1;
            }


            $commandLines[] = getCommandLine($targetEnv, $targetLanguage, getScriptPath('setupStore.php') . ' --quiet --skip-import', '', false, true);
        }
    }
    else if ($script == 'monthly_jobs')
    {
        foreach ($targetLanguages as $targetLanguage)
        {
            $commandLines[] = getCommandLine($targetEnv, $targetLanguage, getScriptPath('removeDuplicateEntities.php') . ' --quiet', '', false);
        }
    }
    else if ($script == 'importImages.php')
    {
        // docker compose -f docker-compose.prod.yml run --rm -v ~/geco/images:/out web_preprod php /var/www/html/maintenance/importImages.php /out/
        $folder = array_pop($argv);
        $volume = '-v "'.$folder.'":/out';
        while ($arg = array_shift($argv)) {
            $scriptPath .= ' ' . $arg;
        }

        $fullScript = $scriptPath . ' /out/';

        foreach ($targetLanguages as $targetLanguage)
            $commandLines[] = getCommandLine($targetEnv, $targetLanguage, $fullScript, $volume, false);
    }
    else
    {
        // Add the rest of the args:
        while ($arg = array_shift($argv)) {
            $scriptPath .= ' ' . $arg;
        }

        foreach ($targetLanguages as $targetLanguage)
        {
            if ($script == 'update.php')
                $commandLines[] = getCommandLine($targetEnv, $targetLanguage, $scriptPath . ' --quick', '', false);
            else
                $commandLines[] = getCommandLine($targetEnv, $targetLanguage, $scriptPath);
        }
    }

    foreach ($commandLines as $commandLine)
    {
        echo $commandLine . "\n";
        $return_var = 0;
        passthru($commandLine, $return_var);

        if ($return_var !== 0)
        {
            $cwd = getcwd();
            throw new \RuntimeException("Error while executing: $commandLine in $cwd");
        }
    }

} catch (\Throwable $th) {
	echo 'Exception: ',  $th->getMessage(), "\n";
	exit(1); // Important: throw an error in order to stop the build
}

exit(0); // Success.

function echoUsageAndExit()
{
	echo "Usage: wiki.php [--all-languages] [--fr] [--dev] [--prod] [--preprod] maintenanceScript\n";
	exit(0);
}

function isTargetEnv($env)
{
    switch ($env) {
        case '--dev':
        case '--prod':
        case '--preprod':
            return true;
    }

    return false;
}

function getTargetEnv($env = '')
{
    $envData = parse_ini_file(__DIR__ . '/.env');

    if (empty($envData['ENV']))
        throw new Exception("No .env file configured. Aborting", 1);

    switch ($envData['ENV']) {
        case 'dev':
            if (empty($env) || $env == '--dev')
                return 'dev';

            throw new Exception("We are in dev environnement. Aborting", 1);

        case 'prod':
            if ($env == '--preprod')
                return 'preprod';

            if ($env == '--prod')
                return 'prod';

            throw new Exception("Please tell me --prod or --preprod. Aborting", 1);

        default:
            throw new Exception(".env not configured. Aborting", 1);
    }
}

function isLanguage($env)
{
    switch ($env) {
        case '--de':
        case '--en':
        case '--es':
        case '--fr':
        case '--it':
        case '--nl':
        case '--pl':
        case '--all-languages':
            return true;
    }

    return false;
}

function getLanguage($env)
{
    switch ($env) {
        case '--de':
        case '--en':
        case '--es':
        case '--fr':
        case '--it':
        case '--nl':
        case '--pl':
        case '--all-languages':
            return trim($env, '-');
    }

    throw new Exception("Unknown language", 1);
}

function getAllLanguages()
{
    return ['fr',
            'en'];
}

function getScriptPath($script)
{
    switch ($script) {
        case 'Maintenance.php' :
        case 'addChangeTag.php' :
        case 'addRFCandPMIDInterwiki.php' :
        case 'addSite.php' :
        case 'attachLatest.php' :
        case 'blockUsers.php' :
        case 'categoryChangesAsRdf.php' :
        case 'changePassword.php' :
        case 'checkBadRedirects.php' :
        case 'checkComposerLockUpToDate.php' :
        case 'checkDependencies.php' :
        case 'checkImages.php' :
        case 'checkLess.php' :
        case 'checkUsernames.php' :
        case 'cleanupAncientTables.php' :
        case 'cleanupBlocks.php' :
        case 'cleanupCaps.php' :
        case 'cleanupEmptyCategories.php' :
        case 'cleanupImages.php' :
        case 'cleanupInvalidDbKeys.php' :
        case 'cleanupPreferences.php' :
        case 'cleanupRemovedModules.php' :
        case 'cleanupRevActorPage.php' :
        case 'cleanupSpam.php' :
        case 'cleanupTitles.php' :
        case 'cleanupUploadStash.php' :
        case 'cleanupUsersWithNoId.php' :
        case 'cleanupWatchlist.php' :
        case 'clearInterwikiCache.php' :
        case 'compareParserCache.php' :
        case 'compareParsers.php' :
        case 'convertExtensionToRegistration.php' :
        case 'convertLinks.php' :
        case 'convertUserOptions.php' :
        case 'copyFileBackend.php' :
        case 'copyJobQueue.php' :
        case 'createAndPromote.php' :
        case 'createBotPassword.php' :
        case 'deduplicateArchiveRevId.php' :
        case 'deleteArchivedFiles.php' :
        case 'deleteArchivedRevisions.php' :
        case 'deleteAutoPatrolLogs.php' :
        case 'deleteBatch.php' :
        case 'deleteDefaultMessages.php' :
        case 'deleteEqualMessages.php' :
        case 'deleteLocalPasswords.php' :
        case 'deleteOldRevisions.php' :
        case 'deleteOrphanedRevisions.php' :
        case 'deleteSelfExternals.php' :
        case 'deleteTag.php' :
        case 'doMaintenance.php' :
        case 'dumpBackup.php' :
        case 'dumpCategoriesAsRdf.php' :
        case 'dumpIterator.php' :
        case 'dumpLinks.php' :
        case 'dumpTextPass.php' :
        case 'dumpUploads.php' :
        case 'edit.php' :
        case 'emptyUserGroup.php' :
        case 'eraseArchivedFile.php' :
        case 'eval.php' :
        case 'exportSites.php' :
        case 'fetchText.php' :
        case 'fileOpPerfTest.php' :
        case 'findBadBlobs.php' :
        case 'findDeprecated.php' :
        case 'findMissingActors.php' :
        case 'findMissingFiles.php' :
        case 'findOrphanedFiles.php' :
        case 'fixDefaultJsonContentPages.php' :
        case 'fixDoubleRedirects.php' :
        case 'fixExtLinksProtocolRelative.php' :
        case 'fixTimestamps.php' :
        case 'fixUserRegistration.php' :
        case 'formatInstallDoc.php' :
        case 'generateJsonI18n.php' :
        case 'generateLocalAutoload.php' :
        case 'generateSchemaSql.php' :
        case 'generateSitemap.php' :
        case 'getConfiguration.php' :
        case 'getLagTimes.php' :
        case 'getReplicaServer.php' :
        case 'getText.php' :
        case 'importDump.php' :
        case 'importImages.php' :
        case 'importSiteScripts.php' :
        case 'importSites.php' :
        case 'importTextFiles.php' :
        case 'initEditCount.php' :
        case 'initSiteStats.php' :
        case 'initUserPreference.php' :
        case 'install.php' :
        case 'invalidateUserSessions.php' :
        case 'jsparse.php' :
        case 'lag.php' :
        case 'makeTestEdits.php' :
        case 'manageForeignResources.php' :
        case 'manageJobs.php' :
        case 'mcc.php' :
        case 'mctest.php' :
        case 'mergeMessageFileList.php' :
        case 'migrateActors.php' :
        case 'migrateArchiveText.php' :
        case 'migrateComments.php' :
        case 'migrateFileRepoLayout.php' :
        case 'migrateImageCommentTemp.php' :
        case 'migrateUserGroup.php' :
        case 'minify.php' :
        case 'moveBatch.php' :
        case 'mwdoc-filter.php' :
        case 'mwdocgen.php' :
        case 'mysql.php' :
        case 'namespaceDupes.php' :
        case 'nukeNS.php' :
        case 'nukePage.php' :
        case 'orphans.php' :
        case 'pageExists.php' :
        case 'parse.php' :
        case 'patchSql.php' :
        case 'populateArchiveRevId.php' :
        case 'populateBacklinkNamespace.php' :
        case 'populateCategory.php' :
        case 'populateChangeTagDef.php' :
        case 'populateContentTables.php' :
        case 'populateExternallinksIndex60.php' :
        case 'populateFilearchiveSha1.php' :
        case 'populateImageSha1.php' :
        case 'populateInterwiki.php' :
        case 'populateIpChanges.php' :
        case 'populateLogSearch.php' :
        case 'populateLogUsertext.php' :
        case 'populatePPSortKey.php' :
        case 'populateParentId.php' :
        case 'populateRecentChangesSource.php' :
        case 'populateRevisionLength.php' :
        case 'populateRevisionSha1.php' :
        case 'preprocessDump.php' :
        case 'preprocessorFuzzTest.php' :
        case 'protect.php' :
        case 'pruneFileCache.php' :
        case 'purgeChangedFiles.php' :
        case 'purgeChangedPages.php' :
        case 'purgeExpiredBlocks.php' :
        case 'purgeExpiredUserrights.php' :
        case 'purgeExpiredWatchlistItems.php' :
        case 'purgeList.php' :
        case 'purgeModuleDeps.php' :
        case 'purgeOldText.php' :
        case 'purgePage.php' :
        case 'purgeParserCache.php' :
        case 'reassignEdits.php' :
        case 'rebuildFileCache.php' :
        case 'rebuildImages.php' :
        case 'rebuildLocalisationCache.php' :
        case 'rebuildall.php' :
        case 'rebuildmessages.php' :
        case 'rebuildrecentchanges.php' :
        case 'rebuildtextindex.php' :
        case 'recountCategories.php' :
        case 'refreshExternallinksIndex.php' :
        case 'refreshFileHeaders.php' :
        case 'refreshImageMetadata.php' :
        case 'refreshLinks.php' :
        case 'removeInvalidEmails.php' :
        case 'removeUnusedAccounts.php' :
        case 'renameDbPrefix.php' :
        case 'renameRestrictions.php' :
        case 'renderDump.php' :
        case 'resetAuthenticationThrottle.php' :
        case 'resetPageRandom.php' :
        case 'resetUserEmail.php' :
        case 'resetUserTokens.php' :
        case 'rollbackEdits.php' :
        case 'runBatchedQuery.php' :
        case 'runJobs.php' :
        case 'runScript.php' :
        case 'shell.php' :
        case 'showJobs.php' :
        case 'showSiteStats.php' :
        case 'sql.php' :
        case 'sqlite.php' :
        case 'syncFileBackend.php' :
        case 'tidyUpT39714.php' :
        case 'undelete.php' :
        case 'update.php' :
        case 'updateArticleCount.php' :
        case 'updateCollation.php' :
        case 'updateCredits.php' :
        case 'updateDoubleWidthSearch.php' :
        case 'updateExtensionJsonSchema.php' :
        case 'updateRestrictions.php' :
        case 'updateSearchIndex.php' :
        case 'updateSpecialPages.php' :
        case 'uppercaseTitlesForUnicodeTransition.php' :
        case 'userOptions.php' :
        case 'validateRegistrationFile.php' :
        case 'view.php' :
        case 'wrapOldPasswords.php' :
            // Regular MW scripts:
            return "php /var/www/html/maintenance/$script";

        case 'setupElasticSearch.sh' :
            return "/var/www/html/maintenance/$script";

        case 'CheckIndexes.php' :
        case 'CirrusNeedsToBeBuilt.php' :
        case 'CopySearchIndex.php' :
        case 'DumpIndex.php' :
        case 'ForceSearchIndex.php' :
        case 'FreezeWritesToCluster.php' :
        case 'IndexNamespaces.php' :
        case 'Metastore.php' :
        case 'RunSearch.php' :
        case 'Saneitize.php' :
        case 'SaneitizeJobs.php' :
        case 'UpdateDYMIndexTemplates.php' :
        case 'UpdateOneSearchIndexConfig.php' :
        case 'UpdateSearchIndexConfig.php' :
        case 'UpdateSuggesterIndex.php' :
        case 'elasticsearch-scripts' :
            return "php /var/www/html/extensions/CirrusSearch/maintenance/$script";

        case 'disposeOutdatedEntities.php' :
        case 'dumpRDF.php' :
        case 'populateHashField.php' :
        case 'purgeEntityCache.php' :
        case 'rebuildConceptCache.php' :
        case 'rebuildData.php' :
        case 'rebuildElasticIndex.php' :
        case 'rebuildElasticMissingDocuments.php' :
        case 'rebuildFulltextSearchTable.php' :
        case 'rebuildPropertyStatistics.php' :
        case 'removeDuplicateEntities.php' :
        case 'runImport.php' :
        case 'runLocalMessageCopy.php' :
        case 'setupStore.php' :
        case 'updateEntityCollation.php' :
        case 'updateEntityCountMap.php' :
        case 'updateQueryDependencies.php' :
            // Semantic MediaWiki
            return "php /var/www/html/extensions/SemanticMediaWiki/maintenance/$script";

        case 'removeSpamAccounts.php' :
        case 'buildSitemap.php':
	        // NeayiAuth
            return "php /var/www/html/extensions/NeayiAuth/maintenance/$script";

        case 'runSyncWithMailchimp.php' :
            return "php /var/www/html/extensions/TriplePerformanceKitchen/bin/$script";

        case 'updateExternalLinks.php' :
            // RottenLinks
            return "php /var/www/html/extensions/RottenLinks/maintenance/$script";

        case 'mysql':
            return '/usr/bin/mysql';

        case 'mysqldump':
            return '/usr/bin/mysqldump';

        case 'build_project.php':
            return 'php /var/www/bin/build_project.php';

        // Pseudo scripts
        case 'initElasticSearch.php':
        case 'frequent_jobs':
        case 'daily_jobs':
        case 'weekly_jobs':
        case 'monthly_jobs':
            return $script;

        default:
            throw new Exception("Unrecognized maintenance script: $script", 1);
    }
}

/**
 * $volume -v ~/youtube:/out (in that case make sure the files you import are in /out)
 */
function getCommandLine($targetEnv, $targetLanguage, $script, $volume = '', $bUseWwwData = true, $bCronMode = false)
{
    $runOptions = "--rm $volume ";
    if ($bUseWwwData)
        $runOptions .= ' --user=www-data:www-data ';

    if ($bCronMode)
        $runOptions .= ' --no-TTY ';

    $runOptions .= " --env MW_INSTALL_PATH=/var/www/html --env WIKI_LANGUAGE=$targetLanguage ";

    $dir = __DIR__ . '/';

    switch ($targetEnv) {
        case 'dev':
            return 'docker compose -f '.$dir.'docker-compose.yml -f '.$dir.'docker-compose.workers.yml run '. $runOptions.' web sh -c "'.$script.'"';

        case 'preprod':
            return 'docker compose -f '.$dir.'docker-compose.prod.yml -f '.$dir.'docker-compose.workers.yml run '.$runOptions.' web_preprod sh -c "'.$script.'"';

        case 'prod':
            return 'docker compose -f '.$dir.'docker-compose.prod.yml -f '.$dir.'docker-compose.workers.yml  run '.$runOptions.' web sh -c "'.$script.'"';
    }
}

/**
 * Note: When we execute a command on the container, there's no possibility to specify which language to operate
 */
function getExecCommandLine($targetEnv, $script, $bCronMode = false)
{
    $runOptions = '';
    if ($bCronMode)
        $runOptions .= ' --no-TTY ';

    $dir = __DIR__ . '/';

    switch ($targetEnv) {
        case 'dev':
            return 'docker compose -f '.$dir.'docker-compose.yml -f '.$dir.'docker-compose.workers.yml exec '. $runOptions.' web sh -c "'.$script.'"';

        case 'preprod':
            return 'docker compose -f '.$dir.'docker-compose.prod.yml -f '.$dir.'docker-compose.workers.yml exec '.$runOptions.' web_preprod sh -c "'.$script.'"';

        case 'prod':
            return 'docker compose -f '.$dir.'docker-compose.prod.yml -f '.$dir.'docker-compose.workers.yml  exec '.$runOptions.' web sh -c "'.$script.'"';
    }
}

function getMysqlCommandLine($targetEnv, $targetLanguage, $script, $sqlBatchFile = '')
{
    $extraParams = '--silent';

    if ($script =='/usr/bin/mysqldump')
        $extraParams .= ' --single-transaction';

    if (!empty($sqlBatchFile) && strpos($script, 'mysqldump') === false)
        $sqlBatchFile = " < $sqlBatchFile";
    else if (!empty($sqlBatchFile) && strpos($script, 'mysqldump') !== false)
        $sqlBatchFile = " > $sqlBatchFile";

    $dir = __DIR__ . '/';

    if (strpos($sqlBatchFile, "wiki"))
    {
        switch ($targetLanguage)
        {
            case 'de':
            case 'en':
            case 'es':
            case 'it':
            case 'nl':
            case 'pl':
                $dbname = "wiki_$targetLanguage";
                break;

            case 'fr':
                $dbname = 'wiki';
        }
    }
    else if (strpos($sqlBatchFile, "insights"))
        $dbname = 'insights';
    else
        throw new Exception("Unrecognized DB name", 1);

    $volume = __DIR__ . '/backup:/backup';
    switch ($targetEnv) {
        case 'dev':
            return "docker compose run --rm -v $volume db sh -c \"$script --defaults-extra-file=/backup/.mysql.cnf $extraParams -P 3306 -h db -u root $dbname $sqlBatchFile\"";

        case 'preprod':
            return "docker compose -f '.$dir.'docker-compose.prod.yml run --rm -v $volume db sh -c \"$script --defaults-extra-file=/backup/.mysql.cnf $extraParams -P 3306 -h db -u root ".$dbname."_preprod $sqlBatchFile\"";

        case 'prod':
            return "docker compose -f '.$dir.'docker-compose.prod.yml run --rm -v $volume db sh -c \"$script --defaults-extra-file=/backup/.mysql.cnf $extraParams -P 3306 -h db -u root ".$dbname."_prod $sqlBatchFile\"";
    }
}


