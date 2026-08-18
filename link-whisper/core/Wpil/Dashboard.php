<?php

/**
 * Class Wpil_Dashboard
 */
class Wpil_Dashboard
{
    public static $domain_relation_cache = array();

    public static function ajax_link_delay_waitlist_signup(){
        Wpil_Base::verify_nonce('wpil-link-delay-waitlist-signup');

        $user = wp_get_current_user();
        if(empty($user) || empty($user->ID)){
            wp_send_json_error(array('message' => __('User not found.', 'wpil')));
        }

        $email = '';
        if(!empty($_POST['email'])){
            $email = sanitize_email(wp_unslash($_POST['email']));
        }
        if(empty($email) && !empty($user->user_email)){
            $email = sanitize_email($user->user_email);
        }

        Wpil_Telemetry::log_event('link_delay_waitlist_signup', array(
            'email' => $email
        ));

        update_user_meta($user->ID, 'wpil_link_delay_waitlist_signup', time());

        wp_send_json_success(array(
            'email' => $email
        ));
    }

    public static function ajax_dashboard_experience_feedback(){
        Wpil_Base::verify_nonce('wpil-dashboard-experience-feedback');

        $user = wp_get_current_user();
        if(empty($user) || empty($user->ID)){
            wp_send_json_error(array('message' => __('User not found.', 'wpil')));
        }

        $response = '';
        if(!empty($_POST['response'])){
            $response = sanitize_text_field(wp_unslash($_POST['response']));
        }
        $response = ($response === 'yes') ? 'yes' : (($response === 'no') ? 'no' : '');
        if(empty($response)){
            wp_send_json_error(array('message' => __('Invalid response.', 'wpil')));
        }

        $email = '';
        if(!empty($_POST['email'])){
            $email = sanitize_email(wp_unslash($_POST['email']));
        }
        if(empty($email) && !empty($user->user_email)){
            $email = sanitize_email($user->user_email);
        }

        $message = '';
        if(isset($_POST['message'])){
            $message = sanitize_textarea_field(wp_unslash($_POST['message']));
            if(strlen($message) > 1000){
                $message = substr($message, 0, 1000);
            }
        }

        Wpil_Telemetry::log_event('dashboard_new_experience_feedback', array(
            'email' => $email,
            'response' => $response,
            'message' => $message
        ));

        wp_send_json_success(array(
            'email' => $email,
            'response' => $response
        ));
    }

