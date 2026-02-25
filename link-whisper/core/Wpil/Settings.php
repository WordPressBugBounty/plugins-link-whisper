<?php

/**
 * Work with settings
 */
class Wpil_Settings
{
    public static $ignore_phrases = null;
    public static $stemmed_ignore_phrases = null;
    public static $ignore_words = null;
    public static $stemmed_ignore_words = null;
    public static $wpml_enabled = null;
    public static $keys = [
        'wpil_2_ignore_numbers',
        'wpil_2_post_types',
        'wpil_suggestion_limited_post_types',
        'wpil_2_term_types',
        'wpil_2_post_statuses',
        'wpil_limit_suggestions_to_post_types',
        'wpil_option_update_reporting_data_on_save',
        'wpil_skip_section_type',
        'wpil_skip_sentences',
        'wpil_selected_language',
        'wpil_ignore_links',
        'wpil_ignore_categories',
        'wpil_show_all_links',
        'wpil_make_suggestion_filtering_persistent',
        'wpil_max_suggestion_post_count',
        'wpil_force_keyword_exact_matches_word_limit',
        'wpil_suggestion_anchor_max_size',
        'wpil_suggestion_anchor_min_size',
        'wpil_full_html_suggestions',
        'wpil_ignore_keywords_posts',
        'wpil_ignore_keywords_posts_by_category',
        'wpil_ignore_orphaned_posts',
        'wpil_ignore_orphaned_posts_by_category',
        'wpil_nofollow_ignore_domains',
        'wpil_new_tab_ignore_domains',
        'wpil_same_tab_ignore_domains',
        'wpil_chat_gpt_api',
        'wpil_ai_batch_processing_limits',
        'wpil_open_ai_api_monthly_limit',
        'wpil_enable_ai_batch_processing',
        'wpil_selected_ai_batch_processes',
        'wpil_ai_generated_keyword_max_count',
        'wpil_use_ai_suggestions',
        'wpil_disable_ai_anchor_building',
        'wpil_restrict_to_top_ai_suggestions',
        'wpil_disable_ai_suggestions_cron',
        'wpil_ai_max_processing_age',
        'wpil_suggestion_relatedness_threshold',
        'wpil_sitemap_embedding_relatedness_threshold',
        'wpil_new_tab_domains',
        'wpil_same_tab_domains',
        'wpil_links_to_ignore',
        'wpil_broken_links_to_ignore',
        'wpil_related_post_links_to_ignore',
        'wpil_ignore_elements_by_class',
        'wpil_ignore_shortcodes_by_name',
        'wpil_ignore_linking_roles',
        'wpil_ignore_tags_from_linking',
        'wpil_ignore_elementor_from_linking',
        'wpil_ignore_pages_completely',
        'wpil_marked_as_external',
        'wpil_disable_acf',
        'wpil_count_related_post_links',
        'wpil_domains_marked_as_internal',
        'wpil_custom_fields_to_process',
        'wpil_acf_post_reference_fields',
        'wpil_add_icon_to_external_link',
        'wpil_external_link_icon',
        'wpil_external_link_icon_title',
        'wpil_external_link_icon_size',
        'wpil_external_link_icon_color',
        'wpil_external_link_icon_html_exclude',
        'wpil_external_link_icon_inner_html_exclude',
        'wpil_external_link_icon_post_ignore',
        'wpil_internal_link_icon_post_ignore',
        'wpil_add_icon_to_internal_link',
        'wpil_internal_link_icon',
        'wpil_internal_link_icon_title',
        'wpil_internal_link_icon_size',
        'wpil_internal_link_icon_color',
        'wpil_internal_link_icon_html_exclude',
        'wpil_internal_link_icon_inner_html_exclude',
        'wpil_process_these_acf_fields',
        'wpil_link_to_yoast_cornerstone',
        'wpil_suggest_to_outbound_posts',
        'wpil_sponsored_domains',
        'wpil_nofollow_domains',
        'wpil_dofollow_domains',
        'wpil_only_match_target_keywords',
        'wpil_add_noreferrer',
        'wpil_add_nofollow',
        'wpil_filter_staging_url',
        'wpil_live_site_url',
        'wpil_staging_site_url',
        'wpil_delete_all_data',
        'wpil_email_notifications_enabled',
        'wpil_remote_dashboard',
        'wpil_enable_telemetry',
        'wpil_testing_mode',
        'wpil_external_links_open_new_tab',
        'wpil_insert_links_as_relative',
        'wpil_prevent_two_way_linking',
        'wpil_disable_autolinking_on_post_update',
        'wpil_enable_autolink_cron_task',
        'wpil_disable_autolink_insert_run',
        'wpil_ignore_image_urls',
        'wpil_include_image_src',
        'wpil_use_ugly_permalinks',
        'wpil_delete_link_inner_html',
        'wpil_delete_links_to_post_on_delete',
        'wpil_include_post_meta_in_support_export',
        'wpil_ignore_acf_fields',
        'wpil_ignore_small_acf_text_fields',
        'wpil_ignore_click_links',
        'wpil_open_all_internal_new_tab',
        'wpil_open_all_external_new_tab',
        'wpil_open_all_internal_same_tab',
        'wpil_open_all_external_same_tab',
        'wpil_js_open_new_tabs',
        'wpil_add_destination_title',
        'wpil_disable_tawkto_widget',
        'wpil_disable_broken_link_cron_check',
        'wpil_disable_click_tracking',
        'wpil_delete_old_click_data',
        'wpil_max_links_per_post',
        'wpil_max_inbound_links_per_post',
        'wpil_max_linking_age',
        'wpil_max_suggestion_count',
        'wpil_disable_click_tracking_info_gathering',
        'wpil_autotag_gsc_keywords',
        'wpil_autotag_gsc_keyword_count',
        'wpil_autotag_gsc_keyword_basis',
        'wpil_show_comment_links',
        'wpil_ignore_latest_posts',
        'wpil_update_reusable_block_links',
        'wpil_content_formatting_level',
        'wpil_delete_all_data',
        'wpil_include_post_meta_in_support_export',
        'wpil_max_suggestion_count',
        'wpil_skip_section_type',
        'wpil_override_global_post_during_scan',
        'wpil_use_ugly_permalinks',
        'wpil_ignore_shortcodes_by_name',
        'wpil_update_reusable_block_links',
        'wpil_use_link_data_table',
    ];

    /**
     * Show settings page
     */
    public static function init()
    {
        //exit if user role lower than editor
        $capability = apply_filters('wpil_filter_main_permission_check', 'manage_categories');
        if (!current_user_can($capability)) {
            exit;
        }

        $types_active = Wpil_Settings::getPostTypes();
        $suggestion_types_active = self::getSuggestionPostTypes();
        $term_types_active = Wpil_Settings::getTermTypes();
        $types_available = get_post_types(['public' => true]);
        $term_types_available = array_intersect(array('category', 'post_tag', 'product_cat', 'product_tag'), get_taxonomies());
        $statuses_available = [
            'publish',
            'private',
            'future',
            'pending',
            'draft'
        ];
        $statuses_active = Wpil_Settings::getPostStatuses();
        Wpil_Base::show_tawkto_widget();
        include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/wpil_settings_v2.php';
    }

    public static function ai_init(){
        if (!empty($_GET['ai_auth_complete'])){
            // refresh the subscription cache
            Wpil_AI::get_user_ai_subscription(true);
            ?>
            <script>
            // This page is now in the popup window
            window.addEventListener("load", () => {
                // Optionally notify parent window
                if (window.opener) {
                    try {
                        window.opener.postMessage({ type: "AI_AUTH_COMPLETE" }, "*");
                    } catch (e) {
                        console.warn("Could not notify parent:", e);
                    }
                }

                // Close after a short delay to ensure REST hit has time to process
                setTimeout(() => {
                window.close();
                }, 1000);
            });
            </script>
            <?php
            return;
        }
        self::create_ai_credit_popup();
?>
        <div class="wrap wpil-report-page wpil_styles">
            <h1 class="wp-heading-inline"><?php esc_html_e('AI Subscription','wpil'); ?></h1>
            <hr class="wp-header-end">
        </div>
<?php
        $ai_id = self::get_linkwhisper_ai_user_id();
        $uemail = self::get_linkwhisper_ai_user_email();
        if(false && (empty($ai_id) || empty($uemail))){
            ?>
                <style>
                    .wpil-ai-not-connected-container{
                        background: #fff;
                        border: 2px solid #ccc;
                        border-radius: 8px;
                        padding: 1.5rem;
                        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.05);
                        display: flex;
                        flex-direction: column;
                        justify-content: space-between;
                        position: relative;
                        max-width: 550px;
                        max-height: 500px;
                        margin: 100px auto;
                        text-align: center;
                    }
                    .wpil-ai-not-connected-title{
                        font-size: 1.35rem;
                        font-weight: 600;
                        margin-bottom: 25px;
                    }
                    .wpil-ai-not-connected-content{
                        font-size: 1rem;
                    }
                </style>
            <div class="wpil-ai-not-connected-container">
                <div class="wpil-ai-not-connected">
                    <h3 class="wpil-ai-not-connected-title">Have a LinkWhisper.com Account?</h3>
                    <div class="wpil-ai-not-connected-content wpil_styles">
                        <a id="wpil-connect-ai-button" href="<?php echo esc_url(Wpil_AI::get_linkwhisper_ai_auth_url(admin_url('admin.php?page=link_whisper_ai_subscription&ai_auth_complete=1')))?>" style="margin-top:15px; user-select: none; text-align: center; min-width: 120px;" class="button-primary"><?php esc_html_e('Yes I Do', 'wpil'); ?></a>
                    </div>
                    <div class="wpil-ai-not-connected-content wpil_styles">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=link_whisper_ai_subscription&no_account=1'))?>" style="margin-top:15px; user-select: none; text-align: center; min-width: 120px;" class="button-primary"><?php esc_html_e('No I Don\'t', 'wpil'); ?></a>
                    </div>
                </div>
            </div>
            <script>
                document.addEventListener("DOMContentLoaded", () => {
                    const connectBtn = document.getElementById("wpil-connect-ai-button");
                    if (!connectBtn) return;

                    connectBtn.addEventListener("click", (e) => {
                        e.preventDefault();

                        const authUrl = connectBtn.href;

                        const width = 600;
                        const height = 700;
                        const left = (window.screen.width / 2) - (width / 2);
                        const top = (window.screen.height / 2) - (height / 2);

                        const popup = window.open(authUrl, 'LinkWhisperAIConnect', `width=${width},height=${height},top=${top},left=${left}`);

                        if (!popup) {
                        alert("Popup blocked! Please allow popups for this site to connect AI.");
                        return;
                        }

                        // Check every second if the popup has closed
                        const interval = setInterval(() => {
                        if (popup.closed) {
                            clearInterval(interval);
                            // Call a function to check if auth was completed (or just reload)
                            window.location.reload(true); // or make an AJAX call to confirm before reload
                        }
                        }, 1000);
                    });
                });
            </script>
            <?php
            return;
        }

        $sub = Wpil_AI::get_user_ai_subscription();
        $credits = Wpil_AI::get_available_ai_credits(isset($_GET['refresh_credits']));
        $recommended = (empty($sub)) ? count(Wpil_AI::get_processable_post_ids()): false;

        $renew = '';
        if(!empty($sub)){
            $timestamp = (!empty($sub->expiration) && !empty(strtotime($sub->expiration))) ? strtotime($sub->expiration): time();
            $date_format = get_option('date_format', '');
            if(!empty($date_format)){
                $renew = date($date_format, $timestamp);
            }else{
                $day = date('j', $timestamp); // Day without leading zero
                $suffix = date('S', $timestamp); // Ordinal suffix
                $renew = date('M', $timestamp) . " {$day}{$suffix}, " . date('Y', $timestamp);
            }
        }
        ?>
    <style>
        .credit-popup-actions{
            display:none;
        }
        .wpil-credit-purchase-table-wrapper *{
            font-family: 'Funnel Sans'
        }
        .wpil-credit-puchase-table{
            max-width: 1500px;
            max-width: 90%;
            margin: 0 auto;
        }
        .pricing-table {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 24px;
            margin: 2rem 0;
        }

        .plan-card {
            background: #fff;
            border: 2px solid #ccc;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            
            max-height: 530px;
        }

        .plan-card.plan-info-card{
            justify-content: initial;
        }

        .plan-card.plan-info-card li{
            font-size: 15px;
        }

        .plan-card.active {
            border-color: #7147b1;
        }

        .plan-card.featured {
            border-color: #007bff;
        }

        .plan-card .tag {
            background: #007bff;
            color: white;
            font-size: 0.75rem;
            padding: 4px 10px;
            border-radius: 12px;
            position: absolute;
            top: -12px;
            left: 50%;
            transform: translateX(-50%);
        }

        .plan-card .tag.active{
            background: #7147b1;
        }

        .plan-card ul{
            margin: 10px 0 0 0;
        }

        .plan-card ul li .main-text{
            font-size: 15px;
            color: #111111;
        }

        .plan-name{
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .plan-benefits{
            color: #000000;
            font-size: 1.1rem;
            line-height: 1.25rem;
            margin: 20px 0 0 0 !important;
        }

        .plan-benefits li{
            margin: 0 0 20px;
            font-weight: 300;
            font-size: 16px;
        }

        .plan-description {
            font-size: 0.95rem;
            color: #555;
            margin-bottom: 1.5rem;
        }

        .plan-price {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
            color: #111;
        }

        .plan-price span {
            font-size: 0.9rem;
            color: #777;
        }

        .plan-note {
            font-size: 15px;
            color: #666;
            margin-bottom: 0.75rem;
        }

        .plan-credits:not(.on-demand-plan-pricing) {
            margin-bottom: 1rem;
            font-size: 0.95rem;
            position: relative;
        }

        .wpil-plan-spacer{
            color: #fff;
            opacity: 0;
        }

        .plan-button {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 6px;
            font-size: 0.95rem;
            cursor: pointer;
            margin-bottom: 0.5rem;
        }

        .plan-button:hover {
        background: #0056b3;
        }

        .plan-button.current {
            background: #7147b1;
            color: #ffffff;
            font-weight: bold;
            cursor: default;
        }

        .current-credits,
        .plan-renew{
            color: #000000;
            font-size: 16px;
        }

        .slider-container {
            margin-top: 1rem;
            text-align: center;
        }

        .slider-container label {
            display: block;
            margin-bottom: 0.25rem;
            font-weight: bold;
            font-size: 0.9rem;
            color: #444;
        }

        .slider-container input[type="range"] {
            width: 100%;
            margin: 0.5rem 0;
        }

        .slider-output {
            font-size: 0.9rem;
            color: #333;
        }

        .custom-plan-cta,
        .ai-info-callout {
            grid-column: 1 / -1;
            border: 2px solid #ccc;
            background: #fff;
            border-radius: 8px;
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.05);
            flex-wrap: wrap;
            gap: 16px;
        }

        .custom-plan-text,
        .ai-info-callout-text {
            font-size: 1.15rem;
            color: #333;
            flex: 1;
            min-width: 220px;
        }

        .ai-info-callout-text ul{
            list-style: disc;
        }

        .ai-info-callout-text ul li{
            margin-left: 20px;
        }

        .custom-plan-button {
            background: #007bff;
            color: white !important;
            border: none;
            padding: 10px 16px;
            border-radius: 6px;
            font-size: 0.95rem;
            text-decoration: none;
            font-weight: 500;
            transition: background 0.2s ease;
        }

        .custom-plan-button:hover {
            background: #0056b3;
        }
        .plan-credits .wpil-help-text{
            background-color: #000;
        }

        .wpil-old-credit-amount {
            text-decoration: line-through;
            color: #888;
            margin-right: 6px;
            font-weight: normal;
        }

        .wpil-new-credit-amount {
            color: #28a745;
            font-weight: bold;
        }


        #wpil-payment-form-wrapper{
            display: none;
        }

        #wpil-payment-form{
            border: 2px solid #ccc;
            background: #fff;
            border-radius: 8px;
            padding: 1.5rem;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.05);
            max-width: 500px;
            min-width: 500px;
            margin: 0 0 0 0;
        }

        #wpil-payment-email-container{
            margin: 0 0 15px 0;
        }

        #wpil-payment-contents-container{
            display: none;
        }

        #wpil-payment-checkout-title{
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.25rem;
        }

        #wpil-payment-email-label{
            font-size: 1rem;
            font-weight: 600;
            display: block;
            margin: 0 0 10px 0;
        }

        #wpil-payment-email{
            border: 2px solid #ccc;
            background: #fff;
            border-radius: 8px;
            width: 100%;
        }

        #wpil-payment-form-terms-container{
            margin: 15px 0 0 0;
        }

        #wpil-payment-form-terms-container a{
            margin: 0 10px 0 0;
        }

        button#wpil-checkout-button {
            display: block;
            width: 100%;
            padding: 12px 16px;
            font-size: 16px;
            font-family: inherit;
            border: 1px solid #ccc;
            border-radius: 6px;
            background-color: #fff;
            color: #000;
            cursor: pointer;
            transition: box-shadow 0.2s, border-color 0.2s;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-top: 16px;
        }

        button#wpil-checkout-button:hover {
            border-color: #888;
            box-shadow: 0 1px 5px rgba(0,0,0,0.2);
        }

        button#wpil-checkout-button:active {
            border-color: #666;
            box-shadow: 0 0 0 2px rgba(0,0,0,0.15);
        }

        button#wpil-checkout-button:disabled {
            background-color: #f2f2f2;
            color: #aaa;
            border-color: #ddd;
            cursor: not-allowed;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
    <div class="wpil-credit-purchase-table-wrapper">
        <div class="wpil-credit-puchase-table">
            <br><br>
            <!-- Credit Balance Section -->
            <div class="lw-credit-balance-section">
                <div class="lw-credit-balance-card-compact">
                    <div class="lw-credit-header-row">
                        <div class="lw-credit-title-with-balance">
                            <span class="lw-credit-label-inline">Available AI Credits:</span>
                            <span class="lw-credit-number-inline"><?php echo number_format((!empty($credits) ? (int) $credits: 0)); ?></span>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=link_whisper_ai_subscription&refresh_credits=1')); ?>" class="lw-refresh-icon" title="Refresh Balance">
                                <span class="dashicons dashicons-update"></span>
                            </a>
                        </div>
                        <div class="lw-credit-actions-group">
                            <?php if (!empty($sub)): ?>
                            <a href="https://linkwhisper.com/my-account/#tab-52427614" target="_blank" style="margin: 0!important;" class="lw-btn lw-btn-secondary">
                                <span class="dashicons dashicons-admin-generic"></span> Manage Subscription
                            </a>
                            <?php elseif(empty($credits)): ?>
                            <a href="<?php echo esc_url(Wpil_AI::get_linkwhisper_ai_auth_url())?>" class="lw-btn lw-btn-secondary lw-ai-sub-connect-check"><?php esc_html_e('Connect! (Requires Credits)', 'wpil'); ?></a>
                            <?php endif; ?>
                            <a href="https://linkwhisper.com/ai-pricing-free" target="_blank" style="margin: 0!important;" class="lw-btn lw-btn-primary">
                                <span class="dashicons dashicons-cart"></span> Buy Credits
                            </a>
                        </div>
                    </div>
                    <?php if (false && !empty($ai_credit_data['updated_at'])): ?>
                        <div class="lw-credit-timestamp">
                            Last updated: <?php echo date('M j, Y g:i a', $ai_credit_data['updated_at']); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <style>
                /**
                 * Link Whisper AI Credits Display Styles
                 */

                /* Credit Balance Section */
                .lw-credit-balance-section {
                    margin-bottom: 20px;
                }

                .lw-credit-balance-card-compact {
                    background: #f8f9fa;
                    color: #1e1e1e;
                    padding: 15px 20px;
                    border-radius: 8px;
                    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                    border: 1px solid #e1e4e7;
                }

                .lw-credit-header-row {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    margin-bottom: 6px;
                    flex-wrap: wrap;
                }

                .lw-credit-title-with-balance {
                    display: flex;
                    align-items: baseline;
                    gap: 10px;
                    flex: 1;
                    min-width: 0;
                }

                .lw-credit-actions-group {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    margin-left: auto;
                }

                .lw-credit-label-inline {
                    font-size: 15px;
                    font-weight: 500;
                    color: #666;
                }

                .lw-credit-number-inline {
                    font-size: 28px;
                    font-weight: 700;
                    line-height: 1;
                    color: #1e1e1e;
                }

                .lw-refresh-icon {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    width: 24px;
                    height: 24px;
                    margin-left: 4px;
                    color: #666;
                    text-decoration: none;
                    transition: all 0.2s ease;
                }

                .lw-refresh-icon:hover {
                    color: rgb(120, 102, 255);
                    transform: rotate(90deg);
                }

                .lw-refresh-icon:active {
                    transform: rotate(180deg);
                }

                .lw-refresh-icon .dashicons {
                    font-size: 18px;
                    width: 18px;
                    height: 18px;
                }

                .lw-credit-timestamp {
                    font-size: 11px;
                    color: #666;
                    margin: 0;
                    line-height: 1.3;
                }

                /* Buttons */
                .lw-btn {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    padding: 8px 16px;
                    border-radius: 6px;
                    font-size: 14px;
                    font-weight: 600;
                    text-decoration: none;
                    border: none;
                    cursor: pointer;
                    transition: all 0.2s ease;
                    white-space: nowrap;
                }

                .lw-btn .dashicons {
                    font-size: 16px;
                    width: 16px;
                    height: 16px;
                }

                .lw-btn-primary {
                    background: rgb(120, 102, 255);
                    color: #ffffff;
                    border: 1px solid rgb(120, 102, 255);
                }

                .lw-btn-primary:hover {
                    background: rgb(95, 77, 230);
                    color: #ffffff;
                    text-decoration: none;
                    border: 1px solid rgb(95, 77, 230);
                }

                .lw-btn-secondary {
                    background: #ffffff;
                    color: #333333;
                    border: 1px solid #ddd;
                }

                .lw-btn-secondary:hover {
                    background: #f6f7f7;
                    color: #333333;
                    text-decoration: none;
                    border: 1px solid #ccc;
                }

                /* Icon-only refresh button */
                .lw-btn-icon {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    width: 32px;
                    height: 32px;
                    padding: 0;
                    background: rgba(255, 255, 255, 0.2);
                    border: 1px solid rgba(255, 255, 255, 0.3);
                    border-radius: 6px;
                    color: #ffffff;
                    cursor: pointer;
                    transition: all 0.2s ease;
                    flex-shrink: 0;
                }

                .lw-btn-icon:hover {
                    background: rgba(255, 255, 255, 0.3);
                    transform: scale(1.05);
                }

                .lw-btn-icon:active {
                    transform: scale(0.95);
                }

                .lw-btn-icon .dashicons {
                    font-size: 18px;
                    width: 18px;
                    height: 18px;
                    margin: 0;
                }

                .lw-btn-icon:disabled {
                    opacity: 0.6;
                    cursor: not-allowed;
                }

                /* Notifications */
                .lw-notification {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    padding: 15px 20px;
                    border-radius: 6px;
                    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                    z-index: 10000;
                    font-size: 14px;
                    font-weight: 600;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }

                .lw-notification .dashicons {
                    font-size: 18px;
                    width: 18px;
                    height: 18px;
                }

                .lw-notification-success {
                    background: #d4edda;
                    color: #155724;
                    border: 1px solid #c3e6cb;
                }

                .lw-notification-error {
                    background: #f8d7da;
                    color: #721c24;
                    border: 1px solid #f5c6cb;
                }

                /* Responsive Design */
                @media (max-width: 768px) {
                    .lw-credit-header-row {
                        gap: 10px;
                    }

                    .lw-credit-title-with-balance {
                        flex: 1 1 100%;
                        flex-wrap: wrap;
                        gap: 6px;
                    }

                    .lw-credit-actions-group {
                        flex: 1 1 100%;
                        margin-left: 0;
                        justify-content: flex-start;
                    }

                    .lw-credit-label-inline {
                        font-size: 14px;
                    }

                    .lw-credit-number-inline {
                        font-size: 24px;
                    }

                    .lw-btn {
                        padding: 7px 12px;
                        font-size: 13px;
                    }

                    .lw-btn-icon {
                        width: 28px;
                        height: 28px;
                    }

                    .lw-btn-icon .dashicons {
                        font-size: 16px;
                        width: 16px;
                        height: 16px;
                    }
                }

                @media (max-width: 480px) {
                    .lw-credit-number-inline {
                        font-size: 22px;
                    }

                    .lw-credit-balance-card-compact {
                        padding: 12px 15px;
                    }

                    .lw-credit-label-inline {
                        font-size: 13px;
                    }

                    .lw-credit-timestamp {
                        font-size: 10px;
                    }

                    .lw-btn {
                        padding: 6px 10px;
                        font-size: 12px;
                    }

                    .lw-btn .dashicons {
                        font-size: 14px;
                        width: 14px;
                        height: 14px;
                    }
                }
            </style>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                var links = document.querySelectorAll('a.lw-ai-sub-connect-check');

                links.forEach(function (link) {
                    link.addEventListener('click', function (e) {
                    var confirmed = window.confirm('Have you bought AI credits? Link Whisper AI needs credits to function.');

                    if (!confirmed) {
                        e.preventDefault();
                        e.stopImmediatePropagation();
                    }
                    });
                });
                });
            </script>
            <div style="display: none" id="wpil-credit-pricing" class="pricing-table">
                <div class="plan-card">
                    <h3 class="plan-name">On Demand Credits</h3>
                    <p class="plan-description">Use for any AI process in Link Whisper.</p>
                    <div class="plan-price" style="margin-bottom: 10px;">
                        <div style="display:inline-block" class="plan-sub-price">$20</div>
                    </div>
                    <ul>
                        <li><div class="plan-credits main-text" style="display:inline-block"><div style="display:inline-block" class="plan-credits on-demand-plan-pricing">1000</div></div> <span>Credits</span></li>
                        <li><div class="plan-note main-text">$0.02 Per Credit</div></li>
                        <li><div class="plan-credits main-text">Process up to <div style="display:inline-block" class="on-demand-posts">1000</div> posts <div style="float: none;display: inline-block;margin: 0;" class="wpil_help"><i class="dashicons dashicons-editor-help" style="font-size: 18px;"></i><div class="wpil-help-text" style="display: none;">Credits per post is estimated based on articles averaging 1,500 words.</div></div></div></li>
                        <li><div class="plan-credits main-text">Credits expire after 30 days</div></li>
                    </ul>
                    <div class="slider-container">
                        <input type="range" id="credit-slider" min="1000" max="10000" step="1000" value="1000" />
                        <label for="credit-slider">Select credit amount:</label>
                    </div>
                    <div class="wpil-plan-spacer">.</div>
                    <button class="plan-button" data-type="custom" data-price-id="price_ondemand_free" data-download="ondemand_free">Buy Credits</button>
                </div>
                <!---->
                <?php $active = (!empty($sub) && isset($sub->product_id)) && (int)$sub->product_id === 5246590; ?>
                <?php $recc = $recommended > 0 && $recommended < 1050; ?>
                <div class="plan-card plan-1k <?php echo $active ? 'active': ''; ?> <?php echo ($recc) ? 'featured': '';?>">
                    <div class="tag active" style="<?php echo $active ? '': 'display:none'; ?>">Active</div>
                    <div class="tag plan-1k <?php echo ($recc) ? '': 'hidden';?>">Recommended</div>
                    <h3 class="plan-name">1k Credits Monthly</h3>
                    <p class="plan-description">Use for any AI process in Link Whisper.</p>
                    <div class="plan-price">$10 <span>/month</span></div>
                    <ul>
                        <li><div class="plan-credits main-text">1000 Credits per month</div></li>
                        <li><div class="plan-note main-text">$0.01 Per Credit</div></li>
                        <li><div class="main-text">Process up to 1000 posts <div style="float: none;display: inline-block;margin: 0;" class="wpil_help"><i class="dashicons dashicons-editor-help" style="font-size: 18px;"></i><div class="wpil-help-text" style="display: none;">Credits per post is estimated based on articles averaging 1,500 words.</div></div></div></li>
                        <li><div class="wpil-plan-spacer">.</div></li>
                        <?php if($active){ ?>
                        <li><div class="current-credits" style="<?php echo $active ? '': 'display:none'; ?>"><?php echo esc_html('Current Plan Credits: ' . (int) $credits);?></div></li>
                        <li><div class="plan-renew" style="<?php echo $active && !empty($renew) ? '': 'display:none'; ?>"><?php echo esc_html('Plan Renews: ' . $renew);?></div></li>
                        <?php } else { ?>
                        <li><div class="wpil-plan-spacer">.</div></li>
                        <li><div class="wpil-plan-spacer">.</div></li>
                        <?php } ?>
                        <li><div class="wpil-plan-spacer">.</div></li>
                        <li><div class="wpil-plan-spacer">.</div></li>
                    </ul>
                    <button class="plan-button <?php echo $active ? 'current': '';?>" data-type="recurring" data-price-id="price_1k_free" data-download="1k_free"><?php echo $active ? 'Cancel Plan': 'Choose Plan';?></button>
                </div>
                <!---->
                <?php $active = (!empty($sub) && isset($sub->product_id)) && (int)$sub->product_id === 5246591; ?>
                <?php $recc = $recommended > 1050 && $recommended < 2050; ?>
                <div class="plan-card plan-2k <?php echo $active ? 'active': ''; ?> <?php echo ($recc) ? 'featured': '';?>">
                    <div class="tag active" style="<?php echo $active ? '': 'display:none'; ?>">Active</div>
                    <div class="tag plan-2k <?php echo ($recc) ? '': 'hidden';?>">Recommended</div>
                    <h3 class="plan-name">2k Credits Monthly</h3>
                    <p class="plan-description">Use for any AI process in Link Whisper.</p>
                    <div class="plan-price">$20 <span>/month</span></div>
                    <ul>
                        <li><div class="plan-credits main-text"><span class="wpil-old-credit-amount">2000</span><span class="wpil-new-credit-amount">2200</span> AI credits per month</div></li>
                        <li><div class="plan-note main-text"><span class="wpil-new-credit-amount">$0.009</span> Per Credit</div></li>
                        <li><div class="main-text">Process up to 2200 posts <div style="float: none;display: inline-block;margin: 0;" class="wpil_help"><i class="dashicons dashicons-editor-help" style="font-size: 18px;"></i><div class="wpil-help-text" style="display: none;">Credits per post is estimated based on articles averaging 1,500 words.</div></div></div></li>
                        <li><div class="wpil-plan-spacer">.</div></li>
                        <?php if($active){ ?>
                        <li><div class="current-credits" style="<?php echo $active ? '': 'display:none'; ?>"><?php echo esc_html('Current Plan Credits: ' . (int) $credits);?></div></li>
                        <li><div class="plan-renew" style="<?php echo $active && !empty($renew) ? '': 'display:none'; ?>"><?php echo esc_html('Plan Renews: ' . $renew);?></div></li>
                        <?php } else { ?>
                        <li><div class="wpil-plan-spacer">.</div></li>
                        <li><div class="wpil-plan-spacer">.</div></li>
                        <?php } ?>
                        <li><div class="wpil-plan-spacer">.</div></li>
                        <li><div class="wpil-plan-spacer">.</div></li>
                    </ul>
                    <button class="plan-button <?php echo $active ? 'current': '';?>" data-type="recurring" data-price-id="price_2k_free" data-download="2k_free"><?php echo $active ? 'Cancel Plan': 'Choose Plan';?></button>
                </div>
                <!---->
                <div class="plan-card plan-info-card">
                    <h3 class="plan-name">Included in All Plans</h3>
                    <ul class="plan-benefits">
                        <li>🤖 Smart internal links created with AI</li>
                        <li>🔗 More relevant links to boost engagement</li>
                        <li>🧠 AI keyword targeting for higher conversions</li>
                        <li>🛒 Automatic product detection</li>
                        <li>🗺️ Visual sitemaps that bring your SEO to life</li>
                        <li>🔁 Plans renew automatically</li>
                    </ul>
                </div>
                <!---->
                <?php /*
                <?php $active = (!empty($sub)) && (int)$sub->product_id === 5244468; ?>
                <div class="plan-card plan-5k <?php echo $active ? 'active': ''; ?>">
                    <div class="tag active" style="<?php echo $active ? '': 'display:none'; ?>">Active</div>
                    <div class="tag plan-5k hidden">Recommended</div>
                    <h3 class="plan-name">Link Whisper 5k Monthly</h3>
                    <p class="plan-description">Keep your internal links optimized and your rankings growing—recurring credits mean nonstop AI-powered SEO support.</p>
                    <div class="plan-price">$40 <span>/month</span></div>
                    <div class="plan-note">Credits do not roll over</div>
                    <div class="plan-credits">5000 AI credits per month</div>
                    <div class="current-credits" style="<?php echo $active ? '': 'display:none'; ?>"><?php echo 'Current Plan Credits: ' . $credits;?></div>
                    <div class="plan-renew" style="<?php echo $active && !empty($renew) ? '': 'display:none'; ?>"><?php echo 'Plan Renews: ' . $renew;?></div>
                    <div class="plan-feature-note">Credits can be used for:</div>
                    <ul class="plan-features">
                        <li>AI Powered Suggestions</li>
                        <li>Content Analysis</li>
                        <li>Keyword Detection</li>
                        <li>Product Detection</li>
                    </ul>
                    <button class="plan-button <?php echo $active ? 'current': '';?>" data-type="recurring" data-price-id="price_5k_free" data-download="5k_free"><?php echo $active ? 'Cancel Plan': 'Choose Plan';?></button>
                </div>
                */ ?>
                <div id="wpil-payment-form-wrapper">
                    <div id="wpil-back-button-container" style="display:none;">
                        <button id="wpil-back-button" style="background:#fff;border:1px solid #ccc;border-radius:6px;padding:10px 16px;cursor:pointer;">
                            ← Back to Plan Selection
                        </button>
                    </div>
                    <form id="wpil-payment-form">
                        <h3 id="wpil-payment-checkout-title">Checkout</h3>
                        <div id="wpil-payment-email-container">
                            <label id="wpil-payment-email-label" for="wpil-payment-email">
                                <?php echo (!empty($uemail)) ? 'Loading checkout… please wait' : 'Enter Your Email'; ?>
                            </label>
                            <input type="email" id="wpil-payment-email" required placeholder="Email" <?php echo (!empty($uemail)) ? 'value="'.esc_attr($uemail).'" style="display:none;"': '';?>>
                            <a href="#" id="wpil-payment-email-submit" style="margin-top:15px; user-select: none; text-align: center; min-width: 120px;" class="button-primary"><?php esc_html_e('Submit', 'wpil'); ?></a>
                        </div>
                        <div id="wpil-payment-contents-container">
                            <div>
                                <div id="payment-element"></div>
                                <div id="card-errors" class="stripe-checkout-error"></div>
                            </div>
                            <button type="submit" id="wpil-checkout-button">Complete Purchase</button>
                            <div id="wpil-payment-form-terms-container">
                                <a href="<?php echo WPIL_STORE_URL . '/privacy-policy/';?>">Privacy Policy</a>
                                <a href="<?php echo WPIL_STORE_URL . '/terms-of-service/';?>">Terms of Service</a>
                            </div>
                        </div>
                    </form>
                    <div id="wpil-loader" style="display:none;text-align:center;padding:20px;">
                        <div class="spinner" style="margin:0 auto;width:40px;height:40px;border:4px solid #ccc;border-top-color:#7147b1;border-radius:50%;animation:spin 1s linear infinite;"></div>
                    </div>
                </div>

            </div>
            <?php if(empty($sub) && false){ ?>
            <div id="wpil-plan-estimator" class="custom-plan-cta">
                <div id="wpil-plan-estimation-intro" class="custom-plan-text">
                    <strong>Not sure what plan fits your site?</strong> Use our site estimator to find the perfect one for you!
                </div>
                <div id="wpil-plan-estimation" class="custom-plan-text plan-recommendation hidden">
                    <strong>Estimation Complete!</strong> 
                </div>
                <a href="#" id="wpil-ai-plan-estimator" class="custom-plan-button">Get Estimate</a>
            </div>
            <br><br>
            <?php } ?>
            <div style="display: none" id="wpil-custom-plan-cta" class="custom-plan-cta">
                <div class="custom-plan-text">
                    <strong>Need more AI credits or a custom plan?</strong> Let’s talk. We’ll help you craft the perfect solution. 🚀
                </div>
                <a class="custom-plan-button" href="https://account.linkwhisper.com/support" target="_blank">Contact Sales</a>
            </div>
            <br>
            <br>
            <div id="wpil-ai-service-description" class="ai-info-callout">
                <div class="ai-info-callout-text">
                    <strong>Wondering how AI can boost your site?</strong><br><br>
                    <div>
                        It can:
                        <ul>
                            <li>Enhance your suggestions to make them more focused and relevant than before.</li>
                            <li>Show you when you're linking between unrelated posts.</li>
                            <li>Generate relational sitemaps to highlight and identify content clusters.</li>
                            <li>Create post-specific Target Keywords for better suggestions.</li>
                            <li>Detect products mentioned in posts, and highlight them for linking.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script>
