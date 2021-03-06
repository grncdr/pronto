<?php
/**
 * PRONTO WEB FRAMEWORK
 * @copyright Copyright (C) 2006, Judd Vinet
 * @author Judd Vinet <jvinet@zeroflux.org>
 *
 * Description: Object factory.  This class will instantiate objects and return
 *              them to the caller.
 *
 * A NOTE ABOUT PERFORMANCE:
 *   The Factory class is not very efficient without caching enabled.
 *   It uses a dynamic method of building file->class maps, and without
 *   a cache, it has to do this for each web request.  If performance
 *   is important to you, then it is highly recommended that you enable
 *   some form of caching.  See config/cache.php.
 *
 **/


/**
 * The current version of Pronto.
 */
define('PRONTO_VERSION', '0.6');

class Factory
{

	/**
	 * Return a new database connection object
	 *
	 * @param array $config Connection parameters (host,user,pass,name)
	 * @return object
	 */
	function &db($config)
	{
		if($config['type']) {
			$type = $config['type'];
		} else if(defined('DB_DRIVER')) {
			$type = DB_DRIVER;
		}
		switch($type) {
			case 'mysql':      $cn = 'DB_MySQL';      break;
			case 'postgresql': $cn = 'DB_PostgreSQL'; break;
			case 'mssql':      $cn = 'DB_MSSQL';      break;
			case 'sqlite':     $cn = 'DB_SQLite';     break;
			case 'sqlite3':    $cn = 'DB_SQLite3';    break;
			case 'odbc':       $cn = 'DB_ODBC';       break;
			case 'pdo':        $cn = 'DB_PDO';        break;
			case 'none':       return new stdClass;
		}
		if(is_array($config['opts'])) {
			$persistent = !!$config['opts']['persistent'];
		} else {
			$persistent = (defined('DB_PERSISTENT') && DB_PERSISTENT === true);
		}

		require_once(DIR_FS_PRONTO.DS.'core'.DS.'db.php');
		require_once(DIR_FS_PRONTO.DS.'core'.DS.'db'.DS.$type.'.php');

		return new $cn($config, $persistent);
	}

	/**
	 * Return a new cache object
	 *
	 * @param array $config Connection parameters (host,user,pass,name)
	 * @return object
	 */
	function &cache($type, $opts)
	{
		$cn = "Cache_$type";
		require_once(DIR_FS_PRONTO.DS.'core'.DS.'cache.php');
		require_once(DIR_FS_PRONTO.DS.'core'.DS.'cache'.DS.$type.'.php');

		if(!class_exists($cn)) {
			trigger_error("$cn class does not exist.");
			return null;
		}

		$obj = new $cn();
		$obj->set_options($opts);

		return $obj;
	}

	/**
	 * Return a new page controller object.
	 * If an existing controller object already exists, it will be used.
	 *
	 * @param string $name
	 * @return object
	 */
	function &page($name)
	{
		// don't re-use the same object -- they may store state that shouldn't
		// be shared
		//$o =& Registry::get("pronto:page:$name");
		//if($o) return $o;

		$filespec = array(DIR_FS_APP.DS.'pages'.DS.'*.php');
		if(defined('MODULES')) {
			foreach(explode(' ',MODULES) as $mod) {
				$filespec[] = DIR_FS_APP.DS.'modules'.DS.$mod.DS.'pages/*.php';
			}
		}
		require_once(DIR_FS_PRONTO.DS.'core'.DS.'sql.php');
		require_once(DIR_FS_PRONTO.DS.'core'.DS.'page.php');
		require_once(DIR_FS_APP.DS.'core'.DS.'page.php');
		require_once(DIR_FS_PRONTO.DS.'core'.DS.'page_crud.php');
		require_once(DIR_FS_PRONTO.DS.'core'.DS.'page_static.php');
		$o =& Factory::newobj('pages', 'p'.$name, $filespec);

		Registry::set("pronto:page:$name", $o);
		return $o;
	}

