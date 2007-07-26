<?php defined('SYSPATH') or die('No direct access allowed.');

(defined('E_RECOVERABLE_ERROR')) or (define('E_RECOVERABLE_ERROR', 4096));

// Set error handler
set_error_handler(array('Kohana', 'error_handler'), E_ALL ^ E_NOTICE);
// Set execption handler
set_exception_handler(array('Kohana', 'exception_handler'));
// Set autoloader
spl_autoload_register(array('Kohana', 'load_class'));
// Set shutdown handler
register_shutdown_function(array('Kohana', 'shutdown'));
// Start output buffering

final class Config {

	public static $conf;

	/**
	 * Return a config item
	 *
	 * @access  public
	 * @param   string
	 * @return  mixed
	 */
	public static function item($key)
	{
		// Configuration autoloading
		if (self::$conf == NULL)
		{
			require APPPATH.'config/config'.EXT;

			// Invalid config file
			(isset($config) AND is_array($config)) or die('Core configuration file is not valid.');

			// Normalize all paths to be absolute and have a trailing slash
			foreach($config['include_paths'] as $n => $path)
			{
				if (substr($path, 0, 1) !== '/')
				{
					$config['include_paths'][$n] = realpath(DOCROOT.$path).'/';
				}
				else
				{
					$config['include_paths'][$n] = rtrim($path, '/').'/';
				}
			}

			$config['include_paths'] = array_merge
			(
				array(APPPATH), // APPPATH first
				$config['include_paths'],
				array(SYSPATH)  // SYSPATH last
			);

			self::$conf = $config;
		}

		return (isset(self::$conf[$key]) ? self::$conf[$key] : FALSE);
	}

} // End Config class

final class Kohana {

	public static $registry;
	public static $instance;

	public static $ob_level;
	public static $error_types;

	public static $shutdown_events;

	public static function initialize()
	{
		$class = Router::$controller;

		require Router::$directory.Router::$controller.EXT;

		if ( ! class_exists($class))
		{
			throw new controller_not_found($class);
		}

		self::$instance = new $class;

		call_user_func_array(array(self::$instance, Router::$method), Router::$arguments);
	}

	public static function output($output)
	{
		$memory = function_exists('memory_get_usage') ? (memory_get_usage() / 1024/1024) : 0;

		return utf8::str_replace(
			array
			(
				'{kohana_version}',
				'{execution_time}',
				'{memory_usage}'
			),
			array
			(
				KOHANA_VERSION,
				Benchmark::get(SYSTEM_BENCHMARK.'_total_execution_time'),
				number_format($memory, 2)
			),
			$output
		);
	}

	public static function shutdown()
	{
		if ( ! empty(self::$shutdown_events))
		{
			foreach(array_reverse(self::$shutdown_events) as $event)
			{
				if ( ! is_array($event))
					continue;

				$func = $event[0];
				$args = isset($event[1]) ? (array) $event[1] : FALSE;

				if ($args == FALSE)
				{
					call_user_func($func);
				}
				else
				{
					call_user_func_array($func, $args);
				}
			}
		}
	}

	public static function error_handler($error, $message, $file, $line)
	{
		$error   = isset(self::$error_types[$error]) ? self::$error_types[$error] : 'Unknown Error';
		$file    = preg_replace('#^'.DOCROOT.'#', '', $file);

		$template = self::find_file('errors', 'php_error');

		while(ob_get_level() > self::$ob_level)
		{
			ob_end_clean();
		}

		ob_start(array('Kohana', 'output'));
		include $template;
		ob_end_flush();

		exit;
	}

	public static function exception_handler($exception)
	{
		while(ob_get_level() > self::$ob_level)
		{
			ob_end_clean();
		}

		die('Uncaught exeception: '.get_class($exception).' ('.$exception->getMessage().')');
	}

	public static function load_class($class)
	{
		$type  = preg_match('/_Model\b/u', $class) ? 'models' : 'libraries';
		$class = preg_replace('/(\bCore_|_Model\b)/u', '', $class);

		if (isset(self::$registry[$class]))
		{
			return self::$registry[$class];
		}

		try
		{
			require self::find_file('libraries', $class, TRUE);
		}
		catch (file_not_found $exception)
		{
			print $exception->getMessage().' Library not be loaded.';
			exit;
		}

		if ($extension = self::find_file('libraries', Config::item('subclass_prefix').$class))
		{
			require $extension;
		}
		else
		{
			eval('class '.$class.' extends Core_'.$class.' { }');
		}

		self::$registry[$class] = new $class();

		return self::$registry[$class];
	}

	public static function find_file($directory, $filename, $required = FALSE)
	{
		static $files;

		if ( ! is_array($files))
		{
			$files = array();
		}

		if (isset($files[$directory.'/'.$filename]))
		{
			return $files[$directory.'/'.$filename];
		}

		$search = $directory.'/'.$filename.EXT;

		foreach (Config::item('include_paths') as $path)
		{
			if (file_exists($path.$search) AND is_file($path.$search))
			{
				$files[$directory.'/'.$filename] = $path.$search;

				return $path.$search;
			}
		}

		if ($required == TRUE) throw new file_not_found($filename);
	}

	public static function load_hook($name)
	{
		if (Config::item('enable_hooks') AND $hook = self::findFile('hooks', $name))
		{
			require $hook;
		}
	}

}

Kohana::$ob_level = ob_get_level();
Kohana::$error_types = array
(
	E_RECOVERABLE_ERROR => 'Recoverable Error',
	E_ERROR             => 'Fatal Error',
	E_USER_ERROR        => 'Fatal Error',
	E_PARSE             => 'Syntax Error',
	E_NOTICE            => 'Runtime Message',
	E_WARNING           => 'Warning Message',
	E_USER_WARNING      => 'Warning Warning'
);

class file_not_found       extends Exception {}
class library_not_found    extends file_not_found {}
class controller_not_found extends file_not_found {}
class model_not_found      extends file_not_found {}
class helper_not_found     extends file_not_found {}