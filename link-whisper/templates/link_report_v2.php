<div class="wrap wpil-report-page wpil_styles">
    <?php if( empty( get_option(WPIL_PREMIUM_NOTICE_DISMISSED, '') ) ){ ?>
    <div id="lw_banner" style="display: none">
        <img class="close" src="<?php echo esc_url(WP_INTERNAL_LINKING_PLUGIN_URL . 'images/icon_delete.png'); ?>">
        <div class="title"><?php _e('Upgrade to Link Whisper Premium', 'wpil'); ?></div>
        <div class="features">
            <div><?php _e('+ Add multiple links with pre-selected anchor text in one action!', 'wpil'); ?></div>
            <div><?php _e('+ Improve SEO by adding inbound links to older, less connected pages.', 'wpil'); ?></div>
            <div><?php _e('+ Quickly manage links (add, edit, delete) from the reports page.', 'wpil'); ?></div>
            <div><?php _e('+ Directly edit sentences and modify anchor text or URLs within Link Whisper.', 'wpil'); ?></div>
            <div><?php _e('+ Exclude specific URLs/categories from being suggested as link opportunities.', 'wpil'); ?></div>
            <div><?php _e('+ Optimize for target SEO keywords with suggested relevant links, including import from SEO plugins like Yoast and RankMath.', 'wpil'); ?></div>
            <div><?php _e('+ Connect to Google Search Console for target keywords Google is giving you impressions for.', 'wpil'); ?></div>
            <div><?php _e('+ Automate linking for specified keywords to chosen URLs, with control over frequency.', 'wpil'); ?></div>
            <div><?php _e('+ Change old URLs site-wide to new ones easily with a bulk link changer.', 'wpil'); ?></div>
            <div><?php _e('+ Identify and manage broken links site-wide, with verification over time to ensure accuracy.', 'wpil'); ?></div>
            <div><?php _e('+ Receive linking suggestions across multiple sites with Link Whisper Premium.', 'wpil'); ?></div>
            <div><?php _e('+ Access the related posts widget which can display posts with thumbnails or bullet lists, and automatically prioritize linking orphan pages.', 'wpil'); ?></div>
        </div>
        <a href="<?php echo esc_url(WPIL_STORE_URL . '/upgrade-offer/'); ?>" target="blank"><?php _e('Get $15 Off Link Whisper Premium Now!', 'wpil'); ?></a>
    </div>
    <?php } ?>
    <?php $user = wp_get_current_user(); ?>
    <h1 class="wp-heading-inline wpil-is-tooltipped wpil-no-overlay wpil-no-scale wpil-link-report" data-wpil-tooltip-read-time="4500" <?php echo Wpil_Toolbox::generate_tooltip_text('link-report-header'); ?>><?php echo $title;?></h1>
    <hr class="wp-header-end">
    <div id="poststuff">
        <div id="post-body" class="metabox-holder">
            <div id="post-body-content" style="position: relative;">
                <input id="wpil-object-cache-flush-nonce" type="hidden" value="<?php echo wp_create_nonce('wpil-flush-object-cache'); ?>" />
                <?php echo $sub_report ?>
                <?php include_once 'report_tabs.php'; ?>
                <div class="tbl-link-reports">
                    <?php echo $report_description; ?>
                        <div class="wpil-filter-wrapper">
                            <button id="wpil-filter-toggle" class="wpil-hamburger-toggle" type="button" aria-expanded="false" aria-controls="wpil-filter-panel">
                                <svg class="wpil-hamburger-icon" viewBox="0 0 100 100" width="30" height="30">
                                    <path class="line top" d="M 20,30 H 80" />
                                    <path class="line middle" d="M 20,50 H 80" />
                                    <path class="line bottom" d="M 20,70 H 80" />
                                </svg>
                            </button>
                            <div id="wpil-filter-panel" class="wpil-report-search-form-wrapper" hidden>
                                <script>
                                    document.addEventListener('DOMContentLoaded', function () {
                                        const toggleBtn = document.getElementById('wpil-filter-toggle');
                                        const panel = document.getElementById('wpil-filter-panel');

                                        toggleBtn.addEventListener('click', function () {
                                            const isOpen = !panel.hasAttribute('hidden');
                                            panel.toggleAttribute('hidden');
                                            toggleBtn.setAttribute('aria-expanded', String(!isOpen));
                                            toggleBtn.classList.toggle('open', !isOpen);
                                        });
                                    });
                                </script>
                                <form class="wpil-report-search-form-inner">
                                    <input type="hidden" name="page" value="link_whisper" />
                                    <input type="hidden" name="type" value="links" />
                                    <?php $tbl->search_box('Search', 'search_posts'); ?>
                                </form>
                            </div>
                        </div>
                    <div class="wpil-is-tooltipped wpil-no-scale wpil-tooltip-target-child wpil-tooltip-target.linkingstats <?php echo (isset($_GET['link_relation'])) ? 'wpil-show-relation': '';?> <?php echo 'wpil-column-count-'.Wpil_Report::get_report_dropdown_column_count();?> <?php echo (Wpil_Settings::get_generate_quick_links()) ? 'wpil-load-quick-links wait-quicklinks': '';?>" style="display:inline-block" <?php echo Wpil_Toolbox::generate_tooltip_text('link-report-table'); ?>>
                        <?php $tbl->display(); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    var wpil_admin_url = '<?php echo admin_url()?>';
</script>
