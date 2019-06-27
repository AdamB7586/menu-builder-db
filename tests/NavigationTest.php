<?php
namespace DBMenu\Tests;

use PHPUnit\Framework\TestCase;
use DBAL\Database;
use Configuration\Config;
use DBMenu\Navigation;

class NavigationTest extends TestCase{
    
    protected $db;
    protected $config;
    protected $nav;
    
    protected function setUp(): void {
        $this->db = new Database($GLOBALS['HOSTNAME'], $GLOBALS['USERNAME'], $GLOBALS['PASSWORD'], $GLOBALS['DATABASE']);
        if(!$this->db->isConnected()){
            $this->markTestSkipped(
                'No local database connection is available'
            );
        }
        $this->db->query(file_get_contents(dirname(dirname(__FILE__)).'/database/mysql_database.sql'));
        $this->config = new Config($this->db);
        $this->nav = new Navigation();
        $this->nav->setConfigObject($this->config)->setDatabaseObject($this->db);
    }

    protected function tearDown(): void {
        $this->db = null;
        $this->config = null;
        $this->nav = null;
    }
    
    public function exampleTest() {
        $this->markTestIncomplete();
    }
    
}