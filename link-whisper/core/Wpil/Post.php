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
            $ignore_post = self::getPostByLink($post_link); // try getting the post to ensure that there's no issues getting the post from url
            if(!empty($ignore_post) && $post->id === $ignore_post->id){
                $ignored_posts .= "\n" . $post_link;
            }else{
                $ignored_posts .= "\n" . $post->getViewLink(false, true); // if we can't turn the url into a viable post, go with the "Ugly" url instead.
            }
        }

        update_option('wpil_ignore_orphaned_posts', $ignored_posts, false);

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
     * @param int $post_id The id of the post that we're checking...
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

        // Try asking ACF about the field keys saved with the post too.
        // Some sites keep their field groups in PHP or JSON, so the field post lookup can come up short.
        if(function_exists('acf_get_field')){
            $include_text = !Wpil_Settings::get_ignore_acf_text_fields();
            $field_keys = $wpdb->get_results("SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = $post_id AND meta_key LIKE '\_%' AND meta_value LIKE 'field_%' AND SUBSTR(meta_key, 2) != ''");

            if(!empty($field_keys)){
                foreach($field_keys as $field_key){
                    $field_data = acf_get_field($field_key->meta_value);
                    if(empty($field_data) || empty($field_data['type'])){
                        continue;
                    }

                    if($field_data['type'] !== 'wysiwyg' && $field_data['type'] !== 'textarea' && (!$include_text || $field_data['type'] !== 'text')){
                        continue;
                    }

                    $name = trim(substr($field_key->meta_key, 1));
                    if(empty($name) || in_array($name, $ignored_fields, true)){
                        continue;
                    }

                    if(!empty($ignored_fields_wildcards) && preg_match('/' . $ignored_fields_wildcards . '/', $name)){
                        continue;
                    }

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

        // if for some reason we couldn't find any fields
        if(empty($fields)){
            // try pulling and formatting fields from ACF using its own functions to make the field names
            $fields = self::flatten_get_fields_allowed_types($post_id);
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
     * Flatten get_fields() and try to pull the fields we need!
     */
    public static function flatten_get_fields_allowed_types($post_id, $skip_empty = true){
        if(!function_exists('get_fields') || !class_exists('ACF')){
            return [];
        }

        $values = get_fields($post_id);
        if(empty($values) || !is_array($values)){
            return [];
        }

        // LW ignore rules
        $ignored_exact = Wpil_Settings::getIgnoredACFFields();
        $ignored_wildcards = [];
        if(!empty($ignored_exact)){
            foreach($ignored_exact as $i => $rule){
                if(strpos($rule, '*') !== false){
                    $ignored_wildcards[] = str_replace('*', '.*', $rule);
                    unset($ignored_exact[$i]);
                }
            }
        }
        $ignored_regex = !empty($ignored_wildcards) ? '/' . implode('|', $ignored_wildcards) . '/' : '';

        $schema = self::acf_get_allowed_schema_fields($post_id);
        $allowed_leaf = $schema['allowed_leaf'];

        $is_ignored = function($str) use ($ignored_exact, $ignored_regex){
            if(in_array($str, $ignored_exact, true)){
                return true;
            }
            if(!empty($ignored_regex) && preg_match($ignored_regex, $str)){
                return true;
            }
            return false;
        };

        $out = [];

        /**
         * @param mixed       $value   Current value
         * @param string      $prefix  Flattened LW-style key path
         * @param string|null $leafKey The field key at this node
         */
        $walk = function($value, $prefix, $leafKey = null) use (&$walk, &$out, $allowed_leaf, $skip_empty, $is_ignored){
            if($leafKey !== null){
                if($is_ignored($leafKey)){
                    return;
                }
            }

            if(!is_array($value)){
                if($leafKey === null || empty($allowed_leaf[$leafKey])){
                    return;
                }

                if(!is_string($value)){
                    return;
                }

                if($skip_empty && trim($value) === ''){
                    return;
                }

                $out[] = $prefix;
                return;
            }

            // Otherwise recurse through arrays (repeaters, flex, groups, etc.)
            foreach($value as $k => $v){
                $nextPrefix = ($prefix === '') ? (string)$k : ($prefix . '_' . $k);
                $nextLeafKey = is_string($k) ? $k : null;
                $walk($v, $nextPrefix, $nextLeafKey);
            }
        };

        foreach($values as $top_key => $top_value){
            if($is_ignored($top_key)){
                continue;
            }
            $walk($top_value, (string)$top_key, (string)$top_key);
        }

        return array_values(array_unique($out));
    }

    /**
     * Build a set of "allowed leaf field names" based on ACF schema:
     * - textarea, wysiwyg, and (optionally) text
     * - recursively through repeater/group/flexible_content/clone
     *
     * Returns: array{allowed_leaf: array<string,true>, allowed_roots: array<string,true>}
     */
    public static function acf_get_allowed_schema_fields($post_id){
        $allowed_leaf = [];
        $allowed_roots = [];

        if(!function_exists('get_field_objects') || !class_exists('ACF')){
            return ['allowed_leaf' => [], 'allowed_roots' => []];
        }

        $include_text = !Wpil_Settings::get_ignore_acf_text_fields();

        $objs = get_field_objects($post_id);
        if(empty($objs) || !is_array($objs)){
            return ['allowed_leaf' => [], 'allowed_roots' => []];
        }

        $walk_field = function($field) use (&$walk_field, &$allowed_leaf, $include_text){
            if(empty($field) || !is_array($field) || empty($field['name']) || empty($field['type'])){
                return;
            }

            $type = $field['type'];
            $name = $field['name'];

            // leaf types we care about
            if($type === 'textarea' || $type === 'wysiwyg' || ($include_text && $type === 'text')){
                $allowed_leaf[$name] = true;
                return;
            }

            // containers
            if(($type === 'group' || $type === 'repeater') && !empty($field['sub_fields'])){
                foreach($field['sub_fields'] as $sub){
                    $walk_field($sub);
                }
                return;
            }

            if($type === 'flexible_content' && !empty($field['layouts'])){
                foreach($field['layouts'] as $layout){
                    if(!empty($layout['sub_fields'])){
                        foreach($layout['sub_fields'] as $sub){
                            $walk_field($sub);
                        }
                    }
                }
                return;
            }

            if($type === 'clone'){
                // clone can inline sub_fields or reference "clone" keys depending on config
                if(!empty($field['sub_fields'])){
                    foreach($field['sub_fields'] as $sub){
                        $walk_field($sub);
                    }
                    return;
                }
            }
        };

        foreach($objs as $root){
            if(empty($root['name'])) continue;
            $allowed_roots[$root['name']] = true;

            $walk_field($root);
        }

        return ['allowed_leaf' => $allowed_leaf, 'allowed_roots' => $allowed_roots];
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
                                $fields[] = $name;
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

                            if ( !empty($ignored_fields_wildcards) && preg_match('/' . $ignored_fields_wildcards . '/', $field) ) {
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
                    'raw_anchor' => trim(wp_kses($anchor, 'post')),
                    'url' => $url
                ];
            }elseif(!empty($matches[7][$i])){
                $url = esc_attr($matches[7][$i]);
    
                $data[] = [
                    'sentence' => esc_attr__('Link is embedded, no sentence text detected', 'wpil'),
                    'anchor' => 'N/A',
                    'raw_anchor' => '',
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
                        'raw_anchor' => '',
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
                        'anchor' => wp_kses($anchor, 'post'),
                        'raw_anchor' => wp_kses($anchor, 'post'),
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
     * If quotes or apostrophes got shaved off before insert, try to borrow them back from the post content so we can actually insert the link.
     *
     * @param string $content
     * @param string $sentence
     * @param string $changed_sentence
     * @return array
     */
    public static function repunctuate_sentence($content, $sentence, $changed_sentence){
        $result = array(
            'sentence' => $sentence,
            'changed_sentence' => $changed_sentence,
        );

        if(empty($content) || empty($sentence) || empty($changed_sentence)){
            return $result;
        }

        // if the sentence is already in the content, we're already where we need to be
        if(
            false !== Wpil_Word::mb_strpos($content, $sentence) ||
            false !== Wpil_Word::mb_strpos(self::normalize_slashes($content), self::normalize_slashes($sentence))
        ){
            return $result;
        }

        $quote_chars = array("'", '"', '’', '‘', '“', '”');
        $split_chars = function($text){
            if($text === ''){
                return array();
            }

            $chars = preg_split('//u', (string) $text, -1, PREG_SPLIT_NO_EMPTY);
            return ($chars !== false) ? $chars : str_split((string) $text);
        };

        $is_quote = function($char) use ($quote_chars){
            return in_array($char, $quote_chars, true);
        };

        $build_match_data = function($text) use ($split_chars, $is_quote){
            $chars = $split_chars((string) $text);
            $clean = '';
            $map = array();

            foreach($chars as $index => $char){
                if($is_quote($char)){
                    continue;
                }

                $clean .= $char;
                $map[] = $index;
            }

            return array(
                'chars' => $chars,
                'clean' => $clean,
                'map' => $map,
            );
        };

        $content_data = $build_match_data($content);
        $sentence_data = $build_match_data($sentence);
        if(empty($content_data['clean']) || empty($sentence_data['clean'])){
            return $result;
        }

        $match_position = Wpil_Word::mb_strpos($content_data['clean'], $sentence_data['clean']);
        if(false === $match_position){
            return $result;
        }

        $clean_length = count($sentence_data['map']);
        if(
            empty($clean_length) || 
            !isset($content_data['map'][$match_position]) || 
            !isset($content_data['map'][$match_position + $clean_length - 1])
        ){
            return $result;
        }

        $match_start = $content_data['map'][$match_position];
        $match_end = $content_data['map'][$match_position + $clean_length - 1];
        $matched_sentence = implode('', array_slice($content_data['chars'], $match_start, (($match_end - $match_start) + 1)));
        if(empty($matched_sentence)){
            return $result;
        }

        $matched_data = $build_match_data($matched_sentence);
        if($matched_data['clean'] !== $sentence_data['clean']){
            return $result;
        }

        $plain_changed_sentence = trim(strip_tags(html_entity_decode($changed_sentence, ENT_QUOTES, 'UTF-8')));
        $changed_data = $build_match_data($plain_changed_sentence);
        if(!empty($changed_data['clean']) && $changed_data['clean'] === $sentence_data['clean']){
            $tokens = preg_split('/(<[^>]+>)/u', $changed_sentence, -1, PREG_SPLIT_DELIM_CAPTURE);

            if($tokens !== false){
                $rebuilt_changed_sentence = '';
                $source_index = 0;

                foreach($tokens as $token){
                    if($token === ''){
                        continue;
                    }

                    if(substr($token, 0, 1) === '<'){
                        $rebuilt_changed_sentence .= $token;
                        continue;
                    }

                    foreach($split_chars($token) as $char){
                        while(isset($matched_data['chars'][$source_index]) && $is_quote($matched_data['chars'][$source_index]) && $matched_data['chars'][$source_index] !== $char){
                            $rebuilt_changed_sentence .= $matched_data['chars'][$source_index];
                            $source_index++;
                        }

                        $rebuilt_changed_sentence .= $char;

                        if(isset($matched_data['chars'][$source_index]) && $matched_data['chars'][$source_index] === $char){
                            $source_index++;
                        }
                    }

                    while(isset($matched_data['chars'][$source_index]) && $is_quote($matched_data['chars'][$source_index])){
                        $rebuilt_changed_sentence .= $matched_data['chars'][$source_index];
                        $source_index++;
                    }
                }

                if(!empty($rebuilt_changed_sentence)){
                    $result['changed_sentence'] = $rebuilt_changed_sentence;
                }
            }
        }

        $result['sentence'] = $matched_sentence;

        return $result;
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

        if(!isset($url_parts['path']) || empty($url_parts['path'])){
            return false;
        }

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
        }elseif(preg_match('#[?&](tag_ID)=(\d+)#', $link, $values)){ // if it looks to be a tag id
            // if it's not, get the id
            $id = absint($values[2]);
            // if there is an id
            if($id){
                // get the term so we can make sure it exists
                $wp_term = get_term($id);
                // if it does exist, set the id. Else, set it to null
                $term_id = (!empty($wp_term)) ? $wp_term->term_id: null;
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

            // if the user isn't using hard rewrite custom perma links
            if(!defined('CUSTOM_PERMALINKS_FILE')){
                $post_id = url_to_postid($link); // try using the default getter.
            }
        }

        if (!empty($post_id)) {
            $post = new Wpil_Model_Post($post_id);
        }elseif(!empty($term_id)){
            $post = new Wpil_Model_Post($term_id, 'term');
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
                        if( false !== strpos($piece, '%postname%') &&   // if we're focussed on the postname
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
                            $all_post_types = Wpil_Query::postTypes('', true);
                            $dat = $wpdb->get_col($wpdb->prepare("SELECT `ID` FROM {$wpdb->posts} WHERE `post_name` = %s {$all_post_types} LIMIT 1", $name));
                        }

                        // if that didn't work either, try looking for the title
                        if(empty($dat)){ // TODO: set up some kind of a post title lookup table. The post_title column isn't indexed, and searching it for many results can take forever
                            // replace any hyphens with spaces
                            $name = str_replace('-', ' ', $name);
                            // and search through our post types
                            $dat = $wpdb->get_col($wpdb->prepare("SELECT `ID` FROM {$wpdb->posts} WHERE `post_title` = %s {$post_types} LIMIT 1", $name)); // for exceedingly long titles, I might consider re-adding the LIKE check. But we'll cross that bridge when we get there

                            // if that still didn't work, check the title across all the post types that we are reasonably sure are active
                            if(empty($dat)){
                                $all_post_types = Wpil_Query::postTypes('', true);
                                $dat = $wpdb->get_col($wpdb->prepare("SELECT `ID` FROM {$wpdb->posts} WHERE `post_title` = %s {$all_post_types} LIMIT 1", $name));
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

    /**
     * Gets a detailed list of ids...
     * Focussing on posts sincce 97% of people will be using those
     **/
    public static function get_money_pages(){
        $list = array(); // yay! our list of page ids!

        // first, go for the easy ones
        // get the ones that the user has set!
        $list = array_merge($list, get_option('wpil_pillar_content_post_ids', []));

        // quit now if the user has defined money pages
        if(!empty($list)){
            return $list;
        }

        // pull cornerstone from Yoast if it's set
        $list = array_merge($list, Wpil_Toolbox::get_cornerstone_ids());

        // and pillar from Rank Math // because they're totally different!
        $list = array_merge($list, Wpil_Toolbox::get_pillar_content_ids());

        // todo: pull in other seo plugins

        // next, pull what we can from the menus
        $menu_links = self::get_nav_menu_items();

        // if we've got something
        if(!empty($menu_links)){
            // add it to the list
            foreach($menu_links as $link){
                $list[] = $link['object_id'];
            }
        }
        
        // next, lets analyse linking trends to see if there are any posts that stand out...
        $list = array_merge($list, Wpil_Report::get_link_targetted_pages());
        // if there are, add them to the list

        // next, check for forms... // later...
        // add the non help forms to the list

        // try pulling in any e-com pages // yeah, later too...

        // sift out the garbage
        $ignored = Wpil_Settings::get_completely_ignored_pages();
        if(!empty($ignored)){
            $ignore_posts = array();
            foreach($ignored as $pid){
                $bits = explode('_', $pid);
                if(!empty($bits) && $bits[1] === 'post'){
                    $ignore_posts[] = $bits[0];
                }
            }

            if(!empty($ignore_posts)){
                $list = array_diff($list, $ignore_posts); 
            }
        }

        // if we've got posts
        if(!empty($list)){
            // filter to get the uniques
            $list = array_keys(array_flip($list)); // and we're left with profit!
        }

        return $list; // profit!
    }

    /**
     * Returns all nav menu items for the site.
     * Makes sure to only give us post objects that can be traced.
     *
     * @return array
     */
    public static function get_nav_menu_items(){
        $results = [];
        $seen    = [];

        // location => term_id
        $locations = get_nav_menu_locations();
        $location_by_term_id = [];
        foreach ($locations as $loc => $term_id) {
            $location_by_term_id[(int) $term_id] = (string) $loc;
        }

        // Pull menus that exist on the site.
        $menus = wp_get_nav_menus(); // array of WP_Term objects

        foreach ($menus as $menu_term) {
            $menu_id   = (int) $menu_term->term_id;
            $menu_name = (string) $menu_term->name;
            $location  = isset($location_by_term_id[$menu_id]) ? $location_by_term_id[$menu_id]: null;

            // Get items for this menu.
            $items = wp_get_nav_menu_items($menu_id);
            if (empty($items) || is_wp_error($items)) {
                continue;
            }

            foreach ($items as $item) {
                // $item is WP_Post with extra properties
                $url = isset($item->url) ? trim((string) $item->url) : '';
                if($url === '' || $url === '#' || !Wpil_Link::isInternal($url)){ 
                    continue;
                }

                $key = strtolower($url);
                if(isset($seen[$key])){
                    continue;
                }
                $seen[$key] = true;

                if($item->type === 'custom'){
                    $post = self::getPostByLink($url);
                    if(!empty($post) && $post->type === 'post'){
                        $object_id = $post->id;
                    }else{
                        continue;
                    }
                }else{
                    $object_id = $item->object_id;
                }

                $results[] = [
                    'menu'      => $menu_name,
                    'location'  => $location,               // null if not assigned to a theme location
                    'item_id'   => (int) $item->ID,
                    'title'     => (string) $item->title,
                    'url'       => $url,                    // already the resolved URL WP outputs in menus
                    'type'      => (string) $item->type,    // 'post_type', 'taxonomy', 'custom', etc.
                    'object'    => (string) $item->object,  // 'page', 'category', etc.
                    'object_id' => (int) $object_id,
                ];
            }
        }

        return $results;
    }

    /**
     * Main entry. Returns best about page candidate or null.
     *
     * @return array|null { post_id, url, score, reasons }
     */
    public static function find_about_page(){
        $candidates = self::find_about_page_candidates();
        if(empty($candidates)){
            return null;
        }

        $scored = [];
        foreach($candidates as $candidate){
            $scored[] = self::score_about_page_candidate($candidate);
        }

        usort($scored, function($a, $b){
            $a_score = isset($a['score']) ? $a['score'] : 0;
            $b_score = isset($b['score']) ? $b['score'] : 0;

            if($a_score == $b_score){
                return 0;
            }

            return ($a_score < $b_score) ? 1 : -1;
        });

        $best = (isset($scored[0]) && !empty($scored[0])) ? $scored[0]: null;

        // Threshold to avoid returning nonsense.
        if (!$best || !isset($best['score']) || $best['score'] < 8) {
            return null;
        }

        return $best;
    }

    /**
     * Collect candidates from menus, homepage header/footer scrape, and fallback published pages.
     *
     * @return array[] Each item: { url, anchor_text, source, post_id }
     */
    public static function find_about_page_candidates() {
        $home_url = home_url('/');
        $home_host = parse_url($home_url, PHP_URL_HOST);

        $raw_candidates = [];

        // 1) Menus
        $raw_candidates = array_merge($raw_candidates, self::pull_nav_menu_links());

        // 2) Homepage scrape for header and footer links
        $raw_candidates = array_merge($raw_candidates, self::pull_links_from_homepage());

        // 3) Fallback: pages list (helps if homepage is locked down or minimal)
        $pages = get_posts([
            'post_type' => 'page',
            'post_status' => 'publish',
            'posts_per_page' => 200,
            'orderby' => 'menu_order',
            'order' => 'ASC',
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);

        if(!empty($pages)){
            foreach ($pages as $page_id) {
                $url = get_permalink($page_id);
                if (!$url) {
                    continue;
                }
                $raw_candidates[] = [
                    'url' => $url,
                    'anchor_text' => get_the_title($page_id),
                    'source' => 'published_pages',
                    'post_id' => (int) $page_id,
                ];
            }
        }

        // Normalize and dedupe
        $dedup = [];
        foreach ($raw_candidates as $cand) {
            $url = isset($cand['url']) ? trim($cand['url']) : '';
            if ($url === '') {
                continue;
            }

            // Only internal links
            $host = parse_url($url, PHP_URL_HOST);
            if ($host && $home_host && strcasecmp($host, $home_host) !== 0) {
                continue;
            }

            // Normalize to absolute
            if (strpos($url, '//') === 0) {
                $url = (is_ssl() ? 'https:' : 'http:') . $url;
            } elseif (strpos($url, 'http') !== 0) {
                $url = home_url($url);
            }

            // Remove fragments
            $url = preg_replace('/#.*$/', '', $url);

            // Skip empty or homepage
            if ($url === $home_url) {
                continue;
            }

            // Resolve to post id when possible
            if(!isset($cand['post_id']) || empty($cand['post_id'])){
                $cand['post_id'] = self::getPostByLink($url);
            }

            $cand['url'] = $url; // make sure the url is fully normalized

            $key = strtolower($url);
            if (!isset($dedup[$key])) {
                $dedup[$key] = $cand;
            } else {
                // Prefer candidates that have anchor_text or better source
                if (empty($dedup[$key]['anchor_text']) && !empty($cand['anchor_text'])) {
                    $dedup[$key]['anchor_text'] = $cand['anchor_text'];
                }
                if (($dedup[$key]['source'] ?? '') !== 'homepage_header_footer' && ($cand['source'] ?? '') === 'homepage_header_footer') {
                    $dedup[$key]['source'] = $cand['source'];
                }
            }
        }

        return array_values($dedup);
    }

    private static function pull_nav_menu_links() {
        $out = [];

        $pages = self::get_nav_menu_items();
        if (!is_array($pages) || empty($pages)) {
            return $out;
        }

        foreach($pages as $page){
            $out[] = array_merge([
                'anchor_text' => $page['title'],
                'source' => 'menu_' . $page['location'],
                'post_id' => $page['object_id']
            ], $page);
        }

        return $out;
    }

    private static function pull_links_from_homepage() {
        $out = [];

        if(!class_exists('DOMDocument')){
            return $out;
        }

        // grabe the homepage directly to ensure we get all the nav links
        $response = wp_remote_get(home_url('/'), [
            'timeout' => 10,
            'redirection' => 5,
            'user-agent' => WPIL_DATA_USER_AGENT,
            'headers' => [
                'Accept' => 'text/html,application/xhtml+xml',
            ],
        ]);

        if(is_wp_error($response)){
            return $out;
        }

        $html = (string) wp_remote_retrieve_body($response);
        if($html === ''){
            return $out;
        }

        // iuf our page is really long
        if(strlen($html) > 1500000){
            // trime it
            $html = substr($html, 0, 1500000);
        }

        // since we're only checking one page, we'll cheat with domdoc
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML($html);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);

        // look for the normal nave areas as well as any page builder sections standing in for them
        $contexts = [
            '//header//a[@href]',
            '//footer//a[@href]',
            '//nav//a[@href]',
            "//*[contains(translate(@id,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'),'header') or contains(translate(@class,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'),'header')]//a[@href]",
            "//*[contains(translate(@id,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'),'footer') or contains(translate(@class,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'),'footer')]//a[@href]",
            "//*[contains(translate(@id,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'),'menu') or contains(translate(@class,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'),'menu')]//a[@href]",
        ];

        $seen = [];
        foreach($contexts as $query){
            $nodes = $xpath->query($query);
            if(!$nodes){
                continue;
            }
            foreach($nodes as $a){
                $href = $a->getAttribute('href');
                if (!$href) {
                    continue;
                }
                $text = trim($a->textContent ?? '');
                $key = strtolower($href . '|' . $text);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                $out[] = [
                    'url' => $href,
                    'anchor_text' => $text,
                    'source' => 'homepage_header_footer',
                ];
            }
        }

        return $out;
    }

    function score_about_page_candidate(array $candidate) {
        $url = (string) ($candidate['url'] ?? '');
        $anchor_text = (string) ($candidate['anchor_text'] ?? '');
        $source = (string) ($candidate['source'] ?? '');
        $post_id = (int) ($candidate['post_id'] ?? 0);

        $reasons = [];
        $score = 0;

        $about_keywords = self::get_standard_about_page_keywords();
        $negative_keywords = self::get_standard_non_about_page_keywords();

        $url_path = strtolower((string) parse_url($url, PHP_URL_PATH));
        $url_host = strtolower((string) parse_url($url, PHP_URL_HOST));

        // Skip obvious non content pages
        foreach ($negative_keywords as $bad) {
            if ($bad !== '' && (strpos($url_path, $bad) !== false || strpos(strtolower($anchor_text), $bad) !== false)) {
                $score -= 20;
                $reasons[] = 'negative_keyword:' . $bad;
                break;
            }
        }

        // Keyword match in URL path
        foreach ($about_keywords as $kw) {
            if ($kw !== '' && strpos($url_path, $kw) !== false) {
                $score += 10;
                $reasons[] = 'url_keyword:' . $kw;
                break;
            }
        }

        // Keyword match in anchor text
        $normalized_anchor = self::about_page_normalize_text($anchor_text);
        foreach ($about_keywords as $kw) {
            if ($kw !== '' && strpos($normalized_anchor, $kw) !== false) {
                $score += 8;
                $reasons[] = 'anchor_keyword:' . $kw;
                break;
            }
        }

        // Source weighting
        if (strpos($source, 'menu_') === 0) {
            $score += 2;
            $reasons[] = 'source:menu';
        }
        if ($source === 'homepage_header_footer') {
            $score += 3;
            $reasons[] = 'source:homepage_layout';
        }

        // If we can resolve to a page, use title and template signals
        if ($post_id > 0) {
            $post = get_post($post_id);
            if ($post && $post->post_type === 'page' && $post->post_status === 'publish') {
                $score += 2;
                $reasons[] = 'is_page';

                $title_norm = self::about_page_normalize_text(get_the_title($post_id));
                foreach ($about_keywords as $kw) {
                    if ($kw !== '' && strpos($title_norm, $kw) !== false) {
                        $score += 8;
                        $reasons[] = 'title_keyword:' . $kw;
                        break;
                    }
                }

                $template = (string) get_page_template_slug($post_id);
                $template_norm = self::about_page_normalize_text($template);
                if ($template_norm && strpos($template_norm, 'about') !== false) {
                    $score += 6;
                    $reasons[] = 'template_mentions_about';
                }

                // Many about pages are top level pages
                if ((int) $post->post_parent === 0) {
                    $score += 1;
                    $reasons[] = 'top_level_page';
                }
            }
        }

        // Prefer shorter paths that look like a page slug
        $segments = array_values(array_filter(explode('/', trim($url_path, '/'))));
        if (count($segments) === 1) {
            $score += 1;
            $reasons[] = 'single_segment_path';
        }

        // Penalize media files and feeds
        if (preg_match('/\.(jpg|jpeg|png|gif|webp|pdf|zip|xml)$/i', $url_path)) {
            $score -= 15;
            $reasons[] = 'file_like_url';
        }
        if (strpos($url_path, '/feed') !== false) {
            $score -= 10;
            $reasons[] = 'feed_url';
        }

        return [
            'post_id' => $post_id,
            'url' => $url,
            'score' => $score,
            'reasons' => $reasons,
            'source' => $source,
            'anchor_text' => $anchor_text,
        ];
    }

    private static function about_page_normalize_text($text) {
        $text = (string) $text;
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strtolower($text);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        // Remove punctuation but keep unicode letters and numbers
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    private static function get_standard_about_page_keywords() {
        return [
            // English
            'about', 'about-us', 'aboutus', 'about-me', 'aboutme',
            'about-the-author', 'about-the-company', 'about-the-site',
            'our-story', 'our team', 'our-team', 'meet-the-team', 'meet our team',
            'who-we-are', 'who we are', 'who-i-am', 'who i am', 'mission',

            // Spanish (Español)
            'acerca', 'acerca-de', 'acerca de',
            'acerca-de-nosotros', 'acerca de nosotros',
            'acerca-de-mi', 'acerca de mi', 'acerca-de-mí', 'acerca de mí',
            'sobre', 'sobre-mi', 'sobre mi', 'sobre-mí', 'sobre mí',
            'sobre-nosotros', 'sobre nosotros', 'sobre-nosotras', 'sobre nosotras',
            'quienes-somos', 'quienes somos',
            'quiénes-somos', 'quiénes somos',
            'nuestra-historia', 'nuestra historia',
            'nuestro-equipo', 'nuestro equipo',
            'conocenos', 'conócenos',

            // French (Français)
            'a-propos', 'a propos',
            'a-propos-de-nous', 'a propos de nous',
            'a-propos-de-moi', 'a propos de moi',
            'apropos',
            'qui-sommes-nous', 'qui sommes nous',
            'notre-histoire', 'notre histoire',
            'notre-equipe', 'notre équipe',
            'l-equipe', 'l equipe',

            // German (Deutsch)
            'uber-uns', 'ueber-uns', 'über-uns',
            'uber uns', 'ueber uns', 'über uns',
            'uber-mich', 'ueber-mich', 'über-mich',
            'uber mich', 'ueber mich', 'über mich',
            'uber-das-unternehmen', 'ueber-das-unternehmen', 'über-das-unternehmen',
            'unsere-geschichte', 'unsere geschichte',
            'unser-team', 'unser team',

            // Russian (Русский)
            'о нас', 'обо мне', 'о компании', 'о проекте', 'о фирме', 'о магазине',
            'o-nas', 'o nas',
            'o-kompanii', 'o kompanii',
            'o-proekte', 'o proekte',

            // Portuguese (Português)
            'sobre-nos', 'sobre nos', 'sobre-nós', 'sobre nós',
            'sobre-mim', 'sobre mim',
            'quem-somos', 'quem somos',
            'nossa-historia', 'nossa história',
            'nossa-equipe', 'nossa equipe',

            // Dutch (Dutch)
            'over-ons', 'over ons',
            'over-mij', 'over mij',
            'ons-verhaal', 'ons verhaal',
            'ons-team', 'ons team',
            'wie-zijn-wij', 'wie zijn wij',

            // Danish / Norwegian / Swedish (Dansk / Norsk bokmål / Svenska)
            'om-oss', 'om oss',
            'om-os', 'om os',
            'om-meg', 'om meg',
            'om-mig', 'om mig',
            'om-foretaget', 'om foretaget',
            'om-foretag', 'om foretag',
            'om-bedriften', 'om bedriften',
            'om-selskapet', 'om selskapet',
            'om-virksomheden', 'om virksomheden',
            'om-virksomheten', 'om virksomheten',
            'var-historia', 'vår-historie', 'vår historik', 'var historie',
            'mot-teamet', 'möt teamet',
            'møte-teamet', 'mote teamet',

            // Italian (Italiano)
            'chi-siamo', 'chi siamo',
            'su-di-noi', 'su di noi',
            'su-di-me', 'su di me',
            'la-nostra-storia', 'la nostra storia',
            'il-nostro-team', 'il nostro team',

            // Polish (Polskie)
            'o-nas', 'o nas',
            'o-mnie', 'o mnie',
            'o-firmie', 'o firmie',
            'nasz-zespol', 'nasz zespol', 'nasz-zespół', 'nasz zespół',
            'kim-jestesmy', 'kim jestesmy', 'kim jesteśmy',

            // Slovak (Slovenčina)
            'o-nas', 'o nás', 'o-nás',
            'o-mne', 'o mne',
            'o-firme', 'o firme',
            'o-spolocnosti', 'o spoločnosti', 'o-spoločnosti',
            'o-projekte', 'o projekte',

            // Arabic (عربي)
            'من نحن', 'عن الشركة', 'عن الموقع', 'نبذة عنا',
            'من-نحن', 'عن-الشركة', 'عن-الموقع', 'نبذة-عنا',

            // Serbian (Српски / srpski)
            'о нама', 'о мени', 'о компанији', 'о фирми', 'о пројекту',
            'o-nama', 'o nama',
            'o-meni', 'o meni',
            'o-kompaniji', 'o kompaniji',
            'o-firmi', 'o firmi',
            'o-projektu', 'o projektu',

            // Finnish (Suomi)
            'meista', 'meistä', 'meistämme',
            'minusta',
            'tietoa',
            'tietoa-meista', 'tietoa meistä',
            'yrityksesta', 'yrityksestä',
            'tietoa-yrityksesta', 'tietoa yrityksestä',

            // Hebrew (עִבְרִית)
            'עלינו', 'על החברה', 'עליי', 'מי אנחנו',
            'על-ינו', 'על-החברה', 'מי-אנחנו',

            // Hindi (हिन्दी)
            'हमारे बारे में', 'मेरे बारे में', 'कंपनी के बारे में',
            'hamare-bare-mein', 'hamare bare mein',
            'mere-bare-mein', 'mere bare mein',
            'company-ke-bare-mein', 'company ke bare mein',

            // Hungarian (Magyar)
            'rólunk', 'rolunk',
            'rólam', 'rolam',
            'csapatunk',
            'kuldetesunk', 'küldetésünk',
            'cégünkről', 'cegunkrol',

            // Romanian (Română)
            'despre', 'despre-noi', 'despre noi',
            'despre-mine', 'despre mine',
            'cine-suntem', 'cine suntem',
            'echipa-noastra', 'echipa noastră',
            'povestea-noastra', 'povestea noastră',

            // Ukrainian (Українська)
            'про нас', 'про мене', 'про компанію', 'про проект', 'про проєкт', 'хто ми',
            'pro-nas', 'pro nas',
            'pro-mene', 'pro mene',
            'pro-kompaniyu', 'pro kompaniyu',
            'pro-kompaniia', 'pro kompaniia',
            'khto-my', 'khto my',

            // Indonesian (Bahasa Indonesia)
            'tentang-kami', 'tentang kami',
            'tentang-saya', 'tentang saya',
            'tentang-perusahaan', 'tentang perusahaan',
            'profil-perusahaan', 'profil perusahaan',
            'kisah-kami', 'kisah kami',

            // Czech (Čeština)
            'o nás', 'o-nas', 'o nas',
            'o mně', 'o-mne', 'o mne',
            'o-spolecnosti', 'o společnosti', 'o spolecnosti',
            'o-projektu', 'o projektu',

            // Bulgarian (български)
            'за нас', 'за мен', 'за компанията', 'за фирмата', 'за проекта',
            'za-nas', 'za nas',
            'za-men', 'za men',
            'za-kompaniyata', 'za kompaniyata',
            'za-firmata', 'za firmata',
            'za-proekta', 'za proekta',

            // Lithuanian (Lietuvių)
            'apie-mus', 'apie mus',
            'apie-mane', 'apie mane',
            'apie-imone', 'apie įmonę', 'apie įmone',
            'apie-projekta', 'apie projektą', 'apie projekta',
            'kas-mes-esame', 'kas mes esame',
            'musu-istorija', 'mūsų istorija', 'musu istorija',
            'musu-komanda', 'mūsų komanda', 'musu komanda',

            // Latvian (Latviešu)
            'par-mums', 'par mums',
            'par-mani', 'par mani',
            'par-uznemumu', 'par uzņēmumu', 'par uznemumu',
            'musu-komanda', 'mūsu komanda', 'musu komanda',
            'musu-stasts', 'mūsu stāsts', 'musu stasts',
            'kas-mes-esam', 'kas mēs esam', 'kas mes esam',

            // Estonian (Eesti)
            'meist',
            'minust',
            'ettevottest', 'ettevõttest', 'ettevoittest',
            'meie-lugu', 'meie lugu',
            'meie-meeskond', 'meie meeskond',
            'kes-me-oleme', 'kes me oleme',
            
            // Greek (someday!)
            'sxetika', 'σχετικα', 'σχετικά',

            // Turkish
            'hakkimizda', 'hakkımızda',

            // Vietnamese (Tiếng Việt)
            'gioi-thieu', 'giới thiệu', 'gioi thieu',
            've-chung-toi', 'về chúng tôi', 've chung toi',
            've-toi', 'về tôi', 've toi',
            've-cong-ty', 'về công ty', 've cong ty',
            'cau-chuyen-cua-chung-toi', 'câu chuyện của chúng tôi', 'cau chuyen cua chung toi',
            'doi-ngu-cua-chung-toi', 'đội ngũ của chúng tôi', 'doi ngu cua chung toi',
            'ai-chung-toi-la', 'ai chúng tôi là', 'ai chung toi la',

            // Japanese (someday!)
            'about', 'プロフィール', '私について', '運営者情報',

            // Chinese (someday!)
            '关于', '关于我们', '关于我',
        ];
    }

    private static function get_standard_non_about_page_keywords() {
        return [
            'privacy', 'privacy policy', 'terms', 'terms of use', 'cookies', 'cookie policy',
            'login', 'sign in', 'signup', 'register', 'account',
            'cart', 'checkout', 'my account',
            'contact', 'support', 'help',
            'refund', 'returns', 'shipping',
            'wp admin', 'wp-login',
        ];
    }




}

