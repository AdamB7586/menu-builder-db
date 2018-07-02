<?php

namespace DBMenu;

use DBAL\Database;

class Navigation extends \Menu\Navigation {
    
    protected $db;
    protected $nav_table = 'menu_items';
    
    public function setDatabaseObject(Database $db) {
        $this->db = $db;
        return $this;
    }
    
    public function setNavigationTable($table){
        if(is_string($table) && !empty($table)){
            $this->nav_table = $table;
        }
        return $this;
    }
    
    public function getNavigationTable(){
        return $this->nav_table;
    }
    
    public function addNavItem() {
        return $this->db->insert($this->getNavigationTable(), []);
    }
    
    public function editNavItem($linkID) {
        return $this->db->update($this->getNavigationTable(), [], ['page_id' => $linkID], 1);
    }
    
    public function deleteNavItem() {
        return $this->db->delete($this->getNavigationTable(), [], 1);
    }
    
    public function buildNavArray($linkID = false){
        $items = $this->db->selectAll($this->getNavigationTable(), ['sub_page_of' => (is_numeric($linkID) ? $linkID :'IS NULL')], '*', ['link_order' => 'ASC']);
        if(is_array($items)){
            foreach($items as $i => $link){
                $items[$i]['children'] = $this->buildNavArray($link['page_id']);
            }
            return $items;
        }
        return false;
    }

}
