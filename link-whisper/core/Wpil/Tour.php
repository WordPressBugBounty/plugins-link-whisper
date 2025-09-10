<?php

class Wpil_Tour
{
    const CACHE_KEY_PREFIX = 'wpil_tours_data_';
    const CACHE_DURATION = 6 * HOUR_IN_SECONDS; // 6 hours
    const BASE_API_URL = 'https://linkwhisper.com';

    /**
     * Fetch tour data from API with caching
     *
     * @param string $page_slug The page slug to fetch tours for
     * @param string $plugin_version The plugin version
     * @return array|WP_Error Tour data or error
     */
    public static function fetch_tours($page_slug, $plugin_version = null)
    {
        // Use plugin version constant if not provided
        if (empty($plugin_version)) {
            $plugin_version = defined('WPIL_PLUGIN_VERSION_NUMBER') ? WPIL_PLUGIN_VERSION_NUMBER : '1.0.0';
        }

        // Try to get cached data first
        if (self::has_cached_data($page_slug, $plugin_version)) {
            return get_transient(self::get_cache_key($page_slug, $plugin_version));
        }

        // Build API URL
        $api_url = self::BASE_API_URL . '/wp-json/lwnh/v1/tours';
        $request_url = add_query_arg([
            'page' => $page_slug,
            'plugin_version' => $plugin_version,
            'plugin_type' => 'free',
            'limit' => 10
        ], $api_url);

        // Make the API request
        $response = wp_remote_get($request_url, [
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'LinkWhisper/' . $plugin_version
            ]
        ]);

        // Check for WP errors
        if (is_wp_error($response)) {
            return $response;
        }

        // Get response code
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return new WP_Error('api_error', 'API request failed with code: ' . $response_code);
        }

        // Get response body
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // Check if JSON decode was successful
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_error', 'Failed to decode API response: ' . json_last_error_msg());
        }

        // Cache the successful response
        set_transient(self::get_cache_key($page_slug, $plugin_version), $data, self::CACHE_DURATION);

        return $data;
    }

    /**
     * Get tours with error handling
     *
     * @param string $page_slug The page slug to fetch tours for
     * @param string $plugin_version The plugin version
     * @return array Tour data (empty array on error)
     */
    public static function get_tours($page_slug, $plugin_version = null)
    {
        $result = self::fetch_tours($page_slug, $plugin_version);
        
        if (is_wp_error($result)) {
            // Log error for debugging (optional)
            error_log('LinkWhisper Tour Error: ' . $result->get_error_message());
            return ['data' => []];
        }

        // Ensure we always have a 'data' key
        if (!isset($result['data'])) {
            return ['data' => []];
        }

        return $result;
    }

    /**
     * Generate cache key for specific page and version
     *
     * @param string $page_slug The page slug
     * @param string $plugin_version The plugin version
     * @return string Cache key
     */
    private static function get_cache_key($page_slug, $plugin_version)
    {
        return self::CACHE_KEY_PREFIX . md5($page_slug . '_' . $plugin_version);
    }

    /**
     * Clear tour cache for specific page and version
     *
     * @param string $page_slug The page slug
     * @param string $plugin_version The plugin version
     * @return bool True on successful deletion, false on failure
     */
    public static function clear_cache($page_slug = null, $plugin_version = null)
    {
        if ($page_slug && $plugin_version) {
            return delete_transient(self::get_cache_key($page_slug, $plugin_version));
        }

        // Clear all tour caches if no specific page/version provided
        global $wpdb;
        $prefix = self::CACHE_KEY_PREFIX;
        
        return $wpdb->query($wpdb->prepare("
            DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE %s
        ", '_transient_' . $prefix . '%'));
    }

    /**
     * Check if cache exists and is valid for specific page and version
     *
     * @param string $page_slug The page slug
     * @param string $plugin_version The plugin version
     * @return bool True if cache exists, false otherwise
     */
    public static function has_cached_data($page_slug, $plugin_version)
    {
//        return false;
        return get_transient(self::get_cache_key($page_slug, $plugin_version)) !== false;
    }

    /**
     * Get user-specific tour progress
     *
     * @return array User progress data
     */
    public static function get_user_progress()
    {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return ['completed_tours' => [], 'completed_steps' => [], 'dismissed_tours' => []];
        }

        $progress = get_user_meta($user_id, 'wpil_tour_progress', true);
        
        if (empty($progress) || !is_array($progress)) {
            return ['completed_tours' => [], 'completed_steps' => [], 'dismissed_tours' => []];
        }

        return $progress;
    }

    /**
     * Save user-specific tour progress
     *
     * @param array $progress Progress data
     * @return bool Success status
     */
    public static function save_user_progress($progress)
    {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return false;
        }

        return update_user_meta($user_id, 'wpil_tour_progress', $progress);
    }

    /**
     * Register AJAX handlers and hooks
     */
    public function register()
    {
        add_action('wp_ajax_wpil_load_tours', [$this, 'ajax_load_tours']);
        add_action('wp_ajax_wpil_save_tour_progress', [$this, 'ajax_save_tour_progress']);
    }

    /**
     * AJAX handler for saving tour progress
     */
    public static function ajax_save_tour_progress()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wpil_save_tour_progress')) {
            wp_send_json_error('Invalid nonce');
            return;
        }

        // Check user capabilities
        $capability = apply_filters('wpil_filter_main_permission_check', 'manage_categories');
        if (!current_user_can($capability)) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        // Make sure we have progress
        if (!isset($_POST['progress']) || empty($_POST['progress'])) {
            wp_send_json_error('No progress made');
            return;
        }

        // Get progress data from request
        $progress_json = stripslashes($_POST['progress']);
        $progress = json_decode($progress_json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error('Invalid progress data');
            return;
        }

        // Save progress
        $saved = self::save_user_progress($progress);

        if ($saved) {
            wp_send_json_success(['message' => 'Progress saved successfully']);
        } else {
            wp_send_json_error('Failed to save progress');
        }
    }

    /**
     * AJAX handler for loading tours
     */
    public static function ajax_load_tours()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wpil_load_tours')) {
            wp_send_json_error('Invalid nonce');
            return;
        }

        // Check user capabilities
        $capability = apply_filters('wpil_filter_main_permission_check', 'manage_categories');
        if (!current_user_can($capability)) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        // Get page slug and plugin version from request
        $page_slug = (isset($_POST['page_slug']) && !empty($_POST['page_slug'])) ? sanitize_text_field($_POST['page_slug']): '';
        $plugin_version = (isset($_POST['plugin_version']) && !empty($_POST['plugin_version'])) ? sanitize_text_field($_POST['plugin_version']): WPIL_PLUGIN_VERSION_NUMBER;

        if (empty($page_slug)) {
            wp_send_json_error('Page slug is required');
            return;
        }

        // Fetch tours
        $tours = self::get_tours($page_slug, $plugin_version);
        
        // Get user-specific progress
        $user_progress = self::get_user_progress();

        wp_send_json_success([
            'tours' => (isset($tours['data']) && !empty($tours['data'])) ? $tours['data']: [],
            'total' => (isset($tours['total']) && !empty($tours['total'])) ? $tours['total']: 0,
            'has_more' => (isset($tours['has_more']) && !empty($tours['has_more'])) ? $tours['has_more']: false,
            'from_cache' => self::has_cached_data($page_slug, $plugin_version),
            'user_progress' => $user_progress
        ]);
    }
}