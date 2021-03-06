<?php
/**
 * PRONTO WEB FRAMEWORK
 * @copyright Copyright (C) 2006, Judd Vinet
 * @author Judd Vinet <jvinet@zeroflux.org>
 *
 * Description: Base class for all page controllers (action/view in MVC terms).
 *
 **/


class Page_Base
{
	var $web;
	var $db;
	var $validator;
	var $models;
	var $plugins;
	var $ajax;

	var $module_name; // if this controller is part of a module...

	var $template;
	var $template_layout;

	/**
	 * Constructor for all Page elements
	 *
	 */
	function Page_Base()
	{
		$this->web     =& Registry::get('pronto:web');
		$this->db      =& Registry::get('pronto:db:main');
		$this->models  =  new stdClass;
		$this->plugins =  new stdClass;

		$this->validator =& Registry::get('pronto:validator');

		$this->template = new Template();
		$this->set_module('');
		$this->set_layout('layout.php');

		// check if we're in AJAX mode or not
		switch(true) {
			case strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest':
			case !!$this->param('_ajax', $this->web->ajax): $ajax = true; break;
			default: $ajax = false;
		}
		$this->set_ajax($ajax);
		$this->template->set('_ajax', $this->ajax);

		// if form data/errors are present in the session, pass them to
		// the template and remove from the session.
		if(isset($_SESSION['form_data'])) {
			assert_type($_SESSION['form_data'], 'array');
			assert_type($_SESSION['form_errors'], 'array');
			foreach($_SESSION['form_data'] as $k=>$v) $this->template->set($k, $v);
			foreach($_SESSION['form_errors'] as $k=>$v) $this->template->set($k, $v);
			unset($_SESSION['form_data'], $_SESSION['form_errors']);
		}

		// load plugins
		$this->plugins =& Registry::get('pronto:plugins');

		if(method_exists($this, '__init__')) {
			$this->__init__();
		}
	}

	/**
	 * Declare this controller (and template object) to be part of a
	 * Pronto module.
	 *
	 * @param string $name Module name
	 */
	function set_module($name)
	{
		$this->module_name = $name;
		$this->template->set_module($name);
	}

	/**
	 * Enable/Disable AJAX mode.  When in AJAX mode, the Page::render()
	 * method will automatically call Page::ajax_render() instead.  Also,
	 * debugging output will be disabled in AJAX mode.
	 *
	 * @param boolean $enable Set to true to enable, false to disable.
	 */
	function set_ajax($enable=true)
	{
		$this->ajax = $enable;
		if(is_object($this->web)) $this->web->ajax = $this->ajax;
	}


	/********************************************************************
	 *
	 * MODEL/PLUGIN IMPORTERS
	 *
	 ********************************************************************/
 
	/**
	 * Instantiate one or more models and load them into the holder
	 *
	 * @param string $name,... Name(s) of model(s) to import
	 */
	function import_model($name)
	{
		foreach(func_get_args() as $name) {
			$this->models->$name =& Factory::model($name);
			if($this->models->$name === false) {
				trigger_error("Model $name does not exist");
				die;
			}
		}
	}

	/**
	 * Import a plugin (aka "page plugin").
	 * @param string $name Plugin name
	 */
	function &import_plugin($name)
	{
		foreach(func_get_args() as $name) {
			$class =& Factory::plugin($name, 'page');
			if($class === false) {
				trigger_error("Plugin $name does not exist");
				die;
			}
		}
		// reload $this->plugins
		$this->plugins =& Registry::get('pronto:plugins');
		if($class) return $class;
	}

	/********************************************************************
	 *
	 * HTTP CONTROLS
	 *
	 ********************************************************************/

	/**
	 * Issue a web redirect to a new relative URL
	 *
	 * @param string $url
	 */
	function redirect($url)
	{
		$this->web->redirect($url);
	}

	/**
	 * Issue a web redirect to the HTTP referrer, falling back to '/' if
	 * the referrer is not present
	 *
	 * @param string $default_url URL to redirect to if HTTP_REFERER is empty
	 */
	function redirect_to_referrer($default_url='')
	{
		if(!$default_url) $default_url = url('/');
		$url = $_SERVER['HTTP_REFERER'] ? $_SERVER['HTTP_REFERER'] : $default_url;
		if($this->ajax) {
			$this->ajax_render('', array('redirect_url'=>$url));
		} else {
			$this->web->redirect($url);
		}
	}

