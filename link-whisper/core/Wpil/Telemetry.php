<?php

/**
 * User telemetry controller
 */

class Wpil_Telemetry
{
    static $current_events = array(
        1 => 'ai_live_download_started',
        2 => 'ai_all_data_cleared',
        3 => 'csv_export_activated',
        4 => 'broken_link_report_reset',
        5 => 'broken_link_url_updated',
        6 => 'broken_link_delete',
        7 => 'creating_autolinking_rule',
        8 => 'bulk_autolinking_rule_created',
        9 => 'delete_autolinking_rule',
        10 => 'autolinking_report_reset',
        11 => 'autolinking_insert_selected_links',
        12 => 'outbound_suggestion_link_add',
        13 => 'inbound_suggestion_link_add',
        14 => 'inserting_custom_link',
        15 => 'ignoring_link_from_suggestions',
        16 => 'delete_selected_links',
        17 => 'ignore_orphaned_post',
        18 => 'running_new_link_scan',
        19 => 'gsc_connection_authorized',
        20 => 'gsc_disconnected',
        21 => 'site_interlinked',
        22 => 'site_unlinked',
        23 => 'site_interlinking_all_post_download_complete',
        24 => 'sitemaps_generated',
        25 => 'custom_sitemap_created',
        26 => 'custom_sitemap_deleted',
        27 => 'custom_target_keyword_created',
        28 => 'custom_target_keyword_deleted',
        29 => 'update_selected_target_keywords',
        30 => 'running_new_target_keyword_scan',
        31 => 'url_changer_rule_create',
        32 => 'url_changer_rule_deleted',
        33 => 'url_changer_reset',
        34 => 'report_open_clicks',
        35 => 'report_open_detailed_clicks',
        36 => 'report_open_domains',
        37 => 'report_open_broken_links',
        38 => 'report_open_autolinking',
        39 => 'report_open_link_activity',
        40 => 'report_open_orphaned',
        41 => 'report_open_links',
        42 => 'report_open_sitemaps',
        43 => 'report_open_target_keywords',
        44 => 'report_open_url_changer',
        45 => 'refresh_auto_selected_related_posts_links',
        46 => 'refresh_all_related_posts_links',
        47 => 'update_selected_related_posts',
        48 => 'activated_ai_powered_suggestions',
        49 => 'deactivated_ai_powered_suggestions',
        50 => 'ran_installation_wizard',
        51 => 'report_open_link_density',
        52 => 'report_open_link_relation',
        53 => 'report_open_link_external_focus',
        54 => 'send_email_notification',
        55 => 'activated_email_notifications',
        56 => 'deactivated_email_notifications',
        57 => 'activated_remote_dashboard_sync',
        58 => 'deactivated_remote_dashboard_sync',
        59 => 'manually_synced_report_data',
        60 => 'report_open_dashboard',
        61 => 'outbound_quicklinks_add',
        62 => 'inbound_quicklinks_add',
        63 => 'linkwhisper_ai_authenticated',
        64 => 'linkwhisper_ai_disconnected',
        65 => 'linkwhisper_ai_plan_purchased',
        66 => 'linkwhisper_ai_plan_cancelled',
        67 => 'linkwhisper_ai_credits_purchased',
        68 => 'linkwhisper_ai_banner_opened',
        69 => 'linkwhisper_ai_banner_authed',
        70 => 'deactivated_tawkto_popup',
        71 => 'link_updated_from_report',
        72 => 'link_updated_from_domains_report',
        73 => 'link_updated_from_links_report',
        74 => 'outbound_links_report_add',
        75 => 'inbound_links_report_add',
        
        // Notification Hub Events
        76 => 'notification_hub_loaded',
        77 => 'notification_hub_notification_impression',
        78 => 'notification_hub_notification_clicked',
        
        // Popup Notification Events
        79 => 'popup_notification_impression',
        80 => 'popup_notification_cta_clicked',
        81 => 'popup_notification_dismissed',
        82 => 'popup_notification_auto_dismissed',
        
        // Tour Events
        83 => 'tour_started',
        84 => 'tour_step_viewed',
        85 => 'tour_step_completed',
        86 => 'tour_completed',
        87 => 'tour_dismissed',
        88 => 'tour_widget_expanded',
        89 => 'tour_widget_minimized',
        90 => 'tour_navigation_clicked',
        91 => 'tour_auto_started',
        92 => 'tour_reset',

        93 => 'linkwhisper_ai_banner_dismissed', // dismissed the 'sign up for ai' banner
        94 => 'report_open_anchor_length',
        95 => 'link_updated_from_anchor_length_report'
    );

