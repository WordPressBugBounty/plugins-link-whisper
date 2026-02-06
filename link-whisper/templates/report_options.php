<input type="hidden" id="wp_screen_options_name" name="wp_screen_options[option]" value="report_options" />
<input type="hidden" name="wp_screen_options[value]" value="yes" />
<fieldset class="screen-options">
    <legend>Options</legend>
    <input type="checkbox" name="report_options[show_categories]" id="show_categories" <?=$show_categories ? 'checked' : ''?> <?= ('links' !== $report && !empty($report)) ? $hide : ''?>/>
    <label for="show_categories" <?= ('links' !== $report && !empty($report)) ? $hide : ''?>>Show categories&nbsp;&nbsp;&nbsp;</label>
    <input type="checkbox" name="report_options[show_type]" id="show_type" <?=$show_type ? 'checked' : ''?> <?= 'links' !== $report ? $hide : ''?>/>
    <label for="show_type" <?= 'links' !== $report ? $hide : ''?>>Show post type&nbsp;&nbsp;&nbsp;</label>
    <input type="checkbox" name="report_options[show_date]" id="show_date" <?=$show_date ? 'checked' : ''?> <?= 'links' !== $report ? $hide : ''?>/>
    <label for="show_date" <?= 'links' !== $report ? $hide : ''?>>Show the post publish date&nbsp;&nbsp;&nbsp;</label>
    <?php if(!empty(Wpil_Settings::HasGSCCredentials())){ ?>
    <input type="checkbox" name="report_options[show_traffic]" id="show_traffic" <?=$show_traffic ? 'checked' : ''?> <?= ('links' !== $report && !empty($report)) ? $hide : ''?>/>
    <label for="show_traffic" <?= ('links' !== $report && !empty($report)) ? $hide : ''?>>Show the organic traffic&nbsp;&nbsp;&nbsp;</label>
    <?php } ?>
    <input type="checkbox" name="report_options[hide_ignore]" id="hide_ignore" <?=$hide_ignore ? 'checked' : ''?> <?= ('links' !== $report && !empty($report)) ? $hide : ''?>/>
    <label for="hide_ignore" <?= ('links' !== $report && !empty($report)) ? $hide : ''?>>Don't show posts that have been ignored&nbsp;&nbsp;&nbsp;</label>
    <input type="checkbox" name="report_options[hide_noindex]" id="hide_noindex" <?=$hide_noindex ? 'checked' : ''?> <?= ('links' !== $report && !empty($report)) ? $hide : ''?>/>
    <label for="hide_noindex" <?= ('links' !== $report && !empty($report)) ? $hide : ''?>>Don't show posts that have been set to noindex&nbsp;&nbsp;&nbsp;</label>
    <input type="checkbox" name="report_options[show_click_traffic]" id="show_click_traffic" <?=$show_click_traffic ? 'checked' : ''?> <?= ('click_details_page' === $report) ? '' :  $hide?>/>
    <label for="show_click_traffic" <?= ('click_details_page' === $report) ? '' :  $hide?>>Show individual click stats&nbsp;&nbsp;&nbsp;</label>
    <input type="checkbox" name="report_options[show_broken_link_type]" id="show_broken_link_type" <?=$show_broken_link_type ? 'checked' : ''?> <?= ('error' === $report) ? '' :  $hide?>/>
    <label for="show_broken_link_type" <?= ('error' === $report) ? '' :  $hide?>>Show if broken link internal or external&nbsp;&nbsp;&nbsp;</label>
    <input type="checkbox" name="report_options[show_broken_link_discovered]" id="show_broken_link_discovered" <?=$show_broken_link_discovered ? 'checked' : ''?> <?= ('error' === $report) ? '' :  $hide?>/>
    <label for="show_broken_link_discovered" <?= ('error' === $report) ? '' :  $hide?>>Show when broken link detected&nbsp;&nbsp;&nbsp;</label>
    <input type="checkbox" name="report_options[enable_tours]" id="enable_tours" <?=$enable_tours ? 'checked' : ''?>/>
    <label for="enable_tours">Enable interactive tours&nbsp;&nbsp;&nbsp;</label>
</fieldset>
<fieldset class="screen-options">
    <legend><?php _e('Pagination', 'wpil'); ?></legend>
    <label for="per_page"><?php _e('Posts per page', 'wpil'); ?></label>
    <input type="number" step="1" min="1" max="999" maxlength="3" name="report_options[per_page]" id="per_page" value="<?=esc_attr($per_page)?>" />
</fieldset>
<a href="<?php echo admin_url('admin.php?page=link_whisper_wizard');?>" class="button button-primary button-large" style="float:right";><?php esc_html_e('Launch One Click Setup', 'wpil');?></a>
<br>
<?=$button?>
<?php wp_nonce_field( 'screen-options-nonce', 'screenoptionnonce', false, false ); ?>
