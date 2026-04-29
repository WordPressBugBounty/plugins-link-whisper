<?php

/**
 * Export controller
 */
class Wpil_Export
{

    private static $instance;

    /**
     * gets the instance via lazy initialization (created on first usage)
     */
    public static function getInstance()
    {
        if (null === self::$instance)
        {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Export data
     */
    function export($post)
    {
        // exit if this isn't the admin
        if(!is_admin()){
            return;
        }

        $data = self::getExportData($post);
        $data = json_encode($data, JSON_PRETTY_PRINT);
        $host = !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';

        //create filename
        if ($post->type == 'term') {
            $term = get_term($post->id);
            $filename = $post->id . '-' . $host . '-' . $term->slug . '.json';
        } else {
            $post_slug = get_post_field('post_name', $post->id);
            $filename = $post->id . '-' . $host . '-' . $post_slug . '.json';
        }

        //download export file
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-type: application/json');
        echo $data;
        exit;
    }

    function export_sitemap_support(){
        // be sure to ignore any external object caches
        Wpil_Base::ignore_external_object_cache();

        // verify the nonce
        Wpil_Base::verify_nonce('wpil_export_sitemap_for_support');

        // exit if this isn't the admin
        if(!is_admin()){
            return;
        }

        $data = self::getSitemapSupportExportData();
        $data = json_encode($data, JSON_PRETTY_PRINT);
        $host = !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
        $filename = 'sitemap-export' . '-' . $host . '-' . time() . '.json';

        //download export file
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-type: application/json');
        echo $data;
        exit;
    }

    function export_ai_token_use_support(){
        // be sure to ignore any external object caches
        Wpil_Base::ignore_external_object_cache();

        // verify the nonce
        Wpil_Base::verify_nonce('wpil_export_ai_token_use_for_support');

        // exit if this isn't the admin
        if(!is_admin()){
            return;
        }

        $filters = self::get_ai_credit_history_filters($_GET);
        $from_ts = isset($filters['from_ts']) ? (int) $filters['from_ts'] : 0;
        $to_ts = isset($filters['to_ts']) ? (int) $filters['to_ts'] : 0;
        $events = isset($filters['events']) ? $filters['events'] : array();
        $view = isset($filters['view']) ? (string) $filters['view'] : 'individual';

        $format = isset($_GET['format']) ? sanitize_text_field(wp_unslash($_GET['format'])) : 'json';
        $format = strtolower($format);

        $payload = self::get_ai_token_use_export_data($from_ts, $to_ts, $events, $view);
        $host = !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
        $base_filename = 'ai-token-use-export' . '-' . $host . '-' . date('Ymd', $from_ts) . '-to-' . date('Ymd', $to_ts);

        if('csv' === $format){
            $filename = $base_filename . '.csv';
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-type: text/csv; charset=utf-8');
            $out = fopen('php://output', 'w');
            if(false !== $out){
                self::stream_ai_token_use_csv($out, $payload);
                fclose($out);
            }
            exit;
        }

        $filename = $base_filename . '.json';
        $data = json_encode($payload, JSON_PRETTY_PRINT);

        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-type: application/json');
        echo $data;
        exit;
    }

    private static function stream_ai_token_use_csv($stream, $payload = array()){
        if(!is_resource($stream)){
            return;
        }

        $rows = (isset($payload['rows']) && is_array($payload['rows'])) ? $payload['rows'] : array();
        fputcsv($stream, array(
            'token_index',
            'process_time_utc',
            'process_time',
            'view_mode',
            'transaction_type',
            'transaction_ref',
            'transaction_note',
            'ai_event',
            'task_name',
            'process_key',
            'process_id',
            'credit_change',
            'process_used',
            'model_version',
            'credits_used',
            'credits_added',
            'input_tokens',
            'output_tokens',
            'total_tokens',
            'cached_prompt_tokens',
            'reasoning_tokens',
            'batch_processed',
            'query_id'
        ));

        foreach($rows as $row){
            $credit_change = self::format_ai_credit_history_change($row, false);
            $task_name = '';
            if(isset($payload['view']) && 'task' === $payload['view']){
                $task_name = self::get_ai_credit_history_event_name($row, 'task');
            }elseif(!empty($row['process_key']) && !empty($row['process_id'])){
                $task_name = Wpil_AI::get_credit_tracking_task_pretty_name($row['process_key'], $row['process_id'], true);
            }
            fputcsv($stream, array(
                isset($row['token_index']) ? $row['token_index'] : '',
                isset($row['process_time_utc']) ? $row['process_time_utc'] : '',
                isset($row['process_time']) ? $row['process_time'] : '',
                isset($payload['view']) ? $payload['view'] : 'individual',
                isset($row['transaction_type']) ? $row['transaction_type'] : '',
                isset($row['transaction_ref']) ? $row['transaction_ref'] : '',
                isset($row['transaction_note']) ? $row['transaction_note'] : '',
                isset($row['process_name']) ? $row['process_name'] : '',
                $task_name,
                isset($row['process_key']) ? $row['process_key'] : '',
                isset($row['process_id']) ? $row['process_id'] : '',
                $credit_change,
                isset($row['process_used']) ? $row['process_used'] : '',
                isset($row['model_version']) ? $row['model_version'] : '',
                isset($row['credits_used']) ? $row['credits_used'] : '',
                isset($row['credits_added']) ? $row['credits_added'] : '',
                isset($row['input_tokens']) ? $row['input_tokens'] : '',
                isset($row['output_tokens']) ? $row['output_tokens'] : '',
                isset($row['total_tokens']) ? $row['total_tokens'] : '',
                isset($row['cached_prompt_tokens']) ? $row['cached_prompt_tokens'] : '',
                isset($row['reasoning_tokens']) ? $row['reasoning_tokens'] : '',
                isset($row['batch_processed']) ? $row['batch_processed'] : '',
                isset($row['query_id']) ? $row['query_id'] : ''
            ));
        }
    }

    public static function get_ai_credit_history_filters($source = array(), $per_page = 10){
        $source = is_array($source) ? $source : array();
        $today = current_time('timestamp');
        $default_to = wp_date('Y-m-d', $today);
        $default_from = wp_date('Y-m-d', strtotime('-30 days', $today));

        $from = isset($source['ai_usage_from']) ? sanitize_text_field(wp_unslash($source['ai_usage_from'])) : (isset($source['from']) ? sanitize_text_field(wp_unslash($source['from'])) : $default_from);
        $to = isset($source['ai_usage_to']) ? sanitize_text_field(wp_unslash($source['ai_usage_to'])) : (isset($source['to']) ? sanitize_text_field(wp_unslash($source['to'])) : $default_to);
        $events = isset($source['ai_usage_events']) ? $source['ai_usage_events'] : (isset($source['events']) ? $source['events'] : array());
        $page = isset($source['ai_usage_page']) ? (int) $source['ai_usage_page'] : (isset($source['page']) ? (int) $source['page'] : 1);
        $view = isset($source['ai_usage_view']) ? sanitize_key(wp_unslash($source['ai_usage_view'])) : (isset($source['view']) ? sanitize_key(wp_unslash($source['view'])) : 'task');
        $view = ('task' === $view) ? 'task': 'individual';

        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)){
            $from = $default_from;
        }

        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)){
            $to = $default_to;
        }

        $from_ts = self::parse_ai_credit_history_date_to_timestamp($from, false);
        $to_ts = self::parse_ai_credit_history_date_to_timestamp($to, true);
        if(empty($from_ts) || empty($to_ts)){
            $from = $default_from;
            $to = $default_to;
            $from_ts = self::parse_ai_credit_history_date_to_timestamp($from, false);
            $to_ts = self::parse_ai_credit_history_date_to_timestamp($to, true);
        }

        if($from_ts > $to_ts){
            $swap_date = $from;
            $from = $to;
            $to = $swap_date;
            $swap_ts = $from_ts;
            $from_ts = $to_ts;
            $to_ts = $swap_ts;
        }

        $event_options = self::get_ai_credit_history_event_options_by_time($from_ts, $to_ts, $view);
        $events = self::sanitize_ai_credit_history_events($events, array_keys($event_options), $view);

        return array(
            'from' => $from,
            'to' => $to,
            'from_ts' => $from_ts,
            'to_ts' => $to_ts,
            'page' => max(1, $page),
            'per_page' => max(1, (int) $per_page),
            'view' => $view,
            'events' => $events,
            'event_options' => $event_options,
            'from_label' => wp_date(get_option('date_format', 'F j, Y'), $from_ts),
            'to_label' => wp_date(get_option('date_format', 'F j, Y'), $to_ts),
        );
    }

    public static function get_ai_credit_history_event_options_by_time($from_ts = 0, $to_ts = 0, $view = 'individual'){
        $rows = self::get_ai_token_use_raw_rows($from_ts, $to_ts);
        if(empty($rows)){
            return array();
        }

        if('task' === $view){
            $options = array();
            $has_credit_additions = false;
            foreach($rows as $row){
                $transaction_type = isset($row['transaction_type']) ? (string) $row['transaction_type'] : 'usage';
                if('credit-deposit' === $transaction_type){
                    $has_credit_additions = true;
                    continue;
                }

                $task_key = isset($row['process_key']) ? sanitize_key((string) $row['process_key']) : '';
                $process_id = isset($row['process_id']) ? (int) $row['process_id'] : 0;
                if(empty($task_key) || $process_id < 1){
                    $legacy_key = self::get_ai_credit_history_legacy_process_key(isset($row['process_used']) ? (int) $row['process_used'] : 0);
                    if(empty($legacy_key)){
                        continue;
                    }

                    $options[$legacy_key] = self::get_ai_credit_history_legacy_process_name(
                        isset($row['process_used']) ? (int) $row['process_used'] : 0,
                        isset($row['transaction_note']) ? (string) $row['transaction_note'] : ''
                    );
                    continue;
                }

                $options[$task_key] = Wpil_AI::get_credit_tracking_task_pretty_name($task_key);
            }

            asort($options);
            if($has_credit_additions){
                $options['credit-deposit'] = __('Credits Added', 'wpil');
            }

            return $options;
        }

        $options = array();
        foreach($rows as $row){
            $process_code = isset($row['process_used']) ? (int) $row['process_used'] : 0;
            if($process_code < 1){
                continue;
            }

            $options[$process_code] = Wpil_AI::get_process_pretty_name_from_code($process_code);
        }

        asort($options);
        return $options;
    }

    public static function get_ai_credit_history_panel_data($source = array(), $per_page = 10){
        $filters = self::get_ai_credit_history_filters($source, $per_page);
        $history = self::get_ai_token_use_export_data_paginated($filters['from_ts'], $filters['to_ts'], $filters['page'], $filters['per_page'], $filters['events'], $filters['view']);

        return array(
            'filters' => $filters,
            'rows' => !empty($history['rows']) && is_array($history['rows']) ? $history['rows'] : array(),
            'total_count' => isset($history['total_count']) ? (int) $history['total_count'] : 0,
            'total_pages' => isset($history['total_pages']) ? max(1, (int) $history['total_pages']) : 1,
            'current_page' => isset($history['page']) ? max(1, (int) $history['page']) : 1,
            'per_page' => isset($history['per_page']) ? (int) $history['per_page'] : $filters['per_page'],
        );
    }

    public static function render_ai_credit_history_panel($source = array(), $per_page = 10){
        $panel_data = self::get_ai_credit_history_panel_data($source, $per_page);
        ob_start();
        include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/dashboard_ai_credit_history.php';
        return ob_get_clean();
    }

    public static function get_ai_credit_history_export_url($source = array()){
        $filters = self::get_ai_credit_history_filters($source);
        $query_args = array(
            'area' => 'wpil_export_ai_token_use_support',
            'nonce' => wp_create_nonce(get_current_user_id() . 'wpil_export_ai_token_use_for_support'),
            'format' => 'csv',
            'from' => $filters['from'],
            'to' => $filters['to'],
            'view' => $filters['view'],
        );
        if(!empty($filters['events'])){
            $query_args['events'] = $filters['events'];
        }

        return add_query_arg($query_args, admin_url('post.php'));
    }

    public static function get_ai_credit_history_event_name($row = array(), $view = 'individual'){
        if(!empty($row['process_name'])){
            return (string) $row['process_name'];
        }

        $transaction_type = isset($row['transaction_type']) ? (string) $row['transaction_type'] : 'usage';
        if('task' === $view){
            if('credit-deposit' === $transaction_type){
                return Wpil_AI::get_process_pretty_name_from_code(16, isset($row['transaction_note']) ? (string) $row['transaction_note'] : '');
            }

            $task_key = isset($row['process_key']) ? sanitize_key((string) $row['process_key']) : '';
            $process_id = isset($row['process_id']) ? (int) $row['process_id'] : 0;
            if(!empty($task_key) && !empty($process_id)){
                return Wpil_AI::get_credit_tracking_task_pretty_name($task_key, $process_id, true);
            }

            return self::get_ai_credit_history_legacy_process_name(
                isset($row['process_used']) ? (int) $row['process_used'] : 0,
                isset($row['transaction_note']) ? (string) $row['transaction_note'] : ''
            );
        }

        $process_code = isset($row['process_used']) ? (int) $row['process_used'] : 0;
        $transaction_note = isset($row['transaction_note']) ? (string) $row['transaction_note'] : '';
        return Wpil_AI::get_process_pretty_name_from_code($process_code, $transaction_note);
    }

    public static function format_ai_credit_history_date($timestamp = 0){
        if(empty($timestamp)){
            return '';
        }

        $date_format = trim(get_option('date_format', 'Y-m-d') . ' ' . get_option('time_format', 'H:i'));
        return wp_date($date_format, (int) $timestamp);
    }

    public static function format_ai_credit_history_change($row = array(), $formatted = true){
        $is_credit_deposit = !empty($row['transaction_type']) && 'credit-deposit' === $row['transaction_type'];
        $credits_used = isset($row['credits_used']) ? (float) $row['credits_used'] : 0;
        $credits_added = isset($row['credits_added']) ? (float) $row['credits_added'] : 0;
        $credit_total = $is_credit_deposit ? $credits_added : $credits_used;
        if($is_credit_deposit && empty($credit_total) && !empty($credits_used)){
            $credit_total = $credits_used;
        }

        $decimals = ((float) floor($credit_total) === (float) $credit_total) ? 0 : 4;
        if(!$formatted){
            return $is_credit_deposit ? $credit_total : ($credit_total * -1);
        }

        $prefix = $is_credit_deposit ? '+' : '-';
        return $prefix . number_format_i18n($credit_total, $decimals);
    }

    private static function get_ai_credit_history_legacy_process_key($process_used = 0){
        $process_used = (int) $process_used;
        if($process_used < 1){
            return '';
        }

        return 'legacy-process-' . $process_used;
    }

    private static function get_ai_credit_history_legacy_process_day_key($process_used = 0, $timestamp = 0){
        $process_key = self::get_ai_credit_history_legacy_process_key($process_used);
        if(empty($process_key) || empty($timestamp)){
            return '';
        }

        return $process_key . '-day-' . wp_date('Y-m-d', (int) $timestamp);
    }

    private static function get_ai_credit_history_legacy_process_name($process_used = 0, $transaction_note = ''){
        $process_used = (int) $process_used;

        $legacy_labels = array(
            1 => __('Suggestion Scoring', 'wpil'),
            2 => __('Post Summarizing', 'wpil'),
            3 => __('Product Detection', 'wpil'),
            4 => __('Calculating Relationship Scores', 'wpil'),
            5 => __('Target Keyword Detection', 'wpil'),
            6 => __('Summary + Product Search', 'wpil'),
            7 => __('Summary + Keyword Search', 'wpil'),
            8 => __('Product + Keyword Search', 'wpil'),
            9 => __('Summary + Keyword + Product Search', 'wpil'),
            10 => __('Create Post Sentence Embeddings', 'wpil'),
            11 => __('Assess Sentence Anchors', 'wpil'),
            12 => __('Outbound AI Links', 'wpil'),
            13 => __('Inbound AI Links', 'wpil'),
            14 => __('Broken Link Replacement', 'wpil'),
            15 => __('Credit Balance Check', 'wpil'),
            16 => __('Credits Added', 'wpil'),
        );

        if(isset($legacy_labels[$process_used])){
            $label = $legacy_labels[$process_used];
        }elseif($process_used > 0){
            $label = Wpil_AI::get_process_pretty_name_from_code($process_used, $transaction_note);
        }else{
            $label = __('Unknown Legacy AI Process', 'wpil');
        }

        if(16 === $process_used && !empty($transaction_note)){
            return Wpil_AI::get_process_pretty_name_from_code($process_used, $transaction_note);
        }

        return $label;
    }

    private static function parse_ai_credit_history_date_to_timestamp($date = '', $end_of_day = false){
        if(empty($date) || !is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)){
            return 0;
        }

        $time = $end_of_day ? '23:59:59' : '00:00:00';
        $datetime = date_create_immutable_from_format('Y-m-d H:i:s', $date . ' ' . $time, wp_timezone());
        return !empty($datetime) ? $datetime->getTimestamp() : 0;
    }

    private static function sanitize_ai_credit_history_events($events = array(), $allowed = array(), $view = 'individual'){
        if(!is_array($events)){
            $events = explode(',', (string) $events);
        }

        $events = array_values(array_filter(array_map('trim', $events), 'strlen'));
        if('task' === $view){
            $events = array_map('sanitize_key', $events);
            if(!empty($allowed)){
                $events = array_values(array_unique(array_intersect($events, $allowed)));
            }else{
                $events = array_values(array_unique($events));
            }
            sort($events);
            return $events;
        }

        if(empty($allowed)){
            $allowed = array_keys(Wpil_AI::get_credit_history_filter_processes());
        }

        $allowed = array_map('intval', $allowed);
        $events = array_map('intval', $events);
        $events = array_values(array_unique(array_intersect($events, $allowed)));
        sort($events);

        return $events;
    }

    /**
     * Get post data, links and settings for export
     *
     * @param $post_id
     * @return array
     */
    public static function getExportData($post)
    {
        global $wpdb;

        // detach any hooks known to cause problems in the loading
        Wpil_Base::remove_problem_hooks(true);

        $thrive_content = get_post_meta($post->id, 'tve_updated_post', true);
        $beaver_content = get_post_meta($post->id, '_fl_builder_data', true);
        $elementor_content = get_post_meta($post->id, '_elementor_data', true);
        $enfold_content = get_post_meta($post->id, '_aviaLayoutBuilderCleanData', true);
        $oxy_prefix = (!empty(get_option('oxy_meta_keys_prefixed', false))) ? '_ct_': 'ct_';
        $old_oxygen_content = get_post_meta($post->id, $oxy_prefix . 'builder_shortcodes', true);
        $new_oxygen_content = get_post_meta($post->id, $oxy_prefix . 'builder_json', true);

        set_transient('wpil_transients_enabled', 'true', 600);
        $transient_enabled = (!empty(get_transient('wpil_transients_enabled'))) ? true: false;

        //export settings
        $settings = [];
        $setting_data = $wpdb->get_results("SELECT * FROM {$wpdb->options} where option_name LIKE 'wpil_%'");
        foreach ($setting_data as $dat) {
            //$settings[$key] = get_option($key, null);
            $settings[$dat->option_name] = maybe_unserialize($dat->option_value);
        }
        //$settings['ignore_words'] = get_option('wpil_2_ignore_words', null);

        $is_admin = current_user_can('activate_plugins');

        $res = [
            'v' => strip_tags(Wpil_Base::showVersion()),
            'created' => date('c'),
            'post_id' => $post->id,
            'type' => $post->type,
            'wp_post_type' => $post->getRealType(),
            'post_terms' => $post->getPostTerms(),
            'post_links_last_update' => ($post->type === 'post') ? get_post_meta($post->id, 'wpil_sync_report2_time', true): get_term_meta($post->id, 'wpil_sync_report2_time', true),
            'has_run_scan' => get_option('wpil_has_run_initial_scan'),
            'last_scan_run' => get_option('wpil_scan_last_run_time', 'Not Yet Activated'),
            'site_url' => get_site_url(),
            'home_url' => get_home_url(),
            'processable_post_count' => Wpil_Report::get_total_post_count(),
            'metafield_count' => Wpil_Toolbox::get_site_meta_row_count(),
            'total_database_posts' => self::get_database_post_count(),
            'url' => $post->getLinks()->view,
            'title' => $post->getTitle(),
            'content' => $post->getContent(false),
            'processed_content' => Wpil_Report::process_content($post->getContent(false), $post),
            'shortcode_processed' => Wpil_Report::run_shortcode_safely($post->getContent(false), $post),
            'clean_content' => $post->getCleanContent(),
            'thrive_content' => $thrive_content,
            'beaver_content' => $beaver_content,
            'elementor_content' => $elementor_content,
            'enfold_content' => $enfold_content,
            'oxygen_shortcodes' => $old_oxygen_content,
            'oxygen_json' => $new_oxygen_content,
            'wp_theme' => ($is_admin) ? print_r(wp_get_theme(), true): 'User not an admin',
            'report_settings' => get_user_meta(get_current_user_id(), 'report_options', true),
            'editor' => $post->editor,
            'transients_enabled' => $transient_enabled,
            'max_execution_time' => ($is_admin) ? ini_get('max_execution_time'): 'User not an admin',
            'max_input_time' => ($is_admin) ? ini_get('max_input_time'): 'User not an admin',
            'max_input_vars' => ($is_admin) ? ini_get('max_input_vars'): 'User not an admin',
            'upload_max_filesize' => ($is_admin) ? ini_get('upload_max_filesize'): 'User not an admin',
            'post_max_size' => ($is_admin) ? ini_get('post_max_size'): 'User not an admin',
            'memory_limit' => ($is_admin) ? ini_get('memory_limit'): 'User not an admin',
            'memory_breakpoint' => Wpil_Report::get_mem_break_point(),
            'php_version' => ($is_admin) ? phpversion(): 'User not an admin',
            'mb_string_active' => extension_loaded('mbstring'),
            'curl_active' => ($is_admin) ? function_exists('curl_init'): 'User not an admin',
            'curl_version' => ($is_admin) ? ((function_exists('curl_version')) ? curl_version(): false): 'User not an admin',
            'relevent_wp_constants' => ($is_admin) ? Wpil_Settings::get_wp_constants(): 'User not an admin',
            'using_custom_htaccess' => ($is_admin) ? Wpil_Toolbox::is_using_custom_htaccess(): 'User not an admin',
            'ACF_active' => class_exists('ACF'),
            'table_statuses' => self::get_table_data(),
            'active_plugins' => ($is_admin) ? get_option('active_plugins', array()): 'User not an admin',
            'oai_processing_status' => json_encode(Wpil_AI::get_ai_batch_processing_status()),
            'settings' => $settings
        ];

        // if we're including meta in the export or ACF is active
        if(!empty(get_option('wpil_include_post_meta_in_support_export')) || class_exists('ACF')){
            $res['post_meta'] = ($post->type === 'post') ? get_post_meta($post->id, '', true) : get_term_meta($post->id, '', true);
        }

        // add reporting data to export
        $keys = [
            WPIL_LINKS_OUTBOUND_INTERNAL_COUNT,
            WPIL_LINKS_INBOUND_INTERNAL_COUNT,
            WPIL_LINKS_OUTBOUND_EXTERNAL_COUNT,
        ];

        $report = [];
        foreach($keys as $key) {
            $report[$key] = $post->getLinksData($key);
            $report[$key.'_data'] = $post->getLinksData($key, true);
        }

        if ($post->type == 'term') {
            $report['wpil_sync_report3'] = get_term_meta($post->id, 'wpil_sync_report3', true);
        } else {
            $report['wpil_sync_report3'] = get_post_meta($post->id, 'wpil_sync_report3', true);
        }

        $res['report'] = $report;
        $res['phrases'] = Wpil_Suggestion::getPostSuggestions($post, null, true, null, null, rand(0, time()));
        $res['site_plugins'] = ($is_admin) ? get_plugins(): 'User not an admin';

        return $res;
    }

    /**
     * Get post data, links and settings for export
     *
     * @param $post_id
     * @return array
     */
    public static function getSitemapSupportExportData(){
        // detach any hooks known to cause problems in the loading
        Wpil_Base::remove_problem_hooks(true);

        $data = array(
            'sitemaps' => base64_encode(print_r(Wpil_Sitemap::get_data(), true)),
            'sitemap_list' => Wpil_Sitemap::get_sitemap_list(),
            'has_completed_post_embeddings' => (Wpil_AI::has_completed_post_embeddings()) ? true: false,
            'ai_embedding_data' => (Wpil_AI::has_completed_post_embeddings()) ? array_slice(Wpil_AI::get_post_embedding_data(), 0, 100): 'has not completed post embeddings',
            'posts_with_calculated_embeddings' => Wpil_AI::get_calculated_embedding_post_ids(),
            'calculated_embedding_data' => array_slice(Wpil_AI::get_calculated_embedding_data(), 0, 50)
        );

        return $data;
    }

    public static function get_ai_token_use_export_data($from_ts = 0, $to_ts = 0, $events = array(), $view = 'individual'){
        $normalized_rows = self::normalize_ai_token_rows(self::get_ai_token_use_raw_rows($from_ts, $to_ts), $events, $view);

        return array(
            'generated_at_utc' => gmdate('c'),
            'site_url' => get_site_url(),
            'range' => array(
                'from_date' => gmdate('Y-m-d', (int) $from_ts),
                'to_date' => gmdate('Y-m-d', (int) $to_ts),
                'from_timestamp' => (int) $from_ts,
                'to_timestamp' => (int) $to_ts
            ),
            'view' => $view,
            'count' => count($normalized_rows),
            'rows' => $normalized_rows
        );
    }

    public static function get_ai_token_use_export_data_paginated($from_ts = 0, $to_ts = 0, $page = 1, $per_page = 25, $events = array(), $view = 'individual'){
        $page = max(1, (int) $page);
        $per_page = max(1, min(200, (int) $per_page));
        $rows = self::normalize_ai_token_rows(self::get_ai_token_use_raw_rows($from_ts, $to_ts), $events, $view);
        $total_count = count($rows);
        $total_pages = max(1, (int) ceil($total_count / $per_page));
        if($page > $total_pages){
            $page = $total_pages;
        }

        if($total_count > 0){
            $rows = array_slice($rows, max(0, ($page - 1) * $per_page), $per_page);
        }

        return array(
            'generated_at_utc' => gmdate('c'),
            'site_url' => get_site_url(),
            'range' => array(
                'from_date' => gmdate('Y-m-d', (int) $from_ts),
                'to_date' => gmdate('Y-m-d', (int) $to_ts),
                'from_timestamp' => (int) $from_ts,
                'to_timestamp' => (int) $to_ts
            ),
            'view' => $view,
            'count' => count($rows),
            'total_count' => $total_count,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => $total_pages,
            'rows' => $rows
        );
    }

    private static function get_ai_token_use_raw_rows($from_ts = 0, $to_ts = 0){
        global $wpdb;

        $table = $wpdb->prefix . 'wpil_ai_token_use_data';
        $table_exists = $wpdb->query("SHOW TABLES LIKE '{$table}'");
        if(empty($table_exists) || empty($from_ts) || empty($to_ts)){
            return array();
        }

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT *
                FROM {$table}
                WHERE `process_time` >= %d AND `process_time` <= %d
                ORDER BY `process_time` DESC, `token_index` DESC",
                (int) $from_ts,
                (int) $to_ts
            ),
            ARRAY_A
        );
    }

    private static function normalize_ai_token_rows($rows = array(), $events = array(), $view = 'individual'){
        if(empty($rows) || !is_array($rows)){
            return array();
        }

        $grouped_rows = ('task' === $view)
            ? self::group_ai_token_rows_by_task($rows, $events)
            : self::group_ai_token_rows_individual($rows, $events);

        foreach($grouped_rows as $index => $row){
            $process_time = isset($row['process_time']) ? (int) $row['process_time'] : 0;
            if(isset($row['transaction_type']) && $row['transaction_type'] === 'credit-deposit' && empty($grouped_rows[$index]['credits_added']) && !empty($grouped_rows[$index]['credits_used'])){
                $grouped_rows[$index]['credits_added'] = $grouped_rows[$index]['credits_used'];
            }

            $grouped_rows[$index]['process_name'] = self::get_ai_credit_history_event_name($grouped_rows[$index], $view);
            $grouped_rows[$index]['process_time_utc'] = !empty($process_time) ? gmdate('c', $process_time) : '';
            $grouped_rows[$index]['grouped_view'] = $view;
        }

        usort($grouped_rows, function($a, $b){
            $time_a = isset($a['process_time']) ? (int) $a['process_time'] : 0;
            $time_b = isset($b['process_time']) ? (int) $b['process_time'] : 0;
            if($time_a === $time_b){
                return ((int) ($b['token_index'] ?? 0)) <=> ((int) ($a['token_index'] ?? 0));
            }

            return $time_b <=> $time_a;
        });

        return array_values($grouped_rows);
    }

    private static function group_ai_token_rows_individual($rows = array(), $events = array()){
        $events = self::sanitize_ai_credit_history_events($events, array(), 'individual');
        $grouped_rows = array();

        foreach($rows as $row){
            $row = self::prepare_ai_token_row($row);
            if(!empty($events) && !in_array((int) $row['process_used'], $events, true)){
                continue;
            }

            $group_key = implode('|', array(
                (int) $row['process_time'],
                (int) $row['process_used'],
                (string) $row['transaction_type'],
                ('credit-deposit' === $row['transaction_type']) ? (string) $row['transaction_ref'] : '',
            ));

            if(!isset($grouped_rows[$group_key])){
                $grouped_rows[$group_key] = $row;
                continue;
            }

            $grouped_rows[$group_key] = self::merge_ai_token_group_rows($grouped_rows[$group_key], $row);
        }

        return array_values($grouped_rows);
    }

    private static function group_ai_token_rows_by_task($rows = array(), $events = array()){
        $events = self::sanitize_ai_credit_history_events($events, array(), 'task');
        $grouped_rows = array();

        foreach($rows as $row){
            $row = self::prepare_ai_token_row($row);
            $transaction_type = (string) $row['transaction_type'];
            $task_key = isset($row['process_key']) ? sanitize_key((string) $row['process_key']) : '';
            $process_id = isset($row['process_id']) ? (int) $row['process_id'] : 0;

            if('credit-deposit' === $transaction_type){
                if(!empty($events) && !in_array('credit-deposit', $events, true)){
                    continue;
                }

                $group_key = 'credit-deposit|' . (string) $row['transaction_ref'];
            }elseif(!empty($task_key) && !empty($process_id)){
                if(!empty($events) && !in_array($task_key, $events, true)){
                    continue;
                }

                $group_key = 'task|' . $task_key . '|' . $process_id;
            }else{
                $legacy_filter_key = self::get_ai_credit_history_legacy_process_key((int) $row['process_used']);
                $legacy_task_key = self::get_ai_credit_history_legacy_process_day_key((int) $row['process_used'], (int) $row['process_time']);
                if(empty($legacy_filter_key) || empty($legacy_task_key)){
                    continue;
                }

                if(!empty($events) && !in_array($legacy_filter_key, $events, true)){
                    continue;
                }

                $row['legacy_usage'] = 1;
                $row['legacy_task_key'] = $legacy_filter_key;
                $group_key = implode('|', array(
                    'legacy-process',
                    $legacy_task_key,
                ));
            }

            if(!isset($grouped_rows[$group_key])){
                $grouped_rows[$group_key] = $row;
                continue;
            }

            $grouped_rows[$group_key] = self::merge_ai_token_group_rows($grouped_rows[$group_key], $row, true);
        }

        return array_values($grouped_rows);
    }

    private static function prepare_ai_token_row($row = array()){
        $row = (is_array($row)) ? $row: array();
        $row['token_index'] = isset($row['token_index']) ? (int) $row['token_index'] : 0;
        $row['process_time'] = isset($row['process_time']) ? (int) $row['process_time'] : 0;
        $row['process_used'] = isset($row['process_used']) ? (int) $row['process_used'] : 0;
        $row['process_id'] = isset($row['process_id']) ? (int) $row['process_id'] : 0;
        $row['transaction_type'] = !empty($row['transaction_type']) ? (string) $row['transaction_type'] : 'usage';
        $row['transaction_ref'] = isset($row['transaction_ref']) ? (string) $row['transaction_ref'] : '';
        $row['transaction_note'] = isset($row['transaction_note']) ? (string) $row['transaction_note'] : '';
        $row['model_version'] = isset($row['model_version']) ? (string) $row['model_version'] : '';
        $row['query_id'] = isset($row['query_id']) ? (string) $row['query_id'] : '';
        $row['process_key'] = isset($row['process_key']) ? sanitize_key((string) $row['process_key']) : '';
        $row['input_tokens'] = isset($row['input_tokens']) ? (int) $row['input_tokens'] : 0;
        $row['output_tokens'] = isset($row['output_tokens']) ? (int) $row['output_tokens'] : 0;
        $row['total_tokens'] = isset($row['total_tokens']) ? (int) $row['total_tokens'] : 0;
        $row['cached_prompt_tokens'] = isset($row['cached_prompt_tokens']) ? (int) $row['cached_prompt_tokens'] : 0;
        $row['reasoning_tokens'] = isset($row['reasoning_tokens']) ? (int) $row['reasoning_tokens'] : 0;
        $row['batch_processed'] = isset($row['batch_processed']) ? (int) $row['batch_processed'] : 0;
        $row['credits_used'] = isset($row['credits_used']) ? (float) $row['credits_used'] : 0;
        $row['credits_added'] = isset($row['credits_added']) ? (float) $row['credits_added'] : 0;

        if('credit-deposit' === $row['transaction_type'] && empty($row['credits_added']) && !empty($row['credits_used'])){
            $row['credits_added'] = $row['credits_used'];
        }

        return $row;
    }

    private static function merge_ai_token_group_rows($existing_row = array(), $row = array(), $task_mode = false){
        $existing_row['token_index'] = max((int) $existing_row['token_index'], (int) $row['token_index']);
        $existing_row['process_time'] = max((int) $existing_row['process_time'], (int) $row['process_time']);
        $existing_row['input_tokens'] = ((int) $existing_row['input_tokens']) + ((int) $row['input_tokens']);
        $existing_row['output_tokens'] = ((int) $existing_row['output_tokens']) + ((int) $row['output_tokens']);
        $existing_row['total_tokens'] = ((int) $existing_row['total_tokens']) + ((int) $row['total_tokens']);
        $existing_row['cached_prompt_tokens'] = ((int) $existing_row['cached_prompt_tokens']) + ((int) $row['cached_prompt_tokens']);
        $existing_row['reasoning_tokens'] = ((int) $existing_row['reasoning_tokens']) + ((int) $row['reasoning_tokens']);
        $existing_row['credits_used'] = ((float) $existing_row['credits_used']) + ((float) $row['credits_used']);
        $existing_row['credits_added'] = ((float) $existing_row['credits_added']) + ((float) $row['credits_added']);
        $existing_row['batch_processed'] = max((int) $existing_row['batch_processed'], (int) $row['batch_processed']);

        if(empty($existing_row['transaction_ref']) && !empty($row['transaction_ref'])){
            $existing_row['transaction_ref'] = $row['transaction_ref'];
        }

        if(empty($existing_row['transaction_note']) && !empty($row['transaction_note'])){
            $existing_row['transaction_note'] = $row['transaction_note'];
        }

        if($task_mode && (int) $existing_row['process_used'] !== (int) $row['process_used']){
            $existing_row['process_used'] = 0;
        }

        if($existing_row['model_version'] !== $row['model_version']){
            $existing_row['model_version'] = '';
        }

        if($existing_row['query_id'] !== $row['query_id']){
            $existing_row['query_id'] = '';
        }

        return $existing_row;
    }

    public static function get_table_data(){
        global $wpdb;
        // create a list of all possible tables
        $tables = Wpil_Base::getDatabaseTableList();

        // set up the list for the table data
        $table_results = array();

        $create_table = "Create Table";

        // go over the list of tables
        foreach($tables as $table){
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
            if($table_exists === $table){
                $results = $wpdb->get_results("SHOW CREATE TABLE {$table}");
                if(!empty($results) && isset($results[0]) && isset($results[0]->Table) && isset($results[0]->$create_table)){
                    $results = array(
                        "Table" => str_ireplace($wpdb->prefix, 'PREFIX_', $results[0]->Table),
                        "Create Table" => str_ireplace($wpdb->prefix, 'PREFIX_', $results[0]->$create_table)
                    );
                }

                $table_results[] = $results;
            }else{
                $table_results[] = 'The "' . str_ireplace($wpdb->prefix, 'PREFIX_', $table) . '" table doesn\'t exist';
            }
        }

        return $table_results;
    }

    /**
     * Counts how many posts are in the posts table.
     **/
    public static function get_database_post_count(){
        global $wpdb;

        $count = $wpdb->get_var("SELECT COUNT(ID) FROM {$wpdb->posts}");
        return !empty($count) ? (int)$count: 0;
    }

    /**
     * Export table data to CSV
     */
    public static function ajax_csv()
    {
        // be sure to ignore any external object caches
        Wpil_Base::ignore_external_object_cache();

        // Remove any hooks that may interfere with AJAX requests
        Wpil_Base::remove_problem_hooks();

        $type = !empty($_POST['type']) ? $_POST['type'] : null;
        $count = !empty($_POST['count']) ? $_POST['count'] : null;
        $id = !empty($_POST['id']) ? preg_replace('/[^a-zA-Z0-9]/', '', sanitize_text_field($_POST['id'])): hash('sha256', str_shuffle(time() . 'Link Whisper is Awesome!') . time()/2);
        $capability = apply_filters('wpil_filter_main_permission_check', 'manage_categories', Wpil_Base::get_current_page());

        if (!$type || !$count || !current_user_can($capability)) {
            wp_send_json([
                    'error' => [
                    'title' => __('Request Error', 'wpil'),
                    'text'  => __('Bad request. Please try again later', 'wpil')
                ]
            ]);
        }

        // get the directory that we'll be writing the export to
        $dir = false;
        $dir_url = false;
        if(is_writable(WP_INTERNAL_LINKING_PLUGIN_DIR)){
            if(!is_dir(WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/exports/')){
                wp_mkdir_p(WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/exports/');
            }

            // if it's possible, write to the plugin directory
            $dir = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/exports/';
            $dir_url = WP_INTERNAL_LINKING_PLUGIN_URL . 'includes/exports/';
        }else{
            // if writing to the plugin directory isn't possible, try for the uploads folder
            $uploads = wp_upload_dir(null, false);
            if(!empty($uploads) && isset($uploads['basedir']) && is_writable($uploads['basedir'])){
                if(wp_mkdir_p(trailingslashit($uploads['basedir']). 'link-whisper-premium/exports')){
                    $dir = trailingslashit($uploads['basedir']). 'link-whisper-premium/exports/';
                    $dir_url = trailingslashit($uploads['baseurl']). 'link-whisper-premium/exports/';
                }
            }
        }

        // if we aren't able to write to any directories
        if(empty($dir)){
            // tell the user about it
            wp_send_json([
                'error' => [
                    'title' => __('File Permission Error', 'wpil'),
                    'text'  => __('The uploads folder isn\'t writable by Link Whisper. Please contact your host or webmaster about making the "/uploads/link-whisper-premium/" folder writable.', 'wpil') // we're defaulting to the uploads folder here since it's the easiest one to support
                ]
            ]);
        }

        // create the file name that we'll be working with
        $filename = $type . '_' . $id . '_export.csv';

        if ($count == 1) {
            // if this is the first go round, clear any old exports
            self::clear_exports();

            $fp = fopen($dir . $filename, 'w');
            switch ($type) {
                case 'links':
                    $header = array(
                        'Title',
                        'Type',
                        'Category',
                        'Tags',
                        'Published',
                    );

                    // normally GCS headers go here
                    // hanging onto space for format similarities

                    $header = array_merge($header, array(
                        'Source Page URL - (The page we are linking from)',
                        'Inbound Link Page Source URL',
                        'Inbound Link Anchor',
                        'Outbound Internal Link URL',
                        'Outbound Internal Link Anchor',
                        'Outbound External Link URL',
                        "Outbound External Link Anchor\n",
                    ));

                    $header = implode(',', $header);

                    break;
                case 'links_summary':
                    $header = "Title,URL,Type,Category,Tags,Published,Inbound internal links,Outbound internal links,Outbound external links\n";
                    break;
                case 'domains':
                    $header = "Domain,Post URL,Anchor Text,Anchor URL,Post Edit Link\n";
                    break;
                case 'domains_summary':
                    $header = "Domain,Post Count,Link Count\n";
                    break;
                case 'error':
                    $header = "Post,Broken URL,Type,Status,Discovered\n";
                    break;
                case 'clicks':
                    if (false && empty(get_option('wpil_disable_click_tracking_info_gathering', false))) { // TODO: Revisit and possibly enable if we ever dont do an aggregate report
                        $header = "Title,URL,Type,Link Anchor,Link URL,Link Created,IP Address,Total Clicks \n";
                    }else{
                        $header = "Title,URL,Type,Link Anchor,Link URL,Link Created,Total Clicks \n";
                    }
                    break;
                case 'clicks_summary':
                    $header = "Title,URL,Type,Total Number of Clicks \n";
                    break;
            }
            fwrite($fp, $header);
        } else {
            $fp = fopen($dir . $filename, 'a');
        }

        //get data
        $data = '';
        $func = 'csv_' . $type;
        if (method_exists('Wpil_export', $func)) {
            $data = self::$func($count);
        }

        //send finish response
        if (empty($data)) {
            header('Content-type: text/csv');
            header('Content-disposition: attachment; filename=' . $filename);
            header('Pragma: no-cache');
            header('Expires: 0');

            wp_send_json([
                'fileExists' => file_exists($dir . $filename),
                'filename' => $dir_url . $filename
            ]);
        }

        //write to file
        fwrite($fp, $data);

        // set the cleanup transient
        set_transient('wpil_clear_exports_folder', (time() + (5 * 60)), (DAY_IN_SECONDS * 7));

        wp_send_json([
            'filename' => '',
            'type' => $type,
            'count' => $count,
            'id' => $id
        ]);
    }

    /**
     * Prepare links data for export
     *
     * @return string
     */
    public static function csv_links($count)
    {
        $links = Wpil_Report::getData($count, '', 'ASC', '', 500);
        $redirected_posts = Wpil_Settings::getRedirectedPosts();
        $data = '';
        $post_url_cache = array();
        foreach ($links['data'] as $link) {
            // if the post is a 'post' and it's been redirected away from
            if($link['post']->type === 'post' && !empty($redirected_posts) && in_array($link['post']->id, $redirected_posts)){
                // skip it
                continue;
            }

            if (!empty($link['post']->getTitle())) {
                $inbound_internal  = $link['post']->getInboundInternalLinks();
                $outbound_internal = $link['post']->getOutboundInternalLinks();
                $outbound_external = $link['post']->getOutboundExternalLinks();

                $limit = max(count($inbound_internal), count($outbound_internal), count($outbound_external), 1); // throw in 1 just in case there's no links so we're sure to go around once

                for ($i = 0; $i < $limit; $i++) {
                    $post = $link['post'];
                    $cats = array();
                    foreach($post->getPostTerms(array('hierarchical' => true)) as $term){
                        $cats[] = $term->name;
                    }
                    $category = (!empty($cats)) ? '"' . addslashes(implode(', ', $cats)) . '"' : '';
    
                    // get any terms
                    $tags = array();
                    foreach($post->getPostTerms(array('hierarchical' => false)) as $term){
                        $tags[] = $term->name;
                    }
                    $tag = (!empty($tags)) ? '"' . addslashes(implode(', ', $tags)) . '"' : '';

                    $inbound_post_source_url = '';
                    if(!empty($inbound_internal[$i])){
                        $inbnd_id = $inbound_internal[$i]->post->id;
                        if(!isset($post_url_cache[$inbnd_id])){
                            $post_url_cache[$inbnd_id] = wp_make_link_relative($inbound_internal[$i]->post->getLinks()->view);
                        }
                        $inbound_post_source_url = $post_url_cache[$inbnd_id];
                    }

                    $item = [
                        !$i ? '"' . mb_convert_encoding(addslashes($post->getTitle()), 'UTF-8') . '"' : '',
                        !$i ? $post->getType() : '',
                        !$i ? '"' . $link['date'] . '"' : '',
                        wp_make_link_relative($post->getLinks()->view),
                        $inbound_post_source_url,
                        !empty($inbound_internal[$i]) ? '"' . addslashes(substr(trim(strip_tags($inbound_internal[$i]->anchor)), 0, 100)) . '"' : '',
                        !empty($outbound_internal[$i]) ? $outbound_internal[$i]->url : '',
                        !empty($outbound_internal[$i]) ? '"' . addslashes(substr(trim(strip_tags($outbound_internal[$i]->anchor)), 0, 100)) . '"' : '',
                        !empty($outbound_external[$i]) ? $outbound_external[$i]->url : '',
                        !empty($outbound_external[$i]) ? '"' . addslashes(substr(trim(strip_tags($outbound_external[$i]->anchor)), 0, 100)) . '"' : '',
                    ];

                    $data .= $item[0] . "," . $item[1] . "," . $category . "," . $tag . "," . $item[2] . "," . $item[3] . "," . $item[4] . "," . $item[5] .  "," . $item[6] . "," . $item[7] . "," . $item[8] . "," . $item[9] . "\n";
                }
            }
        }

        return $data;
    }

    public static function csv_links_summary($count)
    {
        $links = Wpil_Report::getData($count, '', 'ASC', '', 500);
        $redirected_posts = Wpil_Settings::getRedirectedPosts();
        $data = '';
        foreach ($links['data'] as $link) {
            // if the post is a 'post' and it's been redirected away from
            if($link['post']->type === 'post' && !empty($redirected_posts) && in_array($link['post']->id, $redirected_posts)){
                // skip it
                continue;
            }

            if (!empty($link['post']->getTitle())) {
                //prepare data
                $post = $link['post'];
                $title = '"' . mb_convert_encoding(addslashes($post->getTitle()), 'UTF-8') . '"';
                $url = wp_make_link_relative($post->getLinks()->view);
                $type = $post->getType();
                // get the post's categories
                $cats = array();
                foreach($post->getPostTerms(array('hierarchical' => true)) as $term){
                    $cats[] = $term->name;
                }
                $category = (!empty($cats)) ? '"' . addslashes(implode(', ', $cats)) . '"' : '';

                // get any terms
                $tags = array();
                foreach($post->getPostTerms(array('hierarchical' => false)) as $term){
                    $tags[] = $term->name;
                }
                $tag = (!empty($tags)) ? '"' . addslashes(implode(', ', $tags)) . '"' : '';

                $date = '"' . $link['date'] . '"';
                $ii_count = $post->getInboundInternalLinks(true);
                $oi_count = $post->getOutboundInternalLinks(true);
                $oe_count = $post->getOutboundExternalLinks(true);
                $data .= $title . "," . $url . "," . $type . "," . $category . "," . $tag . "," . $date . "," . $ii_count . "," . $oi_count . "," . $oe_count . "\n";
            }
        }

        return $data;
    }

    /**
     * Prepare clicks data for export
     *
     * @return string
     */
    public static function csv_clicks($count)
    {
        $clicks = Wpil_ClickTracker::get_data(500, $count, '', '', 'ASC');
        $data = '';
        foreach ($clicks['data'] as $click) {
            if (!empty($click->ID)) {
                $post = $click->post;
                $title = '"' . mb_convert_encoding(addslashes($click->post_title), 'UTF-8') . '"';
                $url = wp_make_link_relative($post->getLinks()->view);
                $type = $post->getType();

                $data .= $title . "," . $url . "," . $type . "\n";

                // Fetch detailed click data
                $click_detailed = Wpil_ClickTracker::get_detailed_click_table_data($post->id, $post->type, 1, 'total_clicks', 'desc', array('start' => '2000-01-01 01:00:00'));

                if (!empty($click_detailed)) {
                    foreach ($click_detailed['data'] as $detail) {
                        // Extract details from each click entry
                        if (isset($detail->link_anchor) && !empty($detail->link_anchor)) {
                            $link_anchor = '"' . mb_convert_encoding(addslashes($detail->link_anchor), 'UTF-8') . '"';
                        } else {
                            $link_anchor = '""';
                        }

                        if (isset($detail->link_url) && !empty($detail->link_url)) {
                            $link_url = '"' . $detail->link_url . '"';
                        } else {
                            $link_url = '""';
                        }

                        if (isset($detail->link_created) && !empty($detail->link_created)) {
                            // Convert timestamp to human-readable date format
                            $link_created = '"' . date('Y-m-d H:i:s', $detail->link_created) . '"';
                        } else {
                            $link_created = '"Unknown"';
                        }

                        if (isset($detail->total_clicks) && !empty($detail->total_clicks)) {
                            $total_clicks = '"' . $detail->total_clicks . '"';
                        } else {
                            $total_clicks = '""';
                        }

                        // Check if IP tracking is enabled // TODO: Revisit and possibly enable if we ever dont do an aggregate report
                        if (false && empty(get_option('wpil_disable_click_tracking_info_gathering', false))) {
                            // Include IP column only if tracking is enabled
                            if (isset($detail->user_ip) && !empty($detail->user_ip)) {
                                $user_ip = '"' . $detail->user_ip . '"';
                            } else {
                                $user_ip = '""';
                            }

                            // Append detailed data, including IP
                            $data .= ",,," . $link_anchor . "," . $link_url . "," . $link_created . "," . $user_ip . "," . $total_clicks . "\n";
                        } else {
                            // Append detailed data without IP
                            $data .= ",,," . $link_anchor . "," . $link_url . "," . $link_created . "," . $total_clicks . "\n";
                        }
                    }
                }
            }
        }

        return $data;
    }

    public static function csv_clicks_summary($count)
    {
        $clicks = Wpil_ClickTracker::get_data(500, $count, '', '', 'ASC');

        $data = '';
        foreach ($clicks['data'] as $click) {
            if (!empty($click->post_title)) {
                //prepare data
                $post = $click->post;
                $title = '"' . mb_convert_encoding(addslashes($click->post_title), 'UTF-8') . '"';
                $url = wp_make_link_relative($post->getLinks()->view);
                $type = $post->getType();
                $total_clicks = $click->clicks ?? 0;
                $data .= $title . "," . $url . "," . $type . "," . $total_clicks . "\n";

            }
        }

        return $data;
    }

    /**
     * Prepare domains data for export
     *
     * @return string
     */
    public static function csv_domains($count)
    {
        $domains = Wpil_Dashboard::getDomainsData(500, $count, '', 'domain', true, true);
        $data = '';
        foreach ($domains['domains'] as $domain) {
            $max = max(count($domain['posts']), count($domain['links']), 1);
            for ($i=0; $i < $max; $i++) {
                $post = $domain['links'][$i]->post;
                $item = [
                    $domain['host'],
                    !empty($post) ? str_replace('&amp;', '&', $post->getLinks()->view) : '',
                    !empty($domain['links'][$i]->url) ? $domain['links'][$i]->anchor : '',
                    !empty($domain['links'][$i]->url) ? $domain['links'][$i]->url : '',
                    !empty($post) ? str_replace('&amp;', '&', $post->getLinks()->edit) : '',
                ];

                $data .= $item[0] . "," . $item[1] . "," . $item[2] . "," . $item[3] . "," . $item[4] . "\n";
            }
        }

        return $data;
    }

    /**
     * Prepare domains summary data for export
     *
     * @param $count
     * @return string
     */
    public static function csv_domains_summary($count)
    {
        $domains = Wpil_Dashboard::getDomainsData(500, $count, '', 'domain', true, true);
        $data = '';
        foreach ($domains['domains'] as $domain) {
            $data .= $domain['host'] . "," . count($domain['posts']) . "," . count($domain['links']) . "\n";
        }

        return $data;
    }

    /**
     * Prepare errors data for export
     *
     * @return string
     */
    public static function csv_error($count)
    {
        $links = Wpil_Error::getData(500, $count);
        $data = '';
        foreach ($links['links'] as $link) {
            $item = [
                '"' . addslashes($link->post_title) . '"',
                '"' . addslashes($link->url) . '"',
                $link->internal ? 'internal' : 'external',
                $link->code . ' ' . Wpil_Error::getCodeMessage($link->code),
                '"' . date(addslashes(get_option('date_format', 'd M Y') . ' ' . get_option('time_format', '(H:i)')), strtotime($link->created)) . '"'
            ];
            $data .= $item[0] . "," . $item[1] . "," . $item[2] . "," . $item[3] . "," . $item[4] . "\n";
        }

        return $data;
    }

    /**
     * Exports suggestion data in CSV or Excel formats.
     * Using a separate method from the ajax_csv since this handles data from the frontend,
     * and I want to keep things less complicated on that front.
     **/
    public static function ajax_export_suggestion_data(){
        Wpil_Base::verify_nonce('export-suggestions-' . $_POST['export_data']['id']);

        if(empty($_POST['export_data']) || empty($_POST['export_data']['id'])){
            wp_send_json(array('error' => array('title' => __('No Suggestion Data', 'wpil'), 'text' => __('The suggestion data wasn\'t able to be downloaded. Please reload the page and try again', 'wpil'))));
        }

        // decode the data
        $_POST['export_data']['data'] = json_decode(stripslashes($_POST['export_data']['data']), true);

        if(!empty(json_last_error())){
            wp_send_json(array('error' => array('title' => __('Data Error', 'wpil'), 'text' => __('There was a problem in processing the suggestion data. Please reload the page and try again', 'wpil'))));
        }

        if($_POST['export_data']['export_type'] === 'csv'){
            self::create_csv_suggestion_export($_POST['export_data']);
        }elseif($_POST['export_data']['export_type'] === 'excel'){
            self::create_excel_suggestion_export();
        }
    }

    public static function create_csv_suggestion_export($data){
        $gsc_authed = Wpil_Settings::HasGSCCredentials();
        $options = get_user_meta(get_current_user_id(), 'report_options', true); 
        $show_traffic = (isset($options['show_traffic'])) ? ( ($options['show_traffic'] == 'off') ? false : true) : false;


        if($data['suggestion_type'] === 'outbound'){
            $source_post = new Wpil_Model_Post((int)$data['id'], sanitize_text_field($data['type']));
            $filename = $source_post->id . '-' . $source_post->getSlug(false) . '_outbound-suggestions.csv';
        }elseif($data['suggestion_type'] === 'inbound'){
            $destination_post = new Wpil_Model_Post((int)$data['id'], sanitize_text_field($data['type']));
            $filename = $destination_post->id . '-' . $destination_post->getSlug(false) . '_inbound-suggestions.csv';
        }

        $header = "Source Post Title, Source Post URL, Source Sentence Text, Suggested Anchor Text, Destination Post Title, Destination Post URL";
        if($gsc_authed && $show_traffic){
            $header .= ", Source Post GSC Clicks, Source Post GSC Impressions, Source Post GSC Average Position, Source Post GSC CTR";
        }
        $header .= "\n";

        // get the directory that we'll be writing the export to
        $dir = false;
        $dir_url = false;
        if(is_writable(WP_INTERNAL_LINKING_PLUGIN_DIR)){
            // if it's possible, write to the plugin directory
            $dir = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/';
            $dir_url = WP_INTERNAL_LINKING_PLUGIN_URL . 'includes/';
        }else{
            // if writing to the plugin directory isn't possible, try for the uploads folder
            $uploads = wp_upload_dir(null, false);
            if(!empty($uploads) && isset($uploads['basedir']) && is_writable($uploads['basedir'])){
                if(wp_mkdir_p(trailingslashit($uploads['basedir']). 'link-whisper-premium/exports')){
                    $dir = trailingslashit($uploads['basedir']). 'link-whisper-premium/exports/';
                    $dir_url = trailingslashit($uploads['baseurl']). 'link-whisper-premium/exports/';
                }
            }
        }

        // if we aren't able to write to any directories
        if(empty($dir)){
            // tell the user about it
            wp_send_json([
                'error' => [
                    'title' => __('File Permission Error', 'wpil'),
                    'text'  => __('The uploads folder isn\'t writable by Link Whisper. Please contact your host or webmaster about making the "/uploads/link-whisper-premium/" folder writable.', 'wpil') // we're defaulting to the uploads folder here since it's the easiest one to support
                ]
            ]);
        }

        $fp = fopen($dir . 'suggestion_export.csv', 'w');

        fwrite($fp, $header);

        //get data
        $export_data = '';
        $post_cache = array();
        foreach($data['data'] as $link_data){
            foreach ($link_data['links'] as $dat) {
                $cache_id = $dat['id'] . '_' . $dat['type'];
                if($data['suggestion_type'] === 'outbound'){
                    if(isset($post_cache[$cache_id])){
                        $destination_post = $post_cache[$cache_id];
                    }else{
                        $destination_post = new Wpil_Model_Post($dat['id'], $dat['type']);
                    }
                }else{
                    if(isset($post_cache[$cache_id])){
                        $source_post = $post_cache[$cache_id];
                    }else{
                        $source_post = new Wpil_Model_Post($dat['id'], $dat['type']);
                    }
                }

                $dat['sentence'] = trim(strip_tags($dat['sentence_with_anchor'])); // for some reason, the custom sentence doesn't always get picked up. So we'll run with the sentence with anchor
                $dat['sentence_with_anchor'] = trim(stripslashes($dat['sentence_with_anchor']));

                $link = Wpil_Post::getSentenceWithAnchor($dat);
                $source_sentence_text = strip_tags($link);
                preg_match('|<a[^>]*>(.*?)<\/a>|i', $link, $anchor_text);
                $anchor_text = (isset($anchor_text[1]) && !empty($anchor_text[1])) ? strip_tags($anchor_text[1]) : '';

                // Source Post Title, Source Post URL, Source Sentence Text, Suggested Anchor Text, Destination Post Title, Destination Post URL
                $item = array(
                    '"' . $source_post->getTitle() . '"',
                    '"' . str_replace('&amp;', '&', $source_post->getLinks()->view) . '"',
                    '"' . $source_sentence_text . '"',
                    '"' . $anchor_text . '"',
                    '"' . $destination_post->getTitle() . '"',
                    '"' . str_replace('&amp;', '&', $destination_post->getLinks()->view) . '"'
                );

                // if GSC is authed and the user wants to see GSC data
                if($gsc_authed && $show_traffic){
                    $item[] = $source_post->get_organic_traffic()->clicks;
                    $item[] = $source_post->get_organic_traffic()->impressions;
                    $item[] = $source_post->get_organic_traffic()->position;
                    $item[] = $source_post->get_organic_traffic()->ctr;
                }

                $export_data .= implode(',', $item) . "\n";

                // cache the post if it's not already cached
                if(!isset($post_cache[$cache_id])){
                    $post_cache[$cache_id] = ($data['suggestion_type'] === 'outbound') ? $destination_post: $source_post;
                }
            }
        }

        //write to file
        fwrite($fp, $export_data);
        fclose($fp);

        //send finish response
        header('Content-disposition: attachment; filename=suggestion_export.csv');

        wp_send_json([
            'filename' => $dir_url . 'suggestion_export.csv',
            'nicename' => $filename
        ]);
    }


    /** 
     * Todo: create when someone asks for it
     **/
    public static function create_excel_suggestion_export(){
        
    }

    public static function clear_exports(){
        $dir = false;
        if(is_writable(WP_INTERNAL_LINKING_PLUGIN_DIR)){
            // if it's possible, write to the plugin directory
            $dir = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/exports/';
        }else{
            // if writing to the plugin directory isn't possible, try for the uploads folder
            $uploads = wp_upload_dir(null, false);
            if(!empty($uploads) && isset($uploads['basedir']) && is_writable($uploads['basedir'])){
                if(wp_mkdir_p(trailingslashit($uploads['basedir']). 'link-whisper-premium/exports')){
                    $dir = trailingslashit($uploads['basedir']). 'link-whisper-premium/exports/';
                }
            }
        }

        if(empty($dir)){
            return;
        }

        // if this is the first go round, clear any old exports
        $files = glob($dir . '*_export.csv');
        if(!empty($files)){
            foreach($files as $file){
                unlink($file);
            }
        }
    }
}
