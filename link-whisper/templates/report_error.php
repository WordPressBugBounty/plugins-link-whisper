<div class="wrap wpil-report-page wpil_styles">
    <h1 class="wp-heading-inline wpil-is-tooltipped wpil-no-overlay wpil-no-scale" <?php echo Wpil_Toolbox::generate_tooltip_text('broken-link-report-intro'); ?>><?php esc_html_e('Broken Links Report','wpil'); ?></h1>
    <hr class="wp-header-end">
    <div id="poststuff">
        <div id="post-body" class="metabox-holder">
            <div id="post-body-content" style="position: relative;">
                <input id="wpil-object-cache-flush-nonce" type="hidden" value="<?php echo wp_create_nonce('wpil-flush-object-cache'); ?>" />
                <?php include_once 'report_tabs.php'; ?>
                <div id="report_error">
                    <div class="wpil-is-tooltipped wpil-no-scale wpil-tooltip-target-child wpil-tooltip-target.wp-list-table" style="display:inline-block" <?php echo Wpil_Toolbox::generate_tooltip_text('broken-link-report-table'); ?>>
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