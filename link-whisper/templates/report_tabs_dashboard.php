<h2 class="nav-tab-wrapper" style="margin-bottom:1em;">
    <?php $type = (isset($_GET['type']) && !empty($_GET['type'])) ? esc_attr($_GET['type']): ''; ?>
    <div class="wpil-is-tooltipped wpil-no-scale" style="display: inline-block;" <?php echo Wpil_Toolbox::generate_tooltip_text('dashboard-report-tabs'); ?>>
        <a class="nav-tab <?=empty($type)?'nav-tab-active':''?>" id="general-tab" href="<?=admin_url('admin.php?page=link_whisper')?>"><?php  esc_html_e( "Dashboard", 'wpil' )?></a>
        <?php if(WPIL_STATUS_HAS_RUN_SCAN){ ?>
        <?php 
            // get any filter settings from the user's report selection and apply the settings to the Link Report tab url
            $filter_settings = get_user_meta(get_current_user_id(), 'wpil_filter_settings', true);
            $filter_vars = '';
            if(isset($filter_settings['report']) && false){ //TODO: Disabling pending review of persistent filtering. Check back around in version 3.0.0
                $filtering = array();
                if(isset($filter_settings['report']['post_type']) && !empty($filter_settings['report']['post_type'])){
                    $filtering['post_type'] = $filter_settings['report']['post_type'];
                }

                if(isset($filter_settings['report']['category']) && !empty($filter_settings['report']['category'])){
                    $filtering['category'] = $filter_settings['report']['category'];
                }

                if(isset($filter_settings['report']['location']) && !empty($filter_settings['report']['location'])){
                    $filtering['location'] = $filter_settings['report']['location'];
                }

                if(!empty($filtering)){
                    $filter_vars = '&' . http_build_query($filtering);
                }
            } 
        ?>

        <a class="nav-tab wpil-is-tooltipped <?=($type == 'links')?'nav-tab-active':''?>" id="wpil-report-links-tab" href="<?=admin_url('admin.php?page=link_whisper&type=links' . $filter_vars)?>" <?php echo Wpil_Toolbox::generate_tooltip_text('dashboard-link-report-tab'); ?>><?php  esc_html_e( "Links Report", 'wpil' )?> </a>
    <!--    <a class="nav-tab <?=($type == 'link_activity')?'nav-tab-active':''?>" id="home-tab" href="<?=admin_url('admin.php?page=link_whisper&type=link_activity' . $filter_vars)?>"><?php  esc_html_e( "Link Activity Report", 'wpil' )?> </a>-->
        <a class="nav-tab wpil-is-tooltipped <?=($type == 'domains')?'nav-tab-active':''?>" id="wpil-report-domains-tab" href="<?=admin_url('admin.php?page=link_whisper&type=domains')?>" <?php echo Wpil_Toolbox::generate_tooltip_text('dashboard-domain-report-tab'); ?>><?php  esc_html_e( "Domains Report", 'wpil' )?> </a>
        <?php if(empty(get_option('wpil_disable_click_tracking', false))){ ?>
        <a class="nav-tab wpil-is-tooltipped <?=($type == 'clicks')?'nav-tab-active':''?>" id="wpil-report-clicks-tab" href="<?=admin_url('admin.php?page=link_whisper&type=clicks')?>" <?php echo Wpil_Toolbox::generate_tooltip_text('dashboard-click-report-tab'); ?>><?php  esc_html_e( "Clicks Report", 'wpil' )?> </a>
        <?php } ?>
        <a class="nav-tab wpil-is-tooltipped <?=($type == 'error')?'nav-tab-active':''?>" id="wpil-report-broken-links-tab" href="<?=admin_url('admin.php?page=link_whisper&type=error')?>" <?php echo Wpil_Toolbox::generate_tooltip_text('dashboard-broken-links-report-tab'); ?>><?php  esc_html_e( "Broken Links Report", 'wpil' )?> </a>
        <a class="nav-tab wpil-is-tooltipped <?=($type == 'sitemaps')?'nav-tab-active':''?>" id="wpil-report-sitemaps-tab"  href="<?=admin_url('admin.php?page=link_whisper&type=sitemaps')?>" <?php echo Wpil_Toolbox::generate_tooltip_text('dashboard-visual-sitemaps-report-tab'); ?>  data-wpil-tooltip-read-time="4500"><?php  _e( "Visual Sitemaps", 'wpil' )?> </a>
    </div>
    <?php if($type == 'error'){ ?>
    <form action='' method="post" id="wpil_error_reset_data_form">
        <input type="hidden" name="reset" value="1">
        <input type="hidden" name="nonce" value="<?php echo wp_create_nonce($user->ID . 'wpil_error_reset_data'); ?>">
        <a href="javascript:void(0)" class="button-primary csv_button" data-type="error" id="wpil_cvs_export_button" <?php echo isset($_GET['codes']) && !empty($_GET['codes']) ? 'data-codes="' . implode(',', array_map(function($code){ return (int)$code; }, explode(',', $_GET['codes']))) . '"': ''; ?> data-file-name="<?php esc_attr_e('error-code-export.csv', 'wpil'); ?>">Export to CSV</a>
        <button type="submit" class="button-primary"><?php esc_html_e('Scan for Broken Links', 'wpil'); ?></button>
    </form>
    <?php }elseif($type==='clicks'){?>
    <form action='' method="post" id="wpil_clear_clicks_data_form">
        <input type="hidden" name="nonce" value="<?php echo wp_create_nonce($user->ID . 'wpil_clear_clicks_data'); ?>">
        <button type="submit" class="button-primary">Erase Click Data</button>
    </form>
    <?php }elseif($type==='sitemaps'){?>
        <?php if(!empty(Wpil_Sitemap::get_sitemap_list())){ ?>
        <form action='' method="post" id="wpil_generate_link_sitemaps_form">
            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce($user->ID . 'wpil_generate_link_sitemaps'); ?>">
            <button type="submit" class="button-primary">Generate Sitemaps</button>
        </form>
        <?php }
    }?>
    <form action='' method="post" id="wpil_report_reset_data_form">
        <input type="hidden" name="reset_data_nonce" value="<?php echo wp_create_nonce($user->ID . 'wpil_reset_report_data'); ?>">
        <?php if (!empty($_GET['type'])) : ?>
            <a href="javascript:void(0)" class="button-primary csv_button" data-type="<?=esc_attr($_GET['type'])?>" id="wpil_cvs_export_button"  data-file-name="<?php esc_attr_e('detailed-link-export.csv', 'wpil'); ?>">Detailed Export to CSV</a>
            <a href="javascript:void(0)" class="button-primary csv_button" data-type="<?=esc_attr($_GET['type'])?>_summary" id="wpil_cvs_export_button"  data-file-name="<?php esc_attr_e('summary-link-export.csv', 'wpil'); ?>">Summary Export to CSV</a>
            <?php 
                if(!empty(get_transient('wpil_resume_scan_data'))){
                    echo '<a href="javascript:void(0)" class="button-primary wpil-resume-link-scan">' . __('Resume Link Scan', 'wpil') . '</a>';
                }
            ?>
        <?php endif; ?>
        <button type="submit" class="button-primary">Run a Link Scan</button>
    </form>
    <?php } // end link table exist check
    ?>
</h2>