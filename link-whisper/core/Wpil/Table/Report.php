<?php

if (!class_exists('WP_List_Table')) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Wpil_Table_Report extends WP_List_Table
{

    function __construct()
    {
        parent::__construct(array(
            'singular' => __('Linking Stats', 'wpil'),
            'plural' => __('Linking Stats', 'wpil'),
            'ajax' => false
        ));

        $this->prepare_items();
    }

    function column_default($item, $column_name)
    {
        if ($column_name == 'post_type') {
            return $item['post']->getType();
        }

        if (!array_key_exists($column_name, $item)) {
            if($column_name === 'wpil-report-actions'){
                return '<span class="dashicons dashicons-plus"></span>' . $this->build_action_panel($item);
            }

            if($column_name === 'checkall'){
                return '<input class="wpil-report-post-checkbox" type="checkbox" data-post-id="'.$item['post']->get_pid().'">';
            }
            return "<i>(not set)</i>";
        }

        $v = $item[$column_name];
        if (!$v) {
            $v = 0;
        }

        $v_num = $v;

        $post_id = $item['post']->id;
        $post_type = $item['post']->type;
        if (in_array($column_name, Wpil_Report::$meta_keys)) {
            $opts = [];
            $opts['target'] = '_blank';
            $opts['style'] = 'text-decoration: underline';

            $opts['data-wpil-report-post-id'] = $post_id;
            $opts['data-wpil-report-type'] = $column_name;
            $opts['data-wpil-report'] = 1;

            $v = "<span class='wpil_ul'>$v</span>";
            $secondary_content = '';
            $searching_for_links = '
                <div>
                    <table class="wp-list-table widefat fixed posts tbl_keywords_x js-table wpil-outbound-links wpil-is-tooltipped wpil-no-scale wpil-tooltip-no-position" id="tbl_keywords">
                        <tbody>
                            <tr>
                                <td>' . esc_html__('Searching for Quick Link Suggestions...', 'wpil') . '</td>
                            </tr>
                        </tbody>
                    </table>
                </div>';

            switch ($column_name) {
                case WPIL_LINKS_INBOUND_INTERNAL_COUNT:
                    //$v = "<div class='inbound-link-count'>&#x2799;";
                    $v = "<div class='inbound-link-count'>";
                    $links_data = $item['post']->getInboundInternalLinks();
                    $title = __('Inbound Internal', 'wpil');
                    break;

                case WPIL_LINKS_OUTBOUND_EXTERNAL_COUNT:
                    //$v = "&#x279A;";
                    $v = "";
                    $links_data = $item['post']->getOutboundExternalLinks();
                    $title = __('Outbound External', 'wpil');
                    break;

                case WPIL_LINKS_OUTBOUND_INTERNAL_COUNT:
                    //$v = "<div class='outbound-internal-link-count'>&#x2799;";
                    $v = "<div class='outbound-internal-link-count'>";
                    $links_data = $item['post']->getOutboundInternalLinks();
                    $title = __('Outbound Internal', 'wpil');
                    break;
            }


            if ($v_num > 0 || (WPIL_LINKS_INBOUND_INTERNAL_COUNT == $column_name || WPIL_LINKS_OUTBOUND_INTERNAL_COUNT == $column_name) && (isset($_REQUEST['link_density']))) {

            } else {
                $v = "<div title='" . esc_attr($title) . "' style='margin:0px; text-align: center; padding: 5px'>0 $v</div>";
            }

            $get_all_links = Wpil_Settings::showAllLinks();
            $links_diffuse_container = '';
            $link_type = 'inbound-internal';
            $panel_title = 'Inbound Internal Links';
            $activity_tooltip = '';
            $anchor_words = array();

            if ($v_num > 0 || (WPIL_LINKS_INBOUND_INTERNAL_COUNT == $column_name || WPIL_LINKS_OUTBOUND_INTERNAL_COUNT == $column_name) && (isset($_REQUEST['link_density']))) {
                $rep = '';

                if (is_array($links_data)) {
                    $rep .= '<ul class="report_links">';

                    switch ($column_name) {
                        case 'wpil_links_inbound_internal_count':
                            $link_type = 'inbound-internal';
                            $panel_title = esc_attr__('Inbound Internal Links', 'wpil');
                            $activity_tooltip = esc_attr__('View Inbound Internal Links', 'wpil');
                            $relation_counter = array('on' => 0, 'off' => 0);
                            $count = 0;
                            foreach ($links_data as $link) {
                                    $count++;
                                    $related = '';
                                    
                                    if(empty($link->get_ai_relation_percent(true)) || $link->get_ai_relation_percent(true) > 50){
                                        $relation_counter['on']++;
                                    }else{
                                        $related = 'ai-not-related';
                                        $relation_counter['off']++;
                                    }
                                    
                                    if($count > 0){
                                        continue;
                                    }

                                    if (!empty($link->post)) {
                                        $rep .= '<li class="'.$related.'">
                                                    <input type="checkbox" class="wpil_link_select" data-post_id="'.$link->post->id.'" data-post_type="'.$link->post->type.'" data-anchor="'.base64_encode($link->anchor).'" data-url="'.base64_encode($link->url).'">
                                                    <div>
                                                        <div style="margin: 3px 0;"><b>Origin Post Title:</b> ' . esc_html($link->post->getTitle()) . '</div>
                                                        <div style="margin: 3px 0;"><b>Anchor Text:</b> <a href="' . esc_url(add_query_arg(['wpil_admin_frontend' => '1', 'wpil_admin_frontend_data' => $link->create_scroll_link_data()], $link->post->getLinks()->view)) . '" target="_blank">' . esc_html($link->anchor) . ' <span class="dashicons dashicons-external" style="position: relative;top: 3px;"></span></a></div>
                                                        <div style="margin: 3px 0;"><b>Content Relatedness:</b> ' . esc_html($link->get_ai_relation_percent()) . '</div>';
                                        $rep .= ($get_all_links) ? '<div style="margin: 3px 0;"><b>Link Location:</b> ' . $link->location . '</div>' : '';
                                        $rep .= Wpil_Report::get_dropdown_icons($link->post, $link, 'inbound-internal');
                                        $rep .=         '<a href="' . admin_url('post.php?post=' . $link->post->id . '&action=edit') . '" target="_blank">[edit]</a> 
                                                        <a href="' . esc_url($link->post->getLinks()->view) . '" target="_blank">[view]</a>
                                                        <br>
                                                    </div>
                                                </li>';
                                    } else {
                                        $rep .= '<li><div style="margin: 3px 0;"><b>Anchor Text:</b> ' . esc_html(strip_tags($link->anchor)) . '</div></li>';
                                    }
                            }

                            $link_relation = 0;
                            $links_diffuse = '';/*
                            if(!empty($relation_counter['off'])){
                                $link_relation = round($relation_counter['off'] / $count, 2) * 100;
                                if($link_relation > 40){
                                    $links_diffuse = 'wpil-links-diffuse';
                                }
                            }*/

                            $ai_suggestions = '';
                            if(false && Wpil_Settings::get_use_ai_suggestions() && !Wpil_Settings::get_disable_ai_anchor_building()){ // TODO: Disabling pending rework
                                $suggestions = Wpil_Report::get_ai_detected_suggestions($item['post'], true, true);
                                $ai_suggestions = (!empty($suggestions)) ? '<span class="wpil-no-action" style="display: inline-block; margin-left: 10px; text-decoration: underline;" title="'. esc_attr__('AI Detected Linking Opportunities', 'wpil') .'"><span class="wpil-no-action">' . $suggestions . '</span><span class="wpil-no-action"><span class="dashicons dashicons-superhero wpil-no-action"></span></span></span>': '';
                            }

                            $add_highlight_class = '';
                            $highlight_title = '';
                            $increase_count = '';
                            $inbound_suggestion_data = '';
                            if(isset($_REQUEST['link_density']) && empty($count)){
                                $add_highlight_class = ' add-density-highlight';
                                //$highlight_title = 'title="'.esc_attr__('This post needs more inbound internal links pointing to it.', 'wpil').'"';
                                $tooltip = __('Add this many inbound internal links. Premium helps you do it in just a few clicks.', 'wpil');
                                $increase_count = '<span class="wpil-tippy-tooltipped" data-wpil-tooltip-content="'.$tooltip.'"> +1</span>';
                                $inbound_suggestion_data = 'data-wpil-suggestion-url="'. esc_url(admin_url('admin.php?page=link_whisper&type=inbound_suggestions_page_container&'.($item['post']->type=='term'?'term_id=':'post_id=').$item['post']->id.(!empty($source_id) ? '&source=' . $source_id: '').(!empty(get_current_user_id()) ? '&nonce='.wp_create_nonce(get_current_user_id() . 'wpil_suggestion_nonce') : '')).Wpil_Suggestion::getKeywordsUrl() . ((empty($source_id)) ? Wpil_Settings::get_suggestion_filter_string(): '')) .'"';
                            }

                            $add_inbound_links = (isset($item['links_inbound_page_url']) && !empty($item['links_inbound_page_url'])) ? '<a class="add-internal-links add-inbound-internal-links'.$add_highlight_class.'" '.$highlight_title.' href="javascript:void(0)" style="text-decoration: underline;" data-wpil-report-post-id="1" data-wpil-report-type="wpil_links_inbound_internal_count" data-wpil-report="1">' /*Add'*/ . $increase_count . $ai_suggestions . '</a>': '';
                            $v .= '<span class="wpil_ul '.$links_diffuse.'" data-wpil-link-relation="'.$link_relation.'">' . $count . '</span></div>' . $add_inbound_links;
                            break;
                        case 'wpil_links_outbound_internal_count':
                            $link_type = 'outbound-internal';
                            $panel_title = esc_attr__('Outbound Internal Links', 'wpil');
                            $activity_tooltip = esc_attr__('View Outbound Internal Links', 'wpil');
                            $count = 0;
                            $relation_counter = array('on' => 0, 'off' => 0);
                            $anchor_words = array('in_guideline' => 0, 'outside' => 0);
                            foreach ($links_data as $link) {
                                if (!Wpil_Filter::linksLocation() || $link->location == Wpil_Filter::linksLocation()) {
                                    $count++;
                                    $related = '';

                                    if(empty($link->get_ai_relation_percent(true)) || $link->get_ai_relation_percent(true) > 49){
                                        $relation_counter['on']++;
                                    }else{
                                        $relation_counter['off']++;
                                        $related = 'ai-not-related';
                                    }

                                    if($link->anchor_word_count >= 3 && $link->anchor_word_count <= 7){
                                        $anchor_words['in_guideline']++;
                                    }else{
                                        $anchor_words['outside']++;
                                    }

                                    if($count > 0){
                                        continue;
                                    }

                                    $primary_category_note = '';
                                    if(!empty($link->post) && $link->post->type === 'post') {
                                        // Get the main term
                                        $primary_term = Wpil_Post::get_primary_term_for_main_taxonomy($link->post->id, $link->post->getRealType());

                                        if ($primary_term instanceof WP_Term) {
                                            $primary_category_note = '<div style="margin: 3px 0;"><b>Main Category:</b> ' . esc_html($primary_term->name) . '</div>';
                                        } else {
                                            $primary_category_note = '<div style="margin: 3px 0;"><b>Main Category:</b> None assigned.</div>';
                                        }
                                    }

                                    $rep .= '<li class="'.$related.'">
                                                <input type="checkbox" class="wpil_link_select" data-post_id="' . $item['post']->id . '" data-post_type="' . $item['post']->type . '" data-anchor="' . base64_encode($link->anchor) . '" data-url="' . base64_encode($link->url) . '">
                                                <div>
                                                    <div style="margin: 3px 0;"><b>Link:</b> <a href="' . esc_url($link->url) . '" target="_blank" style="text-decoration: underline">' . esc_html($link->url) . '</a></div>
                                                    <div style="margin: 3px 0;"><b>Anchor Text:</b> <a href="' . esc_url(add_query_arg(['wpil_admin_frontend' => '1', 'wpil_admin_frontend_data' => $link->create_scroll_link_data()], $item['post']->getLinks()->view)) . '" target="_blank">' . esc_html($link->anchor) . ' <span class="dashicons dashicons-external" style="position: relative;top: 3px;"></span></a></div>
                                                    <div style="margin: 3px 0;"><div class="content-relatedness-score"><b>Content Relatedness:</b> ' . esc_html($link->get_ai_relation_percent()) . '</div></div>';
                                    $rep .= ($get_all_links) ? '<div style="margin: 3px 0;"><b>Link Location:</b> ' . $link->location . '</div>' : '';
                                    $rep .= $primary_category_note;
                                    $rep .= Wpil_Report::get_dropdown_icons($item['post'], $link, 'outbound-internal');
                                    $rep .=     '</div>
                                            </li>';
                                }
                                $primary_category_note = '';
                                if(!empty($link->post) && $link->post->type === 'post') {
                                    // Get the main term
                                    $primary_term = Wpil_Post::get_primary_term_for_main_taxonomy($link->post->id, $link->post->getRealType());

                                    if ($primary_term instanceof WP_Term) {
                                        $primary_category_note = '<div style="margin: 3px 0;"><b>Main Category:</b> ' . esc_html($primary_term->name) . '</div>';
                                    } else {
                                        $primary_category_note = '<div style="margin: 3px 0;"><b>Main Category:</b> None assigned.</div>';
                                    }
                                }
                                $rep .= '<li>
                                            <div>
                                                <div style="margin: 3px 0;"><b>Link:</b> <a href="' . esc_url($link->url) . '" target="_blank" style="text-decoration: underline">' . esc_html($link->url) . '</a></div>
                                                <div style="margin: 3px 0;"><b>Anchor Text:</b> ' . esc_html(strip_tags($link->anchor)) . '</div>';
                                $rep .=         $primary_category_note;
                                $rep .=         Wpil_Report::get_dropdown_icons($item['post'], $link, 'outbound-internal');
                                $rep .=     '</div>
                                        </li>';
                            }

                            $ai_suggestions = '';
                            // TODO: Enable when the outbound stats are fully trimmable
//                            if(Wpil_Settings::get_use_ai_suggestions()){
//                                $suggestions = Wpil_Report::get_ai_detected_suggestions($item['post'], true);
//                                $ai_suggestions = (!empty($suggestions)) ? '<span class="wpil-no-action" style="display: inline-block; margin-left: 10px; text-decoration: underline;" title="'. esc_attr__('AI Detected Linking Opportunities', 'wpil') .'"><span>' . $suggestions . '</span><span><span class="dashicons dashicons-superhero"></span></span></span>': '';
//                            }

                            $add_highlight_class = '';
                            $highlight_title = '';
                            $increase_count = '';
                            $outbound_suggestion_data = '';
                            if(isset($_REQUEST['link_density']) && $count < 3){
                                $add_highlight_class = ' add-density-highlight';
                                //$highlight_title = 'title="'.esc_attr__('This post needs more outbound internal links.', 'wpil').'"';
                                $tooltip = __('Add this many outbound internal links.', 'wpil');
                                $increase_count = '<span class="wpil-tippy-tooltipped" data-wpil-tooltip-content="'.$tooltip.'">' .' +' . (3 - $count). '</span>';
                                $outbound_suggestion_data = 'data-wpil-suggestion-url="' . esc_url(admin_url('admin.php?post_id=' . $item['post']->id . '&page=link_whisper&type=outbound_suggestions_ajax'.($item['post']->type === 'term'?'&term_id='.$item['post']->id:'').(!empty(get_current_user_id()) ? '&nonce='.wp_create_nonce(get_current_user_id() .'wpil_suggestion_nonce') : '')) . Wpil_Settings::get_suggestion_filter_string()) . '"';
                            }

                            if(isset($_REQUEST['anchor_length'])){
                                $percentage = 0;
                                if(!empty($anchor_words['outside']) && !empty($anchor_words['in_guideline'])){
                                    $percentage = round(($anchor_words['in_guideline']/($anchor_words['in_guideline']+$anchor_words['outside'])) * 100);
                                }else{
                                    $percentage = (!empty($anchor_words['in_guideline'])) ? 100: 0;
                                }

                                if($percentage < 60){
                                    $add_highlight_class = ' wpil-fix-problem wpil-purple-highlight';
                                }else{
                                    $add_highlight_class = '';
                                }
                                
                                $tooltip = __('This is the percent of link anchors are between 3 and 7 words.', 'wpil');
                                $increase_count = '<span class="wpil-tippy-tooltipped" data-wpil-tooltip-content="'.$tooltip.'">' . ($percentage). '%' . '</span>';
                                $anchor_atts = 'data-post-id="'.esc_attr($post_id).'" data-post-type="'.$post_type.'" data-link-type="'.$link_type.'" data-nonce="'.wp_create_nonce(wp_get_current_user()->ID .'wpil_report_link_nonce').'" data-activity-panel-title="'.$panel_title.'" data-show-fix-anchor="1"';
                            }

                            $link_relation = 0;
                            $links_diffuse = '';
                            $high_relation = '';
                            $fix = '';
                            if(!empty($relation_counter['off'])){
                                $link_relation = round($relation_counter['on'] / $count, 2) * 100;
                                $link_unrelation = round($relation_counter['off'] / $count, 2) * 100;
                                if($link_unrelation > 40){
                                    $links_diffuse = 'wpil-links-diffuse';
                                    $links_diffuse_container = 'wpil-has-link-relation';
                                    $tooltip = __('Remove or update links to improve relatedness score.', 'wpil');
                                    $fix = '<span class="wpil-dropdown-fix-indicator wpil-tippy-tooltipped" data-wpil-tooltip-content="'.$tooltip.'" style="position: absolute; left: 65px;background:#ff1e1e;border-radius: 10px;padding: 0 6px;color: #fefefe;font-weight: bold;">' . esc_html__('Fix', 'wpil') . '</span>';
                                }
                            }elseif(!empty($relation_counter['on'])){
                                $link_relation = 100;
                            }

                            
                            if(isset($_REQUEST['link_relation'])){
                                $high_relation = '<span class="wpil-link-relation-indicator wpil-tippy-tooltipped ' . $links_diffuse . '" data-wpil-tooltip-content="' . $link_relation . '% '. esc_attr__('of links on this post are going to related posts. In most cases, you want it to be greater than 60%', 'wpil').'" style="position: absolute; left: 15px;">' . $link_relation . '%</span>' . $fix;
                            }

                            if(isset($_REQUEST['anchor_length'])){
                                $edit_link = '<a class="wpil-show-anchor-words wpil-link-report-stat-indicator'.$add_highlight_class.'" '.$highlight_title.' href="#" '.$anchor_atts.' style="text-decoration: underline;">' /*Add'*/ . $increase_count . $ai_suggestions . '</a>';
                            }else{
                                $edit_link = '<a class="add-outbound-internal-links'.$add_highlight_class.'" '.$highlight_title.' href="javascript:void(window.open(\''. esc_url($item['post']->getLinks()->edit) .'\'))" '.$outbound_suggestion_data.' style="text-decoration: underline;">' /*Add'*/ . $increase_count . $ai_suggestions . '</a>';
                            }

 
                            $v .= $high_relation . '<span class="wpil_ul" data-wpil-link-relation="'.$link_relation.'">' . $count . '</span></div>' . $edit_link;
                            break;
                        case 'wpil_links_outbound_external_count':
                            $link_type = 'outbound-external';
                            $panel_title = esc_attr__('Outbound External Links', 'wpil');
                            $activity_tooltip = esc_attr__('View Outbound External Links', 'wpil');
                            $count = 0;
                            $anchor_words = array('in_guideline' => 0, 'outside' => 0);
                            foreach ($links_data as $link) {
                                    $count++;
                                    
                                    if($link->anchor_word_count >= 3 && $link->anchor_word_count <= 7){
                                        $anchor_words['in_guideline']++;
                                    }else{
                                        $anchor_words['outside']++;
                                    }
                                    if($count > 0){
                                        continue;
                                    }
                                    $rep .= '<li>
                                                <div>
                                                    <div style="margin: 3px 0;"><b>Link:</b> <a href="' . esc_url($link->url) . '" target="_blank" style="text-decoration: underline">' . esc_html($link->url) . '</a></div>
                                                    <div style="margin: 3px 0;"><b>Anchor Text:</b> ' . esc_html(strip_tags($link->anchor)) . '</div>';
                                    $rep .=         Wpil_Report::get_dropdown_icons($item['post'], $link, 'outbound-external');
                                    $rep .=     '</div>
                                            </li>';
                            }

                            if(isset($_REQUEST['anchor_length'])){
                                $percentage = 0;
                                if(!empty($anchor_words['outside']) && !empty($anchor_words['in_guideline'])){
                                    $percentage = round(($anchor_words['in_guideline']/($anchor_words['in_guideline']+$anchor_words['outside'])) * 100);
                                }else{
                                    $percentage = (!empty($anchor_words['in_guideline'])) ? 100: 0;
                                }
                                
                                $add_highlight_class = ' wpil-fix-problem wpil-purple-highlight';
                                $tooltip = __('This is the percent of link anchors are between 3 and 7 words.', 'wpil');
                                $increase_count = '<span class="wpil-tippy-tooltipped" data-wpil-tooltip-content="'.$tooltip.'">' . ($percentage). '%' . '</span>';
                                $anchor_atts = 'data-post-id="'.esc_attr($post_id).'" data-post-type="'.$post_type.'" data-link-type="'.$link_type.'" data-nonce="'.wp_create_nonce(wp_get_current_user()->ID .'wpil_report_link_nonce').'" data-activity-panel-title="'.$panel_title.'" data-show-fix-anchor="1"';
                            }

                            $fix = '';
                            $highlight_title = '';
                            $edit_link = '';
                            if(isset($_REQUEST['anchor_length'])){
                                $edit_link = '<a class="wpil-show-anchor-words wpil-link-report-stat-indicator'.$add_highlight_class.'" '.$highlight_title.' href="#" '.$anchor_atts.' style="text-decoration: underline;">' /*Add'*/ . $increase_count . '</a>';
                            }

                            $v = '<span class="wpil_ul">' . $count . '</span>' . $edit_link . $v;
                            break;
                    }

                    $rep .= '</ul>';
                }

                $e_rt = esc_attr($column_name);
                $e_p_id = esc_attr($post_id);


                $atts = Wpil_Toolbox::output_dropdown_wrapper_atts(array('report_type' => 'links', 'post_id' => $e_p_id, 'post_type' => $post_type, 'nonce' => wp_create_nonce(wp_get_current_user()->ID . 'wpil-collapsible-nonce')));
                $atts = 'data-post-id="'.$e_p_id.'" data-post-type="'.$post_type.'" data-link-type="'.$link_type.'" data-nonce="'.wp_create_nonce(wp_get_current_user()->ID .'wpil_report_link_nonce').'" data-activity-panel-title="'.$panel_title.'"';
                $v = "<div class='wpil-collapsible-wrapper wpil-activity-activate' {$atts}>
  			            <div class='wpil-collapsible wpil-collapsible-static wpil-links-count wpil-no-action " . ((!empty($count)) ? 'wpil-collapsible-has-data': 'wpil-collapsible-no-data') . "' title='" . esc_attr($title) . "' data-wpil-report-type='$e_rt' data-wpil-report-post-id='$e_p_id'>$v<div class=\"wpil-right-arrow-box wpil-tippy-tooltipped\" data-wpil-tooltip-content=\"".$activity_tooltip."\"></div></div>
  				        <div class='wpil-content {$links_diffuse_container}'>
          			        $rep
  				        </div>
  				    </div>";
            }

        }

        return $v;
    }

    function get_columns()
    {
        $columns = ['checkall' => '<input class="wpil-report-post-checkbox" type="checkbox">', 'post_title' => __('Title', 'wpil')];
        $options = get_user_meta(get_current_user_id(), 'report_options', true);

        if (!empty($options['show_date']) && $options['show_date'] == 'on') {
            $columns['date'] = __('Published', 'wpil');
        }

        if (!empty($options['show_type']) && $options['show_type'] == 'on') {
            $columns['post_type'] = __('Type', 'wpil');
        }

        $inbound_help_overlay = 'class="wpil-report-header-container wpil-is-tooltipped wpil-no-scale wpil-tooltip-target-parent wpil-tooltip-target.column-wpil_links_inbound_internal_count" ' . Wpil_Toolbox::generate_tooltip_text('link-report-table-inbound-internal-links-col');
        $outbound_help_overlay = 'class="wpil-report-header-container wpil-is-tooltipped wpil-no-scale wpil-tooltip-target-parent wpil-tooltip-target.column-wpil_links_outbound_internal_count" ' . Wpil_Toolbox::generate_tooltip_text('link-report-table-outbound-internal-links-col');
        $external_help_overlay = 'class="wpil-report-header-container wpil-is-tooltipped wpil-no-scale wpil-tooltip-target-parent wpil-tooltip-target.column-wpil_links_outbound_external_count" ' . Wpil_Toolbox::generate_tooltip_text('link-report-table-outbound-external-links-col');

        $inbound = '<div ' . $inbound_help_overlay . '>' . 
                        __('Inbound Internal', 'wpil') . 
                        '<div class="wpil-report-header-tooltip">
                            <div class="wpil_help">
                                <i class="dashicons dashicons-editor-help"></i>
                                <div class="wpil-help-text" style="display: none;">' . sprintf(__('Inbound Internal Links are links on %s on this site that are pointing to %s.', 'wpil'), '<span style="font-style: italic;float: none;">' . __('other pages', 'wpil') . '</span>', '<span style="text-decoration: underline;float: none;">' . __('this page', 'wpil') . '</span>') . '</div>
                            </div>
                        </div>
                    </div>';

        $outbound = '<div ' . $outbound_help_overlay . '>' . 
                        __('Outbound Internal', 'wpil') . 
                        '<div class="wpil-report-header-tooltip">
                            <div class="wpil_help">
                                <i class="dashicons dashicons-editor-help"></i>
                                <div class="wpil-help-text" style="display: none;">' . sprintf(__('Outbound Internal Links are links that are on %s and are pointing to %s on this site.', 'wpil'), '<span style="font-style: italic;float: none;">' . __('this page', 'wpil') . '</span>',  '<span style="text-decoration: underline;float: none;">' . __('other pages', 'wpil') . '</span>') . '</div>
                            </div>
                        </div>
                    </div>';

        $external = '<div ' . $external_help_overlay . '>' . 
                        __('Outbound External', 'wpil') . 
                        '<div class="wpil-report-header-tooltip">
                            <div class="wpil_help">
                                <i class="dashicons dashicons-editor-help"></i>
                                <div class="wpil-help-text" style="display: none;">' . sprintf(__('Outbound External Links are links that are on %s and are pointing to pages on %s.', 'wpil'),  '<span style="font-style: italic;float: none;">' . __('this page', 'wpil') . '</span>', '<span style="text-decoration: underline;float: none;">' . __('other sites', 'wpil') . '</span>') . '</div>
                            </div>
                        </div>
                    </div>';

        if(!isset($_GET['link_relation']) && !isset($_GET['anchor_length'])){
            $columns = array_merge($columns, [
                WPIL_LINKS_INBOUND_INTERNAL_COUNT => $inbound
            ]);
        }

        if(!isset($_GET['orphaned'])){
            $columns = array_merge($columns, [
                WPIL_LINKS_OUTBOUND_INTERNAL_COUNT => $outbound
            ]);
        }

        if(!isset($_GET['link_density']) && !isset($_GET['orphaned']) && !isset($_GET['link_relation'])){
            $columns = array_merge($columns, [
                WPIL_LINKS_OUTBOUND_EXTERNAL_COUNT => $external
            ]);
        }

        $columns = array_merge($columns, [
            'wpil-report-actions' => 'Actions'
        ]);

        return $columns;
    }

    function column_post_title($item)
    {
        $post = $item['post'];
        $title = '<div class="wpil-report-row-title-container"><a href="' . esc_url($post->getLinks()->edit) . '" class="row-title">' . esc_attr($post->getTitle()) . '</a><div class="wpil-row-title-icon-container">' . $this->get_title_icons($post) . '</div></div>';
        return $title;
    }

    function build_action_panel($item){

            $post = $item['post'];
            $user = wp_get_current_user();

            $actions = [];

            $object_name = 'Item';
            if($post->type === 'post'){
                $name = get_post_type_labels(get_post_type_object(get_post_type($post->id)));
                $object_name = (!empty($name) && isset($name->singular_name)) ? $name->singular_name: 'Post';
            }else{
                $object_name = __('Term', 'wpil');
                // todo: get term taxonomy name
            }


            $actions['view'] = '<a target="_blank" class="wpil-panel-has-subactions" href="' . esc_url($post->getLinks()->view) . '">' . sprintf(__('View %s', 'wpil'), $object_name) . '</a>';
            $link_actions = 
            '<ul class="wpil-panel-subactions">
                <li class="wpil-panel-subaction" data-post-id="'.$post->id.'" data-post-type="'.$post->type.'" data-link-type="inbound-internal" data-nonce="'.wp_create_nonce($user->ID .'wpil_report_link_nonce').'" data-activity-panel-title="'.esc_attr__('Inbound Internal Links', 'wpil').'">View Inbound Internal Links</li>
                <li class="wpil-panel-subaction" data-post-id="'.$post->id.'" data-post-type="'.$post->type.'" data-link-type="outbound-internal" data-nonce="'.wp_create_nonce($user->ID .'wpil_report_link_nonce').'" data-activity-panel-title="'.esc_attr__('Outbound Internal Links', 'wpil').'">View Outbound Internal Links</li>
                <li class="wpil-panel-subaction" data-post-id="'.$post->id.'" data-post-type="'.$post->type.'" data-link-type="outbound-external" data-nonce="'.wp_create_nonce($user->ID .'wpil_report_link_nonce').'" data-activity-panel-title="'.esc_attr__('Outbound External Links', 'wpil').'">View Outbound External Links</li>
            </ul>';
            $actions['view'] .= $link_actions;


            $actions['edit'] = '<a target="_blank" href="' . esc_url($post->getLinks()->edit) . '">' . sprintf(__('Edit %s', 'wpil'), $object_name) . '</a>';

//            $actions['add-outbound-internal'] = '<a class="add-outbound-internal-links" href="javascript:void(window.open(\''. esc_url($item['post']->getLinks()->edit) .'\'))" data-wpil-suggestion-url="' . esc_url(admin_url('admin.php?post_id=' . $post->id . '&page=link_whisper&type=outbound_suggestions_ajax'.($post->type === 'term'?'&term_id='.$post->id:'').(!empty($user->ID) ? '&nonce='.wp_create_nonce($user->ID .'wpil_suggestion_nonce') : '')) . Wpil_Settings::get_suggestion_filter_string()) . '">Add Outbound Internal Links</a>';
//            $actions['add-inbound-internal'] = (isset($item['links_inbound_page_url']) && !empty($item['links_inbound_page_url'])) ? '<a class="add-inbound-internal-links" href="javascript:void(window.open(\''. esc_url($item['links_inbound_page_url']) .'\'))" data-wpil-report-post-id="1" data-wpil-report-type="wpil_links_inbound_internal_count" data-wpil-report="1" data-wpil-suggestion-url="'. esc_url(admin_url('admin.php?page=link_whisper&type=inbound_suggestions_page_container&'.($post->type=='term'?'term_id=':'post_id=').$post->id.(!empty($source_id) ? '&source=' . $source_id: '').(!empty($user->ID) ? '&nonce='.wp_create_nonce($user->ID . 'wpil_suggestion_nonce') : '')).Wpil_Suggestion::getKeywordsUrl() . ((empty($source_id)) ? Wpil_Settings::get_suggestion_filter_string(): '')) .'">Add Inbound Internal Links</a>': '';

//            $actions['delete-post-links'] = '<a target="_blank" href="' . esc_url($post->getLinks()->edit) . '">' . sprintf(__('Delete All Links On %s', 'wpil'), $object_name) . '</a>';

            if($post->type === 'post' && !empty(EMPTY_TRASH_DAYS)){
                $actions['trash'] = '<a href="' . esc_url(get_delete_post_link($post->id)) . '" class="wpil-trash-post-link">' . sprintf(__('Trash %s', 'wpil'), $object_name) . '</a>';
            }

            if(isset($_GET['orphaned'])){
                $actions['ignore-orphaned'] = '<a href="#" class="wpil-ignore-orphaned-post" data-post-id="' . $post->get_pid() . '" data-nonce="'. wp_create_nonce($user->ID . 'ignore-orphaned-post-nonce') .'">' . sprintf(__('Ignore Orphaned %s', 'wpil'), $object_name) . '</a>';
            }

            if(!isset($_GET['orphaned']) && !isset($_GET['link_density']) && !isset($_GET['link_relation'])){
                $actions['export'] = '<a target="_blank" href="' . esc_url($post->getLinks()->export) . '">Export Support Data</a>';
                $actions['excel_export'] = '<a target="_blank" href="' . esc_url($post->getLinks()->excel_export) . '">Export Links to Excel</a>';
            }

            $content = 
            '<div class="wpil-report-action-panel-wrapper">
                <div class="wpil-report-action-panel-container">
                    <div id="wpil-action-post-' .$post->get_pid(). '" class="wpil-panel-actions">
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

    /**
     * Gets the icons that we'll be appending to the post titles in the report for quick information
     **/
    function get_title_icons($post){
        $icons = '';

        if($post->type === 'post'){
            $redirected_post_url = Wpil_Link::get_url_redirection($post->getViewLink());

            // if the current post has had it's URL redirected
            if(!empty($redirected_post_url)){
                // check if the redirect is pointing to a different post
                $new_post = Wpil_Post::getPostByLink($redirected_post_url);
                // if it is, or the redirect is pointing to the home url
                if(!empty($new_post) && $post->id !== $new_post->id || Wpil_Link::url_points_home($redirected_post_url)){
                    $icons .= '<div class="wpil_help">';
                    $icons .= '<i class="dashicons dashicons-hidden"></i>';
                    $icons .= '<div class="wpil-help-text" style="display: none; top: 6px; left: -81px;">' . __('Hidden by redirect', 'wpil') . '</div>';
                    $icons .= '</div>';
                }
            }
        }

        if($post->type === 'post'){
            $is_pillar = false;
            if(class_exists('WPSEO_Meta') && method_exists('WPSEO_Meta', 'get_value')){
                $is_pillar = (WPSEO_Meta::get_value('is_cornerstone', $post->id) === '1');
            }

            if(empty($is_pillar) && defined('RANK_MATH_VERSION')){
                $is_pillar = Wpil_Toolbox::check_pillar_content_status($post->id);
            }

            if(!empty($is_pillar)){
                $icons .= '<div class="wpil_help">';
                $icons .= '<i class="dashicons dashicons-media-text"></i>';
                $icons .= '<div class="wpil-help-text" style="display: none;">' . __('Pillar Content', 'wpil') . '</div>';
                $icons .= '</div>';
            }
        }

        return $icons;
    }

    function get_sortable_columns()
    {
        $cols = $this->get_columns();

        $sortable_columns = [];

        foreach ($cols as $col_k => $col_name) {
            $sortable_columns[$col_k] = [$col_k, false];
        }

        return $sortable_columns;
    }

    function prepare_items()
    {
        define('WPIL_LOADING_REPORT', true);
        $options = get_user_meta(get_current_user_id(), 'report_options', true);
        $per_page = !empty($options['per_page']) ? $options['per_page'] : 20;

        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = array($columns, $hidden, $sortable);

        $start = isset($_REQUEST['paged']) ? (int)$_REQUEST['paged'] : 0;
        $orderby = (isset($_REQUEST['orderby']) && !empty($_REQUEST['orderby'])) ? sanitize_text_field($_REQUEST['orderby']) : '';
        $order = (!empty($_REQUEST['order'])) ? sanitize_text_field($_REQUEST['order']) : 'DESC';
        $search = (!empty($_REQUEST['s'])) ? sanitize_text_field($_REQUEST['s']) : '';
        $orphaned = !empty($_REQUEST['orphaned']);
        $link_density_report = (isset($_REQUEST['link_density']) && !empty($_REQUEST['link_density'])) ? true: false;
        $link_relation_report = (isset($_REQUEST['link_relation']) && !empty($_REQUEST['link_relation'])) ? true: false;
        $anchor_length_report = (isset($_REQUEST['anchor_length']) && !empty($_REQUEST['anchor_length'])) ? true: false;

        if (empty($orderby)) {
            $saved_order = get_transient('wpil_link_report_order');
            if (!empty($saved_order)) {
                $saved_order = explode(';', $saved_order);
                if (count($saved_order) == 2) {
                    $orderby = !empty($saved_order[0]) ? $saved_order[0] : '';
                    $order = !empty($saved_order[1]) ? $saved_order[1] : 'DESC';
                }
            }
        }

        if (!empty($orderby)) {
            set_transient('wpil_link_report_order', $orderby . ';' . $order);
        }

        $data = Wpil_Report::getData($start, $orderby, $order, $search, $per_page, $orphaned, $link_density_report, $anchor_length_report);

        $total_items = $data['total_items'];
        $data = $data['data'];

        $this->items = $data;

        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ));
    }

    /**
     * Displays the search box.
     *
     * @param string $text     The 'submit' button label.
     * @param string $input_id ID attribute value for the search input field.
     */
    public function search_box( $text, $input_id ) {
        if ( empty( $_REQUEST['s'] ) && ! $this->has_items() ) {
            //return;
        }

        $input_id = $input_id . '-search-input';

        if ( ! empty( $_REQUEST['orderby'] ) ) {
            echo '<input type="hidden" name="orderby" value="' . esc_attr( $_REQUEST['orderby'] ) . '" />';
        }
        if ( ! empty( $_REQUEST['order'] ) ) {
            echo '<input type="hidden" name="order" value="' . esc_attr( $_REQUEST['order'] ) . '" />';
        }
        if ( ! empty( $_REQUEST['post_mime_type'] ) ) {
            echo '<input type="hidden" name="post_mime_type" value="' . esc_attr( $_REQUEST['post_mime_type'] ) . '" />';
        }
        if ( ! empty( $_REQUEST['detached'] ) ) {
            echo '<input type="hidden" name="detached" value="' . esc_attr( $_REQUEST['detached'] ) . '" />';
        }

        $post_type = !empty($_GET['post_type']) ? $_GET['post_type'] : 0;
        $cat = !empty($_GET['category']) ? $_GET['category'] : 0;
        $location = !empty($_GET['location']) ? $_GET['location'] : null;
        $filter_type = !empty($_GET['filter_type']) ? $_GET['filter_type'] : 0;
        $link_type = !empty($_GET['link_type']) ? $_GET['link_type'] : null;
        $min = !empty($_GET['link_min_count']) ? $_GET['link_min_count'] : 0;
        $max = array_key_exists('link_max_count', $_GET) ? $_GET['link_max_count'] : null;

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
        ?>
        <style>
            <?php
            switch ($filter_type) {
                case '2':
                    // do nothing to hide the inputs
                    break;
                case '1':
                    echo '.filter-by-type{display:none;}';
                    break;
                case '0':
                default:
                    echo '.filter-by-count{display:none;}';
                break;
            }
            ?> 
        </style>
        <div class="wpil-hamburger-filter-container" style="display:flex; flex-direction: column;">
            <div class="wpil-hamburger-filter-option">
                <div class="wpil-hamburger-filter-title">Search Posts</div>
                <div class="wpil-hamburger-filter-fields">
                    <p class="search-box">
                        <label class="screen-reader-text" for="<?php echo esc_attr( $input_id ); ?>"><?php echo $text; ?>:</label>
                        <input type="search" id="<?php echo esc_attr( $input_id ); ?>" name="s" value="<?php _admin_search_query(); ?>" placeholder="Keyword or URL" />
                        <?php submit_button( "🔎 " . $text, '', '', false, array( 'id' => 'search-submit' ) ); ?>
                    </p>
                </div>
            </div>

            <div class="wpil-hamburger-filter-option">
                <div class="wpil-hamburger-filter-title">Export Links</div>
                <div class="wpil-hamburger-filter-fields">
                    <form action='' method="post" id="wpil_report_reset_data_form">
                        <input type="hidden" name="reset_data_nonce" value="<?php echo wp_create_nonce(get_current_user_id() . 'wpil_reset_report_data'); ?>">
                        <?php if (!empty($_GET['type'])) : ?>
                            <div class="wpil-report-export-button-container" style="display:inline-block">
                                <a href="javascript:void(0)" class="wpil-filter-submit-button csv_button" data-type="<?=esc_attr($_GET['type'])?>" id="wpil_cvs_export_button" style="text-align:left;" data-file-name="<?php esc_attr_e('detailed-link-export.csv', 'wpil'); ?>">📤 Detailed Export to CSV</a>
                                <a href="javascript:void(0)" class="wpil-filter-submit-button csv_button" data-type="<?=esc_attr($_GET['type'])?>_summary" id="wpil_cvs_export_button" style="text-align:left;" data-file-name="<?php esc_attr_e('summary-link-export.csv', 'wpil'); ?>">📤 Summary Export to CSV</a>
                            </div>
                            <?php 
                                if(!empty(get_transient('wpil_resume_scan_data'))){
                                    echo '<a href="javascript:void(0)" class="button-primary wpil-resume-link-scan">' . __('Resume Link Scan', 'wpil') . '</a>';
                                }
                            ?>
                        <?php endif; ?>
                        <button type="submit" class="button-primary" style="display:none;">Run a Link Scan</button>
                    </form>
                </div>
            </div>
            <div class="wpil-hamburger-filter-option">
                <div class="wpil-hamburger-filter-title">Filter Posts</div>
                <div class="wpil-hamburger-filter-fields">
                    <div class="actions bulkactions wpil-is-tooltipped_TODO" style="display: flex;flex-direction: column; margin: 0; width: 310px;" id="wpil_links_table_filter" <?php echo Wpil_Toolbox::generate_tooltip_text('link-report-filters'); ?>>
                        <select name="filter_type">
                            <option value="0" <?php selected($filter_type, '0'); ?>><?php esc_html_e('Filter by Post Type', 'wpil'); ?></option>
                            <?php if(!isset($_REQUEST['link_density'])){ ?>
                            <option value="1" <?php selected($filter_type, '1'); ?>><?php esc_html_e('Filter by Link Count', 'wpil'); ?></option>
                            <option value="2" <?php selected($filter_type, '2'); ?>><?php esc_html_e('Filter by Post Type & Link Count', 'wpil'); ?></option>
                            <?php } ?>
                        </select>
                        <!--filter by post type-->
                        <select name="post_type" class="filter-by-type">
                            <option value="0">All types</option>
                            <?php foreach (Wpil_Settings::getAllTypes() as $type) : ?>
                                <option value="<?=esc_attr($type)?>" <?=$type===$post_type?' selected':''?>><?=ucfirst($type)?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="category" class="filter-by-type">
                            <option value="0">All categories</option>
                            <?php echo $options; ?>
                        </select>
                        <?php if (Wpil_Settings::showAllLinks()) : ?>
                            <select name="location" class="filter-by-type">
                                <option value="0">All locations</option>
                                <?php foreach (['header', 'content', 'footer'] as $loc) : ?>
                                    <option value="<?=$loc?>" <?=$loc==$location?' selected':''?>><?=$loc?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                        <!--/filter by post type-->
                        <!--filter by link counts-->
                        <select name="link_type" class="filter-by-count">
                            <option value="inbound-internal"  <?php selected($link_type, 'inbound-internal'); ?>><?php esc_html_e('Inbound Internal Links', 'wpil'); ?></option>
                            <option value="outbound-internal" <?php selected($link_type, 'outbound-internal'); ?>><?php esc_html_e('Outbound Internal Links', 'wpil'); ?></option>
                            <option value="outbound-external" <?php selected($link_type, 'outbound-external'); ?>><?php esc_html_e('Outbound External Links', 'wpil'); ?></option>
                        </select>
                        <label for="wpil_link_min_count" class="filter-by-count">Min</label>
                        <input id="wpil_link_min_count"type="number" name="link_min_count" class="filter-by-count" min="0" value="<?php echo $min; ?>" style="max-width: 70px;">
                        <label for="wpil_link_max_count" class="filter-by-count">Max</label>
                        <input id="wpil_link_max_count" type="number" name="link_max_count" class="filter-by-count" min="0" <?php if(null !== $max){ echo 'value="' . $max . '"';} ?> style="max-width: 70px;">
                        <!--/filter by link counts-->
                        <span class="wpil-filter-submit-button wpil_links_table_filter_submit" style="text-align:center;">🔎 Filter</span>
                        <input type="hidden" class="post-filter-nonce" value="<?php echo wp_create_nonce(get_current_user_id() . 'wpil_filter_nonce'); ?>">
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    function extra_tablenav( $which ) {
        if ($which != "top") {
            return;
        }?>


<!-- bulk options infos -->
        <div class="wpil-bulk-select" id="wpil-bulk-select">
            <button class="wpil-bulk-trigger" type="button" aria-haspopup="listbox" aria-expanded="false" aria-controls="wpil-bulk-menu">Bulk actions
                <svg class="chev" viewBox="0 0 20 20" width="16" height="16" aria-hidden="true">
                <path d="M5 7l5 6 5-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </button>

            <ul class="wpil-bulk-menu" id="wpil-bulk-menu" role="listbox" tabindex="-1" hidden>
                <?php if(isset($_GET['orphaned'])){ ?>
                <li role="option" data-value="ignore_orphaned">Ignore Orphaned Posts</li>
                <?php } ?>
                <li role="option" data-value="trash_posts">Move to Trash</li>
                <!--<li role="option" data-value="export_csv">Export Selected to CSV</li>-->
            </ul>
            <input type="hidden" id="wpil_links_trash_selected" />
            <input type="hidden" id="wpil_links_ignore_orphaned_selected" data-nonce="<?php echo wp_create_nonce(get_current_user_id() . 'ignore-orphaned-post-nonce');?>" />
        </div>
<script>
jQuery(function($) {
  var $root   = $('#wpil-bulk-select');

  // Example consumer:
  $root.on('wpil:bulkActionSelected', function(e, detail) {
    var data = [];
    switch (detail.value) {
        case 'trash_posts':
            $('#wpil_links_trash_selected').trigger('click');
            break;
        case 'ignore_orphaned':
            $('#wpil_links_ignore_orphaned_selected').trigger('click');
            break;
        default:
            break;
    }

    console.log('Bulk action selected:', detail);
  });

    function animateActivityPanel(title = ''){
        var panel = $('.wpil-activity-panel-wrapper');
        if (panel.hasClass('open')){
            closePanel();
        } else {
            if(title){
                panel.find('.wpil-activity-panel-header').text(title);
            }
        panel.addClass('active-1');
        $('.wpil-overlay').addClass('is-open');
        // slide in to a neat gutter (20px)
        panel.animate({ right: '0px' }, 500, function(){ panel.addClass('open'); });
        }
    }

    function closePanel(){
        var panel = $('.wpil-activity-panel-wrapper');
        if(!panel.hasClass('open')) return;
        panel.animate({ right: '-110vw' }, 500, function(){
            panel.removeClass('active-1 open');
            $('.wpil-overlay').removeClass('is-open');
        });
    }

    $(document).on('click', '.wpil-panel-subaction, .wpil-collapsible-wrapper.wpil-activity-activate', function(e){
        if( $(e.target).hasClass('add-outbound-internal-links') || 
            $(e.target).hasClass('add-inbound-internal-links') ||
            $(e.target).parents('a').hasClass('add-outbound-internal-links') ||
            $(e.target).parents('a').hasClass('add-inbound-internal-links')  || 
            $(e.target).parents('.add-density-highlight').length > 0
        ){
            return;
        }

        debouncePanelClick = true;
        var actionPanel = $('.wpil-report-action-panel-wrapper.open'),
            action = $(this);
        actionPanel.animate({'right': '-600px'}, 500, function(){
            actionPanel.removeClass('active-1 open');
        });
        
        $('.wpil-activity-panel').empty();
        ajaxPullLinkData(action);
        animateActivityPanel(action.data('activity-panel-title'));
    });

    /**
     * Checks to see if the clicked dropdown has all of its data.
     * If the dropdown doesn't, this downloads the remaining data and adds it to the dropdown
     **/
    var globalDownloadTracker = [];
    function ajaxPullLinkData(button = null){
        var clicked = (button) ? button: $(this),
            postId = clicked.data('post-id'),
            postType = clicked.data('post-type'),
            type = clicked.data('link-type'),
            showFixAnchor = clicked.data('show-fix-anchor'),
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
                action: 'get_link_report_link_data',
                link_type: type,
                post_id: postId,
                post_type: postType,
                show_fix_anchor: showFixAnchor,
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
                    $('.wpil-activity-panel').empty().append(response.success.link_table);
                    setTimeout(function(){
                        $('#wpil-tippy-tooltip-target').trigger('click');
                    }, 200);
                    
                }
            },
            error: function(jqXHR, textStatus, errorThrown){
                console.log({jqXHR, textStatus, errorThrown});
            },
            complete: function(){
                debouncePanelClick = false;
            }
        });
    }
});

