<?php

/**
 * Generated by PHPUnit_SkeletonGenerator 1.2.0 on 2012-12-12 at 13:17:14.
 */
class AmazonReportScheduleListTest extends PHPUnit_Framework_TestCase {

    /**
     * @var AmazonReportScheduleList
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp() {
        resetLog();
        $this->object = new AmazonReportScheduleList('testStore', true, null, include(__DIR__.'/../../test-config.php'));
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown() {
        
    }
    
    public function testSetUseToken(){
        $this->assertNull($this->object->setUseToken());
        $this->assertNull($this->object->setUseToken(true));
        $this->assertNull($this->object->setUseToken(false));
        $this->assertFalse($this->object->setUseToken('wrong'));
    }
    
    public function testSetReportTypes(){
        $this->assertFalse($this->object->setReportTypes(null)); //can't be nothing
        $this->assertFalse($this->object->setReportTypes(5)); //can't be an int
        
        $list = array('One','Two','Three');
        $this->assertNull($this->object->setReportTypes($list));
        
        $o = $this->object->getOptions();
        $this->assertArrayHasKey('ReportTypeList.Type.1',$o);
        $this->assertEquals('One',$o['ReportTypeList.Type.1']);
        $this->assertArrayHasKey('ReportTypeList.Type.2',$o);
        $this->assertEquals('Two',$o['ReportTypeList.Type.2']);
        $this->assertArrayHasKey('ReportTypeList.Type.3',$o);
        $this->assertEquals('Three',$o['ReportTypeList.Type.3']);
        
        $this->assertNull($this->object->setReportTypes('Four')); //will cause reset
        $o2 = $this->object->getOptions();
        $this->assertArrayHasKey('ReportTypeList.Type.1',$o2);
        $this->assertEquals('Four',$o2['ReportTypeList.Type.1']);
        $this->assertArrayNotHasKey('ReportTypeList.Type.2',$o2);
        $this->assertArrayNotHasKey('ReportTypeList.Type.3',$o2);
        
        $this->object->resetReportTypes();
        $o3 = $this->object->getOptions();
        $this->assertArrayNotHasKey('ReportTypeList.Type.1',$o3);
    }
    
    public function testFetchReportList(){
        resetLog();
        $this->object->setMock(true,'fetchReportScheduleList.xml'); //no token
        $this->assertNull($this->object->fetchReportList());
        
        $o = $this->object->getOptions();
        $this->assertEquals('GetReportScheduleList',$o['Action']);
        
        $check = parseLog();
        $this->assertEquals('Single Mock File set: fetchReportScheduleList.xml',$check[1]);
        $this->assertEquals('Fetched Mock File: mock/fetchReportScheduleList.xml',$check[2]);
        
        $this->assertFalse($this->object->hasToken());
        
        return $this->object;
    }
    
    public function testFetchReportListToken1(){
        resetLog();
        $this->object->setMock(true,'fetchReportScheduleListToken.xml'); //no token
        
        //without using token
        $this->assertNull($this->object->fetchReportList());
        $check = parseLog();
        $this->assertEquals('Single Mock File set: fetchReportScheduleListToken.xml',$check[1]);
        $this->assertEquals('Fetched Mock File: mock/fetchReportScheduleListToken.xml',$check[2]);
        
        $this->assertTrue($this->object->hasToken());
        $o = $this->object->getOptions();
        $this->assertEquals('GetReportScheduleList',$o['Action']);
        $r = $this->object->getList();
        $this->assertArrayHasKey(0,$r);
        $this->assertEquals(1,count($r));
        $this->assertInternalType('array',$r[0]);
        $this->assertArrayNotHasKey(1,$r);
    }
    
    public function testFetchReportListToken2(){
        resetLog();
        $this->object->setMock(true,array('fetchReportScheduleListToken.xml','fetchReportScheduleListToken2.xml'));
        
        //with using token
        $this->object->setUseToken();
        $this->assertNull($this->object->fetchReportList());
        $check = parseLog();
        $this->assertEquals('Mock files array set.',$check[1]);
        $this->assertEquals('Fetched Mock File: mock/fetchReportScheduleListToken.xml',$check[2]);
        $this->assertEquals('Recursively fetching more Report Schedules',$check[3]);
        $this->assertEquals('Fetched Mock File: mock/fetchReportScheduleListToken2.xml',$check[4]);
        $this->assertFalse($this->object->hasToken());
        $o = $this->object->getOptions();
        $this->assertEquals('GetReportScheduleListByNextToken',$o['Action']);
        $r = $this->object->getList();
        $this->assertArrayHasKey(0,$r);
        $this->assertArrayHasKey(1,$r);
        $this->assertEquals(2,count($r));
        $this->assertInternalType('array',$r[0]);
        $this->assertInternalType('array',$r[1]);
        $this->assertNotEquals($r[0],$r[1]);
    }
    
    /**
     * @depends testFetchReportList
     */
    public function testGetReportType($o){
        $get = $o->getReportType(0);
        $this->assertEquals('_GET_ORDERS_DATA_',$get);
        
        $this->assertFalse($o->getReportType('wrong')); //not number
        $this->assertFalse($o->getReportType(1.5)); //not integer
        $this->assertFalse($this->object->getReportType()); //not fetched yet for this object
    }
    
    /**
     * @depends testFetchReportList
     */
    public function testGetSchedule($o){
        $get = $o->getSchedule(0);
        $this->assertEquals('_30_DAYS_',$get);
        
        $this->assertFalse($o->getSchedule('wrong')); //not number
        $this->assertFalse($o->getSchedule(1.5)); //not integer
        $this->assertFalse($this->object->getSchedule()); //not fetched yet for this object
    }
    
    /**
     * @depends testFetchReportList
     */
    public function testGetScheduledDate($o){
        $get = $o->getScheduledDate(0);
        $this->assertEquals('2009-02-20T02:10:42+00:00',$get);
        
        $this->assertFalse($o->getScheduledDate('wrong')); //not number
        $this->assertFalse($o->getScheduledDate(1.5)); //not integer
        $this->assertFalse($this->object->getScheduledDate()); //not fetched yet for this object
    }
    
    /**
     * @depends testFetchReportList
     */
    public function testGetList($o){
        $x = array();
        $x1 = array();
        $x1['ReportType'] = '_GET_ORDERS_DATA_';
        $x1['Schedule'] = '_30_DAYS_';
        $x1['ScheduledDate'] = '2009-02-20T02:10:42+00:00';
        $x[0] = $x1;
        
        $this->assertEquals($x,$o->getList());
        $this->assertEquals($x1,$o->getList(0));
        
        $this->assertFalse($this->object->getList()); //not fetched yet for this object
    }
    
    public function testFetchCount(){
        resetLog();
        $this->object->setReportTypes('123456');
        $this->object->setMock(true,'fetchReportScheduleCount.xml');
        $this->assertNull($this->object->fetchCount());
        
        $o = $this->object->getOptions();
        $this->assertEquals('GetReportScheduleCount',$o['Action']);
        
        $check = parseLog();
        $this->assertEquals('Single Mock File set: fetchReportScheduleCount.xml',$check[1]);
        $this->assertEquals('Fetched Mock File: mock/fetchReportScheduleCount.xml',$check[2]);
        
        $this->assertFalse($this->object->hasToken());
        
        return $this->object;
    }
    
    /**
     * @depends testFetchCount
     */
    public function testGetCount($o){
        $get = $o->getCount();
        $this->assertEquals('18',$get);
        
        $this->assertFalse($this->object->getCount()); //not fetched yet for this object
    }
    
}

require_once('helperFunctions.php');
