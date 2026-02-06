<?php

if (!class_exists('WP_List_Table')) {
    require_once ( ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

/**
 * Class Wpil_Table_Domain
 */
class Wpil_Table_Domain extends WP_List_Table
{
    function get_columns()
    {

        $options = get_user_meta(get_current_user_id(), 'report_options', true);

        $columns = array(
            'host' => __('Domain', 'wpil')
        );

        $posts_help_overlay = 'class="wpil-report-header-container wpil-is-tooltipped wpil-no-scale wpil-tooltip-target-parent wpil-tooltip-target.column-posts" ' . Wpil_Toolbox::generate_tooltip_text('domain-report-table-posts-col');
        $columns['posts'] = '<div ' . $posts_help_overlay . '>' . 
                                __('Posts', 'wpil') . 
                            '</div>';
        
        $links_help_overlay = 'class="wpil-report-header-container wpil-is-tooltipped wpil-no-scale wpil-tooltip-target-parent wpil-tooltip-target.column-links" data-wpil-tooltip-read-time="9500" ' . Wpil_Toolbox::generate_tooltip_text('domain-report-table-links-col');
        $columns['links'] = '<div ' . $links_help_overlay . '>' . 
            __('Links', 'wpil') . 
        '</div>';

        $columns = array_merge($columns, [
            'wpil-report-actions' => 'Actions'
        ]);

        return $columns;
    }

    function build_action_panel($item){
            $user = wp_get_current_user();
            $nonce = wp_create_nonce($user->ID . 'domain_report_nonce');
            $actions = [
//                'configure-attrs' => '<a class="wpil-action-panel-button" href="#" data-domain="'.esc_attr($item['host']).'" data-view-type="configure-attrs" data-nonce="' . $nonce . '" data-activity-panel-title="Configure Domain Attributes">' . __('Configure Domain Attributes', 'wpil') . '</a>',
                'view-posts' => '<a class="wpil-action-panel-button" href="#" data-domain="'.esc_attr($item['host']).'" data-view-type="view-posts" data-nonce="' . $nonce . '" data-activity-panel-title="View Posts">' . __('View Posts', 'wpil') . '</a>',
                'view-links' => '<a class="wpil-action-panel-button" href="#" data-domain="'.esc_attr($item['host']).'" data-view-type="view-links" data-nonce="' . $nonce . '" data-activity-panel-title="View Links">' . __('View Links', 'wpil') . '</a>',
//                'delete-links' => '<a class="wpil-action-panel-button" href="#" data-domain="'.esc_attr($item['host']).'" data-view-type="delete-links" data-nonce="' . $nonce . '">' . __('Delete ALL Links', 'wpil') . '</a>',
            ];

            $content = 
            '<div class="wpil-report-action-panel-wrapper">
                <div class="wpil-report-action-panel-container">
                    <div id="wpil-action-domain-' .esc_attr($item['host']). '" class="wpil-panel-actions">
                        <div class="wpil-panel-actions-header-container" style="display:none">
                            <button class="wpil-panel-close" style="padding: 0px !important;" aria-label="Close panel">✖</button>
                            <h3 class="wpil-panel-actions-header" style="display: none">Link Whisper Actions</h3>
                        </div>
                        ' . implode('', $actions). '
                    </div>
                </div>
            </div>';

            return $content;
    }

    function prepare_items()
    {
        define('WPIL_LOADING_REPORT', true);
        $options = get_user_meta(get_current_user_id(), 'report_options', true);
        $per_page = !empty($options['per_page']) ? $options['per_page'] : 20;
        $page = isset($_REQUEST['paged']) ? (int)$_REQUEST['paged'] : 1;
        $search = !empty($_GET['s']) ? $_GET['s'] : '';
        $search_type = !empty($_GET['domain_search_type']) ? $_GET['domain_search_type'] : 'domain';
        $show_attributes = !isset($options['show_link_attrs']) || $options['show_link_attrs'] === 'on' ? true: false;
        $show_untargeted = isset($_GET['show_untargeted']) && $_GET['show_untargeted'] == 'on' ? 1 : 0;

        $columns = $this->get_columns();
        $hidden = [];
        $sortable = [];
        $this->_column_headers = [$columns, $hidden, $sortable];
        $data = Wpil_Dashboard::getDomainsData($per_page, $page, $search, $search_type, $show_attributes, false, $show_untargeted);
        $this->items = $data['domains'];

        $this->set_pagination_args(array(
            'total_items' => $data['total'],
            'per_page' => $per_page,
            'total_pages' => ceil($data['total'] / $per_page)
        ));
    }

    function column_default($item, $column_name)
    {
        switch($column_name) {
            case 'host':
                return '<a href="'.$item['protocol'] . $item[$column_name].'" target="_blank">'. $item['protocol'] . $item[$column_name].'</a>';
            case 'posts':
                $posts = $item[$column_name];
                $activity_tooltip = esc_attr__('View posts linking to this domain.', 'wpil');

                $list = '<ul class="report_links">';
                $post_count = 0;
                foreach ($posts as $post) {
                    if($post_count > 100){
                        break;
                    }
                    $list .= '<li>'
                                . esc_html($post->getTitle()) . '<br>
                                <a href="' . admin_url('post.php?post=' . (int)$post->id . '&action=edit') . '" target="_blank">[edit]</a> 
                                <a href="' . esc_url($post->getLinks()->view) . '" target="_blank">[view]</a><br><br>
                              </li>';
                    $post_count++;
                }
                $list .= '</ul>';
                $nonce = wp_create_nonce(wp_get_current_user()->ID . 'domain_report_nonce');

                return '<div class="wpil-collapsible-wrapper wpil-activity-activate" data-wpil-collapsible-host="' . $item['host'] . '" data-domain="'.$item['host'].'" data-view-type="view-posts" data-nonce="'.$nonce.'" data-wpil-collapsible-nonce="' . wp_create_nonce(wp_get_current_user()->ID . 'wpil-collapsible-nonce') . '" data-activity-panel-title="'.esc_html__('View Posts', 'wpil').'">
  			                <div class="wpil-collapsible wpil-no-action wpil-collapsible-static wpil-links-count ' . ((!empty($posts)) ? 'wpil-collapsible-has-data': 'wpil-collapsible-no-data') . '">'.count($posts).'<div class="wpil-right-arrow-box wpil-tippy-tooltipped" data-wpil-tooltip-content="'.$activity_tooltip.'"></div></div>
  				            <div class="wpil-content">'.$list.'</div>
  				        </div>';
            case 'links':
                $links = $item[$column_name];
                $activity_tooltip = esc_attr__('View links to this domain.', 'wpil');

                $list = '<ul class="report_links">';
                foreach ($links as $link) {
                    if(empty($link)){
                        continue;
                    }
                    $list .= '<li>
                                <input type="checkbox" class="wpil_link_select" data-post_id="'.$link->post->id.'" data-post_type="'.$link->post->type.'" data-anchor="' . esc_attr(base64_encode($link->anchor)) . '" data-url="'.base64_encode($link->url).'">
                                <div>
                                    <div style="margin: 3px 0;"><b>Post Title:</b> <a href="' . esc_url($link->post->getLinks()->view) . '" target="_blank">' . esc_html($link->post->getTitle()) . '</a></div>
                                    <div style="margin: 3px 0;"><b>URL:</b> <a href="' . esc_url($link->url) . '" target="_blank">' . esc_html($link->url) . '</a></div>
                                    <div style="margin: 3px 0;"><b>Anchor Text:</b> <a href="' . esc_url(add_query_arg(['wpil_admin_frontend' => '1', 'wpil_admin_frontend_data' => $link->create_scroll_link_data()], $link->post->getLinks()->view)) . '" target="_blank">' . esc_html($link->anchor) . ' <span class="dashicons dashicons-external" style="position: relative;top: 3px;"></span></a></div>
                                    ' . Wpil_Report::get_dropdown_icons($link->post, $link);
                                if('related-post-link' !== Wpil_Toolbox::get_link_context($link->link_context)){
                    $list .=        '<a href="#" class="wpil_edit_link" target="_blank">[' . __('Edit URL', 'wpil') . ']</a>
                                    <div class="wpil-domains-report-url-edit-wrapper">
                                        <input class="wpil-domains-report-url-edit" type="text" value="' . esc_attr($link->url) . '">
                                        <button class="wpil-domains-report-url-edit-confirm wpil-domains-edit-link-btn" data-link_id="' . $link->link_id . '" data-post_id="'.$link->post->id.'" data-post_type="'.$link->post->type.'" data-anchor="' . esc_attr($link->anchor) . '" data-url="'.esc_url($link->url).'" data-nonce="' . wp_create_nonce('wpil_report_edit_' . $link->post->id . '_nonce_' . $link->link_id) . '">
                                            <i class="dashicons dashicons-yes"></i>
                                        </button>
                                        <button class="wpil-domains-report-url-edit-cancel wpil-domains-edit-link-btn">
                                            <i class="dashicons dashicons-no"></i>
                                        </button>
                                    </div>';
                                }
                    $list .=   '</div>
                            </li>';
                }
                $list .= '</ul>';

                $delete_bar = (!empty($links)) ? 
                '<div class="update-post-links">
                    <a href="#" class="button-primary wpil-delete-selected-links disabled" style="margin: 0 0 0 10px;" data-nonce="' . wp_create_nonce(wp_get_current_user()->ID . 'delete-selected-links') . '">' . __('Delete Selected', 'wpil') . '</a>
                    <div style="float: right; display: inline-block;"><strong style="margin: 0 10px 0 0;">Select All</strong><input class="wpil-select-all-dropdown-links" style="margin: 0 10px 0 0;" type="checkbox"></div>
                </div>': '';

                $external_focus = '';
                if(isset($_REQUEST['domain_focus']) && !Wpil_Link::isInternal('https://' . $item['host'])){
                    $percent_limit = 60;
                    $external_link_emphasis = Wpil_Dashboard::get_external_link_distribution(0, $item['host']);
                    $external_link_emphasis_percent = 0;
                    if(!empty($external_link_emphasis) && isset($external_link_emphasis->representation)){
                        $external_link_emphasis_percent = round($external_link_emphasis->representation, 2) * 100;
                    }

                    $highlight = '';
                    $fix = '';
                    $diffuse = '';
                    if($external_link_emphasis_percent > $percent_limit){
                        $highlight = 'background: #7645b1;border-radius: 10px;padding: 0 6px;color: #fefefe;font-weight: bold;';
                        $fix = '<span class="wpil-link-relation-indicator" style="position: absolute; left: 60px;background:#ff1e1e;border-radius: 10px;padding: 0 6px;color: #fefefe;font-weight: bold;">' . esc_html__('Fix', 'wpil') . '</span>';
                        $diffuse = ' wpil-links-diffuse';
                    }

                    $external_focus = '<span class="wpil-link-relation-indicator'.$diffuse.'" style="position: absolute; left: 10px;'.$highlight.'">' . $external_link_emphasis_percent . '%</span>' . $fix;
                }
                $nonce = wp_create_nonce(wp_get_current_user()->ID . 'domain_report_nonce');
                return '<div class="wpil-collapsible-wrapper wpil-activity-activate" data-wpil-collapsible-host="' . $item['host'] . '" data-domain="'.$item['host'].'" data-view-type="view-links" data-nonce="'.$nonce.'" data-wpil-collapsible-nonce="' . wp_create_nonce(wp_get_current_user()->ID . 'wpil-collapsible-nonce') . '" data-activity-panel-title="'.esc_html__('View Domain Links', 'wpil').'">
  			                <div class="wpil-collapsible wpil-no-action wpil-collapsible-static wpil-links-count ' . ((!empty($links)) ? 'wpil-collapsible-has-data': 'wpil-collapsible-no-data') . '">'.$external_focus.'<span class="wpil_ul">'.count($links).'</span><div class="wpil-right-arrow-box wpil-tippy-tooltipped" data-wpil-tooltip-content="'.$activity_tooltip.'"></div></div>
  				            <div class="wpil-content">'.$list.'</div>
                            ' . $delete_bar . '
  				        </div>';
            case 'wpil-report-actions':
                return '<span class="dashicons dashicons-plus"></span>' . $this->build_action_panel($item);
            default:
                return print_r($item, true);
        }
    }

    function extra_tablenav( $which ) {
        if ($which == "bottom" && false) {
            ?>
            <div class="alignright actions bulkactions detailed_export">
                <a href="javascript:void(0)" class="button-primary csv_button" data-type="domains" id="wpil_cvs_export_button" data-file-name="<?php esc_attr_e('detailed-domain-export.csv', 'wpil'); ?>">Detailed Export to CSV</a>
            </div>
            <?php
        }

        if ($which != "top") {
            return;
        }
        $user = wp_get_current_user();
        ?>
        <!-- bulk options infos --><!--
        <div class="wpil-bulk-select" id="wpil-bulk-select">
            <button class="wpil-bulk-trigger" type="button" aria-haspopup="listbox" aria-expanded="false" aria-controls="wpil-bulk-menu">Bulk actions
                <svg class="chev" viewBox="0 0 20 20" width="16" height="16" aria-hidden="true">
                <path d="M5 7l5 6 5-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </button>

            <ul class="wpil-bulk-menu" id="wpil-bulk-menu" role="listbox" tabindex="-1" hidden>
                <li role="option" data-value="edit_attributes">Edit Selected Domain Attributes</li>
                <li role="option" data-value="delete_links">Delete ALL Links From Selected Domains</li>
            </ul>
        </div>-->
        <script>
        jQuery(function($) {
            var $root   = $('#wpil-bulk-select');

            // Example consumer:
            $root.on('wpil:bulkActionSelected', function(e, detail) {
                var data = [];
                switch (detail.value) {
                    case 'edit_attributes':
                        $('.wpil-activity-panel').empty();
                        // todo: think about building
                        break;
                    default:
                        break;
                }

                console.log('Bulk action selected:', detail);
            });
        });

        </script>
        <script>
            jQuery(document).ready(function($){
                const $panel   = $('.wpil-activity-panel-wrapper');
                const $overlay = $('.wpil-overlay');

                $(document).on('click', '.wpil-panel-subaction, .wpil-collapsible-wrapper.wpil-activity-activate', function(){
                    $('.wpil-activity-panel').empty();
                    ajaxPullDomainData($(this));
                });

                function animateActivityPanel(title = ''){
                    if ($panel.hasClass('open')){
                        closePanel();
                    } else {
                        if(title){
                            $panel.find('.wpil-activity-panel-header').text(title);
                        }
                        $panel.addClass('active-1');
                        $overlay.addClass('is-open');
                        // slide in to a neat gutter (20px)
                        $panel.animate({ right: '0px' }, 500, function(){ $panel.addClass('open'); });
                    }
                }

                function closePanel(){
                    if(!$panel.hasClass('open')) return;
                        $panel.animate({ right: '-110vw' }, 500, function(){
                        $panel.removeClass('active-1 open');
                        $overlay.removeClass('is-open');
                    });
                }

                // generate suggestions
                $(document).on('click', '.wpil-action-panel-button', function(e){
                    e.preventDefault();
                    var clicked = $(this),
                        domain = clicked.data('domain'),
                        type = clicked.data('view-type'),
                        title = clicked.data('activity-panel-title');

                    // start the ajax
                    switch (type) {
                        case 'configure-attrs':
                        case 'view-posts':
                        case 'view-links':
                            ajaxPullDomainData(clicked);
                            break;
                        case 'delete-links':
                            
                            break;
                        default:
                            break;
                    }

                    $('.wpil-activity-panel').empty();

                    // slide out the panel and hide the actions
                    animateActivityPanel(title);
                    var actionPanel = $('.wpil-report-action-panel-wrapper.open');
                    actionPanel.animate({'right': '-600px'}, 500, function(){
                        actionPanel.removeClass('active-1 open');
                    });
                });

                /**
                 * Pulls the data needed for the specific view
                 **/
                function ajaxPullDomainData(button = null){
                    var clicked = (button) ? button: $(this),
                        domain = clicked.data('domain'),
                        type = clicked.data('view-type'),
                        searchType = $('[name="domain_search_type"]:checked').val(),
                        search = $('#search-search-input').val(),
                        showUntargetted = $('#wpil-domain-show-untargeted').is(':checked') ? '1': '0',
                        nonce = clicked.data('nonce');

                    // check to make sure we have a nonce
                    if(!nonce){
                        // if we don't have one, exit
                        return;
                    }

                    // start calling for the remaining links
                    $.ajax({
                        type: 'POST',
                        url: ajaxurl,
                        data: {
                            action: 'get_domain_report_data',
                            view_type: type,
                            domain: domain,
                            search_type: searchType,
                            search: search,
                            show_untargetted: showUntargetted,
                            nonce: nonce
                        },
                        success: function(response){
                            console.log(response);
                            if(!isJSON(response)){
                                response = extractAndValidateJSON(response, ['error', 'info', 'success']);
                            }

                            // if there was an error
                            if(response.error){
                                // output the error message
                                wpil_swal(response.error.title, response.error.text, 'error');
                                // and exit
                                return;
                            }

                            // if there was a notice
                            if(response.info){
                                // output the notice message
                                wpil_swal(response.info.title, response.info.text, 'info');
                                // and exit
                                return;
                            }

                            // 
                            if(response.success){
                                // 
                                $('.wpil-activity-panel').empty().append(response.success.table);
                            }
                        },
                        error: function(jqXHR, textStatus, errorThrown){
                            console.log({jqXHR, textStatus, errorThrown});
                        }
                    });
                }
            });
        </script>
        <div class="wpil-overlay" aria-hidden="true"></div>
        <div class="wpil-activity-panel-wrapper">
            <input type="hidden" id="wpil-get-manual-suggestions">
            <div class="wpil-activity-panel-container">
                <div class="wpil-activity-panel-header-container">
                    <button class="wpil-panel-close" style="padding: 0px !important;" aria-label="Close panel" style="background: none">✖</button>
                    <h3 class="wpil-activity-panel-header" style="top: 0px;"></h3>
                </div>
                <div class="wpil-activity-panel">
                </div>
            </div>
        </div>
        <?php 


    }

    public function search_box( $text, $input_id ) {
        if ( empty( $_REQUEST['s'] ) && ! $this->has_items() ) {
//            return;
        }

        $input_id = $input_id . '-search-input';

        if(!empty($_REQUEST['orderby'])){
            echo '<input type="hidden" name="orderby" value="' . esc_attr($_REQUEST['orderby']) . '" />';
        }
        if(!empty($_REQUEST['order'])){
            echo '<input type="hidden" name="order" value="' . esc_attr($_REQUEST['order']) . '" />';
        }
        if(!empty($_REQUEST['post_mime_type'])){
            echo '<input type="hidden" name="post_mime_type" value="' . esc_attr($_REQUEST['post_mime_type']) . '" />';
        }
        if(!empty($_REQUEST['detached'])){
            echo '<input type="hidden" name="detached" value="' . esc_attr($_REQUEST['detached']) . '" />';
        }

        $search_type = isset($_REQUEST['domain_search_type']) && !empty($_REQUEST['domain_search_type']) ? $_REQUEST['domain_search_type']: 'domain';
        
        $show_untargeted = (isset($_REQUEST['show_untargeted']) && !empty($_REQUEST['show_untargeted'])) ? true: false;
        ?>
        <div class="wpil-hamburger-filter-container" style="display:flex; flex-direction: column;">
            <div class="wpil-hamburger-filter-option">
                <div class="wpil-hamburger-filter-title">Search Domains</div>
                <div class="wpil-hamburger-filter-fields">
                    <p class="search-box wpil-is-tooltipped wpil-no-scale wpil-tooltip-no-position" data-wpil-tooltip-read-time="4500" <?php echo Wpil_Toolbox::generate_tooltip_text('domain-report-search'); ?>>
                        <label class="screen-reader-text" for="<?php echo esc_attr($input_id); ?>"><?php echo $text; ?>:</label>
                        <input type="search" id="<?php echo esc_attr($input_id); ?>" name="s" value="<?php _admin_search_query(); ?>" />
                            <?php submit_button("🔎 " . $text, '', '', false, array('id' => 'search-submit')); ?>
                        <br />
                        <span>
                            <span style="display: inline-block; float: left;">
                                <span class="wpil-is-tooltipped wpil-no-scale wpil-tooltip-no-position" style="display:inline-block" data-wpil-tooltip-read-time="3500" <?php echo Wpil_Toolbox::generate_tooltip_text('domain-report-search-domains'); ?>>
                                    <label class="" for="wpil-domain-search-host"><?php esc_html_e('Search by Domain', 'wpil'); ?></label>
                                    <input type="radio" id="wpil-domain-search-host" name="domain_search_type" value="domain" <?php checked($search_type, 'domain');?>>
                                </span>
                                <br>
                                <span class="wpil-is-tooltipped wpil-no-scale wpil-tooltip-no-position" style="display:inline-block" data-wpil-tooltip-read-time="3500" <?php echo Wpil_Toolbox::generate_tooltip_text('domain-report-search-links'); ?>>
                                    <label class="" for="wpil-domain-search-path"><?php esc_html_e('Search by URL', 'wpil'); ?></label>
                                    <input type="radio" id="wpil-domain-search-path" name="domain_search_type" value="links" <?php checked($search_type, 'links');?>>
                                </span>
                                <br>
                                <span class="wpil-is-tooltipped wpil-no-scale wpil-tooltip-no-position" style="display:inline-block" data-wpil-tooltip-read-time="3500" <?php echo Wpil_Toolbox::generate_tooltip_text('domain-report-search-show-untargetted'); ?>>
                                    <label class="" for="wpil-domain-show-untargeted"><?php esc_html_e('Show Untargeted Links', 'wpil'); ?></label>
                                    <input type="checkbox" id="wpil-domain-show-untargeted" class="wpil-tippy-tooltipped" data-wpil-tooltip-content="<?php esc_attr_e('"Show Untargeted Links" tells the report to show internal links that aren\'t pointing to known posts.', 'wpil')?>" name="show_untargeted" <?php checked($show_untargeted);?>>
                                </span>
                            </span>
                        </span>
                    </p>
                </div>
            </div>
            <div class="wpil-hamburger-filter-option">
                <div class="wpil-hamburger-filter-title">Export Links</div>
                <div class="wpil-hamburger-filter-fields">
                <?php if (!empty($_GET['type'])) : ?>
                    <div class="wpil-is-tooltipped wpil-no-scale wpil-tooltip-no-position" style="display:inline-block" data-wpil-tooltip-read-time="4500">
                        <a href="javascript:void(0)" class="wpil-filter-submit-button csv_button" data-type="<?=esc_attr($_GET['type'])?>" id="wpil_cvs_export_button" style="text-align:left" data-file-name="<?php esc_attr_e('detailed-link-export.csv', 'wpil'); ?>">📤 Detailed Export to CSV</a>
                        <a href="javascript:void(0)" class="wpil-filter-submit-button csv_button" data-type="<?=esc_attr($_GET['type'])?>_summary" id="wpil_cvs_export_button" style="text-align:left" data-file-name="<?php esc_attr_e('summary-link-export.csv', 'wpil'); ?>">📤 Summary Export to CSV</a>
                    </div>
                    <?php 
                        if(!empty(get_transient('wpil_resume_scan_data'))){
                            echo '<a href="javascript:void(0)" class="wpil-filter-submit-button wpil-resume-link-scan">' . __('Resume Link Scan', 'wpil') . '</a>';
                        }
                    ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

        <?php
    }
}
