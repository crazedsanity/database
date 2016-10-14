<?php
/*
 * Created on Jun 12, 2009
 */

use \crazedsanity\core\ToolBox;

class TestOfMySQLDatabase extends crazedsanity\database\TestDbAbstract {
	
	protected $type = 'mysql';
	
	//-------------------------------------------------------------------------
	public function __construct() {
		parent::__construct('mysql', 'root');
	}//end __construct()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function test_connect() {
		$this->assertEquals('mysql', $this->type);
		$this->assertTrue(is_object($this->dbObj));
		$this->assertTrue($this->dbObj->is_connected());
		$this->assertEquals(1, parent::reset_db(__DIR__ .'/../setup/schema.mysql.sql'), ToolBox::debug_print($this->dbObj,0));
	}
	//-------------------------------------------------------------------------
	
	
	//-------------------------------------------------------------------------
	public function test_basics() {
		$this->assertTrue(is_object($this->dbObj), "No database objects to test");
		
		$type = 'mysql';
		
		$this->assertEquals($type, $this->dbObj->get_dbType(), "Database type mismatch, expecting (". $type ."), got (". $this->dbObj->get_dbType() .")");


		$this->assertTrue($this->reset_db(dirname(__FILE__) .'/../setup/schema.'. $type .'.sql'), "Failed to reset database");
		$this->assertFalse($this->dbObj->get_transaction_status(), "Already in transaction...?");

		$beginTransRes = $this->dbObj->beginTrans();
		$transactionStatus = $this->dbObj->get_transaction_status();
		$this->assertTrue($transactionStatus);
		$this->assertTrue($beginTransRes, "Start of transaction failed (". $beginTransRes ."), status=(". $transactionStatus .")");

		$this->dbObj->exec('CREATE TABLE test (id serial not null PRIMARY KEY, data varchar(200) not null unique);');
		$this->assertTrue($this->dbObj->get_transaction_status(), "Got out of transaction...?");


		// Make sure we get 0 rows before any data has been inserted.
		$numRows = $this->dbObj->run_query("SELECT * FROM test");
		$data = $this->dbObj->farray_fieldnames();
		$this->assertEquals($numRows, count($data), "Invalid number of rows returned: expected (". count($data) ."), got (". $numRows .")");
		$this->assertEquals($numRows, 0, "Returned unexpected number of rows on fresh table (". $numRows .")");


		$testData = array(
			0 => 'test1', 
			1 => 'test2'
		);
		$i=1;
		$insertTestSql = "INSERT INTO test (data) VALUES (:val)";
		foreach($testData as $val) {
			$createdId = $this->dbObj->run_insert($insertTestSql, array('val'=>$val), 'test_id_seq');
			$this->assertTrue(is_numeric($createdId), "Insert did not yield integer value (". $createdId .")");
			$this->assertEquals($i, $createdId, "Expected Id (". $i .") does not match created id (". $createdId .") for test data (". $val .")");
			$i++;
		}

		// now make sure we've got the date expected.
		$numRows = $this->dbObj->run_query("SELECT * FROM test");
		$data = $this->dbObj->farray_fieldnames();
		$this->assertTrue(is_array($data), "Returned data in an invalid format");
		$this->assertEquals($numRows, count($testData), "Invalid number of records created, expected (". count($testData) ."), got (". $numRows .")");

		$this->assertTrue(isset($data[0]), "Zeroth index does not exist?");
		$this->assertTrue(isset($data[0]['id']), "ID index missing from returned data");
		$this->assertTrue(isset($data[0]['data']), "DATA index missing from returned data");
		$this->assertEquals($data[0]['id'], 1, "Invalid ID in element 0, expected 1 but got (". $data[0]['id'] .")");

		$this->assertEquals($data[1]['id'], 2, "Invalid ID in element 1, expected 2 but got (". $data[1]['id'] .")");

		$numRows = $this->dbObj->run_query("SELECT * FROM test");
		$data = $this->dbObj->farray_nvp('id', 'data');

		$this->assertEquals("test1", $data[1], "Expected ID 1 to be 'test1', but instead got '". $data[1] ."'");
		$this->assertEquals("test2", $data[2], "Expected ID 2 to be 'test2', but instead got '". $data[2] ."'");

		// add a record with a specified ID (retrieving the sequence value will appear to be incorrect, because we're not using it).
		$testData[4] = "test5";
		$createdId = $this->dbObj->run_insert("INSERT INTO test (id, data) VALUES (:id, :val)", array('id'=>5, 'val'=>$testData[4]), 'test_id_seq');
		$this->assertEquals(intval($createdId), 5, "Inserting out-of-order index failed, insert ID should have been 5 (not ". $createdId .")");

		$numRows = $this->dbObj->run_query("SELECT * FROM test");
		$data = $this->dbObj->farray_nvp('id', 'data');
		$this->assertTrue(is_array($data), "Did not retrieve array of information from database... (". ToolBox::debug_var_dump($data,0) .")");
		$this->assertEquals(count($data), count($testData), "Number of records in database (". count($data) .") do not match what is expected (". count($testData) .")");

		$testData[2] = "test3";
		$createdId = $this->dbObj->run_insert("INSERT INTO test (id, data) VALUES (:id, :val)", array('id'=>3, 'val'=>$testData[2]), 'test_id_seq');
		$this->assertEquals($createdId, 3, "Failed to insert ID #3...?");
		$testData[3] = "test4";
		$createdId = $this->dbObj->run_insert("INSERT INTO test (id, data) VALUES (:id, :val)", array('id'=>4,'val'=>$testData[3]), 'test_id_seq');
		$this->assertEquals($createdId, 4, "Failed to insert ID #4...?");


		// Make sure farray_fieldnames works as expected.
		$numRows = $this->dbObj->run_query("SELECT * FROM test");
		$data = $this->dbObj->farray_fieldnames('id');

		$this->assertEquals(count($data), 5);

		$this->assertEquals(array('id'=>1, 'data'=>'test1'), $data[1]);
		$this->assertEquals(array('id'=>2, 'data'=>'test2'), $data[2]);
		$this->assertEquals(array('id'=>3, 'data'=>'test3'), $data[3]);
		$this->assertEquals(array('id'=>4, 'data'=>'test4'), $data[4]);
		$this->assertEquals(array('id'=>5, 'data'=>'test5'), $data[5]);


		$this->assertTrue($this->dbObj->commitTrans());

		$this->assertTrue($this->dbObj->beginTrans());
		// This illustrates what happens when we attempt to insert a duplicate.
		{

			//Okay, here's where there should be an error (re-inserting data that's already there)
			try {
				$createdId = $this->dbObj->run_insert($insertTestSql, array('val'=>$testData[4]), 'test_id_seq');
				$this->assertTrue(false, "DANGER WILL ROBINSON! This should have produced an error!");
			}
			catch(Exception $ex) {
				$errorInfo = $this->dbObj->errorInfo();

				// Make sure it said something about a duplicate key, throw an error if not.
				$this->assertEquals(1, preg_match('~Duplicate entry~', $ex->getMessage()), "Error was strange: ". $ex->getMessage());
			}
		}
		$this->assertTrue($this->dbObj->commitTrans());

		// make sure we're not in a transaction.
		$this->assertFalse($this->dbObj->get_transaction_status());


		// Simpler test for farray()
		$numRows = $this->dbObj->run_query("SELECT * FROM test WHERE id > :id ORDER BY id", array('id'=>0));
		$data = $this->dbObj->farray();
		$this->assertTrue($numRows == count($data));
		$this->assertTrue($numRows > 0);
		$this->assertTrue($numRows == 5);

		$this->assertEquals($data[0][0], 1);
		$this->assertEquals($data[0][1], 'test1');
		$this->assertEquals($data[0]['id'], 1);
		$this->assertEquals($data[0]['data'], 'test1');

		$testElement4 = array(
			0		=> 5,
			'id'	=> 5,
			1		=> 'test5',
			'data'	=> 'test5'
		);
		$this->assertEquals($data[4], $testElement4);


		// use farray_nvp(), but swap id with value (should work, since values are unique)
		$numRows = $this->dbObj->run_query(
				"SELECT * FROM test WHERE id > :id ORDER BY :orderBy", 
				array('id'=>0, 'orderBy' => 'id')
			);
		$data = $this->dbObj->farray_nvp('data', 'id');

		$this->assertEquals($numRows, count($data));
		$this->assertEquals($numRows, 5);
		$this->assertEquals($data['test5'], 5);
		$this->assertEquals($data['test3'], 3);


		$numRows = $this->dbObj->run_query("SELECT * FROM test WHERE id=:id", array('id'=> 2));
		$data = $this->dbObj->get_single_record();

		$this->assertEquals(array('id'=>2, 'data'=>'test2'), $data);
	}//end test_basics()
	//-------------------------------------------------------------------------
	
	
}
