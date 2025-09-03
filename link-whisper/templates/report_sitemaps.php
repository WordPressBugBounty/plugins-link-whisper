<div class="wrap wpil-report-page wpil_styles">
    <style>
        .swal-overlay.swal-overlay--show-modal{
            z-index: 9999999999999999999; /* make sure that we can always see the popups */
        }
    </style>
    <?php 
        $has_maps = (!empty($table->data) && Wpil_Sitemap::has_sitemap('link_sitemap_inbound')); 
        $size = ($has_maps) ? 'width: calc(100% - 60px); min-height: 800px;': 'width: calc(100% - 60px); min-height: 600px;';
    ?>
    <h1 class="wp-heading-inline wpil-is-tooltipped wpil-no-overlay wpil-no-scale" <?php echo Wpil_Toolbox::generate_tooltip_text('visual-sitemap-report-intro'); ?>><?php _e('Visual Sitemaps','wpil'); ?></h1>
    <hr class="wp-header-end">
    <div id="poststuff">
        <div id="post-body" class="metabox-holder">
            <div id="post-body-content" style="position: relative;">
                <div id="wpil-sitemap-context-menu">
                    <style>
                        /* Style for the context menu */
                        #wpil-sitemap-context-menu {
                            display: none;
                            position: absolute;
                            background-color: #33c7fd;
                            border: 2px solid #5b5b5b;
                            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
                            z-index: 1000;
                            border-radius: 16px;
                        }
                        #wpil-sitemap-context-menu ul {
                            list-style-type: none;
                            padding: 0;
                            margin: 0;
                        }
                        #wpil-sitemap-context-menu ul li {
                            padding: 3px 6px;
                            cursor: pointer;
                            background-color: #33c7fd;
                            color: #ffffff;
                            font-weight: 600;
                            border-radius: 14px;
                            margin: 0 0 1px 0;
                        }
                        #wpil-sitemap-context-menu ul li a{
                            color: #ffffff;
                        }
                        #wpil-sitemap-context-menu ul li:last-of-type {
                            margin:0;
                        }
                        #wpil-sitemap-context-menu ul li:hover,
                        #wpil-sitemap-context-menu ul li a:hover {
                            background-color: #f0f0f0;
                            color: #33c7fd;
                        }
                    </style>
                    <ul>
                        <li class="wpil-sitemap-context-menu-add-outbound wpil-sitemap-context-menu-item node-click-item">Add Outbound Links</li>
                    </ul>
                </div>
                <input id="wpil-object-cache-flush-nonce" type="hidden" value="<?php echo wp_create_nonce('wpil-flush-object-cache'); ?>" />
                <input type="hidden" id="wpil-sitemap-max-input" value="<?php echo (int) ini_get('max_input_vars')?>">

                <input type="hidden" id="wpil-sitemap-outbound-links-url" value="<?php echo admin_url("post.php?post={REPLACE_ID}&action=edit");?>">
                <input type="hidden" id="wpil-sitemap-inbound-links-url" value="<?php echo admin_url("admin.php?{REPLACE_TYPE}_id={REPLACE_ID}&page=link_whisper&type=inbound_suggestions_page"); ?>">
                <input type="hidden" id="wpil-sitemap-create-links-url" value="<?php echo admin_url("admin.php?{REPLACE_TYPE}_id={REPLACE_ID}&source={REPLACE_SOURCE_NODE_ID}&page=link_whisper&type=inbound_suggestions_page"); ?>">
                <?php include_once 'report_tabs.php'; ?>
                <div id="report_sitemaps">
                    <?php if($has_maps){ ?>
                    <br clear="all">
                    <div class="wpil-sitemap-manage-background hidden" style="background: #000; opacity: 0.7; filter: alpha(opacity=70); position: fixed; top: 0; right: 0; bottom: 0; left: 0; z-index: 1000500; height: 32px;"></div>
                    <div class="wpil-sitemap-manage-background hidden" style="background: #000; opacity: 0.7; filter: alpha(opacity=70); position: fixed; top: 0; right: 0; bottom: 0; left: 0; z-index: 100050;"></div>
                    <div class="wpil-sitemap-manage-wrapper hidden" style="position: absolute;
                                                                            width: 75%;
                                                                            top: 0px;
                                                                            left: 10%;
                                                                            z-index: 100060;
                                                                            background: #ffffff;
                                                                            padding: 25px 30px;
                                                                            box-shadow: 0 0 0 transparent;
                                                                            border-radius: 4px;
                                                                            border: 1px solid #8c8f94;
                                                                            color: #2c3338;
                                                                            min-height: 300px;">
                        <span id="wpil-sitemap-manage-close" class="dashicons dashicons-no"></span>
                        <div class="wpil-sitemap-manage-header">
                            <h3 style="font-size: 24px;"><?php _e('Manage Custom Sitemaps', 'wpil'); ?></h3>
                        </div>
                        <div class="wpil-sitemap-management-container">
                            <div class="wpil-sitemap-management-controls">
                                <select>
                                    <!--<option value="0"><?php esc_html_e('Select Sitemap Option', 'wpil'); ?></option>-->
                                    <option value="1"><?php esc_html_e('Create Sitemap From CSV', 'wpil'); ?></option>
                                    <option value="2"><?php esc_html_e('Delete Custom Sitemap', 'wpil'); ?></option>
                                </select>
                            </div>
                            <div class="wpil-sitemap-managment-notice-container hidden" style="text-align: center;">
                                <div class="wpil-sitemap-manage-text" style="display: inline-block;">
                                    <p><?php _e('Please select an action to perform.', 'wpil'); ?></p>
                                </div>
                            </div>
                            <div class="wpil-sitemap-import-container" style="text-align: center;">
                                <div class="wpil-sitemap-import-text" style="display: inline-block;">
                                    <p><?php _e('You can create a custom link sitemap by uploading a CSV file of URL references here.', 'wpil'); ?></p>
                                </div>
                                <br />
                                <div class="wpil-sitemap-import" style="padding: 30px; display: inline-block; margin: auto; background: #f8f8f8; border: 1px solid #c3c4c7;">
                                    <div style="float: left;">
                                        <label for="wpil-sitemap-import-name" style="float: left; font-size:14px; font-weight:600;"><?php _e('Sitemap Name', 'wpil'); ?></label>
                                        <br>
                                        <input type="text" id="wpil-sitemap-import-name" style="float: left; margin: 10px 0 20px;">
                                        <br>
                                        <br>
                                        <label for="wpil-sitemap-import-file" style="float: left; font-size:14px; font-weight:600; margin: 0 0 10px 0;"><?php _e('Upload Sitemap CSV', 'wpil'); ?></label>
                                        <br>
                                        <input type="file" id="wpil-sitemap-import-file" multiple="" accept=".csv" style="padding: 3px 15px 3px 0px;">
                                        <input type="hidden" class="wpil-sitemap-import-nonce" value="<?php echo wp_create_nonce(get_current_user_id() . 'wpil_save_custom_sitemap'); ?>">
                                    </div>
                                    <input type="button" value="<?php esc_attr_e('Import Sitemap CSV', 'wpil'); ?>" class="button-primary btn btn-info wpil-sitemap-csv-import disabled" style="margin: 50px 0 0 0;">
                                </div>
                                <br />
                                <div class="wpil-sitemap-import-text" style="display: inline-block; max-width: 600px;">
                                    <p>(<?php echo sprintf(__('The CSV should have two columns of URLs. The first column is for the URL of the post that will have the links, and the second is for the link URLs. Click %s to download an example CSV file for a sample of the format to use.', 'wpil'), '<a href="' . trailingslashit(WP_INTERNAL_LINKING_PLUGIN_URL) . 'sitemap-import-sample.csv" download>' . __('here', 'wpil') . '</a>'); ?>)</p>
                                </div>
                                <br />
                                <div class="wpil-sitemap-import-text wpil-sitemap-many-rows hidden">
                                    <p><?php echo __('This file is quite large, importing the keywords will take some time and may slow down the page.', 'wpil');?></p>
                                    <p><?php echo __('If you experience errors, please consider breaking the file into several smaller files and upload them individually.', 'wpil'); ?></p>
                                </div>
                            </div>
                            <div class="wpil-sitemap-delete-container hidden" style="text-align: center;">
                                <div class="wpil-sitemap-delete-text" style="display: inline-block;">
                                    <p><?php _e('Please select the custom sitemap that you want to delete.', 'wpil'); ?></p>
                                </div>
                                <br />
                                <div class="wpil-sitemap-delete" style="padding: 30px; display: inline-block; margin: auto; background: #f8f8f8; border: 1px solid #c3c4c7;">
                                    <select>
                                        <option value="0"><?php esc_html_e('Please Select A Sitemap', 'wpil');?></option>
                                        <?php
                                            foreach(Wpil_Sitemap::get_sitemap_list('custom_sitemap') as $sitemap){
                                                echo '<option value="' . $sitemap->sitemap_id . '">' . esc_html($sitemap->sitemap_name) . '</option>';
                                            }
                                        ?>
                                    </select>
                                    <input type="hidden" class="wpil-sitemap-delete-nonce" value="<?php echo wp_create_nonce(get_current_user_id() . 'wpil_delete_custom_sitemap'); ?>">
                                    <input type="button" value="<?php esc_attr_e('Delete Custom Sitemap', 'wpil'); ?>" class="button-primary btn btn-info wpil-sitemap-delete-button disabled" style="margin-left: 20px;">
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php } ?>
                    <?php $table->display(); ?>
                    <div style="position: relative;">
                        <div id="container" style="height: 100%;background: #fff; <?php echo $size; ?> border-radius: 3px;margin-left: 30px;border: 2px solid #2c3338; position:relative;" class="wpil-is-tooltipped wpil-no-scale" data-wpil-tooltip-read-time="5500" <?php echo Wpil_Toolbox::generate_tooltip_text('visual-sitemap-table'); ?>>
                        <?php if(!$has_maps){ ?>
                            <div style="text-align:center; height:100%; width: 100%; min-height: 400px;">
                                <div style="position:absolute; top: calc(50% - 40px); left: calc(50% - 100px)">
                                    <form action='' method="post" id="wpil_generate_link_sitemaps_form">
                                        <input type="hidden" name="nonce" value="<?php echo wp_create_nonce($user->ID . 'wpil_generate_link_sitemaps'); ?>">
                                        <button type="submit" class="button-primary wpil-generate-initial-sitemaps wpil-big-button">Generate Sitemaps</button>
                                    </form>
                                </div>
                            </div>
                        <?php } ?>
                        </div>
                        <div id="wpil-sitemap-graph-menu-container" class="wpil-is-tooltipped wpil-no-scale" <?php echo Wpil_Toolbox::generate_tooltip_text('visual-sitemap-table-settings-menu'); ?>>
                            <div style="font-size: 20px; margin: 0 0 12px 0; user-select: none;"><?php esc_html_e('Sitemap Settings', 'wpil'); ?></div>
                            <div>
                                <div class="wpil-sitemap-graph-setting-container ai-sitemap-setting-container">
                                    <label>
                                        <div style="display: inline-block; margin: 0 5px 0 0"><?php esc_html_e('Show Linked', 'wpil'); ?></div><input id="wpil-sitemap-graph-showExistingLinks" class="wpil-sitemap-graph-setting ai-sitemap-setting" style="margin-top: -2px;" type="checkbox" value="ai-has-link">
                                    </label>
                                </div>
                                <div class="wpil-sitemap-graph-setting-container ai-sitemap-setting-container">
                                    <label>
                                        <div style="display: inline-block; margin: 0 5px 0 0"><?php esc_html_e('Show Unlinked', 'wpil'); ?></div><input id="wpil-sitemap-graph-showPossibleLinks" class="wpil-sitemap-graph-setting ai-sitemap-setting" style="margin-top: -2px;" type="checkbox" value="ai-no-link">
                                    </label>
                                </div>
                                <div>
                                    <label>
                                        <div style="display: inline-block; margin: 0 5px 0 0"><?php esc_html_e('Only Show Main Cluster', 'wpil'); ?></div><input id="wpil-sitemap-graph-cropToLargestConnectedComponent" class="wpil-sitemap-graph-setting" style="margin-top: -2px;" type="checkbox" value="1">
                                    </label>
                                </div>
                                <div>
                                    <label>
                                        <div style="display: inline-block; margin: 0 5px 0 0"><?php esc_html_e('Hide Unconnected Nodes', 'wpil'); ?></div><input id="wpil-sitemap-graph-hideUnconnectedNodes" class="wpil-sitemap-graph-setting" style="margin-top: -2px;" type="checkbox" value="1">
                                    </label>
                                </div>
                                <div>
                                    <label>
                                        <?php esc_html_e('Grouping Strength', 'wpil'); ?>
                                        <br>
                                        <input id="wpil-sitemap-graph-edgeWeightInfluence" class="wpil-sitemap-graph-setting" type="range" min="-0.5" max="2" step="0.1" value="1.2">
                                    </label>
                                </div>
                                <div class="wpil-sitemap-graph-setting-container sitemap-run-status-container">
                                    <label>
                                        Map Generation
                                        <span class="dashicons dashicons-clock wpil-sitemap-graph-run wpil-graph-timed"></span>
                                        <span class="dashicons dashicons-controls-play wpil-sitemap-graph-run wpil-graph-play" style="display:none;"></span>
                                        <span class="dashicons dashicons-controls-pause wpil-sitemap-graph-run wpil-graph-pause" style="display:none;"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    var wpil_admin_url = '<?php echo admin_url()?>';
</script>