<?php

/**
 * Report controller
 */
class Wpil_Report
{
    static $all_post_ids = array();
    static $all_term_ids = array();
    static $all_post_count;
    static $memory_break_point;

    public static $meta_keys = [
        'wpil_links_outbound_internal_count',
        'wpil_links_inbound_internal_count',
        'wpil_links_outbound_external_count'
    ];
    /**
     * Register services
     */
    public function register()
    {
        add_action('wp_ajax_reset_report_data', [$this, 'ajax_reset_report_data']);
        add_action('wp_ajax_process_report_data', [$this, 'ajax_process_report_data']);
        add_action('wp_ajax_wpil_save_user_filter_settings', [$this, 'ajax_save_user_filter_settings']);
        add_filter('screen_settings', [ $this, 'showScreenOptions' ], 10, 2);
        add_filter('set_screen_option_report_options', [$this, 'saveOptions'], 12, 3);
        add_action('wp_ajax_get_link_report_dropdown_data', array(__CLASS__, 'ajax_assemble_link_report_dropdown_data'));
        add_action('wp_ajax_get_link_report_link_data', array(__CLASS__, 'ajax_assemble_link_report_link_data'));
        add_action('wp_ajax_get_domain_report_data', array('Wpil_Dashboard', 'ajax_get_domain_report_data'));
        add_action('wp_ajax_wpil_save_screen_options', array(__CLASS__, 'ajax_save_screen_options'));
        add_action('wp_ajax_wpil_dismiss_popup_notice', array(__CLASS__, 'ajax_dismiss_popup_notice'));
    }

    /**
     * Reports init function
     */
    public static function init()
    {
        global $wpdb;

        //exit if user role lower than editor
        $user = wp_get_current_user();
        $capability = apply_filters('wpil_filter_main_permission_check', 'manage_categories', Wpil_Base::get_current_page());
        if (!current_user_can($capability)) {
            exit;
        }

        $type = !empty($_GET['type']) ? $_GET['type'] : '';
        $post = Wpil_Base::getPost();
        //post links count update page
        if ($type == 'post_links_count_update' && !empty($post) && isset($_GET['nonce']) && $post->verify_post_nonce($_GET['nonce'])) {
            self::postLinksCountUpdate();
            return;
        } elseif ($type == 'ignore_link') {
            Wpil_Error::markLinkIgnored();
            return;
        } elseif ($type == 'stop_ignore_link') {
            Wpil_Error::unmarkLinkIgnored();
            return;
        }

        switch($type) {
            case 'links':
                Wpil_Settings::create_ai_credit_popup();
                Wpil_Base::show_tawkto_widget();
//                self::outputCustomTabStyles();
                $tbl = new Wpil_Table_Report();
                $page = isset($_REQUEST['page']) ? sanitize_text_field($_REQUEST['page']) : 'link_whisper';
                $title = ''; // $title is title used in the link report
                $report_description = '';
                $sub_report = '';
                if(isset($_GET['orphaned'])){
                    $title = __('Orphaned Posts Report', 'wpil');
                }elseif(isset($_REQUEST['link_density'])){
                    $title = __('Link Coverage Report', 'wpil');
                    $report_description = 
                    '<div style="float: left; background: #fff; padding: 10px; border-radius: 5px; border: 1px solid #cdcdcd; width:100%">
                        <div style="font-size: 18px;">'. esc_html__('SEO best practices recommend posts have at least 1 Inbound Internal link, and 3 or more Outbound Internal links.', 'wpil') .'</div>'
                        . '<br>
                        <div style="font-size: 16px;">' . esc_html__('To help you meet these goals, this report will show you all the posts that could use some links, and give our recommendation on how many links to add!', 'wpil').'</div></div>';
                }elseif(isset($_REQUEST['anchor_length'])){
                    $title = __('Anchor Length Report', 'wpil');
                    $report_description = 
                    '<div style="float: left; background: #fff; padding: 10px; border-radius: 5px; border: 1px solid #cdcdcd; width:100%">
                        <div style="font-size: 18px;">'. esc_html__('SEO best practices recommend link anchor texts be between 3 and 7 words in length.', 'wpil') .'</div>'
                        . '<br>
                        <div style="font-size: 16px;">' . sprintf(esc_html__('To help you meet these goals, this report shows posts with out-of-guideline anchor text and %s where the percentage of compliant links is low.', 'wpil'), '<span style="background: #7645b1;border-radius: 10px;padding: 2px 6px;color: #fefefe;font-weight: bold;">highlights</span>').'</div></div>';
                    $sub_report = '<input id="wpil-report-sub-type" type="hidden" value="anchor_length">';
                }elseif(isset($_REQUEST['link_relation'])){
                    $title = __('Link Quality Report', 'wpil');
                    $report_description = 
                    '<div style="float: left; background: #fff; padding: 10px; border-radius: 5px; border: 1px solid #cdcdcd; width:100%">
                        <div style="font-size: 18px;">'. esc_html__('SEO best practices recommend linking between related posts, and avoiding links between unrelated posts.', 'wpil') .'</div>'
                        . '<br>
                        <div style="font-size: 16px;">' . sprintf(esc_html__('We recommended having at least 80%% of links going to related posts. We have %s all of posts that have a low proportion of related-post links.', 'wpil'), '<span style="background: #7645b1;border-radius: 10px;padding: 2px 6px;color: #fefefe;font-weight: bold;">highlighted</span>').'</div></div>';
                }else{
                    $title = __('Internal Links Report', 'wpil');
                }
                include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/link_report_v2.php';
                break;
            case 'domains':
                Wpil_Settings::create_ai_credit_popup();
                Wpil_Base::show_tawkto_widget();
                $table = new Wpil_Table_Domain();
                $table->prepare_items();
                $report_description = "";
                if(isset($_REQUEST['domain_focus'])){
                    $report_description = 
                    '<div style="float: left; background: #fff; padding: 10px; border-radius: 5px; border: 1px solid #cdcdcd; width:100%">
                        <div style="font-size: 18px;">'. esc_html__('SEO best practices recommend creating a natural external link profile, and avoiding over-linking to a single domain.', 'wpil') .'</div>'
                        . '<br>
                        <div style="font-size: 16px;">' . sprintf(esc_html__('We recommended having no more than 60%% of all external links going to the same external site. We have %s any domains that exceed this.', 'wpil'), '<span style="background: #7645b1;border-radius: 10px;padding: 2px 6px;color: #fefefe;font-weight: bold;">highlighted</span>').'</div></div>';
                }
                include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/report_domains.php';
                break;
            case 'clicks':
                Wpil_Settings::create_ai_credit_popup();
                Wpil_Base::show_tawkto_widget();
                $table = new Wpil_Table_Click();
                $table->prepare_items();
                include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/report_clicks.php';
                break;
            case 'click_details_page':
                Wpil_Settings::create_ai_credit_popup();
                Wpil_Base::show_tawkto_widget();
                self::setup_click_details_page();
                break;
            case 'error':
                Wpil_Settings::create_ai_credit_popup();
                Wpil_Base::show_tawkto_widget();
                $error_reset_run = get_option('wpil_error_reset_run', 0);
                if ($error_reset_run) {
                    include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/error_process_posts.php';
                } else {
                    $table = new Wpil_Table_Error();
                    $table->prepare_items();
                    include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/report_error.php';
                }
                break;
            case 'sitemaps':
                Wpil_Settings::create_ai_credit_popup();
                Wpil_Base::show_tawkto_widget();
                $table = new Wpil_Table_Sitemap();
                $table->prepare_items();
                include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/report_sitemaps.php';
                break;
            default:
                Wpil_Settings::create_ai_credit_popup();
                Wpil_Base::show_tawkto_widget();
                $domains = Wpil_Dashboard::getTopDomains();
                $top_domain = !empty($domains[0]->cnt) ? $domains[0]->cnt : 0;
                wp_register_script('wpil_chart_js', WP_INTERNAL_LINKING_PLUGIN_URL . 'js/jquery.jqChart.min.js', array('jquery'), false, false);
                wp_enqueue_script('wpil_chart_js');
                wp_register_style('wpil_chart_css', WP_INTERNAL_LINKING_PLUGIN_URL . 'css/jquery.jqChart.css');
                wp_enqueue_style('wpil_chart_css');
                include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/report_dashboard.php';
                break;
        }
    }

    /**
     * Resets all the stored link data in both the meta and the LW link table, on ajax call.
     **/
    public static function ajax_reset_report_data(){
        global $wpdb;
        $links_table = $wpdb->prefix . "wpil_report_links";
        Wpil_Base::verify_nonce('wpil_reset_report_data');

        // say that we're processing report data if we haven't already
        if(!defined('WPIL_RUNNING_LINK_SCAN')){
            define('WPIL_RUNNING_LINK_SCAN', true);
        }

        // be sure to ignore any external object caches
        Wpil_Base::ignore_external_object_cache();

        // Remove any hooks that may interfere with AJAX requests
        Wpil_Base::remove_problem_hooks();

        // validate the data and set the default values
        $status = array(
            'nonce'                     => $_POST['nonce'],
            'loop_count'                => isset($_POST['loop_count'])  ? (int)$_POST['loop_count'] : 0,
            'clear_data'                => (isset($_POST['clear_data']) && 'true' === $_POST['clear_data'])  ? true : false,
            'data_setup_complete'       => false,
            'time'                      => microtime(true),
        );

        // if we're clearing data
        if(true === $status['clear_data']){
            // clear the exsting post meta
            self::clearMeta();

            // clear the link table
            self::setupWpilLinkTable();

            // check to see that the link table was successfully created
            $table = $wpdb->get_results("SELECT `post_id` FROM {$links_table} LIMIT 1");
            if(!empty($wpdb->last_error)){
                // if there was an error, let the user know about it
                wp_send_json(array(
                    'error' => array(
                        'title' => __('Database Error', 'wpil'),
                        'text'  => sprintf(__('There was an error in creating the links database table. The error message was: %s', 'wpil'), $wpdb->last_error),
                    )
                ));
            }

            // set the flag to say that the table has been created and the scan is considered to have started
            update_option('wpil_has_run_initial_scan', true);
            // say what version we ran the scan on
            $plugin_data = get_plugin_data(WP_INTERNAL_LINKING_PLUGIN_DIR . 'link-whisper.php');
            update_option('wpil_scan_last_plugin_version', $plugin_data['Version']);
            // say when the scan was run too
            update_option('wpil_scan_last_run_time', date('c'));
            // clear any stored external site data
            //Wpil_SiteConnector::clear_data_table();
            // clear redirect transients
            delete_transient('wpil_redirected_post_ids');
            delete_transient('wpil_redirected_post_urls');

            // delete the total post transient if it's still with us
            delete_transient('wpil_total_process_post_count');

            // delete the post stat update caches if they're still here
            delete_transient('wpil_refresh_all_stat_not_update_count');
            delete_transient('wpil_refresh_all_stat_post_not_update');
            delete_transient('wpil_refresh_all_stat_term_not_update');

            // set the clear data flag to false now that we're done clearing the data
            $status['clear_data'] = false;
            // signal that the data setup is complete
            $status['data_setup_complete'] = true;
            // get the meta processing screen to show the user on the next leg of processing
            $status['loading_screen'] = self::get_loading_screen('meta-loading-screen');
            // and send back the notice
            wp_send_json($status);
        }

        // if we made it this far without a break, there must have been data missing
        wp_send_json(array(
                'error' => array(
                    'title' => __('Data Error', 'wpil'),
                    'text'  => __('There was some data missing from the reset attempt, please refresh the page and try again.', 'wpil'),
                )
        ));
    }

    /**
     * Inserts the data needed to generate the report in the meta and the link table, on ajax call.
     **/
    public static function ajax_process_report_data(){
        global $wpdb;
        $links_table = $wpdb->prefix . "wpil_report_links";
        Wpil_Base::verify_nonce('wpil_reset_report_data');

        // say that we're processing report data if we haven't already
        if(!defined('WPIL_RUNNING_LINK_SCAN')){
            define('WPIL_RUNNING_LINK_SCAN', true);
        }

        // be sure to ignore any external object caches
        Wpil_Base::ignore_external_object_cache();

        // Remove any hooks that may interfere with AJAX requests
        Wpil_Base::remove_problem_hooks();

        // validate the data and set the default return values
        $status = array(
            'nonce'                         => $_POST['nonce'],
            'loop_count'                    => isset($_POST['loop_count'])             ? (int)$_POST['loop_count'] : 0,
            'link_posts_to_process_count'   => isset($_POST['link_posts_to_process_count']) ? (int)$_POST['link_posts_to_process_count'] : 0,
            'link_posts_processed'          => isset($_POST['link_posts_processed'])   ? (int)$_POST['link_posts_processed'] : 0,
            'link_posts_to_process_diff'    => isset($_POST['link_posts_to_process_diff'])   ? (int)$_POST['link_posts_to_process_diff'] : 0,
            'meta_filled'                   => (isset($_POST['meta_filled']) && 'true' === $_POST['meta_filled']) ? true : false,
            'links_filled'                  => (isset($_POST['links_filled']) && 'true' === $_POST['links_filled']) ? true : false,
            'link_processing_complete'      => false,
            'time'                          => microtime(true),
            'loops_unchanged'               => isset($_POST['loops_unchanged'])   ? (int)$_POST['loops_unchanged'] : 0,
        );

        // get any saved data if we're resuming
        if(isset($_POST['resume_scan']) && !empty($_POST['resume_scan']) && $_POST['resume_scan'] !== 'false'){
            $old_data = get_transient('wpil_resume_scan_data');
            if(!empty($old_data)){
                $status = array_merge($status, $old_data);
            }
        }

        // if the total post count hasn't been obtained yet
        if(0 === $status['link_posts_to_process_count']){
            $status['link_posts_to_process_count'] = self::get_total_post_count();
        }

        // if the meta flags haven't been set
        if(false === $status['meta_filled']){
            if (self::fillMeta()) {
                $status['meta_filled'] = true;
                $status['loading_screen'] = self::get_loading_screen('link-loading-screen');
            }
            // store the current state in case the user needs to resume later
            set_transient('wpil_resume_scan_data', $status, DAY_IN_SECONDS * 3);
            Wpil_Base::set_transient('wpil_resume_scan_data', $status, DAY_IN_SECONDS * 3);
            wp_send_json($status);
        }

        // if the links in the table haven't been filled
        if(false === $status['links_filled']){
            // check to see if there's already some posts processed
            if(0 === $status['link_posts_processed']){
                $status['link_posts_processed'] = $wpdb->get_var("SELECT COUNT(DISTINCT {$links_table}.post_id) FROM {$links_table}");
                // clear any existing stored ids
                delete_transient('wpil_stored_unprocessed_link_ids');
            }
            // begin filling the link table with link references
            $link_processing = self::fillWpilLinkTable();
            // add the number of processed posts to the total count
            $status['link_posts_processed'] += $link_processing['inserted_posts'];
            // say if we're done processing links or not
            $status['links_filled'] = $link_processing['completed'];
            // and signal if the pre processing is complete
            $status['link_processing_complete'] = $link_processing['completed'];

            // if the links have all been processed or we've been in the same place for a really long time
            if($link_processing['completed'] || $status['loops_unchanged'] > 30){
                // get the post processing loading screen
                $status['loading_screen'] = self::get_loading_screen('post-loading-screen');
                // and mark the links as "filled"
                $status['links_filled'] = true;
            }else{
                $processed_post_diff = ($status['link_posts_to_process_count'] - $status['link_posts_processed']);

                // if there's no diff set yet or the diff is different from last time
                if(empty($status['link_posts_to_process_diff']) || (int) $status['link_posts_to_process_diff'] !== (int) $processed_post_diff){
                    // set the current difference between the unprocessed and the processed posts
                    $status['link_posts_to_process_diff'] = $processed_post_diff;
                    // and set the unchanged counter to 0
                    $status['loops_unchanged'] = 0;
                }else{
                    // if the diff is the same between processing runs, increament the unchanged tracker
                    $status['loops_unchanged']++;
                }
            }
            // store the current state in case the user needs to resume later
            set_transient('wpil_resume_scan_data', $status, DAY_IN_SECONDS * 3);
            Wpil_Base::set_transient('wpil_resume_scan_data', $status, DAY_IN_SECONDS * 3);
            // send back the current status data
            wp_send_json($status);
        }

        if(Wpil_Settings::use_link_table_for_data()){
            // if we're just going to be running with the link table, check the process boxes now
            $wpdb->update($wpdb->postmeta, ['meta_value' => '1'], ['meta_key' => 'wpil_sync_report3']);
            $wpdb->update($wpdb->termmeta, ['meta_value' => '1'], ['meta_key' => 'wpil_sync_report3']);
            $refresh = array(
                'loaded' => $status['link_posts_processed'],
                'processed' => $status['link_posts_processed'],
                'finished' => true,
                'remained' => 0
            );
        }else{
            // refresh the posts inbound/outbound link stats
            $refresh = self::refreshAllStat(true);
        }

        // note how many posts have been refreshed
        $status['link_posts_processed'] = $refresh['loaded'];
        // and if we're done yet
        $status['processing_complete']  = $refresh['finished'];
        // track the progress
        Wpil_Toolbox::track_process_progress(
            'post_scanning', 
            'Scanning Posts', 
            $refresh['processed'], 
            $refresh['remained'], 
            $status['link_posts_to_process_count']
        );

        // if we are done with this stretch
        if(!empty($status['processing_complete'])){
            // clear the WP cache
            wp_cache_flush();
            // clear the stored scan state so there's no trouble in the future
            delete_transient('wpil_resume_scan_data');
        }else{
            // store the current state in case the user needs to resume later
            set_transient('wpil_resume_scan_data', $status, DAY_IN_SECONDS * 3);
            Wpil_Base::set_transient('wpil_resume_scan_data', $status, DAY_IN_SECONDS * 3);
        }

        wp_send_json($status);
    }

    /**
     * Refresh posts statistics
     *
     * @return array
     */
    public static function refreshAllStat($report_building = false)
    {
        global $wpdb;
        $post_table  = $wpdb->posts;
        $meta_table  = $wpdb->postmeta;
        $post_types = Wpil_Settings::getPostTypes();
        $process_terms = !empty(Wpil_Settings::getTermTypes());

        $cache_not_updated = get_transient('wpil_refresh_all_stat_not_update_count');
        $cache_post_not_updated = get_transient('wpil_refresh_all_stat_post_not_update');
        $cache_term_not_updated = get_transient('wpil_refresh_all_stat_term_not_update');

        //get all posts count
        $all = self::get_total_post_count();
        $post_type_replace_string = !empty($post_types) ? " AND {$wpdb->posts}.post_type IN ('" . (implode("','", $post_types)) . "') " : "";

        if(empty($cache_not_updated)){
            $updated = 0;
            if($post_types){
                // get the total number of posts that have been updated
                $statuses_query = Wpil_Query::postStatuses($wpdb->posts);
                $updated += $wpdb->get_var("SELECT COUNT({$post_table}.ID) FROM {$post_table} LEFT JOIN {$meta_table} ON ({$post_table}.ID = {$meta_table}.post_id ) WHERE 1=1 AND ( {$meta_table}.meta_key = 'wpil_sync_report3' AND {$meta_table}.meta_value = 1 ) {$post_type_replace_string} $statuses_query");
            }
            // if categories are a selected type
            if($process_terms){
                // add the total number of categories that have been updated
                $updated += $wpdb->get_var("SELECT COUNT(`term_id`) FROM {$wpdb->termmeta} WHERE meta_key = 'wpil_sync_report3' AND meta_value = '1'");
            }
            // and subtract them from the total post count to get the number that have yet to be updated
            $not_updated_count = ($all - $updated);
        }else{
            $not_updated_count = $cache_not_updated;
        }

        // get the post processing limit and add it to the query variables
        $limit = (Wpil_Settings::getProcessingBatchSize()/10);

        $start = microtime(true);
        $time_limit = ($report_building) ? 20: 5;
        $memory_break_point = self::get_mem_break_point();
        $processed_link_count = 0;
        while(true){
            if(empty($cache_post_not_updated)){
                // get the posts that haven't been updated, subject to the proccessing limit
                $statuses_query = Wpil_Query::postStatuses($wpdb->posts);
                $posts_not_updated = $wpdb->get_results("SELECT {$post_table}.ID FROM {$post_table} LEFT JOIN {$meta_table} ON ({$post_table}.ID = {$meta_table}.post_id AND {$meta_table}.meta_key = 'wpil_sync_report3' ) WHERE 1=1 AND ( {$meta_table}.meta_value != 1 ) {$post_type_replace_string} $statuses_query GROUP BY {$post_table}.ID ORDER BY {$post_table}.post_date DESC LIMIT $limit");
            }else{
                $posts_not_updated = $cache_post_not_updated;
            }

            if(empty($cache_term_not_updated)){
                if($process_terms){
                    $terms_not_updated = $wpdb->get_results("SELECT `term_id` FROM {$wpdb->termmeta} WHERE meta_key = 'wpil_sync_report3' AND meta_value = '0'");
                }else{
                    $terms_not_updated = 0;
                }
            }else{
                $terms_not_updated = $cache_term_not_updated;
            }

            // break if there's no posts/cats to update, or the loop is out of time.
            if( (empty($posts_not_updated) && empty($terms_not_updated)) || microtime(true) - $start > $time_limit){
                break;
            }

            //update posts statistics
            if (!empty($posts_not_updated)) {
                foreach($posts_not_updated as $key => $post){
                    if (microtime(true) - $start > $time_limit) {
                        break;
                    }

                    // if there is a memory limit and we've passed the safe limit
                    if('disabled' !== $memory_break_point && memory_get_usage() > $memory_break_point){
                        // update the last updated date
                        update_option('wpil_2_report_last_updated', date('c'));
                        // exit this loop and the WHILE loop that wraps it
                        break 2;
                    }

                    $post_obj = new Wpil_Model_Post($post->ID);
                    self::statUpdate($post_obj, $report_building);

                    $processed_link_count++;
                }
            }

            //update term statistics
            if (!empty($terms_not_updated)) {
                foreach($terms_not_updated as $key => $cat){
                    if (microtime(true) - $start > $time_limit) {
                        break;
                    }

                    // if there is a memory limit and we've passed the safe limit
                    if('disabled' !== $memory_break_point && memory_get_usage() > $memory_break_point){
                        // update the last updated date
                        update_option('wpil_2_report_last_updated', date('c'));
                        // exit this loop and the WHILE loop that wraps it
                        break 2;
                    }

                    $post_obj = new Wpil_Model_Post($cat->term_id, 'term');
                    self::statUpdate($post_obj, $report_building);

                    $processed_link_count++;
                }
            }
        }

        update_option('wpil_2_report_last_updated', date('c'));

        $not_updated_count -= $processed_link_count;

        set_transient('wpil_refresh_all_stat_not_update_count', $not_updated_count, MINUTE_IN_SECONDS * 5);
        set_transient('wpil_refresh_all_stat_post_not_update', $posts_not_updated, MINUTE_IN_SECONDS * 5);
        set_transient('wpil_refresh_all_stat_term_not_update', $terms_not_updated, MINUTE_IN_SECONDS * 5);

        //create array with results
        $r = ['time'=> microtime(true),
            'success' => true,
            'all' => $all,
            'remained' => ($not_updated_count - $processed_link_count), // doesn't seem to be used. Review later
            'loaded' => ($all - $not_updated_count),
            'finished' => ($not_updated_count <= 0) ? true : false,
            'processed' => $processed_link_count,
            'w' => $all ? round((($all - $not_updated_count) / $all) * 100) : 100,
        ];
        $r['status'] = "$r[w]%, $r[loaded] / $r[all]";

        return $r;
    }

