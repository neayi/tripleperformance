<?php

/**
 * This script will exectute maintenance scripts on the right instance
 */

array_shift($argv); // wiki.php

if (empty($argv))
	echoUsageAndExit();

try {

    $targetEnv = array_shift($argv);
    if (isTargetEnv($targetEnv))
    {
        $script = array_shift($argv);
        $targetEnv = getTargetEnv($targetEnv);
    }
    else
    {
        $script = $targetEnv;
        $targetEnv = getTargetEnv();
    }
    
    $scriptPath = getScriptPath($script);
	
    $commandLines = array();

    if ($script == 'mysql')
    {
        if (!file_exists(__DIR__ . '/backup/.mysql.cnf'))
            throw new Exception("Please create a ./backup/.mysql.cnf file with \n[client]\npassword=somepassword", 1);

        $sqlFile = array_shift($argv);
        if (strpos($sqlFile, '.sql') === false)
            throw new Exception("please import a SQL file. Got $sqlFile", 1);
            
        $commandLines[] = getMysqlCommandLine($targetEnv, $scriptPath) .  ' < ' .  $sqlFile;        
    }
    else if ($script == 'mysqldump')
    {
        if (!file_exists(__DIR__ . '/backup/.mysql.cnf'))
            throw new Exception("Please create a ./backup/.mysql.cnf file with \n[client]\npassword=somepassword", 1);

        $sqlFile = array_shift($argv);
        if (strpos($sqlFile, '.sql') === false)
            throw new Exception("please export a SQL file. Got $sqlFile", 1);
        
        $commandLines[] = getMysqlCommandLine($targetEnv, $scriptPath) .  ' > ' .  $sqlFile;        
    }
    else if ($script == 'importDump.php')
    {
        // docker-compose -f docker-compose.prod.yml run --rm -v ~/youtube:/out web_preprod php /var/www/html/maintenance/importDump.php /out/$file
        while ($arg = array_shift($argv))
        {
            if (strpos($arg, '.xml') === false)
                throw new Exception("Please import only xml files. $arg given.", 1);
            
            $volume = '-v '.dirname($arg).':/out';
            $fullScript = $scriptPath . ' /out/' . basename($arg);

            $commandLines[] = getCommandLine($targetEnv, $fullScript, $volume);
        }

        // You might want to run rebuildrecentchanges.php to regenerate RecentChanges,
        // and initSiteStats.php to update page and revision counts
        $commandLines[] = getCommandLine($targetEnv, getScriptPath('rebuildrecentchanges.php'));
        $commandLines[] = getCommandLine($targetEnv, getScriptPath('initSiteStats.php'));               
    }
    else if ($script == 'importImages.php')
    {
        // docker-compose -f docker-compose.prod.yml run --rm -v ~/geco/images:/out web_preprod php /var/www/html/maintenance/importImages.php /out/
        $folder = array_pop($argv);
        $volume = '-v '.$folder.':/out';
        while ($arg = array_shift($argv)) {
            $scriptPath .= ' ' . $arg;
        }

        $fullScript = $scriptPath . ' /out/';

        $commandLines[] = getCommandLine($targetEnv, $fullScript, $volume);
    }    
    else
    {
        // Add the rest of the args:
        while ($arg = array_shift($argv)) {
            $scriptPath .= ' ' . $arg;
        }

        $commandLines[] = getCommandLine($targetEnv, $scriptPath);
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
	echo "Usage: wiki.php [--dev] [--prod] [--preprod] maintenanceScript\n";
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

        case 'mysql':
            return '/usr/bin/mysql';

        case 'mysqldump':
            return '/usr/bin/mysqldump';
            
        default:
            throw new Exception("Unrecognized maintenance script: $script", 1);
    }
}

/**
 * $volume -v ~/youtube:/out (in that case make sure the files you import are in /out)
 */
function getCommandLine($targetEnv, $script, $volume = '')
{        
    switch ($targetEnv) {
        case 'dev':
            return 'docker-compose run --user="www-data:www-data" --rm '.$volume.' web sh -c "'.$script.'"';
        
        case 'preprod':
            return 'docker-compose -f docker-compose.prod.yml run --user="www-data:www-data" --rm '.$volume.' web_preprod sh -c "'.$script.'"';

        case 'prod':
            return 'docker-compose -f docker-compose.prod.yml run --user="www-data:www-data" --rm '.$volume.' web sh -c "'.$script.'"';
    }
}


/**
 * $volume -v ~/youtube:/out (in that case make sure the files you import are in /out)
 */
function getMysqlCommandLine($targetEnv, $script)
{        
    $extraParams = '';

    if ($script =='/usr/bin/mysqldump')
        $extraParams = '--single-transaction';

    $volume = __DIR__ . '/backup:/backup';
    switch ($targetEnv) {
        case 'dev':
            return "docker-compose run --rm -v $volume db $script --defaults-extra-file=/backup/.mysql.cnf $extraParams -P 3306 -h db -u root wiki";
    
        case 'preprod':
            return "docker-compose -f docker-compose.prod.yml run --rm -v $volume db $script --defaults-extra-file=/backup/.mysql.cnf $extraParams -P 3306 -h db -u root wiki_preprod";

        case 'prod':
            return "docker-compose -f docker-compose.prod.yml run --rm -v $volume db $script --defaults-extra-file=/backup/.mysql.cnf $extraParams -P 3306 -h db -u root wiki_prod";
    }
}


