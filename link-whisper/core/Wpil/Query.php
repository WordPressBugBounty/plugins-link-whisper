<?php

/**
 * Work with DB queries
 */
class Wpil_Query
{
    /**
     * Get post statuses query row
     *
     * @param string $table
     * @return string
     */
    public static function postStatuses($table = '')
    {
        $query = "";
        $statuses = Wpil_Settings::getPostStatuses();
        if (!empty($statuses)) {
            $query = " AND " . (!empty($table) ? $table."." : "") . "post_status IN ('" . implode("', '", $statuses) . "') ";
        }

        return $query;
    }

    /**
     * Get post types query row
     *
     * @param string $table
     * @return string
     */
    public static function postTypes($table = '')
    {
        $query = "";
        $post_types = Wpil_Settings::getPostTypes();
        if (!empty($post_types)) {
            $query = " AND " . ((!empty($table)) ? $table . ".post_type" : "`post_type`") . " IN ('" . implode("', '", $post_types) . "') ";
        }

        return $query;
    }

    /**
     * Get term taxonomy query row
     *
     * @param string $table
     * @return string
     */
    public static function taxonomyTypes($table = '')
    {
        $query = "";
        $taxonomies = Wpil_Settings::getTermTypes();
        if (!empty($taxonomies)) {
            $query = " AND taxonomy IN ('" . implode("', '", $taxonomies) . "')";
        }

        return $query;
    }

    /**
     * Get the query string for ACF field keys that the user has chosen to process
     *
     * @param string $table
     * @return string
     */
    public static function querySpecifiedAcfFields($table = '')
    {
        global $wpdb;

        $query = "";
        $fields = Wpil_Settings::getACFFieldsToProcess();
        if(!empty($fields)){
            $query_fields = array();
            foreach($fields as $field){
                $field = trim($field);
                // skip the field if it's empty or just the "%" sign
                if(empty($field) || $field === '%'){
                    continue;
                }
                $query_fields[] = $wpdb->prepare( ((!empty($table)) ? $table . ".meta_key" : "`meta_key`") . " LIKE %s ", $field);
            }
            if(!empty($query_fields)){
                $query = " AND (" . implode(" OR ", $query_fields) . ")";
            }
        }

        return $query;
    }

    /**
     * Get posts IDs for report query
     * Currently only gets the orphaned post ids if we're loading the Orphaned Report
     *
     * @param false $orphaned
     * @return string
     */
    public static function reportPostIds($orphaned = false)
    {
        global $wpdb;
        $link_report_table = $wpdb->prefix . 'wpil_report_links';

        if(!$orphaned){
            return "";
        }else{
            // remove any links that are on the ignore orphan list or are hidden by redirects
            $ignored = '';
            $ignore_ids = array_unique(array_merge(array(), Wpil_Settings::getItemTypeIds(Wpil_Settings::getIgnoreOrphanedPosts(), 'post'), Wpil_Settings::getRedirectedPosts()));

            if(!empty($ignore_ids)){
                $ignored .= " AND p.ID NOT IN (" . implode(',', $ignore_ids) . ") ";
            }

            return Wpil_Settings::use_link_table_for_data() ? " AND p.ID NOT IN (select distinct `target_id` from {$link_report_table} where `target_type` = 'post') {$ignored}": " AND p.ID IN (select post_id from {$wpdb->postmeta} where meta_key = 'wpil_links_inbound_internal_count' and meta_value = '0') {$ignored}";
        }
    }

