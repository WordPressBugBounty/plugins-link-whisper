<?php

class Wpil_Notification
{
    const CACHE_KEY = 'wpil_notifications_data';
    const CACHE_DURATION = 6 * HOUR_IN_SECONDS; // 6 hours
    const BASE_API_URL = 'https://linkwhisper.com';

    /**
     * Fetch notification data from API with caching
     *
     * @return array|WP_Error Notification data or error
     */
    public static function fetch_notifications()
    {
        // Try to get cached data first
        if (self::has_cached_data()) {
            return get_transient(self::CACHE_KEY);
        }

        // Build API URL
        $api_url = self::BASE_API_URL . '/wp-json/lwnh/v1/notifications';
        $query_args = [
            'plugin_version' => WPIL_PLUGIN_VERSION_NUMBER,
            'plugin_type' => 'free'
        ];

        // Add testing mode parameter if enabled
        if (class_exists('Wpil_Settings') && Wpil_Settings::get_if_testing_mode_active()) {
            $query_args['testing_mode'] = '1';
        }

        $request_url = add_query_arg($query_args, $api_url);

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
     * Get notifications with error handling
     *
     * @return array Notification data (empty array on error)
     */
    public static function get_notifications()
    {
        $result = self::fetch_notifications();
        
        if (is_wp_error($result)) {
            // Log error for debugging (optional)
//            error_log('LinkWhisper Notification Error: ' . $result->get_error_message());
            return ['data' => []];
        }

        // Ensure we always have a 'data' key
        if (!isset($result['data'])) {
            return ['data' => []];
        }

        // Filter notifications based on user telemetry events
        if (is_array($result['data'])) {
            $result['data'] = self::filter_notifications_by_events($result['data']);
        }

        return $result;
    }

    /**
     * Clear notification cache
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
     * Filter notifications based on user telemetry events
     *
     * @param array $notifications Array of notifications to filter
     * @return array Filtered notifications
     */
    public static function filter_notifications_by_events($notifications)
    {
        if (empty($notifications)) {
            return $notifications;
        }

        $user_events = self::get_user_events_from_log();
        $filtered = [];

        foreach ($notifications as $notification) {
            if (self::should_show_notification($notification, $user_events)) {
                $filtered[] = $notification;
            }
        }

        // limit to 3 notifications
        return array_slice($filtered, 0, 3);
    }

    /**
     * Check if notification should be shown based on target events and validity dates
     *
     * @param array $notification Notification data
     * @param array $user_events User event data from telemetry
     * @return bool True if notification should be shown
     */
    public static function should_show_notification($notification, $user_events)
    {
        // Check validity date range first
        if (!self::is_notification_valid_by_date($notification)) {
            return false;
        }

        // If no target_events specified, show notification
        if (empty($notification['target_events'])) {
            return true;
        }

        // Parse JSON target events
        $target_events = json_decode($notification['target_events'], true);
        if (json_last_error() !== JSON_ERROR_NONE || empty($target_events)) {
            return true; // Show if can't parse target events
        }

        // Get timeframe (default to 30 days)
        $timeframe_days = isset($notification['event_timeframe']) ? (int)$notification['event_timeframe'] : 30;
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
                return false; // If any condition fails, don't show notification
            }
        }

        return true; // All conditions passed
    }

    /**
     * Check if notification is valid based on date range
     *
     * @param array $notification Notification data
     * @return bool True if notification is valid for current date
     */
    public static function is_notification_valid_by_date($notification)
    {
        $current_time = time();
        
        // Check valid_from date
        if (!empty($notification['valid_from'])) {
            $valid_from = strtotime($notification['valid_from']);
            if ($valid_from && $current_time < $valid_from) {
                return false; // Not yet valid
            }
        }
        
        // Check valid_until date
        if (!empty($notification['valid_until'])) {
            $valid_until = strtotime($notification['valid_until']);
            if ($valid_until && $current_time > $valid_until) {
                return false; // Expired
            }
        }
        
        return true; // Valid or no date constraints
    }

    /**
     * Get user events data from telemetry log table
     *
     * @return array User events with counts and timestamps
     */
    public static function get_user_events_from_log()
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
     * Get event name by ID from telemetry
     *
     * @param int $event_id Event ID
     * @return string|false Event name or false if not found
     */
    public static function get_event_name_by_id($event_id)
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
    public static function get_user_event_count_within_timeframe($user_events, $event_name, $timeframe_seconds, $current_time)
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
    public static function check_event_condition($user_count, $operator, $required_count)
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
                return $user_count == $required_count;
            default:
                return false;
        }
    }

    /**
     * Register AJAX handlers and hooks
     */
    public function register()
    {
        add_action('wp_ajax_wpil_load_notifications', [$this, 'ajax_load_notifications']);
    }

    /**
     * AJAX handler for loading notifications
     */
    public function ajax_load_notifications()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wpil_load_notifications')) {
            wp_send_json_error('Invalid nonce');
            return;
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        // Fetch notifications
        $notifications = self::get_notifications();

        // Render notification list HTML
        ob_start();
        $template_path = WP_INTERNAL_LINKING_PLUGIN_DIR . 'templates/notification_list.php';
        
        if (file_exists($template_path)) {
            include $template_path;
        }
        
        $html = ob_get_clean();

        wp_send_json_success([
            'html' => $html,
        ]);
    }
}