    /**
     * Clears the stored metadata that is created in posts and terms.
     **/
    public static function clearMeta(){
        global $wpdb;

        // create a list of the meta keys we store link data in
        $meta_keys = array( 
            'wpil_links_outbound_internal_count',
            'wpil_links_inbound_internal_count',
            'wpil_links_outbound_external_count',
            'wpil_links_outbound_internal_count_data',
            'wpil_links_inbound_internal_count_data',
            'wpil_links_outbound_external_count_data',
            'wpil_sync_report3',
            'wpil_sync_report2_time',
            'wpil_links' // clear out the link meta too... For some reason, link old links to insert sometimes stick around!
        );

        // clear any stored meta data
        foreach($meta_keys as $key) {
            $wpdb->delete($wpdb->prefix.'postmeta', ['meta_key' => $key]);
            $wpdb->delete($wpdb->prefix.'termmeta', ['meta_key' => $key]);
        }
    }

    /**
     * Create meta records for new posts
     */
    public static function fillMeta()
    {
        global $wpdb;
        $post_table  = $wpdb->prefix . "posts";
        $meta_table  = $wpdb->prefix . "postmeta";
        
        $start = microtime(true);

        $args = array();
        $post_type_replace_string = '';
        $post_types = Wpil_Settings::getPostTypes();
        $process_terms = !empty(Wpil_Settings::getTermTypes());
        $type_count = (count($post_types) - 1);
        foreach($post_types as $key => $post_type){
            if(empty($post_type_replace_string)){
                $post_type_replace_string = ' AND ' . $post_table . '.post_type IN (';
            }
            
            $args[] = $post_type;
            if($key < $type_count){
                $post_type_replace_string .= '%s, ';
            }else{
                $post_type_replace_string .= '%s)';
            }
        }

        $limit = Wpil_Settings::getProcessingBatchSize() * 100;
        $args[] = $limit;
        while(true){
            // select a batch of posts that haven't had their link meta updated yet
            $posts = self::get_untagged_posts();

            if(microtime(true) - $start > 20 || empty($posts)){
                break;
            }

            $count = 0;
            $insert_query = "INSERT INTO {$meta_table} (post_id, meta_key, meta_value) VALUES ";
            $links_data = array ();
            $place_holders = array ();
            foreach ($posts as $post_id) {
                array_push(
                    $links_data, 
                    $post_id,
                    'wpil_sync_report3',
                    '0'
                );
                $place_holders [] = "('%d', '%s', '%s')";

                // if we've hit the limit, stop adding posts to process
                if($count > $limit){
                    break;
                }
                $count++;
            }

            if (count($place_holders) > 0) {
                $insert_query .= implode(', ', $place_holders);
                $insert_query = $wpdb->prepare($insert_query, $links_data);
                $insert_count = $wpdb->query($insert_query);
            }

            if(microtime(true) - $start > 20){
                break;
            }
        }

        // if categories are a selected type
        if($process_terms){
            //create or update meta value for categories
            $taxonomies = Wpil_Settings::getTermTypes();
            $terms = $wpdb->get_results("SELECT term_id FROM {$wpdb->term_taxonomy} WHERE taxonomy IN ('" . implode("', '", $taxonomies) . "')");
            foreach($terms as $term){
                update_term_meta($term->term_id, 'wpil_sync_report3', 0);
            }
        }

        $meta_filled = empty($posts);
        return $meta_filled;
    }

    /**
     * Update post links stats
     *
     * @param object $post An LW post object
     * @param bool $processing_for_report (Are we pulling data from the link table, or the meta? TRUE for the link table, FALSE for the meta)
     */
    public static function statUpdate($post, $processing_for_report = false)
    {
        global $wpdb;
        $meta_table = $wpdb->prefix."postmeta";

        //get links
        if($processing_for_report){
            $internal_inbound   = self::getReportInternalInboundLinks($post);
            $outbound_links     = self::getReportOutboundLinks($post);
        }else{
            $internal_inbound   = self::getInternalInboundLinks($post);
            $outbound_links     = self::getOutboundLinks($post);
        }

        if ($post->type == 'term') {
            //update term meta
            update_term_meta($post->id, 'wpil_links_inbound_internal_count', count($internal_inbound));
            update_term_meta($post->id, 'wpil_links_inbound_internal_count_data', Wpil_Toolbox::compress($internal_inbound));
            update_term_meta($post->id, 'wpil_links_outbound_internal_count', count($outbound_links['internal']));
            update_term_meta($post->id, 'wpil_links_outbound_internal_count_data', Wpil_Toolbox::compress($outbound_links['internal']));
            update_term_meta($post->id, 'wpil_links_outbound_external_count', count($outbound_links['external']));
            update_term_meta($post->id, 'wpil_links_outbound_external_count_data', Wpil_Toolbox::compress($outbound_links['external']));
            update_term_meta($post->id, 'wpil_sync_report3', 1);
            update_term_meta($post->id, 'wpil_sync_report2_time', date('c'));
        } else {
            // create our array of meta data
            $assembled_data = array(
                                'wpil_links_inbound_internal_count'         => count($internal_inbound),
                                'wpil_links_inbound_internal_count_data'    => Wpil_Toolbox::compress($internal_inbound),
                                'wpil_links_outbound_internal_count'        => count($outbound_links['internal']),
                                'wpil_links_outbound_internal_count_data'   => Wpil_Toolbox::compress($outbound_links['internal']),
                                'wpil_links_outbound_external_count'        => count($outbound_links['external']),
                                'wpil_links_outbound_external_count_data'   => Wpil_Toolbox::compress($outbound_links['external']),
//                                'wpil_sync_report3'                         => 1,
                                'wpil_sync_report2_time'                    => date('c'));

            // check to see if any meta data already exists
            $search_query = $wpdb->prepare("SELECT * FROM {$meta_table} WHERE post_id = {$post->id} AND (`meta_key` = %s OR `meta_key` = %s OR `meta_key` = %s OR `meta_key` = %s OR `meta_key` = %s OR `meta_key` = %s OR `meta_key` = %s)", array_keys($assembled_data));
            $results = $wpdb->get_results($search_query);

            // if meta data does exist
            if(!empty($results)){
                // go over the meta we want to save
                foreach($assembled_data as $key => $value){
                    // see if there's old meta data for the current post
                    $updated = false;
                    foreach($results as $stored_data){
                        // if there is old meta data for the current post...
                        if($key === $stored_data->meta_key){
                            // check to make sure the data has changed since it was last saved
                            if($stored_data->meta_value === (string)maybe_serialize($value)){
                                // if it hasn't, mark the data as already updated and skip to the next item
                                $updated = true;
                                break;
                            }
                            // update the meta
                            $wpdb->update(
                                $meta_table,
                                array('meta_value' => maybe_serialize($value)),
                                array('post_id' => $post->id, 'meta_key' => $key)
                            );
                            $updated = true;
                            break;
                        }
                    }
                    // if there isn't old meta data...
                    if(!$updated){
                        // insert the current data
                        $wpdb->insert(
                            $meta_table,
                            array('post_id' => $post->id, 'meta_key' => $key, 'meta_value' => maybe_serialize($value))
                        );
                    }
                }
            }else{
            // if no meta data exists, insert our values
                $insert_query = "INSERT INTO {$meta_table} (post_id, meta_key, meta_value) VALUES ";
                $links_data = array();
                $place_holders = array ();
                foreach($assembled_data as $key => $value){
                    if('wpil_sync_report3' === $key){ // skip the sync flag
                        continue;
                    }

                    array_push (
                        $links_data,
                        $post->id,
                        $key,
                        maybe_serialize($value)
                    );

                    $place_holders [] = "('%d', '%s', '%s')";
                }

                if (count($place_holders) > 0) {
                    $insert_query .= implode (', ', $place_holders);
                    $insert_query = $wpdb->prepare ($insert_query, $links_data);
                    $wpdb->query($insert_query);
                }
            }

            // check to see if the processing flag is set at all
            $checked = $wpdb->get_results("SELECT meta_value FROM {$wpdb->postmeta} WHERE `post_id` = {$post->id} AND `meta_key` = 'wpil_sync_report3'");
            if(empty($checked)){
                // if it's not, set a new flag
                $wpdb->insert(
                    $meta_table,
                    array('post_id' => $post->id, 'meta_key' => 'wpil_sync_report3', 'meta_value' => 1)
                );
            }else{
                // if there's a flag set, make sure it's set to 1
                $wpdb->update(
                    $meta_table,
                    array('meta_key' => 'wpil_sync_report3', 'meta_value' => 1),
                    array('post_id' => $post->id, 'meta_key' => 'wpil_sync_report3')
                );
            }
        }
    }

    public static function getReportInternalInboundLinks($post){
        global $wpdb;
        $links_table = $wpdb->prefix . "wpil_report_links";
        $link_data = array();

        //get other internal links
        $url = Wpil_Link::filter_staging_to_live_domain($post->getLinks()->view);
        $cleaned_url = self::getCleanUrl($url);
        $cleaned_url = str_replace(['http://', 'https://'], '://', $cleaned_url);
        $search_parameters = array( ('https'.$cleaned_url), ('http'.$cleaned_url), $post->id, $post->type);

        // account for ugly permalinks if this is a post
        $ugly_permalinks = "";
        if($post->type === 'post'){
            $cleaned_home_url = trailingslashit(str_replace(['http://', 'https://'], '://', Wpil_Link::filter_staging_to_live_domain(get_home_url())));
            $type = get_post_type($post->id);
            if($type === 'page'){
                $ugly_urls = array(
                    ('https'.$cleaned_home_url.'?page_id='.$post->id),
                    ('http'.$cleaned_home_url.'?page_id='.$post->id)
                );
            }elseif(!empty($type) && $type !== 'post'){
                $ugly_urls = array(
                    ('https'.$cleaned_home_url.'?post_type='. $type . '&p=' . $post->id),
                    ('http'.$cleaned_home_url.'?post_type='. $type . '&p=' . $post->id)
                );
            }else{
                $ugly_urls = array(
                    ('https'.$cleaned_home_url.'?p='.$post->id),
                    ('http'.$cleaned_home_url.'?p='.$post->id)
                );
            }

            $ugly_permalinks = $wpdb->prepare("OR `clean_url` = '%s' OR `clean_url` = '%s'", $ugly_urls);
        }

        $redirect_urls = Wpil_Settings::getRedirectionUrls();
        $redirected = '';
        if(!empty($redirect_urls)){
            $old_url = array_search($url, $redirect_urls);

            if(!empty($old_url)){
                $cleaned_old_url = self::getCleanUrl($old_url);
                $cleaned_old_url = str_replace(['http://', 'https://'], '://', $cleaned_old_url);
                $protocol_variant_old_urls = array( ('https'.$cleaned_old_url), ('http'.$cleaned_old_url) );
                $redirected = $wpdb->prepare("OR `clean_url` = '%s' OR `clean_url` = '%s'", $protocol_variant_old_urls);
            }
        }

        // get all the links from the link table that point at this post and are on the current site.
        $results = $wpdb->get_results($wpdb->prepare("SELECT `post_id`, `post_type`, `host`, `anchor`, `link_whisper_created`, `is_autolink`, `tracking_id`, `module_link`, `link_context`, `ai_relation_score` FROM {$links_table} WHERE (`clean_url` = '%s' OR `clean_url` = '%s' {$ugly_permalinks} {$redirected}) OR (`target_id` = '%d' AND `target_type` = '%s')", $search_parameters));

        $post_objs = array();
        foreach($results as $data){
            if(empty($data->post_id)){
                continue;
            }

            $cache_id = $data->post_type . $data->post_id;
            if(!isset($post_objs[$cache_id])){
                $post_objs[$cache_id] = new Wpil_Model_Post($data->post_id, $data->post_type);
                $post_objs[$cache_id]->content = null;
            }

            $link_data[] = new Wpil_Model_Link([
                'url' => $url,
                'host' => $data->host,
                'internal' => true,
                'post' => $post_objs[$cache_id],
                'anchor' => !empty($data->anchor) ? $data->anchor : '',
                'link_whisper_created' => (isset($data->link_whisper_created) && !empty($data->link_whisper_created)) ? 1: 0,
                'is_autolink' => (isset($data->is_autolink) && !empty($data->is_autolink)) ? 1: 0,
                'tracking_id' => (isset($data->tracking_id) && !empty($data->tracking_id)) ? $data->tracking_id: 0,
                'module_link' => (isset($data->module_link) && !empty($data->module_link)) ? $data->module_link: 0,
                'link_context' => (isset($data->link_context) && !empty($data->link_context)) ? $data->link_context: 0,
                'ai_relation_score' => (isset($data->ai_relation_score) && !empty($data->ai_relation_score)) ? $data->ai_relation_score: 0,
            ]);
        }

        return $link_data;

    }

    /**
     * Cleans up a URL so it's ready for saving to the database.
     * URL cleaning consists of removing query vars, removing the "www." if present and making sure there's a trailling slash.
     * Cleaned URLs are used for index and lookup purposes, so this doesn't affect what the user sees
     **/
    public static function getCleanUrl($url){

        // check if the link isn't a pretty permalink
        if( !empty($url) && 
            (false !== strpos($url, '?') || false !== strpos($url, '&')) && 
            preg_match('#[?&](p|page_id|attachment_id)=(\d+)#', $url, $values))
        {
            // if it is, clean it up just a little and return it
            return strtok(str_replace('www.', '', $url), '#');
        }

        return trailingslashit(strtok(str_replace('www.', '', $url), '?#'));
    }

    /**
     * Gets the current post's inbound links from the cache if they're available.
     * If there's no cache, it attempts to pull up the inbound links for the post.
     * If there aren't any, returns an empty array
     * 
     * @param object $post
     * @return array $link_data
     **/
    public static function getCachedReportInternalInboundLinks($post){
        $link_data = get_transient('wpil_stored_post_internal_inbound_links_' . $post->id);
        if(empty($link_data) && $link_data !== 'no_links'){
            $link_data = self::getReportInternalInboundLinks($post);

            if(!empty($link_data)){
                set_transient('wpil_stored_post_internal_inbound_links_' . $post->id, $link_data, MINUTE_IN_SECONDS * 10);
                Wpil_Base::set_transient('wpil_stored_post_internal_inbound_links_' . $post->id, $link_data, MINUTE_IN_SECONDS * 10);
            }else{
                set_transient('wpil_stored_post_internal_inbound_links_' . $post->id, 'no_links', MINUTE_IN_SECONDS * 10);
                Wpil_Base::set_transient('wpil_stored_post_internal_inbound_links_' . $post->id, 'no_links', MINUTE_IN_SECONDS * 10);
            }

        }elseif('no_links' === $link_data){
            $link_data = array();
        }

        return $link_data;
    }

    public static function getReportOutboundLinks($post){
        global $wpdb;
        $links_table = $wpdb->prefix . "wpil_report_links";

        //create initial array
        $data = array(
            'internal' => array(),
            'external' => array()
        );

        // query all of the link data that the current post has from the link table
        $links = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$links_table} WHERE `post_id` = '%d' AND `post_type` = %s", array($post->id, $post->type)));

        // create a post obj reference to cut down on the number of post queries
        $post_objs = array(); // keyed to clean_url

        // create a nav link reference to cut down on repetetive checks for header and footer links
        $nav_link_objs = array();

        // if the count all links option is active
        if(get_option('wpil_show_all_links', false)){
            // obtain the nav link cache
            $nav_link_objs = get_transient('wpil_nav_link_cache');

            // if it's not empty, merge it with the post objects
            if(!empty($nav_link_objs)){
                $post_objs = array_merge($post_objs, $nav_link_objs);
            }
        }

        //add links to array from post content
        foreach($links as $link){
            // skip if there's no link
            if(empty($link->clean_url)){
                continue;
            }

            // set up the post variable
            $p = null;

            // if the link is an internal one
            if($link->internal){
                // check to see if we've come across the link before
                if(!isset($post_objs[$link->clean_url])){
                    // if we haven't, get the post/term that the link points at
                    
                    // see if we have the target data stored
                    if(isset($link->target_id) && !empty($link->target_id) && isset($link->target_type) && !empty($link->target_type)){
                        $p = new Wpil_Model_Post($link->target_id, $link->target_type);
                    }else{
                        // if we don't, trace the link
                        $p = Wpil_Post::getPostByLink($link->clean_url);
                    }

                    // store the post object in an array in case we need it later
                    $post_objs[$link->clean_url] = $p;

                    // if the link was a nav link
                    if($link->location === 'header' || $link->location === 'footer'){
                        // add it to the nav link array
                        $nav_link_objs[$link->clean_url] = $p;
                    }

                }else{
                    // if the link has been processed previously, set the post obj for the one we stored
                    $p = $post_objs[$link->clean_url];
                }
            }

            $link_obj = new Wpil_Model_Link([
                    'url' => $link->raw_url,
                    'anchor' => $link->anchor,
                    'host' => $link->host,
                    'internal' => Wpil_Link::isInternal($link->raw_url),
                    'post' => $p,
                    'location' => isset($link->location) ? $link->location: 'content',
                    'link_whisper_created' => (isset($link->link_whisper_created) && !empty($link->link_whisper_created)) ? 1: 0,
                    'is_autolink' => (isset($link->is_autolink) && !empty($link->is_autolink)) ? 1: 0,
                    'tracking_id' => (isset($link->tracking_id) && !empty($link->tracking_id)) ? $link->tracking_id: 0,
                    'module_link' => (isset($link->module_link) && !empty($link->module_link)) ? $link->module_link: 0,
                    'link_context' => (isset($link->link_context) && !empty($link->link_context)) ? $link->link_context: 0,
                    'ai_relation_score' => (isset($link->ai_relation_score) && !empty($link->ai_relation_score)) ? $link->ai_relation_score: 0,
            ]);
            
            if ($link->internal) {
                $data['internal'][] = $link_obj;
            } else {
                $data['external'][] = $link_obj;
            }
        }

        // update the nav link cache if there are nav links
        if(!empty($nav_link_objs)){
            set_transient('wpil_nav_link_cache', $nav_link_objs, (4 * HOUR_IN_SECONDS) );
        }

