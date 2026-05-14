<?php

class Wpil_Rest
{
    const REST_SLUG = 'link-whisper';

    const GSC_ROUTE     = 'code';
    const SI_ROUTE      = 'site-interlinking';
    const AI_AUTH       = 'ai-auth';
    const CALLBACK_AUTH_PARAM = 'wpil_rest_auth';
    const CALLBACK_AUTH_TRANSIENT_PREFIX = 'wpil_rest_auth_';

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
                'permission_callback' => [
                    $this,
                    'gsc_permission_callback'
                ],
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
                'permission_callback' => [
                    $this,
                    'site_interlinking_permission_callback'
                ],
                'show_in_index'       => false
            ]);

            register_rest_route(self::REST_SLUG, self::AI_AUTH, [
                'methods'             => 'POST',
                'callback'            => [
                    $this,
                    'ai_auth_handler'
                ],
                'permission_callback' => [
                    $this,
                    'ai_auth_permission_callback'
                ],
                'show_in_index'       => false
            ]);
        });
    }

    /**
     * Creates a short-lived REST callback URL for external auth flows.
     *
     * The GSC and AI endpoints must stay externally reachable, so WP admin nonces
     * are not enough. This verifier ties a callback to a flow initiated by an
     * authorized admin without exposing a long-lived site secret.
     **/
    public static function get_authenticated_rest_url($route)
    {
        $route = sanitize_key($route);
        $token = wp_generate_password(32, false, false);

        // TODO: Generate these callback URLs through AJAX when the user starts auth
        // so the expiration window begins on click instead of on page render.
        set_transient(self::CALLBACK_AUTH_TRANSIENT_PREFIX . $route . '_' . wp_hash($token), 1, DAY_IN_SECONDS);

        return add_query_arg(self::CALLBACK_AUTH_PARAM, rawurlencode($token), get_rest_url(null, '/' . self::REST_SLUG . '/' . $route));
    }

    public function gsc_permission_callback(WP_REST_Request $request)
    {
        return $this->validate_callback_auth($request, self::GSC_ROUTE);
    }

    public function ai_auth_permission_callback(WP_REST_Request $request)
    {
        return $this->validate_callback_auth($request, self::AI_AUTH);
    }

    public function site_interlinking_permission_callback(WP_REST_Request $request)
    {
        if($request->get_method() !== 'POST' || empty(get_option('wpil_link_external_sites', false))){
            return new WP_Error('wpil_rest_forbidden', __('REST access is not available for this endpoint.', 'wpil'), array('status' => 403));
        }

        $time = (int) $request->get_param('time');
        $body_params = $request->get_body_params();

        if(!empty($request->get_param('initok'))){
            $site_url = Wpil_SiteConnector::process_initial_request_string(
                sanitize_text_field(wp_unslash($request->get_param('initok'))),
                $time
            );

            return !empty($site_url) ? true : new WP_Error('wpil_rest_forbidden', __('Invalid site interlinking request.', 'wpil'), array('status' => 403));
        }

        if(!empty($request->get_param('fintok'))){
            $query_data = $body_params;
            unset($query_data['fintok'], $query_data['target_url'], $query_data['time'], $query_data['page'], $query_data['limit']);

            if(isset($query_data['ping']) && !empty($query_data['ping'])){
                unset($query_data['ping']);
            }

            $token_valid = Wpil_SiteConnector::verify_access_token(
                sanitize_text_field(wp_unslash($request->get_param('fintok'))),
                esc_url_raw($request->get_param('target_url')),
                $time,
                (int) $request->get_param('page'),
                $query_data
            );

            return !empty($token_valid) ? true : new WP_Error('wpil_rest_forbidden', __('Invalid site interlinking request.', 'wpil'), array('status' => 403));
        }

        return new WP_Error('wpil_rest_forbidden', __('Invalid site interlinking request.', 'wpil'), array('status' => 403));
    }

    private function validate_callback_auth(WP_REST_Request $request, $route)
    {
        $token = $request->get_param(self::CALLBACK_AUTH_PARAM);

        if(empty($token)){
            return new WP_Error('wpil_rest_forbidden', __('Invalid REST callback.', 'wpil'), array('status' => 403));
        }

        $route = sanitize_key($route);
        $token = sanitize_text_field(wp_unslash($token));
        $transient = self::CALLBACK_AUTH_TRANSIENT_PREFIX . $route . '_' . wp_hash($token);

        if(empty(get_transient($transient))){
            return new WP_Error('wpil_rest_forbidden', __('Invalid REST callback.', 'wpil'), array('status' => 403));
        }

        delete_transient($transient);
        return true;
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
            $token   = sanitize_text_field((string) $request->get_param('access_token'));
            $user_id = sanitize_text_field((string) $request->get_param('user_id'));
            $uid     = absint($request->get_param('uid'));
            $uemail  = sanitize_email((string) $request->get_param('uemail'));
            if(empty($uemail) && !empty($uid)){
                $uemail = sanitize_email((string) get_user_meta($uid, 'wpil_wizard_ai_user_email', true));
            }
            if(empty($uemail) && !empty($uid)){
                $uemail = sanitize_email((string) get_user_meta($uid, 'wpil_ai_access_user_email', true));
            }
            if(empty($uemail) && !empty($uid)){
                $user = get_userdata($uid);
                $uemail = (!empty($user) && !empty($user->user_email)) ? sanitize_email((string) $user->user_email) : '';
            }

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
                if(!empty($uemail) && is_email($uemail)){
                    update_option('wpil_ai_access_user_email', $uemail);
                }
                // tag the user with the id
                if(!empty($uid) && !empty($uemail) && is_email($uemail)){
                    update_user_meta($uid, 'wpil_ai_access_user_email', $uemail);
                }
                update_option('wpil_select_ai_provider', 'linkwhisper');
                delete_option('wpil_ai_access_deactivated');
                // and update the flag so we know it's live
                update_option('wpil_ai_access_authorized', true);

                if(!empty($uid)){
                    delete_user_meta($uid, 'wpil_wizard_ai_user_email');
                    delete_user_meta($uid, 'wpil_wizard_ai_activation_token');
                }

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
