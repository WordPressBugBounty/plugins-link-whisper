<?php
    global $shortcode_tags;

    // get if the user has limited the number of links per post
    $max_links_per_post = get_option('wpil_max_links_per_post', 0);

    // get if the user has limited the number of inbound links per post
    $max_inbound_links_per_post = get_option('wpil_max_inbound_links_per_post', 0);

    // get the max age of posts that links will be inserted in
    $max_linking_age = get_option('wpil_max_linking_age', 0);

    // get the max age of posts that links will be inserted in
    $max_suggestion_count = Wpil_Settings::get_max_suggestion_count();

    // get the content formatting level
    $formatting_level = Wpil_Settings::getContentFormattingLevel();

    // get if we're not tracking user ips with the click tracking
    $disable_ip_tracking = get_option('wpil_disable_click_tracking_info_gathering', false);

    // get the section skip type
    $skip_type = Wpil_Settings::getSkipSectionType();
    // get if the user is ignoring any tags from linking
    $ignored_linking_tags = Wpil_Settings::getIgnoreLinkingTags();

    // get the max suggestion anchor length
    $max_suggestion_length = Wpil_Settings::getSuggestionMaxAnchorSize();

    // get the min suggestion anchor length
    $min_suggestion_length = Wpil_Settings::getSuggestionMinAnchorSize();

    // if WP Recipes is active, get the selected field list
    $wp_recipe_fields = (defined('WPRM_POST_TYPE')) ? Wpil_Editor_WPRecipe::get_selected_fields(): array();

    // get the current roles that are available on the site
    $active_roles = Wpil_Settings::get_available_roles(true);
    // and the roles that the user doesn't want to suggest links to
    $ignore_linking_roles = Wpil_Settings::get_ignore_linking_roles();

    $chat_gpt_version = Wpil_Settings::getChatGPTVersion();
    $available_models = Wpil_AI::get_available_models();

    // get the currently open setting tab. Default to "General Settings" if no tab is selected
    $current_tab = (isset($_GET['tab']) && !empty($_GET['tab'])) ? $_GET['tab']: 'general-settings';

    // get if we're highlighting a setting
    $highlight = (isset($_GET['setting_highlight']) && !empty($_GET['setting_highlight'])) ? $_GET['setting_highlight']: '';

    $is_connected_to_linkwhisper_ai = Wpil_Settings::get_linkwhisper_ai_active();
    $ai_is_active = ($is_connected_to_linkwhisper_ai);
    $ai_processing_status = Wpil_AI::get_ai_batch_processing_status();
    $ai_relatedness_threshold = Wpil_Settings::get_ai_sitemap_relatedness_threshold();
    $ai_system_error_log = Wpil_AI::get_combined_error_logs(200);
    $ai_batch_processing_active = Wpil_Settings::get_ai_batch_processing_active();
    $ai_processing_batch_limits = Wpil_Settings::get_ai_batch_limits();

    $ai_selected_process = Wpil_Settings::get_selected_ai_batch_processes();
    $ai_summary_completed = true;//Wpil_AI::check_batch_status_completed(2); // todo uncomment if we add summarizing feature
    $ai_product_completed = Wpil_AI::check_batch_status_completed(3);
    $ai_embedding_completed = Wpil_AI::check_batch_status_completed(4);
    $ai_embedding_calculation_completed = Wpil_AI::check_batch_status_completed('calculated-post-embeddings');
    $ai_keyword_completed = Wpil_AI::check_batch_status_completed(5);
    $ai_keyword_assignment_completed = Wpil_AI::check_batch_status_completed('keyword-assigning');
    $ai_has_process = false;
    $ai_can_do_ai_suggestions = Wpil_Settings::can_do_ai_powered_suggestions();
    $ai_use_ai_suggestions = Wpil_Settings::get_use_ai_suggestions();
    $ai_disable_anchor_building = Wpil_Settings::get_disable_ai_anchor_building();
    $ai_show_top_ai_suggestions = Wpil_Settings::get_show_top_ai_suggestions();
    $ai_disable_inbound_suggestion_cron = Wpil_Settings::disable_ai_suggestions_cron_task();
    $ai_max_processing_age = Wpil_Settings::get_ai_max_processing_age();
    $ai_suggestion_relatedness_threshold = Wpil_Settings::get_ai_suggestion_relatedness_threshold();
    $ai_linkwhisper_decoding_error = !empty(get_option('wpil_ai_token_decoding_error', '0'));

    // if we're not doing anything
    if(!empty($ai_selected_process)){
        foreach($ai_selected_process as $process){
            if(!Wpil_AI::check_batch_status_completed($process)){
                $ai_has_process = true;
            }

            if(!$ai_has_process && ($process === '4' &&  !$ai_embedding_calculation_completed || $process === '5' &&  !$ai_keyword_assignment_completed)){
                $ai_has_process = true;
            }
        }
    }
?>
<style type="text/css">
    #frmSaveSettings .wpil-<?php echo $current_tab; ?>{
        display: table-row;
    }

    <?php
    if(!empty($highlight)){?>
        <?php echo '#'.$highlight; ?> .wpil-setting-name-row{
            box-shadow: #33c7fd -5px 0px 4px 2px;
        }
        <?php echo '#'.$highlight; ?> .wpil-setting-input-row .wpil-setting-input-container{
            padding: 16px 20px;
            margin: -15px -20px;
            box-shadow: #33c7fd 5px 0px 4px 2px;
        }

    <?php } ?>
