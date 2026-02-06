<div class="wpil_notice" id="wpil_message" <?php if(empty($phrase_groups) || 'post' !== $post->type){ echo 'style="display: none;"'; } ?>>
<?php if( empty( get_option(WPIL_PREMIUM_NOTICE_DISMISSED, '') ) ){ ?>
    <div id="lw_banner">
        <img class="close" src="<?php echo esc_url(WP_INTERNAL_LINKING_PLUGIN_URL . 'images/icon_delete.png'); ?>">
        <div class="title"><?php _e('Upgrade to Link Whisper Premium', 'wpil'); ?></div>
        <div class="features">
            <div><?php _e('+ Add internal links with a single click!', 'wpil'); ?></div>
            <div><?php _e('+ Get inbound internal link suggestions from the reports screen.', 'wpil'); ?></div>
            <div><?php _e('+ Add inbound internal links directly from reports with a single click.', 'wpil'); ?></div>
            <div><?php _e('+ Customize the anchor text by clicking the words you want.', 'wpil'); ?></div>
            <div><?php _e('+ No more copying and pasting links, just click and done!', 'wpil'); ?></div>
            <div><?php _e('+ Save hours of time and gain more control over your internal links.', 'wpil'); ?></div>
        </div>
        <a href="<?php echo esc_url(WPIL_STORE_URL . '/upgrade-offer/'); ?>" target="blank"><?php _e('Get $15 Off Link Whisper Premium Now!', 'wpil'); ?></a>
    </div>
<?php } ?>
</div>
<div class="wpil-suggestion-location-title">
    <h3>Creating Links In: <?php echo esc_html($post->getTitle()); ?></h3>
