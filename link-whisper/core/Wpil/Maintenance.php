<?php
/**
 * Work with links and mapps them all over the site!
 * Speciifcally, thsi is what does all the mass linking actions on the site.
 */
class Wpil_Maintenance
{
    /**
     * Register services
     */
    public function register()
    {
        add_action('wp_ajax_wpil_pillar_search_posts', array(__CLASS__, 'ajax_search_pillar_posts'));
        add_action('wp_ajax_wpil_pillar_save_posts', array(__CLASS__, 'ajax_save_pillar_posts'));
        add_action('wp_ajax_wpil_get_maintenance_timers', array(__CLASS__, 'ajax_get_maintenance_timers'));
        add_action('wp_ajax_wpil_ai_fix_process', array(__CLASS__, 'ajax_ai_fix_process'));
        add_action('wp_ajax_wpil_ai_fix_preview_map', array(__CLASS__, 'ajax_ai_fix_preview_map'));
        add_action('wp_ajax_wpil_ai_fix_export_map', array(__CLASS__, 'ajax_ai_fix_export_map'));
    }

    /**
     * Init tho
     **/
    public static function init(){
        // yeah, don't exist anymore!
        /*$current_maintenance = get_option('wpil_maintenance_plan', array());
        if(empty($current_maintenance)){
            include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/maintenance/start.php';
            include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/maintenance/use-ai.php';
            include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/maintenance/connect-ai.php';
            include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/maintenance/pillars-page.php';
            include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/maintenance/intensity-check.php';
            include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/maintenance/maintenance-plan-review.php';
        }

        $title = __('Maintenance Report', 'wpil');
        $type = isset($_GET['type']) && !empty($_GET['type']) ? esc_attr($_GET['type']): false;
        include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/maintenance-report.php';*/
    }

    public static function ajax_run_maintenance(){
        // if nonce
        Wpil_Base::verify_nonce('maintenance-nonce');

        $status = self::run_maintenance();

        wp_send_json(['success' => ['data' => ['complete' => (($status) ? 1:0)]]]);
    }

    private static function get_ai_fix_process_lock_key($fix_type){
        return 'wpil_ai_fix_' . $fix_type . '_waiter_lock';
    }

    private static function get_ai_fix_cancel_marker_key($fix_type, $item_id = ''){
        return 'wpil_ai_fix_cancelled_' . md5('site:' . $fix_type . ':' . $item_id);
    }

    private static function get_recently_cancelled_ai_fix($fix_type, $item_id = ''){
        $data = get_transient(self::get_ai_fix_cancel_marker_key($fix_type, $item_id));

        return is_array($data) ? $data : array();
    }

    private static function set_recently_cancelled_ai_fix($fix_type, $item_id = '', $start_id = ''){
        set_transient(
            self::get_ai_fix_cancel_marker_key($fix_type, $item_id),
            array(
                'cancelled' => time(),
                'start_id' => is_string($start_id) ? sanitize_text_field($start_id) : '',
            ),
            10 * MINUTE_IN_SECONDS
        );
    }

    private static function clear_recently_cancelled_ai_fix($fix_type, $item_id = ''){
        delete_transient(self::get_ai_fix_cancel_marker_key($fix_type, $item_id));
    }

    private static function acquire_ai_fix_process_lock($fix_type, $ttl = 90){
        $lock_key = self::get_ai_fix_process_lock_key($fix_type);
        $now = time();
        $lock_payload = [
            'created' => $now,
            'expires' => ($now + max(5, (int) $ttl)),
        ];

        // add_option is atomic on option_name, so only one overlapping request can acquire the lock.
        if(add_option($lock_key, $lock_payload, '', 'no')){
            return $lock_key;
        }

        $existing_lock = get_option($lock_key, array());
        $expires = (is_array($existing_lock) && isset($existing_lock['expires'])) ? (int) $existing_lock['expires'] : 0;
        if($expires > $now){
            return '';
        }

        delete_option($lock_key);
        if(add_option($lock_key, $lock_payload, '', 'no')){
            return $lock_key;
        }

        return '';
    }

    private static function build_ai_fix_process_response($fix_type, $item_id, $job = array(), $status = ''){
        $job = is_array($job) ? $job : array();
        $status = !empty($status) ? (string) $status : (isset($job['status']) ? (string) $job['status'] : 'idle');
        $progress = isset($job['progress']) ? (int) $job['progress'] : 0;
        $process_key = isset($job['process_key']) ? (string) $job['process_key'] : '';
        $link_mode = isset($job['link_mode']) ? (string) $job['link_mode'] : 'auto';

        if($status === 'complete'){
            $message = ($fix_type === 'broken_links') ? 'Broken links fixed' : 'Complete';
        }elseif($status === 'cancelled'){
            $message = 'Cancelled';
        }elseif($status === 'idle'){
            $message = 'No active job';
        }else{
            $message = !empty($job['message'])
                ? (string) $job['message']
                : (($link_mode === 'review' && $fix_type !== 'broken_links')
                    ? 'Preparing suggestions for review ' . $progress . '%'
                    : (($fix_type === 'broken_links')
                        ? 'Fixing broken links ' . $progress . '%'
                        : 'Fixing ' . $progress . '%'));
        }

        $response = [
            'fix_type' => $fix_type,
            'item_id'  => $item_id,
            'status'   => $status,
            'progress' => $progress,
            'message'  => $message,
            'estimate' => isset($job['estimate']) ? (int) $job['estimate'] : 0,
            'process_key' => $process_key,
            'link_mode' => $link_mode,
        ];

        $counts = self::get_ai_fix_live_counter_counts($fix_type, $process_key, self::should_count_available_ai_fix_items($fix_type));
        $response['analyzed_count'] = $counts['analyzed_count'];
        $response['total_posts'] = $counts['total_posts'];

        if($fix_type === 'broken_links'){
            $broken_metrics = self::get_broken_link_queue_metrics($process_key);
            $response['fixes_remaining'] = $broken_metrics['remaining'];
            $response['fixes_total'] = $broken_metrics['total'];
            $response['recommendations_url'] = admin_url('admin.php?page=link_whisper&type=error&recommended=1');
        }

        $response['dashboard_metrics'] = self::get_ai_fix_dashboard_metrics_snapshot();

        return $response;
    }

    private static function get_ai_fix_scan_complete_user_meta_key($fix_type = '', $item_id = ''){
        $fix_type = is_string($fix_type) ? sanitize_key($fix_type) : '';
        $item_id = is_string($item_id) ? sanitize_text_field($item_id) : '';

        return 'wpil_fix_scan_complete_' . md5($fix_type . ':' . $item_id);
    }

    private static function should_count_available_ai_fix_items($fix_type = ''){
        return in_array($fix_type, array('orphaned_posts', 'link_coverage', 'link_quality', 'custom_link_map'), true);
    }

    private static function get_ai_fix_live_counter_counts($fix_type = '', $process_key = '', $available_only = false){
        $counts = array(
            'analyzed_count' => 0,
            'total_posts' => 0,
        );

        if(empty($process_key)){
            return $counts;
        }

        foreach(self::get_ai_fix_preview_scopes($fix_type) as $scope){
            if(!empty($available_only)){
                $counts['total_posts'] += Wpil_LinkMapping::get_relation_map_available_item_count($process_key, $scope);
                $counts['analyzed_count'] += Wpil_LinkMapping::get_relation_map_available_ai_processed_item_count($process_key, $scope);
            }else{
                $counts['total_posts'] += Wpil_LinkMapping::get_relation_map_total_item_count($process_key, $scope);
                $counts['analyzed_count'] += Wpil_LinkMapping::get_relation_map_completed_item_count($process_key, $scope);
            }
        }

        return $counts;
    }

    private static function get_ai_fix_dashboard_metrics_snapshot(){
        $posts_crawled = (int) Wpil_Dashboard::getPostCount();
        $orphaned_count = (int) Wpil_Dashboard::getOrphanedPostsCount();
        $broken_links_count = (int) Wpil_Dashboard::getBrokenLinksCount([6,7,28,404,451,500,503,925]);
        $link_relatedness = (float) Wpil_Dashboard::get_related_link_percentage();
        $link_quality_score = round($link_relatedness / 10, 1);
        $link_density = Wpil_Dashboard::get_percent_of_posts_hitting_link_targets();
        $link_coverage_percent = !empty($link_density['percent']) ? (float) $link_density['percent'] : 0.0;
        $external_link_emphasis = Wpil_Dashboard::get_external_link_distribution(1);
        $external_link_emphasis_percent = 0.0;
        if(!empty($external_link_emphasis) && isset($external_link_emphasis[0]->representation)){
            $external_link_emphasis_percent = (float) (round($external_link_emphasis[0]->representation, 2) * 100);
        }

        $health_metrics = [
            'posts_crawled'            => $posts_crawled,
            'broken_links'             => $broken_links_count,
            'orphaned_posts'           => $orphaned_count,
            'link_coverage_percent'    => $link_coverage_percent,
            'link_relatedness_percent' => $link_relatedness,
            'external_site_focus'      => $external_link_emphasis_percent,
        ];
        $health = Wpil_Dashboard::wpil_dash_site_health_score($health_metrics);
        $health_meta = Wpil_Dashboard::wpil_dash_site_health_meta($health['score']);

        $status_link_quality = ($link_quality_score >= 7) ? ['Good', 'status-good', 'var(--green-600)'] : (($link_quality_score >= 5) ? ['Needs Work', 'status-warning', 'var(--amber-600)'] : ['Critical', 'status-poor', 'var(--red-600)']);
        $status_health = ((int)$health['score'] >= 70) ? ['Good', 'status-good', 'var(--green-600)'] : (((int)$health['score'] >= 50) ? ['Needs Work', 'status-warning', 'var(--amber-600)'] : ['Poor', 'status-poor', 'var(--red-600)']);
        $status_coverage = ($link_coverage_percent >= 80) ? ['Good', 'status-good', 'var(--green-600)'] : (($link_coverage_percent >= 60) ? ['Needs Work', 'status-warning', 'var(--amber-600)'] : ['Poor', 'status-poor', 'var(--red-600)']);
        $status_orphans = ($orphaned_count <= 0) ? ['Good', 'status-good', 'var(--green-600)'] : (($orphaned_count <= 25) ? ['Needs Work', 'status-warning', 'var(--amber-600)'] : ['Critical', 'status-poor', 'var(--red-600)']);

        $site_health_hint = !empty($health_meta['hint']) ? (string) $health_meta['hint'] : Wpil_Dashboard::wpil_dash_site_health_hint($health);
        $link_quality_description = ($link_quality_score >= 7) ? 'Strong topical relevance across internal links.' : (($link_quality_score >= 5) ? 'Improve relevance by replacing weaker related links.' : 'Many links have low relevance and need replacement.');
        $coverage_description = ($link_coverage_percent >= 80) ? 'Most content is meeting internal link targets.' : (($link_coverage_percent >= 60) ? 'Coverage is improving but key pages still need links.' : 'Coverage is low and many items are underlinked.');
        $orphan_description = ($orphaned_count <= 0) ? 'No orphaned items detected.' : sprintf('%s items currently have no inbound internal links.', number_format_i18n($orphaned_count));

        return [
            'site_health' => [
                'value' => (int) $health['score'],
                'formatted_value' => (string) ((int) $health['score']),
                'suffix' => '',
                'status_label' => $status_health[0],
                'status_class' => $status_health[1],
                'status_color' => $status_health[2],
                'description' => $site_health_hint,
            ],
            'link_quality' => [
                'value' => $link_quality_score,
                'formatted_value' => number_format_i18n($link_quality_score, 1),
                'suffix' => '/10',
                'status_label' => $status_link_quality[0],
                'status_class' => $status_link_quality[1],
                'status_color' => $status_link_quality[2],
                'description' => $link_quality_description,
            ],
            'link_coverage' => [
                'value' => $link_coverage_percent,
                'formatted_value' => number_format_i18n($link_coverage_percent, 1),
                'suffix' => '%',
                'status_label' => $status_coverage[0],
                'status_class' => $status_coverage[1],
                'status_color' => $status_coverage[2],
                'description' => $coverage_description,
            ],
            'orphaned_posts' => [
                'value' => $orphaned_count,
                'formatted_value' => number_format_i18n($orphaned_count),
                'suffix' => '',
                'status_label' => $status_orphans[0],
                'status_class' => $status_orphans[1],
                'status_color' => $status_orphans[2],
                'description' => $orphan_description,
            ],
        ];
    }

    private static function send_ai_fix_process_response($response, $success = true, $status_code = 200, $skip = false, $lock_key = ''){
        if(!empty($lock_key)){
            delete_option($lock_key);
        }

        $payload = [
            'success' => (bool) $success,
            'data' => is_array($response) ? $response : array(),
        ];

        if($skip){
            $payload['skip'] = true;
        }

        wp_send_json($payload, $status_code);
    }

    /**
     * When a dashboard fix gets cancelled, clear the saved process trail so the next start is actually fresh.
     **/
    private static function clear_cancelled_ai_fix_process_data($process_key = ''){
        $process_key = is_string($process_key) ? sanitize_text_field($process_key) : '';
        if(empty($process_key)){
            return;
        }

        delete_option('wpil_ai_linking_' . $process_key);
        if(get_option('wpil_ai_linking_process_key', '') === $process_key){
            delete_option('wpil_ai_linking_process_key');
        }

        delete_transient('wpil_review_served_' . get_current_user_id());
        delete_transient('wpil_doing_ai_fix_process');

        Wpil_LinkMapping::delete_relation_map($process_key);
        Wpil_AI::clear_credit_tracking_task_run('linking:' . $process_key);
        Wpil_Settings::delete_ai_fix_special_options($process_key);
    }

    /**
     * Dashboard AI fix process handler (stub runner).
     */
    public static function ajax_ai_fix_process(){
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Forbidden'], 403);
        }

        if(!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wpil_ai_fix_nonce')){
            wp_send_json_error(['message' => 'Invalid nonce'], 403);
        }

        $fix_type = isset($_POST['fix_type']) ? sanitize_text_field(wp_unslash($_POST['fix_type'])) : '';
        $item_id  = isset($_POST['item_id']) ? sanitize_text_field(wp_unslash($_POST['item_id'])) : '';
        $step     = isset($_POST['step']) ? sanitize_text_field(wp_unslash($_POST['step'])) : 'run';
        $estimate = isset($_POST['estimate']) ? (int) $_POST['estimate'] : 0;
        $start_id = isset($_POST['start_id']) ? sanitize_text_field(wp_unslash($_POST['start_id'])) : '';
        $process_key = self::get_ai_fix_process_key($fix_type);
        if(empty($process_key)){
            $process_key = isset($_POST['process_key']) ? sanitize_text_field(wp_unslash($_POST['process_key'])) : '';
        }
        $link_mode = isset($_POST['link_mode']) ? sanitize_text_field(wp_unslash($_POST['link_mode'])) : 'auto';
        $link_mode = ('review' === $link_mode) ? 'review' : 'auto';
        if($estimate < 1 && $fix_type === 'custom_link_map'){
            $estimate = (int) Wpil_AI::estimate_ai_linking_credit_cost(Wpil_CsvLinkMap::get_process_key(), true, count(Wpil_CsvLinkMap::get_queue_rows()));
        }

        if (empty($fix_type)) {
            wp_send_json_error(['message' => 'Missing fix_type'], 400);
        }

        // makre sure that we don't call too many times
        $checkout = get_transient('wpil_ai_fix_'.$fix_type.'_waiter');
        if(!empty($checkout) && $checkout > (time() - 10)){
            wp_send_json_error(['message' => 'Already running process!'], 400);
        }
        set_transient('wpil_ai_fix_'.$fix_type.'_waiter', time(), 10); // we're just preventing doubling up, so it is a short lived transient


        $user_id = get_current_user_id();
        $key = 'site:' . $fix_type . ':' . $item_id;

        $registry = get_option('wpil_ai_fix_registry', []);
        if (!is_array($registry)) {
            $registry = [];
        }
        if($fix_type === 'custom_link_map' && (!isset($registry[$key]) || !is_array($registry[$key]))){
            $resumable_job = self::get_custom_link_map_resumable_job($item_id, $link_mode);
            if(!empty($resumable_job)){
                $registry[$key] = $resumable_job;
                update_option('wpil_ai_fix_registry', $registry, false);
            }
        }

        if(in_array($step, array('status', 'start', 'run'), true)){
            $response = self::build_ai_fix_process_response($fix_type, $item_id, array(
                'process_key' => $process_key,
                'link_mode' => $link_mode,
                'estimate' => $estimate,
            ), 'idle');
            $response['message'] = 'This AI fix is hidden for this release.';

            self::send_ai_fix_process_response($response);
        }

        if($step === 'status'){
            if(isset($registry[$key]) && is_array($registry[$key])){
                self::send_ai_fix_process_response(self::build_ai_fix_process_response($fix_type, $item_id, $registry[$key]));
            }

            self::send_ai_fix_process_response(self::build_ai_fix_process_response($fix_type, $item_id, array(), 'idle'));
        }

        if($step === 'cancel'){
            if(isset($registry[$key]) && is_array($registry[$key])){
                if($fix_type === 'custom_link_map'){
                    Wpil_CsvLinkMap::clear_ai_run_started();
                }
                $registry[$key]['status'] = 'cancelled';
                $registry[$key]['cancelled'] = time();
                $registry[$key]['last_tick'] = time();
                if(empty($registry[$key]['process_key']) && !empty($process_key)){
                    $registry[$key]['process_key'] = $process_key;
                }
                update_option('wpil_ai_fix_registry', $registry, false);
                self::set_recently_cancelled_ai_fix($fix_type, $item_id, !empty($registry[$key]['start_id']) ? $registry[$key]['start_id'] : $start_id);
                self::clear_cancelled_ai_fix_process_data(isset($registry[$key]['process_key']) ? $registry[$key]['process_key'] : '');

                self::send_ai_fix_process_response(self::build_ai_fix_process_response($fix_type, $item_id, $registry[$key], 'cancelled'));
            }

            self::set_recently_cancelled_ai_fix($fix_type, $item_id, $start_id);
            self::clear_cancelled_ai_fix_process_data($process_key);
            if($fix_type === 'custom_link_map'){
                Wpil_CsvLinkMap::clear_ai_run_started();
            }

            self::send_ai_fix_process_response(self::build_ai_fix_process_response($fix_type, $item_id, array(), 'cancelled'));
        }

        $lock_key = '';
        if(in_array($step, ['start', 'run'], true)){
            $lock_key = self::acquire_ai_fix_process_lock($fix_type, 90);
            if(empty($lock_key)){
                $existing_job = (isset($registry[$key]) && is_array($registry[$key])) ? $registry[$key] : array();
                $skip_status = !empty($existing_job) ? '' : 'idle';
                $response = self::build_ai_fix_process_response($fix_type, $item_id, $existing_job, $skip_status);
                $response['skip_reason'] = 'locked';
                self::send_ai_fix_process_response($response, true, 200, true);
            }
        }

