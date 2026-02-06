<?php

/**
 * Base controller
 */
class Wpil_Base
{
    public static $report_menu;
    public static $action_tracker = array();

    /**
     * Register services
     */
    public function register()
    {
        add_action('admin_init', [$this, 'init']);
        add_action('admin_menu', [$this, 'addMenu']);
        add_action('wp_dashboard_setup', [__CLASS__, 'register_link_health_widget']);
        add_action('add_meta_boxes', [$this, 'addMetaBoxes']);
        add_action('admin_enqueue_scripts', [$this, 'addScripts']);
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_frontend_scripts'));
        add_action('plugin_action_links_' . WPIL_PLUGIN_NAME, [$this, 'showSettingsLink']);
        add_action('admin_notices', [$this, 'addEmailSignupNotice'], 7);
        add_action('admin_notices', [$this, 'add_notice_for_review'], 20);
        add_action('upgrader_process_complete', [$this, 'upgrade_complete'], 10, 2);
        add_action('wp_ajax_dismiss_email_offer_notice', [$this, 'ajax_dismiss_email_offer_notice']);
        add_action('wp_ajax_signed_up_email_offer_notice', [$this, 'ajax_signed_up_email_offer_notice']);
        add_action('wp_ajax_dismiss_premium_notice', [$this, 'ajax_dismiss_premium_notice']);
        add_action('wp_ajax_dismiss_review_notice', [$this, 'ajax_dismiss_review_notice']);
        add_action('wp_ajax_perm_dismiss_review_notice', [$this, 'ajax_perm_dismiss_review_notice']);
        add_action('wp_ajax_get_post_suggestions', ['Wpil_Suggestion','ajax_get_post_suggestions']);
        add_action('wp_ajax_update_suggestion_display', ['Wpil_Suggestion','ajax_update_suggestion_display']);
        add_action('wp_ajax_wpil_csv_export', ['Wpil_Export','ajax_csv']);
//        add_action('wp_ajax_wpil_export_sitemap_data_for_support', ['Wpil_Export','ajax_export_sitemap_support']);
        add_action('wp_ajax_wpil_export_suggestion_data', ['Wpil_Export','ajax_export_suggestion_data']);
        add_action('wp_ajax_wpil_bulk_keyword_export', ['Wpil_Export','ajax_export_autolink_rule_data']);
        add_action('wp_ajax_wpil_clear_gsc_app_credentials', ['Wpil_SearchConsole','ajax_clear_custom_auth_config']);
        add_action('wp_ajax_wpil_gsc_deactivate_app', ['Wpil_SearchConsole','ajax_disconnect']);
        add_action('wp_ajax_wpil_disconnect_from_ai_subscription', ['Wpil_Settings', 'ajax_disconnect_ai_subscription']);
        add_action('wp_ajax_wpil_save_animation_load_status', array('Wpil_Suggestion', 'ajax_save_animation_load_status'));
        add_action('wp_ajax_wpil_set_multi_link_in_sentence_editor', array('Wpil_Suggestion', 'ajax_set_allow_multiple_sentence_links'));
        add_action('wp_ajax_wpil_term_search', array(__CLASS__, 'ajax_term_search'));
        add_action('wp_ajax_wpil_post_search', array(__CLASS__, 'ajax_post_search'));
        add_action('wp_ajax_wpil_hide_explain_page', array(__CLASS__, 'ajax_hide_explain_page'));
        add_action('wp_ajax_wpil_set_use_ai_suggestions', array('Wpil_Settings', 'ajax_set_ai_suggestions_use'));
        add_action('wp_ajax_wpil_wizard_save_settings', array(__CLASS__, 'ajax_save_wizard_settings'));
        add_action('wp_ajax_wpil_wizard_save_oai_key', array(__CLASS__, 'ajax_wizard_save_oai_key'));
        add_action('wp_ajax_wpil_clear_process_tracker', array(__CLASS__, 'ajax_clear_process_tracker'));
        add_action('wp_ajax_wpil_has_run_wizard', array(__CLASS__, 'ajax_has_run_wizard'));
        add_action('wp_ajax_wpil_get_dashboard_scan_loading_data', array('Wpil_Wizard', 'ajax_pull_loading_progress_for_dashboard'));
        add_action('wp_ajax_wpil_wizard_set_completion_flag', array(__CLASS__, 'ajax_set_processing_complete_flag'));
        add_action('wp_ajax_wpil_run_autolink_insert_search', array(__CLASS__, 'ajax_get_wizard_insert_count'));
        add_action('wp_ajax_user_dismissed_ai_popup', array(__CLASS__, 'ajax_dismiss_ai_popup_banner'), 9);
        add_action('wp_ajax_wpil_update_expanded_details_toggle', array(__CLASS__, 'ajax_update_expanded_details_toggle'), 9);
        /*add_filter('the_content', array(__CLASS__, 'remove_link_whisper_attrs'));
        add_filter('the_content', array(__CLASS__, 'add_link_attrs'));
        add_filter('the_content', array(__CLASS__, 'add_link_icons'), 100, 1);*/
        foreach(Wpil_Settings::getPostTypes() as $post_type){
            add_filter( "manage_{$post_type}_posts_columns", array(__CLASS__, 'add_columns'), 11 );
            add_action( "manage_{$post_type}_posts_custom_column", array(__CLASS__, 'columns_contents'), 11, 2);
        }

        foreach(Wpil_Settings::getTermTypes() as $term_type){
            add_filter($term_type . '_row_actions', array(__CLASS__, 'modify_list_row_actions'), 10, 2); // we can only add the row actions. There's no modifying of the columns...
        }
    }

    /**
     * Initial function
     */
    function init()
    {
        $capability = apply_filters('wpil_filter_main_permission_check', 'manage_categories', self::get_current_page());
        if (!current_user_can($capability)) {
            return;
        }

        $clear_exports = get_transient('wpil_clear_exports_folder');
        if(!empty($clear_exports) && time() > (int)$clear_exports){
            Wpil_Export::clear_exports();
            delete_transient('wpil_clear_exports_folder');
        }

        $clear_exports = get_transient('wpil_clear_exports_folder');
        if(!empty($clear_exports) && time() > (int)$clear_exports){
            Wpil_Export::clear_exports();
            delete_transient('wpil_clear_exports_folder');
        }

        $post = self::getPost();

        if (!empty($_GET['csv_export'])) {
            Wpil_Export::csv();
        }

        if (!empty($_GET['area'])) {
            switch ($_GET['area']) {
                case 'wpil_export':
                    Wpil_Export::getInstance()->export($post);
                    break;
                case 'wpil_excel_export':
                    $post = self::getPost();
                    if (!empty($post)) {
                        try {
                            Wpil_Excel::exportPost($post);
                        } catch (Throwable $t) {
                        } catch (Exception $e) {
                        }
                    }
                    break;
            }
        }

        if (!empty($_POST['hidden_action'])) {
            switch ($_POST['hidden_action']) {
                case 'wpil_save_settings':
                    Wpil_Settings::save();
                    break;
            }
        }

        // if we're on a link whisper page
        if(isset($_GET['page']) && (false !== strpos($_GET['page'], 'link_whisper'))){
            // do a version check
            $version = get_option('wpil_version_check_update', WPIL_PLUGIN_OLD_VERSION_NUMBER);
            // if the plugin update check hasn't run yet
            if($version < WPIL_PLUGIN_VERSION_NUMBER){
                // create any tables that need creating
                self::createDatabaseTables();
                // and make sure the existing tables are up to date
                self::updateTables();
                // note the updated status
                update_option('wpil_version_check_update', WPIL_PLUGIN_VERSION_NUMBER);
            }
        }


        //add screen options
        add_action("load-" . self::$report_menu, function () {
            add_screen_option( 'report_options', array(
                'option' => 'report_options',
            ) );
        });
    }

    /**
     * This function is used for adding menu and submenus
     *
     *
     * @return  void
     */
    public function addMenu()
    {
        $capability = apply_filters('wpil_filter_main_permission_check', 'manage_categories', self::get_current_page());
        if (!current_user_can($capability)) {
            return;
        }

        if(!WPIL_STATUS_HAS_RUN_SCAN && !Wpil_Settings::has_run_wizard()){
            add_menu_page(
                __('Link Whisper', 'wpil'),
                __('Link Whisper', 'wpil'),
                'manage_categories',
                'link_whisper_wizard',
                [Wpil_Wizard::class, 'init'],
                plugin_dir_url(__DIR__).'../images/lw-icon-16x16.png'
            );
           return;
        }

        add_menu_page(
            'Link Whisper',
            'Link Whisper',
            'edit_posts',
            'link_whisper',
            [Wpil_Report::class, 'init'],
            plugin_dir_url(__DIR__). '../images/lw-icon-16x16.png'
        );

        if(WPIL_STATUS_HAS_RUN_SCAN){
            $page_title = __('Internal Links Report', 'wpil');
            $menu_title = __('Reports', 'wpil');

            self::$report_menu = add_submenu_page(
                'link_whisper',
                $page_title,
                $menu_title,
                'edit_posts',
                'link_whisper',
                [Wpil_Report::class, 'init']
            );
        }

        // hide the first item because that's just hte page title
        add_action('admin_head', function() {
            echo '<style>
                #toplevel_page_link_whisper .wp-first-item{
                    display: none;
                }
            </style>';
        });

        $menu_list = array(
//            'link_whisper_dashboard', // we'll always have the main report page since we need to stick the reports to something
            'link_whisper_target_keywords', //
            'link_whisper_settings', //
            'link_whisper_wizard',
            'link_whisper_ai_subscription'
        );

        $menu_list = apply_filters('wpil_filter_menu_listings', $menu_list);

        self::$report_menu = add_submenu_page(
            'link_whisper',
            'Internal Links Report',
            'Reports',
            'edit_posts',
            'link_whisper',
            [Wpil_Report::class, 'init']
        );

        if(in_array('link_whisper_wizard', $menu_list, true)){
            add_submenu_page(
                'link_whisper',
                __('One Click Setup', 'wpil'),
                __('One Click Setup', 'wpil'),
                'manage_categories',
                'link_whisper_wizard',
                [Wpil_Wizard::class, 'init']
            );
        }

        if(in_array('link_whisper_ai_subscription', $menu_list, true) && current_user_can('install_plugins')){
            add_submenu_page(
                'link_whisper',
                __('AI Subscription', 'wpil'),
                __('AI Subscription', 'wpil'),
                'manage_categories',
                'link_whisper_ai_subscription',
                [Wpil_Settings::class, 'ai_init']
            );
        }

        if(in_array('link_whisper_target_keywords', $menu_list, true)){
            $target_keywords = add_submenu_page(
                'link_whisper',
                __('Target Keywords', 'wpil'),
                __('Target Keywords', 'wpil'),
                'manage_categories',
                'link_whisper_target_keywords',
                [Wpil_TargetKeyword::class, 'init']
            );

            //add target keyword screen options
            add_action("load-" . $target_keywords, function () {
                add_screen_option( 'target_keyword_options', array(
                    'option' => 'target_keyword_options',
                ) );
            });
        }

        if(in_array('link_whisper_settings', $menu_list, true)){
            add_submenu_page(
                'link_whisper',
                __('Settings', 'wpil'),
                __('Settings', 'wpil'),
                'manage_categories',
                'link_whisper_settings',
                [Wpil_Settings::class, 'init']
            );
        }

