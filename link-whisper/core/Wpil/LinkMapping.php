<?php

/**
 * Work with links and mapps them all over the site!
 * Speciifcally, thsi is what does all the mass linking actions on the site.
 */
class Wpil_LinkMapping
{
    const RELATION_SCOPE_DEFAULT = 'default';
    const RELATION_SCOPE_OUTBOUND = 'outbound';
    const RELATION_SCOPE_INBOUND = 'inbound';
    const RELATION_AI_CLAIM_MARKER = 'ai-claim';
    const RELATION_AI_CLAIM_TTL = 300;
    const RELATION_RUNTIME_KEY = '_queue_runtime';

    private static $money_page_term_ids = null;
    private static $fix_special_options_cache = array();
    private static $post_type_id_cache = array();
    private static $post_category_cache = array();
    private static $relationship_item_filter_cache = array();
    private static $relation_map_seed_cache = array();
    private static $redirect_hidden_post_ids = null;
    static $url_redirect_cache = array();
    static $cleaned_url_redirect_cache = array();
    static $expanded_mapping = false;

    /**
     * Register services
     */
    public function register()
    {

    }

    /**
     * Creates and cleans the mapping table
     **/
    public static function prepare_table($truncate = false){
        global $wpdb;
        $table = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}wpil_link_mapping'");
        if ($table != $wpdb->prefix . 'wpil_link_mapping') {
            $wpil_link_table_query = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wpil_link_mapping (
                                    id int(10) unsigned NOT NULL AUTO_INCREMENT,
                                    map_date bigint(20) unsigned NOT NULL,
                                    map_name varchar(128),
                                    map_data longtext DEFAULT NULL,
                                    process_key varchar(128) DEFAULT NULL,
                                    item_count int(10) DEFAULT 0,
                                    last_index varchar(16) DEFAULT NULL,
                                    PRIMARY KEY  (id),
                                    INDEX (process_key)
                                ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";

            // create DB table if it doesn't exist
            require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($wpil_link_table_query);
        }

        if($truncate){
            $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}wpil_link_mapping");
        }

        $table = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}wpil_relation_mapping'");
        if ($table != $wpdb->prefix . 'wpil_relation_mapping') {
            $wpil_relation_mapp_table_query = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wpil_relation_mapping (
                                    id int(10) unsigned NOT NULL AUTO_INCREMENT,
                                    map_date bigint(20) unsigned NOT NULL,
                                    map_data longtext DEFAULT NULL,
                                    process_key varchar(128) NOT NULL DEFAULT '',
                                    work_scope varchar(24) NOT NULL DEFAULT 'default',
                                    post_id bigint(20) unsigned NOT NULL DEFAULT 0,
                                    post_type varchar(24) NOT NULL DEFAULT '',
                                    is_pillar tinyint(1) NOT NULL DEFAULT 0,
                                    item_processed tinyint(1) NOT NULL DEFAULT 0,
                                    ai_processed tinyint(1) NOT NULL DEFAULT 0,
                                    pillar_processed tinyint(1) DEFAULT 0,
                                    map_processed tinyint(1) DEFAULT 0,
                                    item_count int(10) DEFAULT 0,
                                    last_index varchar(16) DEFAULT NULL,
                                    PRIMARY KEY  (id),
                                    UNIQUE KEY process_key_scope_post_id_type (process_key, work_scope, post_id, post_type),
                                    KEY process_key_scope_queue (process_key, work_scope, item_processed, ai_processed, is_pillar, id),
                                    KEY process_key_item_processed (process_key, item_processed)
                                ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";

            // create DB table if it doesn't exist
            require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($wpil_relation_mapp_table_query);
        }
    }
    
    public static function get_map($map_name = false, $process_key = false, $map_id = 0, $decode = true){
        global $wpdb;
        $table = $wpdb->prefix . 'wpil_link_mapping';
        $map = array();
        $map_data = array();

        if(!empty($map_name) && is_string($map_name)){
            $map_data = $wpdb->get_col($wpdb->prepare("SELECT `map_data` FROM {$table} WHERE `map_name` = %s", $map_name));
        }elseif(!empty($process_key) && is_string($process_key)){
            $map_data = $wpdb->get_col($wpdb->prepare("SELECT `map_data` FROM {$table} WHERE `process_key` = %s", $process_key));
        }elseif(!empty($map_id)){
            $map_data = $wpdb->get_col($wpdb->prepare("SELECT `map_data` FROM {$table} WHERE `id` = %d", $map_id));
        }

        if(!empty($map_data)){
            if($decode){
                foreach($map_data as $dat){
                    $decoded = Wpil_Toolbox::json_decompress($dat);
                    if(!empty($decoded)){
                        $map = array_merge($map, $decoded);
                    }
                }
            }else{
                $map = $map_data;
            }
        }

        return $map;
    }

    /**
     * Create the map step in the database
     **/
    public static function create_map_step($map_name = false, $process_key = false){
        global $wpdb;
        $table = $wpdb->prefix . 'wpil_link_mapping';
        $map_data = array(
            'map_date' => time(),
            'map_name' => $map_name,
            'map_data' => Wpil_Toolbox::json_compress([]),
            'process_key' => $process_key,
            'item_count' => 0,
        );

        $wpdb->insert($table, $map_data);

        return self::get_map_step(false, $wpdb->insert_id);
    }

    /**
     * Get the current map step from the database
     **/
    public static function get_map_step($process_key = false, $id = false, $decode = true){
        global $wpdb;
        $table = $wpdb->prefix . 'wpil_link_mapping';
        $map_data = array();

        if(!empty($process_key) && is_string($process_key)){
            $map_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE `process_key` = %s AND item_count < 5000 ORDER BY `id` DESC", $process_key));
        }elseif(!empty($id)){
            $map_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE `id` = %d", $id));
        }

        if(!empty($map_data)){
            if($decode){
                $decoded = Wpil_Toolbox::json_decompress($map_data->map_data);
                if($decoded !== false && $decoded !== ''){
                    $map_data->map_data = $decoded;
                }else{
                    $map_data->map_data = array();
                }
            }
        }

        return $map_data;
    }

    /**
     * @param object $step
     **/
    public static function save_map_step($step = array()){
        global $wpdb;
        $table = $wpdb->prefix . 'wpil_link_mapping';

        if(empty($step)){
            return false;
        }

        $save_data = (array)$step;
        if(is_array($save_data['map_data'])){
            $save_data['map_data'] = Wpil_Toolbox::json_compress($save_data['map_data']);
        }

        return !empty($wpdb->update($table, $save_data, ['id' => $step->id]));
    }

    public static function build_schema($map_name = false, $process_key = false){
        global $wpdb;

        $map = self::get_map($map_name);

    }
    // create database tables
    // create ajax schema builder
    // create ajax-powered schema importer
    // create schema builder
    // create schema updater
    // create schema deletor
    // create schema validator
    // create whatever will be processing the links and updating posts based on schema

    // schema options: relative links, more...

    // we're assuming it's post id since terms are kinda iffy
    public static function get_highest_id_in_map($map = array()){
        if(empty($map) || !is_array($map)){
            return false;
        }

        $highest = 0;
        foreach($map as $post){
            $post_data = json_decode($post);
            if(empty($post_data)){
                continue;
            }
            $bits = explode('_', $post_data->pid);
            if(!empty($bits) && $bits[1] > $highest){
                $highest = $bits[1];
            }
        }

        return $highest;
    }

    /**
     * This takes a snapshot of the site's current link map so we can rollback/undo/export the current state of the site.
     * Will someday rely on the link_report table rather than scanning all the posts live
     **/
    public static function take_snapshot($map_name = false, $process_key = false){
        if(empty($process_key) || !is_string($process_key)){
            return array();
        }

        // get the current posts to process...
        $id_list = get_option('wpil_link_map_snapshot_' . $process_key, 'new-batch');

        // if there's no posts...
        if($id_list === 'new-batch'){
            $id_list = [];

            // get some!
            $post_ids = Wpil_Report::get_all_post_ids();

            if(!empty($post_ids)){
                foreach($post_ids as $id){
                    $id_list[] = 'post_' . $id;
                }
            }

            $term_ids = Wpil_Report::get_all_term_ids();
            if(!empty($term_ids)){
                foreach($term_ids as $id){
                    $id_list[] = 'term_' . $id;
                }
            }

            update_option('wpil_link_map_snapshot_' . $process_key, $id_list);
        }

        // get the current mapp step
        $step = self::get_map_step($process_key);

        // if there's no step..
        if(empty($step)){
            // create a new one
            $step = self::create_map_step($map_name, $process_key);
        }

        // go over the posts
        $county = 0;
        foreach($id_list as $ind => $id){
            // run this for as long as reasonably safe
            if(Wpil_Base::overTimeLimit(15)){
                break;
            }

            $bits = explode('_', $id);
            $post = new Wpil_Model_Post($bits[1], $bits[0]);
            $links = self::build_item_link_object($post);
            $step->map_data[] = $links;
            $step->item_count = count($step->map_data);
            $step->last_index = $id;
            $county++;

            // if we've got at least 5k items
            if($step->item_count > 4999){
                // save the current step
                self::save_map_step($step);
                // and start a new one
                $step = self::create_map_step($map_name, $process_key);
                // and reset the counter
                $county = 0;
            }elseif($county % 100 === 0){ // if we've passed a step
                // save our progress
                self::save_map_step($step);
            }

            unset($id_list[$ind]);
            update_option('wpil_link_map_snapshot_' . $process_key, $id_list);
        }

        // save the step before existing...
        self::save_map_step($step);

        return empty($id_list);
    }

    /**
     * Creates the JSON link object for a specific post.
     * This is the stuff maps are made of...
     *
     *  Object is
     *  {
     *      pid,
     *      [
     *          [anchor => '', url => '', context => '']
     *      ]
     *  } 
     **/
    public static function build_item_link_object($post = false, $use_table = false){
        global $wpdb;
        $table = $wpdb->prefix . 'wpil_report_links';
        
        if(empty($post) || !is_a($post, 'Wpil_Model_Post')){
            return [];
        }
        $object = array(
            'id' => $post->get_pid(),
            'data' => []
        );

        if($use_table){ // TODO: if we ever incorporate page_context, use the link table...
            $links = $wpdb->get_results($wpdb->prepare("SELECT `raw_url`, `anchor`, `raw_anchor` FROM {$table} WHERE `post_id` = %d AND `post_type` = %s", $post->id, $post->type), ARRAY_A);
        }else{
            // first analyse the post for links
            $links = Wpil_Report::getContentLinks($post, false, '', true);
        }

        if(!empty($links)){
            foreach($links as $link){
                if(!empty($link->url) && !empty($link->anchor)){
                    $object['data'][] = [
                        'anchor' => !empty($link->raw_anchor) ? base64_encode($link->raw_anchor): base64_encode($link->anchor), 
                        'url' => base64_encode($link->url),
                        'tracking_id' => $link->tracking_id,
                        'context' => base64_encode($link->page_context) // to be safe...
                    ];
                }
            }
        }

        return $object;
    }

    /**
     * Inserts the links from a map without removing any existing ones
     **/
    public static function insert_links_from_map(){

    }

    /**
     * Removes all existing links and replaces them with ones from the map.
     * UNCRITICALLY BLEACHES THE EXISTING LINKS AND REPLACES THEM WITH THE MAPP'S
     **/
    public static function replace_with_links_from_map($map_name = '', $process_key = ''){
        $map = self::get_map($map_name, $process_key);

        if(empty($map)){
            return true;
        }

        // get the list of processed posts for the mapp since we're probably going to do this in batches!
        $processed_post_list = get_transient('wpil_map_replace_links_id_list_' . $process_key);
        if(empty($processed_post_list)){
            $processed_post_list = array();
        }

        // get down to business!
        foreach($map as $data){
            // if we've already processed this post!
            if(isset($processed_post_list[$data->id])){
                // skip to the next!
                continue;
            }

            // create a post!
            $bits = explode('_', $data->id);
            $post = new Wpil_Model_Post($bits[1], $bits[0]);
            // see if it exists!
            if(!$post->check_if_post_exists()){
                // if it doesn't, check it off and proceed!
                $processed_post_list[$data->id] = true;
                continue;
            }

            // clean all the links off the post!
            //Wpil_Link::delete_all_post_links($post);
            
            // get the current content!
            $content = $post->getCleanContent();
            $excerpt = $post->maybeGetExcerpt();

            // put new ones on it!
            if(isset($data->data) && !empty($data->data)){
                $meta = [];
                foreach($data->data as $dat){
                    $context = base64_decode($dat->context);
                    $sentence = strip_tags(base64_decode($dat->context));
                    Wpil_Post::insertLink($content, $sentence, $context);
                    if(!empty($excerpt)){
                        Wpil_Post::insertLink($excerpt, $sentence, $context);
                    }

                    $meta[] = array(
                        'post_origin' => 'internal',
                        'site_url' => '',
                        'sentence' => $sentence,
                        'sentence_with_anchor' => $context,
                        'custom_sentence' => $context
                    );
                }
            }

            // save!
            $post->updateContent($content, $excerpt);

            if($post->type === 'post'){
                Wpil_Post::editors('addLinks', [$meta, $post->id, &$content]);
            }

            if (WPIL_STATUS_LINK_TABLE_EXISTS){
                Wpil_Report::update_post_in_link_table($post);
            }

            // if we're supposed to update the link stats so they reflect the newly removed link
            if(false){
                // update this post's stats
                Wpil_Report::statUpdate($post); // ehhh
            }

            // and update the list of processed posts!
        }


    }
