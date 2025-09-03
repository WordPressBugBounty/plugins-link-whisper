<?php

/**
 * Work with links
 */
class Wpil_Link
{
    static $url_redirect_cache = array();
    static $cleaned_url_redirect_cache = array();

    /**
     * Register services
     */
    public function register()
    {
        add_action('wp_ajax_wpil_get_link_title', ['Wpil_Link', 'getLinkTitle']);
        add_action('wp_ajax_wpil_add_link_to_ignore', [$this, 'addLinkToIgnore']);
    }

    /**
     * Check if link is internal
     *
     * @param $url
     * @return bool
     */
    public static function isInternal($url)
    {
        // it's internal if there are no protocol slashes or the first char is a single slash
        if (strpos($url, '//') === false || 0 === strpos($url, '/')) {
            return true;
        }

        if(self::isAffiliateLink($url)){
            return false;
        }

        $localhost = parse_url(get_home_url(), PHP_URL_HOST);
        $host = parse_url($url, PHP_URL_HOST);

        if (!empty($localhost) && !empty($host)) {
            $localhost = str_replace('www.', '', $localhost);
            $host = str_replace('www.', '', $host);
            if ($localhost == $host) {
                return true;
            }

            $internal_domains = Wpil_Settings::getInternalDomains();

            if(in_array($host, $internal_domains, true)){
                return true;
            }
        }

        return false;
    }

    /**
     * Checks to see if the url can be traced to a post.
     * The main idea is to cut down on the number of external links that are sent through the URL-to-post functionality,
     * so this doesn't have to be an exhaustive search with complete validation.
     * 
     * @param string $url The url to check
     * @return bool Is the url one that we think we can trace?
     **/
    public static function is_traceable($url = ''){
        if(empty($url)){
            return false;
        }

        // clean up the url a little bit for consistent searching
        $url = str_replace('www.', '', $url);

        $host = parse_url($url, PHP_URL_HOST);

        // if there's no host
        if(empty($host)){
            // most likely it's traceable because it _should_ be relative
            return true;
        }

        $localhost = parse_url(get_home_url(), PHP_URL_HOST);

        // if the host matches localhost
        if(empty($localhost) || $localhost === $host || $host === str_replace('www.', '', $localhost)){
            // it is tracable
            return true;
        }

        /* return false if:
            * there's is a host
            * it doesn't match the home site's
            * and trying to filter it doesn't work
        */
        return false;
    }

    /**
     * Checks if the url is a known cloaked affiliate link.
     * 
     * @param string $url The url to be checked
     * @return bool Whether or not the url is to a cloaked affiliate link. 
     **/
    public static function isAffiliateLink($url){
        // if ThirstyAffiliates is active
        if(class_exists('ThirstyAffiliates')){
            $links = self::getThirstyAffiliateLinks();

            if(isset($links[$url])){
                return true;
            }
        }

        return false;
    }

    /**
     * Checks to see if the given url goes to a sponsored domain
     **/
    public static function isSponsoredLink($url){
        $domains = Wpil_Settings::getSponsoredDomains();

        // if there are no sponsored domains, return false now
        if(empty($domains)){
            return false;
        }

        // get the url's domain
        $url_domain = wp_parse_url(str_replace('://www.', '://', $url), PHP_URL_HOST);

        if(empty($url_domain)){
            return false;
        }

        return (in_array($url_domain, $domains, true)) ? true: false;
    }

    /**
     * Check if link is broken
     *
     * @param $url
     * @return bool|int
     */
    public static function getResponseCode($url)
    {
        // if a url was provided and it's formatted correctly
        if(!empty($url) && (parse_url($url, PHP_URL_SCHEME) || substr($url, 0, 1) == '/') ){

            // make sure the url is absolute so cURL doesn't have a problem with it
            $url = Wpil_Settings::makeLinkAbsolute($url);

            // make the call
            return self::getResponseCodeCurl($url);
        }

        return 925;
    }

