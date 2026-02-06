<?php

if (!class_exists('WP_List_Table')) {
    require_once ( ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

/**
 * Class Wpil_Table_Error
 */
class Wpil_Table_Error extends WP_List_Table
{
    function get_columns()
    {
        $options = get_user_meta(get_current_user_id(), 'report_options', true);

        $checkbox_help_overlay = 'class="wpil-report-header-container wpil-is-tooltipped wpil-no-scale wpil-tooltip-target-parent wpil-tooltip-target.column-checkbox" data-wpil-tooltip-read-time="5500" ' . Wpil_Toolbox::generate_tooltip_text('broken-link-report-table-checkbox-col');
        $post_help_overlay = 'class="wpil-report-header-container wpil-is-tooltipped wpil-no-scale wpil-tooltip-target-parent wpil-tooltip-target.column-post" data-wpil-tooltip-read-time="4500" ' . Wpil_Toolbox::generate_tooltip_text('broken-link-report-table-post-col');
        $post_type_help_overlay = 'class="wpil-report-header-container wpil-is-tooltipped wpil-no-scale wpil-tooltip-target-parent wpil-tooltip-target.column-post_type" data-wpil-tooltip-read-time="3500" ' . Wpil_Toolbox::generate_tooltip_text('broken-link-report-table-post-type-col');
        $url_help_overlay = 'class="wpil-report-header-container wpil-is-tooltipped wpil-no-scale wpil-tooltip-target-parent wpil-tooltip-target.column-url" data-wpil-tooltip-read-time="7500" ' . Wpil_Toolbox::generate_tooltip_text('broken-link-report-table-broken-url-col');
        $anchor_help_overlay = 'class="wpil-report-header-container wpil-is-tooltipped wpil-no-scale wpil-tooltip-target-parent wpil-tooltip-target.column-anchor" data-wpil-tooltip-read-time="3500" ' . Wpil_Toolbox::generate_tooltip_text('broken-link-report-table-anchor-col');
        $sentence_help_overlay = 'class="wpil-report-header-container wpil-is-tooltipped wpil-no-scale wpil-tooltip-target-parent wpil-tooltip-target.column-sentence" data-wpil-tooltip-read-time="4500" ' . Wpil_Toolbox::generate_tooltip_text('broken-link-report-table-sentence-col');
        $type_help_overlay = 'class="wpil-report-header-container wpil-is-tooltipped wpil-no-scale wpil-tooltip-target-parent wpil-tooltip-target.column-type" data-wpil-tooltip-read-time="5500" ' . Wpil_Toolbox::generate_tooltip_text('broken-link-report-table-type-col');
        $code_help_overlay = 'class="wpil-report-header-container wpil-is-tooltipped wpil-no-scale wpil-tooltip-target-parent wpil-tooltip-target.column-code" data-wpil-tooltip-read-time="6500" ' . Wpil_Toolbox::generate_tooltip_text('broken-link-report-table-status-col');
        $created_help_overlay = 'class="wpil-report-header-container wpil-is-tooltipped wpil-no-scale wpil-tooltip-target-parent wpil-tooltip-target.column-created" data-wpil-tooltip-read-time="3500" ' . Wpil_Toolbox::generate_tooltip_text('broken-link-report-table-discovered-col');
        $actions_help_overlay = 'class="wpil-report-header-container wpil-is-tooltipped wpil-no-scale wpil-tooltip-target-parent wpil-tooltip-target.column-actions" data-wpil-tooltip-read-time="4500" ' . Wpil_Toolbox::generate_tooltip_text('broken-link-report-table-delete-col');

        $columns = array(
            'checkbox' => '<div ' . $checkbox_help_overlay  . '><input type="checkbox" id="wpil_check_all_errors" style="display: none" /></div>',
            'post' => '<div ' . $post_help_overlay . '>' . __('Post', 'wpil') . '</div>',
        );

        if (!empty($options['show_type']) && $options['show_type'] == 'on') {
            $columns['post_type'] = '<div ' . $post_type_help_overlay . '>' . __('Post Type', 'wpil') . '</div>';
        }

        $columns = array_merge($columns, array(
            'url'       => '<div ' . $url_help_overlay  . '>' . __('Broken URL', 'wpil') . '</div>',
            'anchor'    => '<div ' . $anchor_help_overlay  . '>' . __('Anchor', 'wpil') . '</div>',
//            'sentence'  => '<div ' . $sentence_help_overlay  . '>' . __('Sentence', 'wpil') . '</div>',
            'type'      => '<div ' . $type_help_overlay  . '>' . __('Type', 'wpil') . '</div>',
            'code'      => '<div ' . $code_help_overlay  . '>' . __('Status', 'wpil') . '</div>',
            'created'   => '<div ' . $created_help_overlay  . '>' . __('Discovered', 'wpil') . '</div>',
//            'actions'   => '<div ' . $actions_help_overlay  . '>' . '' . '</div>',
        ));

        if(empty($options['show_broken_link_type']) || $options['show_broken_link_type'] !== 'on'){
            unset($columns['type']);
        }

        if(empty($options['show_broken_link_discovered']) || $options['show_broken_link_discovered'] !== 'on'){
            unset($columns['created']);
        }

        $columns = array_merge($columns, [
            'wpil-report-actions' => 'Actions'
        ]);

        return $columns;
    }

    function pagination($which){
        if($which === 'bottom' || $which === 'top'){
            $this->setup_pagination($which);
        }
    }

    /**
     * Creates the pagination contorls using the normal WordPress pagination system.
     * Setup so we can call it on demand
     **/
    function setup_pagination($which){
        if ( empty( $this->_pagination_args ) ) {
            return;
        }
    
        $total_items     = $this->_pagination_args['total_items'];
        $total_pages     = $this->_pagination_args['total_pages'];
        $infinite_scroll = false;
        if ( isset( $this->_pagination_args['infinite_scroll'] ) ) {
            $infinite_scroll = $this->_pagination_args['infinite_scroll'];
        }
    
        if ( 'top' === $which && $total_pages > 1 ) {
            $this->screen->render_screen_reader_content( 'heading_pagination' );
        }
    
        $output = '<span class="displaying-num">' . sprintf(
            /* translators: %s: Number of items. */
            _n( '%s item', '%s items', $total_items ),
            number_format_i18n( $total_items )
        ) . '</span>';
    
        $current              = $this->get_pagenum();
        $removable_query_args = wp_removable_query_args();
    
        $current_url = set_url_scheme( 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
    
        $current_url = remove_query_arg( $removable_query_args, $current_url );
    
        $page_links = array();
    
        $total_pages_before = '<span class="paging-input">';
        $total_pages_after  = '</span></span>';
    
        $disable_first = false;
        $disable_last  = false;
        $disable_prev  = false;
        $disable_next  = false;
    
        if ( 1 === $current ) {
            $disable_first = true;
            $disable_prev  = true;
        }
        if ( $total_pages === $current ) {
            $disable_last = true;
            $disable_next = true;
        }
    
        if ( $disable_first ) {
            $page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&laquo;</span>';
        } else {
            $page_links[] = sprintf(
                "<a class='first-page button' href='%s'>" .
                    "<span class='screen-reader-text'>%s</span>" .
                    "<span aria-hidden='true'>%s</span>" .
                '</a>',
                esc_url( remove_query_arg( 'paged', $current_url ) ),
                /* translators: Hidden accessibility text. */
                __( 'First page' ),
                '&laquo;'
            );
        }
    
        if ( $disable_prev ) {
            $page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&lsaquo;</span>';
        } else {
            $page_links[] = sprintf(
                "<a class='prev-page button' href='%s'>" .
                    "<span class='screen-reader-text'>%s</span>" .
                    "<span aria-hidden='true'>%s</span>" .
                '</a>',
                esc_url( add_query_arg( 'paged', max( 1, $current - 1 ), $current_url ) ),
                /* translators: Hidden accessibility text. */
                __( 'Previous page' ),
                '&lsaquo;'
            );
        }
    
        if ( 'bottom' === $which ) {
            $html_current_page  = $current;
            $total_pages_before = sprintf(
                '<span class="screen-reader-text">%s</span>' .
                '<span id="table-paging" class="paging-input">' .
                '<span class="tablenav-paging-text">',
                /* translators: Hidden accessibility text. */
                __( 'Current Page' )
            );
        } else {
            $html_current_page = sprintf(
                '<label for="current-page-selector" class="screen-reader-text">%s</label>' .
                "<input class='current-page' id='current-page-selector' type='text'
                    name='paged' value='%s' size='%d' aria-describedby='table-paging' />" .
                "<span class='tablenav-paging-text'>",
                /* translators: Hidden accessibility text. */
                __( 'Current Page' ),
                $current,
                strlen( $total_pages )
            );
        }
    
        $html_total_pages = sprintf( "<span class='total-pages'>%s</span>", number_format_i18n( $total_pages ) );
    
        $page_links[] = $total_pages_before . sprintf(
            /* translators: 1: Current page, 2: Total pages. */
            _x( '%1$s of %2$s', 'paging' ),
            $html_current_page,
            $html_total_pages
        ) . $total_pages_after;
    
        if ( $disable_next ) {
            $page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&rsaquo;</span>';
        } else {
            $page_links[] = sprintf(
                "<a class='next-page button' href='%s'>" .
                    "<span class='screen-reader-text'>%s</span>" .
                    "<span aria-hidden='true'>%s</span>" .
                '</a>',
                esc_url( add_query_arg( 'paged', min( $total_pages, $current + 1 ), $current_url ) ),
                /* translators: Hidden accessibility text. */
                __( 'Next page' ),
                '&rsaquo;'
            );
        }
    
        if ( $disable_last ) {
            $page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&raquo;</span>';
        } else {
            $page_links[] = sprintf(
                "<a class='last-page button' href='%s'>" .
                    "<span class='screen-reader-text'>%s</span>" .
                    "<span aria-hidden='true'>%s</span>" .
                '</a>',
                esc_url( add_query_arg( 'paged', $total_pages, $current_url ) ),
                /* translators: Hidden accessibility text. */
                __( 'Last page' ),
                '&raquo;'
            );
        }
    
        $pagination_links_class = 'pagination-links';
        if ( ! empty( $infinite_scroll ) ) {
            $pagination_links_class .= ' hide-if-js';
        }
        $output .= "\n<span class='$pagination_links_class'>" . implode( "\n", $page_links ) . '</span>';
    
        if ( $total_pages ) {
            $page_class = $total_pages < 2 ? ' one-page' : '';
        } else {
            $page_class = ' no-pages';
        }
        $this->_pagination = "<div class='tablenav-pages{$page_class}'>$output</div>";
    
        echo $this->_pagination;
    }

    function prepare_items()
    {
        //pagination
        $options = get_user_meta(get_current_user_id(), 'report_options', true);
        $per_page = !empty($options['per_page']) ? $options['per_page'] : 20;
        $page = isset($_REQUEST['paged']) ? (int)$_REQUEST['paged'] : 1;
        $orderby = isset($_REQUEST['orderby']) ? $_REQUEST['orderby'] : '';
        $order = isset($_REQUEST['order']) ? $_REQUEST['order'] : '';
        $post_id = isset($_REQUEST['post_id']) ? (int)$_REQUEST['post_id'] : 0;

        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];
        $data = Wpil_Error::getData($per_page, $page, $orderby, $order, $post_id);
        $this->items = $data['links'];

        $this->set_pagination_args(array(
            'total_items' => $data['total'],
            'per_page' => $per_page,
            'total_pages' => ceil($data['total'] / $per_page)
        ));
    }

    function column_default($item, $column_name)
    {
        switch($column_name) {
            case 'checkbox':
                return '<input type="checkbox" data-id="' . $item->id . '" data-broken-link-id="' . $item->id . '" style="display:none" />';
            case 'url':
                $url = (strpos($item->$column_name, '{{wpil-empty-url') !== false) ? esc_attr__('No URL Found!'): esc_url($item->$column_name);
                $display_link = (strpos($item->$column_name, '{{wpil-empty-url') !== false) ? '<span class="wpil-error-report-url">' . $url . '</span>': '<span class="wpil-error-report-url" href="' . $url . '" target="_blank">' . $url . '</span>';

                return $display_link;
            case 'anchor':
                $view = '';
                if(isset($item->post_id, $item->post_type) && !empty($item->post_id)){
                    $post = new Wpil_Model_Post($item->post_id, $item->post_type);
                    $link = new Wpil_Model_Link([
                        //'link_id' => $item->id,
                        'url' => $item->url,
                        'anchor' => strip_tags($item->{$column_name}),
                        'post' => $post
                    ]);
                    $view = '<a href="' . esc_url(add_query_arg(['wpil_admin_frontend' => '1', 'wpil_admin_frontend_data' => $link->create_scroll_link_data()], $post->getLinks()->view)) . '" title="'.esc_attr__('View On Page','wpil').'" target="_blank"><span class="dashicons dashicons-external" style="position: relative;top: 3px;"></span></a>';
                }
                
                return '<div>'.esc_html($item->{$column_name}).' '.$view.'</div>';
            case 'sentence':
                return '<div>'.esc_html($item->{$column_name}).'</div>';
            case 'created':
                return date(get_option('date_format', 'd M Y') . ' ' . get_option('time_format', '(H:i)'), strtotime($item->created));
            case 'code':
                $class = ($item->code > 403 && $item->code < 500) ? 'code-red': 'code-orange';
                return '<span class="' . $class . '">' . Wpil_Error::getCodeMessage($item->code, true) . '</span>';
            case 'type':
                return $item->internal ? 'internal' : 'external';
            case 'actions':
                return $item->delete_icon;
            case 'post':
                return $item->post_title;
            case 'wpil-report-actions':
                return '<span class="dashicons dashicons-plus"></span>' . $this->build_action_panel($item);
            default:
                return $item->{$column_name};
        }
    }

    function build_action_panel($item){
            $user = wp_get_current_user();

            $object_name = 'Item';
            if($item->post_type === 'post'){
                $post_type =  (!empty(get_post($item->post_id)) && get_post_type($item->post_id)) ? get_post_type($item->post_id): '';
                if(!empty($post_type)){
                    $name = null;
                    $post_object = get_post_type_object($post_type);
                    if(!empty($post_object)){
                        $name = get_post_type_labels($post_object);
                    }

                    $object_name = (!empty($name) && isset($name->singular_name)) ? $name->singular_name: 'Post';
                }else{
                    $object_name = 'Post';
                }
                
            }else{
                $object_name = __('Term', 'wpil');
                // todo: get term taxonomy name
            }

            $post = new Wpil_Model_Post($item->post_id, $item->post_type);

            $actions = [
                'view-post' => '<a class="wpil-action-panel-button" href="'.$post->getViewLink().'" target="_blank">' . sprintf(__('View %s', 'wpil'), $object_name) . '</a>',
                'edit-post' => '<a class="wpil-action-panel-button" href="'.$post->getLinks()->edit.'" target="_blank">' . sprintf(__('Edit %s', 'wpil'), $object_name) . '</a>',
                'ignore-broken-link' => $item->ignore_link
            ];

            $content = 
            '<div class="wpil-report-action-panel-wrapper">
                <div class="wpil-report-action-panel-container">
                    <div class="wpil-panel-actions">
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

    function get_sortable_columns()
    {
        return [
            'post' => ['post', false],
            'type' => ['internal', false],
            'code' => ['code', false],
            'created' => ['created', false],
        ];
    }

    function extra_tablenav( $which ) {
        global $wpdb;

        $post_types = get_post_types(array('public' => true));
        $post_types = array_values($post_types);
        $taxonomies = get_object_taxonomies($post_types);

        $taxes = array();
        $tax_index = array();
        foreach($post_types as $ind_post_type){
            $taxonomies = get_object_taxonomies($ind_post_type);
            if(!empty($taxonomies)){
                foreach($taxonomies as $tax){
                    $taxo = get_taxonomy($tax);
                    if($taxo->hierarchical){
                        $taxes[] = $taxo->name;
                        $tax_index[$ind_post_type][] = array($taxo->name => array());
                    }
                }
            }
        }

        $taxonomies2 = get_categories(array('taxonomy' => $taxes, 'hide_empty' => false));
        $options = '';
        $cat = isset($_GET['category']) ? (int)$_GET['category']: 0;

        if(!empty($taxonomies2)){
            foreach($taxonomies2 as $tax){
                foreach($tax_index as $ind_post_type => $tax_names){
                    foreach($tax_names as $key => $tax_name){
                        if(isset($tax_name[$tax->taxonomy])){
                            $selected = $tax->cat_ID===(int)$cat?' selected':'';
                            $options .= '<option value="' . $tax->cat_ID . '" ' . $selected . ' class="wpil_filter_post_type ' . $ind_post_type . '">' . $tax->name . '</option>';
                        }
                    }
                }
            }
        }

        $codes = [];
        $result = $wpdb->get_results("SELECT DISTINCT code FROM {$wpdb->prefix}wpil_broken_links ORDER BY code ASC");
        foreach ($result as $item) {
            $codes[] = $item->code;
        }
        $current_codes = !empty($_GET['codes']) ? explode(',', $_GET['codes']) : array(6, 7, 28, 404, 451, 500, 503, 925);

        $high_confidence_link_count = count(Wpil_Error::get_high_confidence_broken_links());
        $high_confidence_button_text = (!empty($high_confidence_link_count)) ? sprintf(esc_html__('Delete %d High-Confidence Broken Links', 'wpil'), $high_confidence_link_count): sprintf(esc_html__('No High-Confidence Broken Links', 'wpil'), );

        if ( $which == "top" ){
            ?>
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
                            <div class="wpil-hamburger-filter-title">Filter By Code</div>
                            <div class="wpil-hamburger-filter-fields">
                                <div class="actions bulkactions" id="error_table_code_filter" style="padding: 0 1px 0 0;">
                                    <input type="hidden" class="current-post" value="<?php echo (isset($_GET['post_id']) && !empty($_GET['post_id'])) ? (int) $_GET['post_id']: 0; ?>">
                                    <div class="wpil-is-tooltipped wpil-no-scale" data-wpil-tooltip-read-time="6500" style="display:inline-block;width: 100%" <?php echo Wpil_Toolbox::generate_tooltip_text('broken-link-report-codes');?>>
                                        <div class="codes" style="margin-bottom: 5px;">
                                            <div class="item closed">Status Codes <i class="dashicons dashicons-arrow-down"></i><i class="dashicons dashicons-arrow-up"></i></div>
                                            <?php if(count($codes) > 3){ ?>
                                                <div class="item">
                                                    <input type="checkbox" id="check_all_codes" class="check_all" <?php echo (count($codes) === count($current_codes)) ? 'checked' : '' ?>> <?php esc_html_e('Check All', 'wpil');?>
                                                </div>
                                            <?php } ?>
                                            <?php foreach ($codes as $code) : ?>
                                                <div class="item">
                                                    <input type="checkbox" name="code" data-code="<?= $code ?>" <?= in_array($code, $current_codes) ? 'checked' : '' ?>> <?= Wpil_Error::getCodeMessage($code, true); ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <span class="wpil-filter-submit-button" id="wpil_error_filter" style="width: 90%;"><?php echo "🔎 ";?> Filter</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="wpil-hamburger-filter-option">
                            <div class="wpil-hamburger-filter-title">Filter Posts</div>
                            <div class="wpil-hamburger-filter-fields">
                                <?php
                                $post_type = !empty($_GET['post_type']) ? $_GET['post_type'] : 0;
                                $cat = !empty($_GET['category']) ? $_GET['category'] : 0;
                                ?>
                                <div class="actions bulkactions" id="wpil_error_table_post_filter">
                                    <!--filter by post type-->
                                    <select name="post_type" class="filter-by-type" style="margin-bottom: 12px;">
                                        <option value="0">All types</option>
                                        <?php foreach (Wpil_Settings::getAllTypes() as $type) : ?>
                                            <option value="<?=$type?>" <?=$type===$post_type?' selected':''?>><?=ucfirst($type)?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <select name="category" class="filter-by-type">
                                        <option value="0">All categories</option>
                                        <?php echo $options; ?>
                                        <?php /*foreach (get_categories() as $category) : ?>
                                            <option value="<?=$category->cat_ID?>" <?=$category->cat_ID===(int)$cat?' selected':''?>><?=$category->name?></option>
                                        <?php endforeach; */ ?>
                                    </select>
                                    <!--/filter by post type-->
                                    <span class="wpil-filter-submit-button wpil_error_table_filter_submit" style="display: inline-block; width: 90%;margin: 10px 0 0 0;">🔎 Filter</span>
                                    <input type="hidden" class="post-filter-nonce" value="<?php echo wp_create_nonce(get_current_user_id() . 'wpil_filter_nonce'); ?>">
                                </div>
                            </div>
                        </div>



                    </div>
                </div>
            </div>
            <br>
            <br>
            <?php
        }


        if ($which != "top") {
            return;
        }?>
        <!-- bulk options infos -->
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
}