        return $data;
    }

    /**
     * Collect inbound internal links
     * Todo: improve so it pulls links from page builders too
     *
     * @param object $post
     * @return array
     */
    public static function getInternalInboundLinks($post)
    {
        global $wpdb;
        $data = [];

        //get other internal links
        $url = $post->getLinks()->view;
        $host = parse_url($url, PHP_URL_HOST);

        if(empty($url)){
            return $data;
        }

        // if this is a post
        if($post->type === 'post'){
            // trim any trailling slashes to account for times where it's been left off in teh editor
            $url = rtrim($url, '/'); // only doing this for posts since cats can be nested with similar names and I don't want to remove the delimiter
        }

        $posts = [];
        $post_ids = array();

        // make the url protocol agnostic
        $url2 = str_replace(['https://', 'http://'], '://', $url);

        // account for ugly permalinks if this is a post
        $ugly_permalink = "";
        if($post->type === 'post'){
            $type = get_post_type($post->id);
            if($type === 'page'){
                $ugly_relative = "/?page_id=".$post->id;
            }elseif(!empty($type) && $type !== 'post'){
                $ugly_relative = '/?post_type='. $type . '&p='.$post->id;
            }else{
                $ugly_relative = "/?p=".$post->id;
            }

            $ugly_permalink = $wpdb->prepare("OR post_content LIKE %s", Wpil_Toolbox::esc_like($ugly_relative));
        }

        $redirect_urls = Wpil_Settings::getRedirectionUrls();
        $redirected = '';
        $redirected_meta = '';
        if(!empty($redirect_urls)){
            $old_url = array_search($url, $redirect_urls);

            if(!empty($old_url)){
                $cleaned_old_url = self::getCleanUrl($old_url);
                $cleaned_old_url = str_replace(['http://', 'https://'], '://', $cleaned_old_url);
                $redirected = $wpdb->prepare("OR post_content LIKE %s", Wpil_Toolbox::esc_like($cleaned_old_url));
                $redirected_meta = $wpdb->prepare("OR meta_value LIKE %s", Wpil_Toolbox::esc_like($cleaned_old_url));
            }
        }

        $post_types = "AND `post_type` IN ('" . implode("','", Wpil_Settings::getPostTypes()) . "')";

        $statuses_query = Wpil_Query::postStatuses();
        $post_ids = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE (post_content LIKE %s {$redirected} {$ugly_permalink}) {$post_types} {$statuses_query}", Wpil_Toolbox::esc_like($url2)));

        // get inbound links from active builders
        $builder_meta = Wpil_Post::get_builder_meta_keys();

        // if we have builders to search for
        if(!empty($builder_meta)){
            $builder_meta = "('" . implode("','", $builder_meta) . "')";
            $post_types_p = Wpil_Query::postTypes('p');
            $statuses_query_p = Wpil_Query::postStatuses('p');
            $results = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT m.post_id AS id FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON p.ID = m.post_id WHERE m.meta_key IN {$builder_meta} {$post_types_p} {$statuses_query_p} AND m.meta_value LIKE %s {$redirected_meta}", Wpil_Toolbox::esc_like($url2)));
            if(!empty($results)){
                $post_ids = array_merge($post_ids, $results);
                $post_ids = array_flip(array_flip($post_ids));
            }
        }

        if($post_ids){
            foreach($post_ids as $id){
                $posts[] = new Wpil_Model_Post($id);
            }
        }

        //get content from categories
        $result = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->term_taxonomy} WHERE description LIKE %s", Wpil_Toolbox::esc_like($url2)));
        if ($result) {
            foreach ($result as $term) {
                $posts[] = new Wpil_Model_Post($term->term_id, 'term');
            }
        }

        $posts = array_merge($posts, self::getCustomFieldsInboundLinks($url2));

        //make result array from both post types
        foreach($posts as $p){
            preg_match_all('|<a [^>]+'.preg_quote($url2, '|').'[\/]*?[\'\"][^>]*>([^<]*)<|i', $p->getContent(false), $anchors);

            if(!empty($ugly_permalink)){
                preg_match_all('|<a [^>]+'.preg_quote($ugly_relative, '|').'[\/]*?[\'\"][^>]*>([^<]*)<|i', $p->getContent(false), $anchors2);
                $anchors = array_merge($anchors, $anchors2);
            }

            $p->content = null;

            foreach ($anchors[1] as $key => $anchor) {
                if (empty($anchor) && strpos($anchors[0][$key], 'title=') !== false) {
                    preg_match('/<a\s+(?:[^>]*?\s+)?title=(["\'])(.*?)\1/i', $anchors[0][$key], $title);
                    if (!empty($title[2])) {
                        $anchor = $title[2];
                    }
                }

                $data[] = new Wpil_Model_Link([
                    'url' => $url,
                    'host' => str_replace('www.', '', $host),
                    'internal' => Wpil_Link::isInternal($url),
                    'post' => $p,
                    'anchor' => !empty($anchor) ? $anchor : '',
                ]);
            }
        }

        return $data;
    }

    /**
     * Updates the link counts for all posts that the current post is linking to.
     * Link data is from the post meta.
     *
     * @param Wpil_Model_Post $post
     **/
    public static function updateReportInternallyLinkedPosts($post, $removed_links = array()){

        if(empty($post) || !is_object($post)){
            return false;
        }
        // get all the outbound internal links for the current post
        $links = $post->getOutboundInternalLinks();

        // check over the linked posts to remove any that are already up to date
        if(!empty($links)){
            $meta_cache = array();
            foreach($links as $link_key => $link){
                if(!isset($link->post) || empty($link->post->id) || empty($link->post->type) || !isset($link->url) || !isset($link->anchor)){
                    continue;
                }

                $id = $link->post->id . '_' . $link->post->type;

                if(!isset($meta_cache[$id])){
                    $meta_cache[$id] = $link->post->getLinksData('wpil_links_inbound_internal_count');
                }

                $meta_links = $meta_cache[$id];

                if(!empty($meta_links)){
                    foreach($meta_links as $meta_key => $meta_link){
                        if(!isset($meta_link->url) || !isset($meta_link->anchor) || !isset($meta_link->post) || empty($meta_link->post)){
                            continue;
                        }
                        // if there's a reference of a link that matches the current one
                        if( (int)$meta_link->post->id === (int)$post->id && 
                            $meta_link->post->type === $post->type && 
                            rtrim($link->url, '/') === rtrim($meta_link->url, '/') && 
                            $link->anchor === $meta_link->anchor)
                        {
                            // remove the link from processing
                            unset($links[$link_key]);
                            // also remove the meta link so we can handle duplicate link cases
                            unset($meta_links[$meta_key]);
                            // update the cache
                            $meta_cache[$id] = $meta_links;
                            // and exit this sub loop
                            break;
                        }
                    }
                }
            }
        }

        // if we have links after paging through them
        if(!empty($links)){
            // go over each link
            foreach($links as $link_key => $link){
                // if we have a valid link and the post has been scanned into the system
                if( !isset($link->post) || 
                    !isset($link->post->id) || 
                    empty($link->post->id) || 
                    empty($link->post->type) || 
                    !isset($link->url) || 
                    !isset($link->anchor) || 
                    ($link->post->type === 'post' ? empty(get_post_meta($link->post->id, 'wpil_sync_report3', true)): empty(get_term_meta($link->post->id, 'wpil_sync_report3', true))) )
                {
                    continue;
                }

                $link_post = $link->post; //new Wpil_Model_Post($link->target_id, $link->target_type);
                $meta_links = $link_post->getLinksData('wpil_links_inbound_internal_count');

                if(!is_array($meta_links)){
                    continue;
                }

                $meta_links[] = $link;
                $new_link = new Wpil_Model_Link([
                    'url' => $link->raw_url,
                    'anchor' => $link->anchor,
                    'host' => $link->host,
                    'internal' => (bool) $link->internal,
                    'post' => $link_post,
                    'added_by_plugin' => false,
                    'location' => $link->location,
                    'link_whisper_created' => (isset($link->link_whisper_created) && !empty($link->link_whisper_created)) ? 1: 0,
                    'is_autolink' => (isset($link->is_autolink) && !empty($link->is_autolink)) ? 1: 0,
                    'tracking_id' => (isset($link->tracking_id) && !empty($link->tracking_id)) ? $link->tracking_id: 0,
                    'module_link' => (isset($link->module_link) && !empty($link->module_link)) ? $link->module_link: 0,
                    'link_context' => (isset($link->link_context) && !empty($link->link_context)) ? $link->link_context: 0,
                    'ai_relation_score' => (isset($link->ai_relation_score) && !empty($link->ai_relation_score)) ? $link->ai_relation_score: 0,
                ]);

                if($link->post->type === 'post'){
                    Wpil_Toolbox::update_encoded_post_meta($link->post->id, 'wpil_links_inbound_internal_count_data', $meta_links);
                    update_post_meta($link->post->id, 'wpil_links_inbound_internal_count', count($meta_links));
                }else{
                    Wpil_Toolbox::update_encoded_term_meta($link->post->id, 'wpil_links_inbound_internal_count_data', $meta_links);
                    update_term_meta($link->post->id, 'wpil_links_inbound_internal_count', count($meta_links));
                }

                unset($links[$link_key]);
            }
        }


        if(!empty($removed_links)){
            $links = array_merge($links, $removed_links);
        }

        // exit if there's no links
        if(empty($links)){
            return false;
        }

        // get any active redirected urls
        $redirected = Wpil_Settings::getRedirectionUrls();

        // create a list of posts that have already been updated
        $updated = array();

        //add links to array from post content
        foreach($links as $link){
            // skip if there's no link
            if(empty($link->url)){
                continue;
            }

            // set up the post variable
            $p = null;

            // check to see if we've come across the link before
            if(!isset($updated[$link->url])){
                // if we haven't, get the post/term that the link points at
                $p = Wpil_Post::getPostByLink($link->url);

                // if we haven't found a post with the link, and there's a record of a redirect
                if(!is_a($p, 'Wpil_Model_Post') && isset($redirected[$link->url])){
                    // try getting the post with the redirect link
                    $p = Wpil_Post::getPostByLink($redirected[$link->url]);
                }

                // if there is a post/term
                if(is_a($p, 'Wpil_Model_Post')){
                    // update it's link counts
                    self::statUpdate($p, true);
                }

                // store the post/term url so we don't update the same post multiple times
                $updated[$link->url] = true;
            }
        }

        // if any posts have been updated, return true. Otherwise, false.
        return (!empty($updated)) ? true : false;
    }

    /**
     * Get links from text
     *
     * @param $post The WPIL post object to check for links
     * @param bool $ignore_post Should we skip tracing internal URLs to their destination posts? The URL-to-post functionality is intense for some systems, and not all processes require the post data
     * @param string $content Pre-pulled post data so we can skip over most of the database query steps
     * @return array
     */
    public static function getContentLinks($post, $ignore_post = false, $content = '')
    {
        $data = [];
        $compare_content = '';

        if (Wpil_Settings::showAllLinks()) {
            $site_url = site_url();
            //get all links from page
            $ch = curl_init();

            $encoding = "gzip, deflate, br";
            $curl_version = curl_version();
            if(version_compare($curl_version['version'], '7.10.0') >= 0){
                $encoding = "";
            }

            $request_headers = array(
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
                'Accept-Encoding: ' . $encoding,
                'Accept-Language: en-US,en;q=0.9',
                'Cache-Control: max-age=0, no-cache',
                'Pragma: ',
                'Sec-Fetch-Dest: document',
                'Sec-Fetch-Mode: navigate',
                'Sec-Fetch-Site: none',
                'Sec-Fetch-User: ?0',
                'Host: ' . parse_url($site_url, PHP_URL_HOST),
                'Referer: ' . $site_url,
                'User-Agent: ' . WPIL_DATA_USER_AGENT,
                'Connection: close',
            );
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $request_headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_URL, $post->getLinks()->view);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            $content = curl_exec($ch);
            curl_close($ch);

            // remove any classed elements that the user doesn't want to count
            $content = Wpil_Suggestion::removeClassedElements($content);

            // remove any links inside HTML comments since those are only there for refrence
            $content = mb_ereg_replace('<!--[\s\S]*?-->', '', $content);

        }elseif(!empty($content)){ // if the content has been supplied
            // do some light formatting to make sure we're maintaining consistency

            $content = self::process_content($content, $post);

            // replace stylized double quotes with standard versions so we can search
            $content = str_replace(array('&#8221;'), '"', $content); // on a Thrive site, processing the content turned double quotes into stylized ones... 

            if(Wpil_Settings::getCommentLinks()){
                $content .= $post->getCommentContent();
            }

            // remove any classed elements that the user doesn't want to count
            $content = Wpil_Suggestion::removeClassedElements($content);
        }else{
            $content = $post->getContentWithoutSetting(false);

            if(Wpil_Settings::getContentFormattingLevel() > 0 && !empty(Wpil_Post::get_active_editors())){
                $compare_content = self::process_content($content, $post, true);

                // replace stylized double quotes with standard versions so we can search
                $compare_content = str_replace(array('&#8221;'), '"', $content); // on a Thrive site, processing the content turned double quotes into stylized ones... 

                if(Wpil_Settings::getCommentLinks()){
                    $compare_content .= $post->getCommentContent();
                }

                // remove any classed elements that the user doesn't want to count
                $compare_content = Wpil_Suggestion::removeClassedElements($compare_content);
            }

            $content = self::process_content($content, $post);

            // replace stylized double quotes with standard versions so we can search
            $content = str_replace(array('&#8221;'), '"', $content); // on a Thrive site, processing the content turned double quotes into stylized ones... 

            if(Wpil_Settings::getCommentLinks()){
                $content .= $post->getCommentContent();
            }

            // remove any classed elements that the user doesn't want to count
            $content = Wpil_Suggestion::removeClassedElements($content);
        }

        $data = self::pull_links_from_content($content, $ignore_post, $post);

        // if we have content to compare against
        if(!empty($compare_content) && !empty($data)){
            // pull links from the content that hasn't had the formatting applied to it
            $compare_links = self::pull_links_from_content($compare_content, $ignore_post, $post);

            if(!empty($compare_links)){
                foreach($data as &$dat){
                    $is_module = 1;
                    foreach($compare_links as $compare_link){
                        if(Wpil_Toolbox::compare_link_objects($dat, $compare_link)){
                            $is_module = 0;
                            break;
                        }
                    }

                    $dat->module_link = $is_module;

                    if($is_module){
                        $dat->link_context = 3;
                    }
                }
            }
        }

        // if we're not scanning the page directly for links
        if(empty(Wpil_Settings::showAllLinks())){
            // get any alternate links that the post may have
            $alternate_links = self::getAlternateLinks($post);

            if(!empty($alternate_links)){
                $data = array_merge($data, $alternate_links);
            }
        }

        return $data;
    }

    /**
     * Processes post content so we can get links from dynamic elements like shortcodes
     * @param string $content The post content to process
     * @param object $wpil_post The post that the content came from
     **/
    public static function process_content($content = '', $wpil_post = array(), $return_unformatted = false){
        global $post;

        // if the user is ignoring latest post blocks/widgets, remove the blocks if present
        if(Wpil_Settings::ignore_latest_post_links()){
            // remove default Gutenberg
            if(false !== strpos($content, '<!-- wp:latest-posts')){
                $content = mb_ereg_replace('<!-- wp:latest-posts(?:.*?)\/-->', '', $content);
            }

            // remove Gutenberg query/query-loop
            if(false !== strpos($content, '<!-- wp:query')){
                $content = mb_ereg_replace('<!-- wp:query(?:[^>]*?)-->(?:.*?)<!-- \/wp:query -->', '', $content);
            }

            // remove Yoast Related Posts
            if(false !== strpos($content, '<!-- wp:yoast-seo/related-links')){
                $content = mb_ereg_replace('<!-- wp:yoast-seo/related-links(?:.*?) -->(?:.*?)<!-- /wp:yoast-seo/related-links -->', '', $content);
            }

            // remove our related posts
            if(false !== strpos($content, '[link-whisper-related-posts')){
                $content = mb_ereg_replace('\[link-whisper-related-posts(?:.*?)\]', '', $content);
            }
        }

        // get the content formatting level
        $formatting_level = Wpil_Settings::getContentFormattingLevel();

        // if the user has disabled formatting or we're supposed to return the content early
        if(empty($formatting_level) || $return_unformatted){
            // return the content unchanged
            return $content;
        }

        // save a version of the content just in case the processing wipes it
        $old_content = $content;

        // remove any shortcodes that the user wants to ignore
        $content = Wpil_Suggestion::removeShortcodes($content);

        // get the currently active theme
        $theme = wp_get_theme();

        // figure out if we're already inside someone's output buffer
        $currently_buffering = (ob_get_level() > 2) ? true: false;

        // if we're not inside someone else's buffer
        if(!$currently_buffering){
            // start buffering the output to catch content echoes
            ob_start();
        }

        // if this is a post and the user has chosen to override the global $post
        $old_post = 'not-set';
        if($wpil_post->type === 'post' && Wpil_Settings::overrideGlobalPost()){
            // try getting the wp_post object for the current post that we're processing
            $new_post = get_post($wpil_post->id);
            // if we were successful
            if(!empty($new_post)){
                // save the current post for later
                $old_post = $post;
                // and override the global with this new post
                $post = $new_post;
            }
        }

        // remove any content formatting applied by LinkWhisper
        remove_filter('the_content', ['Wpil_Base', 'remove_link_whisper_attrs']);
        remove_filter('the_content', ['Wpil_Base', 'add_link_icons'], 100);

        if(!empty($theme) && $theme->exists() &&                                    // if we've gotten the theme without issue
            false === stripos($theme->name, 'Acabado') &&                           // and it isn't the acabado theme
            false === stripos($theme->parent_theme, 'Acabado') &&                   // and it's not an acabado child theme
            !empty($wpil_post) && 'elementor' !== $wpil_post->getContentEditor() && // and it's not an Elementor post
            $formatting_level === 2                                                 // and the formatting level is set to full
        ){
            // run the content through the_content
            $content = apply_filters('the_content', $content);
        }else{
            // try to processing shortcodes so we can get any links created with them
            $content = do_shortcode($content); // NOTE: if we ever have more than 3 processing options, do something about this. The current system has this defaulting to 1 because 0 & 2 are eliminated elsewhere, so 1 is not eplicitly called here
        }

        // reset the global $post if we overrode it
        if($old_post !== 'not-set'){
            $post = $old_post;
        }

        if(!$currently_buffering){
            // clear the output so no echoes mess up the json
            ob_end_clean(); // we could log this, but for the time being, we'll just clear it
        }

        // if there's no content after processing
        if(empty($content) && !empty($old_content)){
            // revert to the old content
            $content = $old_content;
        }

        return $content;
    }

    /**
     * Processes post content so we can get links from dynamic elements like shortcodes
     * @param string $content The post content to process
     * @param bool $ignore_post Should we not trace inbound internal links back to their target posts?
     * @param Wpil_ModelPost $wpil_post The post that the content came from
     **/
    public static function pull_links_from_content($content = '', $ignore_post = false, $wpil_post = array()){
        $data = [];

        $my_host = parse_url(str_replace('www.', '', Wpil_Link::filter_staging_to_live_domain(get_home_url())), PHP_URL_HOST);
        $post_link = Wpil_Link::filter_staging_to_live_domain($wpil_post->getLinks()->view);
        $location = 'content';
        $content = $wpil_post->getContentWithoutSetting(false);
        $content = self::process_content($content, $wpil_post);
        $include_image_src = false;

        // replace stylized double quotes with standard versions so we can search
        $content = str_replace(array('&#8221;'), '"', $content); // on a Thrive site, processing the content turned double quotes into stylized ones... 


        //get all links from content
        preg_match_all('`<a[^>]*?href=(?:\"|\')([^\"\']*?)(?:\"|\')[^>]*?>([\s\w\W]*?)<\/a>|&lt;a[^&]*?href=(?:\"|\'|&quot;)([^\"\']*?)(?:\"|\'|&quot;).*?&gt;([\s\w\W]*?)&lt;\/a&gt;|<img[^>]*?src=(?:\"|\')([^\"\']*?)(?:\"|\')[^>]*?>|<!-- wp:core-embed\/wordpress {"url":"([^"]*?)"[^}]*?"} -->|(?:>|&nbsp;|\s|\\n)((?:(?:http|ftp|https)\:\/\/)(?:[\w_-]+?(?:(?:\.[\w_-]+?)+?))(?:[\w.,@?^=%&:/~+#-]*?[\w@?^=%&/~+#-]))(?:<|&nbsp;|\s|\\n)|<iframe[^>]*?src=(?:\"|\')([^\"\']*?)(?:\"|\')[^>]*?><\/iframe>`i', $content, $matches);

        //make array with results
        foreach ($matches[0] as $key => $value) {
            // 0 => full match
            // 1 => normal anchor URL
            // 2 => normal anchor text
            // 3 => HTML encoded anchor URL
            // 4 => HTML encoded anchor text
            // 5 => Image src URL
            // 6 => WP Embed URL
            // 7 => In-content URL
            // 8 => iframe URL

            if(!empty($matches[1][$key])){
                $url = trim($matches[1][$key]);
                $anchor = (!empty($matches[2][$key])) ? trim(strip_tags($matches[2][$key])): false;

                if(empty($anchor) && strpos($matches[0][$key], 'title=') !== false){
                    preg_match('/<a\s+(?:[^>]*?\s+)?title=(["\'])(.*?)\1/i', $matches[0][$key], $title);
                    if(!empty($title[2])){
                        $anchor = $title[2];
                    }
                }

                if(empty($anchor) && !empty(trim($matches[2][$key])) && false !== strpos($matches[2][$key], '<img')){
                    $anchor = __('Anchor is image, no text found', 'wpil');
                }

                if(empty($anchor) || self::isJumpLink($url, $post_link)){
                    continue;
                }
            }elseif(!empty($matches[3][$key]) && !empty($matches[4][$key])){
                $url = trim($matches[3][$key]);
                $anchor = trim(strip_tags($matches[4][$key]));

                if(empty($anchor) || self::isJumpLink($url, $post_link)){
                    continue;
                }
            }elseif(!empty($matches[5][$key]) && $include_image_src){
                $url = trim($matches[5][$key]);
                $anchor = __('Link is for an image', 'wpil');
            }elseif(!empty($matches[6][$key])){
                $url = trim($matches[6][$key]);
                $anchor = __('Could not retrieve anchor text, link is embedded', 'wpil');
            }elseif(!empty($matches[7][$key])){
                $url = trim($matches[7][$key]); // if this is a link that is inserted in the content as a straight url // Mostly this means its an embed but as case history grows I'll come up with a better notice for the user
                $anchor = __('Could not retrieve anchor text, link is embedded', 'wpil');
            }elseif(!empty($matches[8][$key])){
                $url = trim($matches[8][$key]); // if this is a src URL for an frame, include it too. // And someday create the ability to delete iframes...
                $anchor = __('No anchor text, link is for an iframe', 'wpil');
            }else{
                continue;
            }

            $host = parse_url($url, PHP_URL_HOST);
            $p = null;

            // ignore any links that are being used as buttons
            if(false !== strpos($url, 'javascript:void(0)')){
                continue;
            }

            // if there is no host, but it's not a jump link
            if(empty($host)){
                // set the host as the current site's
                $host = $my_host;
                // get the site url
                $site_url = get_home_url();
                // explode the site url and the current link
                $s_url_pieces = array_filter(explode('/', $site_url));
                $url_pieces = array_filter(explode('/', $url));
                // get the last word in the site url and the first word in the relative url
                $front = end($s_url_pieces); // from the front half of the url
                $end = reset($url_pieces);  // from the back half of the url

                // see if the last part of the site url is the first part of the link
                if($front === $end){
                    // if it is, remove it
                    $url_end = substr($url, strlen($end) + strpos($url, $end));
                    // and create the full url
                    $url = trailingslashit($site_url) . ltrim($url_end, '/');
                }else{
                    // if there's no overlap between the home url and the relative one, merge them together
                    $url = (trailingslashit($site_url) . ltrim($url, '/'));
                }
            }

            // remove `www.` from the host and my host
            $host = str_replace('www.', '', $host);

            // if the link is internal and we're supposed to trace it back to it's target post
            if ($host == $my_host && !$ignore_post) {
                $p = Wpil_Post::getPostByLink($url);
            }

            $data[] = new Wpil_Model_Link([
                'url' => $url,
                'anchor' => $anchor,
                'host' => $host,
                'internal' => Wpil_Link::isInternal($url),
                'post' => $p,
                'added_by_plugin' => false,
                'location' => $location,
                'link_whisper_created' => 0,
                'is_autolink' => (false !== strpos($value, 'data-wpil-keyword-link="linked"') ? 1: 0)
            ]);
        }

        return $data;
    }

    public static function isJumpLink($link = '', $post_url = ''){
        $is_jump_link = false;

        // if the first char is a #
        if('#' === substr($link, 0, 1)){
            // this is a jump link
            $is_jump_link = true;
        }elseif(!empty($post_url) && strpos($link, $post_url) !== false){
            $part = explode('#', $link);
            if (strlen(str_replace($post_url, '', $part[0])) < 3) {
                // if the link is contained in the post view link, this is a jump link
                $is_jump_link = true;
            }
        }elseif(!empty($post_url) && strpos(strtok($link, '?#'), $post_url) !== false){
            // if the link is in the view link after cleaning it up, this is a jump link
            $is_jump_link = true;
        }else{
            $is_jump_link = false;
        }

        return $is_jump_link;
    }

    /**
     * Pulls in links from alternate sources like related post plugins or page builders with complex data structures
     **/
    public static function getAlternateLinks($post){
        $data = array();
        $get_related = Wpil_Settings::get_related_post_links();

        if($get_related){
            // if YARPP is active and this is a post
            if(defined('YARPP_VERSION') && $post->type === 'post'){
                // check for the yarpp global
                global $yarpp;

                if(!empty($yarpp) && method_exists($yarpp, 'get_related')){
                    $posts = $yarpp->get_related($post->id);

                    if(!empty($posts)){
                        $host = parse_url(Wpil_Link::filter_staging_to_live_domain(get_home_url()), PHP_URL_HOST);
                        foreach($posts as $p){
                            if(!isset($p->ID)){
                                continue;
                            }

                            $url = get_permalink($p);
                            $data[] = new Wpil_Model_Link([
                                'url' => $url,
                                'anchor' => $p->post_title,
                                'host' => str_replace('www.', '', $host),
                                'internal' => true,
                                'post' => new Wpil_Model_Post($p->ID),
                                'added_by_plugin' => false,
                                'location' => 'content',
                                'module_link' => 1,
                                'link_context' => 2,
                            ]);
                        }
                    }
                }
            }
        }


        return $data;
    }

    /**
     * Get all post outbound links
     *
     * @param $post
     * @return array
     */
    public static function getOutboundLinks($post, $ignore_post = false)
    {
        //create initial array
        $data = [
            'internal' => [],
            'external' => []
        ];

        //add links to array from post content
        foreach (self::getContentLinks($post, $ignore_post) as $link) {
            if ($link->internal) {
                $data['internal'][] = $link;
            } else {
                $data['external'][] = $link;
            }
        }

        return $data;
    }

    /**
     * Set up and display the click details page
     */
    public static function setup_click_details_page()
    {
        //prepare variables for template
        $return_url = !empty($_GET['ret_url']) ? base64_decode(urldecode($_GET['ret_url'])) : admin_url('admin.php?page=link_whisper&type=clicks&direct_return=1');

        if(isset($_GET['post_type']) && ($_GET['post_type'] === 'url' || $_GET['post_type'] === 'user_ip') && isset($_GET['post_id']) && !empty($_GET['post_id'])){
            if($_GET['post_type'] === 'url'){
                $id = esc_url_raw($_GET['post_id']);
            }else{
                $id = filter_var($_GET['post_id'], FILTER_VALIDATE_IP);
            }

            $type = $_GET['post_type'];
        }else{
            $post = Wpil_Base::getPost();
            $id = $post->id;
            $type = $post->type;
        }

        $page = isset($_REQUEST['page']) ? $_REQUEST['page'] : 1;
        $sub_title = ($type === 'url' || $type === 'user_ip') ? '<a href="' . esc_url($id) . '">' . esc_html($id) . '</a>': '<a href="' . esc_url($post->getViewLink()) . '">' . esc_html($post->getTitle()) . '</a>';
        $start_date = strtotime('30 days ago');
        if(isset($_GET['start_date']) && !empty($_GET['start_date'])){
            $start_string = preg_replace('/([^0-9-TZ:\/])/', '', $_GET['start_date']);

            if(!empty(DateTime::createFromFormat('Y-m-d', $start_string))){
                $date = new DateTime($start_string);
                $start_date = $date->getTimestamp();
            }
        }

        $end_date = strtotime('now');
        if(isset($_GET['end_date']) && !empty($_GET['end_date'])){
            $end_string = preg_replace('/([^0-9-TZ:\/])/', '', $_GET['end_date']);

            if(!empty(DateTime::createFromFormat('Y-m-d', $end_string))){
                $date = new DateTime($end_string);
                $end_date = $date->getTimestamp();
            }
        }

        $date_format = Wpil_Toolbox::convert_date_format_from_js();

        $click_data = Wpil_ClickTracker::get_detailed_click_data($id, $type, array('start' => $start_date, 'end' => $end_date));
        $click_chart_data = array();

        foreach($click_data as $data){
            $time = date($date_format, strtotime($data->click_date));

            if(!isset($click_chart_data[$time])){
                $click_chart_data[$time] = 1;
            }else{
                $click_chart_data[$time] += 1;
            }
        }

        $total_clicks = count($click_data);

        wp_register_script('wpil_chart_js', WP_INTERNAL_LINKING_PLUGIN_URL . 'js/jquery.jqChart.min.js', array('jquery'), false, false);
        wp_enqueue_script('wpil_chart_js');
        wp_register_style('wpil_chart_css', WP_INTERNAL_LINKING_PLUGIN_URL . 'css/jquery.jqChart.css');
        wp_enqueue_style('wpil_chart_css');

        include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/click_details_page.php';
    }

    /**
     * Show post links count update page
     */
    public static function postLinksCountUpdate()
    {
        //prepare variables
        $post = Wpil_Base::getPost();

        $start = microtime(true);

        $u = admin_url("admin.php?page=link_whisper");

        $prev_t = $post->getSyncReportTime();

        $prev_count = [
            'inbound_internal' => (int)$post->getInboundInternalLinks(true),
            'outbound_internal' => (int)$post->getOutboundInternalLinks(true),
            'outbound_external' => (int)$post->getOutboundExternalLinks(true)
        ];

        if(WPIL_STATUS_LINK_TABLE_EXISTS){
            self::update_post_in_link_table($post);
        }
        self::statUpdate($post);

        wp_cache_delete($post->id, (isset($post->type) && $post->type === 'post') ? 'post_meta':'term_meta');

        $time = microtime(true) - $start;
        $new_time = $post->getSyncReportTime();

        $count = [
            'inbound_internal' => (int)$post->getInboundInternalLinks(true),
            'outbound_internal' => (int)$post->getOutboundInternalLinks(true),
            'outbound_external' => (int)$post->getOutboundExternalLinks(true)
        ];

        $links_data = [
            'inbound_internal' => $post->getInboundInternalLinks(),
            'outbound_internal' => $post->getOutboundInternalLinks(),
            'outbound_external' => $post->getOutboundExternalLinks()
        ];

        include dirname(__DIR__).'/../templates/post_links_count_update.php';
    }

