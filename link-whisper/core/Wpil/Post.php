<?php

/**
 * Work with post
 */
class Wpil_Post
{
    public static $advanced_custom_fields_list = null;
    public static $post_types_without_editors = array(
        'web-story'
    );
    public static $post_url_cache = array();
    public static $editor_insert_log = array();

    /**
     * Register services
     */
    public function register()
    {
        add_action('draft_to_published', [$this, 'updateStatMark'], 99999);
        add_action('save_post', [$this, 'updateStatMark'], 99999);
        add_action('before_delete_post', [$this, 'deleteReferences']);
        add_filter('wp_link_query_args', array(__CLASS__, 'filter_custom_link_post_types'), 10, 1);
        add_filter('wp_link_query', array(__CLASS__, 'custom_link_category_search'), 10, 2);
    }

    /**
     * Ignores the selected orphaned post on the orphaned post view.
     **/
    function ajaxIgnoreOrphanedPost(){
        Wpil_Base::verify_nonce('ignore-orphaned-post-nonce');

        if(!isset($_POST['post_ids']) || empty($_POST['post_ids']) || !is_array($_POST['post_ids'])){
            wp_send_json(array('error' => array('title' => __('Post id empty', 'wpil'),'text' => __('The post id was missing from the ignore orphaned post request.', 'wpil'))));
        }

        // get all the ignored orphaned posts (including ignored by category)
        $ignored = Wpil_Settings::getIgnoreOrphanedPosts();

        // get any specifically ignored posts
        $ignored_posts = get_option('wpil_ignore_orphaned_posts', '');

        foreach($_POST['post_ids'] as $pid){
            // if the post is ignored, move on to the next one
            if(in_array($pid, $ignored, true)){
                continue;
            }

            $bits = explode('_', $pid);

            // get the post
            $post = new Wpil_Model_Post((int)$bits[1], sanitize_text_field($bits[0]));

            $post_link = $post->getViewLink();
            if(!empty(self::getPostByLink($post_link))){
                $ignored_posts .= "\n" . $post_link;
            }else{
                $ignored_posts .= "\n" . $post->getViewLink(false, true); // if we can't turn the url into a viable post, go with the "Ugly" url instead.
            }
        }

        update_option('wpil_ignore_orphaned_posts', $ignored_posts);

        wp_send_json(array('success' => true));
    }

    /**
     * Filters the post types that the custom link search box will look for so the user is only shown selected post types
     **/
    public static function filter_custom_link_post_types($query_args){
        if(!empty($_POST) && isset($_POST['wpil_custom_link_search'])){
            $selected_post_types = Wpil_Settings::getPostTypes();
            if(!empty($selected_post_types)){
                $query_args['post_type'] = $selected_post_types;
            }
        }
        return $query_args;
    }

    /**
     * Queries for terms when the user does a custom link search for outbound suggestions.
     * The existing search only does posts, so we have to do the terms separately
     **/
    public static function custom_link_category_search($queried_items = array()){
        if(!empty($_POST) && isset($_POST['wpil_custom_link_search'])){

            $selected_terms = get_option('wpil_2_term_types', array());

            if(empty($selected_terms)){
                return $queried_items;
            }

            $args = array('taxonomy' => $selected_terms, 'search' => $_POST['search'], 'number' => 20);

            $term_query = new WP_Term_Query($args);
            $terms = $term_query->get_terms();

            if(empty($terms)){
                return $queried_items;
            }

            foreach($terms as $term){
                $queried_items[] = array(
                    'ID' => $term->term_id,
                    'title' => $term->name,
                    'permalink' => get_term_link($term->term_id),
                    'info' => ucfirst($term->taxonomy),
                );

            }
        }

        return $queried_items;
    }

    /**
     * Set mark for post to update report
     *
     * @param $post_id
     */
    public static function updateStatMark($post_id, $direct_call = false)
    {
        // don't save links for revisions
        if(wp_is_post_revision($post_id)){
            return;
        }

        // make sure the post isn't an auto-draft
        $post = get_post($post_id);
        if(!empty($post) && 'auto-draft' === $post->post_status){
            return;
        }

        // make sure we're checking the link stats at the end of the processing or that it's been called directly
        if(99999 !== Wpil_Toolbox::get_current_action_priority() && !$direct_call){
            return;
        }

        // if this is a reusable block
        if($post->post_type === 'wp_block'){
            // process it's links to see if we need to update posts that it links to
            Wpil_Report::update_reusable_block_links($post); // reusable blocks update separately of the main post, so we're able to check at this point in the process!
        }

        // make sure this is for a post type that we track
        if(!in_array($post->post_type, Wpil_Settings::getPostTypes())){
            return;
        }

        // clear the meta flag
        update_post_meta($post_id, 'wpil_sync_report3', 0);

        if (get_option('wpil_option_update_reporting_data_on_save', false)) {
            Wpil_Report::fillMeta();
            if(WPIL_STATUS_LINK_TABLE_EXISTS){
                Wpil_Report::remove_post_from_link_table(new Wpil_Model_Post($post_id));
                Wpil_Report::fillWpilLinkTable();
            }
            Wpil_Report::refreshAllStat();
        }else{
            if(WPIL_STATUS_LINK_TABLE_EXISTS){
                $post = new Wpil_Model_Post($post_id);
                // if the current post has the Thrive builder active, load the Thrive content
                $thrive_active = get_post_meta($post->id, 'tcb_editor_enabled', true);
                if(!empty($thrive_active)){
                    $thrive_content = Wpil_Editor_Thrive::getThriveContent($post->id);
                    if($thrive_content){
                        $post->setContent($thrive_content);
                    }
                }
                if(Wpil_Report::stored_link_content_changed($post)){
                    // get the fresh post content for the benefit of the descendent methods
                    $post->getFreshContent();
                    // find any inbound internal link references that are no longer valid
                    $removed_links = Wpil_Report::find_removed_report_inbound_links($post);
                    // update the links stored in the link table
                    Wpil_Report::update_post_in_link_table($post);
                    // if the user is not just using the link table
                    if(!Wpil_Settings::use_link_table_for_data()){
                        // update the meta data for the post
                        Wpil_Report::statUpdate($post, true);
                        // update the link counts for the posts that this one links to
                        Wpil_Report::updateReportInternallyLinkedPosts($post, $removed_links);
                    }
                    // remove any broken links that are no longer in the post
                    Wpil_Error::update_broken_link_post_listing($post);
                }

                // if the links haven't changed, reset the processing flag
                update_post_meta($post_id, 'wpil_sync_report3', 1);
            }
        }
    }

