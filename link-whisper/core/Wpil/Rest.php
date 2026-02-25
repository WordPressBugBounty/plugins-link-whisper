<?php

class Wpil_Rest
{
    const REST_SLUG = 'link-whisper';

    const GSC_ROUTE     = 'code';
    const SI_ROUTE      = 'site-interlinking';
    const AI_AUTH       = 'ai-auth';

    public function register ()
    {
        $this->register_rest();
        add_action('plugins_loaded', [$this, 'whitelist_json_endpoints']);
    }

    public function register_rest ()
    {
        add_action('rest_api_init', function ( $wp_rest_server )
        {
            /**
             * @var WP_REST_Server $wp_rest_server
             */

            register_rest_route(self::REST_SLUG, self::GSC_ROUTE, [
                'methods'             => 'POST',
                'callback'            => [
                    $this,
                    'handler_rest'
                ],
                'permission_callback' => "__return_true",
                'show_in_index'       => false
            ]);

            /**
             * @var WP_REST_Server $wp_rest_server
             */
            register_rest_route(self::REST_SLUG, self::SI_ROUTE, [
                'methods'             => 'POST, GET',
                'callback'            => [
                    $this,
                    'site_interlinking_handler'
                ],
                'permission_callback' => "__return_true",
                'show_in_index'       => false
            ]);

            register_rest_route(self::REST_SLUG, self::AI_AUTH, [
                'methods'             => 'POST',
                'callback'            => [
                    $this,
                    'ai_auth_handler'
                ],
                'permission_callback' => "__return_true",
                'show_in_index'       => false
            ]);
        });
    }

    /**
     * @param \WP_REST_Request $request
     *
     * @return string|\WP_Error
     */
    public function handler_rest ( WP_REST_Request $request )
    {
        if ( !empty($request->get_param('code')) ) {
            $code     = $request->get_param('code');
            $response = Wpil_SearchConsole::get_access_token(trim($code));

            $message = [
                'status' => $response['access_valid'],
                'text'   => $response['message']
            ];

            set_transient('wpil_gsc_access_status_message', $message, 20);

            if ( !empty($response['access_valid']) ) {
                // and update the flag so we know it's live
                update_option('wpil_gsc_app_authorized', true, false);
            }

            return 'ok';
        } elseif ( !empty($request->get_param('error')) ) {
            $message = [
                'status' => false,
                'text'   => __('Access denied', 'rank-logic')
            ];

            set_transient('wpil_gsc_access_status_message', $message, 20);
        }

        return new WP_Error(400, 'Bad request', [ 'status' => 404 ]);
    }

    /**
     * @param \WP_REST_Request $request
     *
     * @return string|\WP_Error
     */
    public function site_interlinking_handler ( WP_REST_Request $request )
    {
        die(); // die because we do the validation elsewhere at the moment
    }

    /**
     * @param \WP_REST_Request $request
     *
     * @return string|\WP_Error
     */
    public function ai_auth_handler( WP_REST_Request $request )
    {
        if(!empty($request->get_param('access_token'))){
            $token = $request->get_param('access_token');
            $user_id = $request->get_param('user_id');
            $uid = (int)$request->get_param('uid');
            $uemail = $request->get_param('uemail');

            if( !empty($token) && 
                false !== strpos($token, 'ai-') && // if the code isn't corrupted
                (bool) preg_match('/\Aai-[0-9a-f]{64}\z/i', $token) && // is a valid token
                (bool) preg_match('/\A[0-9a-f]{32}\z/i', $user_id)) // has a valid id
            {
                // save the token to the options
                update_option('wpil_ai_access_token', Wpil_Toolbox::encrypt($token));
                // and the user id
                update_option('wpil_ai_access_user_id', $user_id);
                // and the user email
                update_option('wpil_ai_access_user_email', sanitize_email($uemail));
                // tag the user with the id
//                update_user_meta($uid, 'wpil_ai_access_user_id', $user_id);
//                update_user_meta($uid, 'wpil_ai_access_user_email', $uemail);
                // and update the flag so we know it's live
                update_option('wpil_ai_access_authorized', true);
            }

            return 'ok';
        }

        return new WP_Error(400, 'Bad request', [ 'status' => 404 ]);
    }


    /**
     * Adds the link whisper json endpoint to any known whitelists so the GSC connection attempts aren't blocked
     **/
    public function whitelist_json_endpoints(){
        if(class_exists('Clearfy_Plugin')){
            add_filter('clearfy_rest_api_white_list', array($this, 'add_directly'));
        }

        if(defined('PERFMATTERS_VERSION')){
            add_filter('perfmatters_rest_api_exceptions', array($this, 'add_directly'));
        }
    }

    /**
     * Adds the json endpoint directly to an array of endpoint names
     **/
    public function add_directly($whitelist = array()){
        if(is_array($whitelist) && !in_array('link-whisper', $whitelist)){
            $whitelist[] = 'link-whisper';
        }

        return $whitelist;
    }
}