/**
     * Get report data
     *
     * @param int $start
     * @param string $orderby
     * @param string $order
     * @param string $search
     * @param int $limit
     * @return array
     */
    public static function getData($start = 0, $orderby = '', $order = 'DESC', $search='', $limit=20, $orphaned = false, $link_density_report = false, $anchor_length_report = false)
    {
        global $wpdb;
        $link_table = $wpdb->prefix . "wpil_report_links";

        //check if it need to show categories in the list
        $options = get_user_meta(get_current_user_id(), 'report_options', true);
        $show_categories = (!empty($options['show_categories']) && $options['show_categories'] == 'off') ? false : true;
        $show_traffic = (isset($options['show_traffic'])) ? ( ($options['show_traffic'] == 'off') ? false : true) : false;
        $hide_ignored = Wpil_Settings::hideIgnoredPosts();
        $hide_noindex = (isset($options['hide_noindex'])) ? ( ($options['hide_noindex'] == 'off') ? false : true) : false;
        $process_terms = !empty(Wpil_Settings::getTermTypes());

        // get if GSC has been authenticated
        $authenticated = Wpil_Settings::HasGSCCredentials();

        // sanitize the inputs
        $order = (!empty($order)) ? ((strtolower($order) === 'desc') ? 'DESC': 'ASC'): ""; 
        $limit = intval($limit);

        //calculate offset
        $offset = $start > 0 ? (((int)$start - 1) * $limit) : 0;

        $post_type_list = Wpil_Settings::getPostTypes();
        $post_types = "'" . implode("','", $post_type_list) . "'";

        //create search query requests
        $term_search = '';
        $title_search = '';
        $term_title_search = '';
        if (!empty($search)) {
            $is_internal = Wpil_Link::isInternal($search);
            $search_post = Wpil_Post::getPostByLink($search);
            if ($is_internal && $search_post && (empty($post_type_list) || $search_post->type == 'post' && in_array($search_post->getRealType(), $post_type_list)) && ($search_post->type != 'term' || ($show_categories && $process_terms))) {
                if ($search_post->type == 'term') {
                    $term_search = " AND t.term_id = {$search_post->id} ";
                    $search = " AND 2 > 3 ";
                } else {
                    $term_search = " AND 2 > 3 ";
                    $search = " AND p.ID = {$search_post->id} ";
                }
            } else {
                $search = $wpdb->prepare("%s", Wpil_Toolbox::esc_like($search));
                $term_title_search = ", IF(CONVERT(t.name USING utf8mb4) COLLATE utf8mb4_unicode_ci LIKE {$search}, 1, 0) as title_search ";
                $title_search = ", IF(CONVERT(p.post_title USING utf8mb4) COLLATE utf8mb4_unicode_ci LIKE {$search}, 1, 0) as title_search ";
                $term_search = " AND (CONVERT(t.name USING utf8mb4) COLLATE utf8mb4_unicode_ci LIKE {$search} OR CONVERT(tt.description USING utf8mb4) COLLATE utf8mb4_unicode_ci LIKE {$search}) ";
                $search = " AND (CONVERT(p.post_title USING utf8mb4) COLLATE utf8mb4_unicode_ci LIKE {$search} OR CONVERT(p.post_content USING utf8mb4) COLLATE utf8mb4_unicode_ci LIKE {$search}) ";
            }
        }

        //filters
        $post_ids = Wpil_Filter::getLinksLocationIDs();
        if (Wpil_Filter::linksCategory()) {
            $process_terms = false;
            if (!empty($post_ids)) {
                $post_ids = array_intersect($post_ids, Wpil_Filter::getLinksCatgeoryIDs());
            } else {
                $post_ids = Wpil_Filter::getLinksCatgeoryIDs();
                // if there are no posts in this category
                if(empty($post_ids)){
                    // save everyone's time by returning nothing now
                    return array( 'data' => array() , 'total_items' => 0);
                }
            }
        }

        if (!empty($post_ids)) {
            $search .= " AND p.ID IN (" . implode(', ', $post_ids) . ") ";
        }

        if ($post_type = Wpil_Filter::linksPostType()) {
            $term_search .= " AND tt.taxonomy = '$post_type' ";
            $search .= " AND p.post_type = '$post_type' ";
        }

        //sorting
        if (empty($orderby) && !empty($title_search)) {
            $orderby = 'title_search';
            $order = 'DESC';
        } elseif (empty($orderby) || $orderby == 'date') {
            $orderby = 'post_date';
        }else{
            // only allow sorting by specific keys
            switch($orderby){
                case 'post_date':
                case 'post_title':
                case 'post_type':
                case 'title_search':
                case 'organic_traffic':
                    // no worries mon
                    break;
                case 'wpil_links_inbound_internal_count':
                    $post_link_table_query = "
                        SELECT target_id AS id, COUNT(*) AS meta_value
                        FROM {$link_table}
                        WHERE target_type = 'post' AND has_links > 0
                        GROUP BY target_id";

                    $term_link_table_query = "
                        SELECT target_id AS id, COUNT(*) AS meta_value
                        FROM {$link_table}
                        WHERE target_type = 'term' AND has_links > 0
                        GROUP BY target_id";
                    break;
                case 'wpil_links_outbound_internal_count':
                    $post_link_table_query = "
                        SELECT post_id AS id, COUNT(*) AS meta_value
                        FROM {$link_table}
                        WHERE post_type = 'post' AND internal = 1
                        GROUP BY post_id";

                    $term_link_table_query = "
                        SELECT post_id AS id, COUNT(*) AS meta_value
                        FROM {$link_table}
                        WHERE post_type = 'term' AND internal = 1
                        GROUP BY post_id";
                    break;
                case 'wpil_links_outbound_external_count':
                    $post_link_table_query = "
                        SELECT post_id AS id, COUNT(*) AS meta_value
                        FROM {$link_table}
                        WHERE post_type = 'post' AND internal = 0 AND has_links > 0
                        GROUP BY post_id";

                    $term_link_table_query = "
                        SELECT post_id AS id, COUNT(*) AS meta_value
                        FROM {$link_table}
                        WHERE post_type = 'term' AND internal = 0 AND has_links > 0
                        GROUP BY post_id";
                    break;
                default:
                    $orderby = 'post_date';
                    break;
            }
        }

        //get data
        $statuses_query = Wpil_Query::postStatuses('p');
        $report_post_ids = Wpil_Query::reportPostIds($orphaned);
        $report_term_ids = Wpil_Query::reportTermIds($orphaned, $hide_noindex);

        $post_filter_query = "";
        $link_filters = Wpil_Filter::filterLinkCount();
        if($link_filters && !$link_density_report){
            switch($link_filters['link_type']){
                case 'inbound-internal':
                    $post_filter_group_query = "select a.ID AS 'ID' from {$wpdb->posts} a left join {$link_table} b on a.ID = b.target_id and b.target_type = 'post' and b.has_links > 0 where 1";
                    $term_filter_group_query = "select a.term_id as 'ID' from {$wpdb->terms} a left join {$link_table} b on a.term_id = b.target_id and b.target_type = 'term' and b.has_links > 0 where 1";
                    $post_group_filter_by = " group by a.ID having count(b.target_id) >= {$link_filters['link_min_count']}";
                    $term_group_filter_by = " group by a.term_id having count(b.target_id) >= {$link_filters['link_min_count']}";
                    break;
                case 'outbound-internal':
                    $post_filter_group_query = "select a.ID AS 'ID' from {$wpdb->posts} a left join {$link_table} b on a.ID = b.post_id and b.post_type = 'post' and b.internal = 1 where 1";
                    $term_filter_group_query = "select a.term_id as 'ID' from {$wpdb->terms} a left join {$link_table} b on a.term_id = b.post_id and b.post_type = 'term' and b.internal = 1 where 1";
                    $post_group_filter_by = " group by a.ID having count(b.post_id) >= {$link_filters['link_min_count']}";
                    $term_group_filter_by = " group by a.term_id having count(b.post_id) >= {$link_filters['link_min_count']}";
                    break;
                case 'outbound-external':
                default:
                    $post_filter_group_query = "select a.ID AS 'ID' from {$wpdb->posts} a left join {$link_table} b on a.ID = b.post_id and b.post_type = 'post' and internal = 0 and b.has_links > 0 where 1";
                    $term_filter_group_query = "select a.term_id as 'ID' from {$wpdb->terms} a left join {$link_table} b on a.term_id = b.post_id and b.post_type = 'term' and internal = 0 and b.has_links > 0 where 1";
                    $post_group_filter_by = " group by a.ID having count(b.post_id) >= {$link_filters['link_min_count']}";
                    $term_group_filter_by = " group by a.term_id having count(b.post_id) >= {$link_filters['link_min_count']}";
                    break;
            }

            $post_filter_query = " AND p.ID IN ({$post_filter_group_query}";
            $post_group_filter_by .= ($link_filters['link_max_count'] !== null) ? " and count(b.post_id) <= {$link_filters['link_max_count']})": ')';
            $post_filter_query .= $post_group_filter_by;

            $term_group_filter_by .= ($link_filters['link_max_count'] !== null) ? " AND count(b.post_id) <= {$link_filters['link_max_count']}": '';
            $term_filter_group_query .= $term_group_filter_by;

            if(!empty($report_term_ids)){
                $report_term_ids = "term_id IN ($report_term_ids) AND term_id IN ({$term_filter_group_query})";
                $report_term_ids = $wpdb->get_col("SELECT `term_id` FROM $wpdb->terms WHERE $report_term_ids");
                $report_term_ids = implode(',', $report_term_ids);
            }
        }

        $collation = "";

        // if we're processing terms in the report too
        $processing_terms = ($show_categories && $process_terms && !empty($report_term_ids)) ? true: false;
        // we need to make sure the collation matches between the post & term tables
        if($processing_terms){
            // we also need to know what collation we're shooting for
            $table_data = $wpdb->get_row("SELECT table_name, table_collation, SUBSTRING_INDEX(table_collation, '_', 1) AS character_set FROM information_schema.tables WHERE table_schema = '{$wpdb->dbname}' AND table_name = '{$wpdb->posts}'");
            // if we have results for the posts table
            if(!empty($table_data) && isset($table_data->table_collation)){
                // go with it's collation
                $collation = "COLLATE " . $table_data->table_collation;
                // set the charset for the benefit of the terms check
                $post_charset = $table_data->character_set;
            }else{
                // if we have no data, guess that using utf8mb4_unicode_ci will be alright
                $collation = "COLLATE utf8mb4_unicode_ci";
                // set the charset for the benefit of the terms check
                $post_charset = 'utf8mb4';
            }
        }

        // if we're showing the link density report
        if($link_density_report){
            // override the filter settings with the density filters
            $post_inbound_group_query = "select a.ID AS 'ID' from {$wpdb->posts} a left join {$link_table} b on a.ID = b.target_id and b.target_type = 'post' and b.has_links > 0 where 1 group by a.ID having count(b.post_id) <= 0";
            $term_inbound_group_query = "select a.term_id as 'ID' from {$wpdb->terms} a left join {$link_table} b on a.term_id = b.target_id and b.target_type = 'term' and b.has_links > 0 where 1 group by a.term_id having count(b.post_id) <= 0";

            $post_outbound_group_query = "select a.ID AS 'ID' from {$wpdb->posts} a left join {$link_table} b on a.ID = b.post_id and b.post_type = 'post' and b.internal = 1 where 1 group by a.ID having count(b.post_id) < 3";
            $term_outbound_group_query = "select a.term_id as 'ID' from {$wpdb->terms} a left join {$link_table} b on a.term_id = b.post_id and b.post_type = 'term' and b.internal = 1 where 1 group by a.term_id having count(b.post_id) < 3";

            $post_filter_query = " AND (p.ID IN ({$post_inbound_group_query}) OR p.ID IN ({$post_outbound_group_query}))";
            if(!empty($report_term_ids)){
                $report_term_ids = "term_id IN ($report_term_ids) AND (term_id IN ({$term_inbound_group_query}) OR term_id IN ({$term_outbound_group_query}))";
                $report_term_ids = $wpdb->get_col("SELECT `term_id` FROM {$wpdb->terms} WHERE $report_term_ids");
                $report_term_ids = implode(',', $report_term_ids);
            }
        }

        // if we're showing the anchor length report
        if($anchor_length_report){
            // TODO: think about filterign the results to only show posts and links hat are outside the anchor word guidelines
        }

        // hide ignored
        $ignored_posts = Wpil_Query::get_all_report_ignored_post_ids('p', array('orphaned' => $orphaned, 'hide_noindex' => $hide_noindex));
        $ignored_terms = '';
        if($hide_ignored && $show_categories){
            $ignored_terms = Wpil_Query::ignoredTermIds();
        }

        if ($orderby == 'post_date' || $orderby == 'post_title' || $orderby == 'post_type' || $orderby == 'title_search') {
            //create query for order by title or date
            $query = "SELECT DISTINCT p.ID, p.post_title {$collation} AS 'post_title', p.post_type {$collation} AS 'post_type', p.post_date as `post_date`, 'post' as `type` $title_search 
                        FROM {$wpdb->posts} p 
                            WHERE 1 = 1 $report_post_ids $statuses_query $ignored_posts AND p.post_type IN ($post_types) $search {$post_filter_query} AND p.ID IN (select distinct `post_id` from {$link_table} where `post_type` = 'post')";

            if ($processing_terms) {
                $taxonomies = Wpil_Settings::getTermTypes();
                $query .= " UNION
                            SELECT tt.term_id as `ID`, CONVERT(t.name USING {$post_charset}) {$collation} as `post_title`, CONVERT(tt.taxonomy USING {$post_charset}) {$collation} as `post_type`, '1970-01-01 00:00:00' as `post_date`, 'term' as `type` $term_title_search  
                            FROM {$wpdb->term_taxonomy} tt INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id 
                            WHERE t.term_id in ($report_term_ids) $ignored_terms AND tt.taxonomy IN ('" . implode("', '", $taxonomies) . "') $term_search ";
            }

            $query .= " ORDER BY $orderby $order 
                        LIMIT {$limit} OFFSET {$offset}";

        } elseif($orderby === 'organic_traffic') {
            $target_keyword_table = $wpdb->prefix . 'wpil_target_keyword_data';

            $query = "SELECT DISTINCT `ID`, a.post_type {$collation} AS 'post_type', a.post_title {$collation} AS 'post_title', `post_date`, SUM(`clicks`) as county, 'post' as `type` FROM 
            (SELECT p.ID, 'post' AS post_type, p.post_title, p.post_date as `post_date` FROM {$wpdb->posts} p WHERE 1 = 1 $report_post_ids $statuses_query $ignored_posts AND p.post_type IN ($post_types) $search AND p.ID IN (select distinct `post_id` from {$link_table} where `post_type` = 'post')  {$post_filter_query}";

            if ($processing_terms) {
                $taxonomies = Wpil_Settings::getTermTypes();
                $query .= " UNION
                SELECT t.term_id as `ID`, 'term' as `post_type`, CONVERT(t.name USING {$post_charset}) {$collation} as `post_title`, '1970-01-01 00:00:00' as `post_date`, 'term' as `type
                FROM {$wpdb->termmeta} m INNER JOIN {$wpdb->terms} t ON m.term_id = t.term_id INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id 
                WHERE t.term_id in ($report_term_ids) $ignored_terms AND tt.taxonomy IN ('" . implode("', '", $taxonomies) . "') $term_search";
            }
    
            $query .= ") a LEFT JOIN {$target_keyword_table} k ON k.post_id = a.ID GROUP BY ID ORDER BY `county` {$order} LIMIT {$limit} OFFSET {$offset}";

        } else {
            //create query for other orders
            $query = "(SELECT DISTINCT p.ID, p.post_title {$collation} AS 'post_title', p.post_type {$collation} AS 'post_type', p.post_date as `post_date`, IFNULL(post_counts.meta_value, 0) AS 'meta_value', 'post' as `type` {$title_search}
                        FROM {$wpdb->posts} p LEFT JOIN ({$post_link_table_query}) AS post_counts ON p.ID = post_counts.id
                        WHERE 1 = 1 $report_post_ids $statuses_query $ignored_posts AND p.post_type IN ($post_types) {$post_filter_query} $search
                        GROUP BY p.ID";
            if ($processing_terms) {
                $taxonomies = Wpil_Settings::getTermTypes();
                $query .= ") UNION (
                    SELECT t.term_id as `ID`, CONVERT(t.name USING {$post_charset}) {$collation} as `post_title`, CONVERT(tt.taxonomy USING {$post_charset}) {$collation} as `post_type`, '1970-01-01 00:00:00' as `post_date`, IFNULL(term_counts.meta_value, 0) AS 'meta_value', 'term' as `type` {$term_title_search}
                        FROM {$wpdb->terms} t INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                            LEFT JOIN ({$term_link_table_query}) AS term_counts ON t.term_id = term_counts.id
                    WHERE t.term_id in ($report_term_ids) $ignored_terms AND tt.taxonomy IN ('" . implode("', '", $taxonomies) . "') $term_search 
                    GROUP BY t.term_id";
                }

                $query .= ") 
                        ORDER BY meta_value $order 
                        LIMIT {$limit} OFFSET {$offset}";
        }

        //calculate total count
        $total_items = self::getTotalItems($query);

        $result = $wpdb->get_results($query);

        //prepare report data
        $data = [];
        foreach ($result as $key => $post) {
            if ($post->type == 'term') {
                $p = new Wpil_Model_Post($post->ID, 'term');
                $inbound = admin_url("admin.php?term_id={$post->ID}&page=link_whisper&type=inbound_suggestions_page&ret_url=" . base64_encode($_SERVER['REQUEST_URI']));
            } else {
                $p = new Wpil_Model_Post($post->ID);
                $inbound = admin_url("admin.php?post_id={$post->ID}&page=link_whisper&type=inbound_suggestions_page&ret_url=" . base64_encode($_SERVER['REQUEST_URI']));
            }

            $item = [
                'post' => $p,
                'links_inbound_page_url' => $inbound,
                'date' => $post->type == 'post' ? date(str_replace('F', 'M', get_option('date_format', 'F d, Y')), strtotime($post->post_date)) : 'not set'
            ];

            //get meta data
            foreach (self::$meta_keys as $meta_key) {
                $item[$meta_key] = $p->getLinksData($meta_key, true);
            }

            // if we're we're supposed to show the click traffic and GSC has been authenticated
            if($show_traffic && $authenticated){
                $keywords = Wpil_TargetKeyword::get_post_keywords_by_type($item['post']->id, $item['post']->type, 'gsc-keyword', false);
                $clicks = 0;
                $position = 0;
                foreach($keywords as $keyword){
                    $clicks += $keyword->clicks;
                    $position += floatval($keyword->position);
                }

                if($position > 0){
                    $position = round($position/count($keywords), 2);
                }

                $item['organic_traffic'] = $clicks;
                $item['position'] = $position;

            }

            $data[$key] = $item;
        }

        return array( 'data' => $data , 'total_items' => $total_items);
    }

    /**
     * Get total items depend on filters
     *
     * @param $query
     * @return string|null
     */
    public static function getTotalItems($query)
    {
        global $wpdb;

        $query = str_replace('UNION', 'UNION ALL', $query);
        $limit = strpos($query, ' ORDER');
        $query = "SELECT count(*) FROM (" . substr($query, 0, $limit) . ") as t1";
        return $wpdb->get_var($query);
    }

    /**
     * Gets all the AI detected suggestions that have been discovered for the specific post
     * @param Wpil_ModelPost $wpil_post
     **/
    public static function get_ai_detected_suggestions($post = array(), $inbound = false, $return_count = false, $group = true){
        global $wpdb;
        $suggestion_table = $wpdb->prefix . "wpil_ai_suggested_anchors";
        $sentence_table = $wpdb->prefix . "wpil_ai_processed_sentences";

        //TODO: MAKE THIS WORK WITH THIS: Wpil_Settings::getOutboundSuggestionPostIds();

        if(empty($post) || !is_a($post, 'Wpil_Model_Post')){
            return ($return_count) ? 0: array();
        }

        $ignore_ids = Wpil_Settings::getAllIgnoredPosts();
        $ignore_posts = array();
        $ignore_terms = array();
        if(!empty($ignore_ids)){
            foreach($ignore_ids as $id){
                if(!is_string($id) || false === strpos($id, '_')){
                    continue;
                }
                $bits = explode('_', $id);
                if($bits[0] === 'post'){
                    $ignore_posts[] = $bits[1];
                }elseif($bits[0] === 'term'){
                    $ignore_terms[] = $bits[1];
                }
            }
        }

        $post_types = Wpil_Query::postTypes('');
        $status = Wpil_Query::postStatuses('');
        $age_string = Wpil_Query::getPostDateQueryLimit('');

        $taxonomies = Wpil_Query::taxonomyTypes('tt');

        $ignore_posts = (!empty($ignore_posts)) ? " AND ID NOT IN (".implode(',', $ignore_posts).")": '';
        $ignore_terms = (!empty($ignore_terms)) ? " AND t.term_id NOT IN (".implode(',', $ignore_terms).")": '';

        $post_query = "(select ID from {$wpdb->posts} where 1=1 {$post_types} {$status} {$age_string} {$ignore_posts})";
        $term_query = "(select tt.term_taxonomy_id from {$wpdb->terms} t left join {$wpdb->term_taxonomy} tt on t.term_id = tt.term_id where 1=1 {$taxonomies} {$ignore_terms})";

        // todo: setup caching so we don't have to hit the database multiple times!
        // if we're getting the inbound suggestion count
        if($inbound){
            $query = $wpdb->prepare(
                " FROM {$suggestion_table} a LEFT JOIN {$sentence_table} b 
                    ON a.post_id = b.post_id 
                    AND a.data_type = b.data_type 
                    AND a.sentence_id = b.sentence_id 
                WHERE a.ignore_suggestion < 1 
                AND b.has_link < 1
                AND a.target_id = %d
                AND a.target_type = %s
                AND ((a.data_type = 1 AND a.post_id IN {$post_query}) OR (a.data_type = 0 AND a.post_id IN {$term_query}))", $post->id, $post->type);
                if($group){
                    $query .= " GROUP BY a.post_id, a.post_type";
                }
        }else{
            $query = $wpdb->prepare(
                " FROM {$suggestion_table} a LEFT JOIN {$sentence_table} b 
                    ON a.post_id = b.post_id 
                    AND a.data_type = b.data_type 
                    AND a.sentence_id = b.sentence_id 
                WHERE a.ignore_suggestion < 1 
                AND b.has_link < 1
                AND a.post_id = %d
                AND a.post_type = %s
                AND ((a.target_data_type = 1 AND a.target_id IN {$post_query}) OR (a.target_data_type = 0 AND a.target_id IN {$term_query}))", $post->id, $post->type);

            if($group){
                $query .= " GROUP BY a.target_id, a.target_type";
            }
        }

        $suggestions = ($return_count) ?  
            $wpdb->get_results("SELECT COUNT(*) as county" . $query):  
            $wpdb->get_results("SELECT *" . $query);

        if($return_count && !empty($suggestions)){
            $count = 0;
            foreach($suggestions as $suggestion){
                if($group){
                    $count++;
                }else{
                    $count += $suggestion->county;
                }
            }
            $suggestions = $count;
        }

        return $suggestions;
    }

    /**
     * Show screen options form
     *
     * @param $status
     * @param $args
     * @return false|string
     */
    public static function showScreenOptions($status, $args)
    {
        //Skip if it is not our screen options
        if ($args->base != Wpil_Base::$report_menu) {
            return $status;
        }

        if (!empty($args->get_option('report_options'))) {
            $options = get_user_meta(get_current_user_id(), 'report_options', true);

            // Check if the screen options have been saved. If so, use the saved value. Otherwise, use the default values.
            if ( $options ) {
                $show_categories = !empty($options['show_categories']) && $options['show_categories'] != 'off';
                $show_type = !empty($options['show_type']) && $options['show_type'] != 'off';
                $show_date = !empty($options['show_date']) && $options['show_date'] != 'off';
                $per_page = !empty($options['per_page']) ? $options['per_page'] : 20 ;
                $show_traffic = !empty($options['show_traffic']) && $options['show_traffic'] != 'off';
                $hide_ignore = !empty($options['hide_ignore']) && $options['hide_ignore'] != 'off';
                $hide_noindex = !empty($options['hide_noindex']) && $options['hide_noindex'] != 'off';
                $show_click_traffic = !empty($options['show_click_traffic']) && $options['show_click_traffic'] != 'off';
                $show_broken_link_type = !empty($options['show_broken_link_type']) && $options['show_broken_link_type'] != 'off';
                $show_broken_link_discovered = !empty($options['show_broken_link_discovered']) && $options['show_broken_link_discovered'] != 'off';
//                $enable_tours = isset($options['enable_tours']) ? ($options['enable_tours'] != 'off') : Wpil_Settings::get_tours_enabled();
            } else {
                $show_categories = false;
                $show_date = true;
                $show_type = false;
                $per_page = 20;
                $show_traffic = false;
                $hide_ignore = false;
                $hide_noindex = false;
                $show_click_traffic = false;
                $show_broken_link_type = false;
                $show_broken_link_discovered = false;
//                $enable_tours = Wpil_Settings::get_tours_enabled();
            }

            //get apply button
            $button = get_submit_button( __( 'Apply', 'wp-screen-options-framework' ), 'primary large', 'screen-options-apply', false );

            //show HTML form
            ob_start();
            $report = (isset($_GET['type']) && in_array($_GET['type'], array('links', 'domains', 'error', 'clicks', 'click_details_page'), true)) ? $_GET['type']: '';
            $hide = 'style="display:none"';
            include WP_INTERNAL_LINKING_PLUGIN_DIR . 'templates/report_options.php';
            return ob_get_clean();
        }

        return '';
    }

    /**
     * Save screen options
     *
     * @param $status
     * @param $option
     * @param $value
     * @return array|mixed
     */
    public static function saveOptions( $status, $option, $value ) {
        if ($option == 'report_options') {
            $value = [];
            if (isset( $_POST['report_options'] ) && is_array( $_POST['report_options'] )) {
                if (!isset($_POST['report_options']['show_categories'])) {
                    $_POST['report_options']['show_categories'] = 'off';
                }

                $cleaned_options = array();
                foreach($_POST['report_options'] as $option_key => $option_value){
                    $cleaned_options[sanitize_text_field($option_key)] = sanitize_text_field($option_value);
                }

                $value = $cleaned_options;

                if (!isset($_POST['report_options']['show_type'])) {
                    $_POST['report_options']['show_type'] = 'off';
                }
                if (!isset($_POST['report_options']['show_date'])) {
                    $_POST['report_options']['show_date'] = 'off';
                }
                if (!isset($_POST['report_options']['show_traffic'])) {
                    $_POST['report_options']['show_traffic'] = 'off';
                }
                if (!isset($_POST['report_options']['hide_ignore'])) {
                    $_POST['report_options']['hide_ignore'] = 'off';
                }
                if (!isset($_POST['report_options']['hide_noindex'])) {
                    $_POST['report_options']['hide_noindex'] = 'off';
                }
                if (!isset($_POST['report_options']['show_link_attrs'])) {
                    $_POST['report_options']['show_link_attrs'] = 'off';
                }
                if (!isset($_POST['report_options']['enable_tours'])) {
                    $_POST['report_options']['enable_tours'] = 'off';
                }
                $value = $_POST['report_options'];

                // Also update the global setting for tours
                if (isset($value['enable_tours'])) {
                    update_option('wpil_enable_tours', ($value['enable_tours'] != 'off') ? '1' : '0');
                }
            }

            return $value;
        }

        return $status;
    }

    public static function ajax_assemble_link_report_dropdown_data(){

        Wpil_Base::verify_nonce('wpil-collapsible-nonce');

        if(!isset($_POST['dropdown_type']) || !isset($_POST['post_id']) || !isset($_POST['post_type']) || !isset($_POST['item_count'])){
            wp_send_json(array('error' => array('title' => __('Data Missing', 'wpil'), 'text' => __('Some of the data required to load the rest of the dropdown is missing. Please reload the page and try opening the dropdown again.', 'wpil'))));
        }

        $rep = '';
        $post_id = (int)$_POST['post_id'];
        $post_type = ($_POST['post_type'] === 'post') ? 'post': 'term';
        $current = (int) $_POST['item_count'];

        $post = new Wpil_Model_Post($post_id, $post_type);

        switch ($_POST['dropdown_type']) {
            case 'wpil_links_inbound_internal_count':
                $links_data = $post->getInboundInternalLinks();
                $count = 0;
                foreach ($links_data as $link) {
                    if (!Wpil_Filter::linksLocation() || $link->location == Wpil_Filter::linksLocation()) {
                        $count++;
                        if($count <= $current){
                            continue;
                        }
                        if (!empty($link->post)) {
                            $rep .= '<li>
                                        <input type="checkbox" class="wpil_link_select" data-post_id="'.$link->post->id.'" data-post_type="'.$link->post->type.'" data-anchor="'.base64_encode($link->anchor).'" data-url="'.base64_encode($link->url).'">
                                        <div>
                                            <div style="margin: 3px 0;"><b>Origin Post Title:</b> ' . esc_html($link->post->getTitle()) . '</div>
                                            <div style="margin: 3px 0;"><b>Anchor Text:</b> <a href="' . esc_url(add_query_arg(['wpil_admin_frontend' => '1', 'wpil_admin_frontend_data' => $link->create_scroll_link_data()], $link->post->getLinks()->view)) . '" target="_blank">' . esc_html($link->anchor) . ' <span class="dashicons dashicons-external" style="position: relative;top: 3px;"></span></a></div>
                                            <div style="margin: 3px 0;"><b>Content Relatedness:</b> ' . esc_html($link->get_ai_relation_percent()) . '</div>';
                            $rep .= ($get_all_links) ? '<div style="margin: 3px 0;"><b>Link Location:</b> ' . $link->location . '</div>' : '';
                            $rep .= self::get_dropdown_icons($link->post, $link, 'inbound-internal');
                            $rep .=         '<a href="' . admin_url('post.php?post=' . $link->post->id . '&action=edit') . '" target="_blank">[edit]</a> 
                                            <a href="' . esc_url($link->post->getLinks()->view) . '" target="_blank">[view]</a>
                                            <br>
                                        </div>
                                    </li>';
                        } else {
                            $rep .= '<li><div><b>[' . esc_html(strip_tags($link->anchor)) . ']</b><br>[' . $link->location . ']<br><br></div></li>';
                        }
                    }
                }

                break;
            case 'wpil_links_outbound_internal_count':
                $links_data = $post->getOutboundInternalLinks();
                $count = 0;
                foreach ($links_data as $link) {
                    if (!Wpil_Filter::linksLocation() || $link->location == Wpil_Filter::linksLocation()) {
                        $count++;
                        if($count <= $current){
                            continue;
                        }

                        $primary_category_note = '';
                        if(!empty($link->post) && $link->post->type === 'post') {
                            // Get the main term
                            $post_type = $link->post->getRealType();
                            $primary_term = Wpil_Post::get_primary_term_for_main_taxonomy($link->post->id, $post_type);

                            if ($primary_term instanceof WP_Term) {
                                $primary_category_note = '<div style="margin: 3px 0;"><b>Main Category:</b> ' . esc_html($primary_term->name) . '</div>';
                            } else {
                                $primary_category_note = '<div style="margin: 3px 0;"><b>Main Category:</b> None assigned.</div>';
                            }
                        }
                        $rep .= '<li>
                                    <input type="checkbox" class="wpil_link_select" data-post_id="' . $post->id . '" data-post_type="' . $post->type . '" data-anchor="' . base64_encode($link->anchor) . '" data-url="' . base64_encode($link->url) . '">
                                    <div>
                                        <div style="margin: 3px 0;"><b>Link:</b> <a href="' . esc_url($link->url) . '" target="_blank" style="text-decoration: underline">' . esc_html($link->url) . '</a></div>
                                        <div style="margin: 3px 0;"><b>Anchor Text:</b> <a href="' . esc_url(add_query_arg(['wpil_admin_frontend' => '1', 'wpil_admin_frontend_data' => $link->create_scroll_link_data()], $post->getLinks()->view)) . '" target="_blank">' . esc_html($link->anchor) . ' <span class="dashicons dashicons-external" style="position: relative;top: 3px;"></span></a></div>
                                        <div style="margin: 3px 0;"><div class="content-relatedness-score"><b>Content Relatedness:</b> ' . esc_html($link->get_ai_relation_percent()) . '</div></div>';
                        $rep .= ($get_all_links) ? '<div style="margin: 3px 0;"><b>Link Location:</b> ' . $link->location . '</div>' : '';
                        $rep .= $primary_category_note;
                        $rep .= self::get_dropdown_icons($post, $link, 'outbound-internal');
                        $rep .=     '</div>
                                </li>';
                    }

                    $primary_category_note = '';
                    if(!empty($link->post) && $link->post->type === 'post') {
                        // Get the main term
                        $primary_term = Wpil_Post::get_primary_term_for_main_taxonomy($link->post->id, $link->post->getRealType());

                        if ($primary_term instanceof WP_Term) {
                            $primary_category_note = '<div style="margin: 3px 0;"><b>Main Category:</b> ' . esc_html($primary_term->name) . '</div>';
                        } else {
                            $primary_category_note = '<div style="margin: 3px 0;"><b>Main Category:</b> None assigned.</div>';
                        }
                    }

                    $rep .= '<li>
                                <div>
                                    <div style="margin: 3px 0;"><b>Link:</b> <a href="' . esc_url($link->url) . '" target="_blank" style="text-decoration: underline">' . esc_html($link->url) . '</a></div>
                                    <div style="margin: 3px 0;"><b>Anchor Text:</b> ' . esc_html(strip_tags($link->anchor)) . '</div>';
                    $rep .=         $primary_category_note;
                    $rep .=         self::get_dropdown_icons($post, $link, 'outbound-internal');
                    $rep .=     '</div>
                            </li>';
                }

                break;
            case 'wpil_links_outbound_external_count':
                $links_data = $post->getOutboundExternalLinks();
                $count = 0;
                foreach ($links_data as $link) {
                    $count++;
                    if($count <= $current){
                        continue;
                    }
                    $rep .= '<li>
                                <div>
                                    <div style="margin: 3px 0;"><b>Link:</b> <a href="' . esc_url($link->url) . '" target="_blank" style="text-decoration: underline">' . esc_html($link->url) . '</a></div>
                                    <div style="margin: 3px 0;"><b>Anchor Text:</b> ' . esc_html(strip_tags($link->anchor)) . '</div>';
                    $rep .=         self::get_dropdown_icons(array(), $link, 'outbound-external');
                    $rep .=     '</div>
                            </li>';
                }
                break;
        }

        wp_send_json(array('success' => array('item_data' => $rep, 'item_count' => $count)));
    }

    public static function ajax_assemble_link_report_link_data(){
        Wpil_Base::verify_nonce('wpil_report_link_nonce');

        if(!isset($_POST['link_type']) || !isset($_POST['post_id']) || !isset($_POST['post_type'])){
            wp_send_json(array('error' => array('title' => __('Data Missing', 'wpil'), 'text' => __('Some of the data required to load the links is missing. Please reload the page and try again.', 'wpil'))));
        }

        $table = '';
        $rep = '';
        $get_all_links = Wpil_Settings::showAllLinks();
        $post_id = (int)$_POST['post_id'];
        $post_type = ($_POST['post_type'] === 'post') ? 'post': 'term';
        $show_fix_anchors = (isset($_POST['show_fix_anchor']) && !empty($_POST['show_fix_anchor'])) ? true: false;
        $post = new Wpil_Model_Post($post_id, $post_type);
        $phrases = ($show_fix_anchors) ? Wpil_Suggestion::get_post_paragraphs($post->getContent(false)): '';
        $allow_multiple_links = !empty(get_user_meta(get_current_user_id(), 'wpil_allow_multiple_editor_links', true));

        $activity_tooltip = __('Upgrade Link Whisper with AI and see how relevant your links really are.', 'wpil');
        $ai_not_enabled = (!Wpil_Settings::has_ai_enabled()) ? '<div class="wpil-activity-upgrade-ai"><span>93%</span> <a href="'.admin_url('admin.php?page=link_whisper_ai_subscription').'" target="_blank" class="button-primary wpil-tippy-tooltipped" data-wpil-tooltip-theme="link-whisper-report-tippy" data-wpil-tooltip-interactive="1" data-wpil-tooltip-content="'.$activity_tooltip.'">Upgrade</a></div>': '';
        $last_col = ($get_all_links) ? '': 'wpil-activity-last-col';

        $header = ['<th class="wpil-activity-panel-checkbox panel-checkall wpil-activity-panel-fixed-th"><input class="wpil-activity-panel-checkall" type="checkbox"/></th>'];
        switch ($_POST['link_type']) {
            case 'inbound-internal':
                $links_data = $post->getInboundInternalLinks();
                $header = array_merge($header, [
                    '<th class="wpil-activity-panel-post">Origin Post Title</th>',
                    '<th>Anchor Text</th>',
                    '<th>URL</th>',
                    '<th class="wpil-activity-panel-content-related '.$last_col.'">AI Content Relatedness</th>'
                ]);

                if($get_all_links){
                    $header = array_merge($header, [
                        '<th class="wpil-activity-panel-link-location wpil-activity-last-col">Link Location</th>'
                    ]);
                }
                /*$header = array_merge($header, [
                    '<th class="wpil-link-status-icon-header wpil-activity-panel-fixed-th">Status</th>'
                ]);*/

                foreach ($links_data as $link) {
                    if (!Wpil_Filter::linksLocation() || $link->location == Wpil_Filter::linksLocation()) {
                        if (!empty($link->post)) {
                            $related = (!empty($link->get_ai_relation_percent(true)) && $link->get_ai_relation_percent(true) < 50) ? 'ai-not-related': '';
                            $edit_link = '';
                            $rep .= '<tr class="wpil-activity-panel-edit inactive">
                                        <td class="wpil-activity-panel-checkbox"><input type="checkbox" class="wpil_link_select wpil_activity_select" data-link_id="' . $link->post->id . '" data-post_id="'.$link->post->id.'" data-post_type="'.$link->post->type.'" data-anchor="'.base64_encode($link->anchor).'" data-url="'.base64_encode($link->url).'" data-nonce="' . wp_create_nonce('wpil_report_edit_' . $link->post->id . '_nonce_' . $link->post->id) . '"></td>
                                        <td class="wpil-activity-panel-post wpil-activity-panel-limited-text-cell"><div style="margin: 3px 0;"> ' . esc_html($link->post->getTitle()) . '</div></td>
                                        <td class="wpil-activity-panel-limited-text-cell">
                                            <div style="margin: 3px 0; display:flex;">
                                                '.$edit_link.'
                                                <div class="wpil-report-edit-display wpil-activity-panel-anchor-display">
                                                    <div class="wpil-anchor-display-text">' . esc_html($link->anchor) . '</div> <a href="' . esc_url(add_query_arg(['wpil_admin_frontend' => '1', 'wpil_admin_frontend_data' => $link->create_scroll_link_data()], $link->post->getLinks()->view)) . '" class="wpil-report-edit-display wpil-activity-panel-anchor-display" target="_blank"><span class="dashicons dashicons-external" title="'.esc_attr__('View On Page','wpil').'" style="position: relative;top: 3px;"></span></a>
                                                </div>';
                            if('related-post-link' !== Wpil_Toolbox::get_link_context($link->link_context)){
                            $rep .=        '<input class="wpil-activity-panel-anchor-edit wpil-report-edit-input" type="text" value="' . esc_attr($link->anchor) . '">';
                            }
                            $rep .=         '</div>
                                        </td>
                                        <td class="wpil-activity-panel-limited-text-cell">
                                        <div style="margin: 3px 0; display:flex;">
                                            '.$edit_link.'
                                            <div href="' . esc_url($link->url) . '" target="_blank" class="wpil-report-edit-display wpil-activity-panel-url-display" style="text-decoration: underline">
                                                <div class="wpil-url-display-text">' . esc_html($link->url) . '</div>
                                            </div>';
                        if('related-post-link' !== Wpil_Toolbox::get_link_context($link->link_context)){
                        $rep .=            '<input class="wpil-activity-panel-url-edit wpil-report-edit-input" type="text" value="' . esc_attr($link->url) . '">';
                        }
                        $rep .=         '</div>
                                    </td>
                                    <td class="'.$related.'"><div style="margin: 3px 0;"> ' . ((empty($ai_not_enabled)) ? esc_html($link->get_ai_relation_percent()): $ai_not_enabled) . '</div></td>';
                            $rep .= ($get_all_links) ? '<td><div style="margin: 3px 0;">' . $link->location . '</div></td>' : '';
//                            $rep .= '<td class="wpil-status-icon-cell">' . self::get_dropdown_icons($link->post, $link, 'inbound-internal', true) . '</td>';
                            $rep .= '</tr>';
                        } else {
                            $rep .= '<tr>
                                        <td>
                                            <b>' . esc_html(strip_tags($link->anchor)) . '</b><br>' . $link->location . '<br><br>
                                        </td>
                                    </tr>';
                        }
                    }
                }

                break;
            case 'outbound-internal':
                $header = array_merge($header, [
                    '<th>Target Post Title</th>',
                    '<th>Anchor Text</th>',
                ]);

                if(!$show_fix_anchors){
                    $header = array_merge($header, [
                        '<th>URL</th>',
                        '<th class="wpil-activity-panel-content-related '.$last_col.'">AI Content Relatedness</th>'
                    ]);
                }

                if($get_all_links){
                    $header = array_merge($header, [
                        '<th class="wpil-activity-panel-link-location wpil-activity-last-col">Link Location</th>'
                    ]);
                }
                /*$header = array_merge($header, [
                    //'<th>Primary Category</th>',
                    '<th class="wpil-link-status-icon-header wpil-activity-panel-fixed-th">Status</th>'
                ]);*/

                if($show_fix_anchors){
                    $header = array_merge($header, [
                        //'<th>Primary Category</th>',
                        '<th class="wpil-activity-panel-anchor-words wpil-activity-last-col">Word Count</th>'
                    ]);
                }

                $links_data = $post->getOutboundInternalLinks();
                foreach ($links_data as $link) {
                    if($show_fix_anchors && ($link->anchor_word_count > 2 && $link->anchor_word_count < 8)){
                        continue;
                    }

                    if (!Wpil_Filter::linksLocation() || $link->location == Wpil_Filter::linksLocation()) {
                        $target_post = (isset($link->target_id) && !empty($link->target_id)) ? new Wpil_Model_Post($link->target_id, $link->target_type): Wpil_Post::getPostByLink($link->url);
                        $edit_link = '';
                        $related = (!empty($link->get_ai_relation_percent(true)) && $link->get_ai_relation_percent(true) < 50) ? 'ai-not-related': '';
                        
                        /*
                        $primary_category_note = '<td><div style="margin: 3px 0;">None assigned</div></td>';
                        if(!empty($link->post) && $link->post->type === 'post') {
                            // Get the main term
                            $primary_term = Wpil_Post::get_primary_term_for_main_taxonomy($link->post->id, $link->post->getRealType());

                            if ($primary_term instanceof WP_Term) {
                                $primary_category_note = '<td><div style="margin: 3px 0;">' . esc_html($primary_term->name) . '</div></td>';
                            } else {
                                $primary_category_note = '<td><div style="margin: 3px 0;">None assigned</div></td>';
                            }
                        }elseif(!empty($link->post) && $link->post->type === 'term'){
                            $primary_category_note = '<td><div style="margin: 3px 0;">None</div></td>';
                        }*/

                        $phrase_key = false;
                        $phrase_key_id = '';
                        if($show_fix_anchors){
                            $phrase_key = self::find_link_phrase_key($phrases, $link);
                            if(false !== $phrase_key){
                                $phrase_key_id = 'data-wpil-sentence-id="'.$phrase_key.'"';
                            }
                        }

                        $rep .= '<tr class="wpil-activity-panel-edit sentences inactive" '.$phrase_key_id.'>
                                    <td class="wpil-activity-panel-checkbox"><input type="checkbox" class="wpil_link_select wpil_activity_select" data-link_id="' . $link->post->id . '" data-post_id="' . $post->id . '" data-post_type="' . $post->type . '" data-anchor="' . base64_encode($link->anchor) . '" data-url="' . base64_encode($link->url) . '" data-nonce="' . wp_create_nonce('wpil_report_edit_' . $link->post->id . '_nonce_' . $link->post->id) . '"></td>
                                    <td class="wpil-activity-panel-post wpil-activity-panel-limited-text-cell"><div style="margin: 3px 0;"> ' . ((!empty($target_post)) ? esc_html($target_post->getTitle()): 'Unknown Post') . '</div></td>
                                    <td class="'. (false !== $phrase_key ? '': 'wpil-activity-panel-limited-text-cell') .'">';
                            if(false !== $phrase_key && isset($phrases[$phrase_key])){
                                    $phrase = $phrases[$phrase_key];
                                    unset($phrases[$phrase_key]);
                                    $phr = self::assemble_suggestion_item($phrase);
                                $rep .= '
                                    <div style="display:none">'.$edit_link.'</div>
                                    <div class="sentence top-level-sentence" data-id="'.esc_attr($post->id).'" data-type="'.esc_attr($post->type).'">
                                        <div class="wpil_edit_sentence_form">
                                            <textarea class="wpil_content">'.$phr->suggestions[0]->sentence_src_with_anchor.'</textarea>
                                            <span class="button-primary">Save</span>
                                            <span class="button-secondary">Cancel</span>
                                            <span> <input type="checkbox" class="wpil-sentence-allow-multiple-links" data-nonce="'.wp_create_nonce(get_current_user_id() . 'allow_multiple_links_editor').'" '. (($allow_multiple_links) ? 'checked': '') .'>Allow multiple links in sentence</span>
                                        </div>
                                        <span class="wpil_sentence_with_anchor" data-li-id="0"><span class="wpil_sentence" title="'. esc_attr__('Double clicking a word will select it.', 'wpil') .'">' . $phr->suggestions[0]->sentence_with_anchor . '</span><span class="dashicons dashicons-image-rotate wpil-reload-sentence-with-anchor" title="'. esc_attr__('Click to undo changes', 'wpil') . '"></span></span>
                                        <span class="wpil_edit_sentence link-form-button">| <a href="javascript:void(0)">Edit Sentence</a></span>
                                        <input type="hidden" name="sentence" value="'.base64_encode($phr->sentence_src).'">
                                        <input type="hidden" name="custom_sentence" value="">
                                        <input type="hidden" name="original_sentence_with_anchor" value="'.base64_encode($phr->suggestions[0]->original_sentence_with_anchor).'">
                                        <input type="hidden" class="wpil-activity-panel-url-edit wpil-report-edit-input" value="' . esc_attr($link->url) . '">
                                    </div>';
                            }else{
                                $rep .= '<div style="margin: 3px 0; display:flex">
                                            '.$edit_link.'
                                            <div class="wpil-report-edit-display wpil-activity-panel-anchor-display"><div class="wpil-anchor-display-text">' . esc_html($link->anchor) . '</div> <a href="' . esc_url(add_query_arg(['wpil_admin_frontend' => '1', 'wpil_admin_frontend_data' => $link->create_scroll_link_data()], $post->getLinks()->view)) . '" target="_blank"><span class="dashicons dashicons-external" title="'.esc_attr__('View On Page','wpil').'" style="position: relative;top: 3px;"></span></a></div>';
                                if('related-post-link' !== Wpil_Toolbox::get_link_context($link->link_context)){
                                    $rep .= '<input class="wpil-activity-panel-anchor-edit wpil-report-edit-input" type="text" value="' . esc_attr($link->anchor) . '">';
                                }
                            }
                        $rep .=         '</div>
                                    </td>';
                        if($show_fix_anchors){
                            $rep .= '<td class="wpil-activity-panel-limited-text-cell wpil-activity-panel-word-count">' .$link->anchor_word_count. '</td>';
                        }else{
                            $rep .= '<td class="wpil-activity-panel-limited-text-cell">
                                            <div style="margin: 3px 0; display:flex">
                                                '.$edit_link.'
                                                <div href="' . esc_url($link->url) . '" target="_blank" class="wpil-report-edit-display wpil-activity-panel-url-display" style="text-decoration: underline">
                                                    <div class="wpil-url-display-text">' . esc_html($link->url) . '</div>
                                                </div>';
                            if('related-post-link' !== Wpil_Toolbox::get_link_context($link->link_context) && !$show_fix_anchors){
                            $rep .=            '<input class="wpil-activity-panel-url-edit wpil-report-edit-input" type="text" value="' . esc_attr($link->url) . '">';
                            }
                            $rep .=         '</div>
                                        </td>';
                            $rep .= '<td><div class="'.$related.'" style="margin: 3px 0;"><div class="content-relatedness-score">' . ((empty($ai_not_enabled)) ? esc_html($link->get_ai_relation_percent()): $ai_not_enabled) . '</div></div></td>';
                        }
                        $rep .= ($get_all_links) ? '<td><div style="margin: 3px 0;">' . $link->location . '</div></td>' : '';
//                        $rep .= $primary_category_note;
//                        $rep .= '<td class="wpil-status-icon-cell">' . self::get_dropdown_icons($post, $link, 'outbound-internal', true) . '</td>';
                        $rep .= '</tr>';
                    }
                }

                break;
            case 'outbound-external':
                $header = array_merge($header, [
                    '<th>Anchor Text</th>',
                ]);

                if(!$show_fix_anchors){
                    $header = array_merge($header, [
                        '<th>URL</th>'
                    ]);
                }

                if($get_all_links){
                    $header = array_merge($header, [
                        '<th class="wpil-activity-panel-link-location wpil-activity-last-col">Link Location</th>'
                    ]);
                }

                if($show_fix_anchors){
                    $header = array_merge($header, [
                        //'<th>Primary Category</th>',
                        '<th class="wpil-activity-panel-anchor-words wpil-activity-last-col">Word Count</th>'
                    ]);
                }

                /*$header = array_merge($header, [
                    '<th class="wpil-link-status-icon-header wpil-activity-panel-fixed-th">Status</th>'
                ]);*/

                $links_data = $post->getOutboundExternalLinks();
                foreach ($links_data as $link) {
                    if($show_fix_anchors && ($link->anchor_word_count > 2 && $link->anchor_word_count < 8)){
                        continue;
                    }

                    if (!Wpil_Filter::linksLocation() || $link->location == Wpil_Filter::linksLocation()) {
                        $target_post = (isset($link->target_id) && !empty($link->target_id)) ? new Wpil_Model_Post($link->target_id, $link->target_type): Wpil_Post::getPostByLink($link->url);
                        $edit_link = ('related-post-link' !== Wpil_Toolbox::get_link_context($link->link_context)) ? '<span class="dashicons dashicons-edit wpil_activate_edit_link" title="' . esc_attr__('Edit Link', 'wpil') . '"></span>' : '';
                        $related = (!empty($link->get_ai_relation_percent(true)) && $link->get_ai_relation_percent(true) < 50) ? 'ai-not-related': '';
                        
                        /*
                        $primary_category_note = '<td><div style="margin: 3px 0;">None assigned</div></td>';
                        if(!empty($link->post) && $link->post->type === 'post') {
                            // Get the main term
                            $primary_term = Wpil_Post::get_primary_term_for_main_taxonomy($link->post->id, $link->post->getRealType());

                            if ($primary_term instanceof WP_Term) {
                                $primary_category_note = '<td><div style="margin: 3px 0;">' . esc_html($primary_term->name) . '</div></td>';
                            } else {
                                $primary_category_note = '<td><div style="margin: 3px 0;">None assigned</div></td>';
                            }
                        }elseif(!empty($link->post) && $link->post->type === 'term'){
                            $primary_category_note = '<td><div style="margin: 3px 0;">None</div></td>';
                        }*/

                        $phrase_key = false;
                        $phrase_key_id = '';
                        if($show_fix_anchors){
                            $phrase_key = self::find_link_phrase_key($phrases, $link);
                            if(false !== $phrase_key){
                                $phrase_key_id = 'data-wpil-sentence-id="'.$phrase_key.'"';
                            }
                        }

                        $rep .= '<tr class="wpil-activity-panel-edit sentences inactive" '.$phrase_key_id.'>
                                    <td class="wpil-activity-panel-checkbox"><input type="checkbox" class="wpil_link_select wpil_activity_select" data-link_id="' . $link->post->id . '" data-post_id="' . $post->id . '" data-post_type="' . $post->type . '" data-anchor="' . base64_encode($link->anchor) . '" data-url="' . base64_encode($link->url) . '" data-nonce="' . wp_create_nonce('wpil_report_edit_' . $link->post->id . '_nonce_' . $link->post->id) . '"></td>
                                    <td class="'. (false !== $phrase_key ? '': 'wpil-activity-panel-limited-text-cell') .'">';
                            if(false !== $phrase_key && isset($phrases[$phrase_key])){
                                    $phrase = $phrases[$phrase_key];
                                    unset($phrases[$phrase_key]);
                                    $phr = self::assemble_suggestion_item($phrase);
                                $rep .= '
                                    <div style="display:none">'.$edit_link.'</div>
                                    <div class="sentence top-level-sentence" data-id="'.esc_attr($post->id).'" data-type="'.esc_attr($post->type).'">
                                        <div class="wpil_edit_sentence_form">
                                            <textarea class="wpil_content">'.$phr->suggestions[0]->sentence_src_with_anchor.'</textarea>
                                            <span class="button-primary">Save</span>
                                            <span class="button-secondary">Cancel</span>
                                            <span> <input type="checkbox" class="wpil-sentence-allow-multiple-links" data-nonce="'.wp_create_nonce(get_current_user_id() . 'allow_multiple_links_editor').'" '. (($allow_multiple_links) ? 'checked': '') .'>Allow multiple links in sentence</span>
                                        </div>
                                        <span class="wpil_sentence_with_anchor" data-li-id="0"><span class="wpil_sentence" title="'. esc_attr__('Double clicking a word will select it.', 'wpil') .'">' . $phr->suggestions[0]->sentence_with_anchor . '</span><span class="dashicons dashicons-image-rotate wpil-reload-sentence-with-anchor" title="'. esc_attr__('Click to undo changes', 'wpil') . '"></span></span>
                                        <span class="wpil_edit_sentence link-form-button">| <a href="javascript:void(0)">Edit Sentence</a></span>
                                        <input type="hidden" name="sentence" value="'.base64_encode($phr->sentence_src).'">
                                        <input type="hidden" name="custom_sentence" value="">
                                        <input type="hidden" name="original_sentence_with_anchor" value="'.base64_encode($phr->suggestions[0]->original_sentence_with_anchor).'">
                                        <input type="hidden" class="wpil-activity-panel-url-edit wpil-report-edit-input" value="' . esc_attr($link->url) . '">
                                    </div>';
                            }else{
                                $rep .= '<div style="margin: 3px 0; display:flex">
                                            '.$edit_link.'
                                            <div class="wpil-report-edit-display wpil-activity-panel-anchor-display"><div class="wpil-anchor-display-text">' . esc_html($link->anchor) . '</div> <a href="' . esc_url(add_query_arg(['wpil_admin_frontend' => '1', 'wpil_admin_frontend_data' => $link->create_scroll_link_data()], $post->getLinks()->view)) . '" target="_blank"><span class="dashicons dashicons-external" title="'.esc_attr__('View On Page','wpil').'" style="position: relative;top: 3px;"></span></a></div>';
                                if('related-post-link' !== Wpil_Toolbox::get_link_context($link->link_context)){
                                    $rep .= '<input class="wpil-activity-panel-anchor-edit wpil-report-edit-input" type="text" value="' . esc_attr($link->anchor) . '">';
                                }
                            }
                        $rep .=         '</div>
                                    </td>';
                        if($show_fix_anchors){
                            $rep .= '<td class="wpil-activity-panel-limited-text-cell wpil-activity-panel-word-count">' .$link->anchor_word_count. '</td>';
                        }else{
                            $rep .= '<td class="wpil-activity-panel-limited-text-cell">
                                            <div style="margin: 3px 0; display:flex">
                                                '.$edit_link.'
                                                <div href="' . esc_url($link->url) . '" target="_blank" class="wpil-report-edit-display wpil-activity-panel-url-display" style="text-decoration: underline">
                                                    <div class="wpil-url-display-text">' . esc_html($link->url) . '</div>
                                                </div>';
                            if('related-post-link' !== Wpil_Toolbox::get_link_context($link->link_context) && !$show_fix_anchors){
                            $rep .=            '<input class="wpil-activity-panel-url-edit wpil-report-edit-input" type="text" value="' . esc_attr($link->url) . '">';
                            }
                            $rep .=         '</div>
                                        </td>';
                            $rep .= '<td><div class="'.$related.'" style="margin: 3px 0;"><div class="content-relatedness-score">' . ((empty($ai_not_enabled)) ? esc_html($link->get_ai_relation_percent()): $ai_not_enabled) . '</div></div></td>';
                        }
                        $rep .= ($get_all_links) ? '<td><div style="margin: 3px 0;">' . $link->location . '</div></td>' : '';
//                        $rep .= $primary_category_note;
//                        $rep .= '<td class="wpil-status-icon-cell">' . self::get_dropdown_icons($post, $link, 'outbound-internal', true) . '</td>';
                        $rep .= '</tr>';
                    }
                }

                break;
        }

        $table .= '
            <table class="wpil-activity-table widefat" style="width:100%; border-collapse: collapse;">
                <thead>
                    <tr style="text-align: left;">' . implode('', $header) . '</tr>
                </thead>
                <tbody>' . $rep . '</tbody>
            </table>';
        $table .= '
            <div class="wpil-update-activity-items" style="display: flex; justify-content: space-between;">
                <a href="#" class="wpil-edit-selected-activity-items inactive" style="margin: 0 0 0 10px;" data-nonce="' . wp_create_nonce(wp_get_current_user()->ID . 'activity-item-action') . '"><span class="wpil-edit-inactive">📝 Edit Selected</span><span class="wpil-edit-active">🛑Stop Editing</span></a>
                <a href="#" class="wpil-update-selected-activity-items wpil_link_edit_update disabled" style="margin: 0 0 0 10px;" data-nonce="' . wp_create_nonce(wp_get_current_user()->ID . 'activity-item-action') . '">' . __('🔄 Update Selected', 'wpil') . '</a>
                <a href="#" class="wpil-delete-selected-activity-items disabled" style="margin: 0 0 0 10px;" data-nonce="' . wp_create_nonce(wp_get_current_user()->ID . 'delete-selected-links') . '">' . __('🗑️ Delete Selected', 'wpil') . '</a>
            </div>';

        wp_send_json(array('success' => array('link_table' => $table)));
    }

    /**
     * Identifyes the phrase that a specific link is inside
     **/
    private static function find_link_phrase_key($phrases = array(), $link = array()){
        if(empty($phrases) || empty($link)){
            return false;
        }

        // first, lets go over the phrases and try to find the link's monitoring id
        if(!empty($link->tracking_id)){
            foreach($phrases as $key => $phrase){
                if(preg_match('~data-wpil-monitor-id=["\']'.preg_quote($link->tracking_id, '~').'["\']~', $phrase->sentence_src, $m)){
                    return $key;
                }
            }
        }

        // if that dien't work, try finding the link in the phrases
        foreach($phrases as $key => $phrase){
            // if we found it
            if(preg_match('~href=["\']'.preg_quote($link->url, '~').'["\']|href=["\']'.preg_quote(wp_make_link_relative($link->url), '~').'["\']~', $phrase->sentence_src, $m)){
                // return it
                return $key;
            }
        }

        // if that didn't work, we can't find the link
        return false;
    }

    /**
     * Assembles a suggestion object for us to use in the anchor report so that usuers can customize their links!
     * Oh isn't it glorious!!!!
     **/
    private static function assemble_suggestion_item($phrase = array()){
        if(empty($phrase)){
            return false;
        }

        $suggestion = (object) array(
            'sentence_with_anchor' => '',
            'sentence_src_with_anchor' => '',
            'original_sentence_with_anchor' => ''
        );

        //get anchors and sentence with anchor
        $nbsp = urldecode('%C2%A0');

        $sentence_with_anchor = '<span class="wpil_word">' . implode('</span> <span class="wpil_word">', preg_split('~<[^>]*>(*SKIP)(*F)|\s+~u', str_replace($nbsp, ' ', strip_tags($phrase->sentence_src, '<b><i><u><strong><em><code><a>')))) . '</span>';
        $sentence_with_anchor = str_replace(
            [   ',</span>', 
                '<span class="wpil_word">(', 
                ')</span>', 
                ':</span>', 
                '<span class="wpil_word">\'', 
                '\'</span>'
            ], 
            [   '</span><span class="wpil_word no-space-left wpil-non-word">,</span>', 
                '<span class="wpil_word no-space-right wpil-non-word">(</span><span class="wpil_word">', 
                '</span><span class="wpil_word no-space-left wpil-non-word">)</span>', 
                '</span><span class="wpil_word no-space-left wpil-non-word">:</span>', 
                '<span class="wpil_word no-space-right wpil-non-word">\'</span><span class="wpil_word">', 
                '</span><span class="wpil_word no-space-left wpil-non-word">\'</span>'
            ], $sentence_with_anchor);
        $sentence_with_anchor = Wpil_Suggestion::formatTags($sentence_with_anchor);

        // make sure the anchor isn't inside a word
        $sentence_with_anchor = str_replace(['<span class="wpil_word"><a', '</a></span>'], ['<a', '</a>'], $sentence_with_anchor);

        // make sure the anchor words have correct span padding
        $sentence_with_anchor = preg_replace_callback('~(<a\b[^>]*>)(.*?)(</a>)~is', function ($m) {
            [$full, $open, $inner, $close] = $m;

            // Inside the anchor, wrap ONLY the plain-text runs (not tags)
            $wrapped = preg_replace_callback('~([^<]+)|(<[^>]+>)~s', function ($n) {
                // $n[1] = a chunk of text (no "<"), $n[2] = a tag
                if (!empty($n[1])) {
                    // Don’t create empty spans for whitespace-only chunks
                    return trim($n[1]) === '' ? $n[1] : '<span class="wpil_word">' . $n[1] . '</span>';
                }
                return $n[2]; // pass tags straight through
            }, $inner);

            return $open . $wrapped . $close;
        }, $sentence_with_anchor);

        //Wpil_Suggestion::setSentenceSrcWithAnchor($suggestion, $phrase->sentence_src, $words_real[$min], $words_real[$max]);

        $suggestion->sentence_src_with_anchor = $phrase->sentence_src;
        $suggestion->sentence_with_anchor = Wpil_Suggestion::setSuggestionTags($sentence_with_anchor);
        $suggestion->original_sentence_with_anchor = $suggestion->sentence_with_anchor; // for 'reset' not for getting back to the original

        $phrase->suggestions = array($suggestion);
        return $phrase;
    }


    /**
     * Saves the screen options via ajax
     **/
    public static function ajax_save_screen_options(){
        if(!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'screen-options-nonce')){
            wp_send_json(
                array(
                    'error' => array(
                        'title' => __('Data Error', 'wpil'),
                        'text'  => __('There was an error in processing the data, please reload the page and try again.', 'wpil'),
                    )
                )
            );
        }

        $return = array('error' => 'this is an error!');

        $report_types = array('report_options', 'wpil_keyword_options', 'target_keyword_options');

        if( isset($_POST['options']) && !empty(($_POST['options'])) && is_array(($_POST['options'])) &&
            isset($_POST['options']['wp_screen_options_name']) && 
            in_array($_POST['options']['wp_screen_options_name'], $report_types, true) 
        ){
            $report = $_POST['options']['wp_screen_options_name'];
            $index = array(
                // links report
                'show_categories' => 'on/off',
                'show_type' => 'on/off',
                'show_date' => 'on/off',
                'show_traffic' => 'on/off',
                'hide_ignore' => 'on/off',
                'hide_noindex' => 'on/off',
                'show_link_attrs' => 'on/off',
                'show_click_traffic' => 'on/off',
                'show_broken_link_type' => 'on/off',
                'show_broken_link_discovered' => 'on/off',
                'enable_tours' => 'on/off',

                // autolinking report
                'hide_select_links_column' => 'on/off',

                // target keywords
                'show_date' => 'on/off',
                'show_traffic' => 'on/off',
                'remove_obviated_keywords' => 'on/off',

                // general
                'per_page' => 'int',
            );

            $user_id = get_current_user_id();
            $options = get_user_meta($user_id, $report, true);

            if(empty($options) || is_string($options)){
                $options = array();
            }

            foreach($_POST['options'] as $key => $value){
                if(!isset($index[$key])){
                    continue;
                }

                if($index[$key] === 'on/off' && ($value === 'on' || $value === 'off')){
                    $options[$key] = $value;
                }elseif($index[$key] === 'int'){
                    $options[$key] = (int) $value;
                }
            }

            update_user_meta($user_id, $report, $options);

            // If this is report_options and enable_tours was updated, also update the global setting
            if ($report === 'report_options' && isset($options['enable_tours'])) {
                update_option('wpil_enable_tours', ($options['enable_tours'] === 'on') ? '1' : '0');
            }

            $return = array('success' => 'this is success!');
        }

        wp_send_json($return);
    }

    /**
     * Permanently dismisses the suggestion popup notifications
     **/
    public static function ajax_dismiss_popup_notice(){
        Wpil_Base::verify_nonce('dismiss-popup-nonce');

        $user_id = get_current_user_id();
        $ignore_popups = get_user_meta($user_id, 'wpil_dismissed_popups', true);
        $ignore_popups = (!empty($ignore_popups)) ? $ignore_popups: array();

        if(isset($_POST['popup_name']) && !empty($_POST['popup_name'])){
            switch ($_POST['popup_name']) {
                case 'suggestions':
                    $ignore_popups['suggestions'] = 1;
                    break;
                case 'target_keyword_create':
                    $ignore_popups['target_keyword_create'] = 1;
                    break;
                case 'target_keyword_delete':
                    $ignore_popups['target_keyword_delete'] = 1;
                    break;
                case 'target_keyword_update':
                    $ignore_popups['target_keyword_update'] = 1;
                    break;
                case 'link_report_trash_post':
                    $ignore_popups['link_report_trash_post'] = 1;
                    break;
                case 'delete_link':
                    $ignore_popups['delete_link'] = 1;
                    break;
                case 'update_domain_attribute':
                    $ignore_popups['update_domain_attribute'] = 1;
                    break;
                case 'update_broken_link_url':
                    $ignore_popups['update_broken_link_url'] = 1;
                    break;
            }
        }

        update_user_meta($user_id, 'wpil_dismissed_popups', $ignore_popups);
        wp_send_json(array('success' => 'Yay!'));
    }

    /**
     * Obtains the status icons for the URLs in the linking dropdowns
     * @param object $post
     * @param object $link
     **/
    public static function get_dropdown_icons($post = array(), $link = array(), $disposition = 'inbound-internal', $hide_header = false){
        $icons = '';
        $stats = array();

        if(!empty($post)){
            $redirected_post_url = Wpil_Link::get_url_redirection($post->getViewLink());

            // if the current item is a post and it's had it's URL redirected
            if($post->type === 'post' && !empty($redirected_post_url)){
                // check if the redirect is pointing to a different post
                $new_post = Wpil_Post::getPostByLink($redirected_post_url);
                // if it is, or the redirect is pointing to the home url
                if(!empty($new_post) && $post->id !== $new_post->id || Wpil_Link::url_points_home($redirected_post_url)){
                    // create the "hidden by redirect" icon
                    if($disposition === 'inbound-internal'){
                        $description = __('Source post hidden by redirect', 'wpil');
                    }elseif($disposition === 'outbound-internal'){
                        $description = __('Target post hidden by redirect', 'wpil');
                    }else{
                        $description = __('Unknown Error', 'wpil'); // if we're seeing outbound external posts listed in the redirects, something is going wrong
                    }

                    $icon = '';
                    $icon .= '<div class="wpil_help">';
                    $icon .= '<i class="dashicons dashicons-hidden"></i>';
                    $icon .= '<div class="wpil-help-text" style="display: none;">' . $description . '</div>';
                    $icon .= '</div>';
                    $stats[] = $icon;
                }
                /*
                TODO: Consider creating "redirect applied" icon for posts so we can tell users if the post has any kind of redirect
                $redirected = Wpil_Settings::getRedirectedPosts();
                if(!empty($redirected) && in_array($post->id, $redirected)){
                    
                    if($disposition === 'inbound-internal'){
                        $description = __('Source post hidden by redirect', 'wpil');
                    }elseif($disposition === 'outbound-internal'){
                        $description = __('Target post hidden by redirect', 'wpil');
                    }else{
                        $description = __('Unknown Error', 'wpil'); // if we're seeing outbound external posts listed in the redirects, something is going wrong
                    }

                    $icon = '';
                    $icon .= '<div class="wpil_help">';
                    $icon .= '<i class="dashicons dashicons-hidden"></i>';
                    $icon .= '<div class="wpil-help-text" style="display: none;">' . $description . '</div>';
                    $icon .= '</div>';
                    $stats[] = $icon;
                }*/
            }

        }

        $redirected_url = Wpil_Link::get_url_redirection($link->url);
        if(!empty($redirected_url)){
            $icon = '';
            $icon .= '<div class="wpil_help">';
            $icon .= '<i class="dashicons dashicons-redo"></i>';
            $icon .= '<div class="wpil-help-text" style="display: none;">' . __('URL being redirected to: ', 'wpil') . esc_url($redirected_url) . '</div>';
            $icon .= '</div>';
            $stats[] = $icon;
        }

        if(isset($link->link_context) && !empty($link->link_context)){
            $icon = '';
            $icon .= '<div class="wpil_help">';
            $icon .= self::get_link_context_icon($link->link_context);
            $icon .= '</div>';
            $stats[] = $icon;
        }

        // if this is a post and the links are incoming from pillar content
        if(!empty($post) && $post->type === 'post' && $disposition === 'inbound-internal'){
            $is_pillar = false;
            if(class_exists('WPSEO_Meta') && method_exists('WPSEO_Meta', 'get_value')){
                $is_pillar = (WPSEO_Meta::get_value('is_cornerstone', $post->id) === '1');
            }

            if(empty($is_pillar) && defined('RANK_MATH_VERSION')){
                $is_pillar = Wpil_Toolbox::check_pillar_content_status($post->id);
            }

            if(!empty($is_pillar)){
                $icon = '';
                $icon .= '<div class="wpil_help">';
                $icon .= '<i class="dashicons dashicons-media-text"></i>';
                $icon .= '<div class="wpil-help-text" style="display: none;">' . __('Linked From Pillar Content', 'wpil') . '</div>';
                $icon .= '</div>';
                $stats[] = $icon;
            }
        }

        if(!empty($stats)){
            if(!$hide_header){
                $icons = '<div class="wpil-link-status-icon-container" style="margin: 3px 0;"><b>Status:</b> ' . implode('', $stats) . '</div>';
            }else{
                $icons = '<div class="wpil-link-status-icon-container" style="margin: 3px 0;">' . implode('', $stats) . '</div>';
            }
        }

        return $icons;
    }

    public static function get_link_context_icon($context_id = 0){
        $context = '';
        switch (Wpil_Toolbox::get_link_context($context_id)) {
            case 'normal':
                $context .= '<i class="dashicons dashicons-admin-links"></i>';
                $context .= '<div class="wpil-help-text" style="display: none;">' . __('Normal Link', 'wpil') . '</div>';
                break;
            case 'related-post-link':
                $context .= '<i class="dashicons dashicons-migrate"></i>';
                $context .= '<div class="wpil-help-text" style="display: none;">' . __('Related Post Link', 'wpil') . '</div>';
                break;
            case 'page-builder-link':
                $context .= '<i class="dashicons dashicons-admin-page"></i>';
                $context .= '<div class="wpil-help-text" style="display: none;">' . __('Page Builder or Module Link. (may not be deletable)', 'wpil') . '</div>';
                break;
        }

        return $context;
    }

    public static function getCustomFieldsInboundLinks($url)
    {
        global $wpdb;

        if(!Wpil_Settings::get_acf_active()){
            return array();
        }

        $posts = [];
        $custom_fields = Wpil_Post::getAllCustomFields();
        $custom_fields = !empty($custom_fields) ? " m.meta_key IN ('" . implode("', '", $custom_fields ) . "') AND " : '';
        $statuses_query = Wpil_Query::postStatuses('p');
        $result = $wpdb->get_results($wpdb->prepare("SELECT m.post_id FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON m.post_id = p.ID WHERE $custom_fields m.meta_value LIKE %s $statuses_query", Wpil_Toolbox::esc_like($url)));

        if ($result) {
            foreach ($result as $post) {
                $posts[] = new Wpil_Model_Post($post->post_id);
            }
        }

        return $posts;
    }

    /**
     * Creates the report links table in the database if it doesn't exist.
     * Clears the link table if it does.
     * Can be set to only create the link table if it doesn't already exist
     * @param bool $only_insert_table
     **/
    public static function setupWpilLinkTable($only_insert_table = false){
        global $wpdb;
        $wpil_links_table = $wpdb->prefix . 'wpil_report_links';
        $wpil_link_table_query = "CREATE TABLE IF NOT EXISTS {$wpil_links_table} (
                                    link_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                    post_id bigint(20) unsigned NOT NULL,
                                    target_id bigint(20) unsigned NOT NULL,
                                    target_type varchar(8),
                                    clean_url text,
                                    raw_url text,
                                    host text,
                                    anchor text,
                                    anchor_word_count int(10) NOT NULL DEFAULT 0,
                                    internal tinyint(1) DEFAULT 0,
                                    has_links tinyint(1) NOT NULL DEFAULT 0,
                                    post_type varchar(8),
                                    location varchar(20),
                                    broken_link_scanned tinyint(1) DEFAULT 0,
                                    link_whisper_created tinyint(1) DEFAULT 0,
                                    is_autolink tinyint(1) DEFAULT 0,
                                    tracking_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
                                    module_link tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
                                    link_context tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
                                    ai_relation_score double UNSIGNED NOT NULL DEFAULT 0,
                                    PRIMARY KEY  (link_id),
                                    INDEX (post_id),
                                    INDEX (post_type),
                                    INDEX (target_id),
                                    INDEX (target_type),
                                    INDEX (clean_url(500)),
                                    INDEX (host(64)),
                                    INDEX (tracking_id)
                                ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";
        // create DB table if it doesn't exist
        require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($wpil_link_table_query);

        // if there was an error during table creation and it was about the size of the `clean_url` index
        if(!empty($wpdb->last_error) && false !== strpos($wpdb->last_error, 'Index column size too large')){
            // build a table create query with a smaller index size and try again
            $wpil_link_table_query = str_replace('INDEX (clean_url(500))', 'INDEX (clean_url(180))', $wpil_link_table_query);
            dbDelta($wpil_link_table_query);
        }

        if(self::link_table_is_created()){
            update_option(WPIL_LINK_TABLE_IS_CREATED, true);
        }

        if(!$only_insert_table){
            // and clear any existing data
            $wpdb->query("TRUNCATE TABLE {$wpil_links_table}");
        }

        Wpil_Base::fixCollation($wpil_links_table);
    }

    /**
     * Creates the link tracking table that we use to tag and monitor links.
     **/
    public static function prepare_link_tracking_table(){
        global $wpdb;
        $wpil_link_tracking_table = $wpdb->prefix . 'wpil_tracked_link_ids';
        $wpil_link_table_query = "CREATE TABLE {$wpil_link_tracking_table} (
                                    link_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                    creation_time bigint(20) unsigned NOT NULL,
                                    author_id bigint(20) unsigned NOT NULL,
                                    PRIMARY KEY  (link_id),
                                    KEY idx_author_id (author_id)
                                ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";
        // create DB table if it doesn't exist
        require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($wpil_link_table_query);
    }

    /**
     * Does a full search of the DB to check for post ids that don't show up in the link table,
     * and then it processes each of those posts to extract the urls from the content to insert in the link table.
     **/
    public static function fillWpilLinkTable(){
        global $wpdb;
        $links_table = $wpdb->prefix . "wpil_report_links";
        $count = 0;
        $memory_break_point = self::get_mem_break_point();
        $speed_optimize = Wpil_Settings::optimize_link_scan_for_speed();

        // get the ids that haven't been added to the link table yet
        $unprocessed_ids = self::get_all_unprocessed_link_post_ids();
        // if all the posts have been processed
        if(empty($unprocessed_ids)){
            // check to see if categories have been selected for processing
            if(!empty(Wpil_Settings::getTermTypes())){
                // check for categories
                $terms = [];
                $updated_terms = $wpdb->get_results("SELECT DISTINCT `post_id` FROM {$links_table} WHERE `post_type` = 'term'");
                foreach ($updated_terms as $key => $term) {
                    $terms[] = $term->post_id;
                }
                $term_query = !empty($terms) ? " AND `term_id` NOT IN (" . implode(',', $terms) . ") " : "";
                $terms = $wpdb->get_results("SELECT `term_id` FROM {$wpdb->prefix}term_taxonomy WHERE taxonomy IN ('" . implode("','" , Wpil_Settings::getTermTypes()) . "') " . $term_query);

                // if there are categories
                $term_update_count = 0;
                if ($terms) {
                    foreach ($terms as $term) {
                        if(Wpil_Base::overTimeLimit(15, 30) || ('disabled' !== $memory_break_point && memory_get_usage() > $memory_break_point)){
                            break;
                        }

                        // insert the term's links into the link table
                        $post = new Wpil_Model_Post($term->term_id, 'term');
                        $term_insert_count = self::insert_links_into_link_table($post);

                        // if the link insert was successful, increase the update count
                        if($term_insert_count > 0){
                            $term_update_count += $term_insert_count;
                        }
                    }
                }

                // track the progress
                $total = (isset($_POST['link_posts_to_process_count'])) ? (int)$_POST['link_posts_to_process_count'] : self::get_total_post_count();
                Wpil_Toolbox::track_process_progress(
                    'link_scanning', 
                    'Searching for Links', 
                    $term_update_count, 
                    count($terms), 
                    $total
                );

                // if all the found cats have had their links loaded in the database
                if(count($terms) === $term_update_count){
                    // return success
                    return array('completed' => true, 'inserted_posts' => $term_update_count);
                }else{
                    // if not, go around again
                    return array('completed' => false, 'inserted_posts' => $term_update_count);
                }
            }
            
            return array('completed' => true, 'inserted_posts' => 0);
        }

        $interval = microtime(true);
        $old_count = 0;
        $posts = [];
        foreach($unprocessed_ids as $key => $id){
            // exit the loop if we've been at this for 30 seconds or we've passed the memory breakpoint
            if(Wpil_Base::overTimeLimit(15, 30) || ('disabled' !== $memory_break_point && memory_get_usage() > $memory_break_point)){
                break; 
            }

            // allow other plugin/code to record what post id we're about to process
            do_action('wpil_fill_link_table_before_process', $id);

            // set up a new post with the current id
            if(self::insert_links_into_link_table(new Wpil_Model_Post($id))){
                $count++;
                unset($unprocessed_ids[$key]);
            }

            // if we've been at this for over 5 seconds
            if(microtime(true) - $interval > 5){
                $interval = microtime(true);
                $total = (isset($_POST['link_posts_to_process_count'])) ? (int)$_POST['link_posts_to_process_count'] : self::get_total_post_count();
                
                $diff = ($count - $old_count);
                $old_count = $count;

                // track the progress
                Wpil_Toolbox::track_process_progress(
                    'link_scanning', 
                    'Searching for Links', 
                    $diff, 
                    count($unprocessed_ids), 
                    $total
                );
            }

            // check to see if the user has set a limit on the max number of posts to process at one go
            if(apply_filters('wpil_fill_link_table_post_limit_break', false, $count)){
                // if we've exceeded the limit, stop processing posts
                break; 
            }
        }

        if($speed_optimize){
            // update the stored list of unprocessed ids as they're checked off so we stay up to date
            set_transient('wpil_stored_unprocessed_link_ids', $unprocessed_ids, MINUTE_IN_SECONDS * 5);
        }

        // track the progress
        $total = (isset($_POST['link_posts_to_process_count'])) ? (int)$_POST['link_posts_to_process_count'] : self::get_total_post_count();
        $diff = ($count - $old_count);
        Wpil_Toolbox::track_process_progress(
            'link_scanning', 
            'Searching for Links', 
            $diff, 
            count($unprocessed_ids), 
            $total
        );

        return array('completed' => false, 'inserted_posts' => $count);
    }

    /**
     * 
     **/
    public static function update_reusable_block_links($post){
        global $wpdb;

        if(empty($post) || $post->post_type !== 'wp_block' || !Wpil_Settings::update_reusable_block_links()){
            return;
        }

        // don't save on autosaves
        if(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE){
            return;
        }

        // find all of the posts with this block
        $content = $wpdb->prepare(" AND `post_content` LIKE %s", Wpil_Toolbox::esc_like('<!-- wp:block {"ref":' . $post->ID . '} /-->'));
        $post_types = Wpil_Query::postTypes();
        $post_statuses = Wpil_Query::postStatuses();
        $posts = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE 1=1 {$post_types} {$post_statuses} {$content}");

        foreach($posts as $post_id){
            $post = new Wpil_Model_Post($post_id);
            if(self::stored_link_content_changed($post)){
                // get the fresh post content for the benefit of the descendent methods
                $post->getFreshContent();
                // find any Inbound Internal link references that are no longer valid
                $removed_links = self::find_removed_report_inbound_links($post);
                // update the links stored in the link table
                self::update_post_in_link_table($post);
                // update the meta data for the post
                self::statUpdate($post, true);
                // and update the link counts for the posts that this one links to
                self::updateReportInternallyLinkedPosts($post, $removed_links);
            }
        }
    }

    /**
     * First checks to see if the links in the current post's content are different from the ones stored in the Links table.
     * Then checks to see if the meta-stored links have changed
     *
     * @param object $post The post object that we're checking
     * @return bool True if the links have changed, False if they haven't
     **/
    public static function stored_link_content_changed($post){
        global $wpdb;
        $links_table = $wpdb->prefix . "wpil_report_links";

        if(empty($post)){
            return false;
        }

        $stored_links   = $wpdb->get_results($wpdb->prepare("SELECT `raw_url`, `anchor` FROM {$links_table} WHERE `post_id` = %d AND `post_type` = %s", $post->id, $post->type));
        $post_links     = self::getContentLinks($post, true);

        // if there are links in the content and in storage, create URL-anchor strings so we can compare them
        $stored = '';
        $content = '';

        foreach($stored_links as $link){
            $stored .= ($link->raw_url . $link->anchor);
        }

        foreach($post_links as $link){
            $content .= ($link->url . $link->anchor);
        }

        if(md5($stored) !== md5($content)){
            return true;
        }

        // if the database is up to date, check to make sure the post meta links are too
        $meta_links = array_merge($post->getOutboundInternalLinks(), $post->getOutboundExternalLinks());

        // first check the link count
        if(count($meta_links) !== count($post_links)){
            // if they don't match, the links have changed
            return true;
        }

        // if that didn't work, create a link hash string and check that
        $meta_content = '';
        foreach($meta_links as $link){
            $meta_content .= ($link->url . $link->anchor);
        }

        // return if the link hash matches the one we just pulled out of the content
        return md5($meta_content) !== md5($content);
    }

    /**
     * Finds links that are listed in the links report table, but aren't actually present in the current post.
     **/
    public static function find_removed_report_inbound_links($post){
        global $wpdb;
        $links_table = $wpdb->prefix . 'wpil_report_links';

        if(empty($post)){
            return array();
        }

        // first get all of the links that the post currently has
        $links = self::getContentLinks($post, true);

        // clean up and key the Outbound Internal links so we can search them quickly
        $existing = array();
        foreach($links as $link){
            if(empty($link) || empty($link->internal)){
                continue;
            }
            $existing[self::getCleanUrl($link->url) . '_' . $link->anchor] = true;
        }

        // now get all the ones that are stored in the links table
        $report_links = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$links_table} WHERE `post_id` = %d AND `post_type` = %s", $post->id, $post->type));
    
        // go over the results and find all the links that have been removed
        $removed = array();
        foreach($report_links as $report_link){
            if(empty($report_link) || empty($report_link->internal)){
                continue;
            }

            if(!isset($existing[$report_link->clean_url . '_' . $report_link->anchor])){
                $removed[] = $report_link;
            }
        }

        return $removed;
    }

    /**
     * Updates a post's content links by removing the existing link data from the link table and inserting new links from the post content.
     * @param int|object $post 
     * @return bool
     **/
    public static function update_post_in_link_table($post){
        // if we've just been given a post id
        if(is_numeric($post) && !is_object($post)){
            // create a new post object
            $post = new Wpil_Model_Post($post);
        }

        $remove = self::remove_post_from_link_table($post);
        $insert = self::insert_links_into_link_table($post);

        return (empty($remove) || empty($insert)) ? false : true;
    }

    public static function remove_post_from_link_table($post, $delete_link_refs = false){
        global $wpdb;
        $links_table = $wpdb->prefix . "wpil_report_links";

        // exit if a post id isn't given
        if(empty($post)){
            return 0;
        }

        // delete the rows for this post that are stored in the links table
        $results = $wpdb->delete($links_table, array('post_id' => $post->id, 'post_type' => $post->type));
        $results2 = 0;

        // if we're supposed to remove the links that point to the current post as well
        if($delete_link_refs){
            // get the url
            $url = $post->getLinks()->view;
            $cleaned_url = self::getCleanUrl($url);
            // if there is a url
            if(!empty($cleaned_url)){
                // delete the rows that have this post's url in them
                $results2 = $wpdb->delete($links_table, array('clean_url' => $cleaned_url));
            }
        }

        // add together the results of both possible delete operations to get the total rows removed
        return (((int) $results) + ((int) $results2));
    }

    /**
     * Extracts the links from the given post and inserts them into the link table.
     * @param object $post 
     * @param array $posts An array of post objects that already have the content pulled for quicker bulk processing 
     * @return int $count (1 if success, 0 if failure)
     **/
    public static function insert_links_into_link_table($post, $posts = array()){
        global $wpdb;
        $links_table = $wpdb->prefix . "wpil_report_links";
        $speed_optimize = Wpil_Settings::optimize_link_scan_for_speed();

        $count = 0;
        $insert_query = "INSERT INTO {$links_table} (post_id, target_id, target_type, clean_url, raw_url, host, anchor, anchor_word_count, internal, has_links, post_type, location, broken_link_scanned, link_whisper_created, is_autolink, tracking_id, module_link, link_context, ai_relation_score) VALUES ";
        $links_data = array();
        $place_holders = array();

        if(empty($posts)){
            $posts = array($post);
        }

        foreach($posts as $post){
            $ai_relation_data = Wpil_AI::get_embedding_relatedness_data($post->id, $post->type);

            if($speed_optimize){
                $links = self::getContentLinks($post, false, $post->getContent()); 
            }else{
                $links = self::getContentLinks($post); 
            }

            foreach($links as $link){
                $ai_relation_score = 0;
                if(!empty($ai_relation_data) && isset($link->post) && !empty($link->post)){
                    $pid = $link->post->get_pid();
                    if(isset($ai_relation_data->$pid) && !empty($ai_relation_data->$pid)){
                        $ai_relation_score = $ai_relation_data->$pid;
                    }
                }

                array_push (
                    $links_data,
                    $post->id,
                    !empty($link->post) ? $link->post->id: 0,
                    !empty($link->post) ? $link->post->type: '',
                    self::getCleanUrl($link->url),
                    $link->url,
                    $link->host,
                    $link->anchor,
                    Wpil_Word::getWordCount($link->anchor),
                    $link->internal,
                    1,
                    $post->type,
                    $link->location,
                    0,
                    $link->link_whisper_created,
                    $link->is_autolink,
                    $link->tracking_id,
                    $link->module_link,
                    $link->link_context,
                    $ai_relation_score
                );

                $place_holders [] = "('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%f')";
            }

            // if there are no links, update the link table with null values to remove it from processing
            if(empty($links)){
                $insert = $wpdb->insert(
                    $links_table,
                    array(
                        'post_id' => $post->id,
                        'target_id' => 0,
                        'target_type' => null,
                        'clean_url' => null,
                        'raw_url' => null,
                        'host' => null,
                        'anchor' => null,
                        'anchor_word_count' => 0,
                        'internal' => null,
                        'has_links' => 0,
                        'post_type' => $post->type,
                        'location' => 'content',
                        'broken_link_scanned' => 0,
                        'link_whisper_created' => 0,
                        'is_autolink' => 0,
                        'tracking_id' => 0,
                        'module_link' => 0,
                        'link_context' => 0,
                        'ai_relation_score' => 0
                    )
                );

                // if the insert was successful
                if(false !== $insert){
                    // increase the insert count
                    $count += 1;
                }
            }

        }

        if (count($place_holders) > 0) {
            $insert_query .= implode (', ', $place_holders);
            $insert_query = $wpdb->prepare ($insert_query, $links_data);
            $insert = $wpdb->query ($insert_query);

            // if the insert was successful
            if(false !== $insert){
                // increase the insert count
                $count += 1;
            }
        }

        // if there are no links, update the link table with null values to remove it from processing
        if(empty($links)){
            $insert = $wpdb->insert(
                $links_table,
                array(
                    'post_id' => $post->id,
                    'clean_url' => null,
                    'raw_url' => null,
                    'host' => null,
                    'anchor' => null,
                    'internal' => null,
                    'has_links' => 0,
                    'post_type' => $post->type
                )
            );

            // if the insert was successful
            if(false !== $insert){
                // increase the insert count
                $count += 1;
            }
        }
        
        return $count;
    }

    /**
     * Gets all post ids from the post table and returns an array of ids.
     * @return array $all_post_ids (an array of all post ids from the post table. Categories aren't included. We're focusing on post ids since they make up the bulk of the ids)
     **/
    public static function get_all_post_ids($age_limit = null){
        if (empty(self::$all_post_ids)){
            global $wpdb;

            $post_types = Wpil_Settings::getPostTypes();
            $post_type_replace_string = "";
            if (!empty($post_types)) {
                $post_type_replace_string = " AND post_type IN ('" . implode("', '", $post_types) . "') ";
            }

            // get the ids that aren't supposed to be processed
            $ignored_pages = Wpil_Settings::get_completely_ignored_pages();
            $completely_ignore = '';
            if(!empty($ignored_pages)){
                $data = array();
                foreach($ignored_pages as $id){
                    if(false !== strpos($id, 'post')){
                        $dat = explode('_', $id);
                        $data[] = $dat[1];
                    }
                }

                if(!empty($data)){
                    $completely_ignore = " AND ID NOT IN (" . implode(", ", $data) . ") ";
                }
            }

            $max_age = "";
            if(!empty($age_limit)){
                if($age_limit === 'ai'){ // TODO: Create case switch if we need more limit settings
                    $max_age = Wpil_Query::getPostDateQueryLimit('', $age_limit);
                }
            }

            $statuses_query = Wpil_Query::postStatuses();
            self::$all_post_ids = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE 1=1 {$statuses_query} {$post_type_replace_string} {$completely_ignore} {$max_age}");
        }

        return self::$all_post_ids;
    }

    /**
     * Gets all term ids from taxonomies that we process and returns an array of ids.
     * @return array $all_term_ids
     **/
    public static function get_all_term_ids(){
        if (empty(self::$all_term_ids)){
            global $wpdb;

            $taxonomies = Wpil_Query::taxonomyTypes();
            if(!empty($taxonomies)){
                self::$all_term_ids = $wpdb->get_col("SELECT term_id FROM {$wpdb->term_taxonomy} WHERE 1=1 {$taxonomies}");
            }
        }

        return self::$all_term_ids;
    }

    /**
     * Gets all post ids that aren't listed in the link table.
     * Checks a transient to see if there's a stored list of un updated ids.
     * If there isn't, it checks the database directly
     * @return array $unprocessed_ids (All of the post ids that haven't been listed in the link table yet.)
     **/
    public static function get_all_unprocessed_link_post_ids(){
        global $wpdb;
        $stored_ids = get_transient('wpil_stored_unprocessed_link_ids');

        if ($stored_ids){
            $unprocessed_ids = $stored_ids;
        } else {
            $all_post_ids = self::get_all_post_ids();
            $all_processed_ids = $wpdb->get_col("SELECT DISTINCT post_id AS ID FROM {$wpdb->prefix}wpil_report_links");
            $unprocessed_ids = array_diff($all_post_ids, $all_processed_ids);
            set_transient('wpil_stored_unprocessed_link_ids', $unprocessed_ids, MINUTE_IN_SECONDS * 5);
            Wpil_Base::set_transient('wpil_stored_unprocessed_link_ids', $unprocessed_ids, MINUTE_IN_SECONDS * 5);
        }

        // and return the results of our efforts
        return $unprocessed_ids;
    }

    /**
     * Gets the total number of posts that are eligible to include in the link table.
     * This counts all post types selected in the LW settings, including categories.
     * @return int $all_post_count
     **/
    public static function get_total_post_count(){
        global $wpdb;

        if(isset(self::$all_post_count) && !empty(self::$all_post_count)){
            return self::$all_post_count;
        }else{

            $total = get_transient('wpil_total_process_post_count');

            if(empty($total)){
                // get all of the site's posts that are in our settings group
                $post_types = Wpil_Settings::getPostTypes();
                $post_type_replace_string = !empty($post_types) ? " AND post_type IN ('" . (implode("','", $post_types)) . "') " : "";
                $statuses_query = Wpil_Query::postStatuses();

                $post_count = $wpdb->get_var("SELECT COUNT(ID) FROM {$wpdb->posts} WHERE 1=1 {$post_type_replace_string} {$statuses_query}");
                // if term is a selected type
                if(!empty(Wpil_Settings::getTermTypes())){
                    // get all the site's categories that aren't empty
                    $taxonomies = Wpil_Settings::getTermTypes();
                    $cat_count = $wpdb->get_var("SELECT COUNT(DISTINCT term_id) FROM {$wpdb->term_taxonomy} WHERE `taxonomy`IN ('" . implode("', '", $taxonomies) . "')");
                }else{
                    $cat_count = 0;
                }

                // add the post count and term count together and return
                self::$all_post_count = ($post_count + $cat_count);

                set_transient('wpil_total_process_post_count', self::$all_post_count, MINUTE_IN_SECONDS * 60); // should be cleared when a new scan runs, so we can put a long time on this
            }else{
                self::$all_post_count = $total;
            }

            return self::$all_post_count;
        }
    }

    /**
     * Gets the PHP memory safe usage limit so we know when to quit processing.
     * Currently, the break point is 20mb short of the PHP memory limit.
     * 
     * Note "wp_is_ini_value_changeable" checks if the ini values are writable. It might be useful in the future
     **/
    public static function get_mem_break_point(){
        if(isset(self::$memory_break_point) && !empty(self::$memory_break_point)){
            return self::$memory_break_point;
        }else{
            $mem_limit = ini_get('memory_limit');

            // if the max memory has been set, and it's different than the ini's limit
            if('-1' !== $mem_limit && defined('WP_MAX_MEMORY_LIMIT') && !empty(WP_MAX_MEMORY_LIMIT) && WP_MAX_MEMORY_LIMIT !== $mem_limit){
                $mem_limit = wp_convert_hr_to_bytes(WP_MAX_MEMORY_LIMIT) > wp_convert_hr_to_bytes($mem_limit) ? WP_MAX_MEMORY_LIMIT : $mem_limit;
            }
            
            if(empty($mem_limit) || '-1' == $mem_limit){
                self::$memory_break_point = 'disabled';
                return self::$memory_break_point;
            }

            $mem_size = 0;
            switch(substr($mem_limit, -1)){
                case 'M': 
                case 'm': 
                    $mem_size = (int)$mem_limit * 1048576;
                    break;
                case 'K':
                case 'k':
                    $mem_size = (int)$mem_limit * 1024;
                    break;
                case 'G':
                case 'g':
                    $mem_size = (int)$mem_limit * 1073741824;
                    break;
                default: $mem_size = $mem_limit;
            }

            $mem_break_point = round(($mem_size - ($mem_size * 0.15))); // break point == (mem limit - 15%)
            
            if($mem_break_point < 0){
                self::$memory_break_point = 'disabled';
            }else{
                self::$memory_break_point = $mem_break_point;
            }

            return self::$memory_break_point;
        }
    }

    public static function get_loading_screen($screen = ''){
        switch($screen){
            case 'meta-loading-screen':
                ob_start();
                include WP_INTERNAL_LINKING_PLUGIN_DIR . 'templates/report_prepare_meta_processing.php';
                $return_screen = ob_get_clean();
            break;
            case 'link-loading-screen':
                ob_start();
                include WP_INTERNAL_LINKING_PLUGIN_DIR . 'templates/report_prepare_link_inserting_into_table.php';
                $return_screen = ob_get_clean();
            break;
            case 'post-loading-screen':
                ob_start();
                include WP_INTERNAL_LINKING_PLUGIN_DIR . 'templates/report_prepare_process_links.php';
                $return_screen = ob_get_clean();
            break;            
            default:
                $return_screen = '';
        }
        
        return $return_screen;
    }

    /**
     * Checks to see if the link table is created.
     **/
    public static function link_table_is_created(){
        global $wpdb;
        $links_table = $wpdb->prefix . "wpil_report_links";
        // check to see that the link table was successfully created
        $table = $wpdb->get_var("SHOW TABLES LIKE '$links_table'");
        if ($table != $links_table) {
            return false;
        } else {
            return true;
        }
    }
    
    /**
     * Gets the posts that haven't had their meta filled yet.
     **/
    public static function get_untagged_posts(){
        global $wpdb;
        $post_table  = $wpdb->prefix . "posts";
        $meta_table  = $wpdb->prefix . "postmeta";

        $args = array();
        $post_type_replace_string = '';
        $post_types = Wpil_Settings::getPostTypes();
        $type_count = (count($post_types) - 1);
        foreach($post_types as $key => $post_type){
            if(empty($post_type_replace_string)){
                $post_type_replace_string = ' AND ' . $post_table . '.post_type IN (';
            }

            $args[] = $post_type;
            if($key < $type_count){
                $post_type_replace_string .= '%s, ';
            }else{
                $post_type_replace_string .= '%s)';
            }
        }

        // First get all the site's posts
        $all_post_ids = self::get_all_post_ids();
        // Then get the ids of all the posts that have the processing flag
        $posts_with_flag = $wpdb->get_results("SELECT `post_id` FROM {$meta_table} WHERE `meta_key` = 'wpil_sync_report3' ORDER BY `post_id` ASC");

        // create a list of all posts that haven't had their meta filled yet.
        $all_post_ids = array_flip($all_post_ids);
        foreach($posts_with_flag as $flagged_post){
            $all_post_ids[$flagged_post->post_id] = false;
        }

        $unfilled_posts = array_flip(array_filter($all_post_ids, 'strlen'));

        return $unfilled_posts;
    }

    /**
     * Saves a user's report filtering suggestions to the user meta so we can have persistent report filtering.
     **/
    function ajax_save_user_filter_settings(){
        $user_id = get_current_user_id();
        if(isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], $user_id . 'wpil_filter_nonce') && !empty($user_id)){

            if(isset($_POST['setting_type']) && 'target_keywords' === $_POST['setting_type']){
                $keyword_post_type = (isset($_POST['post_type']) && !empty($_POST['post_type'])) ? sanitize_text_field($_POST['post_type']) : false;
                self::save_target_keyword_filtering($keyword_post_type);

            }else{
                $post_type = (isset($_POST['post_type']) && !empty($_POST['post_type'])) ? sanitize_text_field($_POST['post_type']) : false;
                $category = (isset($_POST['category']) && !empty($_POST['category'])) ? sanitize_text_field($_POST['category']) : false;
                self::save_link_report_filtering($post_type, $category);

            }
        }
    }

    public static function save_link_report_filtering($post_type = '', $category = ''){
        $user_id = get_current_user_id();
        $filter_settings = get_user_meta($user_id, 'wpil_filter_settings', true);

        // create the default settings for the user filters
        if(empty($filter_settings)){
            $filter_settings = array();
        }
        
        if(!isset($filter_settings['report'])){
            $filter_settings['report'] = array('post_type' => false, 'category' => false);
        }

        if(!empty($post_type)){
            $filter_settings['report']['post_type'] = $post_type;
        }else{
            $filter_settings['report']['post_type'] = false;
        }

        if(!empty($category)){
            $filter_settings['report']['category'] = $category;
        }else{
            $filter_settings['report']['category'] = false;
        }
        
        update_user_meta($user_id, 'wpil_filter_settings', $filter_settings);
    }

    public static function save_target_keyword_filtering($keyword_post_type){
        $user_id = get_current_user_id();
        $filter_settings = get_user_meta($user_id, 'wpil_filter_settings', true);

        // create the default settings for the user filters
        if(empty($filter_settings)){
            $filter_settings = array();
        }
        
        if(!isset($filter_settings['target_keywords'])){
            $filter_settings['target_keywords'] = array('keyword_post_type' => false);
        }

        if(!empty($keyword_post_type)){
            $filter_settings['target_keywords']['keyword_post_type'] = $keyword_post_type;
        }else{
            $filter_settings['target_keywords']['keyword_post_type'] = false;
        }

        update_user_meta($user_id, 'wpil_filter_settings', $filter_settings);
    }

    /**
     * Outputs some custom styling when specific report tabs
     **/
    public static function outputCustomTabStyles(){
        if(isset($_GET['type']) && $_GET['type'] === 'links'){
            ?>
            <style>
                #toplevel_page_link_whisper .wp-submenu li:nth-of-type(3) a{
                    color: #fff !important;
                    font-weight: 600;
                }
            </style>
            <?php
        }
    }

    /**
     * Resets the number of items to display in the report tables back to the default 20
     **/
    public static function reset_display_counts(){
        $user_id = get_current_user_id();
        $report_options = get_user_meta($user_id, 'report_options', true);

        if(!empty($report_options) && isset($report_options['per_page'])){
            $report_options['per_page'] = 20;
            update_user_meta($user_id, 'report_options', $report_options);
        }
    }

    /**
     * Gets the total number of columns to display on a table
     **/
    public static function get_report_dropdown_column_count(){
        $column_count = 0;
        if(isset($_GET['page']) && $_GET['page'] === 'link_whisper'){
            if($_GET['type'] && $_GET['type'] === 'links'){
                if(isset($_GET['orphaned']) || isset($_GET['link_relation'])){
                    $column_count = 1;
                }elseif(isset($_GET['link_density'])){
                    $column_count = 2;
                }else{
                    $column_count = 3;
                }
            }
        }

        return $column_count;
    }
}
