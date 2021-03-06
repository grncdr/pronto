<?php

/*
 * A NOTE ABOUT CACHING
 *
 * Most caching drivers do not provide any sort of permission structure, which
 * means that any other process on the server (including other web apps) could
 * potentially read the contents of your cache.  This isn't a problem on 
 * dedicated servers, but if the app will reside on a shared server and
 * you're concerned about privacy/security, you may want to disable caching.
 *
 */

/**
 * General Cache Settings
 *   USE_CACHE    :: enable/disable caching
 *   CACHE_DRIVER :: select the cache driver to use
 */
define('USE_CACHE',      false);
define('CACHE_DRIVER',  'file');

/**
 * Enable the cache manifest, which is a stored list of all keys in the
 * cache. If the manifest is enabled, you can use the Cache::delete_by_regex()
 * and RecordModel::invalidate_all() functions, which can be handy for
 * more powerful cache management.
 *
 * However, there are serious side effects.  There is a performance hit
 * involved in maintaining the manifest, and many cache systems may
 * expire/remove the manifest without telling you (eg, memcached).
 *
 * Carefully consider this before enabling it.
 */
define('CACHE_MANIFEST', false);


/***********************************************************************
 * DRIVER SPECIFIC SETTINGS
 ***********************************************************************/

/**
 * DRIVER: FILE
 *   CACHE_FILES_DIR :: full path to the cache directory.
 *
 *   NOTE: It's recommended to place your cache directory outside of
 *         the web root so people cannot access the cache files with
 *         a web browser.  If this is not feasible, then you should
 *         deny access to the directory with an .htaccess file.
 */ 
define('CACHE_FILES_DIR', DIR_FS_BASE.DS.'cache');

/**
 * DRIVER: SHM
 *   CACHE_SHM_SIZE :: total size of cache (bytes)
 */ 
define('CACHE_SHM_SIZE', 4194304); // total memory size used

/**
 * DRIVER: MemCache
 *   CACHE_MEMCACHE_SERVERS :: space-delimited list of host:port tuples
 */
define('CACHE_MEMCACHE_SERVERS', 'localhost:11211');

?>
