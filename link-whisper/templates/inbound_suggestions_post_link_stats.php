<?php

$table = new Wpil_Table_Report;

?>
<div id="wpil-link-stats-metabox" class="categorydiv wpil_styles">
    <script>
        jQuery(function($) {
            $(document).on('click', '.wpil-panel-subaction, .wpil-collapsible-wrapper.wpil-activity-activate', function(e){
                if($(e.target).hasClass('add-outbound-internal-links') || $(e.target).hasClass('add-inbound-internal-links')){
                    return;
                }

                var actionPanel = $('.wpil-report-action-panel-wrapper.open'),
                    action = $(this);
                actionPanel.animate({'right': '-600px'}, 500, function(){
                    actionPanel.removeClass('active-1 open');
                });
                
                $('.wpil-activity-panel').empty();
                ajaxPullLinkData(action);
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
    <table class="wp-list-table widefat fixed striped table-view-list linkingstats sticky-ignore">
        <thead>
            <tr>
                <th scope="col" id="wpil_links_inbound_internal_count" class="manage-column column-wpil_links_inbound_internal_count">
                    <a href="#"><span><?php esc_html_e('Inbound internal links', 'wpil'); ?></span></a>
                </th>
                <th scope="col" id="wpil_links_outbound_internal_count" class="manage-column column-wpil_links_outbound_internal_count">
                    <a href="#"><span><?php esc_html_e('Outbound internal links', 'wpil'); ?></span></a>
                </th>
                <th scope="col" id="wpil_links_outbound_external_count" class="manage-column column-wpil_links_outbound_external_count">
                    <a href="#"><span><?php esc_html_e('Outbound external links', 'wpil'); ?></span></a>
                </th>
            </tr>
        </thead>
        <tbody id="the-list" data-wp-lists="list:linkingstats">
            <tr>
                <td class="wpil_links_inbound_internal_count column-wpil_links_inbound_internal_count" data-colname="Inbound internal links">
                    <?php
                        $item = array('post' => $post, 'wpil_links_inbound_internal_count' => true);
                        echo $table->column_default($item, 'wpil_links_inbound_internal_count');
                    ?>
                </td>
                <td class="wpil_links_outbound_internal_count column-wpil_links_outbound_internal_count" data-colname="Outbound internal links">
                    <?php
                        $item = array('post' => $post, 'wpil_links_outbound_internal_count' => true);
                        echo $table->column_default($item, 'wpil_links_outbound_internal_count');
                    ?>
                </td>
                <td class="wpil_links_outbound_external_count column-wpil_links_outbound_external_count" data-colname="Outbound external links">
                    <?php
                        $item = array('post' => $post, 'wpil_links_outbound_external_count' => true);
                        echo $table->column_default($item, 'wpil_links_outbound_external_count');

                    ?>
                </td>
            </tr>
        </tbody>
    </table>
</div>