<?php

/**
 * Class Wpil_Dashboard
 */
class Wpil_Dashboard
{
    public static $domain_relation_cache = null;

    /**
     * Get posts count with selected types
     *
     * @return string|null
     */
    public static function getPostCount()
    {
        global $wpdb;
        $report_table = $wpdb->prefix . 'wpil_report_links';
        $post_types = implode("','", Wpil_Settings::getPostTypes());
        $statuses_query = Wpil_Query::postStatuses();
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
            $count = $wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$report_table} WHERE `post_type` = 'post' {$ignored}");
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
            $count = $wpdb->get_var("SELECT count(p.ID) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON p.ID = m.post_id WHERE post_type IN ('$post_types') $statuses_query {$ignored} AND meta_key = 'wpil_sync_report3' AND meta_value = '1'");
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
    public static function getLinksCount()
    {
        if (!Wpil_Report::link_table_is_created()) {
            return 0;
        }

        global $wpdb;

        // if the user is hiding the ignored posts, get the posts to ignore
        $ignored = Wpil_Query::getReportLinksIgnoreQueryStrings();

        return $wpdb->get_var("SELECT count(*) FROM {$wpdb->prefix}wpil_report_links WHERE `has_links` = 1 {$ignored}");
    }

    /**
     * Get internal links count
     *
     * @return string|null
     */
    public static function getInternalLinksCount()
    {
        if (!Wpil_Report::link_table_is_created()) {
            return 0;
        }

        global $wpdb;

        // if the user is hiding the ignored posts, get the posts to ignore
        $ignored = Wpil_Query::getReportLinksIgnoreQueryStrings();

        return $wpdb->get_var("SELECT count(*) FROM {$wpdb->prefix}wpil_report_links WHERE internal = 1 {$ignored}");
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
     * @return string|null
     */
    public static function getBrokenLinksCount()
    {
        global $wpdb;
        Wpil_Error::prepareTable(false);
        if(!empty(get_option('wpil_site_db_version', false))){
            return $wpdb->get_var("SELECT count(*) FROM {$wpdb->prefix}wpil_broken_links WHERE `ignore_link` != 1 AND (`code` < 200 OR `code` > 299)");
        }else{
            return $wpdb->get_var("SELECT count(*) FROM {$wpdb->prefix}wpil_broken_links WHERE `code` < 200 OR `code` > 299");
        }
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

        $thirty_days_ago = strtotime('30 days ago');

        return $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE `creation_time` > {$thirty_days_ago}");
    }

    /**
     * Gets the distribution of external links as a proportion of the overall total of external links.
     **/
    public static function get_external_link_distribution($limit = 0, $host = ''){
        global $wpdb;
        $table = $wpdb->prefix . 'wpil_report_links';

        if(self::$domain_relation_cache !== null){
            if(!empty($limit)){
                return array_slice(self::$domain_relation_cache, 0, $limit);
            }

            if(!empty($host)){
                foreach(self::$domain_relation_cache as $domain){
                    if($domain->host === $host){
                        return $domain;
                    }
                }
            }
        }

        self::$domain_relation_cache = $wpdb->get_results("SELECT COUNT(*) AS 'link_count', host FROM {$table} WHERE `internal` = 0 GROUP BY `host` ORDER BY `link_count` DESC");

        $total = 0;
        if(!empty(self::$domain_relation_cache)){
            foreach(self::$domain_relation_cache as $dat){
                $total += $dat->link_count;
            }

            foreach(self::$domain_relation_cache as &$dat){
                $dat->representation = $dat->link_count / $total;
            }
        }

        if(!empty($limit)){
            return array_slice(self::$domain_relation_cache, 0, $limit);
        }

        if(!empty($host)){
            foreach(self::$domain_relation_cache as $domain){
                if($domain->host === $host){
                    return $domain;
                }
            }
        }

        return self::$domain_relation_cache;
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
            $host_search = array();
            foreach($hosts as $host){
                $host_search[] = $host->host;
            }
            $host_search = " AND host IN ('" . implode('\', \'', array_flip(array_flip($host_search))) . "')";
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
                    'post' => $p,
                    'link_whisper_created' => (isset($link->link_whisper_created) && !empty($link->link_whisper_created)) ? 1: 0,
                    'is_autolink' => (isset($link->is_autolink) && !empty($link->is_autolink)) ? 1: 0,
                    'tracking_id' => (isset($link->tracking_id) && !empty($link->tracking_id)) ? $link->tracking_id: 0,
                    'module_link' => (isset($link->module_link) && !empty($link->module_link)) ? $link->module_link: 0,
                    'link_context' => (isset($link->link_context) && !empty($link->link_context)) ? $link->link_context: 0,
                    'ai_relation_score' => (isset($link->ai_relation_score) && !empty($link->ai_relation_score)) ? $link->ai_relation_score: 0,
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
                'post' => $p,
                'link_whisper_created' => (isset($link->link_whisper_created) && !empty($link->link_whisper_created)) ? 1: 0,
                'is_autolink' => (isset($link->is_autolink) && !empty($link->is_autolink)) ? 1: 0,
                'tracking_id' => (isset($link->tracking_id) && !empty($link->tracking_id)) ? $link->tracking_id: 0,
                'module_link' => (isset($link->module_link) && !empty($link->module_link)) ? $link->module_link: 0,
                'link_context' => (isset($link->link_context) && !empty($link->link_context)) ? $link->link_context: 0,
                'ai_relation_score' => (isset($link->ai_relation_score) && !empty($link->ai_relation_score)) ? $link->ai_relation_score: 0
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
                    <input type="checkbox" class="wpil_link_select" data-post_id="'.$link->post->id.'" data-post_type="'.$link->post->type.'" data-anchor="' . esc_attr(base64_encode($link->anchor)) . '" data-url="'.base64_encode($link->url).'">
                    <div>
                        <div style="margin: 3px 0;"><b>Post Title:</b> <a href="' . esc_url($link->post->getLinks()->view) . '" target="_blank">' . esc_html($link->post->getTitle()) . '</a></div>
                        <div style="margin: 3px 0;"><b>URL:</b> <a href="' . esc_url($link->url) . '" target="_blank">' . esc_html($link->url) . '</a></div>
                        <div style="margin: 3px 0;"><b>Anchor Text:</b> <a href="' . esc_url(add_query_arg(['wpil_admin_frontend' => '1', 'wpil_admin_frontend_data' => $link->create_scroll_link_data()], $link->post->getLinks()->view)) . '" target="_blank">' . esc_html($link->anchor) . ' <span class="dashicons dashicons-external" style="position: relative;top: 3px;"></span></a></div>
                        ' . Wpil_Report::get_dropdown_icons($link->post, $link);

                        if('related-post-link' !== Wpil_Toolbox::get_link_context($link->link_context)){
                $response .= '<a href="#" class="wpil_edit_link" target="_blank">[' . __('Edit URL', 'wpil') . ']</a>
                                <div class="wpil-domains-report-url-edit-wrapper">
                                    <input class="wpil-domains-report-url-edit" type="text" value="' . esc_attr($link->url) . '">
                                    <button class="wpil-domains-report-url-edit-confirm wpil-domains-edit-link-btn" data-link_id="' . $link->link_id . '" data-post_id="'.$link->post->id.'" data-post_type="'.$link->post->type.'" data-anchor="' . esc_attr($link->anchor) . '" data-url="'.esc_url($link->url).'" data-nonce="' . wp_create_nonce('wpil_report_edit_' . $link->post->id . '_nonce_' . $link->link_id) . '">
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
                foreach($domains as $domain){
                    $cleaned_hosts[] = wp_parse_url(esc_url_raw($domain), PHP_URL_HOST);
                }

                $host = "AND host IN ('" . implode("', '", $cleaned_hosts) . "')";

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
                    '<th>URL</th>'
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
                foreach($domains as $domain){
                    $cleaned_hosts[] = wp_parse_url(esc_url_raw($domain), PHP_URL_HOST);
                }

                $host = "AND host IN ('" . implode("', '", $cleaned_hosts) . "')";

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
                        'post' => $p,
                        'link_whisper_created' => (isset($link->link_whisper_created) && !empty($link->link_whisper_created)) ? 1: 0,
                        'is_autolink' => (isset($link->is_autolink) && !empty($link->is_autolink)) ? 1: 0,
                        'tracking_id' => (isset($link->tracking_id) && !empty($link->tracking_id)) ? $link->tracking_id: 0,
                        'module_link' => (isset($link->module_link) && !empty($link->module_link)) ? $link->module_link: 0,
                        'link_context' => (isset($link->link_context) && !empty($link->link_context)) ? $link->link_context: 0,
                        'ai_relation_score' => (isset($link->ai_relation_score) && !empty($link->ai_relation_score)) ? $link->ai_relation_score: 0
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
                                <td>
                                    <div style="margin: 3px 0; display:none"><a href="' . esc_url($p->getLinks()->edit) . '" target="_blank">Edit Post</a></div>
                                    <div style="margin: 3px 0; display:none;">'.$edit_link.'</div>
                                </td>';
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

        // if the scan has been run since the 2.6.5 update
        if(version_compare(get_option('wpil_scan_last_plugin_version', '0.0.1'), '2.6.5', '>=')){
            // consisely query the database for data
            $total = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE internal = 1");
            $filtered = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE internal = 1 AND `anchor_word_count` > 2 AND `anchor_word_count` < 8");
        }else{
            // if the scan hasn't been run since the update, process the word counts out of the existing anchors
            // Get all internal anchors (fetch anchor text)
            $anchors = $wpdb->get_results(
                "SELECT anchor FROM $table WHERE internal = 1",
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
        // Step 1: Get all post/term IDs with at least 3 outbound internal links
        $outbound_query = "
            SELECT post_id, post_type
            FROM {$link_table}
            WHERE internal = 1 AND (post_type = 'post' OR post_type = 'term')
            GROUP BY post_id, post_type
            HAVING COUNT(*) >= 3
        ";
        $outbound_results = $wpdb->get_results($outbound_query, ARRAY_A);
        // Normalize outbound keys
        $outbound_keys = array_map(function($row) {
            return $row['post_type'] . '_' . $row['post_id'];
        }, $outbound_results);
        // Step 2: Get all target post/term IDs with at least 1 inbound internal link
        $inbound_query = "
            SELECT target_id, target_type
            FROM {$link_table}
            WHERE internal = 1 AND (target_type = 'post' OR target_type = 'term')
            GROUP BY target_id, target_type
            HAVING COUNT(*) >= 1
        ";
        $inbound_results = $wpdb->get_results($inbound_query, ARRAY_A);
        // Normalize inbound keys
        $inbound_keys = array_map(function($row) {
            return $row['target_type'] . '_' . $row['target_id'];
        }, $inbound_results);
        // Step 3: Find intersection
        
        $matching_keys = array_intersect(array_unique($outbound_keys), array_unique($inbound_keys));
        // Step 4: Get total number of posts + terms (published posts + all terms)

        $post_ids = Wpil_Report::get_all_post_ids();
        $term_ids = Wpil_Report::get_all_term_ids();

        $post_count = (!empty($post_ids) && is_array($post_ids)) ? count($post_ids): 0;
        $term_count = (!empty($term_ids) && is_array($term_ids)) ? count($term_ids): 0;

        $total_items = $post_count + $term_count;

        // Step 5: Calculate percentage
        $qualified = count($matching_keys);
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

        $links = $wpdb->get_row("SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN `ai_relation_score` > 0.5 THEN 1 ELSE 0 END) AS related,
                SUM(CASE WHEN `ai_relation_score` < 0.5 THEN 1 ElSE 0 END) AS unrelated
            FROM {$table}
            WHERE `internal` = 1 AND `ai_relation_score` > 0");

        $percent = 0;
        if(!empty($links) && isset($links->total) && !empty($links->total)){
            if(!empty($links->related)){
                $percent = round($links->related / $links->total, 2) * 100;
            }
        }

        return $percent;
    }
    
}
