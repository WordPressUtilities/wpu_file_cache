<?php
defined('ABSPATH') || die;

/*
Plugin Name: WPU File Cache
Description: Use file system for caching values
Version: 0.7.0
Author: Darklg
Author URI: https://darklg.me/
Text Domain: wpu_file_cache
Requires at least: 6.2
Requires PHP: 8.0
Network: True
License: MIT License
License URI: https://opensource.org/licenses/MIT
*/

class WPUFileCache {
    private $cache_dir_name = 'wpufilecache';
    private $cache_dir_path = '';
    private $cache_root_dir = '';
    private $checked_directories = array();
    public function __construct() {
        add_action('plugins_loaded', array(&$this, 'plugins_loaded'));
    }

    /* ----------------------------------------------------------
      Loading
    ---------------------------------------------------------- */

    public function plugins_loaded() {

        $this->cache_root_dir = apply_filters('wpufilecache_cache_root_dir', WP_CONTENT_DIR . '/cache/');
        $this->cache_dir_name = apply_filters('wpufilecache_cache_dir_name', $this->cache_dir_name);

        /* Get cache dir */
        if (empty($this->cache_dir_path)) {
            $this->cache_dir_path = $this->cache_root_dir . $this->cache_dir_name;
        }

        /* Check if cache dir exists or create it */
        $this->create_directory();

        /* Options */
        $this->set_oembed_cache();
    }

    /* ----------------------------------------------------------
      Values
    ---------------------------------------------------------- */

    public function get_value($cache_id, $folder = false, $cacheduration = 0) {
        $cache_file = $this->get_cache_file($cache_id, $folder);
        if (!$cache_file || !file_exists($cache_file)) {
            return false;
        }
        if ($cacheduration && filemtime($cache_file) + $cacheduration < time()) {
            return false;
        }
        return apply_filters('wpufilecache_get_value', file_get_contents($cache_file), $cache_id);
    }

    public function set_value($cache_id, $value, $folder = false) {
        $cache_file = $this->get_cache_file($cache_id, $folder);
        file_put_contents($cache_file, $value);
        chmod($cache_file, 0664);
    }

    /* ----------------------------------------------------------
      Helpers
    ---------------------------------------------------------- */

    /* Create directory */
    public function create_directory($dir = false) {
        if (!$dir) {
            $dir = $this->cache_dir_path;
        }
        if (in_array($dir, $this->checked_directories)) {
            return;
        }
        if (!is_dir($this->cache_root_dir)) {
            mkdir($this->cache_root_dir);
        }
        if (!is_dir($dir)) {
            @mkdir($dir, 0755);
            @chmod($dir, 0755);
            if ($dir == $this->cache_dir_path) {
                $this->set_protection();
            }
        }
        $this->checked_directories[] = $dir;
    }

    /* Protection */
    public function set_protection() {
        $protection = $this->cache_dir_path . '/.htaccess';
        if (!file_exists($protection)) {
            file_put_contents($protection, 'deny from all');
            chmod($protection, 0644);
        }
    }

    /* Thanks to https://paulund.co.uk/php-delete-directory-and-files-in-directory */
    public function purge_cache($target = false) {
        if (!$target) {
            $target = $this->cache_dir_path;
        }
        if (strpos($target, $this->cache_dir_path) === false) {
            $target = $this->cache_dir_path . '/' . $target;
        }
        if (is_dir($target)) {
            $files = glob($target . '*', GLOB_MARK);
            foreach ($files as $file) {
                $this->purge_cache($file);
            }
        } else if (is_file($target)) {
            unlink($target);
        }
    }

    private function get_cache_file($cache_id, $folder = false) {
        if (empty($this->cache_dir_path)) {
            error_log('[WPU File Cache] Error : cache dir empty. Did you wait for "plugins_loaded" ?');
            return false;
        }
        $cache_file = $this->cache_dir_path;
        if ($folder) {
            $folder = sanitize_title($folder);
            if ($folder) {
                $cache_file .= '/' . $folder;
                $this->create_directory($cache_file);
            }
        }
        $cache_file .= '/' . $cache_id;

        return $cache_file;
    }

    /* ----------------------------------------------------------
      Oembed Cache
    ---------------------------------------------------------- */

    function set_oembed_cache() {
        if (!apply_filters('wpufilecache_set_oembed_cache', false)) {
            return;
        }
        add_filter('pre_oembed_result', array(&$this, 'pre_oembed_result'), 10, 3);
        add_filter('oembed_result', array(&$this, 'oembed_result'), 10, 3);
    }

    function get_oembed_cache_key($url, $args) {
        return sanitize_title(get_bloginfo('name')) . '_oembed_' . md5($url . json_encode($args));
    }

    // Return result if available
    function pre_oembed_result($content = '', $url = '', $args = array()) {
        $cached_content = $this->get_value($this->get_oembed_cache_key($url, $args), false, 30 * 86400);
        if (!empty($cached_content) && !is_null($cached_content)) {
            return $cached_content;
        }
        return $content;
    }

    // Create cache
    function oembed_result($content = '', $url = '', $args = array()) {

        $this->set_value($this->get_oembed_cache_key($url, $args), $content);

        // Return content
        return $content;
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
function wpufilecache_get($cache_id, $folder = false, $cacheduration = 0) {
    global $WPUFileCache;
    if (is_object($WPUFileCache) && method_exists($WPUFileCache, 'get_value')) {
        return $WPUFileCache->get_value($cache_id, $folder, $cacheduration);
    }
    return null;
}

/**
 * Set a cached value by ID
 * @param  string  $cache_id  Cache ID
 * @param  string  $value     (string) value
 * @return void
 */
function wpufilecache_set($cache_id, $value = '', $folder = false) {
    global $WPUFileCache;
    if (is_object($WPUFileCache) && method_exists($WPUFileCache, 'set_value')) {
        return $WPUFileCache->set_value($cache_id, $value, $folder);
    }
    return null;
}

/**
 * Purge cache
 * @return void
 */
function wpufilecache_purge($target = false) {
    global $WPUFileCache;
    if (is_object($WPUFileCache) && method_exists($WPUFileCache, 'purge_cache') && method_exists($WPUFileCache, 'set_protection')) {
        $WPUFileCache->purge_cache($target);
        $WPUFileCache->set_protection();
    }
}
