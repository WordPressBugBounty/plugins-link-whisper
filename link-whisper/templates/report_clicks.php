<div class="wrap wpil-report-page wpil_styles">
    <h1 class="wp-heading-inline wpil-is-tooltipped wpil-no-overlay wpil-no-scale" <?php echo Wpil_Toolbox::generate_tooltip_text('click-report-intro'); ?>>Clicks Report</h1>
    <hr class="wp-header-end">
    <div id="poststuff">
        <div id="post-body" class="metabox-holder">
            <div id="post-body-content" style="position: relative;">
                <?php include_once 'report_tabs.php'; ?>
                <div id="report_clicks">
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
                            <div class="wpil-hamburger-filter-container" style="display:flex; flex-direction: column;">
                                <div class="wpil-hamburger-filter-option">
                                    <div class="wpil-hamburger-filter-title">Search Posts</div>
                                    <div class="wpil-hamburger-filter-fields">
                                        <form class="wpil-report-search-form-inner">
                                            <input type="hidden" name="page" value="link_whisper" />
                                            <input type="hidden" name="type" value="clicks" />
                                            <input type="hidden" name="click_delete_confirm_text" value="<?php esc_attr_e('Do you really want to delete all the click data in the row?', 'wpil'); ?>" />
                                            <?php $table->search_box("🔎 " . 'Search', 'search'); ?>
                                        </form>
                                    </div>
                                </div>
                                <div class="wpil-hamburger-filter-option">
                                    <div class="wpil-hamburger-filter-title">Export Click Data</div>
                                    <div class="wpil-hamburger-filter-fields">
                                        <div class="wpil-csv-export-wrapper">
                                            <div style="display:inline-block">
                                                <a href="javascript:void(0)" class="wpil-filter-submit-button csv_button" data-type="<?=esc_attr($_GET['type'])?>" id="wpil_cvs_export_button" style="text-align:left" data-file-name="<?php esc_attr_e('detailed-clicks-export.csv', 'wpil'); ?>">📤 Detailed Export to CSV</a>
                                                <a href="javascript:void(0)" class="wpil-filter-submit-button csv_button" data-type="<?=esc_attr($_GET['type'])?>_summary" id="wpil_cvs_export_button" style="text-align:left" data-file-name="<?php esc_attr_e('summary-clicks-export.csv', 'wpil'); ?>">📤 Summary Export to CSV</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="wpil-hamburger-filter-option">
                                    <div class="wpil-hamburger-filter-title">Filter Posts</div>
                                    <div class="wpil-hamburger-filter-fields">
                                        <?php
                                        $post_type = Wpil_Filter::linksPostType();
                                        $post_type = !empty($post_type) ? $post_type : 0;
                                        ?>
                                        <div class="actions bulkactions wpil-is-tooltipped wpil-no-scale" data-wpil-tooltip-read-time="3500" <?php echo Wpil_Toolbox::generate_tooltip_text('click-report-filter'); ?> id="wpil_clicks_table_filter">
                                            <select name="click_post_type" style="margin-bottom: 8px;">
                                                <option value="0"><?php esc_html_e('All Post Types', 'wpil'); ?></option>
                                                <?php foreach (Wpil_Settings::getAllTypes() as $type) : ?>
                                                    <option value="<?php echo esc_attr($type) ?>" <?=$type===$post_type?' selected':''?>><?php echo esc_html(ucfirst($type)); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <span class="wpil-click-report-filter-submit wpil-filter-submit-button">🔎 Filter</span>
                                            <input type="hidden" class="post-filter-nonce" value="<?php echo wp_create_nonce(get_current_user_id() . 'wpil_filter_nonce'); ?>">
                                        </div>
                                    </div>
                                </div>
                        </div>
                    </div>
                    <div class="wpil-is-tooltipped wpil-no-scale wpil-tooltip-target-child wpil-tooltip-target.wp-list-table" style="display:inline-block" <?php echo Wpil_Toolbox::generate_tooltip_text('click-report-table'); ?>>
                        <?php $table->display(); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    var wpil_admin_url = '<?php echo admin_url()?>';
</script>