    public static function getResponseCodeCurl($url, $follow_youtube_redirects = true) {
        $c = curl_init(html_entity_decode($url));
        $user_ip = get_transient('wpil_site_ip_address');
        
        // if the ip transient isn't set yet
        if(empty($user_ip)){
            // get the site's ip
            $host = gethostname();
            $user_ip = gethostbyname($host);

            // if that didn't work
            if(empty($user_ip)){
                // get the curent user's ip as best we can
                if (!empty($_SERVER['HTTP_CLIENT_IP'])){
                    $user_ip = $_SERVER['HTTP_CLIENT_IP'];
                }elseif(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])){
                    $user_ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
                }else{
                    $user_ip = $_SERVER['REMOTE_ADDR'];
                }
            }
        }

        // save the ip so we don't have to look it up next time
        set_transient('wpil_site_ip_address', $user_ip, (10 * MINUTE_IN_SECONDS));

        // create the list of headers to make the cURL request with
        $request_headers = array(
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
//            'Accept-Encoding: gzip, deflate, br',
            'Accept-Language: en-US,en;q=0.9',
            'Cache-Control: max-age=0, no-cache',
            'Keep-Alive: 300',
            'Pragma: ',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: none',
            'Sec-Fetch-User: ?0',
            'Host: ' . parse_url($url, PHP_URL_HOST),
            'Referer: ' . site_url(),
            'User-Agent: ' . WPIL_DATA_USER_AGENT,
        );

        // if this isn't a youtube link
        if(!self::is_youtube_link($url)){
            // set the encoding headers
            $request_headers[] = 'Accept-Encoding: gzip, deflate, br';
        }

        if(!empty($user_ip)){
            $request_headers[] = 'X-Real-Ip: ' . $user_ip;
        }

        curl_setopt($c, CURLOPT_HTTPHEADER, $request_headers);
        curl_setopt($c, CURLOPT_HEADER, true);
        curl_setopt($c, CURLOPT_FILETIME, true);
        curl_setopt($c, CURLOPT_HTTPGET, true);
        curl_setopt($c, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($c, CURLOPT_MAXREDIRS, 30);
        curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($c, CURLOPT_CONNECTTIMEOUT, 20);
        curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($c, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($c, CURLOPT_TIMEOUT, 20);
        curl_setopt($c, CURLOPT_COOKIEFILE, null);

        // if this isn't a youtube link
        if(!self::is_youtube_link($url)){
            // don't include the response body
            curl_setopt($c, CURLOPT_NOBODY, true);
        }

        $curl_version = curl_version();
        if (defined('CURLOPT_SSL_FALSESTART') && version_compare(phpversion(), '7.0.7') >= 0 && version_compare($curl_version['version'], '7.42.0') >= 0) {
            curl_setopt($c, CURLOPT_SSL_FALSESTART, true);
        }

        //Set the proxy configuration. The user can provide this in wp-config.php
        if(defined('WP_PROXY_HOST')){
            curl_setopt($c, CURLOPT_PROXY, WP_PROXY_HOST);
        }
        if(defined('WP_PROXY_PORT')){
            curl_setopt($c, CURLOPT_PROXYPORT, WP_PROXY_PORT);
        }
        if(defined('WP_PROXY_USERNAME')){
            $auth = WP_PROXY_USERNAME;
            if(defined('WP_PROXY_PASSWORD')){
                $auth .= ':' . WP_PROXY_PASSWORD;
            }
            curl_setopt($c, CURLOPT_PROXYUSERPWD, $auth);
        }

        //Make CURL return a valid result even if it gets a 404 or other error.
        curl_setopt($c, CURLOPT_FAILONERROR, false);

        $headers = curl_exec($c);
        if(defined('CURLINFO_RESPONSE_CODE')){
            $http_code = intval(curl_getinfo($c, CURLINFO_RESPONSE_CODE));
        }else{
            $info = curl_getinfo($c);
            if(isset($info['http_code']) && !empty($info['http_code'])){
                $http_code = intval($info['http_code']);
            }else{
                $http_code = 0;
            }
        }

        if(self::is_youtube_link($url)){
            $http_code = self::check_youtube_content($headers, $url, $c, $follow_youtube_redirects);
        }

        $curl_error_code = curl_errno($c);
        $return_code = 0;
        // if the curl request ultimately got a http code
        if(!empty($http_code)){
            // return the code
            $return_code = $http_code;
        }elseif(!empty($curl_error_code)){
            // if we got a curl error, return that
            $return_code = $curl_error_code;
        }

        if($return_code > 0 && ($return_code < 200 || $return_code > 399) && preg_match('/\.jpg|\.jpeg|\.svg|\.png|\.gif|\.ico|\.webp/i', $http_code)){
            return 888;
        }


        return !empty($return_code) ? $return_code: 925;
    }

    /**
     * Check if link is broken
     *
     * @param $url
     * @return array
     */
    public static function getResponseCodes($urls = array(), $head_call = false)
    {
        $site_protocol = (is_ssl()) ? 'https:': 'http:';
        $return_urls = array();
        $good_urls = array();
        foreach($urls as $url){
            $decoded = urldecode($url); // TODO: review and make sure that there aren't a lot of false positive results after the 2.5.8 update // remove this note when we get to 2.6.3
            if(!empty($decoded) && $decoded !== $url){
                $url = $decoded;
            }

            // if a url was provided and it's formatted correctly, add it to the list to process
            if(!empty($url) && (parse_url($url, PHP_URL_SCHEME) || substr($url, 0, 2) == '//') && parse_url($url, PHP_URL_HOST)){
                // the current URL is using a relative protocol
                if(strpos($url, '//') === 0){
                    // add the current site's protocol to it so cURL doesn't have a problem with it
                    $url = $site_protocol . $url;
                }
                $good_urls[] = $url;
            }elseif(!empty($url) && strpos($url, '/') === 0){
                // if the URL is relative, make it absolute for the so we can scan it
                $good_urls[] = Wpil_Settings::makeLinkAbsolute($url);
            }else{
                // if it wasn't, add it to the return list as a 925
                $return_urls[$url] = 925;
            }
        }

        // if there are good urls
        if(!empty($good_urls)){
            // get the curl response codes for each of them
            $codes = self::getResponseCodesCurl($good_urls, $head_call);
            // and merge the reponses into the return links
            $return_urls = array_merge($return_urls, $codes);
        }

        return $return_urls;
    }

    public static function getResponseCodesCurl($urls, $head_call = false) {
        $start = microtime(true);
        $redirect_codes = array(301, 302, 307);
        $user_ip = get_transient('wpil_site_ip_address');
        $return_urls = array();

        // if the ip transient isn't set yet
        if(empty($user_ip)){
            // get the site's ip
            $host = gethostname();
            $user_ip = gethostbyname($host);

            // if that didn't work
            if(empty($user_ip)){
                // get the curent user's ip as best we can
                if (!empty($_SERVER['HTTP_CLIENT_IP'])){
                    $user_ip = $_SERVER['HTTP_CLIENT_IP'];
                }elseif(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])){
                    $user_ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
                }else{
                    $user_ip = $_SERVER['REMOTE_ADDR'];
                }
            }
        }

        // save the ip so we don't have to look it up next time
        set_transient('wpil_site_ip_address', $user_ip, (10 * MINUTE_IN_SECONDS));

        // create the multihandle
        $mh = curl_multi_init();

        // if we're debugging curl
        if(WPIL_DEBUG_CURL){
            // setup the log files
            $verbose = fopen(trailingslashit(WP_CONTENT_DIR) . 'curl_connection_log.log', 'a');     // logs the actions that curl goes through in contacting the server
            $connection = fopen(trailingslashit(WP_CONTENT_DIR) . 'curl_connection_info.log', 'a'); // logs the result of contacting the server.
        }

        $handles = array();
        foreach($urls as $url){
            // create the curl handle and add it to the list keyed with the url its using
            $handles[$url] = curl_init(html_entity_decode($url));

            // create the list of headers to make the cURL request with
            $request_headers = array(
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
//                'Accept-Encoding: gzip, deflate, br',
                'Accept-Language: en-US,en;q=0.9',
                'Cache-Control: max-age=0, no-cache',
                'Pragma: ',
                'Sec-Fetch-Dest: document',
                'Sec-Fetch-Mode: navigate',
                'Sec-Fetch-Site: none',
                'Sec-Fetch-User: ?0',
                'Host: ' . parse_url($url, PHP_URL_HOST),
                'Referer: ' . site_url(),
                'User-Agent: ' . WPIL_DATA_USER_AGENT,
            );

            // if this isn't a youtube link
            if(!self::is_youtube_link($url)){
                // set the encoding headers
                $request_headers[] = 'Accept-Encoding: gzip, deflate, br';
            }

            if(!empty($user_ip)){
                $request_headers[] = 'X-Real-Ip: ' . $user_ip;
            }

            if($head_call){
                $request_headers[] = 'Connection: close';
            }else{
                $request_headers[] = 'Connection: keep-alive';
                $request_headers[] = 'Keep-Alive: 300';
            }

            curl_setopt($handles[$url], CURLOPT_HTTPHEADER, $request_headers);
            curl_setopt($handles[$url], CURLOPT_HEADER, true);
            curl_setopt($handles[$url], CURLOPT_FILETIME, true);
            curl_setopt($handles[$url], CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($handles[$url], CURLOPT_MAXREDIRS, 10);
            curl_setopt($handles[$url], CURLOPT_RETURNTRANSFER, true);
            curl_setopt($handles[$url], CURLOPT_CONNECTTIMEOUT, 15);
            curl_setopt($handles[$url], CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($handles[$url], CURLOPT_TIMEOUT, 15);
            curl_setopt($handles[$url], CURLOPT_COOKIEFILE, null);
            curl_setopt($handles[$url], CURLOPT_FORBID_REUSE, true);
            curl_setopt($handles[$url], CURLOPT_FRESH_CONNECT, true);
            curl_setopt($handles[$url], CURLOPT_COOKIESESSION, true);
            curl_setopt($handles[$url], CURLOPT_SSL_VERIFYPEER, false);

            // if this isn't a youtube link
            if(!self::is_youtube_link($url)){
                // don't include the response body
                curl_setopt($handles[$url], CURLOPT_NOBODY, true);
            }

            $curl_version = curl_version();
            if (defined('CURLOPT_SSL_FALSESTART') && version_compare(phpversion(), '7.0.7') >= 0 && version_compare($curl_version['version'], '7.42.0') >= 0) {
                curl_setopt($handles[$url], CURLOPT_SSL_FALSESTART, true);
            }

            if(false === $head_call){
                curl_setopt($handles[$url], CURLOPT_HTTPGET, true);
            }

            //Set the proxy configuration. The user can provide this in wp-config.php
            if(defined('WP_PROXY_HOST')){
                curl_setopt($handles[$url], CURLOPT_PROXY, WP_PROXY_HOST);
            }
            if(defined('WP_PROXY_PORT')){
                curl_setopt($handles[$url], CURLOPT_PROXYPORT, WP_PROXY_PORT);
            }
            if(defined('WP_PROXY_USERNAME')){
                $auth = WP_PROXY_USERNAME;
                if(defined('WP_PROXY_PASSWORD')){
                    $auth .= ':' . WP_PROXY_PASSWORD;
                }
                curl_setopt($handles[$url], CURLOPT_PROXYUSERPWD, $auth);
            }

            //Make CURL return a valid result even if it gets a 404 or other error.
            curl_setopt($handles[$url], CURLOPT_FAILONERROR, false);

            // if we're debugging curl
            if(WPIL_DEBUG_CURL){
                // set curl to verbose logging and set where to write it to
                curl_setopt($handles[$url], CURLOPT_VERBOSE, true);
                curl_setopt($handles[$url], CURLOPT_STDERR, $verbose);
            }

            // and add it to the multihandle
            curl_multi_add_handle($mh, $handles[$url]);
        }

        // if there are handles, execute the multihandle
        if(!empty($handles)){
            do {
                $status = curl_multi_exec($mh, $active);
                if ($active) {
                    curl_multi_select($mh);
                }
            } while ($active && $status == CURLM_OK);
        }

        // get any error codes from the operations
        $curl_codes = array();
        foreach($handles as $handle){
            $info = curl_multi_info_read($mh);
            $handle_int = intval($info['handle']);
            if(isset($info['result'])){
                $curl_codes[$handle_int] = $info['result'];
            }else{
                $curl_codes[$handle_int] = 0;
            }
        }

        // when the multihandle is finished, go over the handles and process the responses
        foreach($handles as $handle_url => $handle){
            $handle_int = intval($handle);
            $http_code = intval(curl_getinfo($handle, CURLINFO_RESPONSE_CODE));
            $curl_error_code = (isset($curl_codes[$handle_int])) ? $curl_codes[$handle_int]: 0;

            if(self::is_youtube_link($handle_url)){
                $http_code = self::check_youtube_content(curl_multi_getcontent($handle), $handle_url, $handle);
            }

            // if we're debugging curl
            if(WPIL_DEBUG_CURL){
                // save the results of the connection
                fwrite($connection, print_r(curl_getinfo($handle),true));
            }

            // if the curl request ultimately got a http code
            if(!empty($http_code)){
                // if the code is for a redirect and we have some time to chase it
                if(in_array($http_code, $redirect_codes) && (microtime(true) - $start) < 15){
                    // get the url from the curl data
                    $new_url = trim(curl_getinfo($handle, CURLINFO_EFFECTIVE_URL));
                    if(!empty($new_url)){
                        // call _that_ url to see what happens and add the response to the link list
                        $return_urls[$handle_url] = self::getResponseCodeCurl($new_url);
                    }
                }else{
                    // if the code wasn't a redirect or we don't have the time to check, add the code to the list
                    $return_urls[$handle_url] =  $http_code;
                }
            }elseif(!empty($curl_error_code)){
                // curl error list: https://curl.haxx.se/libcurl/c/libcurl-errors.html
                // useful for diagnosing errors < 100
                $return_urls[$handle_url] = $curl_error_code;
            }

            if(isset($return_urls[$handle_url]) && ($return_urls[$handle_url] < 200 || $return_urls[$handle_url] > 399) && preg_match('/\.jpg|\.jpeg|\.svg|\.png|\.gif|\.ico|\.webp/i', $handle_url)){
                $return_urls[$handle_url] = 888;
            }

            // if a status hasn't been added to the link yet
            if(!isset($return_urls[$handle_url])){
                // mark it as 925
                $return_urls[$handle_url] = 925;
            }

            // close the current handle
            curl_multi_remove_handle($mh, $handle);
            curl_close($handle);
        }

        // close the multi handle
        curl_multi_close($mh);

        return $return_urls;
    }

    /**
     * Get link title by URL
     */
    public static function getLinkTitle()
    {
        Wpil_Base::verify_nonce('wpil_suggestion_nonce');
        if(!is_admin()){
            die();
        }

        $link = !empty($_POST['link']) ? esc_url_raw(trim($_POST['link'])): '';
        $title = '';
        $id = '';
        $type = '';
        $date = __('Not Available', 'wpil');

        if ($link) {
            if (self::isInternal($link)) {
                $post = Wpil_post::getPostByLink($link);
                if(!empty($post) && isset($post->type)){
                    $title = $post->getTitle();
                    $link = $post->getSlug();
                    $id = $post->id;
                    $type = $post->type;

                    if($post->type === 'post'){
                        $date = get_the_date(get_option('date_format', 'F j, Y'), $post->id);
                    }
                }
            }else{
                $title = __('External Page URL', 'wpil');
            }

            wp_send_json([
                'title' => esc_html($title),
                'link' => esc_url_raw($link),
                'id' => $id,
                'type' => $type,
                'date' => $date
            ]);
        }

        die;
    }

    /**
     * Remove class "wpil_internal_link" from links
     */
    public static function removeLinkClass()
    {
        global $wpdb;

        $wpdb->get_results("UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, 'wpil_internal_link', '') WHERE post_content LIKE '%wpil_internal_link%'");
    }

    /**
     * Add link to ignore list
     */
    public static function addLinkToIgnore()
    {
        $error = false;
        if(!isset($_POST['multiple_links'])){
            $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
            $type = !empty($_POST['type']) ? sanitize_text_field($_POST['type']) : null;
            $site_url = (isset($_POST['site_url']) && !empty($_POST['site_url'])) ? esc_url_raw($_POST['site_url']): null;
            $origin = (isset($_POST['post_origin'])) ? $_POST['post_origin']: null;

            if ($id && $type) {
                $error = self::ignore_link($id, $type, $site_url, $origin);
            } else {
                $error = 'Wrong data';
            }
        }elseif(!empty($_POST['multiple_links'])){
            foreach($_POST['multiple_links'] as $link){
                $id = !empty($link['id']) ? (int)$link['id'] : null;
                $type = !empty($link['type']) ? sanitize_text_field($link['type']) : null;
                $site_url = (isset($link['site_url']) && !empty($link['site_url'])) ? esc_url_raw($link['site_url']): null;
                $origin = (isset($link['post_origin'])) ? $link['post_origin']: null;

                if ($id && $type) {
                    $error = self::ignore_link($id, $type, $site_url, $origin);
                } else {
                    $error = 'Wrong data';
                }
            }
        }

        echo json_encode(['error' => $error]);
        die;
    }

    /**
     * Registers a url in the list of posts to be ignored from the suggestions
     **/
    public static function ignore_link($id, $type, $site_url, $origin){
        $error = false;

        // otherwise, assume it's an internal post object
        $post = new Wpil_Model_Post($id, $type);

        $link = $post->getLinks()->view;
        if(empty(Wpil_Post::getPostByLink($link))){
            $link = $post->getViewLink(false, true); // if we can't turn the url into a viable post, go with the "Ugly" url instead.;
        }

        if (!empty($link)) {
            $links = get_option('wpil_ignore_links');
            if (!empty($links)) {
                $links_array = explode("\n", $links);
                if (!in_array($link, $links_array)) {
                    $links .= "\n" . $link;
                }
            } else {
                $links = $link;
            }
            // clear any ignore link cache that exists
            delete_transient('wpil_ignore_links');
            // save the ignore link
            update_option('wpil_ignore_links', $links);
        } else {
            $error = 'Empty post link';
        }

        return $error;
    }

    /**
     * Clean link from trash symbols
     *
     * @param $link
     * @return string
     */
    public static function clean($link)
    {
        $link = str_replace(['http://', 'https://', '//www.'], '//', strtolower(trim($link)));
        if (substr($link, -1) == '/') {
            $link = substr($link, 0, -1);
        }

        return $link;
    }

    /**
     * Processes and formats urls for comparative purposes inside of Link Whisper.
     * That way, we have a nice standard for comparing if links are basicalls the same, even if there's a few differences in text.
     * Not intended for use when inserting links, its just for when checking to see if two links are the same
     **/
    public static function normalize_url($url){
        // first clean the url
        $url = self::clean($url);
        // decode the double encoded & signs
        $url = str_replace(array('&#038;', '&&amp;'), '&', $url);
        // decode the normally encoded & signs
        $url = str_replace(array('#038;', '&amp;'), '&', $url);

        // and return the url
        return $url;
    }

    /**
     * Check if link was marked as external
     *
     * @param $link
     * @return bool
     */
    public static function markedAsExternal($link)
    {
        $external_links = Wpil_Settings::getMarkedAsExternalLinks();

        if (in_array($link, $external_links)) {
            return true;
        }

        foreach ($external_links as $external_link) {
            if (substr($external_link, -1) == '*' && strpos($link, substr($external_link, 0, -1)) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if the given post is at the outbound link limit
     *
     * @param $post
     * @return bool Returns true if the post is at the limit and false if it is not.
     */
    public static function at_max_outbound_links($post)
    {
        if(empty($post)){
            return false;
        }

        $max_outbound_links = get_option('wpil_max_links_per_post', 0);

        if(empty($max_outbound_links)){
            return false;
        }

        $post_link = $post->getLinks()->view;
        $ignore_image_urls = true;//!empty(get_option('wpil_ignore_image_urls', false));
        $ignored_links = Wpil_Settings::getIgnoreLinks();
        $content = $post->getContent();

        //get all links from content
        preg_match_all('`<a[^>]*?href=(\"|\')([^\"\']*?)(\"|\')[^>]*?>([\s\w\W]*?)<\/a>|<!-- wp:core-embed\/wordpress {"url":"([^"]*?)"[^}]*?"} -->|(?:>|&nbsp;|\s)((?:(?:http|ftp|https)\:\/\/)(?:[\w_-]+(?:(?:\.[\w_-]+)+))(?:[\w.,@?^=%&:/~+#-]*[\w@?^=%&/~+#-]))(?:<|&nbsp;|\s)`i', $content, $matches);
        // if there are encoded links
        if(false !== strpos($content, '&lt;a') && false !== strpos($content, '&lt;/a&gt;')){
            // try getting encoded links too
            preg_match_all('`&lt;a[^&]*?href=(\"|\')([^\"\']*?)(\"|\')[^&]*?&gt;([\s\w\W]*?)&lt;\/a&gt;`i', $content, $matches2);
            if(!empty($matches2) && !empty($matches2[0])){
                foreach($matches2 as $key => $values){
                    $matches[$key] = array_merge($matches[$key], $values);
                }

                $m_count = count($matches2[0]);
                for($i = 0; $i < $m_count; $i++){
                    $matches[5][] = '';
                    $matches[6][] = '';
                }
            }
        }

        // make a counter for the links
        $outbound_count = 0;

        //make array with results
        foreach ($matches[0] as $key => $value) {
            $url = '';
            if (!empty($matches[2][$key]) && !empty($matches[4][$key]) && !Wpil_Report::isJumpLink($matches[2][$key], $post_link)) {
                $url = trim($matches[2][$key]);
            }elseif(!empty($matches[5][$key]) && !Wpil_Report::isJumpLink($matches[5][$key], $post_link) ||  // if this is an embed link
                    !empty($matches[6][$key]) && !Wpil_Report::isJumpLink($matches[6][$key], $post_link))    // if this is a link that is inserted in the content as a straight url // Mostly this means its an embed but as case history grows I'll come up with a better notice for the user
            {
                if(!empty($matches[5][$key])){
                    $url = trim($matches[5][$key]);
                }else{
                    $url = trim($matches[6][$key]);
                }
            }

            // skip if the url is empty
            if(empty($url)){
                continue;
            }

            // ignore any links that are being used as buttons
            if(false !== strpos($url, 'javascript:void(0)')){
                continue;
            }

            // if we're making a point to ignore image urls
            if($ignore_image_urls){
                // if the link is an image url, skip to the next match
                if(preg_match('/\.jpg|\.jpeg|\.svg|\.png|\.gif|\.ico|\.webp/i', $url)){
                    continue;
                }
            }

            // if we're ignoring links
            if(!empty($ignored_links)){
                // check to see if this link is on the ignore list
                if(!empty(array_intersect($ignored_links, array($url)))){
                    // if it is, skip to the next
                    continue;
                }else{
                    // if the link wasn't detected with the simple check, see if there's a partial match possible. Mostly this is to allow domain-based ignoring
                    foreach($ignored_links as $link){
                        if(false !== strpos($url, $link)){
                            continue 2;
                        }
                    }
                }
            }

            // filter the URLs with an internal check so users can choose to ignore outbound external or outbound internal if they wish.
            /**
             * @param bool $count_link Should the link be counted in the total? Default is true.
             * @param bool $internal If the current link is internal or not.
             * @param string $url The URL we're currently looking at
             **/
            if(!apply_filters('wpil_max_outbound_links_filter_internal', true, self::isInternal($url), $url)){
                continue;
            }

            $outbound_count++;
        }

        return ($outbound_count >= $max_outbound_links) ? true: false;
    }

    /**
     * Checks to see if the current post is at the limit for Inbound Internal links
     * @param $post
     * @return bool
     **/
    public static function at_max_inbound_links($post){
        if(empty($post)){
            return false;
        }

        $max_inbound_links = get_option('wpil_max_inbound_links_per_post', 0);

        if(empty($max_inbound_links)){
            return false;
        }

        // get the inbound link counts from the stored data
        $inbound_count = $post->getInboundInternalLinks(true);

        return ($inbound_count >= $max_inbound_links) ? true: false;
    }

    /**
     * Checks to see if the supplied text contains a link.
     * The check is pretty simple at this point, just seeing if the form of an opening tag or a closing tag is present in the text
     * 
     * @param string $text
     * @return bool
     **/
    public static function hasLink($text = '', $replace_text = ''){

        // if there's no link anywhere to be seen, return false
        if(empty(preg_match('/<a [^><]*?(href|src)[^><]*?>|<\/a>|&lt;a [^><]*?(href|src)[^><]*?&gt;|&lt;\/a&gt;/i', $text))){
            return false;
        }

        // if there is a link in the replace text, return true
        if(preg_match('/<a [^><]*?(href|src)[^><]*?>|<\/a>|&lt;a [^><]*?(href|src)[^><]*?&gt;|&lt;\/a&gt;/i', $replace_text)){
            return true;
        }

        // if there is a link, see if it ends before the replace text
        $replace_start = (!empty($replace_text)) ? mb_strpos($text, $replace_text): 0;
        if(preg_match('/<\/a>/i', mb_substr($text, 0, $replace_start)) ){
            // if it does, no worries!
            return false;
        }elseif(preg_match('/<a [^><]*?(href|src)[^><]*?>/i', mb_substr($text, 0, $replace_start)) || preg_match('/<\/a>/i', mb_substr($text, $replace_start)) ){
            // if there's an opening tag before the replace text or somewhere after the start, then presumably the replace text is in the middle of a link
            return true;
        }

        return false;
    }


    /**
     * Checks to see if the supplied text contains a heading tag.
     * The check is pretty simple at this point, just seeing if the form of an opening tag or a closing tag is present in the text
     * 
     * @param string $text
     * @return bool
     **/
    public static function hasHeading($text = '', $replace_text = '', $sentence = ''){
        // if there's no heading anywhere to be seen, return false
        if(empty(preg_match('/<h[1-6][^><]*?>|<\/h[1-6]>|&lt;h[1-6]( |&gt;)|&lt;\/h[1-6]&gt;/i', $text))){
            return false;
        }

        // if there is a heading, see if it ends before the replace text
        $replace_start = mb_strpos($text, $sentence);
        if(preg_match('/<\/h[1-6]>|&lt;\/h[1-6]&gt;/i', mb_substr($text, 0, $replace_start)) ){
            // if it does, no worries!
            return false;
        }elseif(preg_match('/<h[1-6][^><]*?>|&lt;h[1-6].*?&gt;/i', mb_substr($text, 0, $replace_start)) || (preg_match('/<\/h[1-6]>|&lt;\/h[1-6]&gt;/i', mb_substr($text, $replace_start)) && !preg_match('/<h[1-6][^><]*?>|&lt;h[1-6].*?&gt;/i', mb_substr($text, $replace_start)) ) ){
            // if there's an opening tag before the replace text or somewhere after the start, then presumably the replace text is in the middle of a heading
            return true;
        }

        // if there is a heading in the replace text, return true
        if(substr_count($replace_text, $sentence) > 1 && preg_match('/<h[1-6][^><]*?>|<\/h[1-6]>|&lt;h[1-6].*?&gt;|&lt;\/h[1-6]&gt;/i', $replace_text)){
            return true;
        }

        return false;
    }

    /**
     * Checks to see if the supplied text contains a script tag.
     * The check is pretty simple at this point, just seeing if the form of an opening tag or a closing tag is present in the text
     * 
     * @param string $text
     * @return bool
     **/
    public static function hasScript($text = '', $replace_text = '', $sentence = ''){
        // if there's no script tag anywhere to be seen, return false
        if(empty(preg_match('/<script[^><]*?>|<\/script>|&lt;script( |&gt;)|&lt;\/script&gt;/i', $text))){
            return false;
        }

        // if there is a script tag, see if it ends before the replace text
        $replace_start = mb_strpos($text, $sentence);
        if(preg_match('/<\/script>|&lt;\/script&gt;/i', mb_substr($text, 0, $replace_start)) ){
            // if it does, no worries!
            return false;
        }elseif(preg_match('/<script[^><]*?>|&lt;script.*?&gt;/i', mb_substr($text, 0, $replace_start)) || (preg_match('/<\/script>|&lt;\/script&gt;/i', mb_substr($text, $replace_start)) && !preg_match('/<script[^><]*?>|&lt;script.*?&gt;/i', mb_substr($text, $replace_start)) ) ){
            // if there's an opening tag before the replace text or somewhere after the start, then presumably the replace text is in the middle of a script section
            return true;
        }

        // if there is a script tag in the replace text, return true
        if(substr_count($replace_text, $sentence) > 1 && preg_match('/<script[^><]*?>|<\/script>|&lt;script.*?&gt;|&lt;\/script&gt;/i', $replace_text)){
            return true;
        }

        return false;
    }

    public static function remove_all_links_from_text($text = ''){
        if(empty($text)){
            return $text;
        }

        $text = preg_replace('/<a[^>]+>(.*?)<\/a>/', '$1', $text);

        return $text;
    }

    /**
     * Gets all ThirstyAffiliate links in an array keyed with the urls.
     * Caches the results to save processing time later
     **/
    public static function getThirstyAffiliateLinks(){
        global $wpdb;
        $links = get_transient('wpil_thirsty_affiliate_links');

        if(empty($links)){
            // query for the link posts
            $results = $wpdb->get_col("SELECT `ID` FROM {$wpdb->posts} WHERE `post_type` = 'thirstylink'");

            // store a flag if there are no link posts
            if(empty($results)){
                set_transient('wpil_thirsty_affiliate_links', 'no-links', 5 * MINUTE_IN_SECONDS);
                return array();
            }

            // get the urls to the link posts
            $links = array();
            foreach($results as $id){
                $links[] = get_permalink($id);
            }

            // flip the array for easy searching
            $links = array_flip($links);

            // store the results
            set_transient('wpil_thirsty_affiliate_links', $links, 5 * MINUTE_IN_SECONDS);

        }elseif($links === 'no-links'){
            return array();
        }

        return $links;
    }

    /**
     * Checks to see if the supplied text is base64ed.
     * @param string $text The text to check if base64 encoded.
     * @param bool $skip_decode Should we skip the decoding check? Compressed data fails the check, so we need to skip it if the data could be gz-compressed.
     * @return bool True if the text is base64 encoded, false if the string is empty or not encoded
     **/
    public static function checkIfBase64ed($text = '', $skip_decode = false){
        if(empty($text) || !is_string($text)){
            return false;
        }
        $possible = preg_match('`^([A-Za-z0-9+/]{4})*?([A-Za-z0-9+/]{3}=|[A-Za-z0-9+/]{2}==)??$`', $text);

        if($possible === 0){
            return false;
        }

        if($skip_decode || !empty(mb_detect_encoding(base64_decode($text)))){
            return true;
        }

        return false;
    }

    /**
     * Filters the supplied link to change the domain from staging to live.
     * Only changes the site's domain & scheme, otherwise leaves the rest of the URL as is
     * 
     * @param string $url The url to filter
     * @return string $url The filtered URL if it's supposed to be filtered.
     **/
    public static function filter_staging_to_live_domain($url = ''){
        // if there's no url, the user isn't filtering staging urls out or relative link mode is active
        if(empty($url) || !get_option('wpil_filter_staging_url', false) || !empty(get_option('wpil_insert_links_as_relative', false)))
        {
            // return the url
            return $url;
        }

        // get the live site's url
        $live_site_url = trailingslashit(trim(get_option('wpil_live_site_url', false)));
        $staging_site_url = trailingslashit(trim(get_option('wpil_staging_site_url', false)));
        $home_url = get_home_url();

        // if there's no live site url entered, we're actually on the live site, or this isn't a staging site url
        if( empty($live_site_url) ||
            empty($staging_site_url) ||
            $live_site_url === $staging_site_url || // if the urls are the same
            false !== strpos($home_url, $live_site_url) || // if the current site is the live site
            false !== strpos($live_site_url, $home_url) || // if the current site is the live site from a different direction
            false === strpos($url, $staging_site_url) || // if the url isn't pointed at the staging site
            false === strpos($url, $home_url) || // if the url isn't pointed to the current site
            self::isRelativeLink($url) // or if the link is relative
        ){
            // return the url without changing it
            return $url;
        }

        // let's give the user a chance to filter the URL
        $new_url = apply_filters('wpil_filter_staging_url_to_live', $url, $live_site_url, $staging_site_url);
        // if he's changed the URL
        if($new_url !== $url){
            // return it with the changes
            return $new_url;
        }

        // now that we've made it past the checks, lets change the staging domain for the live one
        // first, lets try a simple URL replace and see if it's valid
        $test_url = str_replace($staging_site_url, $live_site_url, $url);

        // if there's a url and it's not changed by sending it through esc_url_raw
        if(!empty($test_url) && $test_url === esc_url_raw($test_url)){
            // it's good
            return $test_url;
        }

        // break it into pieces
        $live_site_url = wp_parse_url(sanitize_text_field($live_site_url));

        // break the staging site url into pieces
        $staging_site_url = wp_parse_url(sanitize_text_field($staging_site_url));

        // exit if either url has no host
        if( !isset($live_site_url['host']) || empty($live_site_url['host']) ||
            !isset($staging_site_url['host']) || empty($staging_site_url['host'])
        ){
            return $url;
        }

        $url = str_replace($staging_site_url['host'], $live_site_url['host'], $url);

        // if the scheme was included in both urls, and they are different
        if( isset($live_site_url['scheme']) && !empty($live_site_url['scheme']) &&
            isset($staging_site_url['scheme']) && !empty($staging_site_url['scheme']) &&
            ($live_site_url['host'] !== $staging_site_url['scheme'])
        ){
            // replace the scheme
            $pos = strpos($url, $staging_site_url['scheme']);
            if($pos !== false){
                $url = substr_replace($url, $live_site_url['scheme'], $pos, strlen($staging_site_url['scheme']));
            }
        }

        return $url;
    }

    /**
     * Filters the supplied link to change the domain from live to staging.
     * Only changes the site's domain & scheme, otherwise leaves the rest of the URL as is
     * 
     * @param string $url The url to filter
     * @return string $url The filtered URL if it's supposed to be filtered.
     **/
    public static function filter_live_to_staging_domain($url = ''){
        // if there's no url, the user isn't filtering staging urls out or relative link mode is active
        if(empty($url) || !get_option('wpil_filter_staging_url', false) || !empty(get_option('wpil_insert_links_as_relative', false)))
        {
            // return the url
            return $url;
        }

        // get the live site's url
        $live_site_url = trailingslashit(trim(get_option('wpil_live_site_url', false)));
        $staging_site_url = trailingslashit(trim(get_option('wpil_staging_site_url', false)));
        $home_url = get_home_url();

        // if there's no live site url entered, we're actually on the live site, or this isn't a staging site url
        if( empty($live_site_url) ||
            empty($staging_site_url) ||
            $live_site_url === $staging_site_url || // if the urls are the same
            false !== strpos($home_url, $live_site_url) || // if the current site is the live site
            false !== strpos($live_site_url, $home_url) || // if the current site is the live site from a different direction
            false === strpos($url, $live_site_url) || // if the url isn't pointed at the live site
            false !== strpos($url, $home_url) || // if the url is pointed to the current site
            self::isRelativeLink($url) // or if the link is relative
        ){
            // return the url without changing it
            return $url;
        }

        // let's give the user a chance to filter the URL
        $new_url = apply_filters('wpil_filter_live_url_to_staging', $url, $live_site_url, $staging_site_url);
        // if he's changed the URL
        if($new_url !== $url){
            // return it with the changes
            return $new_url;
        }

        // now that we've made it past the checks, lets change the live domain for the staging one
        // first, lets try a simple URL replace and see if it's valid
        $test_url = str_replace($live_site_url, $staging_site_url, $url);

        // if there's a url and it's not changed by sending it through esc_url_raw
        if(!empty($test_url) && $test_url === esc_url_raw($test_url)){
            // it's good
            return $test_url;
        }

        // break it into pieces
        $live_site_url = wp_parse_url(sanitize_text_field($live_site_url));

        // break the staging site url into pieces
        $staging_site_url = wp_parse_url(sanitize_text_field($staging_site_url));

        // exit if either url has no host
        if( !isset($live_site_url['host']) || empty($live_site_url['host']) ||
            !isset($staging_site_url['host']) || empty($staging_site_url['host'])
        ){
            return $url;
        }

        $url = str_replace($live_site_url['host'], $staging_site_url['host'], $url);

        // if the scheme was included in both urls, and they are different
        if( isset($live_site_url['scheme']) && !empty($live_site_url['scheme']) &&
            isset($staging_site_url['scheme']) && !empty($staging_site_url['scheme']) &&
            ($live_site_url['host'] !== $staging_site_url['scheme'])
        ){
            // replace the scheme
            $pos = strpos($url, $live_site_url['scheme']);
            if($pos !== false){
                $url = substr_replace($url, $staging_site_url['scheme'], $pos, strlen($live_site_url['scheme']));
            }
        }

        return $url;
    }

    /**
     * Checks if the link is relative
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
     * De-translates relative links so we can track the urls to the post they're pointing to.
     * @param string $link The url that we're going to de-translate if possible
     * @return string Returns the de-translated link if we're successful and the original link if we're not
     **/
    public static function clean_translated_relative_links($link = ''){
        
        // if WPML is active and this is a relative permalink
        if(Wpil_Settings::wpml_enabled() && self::isRelativeLink($link)){
            // get the WPML main class
            global $sitepress;

            // if we can check the settings
            if(!empty($sitepress) && method_exists($sitepress, 'get_setting')){
                // find out how we're differnetiating the languages in URLs
                $set = $sitepress->get_setting('language_negotiation_type');
                // if we're doing it with directories ("/en/testing-post")
                if($set === '1'){
                    // grab the first directory since that should be the language directory
                    $bits = explode('/', trim($link, '/'));
                    $dir = isset($bits[0]) ? $bits[0]: false;

                    // if we've got a dir & its for a WPML locale
                    if(Wpil_Settings::is_supported_wpml_local($dir)){
                        // remove the dir from the link
                        $new_link = mb_substr(ltrim($link, '/'), mb_strlen($dir));
                        $link = (0 !== mb_strpos($link, '/')) ? ltrim($new_link, '/'): $new_link;
                    }
                }
            }
        }

        return $link;
    }

    /**
     * Gets the applied url redirect for the given URL.
     * Cleans up the link a little to remove query parameters.
     * @TODO: add regex url chasing support. Since this is checking redirect status of specific link, we could actually apply the rules here
     * @param string $url The url to check
     * @return string|bool Returns the redirected URL if a redirect is active, and FALSE if there's no redirect
     **/
    public static function get_url_redirection($url = '', $max_depth = 5) {
        $original_url = $url;
        $depth = 0;

        while ($depth < $max_depth) {
            $redirect = self::get_nested_url_redirection($url);

            if ($redirect === false) {
                // No more redirects found
                break;
            }

            $url = $redirect;
            $depth++;
        }

        return ($url !== $original_url) ? $url : false;
    }

    private static function get_nested_url_redirection($url = ''){
        if(empty($url)){
            return false;
        }

        // return false if there are no redirects active
        if(null === self::$url_redirect_cache){
            return false;
        }elseif(empty(self::$url_redirect_cache)){
            self::$url_redirect_cache = Wpil_Settings::getRedirectionUrls();
        }

        // if the url is being redirected
        if(isset(self::$url_redirect_cache[$url])){
            // return the redirect location
            return self::$url_redirect_cache[$url];
        }

        // if that didn't work, try cleaning up the url a bit to see if that makes the difference
        $url = trailingslashit(strtok($url, '?#'));

        // if that works
        if(isset(self::$url_redirect_cache[$url])){
            // return the redirect location
            return self::$url_redirect_cache[$url];
        }

        if (0 !== strpos(parse_url($url, PHP_URL_HOST), 'www.')) {
            if(empty(self::$cleaned_url_redirect_cache)){
                foreach(self::$url_redirect_cache as $key => $value){
                    self::$cleaned_url_redirect_cache[str_replace('://www.', '://', $key)] = $value;
                }
            }

            if(isset(self::$cleaned_url_redirect_cache[$url])){
                return self::$cleaned_url_redirect_cache[$url];
            }
        }

        // otherwise, the url is not being redirected as far as we can tell
        return false;
    }

    /**
     * Checks if the given URL points to the standard "home" locations
     **/
    public static function url_points_home($url = ''){
        if(empty($url)){
            return false;
        }

        // trim the url just in case there's a trailing whitespace or somthing
        $url = trim($url);

        // if the url is pointing to the site root
        if($url === '/'){
            return true;
        }

        // make sure the url is slashed for consistency
        $url = trailingslashit($url);

        // if the url is pointing to the home_url
        if(trailingslashit(get_home_url()) === $url){
            return true;
        }

        // if the url is pointing to the home_url
        if(trailingslashit(get_site_url()) === $url){
            return true;
        }

        // if we haven't caught it, the url probably isn't pointing to the site home url
        return false;
    }

    /**
     * Creates and returns the next tracking id for a link we create.
     * Also notes the time that the id was created and the author id for the user that created it
     **//*
    public static function create_next_tracked_link_id(){
        global $wpdb;
        $table = $wpdb->prefix . 'wpil_tracked_link_ids';

        $author = get_current_user_id();
        if(empty($author)){
            $author = 0;
        }

        $wpdb->insert($table, ['creation_time' => current_time('timestamp', 1), 'author_id' => $author]);
        return !empty($wpdb->insert_id) ? $wpdb->insert_id: 0;
    }*/

    /**
     * Checks to see if the link is one that points to youtube.
     * Works for both full and shortened links
     **/
    public static function is_youtube_link($url = ''){
        if(empty($url)){
            return false;
        }

        if( false !== strpos($url, 'https://www.youtube.com') || 
            false !== strpos($url, 'https://youtube.com') || 
            false !== strpos($url, 'https://youtu.be/'))
        {
            return true;
        }

        return false;
    }

    /**
     * Checks to see if the youtube page content contains a video.
     * @param string $content
     * @param string $url
     * @param CurlHandle $handle
     **/
    public static function check_youtube_content($content = '', $url = '', $handle = '', $follow = true){
        if(empty($content) || empty($url) || empty($handle)){
            return false;
        }

        // if the URL points directly to youtube
        $slashed = trailingslashit($url);
        if( $slashed === 'https://www.youtube.com/' || 
            $slashed === 'https://youtube.com/' || 
            $slashed === 'https://youtu.be/'
        ){
            // clearly it's a good link!
            return 200;
        }

        $code = 825;

        // pull the url meta property to check it for the link's ID
        preg_match('/<meta property="og:url"[^>]*?>/', $content, $meta_url);
        preg_match('/<meta property="og:video:url"[^>]*?>/', $content, $meta_video_url);
        preg_match('/\?v=([0-9a-zA-Z\-_]*)|\/embed\/([0-9a-zA-Z\-_]*)|youtu\.be\/([0-9a-zA-Z\-_]*)\?/', $url, $matches);
        preg_match('/previewPlayabilityStatus\\\":{\\\"status\\\":\\\"([a-zA-Z]*?)\\\"/', $content, $play_status);

        if(!empty($play_status)){
            $play_status = end($play_status);
        }else{
            $play_status = false;
        }

        // if we do have the proper info
        if(!empty($meta_video_url) && !empty($matches)){
            $video_id = end($matches);
            $meta = end($meta_video_url);

            // and the video key is in the meta
            if(false !== strpos($meta, $video_id)){
                // say the video is good!
                $code = 200;
            }

            // if the video is set to be unlisted
            if(false && false !== strpos($content, '"isUnlisted":true')){
                // list it as unlisted in the system
                $code = 826;
            }
        }elseif(false !== strpos($url, 'youtube.com/embed/')){
            if(!empty($play_status) && strtolower($play_status) === 'ok'){
                $code = 200;
            }

        }else if(false !== strpos($url, 'youtube.com/channel/')){ // if the link is supposed to be pointing to a channel
            // check the last available URL to see if it still points to the channel
            $last_url = curl_getinfo($handle, CURLINFO_EFFECTIVE_URL);

            // if they are pointing to the same url
            if(!empty($last_url) && trailingslashit($last_url) === trailingslashit($url)){

                // if we have a metatag for the page's url
                if(!empty($meta_url)){
                    $meta = end($meta_url);

                    // parse the url from the meta tag
                    if(!empty($meta)){
                        // pull the url out of the meta tag
                        preg_match('/content="(.*?)"/', $meta, $meta_url_s);
                        
                        if(!empty($meta_url_s)){
                            $meta_url_s = end($meta_url_s);
                            if(!empty($meta_url_s)){
                                $meta = $meta_url_s;
                            }
                        }
                    }

                    // if the url is inside the metatag
                    if(!empty($meta) && is_string($meta) && false !== strpos($url, $meta)){
                        // the channel exists!
                        $code = 200;
                    }else{
                        // the channel seems to be gone
                        $code = 827;
                    }
                }else{
                    // if we don't, it seems the channel was removed
                    $code = 827;
                }
            }else{
                // if it doesn't, it seems it was removed
                $code = 827;
            }
        }else if(false !== strpos($url, 'youtube.com/@')){ // if the link is supposed to be pointing to a channel
            // check the last available URL to see if it still points to the channel
            $last_url = curl_getinfo($handle, CURLINFO_EFFECTIVE_URL);

            // if they are pointing to the same url
            if(!empty($last_url) && trailingslashit(str_replace('www.', '', $last_url)) === trailingslashit(str_replace('www.', '', $url))){
                // extract the baseURL if possible
                preg_match('/"canonicalBaseUrl":"(\/@[^"]*?)"/', $content, $base_url);

                // if we have a metatag for the page's url
                if(!empty($base_url)){
                    $meta = end($base_url);

                    // if the url is inside the metatag
                    if(!empty($meta) && is_string($meta) && false !== strpos($url, $meta)){
                        // the channel exists!
                        $code = 200;
                    }else{
                        // the channel seems to be gone
                        $code = 827;
                    }
                }else{
                    // if we don't, it seems the channel was removed
                    $code = 827;
                }
            }else{
                // if it doesn't, it seems it was removed
                $code = 827;
            }
        }

        // if the url appears broken
        if($code === 825 && $follow){
            // get the headers to see if the page is a redirect that wasn't chased
            $headerSize = curl_getinfo($handle, CURLINFO_HEADER_SIZE);
            $headers = substr($content, 0, $headerSize);
            if(!empty($headers)){
                $headers = array_map('trim', explode("\r\n", trim($headers)));
                // if this is a redirected page
                if(in_array('HTTP/2 303', $headers)){
                    // see if there's a location to redirect to
                    foreach($headers as $header){
                        // if there is
                        if(false !== strpos($header, 'location:')){
                            // see if it's to a youtube video
                            $bits = explode('location:', $header);
                            $possible_url = trim($bits[1]);
                            if(self::is_youtube_link($possible_url)){
                                // if it is, try pulling the code from the new location and return the result of that
                                return self::getResponseCodeCurl($possible_url, false);
                            }
                        }
                    }
                }
            }
        }

        return $code;
    }
}
