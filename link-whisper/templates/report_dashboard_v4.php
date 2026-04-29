<?php
/**
 * Dashboard V4
 */
?>
<div class="wrap wpil-report-page wpil_styles">
<?php
    $user = wp_get_current_user();
    $site_name = get_bloginfo('name');

    $codes = Wpil_Dashboard::getAllErrorCodes();
    $codes = (!empty($codes)) ? '&codes=' . implode(',', $codes) : '';

    $posts_crawled      = (int) Wpil_Dashboard::getPostCount();
    $orphanedCount      = (int) Wpil_Dashboard::getOrphanedPostsCount();
    $brokenLinksCount   = (int) Wpil_Dashboard::getBrokenLinksCount([6,7,28,404,451,500,503,925]);
    $internal_links     = (int) Wpil_Dashboard::getInternalLinksCount();
    $external_links     = (int) Wpil_Dashboard::getExternalLinksCount();
    $links_inserted_30      = (int) Wpil_Dashboard::get_tracked_link_insert_count();
    $links_inserted_prev_30 = (int) Wpil_Dashboard::get_tracked_link_insert_previous_count();
    $links_inserted_total   = (int) Wpil_Dashboard::get_tracked_link_insert_total_count();
    $link_relatedness   = (float) Wpil_Dashboard::get_related_link_percentage();
    $link_quality_score = round($link_relatedness / 10, 1);

    $link_density = Wpil_Dashboard::get_percent_of_posts_hitting_link_targets();
    $link_coverage_percent = !empty($link_density['percent']) ? (float) $link_density['percent'] : 0.0;
    $coverage_target_items = !empty($link_density['total_items']) ? (int) $link_density['total_items'] : 0;
    $coverage_qualified_items = !empty($link_density['qualified_items']) ? (int) $link_density['qualified_items'] : 0;
    $crawl_target_total = max($coverage_target_items, $posts_crawled);
    $crawl_progress_percent = $crawl_target_total > 0 ? round(($posts_crawled / $crawl_target_total) * 100, 2) : 0.0;
    $crawl_progress_percent = max(0, min(100, $crawl_progress_percent));

    $summary   = Wpil_Dashboard::get_click_traffic_stats();
    $clicks_30 = isset($summary['clicks_30']) ? (int) $summary['clicks_30'] : 0;
    $clicks_old = isset($summary['clicks_old']) ? (int) $summary['clicks_old'] : 0;
    $difference = $clicks_30 - $clicks_old;
    $percent_change = ($clicks_old != 0) ? round(($difference / $clicks_old) * 100, 2) : ($clicks_30 > 0 ? 100 : 0);
    $is_positive = ($difference >= 0);
    $percent_change_abs = abs((float) $percent_change);
    $percent_change_display = rtrim(rtrim(number_format($percent_change_abs, 2, '.', ''), '0'), '.');

    $total_links = max(0, $internal_links + $external_links);
    $internal_percent = ($total_links > 0) ? round(($internal_links / $total_links) * 100) : 0;
    $external_percent = ($total_links > 0) ? round(($external_links / $total_links) * 100) : 0;

    $external_link_emphasis = Wpil_Dashboard::get_external_link_distribution(1);
    $external_link_emphasis_percent = 0.0;
    if(!empty($external_link_emphasis) && isset($external_link_emphasis[0]->representation)){
        $external_link_emphasis_percent = (float) (round($external_link_emphasis[0]->representation, 2) * 100);
    }

    $health_metrics = [
        'posts_crawled'            => $posts_crawled,
        'broken_links'             => $brokenLinksCount,
        'orphaned_posts'           => $orphanedCount,
        'link_coverage_percent'    => $link_coverage_percent,
        'link_relatedness_percent' => $link_relatedness,
        'external_site_focus'      => $external_link_emphasis_percent,
    ];
    $health = Wpil_Dashboard::wpil_dash_site_health_score($health_metrics);
    $health_meta = Wpil_Dashboard::wpil_dash_site_health_meta($health['score']);

    $urls = [
        'links'        => admin_url('admin.php?page=link_whisper&type=links'),
        'domains'      => admin_url('admin.php?page=link_whisper&type=domains'),
        'clicks'       => admin_url('admin.php?page=link_whisper&type=clicks'),
        'broken'       => admin_url('admin.php?page=link_whisper&type=error'),
        'sitemaps'     => admin_url('admin.php?page=link_whisper&type=sitemaps'),
        'orphaned'     => admin_url('admin.php?page=link_whisper&type=links&orphaned=1'),
        'link_quality' => admin_url('admin.php?page=link_whisper&type=links&link_relation=1'),
        'coverage'     => admin_url('admin.php?page=link_whisper&type=links&link_density=1'),
        'one_click'    => admin_url('admin.php?page=link_whisper_wizard'),
        'autolinking'  => admin_url('admin.php?page=link_whisper&type=autolinks'),
        'settings'     => admin_url('admin.php?page=link_whisper_settings'),
    ];

    $hour = (int) wp_date('G', current_time('timestamp'));
    if($hour < 12){
        $day_part = 'morning';
    }elseif($hour < 18){
        $day_part = 'afternoon';
    }else{
        $day_part = 'evening';
    }
    $display_name = !empty($user->display_name) ? $user->display_name : '';
    $name_parts = !empty($display_name) ? preg_split('/\s+/', trim($display_name)) : [];
    $first_name = !empty($name_parts[0]) ? $name_parts[0] : '';

    $admin_urls = [
        'broken_links'       => htmlspecialchars(admin_url('admin.php?page=link_whisper&type=error' . $codes)),
        'orphaned_posts'     => admin_url('admin.php?page=link_whisper&type=links&orphaned=1'),
        'link_density'       => admin_url('admin.php?page=link_whisper&type=links&link_density=1'),
        'link_relation'      => admin_url('admin.php?page=link_whisper&type=links&link_relation=1'),
        'domains_report'     => admin_url('admin.php?page=link_whisper&type=domains'),
        'anchor_suggestions' => '',
    ];
    $action_metrics = [
        'broken_links'            => $brokenLinksCount,
        'orphaned_posts'          => $orphanedCount,
        'anchor_length_percent'   => null,
        'link_coverage_percent'   => $link_coverage_percent,
        'link_relatedness_percent'=> $link_relatedness,
        'external_site_focus'     => $external_percent,
        'admin_urls'              => $admin_urls,
    ];
    $recommended_actions = Wpil_Dashboard::wpil_dash_generate_recommended_actions($action_metrics);
    $running_fix_jobs = [];
    $running_fix_types = [];
    $registry = get_option('wpil_ai_fix_registry', []);
    if(is_array($registry)){
        foreach($registry as $entry){
            if(empty($entry) || !is_array($entry) || empty($entry['fix_type'])){
                continue;
            }
            if(!isset($entry['status']) || 'running' !== $entry['status']){
                continue;
            }
            $type = (string) $entry['fix_type'];
            $item = isset($entry['item_id']) ? (string) $entry['item_id'] : '';
            $job_key = $type . ':' . $item;
            $running_fix_jobs[$job_key] = [
                'type' => $type,
                'itemId' => $item,
                'progress' => isset($entry['progress']) ? (int) $entry['progress'] : 0,
                'message' => isset($entry['message']) ? (string) $entry['message'] : '',
                'estimate' => isset($entry['estimate']) ? (int) $entry['estimate'] : 0,
                'processKey' => isset($entry['process_key']) ? (string) $entry['process_key'] : '',
            ];
            $running_fix_types[$type] = true;
        }
    }
    $quick_wins = [];
    $task_cards = [];
    if(!empty($recommended_actions)){
        foreach($recommended_actions as $item){
            if(count($quick_wins) >= 2){
                // keep collecting task cards even after quick wins
            }elseif(!empty($item['title'])){
                $quick_wins[] = wp_strip_all_tags($item['title']);
            }

            if(count($task_cards) < 3 && !empty($item['title'])){
                $fix = !empty($item['fix']) && is_array($item['fix']) ? $item['fix'] : [];
                $fix_attrs = !empty($fix['attrs']) && is_array($fix['attrs']) ? $fix['attrs'] : [];
                $fix_estimate = !empty($fix_attrs['data-wpil-fix-estimate']) ? (int) $fix_attrs['data-wpil-fix-estimate'] : 0;
                $fix_description = !empty($fix_attrs['data-wpil-fix-description']) ? (string) $fix_attrs['data-wpil-fix-description'] : '';
                $fix_type = !empty($fix['type']) ? (string) $fix['type'] : '';
                $task_explanation = !empty($item['description']) ? wp_strip_all_tags($item['description']) : '';
                switch($fix_type){
                    case 'orphaned_posts':
                        $task_explanation = 'Brings your hidden pages back to life by adding internal links that help visitors and search engines find them.';
                        break;
                    case 'link_coverage':
                        $task_explanation = 'Fills in missing internal links across your site so your content works together and performs better in search.';
                        break;
                    case 'link_quality':
                        $task_explanation = 'Upgrades weak internal links to more relevant ones, improving user experience and strengthening your SEO signals.';
                        break;
                    case 'broken_links':
                        $task_explanation = 'Repairs broken links automatically so visitors stay engaged and your site avoids SEO penalties.';
                        break;
                    case 'external_focus':
                        $task_explanation = 'Keeps more link value on your own site by strengthening internal connections to your important pages.';
                        break;
                }

                $task_cards[] = [
                    'id' => !empty($item['id']) ? (string) $item['id'] : '',
                    'title' => wp_strip_all_tags($item['title']),
                    'url' => !empty($item['review']['url']) ? $item['review']['url'] : (!empty($item['url']) ? $item['url'] : $urls['links']),
                    'impact' => !empty($item['severity']) && 'high' === strtolower((string)$item['severity']) ? 'Impact: High' : 'Impact: Medium',
                    'time' => !empty($item['time_estimate']) ? (string)$item['time_estimate'] : 'Est. time: 2-5 mins',
                    'button' => 'Review',
                    'fix_enabled' => !empty($fix['enabled']) && !empty($fix['type']),
                    'fix_type' => $fix_type,
                    'fix_estimate' => $fix_estimate,
                    'fix_description' => $fix_description,
                    'explanation' => $task_explanation,
                    'is_running' => isset($running_fix_jobs[$fix_type . ':' . (!empty($item['id']) ? (string) $item['id'] : '')]),
                ];
            }
        }
    }

    $notification_data = ['items' => [], 'unread_count' => 0, 'notification_count' => 0];
    if(class_exists('Wpil_Notification') && method_exists('Wpil_Notification', 'get_dashboard_dropdown_notifications')){
        $notification_data = Wpil_Notification::get_dashboard_dropdown_notifications(3);
    }
    $notification_items = isset($notification_data['items']) && is_array($notification_data['items']) ? $notification_data['items'] : [];
    $notification_unread_count = isset($notification_data['unread_count']) ? (int) $notification_data['unread_count'] : 0;
    $notification_total_count = isset($notification_data['notification_count']) ? (int) $notification_data['notification_count'] : count($notification_items);
    $notification_seen_nonce = wp_create_nonce('wpil_dashboard_notifications_seen');
    $waitlist_signup_nonce = wp_create_nonce(get_current_user_id() . 'wpil-link-delay-waitlist-signup');
    $waitlist_signed_up = true; //!empty(get_user_meta(get_current_user_id(), 'wpil_link_delay_waitlist_signup', true));
    $experience_feedback_nonce = wp_create_nonce(get_current_user_id() . 'wpil-dashboard-experience-feedback');
    $experience_feedback_events = class_exists('Wpil_Telemetry') ? Wpil_Telemetry::get_user_event_data() : array();
    $experience_feedback_given = !empty($experience_feedback_events['dashboard_new_experience_feedback']['count']);

    $activity_items = [];
    // TODO: fill out with more useful activity items
/*    if($clicks_30 > 0){
        $activity_items[] = ['text' => sprintf('%s clicks tracked in the last 30 days', number_format_i18n($clicks_30)), 'time' => 'Current window', 'icon' => 'chart-line'];
    }
    if($links_inserted_30 > 0){
        $activity_items[] = ['text' => sprintf('%s links created', number_format_i18n($links_inserted_30)), 'time' => 'Last 30 days', 'icon' => 'admin-links'];
    }
    if($posts_crawled > 0){
        $activity_items[] = ['text' => sprintf('%s posts crawled', number_format_i18n($posts_crawled)), 'time' => 'Current index', 'icon' => 'search'];
    }
    if($brokenLinksCount > 0){
        $activity_items[] = ['text' => sprintf('%s broken links detected', number_format_i18n($brokenLinksCount)), 'time' => 'Needs review', 'icon' => 'warning'];
    }
    $activity_items = array_slice($activity_items, 0, 3);
*/
    $has_quick_wins = !empty($task_cards);
    $has_recent_activity = !empty($activity_items);
    $show_tasks_activity_section = ($has_quick_wins || $has_recent_activity);
    $tasks_activity_layout_class = (!$has_quick_wins || !$has_recent_activity) ? 'single-column': '';

    $ai_configured = class_exists('Wpil_Settings') && method_exists('Wpil_Settings', 'has_ai_enabled') ? Wpil_Settings::has_ai_enabled() : false;
    $ai_suggestions_enabled = class_exists('Wpil_Settings') && method_exists('Wpil_Settings', 'get_use_ai_suggestions') ? Wpil_Settings::get_use_ai_suggestions() : false;
    $has_autolink_rules = false;
    $click_tracking_disabled = !empty(get_option('wpil_disable_click_tracking', false));
    $click_tracking_underused = $click_tracking_disabled || (int)$clicks_30 <= 0;
    $has_target_keywords = class_exists('Wpil_TargetKeyword') && method_exists('Wpil_TargetKeyword', 'has_keywords_stored') ? Wpil_TargetKeyword::has_keywords_stored() : false;
    $has_url_changer_rules = false; //class_exists('Wpil_URLChanger') && method_exists('Wpil_URLChanger', 'hasURLs') ? Wpil_URLChanger::hasURLs() : false;
    $has_custom_link_attrs = class_exists('Wpil_Settings') && method_exists('Wpil_Settings', 'get_active_link_attributes') ? !empty(Wpil_Settings::get_active_link_attributes()) : false;
    $has_custom_sitemap = class_exists('Wpil_Sitemap') && method_exists('Wpil_Sitemap', 'has_sitemap') ? !empty(Wpil_Sitemap::has_sitemap('custom_sitemap')) : false;
    $has_run_wizard = class_exists('Wpil_Settings') && method_exists('Wpil_Settings', 'has_run_wizard') ? Wpil_Settings::has_run_wizard() : !empty(get_option('wpil_has_run_installation_wizard', 0));

    $telemetry_counts = [];
    global $wpdb;
    $telemetry_table = $wpdb->prefix . 'wpil_telemetry_log';
    $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $telemetry_table));
    if($table_exists === $telemetry_table){
        $telemetry_rows = $wpdb->get_results($wpdb->prepare("SELECT event_name, COUNT(*) AS cnt FROM {$telemetry_table} WHERE user_id = %d GROUP BY event_name", get_current_user_id()), ARRAY_A);
        if(!empty($telemetry_rows)){
            foreach($telemetry_rows as $row){
                if(!empty($row['event_name'])){
                    $telemetry_counts[$row['event_name']] = (int) $row['cnt'];
                }
            }
        }
    }
    $telemetry_hit = function($events = []) use ($telemetry_counts){
        if(empty($events)){ return false; }
        foreach($events as $event){
            if(!empty($telemetry_counts[$event])){
                return true;
            }
        }
        return false;
    };

    $show_ai_fix_controls = false;

    $major_features = [
        [
            'name' => 'AI-Powered Suggestions',
            'hint' => !$ai_configured ? 'Connect AI to unlock relevance-driven suggestions.' : 'Turn on AI suggestions to improve suggestion quality.',
            'cta'  => !$ai_configured ? 'Connect' : 'Enable',
            'url'  => !$ai_configured ? admin_url('admin.php?page=link_whisper_ai_subscription') : $urls['settings'],
            'used' => (($ai_configured && $ai_suggestions_enabled) || $telemetry_hit(['activated_ai_powered_suggestions', 'linkwhisper_ai_authenticated', 'linkwhisper_ai_link_inserted'])),
        ],
        /*[
            'name' => 'Auto-Linking Rules',
            'hint' => 'Create rules to automate repetitive internal links at scale.',
            'cta'  => 'Create',
            'url'  => $urls['autolinking'],
            'used' => ($has_autolink_rules || $telemetry_hit(['report_open_autolinking', 'creating_autolinking_rule', 'bulk_autolinking_rule_created'])),
        ],*/
        [
            'name' => 'Click Tracking',
            'hint' => $click_tracking_disabled ? 'Enable click tracking to measure internal link engagement.' : 'Open click reports to identify high-performing link paths.',
            'cta'  => $click_tracking_disabled ? 'Enable' : 'Review',
            'url'  => $urls['clicks'],
            'used' => (!$click_tracking_underused || $telemetry_hit(['report_open_clicks', 'report_open_detailed_clicks'])),
        ],
        [
            'name' => 'Target Keywords',
            'hint' => 'Use target keywords to improve matching and suggestion relevance.',
            'cta'  => 'Manage',
            'url'  => admin_url('admin.php?page=link_whisper_target_keywords'),
            'used' => ($has_target_keywords || $telemetry_hit(['report_open_target_keywords', 'update_selected_target_keywords'])),
        ],
        /*[
            'name' => 'URL Changer',
            'hint' => 'Maintain redirect hygiene and update old URLs in bulk.',
            'cta'  => 'Open',
            'url'  => admin_url('admin.php?page=link_whisper_url_changer'),
            'used' => ($has_url_changer_rules || $telemetry_hit(['report_open_url_changer', 'url_changer_rule_create', 'url_changer_rule_deleted', 'url_changer_reset'])),
        ],*/
        [
            'name' => 'Broken Link Management',
            'hint' => 'Review and repair broken links before they impact UX and crawl quality.',
            'cta'  => 'Review',
            'url'  => $urls['broken'],
            'used' => ($telemetry_hit(['report_open_broken_links', 'broken_link_url_updated', 'link_updated_from_report', 'link_updated_from_domains_report', 'link_updated_from_links_report'])),
        ],
        /*[
            'name' => 'Custom Link Attributes',
            'hint' => 'Set custom attributes to control external link behavior and policy.',
            'cta'  => 'Configure',
            'url'  => $urls['domains'],
            'used' => ($has_custom_link_attrs || $telemetry_hit(['domains_changed_attrs'])),
        ],*/
        [
            'name' => 'Visual Sitemaps',
            'hint' => 'Generate and review custom sitemaps for deeper internal planning.',
            'cta'  => 'Open',
            'url'  => admin_url('admin.php?page=link_whisper&type=sitemaps'),
            'used' => ($has_custom_sitemap || $telemetry_hit(['report_open_sitemaps', 'custom_sitemap_created', 'sitemaps_generated'])),
        ],
    ];

    $feature_cards = [];
    foreach($major_features as $feature){
        if(empty($feature['used'])){
            $feature_cards[] = [
                'name' => $feature['name'],
                'hint' => $feature['hint'],
                'cta'  => $feature['cta'],
                'url'  => $feature['url'],
            ];
        }
    }

    $feature_section_title = "Features You're Not Using";
    $feature_empty_message = '';
    if(empty($feature_cards)){
        $feature_empty_message = "Good Job! You're fully utilizing Link Whisper!";
    }

    $quick_action_items = [
        ['label' => 'Fix Orphaned Posts', 'url' => $urls['orphaned'], 'icon' => 'editor-unlink'],
        //['label' => 'Add Auto-Linking Rule', 'url' => $urls['autolinking'], 'icon' => 'flag'],
        ['label' => 'Get Linking Suggestions', 'url' => $urls['links'], 'icon' => 'admin-generic'],
        //['label' => 'URL Changer Tool', 'url' => $urls['settings'], 'icon' => 'randomize'],
    ];
    $greeting_subtitle = 'Here is your latest internal linking snapshot.';

    $status_link_quality = ($link_quality_score >= 7) ? ['Good', 'status-good', 'var(--green-600)'] : (($link_quality_score >= 5) ? ['Needs Work', 'status-warning', 'var(--amber-600)'] : ['Critical', 'status-poor', 'var(--red-600)']);
    $status_health = ((int)$health['score'] >= 70) ? ['Good', 'status-good', 'var(--green-600)'] : (((int)$health['score'] >= 50) ? ['Needs Work', 'status-warning', 'var(--amber-600)'] : ['Poor', 'status-poor', 'var(--red-600)']);
    $status_coverage = ($link_coverage_percent >= 80) ? ['Good', 'status-good', 'var(--green-600)'] : (($link_coverage_percent >= 60) ? ['Needs Work', 'status-warning', 'var(--amber-600)'] : ['Poor', 'status-poor', 'var(--red-600)']);
    $status_orphans = ($orphanedCount <= 0) ? ['Good', 'status-good', 'var(--green-600)'] : (($orphanedCount <= 25) ? ['Needs Work', 'status-warning', 'var(--amber-600)'] : ['Critical', 'status-poor', 'var(--red-600)']);
    $status_broken = ($brokenLinksCount <= 0) ? ['Good', 'status-good', 'var(--green-600)'] : (($brokenLinksCount <= 20) ? ['Needs Work', 'status-warning', 'var(--amber-600)'] : ['Critical', 'status-poor', 'var(--red-600)']);

    $time_saved_summary = array(
        'seconds' => ($links_inserted_30 * 180),
        'hours' => round(($links_inserted_30 * 180) / HOUR_IN_SECONDS, 1),
        'money' => round(round(($links_inserted_30 * 180) / HOUR_IN_SECONDS, 1) * 30, 0),
    );
    if(class_exists('Wpil_Telemetry') && method_exists('Wpil_Telemetry', 'get_time_saved_summary')){
        $time_saved_summary = Wpil_Telemetry::get_time_saved_summary(30, 30);
    }
    $hours_saved = !empty($time_saved_summary['hours']) ? (float) $time_saved_summary['hours'] : 0.0;
    $money_saved = !empty($time_saved_summary['money']) ? (float) $time_saved_summary['money'] : 0.0;

    if($internal_percent >= 60 && $internal_percent <= 70){
        $distribution_hint = 'Good balance. Maintain 60-70% internal links for strong site flow.';
    }elseif($internal_percent < 60){
        $distribution_hint = 'Increase internal links to strengthen topical connection and crawl depth.';
    }else{
        $distribution_hint = 'External ratio is low. Consider citing more relevant high-authority sources.';
    }

    $crawl_trend_class = ($crawl_progress_percent >= 80) ? 'trend-up' : 'trend-down';
    $crawl_trend_text = ($crawl_progress_percent >= 80) ? 'On target' : 'Below target';
    $click_trend_class = $is_positive ? 'trend-up' : 'trend-down';
    $click_trend_symbol = $is_positive ? '+' : '-';
    $links_created_difference = $links_inserted_30 - $links_inserted_prev_30;
    $links_created_is_positive = ($links_created_difference >= 0);
    $links_created_trend_symbol = $links_created_is_positive ? '+' : '-';
    $links_created_trend_class = $links_created_is_positive ? 'trend-up' : 'trend-down';
    $links_created_percent_change = ($links_inserted_prev_30 > 0) ? round(($links_created_difference / $links_inserted_prev_30) * 100, 2) : ($links_inserted_30 > 0 ? 100 : 0);
    $links_created_percent_display = rtrim(rtrim(number_format(abs((float) $links_created_percent_change), 2, '.', ''), '0'), '.');
    if('' === $links_created_percent_display){
        $links_created_percent_display = '0';
    }
    $links_created_trend_text = $links_created_trend_symbol . $links_created_percent_display . '% vs previous 30 days';

    $site_health_hint = !empty($health_meta['hint']) ? (string) $health_meta['hint'] : Wpil_Dashboard::wpil_dash_site_health_hint($health);
    $link_quality_description = ($link_quality_score >= 7) ? 'Strong topical relevance across internal links.' : (($link_quality_score >= 5) ? 'Improve relevance by replacing weaker related links.' : 'Many links have low relevance and need replacement.');
    $coverage_description = ($link_coverage_percent >= 80) ? 'Most content is meeting internal link targets.' : (($link_coverage_percent >= 60) ? 'Coverage is improving but key pages still need links.' : 'Coverage is low and many items are underlinked.');
    $orphan_description = ($orphanedCount <= 0) ? 'No orphaned items detected.' : sprintf('%s items currently have no inbound internal links.', number_format_i18n($orphanedCount));
    $broken_description = ($brokenLinksCount <= 0) ? 'No broken links detected.' : sprintf('%s broken links need updates or removals.', number_format_i18n($brokenLinksCount));

    $quality_rating = !empty($health_meta['label']) ? (string) $health_meta['label'] : 'Good';
    $quality_breakdown_items = [];
    $quality_improvements = [];
    $quality_total_possible_gain = 0.0;

    $quality_components = [
        'broken_links' => [
            'weight' => 0.30,
            'score' => isset($health['broken_score']) ? (float) $health['broken_score'] : 0.0,
            'good' => ($brokenLinksCount <= 0),
            'good_text' => 'No broken links detected.',
            'bad_text' => sprintf('%s broken links are reducing site health.', number_format_i18n($brokenLinksCount)),
        ],
        'orphaned_posts' => [
            'weight' => 0.20,
            'score' => isset($health['orphan_score']) ? (float) $health['orphan_score'] : 0.0,
            'good' => ($orphanedCount <= 0),
            'good_text' => 'No orphaned posts detected.',
            'bad_text' => sprintf('%s orphaned items need inbound links.', number_format_i18n($orphanedCount)),
        ],
        'link_coverage' => [
            'weight' => 0.20,
            'score' => isset($health['coverage_score']) ? (float) $health['coverage_score'] : $link_coverage_percent,
            'good' => ($link_coverage_percent >= 80),
            'good_text' => sprintf('Link coverage is strong at %s%%.', number_format_i18n($link_coverage_percent, 1)),
            'bad_text' => sprintf('Coverage is %s%%, below the 80%% target.', number_format_i18n($link_coverage_percent, 1)),
        ],
        'link_quality' => [
            'weight' => 0.20,
            'score' => isset($health['related_score']) ? (float) $health['related_score'] : $link_relatedness,
            'good' => ($link_relatedness >= 80),
            'good_text' => sprintf('Topical link quality is solid at %s%%.', number_format_i18n($link_relatedness, 1)),
            'bad_text' => sprintf('Topical link quality is %s%%, below the 80%% target.', number_format_i18n($link_relatedness, 1)),
        ],
        'external_focus' => [
            'weight' => 0.10,
            'score' => isset($health['external_score']) ? (float) $health['external_score'] : ($external_percent <= 60 ? 100 : 0),
            'good' => ($external_percent <= 60),
            'good_text' => sprintf('External focus is balanced at %s%%.', number_format_i18n($external_percent, 0)),
            'bad_text' => sprintf('External focus is %s%%; target is 60%% or less.', number_format_i18n($external_percent, 0)),
        ],
    ];

    foreach($quality_components as $component_key => $component){
        $is_good = !empty($component['good']);
        $quality_breakdown_items[] = [
            'good' => $is_good,
            'text' => $is_good ? $component['good_text'] : $component['bad_text'],
        ];

        $score = isset($component['score']) ? (float) $component['score'] : 0.0;
        $weight = isset($component['weight']) ? (float) $component['weight'] : 0.0;
        $target_score = $score;
        $action_text = '';

        switch($component_key){
            case 'broken_links':
                if($brokenLinksCount > 0){
                    $target_score = 100.0;
                    $action_text = sprintf('Fix %s broken links', number_format_i18n($brokenLinksCount));
                }
                break;
            case 'orphaned_posts':
                if($orphanedCount > 0){
                    $target_score = 100.0;
                    $action_text = sprintf('Add inbound links to %s orphaned items', number_format_i18n($orphanedCount));
                }
                break;
            case 'link_coverage':
                if($link_coverage_percent < 80){
                    $target_score = 80.0;
                    $action_text = sprintf('Raise coverage from %s%% to 80%%', number_format_i18n($link_coverage_percent, 1));
                }
                break;
            case 'link_quality':
                if($link_relatedness < 80){
                    $target_score = 80.0;
                    $action_text = sprintf('Improve link quality from %s%% to 80%%', number_format_i18n($link_relatedness, 1));
                }
                break;
            case 'external_focus':
                if($external_percent > 60){
                    $target_score = 100.0;
                    $action_text = sprintf('Reduce external focus from %s%% to 60%%', number_format_i18n($external_percent, 0));
                }
                break;
        }

        $gain = max(0, ($target_score - $score) * $weight);
        if($gain > 0 && !empty($action_text)){
            $quality_total_possible_gain += $gain;
            $quality_improvements[] = [
                'text' => $action_text,
                'gain' => $gain,
            ];
        }
    }

    usort($quality_improvements, function($a, $b){
        $a_gain = isset($a['gain']) ? (float) $a['gain'] : 0.0;
        $b_gain = isset($b['gain']) ? (float) $b['gain'] : 0.0;
        if($a_gain === $b_gain){
            return 0;
        }
        return ($a_gain > $b_gain) ? -1 : 1;
    });
    $quality_improvements = array_slice($quality_improvements, 0, 3);

    $membership_days_left = 0;
    $membership_is_lifetime = false;
    /*if(class_exists('Wpil_License') && method_exists('Wpil_License', 'get_subscription_days_left')){
        $membership_days_left = (int) Wpil_License::get_subscription_days_left();
    }
    if(class_exists('Wpil_License') && method_exists('Wpil_License', 'is_lifetime_membership')){
        $membership_is_lifetime = (bool) Wpil_License::is_lifetime_membership();
    }*/
    $credits_available = 0;
    if(class_exists('Wpil_AI') && method_exists('Wpil_AI', 'get_available_ai_credits')){
        $credits_available = (int) Wpil_AI::get_available_ai_credits();
    }

    $ai_usage_defaults = Wpil_Export::get_ai_credit_history_filters();
    $ai_usage_nonce = wp_create_nonce(get_current_user_id() . 'wpil_ai_credit_history_panel');