    /**
     * Get terms IDs for report query
     *
     * @param false $orphaned
     * @return string
     */
    public static function reportTermIds($orphaned = false, $hide_noindex = false)
    {
        global $wpdb;
        $link_report_table = $wpdb->prefix . 'wpil_report_links';

        if ($orphaned) {
            if(Wpil_Settings::use_link_table_for_data()){
                $ids = $wpdb->get_col("SELECT term_id FROM {$wpdb->terms} WHERE `term_id` NOT IN (select distinct `target_id` from {$link_report_table} where `target_type` = 'term')");
            }else{
                $ids = $wpdb->get_col("SELECT term_id FROM {$wpdb->terms} WHERE term_id IN (select distinct `post_id` from {$link_report_table} where `post_type` = 'term')");
                $ids = array_intersect($ids, $ids = $wpdb->get_col("SELECT term_id FROM {$wpdb->termmeta} WHERE meta_key = 'wpil_links_inbound_internal_count' AND meta_value = '0'"));
            }

            // remove any links that are on the ignore orphan list
            $ignored = Wpil_Settings::getItemTypeIds(Wpil_Settings::getIgnoreOrphanedPosts(), 'term');
            $ids = array_diff($ids, $ignored);
        }else{
            $ids = $wpdb->get_col("SELECT term_id FROM {$wpdb->terms} WHERE term_id IN (select distinct `post_id` from {$link_report_table} where `post_type` = 'term')");
        }

        if($orphaned || $hide_noindex){
            //** Remove any noIndex post ids **//
            $ids = self::remove_noindex_ids($ids, 'term');
        }

        if(!empty($ids)){
            $taxonomies = self::taxonomyTypes();
            $ids2 = implode(',', $ids);
            $ids = array_intersect($ids, $ids = $wpdb->get_col("SELECT term_id FROM {$wpdb->term_taxonomy} WHERE term_id IN ({$ids2}) {$taxonomies}"));
        }

        return implode(',', $ids);
    }

    /**
     * Removes any noindexed post|term ids from the given list of ids.
     * Can also work with a single item id.
     * @param array|int $ids A list of ids to remove noindexed ids from
     * @param string $type The data type that we're removing the noindexed ids from (post|term)
     * @return array $ids A cleaned list of ids.
     **/
    public static function remove_noindex_ids($ids = array(), $type = 'post'){
        global $wpdb;

        $single = false;
        if(is_int($ids)){
            $ids = array($ids);
            $single = true;
        }

        // if RankMath is active, remove any ids that are set to "noIndex"
        if(defined('RANK_MATH_VERSION')){
            if($type === 'post'){
                $diff_ids = array();
                $rank_math_meta = $wpdb->get_results("SELECT `post_id`, `meta_value` FROM {$wpdb->postmeta} WHERE `meta_key` = 'rank_math_robots'");
            
                foreach($rank_math_meta as $data){
                    if(false !== strpos($data->meta_value, 'noindex')){ // we can check the unserialized data because Rank Math uses a simple flag like structure to the saved data.
                        // NOTE: if the friends want to include the global no index rules, there's a "is_post_indexable" function that should do it. I went this route because it should be faster on large sites.
                        $diff_ids[] = $data->post_id;
                    }
                }

                $ids = array_diff($ids, $diff_ids);
            }else{
                foreach($ids as $key => $id){
                    $term = get_term($id);
                    if(is_a($term, 'WP_Error') || empty(\RankMath\Helper::is_term_indexable($term))){
                        unset($ids[$key]);
                    }
                }
            }
        }

        // if Yoast is active rmeove any ids that are set to "noIndex"
        if(defined('WPSEO_VERSION')){
            if($type === 'post'){
                $no_index_ids = $wpdb->get_col("SELECT DISTINCT `post_id` FROM {$wpdb->postmeta} WHERE meta_key = '_yoast_wpseo_meta-robots-noindex' AND meta_value = '1'");
                $ids = array_diff($ids, $no_index_ids);
            }else{
                $yoast_taxonomy_data = get_site_option('wpseo_taxonomy_meta');
                if(!empty($yoast_taxonomy_data)){
                    foreach($ids as $key => $id){
                        // if the category has been set to noIndex
                        if( isset($yoast_taxonomy_data[$id]) &&
                            isset($yoast_taxonomy_data[$id]['wpseo_noindex']) && 
                            'noindex' === $yoast_taxonomy_data[$id]['wpseo_noindex'])
                        {
                            // remove the id from the list
                            unset($ids[$key]);
                        }
                    }
                }
            }
        }

        if($single){
            return (!empty($ids)) ? $ids[0]: array(); // returns the id if it's not noindexed, but returns an array if empty just incase there's a foreach or implode waiting somewhere
        }else{
            return $ids;
        }
    }

