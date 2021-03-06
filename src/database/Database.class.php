<?php

/*
 * A class for generic PostgreSQL database access, built on PDO (http://www.php.net/manual/en/book.pdo.php)
 * 
 */

namespace crazedsanity\database;
use crazedsanity\core\ToolBox;
use crazedsanity\core\baseAbstract;

class Database extends baseAbstract {
	
	public $queryList=array();
	public $dbh;
	public $sth;
	
	protected $dsn = "";
	protected $connectParams = array();
	protected $username = null;
	protected $password = null;
	protected $dbType = null;
	
	protected $fsObj;
	protected $logFile;
	protected $writeCommandsToFile;
	protected $numRows = -1;
	
	//=========================================================================
	public function __construct($dsn, $username, $password, array $driverOptions=null, $writeCommandsToFile=null) {
		parent::__construct();
		try {
			$this->reconnect($dsn, $username, $password, $driverOptions, $writeCommandsToFile);
		}
		catch(\Exception $ex) {
			throw $ex;
		}
	}
	//=========================================================================
	
	
	
	//=========================================================================
	public function is_connected() {
		$retval = false;
		if(isset($this->dbh) && count($this->dbh->getAvailableDrivers())) {
			$retval = true;
		}
		return($retval);
	}
	//=========================================================================
	
	
	
	//=========================================================================
	public function close() {
		$this->dbh = null;
		$this->sth = null;
	}
	//=========================================================================
	
	
	
	//=========================================================================
	public function reconnect($dsn, $username, $password, array $driverOptions=null, $writeCommandsToFile=null) {
		$this->dbh = null;
		$this->sth = null;
		try {
			$this->dbh = new \PDO($dsn, $username, $password, $driverOptions);
			
			// Set options so PDO's behaviour is consistent (e.g. always throw exceptions)
			$this->dbh->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

			$this->dsn = $dsn;
			$this->username = $username;
			$this->password = $password;

			//break the DSN into bits...
			$tmpDsn = explode(';', preg_replace('/^[aA-zZ]{2,}:/', '', $dsn));
			foreach($tmpDsn as $bit) {
				$subDsnBits = explode('=', $bit);
				$this->connectParams[$subDsnBits[0]] = $subDsnBits[1];
			}
			$bits = array();
			if(preg_match('/^([aA-zZ]{2,}):/', $dsn, $bits)) {
				$this->dbType = $bits[1];
			}
			else {
				ToolBox::debug_print($bits,1);
				throw new \Exception(__METHOD__ .": unable to determine dbType");
			}


			$this->isInitialized = TRUE;

			$this->writeCommandsToFile = $writeCommandsToFile;

			if($this->writeCommandsToFile) {
				$this->logFile = __CLASS__ .".log";
				if(is_string($this->writeCommandsToFile)) {
					$this->logFile = $this->writeCommandsToFile;
				}
				$this->fsObj = new FileSystem(constant('RWDIR'));
				$lsData = $this->fsObj->ls();
				if(!isset($lsData[$this->logFile])) {
					$this->fsObj->create_file($this->logFile, true);
				}
				$this->fsObj->openFile($this->logFile, 'a');	
			}
		}
		catch(PDOException $e) {
			throw new \Exception(__METHOD__ .": failed to connect to database: ".
					$e->getMessage());
		}
	}//end __construct()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Magic method to call methods within the database abstraction layer ($this->dbLayerObj).
	 */
	public function __call($methodName, $args) {
		if(method_exists($this->dbh, $methodName)) {
			if($methodName == 'exec') {
				//update lastQuery list... should hold the last few SQL commands.
				if(count($this->queryList) > 20) {
					array_pop($this->queryList);
				}
				array_unshift($this->queryList, $args[0]);
				
				//log it to a file.
				if($this->writeCommandsToFile) {
					$this->fsObj->append_to_file(date('D, d M Y H:i:s') . ' ('. microtime(true) . ')::: '. $args[0]);
				}
			}
			try {
				
				$retval = call_user_func_array(array($this->dbh, $methodName), $args);
			}
			catch (Exception $e) {
				#cs_debug_backtrace(1);
				throw $e;
			}
		}
		else {
			throw new \Exception(__METHOD__ .': FATAL: unsupported method ('. $methodName .') for database of type ('. $this->dbType .')');
		}
		return($retval);
	}//end __call()	
	//=========================================================================
	
	
	
	//=========================================================================
	public function get_dbtype() {
		return($this->dbType);
	}//end get_dbtype()
	//=========================================================================
	
	
	
	//=========================================================================
	public function get_hostname() {
		$retval = null;
		if(isset($this->connectParams['host'])) {
			$retval = $this->connectParams['host'];
		}
		else {
			throw new \Exception(__METHOD__ .": HOST parameter missing");
		}
		return($this->connectParams['host']);
	}//end get_hostname()
	//=========================================================================
	
	
	
	//=========================================================================
	public function get_dsn() {
		return($this->dsn);
	}//end get_dsn()
	//=========================================================================
	
	
	
	//=========================================================================
	public function get_username() {
		return($this->username);
	}//end get_username()
	//=========================================================================
	
	
	
	//=========================================================================
	public function get_password() {
		return($this->password);
	}//end get_password()
	//=========================================================================
	
	
	
	//=========================================================================
	public function errorMsg() {
		$tRetval = $this->dbh->errorInfo();
		return($tRetval[2]);
	}//end errorMsg()
	//=========================================================================
	
	
	
	//=========================================================================
	public function numRows() {
		return ($this->numRows);
	}//end numRows()
	//=========================================================================
	
	
	
	//=========================================================================
	public function numAffected() {
		return ($this->numRows);
	}//end numAffected()
	//=========================================================================
	
	
	
