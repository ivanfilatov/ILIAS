<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */
/* Copyright (c) 2015 Richard Klees, Extended GPL, see docs/LICENSE */

require_once 'Services/Environment/classes/class.ilRuntime.php';

/**
* Error Handling & global info handling
* uses PEAR error class
*
* @author	Stefan Meyer <meyer@leifos.com>
* @author	Sascha Hofmann <shofmann@databay.de>
* @author	Richard Klees <richard.klees@concepts-and-training.de>
* @version	$Id$
* @extends PEAR
* @todo		when an error occured and clicking the back button to return to previous page the referer-var in session is deleted -> server error
* @todo		This class is a candidate for a singleton. initHandlers could only be called once per process anyways, as it checks for static $handlers_registered.
*/
include_once 'PEAR.php';

// TODO: This would clearly benefit from Autoloading...
require_once("./Services/Exceptions/lib/Whoops/Run.php");
require_once("./Services/Exceptions/lib/Whoops/Handler/HandlerInterface.php");
require_once("./Services/Exceptions/lib/Whoops/Handler/Handler.php");
require_once("./Services/Exceptions/lib/Whoops/Handler/CallbackHandler.php");
require_once("./Services/Exceptions/lib/Whoops/Handler/PrettyPageHandler.php");
require_once("./Services/Exceptions/lib/Whoops/Exception/Inspector.php");
require_once("./Services/Exceptions/lib/Whoops/Exception/ErrorException.php");
require_once("./Services/Exceptions/lib/Whoops/Exception/FrameCollection.php");
require_once("./Services/Exceptions/lib/Whoops/Exception/Frame.php");
require_once("./Services/Exceptions/lib/Whoops/Exception/Inspector.php");
require_once("./Services/Exceptions/lib/Whoops/Exception/Formatter.php");
require_once("./Services/Exceptions/lib/Whoops/Util/TemplateHelper.php");
require_once("./Services/Exceptions/lib/Whoops/Util/Misc.php");

require_once("Services/Exceptions/classes/class.ilDelegatingHandler.php");
require_once("Services/Exceptions/classes/class.ilPlainTextHandler.php");
require_once("Services/Exceptions/classes/class.ilTestingHandler.php");

use Whoops\Run;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Handler\CallbackHandler;
use Whoops\Exception\Inspector;

class ilErrorHandling extends PEAR
{
	/**
	* Toggle debugging on/off
	* @var		boolean
	* @access	private
	*/
	var $DEBUG_ENV;

	/**
	* Error level 1: exit application immedietly
	* @var		integer
	* @access	public
	*/
	var $FATAL;

	/**
	* Error level 2: show warning page
	* @var		integer
	* @access	public
	*/
	var $WARNING;

	/**
	* Error level 3: show message in recent page
	* @var		integer
	* @access	public
	*/
	var $MESSAGE;

	/**
	 * Are the error handlers already registered?
	 * @var bool
	 */
	protected static $handlers_registered = false;

	/**
	* Constructor
	* @access	public
	*/
	function ilErrorHandling()
	{
		$this->PEAR();

		// init vars
		$this->DEBUG_ENV = true;
		$this->FATAL	 = 1;
		$this->WARNING	 = 2;
		$this->MESSAGE	 = 3;

		$this->error_obj = false;
		
		$this->initHandlers();
	}
	
	/**
	 * Initialize Error and Exception Handlers.
	 *
	 * Initializes Whoops, a logging handler and a delegate handler for the late initialisation
	 * of an appropriate error handler.
	 *
	 * @return void
	 */
	protected function initHandlers() {
		if (self::$handlers_registered) {
			// Only register whoops error handlers once.
			return;
		}
		
		$ilRuntime = $this->getIlRuntime();
		$whoops = $this->getWhoops();
		
		$whoops->pushHandler(new ilDelegatingHandler($this));
		
		if ($ilRuntime->shouldLogErrors()) {
			$whoops->pushHandler($this->loggingHandler());
		}
		
		$whoops->register();
		
		self::$handlers_registered = true;
	}

	/**
	 * Get a handler for an error or exception.
	 *
	 * Uses Whoops Pretty Page Handler in DEVMODE and the legacy ILIAS-Error handlers otherwise.
	 *
	 * @return Whoops\Handler
	 */
	public function getHandler() {
		// TODO: * Use Whoops in production mode? This would require an appropriate
		//		   error-handler.
		//		 * Check for context? The current implementation e.g. would output HTML for
		//		   for SOAP.

		if ($this->isDevmodeActive()) {
			return $this->devmodeHandler();
		}

		return $this->defaultHandler();
	}

	function getLastError()
	{
		return $this->error_obj;
	}