    /**
     * Get posts count with selected types
     *
     * @return string|null
     */
    public static function getPostCount($age_limit = true)
    {
        global $wpdb;
        $report_table = $wpdb->prefix . 'wpil_report_links';
        $post_types = implode("','", Wpil_Settings::getPostTypes());
        $statuses_query = Wpil_Query::postStatuses();
        $date_limit = ($age_limit) ? Wpil_Query::getPostDateQueryLimit(): '';
        $report_date_limit = (!empty($date_limit)) ? " AND post_id IN (SELECT ID FROM {$wpdb->posts} WHERE 1=1 {$date_limit})": '';
        $post_date_limit = (!empty($date_limit)) ? Wpil_Query::getPostDateQueryLimit('p'): '';
        $ignoring = Wpil_Settings::hideIgnoredPosts();

        // if the user is removing ignored posts from the reports
        $ignored = "";
        if($ignoring){
            $ignored = Wpil_Query::ignoredPostIds();
        }

        $count = 0;
        // if we're using the link table for our data
        if(Wpil_Settings::use_link_table_for_data()){
            // lets save oursevles some headaches with giant metatables by just using the report link table
            $ignored = (!empty($ignored)) ? str_replace('p.ID', 'post_id', $ignored): "";
            $ignored = (!empty($ignored)) ? " AND (1=1 {$ignored})": '';
            $count = $wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$report_table} WHERE `post_type` = 'post' {$ignored} {$report_date_limit}");
            $taxonomies = Wpil_Settings::getTermTypes();
            if (!empty($taxonomies)) {
                $ignored = "";
                if($ignoring){
                    $ignored = Wpil_Query::ignoredTermIds();
                    $ignored = (!empty($ignored)) ? str_replace('t.term_id', 'post_id', $ignored): "";
                }
                $count += $wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$report_table} WHERE `post_type` = 'term' {$ignored}");
            }
        }else{
            $count = $wpdb->get_var("SELECT count(p.ID) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON p.ID = m.post_id WHERE post_type IN ('$post_types') $statuses_query {$ignored} {$post_date_limit} AND meta_key = 'wpil_sync_report3' AND meta_value = '1'");
            $taxonomies = Wpil_Settings::getTermTypes();
            if (!empty($taxonomies)) {
                $ignored = "";
                if($ignoring){
                    $ignored = Wpil_Query::ignoredTermIds();
                }
                $count += $wpdb->get_var("SELECT count(*) FROM {$wpdb->term_taxonomy} t WHERE t.taxonomy IN ('" . implode("', '", $taxonomies) . "') {$ignored}");
            }
        }

        return $count;
    }

    /**
     * Get all links count
     *
     * @return string|null
     */
    public static function getLinksCount($age_limit = true)
    {
        if (!Wpil_Report::link_table_is_created()) {
            return 0;
        }

        global $wpdb;

        // if the user is hiding the ignored posts, get the posts to ignore
        $ignored = Wpil_Query::getReportLinksIgnoreQueryStrings();
        $ignored = (!empty($ignored)) ? " AND (1=1 {$ignored})": '';
        $date_limit = ($age_limit) ? Wpil_Query::getPostDateQueryLimit(): '';
        $date_limit = (!empty($date_limit)) ? " AND ((`post_type` = 'post' AND `post_id` IN (SELECT ID FROM {$wpdb->posts} WHERE 1=1 {$date_limit})) OR `post_type` = 'term')": '';

        return $wpdb->get_var("SELECT count(*) FROM {$wpdb->prefix}wpil_report_links WHERE `has_links` = 1 {$ignored} {$date_limit}");
    }

    /**
     * Get internal links count
     *
     * @return string|null
     */
    public static function getInternalLinksCount($age_limit = true)
    {
        if (!Wpil_Report::link_table_is_created()) {
            return 0;
        }

        global $wpdb;

        // if the user is hiding the ignored posts, get the posts to ignore
        $ignored = Wpil_Query::getReportLinksIgnoreQueryStrings();
        $ignored = (!empty($ignored)) ? " AND (1=1 {$ignored})": '';
        $date_limit = ($age_limit) ? Wpil_Query::getPostDateQueryLimit(): '';
        $date_limit = (!empty($date_limit)) ? " AND ((`post_type` = 'post' AND `post_id` IN (SELECT ID FROM {$wpdb->posts} WHERE 1=1 {$date_limit})) OR `post_type` = 'term')": '';

        return (int) $wpdb->get_var("SELECT count(*) FROM {$wpdb->prefix}wpil_report_links WHERE `has_links` = 1 AND internal = 1 {$ignored} {$date_limit}");
    }

    /**
     * Get internal links count
     *
     * @return string|null
     */
    public static function getExternalLinksCount($age_limit = true)
    {
        if (!Wpil_Report::link_table_is_created()) {
            return 0;
        }

        global $wpdb;

        // if the user is hiding the ignored posts, get the posts to ignore
        $ignored = Wpil_Query::getReportLinksIgnoreQueryStrings();
        $ignored = (!empty($ignored)) ? " AND (1=1 {$ignored})": '';
        $date_limit = ($age_limit) ? Wpil_Query::getPostDateQueryLimit(): '';
        $date_limit = (!empty($date_limit)) ? " AND ((`post_type` = 'post' AND `post_id` IN (SELECT ID FROM {$wpdb->posts} WHERE 1=1 {$date_limit})) OR `post_type` = 'term')": '';

        return (int) $wpdb->get_var("SELECT count(*) FROM {$wpdb->prefix}wpil_report_links WHERE `has_links` = 1 AND internal = 0 {$ignored} {$date_limit}");
    }

    /**
     * Get posts count without inbound internal links
     *
     * @return string|null
     */
    public static function getOrphanedPostsCount()
    {
        global $wpdb;
        $link_report_table = $wpdb->prefix . 'wpil_report_links';

        $ignore_string = '';
        $ignored_ids = Wpil_Settings::getItemTypeIds(Wpil_Settings::getIgnoreOrphanedPosts(), 'post');

        if(Wpil_Settings::use_link_table_for_data()){
            if(!empty($ignored_ids)){
                $ignore_string = " AND ID NOT IN (" . implode(", ", $ignored_ids) . ")";
            }

            $statuses_query = Wpil_Query::postStatuses('a');
            $post_types = Wpil_Query::postTypes('a');

            $ids = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} a WHERE a.ID NOT IN (select distinct `target_id` from {$link_report_table} where `target_type` = 'post' and has_links > 0) {$ignore_string} {$statuses_query} {$post_types}");
        }else{
            if(!empty($ignored_ids)){
                $ignore_string = " AND m.post_id NOT IN (" . implode(", ", $ignored_ids) . ")";
            }

            $statuses_query = Wpil_Query::postStatuses('p');
            $post_types = Wpil_Query::postTypes('p');

            $ids = $wpdb->get_col(
                "SELECT DISTINCT m.post_id FROM {$wpdb->postmeta} m 
                    INNER JOIN {$wpdb->posts} p ON m.post_id = p.ID 
                    WHERE m.meta_key = 'wpil_links_inbound_internal_count' AND m.meta_value = 0 $ignore_string $statuses_query $post_types");
        }
        if(!empty($ids)){

            // if RankMath is active, remove any ids that are set to "noIndex"
            if(defined('RANK_MATH_VERSION')){
                $rank_math_meta = $wpdb->get_results("SELECT `post_id`, `meta_value` FROM {$wpdb->postmeta} WHERE `meta_key` = 'rank_math_robots'");
                $ids = array_flip($ids);
                foreach($rank_math_meta as $data){
                    if(false !== strpos($data->meta_value, 'noindex')){ // we can check the unserialized data because Rank Math uses a simple flag like structure to the saved data.
                        unset($ids[$data->post_id]);
                    }
                }
                $ids = array_flip($ids);
            }

            // if Yoast is active, remove any posts that are set to "noIndex"
            if(defined('WPSEO_VERSION')){
                $no_index_ids = $wpdb->get_col("SELECT DISTINCT `post_id` FROM {$wpdb->postmeta} WHERE `meta_key` = '_yoast_wpseo_meta-robots-noindex' AND meta_value = '1'");
                $ids = array_diff($ids, $no_index_ids);
            }

            // also remove any posts that are hidden by redirects
            $redirected = Wpil_Settings::getRedirectedPosts();
            $ids = array_diff($ids, $redirected);
        }

        // count the remaining ids
        $count = count($ids);

        // get if the user wants to include categories in the report
        $options = get_user_meta(get_current_user_id(), 'report_options', true);
        $show_categories = (!empty($options['show_categories']) && $options['show_categories'] == 'off') ? false : true;

        // if there are terms selected in the settings
        if (!empty(Wpil_Settings::getTermTypes()) && $show_categories) {

            if(Wpil_Settings::use_link_table_for_data()){
                $term_ids = $wpdb->get_col("SELECT a.term_id FROM {$wpdb->terms} a WHERE a.term_id NOT IN (select distinct `target_id` from {$link_report_table} where `target_type` = 'term' and has_links > 0)");
            }else{
                $term_ids = $wpdb->get_col("SELECT DISTINCT term_id FROM {$wpdb->prefix}termmeta WHERE meta_key = 'wpil_links_inbound_internal_count' AND meta_value = 0");
            }

            $ignored_ids = Wpil_Settings::getItemTypeIds(Wpil_Settings::getIgnoreOrphanedPosts(), 'term');
            if(!empty($ignored_ids)){
                $term_ids = array_diff($term_ids, $ignored_ids);
            }

            // if RankMath is active, remove any ids that are set to "noIndex"
            if(defined('RANK_MATH_VERSION')){
                foreach($term_ids as $key => $id){
                    $term = get_term($id);
                    if(is_a($term, 'WP_Error') || empty(\RankMath\Helper::is_term_indexable($term))){
                        unset($term_ids[$key]);
                    }
                }
            }

            // if Yoast is active rmeove any ids that are set to "noIndex"
            if(defined('WPSEO_VERSION')){
                $yoast_taxonomy_data = get_site_option('wpseo_taxonomy_meta');
                if(!empty($yoast_taxonomy_data)){
                    foreach($term_ids as $key => $id){
                        // if the category has been set to noIndex
                        if( isset($yoast_taxonomy_data[$id]) &&
                            isset($yoast_taxonomy_data[$id]['wpseo_noindex']) && 
                            'noindex' === $yoast_taxonomy_data[$id]['wpseo_noindex'])
                        {
                            // remove the id from the list
                            unset($term_ids[$key]);
                        }
                    }
                }
            }

            if(!empty($term_ids)){
                $taxonomies = Wpil_Query::taxonomyTypes();
                $term_ids = implode(',', $term_ids);
                $count += $wpdb->get_var("SELECT count(term_id) FROM {$wpdb->term_taxonomy} WHERE term_id IN ({$term_ids}) {$taxonomies}");
            }
        }

        return $count;
    }

    /** 
     * Really simple check if there's orphaned posts in the database.
     * Meant to be fast, so it only checks if there's a post/term that doens't have inbound internal links
     **/
    public static function hasOrphanedPosts(){
        global $wpdb;
        $link_report_table = $wpdb->prefix . 'wpil_report_links';

        $has_orphaned = $wpdb->get_row("SELECT * FROM {$wpdb->postmeta} WHERE meta_key = 'wpil_links_inbound_internal_count' AND meta_value = 0 LIMIT 1");

        if(empty($has_orphaned)){
            $has_orphaned = $wpdb->get_row("SELECT * FROM {$wpdb->termmeta} WHERE meta_key = 'wpil_links_inbound_internal_count' AND meta_value = 0 LIMIT 1");
        
            if(empty($has_orphaned)){
                $has_orphaned = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE `ID` NOT IN (select distinct `target_id` from {$link_report_table} where `target_type` = 'post') LIMIT 1");
            }
        }

        return !empty($has_orphaned);
    }

    /**
     * Get 10 most used domains from external links
     *
     * @return array
     */
    public static function getTopDomains()
    {
        if (!Wpil_Report::link_table_is_created()) {
            return [];
        }

        global $wpdb;

        // if the user is hiding the ignored posts, get the posts to ignore
        $ignored = Wpil_Query::getReportLinksIgnoreQueryStrings();

        $result = $wpdb->get_results("SELECT host, count(*) as `cnt` FROM {$wpdb->prefix}wpil_report_links WHERE host IS NOT NULL {$ignored} GROUP BY host ORDER BY count(*) DESC LIMIT 10");

        return $result;
    }

    /**
     * Get broken external links count
     *
     * @param array|string|false|null $filter_codes Optional list of error codes to count.
     * @return string|null
     */
    public static function getBrokenLinksCount($filter_codes = false)
    {
        global $wpdb;

        Wpil_Error::prepareTable(false);

        $where = " WHERE (`code` < 200 OR `code` > 299)";

        if(!empty(get_option('wpil_site_db_version', false))){
            $where .= " AND `ignore_link` != 1";
        }

        if(false !== $filter_codes && null !== $filter_codes){
            if(!is_array($filter_codes)){
                $filter_codes = explode(',', (string) $filter_codes);
            }

            $filter_codes = array_filter(array_unique(array_map('intval', $filter_codes)), function($code){
                return $code >= 0;
            });

            if(!empty($filter_codes)){
                $where .= " AND `code` IN (" . implode(',', $filter_codes) . ")";
            }
        }

        return $wpdb->get_var("SELECT count(*) FROM {$wpdb->prefix}wpil_broken_links{$where}");
    }

    /**
     * Get broken external links count
     *
     * @return string|null
     */
    public static function getBrokenVideoLinksCount()
    {
        global $wpdb;
        if(!empty(get_option('wpil_site_db_version', false))){
            return $wpdb->get_var("SELECT count(*) FROM {$wpdb->prefix}wpil_broken_links WHERE `ignore_link` != 1 AND (`code` = 825)");
        }else{
            return $wpdb->get_var("SELECT count(*) FROM {$wpdb->prefix}wpil_broken_links WHERE `code` < 825");
        }
    }

    /**
     * Get broken external links count
     *
     * @return array|null
     */
    public static function getAllErrorCodes()
    {
        global $wpdb;
        Wpil_Error::prepareTable(false);
        if(!empty(get_option('wpil_site_db_version', false))){
            return $wpdb->get_col("SELECT DISTINCT `code` FROM {$wpdb->prefix}wpil_broken_links WHERE `code` != 768");
        }

        return array();
    }

    /**
     * Get broken internal links count
     *
     * @return string
     */
    public static function get404LinksCount()
    {
        global $wpdb;
        return $wpdb->get_var("SELECT count(*) FROM {$wpdb->prefix}wpil_broken_links WHERE code = 404");
    }

    /**
     * Gets the total number of links inserted in the past 30 days
     **/
    public static function get_tracked_link_insert_count(){
        global $wpdb;
        $table = $wpdb->prefix . 'wpil_tracked_link_ids';
        $links_table = $wpdb->prefix . 'wpil_report_links';

        $thirty_days_ago = strtotime('30 days ago');

        return $wpdb->get_var("SELECT COUNT(*) FROM {$table} t INNER JOIN {$links_table} l ON l.`tracking_id` = t.`link_id` WHERE t.`creation_time` > {$thirty_days_ago} AND t.`link_id` > 0 AND l.`tracking_id` > 0");
    }

    /**
     * Gets the total number of links inserted that have been tracked.
     *
     * @return int
     */
    public static function get_tracked_link_insert_total_count(){
        global $wpdb;
        $table = $wpdb->prefix . 'wpil_tracked_link_ids';
        $links_table = $wpdb->prefix . 'wpil_report_links';

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} t INNER JOIN {$links_table} l ON l.`tracking_id` = t.`link_id` WHERE t.`link_id` > 0 AND l.`tracking_id` > 0");
    }

    /**
     * Gets the number of links inserted in the previous 30 day window.
     * Window: now-60 days through now-30 days.
     *
     * @return int
     */
    public static function get_tracked_link_insert_previous_count(){
        global $wpdb;
        $table = $wpdb->prefix . 'wpil_tracked_link_ids';
        $links_table = $wpdb->prefix . 'wpil_report_links';

        $sixty_days_ago = strtotime('60 days ago');
        $thirty_days_ago = strtotime('30 days ago');

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} t INNER JOIN {$links_table} l ON l.`tracking_id` = t.`link_id` WHERE t.`creation_time` > {$sixty_days_ago} AND t.`creation_time` <= {$thirty_days_ago} AND t.`link_id` > 0 AND l.`tracking_id` > 0");
    }

    /**
     * Gets the distribution of external links as a proportion of the overall total of external links.
     **/
    public static function get_external_link_distribution($limit = 0, $host = '', $age_limit = true){
        global $wpdb;
        $table = $wpdb->prefix . 'wpil_report_links';
        $cache_key = ($age_limit) ? 'limited': 'all';

        if(isset(self::$domain_relation_cache[$cache_key])){
            if(!empty($limit)){
                return array_slice(self::$domain_relation_cache[$cache_key], 0, $limit);
            }

            if(!empty($host)){
                foreach(self::$domain_relation_cache[$cache_key] as $domain){
                    if($domain->host === $host){
                        return $domain;
                    }
                }
            }
        }

        $date_limit = ($age_limit) ? Wpil_Query::getPostDateQueryLimit(): '';
        $date_limit = (!empty($date_limit)) ? " AND ((`post_type` = 'post' AND `post_id` IN (SELECT ID FROM {$wpdb->posts} WHERE 1=1 {$date_limit})) OR `post_type` = 'term')": '';
        self::$domain_relation_cache[$cache_key] = $wpdb->get_results("SELECT COUNT(*) AS 'link_count', host FROM {$table} WHERE `internal` = 0 {$date_limit} GROUP BY `host` ORDER BY `link_count` DESC");

        $total = 0;
        if(!empty(self::$domain_relation_cache[$cache_key])){
            foreach(self::$domain_relation_cache[$cache_key] as $dat){
                $total += $dat->link_count;
            }

            foreach(self::$domain_relation_cache[$cache_key] as &$dat){
                $dat->representation = $dat->link_count / $total;
            }
        }

        if(!empty($limit)){
            return array_slice(self::$domain_relation_cache[$cache_key], 0, $limit);
        }

        if(!empty($host)){
            foreach(self::$domain_relation_cache[$cache_key] as $domain){
                if($domain->host === $host){
                    return $domain;
                }
            }
        }

        return self::$domain_relation_cache[$cache_key];
    }

    /**
     * Get data for domains table
     *
     * @param $per_page
     * @param $page
     * @param $search
     * @return array
     */
    public static function getDomainsData($per_page, $page, $search, $search_type = 'domain', $show_attributes = true, $list_all = false, $show_untargeted = false)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wpil_report_links';

        $domains = [];
        $host_search = "";
        if(!empty($search)){
            if($search_type === 'domain'){
                $multi = false;
                if(false !== strpos($search, ',')){
                    $bits = explode(',', $search);
                    if(!empty($bits)){
                        $domain_search = array();
                        foreach($bits as $bit){
                            $bit = trim($bit);
                            if(!empty($bit)){
                                $domain_search[] = $wpdb->prepare("host LIKE %s", Wpil_Toolbox::esc_like($bit));
                            }
                        }

                        if(!empty($domain_search)){
                            $search = " AND " . implode(" OR ", $domain_search);
                            $multi = true;
                        }
                    }
                }

                if(empty($multi)){
                    $search = $wpdb->prepare(" AND host LIKE %s", Wpil_Toolbox::esc_like($search));
                }

            }elseif($search_type === 'links'){
                $multi = false;
                if(false !== strpos($search, ',')){
                    $bits = explode(',', $search);
                    if(!empty($bits)){
                        $links = array();
                        foreach($bits as $bit){
                            $bit = trim($bit);
                            if(!empty($bit)){
                                $links[] = $wpdb->prepare("raw_url LIKE %s", Wpil_Toolbox::esc_like(mb_ereg_replace('&', '&amp;', $bit)));
                            }
                        }

                        if(!empty($links)){
                            $search = " AND " . implode(" OR ", $links);
                            $multi = true;
                        }
                    }
                }

                if(empty($multi)){
                    $search = $wpdb->prepare(" AND raw_url LIKE %s", Wpil_Toolbox::esc_like(mb_ereg_replace('&', '&amp;', $search)));
                }
            }else{
                $search = '';
            }
        }else{
            $search = '';
        }

        $untargeted = '';
        if($show_untargeted){
            $untargeted = "AND link_id IN (SELECT d.link_id FROM {$table} d LEFT JOIN {$wpdb->posts} p ON d.target_id = p.ID WHERE (d.internal = 1 AND d.target_id = 0) OR (d.target_id > 0 AND d.target_type = 'post' AND p.ID IS NULL))";
        }

        $offset = $page > 0 ? (((int)$page - 1) * (int)$per_page) : 0;
        $limit = "LIMIT " . (int)$per_page . " OFFSET {$offset}";
        $hosts = $wpdb->get_results("SELECT host, count(host) as 'host_count' from {$table} WHERE host IS NOT NULL {$search} GROUP BY host ORDER BY host_count DESC {$limit}");

        if(!empty($hosts)){
            $found_hosts = array();
            foreach($hosts as $host){
                $found_hosts[] = $host->host;
            }

            $found_hosts = array_values(array_unique($found_hosts));
            if(!empty($found_hosts)){
                $placeholders = array_fill(0, count($found_hosts), '%s');
                $host_search = $wpdb->prepare(" AND host IN ('" . implode("', '", $placeholders) . "')", $found_hosts);
            }
        }

        $ignored = Wpil_Query::getReportLinksIgnoreQueryStrings();
        $result = (!empty($host_search)) ? $wpdb->get_results("SELECT * FROM {$table} WHERE host IS NOT NULL {$host_search} {$untargeted} {$search} {$ignored}"): array();
        $post_objs = array();
        foreach ($result as $link) {
            $host = $link->host;
            $id = $link->post_id;
            $type = $link->post_type;
            $cache_id = $type . $id;

            // if we haven't used this post yet
            if(!isset($post_objs[$cache_id])){
                // create it fresh for the post var
                $p = new Wpil_Model_Post($id, $type);
                // and then add it to the object array so we can use it later
                $post_objs[$cache_id] = $p;
            }else{
                // if we have used this post, obtain it from the object list
                $p = $post_objs[$cache_id];
            }

            if (empty($domains[$host])) {
                $dat = ['host' => $host, 'posts' => [], 'links' => []];
                $domains[$host] = $dat;
            }

            if (empty($domains[$host]['posts'][$id])) {
                $domains[$host]['posts'][$id] = $p;
            }

            if(count($domains[$host]['links']) < 100 || $list_all){
                $domains[$host]['links'][] = new Wpil_Model_Link([
                    'link_id' => $link->link_id,
                    'url' => $link->raw_url,
                    'anchor' => strip_tags($link->anchor),
                    'raw_anchor' => (isset($link->raw_anchor) && !empty($link->raw_anchor)) ? $link->raw_anchor : '',
                    'post' => $p,
                    'link_whisper_created' => (isset($link->link_whisper_created) && !empty($link->link_whisper_created)) ? 1: 0,
                    'is_autolink' => (isset($link->is_autolink) && !empty($link->is_autolink)) ? 1: 0,
                    'tracking_id' => (isset($link->tracking_id) && !empty($link->tracking_id)) ? $link->tracking_id: 0,
                    'module_link' => (isset($link->module_link) && !empty($link->module_link)) ? $link->module_link: 0,
                    'link_context' => (isset($link->link_context) && !empty($link->link_context)) ? $link->link_context: 0,
                    'ai_relation_score' => (isset($link->ai_relation_score) && !empty($link->ai_relation_score)) ? $link->ai_relation_score: 0,
                    'url_slug_word_count' => (isset($link->url_slug_word_count) && !empty($link->url_slug_word_count)) ? $link->url_slug_word_count: 0,
                    'anchor_slug_positional_match' => (isset($link->anchor_slug_positional_match) && is_numeric($link->anchor_slug_positional_match)) ? $link->anchor_slug_positional_match: 0,
                ]);
            }else{
                $domains[$host]['links'][] = false;
            }

            // get the protocol for the domain as best we can
            if(!isset($domains[$host]['protocol']) || 'https://' !== $domains[$host]['protocol']){
                if(false !== strpos($link->clean_url, 'https:')){
                    $domains[$host]['protocol'] = 'https://';
                }else{
                    $domains[$host]['protocol'] = 'http://';
                }
            }

        }

        usort($domains, function($a, $b){
            if (count($a['links']) == count($b['links'])) {
                return 0;
            }

            return (count($a['links']) < count($b['links'])) ? 1 : -1;
        });

        $total = $wpdb->get_var("SELECT COUNT(DISTINCT `host`) FROM {$table} WHERE host IS NOT NULL");

        return [
            'total' => $total,
            'domains' => $domains
        ];
    }

    /**
     * Gets the data for the domain dropdown via Ajax!
     * Intended to work in batches, so an offset is used to determine what links to pull
     * 
     **/
    public static function ajax_get_domains_dropdown_data(){
        global $wpdb;

        Wpil_Base::verify_nonce('wpil-collapsible-nonce');

        if(!isset($_POST['dropdown_type']) || !isset($_POST['host']) || !isset($_POST['item_count'])){
            wp_send_json(array('error' => array('title' => __('Data Missing', 'wpil'), 'text' => __('Some of the data required to load the rest of the dropdown is missing. Please reload the page and try opening the dropdown again.', 'wpil'))));
        }

        $search = (isset($_POST['search']) && !empty($_POST['search'])) ? trim($_POST['search']): '';
        $search_type = (isset($_POST['search_type']) && !empty($_POST['search_type'])) ? $_POST['search_type']: false;

        if(!empty($search)){
            if($search_type === 'domain'){
                $search = $wpdb->prepare(" AND host LIKE %s", Wpil_Toolbox::esc_like($search));
            }elseif($search_type === 'links'){
                $multi = false;
                if(false !== strpos($search, ',')){
                    $bits = explode(',', $search);
                    if(!empty($bits)){
                        $links = array();
                        foreach($bits as $bit){
                            $bit = trim($bit);
                            if(!empty($bit)){
                                $links[] = $wpdb->prepare("raw_url LIKE %s", Wpil_Toolbox::esc_like(mb_ereg_replace('&', '&amp;', $bit)));
                            }
                        }

                        if(!empty($links)){
                            $search = " AND " . implode(" OR ", $links);
                            $multi = true;
                        }
                    }
                }

                if(empty($multi)){
                    $search = $wpdb->prepare(" AND raw_url LIKE %s", Wpil_Toolbox::esc_like(mb_ereg_replace('&', '&amp;', $search)));
                }
            }else{
                $search = '';
            }
        }else{
            $search = '';
        }

        $offset = (isset($_POST['item_count'])) ? (int) $_POST['item_count']: 0;
        $limit = "LIMIT 200 OFFSET {$offset} ";
        $host = "AND host = '" . wp_parse_url(esc_url_raw($_POST['host']), PHP_URL_HOST) . "'";

        $ignored = Wpil_Query::getReportLinksIgnoreQueryStrings();
        $post_check = ($_POST['dropdown_type'] === 'posts') ? "GROUP BY post_id ORDER BY link_id ASC": "";
        $result = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wpil_report_links WHERE host IS NOT NULL {$host} {$search} {$ignored} {$post_check} {$limit}");

        $post_objs = array();
        $domain = array();
        foreach ($result as $link) {
            $id = $link->post_id;
            $type = $link->post_type;
            $cache_id = $type . $id;

            // if we haven't used this post yet
            if(!isset($post_objs[$cache_id])){
                // create it fresh for the post var
                $p = new Wpil_Model_Post($id, $type);
                // and then add it to the object array so we can use it later
                $post_objs[$cache_id] = $p;
            }else{
                // if we have used this post, obtain it from the object list
                $p = $post_objs[$cache_id];
            }

            if (empty($domain['posts'][$id])) {
                $domain['posts'][$id] = $p;
            }

            // get the protocol for the domain as best we can
            if(!isset($domain['protocol']) || 'https://' !== $domain['protocol']){
                if(false !== strpos($link->clean_url, 'https:')){
                    $domain['protocol'] = 'https://';
                }else{
                    $domain['protocol'] = 'http://';
                }
            }

            $domain['links'][] = new Wpil_Model_Link([
                'link_id' => $link->link_id,
                'url' => $link->raw_url,
                'anchor' => strip_tags($link->anchor),
                'raw_anchor' => (isset($link->raw_anchor) && !empty($link->raw_anchor)) ? $link->raw_anchor : '',
                'post' => $p,
                'link_whisper_created' => (isset($link->link_whisper_created) && !empty($link->link_whisper_created)) ? 1: 0,
                'is_autolink' => (isset($link->is_autolink) && !empty($link->is_autolink)) ? 1: 0,
                'tracking_id' => (isset($link->tracking_id) && !empty($link->tracking_id)) ? $link->tracking_id: 0,
                'module_link' => (isset($link->module_link) && !empty($link->module_link)) ? $link->module_link: 0,
                'link_context' => (isset($link->link_context) && !empty($link->link_context)) ? $link->link_context: 0,
                'ai_relation_score' => (isset($link->ai_relation_score) && !empty($link->ai_relation_score)) ? $link->ai_relation_score: 0,
                'url_slug_word_count' => (isset($link->url_slug_word_count) && !empty($link->url_slug_word_count)) ? $link->url_slug_word_count: 0,
                'anchor_slug_positional_match' => (isset($link->anchor_slug_positional_match) && is_numeric($link->anchor_slug_positional_match)) ? $link->anchor_slug_positional_match: 0
            ]);
        }

        // now that we have the data, it's time to format it!
        $response = '';
        $county = 0;
        if($_POST['dropdown_type'] === 'posts'){
            $county = count($domain['posts']);
            foreach($domain['posts'] as $post){
                $response .= '<li>'
                . esc_html($post->getTitle()) . '<br>
                <a href="' . admin_url('post.php?post=' . (int)$post->id . '&action=edit') . '" target="_blank">[edit]</a> 
                <a href="' . esc_url($post->getLinks()->view) . '" target="_blank">[view]</a><br><br>
              </li>';
            }
        }else{
            $county = count($domain['links']);
            foreach($domain['links'] as $link){
                $response .= '<li>
                    <input type="checkbox" class="wpil_link_select" data-post_id="'.$link->post->id.'" data-post_type="'.$link->post->type.'" data-anchor="' . esc_attr(base64_encode($link->raw_anchor)) . '" data-url="'.base64_encode($link->url).'">
                    <div>
                        <div style="margin: 3px 0;"><b>Post Title:</b> <a href="' . esc_url($link->post->getLinks()->view) . '" target="_blank">' . esc_html($link->post->getTitle()) . '</a></div>
                        <div style="margin: 3px 0;"><b>URL:</b> <a href="' . esc_url($link->url) . '" target="_blank">' . esc_html($link->url) . '</a></div>
                        <div style="margin: 3px 0;"><b>Anchor Text:</b> <a href="' . esc_url(add_query_arg(['wpil_admin_frontend' => '1', 'wpil_admin_frontend_data' => $link->create_scroll_link_data()], $link->post->getLinks()->view)) . '" target="_blank">' . esc_html($link->anchor) . ' <span class="dashicons dashicons-external" style="position: relative;top: 3px;"></span></a></div>
                        ' . Wpil_Report::get_dropdown_icons($link->post, $link);

                        if('related-post-link' !== Wpil_Toolbox::get_link_context($link->link_context)){
                $response .= '<a href="#" class="wpil_edit_link" target="_blank">[' . __('Edit URL', 'wpil') . ']</a>
                                <div class="wpil-domains-report-url-edit-wrapper">
                                    <input class="wpil-domains-report-url-edit" type="text" value="' . esc_attr($link->url) . '">
                                    <button class="wpil-domains-report-url-edit-confirm wpil-domains-edit-link-btn" data-link_id="' . $link->link_id . '" data-post_id="'.$link->post->id.'" data-post_type="'.$link->post->type.'" data-anchor="' . esc_attr($link->raw_anchor) . '" data-url="'.esc_url($link->url).'" data-nonce="' . wp_create_nonce('wpil_report_edit_' . $link->post->id . '_nonce_' . $link->link_id) . '">
                                        <i class="dashicons dashicons-yes"></i>
                                    </button>
                                    <button class="wpil-domains-report-url-edit-cancel wpil-domains-edit-link-btn">
                                        <i class="dashicons dashicons-no"></i>
                                    </button>
                                </div>';
                        }
                $response .= '
                    </div>
                </li>';
            }
        }

        wp_send_json(array('success' => array('item_data' => $response, 'item_count' => $county)));
    }

    public static function ajax_get_domain_report_data(){
        global $wpdb;
        $links_table = $wpdb->prefix . 'wpil_report_links';

        Wpil_Base::verify_nonce('domain_report_nonce');

        if(!isset($_POST['view_type']) || !isset($_POST['domain'])){
            wp_send_json(array('error' => array('title' => __('Data Missing', 'wpil'), 'text' => __('Some of the data required to load the rest of the dropdown is missing. Please reload the page and try opening the dropdown again.', 'wpil'))));
        }

        $domains = (is_string($_POST['domain'])) ? array(sanitize_text_field($_POST['domain'])) : array_map('sanitize_text_field', $_POST['domain']);
        $search = (isset($_POST['search']) && !empty($_POST['search'])) ? trim($_POST['search']): '';
        $search_type = (isset($_POST['search_type']) && !empty($_POST['search_type'])) ? $_POST['search_type']: false;

        $untargeted = '';
        if(isset($_POST['show_untargetted']) && !empty($_POST['show_untargetted'])){
            $untargeted = "AND link_id IN (SELECT d.link_id FROM {$links_table} d LEFT JOIN {$wpdb->posts} p ON d.target_id = p.ID WHERE (d.internal = 1 AND d.target_id = 0) OR (d.target_id > 0 AND d.target_type = 'post' AND p.ID IS NULL))";
        }

        $table = '';
        $header = [];
        $body = '';
        $update_bar = '';
        switch ($_POST['view_type']) {
            case 'configure-attrs':
                $header = array_merge($header, [
                    '<th>Domain</th>',
                    '<th>Applied Attributes</th>',
                ]);

                // get all the attrs for the current domains(s)
                $applied_rules = Wpil_Settings::get_active_link_attributes();
                $available_attrs = Wpil_Settings::get_available_link_attributes();
                foreach($domains as $domain){
                    $active_attrs = (isset($applied_rules[$domain])) ? $applied_rules[$domain]: [];
                    $options = '';

                    foreach($available_attrs as $attr => $name){
                        $selected = in_array($attr, $active_attrs, true) ? 'selected="selected"': '';
                        $options .= '<option ' . $selected . ' value="' . esc_attr($attr) . '"' . ((Wpil_Settings::check_if_attrs_conflict($attr, $active_attrs)) ? 'disabled="disabled"': '') . '>' . $name . '</option>';
                    }

                    $button_panel = 
                    '<div>
                        <select multiple class="wpil-domain-attribute-multiselect">' . $options . '</select>
                        <button class="wpil-domain-attribute-save button-disabled" data-domain="' . esc_attr($domain) . '" data-saved-attrs="' . esc_attr(json_encode($active_attrs)) . '" data-nonce="' . wp_create_nonce(get_current_user_id() . 'wpil_attr_save_nonce') . '">' .__('Update','wpil'). '</button>
                    </div>';

                    $body .= '<tr>
                                <td><div style="margin: 3px 0;"> ' . $domain . '</div></td>
                                <td><div style="margin: 3px 0;"> ' . $button_panel . '</div></td>';
                    $body .= '</tr>';
                }

                $update_bar = 
                '<div class="wpil-update-activity-items " style="display: flex; justify-content: space-between;">
                    <a href="#" class="wpil-update-selected-activity-items disabled" style="margin: 0 0 0 10px;" data-nonce="' . wp_create_nonce(wp_get_current_user()->ID . 'activity-item-action') . '">Edit Selected</a>
                    <a href="#" class="wpil-delete-selected-activity-items disabled" style="margin: 0 0 0 10px;" data-nonce="' . wp_create_nonce(wp_get_current_user()->ID . 'delete-selected-links') . '">Delete Selected</a>
                </div>';

                break;
            case 'view-posts':
                $header = array_merge($header, [
                    '<th class="wpil-activity-panel-post">Post</th>',
                    '<th>Published</th>',
                    '<th>Domain Link Count</th>',
                    '<th style="text-align:right; width: 100px" class="wpil-activity-panel-fixed-th">Actions</th>'
                ]);

                if(!empty($search)){
                    if($search_type === 'domain'){
                        $search = $wpdb->prepare(" AND host LIKE %s", Wpil_Toolbox::esc_like($search));
                    }elseif($search_type === 'links'){
                        $multi = false;
                        if(false !== strpos($search, ',')){
                            $bits = explode(',', $search);
                            if(!empty($bits)){
                                $links = array();
                                foreach($bits as $bit){
                                    $bit = trim($bit);
                                    if(!empty($bit)){
                                        $links[] = $wpdb->prepare("raw_url LIKE %s", Wpil_Toolbox::esc_like(mb_ereg_replace('&', '&amp;', $bit)));
                                    }
                                }

                                if(!empty($links)){
                                    $search = " AND " . implode(" OR ", $links);
                                    $multi = true;
                                }
                            }
                        }

                        if(empty($multi)){
                            $search = $wpdb->prepare(" AND raw_url LIKE %s", Wpil_Toolbox::esc_like(mb_ereg_replace('&', '&amp;', $search)));
                        }
                    }else{
                        $search = '';
                    }
                }else{
                    $search = '';
                }

                $cleaned_hosts = [];
                $placeholders = [];
                foreach($domains as $domain){
                    $placeholders[] = '%s';
                    $cleaned_hosts[] = wp_parse_url(esc_url_raw($domain), PHP_URL_HOST);
                }

                $host = $wpdb->prepare("AND host IN ('" . implode("', '", $placeholders) . "')", $cleaned_hosts);
                $ignored = Wpil_Query::getReportLinksIgnoreQueryStrings();
                $post_check = "GROUP BY post_id ORDER BY link_id ASC";
                $result = $wpdb->get_results("SELECT *, COUNT(*) as 'link_count' FROM {$links_table} WHERE host IS NOT NULL {$host} {$search} {$ignored} {$untargeted} {$post_check}");

                $post_objs = array();
                $domain = array();
                foreach ($result as $link) {
                    $id = $link->post_id;
                    $type = $link->post_type;
                    $cache_id = $type . $id;

                    // if we haven't used this post yet
                    if(!isset($post_objs[$cache_id])){
                        // create it fresh for the post var
                        $p = new Wpil_Model_Post($id, $type);
                        // and then add it to the object array so we can use it later
                        $post_objs[$cache_id] = $p;
                    }else{
                        // if we have used this post, obtain it from the object list
                        $p = $post_objs[$cache_id];
                    }

                    if (empty($domain['posts'][$id])) {
                        $domain['posts'][$id] = $p;
                    }

                    // get the protocol for the domain as best we can
                    if(!isset($domain['protocol']) || 'https://' !== $domain['protocol']){
                        if(false !== strpos($link->clean_url, 'https:')){
                            $domain['protocol'] = 'https://';
                        }else{
                            $domain['protocol'] = 'http://';
                        }
                    }

                    $body .= '<tr>
                                <td class="wpil-activity-panel-post wpil-activity-panel-limited-text-cell"><div style="margin: 3px 0;"><a href="' . esc_url($p->getLinks()->view) . '" target="_blank">' . esc_html($p->getTitle()) . '</a></div></td>
                                <td>
                                    <div style="margin: 3px 0;">
                                        ' . ($p->get_post_date()) . '
                                    </div>
                                </td>
                                <td>
                                    <div style="margin: 3px 0;">
                                        ' . ((isset($link->link_count) && !empty($link->link_count)) ? (int) $link->link_count: 0) . '
                                    </div>
                                </td>
                                <td style="text-align:right"><div style="margin: 3px 0;"><a href="' . esc_url($p->getLinks()->edit) . '" target="_blank">Edit '.ucfirst($p->type).'</a></div></td>';
                    $body .= '</tr>';
                }

                break;
            case 'view-links':

                $header = array_merge($header, [
                    '<th class="wpil-activity-panel-post">Post</th>',
                    '<th>Anchor Text</th>',
                    '<th>URL</th>',
                    '<th class="wpil-link-status-icon-header wpil-activity-panel-fixed-th">Status</th>'
                ]);

                if(!empty($search)){
                    if($search_type === 'domain'){
                        $search = $wpdb->prepare(" AND host LIKE %s", Wpil_Toolbox::esc_like($search));
                    }elseif($search_type === 'links'){
                        $multi = false;
                        if(false !== strpos($search, ',')){
                            $bits = explode(',', $search);
                            if(!empty($bits)){
                                $links = array();
                                foreach($bits as $bit){
                                    $bit = trim($bit);
                                    if(!empty($bit)){
                                        $links[] = $wpdb->prepare("raw_url LIKE %s", Wpil_Toolbox::esc_like(mb_ereg_replace('&', '&amp;', $bit)));
                                    }
                                }

                                if(!empty($links)){
                                    $search = " AND " . implode(" OR ", $links);
                                    $multi = true;
                                }
                            }
                        }

                        if(empty($multi)){
                            $search = $wpdb->prepare(" AND raw_url LIKE %s", Wpil_Toolbox::esc_like(mb_ereg_replace('&', '&amp;', $search)));
                        }
                    }else{
                        $search = '';
                    }
                }else{
                    $search = '';
                }

                $cleaned_hosts = [];
                $placeholders = [];
                foreach($domains as $domain){
                    $placeholders[] = '%s';
                    $cleaned_hosts[] = wp_parse_url(esc_url_raw($domain), PHP_URL_HOST);
                }

                $host = $wpdb->prepare("AND host IN ('" . implode("', '", $placeholders) . "')", $cleaned_hosts);

                $ignored = Wpil_Query::getReportLinksIgnoreQueryStrings();
                $result = $wpdb->get_results("SELECT * FROM {$links_table} WHERE host IS NOT NULL {$host} {$search} {$untargeted} {$ignored}");

                $post_objs = array();
                $domain = array();
                foreach ($result as $link) {
                    $id = $link->post_id;
                    $type = $link->post_type;
                    $cache_id = $type . $id;

                    // if we haven't used this post yet
                    if(!isset($post_objs[$cache_id])){
                        // create it fresh for the post var
                        $p = new Wpil_Model_Post($id, $type);
                        // and then add it to the object array so we can use it later
                        $post_objs[$cache_id] = $p;
                    }else{
                        // if we have used this post, obtain it from the object list
                        $p = $post_objs[$cache_id];
                    }

                    if (empty($domain['posts'][$id])) {
                        $domain['posts'][$id] = $p;
                    }

                    // get the protocol for the domain as best we can
                    if(!isset($domain['protocol']) || 'https://' !== $domain['protocol']){
                        if(false !== strpos($link->clean_url, 'https:')){
                            $domain['protocol'] = 'https://';
                        }else{
                            $domain['protocol'] = 'http://';
                        }
                    }

                    $link_obj = new Wpil_Model_Link([
                        'link_id' => $link->link_id,
                        'url' => $link->raw_url,
                        'anchor' => strip_tags($link->anchor),
                        'raw_anchor' => (isset($link->raw_anchor) && !empty($link->raw_anchor)) ? $link->raw_anchor : '',
                        'host' => isset($link->host) ? $link->host : '',
                        'internal' => !empty($link->internal),
                        'post' => $p,
                        'link_whisper_created' => (isset($link->link_whisper_created) && !empty($link->link_whisper_created)) ? 1: 0,
                        'is_autolink' => (isset($link->is_autolink) && !empty($link->is_autolink)) ? 1: 0,
                        'tracking_id' => (isset($link->tracking_id) && !empty($link->tracking_id)) ? $link->tracking_id: 0,
                        'module_link' => (isset($link->module_link) && !empty($link->module_link)) ? $link->module_link: 0,
                        'link_context' => (isset($link->link_context) && !empty($link->link_context)) ? $link->link_context: 0,
                        'ai_relation_score' => (isset($link->ai_relation_score) && !empty($link->ai_relation_score)) ? $link->ai_relation_score: 0,
                        'url_slug_word_count' => (isset($link->url_slug_word_count) && !empty($link->url_slug_word_count)) ? $link->url_slug_word_count: 0,
                        'anchor_slug_positional_match' => (isset($link->anchor_slug_positional_match) && is_numeric($link->anchor_slug_positional_match)) ? $link->anchor_slug_positional_match: 0,
                        'target_id' => (isset($link->target_id) && !empty($link->target_id)) ? $link->target_id: 0,
                        'target_type' => (isset($link->target_type) && !empty($link->target_type)) ? $link->target_type: ''
                    ]);

                    $edit_link = '';

                    $body .= '<tr class="wpil-activity-panel-edit inactive">
                                <td class="wpil-activity-panel-post wpil-activity-panel-limited-text-cell">
                                    <div style="margin: 3px 0;">
                                        <a href="' . esc_url($p->getLinks()->view) . '" target="_blank">' . esc_html($p->getTitle()) . '</a>
                                    </div>
                                </td>
                                <td class="wpil-activity-panel-limited-text-cell">
                                    <div style="margin: 3px 0; display:flex;">
                                        '.$edit_link.'
                                        <div class="wpil-report-edit-display wpil-activity-panel-anchor-display"><div class="wpil-anchor-display-text">' . esc_html($link->anchor) . '</div> <a href="' . esc_url(add_query_arg(['wpil_admin_frontend' => '1', 'wpil_admin_frontend_data' => $link_obj->create_scroll_link_data()], $p->getLinks()->view)) . '" title="'.esc_attr__('View On Page','wpil').'" target="_blank"><span class="dashicons dashicons-external" style="position: relative;top: 3px;"></span></a></div>';
                    if('related-post-link' !== Wpil_Toolbox::get_link_context($link_obj->link_context)){
                    $body .=        '<input class="wpil-activity-panel-anchor-edit wpil-report-edit-input" type="text" value="' . esc_attr($link_obj->anchor) . '">';
                    }
                    $body .=        '</div>
                                </td>
                                <td class="wpil-activity-panel-limited-text-cell">
                                    <div style="margin: 3px 0; display:flex;">
                                        '.$edit_link.'
                                        <div href="' . esc_url($link->raw_url) . '" class="wpil-report-edit-display wpil-activity-panel-url-display" target="_blank">
                                            <div class="wpil-url-display-text">' . esc_html($link->raw_url) . '</div>
                                        </div>';
                    if('related-post-link' !== Wpil_Toolbox::get_link_context($link_obj->link_context)){
                    $body .=        '<input class="wpil-activity-panel-url-edit wpil-report-edit-input" type="text" value="' . esc_attr($link_obj->url) . '">';
                    }
                    $body .=        '</div>
                                </td>
                                <td class="wpil-status-icon-cell">' . Wpil_Report::get_dropdown_icons($link_obj->post, $link_obj, (!empty($link_obj->internal) ? 'outbound-internal': 'outbound-external'), true) . '</td>';
                    $body .= '</tr>';
                }

                $update_bar = 
                '<div class="wpil-update-activity-items" style="display: flex; justify-content: space-between;">
                    <a href="#" class="wpil-edit-selected-activity-items inactive" style="margin: 0 0 0 10px;" data-nonce="' . wp_create_nonce(wp_get_current_user()->ID . 'activity-item-action') . '"><span class="wpil-edit-inactive">📝 Edit Selected</span><span class="wpil-edit-active">🛑Stop Editing</span></a>
                    <a href="#" class="wpil-update-selected-activity-items wpil_link_edit_update disabled" style="margin: 0 0 0 10px;" data-nonce="' . wp_create_nonce(wp_get_current_user()->ID . 'activity-item-action') . '">🔄 Update Selected</a>
                    <a href="#" class="wpil-delete-selected-activity-items disabled" style="margin: 0 0 0 10px;" data-nonce="' . wp_create_nonce(wp_get_current_user()->ID . 'delete-selected-links') . '">🗑️ Delete Selected</a>
                </div>';
                
                break;
            default:
                break;
        }

        $table .= '
            <table class="wpil-activity-table widefat" style="width:100%; border-collapse: collapse;">
                <thead>
                    <tr style="text-align: left;">' . implode('', $header) . '</tr>
                </thead>
                <tbody>' . $body . '</tbody>
            </table>';
        $table .= $update_bar;

        wp_send_json(array('success' => array(
            'table' => $table
        )));
    }



    /**
    * Get anchor count
    *
    * @return string|null
    */
    public static function getAnchorPostCounts()
    {
        global $wpdb;
        $table = "{$wpdb->prefix}wpil_report_links";
        $date_limit = Wpil_Query::getPostDateQueryLimit();
        $date_limit = (!empty($date_limit)) ? " AND ((`post_type` = 'post' AND `post_id` IN (SELECT ID FROM {$wpdb->posts} WHERE 1=1 {$date_limit})) OR `post_type` = 'term')": '';

        // if the scan has been run since the 2.6.5 update
        if(version_compare(get_option('wpil_scan_last_plugin_version', '0.0.1'), '2.6.5', '>=')){
            // consisely query the database for data
            $total = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE internal = 1 {$date_limit}");
            $filtered = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE internal = 1 AND `anchor_word_count` > 2 AND `anchor_word_count` < 8 {$date_limit}");
        }else{
            // if the scan hasn't been run since the update, process the word counts out of the existing anchors
            // Get all internal anchors (fetch anchor text)
            $anchors = $wpdb->get_results(
                "SELECT anchor FROM $table WHERE internal = 1 {$date_limit}",
                ARRAY_A
            );

            $total = count($anchors);
            $filtered = 0;
            foreach ($anchors as $row) {
                $anchor = $row['anchor'];
                // Use the Link Whisper word counter to count the number of words in an anchor!
                $word_count = Wpil_Word::getWordCount($anchor);
                if ($word_count >= 3 && $word_count <= 7) {
                    $filtered++;
                }
            }
        }

        return [
            'total' => $total,
            'filtered' => $filtered
        ];
    }

    /**
     * Gets the percent of posts that are hitting the 3-5 outbound internal && 1 inbound internal target
     **/
    public static function get_percent_of_posts_hitting_link_targets() {
        global $wpdb;
        $link_table = $wpdb->prefix . "wpil_report_links";
        // Get the number of posts that have at least 3 outbound internal links && 1 inbound internal
        // select outbound internal using a query that "group by post_id, post_type", and then count
        // select inbound internal using a query that "group by target_id, target_type", and then count
        // find all the post ids that hit our target, and then divide it by the total number of posts to get the percentage

        // Step 0.9: Get all the ignore posts so we can stop inflating the stats!
        $options = get_user_meta(get_current_user_id(), 'report_options', true);
        $show_categories = (!empty($options['show_categories']) && $options['show_categories'] == 'off') ? false : true;
        $ignored_posts = Wpil_Query::get_all_report_ignored_post_ids('', array('orphaned' => true, 'hide_noindex' => true, 'return_ids' => true));
        $ignored_terms = Wpil_Query::ignoredTermIds('', true);
        $ignored_posts = array_map('strval', $ignored_posts);
        $ignored_terms = array_map('strval', $ignored_terms);
        $money_page_keys = Wpil_Settings::get_money_page_pid_list(true);
        if(!$show_categories && !empty($money_page_keys)){
            $money_page_keys = array_values(array_filter($money_page_keys, function($pid){
                return (strpos($pid, 'post_') === 0);
            }));
        }
        $money_page_lookup = array_fill_keys(array_unique($money_page_keys), true);

        // Step 1: Get all post/term IDs with at least 3 outbound internal links
        $outbound_query = "
            SELECT post_id, post_type
            FROM {$link_table}
            WHERE internal = 1 AND " .(($show_categories) ? "(post_type = 'post' OR post_type = 'term')": "(post_type = 'post')"). "
            GROUP BY post_id, post_type
            HAVING COUNT(*) >= 3
        ";
        $outbound_results = $wpdb->get_results($outbound_query, ARRAY_A);
        // Filter the outbound keys
        $outbound_results = array_filter($outbound_results, function($post) use ($ignored_posts, $ignored_terms){
            if($post['post_type'] === 'post'){
                return !in_array((string) $post['post_id'], $ignored_posts, true);
            }else{
                return !in_array((string) $post['post_id'], $ignored_terms, true);
            }
        });
        // Normalize outbound keys
        $outbound_keys = array_map(function($row) {
            return $row['post_type'] . '_' . $row['post_id'];
        }, $outbound_results);
        // Step 2: Get all target post/term IDs with at least 1 inbound internal link
        $inbound_query = "
            SELECT target_id, target_type
            FROM {$link_table}
            WHERE internal = 1 AND " .(($show_categories) ? "(target_type = 'post' OR target_type = 'term')": "(target_type = 'post')"). "
            GROUP BY target_id, target_type
            HAVING COUNT(*) >= 1
        ";
        $inbound_results = $wpdb->get_results($inbound_query, ARRAY_A);
        // Filter the inbound keys
        $inbound_results = array_filter($inbound_results, function($post) use ($ignored_posts, $ignored_terms){
            if($post['target_type'] === 'post'){
                return !in_array((string) $post['target_id'], $ignored_posts, true);
            }else{
                return !in_array((string) $post['target_id'], $ignored_terms, true);
            }
        });
        // Normalize inbound keys
        $inbound_keys = array_map(function($row) {
            return $row['target_type'] . '_' . $row['target_id'];
        }, $inbound_results);
        // Step 3: Find intersection
        
        $outbound_lookup = array_fill_keys(array_unique($outbound_keys), true);
        $inbound_lookup = array_fill_keys(array_unique($inbound_keys), true);
        // Step 4: Get total number of posts + terms (published posts + all terms)

        $post_ids = Wpil_Report::get_all_post_ids('suggestion');
        $term_ids = ($show_categories) ? Wpil_Report::get_all_term_ids() : 0;

        if(!empty($post_ids) && !empty($ignored_posts)){
            $post_ids = array_diff($post_ids, $ignored_posts);
        }

        if($show_categories && !empty($term_ids) && !empty($ignored_terms)){
            $term_ids = array_diff($term_ids, $ignored_terms);
        }

        // Build the list of items we're actually scoring so old link-table data can't push us over 100%.
        $eligible_keys = array();
        if(!empty($post_ids) && is_array($post_ids)){
            $eligible_keys = array_merge($eligible_keys, array_map(function($id){
                return 'post_' . $id;
            }, $post_ids));
        }

        if($show_categories && !empty($term_ids) && is_array($term_ids)){
            $eligible_keys = array_merge($eligible_keys, array_map(function($id){
                return 'term_' . $id;
            }, $term_ids));
        }

        $post_count = (!empty($post_ids) && is_array($post_ids)) ? count($post_ids): 0;
        $term_count = (!empty($term_ids) && is_array($term_ids)) ? count($term_ids): 0;

        $total_items = $post_count + $term_count;

        // Step 5: Calculate percentage
        $qualified = 0;
        foreach(array_unique($eligible_keys) as $key){
            if(isset($money_page_lookup[$key])){
                if(isset($inbound_lookup[$key])){
                    $qualified++;
                }
                continue;
            }

            if(isset($inbound_lookup[$key]) && isset($outbound_lookup[$key])){
                $qualified++;
            }
        }
        $percent = $total_items > 0 ? round(($qualified / $total_items) * 100, 2) : 0;
        return [
            'qualified_items' => $qualified,
            'total_items' => $total_items,
            'percent' => $percent
        ];
    }

    public static function get_click_traffic_stats() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpil_click_data';

        // Get the number of clicks tracked in the past 30 days
        $last_30_days = $wpdb->get_var("
            SELECT COUNT(*) as clicks
            FROM {$table_name}
            WHERE click_date >= NOW() - INTERVAL 30 DAY
        ");

        // Get the number of clicks in the past 30 - 60 days so we can cross reference
        $older_than_30_days = $wpdb->get_var("
            SELECT COUNT(*) as clicks
            FROM {$table_name}
            WHERE click_date < NOW() - INTERVAL 30 DAY AND click_date > NOW() - INTERVAL 60 DAY
        ");

        $difference = $last_30_days - $older_than_30_days;
        $percent_change = 0;
        if(!empty($older_than_30_days)){
            $percent_change = round(($difference / $older_than_30_days) * 100, 2);
        }elseif(!empty($last_30_days)){
            $percent_change = 100;
        }

        return [
            'clicks_30' => $last_30_days,
            'clicks_old' => $older_than_30_days,
            'percent_change' => $percent_change
        ];
    }

    /**
     * Gets the proportion of links that are going to posts that aren't particularly related to the source post.
     * This is an aggregate stat, and should apply to all inbound internal links
     **/
    public static function get_related_link_percentage(){
        global $wpdb;
        $table = $wpdb->prefix . "wpil_report_links";
        $date_limit = Wpil_Query::getPostDateQueryLimit();
        $date_limit = (!empty($date_limit)) ? " AND ((`post_type` = 'post' AND `post_id` IN (SELECT ID FROM {$wpdb->posts} WHERE 1=1 {$date_limit})) OR `post_type` = 'term')": '';

        $links = $wpdb->get_row("SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN (CASE WHEN `anchor_slug_positional_match` >= 80 THEN LEAST((`ai_relation_score` * 1.2), 1) ELSE `ai_relation_score` END) > 0.5 THEN 1 ELSE 0 END) AS related,
                SUM(CASE WHEN (CASE WHEN `anchor_slug_positional_match` >= 80 THEN LEAST((`ai_relation_score` * 1.2), 1) ELSE `ai_relation_score` END) < 0.5 THEN 1 ElSE 0 END) AS unrelated
            FROM {$table}
            WHERE `internal` = 1 AND `ai_relation_score` > 0 {$date_limit}");

        $percent = 0;
        if(!empty($links) && isset($links->total) && !empty($links->total)){
            if(!empty($links->related)){
                $percent = round($links->related / $links->total, 2) * 100;
            }
        }

        return $percent;
    }
    
    /******* Tailwind Styled Dashboard Renderers *******/
    public static function wpil_dash_severity_for_standard_percent($percent) {
        $p = (float)$percent;

        if ($p >= 80) return 'low';
        if ($p >= 60) return 'medium';
        return 'high';
    }

    public static function wpil_dash_severity_for_external_focus($percent) {
        $p = (float)$percent;

        if ($p <= 60) return 'low';
        if ($p >= 80) return 'high';
        return 'medium';
    }

    public static function wpil_dash_should_show_percent_action($severity, $show_medium = true) {
        if ($severity === 'high') return true;
        if ($severity === 'medium') return (bool)$show_medium;
        return false;
    }

    public static function wpil_dash_count_serious_actions($items) {
        $count = 0;
        foreach ($items as $it) {
            if (!empty($it['severity']) && $it['severity'] === 'high') {
                $count++;
            }
        }
        return $count;
    }

    public static function wpil_dash_action_severity_meta($severity) {
        switch ($severity) {
            case 'high':
                return [
                    'pill_bg'   => 'bg-red-100',
                    'pill_text' => 'text-red-600',
                    'icon_bg'   => 'bg-red-100',
                    'icon_text' => 'text-red-500',
                    'icon_bg_hover' => 'group-hover:bg-red-500',
                    'icon_text_hover' => 'group-hover:text-white',
                ];
            case 'medium':
                return [
                    'pill_bg'   => 'bg-orange-100',
                    'pill_text' => 'text-orange-600',
                    'icon_bg'   => 'bg-orange-100',
                    'icon_text' => 'text-orange-500',
                    'icon_bg_hover' => 'group-hover:bg-orange-500',
                    'icon_text_hover' => 'group-hover:text-white',
                ];
            case 'low':
            default:
                return [
                    'pill_bg'   => 'bg-blue-100',
                    'pill_text' => 'text-blue-600',
                    'icon_bg'   => 'bg-blue-100',
                    'icon_text' => 'text-blue-500',
                    'icon_bg_hover' => 'group-hover:bg-blue-500',
                    'icon_text_hover' => 'group-hover:text-white',
                ];
        }
    }

    public static function wpil_dash_action_cta_classes($severity, $enabled = true) {
        if (!$enabled) {
            return 'text-sm font-bold text-gray-400 bg-gray-50 border border-gray-200 px-4 py-2 rounded-lg cursor-not-allowed';
        }

        switch ($severity) {
            case 'high':
                return 'text-sm font-bold text-gray-600 bg-gray-50 border border-gray-200 px-4 py-2 rounded-lg hover:bg-gray-100 hover:text-gray-900 transition-colors';
            case 'medium':
                return 'text-sm font-bold text-white bg-orange-500 px-4 py-2 rounded-lg hover:bg-orange-600 shadow-md transition-colors';
            case 'low':
            default:
                return 'text-sm font-bold text-white bg-blue-500 px-4 py-2 rounded-lg hover:bg-blue-600 shadow-md transition-colors';
        }
    }

    public static function wpil_dash_estimate_fix_credits($fix_type, $m) {
        $fix_type = (string) $fix_type;

        $broken = isset($m['broken_links']) ? (int) $m['broken_links'] : 0;
        $orphan = isset($m['orphaned_posts']) ? (int) $m['orphaned_posts'] : 0;

        $coverage_pct = isset($m['link_coverage_percent']) ? (float) $m['link_coverage_percent'] : 0.0;
        $related_pct  = isset($m['link_relatedness_percent']) ? (float) $m['link_relatedness_percent'] : 0.0;
        $external_pct = isset($m['external_site_focus']) ? (float) $m['external_site_focus'] : 0.0;

        // NOTE: These are intentionally conservative defaults.
        // You can replace with real estimators once your fix jobs know scope precisely.
        switch ($fix_type) {
            case 'broken_links':
                return max(5, min(5000, $broken * 2));

            case 'orphaned_posts':
                // Orphan linking fans out across candidate source posts, so allow for the comparisons too.
                $candidates_per_target = max(1, (float) apply_filters('wpil_inbound_candidates_per_target', 20));
                $candidate_cost = max(0, (float) apply_filters('wpil_inbound_candidate_credit_cost', 1));
                return max(20, (int) ceil($orphan * $candidates_per_target * $candidate_cost));

            case 'link_coverage':
                // more missing coverage => more work
                $gap = max(0, 80 - $coverage_pct); // how far from "great"
                return max(10, (int) round($gap * 8)); // 0..640-ish

            case 'link_quality':
                $gap = max(0, 80 - $related_pct);
                return max(10, (int) round($gap * 6));

            case 'external_focus':
                // only penalize if over 60
                $over = max(0, $external_pct - 60);
                return max(10, (int) round($over * 5));

            default:
                return 0;
        }
    }

    private static function wpil_dash_fmt_pct($p, $decimals = 0) {
        $p = (float) $p;
        return rtrim(rtrim(number_format($p, $decimals), '0'), '.') . '%';
    }

    /**
     * Renders the actions list.
     *
     * @param array $items
     */
    public static function wpil_dash_render_recommended_actions($items) {
        if (empty($items)) {
            ?>
            <div class="bg-white p-5 rounded-xl border border-gray-200 text-sm text-gray-500">
                No recommended actions right now. Nice work!
            </div>
            <?php
            return;
        }

        foreach ($items as $item) {
            $severity = $item['severity'] ?? 'low';
            $meta = self::wpil_dash_action_severity_meta($severity);

            $icon  = $item['icon'] ?? '!';
            $title = $item['title'] ?? '';
            $desc  = $item['description'] ?? '';
            $pill  = $item['pill'] ?? null;

            $review = $item['review'] ?? [];
            $fix    = $item['fix'] ?? [];

            $review_label   = !empty($review['label']) ? $review['label'] : 'Review';
            $review_url     = !empty($review['url']) ? $review['url'] : '';
            $review_target  = !empty($review['target']) ? $review['target'] : '_blank';
            $review_enabled = isset($review['enabled']) ? (bool)$review['enabled'] : !empty($review_url);

            $fix_label   = !empty($fix['label']) ? $fix['label'] : 'Fix';
            $fix_enabled = isset($fix['enabled']) ? (bool)$fix['enabled'] : false;
            $fix_type    = !empty($fix['type']) ? $fix['type'] : '';

            $review_class = 'text-sm font-bold text-gray-600 bg-gray-50 border border-gray-200 px-4 py-2 rounded-lg hover:bg-gray-100 hover:text-gray-900 transition-colors';
            $fix_class    = self::wpil_dash_action_cta_classes($severity, $fix_enabled);
            ?>
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition-shadow flex items-center justify-between gap-6 group" id="<?php echo !empty($item['id']) ? esc_attr('wpil-action-' . $item['id']) : ''; ?>">
                <!-- Left: icon + text -->
                <div class="flex items-center space-x-4 min-w-0">
                    <div class="w-14 h-14 rounded-full <?php echo esc_attr($meta['icon_bg']); ?> flex items-center justify-center <?php echo esc_attr($meta['icon_text']); ?> <?php echo esc_attr($meta['icon_bg_hover']); ?> <?php echo esc_attr($meta['icon_text_hover']); ?> transition-colors flex-shrink-0" style="width:48px;height:48px;">
                        <?php if (is_array($icon) && !empty($icon['type']) && $icon['type'] === 'svg' && !empty($icon['html'])) { ?>
                            <?php
                            echo wp_kses(
                                $icon['html'],
                                [
                                    'svg' => [
                                        'xmlns' => true,
                                        'viewBox' => true,
                                        'fill' => true,
                                        'stroke' => true,
                                        'stroke-width' => true,
                                        'stroke-linecap' => true,
                                        'stroke-linejoin' => true,
                                        'class' => true,
                                        'aria-hidden' => true,
                                        'focusable' => true,
                                    ],
                                    'path' => [
                                        'd' => true,
                                        'fill' => true,
                                        'stroke' => true,
                                        'stroke-width' => true,
                                        'stroke-linecap' => true,
                                        'stroke-linejoin' => true,
                                    ],
                                ]
                            );
                            ?>
                        <?php } elseif (is_array($icon) && !empty($icon['type']) && $icon['type'] === 'dashicon' && !empty($icon['name'])) { ?>
                            <span class="dashicons <?php echo esc_attr($icon['name']); ?>" style="font-size: 32px; width: 32px; height: 32px; line-height: 1;"></span>
                        <?php } else { ?>
                            <span class="font-black text-2xl leading-none" style="font-size: 32px; line-height: 1;"><?php echo esc_html($icon); ?></span>
                        <?php } ?>
                    </div>

                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <h4 class="font-bold text-gray-800 truncate"><?php echo esc_html($title); ?></h4>
                            <?php if (!empty($pill)) { ?>
                                <span class="text-xs font-bold px-2 py-0.5 rounded-full <?php echo esc_attr($meta['pill_bg'] . ' ' . $meta['pill_text']); ?>">
                                    <?php echo esc_html($pill); ?>
                                </span>
                            <?php } ?>
                        </div>
                        <p class="text-sm text-gray-500"><?php echo esc_html($desc); ?></p>
                    </div>
                </div>

                <!-- Right: buttons -->
                <div class="flex items-center gap-2 flex-shrink-0">
                    <?php if ($review_enabled) { ?>
                        <a href="<?php echo esc_url($review_url); ?>"
                        target="<?php echo esc_attr($review_target); ?>"
                        class="<?php echo esc_attr($review_class); ?>">
                            <?php echo esc_html($review_label); ?>
                        </a>
                    <?php } else { ?>
                        <button class="text-sm font-bold text-gray-400 bg-gray-50 border border-gray-200 px-4 py-2 rounded-lg cursor-not-allowed" disabled>
                            <?php echo esc_html($review_label); ?>
                        </button>
                    <?php } ?>

                    <button
                        class="<?php echo esc_attr($fix_class); ?> hidden"
                        <?php echo $fix_enabled ? '' : 'disabled'; ?>
                        data-wpil-fix="1"
                        data-wpil-fix-type="<?php echo esc_attr($fix_type); ?>"
                        data-wpil-fix-severity="<?php echo esc_attr($severity); ?>"
                        data-wpil-fix-item-id="<?php echo esc_attr($item['id'] ?? ''); ?>"
                        data-wpil-review-url="<?php echo esc_attr($review_url); ?>"
                        <?php
                        if (!empty($fix['attrs']) && is_array($fix['attrs'])) {
                            foreach ($fix['attrs'] as $k => $v) {
                                if (strpos($k, 'data-') === 0) {
                                    echo ' ' . esc_attr($k) . '="' . esc_attr($v) . '"';
                                }
                            }
                        }
                        ?>
                    >
                        <?php echo esc_html($fix_label); ?>
                    </button>
                    <?php if ($review_enabled) { ?>
                        <a href="<?php echo esc_url($review_url); ?>"
                        target="<?php echo esc_attr($review_target); ?>"
                        class="<?php echo esc_attr($review_class); ?>">
                            <?php esc_html_e('Fix Manually', 'wpil'); ?>
                        </a>
                    <?php } ?>
                </div>
            </div>
            <?php
        }
    }


    /**
     * Builds the Recommended Actions list from dashboard metrics.
     *
     * Expected $m keys (use whatever you already have in this template):
     * - broken_links
     * - orphaned_posts
     * - anchor_length_percent (nullable)
     * - link_coverage_percent
     * - link_relatedness_percent
     * - admin_urls (array of relevant urls)
     */
    public static function wpil_dash_generate_recommended_actions($m) {
        $items = [];
        $urls = isset($m['admin_urls']) ? (array) $m['admin_urls'] : [];

        $broken = isset($m['broken_links']) ? (int) $m['broken_links'] : 0;
        $orphan = isset($m['orphaned_posts']) ? (int) $m['orphaned_posts'] : 0;

        $anchor_pct   = array_key_exists('anchor_length_percent', $m) ? $m['anchor_length_percent'] : null; // ignored for fix
        $coverage_pct = isset($m['link_coverage_percent']) ? (float) $m['link_coverage_percent'] : 0.0;
        $related_pct  = isset($m['link_relatedness_percent']) ? (float) $m['link_relatedness_percent'] : 0.0;
        $external_pct = isset($m['external_site_focus']) ? (float) $m['external_site_focus'] : null;

        $show_medium = true;

        $fmt_pct = function($p) {
            return rtrim(rtrim(number_format((float)$p, 2), '0'), '.') . '%';
        };

        // helper to build fix payload
        $make_fix = function($fix_type, $severity, $item_id, $estimate_credits, $enabled = true, $extra_attrs = []) {
            $attrs = array_merge([
                'data-wpil-fix-estimate' => (string) max(0, (int) $estimate_credits),
            ], (array) $extra_attrs);

            return [
                'label'   => 'Fix',
                'enabled' => (bool) $enabled,
                'type'    => $fix_type,
                'attrs'   => $attrs,
            ];
        };

        // Broken Links
        if ($broken > 0 && false) { //TODO: come back around to this, needs more TLC
            $estimate = self::wpil_dash_estimate_fix_credits('broken_links', $m);

            $items[] = [
                'id'          => 'broken_links',
                'severity'    => 'high',
                'icon'        => [
                    'type' => 'svg',
                    'html' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6" aria-hidden="true" focusable="false"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>',
                ],
                'pill'        => number_format($broken),
                'title'       => 'Broken Links',
                'description' => 'Fix broken links to protect SEO and avoid dead ends for visitors.',

                'review' => [
                    'label'   => 'Review',
                    'url'     => $urls['broken_links'] ?? '',
                    'target'  => '_blank',
                    'enabled' => !empty($urls['broken_links']),
                ],

                'fix' => $make_fix(
                    'broken_links',
                    'high',
                    'broken_links',
                    $estimate,
                    true,
                    [
                        'data-wpil-count' => (string) $broken,
                        'data-wpil-fix-description' => 'This will use AI to fix the broken links that Link Whisper has found.'
                    ]
                ),
            ];
        }

        // Orphaned Posts
        if ($orphan > 0) {
            $estimate = self::wpil_dash_estimate_fix_credits('orphaned_posts', $m);

            $items[] = [
                'id'          => 'orphaned_posts',
                'severity'    => 'high',
                'icon'        => [
                    'type' => 'svg',
                    'html' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6" aria-hidden="true" focusable="false"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244"/></svg>',
                ],
                'pill'        => number_format($orphan),
                'title'       => 'Orphaned Posts',
                'description' => 'These posts have no internal links pointing to them.',

                'review' => [
                    'label'   => 'Review',
                    'url'     => $urls['orphaned_posts'] ?? '',
                    'target'  => '_blank',
                    'enabled' => !empty($urls['orphaned_posts']),
                ],

                'fix' => $make_fix(
                    'orphaned_posts',
                    'high',
                    'orphaned_posts',
                    $estimate,
                    true,
                    [
                        'data-wpil-count' => (string) $orphan,
                        'data-wpil-fix-description' => 'Link Whisper will use AI to create links pointing to the orphaned posts on the site. Not all posts are linkable with AI, so there may be some follow up required.'
                    ]
                ),
            ];
        }

        // Link Coverage
        $coverage_sev = self::wpil_dash_severity_for_standard_percent($coverage_pct);
        if (self::wpil_dash_should_show_percent_action($coverage_sev, $show_medium)) {
            $estimate = self::wpil_dash_estimate_fix_credits('link_coverage', $m);

            $items[] = [
                'id'          => 'link_coverage',
                'severity'    => $coverage_sev,
                'icon'        => [
                    'type' => 'svg',
                    'html' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6" aria-hidden="true" focusable="false"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12"/></svg>',
                ],
                'pill'        => $fmt_pct($coverage_pct),
                'title'       => 'Link Coverage',
                'description' => ($coverage_sev === 'high')
                    ? 'A lot of pages are missing basic internal links.'
                    : 'You’re close. Filling gaps helps ensure the site is fully linked.',

                'review' => [
                    'label'   => 'Review',
                    'url'     => $urls['link_density'] ?? '',
                    'target'  => '_blank',
                    'enabled' => !empty($urls['link_density']),
                ],

                'fix' => $make_fix(
                    'link_coverage',
                    $coverage_sev,
                    'link_coverage',
                    $estimate,
                    true,
                    [
                        'data-wpil-percent' => (string) $coverage_pct,
                        'data-wpil-fix-description' => 'This will use AI to add internal links to posts that are missing basic inbound and outbound links.'
                    ]
                ),
            ];
        }

        // Link Relatedness (Quality)
        $related_sev = self::wpil_dash_severity_for_standard_percent($related_pct);
        if (false && self::wpil_dash_should_show_percent_action($related_sev, $show_medium)) { // TODO: refine link quality systems and re-enable
            $estimate = self::wpil_dash_estimate_fix_credits('link_quality', $m);

            $items[] = [
                'id'          => 'link_quality',
                'severity'    => $related_sev,
                'icon'        => [
                    'type' => 'svg',
                    'html' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6" aria-hidden="true" focusable="false"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
                ],
                'pill'        => $fmt_pct($related_pct),
                'title'       => 'Link Quality',
                'description' => ($related_sev === 'high')
                    ? 'Many links look weakly related. Improving relevance can boost performance.'
                    : 'Good foundation. A few tweaks can push relevance higher.',

                'review' => [
                    'label'   => 'Review',
                    'url'     => $urls['link_relation'] ?? '',
                    'target'  => '_blank',
                    'enabled' => !empty($urls['link_relation']),
                ],

                'fix' => $make_fix(
                    'link_quality',
                    $related_sev,
                    'link_quality',
                    $estimate,
                    true,
                    [
                        'data-wpil-percent' => (string) $related_pct,
                        'data-wpil-fix-description' => 'This will use AI to remove links between unrelated posts and replace them with links to more related posts.'
                    ]
                ),
            ];
        }

        // External Site Focus
        if ($external_pct !== null && false) { // TODO: we don't currently have a need to have the user fix this, so skip it
            $ext_sev = self::wpil_dash_severity_for_external_focus($external_pct);
            if (self::wpil_dash_should_show_percent_action($ext_sev, $show_medium)) {
                $estimate = self::wpil_dash_estimate_fix_credits('external_focus', $m);

                $items[] = [
                    'id'          => 'external_focus',
                    'severity'    => $ext_sev,
                    'icon'        => [
                        'type' => 'svg',
                        'html' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6" aria-hidden="true" focusable="false"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>',
                    ],
                    'pill'        => $fmt_pct($external_pct),
                    'title'       => 'External Site Focus',
                    'description' => ($ext_sev === 'high')
                        ? 'Your site is sending too much link equity out. Prioritize internal links where it makes sense.'
                        : 'You’re a bit heavy on external links. Adding internal links can help.', // todo: review and update the text so that it's correct

                    'review' => [
                        'label'   => 'Review',
                        'url'     => $urls['domains_report'] ?? '',
                        'target'  => '_blank',
                        'enabled' => !empty($urls['domains_report']),
                    ],

                    'fix' => $make_fix(
                        'external_focus',
                        $ext_sev,
                        'external_focus',
                        $estimate,
                        true,
                        [
                            'data-wpil-percent' => (string) $external_pct,
                        ]
                    ),
                ];
            }
        }

        // Sort high -> medium -> low
        $weight = ['high' => 0, 'medium' => 1, 'low' => 2];
        usort($items, function ($a, $b) use ($weight) {
            $wa = $weight[$a['severity']] ?? 99;
            $wb = $weight[$b['severity']] ?? 99;
            return $wa <=> $wb;
        });

        return $items;
    }


    public static function wpil_dash_health_hint_payload(array $health, array $recommended_actions, array $admin_urls) {
        $top = isset($health['top_drag']) && is_array($health['top_drag']) ? $health['top_drag'] : [];

        $key = !empty($top['key']) ? (string) $top['key'] : '';
        $raw = $top['raw'] ?? null;

        // Map "drag key" to action id AND admin_urls key
        $map = [
            'broken_links'  => ['action_id' => 'broken_links',   'url_key' => 'broken_links'],
            'orphaned_posts'=> ['action_id' => 'orphaned_posts', 'url_key' => 'orphaned_posts'],
            'link_coverage' => ['action_id' => 'link_coverage',  'url_key' => 'link_density'],
            'link_quality'  => ['action_id' => 'link_quality',   'url_key' => 'link_relation'],
            'external_focus'=> ['action_id' => 'external_focus', 'url_key' => 'domains_report'],
        ];

        $text = 'Start with the serious issues below to improve site health.';
        $action_id = '';
        $fallback_url = '';

        if (!empty($key) && isset($map[$key])) {
            $action_id = $map[$key]['action_id'];
            $url_key   = $map[$key]['url_key'];
            $fallback_url = !empty($admin_urls[$url_key]) ? $admin_urls[$url_key] : '';
        }

        // Decide if the action actually exists in the current list
        $has_action = false;
        if (!empty($action_id)) {
            foreach ($recommended_actions as $a) {
                if (!empty($a['id']) && $a['id'] === $action_id) {
                    $has_action = true;
                    break;
                }
            }
        }

        // Use your more-direct messaging
        switch ($key) {
            case 'broken_links':
                $n = (int) $raw;
                $text = ($n > 0)
                    ? "Start by fixing {$n} broken links. This is the biggest issue affecting your site health."
                    : "Start by fixing broken links. This is the biggest issue affecting your site health.";
                break;

            case 'orphaned_posts':
                $n = (int) $raw;
                $text = ($n > 0)
                    ? "Start by adding internal links to {$n} orphaned posts."
                    : "Start by adding internal links to orphaned posts.";
                break;

            case 'link_coverage':
                $p = (float) $raw;
                $text = "Increase internal link coverage to at least 80%. You’re currently at " . self::wpil_dash_fmt_pct($p, 0) . '.';
                break;

            case 'link_quality':
                $p = (float) $raw;
                $text = "Improve link quality by making links more topically relevant, try to get a score of 80%. The current score is: " . self::wpil_dash_fmt_pct($p, 0) . '.';
                break;

            case 'external_focus':
                $p = (float) $raw;
                $text = "Reduce external link emphasis. Aim for 60% or less (currently " . self::wpil_dash_fmt_pct($p, 0) . ').';
                break;

            default:
                // keep default $text
                break;
        }

        return [
            'text' => $text,
            // Only set target if the action exists, otherwise we fall back to linking to report
            'target_action_id' => $has_action ? $action_id : '',
            'fallback_url' => $has_action ? '' : $fallback_url,
        ];
    }


    public static function wpil_dash_site_health_score(array $m) {
        $posts = isset($m['site_post_count']) ? max(1, (int) $m['site_post_count']) : (isset($m['posts_crawled']) ? max(1, (int) $m['posts_crawled']) : 1);

        $broken = isset($m['broken_links']) ? max(0, (int) $m['broken_links']) : 0;
        $orphan = isset($m['orphaned_posts']) ? max(0, (int) $m['orphaned_posts']) : 0;

        $coverage = isset($m['link_coverage_percent']) ? (float) $m['link_coverage_percent'] : 0.0;
        $related  = isset($m['link_relatedness_percent']) ? (float) $m['link_relatedness_percent'] : 0.0;
        $external = isset($m['external_site_focus']) ? (float) $m['external_site_focus'] : 0.0;

        // Clamp percent style inputs
        $coverage = max(0, min(100, $coverage));
        $related  = max(0, min(100, $related));
        $external = max(0, min(100, $external));

        // Counts to 0-100 using exponential decay by "issues per post"
        // k is "tolerance". Lower k means harsher penalties.
        $count_score = function($count, $posts, $k) {
            if ($count <= 0) return 100.0;
            $rate = $count / max(1, $posts); // issues per post
            $score = 100.0 * exp(-($rate / max(1e-9, $k)));
            return max(0.0, min(100.0, $score));
        };

        // Broken links should hurt more than orphans
        $broken_score = $count_score($broken, $posts, 0.005); // 0.5% issues per post tolerance
        $orphan_score = $count_score($orphan, $posts, 0.02);  // 2% tolerance

        // Percent metrics as-is (already 0-100 where higher is better)
        $coverage_score = $coverage;
        $related_score  = $related;

        // External site focus is better when lower (<=60 is great)
        // 0..60 maps to 100..100, then starts falling, 80+ is very bad
        $external_score = 0.0;
        if ($external <= 60) {
            $external_score = 100.0;
        } elseif ($external >= 80) {
            $external_score = 0.0;
        } else {
            // 60..80 linearly down 100..0
            $external_score = 100.0 * (1.0 - (($external - 60.0) / 20.0));
        }

        // Weights: make "fixable, impactful" items dominate
        $weights = [
            'broken'   => 0.30,
            'orphan'   => 0.20,
            'coverage' => 0.20,
            'related'  => 0.20,
            'external' => 0.10,
        ];

        $score =
            ($broken_score   * $weights['broken']) +
            ($orphan_score   * $weights['orphan']) +
            ($coverage_score * $weights['coverage']) +
            ($related_score  * $weights['related']) +
            ($external_score * $weights['external']);

        $score = (int) round(max(0, min(100, $score)));

        // Build "biggest drag" (largest weighted penalty)
        $parts = [
            'broken_links' => [
                'label' => 'Broken Links',
                'score' => $broken_score,
                'weight' => $weights['broken'],
                'raw' => $broken,
            ],
            'orphaned_posts' => [
                'label' => 'Orphaned Posts',
                'score' => $orphan_score,
                'weight' => $weights['orphan'],
                'raw' => $orphan,
            ],
            'link_coverage' => [
                'label' => 'Link Coverage',
                'score' => $coverage_score,
                'weight' => $weights['coverage'],
                'raw' => $coverage, // percent
            ],
            'link_quality' => [
                'label' => 'Link Quality',
                'score' => $related_score,
                'weight' => $weights['related'],
                'raw' => $related, // percent
            ],
            'external_focus' => [
                'label' => 'External Focus',
                'score' => $external_score,
                'weight' => $weights['external'],
                'raw' => $external, // percent
            ],
        ];

        $top = null;
        $top_penalty = -1;

        foreach ($parts as $key => $p) {
            $penalty = (100.0 - (float) $p['score']) * (float) $p['weight'];
            if ($penalty > $top_penalty) {
                $top_penalty = $penalty;
                $top = ['key' => $key, 'penalty' => $penalty] + $p;
            }
        }

        return [
            'score' => $score,
            'broken_score' => $broken_score,
            'orphan_score' => $orphan_score,
            'coverage_score' => $coverage_score,
            'related_score' => $related_score,
            'external_score' => $external_score,
            'top_drag' => $top
        ];
    }

    public static function wpil_dash_site_health_meta($score) {
        $score = (int) $score;

        if ($score >= 85) {
            return ['label' => 'Great', 'hint' => 'Your site is in strong shape.', 'ring' => 'text-green-500'];
        }
        if ($score >= 70) {
            return ['label' => 'Good', 'hint' => 'A few fixes will move the needle.', 'ring' => 'text-orange-500'];
        }
        if ($score >= 50) {
            return ['label' => 'Needs Work', 'hint' => 'Fixing the serious items will help fast.', 'ring' => 'text-orange-600'];
        }
        return ['label' => 'Poor', 'hint' => 'Start with broken links and orphans.', 'ring' => 'text-red-500'];
    }

    public static function wpil_dash_site_health_hint(array $health) {
        $top = isset($health['top_drag']) && is_array($health['top_drag']) ? $health['top_drag'] : null;

        if (empty($top) || empty($top['key'])) {
            return 'Your site is in good shape. Keep building internal links consistently.';
        }

        $key = (string) $top['key'];
        $raw = isset($top['raw']) ? $top['raw'] : null;

        switch ($key) {

            case 'broken_links':
                $n = (int) $raw;
                if ($n > 0) {
                    return "Start by fixing {$n} broken links. This is the biggest issue affecting your site health.";
                }
                return 'Start by fixing broken links. This is the biggest issue affecting your site health.';

            case 'orphaned_posts':
                $n = (int) $raw;
                if ($n > 0) {
                    return "Start by adding internal links to {$n} orphaned posts.";
                }
                return 'Start by adding internal links to orphaned posts.';

            case 'link_coverage':
                $p = (float) $raw;
                return "Increase internal link coverage to at least 80%. You’re currently at " .
                    self::wpil_dash_fmt_pct($p, 0) . '.';

            case 'link_quality':
                $p = (float) $raw;
                return "Improve link quality by making links more topically relevant. Current score: " .
                    self::wpil_dash_fmt_pct($p, 0) . '.';

            case 'external_focus':
                $p = (float) $raw;
                return "Reduce external link emphasis. Aim for 60% or less (currently " .
                    self::wpil_dash_fmt_pct($p, 0) . ').';

            default:
                return 'Start with the serious issues listed below to improve site health.';
        }
    }

    /**
     * Returns a fun human comparison for hours saved.
     */
    public static function get_time_saved_fun_message($hours_saved){

        if($hours_saved <= 0){
            return 'More free time is coming soon!';
        }

        $days = $hours_saved / 24;
        $workdays = $hours_saved / 8;
        $movies = $hours_saved / 2;
        $sleep_nights = $hours_saved / 8;
        $commutes = $hours_saved / 1; // 1 hour commute
        $gym_sessions = $hours_saved / 1.5;
        $books = $hours_saved / 5;
        $coffee_breaks = $hours_saved / 0.25;

        $messages = [

            // Lifestyle
            sprintf("That’s about %s full work days back in your life!", number_format_i18n($workdays, 1)),
            sprintf("That’s %s nights of great sleep!", number_format_i18n($sleep_nights, 1)),
            sprintf("That’s %s movie nights you didn’t have before!", number_format_i18n($movies, 1)),

            // Productivity
            sprintf("Enough time to write %s blog posts!", number_format_i18n($workdays, 1)),
            sprintf("That’s %s deep-focus sessions you just gained!", number_format_i18n($workdays * 2, 1)),

            // Fun comparisons
            sprintf("That’s %s coffee breaks ☕", number_format_i18n($coffee_breaks, 0)),
            sprintf("Enough time for %s gym sessions 💪", number_format_i18n($gym_sessions, 1)),
            sprintf("That’s %s hours you didn’t spend doing tedious linking!", number_format_i18n($hours_saved, 1)),

            // Freedom framing (very strong psychologically)
            sprintf("That’s %s hours you got back to focus on what matters!", number_format_i18n($hours_saved, 1)),
            sprintf("You just reclaimed %s hours of your month!", number_format_i18n($hours_saved, 1)),

            // Owner/business framing
            sprintf("That’s %s hours you can reinvest into growth!", number_format_i18n($hours_saved, 1)),
            sprintf("Like hiring help for %s hours — without payroll!", number_format_i18n($hours_saved, 1)),

            // Commute pain (relatable)
            sprintf("That’s %s daily commutes you skipped!", number_format_i18n($commutes, 0)),

        ];

        return $messages[array_rand($messages)];
    }






    /******* /Tailwind Styled Dashboard Renderers *******/

}
