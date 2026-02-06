<?php

/**
 * A holder for utility methods that are useful to multiple classes.
 * Not intended as a catch-all for any method that doesn't seem to have a place to live
 */
class Wpil_Toolbox
{
    private static $encryption_possible = null;
    private static $pillar_ids = null;
    private static $max_package_size = 0;


    public static function register(){
        add_action('wp_ajax_wpil_flush_object_cache', array(__CLASS__, 'ajax_flush_object_cache'));
    }

    /**
     * Check if OpenSSL is available and encryption is not disabled with filter.
     *
     * @return bool Whether encryption is possible or not.
     */
    public static function is_available(){
        if(null === self::$encryption_possible){
            self::$encryption_possible = extension_loaded('openssl');
        }

        return (bool) self::$encryption_possible;
    }

    /**
     * Get encryption key.
     *
     * @return string Key.
     */
    public static function get_key(){
        if(defined('WPIL_CUSTOM_ENCRYPTION_KEY') && '' !== WPIL_CUSTOM_ENCRYPTION_KEY){
            return WPIL_CUSTOM_ENCRYPTION_KEY;
        }

        if(defined('LOGGED_IN_KEY') && '' !== LOGGED_IN_KEY){
            return LOGGED_IN_KEY;
        }

        return '';
    }

    /**
     * Get salt.
     *
     * @return string Salt.
     */
    public static function get_salt(){
        if(defined('WPIL_CUSTOM_ENCRYPTION_SALT') && '' !== WPIL_CUSTOM_ENCRYPTION_SALT){
            return WPIL_CUSTOM_ENCRYPTION_SALT;
        }

        if(defined('LOGGED_IN_SALT') && '' !== LOGGED_IN_SALT){
            return LOGGED_IN_SALT;
        }

        return '';
    }

    /**
     * Encrypt data.
     * 
     * @param  mixed $value Original string.
     * @return string       Encrypted string.
     */
    public static function encrypt($value){
        if(!self::is_available()){
            return $value;
        }

        $method  = 'aes-256-ctr';
        $ciphers = openssl_get_cipher_methods();
        if(!in_array($method, $ciphers, true)){
            $method = $ciphers[0];
        }

        $ivlen = openssl_cipher_iv_length($method);
        $iv    = openssl_random_pseudo_bytes($ivlen);

        $raw_value = openssl_encrypt($value . self::get_salt(), $method, self::get_key(), 0, $iv);
        if(!$raw_value){
            return $value;
        }

        return base64_encode($iv . $raw_value);
    }

    /**
     * Decrypt string.
     *
     * @param  string $raw_value Encrypted string.
     * @return string            Decrypted string.
     */
    public static function decrypt($raw_value){
        if(!self::is_available()){
            return $raw_value;
        }

        $method  = 'aes-256-ctr';
        $ciphers = openssl_get_cipher_methods();
        if(!in_array($method, $ciphers, true)){
            $method = $ciphers[0];
        }

        $raw_value = base64_decode($raw_value, true);

        $ivlen = openssl_cipher_iv_length($method);
        $iv    = substr($raw_value, 0, $ivlen);

        $raw_value = substr($raw_value, $ivlen);

        if(!$raw_value || strlen($iv) !== $ivlen){
            return $raw_value;
        }

        $salt = self::get_salt();

        $value = openssl_decrypt($raw_value, $method, self::get_key(), 0, $iv);
        if(!$value || substr($value, - strlen($salt)) !== $salt && $salt !== ''){
            return $raw_value;
        }

        return (strlen($salt)) > 0 ? substr($value, 0, - strlen($salt)): $value;
    }

    /**
     * Recursively encrypt array of strings.
     *
     * @param  mixed $data Original strings.
     * @return string       Encrypted strings.
     */
    public static function deep_encrypt($data){
        if(is_array($data)){
            $encrypted = [];
            foreach($data as $key => $value){
                $encrypted[self::encrypt($key)] = self::deep_encrypt($value);
            }

            return $encrypted;
        }

        return self::encrypt($data);
    }

    /**
     * Recursively decrypt array of strings.
     *
     * @param  string $data Encrypted strings.
     * @return string       Decrypted strings.
     */
    public static function deep_decrypt($data){
        if(is_array($data)){
            $decrypted = [];
            foreach($data as $key => $value){
                $decrypted[self::decrypt($key)] = self::deep_decrypt($value);
            }

            return $decrypted;
        }

        return self::decrypt($data);
    }


    /**
     * Escapes strings for "LIKE" queries
     **/
    public static function esc_like($string = ''){
        global $wpdb;
        return '%' . $wpdb->esc_like($string) . '%';
    }

    /**
     * Gets if custom rules have been added to the .htaccess file
     **/
    public static function is_using_custom_htaccess(){
        // Check if a .htaccess file exists.
		if(defined('ABSPATH') && is_file(ABSPATH . '.htaccess')){
			// If the file exists, grab the content of it.
			$htaccess_content = file_get_contents(ABSPATH . '.htaccess');

			// Filter away the core WordPress rules.
			$filtered_htaccess_content = trim(preg_replace('/\# BEGIN WordPress[\s\S]+?# END WordPress/si', '', $htaccess_content));

            // return if there's anything still in the file
            return !empty($filtered_htaccess_content);
		}

        return false;
    }

    /**
     * Gets the current action hook priority that is being executed.
     * 
     * @return int|bool Returns the priority of the currently executed hook if possible, and false if it is not.
     **/
    public static function get_current_action_priority(){
        global $wp_filter;

        $filter_name = current_filter();
        if(isset($wp_filter[$filter_name])){
            $filter_instance = $wp_filter[$filter_name];
            if(method_exists($filter_instance, 'current_priority')){
                return $filter_instance->current_priority();
            }
        }

        return false;
    }

    /**
     * Checks if the link is relative.
     * Ported from URLChanger at version 2.1.6
     * 
     * @param string $link
     **/
    public static function isRelativeLink($link = ''){
        if(empty($link) || empty(trim($link))){
            return false;
        }

        if(strpos($link, 'http') === false && substr($link, 0, 1) === '/'){
            return true;
        }

        // parse the URL to see if it only contains a path
        $parsed = wp_parse_url($link);
        if( !isset($parsed['host']) && 
            !isset($parsed['scheme']) && 
            isset($parsed['path']) && !empty($parsed['path'])
        ){
            return true;
        }else{
            return false;
        }
    }

    /**
     * Attempts to clear the CDN cache for a specific post
     **/
    public static function attempt_cdn_clearing($post_id, $type){
        // exit if we're not supposed to be clearing the cache
        if(empty($post_id) || !Wpil_Settings::clear_cdn()){
            return;
        }

        // if WP Rocket is available
        if(function_exists('rocket_clean_post') && $type === 'post'){
            // try using it to clear the cache
            rocket_clean_post($post_id);
        }elseif(function_exists('rocket_clean_term') && $type === 'term'){
            $term = get_term($post_id);
            if(!empty($term) && !is_a($term, 'WP_Error')){
                rocket_clean_post($post_id, $term->taxonomy);
            }
        }else{
            self::clear_varnish_cache($post_id, $type);
        }
    }

    /**
     * Makes a call to attempt to clear the Varnish cache for a specific post
     **/
    public static function clear_varnish_cache($post_id, $type = 'post'){
        // create our post object
        $post = new Wpil_Model_Post($post_id, $type);

        // try getting it's view link        
        $view_link = $post->getViewLink();

        // if that didn't work
        if(empty($view_link)){
            // exit
            return;
        }

		$url_parts = wp_parse_url($view_link);

        if(!isset($url_parts['host']) || empty($url_parts['host'])){
            return;
        }

        // obtain the information that we'll need to make the ping
        $protocol = ((isset($url_parts['scheme'])) ? $url_parts['scheme']: (is_ssl() ? 'https': 'http')) . '://';
        $host = $url_parts['host']; // todo consider pulling the site host if this misses.
        $path = (isset($url_parts['path'])) ? $url_parts['path'] : '';

        // create a list of addresses to ping
        $addresses = array(
            'localhost',
            '127.0.0.1',
            '::1'
        );

        // get the port that we'll be targeting and allow filtering
        $port = apply_filters('wpil_filter_varnish_purge_port', 6081);

        // if we have a port
        if(!empty($port) && is_numeric($port)){
            // add it to the host header
            $host . ':' . $port;
        }

		// go over the address list and ping each one
		foreach($addresses as $address) {

			// assemble the URL to ping
			$call_url = $protocol . $address . $path;

            // assemble the headers
            $headers = 	array(
                'sslverify' => false,
                'method'    => 'PURGE',
                'headers'   => array(
                    'host'           => $host,
                    'X-Purge-Method' => 'default',
                ),
            );

            // make the call
			wp_remote_request($call_url, $headers);
		}
    }

    /**
     * Triggers a post update after clearing the post cache to _hopefully_ get around caching issues.
     * Only focussing on clearing caches for posts, there doesn't seem to be much need on terms
     **/
    public static function trigger_clean_post_update($post_id, $type = 'post'){
        // exit if we're not supposed to be updating the post
        if(empty($post_id) || !Wpil_Settings::update_post_after_actions()){
            return;
        }

        if($type === 'post'){
            // delete the existing cache for this post
            wp_cache_delete($post_id, 'posts');
            // get a fresh version from the DB to make sure it exists
            $post = get_post($post_id);
            // if it does and there were no issues
            if(!empty($post) && !is_a($post, 'WP_Error')){
                // "update" the post
                wp_update_post(array(
                    'ID' => $post->ID
                ));
            }
        }
    }

    /**
     * Attempts to flush any active object cache
     **/
    public static function attempt_object_cache_flush(){
        global $wp_object_cache;

        // exit if we're not supposed to be clearing the cache
        if(!Wpil_Settings::flush_object_cache()){
            return;
        }

        if(!empty($wp_object_cache) && method_exists($wp_object_cache, 'flush')){
            try {
                $wp_object_cache->flush();
            } catch (Throwable $t) {
            } catch (Exception $e) {
            }
        }
    }

    /**
     * Attempts to flush any active object cache via AJAX
     **/
    public static function ajax_flush_object_cache(){
        // if:
        if( is_admin() && // we're in the admid
            isset($_POST['nonce']) && // we have a nonce
            wp_verify_nonce($_POST['nonce'], 'wpil-flush-object-cache') && // the nonce is good
            current_user_can(apply_filters('wpil_filter_main_permission_check', 'manage_categories', Wpil_Base::get_current_page()))) // and the user can use LinkWhisper
        {
            // flush the cache
            self::attempt_object_cache_flush();
        }
        die();
    }

    /**
     * Checks to see if the current post is a pillar content post.
     * Currently only checks for Rank Math setting
     * 
     * @param int $post_id The id of the post that we're checking
     * @return bool Is this pillar content?
     **/
    public static function check_pillar_content_status($post_id = 0){
        global $wpdb;
        
        if(empty($post_id) || !defined('RANK_MATH_VERSION')){
            return false;
        }

        if(is_null(self::$pillar_ids)){
            $ids = $wpdb->get_col("SELECT DISTINCT `post_id` FROM {$wpdb->postmeta} WHERE `meta_key` = 'rank_math_pillar_content' AND `meta_value` = 'on'");
            self::$pillar_ids = (!empty($ids)) ? $ids: array();
        }

        return in_array($post_id, self::$pillar_ids);
    }

    /**
     * Optimizes the option table if the user has enabled optimizing of the table.
     * This is firing the "OPTIMIZE TABLE" command on the options table, not removing temp data or cleaning up rows of data
     **/
    public static function maybe_optimize_options_table(){
        global $wpdb;
        
        // if the user hasn't opted to optimize the options table or we're waiting for the optimizing to cooldown
        if(!Wpil_Settings::get_if_options_should_optimize()){
            // exit
            return;
        }

        // get the options table information
        $option_status = $wpdb->get_row("SHOW TABLE STATUS WHERE Name = '{$wpdb->options}'");
        if( !empty($option_status) &&               // if there's data
            isset($option_status->Engine) &&        // and the engine is set
            $option_status->Engine === 'InnoDB' &&  // and we're working with an "InnoDB" table
            isset($option_status->Data_free) &&     // and there's overhead
            $option_status->Data_free > 1000000000  // and there's more than a GB tied up in overhead
        ){
            $optimize = $wpdb->get_row("OPTIMIZE TABLE `{$wpdb->options}`");
        }
    }