	/**
	* defines what has to happen in case of error
	* @access	private
	* @param	object	Error
	*/
	function errorHandler($a_error_obj)
	{
		global $log;

		// see bug 18499 (some calls to raiseError do not pass a code, which leads to security issues, if these calls
		// are done due to permission checks)
		if ($a_error_obj->getCode() == null)
		{
			$a_error_obj->code = $this->WARNING;
		}

		$this->error_obj =& $a_error_obj;
//echo "-".$_SESSION["referer"]."-";
		if ($_SESSION["failure"] && substr($a_error_obj->getMessage(), 0, 22) != "Cannot find this block")
		{
			$m = "Fatal Error: Called raise error two times.<br>".
				"First error: ".$_SESSION["failure"].'<br>'.
				"Last Error:". $a_error_obj->getMessage();
			//return;
			$log->write($m);
			#$log->writeWarning($m);
			#$log->logError($a_error_obj->getCode(), $m);
			unset($_SESSION["failure"]);
			die ($m);
		}

		if (substr($a_error_obj->getMessage(), 0, 22) == "Cannot find this block")
		{
			if (DEVMODE == 1)
			{
				echo "<b>DEVMODE</b><br><br>";
				echo "<b>Template Block not found.</b><br>";
				echo "You used a template block in your code that is not available.<br>";
				echo "Native Messge: <b>".$a_error_obj->getMessage()."</b><br>";
				if (is_array($a_error_obj->backtrace))
				{
					echo "Backtrace:<br>";
					foreach ($a_error_obj->backtrace as $b)
					{
						if ($b["function"] == "setCurrentBlock" &&
							basename($b["file"]) != "class.ilTemplate.php")
						{
							echo "<b>";
						}
						echo "File: ".$b["file"].", ";
						echo "Line: ".$b["line"].", ";
						echo $b["function"]."()<br>";
						if ($b["function"] == "setCurrentBlock" &&
							basename($b["file"]) != "class.ilTemplate.php")
						{
							echo "</b>";
						}
					}
				}
				exit;
			}
			return;
		}

		if (is_object($log) and $log->enabled == true)
		{
			$log->write($a_error_obj->getMessage());
			#$log->logError($a_error_obj->getCode(),$a_error_obj->getMessage());
		}

//echo $a_error_obj->getCode().":"; exit;
		if ($a_error_obj->getCode() == $this->FATAL)
		{
			trigger_error(stripslashes($a_error_obj->getMessage()), E_USER_ERROR);
			exit();
		}

		if ($a_error_obj->getCode() == $this->WARNING)
		{
			if ($this->DEBUG_ENV)
			{
				$message = $a_error_obj->getMessage();
			}
			else
			{
				$message = "Under Construction";
			}

			$_SESSION["failure"] = $message;

			if (!defined("ILIAS_MODULE"))
			{
				ilUtil::redirect("error.php");
			}
			else
			{
				ilUtil::redirect("../error.php");
			}
		}

		if ($a_error_obj->getCode() == $this->MESSAGE)
		{
			$_SESSION["failure"] = $a_error_obj->getMessage();
			// save post vars to session in case of error
			$_SESSION["error_post_vars"] = $_POST;

			if (empty($_SESSION["referer"]))
			{
				$dirname = dirname($_SERVER["PHP_SELF"]);
				$ilurl = parse_url(ILIAS_HTTP_PATH);
				$subdir = substr(strstr($dirname,$ilurl["path"]),strlen($ilurl["path"]));
				$updir = "";

				if ($subdir)
				{
					$num_subdirs = substr_count($subdir,"/");

					for ($i=1;$i<=$num_subdirs;$i++)
					{
						$updir .= "../";
					}
				}
				ilUtil::redirect($updir."index.php");
			}

			/* #12104 
			check if already GET-Parameters exists in Referer-URI			 
			if (substr($_SESSION["referer"],-4) == ".php")
			{
				$glue = "?";
			}
			else
			{
			    // this did break permanent links (".html&")
				$glue = "&";
			}
			*/
			ilUtil::redirect($_SESSION["referer"]);			
		}
	}

	function getMessage()
	{
		return $this->message;
	}
	function setMessage($a_message)
	{
		$this->message = $a_message;
	}
	function appendMessage($a_message)
	{
		if($this->getMessage())
		{
			$this->message .= "<br /> ";
		}
		$this->message .= $a_message;
	}
	
