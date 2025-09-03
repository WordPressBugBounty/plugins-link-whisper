<div class="wrap wpil-report-page wpil_styles" id="detailed_clicks_page">
    <h1 class="wp-heading-inline"><?php esc_html_e("Click Details", "wpil"); ?></h1>
    <a href="<?=esc_url($return_url)?>" class="page-title-action return_to_report"><?php esc_html_e('Return to Report','wpil'); ?></a>
    <h2><?php echo sprintf(__('Showing click details for: %s', 'wpil'), $sub_title); ?></h2>
    <hr class="wp-header-end">
    <div id="poststuff">
        <div id="post-body" class="metabox-holder">
            <div id="post-body-content" style="position: relative;">
                <div id="wpil_link-articles" class="postbox">
                    <h2 class="hndle no-drag"><span><?php esc_html_e('Link Whisper Click Chart', 'wpil'); ?></span></h2>
                    <div class="inside">
                        <div id="link-click-detail-chart">
                            <input type="hidden" id="link-click-detail-data" value="<?php echo esc_attr(json_encode($click_chart_data)); ?>">
                            <input type="hidden" id="link-click-detail-data-range" value="<?php echo esc_attr(json_encode( array('start' => date($date_format, $start_date), 'end' => date($date_format, $end_date ) ) )); ?>">
                            <input type="hidden" id="link-click-detail-data-format" value="<?php echo esc_attr(Wpil_Toolbox::convert_date_format_for_js()) ?>">
                        </div>
                    </div>
                </div>
                <div class="wpil-click-detail-controls">
                    <div class="inside" style="margin: 0 0 30px 0; display: inline-block;">
                        <label for="wpil-click-detail-daterange" style="font-weight: bold; font-size: 16px !important; margin: 18px 0 8px 2px; display: block; display: inline-block;"><?php esc_html_e('Filter Clicks by Date', 'wpil'); ?></label><br/>
                        <input id="wpil-click-detail-daterange" type="text" name="daterange" class="wpil-date-range-filter" value="<?php echo date($date_format, $start_date) . ' - ' . date($date_format, $end_date); ?>">
                    </div>
                    <div id="keywords" style="margin-top: 10px;">
                        <form action="" method="post">
                            <label for="keywords_field">Search by Keyword</label>
                            <textarea name="keywords" id="keywords_field"><?=!empty($_POST['keywords'])?sanitize_textarea_field($_POST['keywords']):''?></textarea>
                            <button type="submit" class="button-primary">Search</button>
                        </form>
                    </div>
                </div>
                <div id="wpil_link-articles" class="postbox">
                    <h2 class="hndle no-drag"><span><?php esc_html_e('Link Whisper Click Data Table', 'wpil'); ?></span></h2>
                    <div class="inside">
                    <?php
                        $table = new Wpil_Table_DetailedClick();
                        $table->prepare_items();
                        include WP_INTERNAL_LINKING_PLUGIN_DIR . '/templates/report_detailed_clicks.php';
                    ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