    static $user_events = array(
        'broken_link_code_filter',
        'domains_show_untargeted',
        'domains_changed_attrs',
        'ai_sitemap_opened'
    );

    /**
     * Register services
     */
    public function register()
    {
        // AI
        add_action('wp_ajax_wpil_live_download_ai_data', array(__CLASS__, 'ajax_track_ajax'), 9);
        add_action('wp_ajax_wpil_clear_ai_data', array(__CLASS__, 'ajax_track_ajax'), 9);

        // BASE
        add_action('wp_ajax_wpil_csv_export', array(__CLASS__, 'ajax_track_ajax'), 9);
//        add_action('wp_ajax_wpil_save_domain_attributes', array(__CLASS__, 'ajax_track_ajax'), 9);

        // ClickTracker
        add_action('wp_ajax_wpil_clear_click_data', array(__CLASS__, 'ajax_track_ajax'), 9); // clear is for erasing all click data
        add_action('wp_ajax_wpil_delete_click_data', array(__CLASS__, 'ajax_track_ajax'), 9); // delete is for specific pieces of click data
        add_action('wp_ajax_wpil_delete_user_data', array(__CLASS__, 'ajax_track_ajax'), 9);

        // Error
        add_action('wp_ajax_wpil_error_reset_data', array(__CLASS__, 'ajax_track_ajax'), 9);
        add_action('wp_ajax_wpil_delete_error_links', array(__CLASS__, 'ajax_track_ajax'), 9);

        // Keyword
        add_action('wp_ajax_wpil_keyword_reset', array(__CLASS__, 'ajax_track_ajax'), 9);
        add_action('wp_ajax_wpil_insert_selected_keyword_links', array(__CLASS__, 'ajax_track_ajax'), 9);

        // Link
        add_action('wp_ajax_wpil_get_link_title', array(__CLASS__, 'ajax_track_ajax'), 9);
        add_action('wp_ajax_wpil_add_link_to_ignore', array(__CLASS__, 'ajax_track_ajax'), 9);

        // Post
        add_action('wp_ajax_wpil_ignore_orphaned_post', array(__CLASS__, 'ajax_track_ajax'), 9);

        // Report
        add_action('wp_ajax_reset_report_data', array(__CLASS__, 'ajax_track_ajax'), 9);

        // SiteConnector

        // Sitemap

        // TargetKeyword
        add_action('wp_ajax_wpil_target_keyword_reset', array(__CLASS__, 'ajax_track_ajax'), 9);
        add_action('wp_ajax_wpil_target_keyword_selected_update', array(__CLASS__, 'ajax_track_ajax'), 9);

        // URLChanger
        add_action('wp_ajax_wpil_url_changer_delete', array(__CLASS__, 'ajax_track_ajax'), 9);
        add_action('wp_ajax_wpil_url_changer_reset', array(__CLASS__, 'ajax_track_ajax'), 9);

        // Widgets
        add_action('wp_ajax_wpil_refresh_related_post_links', array(__CLASS__, 'ajax_track_ajax'), 9);
        add_action('wp_ajax_wpil_save_related_posts', array(__CLASS__, 'ajax_track_ajax'), 9);

        // cron
        add_action('admin_init', [__CLASS__, 'schedule_batch_process']);
        add_action('wpil_telemetry_cleanup_cron', [__CLASS__, 'perform_cron_cleanup_process']);

        // self
        add_action('wp_ajax_wpil_user_telemetry_notice_dismiss', array(__CLASS__, 'ajax_usage_notice_dismissed'));

        // notice
        add_action('wp_ajax_wpil_dismiss_telemetry_notice', array(__CLASS__, 'ajax_dismiss_dashboard_telemetry_notice'));

        // general UI
        add_action('wp_ajax_user_opened_ai_popup', array(__CLASS__, 'ajax_track_ajax')); // no need for priority setting since this is only called during telemtry events
        add_action('wp_ajax_user_dismissed_ai_popup', array(__CLASS__, 'ajax_track_ajax'), 9);

        // an event from our frontend system
        add_action('wp_ajax_wpil_log_event', array(__CLASS__, 'ajax_log_frontend_event'));
    }

