<?php

/**
 * Handles the CSV-based Custom Linking Map feature.
 *
 * Users upload a CSV file that prescribes which posts should link to which.
 * The parsed plan rows are stored in a WordPress option (wpil_csv_link_map_plan_rows)
 * and fed into the AI Linking system as a "custom_link_map" fix job that runs
 * through the same relation-map / AI-suggestion pipeline as the other
 * dashboard fix types.
 *
 * CSV columns:
 *   "Inbound Link Posts"   – post that should RECEIVE inbound links (the target)
 *   "Inbound Source Post"  – post that will link TO the target (blank = auto)
 *   "Outbound Link Posts"  – post that should CONTAIN outgoing links (the source)
 *   "Outbound Target Post" – post the source links TO (blank = auto)
 *
 * A row may use only inbound columns, only outbound columns, or both.
 * The same URL may appear on multiple rows to define multiple partners.
 */
class Wpil_CsvLinkMap
{
    const PLAN_CHUNK_SIZE = 250;
    const PARSE_BATCH_LINES = 250;
    const FINALIZE_BATCH_ITEMS = 120;
    const GROUP_BUCKET_COUNT = 32;
    const MAX_STORED_WARNINGS = 50;

    // -------------------------------------------------------------------------
    // Service registration
    // -------------------------------------------------------------------------

    public function register()
    {
        add_action('wp_ajax_wpil_csv_link_map_upload', [__CLASS__, 'ajax_handle_csv_upload']);
        add_action('wp_ajax_wpil_csv_link_map_clear',  [__CLASS__, 'ajax_clear_plan']);
        add_action('wp_ajax_wpil_csv_link_map_status', [__CLASS__, 'ajax_get_plan_status']);
        add_action('wp_ajax_wpil_csv_link_map_parse_step', [__CLASS__, 'ajax_process_parse_step']);
    }

    // -------------------------------------------------------------------------
    // Process key helpers
    // -------------------------------------------------------------------------

    public static function get_process_key()
    {
        return md5('custom-link-map');
    }

    public static function get_manage_url()
    {
        return admin_url('admin.php?page=link_whisper_csv_link_map');
    }

    public static function get_example_template_filename()
    {
        return 'link-whisper-csv-template.csv';
    }

    public static function get_example_template_rows()
    {
        $site_url = rtrim(home_url(), '/');

        return [
            ['Inbound Link Posts', 'Inbound Source Post', 'Outbound Link Posts', 'Outbound Target Post'],
            [$site_url . '/target-post/', $site_url . '/source-post/', '', ''],
            [$site_url . '/another-target/', '', '', ''],
            ['', '', $site_url . '/source-post/', $site_url . '/destination-post/'],
            ['', '', $site_url . '/another-source/', ''],
            [$site_url . '/pillar-post/', $site_url . '/supporting-post/', $site_url . '/supporting-post/', $site_url . '/pillar-post/'],
        ];
    }

    public static function has_active_plan()
    {
        return !empty(get_option('wpil_csv_link_map_has_plan', '')) || self::is_parse_job_active();
    }

    public static function has_ready_preview_map()
    {
        return !empty(get_option('wpil_csv_link_map_preview_ready', '')) && !self::is_parse_job_active();
    }

    public static function has_started_ai_run()
    {
        return !empty(get_option('wpil_csv_link_map_ai_run_started', ''));
    }

    public static function mark_ai_run_started()
    {
        update_option('wpil_csv_link_map_ai_run_started', '1', false);
    }

    public static function clear_ai_run_started()
    {
        delete_option('wpil_csv_link_map_ai_run_started');
    }

    public static function is_parse_job_active()
    {
        $job = self::get_parse_job();
        return !empty($job) && !empty($job['status']) && $job['status'] === 'running';
    }

    // -------------------------------------------------------------------------
    // AJAX handlers
    // -------------------------------------------------------------------------