const STRIPE = {
    'publicKey': 'pk_live_kSOl38xUgfzKw67PzZDQDipr001O3VFl3p',
    'apiUrl': 'https://linkwhisper.com/wp-json/lwasc-checkout/v1',
}
const WPIL_USER_EMAIL = <?php echo $uemail ? json_encode($uemail) : 'null'; ?>;

document.addEventListener("DOMContentLoaded", () => {
  const stripe = Stripe(STRIPE.publicKey);
  let activeStripe = null;
  let activeElements = null;
  let activeType = null;
  let currentPlanButton = null;

  const emailInput = document.getElementById("wpil-payment-email");
  const emailSubmit = document.getElementById("wpil-payment-email-submit");
  const emailContainer = document.getElementById("wpil-payment-email-container");
  const formContainer = document.getElementById("wpil-payment-contents-container");
  const paymentFormWrapper = document.getElementById("wpil-payment-form-wrapper");

  const creditSlider = document.getElementById("credit-slider");
  const creditsEl = document.querySelector(".plan-credits");
  const creditsEl2 = document.querySelector(".on-demand-posts");
  const priceEl = document.querySelector(".plan-sub-price");

  creditSlider.addEventListener("input", () => {
    const credits = parseInt(creditSlider.value);
    const price = (credits / 1000) * 20;
    creditsEl.textContent = credits.toLocaleString();
    creditsEl2.textContent = credits.toLocaleString();
    priceEl.textContent = `$${price.toFixed(0)}`;
  });

  function showCheckoutForm(button) {
    document.querySelectorAll(".plan-card").forEach(card => {
        if (!card.contains(button)) {
        card.style.transition = "opacity 0.3s ease";
        card.style.opacity = "0";
        setTimeout(() => card.style.display = "none", 300);
        }else{
            card.style.maxWidth = '500px';
        }
    });

    setTimeout(() => {
        var pricing = document.getElementById('wpil-credit-pricing');
        pricing.style.display = "flex";
        pricing.style.flexDirection = "column";
        paymentFormWrapper.style.display = "block";
    }, 320);

    currentPlanButton = button;

    if (WPIL_USER_EMAIL) {
        emailInput.value = WPIL_USER_EMAIL;
        emailContainer.style.display = "block";
        formContainer.style.display = "none";
        triggerStripeIntent(WPIL_USER_EMAIL, button.dataset.type, button.dataset.priceId);
    } else {
        document.getElementById("wpil-loader").style.display = "none";
        emailContainer.style.display = "block";
        formContainer.style.display = "none";
    }
  }

document.querySelectorAll(".plan-button").forEach((button) => {
  button.addEventListener("click", async (e) => {
    const type = button.dataset.type;
    const plan = button.dataset.download; // '1k_free', '2k_free', etc.

    if (type === "recurring" && button.classList.contains("current")) {
      // Cancel subscription
      showSubscriptionConfirmModal("Canceling will deactivate your AI subscription on all sites. Proceed?", async () => {
        try {
          showSubscriptionSetupModal(null, 'Cancelling subscription.');
          const res = await fetch(STRIPE.apiUrl + "/cancel-subscription", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ ai_id: "<?php echo esc_attr($ai_id);?>", subscription_id: "<?php echo ((!empty($sub)) && isset($sub->subscription_id)) ? esc_attr($sub->subscription_id): '';?>" })
          });
          const data = await res.json();
          if (data.success) {
            clearUserSubscription();
          } else {
            if(data.message){
                clearUserSubscription();
            }else{
                alert("Cancellation failed: " + (data.message || "Unknown error"));
            }
          }
        } catch (err) {
          alert("Error contacting server: " + err.message);
        }
      });

      return;
    }

    const activePlan = document.querySelector(".plan-card.active")?.querySelector(".plan-button")?.dataset?.download;

    // If user chooses a lower-tier than current
    if (activePlan === "2k_free" && plan === "1k_free") {
      showSubscriptionConfirmModal("Downgrading affects all sites using this subscription. Proceed?", () => {
        currentPlanButton = button;
        showCheckoutForm(button);
      });
      return;
    }

    // Default action
    currentPlanButton = button;
    showCheckoutForm(button);
  });
});



  document.getElementById("wpil-back-button").addEventListener("click", () => {
  document.querySelectorAll(".plan-card").forEach(card => {
    card.style.display = "block";
    card.style.opacity = "1";
  });

  document.getElementById("wpil-back-button-container").style.display = "none";
  emailContainer.style.display = "none";
  formContainer.style.display = "none";
  document.getElementById("wpil-loader").style.display = "none";
  document.getElementById("payment-element").innerHTML = "";
  emailInput.value = "";

  activeStripe = null;
  activeElements = null;
  activeType = null;
  currentPlanButton = null;
});

emailInput.addEventListener("blur", async () => {
    if (!currentPlanButton || !emailInput.value) return;
    const type = currentPlanButton.dataset.type;
    const priceId = currentPlanButton.dataset.priceId;
    triggerStripeIntent(emailInput.value, type, priceId);
});

emailSubmit.addEventListener("click", async (e) => {
    e.preventDefault();
    if (!emailInput.value) return;
    const type = currentPlanButton.dataset.type;
    const priceId = currentPlanButton.dataset.priceId;
    triggerStripeIntent(emailInput.value, type, priceId);
})

async function triggerStripeIntent(email, type, priceId = null) {
  document.getElementById("wpil-loader").style.display = "block";

  const payload = { email, type };
  if (type === "custom") {
    payload.quantity = parseInt(creditSlider.value, 10);
  }

  payload.price_id = priceId;

  try {
    const res = await fetch(STRIPE.apiUrl + "/create-intent", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });

    const { clientSecret, error } = await res.json();
    if (error) throw new Error(error);

    const elements = stripe.elements({ clientSecret });
    const paymentElement = elements.create("payment");
    document.getElementById("payment-element").innerHTML = "";
    paymentElement.mount("#payment-element");

    activeStripe = stripe;
    activeElements = elements;
    activeType = type;

    // Show payment form
    formContainer.style.display = "block";
    emailContainer.style.display = "none";
  } catch (err) {
    alert("Failed to load Stripe form: " + err.message);
  } finally {
    document.getElementById("wpil-loader").style.display = "none";
  }
}

  // Handle form submission
  document.getElementById("wpil-payment-form").addEventListener("submit", async (e) => {
    e.preventDefault();

    const email = emailInput.value;
    const stripe = activeStripe;
    const elements = activeElements;
    const type = activeType;
    const errorDiv = document.getElementById("card-errors");
    const form = jQuery(e.target);
    errorDiv.textContent = "";

    if (!stripe || !elements || !type) {
      alert("Please select a plan and enter your email.");
      return;
    }

    if(form.hasClass('wpil-form-disabled')){
        return;
    }else{
        form.addClass('wpil-form-disabled');
    }

    const method = type === "recurring" ? "confirmSetup" : "confirmPayment";
    const result = await stripe[method]({
      elements,
      confirmParams: {
        payment_method_data: { billing_details: { email } },
      },
      redirect: 'if_required'
    });

    if (result.error) {
      errorDiv.textContent = result.error.message;
    } else {
        showSubscriptionSetupModal(type === "recurring");
        setupUserSubscription(type === "recurring");
    }
  });
});

let loop = 0;
function setupUserSubscription(recurring = false){
    jQuery.ajax({
        type: 'POST',
        url: ajaxurl,
        dataType: 'json',
        data: {
            action: 'setup_user_ai_subscription',
            recurring: (recurring) ? '1': '0',
            nonce: "<?php echo wp_create_nonce(get_current_user_id() . 'setup-ai-subscription');?>"
        },
        error: function (jqXHR, textStatus, errorThrown) {
            var wrapper = document.createElement('div');
            jQuery(wrapper).append('<strong>' + textStatus + '</strong><br>');
            jQuery(wrapper).append(jqXHR.responseText);
            wpil_swal({"title": "Error", "content": wrapper, "icon": "error"});
        },
        success: function(response){
            console.log(response);
            loop++;
            if(response.status && response.status === 'subscription-setup' || loop > 100){
                showSubscriptionSuccess(recurring);
                window.location.reload(true);
            }else{
                setTimeout(function(){setupUserSubscription(recurring)}, 3000);
            }
        },
        complete: function(){
        }
    });
}