	//=========================================================================
	public function get_lastQuery() {
		$retval = null;
		if(is_object($this->sth)) {
			$retval = $this->sth->queryString;
		}
		else {
			throw new \Exception(__METHOD__ .': statement handle not set');
		}
		return($retval);
	}
	//=========================================================================
	
	
	
	//=========================================================================
	public function run_query($sql, array $params=null, array $driverOptions=array()) {
		try {
			$this->sth = null;
			$this->sth = $this->dbh->prepare($sql, $driverOptions);
			if($this->sth === false) {
				throw new \Exception(__METHOD__ .": STH is false... ". ToolBox::debug_print($this->dbh->errorInfo(),0));
			}
			// TODO: throw an exception on error (and possibly if there were no rows returned)
			$this->sth->execute($params); 
			$this->numRows = $this->sth->rowCount();
		}
		catch(PDOException $px) {
			throw new \Exception(__METHOD__ .": ". $px->getMessage());
		}
		return($this->numRows);
	}//end run_query()
	//=========================================================================
	
	
	
	//=========================================================================
	public function run_insert($sql, array $params=null, $seqName=null, array $driverOptions=array()) {
		
		#$this->sth = $this->dbh->prepare($sql, $driverOptions);
		#$this->sth->execute($params);
		$numRows = $this->run_query($sql, $params, $driverOptions);
		
		if($numRows > 0 && is_object($this->dbh)) {
			$retval = $this->dbh->lastInsertId($seqName);
		}
		else {
			throw new \Exception(__METHOD__ .": insert failed");
		}
		
		#$retval = $this->dbh->lastInsertId($seqName);
		return($retval);
	}//end run_insert()
	//=========================================================================
	
	
	
	//=========================================================================
	public function run_update($sql, array $params=null, array $driverOptions=array()) {
		return($this->run_query($sql, $params, $driverOptions));
	}//end run_update()
	//=========================================================================
	
	
	
	//=========================================================================
	public function farray_fieldnames($index=null) {
		$retval = null;
		if(is_object($this->sth)) {
			$oData = $this->sth->fetchAll(\PDO::FETCH_ASSOC);
			
			$retval = array();
			
			if($this->numRows > 0) {
				$retval = $oData;
				
				if(!is_null($index)) {
					$newData = array();
					foreach($oData as $rowData) {
						if(isset($rowData[$index])) {
							if(!isset($newData[$rowData[$index]])) {
								$newData[$rowData[$index]] = $rowData;
							}
							else {
								// TODO: maybe this should be a warning (or *can* be, based on a configuration directive).
								throw new \Exception(__METHOD__ .': duplicate records exist for index=('. $index .'), first duplicate was ('. $rowData[$index] .')');
							}
						}
						else {
							throw new \Exception(__METHOD__ .": record does not contain column '". $index ."'");
						}
					}
					$retval = $newData;
				}
			}
			else {
				// no rows!
				$retval = array();
			}
		}
		else {
			throw new \Exception(__METHOD__ .': statement handle was not created');
		}
		return($retval);
	}//end farray_fieldnames()
	//=========================================================================
	
	
	
	//=========================================================================
	public function farray() {
		if(is_object($this->sth)) {
			$retval = $this->sth->fetchAll();
		}
		else {
			throw new Exception(__METHOD__ .": statement handle was not created");
		}
		
		return($retval);
	}//end farray()
	//=========================================================================
	
	
	
	//=========================================================================
	public function get_single_record() {
		$retval = array();
		if(is_object($this->sth)) {
			$retval = $this->sth->fetchAll(\ PDO::FETCH_ASSOC);
			if(is_array($retval) && count($retval) && isset($retval[0])) {
				$retval = $retval[0];
			}
		}
		else {
			throw new \Exception(__METHOD__ .': statement handle was not created');
		}
		
		return($retval);
	}//end get_single_record()
	//=========================================================================
	
	
	
	//=========================================================================
	public function farray_nvp($name, $value) {
		$myData = $this->farray_fieldnames();
		$retval = array();
		foreach($myData as $i=>$rowData) {
			if(isset($rowData[$name]) && isset($rowData[$value])) {
				if(!isset($retval[$name])) {
					$tKey = $rowData[$name];
					$tVal = $rowData[$value];
					$retval[$tKey] = $tVal;
				}
				else {
					throw new \Exception(__METHOD__ .': duplicate values for column ('. $name .') found for record #'. $i);
				}
			}
			else {
				throw new \Exception(__METHOD__ .': missing name ('. $name .') or value ('. $value .') from dataset');
			}
		}
		return($retval);
	}//end farray_nvp()
	//=========================================================================
	
	
	
	//=========================================================================
	public function run_sql_file($sqlFile) {
		$retval = $this->dbh->exec(file_get_contents($sqlFile));
		
		return($retval);
	}//end run_sql_file()
	//=========================================================================
	
	
	
	//=========================================================================
	public function exec($sql, array $params=null, array $driverOptions=null) {
		$retval = null;
		$this->sth = null;
		if(!is_null($params)) {
			$retval = $this->run_query($sql, $params, $driverOptions);
		}
		else {
			$retval = $this->dbh->exec($sql);
		}
		
		return($retval);
	}//end exec()
	//=========================================================================
	
	
	
	// wrapper methods (for backwards-compatibility)
	public function beginTrans() {return($this->dbh->beginTransaction());}
	public function commitTrans() {return($this->dbh->commit());}
	public function rollbackTrans() {return($this->dbh->rollback());}
	public function get_transaction_status() {return($this->dbh->inTransaction());}
	
	

} // end class Database
