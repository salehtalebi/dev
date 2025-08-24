<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Memory Management Utility Class
 */
class Sales_Dashboard_Memory_Manager {

    /**
     * Check current memory usage
     */
    public static function get_memory_usage() {
        return array(
            'current' => memory_get_usage(true),
            'current_formatted' => self::format_bytes(memory_get_usage(true)),
            'peak' => memory_get_peak_usage(true),
            'peak_formatted' => self::format_bytes(memory_get_peak_usage(true)),
            'limit' => ini_get('memory_limit'),
            'limit_bytes' => self::convert_to_bytes(ini_get('memory_limit'))
        );
    }

    /**
     * Check if we're approaching memory limit
     */
    public static function is_memory_limit_approaching($threshold = 0.8) {
        $usage = self::get_memory_usage();
        $usage_percentage = $usage['current'] / $usage['limit_bytes'];
        return $usage_percentage > $threshold;
    }

    /**
     * Force garbage collection
     */
    public static function cleanup_memory() {
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
        
        // Clear WordPress object cache if available
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }

    /**
     * Get safe batch size based on available memory
     */
    public static function get_safe_batch_size($item_size_estimate = 1024) {
        $memory = self::get_memory_usage();
        $available = $memory['limit_bytes'] - $memory['current'];
        $safe_available = $available * 0.7; // Use only 70% of available memory
        
        $batch_size = floor($safe_available / $item_size_estimate);
        
        // Set reasonable bounds
        return max(10, min($batch_size, 500));
    }

    /**
     * Convert memory limit string to bytes
     */
    private static function convert_to_bytes($value) {
        $value = trim($value);
        $last = strtolower($value[strlen($value)-1]);
        $value = (int) $value;

        switch($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }

        return $value;
    }

    /**
     * Format bytes to human readable
     */
    private static function format_bytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Process large datasets in batches
     */
    public static function process_in_batches($data, $callback, $batch_size = null) {
        if (!$batch_size) {
            $batch_size = self::get_safe_batch_size();
        }

        $results = array();
        $total = count($data);
        
        for ($i = 0; $i < $total; $i += $batch_size) {
            $batch = array_slice($data, $i, $batch_size);
            $batch_result = call_user_func($callback, $batch);
            
            if (is_array($batch_result)) {
                $results = array_merge($results, $batch_result);
            }
            
            // Clean up after each batch
            unset($batch);
            self::cleanup_memory();
            
            // Check if we're approaching memory limit
            if (self::is_memory_limit_approaching(0.9)) {
                error_log('Sales Dashboard: Memory limit approaching, stopping batch processing');
                break;
            }
        }

        return $results;
    }

    /**
     * Log memory usage for debugging
     */
    public static function log_memory_usage($context = '') {
        $memory = self::get_memory_usage();
        error_log(sprintf(
            'Sales Dashboard Memory [%s]: Current: %s, Peak: %s, Limit: %s',
            $context,
            $memory['current_formatted'],
            $memory['peak_formatted'],
            $memory['limit']
        ));
    }
}