    /**
     * Compresses and base64's the given data so it can be saved in the db.
     * 
     * @param string $data The data to be compressed
     * @return null|string Returns a string of compressed and base64 encoded data 
     **/
    public static function compress($data = false){
        // first serialize the data
        $data = serialize($data);

        // if zlib is available
        if(extension_loaded('zlib')){
            // use it to compress the data
            $data = gzcompress($data);
        }elseif(extension_loaded('Bz2')){// if zlib isn't available, but bzip2 is
            // use that to compress the data
            $data = bzcompress($data);
        }

        // now base64 and return the (hopefully) compressed data
        return base64_encode($data);
    }

    /**
     * Decompresses stored data that was compressed with compress.
     * 
     * @param string $data The data to be decompressed
     * @return mixed $data 
     **/
    public static function decompress($data){
        // if there's no data or it's not a string
        if(empty($data) || !is_string($data)){
            // return the data unchanged
            return $data;
        }elseif(!Wpil_Link::checkIfBase64ed($data, true)){
            // if the data is not base64ed, try unserializing it when we send it back
            return maybe_unserialize($data);
        }

        // first un-64 the data
        $data = base64_decode($data);
        // then determine what our flavor of encoding is and decode the data
        // if zlib is available
        if(extension_loaded('zlib')){
            // if the data is zipped
            if(self::is_gz_compressed($data)){
                // use it to decompress the data
                $data = gzuncompress($data);
            }
        }elseif(extension_loaded('Bz2')){// if zlib isn't available, but bzip2 is
            // use that to decompress the data
            $data = bzdecompress($data);
        }

        // and return our unserialized and hopefully de-compressed data
        return maybe_unserialize($data);
    }

    /**
     * Compresses and base64's the given data so it can be saved in the db.
     * Compresses to JSON for plain datasets that don't require intact objects
     * 
     * @param string $data The data to be compressed
     * @return null|string Returns a string of compressed and base64 encoded data 
     **/
    public static function json_compress($data = false, $basic_compress = false){
        // first serialize the data

        // if this is basic data
        if($basic_compress){
            $data = self::super_basic_json_encode($data);
        }else{
            $data = json_encode($data);
        }

        // if zlib is available
        if(extension_loaded('zlib')){
            gc_collect_cycles();
            // use it to compress the data
            $data = gzcompress($data);
        }elseif(extension_loaded('Bz2')){// if zlib isn't available, but bzip2 is
            // use that to compress the data
            gc_collect_cycles();
            $data = bzcompress($data);
        }

        // now base64 and return the (hopefully) compressed data
        return base64_encode($data);
    }

    /**
     * Decompresses stored data that was compressed with compress.
     * 
     * @param string $data The data to be decompressed
     * @return mixed $data 
     **/
    public static function json_decompress($data, $return_assoc = null, $basic_decompress = false){
        if(empty($data) || !is_string($data) || !Wpil_Link::checkIfBase64ed($data, true)){
            return $data;
        }

        // first un-64 the data
        $data = base64_decode($data);
        // then determine what our flavor of encoding is and decode the data
        // if zlib is available
        if(extension_loaded('zlib')){
            // if the data is zipped
            if(self::is_gz_compressed($data)){
                // use it to decompress the data
                gc_collect_cycles();
                $data = gzuncompress($data);
            }
        }elseif(extension_loaded('Bz2')){// if zlib isn't available, but bzip2 is
            // use that to decompress the data
            gc_collect_cycles();
            $data = bzdecompress($data);
        }

        // and return our unserialized and hopefully de-compressed data
        if($basic_decompress){
            return self::super_basic_json_decode($data);
        }else{
            return json_decode($data, $return_assoc);
        }
    }

    /**
     * Gets post meta that _should_ be encoded and compressed and decompresses and decodes it before returning it
     **/
    public static function get_encoded_post_meta($id, $key, $single = false){
        $data = get_post_meta($id, $key, $single);

        if(!empty($data) && is_string($data)){
            // do a double check just to make sure that plain serialized data hasn't been handed to us
            if(is_serialized($data)){
                $data = maybe_unserialize($data);
            }else{
                $dat = self::decompress($data);
                if($dat !== false && $dat !== $data){
                    $data = $dat;
                }
            }
        }

        return $data;
    }

    /**
     * Compresses and encodes object and array based meta data and then saves it
     **/
    public static function update_encoded_post_meta($id, $key, $data, $prev_value = ''){
        if(!empty($data) && (is_array($data) || is_object($data))){
            $dat = self::compress($data);
            if(!empty($dat) && $dat !== $data){
                $data = $dat;
            }
        }

        update_post_meta($id, $key, $data, $prev_value);
    }

    /**
     * Gets term meta that _should_ be encoded and compressed and decompresses and decodes it before returning it
     **/
    public static function get_encoded_term_meta($id, $key, $single = false){
        $data = get_term_meta($id, $key, $single);

        if(!empty($data) && is_string($data)){
            // do a double check just to make sure that plain serialized data hasn't been handed to us
            if(is_serialized($data)){
                $data = maybe_unserialize($data);
            }else{
                $dat = self::decompress($data);
                if($dat !== false && $dat !== $data){
                    $data = $dat;
                }
            }
        }

        return $data;
    }

    /**
     * Compresses and encodes object and array based term meta data and then saves it
     **/
    public static function update_encoded_term_meta($id, $key, $data, $prev_value = ''){
        if(!empty($data) && (is_array($data) || is_object($data))){
            $dat = self::compress($data);
            if(!empty($dat) && $dat !== $data){
                $data = $dat;
            }
        }

        update_term_meta($id, $key, $data, $prev_value);
    }

    /**
     * Helper function. Checks to see if a supplied string is gzcompressed
     * @return bool
     **/
    public static function is_gz_compressed($encoded = ''){
        // first confirm that we're dealing with a possibly encoded string
        if(empty(trim($encoded)) || !is_string($encoded) || strlen($encoded) < 2){
            return false;
        }

        $header = substr($encoded, 0, 2);

        // check to make sure that the header is valid
        $byte1 = ord(substr($encoded, 0, 1));
        $byte2 = ord(substr($encoded, 1, 1));

        if(($byte1 * 256 + $byte2) % 31 !== 0){
            return false;
        }

        // check it against the most common zlib headers
        $zlib_headers = array("\x78\x01", "\x78\x9C", "\x78\xDA", "\x78\x20", "\x78\x5E");
        foreach($zlib_headers as $zheader){
            if($header === $zheader){
                return true;
            }
        }

        // if the first pass didn't work, try checking against less common but still possible headers
        $zlib_headers = array(
            "\x08\x1D",   "\x08\x5B",   "\x08\x99",   "\x08\xD7",
            "\x18\x19",   "\x18\x57",   "\x18\x95",   "\x18\xD3",
            "\x28\x15",   "\x28\x53",   "\x28\x91",   "\x28\xCF",
            "\x38\x11",   "\x38\x4F",   "\x38\x8D",   "\x38\xCB",
            "\x48\x0D",   "\x48\x4B",   "\x48\x89",   "\x48\xC7",
            "\x58\x09",   "\x58\x47",   "\x58\x85",   "\x58\xC3",
            "\x68\x05",   "\x68\x43",   "\x68\x81",   "\x68\xDE"
        );

        foreach($zlib_headers as $zheader){
            if($header === $zheader){
                return true;
            }
        }

        return false;
    }

    public static function output_dropdown_wrapper_atts($data = array()){
        if(empty($data) || !isset($data['report_type'])){
            return;
        }
        $output = '';
        switch($data['report_type']){
            case 'autolinks':
                if(isset($data['keyword_id'])){
                    $output .= ' data-keyword-id="' . (int)$data['keyword_id'] . '"';
                }
                if(isset($data['keyword'])){
                    $output .= ' data-keyword="' . esc_attr($data['keyword']) . '"';
                }
                if(isset($data['dropdown_type'])){
                    $output .= ' data-dropdown-type="' . esc_attr($data['dropdown_type']) . '"';
                }
                break;
            case 'links':
                if(isset($data['post_id'])){
                    $output .= ' data-wpil-report-post-id="' . (int)$data['post_id'] . '"';
                }
                if(isset($data['post_type'])){
                    $output .= ' data-wpil-report-post-type="' . esc_attr($data['post_type']) . '"';
                }
                break;
            default:
                break;
        }

        if(isset($data['nonce']) && !empty($data['nonce'])){
            $output .= ' data-wpil-collapsible-nonce="' . esc_attr($data['nonce']) . '"';
        }

        return $output;
    }

    /**
     * Takes an array of inline styles and validates them to make sure that we don't output anything we don't want to.
     * Also stringifies the styles so we can easily stick them in a tag
     * 
     * Expects the args to be 'property_name' => 'value'
     * Returns measurements in 'px'
     * 
     **/
    public static function validate_inline_styles($styles = array(), $create_style_tag = false){
        $output = '';
        
        if(empty($styles) || !is_array($styles)){
            return $output;
        }

        foreach($styles as $property_name => $value){
            switch ($property_name) {
                case 'height':
                case 'width':
                    $output .= $property_name . ':' . intval($value) . 'px; ';
                    break;
                case 'fill':
                case 'stroke':
                    preg_match('/#(?:[A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})/', $value, $color);
                    if(isset($color[0]) && !empty($color[0])){
                        $output .= $property_name . ':' . $color[0] . '; ';
                    }
                    break;
                case 'display':
                    switch($value){
                        case 'block':
                        case 'inline-block':
                        case 'inline':
                        case 'flex':
                            $output .= $property_name . ':' . $value . '; ';
                        break;
                    }
                    break;
                default:
                    break;
            }
        }

        $output = trim($output);

        if($create_style_tag){
            $output = 'style="' . $output . '"';
        }

        return $output;
    }

    /**
     * Converts the site's date format into a format we can use in our JS calendars.
     * Confirms that the format contains Months, Days and Years, as well as confirming that the user has a set date format.
     * If any of these aren't true, it defaults to the normal MM/DD/YYYY format
     **/
    public static function convert_date_format_for_js(){
        $format = get_option('date_format', 'F d, Y');
        $day = false;
        $month = false;
        $year = false;

        $new_format = '';
        for($i = 0; $i < strlen($format); $i++){
            if(!empty($format[$i])){
                switch($format[$i]){
                    case 'd':
                    case 'j':
                        $new_format .= 'DD/';
                        $day = true;
                        break;
                    case 'F':
                    case 'm':
                    case 'n':
                        $new_format .= 'MM/';
                        $month = true;
                        break;
                    case 'M':
                        $new_format .= 'MMM/';
                        $month = true;
                        break;
                    case 'y':
                        $new_format .= 'YY/';
                        $year = true;
                        break;
                    case 'x':
                    case 'X':
                    case 'Y':
                        $new_format .= 'YYYY/';
                        $year = true;
                        break;
                }
            }
        }

        $new_format = trim($new_format, '/');

        return !empty($new_format) && ($day && $month && $year) ? $new_format: 'MM/DD/YYYY';
    }

    /**
     * Reconverts the site's date format from the JS to one useable by PHP.
     * That way, we'll be sure that both formats add up when we use them
     **/
    public static function convert_date_format_from_js(){
        $format = self::convert_date_format_for_js();

        $bits = explode('/', $format);
        $new_format = '';
        foreach($bits as $bit){
            if(!empty($bit)){
                switch($bit){
                    case 'DD':
                        $new_format .= 'd/';
                        break;
                    case 'MM':
                        $new_format .= 'm/';
                        break;
                    case 'MMM':
                        $new_format .= 'M/';
                        break;
                    case 'YY':
                        $new_format .= 'y/';
                        break;
                    case 'YYYY':
                        $new_format .= 'Y/';
                        break;
                }
            }
        }

        $new_format = trim($new_format, '/');

        return !empty($new_format) ? $new_format: 'd/m/y';
    }