/*
on the one hand, we could strip all the links and then replacce...
That would require us to create a full mapp and then populate the post
    That wouldn't be so bad would it? Then we could load it into the sitemapping and view it live too
    Also, what are we saving? Probably just time and space
On the flip side, the more minimal approach of updating links has the advantage of not nuking the links if something goes wrong
    We can just do a check to see if links are inserted, if they aren't don't save the post
Ok when it comes to settings, how do we handle the create/update/delete options?
    Just merge the existing links with the mapp
        If we're just doing inserts... Or better still! Don't scrub the post if all we're doing is inserting!
        If we're going to update, we'll run the map through a separeate process that checks if hte post currently has a version of the link based on the context
            If it does, send it thorugh the link updater
        And if we have full control, nuke the post and insert the links.
*/

    /**
     * Collects the raw relation signals for a post: embedding scores, keyword scores, and same-term post IDs.
     * Keeping these in the map allows future re-processing without re-hitting the DB.
     **/
    private static function collect_post_signals($post, $limit, $process_key = ''){
        $signals = [
            'embeddings'      => [],
            'keyword_scoring' => [],
            'same_terms'      => [],
        ];

        $relations = Wpil_AI::get_calculated_embedding_data($post->id, $post->type);
        if(!empty($relations) && isset($relations->calculation)){
            $relations = Wpil_Toolbox::json_decompress($relations->calculation, true);
            if(!empty($relations)){
                arsort($relations);
                foreach($relations as $key => $score){
                    // skip any that are at 1.0000 since those aren't significant
                    if($score > 0.99){ continue; }
                    // exit if we're hitting posts under the threshold
                    if($score < $limit){ break; }
                    if(!self::is_allowed_term_pid($key, $process_key)){ continue; }
                    if(!self::is_allowed_relationship_pid($key)){ continue; }
                    $signals['embeddings'][$key] = $score;
                }
            }
        }

        if($post->type === 'post'){
            $keyword_relations = Wpil_TargetKeyword::calculate_keyword_relations($post->id);
            if(!empty($keyword_relations)){
                foreach($keyword_relations as $pid => $score){
                    if(!self::is_allowed_relationship_pid($pid)){ continue; }
                    $signals['keyword_scoring'][$pid] = $score;
                }
            }

            $same_term_posts = Wpil_Toolbox::get_most_term_connected_posts($post, 25);
            if(!empty($same_term_posts)){
                foreach($same_term_posts as $id => $score){
                    $candidate_pid = 'post_' . (int) $id;
                    if(!self::is_allowed_relationship_pid($candidate_pid)){ continue; }
                    $signals['same_terms'][(int) $id] = $score;
                }
            }
        }

        return $signals;
    }

    /**
     *
     **/
    public static function build_relationship_map($process_key = ''){
        if(empty($process_key)){
            return false;
        }

        if(!Wpil_AI::has_completed_post_embeddings()){
            // make sure the embeddings are set
            Wpil_AI::create_site_embeddings();
            // if there are no embeddings currently being processed and we still have time
            $has_completed_embeddings = Wpil_AI::has_completed_post_embedding_calculations();
            if(!Wpil_Base::overTimeLimit(5, 35) && Wpil_AI::has_completed_post_embeddings() && !$has_completed_embeddings){
                Wpil_AI::stepped_calculate_post_embeddings();
            }

            return false; // if we're heere, go around for another pass
        }

        // set a limmit for processing
        $limit = 0.55;

        if(self::get_relation_map_total_item_count($process_key) < 1){
            self::seed_relation_map_process($process_key);
        }

        while(!Wpil_Base::overTimeLimit(5, 35)){
            $next = self::get_next_unprocessed_relation_map_row($process_key);
            if(empty($next)){
                break;
            }
            self::process_relation_map_row($process_key, $next, $limit, !empty($next->is_pillar));
        }

        return !Wpil_Base::overTimeLimit(5, 35);
    }

    /**
     * @param $post current wpil_model_post post to judge for
     **/
    public static function judge_map_item_relations($post, $data = [], $process_key = '', $target_is_source_post = false){
        /**
         * Reference object
         * array(
                'pillar_content' => true,
                'embeddings' => [],
                'keyword_scoring' => [],
                'same_terms' => [],
                'related_posts' => []
            )
         *
         **/

        $related_posts = [];
        $seen          = []; // tracks PIDs already added so we never add duplicates across signal sources
        $inbound_link_limit = (int)get_option('wpil_max_inbound_links_per_post', 0);
        $outbound_link_limit = (int)get_option('wpil_max_links_per_post', 0);
        $likely_inbound = isset($data['pillar_content']) || md5('orphan-post-search') === $process_key;
        $max_relations = 8;
        
        // if we're processing inbound type links
        if($likely_inbound){
            $base_relations = isset($data['pillar_content']) ? max($inbound_link_limit, 6) * 2: max($inbound_link_limit, 6);
            $max_relations = max($base_relations, 24);
        }else{
            $max_relations = max($outbound_link_limit, 8);
        }

        if(get_option('wpil_prevent_two_way_linking', false)){
            $current_links = Wpil_Post::getLinkedPostIDs($post, true);
        }else{
            $report_links = Wpil_Report::getReportOutboundLinks($post);
            $current_links = array($post->id);

            if(!empty($report_links['internal'])){
                foreach($report_links['internal'] as $dat){
                    if(empty($dat->post) || empty($dat->post->id)){
                        continue;
                    }

                    $current_links[] = (int) $dat->post->id;
                }
            }
        }

        $post_language = Wpil_Post::getPostLanguageCode($post);

        $current_link_lookup = array();
        if(!empty($current_links)){
            foreach($current_links as $linked_id){
                $current_link_lookup[(int) $linked_id] = true;
            }
        }

        $candidate_pids = [];
        if(!empty($data['embeddings'])){
            $candidate_pids = array_merge($candidate_pids, array_keys($data['embeddings']));
        }
        if(!empty($data['keyword_scoring'])){
            $candidate_pids = array_merge($candidate_pids, array_keys($data['keyword_scoring']));
        }
        if(!empty($data['same_terms'])){
            foreach(array_keys($data['same_terms']) as $id){
                $candidate_pids[] = 'post_' . (int) $id;
            }
        }

        $candidate_pids = array_values(array_unique($candidate_pids));
        $scored_candidates = [];
        foreach($candidate_pids as $pid){
            $bits = self::parse_pid($pid);
            $id   = $bits['id'];

            if(
                empty($id) ||
                isset($seen[$pid]) ||
                !self::is_allowed_term_pid($pid, $process_key) ||
                !self::is_allowed_relationship_pid($pid) ||
                !self::is_allowed_related_pid_for_fix_options($post, $pid, $data, $process_key, $target_is_source_post) ||
                ($likely_inbound && !self::is_pid_within_ai_processing_age($pid))
            ){
                continue;
            }

            if(isset($current_link_lookup[$id])){
                continue;
            }

            $tp = new Wpil_Model_Post($id, $bits['type']);
            if($post_language !== Wpil_Post::getPostLanguageCode($tp)){
                continue;
            }

            if($likely_inbound && Wpil_Link::at_max_outbound_links($tp, true)){
                continue;
            }

            $candidate_data = self::score_relation_candidate($post, $tp, $pid, $data);
            if(empty($candidate_data) || !self::candidate_meets_relation_threshold($candidate_data)){
                continue;
            }

            $scored_candidates[$pid] = $candidate_data;
        }

        if(empty($scored_candidates) && !empty($data['same_terms'])){ // same-term data only
            foreach(array_keys($data['same_terms']) as $id){
                if(count($related_posts) >= $max_relations){
                    break;
                }

                $candidate_pid = 'post_' . (int) $id;
                if(
                    isset($seen[$candidate_pid]) ||
                    isset($current_link_lookup[(int) $id]) ||
                    !self::is_allowed_relationship_pid($candidate_pid) ||
                    ($likely_inbound && !self::is_pid_within_ai_processing_age($candidate_pid))
                ){
                    continue;
                }

                $tp = new Wpil_Model_Post($id);
                if($post_language !== Wpil_Post::getPostLanguageCode($tp)){
                    continue;
                }

                if(!self::is_allowed_related_pid_for_fix_options($post, $candidate_pid, $data, $process_key, $target_is_source_post)){
                    continue;
                }

                if($likely_inbound && Wpil_Link::at_max_outbound_links($tp, true)){
                    continue;
                }

                $candidate_data = self::score_relation_candidate($post, $tp, $candidate_pid, $data);
                if(empty($candidate_data)){
                    continue;
                }

                $scored_candidates[$candidate_pid] = $candidate_data;
            }
        }

        if(empty($scored_candidates)){ // if we don't have any of this data!
            return $related_posts;
        }

        uasort($scored_candidates, function($a, $b){
            if($a['score'] === $b['score']){
                if($a['signal_count'] === $b['signal_count']){
                    return strcmp($a['pid'], $b['pid']);
                }

                return ($a['signal_count'] > $b['signal_count']) ? -1 : 1;
            }

            return ($a['score'] > $b['score']) ? -1 : 1;
        });

        foreach($scored_candidates as $pid => $candidate_data){
            if(count($related_posts) >= $max_relations){
                break;
            }

            $seen[$pid] = true;
            $related_posts[$pid] = $candidate_data;
        }

        return $related_posts;
    }

    private static function score_relation_candidate($source_post, $candidate_post, $pid = '', $data = []){
        if(empty($source_post) || empty($candidate_post) || empty($pid)){
            return [];
        }

        $parts = self::parse_pid($pid);
        if(empty($parts['id'])){
            return [];
        }

        $embedding_score = 0.0;
        $keyword_score = 0.0;
        $term_score = 0.0;
        $signal_count = 0;
        $reasons = [];
        $boosts = [];
        $score = 0.0;

        if(isset($data['embeddings'][$pid])){
            $embedding_score = (float) $data['embeddings'][$pid];
            $score += ($embedding_score * 0.65);
            $signal_count++;
            $reasons[] = 'embedding';
        }

        if(isset($data['keyword_scoring'][$pid])){
            $keyword_score = (float) $data['keyword_scoring'][$pid];
            $score += ($keyword_score * 0.25);
            $signal_count++;
            $reasons[] = 'keyword';
        }

        if($parts['type'] === 'post' && !empty($data['same_terms']) && isset($data['same_terms'][$parts['id']])){
            $max_shared_terms = max(array_map('intval', $data['same_terms']));
            if($max_shared_terms > 0){
                $term_score = ((int) $data['same_terms'][$parts['id']]) / $max_shared_terms;
            }
            $score += ($term_score * 0.10);
            $signal_count++;
            $reasons[] = 'shared_terms';
        }

        if($source_post->type === $candidate_post->type){
            $score += 0.02;
            $boosts[] = 'same_type';
        }

        if(!empty($data['pillar_content'])){
            $score += 0.03;
            $boosts[] = 'pillar_source';
        }

        $score = min(0.9999, round($score, 4));

        return [
            'pid' => $pid,
            'score' => $score,
            'signal_count' => $signal_count,
            'embedding_score' => round($embedding_score, 4),
            'keyword_score' => round($keyword_score, 4),
            'term_score' => round($term_score, 4),
            'boosts' => $boosts,
            'reasons' => $reasons,
            'source_type' => $source_post->type,
            'candidate_type' => $candidate_post->type,
        ];
    }

    private static function candidate_meets_relation_threshold($candidate = []){
        if(empty($candidate) || empty($candidate['pid'])){
            return false;
        }

        $embedding_score = isset($candidate['embedding_score']) ? (float) $candidate['embedding_score'] : 0.0;
        $keyword_score = isset($candidate['keyword_score']) ? (float) $candidate['keyword_score'] : 0.0;
        $term_score = isset($candidate['term_score']) ? (float) $candidate['term_score'] : 0.0;
        $score = isset($candidate['score']) ? (float) $candidate['score'] : 0.0;

        if($embedding_score >= 0.50){
            return true;
        }

        if($embedding_score >= 0.45 && $keyword_score > 0){
            return true;
        }

        if($embedding_score >= 0.40 && $term_score > 0){
            return true;
        }

        if($keyword_score >= 0.50){
            return true;
        }

        if($keyword_score >= 0.35 && $term_score > 0){
            return true;
        }

        if($term_score >= 0.75 && $score >= 0.10){
            return true;
        }

        return ($score >= 0.38 && !empty($candidate['signal_count']));
    }

    /**
     * Gets all the posts that we'll be processing with the ai linking
     **/
    public static function get_processable_posts($process_key = ''){
        $posts = Wpil_Report::get_all_post_ids('ai');
        $term_ids = Wpil_Settings::get_money_page_term_ids();
        $fix_special_options = self::get_fix_special_options($process_key);
        if(!empty(get_option('wpil_ai_process_all_terms', '')) || !empty($fix_special_options['link_from_category_pages'])){
            $all_term_ids = Wpil_Report::get_all_term_ids();
            $term_ids = (!empty($all_term_ids)) ? array_merge($term_ids, $all_term_ids): $term_ids;
        }

        $ids = array();
        if(!empty($posts)){
            foreach($posts as $id){
                $ids[] = 'post_' . (int)$id;
            }
        }

        if(!empty($term_ids)){
            foreach($term_ids as $id){
                $ids[] = 'term_' . (int)$id;
            }
        }

        $ids = array_values(array_unique($ids));

        return self::filter_source_pids_by_fix_options($ids, $process_key);
    }

    private static function is_dashboard_fix_process_key($process_key = ''){
        if(empty($process_key) || !is_string($process_key)){
            return false;
        }

        return in_array($process_key, self::get_dashboard_fix_process_keys(), true);
    }

    private static function get_fix_special_options($process_key = ''){
        if(!self::is_dashboard_fix_process_key($process_key)){
            return array();
        }

        if(isset(self::$fix_special_options_cache[$process_key])){
            return self::$fix_special_options_cache[$process_key];
        }

        $options = Wpil_Settings::get_ai_fix_special_options($process_key);
        self::$fix_special_options_cache[$process_key] = $options;
        return $options;
    }

    private static function filter_source_pids_by_fix_options($pids = array(), $process_key = ''){
        if(empty($pids) || !is_array($pids)){
            return array();
        }

        $options = self::get_fix_special_options($process_key);
        if(empty($options)){
            return array_values(array_unique($pids));
        }

        $selected_types = !empty($options['selected_post_types']) && is_array($options['selected_post_types']) ? $options['selected_post_types'] : array();
        $selected_post_ids = array();
        $limit_post_types = (!empty($options['select_post_types']) && !empty($selected_types));
        if($limit_post_types){
            $selected_post_ids = self::get_post_ids_for_post_types($selected_types);
        }

        $filtered = array();
        foreach($pids as $pid){
            $parts = self::parse_pid($pid);
            if(empty($parts['id'])){
                continue;
            }

            if(!empty($options['link_from_category_pages']) && $parts['type'] !== 'term'){
                continue;
            }

            if($limit_post_types && $parts['type'] === 'post'){
                if(!isset($selected_post_ids[$parts['id']])){
                    continue;
                }
            }

            $filtered[] = $parts['type'] . '_' . $parts['id'];
        }

        return array_values(array_unique($filtered));
    }

    /**
     * Makes sure we're only mapping real titles and human-looking slugs.
     * This keeps untitled items and numeric slug placeholders out of the relationship map.
     **/
    private static function is_allowed_relationship_pid($pid = ''){
        $parts = self::parse_pid($pid);
        if(empty($parts['id'])){
            return false;
        }

        $cache_key = $parts['type'] . '_' . $parts['id'];
        if(isset(self::$relationship_item_filter_cache[$cache_key])){
            return self::$relationship_item_filter_cache[$cache_key];
        }

        $title = '';
        $slug = '';

        if($parts['type'] === 'term'){
            $term = get_term($parts['id']);
            if(empty($term) || is_a($term, 'WP_Error') || isset($term->errors)){
                self::$relationship_item_filter_cache[$cache_key] = false;
                return false;
            }

            $title = trim(wp_strip_all_tags($term->name));
            $slug = trim(urldecode($term->slug));
        }else{
            $wp_post = get_post($parts['id']);
            if(empty($wp_post) || is_a($wp_post, 'WP_Error')){
                self::$relationship_item_filter_cache[$cache_key] = false;
                return false;
            }

            // if the post's URL is being redirected away to another post, don't put it in the map
            if(self::is_post_hidden_by_redirect($parts['id'])){
                self::$relationship_item_filter_cache[$cache_key] = false;
                return false;
            }

            $title = trim(wp_strip_all_tags($wp_post->post_title));
            $slug = trim(urldecode($wp_post->post_name));
        }

        if('' === $title){
            self::$relationship_item_filter_cache[$cache_key] = false;
            return false;
        }

        if('' !== $slug && preg_match('/^[0-9-]+$/', $slug)){
            self::$relationship_item_filter_cache[$cache_key] = false;
            return false;
        }

        self::$relationship_item_filter_cache[$cache_key] = true;
        return true;
    }

    private static function is_post_hidden_by_redirect($post_id = 0){
        $post_id = (int) $post_id;
        if(empty($post_id)){
            return false;
        }

        if(is_null(self::$redirect_hidden_post_ids)){
            self::$redirect_hidden_post_ids = array();
            $hidden_ids = Wpil_Settings::getPostsHiddenByRedirects(true);

            if(!empty($hidden_ids)){
                foreach($hidden_ids as $id){
                    self::$redirect_hidden_post_ids[(int) $id] = true;
                }
            }
        }

        return isset(self::$redirect_hidden_post_ids[$post_id]);
    }

    /**
     * Stops us from plotting more outbound work from posts that are already at the AI cap.
     **/
    private static function has_reached_ai_outbound_processing_limit($post = null){
        if(!is_a($post, 'Wpil_Model_Post')){
            return false;
        }

        $limit = (int) Wpil_Settings::get_ai_suggestion_outbound_limit();
        if($limit < 1){
            return false;
        }

        return ($post->getOutboundInternalLinks(true) >= $limit);
    }

    private static function is_allowed_related_pid_for_fix_options($source_post, $candidate_pid = '', $data = array(), $process_key = '', $target_is_source_post = false){
        $options = self::get_fix_special_options($process_key);
        $is_dashboard_fix = self::is_dashboard_fix_process_key($process_key);
        if(empty($options) && !$is_dashboard_fix){
            return true;
        }

        $parts = self::parse_pid($candidate_pid);
        if(empty($parts['id'])){
            return false;
        }

        if(!empty($options['link_to_category_pages']) && $parts['type'] !== 'term'){
            return false;
        }

        // Inbound maps point at the current post, while outbound maps point at the related post.
        if($is_dashboard_fix && !empty(get_option('wpil_limit_suggestions_to_post_types', false))){
            $suggestion_types = Wpil_Settings::getSuggestionPostTypes();
            if(!empty($suggestion_types) && is_array($suggestion_types)){
                $suggestion_post_ids = self::get_post_ids_for_post_types($suggestion_types);
                if(
                    $target_is_source_post &&
                    is_a($source_post, 'Wpil_Model_Post') &&
                    $source_post->type === 'post' &&
                    !isset($suggestion_post_ids[$source_post->id])
                ){
                    return false;
                }

                if(!$target_is_source_post && $parts['type'] === 'post' && !isset($suggestion_post_ids[$parts['id']])){
                    return false;
                }
            }
        }

        if(!empty($options['select_post_types']) && !empty($options['selected_post_types']) && is_array($options['selected_post_types']) && $parts['type'] === 'post'){
            $selected_post_ids = self::get_post_ids_for_post_types($options['selected_post_types']);
            if(!isset($selected_post_ids[$parts['id']])){
                return false;
            }
        }

        if(!empty($options['same_category']) && is_a($source_post, 'Wpil_Model_Post')){
            if($source_post->type === 'post'){
                if($parts['type'] === 'post'){
                    return (!empty($data['same_terms']) && isset($data['same_terms'][$parts['id']]));
                }

                if($parts['type'] === 'term'){
                    $source_categories = self::get_cached_post_categories($source_post->id);
                    return in_array($parts['id'], $source_categories, true);
                }
            }
        }

        return true;
    }

    private static function get_post_ids_for_post_types($post_types = array()){
        global $wpdb;

        if(empty($post_types) || !is_array($post_types)){
            return array();
        }

        $post_types = array_values(array_unique(array_filter(array_map('sanitize_key', $post_types))));
        sort($post_types);
        $cache_key = md5(implode('|', $post_types));
        if(isset(self::$post_type_id_cache[$cache_key])){
            return self::$post_type_id_cache[$cache_key];
        }

        // Pull the matching ids in one pass so we don't look up the post type for every suggestion.
        $placeholders = implode(',', array_fill(0, count($post_types), '%s'));
        $query = 'SELECT ID FROM ' . $wpdb->posts . ' WHERE post_type IN (' . $placeholders . ')';
        $post_ids = $wpdb->get_col($wpdb->prepare($query, $post_types));
        self::$post_type_id_cache[$cache_key] = !empty($post_ids) ? array_fill_keys(array_map('intval', $post_ids), true) : array();

        return self::$post_type_id_cache[$cache_key];
    }

    private static function get_cached_post_categories($post_id = 0){
        $post_id = (int) $post_id;
        if(empty($post_id)){
            return array();
        }

        if(isset(self::$post_category_cache[$post_id])){
            return self::$post_category_cache[$post_id];
        }

        $categories = array();
        $wp_post = get_post($post_id);
        if(empty($wp_post)){
            self::$post_category_cache[$post_id] = $categories;
            return self::$post_category_cache[$post_id];
        }

        $taxes = get_object_taxonomies($wp_post);
        if(!empty($taxes)){
            $query_taxes = array();
            foreach($taxes as $tax){
                $taxonomy = get_taxonomy($tax);
                if(!empty($taxonomy) && !empty($taxonomy->hierarchical)){
                    $query_taxes[] = $tax;
                }
            }

            if(!empty($query_taxes)){
                $terms = wp_get_object_terms($post_id, $query_taxes, array('fields' => 'ids'));
                if(!empty($terms) && !is_a($terms, 'WP_Error')){
                    $categories = array_map('intval', $terms);
                }
            }
        }

        self::$post_category_cache[$post_id] = $categories;
        return self::$post_category_cache[$post_id];
    }

    public static function parse_pid($pid = ''){
        $out = array(
            'type' => 'post',
            'id' => 0
        );

        if(is_numeric($pid)){
            $out['id'] = (int)$pid;
            return $out;
        }
 
        if(empty($pid) || !is_string($pid)){
            return $out;
        }

        $bits = explode('_', $pid, 2);
        if(count($bits) === 2){
            $type = $bits[0];
            $id = (int)$bits[1];
            if($type === 'term'){
                $out['type'] = 'term';
            }
            $out['id'] = $id;
        }

        return $out;
    }

    private static function is_allowed_term_pid($pid = '', $process_key = ''){
        $parts = self::parse_pid($pid);
        if($parts['type'] !== 'term'){
            return true;
        }

        $fix_special_options = self::get_fix_special_options($process_key);
        if( !empty(get_option('wpil_ai_process_all_terms', '')) || 
            !empty($fix_special_options['link_to_category_pages'])
        ){
            return true;
        }

        if(is_null(self::$money_page_term_ids)){
            $ids = Wpil_Settings::get_money_page_term_ids();
            self::$money_page_term_ids = array();
            if(!empty($ids)){
                foreach($ids as $id){
                    self::$money_page_term_ids[(int)$id] = true;
                }
            }
        }

        return !empty(self::$money_page_term_ids[$parts['id']]);
    }

    private static function get_relation_map_table(){
        global $wpdb;

        return $wpdb->prefix . 'wpil_relation_mapping';
    }

    public static function get_dashboard_fix_process_keys(){
        return array(
            md5('orphan-post-search'),
            md5('link-coverage-search'),
            md5('link-quality-search'),
            md5('broken-link-search'),
        );
    }

    public static function get_dashboard_fix_process_key($fix_type = ''){
        $map = array(
            'orphaned_posts'  => md5('orphan-post-search'),
            'link_coverage'   => md5('link-coverage-search'),
            'link_quality'    => md5('link-quality-search'),
            'broken_links'    => md5('broken-link-search'),
            'custom_link_map' => md5('custom-link-map'),
        );

        return isset($map[$fix_type]) ? $map[$fix_type] : '';
    }

    /**
     * Pre-populates the map_data for a relation-map item and marks it as
     * item_processed = 1, skipping the relationship-building phase.
     * Used by Wpil_CsvLinkMap for posts with fully-specified CSV constraints.
     */
    public static function pre_populate_relation_map_item($process_key, $pid, $map_data, $work_scope = self::RELATION_SCOPE_DEFAULT){
        return self::update_relation_map_item_row($process_key, $pid, $map_data, true, 0, $work_scope, '');
    }

    public static function update_relation_map_item_data_preserve_state($process_key, $pid, $map_data, $work_scope = self::RELATION_SCOPE_DEFAULT){
        $parts = self::parse_pid($pid);
        if(empty($process_key) || empty($parts['id'])){
            return false;
        }

        $row = self::get_relation_map_item($process_key, $parts['id'], $parts['type'], false, true, $work_scope);
        if(empty($row)){
            return false;
        }

        $resolved_scope = ($work_scope !== self::RELATION_SCOPE_DEFAULT && $work_scope !== '')
            ? self::normalize_relation_work_scope($work_scope)
            : (isset($row->work_scope) ? self::normalize_relation_work_scope($row->work_scope) : self::RELATION_SCOPE_DEFAULT);

        return self::update_relation_map_item_row(
            $process_key,
            $parts['type'] . '_' . $parts['id'],
            is_array($map_data) ? $map_data : array(),
            !empty($row->item_processed),
            !empty($row->ai_processed),
            $resolved_scope,
            isset($row->last_index) ? (string) $row->last_index : ''
        );
    }

    /**
     * Stores CSV pin hints (specified partners) in map_data without marking
     * item_processed = 1, so the auto relationship-building phase still runs.
     * After auto-discovery the hints are merged in by CsvLinkMap::merge_specified_relations.
     */
    public static function store_csv_pin_hints($process_key, $pid, $hint_data, $work_scope = self::RELATION_SCOPE_DEFAULT){
        $parts = self::parse_pid($pid);
        if(empty($parts['id'])){
            return false;
        }

        $table = self::get_relation_map_table();
        global $wpdb;

        // Merge hints into existing map_data without touching item_processed
        $row = self::get_relation_map_item($process_key, $parts['id'], $parts['type'], true, true, $work_scope);
        $existing = is_array($row) ? $row : array();
        $merged = array_merge($existing, $hint_data);

        $work_scope = self::normalize_relation_work_scope($work_scope);
        $wpdb->update(
            $table,
            array(
                'map_data' => Wpil_Toolbox::json_compress($merged),
            ),
            array(
                'process_key' => $process_key,
                'work_scope'  => $work_scope,
                'post_id'     => (int) $parts['id'],
                'post_type'   => (string) $parts['type'],
            ),
            array('%s'),
            array('%s', '%s', '%d', '%s')
        );

        return true;
    }

    /**
     * Normalises a single pid string to 'type_id' format.
     * Public wrapper used by Wpil_CsvLinkMap.
     */
    public static function normalize_pid($pid){
        $parts = self::parse_pid($pid);
        if(empty($parts['id'])){
            return '';
        }
        return $parts['type'] . '_' . $parts['id'];
    }

    /**
     * Checks whether a pid is still inside the user's AI age limit.
     * Terms are always allowed because the age restriction only applies to posts.
     */
    public static function is_pid_within_ai_processing_age($pid = ''){
        $parts = self::parse_pid($pid);
        if(empty($parts['id'])){
            return false;
        }

        if($parts['type'] !== 'post'){
            return true;
        }

        $age_limit = (int) Wpil_Settings::get_ai_max_processing_age();
        if($age_limit < 1){
            return true;
        }

        $post = get_post($parts['id']);
        if(empty($post) || is_a($post, 'WP_Error')){
            return false;
        }

        $date_string = !empty($post->post_date_gmt) && $post->post_date_gmt !== '0000-00-00 00:00:00'
            ? ($post->post_date_gmt . ' UTC')
            : (!empty($post->post_date) && $post->post_date !== '0000-00-00 00:00:00' ? $post->post_date : '');

        if(empty($date_string)){
            return false;
        }

        $post_time = strtotime($date_string);
        if(empty($post_time)){
            return false;
        }

        return ($post_time > (time() - ($age_limit * YEAR_IN_SECONDS)));
    }


    public static function normalize_relation_work_scope($work_scope = ''){
        $work_scope = is_string($work_scope) ? sanitize_key($work_scope) : '';
        if($work_scope === self::RELATION_SCOPE_OUTBOUND){
            return self::RELATION_SCOPE_OUTBOUND;
        }

        if($work_scope === self::RELATION_SCOPE_INBOUND){
            return self::RELATION_SCOPE_INBOUND;
        }

        return self::RELATION_SCOPE_DEFAULT;
    }

    private static function normalize_relation_pid_list($pids = array()){
        $normalized = array();
        foreach((array) $pids as $pid){
            $parts = self::parse_pid($pid);
            if(empty($parts['id'])){
                continue;
            }

            $normalized[$parts['type'] . '_' . $parts['id']] = $parts['type'] . '_' . $parts['id'];
        }

        return array_values($normalized);
    }

    private static function filter_relation_map_related_posts($map = array()){
        if(empty($map) || !is_array($map) || !isset($map['related_posts']) || !is_array($map['related_posts'])){
            return $map;
        }

        $related_posts = array();
        foreach($map['related_posts'] as $pid){
            $pid = self::normalize_pid($pid);
            if(empty($pid) || !self::is_allowed_relationship_pid($pid)){
                continue;
            }

            $related_posts[] = $pid;
        }

        $map['related_posts'] = array_values(array_unique($related_posts));
        return $map;
    }

    private static function get_relation_map_pillar_ids($process_key = ''){
        $pillar_ids = self::filter_source_pids_by_fix_options(Wpil_Settings::get_money_page_pid_list(true), $process_key);
        $pillar_ids = self::normalize_relation_pid_list($pillar_ids);

        $filtered = array();
        foreach($pillar_ids as $pid){
            if(self::is_allowed_relationship_pid($pid)){
                $filtered[] = $pid;
            }
        }

        return array_values(array_unique($filtered));
    }

    private static function get_relation_map_normal_ids($process_key = '', $pillar_ids = array()){
        $pillar_lookup = array_flip(self::normalize_relation_pid_list($pillar_ids));
        $processable = self::normalize_relation_pid_list(self::get_processable_posts($process_key));
        $filtered = array();

        foreach($processable as $pid){
            if(isset($pillar_lookup[$pid]) || !self::is_allowed_relationship_pid($pid)){
                continue;
            }

            $filtered[] = $pid;
        }

        return array_values(array_unique($filtered));
    }

    private static function maybe_reset_legacy_relation_map_process($process_key = ''){
        global $wpdb;

        if(empty($process_key)){
            return;
        }

        $table = self::get_relation_map_table();
        $legacy_count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE process_key = %s AND (post_type = '' OR post_type IS NULL)",
                $process_key
            )
        );

        if(!empty($legacy_count)){
            $wpdb->delete($table, array('process_key' => $process_key), array('%s'));
            unset(self::$relation_map_seed_cache[$process_key]);
        }
    }

    private static function get_relation_queue_row($pid = '', $work_scope = self::RELATION_SCOPE_DEFAULT, $is_pillar = false){
        $parts = self::parse_pid($pid);
        if(empty($parts['id'])){
            return array();
        }

        return array(
            'pid' => $parts['type'] . '_' . $parts['id'],
            'work_scope' => self::normalize_relation_work_scope($work_scope),
            'is_pillar' => !empty($is_pillar) ? 1 : 0,
        );
    }

    public static function seed_relation_map_queue($process_key = '', $queue_rows = array()){
        global $wpdb;

        if(empty($process_key) || empty($queue_rows) || !is_array($queue_rows)){
            return 0;
        }

        self::maybe_reset_legacy_relation_map_process($process_key);

        $table = self::get_relation_map_table();
        $now = time();
        $seeded = 0;
        foreach($queue_rows as $queue_row){
            if(empty($queue_row['pid'])){
                continue;
            }

            $parts = self::parse_pid($queue_row['pid']);
            if(empty($parts['id'])){
                continue;
            }

            if(!self::is_allowed_relationship_pid($parts['type'] . '_' . $parts['id'])){
                continue;
            }

            $work_scope = self::normalize_relation_work_scope(isset($queue_row['work_scope']) ? $queue_row['work_scope'] : self::RELATION_SCOPE_DEFAULT);
            $is_pillar = !empty($queue_row['is_pillar']) ? 1 : 0;
            $result = $wpdb->query(
                $wpdb->prepare(
                    "INSERT INTO {$table} (map_date, map_data, process_key, work_scope, post_id, post_type, is_pillar, item_processed, ai_processed, pillar_processed, map_processed, item_count, last_index)
                    VALUES (%d, %s, %s, %s, %d, %s, %d, 0, 0, 0, 0, 0, %s)
                    ON DUPLICATE KEY UPDATE is_pillar = VALUES(is_pillar)",
                    $now,
                    '',
                    $process_key,
                    $work_scope,
                    (int) $parts['id'],
                    (string) $parts['type'],
                    $is_pillar,
                    ''
                )
            );

            if(false !== $result){
                $seeded++;
            }
        }

        unset(self::$relation_map_seed_cache[$process_key]);
        return $seeded;
    }

    public static function seed_relation_map_process($process_key = ''){
        if(empty($process_key)){
            return array('queue_rows' => array(), 'pillar_ids' => array(), 'normal_ids' => array());
        }

        if(isset(self::$relation_map_seed_cache[$process_key])){
            return self::$relation_map_seed_cache[$process_key];
        }

        $pillar_ids = self::get_relation_map_pillar_ids($process_key);
        $normal_ids = self::get_relation_map_normal_ids($process_key, $pillar_ids);
        shuffle($normal_ids); // mix up the regular posts so partial mapps don't always start in the same place
        $queue_rows = array();
        foreach($pillar_ids as $pid){
            $row = self::get_relation_queue_row($pid, self::RELATION_SCOPE_DEFAULT, true);
            if(!empty($row)){
                $queue_rows[] = $row;
            }
        }

        foreach($normal_ids as $pid){
            $row = self::get_relation_queue_row($pid, self::RELATION_SCOPE_DEFAULT, false);
            if(!empty($row)){
                $queue_rows[] = $row;
            }
        }

        self::seed_relation_map_queue($process_key, $queue_rows);

        self::$relation_map_seed_cache[$process_key] = array(
            'queue_rows' => $queue_rows,
            'pillar_ids' => $pillar_ids,
            'normal_ids' => $normal_ids,
        );

        return self::$relation_map_seed_cache[$process_key];
    }

    /**
     * Clears a saved queue out and puts in the rows that we actually want to work.
     **/
    public static function reset_relation_map_queue($process_key = '', $queue_rows = null){
        if(empty($process_key)){
            return array('queue_rows' => array(), 'seeded' => 0);
        }

        self::delete_relation_map($process_key);

        if(null === $queue_rows){
            $seeded_process = self::seed_relation_map_process($process_key);
            $seeded_process['seeded'] = !empty($seeded_process['queue_rows']) ? count($seeded_process['queue_rows']) : 0;
            return $seeded_process;
        }

        $queue_rows = is_array($queue_rows) ? $queue_rows : array();
        return array(
            'queue_rows' => $queue_rows,
            'seeded' => self::seed_relation_map_queue($process_key, $queue_rows),
        );
    }

    /**
     * Keeps the finished relation map, but lets a new AI pass work through it again.
     **/
    public static function reset_relation_map_ai_queue($process_key = '', $work_scope = ''){
        global $wpdb;

        if(empty($process_key)){
            return false;
        }

        $table = self::get_relation_map_table();
        $sql = "UPDATE {$table} SET ai_processed = 0, last_index = '' WHERE process_key = %s AND item_processed = 1 AND map_data IS NOT NULL AND map_data != ''";
        $params = array($process_key);
        self::append_relation_scope_filter($sql, $params, $work_scope);

        return ($wpdb->query($wpdb->prepare($sql, $params)) !== false);
    }

    private static function sanitize_relation_map_runtime_data($map = array()){
        if(!is_array($map)){
            return array();
        }

        unset($map[self::RELATION_RUNTIME_KEY]);

        return $map;
    }

    private static function set_relation_map_runtime_state($map = array(), $runtime = array()){
        $map = self::sanitize_relation_map_runtime_data($map);
        $runtime = is_array($runtime) ? $runtime : array();
        $clean_runtime = array();

        foreach($runtime as $key => $value){
            if($value === null || $value === ''){
                continue;
            }

            if(is_array($value) && empty($value)){
                continue;
            }

            $clean_runtime[$key] = $value;
        }

        if(!empty($clean_runtime)){
            $map[self::RELATION_RUNTIME_KEY] = $clean_runtime;
        }

        return $map;
    }

    public static function get_relation_map_runtime_state($data = array()){
        if(is_object($data) && isset($data->map_data)){
            $data = $data->map_data;
        }

        if(!is_array($data) || empty($data[self::RELATION_RUNTIME_KEY]) || !is_array($data[self::RELATION_RUNTIME_KEY])){
            return array();
        }

        return $data[self::RELATION_RUNTIME_KEY];
    }

    private static function update_relation_map_item_row($process_key = '', $pid = '', $map = array(), $item_processed = true, $ai_processed = 0, $work_scope = self::RELATION_SCOPE_DEFAULT, $last_index = ''){
        global $wpdb;

        if(empty($process_key) || empty($pid)){
            return false;
        }

        $parts = self::parse_pid($pid);
        if(empty($parts['id'])){
            return false;
        }

        $table = self::get_relation_map_table();
        $compressed = '';
        $map = self::filter_relation_map_related_posts($map);
        if(!empty($item_processed) && isset($map['related_posts']) && empty($map['related_posts'])){
            $map = array();
            $ai_processed = 1;
        }

        if(!empty($map)){
            $compressed = Wpil_Toolbox::json_compress($map);
        }
        $work_scope = self::normalize_relation_work_scope($work_scope);

        $updated = $wpdb->update(
            $table,
            array(
                'map_date' => time(),
                'map_data' => $compressed,
                'item_processed' => empty($item_processed) ? 0 : 1,
                'ai_processed' => empty($ai_processed) ? 0 : 1,
                'last_index' => (string) $last_index,
            ),
            array(
                'process_key' => $process_key,
                'work_scope' => $work_scope,
                'post_id' => (int) $parts['id'],
                'post_type' => (string) $parts['type'],
            ),
            array('%d', '%s', '%d', '%d', '%s'),
            array('%s', '%s', '%d', '%s')
        );

        if(false === $updated){
            return false;
        }

        return true;
    }

    private static function relation_map_item_has_related_posts($map = array()){
        return (isset($map['related_posts']) && !empty($map['related_posts']));
    }

    private static function complete_empty_relation_map_item($process_key = '', $pid = '', $work_scope = self::RELATION_SCOPE_DEFAULT){
        return self::update_relation_map_item_row($process_key, $pid, array(), true, 1, $work_scope, '');
    }

    public static function update_relation_map_runtime_state($process_key = '', $pid = '', $runtime_updates = array(), $work_scope = ''){
        $parts = self::parse_pid($pid);
        if(empty($process_key) || empty($parts['id']) || empty($runtime_updates) || !is_array($runtime_updates)){
            return false;
        }

        $row = self::get_relation_map_item($process_key, $parts['id'], $parts['type'], false, true, $work_scope);
        if(empty($row)){
            return false;
        }

        $map = (isset($row->map_data) && is_array($row->map_data)) ? $row->map_data : array();
        $runtime = self::get_relation_map_runtime_state($map);
        foreach($runtime_updates as $key => $value){
            if($value === null || $value === '' || (is_array($value) && empty($value))){
                unset($runtime[$key]);
                continue;
            }

            $runtime[$key] = $value;
        }

        $work_scope = ($work_scope !== '') ? $work_scope : (isset($row->work_scope) ? $row->work_scope : self::RELATION_SCOPE_DEFAULT);

        return self::update_relation_map_item_row(
            $process_key,
            $parts['type'] . '_' . $parts['id'],
            self::set_relation_map_runtime_state($map, $runtime),
            !empty($row->item_processed),
            !empty($row->ai_processed),
            $work_scope,
            isset($row->last_index) ? $row->last_index : ''
        );
    }

    private static function get_relation_map_row_pid($row = null){
        if(empty($row)){
            return '';
        }

        $post_id = isset($row->post_id) ? (int) $row->post_id : 0;
        $post_type = isset($row->post_type) ? (string) $row->post_type : '';
        if(empty($post_id)){
            return '';
        }

        return (($post_type === 'term') ? 'term' : 'post') . '_' . $post_id;
    }

    private static function normalize_relation_map_row($row = null, $return_map = false){
        if(empty($row)){
            return $return_map ? array() : array();
        }

        if(isset($row->map_data) && '' !== $row->map_data && null !== $row->map_data){
            $decoded = Wpil_Toolbox::json_decompress($row->map_data, true);
            $row->map_data = (is_array($decoded)) ? $decoded : array();
        }else{
            $row->map_data = array();
        }

        $row->pid = self::get_relation_map_row_pid($row);
        $row->work_scope = self::normalize_relation_work_scope(isset($row->work_scope) ? $row->work_scope : self::RELATION_SCOPE_DEFAULT);

        if($return_map){
            return $row->map_data;
        }

        return $row;
    }

    private static function get_processed_relation_map_sibling_row($process_key = '', $pid = '', $excluded_scope = self::RELATION_SCOPE_DEFAULT){
        global $wpdb;

        if(empty($process_key) || empty($pid)){
            return array();
        }

        $parts = self::parse_pid($pid);
        if(empty($parts['id'])){
            return array();
        }

        $table = self::get_relation_map_table();
        $excluded_scope = self::normalize_relation_work_scope($excluded_scope);
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                    WHERE process_key = %s
                        AND post_id = %d
                        AND post_type = %s
                        AND work_scope != %s
                        AND item_processed = 1
                    ORDER BY id ASC
                    LIMIT 1",
                $process_key,
                (int) $parts['id'],
                (string) $parts['type'],
                $excluded_scope
            )
        );

        return !empty($row) ? self::normalize_relation_map_row($row, false) : array();
    }

    private static function save_relation_map_item($process_key = '', $pid = '', $map = array(), $item_processed = true, $work_scope = self::RELATION_SCOPE_DEFAULT){
        return self::update_relation_map_item_row(
            $process_key,
            $pid,
            self::sanitize_relation_map_runtime_data($map),
            $item_processed,
            0,
            $work_scope,
            ''
        );
    }

    private static function process_relation_map_row($process_key = '', $row = null, $limit = 0.55, $is_pillar = false){
        $pid = self::get_relation_map_row_pid($row);
        if(empty($pid)){
            return false;
        }
        $work_scope = self::normalize_relation_work_scope(isset($row->work_scope) ? $row->work_scope : self::RELATION_SCOPE_DEFAULT);

        $parts = self::parse_pid($pid);
        if(empty($parts['id']) || empty($parts['type'])){
            return self::save_relation_map_item($process_key, $pid, array(), true, $work_scope);
        }

        if(!self::is_allowed_relationship_pid($pid)){
            return self::save_relation_map_item($process_key, $pid, array(), true, $work_scope);
        }

        $sibling_row = self::get_processed_relation_map_sibling_row($process_key, $pid, $work_scope);
        if(!empty($sibling_row)){
            return self::save_relation_map_item($process_key, $pid, is_array($sibling_row->map_data) ? $sibling_row->map_data : array(), true, $work_scope);
        }

        $likely_inbound = ($work_scope === self::RELATION_SCOPE_INBOUND || $is_pillar || md5('orphan-post-search') === $process_key);
        $post = new Wpil_Model_Post($parts['id'], $parts['type']);
        if( (!$likely_inbound && // if these are not exclusively inbound internal fodc
            self::has_reached_ai_outbound_processing_limit($post)) || 
            ($likely_inbound && Wpil_Link::at_max_inbound_links($post)) // or the post is already at the max link limite
        ){
            return self::save_relation_map_item($process_key, $pid, array(), true, $work_scope);
        }

        if(
            $process_key === self::get_dashboard_fix_process_key('custom_link_map') &&
            $work_scope === self::RELATION_SCOPE_OUTBOUND &&
            !self::is_pid_within_ai_processing_age($pid)
        ){
            return self::save_relation_map_item($process_key, $pid, array(), true, $work_scope);
        }

        $signals = self::collect_post_signals($post, $limit, $process_key);
        $map_item = array(
            'pillar_content' => !empty($is_pillar),
            'related_posts' => array(),
        );

        $relation_candidates = self::judge_map_item_relations($post, array_merge($map_item, $signals), $process_key, $likely_inbound);
        $map_item['related_posts'] = array_keys($relation_candidates);

        if(self::$expanded_mapping && !empty($map_item['related_posts'])){
            $map_item = array_merge($map_item, $signals, array('related_post_candidates' => $relation_candidates));
        }

        return self::save_relation_map_item($process_key, $pid, $map_item, true, $work_scope);
    }

    private static function build_relation_pid_where_clause($pids = array(), $post_id_col = 'post_id', $post_type_col = 'post_type', $exclude = false){
        $pids = self::normalize_relation_pid_list($pids);
        if(empty($pids)){
            return array('sql' => '', 'params' => array());
        }

        $grouped_ids = array(
            'post' => array(),
            'term' => array(),
        );
        foreach($pids as $pid){
            $parts = self::parse_pid($pid);
            if(empty($parts['id'])){
                continue;
            }

            $type = ($parts['type'] === 'term') ? 'term' : 'post';
            $grouped_ids[$type][(int) $parts['id']] = (int) $parts['id'];
        }

        $clauses = array();
        $params = array();
        foreach($grouped_ids as $type => $ids){
            if(empty($ids)){
                continue;
            }

            $ids = array_values($ids);
            $placeholders = implode(', ', array_fill(0, count($ids), '%d'));
            $clauses[] = "({$post_type_col} = %s AND {$post_id_col} IN ({$placeholders}))";
            $params[] = (string) $type;
            foreach($ids as $id){
                $params[] = (int) $id;
            }
        }

        if(empty($clauses)){
            return array('sql' => '', 'params' => array());
        }

        return array(
            'sql' => $exclude ? ' AND NOT (' . implode(' OR ', $clauses) . ')' : ' AND (' . implode(' OR ', $clauses) . ')',
            'params' => $params,
        );
    }

    public static function get_next_unprocessed_relation_map_row($process_key = '', $scope = ''){
        global $wpdb;

        if(empty($process_key)){
            return array();
        }

        $table = self::get_relation_map_table();
        $sql = "SELECT * FROM {$table} WHERE process_key = %s AND post_id > 0 AND item_processed = 0";
        $params = array($process_key);
        if(is_array($scope)){
            $legacy_scope = self::build_relation_pid_where_clause($scope);
            if(!empty($legacy_scope['sql'])){
                $sql .= $legacy_scope['sql'];
                $params = array_merge($params, $legacy_scope['params']);
            }
            $sql .= " ORDER BY is_pillar DESC, id ASC LIMIT 25";
        }elseif($scope === '' || $scope === null){
            $sql .= " ORDER BY is_pillar DESC, id ASC LIMIT 25";
        }else{
            $sql .= " AND work_scope = %s ORDER BY is_pillar DESC, id ASC LIMIT 25";
            $params[] = self::normalize_relation_work_scope($scope);
        }
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params));
        if(empty($rows)){
            return array();
        }

        foreach($rows as $row){
            $normalized = self::normalize_relation_map_row($row, false);
            if(!empty($normalized->map_data) && !self::relation_map_item_has_related_posts($normalized->map_data)){
                self::complete_empty_relation_map_item($process_key, $normalized->pid, $normalized->work_scope);
                continue;
            }

            return $row;
        }

        return array();
    }

    public static function get_relation_map_item($process_key = '', $post_id = 0, $post_type = 'post', $return_map = false, $include_uncompleted = false, $work_scope = ''){
        global $wpdb;

        $post_id = (int) $post_id;
        $post_type = ('term' === $post_type) ? 'term' : 'post';
        if(empty($process_key) || empty($post_id)){
            return $return_map ? array() : array();
        }

        $table = self::get_relation_map_table();
        $sql = "SELECT * FROM {$table} WHERE process_key = %s AND post_id = %d AND post_type = %s";
        $params = array($process_key, $post_id, $post_type);
        if($work_scope !== ''){
            $sql .= " AND work_scope = %s";
            $params[] = self::normalize_relation_work_scope($work_scope);
        }
        if(!$include_uncompleted){
            $sql .= " AND item_processed = 1 AND map_data IS NOT NULL AND map_data != ''";
        }

        if($work_scope === ''){
            $sql .= " ORDER BY CASE WHEN work_scope = %s THEN 0 ELSE 1 END, item_processed DESC, id ASC";
            $params[] = self::RELATION_SCOPE_DEFAULT;
        }

        $sql .= " LIMIT 1";
        $row = $wpdb->get_row($wpdb->prepare($sql, $params));
        if(empty($row)){
            return $return_map ? array() : array();
        }

        $normalized = self::normalize_relation_map_row($row, false);
        if(!self::is_allowed_relationship_pid($normalized->pid)){
            return $return_map ? array() : array();
        }

        $normalized->map_data = self::filter_relation_map_related_posts($normalized->map_data);
        if(!empty($normalized->map_data) && !self::relation_map_item_has_related_posts($normalized->map_data)){
            self::complete_empty_relation_map_item($process_key, $normalized->pid, $normalized->work_scope);
            return $return_map ? array() : array();
        }

        return $return_map ? $normalized->map_data : $normalized;
    }

    public static function get_relation_map_items($process_key = '', $return_map = false, $include_uncompleted = false, $items_limit = 0, $scope_pids = array(), $exclude_pids = array()){
        global $wpdb;

        if(empty($process_key)){
            return $return_map ? array() : array();
        }

        $table = self::get_relation_map_table();
        $sql = "SELECT * FROM {$table} WHERE process_key = %s AND post_id > 0";
        $params = array($process_key);
        if(!$include_uncompleted){
            $sql .= " AND item_processed = 1 AND map_data IS NOT NULL AND map_data != ''";
        }

        if(self::is_relation_work_scope_arg($scope_pids)){
            self::append_relation_scope_filter($sql, $params, $scope_pids);
        } else {
            $scope = self::build_relation_pid_where_clause($scope_pids);
            if(!empty($scope['sql'])){
                $sql .= $scope['sql'];
                $params = array_merge($params, $scope['params']);
            }
        }

        $exclude = self::build_relation_pid_where_clause($exclude_pids, 'post_id', 'post_type', true);
        if(!empty($exclude['sql'])){
            $sql .= $exclude['sql'];
            $params = array_merge($params, $exclude['params']);
        }

        $sql .= " ORDER BY id ASC";
        if(!empty($items_limit)){
            $sql .= $wpdb->prepare(" LIMIT %d", (int) $items_limit);
        }

        $rows = $wpdb->get_results($wpdb->prepare($sql, $params));
        if(empty($rows)){
            return $return_map ? array() : array();
        }

        if($return_map){
            $map = array();
            foreach($rows as $row){
                $normalized = self::normalize_relation_map_row($row, false);
                if(empty($normalized->pid) || empty($normalized->map_data) || !self::is_allowed_relationship_pid($normalized->pid)){
                    continue;
                }

                $normalized->map_data = self::filter_relation_map_related_posts($normalized->map_data);
                if(!self::relation_map_item_has_related_posts($normalized->map_data)){
                    self::complete_empty_relation_map_item($process_key, $normalized->pid, $normalized->work_scope);
                    continue;
                }

                $map[$normalized->pid] = $normalized->map_data;
            }

            return $map;
        }

        $normalized_rows = array();
        foreach($rows as $row){
            $normalized = self::normalize_relation_map_row($row, false);
            if(empty($normalized->pid) || !self::is_allowed_relationship_pid($normalized->pid)){
                continue;
            }

            if(!empty($normalized->map_data)){
                $normalized->map_data = self::filter_relation_map_related_posts($normalized->map_data);
            }

            $normalized_rows[] = $normalized;
        }

        return $normalized_rows;
    }

    public static function get_relation_map($process_key = '', $return_map = false){
        if(empty($process_key)){
            return $return_map ? array() : array();
        }

        return self::get_relation_map_items($process_key, $return_map, false, 0);
    }

    private static function append_relation_scope_filter(&$sql = '', &$params = array(), $scope = ''){
        if($scope === '' || $scope === null){
            return;
        }

        $sql .= " AND work_scope = %s";
        $params[] = self::normalize_relation_work_scope($scope);
    }

    private static function append_relation_ai_claim_filter(&$sql = '', &$params = array()){
        $sql .= " AND (last_index != %s OR last_index IS NULL OR last_index = '' OR map_date < %d)";
        $params[] = self::RELATION_AI_CLAIM_MARKER;
        $params[] = (time() - self::RELATION_AI_CLAIM_TTL);
    }

    private static function is_relation_work_scope_arg($scope = null){
        if(is_array($scope) || $scope === null || $scope === ''){
            return false;
        }

        return in_array((string) $scope, array(
            self::RELATION_SCOPE_DEFAULT,
            self::RELATION_SCOPE_OUTBOUND,
            self::RELATION_SCOPE_INBOUND,
        ), true);
    }

    /**
     * Deletes the saved relation map for the given process key.
     * Used to force a clean rebuild when a new run starts.
     *
     * @param string $process_key
     * @return bool
     */
    public static function delete_relation_map($process_key = ''){
        if(empty($process_key)){
            return false;
        }

        global $wpdb;
        $table = self::get_relation_map_table();
        $deleted = $wpdb->delete($table, array('process_key' => $process_key), array('%s'));
        unset(self::$relation_map_seed_cache[$process_key]);
        return ($deleted !== false);
    }

    public static function has_processed_pillar_relations($process_key = '', $work_scope = ''){
        global $wpdb;

        if(empty($process_key)){
            return false;
        }

        $table = self::get_relation_map_table();
        $sql = "SELECT COUNT(*) FROM {$table} WHERE process_key = %s AND post_id > 0 AND is_pillar = 1 AND item_processed = 0";
        $params = array($process_key);
        self::append_relation_scope_filter($sql, $params, $work_scope);
        $count = $wpdb->get_var($wpdb->prepare($sql, $params));

        return ((int) $count < 1);
    }

    public static function mark_map_pillar_processed($process_key = ''){
        return true;
    }

    public static function mark_map_processed($process_key = ''){
        return true;
    }

    public static function is_map_processed($process_key = '', $work_scope = ''){
        if(empty($process_key)){
            return false;
        }

        return (self::get_relation_map_total_item_count($process_key, $work_scope) > 0 && self::get_relation_map_unprocessed_item_count($process_key, $work_scope) < 1);
    }

    public static function get_relation_map_total_item_count($process_key = '', $work_scope = ''){
        global $wpdb;

        if(empty($process_key)){
            return 0;
        }

        $table = self::get_relation_map_table();
        $sql = "SELECT COUNT(*) FROM {$table} WHERE process_key = %s AND post_id > 0";
        $params = array($process_key);
        self::append_relation_scope_filter($sql, $params, $work_scope);
        return (int) $wpdb->get_var($wpdb->prepare($sql, $params));
    }

    public static function get_relation_map_available_item_count($process_key = '', $scope_pids = array(), $exclude_pids = array()){
        global $wpdb;

        if(empty($process_key)){
            return 0;
        }

        $table = self::get_relation_map_table();
        $sql = "SELECT COUNT(*) FROM {$table} WHERE process_key = %s AND post_id > 0 AND item_processed = 1 AND map_data IS NOT NULL AND map_data != ''";
        $params = array($process_key);
        if(self::is_relation_work_scope_arg($scope_pids)){
            self::append_relation_scope_filter($sql, $params, $scope_pids);
            return (int) $wpdb->get_var($wpdb->prepare($sql, $params));
        }

        $scope = self::build_relation_pid_where_clause($scope_pids);
        if(!empty($scope['sql'])){
            $sql .= $scope['sql'];
            $params = array_merge($params, $scope['params']);
        }

        $exclude = self::build_relation_pid_where_clause($exclude_pids, 'post_id', 'post_type', true);
        if(!empty($exclude['sql'])){
            $sql .= $exclude['sql'];
            $params = array_merge($params, $exclude['params']);
        }

        return (int) $wpdb->get_var($wpdb->prepare($sql, $params));
    }

    public static function get_relation_map_ai_processed_item_count($process_key = '', $scope_pids = array(), $exclude_pids = array()){
        global $wpdb;

        if(empty($process_key)){
            return 0;
        }

        $table = self::get_relation_map_table();
        $sql = "SELECT COUNT(*) FROM {$table} WHERE process_key = %s AND post_id > 0 AND ai_processed = 1";
        $params = array($process_key);
        if(self::is_relation_work_scope_arg($scope_pids)){
            self::append_relation_scope_filter($sql, $params, $scope_pids);
            return (int) $wpdb->get_var($wpdb->prepare($sql, $params));
        }

        $scope = self::build_relation_pid_where_clause($scope_pids);
        if(!empty($scope['sql'])){
            $sql .= $scope['sql'];
            $params = array_merge($params, $scope['params']);
        }

        $exclude = self::build_relation_pid_where_clause($exclude_pids, 'post_id', 'post_type', true);
        if(!empty($exclude['sql'])){
            $sql .= $exclude['sql'];
            $params = array_merge($params, $exclude['params']);
        }

        return (int) $wpdb->get_var($wpdb->prepare($sql, $params));
    }

    public static function get_relation_map_available_ai_processed_item_count($process_key = '', $scope_pids = array(), $exclude_pids = array()){
        global $wpdb;

        if(empty($process_key)){
            return 0;
        }

        $table = self::get_relation_map_table();
        $sql = "SELECT COUNT(*) FROM {$table} WHERE process_key = %s AND post_id > 0 AND item_processed = 1 AND ai_processed = 1 AND map_data IS NOT NULL AND map_data != ''";
        $params = array($process_key);
        if(self::is_relation_work_scope_arg($scope_pids)){
            self::append_relation_scope_filter($sql, $params, $scope_pids);
            return (int) $wpdb->get_var($wpdb->prepare($sql, $params));
        }

        $scope = self::build_relation_pid_where_clause($scope_pids);
        if(!empty($scope['sql'])){
            $sql .= $scope['sql'];
            $params = array_merge($params, $scope['params']);
        }

        $exclude = self::build_relation_pid_where_clause($exclude_pids, 'post_id', 'post_type', true);
        if(!empty($exclude['sql'])){
            $sql .= $exclude['sql'];
            $params = array_merge($params, $exclude['params']);
        }

        return (int) $wpdb->get_var($wpdb->prepare($sql, $params));
    }

    public static function get_relation_map_pending_ai_item_count($process_key = '', $scope_pids = array(), $exclude_pids = array(), $require_map_data = false){
        global $wpdb;

        if(empty($process_key)){
            return 0;
        }

        $table = self::get_relation_map_table();
        $sql = "SELECT COUNT(*) FROM {$table} WHERE process_key = %s AND post_id > 0 AND item_processed = 1 AND ai_processed = 0";
        $params = array($process_key);
        if($require_map_data){
            $sql .= " AND map_data IS NOT NULL AND map_data != ''";
        }
        self::append_relation_ai_claim_filter($sql, $params);
        if(self::is_relation_work_scope_arg($scope_pids)){
            self::append_relation_scope_filter($sql, $params, $scope_pids);
            return (int) $wpdb->get_var($wpdb->prepare($sql, $params));
        }

        $scope = self::build_relation_pid_where_clause($scope_pids);
        if(!empty($scope['sql'])){
            $sql .= $scope['sql'];
            $params = array_merge($params, $scope['params']);
        }

        $exclude = self::build_relation_pid_where_clause($exclude_pids, 'post_id', 'post_type', true);
        if(!empty($exclude['sql'])){
            $sql .= $exclude['sql'];
            $params = array_merge($params, $exclude['params']);
        }

        return (int) $wpdb->get_var($wpdb->prepare($sql, $params));
    }

    private static function get_relation_scope_chunks($scope_pids = array(), $chunk_size = 200){
        $scope_pids = self::normalize_relation_pid_list($scope_pids);
        $chunk_size = max(1, (int) $chunk_size);
        if(empty($scope_pids)){
            return array();
        }

        $grouped = array(
            'post' => array(),
            'term' => array(),
        );

        foreach($scope_pids as $pid){
            $parts = self::parse_pid($pid);
            if(empty($parts['id'])){
                continue;
            }

            $type = ($parts['type'] === 'term') ? 'term' : 'post';
            $grouped[$type][] = $type . '_' . (int) $parts['id'];
        }

        $chunks = array();
        foreach($grouped as $pids){
            if(empty($pids)){
                continue;
            }

            foreach(array_chunk($pids, $chunk_size) as $chunk){
                if(!empty($chunk)){
                    $chunks[] = $chunk;
                }
            }
        }

        return $chunks;
    }

    public static function get_relation_map_scope_status($process_key = '', $scope_pids = array(), $require_map_data = false){
        if(self::is_relation_work_scope_arg($scope_pids)){
            $work_scope = self::normalize_relation_work_scope($scope_pids);
            $status = array(
                'scope_pids' => array(),
                'work_scope' => $work_scope,
                'total' => self::get_relation_map_total_item_count($process_key, $work_scope),
                'done_count' => self::get_relation_map_ai_processed_item_count($process_key, $work_scope),
                'pending_count' => self::get_relation_map_pending_ai_item_count($process_key, $work_scope, array(), $require_map_data),
                'unprocessed_count' => self::get_relation_map_unprocessed_item_count($process_key, $work_scope),
                'scope_complete' => false,
                'next_pending' => '',
            );

            $status['scope_complete'] = ($status['unprocessed_count'] < 1);
            $next_row = self::get_next_pending_relation_row($process_key, $work_scope, $require_map_data);
            $status['next_pending'] = (!empty($next_row) && !empty($next_row->pid)) ? $next_row->pid : '';
            return $status;
        }

        $scope_pids = self::normalize_relation_pid_list($scope_pids);
        $status = array(
            'scope_pids' => $scope_pids,
            'total' => count($scope_pids),
            'done_count' => 0,
            'pending_count' => 0,
            'unprocessed_count' => 0,
            'scope_complete' => true,
            'next_pending' => '',
        );

        if(empty($process_key) || empty($scope_pids)){
            return $status;
        }

        foreach(self::get_relation_scope_chunks($scope_pids) as $chunk){
            $status['done_count'] += self::get_relation_map_ai_processed_item_count($process_key, $chunk);
            $status['pending_count'] += self::get_relation_map_pending_ai_item_count($process_key, $chunk, array(), $require_map_data);
            $status['unprocessed_count'] += self::get_relation_map_unprocessed_item_count($process_key, $chunk);

            if(empty($status['next_pending'])){
                $rows = self::get_relation_map_items_pending_ai($process_key, 1, $chunk, array(), false, $require_map_data);
                if(!empty($rows)){
                    foreach($rows as $row){
                        if(!empty($row->pid)){
                            $status['next_pending'] = $row->pid;
                            break;
                        }
                    }
                }
            }
        }

        $status['scope_complete'] = ($status['unprocessed_count'] < 1);

        return $status;
    }

    public static function get_next_pending_relation_row($process_key = '', $work_scope = self::RELATION_SCOPE_DEFAULT, $require_map_data = false){
        global $wpdb;

        if(empty($process_key)){
            return array();
        }

        $table = self::get_relation_map_table();
        $sql = "SELECT * FROM {$table} WHERE process_key = %s AND post_id > 0 AND item_processed = 1 AND ai_processed = 0";
        $params = array($process_key);
        self::append_relation_scope_filter($sql, $params, $work_scope);
        if($require_map_data){
            $sql .= " AND map_data IS NOT NULL AND map_data != ''";
        }
        self::append_relation_ai_claim_filter($sql, $params);

        $sql .= " ORDER BY is_pillar DESC, id ASC LIMIT 1";
        $row = $wpdb->get_row($wpdb->prepare($sql, $params));
        $normalized = !empty($row) ? self::normalize_relation_map_row($row, false) : array();
        if(!empty($normalized) && !self::is_allowed_relationship_pid($normalized->pid)){
            self::complete_empty_relation_map_item($process_key, $normalized->pid, $normalized->work_scope);
            return array();
        }

        if(!empty($normalized) && !empty($normalized->map_data)){
            $normalized->map_data = self::filter_relation_map_related_posts($normalized->map_data);
        }

        if(!empty($normalized) && !empty($normalized->map_data) && !self::relation_map_item_has_related_posts($normalized->map_data)){
            self::complete_empty_relation_map_item($process_key, $normalized->pid, $normalized->work_scope);
            return array();
        }

        return $normalized;
    }

    public static function get_relation_map_items_pending_ai($process_key = '', $items_limit = 0, $scope_pids = array(), $exclude_pids = array(), $return_map = false, $require_map_data = false){
        global $wpdb;

        if(empty($process_key)){
            return $return_map ? array() : array();
        }

        $table = self::get_relation_map_table();
        $sql = "SELECT * FROM {$table} WHERE process_key = %s AND post_id > 0 AND item_processed = 1 AND ai_processed = 0";
        $params = array($process_key);
        if($require_map_data){
            $sql .= " AND map_data IS NOT NULL AND map_data != ''";
        }
        self::append_relation_ai_claim_filter($sql, $params);
        if(self::is_relation_work_scope_arg($scope_pids)){
            self::append_relation_scope_filter($sql, $params, $scope_pids);
            $scope_pids = array();
        }

        $scope = self::build_relation_pid_where_clause($scope_pids);
        if(!empty($scope['sql'])){
            $sql .= $scope['sql'];
            $params = array_merge($params, $scope['params']);
        }

        $exclude = self::build_relation_pid_where_clause($exclude_pids, 'post_id', 'post_type', true);
        if(!empty($exclude['sql'])){
            $sql .= $exclude['sql'];
            $params = array_merge($params, $exclude['params']);
        }

        $sql .= " ORDER BY is_pillar DESC, id ASC";
        if(!empty($items_limit)){
            $sql .= $wpdb->prepare(" LIMIT %d", (int) $items_limit);
        }

        $rows = $wpdb->get_results($wpdb->prepare($sql, $params));
        if(empty($rows)){
            return $return_map ? array() : array();
        }

        if($return_map){
            $map = array();
            foreach($rows as $row){
                $normalized = self::normalize_relation_map_row($row, false);
                if(empty($normalized->pid) || empty($normalized->map_data) || !self::is_allowed_relationship_pid($normalized->pid)){
                    continue;
                }

                $normalized->map_data = self::filter_relation_map_related_posts($normalized->map_data);
                if(!self::relation_map_item_has_related_posts($normalized->map_data)){
                    self::complete_empty_relation_map_item($process_key, $normalized->pid, $normalized->work_scope);
                    continue;
                }

                $map[$normalized->pid] = $normalized->map_data;
            }

            return $map;
        }

        $normalized_rows = array();
        foreach($rows as $row){
            $normalized = self::normalize_relation_map_row($row, false);
            if(empty($normalized->pid) || !self::is_allowed_relationship_pid($normalized->pid)){
                if(!empty($normalized->pid)){
                    self::complete_empty_relation_map_item($process_key, $normalized->pid, $normalized->work_scope);
                }
                continue;
            }

            if(!empty($normalized->map_data)){
                $normalized->map_data = self::filter_relation_map_related_posts($normalized->map_data);
            }

            if(!empty($normalized->map_data) && !self::relation_map_item_has_related_posts($normalized->map_data)){
                self::complete_empty_relation_map_item($process_key, $normalized->pid, $normalized->work_scope);
                continue;
            }

            $normalized_rows[] = $normalized;
        }

        return $normalized_rows;
    }

    public static function get_relation_map_completed_item_count($process_key = '', $scope_pids = array(), $exclude_pids = array()){
        global $wpdb;

        if(empty($process_key)){
            return 0;
        }

        $table = self::get_relation_map_table();
        $sql = "SELECT COUNT(*) FROM {$table} WHERE process_key = %s AND post_id > 0 AND item_processed = 1";
        $params = array($process_key);
        if(self::is_relation_work_scope_arg($scope_pids)){
            self::append_relation_scope_filter($sql, $params, $scope_pids);
            return (int) $wpdb->get_var($wpdb->prepare($sql, $params));
        }

        $scope = self::build_relation_pid_where_clause($scope_pids);
        if(!empty($scope['sql'])){
            $sql .= $scope['sql'];
            $params = array_merge($params, $scope['params']);
        }

        $exclude = self::build_relation_pid_where_clause($exclude_pids, 'post_id', 'post_type', true);
        if(!empty($exclude['sql'])){
            $sql .= $exclude['sql'];
            $params = array_merge($params, $exclude['params']);
        }

        return (int) $wpdb->get_var($wpdb->prepare($sql, $params));
    }

    public static function get_relation_map_unprocessed_item_count($process_key = '', $scope_pids = array(), $exclude_pids = array()){
        global $wpdb;

        if(empty($process_key)){
            return 0;
        }

        $table = self::get_relation_map_table();
        $sql = "SELECT COUNT(*) FROM {$table} WHERE process_key = %s AND post_id > 0 AND item_processed = 0";
        $params = array($process_key);
        if(self::is_relation_work_scope_arg($scope_pids)){
            self::append_relation_scope_filter($sql, $params, $scope_pids);
            return (int) $wpdb->get_var($wpdb->prepare($sql, $params));
        }

        $scope = self::build_relation_pid_where_clause($scope_pids);
        if(!empty($scope['sql'])){
            $sql .= $scope['sql'];
            $params = array_merge($params, $scope['params']);
        }

        $exclude = self::build_relation_pid_where_clause($exclude_pids, 'post_id', 'post_type', true);
        if(!empty($exclude['sql'])){
            $sql .= $exclude['sql'];
            $params = array_merge($params, $exclude['params']);
        }

        return (int) $wpdb->get_var($wpdb->prepare($sql, $params));
    }

    public static function are_relation_map_items_processed($process_key = '', $scope_pids = array(), $exclude_pids = array()){
        return (self::get_relation_map_unprocessed_item_count($process_key, $scope_pids, $exclude_pids) < 1);
    }

    public static function mark_relation_map_ai_processed($process_key = '', $pid = '', $processed = true, $work_scope = ''){
        global $wpdb;

        if(empty($process_key) || empty($pid)){
            return false;
        }

        $parts = self::parse_pid($pid);
        if(empty($parts['id'])){
            return false;
        }

        $table = self::get_relation_map_table();
        $data = array(
            'ai_processed' => empty($processed) ? 0 : 1,
            'last_index' => '',
        );
        $where = array(
            'process_key' => $process_key,
            'post_id' => (int) $parts['id'],
            'post_type' => (string) $parts['type'],
        );
        $where_format = array('%s', '%d', '%s');
        if($work_scope !== ''){
            $where['work_scope'] = self::normalize_relation_work_scope($work_scope);
            $where_format[] = '%s';
        }

        $updated = ($wpdb->update($table, $data, $where, array('%d', '%s'), $where_format) !== false);

        return $updated;
    }

    public static function claim_relation_map_ai_item($process_key = '', $pid = '', $work_scope = ''){
        global $wpdb;

        if(empty($process_key) || empty($pid)){
            return false;
        }

        $parts = self::parse_pid($pid);
        if(empty($parts['id'])){
            return false;
        }

        $table = self::get_relation_map_table();
        $data = array(
            'last_index' => self::RELATION_AI_CLAIM_MARKER,
            'map_date' => time(),
        );
        $where = array(
            'process_key' => $process_key,
            'post_id' => (int) $parts['id'],
            'post_type' => (string) $parts['type'],
            'ai_processed' => 0,
        );
        $where_format = array('%s', '%d', '%s', '%d');
        if($work_scope !== ''){
            $where['work_scope'] = self::normalize_relation_work_scope($work_scope);
            $where_format[] = '%s';
        }

        $updated = ($wpdb->update($table, $data, $where, array('%s', '%d'), $where_format) !== false);
        return $updated;
    }

    public static function release_relation_map_ai_claim($process_key = '', $pid = '', $work_scope = ''){
        global $wpdb;

        if(empty($process_key) || empty($pid)){
            return false;
        }

        $parts = self::parse_pid($pid);
        if(empty($parts['id'])){
            return false;
        }

        $table = self::get_relation_map_table();
        $where = array(
            'process_key' => $process_key,
            'post_id' => (int) $parts['id'],
            'post_type' => (string) $parts['type'],
        );
        $where_format = array('%s', '%d', '%s');
        if($work_scope !== ''){
            $where['work_scope'] = self::normalize_relation_work_scope($work_scope);
            $where_format[] = '%s';
        }

        return ($wpdb->update($table, array('last_index' => ''), $where, array('%s'), $where_format) !== false);
    }

    public static function has_completed_relation_map_item($process_key = '', $pid = '', $require_map_data = false, $work_scope = ''){
        global $wpdb;

        if(empty($process_key) || empty($pid)){
            return false;
        }

        $parts = self::parse_pid($pid);
        if(empty($parts['id'])){
            return false;
        }

        $table = self::get_relation_map_table();
        $sql = "SELECT COUNT(*) FROM {$table} WHERE process_key = %s AND post_id = %d AND post_type = %s AND item_processed = 1";
        $params = array($process_key, (int) $parts['id'], (string) $parts['type']);
        if($work_scope !== ''){
            $sql .= " AND work_scope = %s";
            $params[] = self::normalize_relation_work_scope($work_scope);
        }
        if($require_map_data){
            $sql .= " AND map_data IS NOT NULL AND map_data != ''";
        }

        return !empty($wpdb->get_var($wpdb->prepare($sql, $params)));
    }

    /**
     * Saves the whole map as is
     **/
    public static function save_relation_map($process_key = '', $map = []){
        if(empty($process_key) || empty($map)){
            return false;
        }

        $saved = false;
        foreach($map as $pid => $item){
            $parts = self::parse_pid($pid);
            if(empty($parts['id'])){
                continue;
            }

            $saved = self::save_relation_map_item($process_key, $parts['type'] . '_' . $parts['id'], is_array($item) ? $item : array(), true) || $saved;
        }

        return $saved;
    }


    /**
     * This is a vague grouping of methods that perform functions
     **/

    /**
     * Build
     **/
    public static function handle_map_processing($process_key = ''){

    }
}