</style>
<div class="wrap wpil_styles" id="settings_page">
    <?=Wpil_Base::showVersion()?>
    <h1 class="wp-heading-inline"><?php esc_html_e('Link Whisper Settings', 'wpil'); ?></h1>
    <hr class="wp-header-end">
    <div id="poststuff">
        <div id="post-body" class="metabox-holder">
            <h2 class="nav-tab-wrapper" style="margin-bottom:1em;">
                <a class="nav-tab <?php echo ('general-settings' === $current_tab) ? 'nav-tab-active': ''; ?>" id="wpil-general-settings" href="#"><?php esc_html_e('General Settings', 'wpil'); ?></a>
                <a class="nav-tab <?php echo ('content-ignoring-settings' === $current_tab) ? 'nav-tab-active': ''; ?>" id="wpil-content-ignoring-settings" href="#"><?php esc_html_e('Content Ignoring', 'wpil'); ?></a>
                <a class="nav-tab <?php echo ('domain-settings' === $current_tab) ? 'nav-tab-active': ''; ?>" id="wpil-domain-settings" href="#"><?php esc_html_e('Domain Settings', 'wpil'); ?></a>
                <a class="nav-tab <?php echo ('ai-settings' === $current_tab) ? 'nav-tab-active': ''; ?>" id="wpil-ai-settings" href="#"><?php esc_html_e('AI Settings', 'wpil'); ?></a>
                <a class="nav-tab <?php echo ('advanced-settings' === $current_tab) ? 'nav-tab-active': ''; ?>" id="wpil-advanced-settings" href="#"><?php esc_html_e('Advanced Settings', 'wpil'); ?></a>
            </h2>
            <div id="post-body-content" style="position: relative;">
                <?php
                    // if the user has authed GSC, check the status
                    if(Wpil_Settings::HasGSCCredentials()){
                        Wpil_SearchConsole::refresh_auth_token();
                        $authenticated = Wpil_SearchConsole::is_authenticated();
                        $gsc_profiles = Wpil_SearchConsole::get_set_profiles();
                        if(empty($gsc_profiles)){
                            $gsc_profile = Wpil_SearchConsole::get_site_profile();
                            if(!empty($gsc_profile)){
                                $gsc_profiles[] = $gsc_profile;
                            }
                        }
                        $profile_not_found = get_option('wpil_gsc_profile_not_easily_found', false);
                    }else{
                        $authenticated = false;
                        $gsc_profiles = array();
                        $profile_not_found = false;
                    }
                ?>
                <?php if (isset($_REQUEST['success']) && !isset($_REQUEST['access_valid'])) : ?>
                    <div class="notice wpil-notice update notice-success" id="wpil_message" >
                        <p><?php esc_html_e('The Link Whisper Settings have been updated successfully!', 'wpil'); ?></p>
                    </div>
                <?php endif; ?>
                <?php if($message = get_transient('wpil_gsc_access_status_message')){
                    if($message['status']){
                        if(!empty($gsc_profiles)){?>
                            <div class="notice wpil-notice update notice-success" id="wpil_message" >
                                <p><?php echo esc_html($message['text']); ?></p>
                            </div><?php
                        }
                    }else{?>
                        <div class="notice wpil-notice update notice-error" id="wpil_message" >
                        <p><?php echo esc_html($message['text']); ?></p>
                    </div>
                    <?php
                    }
                    ?>
                <?php } ?>
                <?php if(isset($_REQUEST['broken_link_scan_cancelled']) && $message = get_transient('wpil_clear_error_checker_message')){ ?>
                    <div class="notice wpil-notice update notice-success" id="wpil_message" >
                        <p><?php echo esc_html($message); ?></p>
                    </div>
                <?php } ?>
                <?php if(isset($_REQUEST['database_creation_activated']) && $message = get_transient('wpil_database_creation_message')){ ?>
                    <div class="notice wpil-notice update notice-success" id="wpil_message" >
                        <p><?php echo esc_html($message); ?></p>
                    </div>
                <?php } ?>
                <?php if(isset($_REQUEST['database_update_activated']) && $message = get_transient('wpil_database_update_message')){ ?>
                    <div class="notice wpil-notice update notice-success" id="wpil_message" >
                        <p><?php echo esc_html($message); ?></p>
                    </div>
                <?php } ?>
                <?php if(isset($_REQUEST['table_item_count_reset']) && $message = get_transient('wpil_table_item_count_reset_message')){ ?>
                    <div class="notice wpil-notice update notice-success" id="wpil_message" >
                        <p><?php echo esc_html($message); ?></p>
                    </div>
                <?php } ?>
                <?php if(array_key_exists('user_data_deleted', $_REQUEST) && $message = get_transient('wpil_user_data_delete_message')){ ?>
                    <?php if(!empty($_REQUEST['user_data_deleted'])){ ?>
                    <div class="notice wpil-notice update notice-success" id="wpil_message" >
                        <p><?php echo esc_html($message); ?></p>
                    </div>
                    <?php }else{ ?>
                    <div class="notice wpil-notice update notice-error" id="wpil_message" >
                        <p><?php echo esc_html($message); ?></p>
                    </div>
                    <?php } ?>
                <?php } ?>
                <?php if(!empty($authenticated) && empty($gsc_profiles)){?>
                    <div class="notice wpil-notice update notice-error" id="wpil_message" >
                        <p><?php esc_html_e('Connection Error: Either the selected Google account doesn\'t have Search Console access for this site, or Link Whisper is having trouble selecting this site. If you\'re sure the selected account has access to this site\'s GSC data, please select this site\'s profile from the "Currently Selected GSC Profile" option.', 'wpil'); ?></p>
                    </div>
                <?php } ?>
                <?php if(!extension_loaded('mbstring')){?>
                    <div class="notice wpil-notice update notice-error" id="wpil_message" >
                        <p><?php esc_html_e('Dependency Missing: Multibyte String.', 'wpil'); ?></p>
                        <p><?php esc_html_e('The Multibyte String PHP extension is not active on your site. Link Whisper uses this extension to process text when making suggestions. Without this extension, Link Whisper will not be able to make suggestions.', 'wpil'); ?></p>
                        <p><?php esc_html_e('Please contact your hosting provider about enabling the Multibyte String PHP extension.', 'wpil'); ?></p>
                    </div>
                <?php } ?>
                <?php if(!extension_loaded('zlib') && !extension_loaded('Bz2')){?>
                    <div class="notice wpil-notice update notice-error" id="wpil_message" >
                        <p><?php esc_html_e('Dependency Missing: Data Compression Library.', 'wpil'); ?></p>
                        <p><?php esc_html_e('Link Whisper hasn\'t detected a useable compression library on this site. Link Whisper uses compression libraries to reduce how much memory is used when generating suggestions.', 'wpil'); ?></p>
                        <p><?php esc_html_e('It will try to generate suggestions without compressing the suggestion data. If Link Whisper runs out of memory, the suggestion loading will hang in place indefinitely.', 'wpil'); ?></p>
                        <p><?php esc_html_e('If you experience this, please contact your hosting provider about enabling either the "Zlib" compression library, or the "Bzip2" compression library.', 'wpil'); ?></p>
                    </div>
                <?php } ?>
                <?php if(!function_exists('base64_decode') || !function_exists('base64_encode')){?>
                    <div class="notice wpil-notice update notice-error" id="wpil_message" >
                        <p><?php esc_html_e('Dependency Missing: Base64 String Processing.', 'wpil'); ?></p>
                        <p><?php esc_html_e('It appears that the "base64_decode" or the "base64_encode" functions aren\'t available. Link Whisper uses these functions to store and process text data in a way that prevents formatting mistakes.', 'wpil'); ?></p>
                        <p><?php esc_html_e('Without these functions, Link Whisper won\'t be able to preform many of it\'s operations, including Suggestion Generation, Link Deleting, and Autolink Creating.', 'wpil'); ?></p>
                        <p><?php esc_html_e('Please contact your hosting provider or developer about enabling these functions.', 'wpil'); ?></p>
                    </div>
                <?php } ?>
                <?php if(Wpil_Base::overTimeLimit(0, null, true) < 60){?>
                    <div class="notice wpil-notice update notice-info wpil-ai-short-time-limit-notice" id="wpil_message" >
                        <div style="display:flex;">
                            <img src="<?php echo WP_INTERNAL_LINKING_PLUGIN_URL . '/images/lw-icon.png' ?>" width="32px" height="32px" style="margin: 10px 10px 0px 0;">
                            <p style="font-weight: 600;"><?php esc_html_e('Notice: Short PHP Processing Time Detected.', 'wpil'); ?></p>
                        </div>
                        <p><?php esc_html_e('The PHP processing time for the site appears to be shorter than the recommended minimum of 60 seconds. Having a shorter processing time limit may not allow enough time for AI processing to complete, and can result in failed processing attempts.', 'wpil'); ?></p>
                        <p><?php esc_html_e('If you experience failed processing runs, or don\'t see any data despite running the processing scan, please increase your PHP "max_execution_time" to a minimum of 60 seconds, and 90 seconds if possible. If you don\'t know how to do this, please reach out to your hosting provider and they will be able to quickly adjust it.', 'wpil'); ?></p>
                    </div>
                <?php } ?>
                <?php if(Wpil_Report::get_mem_break_point() < 455340275){?>
                    <div class="notice wpil-notice update notice-info" id="wpil_message">
                        <div style="display:flex;">
                            <img src="<?php echo WP_INTERNAL_LINKING_PLUGIN_URL . '/images/lw-icon.png' ?>" width="32px" height="32px" style="margin: 10px 10px 0px 0;">
                            <p style="font-weight: 600;"><?php esc_html_e('Notice: Low PHP Memory Detected', 'wpil'); ?></p>
                        </div>
                        <p><?php esc_html_e('Your site\'s available PHP memory appears to be below Link Whisper\'s recommended 512MB.', 'wpil'); ?></p>
                        <p><?php esc_html_e('This may cause features like link scans or AI suggestions to fail or return incomplete results. We recommend increasing the PHP "memory_limit" to at least 512MB. If you’re not sure how, your hosting provider can usually help adjust this quickly.', 'wpil'); ?></p>
                    </div>
                <?php } ?>
                <?php if(get_option('wpil_oai_insufficient_quota_error', '0') === '1'){ ?>
                    <div class="notice wpil-notice update is-dismissible notice-error wpil-ai-insufficient-quota-notice" id="wpil_message" >
                        <?php
                            $ai_linky = (Wpil_Settings::get_linkwhisper_ai_active()) 
                            ? '<a href="'.admin_url('admin.php?page=link_whisper_ai_subscription').'" target="_blank">' . __('add more credit to your account', 'wpil') . '</a>'
                            : '<a href="https://platform.openai.com/settings/organization/billing/overview" target="_blank">' . __('add more credit to the OpenAI account', 'wpil') . '</a>';
                        ?>
                        <div style="display:flex;">
                            <img src="<?php echo WP_INTERNAL_LINKING_PLUGIN_URL . '/images/lw-icon.png' ?>" width="32px" height="32px" style="margin: 10px 10px 0px 0;">
                            <p style="font-weight: 600;"><?php esc_html_e('Notice: Low Credit Balance', 'wpil'); ?></p>
                        </div>
                        <p><?php esc_html_e('During the last AI processing run, your account ran out of credit and the processing stopped.', 'wpil'); ?></p>
                        <p><?php echo sprintf(esc_html__('To continue processing, please %s. If you have already added more credit to the account, please feel free to dismiss this notice.', 'wpil'), $ai_linky); ?></p>
                    </div>
                <?php } ?>
                <?php if(get_option('wpil_open_ai_key_decoding_error', '0') === '1'){ ?>
                    <div class="notice wpil-notice update is-dismissible notice-error wpil-ai-key-decoding-error-notice" id="wpil_message" >
                        <div style="display:flex;">
                            <img src="<?php echo WP_INTERNAL_LINKING_PLUGIN_URL . '/images/lw-icon.png' ?>" width="32px" height="32px" style="margin: 10px 10px 0px 0;">
                            <p style="font-weight: 600;"><?php esc_html_e('Notice: API Key Decrypting Error', 'wpil'); ?></p>
                        </div>
                        <p><?php esc_html_e('When trying to decrypt the OpenAI API key for use, an error occured and it wasn\'t in a useable format.', 'wpil'); ?></p>
                        <p><?php esc_html_e('Normally when this happens, it\'s because the security tokens that Link Whisper uses to encrypt the API key have changed or are missing.', 'wpil'); ?></p>
                        <p><?php echo sprintf(esc_html__('The tokens that Link Whisper uses are called "%s" and "%s", and they are usually defined inside of your site\'s wp-config.php file.', 'wpil'), 'LOGGED_IN_KEY', 'LOGGED_IN_SALT'); ?></p>
                        <?php $missing_token = false; ?>
                        <?php if(empty(Wpil_Toolbox::get_salt())){ $missing_token = true; ?>
                            <p><?php echo sprintf(esc_html__('Doing a check of the file, it looks like the "%s" token isn\'t defined.', 'wpil'), 'LOGGED_IN_KEY'); ?></p>
                        <?php } ?>
                        <?php if(empty(Wpil_Toolbox::get_key())){  $missing_token = true; ?>
                            <p><?php echo sprintf(esc_html__('And it appears the "%s" token isn\'t currently defined.', 'wpil'), 'LOGGED_IN_SALT'); ?></p>
                        <?php } ?>
                        <?php if(!$missing_token){ ?>
                            <p><?php esc_html_e('The tokens look to be defined, so they may have been changed since the API key was last encrypted. In that case, re-entering the API key will update the encoding and should make it useable.', 'wpil'); ?></p>
                        <?php }else{ ?>
                            <p><?php echo sprintf(esc_html__('If you would like to know more about the tokens, the fine people at Kinsta have an article that %s.', 'wpil'), '<a href="https://kinsta.com/knowledgebase/wordpress-salts/" target="_blank">' . __('explains them in detail', 'wpil') . '</a>'); ?></p>
                        <?php } ?>
                            <p><?php esc_html_e('If you have already taken care of the tokens, please feel free to dismiss this notice.', 'wpil'); ?></p>
                    </div>
                <?php } ?>
                <?php if($ai_linkwhisper_decoding_error){ ?>
                    <div class="notice wpil-notice update is-dismissible notice-error wpil-ai-key-decoding-error-notice" id="wpil_message" >
                        <div style="display:flex;">
                            <img src="<?php echo WP_INTERNAL_LINKING_PLUGIN_URL . '/images/lw-icon.png' ?>" width="32px" height="32px" style="margin: 10px 10px 0px 0;">
                            <p style="font-weight: 600;"><?php esc_html_e('Notice: Link Whisper AI Connection Error', 'wpil'); ?></p>
                        </div>
                        <p><?php esc_html_e('Link Whisper has encountered an error when trying to connect to its AI server.', 'wpil'); ?></p>
                        <p><?php esc_html_e('Normally when this happens, it\'s because the security tokens that Link Whisper uses to encrypt the connection data have changed or are missing.', 'wpil'); ?></p>
                        <p><?php echo sprintf(esc_html__('The tokens that Link Whisper uses are called "%s" and "%s", and they are usually defined inside of your site\'s wp-config.php file.', 'wpil'), 'LOGGED_IN_KEY', 'LOGGED_IN_SALT'); ?></p>
                        <?php $missing_token = false; ?>
                        <?php if(empty(Wpil_Toolbox::get_salt())){ $missing_token = true; ?>
                            <p><?php echo sprintf(esc_html__('Doing a check of the file, it looks like the "%s" token isn\'t defined.', 'wpil'), 'LOGGED_IN_KEY'); ?></p>
                        <?php } ?>
                        <?php if(empty(Wpil_Toolbox::get_key())){  $missing_token = true; ?>
                            <p><?php echo sprintf(esc_html__('And it appears the "%s" token isn\'t currently defined.', 'wpil'), 'LOGGED_IN_SALT'); ?></p>
                        <?php } ?>
                        <?php if(!$missing_token){ ?>
                            <p><?php esc_html_e('The tokens look to be defined, so they may have been changed since the AI connection was last authenticated. In that case, re-authenticating the AI connection will update the token information and should make it possible to run the AI.', 'wpil'); ?></p>
                        <?php }else{ ?>
                            <p><?php echo sprintf(esc_html__('If you would like to know more about the tokens, the fine people at Kinsta have an article that %s.', 'wpil'), '<a href="https://kinsta.com/knowledgebase/wordpress-salts/" target="_blank">' . __('explains them in detail', 'wpil') . '</a>'); ?></p>
                        <?php } ?>
                            <p><?php esc_html_e('If you have already taken care of the tokens, please feel free to dismiss this notice.', 'wpil'); ?></p>
                    </div>
                <?php } ?>
                <form name="frmSaveSettings" id="frmSaveSettings" action='' method='post'>
                    <?php wp_nonce_field('wpil_save_settings','wpil_save_settings_nonce'); ?>
                    <input type="hidden" name="hidden_action" value="wpil_save_settings" />
                    <input type="hidden" name="wpil_related_post_preview_nonce" value="<?php echo wp_create_nonce('wpil-related-posts-preview-nonce');?>" />
                    <table class="form-table">
                        <tbody>
                        <tr class="wpil-general-settings wpil-setting-row">
                            <td scope='row' class="wpil-setting-text"><?php _e('Ignore numbers', 'wpil'); ?></td>
                            <td>
                                <input type="hidden" name="wpil_2_ignore_numbers" value="0" />
                                <input type="checkbox" name="wpil_2_ignore_numbers" <?=get_option('wpil_2_ignore_numbers')==1?'checked':''?> value="1" />
                            </td>
                        </tr>
                        <tr class="wpil-general-settings wpil-setting-row">
                            <td scope='row' class="wpil-setting-text"><?php esc_html_e('Selected Language', 'wpil'); ?></td>
                            <td>
                                <select id="wpil-selected-language" name="wpil_selected_language">
                                    <?php
                                        $languages = Wpil_Settings::getSupportedLanguages();
                                        $selected_language = Wpil_Settings::getSelectedLanguage();
                                    ?>
                                    <?php foreach($languages as $language_key => $language_name) : ?>
                                        <option value="<?php echo $language_key; ?>" <?php selected($language_key, $selected_language); ?>><?php echo $language_name; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" id="wpil-currently-selected-language" value="<?php echo $selected_language; ?>">
                                <input type="hidden" id="wpil-currently-selected-language-confirm-text-1" value="<?php echo esc_attr__('Changing Link Whisper\'s language will replace the current Words to be Ignored with a new list of words.', 'wpil') ?>">
                                <input type="hidden" id="wpil-currently-selected-language-confirm-text-2" value="<?php echo esc_attr__('If you\'ve added any words to the Words to be Ignored area, this will erase them.', 'wpil') ?>">
                            </td>
                        </tr>
                        <tr class="wpil-general-settings wpil-setting-row">
                            <td scope='row' class="wpil-setting-text"><?php esc_html_e('Words to be Ignored', 'wpil'); ?></td>
                            <td>
                                <?php
                                    $lang_data = array();
                                    foreach(Wpil_Settings::getAllIgnoreWordLists() as $lang_id => $words){
                                        $lang_data[$lang_id] = $words;
                                    }
                                ?>
                                <textarea id='ignore_words_textarea' class='regular-text' style="float:left;" rows=10><?php echo esc_textarea(implode("\n", $lang_data[$selected_language])); ?></textarea>
                                <input type="hidden" name='ignore_words' id='ignore_words' value="<?php echo base64_encode(implode("\n", $lang_data[$selected_language])); ?>">
                                <div class="wpil_help">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div><?php esc_html_e('Link Whisper will ignore these words when making linking suggestions. Please enter each word on a new line', 'wpil'); ?></div>
                                </div>
                                <input type="hidden" id="wpil-available-language-word-lists" value="<?php echo esc_attr( wp_json_encode($lang_data, JSON_UNESCAPED_UNICODE) ); ?>">
                            </td>
                        </tr>
                        <tr class="wpil-content-ignoring-settings wpil-setting-row">
                            <td scope='row' class="wpil-setting-text"><?php esc_html_e('Pages to Completely Ignore from Link Whisper.', 'wpil'); ?></td>
                            <td>
                                <textarea name='wpil_ignore_pages_completely' id='wpil_ignore_pages_completely' style="width: 800px;float:left;" class='regular-text' rows=10><?php echo esc_textarea(get_option('wpil_ignore_pages_completely', '')); ?></textarea>
                                <div class="wpil_help">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div style="margin: -160px 0px 0px 30px; width: 300px;">
                                        <?php 
                                        esc_html_e('Link Whisper will completely ignore posts and category pages listed in this field.', 'wpil');
                                        echo '<br /><br />';
                                        esc_html_e('No suggestions will be made TO or FROM the pages listed, no links will be scanned from them, and no autolinks created in them.', 'wpil');
                                        echo '<br /><br />';
                                        esc_html_e('To ignore a page, enter its URL in this field on it\'s own line.', 'wpil');
                                        echo '<br /><br />';
                                        esc_html_e('After entering a URL, you may want to run a link scan to refresh the link data.', 'wpil');
                                        echo '<br /><br />';
                                        ?>
                                    </div>
                                </div>
                                <div style="clear:both;"></div>
                            </td>
                        </tr>
                        <tr class="wpil-content-ignoring-settings wpil-setting-row">
                            <td scope='row' class="wpil-setting-text"><?php esc_html_e('Don\'t Show Suggestion Ignored Posts in the Reports', 'wpil'); ?></td>
                            <td>
                                <div style="max-width:80px;">
                                    <input type="hidden" name="wpil_dont_show_ignored_posts" value="0" />
                                    <input type="checkbox" name="wpil_dont_show_ignored_posts" <?=get_option('wpil_dont_show_ignored_posts')==1?'checked':''?> value="1" />
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div>
                                            <?php esc_html_e('Checking this will tell Link Whisper to hide pages that have been ignored so they don\'t show up in the Reports.', 'wpil');?>
                                            <br />
                                            <br />
                                            <?php esc_html_e('This will apply to pages that have been listed in the "Posts to be Ignored" and "Categories of posts to be Ignored" fields.', 'wpil'); ?>
                                            <br />
                                            <br />
                                            <?php esc_html_e('Pages listed in other ignoring fields will not be affected.', 'wpil'); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-content-ignoring-settings wpil-setting-row">
                            <td scope='row' class="wpil-setting-text"><?php esc_html_e('Posts to be Ignored for Suggestions', 'wpil'); ?></td>
                            <td>
                                <textarea name='wpil_ignore_links' id='wpil_ignore_links' style="width: 800px;float:left;" class='regular-text' rows=10><?php echo esc_textarea(get_option('wpil_ignore_links')); ?></textarea>
                                <div class="wpil_help">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div>
                                        <?php esc_html_e('Link Whisper will not use posts listed here in the suggestions.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php esc_html_e('Outbound linking suggestions will not be made TO these posts. And Inbound linking suggestions will not be made FROM these posts', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php esc_html_e('To ignore a post, enter the post\'s full url on it\'s own line in the text area', 'wpil'); ?>
                                    </div>
                                </div>
                                <div style="clear:both;"></div>
                            </td>
                        </tr>
                        <tr class="wpil-content-ignoring-settings wpil-setting-row">
                            <td scope='row' class="wpil-setting-text"><?php esc_html_e('Categories of posts to be Ignored for Suggestions', 'wpil'); ?></td>
                            <td>
                                <textarea name='wpil_ignore_categories' id='wpil_ignore_categories' style="width: 800px;float:left;" class='regular-text' rows=10><?php echo esc_textarea(get_option('wpil_ignore_categories')); ?></textarea>
                                <div class="wpil_help">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div>
                                        <?php esc_html_e('Link Whisper will not suggest posts from categories listed in this field.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php esc_html_e('Outbound linking suggestions will not be made TO posts in the listed categories. And Inbound linking suggestions will not be made FROM posts in the listed categories.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php esc_html_e('To ignore an entire category, enter the category\'s full url on it\'s own line in the text area', 'wpil'); ?>
                                    </div>
                                </div>
                                <div style="clear:both;"></div>
                            </td>
                        </tr>
                        <tr class="wpil-content-ignoring-settings wpil-setting-row">
                            <td scope='row' class="wpil-setting-text"><?php esc_html_e('Posts to be Ignored from the Sitemaps', 'wpil'); ?></td>
                            <td>
                                <textarea name='wpil_ignore_sitemap_posts' id='wpil_ignore_sitemap_posts' style="width: 800px;float:left;" class='regular-text' rows=10><?php echo esc_textarea(get_option('wpil_ignore_sitemap_posts')); ?></textarea>
                                <div class="wpil_help">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div>
                                        <?php esc_html_e('Link Whisper will show posts listed here in the Sitemaps.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php esc_html_e('To ignore a post, enter the post\'s full url on it\'s own line in the text area', 'wpil'); ?>
                                    </div>
                                </div>
                                <div style="clear:both;"></div>
                            </td>
                        </tr>
                        <tr class="wpil-content-ignoring-settings wpil-setting-row">
                            <td scope='row' class="wpil-setting-text"><?php esc_html_e('Posts to be Ignored from Orphaned Posts Report', 'wpil'); ?></td>
                            <td>
                                <textarea name='wpil_ignore_orphaned_posts' id='wpil_ignore_orphaned_posts' style="width: 800px;float:left;" class='regular-text' rows=10><?php echo esc_textarea(get_option('wpil_ignore_orphaned_posts', '')); ?></textarea>
                                <div class="wpil_help">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div><?php esc_html_e('Link Whisper will not show the listed posts on the Orphaned Posts report. To ignore a post, enter a post\'s full url on it\'s own line in the text area', 'wpil'); ?></div>
                                </div>
                                <div style="clear:both;"></div>
                            </td>
                        </tr>
                        <tr class="wpil-content-ignoring-settings wpil-setting-row">
                            <td scope='row' class="wpil-setting-text"><?php esc_html_e('Categories of Posts to be Ignored from Orphaned Posts Report', 'wpil'); ?></td>
                            <td>
                                <textarea name='wpil_ignore_orphaned_posts_by_category' id='wpil_ignore_orphaned_posts_by_category' style="width: 800px;float:left;" class='regular-text' rows=10><?php echo esc_textarea(get_option('wpil_ignore_orphaned_posts_by_category', '')); ?></textarea>
                                <div class="wpil_help">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div><?php esc_html_e('Link Whisper will not show posts in the listed categories on the Orphaned Posts report. To ignore a category of post, enter a category\'s full url on it\'s own line in the text area', 'wpil'); ?></div>
                                </div>
                                <div style="clear:both;"></div>
                            </td>
                        </tr>
                        <?php if(class_exists('ACF')){ ?>
                        <tr class="wpil-content-ignoring-settings wpil-setting-row">
                            <td scope='row' class="wpil-setting-text"><?php esc_html_e('ACF Fields to be Ignored', 'wpil'); ?></td>
                            <td>
                                <textarea name='wpil_ignore_acf_fields' id='wpil_ignore_acf_fields' style="width: 800px;float:left;" class='regular-text' rows=10><?php echo esc_textarea(get_option('wpil_ignore_acf_fields', '')); ?></textarea>
                                <div class="wpil_help">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div>
                                        <?php esc_html_e('Link Whisper will not process content in the ACF fields listed here. To ignore a field, enter each field\'s name on it\'s own line in the text area', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php esc_html_e('This will entirely ignore the field, so it won\'t show up in reports, be processed for autolinks, or be scanned during the suggestion process.', 'wpil'); ?>
                                    </div>
                                </div>
                                <div style="clear:both;"></div>
                            </td>
                        </tr>
                        <tr class="wpil-content-ignoring-settings wpil-setting-row">
                            <td scope='row' class="wpil-setting-text"><?php esc_html_e('Ignore Small ACF Text Fields', 'wpil'); ?></td>
                            <td>
                                <div style="max-width:80px;">
                                    <input type="hidden" name="wpil_ignore_small_acf_text_fields" value="0" />
                                    <input type="checkbox" name="wpil_ignore_small_acf_text_fields" <?=get_option('wpil_ignore_small_acf_text_fields', 0)==1?'checked':''?> value="1" />
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help"></i>
                                        <div>
                                            <?php esc_html_e('Turning this on will tell Link Whisper to not process or add links to small text fields created by ACF. (These are the single-line of text fields, not textareas or WYSIWYG fields)', 'wpil'); ?>
                                        </div>
                                    </div>
                                    <div style="clear:both;"></div>
                                </div>
                            </td>
                        </tr>
                        <?php } ?>
                        <tr class="wpil-content-ignoring-settings wpil-setting-row">
                            <td scope='row' class="wpil-setting-text"><?php esc_html_e('Links to Ignore Clicks on', 'wpil'); ?></td>
                            <td>
                                <textarea name='wpil_ignore_click_links' id='wpil_ignore_click_links' style="width: 800px;float:left;" class='regular-text' rows=10><?php echo esc_textarea(get_option('wpil_ignore_click_links', '')); ?></textarea>
                                <div class="wpil_help">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div style="margin: -150px 0px 0px 30px;">
                                        <?php 
                                        esc_html_e('Link Whisper will not track clicks on links listed here.', 'wpil');
                                        echo '<br /><br />';
                                        esc_html_e('To ignore a link by URL, enter it\'s URL. The effects apply across the site, so all links with matching URLs will be ignored. Each URL must go on it\'s own line.', 'wpil');
                                        echo '<br /><br />';
                                        esc_html_e('To ignore a link by anchor text, enter it\'s anchor text. The effects apply across the site, so all links with matching anchor texts will be ignored. Each anchor text must go on it\'s own line.', 'wpil');
                                        echo '<br /><br />';
                                        ?>
                                    </div>
                                </div>
                                <div style="clear:both;"></div>
                            </td>
                        </tr>
                        <tr class="wpil-content-ignoring-settings wpil-setting-row">
                            <td scope='row' class="wpil-setting-text"><?php esc_html_e('Links to be Ignored From The Reports.', 'wpil'); ?></td>
                            <td>
                                <textarea name='wpil_links_to_ignore' id='wpil_links_to_ignore' style="width: 800px;float:left;" class='regular-text' rows=10><?php echo esc_textarea(get_option('wpil_links_to_ignore', '')); ?></textarea>
                                <div class="wpil_help">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div style="margin: -150px 0px 0px 30px; width: 300px;">
                                        <?php 
                                        esc_html_e('Link Whisper will ignore the links listed in this field and won\'t show them in the Links Report or other linking stat areas.', 'wpil');
                                        echo '<br /><br />';
                                        esc_html_e('To ignore a link, enter it in this field on it\'s own line.', 'wpil');
                                        echo '<br /><br />';
                                        esc_html_e('Wildcard matching can be performed by using the * character on the end of the link that you want to match. So for example, entering "https://example.com/*" would match links like "https://example.com/example-page-1", "https://example.com/category/examples" and "https://example.com/example-pages/example-page-2"', 'wpil');
                                        echo '<br /><br />';
                                        esc_html_e('After entering a link, you will need to run a link scan to refresh the stored data.', 'wpil');
                                        echo '<br /><br />';
                                        ?>
                                    </div>
                                </div>
                                <div style="clear:both;"></div>
                            </td>
                        </tr>
                        <tr class="wpil-content-ignoring-settings wpil-setting-row">
                            <td scope='row' class="wpil-setting-text"><?php esc_html_e('Links to be Ignored From The Broken Link Scan.', 'wpil'); ?></td>
                            <td>
                                <textarea name='wpil_broken_links_to_ignore' id='wpil_broken_links_to_ignore' style="width: 800px;float:left;" class='regular-text' rows=10><?php echo esc_textarea(get_option('wpil_broken_links_to_ignore', '')); ?></textarea>
                                <div class="wpil_help">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div style="margin: -150px 0px 0px 30px; width: 300px;">
                                        <?php 
                                        esc_html_e('Link Whisper will ignore the links listed in this field and won\'t scan them during the Broken Link Scans.', 'wpil');
                                        echo '<br /><br />';
                                        esc_html_e('To ignore a link, enter it in this field on it\'s own line.', 'wpil');
                                        echo '<br /><br />';
                                        esc_html_e('Wildcard matching can be performed by using the * character on the end of the link that you want to match. So for example, entering "https://example.com/*" would match links like "https://example.com/example-page-1", "https://example.com/category/examples" and "https://example.com/example-pages/example-page-2"', 'wpil');
                                        echo '<br /><br />';
                                        esc_html_e('After entering a link, you may wish to run a new Broken Link Scan to refresh the stored data.', 'wpil');
                                        echo '<br /><br />';
                                        ?>
                                    </div>
                                </div>
                                <div style="clear:both;"></div>
                            </td>
                        </tr>
                        <tr class="wpil-content-ignoring-settings wpil-setting-row">
                            <td scope='row' class="wpil-setting-text"><?php esc_html_e('Elements to Ignore by CSS Class.', 'wpil'); ?></td>
                            <td>
                                <textarea name='wpil_ignore_elements_by_class' id='wpil_ignore_elements_by_class' style="width: 800px;float:left;" class='regular-text' rows=10><?php echo esc_textarea(get_option('wpil_ignore_elements_by_class', '')); ?></textarea>
                                <div class="wpil_help">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div style="margin: -160px 0px 0px 30px; width: 300px;">
                                        <?php 
                                        esc_html_e('Link Whisper will ignore HTML tags that contain CSS classes listed in this field. It won\'t extract links, or make linking suggestions, from elements that have the listed CSS classes.', 'wpil');
                                        echo '<br /><br />';
                                        esc_html_e('To ignore a class, enter it in this field on it\'s own line.', 'wpil');
                                        echo '<br /><br />';
                                        esc_html_e('Wildcard matching can be performed by using the * character on the end of the class that you want to match. So for example, entering "exam*" would match classes like "example", "examples", and "examination"', 'wpil');
                                        echo '<br /><br />';
                                        esc_html_e('After entering a class, you may want to run a link scan to refresh the link data.', 'wpil');
                                        echo '<br /><br />';
                                        ?>
                                    </div>
                                </div>
                                <div style="clear:both;"></div>
                            </td>
                        </tr>
                        <tr class="wpil-content-ignoring-settings wpil-setting-row">
                            <td scope='row' class="wpil-setting-text"><?php esc_html_e('HTML Tags to Ignore from Linking.', 'wpil'); ?></td>
                            <td>
                                <select multiple name='wpil_ignore_tags_from_linking[]' class="wpil-setting-multiselect" id='wpil_ignore_tags_from_linking' style="width: 800px;float:left;">
                                <?php
                                    foreach(Wpil_Settings::getPossibleIgnoreLinkingTags() as $possible_ignore_tag){
                                        echo '<option value="' . $possible_ignore_tag . '" ' . (in_array($possible_ignore_tag, $ignored_linking_tags, true) ? 'selected="selected"': '') . '>' . $possible_ignore_tag . '</option>';
                                    } 
                                ?>
                                </select>
                                <div class="wpil_help">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div style="margin: -160px 0px 0px 30px; width: 300px;">
                                        <?php 
                                        esc_html_e('Link Whisper will not create links in any HTML tag selected in this dropdown', 'wpil');
                                        echo '<br /><br />';
                                        esc_html_e('This will apply to both the Suggestions and the Autolinking.', 'wpil');
                                        ?>
                                    </div>
                                </div>
                                <div style="clear:both;"></div>
                            </td>
                        </tr>
                        <?php
                        if(defined('ELEMENTOR_VERSION')){ 
                            $supported_modules = Wpil_Settings::getPossibleIgnoreElementorModules();
                            $selected_modules = Wpil_Settings::getIgnoreLinkingElementorModules();
                        ?>
                        <tr class="wpil-content-ignoring-settings wpil-setting-row">
                            <td scope='row' class="wpil-setting-text"><?php esc_html_e('Elementor Elements to Ignore from Linking.', 'wpil'); ?></td>
                            <td>
                                <select multiple name='wpil_ignore_elementor_from_linking[]' class="wpil-setting-multiselect" id='wpil_ignore_elementor_from_linking' style="width: 800px;float:left;">
                                <?php
                                    foreach($supported_modules as $module_name => $possible_module){
                                        echo '<option value="' . $module_name . '" ' . (in_array($module_name, $selected_modules, true) ? 'selected="selected"': '') . '>' . $possible_module . '</option>';
                                    } 
                                ?>
                                </select>
                                <div class="wpil_help">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div style="margin: -160px 0px 0px 30px; width: 300px;">
                                        <?php 
                                        esc_html_e('Link Whisper will not create links in any Elementor module selected in this dropdown', 'wpil');
                                        echo '<br /><br />';
                                        esc_html_e('This will apply to both the Suggestions and the Autolinking.', 'wpil');
                                        echo '<br /><br />';
                                        esc_html_e('NOTE: All active Elementor modules are listed here, even though only one\'s with text content can be linked. This is to allow you more control in ignoring modules.', 'wpil');
                                        ?>
                                    </div>
                                </div>
                                <div style="clear:both;"></div>
                            </td>
                        </tr>
                        <?php } ?>
                        <tr class="wpil-content-ignoring-settings wpil-setting-row">
                            <td scope='row' class="wpil-setting-text"><?php esc_html_e('Shortcodes to Ignore by Name.', 'wpil'); ?></td>
                            <td>
                                <textarea name='wpil_ignore_shortcodes_by_name' id='wpil_ignore_shortcodes_by_name' style="width: 800px;float:left;" class='regular-text' rows=10><?php echo esc_textarea(get_option('wpil_ignore_shortcodes_by_name', '')); ?></textarea>
                                <div class="wpil_help">
                                    <i class="dashicons dashicons-info"></i>    
                                    <div style="margin: 0px 0px 0px -500px; width: 500px; overflow: auto; max-height: 200px;">
                                        <?php 
                                        echo '<h3 style="color:#fff; margin-top: 0px;">';
                                        esc_html_e('The known shortcode names are:', 'wpil');
                                        echo '</h3>';
                                        echo '<thing style="display:flex; flex-wrap: wrap;">'; // not div since that gets hidden in wpil_helps
                                        foreach($shortcode_tags as $tag_name => $dat){
                                            echo '<span style="padding: 0 10px 0 0;">' . $tag_name . '</span>';
                                        }
                                        echo '</thing>';
                                        echo '<h3 style="color:#fff;">';
                                        echo '(' . __('There may be other shortcodes active, but this is what we could find.', 'wpil') . ')';
                                        echo '</h3>';
                                        ?>
                                    </div>
                                </div>
                                <div class="wpil_help">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div style="margin: -160px 0px 0px 30px; width: 300px;">
                                        <?php 
                                        esc_html_e('Link Whisper will ignore any shortcodes listed in this field. It won\'t extract links from the listed shortcodes, or create links in any text content of the shortcode.', 'wpil');
                                        echo '<br /><br />';
                                        esc_html_e('To ignore a shortcode, enter it\'s name (without square brackets) in this field on it\'s own line.', 'wpil');
                                        echo '<br /><br />';
                                        esc_html_e('So for example, to ignore the WordPress [caption][/caption] shortcode, enter "caption" (without quotes) on it\'s own line in the field', 'wpil');
                                        echo '<br /><br />';
                                        esc_html_e('After entering a shortcode, you may want to run a link scan to refresh any stored link data based on shortcodes.', 'wpil');
                                        echo '<br /><br />';
                                        ?>
                                    </div>
                                </div>
                                <div style="clear:both;"></div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row' class="wpil-setting-text"><?php esc_html_e('Mark Links as External', 'wpil'); ?></td>
                            <td>
                                <textarea name='wpil_marked_as_external' id='wpil_marked_as_external' style="max-width: 800px;float:left;width: 100%;" class='regular-text' rows=10><?php echo esc_textarea(get_option('wpil_marked_as_external')); ?></textarea>
                                <div class="wpil_help">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div class="display-under"><?php esc_html_e('Link Whisper will recognize these links as external on the Report page. Please enter each link on it\'s own line in the text area', 'wpil'); ?></div>
                                </div>
                                <div style="clear:both;"></div>
                            </td>
                        </tr>
                        <tr class="wpil-domain-settings wpil-setting-row">
                            <td scope='row' class="wpil-setting-text"><?php esc_html_e('Mark Domains as Internal', 'wpil'); ?></td>
                            <td>
                                <textarea name='wpil_domains_marked_as_internal' id='wpil_domains_marked_as_internal' style="width: 800px;float:left;" class='regular-text' rows=5><?php echo esc_textarea(get_option('wpil_domains_marked_as_internal')); ?></textarea>
                                <div class="wpil_help">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div><?php esc_html_e('Link Whisper will recognize links with these domains as internal on the Report page. Please enter each domain on it\'s own line in the text area as it appears in your browser', 'wpil'); ?></div>
                                </div>
                                <div style="clear:both;"></div>
                            </td>
                        </tr>
                        <?php if(class_exists('ACF')){ ?>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row' class="wpil-setting-text"><?php esc_html_e('Only Process These ACF Fields', 'wpil'); ?></td>
                            <td>
                                <textarea name='wpil_process_these_acf_fields' id='wpil_process_these_acf_fields' style="max-width: 800px;float:left;width: 100%;" class='regular-text' rows=5><?php echo esc_textarea(get_option('wpil_process_these_acf_fields')); ?></textarea>
                                <div class="wpil_help">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div class="display-under">
                                        <?php esc_html_e('By default, Link Whisper tries to scan all ACF fields automatically, but since some sites can have thousands of ACF fields, it can be more practical to specify the ones to scan and ignore the rest.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php esc_html_e('This setting allows you to specify the fields to scan, so the rest can be ignored.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php esc_html_e('Wildcard matching is supported with the percent (%) character, so you can tell Link Whisper to scan fields like "example_content" and "testing_content" with "%_content"', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php esc_html_e('Please enter each field name on it\'s own line in the text area.', 'wpil'); ?>
                                    </div>
                                </div>
                                <div style="clear:both;"></div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row' class="wpil-setting-text"><?php esc_html_e('ACF Post-Referencing Fields', 'wpil'); ?></td>
                            <td>
                                <textarea name='wpil_acf_post_reference_fields' id='wpil_acf_post_reference_fields' style="width: 800px;float:left;" class='regular-text' rows=5><?php echo esc_textarea(get_option('wpil_acf_post_reference_fields')); ?></textarea>
                                <div class="wpil_help">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div class="display-under" style="width: 350px;">
                                        <?php esc_html_e('Link Whisper will scan ACF fields listed here to see if they refer to a post containing ACF content meant to be displayed flexibly across the site.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php esc_html_e('Frequently, reusable ACF "modules" are created by making a post/custom post with ACF content, and then storing the post\'s ID in a data field on the post(s) that its meant to be used.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php esc_html_e('This setting allows you to specify what fields contain IDs for the "module" posts, so Link Whisper can pull in the "module" content and scan it for links.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php esc_html_e('Please enter each field name on it\'s own line in the text area. For wildcard matching, please use the "%" character.', 'wpil'); ?>
                                    </div>
                                </div>
                                <div style="clear:both;"></div>
                            </td>
                        </tr>
                        <?php } ?>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row' class="wpil-setting-text"><?php esc_html_e('Custom Fields to Process', 'wpil'); ?></td>
                            <td>
                                <textarea name='wpil_custom_fields_to_process' id='wpil_custom_fields_to_process' style="max-width: 800px;float:left;width: 100%;" class='regular-text' rows=5><?php echo esc_textarea(get_option('wpil_custom_fields_to_process')); ?></textarea>
                                <div class="wpil_help">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div class="display-under">
                                        <?php esc_html_e('Link Whisper will scan custom-content fields listed here for links and to see if it can create links in the content fields.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php esc_html_e('Advanced Custom Fields are automatically scanned, so there\'s no need to list them here.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php esc_html_e('Please enter each field name on it\'s own line in the text area.', 'wpil'); ?>
                                    </div>
                                </div>
                                <div style="clear:both;"></div>
                            </td>
                        </tr>
                        <tr class="wpil-ai-settings wpil-setting-row">
                            <td scope='row' style="min-width:400px;"><?php _e('Link Whisper AI', 'wpil'); ?></td>
                            <td>
                                <div style="display: inline-block; position: relative;">
                                    <?php if(empty(Wpil_Settings::get_linkwhisper_ai_token()) && empty(Wpil_Settings::getOpenAIKey())){ // if they aren't authenticated and don't have an openai key?>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=link_whisper_ai_subscription'))?>" style="margin-top:5px; user-select: none; text-align: center;" class="button-primary"><?php esc_html_e('Get Started!', 'wpil'); ?></a>
                                    <?php } elseif($ai_linkwhisper_decoding_error && $is_connected_to_linkwhisper_ai) { ?>
                                        <a href="<?php echo esc_url(Wpil_AI::get_linkwhisper_ai_auth_url())?>" style="margin-top:5px; user-select: none; text-align: center;" class="button-primary"><?php esc_html_e('Re-connect', 'wpil'); ?></a>
                                        <a style="margin-top:5px; user-select: none; text-align: center;" id="wpil-disconnect-ai-subscription" data-nonce="<?php echo wp_create_nonce('disconnect-ai-subscription'); ?>" class="button-primary"><?php esc_html_e('Disconnect', 'wpil'); ?></a>
                                    <?php }else { ?>
                                        <a href="<?php echo esc_url(Wpil_AI::get_linkwhisper_ai_auth_url())?>" style="margin-top:5px; user-select: none; text-align: center;" class="button-primary <?php echo ($is_connected_to_linkwhisper_ai) ? 'hide-setting': '';?>"><?php esc_html_e('Connect', 'wpil'); ?></a>
                                        <a style="margin-top:5px; user-select: none; text-align: center;" id="wpil-disconnect-ai-subscription" data-nonce="<?php echo wp_create_nonce('disconnect-ai-subscription'); ?>" class="button-primary <?php echo (!$is_connected_to_linkwhisper_ai) ? 'hide-setting': '';?>"><?php esc_html_e('Disconnect', 'wpil'); ?></a>
                                    <?php } ?>
                                    <div class="wpil_help" style="float:right">
                                        <i class="dashicons dashicons-editor-help"></i>
                                        <?php if(!$is_connected_to_linkwhisper_ai){ ?>
                                        <div style="margin: -150px 0px 0px 30px;">
                                            <?php 
                                            _e('Clicking this button will connect your site to Link Whisper\'s AI platform.', 'wpil');
                                            echo '<br /><br />';
                                            _e('Once connected, Link Whisper will use your AI plan\'s credits to provide you with advanced AI features.', 'wpil');
                                            echo '<br /><br />';
                                            _e('If your plan reaches zero credits, processing will stop until the plan renews or more credits are added.', 'wpil');
                                            ?>
                                        </div>
                                        <?php }else{ ?>
                                        <div style="margin: -150px 0px 0px 30px;">
                                            <?php 
                                            _e('You are connected to Link Whisper\'s AI platform!', 'wpil');
                                            echo '<br /><br />';
                                            _e('Link Whisper will use your AI plan\'s credits to provide you with advanced AI features.', 'wpil');
                                            echo '<br /><br />';
                                            _e('Clicking on this button will disconnect this site from your the AI.', 'wpil');
                                            ?>
                                        </div>
                                        <?php } ?>
                                    </div>
                                </div>
                                <div style="width:350px; display:inline-block"></div>
                                <div style="clear:both;"></div>
                            </td>
                        </tr>
                        <tr class="wpil-ai-settings wpil-setting-row <?php echo !empty($ai_is_active) ? '': 'hide-setting'; ?>">
                            <td scope='row' class="wpil-setting-text"><?php _e('AI Processing Actions to Perform', 'wpil'); ?></td>
                            <td>
                                <div style="display: inline-block; position: relative;">
                                    <?php
                                        $available_processes = Wpil_Settings::get_available_ai_batch_processes();
                                        $process_display_names = Wpil_Settings::get_ai_batch_name_list();
                                    ?>
                                    <div class="wpil_help" style="position: absolute; right: -50px; top: -4px;">
                                        <i class="dashicons dashicons-editor-help"></i>
                                        <div style=" width: 400px">
                                            <?php _e('The toggles in this section allow you to select what AI Batch processes you would like to run.', 'wpil'); ?>
                                            <br />
                                            <br />
                                            <?php _e('The "AI Relation Analysis" process determines how related all the posts on the site are to each other. This information is used to make better Suggestions, Related Posts, and the AI Sitemap.', 'wpil'); ?>
                                            <br />
                                            <br />
                                            <?php _e('The "Keyword Analysis" process scans each post\'s content and creates a list of highly relevent Target Keywords.', 'wpil'); ?>
                                            <br />
                                            <br />
                                            <?php _e('The "Product Detection" process scans a post\'s content and identifies any products mentioned in it.', 'wpil'); ?>
                                        </div>
                                    </div>
                                    <?php foreach ($available_processes as $process_code => $process) : ?>
                                            <input type="checkbox" name="wpil_selected_ai_batch_processes[]" value="<?=$process_code?>" data-wpil-ai-process-name="<?=$process?>" data-wpil-ai-process-saved-state="<?=in_array($process_code, $ai_selected_process)?'1':'0'?>" <?=in_array($process_code, $ai_selected_process)?'checked':''?>><label><?=$process_display_names[$process]?></label><br>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-ai-settings wpil-setting-row <?php echo !empty($ai_is_active) ? '': 'hide-setting'; ?>">
                            <td scope='row' class="wpil-setting-text"><?php _e('Use AI-Powered Suggestions', 'wpil'); ?></td>
                            <td>
                                <div style="display: inline-block; position: relative;">
                                    <input type="hidden" name="wpil_use_ai_suggestions" value="0" />
                                    <input type="checkbox" <?php echo (empty($ai_can_do_ai_suggestions) ? 'disabled="disabled"': '');?> name="wpil_use_ai_suggestions" <?=!empty($ai_use_ai_suggestions)&&!empty($ai_can_do_ai_suggestions)?'checked':''?> value="1" />
                                    <div class="wpil_help" style="position: absolute; right: -50px; top: -4px;">
                                        <i class="dashicons dashicons-editor-help"></i>
                                        <div style=" width: 400px">
                                            <?php _e('This setting tells Link Whisper to use AI much more actively when generating suggestions.', 'wpil'); ?>
                                            <br />
                                            <br />
                                            <?php _e('When active, Link Whisper will use AI to search sentence by sentence to find the most related posts to link to.', 'wpil'); ?>
                                            <br />
                                            <br />
                                            <?php _e('To be able to use this feature, at least 10% of the site\'s posts need to be scanned using the "AI Relation Analysis". For best results, please scan the full site.', 'wpil'); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-ai-settings wpil-setting-row wpil-disable-ai-anchor-building <?php echo (!empty($ai_is_active) && !empty($ai_use_ai_suggestions)) ? '': 'hide-setting'; ?>">
                            <td scope='row' class="wpil-setting-text"><?php _e('Disable AI Anchor Building', 'wpil'); ?></td>
                            <td>
                                <div style="display: inline-block; position: relative;">
                                    <input type="hidden" name="wpil_disable_ai_anchor_building" value="0" />
                                    <input type="checkbox" <?php echo (empty($ai_can_do_ai_suggestions) ? 'disabled="disabled"': '');?> name="wpil_disable_ai_anchor_building" <?=!empty($ai_use_ai_suggestions)&&!empty($ai_can_do_ai_suggestions)&&!empty($ai_disable_anchor_building)?'checked':''?> value="1" />
                                    <div class="wpil_help" style="position: absolute; right: -50px; top: -4px;">
                                        <i class="dashicons dashicons-editor-help"></i>
                                        <div style=" width: 400px">
                                            <?php _e('This setting tells Link Whisper not to use AI when building the suggested anchors.', 'wpil'); ?>
                                            <br />
                                            <br />
                                            <?php _e('When active, Link Whisper will use a keyword-based selection method that does not require AI to build the suggested links.', 'wpil'); ?>
                                            <br />
                                            <br />
                                            <?php _e('The result is suggestions that load faster, that may sometimes require a small amount of adjustment.', 'wpil'); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-ai-settings wpil-setting-row wpil-show-top-ai-suggestions <?php echo (!empty($ai_is_active) && !empty($ai_use_ai_suggestions)) ? '': 'hide-setting'; ?>">
                            <td scope='row' class="wpil-setting-text"><?php _e('Only Show Top AI Suggestions', 'wpil'); ?></td>
                            <td>
                                <div style="display: inline-block; position: relative;">
                                    <input type="hidden" name="wpil_restrict_to_top_ai_suggestions" value="0" />
                                    <input type="checkbox" <?php echo (empty($ai_can_do_ai_suggestions) ? 'disabled="disabled"': '');?> name="wpil_restrict_to_top_ai_suggestions" <?=!empty($ai_show_top_ai_suggestions)&&!empty($ai_can_do_ai_suggestions)?'checked':''?> value="1" />
                                    <div class="wpil_help" style="position: absolute; right: -50px; top: -4px;">
                                        <i class="dashicons dashicons-editor-help"></i>
                                        <div style=" width: 400px">
                                            <?php _e('This setting tells Link Whisper to only show you the top AI Powered Suggestions for each post.', 'wpil'); ?>
                                            <br />
                                            <br />
                                            <?php _e('Turning it off will show you ALL of the suggestions that are considered to be good matches, not just the top matches.', 'wpil'); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-ai-settings wpil-setting-row wpil-disable-ai-suggestion-cron <?php echo (!empty($ai_is_active) && !empty($ai_use_ai_suggestions)) ? '': 'hide-setting'; ?>">
                            <td scope='row' class="wpil-setting-text"><?php _e('Disable AI Powered Suggestion Cron Task', 'wpil'); ?></td>
                            <td>
                                <div style="display: inline-block; position: relative;">
                                    <input type="hidden" name="wpil_disable_ai_suggestions_cron" value="0" />
                                    <input type="checkbox" <?php echo (empty($ai_can_do_ai_suggestions) ? 'disabled="disabled"': '');?> name="wpil_disable_ai_suggestions_cron" <?=!empty($ai_disable_inbound_suggestion_cron)&&!empty($ai_can_do_ai_suggestions)?'checked':''?> value="1" />
                                    <div class="wpil_help" style="position: absolute; right: -50px; top: -4px;">
                                        <i class="dashicons dashicons-editor-help"></i>
                                        <div style=" width: 400px">
                                            <?php _e('Stops the automatic process that updates AI link suggestions in the background.', 'wpil'); ?>
                                            <br /><br />
                                            <?php _e('Enable this if you prefer to fetch suggestions manually or reduce server activity.', 'wpil'); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-ai-settings wpil-setting-row wpil-create-post-embeddings-setting <?php echo !empty($ai_is_active) && in_array(4, $ai_selected_process) ? '': 'hide-setting'; ?>">
                            <td scope='row' class="wpil-setting-text"><?php _e('AI Relation Analysis Batch Size', 'wpil'); ?></td>
                            <td>
                                <div style="max-width: 210px;">
                                <input type="number" class="" style="min-width: 170px;" name="wpil_ai_batch_processing_limits[live][create-post-embeddings]" value="<?php echo max([1, (int) $ai_processing_batch_limits['live']['create-post-embeddings']]);?>" min="1" max="<?php echo !($is_connected_to_linkwhisper_ai) ? 1000: 50;?>">
                                <div class="wpil_help" style="float:right">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div style="background: rgba(0, 0, 0, 0.8); width: 400px">
                                        <?php 
                                        esc_html_e('This setting controls the maximum number of posts that Link Whisper will process during the AI Relation Analysis.', 'wpil');
                                        ?>
                                        <br><br>
                                        <?php 
                                        esc_html_e('By default, we ask it to process 50 posts, but if you\'re experiencing persistent errors, you may need to reduce the number of posts.', 'wpil');
                                        ?>
                                    </div>
                                </div>
                                <div style="clear:both;"></div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-ai-settings wpil-setting-row wpil-product-detecting-setting <?php echo !empty($ai_is_active) && in_array(3, $ai_selected_process) ? '': 'hide-setting'; ?>">
                            <td scope='row' class="wpil-setting-text"><?php _e('Product Detecting ChatGPT Version', 'wpil'); ?></td>
                            <td>
                                <div style="max-width: 150px;">
                                <select class="wpil-ai-version-selector" name="wpil_chat_gpt_api[product-detecting]" data-wpil-ai-process-saved-state="<?php echo $chat_gpt_version['product-detecting'];?>">
                                    <?php
                                    foreach($available_models as $model_name => $model){
                                        ?>
                                        <option value="<?php echo esc_attr($model_name); ?>" <?php selected($model_name, $chat_gpt_version['product-detecting']); ?>><?php echo $model; ?></option>
                                        <?php
                                    }
                                    ?>
                                </select>
                                <div class="wpil_help" style="float:right">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div style="background: rgba(0, 0, 0, 0.8); width: 400px">
                                        <?php 
                                        esc_html_e('This is the version of ChatGPT that Link Whisper will use to detect products inside of posts.', 'wpil');
                                        ?>
                                        <br><br>
                                        <?php 
                                        esc_html_e('The currently available ChatGPT versions are:', 'wpil');
                                        ?>
                                        <ul>
                                            <li>- <?php esc_html_e('GPT-4o: Most advanced and capable version, runs slower and is more expensive than GPT-4o Mini', 'wpil'); ?></li><br>
                                            <li>- <?php esc_html_e('GPT-4o Mini: Best cost-benefit model, runs fastest and is least expensive. This is the recommended model for product detection.', 'wpil'); ?></li><br>
                                        </ul>
                                    </div>
                                </div>
                                <div style="clear:both;"></div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-ai-settings wpil-setting-row wpil-keyword-detecting-setting <?php echo !empty($ai_is_active) && in_array(5, $ai_selected_process) ? '': 'hide-setting'; ?>">
                            <td scope='row' class="wpil-setting-text"><?php _e('Keyword Analysis ChatGPT Version', 'wpil'); ?></td>
                            <td>
                                <div style="max-width: 150px;">
                                <select class="wpil-ai-version-selector" name="wpil_chat_gpt_api[keyword-detecting]" data-wpil-ai-process-saved-state="<?php echo $chat_gpt_version['keyword-detecting'];?>">
                                    <?php
                                    foreach($available_models as $model_name => $model){
                                        ?>
                                        <option value="<?php echo esc_attr($model_name); ?>" <?php selected($model_name, $chat_gpt_version['keyword-detecting']); ?>><?php echo $model; ?></option>
                                        <?php
                                    }
                                    ?>
                                </select>
                                <div class="wpil_help" style="float:right">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div style="background: rgba(0, 0, 0, 0.8); width: 400px">
                                        <?php 
                                        _e('This is the version of ChatGPT that Link Whisper will use to create Target Keywords for posts.', 'wpil');
                                        ?>
                                        <br><br>
                                        <?php 
                                        esc_html_e('The currently available ChatGPT versions are:', 'wpil');
                                        ?>
                                        <ul>
                                            <li>- <?php esc_html_e('GPT-4o: Most advanced and capable version, runs slower and is more expensive than GPT-4o Mini', 'wpil'); ?></li><br>
                                            <li>- <?php esc_html_e('GPT-4o Mini: Best cost-benefit model, runs fastest and is least expensive. This is the recommended model for creating keywords', 'wpil'); ?></li><br>
                                        </ul>
                                    </div>
                                </div>
                                <div style="clear:both;"></div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-ai-settings wpil-setting-row wpil-ai-any-setting <?php echo !empty($ai_is_active) ? '': 'hide-setting'; ?>">
                            <td scope='row' class="wpil-setting-text"><?php _e('Process AI Data', 'wpil'); ?></td>
                            <td>
                                <div style="max-width: 235px;">
                                    <a style="margin-top:5px; user-select: none; display: none;" class="wpil-live-download-ai-data-disabled button-primary button-disabled"><?php esc_html_e('Please Save Settings', 'wpil'); ?></a>
                                    <a style="margin-top:5px; user-select: none;" class="wpil-live-download-ai-data button-primary <?php echo ($ai_has_process) ? '': 'button-disabled'; ?>" data-nonce="<?php echo wp_create_nonce(wp_get_current_user()->ID . 'wpil_download_ai_data'); ?>" data-wpil-ai-batch-processing-active="<?php echo ($ai_batch_processing_active) ? 1: 0; ?>"><?php esc_html_e('Begin Processing Data', 'wpil'); ?></a>
                                    <div class="wpil_help wpil-live-download-ai-data-help-tooltip" style="float: right">
                                        <i class="dashicons dashicons-editor-help"></i>
                                        <div style="margin: -50px 0px 0px 30px; width: 400px">
                                            <?php 
                                            _e('Clicking this button will tell Link Whisper to send post content data to AI so it can be processed.', 'wpil');
                                            echo '<BR><BR>';
                                            _e('Processing the data requires the Settings tab to remain open in order to run, and it will use your AI credits.', 'wpil');
                                            echo '<BR><BR>';
                                            _e('As the credits are used, a total will be shown on the page along with the current progress.', 'wpil');
                                            echo '<BR><BR>';
                                            _e('The total cost and amount of time required depends on the size of the site and the amount of content on each page.', 'wpil');
                                            echo '<BR><BR>';
                                            _e('As a rule of thumb, it usually requires 1 credit per process per post (using "GPT-4o Mini"). So for example, processing a post with AI Relation Analysis and Keyword Detection usually costs 2 credits.', 'wpil');
                                            ?>
                                        </div>
                                    </div>
                                </div>
                                    <div class="wpil-ai-loading-progress-bars" style="display:none">
                                        <div class="wpil-ai-loading-background"></div>
                                        <div class="wpil-ai-loading-wrapper wpil-is-popup">
                                            <div class="wpil-ai-loading-header"><?php esc_html_e('Link Whisper AI Processing', 'wpil'); ?></div>
                                            <span id="wpil-ai-loading-close" class="dashicons dashicons-no"></span>
                                            <div class="content-analysis-loading-section wpil-ai-loading-section wpil-create-post-embeddings-setting <?php echo $ai_embedding_completed || !in_array(4, $ai_selected_process) ? 'hide-setting': ''; ?>">
                                                <div class="wpil-ai-loading-section-header">
                                                    AI Relation Analysis Progress:
                                                </div>
                                                <div style="display: flex;">
                                                    <div style="max-width: 150px" class="content-analysis-loading-completion">
                                                        Completed: <div class="wpil-completed-count"><?php echo esc_html($ai_processing_status['completed']['create-post-embeddings']);?></div>
                                                    </div>
                                                    <?php $progress_percent = (!empty($ai_processing_status['total'])) ? round(intval($ai_processing_status['completed']['create-post-embeddings']) / intval($ai_processing_status['total']), 2) * 100: 0;?>
                                                    <div class="progress_panel loader content-analysis-loader" data-wpil-total-count="<?php echo intval($ai_processing_status['total']); ?>" data-wpil-loading-completed="<?php echo intval($ai_processing_status['completed']['create-post-embeddings']); ?>"><div class="progress_count" style="width:<?php echo $progress_percent . '%';?>"><?php echo $progress_percent . '%'; ?></div></div>
                                                    <div style="max-width: 150px">
                                                        Total: <?php echo intval($ai_processing_status['total']);?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="content-calculation-loading-section wpil-ai-loading-section wpil-calculated-post-embeddings-setting <?php echo $ai_embedding_calculation_completed || !in_array(4, $ai_selected_process) ? 'hide-setting': ''; ?>">
                                                <div class="wpil-ai-loading-section-header">
                                                    AI Relation Calculation Progress:
                                                </div>
                                                <div style="display: flex;">
                                                    <div style="max-width: 150px" class="content-calculation-loading-completion">
                                                        Completed: <div class="wpil-completed-count"><?php echo esc_html($ai_processing_status['completed']['calculated-post-embeddings']);?></div>
                                                    </div>
                                                    <?php $progress_percent = (!empty($ai_processing_status['total'])) ? round(intval($ai_processing_status['completed']['calculated-post-embeddings']) / intval($ai_processing_status['total']), 2) * 100: 0;?>
                                                    <div class="progress_panel loader content-calculation-loader" data-wpil-total-count="<?php echo intval($ai_processing_status['total']); ?>" data-wpil-loading-completed="<?php echo intval($ai_processing_status['completed']['calculated-post-embeddings']); ?>"><div class="progress_count" style="width:<?php echo $progress_percent . '%';?>"><?php echo $progress_percent . '%'; ?></div></div>
                                                    <div style="max-width: 150px">
                                                        Total: <?php echo intval($ai_processing_status['total']);?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="product-detection-loading-section wpil-ai-loading-section wpil-product-detecting-setting <?php echo $ai_product_completed || !in_array(3, $ai_selected_process) ? 'hide-setting': ''; ?>">
                                                <div class="wpil-ai-loading-section-header">
                                                    Product Detection Progress:
                                                </div>
                                                <div style="display: flex;">
                                                    <div style="max-width: 150px" class="product-detection-loading-completion">
                                                        Completed: <div class="wpil-completed-count"><?php echo esc_html($ai_processing_status['completed']['product-detecting']);?></div>
                                                    </div>
                                                    <?php $progress_percent = (!empty($ai_processing_status['total'])) ? round(intval($ai_processing_status['completed']['product-detecting']) / intval($ai_processing_status['total']), 2) * 100: 0;?>
                                                    <div class="progress_panel loader product-detection-loader" data-wpil-total-count="<?php echo intval($ai_processing_status['total']); ?>" data-wpil-loading-completed="<?php echo intval($ai_processing_status['completed']['product-detecting']); ?>"><div class="progress_count" style="width:<?php echo $progress_percent . '%';?>"><?php echo $progress_percent . '%'; ?></div></div>
                                                    <div style="max-width: 150px">
                                                        Total: <?php echo intval($ai_processing_status['total']);?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="keyword-detection-loading-section wpil-ai-loading-section wpil-keyword-detecting-setting <?php echo $ai_keyword_completed || !in_array(5, $ai_selected_process) ? 'hide-setting': ''; ?>">
                                                <div class="wpil-ai-loading-section-header">
                                                    Target Keyword Detection Progress:
                                                </div>
                                                <div style="display: flex;">
                                                    <div style="max-width: 150px" class="keyword-detection-loading-completion">
                                                        Completed: <div class="wpil-completed-count"><?php echo esc_html($ai_processing_status['completed']['keyword-detecting']);?></div>
                                                    </div>
                                                    <?php $progress_percent = (!empty($ai_processing_status['total'])) ? round(intval($ai_processing_status['completed']['keyword-detecting']) / intval($ai_processing_status['total']), 2) * 100: 0;?>
                                                    <div class="progress_panel loader keyword-detection-loader" data-wpil-total-count="<?php echo intval($ai_processing_status['total']); ?>" data-wpil-loading-completed="<?php echo intval($ai_processing_status['completed']['keyword-detecting']); ?>"><div class="progress_count" style="width:<?php echo $progress_percent . '%';?>"><?php echo $progress_percent . '%'; ?></div></div>
                                                    <div style="max-width: 150px">
                                                        Total: <?php echo intval($ai_processing_status['total']);?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="keyword-assigning-loading-section wpil-ai-loading-section wpil-keyword-assigning-setting <?php echo $ai_keyword_assignment_completed || !in_array(5, $ai_selected_process) ? 'hide-setting': ''; ?>">
                                                <div class="wpil-ai-loading-section-header">
                                                    Target Keyword Assigning Progress:
                                                </div>
                                                <div style="display: flex;">
                                                    <div style="max-width: 150px" class="keyword-assigning-loading-completion">
                                                        Completed: <div class="wpil-completed-count"><?php echo esc_html($ai_processing_status['completed']['keyword-assigning']);?></div>
                                                    </div>
                                                    <?php $progress_percent = (!empty($ai_processing_status['total'])) ? round(intval($ai_processing_status['completed']['keyword-assigning']) / intval($ai_processing_status['total']), 2) * 100: 0;?>
                                                    <div class="progress_panel loader keyword-assigning-loader" data-wpil-total-count="<?php echo intval($ai_processing_status['total']); ?>" data-wpil-loading-completed="<?php echo intval($ai_processing_status['completed']['keyword-assigning']); ?>"><div class="progress_count" style="width:<?php echo $progress_percent . '%';?>"><?php echo $progress_percent . '%'; ?></div></div>
                                                    <div style="max-width: 150px">
                                                        Total: <?php echo intval($ai_processing_status['total']);?>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php if($ai_has_process){ ?>
                                            <br><br>
                                            <div class="ai-current-process-section">
                                                Current Status: <div class="ai-current-process-text"><?php echo esc_html_e('Starting Data Processing...', 'wpil')?></div>
                                            </div>
                                            <div class="ai-estimated-cost-section">
                                                <?php
                                                if(!$is_connected_to_linkwhisper_ai){ ?>
                                                <div>
                                                    Estimated Processing Cost: <div class="ai-estimated-cost">$0.00</div>
                                                </div>
                                                <?php }else{?>
                                                    AI Tokens Used: <div class="ai-estimated-cost">0</div>
                                                <?php } ?>
                                            </div>
                                            <?php } ?>
                                        <!--<div style="font-size: 18px; font-weight: 600; margin-bottom: 30px;">Please leave this browser tab open, if you close it, the process will stop and need to be restarted later</div>-->
                                    </div>
                                </div>
                                <div style="clear:both;"></div>
                            </td>
                        </tr>
                        <tr class="wpil-ai-settings wpil-setting-row wpil-ai-any-setting <?php echo !empty($ai_is_active) ? '': 'hide-setting'; ?>">
                            <td scope='row' class="wpil-setting-text"><?php _e('Clear AI Data', 'wpil'); ?></td>
                            <td>
                                <div style="max-width: 140px">
                                    <a style="margin-top:5px;" class="wpil-clear-all-ai-data button-primary <?php echo !Wpil_AI::has_ai_processed_data() ? 'button-disabled': '';?>" data-nonce="<?php echo wp_create_nonce(wp_get_current_user()->ID . 'wpil_clear_ai_data'); ?>"><?php esc_html_e('Clear Data', 'wpil'); ?></a>
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help"></i>
                                        <div style="margin: -50px 0px 0px 30px; width: 400px">
                                            <?php 
                                            _e('Clicking this button will tell Link Whisper to delete all of the AI generated data it has stored on the site.', 'wpil');
                                            echo '<BR><BR>';
                                            _e('This will clear the AI Relation data, and the raw keyword and product data. Any existing Target Keywords and the AI Sitemap will remain until they are regenerated directly.', 'wpil');
                                            ?>
                                        </div>
                                    </div>
                                </div>
                                <div style="clear:both;"></div>
                            </td>
                        </tr>
                        <tr class="wpil-ai-settings wpil-setting-row wpil-ai-any-setting <?php echo !empty($ai_is_active) ? '': 'hide-setting'; ?>">
                            <td scope='row' class="wpil-setting-text"><?php _e('Enable AI Processing Cron Task', 'wpil'); ?></td>
                            <td>
                                <div style="max-width: 100px">
                                    <input type="hidden" name="wpil_enable_ai_batch_processing" value="0" />
                                    <input type="checkbox" name="wpil_enable_ai_batch_processing" <?=($ai_batch_processing_active)?'checked':''?> value="1" />
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help"></i>
                                        <div>
                                            <?php 
                                            _e('Toggling this on will activate Link Whisper\'s cron-based AI processing system.', 'wpil');
                                            ?>
                                            <br><br>
                                            <?php 
                                            _e('The system sends batches of post content data to AI for processing without the need to keep the tab open.', 'wpil');
                                            ?>
                                            <br><br>
                                            <?php
                                            _e('The process is fully automatic, and is ideal for keeping the AI data up to date with any site changes. As it\'s designed to run slowly, it may take up to 24 hours for data to begin to be available for use. Processing a full site\'s worth of data with this method alone may take several days.', 'wpil');
                                            ?>
                                        </div>
                                    </div>
                                    <div style="clear:both;"></div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-ai-settings wpil-setting-row wpil-ai-any-setting <?php echo !empty($ai_is_active) ? '': 'hide-setting'; ?>">
                            <td scope="row"><?php esc_html_e('Don\'t Process Posts Older Than', 'wpil'); ?></td>
                            <td>
                                <select name="wpil_ai_max_processing_age" style="float:left; max-width:100px">
                                    <option value="0" <?=0===(int)$ai_max_processing_age ? 'selected' : '' ?>><?php esc_html_e('No Limit', 'wpil'); ?></option>
                                    <?php for($i = 1; $i <= 100; $i++) : ?>
                                        <option value="<?=$i?>" <?=$i===(int)$ai_max_processing_age ? 'selected' : '' ?>><?php printf( _n( '%s year', '%s years', $i, 'wpil' ), $i ); ?></option>
                                    <?php endfor; ?>
                                </select>
                                <div class="wpil_help">
                                    <i class="dashicons dashicons-editor-help" style="margin-top: 4px;"></i>
                                    <div style="margin: -130px 0px 0px 30px;">
                                        <?php 
                                        esc_html_e('This setting puts a limit on how old of posts Link Whisper will process with AI. When set, Link Whisper will not process posts that are older than the limit.', 'wpil');
                                        echo '<br /><br />';
                                        esc_html_e('This only applies to AI processing going forward, it doesn\'t reset or delete any existing AI generated data.', 'wpil');
                                        ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-ai-settings wpil-setting-row <?php echo !empty($ai_is_active) ? '': 'hide-setting'; ?>">
                            <td scope='row' class="wpil-setting-text"><?php _e('Minimum Relatedness Score for Suggestions', 'wpil'); ?></td>
                            <td>
                                <input type="range" name="wpil_suggestion_relatedness_threshold" class="wpil-thick-range" style="float:left;" min="0.4" max="1" step="0.001" value="<?php echo $ai_suggestion_relatedness_threshold;?>">
                                <div class="wpil_help" style="margin: -6px 0 0 0;">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div style="margin: -50px 0px 0px 30px; width: 400px">
                                        <?php 
                                        _e('This setting tells Link Whisper how similar posts need to be in order to be shown in the suggestions.', 'wpil');
                                        echo '<BR><BR>';
                                        _e('Changing it doesn\'t affect the AI batch processing, or require the "AI Relation Analysis" process to be rerun.', 'wpil');
                                        echo '<BR><BR>';
                                        _e('Setting it to a lower number will result in less similar posts being considered related, while a higher number will require posts to be more alike to be considered related.', 'wpil');
                                        ?>
                                    </div>
                                </div>
                                <div style="clear:both;"></div>
                                <div>
                                    <span class="wpil-embedding-relatedness-threshold"><?php echo round($ai_suggestion_relatedness_threshold * 100, 3) . '%'; ?></span><span> Similar</span>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-ai-settings wpil-setting-row <?php echo !empty($ai_is_active) ? '': 'hide-setting'; ?>">
                            <td scope='row' class="wpil-setting-text"><?php _e('Sitemap Relatedness Threshold', 'wpil'); ?></td>
                            <td>
                                <input type="range" name="wpil_sitemap_embedding_relatedness_threshold" class="wpil-thick-range" style="float:left;" min="0" max="1" step="0.001" value="<?php echo $ai_relatedness_threshold;?>">
                                <div class="wpil_help" style="margin: -6px 0 0 0;">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div style="margin: -50px 0px 0px 30px; width: 400px">
                                        <?php 
                                        _e('This setting tells Link Whisper how similar posts need to be in order to be considered related in the AI Sitemap.', 'wpil');
                                        echo '<BR><BR>';
                                        _e('Changing it doesn\'t affect the AI batch processing, or require the "AI Relation Analysis" process to be rerun.', 'wpil');
                                        echo '<BR><BR>';
                                        _e('Setting it to a lower number will result in less similar posts being considered related, while a higher number will require posts to be more alike to be considered related.', 'wpil');
                                        ?>
                                    </div>
                                </div>
                                <div style="clear:both;"></div>
                                <div>
                                    <span class="wpil-embedding-relatedness-threshold"><?php echo round($ai_relatedness_threshold * 100, 3) . '%'; ?></span><span> Similar</span>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-ai-settings wpil-setting-row wpil-ai-any-setting <?php echo !empty($ai_is_active) ? '': 'hide-setting'; ?>">
                            <td scope='row' class="wpil-setting-text"><?php _e('Content Processing Status', 'wpil'); ?></td>
                            <?php if(Wpil_AI::has_ai_processed_data()) {?>
                            <td>
                                <div style="max-height: 400px; overflow: auto;">
                                    <table class="wp-list-table widefat striped">
                                        <thead>
                                        <tr>
                                            <th class="wpil-create-post-embeddings-setting <?php echo (!in_array(4, $ai_selected_process)) ? 'hide-setting': '';?>" style="min-width: 250px;">AI Relation Analysis Progress</th>
                                            <th class="wpil-keyword-detecting-setting <?php echo (!in_array(5, $ai_selected_process)) ? 'hide-setting': '';?>" style="min-width: 250px;">Target Keyword Detection Progress</th>
                                            <th class="wpil-product-detecting-setting <?php echo (!in_array(3, $ai_selected_process)) ? 'hide-setting': '';?>" style="min-width: 250px;">Product Detection Progress</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td class="wpil-create-post-embeddings-setting <?php echo (!in_array(4, $ai_selected_process)) ? 'hide-setting': '';?>">
                                                    <div>
                                                        <ul style="margin-left: 20px;list-style: disc;">
                                                            <li>Total Processable Post Count: <?php echo intval($ai_processing_status['total']);?></li>
                                                            <li>Posts Analyzed: <?php echo esc_html($ai_processing_status['completed']['create-post-embeddings']);?></li>
                                                            <li class="<?php echo !empty($ai_batch_processing_active) ? '': 'hide-setting'; ?>">Posts in Batch Processing Queue: <?php echo esc_html($ai_processing_status['in_progress']['create-post-embeddings']);?></li>
                                                            <li>Unanalyzed Posts Remaining: <?php echo ($ai_processing_status['completed']['create-post-embeddings'] + $ai_processing_status['in_progress']['create-post-embeddings']) ? max(($ai_processing_status['total'] - $ai_processing_status['completed']['create-post-embeddings']), 0): 0;?></li>
                                                            <li>Relations Calculated: <?php echo intval($ai_processing_status['completed']['calculated-post-embeddings']);?></li>
                                                        </ul>
                                                    </div><br>
                                                </td>
                                                <td class="wpil-keyword-detecting-setting <?php echo (!in_array(5, $ai_selected_process)) ? 'hide-setting': '';?>">
                                                    <div>
                                                        <ul style="margin-left: 20px;list-style: disc;">
                                                            <li>Total Processable Post Count: <?php echo intval($ai_processing_status['total']);?></li>
                                                            <li>Posts Scanned: <?php echo esc_html($ai_processing_status['completed']['keyword-detecting']);?></li>
                                                            <li class="<?php echo !empty($ai_batch_processing_active) ? '': 'hide-setting'; ?>">Posts in Batch Processing Queue: <?php echo esc_html($ai_processing_status['in_progress']['keyword-detecting']);?></li>
                                                            <li>Unscanned Posts Remaining: <?php echo ($ai_processing_status['completed']['keyword-detecting'] + $ai_processing_status['in_progress']['keyword-detecting']) ? max(($ai_processing_status['total'] - $ai_processing_status['completed']['keyword-detecting']), 0): 0;?></li>
                                                            <li>Posts With Keywords Assigned: <?php echo ($ai_processing_status['completed']['keyword-assigning']) ? max(($ai_processing_status['completed']['keyword-assigning']), 0): 0;?></li>
                                                        </ul>
                                                    </div><br>
                                                </td>
                                                <td class="wpil-product-detecting-setting <?php echo (!in_array(3, $ai_selected_process)) ? 'hide-setting': '';?>">
                                                    <div>
                                                        <ul style="margin-left: 20px;list-style: disc;">
                                                            <li>Total Processable Post Count: <?php echo intval($ai_processing_status['total']);?></li>
                                                            <li>Posts Scanned: <?php echo esc_html($ai_processing_status['completed']['product-detecting']);?></li>
                                                            <li class="<?php echo !empty($ai_batch_processing_active) ? '': 'hide-setting'; ?>">Posts in Batch Processing Queue: <?php echo esc_html($ai_processing_status['in_progress']['product-detecting']);?></li>
                                                            <li>Unscanned Posts Remaining: <?php echo ($ai_processing_status['completed']['product-detecting'] + $ai_processing_status['in_progress']['product-detecting'] > 0) ? max(($ai_processing_status['total'] - $ai_processing_status['completed']['product-detecting']), 0): 0;?></li>
                                                        </ul>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div style="clear:both;"></div>
                            </td>
                            <?php }else{ ?>
                            <td><?php esc_html_e('When the content processing begins, the state of each AI process will be listed here.', 'wpil'); ?></td>
                            <?php } ?>
                        </tr>
                        <tr class="wpil-ai-settings wpil-setting-row <?php echo !empty($ai_is_active) ? '': 'hide-setting'; ?>">
                            <td scope='row' class="wpil-setting-text"><?php _e('System Error Log', 'wpil'); ?></td>
                            <td>
                                <div style="max-height: 400px; overflow: auto; max-width: 900px;">
                                    <table class="wp-list-table widefat striped">
                                        <thead>
                                        <tr>
                                            <th style="min-width: 200px;">Date/Time</th>
                                            <th style="min-width: 300px;">Error Message</th>
                                            <th>Error Data</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php if (!empty($ai_system_error_log)) {
                                            $format = get_option('date_format', 'F j, Y') . ' ' . get_option('time_format', 'g:i a');
                                            $offset = get_option('gmt_offset', 0) * 3600;
                                            foreach ($ai_system_error_log as $dat) : 
                                            ?>
                                                <tr>
                                                    <td data-log-event-time-unix="<?php echo esc_attr($dat->process_time); ?>">
                                                        <?php echo date_i18n($format, ($dat->process_time + $offset)); ?>
                                                    </td>
                                                    <td>
                                                        <?php echo esc_html($dat->message_text); ?>
                                                        <br>
                                                    </td>
                                                    <td>
                                                        Full Error Logged in Database
                                                        <div style="display:none;">
                                                            <?php if (isset($dat->log_data)) {
                                                                echo esc_html(print_r($dat->log_data, true));
                                                            }elseif(isset($dat->batch_data)){
                                                                echo esc_html(print_r(Wpil_Toolbox::json_decompress($dat->batch_data), true));
                                                            } ?>
                                                        </div>
                                                        <br>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php }else{ ?>
                                            <tr>
                                                <td><?php echo date_i18n(get_option('date_format', 'F j, Y'), current_time('timestamp')); ?></td>
                                                <td><?php _e('No errors logged.', 'wpil'); ?></td>
                                                <td></td>
                                            </tr>
                                        <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div style="clear:both;"></div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row' class="wpil-setting-text"><?php esc_html_e('Prevent Two-Way Linking', 'wpil'); ?></td>
                            <td>
                                <div style="max-width:80px;">
                                    <input type="hidden" name="wpil_prevent_two_way_linking" value="0" />
                                    <input type="checkbox" name="wpil_prevent_two_way_linking" <?=!empty(get_option('wpil_prevent_two_way_linking', false))?'checked':''?> value="1" />
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div>
                                            <?php 
                                            esc_html_e('Checking this will keep Link Whisper from creating two-way linking relationships.', 'wpil');
                                            echo '<br /><br />';
                                            esc_html_e('If for example post "A" has a link to post "B", this setting will prevent Link Whisper from suggesting a link from post "B" to post "A".', 'wpil');
                                            ?>
                                        </div>
                                    </div>
                                    <div style="clear:both;"></div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-general-settings wpil-setting-row">
                            <td scope='row' class="wpil-setting-text"><?php esc_html_e('Post Types to Process', 'wpil'); ?></td>
                            <td>
                                <div style="display: inline-block;">
                                    <div class="wpil_help" style="float:right; position: relative; left: 30px;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div>
                                            <?php
                                                esc_html_e('This setting controls the post types that Link Whisper is active in.', 'wpil');
                                                echo '<br /><br />';
                                                esc_html_e('Link Whisper will create links in the selected post types, scan the post types for links, and will operate all of Link Whisper\'s Advanced Functionality in the post types.', 'wpil');
                                                echo '<br /><br />';
                                                esc_html_e('After changing the post type selection, please go to the Report page and click the "Run a Link Scan" button to clear the old link data.', 'wpil');
                                            ?>
                                        </div>
                                    </div>
                                    <?php foreach ($types_available as $type => $label) : ?>
                                        <input type="checkbox" name="wpil_2_post_types[]" value="<?=$type?>" <?=in_array($type, $types_active)?'checked':''?>><label><?=ucfirst($label)?></label><br>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-general-settings wpil-setting-row wpil-suggestion-post-type-limit-setting <?php echo (empty(get_option('wpil_limit_suggestions_to_post_types', false))) ? 'hide-setting': '';?>">
                            <td scope='row' class="wpil-setting-text"><?php esc_html_e('Post Types to Point Suggestions to', 'wpil'); ?></td>
                            <td>
                                <div style="display: inline-block;">
                                    <div class="wpil_help" style="float:right; position: relative; left: 30px;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div>
                                            <?php esc_html_e('Link Whisper will only offer suggestions that point to posts in the selected post types.', 'wpil'); ?>
                                            <br /><br />
                                            <?php esc_html_e('Only post types that Link Whisper is set to process will be listed here. If you don\'t see a post type listed here, please try selecting it in the "Post Types to Process" setting.', 'wpil'); ?>
                                        </div>
                                    </div>
                                    <?php foreach ($types_available as $type => $label) : ?>
                                        <?php 
                                            $class = 'wpil-suggestion-limit-type-' . $type;
                                            $class .= !in_array($type, $types_active) ? ' hide-setting': ''; 
                                        ?>
                                        <input type="checkbox" name="wpil_suggestion_limited_post_types[]" value="<?=$type?>" <?php echo in_array($type, $suggestion_types_active)?'checked':''?> class="<?php echo $class; ?>"><label class="<?php echo $class; ?>"><?=ucfirst($label)?></label><br class="<?php echo $class; ?>">
                                    <?php endforeach; ?>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-general-settings wpil-setting-row">
                            <td scope='row' class="wpil-setting-text"><?php esc_html_e('Term Types to Process', 'wpil'); ?></td>
                            <td>
                                <div style="display: inline-block;">
                                    <div class="wpil_help" style="float:right; position: relative; left: 30px;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div>
                                            <?php
                                                esc_html_e('This setting controls the term types that Link Whisper is active in.', 'wpil');
                                                echo '<br /><br />';
                                                esc_html_e('Link Whisper will create links in the selected term\'s archive pages, scan the term\'s archive pages for links, and will operate all of Link Whisper\'s Advanced Functionality in the term\'s archive pages.', 'wpil');
                                                echo '<br /><br />';
                                                esc_html_e('After changing the term type selection, please go to the Report page and click the "Run a Link Scan" button to clear the old link data.', 'wpil');
                                            ?>
                                        </div>
                                    </div>
                                    <?php foreach ($term_types_available as $type) : ?>
                                        <input type="checkbox" name="wpil_2_term_types[]" value="<?=$type?>" <?=in_array($type, $term_types_active)?'checked':''?>><label><?=ucfirst($type)?></label><br>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-general-settings wpil-setting-row">
                            <td scope="row"><span><?php esc_html_e('Number of', 'wpil'); ?></span>
                                <select name="wpil_skip_section_type" class="wpil-setting-inline-select">
                                    <option value="sentences"<?php selected($skip_type, 'sentences');?>><?php esc_html_e('Sentences', 'wpil'); ?></option>
                                    <option value="paragraphs"<?php selected($skip_type, 'paragraphs');?>><?php esc_html_e('Paragraphs', 'wpil'); ?></option>
                                </select>
                                <span><?php esc_html_e('to Skip', 'wpil');?></span>
                            </td>
                            <td>
                                <select name="wpil_skip_sentences" style="float:left; max-width:100px">
                                    <?php for($i = 0; $i <= 10; $i++) : ?>
                                        <option value="<?=$i?>" <?=$i==Wpil_Settings::getSkipSentences() ? 'selected' : '' ?>><?=$i?></option>
                                    <?php endfor; ?>
                                </select>
                                <div class="wpil_help">
                                    <i class="dashicons dashicons-editor-help" style="margin-top: 4px;"></i>
                                    <div><?php esc_html_e('Link Whisper will not suggest links for this number of sentences or paragraphs appearing at the beginning of a post.', 'wpil'); ?></div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-general-settings wpil-setting-row">
                            <td scope="row"><?php esc_html_e('Max Number of Suggestions to Display', 'wpil'); ?></td>
                            <td>
                                <select name="wpil_max_suggestion_count" style="float:left; max-width:100px">
                                    <option value="0" <?=0===(int)$max_suggestion_count ? 'selected' : '' ?>><?php esc_html_e('No Limit', 'wpil'); ?></option>
                                    <?php for($i = 1; $i <= 100; $i++) : ?>
                                        <option value="<?=$i?>" <?=$i===(int)$max_suggestion_count ? 'selected' : '' ?>><?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                                <div class="wpil_help">
                                    <i class="dashicons dashicons-editor-help" style="margin-top: 4px;"></i>
                                    <div style="margin: -130px 0px 0px 30px;">
                                        <?php 
                                        esc_html_e('This is the maximum number of suggestions that Link Whisper will show you at once in the Suggestion Panels.', 'wpil');
                                        ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-general-settings wpil-setting-row">
                            <td scope="row"><?php esc_html_e('Remove Link Whisper Support Popup', 'wpil'); ?></td>
                            <td>
                                <div style="max-width:80px;">
                                    <input type="hidden" name="wpil_disable_tawkto_widget" value="0" />
                                    <input type="checkbox" name="wpil_disable_tawkto_widget" <?=(!empty(get_option('wpil_disable_tawkto_widget', '')))?'checked':''?> value="1" />
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div style="margin: -250px 0 0 30px;">
                                            <?php 
                                            esc_html_e('Checking this will remove the support popup inside Link Whisper.', 'wpil');
                                            ?>
                                        </div>
                                    </div>
                                    <div style="clear:both;"></div>
                                </div>
                            </td>
                        </tr>
                        <?php if(class_exists('ACF')){ ?>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row' class="wpil-setting-text"><?php esc_html_e('Disable Linking for Advanced Custom Fields', 'wpil'); ?></td>
                            <td>
                                <input type="hidden" name="wpil_disable_acf" value="0" />
                                <div style="max-width: 80px;">
                                    <input type="checkbox" name="wpil_disable_acf" <?=get_option('wpil_disable_acf', false)==1?'checked':''?> value="1" />
                                    <div class="wpil_help" style="float: right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div style="margin-left: 30px; margin-top: -20px;">
                                            <p><?php esc_html_e('Checking this will tell Link Whisper to not process any data created by Advanced Custom Fields.', 'wpil'); ?></p>
                                            <p><?php esc_html_e('This will speed up the suggestion making and data saving, but will not update the ACF data.', 'wpil'); ?></p>
                                            <p><?php esc_html_e('If you don\'t see Advanced Custom Fields in your Installed Plugins list, it may be included as a component in a plugin or your theme.', 'wpil'); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php } ?>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row' class="wpil-setting-text"><?php esc_html_e('Content Formatting Level in Link Scan', 'wpil'); ?></td>
                            <td>
                                <input type="range" name="wpil_content_formatting_level" class="wpil-thick-range" min="0" max="2" value="<?php echo $formatting_level; ?>">
                                <div class="wpil_help" style="display: inline-block; float: none; margin: 0px 0 0 5px;">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div style="width: 340px;margin-top: -280px;">
                                        <?php esc_html_e('The setting controls how much content formatting Link Whisper does with content when searching it for links.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php esc_html_e('By default, Link Whisper fully formats the content with WordPress\'s "the_content" filter so it\'s closer to what a visitor would see.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php esc_html_e('But for some themes and page builders, this causes issues with links. And the answer is to reduce how much Link Whisper formats the content.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php esc_html_e('Setting this to "Only Shortcodes" will render the shortcodes in post content, but otherwise leave the content unchanged. Setting it to "No Formatting" will disable the formatting entirely.', 'wpil'); ?>
                                    </div>
                                    </div>
                                </div>
                                <div>
                                    <span style="<?php echo ($formatting_level === 0) ? '': 'display:none';?>" class="wpil-content-formatting-text wpil-format-0"><?php esc_html_e('No Formatting', 'wpil'); ?></span>
                                    <span style="<?php echo ($formatting_level === 1) ? '': 'display:none';?>" class="wpil-content-formatting-text wpil-format-1"><?php esc_html_e('Only Shortcodes', 'wpil'); ?></span>
                                    <span style="<?php echo ($formatting_level === 2) ? '': 'display:none';?>" class="wpil-content-formatting-text wpil-format-2"><?php esc_html_e('Full Formatting', 'wpil'); ?></span>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row' class="wpil-setting-text"><?php esc_html_e('Override Global Post During Link Scan', 'wpil'); ?></td>
                            <td>
                                <input type="hidden" name="wpil_override_global_post_during_scan" value="0" />
                                <input type="checkbox" name="wpil_override_global_post_during_scan" <?=!empty(get_option('wpil_override_global_post_during_scan', false))?'checked':''?> value="1" />
                                <div class="wpil_help" style="display: inline-block; float: none; margin: 0px 0 0 5px;">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div style="width: 340px; margin-top: -300px;">
                                        <?php esc_html_e('This setting temporarily overrides global WordPress $post variable with one that matches the post currently being scanned.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php esc_html_e('This is a compatibility measure for shortcodes that rely on the global $post variable to get content information, or to conditionally display content.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php esc_html_e('When the post scanning is completed, the $post variable is reset to its original value.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php esc_html_e('One of the main indicators that this needs to be activated is if after the Link Scan completes, many posts are reporting that they have the same links. Especially if they\'re from "related post" sections.', 'wpil'); ?>
                                    </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row'><?php esc_html_e('Optimize Link Scan For Speed', 'wpil'); ?></td>
                            <td>
                                <input type="hidden" name="wpil_optimize_link_scan_for_speed" value="0" />
                                <input type="checkbox" name="wpil_optimize_link_scan_for_speed" <?=!empty(get_option('wpil_optimize_link_scan_for_speed', false))?'checked':''?> value="1" />
                                <div class="wpil_help" style="display: inline-block; float: none; margin: 0px 0 0 5px;">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div style="width: 340px;">
                                        <?php esc_html_e('This setting tells Link Whisper to try to make the Link Scan run as fast as it can.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php esc_html_e('Doing this requires it to go from being obsessively careful in detecting all links to just being really careful. (Most sites shouldn\'t see a difference.)', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php esc_html_e('If you find that the Dashboard widget says zero posts have been scanned, or you find that the scan hasn\'t made progress in longer than 30 mins, please turn off the setting.', 'wpil'); ?>
                                    </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row'><?php esc_html_e('Run Link Stats From Link Table', 'wpil'); ?></td>
                            <td>
                                <input type="hidden" name="wpil_use_link_data_table" value="0" />
                                <input type="checkbox" name="wpil_use_link_data_table" <?=!empty(get_option('wpil_use_link_data_table', false))?'checked':''?> value="1" />
                                <div class="wpil_help" style="display: inline-block; float: none; margin: 0px 0 0 5px;">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div style="width: 340px;">
                                        <?php esc_html_e('This setting tells Link Whisper to not store link data in the site\'s "post meta" database table, and instead use a custom database table for link data.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php esc_html_e('Doing this will shorten the amount of time that it takes to complete a Link Scan, and should speed up ALL link related activities. Especially those in the Report pages.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php esc_html_e('After activating this setting, please test the Reports to make sure they are working correctly and then run a new Link Scan to clear out any stored data in the "post meta" database table.', 'wpil'); ?>
                                    </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php /*
                        <tr>
                            <td scope='row' class="wpil-setting-text"><?php _e('Count Related Post Links', 'wpil'); ?></td>
                            <td>
                                <div style="max-width:80px;">
                                    <input type="hidden" name="wpil_full_html_suggestions" value="0" />
                                    <input type="checkbox" name="wpil_full_html_suggestions" <?=get_option('wpil_full_html_suggestions')==1?'checked':''?> value="1" />
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div><?php esc_html_e('Turning this on will tell Link Whisper to display the raw HTML version of the link suggestions under the suggestion box.', 'wpil'); ?></div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row' class="wpil-setting-text"><?php esc_html_e('Manually Trigger Suggestions', 'wpil'); ?></td>
                            <td>
                                <input type="hidden" name="wpil_manually_trigger_suggestions" value="0" />
                                <input type="checkbox" name="wpil_manually_trigger_suggestions" <?=get_option('wpil_manually_trigger_suggestions')==1?'checked':''?> value="1" />
                                <div class="wpil_help" style="display: inline-block; float: none; margin: 0px 0 0 5px;">
                                    <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                    <div><?php esc_html_e('Checking this option will stop Link Whisper from automatically generating suggestions when you open the post edit or Inbound Suggestion pages. Instead, Link Whisper will wait until you click the "Get Suggestions" button in the suggestion panel.', 'wpil'); ?></div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row' class="wpil-setting-text"><?php esc_html_e('Disable Outbound Suggestions', 'wpil'); ?></td>
                            <td>
                                <input type="hidden" name="wpil_disable_outbound_suggestions" value="0" />
                                <input type="checkbox" name="wpil_disable_outbound_suggestions" <?=get_option('wpil_disable_outbound_suggestions')==1?'checked':''?> value="1" />
                                <div class="wpil_help" style="display: inline-block; float: none; margin: 0px 0 0 5px;">
                                    <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                    <div><?php esc_html_e('Checking this option will prevent Link Whisper from doing suggestion scans inside post edit screens.', 'wpil'); ?></div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row' class="wpil-setting-text"><?php esc_html_e('Make Suggestion Filtering Persistent', 'wpil'); ?></td>
                            <td>
                                <input type="hidden" name="wpil_make_suggestion_filtering_persistent" value="0" />
                                <input type="checkbox" name="wpil_make_suggestion_filtering_persistent" <?=get_option('wpil_make_suggestion_filtering_persistent')==1?'checked':''?> value="1" />
                                <div class="wpil_help" style="display: inline-block; float: none; margin: 0px 0 0 5px;">
                                    <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                    <div>
                                        <?php esc_html_e('Checking this option will tell Link Whisper to make the Suggestion Filtering Options persistent between page loads.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php esc_html_e('So if, for example, you set the suggestions to be limited to posts in the same categories as the current post. Link Whisper will remember that setting and will use it in future suggestion runs.', 'wpil'); ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row' class="wpil-setting-text"><?php esc_html_e('Limit Max Number of Posts to Search for Suggestions', 'wpil'); ?></td>
                            <td>
                                <input type="number" name="wpil_max_suggestion_post_count" value="<?=get_option('wpil_max_suggestion_post_count', 0)?>" step="1" min="0" />
                                <div class="wpil_help" style="display: inline-block; float: none; margin: 0px 0 0 5px;">
                                    <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                    <div>
                                        <?php esc_html_e('This setting tells Link Whisper the max number of posts that it should search through when generating suggestions.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php esc_html_e('Some sites have a huge number of posts, and trying to search through all of them for suggestions can take a very long time. Limiting the number of posts to search can help return suggestions in a timely manner.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php esc_html_e('When set, Link Whisper will randomly select the posts it will search. Setting it to "0" means there is no limit to the number of posts to be searched.', 'wpil'); ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row' class="wpil-setting-text"><?php esc_html_e('Maximum Suggested Anchor Length', 'wpil'); ?></td>
                            <td>
                                <select name="wpil_suggestion_anchor_max_size" style="float:left; max-width:100px">
                                    <option value="1" <?=1===(int)$max_suggestion_length ? 'selected' : '' ?>><?php esc_html_e('1 Word', 'wpil'); ?></option>
                                    <?php for($i = 2; $i <= 15; $i++) : ?>
                                        <option value="<?=$i?>" <?=$i===(int)$max_suggestion_length ? 'selected' : '' ?>><?php echo sprintf(__('%d Words', 'wpil'), $i);?></option>
                                    <?php endfor; ?>
                                </select>
                                <div class="wpil_help" style="display: inline-block; float: none; margin: 0px 0 0 5px;">
                                    <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                    <div style="width: 260px;">
                                        <?php esc_html_e('This option allows you set the maximum number of words that a suggested anchor can contain.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php esc_html_e('If you are experiencing suggestions that are too long to be relevent, decreasing the maximum anchor length may help improve suggestions.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php esc_html_e('The default max length for an anchor is 10 words.', 'wpil'); ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row' class="wpil-setting-text"><?php esc_html_e('Minimum Suggested Anchor Length', 'wpil'); ?></td>
                            <td>
                                <select name="wpil_suggestion_anchor_min_size" style="float:left; max-width:100px">
                                    <option value="1" <?=1===(int)$min_suggestion_length ? 'selected' : '' ?>><?php esc_html_e('1 Word', 'wpil'); ?></option>
                                    <?php for($i = 2; $i <= 15; $i++) : ?>
                                        <option value="<?=$i?>" <?=$i===(int)$min_suggestion_length ? 'selected' : '' ?>><?php echo sprintf(__('%d Words', 'wpil'), $i);?></option>
                                    <?php endfor; ?>
                                </select>
                                <div class="wpil_help" style="display: inline-block; float: none; margin: 0px 0 0 5px;">
                                    <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                    <div style="width: 260px;">
                                        <?php esc_html_e('This option allows you set the minimum number of words that a suggested anchor must contain.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php esc_html_e('If you are experiencing suggestions that are too short to be relevent, increasing the minimum anchor may help improve suggestions.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php esc_html_e('If this setting is set to "1 Word", the setting filters the suggestions so exact matches of single keywords will be suggested, and suggestions based on all other criteria will required to be at least 2 words long.', 'wpil'); ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row' class="wpil-setting-text"><?php esc_html_e('Apply Word Limits to Exact Keyword Matches', 'wpil'); ?></td>
                            <td>
                                <input type="hidden" name="wpil_force_keyword_exact_matches_word_limit" value="0" />
                                <input type="checkbox" name="wpil_force_keyword_exact_matches_word_limit" <?=Wpil_Settings::get_use_anchor_limit_tk_matches()==1?'checked':''?> value="1" />
                                <div class="wpil_help" style="display: inline-block; float: none; margin: 0px 0 0 5px;">
                                    <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                    <div>
                                        <?php esc_html_e('This setting tells Link Whisper use the Anchor Word Limits on suggestions that contain Target Keywords.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php esc_html_e('By default, you want this on so that all suggestions are within the desired length range.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php esc_html_e('Turning this off will allow Link Whisper to ignore the length settings if the suggestion has a Target Keyword in it. This can be useful if you have very short Target Keywords that you want exact match links for. (EX: brand names like "Coca Cola" or very specific services like "roofing")', 'wpil'); ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php*/
                        if(current_user_can('activate_plugins')){
                        ?>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row' class="wpil-setting-text"><?php esc_html_e('Connect to Google Search Console', 'wpil'); ?></td>
                            <td>
                                <?php
                                $authorized = get_option('wpil_gsc_app_authorized', false);
                                $has_custom = !empty(get_option('wpil_gsc_custom_config', false)) ? true : false;
                                $auth_message = (!$has_custom) ? __('Authorize Link Whisper', 'wpil'): __('Authorize Your App', 'wpil');
                                if(empty($authenticated) || empty($authorized)){ ?>
                                    <div class="wpil_gsc_app_inputs">
                                        <input style="width: 100%;max-width: 400px;margin: 0 0 10px 0;" id="wpil_gsc_access_code" class="wpil_gsc_get_authorize wpil-non-license-key-field" type="text" name="wpil_gsc_access_code"/>
                                        <label for="wpil_gsc_access_code" class="wpil_gsc_get_authorize"><a class="wpil_gsc_enter_app_creds wpil_gsc_button button-primary"><?php esc_html_e('Authorize', 'wpil'); ?></a></label>
                                        <a style="margin-top:5px;" class="wpil-get-gsc-access-token button-primary" href="<?php echo Wpil_Settings::getGSCAuthUrl(); ?>"><?php echo $auth_message; ?></a>
                                        <?php /*
                                        <a <?php echo ($has_custom) ? 'style="display:none"': ''; ?> class="wpil_gsc_switch_app wpil_gsc_button enter-custom button-primary button-purple"><?php esc_html_e('Connect with Custom App', 'wpil'); ?></a>
                                        <a <?php echo ($has_custom) ? '': 'style="display:none"'; ?> class="wpil_gsc_clear_app_creds button-primary button-purple" data-nonce="<?php echo wp_create_nonce('clear-gsc-creds'); ?>"><?php esc_html_e('Clear Custom App Credentials', 'wpil'); ?></a>
                                        */ ?>
                                    </div>
                                    <?php /*
                                    <div style="display:none;" class="wpil_gsc_custom_app_inputs">
                                        <p><i><?php esc_html_e('To create a Google app to connect with, please follow this guide. TODO: Write article', 'wpil'); ?></i></p>
                                        <div>
                                            <input style="width: 100%;max-width: 400px;margin: 0 0 10px 0;" id="wpil_gsc_custom_app_name" class="connect-custom-app" type="text" name="wpil_gsc_custom_app_name"/>
                                            <label for="wpil_gsc_custom_app_name"><?php esc_html_e('App Name', 'wpil'); ?></label>
                                        </div>
                                        <div>
                                            <input style="width: 100%;max-width: 400px;margin: 0 0 10px 0;" id="wpil_gsc_custom_client_id" class="connect-custom-app" type="text" name="wpil_gsc_custom_client_id"/>
                                            <label for="wpil_gsc_custom_client_id"><?php esc_html_e('Client Id', 'wpil'); ?></label>
                                        </div>
                                        <div>
                                            <input style="width: 100%;max-width: 400px;margin: 0 0 10px 0;" id="wpil_gsc_custom_client_secret" class="connect-custom-app" type="text" name="wpil_gsc_custom_client_secret"/>
                                            <label for="wpil_gsc_custom_client_secret"><?php esc_html_e('Client Secret', 'wpil'); ?></label>
                                        </div>
                                        <a style="margin: 0 0 10px 0;" class="wpil_gsc_enter_app_creds wpil_gsc_button button-primary"><?php esc_html_e('Save App Credentials', 'wpil'); ?></a>
                                        <br />
                                        <a class="wpil_gsc_switch_app wpil_gsc_button enter-standard button-primary button-purple"><?php esc_html_e('Connect with Link Whisper App', 'wpil'); ?></a>
                                    </div>
                                    */ ?>
                                <?php }else{ ?>
                                    <a class="wpil-gsc-deactivate-app button-primary"  data-nonce="<?php echo wp_create_nonce('disconnect-gsc'); ?>"><?php esc_html_e('Deactivate', 'wpil'); ?></a>
                                <?php } ?>
                            </td>
                        </tr>
                            <?php if(!empty($authenticated)){ ?>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row' class="wpil-setting-text"><?php esc_html_e('Currently Selected GSC Profile', 'wpil'); ?></td>
                            <td>
                                <select multiple name="wpil_manually_select_gsc_profile[]" class="wpil-setting-multiselect" style="float:left; min-width:400px">
                                <?php foreach(Wpil_SearchConsole::get_profiles() as $key => $profile){ ?>
                                    <option value="<?=esc_attr($key)?>" <?=(in_array($profile, $gsc_profiles, true)) ? 'selected="selected"': '';?>><?=esc_html($profile)?></option>
                                <?php } ?>
                                </select>
                                <div class="wpil_help">
                                    <i class="dashicons dashicons-editor-help" style="margin-top: 4px;"></i>
                                    <div>
                                        <?php esc_html_e('This is the manual GSC profile selector. It lists the currently selected GSC profile and allows you to pick a different one if Link Whisper\'s automatic selector wasn\'t able to find the right one.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php echo sprintf(__('Usually, the profile that matches your site\'s current URL or looks like "sc-domain:%s" is the correct one.', 'wpil'), wp_parse_url(get_home_url(), PHP_URL_HOST)); ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                            <?php } ?>
                            <?php if($authorized){ ?>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row' class="wpil-setting-text"><?php esc_html_e('Disable Automatic Search Console Updates', 'wpil'); ?></td>
                            <td>
                                <input type="hidden" name="wpil_disable_search_update" value="0" />
                                <input type="checkbox" name="wpil_disable_search_update" <?=get_option('wpil_disable_search_update', false)==1?'checked':''?> value="1" />
                                <div class="wpil_help" style="display: inline-block; float: none; margin: 0px 0 0 5px;">
                                    <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                    <div><?php esc_html_e('Link Whisper automatically scans for GSC updates via WordPress Cron. Turning this off will stop Link Whisper from performing the scan.', 'wpil'); ?></div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row' class="wpil-setting-text"><?php esc_html_e('Auto Select Top GSC Keywords', 'wpil'); ?></td>
                            <td>
                                <div style="max-width:80px;">
                                    <input type="hidden" name="wpil_autotag_gsc_keywords" value="0" />
                                    <input type="checkbox" name="wpil_autotag_gsc_keywords" <?=Wpil_Settings::get_if_autotag_gsc_keywords()==1?'checked':''?> value="1" />
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div>
                                            <?php _e('Turning this on will tell Link Whisper to scan and process links in related post areas that are separate from the post content.', 'wpil'); ?>
                                            <br>
                                            <br>
                                            <?php _e('Currently supports links generated by YARPP.', 'wpil'); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php } ?>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row' class="wpil-setting-text"><?php _e('Monitor Link Changes in Gutenberg Reusable Blocks', 'wpil'); ?></td>
                            <td>
                                <input type="hidden" name="wpil_update_reusable_block_links" value="0" />
                                <input type="checkbox" name="wpil_update_reusable_block_links" <?=!empty(get_option('wpil_update_reusable_block_links', false))?'checked':''?> value="1" />
                                <div class="wpil_help" style="display: inline-block; float: none; margin: 0px 0 0 5px;">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div>
                                        <?php _e('Checking this option will tell Link Whisper to monitor changes to Gutenberg reusable blocks and update the link stats of any posts that use the modified blocks.', 'wpil'); ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row' class="wpil-setting-text"><?php esc_html_e('Use "Ugly" Permalinks In Reports', 'wpil'); ?></td>
                            <td>
                                <input type="hidden" name="wpil_use_ugly_permalinks" value="0" />
                                <input type="checkbox" name="wpil_use_ugly_permalinks" <?=!empty(get_option('wpil_use_ugly_permalinks', false))?'checked':''?> value="1" />
                                <div class="wpil_help" style="display: inline-block; float: none; margin: 0px 0 0 5px;">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div style="width: 300px;">
                                        <?php esc_html_e('Checking this will tell Link Whisper to use WordPress\' "Ugly Permalinks" for the "View" links in the Link Whisper Reports.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php esc_html_e('Using the "Ugly" permalinks can save a surprising amount of time when loading the reports because we don\'t have to process all the rules required to calculate the correct URL for each post.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        <?php esc_html_e('One downside is that the Link Report\'s "Hidden by Redirect" icons may not be able to tell that the post is hidden, so the icons may fail to display on redirected posts.', 'wpil'); ?>
                                        <br />
                                        <br />
                                        (<?php esc_html_e('This won\'t affect the inserted links or Suggestions, and it also won\'t change the links on the site itself. The "Ugly" permalinks will only be used for the Link Whisper "View" buttons in the Reports.', 'wpil'); ?>)
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-content-ignoring-settings wpil-setting-row">
                            <td scope='row' class="wpil-setting-text"><?php _e('Shortcodes to Ignore by Name.', 'wpil'); ?></td>
                            <td>
                                <textarea name='wpil_ignore_shortcodes_by_name' id='wpil_ignore_shortcodes_by_name' style="width: 400px;float:left;" class='regular-text' rows=10><?php echo esc_textarea(get_option('wpil_ignore_shortcodes_by_name', '')); ?></textarea>
                                <div class="wpil_help">
                                    <i class="dashicons dashicons-info"></i>    
                                    <div style="margin: 0px 0px 0px -500px; width: 500px; overflow: auto; max-height: 200px;">
                                        <?php 
                                        echo '<h3 style="color:#fff; margin-top: 0px;">';
                                        _e('The known shortcode names are:', 'wpil');
                                        echo '</h3>';
                                        echo '<thing style="display:flex; flex-wrap: wrap;">'; // not div since that gets hidden in wpil_helps
                                        foreach($shortcode_tags as $tag_name => $dat){
                                            echo '<span style="padding: 0 10px 0 0;">' . $tag_name . '</span>';
                                        }
                                        echo '</thing>';
                                        echo '<br />';
                                        echo '<br />';
                                        echo '<span style="color:#fff;">';
                                        echo '(' . __('There may be other shortcodes active, but this is what we could find.', 'wpil') . ')';
                                        echo '</span>';
                                        ?>
                                    </div>
                                </div>
                                <div class="wpil_help">
                                    <i class="dashicons dashicons-editor-help"></i>
                                    <div style="margin: -160px 0px 0px 30px; width: 300px;">
                                        <?php 
                                        _e('Link Whisper will ignore any shortcodes listed in this field. It won\'t extract links from the listed shortcodes, or create links in any text content of the shortcode.', 'wpil');
                                        echo '<br /><br />';
                                        _e('To ignore a shortcode, enter it\'s name (without square brackets) in this field on it\'s own line.', 'wpil');
                                        echo '<br /><br />';
                                        _e('So for example, to ignore the WordPress [caption][/caption] shortcode, enter "caption" (without quotes) on it\'s own line in the field', 'wpil');
                                        echo '<br /><br />';
                                        _e('After entering a shortcode, you may want to run a link scan to refresh any stored link data based on shortcodes.', 'wpil');
                                        echo '<br /><br />';
                                        ?>
                                    </div>
                                </div>
                                <div style="clear:both;"></div>
                            </td>
                        </tr>
                        <?php }else{ ?>
                        <?php } ?>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row' class="wpil-setting-text"><?php esc_html_e('Delete Click Data Older Than', 'wpil'); ?></td>
                            <td>
                                <div style="display: flex;">
                                    <select name="wpil_delete_old_click_data" style="float:left;">
                                        <?php $day_count = get_option('wpil_delete_old_click_data', '0'); ?>
                                        <option value="0" <?php selected('0', $day_count) ?>><?php esc_html_e('Never Delete'); ?></option>
                                        <option value="1" <?php selected('1', $day_count) ?>><?php esc_html_e('1 Day'); ?></option>
                                        <option value="3" <?php selected('3', $day_count) ?>><?php esc_html_e('3 Days'); ?></option>
                                        <option value="7" <?php selected('7', $day_count) ?>><?php esc_html_e('7 Days'); ?></option>
                                        <option value="14" <?php selected('14', $day_count) ?>><?php esc_html_e('14 Days'); ?></option>
                                        <option value="30" <?php selected('30', $day_count) ?>><?php esc_html_e('30 Days'); ?></option>
                                        <option value="180" <?php selected('180', $day_count) ?>><?php esc_html_e('180 Days'); ?></option>
                                        <option value="365" <?php selected('365', $day_count) ?>><?php esc_html_e('1 Year'); ?></option>
                                    </select>
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div style="margin: -50px 0 0 30px;">
                                            <?php esc_html_e("Link Whisper will delete tracked clicks that are older than this setting.", 'wpil'); ?>
                                            <br />
                                            <br />
                                            <?php esc_html_e("By default, Link Whisper doesn't delete tracked click data.", 'wpil'); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row' class="wpil-setting-text"><?php esc_html_e('Disable Click Tracking', 'wpil'); ?></td>
                            <td>
                                <div style="max-width:80px;">
                                    <input type="hidden" name="wpil_disable_click_tracking" value="0" />
                                    <input type="checkbox" name="wpil_disable_click_tracking" <?=get_option('wpil_disable_click_tracking', false)==1?'checked':''?> value="1" />
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div style="margin: -180px 0 0 30px;">
                                            <?php esc_html_e("Activating this will disable the Click Tracking and will remove the Click Report from the Dashboard", 'wpil'); ?>
                                            <br>
                                            <br>
                                            <?php esc_html_e("The Click Tracking uses the Link Whisper Frontend script to track visitor clicks. So disabling this and having the \"Use JS to force opening in new tabs\" off will remove the script.", 'wpil'); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row' class="wpil-setting-text"><?php esc_html_e('Don\'t Collect User-Identifying Information with Click Tracking', 'wpil'); ?></td>
                            <td>
                                <div style="max-width:80px;">
                                    <input type="hidden" name="wpil_disable_click_tracking_info_gathering" value="0" />
                                    <input type="checkbox" name="wpil_disable_click_tracking_info_gathering" <?=$disable_ip_tracking==1?'checked':''?> value="1" />
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div style="margin: -230px 0 0 30px;">
                                            <?php esc_html_e("Activating this will set the Click Tracking to not collect information that could be used to identify a user", 'wpil'); ?>
                                            <br>
                                            <br>
                                            <?php esc_html_e("By default when a user clicks a link, Link Whisper collects the IP address of the visitor.", 'wpil'); ?>
                                            <br>
                                            <br>
                                            <?php esc_html_e("If the visitor has an account on the site, then Link Whisper collects their user id too.", 'wpil'); ?>
                                            <br>
                                            <br>
                                            <?php esc_html_e("With collection disabled, this data will not be saved.", 'wpil'); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row' class="wpil-setting-text"><?php esc_html_e('Track Link Clicks on all Elements', 'wpil'); ?></td>
                            <td>
                                <div style="max-width:80px;">
                                    <input type="hidden" name="wpil_track_all_element_clicks" value="0" />
                                    <input type="checkbox" name="wpil_track_all_element_clicks" <?=get_option('wpil_track_all_element_clicks', 0)==1?'checked':''?> value="1" />
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div style="margin: -280px 0 0 30px; width: 270px;">
                                            <?php esc_html_e("Activating this will set the Click Tracking to track link clicks on all parts of a page.", 'wpil'); ?>
                                            <br>
                                            <br>
                                            <?php esc_html_e("By default, only clicks in the post content areas are tracked so you can easily see how your in-content links are performing.", 'wpil'); ?>
                                            <br>
                                            <br>
                                            <?php esc_html_e("But when this setting is active, Link Whisper will track clicks in your page header, footer, sidebars & menus as well as widget areas.", 'wpil'); ?>
                                            <br>
                                            <br>
                                            <?php esc_html_e("To help identify where in the page the link was clicked, the Detailed Click Report pages will show a 'location' stat for each click.", 'wpil'); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php if(Wpil_ClickTracker::check_for_stored_visitor_data()){ ?>
                        <tr class="wpil-advanced-settings wpil-setting-row <?php echo (empty($disable_ip_tracking)) ? 'hide-setting': '';?>">
                            <td scope='row' class="wpil-setting-text"><?php esc_html_e('Delete all stored visitor data', 'wpil'); ?></td>
                            <td>
                                <div style="max-width:80px;">
                                    <input type="hidden" name="wpil_delete_stored_visitor_data" value="0" />
                                    <input type="checkbox" name="wpil_delete_stored_visitor_data" value="1" />
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div style="margin: -80px 0 0 30px;">
                                            <?php esc_html_e("Activating this will tell Link Whisper to delete all visitor data that it has stored.", 'wpil'); ?>
                                            <br>
                                            <br>
                                            <?php esc_html_e("Currently, the only visitor data stored is used in the Click Report.", 'wpil'); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php } ?>
                        <tr id="wpil-send-email-notice" class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row' class="wpil-setting-name-row"><?php esc_html_e('Send Email Notifications', 'wpil'); ?></td>
                            <td class="wpil-setting-input-row">
                                <div class="wpil-setting-input-container" style="max-width:80px;">
                                    <input type="hidden" name="wpil_email_notifications_enabled" value="0" />
                                    <input type="checkbox" name="wpil_email_notifications_enabled" <?php echo !empty(get_option('wpil_email_notifications_enabled', 1)) ? 'checked': ''; ?> value="1" />
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div style="margin: -100px 0 0 30px;">
                                            <?php esc_html_e("Link Whisper periodically sends you (the admin) emails regarding your site's link health and updates about things that may require your attention.", 'wpil'); ?>
                                            <br>
                                            <br>
                                            <?php esc_html_e("Turning this setting off will disable the email notifications.", 'wpil'); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row' class="wpil-setting-text"><?php esc_html_e('Delete all Link Whisper Data', 'wpil'); ?></td>
                            <td>
                                <div style="max-width:80px;">
                                    <input type="hidden" name="wpil_delete_all_data" value="0" />
                                    <input type="checkbox" class="danger-zone" name="wpil_delete_all_data" <?=get_option('wpil_delete_all_data', false)==1?'checked':''?> value="1" />
                                    <input type="hidden" class="wpil-delete-all-data-message" value="<?php echo sprintf(__('Activating this will tell Link Whisper to delete ALL link Whisper related data when the plugin is deleted. %s This will remove all settings and stored data. Links inserted into content by Link Whisper will still exist. %s Please only activate this option if you\'re sure you want to delete all data.', 'wpil'), '&lt;br&gt;&lt;br&gt;', '&lt;br&gt;&lt;br&gt;'); ?>">
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div style="margin: -260px 0 0 30px;">
                                            <?php esc_html_e("Activating this will tell Link Whisper to delete ALL link Whisper related data when the plugin is deleted.", 'wpil'); ?>
                                            <br>
                                            <br>
                                            <?php esc_html_e("This includes any Settings, Autolinking Rules, URL Changing Rules, and Report Data. This will not delete any links that have been created.", 'wpil'); ?>
                                            <br>
                                            <br>
                                            <?php esc_html_e("Please only activate this option if you're sure you want to delete ALL link Whisper data.", 'wpil'); ?>
                                            <br>
                                            <br>
                                            <?php esc_html_e("This system collects information about how Link Whisper is used, anonymizes it, and sends a digest to us so we can tell what features are popular and warrant further development.", 'wpil'); ?>
                                            <br>
                                            <br>
                                            <?php esc_html_e("All data is completely non personally identifiable, and is only used to make Link Whisper better.", 'wpil'); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr id="wpil-enable-tours" class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row' class="wpil-setting-name-row"><?php esc_html_e('Enable Interactive Tours', 'wpil'); ?></td>
                            <td class="wpil-setting-input-row">
                                <div class="wpil-setting-input-container" style="max-width:80px;">
                                    <input type="hidden" name="wpil_enable_tours" value="0" />
                                    <input type="checkbox" name="wpil_enable_tours" <?=!empty(get_option('wpil_enable_tours', '1'))?'checked':''?> value="1" />
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div style="margin: -100px 0 0 30px;">
                                            <?php esc_html_e("Enable or disable interactive tours and onboarding guides throughout the Link Whisper interface.", 'wpil'); ?>
                                            <br>
                                            <br>
                                            <?php esc_html_e("When disabled, all tour widgets will be hidden. Your tour progress is preserved and will be restored if you re-enable tours.", 'wpil'); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr class="wpil-advanced-settings wpil-setting-row">
                            <td scope='row' class="wpil-setting-text">
                                <span class="settings-carrot">
                                    <?php esc_html_e('Debug Settings', 'wpil'); ?>
                                </span>
                            </td>
                            <td class="setting-control-container">
                                <div class="setting-control">
                                    <input type="hidden" name="wpil_2_debug_mode" value="0" />
                                    <input type='checkbox' name="wpil_2_debug_mode" <?=get_option('wpil_2_debug_mode')==1?'checked':''?> value="1" />
                                    <label><?php esc_html_e('Enable Debug Mode?', 'wpil'); ?></label>
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div style="margin: -220px 0 0 30px;">
                                            <p><?php esc_html_e('If you\'re having errors, or it seems that data is missing, activating Debug Mode may be useful in diagnosing the problem.', 'wpil'); ?></p>
                                            <br>
                                            <p><?php esc_html_e('Enabling Debug Mode will cause your site to display any errors or code problems it\'s expiriencing instead of hiding them from view.', 'wpil'); ?></p>
                                            <br>
                                            <p><?php esc_html_e('These error notices may be visible to your site\'s visitors, so it\'s recommended to only use this for limited periods of time.', 'wpil'); ?></p>
                                            <br>
                                            <p><?php esc_html_e('(If you are already debugging with WP_DEBUG, then there\'s no need to activate this.)', 'wpil'); ?></p>
                                        </div>
                                    </div>
                                    <br>
                                </div>
                                <div class="setting-control">
                                    <input type="hidden" name="wpil_option_update_reporting_data_on_save" value="0" />
                                    <input type='checkbox' name="wpil_option_update_reporting_data_on_save" <?=get_option('wpil_option_update_reporting_data_on_save')==1?'checked':''?> value="1" />
                                    <label><?php esc_html_e('Run a check for un-indexed posts on each post save?', 'wpil'); ?></label>
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div style="margin: -220px 0 0 30px;">
                                            <p><?php esc_html_e('Checking this will tell Link Whisper to look for any posts that haven\'t been indexed for the link reports every time a post is saved.', 'wpil'); ?></p>
                                            <br>
                                            <p><?php esc_html_e('In most cases this isn\'t necessary, but if you\'re finding that some of your posts aren\'t displaying in the reports screens, this may fix it.', 'wpil'); ?></p>
                                            <br>
                                            <p><?php esc_html_e('One word of caution: If you have many un-indexed posts on the site, this may cause memory / timeout errors.', 'wpil'); ?></p>
                                        </div>
                                    </div>
                                    <br>
                                </div>
                                <div class="setting-control">
                                    <input type="hidden" name="wpil_include_post_meta_in_support_export" value="0" />
                                    <input type='checkbox' name="wpil_include_post_meta_in_support_export" <?=get_option('wpil_include_post_meta_in_support_export')==1?'checked':''?> value="1" />
                                    <label><?php esc_html_e('Include post meta in support data export?', 'wpil'); ?></label>
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div style="margin: -220px 0 0 30px;">
                                            <p><?php esc_html_e('Checking this will tell Link Whisper to include additional post data in the data for support export.', 'wpil'); ?></p>
                                            <br>
                                            <p><?php esc_html_e('This isn\'t needed for most support cases. It\'s most commonly used for troubleshooting issues with page builders', 'wpil'); ?></p>
                                        </div>
                                    </div>
                                    <br>
                                </div>
                                <div class="setting-control">
                                    <input type="hidden" name="wpil_clear_error_checker_process" value="0" />
                                    <input type='checkbox' name="wpil_clear_error_checker_process" <?=get_option('wpil_clear_error_checker_process')==1?'checked':''?> value="1" />
                                    <label><?php esc_html_e('Cancel active Broken Link scans?', 'wpil'); ?></label>
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div style="margin: -220px 0 0 30px;">
                                            <p><?php esc_html_e('Checking this will tell Link Whisper to cancel any active Broken Link scans and allow you to access the Broken Links Report table.', 'wpil'); ?></p>
                                            <br>
                                            <p><?php esc_html_e('This can be helpful when the Broken Link scan gets stuck, but it may not solve the underlying issue.', 'wpil'); ?></p>
                                            <br>
                                            <p><?php esc_html_e('Please close any tabs that have an active Broken Link scan running before activating this option.', 'wpil'); ?></p>
                                        </div>
                                    </div>
                                    <br>
                                </div>
                                <div class="setting-control">
                                    <input type="hidden" name="wpil_optimize_option_table" value="0" />
                                    <input type="checkbox" name="wpil_optimize_option_table" <?=get_option('wpil_optimize_option_table', 0)==1?'checked':''?> value="1" />
                                    <label><?php esc_html_e('Attempt to Manage Option Table Overhead on Scans?', 'wpil'); ?></label>
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div style="margin: -280px 0 0 30px; width: 270px;">
                                            <?php esc_html_e("Activating this will tell Link Whisper to try reducing the amount of database overhead that's generated when running a scan.", 'wpil'); ?>
                                            <br>
                                            <br>
                                            <?php esc_html_e("This can be useful when running the scan creates so much overhead the database exceeds the normal data storage limits.", 'wpil'); ?>
                                            <br>
                                            <br>
                                            <?php esc_html_e("This is an advanced setting that may slow down the scan somewhat and could cause freezes on sites with exceptionally large option tables. The system only engages when the overhead exceeds 1 gigabyte.", 'wpil'); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="setting-control">
                                    <input type="hidden" name="wpil_force_database_update" value="0" />
                                    <input type='checkbox' name="wpil_force_database_update" value="1" />
                                    <label><?php echo sprintf(__('Re-run the database table %s routine?', 'wpil'), '<strong>update</strong>'); ?></label>
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div style="margin: -230px 0 0 30px;">
                                            <p><?php esc_html_e('Checking this will tell Link Whisper re-run the database table update process.', 'wpil'); ?></p>
                                            <br>
                                            <p><?php esc_html_e('This process is supposed to automatically run when the plugin is updated, but sometimes it gets interrupted.', 'wpil'); ?></p>
                                            <br>
                                            <p><?php esc_html_e('This can help when you have errors saying that certain columns do not exist in database tables.', 'wpil'); ?></p>
                                        </div>
                                    </div>
                                    <br>
                                </div>
                                <div class="setting-control">
                                    <input type="hidden" name="wpil_force_create_database_tables" value="0" />
                                    <input type='checkbox' name="wpil_force_create_database_tables" value="1" />
                                    <label><?php echo sprintf(__('Re-run the database table %s routine?', 'wpil'), '<strong>creation</strong>'); ?></label>
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div style="margin: -260px 0 0 30px;">
                                            <p><?php esc_html_e('Checking this will tell Link Whisper re-run the database table creation process.', 'wpil'); ?></p>
                                            <br>
                                            <p><?php esc_html_e('This process is supposed to automatically run when the plugin is updated, but sometimes it gets interrupted.', 'wpil'); ?></p>
                                            <br>
                                            <p><?php esc_html_e('This can help when you have errors saying that certain database tables do not exist.', 'wpil'); ?></p>
                                        </div>
                                    </div>
                                    <br>
                                </div>
                                <div class="setting-control">
                                    <input type="hidden" name="wpil_reset_table_display_counts" value="0" />
                                    <input type='checkbox' name="wpil_reset_table_display_counts" value="1" />
                                    <label><?php esc_html_e('Reset the items to display in the Reports to 20?', 'wpil'); ?></label>
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div style="margin: -260px 0 0 30px;">
                                            <p><?php esc_html_e('Checking this will tell Link Whisper reset the number if items to display in the Reports back to the default of 20 items.', 'wpil'); ?></p>
                                            <br>
                                            <p><?php esc_html_e('This is useful for cases where the Reports fail to load because there\'s too many results to display.', 'wpil'); ?></p>
                                        </div>
                                    </div>
                                    <br>
                                </div>
                                <div class="setting-control">
                                    <a class="button button-primary" href="<?php echo esc_url(admin_url("post.php?area=wpil_export_sitemap_support&nonce=" . wp_create_nonce(get_current_user_id() . 'wpil_export_sitemap_for_support')));?>"><?php esc_html_e('Export Sitemap Support Data', 'wpil'); ?></a>
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div style="margin: -260px 0 0 30px;">
                                            <p><?php esc_html_e('Clicking this button will have Link Whisper compile and export sitemap data that can be used to debug issues with the Visual Sitemaps.', 'wpil'); ?></p>
                                            <br>
                                            <p><?php esc_html_e('If you don\'t see a file download after a short wait, or you see an error screen, please try exporting with a different browser.', 'wpil'); ?></p>
                                        </div>
                                    </div>
                                    <br>
                                </div>
                                <div class="setting-control">
                                    <label><input type='text' name="wpil_upload_linkwhisper_ai_token" class="wpil-non-license-key-field" style="margin-right: 15px;" value="" /><?php esc_html_e('Manually upload AI access token?', 'wpil'); ?></label>
                                    <div class="wpil_help" style="float:right;">
                                        <i class="dashicons dashicons-editor-help" style="margin-top: 6px;"></i>
                                        <div style="margin: -260px 0 0 30px;">
                                            <p><?php esc_html_e('This field allows you to manually upload the access token that goes with your Link Whisper AI account.', 'wpil'); ?></p>
                                            <br>
                                            <p><?php esc_html_e('Normally, the access token is automatically sent to your site and stored. But sometimes security plugins block the delivery and the token needs to be manually uploaded.', 'wpil'); ?></p>
                                            <br>
                                            <p><?php esc_html_e('To use this, please enter the access token, (not the linkwhisper license key), and save the settings.', 'wpil'); ?></p>
                                        </div>
                                    </div>
                                    <br>
                                </div>
                            </td>
                        </tr>
                        <!-- /related posts -->
                        </tbody>
                    </table>
                    <p class='submit'>
                        <input type='submit' name='btnsave' id='btnsave' value="<?php echo esc_attr__('Save Settings', 'wpil'); ?>" class='button-primary' />
                    </p>
                </form>
            </div>
        </div>
    </div>
</div>