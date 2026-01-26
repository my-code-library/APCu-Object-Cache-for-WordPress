<?php
/**
 * WordPress APCu Object Cache Drop-In
 * Safe for Bluehost shared hosting + WP-CLI.
 */

// -----------------------------------------------------------------------------
// 1. Simple in-memory array cache (used for WP-CLI and APCu-missing fallback)
// -----------------------------------------------------------------------------
class WP_Object_Cache_Array {
    private $cache = array();

    public function add($key, $data, $group = 'default', $expire = 0) {
        $k = "$group:$key";
        if (isset($this->cache[$k])) return false;
        $this->cache[$k] = $data;
        return true;
    }

    public function set($key, $data, $group = 'default', $expire = 0) {
        $this->cache["$group:$key"] = $data;
        return true;
    }

    public function get($key, $group = 'default', $force = false, &$found = null) {
        $k = "$group:$key";
        if (isset($this->cache[$k])) {
            $found = true;
            return $this->cache[$k];
        }
        $found = false;
        return false;
    }

    public function delete($key, $group = 'default') {
        unset($this->cache["$group:$key"]);
        return true;
    }

    public function flush() {
        $this->cache = array();
        return true;
    }

    public function replace($key, $data, $group = 'default', $expire = 0) {
        $k = "$group:$key";
        if (!isset($this->cache[$k])) return false;
        $this->cache[$k] = $data;
        return true;
    }

    public function incr($key, $offset = 1, $group = 'default') {
        $k = "$group:$key";
        if (!isset($this->cache[$k])) return false;
        $this->cache[$k] += $offset;
        return $this->cache[$k];
    }

    public function decr($key, $offset = 1, $group = 'default') {
        $k = "$group:$key";
        if (!isset($this->cache[$k])) return false;
        $this->cache[$k] -= $offset;
        return $this->cache[$k];
    }

    public function switch_to_blog($id) {}
    public function reset() {}
    public function add_global_groups($groups) {}
    public function add_non_persistent_groups($groups) {}
    public function get_multi($groups) { return array(); }
}

// -----------------------------------------------------------------------------
// 2. APCu-backed cache with local runtime cache
// -----------------------------------------------------------------------------
class WP_Object_Cache_APCu {

    private $local = array();
    private $recursion = array();

    private function key($key, $group) {
        if ($group === '') $group = 'default';
        return "$group:$key";
    }

    private function guard($cache_key) {
        if (!isset($this->recursion[$cache_key])) {
            $this->recursion[$cache_key] = 0;
        }
        $this->recursion[$cache_key]++;
        return $this->recursion[$cache_key] < 5;
    }

    public function add($key, $data, $group = 'default', $expire = 0) {
        $k = $this->key($key, $group);
        if (isset($this->local[$k])) return false;
        if (apcu_exists($k)) return false;
        $this->local[$k] = $data;
        return apcu_store($k, $data, $expire);
    }

    public function set($key, $data, $group = 'default', $expire = 0) {
        $k = $this->key($key, $group);
        $this->local[$k] = $data;
        return apcu_store($k, $data, $expire);
    }

    public function get($key, $group = 'default', $force = false, &$found = null) {
        $k = $this->key($key, $group);

        if (isset($this->local[$k])) {
            $found = true;
            return $this->local[$k];
        }

        if (!$this->guard($k)) {
            $found = false;
            return false;
        }

        $success = false;
        $value = apcu_fetch($k, $success);
        if ($success) {
            $this->local[$k] = $value;
            $found = true;
            return $value;
        }

        $found = false;
        return false;
    }

    public function delete($key, $group = 'default') {
        $k = $this->key($key, $group);
        unset($this->local[$k]);
        return apcu_delete($k);
    }

    public function flush() {
        $this->local = array();
        return apcu_clear_cache();
    }

    public function replace($key, $data, $group = 'default', $expire = 0) {
        $k = $this->key($key, $group);
        if (!apcu_exists($k)) return false;
        $this->local[$k] = $data;
        return apcu_store($k, $data, $expire);
    }

    public function incr($key, $offset = 1, $group = 'default') {
        $k = $this->key($key, $group);
        $this->local[$k] = isset($this->local[$k]) ? $this->local[$k] + $offset : $offset;
        return apcu_inc($k, $offset);
    }

    public function decr($key, $offset = 1, $group = 'default') {
        $k = $this->key($key, $group);
        $this->local[$k] = isset($this->local[$k]) ? $this->local[$k] - $offset : 0;
        return apcu_dec($k, $offset);
    }

    public function switch_to_blog($id) {}
    public function reset() {}
    public function add_global_groups($groups) {}
    public function add_non_persistent_groups($groups) {}

    public function get_multi($groups) {
        $results = array();
        foreach ($groups as $group => $keys) {
            foreach ($keys as $key) {
                $found = false;
                $value = $this->get($key, $group, false, $found);
                if ($found) {
                    $results[$group][$key] = $value;
                }
            }
        }
        return $results;
    }
}

// -----------------------------------------------------------------------------
// 3. Choose implementation: WP-CLI → array, APCu available → APCu, else array
// -----------------------------------------------------------------------------
global $wp_object_cache;

if (defined('WP_CLI') && WP_CLI) {
    $wp_object_cache = new WP_Object_Cache_Array();
} elseif (!function_exists('apcu_fetch')) {
    $wp_object_cache = new WP_Object_Cache_Array();
} else {
    $wp_object_cache = new WP_Object_Cache_APCu();
}

// -----------------------------------------------------------------------------
// 4. Single, global WordPress cache API wrapper (no duplicates)
// -----------------------------------------------------------------------------
function wp_cache_init() {}

function wp_cache_add($k,$d,$g='',$e=0){global $wp_object_cache;return $wp_object_cache->add($k,$d,$g,$e);}
function wp_cache_set($k,$d,$g='',$e=0){global $wp_object_cache;return $wp_object_cache->set($k,$d,$g,$e);}
function wp_cache_get($k,$g='',$f=false,&$found=null){global $wp_object_cache;return $wp_object_cache->get($k,$g,$f,$found);}
function wp_cache_delete($k,$g=''){global $wp_object_cache;return $wp_object_cache->delete($k,$g);}
function wp_cache_flush(){global $wp_object_cache;return $wp_object_cache->flush();}
function wp_cache_replace($k,$d,$g='',$e=0){global $wp_object_cache;return $wp_object_cache->replace($k,$d,$g,$e);}
function wp_cache_incr($k,$o=1,$g=''){global $wp_object_cache;return $wp_object_cache->incr($k,$o,$g);}
function wp_cache_decr($k,$o=1,$g=''){global $wp_object_cache;return $wp_object_cache->decr($k,$o,$g);}
function wp_cache_close(){}
function wp_cache_add_global_groups($g){global $wp_object_cache;$wp_object_cache->add_global_groups($g);}
function wp_cache_add_non_persistent_groups($g){global $wp_object_cache;$wp_object_cache->add_non_persistent_groups($g);}
function wp_cache_switch_to_blog($id){global $wp_object_cache;$wp_object_cache->switch_to_blog($id);}
function wp_cache_reset(){global $wp_object_cache;$wp_object_cache->reset();}
function wp_cache_get_multi($groups){global $wp_object_cache;return $wp_object_cache->get_multi($groups);}
