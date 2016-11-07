<?php

namespace crazedsanity\database;
use crazedsanity\core\ToolBox;
use crazedsanity\core\LockFile;

//TODO: make this work for more than just PostgreSQL.
abstract class TestDbAbstract extends \PHPUnit_Framework_TestCase {
	
	public $dbParams=array();
	public $dbObj = null;
	protected $lock = null;
	protected $dsn;
	protected $type = 'pgsql';
	protected $user = "postgres";
	protected $pass = null;
	protected $dbname = '_unittest_';
	
	const DEFAULT_TYPE = 'pgsql';
	
	//-------------------------------------------------------------------------
	/**
	 * @codeCoverageIgnore
	 */
	public function __construct($type=null, $user=null, $pass=null) {
		if(!is_null($type) && !empty($type)) {
			$this->type = $type;
		}
		if(!is_null($user) && !empty($user)) {
			$this->user = $user;
		}
		if(!is_null($pass) && !empty($pass)) {
			$this->pass = $pass;
		}
		$this->lock = new \crazedsanity\core\Lockfile(constant('UNITTEST__LOCKFILE'));
		$this->internal_connect_db($type, $user, $pass);
		
		$this->reset_db(); //make sure the database is truly in a consistent state
		$this->setUp();
	}//end __construct()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * @codeCoverageIgnore
	 */
	public function skip() {
		$this->skipUnless($this->check_lockfile(), "Lockfile missing (". $this->lock->get_lockfile() ."): create one BEFORE database-related tests occur.");
		$this->skipUnless($this->check_requirements(), "Skipping tests for '". $this->getLabel() ."', database not configured");
	}
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * @codeCoverageIgnore
	 */
	public function check_lockfile() {
		$retval = false;
		
		if($this->lock->is_lockfile_present()) {
			$retval = true;
		}
		
		return($retval);
	}//end check_lockfile()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * @codeCoverageIgnore
	 */
	public function check_requirements() {
		// TODO: make *sure* to stop if there's a lockfile from cs_webdbupgrade.
		
		$retval=false;
		
		if($this->lock->is_lockfile_present()) {
			$retval = true;
		}
		else {
			ToolBox::debug_print(__METHOD__ .": lockfile missing (". $this->lock->get_lockfile() .") while attempting to run test '". $this->getLabel() ."'");
		}
		
		return($retval);
	}//end check_requirements()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * @codeCoverageIgnore
	 */
	protected function setUp() {
		$this->internal_connect_db($this->type, $this->user, $this->pass);
	}//end setUp()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * @codeCoverageIgnore
	 */
	protected function tearDown() {
//		$this->reset_db();
	}//end tearDown()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * @codeCoverageIgnore
	 */
	public function internal_connect_db($type='pgsql', $user=null, $pass=null) {
		if(is_null($type) || empty($type)) {
			$type = self::DEFAULT_TYPE;
		}
		if(!is_null($user)) {
			$this->user = $user;
		}
		
		if(is_null($this->user) || empty($this->user)) {
			switch($this->type) {
				case 'mysql':
					$this->user = 'root';
					break;
				case 'pgsql':
					$this->user = 'postgres';
					break;
				default:
					throw new \Exception(__METHOD__ .": invalid type (". $this->type .")");
			}
		}
		
		
		if(!is_null($pass)) {
			$this->pass = $pass;
		}
		$this->type = $type;
		$this->dsn = $this->type .":host=localhost;dbname=". $this->dbname;
		$this->dbObj = new \crazedsanity\database\Database($this->dsn, $this->user, $this->pass);
		return $this->dbObj;
	}//end internal_connect_db()
	//-------------------------------------------------------------------------
	
	
	
	//-----------------------------------------------------------------------------
	/**
	 * @codeCoverageIgnore
	 */
	public function reset_db($schemaFile=null) {
		$retval = false;
		
		if(!is_null($schemaFile) && !file_exists($schemaFile)) {
			throw new \exception(__METHOD__ .": schema file (". $schemaFile .") does not exist");
		}
		
		try {
			$this->internal_connect_db($this->type, $this->user, $this->pass);
			if($this->dbObj->get_transaction_status() == 1) {
				$this->dbObj->rollbackTrans();
			}
			$this->dbObj->beginTrans();

			if($this->type == 'pgsql') {
				$this->dbObj->run_query("DROP SCHEMA public CASCADE");
				$this->dbObj->run_query("CREATE SCHEMA public AUTHORIZATION " . $this->user);
			}
			elseif($this->type == 'mysql') {
				$this->dbObj->run_query("DROP DATABASE ". $this->dbname);
				$this->dbObj->run_query("CREATE DATABASE ". $this->dbname);
				$this->dbObj->run_query("USE ". $this->dbname);
			}

			if (!is_null($schemaFile)) {
				$this->dbObj->run_sql_file($schemaFile);
			}

			$this->dbObj->commitTrans();

			$retval = true;
		} catch (Exception $e) {
			if(is_object($this->dbObj)) {
				$this->dbObj->rollbackTrans();
			}
			throw $e;
		}
		return ($retval);
		
	}//end create_db()
	//-----------------------------------------------------------------------------
	
	
	
	//-----------------------------------------------------------------------------
	/**
	 * @codeCoverageIgnore
	 */
	public function __destruct() {
		#$this->destroy_db();
		$this->tearDown();
	}//end __destruct()
	//-----------------------------------------------------------------------------



}//end testDbAbstract{}