        if ($step === 'start') {
            $cancel_marker = self::get_recently_cancelled_ai_fix($fix_type, $item_id);
            if(!empty($cancel_marker)){
                $cancelled_start_id = !empty($cancel_marker['start_id']) ? (string) $cancel_marker['start_id'] : '';
                if(empty($start_id) || (!empty($cancelled_start_id) && $cancelled_start_id === $start_id)){
                    $response = self::build_ai_fix_process_response($fix_type, $item_id, array(
                        'process_key' => $process_key,
                        'link_mode' => $link_mode,
                    ), 'cancelled');
                    $response['skip_reason'] = 'recently_cancelled';
                    self::send_ai_fix_process_response($response, true, 200, true, $lock_key);
                }

                self::clear_recently_cancelled_ai_fix($fix_type, $item_id);
            }

            if(isset($registry[$key]) && is_array($registry[$key]) && !empty($registry[$key]['status']) && $registry[$key]['status'] === 'running'){
                $response = self::build_ai_fix_process_response($fix_type, $item_id, $registry[$key]);
                $response['skip_reason'] = 'already_running';
                self::send_ai_fix_process_response($response, true, 200, true, $lock_key);
            }

            $queue_setup = self::get_ai_fix_queue_setup($fix_type, $item_id, $process_key, $process_current_map);
            $reuse_existing_queue = !empty($queue_setup['reuse_existing_queue']);

            if($process_current_map && !$reuse_existing_queue){
                $response = self::build_ai_fix_process_response($fix_type, $item_id, array(
                    'process_key' => $process_key,
                    'link_mode' => $link_mode,
                ), 'idle');
                $response['message'] = 'The preview map does not have enough processed data to start yet.';
                $response['skip_reason'] = 'preview_map_not_started';
                self::send_ai_fix_process_response($response, true, 200, true, $lock_key);
            }

            if($fix_type === 'custom_link_map' && !$reuse_existing_queue && !Wpil_CsvLinkMap::has_ready_preview_map()){
                $response = self::build_ai_fix_process_response($fix_type, $item_id, array(
                    'process_key' => $process_key,
                    'link_mode' => $link_mode,
                ), 'idle');
                $response['message'] = 'Your custom linking preview is still building. Please wait for parsing to finish.';
                $response['skip_reason'] = 'preview_not_ready';
                self::send_ai_fix_process_response($response, true, 200, true, $lock_key);
            }

            if(!empty($process_key)){
                if(!$reuse_existing_queue){
                    self::reset_ai_fix_process_runtime($process_key);
                }else{
                    delete_option('wpil_ai_linking_' . $process_key);
                    Wpil_AI::clear_credit_tracking_task_run('linking:' . $process_key);
                }

                Wpil_AI::begin_credit_tracking_task_run(
                    Wpil_AI::get_credit_tracking_task_key_from_linking_process_key($process_key),
                    'linking:' . $process_key,
                    true
                );
            }

            if(!empty($process_key)){
                if(!$reuse_existing_queue){
                    Wpil_LinkMapping::reset_relation_map_queue($process_key, $queue_setup['queue_rows']);
                }else{
                    Wpil_LinkMapping::reset_relation_map_ai_queue($process_key);
                }
            }

            if($fix_type === 'custom_link_map'){
                Wpil_CsvLinkMap::mark_ai_run_started();
            }

            $registry[$key] = [
                'fix_type' => $fix_type,
                'item_id'  => $item_id,
                'user_id'  => $user_id,
                'progress' => 0,
                'status'   => 'running',
                'started'  => time(),
                'estimate' => $estimate,
                'last_tick'=> time(),
                'stage'    => !empty($queue_setup['stage']) ? $queue_setup['stage'] : '',
                'start_id' => $start_id,
                'process_key' => $process_key,
                'broken_total' => !empty($queue_setup['broken_total']) ? (int) $queue_setup['broken_total'] : 0,
                'link_mode' => $link_mode,
                'specified_populated' => !empty($queue_setup['reuse_existing_queue']),
                'process_current_map' => !empty($process_current_map),
            ];
        }elseif(!isset($registry[$key]) || !is_array($registry[$key])){
            self::send_ai_fix_process_response(self::build_ai_fix_process_response($fix_type, $item_id, array(), 'idle'), true, 200, false, $lock_key);
        }

        $job = $registry[$key];
        // Keep the running job's mode in sync with the current UI selection.
        if(!isset($job['link_mode']) || !in_array($job['link_mode'], ['auto', 'review'], true) || $job['link_mode'] !== $link_mode){
            $job['link_mode'] = $link_mode;
        }
        if(empty($job['process_key']) && !empty($process_key)){
            $job['process_key'] = $process_key;
        }
        $job['last_tick'] = time();

        // If cancel landed while another tick was running, honor cancellation and stop.
        if($step !== 'start'){
            $latest_registry = get_option('wpil_ai_fix_registry', []);
            if(is_array($latest_registry) && isset($latest_registry[$key]) && is_array($latest_registry[$key])){
                $latest_status = isset($latest_registry[$key]['status']) ? (string) $latest_registry[$key]['status'] : '';
                if($latest_status === 'cancelled'){
                    $job['status'] = 'cancelled';
                    if(isset($latest_registry[$key]['progress'])){
                        $job['progress'] = (int) $latest_registry[$key]['progress'];
                    }
                }
            }
        }

        if($job['status'] !== 'cancelled' && (!isset($job['link_mode']) || $job['link_mode'] !== 'review') && !empty($job['process_key']) && Wpil_AI::get_process_review_link_count($job['process_key']) > 100){
            Wpil_AI::auto_create_links_from_suggestions(0, '', $job['process_key']);
            if(Wpil_AI::get_process_review_link_count($job['process_key']) > 100){
                $job['status'] = 'running';
                $job['progress'] = min(99, max(1, isset($job['progress']) ? (int) $job['progress'] : 1));
                $job['message'] = 'Inserting links into posts...';
                $registry[$key] = $job;
                update_option('wpil_ai_fix_registry', $registry, false);
                self::send_ai_fix_process_response(self::build_ai_fix_process_response($fix_type, $item_id, $job), true, 200, false, $lock_key);
            }
        }

        if (!in_array($job['status'], ['complete', 'cancelled'], true)) {
            switch ($fix_type) {
                case 'link_coverage':
                    $job = self::run_link_coverage_ai_fix($job);
                    break;
                case 'orphaned_posts':
                    $job = self::run_orphaned_posts_ai_fix($job);
                    break;
                case 'link_quality':
                    $job = self::run_link_quality_ai_fix($job);
                    break;
                case 'broken_links':
                    $job = self::run_broken_link_ai_fix($job);
                    break;
                case 'custom_link_map':
                    $job = self::run_custom_link_map_ai_fix($job);
                    break;
                default:
                    $job['status'] = 'complete';
                    $job['progress'] = 100;
                    break;
            }
        }

        $final_status = isset($job['status']) ? (string) $job['status'] : '';

        if($step !== 'start' && $final_status !== 'cancelled'){
            $latest_registry = get_option('wpil_ai_fix_registry', []);
            if(is_array($latest_registry) && isset($latest_registry[$key]) && is_array($latest_registry[$key])){
                $latest_status = isset($latest_registry[$key]['status']) ? (string) $latest_registry[$key]['status'] : '';
                if($latest_status === 'cancelled'){
                    $job['status'] = 'cancelled';
                    if(isset($latest_registry[$key]['progress'])){
                        $job['progress'] = (int) $latest_registry[$key]['progress'];
                    }
                    $final_status = 'cancelled';
                }
            }
        }

        if($final_status === 'complete' && (!isset($job['link_mode']) || $job['link_mode'] !== 'review') && !empty($job['process_key'])){
            Wpil_AI::auto_create_links_from_suggestions(0, '', $job['process_key']);
            if(Wpil_AI::get_process_review_link_count($job['process_key']) > 0){
                $job['status'] = 'running';
                $job['progress'] = 99;
                $job['message'] = 'Inserting links into posts...';
                $final_status = 'running';
            }
        }

        $clear_completed_custom_plan = (
            $fix_type === 'custom_link_map' &&
            $final_status === 'complete' &&
            (!isset($job['link_mode']) || $job['link_mode'] !== 'review')
        );

        $should_remove = in_array($final_status, ['complete', 'cancelled'], true);
        if($should_remove){
            if(!empty($job['process_key'])){
                if($final_status === 'cancelled'){
                    self::clear_cancelled_ai_fix_process_data($job['process_key']);
                }else{
                    Wpil_AI::clear_credit_tracking_task_run('linking:' . $job['process_key']);
                    Wpil_Settings::delete_ai_fix_special_options($job['process_key']);
                }
            }
            unset($registry[$key]);
        }else{
            $registry[$key] = $job;
        }
        update_option('wpil_ai_fix_registry', $registry, false);
        if($clear_completed_custom_plan){
            Wpil_CsvLinkMap::clear_plan();
        }elseif($fix_type === 'custom_link_map' && $should_remove){
            Wpil_CsvLinkMap::clear_ai_run_started();
        }

