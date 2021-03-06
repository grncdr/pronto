<?php
/**
 * PRONTO WEB FRAMEWORK
 * @copyright Copyright (C) 2006, Judd Vinet
 * @author Judd Vinet <jvinet@zeroflux.org>
 *
 * Description: Page controller extension that's basically just a dummy
 *              frontend for a directory of templates.  Whatever argument
 *              is passed to it will be used as the template name.
 *
 **/

class Page_Static extends Page
{
	var $template_dir = 'home';

	// Number of path arguments to shift off before resolving the
	// remaining path to a template filename.
	//
	// Example:  /home/welcome
	//   with $path_shift=0, template file will be home__welcome.php
	//   with $path_shift=1, template file will be welcome.php
	var $path_shift = 0;

	/**
	 * Set the template directory
	 *
	 * @param string $dir Template directory
	 */
	function set_dir($dir)
	{
		$this->template_dir = $dir;
	}

	function GET()
	{
		$path = implode('__', $this->path_args($this->path_shift));
		if(empty($path)) $path = 'index';
		if(!preg_match('|^[A-z0-9+_-]+$|', $path)) {
			$this->web->notfound();
		}
		if(!file_exists(DIR_FS_APP.DS.'templates'.DS.$this->template_dir.DS."$path.php")) {
			$this->web->notfound();
		}
		$this->render($this->template_dir.DS."$path.php");
	}

}

?>
