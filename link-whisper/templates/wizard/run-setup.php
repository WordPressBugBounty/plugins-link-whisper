<style>
    .toplevel_page_link_whisper_wizard #adminmenumain,
    .toplevel_page_link_whisper_wizard #wpadminbar{
        display: none;
    }

    .wpil-setup-wizard{
        background: #f0f0f1;
        height: calc(100% + 42px);
        width: 100%;
        display: flex;
        justify-content: center;
        /*text-align: center;*/

        /* temp, remove later */
        position: absolute;
        top: -42px;
        left: -182px;
        z-index: 99999;
        height: calc(100% + 42px);
        min-height: 100vh !important;
        width: calc(100vw);
        width: calc(100% + 182px);
        overflow-x: hidden !important;
    }
    #wpil-setup-wizard-heading-container{
        margin-bottom: 30px;
    }
    #wpil-setup-wizard-heading{
        font-size: 40px;
        font-weight: bold;
        margin-bottom: 20px;
    }
    .wpil-setup-wizard-small-text{
        font-size: 13px;
    }
    .wpil-setup-wizard-normal-text{
        font-size: 16px;
    }

    .wpil-wizard-exit-button{
        position: absolute; 
        right: 20px; 
        top: 20px;
    }

    .wpil-setup-wizard-radio-button-wrapper{
        display: inline-block;
    }
    .wpil-setup-wizard-radio-button-container{
        position: relative;
    }
    .wpil-setup-wizard-radio-button{
        padding: 20px 10px;
        display: inline-block;
        /*background: #33c8fe6e;*/
        font-size: 16px;
        font-weight: bold;
        font-family: sans-serif;
        width: 100%;
        margin: 10px 0;
        cursor: pointer;
        border: 3px solid #f0f0f1;
        min-width: 280px;
        user-select: none;
    }

    .wpil-setup-wizard-radio-button.checked,
    .wpil-setup-wizard-radio-button:hover{
        border: 3px solid #3582c4;
    }

    .wpil-setup-wizard-radio-button input[type="radio"]{
        position: absolute;
        left: 16px;
        top: 38px;
        height: 20px;
        width: 20px;
    }
    .wpil-setup-wizard-radio-button input[type="radio"]:checked::before{
        width: 14px;
        height: 14px;
        margin: calc(0.6px * 3.14) calc(0.6px * 3.14);
    }

    .wpil-setup-wizard .wpil-setup-wizard-main-button{
        font-family:'Barlow', sans-serif; 
        padding: 15px 40px !important; 
        font-size: 18px !important; 
        cursor:pointer;
        user-select: none;
    }

    .wpil-setup-wizard-main-button.button-disabled{
        cursor: initial;
    }

    .wpil-setup-wizard-message{
        display: none;
    }

    /*************************************** */
    .wpil-setup-wizard-loading{
        position: absolute;
        right: -40px;
        top: 0px;
    }

    #wpil-setup-wizard-progress{
        position: absolute;
        top: 20px;
        width: 100%;
        display: flex;
        justify-content: space-around;
        font-size: 16px;
        font-weight: bold;
    }

    #wpil-setup-wizard-progress-loading{
        display: none;
        position: absolute;
        top: 30px;
        left: 18%;
        width: 50%;
    }

    #wpil-setup-wizard-progress-loading-bar{
        height: 2px;
        background-color: #0096ff;
        width: 70%;
    }

    #wpil-setup-wizard-progress div{
        background-color: #f0f0f1;
    }

    #wpil-setup-wizard-progress .complete{
        color: #0096ff;
    }

    .wpil-setup-wizard-content{
        margin: 100px 0 0 0;
        background: #ffffff;
        border-radius: 20px;
        padding: 20px 40px 40px;
        /*box-shadow: 0 0 3px 3px rgb(134, 134, 134);*/
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        height: auto;
        text-align: left;
        position: absolute;
    }

    .wp-core-ui .button-primary{
        line-height: normal !important;
    }

    .wpil-wizard-page .wpil-setup-wizard-sub-heading{
        font-size: 20px !important;
    }

    .wpil-setup-wizard.wpil-wizard-page-hidden{
        display: none;
    }

    .wpil-setup-wizard.wizard-run-setup .wpil-setup-wizard-content{
        padding-left: 80px;
        padding-right: 80px;
    }

    .wpil-wizard-process-subtext{
        display: none;
    }

    .wpil-wizard-fun-fact-container{
        min-height: 130px;
    }

    .wpil-wizard-fun-fact{
        max-width: 520px;
        margin: 0 auto;
        font-size: 16px;
    }

    .wpil-wizard-fun-fact .wpil-setup-wizard-sub-heading{
        font-size: 24px !important;
    }

    .wpil-wizard-fun-fact span{
        font-size: 20px;
        line-height: 1.1;
    }

    .wpil-setup-wizard-loading-bars .syns_div{
        display: flex;
    }

    .wpil-setup-wizard-loading-bars > .wpil-setup-wizard-sub-heading{
        text-align: left;
        margin: 25px 0 -10px 0;
    }

    .wpil-setup-wizard-loading-bars .syns_div .wpil-setup-wizard-sub-heading{
        white-space: nowrap;
        margin-right: 10px;
        min-width: 200px;
    }

    .wpil-setup-wizard-content .progress_panel{
        background: #b6b6b6;
        min-width: 500px;
    }
    
    .wpil-setup-wizard-content .progress_panel .progress_count:after{
        background-image: none;
    }

    .wpil-help-overlay-control-container,
    .wpil-help-overlay-segment-container{
        display: none !important;
    }

    @media screen and (max-width: 1100px) {
        .wpil-setup-wizard #wpil-setup-wizard-progress{
            flex-direction: column;
            left: 10px;
        }

        .wpil-setup-wizard .wpil-setup-wizard-content{
            margin-top: 160px;
        }
    }

    @media screen and (max-width: 960px) {
        .wpil-setup-wizard{
            left: -60px;
        }
    }

    @media screen and (max-width: 782px) {
        .wpil-setup-wizard{
            top: -10px;
            left: -10px;
        }
    }
