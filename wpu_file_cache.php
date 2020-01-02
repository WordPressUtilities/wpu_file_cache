<?php

/*
Plugin Name: WPU File Cache
Description: Use file system for caching values
Version: 0.3.2
Author: Darklg
Author URI: http://darklg.me/
License: MIT License
License URI: http://opensource.org/licenses/MIT
*/

class WPUFileCache {
    private $cache_dir_name = 'wpufilecache';
    private $cache_dir_path = '';
    private $checked_directories = array();
    public function __construct() {
        add_action('plugins_loaded', array(&$this, 'plugins_loaded'));
    }

    /* ----------------------------------------------------------
      Loading
    ---------------------------------------------------------- */

    public function plugins_loaded() {

        /* Get cache dir */
        if (empty($this->cache_dir_path)) {
            $wp_upload_dir = wp_upload_dir();
            $this->cache_dir_path = $wp_upload_dir['basedir'] . '/' . $this->cache_dir_name;
        }

        /* Check if cache dir exists or create it */
        $this->create_directory();
    }

    /* ----------------------------------------------------------
      Values
    ---------------------------------------------------------- */

    public function get_value($cache_id, $folder = false) {
        $cache_file = $this->get_cache_file($cache_id, $folder);
        if ($cache_file && file_exists($cache_file)) {
            return apply_filters('wpufilecache_get_value', file_get_contents($cache_file), $cache_id);
        }
        return false;
    }

    public function set_value($cache_id, $value, $folder = false) {
        $cache_file = $this->get_cache_file($cache_id, $folder);
        file_put_contents($cache_file, $value);
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
function wpufilecache_get($cache_id, $folder = false) {
    global $WPUFileCache;
    return $WPUFileCache->get_value($cache_id, $folder);
}

/**
 * Set a cached value by ID
 * @param  string  $cache_id  Cache ID
 * @param  string  $value     (string) value
 * @return void
 */
function wpufilecache_set($cache_id, $value = '', $folder = false) {
    global $WPUFileCache;
    return $WPUFileCache->set_value($cache_id, $value, $folder);
}

/**
 * Purge cache
 * @return void
 */
function wpufilecache_purge($target = false) {
    global $WPUFileCache;
    $WPUFileCache->purge_cache($target);
    $WPUFileCache->set_protection();
}