    /**
     * Delete all post meta on post delete
     *
     * @param $post_id
     */
    public static function deleteReferences($post_id)
    {
        // if this is a post revision
        if(wp_is_post_revision($post_id)){
            // don't delete the references since that will pull the data for the parent post!
            return;
        }

        $post = new Wpil_Model_Post($post_id);

        // get the inbound links from the post meta
        $inbound = $post->getInboundInternalLinks();

        // if there are links
        if(!empty($inbound)){
            // remove each of the outbound links from the posts linking to this one
            foreach($inbound as $link){
                if(!isset($link->post) || empty($link->post)){
                    continue;
                }

                $stored_link = array();
                try {
                    $stored_links = $link->post->getOutboundInternalLinks();
                } catch (Throwable $t) {
                } catch (Exception $e) {
                }

                // if the current post does have links
                if(!empty($stored_links)){
                    // count how many we're starting with
                    $link_count = count($stored_links);
                    // and go over all the links available
                    foreach($stored_links as $key => $stored_link){
                        if(!isset($stored_link->post) || empty($stored_link->post)){
                            continue;
                        }

                        // if the other post has a link pointing to this one
                        if(trailingslashit($stored_link->url) === trailingslashit($link->url)){
                            // remove the link from the stored data
                            unset($stored_links[$key]);
                        }
                    }

                    // re-count the links so we can tell if we removed any
                    $new_count = count($stored_links);

                    // if we have removed links
                    if($link_count > $new_count){
                        // rekey the link array just in case something is index sensitive
                        $stored_links = array_values($stored_links);
                        // update the stored data and the stored link count
                        if($link->post->type === 'post'){
                            $stored_links = Wpil_Toolbox::update_encoded_post_meta($link->post->id, 'wpil_links_outbound_internal_count_data', $stored_links);
                            $stored_links = Wpil_Toolbox::update_encoded_post_meta($link->post->id, 'wpil_links_outbound_internal_count', $new_count);
                        }else{
                            $stored_links = Wpil_Toolbox::update_encoded_post_meta($link->post->id, 'wpil_links_outbound_internal_count_data', $stored_links);
                            $stored_links = Wpil_Toolbox::update_encoded_term_meta($link->post->id, 'wpil_links_outbound_internal_count', $new_count);
                        }
                    }
                }
            }
        }

        // remove the meta-based link data for this posts
        foreach (array_merge(Wpil_Report::$meta_keys, ['wpil_sync_report3', 'wpil_sync_report2_time']) as $key) {
            delete_post_meta($post_id, $key);
        }
        if(WPIL_STATUS_LINK_TABLE_EXISTS){
            // remove the current post from the links table and the links that point to it
            Wpil_Report::remove_post_from_link_table(new Wpil_Model_Post($post_id), true);
        }
    }

    /**
     * Get linked post Ids for current post
     *
     * @param $post
     * @param bool $return_ids Do we jsut return the linked post ids or the whole link object
     * @return array
     */
    public static function getLinkedPostIDs($post, $return_ids = true, $ignore_self = true)
    {
        $linked_post_ids = array();

        // get the inbound post links
        if(WPIL_STATUS_LINK_TABLE_EXISTS){
            $links = Wpil_Report::getCachedReportInternalInboundLinks($post);
        }else{
            $links = Wpil_Report::getInternalInboundLinks($post);
        }

        // if we're supposed to return just the ids
        if($return_ids){
            if($ignore_self){
                // process out the ids
                $linked_post_ids[] = $post->id;
            }

            foreach ($links as $link) {
                if (!empty($link->post->id)) {
                    $linked_post_ids[] = $link->post->id;
                }
            }
        }else{
            if($ignore_self){
                $url = $post->getLinks()->view;
                $host = parse_url($url, PHP_URL_HOST);


                $linked_post_ids[] = new Wpil_Model_Link([
                    'url' => $url,
                    'host' => str_replace('www.', '', $host),
                    'internal' => Wpil_Link::isInternal($url),
                    'post' => $post,
                    'anchor' => '',
                ]);
            }

            $linked_post_ids = array_merge($linked_post_ids, $links);
        }

        return $linked_post_ids;
    }

    /**
     * Get all Advanced Custom Fields names
     *
     * @return array
     */
    public static function getAdvancedCustomFieldsList($post_id)
    {
        global $wpdb;

        $fields = [];

        if(!class_exists('ACF') || get_option('wpil_disable_acf', false)){
            return $fields;
        }

        // get any ACF fields the user has ignored
        $ignored_fields = Wpil_Settings::getIgnoredACFFields();

        // get a list of ACF field rules to use for regular expressions
        $ignored_fields_wildcards = [];

        // get the content types that we'll be searching for
        $content_types = array('wysiwyg', 'textarea');
        if(!Wpil_Settings::get_ignore_acf_text_fields()){
            $content_types[] = 'text';
        }
        $content_types = " AND (`post_content` LIKE '%" . implode("%' OR `post_content` LIKE '%", $content_types) . "%')";

        if ( !empty($ignored_fields) ) {
            foreach($ignored_fields as $key => $rule) {
                if ( strpos($rule, '*') !== false ) {
                    $ignored_fields_wildcards[] = str_replace('*', '.*', $rule);
                    unset($ignored_fields[$key]);
                }
            }
            
            if ( !empty($ignored_fields_wildcards) ) {
                $ignored_fields_wildcards = implode('|', $ignored_fields_wildcards);
            }
        }

        // get any ACF fields that the user has chosen to focus on
        $acf_fields = Wpil_Query::querySpecifiedAcfFields();

        $fields_query = $wpdb->get_results("SELECT SUBSTR(meta_key, 2) as `name` FROM {$wpdb->postmeta} WHERE post_id = $post_id AND meta_value IN (SELECT DISTINCT post_name FROM {$wpdb->posts} WHERE post_name LIKE 'field_%' {$acf_fields} {$content_types}) AND SUBSTR(meta_key, 2) != ''");
       // print_r("SELECT SUBSTR(meta_key, 2) as `name` FROM {$wpdb->postmeta} WHERE post_id = $post_id AND meta_value IN (SELECT DISTINCT post_name FROM {$wpdb->posts} WHERE post_name LIKE 'field_%' {$acf_fields} {$content_types}) AND SUBSTR(meta_key, 2) != ''");
        foreach ($fields_query as $field) {
            $name = trim($field->name);

            if ( !empty($ignored_fields_wildcards) && preg_match('/' . $ignored_fields_wildcards . '/', $name) ) {
                continue;
            }

            if ($name) {
                $fields[] = $field->name;
            }
        }

        // if there are any fields created with PHP/JSON
        $local_field_groups = (function_exists('acf_get_local_store')) ? acf_get_local_store('groups') : false;
        if(!empty($local_field_groups) && isset($local_field_groups->data)){
            $search_fields = array();
            $secondary_lookup_fields = array();
            foreach($local_field_groups->data as $group){
                // go to some pains to ignore options pages
                if( isset($group['location']) &&
                    isset($group['location'][0]) &&
                    isset($group['location'][0][0]) &&
                    isset($group['location'][0][0]['param']) &&
                    $group['location'][0][0]['param'] == 'options_page' &&
                    $group['location'][0][0]['operator'] == '==')
                {
                    continue;
                }

                if(isset($group['name'])){
                    $search_fields[$group['name']] = true;
                }elseif(isset($group['key']) && function_exists('acf_get_fields')){
                    $secondary_fields = acf_get_fields($group['key']);
                    if(!empty($secondary_fields)){
                        foreach($secondary_fields as $field){
                            if( isset($field['type']) && 
                                ($field['type'] === 'textarea' || $field['type'] === 'wysiwyg') &&
                                isset($field['key'])
                            ){
                                $secondary_lookup_fields[$field['key']] = true;
                            }elseif($field['type'] === 'text' && // if the field is a text AND
                                    !Wpil_Settings::get_ignore_acf_text_fields() && // we're not ignoring text fields AND
                                (
                                    isset($field['name']) && false !== strpos(strtolower($field['name']), 'url') // the text field contains "url" 
                                )
                            ){
                                // We're being extra cautious of text fields since they tend to be used for utility and title purposes.
                                // If there gets to be a lot of cases where we're missing oportunities because the search is limited, we'll see about widening the scope.
                                $secondary_lookup_fields[$field['key']] = true;
                            }elseif(isset($field['type']) && $field['type'] === 'flexible_content' && isset($field['layouts']) && !empty($field['layouts'])){
                                foreach($field['layouts'] as $layout){
                                    if(isset($layout['sub_fields']) && !empty($layout['sub_fields'])){
                                        $secondary_lookup_fields = array_merge($secondary_lookup_fields, self::getRecursiveACFSubFields($layout));
                                    }
                                }
                            }
                        }
                    }
                }
            }

            if(!empty($search_fields)){
                $search_fields = array_keys($search_fields);
                $search_fields = '`meta_key` LIKE \'' . implode('_%\' OR `meta_key` LIKE \'', $search_fields) . '_%\'';

                $fields_query = $wpdb->get_results("SELECT meta_key as 'name' FROM {$wpdb->postmeta} WHERE `post_id` = $post_id AND ({$search_fields})  AND `meta_value` != ''");

                if(!empty($fields_query)){
                    foreach ($fields_query as $field) {
                        $name = trim($field->name);
                        if(!empty($name)){
                            $fields[] = $name;
                        }
                    }
                }
            }

            if(!empty($secondary_lookup_fields)){
                $secondary_lookup_fields = array_keys($secondary_lookup_fields);
                $search_fields = " AND `meta_value` IN ('" . implode("', '", $secondary_lookup_fields) . "')";
                $fields_query = $wpdb->get_col("SELECT meta_key FROM {$wpdb->postmeta} WHERE `post_id` = $post_id {$search_fields}");

                if(!empty($fields_query)){
                    foreach($fields_query as $field){
                        if(0 === strpos($field, '_')){
                            $name = trim(substr($field, 1));
                            if(!empty($name)){
                                $fields[] = $name;
                            }
                        }
                    }
                }
            }

            // remove any duplicate fields
            $fields = array_flip(array_flip($fields));
        }

        return $fields;
    }