	/**
	 * Used by form handlers to redirect back to the calling form after a
	 * failed validation or other errors.
	 *
	 * @param string $url    URL to redirect to (leave blank to use referrer)
	 * @param array $data    Form data array
	 * @param array $errors  Form errors array
	 * @param array $name    The unique names used for the form data and errors.
	 *                       These are used when passing the data back to the
	 *                       template. Use this if you're handling multiple
	 *                       forms on one page. The first element is the name
	 *                       of the form data array, the second is the name
	 *                       of the form errors array.
	 */
	function return_to_form($url, $data, $errors=array(), $name=array('data','errors')) {
		if($this->ajax) {
			$vars = array($name[1] => $errors);
			$this->ajax_render('', $vars);
		} else {
			assert_type($_SESSION['form_data'], 'array');
			assert_type($_SESSION['form_errors'], 'array');
			$_SESSION['form_data'][$name[0]]   = $data;
			$_SESSION['form_errors'][$name[1]] = $errors;
			if($url) {
				$this->redirect($url);
			} else {
				$this->redirect_to_referrer();
			}
		}
	}

	/********************************************************************
	 *
	 * FLASH MESSAGE CONTROL
	 *
	 ********************************************************************/

	/**
	 * Set a flash message which will be displayed the next time a template is rendered
	 *
	 * @param string $message
	 */
	function flash($message)
	{
		if(!empty($message)) $_SESSION['_FLASH_MESSAGE'] = $message;
	}

	/**
	 * Return the currently-set flash message.
	 *
	 * @return string
	 */
	function flash_get()
	{
		return $_SESSION['_FLASH_MESSAGE'];
	}

	/**
	 * Check whether a flash message has been set.
	 *
	 * @return boolean
	 */
	function flash_isset()
	{
		return !empty($_SESSION['_FLASH_MESSAGE']);
	}

	/**
	 * Clear any flash message
	 */
	function flash_clear()
	{
		unset($_SESSION['_FLASH_MESSAGE']);
	}

	/********************************************************************
	 *
	 * REQUEST VARIABLE RETRIEVAL / MANIPULATION
	 *
	 ********************************************************************/

	/**
	 * Fetch a variable from the request arguments, using a default if
	 * it doesn't exist
	 *
	 * @param string $key Name of request variable to look for.  The search
	 *               order is URL,GET,POST, with latter
	 *               variables overwriting the former.
	 * @param string $default Default to use if variable is missing
	 * @return mixed
	 */
	function param($key, $default='')
	{
		$req = Registry::get('pronto:request_args');
		if(isset($req[$key])) {
			return $this->validator->prepare_input($req[$key]);
		}
		return $default;
	}

	/**
	 * Fetch a number of variables with a default of "" (empty string)
	 *
	 * @param string $name,... Names of request variables to fetch
	 * @return mixed
	 */
	function params()
	{
		$a = array();
		foreach(func_get_args() as $k) {
			$a[] = $this->param($k);
		}
		return $a;
	}

	/**
	 * Process an entire associative array of parameters and return it
	 *
	 * @param array $req Array to load variables from
	 *              (default is URL/GET/POST)
	 * @return array
	 */
	function load_input($req='')
	{
		if(!is_array($req)) $req = Registry::get('pronto:request_args');
		$ret = array();
		foreach($req as $k=>$v) {
			$ret[$k] = $this->validator->prepare_input($v);
		}
		return $ret;
	}

	/**
	 * Decode JSON from a GET/POST variable and return it.
	 *
	 * @param string $varname The GET/POST variable name that will contain
	 *                        the JSON data.  Default is 'json'.
	 * @return mixed
	 */
	function load_json($varname='json')
	{
		$json = $this->param($varname, '{}');
		return json_decode($json, true);
	}

	/**
	 * Return an array of arguments from the request path, ignoring
	 * the first $shift elements
	 *
	 * @param int $shift Number of elements to ignore
	 * @return array
	 */
	function path_args($shift=0)
	{
		return array_slice(explode('/', $this->web->context->path), $shift);
	}

	/**
	 * Set (or replace) a parameter in request argument list.
	 *
	 * @param string $key
	 * @param mixed $val
	 */
	function param_set($key, $val)
	{
		$ra =& Registry::get('pronto:request_args');
		$ra[$key] = $val;
		Registry::set('pronto:request_args', $ra);
	}

	/**
	 * Unset a parameter in request argument list.
	 *
	 * @param string $key
	 */
	function param_unset($key)
	{
		$ra =& Registry::get('pronto:request_args');
		unset($ra[$key]);
		Registry::set('pronto:request_args', $ra);
	}

	/********************************************************************
	 *
	 * TEMPLATE VARIABLES/RENDERING
	 *
	 ********************************************************************/

	/**
	 * Convenience function for template variables
	 * @param string $key
	 * @return mixed
	 */
	function tget($key)
	{
		return $this->template->get($key);
	}
	/**
	 * Convenience function for template variables
	 * @param mixed $key
	 * @param mixed $var
	 */
	function tset($key, $var)
	{
		$this->template->set($key, $var);
	}
	/**
	 * Convenience function for template variables
	 * @param string $key
	 */
	function tunset($key)
	{
		$this->template->un_set($key, $var);
	}
	/**
	 * Convenience function for template variables
	 * @param string $key
	 * @return bool
	 */
	function tisset($key)
	{
		return $this->template->is_set($key);
	}

