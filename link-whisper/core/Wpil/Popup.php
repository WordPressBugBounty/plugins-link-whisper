<?php

class Wpil_Popup
{
    const CACHE_KEY = 'wpil_popups_data';
    const CACHE_DURATION = 6 * HOUR_IN_SECONDS; // 6 hours
    const BASE_API_URL = 'https://linkwhisper.com';

    /**
     * Fetch popup data from API with caching
     *
     * @return array|WP_Error Popup data or error
     */
    public static function fetch_popups()
    {
        // Try to get cached data first
        if (self::has_cached_data()) {
            return get_transient(self::CACHE_KEY);
        }

        // Build API URL
        $api_url = self::BASE_API_URL . '/wp-json/lwnh/v1/popups';
        $request_url = add_query_arg([
            'plugin_version' => WPIL_PLUGIN_VERSION_NUMBER,
            'plugin_type' => 'premium',
            'limit' => 50
        ], $api_url);

        // Make the API request
        $response = wp_remote_get($request_url, [
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'LinkWhisper/' . WPIL_PLUGIN_VERSION_NUMBER
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
        set_transient(self::CACHE_KEY, $data, self::CACHE_DURATION);

        return $data;
    }

    /**
     * Get popups with error handling and filtering
     *
     * @param string $page_slug Current page slug
     * @return array Filtered popup data
     */
    public static function get_popups($page_slug = null)
    {
        $result = self::fetch_popups();
        
        if (is_wp_error($result)) {
            error_log('LinkWhisper Popup Error: ' . $result->get_error_message());
            return ['data' => []];
        }

        // Ensure we have data
        $popups = $result['data'] ?? [];
        
        if (empty($popups) || !$page_slug) {
            return ['data' => []];
        }

        // Filter popups for current page
        $filtered_popups = self::filter_popups_for_page($popups, $page_slug);
        
        // Get the next popup to show (by priority and timestamp)
        $popup_to_show = self::get_next_popup_for_page($filtered_popups);

        return [
            'data' => $popup_to_show ? [$popup_to_show] : [],
            'total' => count($filtered_popups),
            'from_cache' => self::has_cached_data()
        ];
    }

    /**
     * Filter popups based on page, date validity, and conditions
     *
     * @param array $popups All popups from API
     * @param string $page_slug Current page slug
     * @return array Filtered popups
     */
    private static function filter_popups_for_page($popups, $page_slug)
    {
        $current_time = current_time('timestamp');
        $filtered = [];

        foreach ($popups as $popup) {
            // Check date validity
            if (!self::is_popup_date_valid($popup, $current_time)) {
                continue;
            }

            // Check if popup is for current page
            if (!self::is_popup_for_page($popup, $page_slug)) {
                continue;
            }

            // Check event-based conditions
            if (!self::popup_meets_event_conditions($popup)) {
                continue;
            }

            // Check display frequency
            if (!self::should_show_based_on_frequency($popup)) {
                continue;
            }

            $filtered[] = $popup;
        }

        return $filtered;
    }

    /**
     * Get next popup to show based on priority and timestamp
     *
     * @param array $filtered_popups Filtered popups for current page
     * @return array|null Next popup to show or null
     */
    private static function get_next_popup_for_page($filtered_popups)
    {
        if (empty($filtered_popups)) {
            return null;
        }

        // Sort by last shown timestamp first (oldest first), then by priority
        usort($filtered_popups, function($a, $b) {
            $timestamp_a = self::get_popup_last_shown($a['id']) ?: 0;
            $timestamp_b = self::get_popup_last_shown($b['id']) ?: 0;
            
            $timestamp_diff = $timestamp_a - $timestamp_b;
            if ($timestamp_diff !== 0) {
                return $timestamp_diff;
            }
            
            return ($a['priority'] ?? 999) - ($b['priority'] ?? 999);
        });

        // Return first popup (highest priority, least recently shown)
        $popup_to_show = $filtered_popups[0];
        
        // Track as shown
        self::set_popup_last_shown($popup_to_show['id']);
        
        return $popup_to_show;
    }


    /**
     * Check if popup is valid for current date/time
     *
     * @param array $popup Popup data
     * @param int $current_time Current timestamp
     * @return bool True if valid
     */
    private static function is_popup_date_valid($popup, $current_time)
    {
        $valid_from = isset($popup['valid_from']) ? strtotime($popup['valid_from']) : 0;
        $valid_until = isset($popup['valid_until']) ? strtotime($popup['valid_until']) : PHP_INT_MAX;

        return $current_time >= $valid_from && $current_time <= $valid_until;
    }

    /**
     * Check if popup should show on current page
     *
     * @param array $popup Popup data
     * @param string $page_slug Current page slug
     * @return bool True if should show
     */
    private static function is_popup_for_page($popup, $page_slug)
    {
        $popup_pages = json_decode($popup['popup_pages'] ?? '[]', true);
        
        if (json_last_error() !== JSON_ERROR_NONE || empty($popup_pages)) {
            return false;
        }

        return in_array($page_slug, $popup_pages);
    }

    /**
     * Check if popup meets event-based conditions using telemetry data
     *
     * @param array $popup Popup data
     * @return bool True if conditions are met
     */
    private static function popup_meets_event_conditions($popup)
    {
        $target_events = $popup['target_events'] ?? null;
        
        if (empty($target_events)) {
            return true; // No conditions = always show
        }

        $events = json_decode($target_events, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($events)) {
            return true;
        }

        $event_timeframe = $popup['event_timeframe'] ?? 30;
        
        foreach ($events as $event) {
            if (!self::check_event_condition($event, $event_timeframe)) {
                return false; // All conditions must be met
            }
        }

        return true;
    }

    /**
     * Check individual event condition against telemetry data
     *
     * @param array $event Event condition
     * @param int $timeframe Days to look back (not used in current implementation)
     * @return bool True if condition is met
     */
    private static function check_event_condition($event, $timeframe)
    {
        if (!class_exists('Wpil_Telemetry')) {
            return true;
        }

        $event_id = $event['event_id'] ?? null;
        $operator = $event['operator'] ?? '>=';
        $required_count = $event['count'] ?? 0;

        if (!$event_id) {
            return true;
        }

        // Get user event data
        $user_events = Wpil_Telemetry::get_user_event_data();
        $event_count_data = $user_events['event_count'] ?? 0;
        
        // For now, use total event count as a fallback
        // TODO: Implement specific event ID counting if needed
        $actual_count = is_numeric($event_count_data) ? (int)$event_count_data : 0;

        switch ($operator) {
            case '>=':
                return $actual_count >= $required_count;
            case '>':
                return $actual_count > $required_count;
            case '=':
            case '==':
                return $actual_count == $required_count;
            case '<':
                return $actual_count < $required_count;
            case '<=':
                return $actual_count <= $required_count;
            default:
                return $actual_count >= $required_count;
        }
    }

    /**
     * Check if popup should show based on display frequency
     *
     * @param array $popup Popup data
     * @return bool True if should show
     */
    private static function should_show_based_on_frequency($popup)
    {
        $frequency = $popup['display_frequency'] ?? 'always';
        
        if ($frequency === 'always') {
            return true;
        }

        $popup_id = $popup['id'];
        $last_shown = self::get_popup_last_shown($popup_id);
        
        if (!$last_shown) {
            return true; // Never shown before
        }

        $time_since_shown = time() - $last_shown;

        switch ($frequency) {
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
     * Get when popup was last shown to user
     *
     * @param int $popup_id Popup ID
     * @return int|false Timestamp or false if never shown
     */
    private static function get_popup_last_shown($popup_id)
    {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return false;
        }

        $shown_data = get_user_meta($user_id, 'wpil_popup_shown_times', true);
        
        if (!is_array($shown_data)) {
            return false;
        }

        return $shown_data[$popup_id] ?? false;
    }

    /**
     * Set when popup was shown to user
     *
     * @param int $popup_id Popup ID
     * @return bool Success status
     */
    private static function set_popup_last_shown($popup_id)
    {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return false;
        }

        $shown_data = get_user_meta($user_id, 'wpil_popup_shown_times', true);
        if (!is_array($shown_data)) {
            $shown_data = [];
        }

        $shown_data[$popup_id] = time();

        return update_user_meta($user_id, 'wpil_popup_shown_times', $shown_data);
    }

    /**
     * Clear popup cache
     *
     * @return bool True on successful deletion, false on failure
     */
    public static function clear_cache()
    {
        return delete_transient(self::CACHE_KEY);
    }

    /**
     * Check if cache exists and is valid
     *
     * @return bool True if cache exists, false otherwise
     */
    public static function has_cached_data()
    {
        return get_transient(self::CACHE_KEY) !== false;
    }

    /**
     * AJAX handler for loading popups
     */
    public static function ajax_load_popups()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wpil_load_popups')) {
            wp_send_json_error('Invalid nonce');
            return;
        }

        // Check user capabilities
        $capability = apply_filters('wpil_filter_main_permission_check', 'manage_categories');
        if (!current_user_can($capability)) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        // Get page slug from request
        $page_slug = isset($_POST['page_slug']) && !empty($_POST['page_slug']) ? 
                     sanitize_text_field($_POST['page_slug']) : '';

        if (empty($page_slug)) {
            wp_send_json_error('Page slug is required');
            return;
        }

        // Fetch popups for this page
        $popups = self::get_popups($page_slug);

        wp_send_json_success([
            'popups' => $popups['data'] ?? [],
            'total' => $popups['total'] ?? 0,
            'from_cache' => $popups['from_cache'] ?? false
        ]);
    }

    /**
     * AJAX handler for marking popup as dismissed
     */
    public static function ajax_dismiss_popup()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wpil_dismiss_popup')) {
            wp_send_json_error('Invalid nonce');
            return;
        }

        // Check user capabilities
        $capability = apply_filters('wpil_filter_main_permission_check', 'manage_categories');
        if (!current_user_can($capability)) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        // Get popup ID
        $popup_id = isset($_POST['popup_id']) ? intval($_POST['popup_id']) : 0;
        
        if (!$popup_id) {
            wp_send_json_error('Popup ID is required');
            return;
        }

        // Update last shown time (for frequency tracking)
        $updated = self::set_popup_last_shown($popup_id);

        if ($updated) {
            wp_send_json_success(['message' => 'Popup dismissed successfully']);
        } else {
            wp_send_json_error('Failed to dismiss popup');
        }
    }
}