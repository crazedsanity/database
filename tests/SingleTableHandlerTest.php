<?php
/*
 * Created on Jun 12, 2009
 */


use crazedsanity\SingleTableHandler;
use crazedsanity\Database;


class TestOfSingleTableHandler extends TestDbAbstract {
	
	
	//-------------------------------------------------------------------------
	public function __construct() {
		parent::__construct();
	}//end __construct()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function setUp() {
		$this->reset_db(dirname(__FILE__) .'/../setup/testTable.pgsql.sql');
		$this->x = new SingleTableHandler($this->dbObj, 'cs_test_table', 'cs_test_table_test_id_seq', 'test_id');
	}
	//-------------------------------------------------------------------------
	
	
	//-------------------------------------------------------------------------
	/**
	 */
	public function test_initialization() {
		$table = 'cs_test_table';
		$seq = 'cs_test_table_test_id_seq';
		$pkey = 'test_id';
		
		
		try {
			$this->assertFalse(new SingleTableHandler());
		}
		catch(Exception $ex) {
			$this->assertTrue((bool)preg_match('~Argument 1 passed to .+ must be an instance of crazedsanity\\\Database, none given~', $ex->getMessage()), "unexpected exception message: ". $ex->getMessage());
		}
		
		try {
			$this->assertFalse(new SingleTableHandler($this->dbObj));
		} catch (Exception $ex) {
			$this->assertTrue((bool)preg_match('~Missing argument 2 ~', $ex->getMessage()), "unexpected exception message: ". $ex->getMessage());
		}
		
		try {
			$this->assertFalse(new SingleTableHandler($this->dbObj, $table));
		} catch (Exception $ex) {
			$this->assertTrue((bool)preg_match('~Missing argument 3 ~', $ex->getMessage()), "unexpected exception message: ". $ex->getMessage());
		}
		
		try {
			$this->assertFalse(new SingleTableHandler($this->dbObj, $table, $seq));
		} catch (Exception $ex) {
			$this->assertTrue((bool)preg_match('~Missing argument 4 ~', $ex->getMessage()), "unexpected exception message: ". $ex->getMessage());
		}
		
		try {
			$this->assertFalse(new SingleTableHandler($this->dbObj, "", $seq, $pkey));
		} catch (Exception $ex) {
			$this->assertTrue((bool)preg_match('~invalid table name~', $ex->getMessage()), "unexpected exception message: ". $ex->getMessage());
		}
		
		$testDb = new Database($this->dbObj->get_dsn(), $this->dbObj->get_username(), $this->dbObj->get_password());
		$testDb->close();
		try {
			$this->assertFalse(new SingleTableHandler($testDb, $table, $seq, $pkey));
		} catch (Exception $ex) {
			$this->assertTrue((bool)preg_match('~database object not connected or not passed~', $ex->getMessage()), "unexpected exception message: ". $ex->getMessage());
		}
		
		try {
			$this->assertFalse(new SingleTableHandler($this->dbObj, $table, "", $pkey));
		} catch (Exception $ex) {
			$this->assertTrue((bool)preg_match('~invalid sequence name~', $ex->getMessage()), "unexpected exception message: ". $ex->getMessage());
		}
		
		try {
			$this->assertFalse(new SingleTableHandler($this->dbObj, $table, $seq, ""));
		} catch (Exception $ex) {
			$this->assertTrue((bool)preg_match('~invalid primary key field name~', $ex->getMessage()), "unexpected exception message: ". $ex->getMessage());
		}
	}
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function test_get_record_by_id() {
		$record = $this->x->get_record_by_id(1);
		$this->assertTrue(is_array($record));
		$this->assertTrue(count($record) > 0);
		$this->assertEquals(1, $record['test_id']);
		$this->assertEquals(1, $record['the_number']);
		$this->assertEquals('first', $record['description']);
		$this->assertEquals(true, $record['is_active']);
		
		$record = $this->x->get_record_by_id(5);
		$this->assertTrue(is_array($record));
		$this->assertEquals(5, $record['test_id']);
		$this->assertEquals(999, $record['the_number']);
		
		//now try to retrieve a record that does not exist.
		try {
			$badRecord = $this->x->get_record_by_id(666);
			$this->assertFalse(count($badRecord), "no exception thrown, and there is data in the returned record....???? ". cs_global::debug_print($badRecord));
		} catch (Exception $ex) {
			$this->assertTrue((bool)preg_match('~returned data did not contain ID~', $ex->getMessage()), "invalid or unexpected exception message: ". $ex->getMessage());
		}
		
		
		try {
			$this->x->get_record_by_id("poop");
		} catch (Exception $ex) {
			$this->assertTrue((bool)preg_match('~record ID must be numeric~', $ex->getMessage()), "unexpected exception message: ". $ex->getMessage());
		}
	}
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function test_create_record() {
		
		$newData = array(
			'description'	=> __METHOD__,
			'the_number'	=> 333,
			'is_active'		=> true
		);
		$newId = $this->x->create_record($newData);
		$this->assertTrue(is_numeric($newId), "New ID (". $newId .") is not a number, it is a '". gettype($newId) ."'");
		$this->assertTrue($newId > 0);
		
		$theRecord = $this->x->get_record_by_id($newId);
		
		foreach($newData as $k=>$v) {
			$this->assertEquals($v, $theRecord[$k], "database values do not match test data: (". $k .") should have value (". $v ."), actual value is (". $theRecord[$k] .")");
		}
		
		
		try {
			$this->x->create_record(null);
		} catch (Exception $ex) {
			$this->assertTrue((bool)preg_match('~Argument 1 passed to .+ must be of the type array, null given~', $ex->getMessage()), "unexpected exception message: ". $ex->getMessage());
		}
		
		try {
			$this->x->create_record(array());
		} catch (Exception $ex) {
			$this->assertTrue((bool)preg_match('~no data passed~', $ex->getMessage()), "unexpected exception message: ". $ex->getMessage());
		}
		
		try {
			$this->x->create_record(array('invalid_column' => 99999999));
		} catch (Exception $ex) {
			$this->assertTrue((bool)preg_match('~failed to create record, DETAILS:::~', $ex->getMessage()), "unexpected exception message: ". $ex->getMessage());
		}
	}
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function test_get_single_record() {
		$record = $this->x->get_single_record(array('the_number'=>999));
		
		$this->assertTrue(is_array($record));
		$this->assertEquals(5, $record['test_id']);
		$this->assertEquals(999, $record['the_number']);
		
		
		try {
			$this->x->get_single_record(array());
		} catch (Exception $ex) {
			$this->assertTrue((bool)preg_match('~no filter passed~', $ex->getMessage()), "unexpected exception message: ". $ex->getMessage());
		}
		
		
		try {
			$this->x->get_single_record(array('test_id', 7283));
		} catch (Exception $ex) {
			$this->assertTrue((bool)preg_match('~get_single_record:: failed to retrieve record, DETAILS::: ~', $ex->getMessage()), "unexpected exception message: ". $ex->getMessage());
		}
	}
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function test_get_records() {
		$base = $this->x->get_records();
		
		$this->assertTrue(is_array($base));
		$this->assertEquals(5, count($base));
		
		// make sure it was ordered
		$this->assertTrue((bool)preg_match('~ ORDER BY test_id~', $this->x->get_last_query()), "query does not appear to have been ordered: ". $this->x->get_last_query());
	}
	//-------------------------------------------------------------------------
	
}