    public static function ignoredPostIds(){
        $post_ids = Wpil_Settings::getAllIgnoredPosts();

        if(empty($post_ids)){
            return '';
        }

        $ids = array();
        foreach($post_ids as $post_id){
            if(false !== strpos($post_id, 'post_')){
                $ids[] = substr($post_id, 5);
            }
        }

        return !empty($ids) ? " AND p.ID NOT IN (" . implode(',', $ids) . ")" : "";
    }

    public static function ignoredTermIds(){
        $post_ids = Wpil_Settings::getAllIgnoredPosts();

        if(empty($post_ids)){
            return '';
        }

        $ids = array();
        foreach($post_ids as $post_id){
            if(false !== strpos($post_id, 'term_')){
                $ids[] = substr($post_id, 5);
            }
        }

        return !empty($ids) ? " AND t.term_id NOT IN (" . implode(',', $ids) . ")" : "";
    }

    public static function ignoredExternalPostIds(){
        $post_ids = Wpil_Settings::getIgnoreExternalPosts();

        if(empty($post_ids)){
            return '';
        }

        $ids = array();
        foreach($post_ids as $post_id){
            if(false !== strpos($post_id, 'post_')){
                $ids[] = substr($post_id, 5);
            }
        }

        return !empty($ids) ? " AND p.ID NOT IN (" . implode(',', $ids) . ")" : "";
    }

    public static function ignoredExternalTermIds(){
        $post_ids = Wpil_Settings::getIgnoreExternalPosts();

        if(empty($post_ids)){
            return '';
        }

        $ids = array();
        foreach($post_ids as $post_id){
            if(false !== strpos($post_id, 'term_')){
                $ids[] = substr($post_id, 5);
            }
        }

        return !empty($ids) ? " AND t.term_id NOT IN (" . implode(',', $ids) . ")" : "";
    }

    public static function getReportLinksIgnoreQueryStrings(){
        // if the user is hiding the ignored posts
        $ignored = "";
        if(Wpil_Settings::hideIgnoredPosts()){
            // don't count the links from ignored posts
            $posts = Wpil_Settings::getAllIgnoredPosts();
            if(!empty($posts)){
                $post_data = array();
                $term_data = array();
                foreach($posts as $post){
                    $post = explode('_', $post);
                    if($post[0] === 'post'){
                        $post_data[] = $post[1];
                    }else{
                        $term_data[] = $post[1];
                    }
                }

                if(!empty($post_data)){
                    $ignored .= " AND (`post_type` = 'post' AND `post_id` NOT IN (" . implode(', ', $post_data) . "))";
                }

                if(!empty($term_data)){
                    $sign = (!empty($post_data)) ? "OR": "AND";
                    $ignored .= " {$sign} (`post_type` = 'term' AND `post_id` NOT IN (" . implode(', ', $term_data) . "))";
                }

            }
        }

        return $ignored;
    }

    /**
     * Gets the query string for obtaining posts newer than the user's date restrictions
     **/
    public static function getPostDateQueryLimit($table = '', $limit_type = 'suggestion'){
        // if the user is age limiting the posts
        if($limit_type === 'ai'){
            $age_limit = Wpil_Settings::get_ai_max_processing_age();
        }else{
            $age_limit = get_option('wpil_max_linking_age', 0);
        }

        $age_query = '';
        if(!empty($age_limit)){
            if(function_exists('wp_date')){
                $time_string = wp_date('Y-m-d H:i:s', (time() - ($age_limit * YEAR_IN_SECONDS)));
            }else{
                $time_string = date_i18n('Y-m-d H:i:s', (time() - ($age_limit * YEAR_IN_SECONDS)));
            }
            $col = (!empty($table)) ? ($table . '.post_date_gmt'): '`post_date_gmt`';
            $age_query .= "AND {$col} > '{$time_string}'";
        }

        return $age_query;
    }