</script>
        <div class="wpil-overlay" aria-hidden="true"></div>
        <div class="wpil-activity-panel-wrapper">
            <input type="hidden" id="wpil-get-manual-suggestions" data-wpil-links-report-activate-manual="1">
            <input type="hidden" id="wpil-tippy-tooltip-target">
            <div class="wpil-activity-panel-container">
                <div class="wpil-activity-panel-header-container">
                    <button class="wpil-panel-close" style="padding: 0px !important;" aria-label="Close panel" style="background: none">✖</button>
                    <h3 class="wpil-activity-panel-header" style="top: 0px;">Outbound Internal Suggestions</h3>
                </div>
                <div class="wpil-activity-panel">
                </div>
            </div>
            <div class="wpil-activity-panel-clone-section">
                <div class="wpil-suggestion-clone-panel">
                    <div class="wpil_keywords_list wpil_styles wpil-activity-panel-suggestions" data-wpil-manual-suggestions="1" data-wpil-suggestion-nonce="<?php echo wp_create_nonce(get_current_user_id() .'wpil_suggestion_nonce'); ?>">
                        <div class="wpil-activity-panel-suggestions-title-container">
                            <h3 class="wpil-activity-panel-suggestions-title"></h3>
                        </div>
                        <div class="progress_panel loader">
                            <div class="progress_count" style="width: 100%"><?php esc_html_e('Processing Link Suggestions', 'wpil');?></div>
                        </div>
                        <div class="wpil-process-loading-error-message" style="display: none;">
                            <p><?php esc_html_e('The suggestions are taking longer than normal, so there might have been an error.', 'wpil'); ?></p>
                            <p><?php esc_html_e('If you don\'t see any progress in the next 2 minutes, please try reloading the page and re-starting the process.', 'wpil'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php 
    }
}