?>
<style>
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

:root {
  --blue-600: #2563eb;
  --blue-500: #3b82f6;
  --blue-400: #60a5fa;
  --blue-50: #eff6ff;
  --green-600: #059669;
  --green-500: #10b981;
  --green-50: #f0fdf4;
  --red-600: #dc2626;
  --red-500: #ef4444;
  --amber-600: #d97706;
  --gray-900: #111827;
  --gray-800: #1f2937;
  --gray-700: #374151;
  --gray-600: #4b5563;
  --gray-500: #6b7280;
  --gray-400: #9ca3af;
  --gray-300: #d1d5db;
  --gray-200: #e5e7eb;
  --gray-100: #f3f4f6;
  --gray-50: #f9fafb;
  --white: #ffffff;
}

.wpil-dashboard-v3 {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
  background: var(--gray-50);
  color: var(--gray-900);
  line-height: 1.5;
  -webkit-font-smoothing: antialiased;
}

.container {
  max-width: 1400px;
  margin: 0 auto;
  padding: 24px;
}

/* Header with Notification */
.header {
  background: var(--white);
  padding: 16px 24px;
  margin-bottom: 16px;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.header h1 {
  font-size: 18px;
  font-weight: 600;
  color: var(--gray-900);
}

.site-name {
  font-size: 14px;
  color: var(--gray-500);
  font-weight: 400;
  margin-left: 8px;
}

/* hide elementor notices from the dashboard */
.header .updated{
    display:none; 
}

.header-right {
  display: flex;
  align-items: center;
  gap: 16px;
}

.header-badges {
  display: flex;
  align-items: center;
  gap: 12px;
}

.badge-item {
  display: flex;
  flex-direction: column;
}

.membership-badge {
  align-items: flex-end;
}

.badge-label {
  font-size: 10px;
  font-weight: 600;
  color: var(--gray-500);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.badge-value {
  font-size: 13px;
  font-weight: 600;
  color: var(--gray-900);
  margin-top: 2px;
}

.badge-separator {
  width: 1px;
  height: 32px;
  background: var(--gray-300);
}

.credits-badge {
  background: var(--gray-100);
  color: var(--gray-700);
  padding: 8px 14px;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 600;
}

.notification-bell {
  position: relative;
  background: var(--gray-100) !important;
  color: var(--gray-700) !important;
  border: 1px solid var(--gray-300) !important;
  width: 36px;
  height: 36px;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  font-size: 16px;
  transition: all 0.2s;
}

.notification-bell:hover {
  background: var(--gray-200) !important;
  color: var(--gray-800) !important;
}

.notification-badge {
  position: absolute;
  top: -4px;
  right: -4px;
  background: var(--red-500);
  color: white;
  font-size: 10px;
  font-weight: 700;
  padding: 2px 5px;
  border-radius: 10px;
  min-width: 18px;
  text-align: center;
}

.notification-dropdown {
  display: none;
  position: absolute;
  top: 48px;
  right: 0;
  background: var(--white);
  border-radius: 8px;
  box-shadow: 0 4px 20px rgba(0,0,0,0.15);
  width: 320px;
  max-height: 400px;
  overflow-y: auto;
  z-index: 1000;
}

.notification-dropdown.active {
  display: block;
}

.notification-header {
  padding: 12px 16px;
  border-bottom: 1px solid var(--gray-200);
  font-size: 14px;
  font-weight: 600;
  color: var(--gray-900);
}

.notification-item {
  padding: 12px 16px;
  border-bottom: 1px solid var(--gray-100);
  cursor: pointer;
  transition: all 0.2s;
  display: block;
}

.notification-item:hover {
  background: var(--gray-50);
}

.notification-item-link {
  color: inherit;
  text-decoration: none;
}

.notification-item:last-child {
  border-bottom: none;
}

.notification-title {
  font-size: 13px;
  font-weight: 500;
  color: var(--gray-900);
  margin-bottom: 4px;
}

.notification-time {
  font-size: 11px;
  color: var(--gray-500);
}

.notification-footer {
  padding: 10px 16px;
  text-align: center;
  border-top: 1px solid var(--gray-200);
}

.notification-footer a {
  font-size: 12px;
  color: var(--blue-600);
  text-decoration: none;
  font-weight: 500;
}

.nav-tab.active {
  color: var(--blue-600);
  border-bottom-color: var(--blue-600);
}

/* Navigation Tabs */
.nav-tabs {
  background: var(--white);
  padding: 2px 24px;
  margin-bottom: 16px;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
  display: flex;
  gap: 8px;
  overflow-x: auto;
  align-items: center;
}

.nav-tab {
  padding: 14px 16px;
  font-size: 13px;
  font-weight: 500;
  color: var(--gray-600);
  text-decoration: none;
  border-bottom: 2px solid transparent;
  transition: all 0.2s;
  white-space: nowrap;
}

.nav-tab:hover {
  color: var(--gray-900);
  border-bottom-color: var(--gray-300);
}

.nav-tab.active {
  color: var(--blue-600);
  border-bottom-color: var(--blue-600);
}

.stat-card-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.posts-refresh-btn {
  position: relative;
  width: 28px;
  height: 28px;
  border: 1px solid #9aa3af;
  border-radius: 999px;
  background: linear-gradient(180deg, #e5e7eb, #d1d5db);
  color: #374151;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s ease;
}

.posts-refresh-btn .dashicons {
  font-size: 24px;
  width: 24px;
  height: 24px;
  line-height: 24px;
  color: #2563eb;
}

.posts-refresh-btn:hover {
  border-color: #6b7280;
  color: #1f2937;
}

.posts-refresh-btn.is-active {
  border-color: var(--blue-400);
  background: linear-gradient(180deg, var(--blue-50), #dbeafe);
  color: var(--blue-600);
  box-shadow: 0 6px 16px rgba(37, 99, 235, 0.25);
}

.posts-refresh-btn.is-active .dashicons {
  animation: wpil-refresh-spin 1s linear infinite;
}

/* Prevent global wpil_styles button gradient bleed from repainting dashboard controls. */
.wpil_styles .wpil-dashboard-v3 a,
.wpil_styles .wpil-dashboard-v3 button,
.wpil_styles .wpil-dashboard-v3 .button {
  background-image: none !important;
}

.posts-refresh-btn::after {
  content: attr(data-tooltip);
  position: absolute;
  left: 50%;
  bottom: calc(100% + 10px);
  transform: translateX(-50%);
  background: var(--gray-900);
  color: var(--white);
  font-size: 11px;
  line-height: 1.35;
  padding: 7px 9px;
  border-radius: 6px;
  white-space: nowrap;
  opacity: 0;
  visibility: hidden;
  pointer-events: none;
  transition: all 0.15s ease;
  z-index: 20;
}

.posts-refresh-btn::before {
  content: '';
  position: absolute;
  left: 50%;
  bottom: calc(100% + 5px);
  transform: translateX(-50%);
  border-left: 5px solid transparent;
  border-right: 5px solid transparent;
  border-top: 5px solid var(--gray-900);
  opacity: 0;
  visibility: hidden;
  transition: all 0.15s ease;
  pointer-events: none;
  z-index: 20;
}

.posts-refresh-btn:hover::after,
.posts-refresh-btn:hover::before {
  opacity: 1;
  visibility: visible;
}

@keyframes wpil-refresh-spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

/* Key Stats Grid - New Layout */
.stats-grid-new {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 12px;
  margin-bottom: 16px;
}

.stat-card-wide {
  grid-column: span 2;
}

.roi-inline {
  display: grid;
  grid-template-columns: auto 1fr;
  gap: 14px;
  align-items: center;
}

.roi-inline .roi-icon {
  font-size: 30px;
}

.roi-inline .roi-content {
  min-width: 0;
}

/* Old Stats Grid (keeping for compatibility) */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 12px;
  margin-bottom: 16px;
}

.stat-card {
  background: var(--white);
  padding: 18px;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
  transition: all 0.2s;
}

.stat-card-link {
  display: block;
  text-decoration: none;
  color: inherit;
  cursor: pointer;
  border: 1px solid transparent;
}

.stat-card-link:hover,
.stat-card-link:focus-visible {
  border-color: var(--blue-200);
  outline: none;
}

.stat-card-link-primary:hover,
.stat-card-link-primary:focus-visible {
  border-color: var(--blue-500);
}

.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

.stat-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--gray-500);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 10px;
}

.stat-value-row {
  display: flex;
  align-items: baseline;
  gap: 8px;
  margin-bottom: 8px;
}

.stat-value {
  font-size: 28px;
  font-weight: 700;
  line-height: 1;
  color: var(--gray-900);
}

.stat-total {
  font-size: 16px;
  color: var(--gray-400);
  font-weight: 500;
}

.stat-progress {
  height: 6px;
  background: var(--gray-100);
  border-radius: 3px;
  overflow: hidden;
  margin-bottom: 8px;
}

.stat-progress-fill {
  height: 100%;
  border-radius: 3px;
  transition: width 0.5s ease;
}

.stat-trend {
  font-size: 12px;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.trend-up {
  color: var(--green-600);
}

.trend-down {
  color: var(--red-600);
}

.stat-subtitle {
  font-size: 12px;
  color: var(--gray-600);
  margin-top: 4px;
}

/* Link Distribution Card */
.distribution-card {
  background: var(--white);
  padding: 20px;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
  margin-bottom: 16px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.card-title {
  font-size: 15px;
  font-weight: 600;
  color: var(--gray-900);
}

.total-links {
  font-size: 13px;
  color: var(--gray-600);
  font-weight: 500;
}

/* Stacked Bar Visualization */
.stacked-bar-container {
  margin-bottom: 16px;
}

.stacked-bar {
  height: 48px;
  background: var(--gray-100);
  border-radius: 8px;
  display: flex;
  overflow: hidden;
}

.stacked-segment {
  height: 100%;
  transition: opacity 0.2s;
  cursor: pointer;
}

.stacked-segment:hover {
  opacity: 0.85;
}

.stacked-segment.internal {
  background: var(--blue-600);
}

.stacked-segment.external {
  background: var(--green-600);
}

/* Distribution Legend */
.distribution-legend {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 12px;
  margin-bottom: 16px;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 10px;
}

.legend-color {
  width: 16px;
  height: 16px;
  border-radius: 4px;
  flex-shrink: 0;
}

.legend-color.internal {
  background: var(--blue-600);
}

.legend-color.external {
  background: var(--green-600);
}

.legend-text {
  flex: 1;
  min-width: 0;
}

.legend-label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  color: var(--gray-700);
}

.legend-value {
  display: block;
  font-size: 11px;
  color: var(--gray-500);
}

.distribution-bars {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-bottom: 16px;
}

.dist-row {
  display: flex;
  align-items: center;
  gap: 12px;
}

.dist-label {
  font-size: 13px;
  color: var(--gray-700);
  font-weight: 500;
  min-width: 70px;
}

.dist-bar-container {
  flex: 1;
  height: 24px;
  background: var(--gray-100);
  border-radius: 4px;
  overflow: hidden;
  position: relative;
}

.dist-bar {
  height: 100%;
  display: flex;
  align-items: center;
  padding: 0 10px;
  font-size: 11px;
  font-weight: 700;
  color: white;
  transition: width 0.5s ease;
}

.dist-bar.internal {
  background: linear-gradient(90deg, var(--blue-600), var(--blue-500));
}

.dist-bar.external {
  background: linear-gradient(90deg, var(--green-600), var(--green-500));
}

.dist-bar.anchor {
  background: linear-gradient(90deg, var(--blue-600), var(--blue-500));
}

.dist-count {
  font-size: 13px;
  font-weight: 600;
  color: var(--gray-700);
  min-width: 80px;
  text-align: right;
}

.dist-insight {
  background: var(--blue-50);
  padding: 12px;
  border-radius: 6px;
  border-left: 3px solid var(--blue-500);
  font-size: 13px;
  color: var(--gray-700);
  line-height: 1.5;
}

.dist-insight strong {
  color: var(--gray-900);
}

/* Link Quality Score - Unified */
.quality-unified {
  background: var(--white);
  padding: 20px;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
  margin-bottom: 16px;
  display: grid;
  grid-template-columns: 1fr auto 1fr;
  gap: 24px;
  align-items: start;
  animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.quality-main {
  min-width: 0;
}

.quality-divider {
  width: 1px;
  background: var(--gray-200);
  align-self: stretch;
}

.quality-improvements {
  min-width: 0;
}

.quality-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 12px;
  margin-bottom: 16px;
}

.quality-card {
  background: var(--white);
  padding: 20px;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.quality-header {
  text-align: center;
  margin-bottom: 16px;
}

.quality-score {
  font-size: 40px;
  font-weight: 700;
  color: var(--green-600);
  line-height: 1;
  margin-bottom: 6px;
}

.quality-rating {
  font-size: 14px;
  font-weight: 600;
  color: var(--gray-600);
}

.quality-breakdown {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.quality-item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
}

.quality-icon {
  width: 18px;
  height: 18px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  font-weight: 700;
  flex-shrink: 0;
}

.quality-icon.good {
  background: var(--green-100);
  color: var(--green-600);
}

.quality-icon.bad {
  background: var(--red-100);
  color: var(--red-600);
}

.quality-text {
  color: var(--gray-700);
  flex: 1;
}

/* Greeting Card */
.greeting-card {
  background: var(--white);
  padding: 5px 20px;
  margin-bottom: 12px;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.greeting-title {
  font-size: 16px;
  font-weight: 600;
  color: var(--gray-900);
  margin-bottom: 5px;
}

.greeting-subtitle {
  font-size: 13px;
  color: var(--gray-600);
}

/* Task Cards */
/* Tasks + Activity 2-Column Grid */
.tasks-activity-grid {
  display: grid;
  grid-template-columns: 1.8fr 1fr;
  gap: 16px;
  margin-bottom: 16px;
  align-items: stretch;
}

.tasks-activity-grid.single-column {
  grid-template-columns: 1fr;
}

.tasks-container-compact {
  display: flex;
  flex-direction: column;
  gap: 10px;
  height: 100%;
}

.activity-section-compact {
  background: var(--white);
  padding: 18px;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
  height: 100%;
}

.activity-section-compact .section-title {
  font-size: 15px;
  font-weight: 600;
  color: var(--gray-900);
  margin-bottom: 14px;
}

.activity-section-compact .activity-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  justify-content: flex-start;
  overflow: hidden;
}

.activity-section-compact .activity-item {
  display: flex;
  gap: 12px;
  align-items: flex-start;
  padding: 10px 0;
  border-bottom: 1px solid var(--gray-100);
}

.activity-section-compact .activity-item:last-child {
  border-bottom: none;
  padding-bottom: 0;
}

.activity-section-compact .activity-icon {
  width: 30px;
  height: 30px;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  background: var(--gray-100);
  flex-shrink: 0;
}

.activity-section-compact .activity-content {
  flex: 1;
  min-width: 0;
}

.activity-section-compact .activity-text {
  font-size: 13px;
  color: var(--gray-900);
  font-weight: 500;
  line-height: 1.45;
}

.activity-section-compact .activity-time {
  font-size: 11px;
  color: var(--gray-500);
  margin-top: 2px;
}

.tasks-container {
  display: grid;
  gap: 10px;
  margin-bottom: 16px;
}

.task-card {
  background: var(--blue-50);
  padding: 16px 18px;
  border-radius: 8px;
  border: 1px solid #e0e7ff;
  display: flex;
  align-items: center;
  gap: 14px;
  transition: all 0.2s;
}

.task-card:hover {
  background: var(--white);
  box-shadow: 0 2px 8px rgba(0,0,0,0.06);
  transform: translateY(-1px);
}

.task-icon {
  width: 36px;
  height: 36px;
  background: linear-gradient(135deg, var(--blue-600), var(--blue-500));
  color: var(--white);
  border-radius: 7px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 17px;
  flex-shrink: 0;
}

.activity-section-compact .activity-item:nth-child(1) .activity-icon {
  color: var(--blue-600);
}

.activity-section-compact .activity-item:nth-child(2) .activity-icon {
  color: var(--green-600);
}

.activity-section-compact .activity-item:nth-child(3) .activity-icon {
  color: var(--amber-600);
}

.task-content {
  flex: 1;
  min-width: 0;
}

.task-title {
  font-size: 14px;
  font-weight: 500;
  color: var(--gray-900);
  margin-bottom: 4px;
}

.task-meta {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.task-action-inline {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 2px 8px;
  min-height: 22px;
  border-radius: 999px;
  border: 1px solid var(--blue-600) !important;
  background: var(--blue-600) !important;
  color: var(--white) !important;
  font-size: 11px;
  font-weight: 700;
  line-height: 1;
  text-decoration: none !important;
  transition: all 0.2s;
}

.task-action-inline:hover {
  background: var(--blue-500) !important;
  border-color: var(--blue-500) !important;
  color: var(--white) !important;
}

.task-description {
  margin-top: 6px;
  margin-bottom: 3px;
  font-size: 12px;
  line-height: 1.45;
  color: var(--gray-600);
}

.impact-badge {
  display: inline-flex;
  align-items: center;
  padding: 3px 7px;
  border-radius: 4px;
  font-size: 11px;
  font-weight: 600;
}

.impact-high {
  background: #fee2e2;
  color: var(--red-600);
}

.impact-medium {
  background: #fef3c7;
  color: var(--amber-600);
}

.time-estimate {
    display:none !important;
  font-size: 12px;
  color: var(--gray-600);
}

.task-action {
  background: var(--blue-600) !important;
  color: var(--white) !important;
  border: 1px solid var(--blue-600) !important;
  padding: 8px 16px !important;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.2s;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.task-action:hover {
  background: var(--blue-500) !important;
  color: var(--white) !important;
}

.task-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
}

.task-action-secondary {
  background: var(--blue-600) !important;
  color: var(--white) !important;
  border: 1px solid var(--blue-600) !important;
}

.task-action-secondary:hover {
  background: var(--blue-500) !important;
  color: var(--white) !important;
}

.task-action-cancel {
  background: var(--gray-100) !important;
  color: var(--gray-700) !important;
  border: 1px solid var(--gray-300) !important;
}

.task-action-cancel:hover {
  background: var(--gray-200) !important;
  color: var(--gray-800) !important;
  border-color: var(--gray-400) !important;
}

.task-action-cancel.is-hidden {
  display: none;
}

/* DFY Card */
.dfy-card {
  background: #fef3c7;
  padding: 14px 18px;
  border-radius: 8px;
  margin-bottom: 16px;
  border: 1px solid #fde68a;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
}

.dfy-content {
  flex: 1;
}

.dfy-title {
  font-size: 13px;
  font-weight: 600;
  color: var(--gray-900);
  margin-bottom: 3px;
}

.dfy-subtitle {
  font-size: 12px;
  color: var(--gray-600);
}

.dfy-cta {
  background: var(--white);
  color: var(--gray-700);
  border: 1px solid var(--gray-300);
  padding: 7px 14px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 500;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.2s;
}

.dfy-cta:hover {
  background: var(--gray-50);
  border-color: var(--blue-400);
}

/* Health Metrics */
.metrics-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 12px;
  margin-bottom: 16px;
}

.metric-card {
  background: var(--white);
  padding: 18px;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
  transition: all 0.2s;
  display: flex;
  flex-direction: column;
  position: relative;
}

.metric-card:hover {
  box-shadow: 0 4px 12px rgba(0,0,0,0.08);
  transform: translateY(-1px);
}

.metric-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--gray-500);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 10px;
}

.metric-value {
  font-size: 32px;
  font-weight: 700;
  line-height: 1;
  margin-bottom: 8px;
}

.metric-status {
  display: inline-block;
  padding: 3px 9px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 600;
  margin-bottom: 10px;
}

.status-poor { background: #fee2e2; color: var(--red-600); }
.status-warning { background: #fef3c7; color: var(--amber-600); }
.status-good { background: #d1fae5; color: var(--green-600); }

.wpil-fix-indicator {
  position: absolute;
  top: 14px;
  right: 14px;
  display: none;
  margin-left: 0;
  vertical-align: top;
  z-index: 5;
}

.wpil-fix-indicator.is-active {
  display: inline-flex;
}

.wpil-fix-indicator-spinner {
  width: 14px;
  height: 14px;
  border-radius: 999px;
  border: 2px solid var(--gray-300);
  border-top-color: var(--blue-600);
  animation: wpil-v3-spin 1s linear infinite;
}

.wpil-fix-indicator-popover {
  position: absolute;
  bottom: calc(100% + 4px);
  right: 0;
  width: 240px;
  background: var(--white);
  border: 1px solid var(--gray-200);
  border-radius: 8px;
  box-shadow: 0 6px 24px rgba(0,0,0,0.14);
  padding: 10px;
  display: block;
  opacity: 0;
  visibility: hidden;
  pointer-events: none;
  transform: translateY(2px);
  transition: opacity 0.14s ease, transform 0.14s ease, visibility 0s linear 0.2s;
  z-index: 20;
}

.wpil-fix-indicator:hover .wpil-fix-indicator-popover,
.wpil-fix-indicator:focus-within .wpil-fix-indicator-popover,
.wpil-fix-indicator.is-open .wpil-fix-indicator-popover {
  opacity: 1;
  visibility: visible;
  pointer-events: auto;
  transform: translateY(0);
  transition-delay: 0s;
}

.wpil-fix-indicator-popover::after {
  content: '';
  position: absolute;
  left: 0;
  right: 0;
  bottom: -10px;
  height: 10px;
  background: transparent;
}

.wpil-fix-indicator-title {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  color: var(--gray-500);
  margin-bottom: 8px;
}

.wpil-fix-indicator-progress {
  height: 6px;
  border-radius: 999px;
  background: var(--gray-100);
  overflow: hidden;
}

.wpil-fix-indicator-progress > span {
  display: block;
  height: 100%;
  width: 0%;
  background: linear-gradient(90deg, var(--blue-600), var(--blue-500));
  transition: width 0.2s ease;
}

.wpil-fix-indicator-meta {
  margin-top: 6px;
  font-size: 11px;
  color: var(--gray-600);
}

.wpil-fix-indicator-cancel {
  margin-top: 8px;
  border: 1px solid var(--gray-300);
  background: var(--gray-50);
  color: var(--gray-700);
  border-radius: 5px;
  font-size: 11px;
  padding: 4px 8px;
  cursor: pointer;
}

.wpil-fix-indicator-cancel:hover {
  background: var(--gray-100);
}

@keyframes wpil-v3-spin {
  to {
    transform: rotate(360deg);
  }
}

#wpil-v3-fix-progress-modal {
  position: fixed;
  inset: 0;
  z-index: 10000;
  display: none;
  align-items: center;
  justify-content: center;
  padding: 24px;
}

#wpil-v3-fix-progress-modal.is-open {
  display: flex;
}

#wpil-v3-fix-progress-modal .wpil-v3-progress-backdrop {
  position: absolute;
  inset: 0;
  background: rgba(17,24,39,0.55);
}

#wpil-v3-fix-progress-modal .wpil-v3-progress-panel {
  position: relative;
  z-index: 1;
  width: min(560px, 100%);
  background: var(--white);
  border-radius: 12px;
  padding: 18px 20px;
  box-shadow: 0 10px 32px rgba(0,0,0,0.2);
}

