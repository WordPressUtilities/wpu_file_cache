<?php

/*
Plugin Name: WPU File Cache
Description: Use file system for caching values
Version: 0.1.0
Author: Darklg
Author URI: http://darklg.me/
License: MIT License
License URI: http://opensource.org/licenses/MIT
*/

class WPUFileCache {
    private $cache_dir_name = 'wpufilecache';
    private $cache_dir_path = '';
    public function __construct() {
        add_action('plugins_loaded', array(&$this, 'plugins_loaded'));
    }

    public function plugins_loaded() {

        /* Get cache dir */
        if (empty($this->cache_dir_path)) {
            $wp_upload_dir = wp_upload_dir();
            $this->cache_dir_path = $wp_upload_dir['basedir'] . '/' . $this->cache_dir_name;
        }

        /* Check if cache dir exists or create it */
        if (!is_dir($this->cache_dir_path)) {
            @mkdir($this->cache_dir_path, 0755);
            @chmod($this->cache_dir_path, 0755);
        }
    }

    public function get_value($cache_id) {
        $cache_file = $this->get_cache_file($cache_id);
        if ($cache_file && file_exists($cache_file)) {
            return file_get_contents($cache_file);
        }
        return false;
    }

    public function set_value($cache_id, $value) {
        $cache_file = $this->get_cache_file($cache_id);
        file_put_contents($cache_file, $value);
    }

    public function get_cache_file($cache_id) {
        $cache_file = $this->cache_dir_path . '/' . $cache_id;
        if (empty($this->cache_dir_path)) {
            error_log('[WPU File Cache] Error : cache dir empty. Did you wait for "plugins_loaded" ?');
            return false;
        }

        return $cache_file;
    }
}

$WPUFileCache = new WPUFileCache();


/* ----------------------------------------------------------
  Helpers
---------------------------------------------------------- */

/**
 * Get a cached value by ID
 * @param  string  $cache_id  Cache ID
 * @return mixed             (string) value or (bool) false
 */
function wpufilecache_get($cache_id) {
    global $WPUFileCache;
    return $WPUFileCache->get_value($cache_id);
}

/**
 * Set a cached value by ID
 * @param  string  $cache_id  Cache ID
 * @param  string  $value     (string) value
 * @return void
 */
function wpufilecache_set($cache_id, $value = '') {
    global $WPUFileCache;
    return $WPUFileCache->set_value($cache_id, $value);
}