</div>
<div class="best_keywords outbound">
    <div style="margin-bottom: 15px; float:left;">
        <input type="hidden" class="wpil-suggestion-input wpil-suggestions-can-be-regenerated" value="0" data-suggestion-input-initial-value="0">
        <br>
        <?php if(!empty($categories)){ ?>
        <div class="wpil-is-tooltipped wpil-no-scale wpil-tooltip-no-position" style="display:inline-block;" <?php echo Wpil_Toolbox::generate_tooltip_text('outbound-suggestions-link-same-category'); ?>>
            <input style="margin-bottom: -5px;" type="checkbox" name="same_category" id="field_same_category" class="wpil-suggestion-input" data-suggestion-input-initial-value="<?php echo !empty($same_category) ? 1: 0;?>" <?=(isset($same_category) && !empty($same_category)) ? 'checked' : ''?>> <label for="field_same_category"><?php esc_html_e('Only Show Link Suggestions in the Same Category as This Post', 'wpil'); ?></label>
            <br>
            <div class="same_category-aux wpil-aux">
                <select multiple name="wpil_selected_category" class="wpil-suggestion-input wpil-suggestion-multiselect" data-suggestion-input-initial-value="<?php echo implode(',', $selected_categories);?>" style="width: 400px;">
                    <?php foreach ($categories as $cat){ ?>
                        <option value="<?php echo $cat->term_taxonomy_id; ?>" <?php echo (in_array($cat->term_taxonomy_id, $selected_categories, true) || empty($selected_categories))?'selected':''; ?>><?php esc_html_e($cat->name)?></option>
                    <?php } ?>
                </select>
                <br>
                <br>
            </div>
        </div>
        <br class="same_category-aux wpil-aux">
            <?php if(!empty($same_category)){ ?>
            <style>
                .best_keywords .same_category-aux{
                    display: inline-block;
                }
            </style>
            <?php } ?>
        <?php } ?>
        <?php if(!empty($tags)){ ?>
        <br />
        <div class="wpil-is-tooltipped wpil-no-scale wpil-tooltip-no-position" data-wpil-tooltip-read-time="4500" style="display:inline-block;" <?php echo Wpil_Toolbox::generate_tooltip_text('outbound-suggestions-link-same-tags'); ?>>
            <input type="checkbox" name="same_tag" id="field_same_tag" class="wpil-suggestion-input" data-suggestion-input-initial-value="<?php echo !empty($same_tag) ? 1: 0;?>"  <?=!empty($same_tag) ? 'checked' : ''?>> <label for="field_same_tag"><?php esc_html_e('Only Show Link Suggestions with the Same Tag as This Post', 'wpil'); ?></label>
            <br>
            <div class="same_tag-aux wpil-aux">
                <select multiple name="wpil_selected_tag" class="wpil-suggestion-input wpil-suggestion-multiselect" data-suggestion-input-initial-value="<?php echo implode(',', $selected_tags);?>" style="width: 400px;">
                    <?php foreach ($tags as $tag){ ?>
                        <option value="<?php echo $tag->term_taxonomy_id; ?>" <?php echo (in_array($tag->term_taxonomy_id, $selected_tags, true))?'selected':''; ?>><?php esc_html_e($tag->name)?></option>
                    <?php } ?>
                </select>
                <br>
                <br>
            </div>
        </div>
        <br class="same_tag-aux wpil-aux">
            <?php if(!empty($same_tag)){ ?>
            <style>
                .best_keywords .same_tag-aux{
                    display: inline-block;
                }
            </style>
            <?php } ?>
        <?php } ?>
        <br />
        <div class="wpil-is-tooltipped wpil-no-scale wpil-tooltip-no-position" data-wpil-tooltip-read-time="5500" style="display:inline-block;" <?php echo Wpil_Toolbox::generate_tooltip_text('outbound-suggestions-link-post-type'); ?>>
            <input type="checkbox" name="select_post_types" id="field_select_post_types" class="wpil-suggestion-input" data-suggestion-input-initial-value="<?php echo !empty($select_post_types) ? 1: 0;?>" <?=!empty($select_post_types) ? 'checked' : ''?>> <label for="field_select_post_types"><?php esc_html_e('Select the Post Types to use in Suggestions', 'wpil'); ?></label>
            <br>
            <div class="select_post_types-aux wpil-aux">
                <select multiple name="selected_post_types" class="wpil-suggestion-input wpil-suggestion-multiselect" data-suggestion-input-initial-value="<?php echo implode(',', $selected_post_types);?>" style="width: 400px;">
                    <?php foreach ($post_types as $post_type => $lable){ ?>
                        <option value="<?php echo $post_type; ?>" <?php echo (in_array($post_type, $selected_post_types, true))?'selected':''; ?>><?php esc_html_e(ucfirst($lable))?></option>
                    <?php } ?>
                </select>
                <br>
                <br>
            </div>
        </div>
        <br />
        <br />
        <div>
            <?php 
            $has_key = Wpil_Settings::get_linkwhisper_ai_active();
            $show_toggle = true;
            if(Wpil_Settings::can_do_ai_powered_suggestions()){
                $possible = true;
                $message = ''; //Wpil_Settings::get_linkwhisper_ai_active() ? '(This will use a small amount of credit)': '(AI Powered Suggestions will incur a very small charge from OpenAI)';
            } else if($has_key && !in_array('4', Wpil_Settings::get_selected_ai_batch_processes())) {
                $possible = false;
                $settings = '<a href="' . admin_url("admin.php?page=link_whisper_settings&tab=ai-settings") . '">' . esc_html__('go to the AI Settings', 'wpil') . '</a>';
                $message = sprintf(esc_html__('To use the AI Powered Suggestions, please enable "AI Relation Analysis" from the Link Whisper AI Settings and perform a scan of the site.', 'wpil'), $settings);
            } else if($has_key) {
                $possible = false;
                $settings = '<a href="' . admin_url("admin.php?page=link_whisper_settings&tab=ai-settings") . '">' . esc_html__('go to the AI Settings', 'wpil') . '</a>';
                $message = 
                    esc_html__('Unfortunately, Link Whisper hasn\'t processed enough posts with the "AI Relation Analysis" to be able to use the AI-Powered Suggestions effectively.', 'wpil') . 
                    '<br><br>' . 
                    esc_html__('At a minimum, Link Whisper needs 10% of the posts to be processed.', 'wpil') .
                    '<br><br>' . 
                    sprintf(esc_html__('Please %s, and run the AI Processing to scan the site.', 'wpil'), $settings);
            } else {
                $possible = false;
                $show_toggle = false;
                $message = esc_html__(
                    'Want to use suggestions powered by AI?!', 'wpil') . 
                    '<br><br>' . 
                    sprintf(esc_html__('Please connect Link Whisper to %s, and then %s.', 'wpil'), '<a href="https://linkwhisper.com/knowledge-base/how-do-i-get-started-with-link-whisper-ai/">' . __('our AI', 'wpil') . '</a>', '<a href="https://linkwhisper.com/knowledge-base/how-do-i-have-openai-process-my-sites-data/">' . __('run an AI Scan', 'wpil') . '</a>');
            } ?>
            <div class="ai-powered-suggestion-container">
                <?php if($show_toggle){ ?>
                <label style="font-weight: bold; font-size: 16px !important; display: inline-block; <?php echo (!$possible) ? 'font-style: italic; opacity: 0.9;': '';?>"><?php _e('Enable AI-Powered Suggestions', 'wpil'); ?><input type="checkbox" id="wpil_use_ai_suggestions" style="margin-left:10px;" class="wpil-slider-checkbox wpil-suggestion-input" data-nonce="<?php echo wp_create_nonce(wp_get_current_user()->ID . 'ai-suggestion-change-nonce'); ?>" data-suggestion-input-initial-value="<?php echo !empty($ai_use_ai_suggestions) ? 1: 0;?>" <?=!empty($ai_use_ai_suggestions && $possible)?'checked':''?> value="1" <?php echo (!$possible) ? 'disabled': '';?> /></label>
                <?php } ?>
                <div style="margin: 15px 0 0 0; font-style: italic; font-weight: 600; max-width: 340px;">
                    <?php echo $message; ?>
                </div>
            </div>
        </div>
        <br />
        <br />
        <button id="wpil-regenerate-suggestions" class="button disabled wpil-is-tooltipped wpil-no-scale wpil-tooltip-no-position" <?php echo Wpil_Toolbox::generate_tooltip_text('outbound-suggestions-regenerate-suggestions'); ?> disabled><?php esc_html_e('Regenerate Suggestions', 'wpil'); ?></button>
        <?php if(!empty($select_post_types)){ ?>
        <style>
            .best_keywords .select_post_types-aux{
                display: inline-block;
            }
        </style>
        <?php } ?>
        <script>
            jQuery('.wpil-suggestion-multiselect').select2();
        </script>
        <br>
        <br>
        <a href="<?=esc_url($post->getLinks()->export)?>" target="_blank" class="wpil-is-tooltipped wpil-no-scale wpil-tooltip-no-position" data-wpil-tooltip-read-time="4500" <?php echo Wpil_Toolbox::generate_tooltip_text('outbound-suggestions-export-support'); ?>>Export Support Data</a><br>
        <a href="<?=esc_url($post->getLinks()->excel_export)?>" target="_blank" class="wpil-is-tooltipped wpil-no-scale wpil-tooltip-no-position" data-wpil-tooltip-read-time="4500" <?php echo Wpil_Toolbox::generate_tooltip_text('outbound-suggestions-export-excel'); ?>>Export Links to Excel</a><br>
        <!--<a class="wpil-export-suggestions" data-export-type="excel" data-suggestion-type="outbound" data-type="<?php echo $post->type; ?>" data-id="<?php echo $post->id; ?>" data-nonce="<?php echo wp_create_nonce(get_current_user_id() . 'export-suggestions-' . $post->id); ?>" href="#" target="_blank">Export Suggestion Data to Excel</a><br>-->
        <a class="wpil-export-suggestions wpil-is-tooltipped wpil-no-scale wpil-tooltip-no-position" data-wpil-tooltip-read-time="4500" <?php echo Wpil_Toolbox::generate_tooltip_text('outbound-suggestions-suggestion-data'); ?> data-export-type="csv" data-suggestion-type="outbound" data-type="<?php echo $post->type; ?>" data-id="<?php echo $post->id; ?>" data-nonce="<?php echo wp_create_nonce(get_current_user_id() . 'export-suggestions-' . $post->id); ?>" href="#" target="_blank">Export Suggestion Data to CSV</a>
    </div>
    <?php if (!empty($phrase_groups)){ ?>
        <div style="display: flex; flex-direction:column; float:right; justify-content: space-between;">
            <?=Wpil_Base::showVersion()?>
            <div style="display:inline-block;" class="wpil-is-tooltipped wpil-no-scale wpil-tooltip-no-position" <?php echo Wpil_Toolbox::generate_tooltip_text('outbound-suggestions-filter-date'); ?>>
                <label for="wpil-outbound-daterange" style="font-weight: bold; font-size: 16px !important; margin: 7px 0 8px; display: block; display: inline-block;"><?php esc_html_e('Filter by Date', 'wpil'); ?></label>
                <br/>
                <input id="wpil-outbound-daterange" type="text" name="daterange" class="wpil-date-range-filter" value="<?php echo date($filter_time_format, strtotime('Jan 1, 2000')) . ' - ' . date($filter_time_format, strtotime('today')); ?>">
            </div>
            <!--
            <label for="suggestion_show_ai_unrelated" style="font-weight: bold; font-size: 16px !important; margin: 18px 0 8px; display: block; display: inline-block;"><?php esc_html_e('Show AI Unrelated Suggestions', 'wpil'); ?></label>
            <br />
            <input type="checkbox" id="suggestion_show_ai_unrelated">
            <br />
            <br />-->
            <div class="wpil-is-tooltipped wpil-no-scale wpil-tooltip-no-position" style="display:inline-block" <?php echo Wpil_Toolbox::generate_tooltip_text('outbound-suggestions-filter-keywords'); ?>>
                <label for="suggestion_filter_field" style="font-weight: bold; font-size: 16px !important; margin: 7px 0 8px; display: block; display: inline-block;">
                    <?php esc_html_e('Filter by Keyword', 'wpil'); ?>
                </label> 
                <br />
                <textarea id="suggestion_filter_field" style="width: 100%;" rows="1"></textarea>
            </div>
            <?php if(Wpil_Settings::has_ai_enabled() && (!empty(Wpil_AI::get_calculated_embedding_data($post->id, $post->type)) || Wpil_Settings::get_use_ai_suggestions())){  ?>
            <div class="wpil-is-tooltipped wpil-no-scale wpil-tooltip-no-position" style="display:inline-block" <?php echo Wpil_Toolbox::generate_tooltip_text('outbound-suggestions-filter-ai-score'); ?>>
                <label for="field_ai_relatedness_threshold" style="font-weight: bold; font-size: 16px !important; margin: 7px 0 8px; display: block; display: inline-block;">
                    <?php _e('Filter by AI Score', 'wpil'); ?>
                </label>
                <input type="range" name="ai_relatedness_threshold" id="field_ai_relatedness_threshold" class="wpil-suggestion-input wpil-thick-range" min="<?php echo Wpil_Settings::get_ai_suggestion_relatedness_threshold(); ?>" max="1" step="0.001" value="0.00" data-suggestion-input-initial-value="<?php echo $ai_relatedness_threshold;?>">
                <div>
                    <span><?php _e('Minimum Relatedness Score:', 'wpil'); ?> </span><span class="wpil-embedding-relatedness-threshold"><?php echo (Wpil_Settings::get_ai_suggestion_relatedness_threshold() * 100) . '%'; ?></span>
                </div>
            </div>
            <?php }  ?>
            <div class="wpil-is-tooltipped wpil-no-scale wpil-tooltip-no-position" style="display: inline-block;" <?php echo Wpil_Toolbox::generate_tooltip_text('outbound-suggestions-sort-suggestions'); ?>>
                <label for="wpil-outbound-suggestions-sorting-select" style="font-weight: bold; font-size: 16px !important; margin: 7px 0 8px; display: block; display: inline-block;"><?php esc_html_e('Sort By', 'wpil'); ?></label>
                <div>
                    <select id="wpil-outbound-suggestions-sorting-select">
                        <?php if(Wpil_AI::has_calculated_embedding_data()){ ?>
                            <option value="wpil-ai-post-relatedness-score"><?php esc_html_e('AI Content Relatedness', 'wpil'); ?></option>
                            <?php if(!empty($ai_use_ai_suggestions)){ ?>
                            <option value="wpil-ai-sentence-relatedness-score"><?php esc_html_e('Sentence Match AI Score', 'wpil'); ?></option>
                            <?php } ?>
                        <?php } ?>
                        <option value="wpil-suggestion-score"><?php esc_html_e('Suggestion Score', 'wpil'); ?></option>
                        <option value="wpil-post-published-date"><?php esc_html_e('Publish Date', 'wpil'); ?></option>
                        <option value="wpil-inbound-internal-links"><?php esc_html_e('Inbound Internal Links', 'wpil'); ?></option>
                        <option value="wpil-outbound-internal-links"><?php esc_html_e('Outbound Internal Links', 'wpil'); ?></option>
                        <option value="wpil-outbound-external-links"><?php esc_html_e('Outbound External Links', 'wpil'); ?></option>
                    </select>
                    <select id="wpil-outbound-suggestions-sorting-select-direction">
                        <option value="desc"><?php esc_html_e('Desc', 'wpil'); ?></option>
                        <option value="asc"><?php esc_html_e('Asc', 'wpil'); ?></option>
                    </select>
                </div>
            </div>
            <br>
            <label style="font-weight: bold; font-size: 16px !important; display: inline-block;"><?php _e('Show Expanded Post Details', 'wpil'); ?><input type="checkbox" id="wpil_show_expanded_details" style="margin-left:10px;" class="wpil-slider-checkbox wpil-suggestion-input" value="1" <?php checked(Wpil_Settings::get_show_expanded_suggestion_details()) ?> /></label>
        </div>
        <script>
            var rows = jQuery('tr[data-wpil-sentence-id]');
            jQuery('#wpil-outbound-daterange').on('apply.wpil-daterangepicker, hide.wpil-daterangepicker', function(ev, picker) {
                var format = '<?php echo Wpil_Toolbox::convert_date_format_for_js() ?>';
                jQuery(this).val(picker.startDate.format(format) + ' - ' + picker.endDate.format(format));
                var start = picker.startDate.unix();
                var end   = picker.endDate.unix();

                rows.each(function(index, element){
                    var suggestions = jQuery(element).find('.dated-outbound-suggestion');
                    var first = true;
                    suggestions.each(function(index, element2){
                        var elementTime = jQuery(element2).data('wpil-post-published-date');
                        var checkbox = jQuery(element2).find('input'); // wpil_dropdown checkbox for the current suggestion, not the suggestion's checkbox

                        if(!start || (start < elementTime && elementTime < end)){
                            jQuery(element2).removeClass('wpil-outbound-date-filtered');

                            // check the first visible suggested post 
                            if(first && checkbox.length > 0){
                                checkbox.trigger('click');
                                first = false;
                            }
                        }else{
                            jQuery(element2).addClass('wpil-outbound-date-filtered');

                            // if this is a suggestion in a collapsible box, uncheck it
                            if(checkbox.length > 0){
                                checkbox.prop('checked', false);
                            }
                        }
                    });

                    // if all of the suggestions have been hidden
                    if(suggestions.length === jQuery(element).find('.dated-outbound-suggestion.wpil-outbound-date-filtered').length){
                        // hide the suggestion row and uncheck it's checkboxes
                        jQuery(element).css({'display': 'none'});
                        jQuery(element).find('.chk-keywords').prop('checked', false);
                    }else{
                        // if not, make sure the suggestion row is showing
                        jQuery(element).css({'display': 'table-row'});
                    }
                });

                // handle the results of hiding any posts
                handleHiddenPosts();
            });

            jQuery('#wpil-outbound-daterange').on('cancel.wpil-daterangepicker', function(ev, picker) {
                jQuery(this).val('');
                jQuery('.wpil-outbound-date-filtered').removeClass('wpil-outbound-date-filtered');
            });

            jQuery('#wpil-outbound-daterange').daterangepicker({
                autoUpdateInput: false,
                linkedCalendars: false,
                locale: {
                    cancelLabel: 'Clear',
                    format: '<?php echo Wpil_Toolbox::convert_date_format_for_js() ?>'
                }
            });

            /**
             * Handles the table display elements when the date range changes
             **/
            function handleHiddenPosts(){
                if(jQuery('.chk-keywords:visible').length < 1){
                    // hide the table elements
                    jQuery('.wp-list-table thead, .sync_linking_keywords_list, .wpil_inbound_links_button').css({'display': 'none'});
                    // make sure the "Check All" box is unchecked
                    jQuery('.inbound-check-all-col input, #select_all').prop('checked', false);
                    // show the "No matches" message
                    jQuery('.wpil-no-posts-in-range').css({'display': 'table-row'});
                }else{
                    // show the table elements
                    jQuery('.wp-list-table thead').css({'display': 'table-header-group'});
                    jQuery('.sync_linking_keywords_list, .wpil_inbound_links_button').css({'display': 'inline-block'});
                    // hide the "No matches" message
                    jQuery('.wpil-no-posts-in-range').css({'display': 'none'});
                }
            }

            
            jQuery('#wpil-outbound-suggestions-sorting-select, #wpil-outbound-suggestions-sorting-select-direction').on('change', function(){
                var sort = jQuery('#wpil-outbound-suggestions-sorting-select').val(),
                    direction = jQuery('#wpil-outbound-suggestions-sorting-select-direction').val();
                    if(sort === 'wpil-ai-post-relatedness-score' || sort === 'wpil-ai-sentence-relatedness-score'){
                        jQuery('#field_ai_relatedness_threshold').trigger('keyup');
                    }
                    sortDropdownSuggestions(sort, direction);
                    sortSuggestionEntries(sort, direction);
            });

            /**
             * Sorts the Outbound suggestions in the dropdowns according to the user's selection criteria
             **/
            function sortDropdownSuggestions(sort, direction) {
                jQuery('.wpil-content').each(function(index, element){
                    var list = element.querySelector('ul'),
                        relatednessScore = jQuery('#field_ai_relatedness_threshold').val();

                    // Detach ul to avoid live DOM manipulations
                    var detachedList = list.parentNode.removeChild(list);

                    var rows = Array.from(detachedList.querySelectorAll('li'));
                    rows.sort(function(a, b) {

                        var scoreA = parseFloat(a.getAttribute('data-' + sort));
                            scoreB = parseFloat(b.getAttribute('data-' + sort));

                        if(direction === 'desc'){
                            return scoreB - scoreA;
                        }else{
                            return scoreA - scoreB;
                        }
                    });

                    // Re-append rows to the detached list
                    rows.forEach(function(row) {
                        detachedList.appendChild(row);
                    });
                    
                    // Reattach the list to the table
                    element.appendChild(detachedList);

                    // if there isn't a suggestion currently selected
                    if( rows.length > 0 && 
                        !jQuery(this).parents('tr').find('td.sentences input.chk-keywords:checked').length &&
                        jQuery(rows[0]).find('input[type="radio"]').data('wpil-ai-relatedness-score') > relatednessScore
                    ){
                        // set the top sentence as the one to display in the dropdown
                        jQuery(rows[0]).find('input[type="radio"]').click();
                    }
                });
            }

            /**
             * Sorts the Outbound suggestion texts according to the user's selection criteria
             **/
            function sortSuggestionEntries(sort, direction) {
                jQuery('.wpil-content').each(function(index, element){
                    var table = document.getElementById('tbl_keywords');
                    var tbody = table.querySelector('tbody');

                    // tag the top sentences so we can sort them
                    jQuery('td.sentences .sentence:visible').addClass('wpil-sorting-sentences');

                    // Detach tbody to avoid live DOM manipulations
                    var detachedTbody = tbody.parentNode.removeChild(tbody);

                    var rows = Array.from(detachedTbody.querySelectorAll('tr'));
                        rows.sort(function(a, b) {

                            if(jQuery(a).hasClass('wpil-no-posts-in-range')){
                                return -1;
                            }else if(jQuery(b).hasClass('wpil-no-posts-in-range')){
                                return 1;
                            }

                            var selectedId = jQuery(a).find('.wpil-sorting-sentences').data('id');
                            a = jQuery(a).find('td .dated-outbound-suggestion[data-id="'+selectedId+'"]').get()[0];
                            var selectedId = jQuery(b).find('.wpil-sorting-sentences').data('id');
                            b = jQuery(b).find('td .dated-outbound-suggestion[data-id="'+selectedId+'"]').get()[0];

                            if(!a){
                                return -1;
                            }else if(!b){
                                return 1;
                            }

                            if(direction === 'desc'){
                                return parseFloat(b.getAttribute('data-' + sort)) - parseFloat(a.getAttribute('data-' + sort));
                            }else{
                                return parseFloat(a.getAttribute('data-' + sort)) - parseFloat(b.getAttribute('data-' + sort));
                            }
                        });

                    // Re-append rows to the detached tbody
                    rows.forEach(function(row) {
                        detachedTbody.appendChild(row);
                    });

                    // Reattach the tbody to the table
                    table.appendChild(detachedTbody);

                    // and remove our sorting tag
                    jQuery('.wpil-sorting-sentences').removeClass('wpil-sorting-sentences');
                });
            }
        </script>
    <?php } ?>
    <?php require WP_INTERNAL_LINKING_PLUGIN_DIR . 'templates/table_suggestions.php'; ?>
</div>
<div class="wpil_notice" id="wpil_message" style="text-align: center; margin-top: 20px; <?php if(empty($phrases)){ echo 'display: none;'; } ?>">
    <p><?php _e('Tip: Link Whisper Premium automatically adds the links to the post content. All you have to do is select the links you want to add and click the "Add Links" button!', 'wpil'); ?></p>
    <p><a href="<?php echo esc_url(WPIL_STORE_URL); ?>" target="blank"><?php _e('Read more about upgrading!', 'wpil'); ?></a></p>
</div>