#wpil-v3-fix-progress-modal h4 {
  margin: 0;
  font-size: 17px;
  color: var(--gray-900);
}

#wpil-v3-fix-progress-modal p {
  margin: 8px 0 0;
  font-size: 13px;
  color: var(--gray-600);
  line-height: 1.45;
}

#wpil-v3-fix-progress-modal .wpil-v3-progress-bar {
  margin-top: 14px;
  height: 8px;
  background: var(--gray-100);
  border-radius: 999px;
  overflow: hidden;
}

#wpil-v3-fix-progress-modal .wpil-v3-progress-bar > span {
  display: block;
  width: 0%;
  height: 100%;
  background: linear-gradient(90deg, var(--blue-600), var(--blue-500));
  transition: width 0.2s ease;
}

#wpil-v3-fix-progress-modal .wpil-v3-progress-meta {
  margin-top: 8px;
  font-size: 12px;
  color: var(--gray-700);
  display: flex;
  justify-content: space-between;
}

#wpil-v3-fix-progress-modal .wpil-v3-progress-foot {
  margin-top: 12px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
}

#wpil-v3-fix-progress-modal .wpil-v3-progress-foot > div {
  display: flex;
  align-items: center;
  gap: 8px;
}

#wpil-v3-fix-progress-modal .wpil-v3-progress-foot small {
  color: var(--gray-500);
  font-size: 11px;
}

#wpil-v3-fix-progress-modal .wpil-v3-progress-foot .button {
  background: var(--gray-200) !important;
  background-image: none !important;
  border: 1px solid var(--gray-300) !important;
  color: var(--gray-800) !important;
}

#wpil-v3-fix-progress-modal .wpil-v3-progress-foot .button:hover {
  background: var(--gray-300) !important;
  color: var(--gray-900) !important;
}

#wpil-v3-fix-progress-modal .wpil-v3-manual-review {
  margin-top: 12px;
  padding: 12px;
  border: 1px solid var(--gray-200);
  border-radius: 8px;
  background: var(--gray-50);
}

#wpil-v3-fix-progress-modal .wpil-v3-manual-review-title {
  margin: 0 0 8px;
  font-size: 13px;
  font-weight: 600;
  color: var(--gray-900);
}

#wpil-v3-fix-progress-modal .wpil-v3-manual-review .button {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

#wpil-v3-fix-progress-modal .wpil-v3-insertion-mode {
  margin-top: 10px;
  padding: 10px 12px;
  border: 1px solid var(--gray-200);
  border-radius: 8px;
  background: var(--white);
}

#wpil-v3-fix-progress-modal .wpil-v3-insertion-mode-title {
  margin: 0 0 8px;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--gray-500);
}

#wpil-v3-fix-progress-modal .wpil-v3-insertion-mode label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: var(--gray-700);
  margin: 4px 0;
}

#wpil-v3-fix-progress-modal .wpil-v3-insertion-mode input[type="radio"] {
  margin: 0;
}

/* Dashboard fallback styles for review modal utility classes (Tailwind-independent). */
#wpil-review-modal .hidden { display: none; }
#wpil-review-modal .flex { display: flex; }
#wpil-review-modal .flex-col { flex-direction: column; }
#wpil-review-modal .items-center { align-items: center; }
#wpil-review-modal .items-start { align-items: flex-start; }
#wpil-review-modal .justify-center { justify-content: center; }
#wpil-review-modal .flex-1 { flex: 1 1 0%; }
#wpil-review-modal .min-w-0 { min-width: 0; }
#wpil-review-modal .w-full { width: 100%; }
#wpil-review-modal .text-center { text-align: center; }
#wpil-review-modal .space-y-4 > * + * { margin-top: 1rem; }
#wpil-review-modal .space-x-4 > * + * { margin-left: 1rem; }
#wpil-review-modal .gap-2 { gap: 0.5rem; }
#wpil-review-modal .gap-3 { gap: 0.75rem; }
#wpil-review-modal .gap-6 { gap: 1.5rem; }
#wpil-review-modal .mb-1 { margin-bottom: 0.25rem; }
#wpil-review-modal .mb-2 { margin-bottom: 0.5rem; }
#wpil-review-modal .mt-0\.5 { margin-top: 0.125rem; }
#wpil-review-modal .mt-1 { margin-top: 0.25rem; }
#wpil-review-modal .rounded { border-radius: 0.25rem; }
#wpil-review-modal .rounded-full { border-radius: 9999px; }
#wpil-review-modal .rounded-xl { border-radius: 0.75rem; }
#wpil-review-modal .border { border: 1px solid #e5e7eb; }
#wpil-review-modal .border-l { border-left: 1px solid #f3f4f6; }
#wpil-review-modal .border-gray-100 { border-color: #f3f4f6; }
#wpil-review-modal .border-gray-200 { border-color: #e5e7eb; }
#wpil-review-modal .bg-white { background: #fff; }
#wpil-review-modal .bg-gray-100 { background: #f3f4f6; }
#wpil-review-modal .shadow-sm { box-shadow: 0 1px 2px rgba(0,0,0,0.06); }
#wpil-review-modal .p-2 { padding: 0.5rem; }
#wpil-review-modal .p-5 { padding: 1.25rem; }
#wpil-review-modal .px-1\.5 { padding-left: 0.375rem; padding-right: 0.375rem; }
#wpil-review-modal .py-0\.5 { padding-top: 0.125rem; padding-bottom: 0.125rem; }
#wpil-review-modal .pl-6 { padding-left: 1.5rem; }
#wpil-review-modal .py-10 { padding-top: 2.5rem; padding-bottom: 2.5rem; }
#wpil-review-modal .font-medium { font-weight: 500; }
#wpil-review-modal .font-bold { font-weight: 700; }
#wpil-review-modal .uppercase { text-transform: uppercase; }
#wpil-review-modal .leading-relaxed { line-height: 1.625; }
#wpil-review-modal .text-\[10px\] { font-size: 10px; }
#wpil-review-modal .text-xs { font-size: 12px; }
#wpil-review-modal .text-sm { font-size: 13px; }
#wpil-review-modal .text-gray-300 { color: #d1d5db; }
#wpil-review-modal .text-gray-400 { color: #9ca3af; }
#wpil-review-modal .text-gray-500 { color: #6b7280; }
#wpil-review-modal .text-gray-700 { color: #374151; }
#wpil-review-modal .text-gray-900 { color: #111827; }
#wpil-review-modal .text-green-600 { color: #059669; }
#wpil-review-modal .text-purple-600 { color: #7c3aed; }
#wpil-review-modal .ring-2 { box-shadow: inset 0 0 0 2px #d1d5db; }
#wpil-review-modal .ring-gray-300 { box-shadow: inset 0 0 0 2px #d1d5db; }
#wpil-review-modal .transition-all,
#wpil-review-modal .transition-colors { transition: all 0.15s ease; }
#wpil-review-modal .w-4 { width: 1rem; height: 1rem; }
#wpil-review-modal .h-4 { width: 1rem; height: 1rem; }
#wpil-review-modal .w-5 { width: 1.25rem; height: 1.25rem; }
#wpil-review-modal .h-5 { width: 1.25rem; height: 1.25rem; }
#wpil-review-modal .w-6 { width: 1.5rem; height: 1.5rem; }
#wpil-review-modal .h-6 { width: 1.5rem; height: 1.5rem; }
#wpil-review-modal .truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
#wpil-review-modal .truncate:hover{
  white-space: normal;    
}
@keyframes wpilSpin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

#wpil-review-modal .animate-spin,
#wpil-v3-fix-progress-modal .animate-spin,
#wpil-v3-fix-progress-modal .wpil-spin {
  animation: wpilSpin 0.8s linear infinite;
}

#wpil-review-modal button {
  background: #ffffff !important;
  background-image: none !important;
  color: #374151 !important;
  border: 1px solid #d1d5db !important;
}

#wpil-review-modal [data-action] {
  border: 0 !important;
  background: transparent !important;
}

#wpil-review-modal #wpil-review-auto-approve-all,
#wpil-review-modal #wpil-review-auto-approve-all.lw-gradient-bg {
  background: linear-gradient(90deg, #7F5AF0 0%, #2C6BFF 100%) !important;
  background-image: linear-gradient(90deg, #7F5AF0 0%, #2C6BFF 100%) !important;
  border: 0 !important;
  color: #ffffff !important;
}

@media (min-width: 768px) {
  #wpil-review-modal .md\:flex-row { flex-direction: row; }
  #wpil-review-modal .md\:block { display: block; }
  #wpil-review-modal .md\:w-1\/4 { width: 25%; }
}

.metric-description {
  font-size: 13px;
  color: var(--gray-600);
  line-height: 1.4;
  margin-bottom: auto;
  padding-bottom: 12px;
}

.metric-button {
  width: 100%;
  padding: 9px;
  background: var(--gray-200) !important;
  border: 1px solid var(--gray-300) !important;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
  color: var(--gray-800) !important;
  cursor: pointer;
  transition: all 0.2s;
  margin-top: auto;
  display: flex;
  align-items: center;
  justify-content: center;
  text-align: center;
}

.metric-button:hover {
  background: var(--gray-300) !important;
  color: var(--gray-900) !important;
}

#wpil-fix-modal .wpil-fix-secondary[data-wpil-fix-cancel] {
  background: var(--gray-200) !important;
  color: var(--gray-700) !important;
  border: 1px solid var(--gray-300) !important;
  border-radius: 8px;
  padding: 8px 14px;
  min-height: 36px;
  font-size: 12px;
  font-weight: 600;
}

#wpil-fix-modal .wpil-fix-secondary[data-wpil-fix-cancel]:hover {
  background: var(--gray-300) !important;
  color: var(--gray-900) !important;
}

/* Compact Row - Time Saved + Coming Soon */
.compact-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  gap: 12px;
  margin-bottom: 16px;
}

