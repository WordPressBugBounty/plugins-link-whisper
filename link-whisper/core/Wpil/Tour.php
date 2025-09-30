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

        // Apply filters to the tours
        return self::filter_tours($result, $page_slug, $plugin_version);
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
            // For testing purposes, use session if no user is logged in
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $progress = $_SESSION['wpil_tour_progress'] ?? [];
            if (empty($progress) || !is_array($progress)) {
                return ['completed_tours' => [], 'completed_steps' => [], 'dismissed_tours' => []];
            }
            return $progress;
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
            error_log("LinkWhisper Tours: No user logged in for save_user_progress");
            // For testing purposes, use session if no user is logged in
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['wpil_tour_progress'] = $progress;
            return true;
        }

        $result = update_user_meta($user_id, 'wpil_tour_progress', $progress);
        error_log("LinkWhisper Tours: save_user_progress for user $user_id, result: " . ($result ? 'success' : 'failed'));
        return $result;
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
        error_log("LinkWhisper Tours: ajax_save_tour_progress called");
        error_log("LinkWhisper Tours: Current user ID: " . get_current_user_id());
        
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wpil_save_tour_progress')) {
            error_log("LinkWhisper Tours: Invalid nonce");
            wp_send_json_error('Invalid nonce');
            return;
        }

        // Check user capabilities
        $capability = apply_filters('wpil_filter_main_permission_check', 'manage_categories');
        if (!current_user_can($capability) && get_current_user_id() > 0) {
            error_log("LinkWhisper Tours: Insufficient permissions for user " . get_current_user_id());
            wp_send_json_error('Insufficient permissions');
            return;
        }

        // Make sure we have progress
        if (!isset($_POST['progress']) || empty($_POST['progress'])) {
            error_log("LinkWhisper Tours: No progress data");
            wp_send_json_error('No progress made');
            return;
        }

        // Get progress data from request
        $progress_json = stripslashes($_POST['progress']);
        $progress = json_decode($progress_json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("LinkWhisper Tours: Invalid JSON: " . json_last_error_msg());
            wp_send_json_error('Invalid progress data');
            return;
        }

        error_log("LinkWhisper Tours: Progress data: " . print_r($progress, true));

        // Save progress
        $saved = self::save_user_progress($progress);

        if ($saved) {
            error_log("LinkWhisper Tours: Progress saved successfully");
            wp_send_json_success(['message' => 'Progress saved successfully']);
        } else {
            error_log("LinkWhisper Tours: Failed to save progress");
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

        // Add debugging information for target_events
        $debug_info = [];
        
        // Get user events for debugging from database
        $user_events_debug = [];
        $user_events = self::get_user_events_from_log();
        if (!empty($user_events)) {
            $total_events = 0;
            $first_event_date = 0;
            $latest_event_date = 0;
            
            foreach ($user_events as $event_name => $event_data) {
                $total_events += $event_data['count'];
                if ($first_event_date === 0 || $event_data['date'] < $first_event_date) {
                    $first_event_date = $event_data['date'];
                }
                if ($event_data['date'] > $latest_event_date) {
                    $latest_event_date = $event_data['date'];
                }
            }
            
            $user_events_debug = [
                'event_count' => $total_events,
                'first_event_date' => $first_event_date,
                'latest_event_date' => $latest_event_date,
                'events' => $user_events
            ];
        }
        
        if (isset($tours['data']) && !empty($tours['data'])) {
            foreach ($tours['data'] as $tour) {
                $debug_info[$tour['id']] = [
                    'title' => $tour['title'] ?? 'Unknown',
                    'target_events' => $tour['target_events'] ?? null,
                    'event_timeframe' => $tour['event_timeframe'] ?? 30,
                    'display_frequency' => $tour['display_frequency'] ?? 'always',
                    'auto_start' => $tour['auto_start'] ?? 0
                ];
            }
        }

        wp_send_json_success([
            'tours' => (isset($tours['data']) && !empty($tours['data'])) ? $tours['data']: [],
            'total' => (isset($tours['total']) && !empty($tours['total'])) ? $tours['total']: 0,
            'has_more' => (isset($tours['has_more']) && !empty($tours['has_more'])) ? $tours['has_more']: false,
            'from_cache' => self::has_cached_data($page_slug, $plugin_version),
            'user_progress' => $user_progress,
            'debug_info' => $debug_info,
            'user_events_debug' => $user_events_debug
        ]);
    }

    /**
     * AJAX handler for marking tour as shown
     */
    public static function ajax_mark_tour_shown()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wpil_mark_tour_shown')) {
            wp_send_json_error('Invalid nonce');
            return;
        }

        // Check user capabilities (allow guests for testing)
        $capability = apply_filters('wpil_filter_main_permission_check', 'manage_categories');
        if (!current_user_can($capability) && get_current_user_id() > 0) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        // Get tour ID from request
        $tour_id = (isset($_POST['tour_id']) && !empty($_POST['tour_id'])) ? intval($_POST['tour_id']) : 0;

        if (!$tour_id) {
            wp_send_json_error('Tour ID is required');
            return;
        }

        // Mark tour as shown
        $result = self::mark_tour_shown($tour_id);

        if ($result) {
            wp_send_json_success(['message' => 'Tour marked as shown']);
        } else {
            wp_send_json_error('Failed to mark tour as shown');
        }
    }

    /**
     * AJAX handler for dismissing tour widget
     */
    public static function ajax_dismiss_tour_widget()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wpil_dismiss_tour_widget')) {
            wp_send_json_error('Invalid nonce');
            return;
        }

        // Check user capabilities (allow guests for testing)
        $capability = apply_filters('wpil_filter_main_permission_check', 'manage_categories');
        if (!current_user_can($capability) && get_current_user_id() > 0) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        // Get tour ID and display frequency from request
        $tour_id = (isset($_POST['tour_id']) && !empty($_POST['tour_id'])) ? intval($_POST['tour_id']) : 0;
        $display_frequency = (isset($_POST['display_frequency']) && !empty($_POST['display_frequency'])) ? sanitize_text_field($_POST['display_frequency']) : 'once';

        if (!$tour_id) {
            wp_send_json_error('Tour ID is required');
            return;
        }

        // Mark tour as dismissed with frequency-based logic
        $result = self::dismiss_tour_widget($tour_id, $display_frequency);

        if ($result) {
            wp_send_json_success(['message' => 'Tour widget dismissed successfully']);
        } else {
            wp_send_json_error('Failed to dismiss tour widget');
        }
    }

    /**
     * Dismiss tour widget with frequency-based reset logic
     *
     * @param int $tour_id Tour ID
     * @param string $display_frequency Tour display frequency
     * @return bool Success status
     */
    public static function dismiss_tour_widget($tour_id, $display_frequency)
    {
        $user_id = get_current_user_id();
        $timestamp = time();
        
        if (!$user_id) {
            // For testing purposes, use session if no user is logged in
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['wpil_tour_dismissed'] = $_SESSION['wpil_tour_dismissed'] ?? [];
            $_SESSION['wpil_tour_dismissed'][$tour_id] = [
                'timestamp' => $timestamp,
                'display_frequency' => $display_frequency
            ];
            return true;
        }

        // Always use database storage for logged-in users (cross-device consistency)
        $tour_dismissed_data = get_user_meta($user_id, 'wpil_tour_dismissed', true);
        if (!is_array($tour_dismissed_data)) {
            $tour_dismissed_data = [];
        }

        $tour_dismissed_data[$tour_id] = [
            'timestamp' => $timestamp,
            'display_frequency' => $display_frequency
        ];
        
        return update_user_meta($user_id, 'wpil_tour_dismissed', $tour_dismissed_data);
    }

    /**
     * Check if tour widget is currently dismissed
     *
     * @param int $tour_id Tour ID
     * @param string $display_frequency Tour display frequency
     * @return bool True if dismissed
     */
    public static function is_tour_widget_dismissed($tour_id, $display_frequency)
    {        
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            // For testing purposes, use session if no user is logged in
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $tour_dismissed_data = $_SESSION['wpil_tour_dismissed'] ?? [];
        } else {
            // Always use database for logged-in users (cross-device consistency)
            $tour_dismissed_data = get_user_meta($user_id, 'wpil_tour_dismissed', true);
            if (!is_array($tour_dismissed_data)) {
                return false;
            }
        }

        if (!isset($tour_dismissed_data[$tour_id])) {
            return false; // Never dismissed
        }

        $dismiss_data = $tour_dismissed_data[$tour_id];
        $dismiss_timestamp = $dismiss_data['timestamp'] ?? 0;
        $dismiss_frequency = $dismiss_data['display_frequency'] ?? $display_frequency;
        
        // Calculate if dismiss period has expired based on frequency
        $time_since_dismissed = time() - $dismiss_timestamp;
        
        switch ($dismiss_frequency) {
            case 'once':
                return true; // Dismissed permanently for 'once' frequency
            case 'daily':
                return $time_since_dismissed < DAY_IN_SECONDS;
            case 'weekly':
                return $time_since_dismissed < (7 * DAY_IN_SECONDS);
            case 'monthly':
                return $time_since_dismissed < (30 * DAY_IN_SECONDS);
            case 'always':
                // For "always" tours, dismiss for only 5 minutes to allow quick reappearance
                return $time_since_dismissed < (0.5 * 60); // 5 minutes = 300 seconds
            default:
                return false;
        }
    }

    /**
     * Filter tours based on page, version, frequency, and event conditions
     *
     * @param array $result Raw tour data from API
     * @param string $page_slug Current page slug  
     * @param string $plugin_version Current plugin version
     * @return array Filtered tour data
     */
    private static function filter_tours($result, $page_slug, $plugin_version)
    {
        if (!isset($result['data']) || empty($result['data'])) {
            return $result;
        }

        $tours = $result['data'];
        $filtered_tours = [];

        foreach ($tours as $tour) {
            // Check if tour is for current page
            if (!self::is_tour_for_page($tour, $page_slug)) {
                continue;
            }

            // Check plugin version compatibility
            if (!self::is_tour_version_compatible($tour, $plugin_version)) {
                continue;
            }

            // Check if tour widget is dismissed
            if (self::is_tour_widget_dismissed($tour['id'], $tour['display_frequency'])) {
                continue;
            }

            // Check event-based conditions
            if (!self::tour_meets_event_conditions($tour)) {
                continue;
            }

            // Check display frequency
            if (!self::should_show_tour_based_on_frequency($tour)) {
                continue;
            }

            $filtered_tours[] = $tour;
        }

        $result['data'] = $filtered_tours;
        $result['total'] = count($filtered_tours);
        
        return $result;
    }

    /**
     * Check if tour is for current page
     *
     * @param array $tour Tour data
     * @param string $page_slug Current page slug
     * @return bool True if tour should show on this page
     */
    private static function is_tour_for_page($tour, $page_slug)
    {
        $tour_page = $tour['page_slug'] ?? '';
        return $tour_page === $page_slug;
    }

    /**
     * Check if tour is compatible with current plugin version
     *
     * @param array $tour Tour data
     * @param string $plugin_version Current plugin version
     * @return bool True if compatible
     */
    private static function is_tour_version_compatible($tour, $plugin_version)
    {
        $min_version = $tour['min_plugin_version'] ?? null;
        $max_version = $tour['max_plugin_version'] ?? null;

        // Check minimum version
        if ($min_version && version_compare($plugin_version, $min_version, '<')) {
            return false;
        }

        // Check maximum version
        if ($max_version && version_compare($plugin_version, $max_version, '>')) {
            return false;
        }

        return true;
    }

    /**
     * Check if tour meets event-based conditions using telemetry data
     *
     * @param array $tour Tour data
     * @return bool True if conditions are met
     */
    private static function tour_meets_event_conditions($tour)
    {
        $target_events = $tour['target_events'] ?? null;
        
        if (empty($target_events)) {
            return true; // No conditions = always show
        }

        // Parse JSON target events
        $target_events = json_decode($target_events, true);
        if (json_last_error() !== JSON_ERROR_NONE || empty($target_events)) {
            return true; // Show if can't parse target events
        }

        // Get user event data from database table
        $user_events = self::get_user_events_from_log();
        if (empty($user_events)) {
            return true; // No user events data available
        }

        // Get timeframe (default to 30 days)
        $timeframe_days = isset($tour['event_timeframe']) ? (int)$tour['event_timeframe'] : 30;
        $timeframe_seconds = $timeframe_days * (24 * 60 * 60); // DAY_IN_SECONDS
        $current_time = time();

        // Check each target event condition
        foreach ($target_events as $target_event) {
            if (!isset($target_event['event_id'], $target_event['operator'], $target_event['count'])) {
                continue; // Skip invalid target event
            }

            $event_id = (int)$target_event['event_id'];
            $operator = $target_event['operator'];
            $required_count = (int)$target_event['count'];

            // Get event name from telemetry
            $event_name = self::get_event_name_by_id($event_id);
            if (!$event_name) {
                continue; // Skip if event ID not found
            }

            // Get user's count for this event within timeframe
            $user_count = self::get_user_event_count_within_timeframe(
                $user_events, 
                $event_name, 
                $timeframe_seconds,
                $current_time
            );

            // Check if condition is met
            if (!self::check_event_condition($user_count, $operator, $required_count)) {
                return false; // If any condition fails, don't show tour
            }
        }

        return true; // All conditions passed
    }

    /**
     * Get user events from telemetry database table
     *
     * @return array User events data from database
     */
    private static function get_user_events_from_log()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wpil_telemetry_log';
        $user_id = get_current_user_id();
        
        if (empty($user_id)) {
            return [];
        }
        
        // Query to get event counts and latest timestamps for current user
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT 
                event_name, 
                COUNT(*) as count, 
                MAX(event_time) as latest_time
            FROM {$table} 
            WHERE user_id = %d 
            GROUP BY event_name
        ", $user_id));
        
        $user_events = [];
        
        if (!empty($results)) {
            foreach ($results as $result) {
                $user_events[$result->event_name] = [
                    'count' => (int)$result->count,
                    'date' => (int)$result->latest_time
                ];
            }
        }
        
        return $user_events;
    }

    /**
     * Get event name by ID
     *
     * @param int $event_id Event ID
     * @return string|false Event name or false if not found
     */
    private static function get_event_name_by_id($event_id)
    {
        if(isset(Wpil_Telemetry::$current_events[$event_id]) && !empty(Wpil_Telemetry::$current_events[$event_id])){
            return Wpil_Telemetry::$current_events[$event_id];
        }
        return false;
    }

    /**
     * Get user event count within timeframe
     *
     * @param array $user_events User event data
     * @param string $event_name Event name
     * @param int $timeframe_seconds Timeframe in seconds
     * @param int $current_time Current timestamp
     * @return int Event count within timeframe
     */
    private static function get_user_event_count_within_timeframe($user_events, $event_name, $timeframe_seconds, $current_time)
    {
        if (!isset($user_events[$event_name]) || !isset($user_events[$event_name]['count'])) {
            return 0;
        }

        $event_data = $user_events[$event_name];
        $event_time = isset($event_data['date']) ? (int)$event_data['date'] : 0;

        // If event is outside timeframe, return 0
        if ($event_time < ($current_time - $timeframe_seconds)) {
            return 0;
        }

        return (int)$event_data['count'];
    }

    /**
     * Check if event condition is met
     *
     * @param int $user_count User's event count
     * @param string $operator Comparison operator (>=, >, <=, <, =)
     * @param int $required_count Required count
     * @return bool True if condition is met
     */
    private static function check_event_condition($user_count, $operator, $required_count)
    {
        switch ($operator) {
            case '>=':
                return $user_count >= $required_count;
            case '>':
                return $user_count > $required_count;
            case '<=':
                return $user_count <= $required_count;
            case '<':
                return $user_count < $required_count;
            case '=':
            case '==':
                return $user_count == $required_count;
            case '!=':
                return $user_count != $required_count;
            default:
                return $user_count >= $required_count;
        }
    }

    /**
     * Check if tour should show based on display frequency
     *
     * @param array $tour Tour data
     * @return bool True if should show
     */
    private static function should_show_tour_based_on_frequency($tour)
    {
        $frequency = $tour['display_frequency'] ?? 'always';
        
        if ($frequency === 'always') {
            return true;
        }

        $tour_id = $tour['id'];
        $last_shown = self::get_tour_last_shown($tour_id);
        
        if (!$last_shown) {
            return true; // Never shown before
        }

        $time_since_shown = time() - $last_shown;

        switch ($frequency) {
            case 'once':
                return false; // Only show once, already shown
            case 'daily':
                return $time_since_shown >= DAY_IN_SECONDS;
            case 'weekly':
                return $time_since_shown >= (7 * DAY_IN_SECONDS);
            case 'monthly':
                return $time_since_shown >= (30 * DAY_IN_SECONDS);
            default:
                return true;
        }
    }

    /**
     * Get when tour was last shown to user
     *
     * @param int $tour_id Tour ID
     * @return int|false Timestamp or false if never shown
     */
    private static function get_tour_last_shown($tour_id)
    {
        $user_id = get_current_user_id();
        if (!$user_id) {
            // For testing purposes, use session if no user is logged in
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $tour_shown_data = $_SESSION['wpil_tour_shown'] ?? [];
            return $tour_shown_data[$tour_id] ?? false;
        }

        $tour_shown_data = get_user_meta($user_id, 'wpil_tour_shown', true);
        if (!is_array($tour_shown_data)) {
            $tour_shown_data = [];
        }

        return $tour_shown_data[$tour_id] ?? false;
    }

    /**
     * Mark tour as shown for frequency tracking
     *
     * @param int $tour_id Tour ID
     * @return bool Success status
     */
    public static function mark_tour_shown($tour_id)
    {
        $user_id = get_current_user_id();
        $timestamp = time();
        
        if (!$user_id) {
            // For testing purposes, use session if no user is logged in
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['wpil_tour_shown'] = $_SESSION['wpil_tour_shown'] ?? [];
            $_SESSION['wpil_tour_shown'][$tour_id] = $timestamp;
            return true;
        }

        $tour_shown_data = get_user_meta($user_id, 'wpil_tour_shown', true);
        if (!is_array($tour_shown_data)) {
            $tour_shown_data = [];
        }

        $tour_shown_data[$tour_id] = $timestamp;
        return update_user_meta($user_id, 'wpil_tour_shown', $tour_shown_data);
    }
}