	/**
	 * This is used in Soap calls to write PHP error in ILIAS Logfile
	 * Not used yet!!!
	 *
	 * @access public
	 * @static
	 *
	 * @param
	 */
	public static function _ilErrorWriter($errno, $errstr, $errfile, $errline)
	{
		global $ilLog;
		
		switch($errno)
		{
			case E_USER_ERROR:
				$ilLog->write('PHP errror: '.$errstr.'. FATAL error on line '.$errline.' in file '.$errfile);
				unset($ilLog);
				exit(1);
			
			case E_USER_WARNING:
				$ilLog->write('PHP warning: ['.$errno.'] '.$errstr.' on line '.$errline.' in file '.$errfile);
				break;
			
		}				
		return true;
	}
	
	/**
	 * Get ilRuntime.
	 * @return ilRuntime
	 */
	protected function getIlRuntime() {
		return ilRuntime::getInstance();
	}
	
	/**
	 * Get an instance of Whoops/Run.
	 * @return Whoops\Run
	 */
	protected function getWhoops() {
		return new Run();
	}
	
	/**
	 * Is the DEVMODE switched on?
	 * @return bool
	 */
	protected function isDevmodeActive() {
		return DEVMODE;
	}

	/**
	 * Get a default error handler.
	 * @return Whoops\Handler
	 */
	protected function defaultHandler() {
		return new CallbackHandler(function(Exception $exception, Inspector $inspector, Run $run) {
			if ($exception instanceof \Whoops\Exception\ErrorException
			and $exception->getCode() == E_ERROR) {
				global $tpl, $lng, $tree;
				$_SESSION["failure"] = $exception->getMessage();
				include("error.php");
				exit();
			}

			require_once("Services/Utilities/classes/class.ilUtil.php");
			ilUtil::sendFailure($exception->getMessage(), true);
			// many test installation have display_errors on, we do not need additional information in this case
			// however in cases of warnings, whoops seems to get rid of these messages, but not in the case of fatals
			// so we do not check for ini_get("display_errors"), but for headers_sent()
			if (!headers_sent())
			{
				// #0019268, when not in setup
				if (class_exists("ilInitialisation"))
				{
					ilInitialisation::initHTML();
					global $tpl, $lng, $tree;
					include("error.php");                // redirect will not display fatal error messages, since writing to session (sendFailure) will not work at this point
				}
				else	// when in setup...
				{
					ilUtil::redirect("error.php");
				}
			}
		});
	}

	/**
	 * Get the handler to be used in DEVMODE.
	 * @return Whoops\Handler
	 */
	protected function devmodeHandler() {
		global $ilLog;
		
		switch (ERROR_HANDLER) {
			case "TESTING":
				return new ilTestingHandler();
			case "PLAIN_TEXT":
				return new ilPlainTextHandler();
			case "PRETTY_PAGE":
				return new PrettyPageHandler();
			default:
				if ($ilLog) {
					$ilLog->write("Unknown or undefined error handler '".ERROR_HANDLER."'. "
								 ."Falling back to PrettyPageHandler.");
				}
				return new PrettyPageHandler();
		}
	}
	
	/**
	 * Get the handler to be used to log errors.
	 * @return Whoops\Handler
	 */
	protected function loggingHandler() {
		// TODO: remove this, when PHP 5.3 support is dropped. Make logMessageFor protected then as well.
		$self = $this;
		return new CallbackHandler(function(Exception $exception, Inspector $inspector, Run $run) use ($self) {
			/**
			 * Don't move this out of this callable
			 * @var ilLog $ilLog;
			 */
			global $ilLog;

			$log_message = $self->logMessageFor($exception, (bool)LOG_ERROR_TRACE);
			if(is_object($ilLog)) {
				// ak: default log level of write() is INFO, which is not appropriate -> set this to warning
				// include_once './Services/Logging/classes/public/class.ilLogLevel.php';	// include may fail, see 19837 (maybe due to temp changed dir)
				$ilLog->write($log_message, 300);
			}

			// Send to system logger
			error_log($log_message);
		});
	}

	/**
	 * Get the error message to be logged.
	 *
	 * TODO: Can be made protected when support for PHP 5.3. is dropped.
	 *
	 * @param	$exception	Exception
	 * @param	$log_trace	bool
	 * @return	string
	 */
	public function logMessageFor(Exception $exception, $log_trace) {
		assert('is_bool($log_trace)');
		$prefix = "PHP Error: ";
		if ($exception instanceof \Whoops\Exception\ErrorException) {
			switch ($exception->getCode()) {
				case E_ERROR:
				case E_USER_ERROR:
					$prefix = "PHP Fatal error: ";
			}
		}

		$msg = $prefix.$exception->getMessage()." in ".$exception->getFile()." on line ".$exception->getLine();

		if ($log_trace) {
			$msg .= "\n".$exception->getTraceAsString();
		}

		return $msg;
	}

} // END class.ilErrorHandling
?>
