<div class="wrap wpil-report-page wpil_styles">
    <style type="text/css">
        .wpil-content{
            padding: 0px;
        }
        <?php
            $sources = Wpil_TargetKeyword::get_active_keyword_sources();
            $num = count($sources);
            $direction = !is_rtl() ? 'right' : 'left';
            switch ($num) {
                case '8':
                    ?>
                    tr .wpil-dropdown-column .wpil-keyword-col-0 .wpil-content,
                    tr .wpil-dropdown-column .wpil-keyword-col-0 .update-post-keywords{
                        width: calc(800% + 140px);
                    }

                    tr .wpil-dropdown-column .wpil-keyword-col-1 .wpil-content,
                    tr .wpil-dropdown-column .wpil-keyword-col-1 .update-post-keywords{
                        width: calc(800% + 140px);
                        position: relative;
                        <?php echo esc_attr($direction); ?>: calc(100% + 20px);
                    }

                    tr .wpil-dropdown-column .wpil-keyword-col-2 .wpil-content,
                    tr .wpil-dropdown-column .wpil-keyword-col-2 .update-post-keywords{
                        width: calc(800% + 140px);
                        position: relative;
                        <?php echo esc_attr($direction); ?>: calc(200% + 40px);
                    }

                    tr .wpil-dropdown-column .wpil-keyword-col-3 .wpil-content,
                    tr .wpil-dropdown-column .wpil-keyword-col-3 .update-post-keywords{
                        width: calc(800% + 140px);
                        position: relative;
                        <?php echo esc_attr($direction); ?>: calc(300% + 60px);
                    }

                    tr .wpil-dropdown-column .wpil-keyword-col-4 .wpil-content,
                    tr .wpil-dropdown-column .wpil-keyword-col-4 .update-post-keywords{
                        width: calc(800% + 140px);
                        position: relative;
                        <?php echo esc_attr($direction); ?>: calc(400% + 80px);
                    }

                    tr .wpil-dropdown-column .wpil-keyword-col-5 .wpil-content,
                    tr .wpil-dropdown-column .wpil-keyword-col-5 .update-post-keywords{
                        width: calc(800% + 140px);
                        position: relative;
                        <?php echo esc_attr($direction); ?>: calc(500% + 100px);
                    }
                    tr .wpil-dropdown-column .wpil-keyword-col-6 .wpil-content,
                    tr .wpil-dropdown-column .wpil-keyword-col-6 .update-post-keywords{
                        width: calc(800% + 140px);
                        position: relative;
                        <?php echo esc_attr($direction); ?>: calc(600% + 120px);
                    }
                    tr .wpil-dropdown-column .wpil-keyword-col-7 .wpil-content,
                    tr .wpil-dropdown-column .wpil-keyword-col-7 .create-post-keywords{
                        width: calc(800% + 140px);
                        position: relative;
                        <?php echo esc_attr($direction); ?>: calc(700% + 140px);
                    }
                    <?php
                break;
                case '7':
                    ?>
                    tr .wpil-dropdown-column .wpil-keyword-col-0 .wpil-content,
                    tr .wpil-dropdown-column .wpil-keyword-col-0 .update-post-keywords{
                        width: calc(700% + 120px);
                    }

                    tr .wpil-dropdown-column .wpil-keyword-col-1 .wpil-content,
                    tr .wpil-dropdown-column .wpil-keyword-col-1 .update-post-keywords{
                        width: calc(700% + 120px);
                        position: relative;
                        <?php echo esc_attr($direction); ?>: calc(100% + 20px);
                    }

                    tr .wpil-dropdown-column .wpil-keyword-col-2 .wpil-content,
                    tr .wpil-dropdown-column .wpil-keyword-col-2 .update-post-keywords{
                        width: calc(700% + 120px);
                        position: relative;
                        <?php echo esc_attr($direction); ?>: calc(200% + 40px);
                    }

                    tr .wpil-dropdown-column .wpil-keyword-col-3 .wpil-content,
                    tr .wpil-dropdown-column .wpil-keyword-col-3 .update-post-keywords{
                        width: calc(700% + 120px);
                        position: relative;
                        <?php echo esc_attr($direction); ?>: calc(300% + 60px);
                    }

                    tr .wpil-dropdown-column .wpil-keyword-col-4 .wpil-content,
                    tr .wpil-dropdown-column .wpil-keyword-col-4 .update-post-keywords{
                        width: calc(700% + 120px);
                        position: relative;
                        <?php echo esc_attr($direction); ?>: calc(400% + 80px);
                    }

                    tr .wpil-dropdown-column .wpil-keyword-col-5 .wpil-content,
                    tr .wpil-dropdown-column .wpil-keyword-col-5 .update-post-keywords{
                        width: calc(700% + 120px);
                        position: relative;
                        <?php echo esc_attr($direction); ?>: calc(500% + 100px);
                    }
                    tr .wpil-dropdown-column .wpil-keyword-col-6 .wpil-content,
                    tr .wpil-dropdown-column .wpil-keyword-col-6 .create-post-keywords{
                        width: calc(700% + 120px);
                        position: relative;
                        <?php echo esc_attr($direction); ?>: calc(600% + 120px);
                    }
                    <?php
                break;
                case '6':
                    ?>
                    tr .wpil-dropdown-column .wpil-keyword-col-0 .wpil-content,
                    tr .wpil-dropdown-column .wpil-keyword-col-0 .update-post-keywords{
                        width: calc(600% + 100px);
                    }

                    tr .wpil-dropdown-column .wpil-keyword-col-1 .wpil-content,
                    tr .wpil-dropdown-column .wpil-keyword-col-1 .update-post-keywords{
                        width: calc(600% + 100px);
                        position: relative;
                        <?php echo esc_attr($direction); ?>: calc(100% + 20px);
                    }

                    tr .wpil-dropdown-column .wpil-keyword-col-2 .wpil-content,
                    tr .wpil-dropdown-column .wpil-keyword-col-2 .update-post-keywords{
                        width: calc(600% + 100px);
                        position: relative;
                        <?php echo esc_attr($direction); ?>: calc(200% + 40px);
                    }

                    tr .wpil-dropdown-column .wpil-keyword-col-3 .wpil-content,
                    tr .wpil-dropdown-column .wpil-keyword-col-3 .update-post-keywords{
                        width: calc(600% + 100px);
                        position: relative;
                        <?php echo esc_attr($direction); ?>: calc(300% + 60px);
                    }

                    tr .wpil-dropdown-column .wpil-keyword-col-4 .wpil-content,
                    tr .wpil-dropdown-column .wpil-keyword-col-4 .update-post-keywords{
                        width: calc(600% + 100px);
                        position: relative;
                        <?php echo esc_attr($direction); ?>: calc(400% + 80px);
                    }

                    tr .wpil-dropdown-column .wpil-keyword-col-5 .wpil-content,
                    tr .wpil-dropdown-column .wpil-keyword-col-5 .create-post-keywords{
                        width: calc(600% + 100px);
                        position: relative;
                        <?php echo esc_attr($direction); ?>: calc(500% + 100px);
                    }
                    <?php
                break;
                case '5':
                    ?>
                    tr .wpil-dropdown-column .wpil-keyword-col-0 .wpil-content,
                    tr .wpil-dropdown-column .wpil-keyword-col-0 .update-post-keywords{
                        width: calc(500% + 80px);
                    }

                    tr .wpil-dropdown-column .wpil-keyword-col-1 .wpil-content,
                    tr .wpil-dropdown-column .wpil-keyword-col-1 .update-post-keywords{
                        width: calc(500% + 80px);
                        position: relative;
                        <?php echo esc_attr($direction); ?>: calc(100% + 20px);
                    }

                    tr .wpil-dropdown-column .wpil-keyword-col-2 .wpil-content,
                    tr .wpil-dropdown-column .wpil-keyword-col-2 .update-post-keywords{
                        width: calc(500% + 80px);
                        position: relative;
                        <?php echo esc_attr($direction); ?>: calc(200% + 40px);
                    }

                    tr .wpil-dropdown-column .wpil-keyword-col-3 .wpil-content,
                    tr .wpil-dropdown-column .wpil-keyword-col-3 .update-post-keywords{
                        width: calc(500% + 80px);
                        position: relative;
                        <?php echo esc_attr($direction); ?>: calc(300% + 60px);
                    }

                    tr .wpil-dropdown-column .wpil-keyword-col-4 .wpil-content,
                    tr .wpil-dropdown-column .wpil-keyword-col-4 .create-post-keywords{
                        width: calc(500% + 80px);
                        position: relative;
                        <?php echo esc_attr($direction); ?>: calc(400% + 80px);
                    }
                    <?php
                break;
                case '4':
                    ?>
                    tr .wpil-dropdown-column .wpil-keyword-col-0 .wpil-content,
                    tr .wpil-dropdown-column .wpil-keyword-col-0 .update-post-keywords{
                        width: calc(400% + 60px);
                    }

                    tr .wpil-dropdown-column .wpil-keyword-col-1 .wpil-content,
                    tr .wpil-dropdown-column .wpil-keyword-col-1 .update-post-keywords{
                        width: calc(400% + 60px);
                        position: relative;
                        <?php echo esc_attr($direction); ?>: calc(100% + 20px);
                    }

                    tr .wpil-dropdown-column .wpil-keyword-col-2 .wpil-content,
                    tr .wpil-dropdown-column .wpil-keyword-col-2 .update-post-keywords{
                        width: calc(400% + 60px);
                        position: relative;
                        <?php echo esc_attr($direction); ?>: calc(200% + 40px);
                    }

                    tr .wpil-dropdown-column .wpil-keyword-col-3 .wpil-content,
                    tr .wpil-dropdown-column .wpil-keyword-col-3 .create-post-keywords{
                        width: calc(400% + 60px);
                        position: relative;
                        <?php echo esc_attr($direction); ?>: calc(300% + 60px);
                    }
                    <?php
                break;
                case '3':
                    ?>
                    tr .wpil-dropdown-column .wpil-keyword-col-0 .wpil-content,
                    tr .wpil-dropdown-column .wpil-keyword-col-0 .update-post-keywords{
                        width: calc(300% + 40px);
                    }

                    tr .wpil-dropdown-column .wpil-keyword-col-1 .wpil-content,
                    tr .wpil-dropdown-column .wpil-keyword-col-1 .update-post-keywords{
                        width: calc(300% + 40px);
                        position: relative;
                        <?php echo esc_attr($direction); ?>: calc(100% + 20px);
                    }

                    tr .wpil-dropdown-column .wpil-keyword-col-2 .wpil-content,
                    tr .wpil-dropdown-column .wpil-keyword-col-2 .create-post-keywords{
                        width: calc(300% + 40px);
                        position: relative;
                        <?php echo esc_attr($direction); ?>: calc(200% + 40px);
                    }
                    <?php
                break;
                case '2':
                    ?>
                    tr .wpil-dropdown-column .wpil-keyword-col-0 .wpil-content,
                    tr .wpil-dropdown-column .wpil-keyword-col-0 .update-post-keywords{
                        width: calc(200% + 20px);
                        position: relative;
                        <?php echo esc_attr($direction); ?>: 0;
                    }

                    tr .wpil-dropdown-column .wpil-keyword-col-1 .wpil-content,
                    tr .wpil-dropdown-column .wpil-keyword-col-1 .create-post-keywords{
                        width: calc(200% + 20px);
                        position: relative;
                        <?php echo esc_attr($direction); ?>: calc(100% + 20px);
                    }
                    <?php
                break;
                case '1':
                    ?>
                    .column-custom .wpil-content{
                        width: calc(100% - 10px);
                    }
                    .column-custom .create-post-keywords{
                        width: 100%;
                    }
                    <?php
                break;
            }
            ?>
    </style>
    <?=Wpil_Base::showVersion()?>
    <h1 class="wp-heading-inline"><?php esc_html_e('Target Keywords', 'wpil'); ?></h1>
    <hr class="wp-header-end">
    <div id="poststuff">
        <div id="post-body" class="metabox-holder">
            <div id="post-body-content" style="position: relative;">
                <div id="wpil_target_keyword_table">
                    <div class="wpil_help" style="float:right;position:relative">
                        <i class="dashicons dashicons-editor-help"></i>
                        <div style="right: 0px; top: 100px;">
                            <?php esc_html_e('Clicking Refresh Target Keywords will clear and re-import any Yoast or Rank Math keywords, and all inactive Google Search Console keywords.', 'wpil'); ?>
                            <br />
                            <br />
                            <?php esc_html_e('If you have just installed Link Whisper, authorized the GSC connect, or don\'t see Yoast/Rank Math keywords, please click this button.', 'wpil'); ?>
                        </div>
                    </div>
                    <a href="javascript:void(0)" class="button-primary" id="wpil_target_keyword_reset_button"><?php esc_html_e('Refresh Target Keywords', 'wpil'); ?></a>
                    <br>
                    <br>
                    <br>
                    <br>
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
                            <form>
                                <input type="hidden" name="page" value="link_whisper_target_keywords" />
                                <?php $table->search_box('Search', 'search'); ?>
                            </form>
                            <?php
                            $post_type = Wpil_Filter::linksPostType();
                            $post_type = !empty($post_type) ? $post_type : 0;
                            ?>
                            <div class="actions bulkactions" id="wpil_links_table_filter">
                                <select name="keyword_post_type">
                                    <option value="0"><?php esc_html_e('All Post Types', 'wpil'); ?></option>
                                    <?php foreach (Wpil_Settings::getAllTypes() as $type) : ?>
                                        <option value="<?=$type?>" <?=$type===$post_type?' selected':''?>><?=ucfirst($type)?></option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="button-primary">Filter</span>
                                <input type="hidden" class="post-filter-nonce" value="<?php echo wp_create_nonce(get_current_user_id() . 'wpil_filter_nonce'); ?>">
                            </div>

                            <div style="clear: both"></div>
                            <input type="hidden" id="wpil_target_keyword_gsc_authenticated" value="<?php echo (Wpil_Settings::HasGSCCredentials()) ? 1: 0; ?>">
                            <input type="hidden" id="wpil_target_keyword_reset_notice" value="<?php esc_attr_e('Please confirm refreshing the target keywords. If you\'ve authenticated the connection to Google Search Console, this will refresh the keyword data.', 'wpil'); ?>" >
                            <input type="hidden" id="wpil_target_keyword_gsc_not_authtext_a" value="<?php esc_attr_e('Link Whisper can not connect to Google Search Console because it has not been authorized yet.', 'wpil'); ?>">
                            <input type="hidden" id="wpil_target_keyword_gsc_not_authtext_b" value="<?php esc_attr_e('Please go to the Link Whisper Settings and authorize access.', 'wpil'); ?>">
                        </div>
                    </div>
                    <?php if (!$reset) : ?>
                        <div class="table">
                            <?php $table->display(); ?>
                        </div>
                    <?php endif; ?>
                    <div class="progress" <?=$reset?'style="display:block"':''?>>
                        <h4 class="progress_panel_msg"><?php esc_html_e('Synchronizing your data..','wpil'); ?></h4>
                        <div class="progress_panel loader">
                            <div class="progress_count"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    var wpil_target_keyword_nonce = '<?php echo wp_create_nonce($user->ID . 'wpil_target_keyword')?>';
    var is_wpil_target_keyword_reset = <?php echo $reset?'true':'false'?>;
    var wpil_admin_url = '<?php echo admin_url()?>';
</script>