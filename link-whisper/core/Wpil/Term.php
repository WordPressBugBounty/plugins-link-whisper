<?php

/**
 * Work with terms
 */
class Wpil_Term
{
    /**
     * Register services
     */
    public function register()
    {
        foreach (Wpil_Settings::getTermTypes() as $term) {
            add_action($term . '_add_form_fields', [$this, 'showTermSuggestions']);
            add_action($term . '_edit_form', [$this, 'showTermSuggestions']);
            // check the term link counts once were sure there's no more link processing to do
            add_action('saved_' . $term, [$this, 'updateTermStats'], 10, 3);
        }
    }

    /**
     * Show suggestions on term page
     */
    public static function showTermSuggestions()
    {
        if(empty($_GET['tag_ID']) ||empty($_GET['taxonomy'] || !in_array($_GET['taxonomy'], Wpil_Settings::getTermTypes()))){
            return;
        }

        $term_id = (int)$_GET['tag_ID'];
        $post_id = 0;
        $user = wp_get_current_user();
        ?>
        <div id="wpil_link-articles" class="postbox">
            <h2 class="hndle no-drag"><span><?php esc_html_e('Link Whisper Suggested Links', 'wpil'); ?></span></h2>
            <div class="inside">
                <?php include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/link_list_v2.php';?>
            </div>
        </div>
        <?php
    }

    /**
     * Show target keywords on term page
     */
    public static function showTargetKeywords()
    {
        if(empty($_GET['tag_ID']) ||empty($_GET['taxonomy'] || !in_array($_GET['taxonomy'], Wpil_Settings::getTermTypes()))){
            return;
        }

        $term_id = (int)$_GET['tag_ID'];
        $post_id = 0;
        $user = wp_get_current_user();
        $post = new Wpil_Model_Post($term_id, 'term');

        // exit if the term has been ignored
        $completely_ignored = Wpil_Settings::get_completely_ignored_pages();
        if(!empty($completely_ignored) && in_array($post->type . '_' . $post->id, $completely_ignored, true)){
            return;
        }

        $keywords = Wpil_TargetKeyword::get_keywords_by_post_ids($term_id, 'term');
        $keyword_sources = Wpil_TargetKeyword::get_active_keyword_sources();
        $is_metabox = true;
        ?>
        <div id="wpil_target-keywords" class="postbox ">
            <h2 class="hndle no-drag"><span><?php esc_html_e('Link Whisper Target Keywords', 'wpil'); ?></span></h2>
            <div class="inside"><?php
                include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/target_keyword_list.php';
            ?>
            </div>
        </div>
        <?php
    }

    /**
     * Updates the term's linking stats after the link adding is completed elsewhere
     **/
    public static function updateTermStats($term_id, $tt_id = 0, $updated = false){
        $term = new Wpil_Model_Post($term_id, 'term');
        if(WPIL_STATUS_LINK_TABLE_EXISTS && Wpil_Report::stored_link_content_changed($term)){
            // get the fresh term content for the benefit of the descendent methods
            $term->getFreshContent();
            // find any inbound internal link references that are no longer valid
            $removed_links = Wpil_Report::find_removed_report_inbound_links($term);
            // update the links stored in the link table
            Wpil_Report::update_post_in_link_table($term);
            // update the meta data for the term
            Wpil_Report::statUpdate($term, true);
            // and update the link counts for the posts that this one links to
            Wpil_Report::updateReportInternallyLinkedPosts($term, $removed_links);
        }
    }

    /**
     * Get all Advanced Custom Fields names
     *
     * @return array
     */
    public static function getAdvancedCustomFieldsList($term_id)
    {
        global $wpdb;

        $fields = [];

        if(!class_exists('ACF') || get_option('wpil_disable_acf', false)){
            return $fields;
        }

        $fields_query = $wpdb->get_results("SELECT SUBSTR(meta_key, 2) as `name` FROM {$wpdb->termmeta} WHERE term_id = $term_id AND meta_value LIKE 'field_%' AND SUBSTR(meta_key, 2) != ''");
        foreach ($fields_query as $field) {
            $name = trim($field->name);

            if ($name) {
                $fields[] = $name;
            }
        }

        return $fields;
    }