        add_submenu_page(
            'link_whisper',
            'Premium',
            '<a class="link-whisper-get-premium-link" href="' . WPIL_STORE_URL . '/upgrade-offer/" target="blank">Get Premium <span style="font-size: 16px; margin: 2px 0 -2px 0;" class="dashicons dashicons-admin-links"></span></a>',
            'manage_categories',
            WPIL_STORE_URL
        );
    }

    public static function register_link_health_widget(){
        if( !defined('WPIL_STATUS_HAS_RUN_SCAN') || !WPIL_STATUS_HAS_RUN_SCAN
        ){
            // exist
            return;
        }
        wp_add_dashboard_widget(
            'wpil_link_health_widget',
            __('Link Whisper Site Health Report', 'wpil'),
            [__CLASS__, 'render_link_health_widget']
        );
    }

    public static function render_link_health_widget(){

        $rows = self::get_dashboard_widget_rows();
        $logo = plugin_dir_url(__DIR__).'../images/lw-icon.png'
        ?>
        <style>
            #lw-digest-widget .lw-header { display:flex; gap:16px; align-items:flex-start; margin-bottom:14px; }
            #lw-digest-widget .lw-logo { width:36px; height:36px; object-fit:contain; margin-top:2px; }
            #lw-digest-widget .lw-title { margin:0 0 2px; font-size:18px; font-weight:600; }
            #lw-digest-widget .lw-subtitle { margin:0; color:#6b7280; }
            #lw-digest-widget .lw-site { margin:4px 0 0; color:#6b7280; }
            #lw-digest-widget .lw-site a { text-decoration:none; }


            #lw-digest-widget .lw-rows { list-style:none; margin:12px 0 0; padding:0; display:flex; flex-direction:column; gap:10px; }
            #lw-digest-widget .lw-row { position:relative; border:1px solid #e5e7eb; background:#fff; border-radius:10px; padding:12px 88px 12px 14px; box-shadow:0 1px 0 rgba(16,24,40,.02); }
            #lw-digest-widget .lw-row-main { display:flex; justify-content:space-between; gap:12px; }
            #lw-digest-widget .lw-row-label { color:#374151; font-weight:600; }
            #lw-digest-widget .lw-row-value { color:#111827; font-variant-numeric:tabular-nums; }
            #lw-digest-widget .lw-row-note { font-size:12px; color:#6b7280; margin-top:6px; font-style:italic; }
            #lw-digest-widget .lw-row-status { position:absolute; right:12px; top:50%; transform:translateY(-50%); }

            #lw-digest-widget .lw-badge { display:inline-block; padding:4px 12px; border-radius:999px; font-size:12px; font-weight:700; line-height:1; }
            #lw-digest-widget .lw-badge.is-great { background:#10b9811a; color:#047857; border:1px solid #10b98155; }
            #lw-digest-widget .lw-badge.is-ok { background:#f59e0b1a; color:#92400e; border:1px solid #f59e0b55; }
            #lw-digest-widget .lw-badge.is-fix { background:#ef44441a; color:#991b1b; border:1px solid #ef444455; }
            #lw-digest-widget .lw-badge.is-info { background:#3b82f61a; color:#1e40af; border:1px solid #3b82f655; }

            #lw-digest-widget .lw-delta { font-size:11px; opacity:.75; margin-left:6px; }

            /* Responsive tweak for narrow admin widths */
            @media (max-width: 782px) {
                #lw-digest-widget .lw-row { padding-right:14px; }
                #lw-digest-widget .lw-row-status { position:static; transform:none; margin-top:8px; }
            }
        </style>
        <div id="lw-digest-widget" class="lw-digest">
            <header class="lw-header">
                <img class="lw-logo" src="<?php echo esc_url($logo); ?>" alt="Link Whisper" />
            </header>


            <ul class="lw-rows" role="list">
                <?php foreach ($rows as $row): ?>
                <li class="lw-row">
                    <div class="lw-row-main">
                        <div class="lw-row-label"><?php echo esc_html($row['label']); ?></div>
                        <div class="lw-row-value"><?php echo wp_kses_post($row['value']); ?></div>
                    </div>
                    <?php if (!empty($row['note'])): ?>
                    <div class="lw-row-note"><?php echo esc_html($row['note']); ?></div>
                    <?php endif; ?>
                    <div class="lw-row-status">
                    <?php echo self::status_badge($row['status'], $row['status_text'], $row['url']); ?>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
    }

    private static function get_dashboard_widget_rows(){
        // get the datas!
        // link density
        $link_density = Wpil_Dashboard::get_percent_of_posts_hitting_link_targets();
        $density_status = 'tag-positive';
        $density_subtext = __('Great! The majority of your posts are linked enough.');
        if(!empty($link_density['percent'])){
            if($link_density['percent'] > 80){
                $density_status = 'tag-positive';
                $density_subtext = __('Great! The majority of your posts are linked enough.');
            }elseif($link_density['percent'] > 60){
                $density_status = 'tag-neutral';
                $density_subtext = __('Most of the site\'s posts are linked enough.');
            }else{
                $density_status = 'tag-negative';
                $density_subtext = __('Uh oh, the majority of the site\'s posts aren\'t linked enough.');
            }
        }

        // broken links
        $broken_link_count = Wpil_Dashboard::getBrokenLinksCount();
        $broken_link_percentage = 0;
        $broken_link_status = 'tag-positive';
        $broken_link_subtext = __('Perfect! There aren\'t any broken links on the site.');
        if(!empty($broken_link_count)){
            $total_links = Wpil_Dashboard::getLinksCount();
            if(!empty($total_links)){
                $broken_link_percentage = round($broken_link_count / $total_links, 2) * 100;
            }
        }

        if($broken_link_percentage == 0){
            $broken_link_status = 'tag-positive';
            $broken_link_subtext = __('Perfect! There aren\'t any broken links on the site.');
        }elseif($broken_link_percentage < 5){
            $broken_link_status = 'tag-positive';
            $broken_link_subtext = __('Good! There are a relatively low number of broken links on the site.');
        }elseif($broken_link_percentage < 10){
            $broken_link_status = 'tag-neutral';
            $broken_link_subtext = __('There are a number of broken links on the site that need fixing.');
        }else{
            $broken_link_status = 'tag-negative';
            $broken_link_subtext = __('Houston, we have a problem. There are a lot of broken links on the site');
        }

        $posts_crawled = Wpil_Dashboard::getPostCount();
        $posts_crawled_status = (empty($posts_crawled)) ? 'tag-negative': 'tag-positive';

        $links_scanned = Wpil_Dashboard::getLinksCount();
        $links_scanned_status = (empty($links_scanned)) ? 'tag-negative': 'tag-positive';

        $links_inserted = Wpil_Dashboard::get_tracked_link_insert_count();
        $links_inserted_status = ($links_inserted > 0) ? 'tag-positive': 'tag-neutral';

        $orphaned_posts = Wpil_Dashboard::getOrphanedPostsCount();
        if(!empty($orphaned_posts)){
            $orphaned_posts_percentage = round($orphaned_posts/$posts_crawled, 2) * 100;
            if($orphaned_posts_percentage == 0){
                $orphaned_posts_status = 'tag-positive';
                $orphaned_posts_subtext = esc_html__('Awesome! There are no orphaned posts on the site.', 'wpil');
            }elseif($orphaned_posts_percentage < 5){
                $orphaned_posts_status = 'tag-positive';
                $orphaned_posts_subtext = esc_html__('Awesome! There are very few orphaned posts on the site.', 'wpil');
            }elseif($orphaned_posts_percentage < 10){
                $orphaned_posts_status = 'tag-neutral';
                $orphaned_posts_subtext = esc_html__('There are a number of orphaned posts that need some links pointing to them.', 'wpil');
            }else{
                $orphaned_posts_status = 'tag-negative';
                $orphaned_posts_subtext = esc_html__('Uh oh, looks like there are a lot of orphaned posts that need links!', 'wpil');
            }
        }else{
            $orphaned_posts_status = 'tag-positive';
            $orphaned_posts_subtext = esc_html__('Awesome! There are no orphaned posts on the site.', 'wpil');
        }
/*
        $ai_active = Wpil_Settings::can_do_ai_powered_suggestions(); // if we have a API key and at least some of the embedding data processed
        $link_relatedness = 0;
        if($ai_active){
            $link_relatedness = Wpil_Dashboard::get_related_link_percentage();
            if($link_relatedness == 0){
                $link_relatedness_status = 'tag-neutral';
                $link_relatedness_subtext = esc_html__('Hmm, there\'s no data available. We might need to run a Link Scan.', 'wpil');
            }elseif($link_relatedness > 79){
                $link_relatedness_status = 'tag-positive';
                $link_relatedness_subtext = esc_html__('Amazing! The majority of the site\'s links are going to highly related posts.', 'wpil');
            }elseif($link_relatedness > 50){
                $link_relatedness_status = 'tag-neutral';
                $link_relatedness_subtext = esc_html__('Most of the site\'s links are pointing to topically related posts.', 'wpil');
            }else{
                $link_relatedness_status = 'tag-negative';
                $link_relatedness_subtext = esc_html__('Uh oh, it looks like most of the site\'s links aren\'t going to related posts.', 'wpil');
            }
        }else{
            $link_relatedness_status = 'tag-neutral';
            $link_relatedness_subtext = esc_html__('Link Whisper\'s AI is not enabled, so we can\'t tell how many links are going to topically related posts.', 'wpil');
        }
*/
        // link clicks
        $click_stats = Wpil_Dashboard::get_click_traffic_stats();
        $click_change_indicator = '(<span title="No change from previous 30 days">0%</span>)';
        $click_change_subtext = esc_html__('The number of clicks has remained consistent over the past 30 days.', 'wpil');
        $click_change_status = 'tag-neutral';
        if($click_stats['percent_change'] > 0){
            $click_change_status = 'tag-positive';
            $click_change_indicator = '(<span class="tag-positive" title="Clicks have gone up over the past 30 days">+' . $click_stats['percent_change'] . '%</span>)';
            $click_change_subtext = esc_html__('The number of clicks on the site has gone up over the past 30 days!', 'wpil');
        }elseif($click_stats['percent_change'] < 0){
            $click_change_status = 'tag-negative';
            $click_change_indicator = '(<span class="tag-negative" title="Clicks have gone down over the past 30 days">-' . $click_stats['percent_change'] . '%</span>)';
            $click_change_subtext = esc_html__('The number of clicks on the site have gone down over the past 30 days.', 'wpil');
        }

        return [
            [
                'label' => __('Posts Crawled', 'wpil'),
                'value' => $posts_crawled,
                'note' => '',
                'status'=> $posts_crawled_status,
                'status_text' => '',
                'url' => admin_url('admin.php?page=link_whisper')
            ],
            [
                'label' => __('Links Detected', 'wpil'),
                'value' => $links_scanned,
                'note' => '',
                'status'=> $links_scanned_status,
                'status_text' => '',
                'url' => admin_url('admin.php?page=link_whisper&type=links')
            ],
            [
                'label' => __('Links Inserted', 'wpil'),
                'value' => $links_inserted,
                'note' => '',
                'status'=> $links_inserted_status,
                'status_text' => '',
                'url' => admin_url('admin.php?page=link_whisper&type=links')
            ],
            [
                'label' => __('Link Coverage', 'wpil'),
                'value' => $link_density['percent'] . '%',
                'note' => '', //$density_subtext,
                'status'=> $density_status,
                'status_text' => '',
                'url' => admin_url('admin.php?page=link_whisper&type=links&link_density=1')
            ],
            [
                'label' => __('Orphaned Posts', 'wpil'),
                'value' => $orphaned_posts,
                'note' => '', // $orphaned_posts_subtext,
                'status'=> $orphaned_posts_status,
                'status_text' => '',
                'url' => admin_url('admin.php?page=link_whisper&type=links&orphaned=1')
            ],
            [
                'label' => __('Link Clicks Tracked', 'wpil'),
                'value' => $click_stats['clicks_30'],
                'note' => '', // $click_change_subtext,
                'status'=> $click_change_status,
                'status_text' => '',
                'url' => admin_url('admin.php?page=link_whisper&type=clicks')
            ],
            [
                'label' => __('Broken Links Found', 'wpil'),
                'value' => $broken_link_count,
                'note' => '', // $broken_link_subtext,
                'status'=> $broken_link_status,
                'status_text' => '',
                'url' => admin_url('admin.php?page=link_whisper&type=error')
            ],
        ];
    }

    private static function status_badge($status, $text = '', $url = ''){
        $map = [
            'tag-positive' => ['label' => (!empty($text) ? $text: __('Great', 'wpil')), 'class' => 'is-great'],
            'tag-neutral' => ['label' => (!empty($text) ? $text: __('OK', 'wpil')), 'class' => 'is-ok'],
            'tag-negative' => ['label' => (!empty($text) ? $text: __('Fix', 'wpil')), 'class' => 'is-fix'],
            'tag-info' => ['label' => (!empty($text) ? $text: __('Info', 'wpil')), 'class' => 'is-info'],
        ];
        $cfg = isset($map[$status]) ? $map[$status]: $map['tag-info'];
        $label = esc_html($cfg['label']);
        $cls   = 'lw-badge ' . $cfg['class'];

        if(!empty($url)){
            return '<a class="'.$cls.'" href="'.esc_url($url).'" target="_blank">'.$label.'<span class="dashicons dashicons-external" style="position: relative;top: 1px;right: -1px;height: 5px;width: 5px;font-size: 12px;"></span></a>';
        }
        return '<span class="'.$cls.'">'.$label.'</span>';
    }

    /**
     * Get post or term by ID from GET or POST request
     *
     * @return Wpil_Model_Post|null
     */
    public static function getPost()
    {
        if (!empty($_REQUEST['term_id'])) {
            $post = new Wpil_Model_Post((int)$_REQUEST['term_id'], 'term');
        } elseif (!empty($_REQUEST['post_id'])) {
            $post = new Wpil_Model_Post((int)$_REQUEST['post_id']);
        } else {
            $post = null;
        }

        return $post;
    }

    /**
     * Show plugin version
     *
     * @return string
     */
    public static function showVersion()
    {
        $plugin_data = get_plugin_data(WP_INTERNAL_LINKING_PLUGIN_DIR . 'link-whisper.php');

        return "<p style='float: right'>version <b>".esc_html($plugin_data['Version'])."</b></p>";
    }

    public static function show_tawkto_widget(){
        if(!empty(get_option('wpil_disable_tawkto_widget', ''))){
            return;
        }?>
        <!--Start of Tawk.to Script-->
            <script type="text/javascript">
            var Tawk_API=Tawk_API||{}, Tawk_LoadStart=new Date();
            (function(){
            var s1=document.createElement("script"),s0=document.getElementsByTagName("script")[0];
            s1.async=true;
            s1.src='https://embed.tawk.to/686b600853ff86190e6b0ab6/1ivhmu817';
            s1.charset='UTF-8';
            s1.setAttribute('crossorigin','*');
            s0.parentNode.insertBefore(s1,s0);
            })();
            </script>
        <!--End of Tawk.to Script-->
        <?php
    }

    /**
     * Show extended error message
     *
     * @param $errno
     * @param $errstr
     * @param $error_file
     * @param $error_line
     */
    public static function handleError($errno, $errstr, $error_file, $error_line)
    {
        if (stristr($errstr, "WordPress could not establish a secure connection to WordPress.org")) {
            return;
        }

        $file = 'n/a';
        $func = 'n/a';
        $line = 'n/a';
        $debugTrace = debug_backtrace();
        if (isset($debugTrace[1])) {
            $file = isset($debugTrace[1]['file']) ? $debugTrace[1]['file'] : 'n/a';
            $line = isset($debugTrace[1]['line']) ? $debugTrace[1]['line'] : 'n/a';
        }
        if (isset($debugTrace[2])) {
            $func = $debugTrace[2]['function'] ? $debugTrace[2]['function'] : 'n/a';
        }

        $out = "call from <b>$file</b>, $func, $line";

        $trace = '';
        $bt = debug_backtrace();
        $sp = 0;
        foreach($bt as $k=>$v) {
            extract($v);

            $args = '';
            if (isset($v['args'])) {
                $args2 = array();
                foreach($v['args'] as $k => $v) {
                    if (!is_scalar($v)) {
                        $args2[$k] = "Array";
                    }
                    else {
                        $args2[$k] = $v;
                    }
                }
                $args = implode(", ", $args2);
            }

            $file = substr($file,1+strrpos($file,"/"));
            $trace .= str_repeat("&nbsp;",++$sp);
            $trace .= "file=<b>$file</b>, line=$line,
									function=$function(".
                var_export($args, true).")<br>";
        }

        $out .= $trace;

        echo "<b>Error:</b> [$errno] $errstr - $error_file:$error_line<br><br><hr><br><br>$out";
    }

    /**
     * Add meta box to the post edit page
     */
    public static function addMetaBoxes()
    {
        $capability = apply_filters('wpil_filter_main_permission_check', 'manage_categories', self::get_current_page());
        if (!current_user_can($capability)) {
            return;
        }

        add_meta_box('wpil_link-articles', 'Link Whisper Suggested Links', array(__CLASS__, 'showSuggestionsBox'), Wpil_Settings::getPostTypes());
    }

    /**
     * Show meta box on the post edit page
     */
    public static function showSuggestionsBox()
    {
        $post_id = isset($_REQUEST['post']) ? (int)$_REQUEST['post'] : '';
        $user = wp_get_current_user();
        if ($post_id) {
            // clear any old links that may still be hiding in the meta
            delete_post_meta($post_id, 'wpil_links');
            include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/link_list_v2.php';
        }else{
            include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/link_list_please_save_post.php';
        }
    }

    /**
     * Add scripts to the admin panel
     *
     * @param $hook
     */
    public static function addScripts($hook)
    {
        $current_screen = null;
        if(function_exists('get_current_screen')){
            $current_screen = get_current_screen();
        }

        if (strpos($_SERVER['REQUEST_URI'], '/post.php') !== false || strpos($_SERVER['REQUEST_URI'], '/term.php') !== false || (!empty($_GET['page']) && $_GET['page'] == 'link_whisper')) {
            if(function_exists('wp_enqueue_editor')){
                wp_enqueue_editor();
            }
        }

        $js_path = 'js/wpil_admin.js';
        $f_path = WP_INTERNAL_LINKING_PLUGIN_DIR.$js_path;
        $ver = filemtime($f_path);
        $current_screen = get_current_screen();

        $added_standard = false;

        if(isset($_GET['page']) && $_GET['page'] == 'link_whisper' || (!empty($current_screen) && $current_screen->base === 'edit')){
            self::add_standard_admin_scripts();
            $added_standard = true;
        }

        // IF
        if (isset($_GET['page']) && $_GET['page'] == 'link_whisper' && isset($_GET['type']) && ($_GET['type'] == 'inbound_suggestions_page' ||  // on the Inbound Suggestions page
            $_GET['type'] == 'click_details_page'  ||                                                                                           // or the Detailed Click Report page
            $_GET['type'] == 'links') ||                                                                                                        // or the Links Report page
            (!empty($current_screen) && ( // we have the current screen and
                'post' === $current_screen->base || // this is a post edit screen OR
                'page' === $current_screen->base || // this is a page edit screen OR
                'term' === $current_screen->base)   // this is a term edit screen
            )
        ){
            if(!$added_standard){
                self::add_standard_admin_scripts();
                $added_standard = true;
            }
            wp_register_style('wpil_daterange_picker_css', WP_INTERNAL_LINKING_PLUGIN_URL . 'css/daterangepicker.css');
            wp_enqueue_style('wpil_daterange_picker_css');
            wp_register_style('wpil_select2_css', WP_INTERNAL_LINKING_PLUGIN_URL . 'css/select2.min.css');
            wp_enqueue_style('wpil_select2_css');
            wp_register_script('wpil_moment', WP_INTERNAL_LINKING_PLUGIN_URL . 'js/moment.js', array('jquery'), $ver, true);
            wp_enqueue_script('wpil_moment');
            wp_register_script('wpil_daterange_picker', WP_INTERNAL_LINKING_PLUGIN_URL . 'js/daterangepicker.js', array('jquery', 'wpil_moment'), $ver, true);
            wp_enqueue_script('wpil_daterange_picker');
            wp_register_script('wpil_select2', WP_INTERNAL_LINKING_PLUGIN_URL . 'js/select2.full.min.js', array('jquery'), $ver, true); // Todo: remove the select2.min.js file when we pass 2.2.0
            wp_enqueue_script('wpil_select2');
        }

        if (isset($_GET['page']) && $_GET['page'] == 'link_whisper' && isset($_GET['type']) && $_GET['type'] == 'links') {
            if(!$added_standard){
                self::add_standard_admin_scripts();
                $added_standard = true;
            }
            wp_register_script('wpil_report', WP_INTERNAL_LINKING_PLUGIN_URL . 'js/wpil_report.js', array('jquery', 'wpil_helper'), $ver, true);
            wp_enqueue_script('wpil_report');
        }

        if (isset($_GET['page']) && $_GET['page'] == 'link_whisper' && isset($_GET['type']) && $_GET['type'] == 'error') {
            if(!$added_standard){
                self::add_standard_admin_scripts();
                $added_standard = true;
            }
            wp_register_script('wpil_error', WP_INTERNAL_LINKING_PLUGIN_URL . 'js/wpil_error.js', array('jquery', 'wpil_helper'), $ver, true);
            wp_enqueue_script('wpil_error');
        }

        if (isset($_GET['page']) && $_GET['page'] == 'link_whisper' && isset($_GET['type']) && $_GET['type'] == 'domains') {
            if(!$added_standard){
                self::add_standard_admin_scripts();
                $added_standard = true;
            }
            wp_register_style('wpil_select2_css', WP_INTERNAL_LINKING_PLUGIN_URL . 'css/select2.min.css');
            wp_enqueue_style('wpil_select2_css');
            wp_register_script('wpil_select2', WP_INTERNAL_LINKING_PLUGIN_URL . 'js/select2.full.min.js', array('jquery'), $ver, true); // Todo: remove the select2.min.js file when we pass 2.2.0
            wp_enqueue_script('wpil_select2');
            
            wp_register_script('wpil_domains', WP_INTERNAL_LINKING_PLUGIN_URL . 'js/wpil_domains.js', array('jquery', 'wpil_helper'), $ver, true);
            wp_enqueue_script('wpil_domains');
        }

        if (isset($_GET['page']) && $_GET['page'] == 'link_whisper' && isset($_GET['type']) && ( $_GET['type'] == 'click_details_page' || $_GET['type'] == 'clicks')) {
            if(!$added_standard){
                self::add_standard_admin_scripts();
                $added_standard = true;
            }
            wp_register_script('wpil_click', WP_INTERNAL_LINKING_PLUGIN_URL . 'js/wpil_click.js', array('jquery', 'wpil_helper'), $ver, true);
            wp_enqueue_script('wpil_click');
        }

        if (isset($_GET['page']) && $_GET['page'] == 'link_whisper' && isset($_GET['type']) && $_GET['type'] == 'sitemaps') {
            if(!$added_standard){
                self::add_standard_admin_scripts();
                $added_standard = true;
            }
            wp_enqueue_script('wpil_sitemaps', WP_INTERNAL_LINKING_PLUGIN_URL . 'js/sitemaps.min.js', array('jquery', 'wpil_helper'), $ver, true);
            wp_register_script('wpil_papa_parse', WP_INTERNAL_LINKING_PLUGIN_URL . 'js/papaparse.min.js', array('jquery'), $ver, true);
            wp_enqueue_script('wpil_papa_parse');
        }

        if (isset($_GET['page']) && ($_GET['page'] == 'link_whisper_target_keywords' || $_GET['page'] == 'link_whisper' && isset($_GET['type']) && $_GET['type'] === 'inbound_suggestions_page') || ('post' === $current_screen->base || 'term' === $current_screen->base) ) {
            if(!$added_standard){
                self::add_standard_admin_scripts();
                $added_standard = true;
            }
            wp_register_script('wpil_target_keyword', WP_INTERNAL_LINKING_PLUGIN_URL . 'js/wpil_target_keyword.js', array('jquery', 'wpil_helper'), $ver, true);
            wp_enqueue_script('wpil_target_keyword');
        }

        if(isset($_GET['page']) && ($_GET['page'] == 'link_whisper_settings')){
            if(!$added_standard){
                self::add_standard_admin_scripts();
                $added_standard = true;
            }
            $js_path = 'js/wpil_admin_settings.js';
            $ver = filemtime(WP_INTERNAL_LINKING_PLUGIN_DIR.$js_path);
    
            wp_register_script('wpil_admin_settings_script', WP_INTERNAL_LINKING_PLUGIN_URL.$js_path, array('jquery', 'wpil_select2', 'wpil_helper'), $ver, true);
            wp_enqueue_script('wpil_admin_settings_script');

            wp_register_style('wpil_select2_css', WP_INTERNAL_LINKING_PLUGIN_URL . 'css/select2.min.css');
            wp_enqueue_style('wpil_select2_css');
            wp_register_script('wpil_select2', WP_INTERNAL_LINKING_PLUGIN_URL . 'js/select2.full.min.js', array('jquery'), $ver, true);
            wp_enqueue_script('wpil_select2');
        }

        if(isset($_GET['page']) && ($_GET['page'] == 'link_whisper_ai_subscription')){
            if(!$added_standard){
                self::add_standard_admin_scripts();
                $added_standard = true;
            }

            wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', [], null, true);


            // TODO: ADD JS IN SPECIFIC FILES
            /*
            $js_path = 'js/wpil_admin_settings.js';
            $f_path = WP_INTERNAL_LINKING_PLUGIN_DIR.$js_path;
            $ver = filemtime($f_path);

            wp_register_script('wpil_admin_settings_script', WP_INTERNAL_LINKING_PLUGIN_URL.$js_path, array('jquery', 'wpil_select2', 'wpil_helper'), $ver, true);
            wp_enqueue_script('wpil_admin_settings_script');*/
        }

        $style_path = 'css/wpil_admin.css';
        $f_path = WP_INTERNAL_LINKING_PLUGIN_DIR.$style_path;
        $ver = filemtime($f_path);

        wp_register_style('wpil_admin_style', WP_INTERNAL_LINKING_PLUGIN_URL.$style_path, array(), $ver);
        wp_enqueue_style('wpil_admin_style');

        $disable_fonts = apply_filters('wpil_disable_fonts', false); // we've only got one font ATM
        if(empty($disable_fonts)){
            $style_path = 'css/wpil_fonts.css';
            $f_path = WP_INTERNAL_LINKING_PLUGIN_DIR.$style_path;
            $ver = filemtime($f_path);

            wp_register_style('wpil_admin_fonts', WP_INTERNAL_LINKING_PLUGIN_URL.$style_path, $deps=[], $ver);
            wp_enqueue_style('wpil_admin_fonts');
        }

        $dismissedPopups = get_user_meta(get_current_user_id(), 'wpil_dismissed_popups', true);
        $dismissedPopups = (!empty($dismissedPopups)) ? $dismissedPopups: array();
        $ajax_url = admin_url('admin-ajax.php');

        $current_page = self::get_current_page();
        $explain_page_hidden_on = (array)get_user_meta(get_current_user_id(), 'wpil_hide_explain_page_on', true);
        $dismiss_explain_page = (!empty(get_user_meta(get_current_user_id(), 'wpil_hide_explain_page_completely', true)) || in_array($current_page, $explain_page_hidden_on)) ? 1: 0;

        $script_params = array();
        $script_params['ajax_url'] = $ajax_url;
        $script_params['wpil_js_path'] = (trailingslashit(WP_INTERNAL_LINKING_PLUGIN_URL) . 'js/');
        $script_params['completed'] = __('completed', 'wpil');
        $script_params['dismissed_popups'] = $dismissedPopups;
        $script_params['dismiss_popup_nonce'] = wp_create_nonce(get_current_user_id() . 'dismiss-popup-nonce');
        $script_params['current_page'] = $current_page;
        $script_params['plugin_version'] = WPIL_PLUGIN_VERSION_NUMBER;
        $script_params['dismiss_explain_page'] = $dismiss_explain_page;
        
        $script_params['debug'] = defined('WPIL_DEBUG') && WPIL_DEBUG;
        $script_params['tour_nonce'] = wp_create_nonce('wpil_load_tours');
        $script_params['save_tour_progress_nonce'] = wp_create_nonce('wpil_save_tour_progress');
        $script_params['mark_tour_shown_nonce'] = wp_create_nonce('wpil_mark_tour_shown');
        $script_params['dismiss_tour_widget_nonce'] = wp_create_nonce('wpil_dismiss_tour_widget');
        $script_params['popup_nonce'] = wp_create_nonce('wpil_load_popups');
        $script_params['dismiss_popup_nonce'] = wp_create_nonce('wpil_dismiss_popup');
//        $script_params['telemetry_active'] = 1; //Wpil_Settings::get_if_telemetry_active();
//        $script_params['telemetry_nonce'] = wp_create_nonce(get_current_user_id() . 'wpil-telemetry-nonce');
//        $script_params['tours_enabled'] = Wpil_Settings::get_tours_enabled();

        $script_params['wpil_timepicker_format'] = Wpil_Toolbox::convert_date_format_for_js();
/*
        $script_params['wpil_help_overlay_controls'] =
        '<div id="wpil-floating-help-menu" class="button-wave-effect" style="display: flex; flex-direction: column; gap: 10px; position: fixed; bottom: 20px; right: 20px;">
            <input type="hidden" id="wpil-floating-help-menu-nonce" value="' . wp_create_nonce(get_current_user_id() . 'wpil-floating-help-menu-nonce') . '">
            <span id="wpil-hide-explain-page-x" class="dashicons dashicons-no-alt"></span>
            <div id="wpil-explain-page-control-wrapper">
                <div class="wpil-floating-button-container">
                    <button id="wpil-explain-page-button" class="wpil-floating-button">
                        Explain Page
                    </button>
                </div>
                <div class="wpil-floating-button-container" style="display:none">
                    <button id="wpil-explain-part-button" class="wpil-floating-button">
                        Explain Part
                    </button>
                </div>
                <div id="wpil-help-overlay-controls" style="display:none;">
                    <div class="wpil-help-overlay-segment-container">
                        <div class="wpil-help-overlay-segment segments-completed"></div>
                        <div>OF</div>
                        <div class="wpil-help-overlay-segment segments-total"></div>
                    </div>
                    <div class="wpil-help-overlay-control-container">
                        <div class="wpil-help-overlay-control wpil-help-backward">
                            <button>' . self::get_svg_icon('previous-track', false, ['width'=>32]) . '</button>
                        </div>
                        <div class="wpil-help-overlay-control wpil-help-pause">
                            <button>' . self::get_svg_icon('pause', false, ['width'=>32]) . '</button>
                        </div>
                        <div class="wpil-help-overlay-control wpil-help-play" style="display:none">
                            <button>' . self::get_svg_icon('play', false, ['width'=>32]) . '</button>
                        </div>
                        <div class="wpil-help-overlay-control wpil-help-forward">
                            <button>' . self::get_svg_icon('next-track', false, ['width'=>32]) . '</button>
                        </div>
                    </div>
                </div>
            </div>
            <div id="wpil-hide-explain-page-option-wrapper" style="display:none;">
                <div class="wpil-hide-explain-page-control">
                    <button class="wpil-floating-button wpil-hide-explain-page-button" value="0">Temp Hide</button>
                </div>
                <div class="wpil-hide-explain-page-control">
                    <button class="wpil-floating-button wpil-hide-explain-page-button" value="1">Remove From This Page</button>
                </div>
                <div class="wpil-hide-explain-page-control">
                    <button class="wpil-floating-button wpil-hide-explain-page-button" value="2">Remove From All Pages</button>
                </div>
            </div>
        </div>';
*/

        $script_params['wpil_help_overlay_controls'] =
        '<div id="wpil-floating-help-menu" style="display: flex; flex-direction: column; gap: 10px; position: fixed; bottom: 20px; right: 20px;height: 0px;width: 0px; padding:0px;">
            <input type="hidden" id="wpil-floating-help-menu-nonce" value="' . wp_create_nonce(get_current_user_id() . 'wpil-floating-help-menu-nonce') . '">
            <div id="wpil-explain-page-control-wrapper">
                <button id="wpil-explain-page-button" class="wpil-floating-button" style="display:none"></button>
                <div id="wpil-help-overlay-controls" style="display:none; height: 0px;width: 0px; padding:0px;">
                <span class="close-overlay dashicons dashicons-plus-alt2"></span>
                    <div class="wpil-help-overlay-segment-container" style="position:fixed; left: calc(50% - 100px); width: 100px; background: #ffffff; bottom: 30px; padding: 5px 20px;">
                        <div class="wpil-help-overlay-segment segments-completed"></div>
                        <div>OF</div>
                        <div class="wpil-help-overlay-segment segments-total"></div>
                    </div>
                    <div class="wpil-help-overlay-control-container">
                        <div class="wpil-help-overlay-control wpil-help-backward" style="position:fixed; left: 50px; top:50%;">
                            <button><span class="dashicons dashicons-arrow-left-alt2"></span></button>
                        </div>
                        <div class="wpil-help-overlay-control wpil-help-forward" style="position:fixed; right: 50px; top:50%; height: 60px; background: #cdcdcd; border-radius: 25px;">
                            <button><span class="dashicons dashicons-arrow-right-alt2"></span></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>';

        if(null !== $current_screen && 'dashboard' === $current_screen->base){
            wp_register_script('wpil_convertkit_script', 'https://f.convertkit.com/ckjs/ck.5.js', array(), false, true);
            wp_enqueue_script('wpil_convertkit_script');

            wp_register_script('wpil_email_signup_script', WP_INTERNAL_LINKING_PLUGIN_URL . 'js/email_signup.js', array('jquery'), false, true);
            wp_enqueue_script('wpil_email_signup_script');

            $user = wp_get_current_user();
            $script_params['wpil_email_dismiss_nonce']  = wp_create_nonce('wpil_email_dismiss_nonce' . (int)$user->ID);
            $script_params['current_user']              = (int)$user->ID;

            wp_register_style('wpil_convertkit_style', WP_INTERNAL_LINKING_PLUGIN_URL . 'css/email_signup.css');
            wp_enqueue_style('wpil_convertkit_style');
        }

        if(self::show_review_notice()){
            wp_register_script('wpil_review_notice_script', WP_INTERNAL_LINKING_PLUGIN_URL . 'js/review_notice.js', array('jquery'), false, true);
            wp_enqueue_script('wpil_review_notice_script');

            $user = wp_get_current_user();
            $script_params['wpil_review_dismiss_nonce'] = wp_create_nonce('wpil_review_notice_nonce' . (int)$user->ID);
            $script_params['wpil_review_nonce']         = wp_create_nonce('wpil_review_nonce' . (int)$user->ID);
            $script_params['current_user']              = (int)$user->ID;

            wp_register_style('wpil_convertkit_style', WP_INTERNAL_LINKING_PLUGIN_URL . 'css/email_signup.css');
            wp_enqueue_style('wpil_convertkit_style');
        }

        // TODO: INSTALL AI NUDGE

        if (isset($_GET['page']) && $_GET['page'] == 'link_whisper_wizard') {
            if(!$added_standard){
                self::add_standard_admin_scripts();
                $added_standard = true;
            }
            wp_register_script('wpil_wizard', WP_INTERNAL_LINKING_PLUGIN_URL . 'js/wpil_wizard.js', array('jquery', 'wpil_base64', 'wpil_tippy', 'wpil_popper', 'wpil_helper'), $ver, true);
            wp_enqueue_script('wpil_wizard');
        }

        if($added_standard){
            wp_localize_script('wpil_admin_script', 'wpil_ajax', $script_params);
        }
    }

    public static function add_standard_admin_scripts(){
        wp_register_script('wpil_helper', WP_INTERNAL_LINKING_PLUGIN_URL . 'js/wpil_helper.js', array(), filemtime(WP_INTERNAL_LINKING_PLUGIN_DIR . '/js/wpil_helper.js'), true);

        wp_register_script('wpil_base64', WP_INTERNAL_LINKING_PLUGIN_URL . 'js/base64.js', array(), false, true);
        wp_enqueue_script('wpil_base64');

        wp_register_script('wpil_sweetalert_script_min', WP_INTERNAL_LINKING_PLUGIN_URL . 'js/sweetalert.min.js', array('jquery'), $ver=false, true);
        wp_enqueue_script('wpil_sweetalert_script_min');

        $js_path = 'js/wpil_admin.js';
        $f_path = WP_INTERNAL_LINKING_PLUGIN_DIR.$js_path;
        $ver = filemtime($f_path);
        $current_screen = get_current_screen();

        wp_register_style('wpil_tippy_style', WP_INTERNAL_LINKING_PLUGIN_URL . 'css/tippy.css');
        wp_enqueue_style('wpil_tippy_style');

        wp_register_script('wpil_popper', WP_INTERNAL_LINKING_PLUGIN_URL.'js/popper.min.js', array(), $ver, true);
        wp_enqueue_script('wpil_popper');

        wp_register_script('wpil_tippy', WP_INTERNAL_LINKING_PLUGIN_URL.'js/tippy.umd.js', array(), $ver, true);
        wp_enqueue_script('wpil_tippy');

        wp_register_script('wpil_admin_script', WP_INTERNAL_LINKING_PLUGIN_URL.$js_path, array('jquery', 'wpil_base64', 'wpil_tippy', 'wpil_popper', 'wpil_helper'), $ver, true);
        wp_enqueue_script('wpil_admin_script');

        wp_register_script('wpil_help_overlay', WP_INTERNAL_LINKING_PLUGIN_URL.'js/wpil_help_overlay.js', array('jquery', 'wpil_base64', 'wpil_tippy', 'wpil_popper', 'wpil_helper'), $ver, true);
        wp_enqueue_script('wpil_help_overlay');
    }

    /**
     * Gets the type of page that we're currently on
     **/
    public static function get_current_page(){
        if(!function_exists('get_current_screen')){
            return '';
        }

        $current_page = get_current_screen();
        $page = '';
        if(isset($_GET['type'])){
            switch($_GET['type']){
                case 'links':
                    $page = $_GET['type'];
                    break;
            }
        }elseif(isset($_GET['page']) && $_GET['page'] === 'link_whisper'){
            $page = 'dashboard';
        }elseif(isset($_GET['page']) && $_GET['page'] === 'link_whisper_keywords'){
            $page = 'autolinking';
        }elseif(isset($_GET['page']) && $_GET['page'] === 'link_whisper_target_keywords'){
            $page = 'target-keywords';
        }elseif(isset($_GET['page']) && $_GET['page'] === 'link_whisper_url_changer'){
            $page = 'url-changer';
        }elseif(isset($_GET['page']) && $_GET['page'] === 'link_whisper_ai_subscription'){
            $page = 'ai-subscription';
        }elseif(!empty($current_page) && $current_page->base === 'post'){
            $page = 'post-edit';
        }elseif(!empty($current_page) && $current_page->base === 'term'){
            $page = 'term-edit';
        }

        return $page;
    }

    /**
     * Gets the available Link Whisper pages and the pages that Link Whisper is active on
     **/
    public static function get_available_pages(){
        $pages = array( 'links', 'dashboard',
                        'post-edit', 'term-edit');
        return $pages;
    }

    /**
     * Enqueues the scripts to use on the frontend.
     **/
    public static function enqueue_frontend_scripts(){
        global $wp_the_query, $post;

        // if we're doing a preview of the settings
        if( isset($_GET['wpil_related_post_preview_nonce']) &&
            isset($_GET['nonce']) && 
            wp_verify_nonce($_GET['nonce'], 'wpil-related-posts-preview-nonce'))
        {
            // inline a bit of JS to scroll the window to the RP widget
            wp_register_script('link-whisper-related-post-preview-inline', '');
            wp_enqueue_script('link-whisper-related-post-preview-inline');
            wp_add_inline_script('link-whisper-related-post-preview-inline', 'window.location.hash = "#link-whisper-related-posts-widget";');
        }

        // TODO: Add an option to disable the frontend scripts.
        if(empty($wp_the_query)){
            return;
        }

        $posty = $wp_the_query->get_queried_object();

        // if we're on a post type archive
        if($wp_the_query->is_post_type_archive || is_a($posty, 'WP_Post_Type')){
            // exit since we can't accurately assign clicks to a post
            return;
        }
        
        if(empty($posty)){
            $posty = $post;
        }

        // get if the links are to be opened in new tabs
        $open_with_js       = (!empty(get_option('wpil_js_open_new_tabs', false))) ? 1: 0;
        $open_all_intrnl    = (!empty(get_option('wpil_open_all_internal_new_tab', false))) ? 1: 0;
        $open_all_extrnl    = (!empty(get_option('wpil_open_all_external_new_tab', false))) ? 1: 0;

        // and if the user has disabled click tracking or there isn't a valid post id
        $dont_track_clicks = (!empty(get_option('wpil_disable_click_tracking', false)) || empty($posty)) ? 1: 0;

        // if none of them are, exit
        if( ($open_with_js == 0 || $open_all_intrnl == 0 && $open_all_extrnl == 0) && $dont_track_clicks == 1){
            return;
        }

        // put together the ajax variables
        $ajax_url = get_site_url(null, 'wp-admin/admin-ajax.php', 'relative');
        $type = null; 
        $id = null;
        if(!empty($posty)){
            $type = (is_a($posty, 'WP_Term')) ? 'term': 'post';
            $id = ($type === 'post') ? $posty->ID: $posty->term_id;
        }
        $script_params = [];
        $script_params['ajaxUrl'] = $ajax_url;
        $script_params['postId'] = $id;
        $script_params['postType'] = $type;
        $script_params['openInternalInNewTab'] = $open_all_intrnl;
        $script_params['openExternalInNewTab'] = $open_all_extrnl;
        $script_params['disableClicks'] = $dont_track_clicks;
        $script_params['openLinksWithJS'] = $open_with_js;
        $script_params['trackAllElementClicks'] = !empty(get_option('wpil_track_all_element_clicks', 0)) ? 1: 0;


        // output some actual localizations
        $script_params['clicksI18n'] = array(
            'imageNoText'   => __('Image in link: No Text', 'wpil'),
            'imageText'     => __('Image Title: ', 'wpil'),
            'noText'        => __('No Anchor Text Found', 'wpil'),
        );

        // enqueue the frontend scripts
        $filename = 'frontend.min.js';

        $file_path = WP_INTERNAL_LINKING_PLUGIN_DIR . 'js/' . $filename;
        $url_path  = WP_INTERNAL_LINKING_PLUGIN_URL . 'js/' . $filename;
        wp_enqueue_script('wpil-frontend-script', $url_path, array(), filemtime($file_path), true);

        // output the ajax variables
        wp_localize_script('wpil-frontend-script', 'wpilFrontend', $script_params);

        // if we're supposed to add the frontend scripts and the user is able to see them
        if( isset($_GET['wpil_admin_frontend']) && 
            is_user_logged_in() && 
            current_user_can(apply_filters('wpil_filter_main_permission_check', 'manage_categories', self::get_current_page())))
        {
            $file_path = WP_INTERNAL_LINKING_PLUGIN_DIR . 'js/admin-frontend.js';
            $url_path  = WP_INTERNAL_LINKING_PLUGIN_URL . 'js/admin-frontend.js';
            wp_enqueue_script('wpil-admin-frontend-script', $url_path, array('jquery'), filemtime($file_path), true);
        }
    }

    /**
     * Show settings link on the plugins page
     *
     * @param $links
     * @return array
     */
    public static function showSettingsLink($links)
    {
        $links[] = '<a href="admin.php?page=link_whisper_settings">Settings</a>';
        return $links;
    }

    /**
     * Displays the email sign up offer in the wp dashboard
     **/
    public static function addEmailSignupNotice(){
        $page = get_current_screen();
        if(empty($page) || !isset($page->base) || (false === strpos($page->base, 'link-whisper') && false === strpos($page->base, 'link_whisper'))){
//            return;
        }

        include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/dashboard_email_signup_notice.php';
    }

    /**
     * Stores the admin's choice of dismissing our email offer if he should decide to do so
     **/
    public static function ajax_dismiss_email_offer_notice(){

        // if the current user id or nonce isn't set, or the nonce doesn't check out
        if( !isset($_POST['current_user']) || 
            !isset($_POST['nonce']) || 
            !wp_verify_nonce($_POST['nonce'], 'wpil_email_dismiss_nonce' . (int)$_POST['current_user']))
        {
            // send back an error
            wp_send_json('It seems there\'s been an error, please refresh the page and try again.');
        }

        // get the user id
        $user_id = (int)$_POST['current_user'];

        // update the dismissed notice status with the admin's id
        update_option(WPIL_EMAIL_OFFER_DISMISSED, $user_id);

        wp_send_json('Notice dismissed!');

    }

    /**
     * Stores the admin's choice of dismissing the premium upgrade notice in the report screen
     **/
    public static function ajax_dismiss_premium_notice(){
        // hide the premium notice
        update_option(WPIL_PREMIUM_NOTICE_DISMISSED, true);
        wp_send_json('Notice dismissed!');
    }

    /**
     * Stores the admin's choice of signing up for the email offer
     **/
    public static function ajax_signed_up_email_offer_notice(){

        // if the current user id or nonce isn't set, or the nonce doesn't check out
        if( !isset($_POST['current_user']) || 
            !isset($_POST['nonce']) || 
            !wp_verify_nonce($_POST['nonce'], 'wpil_email_dismiss_nonce' . (int)$_POST['current_user']))
        {
            // send back an error
            wp_send_json('It seems there\'s been an error, please refresh the page and try again.');
        }

        // get the user id
        $user_id = (int)$_POST['current_user'];

        // get the current list of sign ups
        $signups = get_option(WPIL_SIGNED_UP_EMAIL_OFFER, array());
        $signups = maybe_unserialize($signups);

        // if the current user isn't already on the list
        if(!isset($signups[$user_id])){
            // add the user's id to the signup list
            $signups[$user_id] = $user_id;

            // update the dismissed notice status with the admin's id
            update_option(WPIL_SIGNED_UP_EMAIL_OFFER, $signups);
        }

        wp_send_json('Subscribed for Emails!');
    }

    /**
     * Checks to see if the time is right to ask for a review!
     **/
    public static function show_review_notice(){

        // exit if the user can't use LW
        if(!current_user_can('edit_posts')){
            return false;
        }

        $install_time = get_option('wpil_free_install_date', current_time('mysql', true));
        $current_time = current_time('timestamp', true);
        $update_count = get_option('wpil_free_update_count', 0);

        // if the activation count or time limit hasn't been reached yet, exit
        if($update_count < 2 || (strtotime($install_time) + WEEK_IN_SECONDS * 3) > $current_time){
            return false;
        }

        // check if the user has already given a review or has dismissed the notice entirely
        $user = wp_get_current_user();
        $left_review = get_user_meta($user->ID, 'wpil_review_left', true);
        $perm_dismissed = get_user_meta($user->ID, 'wpil_review_notice_perm_dismissed', true);

        // if he has, exit
        if(!empty($left_review) || !empty($perm_dismissed)){
            return false;
        }

        // finally check to see if the review has been temp disabled
        $temp_disabled = get_user_meta($user->ID, 'wpil_review_notice_temp_dismissed', true);

        // if it has, exit
        if(!empty($temp_disabled) && $current_time < ($temp_disabled + WEEK_IN_SECONDS * 3)){
            return false;
        }

        // if we've made it past all the checks, it's time to show the notice!
        return true;
    }

    /**
     * Displays the notice asking the user for a review
     **/
    public static function add_notice_for_review(){
        $page = get_current_screen();
        if(empty($page) || !isset($page->base) || (false === strpos($page->base, 'link-whisper') && false === strpos($page->base, 'link_whisper'))){
//            return;
        }

        include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/dashboard_review_request_notice.php';
    }

    /**
     * Stores the admin's choice of dismissing the request for review temporarily
     **/
    public static function ajax_dismiss_review_notice(){

        // if the current user id or nonce isn't set, or the nonce doesn't check out
        if( !isset($_POST['current_user']) || 
            !isset($_POST['nonce']) || 
            !wp_verify_nonce($_POST['nonce'], 'wpil_review_nonce' . (int)$_POST['current_user']))
        {
            // send back an error
            wp_send_json('It seems there\'s been an error, please refresh the page and try again.');
        }

        // get the user id
        $user_id = (int)$_POST['current_user'];

        // update the notice for the user with the current timestamp
        update_user_meta($user_id, 'wpil_review_notice_temp_dismissed', current_time('timestamp', true));

        wp_send_json('Notice dismissed!');

    }

    /**
     * Permanently hides the review notice from the user.
     **/
    public static function ajax_perm_dismiss_review_notice(){
        // if the current user id or nonce isn't set, or the nonce doesn't check out
        if( !isset($_POST['current_user']) || 
            !isset($_POST['nonce']) || 
            !wp_verify_nonce($_POST['nonce'], 'wpil_review_notice_nonce' . (int)$_POST['current_user']))
        {
            // send back an error
            wp_send_json('It seems there\'s been an error, please refresh the page and try again.');
        }

        // get the user id
        $user_id = (int)$_POST['current_user'];

        if(isset($_POST['leaving_review']) && !empty($_POST['leaving_review'])){
            update_user_meta($user_id, 'wpil_review_left', true);
        }

        update_user_meta($user_id, 'wpil_review_notice_perm_dismissed', true);

        wp_send_json('Notice dismissed!');
    }

    /**
     * Fill data to DB on plugin activate
     */
    public static function activate()
    {
        update_option(WPIL_EMAIL_OFFER_DISMISSED, '');
        update_option(WPIL_PREMIUM_NOTICE_DISMISSED, '');

        if('' === get_option(WPIL_OPTION_IGNORE_NUMBERS, '')){
            update_option(WPIL_OPTION_IGNORE_NUMBERS, '1');
        }
        if('' === get_option(WPIL_OPTION_POST_TYPES, '')){
            update_option(WPIL_OPTION_POST_TYPES, ['post', 'page']);
        }
        if('' === get_option(WPIL_OPTION_IGNORE_WORDS, '')){
            // if there's no ignore words, configure the language settings
            update_option('wpil_selected_language', Wpil_Settings::getSiteLanguage());
            $ignore = "-\r\n" . implode("\r\n", Wpil_Settings::getIgnoreWords()) . "\r\n-";
            update_option(WPIL_OPTION_IGNORE_WORDS, $ignore);
        }
        if('' === get_option(WPIL_LINK_TABLE_IS_CREATED, '')){
            Wpil_Report::setupWpilLinkTable(true);
        }
        if('' === get_option('wpil_free_install_date', '')){
            // set the install date so we can tell how long the user has been with us
            update_option('wpil_free_install_date', current_time('mysql', true));
        }

        // disabling in 2.5.6... Shouldn't need this anymore since it's been ~4 years since the class has been used.
        //Wpil_Link::removeLinkClass();

        // temp cleanup function, remove when we get to 2.7.0
        Wpil_AI::clear_duplicate_calculated_embeddings();

        self::createDatabaseTables();
        self::updateTables();
        // note the updated status
        update_option('wpil_version_check_update', WPIL_PLUGIN_VERSION_NUMBER);
    }

    /**
     * Runs any update routines after the plugin has been updated.
     */
    public static function upgrade_complete($upgrader_object, $options){
        // If an update has taken place and the updated type is plugins and the plugins element exists
        if( $options['action'] == 'update' && $options['type'] == 'plugin' && isset( $options['plugins'] ) ) {
            // Go through each plugin to see if Link Whisper was updated
            foreach( $options['plugins'] as $plugin ) {
                if( $plugin == WPIL_PLUGIN_NAME ) {
                    // create any tables that need creating
                    self::createDatabaseTables(); // BOOK:
                    // and make sure the existing tables are up to date
                    self::updateTables();
                    // note the updated status
                    update_option('wpil_version_check_update', WPIL_PLUGIN_VERSION_NUMBER);
                }
            }
        }
    }

    /**
     * Updates the existing LW data tables with changes as we add them.
     * Does a version check to see if any DB tables have been updated since the last time this was run.
     * 
     * @param bool $force_update Setting $force_update to true will ignore the version checks and run all update steps
     */
    public static function updateTables($force_update = false){
        global $wpdb;

        $autolink_tbl = $wpdb->prefix . 'wpil_keyword_links';
        $autolink_rule_tbl = $wpdb->prefix . 'wpil_keywords';
        $autolink_select_tbl = $wpdb->prefix . 'wpil_keyword_select_links';
        $broken_link_tbl = $wpdb->prefix . 'wpil_broken_links';
        $ignore_broken_link_tbl = $wpdb->prefix . 'wpil_ignore_links';
        $report_links_tbl = $wpdb->prefix . 'wpil_report_links';
        $target_keyword_tbl = $wpdb->prefix . 'wpil_target_keyword_data';
        $url_changer_tbl = $wpdb->prefix . 'wpil_urls';
        $url_links_tbl = $wpdb->prefix . 'wpil_url_links';
        $click_tracking_tbl = $wpdb->prefix . 'wpil_click_data';
        $related_posts_tbl = $wpdb->prefix . "wpil_related_posts";
        $ai_embedding_tbl = $wpdb->prefix . "wpil_ai_embedding_data";
        $ai_embedding_calc_tbl = $wpdb->prefix . "wpil_ai_embedding_calculation_data";
        $ai_product_tbl = $wpdb->prefix . "wpil_ai_product_data";
        $ai_keyword_tbl =  $wpdb->prefix . "wpil_ai_keyword_data";
        $ai_suggestion_sentence_tbl = $wpdb->prefix . "wpil_ai_processed_sentences";
        $ai_suggested_anchor_tbl = $wpdb->prefix . "wpil_ai_suggested_anchors";
        $ai_credit_tbl = $wpdb->prefix . "wpil_ai_token_use_data";

        $fresh_install = get_option('wpil_fresh_install', false);

        // if the DB is up to date, exit
        if(WPIL_STATUS_SITE_DB_VERSION === WPIL_STATUS_PLUGIN_DB_VERSION && !$force_update){
            return;
        }

        // if this is a fresh install of the plugin and not a forced update
        if($fresh_install && empty(WPIL_STATUS_SITE_DB_VERSION) && !$force_update){
            // set the DB version as the latest since all the created tables will be up to date
            update_option('wpil_site_db_version', WPIL_STATUS_PLUGIN_DB_VERSION);
            update_option('wpil_fresh_install', false);
            // and exit
            return;
        }

        if((float)WPIL_STATUS_SITE_DB_VERSION < 0.9 || $force_update){
            // Added in v1.0.0
            // if the error links table exists
            $error_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$broken_link_tbl}'");
            if(!empty($error_tbl_exists)){
                // find out if the table has a last_checked col
                $col = $wpdb->query("SHOW COLUMNS FROM {$broken_link_tbl} LIKE 'last_checked'");
                if(empty($col)){
                    // if it doesn't, add it and a check_count col to the table
                    $update_table = "ALTER TABLE {$broken_link_tbl} ADD COLUMN check_count INT(2) DEFAULT 0 AFTER created, ADD COLUMN last_checked DATETIME NOT NULL DEFAULT NOW() AFTER created";
                    $wpdb->query($update_table);
                }
            }

            // update the state of the DB to this point
            update_option('wpil_site_db_version', '0.9');
        }

        // if the current DB version is less than 1.0, run the 1.0 update
        if((float)WPIL_STATUS_SITE_DB_VERSION < 1.0 || $force_update){
            /** added in v1.0.1 **/
            // if the error links table exists
            $error_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$broken_link_tbl}'");
            if(!empty($error_tbl_exists)){
                // find out if the table has a ignore_link col
                $col = $wpdb->query("SHOW COLUMNS FROM {$broken_link_tbl} LIKE 'ignore_link'");
                if(empty($col)){
                    // if it doesn't, update it with the "ignore_link" column
                    $update_table = "ALTER TABLE {$broken_link_tbl} ADD COLUMN ignore_link tinyint(1) DEFAULT 0 AFTER `check_count`";
                    $wpdb->query($update_table);
                }
            }

            // update the state of the DB to this point
            update_option('wpil_site_db_version', '1.0');
        }

        if((float)WPIL_STATUS_SITE_DB_VERSION < 1.16 || $force_update){
            $error_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$broken_link_tbl}'");
            if(!empty($error_tbl_exists)) {
                $col = $wpdb->query("SHOW COLUMNS FROM {$broken_link_tbl} LIKE 'sentence'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$broken_link_tbl} ADD COLUMN sentence varchar(1000) AFTER `ignore_link`";
                    $wpdb->query($update_table);
                }
            }

            $error_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$report_links_tbl}'");
            if(!empty($error_tbl_exists)) {
                $col = $wpdb->query("SHOW COLUMNS FROM {$report_links_tbl} LIKE 'location'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$report_links_tbl} ADD COLUMN location varchar(20) AFTER `post_type`";
                    $wpdb->query($update_table);
                }
            }

            update_option('wpil_site_db_version', '1.16');
        }

        if((float)WPIL_STATUS_SITE_DB_VERSION < 1.17 || $force_update){
            $keywrd_url_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$autolink_tbl}'");
            if(!empty($keywrd_url_tbl_exists)) {
                $col = $wpdb->query("SHOW COLUMNS FROM {$autolink_tbl} LIKE 'anchor'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$autolink_tbl} ADD COLUMN anchor text AFTER `post_type`";
                    $wpdb->query($update_table);
                }
            }

            update_option('wpil_site_db_version', '1.17');
        }

        if((float)WPIL_STATUS_SITE_DB_VERSION < 1.18 || $force_update){
            $keywrd_url_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$autolink_rule_tbl}'");
            if(!empty($keywrd_url_tbl_exists)) {
                $col = $wpdb->query("SHOW COLUMNS FROM {$autolink_rule_tbl} LIKE 'restrict_cats'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$autolink_rule_tbl} ADD COLUMN restrict_cats tinyint(1) DEFAULT 0 AFTER `link_once`";
                    $wpdb->query($update_table);
                }
            }

            $keywrd_url_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$autolink_rule_tbl}'");
            if(!empty($keywrd_url_tbl_exists)) {
                $col = $wpdb->query("SHOW COLUMNS FROM {$autolink_rule_tbl} LIKE 'restricted_cats'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$autolink_rule_tbl} ADD COLUMN restricted_cats text AFTER `restrict_cats`";
                    $wpdb->query($update_table);
                }
            }

            update_option('wpil_site_db_version', '1.18');
        }

        if((float)WPIL_STATUS_SITE_DB_VERSION < 1.19 || $force_update){
            $keywrd_url_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$autolink_rule_tbl}'");
            if(!empty($keywrd_url_tbl_exists)) {
                $col = $wpdb->query("SHOW COLUMNS FROM {$autolink_rule_tbl} LIKE 'restrict_date'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$autolink_rule_tbl} ADD COLUMN restrict_date tinyint(1) DEFAULT 0 AFTER `link_once`";
                    $wpdb->query($update_table);
                }

                $col = $wpdb->query("SHOW COLUMNS FROM {$autolink_rule_tbl} LIKE 'restricted_date'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$autolink_rule_tbl} ADD COLUMN restricted_date DATETIME AFTER `restrict_date`";
                    $wpdb->query($update_table);
                }
            }

            update_option('wpil_site_db_version', '1.19');
        }

        if((float)WPIL_STATUS_SITE_DB_VERSION < 1.20 || $force_update){
            $keywrd_url_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$autolink_rule_tbl}'");
            if(!empty($keywrd_url_tbl_exists)) {
                $col = $wpdb->query("SHOW COLUMNS FROM {$autolink_rule_tbl} LIKE 'select_links'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$autolink_rule_tbl} ADD COLUMN select_links tinyint(1) DEFAULT 0 AFTER `link_once`";
                    $wpdb->query($update_table);
                }
            }

            // make sure the possible links table is created too