    /**
     * Create database
     **/
    public static function prepare_table(){
        global $wpdb;

        $telemetry_table = $wpdb->prefix . "wpil_telemetry_log";

        // if the telemetry data table doesn't exist
        $telemetry_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$telemetry_table}'");
        if(empty($telemetry_tbl_exists)){
            $telemetry_data_table_query = "CREATE TABLE IF NOT EXISTS {$telemetry_table} (
                                            event_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                                            event_name varchar(191),
                                            event_data longtext,
                                            event_time bigint(20) UNSIGNED,
                                            user_id bigint(20) UNSIGNED,
                                            pinged tinyint(1) UNSIGNED DEFAULT 0,
                                            PRIMARY KEY (event_id),
                                            INDEX (event_name)
                                        )";
            // create DB table if it doesn't exist
            require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($telemetry_data_table_query);
        }
    }

    /**
     * 
     **/
    public static function schedule_batch_process(){
        if(!wp_get_schedule('wpil_telemetry_cleanup_cron')){
            wp_schedule_event(time(), 'daily', 'wpil_telemetry_cleanup_cron');
        }
    }

    public static function clear_batch_process_cron(){
        wp_unschedule_event(wp_next_scheduled('wpil_telemetry_process_cron'), 'wpil_telemetry_process_cron');
        wp_unschedule_event(wp_next_scheduled('wpil_telemetry_cleanup_cron'), 'wpil_telemetry_cleanup_cron');
    }

    /**
     * Checks to see if there's event data logged that can be sent
     **/
    public static function has_unpinged_event_data(){
        global $wpdb;
        $table = $wpdb->prefix . "wpil_telemetry_log";

        return !empty($wpdb->get_var("SELECT * FROM {$table} WHERE `pinged` < 1 LIMIT 1"));
    }

    /**
     * Pulls a batch of unpinged event data so we can send it home
     **/
    public static function get_unpinged_batch_data(){
        global $wpdb;
        $table = $wpdb->prefix . "wpil_telemetry_log";

        return $wpdb->get_results("SELECT * FROM {$table} WHERE `pinged` < 1 LIMIT 1000");
    }

    /**
     * Assembles the recorded event data into a package that we can process
     **/
    private static function assemble_data_packages($data){
        $packages = array();

        if(!empty($data)){
            $packages['site_url'] = home_url();
            foreach($data as $dat){
                $user = get_userdata($dat->user_id);
                if(empty($user)){
                    self::mark_event_as_pinged($dat->event_id);
                    continue;
                }

                $event_data = array(
                    'u' => $dat->user_id,
                    'n' => self::get_event_name_id($dat->event_name),
                    't' => $dat->event_time
                );

                $event_data = array_merge($event_data, Wpil_Toolbox::json_decompress($dat->event_data, true));
                $packages[] = $event_data;
                self::mark_event_as_pinged($dat->event_id);
            }
        }

        return $packages;
    }

    /**
     * 
     **/
    public static function get_event_name_id($event_name = ''){
        return (in_array($event_name, self::$current_events, true)) ? array_search($event_name, self::$current_events, true): 0;
    }

    /**
     * Marks a specific event as pinged so we can know that it's been processed
     **/
    public static function mark_event_as_pinged($event_id = 0){
        global $wpdb;
        $table = $wpdb->prefix . 'wpil_telemetry_log';

        if(empty($event_id) || !is_numeric($event_id)){
            return;
        }

        $wpdb->update($table, array('pinged' => 1), array('event_id' => $event_id));
    }

    /**
     * Cleans up old event data on a cron so we don't have too much build up
     **/
    public static function perform_cron_cleanup_process(){
        global $wpdb;
        $table = $wpdb->prefix . 'wpil_telemetry_log';
        $time = (time() - (DAY_IN_SECONDS * 14));

        $wpdb->query("DELETE FROM {$table} WHERE `event_time` < {$time} OR `pinged` > 0");
    }

    /**
     * Sends the telemetry data in for processing
     **/
    private static function send_data($data, $endpoint = ''){
        // not used
    }

    /**
     * Tracking all the AJAX THINGS!
     * 
     **/
    public static function ajax_track_ajax(){
        $current_hook = current_filter();
        $user_id = get_current_user_id();

        if(empty($user_id) || !is_admin()){
            return;
        }

        $event_name = '';
        $data = array();
        switch ($current_hook) {
            // AI
            case 'wp_ajax_wpil_live_download_ai_data':
                if(!isset($_POST['start_time']) || empty($_POST['start_time'])){
                    $event_name = 'ai_live_download_started';
                }
                break;
            case 'wp_ajax_wpil_clear_ai_data':
                $event_name = 'ai_all_data_cleared';
                break;
            // Export
            case 'wp_ajax_wpil_csv_export':
                if(isset($_POST['count']) && $_POST['count'] == 1){
                    $event_name = 'csv_export_activated';
                    $type = (isset($_POST['type']) && !empty($_POST['type'])) ? $_POST['type']: 'unknown';
                    $data = array('name' => 'csv_' . $type);
                }
                break;
            // Error
            case 'wp_ajax_wpil_error_reset_data':
                $event_name = 'broken_link_report_reset';
                break;
            case 'wp_ajax_wpil_delete_error_links':
                if(isset($_POST['links']) && !empty($_POST['links']) && is_array($_POST['links'])){
                    $event_name = 'broken_link_delete';
                    $data = array('count' => count($_POST['links']));
                }
                break;
            // Keyword (Autolinking)
            case 'wp_ajax_wpil_keyword_reset':
                if(isset($_POST['count']) && !empty($_POST['count']) && $_POST['count'] == 1){
                    $event_name = 'autolinking_report_reset';
                }
                break;
            case 'wp_ajax_wpil_insert_selected_keyword_links':
                if(isset($_POST['link_ids']) && !empty($_POST['link_ids'])){
                    $event_name = 'autolinking_insert_selected_links';
                    $data = array('count' => count($_POST['link_ids']));
                }
                break;
            // Link
            case 'wp_ajax_wpil_get_link_title':
                $event_name = 'inserting_custom_link';
                break;
            case 'wp_ajax_wpil_add_link_to_ignore':
                $event_name = 'ignoring_link_from_suggestions';
                break;
            // Post
            case 'wp_ajax_wpil_ignore_orphaned_post':
                $event_name = 'ignore_orphaned_post';
                break; 
            // Report
            case 'wp_ajax_reset_report_data':
                if(isset($_POST['clear_data']) && 'true' === $_POST['clear_data']){
                    $event_name = 'running_new_link_scan';
                }
                break; 
            // Target Keywords
            case 'wp_ajax_wpil_target_keyword_reset':
                if(isset($_POST['reset']) && 'true' === $_POST['reset']){
                    $event_name = 'running_new_target_keyword_scan';
                }
                break;
            case 'wp_ajax_wpil_target_keyword_selected_update':
                if(isset($_POST['selected']) && !empty($_POST['selected'])){
                    $event_name = 'update_selected_target_keywords';
                    $data = array('count' => count($_POST['selected']));
                }
                break;
            // URLChanger
            case 'wp_ajax_wpil_url_changer_delete':
                if(isset($_POST['id']) && !empty($_POST['id'])){
                    $event_name = 'url_changer_rule_deleted';
                }
                break;
            case 'wp_ajax_wpil_url_changer_reset':
                if(isset($_POST['count']) && $_POST['count'] == 1){
                    $event_name = 'url_changer_reset';
                }
                break;
            // Widgets
            case 'wp_ajax_wpil_refresh_related_post_links':
                if(isset($_POST['initial']) && !empty($_POST['initial']) && $_POST['initial'] === 'true'){
                    if((isset($_POST['context']) && !empty($_POST['context']) && $_POST['context'] === 'auto')){
                        $event_name = 'refresh_auto_selected_related_posts_links';
                    }else{
                        $event_name = 'refresh_all_related_posts_links';
                    }
                }
                break;
            case 'wp_ajax_wpil_save_related_posts': // saving from the post edit screen
                if(isset($_POST['post_id']) && !empty($_POST['post_id'])){
                    $event_name = 'update_selected_related_posts';
                }
                break;
            // General UI
            case 'wp_ajax_user_opened_ai_popup':
                $event_name = 'linkwhisper_ai_banner_opened';
            case 'wp_ajax_user_dismissed_ai_popup':
                $event_name = 'linkwhisper_ai_banner_dismissed';
            default:
                // something happened, but we don't know what it was!
                break;
        }

        if(!empty($event_name)){
            self::log_event($event_name, $data);
        }
    }

    /**
     * Logs a trackable event with ajax
     **/
    public static function ajax_log_frontend_event(){
        Wpil_Base::verify_nonce('wpil-telemetry-nonce');

        if(isset($_POST['event_name']) && !empty($_POST['event_name']) && self::confirm_event_name($_POST['event_name'])){
            $data = isset($_POST['event_data']) && !empty($_POST['event_data']) ? $_POST['event_data']: [];
            self::log_event($_POST['event_name'], $data);
        }
    }

    public static function log_event($event_name = '', $data = array()){
        global $wpdb;
        $table = $wpdb->prefix . "wpil_telemetry_log";

        if(apply_filters('wpil_disable_telemetry', false) || false === self::confirm_event_name($event_name)){
            return;
        }

        // note the relevent event data in the user's action log
        self::log_event_for_user($event_name);

        // if telemetry isn't active or this is only a user-specific event
        if(!Wpil_Settings::get_if_telemetry_active() || in_array($event_name, self::$user_events)){
            // exit now
//            return;
        }

        $data = self::structure_data($data);

        $wpdb->insert($table, [
            'event_name' => $event_name,
            'event_data' => Wpil_Toolbox::json_compress($data),
            'event_time' => time(),
            'user_id' => get_current_user_id()
        ]);
    }

    /**
     * Makes sure the event given is one that we support
     **/
    private static function confirm_event_name($event_name = ''){
        if(empty($event_name)){
            return false;
        }

        if(in_array($event_name, self::$current_events, true) || in_array($event_name, self::$user_events, true)){
            return true;
        }

        return false;
    }

    public static function structure_data($data = array()){
        $plugin_data = get_plugin_data(WP_INTERNAL_LINKING_PLUGIN_DIR . 'link-whisper.php');
        $structured_data = array(
            'version' => (isset($plugin_data['Version'])) ? sanitize_text_field($plugin_data['Version']): '0.0.1',
//            'timezone' => wp_timezone_string(),
//            'site_url' => trim(trim(get_site_url(), '/'))
        );

        if(!empty($data) && is_string($data)){
            $data = json_decode(wp_unslash($data), true);
        }

        if(empty($data) || !is_array($data)){
            return $structured_data;
        }

        $keys = array(
            'count' => 'int',
            'link_count' => 'int',
            'post_count' => 'int',
            'bulk_autolinking_rules_created' => 'int',
            'target_site' => 'url',
            'name' => 'string', // string assumes plaintext and no HTML
            'token_revoked' => 'bool',
            'email_id' => 'string'
        );

        foreach($data as $key => $dat){
            if(isset($keys[$key])){
                switch ($keys[$key]) {
                    case 'int':
                        $structured_data[$key] = (int) $dat;
                        break;
                    case 'url':
                        $structured_data[$key] = esc_url_raw($dat);
                        break;
                    case 'string':
                        $structured_data[$key] = sanitize_text_field($dat);
                        break;
                    case 'bool':
                        $structured_data[$key] = (bool) $dat;
                        break;
                }
            }else{
                // we'll just assume that the input can be sanitized by a string check 
                $structured_data[$key] = sanitize_text_field($dat);
            }
        }

        return $structured_data;
    }

    public static function general_status(){
        $status = array(
            'has_custom_sitemaps' => (!empty(Wpil_Sitemap::has_sitemap('custom_sitemap'))),
            'has_gsc_connected' => !empty(get_option("wpil_gsc_app_authorized", false)),
            'using_target_keywords' => Wpil_TargetKeyword::has_keywords_stored(),
            'has_run_target_keyword_process' => !empty(get_option('wpil_keyword_reset_last_run_time', false))
        );

        return $status;
    }

    public static function check_usage_notices(){

    }
    
    public static function get_available_usage_notices(){
        // create the list of notices and their associated event no-show triggers
        $notices = array(
            'used_autolinking'              => array(
                                            'notice_text' => __('Did you know Link Whisper can automatically create links for you using keywords?', 'wpil'),
                                            'element_target' => 'li#toplevel_page_link_whisper .wp-submenu a[href="admin.php?page=link_whisper_keywords"]',
                                            'hide_triggers' => array(
                                                'creating_autolinking_rule',
                                                'bulk_autolinking_rule_created',
                                                'delete_autolinking_rule',
                                                'autolinking_report_reset',
                                                'autolinking_insert_selected_links',
                                            )),
            'used_url_changer'              => array(
                                            'notice_text' => __('Did you know that Link Whisper can update the URLs of old links? No need for URL redirects!', 'wpil'),
                                            'element_target' => 'li#toplevel_page_link_whisper .wp-submenu a[href="admin.php?page=link_whisper_url_changer"]',
                                            'hide_triggers' => array(
                                                'url_changer_rule_create',
                                                'url_changer_rule_deleted',
                                                'url_changer_reset',
                                            )),
            'used_target_keyword_report'    => array(
                                            'notice_text' => __('Did you know that you can get better suggestions by telling Link Whisper what keywords are important for your posts?', 'wpil'),
                                            'element_target' => 'li#toplevel_page_link_whisper .wp-submenu a[href="admin.php?page=link_whisper_target_keywords"]',
                                            'hide_triggers' => array(
                                                'general_status' => 'has_run_target_keyword_process'
                                            )),
            'used_link_delete'              => array(
                                            'notice_text' => __('', 'wpil'), // TODO: setup when there's a feature to detect and open a viable dropdown
                                            'element_target' => '.wpil_link_select',
                                            'hide_triggers' => array(
                                                'delete_selected_links',
                                            )),
            'used_inbound_links'            => array(
                                            'notice_text' => __('You can quickly point links to a post by clicking on the "Add" button in this column.', 'wpil'),
                                            'element_target' => '.add-internal-links',
                                            'hide_triggers' => array(
                                                'inbound_suggestion_link_add',
                                            )),
            'used_outbound_links'           => array(
                                            'notice_text' => __('You can quickly add links to a post by clicking on the "Add" button in this column.', 'wpil'),
                                            'element_target' => '.add-outbound-internal-links',
                                            'hide_triggers' => array(
                                                'outbound_suggestion_link_add',
                                                'inserting_custom_link',
                                                'ignoring_link_from_suggestions',
                                            )),
            // reports
            'used_links'                    => array(
                                            'notice_text' => __('The Links Report shows you all the existing links on your site, and allows you to quickly create new ones!', 'wpil'),
                                            'element_target' => '#wpil-report-links-tab',
                                            'hide_triggers' => array(
                                                'report_open_links',
                                            )),
            'used_domains'                  => array(
                                            'notice_text' => __('The Domains Report shows you what domains you\'re linking to, and helps you quickly change the attributes for existing links!', 'wpil'),
                                            'element_target' => '#wpil-report-domains-tab',
                                            'hide_triggers' => array(
                                                'report_open_domains',
                                            )),
            'used_clicks'                   => array(
                                            'notice_text' => __('The Clicks Report shows you what post\'s links are getting the most clicks!', 'wpil'),
                                            'element_target' => '#wpil-report-clicks-tab',
                                            'hide_triggers' => array(
                                                'report_open_clicks',
                                            )),
            'used_detailed_clicks'          => array(
                                            'notice_text' => __('If you want to see what links were clicked, open the dropdown for a post and then click on the "View Detailed Click Report" button!', 'wpil'),
                                            'element_target' => '.wpil-collapsible-has-data',
                                            'hide_triggers' => array(
                                                'report_open_detailed_clicks',
                                            )),
            'used_broken_links'             => array(
                                            'notice_text' => __('The Broken Links Report tells you what links and videos are broken on your site!', 'wpil'),
                                            'element_target' => '#wpil-report-broken-links-tab',
                                            'hide_triggers' => array(
                                                'report_open_broken_links',
                                            )),
            'used_visual_maps'              => array(
                                            'notice_text' => __('The Visual Sitemaps show you how all your posts are linked to each other and the broader web.', 'wpil'),
                                            'element_target' => '#wpil-report-sitemaps-tab',
                                            'hide_triggers' => array(
                                                'report_open_sitemaps',
                                            )),
            // report features
            'used_domains_attrs'            => array(
                                            'notice_text' => __('The "Attributes" section allows you to do things like make all links going to a domain "nofollow", or set them as "sponsored", or have them all open in new tabs.', 'wpil'),
                                            'element_target' => 'table thead #attributes',
                                            'hide_triggers' => array(
                                                'general_status' => 'has_custom_domain_attrs'
                                            )),
            'used_domains_untargeted'       => array(
                                            'notice_text' => __('Want to see all the internal links that aren\'t pointing to a published post? Use this when searching!', 'wpil'),
                                            'element_target' => '#wpil-domain-show-untargeted',
                                            'hide_triggers' => array(
                                                'domains_show_untargeted'
                                            )),
            'used_broken_links_codes'       => array(
                                            'notice_text' => __('Want to see specific types of broken links, or see less common ones that are normally hidden?', 'wpil') . '<br><br>' . __('Use this filter to tell Link Whisper what to show you!', 'wpil'),
                                            'element_target' => '#error_table_code_filter .codes',
                                            'hide_triggers' => array(
                                                'broken_link_code_filter',
                                            )),
            'used_broken_links_edit_url'    => array(
                                            'notice_text' => __('Did you know that you can edit broken links to update their URLs without deleting them?', 'wpil'),
                                            'element_target' => '.wpil_edit_link',
                                            'hide_triggers' => array(
                                                'broken_link_url_updated',
                                            )),
            'used_broken_links_delete'      => array(
                                            'notice_text' => __('You can quickly delete individual broken links by clicking it\'s "X" button!', 'wpil'),
                                            'element_target' => '.wpil_link_delete',
                                            'hide_triggers' => array(
                                                'broken_link_delete',
                                            )),
            'used_ai_sitemap'               => array(
                                            'notice_text' => __('', 'wpil'),
                                            'hide_triggers' => array(
                                                'ai_sitemap_opened' // TODO: setup AJAX caller for htis
                                            )),
            'used_custom_sitemaps'          => array(
                                            'notice_text' => __('Did you know that you can create your own custom sitemaps via CSV?', 'wpil'),
                                            'element_target' => '.wpil-sitemap-manage-map',
                                            'hide_triggers' => array(
                                                'general_status' => 'has_custom_sitemaps'
                                            )),
            // general features
            'used_gsc'                      => array(
                                            'notice_text' => __('Did you know that you can connect Link Whisper to Google Search Console to pull in more keywords?', 'wpil') . '<br><br>' . __('You can', 'wpil') . ' <a href="https://linkwhisper.com/knowledge-base/how-to-integrate-with-google-search-console/" style="color:#00ffec; font-weight:bold; text-decoration:underline;">' . __('learn more here', 'wpil') . ' >>></a>',
                                            'element_target' => 'table.link-whisper_page_link_whisper_target_keywords thead .column-word_cloud',
                                            'hide_triggers' => array(
                                                'general_status' => 'has_gsc_connected'
                                            )),
            'used_target_keywords'          => array(
                                            'notice_text' => __('', 'wpil'),
                                            'element_target' => '',
                                            'hide_triggers' => array(
                                                'general_status' => 'using_target_keywords'
                                            )),
            'used_target_keyword_refresh'   => array(
                                            'notice_text' => __('Clicking on the "Refresh Target Keywords" button will import any missing target keywords from SEO plugins.', 'wpil'),
                                            'element_target' => '#wpil_target_keyword_reset_button',
                                            'hide_triggers' => array(
                                                'general_status' => 'has_run_target_keyword_process'
                                            )),
        );

        return $notices;
    }

    /**
     * User
     **/

    /**
     * Logs the usage notices that the user wants to hide.
     **/
    public static function ajax_usage_notice_dismissed(){
        Wpil_Base::verify_nonce('wpil-telemetry-notice-dismiss');

        if(isset($_POST['notice_id']) && !empty($_POST['notice_id']) && isset(self::get_available_usage_notices()[$_POST['notice_id']])){
            $dismissed_notices = self::get_dismissed_usage_notices();
            $dismissed_notices[] = $_POST['notice_id'];
            update_user_meta(get_current_user_id(), 'wpil_telemetry_user_event_dismissed_notices', $dismissed_notices);
        }
    }
    
    /**
     * Gets the list of dismissed usage notices
     **/
    public static function get_dismissed_usage_notices(){
        $dismissed_notices = get_user_meta(get_current_user_id(), 'wpil_telemetry_user_event_dismissed_notices', true);
        $dismissed_notices = (empty($dismissed_notices) || !is_array($dismissed_notices)) ? array(): $dismissed_notices;
        return $dismissed_notices;
    }

    /**
     * Logs an event for the current user so we can display user-specific helps and tooltips
     **/
    public static function log_event_for_user($event_name = ''){
        $events = self::get_user_event_data();
        
        if(!isset($events[$event_name])){
            $events[$event_name] = array();
        }

        if(!isset($events[$event_name]['count']) || empty($events[$event_name]['count'])){
            $events[$event_name]['count'] = 0;
            $events[$event_name]['date'] = 0;
        }

        $events[$event_name]['count'] += 1;
        $events[$event_name]['date'] = time();
    }

    /**
     * 
     **/
    public static function get_user_event_data(){
        $log_data = get_user_meta(get_current_user_id(), 'wpil_telemetry_user_event_log', true); // "telemetry" isn't accurate because the data is only used on the site, but for consistency we'll call it that
        $last_notice_displayed = (int) get_user_meta(get_current_user_id(), 'wpil_telemetry_user_event_notice_last_display_date', true);
        $user_events = 0;
        $first_event = time();
        $most_recent_event = 0;

        if(empty($log_data)){
            $log_data = array();
        }
        $log_data = array();
        // make sure that we have all the current events registered
        foreach(self::$current_events as $event){
            if(!isset($log_data[$event])){
                $log_data[$event] = array(
                    'count' => 0,
                    'date' => 0
                );
            }else{
                $user_events += (int) $log_data[$event]['count'];
                if($most_recent_event < $log_data[$event]['date']){
                    $most_recent_event = $log_data[$event]['date'];
                }

                if($first_event > $log_data[$event]['date']){
                    $first_event = $log_data[$event]['date'];
                }
            }
        }

        $log_data['event_count'] = $user_events;
        $log_data['first_event_date'] = $first_event;
        $log_data['latest_event_date'] = $most_recent_event;
        $log_data['last_notice_displayed'] = $last_notice_displayed;

        return $log_data;
    }

    /**
     * Notes the time when a notice has been shown to the user
     **/
    public static function log_user_event_notice_time(){
        update_user_meta(get_current_user_id(), 'wpil_telemetry_user_event_notice_last_display_date', time());
    }

    /**
     * Determines what and if a notice should be shown to the current user
     * @param string|array $possible_notices
     **/
    public static function determine_user_notice($possible_notices = array()){
        $event_record = self::get_user_event_data();
        $notice = array();

        if( $event_record['event_count'] < 15 || 
            empty($possible_notices) || 
            ((int) $event_record['first_event_date'] + (DAY_IN_SECONDS * 21)) > time() ||
            ($event_record['last_notice_displayed'] + DAY_IN_SECONDS) > time())
        {
            return $notice;
        }

        if(isset($possible_notices[0])){
            $possible_notices = array_flip($possible_notices);
        }

        $status = self::general_status();
        $search_notices = array_intersect_key(self::get_available_usage_notices(), $possible_notices);

        if(empty($search_notices)){
            return $notice;
        }

        $display_notices = array();
        foreach($search_notices as $notice_name => $notice_data){
            $log_notice = true;
            foreach($notice_data['hide_triggers'] as $ind => $trigger){
                if( ($ind === 'general_status' && isset($status[$trigger]) && $status[$trigger]) ||
                    isset($event_record[$trigger]) && $event_record[$trigger]['count'] > 0
                ){
                    $log_notice = false;
                }
            }

            if($log_notice){
                $display_notices[] = $notice_name;
            }

        }

        $dismissed_notices = self::get_dismissed_usage_notices();
        if(!empty($dismissed_notices)){
            $display_notices = array_diff($display_notices, $dismissed_notices);
        }

        if(!empty($display_notices)){
            // now randomly select the notice that we're going to show to the user
            shuffle($display_notices);
            $notice = reset($display_notices);
        }

        return $notice;
    }

    /**
     * Assembles the information for displaying the desired notice
     **/
    public static function create_usage_notice_information($possible_notices = array()){
        $information = array();
        $notice = self::determine_user_notice($possible_notices);

        if(!empty($notice)){
            $left_align = array('used_autolinking', 'used_url_changer', 'used_target_keyword_report');
            $position = (in_array($notice, $left_align)) ? 'left': 'top';
            $right_align = array('used_broken_links_delete', 'used_target_keyword_refresh');
            $position = (in_array($notice, $right_align)) ? 'right': 'top';
            $notices = self::get_available_usage_notices();
            $content = 
            '<div style="display:flex; flex-direction:column; width: 200px; padding: 0 0 5px;">
                <div style="display: flex; margin: 0 0 10px 0;">
                    <span style="font-size: 18px;font-weight: 500;margin-left: auto;">Link Whisper Tip</span>
                    <span class="dashicons dashicons-no wpil-telemetry-notice-dismiss-button"></span>
                </div>
                <div>'.$notices[$notice]['notice_text'].'</div>
            </div>';
            $information = array(
                'notice' => $notice,
                'tooltipContent' => $content,
                'elementTarget' => $notices[$notice]['element_target'],
                'noticeNonce' => wp_create_nonce(get_current_user_id() . 'wpil-telemetry-notice-dismiss'),
                'position' => $position
            );
        }

        return $information;
    }


    /**
     * Storage
     * 
     */

    /**
     * In plugin notices
     **/

    /**
     * Permenently dismisses the telemetry notice in the Dashboard telling the user that the telemetry system can be disabled
     */
    public static function ajax_dismiss_dashboard_telemetry_notice(){
        update_option('wpil_has_dismissed_telemetry_notice', 1);
    }

    /**
     * Remote contact
     * 
     **/

    /**
     * Data clearing
     * 
     **/

}

?>