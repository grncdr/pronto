<?php
/**
 * PRONTO WEB FRAMEWORK
 * @copyright Copyright (C) 2006, Judd Vinet
 * @author Judd Vinet <jvinet@zeroflux.org>
 *
 * Description: Base class for cache adapters
 *
 **/
class Cache
{
	// A list of all keys stored in the cache
	var $manifest;

	/**
	 * Constructor
	 */
	function Cache()
	{
		$this->manifest = array();
	}

	/**
	 * @param string $key Unique key name to store value under
	 * @param mixed $var Variable to store
	 * @param int $expire Expiration time (from now) in seconds.  Use zero to never expire.
	 */
	function set($key, $var, $expire=0)
	{
		$this->manifest[$key] = true;
	}

	function &get($key)
	{
		$a = false;
		return $a;
	}

	/**
	 * Return a value from the cache.  If the value doesn't exist in the cache,
	 * set it to the value of $default and return that.
	 *
	 * @param string $key
	 * @param mixed $default
	 */
	function get_or_set($key, $default=null)
	{
		$var = $this->get($key);
		if(!$var) {
			$var = $default;
			$this->set($key, $var);
		}
		return $var;
	}

	/**
	 * Return a value from the cache.  If the value doesn't exist in the cache,
	 * create a lambda function that returns the value of the evaluated code in
	 * $default, and set/return that value.
	 *
	 * @param string $key
	 * @param string $default The PHP code to evaluate (no trailing semicolon)
	 */
	function get_or_eval($key, $default='null')
	{
		$var = $this->get($key);
		if(!$var) {
			$fn = create_function('', "return $default;");
			$var = $fn();
			$this->set($key, $var);
		}
		return $var;
	}

	function delete($key)
	{
		unset($this->manifest[$key]);
	}

	/**
	 * Delete all entries that have cache keys matched by this regexp.
	 */
	function delete_by_regex($re)
	{
		$matches = preg_grep($re, array_keys($this->manifest));
		foreach($matches as $key) {
			$this->delete($key);
		}
	}

	/**
	 * Flush entire cache.
	 */
	function flush()
	{
		$this->manifest = array();
	}

	/**
	 * Expire old entries.
	 */
	function gc()
	{
	}

	/**
	 * Return some usage statistics.
	 */
	function stats()
	{
		return array();
	}
}

?>
