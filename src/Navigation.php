<?php

namespace DBMenu;

use DBAL\Database;
use Configuration\Config;
use Menu\Helpers\Validate;

class Navigation extends \Menu\Navigation
{
    
    protected $db;
    protected $config;
    
    public $cachePath;
    public $cacheNav = false;
    
    protected $items = false;
    protected $nav_table = 'menu_items';
    
    /**
     * Sets the database object so it can be called from the current class
     * @param Database $db This should be an instance of the database class
     * @return $this
     */
    public function setDatabaseObject(Database $db)
    {
        $this->db = $db;
        return $this;
    }
    
    /**
     * Sets the config object so it can be called from the current class
     * @param Config $config This should be an instance of the config class
     * @return $this
     */
    public function setConfigObject(Config $config)
    {
        $this->config = $config;
        $this->setNavigationTable($config->nav_table);
        return $this;
    }
    
    /**
     * Get the cache setting
     * @return boolean
     */
    public function getCacheSetting()
    {
        return $this->cacheNav;
    }
    
    /**
     * Set the cache setting
     * @param boolean $cache
     * @return $this
     */
    public function setCacheSetting($cache = true)
    {
        $this->cacheNav = boolval($cache);
        return $this;
    }
    
    /**
     * Get the set cache path
     * @return string
     */
    public function getCachePath()
    {
        return $this->cachePath;
    }
    
    /**
     * Sets the caching path
     * @param string $path The path to the caching directory
     * @return $this
     */
    public function setCachePath($path)
    {
        if (is_string($path)) {
            $this->cachePath = $path;
        }
        return $this;
    }
    
    /**
     * Sets the navigation table
     * @param string $table Sets the navigation table name
     * @return $this
     */
    public function setNavigationTable($table)
    {
        if (is_string($table) && !empty($table)) {
            $this->nav_table = $table;
        }
        return $this;
    }
    
    /**
     * Returns the navigation table
     * @return Returns the navigation table
     */
    public function getNavigationTable()
    {
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
    public function addNavItem($link, $label, $subOf = null, $additional = [])
    {
        if (is_string($link) && !empty(trim($link)) && is_string($label) && !empty(trim($label))) {
            return $this->db->insert($this->getNavigationTable(), array_merge($additional, ['label' => trim($label), 'uri' => Validate::sanitizeURI($link), 'sub_page_of' => (is_numeric($subOf) ? $subOf : null), 'link_order' => $this->getNextOrderNum($subOf)]));
        }
        return false;
    }
    
    /**
     * Edit a navigation item within the database
     * @param int $linkID This should be the link unique ID (`page_id` in the database)
     * @param array $linkInfo This should be the link information
     * @return boolean If the link is successfully updated will return true else returns false
     */
    public function editNavItem($linkID, $linkInfo = [])
    {
        if ($linkInfo['uri']) {
            $linkInfo['uri'] = Validate::sanitizeURI($linkInfo['uri']);
        }
        return $this->db->update($this->getNavigationTable(), $linkInfo, ['page_id' => $linkID], 1);
    }
    
    /**
     * Delete a navigation item
     * @param int $linkID This should be the unique link ID
     * @return boolean If the link is successfully deleted will return true else returns false
     */
    public function deleteNavItem($linkID)
    {
        return $this->db->delete($this->getNavigationTable(), ['page_id' => $linkID], 1);
    }
    
    /**
     * Will build a navigation array
     * @param string This should be the current URL
     * @param int|false $linkID If the array should be a sub array item set as the page id else set as false
     * @param array $additional Any additional SQL parameters should be added as an array
     * @return array|boolean If any items exist will return the navigation array else returns false if no items exist
     */
    protected function buildNavArray($currentURL, $linkID = false, $additional = [])
    {
        $items = $this->db->selectAll($this->getNavigationTable(), array_merge($additional, ['sub_page_of' => (is_numeric($linkID) ? $linkID :'IS NULL'), 'active' => 1]), ['page_id', 'label', 'uri', 'fragment', 'target', 'rel', 'class', 'id', 'link_order', 'sub_page_of', 'li_class', 'li_id', 'ul_class', 'ul_id', 'run_class', 'run_function'], ['link_order' => 'ASC']);
        if (is_array($items)) {
            foreach ($items as $i => $link) {
                if ($link['run_class'] !== null) {
                    $class = new $link['run_class']($this->db, $this->config);
                    $items[$i]['children'] = $class->{$link['run_function']}($currentURL);
                } elseif ($link['run_function'] !== null) {
                    $items[$i]['children'] = $link['run_function']($currentURL);
                } else {
                    $items[$i]['children'] = $this->buildNavArray($currentURL, $link['page_id'], $additional);
                }
            }
            return $items;
        }
        return false;
    }
    
    /**
     * Will return a navigation array
     * @param string This should be the current URL
     * @param int|false $linkID If the array should be a sub array item set as the page id else set as false
     * @param array $additional Any additional SQL parameters should be added as an array
     * @param string $filename The filename for the navigation array cache file
     * @return array|boolean If any items exist will return the navigation array else returns false if no items exist
     */
    public function getNavigationArray($currentURL, $linkID = false, $additional = [], $filename = 'navArrayCache')
    {
        $this->getFromCache($filename);
        if (!is_array($this->items)) {
            $this->items = $this->buildNavArray($currentURL, $linkID, $additional);
            $this->saveToCache($filename);
        }
        return $this->items;
    }


    /**
     * Returns the next page order number
     * @param int|NULL $subOf If the next order should be for a main navigation item set to NULL else set as the page ID of the master page
     * @return int This will be the next order number
     */
    protected function getNextOrderNum($subOf = null)
    {
        return ($this->db->count($this->getNavigationTable(), ['sub_page_of' => $subOf]) + 1);
    }

    
    
    /**
     * Save the navigation array to a file within the cache directory
     * @param string $filename The filename of the cache file
     */
    private function saveToCache($filename)
    {
        if (!file_exists($this->getCachePath().$filename) && !empty($this->getCachePath()) && $this->getCacheSetting() === true) {
            $data = "<?php\n\n
\$this->items = ".var_export($this->items, true).";\n";
            @file_put_contents(CACHE_PATH.$filename, $data);
        }
    }
    
    /**
     * Get the navigation array form the cache file if it exists
     * @param string $filename The filename of the cache file
     * @return boolean If the file doesn't exist or is navigation array has already been set will return false else nothing is returned
     */
    private function getFromCache($filename)
    {
        if (file_exists($this->getCachePath().$filename) && empty($this->items) && $this->getCacheSetting() === true) {
            include_once($this->getCachePath().$filename);
        }
        return false;
    }
}