    /**
     * Gets all post ids that are related to the current post.
     * Pulls the post's parent id, and all of it's sibling post ids.
     * @param object Wpil_Modal_Post post object
     * @return array
     **/
    public static function get_related_post_ids($post = array()){
        global $wpdb;

        if(empty($post) || (isset($post->type) && $post->type === 'term')){
            return array();
        }

        $ids = array();
        $ancestors = get_post_ancestors($post->id);

        if(!empty($ancestors)){
            $ancestors = array_map(function($id){ return (int) $id; }, $ancestors);
            $ids = $ancestors;
            $ancestors = implode(',', $ancestors);
            $results = $wpdb->get_col("SELECT DISTINCT ID FROM {$wpdb->posts} WHERE `post_parent` IN ($ancestors)");

            if(!empty($results)){
                $ids = array_merge($ids, $results);
            }
        }

        $children = get_children(array('post_parent' => $post->id));

        if(!empty($children)){
            $ids[] = $post->id;
            foreach($children as $child){
                $ids[] = $child->ID;
                $grandchildren = get_children(array('post_parent' => $child->ID));
                if(!empty($grandchildren)){
                    foreach($grandchildren as $grandchild){
                        $ids[] = $grandchild->ID;
                    }
                }
            }
        }

        if(!empty($ids)){
            $ids = array_flip(array_flip($ids));
        }

        return $ids;
    }