function clearUserSubscription(){
    jQuery.ajax({
        type: 'POST',
        url: ajaxurl,
        dataType: 'json',
        data: {
            action: 'clear_user_ai_subscription',
            nonce: "<?php echo wp_create_nonce(get_current_user_id() . 'clear-ai-subscription');?>"
        },
        error: function (jqXHR, textStatus, errorThrown) {
            var wrapper = document.createElement('div');
            jQuery(wrapper).append('<strong>' + textStatus + '</strong><br>');
            jQuery(wrapper).append(jqXHR.responseText);
            wpil_swal({"title": "Error", "content": wrapper, "icon": "error"});
        },
        success: function(response){
            if(response.status && response.status === 'subscription-cleared'){
                showSubscriptionSuccess(null, 'Subscription cancelled.');
                window.location.reload(true);
            }
        },
        complete: function(){
        }
    });
}

</script>

<div id="wpil-subscription-setup-modal" style="display:none;">
  <div class="wpil-modal-overlay"></div>
  <div class="wpil-modal-content">
    <div class="wpil-modal-spinner"></div>
    <div class="wpil-modal-checkmark" aria-hidden="true">
        <svg width="64" height="64" viewBox="0 0 64 64" fill="none">
            <circle cx="32" cy="32" r="30" stroke="#7147b1" stroke-width="4" fill="none" />
            <path d="M18 34 L28 44 L46 22" stroke="#7147b1" stroke-width="4" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </div>
    <div class="wpil-modal-questionmark" aria-hidden="true">
          <svg width="64" height="64" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg" fill="#7147b1">
            <path d="M24,3c-11.58614,0 -21,9.41387 -21,21c0,11.58613 9.41386,21 21,21c11.58614,0 21,-9.41387 21,-21c0,-11.58613 -9.41386,-21 -21,-21zM24,5c10.50526,0 19,8.49474 19,19c0,10.50526 -8.49474,19 -19,19c-10.50526,0 -19,-8.49474 -19,-19c0,-10.50526 8.49474,-19 19,-19zM23.86133,13.05859c-2.935,0 -5.21427,1.43547 -6.07227,3.73047c-0.235,0.577 -0.34375,1.06142 -0.34375,1.60742c0,0.625 0.34317,0.98242 0.95117,0.98242c0.546,0 0.812,-0.23475 1,-0.96875c0.453,-2.169 2.07572,-3.49609 4.38672,-3.49609c2.497,0 4.27539,1.57647 4.27539,3.85547c0,1.639 -0.76384,2.74697 -2.71484,4.04297c-2.185,1.421 -3.07617,2.71659 -3.07617,4.80859v0.73242c0,0.672 0.32755,1.2187 1.06055,1.2207c0.718,0 1.01563,-0.516 1.01563,-1.25v-0.41992c0,-1.733 0.57805,-2.62159 2.62305,-3.93359c2.139,-1.374 3.29297,-2.98197 3.29297,-5.29297c0,-3.263 -2.68344,-5.61914 -6.39844,-5.61914zM23.32813,33.00781c-0.811,0 -1.44922,0.6398 -1.44922,1.4668c0,0.812 0.63822,1.45117 1.44922,1.45117c0.827,0 1.46875,-0.63917 1.46875,-1.45117c0,-0.827 -0.64175,-1.4668 -1.46875,-1.4668z"/>
        </svg>
    </div>
    <div class="wpil-modal-message">Setting up subscription…</div>
    <div class="wpil-modal-actions">
      <button id="wpil-modal-confirm" class="button-primary">Yes</button>
      <button id="wpil-modal-cancel" class="button-secondary">No</button>
    </div>
  </div>
</div>

<style>
    #wpil-subscription-setup-modal {
  position: fixed;
  z-index: 9999;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  font-family: 'Funnel Sans', sans-serif;
}

.wpil-modal-overlay {
  position: absolute;
  width: 100%;
  height: 100%;
  background: rgba(0,0,0,0.4);
  cursor: default;
}

.wpil-modal-content {
  position: absolute;
  top: 50%;
  left: 50%;
  width: 320px;
  max-width: 90%;
  padding: 2rem;
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 4px 20px rgba(0,0,0,0.2);
  transform: translate(-50%, -50%);
  text-align: center;
  transition: all 0.3s ease;
}

.wpil-modal-spinner {
  width: 48px;
  height: 48px;
  margin: 0 auto 16px;
  border: 5px solid #ccc;
  border-top-color: #7147b1;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.wpil-modal-checkmark,
.wpil-modal-questionmark {
  font-size: 48px;
  display: none;
  margin-bottom: 16px;
}

.wpil-modal-message {
  font-size: 1.15rem;
  font-weight: 600;
  color: #333;
}

.wpil-modal-actions{
    display: none;
    margin: 20px 0 0 0;
}

.wpil-confetti-piece {
  position: fixed;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  opacity: 1;
  z-index: 10000;
  pointer-events: none;
  animation: confetti-fall 2.5s ease-out forwards;
}

@keyframes confetti-fall {
  0% {
    transform: translate3d(0, 0, 0) rotate(0deg);
    opacity: 1;
  }
  100% {
    transform: translate3d(var(--dx, 0), var(--dy, 100vh), 0) rotate(720deg);
    opacity: 0;
  }
}


</style>
<script>
function showSubscriptionConfirmModal(text, onConfirm = null) {
  const modal = document.getElementById('wpil-subscription-setup-modal');
  if (!modal || !onConfirm) return;

    modal.querySelector('.wpil-modal-spinner').style.display = 'none';
    modal.querySelector('.wpil-modal-checkmark').style.display = 'none';
    modal.querySelector('.wpil-modal-questionmark').style.display = 'block';
    modal.querySelector('.wpil-modal-message').textContent = text;
    modal.style.display = 'block';

  // Allow closing by overlay click
    const overlay = modal.querySelector('.wpil-modal-overlay');
    overlay.style.cursor = 'pointer';
    overlay.onclick = () => hideSubscriptionSetupModal();

    const actions = modal.querySelector(".wpil-modal-actions");
    const confirmBtn = document.getElementById("wpil-modal-confirm");
    const cancelBtn = document.getElementById("wpil-modal-cancel");

    actions.style.display = "block";
    confirmBtn.onclick = () => {
      modal.style.display = "none";
      if (typeof onConfirm === "function") onConfirm();
    };

    cancelBtn.onclick = () => {
      modal.style.display = "none";
      hideSubscriptionSetupModal();
    };
}

function showSubscriptionSetupModal(recurring, customText = '') {
  const modal = document.getElementById('wpil-subscription-setup-modal');
  if (!modal) return;

  modal.querySelector('.wpil-modal-spinner').style.display = 'block';
  modal.querySelector('.wpil-modal-checkmark').style.display = 'none';
  modal.querySelector('.wpil-modal-questionmark').style.display = 'none';
  modal.querySelector('.wpil-modal-actions').style.display = 'none';
  let text;
  if(!customText){
    text = (recurring) ? 'Setting up subscription…': 'Completing purchase…';
  }else{
    text = customText;
  }

  modal.querySelector('.wpil-modal-message').textContent = text;
  
  // Disable click-to-close during loading
  modal.querySelector('.wpil-modal-overlay').style.cursor = 'default';
  modal.querySelector('.wpil-modal-overlay').onclick = null;

  modal.style.display = 'block';

  // Allow closing by overlay click
    /*const overlay = modal.querySelector('.wpil-modal-overlay');
    overlay.style.cursor = 'pointer';
    overlay.onclick = () => hideSubscriptionSetupModal();*/
}

function showSubscriptionSuccess(recurring, customText = '') {
  const modal = document.getElementById('wpil-subscription-setup-modal');
  if (!modal) return;

  modal.querySelector('.wpil-modal-spinner').style.display = 'none';
  modal.querySelector('.wpil-modal-checkmark').style.display = 'block';
  modal.querySelector('.wpil-modal-questionmark').style.display = 'none';
  if(customText){
    modal.querySelector('.wpil-modal-message').textContent = customText;
  }else{
    modal.querySelector('.wpil-modal-message').textContent = (recurring) ? 'Subscription setup complete!': 'Purchase complete!';
    // Trigger confetti burst! THERE IS NO SUCH THING AS TOO MUCH CONFETTI!
    triggerConfettiExplosion();
  }

  setTimeout(hideSubscriptionSetupModal, 1000);
}

function hideSubscriptionSetupModal() {
  const modal = document.getElementById('wpil-subscription-setup-modal');
  if(modal){
    modal.style.display = 'none';
    modal.querySelector('.wpil-modal-spinner').style.display = 'none';
    modal.querySelector('.wpil-modal-checkmark').style.display = 'none';
    modal.querySelector('.wpil-modal-questionmark').style.display = 'none';
  }
}

function triggerConfettiExplosion() {
  const colors = ["#7147b1", "#28a745", "#007bff", "#ffc107", "#e83e8c", "#17a2b8"];
  const numPieces = 250;

  for (let i = 0; i < numPieces; i++) {
    const confetti = document.createElement("div");
    confetti.className = "wpil-confetti-piece";

    const size = Math.random() * 6 + 6;
    confetti.style.width = `${size}px`;
    confetti.style.height = `${size}px`;
    confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];

    const x = Math.random() * window.innerWidth;
    const y = Math.random() * window.innerHeight;
    confetti.style.left = `${x}px`;
    confetti.style.top = `${y}px`;

    const dx = (Math.random() - 0.5) * 300 + "px";
    const dy = (Math.random() * 600 + 200) + "px";

    confetti.style.setProperty('--dx', dx);
    confetti.style.setProperty('--dy', dy);

    document.body.appendChild(confetti);
    setTimeout(() => confetti.remove(), 4500);
  }
}