/* Time Saved */
.roi-card {
  background: var(--white);
  padding: 18px;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
  display: grid;
  grid-template-columns: auto 1fr;
  gap: 14px;
  align-items: center;
}

.roi-icon {
  font-size: 30px;
}

.roi-content {
  min-width: 0;
}

.roi-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--gray-500);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 4px;
}

.roi-value {
  font-size: 24px;
  font-weight: 700;
  color: var(--green-600);
  margin-bottom: 2px;
  line-height: 1;
}

.roi-detail {
  font-size: 12px;
  color: var(--gray-600);
}

/* Quick Actions */
/* Actions + Features Combined Vertical */
.actions-features-combined {
  background: var(--white);
  padding: 18px;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
  margin-bottom: 16px;
  display: grid;
  grid-template-columns: 1fr 1.5fr;
  gap: 24px;
}

.combined-section .section-title {
  font-size: 14px;
  font-weight: 600;
  color: var(--gray-900);
  margin-bottom: 12px;
}

.actions-list-vertical {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.action-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  background: var(--gray-50);
  border: 1px solid var(--gray-200);
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
}

.action-row:hover {
  background: var(--white);
  border-color: var(--blue-400);
  transform: translateX(2px);
}

.action-icon-sm {
  font-size: 16px;
  flex-shrink: 0;
}

.action-text {
  font-size: 13px;
  font-weight: 500;
  color: var(--gray-700);
}

.features-list-vertical {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.feature-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 12px 14px;
  background: var(--gray-50);
  border: 1px solid var(--gray-200);
  border-radius: 6px;
  transition: all 0.2s;
}

.feature-row:hover {
  border-color: var(--blue-400);
  background: var(--white);
}

.feature-info {
  flex: 1;
  min-width: 0;
}

.feature-name {
  font-size: 13px;
  font-weight: 600;
  color: var(--gray-900);
  margin-bottom: 2px;
}

.feature-hint {
  font-size: 11px;
  color: var(--gray-600);
  line-height: 1.3;
}

.feature-btn-sm {
  padding: 6px 12px;
  background: var(--blue-600);
  color: white !important;
  border: none;
  border-radius: 5px;
  font-size: 12px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
  min-width: 80px;
  text-align: center;
}

.feature-btn-sm:hover {
  background: var(--blue-500);
}

.ai-usage-section {
  background: var(--white);
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
  margin-top: 16px;
  overflow: hidden;
}

.ai-usage-toggle {
  width: 100%;
  border: 0;
  background: transparent;
  min-height: 60px;
  padding: 20px !important;
  display: flex;
  align-items: center;
  justify-content: space-between;
  cursor: pointer;
  text-align: left;
}

.ai-usage-toggle-icon {
  color: var(--gray-500);
  transition: transform 0.2s ease;
}

.ai-usage-section.is-open .ai-usage-toggle-icon {
  transform: rotate(180deg);
}

.ai-usage-shell-body {
  position: relative;
  padding: 0 20px 20px;
  border-top: 1px solid var(--gray-100);
}

.ai-usage-shell-body[hidden] {
  display: none !important;
}

.ai-usage-loading {
  padding: 20px 0 2px;
  font-size: 12px;
  color: var(--gray-600);
}

.ai-usage-loading.is-error {
  color: var(--red-600);
}

.ai-usage-shell-body.is-loading .ai-usage-panel {
  opacity: 0.35;
  filter: blur(0.4px);
  pointer-events: none;
}

.ai-usage-loading-overlay {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(255, 255, 255, 0.45);
  backdrop-filter: blur(5px);
  z-index: 3;
}

.ai-usage-loading-chip {
  padding: 8px 12px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.92);
  border: 1px solid var(--gray-200);
  box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
  font-size: 12px;
  font-weight: 600;
  color: var(--gray-700);
}

.ai-usage-header {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  align-items: flex-start;
  flex-wrap: wrap;
  margin-bottom: 16px;
}

.ai-usage-title {
  font-size: 15px;
  font-weight: 600;
  color: var(--gray-900);
}

.ai-usage-subtitle {
  font-size: 12px;
  color: var(--gray-600);
  margin-top: 3px;
}

.ai-usage-filter-form {
  display: flex;
  align-items: flex-start;
  justify-content: flex-end;
  gap: 16px;
  margin: auto 0;
  padding: 10px 0;
}

.ai-usage-filter-main {
  display: flex;
  flex-direction: column;
  gap: 10px;
  min-width: 0;
}

.ai-usage-filter-row {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  flex-wrap: wrap;
  max-width: 280px;
}

.ai-usage-filter-row-top {
  justify-content: flex-start;
}

.ai-usage-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.ai-usage-field-view {
  min-width: 250px;
}

.ai-usage-view-toggle {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  min-height: 38px;
  color: var(--gray-700);
}

.ai-usage-view-label {
  font-size: 12px;
  font-weight: 600;
  line-height: 1.2;
}

.ai-usage-view-switch {
  position: relative;
  width: 48px;
  height: 26px;
  flex: 0 0 auto;
}

.ai-usage-view-switch input {
  position: absolute;
  inset: 0;
  opacity: 0;
  margin: 0;
  cursor: pointer;
  z-index: 2;
}

.ai-usage-view-slider {
  position: absolute;
  inset: 0;
  border-radius: 999px;
  background: var(--gray-300);
  transition: background 0.2s ease;
}

.ai-usage-view-slider::after {
  content: '';
  position: absolute;
  top: 3px;
  left: 3px;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: var(--white);
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.18);
  transition: transform 0.2s ease;
}

.ai-usage-view-switch input:checked + .ai-usage-view-slider {
  background: var(--blue-600);
}

.ai-usage-view-switch input:checked + .ai-usage-view-slider::after {
  transform: translateX(22px);
}

.ai-usage-field label {
  font-size: 11px;
  color: var(--gray-600);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.ai-usage-field input[type="date"] {
  border: 1px solid var(--gray-300);
  border-radius: 6px;
  padding: 6px 8px;
  font-size: 12px;
  color: var(--gray-800);
  background: var(--white);
}

.ai-usage-field select {
  border: 1px solid var(--gray-300);
  border-radius: 6px;
  padding: 6px 8px;
  font-size: 12px;
  color: var(--gray-800);
  background: var(--white);
  min-width: 220px;
}

.ai-usage-field-events {
  width: 100%;
  min-width: 0;
  max-width: 560px;
}

.ai-usage-field-events .select2-container {
  width: 100% !important;
}

.ai-usage-field-events .select2-container--default .select2-selection--multiple {
  border: 1px solid var(--gray-300);
  border-radius: 6px;
  min-height: 38px;
  padding: 4px 8px 4px 6px;
  overflow: visible;
}

.ai-usage-field-events .select2-container--default.select2-container--focus .select2-selection--multiple {
  border: 1px solid var(--blue-500);
}

.ai-usage-field-events .select2-container--default .select2-selection--multiple .select2-selection__rendered {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: 4px;
  overflow: visible;
  white-space: normal;
  padding: 0;
  min-height: 28px;
}

.ai-usage-field-events .select2-container--default .select2-selection--multiple .select2-selection__choice {
  flex: 0 0 auto;
  margin-top: 0;
  margin-right: 0;
  margin-bottom: 0;
  max-width: 100%;
}

.ai-usage-field-events .select2-container--default .select2-search--inline {
  flex: 1 1 140px;
  min-width: 120px;
}

.ai-usage-field-events .select2-container--default .select2-search--inline .select2-search__field {
  margin-top: 0;
  height: 26px;
  width: 100% !important;
  min-width: 100px !important;
}

.ai-usage-field-help {
  font-size: 11px;
  color: var(--gray-500);
}

.ai-usage-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  padding-top: 19px;
  flex: 0 0 auto;
}

.ai-usage-sync-btn {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  min-width: 34px;
  height: 34px;
  padding: 0;
  flex: 0 0 auto;
}

.ai-usage-sync-btn:hover {
  border-color: var(--blue-500);
  color: var(--blue-600);
}

.ai-usage-sync-btn.is-active {
  border-color: var(--blue-400);
  background: linear-gradient(180deg, var(--blue-50), #dbeafe);
  color: var(--blue-600);
  box-shadow: 0 6px 16px rgba(37, 99, 235, 0.18);
}

.ai-usage-sync-btn.is-active .dashicons {
  animation: wpil-refresh-spin 1s linear infinite;
}

.ai-usage-sync-btn::after {
  content: attr(data-tooltip);
  position: absolute;
  right: 0;
  bottom: calc(100% + 10px);
  background: var(--gray-900);
  color: var(--white);
  font-size: 11px;
  line-height: 1.35;
  padding: 7px 9px;
  border-radius: 6px;
  width: 240px;
  white-space: normal;
  opacity: 0;
  visibility: hidden;
  pointer-events: none;
  transition: all 0.15s ease;
  z-index: 20;
}

.ai-usage-sync-btn::before {
  content: '';
  position: absolute;
  right: 10px;
  bottom: calc(100% + 5px);
  border-left: 5px solid transparent;
  border-right: 5px solid transparent;
  border-top: 5px solid var(--gray-900);
  opacity: 0;
  visibility: hidden;
  transition: all 0.15s ease;
  pointer-events: none;
  z-index: 20;
}

.ai-usage-sync-btn:hover::after,
.ai-usage-sync-btn:hover::before,
.ai-usage-sync-btn:focus-visible::after,
.ai-usage-sync-btn:focus-visible::before {
  opacity: 1;
  visibility: visible;
}

.ai-usage-sync-btn .dashicons {
  font-size: 16px;
  width: 16px;
  height: 16px;
}

.ai-usage-btn {
  border: 1px solid var(--gray-300);
  border-radius: 6px;
  padding: 7px 10px;
  background: var(--white);
  color: var(--gray-700);
  text-decoration: none;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  line-height: 1;
}

.ai-usage-btn:hover {
  border-color: var(--blue-500);
  color: var(--blue-600);
}

.ai-usage-btn-disabled {
  opacity: 0.45;
  pointer-events: none;
}

.ai-usage-btn-primary {
  background: var(--blue-600);
  border-color: var(--blue-600);
  color: var(--white);
}

.ai-usage-btn-primary:hover {
  background: var(--blue-500);
  border-color: var(--blue-500);
  color: var(--white);
}

/* Override wpil_styles/Tailwind button reset that makes submit buttons transparent. */
.wpil_styles .wpil-dashboard-v3 button.ai-usage-btn {
  background: var(--white) !important;
  color: var(--gray-700) !important;
  border: 1px solid var(--gray-300) !important;
}

.wpil_styles .wpil-dashboard-v3 button.ai-usage-btn.ai-usage-btn-primary {
  background: var(--blue-600) !important;
  color: var(--white) !important;
  border: 1px solid var(--blue-600) !important;
}

.wpil_styles .wpil-dashboard-v3 button.ai-usage-btn.ai-usage-btn-primary:hover {
  background: var(--blue-500) !important;
  border-color: var(--blue-500) !important;
  color: var(--white) !important;
}

.ai-usage-table-wrap {
  width: 100%;
  overflow-x: auto;
  border: 1px solid var(--gray-200);
  border-radius: 8px;
}

.ai-usage-table {
  width: 100%;
  border-collapse: collapse;
  min-width: 980px;
}

.ai-usage-table thead th {
  background: var(--gray-50);
  color: var(--gray-700);
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  text-align: left;
  padding: 10px 12px;
  border-bottom: 1px solid var(--gray-200);
}

.ai-usage-table tbody td {
  padding: 10px 12px;
  border-bottom: 1px solid var(--gray-100);
  font-size: 12px;
  color: var(--gray-700);
  vertical-align: top;
}

.ai-usage-table tbody tr:last-child td {
  border-bottom: none;
}

.ai-usage-table .mono {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
  font-size: 11px;
  color: var(--gray-600);
}

.ai-usage-table .num {
  text-align: right;
  font-variant-numeric: tabular-nums;
}

.ai-usage-empty {
  padding: 16px;
  font-size: 12px;
  color: var(--gray-600);
}

.ai-usage-foot {
  margin-top: 16px;
  display: flex;
  justify-content: space-between;
  gap: 10px;
  align-items: center;
  flex-wrap: wrap;
}

.ai-usage-count {
  font-size: 12px;
  color: var(--gray-600);
}

.ai-usage-pagination {
  display: flex;
  align-items: center;
  gap: 6px;
}

.ai-usage-page-label {
  font-size: 12px;
  color: var(--gray-600);
  padding: 0 6px;
}

.section-title {
  font-size: 14px;
  font-weight: 600;
  color: var(--gray-900);
  margin-bottom: 12px;
}

.actions-section {
  background: var(--white);
  padding: 18px;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
  margin-bottom: 16px;
}

.section-title {
  font-size: 14px;
  font-weight: 600;
  color: var(--gray-900);
  margin-bottom: 12px;
}

.actions-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));
  gap: 10px;
}

.action-item {
  background: var(--gray-50);
  border: 1px solid var(--gray-200);
  padding: 12px;
  border-radius: 6px;
  text-align: center;
  cursor: pointer;
  transition: all 0.2s;
}

.action-item:hover {
  background: var(--white);
  border-color: var(--blue-400);
  transform: translateY(-1px);
}

.action-icon-btn {
  font-size: 20px;
  margin-bottom: 5px;
}

.action-label {
  font-size: 11px;
  font-weight: 500;
  color: var(--gray-700);
}

/* Features */
.features-section {
  background: var(--white);
  padding: 18px;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
  margin-bottom: 16px;
}

.features-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 10px;
}

.feature-item {
  background: var(--gray-50);
  border: 1px solid var(--gray-200);
  padding: 14px;
  border-radius: 6px;
  transition: all 0.2s;
}

.feature-item:hover {
  border-color: var(--blue-400);
  background: var(--white);
}

.feature-title {
  font-size: 13px;
  font-weight: 600;
  color: var(--gray-900);
  margin-bottom: 4px;
}

.feature-desc {
  font-size: 12px;
  color: var(--gray-600);
  margin-bottom: 10px;
  line-height: 1.4;
}