//            Wpil_Keyword::preparePossibleLinksTable();

            update_option('wpil_site_db_version', '1.20');
        }

        if((float)WPIL_STATUS_SITE_DB_VERSION < 1.21 || $force_update){
            $keywrd_url_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$autolink_rule_tbl}'");
            if(!empty($keywrd_url_tbl_exists)) {
                $col = $wpdb->query("SHOW COLUMNS FROM {$autolink_rule_tbl} LIKE 'set_priority'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$autolink_rule_tbl} ADD COLUMN set_priority tinyint(1) DEFAULT 0 AFTER `select_links`";
                    $wpdb->query($update_table);
                }
                $col = $wpdb->query("SHOW COLUMNS FROM {$autolink_rule_tbl} LIKE 'priority_setting'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$autolink_rule_tbl} ADD COLUMN priority_setting int DEFAULT 0 AFTER `set_priority`";
                    $wpdb->query($update_table);
                }
            }

            update_option('wpil_site_db_version', '1.21');
        }

        if((float)WPIL_STATUS_SITE_DB_VERSION < 1.22 || $force_update){
            $changed_urls_exist = $wpdb->query("SHOW TABLES LIKE '{$url_links_tbl}'");
            if(!empty($changed_urls_exist)) {
                $col = $wpdb->query("SHOW COLUMNS FROM {$url_links_tbl} LIKE 'relative_link'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$url_links_tbl} ADD COLUMN relative_link tinyint(1) DEFAULT 0 AFTER `anchor`";
                    $wpdb->query($update_table);
                }
            }

            update_option('wpil_site_db_version', '1.22');
        }

        if((float)WPIL_STATUS_SITE_DB_VERSION < 1.23 || $force_update){
            $error_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$report_links_tbl}'");
            if(!empty($error_tbl_exists)) {
                $col = $wpdb->query("SHOW COLUMNS FROM {$report_links_tbl} LIKE 'broken_link_scanned'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$report_links_tbl} ADD COLUMN broken_link_scanned tinyint(1) DEFAULT 0 AFTER `location`";
                    $wpdb->query($update_table);
                }
            }

            update_option('wpil_site_db_version', '1.23');
        }

        if((float)WPIL_STATUS_SITE_DB_VERSION < 1.24 || $force_update){
            $trgt_kword_tbl_exists = $wpdb->query("SHOW TABLES LIKE '$target_keyword_tbl'");
            if(!empty($trgt_kword_tbl_exists)) {
                $col = $wpdb->query("SHOW COLUMNS FROM $target_keyword_tbl LIKE 'auto_checked'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE $target_keyword_tbl ADD COLUMN auto_checked tinyint(1) DEFAULT 0 AFTER `save_date`";
                    $wpdb->query($update_table);
                }
            }

            update_option('wpil_site_db_version', '1.24');
        }

        if((float)WPIL_STATUS_SITE_DB_VERSION < 1.25 || $force_update){
            $clk_tbl_exists = $wpdb->query("SHOW TABLES LIKE '$click_tracking_tbl'");
            if(!empty($clk_tbl_exists)) {
                $col = $wpdb->query("SHOW COLUMNS FROM $click_tracking_tbl LIKE 'link_location'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE $click_tracking_tbl ADD COLUMN link_location varchar(64) DEFAULT 'Body Content' AFTER `link_anchor`";
                    $wpdb->query($update_table);
                }
            }

            update_option('wpil_site_db_version', '1.25');
        }

        if((float)WPIL_STATUS_SITE_DB_VERSION < 1.26 || $force_update){
            $keywrd_url_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$autolink_rule_tbl}'");
            if(!empty($keywrd_url_tbl_exists)) {
                $col = $wpdb->query("SHOW COLUMNS FROM {$autolink_rule_tbl} LIKE 'case_sensitive'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$autolink_rule_tbl} ADD COLUMN case_sensitive tinyint(1) DEFAULT 0 AFTER `restricted_cats`";
                    $wpdb->query($update_table);
                }
            }

            update_option('wpil_site_db_version', '1.26');
        }

        if((float)WPIL_STATUS_SITE_DB_VERSION < 1.27 || $force_update){
            $keywrd_url_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$autolink_rule_tbl}'");
            if(!empty($keywrd_url_tbl_exists)) {
                $col = $wpdb->query("SHOW COLUMNS FROM {$autolink_rule_tbl} LIKE 'force_insert'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$autolink_rule_tbl} ADD COLUMN force_insert tinyint(1) DEFAULT 0 AFTER `case_sensitive`";
                    $wpdb->query($update_table);
                }
            }

            update_option('wpil_site_db_version', '1.27');
        }

        if((float)WPIL_STATUS_SITE_DB_VERSION < 1.28 || $force_update){
            $url_changer_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$url_changer_tbl}'");
            if(!empty($url_changer_tbl_exists)) {
                $col = $wpdb->query("SHOW COLUMNS FROM {$url_changer_tbl} LIKE 'wildcard_match'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$url_changer_tbl} ADD COLUMN wildcard_match tinyint(1) DEFAULT 0 AFTER `new`";
                    $wpdb->query($update_table);
                }
            }

            $url_links_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$url_links_tbl}'");
            if(!empty($url_links_tbl_exists)) {
                $col = $wpdb->query("SHOW COLUMNS FROM {$url_links_tbl} LIKE 'original_url'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$url_links_tbl} ADD COLUMN original_url text NOT NULL AFTER `anchor`";
                    $wpdb->query($update_table);
                }
            }

            $broken_link_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$broken_link_tbl}'");
            if(!empty($broken_link_tbl_exists)) {
                $col = $wpdb->query("SHOW COLUMNS FROM {$broken_link_tbl} LIKE 'anchor'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$broken_link_tbl} ADD COLUMN anchor text NOT NULL AFTER `sentence`";
                    $wpdb->query($update_table);
                }
            }

            update_option('wpil_site_db_version', '1.28');
        }

        if((float)WPIL_STATUS_SITE_DB_VERSION < 1.29 || $force_update){
            $autolink_rule_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$autolink_rule_tbl}'");
            if(!empty($autolink_rule_tbl_exists)) {
                $col = $wpdb->query("SHOW COLUMNS FROM {$autolink_rule_tbl} LIKE 'link'"); // This time we have to make sure `link` _does_ exist
                if(!empty($col)){
                    $update_table = "ALTER TABLE {$autolink_rule_tbl} CHANGE `link` `link` VARCHAR(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL";
                    $wpdb->query($update_table);
                }
            }

            update_option('wpil_site_db_version', '1.29');
        }

        if((float)WPIL_STATUS_SITE_DB_VERSION < 1.30 || $force_update){
            $autolink_rule_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$autolink_rule_tbl}'");
            if(!empty($autolink_rule_tbl_exists)) {
                $col = $wpdb->query("SHOW COLUMNS FROM {$autolink_rule_tbl} LIKE 'limit_inserts'"); // since we're adding cols, make sure it doens't already exist
                if(empty($col)){
                    $update_table = "ALTER TABLE {$autolink_rule_tbl} ADD COLUMN limit_inserts tinyint(1) UNSIGNED NOT NULL DEFAULT 0 AFTER `link_once`";
                    $wpdb->query($update_table);
                }

                $col = $wpdb->query("SHOW COLUMNS FROM {$autolink_rule_tbl} LIKE 'insert_limit'"); // since we're adding cols, make sure it doens't already exist
                if(empty($col)){
                    $update_table = "ALTER TABLE {$autolink_rule_tbl} ADD COLUMN insert_limit INT(10) UNSIGNED NOT NULL DEFAULT 0 AFTER `limit_inserts`";
                    $wpdb->query($update_table);
                }

                $col = $wpdb->query("SHOW COLUMNS FROM {$autolink_rule_tbl} LIKE 'prioritize_longtail'"); // since we're adding cols, make sure it doens't already exist
                if(empty($col)){
                    $update_table = "ALTER TABLE {$autolink_rule_tbl} ADD COLUMN prioritize_longtail tinyint(1) UNSIGNED NOT NULL DEFAULT 0 AFTER `priority_setting`";
                    $wpdb->query($update_table);
                }

                $col = $wpdb->query("SHOW COLUMNS FROM {$autolink_rule_tbl} LIKE 'same_lang'"); // since we're adding cols, make sure it doens't already exist
                if(empty($col)){
                    $update_table = "ALTER TABLE {$autolink_rule_tbl} ADD COLUMN same_lang tinyint(1) UNSIGNED NOT NULL DEFAULT 0 AFTER `force_insert`";
                    $wpdb->query($update_table);
                }
            }

            update_option('wpil_site_db_version', '1.30');
        }

        if((float)WPIL_STATUS_SITE_DB_VERSION < 1.31 || $force_update){
            $link_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$report_links_tbl}'");
            if(!empty($link_tbl_exists)) {
                $col = $wpdb->query("SHOW COLUMNS FROM {$report_links_tbl} LIKE 'target_id'"); // since we're adding cols, make sure it doens't already exist
                if(empty($col)){
                    $update_table = "ALTER TABLE {$report_links_tbl} ADD COLUMN target_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0 AFTER `post_id`";
                    $wpdb->query($update_table);
                }

                $col = $wpdb->query("SHOW COLUMNS FROM {$report_links_tbl} LIKE 'target_type'"); // since we're adding cols, make sure it doens't already exist
                if(empty($col)){
                    $update_table = "ALTER TABLE {$report_links_tbl} ADD COLUMN target_type TEXT AFTER `target_id`";
                    $wpdb->query($update_table);
                }
            }

            update_option('wpil_site_db_version', '1.31');
        }

        if((float)WPIL_STATUS_SITE_DB_VERSION < 1.32 || $force_update){
            $keywrd_url_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$autolink_tbl}'");
            if(!empty($keywrd_url_tbl_exists)) {
                $index = $wpdb->query("SHOW INDEX FROM {$autolink_tbl} WHERE COLUMN_NAME = 'keyword_id'"); // since we're adding cols, make sure it doens't already exist
                if(empty($index)){
                    $update_table = "ALTER TABLE {$autolink_tbl} ADD INDEX(`keyword_id`)";
                    $wpdb->query($update_table);
                }
            }

            update_option('wpil_site_db_version', '1.32');
        }

        if((float)WPIL_STATUS_SITE_DB_VERSION < 1.33 || $force_update){
            $link_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$report_links_tbl}'");
            if(!empty($link_tbl_exists)) {
                $col = $wpdb->query("SHOW COLUMNS FROM {$report_links_tbl} LIKE 'link_whisper_created'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$report_links_tbl} ADD COLUMN link_whisper_created tinyint(1) DEFAULT 0 AFTER `broken_link_scanned`";
                    $wpdb->query($update_table);
                }

                $col = $wpdb->query("SHOW COLUMNS FROM {$report_links_tbl} LIKE 'is_autolink'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$report_links_tbl} ADD COLUMN is_autolink tinyint(1) DEFAULT 0 AFTER `link_whisper_created`";
                    $wpdb->query($update_table);
                }
            }

            update_option('wpil_site_db_version', '1.33');
        }

        if((float)WPIL_STATUS_SITE_DB_VERSION < 1.34 || $force_update){
            $error_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$broken_link_tbl}'");
            if(!empty($error_tbl_exists)){
                // find out if the table has a ignore_link col
                $row = $wpdb->get_row("SHOW COLUMNS FROM {$broken_link_tbl} LIKE 'post_id'");
                if(!empty($row) && isset($row->Type) && is_string($row->Type) && false !== strpos($row->Type, 'int(10)')){
                    // if it doesn't, update it with the "ignore_link" column
                    $update_table = "ALTER TABLE {$broken_link_tbl} CHANGE `post_id` `post_id` BIGINT(20) UNSIGNED NOT NULL";
                    $wpdb->query($update_table);
                }
            }

            $ignore_error_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$ignore_broken_link_tbl}'");
            if(!empty($ignore_error_tbl_exists)){
                // find out if the table has a ignore_link col
                $row = $wpdb->get_row("SHOW COLUMNS FROM {$ignore_broken_link_tbl} LIKE 'post_id'");
                if(!empty($row) && isset($row->Type) && is_string($row->Type) && false !== strpos($row->Type, 'int(10)')){
                    // if it doesn't, update it with the "ignore_link" column
                    $update_table = "ALTER TABLE {$ignore_broken_link_tbl} CHANGE `post_id` `post_id` BIGINT(20) UNSIGNED NOT NULL";
                    $wpdb->query($update_table);
                }
            }

            if(!empty($wpdb->query("SHOW TABLES LIKE '{$autolink_rule_tbl}'"))){
                // find out if the table has a ignore_link col
                $row = $wpdb->get_row("SHOW COLUMNS FROM {$autolink_rule_tbl} LIKE 'post_id'");
                if(!empty($row) && isset($row->Type) && is_string($row->Type) && false !== strpos($row->Type, 'int(10)')){
                    // if it doesn't, update it with the "ignore_link" column
                    $update_table = "ALTER TABLE {$autolink_rule_tbl} CHANGE `post_id` `post_id` BIGINT(20) UNSIGNED NOT NULL";
                    $wpdb->query($update_table);
                }
            }

            if(!empty($wpdb->query("SHOW TABLES LIKE '{$autolink_select_tbl}'"))){
                // find out if the table has a ignore_link col
                $row = $wpdb->get_row("SHOW COLUMNS FROM {$autolink_select_tbl} LIKE 'post_id'");
                if(!empty($row) && isset($row->Type) && is_string($row->Type) && false !== strpos($row->Type, 'int(10)')){
                    // if it doesn't, update it with the "ignore_link" column
                    $update_table = "ALTER TABLE {$autolink_select_tbl} CHANGE `post_id` `post_id` BIGINT(20) UNSIGNED NOT NULL";
                    $wpdb->query($update_table);
                }
            }

            if(!empty($wpdb->query("SHOW TABLES LIKE '{$url_links_tbl}'"))){
                // find out if the table has a ignore_link col
                $row = $wpdb->get_row("SHOW COLUMNS FROM {$url_links_tbl} LIKE 'post_id'");
                if(!empty($row) && isset($row->Type) && is_string($row->Type) && false !== strpos($row->Type, 'int(10)')){
                    // if it doesn't, update it with the "ignore_link" column
                    $update_table = "ALTER TABLE {$url_links_tbl} CHANGE `post_id` `post_id` BIGINT(20) UNSIGNED NOT NULL";
                    $wpdb->query($update_table);
                }
            }

            if(!empty($wpdb->query("SHOW TABLES LIKE '{$related_posts_tbl}'"))){
                // find out if the table has a ignore_link col
                $row = $wpdb->get_row("SHOW COLUMNS FROM {$related_posts_tbl} LIKE 'post_id'");
                if(!empty($row) && isset($row->Type) && is_string($row->Type) && false !== strpos($row->Type, 'int(10)')){
                    // if it doesn't, update it with the "ignore_link" column
                    $update_table = "ALTER TABLE {$related_posts_tbl} CHANGE `post_id` `post_id` BIGINT(20) UNSIGNED NOT NULL";
                    $wpdb->query($update_table);
                }
            }

            update_option('wpil_site_db_version', '1.34');
        }

        if((float)WPIL_STATUS_SITE_DB_VERSION < 1.35 || $force_update){
            if(!empty($wpdb->query("SHOW TABLES LIKE '{$autolink_rule_tbl}'"))){
                // find out if the table has a ignore_link col
                $exists = $wpdb->query("SHOW COLUMNS FROM {$autolink_rule_tbl} LIKE 'restrict_to_live'");
                if(empty($exists)){
                    // if it doesn't, update it with the "ignore_link" column
                    $update_table = "ALTER TABLE {$autolink_rule_tbl} ADD COLUMN restrict_to_live tinyint(1) UNSIGNED NOT NULL DEFAULT 0 AFTER `link_once`";
                    $wpdb->query($update_table);
                }
            }

            update_option('wpil_site_db_version', '1.35');
        }

        if((float)WPIL_STATUS_SITE_DB_VERSION < 1.36 || $force_update){
            $clk_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$click_tracking_tbl}'");
            if(!empty($clk_tbl_exists)) {
                $col = $wpdb->query("SHOW COLUMNS FROM {$click_tracking_tbl} LIKE 'tracking_id'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$click_tracking_tbl} ADD COLUMN tracking_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0 AFTER `link_location`, ADD INDEX (`tracking_id`)";
                    $wpdb->query($update_table);
                }
            }

            $link_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$report_links_tbl}'");
            if(!empty($link_tbl_exists)) {
                $col = $wpdb->query("SHOW COLUMNS FROM {$report_links_tbl} LIKE 'tracking_id'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$report_links_tbl} ADD COLUMN tracking_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0 AFTER `is_autolink`, ADD INDEX (`tracking_id`)";
                    $wpdb->query($update_table);
                }

                $col = $wpdb->query("SHOW COLUMNS FROM {$report_links_tbl} LIKE 'module_link'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$report_links_tbl} ADD COLUMN module_link tinyint(1) DEFAULT 0 AFTER `tracking_id`";
                    $wpdb->query($update_table);
                }

                $col = $wpdb->query("SHOW COLUMNS FROM {$report_links_tbl} LIKE 'link_context'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$report_links_tbl} ADD COLUMN link_context tinyint(1) DEFAULT 0 AFTER `module_link`";
                    $wpdb->query($update_table);
                }
            }

            update_option('wpil_site_db_version', '1.36');
        }

        if((float)WPIL_STATUS_SITE_DB_VERSION < 1.37 || $force_update){
            $link_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$report_links_tbl}'");
            if(!empty($link_tbl_exists)) {
                $col = $wpdb->query("SHOW COLUMNS FROM {$report_links_tbl} LIKE 'post_type'");
                if(!empty($col)){
                    $index = $wpdb->query("SHOW INDEX FROM {$report_links_tbl} WHERE COLUMN_NAME = 'post_type'"); // since we're adding cols, make sure it doens't already exist
                    if(empty($index)){
                        $update_table = "ALTER TABLE {$report_links_tbl} ADD INDEX(`post_type`)";
                        $wpdb->query($update_table);
                    }
                }
            }

            update_option('wpil_site_db_version', '1.37');
        }

        if((float)WPIL_STATUS_SITE_DB_VERSION < 1.38 || $force_update){
            $link_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$report_links_tbl}'");
            if(!empty($link_tbl_exists)) {
                $col = $wpdb->get_row("SHOW COLUMNS FROM {$report_links_tbl} LIKE 'post_type'");
                if(!empty($col) && isset($col->Type) && $col->Type === 'text'){
                    $update_table = "ALTER TABLE {$report_links_tbl} MODIFY `post_type` VARCHAR(8)";
                    $wpdb->query($update_table);
                }

                $col = $wpdb->get_row("SHOW COLUMNS FROM {$report_links_tbl} LIKE 'target_type'");
                if(!empty($col) && isset($col->Type) && $col->Type === 'text'){
                    $update_table = "ALTER TABLE {$report_links_tbl} MODIFY `target_type` VARCHAR(8)";
                    $wpdb->query($update_table);
                }

                // make sure it has an index too!
                if(!empty($col)){
                    $index = $wpdb->query("SHOW INDEX FROM {$report_links_tbl} WHERE COLUMN_NAME = 'target_type'"); 
                    if(empty($index)){
                        $update_table = "ALTER TABLE {$report_links_tbl} ADD INDEX(`target_type`)";
                        $wpdb->query($update_table);
                    }
                }

                $col = $wpdb->query("SHOW COLUMNS FROM {$report_links_tbl} LIKE 'target_id'");
                if(!empty($col)){
                    $index = $wpdb->query("SHOW INDEX FROM {$report_links_tbl} WHERE COLUMN_NAME = 'target_id'"); 
                    if(empty($index)){
                        $update_table = "ALTER TABLE {$report_links_tbl} ADD INDEX(`target_id`)";
                        $wpdb->query($update_table);
                    }
                }
            }

            update_option('wpil_site_db_version', '1.38');
        }

        if((float)WPIL_STATUS_SITE_DB_VERSION < 1.39 || $force_update){
            $clk_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$click_tracking_tbl}'");
            if(!empty($clk_tbl_exists)) {
                $index = $wpdb->query("SHOW INDEX FROM {$click_tracking_tbl} WHERE COLUMN_NAME = 'post_type'"); 
                if(empty($index)){
                    $update_table = "ALTER TABLE {$click_tracking_tbl} ADD INDEX(`post_type`)";
                    $wpdb->query($update_table);
                }
            }

            update_option('wpil_site_db_version', '1.39');
        }

        if((float)WPIL_STATUS_SITE_DB_VERSION < 1.40 || $force_update){
            $mbddng_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$ai_embedding_tbl}'");
            if(!empty($mbddng_tbl_exists)){
                $col = $wpdb->query("SHOW COLUMNS FROM {$ai_embedding_tbl} LIKE 'is_empty'");
                if(empty($col)){
                    $update_table = "ALTER TABLE {$ai_embedding_tbl} ADD COLUMN `is_empty` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER `embed_data`";
                    $wpdb->query($update_table);
                }
            }

            $mbddng_clc_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$ai_embedding_calc_tbl}'");
            if(!empty($mbddng_clc_tbl_exists)){
                $col = $wpdb->query("SHOW COLUMNS FROM {$ai_embedding_calc_tbl} LIKE 'calc_index'");
                if(empty($col)){
                    $update_table = "ALTER TABLE {$ai_embedding_calc_tbl} ADD COLUMN `calc_index` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0 AFTER `calculation`, ADD COLUMN `calc_count` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0 AFTER `calc_index`;";
                    $wpdb->query($update_table);

                    if((float)WPIL_STATUS_SITE_DB_VERSION < 1.40){
                        $last_ind = Wpil_AI::get_last_embedding_index();
                        if(!empty($last_ind)){
                            $wpdb->update($ai_embedding_calc_tbl, array('calc_index' => $last_ind), array('calc_index' => 0));
                        }
                    }
                }
            
                if(empty(get_option('wpil_term_index_cleanup', '0')) && empty(Wpil_Settings::getTermTypes())){
                    $wpdb->query("DELETE FROM {$ai_embedding_tbl} WHERE `post_type` = 'term'");
                    $wpdb->query("DELETE FROM {$ai_embedding_calc_tbl} WHERE `post_type` = 'term'");
                    $wpdb->query("DELETE FROM {$ai_product_tbl} WHERE `post_type` = 'term'");
                    $wpdb->query("DELETE FROM {$ai_keyword_tbl} WHERE `post_type` = 'term'");
                    update_option('wpil_term_index_cleanup', '1');
                }
            }

            update_option('wpil_site_db_version', '1.40');
        }

        if((float)WPIL_STATUS_SITE_DB_VERSION < 1.41 || $force_update){
            $mbddng_clc_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$ai_embedding_calc_tbl}'");
            if(!empty($mbddng_clc_tbl_exists)){
                $col = $wpdb->query("SHOW COLUMNS FROM {$ai_embedding_calc_tbl} LIKE 'calc_index'");
                if(!empty($col)){
                    $index = $wpdb->query("SHOW INDEX FROM {$ai_embedding_calc_tbl} WHERE COLUMN_NAME = 'calc_index'"); 
                    if(empty($index)){
                        $update_table = "ALTER TABLE {$ai_embedding_calc_tbl} ADD INDEX(`calc_index`)";
                        $wpdb->query($update_table);
                    }
                }
            }

            update_option('wpil_site_db_version', '1.41');
        }

        if((float)WPIL_STATUS_SITE_DB_VERSION < 1.42 || $force_update){
            $sggstn_sntnc_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$ai_suggestion_sentence_tbl}'");
            if(!empty($sggstn_sntnc_tbl_exists)){
                $col = $wpdb->query("SHOW COLUMNS FROM {$ai_suggestion_sentence_tbl} LIKE 'has_link'");
                if(empty($col)){
                    $update_table = "ALTER TABLE {$ai_suggestion_sentence_tbl} ADD COLUMN has_link tinyint(1) DEFAULT 0 AFTER `data_type`";
                    $wpdb->query($update_table);
                }
            }

            update_option('wpil_site_db_version', '1.42');
        }

        if((float)WPIL_STATUS_SITE_DB_VERSION < 1.43 || $force_update){
            $sggstd_ncr_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$ai_suggested_anchor_tbl}'");
            if(!empty($sggstd_ncr_tbl_exists)){
                $col = $wpdb->query("SHOW COLUMNS FROM {$ai_suggested_anchor_tbl} LIKE 'link_score'");
                if(empty($col)){
                    $update_table = "ALTER TABLE {$ai_suggested_anchor_tbl} ADD COLUMN link_score int(4) DEFAULT 0 AFTER `target_data_type`";
                    $wpdb->query($update_table);
                }
            }

            update_option('wpil_site_db_version', '1.43');
        }
        
        if((float)WPIL_STATUS_SITE_DB_VERSION < 1.44 || $force_update){
            $autolink_rule_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$autolink_rule_tbl}'");
            if(!empty($autolink_rule_tbl_exists)) {
                $col = $wpdb->query("SHOW COLUMNS FROM {$autolink_rule_tbl} LIKE 'auto_imported'"); // since we're adding cols, make sure it doens't already exist
                if(empty($col)){
                    $update_table = "ALTER TABLE {$autolink_rule_tbl} ADD COLUMN auto_imported tinyint(1) UNSIGNED NOT NULL DEFAULT 0 AFTER `same_lang`";
                    $wpdb->query($update_table);
                }

                $col = $wpdb->query("SHOW COLUMNS FROM {$autolink_rule_tbl} LIKE 'auto_managed'"); // since we're adding cols, make sure it doens't already exist
                if(empty($col)){
                    $update_table = "ALTER TABLE {$autolink_rule_tbl} ADD COLUMN auto_managed tinyint(1) UNSIGNED NOT NULL DEFAULT 0 AFTER `auto_imported`";
                    $wpdb->query($update_table);
                }
            }

            update_option('wpil_site_db_version', '1.44');
        }

        if((float)WPIL_STATUS_SITE_DB_VERSION < 1.45 || $force_update){
            $link_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$report_links_tbl}'");
            if(!empty($link_tbl_exists)) {
                $col = $wpdb->query("SHOW COLUMNS FROM {$report_links_tbl} LIKE 'anchor_word_count'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$report_links_tbl} ADD COLUMN anchor_word_count INT(10) UNSIGNED NOT NULL DEFAULT 0 AFTER `anchor`";
                    $wpdb->query($update_table);
                }

                $col = $wpdb->query("SHOW COLUMNS FROM {$report_links_tbl} LIKE 'ai_relation_score'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$report_links_tbl} ADD COLUMN ai_relation_score DOUBLE UNSIGNED NOT NULL DEFAULT '0' AFTER `link_context`";
                    $wpdb->query($update_table);
                }
            }

            update_option('wpil_site_db_version', '1.45');
        }

        if((float)WPIL_STATUS_SITE_DB_VERSION < 1.46 || $force_update){
            $link_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$ai_credit_tbl}'");
            if(!empty($link_tbl_exists)) {
                $col = $wpdb->query("SHOW COLUMNS FROM {$ai_credit_tbl} LIKE 'credits_used'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$ai_credit_tbl} 
                                    ADD COLUMN credits_used DECIMAL(10,4) UNSIGNED NOT NULL DEFAULT 0.0000 
                                    AFTER `total_tokens`";
                    $wpdb->query($update_table);
                }
            }

            $mbddng_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$ai_embedding_tbl}'");
            if(!empty($mbddng_tbl_exists)){
                $col = $wpdb->query("SHOW COLUMNS FROM {$ai_embedding_tbl} LIKE 'is_empty'");
                if(empty($col)){
                    $update_table = "ALTER TABLE {$ai_embedding_tbl} ADD COLUMN `is_empty` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER `embed_data`";
                    $wpdb->query($update_table);
                }
            }

            update_option('wpil_site_db_version', '1.46');
        }

        // todo create a database index for click tracking's user_ip column if people find that it takes too long to load the user_ip view
