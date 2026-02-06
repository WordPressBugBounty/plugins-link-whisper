<?php

/**
 * Handles the wizard functionality
 */
class Wpil_Wizard
{
    /**
     * Show wizard page
     */
    public static function init()
    {
//        include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/wizard/license.php';
//        include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/wizard/about-you.php';
//        include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/wizard/automatic-linking.php';
        include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/wizard/connect-gsc.php';
        include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/wizard/connect-openai.php';
        include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/wizard/run-setup.php';
    }

    /**
     * 
     **/
    public static function ajax_pull_loading_progress_for_dashboard(){
        Wpil_Base::verify_nonce('wpil_dashboard_loading_nonce');

        $tracked_proccesses = array(
            'post_scanning',
            'link_scanning',
            'target_keyword_scanning',
            'autolink_keyword_importing'
        );

        $data = self::pull_loading_progress_for_dashboard($tracked_proccesses);

        wp_send_json(array('data' => $data));
    }

    /**
     * @param array $searching A list of the processes that we're looking for data on
     **/
    public static function pull_loading_progress_for_dashboard($searching = array()){
        global $wpdb;
        $links_table = $wpdb->prefix . 'wpil_report_links';

        // be sure to ignore any external object caches
        Wpil_Base::ignore_external_object_cache();
        // Remove any hooks that may interfere with AJAX requests
        Wpil_Base::remove_problem_hooks();

        // todo: expand as we add more widgets to the Dashboard
        $tracked_proccesses = array( // currently just here for reference
            'post_scanning',
            'link_scanning',
            'target_keyword_scanning',
            'autolink_keyword_importing'
        );

        $results = array();

        // we need to get the estimated time remaining
        // the high level stats that have been completed
        // and then format them for easy consumption!
        $progress = get_transient('wpil_loading_progress_tracker');
/*
        $tracking = array(
            $process => array(
                'display_name' => $display_name,
                'total' => $total,
                'total_completed' => $completed,
                'start' => microtime(true),
                'runs' => array(
                    array(
                        'completed' => $completed, // #completed during this processing run
                        'remaining' => $remaining, // #remaining as of this run
                        'time' => microtime(true)
                    )
                )
            )
        );
*/
        // if we have target keywords
        if(in_array('target_keyword_scanning', $searching) && isset($progress['target_keyword_scanning'])){
            // use the data from teh processing estimate because that tends to be more accurate
            $estimated_keywords = Wpil_TargetKeyword::get_estimated_keyword_posts();
            if(!empty($estimated_keywords)){
                $progress['target_keyword_scanning'] = array_merge($progress['target_keyword_scanning'], array(
                    'total' => $estimated_keywords['total'],
                    'total_completed' => $estimated_keywords['completed']
                ));
            }
        }

        foreach($searching as $search){
            if(isset($progress[$search]) && !empty($progress[$search])){
                // try to estimate the remaining time
                $time_estimate = 0;
                if(count($progress[$search]['runs']) > 1){
                    $most_recent = array_slice($progress[$search]['runs'], -4, 4, true);
                    // determine how long it took to do the most recent runs
                    
                    $tracker = array();
                    $start = 0;
                    foreach($most_recent as $dat){
                        if(!empty($start)){
                            // get the space between the last run and this one
                            $time_difference = $dat['time'] - $start;
                            // get the number of posts completed during that time, and divide it by the time
                            $post_average = ($dat['completed'] > 0) ? $time_difference/$dat['completed']: 0;
                            // and add the average to the tracker
                            $tracker[] = $post_average;
                        }else{
                            // if this is the first pass, just set the reference time
                            $start = $dat['time'];
                        }
                    }
                    // get our average time per post
                    $average = array_sum($tracker)/count($tracker);
                    // and calculate how long it will take to finish the remaining posts at this rate
                    $time_estimate = ($progress[$search]['total'] - $progress[$search]['total_completed']) * $average;
                }

                $progress_amount = !empty($progress[$search]['total']) ? $progress[$search]['total']: 0;

                $results[$search] = array(
                    'completed' => $progress[$search]['total_completed'],
                    'percent_complete' => ($progress_amount > 0) ? (round($progress[$search]['total_completed'] / $progress_amount, 2) * 100): 0,
                    'estimated_completion_time' => $time_estimate,
                    'display_name' => $progress[$search]['display_name']
                );
                
            }else{

            }
        }

        if(in_array('post_scanning', $searching)){
            if(!isset($results['post_scanning'])){
                $results['post_scanning'] = array();
            }

            $posts_crawled = 0;
            $links_found = $wpdb->get_var("SELECT COUNT(*) FROM {$links_table}");
//            $internal_links = $wpdb->get_var("SELECT COUNT(*) FROM {$links_table} WHERE `internal` = 1");
//            $external_links = abs($links_found - $internal_links);
            $posts_crawled = $wpdb->get_var("SELECT COUNT(`post_id`) FROM (select `post_id` from {$links_table} group by `post_id`, `post_type`) a");
/*
            // get the progress from the link scan
            if(!empty($progress) && isset($progress['post_scanning'], $progress['post_scanning']['total_completed'])){
                $posts_crawled = (int)$progress['post_scanning']['total_completed'];
            }*/

//            $top_domains = $wpdb->get_results("SELECT COUNT(*) as 'link_count', `host` FROM {$links_table} GROUP BY `host` ORDER BY `link_count` DESC LIMIT 10");
//            $top_domains = (!empty($top_domains)) ? $top_domains: array();

            $link_density = Wpil_Dashboard::get_percent_of_posts_hitting_link_targets()['percent'] . '%';

            $ai_active = Wpil_Settings::can_do_ai_powered_suggestions(); // if we have a API key and at least some of the embedding data processed
            $link_relatedness = 'AI Connection Required.';
            if($ai_active){
                $link_relatedness = Wpil_Dashboard::get_related_link_percentage() . '%';
            }

            $external_link_emphasis = Wpil_Dashboard::get_external_link_distribution(1);
            $external_link_emphasis_percent = 0 . '%';
            if(!empty($external_link_emphasis) && isset($external_link_emphasis[0]->representation)){
                $external_link_emphasis_percent = (round($external_link_emphasis[0]->representation, 2) * 100) . '%';
            }

            $anchor_word_counts = Wpil_Dashboard::getAnchorPostCounts();
            $anchor_word_percent = 'Unknown';
            if(!empty($anchor_word_counts['total']) && !empty($anchor_word_counts['filtered'])){
                $percentage = $anchor_word_counts['filtered']/$anchor_word_counts['total'];
                $anchor_word_percent = (round($percentage, 2) * 100) . '%';
            }

            $results['post_scanning'] = array_merge($results['post_scanning'], array(
                'posts_crawled' => $posts_crawled,
                'links_found' => $links_found,
//                'internal_links' => $internal_links,
//                'external_links' => $external_links,
//                'top_domains' => $top_domains
                'link_coverage' => $link_density,
                'link_relation_score' => $link_relatedness,
                'external_site_focus' => $external_link_emphasis_percent,
                'anchor_quality' => $anchor_word_percent
            ));
        }

        if(!empty(get_transient('wpil_wizard_inserting_autolinks'))){
            $results['running_autolinks'] = 1;
        }

        if(!empty(get_transient('wpil_wizard_has_completed'))){
            $results['finished'] = 1;
        }
        return $results;
    }
}
?>