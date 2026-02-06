<div class="wrap wpil-report-page wpil_styles">
    <?php
    $codes = Wpil_Dashboard::getAllErrorCodes();
    $codes = (!empty($codes)) ? '&codes=' . implode(',', $codes) : '';
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function ($) {
            $('.actionheader').click(function () {
              // Toggle the open class on the arrow
              $(this).find('.actiontoggle-arrow').toggleClass('open');
              // Toggle the details section inside the closest .card
              $(this).closest('.actioncard').find('.actiondetails').slideToggle();
            });
          });
    </script>
    <style type="text/css">
        .box.wpil-is-tooltipped.wpil-no-scale {
            cursor: move;
        }
    </style>
    <style>
        .reportcontainer {
            display: flex;
            gap: 20px; /* Space between columns */
            padding: 20px 0;
            width: 100%;
        }
        .reportbox {
            flex: 1; /* Equal width */
            background: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .reportbox i {
            font-size: 30px;
            margin-bottom: 10px;
            display: block;
            color: #000;
            width: 100%;
            text-align: left;
        }
        .reportbox h3 {
            font-size: 18px;
            color: gray;
            margin-bottom: 5px;
            padding-bottom: 10px;
            font-weight: bold !important;
        }
        .reportbox p {
            font-size: 20px;
            font-weight: bold;
        }
        .blockdash {
            padding-top: 20px;
            text-align: left;
        }
        .blockdash a {
            font-size: 16px;
            font-weight: 700;
        }
        .topbarhandle a:focus{
            user-select: none;
            box-shadow: none;
        }
        .topbarhandle .reportbox .blockdash {
            padding-top: 0px;
        }
        .topbarhandle .reportbox .blockdash h3 {
            margin-top: 0px;
            font-size: 16px;
            padding-bottom: 18px;
        }
        .topbarhandle .reportbox .blockdash a,
        .topbarhandle .reportbox .wpil-dashboard-infostat {
            font-size: 30px;
            font-weight: 500;
            color: #000000;
        }
        .actioninfo-grid strong {
            font-size: 16px;
            color: grey;
        }
        #report_dashboard .box:nth-child(n+2) {
            margin-left: 10px !important;
        }
        /* Responsive: Stack on smaller screens */
        @media (max-width: 768px) {
            .reportcontainer {
                flex-wrap: wrap;
            }
            .reportbox {
                max-width: 50%; /* 2 columns on medium screens */
            }
        }
        @media (max-width: 480px) {
            .reportbox {
                max-width: 100%; /* 1 column on small screens */
            }
        }
    </style>
    <style type="text/css">
        .actioncontainer {
          display: flex;
          flex-wrap: wrap;
          gap: 20px;
        }
        .actioncolumn {
          flex: 1 1 48%;  /* Takes up 48% of the container width */
          box-sizing: border-box;
        }
        .actioncontent {
          background-color: white;
          padding: 20px;
          border-radius: 8px;
          box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
          min-height: 400px;
        }
        /* Responsive design: Stack columns on smaller screens */
        @media (max-width: 768px) {
          .actioncolumn {
            flex: 1 1 100%;  /* Stacks the columns vertically */
          }
        }
        .actioncard {
          background: white;
          border-radius: 10px;
          box-shadow: 0 1px 3px rgba(0,0,0,0.1);
          padding: 20px;
          max-width: 800px;
          margin: 0 auto 15px;
        }
        .actionheader {
          display: flex;
          justify-content: space-between;
          align-items: center;
          /*margin-bottom: 15px;*/
          /*cursor: pointer;*/
        }
        .actionheader-left {
          display: flex;
          align-items: center;
          font-size: 18px;
        }
        .actionheader-left img {
          width: 40px;
          margin-right: 10px;
        }
        .actionprice-book {
          display: flex;
          align-items: center;
          gap: 10px;
        }
        .actionprice {
          font-size: 20px;
          font-weight: bold;
        }
        .actionbook-btn {
            background: #0071c2;
            color: white !important;
            padding: 6px 0px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            min-width: 110px;
            /*max-width: 90px;*/
            text-align: center;
            overflow: hidden;
            text-overflow: ellipsis;
            position:relative;
            user-select:none;
            font-size: 10pt;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .actiondetails {
          border-top: 1px solid #e1e1e1;
          padding-top: 15px;
        }
        .actionflight-segment {
          margin-bottom: 20px;
        }
        .actionsegment-title {
          font-weight: normal;
          margin-bottom: 8px;
          font-size: 16px;
          color: grey;
        }
        .actioninfo-grid {
          display: grid;
          grid-template-columns: repeat(3, 1fr);
          gap: 10px;
        }
        .actioninfo-block {
          /*background: #f9f9f9;*/
          padding: 10px 0px;
          border-radius: 6px;
          font-size: 30px;
          font-weight: 500;
          color: #000000;
        }
        .actioninfo-block strong {
          display: block;
          margin-bottom: 5px;
        }
        .actioninfo-icon {
          margin-right: 5px;
        }
        .actiontoggle-arrow {
          cursor: pointer;
          transform: rotate(0deg);
          transition: transform 0.3s ease;
        }
        .actiontoggle-arrow.open {
          transform: rotate(180deg);
        }
    </style>
    <style type="text/css">
        .dashheadings {
            padding: 20px 0 !important;
            font-size: 20px !important;
        }
        span.informativeicons {
            float: right;
        }
        span.informativeicons.infodiv i {
            color: orange;
        }
        span.informativeicons.actiondiv i {
            color: red;
        }
        .btngreat{
            background-color: green !important;
        }
        .btnfix{
            background-color: #ff5d5d !important;
        }
        .btnok {
            background-color: orange !important;
        }
        .tag-negative{
            background-color: #ff5d5d !important;
        }
        .tag-neutral{
            background-color: orange !important;
        }
        .tag-positive{
            background-color: green !important;
        }
        .unclickbox {
            cursor: default;
        }
        .clickbox {
            cursor: pointer;
        }
        .unclickbox .blockdash a {
            cursor: default;
        }
        .informativeicons img {
            border: 1px solid lightgrey;
            border-radius: 20%;
            padding: 2px;
        }
        h2.dashheadings {
            font-weight: normal !important;
        }
        .actioncontent div h3 {
            font-weight: normal !important;
        }
        div#report_dashboard_domains .host {
            font-weight: 500;
        }
        #report_dashboard_domains .line {
            background: #e5e2e2;
        }
    </style>
    <style type="text/css">
        /*tooltip*/
        .cust-tooltipdash .wpil-report-header-tooltip .wpil_help i{
            position: absolute;
            top: -6px;
            left: 5px;
        }
        .cust-tooltipdash .wpil-report-header-tooltip .wpil_help i::before{
            position:absolute;
            left:0;
        }
        .cust-tooltipdash .wpil-report-header-tooltip .wpil_help .wpil-help-text {
            left: 50px;
            top: -10px;
        }
        .wpil_help .wpil-help-text{
            background-color: #fdfdfd;
            color: #000;
            border: 1px solid #cdcdcd;
            box-shadow: 0px 0px 1px 1px #bcbcbc;
        }
        .cust-tooltipdash {
            top: -4px !important;
        }
        .cust-tooltipdash .wpil_help {
            position: relative !important;
            top: 0;
            right: 0;
            margin: 0;
            padding: 0;
        }
        .percchangeclick {
            border: 1px solid #e2e2e2;
            width: fit-content;
            padding: 5px;
            display: inline-flex;
            margin-left: 10px;
            border-radius: unset;
        }
    </style>
    <style type="text/css">

        #report_dashboard_domains .line1 span, #report_dashboard_domains .line2 span,
        #report_dashboard_domains .line3 span, #report_dashboard_domains .line4 span {
            background: #4272fd;
        }
        #report_dashboard_domains > div:nth-child(n+1):not(.line), #report_dashboard_domains > div:nth-child(n+2):not(.line) {
            display: none;
        }
    </style>