    /**
     * Get category or tag by slug
     *
     * @param string $slug The slug to search for
     * @param string $url (Optional) The URL that we're trying to pull info from
     * @return WP_Term|false The found term or false if not found
     */
    public static function getTermBySlug($slug, $url = '')
    {
        global $wpdb, $wp_rewrite;

        // Basic validation
        if (empty($slug) || is_int($slug) || is_array($slug)) {
            return false;
        }

        // Get all public taxonomies
        $taxonomies = get_taxonomies(['public' => true]);
        if (empty($taxonomies)) {
            return false;
        }

        // First, try to get the term directly by slug with taxonomy context from URL
        // If we have a URL, try to extract taxonomy context first
        if (!empty($url)) {
            $parsed_url = parse_url($url);
            $path = !empty($parsed_url['path']) ? trim($parsed_url['path'], '/') : '';

            if (!empty($path)) {
                // Try to match taxonomy base from the URL
                foreach ($taxonomies as $taxonomy) {
                    $tax_obj = get_taxonomy($taxonomy);
                    if (empty($tax_obj->rewrite['slug'])) {
                        continue;
                    }

                    $tax_slug = $tax_obj->rewrite['slug'];

                    // Check if the URL contains the taxonomy slug followed by our term slug
                    if (preg_match('#' . preg_quote($tax_slug, '#') . '/([^/]+)/?$#i', $path, $matches)) {
                        $found_slug = $matches[1];
                        if ($found_slug === $slug) {
                            $term = get_term_by('slug', $slug, $taxonomy);
                            if ($term && !is_wp_error($term)) {
                                return $term;
                            }
                        }
                    }
                }
            }
        }

        // If no term found by taxonomy context, try direct lookup
        $args = [
            'get'                   => 'all',
            'slug'                  => $slug,
            'taxonomy'              => array_values($taxonomies),
            'update_term_meta_cache'=> false,
            'orderby'               => 'none',
            'suppress_filter'       => true,
            'number'                => 100,
        ];

        $terms = get_terms($args);

        // If no terms found, try direct database query
        if (empty($terms) || is_wp_error($terms)) {
            $terms = $wpdb->get_results($wpdb->prepare(
                "SELECT t.*, tt.* 
                FROM $wpdb->terms AS t 
                INNER JOIN $wpdb->term_taxonomy AS tt ON t.term_id = tt.term_id 
                WHERE t.slug = %s 
                AND tt.taxonomy IN ('" . implode("','", array_map('esc_sql', array_values($taxonomies))) . "')
                LIMIT 100",
                $slug
            ));

            if (empty($terms)) {
                return false;
            }
        }

        // If only one term found, return it
        if (count($terms) === 1) {
            return is_object($terms[0]) ? $terms[0] : (object)$terms[0];
        }

        // If we have a URL, try to match by URL structure
        if (!empty($url)) {
            $parsed_url = parse_url($url);
            $path = !empty($parsed_url['path']) ? trim($parsed_url['path'], '/') : '';

            if (!empty($path)) {
                // Try to find exact match first
                foreach ($terms as $term) {
                    $term = is_object($term) ? $term : (object)$term;
                    $term_link = get_term_link($term);

                    if (is_wp_error($term_link)) {
                        continue;
                    }

                    $term_path = trim(parse_url($term_link, PHP_URL_PATH), '/');
                    if ($term_path === $path) {
                        return $term;
                    }
                }

                // Try to find best match by path components
                $best_match = null;
                $best_score = 0;
                $path_parts = array_filter(explode('/', $path));

                foreach ($terms as $term) {
                    $term = is_object($term) ? $term : (object)$term;
                    $term_link = get_term_link($term);

                    if (is_wp_error($term_link)) {
                        continue;
                    }

                    $term_path = trim(parse_url($term_link, PHP_URL_PATH), '/');
                    $term_parts = array_filter(explode('/', $term_path));

                    // Calculate how many path components match
                    $matching_parts = 0;
                    $max_parts = min(count($path_parts), count($term_parts));

                    for ($i = 0; $i < $max_parts; $i++) {
                        if ($path_parts[$i] === $term_parts[$i]) {
                            $matching_parts++;
                        } else {
                            break;
                        }
                    }

                    // Calculate a score based on matching path components
                    $score = ($matching_parts / count($path_parts)) * 100;

                    if ($score > $best_score) {
                        $best_score = $score;
                        $best_match = $term;
                    }
                }

                if ($best_score > 50) { // Only return if we have a good match
                    return $best_match;
                }
            }
        }

        // If we have multiple terms but no URL, try to find the most likely one
        $preferred_taxonomies = ['category', 'post_tag', 'product_cat', 'product_tag'];
        foreach ($preferred_taxonomies as $tax) {
            foreach ($terms as $term) {
                $term = is_object($term) ? $term : (object)$term;
                if ($term->taxonomy === $tax) {
                    return $term;
                }
            }
        }

        // If all else fails, return the first term with the most posts
        usort($terms, function($a, $b) {
            $a_count = is_object($a) ? $a->count : 0;
            $b_count = is_object($b) ? $b->count : 0;
            return $b_count - $a_count;
        });

        return is_object($terms[0]) ? $terms[0] : (object)$terms[0];
    }
}
