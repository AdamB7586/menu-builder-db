<?php

namespace DBMenu;

use DBAL\Database;
use Configuration\Config;
use Menu\Helpers\Validate;

class Navigation extends \Menu\Navigation {
    
    protected $db;
    protected $config;
    
    protected $nav_table = 'menu_items';
    
    /**
     * Sets the database object so it can be called from the current class
     * @param Database $db This should be an instance of the database class
     * @return $this
     */
    public function setDatabaseObject(Database $db) {
        $this->db = $db;
        return $this;
    }
    
    /**
     * Sets the config object so it can be called from the current class
     * @param Config $config This should be an instance of the config class
     * @return $this
     */
    public function setConfigObject(Config $config) {
        $this->config = $config;
        $this->setNavigationTable($config->nav_table);
        return $this;
    }
    
    /**
     * Sets the navigation table
     * @param string $table Sets the navigation table name
     * @return $this
     */
    public function setNavigationTable($table){
        if(is_string($table) && !empty($table)){
            $this->nav_table = $table;
        }
        return $this;
    }
    
    /**
     * Returns the navigation table
     * @return Returns the navigation table
     */
    public function getNavigationTable(){
        return $this->nav_table;
    }
    
    /**
     * Add a navigation item to the database
     * @param string $link This should be the link 
     * @param string $label This should be the link text
     * @param int|NULL $subOf If this link is a sub page of another set this should be the page_id of that page else set to NULL
     * @param array $additional Any additional information should be added as an array
     * @return boolean If the item is added will return true else returns false
     */
    public function addNavItem($link, $label, $subOf = NULL, $additional = []) {
        if(is_string($link) && !empty(trim($link)) && is_string($label) && !empty(trim($label))){
            return $this->db->insert($this->getNavigationTable(), array_merge($additional, ['label' => trim($label), 'uri' => Validate::sanitizeURI($link), 'sub_page_of' => (is_numeric($subOf) ? $subOf : NULL), 'link_order' => $this->getNextOrderNum($subOf)]));
        }
        return false;
    }
    
    /**
     * Edit a navigation item within the database
     * @param int $linkID This should be the link unique ID (`page_id` in the database)
     * @param array $linkInfo This should be the link information
     * @return boolean If the link is successfully updated will return true else returns false
     */
    public function editNavItem($linkID, $linkInfo = []) {
        if($linkInfo['uri']){$linkInfo['uri'] = Validate::sanitizeURI($linkInfo['uri']);}
        return $this->db->update($this->getNavigationTable(), $linkInfo, ['page_id' => $linkID], 1);
    }
    
    /**
     * Delete a navigation item
     * @param int $linkID This should be the unique link ID
     * @return boolean If the link is successfully deleted will return true else returns false
     */
    public function deleteNavItem($linkID) {
        return $this->db->delete($this->getNavigationTable(), ['page_id' => $linkID], 1);
    }
    
    /**
     * Will build a navigation array
     * @param string This should be the current URL
     * @param int|false $linkID If the array should be a sub array item set as the page id else set as false
     * @return array|boolean If any items exist will return the navigation array else returns false if no items exist
     */
    public function buildNavArray($currentURL, $linkID = false){
        $items = $this->db->selectAll($this->getNavigationTable(), ['sub_page_of' => (is_numeric($linkID) ? $linkID :'IS NULL'), 'active' => 1], ['page_id', 'label', 'uri', 'fragment', 'target', 'rel', 'class', 'id', 'link_order', 'sub_page_of', 'li_class', 'li_id', 'ul_class', 'ul_id', 'run_class', 'run_function'], ['link_order' => 'ASC']);
        if(is_array($items)){
            foreach($items as $i => $link){
                if($link['run_class'] !== NULL){
                    $class = new $link['run_class']($this->db, $this->config);
                    $items[$i]['children'] = $class->{$link['run_function']}($currentURL);
                }
                elseif($link['run_function'] !== NULL){
                    $items[$i]['children'] = $link['run_function']($currentURL);
                }
                else{
                    $items[$i]['children'] = $this->buildNavArray($currentURL, $link['page_id']);
                }
            }
            return $items;
        }
        return false;
    }
    
    /**
     * Returns the next page order number
     * @param int|NULL $subOf If the next order should be for a main navigation item set to NULL else set as the page ID of the master page
     * @return int This will be the next order number
     */
    protected function getNextOrderNum($subOf = NULL){
        return ($this->db->count($this->getNavigationTable(), ['sub_page_of' => $subOf]) + 1);
    }

}