    /**
     * Recursively goes through the potential multitude of ACF subfields and pulls out all of the
     * textarea & WYSIWYG fields so we can search the database for them
     **/
    public static function getRecursiveACFSubFields($fields){
        $found_fields = array();
        if(isset($fields['sub_fields']) && !empty($fields['sub_fields'])){
            foreach($fields['sub_fields'] as $sub){
                // only get the fields that can reasonably be assumed to be linkable
                if( isset($sub['type']) &&
                    ($sub['type'] === 'textarea' || $sub['type'] === 'wysiwyg') &&
                    isset($sub['key'])
                ){
                    $found_fields[$sub['key']] = true;
                }elseif($sub['type'] === 'text' && // if the subfield is a text AND
                        !Wpil_Settings::get_ignore_acf_text_fields() && // we're not ignoring text fields AND
                    (
                        isset($sub['name']) && false !== strpos(strtolower($sub['name']), 'url') // the text field contains "url" 
                    )
                ){
                    $found_fields[$sub['key']] = true;
                }elseif(isset($sub['sub_fields']) && !empty($sub['sub_fields'])){
                    $found_fields = array_merge($found_fields, self::getRecursiveACFSubFields($sub));
                }
            }
        }

        return $found_fields;
    }


    /**
     * Gets an array of all custom fields on the site.
     * @return array
     **/
    public static function getAllCustomFields()
    {
        global $wpdb;

        if(!class_exists('ACF') || get_option('wpil_disable_acf', false)){
            return array();
        }

        if (self::$advanced_custom_fields_list === null) {
            $ignored_fields = Wpil_Settings::getIgnoredACFFields();
            $only_search_fields = Wpil_Query::querySpecifiedAcfFields('pm');
            $content_types = array('wysiwyg', 'textarea');

            if(!Wpil_Settings::get_ignore_acf_text_fields()){
                $content_types[] = 'text';
            }

            $content_types = implode('|', $content_types);

            $fields = array();

            // try getting the main set of ACF fields
            //$post_names = $wpdb->get_col("SELECT DISTINCT pm.meta_key as `name` FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON pm.meta_value = p.post_name WHERE p.post_type = 'acf-field' AND p.post_name LIKE 'field_%'");
            $post_data = $wpdb->get_results("SELECT DISTINCT pm.meta_key as `name`, p.post_content as `content` FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON pm.meta_value = p.post_name WHERE p.post_type = 'acf-field' {$only_search_fields}");

            // if we found some
            if (!empty($post_data)) {
                // clean up their names and add them to the field list
                foreach ($post_data as $dat) {
                    if(!preg_match('/' . $content_types . '/', $dat->content)){
                        continue;
                    }

                    $name = trim(substr($dat->name, 1));
                    if (!empty($name)) {
                        $fields[] = $name;
                    }
                }
            }

            // if there are any fields created with PHP/JSON
            $local_field_groups = (function_exists('acf_get_local_store')) ? acf_get_local_store('groups') : false;
            if(!empty($local_field_groups) && isset($local_field_groups->data)){
                $search_fields = array();
                $secondary_lookup_fields = array();
                foreach($local_field_groups->data as $group){
                    // go to some pains to ignore options pages
                    if( isset($group['location']) &&
                        isset($group['location'][0]) &&
                        isset($group['location'][0][0]) &&
                        isset($group['location'][0][0]['param']) &&
                        $group['location'][0][0]['param'] == 'options_page' &&
                        $group['location'][0][0]['operator'] == '==')
                    {
                        continue;
                    }

                    if(isset($group['name'])){
                        $search_fields[] = $group['name'];
                    }elseif(isset($group['key']) && function_exists('acf_get_fields')){
                        $secondary_fields = acf_get_fields($group['key']);
                        if(!empty($secondary_fields)){
                            foreach($secondary_fields as $field){
                                if( isset($field['type']) && 
                                    ($field['type'] === 'textarea' || $field['type'] === 'wysiwyg') &&
                                    isset($field['key'])
                                ){
                                    $secondary_lookup_fields[$field['key']] = true;
                                }elseif(isset($field['type']) && $field['type'] === 'flexible_content' && isset($field['layouts']) && !empty($field['layouts'])){
                                    foreach($field['layouts'] as $layout){
                                        if(isset($layout['sub_fields']) && !empty($layout['sub_fields'])){
                                            $secondary_lookup_fields = array_merge($secondary_lookup_fields, self::getRecursiveACFSubFields($layout));
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                if(!empty($search_fields)){
                    $search_fields = '`meta_key` LIKE \'' . implode('_%\' OR `meta_key` LIKE \'', $search_fields) . '_%\'';

                    $fields_query = $wpdb->get_results("SELECT DISTINCT meta_key as `name` FROM {$wpdb->postmeta} WHERE ({$search_fields})");

                    if(!empty($fields_query)){
                        foreach ($fields_query as $field) {
                            $name = trim($field->name);
                            if ($name) {
                                $fields[] = $field->name;
                            }
                        }
                    }
                }

                if(!empty($secondary_lookup_fields)){
                    $secondary_lookup_fields = array_keys($secondary_lookup_fields);
                    $secondary_fields = "`meta_value` IN ('" . implode("', '", $secondary_lookup_fields) . "')";
                    $fields_query = $wpdb->get_col("SELECT DISTINCT meta_key FROM {$wpdb->postmeta} WHERE {$secondary_fields}");
                    if(!empty($fields_query)){

                        foreach($fields_query as $field){
                            if(0 === strpos($field, '_')){
                                $name = trim(substr($field, 1));
                                $fields[] = $name;
                            }
                        }
                    }
                }

                // if we've found some fields
                if(!empty($fields)){
                    // remove any duplicate fields
                    $fields = array_flip(array_flip($fields));

                    // get a list of ACF field rules to use for regular expressions
                    $ignored_fields_wildcards = [];

                    // remove any ignored fields that are defined
                    if(!empty($ignored_fields)){
                        foreach($ignored_fields as $key => $rule) {
                            if ( strpos($rule, '*') !== false ) {
                                $ignored_fields_wildcards[] = str_replace('*', '.*', $rule);
                                unset($ignored_fields[$key]);
                            }
                        }
                        
                        if ( !empty($ignored_fields_wildcards) ) {
                            $ignored_fields_wildcards = implode('|', $ignored_fields_wildcards);
                        }

                        foreach($fields as $ind => $field){
                            if(!empty($ignored_fields) && in_array($field, $ignored_fields, true)){
                                unset($fields[$ind]);
                            }

                            if ( !empty($ignored_fields_wildcards) && preg_match('/' . $ignored_fields_wildcards . '/', $name) ) {
                                unset($fields[$ind]);
                            }
                        }
                    }

                    // re-key the array in case something sensitive is listening
                    $fields = array_values($fields);
                }

            self::$advanced_custom_fields_list = $fields;

            }
        }

        return self::$advanced_custom_fields_list;
    }

    /**
     * Gets a list of the possible meta content fields to add links to
     * @param string $type Is the content for a post or a term?
     * @return array $fields An array of the possible fields for the item
     **/
    public static function getMetaContentFieldList($type = 'post'){
        $fields = [];

        if(defined('RH_MAIN_THEME_VERSION') && $type === 'term'){
            $fields[] = 'brand_second_description';
        }

        return $fields;
    }

    /**
     * Get all posts with the same language
     *
     * @param $post_id
     * @return array
     */
    public static function getSameLanguagePosts($post_id)
    {
        global $wpdb;
        $ids = [];
        $posts = [];

        // if WPML is active and there's languages saved
        if(Wpil_Settings::wpml_enabled()) {
            $table = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}icl_languages'");
            if($table == $wpdb->prefix . 'icl_languages'){
                $post_types = self::getSelectedLanguagePostTypes();
                $language = $wpdb->get_var("SELECT language_code FROM {$wpdb->prefix}icl_translations WHERE element_id = $post_id AND `element_type` IN ({$post_types}) ");
                if (!empty($language)) {
                    $posts = $wpdb->get_results("SELECT element_id as id FROM {$wpdb->prefix}icl_translations WHERE element_id != $post_id AND language_code = '$language' AND `element_type` IN ({$post_types}) ");
                }
            }
        }

        // if Polylang is active
        if(Wpil_Settings::polylang_enabled()){
            $taxonomy_id = $wpdb->get_var("SELECT t.term_taxonomy_id FROM {$wpdb->term_taxonomy} t INNER JOIN {$wpdb->term_relationships} r ON t.term_taxonomy_id = r.term_taxonomy_id WHERE t.taxonomy = 'language' AND r.object_id = " . $post_id);
            if (!empty($taxonomy_id)) {
                $posts = $wpdb->get_results("SELECT object_id as id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id = $taxonomy_id AND object_id != $post_id");
            }
        }

        if (!empty($posts)) {
            foreach ($posts as $post) {
                $ids[] = $post->id;
            }
        }

        return $ids;
    }

    /**
     * Get all posts from languages other than the current post's
     *
     * @param $post_id
     * @return array
     */
    public static function getNonSameLanguagePosts($post_id)
    {
        global $wpdb;
        $ids = [];
        $posts = [];

        // if WPML is active and there's languages saved
        if(Wpil_Settings::wpml_enabled()) {
            $table = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}icl_languages'");
            if($table == $wpdb->prefix . 'icl_languages'){
                $post_types = self::getSelectedLanguagePostTypes();
                $language = $wpdb->get_var("SELECT language_code FROM {$wpdb->prefix}icl_translations WHERE element_id = $post_id AND `element_type` IN ({$post_types}) ");
                if (!empty($language)) {
                    $other_languages = $wpdb->get_col("SELECT code FROM {$wpdb->prefix}icl_languages WHERE code != '{$language}' AND active = 1");
                    if(!empty($other_languages)){
                        
                        $other_languages = "('" . implode("', '", $other_languages) . "')";
                        $posts = $wpdb->get_results("SELECT element_id as id FROM {$wpdb->prefix}icl_translations WHERE element_id != $post_id AND language_code IN {$other_languages} AND `element_type` IN ({$post_types}) ");
                    }
                }
            }
        }

        // if Polylang is active
        if(Wpil_Settings::polylang_enabled()){
            $taxonomy_id = $wpdb->get_var("SELECT t.term_taxonomy_id FROM {$wpdb->term_taxonomy} t INNER JOIN {$wpdb->term_relationships} r ON t.term_taxonomy_id = r.term_taxonomy_id WHERE t.taxonomy = 'language' AND r.object_id = " . $post_id);
            if (!empty($taxonomy_id)) {
                $other_languages = $wpdb->get_col("SELECT DISTINCT t.term_taxonomy_id FROM {$wpdb->term_taxonomy} t INNER JOIN {$wpdb->term_relationships} r ON t.term_taxonomy_id = r.term_taxonomy_id WHERE t.taxonomy = 'language' AND t.term_taxonomy_id != $taxonomy_id");
                if(!empty($other_languages)){
                    $other_languages = implode(',', $other_languages);
                    $posts = $wpdb->get_results("SELECT object_id as id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN ({$other_languages}) AND object_id != $post_id");
                }
            }
        }

        if (!empty($posts)) {
            foreach ($posts as $post) {
                $ids[] = $post->id;
            }
        }

        return $ids;
    }

    /**
     * Gets the selected post types formatted for WPML
     **/
    public static function getSelectedLanguagePostTypes(){
        $post_types = implode("', 'post_", Wpil_Suggestion::getSuggestionPostTypes());

        if(!empty($post_types)){
            $post_types = "'post_" . $post_types . "'";
        }

        return $post_types;
    }

    /**
     * Get all terms in the same language
     *
     * @param $term_id
     * @return array
     */
    public static function getSameLanguageTerms($term_id)
    {
        global $wpdb;
        $ids = [];

        // if WPML is active and there's languages saved
        if(defined('WPML_PLUGIN_BASENAME')) {
            $table = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}icl_languages'");
            if($table == $wpdb->prefix . 'icl_languages'){
                $term_types = self::getSelectedLanguageTermTypes();
                $language = $wpdb->get_var("SELECT language_code FROM {$wpdb->prefix}icl_translations WHERE element_id = $term_id AND `element_type` IN ({$term_types}) ");
                if (!empty($language)) {
                    $ids = $wpdb->get_col("SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE element_id != $term_id AND language_code = '$language' AND `element_type` IN ({$term_types}) ");
                }
            }
        }

        // if Polylang is active
        if(defined('POLYLANG_VERSION')){
            // get the terms that have been translated... Eventually
            $taxonomy_description = $wpdb->get_var("SELECT `description` FROM {$wpdb->term_taxonomy} t INNER JOIN {$wpdb->term_relationships} r ON t.term_taxonomy_id = r.term_taxonomy_id WHERE t.taxonomy = 'term_translations' AND r.object_id = " . $term_id);
            if (!empty($taxonomy_description)) {
                $description_data = maybe_unserialize($taxonomy_description);
                $lang_code = array_search($term_id, $description_data);
                if(!empty($lang_code)){
                    $data = $wpdb->get_results("SELECT * FROM {$wpdb->term_taxonomy} WHERE `taxonomy` = 'term_translations' AND  `description` LIKE '%\"{$lang_code}\"%' AND term_id != $term_id");
                    if(!empty($data)){
                        foreach($data as $term){
                            $dat = maybe_unserialize($term->description);
                            if(!empty($dat) && isset($dat[$lang_code])){
                                $ids[] = $dat[$lang_code];
                            }
                        }
                    }
                }
            }
        }

        if (!empty($ids)) {
            $ids[] = array_flip(array_flip($ids));
        }

        return $ids;
    }

    /**
     * Gets the selected post types formatted for WPML
     **/
    public static function getSelectedLanguageTermTypes(){
        $term_types = implode("', 'tax_", Wpil_Settings::getTermTypes());

        if(!empty($term_types)){
            $term_types = "'tax_" . $term_types . "'";
        }

        return $term_types;
    }

    public static function getAnchors($post)
    {
        preg_match_all('|<a [^>]+>([^<]+)</a>|i', $post->getContent(), $matches);

        if (!empty($matches[1])) {
            return $matches[1];
        }

        return [];
    }

    /**
     * Get URLs from post content
     *
     * @param $post
     * @return array|mixed
     */
    public static function getUrls($post)
    {
        preg_match_all('#<a\s.*?(?:href=[\'"](.*?)[\'"]).*?>#is', $post->getContent(), $matches);

        if (!empty($matches[1])) {
            return $matches[1];
        }

        return [];
    }

    public static function getSentencesWithUrls($post)
    {
        $data = [];
        $content = $post->getContent();

        // replace any base64ed image urls
        $content = preg_replace('`src="data:(?:image|text)\/(?:png|jpeg|svg\+xml|xml);base64,[\s]??[a-zA-Z0-9\/+=]+?"`', '', $content);
        $content = preg_replace('`alt="Source: data:image\/(?:png|jpeg|svg\+xml);base64,[\s]??[a-zA-Z0-9\/+=]+?"`', '', $content);

        preg_match_all('`(\!|\?|\.|^|)[^.!?\n]*<a\s[^>]*?(?:href=([\'"]|\\\")(.*?)([\'"]|\\\"))[^>]*?>(.*?)<\/a>((?!<a)[^.!?\n])*|<!-- wp:(?:core-embed\/wordpress|embed) {[\\\]*?"url[\\\]*?":[\\\]*?"([^"\\\]*?)[\\\]*?"[^}]*?[\\\]*?"} -->`is', $content, $matches);
        for ($i = 0; $i < count($matches[0]); $i++) {
            if (!empty($matches[0][$i]) && !empty($matches[3][$i])) {
                $sentence = $matches[0][$i];
                if (in_array(substr($sentence, 0, 1), ['.', '!', '?'])) {
                    $sentence = substr($sentence, 1);
                }

                $url = $matches[3][$i];

                // if the url is inside slashed quotes
                if( !empty($matches[2][$i]) && $matches[2][$i] === '\"' &&
                    !empty($matches[4][$i]) && $matches[4][$i] === '\"')
                {
                    // add the quotes to the url
                    $url = ($matches[2][$i] . $url . $matches[4][$i]);
                }

                // if there is an anchor
                if(!empty($matches[5][$i]) && $matches[5][$i]){
                    $anchor = $matches[5][$i];
                }else{
                    $anchor = '';
                }

                $data[] = [
                    'sentence' => trim(strip_tags($sentence)),
                    'anchor' => trim(strip_tags($anchor)),
                    'url' => $url
                ];
            }elseif(!empty($matches[7][$i])){
                $url = esc_attr($matches[7][$i]);
    
                $data[] = [
                    'sentence' => esc_attr__('Link is embedded, no sentence text detected', 'wpil'),
                    'anchor' => 'N/A',
                    'url' => $url
                ];
            }
        }

        // get the image tags too
        preg_match_all('#<img\s[^>]*?(?:(?:href|src)=([\'"]|\\\")(.*?)([\'"]|\\\"))[^>]*?>#is', $content, $matches);
        if(!empty($matches)){
            for ($i = 0; $i < count($matches[0]); $i++) {
                if (!empty($matches[0][$i]) && !empty($matches[1][$i])) {
                    $text = $matches[0][$i];

                    if(false !== strpos($text, 'title="') && false === strpos($text, 'title=""')){
                        $offset = (mb_strpos($text, 'title="') + 7);
                        $sentence = __('Broken Image. The title is: ', 'wpil') . '"' . mb_substr($text, $offset, (mb_strpos($text, '"', $offset) - $offset) ) . '"';
                    }elseif(false !== strpos($text, 'alt="') && false === strpos($text, 'alt=""')){
                        $offset = (mb_strpos($text, 'alt="') + 5);
                        $sentence = __('Broken Image. The alt text is: ', 'wpil') . '"' . mb_substr($text, $offset, (mb_strpos($text, '"', $offset) - $offset) ) . '"';
                    }else{
                        $sentence = __('Broken Image. The image doesn\'t have a title or alt text.', 'wpil');
                    }

                    $url = $matches[2][$i];

                    // if the url is inside slashed quotes
                    if( !empty($matches[1][$i]) && $matches[1][$i] === '\"' &&
                        !empty($matches[3][$i]) && $matches[3][$i] === '\"')
                    {
                        // add the quotes to the url
                        $url = ($matches[1][$i] . $url . $matches[3][$i]);
                    }

                    $data[] = [
                        'sentence' => trim(strip_tags($sentence)),
                        'anchor' => '',
                        'url' => $url
                    ];
                }
            }
        }

        // check to make sure that there aren't any empty anchors present
        if(strpos($content, '<a>') !== false){
            // if there are, pull those links too
            preg_match_all('`(\!|\?|\.|^|)[^.!?\n]*<a>(.*?)<\/a>((?!<a)[^.!?\n])*`is', $content, $matches);
            for ($i = 0; $i < count($matches[0]); $i++) {
                if (!empty($matches[0][$i])) {
                    $sentence = $matches[0][$i];
                    if (in_array(substr($sentence, 0, 1), ['.', '!', '?'])) {
                        $sentence = substr($sentence, 1);
                    }
    
                    $anchor = !empty($matches[2][$i]) ? $matches[2][$i]: '';

                    $data[] = [
                        'sentence' => trim(strip_tags($sentence)),
                        'url' => '{{wpil-empty-url}}',
                        'anchor' => $anchor
                    ];
                }
            }
        }

        return $data;
    }

    /**
     * Change sentence if it located inside embedded ACF blocks.
     * Changes the double qoutes in the link to insert's attributes into single quotes so we don't break the ACF blocks
     *
     * @param $content
     * @param $sentence
     * @param $changed_sentence
     * @return string
     */
    public static function changeByACF($content, $sentence, $changed_sentence){
        //find all blocks
        $blocks = [];
        $end = 0;
        while($end <= strlen($content) && strpos($content, '<!-- wp:acf', $end) !== false) {
            $begin = strpos($content, '<!-- wp:acf', $end);
            $end = strpos($content, '-->', $begin);
            $blocks[] = [$begin, $end];
        }

        //change sentence
        if (!empty($blocks)) {
            $pos = strpos($content, $sentence);
            foreach ($blocks as $block) {
                if ($block[0] < $pos && $block[1] > $pos) {
                    $changed_sentence = str_replace('"', "'", $changed_sentence);
                }
            }
        }

        return $changed_sentence;
    }

    /**
     * Get post ID from any URL
     *
     * @param string $url
     * @return int|false
     */
    public static function get_post_id_from_any_url($url) {
        $url = Wpil_Settings::makeLinkAbsolute($url);

        $url_parts = parse_url($url);
        $path = trim($url_parts['path'], '/');
        $path_parts = explode('/', $path);
        $slug = end($path_parts);

        // Get all public post types
        $post_types = get_post_types(array('public' => true));

        // First try exact path match
        $args = array(
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false
        );

        // Try matching the full path first
        $args['name'] = $path;
        $query = new WP_Query($args);

        // If no match, try with just the slug
        if (!$query->have_posts() && !empty($slug)) {
            $args['name'] = $slug;
            $query = new WP_Query($args);
        }

        if ($query->have_posts()) {
            foreach ($query->posts as $post_id) {
                $post = get_post($post_id);

                // Verify the slug matches
                if ($post->post_name !== $slug) {
                    continue;
                }

                $post_url = parse_url(get_permalink($post_id));
                if (trim($post_url['path'], '/') !== $path) {
                    continue;
                }

                return $post_id;
            }
        }

        return false;
    }

    /**
     * Get post model by view link.
     * URLtoPost
     * IDFROMLINK
     * IDFROMURL
     * 
     * @param $link
     * @return Wpil_Model_Post|null
     */
    public static function getPostByLink($link)
    {
        global $wpdb;
        $post = null;
        $link = trim($link);
    //    $link = Wpil_Link::get_url_redirection($link) ?: $link; //todo: make work with
        $starting_link = $link;

        // check to see if we've already come across this link
        $cached = self::get_cached_url_post($link);
        // if we have
        if(!empty($cached)){
            //return the cached version
            return $cached;
        }

        // check to make sure that we are reasonably sure we can trace the link
        if(!Wpil_Link::is_traceable($link)){
            // if we're not, return null
            return $post;
        }

        // check to see if the link isn't a pretty link
        if(preg_match('#[?&](p|page_id|attachment_id)=(\d+)#', $link, $values)){
            // if it's not, get the id
            $id = absint($values[2]);
            // if there is an id
            if($id){
                // get the post so we can make sure it exists
                $wp_post = get_post($id);
                // if it does exist, set the id. Else, set it to null
                $post_id = (!empty($wp_post)) ? $wp_post->ID: null;
            }
        }else{
            // make sure the link isn't double slashed anywhere that it's not supposed to be
            if(!empty(preg_match('/(?<!http:|https:)\/\//', $link, $m)) || !empty($m)){
                $link = preg_replace('/(?<!http:|https:)(?:\/\/\/|\/\/)/', '/', $link);
                $link = preg_replace('/(?<!http:|https:)(?:\/\/\/|\/\/)/', '/', $link);
            }

            $link = explode('$', $link); // TODO: Review and confirm that no users report that links aren't being traced || the number of Inbound Internal links is lower after update 2.5.7
            $link = $link[0];

            // clean up any translations if it's a relative link
            $link = Wpil_Link::clean_translated_relative_links($link);
            $post_id = url_to_postid($link);
        }

        if (!empty($post_id)) {
            $post = new Wpil_Model_Post($post_id);
        }

        // if we couldn't find the post and custom permalinks is active
        if(empty($post) && defined('CUSTOM_PERMALINKS_FILE')){
            // consult it's database listings to see if we can find the post the link belongs to
            $search_url = $link;

            // get the home url and clean it up
            $site_url = get_home_url();
            $site_url = preg_replace('/http:\/\/|https:\/\/|www\./', '', $site_url);
            // make sure the supplied link is similarly clean
            $search_url = preg_replace('/http:\/\/|https:\/\/|www\./', '', $search_url);

            // and replace the home portion of the link to make it relative
            $search_url = trim(str_replace($site_url, '', $search_url), '/'); // Don't add slashes around the url
            
            // get the stati and types to search
            $status = Wpil_Query::postStatuses('p');
            $type = Wpil_Query::postTypes('p');

            // now search the db
            $search = $wpdb->get_col(
                $wpdb->prepare(
                    'SELECT p.ID ' .
                    " FROM $wpdb->posts AS p INNER JOIN $wpdb->postmeta AS pm ON (pm.post_id = p.ID) " .
                    " WHERE pm.meta_key = 'custom_permalink' " .
                    ' AND (pm.meta_value = %s OR pm.meta_value = %s) ' .
                    " {$status} {$type} " .
                    " LIMIT 1",
                    $search_url,
                    $search_url . '/'
                )
            );
            // if we found a post
            if(!empty($search)){
                // that is our new post object
                $post = new Wpil_Model_Post($search[0]);
            }
        }

        // if all that didn't work, the post might be draft or Polylang Pro might be active and we'll have to check for multiple posts with the same name
        // so we'll try pulling the post name from the URL and seeing if that will get us an id
        if((empty($post) || Wpil_Settings::polylang_enabled()) && is_string($link) && !empty($link) && Wpil_Link::isInternal($link)){
            // get the permalink structure
            $link_structure = get_option('permalink_structure', '');
            if(!empty($link_structure)){
                // see if the post name is in it
                if(false !== strpos($link_structure, '%postname%')){
                    // if it is, blow up the link structure
                    $exploded_structure = explode('/', '/' . trim($link_structure, '/') . '/'); // frame the permalink with "/" so that we're consistently comparing it to the link
                    // make the supplied link relative, and blow it up too
                    if(!Wpil_Toolbox::isRelativeLink($link)){
                        // get the home url and clean it up
                        $site_url = get_home_url();
                        $site_url = preg_replace('/http:\/\/|https:\/\/|www\./', '', $site_url);
                        // make sure the supplied link is similarly clean
                        $link = preg_replace('/http:\/\/|https:\/\/|www\./', '', $link);

                        // and replace the home portion of the link to make it relative
                        $link = '/'. trim(str_replace($site_url, '', $link), '/') . '/'; // we're going to assume that the user isn't using a draft post as the home url... That would give us just "/" at this point, and "///" isn't a valid url
                    }

                    // if polylang is active
                    if(Wpil_Settings::translation_enabled() && Wpil_Settings::polylang_enabled()){
                        global $polylang;

                        if(!empty($polylang)){
                            // get the link's language
                            $lang = $polylang->links_model->get_language_from_url($link);

                            // if we got the language, try getting it's term
                            if(!empty($lang)){
                                $language_term = get_term_by('slug', $lang, 'language');
                            }

                            // and remove any translation effect from the url
                            $link = $polylang->links_model->remove_language_from_link($link);
                        }
                    }

                    // now blow up the link
                    $exploded_link = explode('/', $link);

                    // check to see if we're looking at a child page link
                    $offset = 0;
                    if(count($exploded_link) > count($exploded_structure)){
                        // if we are, account for the parent slugs
                        $offset = (count($exploded_link) - count($exploded_structure));
                    }

                    // and see if the link has a postname in the same position as the permalink structure
                    $name = '';
                    foreach($exploded_structure as $key => $piece){
                        $ind = $key + $offset;
                        if( $piece === '%postname%' &&          // if we're focussed on the postname
                            isset($exploded_link[$ind]) &&      // and there's a corresponding piece in the link
                            !empty($exploded_link[$ind]) &&     // and there's something in the corresponding piece
                            is_string($exploded_link[$ind]) &&  // and the corresponding is a string
                            strlen($exploded_link[$ind]) > 0)   // and it's at least 1 char long
                        {
                            // extract the piece as the post name and exit the loop
                            $name = $exploded_link[$ind];
                            break;
                        }
                    }

                    // if we've found something
                    if(!empty($name)){
                        $post_types = Wpil_Query::postTypes();

                        if(Wpil_Settings::translation_enabled() && !empty($language_term)){
                            $query = $wpdb->prepare("SELECT a.ID FROM {$wpdb->posts} a LEFT JOIN {$wpdb->term_relationships} b ON a.ID = b.object_id WHERE a.post_name = %s && b.term_taxonomy_id = %d {$post_types} LIMIT 1", $name, $language_term->term_id);
                        }else{
                            $query = $wpdb->prepare("SELECT `ID` FROM {$wpdb->posts} WHERE `post_name` = %s {$post_types} LIMIT 1", $name);
                        }

                        // see if there's a post in the database with the same name from among the post types that the user has selected
                        $dat = $wpdb->get_col($query);

                        // if there isn't one, check across all the post types
                        if(empty($dat)){
                            $dat = $wpdb->get_col($wpdb->prepare("SELECT `ID` FROM {$wpdb->posts} WHERE `post_name` = %s AND `post_type` != 'revision' LIMIT 1", $name));
                        }

                        // if that didn't work either, try looking for the title
                        if(empty($dat)){ // TODO: set up some kind of a post title lookup table. The post_title column isn't indexed, and searching it for many results can take forever
                            // replace any hyphens with spaces
                            $name = str_replace('-', ' ', $name);
                            // and search through our post types
                            $dat = $wpdb->get_col($wpdb->prepare("SELECT `ID` FROM {$wpdb->posts} WHERE `post_title` = %s {$post_types} LIMIT 1", $name)); // for exceedingly long titles, I might consider re-adding the LIKE check. But we'll cross that bridge when we get there

                            // if that still didn't work, check the title across all the post types
                            if(empty($dat)){
                                $dat = $wpdb->get_col($wpdb->prepare("SELECT `ID` FROM {$wpdb->posts} WHERE `post_title` = %s AND `post_type` != 'revision' LIMIT 1", $name));
                            }
                        }

                        // if we've found a post id
                        if(!empty($dat) && isset($dat[0]) && !empty($dat[0])){
                            // create the post object we've been striving for
                            $post = new Wpil_Model_Post($dat[0]);
                        }
                    }
                }
            }
        }

        if(empty($post)){
            $post_id = self::get_post_id_from_any_url($starting_link);

            // if we've found the post that the link belongs to
            if(!empty($post_id)){
                // setup the post object with it
                $post = new Wpil_Model_Post($post_id);
            }
        }

        // if we _still_ haven't found a post
        if (empty($post)) {
            // see if the URL is actually for a term instead of a post
            $slug = array_filter(explode('/', $starting_link));
            $term = Wpil_Term::getTermBySlug(end($slug), $starting_link);
            if(!empty($term)){
                $post = new Wpil_Model_Post($term->term_id, 'term');
            }
        }

        // if we've gone this far and haven't 

        // cache the results of our efforts in case we come across this link again
        self::update_cached_url_post($starting_link, $post);

        return $post;
    }

    /**
     * Checks to see if the url was previously processed into a post object.
     * If it is in the cache, it returns the cached post so we don't have to run through the process again.
     * Returns false if the url hasn't been processed yet, or it doesn't go to a known post
     **/
    public static function get_cached_url_post($url = ''){
        if(empty($url) || !is_string($url)){
            return false;
        }

        // clean up the url a little so we have consistency between slightly different links
        // clean up any translations if it's a relative link
        $url = Wpil_Link::clean_translated_relative_links($url);
        // remove www & protocol bits
        $url = str_replace(['http', 'https'], '', str_replace('www.', '', $url));

        if(empty($url) || !isset(self::$post_url_cache[$url])){
            return false;
        }

        return self::$post_url_cache[$url];
    }

    /**
     * Updates the url cache when we come across a url + post that we haven't stored yet.
     * Also does some housekeeping to make sure the cache doesn't grow too big
     **/
    public static function update_cached_url_post($url, $post){
        if(empty($url) || empty($post) || isset(self::$post_url_cache[$url]) || !is_string($url)){
            return false;
        }

        // clean up the url a little so we have consistency between slightly different links
        // clean up any translations if it's a relative link
        $url = Wpil_Link::clean_translated_relative_links($url);
        // remove www & protocol bits
        $url = str_replace(['http', 'https'], '', str_replace('www.', '', $url));

        if(empty($url)){
            return false;
        }

        self::$post_url_cache[$url] = $post;

        if(count(self::$post_url_cache) > 5000){
            $ind = key(self::$post_url_cache);
            unset(self::$post_url_cache[$ind]);
        }
    }

    /**
     * Get post IDs from certain category
     *
     * @param $category_id
     * @return array
     */
    public static function getCategoryPosts($category_id)
    {
        global $wpdb;

        $posts = [];
        $categories = $wpdb->get_results("SELECT r.object_id as `id` FROM {$wpdb->term_relationships} r INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = r.term_taxonomy_id WHERE tt.term_id = " . $category_id);
        foreach ($categories as $post) {
            $posts[] = $post->id;
        }

        return $posts;
    }

    /**
     * Run function for all editors
     *
     * @param $action
     * @param $params
     */
    public static function editors($action, $params)
    {
        $editors = [
            'Beaver',
            'Elementor',
            'Origin',
            'Oxygen',
            'Thrive',
            'Themify',
            'Muffin',
            'Enfold',
            'Cornerstone',
            'WPRecipe',
            'Goodlayers',
            'Divi'
        ];

        foreach ($editors as $editor) {
            $class = 'Wpil_Editor_' . $editor;
            call_user_func_array([$class, $action], $params);
        }
    }

    /**
     * TODO: Fill out so that we can pull the editors that are actually active and run through them.
     */
    public static function get_active_editors(){
        $editors = array();
        // check for active editors by looking for major constants or classes
        if(defined('FL_BUILDER_VERSION')){
            $editors[] = 'Beaver';
        }
        if(defined('ELEMENTOR_VERSION')){
            $editors[] = 'Elementor';
        }
        if(defined('SITEORIGIN_PANELS_VERSION')){
            $editors[] = 'Origin';
        }
        if(defined('CT_VERSION')){
            $editors[] = 'Oxygen';
        }
        if(defined('TVE_PLUGIN_FILE') || defined('TVE_EDITOR_URL')){
            $editors[] = 'Thrive';
        }
        if(class_exists('ThemifyBuilder_Data_Manager')){
            $editors[] = 'Themify';
        }
        if(defined('MFN_THEME_VERSION')){
            $editors[] = 'Muffin';
        }
        if(defined('AV_FRAMEWORK_VERSION')){
            $editors[] = 'Enfold';
        }
        if(class_exists('Cornerstone_Plugin')){
            $editors[] = 'Cornerstone';
        }
        if(defined('WPRM_POST_TYPE') && in_array('wprm_recipe', Wpil_Settings::getPostTypes())){
            $editors[] = 'WPRecipe';
        }
        if(defined('GDLR_CORE_LOCAL')){
            $editors[] = 'Goodlayers';
        }
        
        return $editors;
    }

    /**
     * Gets the meta keys for content areas created with page builders so we can search the database for content.
     * @return array
     **/
    public static function get_builder_meta_keys(){
        $builder_meta = array();
        // if Goodlayers is active
        if(defined('GDLR_CORE_LOCAL')){
            $builder_meta[] = 'gdlr-core-page-builder';
        }
        // if Themify builder is active
        if(class_exists('ThemifyBuilder_Data_Manager')){
            $builder_meta[] = '_themify_builder_settings_json';
        }
        // if Oxygen is active
        if(defined('CT_VERSION')){
            $builder_meta[] = 'ct_builder_shortcodes';
        }
        // if Muffin is active
        if(defined('MFN_THEME_VERSION')){
            $builder_meta[] = 'mfn-page-items-seo';
        }
        // if "Thrive" is active
        if(defined('TVE_PLUGIN_FILE') || defined('TVE_EDITOR_URL')){
            $builder_meta[] = 'tve_updated_post';
        }
        // if Elementor is active
        if(defined('ELEMENTOR_VERSION')){
            $builder_meta[] = '_elementor_data';
        }

        return $builder_meta;
    }

    /**
     * Function to fetch the primary term for the main hierarchical taxonomy of a post
     * @param $post_id
     * @param $post_type
     * @return mixed|null
     */
    public static function get_primary_term_for_main_taxonomy($post_id, $post_type) {
        // First, get the main hierarchical taxonomy
        $taxonomy = self::get_main_hierarchical_taxonomy($post_type, $post_id);

        // Check if Yoast SEO's Primary Term functionality exists
        if (class_exists('WPSEO_Primary_Term')) {
            $primary_term = new WPSEO_Primary_Term($taxonomy, $post_id);
            $primary_term_id = $primary_term->get_primary_term();
            if ($primary_term_id && !is_wp_error($primary_term_id)) {
                $term = get_term($primary_term_id, $taxonomy);
                if ($term && !is_wp_error($term)) {
                    return $term;
                }
            }
        }

        // Use the first term if no primary term is set
        $terms = wp_get_post_terms($post_id, $taxonomy);

        return !empty($terms) ? $terms[0] : null;
    }

    /**
     * Function to get the main hierarchical taxonomy for a post type
     * @param $post_type
     * @param $post_id
     * @return string
     */
    public static function get_main_hierarchical_taxonomy($post_type, $post_id) {
        // check for 'post' post type
        if ($post_type === 'post') {
            $categories = get_the_terms($post_id, 'category');
            if ($categories && !is_wp_error($categories)) {
                // Check if any term is not 'uncategorized'
                foreach ($categories as $category) {
                    if ($category->slug !== 'uncategorized') {
                        return 'category';
                    }
                }
            }
            // If only 'uncategorized' exists, continue to other taxonomies
        }

        // Check all taxonomies associated with the post type
        $taxonomies = get_object_taxonomies($post_type, 'objects');
        foreach ($taxonomies as $taxonomy) {
            // Check for hierarchical taxonomies
            if ($taxonomy->hierarchical) {
                // check for the 'category' taxonomy
                if ($taxonomy->name === 'category') {
                    $terms = get_the_terms($post_id, $taxonomy->name);
                    if ($terms && !is_wp_error($terms)) {
                        $has_valid_term = false;
                        foreach ($terms as $term) {
                            if ($term->slug !== 'uncategorized') {
                                $has_valid_term = true;
                                break;
                            }
                        }
                        if ($has_valid_term) {
                            return $taxonomy->name;
                        }
                    }
                    // If only 'uncategorized' exists, proceed to the next taxonomy
                    continue;
                }

                // For other hierarchical taxonomies, simply return the first one found
                return $taxonomy->name;
            }
        }

        return 'category';
    }

}