</style>
<script>
    var dashboardURL = "<?php echo admin_url('admin.php?page=link_whisper'); ?>";
</script>
<?php
$setup_title = array_rand(array_flip(array(
    esc_html__('Unpacking the Magic', 'wpil'), 
    esc_html__('Releasing the Beast', 'wpil'),
    esc_html__('Setting up the Party', 'wpil'),
    esc_html__('Deploying the Awesomeness', 'wpil'),
    esc_html__('Brewing the Tech Potion', 'wpil'),
    esc_html__('Creating the Wonder', 'wpil')
)));
?>
<div class="wpil-setup-wizard wrap wpil_styles wizard-run-setup wpil-wizard-page wpil-wizard-page-hidden">
    <input type="hidden" class="wpil-wizard-reset-report-nonce" name="reset_data_nonce" value="<?php echo wp_create_nonce(get_current_user_id() . 'wpil_reset_report_data'); ?>">
    <input type="hidden" class="wpil-wizard-reset-target-keyword-nonce" value="<?php echo wp_create_nonce(get_current_user_id() . 'wpil_target_keyword'); ?>">
    <input type="hidden" class="wpil-wizard-import-autolink-keywords-nonce" value="<?php echo wp_create_nonce(get_current_user_id() . 'wpil_autolink_keyword_import'); ?>">
    <input type="hidden" class="wpil-wizard-create-autolinks-nonce" value="<?php echo wp_create_nonce(get_current_user_id() . 'wpil_wizard_autolink_created_nonce'); ?>">
    <div id="wpil-setup-wizard-progress-loading"><div id="wpil-setup-wizard-progress-loading-bar" style="width: 90%"></div></div>
    <div id="wpil-setup-wizard-progress">
        <div class="complete"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Connect to Google Search Console', 'wpil'); ?></div>
        <div class="complete"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Connect to AI', 'wpil'); ?></div>
        <div class="complete"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Complete Installation', 'wpil'); ?></div>
    </div>
    <div class="wpil-setup-wizard-content">
        <div id="wpil-help-overlay"></div>
        <a href="<?php echo admin_url('admin.php?page=link_whisper&loading=1');?>" class="wpil-wizard-go-to-dashboard-button wpil-is-tooltipped" data-wpil-tooltip-theme="link-whisper-wizard-notice" data-wpil-show-after-delay="3" data-wpil-tooltip-interactive="1" data-wpil-tooltip-allowHTML="1" data-wpil-tooltip-placement="right"  data-wpil-tooltip-content="<?php echo esc_attr('<div>It looks like it might take Link Whisper a little while to process the site.</div><br><br><div>While it does that, you can check out the Dashboard!</div>'); ?>" target="_blank" style="position: absolute; right: 20px; top: 20px; font-weight: bold; font-size: 16px;">GO TO DASHBOARD</a>
        <div id="wpil-setup-wizard-heading-container">
            <img src="<?php echo WP_INTERNAL_LINKING_PLUGIN_URL . '/images/lw-icon.png' ?>" width="128px" height="128px">
            <h1 id="wpil-setup-wizard-heading"><?php echo $setup_title; ?></h1>
            <div class="wpil-wizard-fun-fact-container">
                <div class="wpil-wizard-fun-fact" data-wpil-wizard-fun-fact-id="1">
                    <p class="wpil-setup-wizard-sub-heading"><strong><?php esc_html_e('Did you know?', 'wpil'); ?></strong></p>
                    <span><?php esc_html_e('As many as 46% of all Google searches are local. Optimizing content for local businesses can be a potent traffic driver.', 'wpil'); ?></span>
                </div>
                <div class="wpil-wizard-fun-fact" data-wpil-wizard-fun-fact-id="2" style="display:none;">
                    <p class="wpil-setup-wizard-sub-heading"><strong><?php esc_html_e('Did you know?', 'wpil'); ?></strong></p>
                    <span><?php esc_html_e('Video content is 50 times more likely to drive organic search results than plain text.', 'wpil'); ?></span>
                </div>
                <div class="wpil-wizard-fun-fact" data-wpil-wizard-fun-fact-id="3" style="display:none;">
                    <p class="wpil-setup-wizard-sub-heading"><strong><?php esc_html_e('Did you know?', 'wpil'); ?></strong></p>
                    <span><?php esc_html_e('Google processes over 8.5 billion searches per day—that\'s nearly 100,000 searches every second!', 'wpil'); ?></span>
                </div>
                <div class="wpil-wizard-fun-fact" data-wpil-wizard-fun-fact-id="4" style="display:none;">
                    <p class="wpil-setup-wizard-sub-heading"><strong><?php esc_html_e('Did you know?', 'wpil'); ?></strong></p>
                    <span><?php esc_html_e('Link Whisper automatically scans your site for broken links and broken YouTube videos.', 'wpil'); ?></span>
                </div>
                <div class="wpil-wizard-fun-fact" data-wpil-wizard-fun-fact-id="5" style="display:none;">
                    <p class="wpil-setup-wizard-sub-heading"><strong><?php esc_html_e('Did you know?', 'wpil'); ?></strong></p>
                    <span><?php esc_html_e('Schema markup (structured data) is used by fewer than 40% of websites—but significantly boosts visibility in search results.', 'wpil'); ?></span>
                </div>
                <div class="wpil-wizard-fun-fact" data-wpil-wizard-fun-fact-id="6" style="display:none;">
                    <p class="wpil-setup-wizard-sub-heading"><strong><?php esc_html_e('Did you know?', 'wpil'); ?></strong></p>
                    <span><?php esc_html_e('Local SEO leads 72% of consumers who perform a local search to visit a store within 5 miles.', 'wpil'); ?></span>
                </div>
                <div class="wpil-wizard-fun-fact" data-wpil-wizard-fun-fact-id="7" style="display:none;">
                    <p class="wpil-setup-wizard-sub-heading"><strong><?php esc_html_e('Did you know?', 'wpil'); ?></strong></p>
                    <span><?php esc_html_e('Over-optimized anchor text from external sites can harm rankings; Google prefers natural, varied anchor text.', 'wpil'); ?></span>
                </div>
                <div class="wpil-wizard-fun-fact" data-wpil-wizard-fun-fact-id="8" style="display:none;">
                    <p class="wpil-setup-wizard-sub-heading"><strong><?php esc_html_e('Did you know?', 'wpil'); ?></strong></p>
                    <span><?php esc_html_e('The average voice search query is 29 words long, significantly longer than typed queries.', 'wpil'); ?></span>
                </div>
                <div class="wpil-wizard-fun-fact" data-wpil-wizard-fun-fact-id="9" style="display:none;">
                    <p class="wpil-setup-wizard-sub-heading"><strong><?php esc_html_e('Did you know?', 'wpil'); ?></strong></p>
                    <span><?php esc_html_e('Link Whisper\'s "Target Keywords" directly tell the plugin what keywords you want the post to rank for in searches so it can tailor the suggestions accordingly.', 'wpil'); ?></span>
                </div>
                <div class="wpil-wizard-fun-fact" data-wpil-wizard-fun-fact-id="10" style="display:none;">
                    <p class="wpil-setup-wizard-sub-heading"><strong><?php esc_html_e('Did you know?', 'wpil'); ?></strong></p>
                    <span><?php esc_html_e('Google updates its search algorithm 500-600 times per year—that\'s 1-2 times daily!', 'wpil'); ?></span>
                </div>
                <div class="wpil-wizard-fun-fact" data-wpil-wizard-fun-fact-id="11" style="display:none;">
                    <p class="wpil-setup-wizard-sub-heading"><strong><?php esc_html_e('Did you know?', 'wpil'); ?></strong></p>
                    <span><?php esc_html_e('The first link on a page pointing to another internal page generally carries the most SEO value, while additional links to the same destination page add minimal benefit.', 'wpil'); ?></span>
                </div>
                <div class="wpil-wizard-fun-fact" data-wpil-wizard-fun-fact-id="12" style="display:none;">
                    <p class="wpil-setup-wizard-sub-heading"><strong><?php esc_html_e('Did you know?', 'wpil'); ?></strong></p>
                    <span><?php esc_html_e('Guest blogging remains one of the most effective methods of acquiring high-quality backlinks, provided it\'s not spammy.', 'wpil'); ?></span>
                </div>
                <div class="wpil-wizard-fun-fact" data-wpil-wizard-fun-fact-id="13" style="display:none;">
                    <p class="wpil-setup-wizard-sub-heading"><strong><?php esc_html_e('Did you know?', 'wpil'); ?></strong></p>
                    <span><?php esc_html_e('Most posts have an average of 8-12 outbound internal links in their content.', 'wpil'); ?></span>
                </div>
                <div class="wpil-wizard-fun-fact" data-wpil-wizard-fun-fact-id="14" style="display:none;">
                    <p class="wpil-setup-wizard-sub-heading"><strong><?php esc_html_e('Did you know?', 'wpil'); ?></strong></p>
                    <span><?php esc_html_e('You can quickly delete links from your site by using the Delete Link options inside any Link Whisper link dropdown.', 'wpil'); ?></span>
                </div>
                <div class="wpil-wizard-fun-fact" data-wpil-wizard-fun-fact-id="15" style="display:none;">
                    <p class="wpil-setup-wizard-sub-heading"><strong><?php esc_html_e('Did you know?', 'wpil'); ?></strong></p>
                    <span><?php esc_html_e('Internal links don’t have the same risk of penalties as external links, allowing more freedom to strategically optimize your linking strategy.', 'wpil'); ?></span>
                </div>
                <div class="wpil-wizard-fun-fact" data-wpil-wizard-fun-fact-id="16" style="display:none;">
                    <p class="wpil-setup-wizard-sub-heading"><strong><?php esc_html_e('Did you know?', 'wpil'); ?></strong></p>
                    <span><?php esc_html_e('Over 66% of pages have zero backlinks, severely limiting their organic search visibility.', 'wpil'); ?></span>
                </div>
                <div class="wpil-wizard-fun-fact" data-wpil-wizard-fun-fact-id="17" style="display:none;">
                    <p class="wpil-setup-wizard-sub-heading"><strong><?php esc_html_e('Did you know?', 'wpil'); ?></strong></p>
                    <span><?php esc_html_e('Over 60% of all searches come from mobile devices. Mobile-friendly SEO is critical!', 'wpil'); ?></span>
                </div>
                <div class="wpil-wizard-fun-fact" data-wpil-wizard-fun-fact-id="18" style="display:none;">
                    <p class="wpil-setup-wizard-sub-heading"><strong><?php esc_html_e('Did you know?', 'wpil'); ?></strong></p>
                    <span><?php esc_html_e('Link Whisper can quickly create multiple links pointing to specific posts by using it\'s Inbound Linking Suggestions feature.', 'wpil'); ?></span>
                </div>
                <div class="wpil-wizard-fun-fact" data-wpil-wizard-fun-fact-id="19" style="display:none;">
                    <p class="wpil-setup-wizard-sub-heading"><strong><?php esc_html_e('Did you know?', 'wpil'); ?></strong></p>
                    <span><?php esc_html_e('SEO has been around since the early 1990s, even before Google existed.', 'wpil'); ?></span>
                </div>
                <div class="wpil-wizard-fun-fact" data-wpil-wizard-fun-fact-id="20" style="display:none;">
                    <p class="wpil-setup-wizard-sub-heading"><strong><?php esc_html_e('Did you know?', 'wpil'); ?></strong></p>
                    <span><?php esc_html_e('The concept of backlinks was inspired by academic citations, which Google’s founders adapted to rank websites.', 'wpil'); ?></span>
                </div>
                <div class="wpil-wizard-fun-fact" data-wpil-wizard-fun-fact-id="21" style="display:none;">
                    <p class="wpil-setup-wizard-sub-heading"><strong><?php esc_html_e('Did you know?', 'wpil'); ?></strong></p>
                    <span><?php esc_html_e('Links placed in the main content area of a page pass more SEO value than links placed in sidebars or footers.', 'wpil'); ?></span>
                </div>
                <div class="wpil-wizard-fun-fact" data-wpil-wizard-fun-fact-id="22" style="display:none;">
                    <p class="wpil-setup-wizard-sub-heading"><strong><?php esc_html_e('Did you know?', 'wpil'); ?></strong></p>
                    <span><?php esc_html_e('Broken link building (replacing dead links with live content) remains one of the most effective white-hat link-building methods.', 'wpil'); ?></span>
                </div>
                <div class="wpil-wizard-fun-fact" data-wpil-wizard-fun-fact-id="23" style="display:none;">
                    <p class="wpil-setup-wizard-sub-heading"><strong><?php esc_html_e('Did you know?', 'wpil'); ?></strong></p>
                    <span><?php esc_html_e('Half of all searches today contain four words or more—long-tail keywords are powerful!', 'wpil'); ?></span>
                </div>
                <div class="wpil-wizard-fun-fact" data-wpil-wizard-fun-fact-id="24" style="display:none;">
                    <p class="wpil-setup-wizard-sub-heading"><strong><?php esc_html_e('Did you know?', 'wpil'); ?></strong></p>
                    <span><?php esc_html_e('Link Whisper gives a quick summary of each post\'s links inside the "All Posts" area. It shows the number of Inbound, Outbound and External links, as well as any Broken Links each post has.', 'wpil'); ?></span>
                </div>
                <div class="wpil-wizard-fun-fact" data-wpil-wizard-fun-fact-id="25" style="display:none;">
                    <p class="wpil-setup-wizard-sub-heading"><strong><?php esc_html_e('Did you know?', 'wpil'); ?></strong></p>
                    <span><?php esc_html_e('53% of website traffic comes from organic search, significantly more than paid ads (15%).', 'wpil'); ?></span>
                </div>
                <div class="wpil-wizard-fun-fact" data-wpil-wizard-fun-fact-id="26" style="display:none;">
                    <p class="wpil-setup-wizard-sub-heading"><strong><?php esc_html_e('Did you know?', 'wpil'); ?></strong></p>
                    <span><?php esc_html_e('Content that earns backlinks organically usually does so through originality, data-driven insights, and comprehensive coverage.', 'wpil'); ?></span>
                </div>
                <div class="wpil-wizard-fun-fact" data-wpil-wizard-fun-fact-id="27" style="display:none;">
                    <p class="wpil-setup-wizard-sub-heading"><strong><?php esc_html_e('Did you know?', 'wpil'); ?></strong></p>
                    <span><?php esc_html_e('Google introduced the “nofollow” attribute in 2005 to combat spam links and clarify which links shouldn’t influence rankings.', 'wpil'); ?></span>
                </div>
                <div class="wpil-wizard-fun-fact" data-wpil-wizard-fun-fact-id="28" style="display:none;">
                    <p class="wpil-setup-wizard-sub-heading"><strong><?php esc_html_e('Did you know?', 'wpil'); ?></strong></p>
                    <span><?php esc_html_e('Pages with outbound links to authoritative sources may rank slightly higher, as these links provide value and context for users.', 'wpil'); ?></span>
                </div>
                <div class="wpil-wizard-fun-fact" data-wpil-wizard-fun-fact-id="29" style="display:none;">
                    <p class="wpil-setup-wizard-sub-heading"><strong><?php esc_html_e('Did you know?', 'wpil'); ?></strong></p>
                    <span><?php esc_html_e('Link Whisper is able to create links between two of your sites using it\'s Site Interlinking feature.', 'wpil'); ?></span>
                </div>
                <div class="wpil-wizard-fun-fact" data-wpil-wizard-fun-fact-id="30" style="display:none;">
                    <p class="wpil-setup-wizard-sub-heading"><strong><?php esc_html_e('Did you know?', 'wpil'); ?></strong></p>
                    <span><?php esc_html_e('Contextual backlinks—those embedded naturally within content—have significantly greater SEO value than isolated or unrelated links.', 'wpil'); ?></span>
                </div>
                <div class="wpil-wizard-fun-fact" data-wpil-wizard-fun-fact-id="31" style="display:none;">
                    <p class="wpil-setup-wizard-sub-heading"><strong><?php esc_html_e('Did you know?', 'wpil'); ?></strong></p>
                    <span><?php esc_html_e('Effective internal linking reduces bounce rate and keeps users on-site longer, positively impacting SEO indirectly.', 'wpil'); ?></span>
                </div>
            </div>
        </div>
        <div class="wpil-setup-wizard-loading-bars">
            <p class="wpil-setup-wizard-sub-heading"><strong><?php esc_html_e('Scanning Posts:', 'wpil'); ?></strong></p>
            <div class="syns_div wpil_report_need_prepare wpil-wizard-post-progress-loader">
                <!--<p class="wpil-setup-wizard-sub-heading"><strong><?php esc_html_e('Scanning Posts:', 'wpil'); ?></strong></p>-->
                <div class="progress_panel">
                    <div class="progress_count"><span class="wpil-loading-status"><?php esc_html_e('Beginning Scan...', 'wpil'); ?></span></div>
                </div>
            </div>
            <p class="wpil-setup-wizard-sub-heading"><strong><?php esc_html_e('Searching for Keywords:', 'wpil'); ?></strong></p>
            <div class="syns_div wpil_report_need_prepare wpil-wizard-target-keyword-progress-loader">
                <!--<p class="wpil-setup-wizard-sub-heading"><strong><?php esc_html_e('Searching for Keywords:', 'wpil'); ?></strong></p>-->
                <div class="progress_panel">
                    <div class="progress_count"><span class="wpil-loading-status"><?php esc_html_e('Beginning Scan...', 'wpil'); ?></span></div>
                </div>
            </div>
            <p class="wpil-setup-wizard-sub-heading wpil-wizard-autolink-progress-header" style="display: none"><strong><?php esc_html_e('Creating Autolink Rules:', 'wpil'); ?></strong></p>
            <div class="syns_div wpil_report_need_prepare wpil-wizard-autolink-progress-loader" style="display: none">
                <!--<p class="wpil-setup-wizard-sub-heading"><strong><?php esc_html_e('Creating Autolink Rules:', 'wpil'); ?></strong></p>-->
                <div class="progress_panel">
                    <div class="progress_count"><span class="wpil-loading-status"><?php esc_html_e('Preparing...', 'wpil'); ?></span></div>
                </div>
            </div>
            <p class="wpil-setup-wizard-sub-heading wpil-wizard-autolink-insert-progress-header" style="display: none"><strong><?php esc_html_e('Creating Autolinks:', 'wpil'); ?></strong></p>
            <div class="syns_div wpil_report_need_prepare wpil-wizard-autolink-insert-progress-loader" style="display: none">
                <!--<p class="wpil-setup-wizard-sub-heading"><strong><?php esc_html_e('Creating Autolinks:', 'wpil'); ?></strong></p>-->
                <div class="progress_panel">
                    <div class="progress_count"><span class="wpil-loading-status"><?php esc_html_e('Preparing...', 'wpil'); ?></span></div>
                </div>
            </div>
            <p class="wpil-setup-wizard-sub-heading wpil-wizard-ai-scan-progress-header" style="display: none"><strong><?php esc_html_e('Scanning with AI:', 'wpil'); ?></strong></p>
            <div class="syns_div wpil_report_need_prepare wpil-wizard-ai-scan-progress-loader" style="display: none">
                <!--<p class="wpil-setup-wizard-sub-heading"><strong><?php esc_html_e('Scanning with AI:', 'wpil'); ?></strong></p>-->
                <div class="progress_panel">
                    <div class="progress_count"><span class="wpil-loading-status"><?php esc_html_e('Preparing...', 'wpil'); ?></span></div>
                </div>
            </div>
            <p class="wpil-setup-wizard-sub-heading wpil-wizard-ai-calculation-progress-header" style="display: none"><strong><?php esc_html_e('Processing AI Data:', 'wpil'); ?></strong></p>
            <div class="syns_div wpil_report_need_prepare wpil-wizard-ai-calculation-progress-loader" style="display: none">
                <!--<p class="wpil-setup-wizard-sub-heading"><strong><?php esc_html_e('Processing AI Data:', 'wpil'); ?></strong></p>-->
                <div class="progress_panel">
                    <div class="progress_count"><span class="wpil-loading-status"><?php esc_html_e('Preparing...', 'wpil'); ?></span></div>
                </div>
            </div>
        </div>
    </div>
</div>