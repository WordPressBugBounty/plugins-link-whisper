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

    function __construct()
    {
        // todo: temp!
        self::$batch_limit = 50000;

        self::$ai_service_connected = (Wpil_Settings::get_linkwhisper_ai_active() && Wpil_Settings::get_linkwhisper_ai_token());
        self::$concurrency = 50;
    }

    public function register()
    {
        add_action('wp_ajax_wpil_live_download_ai_data', [__CLASS__, 'ajax_live_download_ai_data']);
        add_action('wp_ajax_wpil_clear_ai_data', [__CLASS__, 'ajax_wpil_clear_ai_data']);
        add_action('wp_ajax_wpil_ai_dismiss_credit_notice', [__CLASS__, 'ajax_wpil_dismiss_credit_notice']);
        add_action('wp_ajax_wpil_ai_dismiss_api_key_decoding_error', [__CLASS__, 'ajax_wpil_dismiss_api_key_decoding_error']);
        add_action('wp_ajax_wpil_estimate_site_processing_cost', [__CLASS__, 'ajax_estimate_site_processing_cost']);
        add_action('wp_ajax_setup_user_ai_subscription', array(__CLASS__, 'ajax_setup_user_ai_subscription'));
        add_action('wp_ajax_clear_user_ai_subscription', array(__CLASS__, 'ajax_clear_user_ai_subscription'));
        add_filter('cron_schedules', [__CLASS__, 'add_batch_cron_interval']);
        add_action('admin_init', [__CLASS__, 'schedule_batch_process']);
        add_action('wpil_ai_batch_process_cron', [__CLASS__, 'perform_cron_batch_process']);
        add_filter('orhanerday_openai_stream_response_data', [__CLASS__, 'process_streamed_data'], 10, 3);
    }

    /**
     * @return array
     **/
    public static function call_linkwhisper_ai($content = '', $model = '', $action = ''){
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

                        $processed = apply_filters('orhanerday_openai_stream_response_data', $handle_id, $content, $info);
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
     * 
     **/
    public static function ajax_live_download_ai_data(){
        Wpil_Base::verify_nonce('wpil_download_ai_data');
        // be sure to ignore any external object caches
        Wpil_Base::ignore_external_object_cache();
        // Remove any hooks that may interfere with AJAX requests
        Wpil_Base::remove_problem_hooks();

        $selected_processes = Wpil_Settings::get_selected_ai_batch_processes(true);
        $processed_embeddings = false;
        $initial_stats = self::get_completed_post_stats(true);
        $total_posts = self::get_total_processable_posts();
        $post_saving = true;
        $last_pass_unchanged = (array_key_exists('last_pass_unchanged', $_POST) && $_POST['last_pass_unchanged'] === '1') ? true: false;

        // set a flag so that we know that we're downloading data
        set_transient('wpil_doing_ai_data_download', time(), MINUTE_IN_SECONDS * 3);

        // if the batch processing is supposed to be turned on
        if(isset($_POST['activate_batch_processing']) && !empty($_POST['activate_batch_processing'])){
            // turn it on
            update_option('wpil_enable_ai_batch_processing', '1');
        }

        $start_time = isset($_POST['start_time']) && !empty($_POST['start_time']) ? (int)$_POST['start_time']: time();
        $current_process = esc_html__('Sending Site Data to OpenAI be Processed', 'wpil');

        // if the batch processing is supposed to be turned on
        if(isset($_POST['activate_batch_processing']) && !empty($_POST['activate_batch_processing'])){
            // turn it on
            update_option('wpil_enable_ai_batch_processing', '1');
        }

        // if this is the first go round
        if(isset($_POST['start_time']) && empty($_POST['start_time'])){
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
            self::analyze_site_posts();
        }

        if(!Wpil_Base::overTimeLimit(5, 20)){
            $post_saving = self::do_post_save_finishing();
            if(!$post_saving){
                $current_process = esc_html__('Processing Site Data...', 'wpil');
            }

            if(!Wpil_Sitemap::has_sitemap('ai_product_sitemap') && self::check_batch_status_completed(3, true)){
                $products = Wpil_AI::calculate_product_sitemap();
                if(!empty($products)){
                    Wpil_Sitemap::save_sitemap($products, 'ai_product_sitemap', 'AI-Detected Product Sitemap');
                }
            }
        }

        $current_stats = self::get_completed_post_stats(true, true);
        $all_processed = array();
        $completed = false;
        $live_processed_results = array();
        $oai_completed = array();

        if(!empty($current_stats)){
            foreach($current_stats as $ind => $count){
                if((int)$count >= (int)$total_posts){
                    $all_processed[$ind] = true;

                    if(in_array($ind, $selected_processes)){
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

            if(count(array_filter($all_processed)) === count($current_stats)){
                $completed = true;
            }
        }

        $oai_completed = (count(array_filter($oai_completed)) === count($selected_processes)) ? true: false;

        $response = array();
        if(self::$insufficient_quota || self::$invalid_request || self::$invalid_api_key || self::$user_not_exist){
            if(self::$insufficient_quota){
                update_option('wpil_oai_insufficient_quota_error', '1');
            }
            $response = (self::$ai_service_connected) ? array('error' => self::get_linkwhisper_ai_error_message()): array('error' => self::get_live_oai_error_message());
        }elseif(!$completed || !$post_saving || $processed_embeddings > 0){
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
                    'completion_messages' => array(
                        'info' => array(
                            'title' => __('Processing Halted', 'wpil'), 
                            'text' => __('Link Whisper is not currently able to process any more posts. The reason for this is unclear, there may have been an error, or it could be because all the posts are finished processing. Please check the System Error Log to see if there are any errors, and the Content Processing Status to see if all of the posts are processed.', 'wpil')
                        ),
                        'error' => (self::$ai_service_connected) ? self::get_linkwhisper_ai_error_message() : self::get_live_oai_error_message()
                        ),
                    'is_rate_limited' => self::$rate_limited
                )
            );
        }else{
            $response = array(
                'success' => array(
                    'title' => __('Processing Complete!', 'wpil'),
                    'text'  => __('All available site data has been processed!', 'wpil'),
                    'oai_completed' => $oai_completed,
                )
            );
        }

        wp_send_json($response);
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
        $embd_phrase_table      = $wpdb->prefix . "wpil_ai_embedding_phrase_data";
        $embd_phrase_calc_table = $wpdb->prefix . "wpil_ai_embedding_phrase_calculation_data";
        $ai_sggstd_anchor_table = $wpdb->prefix . "wpil_ai_suggested_anchors";
        $ai_anchor_sntnce_table = $wpdb->prefix . "wpil_ai_processed_sentences";
        $ai_ignore_anchor_table = $wpdb->prefix . "wpil_ai_ignored_anchors"; // TODO: Check to see what our size && speed effect are while using the ai_phrase table for keeping track of ignored suggestions. If we need a separate lookup index, build this out.
        $batch_log_table        = $wpdb->prefix . "wpil_ai_batch_log";
        $error_log_table        = $wpdb->prefix . "wpil_ai_error_log";
        $system_error_log_table = $wpdb->prefix . "wpil_ai_system_error_log";
        $completed_log_table    = $wpdb->prefix . "wpil_ai_completed_batch_log";

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
                                            model_version varchar(168),
                                            batch_processed tinyint(1) DEFAULT 0,
                                            input_tokens int(10) unsigned NOT NULL DEFAULT 0,
                                            output_tokens int(10) unsigned NOT NULL DEFAULT 0,
                                            cached_prompt_tokens int(10) unsigned NOT NULL DEFAULT 0,
                                            reasoning_tokens int(10) unsigned NOT NULL DEFAULT 0,
                                            total_tokens int(10) unsigned NOT NULL DEFAULT 0,
                                            credits_used decimal(10,4) unsigned NOT NULL DEFAULT 0.0000,
                                            process_used int(10) unsigned NOT NULL DEFAULT 0,
                                            process_time bigint(20),
                                            PRIMARY KEY (token_index)
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
    }

    /**
     * 
     **/
    public static function clear_ai_data(){
        global $wpdb;

        // NOTE: some tables are intentionally not being cleared until we get past teh initial rollout... We might need things like log data
        $tables = array(
            $wpdb->prefix . "wpil_ai_post_data",
            $wpdb->prefix . "wpil_ai_product_data",
            $wpdb->prefix . "wpil_ai_keyword_data",
//            $wpdb->prefix . "wpil_ai_token_use_data",
            $wpdb->prefix . "wpil_ai_embedding_data",
            $wpdb->prefix . "wpil_ai_embedding_calculation_data",
            $wpdb->prefix . "wpil_ai_batch_log",
//            $wpdb->prefix . "wpil_ai_error_log",
//            $wpdb->prefix . "wpil_ai_system_error_log"
        );

        foreach($tables as $table){
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
            if($table_exists === $table){
                $wpdb->query("TRUNCATE TABLE {$table}");
            }
        }

        // clear any AI keywords
        $target_keywords_table = $wpdb->prefix . "wpil_target_keyword_data";
        $wpdb->delete($target_keywords_table, array('keyword_type' => 'ai-generated-keyword'));

        // clear any AI created sitemaps
        $ai_sitemap = Wpil_Sitemap::get_sitemap_list('ai_sitemap');
        if(!empty($ai_sitemap) && isset($ai_sitemap[0], $ai_sitemap[0]->sitemap_id)){
            Wpil_Sitemap::delete_sitemap($ai_sitemap[0]->sitemap_id, $ai_sitemap[0]->sitemap_type);
        }

        $product_sitemap = Wpil_Sitemap::get_sitemap_list('ai_product_sitemap');
        if(!empty($product_sitemap) && isset($product_sitemap[0], $product_sitemap[0]->sitemap_id)){
            Wpil_Sitemap::delete_sitemap($product_sitemap[0]->sitemap_id, $product_sitemap[0]->sitemap_type);
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
                    $dat->response->body,
                    $dat->response->body->error,
                    $dat->response->body->error->message) ||
                    empty($dat->response->body->error->message)
            ){
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
                $dat->response->body->error->message,
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
                $table_indexes['calculated-post-embeddings'] = $wpdb->prefix . "wpil_ai_embedding_calculation_data";
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
                'calculated-post-embeddings' => $wpdb->prefix . "wpil_ai_embedding_calculation_data",
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
            $calculating_embeddings = ($ind === 'keyword-detecting') ? true: false;

            if($keyword_processing){
                $data = $wpdb->get_results("SELECT `post_id`, `post_type`, `keywords_loaded` FROM {$table}");
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
                        }elseif(is_array($dat)){
                            $stati[$state][$process] = count($dat);
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
                    }elseif(is_array($dat)){
                        $stati[$state][$process] = count($dat);
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
            $wpdb->prefix . "wpil_ai_batch_log",
//            $wpdb->prefix . "wpil_ai_error_log",
        );

        $has_data = false;
        foreach($tables as $table){
            // if we're looking for a specific table and this isn't it
            if(!empty($specific_table) && false === strpos($table, $specific_table)){
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

    public static function save_response_tokens($response, $process_used = '', $is_batch = false){
        $saved = false;

        if(empty($response)){
            return $saved;
        }

        if(!is_array($response)){
            $response = array($response);
        }

        foreach($response as $dat){
            if(is_string($dat)){
                $dat = self::decode($dat);
            }

            if(isset($dat->response) && !empty($dat->response)){
                $dat = $dat->response;
            }

            if(!empty($dat) && isset($dat->model) && !empty($dat->model) && isset($dat->usage)){
                $input = isset($dat->usage->prompt_tokens) && !empty($dat->usage->prompt_tokens) ? $dat->usage->prompt_tokens: 0;
                $output = isset($dat->usage->completion_tokens) && !empty($dat->usage->completion_tokens) ? $dat->usage->completion_tokens: 0;
                $total =  isset($dat->usage->total_tokens) && !empty($dat->usage->total_tokens) ? $dat->usage->total_tokens: 0;
                $cached_prompt = isset($dat->usage->prompt_tokens_details, $dat->usage->prompt_tokens_details->cached_tokens) && !empty($dat->usage->prompt_tokens_details->cached_tokens) ? $dat->usage->prompt_tokens_details->cached_tokens: 0;
                $reasoning = isset($dat->usage->completion_tokens_details, $dat->usage->completion_tokens_details->reasoning_tokens) && !empty($dat->usage->completion_tokens_details->reasoning_tokens) ? $dat->usage->completion_tokens_details->reasoning_tokens: 0;
                $credits_used = isset($dat->usage->credit_used) && !empty($dat->usage->credit_used) ? $dat->usage->credit_used: 0;
                $status = self::save_token_reference($dat->model, $is_batch, self::get_process_code_from_name($process_used), $input, $output, $total, $cached_prompt, $reasoning, $credits_used);
            
                if(!$saved && $status){
                    $saved = true;
                }

                if(!empty($credits_used)){
                    self::subtract_ai_credits($credits_used);
                }
            }
        }

        return $saved;
    }

    public static function save_token_reference($model = '', $batch_process = false, $process_number = 0, $input_tokens = 0, $output_tokens = 0, $total_tokens = 0, $cached_prompt_tokens = 0, $reasoning_tokens = 0, $credits_used = 0){
        global $wpdb;
        $table = $wpdb->prefix . 'wpil_ai_token_use_data';

        if(empty($model)){
            return false;
        }

        if(empty($total_tokens) && (!empty($input_tokens) || !empty($output_tokens))){
            $total_tokens = ($input_tokens + $output_tokens);
        }

        $saved = $wpdb->insert($table, [
            'model_version' => $model,
            'batch_processed' => (int) $batch_process,
            'input_tokens' => $input_tokens,
            'output_tokens' => $output_tokens,
            'cached_prompt_tokens' => $cached_prompt_tokens,
            'reasoning_tokens' => $reasoning_tokens,
            'total_tokens' => $total_tokens,
            'credits_used' => $credits_used,
            'process_used' => (int) $process_number,
            'process_time' => time(),
        ]);

        return !empty($saved);
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
            $cost = $wpdb->get_var($wpdb->prepare("SELECT SUM(credits_used) FROM {$table} WHERE `process_time` >= %d AND `process_time` <= %d", $start_time, $end_time));
            return intval($cost);
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
                                ($token->output_tokens * ($unprefixed[$model]['input']/2))
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
        );

        return (isset($process_list[$code])) ? $process_list[$code]: 'unknown';
    }

    /**
     * Analyzes site posts to create summaries of them and/or to identify products within them.
     **/
    public static function analyze_site_posts(){
        $time = microtime(true);
        $token_size = 0;
        $doing_ajax = (defined('DOING_AJAX') && DOING_AJAX) ? true: false;

        // first, figure out what we're doing
        $active_processes = Wpil_Settings::get_selected_ai_batch_processes(true, true);

        // if we're not doing anything
        if(empty($active_processes)){
            // exit
            return false;
        }

        $grouped = array();
        foreach($active_processes as $process){
            if(self::check_batch_status_completed($process)){
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
            $content = self::trim_text_to_token_limit(mb_ereg_replace("\n", '', mb_ereg_replace('(([a-zA-Z\-_0-9]+="[^"]*")+?[\s]?)', '', strip_tags($post->getContent(false), '<h1><h2><h3><h4><h5><h6><title><ul><ol><li>'))), self::$model, 7800);
            
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
        $calculation_table  = $wpdb->prefix . 'wpil_ai_embedding_calculation_data';

        $ids = array();
        $embedding_data     = $wpdb->get_results("SELECT `post_id`, `post_type` FROM {$embedding_table}");
        $calculation_data   = $wpdb->get_results("SELECT `post_id`, `post_type` FROM {$calculation_table}");
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
                    $sentence_post_id,
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

            $results = self::call_linkwhisper_ai($message_list);

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
        
                    self::save_response_tokens($dat);
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
        self::$purpose = 'create-post-sentence-embeddings'; // TODO: create link cleanup that will check to see if the links have changed between the processed and new text and will tell the stupid AI if it should reprocessed the posit content.
        $phrases = Wpil_Suggestion::getPhrases($post->getContent(), false, array(), false, array(), ('sentence_text' === Wpil_Suggestion::get_phrase_text_prop()));
        //$phrases = Wpil_Suggestion::getPhrases($post->getContent()); // TODO: Review and see if we need to id text instead of sentence_text
        $message_list = array();

        $phrase_list = array();
        foreach($phrases as $phrase){
            $text = Wpil_Suggestion::get_ai_phrase_text($phrase);
            if(empty($text) || strlen($text) < 3 || Wpil_Word::getWordCount($text) < 4){ // only process sentences that are long enough to matter
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

            $results = self::call_linkwhisper_ai($message_list, $model);
        }else{
            foreach($phrase_list as $text => $dat){
                $message_list[] = array(
                    "model" => $model,
                    "input" => $text,
                    "dimensions" => 2048
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
                self::save_response_tokens($dat);
            }
        }

        return $embedding_data;
    }

    /**
     * 
     **/
    public static function process_streamed_data($handle_id, $content, $info){
        $skip_streaming = array(
            'create-post-sentence-embeddings',
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
                self::save_site_embeddings($dat_object);
            }

            self::save_response_tokens($content);
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

                    $processed_embeddings[$id]->calculation[$sub_id] = self::compare_post_embeddings($dat, $d);
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

        if(empty($calculated_phrase_data)){
            $first_sentence = reset($phrase_embeddings->embed_data);
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

        if(empty($index)){
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
        $table = $wpdb->prefix . 'wpil_ai_embedding_calculation_data';

        return !empty($wpdb->get_var("SELECT COUNT(*) FROM $table LIMIT 1"));
    }

    /**
     * Gets all of the embedding data for posts|terms that are stored in the embeddings table.
     * Can return data for a specific post|term
     **/
    public static function get_calculated_embedding_data($post_id = 0, $post_type = 'post', $offset = 0, $limit = 500){
        global $wpdb;
        $table = $wpdb->prefix . 'wpil_ai_embedding_calculation_data';
        $posts = array();

        if($post_type !== 'post' && $post_type !== 'term'){
            return $posts;
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

        // if there is no post id
        if(empty($post_id)){
            $data = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} LIMIT %d OFFSET %d", $limit, ($limit * $offset)));

            if(!empty($data)){
                foreach($data as $dat){
                    $id = $dat->post_type . '_' . $dat->post_id;
                    if(!isset(self::$cached_embedding_data[$id])){
                        self::$cached_embedding_data[$id] = $dat;
                    }
                }
            }else{
                self::$cached_embedding_data = 'no-calculations';
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

    /**
     * Gets the embedding data for a specific post from the calculation table.
     **/
    public static function get_embedding_relatedness_data($post_id = 0, $post_type = 'post', $assoc = false){
        global $wpdb;
        $table = $wpdb->prefix . 'wpil_ai_embedding_calculation_data';
        $posts = array();

        if($post_type !== 'post' && $post_type !== 'term' || empty($post_id)){
            return $posts;
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
        $table = $wpdb->prefix . 'wpil_ai_embedding_calculation_data';
        $posts = array();

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
    public static function get_single_post_embedding_data($post, $decode_embeddings = false){
        global $wpdb;
        $table = $wpdb->prefix . 'wpil_ai_embedding_phrase_data';

        if(empty($post) || !is_a($post, 'Wpil_Model_Post')){
            return array();
        }

        $embedding_data = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE `post_id` = %d AND `post_type` = %s", $post->id, $post->type));

        if(!empty($embedding_data) && $decode_embeddings){
            foreach($embedding_data as $key => $data){
                $embedding_data[$key]->embed_data = Wpil_Toolbox::json_decompress($data->embed_data);
            }
        }

        return (!empty($embedding_data)) ? $embedding_data[0]: array();
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
                $embeddings = Wpil_Toolbox::json_compress($dat->calculation);
                $last_embedding_index = $dat->calc_index;
                $calc_count = count($dat->calculation);
                $model = $dat->model_version;
            }else{
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
            }
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
        );

        if(self::$rate_limited){
            $message['title']   = __("Unable to Complete: Rate Limiting Active", 'wpil');
            $message['text']    = sprintf(__("It seems that the API key's %s per hour has been reached, and you may need to wait for the processing limits to reset. If you haven't already, please wait an hour and then try again.", "wpil") . '<br><br>' . __("If you see this message again after waiting an hour, please wait 24 hours before restarting the process.", 'wpil'), '<a href="https://platform.openai.com/docs/guides/rate-limits/usage-tiers?context=tier-one">' . __('limit on how much data can be processed', 'wpil') .'</a>' );
        }elseif(self::$insufficient_quota){
            $message['title']   = __("Unable to Complete: Credit Limit Reached", 'wpil');
            $message['text']    = __("It seems that the credit limit for the API key has been reached, and further processing isn't possible without more credit.", 'wpil') . '<br><br>' . __('To add more credit to the account, please go here: ', 'wpil') . '<br><br>' . '<a href="https://platform.openai.com/settings/organization/billing/overview" target="_blank">OpenAI Account Billing</a>';
        }elseif(self::$invalid_api_key){
            $message['title']   = __("Unable to Complete: Invalid API Key", 'wpil');
            $message['text']    = __("It seems that there was a mistake when entering the API key, and OpenAI is rejecting our contact request.", 'wpil') . '<br><br>' . __('If you have the API key written down, please try re-entering it in the settings.', 'wpil') . '<br><br>' . sprintf(__('If you don\'t have the API key written down, please %s and enter it in the Settings.', 'wpil'), '<a href="https://platform.openai.com/api-keys" target="_blank">' . __('generate a new one from your OpenAI account', 'wpil') . '</a>');
        }elseif(self::$invalid_request){
            $message['title']   = __("Unable to Complete: Invalid Request", 'wpil');
            $message['text']    = __("It seems that there was an error when reaching out to OpenAI, and post content wasn't able to be processed. Link Whisper isn't sure what caused the error, but it should be logged in the \"System Error Log\" area of the Settings.", 'wpil');
        }else{
            $message['title']   = __("Unable to Complete: Unknown Error", 'wpil');
            $message['text']    = __('It seems that there was an error and some posts may not have been processed by OpenAI. If you see any indications that posts haven\'t been processed, please try waiting an hour and then try restarting the process.', 'wpil');
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
        );

        if(self::$rate_limited){
//            $message['title']   = __("Unable to Complete: Rate Limiting Active", 'wpil');
//            $message['text']    = sprintf(__("It seems that the API key's %s per hour has been reached, and you may need to wait for the processing limits to reset. If you haven't already, please wait an hour and then try again.", "wpil") . '<br><br>' . __("If you see this message again after waiting an hour, please wait 24 hours before restarting the process.", 'wpil'), '<a href="https://platform.openai.com/docs/guides/rate-limits/usage-tiers?context=tier-one">' . __('limit on how much data can be processed', 'wpil') .'</a>' );
        }elseif(self::$invalid_api_key){
            $message['title']   = __("Unable to Complete: API Not Accessible", 'wpil');
            $message['text']    = __("Link Whisper isn't able to make contact with the AI server. This could be caused by network traffic, or a configuration issue.", 'wpil') . '<br><br>' .__("If this is the first time this has happened, please wait 30 minutes and try again.", 'wpil') . '<br><br>' .  sprintf(__('If its happed before, please reach out to Link Whisper support %s so we can help you with this issue.', 'wpil'), '<a href="'.esc_url(WPIL_STORE_URL . '/support').'">right here</a>');
        }elseif(self::$user_not_exist){
            $message['title']   = __("Unable to Complete: AI User Not Logged", 'wpil');
            $message['text']    = __("Unfortunately, it looks like there was an error when setting up the AI connection, and Link Whisper can't access our AI server.", 'wpil') . '<br><br>' . sprintf(__('To resolve this, please reach out to Link Whisper support %s', 'wpil'), '<a href="'.esc_url(WPIL_STORE_URL . '/support').'">right here</a>');
        }elseif(self::$insufficient_quota){
            $message['title']   = __("Unable to Complete: Insufficient Credits", 'wpil');
            $message['text']    = __("Unfortunately, there aren't enough AI credits available to process the posts.", 'wpil') . '<br><br>' . __('To add more to your account, please go here: ', 'wpil') . '<br><br>' . '<a href="' .admin_url('admin.php?page=link_whisper_ai_subscription'). '" target="_blank">AI Subscription Management</a>';
        }else{
            $message['title']   = __("Unable to Complete: Unknown Error", 'wpil');
            $message['text']    = __('It seems that there was an error and some posts may not have been processed by OpenAI. If you see any indications that posts haven\'t been processed, please try waiting an hour and then try restarting the process.', 'wpil');
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
     * Estimates the cost required to process a specific piece of content.
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
     * Gets the current number of credits that the user has available
     **/
    public static function get_available_ai_credits($refresh = false, $precision = false){
        if(!self::$ai_service_connected){
            return 0;
        }

        $credits = get_transient('wpil_ai_credit_balance');
        
        if(empty($credits) || $refresh){
            $credits = self::call_linkwhisper_ai('return_credits', '', 'get-available-credits');
            if(!empty($credits)){
                set_transient('wpil_ai_credit_balance', $credits, 60 * MINUTE_IN_SECONDS);
            }else{
                set_transient('wpil_ai_credit_balance', 'no-credits', 60 * MINUTE_IN_SECONDS);
            }
        }elseif($credits === 'no-credits'){
            return 0;
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
        }
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
            $response = wp_remote_post(WPIL_STORE_URL . '/wp-json/lwasc-checkout/v1/get-subscription', [
                'headers' => [ 'Content-Type' => 'application/json' ],
                'body'    => json_encode([ 'ai_id' => Wpil_Settings::get_linkwhisper_ai_user_id() ]),
                'timeout' => 45,
            ]);

            if(!is_wp_error($response)){
                $response = json_decode(wp_remote_retrieve_body($response));
            }

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
     * 
     **/
    public static function get_linkwhisper_ai_auth_url($return_url = null){
        $params = array(
            'target' => base64_encode(get_rest_url(null, '/' . Wpil_Rest::REST_SLUG . '/' . Wpil_Rest::AI_AUTH)),
            'return_url' => (!empty($return_url)) ? base64_encode($return_url): base64_encode(admin_url('admin.php?page=link_whisper_ai_subscription')),
            'site_url' => base64_encode(site_url()),
            'uid' => base64_encode(get_current_user_id())
        );
        $url = add_query_arg($params, WPIL_STORE_URL . '/connect-link-whisper-ai/');
        return $url;
    }
}