/*
        if((float)WPIL_STATUS_SITE_DB_VERSION < 1.23 || $force_update){
            $error_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$report_links_tbl}'");
            if(!empty($error_tbl_exists)) {
                $col = $wpdb->query("SHOW COLUMNS FROM {$report_links_tbl} LIKE 'broken_link_scanned'");
                if (empty($col)) {
                    $update_table = "ALTER TABLE {$report_links_tbl} ADD COLUMN broken_link_scanned tinyint(1) DEFAULT 0 AFTER `location`";
                    $wpdb->query($update_table);
                }
            }

            update_option('wpil_site_db_version', '1.23');
        }*/
    }

    /**
     * Runs our deactivation routines when the plugin is turned off
     **/
    public static function deactivate(){
        // clear the cron schedules
        Wpil_AI::clear_batch_process_cron();
        Wpil_ClickTracker::clear_cron_schedules();
        Wpil_Error::clearCronSchedules();
        Wpil_TargetKeyword::clear_cron_schedules();
    }

    /**
     * Modifies the post's row actions to add an "Add Inbound Links" button to the row actions.
     * Only adds the link to post types that we create links for.
     * 
     * @param $actions
     * @param $object
     * @return $actions
     **/
    public static function modify_list_row_actions( $actions, $object ) {
        $type = is_a($object, 'WP_Post') ? $object->post_type: $object->taxonomy;

        if(!in_array($type, Wpil_Settings::getAllTypes())){
            return $actions;
        }

        $page = (isset($_GET['paged']) && !empty($_GET['paged'])) ? '&paged=' . (int)$_GET['paged']: '';

        if(is_a($object, 'WP_Post')){
            //$actions['wpil-add-inbound-links'] = '<a target=_blank href="' . admin_url("admin.php?post_id={$object->ID}&page=link_whisper&type=inbound_suggestions_page&ret_url=" . base64_encode(admin_url("edit.php?post_type={$type}{$page}&direct_return=1"))) . '">Add Inbound Links</a>';
        }else{
            $update_count = get_option('wpil_free_update_count', 0);
            update_option('wpil_free_update_count', $update_count += 1);
        }

        // disabling in 0.7.8... Shouldn't need this anymore since it's been ~4 years since the class has been used.
        //Wpil_Link::removeLinkClass();
    }

	/**
	 * Add new columns for SEO title, description and focus keywords.
	 *
	 * @param array $columns Array of column names.
	 *
	 * @return array
	 */
	public static function add_columns($columns){
		global $post_type;

        if(!in_array($post_type, Wpil_Settings::getPostTypes())){
            return $columns;
        }
        
		$columns['wpil-link-stats'] = esc_html__('Link Stats', 'wpil');

		return $columns;
	}

    /**
	 * Add content for custom column in the posts && pages archives.
	 *
	 * @param string $column_name The name of the column to display.
	 * @param int    $post_id     The current post ID.
	 */
	public static function columns_contents($column_name, $post_id){
        if('wpil-link-stats' === $column_name){
            $post_status = get_post_status($post_id);
            // exit if the current post is in a status we don't process
            if(!in_array($post_status, Wpil_Settings::getPostStatuses())){
                $status_obj = get_post_status_object($post_status);
                $status = (!empty($status_obj)) ? $status_obj->label: ucfirst($post_status);
                ?>
                <span class="wpil-link-stats-column-display wpil-link-stats-content">
                    <strong><?php esc_html_e('Links: ', 'wpil'); ?></strong>
                    <span><span><?php echo sprintf(__('%s post processing %s.', 'wpil'), $status, '<a href="' . admin_url("admin.php?page=link_whisper_settings") . '">' . __('not set', 'wpil') . '</a>'); ?></span></span>
                </span>
                <?php
                return;
            }

            $post = new Wpil_Model_Post($post_id);
            $post_scanned = !empty(get_post_meta($post_id, 'wpil_sync_report3', true));
            $inbound_internal = (int) $post->getInboundInternalLinks(true);
            $outbound_internal = (int) $post->getOutboundInternalLinks(true);
            $outbound_external = (int) $post->getOutboundExternalLinks(true);

            ?>
            <span class="wpil-link-stats-column-display wpil-link-stats-content">
                <?php if($post_scanned){ ?>
                <strong><?php esc_html_e('Links: ', 'wpil'); ?></strong>
                <span title="<?php esc_attr_e('Inbound Internal Links', 'wpil'); ?>"><span class="dashicons dashicons-arrow-down-alt <?php echo (!empty($inbound_internal)) ? 'wpil-has-inbound': ''; ?>"></span><span><?php echo $inbound_internal; ?></span></span>
                <span class="divider"></span>
                <span title="<?php esc_attr_e('Outbound Internal Links', 'wpil'); ?>"><a href="<?php echo esc_url(get_edit_post_link($post_id)); ?>"><span class="dashicons dashicons-external  <?php echo (!empty($outbound_internal)) ? 'wpil-has-outbound': ''; ?>"></span> <span><?php echo $outbound_internal; ?></span></a></span>
                <span class="divider"></span>
                <span title="<?php esc_attr_e('Outbound External Links', 'wpil'); ?>"><span class="dashicons dashicons-admin-site-alt3 <?php echo (!empty($outbound_external)) ? 'wpil-has-outbound': ''; ?>"></span> <span><?php echo $outbound_external; ?></span></span>
                <?php }else{ ?>
                    <?php $scan_link = $post->getLinks()->refresh; ?>
                    <strong><?php esc_html_e('Links: Not Scanned', 'wpil'); ?></strong>
                    <span title="<?php esc_attr_e('Scan Links', 'wpil'); ?>"><a target=_blank href="<?php echo esc_url($scan_link); ?>"><span><?php esc_html_e('Scan Links', 'wpil'); ?></span> <span class="dashicons dashicons-update-alt wpil-refresh-links"></span></a></span>
                <?php } ?>
            </span>
        <?php
        }
	}

    /**
     * Gets SVG icon content so that we can use the HTML in PHP
     * 
     **/
    public static function get_svg_icon($name = '', $return_reference = false, $styles = array()){
        if(empty($name)){
            return '';
        }

        $path = '';
        $id = '';
        $viewbox = '';
        $svg = '';
        switch ($name){
            case 'new-tab-1':
                $path = '<g id="wpil-svg-new-tab-1-icon-path">
                            <g fill-rule="evenodd" stroke="none" stroke-width="1" transform="matrix(0.27272726,0,0,0.27272726,-1.6363636,-1.6363636)">
                                <g>
                                <path d="m 45.5,14 h 33 7.5 v 7.5 33 7.5 h -8 v 8 h 8 c 4.418278,0 8,-3.590712 8,-8 V 54.5 21.5 14 C 94,9.581722 90.409288,6 86,6 H 78.5 45.5 38 c -4.418278,0 -8,3.5907123 -8,8 v 8 h 8 V 14 Z M 6,38.008515 C 6,33.585535 9.578055,30 14.008515,30 h 47.98297 C 66.414466,30 70,33.578055 70,38.008515 v 47.98297 C 70,90.414466 66.421945,94 61.991485,94 H 14.008515 C 9.5855345,94 6,90.421945 6,85.991485 Z M 42,46 H 34 V 58 H 22 v 8 h 12 v 12 h 8 V 66 H 54 V 58 H 42 Z" />
                                </g>
                            </g>
                        </g>';
                $id = 'wpil-svg-new-tab-1-icon-path';
                break;
            case 'new-tab-2':
                $path = '<g id="wpil-svg-new-tab-2-icon-path" transform="translate(0,-4.8755901)">
                            <path d="M 23.707456,18.327668 V 15.405081 H 13.462555 c -0.807816,0 -1.463142,-0.654761 -1.463142,-1.461874 V 6.6300173 c 0,-0.8079967 -0.654445,-1.4618817 -1.461967,-1.4618817 H 3.2185296 c -0.8078122,0 -1.4631363,0.65476 -1.4631363,1.4618817 v 7.3123117 c 0,0.806825 -0.6541545,1.461584 -1.4628478,1.461584 v 2.923755 z" fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10" stroke-width="0.585091" />
                            <path d="m 23.707456,15.403913 c -0.807816,0 -1.463141,-0.653593 -1.463141,-1.461584 V 8.8232713 c 0,-0.80712 -0.655325,-1.461879 -1.464309,-1.461879 h -7.317451 c -0.808986,0 -1.463142,0.654759 -1.463142,1.461879 v 5.1190577 c 0,0.807991 0.655326,1.461584 1.464019,1.461584 z" fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10" stroke-width="0.585091" />
                            <path d="M 17.121717,9.5546463 V 13.20978 Z" fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10" stroke-width="0.585094" />
                            <path d="m 15.292428,11.382361 h 3.658577 z" fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10" stroke-width="0.585094" />
                        </g>';
                $id = 'wpil-svg-new-tab-2-icon-path';
                break;
            case 'outbound-1':
                $path = '<g id="wpil-svg-outbound-1-icon-path" transform="matrix(0.046875,0,0,0.046875,0.0234375,0.02343964)">
                            <path d="M 473.563,227.063 407.5,161 262.75,305.75 c -25,25 -49.563,41 -74.5,16 -25,-25 -9,-49.5 16,-74.5 L 349,102.5 283.937,37.406 c -14.188,-14.188 -2,-37.906 19,-37.906 h 170.625 c 20.938,0 37.938,16.969 37.938,37.906 v 170.688 c 0,20.937 -23.687,33.187 -37.937,18.969 z M 63.5,447.5 h 320 V 259.313 l 64,64 V 447.5 c 0,35.375 -28.625,64 -64,64 h -320 c -35.375,0 -64,-28.625 -64,-64 v -320 c 0,-35.344 28.625,-64 64,-64 h 124.188 l 64,64 H 63.5 Z" />
                        </g>';
                $id = 'wpil-svg-outbound-1-icon-path';
                break;
            case 'outbound-2':
                $path = '<g id="wpil-svg-outbound-2-icon-path" transform="matrix(1.2,0,0,1.2,-2.4,-2.4)">
                            <path d="m 20,18 c 0,1.103 -0.897,2 -2,2 H 6 C 4.897,20 4,19.103 4,18 V 6 C 4,4.897 4.897,4 6,4 h 7 V 2 H 6 C 3.794,2 2,3.794 2,6 v 12 c 0,2.206 1.794,4 4,4 h 12 c 2.206,0 4,-1.794 4,-4 v -7 h -2 z"/>
                            <polygon points="22,9 21.999,2 15,2 15,4 18.586,4 13.465,9.121 14.879,10.535 20,5.415 20,9 " />
                        </g>';
                $id = 'wpil-svg-outbound-2-icon-path';
                break;
            case 'outbound-3':
                $path = '<g id="wpil-svg-outbound-3-icon-path">
                            <g transform="matrix(0.92307697,0,0,0.92307697,-209.5794,-43.317149)">
                                <g fill-rule="evenodd" id="action" stroke="none" stroke-width="1" transform="translate(225.04432,44.926904)">
                                    <g transform="translate(-224.99998,-44.999995)">
                                        <g transform="translate(227,47)">
                                            <path d="m 21,12 c 0.552285,0 1,-0.447715 1,-1 V 5 C 22,4.4477152 21.552285,4 21,4 h -6 c -0.552285,0 -1,0.4477152 -1,1 0,0.5522847 0.447715,1 1,1 h 3.580002 l -6.287109,6.292893 c -0.390524,0.390524 -0.390524,1.02369 0,1.414214 0.390524,0.390524 1.02369,0.390524 1.414214,0 L 20,7.4190674 V 11 c 0,0.552285 0.447715,1 1,1 z" />
                                            <path d="m 20,18 v 6.008845 C 20,25.108529 19.110326,26 18.008845,26 H 1.991155 C 0.89147046,26 0,25.110326 0,24.008845 V 7.991155 C 0,6.8914705 0.88967395,6 1.991155,6 H 8 V 8 H 2 v 16 h 16 v -6 z" />
                                            <path d="M 24.008845,0 C 25.108529,0 26,0.88967395 26,1.991155 v 16.01769 C 26,19.108529 25.110326,20 24.008845,20 H 7.991155 C 6.8914705,20 6,19.110326 6,18.008845 V 1.991155 C 6,0.89147046 6.8896739,0 7.991155,0 Z M 8,2 H 24 V 18 H 8 Z" />
                                        </g>
                                    </g>
                                </g>
                            </g>
                        </g>';
                $id = 'wpil-svg-outbound-3-icon-path';
                break;
            case 'outbound-4':
                $path = '<g id="wpil-svg-outbound-4-icon-path">
                            <g fill-rule="evenodd" id="action" stroke="none" stroke-width="1" transform="matrix(0.92307696,0,0,0.92307696,-1.8461539,-1.8461539)">
                                <g transform="translate(-270,-45)">
                                    <g transform="translate(272,47)">
                                        <path d="m 20,22 v 2.008845 C 20,25.108529 19.110326,26 18.008845,26 H 16 v -2 h 2 v -2 z m 0,-2 v -2 h -2 v 2 z m -6,6 h -3 v -2 h 3 z M 9,26 H 6 V 24 H 9 Z M 4,26 H 1.991155 C 0.89147046,26 0,25.110326 0,24.008845 V 22 h 2 v 2 H 4 Z M 0,20 v -3 h 2 v 3 z m 0,-5 v -3 h 2 v 3 z M 0,10 V 7.991155 C 0,6.8914705 0.88967395,6 1.991155,6 H 4 V 8 H 2 v 2 z M 6,6 H 8 V 8 H 6 Z" />
                                        <path d="M 24.008845,0 C 25.108529,0 26,0.88967395 26,1.991155 v 16.01769 C 26,19.108529 25.110326,20 24.008845,20 H 7.991155 C 6.8914705,20 6,19.110326 6,18.008845 V 1.991155 C 6,0.89147046 6.8896739,0 7.991155,0 Z M 8,2 H 24 V 18 H 8 Z" />
                                        <path d="m 21,12 c 0.552285,0 1,-0.447715 1,-1 V 5 C 22,4.4477152 21.552285,4 21,4 h -6 c -0.552285,0 -1,0.4477152 -1,1 0,0.5522847 0.447715,1 1,1 h 3.580002 l -6.287109,6.292893 c -0.390524,0.390524 -0.390524,1.02369 0,1.414214 0.390524,0.390524 1.02369,0.390524 1.414214,0 L 20,7.4190674 V 11 c 0,0.552285 0.447715,1 1,1 z" />
                                    </g>
                                </g>
                            </g>
                        </g>';
                $id = 'wpil-svg-outbound-4-icon-path';
                break;
            case 'outbound-5':
                $path = '<g id="wpil-svg-outbound-5-icon-path">
                            <g transform="matrix(0.3,0,0,0.3,-2.4,-2.4)">
                                <path d="M 73.788323,16 44.56401,45.224313 c -1.715534,1.715534 -1.718018,4.503944 2.89e-4,6.222251 1.71481,1.71481 4.504103,1.718436 6.222251,2.89e-4 L 80,22.233402 v 9.769759 C 80,34.20588 81.790861,36 84,36 c 2.204644,0 4,-1.789446 4,-3.996839 V 11.996839 C 88,10.896005 87.552712,9.8972231 86.829463,9.173436 86.105113,8.4484102 85.10633,8 84.003161,8 H 63.996839 C 61.79412,8 60,9.790861 60,12 c 0,2.204644 1.789446,4 3.996839,4 z M 88,56 V 36.985151 78.029699 C 88,83.536144 84.032788,88 79.132936,88 H 16.867063 C 11.96992,88 8,83.527431 8,78.029699 V 17.970301 C 8,12.463856 11.967212,8 16.867063,8 H 59.566468 40 c 2.209139,0 4,1.790861 4,4 0,2.209139 -1.790861,4 -4,4 H 18.277794 C 17.005287,16 16,17.194737 16,18.668519 V 77.331481 C 16,78.778664 17.019803,80 18.277794,80 H 77.722206 C 78.994713,80 80,78.805263 80,77.331481 V 56 c 0,-2.209139 1.790861,-4 4,-4 2.209139,0 4,1.790861 4,4 z" />
                            </g>
                        </g>';
                $id = 'wpil-svg-outbound-5-icon-path';
                break;
            case 'outbound-6':
                $path = '<g id="wpil-svg-outbound-6-icon-path">
                            <g fill-rule="evenodd" transform="matrix(0.5959368,0,0,0.5959368,-2.3837472,-2.2212188)">
                                <path d="m 40,24.965598 c 0,-0.552285 0.447715,-1 1,-1 0.552285,0 1,0.447715 1,1 0,3.469059 -0.129275,6.918922 -0.387834,10.349539 -0.334407,4.436897 -3.860867,7.963515 -8.297748,8.298115 C 29.895466,43.871087 26.457309,44 23,44 19.540669,44 16.100512,43.870937 12.679584,43.6128 8.2429399,43.278024 4.7167289,39.751579 4.3822446,35.314912 4.127407,31.934695 4,28.519214 4,25.068519 4,21.588185 4.1296049,18.127086 4.3888246,14.685273 4.723001,10.248145 8.2495818,6.7212457 12.68668,6.3866649 16.10527,6.128885 19.543061,6 23,6 23.552285,6 24,6.4477152 24,7 24,7.5522847 23.552285,8 23,8 19.593137,8 16.205509,8.1270043 12.837064,8.381003 9.3859872,8.6412326 6.6430914,11.384376 6.3831764,14.835476 6.1277288,18.227205 6,21.638202 6,25.068519 6,28.469267 6.1255362,31.834597 6.3765849,35.164557 6.6367394,38.615298 9.3793476,41.358088 12.830071,41.61847 16.200821,41.87282 19.590779,42 23,42 c 3.407228,0 6.795216,-0.127032 10.164018,-0.381085 3.450907,-0.260245 6.19371,-3.00317 6.453804,-6.454089 C 39.872604,31.784331 40,28.384605 40,24.965598 Z M 40.834267,5.7513115 c -0.09391,-0.015809 -0.190403,-0.024039 -0.288813,-0.024039 H 30.543365 c -0.552285,0 -1,-0.4477152 -1,-1 0,-0.5522847 0.447715,-1 1,-1 h 10.002089 c 2.058516,0 3.727273,1.6687569 3.727273,3.7272728 v 9.9999997 c 0,0.552285 -0.447715,1 -1,1 -0.552285,0 -1,-0.447715 -1,-1 V 7.4545455 c 0,-0.098534 -0.0083,-0.1951415 -0.0241,-0.2891685 L 30.340158,19.070833 C 29.397229,20.013522 27.983195,18.59913 28.926123,17.65644 Z" fill-rule="nonzero" />
                            </g>
                        </g>';
                $id = 'wpil-svg-outbound-6-icon-path';
                break;
            case 'outbound-7':
                $path = '<g id="wpil-svg-outbound-7-icon-path" fill="none" clip-path="url(#clip0_31_188)">
                            <path d="M9.16724 14.8891L20.1672 3.88908" stroke-linecap="round"/>
                            <path d="M13.4497 3.53554L20.5208 3.53554L20.5208 10.6066" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M17.5 13.5L17.5 16.26C17.5 17.4179 17.5 17.9968 17.2675 18.4359C17.0799 18.7902 16.7902 19.0799 16.4359 19.2675C15.9968 19.5 15.4179 19.5 14.26 19.5L7.74 19.5C6.58213 19.5 6.0032 19.5 5.56414 19.2675C5.20983 19.0799 4.92007 18.7902 4.73247 18.4359C4.5 17.9968 4.5 17.4179 4.5 16.26L4.5 9.74C4.5 8.58213 4.5 8.0032 4.73247 7.56414C4.92007 7.20983 5.20982 6.92007 5.56414 6.73247C6.0032 6.5 6.58213 6.5 7.74 6.5L11 6.5" stroke-linecap="round"/>
                        </g>
                        <defs>
                            <clipPath id="clip0_31_188">
                                <rect fill="white" height="24" width="24"/>
                            </clipPath>
                        </defs>';
                $id = 'wpil-svg-outbound-7-icon-path';
                break;
            case 'outbound-8':
                $path = '<g id="wpil-svg-outbound-8-icon-path">
                            <path d="M 19.318245,10.90244 H 17.12283 V 8.429854 L 13.552391,12.000586 12.000586,10.44761 15.569855,6.87922 H 13.097562 V 4.6840981 h 5.488683 c 0.402439,0 0.732,0.3286829 0.732,0.7308291 z" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10" stroke-width="0.585367" />
                            <path d="M 21.512196,0.29268351 H 9.8054636 c -1.211999,0 -2.195122,0.98312199 -2.195122,2.19512209 V 14.195708 c 0,1.212 0.983123,2.195122 2.195122,2.195122 H 21.512196 c 1.212293,0 2.195122,-0.983122 2.195122,-2.195122 V 2.4878056 c 0,-1.2120001 -0.982829,-2.19512209 -2.195122,-2.19512209 z m 0,13.53687849 c 0,0.201073 -0.164488,0.366146 -0.364976,0.366146 H 10.171611 c -0.2016594,0 -0.3661474,-0.165073 -0.3661474,-0.366146 V 2.8539517 c 0,-0.2007804 0.164488,-0.3661461 0.3661474,-0.3661461 H 21.14722 c 0.200781,0 0.364976,0.1653657 0.364976,0.3661461 z" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10" stroke-width="0.585367" />
                            <path d="m 14.195708,16.39083 v 4.75639 c 0,0.200781 -0.164487,0.364976 -0.365853,0.364976 H 2.8539517 c -0.2007804,0 -0.3661461,-0.164488 -0.3661461,-0.364976 V 10.17161 c 0,-0.2007804 0.1653657,-0.3661464 0.3661461,-0.3661464 H 7.6100496 V 7.610342 h -5.122244 c -1.2120001,0 -2.19512209,0.9831219 -2.19512209,2.1951216 V 21.512196 c 0,1.212293 0.98312199,2.195122 2.19512209,2.195122 H 14.195708 c 1.212,0 2.195122,-0.982829 2.195122,-2.195122 V 16.39083 Z" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10" stroke-width="0.585367" />
                        </g>';
                $id = 'wpil-svg-outbound-8-icon-path';
                break;
            case 'previous-track':
                $path = '<g id="wpil-svg-previous-track-icon-path">
                            <path d="M51.617,7.497c-1.029-0.525-2.271-0.429-3.206,0.25L22.829,26.328c-0.796,0.578-1.269,1.505-1.269,2.489   c0,0.987,0.47,1.913,1.269,2.491L48.411,49.89c0.534,0.389,1.171,0.588,1.808,0.588c0.479,0,0.957-0.109,1.398-0.336   c1.03-0.525,1.682-1.584,1.682-2.742V10.24C53.299,9.083,52.647,8.021,51.617,7.497z"/>
                            <path d="M14.512,7.357H6.744c-0.947,0-1.716,0.769-1.716,1.716v39.918c0,0.949,0.769,1.717,1.716,1.717h7.768   c0.947,0,1.717-0.768,1.717-1.717V9.073C16.229,8.125,15.459,7.357,14.512,7.357z"/>
                        </g>';
                $id = 'wpil-svg-previous-track-icon-path';
                $viewbox = '0 0 56 56';
                break;
            case 'pause':
                $path = '<g id="wpil-svg-pause-icon-path">
                            <path d="M21.765,8.138h-7.768c-0.947,0-1.716,0.768-1.716,1.716v39.918c0,0.947,0.769,1.717,1.716,1.717h7.768   c0.947,0,1.717-0.77,1.717-1.717V9.854C23.481,8.906,22.712,8.138,21.765,8.138z"/>
                            <path d="M43.044,8.138h-7.767c-0.948,0-1.717,0.768-1.717,1.716v39.918c0,0.947,0.769,1.717,1.717,1.717h7.767   c0.948,0,1.717-0.77,1.717-1.717V9.854C44.761,8.906,43.992,8.138,43.044,8.138z"/>
                        </g>';
                $id = 'wpil-svg-pause-icon-path';
                $viewbox = '0 0 56 56';
                break;
            case 'play':
                $path = '<g id="wpil-svg-play-icon-path" transform="scale(-1, 1) translate(-56, 0)">
                            <path d="M42.102,7.123c-1.051-0.535-2.322-0.438-3.279,0.259L12.657,26.385c-0.815,0.592-1.298,1.54-1.298,2.547  c0,1.01,0.48,1.956,1.298,2.547l26.165,19.006c0.547,0.398,1.199,0.604,1.85,0.604c0.49,0,0.979-0.115,1.43-0.344  c1.055-0.539,1.721-1.623,1.721-2.807V9.929C43.822,8.745,43.156,7.661,42.102,7.123z"/>
                        </g>';
                $id = 'wpil-svg-play-icon-path';
                $viewbox = '0 0 56 56';
                break;
            case 'next-track':
                $path = '<g id="wpil-svg-next-track-icon-path">
                            <path d="M34.66,26.426L9.078,7.846C8.144,7.167,6.9,7.069,5.872,7.595C4.841,8.121,4.19,9.18,4.19,10.338v37.161   c0,1.156,0.65,2.217,1.682,2.742c0.441,0.227,0.92,0.336,1.398,0.336c0.637,0,1.273-0.201,1.808-0.588L34.66,31.405   c0.798-0.578,1.269-1.504,1.269-2.488C35.929,27.931,35.456,27.004,34.66,26.426z"/>
                            <path d="M50.744,7.456h-7.767c-0.948,0-1.717,0.769-1.717,1.716v39.919c0,0.947,0.769,1.715,1.717,1.715h7.767   c0.948,0,1.716-0.768,1.716-1.715V9.171C52.46,8.224,51.692,7.456,50.744,7.456z"/>
                        </g>';
                $id = 'wpil-svg-next-track-icon-path';
                $viewbox = '0 0 56 56';
                break;
        }

        if(!empty($path)){
            $custom_style = '';
            if(!empty($styles)){
                $custom_style = Wpil_Toolbox::validate_inline_styles($styles, true);
            }

            if(!empty($viewbox) && !empty($custom_style)){
                // width="auto" height="auto"
                $style = ' ' . $custom_style . ' viewBox="'.$viewbox.'"';
            }else{
                $style = 'width="24" height="24" ' . $custom_style . ' viewBox="0 0 24 24"';
            }

            $svg = '<svg ' . $style . ' version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:svg="http://www.w3.org/2000/svg">';
            if($return_reference){
                $svg .= '<use href="#' . $id . '"></use>';
            }else{
                $svg .= $path;
            }
            $svg .= '</svg>';
        }

        return $svg;
    }

    public static function fixCollation($table)
    {
        global $wpdb;
        $table_status = $wpdb->get_results("SHOW TABLE STATUS where name like '$table'");
        if (!empty($table_status) && (empty($table_status[0]->Collation) || $table_status[0]->Collation != 'utf8mb4_unicode_ci')) {
            $wpdb->query("alter table $table convert to character set utf8mb4 collate utf8mb4_unicode_ci");
        }
    }

    public static function verify_nonce($key)
    {
        $user = wp_get_current_user();
        if(!isset($_REQUEST['nonce']) || !wp_verify_nonce($_REQUEST['nonce'], $user->ID . $key)){
            wp_send_json(array(
                'error' => array(
                    'title' => __('Data Error', 'wpil'),
                    'text'  => __('There was an error in processing the data, please reload the page and try again.', 'wpil'),
                )
            ));
        }
    }

    /**
     * Removes a hooked function from the wp hook or filter.
     * We have to flip through the hooked functions because a lot of the methods use instantiated objects
     *
     * @param string $tag The hook/filter name that the function is hooked to
     * @param string $object The object who's method we're removing from the hook/filter
     * @param string $function The object method that we're removing from the hook/filter
     * @param int $priority The priority of the function that we're removing
     **/
    public static function remove_hooked_function($tag, $object, $function, $priority){
        global $wp_filter;
        $priority = intval($priority);

        // if the hook that we're looking for does exist and at the priority we're looking for
        if( isset($wp_filter[$tag]) &&
            isset($wp_filter[$tag]->callbacks) &&
            !empty($wp_filter[$tag]->callbacks) &&
            isset($wp_filter[$tag]->callbacks[$priority]) &&
            !empty($wp_filter[$tag]->callbacks[$priority]))
        {
            // look over all the callbacks in the priority we're looking in
            foreach($wp_filter[$tag]->callbacks[$priority] as $key => $data)
            {
                // if the current item is the callback we're looking for
                if(isset($data['function']) && (is_a($data['function'][0], $object) || $data['function'][0] === $object) && $data['function'][1] === $function){
                    // remove the callback
                    unset($wp_filter[$tag]->callbacks[$priority][$key]);
                }
            }
        }
    }

    /**
     * Removes all functions that are of a lower priority than the one we supply.
     * If we're working on a main stack process, and we need to loop back for something,
     * there shouldn't be a need to re-call all the other functions that have gone before
     *
     * @param string $tag The hook/filter name that the functions are hooked to
     * @param int $priority_limit The limit on how high of priority hooks we should remove
     **/
    public static function remove_lower_priority_hooked_functions($tag, $priority_limit = 0){
        global $wp_filter;
        $priority_limit = intval($priority_limit);

        // if the hook that we're looking for does exist and at the priority we're looking for
        if( isset($wp_filter[$tag]) &&
            isset($wp_filter[$tag]->callbacks) &&
            !empty($wp_filter[$tag]->callbacks))
        {
            foreach($wp_filter[$tag]->callbacks as $priority => $callbacks){
                if($priority < $priority_limit){
                    unset($wp_filter[$tag]->callbacks[$priority]);
                }
            }
        }
    }

    /**
     * Checks to see if one of the calling ancestors of the current function is what we're looking for
     **/
    public static function has_ancestor_function($function_name = '', $class_name = ''){
        if(empty($function_name)){
            return false;
        }

        $call_stack = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        if(!empty($call_stack)){
            foreach($call_stack as $call){
                if( isset($call['function']) && $call['function'] === $function_name &&
                    (empty($class_name) || isset($call['class']) && $call['class'] === $class_name)
                ){
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Updates the WP option cache independently of the update_options functionality.
     * I've found that for some users the cache won't update and that keeps some option based processing from working.
     * The code is mostly pulled from the update_option function
     *
     * @param string $option The name of the option that we're saving.
     * @param mixed $value The option value that we're saving.
     **/
    public static function update_option_cache($option = '', $value = ''){
        $option = trim( $option );
        if ( empty( $option ) ) {
            return false;
        }

        $serialized_value = maybe_serialize( $value );
        $alloptions = wp_load_alloptions( true );
        if ( isset( $alloptions[ $option ] ) ) {
            $alloptions[ $option ] = $serialized_value;
            wp_cache_set( 'alloptions', $alloptions, 'options' );
        } else {
            wp_cache_set( $option, $serialized_value, 'options' );
        }
    }

    /**
     * Makes sure that the transients are set and that the option cache is updated when data is saved.
     * There are some cases of the transients not sticking, even though they are supposed to be active.
     * I believe the issue is object caching catching the update information, and then not passing it back when we ask for it.
     * 
     * Uses the same arguments as the WP transient function
     **/
    public static function set_transient($transient, $value, $expiration = 0) {

        $expiration         = (int) $expiration;
        $transient_timeout  = '_transient_timeout_' . $transient;
        $transient_option   = '_transient_' . $transient;

        if(false === get_option($transient_option)){
            $autoload = 'yes';
            if($expiration){
                $autoload = 'no';
                add_option($transient_timeout, time() + $expiration, '', 'no');
            }
            $result = add_option($transient_option, $value, '', $autoload);
        }else{
            /*
            * If expiration is requested, but the transient has no timeout option,
            * delete, then re-create transient rather than update.
            */
            $update = true;

            if($expiration){
                if(false === get_option($transient_timeout)){
                    delete_option($transient_option);
                    add_option($transient_timeout, time() + $expiration, '', 'no');
                    $result = add_option($transient_option, $value, '', 'no');
                    $update = false;
                }else{
                    update_option($transient_timeout, time() + $expiration);
                    self::update_option_cache($transient_timeout, time() + $expiration);
                }
            }

            if($update){
                $result = update_option($transient_option, $value);
                self::update_option_cache($transient_option, $value);
            }
        }

        return $result;
    }

    /**
     * Deletes all Link Whisper related data on plugin deletion
     **/
    public static function delete_link_whisper_data(){
        global $wpdb;

        // if we're not really sure that the user wants to delete all data, exit
        if('1' !== get_option('wpil_delete_all_data', false)){
            return;
        }

        // create a list of all possible tables
        $tables = self::getDatabaseTableList();

        // go over the list of tables and delete all tables that exist
        foreach($tables as $table){
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
            if($table_exists === $table){
                $wpdb->query("DROP TABLE {$table}");
            }
        }

        // delete all of the settings from the options table
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE `option_name` LIKE 'wpil_%' OR `option_name` LIKE 'wpil_2_%'");

        // clear all of the transients
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE `option_name` LIKE '_transient_wpil_%' OR `option_name` LIKE '_transient_timeout_wpil_%'");

        // delete all of the link metafields
        Wpil_Report::clearMeta();
    }

    /**
     * Checks to see if we're over the time limit.
     * 
     * @param int $time_pad The amount of time in advance of the PHP time limit that is considered over the time limit
     * @param int $max_time The absolute time limit that we'll wait for the current process to complete
     * @return bool
     **/
    public static function overTimeLimit($time_pad = 0, $max_time = null, $return_time_limit = false){
        $limit = ini_get( 'max_execution_time' );

        // if there is no limit or the limit is larger than 90 seconds
        if(empty($limit) || $limit === '-1' || $limit > 90){
            // create a self imposed limit so the user know LW is still working on looped actions
            $limit = 90;
        }

        // filter the limit so users with special constraints can make adjustments
        $limit = apply_filters('wpil_filter_processing_time_limit', $limit);

        // if the exit time pad is less than the limit
        if($limit < $time_pad){
            // default to a 5 second pad
            $time_pad = 5;
        }

        // if we're supposed to return the time limit
        if($return_time_limit){
            // find the shortest processing time and return it
            return ($max_time !== null && $max_time < ($limit - $time_pad)) ? $max_time: ($limit - $time_pad);
        }

        // get the current time
        $current_time = microtime(true);

        // if we've been running for longer than the PHP time limit minus the time pad, OR
        // a max time has been set and we've passed it
        if( ($current_time - WPIL_STATUS_PROCESSING_START) > ($limit - $time_pad) || 
            $max_time !== null && ($current_time - WPIL_STATUS_PROCESSING_START) > $max_time)
        {
            // signal that we're over the time limit
            return true;
        }else{
            return false;
        }
    }

    /**
     * Creates the database tables so we're sure that they're all set.
     * I'll still use the old method of creation for a while as a fallback.
     * But this will make LW more plug-n-play
     **/
    public static function createDatabaseTables(){
        Wpil_ClickTracker::prepare_table();
        Wpil_Error::prepareTable(false);
        Wpil_Error::prepareIgnoreTable();
        Wpil_TargetKeyword::prepareTable();
        Wpil_AI::prepare_table();
        Wpil_Sitemap::prepare_table();
        Wpil_Report::prepare_link_tracking_table();
        Wpil_Telemetry::prepare_table();

        // search console table not included because it's explicitly activated by the user
        // linked site data table also not included because it's explicitly activated by the user
    }

    /**
     * Returns an array of all the tables created by Link Whisper.
     * @param bool $should_prefix Should the returned tables have the site's database prefix attached?
     * @return array
     **/
    public static function getDatabaseTableList($should_prefix = true){
        global $wpdb;

        if($should_prefix){
            $prefix = $wpdb->prefix;
        }else{
            $prefix = '';
        }

        return array(
            "{$prefix}wpil_report_links",
            "{$prefix}wpil_tracked_link_ids",
            "{$prefix}wpil_search_console_data",
            "{$prefix}wpil_site_linking_data",
            "{$prefix}wpil_target_keyword_data",
            "{$prefix}wpil_urls",
            "{$prefix}wpil_url_links",
            "{$prefix}wpil_related_posts",
            "{$prefix}wpil_ai_post_data",
            "{$prefix}wpil_ai_product_data",
            "{$prefix}wpil_ai_keyword_data",
            "{$prefix}wpil_ai_token_use_data",
            "{$prefix}wpil_ai_embedding_data",
            "{$prefix}wpil_ai_embedding_calculation_data",
            "{$prefix}wpil_ai_embedding_phrase_data",
            "{$prefix}wpil_ai_embedding_phrase_calculation_data",
            "{$prefix}wpil_ai_suggested_anchors",
            "{$prefix}wpil_ai_processed_sentences",
            "{$prefix}wpil_ai_batch_log",
            "{$prefix}wpil_ai_error_log",
            "{$prefix}wpil_ai_system_error_log",
            "{$prefix}wpil_ai_completed_batch_log",
        );
    }

    /**
     * Helper function to set WP to not use external object caches when doing AJAX
     **/
    public static function ignore_external_object_cache($ignore_ajax = false){
        if( (defined('DOING_AJAX') && DOING_AJAX || $ignore_ajax) &&
            function_exists('wp_using_ext_object_cache') &&
            file_exists( WP_CONTENT_DIR . '/object-cache.php') &&
            wp_using_ext_object_cache())
        {
            if(!defined('WP_REDIS_DISABLED') && defined('WP_REDIS_FILE')){
                define('WP_REDIS_DISABLED', true);
            }
            wp_using_ext_object_cache(false);
        }

        if(defined('DOING_AJAX') && DOING_AJAX || $ignore_ajax){
            if(function_exists('nocache_headers')){
                nocache_headers();
            }
            if(!defined('DONOTCACHEOBJECT')){
                define('DONOTCACHEOBJECT', true);
            }
            if(!defined('DONOTCACHEDB')){
                define('DONOTCACHEDB', true);
            }
        }
    }

    /**
     *  Helper function to remove any problem hooks interfering with our AJAX requests
     * 
     * @param bool $ignore_ajax True allows the removing of hooks when ajax is not running
     **/
    public static function remove_problem_hooks($ignore_ajax = false){
        $admin_ajax = is_admin() && defined('DOING_AJAX') && DOING_AJAX;

        if( ($admin_ajax || $ignore_ajax) && defined('TOC_VERSION')){
            remove_all_actions('wp_enqueue_scripts');
        }
    }

    /**
     * Tracks actions that have taken place so we can tell if something in a distantly connected part of Link Whisper happened
     * 
     * @param string $action The name we've given to the action that's happened
     * @param mixed $value The value of the action that we're watching
     * @param bool $overwrite_true Should we overwrite TRUE results with whatever we currently have? By default, we don't so we can track if a result happened somewhere
     **/
    public static function track_action($action = '', $value = null, $overwrite_true = false){
        if(empty($action) || !is_string($action)){
            return;
        }

        // if the action has happened AND we should overwrite that status with the most recent one
        if(isset(self::$action_tracker[$action]) && !empty(self::$action_tracker[$action]) && $overwrite_true){
            self::$action_tracker[$action] = $value;
        }elseif(!array_key_exists($action, self::$action_tracker)){ // if the event has not happened yet
            self::$action_tracker[$action] = $value;
        }elseif(array_key_exists($action, self::$action_tracker) && empty(self::$action_tracker[$action]) && !empty($value)){ // if the event has been attempted and hasn't succeeded yet, but we now have a record of it happening!
            self::$action_tracker[$action] = $value;
        }
    }

    public static function action_happened($action = '', $return_result = true){
        if(empty($action) || !is_string($action)){
            return false;
        }

        $logged = array_key_exists($action, self::$action_tracker);

        if(!$logged){
            return false;
        }

        return ($return_result) ? self::$action_tracker[$action]: $logged;
    }

    public static function clear_tracked_action($action = ''){
        if(empty($action) || !is_string($action)){
            return;
        }

        if(array_key_exists($action, self::$action_tracker)){
            unset(self::$action_tracker[$action]);
        }
    }

    public static function ajax_hide_explain_page(){
        Wpil_Base::verify_nonce('wpil-floating-help-menu-nonce');

        $user_id = get_current_user_id();
        if(isset($_POST['hide_on_all_pages']) && !empty((int)$_POST['hide_on_all_pages'])){
            update_user_meta($user_id, 'wpil_hide_explain_page_completely', '1');
        }else{
            if(isset($_POST['current_page']) && !empty($_POST['current_page'])){
                $available_pages = self::get_available_pages();
                if(in_array($_POST['current_page'], $available_pages)){
                    $hidden_pages = get_user_meta($user_id, 'wpil_hide_explain_page_on', true);

                    if(empty($hidden_pages)){
                        $hidden_pages = array();
                    }
                    $hidden_pages[] = trim($_POST['current_page']);
                    update_user_meta($user_id, 'wpil_hide_explain_page_on', $hidden_pages);
                }
            }
        }
    }

    public static function ajax_save_wizard_settings(){
        Wpil_Base::verify_nonce('wpil_wizard_save_nonce');

        $response = array('status' => 'invalid', 'message' => 'please select all of the required options to proceed');
        if(isset($_POST['settings']) && !empty($_POST['settings'])){
            // verify the settings
            $settings = array(
                'wpil_setup_wizard_existing_user', 
                'wpil_setup_wizard_configure_settings',
                'wpil_setup_wizard_run_linking'
            );

            $acceptable_values = array(
                'yes',
                'no'
            );

            $save_settings = array();
            foreach($_POST['settings'] as $key => $value){
                if(in_array($key, $settings, true) && in_array($value, $acceptable_values, true)){
                    $save_settings[$key] = $value;
                }
            }
            
            if(!empty($save_settings)){
                update_option('wpil_wizard_settings_selected', $save_settings);
                // if we're not just saving the settings
                if(!isset($_POST['temp_save']) || empty($_POST['temp_save'])){
                    self::configure_wizard_settings();
                }
            }

            $response = array('status' => 'valid', 'message' => 'Settings Saved!');
        }
        wp_send_json($response);
    }

    public static function configure_wizard_settings(){
        delete_option('wpil_wizard_import_autolink_rules');
        $settings = get_option('wpil_wizard_settings_selected');
        $preconfigured = array(
            'wpil_add_destination_title' => '1',
            'wpil_ignore_tags_from_linking' => array('code'),
            'wpil_ignore_latest_posts' => '1',
            'wpil_update_reusable_block_links' => '1',
            'wpil_override_global_post_during_scan' => '1',
            'wpil_use_link_data_table' => '1',
            'wpil_make_suggestion_filtering_persistent' => '1',
            'wpil_disable_click_tracking_info_gathering' => '1',
            'wpil_override_global_post_during_scan' => '1',
            'wpil_use_link_data_table' => '1',
            'wpil_ignore_latest_posts' => '1',
            'wpil_remove_noindex_post_suggestions' => '1',
            'wpil_ignore_image_urls' => '1',
            'wpil_suggestion_anchor_max_size' => '7',
            'wpil_suggestion_anchor_min_size' => '3'
        );

        if(
            isset($settings['wpil_setup_wizard_configure_settings']) && $settings['wpil_setup_wizard_configure_settings'] === 'yes' ||
            isset($settings['wpil_setup_wizard_run_linking']) && $settings['wpil_setup_wizard_run_linking'] === 'yes' // if they're having us do the linking, assume they want us to do the settings too for the time being
        ){
            foreach($preconfigured as $key => $value){
                update_option($key, $value);
            }

            $ignore_pages = Wpil_Settings::get_service_pages_to_ignore();

            
            if(!empty($ignore_pages)){
                $links = get_option('wpil_ignore_pages_completely', '');
                $links_array = explode("\n", $links);
                foreach($ignore_pages as $id){
                    $post = new Wpil_Model_Post($id);
                    $post_link = $post->getViewLink();
                    if(!empty($post_link) && !in_array($post_link, $links_array)){
                        $links .= "\n" . $post_link;
                    }
                }

                // clear any ignore link cache that exists
                delete_transient('wpil_ignore_pages_completely');
                // save the ignore link
                update_option('wpil_ignore_pages_completely', $links);
            }

            update_option('wpil_wizard_import_autolink_rules', 1); // set the flag to say that we'll be importing autolinhk rules
            delete_option('wpil_wizard_settings_selected'); // clear the setting data so we done redo the settings without the user selecting it
        }
    }

    public static function ajax_wizard_save_oai_key(){
        Wpil_Base::verify_nonce('wpil_wizard_save_nonce');

        if(isset($_POST['key']) && !empty($_POST['key'])){
            // update the api key
            update_option('wpil_open_ai_api_key', Wpil_Toolbox::encrypt(trim(sanitize_text_field($_POST['key']))));
            // and check if this is a free key
            Wpil_AI::is_free_oai_subscription(true);
        }

        wp_send_json(array('status' => 'valid'));
    }

    public static function ajax_clear_process_tracker(){
        delete_transient('wpil_loading_progress_tracker');
        delete_transient('wpil_wizard_has_completed');
        delete_transient('wpil_wizard_inserting_autolinks');
        update_option('wpil_wizard_start_time', time()); // note the start time
        wp_send_json(array('status' => 'tracker_cleared'));
    }

    /**
     * Set a flahg so we know that the user has run the wizard
     **/
    public static function ajax_has_run_wizard(){
        Wpil_Settings::set_run_wizard();
    }
    
    /**
     * Set a flahg so we know that the user has run the wizard
     **/
    public static function ajax_set_processing_complete_flag(){
        // set the completion flag
        set_transient('wpil_wizard_has_completed', 1, HOUR_IN_SECONDS);

        // if the notification emails are enabled
        if(Wpil_Settings::email_notifications_are_enabled()){
            // send the completion email
            Wpil_Email::send_email_notification('wizard-complete');
        }
    }

    /**
     * Checks to see how many links have been inserted since the wizard began
     **/
    public static function ajax_get_wizard_insert_count(){
        self::verify_nonce('wpil_dashboard_loading_nonce');

        global $wpdb;
        $table = $wpdb->prefix . 'wpil_tracked_link_ids';
        $start_time = get_option('wpil_wizard_start_time', time());
        $link_count = $wpdb->get_col("SELECT COUNT(*) FROM {$table} WHERE `creation_time` > {$start_time}");
        wp_send_json(array('data' => array('link_inserts' => $link_count, 'finished' => (!empty(get_transient('wpil_wizard_has_completed')) || empty(get_transient('wpil_doing_ajax_autolinks'))))));
    }

    /** 
     * 
     **/
    public static function ajax_dismiss_ai_popup_banner(){
        update_user_meta(get_current_user_id(), 'wpil_dismissed_ai_notice_banner', '1');
    }

    public static function ajax_update_expanded_details_toggle(){
        $status = (!isset($_POST['status']) || empty($_POST['status'])) ? 0: 1;
        update_option('wpil_show_expanded_suggestion_details', $status);
        wp_send_json('updated details!');
    }
}