        self::send_ai_fix_process_response(self::build_ai_fix_process_response($fix_type, $item_id, $job, $final_status), true, 200, false, $lock_key);
    }

    /**
     * Builds the dashboard fix preview map and returns the estimate that comes out of it.
     **/
    public static function ajax_ai_fix_preview_map(){
        if(!current_user_can('manage_options')){
            wp_send_json_error(array('message' => 'Forbidden'), 403);
        }

        if(!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wpil_ai_fix_nonce')){
            wp_send_json_error(array('message' => 'Invalid nonce'), 403);
        }

        $fix_type = isset($_POST['fix_type']) ? sanitize_text_field(wp_unslash($_POST['fix_type'])) : '';
        $item_id = isset($_POST['item_id']) ? sanitize_text_field(wp_unslash($_POST['item_id'])) : '';
        $reset = isset($_POST['reset']) && !empty($_POST['reset']);
        $force_reset = isset($_POST['force_reset']) && !empty($_POST['force_reset']);
        $special_options = isset($_POST['special_options']) ? wp_unslash($_POST['special_options']) : array();
        $allowed_types = array('orphaned_posts', 'link_coverage', 'link_quality', 'broken_links', 'external_focus');
        $fix_special_types = array('orphaned_posts', 'link_coverage', 'link_quality');

        if(empty($fix_type) || !in_array($fix_type, $allowed_types, true)){
            wp_send_json_error(array('message' => 'Missing fix_type'), 400);
        }

        $process_key = self::get_ai_fix_process_key($fix_type);
        if(empty($process_key)){
            wp_send_json_error(array('message' => 'Unable to prepare the preview map.'), 400);
        }

        $scan_complete_key = self::get_ai_fix_scan_complete_user_meta_key($fix_type, $item_id);
        if($reset && !$force_reset && get_user_meta(get_current_user_id(), $scan_complete_key, true) && self::is_ai_fix_preview_map_ready($fix_type, $process_key)){
            $reset = false;
        }

        $total_items = Wpil_LinkMapping::get_relation_map_total_item_count($process_key);
        if($reset || $total_items < 1){
            self::reset_ai_fix_process_runtime($process_key);
            if(in_array($fix_type, $fix_special_types, true)){
                Wpil_Settings::update_ai_fix_special_options($special_options, $process_key);
            }
            $queue_setup = self::get_ai_fix_queue_setup($fix_type, $item_id, $process_key);
            Wpil_LinkMapping::reset_relation_map_queue($process_key, $queue_setup['queue_rows']);
        }elseif(in_array($fix_type, $fix_special_types, true)){
            Wpil_Settings::update_ai_fix_special_options($special_options, $process_key);
        }

        $total_items = Wpil_LinkMapping::get_relation_map_total_item_count($process_key);
        if($total_items < 1){
            wp_send_json_success(self::build_ai_fix_preview_response($fix_type, $process_key, 'complete', 'No eligible items were found for this fix.'));
        }

        if(!self::is_ai_fix_preview_map_ready($fix_type, $process_key)){
            Wpil_LinkMapping::build_relationship_map($process_key);
        }

        $ready = self::is_ai_fix_preview_map_ready($fix_type, $process_key);
        $message = $ready ? 'Linking plan ready.' : 'Generating the linking plan for this fix...';
        if($ready){
            update_user_meta(get_current_user_id(), $scan_complete_key, time());
        }
        wp_send_json_success(self::build_ai_fix_preview_response($fix_type, $process_key, ($ready ? 'complete' : 'running'), $message));
    }

    /**
     * Exports the current relation mapp that the AI fix popup is working with.
     **/
    public static function ajax_ai_fix_export_map(){
        if(!current_user_can('manage_options')){
            wp_die('Forbidden', '', array('response' => 403));
        }

        if(!isset($_REQUEST['nonce']) || !wp_verify_nonce($_REQUEST['nonce'], 'wpil_ai_fix_nonce')){
            wp_die('Invalid nonce', '', array('response' => 403));
        }

        $fix_type = isset($_REQUEST['fix_type']) ? sanitize_text_field(wp_unslash($_REQUEST['fix_type'])) : '';
        $process_key = isset($_REQUEST['process_key']) ? sanitize_text_field(wp_unslash($_REQUEST['process_key'])) : '';
        $allowed_types = array('orphaned_posts', 'link_coverage', 'link_quality', 'broken_links', 'external_focus', 'custom_link_map');

        if(empty($fix_type) || !in_array($fix_type, $allowed_types, true)){
            wp_die('Missing fix_type', '', array('response' => 400));
        }

        if(empty($process_key)){
            $process_key = self::get_ai_fix_process_key($fix_type);
        }

        if(empty($process_key)){
            wp_die('Unable to find the AI fix mapp.', '', array('response' => 400));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'wpil_relation_mapping';
        $rows = array();
        $offset = 0;
        $limit = 250;

        while(true){
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, map_date, process_key, work_scope, post_id, post_type, is_pillar, item_processed, ai_processed, pillar_processed, map_processed, item_count, last_index, map_data FROM {$table} WHERE process_key = %s ORDER BY id ASC LIMIT %d OFFSET %d",
                    $process_key,
                    $limit,
                    $offset
                )
            );

            if(empty($results)){
                break;
            }

            foreach($results as $row){
                $map_data = Wpil_Toolbox::json_decompress(isset($row->map_data) ? $row->map_data : '', true);
                $rows[] = array(
                    'id' => (int) $row->id,
                    'pid' => $row->post_type . '_' . (int) $row->post_id,
                    'post_id' => (int) $row->post_id,
                    'post_type' => $row->post_type,
                    'work_scope' => Wpil_LinkMapping::normalize_relation_work_scope(isset($row->work_scope) ? $row->work_scope : ''),
                    'map_date' => (int) $row->map_date,
                    'map_date_utc' => !empty($row->map_date) ? gmdate('Y-m-d H:i:s', (int) $row->map_date) : '',
                    'is_pillar' => (int) $row->is_pillar,
                    'item_processed' => (int) $row->item_processed,
                    'ai_processed' => (int) $row->ai_processed,
                    'pillar_processed' => (int) $row->pillar_processed,
                    'map_processed' => (int) $row->map_processed,
                    'item_count' => (int) $row->item_count,
                    'last_index' => isset($row->last_index) ? $row->last_index : '',
                    'map_data' => is_array($map_data) ? $map_data : array(),
                );
            }

            if(count($results) < $limit){
                break;
            }

            $offset += $limit;
        }

        $payload = array(
            'exported_at_utc' => gmdate('Y-m-d H:i:s'),
            'fix_type' => $fix_type,
            'process_key' => $process_key,
            'summary' => self::get_ai_fix_preview_summary_from_relation_map($process_key),
            'row_count' => count($rows),
            'rows' => $rows,
        );

        $host = !empty($_SERVER['HTTP_HOST']) ? sanitize_file_name(wp_unslash($_SERVER['HTTP_HOST'])) : 'site';
        $filename = 'ai-fix-map-' . sanitize_file_name($fix_type) . '-' . $host . '-' . time() . '.json';

        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-type: application/json; charset=utf-8');
        echo wp_json_encode($payload, JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Manual smoke-test helper for ajax_ai_fix_process().
     * Executes the AJAX handler in-process and captures the JSON payload.
     *
     * Usage (from wp shell/wp eval):
     * Wpil_Maintenance::smoke_test_ajax_ai_fix_process([
     *   'fix_type' => 'link_coverage',
     *   'item_id'  => '',
     *   'step'     => 'start',
     *   'estimate' => 1,
     *   'source'   => 'js',
     *   'user_id'  => 1
     * ]);
     */
    public static function smoke_test_ajax_ai_fix_process($args = []){
        $defaults = [
            'fix_type' => 'link_coverage',
            'item_id'  => '',
            'step'     => 'start',
            'estimate' => 1,
            'source'   => 'js',
            'user_id'  => 0,
        ];
        $args = wp_parse_args($args, $defaults);

        $original_post = $_POST;
        $original_user = get_current_user_id();
        $set_user_id = (int) $args['user_id'];
        if($set_user_id > 0 && $set_user_id !== (int)$original_user){
            wp_set_current_user($set_user_id);
        }

        $_POST = [
            'fix_type' => (string) $args['fix_type'],
            'item_id'  => (string) $args['item_id'],
            'step'     => (string) $args['step'],
            'estimate' => (int) $args['estimate'],
            'source'   => (string) $args['source'],
            'nonce'    => wp_create_nonce('wpil_ai_fix_nonce'),
        ];

        $captured = '';
        $error = '';
        $die_handler = function($message = ''){
            echo $message;
        };
        $die_filter = function() use ($die_handler){
            return $die_handler;
        };

        add_filter('wp_die_handler', $die_filter);
        ob_start();
        try{
            self::ajax_ai_fix_process();
        }catch(Throwable $e){
            $error = $e->getMessage();
        }
        $captured = ob_get_clean();
        remove_filter('wp_die_handler', $die_filter);

        $_POST = $original_post;
        if($set_user_id > 0 && $set_user_id !== (int)$original_user){
            wp_set_current_user($original_user);
        }

        return [
            'ok' => empty($error),
            'error' => $error,
            'raw' => $captured,
            'decoded' => json_decode($captured, true),
        ];
    }

    /**
     * Manual smoke-test runner that starts and advances ajax_ai_fix_process()
     * until complete or until max passes are reached.
     *
     * Usage (from wp shell/wp eval):
     * Wpil_Maintenance::smoke_test_ajax_ai_fix_until_complete('broken_links', '', 30, 1);
     */
    public static function smoke_test_ajax_ai_fix_until_complete($fix_type = 'link_coverage', $item_id = '', $max_passes = 20, $user_id = 0){
        $max_passes = max(1, (int) $max_passes);
        $results = [];

        for($i = 0; $i < $max_passes; $i++){
            $step = ($i === 0) ? 'start' : 'run';
            $result = self::smoke_test_ajax_ai_fix_process([
                'fix_type' => $fix_type,
                'item_id'  => $item_id,
                'step'     => $step,
                'estimate' => 1,
                'source'   => 'js',
                'user_id'  => (int) $user_id,
            ]);

            $results[] = $result;

            $status = '';
            if(!empty($result['decoded']) && isset($result['decoded']['data']['status'])){
                $status = (string) $result['decoded']['data']['status'];
            }

            if($status === 'complete' || !$result['ok']){
                break;
            }
        }

        return $results;
    }

    public static function run_maintenance(){
        // get the maintenance plan
        $plan = get_option('wpil_maintenance_plan', array());

        // if it's empty
        if(empty($plan)){
            // exist
            return false;
        }

        // first, make sure that we have a snapshot of the site so we can roll back if needed
        if(!in_array('take_snapshot', $plan['completed_steps'])){
            $finished = Wpil_LinkMapping::take_snapshot('Site Snapshot: ' . date('Y-m-d'), $plan['process_key']);
            if($finished){
                $plan['completed_steps'][] = 'take_snapshot';
            }else{
                // if we haven't completed the plan, exist here
                return;
            }
        }

        // next, work on the mapp of posts on the whole site!
        if(!in_array('build_relationship_map', $plan['completed_steps'])){
            $finished = Wpil_LinkMapping::build_relationship_map($plan['process_key']);
            if($finished){
                $plan['completed_steps'][] = 'build_relationship_map';
            }else{
                // if we haven't completed the plan, exist here
                return;
            }
        }

        // next, work on the mapp of posts on the whole site!
        if(!in_array('plan_link_changes', $plan['completed_steps'])){
            $finished = Wpil_LinkMapping::take_snapshot('Site Snapshot: ' . date('Y-m-d'), $plan['process_key']);
            if($finished){
                $plan['completed_steps'][] = 'plan_link_changes';
            }else{
                // if we haven't completed the plan, exist here
                return;
            }
        }

        // next, work on the mapp of posts on the whole site!
        if(!in_array('map_site_links', $plan['completed_steps'])){
            self::process_linking_mapp($plan['process_key']);
        }

        
        // first, takle the easy stuff
        $broken_fixed = true;
        if(in_array('clean_broken_links', $plan)){
            // clean up the broken links that are marked with replaces
            self::clean_up_broken_links();
        }

        // if we have time, start updating the links
        $mapping_complete = true;
        if(in_array('map_site_links', $plan) && Wpil_Base::overTimeLimit(15)){
            
        }

        // if we havve any other ideas... Do them here

        // set if the plan is complete
        if($broken_fixed && $mapping_complete){
            $plan['plan_complete'] = 1;
        }


        // doo something with the plan!!!
        $plan;

        return $broken_fixed && $mapping_complete;
    }

    public static function clean_up_broken_links(){
        // runn the broken link replace method
        return Wpil_Error::replace_broken_links_with_suggested();
    }

    public static function process_linking_mapp($process_key = ''){
        return Wpil_LinkMapping::handle_map_processing($process_key);
    }

    public static function ajax_search_pillar_posts(){
        global $wpdb;

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Forbidden'], 403);
        }

        Wpil_Base::verify_nonce('wpil_pillar_content_nonce');

        $term = isset($_POST['term']) ? sanitize_text_field(wp_unslash($_POST['term'])) : '';
        $term = trim($term);

        if (strlen($term) < 2) {
            wp_send_json_success(['items' => []]);
        }

        $items = [];
        $seen = [];
        $max_items = 10;

        $add_post_item = static function($post_id) use (&$items, &$seen, $max_items){
            if (count($items) >= $max_items) {
                return;
            }

            $post_id = (int) $post_id;
            if ($post_id <= 0) {
                return;
            }

            $key = 'post_' . $post_id;
            if (isset($seen[$key])) {
                return;
            }

            $p = get_post($post_id);
            if (!$p) {
                return;
            }

            $seen[$key] = true;
            $post = new Wpil_Model_Post($post_id, 'post');
            $items[] = [
                'id' => $post_id,
                'pid' => $key,
                'title' => get_the_title($post_id) ?: __('(No title)', 'wpil'),
                'type' => 'post',
                'real_type_name' => $post->getRealTypeName(),
                'status' => get_post_status($post_id),
                'edit' => get_edit_post_link($post_id, 'raw'),
                'view' => $post->getViewLink(),
            ];
        };

        $add_term_item = static function($term_id, $taxonomy = null) use (&$items, &$seen, $max_items){
            if (count($items) >= $max_items) {
                return;
            }

            $term_id = (int) $term_id;
            if ($term_id <= 0) {
                return;
            }

            $term = empty($taxonomy) ? get_term($term_id) : get_term($term_id, $taxonomy);
            if (empty($term) || is_wp_error($term)) {
                return;
            }

            $key = 'term_' . $term_id;
            if (isset($seen[$key])) {
                return;
            }

            $seen[$key] = true;
            $post = new Wpil_Model_Post($term_id, 'term');
            $items[] = [
                'id' => $term_id,
                'pid' => $key,
                'title' => $term->name ?: __('(No title)', 'wpil'),
                'type' => 'term',
                'real_type_name' => $post->getRealTypeName(),
                'status' => $term->taxonomy,
                'edit' => get_edit_term_link($term_id, $term->taxonomy),
                'view' => $post->getViewLink(),
            ];
        };

        $is_full_url = (bool) preg_match('#^(https?:)?//#i', $term) || !empty(wp_parse_url($term, PHP_URL_HOST));
        $is_relative = (!$is_full_url && isset($term[0]) && $term[0] === '/');

        // Pre-search URL/relative inputs with slug/path lookups before full text query.
        if($is_full_url || $is_relative){
            $statuses = ['publish', 'private', 'draft', 'future'];
            $quoted_statuses = "'" . implode("','", array_map('esc_sql', $statuses)) . "'";
            $taxonomies = Wpil_Settings::getTermTypes();
            $quoted_taxonomies = !empty($taxonomies) ? "'" . implode("','", array_map('esc_sql', $taxonomies)) . "'" : '';

            if($is_full_url){
                $post_model = Wpil_Post::getPostByLink($term);
                if(!empty($post_model) && !empty($post_model->id)){
                    $add_post_item($post_model->id);
                }

                if(count($items) < $max_items){
                    $url_path = (string) wp_parse_url($term, PHP_URL_PATH);
                    $url_slug = sanitize_title(basename(trim($url_path, '/')));
                    if(!empty($url_slug)){
                        $url_term = Wpil_Term::getTermBySlug($url_slug, $term);
                        if(!empty($url_term) && !is_wp_error($url_term)){
                            $add_term_item($url_term->term_id, $url_term->taxonomy);
                        }
                    }
                }
            }else{
                $raw_path = (string) wp_parse_url($term, PHP_URL_PATH);
                if(empty($raw_path)){
                    $raw_path = $term;
                }

                $exact = (substr($raw_path, -1) === '/');
                $clean_path = trim($raw_path, '/');
                $parts = array_values(array_filter(explode('/', $clean_path), static function($part){
                    return $part !== '';
                }));

                if(!empty($parts)){
                    $first_slug = sanitize_title($parts[0]);
                    $last_slug = sanitize_title($parts[count($parts) - 1]);
                    $first_match = $exact ? $first_slug : $wpdb->esc_like($first_slug) . '%';
                    $last_match = $exact ? $last_slug : $wpdb->esc_like($last_slug) . '%';
                    $operator = $exact ? '=' : 'LIKE';

                    if(count($parts) > 1){
                        $parent_ids = $wpdb->get_col(
                            $wpdb->prepare(
                                "SELECT ID
                                 FROM {$wpdb->posts}
                                 WHERE post_status IN ({$quoted_statuses})
                                   AND post_type <> 'revision'
                                   AND post_name {$operator} %s
                                 LIMIT 100",
                                $first_match
                            )
                        );

                        if(!empty($parent_ids)){
                            $parent_ids = array_values(array_unique(array_map('intval', $parent_ids)));
                            $parent_in = implode(',', $parent_ids);
                            if(!empty($parent_in)){
                                $post_ids = $wpdb->get_col(
                                    $wpdb->prepare(
                                        "SELECT ID
                                         FROM {$wpdb->posts}
                                         WHERE post_status IN ({$quoted_statuses})
                                           AND post_type <> 'revision'
                                           AND post_parent IN ({$parent_in})
                                           AND post_name {$operator} %s
                                         LIMIT %d",
                                        $last_match,
                                        $max_items
                                    )
                                );

                                if(!empty($post_ids)){
                                    foreach($post_ids as $post_id){
                                        $add_post_item($post_id);
                                    }
                                }
                            }
                        }

                        if(empty($items)){
                            $post_ids = $wpdb->get_col(
                                $wpdb->prepare(
                                    "SELECT ID
                                     FROM {$wpdb->posts}
                                     WHERE post_status IN ({$quoted_statuses})
                                       AND post_type <> 'revision'
                                       AND post_name {$operator} %s
                                     LIMIT %d",
                                    $last_match,
                                    $max_items
                                )
                            );

                            if(!empty($post_ids)){
                                foreach($post_ids as $post_id){
                                    $add_post_item($post_id);
                                }
                            }
                        }
                    }else{
                        $post_ids = $wpdb->get_col(
                            $wpdb->prepare(
                                "SELECT ID
                                 FROM {$wpdb->posts}
                                 WHERE post_status IN ({$quoted_statuses})
                                   AND post_type <> 'revision'
                                   AND post_name {$operator} %s
                                 LIMIT %d",
                                $first_match,
                                $max_items
                            )
                        );

                        if(!empty($post_ids)){
                            foreach($post_ids as $post_id){
                                $add_post_item($post_id);
                            }
                        }
                    }

                    if(count($items) < $max_items && !empty($quoted_taxonomies)){
                        $term_ids = $wpdb->get_col(
                            $wpdb->prepare(
                                "SELECT t.term_id
                                 FROM {$wpdb->terms} t
                                 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
                                 WHERE tt.taxonomy IN ({$quoted_taxonomies})
                                   AND t.slug {$operator} %s
                                 LIMIT %d",
                                $last_match,
                                $max_items
                            )
                        );

                        if(!empty($term_ids)){
                            foreach($term_ids as $term_id){
                                $add_term_item($term_id);
                            }
                        }
                    }
                }
            }

            if(!empty($items)){
                wp_send_json_success(['items' => $items]);
            }
        }

        $q = new WP_Query([
            's' => $term,
            'post_type' => 'any',
            'post_status' => ['publish', 'private', 'draft', 'future'],
            'posts_per_page' => 10,
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);

        foreach ($q->posts as $post_id) {
            $add_post_item($post_id);
        }

        $taxonomies = Wpil_Settings::getTermTypes();
        if(!empty($taxonomies)){
            $terms = get_terms([
                'taxonomy' => $taxonomies,
                'search' => $term,
                'hide_empty' => false,
                'number' => 10,
            ]);

            if(!is_wp_error($terms) && !empty($terms)){
                foreach($terms as $t){
                    $add_term_item($t->term_id, $t->taxonomy);
                }
            }
        }

        wp_send_json_success(['items' => $items]);
    }

    /**
     * 
     **/
    public static function ajax_save_pillar_posts(){
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Forbidden'], 403);
        }

        Wpil_Base::verify_nonce('wpil_pillar_content_nonce');

        $raw = isset($_POST['ids']) ? sanitize_text_field(wp_unslash($_POST['ids'])) : '';
        $parts = array_filter(preg_split('/\s*,\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY));

        $post_ids = array();
        $term_ids = array();

        foreach($parts as $part){
            $part = trim($part);
            if(empty($part)){
                continue;
            }

            if(is_numeric($part)){
                $post_ids[] = (int) $part;
                continue;
            }

            $bits = explode('_', $part, 2);
            if(count($bits) !== 2){
                continue;
            }

            $type = $bits[0];
            $id = (int) $bits[1];
            if(!$id){
                continue;
            }

            if($type === 'term'){
                $term_ids[] = $id;
            }else{
                $post_ids[] = $id;
            }
        }

        // Keep only valid posts/terms.
        $post_ids = array_values(array_filter(array_unique($post_ids), function($id){
            return (bool) get_post($id);
        }));
        $term_ids = array_values(array_filter(array_unique($term_ids), function($id){
            $term = get_term($id);
            return (!empty($term) && !is_wp_error($term));
        }));

        update_option('wpil_pillar_content_post_ids', $post_ids, false);
        update_option('wpil_pillar_content_term_ids', $term_ids, false);

        wp_send_json_success(['saved' => ['posts' => $post_ids, 'terms' => $term_ids]]);
    }

    /**
     * 
     **/
    public static function get_pillar_post_items(){

        $ids = Wpil_Settings::get_money_page_ids(true);
        $term_ids = Wpil_Settings::get_money_page_term_ids();

        $items = [];
        if(!empty($ids)){
            foreach ($ids as $post_id) {
                $p = get_post($post_id);
                if (!$p) continue;

                $title = get_the_title($post_id);
                if(empty($title)){
                    $title = __('(No title)', 'wpil');
                }

                $post = new Wpil_Model_Post($post_id, 'post');
                $items[] = [
                    'id' => (int) $post_id,
                    'pid' => 'post_' . (int) $post_id,
                    'title' => $title,
                    'type' => 'post',
                    'real_type_name' => $post->getRealTypeName(),
                    'status' => get_post_status($post_id),
                    'edit' => get_edit_post_link($post_id, 'raw'),
                    'view' => $post->getViewLink(),
                ];
            }
        }

        if(!empty($term_ids)){
            foreach($term_ids as $term_id){
                $term = get_term($term_id);
                if(empty($term) || is_wp_error($term)){
                    continue;
                }

                $post = new Wpil_Model_Post($term_id, 'term');
                $items[] = [
                    'id' => (int) $term_id,
                    'pid' => 'term_' . (int) $term_id,
                    'title' => $term->name ?: __('(No title)', 'wpil'),
                    'type' => 'term',
                    'real_type_name' => $post->getRealTypeName(),
                    'status' => $term->taxonomy,
                    'edit' => get_edit_term_link($term_id, $term->taxonomy),
                    'view' => $post->getViewLink(),
                ];
            }
        }

        return $items;
    }

    /**
     * Gets the remaining time on the maintenance process
     **/
    public static function ajax_get_maintenance_timers(){
        $completed = get_option('wpil_maintenance_plan');
        $post_count = Wpil_Report::get_total_post_count();
        $process = array(
            "processes" => array(
                "snapshot_links" => $post_count * 1,
                "scan_site_content" => $post_count * 3,
                "build_high_level_plan" => $post_count * 2,
                "build_post_by_post_plan" => $post_count * 3,
                "update_posts_with_new_links" => $post_count * 1,
                "fix_broken_links" => $post_count * 1
            )
        );
        wp_send_json($process);
    }

    /**
     * Links Orphaned Posts with AI!
     **/
    public static function perform_orphaned_post_maintenance(){
        
    }

    /**
     * Performs internal linking and relation adjusting so that links between posts are more relevant!
     **/
    public static function perform_link_relation_maintenance(){

    }

    /**
     * In the shadows faces appear, warriors wearing full metal gear...
     * Fixes broken links with new urls!
     **/
    public static function perform_broken_link_fix(){
        
    }

    /**
     * Performs link coverage linking to bring the posts up to our inbound/outbound internal linking guidelines!
     **/
    public static function perform_link_coverage_maintenance(){
        
    }

    private static function get_ai_fix_process_key($fix_type = ''){
        $process_key = Wpil_LinkMapping::get_dashboard_fix_process_key($fix_type);
        return !empty($process_key) ? $process_key : '';
    }

    private static function get_custom_link_map_resumable_job($item_id = '', $link_mode = 'auto'){
        if(
            !Wpil_CsvLinkMap::has_active_plan() ||
            !Wpil_CsvLinkMap::has_started_ai_run()
        ){
            return array();
        }

        $process_key = self::get_ai_fix_process_key('custom_link_map');
        if(empty($process_key)){
            $process_key = Wpil_CsvLinkMap::get_process_key();
        }

        if(empty($process_key)){
            return array();
        }

        $outbound_status = Wpil_LinkMapping::get_relation_map_scope_status($process_key, Wpil_LinkMapping::RELATION_SCOPE_OUTBOUND);
        $inbound_status = Wpil_LinkMapping::get_relation_map_scope_status($process_key, Wpil_LinkMapping::RELATION_SCOPE_INBOUND);

        $outbound_total = !empty($outbound_status['total']) ? (int) $outbound_status['total'] : 0;
        $inbound_total = !empty($inbound_status['total']) ? (int) $inbound_status['total'] : 0;
        if(($outbound_total + $inbound_total) < 1){
            return array();
        }

        $outbound_pending = !empty($outbound_status['pending_count']) ? (int) $outbound_status['pending_count'] : 0;
        $inbound_pending = !empty($inbound_status['pending_count']) ? (int) $inbound_status['pending_count'] : 0;
        $outbound_unprocessed = !empty($outbound_status['unprocessed_count']) ? (int) $outbound_status['unprocessed_count'] : 0;
        $inbound_unprocessed = !empty($inbound_status['unprocessed_count']) ? (int) $inbound_status['unprocessed_count'] : 0;

        if($outbound_pending < 1 && $inbound_pending < 1 && $outbound_unprocessed < 1 && $inbound_unprocessed < 1){
            return array();
        }

        $stage = 'inbound';
        if($outbound_total > 0 && ($outbound_pending > 0 || $outbound_unprocessed > 0)){
            $stage = 'outbound';
        }elseif($inbound_total > 0){
            $stage = 'inbound';
        }

        $outbound_done = !empty($outbound_status['done_count']) ? (int) $outbound_status['done_count'] : 0;
        $inbound_done = !empty($inbound_status['done_count']) ? (int) $inbound_status['done_count'] : 0;
        if($stage === 'outbound'){
            $progress = ($outbound_total > 0) ? min(50, (int) round(($outbound_done / max($outbound_total, 1)) * 50)) : 50;
        }else{
            $inbound_progress = ($inbound_total > 0) ? (int) round(($inbound_done / max($inbound_total, 1)) * 50) : 50;
            $progress = min(100, 50 + $inbound_progress);
        }

        return array(
            'fix_type' => 'custom_link_map',
            'item_id' => is_scalar($item_id) ? (string) $item_id : '',
            'user_id' => get_current_user_id(),
            'progress' => $progress,
            'status' => 'running',
            'started' => time(),
            'estimate' => (int) Wpil_AI::estimate_ai_linking_credit_cost(Wpil_CsvLinkMap::get_process_key(), true, count(Wpil_CsvLinkMap::get_queue_rows())),
            'last_tick' => time(),
            'stage' => $stage,
            'process_key' => $process_key,
            'link_mode' => ('review' === $link_mode) ? 'review' : 'auto',
            'specified_populated' => Wpil_CsvLinkMap::has_ready_preview_map(),
        );
    }

    public static function get_dashboard_resumable_ai_fix_job($fix_type = '', $item_id = '', $link_mode = 'auto'){
        $fix_type = is_string($fix_type) ? sanitize_text_field($fix_type) : '';
        $item_id = is_scalar($item_id) ? sanitize_text_field((string) $item_id) : '';
        $link_mode = ('review' === $link_mode) ? 'review' : 'auto';

        switch($fix_type){
            case 'custom_link_map':
                return self::get_custom_link_map_resumable_job($item_id, $link_mode);
        }

        return array();
    }

    private static function reset_ai_fix_process_runtime($process_key = ''){
        $process_key = is_string($process_key) ? sanitize_text_field($process_key) : '';
        if(empty($process_key)){
            return;
        }
        delete_option('wpil_ai_linking_' . $process_key);
        Wpil_LinkMapping::delete_relation_map($process_key);
        Wpil_AI::clear_credit_tracking_task_run('linking:' . $process_key);
    }

    private static function get_relation_queue_rows_from_pids($pids = array(), $work_scope = 'default'){
        $pids = self::normalize_pid_scope($pids);
        if(empty($pids)){
            return array();
        }

        $pillar_lookup = array_flip(Wpil_Settings::get_money_page_pid_list(true));
        $rows = array();
        foreach($pids as $pid){
            $parts = self::parse_pid($pid);
            if(empty($parts['id'])){
                continue;
            }

            $rows[] = array(
                'pid' => $parts['type'] . '_' . $parts['id'],
                'work_scope' => Wpil_LinkMapping::normalize_relation_work_scope($work_scope),
                'is_pillar' => isset($pillar_lookup[$parts['type'] . '_' . $parts['id']]) ? 1 : 0,
            );
        }

        return $rows;
    }

    private static function get_ai_fix_preview_scopes($fix_type = ''){
        if($fix_type === 'link_coverage'){
            return array(
                Wpil_LinkMapping::RELATION_SCOPE_OUTBOUND,
                Wpil_LinkMapping::RELATION_SCOPE_INBOUND,
            );
        }

        return array(Wpil_LinkMapping::RELATION_SCOPE_DEFAULT);
    }

    private static function is_ai_fix_preview_map_ready($fix_type = '', $process_key = ''){
        if(empty($process_key)){
            return false;
        }

        $has_items = false;
        foreach(self::get_ai_fix_preview_scopes($fix_type) as $scope){
            if(Wpil_LinkMapping::get_relation_map_total_item_count($process_key, $scope) < 1){
                continue;
            }

            $has_items = true;
            if(!Wpil_LinkMapping::is_map_processed($process_key, $scope)){
                return false;
            }
        }

        return $has_items;
    }

    private static function get_ai_fix_preview_progress($fix_type = '', $process_key = ''){
        $counts = self::get_ai_fix_live_counter_counts($fix_type, $process_key);
        $total = $counts['total_posts'];
        $complete = $counts['analyzed_count'];

        if($total < 1){
            return 100;
        }

        return max(1, min(100, (int) round(($complete / max($total, 1)) * 100)));
    }

    private static function get_ai_fix_preview_summary_from_relation_map($process_key = ''){
        global $wpdb;

        $summary = array(
            'source_posts_exact' => 0,
            'target_posts_exact' => 0,
            'potential_links_min' => 0,
            'potential_links_max' => 0,
        );

        if(empty($process_key)){
            return $summary;
        }

        $table = $wpdb->prefix . 'wpil_relation_mapping';
        $offset = 0;
        $limit = 250;
        $source_posts = array();
        $target_posts = array();
        $inbound_source_posts = array();
        $outbound_rows = array();

        while(true){
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT post_id, post_type, work_scope, map_data FROM {$table} WHERE process_key = %s ORDER BY id ASC LIMIT %d OFFSET %d",
                    $process_key,
                    $limit,
                    $offset
                )
            );

            if(empty($rows)){
                break;
            }

            foreach($rows as $row){
                if(empty($row->post_id)){
                    continue;
                }

                $map_data = Wpil_Toolbox::json_decompress(isset($row->map_data) ? $row->map_data : '');
                $map_data = is_object($map_data) ? $map_data : array();
                $related_pids = self::extract_ai_fix_preview_related_pids(isset($map_data->related_posts) ? $map_data->related_posts : array());
                $relation_count = count($related_pids);
                if($relation_count < 1){
                    continue;
                }

                $queue_pid = (string) $row->post_type . '_' . (int) $row->post_id;
                $scope = Wpil_LinkMapping::normalize_relation_work_scope(isset($row->work_scope) ? $row->work_scope : '');
                $treat_as_inbound = ($scope === Wpil_LinkMapping::RELATION_SCOPE_INBOUND || !empty($map_data->pillar_content));

                if($treat_as_inbound){
                    $target_posts[$queue_pid] = true;
                    foreach($related_pids as $source_pid){
                        $source_posts[$source_pid] = true;
                        $inbound_source_posts[$source_pid] = true;
                    }
                }else{
                    $source_posts[$queue_pid] = true;
                    foreach($related_pids as $target_pid){
                        $target_posts[$target_pid] = true;
                    }
                    $outbound_rows[] = array(
                        'source_pid' => $queue_pid,
                        'relation_count' => $relation_count,
                    );
                }
            }

            if(count($rows) < $limit){
                break;
            }

            $offset += $limit;
        }

        $summary['source_posts_exact'] = count($source_posts);
        $summary['target_posts_exact'] = count($target_posts);
        $inbound_source_count = count($inbound_source_posts);
        if($inbound_source_count > 0){
            $inbound_min = (int) floor($inbound_source_count * 0.25);
            $inbound_max = (int) ceil($inbound_source_count * 0.75);
            $summary['potential_links_min'] += max(0, $inbound_min);
            $summary['potential_links_max'] += max($inbound_min, min($inbound_max, $inbound_source_count));
        }

        if(!empty($outbound_rows)){
            $max_outbound = (int) get_option('wpil_max_links_per_post', 0);
            foreach($outbound_rows as $outbound_row){
                $parts = self::parse_pid($outbound_row['source_pid']);
                if(empty($parts['id'])){
                    continue;
                }

                $post = new Wpil_Model_Post($parts['id'], $parts['type']);
                $remaining_capacity = 3;
                if($max_outbound > 0){
                    $remaining_capacity = max(0, $max_outbound - (int) $post->getOutboundInternalLinks(true));
                }

                $possible_links = min((int) $outbound_row['relation_count'], $remaining_capacity);
                $summary['potential_links_max'] += $possible_links;
                $summary['potential_links_min'] += (int) floor($possible_links * 0.5);
            }
        }

        return $summary;
    }

    private static function extract_ai_fix_preview_related_pids($related_posts = array()){
        $pids = array();
        foreach((array) $related_posts as $key => $value){
            $candidate = '';
            if(is_string($key) && preg_match('/^(post|term)_\d+$/', $key)){
                $candidate = $key;
            }elseif(is_string($value) && preg_match('/^(post|term)_\d+$/', $value)){
                $candidate = $value;
            }elseif(is_array($value) && !empty($value['pid']) && is_string($value['pid'])){
                $candidate = $value['pid'];
            }elseif(is_object($value) && !empty($value->pid) && is_string($value->pid)){
                $candidate = $value->pid;
            }

            $candidate = Wpil_LinkMapping::normalize_pid($candidate);
            if(!empty($candidate)){
                $pids[$candidate] = $candidate;
            }
        }

        return array_values($pids);
    }

    private static function build_ai_fix_preview_response($fix_type = '', $process_key = '', $status = 'running', $message = ''){
        $summary = self::get_ai_fix_preview_summary_from_relation_map($process_key);
        $source_posts = (int) $summary['source_posts_exact'];
        $counts = self::get_ai_fix_live_counter_counts($fix_type, $process_key, ($status === 'complete' && self::should_count_available_ai_fix_items($fix_type)));
        $estimate = Wpil_AI::estimate_ai_linking_credit_cost($process_key, true);

        return array(
            'status' => $status,
            'message' => $message,
            'process_key' => $process_key,
            'progress' => ($status === 'complete') ? 100 : self::get_ai_fix_preview_progress($fix_type, $process_key),
            'preview_ready' => ($status === 'complete'),
            'credit_estimate' => $estimate,
            'analyzed_count' => $counts['analyzed_count'],
            'total_posts' => $counts['total_posts'],
            'source_posts_exact' => $source_posts,
            'target_posts_exact' => (int) $summary['target_posts_exact'],
            'potential_links_min' => (int) $summary['potential_links_min'],
            'potential_links_max' => (int) $summary['potential_links_max'],
        );
    }

    private static function get_ai_fix_queue_setup($fix_type = '', $item_id = '', $process_key = '', $process_current_map = false){
        $setup = array(
            'queue_rows' => array(),
            'stage' => '',
            'broken_total' => 0,
            'reuse_existing_queue' => false,
        );

        switch($fix_type){
            case 'orphaned_posts':
                $requested_pids = self::normalize_orphan_fix_pid_input($item_id);
                $orphan_pids = !empty($requested_pids) ? self::filter_allowed_orphan_fix_pids($requested_pids) : self::get_orphaned_fix_pids();
                $setup['queue_rows'] = self::get_relation_queue_rows_from_pids($orphan_pids);
                break;
            case 'link_quality':
                $scope_pids = self::normalize_orphan_fix_pid_input($item_id);
                $quality_rows = self::get_low_relatedness_link_rows($scope_pids);
                $setup['queue_rows'] = self::get_relation_queue_rows_from_pids(self::get_quality_source_pids($quality_rows));
                break;
            case 'broken_links':
                $broken_ids = self::get_broken_link_ids(0);
                $setup['queue_rows'] = self::get_relation_queue_rows_from_pids(self::get_broken_link_source_pids($broken_ids));
                $setup['broken_total'] = count($broken_ids);
                break;
            case 'link_coverage':
                $requested_pids = self::normalize_orphan_fix_pid_input($item_id);
                $outbound_ids = self::get_link_coverage_outbound_post_ids($requested_pids);
                $inbound_ids = self::get_link_coverage_inbound_post_ids($requested_pids);
                if(!empty($outbound_ids)){
                    $setup['queue_rows'] = array_merge($setup['queue_rows'], self::get_relation_queue_rows_from_pids($outbound_ids, Wpil_LinkMapping::RELATION_SCOPE_OUTBOUND));
                }

                if(!empty($inbound_ids)){
                    $setup['queue_rows'] = array_merge($setup['queue_rows'], self::get_relation_queue_rows_from_pids($inbound_ids, Wpil_LinkMapping::RELATION_SCOPE_INBOUND));
                }

                $setup['stage'] = !empty($outbound_ids) ? 'outbound' : 'inbound';
                break;
            case 'custom_link_map':
                $csv_queue_rows = Wpil_CsvLinkMap::get_queue_rows();
                $setup['queue_rows'] = $csv_queue_rows;
                $setup['reuse_existing_queue'] = Wpil_CsvLinkMap::has_ready_preview_map();
                // Determine the initial stage based on which scopes exist in the plan
                $has_outbound = !empty(array_filter($csv_queue_rows, function($r){ return isset($r['work_scope']) && $r['work_scope'] === Wpil_LinkMapping::RELATION_SCOPE_OUTBOUND; }));
                $setup['stage'] = $has_outbound ? 'outbound' : 'inbound';
                break;
        }

        if($fix_type !== 'custom_link_map' && !empty($process_key) && (self::is_ai_fix_preview_map_ready($fix_type, $process_key) || (!empty($process_current_map) && self::has_ai_fix_preview_map_data($fix_type, $process_key)))){
            $setup['reuse_existing_queue'] = true;
        }

        return $setup;
    }

    private static function has_ai_fix_preview_map_data($fix_type = '', $process_key = ''){
        if(empty($process_key)){
            return false;
        }

        foreach(self::get_ai_fix_preview_scopes($fix_type) as $scope){
            if(Wpil_LinkMapping::get_relation_map_completed_item_count($process_key, $scope) > 0){
                return true;
            }
        }

        return false;
    }

    private static function get_broken_link_queue_metrics($process_key = ''){
        $metrics = array(
            'total' => 0,
            'done' => 0,
            'remaining' => 0,
        );

        if(empty($process_key)){
            return $metrics;
        }

        $rows = Wpil_LinkMapping::get_relation_map_items($process_key, false, true, 0, Wpil_LinkMapping::RELATION_SCOPE_DEFAULT);
        if(empty($rows)){
            return $metrics;
        }

        foreach($rows as $row){
            if(empty($row->pid)){
                continue;
            }

            $runtime = Wpil_LinkMapping::get_relation_map_runtime_state($row);
            $total_ids = !empty($runtime['broken_link_total_ids']) && is_array($runtime['broken_link_total_ids']) ? $runtime['broken_link_total_ids'] : self::get_broken_link_ids_for_source_pid($row->pid);
            $processed_ids = !empty($runtime['broken_link_processed_ids']) && is_array($runtime['broken_link_processed_ids']) ? $runtime['broken_link_processed_ids'] : array();
            $metrics['total'] += count(array_values(array_unique(array_map('intval', $total_ids))));
            $metrics['done'] += count(array_values(array_unique(array_map('intval', $processed_ids))));
        }

        $metrics['remaining'] = max(0, $metrics['total'] - $metrics['done']);

        return $metrics;
    }

    /**
     * Runs the AI fix process for broken links.
     **/
    public static function run_broken_link_ai_fix($job = []){
        if(empty($job) || !is_array($job)){
            return $job;
        }

        if(empty($job['process_key'])){
            $job['process_key'] = self::get_ai_fix_process_key('broken_links');
        }

        if(Wpil_LinkMapping::get_relation_map_total_item_count($job['process_key'], Wpil_LinkMapping::RELATION_SCOPE_DEFAULT) < 1){
            $job['status'] = 'complete';
            $job['progress'] = 100;
            return $job;
        }

        $gate_state = self::maybe_build_relation_map_for_ai($job['process_key'], Wpil_LinkMapping::RELATION_SCOPE_DEFAULT);
        $source_status = $gate_state['status'];
        $ready_count = $gate_state['ready_count'];
        $scope_complete = $gate_state['scope_complete'];
        if(!$gate_state['can_process_ai']){
            if(self::should_wait_for_relation_map_gate($job, $job['process_key'], Wpil_LinkMapping::RELATION_SCOPE_DEFAULT)){
                $job['progress'] = self::get_relation_map_gate_progress($job['process_key'], Wpil_LinkMapping::RELATION_SCOPE_DEFAULT);
                return $job;
            }
        }

        $next_pid = !empty($source_status['next_pending']) ? $source_status['next_pending'] : '';

        if(empty($next_pid)){
            if(!$scope_complete && !self::should_use_available_ai_progress($job)){
                $job['progress'] = self::get_relation_map_gate_progress($job['process_key'], Wpil_LinkMapping::RELATION_SCOPE_DEFAULT);
                return $job;
            }

            if($ready_count > 0){
                $job['progress'] = self::get_relation_map_ai_progress($job['process_key'], Wpil_LinkMapping::RELATION_SCOPE_DEFAULT, 0, 100, self::should_use_available_ai_progress($job));
                return $job;
            }

            $job['status'] = 'complete';
            $job['progress'] = 100;
            return $job;
        }

        Wpil_LinkMapping::claim_relation_map_ai_item($job['process_key'], $next_pid, Wpil_LinkMapping::RELATION_SCOPE_DEFAULT);
        $next_parts = self::parse_pid($next_pid);
        $next_row = Wpil_LinkMapping::get_relation_map_item($job['process_key'], $next_parts['id'], $next_parts['type'], false, true, Wpil_LinkMapping::RELATION_SCOPE_DEFAULT);
        $runtime = Wpil_LinkMapping::get_relation_map_runtime_state($next_row);
        $total_ids = !empty($runtime['broken_link_total_ids']) && is_array($runtime['broken_link_total_ids']) ? $runtime['broken_link_total_ids'] : self::get_broken_link_ids_for_source_pid($next_pid);
        $processed_ids = !empty($runtime['broken_link_processed_ids']) && is_array($runtime['broken_link_processed_ids']) ? $runtime['broken_link_processed_ids'] : array();

        $total_ids = array_values(array_unique(array_map('intval', $total_ids)));
        $processed_ids = array_values(array_unique(array_map('intval', $processed_ids)));

        if(empty($runtime['broken_link_total_ids']) && !empty($total_ids)){
            Wpil_LinkMapping::update_relation_map_runtime_state($job['process_key'], $next_pid, array(
                'broken_link_total_ids' => $total_ids,
            ), Wpil_LinkMapping::RELATION_SCOPE_DEFAULT);
        }

        $source_id = self::get_next_unprocessed_id($total_ids, $processed_ids);
        if(empty($source_id)){
            Wpil_LinkMapping::mark_relation_map_ai_processed($job['process_key'], $next_pid, true, Wpil_LinkMapping::RELATION_SCOPE_DEFAULT);
        }else{
            self::process_broken_link_item($source_id, $job['process_key']);
            $processed_ids[] = (int) $source_id;
            $processed_ids = array_values(array_unique(array_map('intval', $processed_ids)));
            Wpil_LinkMapping::update_relation_map_runtime_state($job['process_key'], $next_pid, array(
                'broken_link_total_ids' => $total_ids,
                'broken_link_processed_ids' => $processed_ids,
            ), Wpil_LinkMapping::RELATION_SCOPE_DEFAULT);

            if(empty(self::get_next_unprocessed_id($total_ids, $processed_ids))){
                Wpil_LinkMapping::mark_relation_map_ai_processed($job['process_key'], $next_pid, true, Wpil_LinkMapping::RELATION_SCOPE_DEFAULT);
            }else{
                Wpil_LinkMapping::release_relation_map_ai_claim($job['process_key'], $next_pid, Wpil_LinkMapping::RELATION_SCOPE_DEFAULT);
            }
        }

        $job['progress'] = self::get_relation_map_ai_progress($job['process_key'], Wpil_LinkMapping::RELATION_SCOPE_DEFAULT, 0, 100, self::should_use_available_ai_progress($job));

        // apply any suggested replacements in small batches if auto-apply is enabled
        if(!empty(get_option('wpil_auto_apply_broken_link_recommendations', false))){
            Wpil_Error::replace_broken_links_with_suggested();
        }

        return $job;
    }

    /**
     * Runs the AI fix process for orphaned posts/terms.
     *
     * Supports:
     * - explicit IDs in $job['item_id'] (post_123,term_55,123,...)
     * - "fix all" mode when no IDs are supplied
     **/
    public static function run_orphaned_posts_ai_fix($job = []){
        if(empty($job) || !is_array($job)){
            return $job;
        }
        $auto_insert = empty($job['link_mode']) || ('review' !== $job['link_mode']);

        if(empty($job['process_key'])){
            $job['process_key'] = self::get_ai_fix_process_key('orphaned_posts');
        }

        if(Wpil_LinkMapping::get_relation_map_total_item_count($job['process_key'], Wpil_LinkMapping::RELATION_SCOPE_DEFAULT) < 1){
            $job['status'] = 'complete';
            $job['progress'] = 100;
            return $job;
        }

        $gate_state = self::maybe_build_relation_map_for_ai($job['process_key'], Wpil_LinkMapping::RELATION_SCOPE_DEFAULT);
        $scope_state = $gate_state['status'];
        $scope_complete = $gate_state['scope_complete'];
        if(!$gate_state['can_process_ai']){
            if(self::should_wait_for_relation_map_gate($job, $job['process_key'], Wpil_LinkMapping::RELATION_SCOPE_DEFAULT)){
                $job['progress'] = self::get_relation_map_gate_progress($job['process_key'], Wpil_LinkMapping::RELATION_SCOPE_DEFAULT);
                return $job;
            }
        }

        $next_pid = '';
        while(true){
            $candidate_row = Wpil_LinkMapping::get_next_pending_relation_row($job['process_key'], Wpil_LinkMapping::RELATION_SCOPE_DEFAULT);
            $candidate_pid = (!empty($candidate_row) && !empty($candidate_row->pid)) ? $candidate_row->pid : '';

            if(empty($candidate_pid)){
                break;
            }

            // Live check against the link report table before trying to fix the item.
            if(!self::is_orphaned_pid_in_link_report($candidate_pid)){
                // This item no longer needs orphan processing, so mark the map row complete for this job.
                Wpil_LinkMapping::mark_relation_map_ai_processed($job['process_key'], $candidate_pid, true, Wpil_LinkMapping::RELATION_SCOPE_DEFAULT);
                continue;
            }

            $next_pid = $candidate_pid;
            break;
        }

        if(empty($next_pid)){
            $scope_state = Wpil_LinkMapping::get_relation_map_scope_status($job['process_key'], Wpil_LinkMapping::RELATION_SCOPE_DEFAULT);
            $pending_ai_count = $scope_state['pending_count'];
            $scope_complete = $scope_state['scope_complete'];
            if(!$scope_complete && !self::should_use_available_ai_progress($job)){
                $job['progress'] = self::get_relation_map_gate_progress($job['process_key'], Wpil_LinkMapping::RELATION_SCOPE_DEFAULT);
                return $job;
            }

            $counts = self::get_relation_map_ai_progress_counts($job['process_key'], Wpil_LinkMapping::RELATION_SCOPE_DEFAULT, self::should_use_available_ai_progress($job));
            $total = max(1, $counts['total']);
            $done = $counts['done'];
            $job['progress'] = ($total > 0) ? min(100, (int) round(($done / max($total, 1)) * 100)) : 100;
            if($pending_ai_count > 0){
                return $job;
            }
            $job['status'] = 'complete';
            $job['progress'] = 100;
            return $job;
        }

        Wpil_LinkMapping::claim_relation_map_ai_item($job['process_key'], $next_pid, Wpil_LinkMapping::RELATION_SCOPE_DEFAULT);
        self::process_orphaned_fix_pid($next_pid, $job['process_key'], $auto_insert);

        $scope_state = Wpil_LinkMapping::get_relation_map_scope_status($job['process_key'], Wpil_LinkMapping::RELATION_SCOPE_DEFAULT);
        $counts = self::get_relation_map_ai_progress_counts($job['process_key'], Wpil_LinkMapping::RELATION_SCOPE_DEFAULT, self::should_use_available_ai_progress($job));
        $total = max(1, $counts['total']);
        $done = $counts['done'];
        $job['progress'] = ($total > 0) ? min(100, (int) round(($done / max($total, 1)) * 100)) : 100;

        return $job;
    }

    /**
     * Converts supplied fix IDs into normalized pid values.
     * Accepts: "post_123,term_4", "123", ["post_1", "term_2"], etc.
     **/
    public static function normalize_orphan_fix_pid_input($item_id = ''){
        if(is_array($item_id)){
            $raw = $item_id;
        }elseif(is_string($item_id)){
            $trimmed = trim($item_id);
            if($trimmed === '' || $trimmed === 'orphaned_posts' || strtolower($trimmed) === 'all'){
                return [];
            }
            $raw = preg_split('/\s*,\s*/', $trimmed, -1, PREG_SPLIT_NO_EMPTY);
        }elseif(is_numeric($item_id)){
            $raw = array((string) $item_id);
        }else{
            return [];
        }

        $out = [];
        foreach($raw as $value){
            if(is_numeric($value)){
                $out[] = 'post_' . (int) $value;
                continue;
            }

            if(!is_string($value) || strpos($value, '_') === false){
                continue;
            }

            $parts = explode('_', $value, 2);
            if(count($parts) !== 2){
                continue;
            }

            $type = (trim($parts[0]) === 'term') ? 'term' : 'post';
            $id = (int) $parts[1];
            if(empty($id)){
                continue;
            }

            $out[] = $type . '_' . $id;
        }

        return array_values(array_unique($out));
    }

    /**
     * Removes pids that should not be processed because they are ignored/not orphaned/invalid.
     **/
    public static function filter_allowed_orphan_fix_pids($pids = []){
        if(empty($pids) || !is_array($pids)){
            return [];
        }

        $ignored = Wpil_Settings::getIgnoreOrphanedPosts();
        $ignored_lookup = [];
        if(!empty($ignored)){
            foreach($ignored as $pid){
                if(!is_string($pid)){
                    continue;
                }
                $ignored_lookup[$pid] = true;
            }
        }

        $allowed_taxonomies = Wpil_Settings::getTermTypes();
        $out = [];

        foreach($pids as $pid){
            if(empty($pid) || !is_string($pid)){
                continue;
            }

            if(isset($ignored_lookup[$pid])){
                continue;
            }

            $parts = self::parse_pid($pid);
            if(empty($parts['id'])){
                continue;
            }

            if($parts['type'] === 'term'){
                $term = get_term($parts['id']);
                if(empty($term) || is_wp_error($term)){
                    continue;
                }

                if(!empty($allowed_taxonomies) && !in_array($term->taxonomy, $allowed_taxonomies, true)){
                    continue;
                }
            }else{
                $post = get_post($parts['id']);
                if(empty($post)){
                    continue;
                }
            }

            if(!self::is_orphaned_pid($pid)){
                continue;
            }

            $out[] = $pid;
        }

        return array_values(array_unique($out));
    }

    /**
     * Gets all orphaned post/term pids that should be processed.
     **/
    public static function get_orphaned_fix_pids(){
        global $wpdb;

        $link_report_table = $wpdb->prefix . 'wpil_report_links';
        $pids = [];

        // Posts
        if(Wpil_Settings::use_link_table_for_data() && Wpil_Report::link_table_is_created()){
            $ignored = Wpil_Query::get_all_report_ignored_post_ids('p', ['orphaned' => true, 'hide_noindex' => true]);
            $statuses_query = Wpil_Query::postStatuses('p');
            $post_types = Wpil_Query::postTypes('p');

            $post_ids = $wpdb->get_col(
                "SELECT p.ID
                    FROM {$wpdb->posts} p
                    WHERE p.ID NOT IN (
                        SELECT DISTINCT target_id
                        FROM {$link_report_table}
                        WHERE target_type = 'post' AND has_links > 0
                    )
                    {$ignored} {$statuses_query} {$post_types}"
            );
        }else{
            $ignored = Wpil_Query::get_all_report_ignored_post_ids('p', ['orphaned' => true, 'hide_noindex' => true]);
            $statuses_query = Wpil_Query::postStatuses('p');
            $post_types = Wpil_Query::postTypes('p');

            $post_ids = $wpdb->get_col(
                "SELECT DISTINCT m.post_id
                    FROM {$wpdb->postmeta} m
                    INNER JOIN {$wpdb->posts} p ON m.post_id = p.ID
                    WHERE m.meta_key = 'wpil_links_inbound_internal_count'
                        AND m.meta_value = 0
                        {$ignored} {$statuses_query} {$post_types}"
            );
        }

        if(!empty($post_ids)){
            foreach($post_ids as $post_id){
                $post_id = (int) $post_id;
                if($post_id > 0){
                    $pids[] = 'post_' . $post_id;
                }
            }
        }

        // Terms
        $term_taxonomies = Wpil_Settings::getTermTypes();
        if(!empty($term_taxonomies)){
            if(Wpil_Settings::use_link_table_for_data() && Wpil_Report::link_table_is_created()){
                $term_ids = $wpdb->get_col(
                    "SELECT t.term_id
                        FROM {$wpdb->terms} t
                        WHERE t.term_id NOT IN (
                            SELECT DISTINCT target_id
                            FROM {$link_report_table}
                            WHERE target_type = 'term' AND has_links > 0
                        )"
                );
            }else{
                $term_ids = $wpdb->get_col(
                    "SELECT DISTINCT term_id
                        FROM {$wpdb->termmeta}
                        WHERE meta_key = 'wpil_links_inbound_internal_count'
                            AND meta_value = 0"
                );
            }

            if(!empty($term_ids)){
                $ignored_terms = Wpil_Settings::getItemTypeIds(Wpil_Settings::getIgnoreOrphanedPosts(), 'term');
                if(!empty($ignored_terms)){
                    $term_ids = array_diff($term_ids, $ignored_terms);
                }

                $term_ids = Wpil_Query::remove_noindex_ids($term_ids, 'term');

                $tax_query = "'" . implode("','", array_map('esc_sql', $term_taxonomies)) . "'";
                $term_ids = array_values(array_unique(array_map('intval', $term_ids)));
                if(!empty($term_ids)){
                    $term_id_sql = implode(',', $term_ids);
                    $allowed_term_ids = $wpdb->get_col(
                        "SELECT term_id
                            FROM {$wpdb->term_taxonomy}
                            WHERE term_id IN ({$term_id_sql})
                                AND taxonomy IN ({$tax_query})"
                    );

                    if(!empty($allowed_term_ids)){
                        foreach($allowed_term_ids as $term_id){
                            $term_id = (int) $term_id;
                            if($term_id > 0){
                                $pids[] = 'term_' . $term_id;
                            }
                        }
                    }
                }
            }
        }

        return array_values(array_unique($pids));
    }

    /**
     * Checks whether a pid currently qualifies as orphaned.
     **/
    public static function is_orphaned_pid($pid = ''){
        global $wpdb;

        $parts = self::parse_pid($pid);
        if(empty($parts['id'])){
            return false;
        }

        $id = (int) $parts['id'];
        $type = $parts['type'];
        $link_report_table = $wpdb->prefix . 'wpil_report_links';

        if(Wpil_Settings::use_link_table_for_data() && Wpil_Report::link_table_is_created()){
            $target_type = ($type === 'term') ? 'term' : 'post';
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT 1
                    FROM {$link_report_table}
                    WHERE target_type = %s
                        AND target_id = %d
                        AND has_links > 0
                    LIMIT 1",
                $target_type,
                $id
            ));

            return empty($exists);
        }

        if($type === 'term'){
            $count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT meta_value
                    FROM {$wpdb->termmeta}
                    WHERE term_id = %d
                        AND meta_key = 'wpil_links_inbound_internal_count'
                    LIMIT 1",
                $id
            ));
        }else{
            $count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT meta_value
                    FROM {$wpdb->postmeta}
                    WHERE post_id = %d
                        AND meta_key = 'wpil_links_inbound_internal_count'
                    LIMIT 1",
                $id
            ));
        }

        return ($count <= 0);
    }

    /**
     * Checks orphan status directly from wpil_report_links for live validation.
     **/
    public static function is_orphaned_pid_in_link_report($pid = ''){
        global $wpdb;

        $parts = self::parse_pid($pid);
        if(empty($parts['id'])){
            return false;
        }

        if(!Wpil_Report::link_table_is_created()){
            return self::is_orphaned_pid($pid);
        }

        $target_type = ($parts['type'] === 'term') ? 'term' : 'post';
        $target_id = (int) $parts['id'];
        $link_report_table = $wpdb->prefix . 'wpil_report_links';

        $has_inbound_links = $wpdb->get_var($wpdb->prepare(
            "SELECT 1
                FROM {$link_report_table}
                WHERE target_type = %s
                    AND target_id = %d
                    AND has_links > 0
                LIMIT 1",
            $target_type,
            $target_id
        ));

        return empty($has_inbound_links);
    }

    /**
     * Processes a single orphaned pid by creating inbound internal links for it.
     **/
    private static function handle_inbound_ai_fix_result($pid = '', $process_key = '', $work_scope = Wpil_LinkMapping::RELATION_SCOPE_DEFAULT, $result = array(), $auto_insert = true){
        $data = $result;
        $meta = array();
        if(is_array($result) && isset($result['data']) && isset($result['meta'])){
            $data = $result['data'];
            $meta = is_array($result['meta']) ? $result['meta'] : array();
        }

        $saved_count = !empty($data) ? Wpil_AI::save_ai_linking_suggestions($data, $process_key) : 0;
        if($auto_insert && $saved_count > 0){
            Wpil_AI::auto_create_links_from_suggestions(0, '', $process_key);
        }

        $parts = self::parse_pid($pid);
        $map_item = !empty($parts['id']) ? Wpil_LinkMapping::get_relation_map_item($process_key, $parts['id'], $parts['type'], true, true, $work_scope) : array();
        $runtime = Wpil_LinkMapping::get_relation_map_runtime_state(is_array($map_item) ? $map_item : array());

        $searched_pids = !empty($runtime['inbound_searched_pids']) && is_array($runtime['inbound_searched_pids']) ? self::normalize_pid_scope($runtime['inbound_searched_pids']) : array();
        $pass_pids = !empty($meta['searched_pids']) && is_array($meta['searched_pids']) ? self::normalize_pid_scope($meta['searched_pids']) : array();
        $searched_pids = array_values(array_unique(array_merge($searched_pids, $pass_pids)));

        $pass_count = isset($runtime['inbound_pass_count']) ? (int) $runtime['inbound_pass_count'] : 0;
        $total_suggestions = isset($runtime['inbound_suggestion_count']) ? (int) $runtime['inbound_suggestion_count'] : 0;
        $runtime_updates = array(
            'inbound_pass_count' => $pass_count + 1,
            'inbound_suggestion_count' => $total_suggestions + (int) $saved_count,
            'inbound_last_pass_count' => (int) $saved_count,
        );

        if(!empty($searched_pids)){
            $runtime_updates['inbound_searched_pids'] = $searched_pids;
        }

        Wpil_LinkMapping::update_relation_map_runtime_state($process_key, $pid, $runtime_updates, $work_scope);

        $remaining_pids = !empty($meta['remaining_pids']) && is_array($meta['remaining_pids']) ? self::normalize_pid_scope($meta['remaining_pids']) : array();
        if($saved_count > 0 || empty($remaining_pids)){
            Wpil_LinkMapping::mark_relation_map_ai_processed($process_key, $pid, true, $work_scope);
        }else{
            Wpil_LinkMapping::release_relation_map_ai_claim($process_key, $pid, $work_scope);
        }

        return $saved_count;
    }

    public static function process_orphaned_fix_pid($pid = '', $process_key = '', $auto_insert = true){
        if(empty($pid) || empty($process_key)){
            return false;
        }

        $data = Wpil_AI::create_link_suggestions_from_map($pid, $process_key, 'inbound');
        self::handle_inbound_ai_fix_result($pid, $process_key, Wpil_LinkMapping::RELATION_SCOPE_DEFAULT, $data, $auto_insert);
        return true;
    }

    /**
     * Runs the AI fix process for link quality.
     * Replaces low-relatedness internal links with better related targets.
     **/
    public static function run_link_quality_ai_fix($job = []){
        if(empty($job) || !is_array($job)){
            return $job;
        }

        if(empty($job['process_key'])){
            $job['process_key'] = self::get_ai_fix_process_key('link_quality');
        }

        if(Wpil_LinkMapping::get_relation_map_total_item_count($job['process_key'], Wpil_LinkMapping::RELATION_SCOPE_DEFAULT) < 1){
            $job['status'] = 'complete';
            $job['progress'] = 100;
            return $job;
        }

        $gate_state = self::maybe_build_relation_map_for_ai($job['process_key'], Wpil_LinkMapping::RELATION_SCOPE_DEFAULT);
        $scope_status = $gate_state['status'];
        $ready_count = isset($scope_status['pending_count']) ? (int) $scope_status['pending_count'] : 0;
        $scope_complete = !empty($scope_status['scope_complete']);
        if(!$gate_state['can_process_ai']){
            if(self::should_wait_for_relation_map_gate($job, $job['process_key'], Wpil_LinkMapping::RELATION_SCOPE_DEFAULT)){
                $job['progress'] = self::get_relation_map_gate_progress($job['process_key'], Wpil_LinkMapping::RELATION_SCOPE_DEFAULT);
                return $job;
            }
        }

        $next_pid = !empty($scope_status['next_pending']) ? $scope_status['next_pending'] : '';
        if(empty($next_pid)){
            if(!$scope_complete && !self::should_use_available_ai_progress($job)){
                $job['progress'] = self::get_relation_map_gate_progress($job['process_key'], Wpil_LinkMapping::RELATION_SCOPE_DEFAULT);
                return $job;
            }

            if($ready_count > 0){
                $job['progress'] = self::get_relation_map_ai_progress($job['process_key'], Wpil_LinkMapping::RELATION_SCOPE_DEFAULT, 0, 100, self::should_use_available_ai_progress($job));
                return $job;
            }

            $job['status'] = 'complete';
            $job['progress'] = 100;
            return $job;
        }

        $source_rows = self::get_low_relatedness_link_rows(array($next_pid), 600);
        Wpil_LinkMapping::claim_relation_map_ai_item($job['process_key'], $next_pid, Wpil_LinkMapping::RELATION_SCOPE_DEFAULT);
        if(empty($source_rows)){
            Wpil_LinkMapping::mark_relation_map_ai_processed($job['process_key'], $next_pid, true, Wpil_LinkMapping::RELATION_SCOPE_DEFAULT);
        }else{
            foreach($source_rows as $row){
                self::process_link_quality_row($row, $job['process_key']);
            }
            Wpil_LinkMapping::mark_relation_map_ai_processed($job['process_key'], $next_pid, true, Wpil_LinkMapping::RELATION_SCOPE_DEFAULT);
        }

        $job['progress'] = self::get_relation_map_ai_progress($job['process_key'], Wpil_LinkMapping::RELATION_SCOPE_DEFAULT, 0, 100, self::should_use_available_ai_progress($job));

        return $job;
    }

    /**
     * Gets low-relatedness internal links to evaluate for replacement.
     **/
    public static function get_low_relatedness_link_rows($scope_pids = [], $limit = 600){
        global $wpdb;

        $table = $wpdb->prefix . 'wpil_report_links';
        $threshold = 0.5000; // keep in sync with dashboard "related/unrelated" split
        $limit = max(1, (int) $limit);

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT post_id, post_type, target_id, target_type, raw_url, anchor, ai_relation_score, link_context
                    FROM {$table}
                    WHERE internal = 1
                        AND has_links > 0
                        AND location = 'content'
                        AND target_id > 0
                        AND ai_relation_score > 0
                        AND ai_relation_score < %f
                    ORDER BY ai_relation_score ASC
                    LIMIT %d",
                $threshold,
                $limit
            ),
            ARRAY_A
        );

        if(empty($rows)){
            return [];
        }

        $ignored_lookup = self::get_ignored_pid_lookup();
        $scope_lookup = [];
        if(!empty($scope_pids)){
            foreach($scope_pids as $pid){
                if(is_string($pid) && $pid !== ''){
                    $scope_lookup[$pid] = true;
                }
            }
        }

        $out = [];
        foreach($rows as $row){
            $source_pid = (($row['post_type'] === 'term') ? 'term_' : 'post_') . (int)$row['post_id'];
            $target_pid = (($row['target_type'] === 'term') ? 'term_' : 'post_') . (int)$row['target_id'];

            if(!empty($scope_lookup) && !isset($scope_lookup[$source_pid])){
                continue;
            }

            if(self::is_money_page_pid($source_pid)){
                continue;
            }

            if(isset($ignored_lookup[$source_pid]) || isset($ignored_lookup[$target_pid])){
                continue;
            }

            if(empty($row['raw_url']) || empty($row['anchor'])){
                continue;
            }

            $key = md5(
                $source_pid . '|' .
                $target_pid . '|' .
                (string)$row['raw_url'] . '|' .
                (string)$row['anchor'] . '|' .
                (string)$row['ai_relation_score']
            );

            $row['_source_pid'] = $source_pid;
            $row['_target_pid'] = $target_pid;
            $row['_key'] = $key;
            $out[] = $row;
        }

        return $out;
    }

    /**
     * Gets next link-quality row to process.
     **/
    public static function get_next_unprocessed_quality_row($rows = [], $processed_keys = []){
        if(empty($rows)){
            return [];
        }

        $lookup = [];
        foreach((array)$processed_keys as $k){
            if(is_string($k) && $k !== ''){
                $lookup[$k] = true;
            }
        }

        foreach($rows as $row){
            if(empty($row['_key'])){
                continue;
            }
            if(isset($lookup[$row['_key']])){
                continue;
            }
            return ['key' => $row['_key'], 'row' => $row];
        }

        return [];
    }

    /**
     * Processes a single low-relatedness link row and attempts replacement.
     **/
    public static function process_link_quality_row($row = [], $process_key = ''){
        if(empty($row) || empty($process_key)){
            return false;
        }

        if(!empty($row['_source_pid']) && self::is_money_page_pid($row['_source_pid'])){
            return false;
        }

        $source = new Wpil_Model_Post((int)$row['post_id'], $row['post_type']);
        if(empty($source) || !$source->check_if_post_exists()){
            return false;
        }

        $old_target = new Wpil_Model_Post((int)$row['target_id'], $row['target_type']);
        if(empty($old_target) || !$old_target->check_if_post_exists()){
            return false;
        }

        $current_score = isset($row['ai_relation_score']) ? (float)$row['ai_relation_score'] : 0;
        if($current_score <= 0){
            $current_score = (float) Wpil_AI::get_post_relationship_score($source, $old_target);
        }

        $candidates = self::get_link_quality_candidate_targets($source, $old_target, $process_key, $current_score);
        if(empty($candidates)){
            return false; // no viable candidates, no outbound AI call
        }

        $content = $source->getContent();
        if(empty($content)){
            return false;
        }

        $context = self::extract_link_context_for_quality_fix($content, $row['raw_url'], $row['anchor']);
        if(empty($context['paragraph'])){
            return false;
        }

        $targets = [];
        foreach($candidates as $pid => $data){
            $targets[] = array(
                'title' => $data['post']->getTitle(),
                'keywords' => !empty($data['keywords']) ? $data['keywords'] : [],
                'target_id' => $data['post']->id,
                'target_type' => $data['post']->type,
            );
        }

        if(empty($targets)){
            return false; // no viable outbound targets, skip call
        }

        $context_content = $context['paragraph'];
        if(!empty($context['sentence'])){
            $context_content = $context['sentence'] . "\n\n" . $context_content;
        }

        $input = array(
            'source' => array(
                'content' => Wpil_AI::prepare_post_content_for_linking($context_content),
                'keywords' => Wpil_TargetKeyword::get_active_keyword_list($source->id, $source->type),
            ),
            'targets' => $targets,
        );

        $encoded = wp_json_encode($input);
        if(empty($encoded)){
            return false;
        }

        $response = Wpil_AI::determine_linking_candidates($encoded, 'outbound');
        if(empty($response)){
            return false;
        }

        $choice = self::pick_best_quality_replacement_from_ai($response, $candidates);
        if(empty($choice) || empty($choice['pid']) || !isset($candidates[$choice['pid']])){
            return false;
        }

        $new_target = $candidates[$choice['pid']]['post'];
        $new_url = $new_target->getViewLink();
        if(empty($new_url)){
            return false;
        }
        $new_url = Wpil_Link::filter_staging_to_live_domain($new_url);

        $old_url = (string) $row['raw_url'];
        if(empty($old_url) || $old_url === $new_url){
            return false;
        }

        $old_sentence = $context['paragraph'];
        $new_sentence = str_replace($old_url, $new_url, $old_sentence);
        if($new_sentence === $old_sentence){
            $old_sentence = '';
            $new_sentence = '';
        }

        $updated = Wpil_Link::updateExistingLink(
            $source->id,
            $source->type,
            $old_url,
            $new_url,
            $row['anchor'],
            '',
            $old_sentence,
            $new_sentence
        );

        if(!$updated){
            return false;
        }

        Wpil_Report::update_post_in_link_table($source);
        return true;
    }

    /**
     * Finds better target posts/terms for a low-relatedness outbound link.
     **/
    public static function get_link_quality_candidate_targets($source = null, $old_target = null, $process_key = '', $current_score = 0){
        if(empty($source) || empty($old_target) || empty($process_key)){
            return [];
        }

        $source_pid = $source->get_pid();
        $map_item = Wpil_LinkMapping::get_relation_map_item($process_key, $source->id, $source->type, true);
        if(empty($map_item) || empty($map_item['related_posts'])){
            return [];
        }

        $ignored_lookup = self::get_ignored_pid_lookup();
        $existing_targets = self::get_source_internal_target_pid_lookup($source->id, $source->type);

        $max_inbound = (int)get_option('wpil_max_inbound_links_per_post', 0);
        $relation_data = Wpil_AI::get_embedding_relatedness_data($source->id, $source->type, true);

        $candidates = [];
        foreach($map_item['related_posts'] as $pid){
            $parts = self::parse_pid($pid);
            if(empty($parts['id'])){
                continue;
            }

            $candidate_pid = $parts['type'] . '_' . $parts['id'];
            if($candidate_pid === $source_pid){
                continue;
            }
            if($candidate_pid === $old_target->get_pid()){
                continue;
            }
            if(isset($existing_targets[$candidate_pid])){
                continue;
            }
            if(isset($ignored_lookup[$candidate_pid])){
                continue;
            }

            $candidate = new Wpil_Model_Post($parts['id'], $parts['type']);
            if(empty($candidate) || !$candidate->check_if_post_exists()){
                continue;
            }

            if($max_inbound > 0 && $candidate->getInboundInternalLinks(true) >= $max_inbound){
                continue;
            }

            $score = 0.0;
            if(!empty($relation_data) && isset($relation_data[$candidate_pid])){
                $score = (float)$relation_data[$candidate_pid];
            }else{
                $score = (float) Wpil_AI::get_post_relationship_score($source, $candidate);
            }

            if($score <= $current_score){
                continue;
            }

            $candidates[$candidate_pid] = [
                'pid' => $candidate_pid,
                'post' => $candidate,
                'score' => $score,
                'keywords' => Wpil_TargetKeyword::get_active_keyword_list($candidate->id, $candidate->type),
            ];
        }

        if(empty($candidates)){
            return [];
        }

        uasort($candidates, function($a, $b){
            if($a['score'] === $b['score']){
                return 0;
            }
            return ($a['score'] > $b['score']) ? -1 : 1;
        });

        return array_slice($candidates, 0, 8, true);
    }

    /**
     * Gets current internal target pid lookup for a source post/term.
     **/
    public static function get_source_internal_target_pid_lookup($post_id = 0, $post_type = 'post'){
        global $wpdb;

        if(empty($post_id)){
            return [];
        }

        $table = $wpdb->prefix . 'wpil_report_links';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DISTINCT target_id, target_type
                    FROM {$table}
                    WHERE post_id = %d
                        AND post_type = %s
                        AND internal = 1
                        AND has_links > 0
                        AND target_id > 0",
                (int)$post_id,
                (string)$post_type
            ),
            ARRAY_A
        );

        $lookup = [];
        if(!empty($rows)){
            foreach($rows as $row){
                $pid = (($row['target_type'] === 'term') ? 'term_' : 'post_') . (int)$row['target_id'];
                $lookup[$pid] = true;
            }
        }

        return $lookup;
    }

    /**
     * Builds a lookup of ignored post/term pids.
     **/
    public static function get_ignored_pid_lookup(){
        $lookup = [];

        $ignored = Wpil_Settings::getAllIgnoredPosts();
        if(!empty($ignored)){
            foreach($ignored as $pid){
                if(is_string($pid) && $pid !== ''){
                    $lookup[$pid] = true;
                }
            }
        }

        $orphan_ignored = Wpil_Settings::getIgnoreOrphanedPosts();
        if(!empty($orphan_ignored)){
            foreach($orphan_ignored as $pid){
                if(is_string($pid) && $pid !== ''){
                    $lookup[$pid] = true;
                }
            }
        }

        return $lookup;
    }

    /**
     * Extracts the paragraph and sentence around a specific link for AI reassessment.
     **/
    public static function extract_link_context_for_quality_fix($content = '', $url = '', $anchor = ''){
        $out = array(
            'paragraph' => '',
            'sentence' => '',
        );

        if(empty($content) || empty($url)){
            return $out;
        }

        $needle = preg_quote($url, '/');

        // 1) Prefer a containing paragraph/list/div/blockquote block.
        $block_regex = '/<(p|li|div|blockquote)\b[^>]*>[\s\S]*?href\s*=\s*([\'"])\s*' . $needle . '\s*\2[\s\S]*?<\/\1>/i';
        if(preg_match($block_regex, $content, $m)){
            $out['paragraph'] = $m[0];
        }else{
            // 2) Fallback to nearest sentence around matching anchor element.
            $anchor_regex = '/<a\b[^>]*href\s*=\s*([\'"])\s*' . $needle . '\s*\1[^>]*>[\s\S]*?<\/a>/i';
            if(preg_match($anchor_regex, $content, $a, PREG_OFFSET_CAPTURE)){
                $link_html = $a[0][0];
                $link_pos = (int)$a[0][1];
                $start = max(0, $link_pos - 450);
                $length = min(strlen($content) - $start, strlen($link_html) + 900);
                $out['paragraph'] = substr($content, $start, $length);
            }
        }

        if(empty($out['paragraph'])){
            return $out;
        }

        // Try to isolate one sentence containing the link.
        if(preg_match('/[^.!?]*<a\b[^>]*href\s*=\s*([\'"])\s*' . $needle . '\s*\1[^>]*>[\s\S]*?<\/a>[^.!?]*[.!?]?/i', $out['paragraph'], $s)){
            $out['sentence'] = trim($s[0]);
        }else{
            $out['sentence'] = trim($out['paragraph']);
        }

        // Keep context modest for model input.
        if(strlen($out['paragraph']) > 2200){
            $out['paragraph'] = substr($out['paragraph'], 0, 2200);
        }

        return $out;
    }

    /**
     * Picks the best AI suggestion that maps to valid replacement candidates.
     **/
    public static function pick_best_quality_replacement_from_ai($response = null, $candidates = []){
        if(empty($response) || empty($candidates)){
            return [];
        }

        $unwrapped = Wpil_AI::unwrap_linking_live_completion($response);
        if(empty($unwrapped['ok']) || empty($unwrapped['results'])){
            return [];
        }

        $best = [];
        $best_score = -1;

        foreach($unwrapped['results'] as $result){
            if(empty($result) || !is_object($result) || empty($result->target_id)){
                continue;
            }

            $target_type = (isset($result->target_type) && $result->target_type === 'term') ? 'term' : 'post';
            $target_id = $result->target_id;

            if(is_string($target_id) && strpos($target_id, '_') !== false){
                $parts = self::parse_pid($target_id);
                if(empty($parts['id'])){
                    continue;
                }
                $target_type = $parts['type'];
                $target_id = $parts['id'];
            }

            $pid = $target_type . '_' . (int)$target_id;
            if(!isset($candidates[$pid])){
                continue;
            }

            if(!isset($result->sentence_with_anchor_text) || false === strpos((string)$result->sentence_with_anchor_text, 'wpil-link-proposal')){
                continue;
            }

            $score = (float)$candidates[$pid]['score'];
            if($score > $best_score){
                $best_score = $score;
                $best = array(
                    'pid' => $pid,
                    'result' => $result,
                );
            }
        }

        return $best;
    }

    /**
     * Gets next unprocessed pid from string pid lists.
     **/
    public static function get_next_unprocessed_pid($pids = [], $processed = []){
        if(empty($pids)){
            return '';
        }

        $processed_lookup = [];
        foreach((array) $processed as $pid){
            $parts = self::parse_pid($pid);
            if(empty($parts['id'])){
                continue;
            }
            $processed_lookup[$parts['type'] . '_' . $parts['id']] = true;
        }

        foreach((array) $pids as $pid){
            $parts = self::parse_pid($pid);
            if(empty($parts['id'])){
                continue;
            }

            $normalized = $parts['type'] . '_' . $parts['id'];
            if(isset($processed_lookup[$normalized])){
                continue;
            }
            return $normalized;
        }

        return '';
    }

    private static function get_remaining_unprocessed_pids($pids = array(), $processed = array()){
        if(empty($pids)){
            return array();
        }

        $processed_lookup = array();
        foreach((array) $processed as $pid){
            $parts = self::parse_pid($pid);
            if(empty($parts['id'])){
                continue;
            }

            $processed_lookup[$parts['type'] . '_' . $parts['id']] = true;
        }

        $remaining = array();
        foreach((array) $pids as $pid){
            $parts = self::parse_pid($pid);
            if(empty($parts['id'])){
                continue;
            }

            $normalized = $parts['type'] . '_' . $parts['id'];
            if(isset($processed_lookup[$normalized])){
                continue;
            }

            $remaining[] = $normalized;
        }

        return array_values(array_unique($remaining));
    }

    private static function get_relation_map_gate_progress($process_key = '', $scope_pids = array()){
        if(!is_array($scope_pids) && $scope_pids !== ''){
            $total = max(1, Wpil_LinkMapping::get_relation_map_total_item_count($process_key, $scope_pids));
            $completed = Wpil_LinkMapping::get_relation_map_completed_item_count($process_key, $scope_pids);
            return max(1, min(99, (int) round(($completed / $total) * 100)));
        }

        $scope_pids = self::normalize_orphan_fix_pid_input($scope_pids);
        $total = max(1, count($scope_pids));
        $completed = Wpil_LinkMapping::get_relation_map_completed_item_count($process_key, $scope_pids);

        return max(1, min(99, (int) round(($completed / $total) * 100)));
    }

    private static function get_relation_map_ai_gate_state($process_key = '', $scope = Wpil_LinkMapping::RELATION_SCOPE_DEFAULT, $ready_threshold = 10){
        $scope_status = Wpil_LinkMapping::get_relation_map_scope_status($process_key, $scope);
        $map_processed = Wpil_LinkMapping::is_map_processed($process_key, $scope);
        $ready_count = isset($scope_status['pending_count']) ? (int) $scope_status['pending_count'] : 0;
        $scope_complete = !empty($scope_status['scope_complete']);

        return array(
            'status' => $scope_status,
            'map_processed' => $map_processed,
            'ready_count' => $ready_count,
            'scope_complete' => $scope_complete,
            'can_process_ai' => ($map_processed || $scope_complete || $ready_count > (int) $ready_threshold),
        );
    }

    private static function maybe_build_relation_map_for_ai($process_key = '', $scope = Wpil_LinkMapping::RELATION_SCOPE_DEFAULT, $ready_threshold = 10){
        $state = self::get_relation_map_ai_gate_state($process_key, $scope, $ready_threshold);
        if($state['can_process_ai']){
            $state['build_attempted'] = false;
            $state['build_finished'] = false;
            return $state;
        }

        $build_finished = Wpil_LinkMapping::build_relationship_map($process_key);
        $state = self::get_relation_map_ai_gate_state($process_key, $scope, $ready_threshold);
        $state['build_attempted'] = true;
        $state['build_finished'] = $build_finished;
        $state['can_process_ai'] = ($state['map_processed'] || $state['scope_complete'] || $state['ready_count'] > 0);

        return $state;
    }

    private static function normalize_pid_scope($pids = array()){
        $normalized = array();
        foreach((array) $pids as $pid){
            $parts = self::parse_pid($pid);
            if(empty($parts['id'])){
                continue;
            }

            $key = $parts['type'] . '_' . $parts['id'];
            $normalized[$key] = $key;
        }

        return array_values($normalized);
    }

    private static function get_relation_map_ai_progress($process_key = '', $scope_pids = array(), $min = 0, $max = 100, $available_only = false){
        $max = (int) $max;
        $min = (int) $min;
        if($max <= $min){
            return $max;
        }

        $counts = self::get_relation_map_ai_progress_counts($process_key, $scope_pids, $available_only);
        if($counts['total'] < 1){
            return $max;
        }

        $ratio = min(1, max(0, ($counts['done'] / max($counts['total'], 1))));

        return min($max, max($min, (int) round($min + (($max - $min) * $ratio))));
    }

    private static function get_relation_map_ai_progress_counts($process_key = '', $scope_pids = array(), $available_only = false){
        if(!is_array($scope_pids) && $scope_pids !== ''){
            if(!empty($available_only)){
                return array(
                    'total' => Wpil_LinkMapping::get_relation_map_available_item_count($process_key, $scope_pids),
                    'done' => Wpil_LinkMapping::get_relation_map_available_ai_processed_item_count($process_key, $scope_pids),
                );
            }

            $scope_status = Wpil_LinkMapping::get_relation_map_scope_status($process_key, $scope_pids);
            return array(
                'total' => Wpil_LinkMapping::get_relation_map_total_item_count($process_key, $scope_pids),
                'done' => isset($scope_status['done_count']) ? (int) $scope_status['done_count'] : 0,
            );
        }

        $scope_pids = self::normalize_pid_scope($scope_pids);
        if(!empty($available_only)){
            return array(
                'total' => Wpil_LinkMapping::get_relation_map_available_item_count($process_key, $scope_pids),
                'done' => Wpil_LinkMapping::get_relation_map_available_ai_processed_item_count($process_key, $scope_pids),
            );
        }

        $scope_status = Wpil_LinkMapping::get_relation_map_scope_status($process_key, $scope_pids);
        return array(
            'total' => count($scope_pids),
            'done' => isset($scope_status['done_count']) ? (int) $scope_status['done_count'] : 0,
        );
    }

    private static function should_use_available_ai_progress($job = array()){
        if(empty($job) || !is_array($job)){
            return false;
        }

        if(!empty($job['fix_type']) && $job['fix_type'] === 'custom_link_map'){
            return false;
        }

        return (!empty($job['process_current_map']) || !empty($job['specified_populated']));
    }

    private static function should_wait_for_relation_map_gate($job = array(), $process_key = '', $scope_pids = array()){
        if(!self::should_use_available_ai_progress($job)){
            return true;
        }

        return (Wpil_LinkMapping::get_relation_map_available_item_count($process_key, $scope_pids) < 1);
    }

    private static function get_relation_scope_state($process_key = '', $scope_pids = array()){
        if(!is_array($scope_pids) && $scope_pids !== ''){
            return Wpil_LinkMapping::get_relation_map_scope_status($process_key, $scope_pids);
        }

        return Wpil_LinkMapping::get_relation_map_scope_status($process_key, self::normalize_pid_scope($scope_pids));
    }

    private static function get_next_pending_relation_pid($process_key = '', $scope_pids = array()){
        $state = self::get_relation_scope_state($process_key, $scope_pids);
        return !empty($state['next_pending']) ? $state['next_pending'] : '';
    }

    private static function get_quality_source_pids($rows = array()){
        $pids = array();
        foreach((array) $rows as $row){
            if(empty($row['_source_pid'])){
                continue;
            }

            $parts = self::parse_pid($row['_source_pid']);
            if(empty($parts['id'])){
                continue;
            }

            $pid = $parts['type'] . '_' . $parts['id'];
            $pids[$pid] = $pid;
        }

        return array_values($pids);
    }

    private static function get_broken_link_source_pids($ids = array()){
        $pids = array();
        foreach((array) $ids as $id){
            $pid = self::get_broken_link_source_pid($id);
            if($pid === ''){
                continue;
            }

            $pids[$pid] = $pid;
        }

        return array_values($pids);
    }

    private static function get_next_ready_relation_pid($pids = array(), $processed = array(), $process_key = ''){
        if(empty($process_key) || empty($pids)){
            return '';
        }

        foreach(self::get_remaining_unprocessed_pids($pids, $processed) as $pid){
            if(Wpil_LinkMapping::has_completed_relation_map_item($process_key, $pid, true)){
                return $pid;
            }
        }

        return '';
    }

    private static function get_next_ready_quality_row($rows = array(), $processed_keys = array(), $process_key = ''){
        if(empty($process_key) || empty($rows)){
            return array();
        }

        $lookup = array();
        foreach((array) $processed_keys as $k){
            if(is_string($k) && $k !== ''){
                $lookup[$k] = true;
            }
        }

        foreach((array) $rows as $row){
            if(empty($row['_key']) || isset($lookup[$row['_key']]) || empty($row['_source_pid'])){
                continue;
            }

            if(Wpil_LinkMapping::has_completed_relation_map_item($process_key, $row['_source_pid'], true)){
                return array('key' => $row['_key'], 'row' => $row);
            }
        }

        return array();
    }

    private static function get_broken_link_source_pid($link_id = 0){
        global $wpdb;

        $link_id = (int) $link_id;
        if(empty($link_id)){
            return '';
        }

        $table = $wpdb->prefix . 'wpil_broken_links';
        $row = $wpdb->get_row($wpdb->prepare("SELECT post_id, post_type FROM {$table} WHERE id = %d", $link_id));
        if(empty($row) || empty($row->post_id)){
            return '';
        }

        $post_type = !empty($row->post_type) ? (string) $row->post_type : 'post';
        return (($post_type === 'term') ? 'term' : 'post') . '_' . (int) $row->post_id;
    }

    private static function get_next_ready_broken_link_id($ids = array(), $processed_ids = array(), $process_key = ''){
        global $wpdb;

        if(empty($process_key) || empty($ids)){
            return 0;
        }

        $processed_lookup = array();
        foreach((array) $processed_ids as $id){
            $id = (int) $id;
            if($id > 0){
                $processed_lookup[$id] = true;
            }
        }

        $table = $wpdb->prefix . 'wpil_broken_links';
        foreach((array) $ids as $id){
            $id = (int) $id;
            if(empty($id) || isset($processed_lookup[$id])){
                continue;
            }

            $link = $wpdb->get_row($wpdb->prepare("SELECT id, url, post_id, post_type FROM {$table} WHERE id = %d", $id));
            if(empty($link)){
                continue;
            }

            if(empty($link->url) || !Wpil_Link::isInternal($link->url)){
                return $id;
            }

            $source_pid = !empty($link->post_id) ? (((!empty($link->post_type) && $link->post_type === 'term') ? 'term' : 'post') . '_' . (int) $link->post_id) : '';
            if(empty($source_pid) || Wpil_LinkMapping::has_completed_relation_map_item($process_key, $source_pid, true)){
                return $id;
            }
        }

        return 0;
    }

    private static function get_remaining_broken_link_source_pids($ids = array(), $processed_ids = array()){
        $processed_lookup = array();
        foreach((array) $processed_ids as $id){
            $id = (int) $id;
            if($id > 0){
                $processed_lookup[$id] = true;
            }
        }

        $pids = array();
        foreach((array) $ids as $id){
            $id = (int) $id;
            if(empty($id) || isset($processed_lookup[$id])){
                continue;
            }

            $pid = self::get_broken_link_source_pid($id);
            if($pid !== ''){
                $pids[$pid] = $pid;
            }
        }

        return array_values($pids);
    }

    private static function get_broken_link_ids_for_source_pid($pid = '', $exclude_ids = array()){
        global $wpdb;

        $parts = self::parse_pid($pid);
        if(empty($parts['id'])){
            return array();
        }

        $table = $wpdb->prefix . 'wpil_broken_links';
        $post_type = ($parts['type'] === 'term') ? 'term' : 'post';
        $query = $wpdb->prepare(
            "SELECT id
                FROM {$table}
                WHERE ignore_link = 0
                    AND (`code` < 200 OR `code` > 299)
                    AND post_id = %d
                    AND post_type = %s
                ORDER BY created ASC",
            (int) $parts['id'],
            $post_type
        );

        $ids = $wpdb->get_col($query);
        if(empty($ids)){
            return array();
        }

        $exclude_lookup = array();
        foreach((array) $exclude_ids as $exclude_id){
            $exclude_id = (int) $exclude_id;
            if($exclude_id > 0){
                $exclude_lookup[$exclude_id] = true;
            }
        }

        $out = array();
        foreach($ids as $id){
            $id = (int) $id;
            if($id < 1 || isset($exclude_lookup[$id])){
                continue;
            }

            $out[] = $id;
        }

        return array_values(array_unique($out));
    }

    /**
     * Parses a pid value like "post_12" or "term_8".
     **/
    public static function parse_pid($pid = ''){
        $out = array(
            'type' => 'post',
            'id' => 0,
        );

        if(is_numeric($pid)){
            $out['id'] = (int) $pid;
            return $out;
        }

        if(!is_string($pid) || strpos($pid, '_') === false){
            return $out;
        }

        $bits = explode('_', $pid, 2);
        if(count($bits) !== 2){
            return $out;
        }

        $out['type'] = (trim($bits[0]) === 'term') ? 'term' : 'post';
        $out['id'] = (int) $bits[1];

        return $out;
    }

    /**
     * Checks if this pid belongs to one of the money pages we want to keep inbound-only.
     **/
    private static function is_money_page_pid($pid = ''){
        $parts = self::parse_pid($pid);
        if(empty($parts['id'])){
            return false;
        }

        return in_array($parts['type'] . '_' . $parts['id'], Wpil_Settings::get_money_page_pid_list(true), true);
    }

    /**
     * Gets a batch of broken link IDs.
     **/
    public static function get_broken_link_ids($limit = 200, $exclude_ids = []){
        global $wpdb;
        $table = $wpdb->prefix . 'wpil_broken_links';

        $limit = (int) $limit;
        $where = "WHERE `ignore_link` = 0 AND (`code` < 200 OR `code` > 299)";
        $query = "SELECT id FROM {$table} {$where}";

        if(!empty($exclude_ids) && is_array($exclude_ids)){
            $exclude_ids = array_values(array_unique(array_map('intval', $exclude_ids)));
            $exclude_ids = array_filter($exclude_ids, function($id){ return $id > 0; });
            if(!empty($exclude_ids)){
                $query .= " AND id NOT IN (" . implode(',', $exclude_ids) . ")";
            }
        }

        $query .= " ORDER BY `created` ASC";
        if($limit > 0){
            $query .= " LIMIT {$limit}";
        }
        $ids = $wpdb->get_col($query);

        return !empty($ids) ? array_values(array_unique(array_map('intval', $ids))) : [];
    }

    /**
     * Processes a single broken link.
     **/
    public static function process_broken_link_item($link_id = 0, $process_key = ''){
        if(empty($link_id)){
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'wpil_broken_links';
        $link = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $link_id));

        if(empty($link)){
            return false;
        }

        $redirect = Wpil_Link::get_url_redirection($link->url);
        if(!empty($redirect)){
            $redirect = Wpil_Link::apply_affiliate_params($link->url, $redirect);
            self::set_broken_link_replacement($link->id, $redirect, $link->url, 'redirect');
            return true;
        }

        $is_internal = Wpil_Link::isInternal($link->url);

        if($is_internal){
            $target_post = Wpil_Post::getPostByLink($link->url);
            if(!empty($target_post) && is_a($target_post, 'Wpil_Model_Post')){
                $target_url = $target_post->getViewLink();
                if(!empty($target_url)){
                    $target_url = Wpil_Link::apply_affiliate_params($link->url, $target_url);
                    self::set_broken_link_replacement($link->id, $target_url, $link->url, 'redirect');
                    return true;
                }
            }
        }

        if($is_internal){
            $replacement = self::get_ai_broken_link_replacement($link, $process_key, true);
            if(!empty($replacement)){
                $replacement = Wpil_Link::apply_affiliate_params($link->url, $replacement);
                self::set_broken_link_replacement($link->id, $replacement, $link->url, 'rewrite');
                return true;
            }
        }else{
            $replacement = self::get_ai_external_broken_link_replacement($link);
            if(!empty($replacement)){
                $replacement = Wpil_Link::apply_affiliate_params($link->url, $replacement);
                self::set_broken_link_replacement($link->id, $replacement, $link->url, 'rewrite');
                return true;
            }
        }

        self::set_broken_link_replacement($link->id, '', $link->url, 'delete');

        return true;
    }

    /**
     * Uses AI relation data to select a replacement URL for a broken link.
     **/
    public static function get_ai_broken_link_replacement($link = null, $process_key = '', $is_internal = false){
        if(empty($link)){
            return '';
        }

        $post = new Wpil_Model_Post($link->post_id, $link->post_type);
        if(empty($post) || !$post->check_if_post_exists()){
            return '';
        }

        if(empty($process_key)){
            $process_key = self::get_ai_fix_process_key('broken_links');
        }

        // ensure AI relation data exists for best results
        if(!Wpil_AI::has_ai_processed_data()){
            return '';
        }

        $map_processed = Wpil_LinkMapping::is_map_processed($process_key);
        if(!$map_processed){
            $results = Wpil_LinkMapping::build_relationship_map($process_key);
            if(!$results){
                return '';
            }
        }

        $map_item = Wpil_LinkMapping::get_relation_map_item($process_key, $post->id, $post->type, true);
        if(empty($map_item) || empty($map_item['related_posts'])){
            return '';
        }

        $related = $map_item['related_posts'];
        $related_pids = [];
        foreach((array)$related as $rp){
            $parts = self::parse_pid($rp);
            if(empty($parts['id'])){
                continue;
            }
            $related_pids[] = $parts['type'] . '_' . $parts['id'];
        }
        $related_pids = array_values(array_unique($related_pids));

        if(empty($related_pids)){
            return '';
        }

        // If this was an external link, try a quick AI-relatedness match to the best internal target.
        $threshold = Wpil_Settings::get_ai_auto_insert_relatedness_threshold();
        $best_pid = '';
        $best_score = 0;

        foreach($related_pids as $candidate_pid){
            if(Wpil_Base::overTimeLimit(5, 25)){
                break;
            }

            if($candidate_pid === $pid){
                continue;
            }

            $parts = self::parse_pid($candidate_pid);
            if(empty($parts['id'])){
                continue;
            }

            $candidate = new Wpil_Model_Post($parts['id'], $parts['type']);
            if(empty($candidate) || !$candidate->check_if_post_exists()){
                continue;
            }

            $score = Wpil_AI::get_post_relationship_score($post, $candidate);
            if($score > $best_score){
                $best_score = $score;
                $best_pid = $candidate_pid;
            }
        }

        if(!empty($best_pid) && $best_score >= $threshold){
            $parts = self::parse_pid($best_pid);
            if(empty($parts['id'])){
                return '';
            }
            $best = new Wpil_Model_Post($parts['id'], $parts['type']);
            return $best->getViewLink();
        }

        return '';
    }

    /**
     * Uses Link Whisper AI to locate a suitable external replacement URL for a broken link.
     **/
    public static function get_ai_external_broken_link_replacement($link = null){
        if(empty($link)){
            return '';
        }

        if(!Wpil_Settings::get_linkwhisper_ai_active() || !Wpil_Settings::get_linkwhisper_ai_token()){
            return '';
        }

        $post = new Wpil_Model_Post($link->post_id, $link->post_type);
        if(empty($post) || !$post->check_if_post_exists()){
            return '';
        }

        $broken_host = wp_parse_url($link->url, PHP_URL_HOST);
        $broken_host = !empty($broken_host) ? str_replace('www.', '', $broken_host) : '';

        $keywords = Wpil_TargetKeyword::get_keyword_list_by_posts($post->id);
        $keywords = (!empty($keywords) && isset($keywords[$post->id])) ? array_slice($keywords[$post->id], 0, 10): [];

        $prompt_base = array(
            'site_url' => get_home_url(),
            'post_title' => $post->getTitle(),
            'post_url' => $post->getViewLink(),
            'anchor_text' => $link->anchor,
            'broken_url' => $link->url,
            'sentence' => $link->sentence,
            'keywords' => $keywords,
        );

        $response = '';
        $used_strict = false;
        if(!empty($broken_host)){
            $prompt = array_merge($prompt_base, [
                'preferred_domain' => $broken_host,
                'rules' => array(
                    'Return a JSON object with keys "replacement_url" and "confidence".',
                    'If no suitable replacement exists on the preferred domain, set "replacement_url" to an empty string.',
                    'The replacement must be on the preferred domain.',
                ),
            ]);

            $response = Wpil_AI::call_linkwhisper_ai(wp_json_encode($prompt), 'gpt-5-mini', 'broken-link-replacement', array('response_format' => 'json_object'));
            $used_strict = true;
        }

        if(empty($response)){
            $prompt = array_merge($prompt_base, [
                'rules' => array(
                    'Return a JSON object with keys "replacement_url" and "confidence".',
                    'If no suitable replacement exists, set "replacement_url" to an empty string.',
                    'Prefer authoritative sources. Avoid affiliate or tracking URLs.',
                ),
            ]);

            $response = Wpil_AI::call_linkwhisper_ai(wp_json_encode($prompt), 'gpt-5-mini', 'broken-link-replacement', array('response_format' => 'json_object'));
            $used_strict = false;
        }

        if(empty($response)){
            return '';
        }

        $decoded = json_decode($response, true);
        $candidate = '';
        if(is_array($decoded) && !empty($decoded['replacement_url'])){
            $candidate = $decoded['replacement_url'];
        }else{
            if(preg_match('~https?://[^\s\"\\)]+~i', $response, $match)){
                $candidate = $match[0];
            }
        }

        $candidate = esc_url_raw($candidate);
        if(empty($candidate)){
            return '';
        }

        if(Wpil_Link::isInternal($candidate)){
            return '';
        }

        $old_clean = strtok($link->url, '#');
        $new_clean = strtok($candidate, '#');
        if(!empty($old_clean) && !empty($new_clean) && $old_clean === $new_clean){
            return '';
        }

        if(!empty($broken_host)){
            $candidate_host = wp_parse_url($candidate, PHP_URL_HOST);
            $candidate_host = !empty($candidate_host) ? str_replace('www.', '', $candidate_host) : '';
            if(!empty($candidate_host)){
                Wpil_Link::set_affiliate_params_for_host($broken_host, Wpil_Link::get_affiliate_query_params($link->url));
                if($candidate_host !== $broken_host && $used_strict){
                    // strict run failed to honor the preferred domain, so try relaxed prompt
                    $prompt = array_merge($prompt_base, [
                        'rules' => array(
                            'Return a JSON object with keys "replacement_url" and "confidence".',
                            'If no suitable replacement exists, set "replacement_url" to an empty string.',
                            'Prefer authoritative sources. Avoid affiliate or tracking URLs.',
                        ),
                    ]);

                    $response = Wpil_AI::call_linkwhisper_ai(wp_json_encode($prompt), 'gpt-5-mini', 'broken-link-replacement', array('response_format' => 'json_object'));
                    if(empty($response)){
                        return '';
                    }

                    $decoded = json_decode($response, true);
                    $candidate = '';
                    if(is_array($decoded) && !empty($decoded['replacement_url'])){
                        $candidate = $decoded['replacement_url'];
                    }else{
                        if(preg_match('~https?://[^\s\"\\)]+~i', $response, $match)){
                            $candidate = $match[0];
                        }
                    }

                    $candidate = esc_url_raw($candidate);
                    if(empty($candidate) || Wpil_Link::isInternal($candidate)){
                        return '';
                    }

                    $new_clean = strtok($candidate, '#');
                    if(!empty($old_clean) && !empty($new_clean) && $old_clean === $new_clean){
                        return '';
                    }
                }
            }
        }

        return $candidate;
    }

    /**
     * Saves a suggested replacement URL for a broken link.
     **/
    public static function set_broken_link_replacement($link_id = 0, $url = '', $old_url = '', $recommended_action = ''){
        if(empty($link_id)){
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'wpil_broken_links';

        if(!empty($old_url)){
            $old_host = wp_parse_url($old_url, PHP_URL_HOST);
            if(!empty($old_host)){
                $affiliate_params = Wpil_Link::get_affiliate_query_params($old_url);
                if(!empty($affiliate_params)){
                    Wpil_Link::set_affiliate_params_for_host($old_host, $affiliate_params);
                }
            }
        }

        $update = ['suggested_url_replacement' => $url];
        if(!empty($recommended_action)){
            $update['recommended_action'] = $recommended_action;
        }

        $wpdb->update($table, $update, ['id' => (int) $link_id]);
        return true;
    }

    /**
     * Runs the AI fix process for link coverage.
     **/
    public static function run_link_coverage_ai_fix($job = []){
        if(empty($job) || !is_array($job)){
            return $job;
        }
        $auto_insert = empty($job['link_mode']) || ('review' !== $job['link_mode']);

        if(empty($job['process_key'])){
            $job['process_key'] = self::get_ai_fix_process_key('link_coverage');
        }

        if(empty($job['stage'])){
            $job['stage'] = (Wpil_LinkMapping::get_relation_map_total_item_count($job['process_key'], Wpil_LinkMapping::RELATION_SCOPE_OUTBOUND) > 0) ? 'outbound' : 'inbound';
        }

        if($job['stage'] === 'outbound'){
            $outbound_scope = Wpil_LinkMapping::RELATION_SCOPE_OUTBOUND;
            if(Wpil_LinkMapping::get_relation_map_total_item_count($job['process_key'], $outbound_scope) < 1){
                $job['stage'] = 'inbound';
            }else{
                $outbound_gate = self::maybe_build_relation_map_for_ai($job['process_key'], $outbound_scope);
                $outbound_status = $outbound_gate['status'];
                $ready_count = isset($outbound_status['pending_count']) ? (int) $outbound_status['pending_count'] : 0;
                $scope_complete = !empty($outbound_status['scope_complete']);
                if(!$outbound_gate['can_process_ai']){
                    if(self::should_use_available_ai_progress($job) && Wpil_LinkMapping::get_relation_map_available_item_count($job['process_key'], $outbound_scope) < 1){
                        $job['stage'] = 'inbound';
                    }elseif(self::should_wait_for_relation_map_gate($job, $job['process_key'], $outbound_scope)){
                        $job['progress'] = self::get_relation_map_gate_progress($job['process_key'], $outbound_scope);
                        return $job;
                    }
                }

                if($job['stage'] === 'outbound'){
                    $next = !empty($outbound_status['next_pending']) ? $outbound_status['next_pending'] : '';
                    if(empty($next)){
                        if(!$scope_complete && !self::should_use_available_ai_progress($job)){
                            $job['progress'] = self::get_relation_map_gate_progress($job['process_key'], $outbound_scope);
                            return $job;
                        }

                        if($ready_count > 0){
                            $job['progress'] = self::get_relation_map_ai_progress($job['process_key'], $outbound_scope, 0, 50, self::should_use_available_ai_progress($job));
                            return $job;
                        }

                        $job['stage'] = 'inbound';
                    }else{
                        Wpil_LinkMapping::claim_relation_map_ai_item($job['process_key'], $next, Wpil_LinkMapping::RELATION_SCOPE_OUTBOUND);
                        self::process_link_coverage_outbound_post($next, $job['process_key'], $auto_insert);
                    }
                }
            }
        }

        if($job['stage'] === 'inbound'){
            $inbound_scope = Wpil_LinkMapping::RELATION_SCOPE_INBOUND;
            if(Wpil_LinkMapping::get_relation_map_total_item_count($job['process_key'], $inbound_scope) < 1){
                $job['status'] = 'complete';
                $job['progress'] = 100;
                return $job;
            }

            $inbound_gate = self::maybe_build_relation_map_for_ai($job['process_key'], $inbound_scope);
            $inbound_status = $inbound_gate['status'];
            $ready_count = isset($inbound_status['pending_count']) ? (int) $inbound_status['pending_count'] : 0;
            $scope_complete = !empty($inbound_status['scope_complete']);
            if(!$inbound_gate['can_process_ai']){
                if(self::should_use_available_ai_progress($job) && Wpil_LinkMapping::get_relation_map_available_item_count($job['process_key'], $inbound_scope) < 1){
                    $job['status'] = 'complete';
                    $job['progress'] = 100;
                    return $job;
                }elseif(self::should_wait_for_relation_map_gate($job, $job['process_key'], $inbound_scope)){
                    $job['progress'] = min(99, max(50, 50 + (int) round(self::get_relation_map_gate_progress($job['process_key'], $inbound_scope) / 2)));
                    return $job;
                }
            }

            $next = !empty($inbound_status['next_pending']) ? $inbound_status['next_pending'] : '';
            if(!empty($next)){
                Wpil_LinkMapping::claim_relation_map_ai_item($job['process_key'], $next, Wpil_LinkMapping::RELATION_SCOPE_INBOUND);
                self::process_link_coverage_inbound_post($next, $job['process_key'], $auto_insert);
            }else{
                if(!$scope_complete && !self::should_use_available_ai_progress($job)){
                    $job['progress'] = min(99, max(50, 50 + (int) round(self::get_relation_map_gate_progress($job['process_key'], $inbound_scope) / 2)));
                    return $job;
                }

                if($ready_count > 0){
                    $job['progress'] = self::get_relation_map_ai_progress($job['process_key'], $inbound_scope, 50, 100, self::should_use_available_ai_progress($job));
                    return $job;
                }

                $job['status'] = 'complete';
                $job['progress'] = 100;
                return $job;
            }
        }

        $outbound_scope = Wpil_LinkMapping::RELATION_SCOPE_OUTBOUND;
        $inbound_scope = Wpil_LinkMapping::RELATION_SCOPE_INBOUND;
        $use_available_progress = self::should_use_available_ai_progress($job);
        $outbound_counts = self::get_relation_map_ai_progress_counts($job['process_key'], $outbound_scope, $use_available_progress);
        $inbound_counts = self::get_relation_map_ai_progress_counts($job['process_key'], $inbound_scope, $use_available_progress);
        $outbound_total = $outbound_counts['total'];
        $inbound_total = $inbound_counts['total'];
        $outbound_done = $outbound_counts['done'];
        $inbound_done = $inbound_counts['done'];

        if($job['status'] !== 'complete'){
            if($job['stage'] === 'outbound'){
                $job['progress'] = ($outbound_total > 0) ? min(50, (int) round(($outbound_done / max($outbound_total, 1)) * 50)) : 50;
            }else{
                $inbound_progress = ($inbound_total > 0) ? (int) round(($inbound_done / max($inbound_total, 1)) * 50) : 50;
                $job['progress'] = min(100, 50 + $inbound_progress);
            }
        }

        return $job;
    }

    /**
     * Runs one tick of the Custom CSV Linking Map AI fix.
     *
     * Mirrors run_link_coverage_ai_fix (inbound + outbound scopes) but draws
     * its queue from the user-uploaded CSV plan rather than site-wide analysis.
     *
     * On the very first tick (specified_populated not yet set) we call
     * Wpil_CsvLinkMap::populate_specified_relations() which pre-marks items
     * whose relationships are fully specified in the CSV, skipping the
     * embedding-based relationship-building phase for those items.
     **/
    public static function run_custom_link_map_ai_fix($job = []){
        if(empty($job) || !is_array($job)){
            return $job;
        }

        $auto_insert = empty($job['link_mode']) || ('review' !== $job['link_mode']);

        if(empty($job['process_key'])){
            $job['process_key'] = Wpil_LinkMapping::get_dashboard_fix_process_key('custom_link_map');
        }

        // First tick: pre-populate map_data for fully-specified CSV relationships
        if(empty($job['specified_populated'])){
            Wpil_CsvLinkMap::populate_specified_relations($job['process_key'], Wpil_LinkMapping::RELATION_SCOPE_OUTBOUND);
            Wpil_CsvLinkMap::populate_specified_relations($job['process_key'], Wpil_LinkMapping::RELATION_SCOPE_INBOUND);
            $job['specified_populated'] = true;
        }

        if(empty($job['stage'])){
            $has_outbound = Wpil_LinkMapping::get_relation_map_total_item_count($job['process_key'], Wpil_LinkMapping::RELATION_SCOPE_OUTBOUND) > 0;
            $job['stage'] = $has_outbound ? 'outbound' : 'inbound';
        }

        $outbound_scope = Wpil_LinkMapping::RELATION_SCOPE_OUTBOUND;
        $inbound_scope  = Wpil_LinkMapping::RELATION_SCOPE_INBOUND;

        // ── Outbound stage ───────────────────────────────────────────────────
        if($job['stage'] === 'outbound'){
            if(Wpil_LinkMapping::get_relation_map_total_item_count($job['process_key'], $outbound_scope) < 1){
                $job['stage'] = 'inbound';
            }else{
                $gate = self::maybe_build_relation_map_for_ai($job['process_key'], $outbound_scope);

                if(!$gate['can_process_ai']){
                    $job['progress'] = self::get_relation_map_gate_progress($job['process_key'], $outbound_scope);
                    return $job;
                }

                $status     = $gate['status'];
                $next       = !empty($status['next_pending']) ? $status['next_pending'] : '';
                $ready      = isset($status['pending_count']) ? (int) $status['pending_count'] : 0;
                $scope_done = !empty($status['scope_complete']);

                if(empty($next)){
                    if(!$scope_done){
                        $job['progress'] = self::get_relation_map_gate_progress($job['process_key'], $outbound_scope);
                        return $job;
                    }
                    if($ready > 0){
                        $job['progress'] = self::get_relation_map_ai_progress($job['process_key'], $outbound_scope, 0, 50);
                        return $job;
                    }
                    $job['stage'] = 'inbound';
                }else{
                    Wpil_LinkMapping::claim_relation_map_ai_item($job['process_key'], $next, $outbound_scope);
                    // Merge any CSV-specified targets into the auto-discovered map_data
                    Wpil_CsvLinkMap::merge_specified_relations($job['process_key'], $next, $outbound_scope);
                    self::process_custom_link_map_outbound_post($next, $job['process_key'], $auto_insert);
                }
            }
        }

        // ── Inbound stage ────────────────────────────────────────────────────
        if($job['stage'] === 'inbound'){
            if(Wpil_LinkMapping::get_relation_map_total_item_count($job['process_key'], $inbound_scope) < 1){
                $job['status']   = 'complete';
                $job['progress'] = 100;
                return $job;
            }

            $gate = self::maybe_build_relation_map_for_ai($job['process_key'], $inbound_scope);

            if(!$gate['can_process_ai']){
                $job['progress'] = min(99, max(50, 50 + (int) round(self::get_relation_map_gate_progress($job['process_key'], $inbound_scope) / 2)));
                return $job;
            }

            $status     = $gate['status'];
            $next       = !empty($status['next_pending']) ? $status['next_pending'] : '';
            $ready      = isset($status['pending_count']) ? (int) $status['pending_count'] : 0;
            $scope_done = !empty($status['scope_complete']);

            if(!empty($next)){
                Wpil_LinkMapping::claim_relation_map_ai_item($job['process_key'], $next, $inbound_scope);
                // Merge any CSV-specified sources into the auto-discovered map_data
                Wpil_CsvLinkMap::merge_specified_relations($job['process_key'], $next, $inbound_scope);
                self::process_custom_link_map_inbound_post($next, $job['process_key'], $auto_insert);
            }else{
                if(!$scope_done){
                    $job['progress'] = min(99, max(50, 50 + (int) round(self::get_relation_map_gate_progress($job['process_key'], $inbound_scope) / 2)));
                    return $job;
                }
                if($ready > 0){
                    $job['progress'] = self::get_relation_map_ai_progress($job['process_key'], $inbound_scope, 50, 100);
                    return $job;
                }
                $job['status']   = 'complete';
                $job['progress'] = 100;
                return $job;
            }
        }

        // ── Progress calculation ─────────────────────────────────────────────
        $outbound_total = Wpil_LinkMapping::get_relation_map_total_item_count($job['process_key'], $outbound_scope);
        $inbound_total  = Wpil_LinkMapping::get_relation_map_total_item_count($job['process_key'], $inbound_scope);
        $outbound_done  = Wpil_LinkMapping::get_relation_map_ai_processed_item_count($job['process_key'], $outbound_scope);
        $inbound_done   = Wpil_LinkMapping::get_relation_map_ai_processed_item_count($job['process_key'], $inbound_scope);
        $outbound_pending = Wpil_LinkMapping::get_relation_map_pending_ai_item_count($job['process_key'], $outbound_scope);
        $inbound_pending  = Wpil_LinkMapping::get_relation_map_pending_ai_item_count($job['process_key'], $inbound_scope);
        $outbound_unprocessed = Wpil_LinkMapping::get_relation_map_unprocessed_item_count($job['process_key'], $outbound_scope);
        $inbound_unprocessed  = Wpil_LinkMapping::get_relation_map_unprocessed_item_count($job['process_key'], $inbound_scope);

        if(($outbound_total + $inbound_total) > 0 && $outbound_pending < 1 && $inbound_pending < 1 && $outbound_unprocessed < 1 && $inbound_unprocessed < 1){
            $job['status'] = 'complete';
            $job['progress'] = 100;
            return $job;
        }

        if($job['status'] !== 'complete'){
            if($job['stage'] === 'outbound'){
                $job['progress'] = ($outbound_total > 0) ? min(50, (int) round(($outbound_done / max($outbound_total, 1)) * 50)) : 50;
            }else{
                $inbound_progress = ($inbound_total > 0) ? (int) round(($inbound_done / max($inbound_total, 1)) * 50) : 50;
                $job['progress']  = min(100, 50 + $inbound_progress);
            }
        }

        return $job;
    }

    /**
     * Processes one outbound post from the custom CSV linking map.
     **/
    private static function process_custom_link_map_outbound_post($post_id, $process_key, $auto_insert = true){
        if(empty($post_id) || empty($process_key)){
            return;
        }
        $parts = self::parse_pid($post_id);
        if(empty($parts['id'])){
            return;
        }
        $pid  = $parts['type'] . '_' . $parts['id'];
        $data = Wpil_AI::create_link_suggestions_from_map($pid, $process_key, 'outbound');
        if(!empty($data)){
            Wpil_AI::save_ai_linking_suggestions($data, $process_key);
        }
        if($auto_insert){
            Wpil_AI::auto_create_links_from_suggestions((int) $parts['id'], $parts['type'], $process_key);
        }
        Wpil_LinkMapping::mark_relation_map_ai_processed($process_key, $pid, true, Wpil_LinkMapping::RELATION_SCOPE_OUTBOUND);
    }

    /**
     * Processes one inbound target post from the custom CSV linking map.
     **/
    private static function process_custom_link_map_inbound_post($post_id, $process_key, $auto_insert = true){
        if(empty($post_id) || empty($process_key)){
            return;
        }
        $parts = self::parse_pid($post_id);
        if(empty($parts['id'])){
            return;
        }
        $pid  = $parts['type'] . '_' . $parts['id'];
        $data = Wpil_AI::create_link_suggestions_from_map($pid, $process_key, 'inbound');
        self::handle_inbound_ai_fix_result($pid, $process_key, Wpil_LinkMapping::RELATION_SCOPE_INBOUND, $data, $auto_insert);
    }

    /**
     * Gets posts that need outbound internal link coverage.
     **/
    public static function get_link_coverage_outbound_post_ids($scope_pids = []){
        global $wpdb;

        $ids = [];
        $ignored = Wpil_Query::get_all_report_ignored_post_ids('p', ['orphaned' => true, 'hide_noindex' => true]);
        $statuses_query = Wpil_Query::postStatuses('p');
        $post_types = Wpil_Query::postTypes('p');

        if(Wpil_Settings::use_link_table_for_data() && Wpil_Report::link_table_is_created()){
            $link_report_table = $wpdb->prefix . 'wpil_report_links';
            $ids = $wpdb->get_col(
                "SELECT p.ID
                    FROM {$wpdb->posts} p
                    LEFT JOIN {$link_report_table} l 
                        ON p.ID = l.post_id 
                        AND l.post_type = 'post' 
                        AND l.internal = 1 
                        AND l.has_links > 0
                    WHERE 1=1 {$ignored} {$statuses_query} {$post_types}
                    GROUP BY p.ID
                    HAVING COUNT(l.post_id) < 3"
            );
        }else{
            $ids = $wpdb->get_col(
                "SELECT p.ID
                    FROM {$wpdb->posts} p
                    LEFT JOIN {$wpdb->postmeta} m 
                        ON p.ID = m.post_id 
                        AND m.meta_key = 'wpil_links_outbound_internal_count'
                    WHERE 1=1 {$ignored} {$statuses_query} {$post_types}
                        AND (m.meta_value IS NULL OR CAST(m.meta_value AS UNSIGNED) < 3)"
            );
        }

        if(!empty($ids)){
            $money_pages = Wpil_Settings::get_money_page_ids(true);
            if(!empty($money_pages)){
                $ids = array_diff($ids, $money_pages);
            }

            $ids = array_values(array_unique(array_map('intval', $ids)));
        }

        $pids = [];
        if(!empty($ids)){
            foreach($ids as $id){
                if((int)$id > 0){
                    $pids[] = 'post_' . (int)$id;
                }
            }
        }

        if(!empty(get_option('wpil_ai_process_all_terms', ''))){
            $term_ids = Wpil_Report::get_all_term_ids();
            if(!empty($term_ids)){
                foreach($term_ids as $term_id){
                    $term_id = (int) $term_id;
                    if($term_id <= 0){
                        continue;
                    }
                    $term = new Wpil_Model_Post($term_id, 'term');
                    if($term->getOutboundInternalLinks(true) < 3){
                        $pids[] = 'term_' . $term_id;
                    }
                }
            }
        }

        $pids = array_values(array_unique($pids));
        if(!empty($pids)){
            $pids = array_values(array_filter($pids, function($pid){
                return !self::is_money_page_pid($pid);
            }));
        }

        if(empty($scope_pids)){
            return $pids;
        }

        $scope_lookup = array_flip(array_values(array_filter((array)$scope_pids, 'is_string')));
        return array_values(array_filter($pids, function($pid) use ($scope_lookup){
            return isset($scope_lookup[$pid]);
        }));
    }

    /**
     * Gets posts that need inbound internal link coverage.
     **/
    public static function get_link_coverage_inbound_post_ids($scope_pids = []){
        global $wpdb;

        $ids = [];
        $ignored = Wpil_Query::get_all_report_ignored_post_ids('p', ['orphaned' => true, 'hide_noindex' => true]);
        $statuses_query = Wpil_Query::postStatuses('p');
        $post_types = Wpil_Query::postTypes('p');

        if(Wpil_Settings::use_link_table_for_data() && Wpil_Report::link_table_is_created()){
            $link_report_table = $wpdb->prefix . 'wpil_report_links';
            $ids = $wpdb->get_col(
                "SELECT p.ID
                    FROM {$wpdb->posts} p
                    LEFT JOIN {$link_report_table} l 
                        ON p.ID = l.target_id 
                        AND l.target_type = 'post' 
                        AND l.internal = 1 
                        AND l.has_links > 0
                    WHERE 1=1 {$ignored} {$statuses_query} {$post_types}
                    GROUP BY p.ID
                    HAVING COUNT(l.target_id) <= 0"
            );
        }else{
            $ids = $wpdb->get_col(
                "SELECT p.ID
                    FROM {$wpdb->posts} p
                    LEFT JOIN {$wpdb->postmeta} m 
                        ON p.ID = m.post_id 
                        AND m.meta_key = 'wpil_links_inbound_internal_count'
                    WHERE 1=1 {$ignored} {$statuses_query} {$post_types}
                        AND (m.meta_value IS NULL OR CAST(m.meta_value AS UNSIGNED) <= 0)"
            );
        }

        if(!empty($ids)){
            $ids = array_values(array_unique(array_map('intval', $ids)));
        }

        $pids = [];
        if(!empty($ids)){
            foreach($ids as $id){
                if((int)$id > 0){
                    $pids[] = 'post_' . (int)$id;
                }
            }
        }

        if(!empty(get_option('wpil_ai_process_all_terms', ''))){
            $term_ids = Wpil_Report::get_all_term_ids();
            if(!empty($term_ids)){
                foreach($term_ids as $term_id){
                    $term_id = (int) $term_id;
                    if($term_id <= 0){
                        continue;
                    }
                    $term = new Wpil_Model_Post($term_id, 'term');
                    if($term->getInboundInternalLinks(true) <= 0){
                        $pids[] = 'term_' . $term_id;
                    }
                }
            }
        }

        $pids = array_values(array_unique($pids));
        if(empty($scope_pids)){
            return $pids;
        }

        $scope_lookup = array_flip(array_values(array_filter((array)$scope_pids, 'is_string')));
        return array_values(array_filter($pids, function($pid) use ($scope_lookup){
            return isset($scope_lookup[$pid]);
        }));
    }

    /**
     * Gets the next unprocessed post id from a list.
     **/
    public static function get_next_unprocessed_id($ids = [], $processed = []){
        if(empty($ids)){
            return 0;
        }

        $processed = array_map('intval', (array) $processed);
        $processed_lookup = array_flip($processed);

        foreach($ids as $id){
            $id = (int) $id;
            if(isset($processed_lookup[$id])){
                continue;
            }
            return $id;
        }

        return 0;
    }

    /**
     * Handles outbound link coverage processing for a single post.
     **/
    public static function process_link_coverage_outbound_post($post_id = 0, $process_key = '', $auto_insert = true){
        if(empty($post_id) || empty($process_key)){
            return;
        }

        $parts = self::parse_pid($post_id);
        if(empty($parts['id'])){
            return;
        }

        $pid = $parts['type'] . '_' . $parts['id'];
        if(self::is_money_page_pid($pid)){
            Wpil_LinkMapping::mark_relation_map_ai_processed($process_key, $pid, true, Wpil_LinkMapping::RELATION_SCOPE_OUTBOUND);
            return;
        }

        $data = Wpil_AI::create_link_suggestions_from_map($pid, $process_key, 'outbound');
        if(!empty($data)){
            Wpil_AI::save_ai_linking_suggestions($data, $process_key);
        }
        if($auto_insert){
            Wpil_AI::auto_create_links_from_suggestions((int)$parts['id'], $parts['type'], $process_key);
        }
        Wpil_LinkMapping::mark_relation_map_ai_processed($process_key, $pid, true, Wpil_LinkMapping::RELATION_SCOPE_OUTBOUND);
    }

    /**
     * Handles inbound link coverage processing for a single post.
     **/
    public static function process_link_coverage_inbound_post($post_id = 0, $process_key = '', $auto_insert = true){
        if(empty($post_id) || empty($process_key)){
            return;
        }

        $parts = self::parse_pid($post_id);
        if(empty($parts['id'])){
            return;
        }

        $pid = $parts['type'] . '_' . $parts['id'];
        $data = Wpil_AI::create_link_suggestions_from_map($pid, $process_key, 'inbound');
        self::handle_inbound_ai_fix_result($pid, $process_key, Wpil_LinkMapping::RELATION_SCOPE_INBOUND, $data, $auto_insert);
    }
















}
