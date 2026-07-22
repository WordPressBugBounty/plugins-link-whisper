<?php
use LWVendor\PhpOffice\PhpSpreadsheet\Calculation\MathTrig\Trig\Cotangent;

/**
 * AI controller
 */
class Wpil_AI
{
    private static $ai;
    private static $ai_service_connected = null;
    public static $question_limit = 6000;
    public static $concurrency = 100;
    public static $magic = '';
    public static $magnatude_cache = array();
    public static $dot_product_cache = array();
    public static $batch_limit = null;
    public static $cached_embedding_data = array();
    public static $cached_post_sentence_embedding_data = array();
    public static $status_cache = array();
    public static $complete_log_cache = array();
    public static $chunked_posts = array();
    public static $query_ids = array();
    public static $sentence_anchor_cache = array();
    public static $origin_post = null;
    public static $purpose = null;
    public static $model = null;
    public static $rate_limited = false;
    public static $insufficient_quota = false;
    public static $user_not_exist = false;
    public static $invalid_request = false;
    public static $invalid_api_key = false;
    public static $error_message = '';
    public static $error_log = array();
    public static $current_error = false;
    public static $anchor_assessment_ids = array();
    public static $active_linking_process_key = '';
    public static $keyword_cannibalization_keywords = null;
    public static $active_credit_tracking_context = array();
    public static $request_credit_tracking_runs = array();

    function __construct()
    {
        // todo: temp!
        self::$batch_limit = 50000;

        self::$ai_service_connected = (Wpil_Settings::get_linkwhisper_ai_active() && Wpil_Settings::get_linkwhisper_ai_token());
        self::$concurrency = 100;
    }

    public function register()
    {
        add_action('wp_ajax_wpil_live_download_ai_data', [__CLASS__, 'ajax_live_download_ai_data']);
        add_action('wp_ajax_wpil_cancel_dashboard_basic_scan', [__CLASS__, 'ajax_cancel_dashboard_basic_scan']);
        add_action('wp_ajax_wpil_live_ai_linking', [__CLASS__, 'ajax_live_ai_linking']);
        add_action('wp_ajax_wpil_clear_ai_data', [__CLASS__, 'ajax_wpil_clear_ai_data']);
        add_action('wp_ajax_wpil_clear_ai_relation_data', [__CLASS__, 'ajax_wpil_clear_ai_relation_data']);
        add_action('wp_ajax_wpil_clear_ai_keyword_data', [__CLASS__, 'ajax_wpil_clear_ai_keyword_data']);
        add_action('wp_ajax_wpil_clear_ai_embedding_calculation_v2', [__CLASS__, 'ajax_wpil_clear_ai_embedding_calculation_v2']);
        add_action('wp_ajax_wpil_rescan_empty_ai_embeddings', [__CLASS__, 'ajax_rescan_empty_ai_embeddings']);
        add_action('wp_ajax_wpil_clear_ai_linking_process_data', [__CLASS__, 'ajax_clear_ai_linking_process_data']);
        add_action('wp_ajax_wpil_ai_dismiss_credit_notice', [__CLASS__, 'ajax_wpil_dismiss_credit_notice']);
        add_action('wp_ajax_wpil_ai_dismiss_api_key_decoding_error', [__CLASS__, 'ajax_wpil_dismiss_api_key_decoding_error']);
        add_action('wp_ajax_wpil_estimate_site_processing_cost', [__CLASS__, 'ajax_estimate_site_processing_cost']);
        add_action('wp_ajax_setup_user_ai_subscription', array(__CLASS__, 'ajax_setup_user_ai_subscription'));
        add_action('wp_ajax_clear_user_ai_subscription', array(__CLASS__, 'ajax_clear_user_ai_subscription'));
        add_filter('cron_schedules', [__CLASS__, 'add_batch_cron_interval']);
        add_action('admin_init', [__CLASS__, 'schedule_batch_process']);
        add_action('wpil_ai_batch_process_cron', [__CLASS__, 'perform_cron_batch_process']);
        add_filter('orhanerday_openai_stream_response_data', [__CLASS__, 'process_streamed_data'], 10, 3);
        add_action('wp_ajax_wpil_get_review_links', array(__CLASS__, 'ajax_get_review_links'));
        add_action('wp_ajax_wpil_get_review_link_count', array(__CLASS__, 'ajax_get_review_link_count'));
        add_action('wp_ajax_wpil_set_review_link_decision', array(__CLASS__, 'ajax_set_review_link_decision'));
        add_action('wp_ajax_wpil_get_wizard_credit_estimate', [__CLASS__, 'ajax_get_wizard_credit_estimate']);
    }

    /**
     * @return array
     **/
    public static function call_linkwhisper_ai($content = '', $model = '', $action = '', $params = []){
        if(is_array($content)){
            $args = array(
                'user_id' => Wpil_Settings::get_linkwhisper_ai_user_id(),
                'action' => (!empty($action)) ? $action: self::$purpose,
                'message_list' => $content,
                'input' => '',
                'model' => (!empty($model)) ? $model: self::$model,
                'access_token' => Wpil_Settings::get_linkwhisper_ai_token(),
                'url' => site_url()
            );
        }else{
            $args = array(
                'user_id' => Wpil_Settings::get_linkwhisper_ai_user_id(),
                'action' => (!empty($action)) ? $action: self::$purpose,
                'input' => $content,
                'model' => (!empty($model)) ? $model: self::$model,
                'access_token' => Wpil_Settings::get_linkwhisper_ai_token(),
                'url' => site_url()
            );
        }

        if(!empty($params)){
            $args['params'] = $params;
        }

        // exist if there are 
        /*$creds = self::get_available_ai_credits();
        if(empty($creds)){
            return array();
        }*/

        $response = null;
        if(is_array($content)){
            $response = self::sendMultiRequest($args);
        }else{
            $response = self::sendRequest($args);
        }

        return $response; 
    }

    /**
     * @param  string  $url
     * @param  string  $method
     * @param  array   $opts
     * @return bool|string
     */
    private static function sendRequest($opts = [])
    {
        $post_fields = wp_json_encode($opts);
        $headers = array("Content-Type: application/json");
        $curl_info = [
            CURLOPT_USERAGENT      => WPIL_DATA_USER_AGENT,
            CURLOPT_URL            => 'https://api.linkwhisper.com/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_POST           => 1,
            CURLOPT_POSTFIELDS     => $post_fields,
            CURLOPT_HTTPHEADER     => $headers
        ];

        if ($opts == []) {
            unset($curl_info[CURLOPT_POSTFIELDS]);
        }

        $curl = curl_init();
        curl_setopt_array($curl, $curl_info);
        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }

    /**
     * @param  string  $url
     * @param  string  $method
     * @param  array   $opts
     * @return bool|string
     */
    private static function sendMultiRequest($opts = [])
    {
        // create the multihandle
        $mh = curl_multi_init();
        $handles = array();
        $messages = $opts['message_list'];
        unset($opts['message_list']);

        for($i = 0; $i < self::$concurrency; $i++){
            if(!isset($messages[$i])){
                break;
            }

            $curl_opts = array_merge($opts, ['input' => $messages[$i]]);
            $post_fields    = wp_json_encode($curl_opts);
            $headers = array("Content-Type: application/json");

            $curl_info = [
                CURLOPT_URL            => 'https://api.linkwhisper.com/',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING       => '',
                CURLOPT_MAXREDIRS      => 10,
                CURLOPT_TIMEOUT        => 60,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                CURLOPT_POST           => 1,
                CURLOPT_POSTFIELDS     => $post_fields,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_BUFFERSIZE     => 65536,
            ];
            if ($curl_opts == []) {
                unset($curl_info[CURLOPT_POSTFIELDS]);
            }

            $handles[$i] = curl_init();
            curl_setopt_array($handles[$i], $curl_info);
            curl_multi_add_handle($mh, $handles[$i]);
        }

        if(!empty($handles)){
            do {
                $status = curl_multi_exec($mh, $active);
                
                // Check if any handle has completed
                while ($info = curl_multi_info_read($mh)) {
                    $handle = $info['handle'];

                    // Only process if the handle completed successfully
                    if ($info['result'] === CURLE_OK) {
                        // Find the array key associated with this handle
                        $handle_id = array_search($handle, $handles, true);

                        // Get content from the handle
                        $content = curl_multi_getcontent($handle);
                        $info    = curl_getinfo($handle);

                        $processed = has_filter('orhanerday_openai_stream_response_data') ? apply_filters('orhanerday_openai_stream_response_data', $handle_id, $content, $info) : false;
                        if($processed){
                            // Remove the handle once processed
                            curl_multi_remove_handle($mh, $handle);
                            curl_close($handle);
                            unset($handles[$handle_id]);
                        }
                    }
                }
                
                if ($active) {
                    curl_multi_select($mh);
                }
            } while ($active && $status == CURLM_OK);
        }

        $responses = array();
        foreach($handles as $handle_id => $handle){
            $responses[$handle_id] = curl_multi_getcontent($handle);
            curl_multi_remove_handle($mh, $handle);
            curl_close($handle);
        }
        curl_multi_close($mh);
        return $responses;
    }

    /**
     * Does stuff...
     * Ok fine, it does live ai linking.
     * For the One Click Setup
     **/
    public static function ajax_live_ai_linking(){
        Wpil_Base::verify_nonce('wpil_download_ai_data');
        // be sure to ignore any external object caches
        Wpil_Base::ignore_external_object_cache();
        // Remove any hooks that may interfere with AJAX requests
        Wpil_Base::remove_problem_hooks();
        wp_send_json(array('success' => array(
            'progress' => 100,
            'ai_credits' => self::get_available_ai_credits(),
            'estimated_credit_cost' => self::estimate_site_processing_credit_cost('', false),
            'review_ready_count' => 0,
            'disabled' => true,
        )));
    }

    private static function is_release_disabled_ai_linking_process($process_key = '', $fix_type = ''){
        $process_key = is_string($process_key) ? sanitize_text_field($process_key) : '';
        $fix_type = is_string($fix_type) ? sanitize_key($fix_type) : '';

        if($process_key === md5('one-click-setup')){
            return true;
        }

        if(!empty($process_key) && in_array($process_key, self::get_dashboard_fix_process_keys(), true)){
            return true;
        }

        return in_array($fix_type, array('orphaned_posts', 'link_coverage', 'link_quality', 'broken_links', 'external_focus'), true);
    }

    public static function get_process_review_link_count($process_key = ''){
        global $wpdb;

        $process_key = is_string($process_key) ? sanitize_text_field($process_key) : '';
        if(empty($process_key)){
            return 0;
        }

        if(self::is_release_disabled_ai_linking_process($process_key)){
            return 0;
        }

        $table = $wpdb->prefix . 'wpil_ai_linking';
        $sql = $wpdb->prepare(
            "SELECT COUNT(*)
                FROM {$table}
                WHERE inserted = 0
                    AND ignored = 0
                    AND process_key = %s",
            $process_key
        );

        return (int) $wpdb->get_var($sql);
    }

    /**
     * Gets the ids that have active linking suggestions waiting for review.
     **/
    public static function get_linking_suggestion_processed_ids($process_key = '', $direction = 'outbound', $pid = ''){
        global $wpdb;
        $linking_table = $wpdb->prefix . 'wpil_ai_linking';

        $pid = self::normalize_pid($pid);
        $pid_parts = !empty($pid) ? self::parse_pid($pid) : array();

        $search = "CONCAT(`post_type`, '_', `post_id`)";
        if($direction === 'inbound'){
            $search = "CONCAT(`target_type`, '_', `target_id`)";
        }

        $sql = "SELECT DISTINCT {$search} FROM {$linking_table} WHERE inserted = 0 AND ignored = 0";
        if(!empty($process_key)){
            $sql .= $wpdb->prepare(" AND process_key = %s", $process_key);
        }

        if(!empty($pid_parts['id'])){
            if($direction === 'inbound'){
                $search = "CONCAT(`post_type`, '_', `post_id`)";
                $sql = "SELECT DISTINCT {$search} FROM {$linking_table} WHERE inserted = 0 AND ignored = 0";
                if(!empty($process_key)){
                    $sql .= $wpdb->prepare(" AND process_key = %s", $process_key);
                }
                $sql .= $wpdb->prepare(" AND target_id = %d AND target_type = %s", $pid_parts['id'], $pid_parts['type']);
            }else{
                $search = "CONCAT(`target_type`, '_', `target_id`)";
                $sql = "SELECT DISTINCT {$search} FROM {$linking_table} WHERE inserted = 0 AND ignored = 0";
                if(!empty($process_key)){
                    $sql .= $wpdb->prepare(" AND process_key = %s", $process_key);
                }
                $sql .= $wpdb->prepare(" AND post_id = %d AND post_type = %s", $pid_parts['id'], $pid_parts['type']);
            }
        }

        $ids = $wpdb->get_col($sql);

        return (!empty($ids)) ? $ids: [];
    }

    /**
     * 
     **/
    public static function ajax_live_download_ai_data(){
        Wpil_Base::verify_nonce('wpil_download_ai_data');
        // be sure to ignore any external object caches
        Wpil_Base::ignore_external_object_cache();
        // Remove any hooks that may interfere with AJAX requests
        Wpil_Base::remove_problem_hooks();
        $ai_linking_enabled = self::is_ai_linking_enabled_request();
        $dashboard_basic_scan = self::is_dashboard_basic_scan_request();
        $selected_processes = ($dashboard_basic_scan) ? self::get_dashboard_basic_scan_processes(): Wpil_Settings::get_selected_ai_batch_processes(true);
        $processed_embeddings = false;
        $initial_stats = self::get_completed_post_stats(true);
        $total_posts = self::get_total_processable_posts();
        $post_saving = true;
        $last_pass_unchanged = (array_key_exists('last_pass_unchanged', $_POST) && $_POST['last_pass_unchanged'] === '1') ? true: false;

        if($dashboard_basic_scan && get_transient('wpil_dashboard_basic_scan_cancelled') && !(isset($_POST['start_time']) && empty($_POST['start_time']))){
            delete_transient('wpil_doing_ai_data_download');
            delete_transient('wpil_doing_ai_data_download_mode');
            delete_transient('wpil_dashboard_basic_scan_process_text');

            wp_send_json(array(
                'cancelled' => array(
                    'title' => __('Scan Cancelled', 'wpil'),
                    'text'  => __('The basic AI scan has been cancelled.', 'wpil'),
                    'dashboard_basic_scan' => self::get_dashboard_basic_scan_status(true),
                )
            ));
        }

        // set a flag so that we know that we're downloading data
        set_transient('wpil_doing_ai_data_download', time(), MINUTE_IN_SECONDS * 3);
        set_transient('wpil_doing_ai_data_download_mode', ($dashboard_basic_scan ? 'dashboard-basic-scan': 'default'), MINUTE_IN_SECONDS * 3);
        // if the batch processing is supposed to be turned on
        if(isset($_POST['activate_batch_processing']) && !empty($_POST['activate_batch_processing'])){
            // turn it on
            update_option('wpil_enable_ai_batch_processing', '1');
        }

        $start_time = isset($_POST['start_time']) && !empty($_POST['start_time']) ? (int)$_POST['start_time']: time();
        $current_process = esc_html__('Sending Site Data to AI for Processing', 'wpil');

        // if the batch processing is supposed to be turned on
        if(isset($_POST['activate_batch_processing']) && !empty($_POST['activate_batch_processing'])){
            // turn it on
            update_option('wpil_enable_ai_batch_processing', '1');
        }

        // if this is the first go round
        if(isset($_POST['start_time']) && empty($_POST['start_time'])){
            if($dashboard_basic_scan){
                delete_transient('wpil_dashboard_basic_scan_cancelled');
            }

            // and we're connected to the ai service
            if(self::$ai_service_connected){
                // do a credit check
                $credit = self::get_available_ai_credits(true);
                if($credit < 1){
                    self::$insufficient_quota = true;
                }
            }

            // clear the embedding id lock
            self::set_last_embedding_id_lock();
        }

        if(in_array('create-post-embeddings', $selected_processes)){
            $current_process = esc_html__('Generating AI Relation Data...', 'wpil');

            // if we're not running the ai service
            if(!self::$ai_service_connected){
                // up the number of concurrant processes we'll run for the post embeddings
                self::$concurrency = Wpil_Settings::get_ai_process_limit('create-post-embeddings', true);
                self::$ai->setConcurrency(self::$concurrency);
            }

            self::create_site_embeddings();

            // if we're still not running the ai service
            if(!self::$ai_service_connected){
                // set the concurrancy back to where it should be
                self::$concurrency = 100;
                self::$ai->setConcurrency(self::$concurrency);
            }
        }

        // if there are no embeddings currently being processed and we still have time
        $has_completed_embeddings = self::has_completed_post_embedding_calculations();
        if(!Wpil_Base::overTimeLimit(5, 35) && self::has_completed_post_embeddings() && !$has_completed_embeddings){
            $current_process = esc_html__('Calculating AI Relation Scores...', 'wpil');
            $processed_embeddings = self::stepped_calculate_post_embeddings();
            if($processed_embeddings){
                $total = count(self::get_calculated_embedding_post_ids());
                $current_process .= sprintf(esc_html__(' %d Total Posts Scored...', 'wpil'), $total);
            }

            // clear the old AI Sitemap
            Wpil_Sitemap::delete_sitemap(false, 'ai_sitemap');
        }

        if($has_completed_embeddings && !Wpil_Sitemap::has_sitemap('ai_sitemap')){
            $relatedness = Wpil_AI::calculate_relatedness_sitemap();
            Wpil_Sitemap::save_sitemap($relatedness, 'ai_sitemap', 'AI Sitemap');
        }

        // if we haven't made downloading progress and there's time
        if($last_pass_unchanged && !Wpil_Base::overTimeLimit(5, 20)){
            // try to process any available keywords
            $post_saving = self::do_post_save_finishing();
            if(!$post_saving){
                $current_process = esc_html__('Processing Site Data...', 'wpil');
            }
        }

        if(!Wpil_Base::overTimeLimit(5, 20)){
            $current_process = esc_html__('Analyzing Site Posts...', 'wpil');
            self::analyze_site_posts(($dashboard_basic_scan) ? $selected_processes: null);
        }

        if(!Wpil_Base::overTimeLimit(5, 20)){
            $post_saving = self::do_post_save_finishing();
            if(!$post_saving){
                $current_process = esc_html__('Processing Site Data...', 'wpil');
            }

            if(!$dashboard_basic_scan && !Wpil_Sitemap::has_sitemap('ai_product_sitemap') && self::check_batch_status_completed(3, true)){
                $products = Wpil_AI::calculate_product_sitemap();
                if(!empty($products)){
                    Wpil_Sitemap::save_sitemap($products, 'ai_product_sitemap', 'AI-Detected Product Sitemap');
                }
            }
        }

        $current_stats = self::get_completed_post_stats(true, (!$dashboard_basic_scan));
        $all_processed = array();
        $completed = false;
        $live_processed_results = array();
        $oai_completed = array();

        if(!empty($current_stats)){
            foreach($current_stats as $ind => $count){
                if($dashboard_basic_scan && !in_array($ind, array('create-post-embeddings', 'calculated-post-embeddings', 'keyword-detecting', 'keyword-assigning'), true)){
                    continue;
                }

                if((int)$count >= (int)$total_posts){
                    $all_processed[$ind] = true;

                    if(
                        in_array($ind, $selected_processes) ||
                        ($dashboard_basic_scan && $ind === 'calculated-post-embeddings') ||
                        ($dashboard_basic_scan && $ind === 'keyword-assigning' && in_array('keyword-detecting', $selected_processes, true))
                    ){
                        $oai_completed[$ind] = true;
                    }
                }

                if(array_key_exists($ind, $initial_stats)){
                    $live_processed_results[$ind] = ($count - $initial_stats[$ind]);
                }else{
                    $live_processed_results[$ind] = $count;
                }

                if(empty($live_processed_results[$ind])){
                    unset($live_processed_results[$ind]);
                }
            }

            if(!$dashboard_basic_scan && count(array_filter($all_processed)) === count($current_stats)){
                $completed = true;
            }
        }

        $dashboard_scan_status = self::get_dashboard_basic_scan_status(true);
        if($dashboard_basic_scan){
            $completed = !empty($dashboard_scan_status['basic_scan_complete']);
            $oai_completed = $completed;
        }else{
            $oai_completed = (count(array_filter($oai_completed)) === count($selected_processes)) ? true: false;
        }
        set_transient('wpil_dashboard_basic_scan_process_text', $current_process, MINUTE_IN_SECONDS * 5);

        $response = array();
        if(self::$insufficient_quota || self::$invalid_request || self::$invalid_api_key || self::$user_not_exist){
            if(self::$insufficient_quota){
                update_option('wpil_oai_insufficient_quota_error', '1');
            }
            $error = (self::$ai_service_connected) ? self::get_linkwhisper_ai_error_message(): self::get_live_oai_error_message();
            if($dashboard_basic_scan){
                $dashboard_scan_status = self::get_dashboard_basic_scan_status(true);
                $error['dashboard_basic_scan'] = $dashboard_scan_status;
            }
            $response = array('error' => $error);
        }elseif(!$completed || !$post_saving || $processed_embeddings > 0){
            if($dashboard_basic_scan){
                $dashboard_scan_status = self::get_dashboard_basic_scan_status(true);
            }
            $response = array(
                'continue' => array(
                    'data' => $live_processed_results,
                    'data_total_processed' => $current_stats,
                    'oai_completed' => $oai_completed,
                    'all' =>  $all_processed,
                    'post_saving' => $post_saving,
                    'processed_embeddings' => $processed_embeddings,
                    'estimated_cost' => self::calculate_token_cost_by_time($start_time),
                    'current_process' => $current_process,
                    'start_time' => $start_time,
                    'completed' => $completed,
                    'lock' => self::get_last_embedding_id_lock(),
                    'completion_messages' => array(
                        'info' => array(
                            'title' => __('Processing Halted', 'wpil'), 
                            'text' => __('Link Whisper is not currently able to process any more posts. The reason for this is unclear, there may have been an error, or it could be because all the posts are finished processing. Please check the System Error Log to see if there are any errors, and the Content Processing Status to see if all of the posts are processed.', 'wpil')
                        ),
                        'error' => (self::$ai_service_connected) ? self::get_linkwhisper_ai_error_message() : self::get_live_oai_error_message()
                        ),
                    'is_rate_limited' => self::$rate_limited,
                    'ai_credits' => self::get_available_ai_credits(),
                    'estimated_credit_cost' => ($dashboard_basic_scan) ? self::estimate_dashboard_basic_scan_credit_cost(): self::estimate_site_processing_credit_cost(get_option('wpil_ai_linking_process_key', null), $ai_linking_enabled),
                    'dashboard_basic_scan' => $dashboard_scan_status
                )
            );
        }else{
            if($dashboard_basic_scan){
                $dashboard_scan_status = self::get_dashboard_basic_scan_status(true);
            }
            $response = array(
                'success' => array(
                    'title' => __('Processing Complete!', 'wpil'),
                    'text'  => __('All available site data has been processed!', 'wpil'),
                    'oai_completed' => $oai_completed,
                    'estimated_cost' => self::calculate_token_cost_by_time($start_time),
                    'ai_credits' => self::get_available_ai_credits(),
                    'estimated_credit_cost' => ($dashboard_basic_scan) ? self::estimate_dashboard_basic_scan_credit_cost(): self::estimate_site_processing_credit_cost(get_option('wpil_ai_linking_process_key', null), $ai_linking_enabled),
                    'dashboard_basic_scan' => $dashboard_scan_status
                )
            );
        }

        wp_send_json($response);
    }

    /**
     * Tells the Dashboard setup scan to stop on the next pass.
     **/
    public static function ajax_cancel_dashboard_basic_scan(){
        Wpil_Base::verify_nonce('wpil_download_ai_data');

        set_transient('wpil_dashboard_basic_scan_cancelled', time(), 10 * MINUTE_IN_SECONDS);
        delete_transient('wpil_doing_ai_data_download');
        delete_transient('wpil_doing_ai_data_download_mode');
        delete_transient('wpil_dashboard_basic_scan_process_text');

        wp_send_json(array(
            'success' => array(
                'title' => __('Scan Cancelled', 'wpil'),
                'text'  => __('The basic AI scan has been cancelled.', 'wpil'),
                'dashboard_basic_scan' => self::get_dashboard_basic_scan_status(true),
            )
        ));
    }

    public static function ajax_wpil_clear_ai_data(){
        Wpil_Base::verify_nonce('wpil_clear_ai_data');

        $cleared =  self::clear_ai_data();

        $response = array();
        if($cleared){
            $response = array(
                'success' => array(
                    'title' => __('Data Cleared!', 'wpil'),
                    'text'  => __('All AI generated data has been deleted.', 'wpil'),
                )
            );
        }else{
            $response = array(
                'error' => array(
                    'title' => __('Unknown Error', 'wpil'),
                    'text'  => __('Unfortunately, there was an error while trying to clear the AI data, and there may still be some stored on the site.', 'wpil'),
                )
             );
        }

        wp_send_json($response);
    }

    public static function ajax_wpil_clear_ai_relation_data(){
        Wpil_Base::verify_nonce('wpil_clear_ai_data');

        $cleared =  self::clear_ai_data(['relation_analysis']);

        $response = array();
        if($cleared){
            $response = array(
                'success' => array(
                    'title' => __('Data Cleared!', 'wpil'),
                    'text'  => __('All AI Relation Analysis data has been deleted.', 'wpil'),
                )
            );
        }else{
            $response = array(
                'error' => array(
                    'title' => __('Unknown Error', 'wpil'),
                    'text'  => __('Unfortunately, there was an error while trying to clear the AI Relation Analysis data, and there may still be some stored on the site.', 'wpil'),
                )
             );
        }

        wp_send_json($response);
    }

    public static function ajax_wpil_clear_ai_keyword_data(){
        Wpil_Base::verify_nonce('wpil_clear_ai_data');

        $cleared =  self::clear_ai_data(['keyword_analysis']);
        // also remove any assigned ai generated target keywords from the target keyword report!
        Wpil_TargetKeyword::delete_keyword_by_type('ai-generated-keyword');

        $response = array();
        if($cleared){
            $response = array(
                'success' => array(
                    'title' => __('Data Cleared!', 'wpil'),
                    'text'  => __('All AI Keywords have been deleted.', 'wpil'),
                )
            );
        }else{
            $response = array(
                'error' => array(
                    'title' => __('Unknown Error', 'wpil'),
                    'text'  => __('Unfortunately, there was an error while trying to clear the AI Keywords, and there may still be some stored on the site.', 'wpil'),
                )
             );
        }

        wp_send_json($response);
    }

    public static function ajax_wpil_clear_ai_embedding_calculation_v2(){
        Wpil_Base::verify_nonce('wpil_clear_ai_embedding_calculation_v2');

        if(!current_user_can('manage_options')){
            wp_send_json(array(
                'error' => array(
                    'title' => __('Permission Error', 'wpil'),
                    'text'  => __('You do not have permission to perform this action.', 'wpil'),
                )
            ));
        }

        $cleared = self::clear_ai_embedding_calculation_v2();

        if($cleared){
            wp_send_json(array(
                'success' => array(
                    'title' => __('Data Cleared!', 'wpil'),
                    'text'  => __('The V2 AI Relation calculations have been deleted.', 'wpil'),
                )
            ));
        }

        wp_send_json(array(
            'error' => array(
                'title' => __('Unknown Error', 'wpil'),
                'text'  => __('Unfortunately, there was an error while trying to clear the V2 AI Relation calculations.', 'wpil'),
            )
        ));
    }

    public static function clear_ai_embedding_calculation_v2(){
        global $wpdb;
        $table = $wpdb->prefix . 'wpil_ai_embedding_calculation_data_v2';

        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
        if($table_exists === $table){
            $result = $wpdb->query("TRUNCATE TABLE {$table}");
            if($result === false){
                return false;
            }
        }

        self::$cached_embedding_data = array();
        delete_transient('wpil_last_embedding_index_lock');

        return true;
    }

    public static function ajax_rescan_empty_ai_embeddings(){
        Wpil_Base::verify_nonce('wpil_rescan_empty_ai_embeddings');

        if(!current_user_can('manage_options')){
            wp_send_json(array(
                'error' => array(
                    'title' => __('Permission Error', 'wpil'),
                    'text'  => __('You do not have permission to perform this action.', 'wpil'),
                )
            ));
        }

        if(empty(Wpil_Settings::getOpenAIKey()) && (!Wpil_Settings::get_linkwhisper_ai_active() || empty(Wpil_Settings::get_linkwhisper_ai_token()))){
            wp_send_json(array(
                'error' => array(
                    'title' => __('AI Not Connected', 'wpil'),
                    'text'  => __('Please connect Link Whisper AI or add an OpenAI API key before rescanning empty embedding posts.', 'wpil'),
                )
            ));
        }

        $repair_state = self::get_empty_embedding_relation_repair_state();
        if(!empty($repair_state)){
            wp_send_json(self::process_empty_embedding_relation_repair_state($repair_state));
        }

        $results = self::rescan_empty_ai_embeddings();

        wp_send_json($results);
    }

    public static function get_empty_ai_embedding_count(){
        global $wpdb;
        $table = $wpdb->prefix . 'wpil_ai_embedding_data';

        return (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE `is_empty` = 1");
    }

    private static function rescan_empty_ai_embeddings(){
        global $wpdb;
        $table = $wpdb->prefix . 'wpil_ai_embedding_data';
        $cursor = self::get_empty_embedding_rescan_cursor();
        $batch_limit = (int)Wpil_Settings::get_ai_process_limit('create-post-embeddings', true);
        $batch_limit = !empty($batch_limit) ? max(1, min($batch_limit, 10)): 1;

        $rows = $wpdb->get_results($wpdb->prepare("SELECT `embed_index`, `post_id`, `post_type` FROM {$table} WHERE `is_empty` = 1 AND `embed_index` > %d ORDER BY `embed_index` ASC LIMIT %d", $cursor, $batch_limit));
        $response = array(
            'processed' => 0,
            'repaired' => 0,
            'skipped' => 0,
            'failed' => 0,
            'remaining' => self::get_empty_ai_embedding_count(),
            'relation_processing' => false,
            'complete' => false,
        );

        if(empty($rows)){
            self::clear_empty_embedding_rescan_cursor();
            $response['remaining'] = self::get_empty_ai_embedding_count();
            $response['complete'] = true;
            $response['message'] = empty($response['remaining']) ? __('No empty embedding posts were found.', 'wpil') : __('The rescan finished this pass. Some empty embedding rows could not be repaired and were left for review.', 'wpil');
            return $response;
        }

        self::$purpose = 'create-post-embeddings';
        self::$model = Wpil_Settings::getChatGPTVersion('create-post-embeddings');
        $repaired_posts = array();

        foreach($rows as $row){
            if(Wpil_Base::overTimeLimit(5, 35)){
                break;
            }

            $response['processed']++;
            self::set_empty_embedding_rescan_cursor((int)$row->embed_index);

            $post = new Wpil_Model_Post((int)$row->post_id, $row->post_type);
            $content = self::get_clean_embedding_post_content($post);

            if(empty($content)){
                $response['skipped']++;
                continue;
            }

            $embedding = self::request_single_post_embedding_for_rescan($content, $post->get_pid());
            if(empty($embedding['embedding']) || empty($embedding['model'])){
                $response['failed']++;
                continue;
            }

            $updated = $wpdb->update(
                $table,
                array(
                    'embed_data' => Wpil_Toolbox::json_compress($embedding['embedding'], true),
                    'is_empty' => 0,
                    'process_time' => time(),
                    'model_version' => $embedding['model'],
                ),
                array(
                    'embed_index' => (int)$row->embed_index,
                ),
                array('%s', '%d', '%d', '%s'),
                array('%d')
            );

            if(false === $updated){
                $response['failed']++;
                continue;
            }

            $repaired_posts[] = array(
                'post_id' => (int)$row->post_id,
                'post_type' => $row->post_type,
            );
            $response['repaired']++;
        }

        if(!empty($repaired_posts)){
            self::queue_empty_embedding_relation_repair($repaired_posts);
            $response['relation_processing'] = true;
        }

        $response['remaining'] = self::get_empty_ai_embedding_count();
        $response['complete'] = false;

        return $response;
    }

    private static function get_empty_embedding_rescan_cursor(){
        return (int)get_transient('wpil_empty_embedding_rescan_cursor');
    }

    private static function set_empty_embedding_rescan_cursor($embed_index = 0){
        set_transient('wpil_empty_embedding_rescan_cursor', (int)$embed_index, DAY_IN_SECONDS);
    }

    private static function clear_empty_embedding_rescan_cursor(){
        delete_transient('wpil_empty_embedding_rescan_cursor');
    }

    private static function get_clean_embedding_post_content($post = null, $token_limit = 7800){
        if(empty($post) || !is_a($post, 'Wpil_Model_Post')){
            return '';
        }

        $content = strip_tags($post->getContent(false), '<h1><h2><h3><h4><h5><h6><title><ul><ol><li>');
        $content = mb_ereg_replace('(([a-zA-Z\-_0-9]+="[^"]*")+?[\s]?)', '', $content);
        $content = mb_ereg_replace("&nbsp;", ' ', $content);
        $content = mb_ereg_replace("[\s]+", ' ', $content);

        return self::trim_text_to_token_limit($content, self::$model, $token_limit);
    }

    private static function request_single_post_embedding_for_rescan($content = '', $pid = ''){
        $out = array(
            'embedding' => array(),
            'model' => '',
        );

        if(empty($content)){
            return $out;
        }

        $dimensions = Wpil_Settings::get_ai_dimension_limit();

        if(self::$ai_service_connected){
            self::$query_ids = array($pid);
            $stream_filter_active = has_filter('orhanerday_openai_stream_response_data', array(__CLASS__, 'process_streamed_data'));
            if(false !== $stream_filter_active){
                remove_filter('orhanerday_openai_stream_response_data', array(__CLASS__, 'process_streamed_data'));
            }

            try{
                $results = self::call_linkwhisper_ai(array($content), '', '', array('dimensions' => $dimensions));
            }finally{
                if(false !== $stream_filter_active){
                    add_filter('orhanerday_openai_stream_response_data', array(__CLASS__, 'process_streamed_data'), 10, 3);
                }
            }

            $response = (!empty($results) && isset($results[0])) ? self::decode($results[0]) : null;
        }else{
            if(empty(self::$ai)){
                return $out;
            }

            self::$query_ids = array($pid);
            $results = self::$ai->embeddings(array(
                'message_list' => array(array(
                    'model' => self::$model,
                    'input' => $content,
                    'dimensions' => $dimensions
                )),
            ), true);
            $response = (!empty($results) && isset($results[0])) ? self::decode($results[0]) : null;
        }

        if( empty($response) ||
            !isset($response->data, $response->data[0], $response->data[0]->embedding, $response->model) ||
            empty($response->data[0]->embedding)
        ){
            return $out;
        }

        $out['embedding'] = $response->data[0]->embedding;
        $out['model'] = (string)$response->model;

        self::save_response_tokens(json_encode($response), self::$purpose, false, $pid);

        return $out;
    }

    private static function get_empty_embedding_relation_repair_state(){
        $state = get_transient('wpil_empty_embedding_relation_repair_state');
        return (!empty($state) && is_array($state)) ? $state: array();
    }

    private static function queue_empty_embedding_relation_repair($post_id = 0, $post_type = 'post'){
        global $wpdb;

        $posts = array();
        if(is_array($post_id)){
            foreach($post_id as $post){
                if(empty($post['post_id'])){
                    continue;
                }

                $posts[] = array(
                    'post_id' => (int)$post['post_id'],
                    'post_type' => (isset($post['post_type']) && $post['post_type'] === 'term') ? 'term': 'post',
                );
            }
        }else{
            $post_id = (int)$post_id;
            $post_type = ($post_type === 'term') ? 'term' : 'post';
            if(!empty($post_id)){
                $posts[] = array(
                    'post_id' => $post_id,
                    'post_type' => $post_type,
                );
            }
        }

        if(empty($posts)){
            return false;
        }

        foreach($posts as $post){
            $wpdb->delete($wpdb->prefix . 'wpil_ai_embedding_calculation_data', array('post_id' => $post['post_id'], 'post_type' => $post['post_type']), array('%d', '%s'));
            $wpdb->delete($wpdb->prefix . 'wpil_ai_embedding_calculation_data_v2', array('post_id' => $post['post_id'], 'post_type' => $post['post_type']), array('%d', '%s'));
        }

        set_transient('wpil_empty_embedding_relation_repair_state', array(
            'post_id' => $posts[0]['post_id'],
            'post_type' => $posts[0]['post_type'],
            'posts' => $posts,
            'phase' => 'source',
            'offset' => 0,
        ), DAY_IN_SECONDS);

        return true;
    }

    private static function process_empty_embedding_relation_repair_state($state = array()){
        global $wpdb;

        $post_id = isset($state['post_id']) ? (int)$state['post_id']: 0;
        $post_type = isset($state['post_type']) && $state['post_type'] === 'term' ? 'term': 'post';
        $phase = isset($state['phase']) && $state['phase'] === 'target' ? 'target': 'source';
        $offset = isset($state['offset']) ? (int)$state['offset']: 0;
        $posts = array();

        if(!empty($state['posts']) && is_array($state['posts'])){
            foreach($state['posts'] as $post){
                if(empty($post['post_id'])){
                    continue;
                }

                $posts[] = array(
                    'post_id' => (int)$post['post_id'],
                    'post_type' => (isset($post['post_type']) && $post['post_type'] === 'term') ? 'term': 'post',
                );
            }
        }elseif(!empty($post_id)){
            $posts[] = array(
                'post_id' => $post_id,
                'post_type' => $post_type,
            );
        }

        $response = array(
            'processed' => 0,
            'repaired' => 0,
            'skipped' => 0,
            'failed' => 0,
            'remaining' => self::get_empty_ai_embedding_count(),
            'relation_processing' => true,
            'complete' => false,
            'message' => '',
        );

        if(empty($posts)){
            delete_transient('wpil_empty_embedding_relation_repair_state');
            $response['complete'] = true;
            $response['relation_processing'] = false;
            $response['message'] = __('The relation repair state was empty, so the process was cleared.', 'wpil');
            return $response;
        }

        $embedding_table = $wpdb->prefix . 'wpil_ai_embedding_data';
        $sources = array();
        foreach($posts as $post){
            $source = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$embedding_table} WHERE `post_id` = %d AND `post_type` = %s AND `is_empty` = 0 LIMIT 1", $post['post_id'], $post['post_type']));
            if(empty($source) || empty($source->embed_data)){
                continue;
            }

            $source->embed_data = Wpil_Toolbox::json_decompress($source->embed_data, null, true);
            if(empty($source->embed_data) || !is_array($source->embed_data)){
                continue;
            }

            $sources[] = $source;
        }

        if(empty($sources)){
            delete_transient('wpil_empty_embedding_relation_repair_state');
            $response['complete'] = empty($response['remaining']);
            $response['relation_processing'] = false;
            $response['failed'] = 1;
            $response['message'] = __('The repaired embedding could not be loaded for relation processing.', 'wpil');
            return $response;
        }

        $page_size = (int)Wpil_Settings::get_ai_process_limit('create-post-embeddings', true);
        if(empty($page_size)){
            $page_size = 50;
        }
        $page_size = max(25, min($page_size * 10, 250));

        $batch = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$embedding_table} WHERE `is_empty` = 0 ORDER BY `post_type` ASC, `post_id` ASC, `embed_index` ASC LIMIT %d OFFSET %d", $page_size, $offset));
        if(empty($batch)){
            if($phase === 'source'){
                $state['phase'] = 'target';
                $state['offset'] = 0;
                set_transient('wpil_empty_embedding_relation_repair_state', $state, DAY_IN_SECONDS);
                $response['message'] = __('Finished rebuilding source relation data. Starting target-side relation refresh.', 'wpil');
                return $response;
            }

            delete_transient('wpil_empty_embedding_relation_repair_state');
            self::$cached_embedding_data = array();
            delete_transient('wpil_last_embedding_index_lock');
            $response['relation_processing'] = false;
            $response['complete'] = empty($response['remaining']);
            $response['message'] = empty($response['remaining']) ? __('The empty AI embedding rescan has finished.', 'wpil') : __('Finished relation repair for this post. Continuing with the next empty embedding post.', 'wpil');
            return $response;
        }

        $batch_count = count($batch);
        foreach($batch as $key => $embedding){
            $batch[$key]->embed_data = Wpil_Toolbox::json_decompress($embedding->embed_data, null, true);
            if(empty($batch[$key]->embed_data) || !is_array($batch[$key]->embed_data)){
                unset($batch[$key]);
            }
        }

        if($phase === 'source'){
            $response['processed'] = self::repair_empty_embedding_source_relation_chunk($sources, $batch);
        }else{
            $response['processed'] = self::repair_empty_embedding_target_relation_chunk($sources, $batch);
        }

        $state['offset'] = $offset + $batch_count;
        set_transient('wpil_empty_embedding_relation_repair_state', $state, DAY_IN_SECONDS);
        $response['message'] = sprintf(__('Refreshing %s relation scores. Processed %d rows in this pass.', 'wpil'), $phase, (int)$response['processed']);

        return $response;
    }

    private static function repair_empty_embedding_source_relation_chunk($source, $embeddings){
        global $wpdb;

        $v1_table = $wpdb->prefix . 'wpil_ai_embedding_calculation_data';
        $v2_table = $wpdb->prefix . 'wpil_ai_embedding_calculation_data_v2';
        $sources = is_array($source) ? $source: array($source);
        $total_processed = 0;

        foreach($sources as $source){
            if(empty($source) || empty($source->embed_data) || !is_array($source->embed_data)){
                continue;
            }

            $source_pid = $source->post_type . '_' . $source->post_id;
            $v1_row = $wpdb->get_row($wpdb->prepare("SELECT `embed_index`, `calculation`, `calc_index`, `calc_count` FROM {$v1_table} WHERE `post_id` = %d AND `post_type` = %s LIMIT 1", $source->post_id, $source->post_type));
            $calculation = (!empty($v1_row) && !empty($v1_row->calculation)) ? Wpil_Toolbox::json_decompress($v1_row->calculation, true): array();
            $calculation = (!empty($calculation) && is_array($calculation)) ? $calculation: array();
            $calc_index = !empty($v1_row->calc_index) ? (int)$v1_row->calc_index: 0;
            $calc_count = !empty($v1_row->calc_count) ? (int)$v1_row->calc_count: 0;
            $pages = array();
            $processed = 0;
            $calculated_scores = array();
            $stored_scores = array();

            foreach($embeddings as $embedding){
                if(empty($embedding->embed_data) || !is_array($embedding->embed_data)){
                    continue;
                }

                $target_pid = $embedding->post_type . '_' . $embedding->post_id;
                $target_type = $embedding->post_type;
                if(!isset($pages[$target_type])){
                    $pages[$target_type] = array(
                        'post_id' => $source->post_id,
                        'post_type' => $source->post_type,
                        'target_post_type' => $target_type,
                        'starting_id' => $embedding->post_id,
                        'ending_id' => $embedding->post_id,
                        'calculation' => array(),
                        'calc_index' => 0,
                        'calc_count' => 0,
                        'model_version' => $source->model_version,
                    );
                }

                if((int)$pages[$target_type]['starting_id'] > (int)$embedding->post_id){
                    $pages[$target_type]['starting_id'] = (int)$embedding->post_id;
                }

                if((int)$pages[$target_type]['ending_id'] < (int)$embedding->post_id){
                    $pages[$target_type]['ending_id'] = (int)$embedding->post_id;
                }

                if((int)$pages[$target_type]['calc_index'] < (int)$embedding->embed_index){
                    $pages[$target_type]['calc_index'] = (int)$embedding->embed_index;
                }

                $pages[$target_type]['calc_count'] += 1;
                if((int)$calc_index < (int)$embedding->embed_index){
                    $calc_index = (int)$embedding->embed_index;
                }

                if($source_pid === $target_pid){
                    continue;
                }

                $processed++;
                $calc_count++;
                $score = self::compare_post_embeddings(
                    (object)array('post_id' => $source->post_id, 'post_type' => $source->post_type, 'embed_data' => $source->embed_data),
                    (object)array('post_id' => $embedding->post_id, 'post_type' => $embedding->post_type, 'embed_data' => $embedding->embed_data),
                    true
                );
                $calculated_scores[$target_pid] = $score;

                if($score > 0.40){
                    $calculation[$target_pid] = $score;
                    $pages[$target_type]['calculation'][$target_pid] = $score;
                    $stored_scores[$target_pid] = $score;
                }
            }

            if(!empty($v1_row)){
                $wpdb->update(
                    $v1_table,
                    array(
                        'calculation' => Wpil_Toolbox::json_compress($calculation),
                        'calc_index' => $calc_index,
                        'calc_count' => $calc_count,
                        'process_time' => time(),
                        'model_version' => $source->model_version,
                    ),
                    array('embed_index' => (int)$v1_row->embed_index),
                    array('%s', '%d', '%d', '%d', '%s'),
                    array('%d')
                );
            }else{
                $wpdb->insert($v1_table, array(
                    'post_id' => (int)$source->post_id,
                    'post_type' => $source->post_type,
                    'data_type' => (($source->post_type === 'post') ? 1: 0),
                    'calculation' => Wpil_Toolbox::json_compress($calculation),
                    'calc_index' => $calc_index,
                    'calc_count' => $calc_count,
                    'process_time' => time(),
                    'model_version' => $source->model_version,
                ));
            }

            if(!empty($pages)){
                self::save_calculated_embedding_data_v2($pages);
            }

            $total_processed += $processed;
        }

        return $total_processed;
    }

    private static function repair_empty_embedding_target_relation_chunk($target, $embeddings){
        global $wpdb;

        $v1_table = $wpdb->prefix . 'wpil_ai_embedding_calculation_data';
        $v2_table = $wpdb->prefix . 'wpil_ai_embedding_calculation_data_v2';
        $targets = is_array($target) ? $target: array($target);
        $processed = 0;
        $calculated_scores = array();

        foreach($embeddings as $embedding){
            if(empty($embedding->embed_data) || !is_array($embedding->embed_data)){
                continue;
            }

            $source_pid = $embedding->post_type . '_' . $embedding->post_id;
            $target_scores = array();
            foreach($targets as $target){
                if(empty($target) || empty($target->embed_data) || !is_array($target->embed_data)){
                    continue;
                }

                $target_pid = $target->post_type . '_' . $target->post_id;
                if($source_pid === $target_pid){
                    continue;
                }

                $processed++;
                $score = self::compare_post_embeddings(
                    (object)array('post_id' => $embedding->post_id, 'post_type' => $embedding->post_type, 'embed_data' => $embedding->embed_data),
                    (object)array('post_id' => $target->post_id, 'post_type' => $target->post_type, 'embed_data' => $target->embed_data),
                    true
                );
                $calculated_scores[$target_pid][$source_pid] = $score;
                $target_scores[$target_pid] = $score;

                $v2_rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT `embed_index`, `calculation` FROM {$v2_table} WHERE `post_id` = %d AND `post_type` = %s AND `target_post_type` = %s AND `starting_id` <= %d AND `ending_id` >= %d",
                    $embedding->post_id,
                    $embedding->post_type,
                    $target->post_type,
                    $target->post_id,
                    $target->post_id
                ));

                if(!empty($v2_rows)){
                    foreach($v2_rows as $v2_row){
                        self::update_embedding_relation_target_score_row($v2_table, $v2_row, $target_pid, $score, false);
                    }
                }
            }

            if(!empty($target_scores)){
                self::update_embedding_relation_target_score($v1_table, $embedding->post_id, $embedding->post_type, $target_scores);
            }
        }

        return $processed;
    }

    private static function update_embedding_relation_target_score($table, $post_id, $post_type, $target_pid, $score = null){
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare("SELECT `embed_index`, `calculation` FROM {$table} WHERE `post_id` = %d AND `post_type` = %s LIMIT 1", $post_id, $post_type));
        if(empty($row)){
            return false;
        }

        return self::update_embedding_relation_target_score_row($table, $row, $target_pid, $score, true);
    }

    private static function update_embedding_relation_target_score_row($table, $row, $target_pid, $score, $update_count = true){
        global $wpdb;

        if(empty($row) || empty($row->embed_index)){
            return false;
        }

        $calculation = Wpil_Toolbox::json_decompress($row->calculation, true);
        $calculation = (!empty($calculation) && is_array($calculation)) ? $calculation: array();

        if(is_array($target_pid)){
            foreach($target_pid as $pid => $score){
                if($score > 0.40){
                    $calculation[$pid] = $score;
                }else{
                    unset($calculation[$pid]);
                }
            }
        }else{
            if($score > 0.40){
                $calculation[$target_pid] = $score;
            }else{
                unset($calculation[$target_pid]);
            }
        }

        $update = array(
            'calculation' => Wpil_Toolbox::json_compress($calculation),
            'process_time' => time(),
        );
        $formats = array('%s', '%d');

        if($update_count){
            $update['calc_count'] = count($calculation);
            $formats[] = '%d';
        }

        return $wpdb->update($table, $update, array('embed_index' => (int)$row->embed_index), $formats, array('%d'));
    }

    public static function ajax_clear_ai_linking_process_data(){
        Wpil_Base::verify_nonce('wpil_clear_ai_linking_process_data');

        if(!current_user_can('manage_options')){
            wp_send_json(array(
                'error' => array(
                    'title' => __('Permission Error', 'wpil'),
                    'text'  => __('You do not have permission to perform this action.', 'wpil'),
                )
            ));
        }

        $cleared = self::clear_ai_linking_process_data();

        if($cleared){
            wp_send_json(array(
                'success' => array(
                    'title' => __('Data Cleared!', 'wpil'),
                    'text'  => __('AI Linking process data has been cleared.', 'wpil'),
                )
            ));
        }

        wp_send_json(array(
            'error' => array(
                'title' => __('Unknown Error', 'wpil'),
                'text'  => __('Unfortunately, there was an error while trying to clear the AI Linking process data.', 'wpil'),
            )
        ));
    }

    /**
     * Clears only AI linking process artifacts used by AI linking/fix runs.
     * Does not clear broader AI datasets, saved suggestions, or unrelated site data.
     **/
    public static function clear_ai_linking_process_data(){
        global $wpdb;

        $all_ok = true;
        $relation_map_table = $wpdb->prefix . 'wpil_relation_mapping';

        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $relation_map_table));
        if($table_exists === $relation_map_table){
            $result = $wpdb->query("TRUNCATE TABLE {$relation_map_table}");
            if($result === false){
                $all_ok = false;
            }
        }

        // Clear active fix tracker and registry for dashboard Fix with AI.
        delete_option('wpil_ai_fix_registry');
        delete_transient('wpil_doing_ai_fix_process');
        $linking_process_keys = array(
            md5('one-click-setup'),
            md5('orphan-post-search'),
            md5('link-coverage-search'),
            md5('link-quality-search'),
            md5('broken-link-search'),
            md5('external-focus-search'),
            md5('custom-link-map'),
        );
        foreach($linking_process_keys as $process_key){
            self::clear_credit_tracking_task_run('linking:' . $process_key);
        }

        // Remove AI linking process options (process key + processed-id trackers).
        $option_names = $wpdb->get_col(
            "SELECT option_name
                FROM {$wpdb->options}
                WHERE option_name LIKE 'wpil_ai_linking_%'
                    OR option_name LIKE 'wpil_link_map_snapshot_%'"
        );
        if(!empty($option_names)){
            foreach($option_names as $option_name){
                if(!is_string($option_name) || $option_name === ''){
                    continue;
                }
                delete_option($option_name);
            }
        }

        // Remove review/fix transient rows for all users/process keys.
        $transient_cleanup = $wpdb->query(
            "DELETE FROM {$wpdb->options}
                WHERE option_name LIKE '_transient_wpil_review_served_%'
                    OR option_name LIKE '_transient_timeout_wpil_review_served_%'
                    OR option_name LIKE '_transient_wpil_map_replace_links_id_list_%'
                    OR option_name LIKE '_transient_timeout_wpil_map_replace_links_id_list_%'"
        );
        if($transient_cleanup === false){
            $all_ok = false;
        }

        // Clear current user's in-memory review list immediately as well.
        delete_transient('wpil_review_served_' . get_current_user_id());

        return $all_ok;
    }

    /**
     * Clears the saved queue trail for a single AI linking run so the next pass starts fresh.
     **/
    private static function reset_ai_linking_process_runtime($process_key = '', $clear_process_key = true){
        $process_key = is_string($process_key) ? sanitize_text_field($process_key) : '';
        if(empty($process_key)){
            return;
        }

        if($clear_process_key && get_option('wpil_ai_linking_process_key', '') === $process_key){
            delete_option('wpil_ai_linking_process_key');
        }

        delete_option('wpil_ai_linking_' . $process_key);
        delete_transient('wpil_review_served_' . get_current_user_id());

        Wpil_LinkMapping::delete_relation_map($process_key);
        self::clear_credit_tracking_task_run('linking:' . $process_key);
    }

    public static function ajax_wpil_dismiss_credit_notice(){
        update_option('wpil_oai_insufficient_quota_error', '0');
    }

    public static function ajax_wpil_dismiss_api_key_decoding_error(){
        update_option('wpil_open_ai_key_decoding_error', '0');
        update_option('wpil_ai_token_decoding_error', '0'); // also update the Link Whisper AI since the user _should_ have updated the wp encryption tokens needed to run the system
    }

    public static function ajax_estimate_site_processing_cost(){
        // create a list of all the posts that need to be processed
        // create a counter that will keep track of their estimated processing costs
        // create something that will loop over each one and estimate based onteh content
        // return the result to the customer

        if(isset($_POST['reset']) && !empty($_POST['reset'])){
            delete_transient('wpil_ai_content_estimate_post_ids');
            delete_transient('wpil_ai_content_estimate_post_id_count');
            delete_transient('wpil_ai_content_estimate_cost');
        }

        $mode = (isset($_POST['estimate_mode']) && $_POST['estimate_mode'] === 'link-whisper') ? 'link-whisper': 'direct';

        $cost = get_transient('wpil_ai_content_estimate_cost');
        if(empty($cost)){
            $cost = 0;
        }

        $processable = get_transient('wpil_ai_content_estimate_post_ids');
        $total = get_transient('wpil_ai_content_estimate_post_id_count');
        if(empty($processable)){
            // for the time being, assume that we're only checking for a full site scan
            // doing it per suggestion scan is just too small to warrant asking the user if he wants to confirm the purchase
            $processable = self::get_processable_post_ids();
            set_transient('wpil_ai_content_estimate_post_ids', $processable, 10 * MINUTE_IN_SECONDS);
            $total = count($processable);
            set_transient('wpil_ai_content_estimate_post_id_count', $total, 10 * MINUTE_IN_SECONDS);
        }

        // first, figure out what we're doing
        $active_processes = Wpil_Settings::get_selected_ai_batch_processes(true);

        $processes = array();
        foreach($active_processes as $process){
            if(Wpil_AI::check_batch_status_completed($process)){
                continue;
            }

            $model = Wpil_Settings::getChatGPTVersion($process);
            $processes[$model] = true;
        }

        foreach($processable as $key => $post_id){
            // exist if we're over the limit
            if(Wpil_Base::overTimeLimit(0, 10)){
                break;
            }

            $bits = explode('_', $post_id);
            unset($processable[$key]);
            if(empty($bits)){
                continue;
            }

            $post = new Wpil_Model_Post($bits[1], $bits[0]);
            $content = $post->getContent();
            foreach($processes as $model => $process){
                $cost += ($mode === 'link-whisper') ? self::estimate_processing_token_cost($content, $model): self::estimate_processing_cost($content, $model);
            }
        }

        // update the counter in case we need to go around again
        set_transient('wpil_ai_content_estimate_post_ids', $processable, 10 * MINUTE_IN_SECONDS);
        set_transient('wpil_ai_content_estimate_post_id_count', $total, 10 * MINUTE_IN_SECONDS);
        set_transient('wpil_ai_content_estimate_cost', $cost, 10 * MINUTE_IN_SECONDS);

        $response = array(
            'finished' => (empty($processable)) ? 1: 0,
            'posts_remaining' => count($processable),
            'total' => $total,
            'cost' => $cost,
            'mode' => $mode
        );

        wp_send_json($response);
    }

    public static function ajax_get_wizard_credit_estimate(){
        Wpil_Base::verify_nonce('wpil_download_ai_data');

        $ai_linking_enabled = self::is_ai_linking_enabled_request();
        $process_key = get_option('wpil_ai_linking_process_key', null);
        $refresh_credits = isset($_POST['refresh_credits']) && !empty($_POST['refresh_credits']);

        wp_send_json(array(
            'success' => array(
                'ai_credits' => self::get_available_ai_credits($refresh_credits),
                'estimated_credit_cost' => self::estimate_site_processing_credit_cost($process_key, $ai_linking_enabled)
            )
        ));
    }

    private static function is_ai_linking_enabled_request(){
        if(isset($_POST['ai_linking_enabled'])){
            return !empty($_POST['ai_linking_enabled']);
        }

        return true;
    }

    /**
     * Lets us tell when the Dashboard is asking for the lightweight setup scan.
     **/
    public static function is_dashboard_basic_scan_request(){
        return !empty($_POST['dashboard_basic_scan']);
    }

    /**
     * The Dashboard only needs the relation data, plus keyword processing if it's switched on.
     **/
    public static function get_dashboard_basic_scan_processes(){
        $processes = array('create-post-embeddings');
        $selected_processes = Wpil_Settings::get_selected_ai_batch_processes(true);

        if(in_array('keyword-detecting', $selected_processes, true)){
            $processes[] = 'keyword-detecting';
        }

        return $processes;
    }

    /**
     * The Dashboard fix gate kicks off again whenever the site drops under this point.
     **/
    public static function get_dashboard_basic_scan_threshold(){
        return 90;
    }

    /**
     * Checks if the active live AI process belongs to the Dashboard setup gate.
     **/
    public static function is_dashboard_basic_scan_running(){
        if(get_transient('wpil_dashboard_basic_scan_cancelled')){
            return false;
        }

        $running = get_transient('wpil_doing_ai_data_download');
        $mode = get_transient('wpil_doing_ai_data_download_mode');

        if(empty($running) || !in_array($mode, array('dashboard-basic-scan', 'default'), true)){
            return false;
        }

        return (((int)$running + (MINUTE_IN_SECONDS * 5)) > time());
    }

    /**
     * Pulls the current Dashboard setup gate state together in one tidy little packet.
     **/
    public static function get_dashboard_basic_scan_status($ignore_cache = false){
        $threshold = self::get_dashboard_basic_scan_threshold();
        $selected_processes = Wpil_Settings::get_selected_ai_batch_processes(true);
        $stats = self::get_completed_post_stats(true);
        $total = self::get_total_processable_posts();
        $relation_embedding_processed = isset($stats['create-post-embeddings']) ? (int) $stats['create-post-embeddings'] : 0;
        $relation_calculation_processed = isset($stats['calculated-post-embeddings']) ? (int) $stats['calculated-post-embeddings'] : 0;
        $keyword_detecting_processed = isset($stats['keyword-detecting']) ? (int) $stats['keyword-detecting'] : 0;
        $keyword_assigning_processed = isset($stats['keyword-assigning']) ? (int) $stats['keyword-assigning'] : 0;
        $keyword_enabled = in_array('keyword-detecting', $selected_processes, true);
        $relation_embedding_percent = ($total > 0) ? self::get_batch_status_completion_percent('create-post-embeddings', $ignore_cache): 100;
        $relation_calculation_percent = ($total > 0) ? self::get_batch_status_completion_percent('calculated-post-embeddings', $ignore_cache): 100;
        $relation_percent = (int) min(100, floor(($relation_embedding_percent / 2) + ($relation_calculation_percent / 2)));
        $keyword_detecting_percent = ($total > 0) ? self::get_batch_status_completion_percent('keyword-detecting', $ignore_cache): 100;
        $keyword_assigning_percent = ($total > 0) ? self::get_batch_status_completion_percent('keyword-assigning', $ignore_cache): 100;
        $keyword_percent = (int) min(100, floor(($keyword_detecting_percent / 2) + ($keyword_assigning_percent / 2)));
        // Count the work that's left so one lonely post doesn't wave the Dashboard scan flag.
        $minimum_missing_posts = ($total > 0) ? max(2, (int) ceil($total * ((100 - $threshold) / 100))) : 0;
        $relation_missing = max(0, ($total - $relation_embedding_processed));
        $keyword_missing = max(
            max(0, ($total - $keyword_detecting_processed)),
            max(0, ($total - $keyword_assigning_processed))
        );
        $relation_complete = ($total < 1 || $relation_missing < $minimum_missing_posts);
        $keyword_complete = (!$keyword_enabled || $total < 1 || $keyword_missing < $minimum_missing_posts);
        $ai_configured = Wpil_Settings::has_ai_enabled();
        $current_process = get_transient('wpil_dashboard_basic_scan_process_text');
        $basic_scan_complete = ($ai_configured && $relation_complete && $keyword_complete);
        $basic_scan_running = self::is_dashboard_basic_scan_running();

        return array(
            'ai_configured' => $ai_configured,
            'basic_scan_complete' => $basic_scan_complete,
            'basic_scan_running' => $basic_scan_running,
            'basic_scan_threshold' => $threshold,
            'current_process' => (!empty($current_process) ? $current_process : __('Preparing basic AI scan...', 'wpil')),
            'relation_percent' => $relation_percent,
            'relation_processed' => $relation_calculation_processed,
            'relation_embedding_percent' => $relation_embedding_percent,
            'relation_embedding_processed' => $relation_embedding_processed,
            'relation_calculation_percent' => $relation_calculation_percent,
            'relation_calculation_processed' => $relation_calculation_processed,
            'relation_total' => $total,
            'relation_complete' => $relation_complete,
            'keyword_enabled' => $keyword_enabled,
            'keyword_percent' => ($keyword_enabled ? $keyword_percent: 100),
            'keyword_processed' => $keyword_assigning_processed,
            'keyword_detecting_percent' => $keyword_detecting_percent,
            'keyword_detecting_processed' => $keyword_detecting_processed,
            'keyword_assigning_percent' => $keyword_assigning_percent,
            'keyword_assigning_processed' => $keyword_assigning_processed,
            'keyword_total' => $total,
            'keyword_complete' => $keyword_complete,
            'estimated_credit_cost' => self::estimate_dashboard_basic_scan_credit_cost(),
        );
    }

    /**
     * Estimates the credits needed for the Dashboard's basic scan.
     **/
    public static function estimate_dashboard_basic_scan_credit_cost(){
        $estimate = 0;
        $selected_processes = self::get_dashboard_basic_scan_processes();
        $credit_processes = array('create-post-embeddings', 'keyword-detecting', 'product-detecting', 'post-summarizing');
        $total_posts = self::get_total_processable_posts();
        $processed_posts = self::get_completed_post_stats(true);

        foreach($selected_processes as $process){
            if(!in_array($process, $credit_processes, true)){
                continue;
            }

            if(isset($processed_posts[$process])){
                $estimate += max(0, ($total_posts - (int) $processed_posts[$process]));
            }else{
                $estimate += $total_posts;
            }
        }

        return $estimate;
    }

    public static function add_batch_cron_interval($schedules){
        if(!isset($schedules['hourly'])){
            $schedules['hourly'] = array(
                'interval' => 60 * 60,
                'display' => __('Hourly', 'wpil')
            );
        }
        return $schedules;
    }

    /**
     * 
     **/
    public static function schedule_batch_process(){
        if(!empty(Wpil_Settings::get_ai_batch_processing_active()) && !empty(Wpil_Settings::get_selected_ai_batch_processes())){
            if(!wp_get_schedule('wpil_ai_batch_process_cron')){
                wp_schedule_event(time(), 'hourly', 'wpil_ai_batch_process_cron');
            }
        }elseif(wp_get_schedule('wpil_ai_batch_process_cron')){
            self::clear_batch_process_cron();
        }
    }

    public static function clear_batch_process_cron(){
        $timestamp = wp_next_scheduled('wpil_ai_batch_process_cron');
        wp_unschedule_event($timestamp, 'wpil_ai_batch_process_cron');
    }

    /**
     * Runs and coordinates the the cron-based batch processes.
     **/
    public static function perform_cron_batch_process(){
        // don't run the cron task if there's a live download in process
        $live_download = get_transient('wpil_doing_ai_data_download');

        if(!empty($live_download) && ((int)$live_download + (MINUTE_IN_SECONDS * 5)) > time()){
            return;
        }

        // set a flag so that we know that we're downloading data
        set_transient('wpil_doing_ai_data_download', time(), MINUTE_IN_SECONDS * 3);

        $selected_processes = Wpil_Settings::get_selected_ai_batch_processes(true);

        // if no batches are selected
        if(empty($selected_processes)){
            // exit
            return;
        }

        // if we're running the AI service and there are no AI credits available
        if(Wpil_Settings::get_linkwhisper_ai_active() && empty(self::get_available_ai_credits(true))){ // TODO: handle the edge case where a customer will be out of AI credits, but there is AI relation & keyword data taht we can process
            return;
        }

        // if we could do all that in less than 20 seconds
        if(!Wpil_Base::overTimeLimit(5, 20)){

            // set the batch size limit
            self::$batch_limit = 50000;

            // queue up the possible batches
            if(in_array('create-post-embeddings', $selected_processes)){
                self::create_site_embeddings();
            }

            // if that took over 20 seconds
            if(Wpil_Base::overTimeLimit(5, 20)){
                // exist
                return;
            }

            if(
                in_array('post-summarizing', $selected_processes) || 
                in_array('product-detecting', $selected_processes) || 
                in_array('keyword-detecting', $selected_processes))
            {
                self::analyze_site_posts();
            }
        }

        // if there are no embeddings currently being processed and we still have time
        if(in_array('create-post-embeddings', $selected_processes) && !Wpil_Base::overTimeLimit(5, 35) && self::has_completed_post_embeddings() && !self::has_completed_post_embedding_calculations()){
            self::stepped_calculate_post_embeddings();
            // clear the old AI Sitemap
            Wpil_Sitemap::delete_sitemap(false, 'ai_sitemap');
        }

        // if we've completed the embedding calculations and we don't have the sitemap generated yet
        if(self::has_completed_post_embedding_calculations() && !Wpil_Sitemap::has_sitemap('ai_sitemap')){
            // generate it now
            $relatedness = Wpil_AI::calculate_relatedness_sitemap();
            Wpil_Sitemap::save_sitemap($relatedness, 'ai_sitemap', 'AI Sitemap');
        }

        // if the embeddingsd are complete and no other factors concern us
        if(self::has_completed_post_embedding_calculations()){
            // clear the embedding lock
            self::set_last_embedding_id_lock();
        }

        if(!Wpil_Base::overTimeLimit(5, 35)){
            self::do_post_save_finishing();

            if(!Wpil_Sitemap::has_sitemap('ai_product_sitemap') && self::check_batch_status_completed(3, true)){
                $products = Wpil_AI::calculate_product_sitemap();
                if(!empty($products)){
                    Wpil_Sitemap::save_sitemap($products, 'ai_product_sitemap', 'AI-Detected Product Sitemap');
                }
            }
        }
    }

    public static function get_available_models(){
        $supported_models = array(
            'gpt-5.4-mini' => 'GPT-5.4 Mini',
            'gpt-5.4-nano' => 'GPT-5.4 Nano',
            'gpt-5.1' => 'GPT-5.1',
            'gpt-5' => 'GPT-5',
            'gpt-5-mini' => 'GPT-5 Mini',
            'gpt-5-nano' => 'GPT-5 Nano',
            'gpt-4.1' => 'GPT-4.1',
            'gpt-4.1-mini' => 'GPT-4.1 Mini',
            'gpt-4.1-nano' => 'GPT-4.1 Nano',
            'gpt-4o' => 'GPT-4o',
            'gpt-4o-mini' => 'GPT-4o Mini'
        );
        return $supported_models;
        // TODO: create option to pull models from api
    }

    public static function prepare_table(){
        global $wpdb;

        $ai_post_data           = $wpdb->prefix . "wpil_ai_post_data";
        $ai_product_data        = $wpdb->prefix . "wpil_ai_product_data";
        $ai_keyword_data        = $wpdb->prefix . "wpil_ai_keyword_data";
        $ai_token_table         = $wpdb->prefix . "wpil_ai_token_use_data";
        $embd_data_table        = $wpdb->prefix . "wpil_ai_embedding_data";
        $embd_calc_table        = $wpdb->prefix . "wpil_ai_embedding_calculation_data";
        $embd_calc_v2_table     = $wpdb->prefix . "wpil_ai_embedding_calculation_data_v2";
        $embd_phrase_table      = $wpdb->prefix . "wpil_ai_embedding_phrase_data";
        $embd_phrase_calc_table = $wpdb->prefix . "wpil_ai_embedding_phrase_calculation_data";
        $ai_sggstd_anchor_table = $wpdb->prefix . "wpil_ai_suggested_anchors";
        $ai_anchor_sntnce_table = $wpdb->prefix . "wpil_ai_processed_sentences";
        $ai_ignore_anchor_table = $wpdb->prefix . "wpil_ai_ignored_anchors"; // TODO: Check to see what our size && speed effect are while using the ai_phrase table for keeping track of ignored suggestions. If we need a separate lookup index, build this out.
        $batch_log_table        = $wpdb->prefix . "wpil_ai_batch_log";
        $error_log_table        = $wpdb->prefix . "wpil_ai_error_log";
        $system_error_log_table = $wpdb->prefix . "wpil_ai_system_error_log";
        $completed_log_table    = $wpdb->prefix . "wpil_ai_completed_batch_log";
        $ai_linking_table       = $wpdb->prefix . "wpil_ai_linking";

        // if the AI post data table doesn't exist
        $ai_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$ai_post_data}'");
        if(empty($ai_tbl_exists)){
            $ai_post_data_table_query = "CREATE TABLE IF NOT EXISTS {$ai_post_data} (
                                            ai_index bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                            post_id bigint(20) unsigned NOT NULL,
                                            post_type varchar(8),
                                            data_type tinyint(1) DEFAULT 1,
                                            summary longtext,
                                            process_time bigint(20),
                                            model_version varchar(168),
                                            PRIMARY KEY (ai_index),
                                            INDEX (post_id),
                                            INDEX (post_type)
                                        )";
                /**
                 * id === table index
                 * post_id === post|term id
                 * post_type === data type, 'post'|'term'
                 * data_type === boolint 'post' => 1|'term' => 0
                 * summary === AI summary describing the post|term
                 * process_time === timestamp of last process
                 * model_version === the AI model used to process the data
                 */

            // create DB table if it doesn't exist
            require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($ai_post_data_table_query);
        }

        // if the AI product data table doesn't exist
        $ai_prdct_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$ai_product_data}'");
        if(empty($ai_prdct_tbl_exists)){
            $ai_product_data_table_query = "CREATE TABLE IF NOT EXISTS {$ai_product_data} (
                                            ai_product_index bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                            post_id bigint(20) unsigned NOT NULL,
                                            post_type varchar(8),
                                            data_type tinyint(1) DEFAULT 1,
                                            products longtext,
                                            product_count int DEFAULT 0,
                                            process_time bigint(20),
                                            model_version varchar(168),
                                            PRIMARY KEY (ai_product_index),
                                            INDEX (post_id),
                                            INDEX (post_type)
                                        )";
                /**
                 * id === table index
                 * post_id === post|term id
                 * post_type === data type, 'post'|'term'
                 * data_type === boolint 'post' => 1|'term' => 0
                 * products === AI identified products within the post|term
                 * process_time === timestamp of last process
                 * model_version === the AI model used to process the data
                 */

            // create DB table if it doesn't exist
            require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($ai_product_data_table_query);
        }

        // if the AI keyword data table doesn't exist
        $ai_kwrd_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$ai_keyword_data}'");
        if(empty($ai_kwrd_tbl_exists)){
            $ai_keyword_data_table_query = "CREATE TABLE IF NOT EXISTS {$ai_keyword_data} (
                                            ai_keyword_index bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                            post_id bigint(20) unsigned NOT NULL,
                                            post_type varchar(8),
                                            data_type tinyint(1) DEFAULT 1,
                                            keywords longtext,
                                            keyword_count int DEFAULT 0,
                                            keywords_loaded tinyint(1) default 0,
                                            process_time bigint(20),
                                            model_version varchar(168),
                                            PRIMARY KEY (ai_keyword_index),
                                            INDEX (post_id),
                                            INDEX (post_type)
                                        )";
                /**
                 * id === table index
                 * post_id === post|term id
                 * post_type === data type, 'post'|'term'
                 * data_type === boolint 'post' => 1|'term' => 0
                 * keywords === AI identified products within the post|term
                 * process_time === timestamp of last process
                 * model_version === the AI model used to process the data
                 */

            // create DB table if it doesn't exist
            require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($ai_keyword_data_table_query);
        }

        // if the AI token data table doesn't exist
        $ai_tkn_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$ai_token_table}'");
        if(empty($ai_tkn_tbl_exists)){
            $ai_token_data_table_query = "CREATE TABLE IF NOT EXISTS {$ai_token_table} (
                                            token_index bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                            query_id varchar(128) DEFAULT NULL,
                                            transaction_type varchar(24) NOT NULL DEFAULT 'usage',
                                            transaction_ref varchar(128) DEFAULT NULL,
                                            transaction_note varchar(64) DEFAULT NULL,
                                            model_version varchar(168),
                                            batch_processed tinyint(1) DEFAULT 0,
                                            input_tokens int(10) unsigned NOT NULL DEFAULT 0,
                                            output_tokens int(10) unsigned NOT NULL DEFAULT 0,
                                            cached_prompt_tokens int(10) unsigned NOT NULL DEFAULT 0,
                                            reasoning_tokens int(10) unsigned NOT NULL DEFAULT 0,
                                            total_tokens int(10) unsigned NOT NULL DEFAULT 0,
                                            credits_used decimal(10,4) unsigned NOT NULL DEFAULT 0.0000,
                                            credits_added decimal(10,4) unsigned NOT NULL DEFAULT 0.0000,
                                            process_used int(10) unsigned NOT NULL DEFAULT 0,
                                            process_key varchar(64) NOT NULL DEFAULT '',
                                            process_id int(10) unsigned NOT NULL DEFAULT 0,
                                            process_time bigint(20),
                                            PRIMARY KEY (token_index),
                                            INDEX (query_id),
                                            INDEX (transaction_type),
                                            INDEX (transaction_ref),
                                            INDEX process_key_id_time (process_key, process_id, process_time),
                                            UNIQUE KEY credit_transaction_ref (transaction_type, transaction_ref)
                                        )";

            // create DB table if it doesn't exist
            require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($ai_token_data_table_query);
        }

        // if the AI embedding data table doesn't exist
        $emdb_data_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$embd_data_table}'");
        if(empty($emdb_data_tbl_exists)){
            $embd_data_table_query = "CREATE TABLE IF NOT EXISTS {$embd_data_table} (
                                            embed_index bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                            post_id bigint(20) unsigned NOT NULL,
                                            post_type varchar(8),
                                            data_type tinyint(1) DEFAULT 1,
                                            embed_data longtext,
                                            is_empty TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                                            process_time bigint(20),
                                            model_version varchar(168),
                                            PRIMARY KEY (embed_index),
                                            INDEX (post_id),
                                            INDEX (post_type)
                                        )";
                /**
                 * id === table index
                 * post_id === post|term id
                 * post_type === data type, 'post'|'term'
                 * data_type === boolint 'post' => 1|'term' => 0
                 * summary === AI summary describing the post|term
                 * products === AI identified products within the post|term
                 * process_time === timestamp of last process
                 * model_version === the AI model used to process the data
                 */

            // create DB table if it doesn't exist
            require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($embd_data_table_query);
        }

        // if the embedding calculation data table doesn't exist
        $embd_calc_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$embd_calc_table}'");
        if(empty($embd_calc_tbl_exists)){
            $embd_calc_table_query = "CREATE TABLE IF NOT EXISTS {$embd_calc_table} (
                                            embed_index bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                            post_id bigint(20) unsigned NOT NULL,
                                            post_type varchar(8),
                                            data_type tinyint(1) DEFAULT 1,
                                            calculation longtext,
                                            calc_index bigint(20) unsigned NOT NULL DEFAULT 0,
                                            calc_count bigint(20) unsigned NOT NULL DEFAULT 0,
                                            process_time bigint(20),
                                            model_version varchar(168),
                                            PRIMARY KEY (embed_index),
                                            INDEX (post_id),
                                            INDEX (post_type),
                                            INDEX (calc_index)
                                        )";
                /**
                 * data_type === boolint 'post' => 1|'term' => 0
                 */

            // create DB table if it doesn't exist
            require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($embd_calc_table_query);
        }

        // if the v2 embedding calculation data table doesn't exist
        $embd_calc_v2_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$embd_calc_v2_table}'");
        if(empty($embd_calc_v2_tbl_exists)){
            $embd_calc_v2_table_query = "CREATE TABLE IF NOT EXISTS {$embd_calc_v2_table} (
                                            embed_index bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                            post_id bigint(20) unsigned NOT NULL,
                                            post_type varchar(8),
                                            data_type tinyint(1) DEFAULT 1,
                                            target_post_type varchar(8),
                                            starting_id bigint(20) unsigned NOT NULL DEFAULT 0,
                                            ending_id bigint(20) unsigned NOT NULL DEFAULT 0,
                                            calculation longtext,
                                            calc_index bigint(20) unsigned NOT NULL DEFAULT 0,
                                            calc_count bigint(20) unsigned NOT NULL DEFAULT 0,
                                            process_time bigint(20),
                                            model_version varchar(168),
                                            PRIMARY KEY (embed_index),
                                            INDEX (post_id),
                                            INDEX (post_type),
                                            INDEX (target_post_type),
                                            INDEX (starting_id),
                                            INDEX (ending_id),
                                            INDEX (calc_index),
                                            INDEX target_range (target_post_type, starting_id, ending_id),
                                            INDEX source_lookup (post_id, post_type)
                                        )";
                /**
                 * V2 stores one source post's relation data in target ID pages.
                 * That keeps us from opening one giant blob just to find one post.
                 */

            // create DB table if it doesn't exist
            require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($embd_calc_v2_table_query);
        }
        
        // if the AI phrase embedding data table doesn't exist
        $emdb_phrs_data_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$embd_phrase_table}'");
        if(empty($emdb_phrs_data_tbl_exists)){
            $embd_phrs_data_table_query = "CREATE TABLE IF NOT EXISTS {$embd_phrase_table} (
                                            embed_index bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                            post_id bigint(20) unsigned NOT NULL,
                                            post_type varchar(8),
                                            data_type tinyint(1) DEFAULT 1,
                                            post_phrase_id varchar(168),
                                            embed_data longtext,
                                            no_data tinyint(1) DEFAULT 0,
                                            process_time bigint(20),
                                            model_version varchar(168),
                                            dimension_count int(8) DEFAULT 0,
                                            PRIMARY KEY (embed_index),
                                            INDEX (post_id),
                                            INDEX (post_type)
                                        )";

            // create DB table if it doesn't exist
            require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($embd_phrs_data_table_query);
        }

        // if the AI phrase embedding calculating result data table doesn't exist
        $emdb_phrs_calc_data_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$embd_phrase_calc_table}'");
        if(empty($emdb_phrs_calc_data_tbl_exists)){
            $emdb_phrs_calc_data_table_query = "CREATE TABLE IF NOT EXISTS {$embd_phrase_calc_table} (
                                            embed_index bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                            post_id bigint(20) unsigned NOT NULL,
                                            post_type varchar(8),
                                            data_type tinyint(1) DEFAULT 1,
                                            post_phrase_id varchar(168),
                                            calculation longtext,
                                            calc_index longtext,
                                            calc_count bigint(20) unsigned NOT NULL DEFAULT 0,
                                            no_data tinyint(1) DEFAULT 0,
                                            process_time bigint(20),
                                            model_version varchar(168),
                                            dimension_count int(8) DEFAULT 0,
                                            PRIMARY KEY (embed_index),
                                            INDEX (post_id),
                                            INDEX (post_type)
                                        )";

            // create DB table if it doesn't exist
            require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($emdb_phrs_calc_data_table_query);
        }

        // if the AI suggested anchor data table doesn't exist
        $sggstd_nchr_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$ai_sggstd_anchor_table}'");
        if(empty($sggstd_nchr_tbl_exists)){
            $sggstd_nchr_table_query = "CREATE TABLE IF NOT EXISTS {$ai_sggstd_anchor_table} (
                                            suggestion_index bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                            post_id bigint(20) unsigned NOT NULL,
                                            post_type varchar(8),
                                            data_type tinyint(1) DEFAULT 1,
                                            sentence_post_id varchar(168),
                                            sentence_id varchar(168),
                                            suggestion_words text,
                                            notes text,
                                            target_id bigint(20) unsigned NOT NULL,
                                            target_type varchar(8),
                                            target_data_type tinyint(1) DEFAULT 1,
                                            link_score int(4),
                                            ignore_suggestion tinyint(1) DEFAULT 0,
                                            process_time bigint(20),
                                            model_version varchar(168),
                                            PRIMARY KEY (suggestion_index),
                                            INDEX (post_id),
                                            INDEX (post_type),
                                            INDEX (sentence_post_id)
                                        )";

            // create DB table if it doesn't exist
            require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sggstd_nchr_table_query);
        }

        // if the table that keeps track if we've scanned post sentences or not doesn't exist
        $nchr_sntnc_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$ai_anchor_sntnce_table}'");
        if(empty($nchr_sntnc_tbl_exists)){
            $ai_anchor_sntnce_table_query = "CREATE TABLE IF NOT EXISTS {$ai_anchor_sntnce_table} (
                                            sentence_index bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                            post_id bigint(20) unsigned NOT NULL,
                                            post_type varchar(8),
                                            data_type tinyint(1) DEFAULT 1,
                                            has_link tinyint(1) DEFAULT 0,
                                            sentence_id varchar(168),
                                            process_time bigint(20),
                                            PRIMARY KEY (sentence_index),
                                            INDEX (post_id),
                                            INDEX (post_type),
                                            INDEX (sentence_id)
                                        )";

            // create DB table if it doesn't exist
            require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($ai_anchor_sntnce_table_query);
        }

        // if the AI post data table doesn't exist
        $batch_lg_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$batch_log_table}'");
        if(empty($batch_lg_tbl_exists)){
            $batch_log_table_query = "CREATE TABLE IF NOT EXISTS {$batch_log_table} (
                                            log_index bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                            batch_id varchar(128),
                                            batch_data longtext,
                                            process_id tinyint(1) DEFAULT 0,
                                            process_time bigint(20) UNSIGNED,
                                            check_time bigint(20) UNSIGNED,
                                            PRIMARY KEY (log_index),
                                            INDEX (batch_id)
                                        )";

            // create DB table if it doesn't exist
            require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($batch_log_table_query);
        }

        // if the AI post data table doesn't exist
        $rrr_lg_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$error_log_table}'");
        if(empty($rrr_lg_tbl_exists)){
            $rrr_lg_tbl_query = "CREATE TABLE IF NOT EXISTS {$error_log_table} (
                                            log_index bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                            post_id bigint(20) unsigned NOT NULL,
                                            post_type varchar(8),
                                            data_type tinyint(1) DEFAULT 1,
                                            batch_id varchar(128),
                                            batch_data longtext,
                                            message_text longtext,
                                            process_time bigint(20) UNSIGNED,
                                            PRIMARY KEY (log_index),
                                            INDEX (post_id),
                                            INDEX (post_type),
                                            INDEX (batch_id)
                                        )";

            // create DB table if it doesn't exist
            require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($rrr_lg_tbl_query);
        }

        // if the AI post data table doesn't exist
        $sstm_rrr_lg_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$system_error_log_table}'");
        if(empty($sstm_rrr_lg_tbl_exists)){
            $sstm_rrr_lg_tbl_query = "CREATE TABLE IF NOT EXISTS {$system_error_log_table} (
                                            log_index bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                            message_text longtext,
                                            log_data longtext,
                                            process_time bigint(20) UNSIGNED,
                                            PRIMARY KEY (log_index)
                                        )";

            // create DB table if it doesn't exist
            require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sstm_rrr_lg_tbl_query);
        }

        // if the AI post data table doesn't exist
        $cmpltd_lg_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$completed_log_table}'");
        if(empty($cmpltd_lg_tbl_exists)){
            $cmpltd_lg_tbl_query = "CREATE TABLE IF NOT EXISTS {$completed_log_table} (
                                            log_index bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                            batch_id varchar(128),
                                            batch_status varchar(128),
                                            process_time bigint(20) UNSIGNED,
                                            PRIMARY KEY (log_index)
                                        )";

            // create DB table if it doesn't exist
            require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($cmpltd_lg_tbl_query);
        }

        // if the AI post data table doesn't exist
        $ai_lnk_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$ai_linking_table}'");
        if(empty($ai_lnk_tbl_exists)){
            $ai_linking_table_table_query = "CREATE TABLE IF NOT EXISTS {$ai_linking_table} (
                                            ai_index bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                            post_id bigint(20) unsigned NOT NULL,
                                            post_type varchar(8),
                                            data_type tinyint(1) DEFAULT 1,
                                            target_id bigint(20) unsigned NOT NULL,
                                            target_type varchar(8),
                                            target_data_type tinyint(1) DEFAULT 1,
                                            sentence_text text DEFAULT NULL,
                                            sentence_id varchar(168) DEFAULT '',
                                            sentence_with_anchor_text text DEFAULT NULL,
                                            ai_relation_score double UNSIGNED NOT NULL DEFAULT 0,
                                            ignored tinyint(1) DEFAULT 0,
                                            inserted tinyint(1) DEFAULT 0,
                                            process_key varchar(64) DEFAULT '',
                                            process_time bigint(20),
                                            model_version varchar(168),
                                            PRIMARY KEY (ai_index),
                                            INDEX (post_id),
                                            INDEX (post_type),
                                            INDEX (sentence_id),
                                            INDEX (process_key)
                                        )";
                /**
                 * id === table index
                 * post_id === post|term id
                 * post_type === data type, 'post'|'term'
                 * data_type === boolint 'post' => 1|'term' => 0
                 * summary === AI summary describing the post|term
                 * process_time === timestamp of last process
                 * model_version === the AI model used to process the data
                 */

            // create DB table if it doesn't exist
            require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($ai_linking_table_table_query);
        }
    }

    /**
     * 
     **/
    public static function clear_ai_data($params = []){
        global $wpdb;

        // NOTE: some tables are intentionally not being cleared until we get past teh initial rollout... We might need things like log data
        $tables = array();
//      $wpdb->prefix . "wpil_ai_token_use_data",
//      $wpdb->prefix . "wpil_ai_error_log",
//      $wpdb->prefix . "wpil_ai_system_error_log"

        if(empty($params)){
            $tables = array_merge($tables, [
                $wpdb->prefix . "wpil_ai_batch_log"
            ]);
        }

        if(empty($params) || in_array('post_summary', $params)){
            $tables = array_merge($tables, [
                $wpdb->prefix . "wpil_ai_post_data"
            ]);
        }

        if(empty($params) || in_array('product_analysis', $params)){
            $tables = array_merge($tables, [
                $wpdb->prefix . "wpil_ai_product_data"
            ]);
        }

        if(empty($params) || in_array('keyword_analysis', $params)){
            $tables = array_merge($tables, [
                $wpdb->prefix . "wpil_ai_keyword_data"
            ]);
        }

        if(empty($params) || in_array('relation_analysis', $params)){
            $tables = array_merge($tables, [
                $wpdb->prefix . "wpil_ai_embedding_data",
                $wpdb->prefix . "wpil_ai_embedding_calculation_data",
                $wpdb->prefix . "wpil_ai_embedding_calculation_data_v2",
                $wpdb->prefix . "wpil_ai_embedding_phrase_data",
                $wpdb->prefix . "wpil_ai_embedding_phrase_calculation_data",
                $wpdb->prefix . "wpil_ai_suggested_anchors",
                $wpdb->prefix . "wpil_ai_processed_sentences"
            ]);
        }

        foreach($tables as $table){
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
            if($table_exists === $table){
                $wpdb->query("TRUNCATE TABLE {$table}");
            }
        }

        if(empty($params) || in_array('keyword_analysis', $params)){
            // clear any AI keywords
            $target_keywords_table = $wpdb->prefix . "wpil_target_keyword_data";
            $wpdb->delete($target_keywords_table, array('keyword_type' => 'ai-generated-keyword'));
        }

        if(empty($params) || in_array('product_analysis', $params)){
            $product_sitemap = Wpil_Sitemap::get_sitemap_list('ai_product_sitemap');
            if(!empty($product_sitemap) && isset($product_sitemap[0], $product_sitemap[0]->sitemap_id)){
                Wpil_Sitemap::delete_sitemap($product_sitemap[0]->sitemap_id, $product_sitemap[0]->sitemap_type);
            }
        }

        if(empty($params) || in_array('relation_analysis', $params)){
            // clear any AI created sitemaps
            $ai_sitemap = Wpil_Sitemap::get_sitemap_list('ai_sitemap');
            if(!empty($ai_sitemap) && isset($ai_sitemap[0], $ai_sitemap[0]->sitemap_id)){
                Wpil_Sitemap::delete_sitemap($ai_sitemap[0]->sitemap_id, $ai_sitemap[0]->sitemap_type);
            }
        }

        // for the time being, we'll just assume that everything worked.
        return true;
    }

    /**
     * Gets the ids of all logged batches
     **/
    public static function get_batch_log_ids(){
        global $wpdb;
        $table = $wpdb->prefix . 'wpil_ai_batch_log';

        return $wpdb->get_col("SELECT `batch_id` FROM {$table} ORDER BY `check_time` DESC");
    }

    /**
     * Gets the ids of all logged batches
     **/
    public static function get_next_process_batch_log_id(){
        global $wpdb;
        $table = $wpdb->prefix . 'wpil_ai_batch_log';

        return $wpdb->get_col("SELECT `batch_id` FROM {$table} ORDER BY `check_time` DESC LIMIT 1");
    }

    public static function get_batch_log_data($batch_id = '', $process = 0, $all = false){
        global $wpdb;
        $table = $wpdb->prefix . 'wpil_ai_batch_log';

        if(empty($batch_id) && empty($process) && empty($all)){
            return false;
        }

        if(!empty($batch_id)){
            return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE `batch_id` = %s", $batch_id));
        }else{
            if(!empty($all)){
                return $wpdb->get_results("SELECT * FROM {$table}");
            }else{
                if(is_string($process)){
                    $process = self::get_process_code_from_name($process);
                }
                return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE `process_id` = %d", $process));
            }
        }
    }

    public static function save_batch_log_data($batch_id = '', $data = '', $process = 0){
        global $wpdb;
        $table = $wpdb->prefix . 'wpil_ai_batch_log';

        if(empty($batch_id) || !empty(self::get_batch_log_data($batch_id))){
            return false;
        }

        if(!is_string($data)){
            $data = Wpil_Toolbox::json_compress($data);
        }

        if(is_string($process)){
            $process = self::get_process_code_from_name($process);
        }

        $insert = $wpdb->insert($table, [
            'batch_id' => $batch_id,
            'batch_data' => $data,
            'process_id' => $process,
            'process_time' => time(),
        ]);

        return (!empty($insert)) ? $wpdb->insert_id: false;
    }

    /**
     * 
     **/
    public static function save_completed_batch_log_entry($batch_id, $batch_status = ''){
        global $wpdb;
        $completed_log_table = $wpdb->prefix . "wpil_ai_completed_batch_log";

        if(empty($batch_id) || empty($batch_status)){
            return false;
        }
    
        // if this batch hasn't already been 
        $logged = self::get_completed_batch_log_entries($batch_id);
        if(empty($logged)){
            $wpdb->insert(
                $completed_log_table, 
                array(  
                    'batch_id' => $batch_id, 
                    'batch_status' => $batch_status, 
                    'process_time' => time()
                ), 
                array('%s', '%s', '%d')
            );
        }
    
        // Return the ID of the inserted row
        return (!empty($logged)) ? $logged->log_index: $wpdb->insert_id;
    }

    /**
     * Cleans up old batch log entries so they don't pile up in the database
     **/
    public static function delete_old_completed_batch_log_entries(){
        global $wpdb;
        $completed_log_table = $wpdb->prefix . "wpil_ai_completed_batch_log";
    
        // Calculate the timestamp for 2 weeks ago
        $two_weeks_ago = time() - (14 * DAY_IN_SECONDS);
    
        $wpdb->query($wpdb->prepare("DELETE FROM {$completed_log_table} WHERE process_time < %d", $two_weeks_ago));
    }

    /**
     * 
     **/
    public static function get_completed_batch_log_entries($batch_id = null){
        global $wpdb;
        $completed_log_table = $wpdb->prefix . "wpil_ai_completed_batch_log";

        if($batch_id !== null){
            if(empty(self::$complete_log_cache)){
                $logs = self::get_completed_batch_log_entries();
                if(!empty($logs)){
                    foreach($logs as $log){
                        if(!isset(self::$complete_log_cache[$log->batch_id])){
                            self::$complete_log_cache[$log->batch_id] = $log;
                        }
                    }
                }
            }

            // Retrieve a specific entry by batch id
            $result = isset(self::$complete_log_cache[$batch_id]) ? self::$complete_log_cache[$batch_id]: array();
        }else{
            // Retrieve all entries sorted by process_time descending
            $result = $wpdb->get_results("SELECT * FROM {$completed_log_table} ORDER BY process_time DESC");
        }

        return $result;
    }

    /**
     * Updates the last time a specific batch was checked
     **/
    public static function update_batch_process_check_time($batch_id = ''){
        global $wpdb;
        $table = $wpdb->prefix . 'wpil_ai_batch_log';

        if(empty($batch_id) || !is_string($batch_id)){
            return false;
        }

        $wpdb->update($table, ['check_time' => time()], ['batch_id' => $batch_id]);
    }

    public static function delete_batch_log_data($batch_id = ''){
        global $wpdb;
        $table = $wpdb->prefix . 'wpil_ai_batch_log';

        if(empty($batch_id)){
            return false;
        }

        $deleted = $wpdb->delete($table, [
            'batch_id' => $batch_id
        ]);

        return !empty($deleted);
    }

    /**
     * 
     **/
    public static function track_error($error = ''){
        if(empty($error) || !is_string($error)){
            return false;
        }

        if(!isset(self::$error_log[$error])){
            self::$error_log[$error] = 0;
        }

        self::$error_log[$error] += 1;
        self::$current_error = $error;
    }

    /**
     * 
     **/
    public static function has_error($error = ''){
        if(empty($error) || !is_string($error)){
            return false;
        }

        if(!isset(self::$error_log[$error]) && !empty(self::$error_log[$error])){
            return true;
        }

        return false;
    }

    /**
     * Checks to see if the current error is one that should only be logged once, and it's already been logged at least once
     **/
    public static function check_single_log_error($error = '', $limit = 1){
        if(empty($error) || !is_string($error)){
            if(!empty(self::$current_error)){
                $error = self::$current_error;
            }else{
                return false;
            }
        }

        $single_log_errors = array(
            'rate_limit_exceeded',
            'insufficient_quota',
            'invalid_request_error',
            'invalid_api_key'
        );

        if(isset(self::$error_log[$error]) && !empty(self::$error_log[$error]) && in_array($error, $single_log_errors, true) && self::$error_log[$error] > $limit){
            return true;
        }

        return false;
    }

    /**
     * Saves error messages for specific posts that have been processed in a batch.
     * Does not handle batch-level errors
     * If a batch contains post A, B, C, and post B has an error...
     * The error data for post B is what this will save.
     **/
    public static function save_error_log_data($batch_id = '', $data = array(), $process = 0){
        global $wpdb;
        $table = $wpdb->prefix . 'wpil_ai_error_log';

        if(empty($batch_id) || empty($data) || self::check_single_log_error()){
            return false;
        }

        if(!is_array($data)){
            $data = array($data);
        }

        $total_count = 0;
        $count = 0;
        $insert_query = "INSERT INTO {$table} (post_id, post_type, data_type, batch_id, batch_data, message_text, process_time) VALUES ";
        $error_data = array();
        $place_holders = array();
        $total = count($data);
        $limit = 1000;
        foreach($data as $key => $dat){
            $total_count++;
            $dat = self::decode($dat);

            if( empty($dat) ||                              // if there's no data
                !isset(                                     // or we don't have all the data from OAI that we need
                    $dat->id,
                    $dat->response,
                    $dat->custom_id,
                    $dat->response->status_code,
                    $dat->response->body)
            ){
                continue;
            }

            $message = '';
            if( isset($dat->response->body->error, $dat->response->body->error->message) && 
                !empty($dat->response->body->error->message)
            ){
                $message = $dat->response->body->error->message;
            }

            if( isset($dat->response->body->body, $dat->response->body->body->error) && 
                is_string($dat->response->body->body->error) && !empty($dat->response->body->body->error)
            ){
                $message = $dat->response->body->body->error;
            }

            if(empty($message)){
                continue;
            }

            $ids = explode('_', $dat->custom_id);
            array_push(
                $error_data, 
                $ids[1],
                $ids[0],
                (($ids[0] === 'post') ? 1: 0),
                $dat->id,
                Wpil_Toolbox::json_compress($dat->response),
                $message,
                time()
            );
            $place_holders[] = "('%d', '%s', '%d', '%s', '%s', '%s', '%d')";

            // if we've hit the limit
            if($count > $limit || ($key + 1) >= $total){
                // assemble the insert
                $insert = ($insert_query . implode(', ', $place_holders));
                $insert = $wpdb->prepare($insert, $error_data);
                // insert the data
                $wpdb->query($insert);
                // reset the data variables
                $error_data = [];
                $place_holders = [];
                $count = 0;
            }

            $count++;
        }

        // if we still have data that hasn't been inserted
        if(!empty($error_data) && !empty($place_holders)){
            // assemble the insert
            $insert = ($insert_query . implode(', ', $place_holders));
            $insert = $wpdb->prepare($insert, $error_data);
            // and insert the data
            $wpdb->query($insert);
        }

        // unset the current error since it's been logged
        self::$current_error = false;

        // return the total number of posts processed
        return $total_count;
    }

    /**
     * Gets the error log data for specific post processing attempts
     **/
    public static function get_error_log_data(){
        global $wpdb;
        $table = $wpdb->prefix . 'wpil_ai_error_log';

        // TODO: Fill out if needed
    }

    /**
     * Saves system error messages from OpenAI.
     * Does not handle batch-level or post errors
     * @param object $data The error respose data from OAI
     **/
    public static function save_system_error_log_data($data = array(), $error_message = ''){
        global $wpdb;
        $table = $wpdb->prefix . 'wpil_ai_system_error_log';

        if(empty($data)){
            return false;
        }

        if(!empty($error_message)){
            $error_message = sanitize_text_field($error_message);
        }else{
            $error_message = (
                isset($data->error) && 
                !empty($data->error) && 
                isset($data->error->message) && 
                !empty($data->error->message)
            ) ? sanitize_text_field($data->error->message): 'An unknown error has occurred.';
        }


        $insert = $wpdb->insert($table, [
            'message_text' => $error_message,
            'log_data' => json_encode($data), // TODO: consider compressing
            'process_time' => time(),
        ]);

        return (!empty($insert)) ? $wpdb->insert_id: false;
    }

    /**
     * Gets a list of the recent system error log messages
     * Can be set to return X number of the most recent entries
     **/
    public static function get_system_error_log_data($entry_count = 0){
        global $wpdb;
        $table = $wpdb->prefix . 'wpil_ai_system_error_log';

        if(empty($entry_count)){
            $messages = $wpdb->get_results("SELECT * FROM {$table}");
        }else{
            $messages = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} ORDER BY `log_index` DESC LIMIT %d", $entry_count));
        }

        return (!empty($messages)) ? $messages: array();
    }

    /**
     * Gets the combined post and system error logs
     **/
    public static function get_combined_error_logs($entry_count){
        global $wpdb;
        $system_table = $wpdb->prefix . 'wpil_ai_system_error_log';
        $post_table = $wpdb->prefix . 'wpil_ai_error_log';
        $messages = array();

        if(empty($entry_count)){
            $system_messages = $wpdb->get_results("SELECT * FROM {$system_table}");
            $post_messages = $wpdb->get_results("SELECT * FROM {$post_table}");
            $messages = array_merge($system_messages, $post_messages);
        }else{
            $system_messages = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$system_table} ORDER BY `log_index` DESC LIMIT %d", $entry_count));
            $post_messages = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$post_table} ORDER BY `log_index` DESC LIMIT %d", $entry_count));
            $messages = array_merge($system_messages, $post_messages);
        }

        if(!empty($messages)){
            usort($messages, function($a, $b){
                if ($a->process_time == $b->process_time) {
                    return 0;
                }

                return ($a->process_time < $b->process_time) ? 1 : -1;
            });
        }

        if(!empty($entry_count) && !empty($messages)){
            $messages = array_slice($messages, 0, $entry_count);
        }

        return (!empty($messages)) ? $messages: array();
    }

    /**
     * Gets a list of all the posts that have had their AI processes completed.
     * Includes the intermediate stats such as the embedding calculations and keywords assignations
     **/
    public static function get_completed_post_stats($return_count = false, $ignore_unselected = false){
        global $wpdb;
        
        if($ignore_unselected){
            $selected_processes = Wpil_Settings::get_selected_ai_batch_processes(true);
            $completed = array();
            $table_indexes = array();
            if(in_array('create-post-embeddings', $selected_processes)){
                $completed['create-post-embeddings'] = array();
                $completed['calculated-post-embeddings'] = array();
                $table_indexes['create-post-embeddings'] = $wpdb->prefix . "wpil_ai_embedding_data";
                $table_indexes['calculated-post-embeddings'] = $wpdb->prefix . ((Wpil_Settings::use_ai_embedding_calculation_v2()) ? "wpil_ai_embedding_calculation_data_v2": "wpil_ai_embedding_calculation_data");
            }
            if(in_array('product-detecting', $selected_processes)){
                $completed['product-detecting'] = array();
                $table_indexes['product-detecting'] = $wpdb->prefix . "wpil_ai_product_data";
            }
            if(in_array('keyword-detecting', $selected_processes)){
                $completed['keyword-detecting'] = array();
                $table_indexes['keyword-detecting'] = $wpdb->prefix . "wpil_ai_keyword_data";
            }
        }else{
            $completed = array(
                'product-detecting' => array(),
                'create-post-embeddings' => array(),
                'calculated-post-embeddings' => array(),
                'keyword-detecting' => array(),
                'keyword-assigning' => array()
            );
    
            $table_indexes = array(
    //            'post-summarizing' => $wpdb->prefix . "wpil_ai_post_data",
                'product-detecting' => $wpdb->prefix . "wpil_ai_product_data",
                'create-post-embeddings' => $wpdb->prefix . "wpil_ai_embedding_data",
                'calculated-post-embeddings' => $wpdb->prefix . ((Wpil_Settings::use_ai_embedding_calculation_v2()) ? "wpil_ai_embedding_calculation_data_v2": "wpil_ai_embedding_calculation_data"),
                'keyword-detecting' => $wpdb->prefix . "wpil_ai_keyword_data",
            );
        }
        
        if(empty($completed)){
            return array();
        }

        $last_embedding_index = self::get_last_embedding_index();
        $processable = self::get_processable_post_ids(true);

        // get the completed posts
        foreach($table_indexes as $ind => $table){
            $keyword_processing = ($ind === 'keyword-detecting') ? true: false;
            $calculating_embeddings = ($ind === 'calculated-post-embeddings') ? true: false;

            if($keyword_processing){
                $data = $wpdb->get_results("SELECT `post_id`, `post_type`, `keywords_loaded` FROM {$table}");
            }elseif($calculating_embeddings && Wpil_Settings::use_ai_embedding_calculation_v2()){
                $data = self::get_completed_embedding_calc_v2_posts();
            }elseif($calculating_embeddings && !empty($last_embedding_index)){
                $data = $wpdb->get_results("SELECT `post_id`, `post_type` FROM {$table} WHERE `calc_index` >= {$last_embedding_index}");
            }else{
                $data = $wpdb->get_results("SELECT `post_id`, `post_type` FROM {$table}");
            }
            

            if(!empty($data)){
                foreach($data as $dat){
                    $id = $dat->post_type . '_' . $dat->post_id;

                    if(!isset($processable[$id])){
                        continue;
                    }

                    if(!isset($completed[$ind][$id])){
                        $completed[$ind][$id] = true;
                    }

                    if($keyword_processing && !empty($dat->keywords_loaded)){
                        if(!isset($completed['keyword-assigning'][$id])){
                            $completed['keyword-assigning'][$id] = true;
                        }
                    }
                }
            }
        }

        if($return_count && !empty($completed)){
            foreach($completed as $ind => $data){
                $completed[$ind] = count($data);
            }
        }

        return $completed;
    }

    /**
     * 
     **/
    public static function get_total_processable_posts(){
        return (count(Wpil_Report::get_all_post_ids('ai')) + count(Wpil_Report::get_all_term_ids()));
    }

    /**
     * Gets all of the post & term ids that are set for processing.
     **/
    public static function get_processable_post_ids($return_indexed = false){
        // get all of the post and term ids that we're set to process
        $post_ids = Wpil_Report::get_all_post_ids('ai');
        $term_ids = Wpil_Report::get_all_term_ids();
        $ids = array();

        if(!empty($post_ids)){
            foreach($post_ids as $post_id){
                $id = 'post_' . $post_id;
                $ids[$id] = true;
            }
        }

        if(!empty($term_ids)){
            foreach($term_ids as $term_id){
                $id = 'term_' . $term_id;
                $ids[$id] = true;
            }
        }

        return (!empty($ids) && !$return_indexed) ? array_keys($ids) : $ids;
    }

    /**
     * Gets a list of all the posts and their known stati
     **/
    public static function get_ai_batch_processing_status($return_count = true, $ignore_cache = false){

        if(!empty(self::$status_cache) && !$ignore_cache){
            $stati = self::$status_cache;
            if($return_count){
                // count all the items in the in progress logs 
                foreach(self::$status_cache as $state => $data){
                    if(!is_array($data) && !is_object($data)){
                        $stati[$state] = $data;
                        continue;
                    }
                    foreach($data as $process => $dat){
                        if(!isset($stati[$state])){
                            $stati[$state] = array();
                        }

                        if(empty($dat)){
                            $stati[$state][$process] = 0;
                        }elseif(is_array($dat) || is_object($dat)){
                            $stati[$state][$process] = count((array)$dat);
                        }
                    }
                }
            }
            return $stati;
        }

        $indexes = array(
            'post-summarizing' => array(),
            'product-detecting' => array(),
            'create-post-embeddings' => array(),
            'calculated-post-embeddings' => array(),
            'keyword-detecting' => array(),
            'keyword-assigning' => array(),
        );
        $stati = array(
            'completed' => $indexes,
            'in_progress' => $indexes,
            'errored' => $indexes,
            'total' => $indexes
        );

        $completed = self::get_completed_post_stats();

        if(!empty($completed)){
            $stati['completed'] = $completed;
        }

        $log_data = self::get_batch_log_data(false, false, true);

        // if there are posts currently in a batch process
        if(!empty($log_data)){
            // go over each process
            foreach($log_data as $batch){
                // decompress the specific post data
                $dat = Wpil_Toolbox::json_decompress($batch->batch_data);
                // if that worked
                if(!empty($dat)){
                    // get the process name
                    $process = self::get_process_name_from_code($batch->process_id);

                    // if this is a single process
                    if(isset($stati['in_progress'][$process])){
                        $stati['in_progress'][$process] = array_unique(array_merge($stati['in_progress'][$process], $dat));
                    }else{
                        if(false !== strpos($process, 'summary')){
                            $stati['in_progress']['post-summarizing'] = array_unique(array_merge($stati['in_progress']['post-summarizing'], $dat));
                        }

                        if(false !== strpos($process, 'product')){
                            $stati['in_progress']['product-detecting'] = array_unique(array_merge($stati['in_progress']['product-detecting'], $dat));
                        }

                        if(false !== strpos($process, 'keyword')){
                            $stati['in_progress']['keyword-detecting'] = array_unique(array_merge($stati['in_progress']['keyword-detecting'], $dat));
                        }
                    }
                }
            }
        }

        // get all of the post and term ids that we're set to process
        $stati['total'] = self::get_total_processable_posts();

        self::$status_cache = $stati;

        if($return_count){
            // count all the items in the in progress logs 
            foreach($stati as $state => $data){
                if(!is_array($data) && !is_object($data)){
                    $stati[$state] = $data;
                    continue;
                }
                foreach($data as $process => $dat){
                    if(empty($dat)){
                        $stati[$state][$process] = 0;
                    }elseif(is_array($dat) || is_object($dat)){
                        $stati[$state][$process] = count((array)$dat);
                    }
                }
            }
        }

        return $stati;
    }

    /**
     * 
     **/
    public static function check_batch_status_completed($process = '', $ignore_cache = false){
        if(empty($process)){
            return false;
        }
        
        $stati = self::get_ai_batch_processing_status(false, $ignore_cache);

        if(is_numeric($process)){
            $process = self::get_process_name_from_code($process);
        }

        $processable = self::get_processable_post_ids();
        $total = count($processable);

        if(isset($stati['completed'][$process]) && !empty($total)){
            $complete = 0;
            foreach($processable as $id){
                if(isset($stati['completed'][$process][$id])){
                    $complete++;
                }
            }

            return $complete >= $total;
        }

        return false;
    }

    /**
     * 
     **/
    public static function get_batch_status_completion_percent($process = '', $ignore_cache = false){
        if(empty($process)){
            return 0;
        }
        
        $stati = self::get_ai_batch_processing_status(false, $ignore_cache);

        if(is_numeric($process)){
            $process = self::get_process_name_from_code($process);
        }

        $processable = self::get_processable_post_ids();
        $total = count($processable);

        if(isset($stati['completed'][$process])){
            $complete = 0;
            foreach($processable as $id){
                if(isset($stati['completed'][$process][$id])){
                    $complete++;
                }
            }

            return (empty($complete)) ? 0: floor(($complete/intval($total)) * 100);
        }

        return 0;
    }

    /**
     * Checks to see if there is any AI processed data stored on the site
     **/
    public static function has_ai_processed_data($specific_table = ''){
        global $wpdb;

        $tables = array(
            $wpdb->prefix . "wpil_ai_post_data",
            $wpdb->prefix . "wpil_ai_product_data",
            $wpdb->prefix . "wpil_ai_keyword_data",
            $wpdb->prefix . "wpil_ai_embedding_data",
            $wpdb->prefix . "wpil_ai_embedding_calculation_data",
            $wpdb->prefix . "wpil_ai_embedding_calculation_data_v2",
            $wpdb->prefix . "wpil_ai_batch_log",
//            $wpdb->prefix . "wpil_ai_error_log",
        );

        $has_data = false;
        foreach($tables as $table){
            // if we're looking for a specific table and this isn't it
            if(!empty($specific_table) && false === strpos($table, $specific_table) && !(Wpil_Settings::use_ai_embedding_calculation_v2() && $specific_table === 'wpil_ai_embedding_calculation_data' && false !== strpos($table, 'wpil_ai_embedding_calculation_data_v2'))){
                // skip to the next one
                continue;
            }
            $has_data = !empty($wpdb->get_var("SELECT COUNT(*) FROM {$table} LIMIT 1"));
            if(!empty($has_data)){
                break;
            }
        }

        return $has_data;
    }

    /**
     * Attempts to pull useable JSON content out of a partially completed string 
     **/
    public static function attempt_recover_json($string, $finish_reason = false){
        // if the string is empty or isn't likely to be JSON
        if(empty($string) || false === strpos($string, '{')){
            // return the string
            return $string;
        }

        // first, make sure that the content isn't too unslashed
        $recovered = self::reslash_output_json($string);
        $maybe_recovered = json_decode($recovered);

        // if that's all it needed
        if(!empty($maybe_recovered)){
            // return the json
            return $maybe_recovered;
        }

        // if that didn't work, we might be looking at a partial string
        preg_match_all('/("[\d\-]+":\s\{[^{}]+?\})/', $recovered, $matches);

        // if we were able to pull something
        if(!empty($matches) && !empty($matches[0])){
            // apply formatting...
            $recovered = '{' . implode(',', $matches[0]) . '}';
            // try parsing
            $maybe_recovered = json_decode($recovered);
            // if that worked
            if(!empty($maybe_recovered)){
                // it's our new data!
                return $recovered;
            }
        }

        preg_match_all('/("[\S\s]*?"|[0-9]*):[\s]*("[\S\s]*?"|[0-9]*)/', $recovered, $matches);
        if(!empty($matches) && !empty($matches[0])){
            $recovered = '{' . implode(',', $matches[0]) . '}';
            $maybe_recovered = json_decode($recovered);

            // if that's all it needed
            if(!empty($maybe_recovered)){
                // return the json
                return $recovered;
            }
        }

        return $string;
    }

    public static function reslash_output_json($string){
        // first, make sure the slashing is correct
        $recovered = mb_ereg_replace(preg_quote('\\'), '', $string);
        preg_match_all('/"explanation": "(.*?)"}/', $recovered, $matches);

        $slashed = false;
        if(isset($matches[1]) && !empty($matches[1])){
            foreach($matches[1] as $match){
                $recovered = str_replace($match, str_replace(['"'], ['\\"'], $match), $recovered);
                $slashed = true;
            }
        }

        return ($slashed) ? $recovered: $string;
    }

    public static function save_response_tokens($response, $process_used = '', $is_batch = false, $fallback_query_id = '', $process_key = '', $process_id = 0){
        $saved = false;

        if(empty($response)){
            return $saved;
        }

        if(empty($process_used) && !empty(self::$purpose)){
            $process_used = self::$purpose;
        }

        $process_code = self::get_process_code_from_name($process_used);

        if(!is_array($response)){
            $response = array($response);
        }

        foreach($response as $index => $dat){
            $query_id = self::extract_query_id_from_response($dat);
            if(is_string($dat)){
                $dat = self::decode($dat);
                if(empty($query_id)){
                    $query_id = self::extract_query_id_from_response($dat);
                }
            }
            if(empty($query_id) && !empty($fallback_query_id)){
                $query_id = (string) $fallback_query_id;
            }
            if(empty($query_id) && isset(self::$query_ids[$index]) && !empty(self::$query_ids[$index])){
                $query_id = self::$query_ids[$index];
            }

            $credit_sources = array();
            if(is_object($dat)){
                $credit_sources[] = $dat;
            }

            if(isset($dat->response) && !empty($dat->response)){
                if(is_object($dat->response)){
                    $credit_sources[] = $dat->response;
                }
                $dat = $dat->response;
            }

            if(isset($dat->body) && !empty($dat->body)){
                $body = is_string($dat->body) ? self::decode($dat->body) : $dat->body;
                if(is_object($body)){
                    $credit_sources[] = $body;
                }
                if(is_object($body) && isset($body->usage)){
                    $dat = $body;
                }
            }

            if(!empty($dat) && isset($dat->model) && !empty($dat->model) && isset($dat->usage) && is_object($dat->usage)){
                $usage = $dat->usage;

                // Support both Chat Completions and Responses-style usage keys.
                $input = 0;
                if(isset($usage->prompt_tokens)){
                    $input = (int) $usage->prompt_tokens;
                }elseif(isset($usage->input_tokens)){
                    $input = (int) $usage->input_tokens;
                }

                $output = 0;
                if(isset($usage->completion_tokens)){
                    $output = (int) $usage->completion_tokens;
                }elseif(isset($usage->output_tokens)){
                    $output = (int) $usage->output_tokens;
                }

                $total = isset($usage->total_tokens) ? (int) $usage->total_tokens : 0;

                $cached_prompt = 0;
                if(isset($usage->prompt_tokens_details, $usage->prompt_tokens_details->cached_tokens)){
                    $cached_prompt = (int) $usage->prompt_tokens_details->cached_tokens;
                }elseif(isset($usage->input_tokens_details, $usage->input_tokens_details->cached_tokens)){
                    $cached_prompt = (int) $usage->input_tokens_details->cached_tokens;
                }elseif(isset($usage->cached_tokens)){
                    $cached_prompt = (int) $usage->cached_tokens;
                }

                $reasoning = 0;
                if(isset($usage->completion_tokens_details, $usage->completion_tokens_details->reasoning_tokens)){
                    $reasoning = (int) $usage->completion_tokens_details->reasoning_tokens;
                }elseif(isset($usage->output_tokens_details, $usage->output_tokens_details->reasoning_tokens)){
                    $reasoning = (int) $usage->output_tokens_details->reasoning_tokens;
                }

                $credits_used = 0;
                if(isset($usage->credit_used)){
                    $credits_used = (float) $usage->credit_used;
                }elseif(isset($usage->credits_used)){
                    $credits_used = (float) $usage->credits_used;
                }else{
                    foreach($credit_sources as $credit_source){
                        if(isset($credit_source->credit_used)){
                            $credits_used = (float) $credit_source->credit_used;
                            break;
                        }elseif(isset($credit_source->credits_used)){
                            $credits_used = (float) $credit_source->credits_used;
                            break;
                        }elseif(isset($credit_source->results) && (is_array($credit_source->results) || is_object($credit_source->results))){
                            $result_credits = 0;
                            foreach($credit_source->results as $result){
                                if(is_object($result) && isset($result->credit_used)){
                                    $result_credits += (float) $result->credit_used;
                                }elseif(is_object($result) && isset($result->credits_used)){
                                    $result_credits += (float) $result->credits_used;
                                }elseif(is_array($result) && isset($result['credit_used'])){
                                    $result_credits += (float) $result['credit_used'];
                                }elseif(is_array($result) && isset($result['credits_used'])){
                                    $result_credits += (float) $result['credits_used'];
                                }
                            }

                            if(!empty($result_credits)){
                                $credits_used = $result_credits;
                                break;
                            }
                        }
                    }
                }

                // Some provider responses only report cached token details.
                if($input < 1 && $cached_prompt > 0){
                    $input = $cached_prompt;
                }

                if($total < 1 && ($input > 0 || $output > 0)){
                    $total = ($input + $output);
                }

                $status = self::save_token_reference($dat->model, $is_batch, $process_code, $input, $output, $total, $cached_prompt, $reasoning, $credits_used, $query_id, 'usage', '', '', 0, 0, $process_key, $process_id);
            
                if(!$saved && $status){
                    $saved = true;
                }

                if(!empty($credits_used)){
                    self::subtract_ai_credits($credits_used);
                }
            }elseif(self::$ai_service_connected){
                $credits_used = 0;
                $model = '';

                foreach($credit_sources as $credit_source){
                    if(empty($model)){
                        if(isset($credit_source->model) && !empty($credit_source->model)){
                            $model = (string) $credit_source->model;
                        }elseif(isset($credit_source->model_version) && !empty($credit_source->model_version)){
                            $model = (string) $credit_source->model_version;
                        }
                    }

                    if(isset($credit_source->credit_used)){
                        $credits_used = (float) $credit_source->credit_used;
                        break;
                    }elseif(isset($credit_source->credits_used)){
                        $credits_used = (float) $credit_source->credits_used;
                        break;
                    }elseif(isset($credit_source->results) && (is_array($credit_source->results) || is_object($credit_source->results))){
                        $result_credits = 0;
                        foreach($credit_source->results as $result){
                            if(is_object($result) && isset($result->credit_used)){
                                $result_credits += (float) $result->credit_used;
                            }elseif(is_object($result) && isset($result->credits_used)){
                                $result_credits += (float) $result->credits_used;
                            }elseif(is_array($result) && isset($result['credit_used'])){
                                $result_credits += (float) $result['credit_used'];
                            }elseif(is_array($result) && isset($result['credits_used'])){
                                $result_credits += (float) $result['credits_used'];
                            }
                        }

                        if(!empty($result_credits)){
                            $credits_used = $result_credits;
                            break;
                        }
                    }
                }

                // If the LW AI service only sends the credit count back, log that too.
                if(!empty($credits_used)){
                    if(empty($model)){
                        $model = ($process_used === 'compare-sentences-to-content') ? 'gpt-5-nano': (!empty(self::$model) ? self::$model: 'linkwhisper-ai');
                    }

                    $status = self::save_token_reference($model, $is_batch, $process_code, 0, 0, 0, 0, 0, $credits_used, $query_id, 'usage', '', '', 0, 0, $process_key, $process_id);

                    if(!$saved && $status){
                        $saved = true;
                    }

                    self::subtract_ai_credits($credits_used);
                }
            }
        }

        return $saved;
    }

    public static function save_token_reference($model = '', $batch_process = false, $process_number = 0, $input_tokens = 0, $output_tokens = 0, $total_tokens = 0, $cached_prompt_tokens = 0, $reasoning_tokens = 0, $credits_used = 0, $query_id = '', $transaction_type = 'usage', $transaction_ref = '', $transaction_note = '', $process_time = 0, $credits_added = 0, $process_key = '', $process_id = 0){
        global $wpdb;
        $table = $wpdb->prefix . 'wpil_ai_token_use_data';

        if(empty($model)){
            return false;
        }

        if(empty($total_tokens) && (!empty($input_tokens) || !empty($output_tokens))){
            $total_tokens = ($input_tokens + $output_tokens);
        }

        $context = array('task_key' => '', 'process_id' => 0);
        if($transaction_type === 'usage'){
            $context = self::get_current_credit_tracking_context($process_number, $batch_process, $process_key, $process_id);
        }

        $saved = $wpdb->insert($table, [
            'query_id' => !empty($query_id) ? substr(sanitize_text_field((string)$query_id), 0, 128) : null,
            'transaction_type' => !empty($transaction_type) ? substr(sanitize_key((string) $transaction_type), 0, 24) : 'usage',
            'transaction_ref' => !empty($transaction_ref) ? substr(sanitize_text_field((string) $transaction_ref), 0, 128) : null,
            'transaction_note' => !empty($transaction_note) ? substr(sanitize_text_field((string) $transaction_note), 0, 64) : null,
            'model_version' => $model,
            'batch_processed' => (int) $batch_process,
            'input_tokens' => $input_tokens,
            'output_tokens' => $output_tokens,
            'cached_prompt_tokens' => $cached_prompt_tokens,
            'reasoning_tokens' => $reasoning_tokens,
            'total_tokens' => $total_tokens,
            'credits_used' => $credits_used,
            'credits_added' => round((float) $credits_added, 4),
            'process_used' => (int) $process_number,
            'process_key' => !empty($context['task_key']) ? $context['task_key'] : '',
            'process_id' => !empty($context['process_id']) ? (int) $context['process_id'] : 0,
            'process_time' => !empty($process_time) ? (int) $process_time : time(),
        ]);

        return !empty($saved);
    }

    public static function save_credit_deposit_notice($purchase_id = '', $credits = 0, $purchase_type = '', $purchase_time = 0){
        global $wpdb;
        $table = $wpdb->prefix . 'wpil_ai_token_use_data';
        $purchase_id = substr(sanitize_text_field((string) $purchase_id), 0, 128);
        $credits = round((float) $credits, 4);
        $purchase_time = !empty($purchase_time) ? (int) $purchase_time : time();

        if(empty($purchase_id) || $credits <= 0){
            return false;
        }

        // If this purchase is already in the ledger, we can skip it.
        $existing = $wpdb->get_var($wpdb->prepare("SELECT token_index FROM {$table} WHERE `transaction_type` = %s AND `transaction_ref` = %s LIMIT 1", 'credit-deposit', $purchase_id));
        if(!empty($existing)){
            return true;
        }

        $saved = self::save_token_reference(
            'credit-deposit', false, self::get_process_code_from_name('credit-deposit'), 0, 0, 0, 0, 0, 0, '', 'credit-deposit', $purchase_id, $purchase_type, $purchase_time, $credits
        );

        if(!$saved){
            // If another request slipped the same purchase in first, count that as a win.
            $existing = $wpdb->get_var($wpdb->prepare("SELECT token_index FROM {$table} WHERE `transaction_type` = %s AND `transaction_ref` = %s LIMIT 1", 'credit-deposit', $purchase_id));
            return !empty($existing);
        }

        return true;
    }

    private static function extract_query_id_from_response($response = null){
        if(empty($response)){
            return '';
        }

        if(is_string($response)){
            $decoded = self::decode($response);
            if(!empty($decoded)){
                $response = $decoded;
            }else{
                return '';
            }
        }

        if(is_array($response)){
            $response = (object) $response;
        }

        if(!is_object($response)){
            return '';
        }

        if(isset($response->custom_id) && !empty($response->custom_id)){
            return (string) $response->custom_id;
        }

        if(isset($response->query_id) && !empty($response->query_id)){
            return (string) $response->query_id;
        }

        if(isset($response->metadata) && is_object($response->metadata)){
            if(isset($response->metadata->custom_id) && !empty($response->metadata->custom_id)){
                return (string) $response->metadata->custom_id;
            }
            if(isset($response->metadata->query_id) && !empty($response->metadata->query_id)){
                return (string) $response->metadata->query_id;
            }
        }

        return '';
    }

    /**
     * Calculates how much the user has spent on tokens based on a time range
     **/
    public static function calculate_token_cost_by_time($start_time = 0, $end_time = 0){
        global $wpdb;
        $table = $wpdb->prefix . 'wpil_ai_token_use_data';

        // As of 09-18-2024
        $normal_costs_per_model = self::get_standard_model_costs();

        $unprefixed = array(
            'gpt-4o-mini' => array('input' => 0.15/1000000, 'output' => 0.600/1000000),
            'gpt-4o' => array('input' => 5.00/1000000, 'output' => 15.00/1000000),
            'gpt-4-turbo' => array('input' => 10.00/1000000, 'output' => 30.00/1000000),
        );

        if(empty($start_time)){
            return 0;
        }

        if(empty($end_time)){
            $end_time = time();
        }

        // if we're using our AI service
        if(self::$ai_service_connected){
            // just pull the credits and return them
            $cost = $wpdb->get_var($wpdb->prepare("SELECT SUM(credits_used) FROM {$table} WHERE `process_time` >= %d AND `process_time` <= %d AND `transaction_type` = %s", $start_time, $end_time, 'usage'));
            return round((float) $cost, 4);
        }

        $tokens = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE `process_time` >= %d AND `process_time` <= %d", $start_time, $end_time));
    
        $cost = 0;
        if(!empty($tokens)){
            foreach($tokens as $token){
                if(false !== strpos($token->model_version, 'text-embedding')){
                    $price = ($token->total_tokens * $normal_costs_per_model[$token->model_version]['input']);
                    $cost += ($token->batch_processed) ? ($price/2): $price;
                }elseif(isset($normal_costs_per_model[$token->model_version])){
                    $input = (!empty($token->cached_prompt_tokens)) ? abs($token->input_tokens - $token->cached_prompt_tokens): $token->input_tokens;
                    $price =  (
                        ($input * $normal_costs_per_model[$token->model_version]['input']) + 
                        ($token->output_tokens * $normal_costs_per_model[$token->model_version]['output']) +
                        ($token->cached_prompt_tokens * ($normal_costs_per_model[$token->model_version]['input']/2))
                    );

                    $cost += ($token->batch_processed) ? ($price/2): $price;
                }else{
                    foreach($unprefixed as $model => $dat){
                        if(false !== strpos($token->model_version, $model)){
                            $input = (!empty($token->cached_prompt_tokens)) ? abs($token->input_tokens - $token->cached_prompt_tokens): $token->input_tokens;
                            $price = (
                                ($input * $unprefixed[$model]['input']) + 
                                ($token->output_tokens * $unprefixed[$model]['output']) +
                                ($token->cached_prompt_tokens * ($unprefixed[$model]['input']/2))
                            );
                            $cost += ($token->batch_processed) ? ($price/2): $price;
                            break;
                        }
                    }
                }
            }
        }

        return $cost;
    }

    public static function get_standard_model_costs(){
        return array(
            'text-embedding-3-small' => array('input' => 0.02/1000000, 'output' => 0.02/1000000),
            'text-embedding-3-large' => array('input' => 0.13/1000000, 'output' => 0.13/1000000),
            'ada v2' => array('input' => 0.10/1000000, 'output' => 0.10/1000000),

            'gpt-5.4-mini' => array('input' => 0.75/1000000, 'output' => 4.50/1000000),
            'gpt-5.4-nano' => array('input' => 0.20/1000000, 'output' => 1.25/1000000),
            'gpt-5.1' => array('input' => 1.25/1000000, 'output' => 10.00/1000000),
            'gpt-5' => array('input' => 1.25/1000000, 'output' => 10.00/1000000),
            'gpt-5-mini' => array('input' => 0.25/1000000, 'output' => 2.00/1000000),
            'gpt-5-nano' => array('input' => 0.05/1000000, 'output' => 0.40/1000000),

            'gpt-4.1' => array('input' => 2.00/1000000, 'output' => 8.00/1000000),
            'gpt-4.1-mini' => array('input' => 0.40/1000000, 'output' => 1.60/1000000),
            'gpt-4.1-nano' => array('input' => 0.10/1000000, 'output' => 0.40/1000000),

            'gpt-4o-mini' => array('input' => 0.15/1000000, 'output' => 0.600/1000000),
            'gpt-4o-mini-2024-07-18' => array('input' => 0.15/1000000, 'output' => 0.600/1000000),

            'gpt-4o' => array('input' => 2.50/1000000, 'output' => 10.00/1000000),
            'gpt-4o-2024-08-06' => array('input' => 2.50/1000000, 'output' => 10.00/1000000),
            'gpt-4o-2024-05-13' => array('input' => 5.00/1000000, 'output' => 15.00/1000000),

            'chatgpt-4o-latest' => array('input' => 5.00/1000000, 'output' => 15.00/1000000),
            'gpt-4-turbo' => array('input' => 10.00/1000000, 'output' => 30.00/1000000),
            'gpt-4-turbo-2024-04-09' => array('input' => 10.00/1000000, 'output' => 30.00/1000000),
            'gpt-4' => array('input' => 30.00/1000000, 'output' => 60.00/1000000),
            'gpt-4-32k' => array('input' => 60.00/1000000, 'output' => 120.00/1000000),
            'gpt-4-0125-preview' => array('input' => 10.00/1000000, 'output' => 30.00/1000000),
            'gpt-4-1106-preview' => array('input' => 10.00/1000000, 'output' => 30.00/1000000),
            'gpt-4-vision-preview' => array('input' => 10.00/1000000, 'output' => 30.00/1000000),
            'gpt-3.5-turbo-0125' => array('input' => 0.50/1000000, 'output' => 1.50/1000000),
            'gpt-3.5-turbo-instruct' => array('input' => 1.50/1000000, 'output' => 2.00/1000000),
            'gpt-3.5-turbo-1106' => array('input' => 1.50/1000000, 'output' => 2.00/1000000),
            'gpt-3.5-turbo-0613' => array('input' => 1.00/1000000, 'output' => 2.00/1000000),
            'gpt-3.5-turbo-16k-0613' => array('input' => 3.00/1000000, 'output' => 4.00/1000000),
            'gpt-3.5-turbo-0301' => array('input' => 1.50/1000000, 'output' => 2.00/1000000),
        );
    }

    private static function get_credit_tracking_process_counter_option_name(){
        return 'wpil_ai_credit_process_counter';
    }

    private static function get_credit_tracking_process_run_option_name(){
        return 'wpil_ai_credit_process_runs';
    }

    private static function get_next_credit_tracking_process_id(){
        $option = self::get_credit_tracking_process_counter_option_name();
        $process_id = ((int) get_option($option, 0)) + 1;
        update_option($option, $process_id, false);

        return $process_id;
    }

    private static function get_credit_tracking_process_runs(){
        $runs = get_option(self::get_credit_tracking_process_run_option_name(), array());

        return (is_array($runs)) ? $runs: array();
    }

    private static function update_credit_tracking_process_runs($runs = array()){
        update_option(self::get_credit_tracking_process_run_option_name(), (is_array($runs)) ? $runs: array(), false);
    }

    private static function get_credit_tracking_context_cache_key($context_key = ''){
        $context_key = trim((string) $context_key);

        return (!empty($context_key)) ? md5('credit_task_run_' . $context_key): '';
    }

    private static function sanitize_credit_tracking_task_key($task_key = ''){
        $task_key = substr(sanitize_key((string) $task_key), 0, 64);

        return (!empty($task_key)) ? $task_key: '';
    }

    public static function begin_credit_tracking_task_run($task_key = '', $context_key = '', $persist = true){
        $task_key = self::sanitize_credit_tracking_task_key($task_key);
        if(empty($task_key)){
            return array('task_key' => '', 'process_id' => 0, 'context_key' => '');
        }

        $cache_key = self::get_credit_tracking_context_cache_key(!empty($context_key) ? $context_key: $task_key);
        $process_id = 0;

        if($persist){
            $runs = self::get_credit_tracking_process_runs();
            if(isset($runs[$cache_key]['process_id'], $runs[$cache_key]['task_key']) &&
                !empty($runs[$cache_key]['process_id']) &&
                $runs[$cache_key]['task_key'] === $task_key)
            {
                $process_id = (int) $runs[$cache_key]['process_id'];
            }else{
                $process_id = self::get_next_credit_tracking_process_id();
            }

            $runs[$cache_key] = array(
                'task_key' => $task_key,
                'process_id' => $process_id,
                'updated_at' => time(),
            );
            self::update_credit_tracking_process_runs($runs);
        }else{
            if(isset(self::$request_credit_tracking_runs[$cache_key]['process_id'], self::$request_credit_tracking_runs[$cache_key]['task_key']) &&
                !empty(self::$request_credit_tracking_runs[$cache_key]['process_id']) &&
                self::$request_credit_tracking_runs[$cache_key]['task_key'] === $task_key)
            {
                $process_id = (int) self::$request_credit_tracking_runs[$cache_key]['process_id'];
            }else{
                $process_id = self::get_next_credit_tracking_process_id();
                self::$request_credit_tracking_runs[$cache_key] = array(
                    'task_key' => $task_key,
                    'process_id' => $process_id,
                    'updated_at' => time(),
                );
            }
        }

        self::$active_credit_tracking_context = array(
            'task_key' => $task_key,
            'process_id' => $process_id,
            'context_key' => $cache_key,
            'persist' => !empty($persist),
        );

        return self::$active_credit_tracking_context;
    }

    public static function clear_credit_tracking_task_run($context_key = ''){
        $cache_key = self::get_credit_tracking_context_cache_key($context_key);

        if(!empty($cache_key)){
            $runs = self::get_credit_tracking_process_runs();
            if(isset($runs[$cache_key])){
                unset($runs[$cache_key]);
                self::update_credit_tracking_process_runs($runs);
            }

            if(isset(self::$request_credit_tracking_runs[$cache_key])){
                unset(self::$request_credit_tracking_runs[$cache_key]);
            }

            if(isset(self::$active_credit_tracking_context['context_key']) && self::$active_credit_tracking_context['context_key'] === $cache_key){
                self::$active_credit_tracking_context = array();
            }

            return;
        }

        self::$active_credit_tracking_context = array();
    }

    public static function clear_all_credit_tracking_task_runs(){
        self::$active_credit_tracking_context = array();
        self::$request_credit_tracking_runs = array();
        delete_option(self::get_credit_tracking_process_run_option_name());
    }

    public static function get_credit_tracking_task_key_from_linking_process_key($process_key = ''){
        $process_key = sanitize_text_field((string) $process_key);
        if(empty($process_key)){
            return '';
        }

        $task_map = array(
            md5('one-click-setup') => 'one-click-ai-suggestions',
            md5('orphan-post-search') => 'fix-orphaned-posts',
            md5('link-coverage-search') => 'fix-link-coverage',
            md5('link-quality-search') => 'fix-link-quality',
            md5('broken-link-search') => 'fix-broken-links',
            md5('external-focus-search') => 'fix-external-focus',
        );

        return isset($task_map[$process_key]) ? $task_map[$process_key]: 'ai-linking-task';
    }

    public static function get_credit_tracking_task_key_from_purpose($purpose = ''){
        $purpose = sanitize_key((string) $purpose);
        if(empty($purpose)){
            return '';
        }

        $task_map = array(
            'post-summarizing' => 'site-analysis-post-summarizing',
            'product-detecting' => 'site-analysis-product-detection',
            'create-post-embeddings' => 'site-analysis-relation-analysis',
            'keyword-detecting' => 'site-analysis-keyword-analysis',
            'summary-and-product-searching' => 'site-analysis-summary-product-search',
            'summary-and-keyword-searching' => 'site-analysis-summary-keyword-search',
            'product-and-keyword-searching' => 'site-analysis-product-keyword-search',
            'summary-keyword-and-product-searching' => 'site-analysis-summary-keyword-product-search',
            'create-post-sentence-embeddings' => 'create-post-sentence-embeddings',
            'assess-sentence-anchors' => 'assess-sentence-anchors',
            'assess-outbound-links' => 'assess-outbound-links',
            'assess-inbound-links' => 'assess-inbound-links',
            'compare-sentences-to-content' => 'sentence-relatedness-check',
            'broken-link-replacement' => 'broken-link-replacement',
            'get-available-credits' => 'credit-balance-check',
        );

        return isset($task_map[$purpose]) ? $task_map[$purpose]: self::sanitize_credit_tracking_task_key($purpose);
    }

    public static function get_credit_tracking_task_key_from_process_code($process_code = 0){
        return self::get_credit_tracking_task_key_from_purpose(self::get_process_name_from_code((int) $process_code));
    }

    public static function get_credit_tracking_task_pretty_name($task_key = '', $process_id = 0, $include_run = false){
        $task_key = self::sanitize_credit_tracking_task_key($task_key);
        $label_map = array(
            'one-click-ai-suggestions' => 'One Click AI Suggestions',
            'fix-orphaned-posts' => 'Fix Orphaned Posts',
            'fix-link-coverage' => 'Fix Link Coverage',
            'fix-link-quality' => 'Fix Link Quality',
            'fix-broken-links' => 'Fix Broken Links',
            'fix-external-focus' => 'Fix External Focus',
            'ai-linking-task' => 'AI Linking Task',
            'site-analysis-post-summarizing' => 'Site Analysis: Post Summarizing',
            'site-analysis-product-detection' => 'Site Analysis: Product Detection',
            'site-analysis-relation-analysis' => 'Site Analysis: Relation Analysis',
            'site-analysis-keyword-analysis' => 'Site Analysis: Keyword Analysis',
            'site-analysis-summary-product-search' => 'Site Analysis: Summary + Product Search',
            'site-analysis-summary-keyword-search' => 'Site Analysis: Summary + Keyword Search',
            'site-analysis-product-keyword-search' => 'Site Analysis: Product + Keyword Search',
            'site-analysis-summary-keyword-product-search' => 'Site Analysis: Summary + Keyword + Product Search',
            'create-post-sentence-embeddings' => 'Create Post Sentence Embeddings',
            'assess-sentence-anchors' => 'Assess Sentence Anchors',
            'assess-outbound-links' => 'Assess Outbound Links',
            'assess-inbound-links' => 'Assess Inbound Links',
            'sentence-relatedness-check' => 'Sentence Relatedness Check',
            'broken-link-replacement' => 'Broken Link Replacement',
            'credit-balance-check' => 'Credit Balance Check',
        );

        $label = isset($label_map[$task_key]) ? $label_map[$task_key] : ucwords(str_replace('-', ' ', $task_key));

        if($include_run && !empty($process_id)){
            $label .= ' (Run #' . (int) $process_id . ')';
        }

        return $label;
    }

    private static function get_current_credit_tracking_context($process_number = 0, $batch_process = false, $task_key = '', $process_id = 0){
        $task_key = self::sanitize_credit_tracking_task_key($task_key);
        $process_id = (int) $process_id;

        if(!empty($task_key) && !empty($process_id)){
            return array('task_key' => $task_key, 'process_id' => $process_id);
        }

        if(!empty($task_key) &&
            !empty(self::$active_credit_tracking_context['task_key']) &&
            !empty(self::$active_credit_tracking_context['process_id']) &&
            self::$active_credit_tracking_context['task_key'] === $task_key)
        {
            return array(
                'task_key' => self::$active_credit_tracking_context['task_key'],
                'process_id' => (int) self::$active_credit_tracking_context['process_id'],
            );
        }

        if(!empty(self::$active_linking_process_key)){
            $task_key = self::get_credit_tracking_task_key_from_linking_process_key(self::$active_linking_process_key);
            if(!empty($task_key)){
                return self::begin_credit_tracking_task_run($task_key, 'linking:' . self::$active_linking_process_key, true);
            }
        }

        if(!empty(self::$purpose)){
            $task_key = self::get_credit_tracking_task_key_from_purpose(self::$purpose);
            if(!empty($task_key)){
                return self::begin_credit_tracking_task_run($task_key, 'purpose:' . self::$purpose, !empty($batch_process));
            }
        }

        if(!empty($process_number)){
            $task_key = self::get_credit_tracking_task_key_from_process_code($process_number);
            if(!empty($task_key)){
                return self::begin_credit_tracking_task_run($task_key, 'request:' . $task_key, false);
            }
        }

        if(!empty(self::$active_credit_tracking_context['task_key']) && !empty(self::$active_credit_tracking_context['process_id'])){
            return array(
                'task_key' => self::$active_credit_tracking_context['task_key'],
                'process_id' => (int) self::$active_credit_tracking_context['process_id'],
            );
        }

        return array('task_key' => '', 'process_id' => 0);
    }

    public static function get_process_code_from_name($name = ''){
        if(is_int($name)){
            return $name;
        }

        $code = 0;
        switch ($name) {
            case 'suggestion-scoring':
                $code = 1;
                break;
            case 'post-summarizing':
                $code = 2;
                break;
            case 'product-detecting':
                $code = 3;
                break;
            case 'create-post-embeddings':
                $code = 4;
                break;
            case 'keyword-detecting':
                $code = 5;
                break;
            case 'summary-and-product-searching':
                $code = 6;
                break;
            case 'summary-and-keyword-searching':
                $code = 7;
                break;
            case 'product-and-keyword-searching':
                $code = 8;
                break;
            case 'summary-keyword-and-product-searching':
                $code = 9;
                break;
            case 'create-post-sentence-embeddings':
                $code = 10;
                break;
            case 'assess-sentence-anchors':
                $code = 11;
                break;
            case 'assess-outbound-links':
                $code = 12;
                break;
            case 'assess-inbound-links':
                $code = 13;
                break;
            case 'broken-link-replacement':
                $code = 14;
                break;
            case 'get-available-credits':
                $code = 15;
                break;
            case 'credit-deposit': // beseeching forgiveness of future selves is in order...
                $code = 16;
                break;
            case 'compare-sentences-to-content':
                $code = 17;
                break;
        }

        return $code;
    }

    public static function get_process_name_from_code($code = 0){
        $process_list = array(
            0 => 'unknown',
            1 => 'suggestion-scoring',
            2 => 'post-summarizing',
            3 => 'product-detecting',
            4 => 'create-post-embeddings',
            5 => 'keyword-detecting',
            6 => 'summary-and-product-searching',
            7 => 'summary-and-keyword-searching',
            8 => 'product-and-keyword-searching',
            9 => 'summary-keyword-and-product-searching',
            10 => 'create-post-sentence-embeddings',
            11 => 'assess-sentence-anchors',
            12 => 'assess-outbound-links',
            13 => 'assess-inbound-links',
            14 => 'broken-link-replacement',
            15 => 'get-available-credits',
            16 => 'credit-deposit', // further beseeching forgiveness is in order...
            17 => 'compare-sentences-to-content',
        );

        return (isset($process_list[$code])) ? $process_list[$code]: 'unknown';
    }

    public static function get_process_pretty_name_from_code($code = 0, $transaction_note = ''){
        $process_list = array(
            0 => 'Unknown',
            1 => 'Suggestion Scoring',
            2 => 'Post Summarizing',
            3 => 'Product Detection',
            4 => 'Create Post Embeddings',
            5 => 'Keyword Detection',
            6 => 'Summary + Product Search',
            7 => 'Summary + Keyword Search',
            8 => 'Product + Keyword Search',
            9 => 'Summary + Keyword + Product Search',
            10 => 'Create Post Sentence Embeddings',
            11 => 'Assess Sentence Anchors',
            12 => 'Assess Outbound Links',
            13 => 'Assess Inbound Links',
            14 => 'Broken Link Replacement',
            15 => 'Credit Balance Check',
            16 => 'Credits Added',
            17 => 'Sentence Relatedness Check',
        );

        $label = isset($process_list[$code]) ? $process_list[$code] : 'Unknown';
        if(16 === (int) $code && !empty($transaction_note)){
            $label .= ' (' . ucwords(str_replace(array('-', '_'), ' ', (string) $transaction_note)) . ')';
        }

        return $label;
    }

    public static function get_credit_history_filter_processes(){
        $processes = array();
        for($i = 1; $i <= 17; $i++){
            $processes[$i] = self::get_process_pretty_name_from_code($i);
        }

        return $processes;
    }

    /**
     * Analyzes site posts to create summaries of them and/or to identify products within them.
     **/
    public static function analyze_site_posts($active_processes_override = null){
        $time = microtime(true);
        $token_size = 0;
        $doing_ajax = (defined('DOING_AJAX') && DOING_AJAX) ? true: false;

        // first, figure out what we're doing
        $active_processes = (!empty($active_processes_override) && is_array($active_processes_override)) ? $active_processes_override: Wpil_Settings::get_selected_ai_batch_processes(true, true);
        if(in_array('create-post-embeddings', $active_processes, true)){
            $key = array_search('create-post-embeddings', $active_processes, true);
            unset($active_processes[$key]);
        }
        $active_processes = array_values(array_unique($active_processes));

        // if we're not doing anything
        if(empty($active_processes)){
            // exit
            return false;
        }

        $grouped = array();
        foreach($active_processes as $process){
            if(self::check_batch_status_completed($process)){
                self::clear_credit_tracking_task_run('purpose:' . $process);
                continue;
            }

            $gpt = Wpil_Settings::getChatGPTVersion($process);
            if(!isset($grouped[$gpt])){
                $grouped[$gpt] = array();
            }
            $grouped[$gpt][] += self::get_process_code_from_name($process);
        }

        foreach($grouped as $model => $dat){
            $process = 0;
            if(Wpil_Base::overTimeLimit(5, 40)){
                break;
            }

            self::$model = $model;
            $task_sum = array_sum($dat); //TODO: Create a better way to select the active processes
            $doing_keywords = (count($dat) === 1 && isset($dat[0]) && $dat[0] === 5) ? true: false; // TODO: Create a better way to do this

            // if we're doing product search, summaries and keyword detecting
            if($task_sum === 10){
                self::$purpose = 'summary-keyword-and-product-searching';
                $keyword_count = Wpil_Settings::get_ai_keyword_count_max();

                // get the posts
                $posts = self::get_all_batch_process_posts(self::$purpose, self::get_process_code_from_name(self::$purpose));
            }elseif($task_sum === 8){ // if we're doing product and keyword detecting
                self::$purpose = 'product-and-keyword-searching';
                $keyword_count = Wpil_Settings::get_ai_keyword_count_max();

                // get the posts
                $posts = self::get_all_batch_process_posts(self::$purpose, self::get_process_code_from_name(self::$purpose));
            }elseif($task_sum === 7){ // if we're doing summary and keyword detecting
                self::$purpose = 'summary-and-keyword-searching';
                $keyword_count = Wpil_Settings::get_ai_keyword_count_max();

                // get the posts
                $posts = self::get_all_batch_process_posts(self::$purpose, self::get_process_code_from_name(self::$purpose));
            }elseif($task_sum === 5 && $doing_keywords){ // if we're doing keyword detecting
                self::$purpose = 'keyword-detecting';
                $keyword_count = Wpil_Settings::get_ai_keyword_count_max();

                // get the posts
                $posts = self::get_all_batch_process_posts(self::$purpose, self::get_process_code_from_name(self::$purpose));
            }elseif($task_sum === 5){
                self::$purpose = 'summary-and-product-searching';

                // get the posts
                $posts = self::get_all_batch_process_posts(self::$purpose, self::get_process_code_from_name(self::$purpose));
            }elseif($task_sum === 2){ // doing summaries
                self::$purpose = 'post-summarizing';

                // get the posts
                $posts = self::get_all_batch_process_posts(self::$purpose, self::get_process_code_from_name(self::$purpose));
            }elseif($task_sum === 3){// doing product search
                self::$purpose = 'product-detecting';

                // get the posts
                $posts = self::get_all_batch_process_posts(self::$purpose, self::get_process_code_from_name(self::$purpose));
            }

            if(empty($posts) || (self::check_delayed_batch_process(self::$purpose) && !$doing_ajax)){
                continue;
            }

            shuffle($posts);
            $post_data = array();
            $count = 0;
            $instruction_size = 600;
            $chunked_posts = self::get_chunked_posts();
            $limits = self::get_api_rate_limits();
            foreach($posts as $post){
                // exit if we've been at this for more than 20 seconds or we've managed to pull down 50,000 posts
                if(microtime(true) - $time > 20 || $count >= self::$batch_limit || $doing_ajax && $count >= self::$concurrency){
                    break;
                }

                $id = ($post->type . '_' . $post->id);
                // get the cleaned content
                $content = mb_ereg_replace("\n", '', mb_ereg_replace('(([a-zA-Z\-_0-9]+="[^"]*")+?[\s]?)', '', strip_tags($post->getContent(false), '<h1><h2><h3><h4><h5><h6><title><ul><ol><li>')));

                // if there's no content to process
                if(empty($content)){
                    // mark it as complete and don't waste time on it
                    self::save_empty_post_data($id, self::$model, self::$purpose);
                    continue;
                }

                if($doing_ajax && isset($chunked_posts[$id])){
                    $content = self::chunk_post_content($content, 2500);
                }else{
                    $content = self::trim_text_to_token_limit($content, self::$model, 7800);
                }

                $query = (is_array($content)) ? array_reduce($content, function($count, $chunk) use ($instruction_size){ return $count += ($instruction_size + self::count_tokens($chunk, self::$model)); }): $instruction_size + self::count_tokens($content, self::$model);
                if($token_size + $query < ($limits[self::$model] - 10000)){
                    $token_size += $query;
                    $post_data[$id] = $content;
                    if(is_array($content)){
                        $count += count($content);
                    }

                }else{
                    break;
                }

                $count++;
            }

            if(self::$ai_service_connected){
                self::live_query_linkwhisper_ai_results($post_data, 'completions');
                return;
            }
        }
    }

    /**
     * 
     **/
    public static function create_site_embeddings(){
        $time = microtime(true);
        $posts = self::get_all_batch_process_posts('create-post-embeddings', 4);
        self::$purpose = 'create-post-embeddings';
        self::$model = Wpil_Settings::getChatGPTVersion('create-post-embeddings');
        $token_size = 0;
        $doing_ajax = (defined('DOING_AJAX') && DOING_AJAX) ? true: false;

        if(empty($posts) || (self::check_delayed_batch_process('create-post-embeddings') && !$doing_ajax)){
            return false;
        }

        $post_data = array();
        $count = 0;
        $limit = self::get_api_rate_limits('text-embedding-3-large');
        foreach($posts as $post){
            // exit if we've been at this for more than 20 seconds or we've managed to pull down 50,000 posts
            if(microtime(true) - $time > 20 || $count >= self::$batch_limit || $doing_ajax && $count >= self::$concurrency){
                break;
            }

            $id = ($post->type . '_' . $post->id);
            $content = self::get_clean_embedding_post_content($post);
            
            // if there's no content to process
            if(empty($content)){
                // mark it as complete and don't waste time on it
                self::save_empty_post_data($id, self::$model, self::$purpose);
                continue;
            }
            
            $query = self::count_tokens($content, self::$model);
            if($token_size + $query < $limit){
                $token_size += $query;
                $post_data[$id] = $content;
            }else{
                break;
            }

            $count++;
        }

        if(self::$ai_service_connected){
            self::live_query_linkwhisper_ai_results($post_data, 'embeddings');
        }
    }

    /**
     * Analyzes a list of sentences that we're confident are related to their target posts to find the best anchors
     * TODO: Refactor this so the sentence processing indexes words and builds sentences more naturally instead of leaning so hard on delimiters.
     * Soooo much better idea on how to fix this! Just need more time to do it!
     **/
    public static function assess_post_sentence_anchors($phrase_data, $origin_post_id = ''){
        $time = microtime(true);
//        $posts = self::get_all_batch_process_posts('assess-sentence-anchors', 4); // todo setup batching later
        self::$purpose = 'assess-sentence-anchors';
        self::$model = Wpil_Settings::getChatGPTVersion('assess-sentence-anchors');
        $token_size = 0;
        $doing_ajax = true || (defined('DOING_AJAX') && DOING_AJAX) ? true: false;

        if(empty($phrase_data) || (self::check_delayed_batch_process('assess-sentence-anchors') && !$doing_ajax) || empty($origin_post_id)){
            return null;
        }

        $bits = explode('_', $origin_post_id);
        self::$origin_post = new Wpil_Model_Post($bits[1], $bits[0]);

        $ind = 0;
        $process_data = array();
        $count = 0;
        $instruction_size = 600;
        $limit = self::get_api_rate_limits(self::$model, $doing_ajax); // TODO: Setup batch processing!
        $already_processed = self::get_processed_anchor_sentences(self::$origin_post, true); //Currently only tracking sentences... not the sentence + targets that they might point to

        $queued = get_transient('wpil_queued_up_ai_sentences');
        if(!empty($queued)){
            foreach($queued as $pid => $sentences){
                $bits = explode('_', $pid);
                $queue_post = new Wpil_Model_Post($bits[1], $bits[0]);
                foreach($sentences as $sentence => $bool){
                    self::log_processed_anchor_sentence($sentence, $queue_post);
                    unset($queued[$pid][$sentence]);
                    if(empty($queued[$pid])){
                        unset($queued[$pid]);
                    }
                }
            }
        }else{
            $queued = array();
        }

        foreach($phrase_data as $sentence => $data){ // sentence == text && target_data == array(pid, post title, keywords) 
            // normalize the sentence to make sure that we're consistent
            //$sentence = self::normalize_whitespace($sentence);

            foreach($data as $target_data){
                // exit if we've been at this for more than 20 seconds or we've managed to hit the limit of posts we can process
                if(microtime(true) - $time > 20 || $count >= self::$batch_limit || $doing_ajax && $count >= self::$concurrency){
                    break;
                }

                $sentence_id = md5($sentence);
                $sentence_post_id = md5($sentence . '|||' . $target_data[0]); // we need to be able to identify the sentence and the post that it's pointing to, so use the custom id

                if(isset($already_processed[$sentence_id])){
                    continue;
                }
                // add the sentence id to the queue list
                if(!isset($queued[$target_data[0]])){
                    $queued[$origin_post_id] = array();
                }
                $queued[$origin_post_id][$sentence_id] = true;

                if( isset($process_data[$ind]) && 
                    !empty($process_data[$ind]) &&
                    ($instruction_size + array_reduce($process_data[$ind], 
                        function($count, $chunk) use ($instruction_size){ 
                            return $count += (self::count_tokens(wp_slash((string) wp_json_encode($chunk)), self::$model)); 
                    }) > 7800 || count($process_data[$ind]) > 1)
                )
                {
                    $ind++;
                }

                // make sure there's no bad stuff in the title or keywords
                $post_title = self::trim_text_to_token_limit(mb_ereg_replace("\n", '', mb_ereg_replace('(([a-zA-Z\-_0-9]+="[^"]*")+?[\s]?)', '', strip_tags($target_data[1], '<h1><h2><h3><h4><h5><h6><title><ul><ol><li>'))), self::$model, 7800);
                $keywords = self::trim_text_to_token_limit(mb_ereg_replace("\n", '', mb_ereg_replace('(([a-zA-Z\-_0-9]+="[^"]*")+?[\s]?)', '', strip_tags($target_data[2], '<h1><h2><h3><h4><h5><h6><title><ul><ol><li>'))), self::$model, 7800);

                $content = ['meta_id' => $sentence_post_id, 'words' => '', 'sentence' => $sentence, 'post_title' => $post_title, 'keywords' => $keywords, 'match_score' => 0, 'free_form' => '', 'rando' => time()];
                $question = wp_slash((string) wp_json_encode($content));
                $query = $instruction_size + self::count_tokens($question, self::$model);
                if($token_size + $query < $limit){
                    $token_size += $query;
                    $process_data[$ind][] = $content;
                    self::$anchor_assessment_ids[$sentence_post_id] = array(
                        'sentence_id' => $sentence_id,
                        'sentence' => $sentence,
                        'pid' => $target_data[0]
                    );
                }else{
                    break;
                }

                $count++;
            }
        }

        if(!empty($process_data)){
            foreach($process_data as $key => $dat){
                $process_data[$key] = wp_slash((string) wp_json_encode($dat));
            }
        }

        // if we have sentences to process
        if(!empty($queued)){
            // save them here in case there's an error
            set_transient('wpil_queued_up_ai_sentences', $queued, DAY_IN_SECONDS * 7);
        }

        if(self::$ai_service_connected){
            self::live_query_linkwhisper_ai_results($process_data, 'completions');
        }

        // if we have no process data... We must be finished!
        return empty($process_data) || !self::$ai_service_connected ? true: false;
    }

    public static function get_all_batch_process_posts($database = '', $process = 0){
        $posts = array();
        $count = 0;

        if(empty($database) || empty($process)){
            return $posts;
        }

        // get all the posts that have already been processed
        $inserted = self::get_inserted_post_data($database);

        // get all of the post data for known batches which are being processed
        $batched = (defined('DOING_AJAX') && DOING_AJAX) ? array(): self::get_batch_log_data(false, $process);

        // get all of the post and term ids that we're set to process
        $post_ids = Wpil_Report::get_all_post_ids('ai');
        $term_ids = Wpil_Report::get_all_term_ids();

        // if there are posts currently in a batch process
        if(!empty($batched)){
            // go over each process
            foreach($batched as $batch){
                // decompress the specific post data
                $dat = Wpil_Toolbox::json_decompress($batch->batch_data);
                // if that worked
                if(!empty($dat)){
                    // go over each post in the record
                    foreach($dat as $d){
                        // and if it's not in the 'completed' list
                        if(!isset($inserted[$d])){
                            // add it
                            $inserted[$d] = true;
                        }
                    }
                }
            }
        }

        if(!empty($post_ids)){
            foreach($post_ids as $post_id){
                if($count >= self::$batch_limit){ // todo: think about if we should not be limiting the batch size here. It should be no negative, but something to think about
                    break;
                }

                $id = 'post_' . $post_id;
                if(!isset($inserted[$id])){
                    $posts[] = new Wpil_Model_Post($post_id);
                    $count++;
                }
            }
        }

        if(!empty($term_ids) && $count < self::$batch_limit){
            foreach($term_ids as $term_id){
                if($count >= self::$batch_limit){
                    break;
                }

                $id = 'term_' . $term_id;
                if(!isset($inserted[$id])){
                    $posts[] = (new Wpil_Model_Post($term_id, 'term'));
                    $count++;
                }
            }
        }
        
        return $posts;
    }

    /**
     * Saves the post summary data from the batch process response. TODO: add product saving!
     * @return int Returns the total number of processed and inserted posts
     **/
    public static function save_site_post_summaries($data = array()){
        global $wpdb;
        $summary_table = $wpdb->prefix . 'wpil_ai_post_data';

        if(empty($data)){
            return 0;
        }

        if(!is_array($data)){
            $data = array($data);
        }

        $total_count = 0;
        $count = 0;
        $inserted = self::get_inserted_post_data('post-summarizing');
        $insert_query = "INSERT INTO {$summary_table} (post_id, post_type, data_type, summary, process_time, model_version) VALUES ";
        $summary_data = array();
        $place_holders = array();
        $total = count($data);
        $limit = 1000;
        foreach($data as $key => $dat){
            $total_count++;
            $dat = self::decode($dat);

            if( empty($dat) ||                              // if there's no data
                !isset(                                     // or we don't have all the data from OAI that we need
                    $dat->response,
                    $dat->custom_id,
                    $dat->response->status_code,
                    $dat->response->body,
                    $dat->response->body->choices,
                    $dat->response->body->choices[0],
                    $dat->response->body->choices[0]->message,
                    $dat->response->body->choices[0]->message->content,
                    $dat->response->body->model) || 
                (int)$dat->response->status_code !== 200 || // or this wasn't a success
                isset($inserted[$dat->custom_id])           // or we've already saved the post
            ){
                continue;
            }

            $results = self::decode($dat->response->body->choices[0]->message->content); // the response is supposed to be in JSON, keyed to the "keywords" index

            if(!empty($results) && isset($results->results) && !empty($results->results)){
                $results = $results->results;
            }

            if(empty($results) || !is_array($results) || !isset($results[0]->summary) || empty($results[0]->summary)){
                if(!empty($results) && isset($results->summary) && !empty($results->summary)){
                    if(is_string($results->summary)){
                        $summary = trim(sanitize_text_field(trim($results->summary)));
                    }elseif(is_object($results->summary) || is_array($results->summary)){
                        $summary = sanitize_text_field(json_encode($results));
                    }else{
                        continue;
                    }
                }elseif(!empty($results) && (is_object($results) || is_array($results))){
                    $summary = json_encode(array_map('sanitize_text_field', (array)$results)); // if the response is an object, format it so we can catch it in the results
                }else{
                    continue;
                }
            }else{
                $summary = trim(sanitize_text_field(trim($results[0]->summary)));
            }

            $ids = explode('_', $dat->custom_id);
            array_push(
                $summary_data, 
                $ids[1],
                $ids[0],
                (($ids[0] === 'post') ? 1: 0),
                $summary,
                time(),
                $dat->response->body->model
            );
            $place_holders[] = "('%d', '%s', '%d', '%s', '%d', '%s')";

            // if we've hit the limit
            if($count > $limit || ($key + 1) >= $total){
                // assemble the insert
                $insert = ($insert_query . implode(', ', $place_holders));
                $insert = $wpdb->prepare($insert, $summary_data);
                // insert the data
                $wpdb->query($insert);
                // reset the data variables
                $summary_data = [];
                $place_holders = [];
                $count = 0;
            }

            $count++;
        }

        // if we still have data that hasn't been inserted
        if(!empty($summary_data) && !empty($place_holders)){
            // assemble the insert
            $insert = ($insert_query . implode(', ', $place_holders));
            $insert = $wpdb->prepare($insert, $summary_data);
            // and insert the data
            $wpdb->query($insert);
        }

        // return the total number of posts processed
        return $total_count;
    }

    /**
     * Saves the embedding data from the batch process response
     * @return int Returns the total number of processed and inserted posts
     **/
    public static function save_site_embeddings($data = array()){
        global $wpdb;
        $embedding_table = $wpdb->prefix . 'wpil_ai_embedding_data';

        if(empty($data)){
            return 0;
        }

        if(!is_array($data)){
            $data = array($data);
        }

        $total_count = 0;
        $count = 0;
        $inserted = self::get_inserted_post_data('create-post-embeddings');
        $insert_query = "INSERT INTO {$embedding_table} (post_id, post_type, data_type, embed_data, process_time, model_version) VALUES ";
        $embedding_data = array();
        $place_holders = array();
        $total = count($data);
        $limit = 1000;
        foreach($data as $key => $dat){
            $total_count++;
            $dat = self::decode($dat);

            if( empty($dat) ||                              // if there's no data
                !isset(                                     // or we don't have all the data from OAI that we need
                    $dat->response,
                    $dat->custom_id,
                    $dat->response->status_code,
                    $dat->response->body,
                    $dat->response->body->data,
                    $dat->response->body->data[0],
                    $dat->response->body->data[0]->embedding,
                    $dat->response->body->model) || 
                (int)$dat->response->status_code !== 200 || // or this wasn't a success
                isset($inserted[$dat->custom_id])           // or we've already saved the post
            ){
                continue;
            }

            $ids = explode('_', $dat->custom_id);
            array_push(
                $embedding_data, 
                $ids[1],
                $ids[0],
                (($ids[0] === 'post') ? 1: 0),
                Wpil_Toolbox::json_compress($dat->response->body->data[0]->embedding, true),
                time(),
                $dat->response->body->model
            );
            $place_holders[] = "('%d', '%s', '%d', '%s', '%d', '%s')";

            // if we've hit the limit
            if($count > $limit || ($key + 1) >= $total){
                // assemble the insert
                $insert = ($insert_query . implode(', ', $place_holders));
                $insert = $wpdb->prepare($insert, $embedding_data);
                // insert the data
                $wpdb->query($insert);
                // reset the data variables
                $embedding_data = [];
                $place_holders = [];
                $count = 0;
            }

            $count++;
        }

        // if we still have data that hasn't been inserted
        if(!empty($embedding_data) && !empty($place_holders)){
            // assemble the insert
            $insert = ($insert_query . implode(', ', $place_holders));
            $insert = $wpdb->prepare($insert, $embedding_data);
            // and insert the data
            $wpdb->query($insert);
        }

        // return the total number of posts processed
        return $total_count;
    }

    /**
     * Saves an empty dataset for a post with no content so we can process it and check it off the list
     * @return int Returns the total number of processed and inserted posts
     **/
    public static function save_empty_post_embedding($id = '', $model = ''){
        global $wpdb;
        $embedding_table = $wpdb->prefix . 'wpil_ai_embedding_data';

        if(empty($id)){
            return 0;
        }

        $bits = explode('_', $id);

        if(empty($bits) || !isset($bits[0], $bits[1])){
            return 0;
        }

        $post_id = $bits[1];
        $post_type = $bits[0];
        $post = new Wpil_Model_Post((int)$post_id, $post_type);
        $current_model = self::$model;

        // If the post still has usable content, don't save the empty marker. Let it come around for another try.
        if(empty(self::$model)){
            self::$model = !empty($model) ? $model: Wpil_Settings::getChatGPTVersion('create-post-embeddings');
        }

        $content = self::get_clean_embedding_post_content($post);
        self::$model = $current_model;

        if(!empty($content)){
            return 0;
        }

        // check to make sure that the post isn't already saved
        if(!empty($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $embedding_table WHERE `post_id` = %d AND `post_type` = %s LIMIT 1", $post_id, $post_type)))){
            return 1;
        }

        // create the "empty" embedding calculation
        $data = [-0.0011765664, -0.034535293, -0.057125367, 0.02252959, -0.07118746, -0.014417427, 0.018023673, 0.14273782, 0.0019250326, 0.027806656, -0.051893663, 0.050804984, -0.05035137, 0.026551653, -0.0033775487, -0.003103489, -0.054796804, 0.0131699825, -0.056218136, 0.03662193, 0.035442524, -0.02615852, -0.12410932, 0.061268393, 0.02331586, -0.07366723, -0.024298694, 0.011975461, -0.03163215, 0.0028124189, 0.05062354, 0.056278616, -0.003301946, 0.052226316, -0.030346906, 0.011605008, -0.01905187, -0.06610696, -0.010047593, 0.026869183, -0.02131995, -0.0042224084, -0.036198553, 0.038527112, -0.0027689473, 0.043758817, 0.035714693, -0.0022794202, 0.013555557, 0.015574147, -0.037408195, -0.06096598, -0.01537002, -0.0027122453, -0.03758964, -0.04067423, 0.025326889, -0.09997695, -0.006762658, -0.04354713, 0.016965237, -0.0013901439, -0.023996282, 0.021274587, 0.019248437, 0.0005570971, 0.0046306625, -0.031511188, 0.033779267, 0.09695285, 0.017207164, 0.01994398, 0.019581089, 0.06574407, 0.0153549, -0.040039167, 0.031571668, 0.021501396, -0.012625644, 0.014939085, 0.005435831, -0.06919155, -0.029711844, 0.053829093, -0.022937845, 0.004388734, -0.00711043, -0.23987211, 0.025508337, 0.022816882, 0.026899425, 0.010607053, 0.061842974, -0.022665676, 0.03867832, -0.037196506, -0.03870856, 0.005352668, -0.08370726, -0.0040334016, 0.061540563, -0.02987817, 0.050925948, 0.011068229, -0.02871389, 0.040281095, -0.026506292, 0.035200596, -0.01930892, 0.0074657626, -0.013956251, -0.019565968, -0.035442524, -0.0092084035, -0.053556923, 0.008013882, -0.03589614, -0.053617403, -0.0312995, -0.021864288, 0.042821344, -0.07263903, -0.013797485, 0.03099709, -0.005946149, -0.014341824, -0.06634889, -0.03214625, -0.08346533, 0.03263011, -0.031450704, -0.011234554, -0.04750871, 0.04581521, -0.019550847, -0.045210388, -0.038950488, 0.063143335, -0.084493525, -0.053375475, -0.06117767, 0.015997522, 0.011899858, 0.002109314, -0.030815642, -0.039071452, 0.038950488, -0.006486708, 0.010304642, -0.03786181, 0.057125367, -0.010387805, 0.016299933, -0.016194088, 0.036954578, 0.016904755, 0.020563923, -0.059635375, 0.01820512, 0.057911634, -0.014470348, 0.006921423, -0.0683448, 0.030195702, -0.036863856, -0.02847196, 0.021486275, -0.014886163, 0.023225136, 0.03934362, -0.043153998, 0.0683448, 0.047206298, -0.008724547, 0.009782984, 0.097860076, -0.0051182997, 0.014765199, 0.05942369, -0.0066719344, 0.09477549, 0.026385328, 0.025961952, 0.007087749, -0.0106524145, -0.015135651, -0.009760303, 0.060119234, -0.034565534, -0.09864634, -0.0007446862, -0.07868724, -0.0075224643, -0.09017885, 0.06265948, -0.021985253, -0.013427032, 0.008550661, -0.018053913, 0.013910889, 0.024601104, 0.028230032, 0.028124189, -0.11691195, 0.046480514, -0.043940265, -0.019233316, 0.015725352, -0.0018758909, 0.05863742, -0.024434779, -0.08818294, -0.031148294, -0.024706949, -0.0072124936, 0.1143717, -0.07421157, -0.011771333, 0.016345294, 0.007991201, -0.0010820631, -0.009442772, 0.049686067, 0.06695371, -0.051561013, -0.044030987, 0.02479767, 0.06519973, 0.026113158, 0.016738428, -0.07729615, -0.02934895, -0.031692635, 0.07094553, -0.04896028, -0.0063657435, 0.040825434, -0.018386565, 0.071550354, 0.06265948, -0.020337114, 0.012398835, -0.030150339, -0.05032113, 0.049686067, -0.040613748, -0.047750637, -0.046843406, -0.0069592246, -0.031239018, 0.02984793, 0.012550041, 0.084493525, 0.014750078, 0.04155122, 0.022741279, -0.033718783, 0.025190806, -0.015294418, -0.013411911, 0.05975634, 0.06483684, 0.028305635, 0.03867832, 0.01849241, 0.012119106, -0.048718352, -0.07971544, -0.006637913, -0.051349323, 0.06265948, -0.0046835844, -0.016753549, -0.017328128, 0.042851586, 0.024873273, -0.019959101, 0.020291753, 0.010629733, -0.0027443764, -0.007741712, 0.009246205, 0.0690101, -0.026506292, -0.03641024, -0.0051069595, -0.081892796, -0.01735837, -0.03961579, -0.0035155236, -0.041460495, -0.016133606, 0.04327496, 0.06380864, -0.021561878, 0.036228795, 0.020367356, 0.00072484044, -0.050169922, 0.008512859, 0.08364678, -0.0575185, 0.01880994, -0.060179714, 0.07094553, 0.04642003, 0.09804153, 0.020881454, -0.006868501, 0.07269952, 0.047659915, -0.06574407, -0.016103366, -0.021970132, -0.054252468, -0.0318136, -0.0016056114, -0.02761009, 0.022317905, -0.029802566, 0.03834567, -0.040281095, -0.0984649, 0.023784596, 0.08134846, 0.022378387, -0.020049825, -0.021153623, 0.00938229, 0.0092235245, -0.057669707, -0.013805045, 0.008384335, 0.04267014, 0.045452315, -0.10257769, -0.012013262, 0.011476483, -0.01026684, -0.045694247, -0.066167444, 0.009835905, 0.0380735, -0.030543473, -0.042035077, -0.035714693, 0.079533994, -0.021607239, -0.016315054, -0.0015895459, 0.0020904134, -0.07439301, -0.029091902, -0.031087812, 0.0910256, -0.026854064, 0.012035943, -0.01451571, 0.035170358, -0.013706761, -0.015430503, 0.00003139282, 0.07935255, 0.062115144, -0.051923905, -0.08364678, -0.04155122, 0.019747414, -0.01310194, 0.022378387, -0.120298944, 0.05897007, -0.00032934407, -0.00883795, -0.08388871, -0.04705509, 0.06005875, -0.020594163, -0.0043282523, -0.039676275, -0.047357503, 0.0426399, 0.017963191, 0.026037555, 0.05996803, -0.12096425, -0.0016235671, -0.03870856, 0.029273348, -0.003046787, -0.059363205, -0.0074960035, -0.020246392, 0.025523458, 0.07312289, -0.019702053, -0.0609055, -0.008656505, -0.04635955, 0.09628754, 0.016481379, -0.07439301, -0.019278677, -0.009147922, 0.011536965, -0.05996803, -0.012988537, 0.019883499, 0.035230838, 0.010720457, -0.053587165, -0.011597447, 0.03816422, -0.051288843, 0.043577373, 0.027141353, 0.013449713, 0.017963191, 0.07463494, -0.04155122, 0.05721609, -0.025478095, -0.0112043135, 0.0038670758, -0.04723654, 0.11310157, 0.038043257, -0.028683648, -0.03529132, -0.021501396, -0.07663085, 0.0210629, 0.0056437384, -0.008611143, -0.03786181, -0.04233749, 0.012345914, -0.061026465, -0.0058818865, 0.022711039, -0.008467497, -0.0072654155, -0.011234554, 0.0312995, -0.014379625, 0.05325451, -0.008633823, -0.048294976, 0.0013334418, 0.025175685, 0.002956064, -0.036833614, -0.0443334, -0.026687738, 0.0031809818, 0.033204686, 0.059937786, 0.16185017, 0.009132801, -0.009132801, 0.034837704, 0.024963997, 0.06278045, 0.0066530337, 0.037438437, -0.00015144158, -0.039373863, -0.00910256, -0.034232885, -0.008701866, 0.08364678, -0.025478095, -0.023013448, -0.0040334016, -0.032116007, -0.042216524, 0.036561444, -0.01735837, -0.0015904909, -0.08044123, -0.014349384, -0.03018058, -0.03016546, -0.023270497, 0.05271017, 0.008679185, 0.038466632, 0.018885544, -0.023361221, -0.0318136, 0.062115144, -0.037740845, -0.012119106, 0.045028944, 0.029258229, 0.0020563921, -0.011801574, -0.00020554473, 0.016058004, 0.051319085, 0.006006631, 0.022060854, 0.09241669, -0.030906366, -0.036500964, -0.07911062, 0.047085334, 0.016859392, -0.037105784, 0.07463494, 0.04699461, -0.029651362];

        $wpdb->insert($embedding_table, [
            'post_id' => $post_id,
            'post_type' => $post_type,
            'data_type' => (($post_type === 'post') ? 1: 0),
            'embed_data' => Wpil_Toolbox::json_compress($data, true),
            'is_empty' => 1,
            'process_time' => time(),
            'model_version' => $model
        ]);

        // return the total number of posts processed
        return !empty($wpdb->insert_id) ? 1: 0;
    }

    /**
     * Checks to make sure that we've created embeddings for all the available posts
     **/
    public static function has_completed_post_embeddings(){
        global $wpdb;
        $table = $wpdb->prefix . 'wpil_ai_embedding_data';

        $ids = array();
        $data = $wpdb->get_results("SELECT `post_id`, `post_type` FROM {$table}");
        $not_processed = array();

        if(!empty($data)){
            foreach($data as $dat){
                if(empty($dat) || !isset($dat->post_id, $dat->post_type) || empty($dat->post_id) || empty($dat->post_type)){
                    continue;
                }
                $id = $dat->post_type . '_' . $dat->post_id;
                $ids[$id] = true;
            }
        }

        if(!empty($ids)){
            $post_ids = Wpil_Report::get_all_post_ids('ai');
            $term_ids = Wpil_Report::get_all_term_ids();

            if(!empty($post_ids)){
                foreach($post_ids as $p_id){
                    $id = 'post_' . $p_id;

                    if(!isset($ids[$id])){
                        $not_processed[] = $id;
                    }

                }
            }

            if(!empty($term_ids)){
                foreach($term_ids as $t_id){
                    $id = 'term_' . $t_id;

                    if(!isset($ids[$id])){
                        $not_processed[] = $id;
                    }

                }
            }
        }

        return empty($not_processed); // return true on empty so we know that there are no more posts to process
    }

    /**
     * Checks to make sure that we've created embedding calculations for all the available posts.
     * Only checks to see if all the embedding data has been used for calculations.
     **/
    public static function has_completed_post_embedding_calculations(){
        global $wpdb;
        $embedding_table    = $wpdb->prefix . 'wpil_ai_embedding_data';
        $calculation_table  = $wpdb->prefix . ((Wpil_Settings::use_ai_embedding_calculation_v2()) ? 'wpil_ai_embedding_calculation_data_v2': 'wpil_ai_embedding_calculation_data');

        $ids = array();
        $embedding_data     = $wpdb->get_results("SELECT `post_id`, `post_type` FROM {$embedding_table}");
        $calculation_data   = (Wpil_Settings::use_ai_embedding_calculation_v2()) ? self::get_completed_embedding_calc_v2_posts(): $wpdb->get_results("SELECT `post_id`, `post_type`, `calc_index` FROM {$calculation_table}");
        $last_embedding_index = self::get_last_embedding_index();

        if(!empty($embedding_data)){
            foreach($embedding_data as $dat){
                if(empty($dat) || !isset($dat->post_id, $dat->post_type) || empty($dat->post_id) || empty($dat->post_type)){
                    continue;
                }
                $id = $dat->post_type . '_' . $dat->post_id;
                $ids[$id] = true;
            }
        }

        if(!empty($calculation_data)){
            foreach($calculation_data as $dat){
                if( empty($dat) || 
                    !isset($dat->post_id, $dat->post_type) || 
                    empty($dat->post_id) || 
                    empty($dat->post_type) || 
                    (isset($dat->calc_index) && $dat->calc_index < $last_embedding_index))
                {
                    continue;
                }
                $id = $dat->post_type . '_' . $dat->post_id;
                if(isset($ids[$id])){
                    unset($ids[$id]);
                }
            }
        }

        return empty($ids); // return true on empty so we know that there are no more posts to process
    }

    /**
     * Saves the products found during our search of the site posts
     * @return int Returns the total number of processed and inserted posts
     **/
    public static function save_site_products($data = array()){
        global $wpdb;
        $product_table = $wpdb->prefix . 'wpil_ai_product_data';

        if(empty($data)){
            return 0;
        }

        if(!is_array($data)){
            $data = array($data);
        }

        $total_count = 0;
        $count = 0;
        $inserted = self::get_inserted_post_data('product-detecting');
        $insert_query = "INSERT INTO {$product_table} (post_id, post_type, data_type, products, product_count, process_time, model_version) VALUES ";
        $product_data = array();
        $place_holders = array();
        $total = count($data);
        $limit = 1000;
        foreach($data as $key => $dat){
            $total_count++;
            $dat = self::decode($dat);

            if( empty($dat) ||                              // if there's no data
                !isset(                                     // or we don't have all the data from OAI that we need
                    $dat->response,
                    $dat->custom_id,
                    $dat->response->status_code,
                    $dat->response->body,
                    $dat->response->body->choices,
                    $dat->response->body->choices[0],
                    $dat->response->body->choices[0]->message,
                    $dat->response->body->choices[0]->message->content,
                    $dat->response->body->model) || 
                (int)$dat->response->status_code !== 200 || // or this wasn't a success
                isset($inserted[$dat->custom_id])           // or we've already saved the post
            ){
                continue;
            }

            $results = self::decode($dat->response->body->choices[0]->message->content); // the response is supposed to be in JSON, keyed to the "keywords" index

            if(!empty($results) && isset($results->results) && !empty($results->results)){
                $results = $results->results;
            }

            if(empty($results) || !is_array($results) || !isset($results[0]->products) || empty($results[0]->products)){
                if(!empty($results) && isset($results->products) && !empty($results->products)){
                    if(is_string($results->products)){
                        $products = array_map('sanitize_text_field', array_unique(explode(',', $results->products)));
                    }elseif(is_array($results->products) || is_object($results->products)){
                        $products = array_map('sanitize_text_field', array_unique((array)$results->products));
                    }else{
                        continue;
                    }
                    
                }else{
                    continue;
                }
            }else{
                $products = array_map('sanitize_text_field', array_unique(explode(',', $results[0]->products)));
            }

            $no_products = array_search('no-products', $products);
            if(false !== $no_products){
                unset($products[$no_products]);
            }

            $ids = explode('_', $dat->custom_id);
            array_push(
                $product_data, 
                $ids[1],
                $ids[0],
                (($ids[0] === 'post') ? 1: 0),
                Wpil_Toolbox::json_compress($products),
                count($products),
                time(),
                $dat->response->body->model
            );
            $place_holders[] = "('%d', '%s', '%d', '%s', '%d', '%d', '%s')";

            // if we've hit the limit
            if($count > $limit || ($key + 1) >= $total){
                // assemble the insert
                $insert = ($insert_query . implode(', ', $place_holders));
                $insert = $wpdb->prepare($insert, $product_data);
                // insert the data
                $wpdb->query($insert);
                // reset the data variables
                $product_data = [];
                $place_holders = [];
                $count = 0;
            }

            $count++;
        }

        // if we still have data that hasn't been inserted
        if(!empty($product_data) && !empty($place_holders)){
            // assemble the insert
            $insert = ($insert_query . implode(', ', $place_holders));
            $insert = $wpdb->prepare($insert, $product_data);
            // and insert the data
            $wpdb->query($insert);
        }

        // return the total number of posts processed
        return $total_count;
    }

    /**
     * Saves the products found during our search of the site posts
     * @return int Returns the total number of processed and inserted posts
     **/
    public static function save_site_keywords($data = array()){
        global $wpdb;
        $keyword_table = $wpdb->prefix . 'wpil_ai_keyword_data';
        $max_keywords = Wpil_Settings::get_ai_keyword_count_max();

        if(empty($data)){
            return 0;
        }
        
        if(!is_array($data)){
            $data = array($data);
        }

        $total_count = 0;
        $count = 0;
        $inserted = self::get_inserted_post_data('keyword-detecting');
        $insert_query = "INSERT INTO {$keyword_table} (post_id, post_type, data_type, keywords, keyword_count, process_time, model_version) VALUES ";
        $keyword_data = array();
        $place_holders = array();
        $total = count($data);
        $limit = 1000;
        foreach($data as $key => $dat){
            $total_count++;
            $dat = self::decode($dat);

            if( empty($dat) ||                              // if there's no data
                !isset(                                     // or we don't have all the data from OAI that we need
                    $dat->response,
                    $dat->custom_id,
                    $dat->response->status_code,
                    $dat->response->body,
                    $dat->response->body->choices,
                    $dat->response->body->choices[0],
                    $dat->response->body->choices[0]->message,
                    $dat->response->body->choices[0]->message->content,
                    $dat->response->body->model) || 
                (int)$dat->response->status_code !== 200 || // or this wasn't a success
                isset($inserted[$dat->custom_id])           // or we've already saved the post
            ){
                continue;
            }

            $results = self::decode($dat->response->body->choices[0]->message->content); // the response is supposed to be in JSON, keyed to the "keywords" index

            if(!empty($results) && isset($results->results) && !empty($results->results)){
                $results = $results->results;
            }
            if(empty($results) || !is_array($results) || !isset($results[0]->keywords) || empty($results[0]->keywords)){
                if(!empty($results) && isset($results->keywords) && !empty($results->keywords)){
                    if(is_array($results->keywords) || is_object($results->keywords)){
                        $kwrds = (array) $results->keywords;
                    }else{
                        $kwrds = explode(',', $results->keywords);
                    }
                    $keywords = array_map('sanitize_text_field', array_unique($kwrds));
                }else{
                    continue;
                }
            }else{
                $keywords = array_map('sanitize_text_field', array_unique(explode(',', $results[0]->keywords)));
            }

            $no_keywords = array_search('no-keywords', $keywords);
            if(false !== $no_keywords){
                unset($keywords[$no_keywords]);
            }

            // if there are more keywords available than the user's limit
            if(count($keywords) > $max_keywords){
                // make sure all of the keywords are unique and trim to fit
                $keywords = array_slice(array_unique($keywords), 0, $max_keywords);
            }

            $ids = explode('_', $dat->custom_id);
            array_push(
                $keyword_data, 
                $ids[1],
                $ids[0],
                (($ids[0] === 'post') ? 1: 0),
                Wpil_Toolbox::json_compress($keywords),
                count($keywords),
                time(),
                $dat->response->body->model
            );
            $place_holders[] = "('%d', '%s', '%d', '%s', '%d', '%d', '%s')";

            // if we've hit the limit
            if($count > $limit || ($key + 1) >= $total){
                // assemble the insert
                $insert = ($insert_query . implode(', ', $place_holders));
                $insert = $wpdb->prepare($insert, $keyword_data);
                // insert the data
                $wpdb->query($insert);
                // reset the data variables
                $keyword_data = [];
                $place_holders = [];
                $count = 0;
            }

            $count++;
        }

        // if we still have data that hasn't been inserted
        if(!empty($keyword_data) && !empty($place_holders)){
            // assemble the insert
            $insert = ($insert_query . implode(', ', $place_holders));
            $insert = $wpdb->prepare($insert, $keyword_data);
            // and insert the data
            $wpdb->query($insert);
        }

        // return the total number of posts processed
        return $total_count;
    }

    /**
     * Saves empty data in the AI tables so we can process posts without content
     **/
    public static function save_empty_post_data($id = '', $model = '', $purpose = ''){
        global $wpdb;
        $summary_table = $wpdb->prefix . "wpil_ai_post_data";
        $product_table = $wpdb->prefix . "wpil_ai_product_data";
        $keyword_table = $wpdb->prefix . "wpil_ai_keyword_data";
        
        if(empty($id)){
            return 0;
        }

        $bits = explode('_', $id);

        if(empty($bits)){
            return 0;
        }

        $post_id = $bits[1];
        $post_type = $bits[0];

        if(empty($purpose) || $purpose === 'create-post-embeddings'){
            self::save_empty_post_embedding($id, $model);
        }
        // TODO: Uncomment if we ever implement post summaries
/*
        if(empty($purpose) || $purpose === 'post-summarizing' || false !== strpos($purpose, 'summary')){
            if(empty($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$summary_table} WHERE `post_id` = %d AND `post_type` = %s LIMIT 1", $post_id, $post_type)))){
                $wpdb->insert($summary_table, [
                    'post_id' => $post_id,
                    'post_type' => $post_type,
                    'data_type' => (($post_type === 'post') ? 1: 0),
                    'summary' => '',
                    'process_time' => time(),
                    'model_version' => $model
                ]);
            }
        }*/

        if(empty($purpose) || false !== strpos($purpose, 'product')){
            if(empty($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$product_table} WHERE `post_id` = %d AND `post_type` = %s LIMIT 1", $post_id, $post_type)))){
                $wpdb->insert($product_table, [
                    'post_id' => $post_id,
                    'post_type' => $post_type,
                    'data_type' => (($post_type === 'post') ? 1: 0),
                    'products' => Wpil_Toolbox::json_compress(array()),
                    'product_count' => 0,
                    'process_time' => time(),
                    'model_version' => $model
                ]);
            }
        }

        if(empty($purpose) || false !== strpos($purpose, 'keyword')){
            if(empty($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$keyword_table} WHERE `post_id` = %d AND `post_type` = %s LIMIT 1", $post_id, $post_type)))){
                $wpdb->insert($keyword_table, [
                    'post_id' => $post_id,
                    'post_type' => $post_type,
                    'data_type' => (($post_type === 'post') ? 1: 0),
                    'keywords' => Wpil_Toolbox::json_compress(array()),
                    'keyword_count' => 0,
                    'process_time' => time(),
                    'model_version' => $model
                ]);
            }
        }

        // return the total number of posts processed
        return !empty($wpdb->insert_id) ? 1: 0;
    }

    /**
     * Saves the embedding data for the sentences for a single post.
     * @param Wpil_Model_Post $post
     * @param array $data The embedding data for the post. 
     **/
    public static function save_single_post_embedding_data($post, $data = array()){
        global $wpdb;
        $embedding_table = $wpdb->prefix . 'wpil_ai_embedding_phrase_data';

        if(empty($post) || !is_a($post, 'Wpil_Model_Post') || empty($data)){
            return 0;
        }

        if(!is_array($data)){
            $data = array($data);
        }

        $model = Wpil_Settings::getChatGPTVersion('create-post-embeddings');
        $dimension_count =  0;
        $compressed_data = array();
        foreach($data as $phrase => $dat){
            $compressed_data[$phrase] = $dat[0]->embedding;
            $dimension_count = count($dat[0]->embedding);
        }

        $insert_query = "INSERT INTO {$embedding_table} (post_id, post_type, data_type, post_phrase_id, embed_data, no_data, process_time, model_version, dimension_count) VALUES ";
        $embedding_data = array(
            $post->id,
            $post->type,
            (($post->type === 'post') ? 1: 0),
            Wpil_Toolbox::create_post_content_id($post),
            Wpil_Toolbox::json_compress($compressed_data),
            ((empty($compressed_data)) ? 1: 0),
            time(),
            $model,
            $dimension_count
        );
        $place_holders[] = "('%d', '%s', '%d', '%s', '%s', '%d', '%d', '%s', '%d')";
        
        // assemble the insert
        $insert = ($insert_query . implode(', ', $place_holders));
        $insert = $wpdb->prepare($insert, $embedding_data);
        // and insert the data
        $wpdb->query($insert);

        // return the total number of posts processed
        return (!empty($wpdb->last_error)) ? 1 : 0;
    }

    /**
     * Saves an empty dataset for a post with no content so we can process it and check it off the list
     * @return int Returns the total number of processed and inserted posts
     **/
    public static function save_empty_phrase_embedding($id = '', $model = ''){
        global $wpdb;
        $embedding_table = $wpdb->prefix . 'wpil_ai_embedding_phrase_data';

        if(empty($id)){
            return 0;
        }

        $bits = explode('_', $id);

        if(empty($bits)){
            return 0;
        }

        $post_id = $bits[1];
        $post_type = $bits[0];

        // check to make sure that the post isn't already saved
        if(!empty($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $embedding_table WHERE `post_id` = %d AND `post_type` = %s LIMIT 1", $post_id, $post_type)))){
            return 1;
        }

        // create the "empty" embedding calculation
        $data = array();

        $wpdb->insert($embedding_table, [
            'post_id' => $post_id,
            'post_type' => $post_type,
            'data_type' => (($post_type === 'post') ? 1: 0),
            'embed_data' => Wpil_Toolbox::json_compress($data),
            'no_data' => 1,
            'process_time' => time(),
            'model_version' => $model
        ]);

        // return the total number of posts processed
        return !empty($wpdb->insert_id) ? 1: 0;
    }

    /**
     * Saves AI generated suggestions to their own table.
     * @return int Returns the total number of processed and inserted suggestions
     **/
    public static function save_ai_suggestion_words($data = array()){
        global $wpdb;
        $anchor_table = $wpdb->prefix . 'wpil_ai_suggested_anchors';
        $enable_notes = false;

        if(empty($data) || empty(self::$origin_post)){
            return 0;
        }
        
        if(!is_array($data)){
            $data = array($data);
        }

        $total_count = 0;
        $count = 0;
        $insert_query = "INSERT INTO {$anchor_table} (post_id, post_type, data_type, sentence_post_id, sentence_id, suggestion_words, notes, target_id, target_type, target_data_type, link_score, ignore_suggestion, process_time, model_version) VALUES ";
        $inserted = self::get_processed_anchor_sentences(self::$origin_post, true);
        $suggestion_data = array();
        $place_holders = array();
        $limit = 1000;
        foreach($data as $key => $dat){
            $total_count++;
            $dat = self::decode($dat);

            if( empty($dat) ||                              // if there's no data
                !isset(                                     // or we don't have all the data from OAI that we need
                    $dat->response,
                    $dat->custom_id,
                    $dat->response->status_code,
                    $dat->response->body,
                    $dat->response->body->choices,
                    $dat->response->body->choices[0],
                    $dat->response->body->choices[0]->message,
                    $dat->response->body->choices[0]->message->content,
                    $dat->response->body->model) || 
                (int)$dat->response->status_code !== 200 // or this wasn't a success
            ){
                continue;
            }

            $results = self::decode($dat->response->body->choices[0]->message->content); // the response is supposed to be in JSON, keyed to the "keywords" index

            if(!empty($results) && isset($results->results) && !empty($results->results)){
                $results = $results->results;
            }

            if(empty($results)){
                continue;
            }

            // get the processing transient
            $queued = get_transient('wpil_queued_up_ai_sentences');
            $total = count($data);
            foreach($results as $res){
                // if we can get the sentence id information from our tracker
                if(isset(self::$anchor_assessment_ids[$res->meta_id])){
                    $sentence_post_id = $res->meta_id; // we need to be able to identify the sentence and the post that it's pointing to, so use the custom id
                    $sentence_id = self::$anchor_assessment_ids[$res->meta_id]['sentence_id']; // we also need to be able to id the sentence within the post quickly
                    $sentence = self::$anchor_assessment_ids[$res->meta_id]['sentence']; // get the real sentencce!
                    $pid = self::$anchor_assessment_ids[$res->meta_id]['pid'];
                }elseif(false !== strpos($res->meta_id, '_')){
                    // if we can't try our best with the return data
                    $sentence_post_id = md5($res->sentence . '|||' . $res->meta_id); // we need to be able to identify the sentence and the post that it's pointing to, so use the custom id
                    $sentence_id = md5($res->sentence); // we also need to be able to id the sentence within the post quickly
                    $sentence = $res->sentence;
                    $pid = $res->meta_id;
                }else{
                    // come up with a way of marking the post completed, because there's no way to tell if we're done or not...
                    $sentence_post_id = null;
                }

                //$id = ($sentence . '|||' . $pid);


                // skip this sentence if we've already processed it
                if(isset($inserted[$sentence_id])){
                    continue;
                }

                // log the sentence's id
                self::log_processed_anchor_sentence($sentence, self::$origin_post);

                // unset the sentence from the processing transient
                if(isset($queued[$pid], $queued[$pid][$sentence_id])){
                    unset($queued[$pid][$sentence_id]);
                }

                $target_ids = explode('_', $pid);

                if(empty($target_ids)){
                    continue;
                }

                $target_post = new Wpil_Model_Post($target_ids[1], $target_ids[0]);

                if(empty($res->words) || !isset($res->words)){
                    $res->words = 'no-words';
                }

                foreach($res as $ind => $value){
                    if(empty($res->$ind) || !isset($res->$ind)){
                        $res->$ind = '';
                    }
                    $res->$ind = sanitize_text_field($value);
                }

                $match_score = (isset($res->match_score) && !empty($res->match_score)) ? (int)$res->match_score: 0;

                array_push(
                    $suggestion_data, 
                    self::$origin_post->id,
                    self::$origin_post->type,
                    ((self::$origin_post->type === 'post') ? 1: 0),
                    (!empty($sentence_post_id) ? $sentence_post_id: null),
                    $sentence_id,
                    Wpil_Toolbox::json_compress($res->words),
                    ($enable_notes) ? $res->free_form: '', //Wpil_Toolbox::json_compress($res->free_form): '',
                    $target_post->id,
                    $target_post->type,
                    (($target_post->type === 'post') ? 1: 0),
                    $match_score,
                    ($match_score < 5) ? 1: 0,
                    time(),
                    $dat->response->body->model
                );

                $place_holders[] = "('%d', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%d', '%d', '%s')";

                // if we've hit the limit
                if($count > $limit || ($key + 1) >= $total){
                    // assemble the insert
                    $insert = ($insert_query . implode(', ', $place_holders));
                    $insert = $wpdb->prepare($insert, $suggestion_data);
                    // insert the data
                    $wpdb->query($insert);
                    // reset the data variables
                    $suggestion_data = [];
                    $place_holders = [];
                    $count = 0;
                }

                $count++;
            }

            // if we still have data that hasn't been inserted
            if(!empty($suggestion_data) && !empty($place_holders)){
                // assemble the insert
                $insert = ($insert_query . implode(', ', $place_holders));
                $insert = $wpdb->prepare($insert, $suggestion_data);
                // and insert the data
                $wpdb->query($insert);
            }

            if(!empty($queued)){
                set_transient('wpil_queued_up_ai_sentences', $queued, DAY_IN_SECONDS * 7);
            }else{
                delete_transient('wpil_queued_up_ai_sentences');
            }
        }

        // return the total number of phrases processed
        return $total_count;
    }

    /**
     * Saves AI generated LINING suggestions to their own table.
     * TOTALLY DIFFERENT FROM THE LAST IMPLEMENTATION!!!
     * @return int Returns the total number of processed and inserted suggestions
     **/
    public static function save_ai_linking_suggestions($data = array(), $process_key = ''){
        global $wpdb;
        $linking_table = $wpdb->prefix . 'wpil_ai_linking';

        if(empty($data)){
            return 0;
        }
        
        if(!is_array($data)){
            $data = array($data);
        }

        if(empty($process_key)){
            if(!empty(self::$active_linking_process_key)){
                $process_key = self::$active_linking_process_key;
            }else{
                $process_key = get_option('wpil_ai_linking_process_key', '');
            }
        }
        $process_key = is_string($process_key) ? sanitize_text_field($process_key) : '';
        $orphan_only_targets = self::is_orphan_fix_process_key($process_key);

        $valid_count = 0;
        $count = 0;
        $cols = [   'post_id', // cause I'm sick of long strings!
                    'post_type',
                    'data_type',
                    'target_id',
                    'target_type',
                    'target_data_type',
                    'sentence_text',
                    'sentence_id',
                    'sentence_with_anchor_text',
                    'ai_relation_score',
                    'ignored',
                    'process_key',
                    'process_time',
                    'model_version'
                ];
        $insert_query = "INSERT INTO {$linking_table} (" . implode(',', $cols) .") VALUES ";
        $suggestion_data = array();
        $place_holders = array();
        $total = count($data);
        $limit = 1000;
        $ai_relation_data_cache = array();
        foreach($data as $key => $dat){
            $unwrapped = self::unwrap_linking_batch_item($dat);
            if(!$unwrapped['ok'] || empty($unwrapped['custom_id'])){
                continue;
            }

            $results = $unwrapped['results'];
            $model   = $unwrapped['model'];

            if(!empty($results) && isset($results->results) && !empty($results->results)){
                $results = $results->results;
            }elseif(!empty($results) && isset($results[0]) && !empty($results[0])){
                $results = $results[0];
            }

            if(empty($results)){
                continue;
            }

            $source_post_bits = explode('_', $unwrapped['custom_id']);
            if(!isset($source_post_bits[0], $source_post_bits[1])){
                continue;
            }

            $source_post_type = sanitize_text_field($source_post_bits[0]);
            $source_post_id = (int)$source_post_bits[1];
            $source_data_type = (($source_post_type === 'post') ? 1: 0);
            $source_pid = $source_post_type . '_' . $source_post_id;
            if(!isset($ai_relation_data_cache[$source_pid])){
                $ai_relation_data_cache[$source_pid] = self::get_embedding_relatedness_data($source_post_id, $source_post_type);
            }

            $target_data = self::parse_linking_target($results);
            if($orphan_only_targets && !self::is_orphaned_target_for_ai_suggestion((int)$target_data['id'], (string)$target_data['type'])){
                continue;
            }

            $target_post = new Wpil_Model_Post((int)$target_data['id'], $target_data['type']);
            $target_pid = $target_post->get_pid();

            $ai_relation_score = 0;
            if(!empty($ai_relation_data_cache[$source_pid]) && !empty($target_pid) && isset($ai_relation_data_cache[$source_pid]->$target_pid) && !empty($ai_relation_data_cache[$source_pid]->$target_pid)){
                $ai_relation_score = (float)$ai_relation_data_cache[$source_pid]->$target_pid;
            }

            $sentence_text = wp_kses($results->sentence_text, 'post');
            $sentence_with_anchor_text = wp_kses($results->sentence_with_anchor_text, 'post');
            $sentence_id = (!empty($sentence_text)) ? md5($sentence_text) : '';
            $usable_suggestion = (!empty($sentence_text) && !empty($sentence_with_anchor_text));

            array_push(
                $suggestion_data, 
                $source_post_id,
                $source_post_type,
                $source_data_type,
                $target_data['id'],
                $target_data['type'],
                $target_data['data_type'],
                $sentence_text,
                $sentence_id,
                $sentence_with_anchor_text,
                $ai_relation_score,
                $usable_suggestion ? 0: 1,
                $process_key,
                time(),
                $model
            );
            if($usable_suggestion){
                $valid_count++;
            }

            $place_holders[] = "('%d', '%s', '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%f', '%d', '%s', '%d', '%s')";

            // if we've hit the limit
            if($count > $limit || ($key + 1) >= $total){
                // assemble the insert
                $insert = ($insert_query . implode(', ', $place_holders));
                $insert = $wpdb->prepare($insert, $suggestion_data);
                // insert the data
                $wpdb->query($insert);
                // reset the data variables
                $suggestion_data = [];
                $place_holders = [];
                $count = 0;
            }

            $count++;
        }

        // if we still have data that hasn't been inserted
        if(!empty($suggestion_data) && !empty($place_holders)){
            // assemble the insert
            $insert = ($insert_query . implode(', ', $place_holders));
            $insert = $wpdb->prepare($insert, $suggestion_data);
            // and insert the data
            $wpdb->query($insert);
        }

        // return the total number of suggestions processed
        return $valid_count;
    }

    public static function unwrap_linking_batch_item($item){
        $out = array(
            'ok'        => false,
            'model'     => '',
            'custom_id' => '',
            'results'   => array(),
        );

        if(empty($item)){
            return $out;
        }

        // Decode if it's a JSON string
        if(!is_object($item)){
            $item = self::decode($item);
        }

        if(empty($item) || !is_object($item)){
            return $out;
        }

        // Basic required wrapper fields
        if(
            !isset($item->response) ||
            !isset($item->response->status_code) ||
            (int)$item->response->status_code !== 200 ||
            !isset($item->response->body) ||
            !isset($item->response->body->choices) ||
            empty($item->response->body->choices) ||
            !isset($item->response->body->choices[0]) ||
            !isset($item->response->body->choices[0]->message) ||
            !isset($item->response->body->choices[0]->message->content)
        ){
            return $out;
        }

        $out['custom_id'] = isset($item->custom_id) ? (string)$item->custom_id : '';
        $out['model']     = isset($item->response->body->model) ? (string)$item->response->body->model : '';

        $content = $item->response->body->choices[0]->message->content;
        if(empty($content)){
            return $out;
        }

        // Content is JSON-as-string
        $decoded = self::decode($content);
        if(empty($decoded)){
            return $out;
        }

        // Normalize "results"
        $results = null;

        if(is_object($decoded) && isset($decoded->results)){
            $results = $decoded->results;
        } else {
            // allow raw array/object without results key
            $results = $decoded;
        }

        // If single object, wrap into array
        if(is_object($results)){
            $results = array($results);
        }

        if(!is_array($results) || empty($results)){
            return $out;
        }

        $out['ok'] = true;
        $out['results'] = $results;

        return $out;
    }

    public static function save_ai_outbound_linking_suggestions($live_response, $source_post = null, $process_key = ''){
        global $wpdb;
        $linking_table = $wpdb->prefix . 'wpil_ai_linking';

        // Source post fallback to the static origin post if it's set
        if(empty($source_post) && !empty(self::$origin_post)){
            $source_post = self::$origin_post;
        }

        if(empty($source_post) || !is_object($source_post) || empty($source_post->id)){
            return 0;
        }

        if(empty($live_response)){
            return 0;
        }

        $unwrapped = self::unwrap_linking_live_completion($live_response);
        if(!$unwrapped['ok']){
            return 0;
        }

        $results = $unwrapped['results'];
        $model   = $unwrapped['model'];

        $cols = array(
            'post_id',
            'post_type',
            'data_type',
            'target_id',
            'target_type',
            'target_data_type',
            'sentence_text',
            'sentence_id',
            'sentence_with_anchor_text',
            'ai_relation_score',
            'ignored',
            'process_time',
            'model_version',
            'process_key'
        );

        $insert_query = "INSERT INTO {$linking_table} (" . implode(',', $cols) . ") VALUES ";

        $suggestion_data = array();
        $place_holders   = array();

        $count = 0;
        $limit = 1000;

        $source_post_id   = (int) $source_post->id;
        $source_post_type = !empty($source_post->type) ? (string)$source_post->type : 'post';
        $data_type        = (($source_post_type === 'post') ? 1 : 0);

        // Get AI relation data for the source post
        $ai_relation_data = self::get_embedding_relatedness_data($source_post->id, $source_post->type);

        foreach($results as $r){
            $target_data = self::parse_linking_target($r);
            $target_id = (int) $target_data['id'];
            $target_type = $target_data['type'];

            $sentence_text = wp_kses((string)$r->sentence_text, 'post');
            $sentence_id = (!empty($sentence_text)) ? md5($sentence_text) : '';
            $sentence_with = wp_kses((string)$r->sentence_with_anchor_text, 'post');

            // Calculate ai_relation_score the same way as insert_links_into_link_table
            $ai_relation_score = 0;
            if(!empty($ai_relation_data)){
                $target_post = new Wpil_Model_Post($target_id, $target_type);
                $pid = $target_post->get_pid();
                if(isset($ai_relation_data->$pid) && !empty($ai_relation_data->$pid)){
                    $ai_relation_score = $ai_relation_data->$pid;
                }
            }

            $ignored = (!empty($sentence_text) && !empty($sentence_with)) ? 0 : 1;

            array_push(
                $suggestion_data,
                $source_post_id,
                $source_post_type,
                $data_type,
                $target_id,
                $target_type,
                $target_data['data_type'],
                $sentence_text,
                $sentence_id,
                $sentence_with,
                $ai_relation_score,
                $ignored,
                time(),
                $model,
                $process_key
            );

            $place_holders[] = "('%d','%s','%d','%d','%s','%d','%s','%s','%s','%f','%d','%d','%s','%s')";
            $count++;

            // Flush chunk
            if($count >= $limit){
                $insert = $insert_query . implode(', ', $place_holders);
                $insert = $wpdb->prepare($insert, $suggestion_data);
                $wpdb->query($insert);

                $suggestion_data = array();
                $place_holders   = array();
                $count = 0;
            }
        }

        // Final flush!
        if(!empty($suggestion_data) && !empty($place_holders)){
            $insert = $insert_query . implode(', ', $place_holders);
            $insert = $wpdb->prepare($insert, $suggestion_data);
            $wpdb->query($insert);
        }

        return count($results);
    }

    /**
     * Unwraps for the OUTBOUND suggestions
     **/
    public static function unwrap_linking_live_completion($item){
        $out = array(
            'ok'      => false,
            'model'   => '',
            'results' => array(),
        );

        if(empty($item)){
            return $out;
        }

        // Decode if it's a JSON string
        if(!is_object($item)){
            $item = self::decode($item);
        }

        if(empty($item) || !is_object($item)){
            return $out;
        }

        // Live chat completion shape: choices[0].message.content
        if(
            !isset($item->choices) ||
            empty($item->choices) ||
            !isset($item->choices[0]) ||
            !isset($item->choices[0]->message) ||
            !isset($item->choices[0]->message->content)
        ){
            return $out;
        }

        $out['model'] = isset($item->model) ? (string)$item->model : '';

        $content = $item->choices[0]->message->content;
        if(empty($content) || !is_string($content)){
            return $out;
        }

        // content is JSON-as-string: {"results":[...]}
        $decoded = self::decode($content);
        if(empty($decoded)){
            return $out;
        }

        $results = null;

        if(is_object($decoded) && isset($decoded->results)){
            $results = $decoded->results;
        } else {
            // Allow raw array/object without results key
            $results = $decoded;
        }

        if(is_object($results)){
            $results = array($results);
        }

        if(!is_array($results) || empty($results)){
            return $out;
        }

        // Validate basic required fields for each item
        $clean = array();
        foreach($results as $r){
            if(!is_object($r)){
                continue;
            }
            if(!isset($r->sentence_text) || !isset($r->sentence_with_anchor_text) || !isset($r->target_id)){
                continue;
            }
            $clean[] = $r;
        }

        if(empty($clean)){
            return $out;
        }

        $out['ok'] = true;
        $out['results'] = $clean;

        return $out;
    }

    /**
     * Gets the ids for all the suggested sentences that we've processed with AI for this post
     * @param Wpil_Model_Post $post
     * @param bool $reindex Should we reindex the results so we can quickly search for a specific id using isset?
     **/
    public static function get_post_suggestion_anchor_ids($post = array(), $reindex = false){
        global $wpdb;
        $anchor_table = $wpdb->prefix . 'wpil_ai_suggested_anchors';

        if(empty($post) || !is_a($post, 'Wpil_Model_Post')){
            return false;
        }

        $ids = $wpdb->get_results($wpdb->prepare("SELECT `sentence_post_id` FROM {$anchor_table} WHERE `post_id` = %s AND `post_type` = %d", $post->id, $post->type));
    
        if(!empty($ids) && $reindex){
            $reindexed = array();
            foreach($ids as $id){
                $reindexed[$id->sentence_post_id] = true;
            }
            $ids = $reindexed;
        }

        return $ids;
    }

    /**
     * Gets the AI processed sentences that are good enough to be viable
     * @param Wpil_Model_Post $post
     **/
    public static function get_ai_post_suggestion_sentences($post = array(), $decode = true){
        global $wpdb;
        $anchor_table = $wpdb->prefix . 'wpil_ai_suggested_anchors';

        if(empty($post) || !is_a($post, 'Wpil_Model_Post')){
            return false;
        }

        $suggestion_data = $wpdb->get_results($wpdb->prepare("SELECT `post_id`, `post_type`, `sentence_post_id`, `sentence_id`, `suggestion_words`, `notes`, `target_id`, `target_type` FROM {$anchor_table} WHERE `post_id` = %s AND `post_type` = %d AND `ignore_suggestion` = 0", $post->id, $post->type));
        $suggestions = array();
        if(!empty($suggestion_data)){
            foreach($suggestion_data as $dat){
                if(!isset($suggestions[$dat->sentence_id])){
                    $suggestions[$dat->sentence_id] = array();
                }

                if($decode){
                    $dat->suggestion_words = Wpil_Toolbox::json_decompress($dat->suggestion_words);
                    if(!empty($dat->notes)){
                        $dat->notes = Wpil_Toolbox::json_decompress($dat->notes);
                    }
                }

                $suggestions[$dat->sentence_id][] = $dat;
            }
        }

        return $suggestions;
    }

    /**
     * Deletes the phrase embedding data for a specific post
     * @param Wpil_Model_Post $post
     **/
    public static function clear_post_phrase_suggestion_sentences($post = array(), $age = 0){
        global $wpdb;
        $table = $wpdb->prefix . "wpil_ai_suggested_anchors";

        if(empty($post) || !is_a($post, 'Wpil_Model_Post')){
            return true;
        }

        $where = "`post_id` = {$post->id} AND `post_type` = '{$post->type}'";

        if(!empty($age) && is_numeric($age)){
            $age = intval($age);
            $where .= " AND `process_time` < {$age}";
        }

        $wpdb->query("DELETE FROM {$table} WHERE $where");
    }

    /**
     * Inserts processed sentences into the processed anchor sentence table
     * @param string $sentence The sentence that we're planning to log
     * @param Wpil_Model_Post The post object that the sentence belongs to
     **/
    public static function log_processed_anchor_sentence($sentence = '', $post = array()){
        global $wpdb;
        $table = $wpdb->prefix . "wpil_ai_processed_sentences";

        if(empty($post) || !is_a($post, 'Wpil_Model_Post') || empty($sentence)){
            return false;
        }

        if(preg_match('/^[a-f0-9]{32}$/i', $sentence)){
            $sentence_id = $sentence;
        }else{
            $sentence_id = md5($sentence);
        }

        $has_link = false;
        $phrase = Wpil_Suggestion::getPhrasebyId($sentence_id, $post);
        if(!empty($phrase)){
            $has_link = (Wpil_Link::hasLink($phrase->sentence_src));
        }

        $sentences = self::get_processed_anchor_sentences($post);
        if(!in_array($sentence, $sentences)){
            $wpdb->insert($table, array(
                'post_id' => $post->id,
                'post_type' => $post->type,
                'data_type' => (($post->type === 'post') ? 1: 0),
                'has_link' => (($has_link) ? 1: 0),
                'sentence_id' => $sentence_id,
                'process_time' => time()
            ));

            if(!isset(self::$sentence_anchor_cache[$post->get_pid()])){
                self::$sentence_anchor_cache[$post->get_pid()] = array();
            }
            self::$sentence_anchor_cache[$post->get_pid()][] = $sentence;
        }
    }

    /**
     * Gets processed sentence references from the processed anchor sentence table
     * @param Wpil_Model_Post The post object that the sentence belongs to
     **/
    public static function get_processed_anchor_sentences($post = array(), $reindex = false, $ignore_cache = false){
        if(empty($post) || !is_a($post, 'Wpil_Model_Post')){
            return false;
        }

        if(!isset(self::$sentence_anchor_cache[$post->get_pid()]) || $ignore_cache){
            self::load_processed_anchor_sentence_cache($post);
        }

        $ids = self::$sentence_anchor_cache[$post->get_pid()];

        if($reindex && !empty($ids)){
            $reindexed = array();
            foreach($ids as $id){
                $reindexed[$id] = true;
            }
            $ids = $reindexed;
        }

        return $ids;
    }

    /**
     * Loads the sentence cache so we don't have to hit the database every time we want to check a sentence
     * @param Wpil_Model_Post The post object that we're pulling sentences from
     **/
    public static function load_processed_anchor_sentence_cache($post = array()){
        global $wpdb;
        $table = $wpdb->prefix . "wpil_ai_processed_sentences";

        if(empty($post) || !is_a($post, 'Wpil_Model_Post')){
            return false;
        }

        $sentences = $wpdb->get_col($wpdb->prepare("SELECT `sentence_id` FROM {$table} WHERE `post_id` = %d AND `post_type` = %s", $post->id, $post->type));
        $processed = array();
        if(!empty($sentences)){
            foreach($sentences as $sentence){
                $processed[$sentence] = true;
            }

            if(!empty($processed)){
                $processed = array_keys($processed);
            }
        }
        
        self::$sentence_anchor_cache[$post->get_pid()] = $processed;
    }


    /**
     * Clears processed sentence data from the processed anchor sentence table
     * @param Wpil_Model_Post The post object that the sentence belongs to
     **/
    public static function clear_processed_anchor_sentences($post = array(), $age = 0){
        global $wpdb;
        $table = $wpdb->prefix . "wpil_ai_processed_sentences";

        if(empty($post) || !is_a($post, 'Wpil_Model_Post')){
            return false;
        }

        $where = "`post_id` = {$post->id} AND `post_type` = '{$post->type}'";

        if(!empty($age) && is_numeric($age)){
            $age = intval($age);
            $where .= " AND `process_time` < {$age}";
        }

        $wpdb->query("DELETE FROM {$table} WHERE $where");
    }

    /**
     * Gets the post ids for all posts that are currently inserted in one of the databases that relys on OIA batch data.
     * The "ids" are a combination of 'post->type' . '_' . 'post->id' so they can be easily compared to the "custom_id" that was set for the batch item
     * 
     * @param string $database What database should we be checking for posts?
     * @return array The list of all the posts that are currently stored in the database.
     **/
    public static function get_inserted_post_data($database = ''){
        global $wpdb;
        $summarized_posts = $wpdb->prefix . 'wpil_ai_post_data';
        $embedding_data = $wpdb->prefix . 'wpil_ai_embedding_data';
        $product_data = $wpdb->prefix . 'wpil_ai_product_data';
        $keyword_data = $wpdb->prefix . 'wpil_ai_keyword_data';
        $post_ids = array();

        if(empty($database)){
            return $post_ids;
        }

        if($database === 'post-summarizing'){
            $results = $wpdb->get_results("SELECT `post_id`, `post_type` FROM {$summarized_posts}");
        }elseif($database === 'create-post-embeddings'){
            $results = $wpdb->get_results("SELECT `post_id`, `post_type` FROM {$embedding_data}");
        }elseif($database === 'product-detecting'){
            $results = $wpdb->get_results("SELECT `post_id`, `post_type` FROM {$product_data}");
        }elseif($database === 'keyword-detecting'){
            $results = $wpdb->get_results("SELECT `post_id`, `post_type` FROM {$keyword_data}");
        }elseif($database === 'summary-and-product-searching'){
            $results = $wpdb->get_results("SELECT b.post_id, b.post_type FROM {$summarized_posts} a LEFT JOIN {$product_data} b ON a.post_id = b.post_id AND a.data_type = b.data_type WHERE b.post_id > 0");
        }elseif($database === 'summary-and-keyword-searching'){
            $results = $wpdb->get_results("SELECT b.post_id, b.post_type FROM {$summarized_posts} a LEFT JOIN {$keyword_data} b ON a.post_id = b.post_id AND a.data_type = b.data_type WHERE b.post_id > 0");
        }elseif($database === 'product-and-keyword-searching'){
            $results = $wpdb->get_results("SELECT b.post_id, b.post_type FROM {$product_data} a LEFT JOIN {$keyword_data} b ON a.post_id = b.post_id AND a.data_type = b.data_type WHERE b.post_id > 0");
        }elseif($database === 'summary-keyword-and-product-searching'){
            $results = $wpdb->get_results(
                "SELECT b.post_id, b.post_type FROM {$summarized_posts} a 
                    LEFT JOIN {$keyword_data} b ON a.post_id = b.post_id AND a.data_type = b.data_type 
                    LEFT JOIN {$product_data} c ON b.post_id = c.post_id AND b.data_type = c.data_type 
                    WHERE c.post_id > 0");
        }

        if(!empty($results)){
            foreach($results as $result){
                $id = $result->post_type . '_' . $result->post_id;
                $post_ids[$id] = true;
            }
        }

        return $post_ids;
    }

    /**
     * Checks to see how the current batch is doing
     **/
    public static function check_batch_process($batch_id = ''){
        if(empty($batch_id) || !is_string($batch_id)){
            return false;
        }

        $batch = self::decode(self::$ai->retrieveBatch($batch_id));
        $status = 'running';
        if(!empty($batch) && isset($batch->status)){
            switch ($batch->status) {
                case 'failed':
                case 'expired':
                case 'cancelled':
                case 'cancelling':
                    self::delete_batch($batch);
                    $status = 'deleted';
                    break;
                case 'completed':
                    self::mark_batch_complete($batch);
                    $status = 'completed';
                    break;
                default:
            }
        }elseif(!empty($batch) && isset($batch->error) && !empty($batch->error)){
            // if there's an error, assume it's because the batch doesn't exist
            $status = 'deleted';
        }

        return $status;
    }

    /**
     * Adds a completed batch to the list of batches that are ready for processing
     * @param object $batch
     **/
    public static function mark_batch_complete($batch = array()){
        if(empty($batch)){
            return false;
        }

        $batches = get_option('wpil_oai_completed_batch_data', array());
        $listed = false;
        if(!empty($batches)){
            foreach($batches as $key => $dat){
                if(!isset($dat->id) || empty($dat->id)){
                    unset($batches[$key]);
                    continue;
                }

                // if the batch is already logged
                if($dat->id === $batch->id){
                    // replace it since this is more recent
                    $batches[$key] = $batch;
                    // and make a note of it
                    $listed = true;
                }
            }
        }

        // if the batch isn't already logged
        if(!$listed){
            // add it to the complete list
            $batches[] = $batch;
        }
        
        update_option('wpil_oai_completed_batch_data', $batches);

        return true;
    }

    /**
     * Deletes a batch from the OpenAI storage and removes the listing from our cache
     * @param object $batch
     **/
    public static function delete_batch($batch = array()){
        if(empty($batch)){
            return false;
        }

        // temp removing so I can pull down the file!
        if(isset($batch->input_file_id) && !empty($batch->input_file_id)){
            self::$ai->deleteFile($batch->input_file_id);
        }

        if(isset($batch->output_file_id) && !empty($batch->output_file_id)){
            self::$ai->deleteFile($batch->output_file_id);
        }

        if(isset($batch->error_file_id) && !empty($batch->error_file_id)){
            self::$ai->deleteFile($batch->error_file_id);
        }

        if( isset($batch->errors) && !empty($batch->errors) && 
            isset($batch->errors->data) && !empty($batch->errors->data) && 
            isset($batch->metadata) && !empty($batch->metadata) &&
            isset($batch->metadata->purpose) && !empty($batch->metadata->purpose) &&
            is_array($batch->errors->data))
        {
            foreach($batch->errors->data as $dat){
                $delayed = false;
                if(!empty($dat) && isset($dat->code) && !empty($dat->code) && $dat->code === 'token_limit_exceeded'){
                    $delayed = self::delay_batch_process($batch->metadata->purpose);
                }

                if(isset($dat->message) && !empty($dat->message) && empty($delayed)){
                    self::save_system_error_log_data($batch, $dat->message);
                    if(
                        isset($response->error->code) && 
                        !empty($response->error->code) && 
                        ($response->error->code === 'insufficient_quota' || $response->error->code === 'billing_hard_limit_reached'))
                    {
                        update_option('wpil_oai_insufficient_quota_error', '1');
                    }
                }
            }
        }

        self::save_completed_batch_log_entry($batch->id, $batch->status);
        self::delete_batch_log_data($batch->id);
        if(isset($batch->metadata, $batch->metadata->purpose) && !empty($batch->metadata->purpose)){
            self::clear_credit_tracking_task_run('purpose:' . $batch->metadata->purpose);
        }

        $batch_cache = get_option('wpil_oai_batch_data', array());
        $batch_completed = get_option('wpil_oai_completed_batch_data', array());

        if(!empty($batch_cache)){
            foreach($batch_cache as $key => $dat){
                if(isset($dat->id, $batch->id) && $dat->id === $batch->id){
                    unset($batch_cache[$key]);
                }
            }

            update_option('wpil_oai_batch_data', $batch_cache);
        }

        if(!empty($batch_completed)){
            foreach($batch_completed as $key => $dat){
                if(isset($dat->id, $batch->id) && $dat->id === $batch->id){
                    unset($batch_completed[$key]);
                }
            }
            update_option('wpil_oai_completed_batch_data', $batch_completed);
        }
    }

    /**
     * Gets the data from a completed batch process
     **/
    public static function get_batch_data($file_id = ''){
        if(empty($file_id) || !is_string($file_id)){
            return false;
        }

        // retrieve the json data. Is JSONL, so we don't need to decode it
        $file = self::$ai->retrieveFileContent($file_id);
        $decoded = self::decode($file, true); // in fact, if we decode it, there should be an error
        if(!empty($file) && empty($decoded)){
            $file = explode("\n", $file);
            if(!empty($file)){
                return array_filter(array_map('trim', $file));
            }
        }elseif(!empty($file) && !empty($decoded) && isset($decoded->error)){
            return $decoded;
        }elseif(!empty($file) && !empty($decoded) && isset($decoded->id, $decoded->custom_id, $decoded->response) && !empty($decoded->id) && !empty($decoded->custom_id) && !empty($decoded->response) && is_object($decoded)){
            // if there appears to be only one item, wrap it in an array so we can process
            return array($decoded);
        }

        return false;
    }

    /**
     * 
     **/
    public static function live_query_linkwhisper_ai_results($data, $endpoint = ''){
        if(empty($data) || empty(self::$ai_service_connected)){
            return false;
        }

        if($endpoint === 'embeddings'){
            $message_list = array();
            self::$query_ids = array();
            foreach($data as $post_id => $dat){
                if(is_array($dat)){ // we really shouldn't be chunking embedding data...
                    foreach($dat as $chunk_data){
                        $message_list[] = $chunk_data;
                        self::$query_ids[] = $post_id;
                    }
                }else{
                    $message_list[] = $dat;
                    self::$query_ids[] = $post_id;
                }
            }

            $results = self::call_linkwhisper_ai($message_list, '', '', ['dimensions' => Wpil_Settings::get_ai_dimension_limit()]);

            if(!empty($results)){
                foreach($results as $key => $dat){
                    
                    $response = self::decode($dat);
                    if(!empty($response) &&
                        ((isset($response->error) && !empty($response->error)) ||
                        (isset($response->statusCode) && ($response->statusCode > 203 || $response->statusCode < 200)))
                    ){
                        if(isset($response->error)){
                            if(isset($response->error->message) && !empty($response->error->message)){
                                self::$error_message = esc_html($response->error->message);
                            }

                            if(isset($response->error->type)){
                                if($response->error->type === 'invalid_request_error'){
                                    self::$invalid_request = true;
                                    self::track_error($response->error->type);
                                }
                            }

                            if(isset($response->error->code)){
                                if($response->error->code === 'rate_limit_exceeded'){
                                    self::$rate_limited = true;
                                }elseif($response->error->code === 'invalid_prompt'){
            
                                }elseif($response->error->code === 'insufficient_quota' || $response->error->code === 'billing_hard_limit_reached'){
                                    self::$insufficient_quota = true;
                                }elseif($response->error->code === 'invalid_api_key'){
                                    self::$invalid_api_key = true;
                                }
                                
                                self::track_error($response->error->code);
                            }
                        }elseif(self::$ai_service_connected){
                            if(isset($response->body)){
                                if(is_string($response->body)){
                                    $response->body = json_decode($response->body);
                                }

                                if(isset($response->body->error)){
                                    if(isset($response->body->error)){
                                        if($response->body->error === 'User not found'){
                                            self::$user_not_exist = true;
                                        }elseif($response->body->error === 'Insufficient credits'){
                                            self::$insufficient_quota = true;
                                        }elseif($response->body->error === 'Access not valid'){
                                            self::$invalid_api_key = true;
                                        }
                                        
                                        self::track_error($response->body->error);
                                    }
                                }
                            }
                        }

                        // format the data for saving
                        $dat_object = (object)array(
                            'response' => array(
                                'status_code' => 200,
                                'body' => $response,
                                'purpose' => self::$purpose
                            ),
                            'custom_id' => self::$query_ids[$key],
                            'id' => 'live_download'
                        );
    
                        $dat_object = json_encode($dat_object);
                        if(!empty($dat_object)){
                            $dat_object = array($dat_object);
                            self::save_error_log_data('live_download', $dat_object, self::$purpose);
                            // and mark the post as processed if there isn't a temp/quota error
                            if(!self::$insufficient_quota && !self::$rate_limited && !self::$invalid_request && !self::$invalid_api_key && !self::$user_not_exist){
                                self::save_empty_post_data(self::$query_ids[$key], self::$model, self::$purpose);
                            }

                            if(self::$invalid_api_key || self::$user_not_exist){
                                return;
                            }else{
                                continue;
                            }
                        }
                    }
        
                    $dat_object = (object)array(
                        'response' => array(
                            'status_code' => 200,
                            'body' => $response
                        ),
                        'custom_id' => self::$query_ids[$key],
                    );
        
                    $dat_object = json_encode($dat_object);
                    
                    if(!empty($dat_object)){
                        $dat_object = array($dat_object);
                        self::save_site_embeddings($dat_object);
                    }
        
                    self::save_response_tokens($dat, self::$purpose, false, isset(self::$query_ids[$key]) ? self::$query_ids[$key] : '');
                }
            }
        }else{
            $message_list = array();
            self::$query_ids = array();
            foreach($data as $post_id => $dat){
                if(is_array($dat)){
                    foreach($dat as $chunk_data){
                        $message_list[] = $chunk_data;
                        self::$query_ids[] = $post_id;
                        self::$chunked_posts[$post_id] = true;
                    }
                }else{
                    $message_list[] = $dat;
                    self::$query_ids[] = $post_id;
                }
            }

            $chat = self::call_linkwhisper_ai($message_list);
            $merge_data = array();
            foreach($chat as $chat_id => $dat){
                // if there was no response but we did have content to supply
                if(empty($dat) && isset($message_list[$chat_id]) && !empty($message_list[$chat_id])){
                    // if we've already chunked the post
                    if(isset(self::$chunked_posts[self::$query_ids[$chat_id]])){
                        // remove it from the chunked post list
                        self::remove_chunked_post(self::$query_ids[$chat_id]);
                        // save the empty data
                        self::save_empty_post_data(self::$query_ids[$chat_id], self::$model, self::$purpose);
                        // and continue
                        continue;
                    }

                    // assume that we need to chunck the request to get it past OAI
                    self::save_chunked_post(self::$query_ids[$chat_id]);
                    continue;
                }

                $response = self::decode($dat);

                if(!empty($response) && isset(self::$chunked_posts[self::$query_ids[$chat_id]])){
                    $next = $chat_id + 1;
                    $sub_dat = self::decode($response->choices[0]->message->content);

                    // add the response content to the chunk merge data
                    if(!isset($merge_data[self::$query_ids[$chat_id]])){
                        $merge_data[self::$query_ids[$chat_id]] = array(
                            'keywords' => array(),
                            'keyword-count' => 0,
                            'products' => array(),
                            'product-count' => 0
                        );
                    }

                    if(!empty($sub_dat) && isset($sub_dat->results)){
                        if(isset($sub_dat->results->keywords)){ 
                            $merge_data[self::$query_ids[$chat_id]]['keywords'] = array_unique(array_merge($merge_data[self::$query_ids[$chat_id]]['keywords'], explode(',', $sub_dat->results->keywords)));
                            $merge_data[self::$query_ids[$chat_id]]['keyword-count'] = count($merge_data[self::$query_ids[$chat_id]]['keywords']);
                        }
                        if(isset($sub_dat->results->products)){ 
                            $merge_data[self::$query_ids[$chat_id]]['products'] = array_unique(array_merge($merge_data[self::$query_ids[$chat_id]]['products'], explode(',', $sub_dat->results->products)));
                            $merge_data[self::$query_ids[$chat_id]]['product-count'] = count($merge_data[self::$query_ids[$chat_id]]['products']);
                        }
                    }

                    if(
                        isset($chat[$next]) && // if there's another item in the chat
                        isset(self::$query_ids[$next]) && // and we have an id for it
                        isset(self::$chunked_posts[self::$query_ids[$next]]) && // and the next item is chunked
                        self::$query_ids[$chat_id] === self::$query_ids[$next] // and the id is the same as this one
                    ){
                        // note the tokens used for this request
                        self::save_response_tokens($dat, self::$purpose);
                        // and continue on to the next item
                        continue;
                    }

                    // if this is the last item, update the response content
                    $merge_data[self::$query_ids[$chat_id]]['keywords'] = implode(',', $merge_data[self::$query_ids[$chat_id]]['keywords']);
                    $merge_data[self::$query_ids[$chat_id]]['products'] = implode(',', $merge_data[self::$query_ids[$chat_id]]['products']);
                    $response->choices[0]->message->content = json_encode(array('results' => $merge_data[self::$query_ids[$chat_id]]));
                
                    // and remove it from the chunked post list
                    self::remove_chunked_post(self::$query_ids[$chat_id]);
                }

                if(!empty($response) &&
                    ((isset($response->error) && !empty($response->error)) ||
                    (isset($response->statusCode) && ($response->statusCode > 203 || $response->statusCode < 200)))
                ){
                    if(isset($response->error)){
                        if(isset($response->error->message) && !empty($response->error->message)){
                            self::$error_message = esc_html($response->error->message);
                        }

                        if(isset($response->error->type)){
                            if($response->error->type === 'invalid_request_error'){
                                self::$invalid_request = true;
                                self::track_error($response->error->type);
                            }
                        }

                        if(isset($response->error->code)){
                            if($response->error->code === 'rate_limit_exceeded'){
                                self::$rate_limited = true;
                            }elseif($response->error->code === 'invalid_prompt'){
        
                            }elseif($response->error->code === 'insufficient_quota' || $response->error->code === 'billing_hard_limit_reached'){
                                self::$insufficient_quota = true;
                            }elseif($response->error->code === 'invalid_api_key'){
                                self::$invalid_api_key = true;
                            }
                            
                            self::track_error($response->error->code);
                        }
                    }elseif(self::$ai_service_connected){
                        if(isset($response->body)){
                            if(is_string($response->body)){
                                $response->body = json_decode($response->body);
                            }

                            if(isset($response->body->error)){
                                if(isset($response->body->error)){
                                    if($response->body->error === 'User not found'){
                                        self::$user_not_exist = true;
                                    }elseif($response->body->error === 'Insufficient credits'){
                                        self::$insufficient_quota = true;
                                    }elseif($response->body->error === 'Access not valid'){
                                        self::$invalid_api_key = true;
                                    }
                                    
                                    self::track_error($response->body->error);
                                }
                            }
                        }
                    }

                    // format the data for saving
                    $dat_object = (object)array(
                        'response' => array(
                            'status_code' => 200,
                            'body' => $response,
                            'purpose' => self::$purpose
                        ),
                        'custom_id' => self::$query_ids[$chat_id],
                        'id' => 'live_download'
                    );

                    $dat_object = json_encode($dat_object);
                    if(!empty($dat_object)){
                        $dat_object = array($dat_object);
                        self::save_error_log_data('live_download', $dat_object, self::$purpose);
                        // and mark the post as processed if there isn't a temp/quota error
                        if(!self::$insufficient_quota && !self::$rate_limited && !self::$invalid_request && !self::$invalid_api_key && !self::$user_not_exist){
                            self::save_empty_post_data(self::$query_ids[$chat_id], self::$model, self::$purpose);
                        }

                        if(self::$invalid_api_key || self::$user_not_exist){
                            return;
                        }else{
                            continue;
                        }
                    }
                }

                $dat_object = (object)array(
                    'response' => array(
                        'status_code' => 200,
                        'body' => $response
                    ),
                    'custom_id' => self::$query_ids[$chat_id],
                );

                $dat_object = json_encode($dat_object);
                if(!empty($dat_object)){
                    $dat_object = array($dat_object);
                    self::save_site_keywords($dat_object);
//                    self::save_site_post_summaries($dat_object);
                    self::save_site_products($dat_object);
                }

                self::save_response_tokens($dat, self::$purpose);
            }

            // if we have suggestion processing data
            if(!empty(self::$anchor_assessment_ids)){
                // go over it
                foreach(self::$anchor_assessment_ids as $post_sntnce_id => $dat){
                    // and make sure that we've checked off the sentences so we don't get stuck in infinite loops
                    self::log_processed_anchor_sentence($dat['sentence'], self::$origin_post); // if we cut off too soon, and miss some opportunities, we can revisit this...
                }
            }
        }

        // unset any chuncked poasts
        self::$chunked_posts = array();
    }

    /**
     * Gets the embeddings for a specific post, broken down by phrase
     * @param Wpil_Post $post
     **/
    public static function live_query_single_post_embedding_data($post = null){
        if(empty($post) || !is_a($post, 'Wpil_Model_Post') || (empty(self::$ai) && !self::$ai_service_connected)){
            return false;
        }

        $model = Wpil_Settings::getChatGPTVersion('create-post-embeddings');
        $dimensions = Wpil_Settings::get_ai_dimension_limit();
        add_filter('wpil_filter_ignore_linking_tags', function($tags){ return array_merge($tags, ['pre', 'code']);});
        self::$purpose = 'create-post-sentence-embeddings'; // TODO: create link cleanup that will check to see if the links have changed between the processed and new text and will tell the stupid AI if it should reprocessed the posit content.
        $phrases = Wpil_Suggestion::getPhrases($post->getContent(), false, array(), false, array(), ('sentence_text' === Wpil_Suggestion::get_phrase_text_prop()));
        //$phrases = Wpil_Suggestion::getPhrases($post->getContent()); // TODO: Review and see if we need to id text instead of sentence_text
        $message_list = array();

        $phrase_list = array();
        foreach($phrases as $phrase){
            $text = Wpil_Suggestion::get_ai_phrase_text($phrase);
            if(empty($text) || strlen($text) < 3 || Wpil_Word::getWordCount($text, true) < 4){ // only process sentences that are long enough to matter
                continue;
            }
            
            $phrase_list[$text] = true;
        }

        if(empty($phrase_list)){
            self::save_empty_phrase_embedding($post->get_pid(), $model);
            return array();
        }

        if(self::$ai_service_connected){
            foreach($phrase_list as $text => $dat){
                $message_list[] = $text;
                self::$query_ids[] = $post->get_pid(); // get the pid for this post because any errors will be indexed for it
            }

            $results = self::call_linkwhisper_ai($message_list, $model, '', ['dimensions' => $dimensions]);
        }else{
            foreach($phrase_list as $text => $dat){
                $message_list[] = array(
                    "model" => $model,
                    "input" => $text,
                    "dimensions" => $dimensions
                );

                self::$query_ids[] = $post->get_pid(); // get the pid for this post because any errors will be indexed for it
            }

            $args = array(
                'message_list' => $message_list,
            );

            $results = self::$ai->embeddings($args, true);
        }

        $embedding_data = array();
        if(!empty($results)){
            $inds = array_keys($phrase_list);
            foreach($results as $key => $dat){
                $response = self::decode($dat);
                if( !empty($response) &&
                    ((isset($response->error) && !empty($response->error)) ||
                    (isset($response->statusCode) && ($response->statusCode > 203 || $response->statusCode < 200)))
                ){
                    if(isset($response->error)){
                        if(isset($response->error->message) && !empty($response->error->message)){
                            self::$error_message = esc_html($response->error->message);
                        }

                        if(isset($response->error->type)){
                            if($response->error->type === 'invalid_request_error'){
                                self::$invalid_request = true;
                                self::track_error($response->error->type);
                            }
                        }

                        if(isset($response->error->code)){
                            if($response->error->code === 'rate_limit_exceeded'){
                                self::$rate_limited = true;
                            }elseif($response->error->code === 'invalid_prompt'){

                            }elseif($response->error->code === 'insufficient_quota' || $response->error->code === 'billing_hard_limit_reached'){
                                self::$insufficient_quota = true;
                            }elseif($response->error->code === 'invalid_api_key'){
                                self::$invalid_api_key = true;
                            }
                            
                            self::track_error($response->error->code);
                        }
                    }elseif(self::$ai_service_connected){
                        if(isset($response->body)){
                            if(is_string($response->body)){
                                $response->body = json_decode($response->body);
                            }

                            if(isset($response->body->error)){
                                if(isset($response->body->error)){
                                    if($response->body->error === 'User not found'){
                                        self::$user_not_exist = true;
                                    }elseif($response->body->error === 'Insufficient credits'){
                                        self::$insufficient_quota = true;
                                    }elseif($response->body->error === 'Access not valid'){
                                        self::$invalid_api_key = true;
                                    }
                                    
                                    self::track_error($response->body->error);
                                }
                            }
                        }
                    }

                    // format the data for saving
                    $dat_object = (object)array(
                        'response' => array(
                            'status_code' => 200,
                            'body' => $response,
                            'purpose' => self::$purpose
                        ),
                        'custom_id' => isset(self::$query_ids[$key]) ? self::$query_ids[$key]: '', // TODO: Keep an eye on this and make sure that it doesn't allow endless loops
                        'id' => 'live_download'
                    );

                    $dat_object = json_encode($dat_object);
                    if(!empty($dat_object)){
                        $dat_object = array($dat_object);
                        self::save_error_log_data('live_download', $dat_object, self::$purpose);
                        // and mark the post as processed if there isn't a temp/quota error
                        if(!self::$insufficient_quota && !self::$rate_limited && !self::$invalid_request && !self::$invalid_api_key || self::$user_not_exist){
                            //self::save_empty_post_data(self::$query_ids[$key], self::$model, self::$purpose);
                        }

                        if(self::$invalid_api_key || self::$user_not_exist){
                            return;
                        }else{
                            continue;
                        }
                    }
                }

                if(isset($response->data) && !empty($response->data)){
                    $phrase_id = $inds[$key];
                    $embedding_data[$phrase_id] = $response->data;
                }
                self::save_response_tokens($dat, self::$purpose, false, isset(self::$query_ids[$key]) ? self::$query_ids[$key] : '');
            }
        }

        return $embedding_data;
    }

    /**
     * 
     **/
    public static function process_streamed_data($handle_id, $content, $info){
        $skip_streaming = array(
            'create-post-sentence-embeddings'
        );

        // if the current action isn't supposed to be streamed
        if(!empty(self::$purpose) && in_array(self::$purpose, $skip_streaming)){
            // return now
            return false;
        }

        // for the time being, don't process chunked posts
        if(!empty(self::$chunked_posts) && isset(self::$chunked_posts[$handle_id])){
            return false;
        }

        if(empty($content) || empty($info) || empty($info['url'])){
            return false;
        }

        // if the response wasn't successful
        if($info['http_code'] == 429 || $info['http_code'] > 499){
            return true; // return 'true' to mark the post as processed here so it doesn't get processed by the code that checks off failed posts
        }

        if(false !== strpos($info['url'], 'embeddings') || (false !== strpos($info['url'], 'api.linkwhisper.com') && self::$purpose === 'create-post-embeddings')){
            $response = self::decode($content);
            if( !empty($response) && 
                ((isset($response->error) && !empty($response->error)) ||
                (isset($response->statusCode) && ($response->statusCode > 203 || $response->statusCode < 200)))
            ){
                if(isset($response->error)){
                    if(isset($response->error->message) && !empty($response->error->message)){
                        self::$error_message = esc_html($response->error->message);
                    }
                    if(isset($response->error->type)){
                        if($response->error->type === 'invalid_request_error'){
                            self::$invalid_request = true;
                            self::track_error($response->error->type);
                        }
                    }

                    if(isset($response->error->code)){
                        if($response->error->code === 'rate_limit_exceeded'){
                            self::$rate_limited = true;
                        }elseif($response->error->code === 'invalid_prompt'){

                        }elseif($response->error->code === 'insufficient_quota' || $response->error->code === 'billing_hard_limit_reached'){
                            self::$insufficient_quota = true;
                        }elseif($response->error->code === 'invalid_api_key'){
                            self::$invalid_api_key = true;
                        }
                        
                        self::track_error($response->error->code);
                    }
                }elseif(self::$ai_service_connected){
                    if(isset($response->body)){
                        if(is_string($response->body)){
                            $response->body = json_decode($response->body);
                        }

                        if(isset($response->body->error)){
                            if(isset($response->body->error)){
                                if($response->body->error === 'User not found'){
                                    self::$user_not_exist = true;
                                }elseif($response->body->error === 'Insufficient credits'){
                                    self::$insufficient_quota = true;
                                }elseif($response->body->error === 'Access not valid'){
                                    self::$invalid_api_key = true;
                                }
                                
                                self::track_error($response->body->error);
                            }
                        }
                    }
                }

                // format the data for saving
                $dat_object = (object)array(
                    'response' => array(
                        'status_code' => 200,
                        'body' => $response,
                        'purpose' => self::$purpose
                    ),
                    'custom_id' => self::$query_ids[$handle_id],
                    'id' => 'live_download'
                );

                $dat_object = json_encode($dat_object);
                if(!empty($dat_object)){
                    $dat_object = array($dat_object);
                    self::save_error_log_data('live_download', $dat_object, self::$purpose);
                    // and mark the post as processed if there isn't a temp/quota error
                    if( !self::$insufficient_quota && 
                        !self::$rate_limited && 
                        !self::$invalid_request && 
                        !self::$invalid_api_key && 
                        !self::$user_not_exist || 
                        (self::$ai_service_connected) // or if we're using the LW AI service 
                    ){
                        self::save_empty_post_data(self::$query_ids[$handle_id], self::$model, self::$purpose);
                    }
                    return true;
                }
            }

            $dat_object = (object)array(
                'response' => array(
                    'status_code' => 200,
                    'body' => $response
                ),
                'custom_id' => self::$query_ids[$handle_id],
            );

            $dat_object = json_encode($dat_object);
            if(!empty($dat_object)){
                $dat_object = array($dat_object);
                self::save_site_embeddings($dat_object);
            }

            self::save_response_tokens($content, self::$purpose, false, isset(self::$query_ids[$handle_id]) ? self::$query_ids[$handle_id] : '');
        }else{
            // if there was no response but we did have content to supply
            if(empty($content) && isset(self::$query_ids[$handle_id]) && !empty(self::$query_ids[$handle_id])){
                // assume that we need to chunck the request to get it past OAI
                self::save_chunked_post(self::$query_ids[$handle_id]);
                return true;
            }

            $response = self::decode($content);
            if(!empty($response) && 
                ((isset($response->error) && !empty($response->error)) ||
                (isset($response->statusCode) && ($response->statusCode > 203 || $response->statusCode < 200)))
            ){
                if(isset($response->error)){
                    if(isset($response->error->message) && !empty($response->error->message)){
                        self::$error_message = esc_html($response->error->message);
                    }

                    if(isset($response->error->type)){
                        if($response->error->type === 'invalid_request_error'){
                            self::$invalid_request = true;
                            self::track_error($response->error->type);
                        }
                    }

                    if(isset($response->error->code)){
                        if($response->error->code === 'rate_limit_exceeded'){
                            self::$rate_limited = true;
                        }elseif($response->error->code === 'invalid_prompt'){

                        }elseif($response->error->code === 'insufficient_quota' || $response->error->code === 'billing_hard_limit_reached'){
                            self::$insufficient_quota = true;
                        }elseif($response->error->code === 'invalid_api_key'){
                            self::$invalid_api_key = true;
                        }
                        
                        self::track_error($response->error->code);
                    }
                }elseif(self::$ai_service_connected){
                    if(isset($response->body)){
                        if(is_string($response->body)){
                            $response->body = json_decode($response->body);
                        }

                        if(isset($response->body->error)){
                            if(isset($response->body->error)){
                                if($response->body->error === 'User not found'){
                                    self::$user_not_exist = true;
                                }elseif($response->body->error === 'Insufficient credits'){
                                    self::$insufficient_quota = true;
                                }elseif($response->body->error === 'Access not valid'){
                                    self::$invalid_api_key = true;
                                }
                                
                                self::track_error($response->body->error);
                            }
                        }
                    }
                }

                // format the data for saving
                $dat_object = (object)array(
                    'response' => array(
                        'status_code' => 200,
                        'body' => $response,
                        'purpose' => self::$purpose
                    ),
                    'custom_id' => self::$query_ids[$handle_id],
                    'id' => 'live_download'
                );

                $dat_object = json_encode($dat_object);
                if(!empty($dat_object)){
                    $dat_object = array($dat_object);
                    self::save_error_log_data('live_download', $dat_object, self::$purpose);
                    // and mark the post as processed if there isn't a temp/quota error
                    if(!self::$insufficient_quota && !self::$rate_limited && !self::$invalid_request && !self::$invalid_api_key && !self::$user_not_exist){
                        self::save_empty_post_data(self::$query_ids[$handle_id], self::$model, self::$purpose);
                    }
                    return true;
                }
            }

            $dat_object = (object)array(
                'response' => array(
                    'status_code' => 200,
                    'body' => $response
                ),
                'custom_id' => self::$query_ids[$handle_id],
            );

            $dat_object = json_encode($dat_object);
            if(!empty($dat_object)){
                $dat_object = array($dat_object);

                // TODO: make more elegant in the future
                if(self::$purpose === 'assess-sentence-anchors'){
                    self::save_ai_suggestion_words($dat_object);
                }elseif(self::$purpose === 'assess-inbound-links'){
                    self::save_ai_linking_suggestions($dat_object);
                }elseif(self::$purpose === 'assess-outbound-links'){
                    //self::save_ai_linking_suggestions($dat_object); // TODO: configure if we move to mlulltiple process runs
                }else{
                    self::save_site_keywords($dat_object);
                    //self::save_site_post_summaries($dat_object);
                    self::save_site_products($dat_object);
                }
            }

            self::save_response_tokens($content, self::$purpose);
        }

        return true;
    }

    /**
     * Calculates the post embeddings for the posts on the site.
     * Handles all the process running required to make the calculations.
     **/
    public static function calculate_post_embeddings(){
        if(Wpil_Settings::use_ai_embedding_calculation_v2()){
            return self::stepped_calculate_post_embeddings_v2();
        }

        $processed_embeddings = array();

        $large_site = self::get_total_processable_posts() > 4000;
        if($large_site){
            $embedding_data = self::get_post_embedding_data(); // currently pulling all data, will batch in the future
        }else{
            $embedding_data = self::get_post_embedding_data(true); // currently pulling all data, will batch in the future
        }

        $stored_posts = self::get_calculated_embedding_post_ids();

        if(empty($embedding_data)){
            return false;
        }

        $count = 0;
        foreach($embedding_data as $key => $dat){
            if(Wpil_Base::overTimeLimit(15) || Wpil_Toolbox::is_over_memory_limit()){
                break;
            }

            $id = $dat->post_type . '_' . $dat->post_id;

            // if the post is already stored in the embedding table
            if(isset($stored_posts[$id])){
                continue;
            }

            if(!isset($processed_embeddings[$id])){
                $processed_embeddings[$id] = array();
            }

            if($large_site){
                $dat->embed_data = Wpil_Toolbox::json_decompress($dat->embed_data, null, true);
            }
            foreach($embedding_data as $d){
                $sub_id = $d->post_type . '_' . $d->post_id;

                // if the sub item is the main item
                if($id === $sub_id){
                    // skip to the next because we don't need to determine how related the post is to itself
                    continue;
                }

                if(!isset($processed_embeddings[$id]['embeddings'])){
                    $processed_embeddings[$id]['embeddings'] = array();
                    $processed_embeddings[$id]['model_version'] = $dat->model_version;
                }

                if($large_site){
                    $d->embed_data = Wpil_Toolbox::json_decompress($d->embed_data, null, true);
                    gc_collect_cycles();
                }
                $processed_embeddings[$id]['embeddings'][$sub_id] = self::compare_post_embeddings($dat, $d);
            }

            if($count > 100){
                self::save_calculated_embedding_data($processed_embeddings);
                $count = 0;
                $processed_embeddings = [];
            }elseif(Wpil_Toolbox::is_over_memory_limit() && !empty($processed_embeddings)){
                self::save_calculated_embedding_data($processed_embeddings);
                $count++;
                break;
            }

            $count++;
        }

        if(!empty($processed_embeddings)){
            self::save_calculated_embedding_data($processed_embeddings);
        }

        return $count;
    }

    /**
     * Calculates the post embeddings for the posts on the site.
     * Handles all the process running required to make the calculations.
     **/
    public static function stepped_calculate_post_embeddings(){
        if(Wpil_Settings::use_ai_embedding_calculation_v2()){
            return self::stepped_calculate_post_embeddings_v2();
        }

        $processed_embeddings = array();
        // get the latest index
        $last_embedding_index = self::get_last_embedding_index();
        $batch_limit = Wpil_Settings::get_ai_process_limit('create-post-embeddings', true);
        $lowest_ind = 0;
        $saving = array();

        // get a batch of posts that are less than the last index
        $calc_process_posts = self::get_offset_embedding_calc_posts($last_embedding_index, $batch_limit, true);

        // find the lowest index info
        if(!empty($calc_process_posts)){
            foreach($calc_process_posts as $dat){
                $id = $dat->post_type . '_' . $dat->post_id;
                $processed_embeddings[$id] = $dat;
            }
            unset($calc_process_posts);
        }else{
            return false;
        }

        while(!Wpil_Base::overTimeLimit(15)){
            // find the lowest index info
            $lowest_ind = $last_embedding_index;
            foreach($processed_embeddings as $dat){
                if(is_null($dat->calc_index)){
                    $dat->calc_index = 0;
                }

                if(isset($dat->calc_index) && $dat->calc_index < $lowest_ind){
                    $lowest_ind = $dat->calc_index;
                }
            }

            // pull a batch of embedding data that picks up after the last
            $batch_embeddings = self::get_post_embedding_data(true, $lowest_ind, $batch_limit);

            if(empty($batch_embeddings)){
                break;
            }

            $count = 0;
            $saving = array();
            foreach($processed_embeddings as $key => $dat){
                if(Wpil_Base::overTimeLimit(15)){
                    break;
                }

                $id = $dat->post_type . '_' . $dat->post_id;

                if(!isset($processed_embeddings[$id])){
                    $processed_embeddings[$id] = array();
                }

                foreach($batch_embeddings as $d){
                    $sub_id = $d->post_type . '_' . $d->post_id;

                    // if this item already has this embedding data calculated
                    if($processed_embeddings[$id]->calc_index >= $d->embed_index){
                        // skip to the next to save time
                        continue;
                    }

                    // if the sub item is the main item
                    if($id === $sub_id){
                        // tag the embed index so we know it's counted
                        if($processed_embeddings[$id]->calc_index < $d->embed_index){
                            $processed_embeddings[$id]->calc_index = $d->embed_index;
                        }
                        // skip to the next because we don't need to determine how related the post is to itself
                        continue;
                    }

                    if(!isset($processed_embeddings[$id]->calculation) || empty($processed_embeddings[$id]->calculation)){
                        $processed_embeddings[$id]->calculation = array();
                    }

                    $calculation = self::compare_post_embeddings($dat, $d);

                    // if we pass the threshold for minimum relatability
                    if($calculation > 0.40){ // TODO: make into a setting if we have trouble with generating enough suggestions
                        // add it to the list
                        $processed_embeddings[$id]->calculation[$sub_id] = $calculation;
                    }
                    
                    if($processed_embeddings[$id]->calc_index < $d->embed_index){
                        $processed_embeddings[$id]->calc_index = $d->embed_index;
                    }

                    $processed_embeddings[$id]->calc_count += 1;
                }
                $saving[$id] = $processed_embeddings[$id];
                
                // if we're at the memory breakpoint
                if(Wpil_Toolbox::is_over_memory_limit()){
                    // save the data to clear it
                    self::save_calculated_embedding_data($saving, true);
                    $count = 0;
                    $saving = [];
                }

                $count++;
            }

            if(!empty($saving)){
                self::save_calculated_embedding_data($saving, true);
                $saving = [];
            }
        }

        // if we _still_ have data to save
        if(!empty($saving)){
            // save it here
            self::save_calculated_embedding_data($saving, true);
        }

        return true;
    }

    /**
     * 
     **/
    public static function get_last_embedding_index(){
        global $wpdb;
        $table = $wpdb->prefix . 'wpil_ai_embedding_data';

        $index = self::get_last_embedding_id_lock();

        if(!empty($index)){
            return $index;
        }

        $index = $wpdb->get_var("SELECT `embed_index` FROM {$table} ORDER BY `embed_index` DESC LIMIT 1");

        self::set_last_embedding_id_lock($index);

        return (!empty($index)) ? (int)$index: 0;
    }

    private static function get_last_embedding_id_lock(){
        $lock = get_transient('wpil_last_embedding_index_lock');
        return (!empty($lock)) ? (int)$lock: 0;
    }

    private static function set_last_embedding_id_lock($id = null){
        if(empty($id)){
            delete_transient('wpil_last_embedding_index_lock');
        }else{
            set_transient('wpil_last_embedding_index_lock', (int)$id, DAY_IN_SECONDS);
        }
    }

    /**
     * Calculates post embedding relatedness in smaller V2 pages.
     **/
    public static function stepped_calculate_post_embeddings_v2(){
        $batch_limit = Wpil_Settings::get_ai_process_limit('create-post-embeddings', true);
        $page_size = 1000;

        $calc_process_posts = self::get_offset_embedding_calc_posts_v2(0, $batch_limit, true);
        if(empty($calc_process_posts)){
            return false;
        }

        foreach($calc_process_posts as $dat){
            if(Wpil_Base::overTimeLimit(15)){
                break;
            }

            $calc_offset = (!empty($dat->calc_count)) ? (int)$dat->calc_count: 0;

            while(!Wpil_Base::overTimeLimit(15)){
                $batch_embeddings = self::get_post_embedding_data_v2_page($calc_offset, $page_size, true);

                if(empty($batch_embeddings)){
                    break;
                }

                $pages = array();
                foreach($batch_embeddings as $d){
                    $target_type = $d->post_type;
                    $source_id = $dat->post_type . '_' . $dat->post_id;
                    $target_id = $d->post_type . '_' . $d->post_id;

                    if(!isset($pages[$target_type])){
                        $pages[$target_type] = array(
                            'post_id' => $dat->post_id,
                            'post_type' => $dat->post_type,
                            'target_post_type' => $target_type,
                            'starting_id' => $d->post_id,
                            'ending_id' => $d->post_id,
                            'calculation' => array(),
                            'calc_index' => 0,
                            'calc_count' => 0,
                            'model_version' => $dat->model_version,
                        );
                    }

                    if((int)$pages[$target_type]['starting_id'] > (int)$d->post_id){
                        $pages[$target_type]['starting_id'] = $d->post_id;
                    }

                    if((int)$pages[$target_type]['ending_id'] < (int)$d->post_id){
                        $pages[$target_type]['ending_id'] = $d->post_id;
                    }

                    if($source_id !== $target_id){
                        $calculation = self::compare_post_embeddings($dat, $d);

                        if($calculation > 0.40){
                            $pages[$target_type]['calculation'][$target_id] = $calculation;
                        }
                    }

                    if((int)$pages[$target_type]['calc_index'] < (int)$d->embed_index){
                        $pages[$target_type]['calc_index'] = $d->embed_index;
                    }

                    $pages[$target_type]['calc_count'] += 1;
                }

                if(!empty($pages)){
                    self::save_calculated_embedding_data_v2($pages);
                }

                foreach($batch_embeddings as $d){
                    $calc_offset++;
                }

                if($calc_offset >= self::get_total_embedding_data_count()){
                    break;
                }
            }
        }

        return true;
    }

    public static function get_offset_embedding_calc_posts_v2($embedding_index = 0, $limit = 0, $decode = false){
        global $wpdb;
        $embed_table = $wpdb->prefix . 'wpil_ai_embedding_data';
        $calc_table = $wpdb->prefix . 'wpil_ai_embedding_calculation_data_v2';

        $limit = (int)$limit;
        $embedding_index = (int)$embedding_index;

        if(empty($limit)){
            $limit = 1000;
        }

        $total_embeddings = self::get_total_embedding_data_count();

        $data = $wpdb->get_results("SELECT a.post_id, a.post_type, a.data_type, a.embed_data, a.model_version, b.calc_index, b.calc_count FROM
            {$embed_table} a LEFT JOIN (
                SELECT post_id, post_type, MAX(calc_index) AS calc_index, SUM(calc_count) AS calc_count
                FROM {$calc_table}
                GROUP BY post_id, post_type
            ) b ON a.post_id = b.post_id AND a.post_type = b.post_type
            WHERE b.calc_count < {$total_embeddings} OR ISNULL(b.calc_count)
            ORDER BY a.post_type ASC, a.post_id ASC, a.embed_index ASC
            LIMIT {$limit}");

        if($decode && !empty($data)){
            foreach($data as $key => $dat){
                if(isset($dat->embed_data)){
                    $data[$key]->embed_data = Wpil_Toolbox::json_decompress($dat->embed_data, true, true);
                }
            }
        }

        return $data;
    }

    public static function get_post_embedding_data_v2_page($index_offset = 0, $search_limit = 1000, $decode_embeddings = false){
        global $wpdb;
        $table = $wpdb->prefix . 'wpil_ai_embedding_data';

        $embedding_offset = (int)$index_offset;
        $search_limit = (int)$search_limit;

        if(empty($search_limit)){
            $search_limit = 1000;
        }

        $embedding_data = $wpdb->get_results("SELECT * FROM {$table} ORDER BY `post_type` ASC, `post_id` ASC, `embed_index` ASC LIMIT {$search_limit} OFFSET {$embedding_offset}");

        if(!empty($embedding_data) && $decode_embeddings){
            foreach($embedding_data as $key => $data){
                $embedding_data[$key]->embed_data = Wpil_Toolbox::json_decompress($data->embed_data, null, true);
            }
        }

        return (!empty($embedding_data)) ? $embedding_data: array();
    }
    
    /**
     * 
     **/
    public static function get_offset_embedding_calc_posts($embedding_index = 0, $limit = 0, $decode = false){
        global $wpdb;
        $embed_table = $wpdb->prefix . 'wpil_ai_embedding_data';
        $calc_table = $wpdb->prefix . 'wpil_ai_embedding_calculation_data';

        $limit = (int)$limit;
        $embedding_index = (int)$embedding_index;

        if(empty($limit)){
            $limit = 1000;
        }

        $data = $wpdb->get_results("SELECT a.post_id, a.post_type, a.data_type, a.embed_data, a.model_version, b.calculation, b.calc_index, b.calc_count  FROM 
            {$embed_table} a LEFT JOIN {$calc_table} b ON a.post_id = b.post_id AND a.post_type = b.post_type 
            WHERE b.calc_index < {$embedding_index} OR ISNULL(b.calc_index) LIMIT {$limit}");

        if($decode && !empty($data)){
            foreach($data as $key => $dat){
                if(isset($dat->calculation)){
                    $data[$key]->calculation = Wpil_Toolbox::json_decompress($dat->calculation, true);
                }

                if(isset($dat->embed_data)){
                    $data[$key]->embed_data = Wpil_Toolbox::json_decompress($dat->embed_data, true, true);
                }
            }
        }

        return $data;
    }
    
    /**
     * Gets the raw embedding data for psots that we're currently calculating the AI relationship scor fore
     **/
    public static function get_target_embedding_posts($post_ids = array()){
        global $wpdb;
        $table = $wpdb->prefix . 'wpil_ai_embedding_data';
        $target_posts = array();

        if(empty($post_ids)){
            return $target_posts;
        }

        $query = "";
        foreach($post_ids as $type => $post_ids){
            $ids = array_filter(array_map(function($id){ return (int)$id; }, $post_ids));

            if(!empty($ids) && ($type === 'post' || $type === 'term')){
                $ids = implode(',', $ids);
                $query .= !empty($query) ? " OR ": "";
                $query .= "(`post_type` = {$type} AND `post_id` IN ({$ids})) ";
            }
        }

        if(!empty($query)){
            $target_posts = $wpdb->get_results("SELECT * FROM {$table} WHERE {$query}");
        }

        return $target_posts;
    }

    /**
     * @param object $post1
     * @param object $post2
     **/
    public static function compare_post_embeddings($post1 = array(), $post2 = array(), $forbid_cache = false){
        $dimension1_count = count($post1->embed_data);
        $dimension2_count = count($post2->embed_data);

        if($dimension1_count > $dimension2_count){
            $post1->embed_data = self::reduce_embedding_dimensions($post1->embed_data, $dimension2_count);
        }elseif($dimension2_count > $dimension1_count){
            $post2->embed_data = self::reduce_embedding_dimensions($post2->embed_data, $dimension1_count);
        }

        $dot_product = self::get_cached_dot_product($post1, $post2, $forbid_cache);
        $magnitude_a = self::get_cached_magnatude($post1, $forbid_cache);
        $magnitude_b = self::get_cached_magnatude($post2, $forbid_cache);
        $similarity = $dot_product / ($magnitude_a * $magnitude_b);
        return number_format($similarity, 12, '.', '');
    }

    /**
     * @param object $post1
     * @param object $post2
     **/
    public static function get_cached_dot_product($post1 = array(), $post2 = array(), $forbid_cache = false){
        if(isset($post1->sentence)){
            $id = md5($post1->sentence) . '_' . $post2->post_id . '_' . $post2->post_type;
        }elseif(isset($post2->sentence)){
            $id = md5($post2->sentence) . '_' . $post1->post_id . '_' . $post1->post_type;
        }else{
            if($post1->post_id > $post2->post_id){
                $id = ($post1->post_id . '_' . $post1->post_type) . '_' . ($post2->post_id . '_' . $post2->post_type);
            }else{
                $id = ($post2->post_id . '_' . $post2->post_type) . '_' . ($post1->post_id . '_' . $post1->post_type);
            }
        }

        // if we don't have the magnatude cached
        if(!isset(self::$dot_product_cache[$id])){
            $dot_product = 0;
            foreach($post1->embed_data as $key => $p1s){
                if(isset($post2->embed_data[$key])){
                    $dot_product += $p1s * $post2->embed_data[$key];
                }
            }

            // if we're not caching it
            if($forbid_cache){
                // return it here
                return $dot_product;
            }

            // create and cache it
            self::$dot_product_cache[$id] = $dot_product;
        }

        return self::$dot_product_cache[$id];
    }

    /**
     * @param object $embedded_post
     **/
    public static function get_cached_magnatude($embedded_post = array(), $forbid_cache = false){
        $id = (isset($embedded_post->sentence) && !empty($embedded_post->sentence)) ? md5($embedded_post->sentence): $embedded_post->post_type . '_' . $embedded_post->post_id;

        // if we don't have the magnatude cached
        if(!isset(self::$magnatude_cache[$id])){
            // create it
            $magnatude = sqrt(array_sum(array_map(function($x){return $x * $x;}, Wpil_Toolbox::json_decompress($embedded_post->embed_data, null, true))));

            // if we're not supposed to cache it
            if($forbid_cache){
                // return it now
                return $magnatude;
            }

            // otherwise, cache it for future use
            self::$magnatude_cache[$id] = $magnatude;
        }

        return self::$magnatude_cache[$id];
    }


    /**
     * TODO: redescribe
     * Calculates the post embeddings for the posts on the site.
     * Handles all the process running required to make the calculations.
     * 
     * TODO: Make able to take post language status into account
     * 
     * @param Wpil_Model_Post $target_post The post whose sentences we're calculating relations for
     **/
    public static function stepped_calculate_phrase_embeddings($target_post = array(), $return_calculations = false){
        if(empty($target_post) || !is_a($target_post, 'Wpil_Model_Post')){
            return ($return_calculations) ? 0: false;
        }

        // get the latest index
        $last_embedding_index = self::get_last_embedding_index();
        $batch_limit = Wpil_Settings::get_ai_process_limit('create-post-embeddings', true);
        $lowest_ind = 0;
        $completed = false;

        // get a batch of posts that are less than the last index
        $phrase_embeddings = self::get_single_post_embedding_data($target_post, true);
        $calculated_phrase_data = self::get_embedding_calc_phrases($target_post, true, true);

        if(empty($phrase_embeddings)){
            return ($return_calculations) ? 0: false;
        }

        // if there are no sentences in the embedding data, there's nothing to calculate
        if(empty($phrase_embeddings->embed_data) || (!is_array($phrase_embeddings->embed_data) && !is_object($phrase_embeddings->embed_data))){
            return ($return_calculations) ? 0: false;
        }

        if(is_object($phrase_embeddings->embed_data)){
            $phrase_embeddings->embed_data = get_object_vars($phrase_embeddings->embed_data);
        }

        if(empty($calculated_phrase_data)){
            $first_sentence = reset($phrase_embeddings->embed_data);
            if(empty($first_sentence) || !is_array($first_sentence)){
                return ($return_calculations) ? 0: false;
            }

            $calculated_phrase_data = (object) array(
                'post_id' => $target_post->id,
                'post_type' => $target_post->type,
                'data_type' => ($target_post->type === 'post' ? 1: 0),
                'calculation' => array(),
                'calc_index' => array(),
                'calc_count' => 0,
                'process_time' => time(),
                'model_version' => $phrase_embeddings->model_version,
                'dimension_count' => count($first_sentence)
            );
        }

        // if we don't have calculation data
        if(empty($calculated_phrase_data->calculation)){
            // setup the calculation indexes
            foreach($phrase_embeddings->embed_data as $sentence => $dat){
                $calculated_phrase_data->calculation[$sentence] = array();
                $calculated_phrase_data->calc_index[$sentence] = 0;
            }
        }

        $id = $phrase_embeddings->post_type . '_' . $phrase_embeddings->post_id;
        while(!Wpil_Base::overTimeLimit(15)){

            // find the lowest index info
            $lowest_ind = $last_embedding_index;
            foreach($calculated_phrase_data->calc_index as $snt => $dat){
                if(is_null($dat)){
                    $dat = 0;
                }

                if($dat < $lowest_ind){
                    $lowest_ind = $dat;
                }
            }

            // pull a batch of embedding data that picks up after the last
            $batch_embeddings = self::get_post_embedding_data(true, $lowest_ind, $batch_limit);


            if(empty($batch_embeddings)){
                $completed = true;
                break;
            }

            $count = 0;
            foreach($phrase_embeddings->embed_data as $sentence => $dat){
                if(Wpil_Base::overTimeLimit(15)){
                    break;
                }

                $phrase_object = (object) array(
                    'sentence' => $sentence,
                    'embed_data' => $dat
                );

                foreach($batch_embeddings as $d){
                    $sub_id = $d->post_type . '_' . $d->post_id;

                    // if this item already has this embedding data calculated
                    if($calculated_phrase_data->calc_index[$sentence] >= $d->embed_index){
                        // skip to the next to save time
                        continue;
                    }

                    // if the sub item is the main item
                    if($id === $sub_id){
                        // tag the embed index so we know it's counted
                        if($calculated_phrase_data->calc_index[$sentence] < $d->embed_index){
                            $calculated_phrase_data->calc_index[$sentence] = $d->embed_index;
                        }
                        // skip to the next because we don't need to determine how related the post is to itself
                        continue;
                    }

                    // run the calculation to see how related we are
                    $calculation = self::compare_post_embeddings($phrase_object, $d);

                    // if we pass the threshold for minimum relatability
                    if($calculation > 0.45){
                        // add it to the list
                        $calculated_phrase_data->calculation[$sentence][$sub_id] = $calculation;
                    }
                    
                    if($calculated_phrase_data->calc_index[$sentence] < $d->embed_index){
                        $calculated_phrase_data->calc_index[$sentence] = $d->embed_index;
                    }

                    $calculated_phrase_data->calc_count += 1;
                }

                // save periodically to make sure that we don't lose data
                if($count > 100){
                    self::save_calculated_single_post_embedding_data($calculated_phrase_data, true);
                    $count = 0;
                }

                $count++;
            }
        }

        self::save_calculated_single_post_embedding_data($calculated_phrase_data, true);

        if($return_calculations){
            return $calculated_phrase_data->calc_count;
        }

        return $completed ? 'completed': 'uncompleted';
    }

    /**
     * Gets the calculated phrase data for a specific post.
     * Caches data between calls to cut down on DB hits
     **/
    public static function get_embedding_calc_phrases($post, $decode = false, $ignore_cache = false){
        global $wpdb;
        $calc_table = $wpdb->prefix . 'wpil_ai_embedding_phrase_calculation_data';

        if(empty($post) || !is_a($post, 'Wpil_Model_Post')){
            return array();
        }
        $pid = $post->type . '_' . $post->id;

        if(isset(self::$cached_post_sentence_embedding_data[$pid]) && !$ignore_cache){
            $data = self::$cached_post_sentence_embedding_data[$pid];
        }else{
            $data = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$calc_table} WHERE `post_id` = %d AND `post_type` = %s", $post->id, $post->type));
            // if there is no data
            if(empty($data)){
                // set a flag so that we know that theres' nothing here
                $data = 'no-calculation-data';
            }

            self::$cached_post_sentence_embedding_data[$pid] = $data;
        }

        if($data === 'no-calculation-data'){
            return array();
        }

        // currently, we're decoding outside of the cache to try and save space in the cache.
        // if this gets to be a big time sinke, decompress before adding to cache
        if($decode && !empty($data)){
            if(isset($data->calculation)){
                $data->calculation = Wpil_Toolbox::json_decompress($data->calculation, true);
            }
            if(isset($data->calc_index)){
                $data->calc_index = Wpil_Toolbox::json_decompress($data->calc_index, true);
            }
        }

        return $data;
    }

    /**
     * 
     **/
    public static function has_calculated_phrase_embeddings($target_post = array()){
        if(empty($target_post) || !is_a($target_post, 'Wpil_Model_Post')){
            return false;
        }

        // get the latest index
        $last_embedding_index = self::get_last_embedding_index();

        // get the calculated phrases
        $calculated_phrase_data = self::get_embedding_calc_phrases($target_post);

        if(empty($calculated_phrase_data) || empty($calculated_phrase_data->calc_index)){
            return false;
        }

        $index = Wpil_Toolbox::json_decompress($calculated_phrase_data->calc_index);

        if(empty($index) || (!is_array($index) && !is_object($index))){
            return false;
        }

        foreach($index as $calc_index){
            if($calc_index < $last_embedding_index){
                return false;
            }
        }

        return true;
    }

    /**
     * Deletes the phrase embedding data for a specific post
     * @param Wpil_Model_Post $post
     **/
    public static function clear_post_phrase_embedding_data($post = array(), $age = 0){
        global $wpdb;
        $embedding_table = $wpdb->prefix . "wpil_ai_embedding_phrase_data";
        $embedding_calc_table = $wpdb->prefix . "wpil_ai_embedding_phrase_calculation_data";

        if(empty($post) || !is_a($post, 'Wpil_Model_Post')){
            return true;
        }

        $where = "`post_id` = {$post->id} AND `post_type` = '{$post->type}'";

        if(!empty($age) && is_numeric($age)){
            $age = intval($age);
            $where .= " AND `process_time` < {$age}";
        }

        $wpdb->query("DELETE FROM {$embedding_table} WHERE $where");
        $wpdb->query("DELETE FROM {$embedding_calc_table} WHERE $where");
    }

    /**
     * Deletes post sentence embedding data so that we don't max out the user's database
     * @param Wpil_Model_Post $post
     **/
    public static function housekeep_phrase_embedding_data(){
        global $wpdb;
        $embedding_table = $wpdb->prefix . "wpil_ai_embedding_phrase_data";

        $wpdb->query("DELETE FROM {$embedding_table} WHERE `embed_index` NOT IN (
            SELECT `embed_index` FROM ( 
              SELECT `embed_index` FROM {$embedding_table} ORDER BY embed_index DESC LIMIT 10
            ) AS newest
          )"
        );
    }


    /**
     * Checks to see if there is embedding data stored
     **/
    public static function has_calculated_embedding_data(){
        global $wpdb;
        $table = $wpdb->prefix . ((Wpil_Settings::use_ai_embedding_calculation_v2()) ? 'wpil_ai_embedding_calculation_data_v2': 'wpil_ai_embedding_calculation_data');

        $has_data = !empty($wpdb->get_var("SELECT COUNT(*) FROM $table LIMIT 1"));
        if(!$has_data && Wpil_Settings::use_ai_embedding_calculation_v2()){
            $table = $wpdb->prefix . 'wpil_ai_embedding_calculation_data';
            $has_data = !empty($wpdb->get_var("SELECT COUNT(*) FROM $table LIMIT 1"));
        }

        return $has_data;
    }

    /**
     * Gets all of the embedding data for posts|terms that are stored in the embeddings table.
     * Can return data for a specific post|term
     **/
    public static function get_calculated_embedding_data($post_id = 0, $post_type = 'post', $offset = 0, $limit = 500){
        global $wpdb;
        $table = $wpdb->prefix . ((Wpil_Settings::use_ai_embedding_calculation_v2()) ? 'wpil_ai_embedding_calculation_data_v2': 'wpil_ai_embedding_calculation_data');
        $posts = array();

        if($post_type !== 'post' && $post_type !== 'term'){
            return $posts;
        }

        if(Wpil_Settings::use_ai_embedding_calculation_v2()){
            if(empty($post_id)){
                $data = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} LIMIT %d OFFSET %d", $limit, ($limit * $offset)));
                if(!empty($data)){
                    return $data;
                }

                $table = $wpdb->prefix . 'wpil_ai_embedding_calculation_data';
                $data = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} LIMIT %d OFFSET %d", $limit, ($limit * $offset)));
                return !empty($data) ? $data : $posts;
            }

            $data = self::get_calculated_embedding_data_v2($post_id, $post_type);
            if(!empty($data)){
                return $data;
            }

            unset(self::$cached_embedding_data[$post_type . '_' . $post_id]);
            $table = $wpdb->prefix . 'wpil_ai_embedding_calculation_data';
        }

        // Bulk pagination should return only the requested page and must not
        // populate the shared single-post cache, otherwise sitemap generation
        // keeps re-accumulating prior batches in memory.
        if(empty($post_id)){
            $data = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} LIMIT %d OFFSET %d", $limit, ($limit * $offset)));
            return !empty($data) ? $data : $posts;
        }

        $search_id = $post_type . '_' . $post_id;
        // if we have a post id and there is no prior instance of this data
        if(!empty($post_id) && !isset(self::$cached_embedding_data[$search_id])){
            $data = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE `post_id` = %d AND `post_type` = %s", $post_id, $post_type));

            if(!empty($data)){
                foreach($data as $dat){
                    $id = $dat->post_type . '_' . $dat->post_id;
                    if(!isset(self::$cached_embedding_data[$id])){
                        self::$cached_embedding_data[$id] = $dat;
                    }
                }
            }else{
                self::$cached_embedding_data[$search_id] = 'no-calculations';
            }
        }

        if(self::$cached_embedding_data === 'no-calculations'){
            return $posts;
        }

        if(!empty($post_id)){
            $id = $post_type . '_' . $post_id;
            return (isset(self::$cached_embedding_data[$id]) && !empty(self::$cached_embedding_data[$id]) && self::$cached_embedding_data[$id] !== 'no-calculations') ? self::$cached_embedding_data[$id]: array();
        }else{
            return self::$cached_embedding_data;
        }
    }

    public static function get_calculated_embedding_data_v2($post_id = 0, $post_type = 'post'){
        global $wpdb;
        $table = $wpdb->prefix . 'wpil_ai_embedding_calculation_data_v2';
        $search_id = $post_type . '_' . $post_id;

        if(empty($post_id) || ($post_type !== 'post' && $post_type !== 'term')){
            return array();
        }

        if(!isset(self::$cached_embedding_data[$search_id])){
            $data = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE `post_id` = %d AND `post_type` = %s ORDER BY `target_post_type` ASC, `starting_id` ASC", $post_id, $post_type));

            if(!empty($data)){
                $calculation = array();
                $calc_index = 0;
                $calc_count = 0;
                $model_version = '';
                $process_time = 0;

                foreach($data as $dat){
                    $page_calculation = Wpil_Toolbox::json_decompress($dat->calculation, true);
                    if(!empty($page_calculation) && is_array($page_calculation)){
                        $calculation = array_merge($calculation, $page_calculation);
                    }

                    if((int)$calc_index < (int)$dat->calc_index){
                        $calc_index = (int)$dat->calc_index;
                    }

                    $calc_count += (int)$dat->calc_count;
                    $model_version = (!empty($dat->model_version)) ? $dat->model_version: $model_version;
                    $process_time = ((int)$process_time < (int)$dat->process_time) ? (int)$dat->process_time: $process_time;
                }

                self::$cached_embedding_data[$search_id] = (object)array(
                    'embed_index' => $data[0]->embed_index,
                    'post_id' => $post_id,
                    'post_type' => $post_type,
                    'data_type' => (($post_type === 'post') ? 1: 0),
                    'calculation' => Wpil_Toolbox::json_compress($calculation),
                    'calc_index' => $calc_index,
                    'calc_count' => $calc_count,
                    'process_time' => $process_time,
                    'model_version' => $model_version,
                );
            }else{
                self::$cached_embedding_data[$search_id] = 'no-calculations';
            }
        }

        return (isset(self::$cached_embedding_data[$search_id]) && self::$cached_embedding_data[$search_id] !== 'no-calculations') ? self::$cached_embedding_data[$search_id]: array();
    }

    /**
     * Gets the embedding data for a specific post from the calculation table.
     **/
    public static function get_embedding_relatedness_data($post_id = 0, $post_type = 'post', $assoc = false){
        global $wpdb;
        $table = $wpdb->prefix . ((Wpil_Settings::use_ai_embedding_calculation_v2()) ? 'wpil_ai_embedding_calculation_data_v2': 'wpil_ai_embedding_calculation_data');
        $posts = array();

        if($post_type !== 'post' && $post_type !== 'term' || empty($post_id)){
            return $posts;
        }

        if(Wpil_Settings::use_ai_embedding_calculation_v2()){
            $data = $wpdb->get_results($wpdb->prepare("SELECT `calculation` FROM {$table} WHERE `post_id` = %d AND `post_type` = %s", $post_id, $post_type));

            if(!empty($data)){
                foreach($data as $dat){
                    $calculation = Wpil_Toolbox::json_decompress($dat->calculation, true);
                    if(!empty($calculation) && is_array($calculation)){
                        $posts = array_merge($posts, $calculation);
                    }
                }

                return ($assoc) ? $posts: (object)$posts;
            }

            $table = $wpdb->prefix . 'wpil_ai_embedding_calculation_data';
        }

        $data = $wpdb->get_var($wpdb->prepare("SELECT `calculation` FROM {$table} WHERE `post_id` = %d AND `post_type` = %s", $post_id, $post_type));

        if(!empty($data)){
            $data = Wpil_Toolbox::json_decompress($data, $assoc);
            if(!empty($data) && (is_object($data) || is_array($data))){
                $posts = $data;
            }
        }

        return $posts;
    }

    /**
     * Gets the ids for posts|terms that are stored in the embeddings table
     **/
    public static function get_calculated_embedding_post_ids(){
        global $wpdb;
        $posts = array();

        if(Wpil_Settings::use_ai_embedding_calculation_v2()){
            $embedding_data = self::get_completed_embedding_calc_v2_posts();

            if(!empty($embedding_data)){
                foreach($embedding_data as $data){
                    $id = $data->post_type . '_' . $data->post_id;
                    $posts[$id] = true;
                }
            }

            return $posts;
        }

        $table = $wpdb->prefix . 'wpil_ai_embedding_calculation_data';

        $last_embedding_index = self::get_last_embedding_index();
        if(empty($last_embedding_index)){
            return $posts;
        }

        $embedding_data = $wpdb->get_results("SELECT `post_id`, `post_type` FROM {$table} WHERE `calc_index` >= {$last_embedding_index}");

        if(!empty($embedding_data)){
            foreach($embedding_data as $data){
                $id = $data->post_type . '_' . $data->post_id;
                $posts[$id] = true;
            }
        }

        return $posts;
    }

    public static function get_completed_embedding_calc_v2_posts(){
        global $wpdb;
        $table = $wpdb->prefix . 'wpil_ai_embedding_calculation_data_v2';
        $total_embeddings = self::get_total_embedding_data_count();

        if(empty($total_embeddings)){
            return array();
        }

        return $wpdb->get_results("SELECT `post_id`, `post_type` FROM {$table} GROUP BY `post_id`, `post_type` HAVING SUM(`calc_count`) >= {$total_embeddings}");
    }

    public static function get_total_embedding_data_count(){
        global $wpdb;
        $table = $wpdb->prefix . 'wpil_ai_embedding_data';

        return (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    }

    public static function get_post_embedding_data($decode_embeddings = false, $index_offset = 0, $search_limit = 0){
        global $wpdb;
        $table = $wpdb->prefix . 'wpil_ai_embedding_data';

        $search_limit = (int)$search_limit;
        $limit = !empty($search_limit) ? "LIMIT {$search_limit}": "";

        $embedding_index = (int)$index_offset;
        if(!empty($embedding_index)){
            $embedding_data = $wpdb->get_results("SELECT * FROM {$table} WHERE `embed_index` > {$embedding_index} {$limit}");
        }else{
            $embedding_data = $wpdb->get_results("SELECT * FROM {$table} {$limit}");
        }
        

        if(!empty($embedding_data) && $decode_embeddings){
            foreach($embedding_data as $key => $data){
                $embedding_data[$key]->embed_data = Wpil_Toolbox::json_decompress($data->embed_data, null, true);
            }
        }

        return (!empty($embedding_data)) ? $embedding_data: array();
    }

    /**
     * Gets the embedding data for a post that has had all of it's sentences processed
     **/
    public static function get_single_post_embedding_data($post, $decode_embeddings = false, $decode_assoc = false){
        global $wpdb;
        $table = $wpdb->prefix . 'wpil_ai_embedding_phrase_data';

        if(empty($post) || !is_a($post, 'Wpil_Model_Post')){
            return array();
        }

        $embedding_data = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE `post_id` = %d AND `post_type` = %s", $post->id, $post->type));

        if(!empty($embedding_data) && $decode_embeddings){
            foreach($embedding_data as $key => $data){
                $embedding_data[$key]->embed_data = Wpil_Toolbox::json_decompress($data->embed_data, $decode_assoc);
            }
        }

        return (!empty($embedding_data)) ? $embedding_data[0]: array();
    }

    public static function save_calculated_embedding_data_v2($embedding_data = array()){
        global $wpdb;
        $table = $wpdb->prefix . 'wpil_ai_embedding_calculation_data_v2';

        if(empty($embedding_data)){
            return 0;
        }

        $max_package_size = Wpil_Toolbox::get_max_allowable_package_size();
        $insert_length = 0;
        $time = time();
        $total_count = 0;
        $count = 0;
        $insert_query = "INSERT INTO {$table} (post_id, post_type, data_type, target_post_type, starting_id, ending_id, calculation, calc_index, calc_count, process_time, model_version) VALUES ";
        $insert_data = array();
        $place_holders = array();
        $place_holder_string = "('%d', '%s', '%d', '%s', '%d', '%d', '%s', '%d', '%d', '%d', '%s')";
        $limit = 100;

        foreach($embedding_data as $dat){
            if(empty($dat) || !isset($dat['post_id'], $dat['post_type'], $dat['target_post_type'])){
                continue;
            }

            $total_count++;
            $calculation = (isset($dat['calculation']) && is_array($dat['calculation'])) ? $dat['calculation']: array();
            $embeddings = Wpil_Toolbox::json_compress($calculation);

            array_push(
                $insert_data,
                (int)$dat['post_id'],
                $dat['post_type'],
                (($dat['post_type'] === 'post') ? 1: 0),
                $dat['target_post_type'],
                (int)$dat['starting_id'],
                (int)$dat['ending_id'],
                $embeddings,
                (int)$dat['calc_index'],
                (int)$dat['calc_count'],
                $time,
                $dat['model_version']
            );
            $place_holders[] = $place_holder_string;

            // keep the packet size under control as we add the pages.
            $insert_size = (strlen($embeddings) + strlen($place_holder_string) + 260);
            $insert_length += $insert_size;

            if($count >= $limit || (!empty($max_package_size) && ($insert_length + $insert_size) > $max_package_size)){
                $insert = ($insert_query . implode(', ', $place_holders));
                $insert = $wpdb->prepare($insert, $insert_data);
                $wpdb->query($insert);

                $insert_data = [];
                $place_holders = [];
                $count = 0;
                $insert_length = 0;
            }

            $count++;
        }

        if(!empty($insert_data) && !empty($place_holders)){
            $insert = ($insert_query . implode(', ', $place_holders));
            $insert = $wpdb->prepare($insert, $insert_data);
            $wpdb->query($insert);
        }

        return $total_count;
    }

    public static function save_calculated_embedding_data($embedding_data = array(), $partial = false){
        global $wpdb;
        $table = $wpdb->prefix . 'wpil_ai_embedding_calculation_data';

        if(empty($embedding_data)){
            return 0;
        }

        $max_package_size = Wpil_Toolbox::get_max_allowable_package_size();
        $insert_length = 0;
        $last_embedding_index = self::get_last_embedding_index();
        $time = time();
        $total_count = 0;
        $count = 0;
        $insert_query = "INSERT INTO {$table} (post_id, post_type, data_type, calculation, calc_index, calc_count, process_time, model_version) VALUES ";
        $insert_data = array();
        $place_holders = array();
        $place_holder_string = "('%d', '%s', '%d', '%s', '%d', '%d', '%d', '%s')";
        $limit = 100;
        foreach($embedding_data as $key => $dat){
            $total_count++;
            $ids = explode('_', $key);
            if($partial){
                if (is_object($dat->calculation)) {
                    $dat->calculation = get_object_vars($dat->calculation);
                }

                if (! is_array($dat->calculation)) {
                    $dat->calculation = array();
                }
                $embeddings = Wpil_Toolbox::json_compress($dat->calculation);
                $last_embedding_index = $dat->calc_index;
                $calc_count = count($dat->calculation);
                $model = $dat->model_version;
            }else{
                if (! is_array($dat['embeddings'])) {
                    $dat['embeddings'] = array();
                }
                $embeddings = Wpil_Toolbox::json_compress($dat['embeddings']);
                $calc_count = count($dat['embeddings']);
                $model = $dat['model_version'];
            }

            array_push(
                $insert_data, 
                $ids[1],
                $ids[0],
                (($ids[0] === 'post') ? 1: 0),
                $embeddings,
                $last_embedding_index,
                $calc_count,
                $time,
                $model
            );
            $place_holders[] = $place_holder_string;

            // increase the estimated insert length
            $insert_size = (strlen($embeddings) + strlen($place_holder_string) + 200); // 200 is to cover the other bits of data that are being inserted
            $insert_length += $insert_size;

            // if we've hit the limit or processing a similarly sized dataset will push us over the limit
            if($count >= $limit || (!empty($max_package_size) && ($insert_length + $insert_size) > $max_package_size)){
                // assemble the insert
                $insert = ($insert_query . implode(', ', $place_holders));
                $insert = $wpdb->prepare($insert, $insert_data);
                // insert the data
                $wpdb->query($insert);
                // reset the data variables
                $insert_data = [];
                $place_holders = [];
                $count = 0;
                $insert_length = 0;
            }

            $count++;
        }

        // if we still have data that hasn't been inserted
        if(!empty($insert_data) && !empty($place_holders)){
            // assemble the insert
            $insert = ($insert_query . implode(', ', $place_holders));
            $insert = $wpdb->prepare($insert, $insert_data);
            // and insert the data
            $wpdb->query($insert);
        }

        // if we're doing stepped saving
        if($partial){
            self::clear_duplicate_calculated_embeddings();
        }

        // return the total number of posts processed
        return $total_count;
    }

    /**
     * Removes the duplicate embedding calcs that happen when generating the calculations
     **/
    public static function clear_duplicate_calculated_embeddings($phrases = false){
        global $wpdb;
        $table = $wpdb->prefix . ((empty($phrases)) ? "wpil_ai_embedding_calculation_data": "wpil_ai_embedding_phrase_calculation_data");
        $temp_table = $wpdb->prefix . ((empty($phrases)) ? "wpil_ai_temp_calculation_data": "wpil_ai_temp_phrase_calculation_data") ;

        if(empty($wpdb->query("SHOW TABLES LIKE '{$table}'"))){
            return;
        }

        // create a temporary table with the latest records
        $wpdb->query("CREATE TEMPORARY TABLE {$temp_table} AS
        SELECT post_id, post_type, MAX(embed_index) AS max_embed_index
        FROM {$table}
        GROUP BY post_id, post_type");

        // delete records that are not the latest
        $wpdb->query("DELETE FROM {$table}
        WHERE (post_id, post_type, embed_index) NOT IN (
            SELECT post_id, post_type, max_embed_index
            FROM {$temp_table}
        )");

        // drop the temporary table
        $wpdb->query("DROP TEMPORARY TABLE {$temp_table}");
    }

    /**
     * Saves the embedding calculations for a single post's phrases
     * @param object $embedding_data
     **/
    public static function save_calculated_single_post_embedding_data($embedding_data = array()){
        global $wpdb;
        $table = $wpdb->prefix . "wpil_ai_embedding_phrase_calculation_data";

        if(empty($embedding_data)){
            return 0;
        }

        $no_data = 1;
        $phrase_string = '';
        foreach($embedding_data->calculation as $sentence => $dat){
            if(!empty($dat)){
                $no_data = 0;
            }
            $phrase_string .= $sentence;
        }

        $post = new Wpil_Model_Post($embedding_data->post_id, $embedding_data->post_type);

        $data = array(
            'post_id' => $post->id,
            'post_type' => $post->type,
            'data_type' => (($post->type === 'post') ? 1: 0),
            'post_phrase_id' => Wpil_Toolbox::create_post_content_id($post),
            'calculation' => Wpil_Toolbox::json_compress($embedding_data->calculation),
            'calc_index' => Wpil_Toolbox::json_compress($embedding_data->calc_index),
            'calc_count' => $embedding_data->calc_count,
            'no_data' => $no_data,
            'process_time' => time(),
            'model_version' => $embedding_data->model_version,
            'dimension_count' => $embedding_data->dimension_count,
        );

        $wpdb->insert($table, $data);

        if(!empty($wpdb->insert_id)){
            self::clear_duplicate_calculated_embeddings(true);
        }
    }

    /**
     * Calculates how related all the posts 
     **/
    public static function calculate_relatedness_sitemap(){
        $limit = Wpil_Settings::get_ai_sitemap_relatedness_threshold();
        $calculated = array();
        $step = 0;
        while(!Wpil_Toolbox::is_over_memory_limit() && !empty($data = self::get_calculated_embedding_data(0, 'post', $step))){
            foreach($data as $dat){
                $id = $dat->post_type . '_' . $dat->post_id;
                $calc = Wpil_Toolbox::json_decompress($dat->calculation);

                if(!isset($calculated[$id])){
                    $calculated[$id] = array();
                }

                if(!empty($calc)){
                    foreach($calc as $p_id => $c){
                        if($c >= $limit && $c < 0.999){
                            $calculated[$id][$p_id] = $c;
                        }
                    }
                }

                unset($calc);
            }

            unset($data);
            $step++;
        }

        return $calculated;
    }

    /**
     * Gets the product data
     **/
    public static function get_product_data(){
        global $wpdb;
        $table = $wpdb->prefix . "wpil_ai_product_data";
        $products = $wpdb->get_results("SELECT * FROM {$table} WHERE `product_count` > 0");
        return (!empty($products)) ? $products: array();
    }

    /**
     * Calculates products mentioned by specific posts 
     **/
    public static function calculate_product_sitemap(){
        $calculated = array();
        $data = self::get_product_data();

        foreach($data as $dat){
            $id = $dat->post_type . '_' . $dat->post_id;
            $products = Wpil_Toolbox::json_decompress($dat->products);

            if(!isset($calculated[$id])){
                $calculated[$id] = array();
            }

            if(!empty($products)){
                foreach($products as $product){
                    $p_id = trim(mb_strtolower($product));

                    if(!isset($calculated[$p_id])){
                        $calculated[$p_id] = array();
                    }

                    $calculated[$id][$p_id] = addslashes(html_entity_decode($product));
                }
            }
        }

        return $calculated;
    }

    /**
     * JSON decodes the data from OpenAI responses
     **/
    public static function decode($response, $skip_recover = false, $log = false){
        $decoded = false;
        if(is_string($response) && !empty($response)){
            $decoded = json_decode($response);

            if(empty($decoded) && !$skip_recover){
                // trim the response so we don't go insane
                $response = trim($response);
                $start_marker = '```json';
                $end_marker = '```';
                $start_marker_len = strlen($start_marker);
                $end_marker_len = strlen($end_marker);
                if(0 === strpos($response, $start_marker) && (strrpos($response, $end_marker) + $end_marker_len) === strlen($response)){
                    $response = trim(substr($response, $start_marker_len, strrpos($response, $end_marker) - $start_marker_len));
                }

                $decoded = json_decode($response);

                // if that didn't work
                if(empty($decoded)){
                    // try replacing any control characters
                    $maybe_ready = mb_eregi_replace('[[:cntrl:]]', ' ', $response);
                    if(!empty($maybe_ready)){
                        $decoded = json_decode($maybe_ready);
                    }
                }

                if(empty($decoded)){
                    $decoded = json_decode(self::attempt_recover_json($response));
                }
            }
        }elseif((is_object($response) || is_array($response)) && !empty($response)){
            $decoded = $response;
        }

        return !empty($decoded) && $decoded !== false && $decoded !== null ? $decoded: false;
    }

    public static function count_tokens($text = '', $model = '', $return_tokens = false) {
        return self::estimate_openai_tokens($text);
    }

    private static function estimate_openai_tokens($text) {
        // Strip leading/trailing whitespace
        $text = trim($text);

        // Count the words and the letters
        $charCount = mb_strlen($text, 'UTF-8');
        $wordCount = str_word_count($text);

        // Divide the totals by approximate values for how many chars/words go into a token
        $byChars = $charCount / 4;
        $byWords = $wordCount / 0.85;

        // Estimate the numbeer of tokens by creating a weighted averate of the wohrds and chars
        $estimatedTokens = ($byChars * 0.4) + ($byWords * 0.6);

        return (int) round($estimatedTokens);
    }

    public static function trim_text_to_token_limit($text = '', $model = '', $limit = 0){
        if (self::estimate_openai_tokens($text) <= $limit) {
            return $text;
        }

        // Split text into words
        $words = preg_split('/\s+/', trim($text));

        // Binary-style trimming loop
        $low = 0;
        $high = count($words);
        $best = "";

        while ($low <= $high) {
            $mid = (int)(($low + $high) / 2);
            $candidate = implode(" ", array_slice($words, 0, $mid));
            $tokens = self::estimate_openai_tokens($candidate);

            if ($tokens <= $limit) {
                $best = $candidate;
                $low = $mid + 1; // try adding more
            } else {
                $high = $mid - 1; // trim down
            }
        }

        return $best;
    }

    /**
     * Breaks a post's content up into chunks without splitting words
     * @param string $content The content to be split
     * @param int $length The byte length that each chunk should be
     **/
    public static function chunk_post_content($content, $chunk_length = 0){
        if(empty($content) || empty($chunk_length)){
            return $content;
        }

        // Split the string into words and delimiters (spaces, punctuation)
        $words = preg_split('/(\s+)/u', $content, -1, PREG_SPLIT_DELIM_CAPTURE);

        $chunks = array();
        $current_chunk = '';

        foreach ($words as $word) {
            // Calculate the length of the current chunk plus the new word
            $length = mb_strlen($current_chunk . $word, 'UTF-8');
            
            if ($length <= $chunk_length) {
                // Append the word to the current chunk
                $current_chunk .= $word;
            } else {
                // If the current chunk is not empty, add it to the chunks array
                if ($current_chunk !== '') {
                    $chunks[] = $current_chunk;
                }
                // Start a new chunk with the current word
                $current_chunk = $word;

                // Handle the case where a single word exceeds 2500 characters
                if (mb_strlen($word, 'UTF-8') > $chunk_length) {
                    // Optionally split the word or handle it according to your needs
                    $chunks[] = $current_chunk;
                    $current_chunk = '';
                }
            }
        }

        // Add any remaining text in the current chunk to the chunks array
        if ($current_chunk !== '') {
            $chunks[] = $current_chunk;
        }

        return $chunks;
    }

    /**
     * Gets the list of posts that are supposed to be chunk-processed
     **/
    public static function get_chunked_posts(){
        $posts = get_transient('wpil_chunked_ai_process_posts');
        return (!empty($posts)) ? $posts: array();
    }

    /**
     * Saves a post to be chunk-processed to the chunk process list.
     * @param Wpil_Post|string A Link Whisper post object or a post_id string
     **/
    public static function save_chunked_post($post){
        if(empty($post)){
            return false;
        }

        $posts = self::get_chunked_posts();
        if(is_string($post)){
            $posts[$post] = true;
        }else{
            $id = $post->post_type . '_' . $post->post_id;
            $posts[$id] = true;
        }

        set_transient('wpil_chunked_ai_process_posts', $posts, HOUR_IN_SECONDS * 12);
        return true;
    }

    /**
     * Removes a post from the list of posts that are supposed to be chunk-processed
     * @param Wpil_Post|string A Link Whisper post object or a post_id string
     **/
    public static function remove_chunked_post($post){
        if(empty($post)){
            return false;
        }

        $posts = self::get_chunked_posts();

        if(empty($posts)){
            return true;
        }

        if(is_string($post)){
            $id = $post;
        }else{
            $id = $post->post_type . '_' . $post->post_id;
        }
        
        if(isset($posts[$id])){
            unset($posts[$id]);
            set_transient('wpil_chunked_ai_process_posts', $posts, HOUR_IN_SECONDS * 12);
        }
        
        return true;
    }

    /**
     * Gets the embedding relatedness score for specific posts
     * @param Wpil_Model_Post $post_a
     * @param Wpil_Model_Post $post_b
     **/
    public static function get_post_relationship_score($post_a = array(), $post_b = array()){
        $score = 0.000;
        if(empty($post_a) || empty($post_b)){
            return $score;
        }

        $a = self::get_calculated_embedding_data($post_a->id, $post_a->type);

        if(empty($a)){
            return $score;
        }

        $id = $post_b->type . '_' . $post_b->id;
        $calc = Wpil_Toolbox::json_decompress($a->calculation, true);

        if(!empty($calc) && isset($calc[$id])){
            $score = $calc[$id];
        }

        return floatval($score);
    }

    /**
     * Gets the embedding relatedness score for specific sentences to posts
     * @param Wpil_Model_Post $post_a the post that has the sentence that we want to score
     * @param Wpil_Model_Post $post_b the post that we want to see how related to the current sentence
     * @param string $sentence the sentence that we're checking for relatedness to post_b
     **/
    public static function get_sentence_relationship_score($post_a = array(), $post_b = array(), $sentence = ''){
        $score = 0.000;
        if(empty($post_a) || empty($post_b) || empty($sentence)){
            return $score;
        }

        $calculated_phrases = self::get_embedding_calc_phrases($post_a, true);

        if(empty($calculated_phrases)){
            return $score;
        }

        $id = $post_b->type . '_' . $post_b->id;

        if(isset($calculated_phrases->calculation[$sentence][$id])){
            $score = $calculated_phrases->calculation[$sentence][$id];
        }

        return floatval($score);
    }

    /**
     * Deletes all AI Suggestion related data for a specific post
     * @param Wpil_Model_Post $post
     **/
    public static function clear_ai_suggestion_data($post = array(), $age = 0){
        if(empty($post) || !is_a($post, 'Wpil_Model_Post')){
            return true;
        }

        self::clear_processed_anchor_sentences($post, $age);
        self::clear_post_phrase_suggestion_sentences($post, $age);
        self::clear_post_phrase_embedding_data($post, $age);
    }
    
    /**
     * Checks if post content has changed since the process was last run
     **/
    public static function sentence_post_id_changed($post = array()){
        global $wpdb;
        $table = $wpdb->prefix . "wpil_ai_embedding_phrase_data";

        if(empty($post) || !is_a($post, 'Wpil_Model_Post')){
            return true;
        }

        $phrase_id = $wpdb->get_var($wpdb->prepare("SELECT `post_phrase_id` FROM {$table} WHERE `post_id` = %d AND `post_type` = %s", $post->id, $post->type));

        // if there are no results
        if(empty($phrase_id)){
            // say that the id has changed
            return true;
        }

        // if the stored id is difference from the current id >>> return true ||| Wotyherwise, it has neot changed!
        return $phrase_id !== Wpil_Toolbox::create_post_content_id($post);
    }

    /**
     * 
     **/
    public static function delay_batch_process($process = ''){
        if(empty($process)){
            return false;
        }

        $processes = get_transient('wpil_oai_batch_process_delay');
        if(empty($processes) && !is_array($processes)){
            $processes = array($process => time() + (DAY_IN_SECONDS + HOUR_IN_SECONDS));
        }else{
            $processes[$process] = time() + (DAY_IN_SECONDS + HOUR_IN_SECONDS);
        }

        set_transient('wpil_oai_batch_process_delay', $processes, DAY_IN_SECONDS * 2);

        return true;
    }

    /**
     * Checks to see if the currently supplied process is under a delay
     **/
    public static function check_delayed_batch_process($process = ''){
        if(empty($process)){
            return false;
        }

        $processes = get_transient('wpil_oai_batch_process_delay');

        if(empty($processes) || !is_array($processes) || !isset($processes[$process])){
            return false;
        }

        // if the delay time still hasn't lapsed
        if($processes[$process] > time()){
            // say that we're delayed
            return true;
        }

        return false;
    }

    /**
     * Does final data processing actions for AI data
     **/
    public static function do_post_save_finishing(){
        $selected_processes = Wpil_Settings::get_selected_ai_batch_processes(true);
        $completed = true;

        if(in_array('keyword-detecting', $selected_processes)){
            $state = Wpil_TargetKeyword::process_ai_generated_keywords_data(array('state' => 'ai_generated_process'), microtime(true));
            $completed = ($state['state'] === 'ai_generated_process') ? false: true;
        }

        return $completed;
    }

    /**
     * 
     **/
    public static function get_api_rate_limits($endpoint = '', $non_batched = false){
        $doing_ajax = (defined('DOING_AJAX') && DOING_AJAX) ? true: false;
        $rate_limits = array();
        if($doing_ajax || $non_batched){
            $rate_limits = array(
                'gpt-4o' => 30000,
                'gpt-4o-mini' => 185000,
                'gpt-4-turbo' => 30000,
                'gpt-3.5-turbo' => 185000,
                'text-embedding-3-large' => 900000
            );
        }else{
            $rate_limits = array(
                'gpt-4o' => 90000/2,
                'gpt-4o-mini' => 1800000/2,
                'gpt-4-turbo' => 90000/2,
                'gpt-3.5-turbo' => 1800000/2,
                'text-embedding-3-large' => 2800000/2
            );
        }

        return (!empty($endpoint) && isset($rate_limits[$endpoint])) ? $rate_limits[$endpoint]: $rate_limits;
    }

    /**
     * 
     **/
    public static function get_live_oai_error_message(){
        $message = array(
            'title' => __('Processing Halted.', 'wpil'),
            'text'  => __("Link Whisper has processed all of the posts that it's able to, and has stopped.", 'wpil'),
            'code'  => 'unknown',
        );

        if(self::$rate_limited){
            $message['title']   = __("Unable to Complete: Rate Limiting Active", 'wpil');
            $message['text']    = sprintf(__("It seems that the API key's %s per hour has been reached, and you may need to wait for the processing limits to reset. If you haven't already, please wait an hour and then try again.", "wpil") . '<br><br>' . __("If you see this message again after waiting an hour, please wait 24 hours before restarting the process.", 'wpil'), '<a href="https://platform.openai.com/docs/guides/rate-limits/usage-tiers?context=tier-one">' . __('limit on how much data can be processed', 'wpil') .'</a>' );
            $message['code']    = 'rate_limited';
        }elseif(self::$insufficient_quota){
            $message['title']   = __("Unable to Complete: Credit Limit Reached", 'wpil');
            $message['text']    = __("It seems that the credit limit for the API key has been reached, and further processing isn't possible without more credit.", 'wpil') . '<br><br>' . __('To add more credit to the account, please go here: ', 'wpil') . '<br><br>' . '<a href="https://platform.openai.com/settings/organization/billing/overview" target="_blank">OpenAI Account Billing</a>';
            $message['code']    = 'insufficient_credits';
        }elseif(self::$invalid_api_key){
            $message['title']   = __("Unable to Complete: Invalid API Key", 'wpil');
            $message['text']    = __("It seems that there was a mistake when entering the API key, and OpenAI is rejecting our contact request.", 'wpil') . '<br><br>' . __('If you have the API key written down, please try re-entering it in the settings.', 'wpil') . '<br><br>' . sprintf(__('If you don\'t have the API key written down, please %s and enter it in the Settings.', 'wpil'), '<a href="https://platform.openai.com/api-keys" target="_blank">' . __('generate a new one from your OpenAI account', 'wpil') . '</a>');
            $message['code']    = 'invalid_api_key';
        }elseif(self::$invalid_request){
            $message['title']   = __("Unable to Complete: Invalid Request", 'wpil');
            $message['text']    = __("It seems that there was an error when reaching out to OpenAI, and post content wasn't able to be processed. Link Whisper isn't sure what caused the error, but it should be logged in the \"System Error Log\" area of the Settings.", 'wpil');
            $message['code']    = 'invalid_request';
        }else{
            $message['title']   = __("Unable to Complete: Unknown Error", 'wpil');
            $message['text']    = __('It seems that there was an error and some posts may not have been processed by OpenAI. If you see any indications that posts haven\'t been processed, please try waiting an hour and then try restarting the process.', 'wpil');
            $message['code']    = 'unknown';
        }

        //$message .= (!empty(self::$error_message)) ? "\n\n" . __('During processing, OpenAI sent along this error message: ', 'wpil') . self::$error_message . "\n\n" . __('If you need to contact support about the issue, please be sure to include this message in your ticket.', 'wpil'): '';
    
        return $message;
    }

    /**
     * 
     **/
    public static function get_linkwhisper_ai_error_message(){
        $message = array(
            'title' => __('Processing Halted.', 'wpil'),
            'text'  => __("Link Whisper has processed all of the posts that it's able to, and has stopped.", 'wpil'),
            'code'  => 'unknown',
        );

        if(self::$rate_limited){
//            $message['title']   = __("Unable to Complete: Rate Limiting Active", 'wpil');
//            $message['text']    = sprintf(__("It seems that the API key's %s per hour has been reached, and you may need to wait for the processing limits to reset. If you haven't already, please wait an hour and then try again.", "wpil") . '<br><br>' . __("If you see this message again after waiting an hour, please wait 24 hours before restarting the process.", 'wpil'), '<a href="https://platform.openai.com/docs/guides/rate-limits/usage-tiers?context=tier-one">' . __('limit on how much data can be processed', 'wpil') .'</a>' );
            $message['code']    = 'rate_limited';
        }elseif(self::$invalid_api_key){
            $message['title']   = __("Unable to Complete: API Not Accessible", 'wpil');
            $message['text']    = __("Link Whisper isn't able to make contact with the AI server. This could be caused by network traffic, or a configuration issue.", 'wpil') . '<br><br>' .__("If this is the first time this has happened, please wait 30 minutes and try again.", 'wpil') . '<br><br>' .  sprintf(__('If its happed before, please reach out to Link Whisper support %s so we can help you with this issue.', 'wpil'), '<a href="'.esc_url(WPIL_STORE_URL . '/support').'">right here</a>');
            $message['code']    = 'ai_connection';
        }elseif(self::$user_not_exist){
            $message['title']   = __("Unable to Complete: AI User Not Logged", 'wpil');
            $message['text']    = __("Unfortunately, it looks like there was an error when setting up the AI connection, and Link Whisper can't access our AI server.", 'wpil') . '<br><br>' . sprintf(__('To resolve this, please reach out to Link Whisper support %s', 'wpil'), '<a href="'.esc_url(WPIL_STORE_URL . '/support').'">right here</a>');
            $message['code']    = 'ai_user_missing';
        }elseif(self::$insufficient_quota){
            $message['title']   = __("Unable to Complete: Insufficient Credits", 'wpil');
            $message['text']    = __("Unfortunately, there aren't enough AI credits available to process the posts.", 'wpil') . '<br><br>' . __('To add more to your account, please go here: ', 'wpil') . '<br><br>' . '<a href="' .admin_url('admin.php?page=link_whisper_ai_subscription'). '" target="_blank">AI Subscription Management</a>';
            $message['code']    = 'insufficient_credits';
        }else{
            $message['title']   = __("Unable to Complete: Unknown Error", 'wpil');
            $message['text']    = __('It seems that there was an error and some posts may not have been processed by OpenAI. If you see any indications that posts haven\'t been processed, please try waiting an hour and then try restarting the process.', 'wpil');
            $message['code']    = 'unknown';
        }

        //$message .= (!empty(self::$error_message)) ? "\n\n" . __('During processing, OpenAI sent along this error message: ', 'wpil') . self::$error_message . "\n\n" . __('If you need to contact support about the issue, please be sure to include this message in your ticket.', 'wpil'): '';
    
        return $message;
    }

    /**
     * Removes the AI process streaming
     **/
    public static function disable_ai_streaming($handle_id, $content, $info){
        remove_filter('orhanerday_openai_stream_response_data', [__CLASS__, 'process_streamed_data']);
        return false;
    }

    /**
     * 
     **/
    public static function reduce_embedding_dimensions($embedding_data = array(), $dimension_count = 0){
        // Truncate the embedding to our selected number of dimensions.
        $truncated_embedding = array_slice($embedding_data, 0, $dimension_count);
        
        // Compute the L2 norm of the truncated embedding.
        $l2_norm = sqrt(array_sum(array_map(function($x) {
            return $x * $x;
        }, $truncated_embedding)));
        
        // Normalize the truncated embedding.
        if ($l2_norm != 0) {
            $normalized_embedding = array_map(function($x) use ($l2_norm) {
                return $x / $l2_norm;
            }, $truncated_embedding);
        } else {
            $normalized_embedding = $truncated_embedding;
        }

        // And return our new shortened embedding
        return $normalized_embedding;
    }

    /**
     * Checks to see if the current session has flipped a rate limiting switch
     **/
    public static function is_rate_limited(){
        return (!empty(self::$rate_limited));
    }

    /**
     * Checks to see if we're out of money
     **/
    public static function is_insufficient_quota(){
        return (!empty(self::$insufficient_quota));
    }

    /**
     * Normalizes spaces in strings to remove fun and unexpected whitespaces that OAI won't be returning to us in the output
     **/
    public static function normalize_whitespace($string = ''){
        if(!function_exists('mb_ereg_replace')){
            return preg_replace('/[^\S ]+/u', ' ', $string);
        }

        return mb_ereg_replace('( |[^\S ])+', ' ', $string);
    }

    /**
     * Estimates the cost required to process a specific piece of content directly throught the OpenAI API.
     * Only an estimate because the output depends on ChatGPT and can't be predicted
     **/
    public static function estimate_processing_cost($content = '', $model = ''){
        $costs = self::get_standard_model_costs();
        $input_tokens = self::count_tokens($content, $model);
        $output_tokens = (!empty($input_tokens)) ? $input_tokens/10 : 0;
        $cost = 0;
        if(isset($costs[$model])){
            $cost += $costs[$model]['input'] * $input_tokens;
            $cost += $costs[$model]['output'] * $output_tokens;
        }

        return $cost;
    }

    /**
     * Estimates the token cost required to process a specific piece of content.
     * Only an estimate because the output depends on ChatGPT and can't be predicted with precision
     **/
    public static function estimate_processing_token_cost($content = '', $model = ''){
        $input_tokens = self::count_tokens($content, $model);
        $output_tokens = (!empty($input_tokens)) ? $input_tokens/10 : 0;
        $input_divisor = ($model === 'gpt-4o-mini' || $model === 'text-embedding-3-large') ? 10000: 1000;
        $output_divisor = ($model === 'gpt-4o-mini' || $model === 'text-embedding-3-large') ? 1000: 100;
        $tokens = 0;

        $tokens += ($input_tokens > 0) ? (ceil(($input_tokens/$input_divisor) + ($output_tokens/$output_divisor))): 0;

        return $tokens;
    }

    /**
     * Estimates the number of credits needed to process an AI linking queue
     **/
    public static function estimate_ai_linking_credit_cost($ai_linking_process_key = '', $fallback_to_total_queue = false, $fallback_count = null){
        $total_posts = self::get_total_processable_posts();
        $remaining = $total_posts;

        if(!empty($ai_linking_process_key)){
            $remaining = Wpil_LinkMapping::get_relation_map_pending_ai_item_count($ai_linking_process_key, array(), array(), true);
            $total_queued = Wpil_LinkMapping::get_relation_map_total_item_count($ai_linking_process_key);

            if($remaining < 1){
                if(!empty($fallback_to_total_queue) && $total_queued > 0){
                    $remaining = $total_queued;
                }elseif(null !== $fallback_count){
                    $remaining = max(0, (int) $fallback_count);
                }elseif($total_queued < 1){
                    $remaining = $total_posts;
                }
            }
        }elseif(null !== $fallback_count){
            $remaining = max(0, (int) $fallback_count);
        }

        // base linking cost, plus a budget for the AI sentence relatedness checks that run during linking
        $estimate = ($remaining * 4) + self::estimate_sentence_relatedness_credit_cost($remaining);

        return (int) ceil($estimate);
    }

    /**
     * Estimates the credits the AI sentence relatedness checker will burn while linking a set of posts.
     *
     * The checker runs once per post on outbound passes (every target is bundled into a single call),
     * but fans out to one call per candidate target on inbound passes (pillar / money pages), up to the
     * inbound candidate cap. So we weight the per-post call count by the share of posts that are pillars.
     * In testing we're running about 0.5 credits per checker call.
     *
     * @param int $remaining_posts The number of posts still due for AI linking.
     * @return float The estimated credit cost of the sentence checks for those posts.
     **/
    public static function estimate_sentence_relatedness_credit_cost($remaining_posts = 0){
        $remaining_posts = max(0, (int) $remaining_posts);
        if($remaining_posts < 1){
            return 0;
        }

        // roughly half a credit per checker call in testing
        $credits_per_call = (float) apply_filters('wpil_sentence_relatedness_credits_per_call', 0.5);
        // outbound linking bundles every target into a single checker call
        $outbound_calls = (float) apply_filters('wpil_sentence_relatedness_outbound_calls', 1);
        // inbound linking (pillar / money pages) runs one call per candidate target, capped at 6
        $inbound_calls = (float) apply_filters('wpil_sentence_relatedness_inbound_calls', 6);

        // work out what share of the posts get the pricier inbound treatment
        $total_posts = self::get_total_processable_posts();
        $pillar_posts = count(Wpil_Settings::get_money_page_pid_list());
        $pillar_fraction = ($total_posts > 0) ? min(1, ($pillar_posts / $total_posts)) : 0;

        // blend the per-post call counts by direction, then price them out
        $calls_per_post = ($inbound_calls * $pillar_fraction) + ($outbound_calls * (1 - $pillar_fraction));

        return ($remaining_posts * $calls_per_post * $credits_per_call);
    }

    /**
     * Estimates the number of credits needed to process the site
     **/
    public static function estimate_site_processing_credit_cost($ai_linking_process_key = '', $include_ai_linking = true){
        $estimate = 0; // we're pretty much rocking 1 credit per process per post, so we'll run with that

        // get the active processes
        $selected_processes = Wpil_Settings::get_selected_ai_batch_processes(true);
        $credit_processes = array('create-post-embeddings', 'keyword-detecting', 'product-detecting', 'post-summarizing');

        // the total number of posts that need processing
        $total_posts = self::get_total_processable_posts();

        // the number of posts that are already processed
        $processed_posts = self::get_completed_post_stats(true);

        // go over the processes
        foreach($selected_processes as $process){
            if(!in_array($process, $credit_processes, true)){
                continue;
            }

            if(isset($processed_posts[$process])){
                $estimate += ($total_posts - $processed_posts[$process]);
            }else{
                $estimate += $total_posts;
            }
        }

        // if we're going to be doing post linking too
        if($include_ai_linking && (!empty($ai_linking_process_key) || null === $ai_linking_process_key)){
            // throw it on the stack of posts
            $estimate += self::estimate_ai_linking_credit_cost($ai_linking_process_key);
        }

        // the result is our estimate!
        return $estimate;
    }

    /**
     * Gets the current number of credits that the user has available
     **/
    public static function get_available_ai_credits($refresh = false, $precision = false){
        if(!self::$ai_service_connected){
            return 0;
        }

        $credits = get_transient('wpil_ai_credit_balance');
        
        if(empty($credits) || $refresh){
            $stored_credits = self::get_stored_ai_credit_balance();
            $credits = self::call_linkwhisper_ai('return_credits', '', 'get-available-credits');
            if(!empty($credits)){
                $credits = round((float) $credits, 4);
                set_transient('wpil_ai_credit_balance', $credits, 60 * MINUTE_IN_SECONDS);
                self::set_stored_ai_credit_balance($credits);

                if($refresh && $credits > 10){
                    // If the refreshed balance is healthy again, clear the old low-credit warning.
                    update_option('wpil_oai_insufficient_quota_error', '0');
                }

                if(null !== $stored_credits && $credits > ($stored_credits + 0.00009)){
                    self::refresh_ai_credit_purchase_data();
                }
            }else{
                set_transient('wpil_ai_credit_balance', 'no-credits', 5 * MINUTE_IN_SECONDS);
                self::set_stored_ai_credit_balance(0);
            }
        }elseif($credits === 'no-credits'){
            return 0;
        }else{
            self::set_stored_ai_credit_balance($credits);
        }

        return ($precision) ? round((float) $credits, 4): (int) $credits;
    }

    /**
     * 
     **/
    public static function subtract_ai_credits($credits_spent = 0){
        // if there are no creds spent or it's somehow a negative number
        if(empty($credits_spent) || $credits_spent < 0){
            // just say everything's fine
            return true;
        }

        // get the current credit count
        $credits = self::get_available_ai_credits(false, true);

        // if they have creds
        if($credits > 0){
            // subtract from the total
            $credits = ($credits - $credits_spent);
            // and update
            set_transient('wpil_ai_credit_balance', $credits, HOUR_IN_SECONDS);
            self::set_stored_ai_credit_balance($credits);
        }
    }

    private static function get_stored_ai_credit_balance(){
        $credits = get_option('wpil_ai_last_known_credit_balance', null);
        return is_numeric($credits) ? round((float) $credits, 4) : null;
    }

    private static function set_stored_ai_credit_balance($credits = 0){
        update_option('wpil_ai_last_known_credit_balance', round(max(0, (float) $credits), 4), false);
    }

    private static function refresh_ai_credit_purchase_data(){
        // When the balance jumps, do a quiet subscription check so the new purchase rows get logged too.
        self::check_ai_subscription(true, true);
    }

    /**
     * Checks the AI subscription state and refreshes the stored data when needed
     **/
    public static function check_ai_subscription($refresh = false, $sync_purchases = true){
        $cached_subscription = get_transient('wpil_user_ai_subscription');
        $cached_credits = get_transient('wpil_ai_credit_balance');
        $stored_credits = self::get_stored_ai_credit_balance();
        $ai_id = trim((string) Wpil_Settings::get_linkwhisper_ai_user_id());

        if('no-credits' === $cached_credits){
            $cached_credits = 0;
        }elseif(is_numeric($cached_credits)){
            $cached_credits = round((float) $cached_credits, 4);
        }else{
            $cached_credits = (null !== $stored_credits) ? $stored_credits: null;
        }

        if(empty($ai_id)){
            return array(
                'success' => false,
                'code' => 'ai_id_missing',
                'subscription' => false,
                'credits' => null,
                'purchases_synced' => 0,
            );
        }

        if(!$refresh && !empty($cached_subscription)){
            return array(
                'success' => ('no-subscription' !== $cached_subscription),
                'code' => ('no-subscription' !== $cached_subscription) ? 'ok': 'no_subscription',
                'subscription' => ('no-subscription' !== $cached_subscription) ? $cached_subscription: false,
                'credits' => $cached_credits,
                'purchases_synced' => 0,
            );
        }

        $response = wp_remote_post(WPIL_STORE_URL . '/wp-json/lwasc-checkout/v1/get-subscription', array(
            'headers' => array('Content-Type' => 'application/json'),
            'body' => wp_json_encode(array('ai_id' => $ai_id)),
            'timeout' => 45,
        ));

        if(is_wp_error($response)){
            return array(
                'success' => false,
                'code' => 'request_failed',
                'subscription' => (!empty($cached_subscription) && 'no-subscription' !== $cached_subscription) ? $cached_subscription: false,
                'credits' => $cached_credits,
                'purchases_synced' => 0,
            );
        }

        $response_body = wp_remote_retrieve_body($response);
        $response = json_decode($response_body);
        if(empty($response_body) || (null === $response && JSON_ERROR_NONE !== json_last_error())){
            return array(
                'success' => false,
                'code' => 'request_failed',
                'subscription' => (!empty($cached_subscription) && 'no-subscription' !== $cached_subscription) ? $cached_subscription: false,
                'credits' => $cached_credits,
                'purchases_synced' => 0,
            );
        }

        $credits = $cached_credits;
        if(isset($response->credits) && is_numeric($response->credits)){
            $credits = round((float) $response->credits, 4);
            if($credits > 0){
                set_transient('wpil_ai_credit_balance', $credits, 60 * MINUTE_IN_SECONDS);
                self::set_stored_ai_credit_balance($credits);
            }else{
                set_transient('wpil_ai_credit_balance', 'no-credits', 60 * MINUTE_IN_SECONDS);
                self::set_stored_ai_credit_balance(0);
                $credits = 0;
            }
        }

        $purchases_synced = 0;
        if($sync_purchases && isset($response->purchases) && is_array($response->purchases)){
            foreach($response->purchases as $purchase){
                if(!is_object($purchase) && !is_array($purchase)){
                    continue;
                }

                $purchase = (object) $purchase;
                $purchase_id = isset($purchase->purchase_id) ? $purchase->purchase_id: '';
                $purchase_credits = isset($purchase->credits) ? $purchase->credits: 0;
                $purchase_type = isset($purchase->purchase_type) ? $purchase->purchase_type: '';
                $purchase_time = isset($purchase->purchase_time) ? $purchase->purchase_time: 0;

                if(self::save_credit_deposit_notice($purchase_id, $purchase_credits, $purchase_type, $purchase_time)){
                    $purchases_synced++;
                }
            }
        }

        if(isset($response->success) && !empty($response->success) && isset($response->subscription)){
            set_transient('wpil_user_ai_subscription', $response->subscription, 24 * HOUR_IN_SECONDS);

            return array(
                'success' => true,
                'code' => 'ok',
                'subscription' => $response->subscription,
                'credits' => $credits,
                'purchases_synced' => $purchases_synced,
            );
        }

        $no_subscription = false;
        if((isset($response->code) && 'no_subscription' === sanitize_key((string) $response->code)) ||
            (isset($response->message) && false !== stripos((string) $response->message, 'no subscription')) ||
            (isset($response->success) && !empty($response->success) && empty($response->subscription)))
        {
            $no_subscription = true;
        }

        if($no_subscription){
            set_transient('wpil_user_ai_subscription', 'no-subscription', 24 * HOUR_IN_SECONDS);

            return array(
                'success' => false,
                'code' => 'no_subscription',
                'subscription' => false,
                'credits' => $credits,
                'purchases_synced' => $purchases_synced,
            );
        }

        return array(
            'success' => false,
            'code' => 'request_failed',
            'subscription' => (!empty($cached_subscription) && 'no-subscription' !== $cached_subscription) ? $cached_subscription: false,
            'credits' => $credits,
            'purchases_synced' => $purchases_synced,
        );
    }

    /**
     * 
     **/
    public static function get_user_ai_subscription($reset = false){
        if(!self::$ai_service_connected){
            return null;
        }

        $subscription = get_transient('wpil_user_ai_subscription');
        if(empty($subscription) || $reset){
            $raw = wp_remote_post(WPIL_STORE_URL . '/wp-json/lwasc-checkout/v1/get-subscription', [
                'headers' => [ 'Content-Type' => 'application/json' ],
                'body'    => json_encode([ 'ai_id' => Wpil_Settings::get_linkwhisper_ai_user_id() ]),
                'timeout' => 45,
            ]);

            if(is_wp_error($raw) || empty(wp_remote_retrieve_body($raw))){
                // Network or server error — retry in 15 minutes, don't lock out for 24h
                set_transient('wpil_user_ai_subscription', 'no-subscription', 15 * MINUTE_IN_SECONDS);
                return false;
            }

            $response = json_decode(wp_remote_retrieve_body($raw));

            if(isset($response->success) && !empty($response->success) && isset($response->subscription)){
                $subscription = $response->subscription;
            }else{
                $subscription = 'no-subscription';
            }

            set_transient('wpil_user_ai_subscription', $subscription, 24 * HOUR_IN_SECONDS);
        }

        return ($subscription !== 'no-subscription') ? $subscription: false;
    }

    /**
     * 
     **/
    public static function ajax_setup_user_ai_subscription(){
        Wpil_Base::verify_nonce('setup-ai-subscription');
        $results = self::setup_user_ai_subscription(isset($_POST['recurring']) && !empty($_POST['recurring']));
        wp_send_json(['status' => $results]);
    }

    /**
     * 
     **/
    public static function setup_user_ai_subscription($recurring = false){
        if(!self::$ai_service_connected){
            return null;
        }

        // clear any balance transients that exist
        delete_transient('wpil_ai_credit_balance');

        // clear any OpenAI markers
        delete_option('wpil_is_free_ai_key');

        // get the subscription
        $subscription = self::get_user_ai_subscription(true);

        if(!empty($subscription) && empty($subscription->setting_new_sub) || (!$recurring && empty($subscription))){
            // refresh the credit count
            self::get_available_ai_credits(true);
        }

        return (!empty($subscription) && empty($subscription->setting_new_sub) || (!$recurring && empty($subscription))) ? 'subscription-setup': 'waiting-for-subscription';
    }

    /**
     * 
     **/
    public static function ajax_clear_user_ai_subscription(){
        Wpil_Base::verify_nonce('clear-ai-subscription');
        $results = self::clear_user_ai_subscription();
        wp_send_json(['status' => $results]);
    }

    /**
     * Unsets the active subscription so that the site stops showing the old subscription as the active one
     **/
    public static function clear_user_ai_subscription(){
        set_transient('wpil_user_ai_subscription', 'no-subscription', 24 * HOUR_IN_SECONDS);
        return 'subscription-cleared';
    }

    /**
     * Gets the auth return url that the wizard uses when it's time to finish the LW auth
     **/
    public static function get_wizard_ai_auth_return_url(){
        return admin_url('admin.php?page=link_whisper_ai_subscription&ai_auth_complete=1&origin=wizard');
    }

    /**
     * Gets the auth url that the wizard uses once the email has been verified
     **/
    public static function get_wizard_ai_auth_url($email = ''){
        $params = array(
            'free_activation' => '1',
            'origin' => 'wizard'
        );

        if(!empty($email) && is_email($email)){
            $params['uemail'] = $email;
        }

        return self::get_linkwhisper_ai_auth_url(self::get_wizard_ai_auth_return_url(), $params);
    }

    /**
     * Starts the free activation flow on Link Whisper's side
     **/
    public static function start_wizard_free_activation($email = ''){
        $email = sanitize_email($email);
        if(empty($email) || !is_email($email)){
            return array(
                'status' => 'invalid',
                'email' => '',
                'activation_token' => '',
                'auth_url' => '',
                'message' => __('Please enter a valid email address to connect Link Whisper AI.', 'wpil'),
            );
        }

        return self::run_wizard_free_activation_request('start-free-activation', array(
            'email' => $email,
            'site_url' => site_url(),
            'uid' => get_current_user_id(),
            'origin' => 'wizard',
            'free_activation' => 1,
        ), $email);
    }

    /**
     * Checks to see if the free activation email has been verified yet
     **/
    public static function check_wizard_free_activation($activation_token = '', $email = ''){
        $activation_token = sanitize_text_field($activation_token);
        $email = sanitize_email($email);

        if(empty($activation_token)){
            return array(
                'status' => 'expired',
                'email' => $email,
                'activation_token' => '',
                'auth_url' => '',
                'message' => __('This activation request has expired. Please enter your email again to start over.', 'wpil'),
            );
        }

        return self::run_wizard_free_activation_request('check-free-activation', array(
            'activation_token' => $activation_token,
        ), $email);
    }

    /**
     * Runs a free activation request against Link Whisper and cleans up the response so the wizard can use it
     **/
    private static function run_wizard_free_activation_request($path = '', $payload = array(), $email = ''){
        $path = trim((string) $path, '/');
        $email = sanitize_email($email);

        if(empty($path)){
            return array(
                'status' => 'invalid',
                'email' => $email,
                'activation_token' => '',
                'auth_url' => '',
                'message' => __('We could not start the Link Whisper AI activation right now. Please try again.', 'wpil'),
            );
        }

        $response = wp_remote_post(WPIL_STORE_URL . '/wp-json/lwasc-checkout/v1/' . $path, array(
            'headers' => array('Content-Type' => 'application/json'),
            'body' => wp_json_encode($payload),
            'timeout' => 45,
        ));

        if(is_wp_error($response)){
            return array(
                'status' => 'invalid',
                'email' => $email,
                'activation_token' => '',
                'auth_url' => '',
                'message' => __('We could not reach Link Whisper AI right now. Please try again in a moment.', 'wpil'),
            );
        }

        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);
        if(empty($response_body) || !is_array($response_data)){
            return array(
                'testing' => $response_body,
                'status' => 'invalid',
                'email' => $email,
                'activation_token' => '',
                'auth_url' => '',
                'message' => __('We got an unexpected response while starting Link Whisper AI. Please try again.', 'wpil'),
            );
        }

        $status = !empty($response_data['status']) ? sanitize_key($response_data['status']) : 'invalid';
        if(!in_array($status, array('connected', 'auth_ready', 'verification_required', 'rate_limited', 'blocked', 'invalid', 'expired'), true)){
            $status = 'invalid';
        }

        $response_email = !empty($response_data['email']) ? sanitize_email($response_data['email']) : $email;
        $activation_token = !empty($response_data['activation_token']) ? sanitize_text_field($response_data['activation_token']) : '';
        $auth_url = !empty($response_data['auth_url']) ? esc_url_raw($response_data['auth_url']) : '';

        if('connected' === $status){
            $status = 'auth_ready';
        }

        if('auth_ready' === $status && empty($auth_url)){
            $auth_url = self::get_wizard_ai_auth_url($response_email);
        }

        $message = !empty($response_data['message']) ? sanitize_text_field($response_data['message']) : '';
        if(empty($message)){
            $message = self::get_wizard_free_activation_message($status);
        }

        return array(
            'status' => $status,
            'email' => $response_email,
            'activation_token' => $activation_token,
            'auth_url' => $auth_url,
            'message' => $message,
        );
    }

    /**
     * Gives the wizard a nice default message for each activation state
     **/
    private static function get_wizard_free_activation_message($status = ''){
        switch($status){
            case 'auth_ready':
                return __('Your Link Whisper AI account is ready. Finish the secure popup to connect this site.', 'wpil');
            case 'verification_required':
                return __('Check your email for the Link Whisper verification message, then come back here and click the button below.', 'wpil');
            case 'expired':
                return __('This activation request has expired. Please enter your email again to start over.', 'wpil');
            case 'rate_limited':
                return __('We have received a few activation requests already. Please wait a minute and try again.', 'wpil');
            case 'blocked':
                return __('We could not start the free activation for this request. Please contact support if this keeps happening.', 'wpil');
            default:
                return __('We could not start the Link Whisper AI activation right now. Please try again.', 'wpil');
        }
    }

    /**
     * 
     **/
    public static function get_linkwhisper_ai_auth_url($return_url = null, $extra_params = array()){
        $params = array(
            'target' => base64_encode(Wpil_Rest::get_authenticated_rest_url(Wpil_Rest::AI_AUTH)),
            'return_url' => (!empty($return_url)) ? base64_encode($return_url): base64_encode(admin_url('admin.php?page=link_whisper_settings&tab=ai-settings&ai-subscription-check')),
            'site_url' => base64_encode(site_url()),
            'uid' => base64_encode(get_current_user_id())
        );

        if(!empty($extra_params) && is_array($extra_params)){
            foreach($extra_params as $key => $value){
                $key = sanitize_key($key);
                if(empty($key) || is_array($value) || is_object($value)){
                    continue;
                }

                if('uemail' === $key){
                    $value = sanitize_email($value);
                }else{
                    $value = sanitize_text_field((string) $value);
                }

                if('' === $value || $value === null){
                    continue;
                }

                $params[$key] = $value;
            }
        }

        $url = add_query_arg($params, WPIL_STORE_URL . '/connect-link-whisper-ai/');
        return $url;
    }

    /**
     * Guess the "about me/us" page.
     * Returns: ['post_id' => int|null, 'confidence' => 0..1, 'candidates' => array]
     */
    public static function guess_about_page($limit = 50){
        $exclude_slugs = [
            'contact', 'privacy-policy', 'terms', 'terms-and-conditions', 'checkout',
            'cart', 'my-account', 'account', 'shop', 'store', 'blog'
        ];

        $about_keywords = [
            'about', 'about me', 'about-me', 'about us', 'about-us',
            'bio', 'biography', 'my story', 'my-story', 'our story', 'our-story',
            'who i am', 'who-i-am', 'who we are', 'who-we-are',
            'team'
        ];

        $menu_page_ids = self::lw_collect_menu_page_ids();

        $pages = get_posts([
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'numberposts'    => $limit,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'suppress_filters' => true,
        ]);

        $front_id  = (int) get_option('page_on_front');
        $posts_id  = (int) get_option('page_for_posts');

        $scored = [];

        foreach ($pages as $page_id) {
            if ((int)$page_id === $front_id || (int)$page_id === $posts_id) {
                continue;
            }

            $post = get_post($page_id);
            if (!$post) continue;

            $slug  = '';
            $title = '';
            if(isset($post->post_name) && !empty($post->post_name)){
                $slug  = strtolower($post->post_name);
            }

            if(isset($post->post_title) && !empty($post->post_title)){
                $title = strtolower($post->post_title);
            }


            // Exclude obvious non-about pages
            foreach ($exclude_slugs as $bad) {
                if ($slug === $bad || strpos($slug, $bad) !== false) {
                    continue 2;
                }
            }

            $content = strtolower(wp_strip_all_tags($post->post_content ?? ''));
            $score = 0;
            $reasons = [];

            // Slug and title keyword scoring
            foreach ($about_keywords as $kw) {
                $kw_norm = strtolower($kw);

                if ($kw_norm && strpos($slug, str_replace(' ', '-', $kw_norm)) !== false) {
                    $score += 50;
                    $reasons[] = "slug-match:$kw_norm";
                } elseif ($kw_norm && strpos($title, $kw_norm) !== false) {
                    $score += 35;
                    $reasons[] = "title-match:$kw_norm";
                }
            }

            // Menu presence
            if (isset($menu_page_ids[$page_id])) {
                $score += 25;
                $reasons[] = "in-menu";

                $menu_info = $menu_page_ids[$page_id];
                if (!empty($menu_info['label']) && strpos(strtolower($menu_info['label']), 'about') !== false) {
                    $score += 25;
                    $reasons[] = "menu-label-about";
                }
                if (!empty($menu_info['is_top_level'])) {
                    $score += 10;
                    $reasons[] = "menu-top-level";
                }
            }

            // Page template hint
            $tpl = (string) get_post_meta($page_id, '_wp_page_template', true);
            if ($tpl && strpos(strtolower($tpl), 'about') !== false) {
                $score += 15;
                $reasons[] = "template-about";
            }

            // Content phrase hints
            $phrases = [
                "my name is", "hi i'm", "hi im", "i am ", "about me", "about us",
                "my story", "our story", "mission", "values", "background"
            ];
            foreach ($phrases as $p) {
                if (strpos($content, $p) !== false) {
                    $score += 8;
                    $reasons[] = "content-phrase:$p";
                }
            }

            // First person ratio
            $words = preg_split('/\s+/', trim($content));
            $word_count = is_array($words) ? count($words) : 0;

            if ($word_count > 50) {
                $first_person = 0;
                $first_person += preg_match_all('/\b(i|me|my|mine|im|i\'m|ive|i\'ve)\b/', $content, $m);
                $ratio = $first_person / max(1, $word_count);

                // Scale contribution: 0.01 ratio adds a little, 0.05 adds more
                $score += (int) min(20, max(0, $ratio * 400));
                $reasons[] = "first-person-ratio:" . round($ratio, 4);
            }

            // Mild penalty if content is extremely short
            if ($word_count > 0 && $word_count < 80) {
                $score -= 10;
                $reasons[] = "very-short";
            }

            $scored[] = [
                'post_id' => (int)$page_id,
                'score'   => (int)$score,
                'title'   => $post->post_title,
                'slug'    => $post->post_name,
                'reasons' => $reasons,
            ];
        }

        usort($scored, function($a, $b){ return $b['score'] <=> $a['score']; });

        $best = $scored[0] ?? null;
        $best_score = $best['score'] ?? 0;

        // Simple confidence: compare best vs second best, and absolute score
        $second_score = $scored[1]['score'] ?? 0;
        $gap = max(0, $best_score - $second_score);

        $confidence = 0.0;
        if ($best_score >= 40) {
            $confidence = min(1.0, 0.4 + ($best_score / 200) + ($gap / 120));
        }

        return [
            'post_id'     => $best['post_id'] ?? null,
            'confidence'  => round($confidence, 3),
            'candidates'  => array_slice($scored, 0, 10),
        ];
    }

    /**
     * Collect page IDs that appear in menus, plus label and top level status.
     * Returns: [page_id => ['label' => string, 'is_top_level' => bool]]
     */
    private static function lw_collect_menu_page_ids(){
        $out = [];

        $locations = get_nav_menu_locations();
        if (empty($locations) || !is_array($locations)) return $out;

        foreach ($locations as $loc => $menu_id) {
            if (!$menu_id) continue;

            $items = wp_get_nav_menu_items($menu_id);
            if (empty($items)) continue;

            foreach ($items as $item) {
                if (empty($item->object_id) || $item->object !== 'page') continue;

                $page_id = (int) $item->object_id;
                $label = (string) (isset($item->title) && !empty($item->title) ? $item->title: '');

                // Keep the “best” label if it contains about
                $is_about_label = (stripos($label, 'about') !== false);
                $is_top_level = empty($item->menu_item_parent) || (int)$item->menu_item_parent === 0;

                if (!isset($out[$page_id])) {
                    $out[$page_id] = ['label' => $label, 'is_top_level' => $is_top_level];
                } else {
                    if ($is_about_label) $out[$page_id]['label'] = $label;
                    $out[$page_id]['is_top_level'] = $out[$page_id]['is_top_level'] || $is_top_level;
                }
            }
        }

        return $out;
    }

    /**
     * Gets the linking recommendations for a specfic post.
     * 
     * @param $post_id the ide of the post that we're linking from
     * @param $process_key The id of the mapp that we're pulling the data from
     * @param $direction are these inbound or outbound links? Default, outbound
     **/
    public static function create_link_suggestions_from_map($post_id, $process_key = '', $direction = 'outbound'){
        if(empty($post_id) || empty($process_key)){
            return false;
        }

        $pid = self::normalize_pid($post_id);
        $pid_parts = self::parse_pid($pid);
        if(empty($pid_parts['id'])){
            return false;
        }

        $post_id = $pid_parts['id'];
        $post_type = $pid_parts['type'];

        // dashboard fixes should treat money pages as inbound-only so we don't send links out from them
        if($direction === 'outbound' && self::should_skip_dashboard_fix_money_page_source($pid, $process_key)){
            return false;
        }

        $work_scope = '';
        $scoped_process_keys = array(md5('link-coverage-search'), md5('custom-link-map'));
        if(in_array($process_key, $scoped_process_keys, true) && in_array($direction, array('outbound', 'inbound'), true)){
            $work_scope = Wpil_LinkMapping::normalize_relation_work_scope($direction);
        }

        $map_item = Wpil_LinkMapping::get_relation_map_item($process_key, $post_id, $post_type, true, false, $work_scope);
        $local_model = 'gpt-4o-mini';

        if( empty($map_item) || 
            !isset($map_item['related_posts']) || 
            empty($map_item['related_posts'])
        ){
            return false;
        }

        // make sure that we're not processing content that's unlikely to be linked
        add_filter('wpil_filter_ignore_linking_tags', function($tags){ return array_merge($tags, ['pre', 'code']);});

        $relations = self::normalize_pid_list($map_item['related_posts']);
        // If this relationship already has a live suggestion, leave it alone until review/autocheck handles it.
        $active_suggestion_pids = self::get_linking_suggestion_processed_ids('', $direction, $pid);
        if(!empty($active_suggestion_pids)){
            $active_suggestion_lookup = array_flip(self::normalize_pid_list($active_suggestion_pids));
            foreach($relations as $key => $relation_pid){
                if(isset($active_suggestion_lookup[$relation_pid])){
                    unset($relations[$key]);
                }
            }

            $relations = array_values($relations);
            if(empty($relations)){
                return false;
            }
        }

        $data = [];
        $input = [];

        // get the post that we'll be focussing on
        $post = new Wpil_Model_Post($post_id, $post_type);

        // make sure the current post has a title
        if(empty($post->getTitle())){
            return false; /// exist if it doesn't
        }

        if($direction === 'outbound' && self::has_reached_ai_outbound_processing_limit($post)){
            return false;
        }

        // if we're creating links in this post pointing to other posts
        if($direction === 'outbound'){
            // check to make sure that we're reasonably sure that we can create links in the post
            $phrases = Wpil_Suggestion::getPhrases($post->getContent(), false, array(), false, array(), true);
            if(empty($phrases)){
                return false; // if we can't, oh darn!
            }

            // get the post's current links
            // if we're preventing twoway linking
            if(get_option('wpil_prevent_two_way_linking', false)){
                // get the inbound && the outbound internal links
                $post_links = Wpil_Post::getLinkedPostIDs($post, true);
            }else{
                $report_links = Wpil_Report::getReportOutboundLinks($post)['internal'];
                $post_links = [];

                if(!empty($report_links)){
                    foreach($report_links as $dat){
                        if(empty($dat->post) || empty($dat->post->id)){
                            continue;
                        }
                        $post_links[] = $dat->post->id;
                    }
                }
            }


            // get the post's keywrods
            $post_keywords = Wpil_TargetKeyword::get_active_keyword_list($post_id, $post_type);

            // set the origin post id
            self::$origin_post = $post;

            // get the keywords for the posts that we'll be mapping to
            $target_data = [];
            foreach($relations as $relation_pid){
                $relation_parts = self::parse_pid($relation_pid);
                if(empty($relation_parts['id'])){
                    continue;
                }
                $target_data[$relation_parts['type'] . '_' . $relation_parts['id']] = [
                    'id' => $relation_parts['id'],
                    'type' => $relation_parts['type'],
                    'keywords' => Wpil_TargetKeyword::get_active_keyword_list($relation_parts['id'], $relation_parts['type'])
                ];
            }

            $phrases = self::filter_ai_source_phrases_for_keyword_cannibalization($phrases, $post, array_keys($target_data));
            $content = self::get_ai_source_content_from_phrases($phrases);

            if(empty($content)){
                return false;
            }

            $input['targets'] = [];
            $target_posts = array();
            $source_content = self::trim_text_to_token_limit($content, $local_model, 20000);
            $token_size = 300 + self::count_tokens($source_content . implode(',', $post_keywords), $local_model);
            $limit = 100000;
            $target_limit = 15;
            foreach($target_data as $target_pid => $target){
                if(count($input['targets']) > $target_limit){
                    break;
                }

                if(in_array($target['id'], $post_links)){
                    continue;
                }

                $tp = new Wpil_Model_Post($target['id'], $target['type']);

                $title = $tp->getTitle();
                if(empty($title)){
                    continue;
                }

                // if there are no distantly related sentences
                if(empty(Wpil_Phrase::filter_unrelated_phrases($phrases, '', $tp))){
                    continue; // skip to the next
                }

                $dat = [
                    'title' => $title,
                    'keywords' => !empty($target['keywords']) ? $target['keywords']: [],
                    'target_id' => $tp->id,
                    'target_type' => $tp->type
                ];

                $query = self::count_tokens(($dat['title'] . ((!empty($dat['keywords']) && is_array($dat['keywords'])) ? implode(',', $dat['keywords']): '') . $dat['target_id']), $local_model);
                if($token_size + $query < $limit){
                    $token_size += $query;
                    $input['targets'][] = $dat;
                    $target_posts[] = $tp;
                }else{
                    break;
                }
            }

            // if there are no viable targets
            if(empty($input['targets'])){
                return false; // exist
            }

            $source_content = self::filter_ai_source_content_by_sentence_relatedness($source_content, $target_posts);
            if(empty($source_content)){
                return false;
            }

            // assemble the data object
            $input['source'] = ['content' => self::trim_text_to_token_limit($source_content, $local_model, 20000), 'keywords' => $post_keywords];

            // json encode the whole thinkg
            $input = json_encode($input, JSON_UNESCAPED_UNICODE);

            // if there was an error
            if(empty($input) || !empty(json_last_error())){
                return false; // todo: maybe consider some error logging here!
            }

            // log the query id
            self::$query_ids[] = $post->get_pid();

            // send off our carefully assembled data!
            $data = self::determine_linking_candidates($input, $direction, $process_key);
            // and save the results!
            self::save_ai_outbound_linking_suggestions($data, $post, $process_key);
        }elseif($direction === 'inbound'){ // if we're trying to point links to the current post
            // get the existing links
            $internal_links = Wpil_Post::getLinkedPostIDs($post, true);

            // get the post's keywrods
            $post_keywords = Wpil_TargetKeyword::get_active_keyword_list($post_id, $post_type);

            // get the keywords for the posts that we'll be mapping to
            // limit
            $limit = 6;
            $runtime = Wpil_LinkMapping::get_relation_map_runtime_state($map_item);
            $searched_pids = !empty($runtime['inbound_searched_pids']) && is_array($runtime['inbound_searched_pids']) ? self::normalize_pid_list($runtime['inbound_searched_pids']) : array();
            $searched_lookup = array();
            foreach($searched_pids as $searched_pid){
                $searched_lookup[$searched_pid] = true;
            }

            $pass_pids = array();
            $remaining_pids = array();

            // assemble the data objects
            foreach($relations as $relation_pid){
                $relation_pid = self::normalize_pid($relation_pid);
                if(empty($relation_pid) || isset($searched_lookup[$relation_pid])){
                    continue;
                }

                if(count($pass_pids) >= $limit){
                    $remaining_pids[] = $relation_pid;
                    continue;
                }

                $pass_pids[] = $relation_pid;

                $relation_parts = self::parse_pid($relation_pid);
                if(empty($relation_parts['id']) || in_array($relation_parts['id'], $internal_links)){
                    continue;
                }

                $source_pid = $relation_parts['type'] . '_' . $relation_parts['id'];
                if(self::should_skip_dashboard_fix_money_page_source($source_pid, $process_key)){
                    continue;
                }

                $tp = new Wpil_Model_Post($relation_parts['id'], $relation_parts['type']);
                if(self::has_reached_ai_outbound_processing_limit($tp)){
                    continue;
                }

                // check to make sure that we're reasonably sure that we can create links in the post
                $phrases = Wpil_Suggestion::getPhrases($tp->getContent(), false, array(), false, array(), true);
                $phrases = self::filter_ai_source_phrases_for_keyword_cannibalization($phrases, $tp, array($post->get_pid()));
                $phrases = Wpil_Phrase::filter_unrelated_phrases($phrases, '', $post);
                $content = self::get_ai_source_content_from_phrases($phrases);

                if(empty($content)){
                    continue; // if we can't, skip to the next
                }

                $content = self::filter_ai_source_content_by_sentence_relatedness($content, array($post));
                if(empty($content)){
                    continue; // if none of the sentences fit, skip to the next
                }

                $kwords = Wpil_TargetKeyword::get_active_keyword_list($tp->id, $tp->type);

                $dat = json_encode([
                    'source' => [
                        'content' => self::trim_text_to_token_limit($content, 'gpt-4o-mini', 25000),
                        'keywords' => (!empty($kwords) ? $kwords: [])
                    ],
                    'target' => [
                        'title' => $post->getTitle(),
                        'keywords' => !empty($post_keywords) ? $post_keywords: [],
                        'target_id' => $post->id,
                        'target_type' => $post->type
                    ]
                ], JSON_UNESCAPED_UNICODE);

                // if there was an error
                if(empty($dat) || !empty(json_last_error())){
                    // skip to hte next post
                    continue; // todo: maybe consider some error logging here!
                }

                self::$query_ids[] = $tp->get_pid();
                $input[] = $dat;
            }

            $retry_meta = array(
                'searched_pids' => $pass_pids,
                'remaining_pids' => $remaining_pids,
                'relation_count' => count($relations),
            );

            // if there is no input
            if(empty($input)){
                return array(
                    'data' => array(),
                    'meta' => $retry_meta,
                ); // todo: maybe consider some error logging here!
            }

            // send off our carefully assembled data!
            $data = self::determine_linking_candidates($input, $direction, $process_key);
            $data = array(
                'data' => $data,
                'meta' => $retry_meta,
            );
        }

        return $data;
    }
    
    /**
     * Uses teh spooky power of AI to determine which and wehere links should be made!
     **/
    public static function determine_linking_candidates($data = array(), $direction = 'inbound', $process_key = ''){
        global $wpdb;

        // get the max outbound links for a post
        $max_outbound = (int)get_option('wpil_max_links_per_post', 0);

        // get the max inbound links for a post
        $max_inbound = (int)get_option('wpil_max_inbound_links_per_post', 0);

        // get if we're allowed to rewrite sentences for better fits
        $rewrite_sentences = 0; // TODO: implement a setting later

        /*
        basic structure
        $data[
            source => [
                'content' => '',
                'keywords' => []
            ]        
            targets[
                [
                    'title' => '',
                    'keywords' => []
                ],
                [
                    'title' => '',
                    'keywords' => []
                ],
                [
                    'title' => '',
                    'keywords' => []
                ],
            ]
        ]

        */

        // and call!
        self::$purpose = ($direction === 'outbound') ? 'assess-outbound-links': 'assess-inbound-links';
        $prev_process_key = self::$active_linking_process_key;
        self::$active_linking_process_key = (string) $process_key;
        try{
            $results = self::call_linkwhisper_ai($data, 'gpt-5-mini', '', ['rewrite_sentences' => $rewrite_sentences, 'max_outbound_links' => $max_outbound, 'max_inbound_links' => $max_inbound]);
            self::save_response_tokens($results, self::$purpose);
        }finally{
            self::$active_linking_process_key = $prev_process_key;
        }

        // return the fruits of our labours!
        return $results;
    }

    /**
     * Filters source sentences with the quick relatedness check before we spend the bigger AI call on them.
     **/
    private static function filter_ai_source_content_by_sentence_relatedness($source_content = '', $target_posts = array()){
        if(!Wpil_Settings::get_linkwhisper_ai_active() || !Wpil_Settings::get_linkwhisper_ai_token()){
            return $source_content;
        }

        if(empty($source_content) || empty($target_posts)){
            return $source_content;
        }

        if(!is_array($target_posts)){
            $target_posts = array($target_posts);
        }
        $sentences = self::get_ai_sentence_relatedness_items_from_content($source_content);
        if(empty($sentences)){
            return $source_content;
        }

        // Quick word-overlap relatedness check that skips the AI call entirely.
        // A sentence sharing 2 or fewer words with every target is unrelated (dropped);
        // sharing 5 or more with any target is related (kept). Anything in between is kept too.
        $target_contents = array();
        foreach($target_posts as $target_post){
            if(empty($target_post) || !is_object($target_post) || !method_exists($target_post, 'getContent')){
                continue;
            }

            $content = self::get_ai_relatedness_content_from_post($target_post);
            if(!empty($content)){
                $target_contents[] = $content;
            }
        }

        if(!empty($target_contents)){
            $kept = array();
            foreach($sentences as $sentence){
                foreach($target_contents as $target_content){
                    // more than 2 shared words with any target and we keep the sentence (5+ is a sure thing)
                    if(Wpil_Word::content_contains($sentence['compare'], $target_content, 3)){
                        $kept[] = $sentence['compare'];
                        break;
                    }
                }
            }

            return !empty($kept) ? implode('|||', $kept) : '';
        }else{
            return $source_content;
        }
    }

    /**
     * Cleans up a post's content for the relatedness check.
     **/
    private static function get_ai_relatedness_content_from_post($post = null){
        if(empty($post) || !is_object($post) || !method_exists($post, 'getContent')){
            return '';
        }

        $content = $post->getContent(false);
        $content = strip_tags($content, '<h1><h2><h3><h4><h5><h6><title><ul><ol><li>');
        $content = mb_ereg_replace('(([a-zA-Z\-_0-9]+="[^"]*")+?[\s]?)', ' ', $content);
        $content = mb_ereg_replace("&nbsp;", ' ', $content);
        $content = mb_ereg_replace("[\s]+", ' ', $content);

        return self::trim_text_to_token_limit($content, 'gpt-5-nano', 8000);
    }

    /**
     * Splits the source content back into clean sentence packets.
     **/
    private static function get_ai_sentence_relatedness_items_from_content($content = ''){
        if(empty($content) || !is_string($content)){
            return array();
        }

        $items = array();
        $sentences = explode('|||', $content);
        $min_anchor_length = Wpil_Settings::getSuggestionMinAnchorSize(3);
        foreach($sentences as $sentence){
            if(!is_scalar($sentence) || empty(trim((string)$sentence))){
                continue;
            }

            $sentence = trim(preg_replace('/[\s]+/', ' ', strip_tags((string)$sentence)));
            if(empty($sentence) || Wpil_Word::getWordCount($sentence, true) < $min_anchor_length){
                continue;
            }

            $items[] = array(
                'compare' => $sentence,
            );
        }

        return $items;
    }

    private static function normalize_pid($pid){
        if(empty($pid)){
            return '';
        }

        if(is_numeric($pid)){
            return 'post_' . (int)$pid;
        }

        if(is_string($pid) && strpos($pid, '_') !== false){
            return $pid;
        }

        return '';
    }

    public static function normalize_pid_list($ids = []){
        if(empty($ids)){
            return [];
        }

        if(!is_array($ids)){
            $ids = [$ids];
        }

        $out = [];
        foreach($ids as $id){
            $pid = self::normalize_pid($id);
            if(!empty($pid)){
                $out[] = $pid;
            }
        }

        return array_values(array_unique($out));
    }

    private static function parse_pid($pid){
        $out = [
            'type' => 'post',
            'id' => 0,
            'pid' => ''
        ];

        $pid = self::normalize_pid($pid);
        if(empty($pid)){
            return $out;
        }

        $bits = explode('_', $pid, 2);
        if(count($bits) === 2){
            $type = $bits[0];
            $id = (int)$bits[1];
            if($type === 'term'){
                $out['type'] = 'term';
            }
            $out['id'] = $id;
            $out['pid'] = $out['type'] . '_' . $id;
        }

        return $out;
    }

    private static function parse_linking_target($result){
        $target_type = '';
        $target_id = 0;

        if(is_object($result)){
            if(isset($result->target_type)){
                $target_type = (string)$result->target_type;
            }
            if(isset($result->target_id)){
                $target_id = $result->target_id;
            }
        }else{
            $target_id = $result;
        }

        if(is_string($target_id) && strpos($target_id, '_') !== false){
            $parts = self::parse_pid($target_id);
            if(!empty($parts['id'])){
                $target_id = $parts['id'];
                $target_type = $parts['type'];
            }
        }

        if(empty($target_type)){
            $target_type = 'post';
        }

        return [
            'id' => (int)$target_id,
            'type' => $target_type,
            'data_type' => ($target_type === 'post') ? 1 : 0
        ];
    }

    /**
     * Retuns a list of the currently available Dashboartd fix with ai actions
     **/
    public static function get_dashboard_fix_process_keys(){
        return array(
            md5('orphan-post-search'),
            md5('link-coverage-search'),
            md5('link-quality-search'),
            md5('broken-link-search'),
            md5('external-focus-search'),
        );
    }

    /**
     * Returns true when the process key belongs to orphaned-post fixing.
     **/
    private static function is_orphan_fix_process_key($process_key = ''){
        $process_key = is_string($process_key) ? sanitize_text_field($process_key) : '';
        if($process_key === ''){
            return false;
        }

        return ($process_key === md5('orphan-post-search'));
    }

    /**
     * Lets us tell when a process came from one of the dashboard fix tools.
     **/
    private static function is_dashboard_fix_process_key($process_key = ''){
        $process_key = is_string($process_key) ? sanitize_text_field($process_key) : '';
        if($process_key === ''){
            return false;
        }

        return in_array($process_key, self::get_dashboard_fix_process_keys(), true);
    }

    /**
     * Checks if the pid belongs to one of the pages we want to keep inbound-only.
     **/
    private static function is_money_page_pid($pid = ''){
        $parts = self::parse_pid($pid);
        if(empty($parts['id'])){
            return false;
        }

        return in_array($parts['pid'], Wpil_Settings::get_money_page_pid_list(true), true);
    }

    /**
     * Dashboard fix runs can still target money pages, but they shouldn't use them as source posts.
     * Exception: the custom CSV linking map honours explicitly specified outbound sources regardless
     * of money-page status, since the user has deliberately chosen them.
     **/
    private static function should_skip_dashboard_fix_money_page_source($pid = '', $process_key = ''){
        if($process_key === md5('custom-link-map')){
            return false;
        }
        return (self::is_dashboard_fix_process_key($process_key) && self::is_money_page_pid($pid));
    }

    /**
     * Pulls the sentence text out of the phrase objects so we can hand it off to the AI.
     **/
    public static function get_ai_source_content_from_phrases($phrases = array()){
        if(empty($phrases) || !is_array($phrases)){
            return '';
        }

        $sentences = array();
        $searched = [];
        foreach($phrases as $phrase){
            if(!is_object($phrase)){
                continue;
            }

            $sentence = '';
            if(isset($phrase->sentence_text) && !empty($phrase->sentence_text)){
                $sentence = $phrase->sentence_text;
            }elseif(isset($phrase->text) && !empty($phrase->text)){
                $sentence = $phrase->text;
            }

            if(!empty($sentence) && !in_array(md5($sentence), $searched)){ // make sure that we only scan the searchable content once
                $sentences[] = $sentence;
                $searched[] = md5($sentence);
            }
        }

        return !empty($sentences) ? implode('|||', $sentences): '';
    }

    /**
     * When the setting is on, strip out any source sentences that are trying to rank another post's keyword.
     * We still keep sentences that are talking about the current post or the target post we're linking to.
     **/
    public static function filter_ai_source_phrases_for_keyword_cannibalization($phrases = array(), $source_post = null, $allowed_target_pids = array()){
        if(empty($phrases) || !is_array($phrases) || !is_a($source_post, 'Wpil_Model_Post') || !Wpil_Settings::get_prevent_keyword_cannibalization()){
            return $phrases;
        }

        $keyword_data = self::get_keyword_cannibalization_keyword_data();
        if(empty($keyword_data['keywords'])){
            return $phrases;
        }

        $allowed_lookup = array();
        $allowed_lookup[$source_post->get_pid()] = true;
        if(!empty($allowed_target_pids)){
            foreach($allowed_target_pids as $pid){
                $parts = self::parse_pid($pid);
                if(empty($parts['id'])){
                    continue;
                }

                $allowed_lookup[$parts['pid']] = true;
            }
        }

        $filtered = array();
        $searched = [];
        foreach($phrases as $phrase){
            if(!is_object($phrase)){
                continue;
            }

            $sentence = '';
            if(isset($phrase->sentence_text) && !empty($phrase->sentence_text)){
                $sentence = $phrase->sentence_text;
            }elseif(isset($phrase->text) && !empty($phrase->text)){
                $sentence = $phrase->text;
            }

            if(empty($sentence) || in_array(md5($sentence), $searched)){ // make sure that we only scan the searchable content once
                continue;
            }

            $searched[] = md5($sentence);

            $matched_owner_pids = self::get_ai_sentence_keyword_owner_pids($sentence, $keyword_data['keywords'], $keyword_data['more_specific_keywords']);
            if(empty($matched_owner_pids)){
                $filtered[] = $phrase;
                continue;
            }

            $keep_phrase = true;
            foreach($matched_owner_pids as $pid => $matched){
                if(empty($allowed_lookup[$pid])){
                    $keep_phrase = false;
                    break;
                }
            }

            if($keep_phrase){
                $filtered[] = $phrase;
            }
        }

        return $filtered;
    }

    /**
     * Loads the active keyword data once so we don't keep hammering the DB on every AI call.
     **/
    private static function get_keyword_cannibalization_keyword_data(){
        if(!is_null(self::$keyword_cannibalization_keywords)){
            return self::$keyword_cannibalization_keywords;
        }

        $keywords = Wpil_TargetKeyword::get_all_active_keywords([],[],['ai-generated-keyword']);

        $formatted_keywords = array();
        if(!empty($keywords)){
            foreach($keywords as $keyword){
                $formatted = is_a($keyword, 'Wpil_Model_Keyword') ? $keyword: new Wpil_Model_Keyword($keyword);
                if(empty($formatted->post_id) || empty($formatted->keywords)){
                    continue;
                }

                $formatted_keywords[] = $formatted;
            }
        }

        self::$keyword_cannibalization_keywords = array(
            'keywords' => $formatted_keywords,
            'more_specific_keywords' => []//!empty($formatted_keywords) ? Wpil_Suggestion::getMoreSpecificKeywords($formatted_keywords, $formatted_keywords): array(),
        );

        return self::$keyword_cannibalization_keywords;
    }

    /**
     * Mirrors the regular suggestion matching so the AI flow respects the same target keyword guard rails.
     **/
    private static function get_ai_sentence_keyword_owner_pids($sentence = '', $target_keywords = array(), $more_specific_keywords = array()){
        if(empty($sentence) || empty($target_keywords)){
            return array();
        }

        $matched_posts = array();
        $sentence = Wpil_Word::strtolower($sentence);
        $stemmed_sentence = Wpil_Word::getStemmedSentence($sentence);
        $normalized_sentence = Wpil_Word::getStemmedSentence(Wpil_Word::remove_accents($stemmed_sentence), true);

        foreach($target_keywords as $keyword){
            if(empty($keyword) || empty($keyword->post_id) || empty($keyword->keywords) || 3 > strlen($keyword->keywords) || $keyword->keyword_type === 'ai-generated-keyword'){
                continue;
            }

            if(!self::ai_sentence_has_target_keyword_match($sentence, $stemmed_sentence, $normalized_sentence, $keyword)){
                continue;
            }

            if(isset($more_specific_keywords[$keyword->stemmed])){
                $skip_keyword = false;
                foreach($more_specific_keywords[$keyword->stemmed] as $specific_keyword){
                    if(self::ai_sentence_has_target_keyword_match($sentence, $stemmed_sentence, $normalized_sentence, $specific_keyword)){
                        $skip_keyword = true;
                        break;
                    }
                }

                if($skip_keyword){
                    continue;
                }
            }

            $matched_posts[(($keyword->post_type === 'term') ? 'term_' : 'post_') . (int) $keyword->post_id] = true;
        }

        return $matched_posts;
    }

    /**
     * Checks the sentence against a keyword using the same gentle matching rules as the normal suggestion builder.
     **/
    private static function ai_sentence_has_target_keyword_match($sentence = '', $stemmed_sentence = '', $normalized_sentence = '', $target_keyword = null){
        if(empty($target_keyword) || empty($target_keyword->keywords) || empty($target_keyword->stemmed)){
            return false;
        }

        $keyword = Wpil_Word::strtolower($target_keyword->keywords);
        $in_unstemmed = false !== strpos($sentence, $keyword);
        $in_stemmed = false !== strpos($stemmed_sentence, $target_keyword->stemmed);
        $in_normalized = false;

        if(!$in_unstemmed && !$in_stemmed){
            $in_normalized = !empty($normalized_sentence) && !empty($target_keyword->normalized) && false !== strpos($normalized_sentence, $target_keyword->normalized);
        }

        if(!$in_unstemmed && !$in_stemmed && !$in_normalized){
            return false;
        }

        if($in_unstemmed && !$in_stemmed){
            $pos = Wpil_Word::mb_strpos($sentence, $keyword);
            if(Wpil_Word::isPartOfWord($sentence, $keyword, $pos)){
                return false;
            }
        }

        if($in_stemmed){
            $pos = Wpil_Word::mb_strpos($stemmed_sentence, $target_keyword->stemmed);
            if(Wpil_Word::isPartOfWord($stemmed_sentence, $target_keyword->stemmed, $pos)){
                return false;
            }
        }

        if($in_normalized){
            $pos = Wpil_Word::mb_strpos($normalized_sentence, $target_keyword->normalized);
            if(Wpil_Word::isPartOfWord($normalized_sentence, $target_keyword->normalized, $pos)){
                return false;
            }
        }

        return true;
    }

    /**
     * Once a post hits the outbound cap, leave it alone for AI outbound work.
     **/
    private static function has_reached_ai_outbound_processing_limit($post = null){
        if(!is_a($post, 'Wpil_Model_Post')){
            return false;
        }

        $limit = (int) Wpil_Settings::get_ai_suggestion_outbound_limit();
        if($limit < 1){
            return false;
        }

        return ($post->getOutboundInternalLinks(true) >= $limit);
    }

    /**
     * Live orphan check for target items used in AI suggestions.
     **/
    private static function is_orphaned_target_for_ai_suggestion($target_id = 0, $target_type = 'post'){
        $target_id = (int) $target_id;
        $target_type = ($target_type === 'term') ? 'term' : 'post';
        if($target_id <= 0){
            return false;
        }

        $target_state = self::get_live_target_state($target_id, $target_type);

        return !empty($target_state['orphan_eligible']);
    }

    /**
     * Gets the current live link state for a target post/term.
     **/
    private static function get_live_target_state($target_id = 0, $target_type = 'post'){
        $target_id = (int) $target_id;
        $target_type = ($target_type === 'term') ? 'term' : 'post';
        $state = array(
            'inbound_internal' => 0,
            'outbound_internal' => 0,
            'outbound_external' => 0,
            'orphan_eligible' => false,
        );

        if($target_id <= 0){
            return $state;
        }

        $target_post = new Wpil_Model_Post($target_id, $target_type);
        if(empty($target_post) || !$target_post->check_if_post_exists()){
            return $state;
        }

        $state['inbound_internal'] = (int) $target_post->getInboundInternalLinks(true);
        $state['outbound_internal'] = (int) $target_post->getOutboundInternalLinks(true);
        $state['outbound_external'] = (int) $target_post->getOutboundExternalLinks(true);
        $state['orphan_eligible'] = ($state['inbound_internal'] <= 0);

        return $state;
    }

    /**
     * Builds the review modal display data for a post or term.
     **/
    private static function get_review_post_display_data($post = null, $ai_relatedness = ''){
        $data = array(
            'title' => '',
            'type' => '',
            'taxonomy' => '',
            'categories' => '',
            'tags' => '',
            'inbound_internal' => 0,
            'outbound_internal' => 0,
            'outbound_external' => 0,
            'post_id' => 0,
            'language' => '',
            'view_link' => '',
            'ai_relatedness' => $ai_relatedness
        );

        if(empty($post) || !is_a($post, 'Wpil_Model_Post') || !$post->check_if_post_exists()){
            return $data;
        }

        $categories = '';
        $tags = '';
        if($post->type === 'post'){
            $taxonomies = Wpil_Settings::getTermTypes();
            $terms = get_terms(array(
                'taxonomy' => $taxonomies,
                'hide_empty' => false,
                'object_ids' => $post->id,
            ));

            $categories_list = array();
            $tags_list = array();
            if(!is_wp_error($terms) && !empty($terms)){
                foreach($terms as $term){
                    $taxonomy = get_taxonomy($term->taxonomy);
                    if($taxonomy && $taxonomy->hierarchical){
                        $categories_list[] = $term->name;
                    }else{
                        $tags_list[] = $term->name;
                    }
                }
            }

            if(!empty($categories_list)){
                $categories = implode(', ', $categories_list);
            }
            if(!empty($tags_list)){
                $tags = implode(', ', $tags_list);
            }
        }

        $state = self::get_live_target_state((int) $post->id, (string) $post->type);
        $view_link = $post->getLinks()->view;
        if(!empty($view_link)){
            $view_link = Wpil_Link::filter_staging_to_live_domain($view_link);
        }

        $data = array(
            'title' => $post->getTitle(),
            'type' => $post->getType(),
            'taxonomy' => ($post->type === 'term') ? $post->getRealType() : '',
            'categories' => $categories,
            'tags' => $tags,
            'inbound_internal' => (int) $state['inbound_internal'],
            'outbound_internal' => (int) $state['outbound_internal'],
            'outbound_external' => (int) $state['outbound_external'],
            'post_id' => (int) $post->id,
            'language' => Wpil_Post::getPostLanguageCode($post),
            'view_link' => $view_link,
            'ai_relatedness' => $ai_relatedness
        );

        return $data;
    }

    /**
     * Checks if the suggestion's source now links to the target in the live report data.
     **/
    private static function did_suggestion_create_live_link($suggestion){
        if(empty($suggestion) || !is_object($suggestion) || empty($suggestion->post_id) || empty($suggestion->post_type) || empty($suggestion->target_id) || empty($suggestion->target_type)){
            return false;
        }

        $source_post = new Wpil_Model_Post((int) $suggestion->post_id, (string) $suggestion->post_type);
        if(empty($source_post) || !$source_post->check_if_post_exists()){
            return false;
        }

        $outbound_links = Wpil_Report::link_table_is_created() ? Wpil_Report::getReportOutboundLinks($source_post) : Wpil_Report::getOutboundLinks($source_post);
        $internal_links = (!empty($outbound_links['internal']) && is_array($outbound_links['internal'])) ? $outbound_links['internal'] : array();
        if(empty($internal_links)){
            return false;
        }

        foreach($internal_links as $link){
            if(empty($link->post) || empty($link->post->id) || empty($link->post->type)){
                continue;
            }

            if((int) $link->post->id === (int) $suggestion->target_id && (string) $link->post->type === (string) $suggestion->target_type){
                return true;
            }
        }

        return false;
    }

    /**
     * Applies a suggestion and reports what actually happened.
     **/
    private static function apply_linking_suggestion($suggestion, $ignore_on_fail = false){
        global $wpdb;
        $linking_table = $wpdb->prefix . 'wpil_ai_linking';

        $response = array(
            'applied' => false,
            'stale' => false,
            'message' => '',
            'reason' => '',
            'process_key' => (!empty($suggestion->process_key)) ? (string) $suggestion->process_key : '',
            'link_id' => (!empty($suggestion->ai_index)) ? (int) $suggestion->ai_index : 0,
        );

        if(empty($suggestion) || !is_object($suggestion) || empty($suggestion->ai_index)){
            $response['message'] = 'Suggestion not found.';
            $response['reason'] = 'missing_suggestion';
            return $response;
        }

        $process_key = !empty($suggestion->process_key) ? (string) $suggestion->process_key : '';
        if(self::is_orphan_fix_process_key($process_key)){
            $target_state = self::get_live_target_state((int) $suggestion->target_id, (string) $suggestion->target_type);
            if(empty($target_state['orphan_eligible'])){
                $wpdb->update($linking_table, ['ignored' => 1], ['ai_index' => (int) $suggestion->ai_index]);
                $response['stale'] = true;
                $response['message'] = 'The target already has inbound links.';
                $response['reason'] = 'target_not_orphaned';
                return $response;
            }
        }

        $target_link = self::build_target_link_from_suggestion($suggestion);
        if(empty($target_link)){
            if($ignore_on_fail){
                $wpdb->update($linking_table, ['ignored' => 1], ['ai_index' => (int) $suggestion->ai_index]);
            }

            $response['message'] = 'Unable to build the proposed link from the current suggestion data.';
            $response['reason'] = 'invalid_target_link';
            return $response;
        }

        $meta = [[
            'id' => $suggestion->target_id,
            'type' => $suggestion->target_type,
            'post_origin' => 'internal',
            'site_url' => '',
            'sentence' => $suggestion->sentence_text,
            'sentence_with_anchor' => '',
            'custom_sentence' => $target_link,
        ]];

        Wpil_Base::clear_tracked_action('link_inserted');

        if(!empty($suggestion->post_type) && $suggestion->post_type === 'term'){
            Wpil_Toolbox::update_encoded_term_meta($suggestion->post_id, 'wpil_links', $meta);
            Wpil_Term::addLinksToTerm($suggestion->post_id);
        }else{
            Wpil_Post::addLinksToContent(null, ['ID' => $suggestion->post_id], array(), false, true, $meta);
        }

        $applied = Wpil_Base::action_happened('link_inserted') || self::did_suggestion_create_live_link($suggestion);
        Wpil_Base::clear_tracked_action('link_inserted');

        if($applied){
            self::mark_suggestion_inserted_and_ignore_sentence_matches($suggestion);
            $response['applied'] = true;
            $response['message'] = 'Link inserted.';
            $response['reason'] = 'inserted';
            return $response;
        }

        if($ignore_on_fail){
            $wpdb->update($linking_table, ['ignored' => 1], ['ai_index' => (int) $suggestion->ai_index]);
        }

        $response['message'] = 'Unable to insert the link into the current content.';
        $response['reason'] = 'insert_failed';
        return $response;
    }

    /**
     * 
     **/
    public static function ignore_linking_suggestions($ids = []){
        global $wpdb;
        $linking_table = $wpdb->prefix . 'wpil_ai_linking';

        if(empty($ids)){
            return false;
        }

        if(!is_array($ids)){
            $ids = array($ids);
        }

        foreach($ids as $id){
            $wpdb->update($linking_table, ['ignored' => 1], ['ai_index' => $id]);
        }
    }

    /**
     * Creates links from the database
     **/
    public static function create_links_from_linking_suggestions($ids = []){
        global $wpdb;
        $linking_table = $wpdb->prefix . 'wpil_ai_linking';

        if(empty($ids)){
            return array(
                'applied' => false,
                'stale' => false,
                'message' => 'No suggestions were supplied.',
                'reason' => 'missing_ids',
                'process_key' => '',
                'link_id' => 0,
            );
        }

        $ids = implode(',', $ids);

        $data = $wpdb->get_results("SELECT * FROM {$linking_table} WHERE `ai_index` in ({$ids}) AND `ignored` < 1 AND `inserted` < 1");

        if(empty($data)){
            return array(
                'applied' => false,
                'stale' => false,
                'message' => 'Suggestion not found or already processed.',
                'reason' => 'missing_suggestion',
                'process_key' => '',
                'link_id' => 0,
            );
        }

        $links_inserted = 0;
        $results = array();
        foreach($data as $dat){
            if(Wpil_Base::overTimeLimit(5,30)){
                break;
            }

            $result = self::apply_linking_suggestion($dat, true);
            $results[(int) $dat->ai_index] = $result;
            if(!empty($result['applied'])){
                $links_inserted++;
            }
        }

        if(!empty($links_inserted)){
            Wpil_Telemetry::log_event('linkwhisper_ai_link_inserted', array('count' => $links_inserted));
        }

        if(count($results) === 1){
            return reset($results);
        }

        return $results;
    }

    /**
     * Automatically inserts links from suggestions
     **/
    public static function auto_create_links_from_suggestions($source_post_id = 0, $source_post_type = '', $process_key = ''){
        global $wpdb;
        $linking_table = $wpdb->prefix . 'wpil_ai_linking';

        $source_post_id = (int) $source_post_id;
        $source_post_type = ($source_post_type === 'term') ? 'term' : (($source_post_type === 'post') ? 'post' : '');
        $process_key = is_string($process_key) ? sanitize_text_field($process_key) : '';

        $where = "WHERE `ignored` < 1 AND `inserted` < 1";
        if(!empty($process_key)){
            $where .= $wpdb->prepare(" AND `process_key` = %s", $process_key);
        }
        if($source_post_id > 0){
            $where .= $wpdb->prepare(" AND `post_id` = %d", $source_post_id);
            if(!empty($source_post_type)){
                $where .= $wpdb->prepare(" AND `post_type` = %s", $source_post_type);
            }
        }

        $data = $wpdb->get_results("SELECT * FROM {$linking_table} {$where} ORDER BY COALESCE(ai_relation_score, 0) DESC, ai_index ASC LIMIT 50");

        if(empty($data)){
            return true; // return true for the benefit of whoever is listening
        }

        // get the max outbound links for a post
        $max_outbound = (int)get_option('wpil_max_links_per_post', 0);

        // get the max inbound links for a post
        $max_inbound = (int)get_option('wpil_max_inbound_links_per_post', 0);

        // get the minimum relatedness score required to auto-insert
        $auto_insert_threshold = Wpil_Settings::get_ai_auto_insert_relatedness_threshold();

        $links_inserted = 0;
        foreach($data as $dat){
            if(Wpil_Base::overTimeLimit(5,60)){
                break;
            }

            $row_process_key = !empty($dat->process_key) ? (string)$dat->process_key : $process_key;
            $orphan_only_targets = self::is_orphan_fix_process_key($row_process_key);
            if($orphan_only_targets && !self::is_orphaned_target_for_ai_suggestion((int)$dat->target_id, (string)$dat->target_type)){
                $wpdb->update($linking_table, ['ignored' => 1], ['ai_index' => $dat->ai_index]);
                continue;
            }

            if($auto_insert_threshold > 0){
                $ai_score = isset($dat->ai_relation_score) ? floatval($dat->ai_relation_score) : null;

                if($ai_score === null || $ai_score <= 0){
                    $source_post = new Wpil_Model_Post($dat->post_id, $dat->post_type);
                    $target_post = new Wpil_Model_Post($dat->target_id, $dat->target_type);
                    $ai_score = self::get_post_relationship_score($source_post, $target_post);

                    if($ai_score > 0){
                        $wpdb->update($linking_table, ['ai_relation_score' => $ai_score], ['ai_index' => $dat->ai_index]);
                    }
                }

                if($ai_score === null || $ai_score < $auto_insert_threshold || $ai_score > 0.9999){
                    $wpdb->update($linking_table, ['ignored' => 1], ['ai_index' => $dat->ai_index]);
                    continue;
                }
            }
            
            $source_post = new Wpil_Model_Post($dat->post_id, $dat->post_type);
            $target_post = new Wpil_Model_Post($dat->target_id, $dat->target_type);

            // if we're at the linking limits
            if( self::has_reached_ai_outbound_processing_limit($source_post) ||
                ($max_outbound > 0 && $source_post->getOutboundInternalLinks(true) >= $max_outbound) ||
                ($max_inbound > 0 && $target_post->getInboundInternalLinks(true) >= $max_inbound) ||
                ($orphan_only_targets && $target_post->getInboundInternalLinks(true) > 0)
            ){
                // marke the link as processed
                $wpdb->update($linking_table, ['ignored' => 1], ['ai_index' => $dat->ai_index]);
                // and skip inserting this suggestion
                continue;
            }

            $result = self::apply_linking_suggestion($dat, true);
            if(!empty($result['applied'])){
                $links_inserted++;
            }
        }

        if(!empty($links_inserted)){
            Wpil_Telemetry::log_event('linkwhisper_ai_link_inserted', array('count' => $links_inserted));
        }

        return false; // just assume that we're not done with processing
    }

    /**
     * Marks the inserted suggestion row and suppresses duplicate target suggestions for the same sentence.
     *
     * @param object $suggestion
     * @return void
     */
    private static function mark_suggestion_inserted_and_ignore_sentence_matches($suggestion){
        global $wpdb;
        $linking_table = $wpdb->prefix . 'wpil_ai_linking';

        if(empty($suggestion) || !is_object($suggestion) || empty($suggestion->ai_index)){
            return;
        }

        $sentence_id = !empty($suggestion->sentence_id) ? (string)$suggestion->sentence_id : '';
        if($sentence_id === '' && !empty($suggestion->sentence_text)){
            $sentence_id = md5((string)$suggestion->sentence_text);
        }
        $process_key = !empty($suggestion->process_key) ? (string)$suggestion->process_key : '';

        $wpdb->update($linking_table, ['inserted' => 1], ['ai_index' => (int)$suggestion->ai_index]);

        $sentence_text = !empty($suggestion->sentence_text) ? (string)$suggestion->sentence_text : '';

        // Independent rule 1: ignore all sibling suggestions for the same source sentence.
        if($sentence_id !== ''){
            $sql = "UPDATE {$linking_table}
                    SET ignored = 1
                    WHERE ai_index != %d
                        AND inserted < 1
                        AND ignored < 1
                        AND post_id = %d
                        AND post_type = %s
                        AND (
                            sentence_id = %s
                            OR ((sentence_id = '' OR sentence_id IS NULL) AND sentence_text = %s)
                        )";
            $params = [
                (int)$suggestion->ai_index,
                (int)$suggestion->post_id,
                (string)$suggestion->post_type,
                $sentence_id,
                $sentence_text,
            ];
            if($process_key !== ''){
                $sql .= " AND process_key = %s";
                $params[] = $process_key;
            }

            $wpdb->query($wpdb->prepare($sql, $params));
        }

        // Independent rule 2: ignore all sibling suggestions for the same source -> target pair.
        $sql = "UPDATE {$linking_table}
                SET ignored = 1
                WHERE ai_index != %d
                    AND inserted < 1
                    AND ignored < 1
                    AND post_id = %d
                    AND post_type = %s
                    AND target_id = %d
                    AND target_type = %s";
        $params = [
            (int)$suggestion->ai_index,
            (int)$suggestion->post_id,
            (string)$suggestion->post_type,
            (int)$suggestion->target_id,
            (string)$suggestion->target_type,
        ];
        if($process_key !== ''){
            $sql .= " AND process_key = %s";
            $params[] = $process_key;
        }

        $wpdb->query($wpdb->prepare($sql, $params));
    }


    /**
     * @param object $linking_data
     **/
    public static function build_target_link_from_suggestion($linking_data = array()){
        if(empty($linking_data)){
            return false;
        }

        // get the target URL
        $target = new Wpil_Model_Post($linking_data->target_id, $linking_data->target_type);

        // make sure the post exists
        if(!$target->check_if_post_exists() || empty($target->getViewLink())){
            return false;
        }

        // get the anchor
        preg_match_all('~<a\b[^>]*href\s*=\s*([\'"])wpil-link-proposal\1[^>]*>(.*?)</a>~is', $linking_data->sentence_with_anchor_text, $m);

        if(!isset($m[2]) || empty($m[2])){
            return false;
        }

        $anchor = $m[2][0];

        // create a clean sentence version
        $sentence = preg_replace('~<a\b[^>]*href\s*=\s*([\'"])wpil-link-proposal\1[^>]*>(.*?)</a>~is', '$2', $linking_data->sentence_with_anchor_text);
        
        // build the link
        $link = Wpil_Post::build_link_with_attrs($target->getViewLink(), $anchor, $sentence);

        // and return it
        return $link;
    }

    public static function ajax_get_review_links(){
        global $wpdb;
        $table = $wpdb->prefix . 'wpil_ai_linking';
        $report_links_table = $wpdb->prefix . 'wpil_report_links';

        Wpil_Base::verify_nonce('wizard-scanning-nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'No permission'), 403);
        }

        $visible_limit = isset($_POST['visible_limit']) ? (int) wp_unslash($_POST['visible_limit']) : 5;
        if($visible_limit < 3){
            $visible_limit = 3;
        }elseif($visible_limit > 30){
            $visible_limit = 30;
        }

        $max = $visible_limit;
        $fetch_limit = max(50, ($visible_limit * 4));
        $sort_key = !empty($_POST['sort_key']) ? sanitize_key(wp_unslash($_POST['sort_key'])) : 'ai';
        $sort_dir = !empty($_POST['sort_dir']) ? strtolower(sanitize_text_field(wp_unslash($_POST['sort_dir']))) : 'desc';
        $sort_dir = ($sort_dir === 'asc') ? 'asc' : 'desc';
        $has_process_key = isset($_POST['process_key']);
        $process_key = $has_process_key ? sanitize_text_field(wp_unslash($_POST['process_key'])) : '';
        if(!$has_process_key && empty($process_key)){
            $process_key = get_option('wpil_ai_linking_process_key', '');
        }
        $fix_type = !empty($_POST['fix_type']) ? sanitize_key(wp_unslash($_POST['fix_type'])) : '';
        if($fix_type === 'orphaned_posts' && !self::is_orphan_fix_process_key($process_key)){
            $process_key = md5('orphan-post-search');
        }
        if(self::is_release_disabled_ai_linking_process($process_key, $fix_type)){
            wp_send_json_success(array(
                'items' => array(),
                'remaining' => 0,
                'process_key' => $process_key,
            ));
        }
        $orphan_only_targets = self::is_orphan_fix_process_key($process_key);
        $dashboard_fix_keys = self::get_dashboard_fix_process_keys();
        $is_dashboard_fix_process = (!empty($process_key) && in_array($process_key, $dashboard_fix_keys, true));
        $fix_special_options = $is_dashboard_fix_process ? Wpil_Settings::get_ai_fix_special_options($process_key) : array();
        $fix_filter_sql = '';
        $fix_filter_params = array();
        if(!empty($fix_special_options['link_to_category_pages'])){
            $fix_filter_sql .= " AND a.target_type = 'term'";
        }
        if(!empty($fix_special_options['link_from_category_pages'])){
            $fix_filter_sql .= " AND a.post_type = 'term'";
        }
        if(!empty($fix_special_options['select_post_types']) && !empty($fix_special_options['selected_post_types']) && is_array($fix_special_options['selected_post_types'])){
            $selected_types = array_values(array_unique(array_filter(array_map('sanitize_text_field', $fix_special_options['selected_post_types']))));
            if(!empty($selected_types)){
                $type_placeholders = implode(',', array_fill(0, count($selected_types), '%s'));
                $fix_filter_sql .= " AND (
                    a.target_type <> 'post'
                    OR EXISTS (
                        SELECT 1
                        FROM {$wpdb->posts} fix_posts
                        WHERE fix_posts.ID = a.target_id
                            AND fix_posts.post_type IN ({$type_placeholders})
                    )
                )";
                $fix_filter_params = array_merge($fix_filter_params, $selected_types);
            }
        }

        $hidden_link_ids = get_transient('wpil_hide_ai_link_id');
        if(!is_array($hidden_link_ids)){
            $hidden_link_ids = array();
        }
        $hidden_link_ids = array_values(array_unique(array_filter(array_map('intval', $hidden_link_ids))));

        $table_data = $wpdb->get_row("SELECT table_collation, SUBSTRING_INDEX(table_collation, '_', 1) AS character_set FROM information_schema.tables WHERE table_schema = '{$wpdb->dbname}' AND table_name = '{$wpdb->posts}'");
        $report_collation = (!empty($table_data) && !empty($table_data->table_collation)) ? $table_data->table_collation : 'utf8mb4_unicode_ci';
        $report_charset = (!empty($table_data) && !empty($table_data->character_set)) ? $table_data->character_set : 'utf8mb4';
        $target_type_compare_sql = "CONVERT(rl.target_type USING {$report_charset}) COLLATE {$report_collation} = CONVERT(a.target_type USING {$report_charset}) COLLATE {$report_collation}";
        $post_type_compare_sql = "CONVERT(rl.post_type USING {$report_charset}) COLLATE {$report_collation} = CONVERT(a.target_type USING {$report_charset}) COLLATE {$report_collation}";

        $allowed_sort_keys = array('ai', 'type', 'tax', 'inbound', 'outbound_internal', 'outbound_external', 'post_id', 'language');
        if(!in_array($sort_key, $allowed_sort_keys, true)){
            $sort_key = 'ai';
        }
        $source_filter_data = self::get_review_source_filter_sql('a', $report_links_table, $report_charset, $report_collation);

        $params = array();
        $where = "WHERE a.inserted = 0 AND a.ignored = 0";
        if($has_process_key && $process_key === ''){
            $where .= " AND 1 = 0";
        }
        if(!empty($process_key) && $process_key !== 'all'){
            $where .= " AND a.ai_relation_score < 1 AND a.process_key = %s";
            $params[] = $process_key;
        }
        if($orphan_only_targets){
            $where .= " AND NOT EXISTS (
                SELECT 1
                FROM {$report_links_table} rl
                WHERE rl.target_id = a.target_id
                    AND ((a.target_type = 'post' AND rl.target_type = 'post') OR (a.target_type = 'term' AND rl.target_type = 'term'))
            )";
        }
        if(!empty($fix_filter_sql)){
            $where .= $fix_filter_sql;
            $params = array_merge($params, $fix_filter_params);
        }
        if(!empty($hidden_link_ids)){
            $where .= " AND a.ai_index NOT IN (" . implode(',', array_fill(0, count($hidden_link_ids), '%d')) . ")";
            $params = array_merge($params, $hidden_link_ids);
        }
        if(!empty($source_filter_data['sql'])){
            $where .= $source_filter_data['sql'];
            $params = array_merge($params, $source_filter_data['params']);
        }

        $select_sql = "SELECT a.*";
        $order_sql = "ORDER BY a.ai_relation_score DESC, a.ai_index DESC";
        if(in_array($sort_key, array('inbound', 'outbound_internal', 'outbound_external'), true)){
            switch($sort_key){
                case 'inbound':
                    $sort_count_sql = "(SELECT COUNT(*) FROM {$report_links_table} rl WHERE rl.target_id = a.target_id AND {$target_type_compare_sql})";
                    break;
                case 'outbound_internal':
                    $sort_count_sql = "(SELECT COUNT(*) FROM {$report_links_table} rl WHERE rl.post_id = a.target_id AND {$post_type_compare_sql} AND rl.internal = 1)";
                    break;
                case 'outbound_external':
                default:
                    $sort_count_sql = "(SELECT COUNT(*) FROM {$report_links_table} rl WHERE rl.post_id = a.target_id AND {$post_type_compare_sql} AND rl.internal = 0)";
                    break;
            }

            $select_sql .= ", {$sort_count_sql} AS report_sort_count";
            $order_sql = "ORDER BY report_sort_count {$sort_dir}, a.ai_relation_score DESC, a.ai_index DESC";
        }

        $sql = "{$select_sql} FROM {$table} a {$where} {$order_sql} LIMIT %d";
        $params[] = $fetch_limit;
        $prepared = $wpdb->prepare($sql, $params);
        $rows = $wpdb->get_results($prepared, ARRAY_A);

        $items = array();
        if (!empty($rows)) {
            foreach ($rows as $row) {
                $target_title = '';
                $target_hint  = '';
                $target_data  = array(
                    'title' => '',
                    'type' => '',
                    'taxonomy' => '',
                    'categories' => '',
                    'tags' => '',
                    'inbound_internal' => 0,
                    'outbound_internal' => 0,
                    'outbound_external' => 0,
                    'post_id' => 0,
                    'language' => '',
                    'view_link' => '',
                    'ai_relatedness' => ''
                );
                $source_data = array(
                    'title' => '',
                    'type' => '',
                    'taxonomy' => '',
                    'categories' => '',
                    'tags' => '',
                    'inbound_internal' => 0,
                    'outbound_internal' => 0,
                    'outbound_external' => 0,
                    'post_id' => 0,
                    'language' => '',
                    'view_link' => '',
                    'ai_relatedness' => ''
                );

                $source_post = null;
                $source_view_link = '';
                $ai_relatedness = '';
                if(isset($row['ai_relation_score']) && is_numeric($row['ai_relation_score']) && !empty($row['ai_relation_score'])){
                    $ai_relatedness = (round((float)$row['ai_relation_score'], 4) * 100) . '%';
                }
                if(!empty($row['post_id']) && !empty($row['post_type'])){
                    $source_post = new Wpil_Model_Post((int)$row['post_id'], $row['post_type']);
                    if(!empty($source_post)){
                        $source_view_link = $source_post->getLinks()->view;
                        if(!empty($source_view_link)){
                            $source_view_link = Wpil_Link::filter_staging_to_live_domain($source_view_link);
                        }
                        $source_data = self::get_review_post_display_data($source_post, $ai_relatedness);
                    }
                }

                if (!empty($row['target_id']) && !empty($row['target_type'])) {
                    $target_post = new Wpil_Model_Post((int)$row['target_id'], $row['target_type']);
                    $target_title = $target_post->getTitle();
                    $target_state = self::get_live_target_state((int) $row['target_id'], (string) $row['target_type']);

                    if($orphan_only_targets && empty($target_state['orphan_eligible'])){
                        continue;
                    }
                    $target_data = self::get_review_post_display_data($target_post, $ai_relatedness);
                }

                $sentence_link = self::build_target_link_from_suggestion((object)$row);
                if(empty($sentence_link)){
                    self::ignore_linking_suggestions([(int)$row['ai_index']]);
                    continue;
                }

                $sentence_id = isset($row['sentence_id']) ? (string)$row['sentence_id'] : '';
                if($sentence_id === '' && !empty($row['sentence_text'])){
                    $sentence_id = md5((string)$row['sentence_text']);
                }

                $items[] = array(
                    'id' => (int)$row['ai_index'],
                    'post_id' => (int)$row['post_id'],
                    'post_type' => isset($row['post_type']) ? (string)$row['post_type'] : '',
                    'sentence_id' => $sentence_id,
                    'target_id' => (int)$row['target_id'],
                    'target_type' => isset($row['target_type']) ? (string)$row['target_type'] : '',
                    'post_title' => (!empty($source_post) ? $source_post->getTitle() : get_the_title((int)$row['post_id'])),
                    'source_view_link' => $source_view_link,
                    'target_title' => $target_title,
                    'target_hint'  => $target_hint,
                    'proposed_sentence_html' => $sentence_link,
                    'ai_relation_score' => isset($row['ai_relation_score']) ? floatval($row['ai_relation_score']) : null,
                    'source_data' => $source_data,
                    'target_data' => $target_data,
                );
            }
        }

        if(!empty($items)){
            $numeric_sort_keys = array(
                'ai' => true,
                'inbound' => true,
                'outbound_internal' => true,
                'outbound_external' => true,
                'post_id' => true
            );

            $extract_sort_value = function($item) use ($sort_key){
                $target_data = isset($item['target_data']) && is_array($item['target_data']) ? $item['target_data'] : array();
                switch($sort_key){
                    case 'ai':
                        return isset($item['ai_relation_score']) ? $item['ai_relation_score'] : null;
                    case 'type':
                        return isset($target_data['type']) ? $target_data['type'] : '';
                    case 'tax':
                        if(!empty($target_data['categories'])) return $target_data['categories'];
                        if(!empty($target_data['tags'])) return $target_data['tags'];
                        return isset($target_data['taxonomy']) ? $target_data['taxonomy'] : '';
                    case 'inbound':
                        return isset($target_data['inbound_internal']) ? $target_data['inbound_internal'] : null;
                    case 'outbound_internal':
                        return isset($target_data['outbound_internal']) ? $target_data['outbound_internal'] : null;
                    case 'outbound_external':
                        return isset($target_data['outbound_external']) ? $target_data['outbound_external'] : null;
                    case 'post_id':
                        return isset($target_data['post_id']) ? $target_data['post_id'] : null;
                    case 'language':
                        return isset($target_data['language']) ? $target_data['language'] : '';
                    default:
                        return isset($item['ai_relation_score']) ? $item['ai_relation_score'] : null;
                }
            };

            usort($items, function($a, $b) use ($extract_sort_value, $numeric_sort_keys, $sort_key, $sort_dir){
                $a_val = $extract_sort_value($a);
                $b_val = $extract_sort_value($b);
                $is_numeric = !empty($numeric_sort_keys[$sort_key]);

                if($is_numeric){
                    $a_missing = !is_numeric($a_val);
                    $b_missing = !is_numeric($b_val);
                    if($a_missing !== $b_missing){
                        return $a_missing ? 1 : -1;
                    }
                    if(!$a_missing){
                        $a_num = (float) $a_val;
                        $b_num = (float) $b_val;
                        if($a_num !== $b_num){
                            if($sort_dir === 'asc'){
                                return ($a_num < $b_num) ? -1 : 1;
                            }
                            return ($a_num > $b_num) ? -1 : 1;
                        }
                    }
                }else{
                    $a_str = trim(strtolower((string) $a_val));
                    $b_str = trim(strtolower((string) $b_val));
                    $a_missing = ($a_str === '');
                    $b_missing = ($b_str === '');
                    if($a_missing !== $b_missing){
                        return $a_missing ? 1 : -1;
                    }
                    if(!$a_missing && $a_str !== $b_str){
                        if($sort_dir === 'asc'){
                            return ($a_str < $b_str) ? -1 : 1;
                        }
                        return ($a_str > $b_str) ? -1 : 1;
                    }
                }

                $a_score = (isset($a['ai_relation_score']) && is_numeric($a['ai_relation_score'])) ? (float)$a['ai_relation_score'] : -INF;
                $b_score = (isset($b['ai_relation_score']) && is_numeric($b['ai_relation_score'])) ? (float)$b['ai_relation_score'] : -INF;
                if($a_score !== $b_score){
                    return ($a_score > $b_score) ? -1 : 1;
                }

                $a_id = isset($a['id']) ? (int)$a['id'] : 0;
                $b_id = isset($b['id']) ? (int)$b['id'] : 0;
                if($a_id === $b_id){
                    return 0;
                }
                return ($a_id > $b_id) ? -1 : 1;
            });
        }

        if(count($items) > $max){
            $items = array_slice($items, 0, $max);
        }

        if($has_process_key && $process_key === ''){
            $remaining = 0;
        }elseif(!empty($process_key) && $process_key !== 'all'){
            $remaining_where = "a.inserted = 0 AND a.ignored = 0 AND a.ai_relation_score < 1 AND a.process_key = %s";
            $remaining_params = array($process_key);
            if($orphan_only_targets){
                $remaining_where .= " AND NOT EXISTS (
                    SELECT 1
                    FROM {$report_links_table} rl
                    WHERE rl.target_id = a.target_id
                        AND ((a.target_type = 'post' AND rl.target_type = 'post') OR (a.target_type = 'term' AND rl.target_type = 'term'))
                )";
            }
            if(!empty($fix_filter_sql)){
                $remaining_where .= $fix_filter_sql;
                $remaining_params = array_merge($remaining_params, $fix_filter_params);
            }
            if(!empty($hidden_link_ids)){
                $remaining_where .= " AND a.ai_index NOT IN (" . implode(',', array_fill(0, count($hidden_link_ids), '%d')) . ")";
                $remaining_params = array_merge($remaining_params, $hidden_link_ids);
            }
            if(!empty($source_filter_data['sql'])){
                $remaining_where .= $source_filter_data['sql'];
                $remaining_params = array_merge($remaining_params, $source_filter_data['params']);
            }

            $remaining_sql = "SELECT COUNT(*) FROM {$table} a WHERE {$remaining_where}";
            $remaining = (int) $wpdb->get_var($wpdb->prepare($remaining_sql, $remaining_params));
        }else{
            $remaining_where = "a.inserted = 0 AND a.ignored = 0";
            $remaining_params = array();
            if(!empty($hidden_link_ids)){
                $remaining_where .= " AND a.ai_index NOT IN (" . implode(',', array_fill(0, count($hidden_link_ids), '%d')) . ")";
                $remaining_params = array_merge($remaining_params, $hidden_link_ids);
            }
            if(!empty($source_filter_data['sql'])){
                $remaining_where .= $source_filter_data['sql'];
                $remaining_params = array_merge($remaining_params, $source_filter_data['params']);
            }

            $remaining_sql = "SELECT COUNT(*) FROM {$table} a WHERE {$remaining_where}";
            $remaining = !empty($remaining_params) ? (int) $wpdb->get_var($wpdb->prepare($remaining_sql, $remaining_params)) : (int) $wpdb->get_var($remaining_sql);
        }

        wp_send_json_success(array(
            'items' => $items,
            'remaining' => $remaining,
            'process_key' => $process_key,
        ));
    }

    private static function sanitize_review_source_filter_date($date = ''){
        $date = sanitize_text_field((string) $date);
        if(empty($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)){
            return '';
        }

        $date_parts = explode('-', $date);
        if(3 !== count($date_parts)){
            return '';
        }

        $year = (int) $date_parts[0];
        $month = (int) $date_parts[1];
        $day = (int) $date_parts[2];

        return checkdate($month, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : '';
    }

    private static function get_review_source_filter_sql($table_alias = 'a', $report_links_table = '', $report_charset = 'utf8mb4', $report_collation = 'utf8mb4_unicode_ci'){
        global $wpdb;

        $table_alias = preg_replace('/[^a-z0-9_]/i', '', (string) $table_alias);
        if(empty($table_alias)){
            $table_alias = 'a';
        }

        $report_links_table = !empty($report_links_table) ? $report_links_table : ($wpdb->prefix . 'wpil_report_links');
        $report_charset = preg_replace('/[^a-z0-9_]/i', '', (string) $report_charset);
        $report_collation = preg_replace('/[^a-z0-9_]/i', '', (string) $report_collation);
        if(empty($report_charset)){
            $report_charset = 'utf8mb4';
        }
        if(empty($report_collation)){
            $report_collation = 'utf8mb4_unicode_ci';
        }

        $source_date_after = isset($_POST['source_date_after']) ? self::sanitize_review_source_filter_date(wp_unslash($_POST['source_date_after'])) : '';
        $source_link_metric = isset($_POST['source_link_metric']) ? sanitize_key(wp_unslash($_POST['source_link_metric'])) : '';
        $source_link_compare = isset($_POST['source_link_compare']) ? sanitize_key(wp_unslash($_POST['source_link_compare'])) : '';
        $source_link_value = isset($_POST['source_link_value']) ? trim((string) wp_unslash($_POST['source_link_value'])) : '';

        if(!in_array($source_link_metric, array('inbound', 'outbound_internal'), true)){
            $source_link_metric = '';
        }

        if(!in_array($source_link_compare, array('gt', 'eq', 'lt'), true)){
            $source_link_compare = '';
        }

        if('' !== $source_link_value){
            if(ctype_digit($source_link_value)){
                $source_link_value = (int) $source_link_value;
            }else{
                $source_link_value = null;
            }
        }else{
            $source_link_value = null;
        }

        $sql = '';
        $params = array();

        if(!empty($source_date_after)){
            $sql .= " AND {$table_alias}.post_type = 'post'
                AND EXISTS (
                    SELECT 1
                    FROM {$wpdb->posts} review_source_posts
                    WHERE review_source_posts.ID = {$table_alias}.post_id
                        AND review_source_posts.post_date >= %s
                )";
            $params[] = $source_date_after . ' 00:00:00';
        }

        if(!empty($source_link_metric) && !empty($source_link_compare) && null !== $source_link_value){
            $operator = '=';
            if('gt' === $source_link_compare){
                $operator = '>';
            }elseif('lt' === $source_link_compare){
                $operator = '<';
            }

            $source_target_type_compare_sql = "CONVERT(source_rl.target_type USING {$report_charset}) COLLATE {$report_collation} = CONVERT({$table_alias}.post_type USING {$report_charset}) COLLATE {$report_collation}";
            $source_post_type_compare_sql = "CONVERT(source_rl.post_type USING {$report_charset}) COLLATE {$report_collation} = CONVERT({$table_alias}.post_type USING {$report_charset}) COLLATE {$report_collation}";
            if('outbound_internal' === $source_link_metric){
                $source_link_count_sql = "(SELECT COUNT(*) FROM {$report_links_table} source_rl WHERE source_rl.post_id = {$table_alias}.post_id AND {$source_post_type_compare_sql} AND source_rl.internal = 1)";
            }else{
                $source_link_count_sql = "(SELECT COUNT(*) FROM {$report_links_table} source_rl WHERE source_rl.target_id = {$table_alias}.post_id AND {$source_target_type_compare_sql} AND source_rl.internal = 1)";
            }

            $sql .= " AND ({$source_link_count_sql}) {$operator} %d";
            $params[] = $source_link_value;
        }

        return array(
            'sql' => $sql,
            'params' => $params,
        );
    }

    public static function ajax_get_review_link_count(){
        global $wpdb;
        $table = $wpdb->prefix . 'wpil_ai_linking';
        $report_links_table = $wpdb->prefix . 'wpil_report_links';

        Wpil_Base::verify_nonce('wizard-scanning-nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'No permission'), 403);
        }

        $has_process_key = isset($_POST['process_key']);
        $process_key = $has_process_key ? sanitize_text_field(wp_unslash($_POST['process_key'])) : '';
        if(!$has_process_key && empty($process_key)){
            $process_key = get_option('wpil_ai_linking_process_key', '');
        }
        $fix_type = !empty($_POST['fix_type']) ? sanitize_key(wp_unslash($_POST['fix_type'])) : '';
        if($fix_type === 'orphaned_posts' && !self::is_orphan_fix_process_key($process_key)){
            $process_key = md5('orphan-post-search');
        }
        if(self::is_release_disabled_ai_linking_process($process_key, $fix_type)){
            wp_send_json_success(array(
                'remaining' => 0,
                'process_key' => $process_key,
            ));
        }
        $orphan_only_targets = self::is_orphan_fix_process_key($process_key);
        $dashboard_fix_keys = self::get_dashboard_fix_process_keys();
        $is_dashboard_fix_process = (!empty($process_key) && in_array($process_key, $dashboard_fix_keys, true));
        $fix_special_options = $is_dashboard_fix_process ? Wpil_Settings::get_ai_fix_special_options($process_key) : array();
        $fix_filter_sql = '';
        $fix_filter_params = array();
        if(!empty($fix_special_options['link_to_category_pages'])){
            $fix_filter_sql .= " AND a.target_type = 'term'";
        }
        if(!empty($fix_special_options['link_from_category_pages'])){
            $fix_filter_sql .= " AND a.post_type = 'term'";
        }
        if(!empty($fix_special_options['select_post_types']) && !empty($fix_special_options['selected_post_types']) && is_array($fix_special_options['selected_post_types'])){
            $selected_types = array_values(array_unique(array_filter(array_map('sanitize_text_field', $fix_special_options['selected_post_types']))));
            if(!empty($selected_types)){
                $type_placeholders = implode(',', array_fill(0, count($selected_types), '%s'));
                $fix_filter_sql .= " AND (
                    a.target_type <> 'post'
                    OR EXISTS (
                        SELECT 1
                        FROM {$wpdb->posts} fix_posts
                        WHERE fix_posts.ID = a.target_id
                            AND fix_posts.post_type IN ({$type_placeholders})
                    )
                )";
                $fix_filter_params = array_merge($fix_filter_params, $selected_types);
            }
        }

        $hidden_link_ids = get_transient('wpil_hide_ai_link_id');
        if(!is_array($hidden_link_ids)){
            $hidden_link_ids = array();
        }
        $hidden_link_ids = array_values(array_unique(array_filter(array_map('intval', $hidden_link_ids))));

        if($has_process_key && $process_key === ''){
            $remaining = 0;
        }elseif(!empty($process_key) && $process_key !== 'all'){
            $remaining_where = "a.inserted = 0 AND a.ignored = 0 AND a.ai_relation_score < 1 AND a.process_key = %s";
            $remaining_params = array($process_key);
            if($orphan_only_targets){
                $remaining_where .= " AND NOT EXISTS (
                    SELECT 1
                    FROM {$report_links_table} rl
                    WHERE rl.target_id = a.target_id
                        AND ((a.target_type = 'post' AND rl.target_type = 'post') OR (a.target_type = 'term' AND rl.target_type = 'term'))
                )";
            }
            if(!empty($fix_filter_sql)){
                $remaining_where .= $fix_filter_sql;
                $remaining_params = array_merge($remaining_params, $fix_filter_params);
            }
            if(!empty($hidden_link_ids)){
                $remaining_where .= " AND a.ai_index NOT IN (" . implode(',', array_fill(0, count($hidden_link_ids), '%d')) . ")";
                $remaining_params = array_merge($remaining_params, $hidden_link_ids);
            }

            $remaining_sql = "SELECT COUNT(*) FROM {$table} a WHERE {$remaining_where}";
            $remaining = (int) $wpdb->get_var($wpdb->prepare($remaining_sql, $remaining_params));
        }else{
            $remaining_where = "a.inserted = 0 AND a.ignored = 0";
            $remaining_params = array();
            if(!empty($hidden_link_ids)){
                $remaining_where .= " AND a.ai_index NOT IN (" . implode(',', array_fill(0, count($hidden_link_ids), '%d')) . ")";
                $remaining_params = array_merge($remaining_params, $hidden_link_ids);
            }

            $remaining_sql = "SELECT COUNT(*) FROM {$table} a WHERE {$remaining_where}";
            $remaining = !empty($remaining_params) ? (int) $wpdb->get_var($wpdb->prepare($remaining_sql, $remaining_params)) : (int) $wpdb->get_var($remaining_sql);
        }
        wp_send_json_success(array(
            'remaining' => $remaining,
            'process_key' => $process_key,
        ));
    }

    public static function wpil_parse_anchor_html($html){
        $out = array(
            'href' => '',
            'text' => '',
        );

        if (empty($html)) {
            return $out;
        }

        // Extract href
        if (preg_match('/<a\b[^>]*\bhref\s*=\s*([\'"])(.*?)\1/i', $html, $m)) {
            $out['href'] = html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');
        } elseif (preg_match('/<a\b[^>]*\bhref\s*=\s*([^\'"\s>]+)/i', $html, $m)) {
            $out['href'] = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
        }

        // Extract inner text (strip nested tags if any)
        if (preg_match('/<a\b[^>]*>(.*?)<\/a>/is', $html, $m)) {
            $text = trim(wp_strip_all_tags($m[1]));
            $out['text'] = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        }

        return $out;
    }

    /**
     * Prepare WordPress post content for link analysis by stripping non-essential syntax.
     *
     * - Removes Gutenberg block comments (keeps block inner HTML/text)
     * - Removes shortcode wrappers while preserving enclosed content
     * - Removes HTML attributes from opening tags, except href on <a> tags
     *
     * @param string $content
     * @return string
     */
    public static function prepare_post_content_for_linking($content){
        if(!is_string($content) || $content === ''){
            return '';
        }

        // Remove Gutenberg block comments like:
        // <!-- wp:paragraph {"align":"center"} -->
        // <!-- /wp:paragraph -->
        $content = preg_replace('/<!--\s*\/?wp:[\s\S]*?-->/i', '', $content);

        // Remove shortcode tags while preserving wrapped content where present.
        if(function_exists('get_shortcode_regex')){
            $pattern = '/' . get_shortcode_regex() . '/s';
            $content = preg_replace_callback($pattern, function($matches){
                // Handle escaped shortcodes like [[tag]]
                if(isset($matches[1], $matches[6]) && $matches[1] === '[' && $matches[6] === ']'){
                    return substr($matches[0], 1, -1);
                }

                // Keep enclosed content for non-self-closing shortcodes.
                if(isset($matches[5]) && $matches[5] !== ''){
                    return $matches[5];
                }

                return '';
            }, $content);
        }else{
            // Fallback: remove shortcode-like tags.
            $content = preg_replace('/\[(\/?)[^\[\]]+?\]/', '', $content);
        }

        // Strip all attributes from opening HTML tags except href in anchor tags.
        $content = preg_replace_callback('/<(?!\/|!|\?)([a-zA-Z][a-zA-Z0-9:-]*)(\s+[^>]*?)?(\s*\/?)>/', function($matches){
            $tag_name = strtolower($matches[1]);
            $is_self_closing = (isset($matches[3]) && strpos($matches[3], '/') !== false);

            if($tag_name !== 'a'){
                return '<' . $matches[1] . ($is_self_closing ? ' /' : '') . '>';
            }

            $attrs = isset($matches[2]) ? $matches[2] : '';
            $href = '';
            if(preg_match('/\bhref\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'>]+))/i', $attrs, $href_match)){
                if(isset($href_match[1]) && $href_match[1] !== ''){
                    $href = $href_match[1];
                }elseif(isset($href_match[2]) && $href_match[2] !== ''){
                    $href = $href_match[2];
                }elseif(isset($href_match[3])){
                    $href = $href_match[3];
                }
            }

            if($href === ''){
                return '<' . $matches[1] . '>';
            }else{
                // for the time being, use a placeholder for the href... Just to keep the bot from being stupid
                $href = 'https://en.wikipedia.org/'; // because wiki !== 'obvious testing domain'
            }

            return '<' . $matches[1] . ' href="' . esc_attr($href) . '"' . ($is_self_closing ? ' /' : '') . '>';
        }, $content);

        return $content;
    }

    public static function ajax_set_review_link_decision(){
        global $wpdb;
        $table = $wpdb->prefix . 'wpil_ai_linking';

        Wpil_Base::verify_nonce('wizard-scanning-nonce');

        $link_id = isset($_POST['link_id']) ? sanitize_text_field($_POST['link_id']) : '';
        $decision = isset($_POST['decision']) ? sanitize_text_field($_POST['decision']) : '';
        $has_process_key = isset($_POST['process_key']);
        $process_key = $has_process_key ? sanitize_text_field(wp_unslash($_POST['process_key'])) : '';
        if(!$has_process_key && empty($process_key)){
            $process_key = get_option('wpil_ai_linking_process_key', '');
        }
        $fix_type = !empty($_POST['fix_type']) ? sanitize_key(wp_unslash($_POST['fix_type'])) : '';
        if($fix_type === 'orphaned_posts' && !self::is_orphan_fix_process_key($process_key)){
            $process_key = md5('orphan-post-search');
        }
        if(self::is_release_disabled_ai_linking_process($process_key, $fix_type)){
            wp_send_json_success(array(
                'link_id' => $link_id,
                'decision' => $decision,
                'process_key' => $process_key,
                'applied' => false,
                'stale' => false,
                'message' => 'AI link review is hidden for this release.',
                'reason' => 'release_disabled',
            ));
        }

        if(empty($link_id) || ($decision !== 'approve' && $decision !== 'reject')){
            wp_send_json_error(array('message' => 'Invalid payload'), 400);
        }

        $id_list = get_transient('wpil_hide_ai_link_id');
        if(empty($id_list)){
            $id_list = [];
        }

        $id_list[] = $link_id;

        set_transient('wpil_hide_ai_link_id', $id_list, MINUTE_IN_SECONDS); // set a flag so we can tell what ids we're ignoring!

        if($has_process_key && $process_key === ''){
            wp_send_json_error(array('message' => 'Process key required'), 400);
        }

        $suggestion = null;
        if(!empty($process_key) && 'all' !== $process_key){
            $suggestion = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table} WHERE ai_index = %d AND process_key = %s LIMIT 1",
                (int) $link_id,
                $process_key
            ));
            if(empty($suggestion)){
                wp_send_json_error(array('message' => 'Suggestion not found for process'), 404);
            }
        }else{
            $suggestion = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table} WHERE ai_index = %d LIMIT 1",
                (int) $link_id
            ));
            if(empty($suggestion)){
                wp_send_json_error(array('message' => 'Suggestion not found'), 404);
            }
        }

        if($decision === 'approve'){
            $result = self::create_links_from_linking_suggestions([$link_id]);
            wp_send_json_success(array(
                'link_id' => $link_id,
                'decision' => $decision,
                'process_key' => $process_key,
                'applied' => !empty($result['applied']),
                'stale' => !empty($result['stale']),
                'message' => !empty($result['message']) ? $result['message'] : '',
                'reason' => !empty($result['reason']) ? $result['reason'] : '',
            ));
        }else{
            self::ignore_linking_suggestions([$link_id]);
            wp_send_json_success(array(
                'link_id' => $link_id,
                'decision' => $decision,
                'process_key' => $process_key,
                'applied' => true,
                'stale' => false,
                'message' => 'Suggestion rejected.',
                'reason' => 'rejected',
            ));
        }
    }
}