    public static function ajax_handle_csv_upload()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Forbidden'], 403);
        }

        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'wpil_csv_link_map_nonce')) {
            wp_send_json_error(['message' => 'Invalid nonce'], 403);
        }

        if (empty($_FILES['csv_file']['tmp_name'])) {
            wp_send_json_error(['message' => 'No file uploaded.']);
        }

        $tmp  = sanitize_text_field(/*wp_unslash*/($_FILES['csv_file']['tmp_name']));
        $name = sanitize_file_name(wp_unslash($_FILES['csv_file']['name']));

        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'csv') {
            wp_send_json_error(['message' => 'Only CSV files are supported.']);
        }

        $result = self::parse_and_store_csv($tmp, $name);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success($result);
    }

    public static function ajax_process_parse_step()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Forbidden'], 403);
        }

        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'wpil_csv_link_map_nonce')) {
            wp_send_json_error(['message' => 'Invalid nonce'], 403);
        }

        $result = self::process_parse_job_step();
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success($result);
    }

    public static function ajax_clear_plan()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Forbidden'], 403);
        }

        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'wpil_csv_link_map_nonce')) {
            wp_send_json_error(['message' => 'Invalid nonce'], 403);
        }

        self::clear_plan();
        wp_send_json_success(['message' => 'Plan cleared.']);
    }

    public static function ajax_get_plan_status()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Forbidden'], 403);
        }

        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'wpil_csv_link_map_nonce')) {
            wp_send_json_error(['message' => 'Invalid nonce'], 403);
        }

        wp_send_json_success(self::get_plan_summary());
    }

    // -------------------------------------------------------------------------
    // CSV parsing
    // -------------------------------------------------------------------------

    /**
     * Moves the uploaded CSV into managed temp storage, creates an async parse
     * job, and returns the current status payload.
     */
    public static function parse_and_store_csv($file_path, $original_name = '')
    {
        if (!is_readable($file_path)) {
            return new WP_Error('unreadable', 'Uploaded file could not be read.');
        }

        $temp_path = self::move_uploaded_csv_to_temp($file_path, $original_name);
        if (is_wp_error($temp_path)) {
            return $temp_path;
        }

        self::clear_plan();
        $job = self::start_parse_job($temp_path);
        if (is_wp_error($job)) {
            self::maybe_delete_file($temp_path);
            return $job;
        }

        update_option('wpil_csv_link_map_has_plan', '1', false);
        return self::get_plan_summary();
    }

    public static function process_parse_job_step()
    {
        $job = self::get_parse_job();
        if (empty($job)) {
            return self::get_plan_summary();
        }

        if (!empty($job['status']) && $job['status'] === 'error') {
            return self::get_plan_summary();
        }

        if (!empty($job['status']) && $job['status'] !== 'running') {
            return self::get_plan_summary();
        }

        switch (!empty($job['phase']) ? $job['phase'] : 'parsing') {
            case 'parsing':
                $result = self::process_csv_parse_batch($job);
                break;
            case 'building':
                $result = self::process_preview_build_batch($job);
                break;
            case 'finalizing':
                $result = self::process_preview_finalize_batch($job);
                break;
            case 'complete':
                self::complete_parse_job($job);
                return self::get_plan_summary();
            case 'error':
                return self::get_plan_summary();
            default:
                $result = self::mark_parse_job_error($job, 'The custom linking parser entered an invalid state.');
                break;
        }

        if (is_wp_error($result)) {
            self::mark_parse_job_error($job, $result->get_error_message());
        }

        return self::get_plan_summary();
    }

    private static function start_parse_job($file_path)
    {
        if (!is_readable($file_path)) {
            return new WP_Error('unreadable', 'Uploaded file could not be read.');
        }

        $handle = fopen($file_path, 'r');
        if (!$handle) {
            return new WP_Error('open_failed', 'Could not open the uploaded file.');
        }

        $header = fgetcsv($handle);
        if (empty($header)) {
            fclose($handle);
            return new WP_Error('empty_file', 'The CSV file is empty.');
        }

        $col_map = self::map_csv_columns($header);
        if (empty($col_map)) {
            fclose($handle);
            return new WP_Error('invalid_header', 'Could not recognise the CSV headers. Expected: "Inbound Link Posts", "Inbound Source Post", "Outbound Link Posts", "Outbound Target Post".');
        }

        $offset = ftell($handle);
        fclose($handle);

        $job = [
            'status' => 'running',
            'phase' => 'parsing',
            'message' => 'Uploading complete. Parsing CSV rows...',
            'file_path' => $file_path,
            'file_size' => max(1, (int) @filesize($file_path)),
            'offset' => max(0, (int) $offset),
            'line' => 1,
            'headers' => $col_map,
            'warning_total' => 0,
            'warnings' => [],
            'stats' => self::get_default_raw_stats(),
            'finalize_scope' => 'outbound',
            'finalize_bucket' => 0,
            'finalize_offset' => 0,
            'finalize_processed' => 0,
            'queue_total' => 0,
            'started' => time(),
            'updated' => time(),
        ];

        self::save_parse_job($job);
        self::store_parse_errors([], 0);

        return $job;
    }

    private static function process_csv_parse_batch(&$job)
    {
        if (empty($job['file_path']) || !is_readable($job['file_path'])) {
            return new WP_Error('missing_file', 'The uploaded CSV could not be found. Please upload it again.');
        }

        $handle = fopen($job['file_path'], 'r');
        if (!$handle) {
            return new WP_Error('open_failed', 'Could not reopen the uploaded CSV file.');
        }

        if (!empty($job['offset'])) {
            fseek($handle, (int) $job['offset']);
        }

        $line_count = 0;
        $batch_rows = [];
        $batch_groups = [
            'inbound' => [],
            'outbound' => [],
        ];
        $batch_stats = self::get_default_raw_stats();
        $end_of_file = false;

        while ($line_count < self::PARSE_BATCH_LINES) {
            $data = fgetcsv($handle);
            if ($data === false) {
                $end_of_file = true;
                break;
            }

            $line_count++;
            $job['line'] = (int) $job['line'] + 1;
            $line = (int) $job['line'];

            $inbound_target_url = self::get_col($data, $job['headers'], 'inbound_target');
            $inbound_source_url = self::get_col($data, $job['headers'], 'inbound_source');
            $outbound_source_url = self::get_col($data, $job['headers'], 'outbound_source');
            $outbound_target_url = self::get_col($data, $job['headers'], 'outbound_target');

            if (!empty($inbound_target_url)) {
                $target_pid = self::url_to_pid($inbound_target_url);
                if (empty($target_pid)) {
                    self::append_parse_warning($job, 'Line ' . $line . ': could not resolve inbound target URL "' . $inbound_target_url . '".');
                } else {
                    $source_pid = '';
                    if (!empty($inbound_source_url)) {
                        $source_pid = self::url_to_pid($inbound_source_url);
                        if (empty($source_pid)) {
                            self::append_parse_warning($job, 'Line ' . $line . ': could not resolve inbound source URL "' . $inbound_source_url . '".');
                        }
                    }

                    if (empty($inbound_source_url) || !empty($source_pid)) {
                        self::append_batch_row($batch_rows, $batch_groups, $batch_stats, 'inbound', $source_pid, $target_pid);
                    }
                }
            }

            if (!empty($outbound_source_url)) {
                $source_pid = self::url_to_pid($outbound_source_url);
                if (empty($source_pid)) {
                    self::append_parse_warning($job, 'Line ' . $line . ': could not resolve outbound source URL "' . $outbound_source_url . '".');
                } else {
                    $target_pid = '';
                    if (!empty($outbound_target_url)) {
                        $target_pid = self::url_to_pid($outbound_target_url);
                        if (empty($target_pid)) {
                            self::append_parse_warning($job, 'Line ' . $line . ': could not resolve outbound target URL "' . $outbound_target_url . '".');
                        }
                    }

                    if (empty($outbound_target_url) || !empty($target_pid)) {
                        self::append_batch_row($batch_rows, $batch_groups, $batch_stats, 'outbound', $source_pid, $target_pid);
                    }
                }
            }
        }

        $job['offset'] = max(0, (int) ftell($handle));
        fclose($handle);

        if (!empty($batch_rows)) {
            self::append_plan_rows($batch_rows);
        }

        $job['stats'] = self::merge_raw_stats(isset($job['stats']) ? $job['stats'] : [], $batch_stats);
        self::persist_group_updates('inbound', $batch_groups['inbound'], $job, 'inbound_targets');
        self::persist_group_updates('outbound', $batch_groups['outbound'], $job, 'outbound_sources');

        if ($end_of_file) {
            if (empty($job['stats']['total_rows'])) {
                return self::mark_parse_job_error($job, 'No valid relationship rows could be parsed from the uploaded CSV.');
            }

            return self::initialize_preview_relation_map($job);
        }

        $job['message'] = 'Parsing CSV rows... line ' . number_format_i18n((int) $job['line']) . ' processed so far.';
        $job['updated'] = time();
        self::save_parse_job($job);

        return ['progress' => self::get_parse_job_progress($job)];
    }

    private static function initialize_preview_relation_map(&$job)
    {
        $process_key = self::get_process_key();
        $queue_rows = self::get_queue_rows();
        $job['queue_total'] = count($queue_rows);

        Wpil_LinkMapping::reset_relation_map_queue($process_key, $queue_rows);
        self::populate_specified_relations($process_key, Wpil_LinkMapping::RELATION_SCOPE_OUTBOUND);
        self::populate_specified_relations($process_key, Wpil_LinkMapping::RELATION_SCOPE_INBOUND);

        if (empty($queue_rows)) {
            $job['phase'] = 'finalizing';
            $job['message'] = 'Finishing your custom linking preview...';
            $job['updated'] = time();
            self::save_parse_job($job);
            return self::process_preview_finalize_batch($job);
        }

        $job['phase'] = 'building';
        $job['message'] = 'Building the preview map that will be sent to AI...';
        $job['updated'] = time();
        self::save_parse_job($job);

        return ['progress' => 60];
    }

    private static function process_preview_build_batch(&$job)
    {
        $process_key = self::get_process_key();
        Wpil_LinkMapping::build_relationship_map($process_key);

        $total = Wpil_LinkMapping::get_relation_map_total_item_count($process_key);
        $done = Wpil_LinkMapping::get_relation_map_completed_item_count($process_key);
        $processed = Wpil_LinkMapping::is_map_processed($process_key);

        if ($processed || ($total > 0 && $done >= $total)) {
            $job['phase'] = 'finalizing';
            $job['message'] = 'Finalizing the custom linking preview...';
            $job['finalize_scope'] = 'outbound';
            $job['finalize_bucket'] = 0;
            $job['finalize_offset'] = 0;
            $job['finalize_processed'] = 0;
            $job['updated'] = time();
            self::save_parse_job($job);
            return self::process_preview_finalize_batch($job);
        }

        $job['message'] = 'Building the preview map... ' . number_format_i18n($done) . ' of ' . number_format_i18n($total) . ' map items are ready.';
        $job['updated'] = time();
        self::save_parse_job($job);

        return ['progress' => self::get_parse_job_progress($job)];
    }

    private static function process_preview_finalize_batch(&$job)
    {
        $bucket_count = self::get_group_bucket_count();
        $processed_now = 0;
        $scope = !empty($job['finalize_scope']) ? $job['finalize_scope'] : 'outbound';
        $bucket = isset($job['finalize_bucket']) ? (int) $job['finalize_bucket'] : 0;
        $offset = isset($job['finalize_offset']) ? (int) $job['finalize_offset'] : 0;

        while ($processed_now < self::FINALIZE_BATCH_ITEMS) {
            if ($bucket >= $bucket_count) {
                if ($scope === 'outbound') {
                    $scope = 'inbound';
                    $bucket = 0;
                    $offset = 0;
                    continue;
                }
                break;
            }

            $entries = self::get_group_bucket_entries($scope, $bucket);
            if (empty($entries)) {
                $bucket++;
                $offset = 0;
                continue;
            }

            $queue_pids = array_keys($entries);
            sort($queue_pids, SORT_NATURAL);

            if ($offset >= count($queue_pids)) {
                $bucket++;
                $offset = 0;
                continue;
            }

            $queue_pid = $queue_pids[$offset];
            self::merge_specified_relations(
                self::get_process_key(),
                $queue_pid,
                $scope === 'inbound' ? Wpil_LinkMapping::RELATION_SCOPE_INBOUND : Wpil_LinkMapping::RELATION_SCOPE_OUTBOUND
            );

            $offset++;
            $processed_now++;
            $job['finalize_processed'] = isset($job['finalize_processed']) ? ((int) $job['finalize_processed'] + 1) : 1;
        }

        $job['finalize_scope'] = $scope;
        $job['finalize_bucket'] = $bucket;
        $job['finalize_offset'] = $offset;

        $all_done = ($scope === 'inbound' && $bucket >= $bucket_count);
        if ($all_done) {
            $summary = self::build_final_summary($job);
            self::save_summary_cache($summary);
            self::store_parse_errors(!empty($job['warnings']) ? $job['warnings'] : [], !empty($job['warning_total']) ? (int) $job['warning_total'] : 0);
            update_option('wpil_csv_link_map_preview_ready', '1', false);
            update_option('wpil_csv_link_map_has_plan', !empty($summary['has_plan']) ? '1' : '', false);
            self::complete_parse_job($job);
            return $summary;
        }

        $job['message'] = 'Finalizing the custom linking preview...';
        $job['updated'] = time();
        self::save_parse_job($job);

        return ['progress' => self::get_parse_job_progress($job)];
    }

    private static function complete_parse_job($job = [])
    {
        if (!empty($job['file_path'])) {
            self::maybe_delete_file($job['file_path']);
        }

        self::delete_parse_job();
    }

    private static function mark_parse_job_error(&$job, $message)
    {
        $message = is_string($message) ? trim($message) : 'The custom linking parser failed.';
        $job['status'] = 'error';
        $job['phase'] = 'error';
        $job['message'] = $message;
        $job['updated'] = time();
        self::save_parse_job($job);
        self::store_parse_errors(!empty($job['warnings']) ? $job['warnings'] : [], !empty($job['warning_total']) ? (int) $job['warning_total'] : 0);
        update_option('wpil_csv_link_map_has_plan', '', false);
        delete_option('wpil_csv_link_map_preview_ready');

        return new WP_Error('parse_error', $message);
    }

    // -------------------------------------------------------------------------
    // Column-mapping helpers
    // -------------------------------------------------------------------------

    private static function map_csv_columns($header)
    {
        $aliases = [
            'inbound_target'  => ['inbound link posts', 'inbound link post', 'inbound target post', 'inbound target posts'],
            'inbound_source'  => ['inbound source post', 'inbound source posts'],
            'outbound_source' => ['outbound link posts', 'outbound link post', 'outbound source post', 'outbound source posts'],
            'outbound_target' => ['outbound target post', 'outbound target posts', 'outbound destination post', 'outbound destination posts', 'outbound link target', 'outbound link targets'],
        ];

        $col_map = [];
        foreach ($header as $idx => $cell) {
            $lc = self::normalize_header_cell($cell);
            foreach ($aliases as $key => $options) {
                if (in_array($lc, $options, true) && !isset($col_map[$key])) {
                    $col_map[$key] = $idx;
                }
            }
        }

        if (!isset($col_map['inbound_target']) && !isset($col_map['outbound_source'])) {
            return [];
        }

        return $col_map;
    }

    private static function normalize_header_cell($cell)
    {
        $cell = is_string($cell) ? $cell : '';
        $cell = str_replace("\xEF\xBB\xBF", '', $cell);
        $cell = preg_replace('/\s+/', ' ', trim($cell));

        return strtolower($cell);
    }

    private static function get_col($data, $col_map, $key)
    {
        if (!isset($col_map[$key])) {
            return '';
        }
        $val = isset($data[$col_map[$key]]) ? trim($data[$col_map[$key]]) : '';
        return $val;
    }

    // -------------------------------------------------------------------------
    // URL → PID
    // -------------------------------------------------------------------------

    public static function url_to_pid($url)
    {
        $url  = trim($url);
        if (empty($url)) {
            return '';
        }
        $post = Wpil_Post::getPostByLink($url);
        if (empty($post) || empty($post->id)) {
            return '';
        }
        return $post->type . '_' . $post->id;
    }

    // -------------------------------------------------------------------------
    // Plan storage / retrieval
    // -------------------------------------------------------------------------

    /**
     * Returns all stored plan rows as an array of stdClass objects.
     * Each element has: ->source_pid, ->target_pid, ->row_type
     *
     * @param string|null $row_type  ROW_TYPE_INBOUND | ROW_TYPE_OUTBOUND | null (all)
     */
    public static function get_plan_rows($row_type = null)
    {
        $row_type = ($row_type === 'inbound' || $row_type === 'outbound') ? $row_type : null;
        $rows = [];
        $chunk_count = (int) get_option('wpil_csv_link_map_plan_chunk_count', 0);

        if ($chunk_count > 0) {
            for ($i = 0; $i < $chunk_count; $i++) {
                $chunk = self::get_plan_chunk($i);
                if (empty($chunk)) {
                    continue;
                }

                foreach ($chunk as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    if ($row_type !== null && (!isset($row['row_type']) || $row['row_type'] !== $row_type)) {
                        continue;
                    }
                    $rows[] = (object) $row;
                }
            }

            return $rows;
        }

        $raw = get_option('wpil_csv_link_map_plan_rows', '');
        if (empty($raw)) {
            return [];
        }

        $all = json_decode($raw); // stdClass objects — matches existing ->prop access throughout
        if (!is_array($all)) {
            return [];
        }

        if ($row_type !== null) {
            $all = array_values(array_filter($all, function ($r) use ($row_type) {
                return isset($r->row_type) && $r->row_type === $row_type;
            }));
        }

        return $all;
    }

    /**
     * Wipes the plan option and clears the wpil_relation_mapping queue for our process key.
     */
    public static function clear_plan()
    {
        global $wpdb;

        $process_key = self::get_process_key();
        $job = self::get_parse_job();
        if (!empty($job['file_path'])) {
            self::maybe_delete_file($job['file_path']);
        }

        self::delete_all_plan_chunks();
        self::delete_all_group_buckets();
        delete_option('wpil_csv_link_map_group_meta');
        delete_option('wpil_csv_link_map_summary_cache');
        delete_option('wpil_csv_link_map_parse_errors');
        delete_option('wpil_csv_link_map_parse_error_total');
        delete_option('wpil_csv_link_map_preview_ready');
        delete_option('wpil_csv_link_map_parse_job');
        delete_option('wpil_csv_link_map_has_plan');
        delete_option('wpil_csv_link_map_ai_run_started');

        delete_option('wpil_csv_link_map_plan_rows');
        delete_option('wpil_csv_link_map_parse_errors');
        Wpil_LinkMapping::delete_relation_map($process_key);
        delete_option('wpil_csv_link_map_has_plan');

        delete_option('wpil_ai_linking_' . $process_key);
        if (get_option('wpil_ai_linking_process_key', '') === $process_key) {
            delete_option('wpil_ai_linking_process_key');
        }

        delete_transient('wpil_review_served_' . get_current_user_id());
        delete_transient('wpil_doing_ai_fix_process');

        $registry = get_option('wpil_ai_fix_registry', []);
        if (is_array($registry)) {
            $changed = false;
            foreach ($registry as $key => $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                if (
                    (!empty($entry['fix_type']) && (string) $entry['fix_type'] === 'custom_link_map') ||
                    (!empty($entry['process_key']) && (string) $entry['process_key'] === $process_key)
                ) {
                    unset($registry[$key]);
                    $changed = true;
                }
            }

            if ($changed) {
                update_option('wpil_ai_fix_registry', $registry, false);
            }
        }


        if (class_exists('Wpil_AI')) {
            Wpil_AI::clear_credit_tracking_task_run('linking:' . $process_key);
        }

        if (class_exists('Wpil_Settings')) {
            Wpil_Settings::delete_ai_fix_special_options($process_key);
        }
    }

    // -------------------------------------------------------------------------
    // Summary
    // -------------------------------------------------------------------------

    public static function get_plan_summary()
    {
        $summary = self::get_default_summary();
        $summary['manage_url'] = self::get_manage_url();
        $summary['template_filename'] = self::get_example_template_filename();
        $summary['process_key'] = self::get_process_key();

        $cached = self::get_summary_cache();
        if (!empty($cached)) {
            $summary = array_merge($summary, $cached);
        }

        $job = self::get_parse_job();
        if (!empty($job)) {
            $job_stats = !empty($job['stats']) && is_array($job['stats']) ? $job['stats'] : self::get_default_raw_stats();
            return array_merge($summary, [
                'has_plan' => !empty(get_option('wpil_csv_link_map_has_plan', '')),
                'total_rows' => (int) $job_stats['total_rows'],
                'inbound_targets' => (int) $job_stats['inbound_targets'],
                'inbound_specified' => (int) $job_stats['inbound_specified'],
                'inbound_auto' => max(0, (int) $job_stats['inbound_total'] - (int) $job_stats['inbound_specified']),
                'outbound_sources' => (int) $job_stats['outbound_sources'],
                'outbound_specified' => (int) $job_stats['outbound_specified'],
                'outbound_auto' => max(0, (int) $job_stats['outbound_total'] - (int) $job_stats['outbound_specified']),
                'parse_status' => !empty($job['status']) ? $job['status'] : 'running',
                'parse_phase' => !empty($job['phase']) ? $job['phase'] : 'parsing',
                'parse_progress' => self::get_parse_job_progress($job),
                'parse_message' => !empty($job['message']) ? $job['message'] : self::get_phase_message(!empty($job['phase']) ? $job['phase'] : 'parsing'),
                'parse_errors' => !empty($job['warnings']) && is_array($job['warnings']) ? array_values($job['warnings']) : [],
                'parse_error_total' => !empty($job['warning_total']) ? (int) $job['warning_total'] : 0,
                'preview_ready' => false,
            ]);
        }

        if (!empty($cached)) {
            $summary = self::refresh_preview_metrics_from_relation_map($summary);
            $summary['parse_status'] = !empty($summary['has_plan']) ? 'complete' : 'idle';
            $summary['parse_phase'] = !empty($summary['has_plan']) ? 'complete' : 'idle';
            $summary['parse_progress'] = !empty($summary['has_plan']) ? 100 : 0;
            $summary['parse_message'] = !empty($summary['has_plan']) ? 'Custom linking preview is ready.' : '';
            $summary['preview_ready'] = self::has_ready_preview_map();
            $summary['parse_errors'] = self::get_parse_errors();
            $summary['parse_error_total'] = self::get_parse_error_total();

            if ($summary !== $cached) {
                self::save_summary_cache($summary);
            }

            return $summary;
        }

        if (self::has_active_plan()) {
            return array_merge($summary, self::build_legacy_summary_from_storage(), [
                'preview_ready' => self::has_ready_preview_map(),
                'parse_errors' => self::get_parse_errors(),
                'parse_error_total' => self::get_parse_error_total(),
            ]);
        }

        return $summary;

        $inbound_rows  = self::get_plan_rows('inbound');
        $outbound_rows = self::get_plan_rows('outbound');

        $inbound_targets    = array_values(array_unique(array_filter(array_column($inbound_rows, 'target_pid'))));
        $inbound_specified  = array_filter($inbound_rows, function ($r) { return !empty($r->source_pid); });
        $outbound_sources   = array_values(array_unique(array_filter(array_column($outbound_rows, 'source_pid'))));
        $outbound_specified = array_filter($outbound_rows, function ($r) { return !empty($r->target_pid); });

        return [
            'has_plan'           => !empty($inbound_rows) || !empty($outbound_rows),
            'total_rows'         => count($inbound_rows) + count($outbound_rows),
            'inbound_targets'    => count($inbound_targets),
            'inbound_specified'  => count($inbound_specified),
            'inbound_auto'       => count($inbound_rows) - count($inbound_specified),
            'outbound_sources'   => count($outbound_sources),
            'outbound_specified' => count($outbound_specified),
            'outbound_auto'      => count($outbound_rows) - count($outbound_specified),
            'process_key'        => self::get_process_key(),
            'credit_estimate'    => Wpil_AI::estimate_ai_linking_credit_cost(self::get_process_key(), true, count(self::get_queue_rows())),
            'manage_url'         => self::get_manage_url(),
            'template_filename'  => self::get_example_template_filename(),
            'parse_errors'       => self::get_parse_errors(),
        ];
    }

    // -------------------------------------------------------------------------
    // Queue row generation  (for Maintenance::get_ai_fix_queue_setup)
    // -------------------------------------------------------------------------

    /**
     * Returns rows in the format expected by Wpil_LinkMapping::seed_relation_map_queue.
     *
     * Inbound plan rows  → queue the TARGET post with INBOUND scope.
     * Outbound plan rows → queue the SOURCE post with OUTBOUND scope.
     */
    public static function get_queue_rows()
    {
        $queue_rows = [];
        $inbound_entries = self::get_grouped_scope_entries('inbound');
        $outbound_entries = self::get_grouped_scope_entries('outbound');

        foreach ($inbound_entries as $pid => $state) {
            if (empty($pid)) {
                continue;
            }

            $queue_rows[] = [
                'pid' => $pid,
                'work_scope' => Wpil_LinkMapping::RELATION_SCOPE_INBOUND,
                'is_pillar' => 1,
            ];
        }

        foreach ($outbound_entries as $pid => $state) {
            if (empty($pid)) {
                continue;
            }

            $has_specified = !empty($state['specified']);
            $can_auto_process = !empty($state['has_auto']) && Wpil_LinkMapping::is_pid_within_ai_processing_age($pid);
            if (!$has_specified && !$can_auto_process) {
                continue;
            }

            $queue_rows[] = [
                'pid' => $pid,
                'work_scope' => Wpil_LinkMapping::RELATION_SCOPE_OUTBOUND,
                'is_pillar' => 0,
            ];
        }

        return $queue_rows;

        $rows       = self::get_plan_rows();
        $inbound    = [];
        $outbound   = [];
        $queue_rows = [];

        foreach ($rows as $row) {
            if ($row->row_type === 'inbound' && !empty($row->target_pid)) {
                if (!isset($inbound[$row->target_pid])) {
                    $inbound[$row->target_pid] = ['has_specified' => false, 'has_auto' => false];
                }

                if (!empty($row->source_pid)) {
                    $inbound[$row->target_pid]['has_specified'] = true;
                } else {
                    $inbound[$row->target_pid]['has_auto'] = true;
                }
            } elseif ($row->row_type === 'outbound' && !empty($row->source_pid)) {
                if (!isset($outbound[$row->source_pid])) {
                    $outbound[$row->source_pid] = ['has_specified' => false, 'has_auto' => false];
                }

                if (!empty($row->target_pid)) {
                    $outbound[$row->source_pid]['has_specified'] = true;
                } else {
                    $outbound[$row->source_pid]['has_auto'] = true;
                }
            }
        }

        foreach ($inbound as $pid => $state) {
            $queue_rows[] = [
                'pid'        => $pid,
                'work_scope' => Wpil_LinkMapping::RELATION_SCOPE_INBOUND,
                'is_pillar'  => 1,
            ];
        }

        foreach ($outbound as $pid => $state) {
            $can_auto_process = !empty($state['has_auto']) && Wpil_LinkMapping::is_pid_within_ai_processing_age($pid);
            if (empty($state['has_specified']) && !$can_auto_process) {
                continue;
            }

            $queue_rows[] = [
                'pid'        => $pid,
                'work_scope' => Wpil_LinkMapping::RELATION_SCOPE_OUTBOUND,
                'is_pillar'  => 0,
            ];
        }

        return $queue_rows;
    }

    // -------------------------------------------------------------------------
    // Pre-population of specified relationships
    // -------------------------------------------------------------------------

    /**
     * Called once on the first runner tick (before AI processing starts).
     *
     * For posts where ALL CSV rows have specified partners: pre-populates
     * map_data so the relationship-building phase is skipped.
     *
     * For posts with mixed rows (some specified, some auto): stores the
     * specified partners as 'csv_pins' for merging after auto-discovery.
     */
    public static function populate_specified_relations($process_key, $scope)
    {
        $scope = Wpil_LinkMapping::normalize_relation_work_scope($scope);
        $group_scope = ($scope === Wpil_LinkMapping::RELATION_SCOPE_INBOUND) ? 'inbound' : 'outbound';
        $grouped = self::get_grouped_scope_entries($group_scope);

        if (empty($grouped)) {
            return;
        }

        foreach ($grouped as $queue_pid => $data) {
            $specified = !empty($data['specified']) ? array_values(array_unique(array_filter($data['specified']))) : [];
            if (empty($specified)) {
                continue;
            }

            $skip_auto_generation = (
                $scope === Wpil_LinkMapping::RELATION_SCOPE_OUTBOUND &&
                !Wpil_LinkMapping::is_pid_within_ai_processing_age($queue_pid)
            );

            if (empty($data['has_auto']) || $skip_auto_generation) {
                Wpil_LinkMapping::pre_populate_relation_map_item(
                    $process_key,
                    $queue_pid,
                    ['related_posts' => $specified],
                    $scope
                );
            } else {
                Wpil_LinkMapping::store_csv_pin_hints(
                    $process_key,
                    $queue_pid,
                    ['csv_pins' => $specified],
                    $scope
                );
            }
        }

        return;

        $scope    = Wpil_LinkMapping::normalize_relation_work_scope($scope);
        $row_type = ($scope === Wpil_LinkMapping::RELATION_SCOPE_INBOUND)
            ? 'inbound'
            : 'outbound';

        $plan_rows = self::get_plan_rows($row_type);
        if (empty($plan_rows)) {
            return;
        }

        // Group by the queued post
        $grouped = [];
        foreach ($plan_rows as $row) {
            $queue_pid   = ($row_type === 'inbound') ? $row->target_pid : $row->source_pid;
            $related_pid = ($row_type === 'inbound') ? $row->source_pid : $row->target_pid;

            if (empty($queue_pid)) {
                continue;
            }

            if (!isset($grouped[$queue_pid])) {
                $grouped[$queue_pid] = ['specified' => [], 'has_auto' => false];
            }

            if (empty($related_pid)) {
                $grouped[$queue_pid]['has_auto'] = true;
            } elseif (!in_array($related_pid, $grouped[$queue_pid]['specified'], true)) {
                $grouped[$queue_pid]['specified'][] = $related_pid;
            }
        }

        foreach ($grouped as $queue_pid => $data) {
            if (empty($data['specified'])) {
                continue; // purely auto – relationship building will handle it
            }

            $skip_auto_generation = (
                $row_type === 'outbound' &&
                !Wpil_LinkMapping::is_pid_within_ai_processing_age($queue_pid)
            );

            if (!$data['has_auto'] || $skip_auto_generation) {
                // All rows specified → pre-populate and mark item_processed = 1
                Wpil_LinkMapping::pre_populate_relation_map_item(
                    $process_key,
                    $queue_pid,
                    ['related_posts' => $data['specified']],
                    $scope
                );
            } else {
                // Mixed → store hints; item_processed stays 0 so auto runs
                Wpil_LinkMapping::store_csv_pin_hints(
                    $process_key,
                    $queue_pid,
                    ['csv_pins' => $data['specified']],
                    $scope
                );
            }
        }
    }

    /**
     * Merges CSV-specified partners into the auto-discovered map_data of $pid.
     * Called just before the AI processing step for each item.
     */
    public static function merge_specified_relations($process_key, $pid, $scope)
    {
        $scope = Wpil_LinkMapping::normalize_relation_work_scope($scope);
        $group_scope = ($scope === Wpil_LinkMapping::RELATION_SCOPE_INBOUND) ? 'inbound' : 'outbound';
        $entry = self::get_grouped_scope_entry($group_scope, $pid);
        if (empty($entry['specified'])) {
            return;
        }

        $parts = Wpil_LinkMapping::parse_pid($pid);
        if (empty($parts['id'])) {
            return;
        }

        $item = Wpil_LinkMapping::get_relation_map_item($process_key, $parts['id'], $parts['type'], true, true, $scope);
        $item = is_array($item) ? $item : [];
        $existing = self::extract_related_pids(isset($item['related_posts']) ? $item['related_posts'] : []);
        $merged = array_values(array_unique(array_merge($existing, $entry['specified'])));

        if ($merged !== $existing) {
            Wpil_LinkMapping::update_relation_map_item_data_preserve_state(
                $process_key,
                $pid,
                array_merge($item, ['related_posts' => $merged]),
                $scope
            );
        }

        return;

        $scope    = Wpil_LinkMapping::normalize_relation_work_scope($scope);
        $row_type = ($scope === Wpil_LinkMapping::RELATION_SCOPE_INBOUND)
            ? 'inbound'
            : 'outbound';

        $plan_rows = self::get_plan_rows($row_type);
        if (empty($plan_rows)) {
            return;
        }

        $norm_pid  = Wpil_LinkMapping::normalize_pid($pid);
        $specified = [];

        foreach ($plan_rows as $row) {
            $queue_pid   = ($row_type === 'inbound') ? $row->target_pid : $row->source_pid;
            $related_pid = ($row_type === 'inbound') ? $row->source_pid : $row->target_pid;

            if (Wpil_LinkMapping::normalize_pid($queue_pid) !== $norm_pid || empty($related_pid)) {
                continue;
            }

            if (!in_array($related_pid, $specified, true)) {
                $specified[] = $related_pid;
            }
        }

        if (empty($specified)) {
            return;
        }

        $parts = Wpil_LinkMapping::parse_pid($pid);
        if (empty($parts['id'])) {
            return;
        }

        $item     = Wpil_LinkMapping::get_relation_map_item($process_key, $parts['id'], $parts['type'], true, true, $scope);
        $item     = is_array($item) ? $item : [];
        $existing = isset($item['related_posts']) && is_array($item['related_posts']) ? $item['related_posts'] : [];
        $merged   = array_values(array_unique(array_merge($existing, $specified)));

        if ($merged !== $existing) {
            Wpil_LinkMapping::update_relation_map_item_data_preserve_state(
                $process_key,
                $pid,
                array_merge($item, ['related_posts' => $merged]),
                $scope
            );
        }
    }

    private static function get_default_summary()
    {
        return [
            'has_plan' => false,
            'total_rows' => 0,
            'inbound_targets' => 0,
            'inbound_specified' => 0,
            'inbound_auto' => 0,
            'outbound_sources' => 0,
            'outbound_specified' => 0,
            'outbound_auto' => 0,
            'source_posts_exact' => 0,
            'target_posts_exact' => 0,
            'potential_links_min' => 0,
            'potential_links_max' => 0,
            'process_key' => '',
            'credit_estimate' => 0,
            'manage_url' => '',
            'template_filename' => self::get_example_template_filename(),
            'parse_status' => 'idle',
            'parse_phase' => 'idle',
            'parse_progress' => 0,
            'parse_message' => '',
            'parse_errors' => [],
            'parse_error_total' => 0,
            'preview_ready' => false,
        ];
    }

    private static function get_default_raw_stats()
    {
        return [
            'total_rows' => 0,
            'inbound_total' => 0,
            'inbound_specified' => 0,
            'inbound_targets' => 0,
            'outbound_total' => 0,
            'outbound_specified' => 0,
            'outbound_sources' => 0,
        ];
    }

    private static function merge_raw_stats($base, $delta)
    {
        $merged = self::get_default_raw_stats();
        foreach ($merged as $key => $value) {
            $merged[$key] = (int) (isset($base[$key]) ? $base[$key] : 0) + (int) (isset($delta[$key]) ? $delta[$key] : 0);
        }

        return $merged;
    }

    private static function append_batch_row(&$batch_rows, &$batch_groups, &$batch_stats, $row_type, $source_pid, $target_pid)
    {
        $batch_rows[] = [
            'source_pid' => $source_pid,
            'target_pid' => $target_pid,
            'row_type' => $row_type,
        ];
        $batch_stats['total_rows']++;

        if ($row_type === 'inbound') {
            $batch_stats['inbound_total']++;
            if (!empty($source_pid)) {
                $batch_stats['inbound_specified']++;
            }

            if (!isset($batch_groups['inbound'][$target_pid])) {
                $batch_groups['inbound'][$target_pid] = ['specified' => [], 'has_auto' => false];
            }

            if (empty($source_pid)) {
                $batch_groups['inbound'][$target_pid]['has_auto'] = true;
            } elseif (!in_array($source_pid, $batch_groups['inbound'][$target_pid]['specified'], true)) {
                $batch_groups['inbound'][$target_pid]['specified'][] = $source_pid;
            }

            return;
        }

        $batch_stats['outbound_total']++;
        if (!empty($target_pid)) {
            $batch_stats['outbound_specified']++;
        }

        if (!isset($batch_groups['outbound'][$source_pid])) {
            $batch_groups['outbound'][$source_pid] = ['specified' => [], 'has_auto' => false];
        }

        if (empty($target_pid)) {
            $batch_groups['outbound'][$source_pid]['has_auto'] = true;
        } elseif (!in_array($target_pid, $batch_groups['outbound'][$source_pid]['specified'], true)) {
            $batch_groups['outbound'][$source_pid]['specified'][] = $target_pid;
        }
    }

    private static function append_parse_warning(&$job, $warning)
    {
        $warning = is_string($warning) ? trim($warning) : '';
        if ($warning === '') {
            return;
        }

        $job['warning_total'] = !empty($job['warning_total']) ? ((int) $job['warning_total'] + 1) : 1;
        if (empty($job['warnings']) || !is_array($job['warnings'])) {
            $job['warnings'] = [];
        }

        if (count($job['warnings']) < self::MAX_STORED_WARNINGS) {
            $job['warnings'][] = $warning;
        }
    }

    private static function get_parse_job_progress($job)
    {
        $phase = !empty($job['phase']) ? $job['phase'] : 'idle';
        if ($phase === 'parsing') {
            return min(55, (int) round((!empty($job['offset']) ? (int) $job['offset'] : 0) / max(1, !empty($job['file_size']) ? (int) $job['file_size'] : 1) * 55));
        }

        if ($phase === 'building') {
            $process_key = self::get_process_key();
            $total = Wpil_LinkMapping::get_relation_map_total_item_count($process_key);
            $done = Wpil_LinkMapping::get_relation_map_completed_item_count($process_key);
            return min(90, max(55, 55 + (($total > 0) ? (int) round(($done / max($total, 1)) * 35) : 35)));
        }

        if ($phase === 'finalizing') {
            $total = max(
                1,
                (int) (!empty($job['stats']['outbound_sources']) ? $job['stats']['outbound_sources'] : 0) +
                (int) (!empty($job['stats']['inbound_targets']) ? $job['stats']['inbound_targets'] : 0)
            );

            return min(99, max(90, 90 + (int) round((!empty($job['finalize_processed']) ? (int) $job['finalize_processed'] : 0) / $total * 10)));
        }

        if ($phase === 'complete') {
            return 100;
        }

        return 0;
    }

    private static function get_phase_message($phase)
    {
        switch ($phase) {
            case 'parsing':
                return 'Parsing CSV rows...';
            case 'building':
                return 'Building the preview map that will be sent to AI...';
            case 'finalizing':
                return 'Finalizing the custom linking preview...';
            case 'complete':
                return 'Custom linking preview is ready.';
            case 'error':
                return 'The custom linking preview failed.';
            default:
                return '';
        }
    }

    private static function get_parse_job()
    {
        $job = get_option('wpil_csv_link_map_parse_job', []);
        return is_array($job) ? $job : [];
    }

    private static function save_parse_job($job)
    {
        update_option('wpil_csv_link_map_parse_job', is_array($job) ? $job : [], false);
    }

    private static function delete_parse_job()
    {
        delete_option('wpil_csv_link_map_parse_job');
    }

    private static function get_summary_cache()
    {
        $summary = get_option('wpil_csv_link_map_summary_cache', []);
        return is_array($summary) ? $summary : [];
    }

    private static function save_summary_cache($summary)
    {
        update_option('wpil_csv_link_map_summary_cache', is_array($summary) ? $summary : [], false);
    }

    private static function append_plan_rows($rows)
    {
        $rows = array_values(array_filter((array) $rows, function ($row) {
            return is_array($row) && !empty($row['row_type']);
        }));
        if (empty($rows)) {
            return;
        }

        $chunk_count = (int) get_option('wpil_csv_link_map_plan_chunk_count', 0);
        foreach (array_chunk($rows, self::PLAN_CHUNK_SIZE) as $chunk) {
            update_option('wpil_csv_link_map_plan_chunk_' . $chunk_count, wp_json_encode(array_values($chunk)), false);
            $chunk_count++;
        }

        update_option('wpil_csv_link_map_plan_chunk_count', $chunk_count, false);
    }

    private static function get_plan_chunk($index)
    {
        $raw = get_option('wpil_csv_link_map_plan_chunk_' . (int) $index, '');
        if (empty($raw)) {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function delete_all_plan_chunks()
    {
        $chunk_count = (int) get_option('wpil_csv_link_map_plan_chunk_count', 0);
        for ($i = 0; $i < $chunk_count; $i++) {
            delete_option('wpil_csv_link_map_plan_chunk_' . $i);
        }

        delete_option('wpil_csv_link_map_plan_chunk_count');
    }

    private static function store_parse_errors($errors, $total = null)
    {
        $errors = array_values(array_filter(array_map('strval', (array) $errors)));
        update_option('wpil_csv_link_map_parse_errors', wp_json_encode($errors), false);
        update_option('wpil_csv_link_map_parse_error_total', (int) (!is_null($total) ? $total : count($errors)), false);
    }

    private static function get_parse_errors()
    {
        $raw = get_option('wpil_csv_link_map_parse_errors', '');
        if (empty($raw)) {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? array_values(array_filter(array_map('strval', $decoded))) : [];
    }

    private static function get_parse_error_total()
    {
        return (int) get_option('wpil_csv_link_map_parse_error_total', 0);
    }

    private static function get_group_bucket_count()
    {
        return self::GROUP_BUCKET_COUNT;
    }

    private static function get_group_bucket_option_name($scope, $bucket)
    {
        return 'wpil_csv_link_map_group_' . sanitize_key($scope) . '_' . (int) $bucket;
    }

    private static function get_group_bucket_index($queue_pid)
    {
        $hash = md5((string) $queue_pid);
        return hexdec(substr($hash, 0, 2)) % self::get_group_bucket_count();
    }

    private static function normalize_group_entry($entry)
    {
        $specified = [];
        if (!empty($entry['specified']) && is_array($entry['specified'])) {
            foreach ($entry['specified'] as $pid) {
                $pid = Wpil_LinkMapping::normalize_pid($pid);
                if (!empty($pid)) {
                    $specified[$pid] = $pid;
                }
            }
        }

        return [
            'specified' => array_values($specified),
            'has_auto' => !empty($entry['has_auto']),
        ];
    }

    private static function get_group_bucket_entries($scope, $bucket)
    {
        $raw = get_option(self::get_group_bucket_option_name($scope, $bucket), '');
        if (empty($raw)) {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function get_group_meta()
    {
        $meta = get_option('wpil_csv_link_map_group_meta', []);
        return is_array($meta) ? $meta : [];
    }

    private static function save_group_meta($meta)
    {
        update_option('wpil_csv_link_map_group_meta', is_array($meta) ? $meta : [], false);
    }

    private static function delete_all_group_buckets()
    {
        for ($bucket = 0; $bucket < self::get_group_bucket_count(); $bucket++) {
            delete_option(self::get_group_bucket_option_name('inbound', $bucket));
            delete_option(self::get_group_bucket_option_name('outbound', $bucket));
        }
    }

    private static function persist_group_updates($scope, $updates, &$job, $counter_key)
    {
        if (empty($updates)) {
            return;
        }

        $bucketed = [];
        foreach ($updates as $queue_pid => $entry) {
            if (empty($queue_pid)) {
                continue;
            }

            $bucket = self::get_group_bucket_index($queue_pid);
            if (!isset($bucketed[$bucket])) {
                $bucketed[$bucket] = [];
            }
            $bucketed[$bucket][$queue_pid] = self::normalize_group_entry($entry);
        }

        $meta = self::get_group_meta();
        if (!isset($meta[$counter_key])) {
            $meta[$counter_key] = 0;
        }

        foreach ($bucketed as $bucket => $bucket_updates) {
            $stored = self::get_group_bucket_entries($scope, $bucket);
            foreach ($bucket_updates as $queue_pid => $entry) {
                $is_new = !isset($stored[$queue_pid]);
                $stored_entry = $is_new ? ['specified' => [], 'has_auto' => false] : self::normalize_group_entry($stored[$queue_pid]);
                $stored[$queue_pid] = [
                    'specified' => array_values(array_unique(array_merge($stored_entry['specified'], $entry['specified']))),
                    'has_auto' => !empty($stored_entry['has_auto']) || !empty($entry['has_auto']),
                ];

                if ($is_new) {
                    $meta[$counter_key] = (int) $meta[$counter_key] + 1;
                    $job['stats'][$counter_key] = !empty($job['stats'][$counter_key]) ? ((int) $job['stats'][$counter_key] + 1) : 1;
                }
            }

            update_option(self::get_group_bucket_option_name($scope, $bucket), wp_json_encode($stored), false);
        }

        self::save_group_meta($meta);
    }

    private static function get_grouped_scope_entries($scope)
    {
        $entries = [];
        for ($bucket = 0; $bucket < self::get_group_bucket_count(); $bucket++) {
            $bucket_entries = self::get_group_bucket_entries($scope, $bucket);
            if (empty($bucket_entries)) {
                continue;
            }

            foreach ($bucket_entries as $queue_pid => $entry) {
                $entries[$queue_pid] = self::normalize_group_entry($entry);
            }
        }

        return $entries;
    }

    private static function get_grouped_scope_entry($scope, $queue_pid)
    {
        if (empty($queue_pid)) {
            return ['specified' => [], 'has_auto' => false];
        }

        $bucket = self::get_group_bucket_index($queue_pid);
        $entries = self::get_group_bucket_entries($scope, $bucket);
        if (!isset($entries[$queue_pid])) {
            return ['specified' => [], 'has_auto' => false];
        }

        return self::normalize_group_entry($entries[$queue_pid]);
    }

    private static function build_final_summary($job = [])
    {
        $raw_stats = !empty($job['stats']) && is_array($job['stats']) ? $job['stats'] : self::get_default_raw_stats();
        $preview = self::calculate_preview_summary_from_relation_map();

        return [
            'has_plan' => (int) $raw_stats['total_rows'] > 0,
            'total_rows' => (int) $raw_stats['total_rows'],
            'inbound_targets' => (int) $raw_stats['inbound_targets'],
            'inbound_specified' => (int) $raw_stats['inbound_specified'],
            'inbound_auto' => max(0, (int) $raw_stats['inbound_total'] - (int) $raw_stats['inbound_specified']),
            'outbound_sources' => (int) $raw_stats['outbound_sources'],
            'outbound_specified' => (int) $raw_stats['outbound_specified'],
            'outbound_auto' => max(0, (int) $raw_stats['outbound_total'] - (int) $raw_stats['outbound_specified']),
            'source_posts_exact' => (int) $preview['source_posts_exact'],
            'target_posts_exact' => (int) $preview['target_posts_exact'],
            'potential_links_min' => (int) $preview['potential_links_min'],
            'potential_links_max' => (int) $preview['potential_links_max'],
            'process_key' => self::get_process_key(),
            'credit_estimate' => Wpil_AI::estimate_ai_linking_credit_cost(self::get_process_key(), true, count(self::get_queue_rows())),
            'manage_url' => self::get_manage_url(),
            'template_filename' => self::get_example_template_filename(),
            'parse_status' => 'complete',
            'parse_phase' => 'complete',
            'parse_progress' => 100,
            'parse_message' => 'Custom linking preview is ready.',
            'parse_errors' => !empty($job['warnings']) ? array_values($job['warnings']) : self::get_parse_errors(),
            'parse_error_total' => !empty($job['warning_total']) ? (int) $job['warning_total'] : self::get_parse_error_total(),
            'preview_ready' => true,
        ];
    }

    private static function build_legacy_summary_from_storage()
    {
        $raw_stats = self::get_stored_raw_stats();
        $preview = self::calculate_preview_summary_from_relation_map();

        return [
            'has_plan' => (int) $raw_stats['total_rows'] > 0,
            'total_rows' => (int) $raw_stats['total_rows'],
            'inbound_targets' => (int) $raw_stats['inbound_targets'],
            'inbound_specified' => (int) $raw_stats['inbound_specified'],
            'inbound_auto' => max(0, (int) $raw_stats['inbound_total'] - (int) $raw_stats['inbound_specified']),
            'outbound_sources' => (int) $raw_stats['outbound_sources'],
            'outbound_specified' => (int) $raw_stats['outbound_specified'],
            'outbound_auto' => max(0, (int) $raw_stats['outbound_total'] - (int) $raw_stats['outbound_specified']),
            'source_posts_exact' => (int) $preview['source_posts_exact'],
            'target_posts_exact' => (int) $preview['target_posts_exact'],
            'potential_links_min' => (int) $preview['potential_links_min'],
            'potential_links_max' => (int) $preview['potential_links_max'],
            'credit_estimate' => Wpil_AI::estimate_ai_linking_credit_cost(self::get_process_key(), true, count(self::get_queue_rows())),
            'parse_status' => self::has_ready_preview_map() ? 'complete' : 'idle',
            'parse_phase' => self::has_ready_preview_map() ? 'complete' : 'idle',
            'parse_progress' => self::has_ready_preview_map() ? 100 : 0,
            'parse_message' => self::has_ready_preview_map() ? 'Custom linking preview is ready.' : '',
        ];
    }

    private static function get_stored_raw_stats()
    {
        $stats = self::get_default_raw_stats();
        $meta = self::get_group_meta();
        if (!empty($meta)) {
            $stats['inbound_targets'] = !empty($meta['inbound_targets']) ? (int) $meta['inbound_targets'] : 0;
            $stats['outbound_sources'] = !empty($meta['outbound_sources']) ? (int) $meta['outbound_sources'] : 0;
        }

        foreach (self::get_plan_rows() as $row) {
            if (empty($row->row_type)) {
                continue;
            }

            $stats['total_rows']++;
            if ($row->row_type === 'inbound') {
                $stats['inbound_total']++;
                if (!empty($row->source_pid)) {
                    $stats['inbound_specified']++;
                }
            } elseif ($row->row_type === 'outbound') {
                $stats['outbound_total']++;
                if (!empty($row->target_pid)) {
                    $stats['outbound_specified']++;
                }
            }
        }

        return $stats;
    }

    private static function refresh_preview_metrics_from_relation_map($summary)
    {
        if (empty($summary['has_plan']) || !self::has_ready_preview_map()) {
            return is_array($summary) ? $summary : self::get_default_summary();
        }

        $preview = self::calculate_preview_summary_from_relation_map();
        if (!is_array($summary)) {
            $summary = self::get_default_summary();
        }

        $summary['source_posts_exact'] = (int) $preview['source_posts_exact'];
        $summary['target_posts_exact'] = (int) $preview['target_posts_exact'];
        $summary['potential_links_min'] = (int) $preview['potential_links_min'];
        $summary['potential_links_max'] = (int) $preview['potential_links_max'];

        return $summary;
    }

    public static function calculate_preview_summary_from_relation_map()
    {
        global $wpdb;

        $table = $wpdb->prefix . 'wpil_relation_mapping';
        $process_key = self::get_process_key();
        $offset = 0;
        $limit = 250;
        $source_posts = [];
        $target_posts = [];
        $potential_min = 0;
        $potential_max = 0;

        while (true) {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT post_id, post_type, work_scope, map_data FROM {$table} WHERE process_key = %s ORDER BY id ASC LIMIT %d OFFSET %d",
                    $process_key,
                    $limit,
                    $offset
                )
            );

            if (empty($rows)) {
                break;
            }

            foreach ($rows as $row) {
                if (empty($row->post_id)) {
                    continue;
                }

                $map_data = Wpil_Toolbox::json_decompress(isset($row->map_data) ? $row->map_data : '');
                $map_data = is_object($map_data) ? $map_data : [];
                $related_pids = self::extract_related_pids(isset($map_data->related_posts) ? $map_data->related_posts : []);
                $relation_count = count($related_pids);
                if ($relation_count < 1) {
                    continue;
                }

                $queue_pid = (string) $row->post_type . '_' . (int) $row->post_id;
                $scope = Wpil_LinkMapping::normalize_relation_work_scope(isset($row->work_scope) ? $row->work_scope : '');

                if ($scope === Wpil_LinkMapping::RELATION_SCOPE_INBOUND) {
                    $target_posts[$queue_pid] = true;
                    foreach ($related_pids as $source_pid) {
                        $source_posts[$source_pid] = true;
                    }
                } else {
                    $source_posts[$queue_pid] = true;
                    foreach ($related_pids as $target_pid) {
                        $target_posts[$target_pid] = true;
                    }
                }

                $potential_max += $relation_count;
                $potential_min += (int) floor($relation_count / 2);
            }

            if (count($rows) < $limit) {
                break;
            }

            $offset += $limit;
        }

        return [
            'source_posts_exact' => count($source_posts),
            'target_posts_exact' => count($target_posts),
            'potential_links_min' => $potential_min,
            'potential_links_max' => $potential_max,
        ];
    }

    private static function extract_related_pids($related_posts)
    {
        $pids = [];
        foreach ((array) $related_posts as $key => $value) {
            $candidate = '';
            if (is_string($key) && preg_match('/^(post|term)_\d+$/', $key)) {
                $candidate = $key;
            } elseif (is_string($value) && preg_match('/^(post|term)_\d+$/', $value)) {
                $candidate = $value;
            } elseif (is_array($value) && !empty($value['pid']) && is_string($value['pid'])) {
                $candidate = $value['pid'];
            } elseif (is_object($value) && !empty($value->pid) && is_string($value->pid)) {
                $candidate = $value->pid;
            }

            $candidate = Wpil_LinkMapping::normalize_pid($candidate);
            if (!empty($candidate)) {
                $pids[$candidate] = $candidate;
            }
        }

        return array_values($pids);
    }

    private static function move_uploaded_csv_to_temp($tmp_path, $original_name = '')
    {
        $uploads = wp_upload_dir();
        if (empty($uploads['basedir'])) {
            return new WP_Error('upload_dir_missing', 'The upload directory is not available.');
        }

        $dir = trailingslashit($uploads['basedir']) . 'link-whisper/custom-link-map/';
        if (!wp_mkdir_p($dir)) {
            return new WP_Error('mkdir_failed', 'Could not create a temp folder for the CSV upload.');
        }

        $base_name = !empty($original_name) ? sanitize_file_name($original_name) : 'custom-link-map.csv';
        if (strtolower(pathinfo($base_name, PATHINFO_EXTENSION)) !== 'csv') {
            $base_name .= '.csv';
        }

        $target_name = wp_unique_filename($dir, $base_name);
        $target_path = trailingslashit($dir) . $target_name;

        $moved = @move_uploaded_file($tmp_path, $target_path);
        if (!$moved) {
            $moved = @copy($tmp_path, $target_path);
        }

        if (!$moved) {
            return new WP_Error('move_failed', 'Could not store the uploaded CSV for parsing.');
        }

        return $target_path;
    }

    private static function maybe_delete_file($path)
    {
        $path = is_string($path) ? $path : '';
        if ($path !== '' && file_exists($path)) {
            @unlink($path);
        }
    }

    // -------------------------------------------------------------------------
    // Admin page
    // -------------------------------------------------------------------------

    public static function render_admin_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to access this page.');
        }

        $summary = self::has_active_plan() ? self::get_plan_summary() : null;

        include WP_INTERNAL_LINKING_PLUGIN_DIR . 'templates/csv-link-map.php';
    }
}