	/**
	 * Set the layout (base template) file.
	 * @param string $filename
	 */
	function set_layout($filename)
	{
		$this->template_layout = $filename;
	}

	/**
	 * Fetch a page element.  Page elements function like normal
	 * page controllers, except they can only be called by other page
	 * controllers and they return rendered content of some kind.
	 *
	 * This method can be called through the Page class directly.
	 * Example: Page::render_element('MyPage', 'MyElement');
	 *
	 * @param string $pagename Name of the page class to use (exclude the 'p' prefix)
	 * @param string $element Name of the element to fetch
	 * @param array $args Array of arguments to be passed to the element method
	 * @param boolean $merge_vars If true, merge template variables to/from the
	 *                            the rendered element from/to the current page's
	 *                            scope.
	 * @return string Rendered content from the page element method
	 */
	function render_element($pagename, $element, $args=array(), $merge_vars=true)
	{
		$page =& Factory::page($pagename);
		if(!is_object($page)) {
			trigger_error("$pagename is not a valid Page name");
		}

		// can't merge vars if we're called as a class (eg, Page::render_element())
		if($this && $merge_vars) {
			// merge in template variables from the parent controller
			foreach($this->template->variables as $k=>$v) $page->template->set($k, $v);
		}

		$content = call_user_func_array(array(&$page, "ELEM_$element"), $args);

		// can't merge vars if we're called as a class (eg, Page::render_element())
		if($this && $merge_vars) {
			// merge in template variables from the rendered element
			foreach($page->template->variables as $k=>$v) $this->template->set($k, $v);
		}

		return $content;
	}

	/**
	 * Convenience function to fetch a template
	 * @param string $filename Template file to render
	 * @param array $vars Additional variables to include in template
	 * @param string $language I18N language to set before rendering template
	 */
	function fetch($filename, $vars=array(), $language=false)
	{
		return $this->template->fetch($filename, $vars, $language);
	}

	/**
	 * Convenience function to render a template
	 * @param string $filename Template file to render
	 * @param array $vars Additional variables to include in template
	 * @param string $layout Layout template to use.  Empty string for default ($this->template_layout) or set to false for none.
	 */
	function render($filename, $vars=array(), $layout='')
	{
		if($layout === '') $layout = $this->template_layout;

		if($this->ajax) {
			$this->ajax_render($filename);
		} else {
			$this->web->render($this->template, $filename, $vars, $layout);
		}
	}

	/**
	 * Convenience function to render a string through a layout.
	 * @param string $content Content to render
	 * @param array $vars Additional variables to include in template
	 * @param string $layout Layout template to use.  Empty string for default ($this->template_layout) or set to false for none.
	 */
	function render_string($content, $vars=array(), $layout='')
	{
		if($layout === '') $layout = $this->template_layout;

		$vars['CONTENT_FOR_LAYOUT'] = $content;
		$this->web->render($this->template, DIR_FS_APP.DS.'templates'.DS.$layout, $vars);
	}

	/**
	 * Render JSON. Useful for generating responses to AJAX requests.
	 * @param array $data Data that will be encoded into JSON and returned.
	 */
	function render_json($data)
	{
		// enable AJAX mode to debugging output will be suppressed
		$this->set_ajax(true);

		$this->web->header('Content-Type', 'application/json');
		echo json_encode($data);
	}

	/**
	 * Convenience function to render a template for an AJAX call
	 *
	 * @param string $filename Template file to render
	 * @param array $jsvars Additional JavaScript variables to pass back
	 */
	function ajax_render($filename, $jsvars=array())
	{
		$this->web->ajax_render($this->template, $filename, $jsvars);
	}

	/**
	 * Convenience function to pass back JavaScript as an AJAX response.
	 *
	 * @param string $js JavaScript to pass back via ajax_render()
	 */
	function ajax_exec($js='')
	{
		$this->web->ajax_exec($js);
	}

	/********************************************************************
	 *
	 * DEFAULT ACTION HANDLERS
	 *
	 ********************************************************************/

	/**
	 * The default GET action handler.  To be overridden by subclasses.
	 */
	function GET()
	{
		$this->web->notfound();
	}

	/*
	 * The default POST action handler.  To be overridden by subclasses.
	 */
	function POST()
	{
		$this->web->notfound();
	}

	/*
	 * The default PUT action handler.  To be overridden by subclasses.
	 */
	function PUT()
	{
		$this->web->notfound();
	}

	/*
	 * The default DELETE action handler.  To be overridden by subclasses.
	 */
	function DELETE()
	{
		$this->web->notfound();
	}

	/*
	 * The default HEAD action handler.  To be overridden by subclasses.
	 */
	function HEAD()
	{
		$this->web->notfound();
	}
}

?>