.feature-btn {
  width: 100%;
  padding: 7px;
  background: var(--blue-600);
  color: white;
  border: none;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.feature-btn:hover {
  background: var(--blue-500);
}

/* Coming Soon */
.coming-soon {
  background: var(--green-50);
  border: 1px solid #bbf7d0;
  padding: 14px 18px;
  border-radius: 8px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 14px;
}

.coming-soon-content {
  flex: 1;
}

.coming-soon-title {
  font-size: 13px;
  font-weight: 600;
  color: var(--gray-900);
  margin-bottom: 3px;
}

.coming-soon-subtitle {
  font-size: 11px;
  color: var(--gray-600);
}
/* Container */
/* Icon-only reopen bubble */
.wpil-feedback-reopen {
  position: absolute;
  right: 0;
  bottom: 0;

  width: 44px;
  height: 44px;
  border-radius: 999px;
  border: 1px solid #e7e7e7;
  background: #fff;
  box-shadow: 0 10px 26px rgba(0,0,0,0.10);

  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;

  transition: transform .18s ease, box-shadow .18s ease;
}

.wpil-feedback-reopen:hover {
  transform: translateY(-1px);
  box-shadow: 0 14px 32px rgba(0,0,0,0.12);
}

/* While open, hide the reopen bubble */
.wpil-feedback.is-open .wpil-feedback-reopen {
  opacity: 0;
  pointer-events: none;
}

/* When closed, hide the panel */
.wpil-feedback:not(.is-open) .wpil-feedback-panel {
  opacity: 0;
  pointer-events: none;
  transform: translateX(10px) scale(0.98);
}

.wpil-feedback {
  position: fixed;
  right: 14px;
  bottom: 100px;
  z-index: 9999;
  font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
}

/* Floating tab */
.wpil-feedback-tab {
  position: absolute;
  right: 0;
  bottom: 0;
  display: inline-flex;
  align-items: center;
  gap: 8px;

  padding: 10px 12px;
  border-radius: 999px;
  border: 1px solid #e7e7e7;
  background: #ffffff;

  box-shadow: 0 10px 26px rgba(0,0,0,0.10);
  cursor: pointer;
  transition: transform .18s ease, box-shadow .18s ease, background .18s ease;
}

.wpil-feedback-tab:hover {
  transform: translateY(-1px);
  box-shadow: 0 14px 32px rgba(0,0,0,0.12);
  background: #fbfbfb;
}

.wpil-feedback-tab-icon {
  font-size: 16px;
  line-height: 1;
}

.wpil-feedback-tab-text {
  font-size: 13px;
  font-weight: 600;
  color: #2d2d2d;
}

/* Slide-out panel (compact: less white area) */
.wpil-feedback-panel {
  position: absolute;
  right: 0;
  bottom: 0;

  width: 190px;
  padding: 12px;
  border-radius: 16px;

  border: 1px solid #ededed;
  background: linear-gradient(180deg, #ffffff 0%, #fbfbff 100%);
  box-shadow: 0 16px 46px rgba(0,0,0,0.14);

  transform: translateX(10px) scale(0.98);
  opacity: 0;
  pointer-events: none;
  transition: opacity .18s ease, transform .18s ease;
}

/* Open state */
.wpil-feedback.is-open .wpil-feedback-panel {
  transform: translateX(0) scale(1);
  opacity: 1;
  pointer-events: auto;
}

.wpil-feedback.is-open .wpil-feedback-tab {
  opacity: 0;
  pointer-events: none;
  transform: translateY(2px);
}

/* Header row */
.wpil-feedback-top {
  display: flex;
  align-items: center;
  justify-content: space-around;
  margin-bottom: 10px;
}

.wpil-feedback-title {
  font-size: 13px;
  font-weight: 700;
  color: #222;
  line-height: 1.2;
}

.wpil-feedback-close {
  border: 0;
  background: transparent;
  cursor: pointer;
  color: #666;
  font-size: 14px;
  padding: 6px 8px;
  border-radius: 10px;
}
.wpil-feedback-close:hover {
  background: rgba(0,0,0,0.05);
}

/* Choices (big emojis, compact) */
.wpil-feedback-choices {
  display: grid;
  grid-template-columns: repeat(2, 60px);
  justify-content: center;
  gap: 10px;
}

.wpil-feedback-choice {
  border: 1px solid #e8e8e8;
  background: #ffffff;
  border-radius: 14px;
  padding: 10px 8px;

  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;

  cursor: pointer;
  transition: transform .16s ease, border-color .16s ease, box-shadow .16s ease;
}

.wpil-feedback-choice .emoji {
  font-size: 34px; /* bigger */
  line-height: 1;
}

.wpil-feedback-choice .label {
  font-size: 12px;
  font-weight: 600;
  color: #555;
}

.wpil-feedback-choice:hover {
  transform: translateY(-1px);
  border-color: #cddfff;
  box-shadow: 0 10px 22px rgba(0,0,0,0.10);
}

/* Follow-up */
.wpil-feedback-followup {
  margin-top: 10px;
  padding-top: 10px;
  border-top: 1px solid rgba(0,0,0,0.06);
}

.wpil-feedback-followup-title {
  font-size: 12px;
  font-weight: 700;
  color: #333;
  margin-bottom: 8px;
  text-align: center;
}

.wpil-feedback-text {
  width: 100%;
  resize: vertical;
  min-height: 72px;
  max-height: 160px;
  border-radius: 12px;
  border: 1px solid #e6e6e6;
  padding: 10px;
  font-size: 13px;
  outline: none;
  background: #fff;
}

.wpil-feedback-text:focus {
  border-color: #cddfff;
  box-shadow: 0 0 0 3px rgba(160, 190, 255, 0.25);
}

.wpil-feedback-actions {
  display: flex;
  gap: 8px;
  margin-top: 10px;
}

.wpil-feedback-send {
  flex: 1;
  border: 0;
  border-radius: 12px;
  padding: 10px 12px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  background: #2b6fff;
  color: #fff;
  transition: transform .16s ease, opacity .16s ease;
}
.wpil-feedback-send:hover { transform: translateY(-1px); }

.wpil-feedback-skip {
  border: 1px solid #e3e3e3;
  border-radius: 12px;
  padding: 10px 12px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  background: #fff;
  color: #444;
}

/* Keep feedback action buttons visible even with global dashboard button overrides. */
.wpil_styles .wpil-dashboard-v3 .wpil-feedback-send {
  background: #1d4ed8 !important;
  color: #ffffff !important;
  border: 1px solid #1e40af !important;
  box-shadow: 0 6px 14px rgba(29, 78, 216, 0.26);
}

.wpil_styles .wpil-dashboard-v3 .wpil-feedback-send:hover {
  background: #1e40af !important;
  color: #ffffff !important;
  border-color: #1e3a8a !important;
  box-shadow: 0 10px 20px rgba(30, 64, 175, 0.30);
}

.wpil_styles .wpil-dashboard-v3 .wpil-feedback-send:focus-visible {
  outline: 2px solid #93c5fd;
  outline-offset: 2px;
}

.wpil_styles .wpil-dashboard-v3 .wpil-feedback-send:disabled {
  opacity: 0.55;
  cursor: not-allowed;
  box-shadow: none;
}

.wpil_styles .wpil-dashboard-v3 .wpil-feedback-skip {
  background: #ffffff !important;
  color: #1f2937 !important;
  border: 1px solid #cbd5e1 !important;
}

.wpil_styles .wpil-dashboard-v3 .wpil-feedback-skip:hover {
  background: #f1f5f9 !important;
  color: #0f172a !important;
  border-color: #94a3b8 !important;
}

.wpil-feedback-thanks {
  margin-top: 10px;
  font-size: 13px;
  font-weight: 650;
  color: #2d2d2d;
  background: #f2f6ff;
  border: 1px solid #dbe7ff;
  border-radius: 12px;
  padding: 10px;
}


.wpil_styles .wpil-dashboard-v3 .coming-soon-standalone .waitlist-btn {
  -webkit-appearance: none;
  appearance: none;
  background: #2563eb !important;
  color: #fff !important;
  border: 1px solid #1d4ed8 !important;
  padding: 8px 14px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 700;
  line-height: 1.2;
  text-transform: none !important;
  text-decoration: none !important;
  cursor: pointer !important;
  white-space: nowrap;
  display: inline-flex !important;
  align-items: center;
  justify-content: center;
  min-height: 34px;
  transition: opacity 0.2s, box-shadow 0.2s, transform 0.08s;
  box-shadow: 0 8px 18px rgba(37, 99, 235, 0.22);
}

.wpil_styles .wpil-dashboard-v3 .coming-soon-standalone .waitlist-btn:hover {
  opacity: .96;
  background: #1d4ed8 !important;
  border-color: #1e40af !important;
  box-shadow: 0 10px 22px rgba(37, 99, 235, 0.30);
}

.wpil_styles .wpil-dashboard-v3 .coming-soon-standalone .waitlist-btn:active {
  transform: translateY(1px);
}

/* Coming Soon Standalone */
.coming-soon-standalone {
  background: var(--green-50);
  border: 1px solid #bbf7d0;
  padding: 16px 20px;
  border-radius: 8px;
  margin-bottom: 16px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
}

/* Activity */
.activity-section {
  background: var(--white);
  padding: 18px;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.activity-section .activity-list {
  display: flex;
  flex-direction: column;
}

.activity-section .activity-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 0;
  border-bottom: 1px solid var(--gray-100);
}

.activity-section .activity-item:last-child {
  border-bottom: none;
}

.activity-section .activity-icon {
  width: 28px;
  height: 28px;
  background: var(--gray-100);
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  flex-shrink: 0;
}

.activity-section .activity-text {
  flex: 1;
  min-width: 0;
}

.activity-section .activity-title {
  font-size: 13px;
  font-weight: 500;
  color: var(--gray-900);
  margin-bottom: 1px;
}

.activity-section .activity-time {
  font-size: 11px;
  color: var(--gray-500);
}

/* Responsive */
@media (max-width: 768px) {
  .header {
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
  }
  
  .header-right {
    width: 100%;
    justify-content: space-between;
  }
  
  .header-badges {
    flex: 1;
  }
  
  .badge-separator {
    height: 24px;
  }
  
  .tasks-activity-grid {
    grid-template-columns: 1fr;
  }
  
  .actions-features-combined {
    grid-template-columns: 1fr;
    gap: 20px;
  }

  .ai-usage-header {
    flex-direction: column;
    align-items: stretch;
  }

  .ai-usage-filter-form {
    flex-direction: column;
    align-items: stretch;
  }

  .ai-usage-filter-main {
    width: 100%;
  }

  .ai-usage-actions {
    margin-top: 2px;
    padding-top: 0;
  }
  
  .stats-grid,
  .stats-grid-new,
  .quality-grid,
  .metrics-grid,
  .features-grid,
  .compact-row {
    grid-template-columns: 1fr;
  }
  
  .stat-card-wide {
    grid-column: span 1;
  }
  
  .roi-inline {
    grid-template-columns: auto 1fr;
  }
  
  .quality-unified {
    grid-template-columns: 1fr;
    gap: 16px;
  }
  
  .quality-divider {
    width: 100%;
    height: 1px;
  }
  
  .roi-card {
    grid-template-columns: 1fr;
  }
  
  .dfy-card,
  .coming-soon,
  .coming-soon-standalone {
    flex-direction: column;
    align-items: flex-start;
  }
  
  .dfy-cta,
  .waitlist-btn {
    width: 100%;
  }
  
  .nav-tabs {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }
  
}
</style>
<div class="wpil-dashboard-v3">
<div class="container">
  <form action="" method="post" id="wpil_report_reset_data_form" style="display:none;">
    <input type="hidden" name="reset_data_nonce" value="<?php echo esc_attr(wp_create_nonce(get_current_user_id() . 'wpil_reset_report_data')); ?>">
    <button type="submit" class="button-primary" aria-hidden="true" tabindex="-1">Run a Link Scan</button>
  </form>

  <!-- Header with Notifications -->
  <div class="header">
    <h1>
      Link Whisper
      <span class="site-name"><?php echo esc_html($site_name); ?></span>
    </h1>
    <div class="header-right">
      <div class="header-badges">
        <div class="badge-item membership-badge" style="display:none">
          <span class="badge-label">Membership</span>
          <span class="badge-value"><?php echo $membership_is_lifetime ? esc_html__('Lifetime Membership', 'wpil') : esc_html(number_format_i18n($membership_days_left) . ' days left'); ?></span>
        </div>
        <div class="badge-separator" style="display:none"></div>
        <div class="badge-item credits-badge">
          <?php echo esc_html(number_format_i18n($credits_available)); ?> Credits
        </div>
      </div>
      <div style="position: relative;">
        <button class="notification-bell" type="button" onclick="toggleNotifications()">
          <span class="dashicons dashicons-bell"></span>
          <span id="wpilV3NotificationBadge" class="notification-badge" <?php echo ($notification_unread_count <= 0) ? 'style="display:none;"' : ''; ?>><?php echo esc_html((string)$notification_unread_count); ?></span>
        </button>
        <div class="notification-dropdown" id="notificationDropdown">
          <div class="notification-header">Notifications (<?php echo esc_html((string)$notification_total_count); ?>)</div>
          <?php if(!empty($notification_items)): ?>
            <?php foreach($notification_items as $item): ?>
              <?php $has_action_url = !empty($item['action_url']); ?>
              <?php if($has_action_url): ?>
              <a class="notification-item notification-item-link"
                href="<?php echo esc_url($item['action_url']); ?>"
                target="_blank"
                rel="noopener noreferrer"
                data-wpil-notification-id="<?php echo esc_attr($item['key']); ?>"
                data-wpil-position="<?php echo esc_attr((string)$item['position']); ?>"
                data-wpil-seen="<?php echo !empty($item['seen']) ? '1' : '0'; ?>">
              <?php else: ?>
              <div class="notification-item"
                data-wpil-notification-id="<?php echo esc_attr($item['key']); ?>"
                data-wpil-position="<?php echo esc_attr((string)$item['position']); ?>"
                data-wpil-seen="<?php echo !empty($item['seen']) ? '1' : '0'; ?>">
              <?php endif; ?>
                <div class="notification-title"><?php echo esc_html($item['title']); ?></div>
                <?php if(!empty($item['description'])): ?>
                <div class="notification-time"><?php echo esc_html($item['description']); ?></div>
                <?php endif; ?>
              <?php if($has_action_url): ?>
              </a>
              <?php else: ?>
              </div>
              <?php endif; ?>
            <?php endforeach; ?>
          <?php else: ?>
          <div class="notification-item">
            <div class="notification-title"><?php esc_html_e('No notifications available at the moment.', 'wpil'); ?></div>
          </div>
          <?php endif; ?>
          <div class="notification-footer">
            <a href="<?php echo esc_url($urls['links']); ?>">View all notifications</a>
            <!-- TODO: wire dedicated notification center -->
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Navigation Tabs -->
  <div class="nav-tabs">
    <a href="#overview" class="nav-tab active">Dashboard</a>
    <a href="<?php echo esc_url($urls['links']); ?>" class="nav-tab">Links Report</a>
    <a href="<?php echo esc_url($urls['domains']); ?>" class="nav-tab">Domains Report</a>
    <a href="<?php echo esc_url($urls['clicks']); ?>" class="nav-tab">Clicks Report</a>
    <a href="<?php echo esc_url($urls['broken']); ?>" class="nav-tab">Broken Links Report</a>
    <a href="<?php echo esc_url($urls['sitemaps']); ?>" class="nav-tab">Visual Sitemaps</a>
  </div>

  <!-- Greeting -->
  <div class="greeting-card">
    <h2 class="greeting-title">&#128075; Good <span id="wpil-dashboard-greeting-part"><?php echo esc_html($day_part); ?></span><?php echo !empty($first_name) ? ', ' . esc_html($first_name) : ''; ?>!</h2>
    <p class="greeting-subtitle"><?php echo esc_html($greeting_subtitle); ?></p>
  </div>

  <!-- Priority Tasks + Recent Activity - Two Column Layout -->
  <?php if($show_tasks_activity_section): ?>
  <div class="tasks-activity-grid <?php echo esc_attr($tasks_activity_layout_class); ?>">
    <?php if($has_quick_wins): ?>
    <div class="tasks-container-compact">
      <?php foreach($task_cards as $task): ?>
      <div class="task-card">
        <div class="task-icon"><span class="dashicons dashicons-admin-links"></span></div>
        <div class="task-content">
          <div class="task-title"><?php echo esc_html($task['title']); ?></div>
          <?php if(!empty($task['explanation'])): ?>
          <div class="task-description"><?php echo esc_html($task['explanation']); ?></div>
          <?php endif; ?>
          <div class="task-meta">
            <span class="impact-badge impact-high"><?php echo esc_html($task['impact']); ?></span>
            <a class="task-action-inline" href="<?php echo esc_url($task['url']); ?>"><?php echo esc_html($task['button']); ?></a>
            <span class="time-estimate"><?php echo esc_html($task['time']); ?></span>
          </div>
        </div>
        <div class="task-actions">
          <?php if($show_ai_fix_controls && !empty($task['fix_enabled'])): ?>
          <a class="task-action" href="#"
            role="button"
            data-wpil-fix="1"
            data-wpil-fix-type="<?php echo esc_attr($task['fix_type']); ?>"
            data-wpil-fix-item-id="<?php echo esc_attr($task['id']); ?>"
            data-wpil-fix-estimate="<?php echo esc_attr((string)$task['fix_estimate']); ?>"
            data-wpil-fix-description="<?php echo esc_attr($task['fix_description']); ?>"
          ><?php echo !empty($task['is_running']) ? 'Review Progress' : 'Fix with AI'; ?></a>
          <a
            class="task-action task-action-cancel <?php echo empty($task['is_running']) ? 'is-hidden' : ''; ?>"
            href="#"
            role="button"
            data-wpil-fix-cancel-inline="<?php echo esc_attr($task['fix_type']); ?>"
            data-wpil-fix-cancel-item-id="<?php echo esc_attr($task['id']); ?>"
          >Cancel</a>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if($has_recent_activity): ?>
    <div class="activity-section-compact">
      <h3 class="section-title">Recent Activity</h3>
      <div class="activity-list">
        <?php foreach($activity_items as $activity): ?>
        <div class="activity-item">
          <div class="activity-icon"><span class="dashicons dashicons-<?php echo esc_attr($activity['icon']); ?>"></span></div>
          <div class="activity-content">
            <div class="activity-text"><?php echo esc_html($activity['text']); ?></div>
            <div class="activity-time"><?php echo esc_html($activity['time']); ?></div>
          </div>
        </div>
        <?php endforeach; ?>
        <!-- TODO: replace with event timeline when available -->
      </div>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
  <!-- DFY Card - A/B Test Variant A: Scheduling -->
  <div class="dfy-card">
    <div class="dfy-content">
      <div class="dfy-title">Too busy to fix these yourself?</div>
      <div class="dfy-subtitle">Our team can handle the cleanup for you. Starting at $0.39 a post</div>
    </div>
    <div style="display:flex; gap:8px; align-items:center;">
      <a class="dfy-cta" href="mailto:sarah@linkwhisper.com">Email Us</a>
      <a class="dfy-cta" href="https://linkwhisper.com/book-dfy-call" target="_blank" rel="noopener noreferrer">Schedule a Call</a>
    </div>
  </div>

  <!-- DFY Card - A/B Test Variant B: Payment (Hidden by default, toggle to test) -->
  <div class="dfy-card" id="dfyVariantB" style="display: none;">
    <div class="dfy-content">
      <div class="dfy-title">Too busy to fix these yourself?</div>
      <div class="dfy-subtitle">Get it done in 48 hours &bull; Starting at $497</div>
    </div>
    <a class="dfy-cta" href="https://linkwhisper.com/book-dfy-call" target="_blank" rel="noopener noreferrer">Get Started</a>
  </div>

  <!-- Key Stats Grid -->
  <div class="stats-grid-new">
    <!-- Posts Crawled -->
    <div class="stat-card stat-card-link stat-card-link-primary"
      role="link"
      tabindex="0"
      onclick="window.location.href='<?php echo esc_js($urls['links']); ?>';"
      onkeydown="if(event.key==='Enter' || event.key===' '){ event.preventDefault(); window.location.href='<?php echo esc_js($urls['links']); ?>'; }">
      <div class="stat-card-head">
        <div class="stat-label">Posts Crawled</div>
        <button class="posts-refresh-btn" style="padding-top:0px;" type="button" data-tooltip="Click to rescan the site's posts and refresh link stats."
          title="Click to rescan the site's posts and refresh link stats."
          aria-label="Rescan the site's posts and refresh link stats"
          onclick="event.stopPropagation(); this.classList.add('is-active'); var f = document.getElementById('wpil_report_reset_data_form'); if(f){ if(typeof f.requestSubmit === 'function'){ f.requestSubmit(); }else{ f.submit(); } }">
          <span class="dashicons dashicons-update"></span>
        </button>
      </div>
      <div class="stat-value-row">
        <span class="stat-value"><?php echo esc_html(number_format_i18n($posts_crawled)); ?></span>
      </div>
      <div class="stat-progress">
        <div class="stat-progress-fill" style="width: <?php echo esc_attr((string)$crawl_progress_percent); ?>%; background: linear-gradient(90deg, var(--blue-600), var(--blue-500));"></div>
      </div>
      <div class="stat-subtitle"><?php echo esc_html(number_format_i18n($crawl_progress_percent, 1)); ?>% indexed, <?php echo esc_html(number_format_i18n($coverage_qualified_items)); ?> meeting coverage target &bull; <span class="stat-trend <?php echo esc_attr($crawl_trend_class); ?>"><?php echo esc_html($crawl_trend_text); ?></span></div>
    </div>

    <!-- Clicks Tracked -->
    <a class="stat-card stat-card-link" href="<?php echo esc_url($urls['clicks']); ?>">
      <div class="stat-label">Clicks Tracked</div>
      <div class="stat-value-row">
        <span class="stat-value"><?php echo esc_html(number_format_i18n($clicks_30)); ?></span>
      </div>
      <div class="stat-trend <?php echo esc_attr($click_trend_class); ?>"><?php echo esc_html($click_trend_symbol . $percent_change_display); ?>% vs previous 30 days</div>
      <div class="stat-subtitle">Previous 30 days: <?php echo esc_html(number_format_i18n($clicks_old)); ?> clicks</div>
    </a>

    <!-- Links Created -->
    <div class="stat-card">
      <div class="stat-label">Links Created (30d)</div>
      <div class="stat-value-row">
        <span class="stat-value"><?php echo esc_html(number_format_i18n($links_inserted_30)); ?></span>
      </div>
      <div class="stat-trend <?php echo esc_attr($links_created_trend_class); ?>"><?php echo esc_html($links_created_trend_text); ?></div>
      <div class="stat-subtitle">Previous 30 days: <?php echo esc_html(number_format_i18n($links_inserted_prev_30)); ?> &bull; Tracked total: <?php echo esc_html(number_format_i18n($links_inserted_total)); ?></div>
    </div>

    <!-- Time Saved - Wider Tile -->
    <div class="stat-card stat-card-wide">
      <div class="roi-inline">
        <div class="roi-icon">⏱️</div>
        <div class="roi-content">
          <div class="stat-label">Time Saved This Month</div>
          <div class="stat-value-row">
            <span class="stat-value" style="color: var(--green-600);"><?php echo esc_html(number_format_i18n($hours_saved, 1)); ?></span>
            <span class="stat-total">Manual hours</span>
          </div>
          <div class="stat-subtitle">
            <?php echo esc_html(Wpil_Dashboard::get_time_saved_fun_message($hours_saved)); ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Link Distribution -->
  <div class="distribution-card">
    <div class="card-header">
      <h3 class="card-title">Link Distribution</h3>
      <span class="total-links">Total: <?php echo esc_html(number_format_i18n($total_links)); ?> links</span>
    </div>
    
    <!-- Stacked Bar -->
    <div class="stacked-bar-container">
      <div class="stacked-bar">
        <div class="stacked-segment internal" style="width: <?php echo esc_attr($internal_percent); ?>%;" title="Internal: 1,234 links (65%)"></div>
        <div class="stacked-segment external" style="width: <?php echo esc_attr($external_percent); ?>%;" title="External: 666 links (35%)"></div>
      </div>
    </div>

    <!-- Legend -->
    <div class="distribution-legend">
      <div class="legend-item">
        <div class="legend-color internal"></div>
        <div class="legend-text">
          <span class="legend-label">Internal Links</span>
          <span class="legend-value"><?php echo esc_html(number_format_i18n($internal_links)); ?> (<?php echo esc_html((string)$internal_percent); ?>%)</span>
        </div>
      </div>
      <div class="legend-item">
        <div class="legend-color external"></div>
        <div class="legend-text">
          <span class="legend-label">External Links</span>
          <span class="legend-value"><?php echo esc_html(number_format_i18n($external_links)); ?> (<?php echo esc_html((string)$external_percent); ?>%)</span>
        </div>
      </div>
    </div>
    
    <div class="dist-insight">
      <?php echo esc_html($distribution_hint); ?>
    </div>
  </div>

  <!-- Health Metrics -->
  <div class="metrics-grid">
    <div class="metric-card" data-wpil-live-metric="site_health">
      <div class="metric-label">Site Health Score</div>
      <div class="metric-value" data-wpil-metric-value="site_health" style="color: <?php echo esc_attr($status_health[2]); ?>;"><span data-wpil-metric-number="site_health"><?php echo esc_html((string)((int)$health['score'])); ?></span><span data-wpil-metric-suffix="site_health"></span></div>
      <span class="metric-status <?php echo esc_attr($status_health[1]); ?>" data-wpil-metric-status="site_health"><?php echo esc_html($status_health[0]); ?></span>
      <p class="metric-description" data-wpil-metric-description="site_health"><?php echo esc_html($site_health_hint); ?></p>
      <a class="metric-button" onclick="toggleQualityBreakdown()">View Quality Breakdown</a>
    </div>

    <!-- Link Quality Score -->
    <div class="metric-card" data-wpil-live-metric="link_quality">
      <div class="metric-label">Link Quality Score</div>
      <div class="metric-value" data-wpil-metric-value="link_quality" style="color: <?php echo esc_attr($status_link_quality[2]); ?>;"><span data-wpil-metric-number="link_quality"><?php echo esc_html(number_format_i18n($link_quality_score, 1)); ?></span><span data-wpil-metric-suffix="link_quality" style="font-size: 18px; color: var(--gray-400);">/10</span></div>
      <span class="metric-status <?php echo esc_attr($status_link_quality[1]); ?>" data-wpil-metric-status="link_quality"><?php echo esc_html($status_link_quality[0]); ?></span>
      <?php if($show_ai_fix_controls){ ?>
      <span class="wpil-fix-indicator <?php echo !empty($running_fix_types['link_quality']) ? 'is-active' : ''; ?>" data-wpil-fix-indicator="link_quality">
        <span class="wpil-fix-indicator-spinner" aria-hidden="true"></span>
        <span class="wpil-fix-indicator-popover">
          <div class="wpil-fix-indicator-title">AI Fix Running</div>
          <div class="wpil-fix-indicator-progress"><span data-wpil-fix-progress-bar="link_quality"></span></div>
          <div class="wpil-fix-indicator-meta"><span data-wpil-fix-progress-text="link_quality">Starting...</span></div>
          <button class="wpil-fix-indicator-cancel" type="button" data-wpil-fix-cancel-inline="link_quality">Cancel</button>
        </span>
      </span>
      <?php } ?>
      <p class="metric-description" data-wpil-metric-description="link_quality"><?php echo esc_html($link_quality_description); ?></p>
      <a class="metric-button" href="<?php echo esc_url($urls['link_quality']); ?>">View Details</a>
    </div>

    <div class="metric-card" data-wpil-live-metric="link_coverage">
      <div class="metric-label">Link Coverage</div>
      <div class="metric-value" data-wpil-metric-value="link_coverage" style="color: <?php echo esc_attr($status_coverage[2]); ?>;"><span data-wpil-metric-number="link_coverage"><?php echo esc_html(number_format_i18n($link_coverage_percent, 1)); ?></span><span data-wpil-metric-suffix="link_coverage">%</span></div>
      <span class="metric-status <?php echo esc_attr($status_coverage[1]); ?>" data-wpil-metric-status="link_coverage"><?php echo esc_html($status_coverage[0]); ?></span>
      <?php if($show_ai_fix_controls){ ?>
      <span class="wpil-fix-indicator <?php echo !empty($running_fix_types['link_coverage']) ? 'is-active' : ''; ?>" data-wpil-fix-indicator="link_coverage">
        <span class="wpil-fix-indicator-spinner" aria-hidden="true"></span>
        <span class="wpil-fix-indicator-popover">
          <div class="wpil-fix-indicator-title">AI Fix Running</div>
          <div class="wpil-fix-indicator-progress"><span data-wpil-fix-progress-bar="link_coverage"></span></div>
          <div class="wpil-fix-indicator-meta"><span data-wpil-fix-progress-text="link_coverage">Starting...</span></div>
          <button class="wpil-fix-indicator-cancel" type="button" data-wpil-fix-cancel-inline="link_coverage">Cancel</button>
        </span>
      </span>
      <?php } ?>
      <p class="metric-description" data-wpil-metric-description="link_coverage"><?php echo esc_html($coverage_description); ?></p>
      <a class="metric-button" href="<?php echo esc_url($urls['coverage']); ?>">View Details</a>
    </div>

    <div class="metric-card" data-wpil-live-metric="orphaned_posts">
      <div class="metric-label">Orphaned Posts</div>
      <div class="metric-value" data-wpil-metric-value="orphaned_posts" style="color: <?php echo esc_attr($status_orphans[2]); ?>;"><span data-wpil-metric-number="orphaned_posts"><?php echo esc_html(number_format_i18n($orphanedCount)); ?></span><span data-wpil-metric-suffix="orphaned_posts"></span></div>
      <span class="metric-status <?php echo esc_attr($status_orphans[1]); ?>" data-wpil-metric-status="orphaned_posts"><?php echo esc_html($status_orphans[0]); ?></span>
      <?php if($show_ai_fix_controls){ ?>
      <span class="wpil-fix-indicator <?php echo !empty($running_fix_types['orphaned_posts']) ? 'is-active' : ''; ?>" data-wpil-fix-indicator="orphaned_posts">
        <span class="wpil-fix-indicator-spinner" aria-hidden="true"></span>
        <span class="wpil-fix-indicator-popover">
          <div class="wpil-fix-indicator-title">AI Fix Running</div>
          <div class="wpil-fix-indicator-progress"><span data-wpil-fix-progress-bar="orphaned_posts"></span></div>
          <div class="wpil-fix-indicator-meta"><span data-wpil-fix-progress-text="orphaned_posts">Starting...</span></div>
          <button class="wpil-fix-indicator-cancel" type="button" data-wpil-fix-cancel-inline="orphaned_posts">Cancel</button>
        </span>
      </span>
      <?php } ?>
      <p class="metric-description" data-wpil-metric-description="orphaned_posts"><?php echo esc_html($orphan_description); ?></p>
      <a class="metric-button" href="<?php echo esc_url($urls['orphaned']); ?>">Connect Posts</a>
    </div>

    <div class="metric-card">
      <div class="metric-label">Broken Links</div>
      <div class="metric-value" style="color: <?php echo esc_attr($status_broken[2]); ?>;"><?php echo esc_html(number_format_i18n($brokenLinksCount)); ?></div>
      <span class="metric-status <?php echo esc_attr($status_broken[1]); ?>"><?php echo esc_html($status_broken[0]); ?></span>
      <?php if($show_ai_fix_controls){ ?>
      <span class="wpil-fix-indicator <?php echo !empty($running_fix_types['broken_links']) ? 'is-active' : ''; ?>" data-wpil-fix-indicator="broken_links">
        <span class="wpil-fix-indicator-spinner" aria-hidden="true"></span>
        <span class="wpil-fix-indicator-popover">
          <div class="wpil-fix-indicator-title">AI Fix Running</div>
          <div class="wpil-fix-indicator-progress"><span data-wpil-fix-progress-bar="broken_links"></span></div>
          <div class="wpil-fix-indicator-meta"><span data-wpil-fix-progress-text="broken_links">Starting...</span></div>
          <button class="wpil-fix-indicator-cancel" type="button" data-wpil-fix-cancel-inline="broken_links">Cancel</button>
        </span>
      </span>
      <?php } ?>
      <p class="metric-description"><?php echo esc_html($broken_description); ?></p>
      <a class="metric-button" href="<?php echo esc_url($urls['broken']); ?>">Fix Links</a>
    </div>
  </div>

  <!-- Quality Breakdown (Initially Hidden) -->
  <div class="quality-unified" id="qualityBreakdown" style="display: none;">
    <div class="quality-main">
      <div class="quality-header">
        <div class="quality-score"><?php echo esc_html((string)((int)$health['score'])); ?><span style="font-size: 24px; color: var(--gray-400);">/100</span></div>
        <div class="quality-rating"><?php echo esc_html($quality_rating); ?></div>
      </div>
      <div class="quality-breakdown">
        <?php foreach($quality_breakdown_items as $item): ?>
        <div class="quality-item">
          <span class="quality-icon <?php echo !empty($item['good']) ? 'good' : 'bad'; ?>"><?php echo !empty($item['good']) ? '&#10003;' : '&#10007;'; ?></span>
          <span class="quality-text"><?php echo esc_html($item['text']); ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="quality-divider"></div>
    <div class="quality-improvements" style="margin-top: 80px;">
      <div style="font-size: 14px; font-weight: 600; color: var(--gray-900); margin-bottom: 12px;">Improvement Opportunities</div>
      <?php if(!empty($quality_improvements)): ?>
      <div style="font-size: 13px; color: var(--gray-700); line-height: 1.6; margin-bottom: 10px;">
        <strong>+<?php echo esc_html(number_format_i18n($quality_total_possible_gain, 1)); ?> points</strong> available by:
      </div>
      <ul style="list-style: none; padding: 0; font-size: 12px; color: var(--gray-600); line-height: 1.8;">
        <?php foreach($quality_improvements as $improvement): ?>
        <li>&bull; <?php echo esc_html($improvement['text']); ?> (+<?php echo esc_html(number_format_i18n($improvement['gain'], 1)); ?>)</li>
        <?php endforeach; ?>
      </ul>
      <?php else: ?>
      <div style="font-size: 13px; color: var(--gray-700); line-height: 1.6;">
        Nice work. You are already performing well across the core site health metrics.
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Coming Soon -->
  <?php if(!$waitlist_signed_up): ?>
  <div class="coming-soon-standalone">
    <div class="coming-soon-content">
      <div class="coming-soon-title">Coming Soon: Link Decay Detection</div>
      <div class="coming-soon-subtitle">Automatically detect links pointing to outdated content (2+ years old)</div>
    </div>
    <button class="waitlist-btn" type="button" data-email="<?php echo esc_attr($user->user_email); ?>">Join Waitlist</button>
  </div>
  <?php endif; ?>

  <?php if(!$experience_feedback_given): ?>
    <div id="wpil-feedback"
     class="wpil-feedback"
     data-email="<?php echo esc_attr($user->user_email); ?>">

        <!-- Tiny reopen button (only visible after close) -->
        <button type="button" class="wpil-feedback-reopen" style="display:none;" aria-expanded="false" aria-controls="wpil-feedback-panel" aria-label="Open feedback">
            &#x1F5E8;&#xFE0F;
        </button>

        <div id="wpil-feedback-panel" class="wpil-feedback-panel" role="dialog" aria-label="Dashboard feedback">
            <div class="wpil-feedback-top">
            <div class="wpil-feedback-title">Love the new experience?</div>

            <button type="button" style="display:none" class="wpil-feedback-close" aria-label="Close">
                ✕
            </button>
            </div>

            <div class="wpil-feedback-choices">
            <button type="button" class="wpil-feedback-choice" data-feedback-response="yes" aria-label="Yes">
                <span class="emoji">😍</span>
                <span class="label">Yes</span>
            </button>

            <button type="button" class="wpil-feedback-choice" data-feedback-response="no" aria-label="No">
                <span class="emoji">😕</span>
                <span class="label">No</span>
            </button>
            </div>

            <div class="wpil-feedback-followup" hidden>
            <div class="wpil-feedback-followup-title"></div>

            <textarea class="wpil-feedback-text"
                        rows="3"
                        placeholder="Tell us what we should change (optional)"></textarea>

            <div class="wpil-feedback-actions">
                <button type="button" class="wpil-feedback-send">Send</button>
                <button type="button" class="wpil-feedback-skip">Skip</button>
            </div>

            <div class="wpil-feedback-thanks" hidden>
                Thanks! You’re helping make Link Whisper better. 💙
            </div>
            </div>
        </div>
        </div>


  <?php endif; ?>

  <!-- Quick Actions + Features Combined -->
  <div class="actions-features-combined">
    <div class="combined-section">
      <h3 class="section-title">Quick Actions</h3>
      <div class="actions-list-vertical">
        <?php foreach($quick_action_items as $action): ?>
        <a class="action-row" href="<?php echo esc_url($action['url']); ?>">
          <div class="action-icon-sm"><span class="dashicons dashicons-<?php echo esc_attr($action['icon']); ?>"></span></div>
          <div class="action-text"><?php echo esc_html($action['label']); ?></div>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="combined-section">
      <h3 class="section-title"><?php echo esc_html($feature_section_title); ?></h3>
      <div class="features-list-vertical">
        <?php if(!empty($feature_cards)): ?>
        <?php foreach($feature_cards as $feature): ?>
        <div class="feature-row">
          <div class="feature-info">
            <div class="feature-name"><?php echo esc_html($feature['name']); ?></div>
            <div class="feature-hint"><?php echo esc_html($feature['hint']); ?></div>
          </div>
          <a class="feature-btn-sm" href="<?php echo esc_url($feature['url']); ?>"><?php echo esc_html($feature['cta']); ?></a>
        </div>
        <?php endforeach; ?>
        <?php else: ?>
        <div class="feature-row">
          <div class="feature-info">
            <div class="feature-name"><?php echo esc_html($feature_empty_message); ?></div>
            <div class="feature-hint">No action needed right now.</div>
          </div>
        </div>
        <?php endif; ?>
        <!-- TODO: replace with actual unused-feature detection -->
      </div>
    </div>
  </div>

  <div
    class="ai-usage-section"
    id="wpil-ai-usage-history"
    tabindex="-1"
    data-wpil-ai-history-shell
    data-nonce="<?php echo esc_attr($ai_usage_nonce); ?>"
    data-default-from="<?php echo esc_attr($ai_usage_defaults['from']); ?>"
    data-default-to="<?php echo esc_attr($ai_usage_defaults['to']); ?>">
    <button class="ai-usage-toggle" style="padding: 20px !important;" type="button" data-wpil-ai-history-toggle aria-expanded="false" aria-controls="wpil-ai-usage-history-body">
      <span class="ai-usage-title"><?php esc_html_e('AI Credit History', 'wpil'); ?></span>
      <span class="dashicons dashicons-arrow-down-alt2 ai-usage-toggle-icon" aria-hidden="true"></span>
    </button>
    <div class="ai-usage-shell-body" id="wpil-ai-usage-history-body" data-wpil-ai-history-body hidden></div>
  </div>
</div>
</div>

<script>
const wpilDashboardGreetingPart = document.getElementById('wpil-dashboard-greeting-part');
if (wpilDashboardGreetingPart) {
  const hour = new Date().getHours();
  let part = 'morning';
  if (hour >= 12 && hour < 18) part = 'afternoon';
  if (hour >= 18) part = 'evening';
  wpilDashboardGreetingPart.textContent = part;
}

const wpilV3Notifications = {
  loadedLogged: false,
  impressionsLogged: false,
  seenMarked: false,
  unreadCount: <?php echo (int)$notification_unread_count; ?>,
  nonce: '<?php echo esc_js($notification_seen_nonce); ?>'
};

function wpilV3LogNotificationTelemetry(dropdown) {
  if (!window.wpilTelemetry || !dropdown) { return; }

  const items = dropdown.querySelectorAll('.notification-item[data-wpil-notification-id]');

  if (!wpilV3Notifications.loadedLogged) {
    window.wpilTelemetry.logNotificationHubLoaded(items.length, wpilV3Notifications.unreadCount);
    wpilV3Notifications.loadedLogged = true;
  }

  if (!wpilV3Notifications.impressionsLogged) {
    items.forEach(function(item, index) {
      const notificationId = item.getAttribute('data-wpil-notification-id') || ('notification_' + index);
      const position = parseInt(item.getAttribute('data-wpil-position') || index, 10);
      window.wpilTelemetry.logNotificationHubNotificationImpression(notificationId, position);
    });
    wpilV3Notifications.impressionsLogged = true;
  }
}

function wpilV3MarkNotificationsSeen(dropdown) {
  if (wpilV3Notifications.seenMarked || !dropdown || typeof ajaxurl === 'undefined') { return; }

  const unseenItems = dropdown.querySelectorAll('.notification-item[data-wpil-seen="0"]');
  if (!unseenItems.length) { return; }

  const keys = [];
  unseenItems.forEach(function(item) {
    const key = item.getAttribute('data-wpil-notification-id');
    if (key) {
      keys.push(key);
      item.setAttribute('data-wpil-seen', '1');
    }
  });

  if (!keys.length) { return; }

  if (typeof jQuery === 'undefined' || !jQuery.post) {
    return;
  }

  jQuery.post(ajaxurl, {
    action: 'wpil_mark_dashboard_notifications_seen',
    nonce: wpilV3Notifications.nonce,
    keys: JSON.stringify(keys)
  }).always(function() {
    wpilV3Notifications.seenMarked = true;
    wpilV3Notifications.unreadCount = 0;
    const badge = document.getElementById('wpilV3NotificationBadge');
    if (badge) {
      badge.textContent = '0';
      badge.style.display = 'none';
    }
  });
}

function toggleNotifications() {
  const dropdown = document.getElementById('notificationDropdown');
  if (!dropdown) { return; }
  dropdown.classList.toggle('active');
  if (dropdown.classList.contains('active')) {
    wpilV3LogNotificationTelemetry(dropdown);
    wpilV3MarkNotificationsSeen(dropdown);
  }
}

function toggleQualityBreakdown() {
  const breakdown = document.getElementById('qualityBreakdown');
  if (!breakdown) { return; }
  const isVisible = breakdown.style.display !== 'none';
  if (isVisible) {
    breakdown.style.display = 'none';
  } else {
    breakdown.style.display = 'grid';
    setTimeout(() => {
      breakdown.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }, 100);
  }
}

document.addEventListener('click', function(event) {
  const bell = document.querySelector('.notification-bell');
  const dropdown = document.getElementById('notificationDropdown');
  if (!bell || !dropdown) { return; }
  if (!bell.contains(event.target) && !dropdown.contains(event.target)) {
    dropdown.classList.remove('active');
  }
});

document.addEventListener('click', function(event) {
  const target = event.target.closest('.notification-item[data-wpil-notification-id]');
  if (!target || !window.wpilTelemetry) { return; }
  const notificationId = target.getAttribute('data-wpil-notification-id');
  if (notificationId) {
    window.wpilTelemetry.logNotificationHubNotificationClicked(notificationId);
  }
});
</script>

<?php if($show_ai_fix_controls){ ?>
<?php include_once 'fix-modal.php'; ?>
<?php } ?>
<?php include_once 'wizard/credits-modal.php'; ?>
<?php if($show_ai_fix_controls){ ?>
<div id="wpil-v3-fix-progress-modal" aria-hidden="true">
  <div class="wpil-v3-progress-backdrop" data-wpil-v3-progress-close="1"></div>
  <div class="wpil-v3-progress-panel" role="dialog" aria-modal="true" aria-label="AI Fix Progress">
    <h4 id="wpil-v3-progress-title">AI Fix In Progress</h4>
    <p id="wpil-v3-progress-description">Link Whisper is running your AI fix now.</p>
    <div class="wpil-v3-progress-bar"><span id="wpil-v3-progress-bar-fill"></span></div>
    <div class="wpil-v3-progress-meta">
      <span id="wpil-v3-progress-text">Starting...</span>
      <span id="wpil-v3-progress-percent">0%</span>
    </div>
    <input type="hidden" id="wpil-link-mode" value="auto">
    <input type="hidden" id="wpil-scanning-nonce" value="<?php echo esc_attr(wp_create_nonce(get_current_user_id() . 'wizard-scanning-nonce')); ?>">
    <input type="hidden" id="wpil-ai-linking-complete" value="0">
    <input type="hidden" id="wpil-ai-linking-running" value="0">
    <input type="hidden" id="wpil-review-process-key" value="">
    <input type="hidden" id="wpil-review-fix-type" value="">
    <div id="wpil-manual-review-section" class="wpil-v3-manual-review hidden">
      <p class="wpil-v3-manual-review-title">Review AI Link Suggestions (<span data-role="review-ready-count">0</span> ready)</p>
      <button type="button" id="wpil-review-open" class="button button-secondary">
        <span data-role="review-button-label">Review</span>
        <svg data-role="review-button-spinner" class="hidden wpil-spin" width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" opacity="0.25"></circle>
          <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path>
        </svg>
      </button>
    </div>
    <div class="wpil-v3-insertion-mode wpil-wizard-linking-mode-buttons">
      <p class="wpil-v3-insertion-mode-title">Insertion mode</p>
      <label>
        <input type="radio" name="wpil-ai-linking-mode" id="wpil-ai-linking-mode-review" value="review">
        <span>Review before inserting</span>
      </label>
      <label>
        <input type="radio" name="wpil-ai-linking-mode" id="wpil-ai-linking-mode-auto" value="auto" checked>
        <span>Insert automatically</span>
      </label>
    </div>
    <div class="wpil-v3-progress-foot">
      <small>You can close this modal and the process will keep running in the background.</small>
      <div>
        <button class="button button-secondary" type="button" data-wpil-v3-progress-cancel="1">Cancel Fix</button>
        <button class="button" type="button" data-wpil-v3-progress-close="1">Close</button>
      </div>
    </div>
  </div>
</div>
<?php include_once 'wizard/manual-review.php'; ?>
<?php } ?>
<script>
  window.WPIL_AI_CREDITS = <?php echo (int) Wpil_AI::get_available_ai_credits(); ?>;
  window.WPIL_AI_FIX_NONCE = '<?php echo esc_js(wp_create_nonce('wpil_ai_fix_nonce')); ?>';
  window.WPIL_RUNNING_FIX_JOBS = <?php echo wp_json_encode($show_ai_fix_controls ? $running_fix_jobs : (object) []); ?>;
  window.WPIL_AI_FIX_SPECIAL_OPTIONS = <?php echo wp_json_encode($show_ai_fix_controls ? Wpil_Settings::get_ai_fix_special_options() : (object) []); ?>;
  window.WPIL_LINK_DELAY_WAITLIST_NONCE = '<?php echo esc_js($waitlist_signup_nonce); ?>';
  window.WPIL_DASHBOARD_EXPERIENCE_FEEDBACK_NONCE = '<?php echo esc_js($experience_feedback_nonce); ?>';
  window.WPIL_DASHBOARD_PROCESS_KEYS = <?php echo $show_ai_fix_controls ? wp_json_encode(array(
    'orphaned_posts' => md5('orphan-post-search'),
    'link_coverage' => md5('link-coverage-search'),
    'link_quality' => md5('link-quality-search'),
    'broken_links' => md5('broken-link-search'),
    'external_focus' => md5('external-focus-search')
  )) : wp_json_encode((object) []); ?>;
</script>
<script>
(function() {
  let lastFixContext = null;
  let currentProgressContext = null;
  const fixButtonWaiters = {};
  const lastReviewCountPollByKey = {};

  function wpilParseInt(val) {
    if (val === undefined || val === null) return 0;
    const s = String(val).replace(/,/g, '').trim();
    const n = parseInt(s, 10);
    return isNaN(n) ? 0 : n;
  }

  function wpilFormatInt(n) {
    return wpilParseInt(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
  }

  function isSameFixContext(a, b){
    if(!a || !b){ return false; }
    const aType = (a.type || '').toString();
    const bType = (b.type || '').toString();
    const aItem = (a.itemId === undefined || a.itemId === null) ? '' : String(a.itemId);
    const bItem = (b.itemId === undefined || b.itemId === null) ? '' : String(b.itemId);
    return aType !== '' && aType === bType && aItem === bItem;
  }

  function getFixWaiterKey(ctx, scope){
    if(!ctx || !ctx.type){
      return '';
    }

    const itemId = (ctx.itemId === undefined || ctx.itemId === null) ? '' : String(ctx.itemId);
    return (scope || 'fix') + ':' + String(ctx.type) + ':' + itemId;
  }

  function claimFixWaiter(ctx, scope, ttl){
    const key = getFixWaiterKey(ctx, scope);
    if(!key){
      return false;
    }

    const now = Date.now();
    if(fixButtonWaiters[key] && fixButtonWaiters[key] > now){
      return false;
    }

    fixButtonWaiters[key] = now + Math.max(250, wpilParseInt(ttl || 0));
    return true;
  }

  function releaseFixWaiter(ctx, scope){
    const key = getFixWaiterKey(ctx, scope);
    if(key && fixButtonWaiters[key]){
      delete fixButtonWaiters[key];
    }
  }

  function getFixButtons(ctx){
    if(!ctx || !ctx.type){ return []; }
    const itemId = (ctx.itemId || '');
    let selector = '[data-wpil-fix-type="' + ctx.type + '"]';
    if(itemId !== ''){
      selector += '[data-wpil-fix-item-id="' + itemId + '"]';
    }
    return Array.prototype.slice.call(document.querySelectorAll(selector));
  }

  function getFixCancelButtons(ctx){
    if(!ctx || !ctx.type){ return []; }
    const itemId = (ctx.itemId || '');
    let selector = '[data-wpil-fix-cancel-inline="' + ctx.type + '"]';
    if(itemId !== ''){
      selector += '[data-wpil-fix-cancel-item-id="' + itemId + '"]';
    }
    return Array.prototype.slice.call(document.querySelectorAll(selector));
  }

  function setFixButtonsRunningState(ctx, running){
    const buttons = getFixButtons(ctx);
    buttons.forEach(function(btn){
      if(!btn.dataset.wpilFixOrigLabel){
        btn.dataset.wpilFixOrigLabel = (btn.textContent || '').trim() || 'Fix with AI';
      }
      if(running){
        btn.textContent = 'Review Progress';
      }else{
        btn.textContent = btn.dataset.wpilFixOrigLabel || 'Fix with AI';
      }
    });

    const cancelButtons = getFixCancelButtons(ctx);
    cancelButtons.forEach(function(btn){
      btn.classList.toggle('is-hidden', !running);
      btn.disabled = !running;
    });
  }

  function setAll(selector, value) {
    document.querySelectorAll(selector).forEach(function(el) {
      el.textContent = value;
    });
  }

  function setAllInFixModal(selector, value) {
    const modal = document.getElementById('wpil-fix-modal');
    if (!modal) { return; }
    modal.querySelectorAll(selector).forEach(function(el) {
      el.textContent = value;
    });
  }

  function padShortfall(shortfall) {
    let padded = Math.ceil(shortfall * 1.25);
    padded = Math.round(padded / 100) * 100;
    if (padded < shortfall) padded += 100;
    if (padded < 500) padded = 500;
    return padded;
  }

  function canShowFixSpecialOptions(type){
    return type === 'orphaned_posts' || type === 'link_coverage' || type === 'link_quality';
  }

  function getDefaultFixSpecialOptions(){
    return {
      link_to_category_pages: 0,
      link_from_category_pages: 0,
      select_post_types: 0,
      selected_post_types: [],
      same_category: 0
    };
  }

  function getFixSpecialOptionsFromWindow(){
    const defaults = getDefaultFixSpecialOptions();
    const source = (window.WPIL_AI_FIX_SPECIAL_OPTIONS && typeof window.WPIL_AI_FIX_SPECIAL_OPTIONS === 'object')
      ? window.WPIL_AI_FIX_SPECIAL_OPTIONS
      : {};

    const selected = Array.isArray(source.selected_post_types) ? source.selected_post_types : [];
    return {
      link_to_category_pages: source.link_to_category_pages ? 1 : 0,
      link_from_category_pages: source.link_from_category_pages ? 1 : 0,
      select_post_types: source.select_post_types ? 1 : 0,
      selected_post_types: selected.map(function(val){ return String(val); }),
      same_category: source.same_category ? 1 : 0
    };
  }

  function writeFixSpecialOptionsToUi(opts){
    const options = opts || getDefaultFixSpecialOptions();
    const panel = document.querySelector('[data-wpil-fix-special-options]');
    if(!panel){ return; }

    panel.querySelectorAll('input[data-wpil-fix-option]').forEach(function(input){
      const key = input.getAttribute('data-wpil-fix-option');
      input.checked = !!options[key];
    });

    const select = panel.querySelector('select[data-wpil-fix-option="selected_post_types"]');
    if(select){
      const selected = Array.isArray(options.selected_post_types) ? options.selected_post_types : [];
      Array.prototype.slice.call(select.options).forEach(function(opt){
        opt.selected = selected.indexOf(String(opt.value)) !== -1;
      });
    }

    const wrap = panel.querySelector('[data-wpil-fix-post-type-wrap]');
    if(wrap){
      wrap.classList.toggle('is-active', !!options.select_post_types);
    }

    initFixSpecialPostTypeSelect();
  }

  function readFixSpecialOptionsFromUi(){
    const options = getDefaultFixSpecialOptions();
    const panel = document.querySelector('[data-wpil-fix-special-options]');
    if(!panel){ return options; }

    panel.querySelectorAll('input[data-wpil-fix-option]').forEach(function(input){
      const key = input.getAttribute('data-wpil-fix-option');
      options[key] = input.checked ? 1 : 0;
    });

    const select = panel.querySelector('select[data-wpil-fix-option="selected_post_types"]');
    if(select){
      options.selected_post_types = Array.prototype.slice.call(select.selectedOptions).map(function(opt){
        return String(opt.value);
      });
    }

    return options;
  }

  function initFixSpecialPostTypeSelect(){
    if(!window.jQuery){
      return;
    }

    const $panel = jQuery('[data-wpil-fix-special-options]');
    if($panel.length < 1){
      return;
    }

    const $select = $panel.find('select[data-wpil-fix-option="selected_post_types"]');
    if($select.length < 1){
      return;
    }

    if(typeof jQuery.fn.select2 === 'function'){
      if(!$select.hasClass('select2-hidden-accessible')){
        $select.select2({
          width: '100%',
          dropdownParent: $panel
        });
      }else{
        $select.trigger('change.select2');
      }
    }
  }

  function setReviewLinkingComplete(isComplete){
    const input = document.getElementById('wpil-ai-linking-complete');
    if(!input){ return; }
    input.value = isComplete ? '1' : '0';
  }

  function setReviewLinkingRunning(isRunning){
    const input = document.getElementById('wpil-ai-linking-running');
    if(!input){ return; }
    input.value = isRunning ? '1' : '0';
  }

  function getDashboardProcessKey(type){
    const map = (window.WPIL_DASHBOARD_PROCESS_KEYS && typeof window.WPIL_DASHBOARD_PROCESS_KEYS === 'object')
      ? window.WPIL_DASHBOARD_PROCESS_KEYS
      : {};
    if(type && map[type]){
      return String(map[type]);
    }
    return '';
  }

  function setReviewProcessKey(processKey){
    const key = (processKey || '').toString();
    const input = document.getElementById('wpil-review-process-key');
    if(input){
      input.value = key;
    }
    if(window.wpilReview && typeof window.wpilReview === 'object'){
      window.wpilReview.process_key = key;
    }
  }

  function setReviewFixType(fixType){
    const type = (fixType || '').toString();
    const input = document.getElementById('wpil-review-fix-type');
    if(input){
      input.value = type;
    }
    if(!window.wpilReview || typeof window.wpilReview !== 'object'){
      window.wpilReview = {};
    }
    window.wpilReview.fix_type = type;
  }

  function getLinkMode(){
    const selectedRadio = document.querySelector('input[name="wpil-ai-linking-mode"]:checked');
    if(selectedRadio && selectedRadio.value){
      return selectedRadio.value === 'auto' ? 'auto' : 'review';
    }

    const input = document.getElementById('wpil-link-mode');
    if(!input || !input.value){
      return 'auto';
    }
    return input.value === 'auto' ? 'auto' : 'review';
  }

  function setLinkMode(mode){
    const normalized = (mode === 'auto') ? 'auto' : 'review';
    const input = document.getElementById('wpil-link-mode');
    const auto = document.getElementById('wpil-ai-linking-mode-auto');
    const review = document.getElementById('wpil-ai-linking-mode-review');
    const reviewSection = document.getElementById('wpil-manual-review-section');

    if(input){
      input.value = normalized;
    }
    if(auto){
      auto.checked = (normalized === 'auto');
    }
    if(review){
      review.checked = (normalized === 'review');
    }
    if(reviewSection){
      reviewSection.classList.toggle('hidden', normalized !== 'review');
    }
    try{
      window.localStorage.setItem('wpil_dashboard_link_mode', normalized);
    }catch(e){}
  }

  function hydrateLinkModePreference(){
    let mode = '';
    try{
      mode = (window.localStorage.getItem('wpil_dashboard_link_mode') || '').toString();
    }catch(e){}
    if(mode !== 'auto' && mode !== 'review'){
      mode = getLinkMode();
    }
    setLinkMode(mode);
  }

  function getReviewNonce(){
    const input = document.getElementById('wpil-scanning-nonce');
    return input ? input.value : '';
  }

  function isReviewCountPollingAllowed(ctx, data){
    const runner = getAiFixRunner();
    const job = runner ? runner.getJob(ctx || {}) : null;
    const status = data && data.status ? String(data.status) : (job && job.lastData && job.lastData.status ? String(job.lastData.status) : '');

    return status === 'running';
  }

  function syncReviewButtonState(total){
    const count = Math.max(0, wpilParseInt(total));
    const reviewButton = document.getElementById('wpil-review-open');
    const reviewButtonLabel = reviewButton ? reviewButton.querySelector('[data-role="review-button-label"]') : null;
    const reviewButtonSpinner = reviewButton ? reviewButton.querySelector('[data-role="review-button-spinner"]') : null;

    document.querySelectorAll('[data-role="review-ready-count"]').forEach(function(el){
      el.textContent = count;
    });

    if(!reviewButton){
      return;
    }

    reviewButton.disabled = (count < 1);
    reviewButton.classList.toggle('opacity-50', count < 1);
    reviewButton.classList.toggle('cursor-not-allowed', count < 1);

    if(reviewButtonLabel){
      reviewButtonLabel.textContent = 'Review';
    }

    if(reviewButtonSpinner && count > 0){
      reviewButtonSpinner.classList.add('hidden');
    }
  }

  function updateReviewCountForProcessKey(processKey, fixType){
    if(!window.jQuery || typeof ajaxurl === 'undefined'){
      return;
    }
    jQuery.ajax({
      type: 'POST',
      url: ajaxurl,
      dataType: 'json',
      data: {
        action: 'wpil_get_review_link_count',
        nonce: getReviewNonce(),
        process_key: processKey || '',
        fix_type: fixType || ''
      },
      success: function(resp){
        if(!resp || !resp.success || !resp.data){
          return;
        }
        const total = wpilParseInt(resp.data.remaining);
        syncReviewButtonState(total);
      }
    });
  }

  function maybePollReviewCount(processKey, force, fixType){
    if(!processKey){
      return;
    }
    const pollKey = (fixType || '') + '|' + String(processKey);
    const now = Date.now();
    if(!force && lastReviewCountPollByKey[pollKey] && (now - lastReviewCountPollByKey[pollKey]) < 5000){
      return;
    }
    lastReviewCountPollByKey[pollKey] = now;
    updateReviewCountForProcessKey(processKey, fixType || '');
  }

  function openProgressModal(ctx){
    const modal = document.getElementById('wpil-v3-fix-progress-modal');
    if(!modal){ return; }
    currentProgressContext = ctx ? {
      type: ctx.type || '',
      itemId: (ctx.itemId !== undefined && ctx.itemId !== null) ? String(ctx.itemId) : '',
      estimate: wpilParseInt(ctx.estimate),
      processKey: (ctx.processKey || '')
    } : null;
    const resolvedProcessKey = (ctx && ctx.processKey) ? ctx.processKey : getDashboardProcessKey(ctx ? ctx.type : '');
    if(ctx){
      ctx.processKey = resolvedProcessKey;
    }
    if(currentProgressContext){
      currentProgressContext.processKey = resolvedProcessKey;
    }
    setReviewFixType(ctx ? (ctx.type || '') : '');
    setReviewProcessKey(resolvedProcessKey);
    if(resolvedProcessKey){
      maybePollReviewCount(resolvedProcessKey, true, ctx ? (ctx.type || '') : '');
    }else{
      syncReviewButtonState(0);
    }
    const titleMap = {
      orphaned_posts: 'Fixing Orphaned Posts',
      link_coverage: 'Fixing Link Coverage',
      link_quality: 'Fixing Link Quality',
      broken_links: 'Fixing Broken Links',
      external_focus: 'Fixing External Focus'
    };
    document.getElementById('wpil-v3-progress-title').textContent = titleMap[ctx.type] || 'AI Fix In Progress';
    document.getElementById('wpil-v3-progress-description').textContent = 'Link Whisper is applying AI updates now. You can close this modal and the process will keep running.';
    const runner = getAiFixRunner();
    const job = runner ? runner.getJob(ctx) : null;
    setProgressModalRunningState(!job || !job.lastData || (job.lastData.status || 'running') === 'running');
    if(job && job.lastData){
      updateProgressModal(job.lastData);
    }

    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
  }

  function closeProgressModal(){
    const modal = document.getElementById('wpil-v3-fix-progress-modal');
    if(!modal){ return; }
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
  }

  function setProgressModalRunningState(isRunning){
    const modal = document.getElementById('wpil-v3-fix-progress-modal');
    if(!modal){ return; }

    const progressBar = modal.querySelector('.wpil-v3-progress-bar');
    const progressMeta = modal.querySelector('.wpil-v3-progress-meta');
    const insertionMode = modal.querySelector('.wpil-v3-insertion-mode');
    const cancelButton = modal.querySelector('[data-wpil-v3-progress-cancel]');
    const reviewSpinner = modal.querySelector('[data-role="review-button-spinner"]');

    if(progressBar){ progressBar.classList.toggle('hidden', !isRunning); }
    if(progressMeta){ progressMeta.classList.toggle('hidden', !isRunning); }
    if(insertionMode){ insertionMode.classList.toggle('hidden', !isRunning); }
    if(cancelButton){ cancelButton.classList.toggle('hidden', !isRunning); }
    if(!isRunning && reviewSpinner){ reviewSpinner.classList.add('hidden'); }
  }

  function updateProgressModal(data){
    const status = data && data.status ? String(data.status) : 'running';
    setProgressModalRunningState(status === 'running');
    const pct = Math.max(0, Math.min(100, wpilParseInt(data.progress)));
    const fill = document.getElementById('wpil-v3-progress-bar-fill');
    const txt = document.getElementById('wpil-v3-progress-text');
    const per = document.getElementById('wpil-v3-progress-percent');
    if(fill){ fill.style.width = pct + '%'; }
    if(txt){ txt.textContent = data.message || ('Fixing ' + pct + '%'); }
    if(per){ per.textContent = pct + '%'; }
  }

  function setInlineIndicator(type, active, progress, message){
    const indicator = document.querySelector('[data-wpil-fix-indicator="' + type + '"]');
    if(!indicator){ return; }
    indicator.classList.toggle('is-active', !!active);

    const bar = indicator.querySelector('[data-wpil-fix-progress-bar="' + type + '"]');
    const text = indicator.querySelector('[data-wpil-fix-progress-text="' + type + '"]');
    const pct = Math.max(0, Math.min(100, wpilParseInt(progress)));
    if(bar){ bar.style.width = pct + '%'; }
    if(text){ text.textContent = message || ('Fixing ' + pct + '%'); }
  }

  function applyDashboardMetric(metricKey, metricData){
    if(!window.jQuery || !metricKey || !metricData){
      return;
    }

    var $value = jQuery('[data-wpil-metric-value="' + metricKey + '"]');
    var $number = jQuery('[data-wpil-metric-number="' + metricKey + '"]');
    var $suffix = jQuery('[data-wpil-metric-suffix="' + metricKey + '"]');
    var $status = jQuery('[data-wpil-metric-status="' + metricKey + '"]');
    var $description = jQuery('[data-wpil-metric-description="' + metricKey + '"]');

    if($number.length){
      $number.text(metricData.formatted_value || '');
    }
    if($suffix.length){
      $suffix.text(metricData.suffix || '');
    }
    if($value.length && metricData.status_color){
      $value.css('color', metricData.status_color);
    }
    if($status.length){
      $status
        .removeClass('status-good status-warning status-poor')
        .addClass(metricData.status_class || '')
        .text(metricData.status_label || '');
    }
    if($description.length){
      $description.text(metricData.description || '');
    }
  }

  function applyDashboardMetrics(metrics){
    if(!metrics){
      return;
    }

    applyDashboardMetric('site_health', metrics.site_health || null);
    applyDashboardMetric('link_quality', metrics.link_quality || null);
    applyDashboardMetric('link_coverage', metrics.link_coverage || null);
    applyDashboardMetric('orphaned_posts', metrics.orphaned_posts || null);
  }

  function getAiFixRunner(){
    return (window.wpilAiFixRunner && typeof window.wpilAiFixRunner === 'object')
      ? window.wpilAiFixRunner
      : null;
  }

  function configureAiFixRunner(){
    const runner = getAiFixRunner();
    if(!runner){
      return null;
    }

    runner.configure({
      getProcessKey: function(ctx){
        return getDashboardProcessKey(ctx ? ctx.type : '');
      },
      getLinkMode: function(){
        return getLinkMode();
      },
      getSpecialOptions: function(){
        return readFixSpecialOptionsFromUi();
      }
    });

    return runner;
  }

  function startFixProcess(ctx){
    const runner = configureAiFixRunner();
    if(!runner || !ctx || !ctx.type){
      return null;
    }

    return runner.start(ctx);
  }

  function checkExistingJobStatus(ctx, callback){
    const runner = configureAiFixRunner();
    if(!runner || !ctx || !ctx.type){
      if(typeof callback === 'function'){
        callback(null, { message: 'Runner unavailable', retryable: false });
      }
      return;
    }

    runner.checkStatus(ctx, function(result){
      if(typeof callback !== 'function'){
        return;
      }

      if(result && result.success && result.data){
        callback(result.data, null);
        return;
      }

      callback(null, result ? result.error : null);
    });
  }

  function cancelFixByType(type, itemId){
    const runner = configureAiFixRunner();
    if(!runner || !type){
      return;
    }

    runner.cancelByType(type, itemId, function(result){
      if(!result || !result.success){
        return;
      }

      if(getLinkMode() === 'auto'){
        closeProgressModal();
      }
    });
  }

  function handleFixProcessUpdate(ctx, data){
    if(!ctx || !ctx.type || !data){
      return;
    }

    const previousProcessKey = ctx.processKey ? String(ctx.processKey) : '';
    applyDashboardMetrics(data.dashboard_metrics || null);

    if(data.process_key){
      ctx.processKey = String(data.process_key);
    }

    const resolvedProcessKey = (ctx.processKey || getDashboardProcessKey(ctx.type || ''));
    if(isSameFixContext(currentProgressContext, ctx)){
      currentProgressContext.processKey = resolvedProcessKey;
      setReviewFixType(ctx.type || '');
      setReviewProcessKey(resolvedProcessKey);
      if(isReviewCountPollingAllowed(ctx, data)){
        maybePollReviewCount(resolvedProcessKey, (!!data.process_key && previousProcessKey !== String(data.process_key)), ctx.type || '');
      }
      updateProgressModal(data);
    }

    const inlineMessage = data.status === 'complete'
      ? 'Complete'
      : (data.status === 'cancelled'
        ? 'Cancelled'
        : (data.message || ('Fixing ' + wpilParseInt(data.progress) + '%')));
    setFixButtonsRunningState(ctx, data.status === 'running');
    setInlineIndicator(ctx.type, data.status === 'running', data.progress, inlineMessage);
  }

  function handleFixProcessFinish(ctx, data){
    if(!ctx || !ctx.type){
      return;
    }

    const finalData = data || { status: 'idle', progress: 0, message: '' };
    const resolvedProcessKey = (finalData.process_key || ctx.processKey || getDashboardProcessKey(ctx.type || ''));
    applyDashboardMetrics(finalData.dashboard_metrics || null);
    setFixButtonsRunningState(ctx, false);
    setInlineIndicator(ctx.type, false, finalData.progress || 0, finalData.message || '');

    if(finalData.status === 'complete'){
      setReviewLinkingComplete(true);
      updateProgressModal({
        status: 'complete',
        progress: 100,
        message: finalData.message || 'Fix complete'
      });
    }else if(finalData.status === 'cancelled'){
      setReviewLinkingComplete(false);
      updateProgressModal({
        status: 'cancelled',
        progress: finalData.progress || 0,
        message: 'Fix cancelled'
      });
    }else if(finalData.status === 'error'){
      setReviewLinkingComplete(false);
      updateProgressModal({
        status: 'error',
        progress: finalData.progress || 0,
        message: finalData.message || 'Fix failed'
      });
    }else{
      setReviewLinkingComplete(false);
    }

    if(resolvedProcessKey){
      updateReviewCountForProcessKey(resolvedProcessKey, ctx.type || '');
    }else{
      syncReviewButtonState(0);
    }

    if(currentProgressContext && isSameFixContext(currentProgressContext, ctx)){
      currentProgressContext = null;
    }
  }

  function handleFixProcessError(ctx, error, job){
    if(!ctx || !ctx.type){
      return;
    }

    const progress = job && job.lastData ? wpilParseInt(job.lastData.progress) : 0;
    const message = (error && error.message) ? error.message : 'Fix failed';

    if(error && error.retryable){
      setFixButtonsRunningState(ctx, true);
      setInlineIndicator(ctx.type, true, progress, message);
      if(isSameFixContext(currentProgressContext, ctx)){
        updateProgressModal({
          status: 'running',
          progress: progress,
          message: message
        });
      }
      return;
    }

    setFixButtonsRunningState(ctx, false);
    setInlineIndicator(ctx.type, false, progress, message);
    if(isSameFixContext(currentProgressContext, ctx)){
      updateProgressModal({
        status: 'error',
        progress: progress,
        message: message
      });
      currentProgressContext = null;
    }
  }

  if(window.jQuery){
    configureAiFixRunner();

    jQuery(document).on('wpil:fix_started', function(event, ctx){
      if(!ctx || !ctx.type){
        return;
      }

      setReviewLinkingRunning(true);
      setReviewLinkingComplete(false);
      setFixButtonsRunningState(ctx, true);
      setInlineIndicator(ctx.type, true, 0, 'Starting...');
      if(isSameFixContext(currentProgressContext, ctx)){
        setProgressModalRunningState(true);
        updateProgressModal({ status: 'running', progress: 0, message: 'Starting...' });
      }
    });

    jQuery(document).on('wpil:fix_updated', function(event, ctx, data){
      handleFixProcessUpdate(ctx, data);
    });

    jQuery(document).on('wpil:fix_finished', function(event, ctx, data){
      setReviewLinkingRunning(false);
      handleFixProcessFinish(ctx, data);
    });

    jQuery(document).on('wpil:fix_error', function(event, ctx, error, job){
      setReviewLinkingRunning(false);
      handleFixProcessError(ctx, error, job);
    });
  }

  function openFixModal(ctx) {
    lastFixContext = ctx;

    const fixCopy = {
        broken_links: {
            title: 'Fix Broken Links with AI',
            metaLabel: 'Broken links found',
            bullets: [
            'Finds broken URLs and updates them to working destinations.',
            'Keeps your content readable and avoids dead ends for visitors.',
            'You can review progress anytime while it runs.'
            ],
            note: 'Nothing runs until you click “Start AI Fix”.'
        },
        orphaned_posts: {
            title: 'Add Links to Orphaned Posts',
            metaLabel: 'Orphaned posts',
            bullets: [
            'Adds a few relevant internal links pointing to each orphaned post.',
            'Places links naturally inside existing content.',
            'Respects your Link Whisper settings and link limits.'
            ],
            note: 'Tip: you can close the progress window and keep working. The fix continues in the background.'
        },
        link_coverage: {
            title: 'Improve Link Coverage',
            metaLabel: 'Coverage score',
            bullets: [
            'Adds internal links to pages that are missing key internal connections.',
            'Helps bring posts closer to your inbound and outbound link targets.',
            'Avoids overlinking by respecting your limits.'
            ],
            note: 'This focuses on filling gaps, not spamming links everywhere.'
        },
        link_quality: {
            title: 'Improve Link Quality',
            metaLabel: 'Quality score',
            bullets: [
            'Finds weakly related internal links and removes the worst offenders.',
            'Replaces them with more relevant internal links where possible.',
            'Aims to improve relevance without bloating link count.'
            ],
            note: 'Best run after you have a baseline of internal links in place.'
        },
        external_focus: {
            title: 'Reduce External Link Focus',
            metaLabel: 'External focus',
            bullets: [
            'Highlights places where internal links can be prioritized over external links.',
            'Adds helpful internal links where it makes sense for users.',
            'Helps keep more link equity flowing through your own site.'
            ],
            note: 'External links are still useful. This just helps balance things.'
        }
    };
    const estimate = wpilParseInt(ctx.estimate);
    const balance = wpilParseInt(ctx.balance);

    const modal = document.getElementById('wpil-fix-modal');
    if (!modal) { return; }

    const specialPanel = modal.querySelector('[data-wpil-fix-special-options]');
    const showSpecialOptions = canShowFixSpecialOptions(ctx.type);
    if (specialPanel) {
      specialPanel.classList.toggle('is-hidden', !showSpecialOptions);
    }

    const activeSpecialOptions = getFixSpecialOptionsFromWindow();
    writeFixSpecialOptionsToUi(activeSpecialOptions);
    ctx.specialOptions = activeSpecialOptions;

    const copy = fixCopy[ctx.type] || {};
    const titleEl = modal.querySelector('.wpil-modal-panel h3');
    if (titleEl) {
        titleEl.textContent = copy.title || 'Link Whisper can fix this for you';
    }

    const desc = ctx.description || '';
    const friendlyDesc =
        desc
            ? desc
            : 'Link Whisper will apply an AI-powered fix based on your settings. You can track progress and keep working while it runs.';
        document.getElementById('wpil-fix-description').textContent = friendlyDesc;

    // Meta (count or percent)
    const metaLabel = modal.querySelector('[data-wpil-fix-meta-label]');
    const metaValue = modal.querySelector('[data-wpil-fix-meta-value]');

    let label = copy.metaLabel || 'Items';
    let value = '';

    // Prefer count when present, else show percent for score-based actions
    if (ctx.count > 0) {
        value = wpilFormatInt(ctx.count);
    } else if (ctx.percent !== '') {
        value = (String(ctx.percent).indexOf('%') !== -1) ? String(ctx.percent) : (String(ctx.percent) + '%');
    } else {
        value = 'Ready';
    }

    if (metaLabel) metaLabel.textContent = label;
    if (metaValue) metaValue.textContent = value;

    // Bullets
    const bulletsWrap = modal.querySelector('[data-wpil-fix-bullets]');
    if (bulletsWrap) {
        bulletsWrap.innerHTML = '';
        const bullets = Array.isArray(copy.bullets) ? copy.bullets : [];
        bullets.forEach(function(line){
            const li = document.createElement('li');
            li.textContent = line;
            bulletsWrap.appendChild(li);
        });
    }

    // Note
    const noteEl = modal.querySelector('[data-wpil-fix-note]');
    if (noteEl) {
        noteEl.textContent = copy.note || 'Nothing runs until you click “Start AI Fix”.';
    }

    setAllInFixModal('[data-wpil-fix-estimate]', wpilFormatInt(estimate));
    setAllInFixModal('[data-wpil-fix-balance]', wpilFormatInt(balance));

    const enoughCredits = (estimate <= 0) ? true : (balance >= estimate);

    const statusBadge = document.querySelector('[data-role="wpil-fix-status"]');
    if (statusBadge) {
      statusBadge.textContent = enoughCredits ? 'Ready' : 'Not enough credits';
      statusBadge.classList.toggle('is-bad', !enoughCredits);
    }

    const bar = document.querySelector('[data-role="wpil-fix-bar"]');
    if (bar) {
      const pct = (estimate > 0) ? Math.min(100, Math.round((balance / estimate) * 100)) : 100;
      bar.style.width = pct + '%';
    }

    document.getElementById('wpil-fix-warning').classList.toggle('hidden', enoughCredits);
    document.getElementById('wpil-fix-actions-enough').classList.toggle('hidden', !enoughCredits);
    document.getElementById('wpil-fix-actions-short').classList.toggle('hidden', enoughCredits);

    if (!enoughCredits) {
      const shortfall = Math.max(0, estimate - balance);
      const padded = padShortfall(shortfall);
      setAllInFixModal('[data-wpil-fix-shortfall]', wpilFormatInt(padded));

      const buyBtn = document.getElementById('wpil-fix-buy');
      if (buyBtn) {
        buyBtn.dataset.credits = padded;
        buyBtn.dataset.quantity = padded;
      }
    }

    modal.classList.remove('hidden');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('wpil-fix-modal-open');
  }

  function closeFixModal() {
    const modal = document.getElementById('wpil-fix-modal');
    if (!modal) { return; }
    modal.classList.add('hidden');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('wpil-fix-modal-open');
  }

  document.addEventListener('click', function(e) {
    const fixBtn = e.target.closest('[data-wpil-fix-type]');
    if (!fixBtn) return;

    e.preventDefault();

      const ctx = {
        type: fixBtn.dataset.wpilFixType,
        itemId: fixBtn.dataset.wpilFixItemId || '',
        estimate: parseInt(fixBtn.dataset.wpilFixEstimate, 10),
        processKey: getDashboardProcessKey(fixBtn.dataset.wpilFixType),
        description: fixBtn.dataset.wpilFixDescription || '',
        balance: window.WPIL_AI_CREDITS || 0,
      count: wpilParseInt(fixBtn.dataset.wpilCount),
      percent: (fixBtn.dataset.wpilPercent !== undefined) ? String(fixBtn.dataset.wpilPercent) : ''
    };

    if(!claimFixWaiter(ctx, 'open', 2500)){
      return;
    }

    const runner = configureAiFixRunner();
    const activeJob = runner ? runner.getJob(ctx) : null;
    if(activeJob && activeJob.lastData && activeJob.lastData.status === 'running'){
      releaseFixWaiter(ctx, 'open');
      if(activeJob.ctx && activeJob.ctx.processKey){
        ctx.processKey = String(activeJob.ctx.processKey);
      }
      setFixButtonsRunningState(ctx, true);
      setInlineIndicator(ctx.type, true, activeJob.lastData.progress || 0, activeJob.lastData.message || 'Fixing...');
      openProgressModal(ctx);
      return;
    }

    checkExistingJobStatus(ctx, function(statusData){
      releaseFixWaiter(ctx, 'open');
      if(statusData && statusData.status === 'running'){
        if(statusData.process_key){
          ctx.processKey = String(statusData.process_key);
        }
        if(runner){
          runner.resume(ctx, statusData, { transportMode: 'run' });
        }
        setFixButtonsRunningState(ctx, true);
        setInlineIndicator(ctx.type, true, statusData.progress || 0, statusData.message || 'Fixing...');
        openProgressModal(ctx);
        return;
      }
      openFixModal(ctx);
    });
  });

  document.querySelectorAll('[data-wpil-fix-cancel]').forEach(function(btn) {
    btn.addEventListener('click', closeFixModal);
  });

  document.addEventListener('keydown', function(e){
    if (e.key !== 'Escape') {
      return;
    }
    const modal = document.getElementById('wpil-fix-modal');
    if (modal && !modal.classList.contains('hidden')) {
      closeFixModal();
    }
  });

  document.getElementById('wpil-fix-begin')
    ?.addEventListener('click', function() {
      if(!lastFixContext || !claimFixWaiter(lastFixContext, 'start', 4000)){
        return;
      }

      if(lastFixContext){
        lastFixContext.specialOptions = readFixSpecialOptionsFromUi();
        window.WPIL_AI_FIX_SPECIAL_OPTIONS = lastFixContext.specialOptions;
        openProgressModal(lastFixContext);
      }
      closeFixModal();
      startFixProcess(lastFixContext);
      setTimeout(function(){
        releaseFixWaiter(lastFixContext, 'start');
      }, 500);
    });

  document.addEventListener('change', function(event){
    const toggle = event.target.closest('input[data-wpil-fix-option="select_post_types"]');
    if(!toggle){
      return;
    }
    const panel = document.querySelector('[data-wpil-fix-special-options]');
    if(!panel){
      return;
    }
    const wrap = panel.querySelector('[data-wpil-fix-post-type-wrap]');
    if(wrap){
      wrap.classList.toggle('is-active', !!toggle.checked);
    }
  });

  document.addEventListener('click', function(event){
    const row = event.target.closest('.wpil-fix-special-row');
    if(!row){
      return;
    }

    if(event.target.closest('input, select, option, .select2, .select2-container, .select2-selection, .select2-dropdown')){
      return;
    }

    const input = row.querySelector('input[data-wpil-fix-option]');
    if(!input){
      return;
    }

    input.checked = !input.checked;
    input.dispatchEvent(new Event('change', {bubbles: true}));
  });

  if (window.jQuery) {
    jQuery(document).on('lwcc:paid', function() {
      if (!lastFixContext) return;

      const buyBtn = document.getElementById('wpil-fix-buy');
      const purchase = wpilParseInt(buyBtn ? buyBtn.dataset.credits : 0);

      if (purchase > 0) {
        window.WPIL_AI_CREDITS = wpilParseInt(window.WPIL_AI_CREDITS) + purchase;
      }

      lastFixContext.balance = window.WPIL_AI_CREDITS || 0;
      openFixModal(lastFixContext);
    });

    jQuery(document).on('click', '.waitlist-btn', function(e){
      e.preventDefault();

      var $btn = jQuery(this);
      if($btn.prop('disabled')){
        return;
      }

      $btn.prop('disabled', true).text('Joining...');

      jQuery.post(ajaxurl, {
        action: 'wpil_link_delay_waitlist_signup',
        nonce: window.WPIL_LINK_DELAY_WAITLIST_NONCE || '',
        email: ($btn.data('email') || '')
      }).done(function(response){
        if(response && response.success){
          var $section = $btn.closest('.coming-soon-standalone');
          if($section.length){
            $section.slideUp(180, function(){ jQuery(this).remove(); });
          }
          return;
        }

        $btn.prop('disabled', false).text('Join Waitlist');
      }).fail(function(){
        $btn.prop('disabled', false).text('Join Waitlist');
      });
    });

    (function($){
        var wpilFeedbackAutoOpenTimer = null;

        // helpers
        function wpilOpenFeedback(){
            var $root = $('#wpil-feedback');
            if(!$root.length) return;
            if(wpilFeedbackAutoOpenTimer){
                clearTimeout(wpilFeedbackAutoOpenTimer);
                wpilFeedbackAutoOpenTimer = null;
            }
            $root.addClass('is-open');
            $root.find('.wpil-feedback-reopen').attr('aria-expanded', 'true');
        }

        function wpilCloseFeedback(){
            var $root = $('#wpil-feedback');
            if(!$root.length) return;
            $root.removeClass('is-open');
            $root.find('.wpil-feedback-reopen').attr('aria-expanded', 'false');
        }

        // Reopen bubble
        $(document).on('click', '#wpil-feedback .wpil-feedback-reopen', function(e){
            e.preventDefault();
            wpilOpenFeedback();
        });

        // Close button
        $(document).on('click', '#wpil-feedback .wpil-feedback-close', function(e){
            e.preventDefault();
            wpilCloseFeedback();
        });

        function wpilLockFeedback($root, locked){
            $root.find('.wpil-feedback-tab, .wpil-feedback-close, .wpil-feedback-choice, .wpil-feedback-send, .wpil-feedback-skip')
            .prop('disabled', !!locked)
            .attr('aria-disabled', locked ? 'true' : 'false');

            $root.toggleClass('is-busy', !!locked);
        }

        function wpilShowThanks($root){
            $root.find('.wpil-feedback-thanks').prop('hidden', false);
            // Optional: hide followup controls after submit
            // $root.find('.wpil-feedback-text, .wpil-feedback-actions').hide();
        }

        function wpilFadeOutRemove($root){
            $root.fadeOut(180, function(){ $(this).remove(); });
        }

        function wpilPostFeedback($root, payload){
            // hard guard: only yes/no goes through
            payload.response = (payload.response || '').toString();
            if(payload.response !== 'yes' && payload.response !== 'no'){
            return $.Deferred().reject().promise();
            }

            wpilLockFeedback($root, true);

            return $.post(ajaxurl, $.extend({
            action: 'wpil_dashboard_experience_feedback',
            nonce: window.WPIL_DASHBOARD_EXPERIENCE_FEEDBACK_NONCE || '',
            email: ($root.data('email') || '')
            }, payload))
            .done(function(result){
            if(result && result.success){
                wpilShowThanks($root);
                // keep the “thanks” visible briefly, then remove like old flow
                setTimeout(function(){ wpilFadeOutRemove($root); }, 900);
                return;
            }
            wpilLockFeedback($root, false);
            })
            .fail(function(){
            wpilLockFeedback($root, false);
            });
        }

        // Open from floating tab
        $(document).on('click', '#wpil-feedback .wpil-feedback-tab', function(e){
            e.preventDefault();
            wpilOpenFeedback();
        });

        // Close button
        $(document).on('click', '#wpil-feedback .wpil-feedback-close', function(e){
            e.preventDefault();
            wpilCloseFeedback();
        });

        // Click outside panel closes (but not when clicking inside)
        $(document).on('mousedown', function(e){
            var $root = $('#wpil-feedback');
            if(!$root.length || !$root.hasClass('is-open')) return;

            var $panel = $root.find('.wpil-feedback-panel');
            if($panel.is(e.target) || $panel.has(e.target).length) return;

            wpilCloseFeedback();
        });

        // Choice click: sets selected yes/no and reveals follow-up area (no submit yet)
        $(document).on('click', '#wpil-feedback .wpil-feedback-choice', function(e){
            e.preventDefault();

            var $root = $('#wpil-feedback');
            if(!$root.length) return;

            var response = ($(this).data('feedback-response') || '').toString();
            if(response !== 'yes' && response !== 'no') return;

            // store selection on root for later send/skip
            $root.attr('data-selected', response);

            // reveal follow-up block + update title
            var $follow = $root.find('.wpil-feedback-followup');
            var $title  = $root.find('.wpil-feedback-followup-title');

            $follow.prop('hidden', false);

            $title.text(response === 'yes'
            ? 'Awesome! What\'s your favorite part?'
            : 'Oof. What should we improve?'
            );

            // update placeholder + focus textarea
            var $ta = $root.find('.wpil-feedback-text');
            if($ta.length){
            $ta.attr('placeholder', response === 'yes'
                ? 'We really want to know what you think!'
                : 'Tell us what we should change (optional)'
            ).trigger('focus');
            }
        });

        // Send: submits yes/no + optional message
        $(document).on('click', '#wpil-feedback .wpil-feedback-send', function(e){
            e.preventDefault();

            var $root = $('#wpil-feedback');
            if(!$root.length) return;

            var response = ($root.attr('data-selected') || '').toString();
            if(response !== 'yes' && response !== 'no') return;

            var message = ($root.find('.wpil-feedback-text').val() || '').toString();

            // keep payload minimal for telemetry, but include extra context if you want it
            wpilPostFeedback($root, {
            response: response,
            message: message,
            page: window.location.href
            });
        });

        // Skip: submits yes/no (no message)
        $(document).on('click', '#wpil-feedback .wpil-feedback-skip', function(e){
            e.preventDefault();

            var $root = $('#wpil-feedback');
            if(!$root.length) return;

            var response = ($root.attr('data-selected') || '').toString();
            if(response !== 'yes' && response !== 'no') return;

            wpilPostFeedback($root, {
            response: response,
            message: '',
            page: window.location.href
            });
        });

        var $feedbackRoot = $('#wpil-feedback');
        if($feedbackRoot.length){
            wpilFeedbackAutoOpenTimer = setTimeout(function(){
                if(!$feedbackRoot.length || $feedbackRoot.hasClass('is-busy') || $feedbackRoot.hasClass('is-open')){
                    return;
                }
                wpilOpenFeedback();
            }, 12000);
        }

        })(jQuery);

  }

  document.querySelectorAll('[data-wpil-v3-progress-close]').forEach(function(btn){
    btn.addEventListener('click', closeProgressModal);
  });

  document.querySelectorAll('input[name="wpil-ai-linking-mode"]').forEach(function(input){
    input.addEventListener('change', function(){
      setLinkMode(this && this.value ? this.value : 'review');
    });
  });

  document.querySelector('[data-wpil-v3-progress-cancel]')?.addEventListener('click', function(e){
    e.preventDefault();
    if(!currentProgressContext || !currentProgressContext.type){
      return;
    }
    cancelFixByType(currentProgressContext.type, currentProgressContext.itemId || '');
  });

  document.getElementById('wpil-review-open')?.addEventListener('click', function(){
    closeProgressModal();
  });

  if(window.jQuery){
    jQuery(document).on('wpil:review_modal_close_request', function(){
      if(currentProgressContext && currentProgressContext.type){
        openProgressModal(currentProgressContext);
      }
    });
  }

  document.querySelectorAll('[data-wpil-fix-cancel-inline]').forEach(function(btn){
    btn.addEventListener('click', function(e){
      e.preventDefault();
      const type = btn.getAttribute('data-wpil-fix-cancel-inline');
      const itemId = btn.getAttribute('data-wpil-fix-cancel-item-id') || '';
      cancelFixByType(type, itemId);
    });
  });

  (function syncLinkModeInputs(){
    hydrateLinkModePreference();
  })();

    <?php if($show_ai_fix_controls){ ?>
    setTimeout(function(){
      const runner = configureAiFixRunner();
      if(!runner){
        return;
      }
      runner.hydrateRunningJobs(window.WPIL_RUNNING_FIX_JOBS || {});
      runner.hydratePendingStarts();
    }, 2000);
    <?php } ?>

})();
</script>
</div>