	/**
	 * Return a new data model object
	 * If an existing model object already exists, it will be used.
	 *
	 * @param string $name
	 * @return object
	 */
	function &model($name)
	{
		$o =& Registry::get("pronto:model:$name");
		if($o) return $o;

		$filespec = array(DIR_FS_APP.DS.'models'.DS.'*.php');
		if(defined('MODULES')) {
			foreach(explode(' ',MODULES) as $mod) {
				$filespec[] = DIR_FS_APP.DS.'modules'.DS.$mod.DS.'models'.DS.'*.php';
			}
		}
		require_once(DIR_FS_PRONTO.DS.'core'.DS.'record_model.php');
		require_once(DIR_FS_PRONTO.DS.'core'.DS.'record_selector.php');
		require_once(DIR_FS_APP.DS.'core'.DS.'record_model.php');

		// old model -- backwards compatibility
		require_once(DIR_FS_PRONTO.DS.'core'.DS.'model.php');
		require_once(DIR_FS_APP.DS.'core'.DS.'model.php');

		$o =& Factory::newobj('models', 'm'.$name, $filespec);
		Registry::set("pronto:model:$name", $o);
		return $o;
	}

	/**
	 * Return an ad-hoc ("on the fly") model object.  A fly-model is
	 * just an instance of RecordModel that's been configured to use
	 * the specified table.
	 *
	 * This is convenient for simple tables, when explicitly defining a
	 * model class may be overkill.
	 *
	 * @param string $table The database table that this fly-model
	 *                      will operate on.
	 * @return object
	 */
	function &fly_model($table)
	{
		require_once(DIR_FS_PRONTO.DS.'core'.DS.'record_model.php');
		require_once(DIR_FS_PRONTO.DS.'core'.DS.'record_selector.php');
		require_once(DIR_FS_APP.DS.'core'.DS.'record_model.php');

		$o = new RecordModel();
		$o->table = $table;
		// no caching for fly-models
		$o->enable_cache = false;

		return $o;
	}

	/**
	 * Return a new plugin object.
	 * If an existing plugin object already exists, it will be used.
	 *
	 * @param string $name
	 * @param string $type Type of plugin (page, template)
	 * @return object
	 */
	function &plugin($name, $type='page')
	{
		switch($type) {
			case 'page':
				$prefix = 'pp';
				$regname = 'pronto:plugins';
				break;
			case 'template':
				$prefix = 'tp';
				$regname = 'pronto:helpers';
				break;
		}
		$store =& Registry::get($regname);
		if(!$store) $store = new stdClass;

		// check if plugin already exists
		if(isset($store->$name)) return $store->$name;

		$filespec = array(
			DIR_FS_PRONTO.DS.'plugins'.DS.$type.DS.'*.php',
			DIR_FS_APP.DS.'plugins'.DS.$type.DS.'*.php'
		);
		if(defined('MODULES')) {
			foreach(explode(' ',MODULES) as $mod) {
				$filespec[] = DIR_FS_APP.DS.'modules'.DS.$mod.DS.'plugins'.DS.$type.DS.'*.php';
			}
		}
		require_once(DIR_FS_PRONTO.DS.'core'.DS.'plugin.php');
		require_once(DIR_FS_APP.DS.'core'.DS.'plugin.php');
		$o =& Factory::newobj("{$type}_plugins", $prefix.$name, $filespec);

		$store->$name =& $o;
		Registry::set("$regname", $store);
		return $o;
	}

	/**
	 * Return a new helper (aka "template plugin") object
	 * If an existing helper object already exists, it will be used.
	 *
	 * @param string $name
	 * @return object
	 */
	function &helper($name)
	{
		return Factory::plugin($name, 'template');
	}

	/********************************************************************
	 * INTERNAL METHODS
	 *******************************************************************/

	function &newobj($type, $name, $filespec)
	{
		$name = strtolower($name);
		$map =& Registry::get("pronto:map:$type");
		if($map == false) { 
			// look in the cache
			$cache =& Registry::get('pronto:cache');
			$map =& $cache->get("pronto:map:$type");
			if($map == false) {
				// okay, no one has it, so build it
				$files = array();
				foreach($filespec as $f) {
					$g = glob($f);
					if($g) $files = array_merge($files, $g);
				}
				$map = Factory::build_class_map($files);
				$cache->set("pronto:map:$type", $map);
			}
			Registry::set("pronto:map:$type", $map);
		}

		if(!isset($map[$name])) {
			$f = false;
			return $f;
		}

		if(preg_match('|_plugins$|', $type)) {
			// use the lazy-loading proxy class
			$obj = new LazyLoad($name, $map[$name]);
		} else {
			require_once($map[$name]);
			$obj = new $name();
		}
		return $obj;
	}

	function build_class_map($files) {
		$map = array();
		foreach($files as $f) {
			if(substr(basename($f), 0, 1) == '_') continue;
			$class_list = get_declared_classes();
			require_once($f);
			foreach(array_diff(get_declared_classes(), $class_list) as $c) {
				$map[strtolower($c)] = $f;
			}
		}
		return $map;
	}
}

?>