</script>
        <?php
    }

    public static function create_ai_credit_popup(){
        $sub = Wpil_AI::get_user_ai_subscription();
        $authed = Wpil_Settings::get_linkwhisper_ai_user_id();
        $credits = Wpil_AI::get_available_ai_credits();
        $auth_url = admin_url('admin.php?page=link_whisper_ai_subscription');
        if (empty($authed) && 'ai-subscription' !== Wpil_Base::get_current_page()){
            // if the user has dismissed this popup
            if(!empty(get_user_meta(get_current_user_id(), 'wpil_dismissed_ai_notice_banner', true))){
                // stop here
                return;
            }
        ?>
            <style>
            @media screen and (min-width: 768px) {
                #wpbody {
                    position: relative;
                    top: 32px;
                }

                .wpil-no-ai-banner {
                    width: 100%;
                    height: 32px;
                    background: #4272fd;
                    position: fixed;
                    top: 32px;
                    z-index: 9999;
                    margin: 0 0 0 -20px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: #fff;
                    font-weight: bold;
                    font-size: 14px;
                    cursor: pointer;
                    transition: background 0.2s ease;
                }

                #inbound_suggestions_page .wpil-no-ai-banner{
                    margin: 0 0 0 -23px;
                }

                .wpil-no-ai-banner:hover {
                    background: #2c55cb;
                }

                .wpil-hide-ai-banner{
                    position: absolute;
                    right: 170px;
                }

                .wpil-hide-ai-banner:hover{
                    background: #7866ff;
                }

                .wpil-ai-popup-overlay {
                    display: none;
                    position: fixed;
                    top: 0; left: 0; right: 0; bottom: 0;
                    background: rgba(0,0,0,0.4);
                    z-index: 9999991;
                    justify-content: center;
                    align-items: center;
                }

                .wpil-ai-popup {
                    background: #fff;
                    border-radius: 8px;
                    /*width: 90%;*/
                    box-shadow: 0 4px 16px rgba(0,0,0,0.3);
                    position: relative;

                    display: flex;
                    gap: 24px;
                    flex-direction: row;
                    text-align: left;
                    padding: 24px;
                }

                .wpil-ai-popup-close {
                    position: absolute;
                    top: 10px; right: 10px;
                    font-size: 18px;
                    cursor: pointer;
                    background: none;
                    border: none;
                    color: #888;
                }

                .wpil-ai-popup-close:hover {
                    color: #333;
                }

                .wpil-ai-popup-left {
                    display: flex;
                    flex-direction: column;
                    justify-content: space-between;
                    max-width: 75%;
                }

                .wpil-ai-popup-left h3 {
                    font-size: 1rem;
                    margin-bottom: 8px;
                }

                .wpil-ai-popup-right {
                    flex: 1;
                    border-left: 1px solid #eee;
                    padding-left: 20px;
                    display: flex;
                    flex-direction: column;
                }

                .wpil-ai-popup-close {
                    position: absolute;
                    top: 10px;
                    right: 10px;
                    font-size: 18px;
                    cursor: pointer;
                    background: none;
                    border: none;
                    color: #888;
                }

                .wpil-ai-popup-close:hover {
                    color: #333;
                }

                .ai-linking-table {
                width: 100%;
                border-collapse: collapse;
                overflow: hidden;
                font-family: "Segoe UI", "Helvetica Neue", sans-serif;
                font-size: 16px;
                background-color: #fff;
                }

                .ai-linking-table thead {
                background-color: #f8f8f8;
                }

                .ai-linking-table th,
                .ai-linking-table td {
                padding: 14px 18px;
                text-align: left;
                }

                .ai-linking-table tbody tr:nth-child(even) {
                background-color: #fafafa;
                }

                .ai-linking-table tbody tr:hover {
                background-color: #f1f5ff;
                }

                .ai-linking-table th {
                font-weight: 700;
                color: #333;
                font-size: 18px;
                }

                .ai-linking-table td {
                vertical-align: top;
                font-size: 16px;
                }

                /* Custom highlight columns */
                .ai-benefit {
                color: #228B22; /* forest green */
                }

                .manual-drawback {
                color: #000; /* black */
                }
            }

            @media screen and (max-width: 767px) {
                .wpil-no-ai-banner,
                .wpil-ai-popup-overlay,
                .wpil-ai-popup{
                    display: none;
                }
            }

            @media screen and (max-width: 1480px) {
                .wpil-ai-upgrade-1{
                    display: none;
                }
                .ai-linking-table td{
                    font-size: 15px;
                }
            }
            @media screen and (max-width: 1200px) {
                .wpil-ai-upgrade-2{
                    display: none;
                }
                .ai-linking-table td{
                    font-size: 14px;
                }
            }

            @media screen and (max-width: 1000px) {
                .wpil-ai-popup-right{
                    display: none;
                }
                .ai-linking-table td{
                    font-size: 14px;
                }
            }

            @media screen and (max-width: 960px) {
                .wpil-hide-ai-banner{
                    right: 42px;
                }
            }
            </style>

            <!-- Banner -->
            <div class="wpil-no-ai-banner" id="wpil-no-ai-banner">
                <div>🚀 Connect Link Whisper AI to unlock powerful features</div>
                <span id="wpil-hide-ai-banner" class="dashicons dashicons-no-alt wpil-hide-ai-banner"></span>
            </div>

            <!-- Modal Popup -->
            <div class="wpil-ai-popup-overlay" id="wpil-ai-popup-overlay">
            <div class="wpil-ai-popup" id="wpil-ai-popup">
                <button class="wpil-ai-popup-close" id="wpil-popup-close">&times;</button>

                <!--
                <div class="wpil-ai-popup-left wpil_styles">
                    <div>
                        <h3 style="font-size: 22px;">Link Whisper AI Gives You:</h3>
                        <ul>
                            <li>🤖 Smarter internal links placed instantly for you</li>
                            <li>🔗 More relevant links, so your readers stay engaged</li>
                            <li>🧠 Higher conversions with intelligent keyword targeting</li>
                            <li>🛒 Automatic product detection to boost affiliate linking</li>
                            <li>🗺️ A visual map of your internal linking strategy</li>
                            <li>🔁 Automatic renewal—so your AI tools never stop working</li>
                        </ul>
                    </div>
                    <div>
                        <a href="<?php echo esc_url($auth_url); ?>" style="margin-top:15px; user-select: none; text-align: center; max-width: 170px;" id="wpil-connect-ai-button" class="button-primary" data-ai-authed="<?php echo (!empty($authed)) ? '1': '0';?>" style="margin-top: 20px;">Upgrade!</a>
                    </div>
                </div>-->

                <div class="wpil-ai-popup-right_old">
                    <table class="ai-linking-table">
                        <thead>
                            <tr>
                                <th>Feature</th>
                                <th>With AI</th>
                                <th>Without AI</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Speed</td>
                                <td class="ai-benefit">Quickly scans and links across all content</td>
                                <td class="manual-drawback">Time-consuming, manual scanning required</td>
                            </tr>
                            <tr>
                                <td>Accuracy</td>
                                <td class="ai-benefit">Identifies relevant and contextual links with NLP</td>
                                <td class="manual-drawback">Prone to human error or oversight</td>
                            </tr>
                            <tr>
                                <td>SEO Optimization</td>
                                <td class="ai-benefit">Boosts topical authority with smart keyword targeting</td>
                                <td class="manual-drawback">Relies on SEO knowledge and manual effort</td>
                            </tr>
                            <tr class="wpil-ai-upgrade-1">
                                <td>Scalability</td>
                                <td class="ai-benefit">Works efficiently across hundreds or thousands of posts</td>
                                <td class="manual-drawback">Difficult to manage at scale</td>
                            </tr>
                            <tr class="wpil-ai-upgrade-2">
                                <td>User Engagement</td>
                                <td class="ai-benefit">Suggests links that improve time-on-site and reduce bounce rates</td>
                                <td class="manual-drawback">Easy to miss opportunities for engagement</td>
                            </tr>
                            <tr class="wpil-ai-upgrade-1">
                                <td>Cost Efficiency</td>
                                <td class="ai-benefit">Saves time = saves money</td>
                                <td class="manual-drawback">Labor-intensive and slow</td>
                            </tr>
                            <tr>
                                <td>Smart Filtering</td>
                                <td class="ai-benefit">Avoids linking irrelevant or low-value content</td>
                                <td class="manual-drawback">Manual review needed for quality control</td>
                            </tr>
                            <tr>
                                <td>Consistency</td>
                                <td class="ai-benefit">Applies linking logic uniformly across site</td>
                                <td class="manual-drawback">Depends on individual effort and style</td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="wpil_styles">
                        <a href="<?php echo esc_url($auth_url); ?>" style="margin-top:15px; user-select: none; text-align: center; max-width: 170px;" id="wpil-connect-ai-button" class="button-primary" data-ai-authed="<?php echo (!empty($authed)) ? '1': '0';?>" style="margin-top: 20px;">Upgrade!</a>
                    </div>
                </div>
            </div>
            </div>

            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    const wpBody = document.getElementById('wpbody');
                    const banner = document.getElementById('wpil-no-ai-banner');
                    const dismissBanner = document.getElementById('wpil-hide-ai-banner');
                    const overlay = document.getElementById('wpil-ai-popup-overlay');
                    const popup = document.getElementById('wpil-ai-popup');
                    const closeBtn = document.getElementById('wpil-popup-close');

                    // Open popup on banner click
                    banner.addEventListener('click', () => {
                        overlay.style.display = 'flex';
                        jQuery.ajax({
                            type: 'POST',
                            url: ajaxurl,
                            dataType: 'json',
                            data: {
                                action: 'user_opened_ai_popup'
                            },
                        });
                    });

                    // Close when clicking close button
                    closeBtn.addEventListener('click', () => {
                        overlay.style.display = 'none';
                    });

                    // Close when clicking outside the popup
                    overlay.addEventListener('click', (e) => {
                        if (!popup.contains(e.target)) {
                            overlay.style.display = 'none';
                        }
                    });
                
                    // Perma hide the banner when the user dimisses it
                    dismissBanner.addEventListener('click', () => {
                        wpBody.style.top = 0;
                        overlay.style.display = 'none';
                        overlay.classList.add('wpil-hidden');
                        banner.style.display = 'none';
                        jQuery.ajax({
                            type: 'POST',
                            url: ajaxurl,
                            dataType: 'json',
                            data: {
                                action: 'user_dismissed_ai_popup'
                            },
                        });
                    });

                    const connectBtn = document.getElementById("wpil-connect-ai-button");
                    if (!connectBtn || connectBtn.dataset.aiAuthed == 1) return;

                    connectBtn.addEventListener("click", (e) => {
                        e.preventDefault();

                        const authUrl = connectBtn.href;

                        const width = 600;
                        const height = 700;
                        const left = (window.screen.width / 2) - (width / 2);
                        const top = (window.screen.height / 2) - (height / 2);

                        const popup = window.open(authUrl, 'LinkWhisperAIConnect', `width=${width},height=${height},top=${top},left=${left}`);

                        if (!popup) {
                            alert("Popup blocked! Please allow popups for this site to connect AI.");
                            return;
                        }

                        // Check every second if the popup has closed
                        const interval = setInterval(() => {
                        if (popup.closed) {
                            clearInterval(interval);
                            // Call a function to check if auth was completed (or just reload)
                            window.location.href = "<?php echo esc_url(admin_url('admin.php?page=link_whisper_ai_subscription')); ?>";
                        }
                        }, 1000);
                    });*/
                });
            </script>

            <?php 
            return;
        }

        $renew = '';
        if(!empty($sub)){
            $timestamp = (!empty($sub->expiration) && !empty(strtotime($sub->expiration))) ? strtotime($sub->expiration): time();
            $date_format = get_option('date_format', '');
            if(!empty($date_format)){
                $renew = date($date_format, $timestamp);
            }else{
                $day = date('j', $timestamp); // Day without leading zero
                $suffix = date('S', $timestamp); // Ordinal suffix
                $renew = date('M', $timestamp) . " {$day}{$suffix}, " . date('Y', $timestamp);
            }
        }
        ?>
        <div id="credit-status-container">
        <div id="credit-status-display"><svg xmlns="http://www.w3.org/2000/svg" class="wpil-credit-icon" viewBox="0 0 512 512"><!--!Font Awesome Free 6.7.2 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2025 Fonticons, Inc.--><path d="M512 80c0 18-14.3 34.6-38.4 48c-29.1 16.1-72.5 27.5-122.3 30.9c-3.7-1.8-7.4-3.5-11.3-5C300.6 137.4 248.2 128 192 128c-8.3 0-16.4 .2-24.5 .6l-1.1-.6C142.3 114.6 128 98 128 80c0-44.2 86-80 192-80S512 35.8 512 80zM160.7 161.1c10.2-.7 20.7-1.1 31.3-1.1c62.2 0 117.4 12.3 152.5 31.4C369.3 204.9 384 221.7 384 240c0 4-.7 7.9-2.1 11.7c-4.6 13.2-17 25.3-35 35.5c0 0 0 0 0 0c-.1 .1-.3 .1-.4 .2c0 0 0 0 0 0s0 0 0 0c-.3 .2-.6 .3-.9 .5c-35 19.4-90.8 32-153.6 32c-59.6 0-112.9-11.3-148.2-29.1c-1.9-.9-3.7-1.9-5.5-2.9C14.3 274.6 0 258 0 240c0-34.8 53.4-64.5 128-75.4c10.5-1.5 21.4-2.7 32.7-3.5zM416 240c0-21.9-10.6-39.9-24.1-53.4c28.3-4.4 54.2-11.4 76.2-20.5c16.3-6.8 31.5-15.2 43.9-25.5l0 35.4c0 19.3-16.5 37.1-43.8 50.9c-14.6 7.4-32.4 13.7-52.4 18.5c.1-1.8 .2-3.5 .2-5.3zm-32 96c0 18-14.3 34.6-38.4 48c-1.8 1-3.6 1.9-5.5 2.9C304.9 404.7 251.6 416 192 416c-62.8 0-118.6-12.6-153.6-32C14.3 370.6 0 354 0 336l0-35.4c12.5 10.3 27.6 18.7 43.9 25.5C83.4 342.6 135.8 352 192 352s108.6-9.4 148.1-25.9c7.8-3.2 15.3-6.9 22.4-10.9c6.1-3.4 11.8-7.2 17.2-11.2c1.5-1.1 2.9-2.3 4.3-3.4l0 3.4 0 5.7 0 26.3zm32 0l0-32 0-25.9c19-4.2 36.5-9.5 52.1-16c16.3-6.8 31.5-15.2 43.9-25.5l0 35.4c0 10.5-5 21-14.9 30.9c-16.3 16.3-45 29.7-81.3 38.4c.1-1.7 .2-3.5 .2-5.3zM192 448c56.2 0 108.6-9.4 148.1-25.9c16.3-6.8 31.5-15.2 43.9-25.5l0 35.4c0 44.2-86 80-192 80S0 476.2 0 432l0-35.4c12.5 10.3 27.6 18.7 43.9 25.5C83.4 438.6 135.8 448 192 448z"/></svg> <?php echo (int) $credits;?> AI Credits</div>
        </div>

        <div id="credit-popup-overlay" class="hidden">
        <div id="credit-status-popup">
            <button class="credit-popup-close" aria-label="Close popup">&times;</button>
            <div class="credit-popup-inner">
                <div class="credit-popup-header">
                    <div class="main-popup-header">Link Whisper AI Credits</div>
                    <span class="credit-popup-label">Your account balance</span>
                    <div class="credit-popup-balance">
                        <svg xmlns="http://www.w3.org/2000/svg" class="wpil-credit-icon" viewBox="0 0 512 512"><!--!Font Awesome Free 6.7.2 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2025 Fonticons, Inc.--><path d="M512 80c0 18-14.3 34.6-38.4 48c-29.1 16.1-72.5 27.5-122.3 30.9c-3.7-1.8-7.4-3.5-11.3-5C300.6 137.4 248.2 128 192 128c-8.3 0-16.4 .2-24.5 .6l-1.1-.6C142.3 114.6 128 98 128 80c0-44.2 86-80 192-80S512 35.8 512 80zM160.7 161.1c10.2-.7 20.7-1.1 31.3-1.1c62.2 0 117.4 12.3 152.5 31.4C369.3 204.9 384 221.7 384 240c0 4-.7 7.9-2.1 11.7c-4.6 13.2-17 25.3-35 35.5c0 0 0 0 0 0c-.1 .1-.3 .1-.4 .2c0 0 0 0 0 0s0 0 0 0c-.3 .2-.6 .3-.9 .5c-35 19.4-90.8 32-153.6 32c-59.6 0-112.9-11.3-148.2-29.1c-1.9-.9-3.7-1.9-5.5-2.9C14.3 274.6 0 258 0 240c0-34.8 53.4-64.5 128-75.4c10.5-1.5 21.4-2.7 32.7-3.5zM416 240c0-21.9-10.6-39.9-24.1-53.4c28.3-4.4 54.2-11.4 76.2-20.5c16.3-6.8 31.5-15.2 43.9-25.5l0 35.4c0 19.3-16.5 37.1-43.8 50.9c-14.6 7.4-32.4 13.7-52.4 18.5c.1-1.8 .2-3.5 .2-5.3zm-32 96c0 18-14.3 34.6-38.4 48c-1.8 1-3.6 1.9-5.5 2.9C304.9 404.7 251.6 416 192 416c-62.8 0-118.6-12.6-153.6-32C14.3 370.6 0 354 0 336l0-35.4c12.5 10.3 27.6 18.7 43.9 25.5C83.4 342.6 135.8 352 192 352s108.6-9.4 148.1-25.9c7.8-3.2 15.3-6.9 22.4-10.9c6.1-3.4 11.8-7.2 17.2-11.2c1.5-1.1 2.9-2.3 4.3-3.4l0 3.4 0 5.7 0 26.3zm32 0l0-32 0-25.9c19-4.2 36.5-9.5 52.1-16c16.3-6.8 31.5-15.2 43.9-25.5l0 35.4c0 10.5-5 21-14.9 30.9c-16.3 16.3-45 29.7-81.3 38.4c.1-1.7 .2-3.5 .2-5.3zM192 448c56.2 0 108.6-9.4 148.1-25.9c16.3-6.8 31.5-15.2 43.9-25.5l0 35.4c0 44.2-86 80-192 80S0 476.2 0 432l0-35.4c12.5 10.3 27.6 18.7 43.9 25.5C83.4 438.6 135.8 448 192 448z"/></svg>
                        <span class="balance-amount"><?php echo (int)$credits; ?> credits</span>
                    </div>
                </div>

                <p class="credit-popup-description">
                    Credits power Link Whisper’s AI. They are used for creating suggestions, advanced content analysis, keyword generation, and more.
                </p>

                <?php if(!empty($sub)){ ?>
                    <div class="current-plan">
                        <span class="bold-text">Your current plan is:</span> <?php echo (isset($sub->title)) ? esc_html($sub->title) : '';?>
                        <div>
                            <span class="bold-text">It renews on:</span> <?php echo esc_html($renew);?>
                        </div>
                    </div>
                <?php }elseif(!empty($credits)){ ?>
                    <div class="current-plan">
                        <span class="bold-text">Your current plan is:</span> Pay as you go.
                    </div>
                <?php }else{ ?>
                    <div class="current-plan">
                        Want to save on credits? <span class="bold-text">Get a plan that fits your site!</span>
                    </div>
                <?php } ?>
                <div class="credit-popup-actions">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=link_whisper_ai_subscription')); ?>" class="credit-btn"><?php echo (!empty($sub)) ? esc_html__('Manage Plan', 'wpil'): esc_html__('Subscribe', 'wpil');?></a>
                </div>
            </div>
        </div>
        </div>
        <style>
            /* Fixed top-right credit button */
            #credit-status-container {
                position: fixed;
                top: 30px;
                right: 160px;
                z-index: 10000;
            }

            #credit-status-display {
            color: #007bff;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            padding: 6px 12px;
            border-radius: 6px;
            transition: background 0.2s ease;
            }

            #credit-status-display:hover {
            background: rgba(0, 123, 255, 0.1);
            }

            /* Full screen overlay */
            #credit-popup-overlay {
            position: fixed;
            inset: 0;
            background-color: rgba(0, 0, 0, 0.2);
            display: flex;
            justify-content: flex-end;
            padding: 60px 20px 20px;
            z-index: 9999;
            }

            #credit-popup-overlay.hidden {
            display: none;
            }

            /* The popup panel */
            #credit-status-popup {
                position: absolute;
                left: calc(50% - 160px);
                background: #fff;
                border-radius: 8px;
                padding: 20px;
                max-width: 380px;
                box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
                animation: slideFadeIn 0.2s ease-out;
            }

            @keyframes slideFadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
            }

            .credit-popup-inner p {
            margin: 0.8rem 0;
            font-size: 0.85rem;
            color: #333;
            }

            .credit-popup-header .main-popup-header{
                font-weight: bold;
                font-size: 20px;
                margin-bottom: 15px;
            }

            .credit-popup-label {
                font-size: 0.85rem;
                color: #666;
            }

            .wpil-credit-icon{
                width: 18px;
                height: 18px;
                position: relative;
                top: 3px;
                color: #00bbff;
                fill: #00bbff;
            }

            .credit-popup-balance {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1d4ed8;
            margin: 10px 0 15px;
            }

            .credit-popup-inner .credit-popup-description {
            font-size: 0.85rem;
            color: #444;
            margin-bottom: 1.5rem;
            }

            .credit-popup-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            }

            .credit-btn {
                padding: 8px 14px;
                font-size: 0.85rem;
                border-radius: 6px;
                text-decoration: none;
                font-weight: 500;
                background: #007bff;
                color: #fff !important;
                border: none;
                transition: background 0.2s ease;
            }

            .credit-btn:hover {
            background: #0056b3;
            }

            .current-plan {
                font-size: 1rem;
                color: #111;
                margin-bottom: 1.5rem;
                line-height: 1.5rem;
            }

            .current-plan .bold-text{
                font-weight: 600;
            }

            .credit-popup-close {
                position: absolute;
                top: 10px;
                right: 12px;
                font-size: 1.25rem;
                font-weight: bold;
                background: none;
                border: none;
                color: #555;
                cursor: pointer;
                z-index: 1;
                padding: 0;
                line-height: 1;
            }

            .credit-popup-close:hover {
                color: #000;
            }
        </style>
        <script>
            const creditBtn = document.getElementById('credit-status-display');
            const overlay = document.getElementById('credit-popup-overlay');

            creditBtn.addEventListener('click', () => {
                overlay.classList.remove('hidden');
            });

            overlay.addEventListener('click', (e) => {
                // Close only if clicking outside the popup
                if (!e.target.closest('#credit-status-popup')) {
                overlay.classList.add('hidden');
                }
            });

            const closeBtn = document.querySelector('.credit-popup-close');
            closeBtn.addEventListener('click', () => {
                document.getElementById('credit-popup-overlay').classList.add('hidden');
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    document.getElementById('credit-popup-overlay').classList.add('hidden');
                }
            });
        </script>
        <?php
    }

    /**
     * Not currently used... Delete if we pass 2.9.5 without needing it
     **/
    public static function create_estimate_model(){
        ?>
        <div id="wpil-estimate-modal" style="display: none;">
            <div class="wpil-estimate-overlay"></div>
            <div class="wpil-estimate-box">
                <p>Estimating recommended plan size...</p>
                <div id="wpil-progress-bar"><div id="wpil-progress-inner"></div></div>
                <p id="wpil-progress-text">Starting...</p>
            </div>
            </div>
            <script>
                jQuery(document).ready(function($) {
                    $('#wpil-ai-plan-estimator').on('click', function(e) {
                        e.preventDefault();
                        $('#wpil-estimate-modal').fadeIn();
                        $('#wpil-progress-text').text('Starting...');
                        estimateLoop(true); // first loop resets
                    });

                    function estimateLoop(reset = false, estimateMode = 'link-whisper') {
                        $.post(ajaxurl, {
                        action: 'wpil_estimate_site_processing_cost',
                        reset: reset ? 1 : 0,
                        estimate_mode: estimateMode
                        }, function(response) {
                        if (typeof response !== 'object') {
                            try {
                            response = JSON.parse(response);
                            } catch (e) {
                            console.error('Bad response:', response);
                            $('#wpil-progress-text').text('Error calculating estimate.');
                            return;
                            }
                        }

                        $('#wpil-progress-text').text(
                            response.finished
                            ? `Done! Estimated cost: ${response.cost} credits`
                            : `Processing... ${response.posts_remaining} posts remaining`
                        );

                        let totalPosts = response.total + 1;
                        let progressPercent = Math.min(100, Math.round(((totalPosts - response.posts_remaining) / totalPosts) * 100));
                        $('#wpil-progress-inner').css('width', progressPercent + '%');

                        if (!response.finished) {
                            setTimeout(() => estimateLoop(false), 500);
                        } else {
                            $('#wpil-estimate-result').html(`<strong>Estimated credits required: ${response.cost}</strong>`);
                            $('#wpil-estimate-modal').fadeOut();
                            let plan = '';
                            let planClass = '';
                            if(response.cost < 1050){
                                plan = 'Based on our analysis, a Link Whisper 1k Monthly plan would work best for your site.';
                                planClass = '.plan-1k';
                            }else if (response.cost < 2050){
                                plan = 'Based on our analysis, a Link Whisper 2k Monthly plan would work best for your site.';
                                planClass = '.plan-2k';
                            }else if (response.cost < 5050){
                                plan = 'Based on our analysis, a Link Whisper 5k Monthly plan would work best for your site.';
                                planClass = '.plan-5k';
                            }else{
                                plan = 'Based on our analysis, a custom plan would work best for your site.';
                            }

                            $("#wpil-plan-estimation-intro, #wpil-ai-plan-estimator").addClass('hidden');
                            $("#wpil-plan-estimation").append(plan).removeClass('hidden');

                            if(planClass){
                                $(planClass).removeClass('hidden');
                                $('.plan-card' + planClass).addClass('featured');
                            }

                            // Add CSS class or highlight suggested plan, e.g.:
                            $('.plan-box').removeClass('recommended');
                            $(`.plan-box[data-threshold-min="${response.cost}"]`).addClass('recommended');
                        }
                        });
                    }
                });

            </script>
            <style>
                #wpil-estimate-modal {
                    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
                    background: rgba(0,0,0,0.4);
                    display: flex; justify-content: center; align-items: center;
                    z-index: 9999;
                }
                .wpil-estimate-box {
                    background: #fff;
                    padding: 20px;
                    border-radius: 5px;
                    text-align: center;
                    min-width: 300px;
                }
                #wpil-progress-bar {
                    width: 100%;
                    height: 10px;
                    background: #eee;
                    margin-top: 10px;
                    border-radius: 5px;
                    overflow: hidden;
                }
                #wpil-progress-inner {
                    height: 10px;
                    width: 0%;
                    background: #0073aa;
                    transition: width 0.4s ease;
                }
            </style>
        <?php
    }


    /**
     * Get ignore phrases
     */
    public static function getIgnorePhrases()
    {
        if(is_null(self::$ignore_phrases)){
            $phrases = [];
            $stemmed = array();
            $no_stemmed = is_null(self::$stemmed_ignore_phrases);
            foreach (self::getIgnoreWords() as $word) {
                if (strpos($word, ' ') !== false) {
                    $cleaned = preg_replace('/\s+/', ' ', $word);
                    $phrases[] = $cleaned;
                    if($no_stemmed){
                        $stemmed[] = Wpil_Word::getStemmedSentence($cleaned);
                    }
                }
            }

            self::$ignore_phrases = $phrases;

            if($no_stemmed){
                self::$stemmed_ignore_phrases = $stemmed;
            }
        }

        return self::$ignore_phrases;
    }

    /**
     * Gets the stemmed version of the phrases to ignore
     **/
    public static function getStemmedIgnorePhrases()
    {
        if(is_null(self::$stemmed_ignore_phrases)){
            self::getIgnorePhrases();
        }

        return self::$stemmed_ignore_phrases;
    }

    /**
     * Gets the site's current language as defined in the WP settings
     **/
    public static function getSiteLanguage(){
        $locale = get_locale();

        switch ($locale) {
            case 'en':
            case 'en_AU':
            case 'en_GB':
            case 'en_CA':
            case 'en_NZ':
            case 'en_ZA':
                $language = 'english';
                break;
            case 'es_ES':
            case 'es_AR':
            case 'es_EC':
            case 'es_CO':
            case 'es_VE':
            case 'es_DO':
            case 'es_UY':
            case 'es_PE':
            case 'es_CL':
            case 'es_PR':
            case 'es_CR':
            case 'es_GT':
            case 'es_MX':
                $language = 'spanish';
                break;
            case 'fr_CA':
            case 'fr_FR':
            case 'fr_BE':
                $language = 'french';
                break;
            case 'de_CH_informal':
            case 'de_DE':
            case 'de_CH':
            case 'de_AT':
                $language = 'german';
                break;
            case 'ru_RU':
                $language = 'russian';
                break;
            case 'pt_BR':
            case 'pt_PT_ao90':
            case 'pt_PT':
            case 'pt_AO':
                $language = 'portuguese';
                break;
            case 'nl_NL':
            case 'nl_NL_formal':
            case 'nl_BE':
                $language = 'dutch';
                break;
            case 'da_DK':
                $language = 'danish';
                break;
            case 'it_IT':
                $language = 'italian';
                break;
            case 'pl_PL':
                $language = 'polish';
                break;
            case 'sk_SK':
                $language = 'slovak';
                break;
            case 'nb_NO':
                $language = 'norwegian';
                break;
            case 'sv_SE':
                $language = 'swedish';
                break;
            case 'ar':
            case 'ary':
                $language = 'arabic';
                break;
            case 'sr_RS':
                $language = 'serbian';
                break;
            case 'fi':
                $language = 'finnish';
                break;
            case 'he_IL':
                $language = 'hebrew';
                break;
            case 'hi_IN':
                $language = 'hindi';
                break;
            case 'hu_HU':
                $language = 'hungarian';
                break;
            case 'ro_RO':
                $language = 'romanian';
                break;
            case 'uk':
                $language = 'ukrainian';
                break;
            case 'id_ID':
                $language = 'indonesian';
                break;
            case 'cs_CZ':
                $language = 'czech';
                break;
            case 'bg_BG':
                $language = 'bulgarian';
                break;
//            case 'el':
//                $language = 'greek';
//                break;
            default:
                $language = 'english';
                break;
        }

        return $language;
    }

    /**
     * Get ignore words
     */
    public static function getIgnoreWords()
    {
        if (is_null(self::$ignore_words)) {
            $words = get_option('wpil_2_ignore_words', null);
            // get the user's current language
            $selected_language = self::getSelectedLanguage();

            // if there are no stored words or the current language is different from the selected one
            if (is_null($words) || (WPIL_CURRENT_LANGUAGE !== $selected_language)) {
                $ignore_words_file = self::getIgnoreFile($selected_language);
                $words = file($ignore_words_file);

                foreach($words as $key => $word) {
                    $words[$key] = trim(Wpil_Word::strtolower($word));
                }
            } else {

                $words = explode("\n", $words);
                $words = array_unique($words);
                sort($words);

                foreach($words as $key => $word) {
                    $words[$key] = trim(Wpil_Word::strtolower($word));
                }
            }

            self::$ignore_words = $words;
        }

        return self::$ignore_words;
    }

    /**
     * Get stemmed versions of the ignore words
     */
    public static function getStemmedIgnoreWords()
    {
        if (is_null(self::$stemmed_ignore_words)) {
            $words = self::getIgnoreWords();
            foreach($words as $key => $word) {
                $words[$key] = Wpil_Word::remove_accents(trim(Wpil_Stemmer::Stem($word)));
            }

            // remove any duplicates
            $words = array_keys(array_flip($words));

            self::$stemmed_ignore_words = $words;
        }

        return self::$stemmed_ignore_words;
    }

    /**
     * Gets all current ignore word lists.
     * The word list for the language the user is currently using is loaded from the settings.
     * All other languages are loaded from the word files
     **/
    public static function getAllIgnoreWordLists(){
        $current_language       = self::getSelectedLanguage();
        $supported_languages    = self::getSupportedLanguages();
        $all_ignore_lists       = array();

        // go over all currently supported languages
        foreach($supported_languages as $language_id => $supported_language){

            // if the current language is the user's selected one
            if($language_id === $current_language){

                $words = get_option('wpil_2_ignore_words', null);
                if(is_null($words)){
                    $words = self::getIgnoreWords();
                }else{
                    $words = explode("\n", $words);
                    $words = array_unique($words);
                    sort($words);
                    foreach($words as $key => $word) {
                        $words[$key] = trim(Wpil_Word::strtolower($word));
                    }
                }

                $all_ignore_lists[$language_id] = $words;
            }else{
                $ignore_words_file = self::getIgnoreFile($language_id);
                $words = array();
                if(file_exists($ignore_words_file)){
                    $words = file($ignore_words_file);
                }else{
                    // if there is no word file, skip to the next one
                    continue;
                }
                
                if(empty($words)){
                    $words = array();
                }
                
                foreach($words as $key => $word) {
                    $words[$key] = trim(Wpil_Word::strtolower($word));
                }
                
                $all_ignore_lists[$language_id] = $words;
            }
        }

        return $all_ignore_lists;
    }

    /**
     * Get ignore words file based on current language
     *
     * @param $language
     * @return string
     */
    public static function getIgnoreFile($language)
    {
        switch($language){
            case 'spanish':
                $file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/ES_ignore_words.txt';
                break;
            case 'french':
                $file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/FR_ignore_words.txt';
                break;
            case 'german':
                $file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/DE_ignore_words.txt';
                break;
            case 'russian':
                $file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/RU_ignore_words.txt';
                break;
            case 'portuguese':
                $file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/PT_ignore_words.txt';
                break;
            case 'dutch':
                $file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/NL_ignore_words.txt';
                break;
            case 'danish':
                $file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/DA_ignore_words.txt';
                break;
            case 'italian':
                $file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/IT_ignore_words.txt';
                break;
            case 'polish':
                $file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/PL_ignore_words.txt';
                break;            
            case 'slovak':
                $file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/SK_ignore_words.txt';
                break;
            case 'norwegian':
                $file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/NO_ignore_words.txt';
                break;
            case 'swedish':
                $file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/SW_ignore_words.txt';
                break;            
            case 'arabic':
                $file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/AR_ignore_words.txt';
                break;
            case 'serbian':
                $file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/SR_ignore_words.txt';
                break;
            case 'finnish':
                $file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/FI_ignore_words.txt';
                break;
            case 'hebrew':
                $file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/HE_ignore_words.txt';
                break;
            case 'hindi':
                $file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/HI_ignore_words.txt';
                break;
            case 'hungarian':
                $file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/HU_ignore_words.txt';
                break;
            case 'romanian':
                $file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/RO_ignore_words.txt';
                break;
            case 'ukrainian':
                $file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/UK_ignore_words.txt';
                break;
            case 'indonesian':
                $file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/ID_ignore_words.txt';
                break;
            case 'czech':
                $file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/CZ_ignore_words.txt';
                break;
            case 'bulgarian':
                $file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/BG_ignore_words.txt';
                break;
//            case 'greek':
//                $file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/GK_ignore_words.txt';
//                break;
            default:
                $file = WP_INTERNAL_LINKING_PLUGIN_DIR . 'includes/ignore_word_lists/EN_ignore_words.txt';
                break;
        }

        return $file;
    }

    /**
     * Get selected post types
     *
     * @return mixed|void
     */
    public static function getPostTypes()
    {
        return get_option('wpil_2_post_types', ['post', 'page']);
    }

    /**
     * Get the post types that users have limited the suggestions to
     *
     * @return mixed|void
     */
    public static function getSuggestionPostTypes()
    {
        return get_option('wpil_suggestion_limited_post_types', self::getPostTypes());
    }

    /**
     * Gets the maximum number of words that should go into an anchor.
     * The default is 10
     * 
     * @return int
     */
    public static function getSuggestionMaxAnchorSize(){
        return (int) get_option('wpil_suggestion_anchor_max_size', 10);
    }

    /**
     * Gets the minimum number of words that should go into an anchor.
     * The default is 1 so that single-word target keyword matches can be allowed
     * 
     * @return int
     */
    public static function getSuggestionMinAnchorSize(){
        return (int) get_option('wpil_suggestion_anchor_min_size', 1);
    }

    /**
     * Get merged array of post types and term types
     *
     * @return array
     */
    public static function getAllTypes()
    {
        return array_merge(self::getPostTypes(), self::getTermTypes());
    }

    /**
     * Get selected post statuses
     *
     * @return array
     */
    public static function getPostStatuses()
    {
        return get_option('wpil_2_post_statuses', ['publish']);
    }

    public static function getInternalDomains(){
        $domains = get_transient('wpil_domains_marked_as_internal');
        if(empty($domains) && $domains === false){
            $domains = array();
            $domain_data = get_option('wpil_domains_marked_as_internal');
            $domain_data = explode("\n", $domain_data);
            foreach ($domain_data as $domain) {
                $pieces = wp_parse_url(trim($domain));
                if(!empty($pieces) && isset($pieces['host'])){
                    $domains[] = str_replace('www.', '', $pieces['host']);
                }
            }

            set_transient('wpil_domains_marked_as_internal', $domains, 15 * MINUTE_IN_SECONDS);
        }

        return $domains;
    }

    /**
     * Checks to see if ACF is installed on the site and if the user has disabled ACF processing or not
     * @return bool
     **/
    public static function get_acf_active(){
        if(!class_exists('ACF') || get_option('wpil_disable_acf', false)){
            return false;
        }

        return true;
    }


    /**
     * Gets any ACF fields that the user has specified as the only ones to process.
     * @return array $fields Returns an array if there's fields, and an empty array if there's no fields.
     **/
    public static function getACFFieldsToProcess(){
        $fields = get_transient('wpil_process_these_acf_fields');
        if(empty($fields)){
            $fields = get_option('wpil_process_these_acf_fields', array());

            if(empty($fields)){
                $fields = 'no-fields';
            }else{
                $fields = explode("\n", $fields);
                if(!empty($fields)){
                    $fields = array_filter(array_map('trim', $fields));
                }else{
                    $fields = 'no-fields';
                }
            }

            set_transient('wpil_process_these_acf_fields', $fields, 15 * MINUTE_IN_SECONDS);
        }

        if($fields === 'no-fields'){
            return array();
        }

        return $fields;
    }

    /**
     * Gets any custom content fields that the user has defined on his site and wants to process for content.
     * @return array $fields Returns an array if there's fields, and an empty array if there's no fields.
     **/
    public static function getCustomFieldsToProcess(){
        $fields = get_transient('wpil_custom_fields_to_process');
        if(empty($fields)){
            $fields = get_option('wpil_custom_fields_to_process', array());

            if(empty($fields)){
                $fields = 'no-fields';
            }else{
                $fields = explode("\n", $fields);
                if(!empty($fields)){
                    $fields = array_map('trim', $fields);
                }else{
                    $fields = 'no-fields';
                }
            }

            set_transient('wpil_custom_fields_to_process', $fields, 15 * MINUTE_IN_SECONDS);
        }

        if($fields === 'no-fields'){
            return array();
        }

        return $fields;
    }

    /**
     * Gets any custom content fields that the user has defined on his site and wants to process for content.
     * @return array $fields Returns an array if there's fields, and an empty array if there's no fields.
     **/
    public static function getPostReferenceFields(){
        $fields = get_transient('wpil_acf_post_reference_fields');
        if(empty($fields)){
            $fields = get_option('wpil_acf_post_reference_fields', array());

            if(empty($fields)){
                $fields = 'no-fields';
            }else{
                $fields = explode("\n", $fields);
                if(!empty($fields)){
                    $fields = array_map('trim', $fields);
                }else{
                    $fields = 'no-fields';
                }
            }

            set_transient('wpil_acf_post_reference_fields', $fields, 15 * MINUTE_IN_SECONDS);
        }

        if($fields === 'no-fields'){
            return array();
        }

        return $fields;
    }


    /**
     * Gets the currently supported languages
     * 
     * @return array
     **/
    public static function getSupportedLanguages(){
        $languages = array(
            'english'       => 'English',
            'spanish'       => 'Español',
            'french'        => 'Français',
            'german'        => 'Deutsch',
            'russian'       => 'Русский',
            'portuguese'    => 'Português',
            'dutch'         => 'Nederlands',
            'danish'        => 'Dansk',
            'italian'       => 'Italiano',
            'polish'        => 'Polskie',
            'norwegian'     => 'Norsk bokmål',
            'swedish'       => 'Svenska',
            'slovak'        => 'Slovenčina',
            'arabic'        => 'عربي',
            'serbian'       => 'Српски / srpski',
            'finnish'       => 'Suomi',
            'hebrew'        => 'עִבְרִית',
            'hindi'         => 'हिन्दी',
            'hungarian'     => 'Magyar',
            'romanian'      => 'Română',
            'ukrainian'     => 'Українська',
            'indonesian'    => 'Bahasa Indonesia',
            'czech'         => 'Čeština',
            'bulgarian'     => 'български'
//            'greek'         => 'Ελληνικά'
        );
        
        return $languages;
    }

    /**
     * Gets the currently selected language
     * 
     * @return array
     **/
    public static function getSelectedLanguage(){
        return get_option('wpil_selected_language', 'english');
    }

    /**
     * Gets the language for the current processing run.
     * Does a check to see if there's a translation plugin active.
     * If there is, it tries to set the current language to the current post's language.
     * If that's not possible, or there isn't a translation plugin, it defaults to the set language
     **/
    public static function getCurrentLanguage(){

        // if Polylang is active
        if(defined('POLYLANG_VERSION')){
            // see if we're creating suggestions and there's a post
            if( isset($_POST['action']) && ($_POST['action'] === 'get_post_suggestions' || $_POST['action'] === 'update_suggestion_display') &&
                isset($_POST['post_id']) && !empty($_POST['post_id']))
            {
                global $wpdb;
                $post_id = (int) $_POST['post_id'];

                // get the language ids
                $language_ids = $wpdb->get_col("SELECT `term_taxonomy_id` FROM $wpdb->term_taxonomy WHERE `taxonomy` = 'language'");

                // if there are no ids, return the selected language from the settings
                if(empty($language_ids)){
                    return self::getSelectedLanguage();
                }

                $language_ids = implode(', ', $language_ids);

                // check the term_relationships to see if any are applied to the current post
                $tax_id = $wpdb->get_var("SELECT `term_taxonomy_id` FROM $wpdb->term_relationships WHERE `object_id` = {$post_id} AND `term_taxonomy_id` IN ({$language_ids})");

                // if there are no ids, return the selected language from the settings
                if(empty($tax_id)){
                    return self::getSelectedLanguage();
                }

                // query the wp_terms to get the language code for the applied language
                $code = $wpdb->get_var("SELECT `slug` FROM $wpdb->terms WHERE `term_id` = {$tax_id}");

                // if we've gotten the language code, see if we support the language
                if($code){
                    $supported_language_codes = array(
                        'en' => 'english',
                        'es' => 'spanish',
                        'fr' => 'french',
                        'de' => 'german',
                        'ru' => 'russian',
                        'pt' => 'portuguese',
                        'nl' => 'dutch',
                        'da' => 'danish',
                        'it' => 'italian',
                        'pl' => 'polish',
                        'sk' => 'slovak',
                        'nb' => 'norwegian',
                        'sv' => 'swedish',
                        'sd' => 'arabic',
                        'snd' => 'arabic',
                        'sr' => 'serbian',
                        'fi' => 'finnish',
                        'he' => 'hebrew',
                        'hi' => 'hindi',
                        'hu' => 'hungarian',
                        'ro' => 'romanian',
                        'uk' => 'ukrainian',
                        'id' => 'indonesian',
                        'cs' => 'czech',
                        'bg' => 'bulgarian'
//                        'el' => 'greek'
                    );

                    // if we support the language, return it as the active one
                    if(isset($supported_language_codes[$code])){
                        return $supported_language_codes[$code];
                    }
                }
            }
        }

        // if WPML is active
        if(self::wpml_enabled()){
            // see if we're creating suggestions and there's a post
            if( isset($_POST['action']) && ($_POST['action'] === 'get_post_suggestions' || $_POST['action'] === 'update_suggestion_display') &&
            isset($_POST['post_id']) && !empty($_POST['post_id']))
            {
                global $wpdb;
                $post_id = (int) $_POST['post_id'];
                $post_type = get_post_type($post_id);
                $post_type = 'post_' . $post_type;
                $code = $wpdb->get_var("SELECT language_code FROM {$wpdb->prefix}icl_translations WHERE element_id = $post_id AND `element_type` = '{$post_type}'");

                if(!empty($code)){

                    $supported_language_codes = array(
                        'en' => 'english',
                        'es' => 'spanish',
                        'fr' => 'french',
                        'de' => 'german',
                        'ru' => 'russian',
                        'pt-br' => 'portuguese',
                        'pt-pt' => 'portuguese',
                        'nl' => 'dutch',
                        'da' => 'danish',
                        'it' => 'italian',
                        'pl' => 'polish',
                        'sk' => 'slovak',
                        'no' => 'norwegian',
                        'sv' => 'swedish',
                        'ar' => 'arabic',
                        'sr' => 'serbian',
                        'fi' => 'finnish',
                        'he' => 'hebrew',
                        'hi' => 'hindi',
                        'hu' => 'hungarian',
                        'ro' => 'romanian',
                        'uk' => 'ukrainian',
                        'id' => 'indonesian',
                        'cs' => 'czech',
                        'bg' => 'bulgarian'
//                        'el' => 'greek'
                    );

                    // if we support the language, return it as the active one
                    if(isset($supported_language_codes[$code])){
                        return $supported_language_codes[$code];
                    }
                }
            }
        }

        return self::getSelectedLanguage();
    }

    public static function getProcessingBatchSize(){
        $batch_size = (int) get_option('wpil_option_suggestion_batch_size', 300);
        if($batch_size < 10){
            $batch_size = 10;
        }
        return $batch_size;
    }

    /**
     * This function is used handle settting page submission
     *
     * @return  void
     */
    public static function save()
    {
        if (isset($_POST['wpil_save_settings_nonce'])
            && wp_verify_nonce($_POST['wpil_save_settings_nonce'], 'wpil_save_settings')
            && isset($_POST['hidden_action'])
            && $_POST['hidden_action'] == 'wpil_save_settings'
        ) {
            // ignore any external caches so they don't get in the way of the option saving
            Wpil_Base::ignore_external_object_cache(true);

            //prepare ignore words to save
            $ignore_words = sanitize_textarea_field(stripslashes(trim(base64_decode($_POST['ignore_words']))));
            $ignore_words = mb_split("\n|\r", $ignore_words);
            $ignore_words = array_unique($ignore_words);
            $ignore_words = array_filter(array_map('trim', $ignore_words));
            sort($ignore_words);
            $ignore_words = implode(PHP_EOL, $ignore_words);

            //update ignore words
            update_option(WPIL_OPTION_IGNORE_WORDS, $ignore_words);

            $setting_update_msg = '';
            if (empty($_POST[WPIL_OPTION_POST_TYPES]))
            {
                $_POST[WPIL_OPTION_POST_TYPES] = [];
            }

            if (empty($_POST['wpil_2_term_types'])) {
                $_POST['wpil_2_term_types'] = [];
            }

            // if the settings aren't set for showing all post types, remove all but the public ones
            if( empty($_POST['wpil_2_show_all_post_types']) &&
                isset($_POST['wpil_2_post_types']) &&
                !empty($_POST['wpil_2_post_types']))
            {
                $types_available = get_post_types(['public' => true]);
                foreach($_POST['wpil_2_post_types'] as $key => $type){
                    if(!isset($types_available[$type])){
                        unset($_POST['wpil_2_post_types'][$key]);
                    }
                }
            }

            if (empty($_POST['wpil_selected_target_keyword_sources'])) {
                $_POST['wpil_selected_target_keyword_sources'] = [];
            }

            if (empty($_POST['wpil_selected_ai_batch_processes'])) {
                $_POST['wpil_selected_ai_batch_processes'] = [];
            }
            
            if (empty($_POST['wpil_selected_post_content_target_keyword_sources'])) {
                $_POST['wpil_selected_post_content_target_keyword_sources'] = [];
            }

            if (empty($_POST['wpil_related_post_cat_ignore'])) {
                $_POST['wpil_related_post_cat_ignore'] = [];
            }

            if (empty($_POST['wpil_related_post_tag_ignore'])) {
                $_POST['wpil_related_post_tag_ignore'] = [];
            }

            // update the list of known keyword sources
            update_option('wpil_available_target_keyword_sources', Wpil_TargetKeyword::get_available_keyword_sources()); // should mention at_save, but the name would be getting too long

            // if the user just uploaded his secret access token...
            if(isset($_POST['wpil_upload_linkwhisper_ai_token']) && !empty($_POST['wpil_upload_linkwhisper_ai_token'])){
                // save the damn thing to the database
                $token = $_POST['wpil_upload_linkwhisper_ai_token'];

                if(is_string($token) && false !== strpos($token, 'ai-')){
                    // save the token to the options
                    update_option('wpil_ai_access_token', Wpil_Toolbox::encrypt($token));
                    // and update the flag so we know it's live
                    update_option('wpil_ai_access_authorized', '1');
                }
            }

            //save other settings
            $opt_keys = self::$keys;
            foreach($opt_keys as $opt_key) {
                if (array_key_exists($opt_key, $_POST)) {
                    if(is_array($_POST[$opt_key])){
                        update_option($opt_key, array_map('sanitize_text_field', $_POST[$opt_key]));
                    }elseif($opt_key === 'wpil_ignore_shortcodes_by_name'){
                        update_option($opt_key, sanitize_textarea_field($_POST[$opt_key]));
                    }else{
                        update_option($opt_key, sanitize_text_field($_POST[$opt_key]));
                    }
                }
            }

            // if the user has checked the option to cancel the active broken link scans
            if(isset($_POST['wpil_clear_error_checker_process']) && !empty($_POST['wpil_clear_error_checker_process'])){
                // run the finishing routine for the link checker
                update_option('wpil_error_reset_run', 0);
                Wpil_Error::mergeIgnoreLinks();
                Wpil_Error::deleteValidLinks();
                update_option('wpil_error_check_links_cron', 1);
                // tell the user that we've cancelled the process
                $setting_update_msg .= '&broken_link_scan_cancelled=1';
                set_transient('wpil_clear_error_checker_message', __('Broken Link scan cancelled!', 'wpil'), 60);
            }

            // if the user has checked the option to create the database tables
            if(isset($_POST['wpil_force_create_database_tables']) && !empty($_POST['wpil_force_create_database_tables'])){
                // run the table create routine
                Wpil_Base::createDatabaseTables();
                // tell the user that we've re-run the process
                $setting_update_msg .= '&database_creation_activated=1';
                set_transient('wpil_database_creation_message', __('Database creation routine complete!', 'wpil'), 60);
            }

            // if the user has checked the option to update the database tables
            if(isset($_POST['wpil_force_database_update']) && !empty($_POST['wpil_force_database_update'])){
                // run the table update routine
                Wpil_Base::updateTables(true);
                // tell the user that we've re-run the process
                $setting_update_msg .= '&database_update_activated=1';
                set_transient('wpil_database_update_message', __('Database update routine complete!', 'wpil'), 60);
            }

            // clear the item caches if they're set
            $setting_caches = array(
                'wpil_ignore_links',
                'wpil_ignore_sitemap_posts',
                'wpil_ignore_external_links',
                'wpil_ignore_keywords_posts',
                'wpil_ignore_keywords_posts_by_category',
                'wpil_ignore_categories',
                'wpil_domains_marked_as_internal',
                'wpil_links_to_ignore',
                'wpil_broken_links_to_ignore',
                'wpil_related_post_links_to_ignore',
                'wpil_ignore_elements_by_class',
                'wpil_ignore_shortcodes_by_name',
                'wpil_ignore_linking_roles',
                'wpil_ignore_pages_completely',
                'wpil_suggest_to_outbound_posts',
                'wpil_ignore_acf_fields',
                'wpil_ignore_click_links',
                'wpil_sponsored_domains',
                'wpil_nofollow_domains',
                'wpil_dofollow_domains',
                'wpil_custom_fields_to_process',
                'wpil_acf_post_reference_fields',
                'wpil_process_these_acf_fields',
                'wpil_applied_link_attributes',
                'wpil_redirected_post_ids',
                'wpil_redirected_post_urls',
                'wpil_related_post_settings',
                'wpil_ai_suggestion_post_process_cron_ids'
            );

            foreach($setting_caches as $cache){
                delete_transient($cache);
            }

            // set the tab that was last open
            if(isset($_POST['wpil_setting_selected_tab']) && !empty($_POST['wpil_setting_selected_tab'])){
                $setting_update_msg .= '&tab=' . sanitize_text_field($_POST['wpil_setting_selected_tab']);
            }else{
                $setting_update_msg .= '&tab=general-settings';
            }

            // flush the cache to make sure nothing's hanging
            wp_cache_flush();

            wp_redirect(admin_url('admin.php?page=link_whisper_settings&success'));
            exit;
        }
    }

    public static function getSkipSectionType()
    {
        return 'sentences';
    }

    public static function getSkipSentences()
    {
        return get_option('wpil_skip_sentences', 3);
    }

    public static function get_generate_quick_links(){
        return false;
        return !empty(get_option('wpil_generate_quick_links', 1));
    }

    /**
     * Gets the max number of suggestions that will be shown at once in the suggestion panel.
     * @return int
     **/
    public static function get_max_suggestion_count(){
        return (int) get_option('wpil_max_suggestion_count', 8);
    }

    /**
     * @return string
     */
    public static function detect_multilingual_plugin()
    {
        global $wpdb;

        // Check Polylang
        if (
            function_exists('pll_current_language') ||
            defined('POLYLANG_VERSION')
        ) {
            return 'polylang';
        }

        // Check WPML
        if (
            function_exists('icl_object_id') ||
            class_exists('SitePress') ||
            defined('ICL_SITEPRESS_VERSION')
        ) {
            $table_name = $wpdb->prefix . 'icl_languages';
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));

            if ($table_exists === $table_name) {
                $active_languages = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE active = 1");

                if ($active_languages > 1) {
                    return 'wpml';
                } else {
                    // WPML is active but only one language, or not yet configured
                }
            } else {
                // WPML active but not yet set up (no tables)
            }
        }

        // No multilingual plugin detected
        return 'none';
    }

    public static function polylang_enabled()
    {
        return self::detect_multilingual_plugin() === 'polylang';
    }

    /**
     * Checks to see if the site has a translation plugin active
     *
     * @return bool
     **/
    public static function translation_enabled(){
        return self::polylang_enabled() || self::wpml_enabled();
    }

    /**
     * Check if WPML installed and has at least 2 languages
     *
     * @return bool
     */
    public static function wpml_enabled()
    {
        if(null !== self::$wpml_enabled){
            return self::$wpml_enabled;
        }

        // false until proven otherwise
        self::$wpml_enabled = (self::detect_multilingual_plugin() === 'wpml');

        return self::$wpml_enabled;
    }

    /**
     * Gets the list of WPML supported locales
     **/
    public static function get_wpml_locales(){
        $locales = array();

        if(function_exists('icl_get_languages_locales')){
            $locales = icl_get_languages_locales();
        }

        return $locales;
    }

    /**
     * Checks if the given local is one supported by WPML
     **/
    public static function is_supported_wpml_local($local = ''){
        if(empty($local)){
            return false;
        }

        $locales = self::get_wpml_locales();
        if(!empty($locales) && isset($locales[$local])){
            return true;
        }else{
            return false;
        }
    }

    /**
     * Get checked term types
     *
     * @return array
     */
    public static function getTermTypes()
    {
        $terms = get_option('wpil_2_term_types', []);
        return array_intersect(array('category', 'post_tag', 'product_cat', 'product_tag'), $terms);
    }

    /**
     * Get ignore posts
     * Currently disabled.
     * Pulls posts from cache if available to save processing time.
     *
     * @return array
     */
    public static function getIgnorePosts()
    {
        return array();
        $posts = get_transient('wpil_ignore_links');
        if(empty($posts)){
            $posts = [];
            $links = get_option('wpil_ignore_links');
            $links = explode("\n", $links);
            foreach ($links as $link) {
                $link = trim($link);
                if(empty($link)){
                    continue;
                }

                $post = Wpil_Post::getPostByLink($link);
                if (!empty($post)) {
                    $posts[] = $post->type . '_' . $post->id;
                }
            }

            set_transient('wpil_ignore_links', $posts, 15 * MINUTE_IN_SECONDS);
        }

        return $posts;
    }

    /**
     * Get categories list to be ignored
     *
     * @return array
     */
    public static function getIgnoreCategoriesPosts()
    {
        return array(); // todo: remove if we implement ignore categories
    }

    /**
     * Get ignored orphaned posts
     * Used in the link report page
     *
     * @return array
     */
    public static function getIgnoreOrphanedPosts()
    {
        $posts = [];
        $links = get_option('wpil_ignore_orphaned_posts');
        $links = explode("\n", $links);
        foreach ($links as $link) {
            $link = trim($link);
            if(empty($link)){
                continue;
            }

            $post = Wpil_Post::getPostByLink($link);
            if (!empty($post)) {
                $posts[] = $post->type . '_' . $post->id;
            }
        }

        $ignored_categories = explode("\n", get_option('wpil_ignore_orphaned_posts_by_category', ''));
        if(!empty($ignored_categories)){
            foreach($ignored_categories as $cat_link){
                $category = Wpil_Post::getPostByLink(trim($cat_link));
                if (!empty($category)) {
                    $found = Wpil_Post::getCategoryPosts($category->id);
                    foreach($found as $id){
                        $posts[] = 'post_' . $id;
                    }
                }
            }
        }

        $completely_ignored = self::get_completely_ignored_pages();
        if(!empty($completely_ignored)){
            $posts = array_merge($posts, $completely_ignored);
        }

        // if we have posts
        if(!empty($posts)){
            // remove any duplicate entries
            $posts = array_values(array_flip(array_flip($posts)));
        }

        return $posts;
    }

    /**
     * Gets the ids of all the posts and categories that have been ignored from the suggestion process.
     * So it counts BOTH the posts that have been ignored directly, and the ones that have been ignored by category.
     * Also loops in the pages that have been completely ignored.
     **/
    public static function getAllIgnoredPosts(){
        $posts = array();

        $ignored_posts = self::getIgnorePosts();
        if(!empty($ignored_posts)){
            $posts = array_merge($posts, $ignored_posts);
        }

        $ignored_posts = self::getIgnoreCategoriesPosts();
        if(!empty($ignored_posts)){
            foreach($ignored_posts as $id){
                $posts[] = 'post_' . $id;
            }
        }

        $completely_ignored = self::get_completely_ignored_pages();
        if(!empty($completely_ignored)){
            $posts = array_merge($posts, $completely_ignored);
        }

        if(!empty($posts)){
            $posts = array_values(array_flip(array_flip($posts)));
        }

        return $posts;
    }

    /**
     * Get if the ignored posts aren't supposed to be shown or referenced on the Report pages
     * @return bool
     **/
    public static function hideIgnoredPosts(){
        // check if the hide setting has been set from the Settings page
        if(!empty(get_option('wpil_dont_show_ignored_posts', false))){
            return true;
        }

        // get if the specific user want's to hide the posts
        $options = get_user_meta(get_current_user_id(), 'report_options', true);
        $hide_ignored = (isset($options['hide_ignore'])) ? ( ($options['hide_ignore'] == 'off') ? false : true) : false;

        return $hide_ignored;
    }

    /**
     * Gets all available cats and tags for the current post types
     **/
    public static function get_available_related_post_terms(){
        $terms = get_transient('wpil_available_related_post_terms');

        if($terms === '' || $terms === false){
            $post_types = self::get_related_posts_active_post_types();

            if(!empty($post_types)){
                $taxes = get_object_taxonomies($post_types);
                if(!empty($taxes)){

                }
            }
        }

        return $terms;
    }

    /**
     * Get ignore posts (posts & terms)
     * Pulls posts from cache if available to save processing time.
     *
     * @return array
     */
    public static function getIgnoreSitemapPosts()
    {
        $posts = get_transient('wpil_ignore_sitemap_posts');
        if(empty($posts)){
            $posts = [];
            $links = get_option('wpil_ignore_sitemap_posts');
            $links = explode("\n", $links);
            foreach ($links as $link) {
                $link = trim($link);
                if(empty($link)){
                    continue;
                }

                $post = Wpil_Post::getPostByLink($link);
                if (!empty($post)) {
                    $posts[] = $post->type . '_' . $post->id;
                }
            }

            set_transient('wpil_ignore_sitemap_posts', $posts, 15 * MINUTE_IN_SECONDS);
        }

        return $posts;
    }

    /**
     * Gets an array of post ids to affirmatively make outbound links to.
     *
     * @return array
     */
    public static function getOutboundSuggestionPostIds()
    {
        $posts = get_transient('wpil_suggest_to_outbound_posts');
        if(empty($posts)){
            $posts = [];
            $links = get_option('wpil_suggest_to_outbound_posts', '');
            $links = explode("\n", $links);

            // check if there are any wildcarded links
            $wildcards = array('start' => array(), 'both' => array(), 'end' => array());
            $has_wildcards = false;
            foreach($links as $link){
                if(false !== strpos($link, '*')){
                    $start = (0 === strpos($link, '*')) ? true: false;
                    $end = (Wpil_Word::mb_strrpos($link, '*') === (mb_strlen($link) - 1)) ? true: false;
                    $cleaned = trim($link, '*/');

                    if($start && $end){
                        $wildcards['both'][] = $cleaned;
                        $has_wildcards = true;
                    }elseif($start){
                        $wildcards['start'][] = $cleaned;
                        $has_wildcards = true;
                    }elseif($end){
                        $wildcards['end'][] = $cleaned;
                        $has_wildcards = true;
                    }
                }
            }

            if($has_wildcards){
                $post_urls = Wpil_Toolbox::get_site_page_urls(false);
                foreach($post_urls as $pid => $url){
                    if(!empty($wildcards['start'])){
                        foreach($wildcards['start'] as $card){
                            // if we're wild matching for the start of the URL
                            $normalized_url = trim($url, '/');
                            $pos = Wpil_Word::mb_strpos($normalized_url, $card);
                            if((false !== $pos) && mb_strlen($normalized_url) === ($pos + mb_strlen($card))){
                                $posts[$pid] = true;
                            }
                        }
                    }

                    if(!empty($wildcards['both'])){
                        foreach($wildcards['both'] as $card){
                            // if the match shows up somewhere inside the URL
                            if(false !== strpos($url, $card)){
                                $posts[$pid] = true;
                            }
                        }
                    }

                    if(!empty($wildcards['end'])){
                        foreach($wildcards['end'] as $card){
                            // if the match shows up somewhere inside the URL
                            if(0 === strpos($url, $card)){
                                $posts[$pid] = true;
                            }
                        }
                    }
                }
            }

            foreach ($links as $link) {
                $post = Wpil_Post::getPostByLink($link);
                if (!empty($post)) {
                    $posts[$post->get_pid()] = true;
                }
            }

            if(empty($posts)){
                $posts = 'no-posts';
            }else{
                $posts = array_keys($posts);
            }

            set_transient('wpil_suggest_to_outbound_posts', $posts, 15 * MINUTE_IN_SECONDS);
        }

        // if there are no posts
        if($posts === 'no-posts'){
            // return an empty array
            $posts = array();
        }

        return $posts;
    }

    /**
     * Gets an array of type specific ids from the url input settings.
     */
    public static function getItemTypeIds($ids = array(), $type = 'post'){
        $data = array('post' => array(), 'term' => array());

        foreach($ids as $id){
            $dat = explode('_', $id);
            if(isset($dat[0]) && !empty($dat[0]) && isset($dat[1]) && !empty($dat[1])){
                $data[$dat[0]][] = $dat[1];
            }
        }

        if(isset($data[$type])){
            return $data[$type];
        }else{
            return $data;
        }
    }

    //Check if need to show ALL links
    public static function showAllLinks()
    {
        return !empty(get_option('wpil_show_all_links'));
    }

    /**
     * Gets if the user wants to count links from related post plugins in the Links Report.
     * Returns false if the user has opted to show all links because that includes related post links already.
     **/
    public static function get_related_post_links()
    {
        return !empty(get_option('wpil_count_related_post_links', false));
    }

    /**
     * Gets if the user wants to ignore links from latest post blocks/widgets in the Links Report.
     **/
    public static function ignore_latest_post_links()
    {
        return !empty(get_option('wpil_ignore_latest_posts', false));
    }

    /**
     * Gets if the user wants to run a special link update process when Gutenberg Reusable Blocks are Updated
     **/
    public static function update_reusable_block_links()
    {
        return !empty(get_option('wpil_update_reusable_block_links', false));
    }

    /**
     * Gets if the user wants to show comment links in the Links Report.
     * Returns false if the user has opted to show all links because that includes comments already.
     **/
    public static function getCommentLinks()
    {
        return (!empty(get_option('wpil_show_comment_links')) && !self::showAllLinks());
    }

    /**
     * Gets the current content formatting level when pulling links from content
     **/
    public static function getContentFormattingLevel()
    {
        // if the user has programattically disabled formatting, return zero
        if(apply_filters('wpil_disable_content_link_formatting', false)){
            return 0;
        }

        return (int) get_option('wpil_content_formatting_level', 2);
    }

    /**
     * Gets if the user wants to override the global $post varible during link scans with a new one that matches the content currently being scanned.
     * Mostly it's a compatibility setting for shortcodes that rely on the global $post variable to determine what to display
     **/
    public static function overrideGlobalPost()
    {
        return !empty(get_option('wpil_override_global_post_during_scan', false));
    }

    /**
     * Gets if the user wants to optimize the link scan for speed at the cost of error handling
     **/
    public static function optimize_link_scan_for_speed()
    {
        return !empty(get_option('wpil_optimize_link_scan_for_speed', false));
    }

    /**
     * Gets if the user wants to use the link data stored in the link table instead of the post meta
     **/
    public static function use_link_table_for_data()
    {
        return !empty(get_option('wpil_use_link_data_table', true));
    }

    /**
     * Gets a list of HTML tags that the user can choose to ignore from linking
     */
    public static function getPossibleIgnoreLinkingTags(){
        return array('p', 'span', 'li', 'div', 'ul', 'ol', 'blockquote', 'td', 'th', 'strong', 'i', 'code');
    }

    /**
     * 
     */
    public static function getIgnoreLinkingTags(){
        $tags = get_option('wpil_ignore_tags_from_linking', array());
        $tag_list = self::getPossibleIgnoreLinkingTags();
        $return_tags = array();

        if(!empty($tags) && is_array($tags)){
            foreach($tags as $tag){
                // if the tag is in the list of preapproved tags
                if(in_array($tag, $tag_list, true)){
                    // add it to the return list
                    $return_tags[] = $tag;
                }
            }
        }

        return $return_tags;
    }

    /**
     * Gets a list of Elementor modules that we could ignore if the user wants to
     */
    public static function getPossibleIgnoreElementorModules(){
        return Wpil_Editor_Elementor::getSupportedModules();
    }

    /**
     * Gets the list of Elementor modules that the user does want to ignore
     */
    public static function getIgnoreLinkingElementorModules(){
        $modules = get_option('wpil_ignore_elementor_from_linking', array());
        if(!empty($modules)){
            foreach($modules as $key => $module){
                $modules[$key] = trim($module);
            }
        }
        return $modules;
    }

    /**
     * Gets if the user has connected Link Whisper to the AI API
     **/
    public static function get_linkwhisper_ai_active(){
        return !empty(get_option('wpil_ai_access_authorized', '0'));
    }

    /**
     * Gets if the user has connected Link Whisper to the AI API
     **/
    public static function get_selected_ai_provider(){
        $selected = get_option('wpil_select_ai_provider', '');

        if(empty($selected)){
            if(self::get_linkwhisper_ai_active()){
                $selected = 'linkwhisper';
            }elseif(!empty(self::getOpenAIKey())){
                $selected = 'openai';
            }
        }
        return $selected;
    }

    /** 
     * Gets the access token needed to make ai requests
     **/
    public static function get_linkwhisper_ai_token(){
        $token = get_option('wpil_ai_access_token', '');
        if(empty($token)){
            return '';
        }

        $token = Wpil_Toolbox::decrypt($token);

        if(0 !== strpos(trim($token), 'ai-')){
            update_option('wpil_ai_token_decoding_error', '1');
        }else{
            update_option('wpil_ai_token_decoding_error', '0');
        }

        return (!empty($token)) ? trim($token): '';
    }

    /** 
     * Gets the access token needed to make ai requests
     **/
    public static function get_linkwhisper_ai_user_id(){
        return get_option('wpil_ai_access_user_id', '');
    }

    /**
     * Gets the email associated with this ai subscription
     **/
    public static function get_linkwhisper_ai_user_email(){
        $email = get_option('wpil_ai_access_user_email', '');
        if(empty($email)){
            $email = get_user_meta(get_current_user_id(), 'wpil_ai_access_user_email', true);
        }

        return $email;
    }

    /**
     * Delets the ai api tokens so that we can disconnect this site
     **/
    public static function disconnect_linkwhisper_ai(){
        delete_option('wpil_ai_access_token');
        delete_option('wpil_ai_access_user_id');
        delete_option('wpil_ai_access_user_email');
        delete_option('wpil_ai_access_authorized');
    }

    /**
     * Disconnects from the Google app on ajax call.
     **/
    public static function ajax_disconnect_ai_subscription(){
        if(isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'disconnect-ai-subscription')){
            self::disconnect_linkwhisper_ai();
        }
    }

    /** 
     * Gets the OpenAI API key that the user entered in the Settings.
     * Has optional obfuscation that hides all but the last four digites of the key
     **/
    public static function getOpenAIKey($obfuscate = false){
        $key = get_option('wpil_open_ai_api_key', '');
        if(empty($key) || !empty(self::get_linkwhisper_ai_active())){
            return '';
        }

        $key = Wpil_Toolbox::decrypt($key);

        if(0 !== strpos(trim($key), 'sk-')){
            update_option('wpil_open_ai_key_decoding_error', '1');
        }else{
            update_option('wpil_open_ai_key_decoding_error', '0');
        }

        if($obfuscate){
            $key = '**************************' . substr($key, -4);
        }

        return (!empty($key)) ? trim($key): '';
    }

    /** 
     * Gets the OpenAI API key that the user entered in the Settings.
     **/
    public static function getChatGPTVersion($key = ''){
        $defaults = array(
            'suggestion-scoring' => 'gpt-4o-mini',
            'post-summarizing' => 'gpt-4o',
            'product-detecting' => 'gpt-4o-mini',
            'keyword-detecting' => 'gpt-4o-mini',
        );

        $available_models = Wpil_AI::get_available_models();

        $standard = array(
            'create-post-embeddings' => 'text-embedding-3-large',
            'assess-sentence-anchors' => (isset($available_models['gpt-4o-mini']) ? 'gpt-4o-mini': 'gpt-3.5-turbo')
        );

        $data = get_option('wpil_chat_gpt_api', $defaults);

        $data = array_merge($data, $standard);

        if(empty($key)){
            return !empty($data) ? $data: $defaults;
        }

        if(isset($data[$key])){
            return trim($data[$key]);
        }

        return 'gpt-4o-mini';
    }

    public static function get_ai_suggestion_score_active(){
        return false;
        return get_option('wpil_ai_suggestion_score_active', false);
    }

    /**
     * Gets if the user has turned on the AI batch processing
     **/
    public static function get_ai_batch_processing_active(){
        return !empty(get_option('wpil_enable_ai_batch_processing', false));
    }

    /**
     * Gets the suggestion relatedness threshold
     **/
    public static function get_ai_suggestion_relatedness_threshold(){
        return floatval(get_option('wpil_suggestion_relatedness_threshold', 0.4500));
    }

    /**
     * Gets the post relatedness threshold
     **/
    public static function get_ai_sitemap_relatedness_threshold(){
        return floatval(get_option('wpil_sitemap_embedding_relatedness_threshold', 0.8500));
    }
    
    public static function get_available_ai_batch_processes(){
        // return a list of the available batch processes, indexed to their process code
        return array(
            4 => 'create-post-embeddings',
//            2 => 'post-summarizing', // todo: re-enable later
            3 => 'product-detecting',
            5 => 'keyword-detecting'
        );
    }

    public static function get_selected_ai_batch_processes($return_process_names = false, $remove_embedding = false){
        $processes = self::get_available_ai_batch_processes();
        $selected_processes = get_option('wpil_selected_ai_batch_processes', $processes);
        $available = array_intersect($selected_processes, array_flip($processes));

        if($remove_embedding && in_array(4, $available)){
            $key = array_search(4, $available);
            unset($available[$key]);
        }

        return ($return_process_names) ? array_map(function($id){ return Wpil_AI::get_process_name_from_code($id); }, $available): $available;
    }

    /**
     * Creates a list of the supported AI batch names for display
     **/
    public static function get_ai_batch_name_list(){
        $names = array(
            'post-summarizing'          => __('Post Summarization', 'wpil'),
            'product-detecting'         => __('Product Detection', 'wpil'),
            'create-post-embeddings'    => __('AI Relation Analysis', 'wpil'),
            'keyword-detecting'         => __('Keyword Analysis', 'wpil'),
        );

        return $names;
    }

    /**
     * Gets the max number of keywords that we're going to ask ChatGPT for
     **/
    public static function get_ai_keyword_count_max(){
        $count = intval(get_option('wpil_ai_generated_keyword_max_count', 30));
        return (!empty($count)) ? $count: 30;
    }
    
    /**
     * Sets the number of dimensions to use in embeddings
     **/
    public static function get_ai_embedding_dimensions(){
        $dimensions = get_option('wpil_ai_embedding_dimension_count', 3072);
    }

    /**
     * Gets the max post per batch limits for the AI processes.
     * Handles both Live and Batch download limits
     **/
    public static function get_ai_batch_limits(){
        $ai_service_active = self::get_linkwhisper_ai_active();
        $defaults = array(
            'live' => array(
                'post-summarizing'          => 100,
                'product-detecting'         => 100,
                'create-post-embeddings'    => ($ai_service_active) ? 50: 500,
                'keyword-detecting'         => 100
            ),
            'batch' => array(
                'post-summarizing'          => 100,
                'product-detecting'         => 100,
                'create-post-embeddings'    => ($ai_service_active) ? 50: 500,
                'keyword-detecting'         => 100,
            )
        );
        
        $limits = get_option('wpil_ai_batch_processing_limits', array());

        if(isset($limits['live']) && is_array($limits['live'])){
            if($ai_service_active && $limits['live']['create-post-embeddings'] > 50){
                $limits['live']['create-post-embeddings'] = 50;
            }
            $defaults['live'] = array_merge($defaults['live'], $limits['live']);
        }

        if(isset($limits['batch']) && is_array($limits['batch'])){
            $defaults['batch'] = array_merge($defaults['batch'], $limits['batch']);
        }

        return $defaults;
    }

    /**
     * Gets the processing limit for a specific AI process
     **/
    public static function get_ai_process_limit($process = '', $live = false){
        $limits = self::get_ai_batch_limits();
        $limit = 100;

        if($live){
            if(isset($limits['live'], $limits['live'][$process]) && !empty($limits['live'][$process])){
                $limit = (int) $limits['live'][$process];
            }
        }else{
            if(isset($limits['batch'], $limits['batch'][$process]) && !empty($limits['batch'][$process])){
                $limit = (int) $limits['batch'][$process];
            }
        }

        return $limit;
    }

    /**
     * Gets if the user wants to use AI to power the suggestions
     **/
    public static function get_use_ai_suggestions(){
        return (!empty(get_option('wpil_use_ai_suggestions', false)));
    }

    /**
     * Gets if the user wants to use AI to power the suggestions
     **/
    public static function get_disable_ai_anchor_building(){
        return (!empty(get_option('wpil_disable_ai_anchor_building', false)));
    }
    
    /**
     * Gets if the user wants to only see the top "AI" suggestions
     **/
    public static function get_show_top_ai_suggestions(){
        return (!empty(get_option('wpil_restrict_to_top_ai_suggestions', true)));
    }

    public static function has_ai_enabled(){
        return (!empty(self::getOpenAIKey()) || self::get_linkwhisper_ai_active());
    }

    /**
     * Checks to make sure that it's possible to do AI Powered Suggestions
     **/
    public static function can_do_ai_powered_suggestions(){
        // currently just checking to make sure taht there's at least 10% of posts processed
        if((!empty(self::getOpenAIKey()) || self::get_linkwhisper_ai_active()) && Wpil_AI::get_batch_status_completion_percent('calculated-post-embeddings') > 10){
            return true;
        }

        return false;
    }

    public static function ajax_set_ai_suggestions_use(){
        Wpil_Base::verify_nonce('ai-suggestion-change-nonce');

        if(!isset($_POST['status'])){
            wp_send_json(array('status' => 'no status!'));
        }

        $status = (int) !empty($_POST['status']);

        update_option('wpil_use_ai_suggestions', $status);
        wp_send_json(array('status' => 'updated!'));
    }

    /**
     * Gets if the user wants to update the Post Modified date when links are inserted.
     * Returns false by default, and only true if the user has activated the setting.
     **/
    public static function updatePostModifiedDate()
    {
        return (!empty(get_option('wpil_update_post_edit_date', false)));
    }

    /**
     * Gets if the user wants to prevent suggestions being made for posts marked as "noindex"
     **/
    public static function removeNoindexFromSuggestions()
    {
        return (!empty(get_option('wpil_remove_noindex_post_suggestions', false)));
    }

    /**
     * Gets if the user wants to force all LW created links to be in HTTPS.
     * Returns false by default, and only true if the user has activated the setting.
     **/
    public static function forceHTTPS()
    {
        return (!empty(get_option('wpil_force_https_links', false)));
    }

    /**
     * Gets if the user wants to use "Ugly" permalinks in the reports.
     * It turns out that calculating the "Pretty" permalinks in the reports can take a TON of time.
     * Using the ugly ones hardly takes any time at all
     **/
    public static function use_ugly_permalinks()
    {
        return (!empty(get_option('wpil_use_ugly_permalinks', false)));
    }

    /**
     * Gets if the user wants to try clearing the CDN cache after link deletes
     * Returns false by default, and only true if the user has activated the setting.
     **/
    public static function clear_cdn()
    {
        return (!empty(get_option('wpil_clear_cdn_link_delete', false)));
    }

    /**
     * Gets if the user wants to try flushing the object cache after unspecified actions
     * Returns false by default, and only true if the user has activated the setting.
     **/
    public static function flush_object_cache()
    {
        return (!empty(get_option('wpil_object_cache_flush', false)));
    }

    /**
     * Gets if the user wants to try optimizing the options table.
     * Returns false by default, and only true if the user has activated the setting.
     * @return bool
     **/
    public static function get_if_options_should_optimize()
    {
        return (!empty(get_option('wpil_optimize_option_table', false)));
    }

    /**
     * Gets if the user wants to try updating the post after certain actions. (Viz, link deletes)
     * Returns false by default, and only true if the user has activated the setting.
     * @return bool
     **/
    public static function update_post_after_actions()
    {
        return (!empty(get_option('wpil_update_post_after_action', false)));
    }

    /**
     * Gets if the user wants to make suggestion matches based on some of the words in the post title.
     **/
    public static function matchPartialTitles()
    {
        return (!empty(get_option('wpil_get_partial_titles', false)));
    }

    /**
     * Checks to see if the user has saved auth credentials on the site and has gotten authed in the past
     * @return bool
     **/
    public static function HasGSCCredentials(){
        $credentials = get_option('wpil_search_console_data');
        return (!empty($credentials) && isset($credentials['authorized']) && $credentials['authorized'] != false && isset($credentials['access_token']) && !empty($credentials['access_token']));
    }

    /**
     * Gets the configuration data for the GSC integration.
     * Was formerly in the GSC class, but instantiating the class would trigger a call to Google.
     * If the site wasn't connected, this would be unnecessary and would result in a 401 error.
     * @return array
     **/
    public static function getGSCConfiguration($return_wizard = false){
        // get the auth method
        $method = get_option('wpil_gsc_auth_method', 'standard');

        $return_wizard = ($return_wizard) ? '?return_wizard=1': '';

        switch($method){
            case 'standard':
                $credentials = self::get_credentials();

                $state_args = array('rest' => get_rest_url(null, '/' . Wpil_Rest::REST_SLUG . '/' . Wpil_Rest::GSC_ROUTE . $return_wizard));

                if(is_ssl() && current_user_can('activate_plugins')){
                    $user = wp_get_current_user();
                    if(!empty($user) && isset($user->data, $user->data->user_login)){
                        $state_args['check'] = base64_encode($user->data->user_login . ':' . Wpil_Toolbox::create_application_password_for_user($user->ID, 'link-whisper-gsc'));
                    }
                }

                $state = base64_encode(json_encode($state_args));

                $config = [
                    'application_name'  => 'Link Whisper',
                    'redirect_uri'      => WPIL_STORE_URL . '/wp-json/link-whisper/auth',
                    'scopes'            => [ 'https://www.googleapis.com/auth/webmasters.readonly' ],
                    'access_type'       => 'offline',
                    'state'             => $state,
                    'prompt'            => 'consent',
                ];

                $config = array_merge($config, $credentials);

            break;
            case 'custom_auth':
                $config = get_option('wpil_gsc_custom_config', array());
                if(!empty($config)){
                    $config['redirect_uri'] = 'urn:ietf:wg:oauth:2.0:oob';
                    $config['scopes']       = array('https://www.googleapis.com/auth/webmasters.readonly');
                }
            break;
            case 'legacy_api':
                // todo fill out
            break;
        }

        // todo handle empty config further down the line
        return $config;
    }

    public static function get_credentials ()
    {
        $credentials = get_option('wpil_gsc_remote_credentials', array());
        if(empty($credentials)){
            return self::get_remote_gsc_credentials();
        }else{
            $credentials = Wpil_Toolbox::deep_decrypt($credentials);
            
            // if the credentials don't have a valid client_id (probs because the salt/key has changed)
            if(!isset($credentials['client_id']) && !empty(Wpil_Toolbox::get_key()) && !empty(Wpil_Toolbox::get_salt())){
                // try getting some new ones and return the results of the attempt
                return self::get_remote_gsc_credentials();
            }

            return $credentials;
        }
        return [];
    }

    /**
     * Gets the GSC credentials from the proxy server and stores them in an option if they're available.
     **/
    private static function get_remote_gsc_credentials(){
        $response = wp_remote_get(WPIL_STORE_URL . '/wp-json/link-whisper/credentials', [
            'body' => [
                'name' => WPIL_PLUGIN_NAME
            ]
        ]);

        if ( !is_wp_error($response) && !empty($response = json_decode($response['body'], true)) ) {
            if ( isset($response['credentials']) ) {
                update_option('wpil_gsc_remote_credentials', Wpil_Toolbox::deep_encrypt($response['credentials']));
                return $response['credentials'];
            }
        }

        // if there's no creds, return an array
        return array();
    }

    /**
     * Gets the authentication URL for the GSC connection.
     * Was formerly in the GSC class, but instantiating the class would trigger a call to Google.
     * If the site wasn't connected, this would be unnecessary and would result in a 401 error.
     * @return string
     **/
    public static function getGSCAuthUrl($return_wizard = false){
        $config = self::getGSCConfiguration($return_wizard);

        $args = array(
            [
                'response_type'    => 'code',
                'client_id'        => $config['client_id'],
                'redirect_uri'     => $config['redirect_uri'],
                'scope'            => implode(' ', $config['scopes']),
                'state'            => $config['state'],
                'access_type'      => $config['access_type'],
                'prompt'           => $config['prompt'],
            ]
        );

        $url = add_query_arg($args, 'https://accounts.google.com/o/oauth2/v2/auth');

        return esc_url_raw($url);
    }

    /**
     * Gets if the user wants to automatically select a number of GSC keywords as Target Keywords.
     * @return int
     **/
    public static function get_if_autotag_gsc_keywords(){
        return (int) get_option('wpil_autotag_gsc_keywords', 1);
    }

    /**
     * Gets the basis that the user wants to autotag the keywords on.
     * @return string
     **/
    public static function get_autotag_gsc_keyword_basis(){
        return ('impressions' === get_option('wpil_autotag_gsc_keyword_basis', 'impressions')) ? 'impressions': 'clicks';
    }

    /**
     * Gets the number of GSC keywords to automatically select as Target Keywords.
     * Default is 10 keywords
     * @return int
     **/
    public static function get_autotag_gsc_keyword_count(){
        return (int) get_option('wpil_autotag_gsc_keyword_count', 10);
    }

    /**
     * Gets the target keyword sources the user has selected from the settings.
     * Automatically includes new keyword sources if the user hasn't saved them
     **/
    public static function getSelectedKeywordSources()
    {
        $kw_sources_known_at_save = get_option('wpil_available_target_keyword_sources', array());
        $kw_sources = Wpil_TargetKeyword::get_available_keyword_sources();
        $diffed_kw_sources = array_diff($kw_sources, $kw_sources_known_at_save);
        $selected_sources = get_option('wpil_selected_target_keyword_sources', $kw_sources);
        return array_merge($selected_sources, $diffed_kw_sources, array('custom'));
    }

    /**
     * Gets the target keyword sources the user has selected from the settings.
     * Automatically includes new keyword sources if the user hasn't saved them
     **/
    public static function get_selected_post_content_keyword_sources()
    {
        return get_option('wpil_selected_post_content_target_keyword_sources', Wpil_TargetKeyword::get_available_post_content_keyword_sources());
    }

    /**
     * Gets if links should have any HTML tags in their anchor texts removed when they are deleted.
     **/
    public static function delete_link_inner_html(){
        return !empty(get_option('wpil_delete_link_inner_html', false));
    }

    /**
     * Gets if the Inbound Internal links pointing to a specific post should be deleted when that post is deleted
     **/
    public static function delete_inbound_internal_on_post_delete(){
        return !empty(get_option('wpil_delete_links_to_post_on_delete', false));
    }

    /**
     * Check if need to show full HTML in suggestions
     *
     * @return bool
     */
    public static function fullHTMLSuggestions()
    {
        return !empty(get_option('wpil_full_html_suggestions'));
    }

    /**
     * Checks to see if the user has disabled post updating on follow-up actions.
     * Things like the URL Changer's update_post call after the changing code
     **/
    public static function disable_followup_post_updating(){
        return apply_filters('wpil_disable_url_changer_update', false);
    }

    /**
     * Gets any active suggestion filter based on requested index
     * @param string $index The $_REQUEST or stored data index to search for
     * @return bool|array
     */
    public static function get_suggestion_filter($index = ''){
        if(empty($index)){
            return false;
        }

        $filters_persistent = true; //!empty(get_option('wpil_make_suggestion_filtering_persistent', false));
        $filtering_settings = ($filters_persistent) ? get_user_meta(get_current_user_id(), 'wpil_persistent_filter_settings', true) : false;

        $status = false;
        switch ($index) {
            // bool filters
            case 'same_category':
            case 'same_tag':
            case 'select_post_types':
            case 'link_orphaned':
            case 'same_parent':
                if($filters_persistent){
                    $status = (isset($filtering_settings[$index]) && !empty($filtering_settings[$index])) ? true: false;
                }else{
                    $status = (isset($_REQUEST[$index]) && !empty($_REQUEST[$index])) ? true: false;
                }
            break;
            // number array filters
            case 'selected_category':
            case 'selected_tag':
                if($filters_persistent){
                    $data = (isset($filtering_settings[$index]) && !empty($filtering_settings[$index])) ? $filtering_settings[$index]: array();
                }else{
                    $data = (isset($_REQUEST[$index]) && !empty($_REQUEST[$index])) ? $_REQUEST[$index]: array();
                }

                $status = (!empty($data) && is_array($data)) ? array_filter(array_map(function($dat){ return (int)$dat; }, $data)): array();
            break;
            // selected post type filter
            case 'selected_post_types':
                if($filters_persistent){
                    $data = (isset($filtering_settings[$index]) && !empty($filtering_settings[$index])) ? $filtering_settings[$index]: array();
                }else{
                    $data = (isset($_REQUEST[$index]) && !empty($_REQUEST[$index])) ? $_REQUEST[$index]: array();
                }

                // make sure the post types that are being requested are ones that the user selected in the settings
                $status = (!empty($data) && is_array($data)) ? array_intersect(Wpil_Settings::getPostTypes(), $data): array();
            break;
            default:
                $status = false;
                break;
        }

        return $status;
    }

    /**
     * Updates the suggestion filter settings based on $_REQUEST data
     **/
    public static function update_suggestion_filters(){
        // if we're not making the filters persistent
//        if(empty(get_option('wpil_make_suggestion_filtering_persistent', false))){
            // exit
//            return;
//        }

        // set the default state of the filters. (off)
        $setting_data = array(
            'same_category' => false,
            'same_tag' => false,
            'select_post_types' => false,
            'link_orphaned' => false,
            'same_parent' => false,
            'selected_category' => array(),
            'selected_tag' => array(),
            'selected_post_types' => array(),
            'ai_relatedness_threshold' => 0
        );

        // go over the $_REQUEST variable to see if any of the filters are turned on
        foreach($setting_data as $index => $default){
            switch ($index) {
                // bool filters
                case 'same_category':
                case 'same_tag':
                case 'select_post_types':
                case 'link_orphaned':
                case 'same_parent':
                    $status = (isset($_REQUEST[$index]) && !empty($_REQUEST[$index])) ? true: false;
                break;
                // number array filters
                case 'selected_category':
                case 'selected_tag':
                    $data = (isset($_REQUEST[$index]) && !empty($_REQUEST[$index])) ? $_REQUEST[$index]: array();
                    $status = (!empty($data) && is_array($data)) ? array_filter(array_map(function($dat){ return (int)$dat; }, $data)): array();
                break;
                // selected post type filter
                case 'selected_post_types':
                    $data = (isset($_REQUEST[$index]) && !empty($_REQUEST[$index])) ? $_REQUEST[$index]: array();
                    // make sure the post types that are being requested are ones that the user selected in the settings
                    $status = (!empty($data) && is_array($data)) ? array_intersect(Wpil_Settings::getPostTypes(), $data): array();
                break;
                default:
                    $status = false;
                    break;
            }

            // if there is a filter active
            if(!empty($status)){
                // save the data
                $setting_data[$index] = $status;
            }
        }

        // update the stored settings with the results of our efforts
        update_user_meta(get_current_user_id(), 'wpil_persistent_filter_settings', $setting_data); // the settings are user-specific
    }

    /**
     * Gets the selected suggestion filtering options in a URL encoded string for when the suggestions are initially loaded
     * Checks for the global post type suggestion setting
     **/
    public static function get_suggestion_filter_string(){
        $indexes = array(
            'same_category',
            'same_tag',
            'select_post_types',
            'link_orphaned',
            'same_parent',
            'selected_category',
            'selected_tag',
            'selected_post_types'
        );

        $string_data = array();
        $suggestion_post_type_filtering = (!empty(get_option('wpil_limit_suggestions_to_post_types', false))) ? self::getSuggestionPostTypes() : false;

        foreach($indexes as $index){
            $filter_setting = self::get_suggestion_filter($index);
            if(!empty($filter_setting)){
                $string_data[$index] = is_array($filter_setting) ? implode(',', $filter_setting): $filter_setting;
            }
        }

        // if the user has selected a limited set of post types to point suggestions to
        if(!empty($suggestion_post_type_filtering) && is_array($suggestion_post_type_filtering)){
            $string_data['select_post_types'] = 1; // check the "filter post types" box
            $string_data['selected_post_types'] = implode(',', $suggestion_post_type_filtering); // and set the post types
        }

        return !empty($string_data) ? '&' . http_build_query($string_data): '';
    }

    /**
     * Get the max number of posts to search for suggestions
     *
     * @return int
     */
    public static function get_max_suggestion_post_count(){
        return (int) get_option('wpil_max_suggestion_post_count', 0);
    }

    /**
     * Get if we're going to be using the anchor word limits for suggestions that contain target keywords
     *
     * @return int
     */
    public static function get_use_anchor_limit_tk_matches(){
        return (int) get_option('wpil_force_keyword_exact_matches_word_limit', 1);
    }

    /**
     * Get links that the user wants to ignore from the broken links report
     *
     * @return array
     */
    public static function get_broken_ignore_links()
    {
        $links = get_transient('wpil_broken_links_to_ignore');
        if(empty($links)){

            $links = get_option('wpil_broken_links_to_ignore', array());
            if (!empty($links)) {
                $links = explode("\n", $links);
                foreach ($links as $key => $link) {
                    if(empty(trim($link)) || empty(esc_url_raw($link)) && !Wpil_Link::isRelativeLink($link)){
                        unset($links[$key]);
                    }else{
                        $links[$key] = str_replace('www.', '', trim($link));
                    }
                }
            }
            if(empty($links)){
                $links = 'no-links-ignored';
            }

            set_transient('wpil_broken_links_to_ignore', $links, 60 * MINUTE_IN_SECONDS);
        }

        if($links === 'no-links-ignored'){
            return array();
        }

        return $links;
    }

    /**
     * Get links that the user wants to ignore from the related posts widget
     *
     * @return array
     */
    public static function get_related_post_widget_ignore_posts()
    {
        $posts = get_transient('wpil_related_post_links_to_ignore');
        if(empty($posts)){
            $posts = [];
            $links = get_option('wpil_related_post_links_to_ignore', array());
            if (!empty($links)) {
                $links = explode("\n", $links);
                foreach ($links as $key => $link) {
                    if(empty(trim($link)) || empty(esc_url_raw($link)) && !Wpil_Link::isRelativeLink($link)){
                        continue;
                    }

                    $post = Wpil_Post::getPostByLink($link);

                    if(!empty($post) && !empty($post->id)){
                        $posts[] = $post->get_pid();
                    }
                }
            }
            if(empty($posts)){
                $posts = 'no-links-ignored';
            }

            set_transient('wpil_related_post_links_to_ignore', $posts, 60 * MINUTE_IN_SECONDS);
        }

        if($posts === 'no-links-ignored'){
            return array();
        }

        return $posts;
    }

    /**
     * Gets an array of any classes that the user wants to be ignored from both the Link Report and the Suggestions
     **/
    public static function get_ignored_element_classes(){
        $classes = get_transient('wpil_ignore_elements_by_class');
        if(empty($classes)){

            $classes = get_option('wpil_ignore_elements_by_class', array());
            if(!empty($classes)){
                $classes = explode("\n", $classes);
                foreach($classes as $key => $class){
                    $class = trim(trim($class, '.'));
                    if(empty($class)){
                        unset($classes[$key]);
                    }else{
                        $classes[$key] = $class;
                    }
                }
            }
            if(empty($classes)){
                $classes = 'no-elements-ignored';
            }

            set_transient('wpil_ignore_elements_by_class', $classes, 60 * MINUTE_IN_SECONDS);
        }

        if($classes === 'no-elements-ignored'){
            return array();
        }

        return $classes;
    }

    /**
     * Gets an array of shortcode names that the user wants to ignore
     **/
    public static function get_ignored_shortcode_names(){
        $shortcodes = get_transient('wpil_ignore_shortcodes_by_name');
        if(empty($shortcodes)){

            $shortcodes = get_option('wpil_ignore_shortcodes_by_name', array());
            if(!empty($shortcodes)){
                $shortcodes = explode("\n", $shortcodes);
                foreach($shortcodes as $key => $shortcode){
                    $shortcode = trim(preg_replace('`[^\w-]`', '', $shortcode)); // remove all non-word chars minus hyphens from the shortcode name
                    if(empty($shortcode)){
                        unset($shortcodes[$key]);
                    }else{
                        $shortcodes[$key] = $shortcode;
                    }
                }
            }

            $defaults = self::get_default_ignored_shortcodes();
            if(!empty($defaults) && is_array($shortcodes)){
                $shortcodes = array_merge($shortcodes, $defaults);
            }elseif(!empty($defaults) && empty($shortcodes)){
                $shortcodes = $defaults;
            }

            if(empty($shortcodes)){
                $shortcodes = 'no-shortcodes-ignored';
            }

            set_transient('wpil_ignore_shortcodes_by_name', $shortcodes, 60 * MINUTE_IN_SECONDS);
        }

        if($shortcodes === 'no-shortcodes-ignored'){
            return array();
        }

        return $shortcodes;
    }

    /**
     * Gets a list of the available roles
     **/
    public static function get_available_roles($format = false){
        $roles = apply_filters('wpil_filter_available_roles', wp_roles()->roles);

        // if we're supposed to format them for display
        if($format){
            $formatted = array();
            foreach($roles as $role => $data){
                $formatted[$role] = $data['name'];
            }

            return $formatted;
        }

        return $roles;
    }

    /**
     * Gets the list of roles that the user has selected.
     * For the time being, I'm going to make it so that admins are always in the list.
     **/
    public static function get_ignore_linking_roles(){
        $roles = get_option('wpil_ignore_linking_roles', array());
        $possible_roles = self::get_available_roles();

        // if there are stored roles
        if(!empty($roles)){
            // make sure that the stored roles are still valid
            $confirmed = array();
            foreach($roles as $role){
                if(isset($possible_roles[$role])){
                    $confirmed[$role] = $possible_roles[$role]['name'];
                }
            }
            $roles = $confirmed;
        }

        return $roles;
    }

    /**
     * Gets all of the shortcodes that we don't want to/can't process
     **/
    public static function get_default_ignored_shortcodes(){
        $shortcodes = array();
        // if GiveWP is active
        if(defined('GIVE_VERSION')){
            // add the payment reciept shortcode
            $shortcodes[] = 'give_receipt';
        }

        // return the list of assmebled shortcodes
        return $shortcodes;
    }

    /**
     * Gets an array of post & term ids that the user wants to ignore.
     **/
    public static function get_completely_ignored_pages(){
        $pages = get_transient('wpil_ignore_pages_completely');
        if(empty($pages)){
            $pages = array();

            $page_links = get_option('wpil_ignore_pages_completely', array());
            if(!empty($page_links)){
                $page_links = explode("\n", $page_links);
                foreach ($page_links as $link) {
                    $post = Wpil_Post::getPostByLink(trim($link));
                    if (!empty($post)) {
                        $pages[] = $post->type . '_' . $post->id;
                    }
                }
            }
            if(empty($pages)){
                $pages = 'no-pages-ignored';
            }

            set_transient('wpil_ignore_pages_completely', $pages, 60 * MINUTE_IN_SECONDS);
        }

        if($pages === 'no-pages-ignored'){
            return array();
        }

        return $pages;
    }

    /**
     * Get links that was marked as external
     *
     * @return array
     */
    public static function getMarkedAsExternalLinks()
    {
        $links = get_option('wpil_marked_as_external', '');

        if (!empty($links)) {
            $links = explode("\n", $links);
            foreach ($links as $key => $link) {
                $links[$key] = trim($link);
            }

            return $links;
        }

        return [];
    }

    /**
     * Gets if the user wants to use the post slug instead of the title for suggestions
     *
     * @return array
     */
    public static function use_post_slug_for_suggestions()
    {
        return !empty(get_option('wpil_post_slug_for_suggestions', false));
    }

    /**
     * Gets if the user wants to use the REST API to trade site interlinking data between posts
     *
     * @return array
     */
    public static function use_rest_api_for_site_interlinking()
    {
        return !empty(get_option('wpil_external_site_use_json_api', false));
    }

    /**
     * Gets an array of ACF fields that the user wants to ignore from processing
     **/
    public static function getIgnoredACFFields(){
        $field_data = get_transient('wpil_ignore_acf_fields');
        if(empty($field_data)){
            $field_data = get_option('wpil_ignore_acf_fields', array());

            if(is_string($field_data)){
                $field_data = array_map('trim', explode("\n", $field_data));
            }

            set_transient('wpil_ignore_acf_fields', $field_data, 60 * MINUTE_IN_SECONDS);
        }

        return $field_data;
    }

    /**
     * Checcks to see if the user wants to avoid inserting links into ACF created "text" fields
     **/
    public static function get_ignore_acf_text_fields(){
        return !empty(get_option('wpil_ignore_small_acf_text_fields', 0));
    }

    /**
     * Gets an array of URLs and anchors that the user doesn't want tracked by the click tracking
     * @return array
     **/
    public static function getIgnoredClickLinks(){
        $click_data = get_transient('wpil_ignore_click_links');
        if(empty($click_data)){
            $click_data = get_option('wpil_ignore_click_links', array());

            if(is_string($click_data)){
                $click_data = array_map('trim', explode("\n", $click_data));
            }elseif(empty($click_data)){
                $click_data = 'no-links-ignored';
            }

            set_transient('wpil_ignore_click_links', $click_data, 60 * MINUTE_IN_SECONDS);
        }

        if($click_data === 'no-links-ignored'){
            return array();
        }

        return $click_data;
    }

    /**
     * Gets a list of posts that have had redirects applied to their urls.
     * Obtains the redirect list from plugins that offer redirects.
     * Results are cached for 5 minutes
     * 
     * @param bool $flip Should we return a flipped array of post ids so they can be searched easily?
     * @return array $post_ids And array of posts that have had redirections applied to them
     **/
    public static function getRedirectedPosts($flip = false){
        global $wpdb;

        $post_ids = get_transient('wpil_redirected_post_ids');

        if(!empty($post_ids) && $post_ids !== 'no-ids'){
            // refresh the transient
            set_transient('wpil_redirected_post_ids', $post_ids, 5 * MINUTE_IN_SECONDS);
            // and return the ids
            return ($flip) ? array_flip($post_ids) : $post_ids;
        }elseif($post_ids === 'no-ids'){
            // if a prevsious run hadn't found any ids, return an empty array
            return array();
        }

        // set up the id array
        $post_ids = array();

        // if RankMath is active and the redirections table exists
        if(defined('RANK_MATH_VERSION') && !empty($wpdb->query("SHOW TABLES LIKE '{$wpdb->prefix}rank_math_redirections'"))){
            $dest_url_cache = array();

            $permalink_format = get_option('permalink_structure', '');
            $post_name_position = false;

            if(false !== strpos($permalink_format, '%postname%')){
                $pieces = explode('/', $permalink_format);
                $piece_count = count($pieces);
                $post_name_position = array_search('%postname%', $pieces);
            }

            // get the active redirect rules from Rank Math
            $two_years_ago = date('Y-m-d H:i:s', strtotime('-2 years'));
            $active_redirections = $wpdb->get_results("SELECT `id`, `url_to`, `sources` FROM {$wpdb->prefix}rank_math_redirections WHERE `status` = 'active' AND (`last_accessed` > '{$two_years_ago}' OR `updated` > '{$two_years_ago}'  AND `last_accessed` = '0000-00-00 00:00:00')");

            // if there are redirections
            if(!empty($active_redirections)){
                $redirection_ids = array();
                foreach($active_redirections as $dat){
                    if(false !== strpos($dat->sources, 'regex')){
                        continue;
                    }

                    // create a list of the destination urls so that we can exclude posts that aren't hidden by redirects
                    if(!isset($dest_url_cache[$dat->url_to])){
                        $post = Wpil_Post::getPostByLink($dat->url_to);
                        if(!empty($post) && $post->type === 'post'){
                            $dest_url_cache[$dat->url_to] = $post->id;
                        }
                    }

                    $redirection_ids[] = $dat->id;
                }

                // filter the destination urls to pull the post ids
                $ignore_ids = (!empty($dest_url_cache) && !empty(array_filter(array_values($dest_url_cache)))) ? array_filter(array_values($dest_url_cache)): array();

                // if there are posts with updated urls, get the ids so we can ignore them
                $ignore_posts = '';
                if($ignore_ids){
                    $ignore_posts = "AND `object_id` NOT IN (" . implode(', ', $ignore_ids) . ")";
                }

                $redirection_ids = implode(', ', $redirection_ids);
                $redirection_data = $wpdb->get_results("SELECT `from_url`, `object_id` FROM {$wpdb->prefix}rank_math_redirections_cache WHERE `redirection_id` IN ({$redirection_ids}) {$ignore_posts}"); // we're getting the redriects from the cache to save processing time. Rules based searching could take a long time

                // go over the data from the Rank Math cache
                $post_names = array();
                foreach($redirection_data as $dat){
                    // if a redirect was specified for a post, grab the id directly
                    if(isset($dat->object_id) && !empty($dat->object_id)){
                        $post_ids[] = $dat->object_id;
                    }else{
                        // if a url was redirected based on a rule, try to get the post name from the data so we can search the post table for it
                        $url_pieces = explode('/', $dat->from_url);
                        $url_pieces_count = count($url_pieces);

                        if($post_name_position && $url_pieces_count === $piece_count){  // if the url uses the permalink settings and therefor has the same number of pieces as the permalink string (EX: it's a post)
                            $post_names[] = $url_pieces[$post_name_position];
                        }elseif($url_pieces_count === 1){                               // if the url is just the slug
                            $post_names[] = $dat->from_url;
                        }elseif($url_pieces_count === 2 || $url_pieces_count === 3){    // if the url is just the slug, but there's a slash or two
                            $post_names[] = $url_pieces[1];
                        }
                    }
                }

                // if we've found the post names
                if(!empty($post_names)){
                    // query the post table with them to get the post ids
                    $post_names = implode('\', \'', $post_names);
                    $ids = $wpdb->get_col("SELECT `ID` FROM {$wpdb->posts} WHERE `post_name` IN ('{$post_names}')");

                    // remove any ids that are supposed to be ignored, but were accidentally included
                    if(!empty($ignore_ids)){
                        $ids = array_diff($ids, $ignore_ids);
                    }

                    // if there's ids
                    if(!empty($ids)){
                        // add them to the list of post ids that are redirected away from
                        $post_ids = array_merge($post_ids, $ids);
                    }
                }
            }
        }

        // if SEOPress is active
        if(defined('SEOPRESS_PRO_VERSION')){
            // try getting redirected posts
            $query = "SELECT p.post_title as 'old_relative' FROM {$wpdb->posts} p 
                        LEFT JOIN {$wpdb->postmeta} m ON p.ID = m.post_id
                        WHERE p.post_type = 'seopress_404' AND m.meta_key = '_seopress_redirections_enabled' AND m.meta_value = 'yes'";

            $results = $wpdb->get_results($query);

            // if there are some posts
            if(!empty($results)){
                $ids = array();
                // go over them and obtain the redirected post's ids
                foreach($results as $dat){
                    $post = Wpil_Post::getPostByLink($dat->old_relative);

                    if(!empty($post)){
                        $ids[] = $post->id;
                    }
                }

                // if there are ids
                if(!empty($ids)){
                    // add them to the list of post ids that are redirected away from
                    $post_ids = array_merge($post_ids, $ids);
                }
            }
        }

        // if there aren't any ids
        if(empty($post_ids)){
            // make a note that there aren't any and return an empty
            set_transient('wpil_redirected_post_ids', 'no-ids', 5 * MINUTE_IN_SECONDS);
        }else{
            // save the fruits of our labours in the cache
            set_transient('wpil_redirected_post_ids', $post_ids, 5 * MINUTE_IN_SECONDS);
        }

        return ($flip && !empty($post_ids)) ? array_flip($post_ids) : $post_ids;
    }

    /**
     * Obtains an array of URLs that have been redirected away from and their destination URLs.
     * The output is an array of new URLs keyed to the old URLs that are being redirected away from.
     * All URLs are trailing slashed for consistency.
     * When comparing URLs in content to the URLs, be sure to slash them.
     *
     * Currently supports Rank Math, Yoast, SEO Press and Redirection (John Godley)
     * At the moment, we're only focusing on the absolute versions of the URLs.
     * Nobody has asked for relative, and there's only been a couple users that have ever mentioned using relative links.
     * Added to this is the fact that the inbound linking functionality only counts absolute URLs makes adding relative moot.
     **/
    public static function getRedirectionUrls(){
        global $wpdb;

        $urls = get_transient('wpil_redirected_post_urls');

        if($urls !== 'no-redirects' && !empty($urls)){
            // refresh the transient
            set_transient('wpil_redirected_post_urls', $urls, 5 * MINUTE_IN_SECONDS);
            // and return the URLs
            return $urls;
        }elseif($urls === 'no-redirects'){
            return array();
        }

        // set up the url array
        $urls = array();

        if(defined('RANK_MATH_VERSION') && !empty($wpdb->query("SHOW TABLES LIKE '{$wpdb->prefix}rank_math_redirections'"))){
            // get the active redirect rules from Rank Math
            $two_years_ago = date('Y-m-d H:i:s', strtotime('-2 years'));
            $active_redirections = $wpdb->get_results("SELECT `id`, `url_to`, `sources` FROM {$wpdb->prefix}rank_math_redirections WHERE `status` = 'active' AND (`last_accessed` > '{$two_years_ago}' OR `updated` > '{$two_years_ago}'  AND `last_accessed` = '0000-00-00 00:00:00')");

            // if there are redirections
            if(!empty($active_redirections)){

                $redirection_ids = array();
                foreach($active_redirections as $dat){
                    if(false !== strpos($dat->sources, 'regex')){
                        continue;
                    }

                    $redirection_ids[$dat->id] = trailingslashit($dat->url_to);
                }

                $id_string = implode(', ', array_keys($redirection_ids));
                $redirection_data = $wpdb->get_results("SELECT `from_url`, `object_id`, `redirection_id` FROM {$wpdb->prefix}rank_math_redirections_cache WHERE `redirection_id` IN ({$id_string})"); // we're getting the redriects from the cache to save processing time. Rules based searching could take a long time

                // go over the data from the Rank Math cache
                foreach($redirection_data as $dat){
                    $url = trailingslashit(self::makeLinkAbsolute($dat->from_url));
                    $redirected_url = trailingslashit(self::makeLinkAbsolute($redirection_ids[$dat->redirection_id]));
                    $urls[$url] = $redirected_url;
                }
            }
        }

        if(defined('WPSEO_VERSION')){
            $active_redirections   = $wpdb->get_results("SELECT option_name, option_value FROM  {$wpdb->options} WHERE option_name = 'wpseo-premium-redirects-export-plain'");
            foreach ( $active_redirections as $redirection ) {
                $dat = maybe_unserialize($redirection->option_value);
                if(!empty($dat)){
                    foreach($dat as $key => $d){
                        $url = trailingslashit(self::makeLinkAbsolute($key));
                        $redirected_url = trailingslashit(self::makeLinkAbsolute($d['url']));
                        $urls[$url] = $redirected_url;
                    }
                }
            }
        }

        /**
         * Search for the redirects from the dedicated redirect pl;ugin last to override the SEO plugins' redirects
         **/
        if(defined('REDIRECTION_VERSION') && !empty($wpdb->query("SHOW TABLES LIKE '{$wpdb->prefix}redirection_items'"))){
            // get the redirect plugin data
            $active_redirections = $wpdb->get_results("SELECT `url`, `action_data` FROM {$wpdb->prefix}redirection_items WHERE `match_type` ='url' AND `match_url` != 'regex'");

            // add the redirections to the url list
            foreach($active_redirections as $dat){
                if(is_string($dat->action_data)){
                    $url = trailingslashit(self::makeLinkAbsolute($dat->url));
                    $action_data = trailingslashit(self::makeLinkAbsolute($dat->action_data));
                    $urls[$url] = $action_data;
                }
            }
        }

        // if SEOPress is active
        if(defined('SEOPRESS_PRO_VERSION')){
            // try getting redirected posts...
            // We're specifically searching for posts that aren't regex-based, are currently active, and result in a 3xx response code so it's not a dead end result
            $query = "SELECT p.post_title AS 'old_relative', m.meta_value as 'new_absolute' FROM {$wpdb->posts} p 
                LEFT JOIN {$wpdb->postmeta} m ON p.ID = m.post_id
                LEFT JOIN {$wpdb->postmeta} ex ON p.ID = ex.post_id AND ex.meta_key = '_seopress_redirections_enabled_regex' 
                LEFT JOIN {$wpdb->postmeta} act ON p.ID = act.post_id AND act.meta_key = '_seopress_redirections_enabled' 
                LEFT JOIN {$wpdb->postmeta} red ON p.ID = red.post_id AND red.meta_key = '_seopress_redirections_type' 
                WHERE p.post_type = 'seopress_404' AND m.meta_key = '_seopress_redirections_value' AND act.meta_value = 'yes' AND ex.meta_key IS NULL AND red.meta_value IN (301,302,307)";
            $results = $wpdb->get_results($query);

            // if there are some posts
            if(!empty($results)){
                // go over them 
                foreach($results as $dat){
                    $url = trailingslashit(self::makeLinkAbsolute($dat->old_relative));
                    $redirected_url = trailingslashit($dat->new_absolute);
                    $urls[$url] = $redirected_url;
                }
            }
        }

        // if Custom Permalinks is active
        if(defined('CUSTOM_PERMALINKS_FILE') && class_exists('Custom_Permalinks_Frontend') && false){

            // TODO: work out why I'm not getting the original post links like I expect to.
            // create a CP url handler class
            $permalink_handler = new Custom_Permalinks_Frontend();

            // search the db for changed urls
			$ids = $wpdb->get_col("SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'custom_permalink'");

            // if there are some posts
            if(!empty($ids)){
                // go over them 
                foreach($ids as $id){
                    $url = trailingslashit($permalink_handler->original_post_link($id));
                    $redirected_url = $permalink_handler->custom_post_link($url, get_post($id));
                    $urls[$url] = $redirected_url;
                }
            }
        }

        // if we've found some redirected urls
        if(!empty($urls)){
            // save the fruits of our labours in the cache
            set_transient('wpil_redirected_post_urls', $urls, 5 * MINUTE_IN_SECONDS);
        }else{
            // otherwise, set a flag so we know there's no urls to keep an eye out for
            set_transient('wpil_redirected_post_urls', 'no-redirects', 5 * MINUTE_IN_SECONDS);
        }

        if('no-redirects' === $urls){
            return array();
        }

        return $urls;
    }

    /**
     * Obtains an array of ids from posts that we know have been hidden by redirects.
     * Our standard for 'hidden' are that the original post is inaccessible by url due to being redirected to a different post.
     * 
     * @param bool $return_hidden_ids Should we just return the ids of posts that have been hidden?
     * @return array
     **/
    public static function getPostsHiddenByRedirects($return_hidden_ids = false){
        $posts = get_transient('wpil_redirected_hidden_posts');

        if(!empty($posts) && $posts !== 'no-redirects'){
            // refresh the transient
            set_transient('wpil_redirected_hidden_posts', $posts, 15 * MINUTE_IN_SECONDS);
            // and return the URLs
            return ($return_hidden_ids)? array_keys($posts): $posts;
        }elseif($posts === 'no-redirects'){
            return array();
        }

        $urls = self::getRedirectionUrls();

        if(empty($urls)){
            set_transient('wpil_redirected_hidden_posts', 'no-redirects', 15 * MINUTE_IN_SECONDS);
            return array();
        }

        $posts = array();
        foreach($urls as $old_url => $new_url){
            $old_post = Wpil_Post::getPostByLink($old_url);

            // if we can't identify the original post
            if(empty($old_post)){
                // skip to the next URL since we can't confirm if the original post is hidden or not
                continue;
            }

            // try getting the new post
            $new_post = Wpil_Post::getPostByLink($new_url);
            // if there's no post that we can find
            if(empty($new_post)){
                // skip to the next one
                continue;
            }

            // if we've made it here, check if the ids are different between the posts
            if($old_post->id !== $new_post->id){
                // if it is different, we know that the post is hidden by a redirect
                $posts[$old_post->id] = $new_post->id;
            }
        }

        // if we've managed to find some hidden posts
        if(!empty($posts)){
            // save the fruits of our labours in the cache
            set_transient('wpil_redirected_hidden_posts', $posts, 15 * MINUTE_IN_SECONDS);
        }else{
            // otherwise, set a flag so we know there's no posts to keep an eye out for
            set_transient('wpil_redirected_hidden_posts', 'no-redirects', 15 * MINUTE_IN_SECONDS);
        }

        return ($return_hidden_ids)? array_keys($posts): $posts;;
    }

    /**
     * Makes the supplied link an absolute one.
     * If the link is already absolute, the link is returned unchanged
     * 
     * @param string $url The relative link to make absolute
     * @return string $url The absolute version of the link
     **/
    public static function makeLinkAbsolute($url){
        $site_url = trailingslashit(get_home_url());
        $site_domain = wp_parse_url($site_url, PHP_URL_HOST);
        $site_scheme = wp_parse_url($site_url, PHP_URL_SCHEME);
        $url_domain = wp_parse_url($url, PHP_URL_HOST);

        // if the link isn't pointing to the current domain, 
        if( strpos($url, $site_domain) === false && 
            empty($url_domain) &&                       // but also isn't pointing to an external one
            strpos($url, 'www.') !== 0)                 // and doesn't start with "www.". (Even though browsers DO consider this to be a relative URL. The user didn't mean for it to be)
        {
            $url = ltrim($url, '/');
            $url_pieces = array_reverse(explode('/', rtrim(trim($site_url), '/')));

            foreach($url_pieces as $piece){
                if(empty($piece) || false === strpos(trim($url), $piece)){
                    $url = $piece . '/' . $url;
                }
            }
        }elseif(strpos($url, 'http') === false){
            $url = rtrim($site_scheme, ':') . '://' . ltrim($url, '/');
        }

        return $url;
    }

    /**
     * Gets the labels for the given post types.
     * Currently, only gets the labels for the public post types because the non-public ones are usually utility post types and the labels are often generic.
     * So if we used their given labels, it may confuse the user.
     *
     * @param string|array $post_types The list of post types that we're getting the labels for. Can also accept a single post type string
     * @return array $labled_types An array of post type labels keyed to their respective post types. Or an empty array if we can't find the post types...
     **/
    public static function getPostTypeLabels($post_types = array()){
        $labled_types = array();

        if(empty($post_types) || (!is_array($post_types) && !is_string($post_types))){
            return $labled_types;
        }

        if(is_string($post_types)){
            $post_types = array($post_types);
        }

        foreach($post_types as $type){
            $type_object = get_post_type_object($type);
            if(!empty($type_object)){
                if(!empty($type_object->public)){
                    $labled_types[$type_object->name] = $type_object->label;
                }else{
                    $labled_types[$type_object->name] = $type_object->name;
                }
            }
        }

        return $labled_types;
    }

    /**
     * Gets an array of WP constants that are active on the site and could have some impact on Link Whisper's functioning.
     **/
    public static function get_wp_constants($constant = ''){
        $constants = array();

        if(defined('WP_MEMORY_LIMIT')){
            $constants['WP_MEMORY_LIMIT'] = WP_MEMORY_LIMIT;
        }

        if(defined('WP_MAX_MEMORY_LIMIT')){
            $constants['WP_MAX_MEMORY_LIMIT'] = WP_MAX_MEMORY_LIMIT;
        }
        
        if(defined('DISABLE_WP_CRON')){
            $constants['DISABLE_WP_CRON'] = DISABLE_WP_CRON;
        }

        if(!empty($constant) && !empty($constants) && isset($constants[$constant])){
            return $constants[$constant];
        }elseif(!empty($constant)){
            return null;
        }

        return $constants;
    }

    /**
     * Recursively applies sanitize_text_field to array values
     **/
    public static function simple_textfield_array_sanitizer($array){

        $cleaned = array();
        foreach($array as $index => $data){
            if(is_array($data)){
                $cleaned[sanitize_text_field($index)] = Wpil_Settings::simple_textfield_array_sanitizer($data);
            }else{
                $cleaned[sanitize_text_field($index)] = sanitize_text_field($data);
            }
        }

        return $cleaned;
    }

    public static function get_if_testing_mode_active(){
        return !empty(get_option('wpil_testing_mode', '0'));
    }

    public static function get_ai_inbound_suggestion_ids(){
        $ids = get_transient('wpil_ai_suggestion_post_process_cron_ids');
        if(empty($ids) && $ids === false){
            $ids = array();
            $post_ids = Wpil_Report::get_all_post_ids();
            if(!empty($post_ids)){
                rsort($post_ids);
                foreach($post_ids as $id){
                    $pid = 'post_' . $id;
                    $ids[$pid] = true;
                }
            }
            $term_ids = Wpil_Report::get_all_term_ids();
            if(!empty($term_ids)){
                rsort($term_ids);
                foreach($term_ids as $id){
                    $pid = 'term_' . $id;
                    $ids[$pid] = true;
                }
            }

            // if we have posts
            if(!empty($ids)){
                // remove any ignored posts
                $ignored_posts = self::getAllIgnoredPosts();

                if(!empty($ignored_posts)){
                    foreach($ignored_posts as $post_id){
                        if(isset($ids[$post_id])){
                            unset($ids[$post_id]);
                        }
                    }
                }

                // posts hidden by redirects
                $hidden = self::getPostsHiddenByRedirects(true);

                if(!empty($hidden)){
                    foreach($hidden as $id){
                        $post_id = 'post_' . $id;
                        if(isset($ids[$post_id])){
                            unset($ids[$post_id]);
                        }
                    }
                }

                // focus on any posts that we are supposed to process
                // TODO: later
                // todo: remove any ids that are already processed
            }

            set_transient('wpil_ai_suggestion_post_process_cron_ids', $ids, 60 * MINUTE_IN_SECONDS);
        }

        if(empty($ids) && is_array($ids)){
            delete_transient('wpil_ai_suggestion_post_process_cron_ids');
        }

        return (!empty($ids)) ? array_keys($ids): array();
    }

    public static function update_ai_inbound_suggestion_ids($id = 0){
        if(empty($id)){
            return false;
        }

        $ids = get_transient('wpil_ai_suggestion_post_process_cron_ids');
        if(!empty($ids) && isset($ids[$id])){
            unset($ids[$id]);
            set_transient('wpil_ai_suggestion_post_process_cron_ids', $ids, 60 * MINUTE_IN_SECONDS);
        }
    }

    public static function disable_ai_suggestions_cron_task(){
        // are AI powered suggestions enabled and has the user disabled the AI suggestions cron
        return !empty(get_option('wpil_disable_ai_suggestions_cron', '0')) || !self::get_use_ai_suggestions();
    }

    public static function get_ai_max_processing_age(){
        return get_option('wpil_ai_max_processing_age', 0);
    }

    public static function has_run_wizard(){
        return !empty(get_option('wpil_has_run_installation_wizard', 0));
    }

    public static function set_run_wizard(){
        update_option('wpil_has_run_installation_wizard', 1);
    }

    /**
     * Checks to see if the email cron task is 
     **/
    public static function email_notifications_are_enabled(){
        return !empty(get_option('wpil_email_notifications_enabled', 1));
    }

    public static function get_if_telemetry_active(){
        return false;
        return !empty(get_option('wpil_enable_telemetry', '1'));
    }

    public static function get_if_remote_dashboard_active(){
        return false;
        return !empty(get_option('wpil_remote_dashboard', '1'));
    }
    public static function get_show_expanded_suggestion_details(){
        return !empty(get_option('wpil_show_expanded_suggestion_details', '0'));
    }

    public static function get_service_pages_to_ignore(){
        global $wpdb;

        $ignore_page_names = array(
            'cart',
            'checkout',
            'account',
            'sitemap',
            'changelog',
            'profile',
            'about-us',
            'terms-of-service',
            'privacy'
        );

        $ignore_page_query = "AND (`post_name` LIKE '%" . implode("%' OR `post_name` LIKE '%", $ignore_page_names) . "%')";
        $pages = $wpdb->get_col("SELECT `ID` FROM {$wpdb->posts} WHERE `post_type` != 'post' {$ignore_page_query}");

        // try getting any edd settings
        $options = get_option('edd_settings');
        // if we have some
        if(!empty($options)){
            // make sure that we can add them to the pages variable
            if(empty($pages) || !is_array($pages)){
                $pages = array();
            }

            // go over each option
            foreach($options as $key => $value){
                // if there are any relating to pages and there's an id stored
                if(false !== strpos($key, 'page') && is_numeric($value)){
                    // add it to the page list
                    $pages[] = $value;
                }
            }

            // if there are page ids
            if(!empty($pages)){
                // make sure we don't have duplicates
                $pages = array_unique($pages);
            }
        }





        return (!empty($pages)) ? $pages: [];
        // TODO: get more pages from ecommerce and profile management plugins and include them in the list
    }

    public static function get_money_pages(){
        // we're going to want menu pages
        // pages mentioned by seo po
    }
}