    /**
     * 
     **/
    public static function get_site_meta_row_count(){
        global $wpdb;

        return $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta}");
    }

    public static function wildcard_field_check($field = '', $search_fields = array()){
        if(empty($field) || empty($search_fields) || !is_string($field) || !is_array($search_fields)){
            return false;
        }


        foreach($search_fields as $search_field){
            // first, do the easy and check if the field is inside the search field list
            if($field === $search_field){
                return true;
            }

            $wildcard_start = strpos($search_field, '%');
            $wildcard_end = strrpos($search_field, '%');
            $trimmed_field = trim($search_field, '%');

            if(false !== $wildcard_start){
                if(false !== $wildcard_start && false !== $wildcard_end && $wildcard_start !== $wildcard_end){
                    if(false !== strpos($field, $trimmed_field)){
                        return true;
                    }
                }elseif(0 === $wildcard_start){ // if the wildcard is at the start of the search field
                    // and the search field does appear at the end of the field
                    if(strlen($field) === (strrpos($field, $trimmed_field) + strlen($trimmed_field))){
                        return true;
                    }
                }elseif(strlen($search_field) === $wildcard_start + 1){ // if the wildcard is at the end of the field
                    // and the search field does appear at the beginning of the field
                    if(0 === strpos($field, $trimmed_field)){
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Checks to see if two Wpil_Model_Link objects represent the same link
     **/
    public static function compare_link_objects($link_one, $link_two){
        if(empty($link_one) || empty($link_two)){
            return false;
        }

        if(
            $link_one->host !== $link_two->host ||
            (int) $link_one->internal !== (int) $link_two->internal ||
            $link_one->location !== $link_two->location ||
            $link_one->link_whisper_created !== $link_two->link_whisper_created ||
            (int) $link_one->is_autolink !== (int) $link_two->is_autolink ||
            (int) $link_one->tracking_id !== (int) $link_two->tracking_id
        ){
            return false;
        }

        $formatting_level = Wpil_Settings::getContentFormattingLevel();
        if($formatting_level > 0){
            $formatted_anchor1 = trim(mb_ereg_replace('[^[:alpha:]]', '', html_entity_decode($link_one->anchor)));
            $formatted_anchor2 = trim(mb_ereg_replace('[^[:alpha:]]', '', html_entity_decode($link_two->anchor)));
//            $formatted_anchor1 = mb_ereg_replace('[^[:alpha:]\/\-]', '', html_entity_decode($link_one->anchor));
//            $formatted_anchor2 = mb_ereg_replace('[^[:alpha:]\/\-]', '', html_entity_decode($link_two->anchor));
        }

        if( $link_one->url === $link_two->url || 
            urldecode($link_two->url) === $link_one->url || 
//            $formatted_url === $link_one->url || 
            html_entity_decode($link_two->url) === $link_one->url ||
            urldecode(html_entity_decode($link_two->url)) === $link_one->url ||
            str_replace(['&'], ['&#038;'], $link_two->url) === $link_one->url
        ){
            if(trim($link_one->anchor) === trim($link_two->anchor) || !empty($formatted_anchor1) && !empty($formatted_anchor2) && $formatted_anchor1 === $formatted_anchor2){
                return true;
            }
        }

        return false;
    }

    /**
     * Gets the link context from the context int
     **/
    public static function get_link_context($context_int = 0){
        $context = 'normal';
        switch($context_int){
            case 1:
            case 2:
                $context = 'related-post-link';
                break;
            case 3:
                $context = 'page-builder-link';
        }

        return $context;
    }

    /**
     * Gets the content from multiple posts at once and then creates a list of those posts so other things can use them
     * Currently doens't support page builders...
     **/
    public static function get_multiple_posts_with_content($post_ids = array(), $remove_unprocessable = true){
        global $wpdb;

        if(empty($post_ids)){
            return array();
        }

        $post_ids = array_filter(array_map(function($id){ return (int)$id; }, $post_ids));

        if(empty($post_ids)){
            return array();
        }

        $posts = array_map(function($id){ return new Wpil_Model_Post($id); }, $post_ids);

        /*
        // if the Thrive plugin is active
        if(defined('TVE_PLUGIN_FILE') || defined('TVE_EDITOR_URL')){
            $thrive_active = get_post_meta($this->id, 'tcb_editor_enabled', true);
            if(!empty($thrive_active)){
                $thrive_content = Wpil_Editor_Thrive::getThriveContent($this->id);
                if($thrive_content){
                    $content = $thrive_content;
                }
            }

            if(get_post_meta($this->id, 'tve_landing_set', true) && $thrive_template = get_post_meta($this->id, 'tve_landing_page', true)){
                $content = get_post_meta($this->id, 'tve_updated_post_' . $thrive_template, true);
            }

            $this->editor = !empty($content) ? 'thrive' : null;
        }

        // if there's no content and the muffin builder is active
        if(empty($content) && defined('MFN_THEME_VERSION')){
            // try getting the Muffin content
            $content = Wpil_Editor_Muffin::getContent($this->id);
            $this->editor = !empty($content) ? 'muffin' : null;
        }

        // if there's no content and the goodlayer builder is active
        if(empty($content) && defined('GDLR_CORE_LOCAL')){
            // try getting the Goodlayer content
            $content = Wpil_Editor_Goodlayers::getContent($this->id);
            $this->editor = !empty($content) ? 'goodlayers' : null;
        }

        // if the Enfold Advanced editor is active
        if(defined('AV_FRAMEWORK_VERSION') && 'active' === get_post_meta($this->id, '_aviaLayoutBuilder_active', true)){
            // get the editor content from the meta
            $content = get_post_meta($this->id, '_aviaLayoutBuilderCleanData', true);
            $this->editor = !empty($content) ? 'enfold': null;
        }

        // if we have no content and Cornerstone is active
        if(empty($content) && class_exists('Cornerstone_Plugin')){
            // try getting the Cornerstone content
            $content = Wpil_Editor_Cornerstone::getContent($this->id);
            $this->editor = !empty($content) ? 'cornerstone': null;
        }

        // if we have no content
        if(empty($content) && 
            defined('ELEMENTOR_VERSION') && // Elementor is active
            class_exists('\Elementor\Plugin') &&
            isset(\Elementor\Plugin::$instance) && !empty(\Elementor\Plugin::$instance) && // and we have an instance
            isset(\Elementor\Plugin::$instance->db) && !empty(\Elementor\Plugin::$instance->db) && // and the instance has a db method?
            isset($this->id) && 
            !empty($this->id)){
            // check if the post was made with Elementor

            $document = Wpil_Editor_Elementor::getDocument($this->id);

            if (!empty($document) && $document->is_built_with_elementor()){
                // if it was, use the power of Elementor to get the content
                $content = Wpil_Editor_Elementor::getContent($this->id, true, $remove_unprocessable);
                $this->editor = !empty($content) ? 'elementor': null;
            }
        }

        // if WP Recipe is active and we're REALLY sure that this is a recipe
        if(defined('WPRM_POST_TYPE') && in_array('wprm_recipe', Wpil_Settings::getPostTypes()) && 'wprm_recipe' === get_post_type($this->id)){
            // get the recipe content
            $content = Wpil_Editor_WPRecipe::getPostContent($this->id);
            $this->editor = !empty($content) ? 'wp-recipe': null;
        }

        // Beaver Builder is active and this is a BB post
        if( defined('FL_BUILDER_VERSION') && 
            class_exists('FLBuilder') && 
            class_exists('FLBuilderModel') && 
            is_array(FLBuilderModel::get_admin_settings_option('_fl_builder_post_types')) && 
            in_array($this->getRealType(), FLBuilderModel::get_admin_settings_option('_fl_builder_post_types'), true) &&
            FLBuilderModel::is_builder_enabled($this->id)
        ){
            // try getting it's BB content
            $beaver = get_post_meta($this->id, '_fl_builder_data', true);
            if(!empty($beaver) && is_array($beaver)){
                // go over all the beaver content and create a long string of it
                foreach ($beaver as $key => $item) {
                    foreach (['text', 'html'] as $element) {
                        if (!empty($item->settings->$element) && !isset($item->settings->link)) { // if the element has content that we can process and isn't something that comes with a link
                            $content .= ("\n" . $item->settings->$element);
                        }
                    }
                }
                $content = trim($content);
                unset($beaver);
                $this->editor = !empty($content) ? 'beaver': null;
            }
        }

        if(empty($content) && Wpil_Editor_YooTheme::yoo_active()){
            $content = Wpil_Editor_YooTheme::getContent($this->id, $remove_unprocessable);
        }
*/

        $post_ids = array_map(function($id){ return (int) $id; }, $post_ids);

        $ids = implode(',', $post_ids);

        $post_data = $wpdb->get_results("SELECT * FROM {$wpdb->posts} WHERE `ID` IN ($ids)");

        foreach($post_data as $dat){
            foreach($posts as &$post){
                if((int)$dat->ID === (int)$post->id){

                    $content = (isset($dat->post_content) && !empty($dat->post_content)) ? $dat->post_content: "";
                    $content .= $post->getAddonContent();
                    $content .= (defined('WC_PLUGIN_FILE') && 'product' === $dat->post_type) ? $dat->post_excerpt: "";
                    $content .= $post->getAdvancedCustomFields($remove_unprocessable); // TODO: Think about creating a multiple getter for the ACF fields
                    $content .= $post->getMetaContent();
                    $post->editor = !empty($content) ? 'wordpress': null;
        
                    if(class_exists('ThemifyBuilder_Data_Manager')){
                        // if there's Themify static editor content in the post content
                        if(false !== strpos($content, 'themify_builder_static')){
                            // remove it
                            $content = mb_ereg_replace('<!--themify_builder_static-->[\w\W]*?<!--/themify_builder_static-->', '', $content);
                        }
                    }
/*
                    $content .= $post->getThemifyContent();
                    $oxy_content = Wpil_Editor_Oxygen::getContent($post->id, $remove_unprocessable);
                    if(!empty($oxy_content)){
                        $content .= $oxy_content;
                        $post->editor = 'oxygen';
                    }
*/
                    $post->content = $content;
                }
            }
        }

        return $posts;
    }

    /**
     * Checks to see if we're pretty sure that the current action was instagated by a REST call from an external source
     **/
    public static function is_doing_external_rest_api_action(){
        $doing_rest = false;

        if(!defined('REST_REQUEST')){
            return $doing_rest;
        }

        // if
        if(
            (isset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) && // the application user && password are set
            !empty($_SERVER['PHP_AUTH_USER']) && !empty($_SERVER['PHP_AUTH_PW'])) || // and not empty OR
            (isset($_SERVER['HTTP_AUTHORIZATION']) && !empty($_SERVER['HTTP_AUTHORIZATION'])) // the application user && password are set
        ){
            // we must be looking at a REST API created post action
            $doing_rest = true;
        }elseif(isset($_SERVER['REQUEST_URI']) && !empty($_SERVER['REQUEST_URI'])){ // if there is a path available
            // check if we're looking at the post|page endpoint
            $rest_url = trailingslashit(rest_url('/wp/v2/posts'));
            $request_url = trailingslashit($_SERVER['REQUEST_URI']);
            $pos = strrpos($rest_url, $request_url);
            if(false === $pos){
                $rest_url = trailingslashit(rest_url('/wp/v2/pages'));
                $pos = strrpos($rest_url, $request_url);
            }

            // if we are looking at a post|page endpoint, and there are no other parameters
            if(false !== $pos && strlen($rest_url) === ($pos + strlen($request_url))){
                // we must be looking at a REST API created post
                $doing_rest = true;
            }
        }

        return $doing_rest;
    }

    /**
     * Programatically generates an application password for a specific application
     **/
    public static function create_application_password_for_user($user_id, $app_name){
        // Ensure the necessary files are included
        if(!class_exists('WP_Application_Passwords')){
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }
    
        // make sure there isn't already a password with this name
        self::revoke_application_passwords_by_name($user_id, $app_name, true);

        // Generate a new application password
        $new_password = wp_generate_password(24, false); // 24 characters, no special characters
        $args = array(
            'name'     => $app_name,
            'password' => $new_password,
        );

        $item = WP_Application_Passwords::create_new_application_password($user_id, $args);

        if(is_wp_error($item)){
            // Handle error
            return $item;
        }

        // return the password so we can do something with it
        return $item[0];
    }

    /**
     * Revokes purpose specific application passwords for users by application name
     **/
    public static function revoke_application_passwords_by_name($user_id, $app_name, $clear = false){
        // Retrieve existing application passwords
        $passwords = get_user_meta($user_id, '_application_passwords', true);

        if(empty($passwords)){
            // No application passwords found
            return false;
        }
    
        // Filter out passwords that match the given name
        $updated_passwords = array_filter($passwords, function($password) use ($app_name, $clear){
            if( $password['name'] === $app_name && // if this is one of our named LW passwords AND
                ($clear ||  // we're supposed to clear all the LW named passwords OR
                !$clear && $password['created'] < (time() - (MINUTE_IN_SECONDS * 5) )))   // this password has expired
            {
                return false;
            }else{
                return true;
            }
        });
    
        // Update the user's application passwords
        update_user_meta($user_id, '_application_passwords', $updated_passwords);
    
        return true;
    }

    public static function is_over_memory_limit(){
        $memory_break_point = Wpil_Report::get_mem_break_point();
        return (('disabled' !== $memory_break_point && memory_get_usage() > $memory_break_point));
    }

    /**
     * Creates the text for the "Explain Page" tooltips
     **/
    public static function generate_tooltip_text($location = ''){
        $text = '';
        if(empty($location)){
            return '';
        }
        switch ($location) {
            /* Dashboard Report */
            case 'dashboard-intro':
                $text = esc_attr__('This is the Link Whisper Dashboard. It gives you a high-level overview of your site and quick access to all major reports.', 'wpil');
                break;
            case 'dashboard-report-tabs':
                $text = esc_attr__('Link Whisper\'s main reports are listed here.', 'wpil');
                break;
            case 'dashboard-link-report-tab':
                $text = esc_attr__('The Links Report shows every link on your site, organized by post. Use it to quickly create or remove links between your content.', 'wpil');
                break;
            case 'dashboard-domain-report-tab':
                $text = esc_attr__('The Domains Report breaks down your site’s links by domain. You can add/remove link attributes and bulk-delete links by domain.', 'wpil');
                break;
            case 'dashboard-click-report-tab':
                $text = esc_attr__('The Clicks Report displays all link clicks across your site, broken down by post, so you can see what’s getting traffic.', 'wpil');
                break;
            case 'dashboard-broken-links-report-tab':
                $text = esc_attr__('The Broken Links Report lists all detected broken links so you can fix or remove them.', 'wpil');
                break;
            case 'dashboard-visual-sitemaps-report-tab':
                $text = sprintf(esc_attr__('The Visual Sitemap Report uses charts to show how your posts are linked and what domains you\'re linking to.%sIf AI data is available, it also reveals related posts and linked products.', 'wpil'), '<br><br>');
                break;
            case 'dashboard-run-link-scan-button':
                $text = sprintf(esc_attr__('The "Run A Link Scan" button starts a full site scan to detect all links for use in reports.%sUsually only needed after major setting changes or if link data appears out of sync.', 'wpil'), '<br><br>');
                break;
            case 'dashboard-link-stats-widget':
                $text = esc_attr__('The Link Stats widget shows you a high-level overview of the site\'s posts and links.', 'wpil');
                break;
            case 'dashboard-report-loading-bar':
                $text = esc_attr__('The Scan Progress bar shows you the progress of the wizard\'s scanning. It also provides an estimate of how long it will be until the scan is complete.', 'wpil');
                break;
            case 'dashboard-link-stats-widget-posts-crawled-stat':
                $text = esc_attr__('The Posts Crawled stat says how many posts Link Whisper has scanned for links.', 'wpil');
                break;
            case 'dashboard-link-stats-widget-links-found-stat':
                $text = esc_attr__('The Links Found stat says how many links Link Whisper found while scanning.', 'wpil');
                break;
            case 'dashboard-link-stats-widget-internal-links-stat':
                $text = esc_attr__('The Internal Links stat displays the number of links pointing to other posts within your site.', 'wpil');
                break;
            case 'dashboard-link-stats-widget-orphaned-posts-stat':
                $text = sprintf(esc_attr__('Shows how many posts have no internal links pointing to them.%sClick to view the Orphaned Posts Report and quickly add links to them.', 'wpil'), '<br><br>');
                break;
            case 'dashboard-link-stats-widget-broken-links-stat':
                $text = sprintf(esc_attr__('The Broken Links stat says how many broken links have been detected on the site.%sClicking on the stat will take you to the Broken Links Report.', 'wpil'), '<br><br>');
                break;
            case 'dashboard-link-stats-widget-broken-videos-stat':
                $text = sprintf(esc_attr__('Shows how many broken video links have been found.%sClick to view any video issues on the Broken Links Report.', 'wpil'), '<br><br>');
                break;
            case 'dashboard-link-stats-widget-404-links-stat':
                $text = sprintf(esc_attr__('The 404 Errors stat show how many 404 links have been detected.%sClick to view them in the Broken Links Report.', 'wpil'), '<br><br>');
                break;
            case 'dashboard-domains-widget':
                $text = esc_attr__('The Most Linked To Domains widget shows you the domains that your site is linking to the most.', 'wpil');
                break;
            case 'dashboard-internal-external-links-widget':
                $text = esc_attr__('The Internal vs External links widget compares how many links point to posts on this site vs. links pointing to external sites.', 'wpil');
                break;
            /* Links Report */
            case 'link-report-header':
                $text = esc_attr__('This is the Link Whisper Internal Links Report. This report lists all of the posts on the site, and all of their links.', 'wpil');
                break;
            case 'link-report-filters':
                $text = esc_attr__('The filter controls allow you to choose what posts you want to see listed inside the Internal Links Report.', 'wpil') . '<br><br>' . esc_attr__('You can filter the posts listed in the report by:', 'wpil') . '<ul><li>' . esc_attr__('Post Type and Category.', 'wpil') . '</li><li>' . esc_attr__('The Number of Links Each Post Has.', 'wpil') . '</li><li>' . esc_attr__('A Combination of Post Type and Link Count.', 'wpil') . '</li></ul>';
                break;
            case 'link-report-table-search': // todo implement
                $text = esc_attr__('The search function allows you look for specific posts. You can search by either keyword or by post URL.', 'wpil');
                break;
            case 'link-report-export-buttons':
                $text = esc_attr__('These are the Link Report export buttons.', 'wpil') . '<br><br>' . esc_attr__('The Detailed Export exports a .CSV file containing each link for each post on the site. The links are divided into Inbound Internal, Outbound Internal and External link columns.', 'wpil') . '<br><br>' . esc_attr__('The Summary Export creates .CSV file that lists the total numbers of Inbound Internal, Outbound Internal and External links for each post.', 'wpil');
                break;
            case 'link-report-table':
                $text = esc_attr__('The Link Report table contains all of the posts that Link Whisper knows about, and shows their links. The links are broken down into Inbound Internal, Outbound Internal, and External links', 'wpil')/* . '<br><br>' . esc_attr__('Inbound Internal links are links that point to the post from another post on the site.', 'wpil') . '<br><br>' . esc_attr__('Outbound Internal links are links in the post that point to other posts on this site.', 'wpil') . '<br><br>' . esc_attr__('External links are links in the post that point to other sites.', 'wpil')*/;
                break;
            case 'link-report-table-title-col':
                $text = esc_attr__('The "Title" column lists each post\'s title, and is sortable. Sorting by the post title will order the posts alphabetically.', 'wpil');
                break;
            case 'link-report-table-published-col':
                $text = esc_attr__('The "Published" column lists each post\'s publish data, and is sortable. Sorting by the published date will order the posts by date.', 'wpil');
                break;
            case 'link-report-table-type-col':
                $text = esc_attr__('The "Type" column lists each post\'s "post type", and is sortable. Sorting by type will order the posts alphabetically according to post type.', 'wpil');
                break;
            case 'link-report-table-inbound-internal-links-col':
                $text = esc_attr__('The "Inbound Internal Links" column lists all of the links that are pointing to the current post from other posts on the site.', 'wpil') . '<br><br>' . esc_attr__('The column is sortable, and sorting by it will order the posts based on the number of links they have pointing to them.', 'wpil');
                break;
            case 'link-report-table-outbound-internal-links-col':
                $text = esc_attr__('The "Outbound Internal Links" column lists all of the links that the current post has that are pointing to other posts on the site.', 'wpil') . '<br><br>' . esc_attr__('The column is sortable, and sorting by it will order the posts based on the number of links they have pointing to other posts on the site.', 'wpil');
                break;
            case 'link-report-table-outbound-external-links-col':
                $text = esc_attr__('The "External Links" column lists all of the links that the current post has which are pointing to other sites and not to some other page on this site.', 'wpil') . '<br><br>' . esc_attr__('The column is sortable, and sorting by it will order the posts based on the number of links they have pointing to other sites.', 'wpil');
                break;
            case 'link-report-table-first-item':
            case 'link-report-table-first-item-add-outbound':
            case 'link-report-table-first-item-add-inbound':
            case 'link-report-table-first-item-inbound-dropdown-open':
            case 'link-report-table-first-item-inbound-dropdown-open-item-1':
            case 'link-report-table-first-item-':
            case 'link-report-table-first-item-':
            case 'link-report-table-first-item-':
            /* Domains Report */
            case 'domain-report-intro':
                $text = esc_attr__('The Link Whisper Domains Report shows you all of the links on your site grouped by the domain that they are pointing to.', 'wpil');
                break;
            case 'domain-report-export':
                $text = esc_attr__('The Domains Report Export buttons allow you to export the entire report to a .CSV file.', 'wpil') . '<br><br>' . esc_attr__('The "Detailed Export" exports all of the links in one big file, with each link getting it\'s own row in the spreadsheet. In addition to the link, information about its anchor, the domain that it points to, and the post that it\'s in is included in the export.', 'wpil') . '<br><br>' . esc_attr__('The "Summary Export" is a more condensed export which lists the domains that links are pointing to, the number of links pointing to each of them, and the number of posts that contain links for that specific domain.', 'wpil');
                break;
            case 'domain-report-search':
                $text = esc_attr__('The Search form allows you to search for specific links or domains that you want to see in the report.', 'wpil');
                break;
            case 'domain-report-search-domains':
                $text = esc_attr__('Selecting the option to search by "Domain" tells Link Whisper to find links with specific domains that match your search term.', 'wpil') . '<br><br>' . esc_attr__('This only searches for matching domains, so long URLs that contain the search term outside the domain won\'t be shown.', 'wpil');
                break;
            case 'domain-report-search-links':
                $text = esc_attr__('Selecting the option to search by "Links" tells Link Whisper to search for links that contain your search term somewhere in it\'s URL.', 'wpil') . '<br><br>' . esc_attr__('This is a broader search than the "Domain" search, and will show links that have matches in either the domain or the URL path.', 'wpil');
                break;
            case 'domain-report-search-show-untargetted':
                $text = esc_attr__('Selecting the option to search by "Show Untargeted" tells Link Whisper to find all the internal links that couldn\'t be traced to a specific post. In most cases, these are 404 links that haven\'t been redirected and should be updated.', 'wpil');
                break;
            case 'domain-report-table':
                $text = esc_attr__('This is the Domain Report Table, and it shows all the domains that you\'re currently linking to on this site.', 'wpil') . '<br><br>' . esc_attr__('It also allows you to set domain-specific link attributes, see what posts have links for each domain, and what the individual links are.', 'wpil');
                break;
            case 'domain-report-table-domain-col':
            case 'domain-report-table-attr-col':
                $text = esc_attr__('The "Applied Domain Attributes" column allows you to set link attributes all of the links pointing to a specific domain. The attributes that you set here will override the attributes in the links themselves, so it\'s a great way to update old links, or to make sure all your links are behaving uniformly', 'wpil') . '<br><br>' . esc_attr__('For example, if you were linking to "wikipedia.org", and you wanted to make all of those links "nofollow" to be sure Google stays on your site and doesn\t follow them. You can set the "No Follow" attribute for wikipedia, and then click the blue "Update" button to set that attribute for all links pointing to wikipedia.', 'wpil');
                break;
            case 'domain-report-table-posts-col':
                $text = esc_attr__('The "Posts" column shows you all the posts that contain links for the current domain.', 'wpil');
                break;
            case 'domain-report-table-links-col':
                $text = esc_attr__('The "Links" column shows you all of the links that Link Whisper has dectected for the current domain.', 'wpil') . '<br><br>' . esc_attr__('From the dropdown, you can see more information about each detected link, quickly check out the link on the frontend, change its URL, or delete it.', 'wpil') . '<br><br>' . esc_attr__('You can quick delete all of the links from a specific domain by opening the dropdown, clicking on the "Select All" checkbox, and then clicking the "Delete Selected" button. This is a great way of quickly removing links to dead sites, or domains that you no longer wish to link to.', 'wpil');
                break;
            /* Clicks Report */
            case 'click-report-intro':
                $text = esc_attr__('The Link Whisper Clicks Report shows you all the link clicks that Link Whisper has tracked on your website.', 'wpil');
                break;
            case 'click-report-filter':
                $text = esc_attr__('The post type filter allows you to filter the report table so that it only shows you posts from a specific post type.', 'wpil');
                break;
            case 'click-report-export':
                $text = esc_attr__('The Click Report Export buttons allow you to export the last 30 days of clicks recorded in the report to a .CSV file.', 'wpil') . '<br><br>' . esc_attr__('The "Detailed Export" exports a list of all of the top-clicked links, broken down by the post that they\'re in. In addition, the export lists the link\'s URL, the number of clicks that it\'s gotten, and when the link was created if known.', 'wpil') . '<br><br>' . esc_attr__('The "Summary Export" is a more condensed export which lists the total number of clicks the links on each post have recieved.', 'wpil');
                break;
            case 'click-report-erase-data':
                $text = esc_attr__('The "Erase Click Data" button tells Link Whisper to delete all of the click data stored in the database.', 'wpil') . '<br><br>' . esc_attr__('Please only delete this data when you are sure that you won\'t be needing it.', 'wpil');
                break;
            case 'click-report-search':
            case 'click-report-table':
                $text = esc_attr__('This is the Clicks Report table. It lists all of the posts that Link Whisper is set to monitor link clicks on, and shows you the high-level results of the tracking.', 'wpil');
                break;
            case 'click-report-table-post-col':
                $text = esc_attr__('The "Post" column shows the title of each post that Link Whisper is tracking link clicks on.', 'wpil') . '<br><br>' . esc_attr__('The column is sortable, and clicking on it will sort the listed posts alphabetically.', 'wpil');
                break;
            case 'click-report-table-published-col':
                $text = esc_attr__('The "Published" column shows when each of the posts was published.', 'wpil') . '<br><br>' . esc_attr__('This column is sortable and clicking on it will sort the posts according to age.', 'wpil');
                break;
            case 'click-report-table-type-col':
                $text = esc_attr__('The "Post Type" column shows the post type of each of the listed posts.', 'wpil') . '<br><br>' . esc_attr__('The column is sortable, and clicking on it will sort the posts alphabetically by post type.', 'wpil');
                break;
            case 'click-report-table-link-clicks-col':
                $text = esc_attr__('The "Link Clicks" column shows number of clicks tracked for each post.', 'wpil') . '<br><br>' . esc_attr__('Clicking on a dropdown containing link clicks will tell you more about the clicks that have been tracked, and will allow you to go to the "Detailed Click Report" area.', 'wpil') . '<br><br>' . esc_attr__('The column is sortable, and clicking on it will sort the posts based on the number of clicks each post has tracked.', 'wpil');
                break;
            case 'click-report-table-link-clicks-dropdown-open':
            /* Broken Links Report */
            case 'broken-link-report-intro':
                $text = esc_attr__('The Link Whisper Broken Links Report lists all of the broken links that it has detected on this site.', 'wpil');
                break;
            case 'broken-link-report-codes':
                $text = esc_attr__('The "Status Codes" selector allows you to pick the kinds of broken links shown in the report table, based on error code', 'wpil') . '<br><br>' . esc_attr__('By default, the report shows 404, 501, and 503 links, as well as links that point to sites that no longer exist or are misformatted.', 'wpil') . '<br><br>' . esc_attr__('To change what\'s shown in the report table, please select the kinds of broken links that you want to see, and then click the "Search" button.', 'wpil');
                break;
            case 'broken-link-report-filter':
                $text = esc_attr__('', 'wpil');
                break;
            case 'broken-link-report-export':
                $text = esc_attr__('The "Export to CSV" button allows you to export all of the broken links shown in the report to a .CSV file.', 'wpil') . '<br><br>' . esc_attr__('The export file will contain links from all the statuses selected in the "Status Codes" setting.', 'wpil') . '<br><br>' . esc_attr__('So if for example you only want to export 404 links, you can do it by selecting only the 404 error code in the "Status Codes" selector and then clicking the "Export to CSV" button.', 'wpil');
                break;
            case 'broken-link-report-scan-links':
                $text = esc_attr__('The "Scan for Broken Links" button tells Link Whisper to clear the old broken link data, and rescan the site for broken links.', 'wpil');
                break;
            case 'broken-link-report-table':
                $text = esc_attr__('This is the Broken Links Report table. It lists all of the broken links that Link Whisper has detected on your site, and allows you to modify or delete the links as needed.', 'wpil');
                break;
            case 'broken-link-report-table-bulk-delete':
                $text = esc_attr__('This is the link Delete Selected button, it enables you to delete many broken links quickly and easily.', 'wpil') . '<br><br>' . esc_attr__('To do this, simply click on the checkboxes next to the links that you want to delete, and then click the button.', 'wpil') . '<br><br>' . esc_attr__('Link Whisper will then go into each post, and will remove the broken link from it\'s content.', 'wpil');
                break;
            case 'broken-link-report-table-checkbox-col':
                $text = esc_attr__('These are the broken link selection checkboxes. Clicking on one of them will "select" the broken link so that it can be quickly deleted with the "Delete Selected" button.', 'wpil');
                break;
            case 'broken-link-report-table-post-col':
                $text = esc_attr__('The "Post" column shows the titles of the post where the broken link is located.', 'wpil') . '<br><br>' . esc_attr__('The column is sortable, and clicking on it will sort the listed posts alphabetically by title.', 'wpil');
                break;
            case 'broken-link-report-table-post-type-col':
                $text = esc_attr__('The "Post Type" column lists the "post type" of the post where the broken link is located.', 'wpil');
                break;
            case 'broken-link-report-table-broken-url-col':
                $text = esc_attr__('The "Broken URL" column shows the broken link\'s URL so you can easily see where it is pointing to.', 'wpil') . '<br><br>' . esc_attr__('If you hover over the URL, you\'ll see a hover menu appear with the options to "Ignore" and "Edit".', 'wpil') . '<br><br>' . esc_attr__('Clicking on the "Ignore" option will tell Link Whisper that the link isn\'t broken and that it should not list the link in the report.', 'wpil') . '<br><br>' . esc_attr__('Clicking on the "Edit" button will open the URL editor for the link and will allow you to update the link\'s URL so that it is no longer broken.', 'wpil');
                break;
            case 'broken-link-report-table-anchor-col':
                $text = esc_attr__('The "Anchor" column shows the anchor text for the broken link so you can see what the link\'s text is.', 'wpil');
                break;
            case 'broken-link-report-table-sentence-col':
                $text = esc_attr__('The "Sentence" column shows the larger sentence that the broken link is in to give an idea of the context that it exists in.', 'wpil');
                break;
            case 'broken-link-report-table-type-col':
                $text = esc_attr__('The "Type" column says if a broken link is internal or external. Internal links point to other posts on the site, while external links point to other sites.', 'wpil') . '<br><br>' . esc_attr__('The column is sortable, and clicking on it will sort the broken links by if they are internal or external.', 'wpil');
                break;
            case 'broken-link-report-table-status-col':
                $text = esc_attr__('The "Status" column says what kind of broken link the current broken link is.') . '<br><br>' . esc_attr__('For example, if the broken link is a "404" type, the status will be "404 Not Found". If it was a "403" type, the status will be "403 Forbidden".') . '<br><br>' . esc_attr__('Currently, there are 48 different error codes that Link Whisper tracks.', 'wpil');
                break;
            case 'broken-link-report-table-discovered-col':
                $text = esc_attr__('The "Discovered" column says when Link Whisper detected the broken link.', 'wpil');
                break;
            case 'broken-link-report-table-delete-col':
                $text = esc_attr__('The blue "X" buttons allow you to quickly delete a single broken link. Just click on the "X", and Link Whisper will remove the broken link from the post that it\'s on.', 'wpil');
                break;
            /* Visual Sitemaps */
            case 'visual-sitemap-report-intro':
                $text = esc_attr__('The Visual Sitemaps Report is where you can see the sitemaps that Link Whisper creates to provide you with a visual representation of your site.', 'wpil');
                break;
            case 'visual-sitemap-report-generate-initial-maps':
            case 'visual-sitemap-report-generate-maps':
                $text = esc_attr__('Clicking on the "Generate Sitemaps" button will tell Link Whisper to clear the old sitemap data, and generate new maps. This is very helpful if you see that there are standard sitemaps missing or the standard sitemaps are out of sync with the site.', 'wpil') . '<br><br>' . esc_attr__('Regenerating the Sitemaps won\'t affect sitemaps that you create via .CSV upload. Those are managed from the "Manage Custom Sitemaps" section.', 'wpil');
                break;
            case 'visual-sitemap-report-select-maps':
                $text = esc_attr__('The "Select Sitemap" dropdown allows you to select the sitemap you want to see in the Sitemap field.', 'wpil');
                break;
            case 'visual-sitemap-report-display-map':
                $text = esc_attr__('After selecting a sitemap, you\'ll need to click on this "Display Sitemap" button to tell Link Whisper to show the selected sitemap in the Sitemap Field.', 'wpil');
                break;
            case 'visual-sitemap-report-manage-sitemaps':
                $text = esc_attr__('The "Manage Custom Sitemaps" section allows you to create and delete your own custom sitemaps.', 'wpil');
                break;
            case 'visual-sitemap-report-labels':
                $text = esc_attr__('The "Show/Hide Labels" button allows you to tell Link Whisper if it should show post titles in the sitemaps or not.', 'wpil') . '<br><br>' . esc_attr__('On very large sitemaps, it can be helpful to turn off the labels so it\'s easier to see the data.', 'wpil');
                break;
            case 'visual-sitemap-report-filter-maps':
                $text = esc_attr__('The "Filter Sitemap Data" allows you to filter sitemaps so they only show posts that have titles matching your search term.', 'wpil');
                break;
            case 'visual-sitemap-table':
                $text = esc_attr__('This is the Sitemap Field, and it\'s where Link Whisper will display all of your sitemaps.', 'wpil') . '<br><br>' . esc_attr__('You can zoom the field in and out to get a better look at the map, as well click-drag the window around to naviagte the map.', 'wpil') . '<br><br>' . esc_attr__('Right clicking on any of the post "dots" will open a quick menu that you can use to create links for the post that you clicked on.', 'wpil');
                break;
            case 'visual-sitemap-table-settings-menu':
                $text = esc_attr__('The Sitemap Settings give you advanced control over the sitemap currently being displayed.', 'wpil') . '<br><br>' . esc_attr('<a href="https://linkwhisper.com/knowledge-base/what-are-the-sitemap-settings-and-what-do-they-do/" target="_blank">' . __('You can read more about the settings, and how they work, here.', 'wpil') . '</a>');
                break;
            /* Post Edit: Related Posts */
            case 'related-posts-intro':
                $text = esc_attr__('The Link Whisper Related Posts section allows you to manually control what posts are shown inside the Related Posts widget for this post.', 'wpil') . '<br><br>' . esc_attr__('You can select as many posts as you want to see listed in this post\'s widget, and clicking the "Refresh Auto Selected Links" inside of the Related Post Settings area won\'t reset them.', 'wpil');
                break;
            case 'related-posts-active':
                $text = esc_attr__('This checkbox allows you to set if the Related Posts widget is active for this post or not.', 'wpil') . '<br><br>' . esc_attr__('Unchecking it will turn off the Related Posts widget for this post, and it won\'t affect other posts.', 'wpil');
                break;
            case 'related-posts-current-related':
                $text = esc_attr__('This area shows all of the posts that are currently being linked to by this post\'s Related Posts widget. To remove a post from the widget, simply uncheck a post and click the "Update Related Posts" button.', 'wpil') . '<br><br>' . esc_attr__('(Clicking on the "Update Post" button won\'t save the selection.)', 'wpil');
                break;
            case 'related-posts-search':
                $text = esc_attr__('This is the Related Posts search area, and it allows you to search for posts to include in the Related Posts widget for this post. Simply type a few letters or a word in the field, and Link Whisper will find you posts that have titles containing your search text.', 'wpil') . '<br><br>' . esc_attr__('Once the search is complete, you can select the posts that you want to show in the widget by clicking on their checkboxes and clicking the "Add Posts" button.', 'wpil') . '<br><br>' . esc_attr__('After adding the posts, and when you\'re satisfied with the selection, please click on the "Update Related Posts" to save them to the widget.', 'wpil');
                break;
            case 'related-posts-update':
                $text = esc_attr__('This is the Related Posts Update button. Whenever you make a change to the related posts in this area, you\'ll need to click on this button to save the changes to the widget.', 'wpil') . '<br><br>' . esc_attr__('(Clicking on the post\'s "Update" or "Publish" button won\'t save the changes you make to the widget.)', 'wpil');
                break;
            /* Post Edit: Target Keywords */
            case 'target-keywords-intro':
                $text = esc_attr__('This is the Target Keyword Panel. It\'s used for setting and changing the Target Keywords for this post.', 'wpil') . '<br><br>' . esc_attr__('Target Keywords improve the suggestions, focus your links to emphasis specific keywords, and to help adjust what kinds of suggestions are shown to you.', 'wpil') . '<br><br>' . esc_attr__('How the Target Keywords are used depends on what kind of suggestions are being generated.', 'wpil') . '<br><br>' . esc_attr__('For Outbound Suggestions, (in the post editing screen), Link Whisper removes any suggestions that contain the keywords. This is because creating a link containing one of the Target Keywords indicates to search engines that the post that is being linking to should rank for that keyword. Since this is exactly opposite to what we want to achieve, the suggestions containing the keywords are removed.', 'wpil') . '<br><br>' . esc_attr__('On the Inbound Suggestions page, Link Whisper prioritizes suggestions that contain the Target Keywords. This is because we\'re trying to make the target post rank for specific keywords, and having links that contain them is a good thing.', 'wpil');
                break;
            case 'target-keywords-types':
                $text = esc_attr__('These are the types of Target Keyword that are currently available. Each tab represents a source that has provided keywords to Link Whisper', 'wpil') . '<br><br>' . esc_attr__('Clicking on one of the keyword tabs will show you the keywords from the specific source of keywords.', 'wpil');
                break;
            case 'target-keywords-checkboxes':
                $text = esc_attr__('To set the keywords for this post, just click the checkbox next to the keyword.', 'wpil') . '<br><br>' . esc_attr__('To unset a keyword, uncheck the the checkbox next to the keyword.', 'wpil');
                break;
            case 'target-keywords-update':
                $text = esc_attr__('After checking or unchecking keywords, you\'ll need to click the "Update Existing Keywords" button to save them.', 'wpil');
                break;
            /* Post Edit: Outbound Suggestions */
            case 'outbound-suggestions-intro':
                $text = esc_attr__('This is the Link Whisper Outbound Suggestion panel. The suggestions shown here are for links that will be inserted into this post, and will point to other posts on the site.', 'wpil');
                break;
            case 'outbound-suggestions-link-orphaned':
                $text = esc_attr__('Turning "On" the option to "Only Link to Orphaned Posts" will tell Link Whisper to generate suggestions pointing to posts that don\'t have any links currently pointing to them.', 'wpil');
                break;
            case 'outbound-suggestions-link-same-parent':
                $text = esc_attr__('Turning "On" the option to "Only Suggest Links to Posts With the Same Parent as This Post" will tell Link Whisper to generate suggestions pointing to posts that have the same parent post as this one.', 'wpil');
                break;
            case 'outbound-suggestions-link-same-category':
                $text = esc_attr__('Turning "On" the option to "Only Link in This Post\'s Categories" will tell Link Whisper to only make linking suggestions to posts that are in the same categories as the current post.', 'wpil') . '<br><br>' . esc_attr__('When you turn on this option, you will see a dropdown of the post\'s current categories so you can further narrow down the categories of posts to search in for suggestions.', 'wpil');
                break;
            case 'outbound-suggestions-link-same-tags':
                $text = esc_attr__('Turning "On" the option to "Only Suggest Posts with the Same Tags" will tell Link Whisper to only make linking suggestions to posts that have the same tags as the current post.', 'wpil') . '<br><br>' . esc_attr__('When you turn on this option, you will see a dropdown of the post\'s current tags so you can further narrow down the number of tagged posts to search for suggestions.', 'wpil');
                break;
            case 'outbound-suggestions-link-post-type':
                $text = esc_attr__('Turning "On" the option to "Select Linking Post Types" will allow you to restrict Link Whisper\'s suggestions to posts in specific post types.', 'wpil') . '<br><br>' . esc_attr__('This is helpful if you want to target a particular post type for linking, or you want to completely ignore a post type from the suggestions.', 'wpil');
                break;
            case 'outbound-suggestions-regenerate-suggestions':
                $text = esc_attr__('The "Regenerate Suggestions" button allows you to regenerate the suggestions after changing any of the above linking options.', 'wpil');
                break;
            case 'outbound-suggestions-export-support':
                $text = esc_attr__('The "Export Support Data" link is used to generate a diagnostic export when contacting Link Whisper support. It contains information about this post, and some information about the site to help Support diagnose problems if they are occuring.', 'wpil');
                break;
            case 'outbound-suggestions-export-excel':
                $text = esc_attr__('The "Export Links to Excel" exports this post\'s link information to an Excel spreadsheet. The export contains a full list of this post\'s Inbound Internal, Outbound Internal, and External links.', 'wpil');
                break;
            case 'outbound-suggestions-suggestion-data':
                $text = esc_attr__('The "Export Suggestion Data to CSV" exports the all of the suggestion data that Link Whisper has generated for this post to a .CSV spreadsheet.', 'wpil');
                break;
            case 'outbound-suggestions-add-inbound':
                $text = esc_attr__('The "Add Inbound Links" button is a shortcut to the Inbound Suggestion page for this post. Clicking on it will take you to the Inbound Suggestion Page for this post so you can quickly create links pointing to this post.', 'wpil');
                break;
            case 'outbound-suggestions-filter-date':
                $text = esc_attr__('The "Filter by Date" filter allows you to hide all suggestions for posts that were published at times outside of the selected range.', 'wpil') . '<br><br>' . esc_attr__('By default, suggestions are shown for posts published between January, 1, 2000 and the present day.', 'wpil');
                break;
            case 'outbound-suggestions-filter-keywords':
                $text = esc_attr__('The "Filter Suggested Posts by Keyword" filter allows you to search the generated suggestions for suggestions that contain a specific word or phrase.', 'wpil') . '<br><br>' . esc_attr__('(Give it a try, you should see the number of suggestions trim up fast)', 'wpil');
                break;
            case 'outbound-suggestions-filter-ai-score':
                $text = esc_attr__('The "Filter by AI Score" filter allows you to filter the current suggestions so that you\'re only shown suggestions that AI thinks are related to the post.', 'wpil') . '<br><br>' . esc_attr__('To use it, just move the purple slider to the right until it reaches the desired limit for how related a post needs to be in order to be suggested.', 'wpil') . '<br><br>' . esc_attr__('(Try setting it to 70%, you should see the suggestions become much more like this post)', 'wpil');
                break;
            case 'outbound-suggestions-sort-suggestions':
                $options =
                '<ul>
                    <li>' . esc_attr__('AI Score: Sort based on how related AI thinks the suggestion is to the target post.', 'wpil') . '</li>
                    <li>' . esc_attr__('Suggestion Score: Sort based on how related Link Whisper thinks the suggestion is to the target post.', 'wpil') . '</li>
                    <li>' . esc_attr__('Publish Date: Sort by the suggestions by the age of the suggested post.', 'wpil') . '</li>
                    <li>' . esc_attr__('Inbound Internal Links: Sort the suggestions according to the number of Inbound Internal Links the suggested posts have.', 'wpil') . '</li>
                    <li>' . esc_attr__('Outbound Internal Links: Sort the suggestions according to the number of Outbound Internal Links the suggested posts have.', 'wpil') . '</li>
                    <li>' . esc_attr__('Outbound External Links: Sort the suggestions according to the number of Outbound External Links the suggested posts have.', 'wpil') . '</li>
                </ul>';
                $text = esc_attr__('The suggestion sorting area allows you to sort the available suggestions for your convenience.') . '<br><br>' . esc_attr__('Currently, suggestions can be sorted by:', 'wpil')  . '<br><br>' . $options;
                break;
            case 'outbound-suggestions-table':
                $text = esc_attr__('This is the suggestion table, it contains all of the suggestions that Link Whisper has for links that could be created inside of this post.', 'wpil') . '<br><br>' . esc_attr__('The table is designed to give you a wide selection of links to choose from so that you can pick out the best ones.', 'wpil') . '<br><br>' . esc_attr__('To add a link, simply click on the checkbox to it\'s left, and then click the "Insert Link Into Post" button. If you want to insert multiple links at once, just select all of the suggestions you like and then click the "Insert Link Into Post" button.', 'wpil');
                break;
            /* Inbound Suggestions */
            case 'inbound-suggestions-intro':
                $text = esc_attr__('This is the Inbound Suggestions Page. It generates linking suggestions that point to the target page, making it very easy to focus links on specific posts.', 'wpil');
                break;
            case 'inbound-suggestions-return-to-report':
                $text = esc_attr__('The "Return to Report" button takes you back to the screen that you were on before opening the Inbound Suggestions Page.', 'wpil');
                break;
            case 'inbound-suggestions-link-target':
                $text = esc_attr__('This is the post that all of the links created on this page will point to.', 'wpil');
                break;
            case 'inbound-suggestions-link-source':
                $text = esc_attr__('This is the post where the links will be created.', 'wpil');
                break;
            case 'inbound-suggestions-keyword-search':
                $text = esc_attr__('The "Search by Keyword" feature allows you to tell Link Whisper to search through all the posts on the site and find sentences to suggest that contain your search term.') . '<br><br>' . esc_attr__('This triggers a new search for suggestions, it doesn\'t filter the existing results.', 'wpil');
                break;
            case 'inbound-suggestions-link-stats':
                $text = esc_attr__('These are the target post\'s current Inbound Internal, Outbound Internal and Outbound External links.') . '<br><br>' . esc_attr__('You can hide this panel by clicking on the "Target Post\'s Link Stats" button.', 'wpil');
                break;
            case 'inbound-suggestions-table':
                $text = esc_attr__('This is the Inbound Suggestions table, and it contains all of the suggestions Link Whisper has for creating links to the target post.', 'wpil');
                break;
            case 'inbound-suggestions-same-category':
                $text = esc_attr__('Turning "On" the option to "Only Link in This Post\'s Categories" will tell Link Whisper to only make linking suggestions from posts that are in the same categories as the current post.', 'wpil') . '<br><br>' . esc_attr__('When you turn on this option, you will see a dropdown of the target post\'s current categories so you can further narrow down the categories of posts to search in for suggestions.', 'wpil');
                break;
            case 'inbound-suggestions-same-tags':
                $text = esc_attr__('Turning "On" the option to "Only Suggest Posts with the Same Tags" will tell Link Whisper to only make linking suggestions from posts that have the same tags as the current post.', 'wpil') . '<br><br>' . esc_attr__('When you turn on this option, you will see a dropdown of the target post\'s current tags so you can further narrow down the number of tagged posts to search for suggestions.', 'wpil');
                break;
            case 'inbound-suggestions-same-parent':
                $text = esc_attr__('Turning "On" the option to "Only Suggest Links to Posts With the Same Parent as This Post" will tell Link Whisper to generate suggestions from posts that have the same parent post as this one.', 'wpil');
                break;
            case 'inbound-suggestions-select-post-type':
                $text = esc_attr__('Turning "On" the option to "Select Linking Post Types" will allow you to restrict Link Whisper\'s suggestions to posts from specific post types.', 'wpil') . '<br><br>' . esc_attr__('This is helpful if you want to get linking suggestions from a particular post type, or you want to exclude suggestions from specific post types.', 'wpil');
                break;
            case 'inbound-suggestions-filter-date':
                $text = esc_attr__('The "Filter by Date" filter allows you to hide all suggestions from posts that were published at times outside of the selected range.', 'wpil') . '<br><br>' . esc_attr__('By default, suggestions are shown for posts published between January, 1, 2000 and the present day.', 'wpil');
                break;
            case 'inbound-suggestions-filter-keywords':
                $text = esc_attr__('The "Filter by Keyword" filter allows you to search the generated suggestions for suggestions that contain a specific word or phrase.', 'wpil') . '<br><br>' . esc_attr__('(Give it a try, you should see the number of suggestions trim up fast)', 'wpil');
                break;
            case 'inbound-suggestions-filter-ai-score':
                $text = esc_attr__('The "Filter by AI Score" filter allows you to filter the current suggestions so that you\'re only shown suggestions that AI thinks are related to the post.', 'wpil') . '<br><br>' . esc_attr__('To use it, just move the purple slider to the right until it reaches the desired limit for how related a post needs to be in order to be suggested.', 'wpil') . '<br><br>' . esc_attr__('(Try setting it to 70%, you should see the suggestions become much more like this post)', 'wpil');
                break;
            case 'inbound-suggestions-sort-suggestions':
                $style = esc_attr('style="margin-bottom: 10px;"');
                $options =
                '<ul>
                    <li '. $style .'><strong>• ' . esc_attr__('AI Score:', 'wpil') . '</strong> ' . esc_attr__('Sort based on how related AI thinks the suggestion is to the target post.', 'wpil') . '</li>
                    <li '. $style .'><strong>• ' . esc_attr__('Suggestion Score:', 'wpil') . '</strong> ' . esc_attr__('Sort based on how related Link Whisper thinks the suggestion is to the target post.', 'wpil') . '</li>
                    <li '. $style .'><strong>• ' . esc_attr__('Publish Date:', 'wpil') . '</strong> ' . esc_attr__('Sort by the suggestions by the age of the suggested post.', 'wpil') . '</li>
                    <li '. $style .'><strong>• ' . esc_attr__('Inbound Internal Links:', 'wpil') . '</strong> ' . esc_attr__('Sort the suggestions according to the number of Inbound Internal Links the suggested posts have.', 'wpil') . '</li>
                    <li '. $style .'><strong>• ' . esc_attr__('Outbound Internal Links:', 'wpil') . '</strong> ' . esc_attr__('Sort the suggestions according to the number of Outbound Internal Links the suggested posts have.', 'wpil') . '</li>
                    <li '. $style .'><strong>• ' . esc_attr__('Outbound External Links:', 'wpil') . '</strong> ' . esc_attr__('Sort the suggestions according to the number of Outbound External Links the suggested posts have.', 'wpil') . '</li>
                </ul>';
                $text = esc_attr__('The suggestion sorting area allows you to sort the available suggestions for your convenience.', 'wpil') . '<br><br>' . esc_attr__('Currently, suggestions can be sorted by:', 'wpil')  . '<br>' . $options;
                break;
            case 'inbound-suggestions-select-suggestion':
                $text = esc_attr__('These checkboxes allow you to select what links should be created when you click the "Add Links" button.', 'wpil');
                break;
            case 'inbound-suggestions-suggested-sentence':
                $text = esc_attr__('These are the individual sentences where the links will be inserted.', 'wpil') . '<br><br>' . esc_attr__('If multiple sentences were found for a post, Link Whisper will give you a dropdown of possible sentences so that you can pick the best one for the link.', 'wpil') . '<br><br>' . esc_attr__('You can adjust the anchor text of the suggested link by clicking on the words in the sentence, and the blue "link" in the sentence will change to show you what the link would look like if you inserted it.', 'wpil') . '<br><br>' . esc_attr__('Double-clicking on a word will set the link to just contain that one word, and you can edit the text of the sentence itself by clicking on the "Edit Sentence" button.', 'wpil');
                break;
            case 'inbound-suggestions-suggested-post':
                $text = esc_attr__('The "Posts To Create Links In" column lists the posts Link Whisper has found the suggested sentences in, and gives some helpful information about the post.', 'wpil');
                break;
            case 'inbound-suggestions-suggested-post-published':
                $text = esc_attr__('The Date Published column says when the suggested post was published.', 'wpil');
                break;
            /* Autolinking */
            case 'autolinking-intro':
                $text = esc_attr__('This is the Link Whisper Auto-Linking Report. It allows you to automatically create links on your site based on specific keywords that you define.', 'wpil');
                break;
            case 'autolinking-create-one':
                $text = esc_attr__('The quick create area allows you to create one autolink quickly by just entering in the keyword that you want to link with, and the URL that should be used for the link.', 'wpil') . '<br><br>' . esc_attr__('Once you\'ve entered them, just click on the "Create Autolink Rule" button, and then Link Whisper will examine all of the posts on the site and will insert your link wherever possible.', 'wpil') . '<br><br>' . esc_attr__('In addition to this active scanning when the autolinking rule is created, Link Whisper will also passively monitor your content, and will insert the link into any new posts that contain the keyword.', 'wpil');
                break;
            case 'autolinking-create-one-settings':
                $text = esc_attr__('The Autolinking Settings allow you to control where autolinks are inserted, how many to insert, and special circumstances that should be considered when inserting links.', 'wpil') . '<br><br>' . sprintf(esc_attr__('If you would like to know more about the settings, we have a %s that explains them and how they work in more detail.', 'wpil'), esc_attr('<a href="https://linkwhisper.com/knowledge-base/how-to-use-the-auto-linking-feature/#controlling-link-insertion" target="_blank">'.__('Knowledge Base article', 'wpil').'</a>'));
                break;
            case 'autolinking-bulk-settings':
                $text = 
                    esc_attr__('The Autolinking Bulk Actions allow you to create and modify large numbers of Autolinking Rules easily.', 'wpil') . 
                    '<br><br>' . 
                    esc_attr__('They allow you to:', 'wpil') . 
                    '<br><br>' . 
                    '<ul>' .
                    '<li>• ' . sprintf(esc_attr__('Quickly create many Autolinking Rules at once. (%s)', 'wpil'), esc_attr('<a href="https://linkwhisper.com/knowledge-base/how-to-i-bulk-create-autolinks/" target="_blank">' . __('Read more here', 'wpil') . '</a>')) . '</li>' .
                    '<li>• ' . esc_attr__('Re-scan the site to see if there are any places that need to have an autolink inserted.', 'wpil') . '</li>' .
                    '<li>• ' . esc_attr__('Export a .CSV file of all the site\'s Autolinking Rules. (This export can be imported on other sites with the Bulk Create option)', 'wpil') . '</li>' .
                    '<li>• ' . esc_attr__('Quickly mass delete selected Autolinking Rules.', 'wpil') . '</li>' .
                    '</ul>';
                break;
            case 'autolinking-search':
                $text = 
                    esc_attr__('The Autolinking Search function allows you find specific Autolinking Rules. It doesn\'t search for posts that have been autolinked.', 'wpil') . 
                    '<br><br>' . 
                    esc_attr__('You can search by "Keyword" or by "Link".', 'wpil');
                break;
            case 'autolinking-refresh-report':
                $text = 
                esc_attr__('The "Refresh Auto-Linking Report" tells Link Whisper to scan the entire site to find all of the autolinks that have been inserted.', 'wpil') . 
                '<br><br>' . 
                esc_attr__('The scan does not create new links, it only finds the ones that have been inserted so that they can be listed in the report table.', 'wpil');
                break;
            case 'autolinking-table':
                $text = 
                esc_attr__('The Autolinking Report Table lists all of the existing Autolinking Rules, the number of times that their links have been inserted on the site, and the posts that they\'ve been inserted in.', 'wpil');
                break;
            case 'autolinking-table-keyword-col':
                /*$text = 
                esc_attr__('The Autolinking Table\'s "Keyword" column lists all of the keywords that will be used.', 'wpil') . 
                '<br><br>' . 
                esc_attr__('The scan does not create new links, it only finds the ones that have been inserted so that they can be listed in the report table.', 'wpil');
                break;*/
            case 'autolinking-table-link-col':
            case 'autolinking-table-possible-links-col':
            case 'autolinking-table-links-added-col':
            case 'autolinking-table-delete-col':
            /* Target Keyword Page */
            case 'target-keyword-report-intro':
            case 'target-keyword-report-filter':
            case 'target-keyword-report-search':
            case 'target-keyword-report-refresh':
            case 'target-keyword-report-table':
            case 'target-keyword-report-table-post-col':
            case 'target-keyword-report-table-active-col':
            case 'target-keyword-report-table-organic-col':
            case 'target-keyword-report-table-custom-keyword-col':
            case 'target-keyword-report-table-page-content-keyword-col':
            case 'target-keyword-report-table-ai-generated-keyword-col':
            case 'target-keyword-report-table-gsc-keyword-col':
            case 'target-keyword-report-table-yoast-keyword-col':
            case 'target-keyword-report-table-rank-math-keyword-col':
            case 'target-keyword-report-table-aioseo-keyword-col':
            case 'target-keyword-report-table-seopress-keyword-col':
            case 'target-keyword-report-table-squirrly-keyword-col':
            case 'target-keyword-report-table-aioseo-keyword-col':
            /* URL Changer */
        }

        if(!empty($text)){
            // create an estimate of how long the popup should show
            // start with our baseline reading speed for a really slow reader
            $wpm = 150;
            // find out how many words per second that is
            $wps = ($wpm/60);
            // count the number of words in the text
            $wcount = Wpil_Word::getWordCount($text);
            // calculate how long it will take to read
            $spd = ($wps * $wcount) * 100;

            // make sure that it's at least 4.5 seconds
            if($spd < 4500){
                $spd = 4500;
            }

            $text = 'data-wpil-tooltip-content="' . $text . '" data-wpil-tooltip-read-time="' . $spd . '"';
        }

        return $text;
    }

    /**
     * Super basically encodes an array of data into a json string.
     * Only really intended for encoding a one-level array of numbers
     **/
    public static function super_basic_json_encode($data){
        if(empty($data) || !is_array($data)){
            return $data;
        }
        return ('[' . implode(',', $data) . ']');
    }

    /**
     * Super basically decodes a json string of data.
     * Only intended to decode a one-level string of numbers
     **/
    public static function super_basic_json_decode($data){
        if(empty($data) || !is_string($data) || false !== strpos($data, ':')){
            return $data;
        }

        $dat = explode(',', trim($data, '[]'));

        if(is_array($dat)){
            return $dat;
        }

        return $data;
    }

    public static function basic_json_encode($data) {
        if (is_array($data)) {
            return self::encode_array($data);
        } elseif (is_string($data)) {
            return self::encode_string($data);
        } elseif (is_numeric($data)) {
            return is_finite($data) ? (string)$data : 'null';
        } elseif (is_bool($data)) {
            return $data ? 'true' : 'false';
        } elseif (is_null($data)) {
            return 'null';
        } else {
            // Handle other data types or objects as needed
            return 'null';
        }
    }
    
    public static function encode_array(&$data) {
        $isAssoc = self::is_associative_array($data);
        $result = $isAssoc ? '{' : '[';
        $first = true;
    
        foreach ($data as $key => &$value) {
            if (!$first) {
                $result .= ',';
            } else {
                $first = false;
            }
    
            if ($isAssoc) {
                $result .= self::encode_string($key) . ':';
            }
    
            if (is_array($value)) {
                $result .= self::encode_array($value);
            } else {
//                $result .= self::custom_json_encode($value);
            }
    
            // Free memory if necessary
            unset($data[$key]);
        }
        unset($value); // Break the reference with the last element
    
        $result .= $isAssoc ? '}' : ']';
        return $result;
    }
    
    public static function encode_string($string) {
        static $replacements = [
            "\\" => "\\\\",
            "\"" => "\\\"",
            "\n" => "\\n",
            "\r" => "\\r",
            "\t" => "\\t",
            "\f" => "\\f",
            "\b" => "\\b",
            "/"  => "\\/"
        ];
    
        $escaped = strtr($string, $replacements);
        // Handle non-printable characters
        $escaped = preg_replace_callback('/[^\x20-\x7E]/u', function ($matches) {
            $char = $matches[0];
            $codepoint = mb_ord($char, 'UTF-8');
            return sprintf("\\u%04x", $codepoint);
        }, $escaped);
    
        return '"' . $escaped . '"';
    }
    
    public static function is_associative_array($array) {
        // Check if array is associative
        return count(array_filter(array_keys($array), 'is_string')) > 0;
    }

    /**
     * Creates a standard content id so we can tell if a post's content has changed
     **/
    public static function create_post_content_id($post){

        if(empty($post) || !is_a($post, 'Wpil_Model_Post')){
            return false;
        }
        
        $content = $post->getContent();
        
        // replace unicode chars with their decoded forms
        $replace_unicode = array('\u003c', '\u003', '\u0022');
        $replacements = array('<', '>', '"');

        $content = str_ireplace($replace_unicode, $replacements, $content);

        // replace any base64ed image urls
        $content = preg_replace('`src="data:image\/(?:png|jpeg);base64,[\s]??[a-zA-Z0-9\/+=]+?"`', '', $content);
        $content = preg_replace('`alt="Source: data:image\/(?:png|jpeg);base64,[\s]??[a-zA-Z0-9\/+=]+?"`', '', $content);

        // decode page builder encoded sections
        $content = Wpil_Suggestion::decode_page_builder_content($content);

        // remove the heading tags from the text
        $content = mb_ereg_replace('<h1(?:[^>]*)>(.*?)<\/h1>|<h2(?:[^>]*)>(.*?)<\/h2>|<h3(?:[^>]*)>(.*?)<\/h3>|<h4(?:[^>]*)>(.*?)<\/h4>|<h5(?:[^>]*)>(.*?)<\/h5>|<h6(?:[^>]*)>(.*?)<\/h6>', '', $content);

        // remove the head tag if it's present. It should only be present if processing a full page stored in the content
        if(false !== strpos($content, '<head')){
            $content = mb_ereg_replace('<head(?:[^>]*)>(.*?)<\/head>', '', $content);
        }

        // remove any title tags that might be present. These should only be present if processing a full page stored in the content
        if(false !== strpos($content, '<title')){
            $content = mb_ereg_replace('<title(?:[^>]*)>(.*?)<\/title>', '', $content);
        }

        // remove any meta tags that might be present. These should only be present if processing a full page stored in the content
        if(false !== strpos($content, '<meta')){
            $content = mb_ereg_replace('<meta(?:[^>]*)>(.*?)<\/meta>', '', $content);
        }

        // remove any link tags that might be present. These should only be present if processing a full page stored in the content
        if(false !== strpos($content, '<link')){
            $content = mb_ereg_replace('<link(?:[^>]*)>(.*?)<\/link>', '', $content);
        }

        // remove any script tags that might be present. We really don't want to suggest links for schema sections
        if(false !== strpos($content, '<script')){
            $content = mb_ereg_replace('<script(?:[^>]*)>(.*?)<\/script>', '', $content);
        }

        // remove any YooTheme JSON that's in the content
        if( false !== strpos($content, '<!--more-->') && (false !== strpos($content, '<!--') || false !== strpos($content, '<!-- ')) && Wpil_Editor_YooTheme::yoo_active()){
            $content = mb_ereg_replace('<!--\s*?(\{(?:.*?)\})\s*?-->', '', $content);
        }

        // if there happen to be any css tags, remove them too
        if(false !== strpos($content, '<style')){
            $content = mb_ereg_replace('<style(?:[^>]*)>(.*?)<\/style>', '', $content);
        }

        // if there are any 'pre' tags, remove them from the content
        if(false !== strpos($content, '<pre')){
            $content = mb_ereg_replace('<pre(?:[^>]*)>(.*?)<\/pre>', "\n", $content);
        }

        // remove any shortcodes that the user has defined
        $content = Wpil_Suggestion::removeShortcodes($content);

        // remove page builder modules that will be turned into things like headings, buttons, and links
        $content = Wpil_Suggestion::removePageBuilderModules($content);

        // remove elements that have certain classes
        $content = Wpil_Suggestion::removeClassedElements($content);

        // remove any tags that the user doesn't want to create links in
        $content = Wpil_Suggestion::removeIgnoredContentTags($content);

        return md5(strip_tags($content));
    }

    /**
     * Obtains a list of all the known urls on the site that we are supposed to process.
     * @param bool $relative Should we only concern ourselves with returning a list of relative links? Default is yes to save space
     **/
    public static function get_site_page_urls($relative = true){
        $urls = array();
        $ids = Wpil_Report::get_all_post_ids();
        if(!empty($ids)){
            foreach($ids as $id){
                $post = new Wpil_Model_Post($id);
                $link = $post->getViewLink();

                if(empty($link)){
                    continue;
                }

                $urls[$post->get_pid()] = ($relative) ? wp_make_link_relative($link): $link;
            }
        }
        $ids = Wpil_Report::get_all_term_ids();
        if(!empty($ids)){
            foreach($ids as $id){
                $post = new Wpil_Model_Post($id, 'term');
                $link = $post->getViewLink();

                if(empty($link)){
                    continue;
                }

                $urls[$post->get_pid()] = ($relative) ? wp_make_link_relative($link): $link;
            }
        }

        return $urls;
    }

    /**
     * 
     **/
    public static function track_process_progress($process = '', $display_name = '', $completed = 0, $remaining = 0, $total = ''){
        /**
         * Currently tracked processes are
         * * post_scanning
         * * link_scanning
         * * target_keyword_scanning
         * * autolink_keyword_importing
         **/


        $tracking = get_transient('wpil_loading_progress_tracker');

        // if we don't have any tracking data
        if(empty($tracking)){
            $tracking = array(
                $process => array(
                    'display_name' => $display_name,
                    'total' => $total,
                    'total_completed' => $completed,
                    'start' => microtime(true),
                    'runs' => array(
                        array(
                            'completed' => $completed, // #completed during this processing run
                            'remaining' => $remaining, // #remaining as of this run
                            'time' => microtime(true)
                        )
                    )
                )
            );
        }elseif(!isset($tracking[$process])){
            // if this is the first time for this process
            $tracking[$process] = array(
                'display_name' => $display_name,
                'total' => $total,
                'total_completed' => $completed,
                'start' => microtime(true),
                'runs' => array(
                    array(
                        'completed' => $completed,
                        'remaining' => $remaining,
                        'time' => microtime(true)
                    )
                )
            );
        }else{
            // if this is adding another processing run to the list
            $tracking[$process]['total_completed'] += $completed;
            $tracking[$process]['runs'][] = array(
                'completed' => $completed,
                'remaining' => $remaining,
                'time' => microtime(true)
            );
        }

        set_transient('wpil_loading_progress_tracker', $tracking, 15 * MINUTE_IN_SECONDS);
    }

    /**
     * 
     **/
    public static function clear_tracked_process_progress(){
        delete_transient('wpil_loading_progress_tracker');
    }

    /**
     * Gets the max_allowable_package size for the current database
     **/
    public static function get_max_allowable_package_size(){
        global $wpdb;

        if(!empty(self::$max_package_size)){
            return self::$max_package_size;
        }

        $result = $wpdb->get_row("SHOW VARIABLES LIKE 'max_allowed_packet'");
        if ($result) {
            self::$max_package_size = (int)$result->Value;
        }

        return self::$max_package_size;
    }

    public static function get_version_number(){
        $plugin_data = get_plugin_data(WP_INTERNAL_LINKING_PLUGIN_DIR . 'link-whisper.php');
        return (isset($plugin_data['Version'])) ? sanitize_text_field($plugin_data['Version']): WPIL_PLUGIN_VERSION_NUMBER; // WPIL_PLUGIN_VERSION_NUMBER number _should_ be the current version, but sometimes it's not so we rely on the plugin data first.
    }

    /**
     * Gets the stats for the main files so we can check if object caching is messing with us!
     **/
    public static function get_file_stats(){
        $stats = array();
        $files = array(
            'Base', 'Error', 'Keyword', 'Link',
            'License', 'Post', 'Report', 'StemmerLoader',
            'Term', 'URLChanger', 'TargetKeyword', 'SiteConnector',
            'ClickTracker', 'Rest', 'Toolbox', 'Widgets', 'AI',
            'Sitemap', 'Telemetry', 'Email', 'Dashboard', 'Excel',
            'Export', 'Filter', 'Phrase', 'Query', 'SearchConsole',
            'Settings', 'Suggestion', 'Wizard', 'Word'
        );

        foreach($files as $file){
            $path = WP_INTERNAL_LINKING_PLUGIN_DIR . 'core/Wpil/' . $file . '.php';
            $stats[$file] = array(
                'time' => filemtime($path),
                'state' => md5_file($path)
            );
        }

        return $stats;
    }

    /**
     * Checks to see if it looks like one or more of the Link Whisper files have gotten stuck in the site's caching system and needs a refresh
     **/
    public static function check_if_cached(){
        $file_stats = self::get_file_stats();

        $times = array(); // ATM, we're just checking the times
        foreach($file_stats as $stat){
            $times[] = $stat['time'];
        }

        $update_times = array_unique($times);

        // if all of the times are the same
        if(count($update_times) === 1){
            // we're not stuck
            return false;
        }

        // if all the files were updated at around the same time
        if(max($update_times) - min($update_times) < 15){
            return false;
        }

        // if we're here, we could be stuck
        return true;
    }

    /**
     * Merges JSON input into $_POST data so that our Ajax can run with it
     **/
    public static function retrieve_post_data(){
        // if there's already post data
        if(!empty($_POST)){
            // exist
            return;
        }

        // If sent as JSON, merge into $_POST so we can roll with the change without throwing everything off!
        $content_type = isset($_SERVER['CONTENT_TYPE']) ? strtolower(trim($_SERVER['CONTENT_TYPE'])) : '';
        if (strpos($content_type, 'application/json') !== false) {
            $raw = file_get_contents('php://input');
            if ($raw !== false && $raw !== '') {
                $json = json_decode($raw, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
                    $_POST = array_merge($_POST, $json);
                }
            }
        }
    }
}