<?php
    $link_icon = '<svg width="24" height="24" style="position: absolute; margin: 1px 0px 0 3px; height: 12px; width: 12px;fill:#ffffff; stroke:#ffffff; display:inline-block;" viewBox="0 0 24 24" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:svg="http://www.w3.org/2000/svg"><g id="wpil-svg-outbound-1-icon-path" transform="matrix(0.046875,0,0,0.046875,0.0234375,0.02343964)">
                            <path d="M 473.563,227.063 407.5,161 262.75,305.75 c -25,25 -49.563,41 -74.5,16 -25,-25 -9,-49.5 16,-74.5 L 349,102.5 283.937,37.406 c -14.188,-14.188 -2,-37.906 19,-37.906 h 170.625 c 20.938,0 37.938,16.969 37.938,37.906 v 170.688 c 0,20.937 -23.687,33.187 -37.937,18.969 z M 63.5,447.5 h 320 V 259.313 l 64,64 V 447.5 c 0,35.375 -28.625,64 -64,64 h -320 c -35.375,0 -64,-28.625 -64,-64 v -320 c 0,-35.344 28.625,-64 64,-64 h 124.188 l 64,64 H 63.5 Z"></path>
                        </g></svg>';
    ?>
    <h1 class="wp-heading-inline wpil-is-tooltipped wpil-no-overlay wpil-no-scale" <?php echo Wpil_Toolbox::generate_tooltip_text('dashboard-intro'); ?>>Dashboard</h1>
    <hr class="wp-header-end">
    <div id="poststuff">
        <div id="post-body" class="metabox-holder">
            <div id="post-body-content" style="position: relative;">
                <?php include_once 'report_tabs_dashboard.php'; ?>
                <?php $loading = (isset($_GET['loading']) && !empty($_GET['loading'])); ?>
                <?php
                $orphanedCount     = Wpil_Dashboard::getOrphanedPostsCount();
                $brokenLinksCount  = Wpil_Dashboard::getBrokenLinksCount();
                $notfoundLinksCount = Wpil_Dashboard::get404LinksCount();
                function wpilDashboardgetStatusIcon($count){
                    return (WP_INTERNAL_LINKING_PLUGIN_URL . '/images/') . (empty($count) ? 'check.png' : 'spanner.png');
                }
                ?>
                <div id="report_dashboard <?php echo ($loading) && false ? 'wpil-dashboard-report-is-loading': '';?> ">
                    <?php if($loading){ ?>
                    <input type="hidden" class="wpil-wizard-loading-dashboard" value="1">
                    <input type="hidden" class="wpil-wizard-inserting-autolinks" value="0">
                    <input type="hidden" class="wpil-wizard-loading-dashboard-nonce" value="<?php echo wp_create_nonce($user->ID . 'wpil_dashboard_loading_nonce'); ?>">
                    <div class="syns_div wpil_report_need_prepare wpil-report-download-banner wpil-is-tooltipped wpil-no-scale" <?php echo Wpil_Toolbox::generate_tooltip_text('dashboard-report-loading-bar'); ?>>
                        <!--<p class="wpil-setup-wizard-sub-heading"><strong><?php esc_html_e('Processing AI Data:', 'wpil'); ?></strong></p>-->
                        <div class="progress_panel" style="position:relative">
                            <div class="wpil-dashboard-processing-status" style="position: absolute; z-index: 9999; width: 100%;">
                                <div class="wpil-report-download-banner-process">
                                    <i class="dashicons dashicons-clock" style="width: 40px;height: 40px;color: #fff;margin: 10px;font-size: 40px;"></i>
                                    <span class="wpil-dashboard-processing-message" style="margin-left: 5px; padding: 18px 0px !important;position: absolute; font-size: 20px;color: white;">Scanning Site</span>
                                </div>
                                <div class="wpil-report-download-banner-remaining-time" style="margin-left: 5px; padding: 18px 0px !important; position: absolute; font-size: 20px; color: white; right: 15px; top: 0;">
                                    <span class="wpil-dashboard-processing-time-remaining" style="margin-right:10px;">Calculating Time Remaining</span>
                                    <span class="wpil-dashboard-processing-clock">--:--:--</span>
                                </div>
                            </div>
                            <div class="progress_count" style="background-color:#2da7fd"><span class="wpil-loading-status"></span></div>
                        </div>
                    </div>
                    <div class="syns_div wpil_report_need_prepare wpil-report-autolink-insert-banner wpil-is-tooltipped wpil-no-scale" style="display:none;" <?php echo Wpil_Toolbox::generate_tooltip_text('dashboard-report-loading-bar'); ?>>
                        <div class="progress_panel" style="position:relative">
                            <div class="wpil-dashboard-processing-status" style="position: absolute; z-index: 9999; width: 100%;">
                                <div class="wpil-report-autolink-insert-process">
                                    <i class="dashicons dashicons-admin-links" style="width: 40px;height: 40px;color: #fff;margin: 10px;font-size: 40px;"></i>
                                    <span class="wpil-dashboard-processing-message" style="margin-left: 5px; padding: 18px 0px !important;position: absolute; font-size: 20px;color: white;">Creating Links!</span>
                                </div>
                                <div class="wpil-report-autolink-insert-remaining-time" style="margin-left: 5px; padding: 18px 0px !important; position: absolute; font-size: 20px; color: white; right: 15px; top: 0;">
                                    <span style="margin-right:10px;">Links Created:</span>
                                    <span class="wpil-dashboard-processing-autolinks-inserted">0</span>
                                </div>
                            </div>
                            <div class="progress_count" style="width: 100%; background-color: #b63ef8;"><span class="wpil-loading-status"></span></div>
                        </div>
                    </div>
                    <?php } ?>
                    <h2 class="dashheadings">Link Insights</h2>
                    <?php $box_counter = 1; ?>
                    <div class="reportcontainer topbarhandle">
                        <a href="<?php echo admin_url('admin.php?page=link_whisper&type=links')?>" target="_blank" class="reportbox clickbox">
                            <div class="blockdash">
                                <h3>Posts Crawled</h3>
                                <div class="wpil-dashboard-infostat" <?php echo Wpil_Toolbox::generate_tooltip_text('dashboard-link-stats-widget-posts-crawled-stat'); ?>><span class="wpil-report-stats-posts-crawled"><?=Wpil_Dashboard::getPostCount()?></span></div>
                            </div>
                        </a><?php /*
                        <a href="<?php echo admin_url('admin.php?page=link_whisper&type=links')?>" target="_blank" class="reportbox clickbox">
                            <div class="blockdash">
                                <h3>Links Scanned</h3>
                                <div class="wpil-dashboard-infostat" <?php echo Wpil_Toolbox::generate_tooltip_text('dashboard-link-stats-widget-links-found-stat'); ?>><span class="wpil-report-stats-links-found"><?=Wpil_Dashboard::getLinksCount();?></span></div>
                            </div>
                        </a>*/ ?>
                        <div class="reportbox unclickbox">
                            <div class="blockdash">
                                <?php 
                                    $summary = Wpil_Dashboard::get_click_traffic_stats();
                                    $clicks_30 = $summary['clicks_30'];
                                    $clicks_old = $summary['clicks_old'];
                                    $difference = $clicks_30 - $clicks_old;
                                    $percent_change = $clicks_old != 0 
                                        ? round(($difference / $clicks_old) * 100, 2) 
                                        : ($clicks_30 > 0 ? 100 : 0);
                                    $is_positive = $difference >= 0;
                                ?>
                                <h3>Link Clicks Tracked</h3>
                                <a href="javascript:void(0)"><?php echo number_format($summary['clicks_30']); ?></a>
                                <div class="percchangeclick">
                                    <?php if ($is_positive): ?>
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#2e7d32" viewBox="0 0 24 24">
                                            <path d="M4 12l1.41 1.41L11 7.83v12.17h2V7.83l5.59 5.58L20 12l-8-8-8 8z"/>
                                        </svg>
                                    <?php else: ?>
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#c62828" viewBox="0 0 24 24">
                                            <path d="M4 12l1.41-1.41L11 16.17V4h2v12.17l5.59-5.58L20 12l-8 8-8-8z"/>
                                        </svg>
                                    <?php endif; ?>
                                    <span style="margin-left:5px;"><?php echo ($is_positive ? '+' : '') . $percent_change . '%'; ?></span>
                                </div>
                            </div>
                        </div>
                        <a href="<?php echo admin_url('admin.php?page=link_whisper&type=links&orphaned=1')?>" target="_blank" class="reportbox <?php echo (!empty($orphanedCount)) ? 'clickbox': ''; ?>">
                            <span class="informativeicons actiondiv">
                                <img src="<?php echo wpilDashboardgetStatusIcon($orphanedCount); ?>" />
                            </span>
                            <div class="blockdash">
                                <h3>Orphaned Posts</h3>
                                <?php if($loading){ ?>
                                    <div class="wpil-dashboard-infostat" <?php echo Wpil_Toolbox::generate_tooltip_text('dashboard-link-stats-widget-orphaned-posts-stat'); ?>><div class="wpil-report-dashboard-loading la-ball-clip-rotate la-mid"><div style="border-color: black;border-bottom-color: transparent;"></div></div>
                                <?php } else { ?>
                                    <div class="wpil-dashboard-infostat" href="javascript:void(0)" <?php echo Wpil_Toolbox::generate_tooltip_text('dashboard-link-stats-widget-orphaned-posts-stat'); ?>><?=Wpil_Dashboard::getOrphanedPostsCount()?>
                                <?php } ?>
                                </div>
                            </div>
                        </a>
                        <a href="<?php echo htmlspecialchars(admin_url('admin.php?page=link_whisper&type=error' . $codes)) ?>" target="_blank" class="reportbox <?php echo (!empty($brokenLinksCount)) ? 'clickbox': ''; ?>">
                            <span class="informativeicons actiondiv">
                                <img src="<?= wpilDashboardgetStatusIcon($brokenLinksCount); ?>" />
                            </span>
                            <div class="blockdash">
                                <h3>Broken Links</h3>
                                <div class="wpil-dashboard-infostat" <?php echo Wpil_Toolbox::generate_tooltip_text('dashboard-link-stats-widget-broken-links-stat'); ?>><?=Wpil_Dashboard::getBrokenLinksCount()?></div>
                            </div>
                        </a>
                    </div>
                    <div class="actioncontainer reportcontainer">
                        <div class="actioncolumn" style="height: 100%;">
                            <h2 class="dashheadings">Link Health</h2>
                            <div class="actioncontent">
                            <div class="actioncard">
                              <div class="actionheader">
                                <?php
                                $link_density = Wpil_Dashboard::get_percent_of_posts_hitting_link_targets();
                                $density_status = 'tag-positive';
                                $density_subtext = __('Great', 'wpil');
                                if (!empty($link_density['percent'])) {
                                    if ($link_density['percent'] > 80) {
                                        $density_status = 'tag-positive';
                                        $density_subtext = __('Great', 'wpil');
                                    } elseif ($link_density['percent'] > 60) {
                                        $density_status = 'tag-neutral';
                                        $density_subtext = __('Ok', 'wpil');
                                    } else {
                                        $density_status = 'tag-negative';
                                        $density_subtext = 'Fix';
                                    }
                                }

                                $density_subtext = '<a href="'. admin_url('admin.php?page=link_whisper&type=links&link_density=1') . '" class="actionbook-btn '.$density_status.'" target="_blank">' . $density_subtext .$link_icon.'</a>';
                                ?>
                                <div class="actionheader-left">
                                    <div>Link Coverage: <span class="wpil-report-stats-link-coverage"><?php echo $link_density['percent']; ?>%</span></div>
                                    <div class="wpil-report-header-container cust-tooltipdash">
                                        <div class="wpil-report-header-tooltip">
                                            <div class="wpil_help">
                                                <i class="dashicons dashicons-editor-help"></i>
                                                <div class="wpil-help-text" style="display: none;">Link Coverage tells you how many pages are receiving internal links.<br><br>A high coverage means most of your content is connected and discoverable by search engines and users — great for crawlability and SEO.<br><br>It's recommended that each post have at least 1 Inbound Internal link, and 3 Outbound Internal links.</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="actionprice-book">
                                    <?php echo $density_subtext; ?>
                                </div>
                              </div>
                            </div>
                            <br>
                            <?php
                                $ai_active = Wpil_Settings::can_do_ai_powered_suggestions(); // if we have a API key and at least some of the embedding data processed
                                $link_relatedness = 0;
                                if($ai_active){
                                    $link_relatedness = Wpil_Dashboard::get_related_link_percentage();
                                    if($link_relatedness == 0){
                                        $link_relatedness_status = 'tag-neutral';
                                        $link_relatedness_button_text = 'OK';
                                    }elseif($link_relatedness > 79){
                                        $link_relatedness_status = 'tag-positive';
                                        $link_relatedness_button_text = 'Great';
                                    }elseif($link_relatedness > 50){
                                        $link_relatedness_status = 'tag-neutral';
                                        $link_relatedness_button_text = 'OK';
                                    }else{
                                        $link_relatedness_status = 'tag-negative';
                                        $link_relatedness_button_text = 'Fix';
                                    }
                                    $link_relatedness .= '%';
                                    $link_relatedness_button_text = '<a href="' . admin_url('admin.php?page=link_whisper&type=links&link_relation=1') . '" class="actionbook-btn '.$link_relatedness_status.'">'.$link_relatedness_button_text.$link_icon.'</a>';
                                }else{
                                    $link_relatedness = "Connect to AI for Analysis";
                                    $link_relatedness_status = 'tag-neutral';
                                    $link_relatedness_button_text = 'Connect';
                                    $link_relatedness_button_text = '<a href="' . esc_url(Wpil_AI::get_linkwhisper_ai_auth_url(admin_url('admin.php?page=link_whisper_ai_subscription'))) . '" class="actionbook-btn '.$link_relatedness_status.'">'.$link_relatedness_button_text.$link_icon.'</a>';
                                }
                            ?>
                            <div class="actioncard">
                              <div class="actionheader">
                                <div class="actionheader-left">
                                    <div>Link Quality Score: <span class="wpil-report-stats-relation-score"><?php echo $link_relatedness; ?></span></div>
                                    <div class="wpil-report-header-container cust-tooltipdash">
                                        <div class="wpil-report-header-tooltip">
                                            <div class="wpil_help">
                                                <i class="dashicons dashicons-editor-help"></i>
                                                <div class="wpil-help-text" style="display: none;">Link Quality Score measures how topically related the linking source and target pages are.<br><br>A higher score = more on-topic internal linking = good for SEO.<br><br>We recommend having 80% of your links going between related posts.</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="actionprice-book">
                                    <?php echo $link_relatedness_button_text; ?>
                                </div>
                              </div>
                            </div>
                            <br>
                            <?php
                                $external_link_emphasis = Wpil_Dashboard::get_external_link_distribution(1);
                                $external_link_emphasis_percent = 0;
                                $external_link_tag = 'OK';
                                if(empty($external_link_emphasis) || !isset($external_link_emphasis[0]->representation)){
                                    $external_link_emphasis_status = 'tag-neutral';
                                }else{
                                    $external_link_emphasis_percent = round($external_link_emphasis[0]->representation, 2) * 100;
                                    if($external_link_emphasis_percent < 60){
                                        $external_link_emphasis_status = 'tag-positive';
                                        $external_link_emphasis_subtext = '';
                                        $external_link_tag = 'Great';
                                    }elseif($external_link_emphasis_percent < 70){
                                        $external_link_emphasis_status = 'tag-neutral';
                                        $external_link_emphasis_subtext = '';
                                        $external_link_tag = 'OK';
                                    }else{
                                        $external_link_emphasis_status = 'tag-negative';
                                        $external_link_emphasis_subtext = '';
                                        $external_link_tag = 'Fix';
                                    }
                                }

                                $external_link_tag = '<a href="'. admin_url('admin.php?page=link_whisper&type=domains&domain_focus=1') . '" target="_blank" class="actionbook-btn '.$external_link_emphasis_status.'">'.$external_link_tag.$link_icon.'</a>';
                            ?>
                            <div class="actioncard">
                              <div class="actionheader">
                                <div class="actionheader-left">
                                    <div>External Site Focus: <span class="wpil-report-stats-external-focus"><?php echo $external_link_emphasis_percent . '%'; // Display the percentage value ?></span></div>
                                    <div class="wpil-report-header-container cust-tooltipdash">
                                        <div class="wpil-report-header-tooltip">
                                            <div class="wpil_help">
                                                <i class="dashicons dashicons-editor-help"></i>
                                                <div class="wpil-help-text" style="display: none;">Best practices recommend having a balanced distribution of external links as this looks most natural to search engines.<br><br>If your site has more than 60% of Outbound External links going to the same site, this can look unnatural.</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="actionprice-book">
                                  <?php echo $external_link_tag; ?>
                                </div>
                              </div>
                            </div>
                            <br>
                            <?php
                            
                                // WE"LL JUST LIFT THE CODE FROM THE EMAIL!
                                $anchor_word_counts = Wpil_Dashboard::getAnchorPostCounts();
                                $anchor_word_status = 'tag-neutral';
                                $anchor_word_button_text = 'OK';
                                $anchor_word_percent = 'Unknown';
                                if(!empty($anchor_word_counts['total']) && !empty($anchor_word_counts['filtered'])){
                                    $percentage = $anchor_word_counts['filtered']/$anchor_word_counts['total'];
                                    if($percentage > 0.80){
                                        $anchor_word_status = 'tag-positive';
                                        $anchor_word_button_text = 'Great';
                                    }elseif($percentage > 0.60){
                                        $anchor_word_status = 'tag-neutral';
                                        $anchor_word_button_text = 'OK';
                                    }else{
                                        $anchor_word_status = 'tag-negative';
                                        $anchor_word_button_text = 'Fix';
                                    }

                                    $anchor_word_percent = (round($percentage, 2) * 100) . '%';
                                }

                                $anchor_word_button_text = '<a href="'. admin_url('admin.php?page=link_whisper&type=links&anchor_length=1') . '" target="_blank" class="actionbook-btn '.$anchor_word_status.'">'.$anchor_word_button_text.$link_icon.'</a>';
                                $anchor_word_button_text = '<a href="#" class="actionbook-btn '.$anchor_word_status.'">Coming Soon</a>';
                            ?>
                            <div class="actioncard">
                              <div class="actionheader">
                                <div class="actionheader-left">
                                    <div>Anchor Length Score: <span class="wpil-report-stats-anchor-quality"><?php echo esc_html($anchor_word_percent); ?></span></div>
                                    <div class="wpil-report-header-container cust-tooltipdash">
                                        <div class="wpil-report-header-tooltip">
                                            <div class="wpil_help">
                                                <i class="dashicons dashicons-editor-help"></i>
                                                <div class="wpil-help-text" style="display: none;">Best practices recommend that anchors be long enough to convey meaning to a human reader.<br><br>In most cases, this is between 3 and 7 words long.<br><br>We recommend having the score above 60%</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="actionprice-book">
                                  <?php echo $anchor_word_button_text; ?>
                                </div>
                              </div>
                            </div>
                          </div>
                            <div class="actioncontent" style="margin-top: 20px;">
                                <!-- Content for the second column -->
                                <div <?php echo Wpil_Toolbox::generate_tooltip_text('dashboard-domains-widget'); ?>>
                                    <h3 class="title">Most Linked To <a href="<?=admin_url('admin.php?page=link_whisper&type=domains')?>">Domains</a></h3>
                                    <div id="wpil_links_domain_chart" style="width: 320px;height: 320px;margin: 0 auto;"></div>
                                    <div class="body" id="report_dashboard_domains">
                                        <?php
                                        $i = 0;
                                        $prev = isset($domains[0]->host) ? $domains[0]->host : 0;
                                        $count = 0; // Initialize counter
                                    ?>
                                    <?php foreach ($domains as $domain) : ?>
                                        <?php 
                                            if ($count >= 5) break; // Stop after 5 items
                                            if ($prev != $domain->host) { 
                                                $i++; 
                                                $prev = $domain->host; 
                                            } 
                                            $count++; // Increment counter
                                            ?>
                                            <div class="domainrelatedcontent">
                                                <div class="count <?php echo 'mltdcount-'.$i; ?>"><?= $domain->cnt ?></div>
                                                <div class="host <?php echo 'mltdval-'.$i; ?>"><?= $domain->host ?></div>

                                                <div class="line line<?= $i ?>">
                                                    <span style="width: <?= (($domain->cnt / $top_domain) * 100) ?>%"></span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php include 'notification_hub.php'; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