    /**
     * Gets a query string for ignoring post ids from all sources. 
     * @param string $table The table variable that we're building the query for
     * @param array $args An array of indexes that we're going to use to get ids to ignore
     * @todo Migrate the term_id getting functionality so it works like the whole system that uses this. (Includes some filter and query changes in the reports)
     **/
    public static function get_all_report_ignored_post_ids($table = '', $args = array()){
        global $wpdb;

        $ids = array();

        if(isset($args['orphaned']) && !empty($args['orphaned'])){
            // remove any links that are on the ignore orphan list
            $ignored = Wpil_Settings::getItemTypeIds(Wpil_Settings::getIgnoreOrphanedPosts(), 'post');
            $ids = array_merge($ids, $ignored);

            // also remove any posts that are hidden by redirects
            $redirected = Wpil_Settings::getRedirectedPosts();
            $ids = array_merge($ids, $redirected);
        }

        if( (isset($args['orphaned']) && !empty($args['orphaned'])) || (isset($args['hide_noindex']) && !empty($args['hide_noindex'])) ){
            //** Remove any noIndex post ids **//
            // if RankMath is active, remove any ids that are set to "noIndex"
            if(defined('RANK_MATH_VERSION')){
                $id_string = " `post_id` NOT IN ('" . implode("', '", $ids) . "')";
                $rank_math_meta = $wpdb->get_results("SELECT `post_id`, `meta_value` FROM {$wpdb->postmeta} WHERE {$id_string} AND `meta_key` = 'rank_math_robots'");

                foreach($rank_math_meta as $data){
                    if(false !== strpos($data->meta_value, 'noindex')){ // we can check the unserialized data because Rank Math uses a simple flag like structure to the saved data.
                        // NOTE: if the friends want to include the global no index rules, there's a "is_post_indexable" function that should do it. I went this route because it should be faster on large sites.
                        $ids[] = $data->post_id;
                    }
                }
            }

            // if Yoast is active, remove any posts that are set to "noIndex"
            if(defined('WPSEO_VERSION')){
                $id_string = " `post_id` NOT IN ('" . implode("', '", $ids) . "')";
                $no_index_ids = $wpdb->get_col("SELECT DISTINCT `post_id` FROM {$wpdb->postmeta} WHERE $id_string AND meta_key = '_yoast_wpseo_meta-robots-noindex' AND meta_value = '1'");
                $ids = array_merge($ids, $no_index_ids);
            }
        }

        $hide_ignored = Wpil_Settings::hideIgnoredPosts();

        if($hide_ignored){
            $setting_ignored_ids = Wpil_Settings::getAllIgnoredPosts();

            if(!empty($setting_ignored_ids)){
                foreach($setting_ignored_ids as $post_id){
                    if(false !== strpos($post_id, 'post_')){
                        $ids[] = substr($post_id, 5);
                    }
                }
            }
        }

        if(!empty($ids)){
            $ids = array_flip(array_flip($ids)); 
        }

        // if the flag has been set to return the ids outside of a query string
        if(isset($args['return_ids'])){
            // return the ids
            return $ids;
        }

        if(!empty($ids)){
            return " AND " . (!empty($table) ? $table."." : "") . "ID NOT IN (" . implode(",", $ids) . ") ";
        }
        
        return '';
        // get any 
    }

    /**
     * Gets the query string for obtaining posts that weren't created by a user role that the customer wants to ignore from the suggestions
     **/
    public static function get_ignore_user_role_suggestions_query($table = ''){
        global $wpdb;

        // if the user is age limiting the posts
        $roles = Wpil_Settings::get_ignore_linking_roles();
        $role_query = '';
        if(!empty($roles)){
            // make sure that we're only processing roles that are registered on the site
            $possible_roles = Wpil_Settings::get_available_roles();
            $roles = array_intersect(array_keys($possible_roles), array_keys($roles));

            if(!empty($roles)){
                $role_string = '';
                foreach($roles as $role){
                    if(!empty($role_string)){
                        $role_string .= ' or ';
                    }
                    $role_string .= ('`meta_value` like \'%\"' . $role . '\"%\'');
                }

                $role_query = " AND " . (!empty($table) ? $table."." : "") . "post_author NOT IN (select `user_id` from {$wpdb->usermeta} where `meta_key` = 'wp_capabilities' and ({$role_string}))";
            }
        }

        return $role_query;
    }
}
