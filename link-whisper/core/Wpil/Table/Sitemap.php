<?php

if (!class_exists('WP_List_Table')) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Wpil_Table_Sitemap extends WP_List_Table
{
    public $data = array();
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

    }

    function get_columns()
    {
        
    }

    function display()
    {
        echo '<div class="tablenav top">';
        $this->extra_tablenav("top");
        echo '</div>';
        foreach($this->data as $id => $map){
            echo '<input id="wpil-sitemap-' . $id . '" type="hidden" class="wpil-sitemap-data" value="' . esc_attr(json_encode($map)) . '">';
        }
    }

    function prepare_items()
    {
        if(!defined('WPIL_LOADING_REPORT')){
            define('WPIL_LOADING_REPORT', true);
        }

        $this->data = Wpil_Sitemap::get_data();
    }

    /**
     * Displays the search box.
     *
     * @param string $text     The 'submit' button label.
     * @param string $input_id ID attribute value for the search input field.
     */
    public function search_box( $text, $input_id ) {

    }

    function extra_tablenav( $which ) {
        if ($which == "top") {
            $sitemaps = Wpil_Sitemap::get_sitemap_list();
            if(empty($sitemaps)){
                return;
            }
            ?>
            <div class="actions bulkactions" id="wpil_sitemap_table_filter">
                <select class="wpil-sitemap-filter-select wpil-is-tooltipped wpil-no-scale" <?php echo Wpil_Toolbox::generate_tooltip_text('visual-sitemap-report-select-maps'); ?> name="sitemap_type">
                    <?php
                    echo '<option value="0">' . esc_html__('Select Sitemap', 'wpil') . '</option>';
                    foreach($sitemaps as $map_data){
                        if(empty($map_data)){
                            continue;
                        }
                        echo '<option value="' . intval($map_data->sitemap_id) . '">' . esc_html($map_data->sitemap_name) . '</option>';
                    }
                    ?>
                </select>
                <span class="button-primary wpil-sitemap-display-map disabled wpil-is-tooltipped" <?php echo Wpil_Toolbox::generate_tooltip_text('visual-sitemap-report-display-map'); ?> style="margin-right:20px;user-select: none;" disabled="disabled"><?php esc_html_e('Display Sitemap', 'wpil'); ?></span>
                <span class="button-primary wpil-sitemap-manage-map wpil-is-tooltipped" <?php echo Wpil_Toolbox::generate_tooltip_text('visual-sitemap-report-manage-sitemaps'); ?> style="margin-right:20px;user-select: none;"><?php esc_html_e('Manage Custom Sitemaps', 'wpil'); ?></span>
                <input type="hidden" class="post-filter-nonce" value="<?php echo wp_create_nonce(get_current_user_id() . 'wpil_filter_nonce'); ?>">
                <input id="wpil-sitemap-search" class="wpil-is-tooltipped" data-wpil-tooltip-read-time="3500" <?php echo Wpil_Toolbox::generate_tooltip_text('visual-sitemap-report-filter-maps'); ?> type="text" placeholder="<?php esc_attr_e('Filter Sitemap Data', 'wpil');?>">
                <div class="wpil-is-tooltipped" style="float:right; margin-right: 20px;" data-wpil-tooltip-read-time="3500" <?php echo Wpil_Toolbox::generate_tooltip_text('visual-sitemap-report-labels'); ?>>
                    <input class="wpil-sitemap-label-toggle button-primary" type="button" data-wpil-sitemap-label-toggle="0" value="<?php esc_attr_e('Hide Labels', 'wpil');?>">
                    <input class="wpil-sitemap-label-toggle button-primary" style="display:none;" type="button" data-wpil-sitemap-label-toggle="1" value="<?php esc_attr_e('Show Labels', 'wpil');?>">
                </div>
            </div>
            <?php
        }
    }
}