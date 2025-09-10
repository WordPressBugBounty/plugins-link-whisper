<?php

/**
 * Sitemap controller
 */
class Wpil_Sitemap
{

    public static $existing_sitemaps = array(); // NOTE: Currently only lists sitemap types to save on data use
    /**
     * Register services
     */
    public function register()
    {
        add_action('wp_ajax_wpil_generate_link_sitemaps', array(__CLASS__, 'ajax_generate_link_sitemaps'));
        add_action('wp_ajax_wpil_save_custom_sitemap_data', array(__CLASS__, 'ajax_save_custom_sitemap_data'));
        add_action('wp_ajax_wpil_delete_custom_sitemap', array(__CLASS__, 'ajax_delete_custom_sitemap'));
    }

    /**
     * Creates the sitemap table if it doesn't exist
     **/
    public static function prepare_table(){
        global $wpdb;
        $sitemap_table = $wpdb->prefix . 'wpil_sitemaps';


        // if the sitemap table doesn't exist
        $stmp_tbl_exists = $wpdb->query("SHOW TABLES LIKE '{$sitemap_table}'");
        if(empty($stmp_tbl_exists)){
            $sitemap_table_query = "CREATE TABLE IF NOT EXISTS {$sitemap_table} (
                sitemap_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                sitemap_name text,
                sitemap_content longtext, 
                sitemap_type varchar(64),
                PRIMARY KEY (sitemap_id)
            ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";

            // create DB table if it doesn't exist
            require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sitemap_table_query);
        }
    }


    /**
     * Generates the link sitemaps on Ajax call
     **/
    public static function ajax_generate_link_sitemaps(){
        Wpil_Base::verify_nonce('wpil_generate_link_sitemaps');

        // make sure the table is set up every time a user calls for it
        self::prepare_table();

        $memory_break_point = Wpil_Report::get_mem_break_point();
        $link_sitemaps = array(
            'link_sitemap_inbound'  => __('Inbound Link Sitemap', 'wpil'),
            'link_sitemap_outbound' => __('Outbound Link Sitemap', 'wpil'),
            'link_sitemap_external' => __('External Link Sitemap', 'wpil'),
            'link_sitemap_all'      => __('All Link Sitemap', 'wpil'),
        );

        if(isset($_POST['last_sitemap']) && !empty($_POST['last_sitemap']) && isset($link_sitemaps[$_POST['last_sitemap']])){
            $link_sitemaps = array_slice($link_sitemaps, (array_search($_POST['last_sitemap'], array_keys($link_sitemaps)) + 1));

        }

        $last_sitemap = key($link_sitemaps);
        end($link_sitemaps);
        $final_sitemap = key($link_sitemaps);
        $sitemap_count = count($link_sitemaps);
        foreach($link_sitemaps as $sitemap_type => $sitemap_name){
            if (Wpil_Base::overTimeLimit(10, 25) || ('disabled' !== $memory_break_point && memory_get_usage() > $memory_break_point) ){
                break;
            }
            $last_sitemap = $sitemap_type;
            Wpil_Sitemap::save_sitemap(Wpil_Sitemap::create_link_sitemap($sitemap_type), $sitemap_type, $sitemap_name);
        }

        if(!empty(Wpil_Settings::has_ai_enabled())){
            // if we have relation data
            if(!empty(Wpil_AI::has_calculated_embedding_data())){
                $relatedness = Wpil_AI::calculate_relatedness_sitemap();
                Wpil_Sitemap::save_sitemap($relatedness, 'ai_sitemap', 'AI Sitemap');
                $sitemap_count++;
    //            Wpil_Sitemap::save_sitemap($relatedness, 'ai_combined_sitemap', 'AI & Link Sitemap');
            }

            // if we have products detected
            $products = Wpil_AI::calculate_product_sitemap();
            if(!empty($products) && !Wpil_Sitemap::has_sitemap('ai_product_sitemap')){
                Wpil_Sitemap::save_sitemap($products, 'ai_product_sitemap', 'AI-Detected Product Sitemap');
                $sitemap_count++;
            }
        }

        if($last_sitemap === $final_sitemap){
            // and tell the user about it
            wp_send_json(array('success' => array('title' => __('Sitemaps Generated!', 'wpil'), 'text' => __('The Sitemaps have been generated!', 'wpil'))));
        }else{
            wp_send_json(array('continue' => array('data' => array('sitemap' => $last_sitemap))));
        }
    }

    public static function ajax_save_custom_sitemap_data(){
        Wpil_Base::verify_nonce('wpil_save_custom_sitemap');

        if( !isset($_POST['sitemap_data']) || 
            empty($_POST['sitemap_data']) || 
            !isset($_POST['sitemap_name']) || 
            empty($_POST['sitemap_name']))
        {
            $text = (!isset($_POST['sitemap_data']) || empty($_POST['sitemap_data'])) ? __('There was no sitemap data extracted from the CSV, please check the file and make sure that it\'s formatter correctly.', 'wpil'): __('The sitemap name seems to be missing, please enter a name for this sitemap.', 'wpil');

            wp_send_json(array(
                'error' => array(
                    'title' => __('Missing Data', 'wpil'),
                    'text'  => $text,
                )
            ));
        }

        $post_cache = array();
        $calculated = array();

        if(isset($_POST['sitemap_id']) && !empty($_POST['sitemap_id'])){
            $calculated = self::get_sitemap((int)$_POST['sitemap_id'], true);
        }

        $sitemap_name = sanitize_text_field($_POST['sitemap_name']);
        foreach($_POST['sitemap_data'] as $dat){
            if(!isset($dat['source']) || empty($dat['source'])){
                continue;
            }

            $post = null;

            // if the url is internal
            if(Wpil_link::isInternal($dat['source'])){
                if(!isset($post_cache[$dat['source']])){
                    $post = Wpil_Post::getPostByLink($dat['source']);
                    $post_cache[$dat['source']] = $post;
                }else{
                    $post = $post_cache[$dat['source']];
                }
            }

            if(!empty($post)){
                $source = $post->type  . '_' . $post->id;
            }else{
                // if the link is missing it's protocol
                if(false === strpos($dat['source'], 'http')){
                    // add one for the benefit of the url parser
                    $dat['source'] = ('https://' . $dat['source']);
                }

                $host = wp_parse_url($dat['source'], PHP_URL_HOST);

                // if there is no host
                if(empty($host)){
                    continue;
                }
                $source = str_replace('www.', '', $host);
            }

            $target = null;
            $target_post = null;

            if(!isset($calculated[$source])){
                $calculated[$source] = array();
            }

            // if the url is internal
            if(Wpil_link::isInternal($dat['target'])){
                if(!isset($post_cache[$dat['target']])){
                    $target_post = Wpil_Post::getPostByLink($dat['target']);
                    $post_cache[$dat['target']] = $target_post;
                }else{
                    $target_post = $post_cache[$dat['target']];
                }
            }

            if(!empty($target_post)){
                $target = $target_post->type  . '_' . $target_post->id;
            }else{
                // if the link is missing it's protocol
                if(false === strpos($dat['target'], 'http')){
                    // add one for the benefit of the url parser
                    $dat['target'] = ('https://' . $dat['target']);
                }

                $host = wp_parse_url($dat['target'], PHP_URL_HOST);

                // if there is no host
                if(empty($host)){
                    continue;
                }
                $target = str_replace('www.', '', $host);
            }

            if(!empty($target)){
                $calculated[$source][$target] = 1;
            }
        }

        // if we're doing a partial batch and there's more to go
        if( isset($_POST['partial'], $_POST['finishing']) && 
            !empty((int)$_POST['partial']) && empty((int)$_POST['finishing']))
        {
            // save the existing data and go around for another pass
            $map_id = self::save_sitemap($calculated, 'custom_sitemap', $sitemap_name, true);
            wp_send_json(array('continue' => array('data' => array('sitemap_id' => $map_id))));
        }

        if(!empty($calculated)){
            // go over the results and make sure all of the targets have a destination
            foreach($calculated as $src => $dat){
                foreach($dat as $s => $d){
                    if(!isset($calculated[$s])){
                        $calculated[$s] = array();
                    }
                }
            }

            self::save_sitemap($calculated, 'custom_sitemap', $sitemap_name);
            wp_send_json(array('success' => array('title' => __('Sitemap Created!', 'wpil'), 'text' => __('The custom sitemap has been created!', 'wpil'))));
        }

        wp_send_json(array('info' => array('title' => __('Sitemap Not Created!', 'wpil'), 'text' => __('The sitemap data couldn\'t be processed and the sitemap hasn\'t been created. Please check the CSV file\'s formatting and confirm that there are only two rows of URLs and that none of the URLs contain special charactors like quotes or commas.', 'wpil'))));
    }

    public static function ajax_delete_custom_sitemap(){
        Wpil_Base::verify_nonce('wpil_delete_custom_sitemap');

        $deleted = false;
        if(isset($_POST['sitemap_id']) && !empty($_POST['sitemap_id'])){
            $deleted = self::delete_sitemap((int)$_POST['sitemap_id'], 'custom_sitemap');
        }

        if($deleted){
            wp_send_json(array('success' => array('title' => __('Sitemap Deleted!', 'wpil'), 'text' => __('The custom sitemap has been deleted!', 'wpil'))));
        }else{
            wp_send_json(array('info' => array('title' => __('Sitemap Not Deleted!', 'wpil'), 'text' => __('The sitemap data couldn\'t be deleted at this time. Please reload the page and try again', 'wpil'))));
        }
    }


    public static function get_sitemap($id = 0, $return_assoc = false){
        global $wpdb;
        $sitemap_table = $wpdb->prefix . 'wpil_sitemaps';

        if(empty($id)){
            return array();
        }

        $results = $wpdb->get_var($wpdb->prepare("SELECT `sitemap_content` FROM {$sitemap_table} WHERE `sitemap_id` = %d", $id));

        if(!empty($results)){
            $decompress = Wpil_Toolbox::json_decompress($results, $return_assoc);

            if(!empty($decompress)){
                return $decompress;
            }
        }

        return array();
    }

    public static function get_ai_sitemap(){
        global $wpdb;
        $sitemap_table = $wpdb->prefix . 'wpil_sitemaps';

        $results = $wpdb->get_row("SELECT `sitemap_content` FROM {$sitemap_table} WHERE `sitemap_type` = 'ai_sitemap'");

        if(!empty($results)){
            $decompress = Wpil_Toolbox::json_decompress($results);

            if(!empty($decompress)){
                return $decompress;
            }
        }

        return array();
    }

    public static function get_ai_product_sitemap(){
        global $wpdb;
        $sitemap_table = $wpdb->prefix . 'wpil_sitemaps';

        $results = $wpdb->get_row("SELECT `sitemap_content` FROM {$sitemap_table} WHERE `sitemap_type` = 'ai_product_sitemap'");

        if(!empty($results)){
            $decompress = Wpil_Toolbox::json_decompress($results);

            if(!empty($decompress)){
                return $decompress;
            }
        }

        return array();
    }

    public static function get_sitemap_list($type = ''){
        global $wpdb;
        $sitemap_table = $wpdb->prefix . 'wpil_sitemaps';
        $results = array();

        if(!empty($type)){
            if(self::is_valid_sitemap_type($type)){
                $results = $wpdb->get_results($wpdb->prepare("SELECT `sitemap_id`, `sitemap_name`, `sitemap_type` FROM {$sitemap_table} WHERE `sitemap_type` = %s", $type));
            }
        }else{
            $results = $wpdb->get_results("SELECT `sitemap_id`, `sitemap_name`, `sitemap_type` FROM {$sitemap_table}");
        }

        return !empty($results) ? $results: array();
    }

    public static function get_all_sitemap_data($decompress = false){
        global $wpdb;
        $sitemap_table = $wpdb->prefix . 'wpil_sitemaps';

        $results = $wpdb->get_results("SELECT * FROM {$sitemap_table}");

        if(!empty($decompress) && !empty($results)){
            foreach($results as $key => $result){
                $result[$key]->sitemap_content = Wpil_Toolbox::json_decompress($result->sitemap_content);
            }
        }

        return !empty($results) ? $results: array();
    }

    public static function save_sitemap($sitemap = array(), $type = '', $sitemap_name = '', $return_id = false){
        global $wpdb;
        $sitemap_table = $wpdb->prefix . 'wpil_sitemaps';
        $map_id = 0;

        if(empty($sitemap) || empty($type)){
            return array();
        }

        $sitemap = Wpil_Toolbox::json_compress($sitemap);
        if(empty($sitemap)){
            return array();
        }

        $results = false;
        if($type === 'ai_sitemap'){
            $exists = $wpdb->get_var("SELECT COUNT(*) FROM {$sitemap_table} WHERE `sitemap_type` = 'ai_sitemap'");
            if(!empty($exists)){
                $results = $wpdb->update(
                    $sitemap_table,
                    [
                        'sitemap_content' => $sitemap, 
                        'sitemap_name' => $sitemap_name,
                    ],
                    [
                        'sitemap_type' => $type,
                    ]
                );
                $results = 1;

                if($return_id){
                    $map_id = $wpdb->get_var($wpdb->prepare("SELECT `sitemap_id` FROM {$sitemap_table} WHERE `sitemap_type` = %s ORDER BY `sitemap_id` DESC", $type));
                }
            }
        }

        if($type === 'ai_combined_sitemap'){
            $exists = $wpdb->get_var("SELECT COUNT(*) FROM {$sitemap_table} WHERE `sitemap_type` = 'ai_combined_sitemap'");
            if(!empty($exists)){
                $results = $wpdb->update(
                    $sitemap_table,
                    [
                        'sitemap_content' => $sitemap, 
                        'sitemap_name' => $sitemap_name,
                    ],
                    [
                        'sitemap_type' => $type,
                    ]
                );
                $results = 1;

                if($return_id){
                    $map_id = $wpdb->get_var($wpdb->prepare("SELECT `sitemap_id` FROM {$sitemap_table} WHERE `sitemap_type` = %s ORDER BY `sitemap_id` DESC", $type));
                }
            }
        }
        
        if($type === 'ai_product_sitemap'){
            $exists = $wpdb->get_var("SELECT COUNT(*) FROM {$sitemap_table} WHERE `sitemap_type` = 'ai_product_sitemap'");
            if(!empty($exists)){
                $results = $wpdb->update(
                    $sitemap_table,
                    [
                        'sitemap_content' => $sitemap, 
                        'sitemap_name' => $sitemap_name,
                    ],
                    [
                        'sitemap_type' => $type,
                    ]
                );
                $results = 1;

                if($return_id){
                    $map_id = $wpdb->get_var($wpdb->prepare("SELECT `sitemap_id` FROM {$sitemap_table} WHERE `sitemap_type` = %s ORDER BY `sitemap_id` DESC", $type));
                }
            }
        }

        $link_sitemaps = array(
            'link_sitemap_inbound',
            'link_sitemap_outbound',
            'link_sitemap_external',
            'link_sitemap_all',
        );
        if(in_array($type, $link_sitemaps, true)){
            $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$sitemap_table} WHERE `sitemap_type` = %s", $type));
            if(!empty($exists)){
                $results = $wpdb->update(
                    $sitemap_table,
                    [
                        'sitemap_content' => $sitemap, 
                        'sitemap_name' => $sitemap_name,
                    ],
                    [
                        'sitemap_type' => $type,
                    ]
                );
                $results = 1;

                if($return_id){
                    $map_id = $wpdb->get_var($wpdb->prepare("SELECT `sitemap_id` FROM {$sitemap_table} WHERE `sitemap_type` = %s ORDER BY `sitemap_id` DESC", $type));
                }
            }
        }

        if(false === $results){
            $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$sitemap_table} WHERE `sitemap_name` = %s", $sitemap_name));

            if(!empty($exists)){
                $results = $wpdb->update(
                    $sitemap_table,
                    [
                        'sitemap_content' => $sitemap, 
                    ],
                    [
                        'sitemap_name' => $sitemap_name,
                        'sitemap_type' => $type,
                    ]
                );

                if($return_id){
                    $map_id = $wpdb->get_var($wpdb->prepare("SELECT `sitemap_id` FROM {$sitemap_table} WHERE `sitemap_name` = %s ORDER BY `sitemap_id` DESC", $sitemap_name));
                }
            }else{
                $results = $wpdb->insert(
                    $sitemap_table,
                    [
                        'sitemap_content' => $sitemap, 
                        'sitemap_type' => $type,
                        'sitemap_name' => $sitemap_name,
                    ]
                );

                $map_id = $wpdb->insert_id;
            }
        }

        return ($return_id) ? $map_id: $results;
    }

    public static function update_sitemap(){

    }

    public static function delete_sitemap($id = 0, $type = ''){
        global $wpdb;
        $sitemap_table = $wpdb->prefix . 'wpil_sitemaps';
        $where = array();

        if(empty($id) && empty($type)){
            return false;
        }

        if(!empty($id)){
            $where['sitemap_id'] = (int)$id;
        }

        if(!empty($type)){
            if(self::is_valid_sitemap_type($type)){
                $where['sitemap_type'] = $type;
            }else{
                return false;
            }
        }


        $deleted = $wpdb->delete($sitemap_table, $where);

        return $deleted;
    }
    /*
        sitemap_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        sitemap_name text,
        sitemap_content longtext, 
        sitemap_type varchar,
    */

    /**
     * Gets and formats data for use in the Sitemap report page!
     **/
    public static function get_data(){
        global $wpdb;
        $sitemaps = self::get_all_sitemap_data();
        $links_table = $wpdb->prefix . "wpil_report_links";

        $post_cache = array();
        $map_data = array();
        $site_domain = wp_parse_url(get_home_url(), PHP_URL_HOST);
        $ignore_posts = Wpil_Settings::getIgnoreSitemapPosts();
        $ai_map_id = null;
        $inbound_map_id = null;
        $product_map_id = null;
        $custom_map_ids = array();

        if(!empty($sitemaps)){
            foreach($sitemaps as $map){
                $decompressed = Wpil_Toolbox::json_decompress($map->sitemap_content);
                $edge_listed = array();
                $edge_count = 0;

                if(!empty($decompressed)){
                    $map_data[$map->sitemap_id] = array('nodes' => array(), 'edges' => array(), 'type' => $map->sitemap_type);
                    $ai_map_id = ($map->sitemap_type === 'ai_sitemap'/*'ai_combined_sitemap'*/) ? $map->sitemap_id: $ai_map_id;
                    $inbound_map_id = ($map->sitemap_type === 'link_sitemap_inbound') ? $map->sitemap_id: $inbound_map_id;
                    $product_map_id = ($map->sitemap_type === 'ai_product_sitemap') ? $map->sitemap_id: $product_map_id;
                    if($map->sitemap_type === 'custom_sitemap'){
                        $custom_map_ids[] = $map->sitemap_id;
                    }
                    
                    $target_counter = array();
                    foreach($decompressed as $post_id => $dat){
                        $size = 3;
                        $not_domain = preg_match('/(post|term)_[0-9]+?/', $post_id, $m);
                        $is_home = false;

                        if(in_array($post_id, $ignore_posts)){
                            continue;
                        }

                        // if the 'post' is either a post or a term
                        if($not_domain){
                            if(!isset($post_cache[$post_id])){
                                $bits = explode('_', $post_id);
                                $post = new Wpil_Model_Post($bits[1], $bits[0]);
                                $post_cache[$post_id] = $post;
                            }else{
                                $post = $post_cache[$post_id];
                            }

                            $title = $post->getTitle();
                        }else{
                            // if the 'post' is actually a domain
                            $title = $post_id;
                            $is_home = (trim($site_domain) === trim($post_id)) ? true: false;
                        }

                        if(!empty($dat)){
                            foreach($dat as $d_id => $d){

                                if(in_array($d_id, $ignore_posts)){
                                    continue;
                                }

                                if(preg_match('/(post|term)_[0-9]+?/', $d_id, $m)){
                                    $bits = explode('_', $d_id);

                                    if($post->id > $bits[1]){
                                        $edge_id = ($post->type . '_' . $post->id) . '_' . $d_id;
                                    }else{
                                        $edge_id = $d_id . '_' . ($post->type . '_' . $post->id);
                                    }

                                    if(isset($edge_listed[$edge_id])){
                                        continue;
                                    }else{
                                        $edge_listed[$edge_id] = true;
                                    }
                                }

                                $type = ($map->sitemap_type === 'ai_sitemap'/* || $map->sitemap_type === 'ai_combined_sitemap'*/) ? 'line': 'arrow';

                                $map_data[$map->sitemap_id]['edges'][] = array('id' => 'e' . $edge_count, 'source' => $post_id, 'target' => $d_id, 'type' => $type);
                                $edge_count++;
                                
                                if($map->sitemap_type === 'link_sitemap_outbound'){
                                    if(!isset($target_counter[$post_id])){
                                        $target_counter[$post_id] = 1;
                                    }else{
                                        $target_counter[$post_id] += 1;
                                    }
                                }else{
                                    if(!isset($target_counter[$d_id])){
                                        $target_counter[$d_id] = 1;
                                    }else{
                                        $target_counter[$d_id] += 1;
                                    }
                                }

                                if(($map->sitemap_type === 'ai_sitemap'/* || $map->sitemap_type === 'ai_combined_sitemap'*/) && count($map_data[$map->sitemap_id]['edges']) % 2 === 0){
                                    break;
                                }
                            }
                        }

                        $map_data[$map->sitemap_id]['nodes'][] = array(
                            'id' => $post_id, 
                            'label' => addslashes(html_entity_decode($title)),
                            'size' => $size, 
                            'color' => (($not_domain || $is_home) ? '#33c7fd': '#7646b0'),
                            'external' => (($not_domain || $is_home) ? false: true)
                        );
                    }

                    // try getting the relative scale of the dots
                    $step = null;
                    if(is_array($target_counter) && !empty($target_counter)){
                        $max = (max($target_counter) > 12) ? max($target_counter): 12;
                        $min = min($target_counter);
                        $step = ($max - $min) * 0.05;
                    }

                    if(!empty($map_data[$map->sitemap_id]['nodes'])){
                        foreach($map_data[$map->sitemap_id]['nodes'] as &$node){
                            $county = isset($target_counter[$node['id']]) ? $target_counter[$node['id']]: 0;
                            $increase = ($county * 0.1);

                            if($county > 1){
                                if(empty($step)){
                                    if($county < 20){
                                        $increase += 2;
                                    }elseif($county < 30){
                                        $increase += 3;
                                    }elseif($county < 40){
                                        $increase += 4;
                                    }else{
                                        $increase += 5;
                                    }
                                }else{
                                    $increase = ceil($target_counter[$node['id']] / $step);
                                }
                            }

                            $node['size'] += $increase;
                        }
                    }
                }

                $edge_count = 0;
            }
        }
        // we're listing:
            // ai nodes with links
            // ai nodes without links
        // unrelated nodes with links and not related


        // if we have the sitemap data and inbound link data
        if( $ai_map_id !== null && !empty($map_data[$ai_map_id]) && 
            $inbound_map_id !== null && !empty($map_data[$inbound_map_id]))
        {
            // combine the data to create the AI Combined Sitemap
            // first tag the AI sitemap data as primary and map all of the existing edges by post id
            $ai_edges = array();
            $county = 0;
            foreach($map_data[$ai_map_id]['edges'] as $key => $dat){
                $map_data[$ai_map_id]['edges'][$key] = array_merge($dat, array('course' => 1, 'color' => '', 'size' => 2, 'class' => 'ai-base-edge')); // RED
                $id = $dat['source'] . '_' . $dat['target'];
                $ai_edges[$id] = true;
                $county = $dat['id']; // note the edge id so we don't have conflicts
            }
        
            // remove the "e" and just leave the index number
            $county = (false !== strpos($county, 'e')) ? (int) substr($county, 1): $county;

            // now go over the link map data
            // AND IDENTIFY ALL OF THE EXISTING LINKING RELATIONSHIPS
            $linking_edges = array();
            foreach($map_data[$inbound_map_id]['edges'] as $dat){
                $id = $dat['source'] . '_' . $dat['target'];
                $id2 = $dat['target'] . '_' . $dat['source'];

                // note the linking relationship
                $linking_edges[$id] = true;
                $linking_edges[$id2] = true;

                // if the linking relationship isn't in the AI sitemap
                if(!isset($ai_edges[$id])){
                    // skip to the next
                    continue;
                }

                $county++;

                // if there is a linking relationship and an AI relation, log the edge
                $map_data[$ai_map_id]['edges'][] = array_merge($dat, array('id' => 'e' . $county, 'course' => 2, 'color' => '#466c04', 'size' => 3, 'class' => 'ai-has-link')); // GREEN
            }

            // go over the AI map data one more time to create the edges for potential links
            // IE: NO LINKS CURRENTLY EXIST
            foreach($map_data[$ai_map_id]['edges'] as $key => $dat){
                $id = $dat['source'] . '_' . $dat['target'];
                if(isset($linking_edges[$id])){
                    // skip to the next
                    continue;
                }

                $county++;
                $map_data[$ai_map_id]['edges'][] = array_merge($dat, array('id' => 'e' . $county, 'course' => 3, 'color' => '#005ea2', 'size' => 3, 'class' => 'ai-no-link')); //purple
            }
        }

        // if we have the product map
        if($product_map_id !== null && !empty($map_data[$product_map_id])){
            // combine the data to create the AI Combined Sitemap
            // first tag the AI sitemap data as primary and map all of the existing edges by post id
            $product_edges = array();
            $county = 0;
            foreach($map_data[$product_map_id]['edges'] as $key => $dat){
                $map_data[$product_map_id]['edges'][$key] = array_merge($dat, array('course' => 1, 'color' => '', 'size' => 2, 'class' => 'ai-base-edge')); // RED
                $id = $dat['source'] . '_' . $dat['target'];
                $product_edges[$id] = true;
                $county = $dat['id']; // note the edge id so we don't have conflicts
            }
        
            // remove the "e" and just leave the index number
            $county = (false !== strpos($county, 'e')) ? (int) substr($county, 1): $county;

            // parse out all of the post & term ids
            $post_ids = array();
            $term_ids = array();
            foreach($map_data[$product_map_id]['nodes'] as $key => $dat){
                if(!$dat['external']){
                    $bits = explode('_', $dat['id']);
                    if(empty($bits) || !isset($bits[1])){
                        continue;
                    }
                    if(false !== strpos($dat['id'], 'post')){
                        $post_ids[$bits[1]] = true;
                    }else{
                        $term_ids[$bits[1]] = true;
                    }
                }
            }

            // if we have posts
            if(!empty($post_ids)){
                // pull the ids
                $post_ids = array_keys($post_ids);
                // implode them
                $post_ids = implode(',', $post_ids);
                // and find all of the links for the posts
                $links = $wpdb->get_results("SELECT `post_id`, `anchor` FROM {$links_table} WHERE `post_id` IN ({$post_ids}) AND `post_type` = 'post'");

                // if we have links
                if(!empty($links)){
                    // index them for faster searching
                    $link_index = array();
                    foreach($links as $key => $link){
                        $id = 'post_' . $link->post_id;
                        if(!isset($link_index[$id])){
                            $link_index[$id] = array();
                        }

                        if(!empty($link->anchor)){
                            $anchor = trim($link->anchor);
                            if(!empty($anchor)){
                                $link_index[$id][] = mb_strtolower($anchor);
                            }
                        }

                        unset($links[$key]);
                    }
                    // find out if the products are used in the links
                    $linking_edges = array();
                    foreach($map_data[$product_map_id]['edges'] as $dat){
                        $id = $dat['source'];
                        $product = $dat['target'];

                        // if the linking relationship isn't in the AI sitemap
                        if(!isset($link_index[$id]) || false === strpos($id, 'post_')){
                            //$county++;
                           // $map_data[$product_map_id]['edges'][] = array_merge($dat, array('id' => 'e' . $county, 'course' => 3, 'color' => '#005ea2', 'size' => 3, 'class' => 'ai-no-link')); //purple
                            // and skip to the next
                            continue;
                        }
        
                        // look over the links and seee if the product is present in an anchor
                        $has_product = false;
                        foreach($link_index[$id] as $anchor){
                            if(!empty($anchor) && false !== strpos($anchor, $product)){
                                $has_product = true;
                                break;
                            }
                        }

                        if(!$has_product){
                            $county++;
                            $map_data[$product_map_id]['edges'][] = array_merge($dat, array('id' => 'e' . $county, 'course' => 3, 'color' => '#005ea2', 'size' => 3, 'class' => 'ai-no-link')); //purple
                            continue;
                        }

                        $county++;
        
                        // if there is a linking relationship and an AI relation, log the edge
                        $map_data[$product_map_id]['edges'][] = array_merge($dat, array('id' => 'e' . $county, 'course' => 2, 'color' => '#466c04', 'size' => 3, 'class' => 'ai-has-link')); // GREEN
                    }
                }
            }

            // if we have terms
            if(!empty($term_ids)){
                // pull the ids
                $term_ids = array_keys($term_ids);
                // implode them
                $term_ids = implode(',', $term_ids);
                // and find all of the links for the terms
                $links = $wpdb->get_results("SELECT `post_id`, `anchor` FROM {$links_table} WHERE `post_id` IN ({$term_ids}) AND `post_type` = 'term'");

                // if we have links
                if(!empty($links)){
                    // index them for faster searching
                    $link_index = array();
                    foreach($links as $key => $link){
                        $id = 'term_' . $link->post_id;
                        if(!isset($link_index[$id])){
                            $link_index[$id] = array();
                        }

                        if(!empty($link->anchor)){
                            $anchor = trim($link->anchor);
                            if(!empty($anchor)){
                                $link_index[$id][] = mb_strtolower($anchor);
                            }
                        }

                        unset($links[$key]);
                    }
                    // find out if the products are used in the links
                    $linking_edges = array();
                    foreach($map_data[$product_map_id]['edges'] as $dat){
                        $id = $dat['source'];
                        $product = $dat['target'];

                        // if the linking relationship isn't in the AI sitemap
                        if(!isset($link_index[$id]) || false === strpos($id, 'term_')){
//                            $county++;
//                            $map_data[$product_map_id]['edges'][] = array_merge($dat, array('id' => 'e' . $county, 'course' => 3, 'color' => '#005ea2', 'size' => 3, 'class' => 'ai-no-link')); //purple
                            // and skip to the next
                            continue;
                        }
        
                        // look over the links and seee if the product is present in an anchor
                        $has_product = false;
                        foreach($link_index[$id] as $anchor){
                            if(!empty($anchor) && false !== strpos($anchor, $product)){
                                $has_product = true;
                                break;
                            }
                        }

                        if(!$has_product){
                            $county++;
                            $map_data[$product_map_id]['edges'][] = array_merge($dat, array('id' => 'e' . $county, 'course' => 3, 'color' => '#005ea2', 'size' => 3, 'class' => 'ai-no-link')); //purple
                            continue;
                        }

                        $county++;
        
                        // if there is a linking relationship and an AI relation, log the edge
                        $map_data[$product_map_id]['edges'][] = array_merge($dat, array('id' => 'e' . $county, 'course' => 2, 'color' => '#466c04', 'size' => 3, 'class' => 'ai-has-link')); // GREEN
                    }
                }
            }

        }

        // if we have custom maps and the inbound map
        if(!empty($custom_map_ids) && $inbound_map_id !== null && !empty($map_data[$inbound_map_id])){
            // first tag the external nodes so we can pick them out later
            foreach($custom_map_ids as $map_id){
                $external = array();
                foreach($map_data[$map_id]['nodes'] as $key => $dat){
                    if($dat['external']){
                        $external[$dat['id']] = true;
                    }
                }

                // then tag the base sitemap data as primary and map all of the existing edges by post id
                $base_edges = array();
                $county = 0;
                foreach($map_data[$map_id]['edges'] as $key => $dat){
                    $map_data[$map_id]['edges'][$key] = array_merge($dat, array('course' => 1, 'color' => '', 'size' => 2, 'class' => 'ai-base-edge')); // RED
                    $id = $dat['source'] . '_' . $dat['target'];
                    $base_edges[$id] = true;
                    $county = $dat['id']; // note the edge id so we don't have conflicts
                }
            
                // remove the "e" and just leave the index number
                $county = (false !== strpos($county, 'e')) ? (int) substr($county, 1): $county;

                // now go over the link map data
                // AND IDENTIFY ALL OF THE EXISTING LINKING RELATIONSHIPS
                $linking_edges = array();
                foreach($map_data[$inbound_map_id]['edges'] as $dat){
                    $id = $dat['source'] . '_' . $dat['target'];
                    $id2 = $dat['target'] . '_' . $dat['source'];

                    // note the linking relationship
                    $linking_edges[$id] = true;
                    $linking_edges[$id2] = true;

                    // if the linking relationship isn't in the sitemap
                    if(!isset($base_edges[$id])){
                        // skip to the next
                        continue;
                    }

                    $county++;

                    // if there is a linking relationship and an AI relation, log the edge
                    $map_data[$map_id]['edges'][] = array_merge($dat, array('id' => 'e' . $county, 'course' => 2, 'color' => '#466c04', 'size' => 3, 'class' => 'ai-has-link')); // GREEN
                }

                // go over the map data one more time to create the edges for potential links
                // IE: NO LINKS CURRENTLY EXIST
                foreach($map_data[$map_id]['edges'] as $key => $dat){
                    $id = $dat['source'] . '_' . $dat['target'];
                    if(isset($linking_edges[$id]) || isset($external[$dat['source']]) || isset($external[$dat['target']])){
                        // skip to the next
                        continue;
                    }

                    $county++;
                    $map_data[$map_id]['edges'][] = array_merge($dat, array('id' => 'e' . $county, 'course' => 3, 'color' => '#005ea2', 'size' => 3, 'class' => 'ai-no-link')); //purple
                }
            }
        }

        return $map_data;
    }

    /**
     * Creates the sitemap based on link data
     **/
    public static function create_link_sitemap($type = 'link_sitemap_all'){
        global $wpdb;
        $link_table = $wpdb->prefix . 'wpil_report_links';

        // setup the calculation
        $calculated = array();

        // pull all the posts in the report table
        $posts = $wpdb->get_results("SELECT `post_id`, `post_type` FROM {$link_table} GROUP BY `post_id`, `post_type`");

        // go over all the posts and create all of the data indexes
        foreach($posts as $post){
            if(empty($post->post_id)){
                continue;
            }

            $id = $post->post_type . '_' . $post->post_id;

            if(!isset($calculated[$id])){
                $calculated[$id] = array();
            }
        }

        switch($type){
            case 'link_sitemap_inbound':
            case 'link_sitemap_outbound':
                // pull the link data for all the internal posts
                $calculated = self::calculate_internal_linking_data();

                break;
            case 'link_sitemap_external':
                // pull the external link data
                $data = $wpdb->get_results("SELECT `post_id`, `post_type`, `host` FROM {$link_table} WHERE `internal` = 0");

                foreach($data as $dat){
                    if(!empty($dat->host)){
                        $id = $dat->post_type . '_' . $dat->post_id;
                        $t_id = $dat->host;
        
                        // the argument layout is source -> target -> similarity score 
                        // Since this is linking, we just go with 1 as a placeholder for the score
                        // The `target` count sets how big the dot is in the chart
                        if(!isset($calculated[$id])){
                            $calculated[$id] = array();
                        }
                        if(!isset($calculated[$t_id])){
                            $calculated[$t_id] = array();
                        }

                        $calculated[$id][$t_id] = 1;
                    }
                }

                break;
            case 'link_sitemap_all':
            default:
                // pull the all link data
                $data = $wpdb->get_results("SELECT `post_id`, `post_type`, `target_id`, `target_type`, `host`, `internal` FROM {$link_table} WHERE NOT ISNULL(`internal`)");
                
                foreach($data as $dat){
                    if(!empty($dat->host) && empty($dat->internal)){
                        $id = $dat->post_type . '_' . $dat->post_id;
                        $t_id = $dat->host;

                        if(!isset($calculated[$id])){
                            $calculated[$id] = array();
                        }

                        if(!isset($calculated[$t_id])){
                            $calculated[$t_id] = array();
                        }

                        $calculated[$id][$t_id] = 1;
                    }elseif(!empty($dat->target_id)){
                        $id = $dat->post_type . '_' . $dat->post_id;
                        $t_id = $dat->target_type . '_' . $dat->target_id;
        
                        if(!isset($calculated[$id])){
                            $calculated[$id] = array();
                        }

                        if(!isset($calculated[$t_id])){
                            $calculated[$t_id] = array();
                        }

                        $calculated[$id][$t_id] = 1;
                    }
                }
                break;
        }

        // if we have calculated linking relationships
        if(!empty($calculated)){
            // go over the results and make sure all of the targets have a destination
            foreach($calculated as $src => $dat){
                foreach($dat as $s => $d){
                    if(!isset($calculated[$s])){
                        $calculated[$s] = array();
                    }
                }
            }
        }

        return $calculated;
    }

    public static function calculate_internal_linking_data(){
        global $wpdb;
        $link_table = $wpdb->prefix . 'wpil_report_links';

        // setup the calculation
        $calculated = array();

        // pull the link data for all the internal posts
        $data = $wpdb->get_results("SELECT `post_id`, `post_type`, `target_id`, `target_type` FROM {$link_table} WHERE `internal` = 1");

        foreach($data as $dat){
            if(!empty($dat->target_id)){
                $id = $dat->post_type . '_' . $dat->post_id;
                $t_id = $dat->target_type . '_' . $dat->target_id;

                if(!isset($calculated[$id])){
                    $calculated[$id] = array();
                }
                if(!isset($calculated[$t_id])){
                    $calculated[$t_id] = array();
                }

                // the argument layout is source -> target -> similarity score 
                // Since this is linking, we just go with 1 as a placeholder for the score
                // The `target` count sets how big the dot is in the chart
                $calculated[$id][$t_id] = 1;
            }
        }

        return $calculated;
    }

    public static function is_valid_sitemap_type($type = ''){
        if(empty($type) || !is_string($type)){
            return false;
        }

        $sitemap_types = array(
            'ai_sitemap',
//            'ai_combined_sitemap',
            'ai_product_sitemap',
            'link_sitemap_inbound',
            'link_sitemap_outbound',
            'link_sitemap_external',
            'link_sitemap_all',
            'custom_sitemap'
        );

        return in_array($type, $sitemap_types, true);
    }

    /**
     * Checcks to see if a specific sitemap exists
     **/
    public static function has_sitemap($sitemap_type = ''){
        global $wpdb;
        $table = $wpdb->prefix . 'wpil_sitemaps';

        if(empty($sitemap_type)){
            return false;
        }

        if(empty(self::$existing_sitemaps)){
            $sitemaps = $wpdb->get_col("SELECT `sitemap_type` FROM {$table}");

            if(!empty($sitemaps)){
                self::$existing_sitemaps = array_flip(array_flip($sitemaps));
            }else{
                self::$existing_sitemaps = array('no-sitemaps');
            }
        }

        return (!empty(self::$existing_sitemaps) && in_array($sitemap_type, self::$existing_sitemaps, true)) ? true: false;
    }

    /**
     * Creates a basic map of the inbound links so we can check and see who's linked to who
     **/
    public static function get_inbound_linked_references(){
        global $wpdb;
        $table = $wpdb->prefix . 'wpil_report_links';

        $links = array();
        $link_references = $wpdb->get_results("SELECT `post_id`, `post_type`, `target_id`, `target_type` FROM {$table} WHERE `internal` = 1");

        if(!empty($link_references)){
            foreach($link_references as $dat){
                $source_id = $dat->post_type . '_' . $dat->post_id;
                $target_id = $dat->target_type . '_' . $dat->target_id;

                if(!isset($links[$source_id])){
                    $links[$source_id] = array();
                }

                $links[$source_id][$target_id] = true;
            }
        }

        return $links;
    }
}
