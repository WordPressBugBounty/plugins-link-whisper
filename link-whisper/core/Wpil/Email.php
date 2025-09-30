<?php

/**
 * Class Wpil_Email
 */
class Wpil_Email
{
    static $singular_email_types = array(
//        'year-in-review',
        'wizard-complete'
    );
    static $recurring_email_types = array(
        'regular-update',
//        'feature-notification'
    );

    /**
     * Register services
     */
    public function register()
    {
        self::init_cron();
    }

    /**
     * Inits cron
     **/
    public static function init_cron(){
        if(Wpil_Settings::email_notifications_are_enabled()){
            add_action('admin_init', array(__CLASS__, 'schedule_email_check'));
            add_action('wpil_email_cron', array(__CLASS__, 'cron_email_check'));
        }else{
            self::clear_cron_schedules();
        }
    }

    /**
     * Schedules the broken link checks if the user hasn't disabled checking.
     * If the user has, then it disables the checks
     **/
    public static function schedule_email_check(){
        if(Wpil_Settings::email_notifications_are_enabled()){
            if(!wp_get_schedule('wpil_email_cron')){
                wp_schedule_event(time(), 'hourly', 'wpil_email_cron');
            }
        }elseif(wp_get_schedule('wpil_email_cron')){
            self::clear_cron_schedules();
        }
    }

    public static function clear_cron_schedules(){
        $timestamp = wp_next_scheduled('wpil_email_cron');
        wp_unschedule_event($timestamp, 'wpil_email_cron');
    }

    /**
     * Uses the WP cron to see if we have an email that needs to go out.
     * If we do, then it fires it off.
     * 
     * @TODO:
     * Make sure that it doens't fire off two emails at the same time
     * 
     **/
    public static function cron_email_check(){
        // if the notifications have been disabled
        if(!Wpil_Settings::email_notifications_are_enabled()){
            // clear the schedules here too
            self::clear_cron_schedules();
            return;
        }

        $last_sends = get_option('wpil_email_notification_record', array());

        // if there are no sends
        if(empty($last_sends)){ // TODO: Rework into updator to make sure that all the recurring events are tracked
            // assemble a list of emails and start the clocks
            foreach(self::$recurring_email_types as $type){
                $last_sends[$type] = time();
            }

            // update the record for the next go round
            update_option('wpil_email_notification_record', $last_sends);
        }

        // remove any logged one-offs
        foreach(self::$singular_email_types as $singular){
            if(isset($last_sends[$singular])){
                unset($last_sends[$singular]);
            }
        }

        // find the most recent send
        $most_recent = 0;
        foreach($last_sends as $time){
            if($time > $most_recent){
                $most_recent = $time;
            }
        }

        // if we've sent an email in the past day
        if(time() - $most_recent < DAY_IN_SECONDS){
            // exit
            return;
        }

        // get the sending frequency for the recurring emails
        $recurring_freqs = self::get_recurring_email_send_frequency();

        // sort the emails by their sending dates
        asort($last_sends);

        // go over the sends
        $next_up = null;
        foreach($last_sends as $id => $time){
            // if the time of last send + frequency buffer is less than the current time
            if(isset($recurring_freqs[$id]) && ($time + $recurring_freqs[$id]) < time()){
                // tee up the email
                $next_up = $id;
                // and exist the loop
                break;
            }
        }

        // if we've found our email
        if(!empty($next_up)){
            // send it
            self::send_email_notification($next_up);
        }
    }

    public static function get_recurring_email_send_frequency(){
        return get_option('wpil_email_notification_frequency', array(
            'regular-update' => DAY_IN_SECONDS * 30,
            //'feature-notification' => DAY_IN_SECONDS * 14,
        ));
    }

    public static function send_email_notification($email_id = ''){
        if(empty($email_id)){
            return;
        }

        $email = self::get_email($email_id);

        if(!empty($email['subject']) && !empty($email['body'])){
            self::send_email($email, $email_id);
        }
    }

    /**
     * Gets all email content by id
     **/
    public static function get_email($email_id = ''){
        if(empty($email_id)){
            return false;
        }

        $email = array(
            'subject' => self::build_email_subject($email_id),
            'body' => self::build_email_body($email_id)
        );

        return $email;
    }

    public static function send_email($email, $email_id = ''){
        // get the destination emails
        $addresses = self::get_email_notification_addresses();

        // set the email headers to allow HTML
        $headers = array('Content-Type: text/html; charset=UTF-8');
    
        // if we have addresses
        if(!empty($addresses)){
            // go over each one
            foreach($addresses as $address){
                // and send the mail
                wp_mail($address, $email['subject'], $email['body'], $headers);
            }
        }

        if(!empty($email_id)){
            $notification_record = get_option('wpil_email_notification_record', array());
            $notification_record[$email_id] = time();
            update_option('wpil_email_notification_record', $notification_record);
        }
    }

    /**
     * Gets the emails subject
     **/
    public static function build_email_subject($email_id = ''){
        $subject = '';
        switch($email_id){
            // recurring emails
            case 'regular-update':
                $subject = 'Hey! Your monthly Link Health Report is ready!'; // TODO: Maybe translate some day
                break;
            // one-off emails
            case 'wizard-complete':
                $subject = 'Woohoo! Link Whisper\'s One Click Setup is complete and your links are ready!';
                break;
        }

        return $subject;
    }

    /**
     * Builds the email body based on a template
     **/
    public static function build_email_body($email_id = ''){
        $body = '';

        switch($email_id){
            // recurring emails
            case 'regular-update':
                $body = self::get_status_email_content();
                break;
            // one-off emails
            case 'wizard-complete':
                $body = self::get_wizard_email_content();
                break;
        }

        return $body;
    }

    public static function get_email_notification_addresses(){
        return apply_filters('wpil_email_notification_addresses', array(get_option('admin_email', []))); // ATM we're just sending the notice to the site admin
    }

    public static function get_status_email_content(){
        $problem_counter = 0;

        // link density
        $link_density = Wpil_Dashboard::get_percent_of_posts_hitting_link_targets();
        $density_status = 'tag-positive';
        $density_subtext = __('Great! The majority of your posts are linked enough.');
        if(!empty($link_density['percent'])){
            if($link_density['percent'] > 80){
                $density_status = 'tag-positive';
                $density_subtext = __('Great! The majority of your posts are linked enough.');
            }elseif($link_density['percent'] > 60){
                $density_status = 'tag-neutral';
                $density_subtext = __('Most of the site\'s posts are linked enough.');
            }else{
                $problem_counter++;
                $density_status = 'tag-negative';
                $density_subtext = __('Uh oh, the majority of the site\'s posts aren\'t linked enough.');
            }
        }

        // broken links
        $broken_link_count = Wpil_Dashboard::getBrokenLinksCount();
        $broken_link_percentage = 0;
        $broken_link_status = 'tag-positive';
        $broken_link_subtext = __('Perfect! There aren\'t any broken links on the site.');
        if(!empty($broken_link_count)){
            $total_links = Wpil_Dashboard::getLinksCount();
            if(!empty($total_links)){
                $broken_link_percentage = round($broken_link_count / $total_links, 2) * 100;
            }
        }

        if($broken_link_percentage == 0){
            $broken_link_status = 'tag-positive';
            $broken_link_subtext = __('Perfect! There aren\'t any broken links on the site.');
        }elseif($broken_link_percentage < 5){
            $broken_link_status = 'tag-positive';
            $broken_link_subtext = __('Good! There are a relatively low number of broken links on the site.');
        }elseif($broken_link_percentage < 10){
            $broken_link_status = 'tag-neutral';
            $broken_link_subtext = __('There are a number of broken links on the site that need fixing.');
        }else{
            $problem_counter++;
            $broken_link_status = 'tag-negative';
            $broken_link_subtext = __('Houston, we have a problem. There are a lot of broken links on the site');
        }

        $anchor_word_counts = Wpil_Dashboard::getAnchorPostCounts();
        $anchor_word_status = '';
        $anchor_word_subtext = '';
        $anchor_word_percent = '0%';
        if(!empty($anchor_word_counts['total']) && !empty($anchor_word_counts['filtered'])){
            $percentage = $anchor_word_counts['filtered']/$anchor_word_counts['total'];
            if($percentage > 0.80){
                $anchor_word_status = 'tag-positive';
                $anchor_word_subtext = __('Great! The majority of your link anchors are around 3 to 7 words in length.');
            }elseif($percentage > 0.60){
                $anchor_word_status = 'tag-neutral';
                $anchor_word_subtext = __('Most of the site\'s link anchors are around 3 to 7 words in length.');
            }else{
                $problem_counter++;
                $anchor_word_status = 'tag-negative';
                $anchor_word_subtext = __('The majority of the site\'s link anchors aren\'t around 3 to 7 words in length.');
            }

            $anchor_word_percent = (round($percentage, 2) * 100) . '%';
        }

        $posts_crawled = Wpil_Dashboard::getPostCount();
        $posts_crawled_status = (empty($posts_crawled)) ? 'tag-negative': 'tag-positive';

        $links_scanned = Wpil_Dashboard::getLinksCount();
        $links_scanned_status = (empty($links_scanned)) ? 'tag-negative': 'tag-positive';

        $orphaned_posts = Wpil_Dashboard::getOrphanedPostsCount();
        if(!empty($orphaned_posts)){
            $orphaned_posts_percentage = round($orphaned_posts/$posts_crawled, 2) * 100;
            if($orphaned_posts_percentage == 0){
                $orphaned_posts_status = 'tag-positive';
                $orphaned_posts_subtext = esc_html__('Awesome! There are no orphaned posts on the site.', 'wpil');
            }elseif($orphaned_posts_percentage < 5){
                $orphaned_posts_status = 'tag-positive';
                $orphaned_posts_subtext = esc_html__('Awesome! There are very few orphaned posts on the site.', 'wpil');
            }elseif($orphaned_posts_percentage < 10){
                $orphaned_posts_status = 'tag-neutral';
                $orphaned_posts_subtext = esc_html__('There are a number of orphaned posts that need some links pointing to them.', 'wpil');
            }else{
                $problem_counter++;
                $orphaned_posts_status = 'tag-negative';
                $orphaned_posts_subtext = esc_html__('Uh oh, looks like there are a lot of orphaned posts that need links!', 'wpil');
            }
        }else{
            $orphaned_posts_status = 'tag-positive';
            $orphaned_posts_subtext = esc_html__('Awesome! There are no orphaned posts on the site.', 'wpil');
        }

        $ai_active = Wpil_Settings::can_do_ai_powered_suggestions(); // if we have a API key and at least some of the embedding data processed
        $link_relatedness = 0;
        if($ai_active){
            $link_relatedness = Wpil_Dashboard::get_related_link_percentage();
            if($link_relatedness == 0){
                $link_relatedness_status = 'tag-neutral';
                $link_relatedness_subtext = esc_html__('Hmm, there\'s no data available. We might need to run a Link Scan.', 'wpil');
            }elseif($link_relatedness > 79){
                $link_relatedness_status = 'tag-positive';
                $link_relatedness_subtext = esc_html__('Amazing! The majority of the site\'s links are going to highly related posts.', 'wpil');
            }elseif($link_relatedness > 50){
                $link_relatedness_status = 'tag-neutral';
                $link_relatedness_subtext = esc_html__('Most of the site\'s links are pointing to topically related posts.', 'wpil');
            }else{
                $problem_counter++;
                $link_relatedness_status = 'tag-negative';
                $link_relatedness_subtext = esc_html__('Uh oh, it looks like most of the site\'s links aren\'t going to related posts.', 'wpil');
            }
        }else{
            $link_relatedness_status = 'tag-neutral';
            $link_relatedness_subtext = esc_html__('Link Whisper\'s AI is not enabled, so we can\'t tell how many links are going to topically related posts.', 'wpil');
        }

        $external_link_emphasis = Wpil_Dashboard::get_external_link_distribution(1);
        $external_link_emphasis_percent = 0;
        if(empty($external_link_emphasis) || !isset($external_link_emphasis[0]->representation)){
            $external_link_emphasis_status = 'tag-neutral';
            $external_link_emphasis_subtext = esc_html__('Hmm, there\'s no data available. We might need to run a Link Scan.', 'wpil');
        }else{
            $external_link_emphasis_percent = round($external_link_emphasis[0]->representation, 2) * 100;
            if($external_link_emphasis_percent < 60){
                $external_link_emphasis_status = 'tag-positive';
                $external_link_emphasis_subtext = esc_html__('Super! There aren\'t too many links going to the same external site.', 'wpil');
            }elseif($external_link_emphasis_percent < 70){
                $external_link_emphasis_status = 'tag-neutral';
                $external_link_emphasis_subtext = esc_html__('There are quite a few links going to the same external site.', 'wpil');
            }else{
                $problem_counter++;
                $external_link_emphasis_status = 'tag-negative';
                $external_link_emphasis_subtext = esc_html__('Hmm, it looks like the majority of the external outbound links are going to the same site. You might want to vary it up a little.', 'wpil');
            }
        }

        // link clicks
        $click_stats = Wpil_Dashboard::get_click_traffic_stats();
        $click_change_indicator = '(<span title="No change from previous 30 days">0%</span>)';
        $click_change_subtext = esc_html__('The number of clicks has remained consistent over the past 30 days.', 'wpil');
        $click_change_status = 'tag-neutral';
        if($click_stats['percent_change'] > 0){
            $click_change_status = 'tag-positive';
            $click_change_indicator = '(<span class="tag-positive" title="Clicks have gone up over the past 30 days">+' . $click_stats['percent_change'] . '%</span>)';
            $click_change_subtext = esc_html__('The number of clicks on the site has gone up over the past 30 days!', 'wpil');
        }elseif($click_stats['percent_change'] < 0){
            $click_change_status = 'tag-negative';
            $click_change_indicator = '(<span class="tag-negative" title="Clicks have gone down over the past 30 days">-' . $click_stats['percent_change'] . '%</span>)';
            $click_change_subtext = esc_html__('The number of clicks on the site have gone down over the past 30 days.', 'wpil');
        }

        ob_start();
?>
        <!DOCTYPE html>
        <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Link Whisper Monthly Report Email</title>
                <style>
                    @media only screen and (max-width: 600px) {
                    .responsive-table td {display: flex !important;width: 100% !important;padding-left: 0 !important;}
                    }
                </style>
            </head>
            <body>
                <div class="email-wrapper" style="background-color: #e8f3fc; font-family: Arial, sans-serif; padding: 20px; margin: 0;">
                    <div class="email-container" style="background-color: #ffffff; max-width: 880px; margin: 0 auto; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); padding: 20px;">
                        <div class="logo-container" style="text-align: center; margin-bottom: 20px;">
                            <img src="<?php echo WP_INTERNAL_LINKING_PLUGIN_URL . 'images\lw-icon.png'; ?>" style="height: 100px; max-width: 200px;" alt="<?php esc_attr_e('Link Whisper Logo', 'wpil'); ?>">
                        </div>

                        <div class="report-title" style="text-align: center; font-size: 24px; margin-bottom: 25px; color: #333;">Link Whisper Monthly Digest</div>
                            <p style="font-size: 20px; text-align: center;">We've crunched the latest numbers, just for you!</p>
                            <p style="font-size: 20px; text-align: center;">Here's how <a href="<?php echo esc_attr(admin_url('admin.php?page=link_whisper')); ?>" target="_blank"><?php echo esc_url(home_url()); ?></a> is doing.</p>
                            <table class="responsive-table" role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="max-width: 840px; margin: 0 auto;">
                                <tr>
                                    <td style="width: 100%; padding: 10px; font-weight:bold;">
                                        <table role="presentation" width="100%" style="position:relative; background-color: #ffffff; border: 1px solid #c3c4c7; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); padding: 15px;">
                                            <tr>
                                                <td style="font-size: 16px; color: #555;display: flex;width: 100%; font-weight:300;"><div style="margin-right: auto;">Posts Crawled: <?php echo $posts_crawled; ?></div><div><?php self::get_status_notice($posts_crawled_status); ?></div></td></tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="width: 100%; padding: 10px; font-weight:bold;">
                                        <table role="presentation" width="100%" style="position:relative; background-color: #ffffff; border: 1px solid #c3c4c7; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); padding: 15px;">
                                            <tr><td style="font-size: 16px; color: #555;display: flex;width: 100%; font-weight:300;"><div style="margin-right: auto;">Links Detected: <?php echo $links_scanned; ?></div><div><?php self::get_status_notice($links_scanned_status); ?></div></td></tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="width: 100%; padding: 10px; font-weight:bold;">
                                        <table role="presentation" width="100%" style="position:relative; background-color: #ffffff; border: 1px solid #c3c4c7; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); padding: 15px;">
                                            <tr><td style="font-size: 16px; color: #555;display: flex;width: 100%; font-weight:300;"><div style="margin-right: auto;">Link Coverage: <?php echo $link_density['percent'] . '%'; ?></div><div class="small-text" style="font-size: 13px; font-weight:300; font-style:italic; margin-right: auto; padding:0 5px; <?php self::display_small_text($density_status); ?>"><?php echo $density_subtext; ?></div><div><?php self::get_status_notice($density_status); ?></div></td></tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="width: 100%; padding: 10px; font-weight:bold;">
                                        <table role="presentation" width="100%" style="position:relative; background-color: #ffffff; border: 1px solid #c3c4c7; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); padding: 15px;">
                                            <tr>
                                                <td style="font-size: 16px; color: #555;display: flex;width: 100%; font-weight:300;">
                                                    <div style="margin-right: auto;">Link Clicks Tracked: <?php echo $click_stats['clicks_30'];?> 
                                                        <div class="percchangeclick" style="border: 1px solid #e2e2e2;width: fit-content; padding: 0px 6px 0 2px; display: inline-flex; margin-left: 2px; border-radius: unset;">
                                                        <?php if ($click_stats['percent_change'] > 0): ?>
                                                            <img height="16px;" width="16px" src="<?php echo WP_INTERNAL_LINKING_PLUGIN_URL . 'images/icons/png/up-arrow.png'; ?>">
                                                        <?php else: ?>
                                                            <img height="16px;" width="16px" src="<?php echo WP_INTERNAL_LINKING_PLUGIN_URL . 'images/icons/png/down-arrow.png'; ?>">
                                                        <?php endif; ?>
                                                        <span style="font-size:12px;"><?php echo abs($click_stats['percent_change']) . '%'; ?></span>
                                                        </div>
                                                    </div>
                                                    <div class="small-text" style="font-size: 13px; font-weight:300; font-style:italic; margin-right: auto; padding:0 5px; <?php self::display_small_text($click_change_status); ?>"><?php echo $click_change_subtext; ?></div>
                                                    <div><?php self::get_status_notice($click_change_status); ?></div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="width: 100%; padding: 10px; font-weight:bold;">
                                        <table role="presentation" width="100%" style="position:relative; background-color: #ffffff; border: 1px solid #c3c4c7; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); padding: 15px;">
                                            <tr>
                                                <td style="font-size: 16px; color: #555;display: flex;width: 100%; font-weight:300;"><div style="margin-right: auto;">Broken Links Found: <?php echo $broken_link_count; ?></div><div class="small-text" style="font-size: 13px; font-weight:300; font-style:italic; margin-right: auto; padding:0 5px; <?php self::display_small_text($broken_link_status); ?>"><?php echo $broken_link_subtext; ?></div><div><?php self::get_status_notice($broken_link_status); ?></div></td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="width: 100%; padding: 10px; font-weight:bold;">
                                        <table role="presentation" width="100%" style="position:relative; background-color: #ffffff; border: 1px solid #c3c4c7; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); padding: 15px;">
                                            <tr><td style="font-size: 16px; color: #555;display: flex;width: 100%; font-weight:300;"><div style="margin-right: auto;">Link Quality Score: <?php echo $link_relatedness . '%'; ?></div><div class="small-text" style="font-size: 13px; font-weight:300; font-style:italic; margin-right: auto; padding:0 5px; <?php self::display_small_text($link_relatedness_status); ?>"><?php echo $link_relatedness_subtext;?></div><div><?php self::get_status_notice($link_relatedness_status); ?></div></td></tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="width: 100%; padding: 10px; font-weight:bold;">
                                        <table role="presentation" width="100%" style="position:relative; background-color: #ffffff; border: 1px solid #c3c4c7; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); padding: 15px;">
                                            <tr><td style="font-size: 16px; color: #555;display: flex;width: 100%; font-weight:300;"><div style="margin-right: auto;">External Site Focus: <?php echo $external_link_emphasis_percent . '%'; ?></div><div class="small-text" style="font-size: 13px; font-weight:300; font-style:italic; margin-right: auto; padding:0 5px; <?php self::display_small_text($external_link_emphasis_status); ?>"><?php echo $external_link_emphasis_subtext;?></div><div><?php self::get_status_notice($external_link_emphasis_status); ?></div></td></tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="width: 100%; padding: 10px; font-weight:bold;">
                                        <table role="presentation" width="100%" style="position:relative; background-color: #ffffff; border: 1px solid #c3c4c7; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); padding: 15px;">
                                            <tr><td style="font-size: 16px; color: #555;display: flex;width: 100%; font-weight:300;"><div style="margin-right: auto;">Anchor Length Score: <?php echo esc_html($anchor_word_percent); ?></div><div class="small-text" style="font-size: 13px; font-weight:300; font-style:italic; margin-right: auto; padding:0 5px; <?php self::display_small_text($anchor_word_status); ?>"><?php echo $anchor_word_subtext; ?></div><div><?php self::get_status_notice($anchor_word_status); ?></div></td></tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            <br><br>
                            <p style="font-size: 22px; text-align: center;"><?php self::summary_message($problem_counter); ?></p>
                            <br><br>
                            <div class="dashboard-button-container" style="text-align: center;">
                                <a href="<?php echo esc_attr(admin_url('admin.php?page=link_whisper')); ?>" class="dashboard-button" style="min-height: 28px; height: auto; text-transform: uppercase; border: 1px solid transparent; box-shadow: none; text-shadow: none; background: #33c7fd; letter-spacing: 0.05em; font-weight: 600; color: #fff; white-space: nowrap; padding: 20px 40px !important; text-decoration: none; font-size: 20px !important;"><?php esc_html_e('Go to Dashboard', 'wpil'); ?></a>
                            </div>
                            <br><br>
                            <br><br>
                            <div style="text-align:center;">
                            <a href="https://linkwhisper.com/support/" target="_blank" style="color: #33c7fd; font-style: italic;">Have a question or need a hand with anything? Reach out to Support at LinkWhisper.com!</a>
                                <?php /*<br>
                                <br>
                                <a href="<?php echo esc_attr(admin_url("admin.php?page=link_whisper_settings&tab=advanced-settings&setting_highlight=wpil-send-email-notice#wpil-send-email-notice")); ?>" style="color:rgb(129, 129, 129); font-style: italic;">Want to stop these emails? Go here to turn them off.</a>
                                */ ?>
                            </div>
                            <br>
                        </div>
                    </div>
                </div>
            </body>
        </html>
<?php
        $email = ob_get_clean();

        return $email;
    }

    public static function get_status_notice($tag = ''){
        //$styles = 'margin: 5px 15px 2px; position: absolute; right: -5px; top: 5px; border: none; border-radius: 15px; color: white; font-weight: bold; padding: 5px 10px;';
        $styles = 'border: none; border-radius: 15px; color: white; font-weight: bold; padding: 5px 10px;';
        switch ($tag) {
            case 'tag-positive':
                $styles .= 'background: #00a05c;';
                $output = '<span class="notice good" style="'.$styles.'">Great</span>';
                break;
            case 'tag-neutral':
                $styles .= 'background: #ffac10;';
                $output = '<span class="notice neutral" style="'.$styles.'">OK</span>';
                break;
            case 'tag-negative':
            default:
                $styles .= 'background: #ff1e1e;';
                $output = '<span class="notice problem" style="'.$styles.'">Fix</span>';
                break;
        }

        echo $output;
    }

    /**
     * Checks the supplied status tag to see if we should display the small text
     **/
    public static function display_small_text($tag = ''){
        switch ($tag) {
            case 'tag-positive':
            case 'tag-neutral':
                $output = 'display: none;';
                break;
            case 'tag-negative':
            default:
                $output = '';
                break;
        }

        echo $output;
    }

    public static function get_small_text_color($tag = ''){
        switch ($tag) {
            case 'tag-positive':
                $output = 'color: #00a05c !important;';
                break;
            case 'tag-neutral':
                $output = 'color: #000000 !important;';
                break;
            case 'tag-negative':
            default:
                $output = 'color: #ff1e1e !important;';
                break;
        }

        echo $output;
    }

    public static function summary_message($problem_counter = 0){
        $message = '';
        if($problem_counter < 2){
            $message = 'Keep doing what you\'re doing, the site is healthy!';
        }elseif($problem_counter < 4){
            $message = 'Overall, there are a few challenges, but nothing we can\'t handle!';
        }else{
            $message = 'The site needs some TLC, but together we can take care of it!';
        }

        echo $message;
    }

    public static function get_wizard_email_content(){
        $problem_counter = 0;

        // link density
        $link_density = Wpil_Dashboard::get_percent_of_posts_hitting_link_targets();
        $density_status = 'tag-positive';
        $density_subtext = __('Great! The majority of your posts are linked enough.');
        if(!empty($link_density['percent'])){
            if($link_density['percent'] > 80){
                $density_status = 'tag-positive';
                $density_subtext = __('Great! The majority of your posts are linked enough.');
            }elseif($link_density['percent'] > 60){
                $density_status = 'tag-neutral';
                $density_subtext = __('Most of the site\'s posts are linked enough.');
            }else{
                $problem_counter++;
                $density_status = 'tag-negative';
                $density_subtext = __('Uh oh, the majority of the site\'s posts aren\'t linked enough.');
            }
        }
        
        // broken links
        $broken_link_count = Wpil_Dashboard::getBrokenLinksCount();
        $broken_link_percentage = 0;
        $broken_link_status = 'tag-positive';
        $broken_link_subtext = __('Perfect! There aren\'t any broken links on the site.');
        if(!empty($broken_link_count)){
            $total_links = Wpil_Dashboard::getLinksCount();
            if(!empty($total_links)){
                $broken_link_percentage = round($broken_link_count / $total_links, 2) * 100;
            }
        }

        if($broken_link_percentage == 0){
            $broken_link_status = 'tag-positive';
            $broken_link_subtext = __('Perfect! There aren\'t any broken links on the site.');
        }elseif($broken_link_percentage < 5){
            $broken_link_status = 'tag-positive';
            $broken_link_subtext = __('Good! There are a relatively low number of broken links on the site.');
        }elseif($broken_link_percentage < 10){
            $broken_link_status = 'tag-neutral';
            $broken_link_subtext = __('There are a number of broken links on the site that need fixing.');
        }else{
            $problem_counter++;
            $broken_link_status = 'tag-negative';
            $broken_link_subtext = __('Houston, we have a problem. There are a lot of broken links on the site');
        }

        $anchor_word_counts = Wpil_Dashboard::getAnchorPostCounts();
        $anchor_word_status = '';
        $anchor_word_subtext = '';
        $anchor_word_percent = '0%';
        if(!empty($anchor_word_counts['total']) && !empty($anchor_word_counts['filtered'])){
            $percentage = $anchor_word_counts['filtered']/$anchor_word_counts['total'];
            if($percentage > 0.80){
                $anchor_word_status = 'tag-positive';
                $anchor_word_subtext = __('Great! The majority of your link anchors are around 3 to 7 words in length.');
            }elseif($percentage > 0.60){
                $anchor_word_status = 'tag-neutral';
                $anchor_word_subtext = __('Most of the site\'s link anchors are around 3 to 7 words in length.');
            }else{
                $problem_counter++;
                $anchor_word_status = 'tag-negative';
                $anchor_word_subtext = __('The majority of the site\'s link anchors aren\'t around 3 to 7 words in length.');
            }

            $anchor_word_percent = (round($percentage, 2) * 100) . '%';
        }

        $posts_crawled = Wpil_Dashboard::getPostCount();
        $posts_crawled_status = (empty($posts_crawled)) ? 'tag-negative': 'tag-positive';

        $links_scanned = Wpil_Dashboard::getLinksCount();
        $links_scanned_status = (empty($links_scanned)) ? 'tag-negative': 'tag-positive';

        $orphaned_posts = Wpil_Dashboard::getOrphanedPostsCount();
        if(!empty($orphaned_posts)){
            $orphaned_posts_percentage = round($orphaned_posts/$posts_crawled, 2) * 100;
            if($orphaned_posts_percentage == 0){
                $orphaned_posts_status = 'tag-positive';
                $orphaned_posts_subtext = esc_html__('Awesome! There are no orphaned posts on the site.', 'wpil');
            }elseif($orphaned_posts_percentage < 5){
                $orphaned_posts_status = 'tag-positive';
                $orphaned_posts_subtext = esc_html__('Awesome! There are very few orphaned posts on the site.', 'wpil');
            }elseif($orphaned_posts_percentage < 10){
                $orphaned_posts_status = 'tag-neutral';
                $orphaned_posts_subtext = esc_html__('There are a number of orphaned posts that need some links pointing to them.', 'wpil');
            }else{
                $problem_counter++;
                $orphaned_posts_status = 'tag-negative';
                $orphaned_posts_subtext = esc_html__('Uh oh, looks like there are a lot of orphaned posts that need links!', 'wpil');
            }
        }else{
            $orphaned_posts_status = 'tag-positive';
            $orphaned_posts_subtext = esc_html__('Awesome! There are no orphaned posts on the site.', 'wpil');
        }

        $ai_active = Wpil_Settings::can_do_ai_powered_suggestions(); // if we have a API key and at least some of the embedding data processed
        $link_relatedness = 0;
        if($ai_active){
            $link_relatedness = Wpil_Dashboard::get_related_link_percentage();
            if($link_relatedness == 0){
                $link_relatedness_status = 'tag-neutral';
                $link_relatedness_subtext = esc_html__('Hmm, there\'s no data available. We might need to run a Link Scan.', 'wpil');
            }elseif($link_relatedness > 79){
                $link_relatedness_status = 'tag-positive';
                $link_relatedness_subtext = esc_html__('Amazing! The majority of the site\'s links are going to highly related posts.', 'wpil');
            }elseif($link_relatedness > 50){
                $link_relatedness_status = 'tag-neutral';
                $link_relatedness_subtext = esc_html__('Most of the site\'s links are pointing to topically related posts.', 'wpil');
            }else{
                $problem_counter++;
                $link_relatedness_status = 'tag-negative';
                $link_relatedness_subtext = esc_html__('Uh oh, it looks like most of the site\'s links aren\'t going to related posts.', 'wpil');
            }
        }else{
            $link_relatedness_status = 'tag-neutral';
            $link_relatedness_subtext = esc_html__('Link Whisper\'s AI is not enabled, so we can\'t tell how many links are going to topically related posts.', 'wpil');
        }

        $external_link_emphasis = Wpil_Dashboard::get_external_link_distribution(1);
        $external_link_emphasis_percent = 0;
        if(empty($external_link_emphasis) || !isset($external_link_emphasis[0]->representation)){
            $external_link_emphasis_status = 'tag-neutral';
            $external_link_emphasis_subtext = esc_html__('Hmm, there\'s no data available. We might need to run a Link Scan.', 'wpil');
        }else{
            $external_link_emphasis_percent = round($external_link_emphasis[0]->representation, 2) * 100;
            if($external_link_emphasis_percent < 60){
                $external_link_emphasis_status = 'tag-positive';
                $external_link_emphasis_subtext = esc_html__('Super! There aren\'t too many links going to the same external site.', 'wpil');
            }elseif($external_link_emphasis_percent < 70){
                $external_link_emphasis_status = 'tag-neutral';
                $external_link_emphasis_subtext = esc_html__('There are quite a few links going to the same external site.', 'wpil');
            }else{
                $problem_counter++;
                $external_link_emphasis_status = 'tag-negative';
                $external_link_emphasis_subtext = esc_html__('Hmm, it looks like the majority of the external outbound links are going to the same site. You might want to vary it up a little.', 'wpil');
            }
        }

        // link clicks
        $click_stats = Wpil_Dashboard::get_click_traffic_stats();
        $click_change_indicator = '(<span title="No change from previous 30 days">0%</span>)';
        $click_change_subtext = esc_html__('The number of clicks has remained consistent over the past 30 days.', 'wpil');
        $click_change_status = 'tag-neutral';
        if($click_stats['percent_change'] > 0){
            $click_change_status = 'tag-positive';
            $click_change_indicator = '(<span class="tag-positive" title="Clicks have gone up over the past 30 days">+' . $click_stats['percent_change'] . '%</span>)';
            $click_change_subtext = esc_html__('The number of clicks on the site has gone up over the past 30 days!', 'wpil');
        }elseif($click_stats['percent_change'] < 0){
            $click_change_status = 'tag-negative';
            $click_change_indicator = '(<span class="tag-negative" title="Clicks have gone down over the past 30 days">-' . $click_stats['percent_change'] . '%</span>)';
            $click_change_subtext = esc_html__('The number of clicks on the site have gone down over the past 30 days.', 'wpil');
        }

        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Link Whisper One Click Setup</title>
                <style>
                    @media only screen and (max-width: 600px) {
                    .responsive-table td {display: flex !important;width: 100% !important;padding-left: 0 !important;}
                    }
                </style>
            </head>
            <body>
                <div class="email-wrapper" style="background-color: #e8f3fc; font-family: Arial, sans-serif; padding: 20px; margin: 0;">
                    <div class="email-container" style="background-color: #ffffff; max-width: 880px; margin: 0 auto; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); padding: 20px;">
                        <div class="logo-container" style="text-align: center; margin-bottom: 20px;">
                            <img src="<?php echo WP_INTERNAL_LINKING_PLUGIN_URL . 'images\lw-icon.png'; ?>" style="height: 100px; max-width: 200px;" alt="<?php esc_attr_e('Link Whisper Logo', 'wpil'); ?>">
                        </div>
                        <div class="report-title" style="text-align: center; font-size: 24px; margin-bottom: 25px; color: #333;">Your internal links are setup!</div>
                            <p style="font-size: 20px; text-align: center;">Here is the link health report for: <a href="<?php echo esc_attr(admin_url('admin.php?page=link_whisper')); ?>" target="_blank"><?php echo esc_url(home_url()); ?></a></p>
                            <table class="responsive-table" role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="max-width: 840px; margin: 0 auto;">
                                <tr>
                                    <td style="width: 100%; padding: 10px; font-weight:bold;">
                                        <table role="presentation" width="100%" style="position:relative; background-color: #ffffff; border: 1px solid #c3c4c7; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); padding: 15px;">
                                            <tr>
                                                <td style="font-size: 16px; color: #555;display: flex;width: 100%; font-weight:300;"><div style="margin-right: auto;">Posts Crawled: <?php echo $posts_crawled; ?></div><div><?php self::get_status_notice($posts_crawled_status); ?></div></td></tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="width: 100%; padding: 10px; font-weight:bold;">
                                        <table role="presentation" width="100%" style="position:relative; background-color: #ffffff; border: 1px solid #c3c4c7; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); padding: 15px;">
                                            <tr><td style="font-size: 16px; color: #555;display: flex;width: 100%; font-weight:300;"><div style="margin-right: auto;">Links Detected: <?php echo $links_scanned; ?></div><div><?php self::get_status_notice($links_scanned_status); ?></div></td></tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="width: 100%; padding: 10px; font-weight:bold;">
                                        <table role="presentation" width="100%" style="position:relative; background-color: #ffffff; border: 1px solid #c3c4c7; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); padding: 15px;">
                                            <tr><td style="font-size: 16px; color: #555;display: flex;width: 100%; font-weight:300;"><div style="margin-right: auto;">Link Coverage: <?php echo $link_density['percent'] . '%'; ?></div><div class="small-text" style="font-size: 13px; font-weight:300; font-style:italic; margin-right: auto; padding:0 5px; <?php self::display_small_text($density_status); ?>"><?php echo $density_subtext; ?></div><div><?php self::get_status_notice($density_status); ?></div></td></tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="width: 100%; padding: 10px; font-weight:bold;">
                                        <table role="presentation" width="100%" style="position:relative; background-color: #ffffff; border: 1px solid #c3c4c7; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); padding: 15px;">
                                            <tr>
                                                <td style="font-size: 16px; color: #555;display: flex;width: 100%; font-weight:300;">
                                                    <div style="margin-right: auto;">Link Clicks Tracked: <?php echo $click_stats['clicks_30'];?> 
                                                        <div class="percchangeclick" style="border: 1px solid #e2e2e2;width: fit-content; padding: 0px 6px 0 2px; display: inline-flex; margin-left: 2px; border-radius: unset;">
                                                        <?php if ($click_stats['percent_change'] > 0): ?>
                                                            <img height="16px;" width="16px" src="<?php echo WP_INTERNAL_LINKING_PLUGIN_URL . 'images/icons/png/up-arrow.png'; ?>">
                                                        <?php else: ?>
                                                            <img height="16px;" width="16px" src="<?php echo WP_INTERNAL_LINKING_PLUGIN_URL . 'images/icons/png/down-arrow.png'; ?>">
                                                        <?php endif; ?>
                                                        <span style="font-size:12px;"><?php echo abs($click_stats['percent_change']) . '%'; ?></span>
                                                        </div>
                                                    </div>
                                                    <div class="small-text" style="font-size: 13px; font-weight:300; font-style:italic; margin-right: auto; padding:0 5px; <?php self::display_small_text($click_change_status); ?>"><?php echo $click_change_subtext; ?></div>
                                                    <div><?php self::get_status_notice($click_change_status); ?></div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="width: 100%; padding: 10px; font-weight:bold;">
                                        <table role="presentation" width="100%" style="position:relative; background-color: #ffffff; border: 1px solid #c3c4c7; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); padding: 15px;">
                                            <tr>
                                                <td style="font-size: 16px; color: #555;display: flex;width: 100%; font-weight:300;"><div style="margin-right: auto;">Broken Links Found: <?php echo $broken_link_count; ?></div><div class="small-text" style="font-size: 13px; font-weight:300; font-style:italic; margin-right: auto; padding:0 5px; <?php self::display_small_text($broken_link_status); ?>"><?php echo $broken_link_subtext; ?></div><div><?php self::get_status_notice($broken_link_status); ?></div></td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="width: 100%; padding: 10px; font-weight:bold;">
                                        <table role="presentation" width="100%" style="position:relative; background-color: #ffffff; border: 1px solid #c3c4c7; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); padding: 15px;">
                                            <tr><td style="font-size: 16px; color: #555;display: flex;width: 100%; font-weight:300;"><div style="margin-right: auto;">Link Quality Score: <?php echo $link_relatedness . '%'; ?></div><div class="small-text" style="font-size: 13px; font-weight:300; font-style:italic; margin-right: auto; padding:0 5px; <?php self::display_small_text($link_relatedness_status); ?>"><?php echo $link_relatedness_subtext;?></div><div><?php self::get_status_notice($link_relatedness_status); ?></div></td></tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="width: 100%; padding: 10px; font-weight:bold;">
                                        <table role="presentation" width="100%" style="position:relative; background-color: #ffffff; border: 1px solid #c3c4c7; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); padding: 15px;">
                                            <tr><td style="font-size: 16px; color: #555;display: flex;width: 100%; font-weight:300;"><div style="margin-right: auto;">External Site Focus: <?php echo $external_link_emphasis_percent . '%'; ?></div><div class="small-text" style="font-size: 13px; font-weight:300; font-style:italic; margin-right: auto; padding:0 5px; <?php self::display_small_text($external_link_emphasis_status); ?>"><?php echo $external_link_emphasis_subtext;?></div><div><?php self::get_status_notice($external_link_emphasis_status); ?></div></td></tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="width: 100%; padding: 10px; font-weight:bold;">
                                        <table role="presentation" width="100%" style="position:relative; background-color: #ffffff; border: 1px solid #c3c4c7; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); padding: 15px;">
                                            <tr><td style="font-size: 16px; color: #555;display: flex;width: 100%; font-weight:300;"><div style="margin-right: auto;">Anchor Length Score: <?php echo esc_html($anchor_word_percent); ?></div><div class="small-text" style="font-size: 13px; font-weight:300; font-style:italic; margin-right: auto; padding:0 5px; <?php self::display_small_text($anchor_word_status); ?>"><?php echo $anchor_word_subtext; ?></div><div><?php self::get_status_notice($anchor_word_status); ?></div></td></tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            <br><br>
                            <p style="font-size: 22px; text-align: center;"><?php self::summary_message($problem_counter); ?></p>
                            <br><br>
                            <div class="dashboard-button-container" style="text-align: center;">
                                <a href="<?php echo esc_attr(admin_url('admin.php?page=link_whisper')); ?>" class="dashboard-button" style="min-height: 28px; height: auto; text-transform: uppercase; border: 1px solid transparent; box-shadow: none; text-shadow: none; background: #33c7fd; letter-spacing: 0.05em; font-weight: 600; color: #fff; white-space: nowrap; padding: 20px 40px !important; text-decoration: none; font-size: 20px !important;"><?php esc_html_e('Go to Dashboard', 'wpil'); ?></a>
                            </div>
                            <br><br>
                            <br><br>
                            <div style="text-align:center;">
                                <a href="https://linkwhisper.com/support/" target="_blank" style="color: #33c7fd; font-style: italic;">Have a question or need a hand with anything? Reach out to Support at LinkWhisper.com!</a>
                                <?php /*
                                <br>
                                <br>
                                <a href="<?php echo esc_attr(admin_url("admin.php?page=link_whisper_settings&tab=advanced-settings&setting_highlight=wpil-send-email-notice#wpil-send-email-notice")); ?>" style="color:rgb(129, 129, 129); font-style: italic;">Want to stop these emails? Go here to turn them off.</a>
                                */ ?>
                            </div>
                            <br>
                        </div>
                    </div>
                </div>
            </body>
        </html>
        <?php
        $email = ob_get_clean();

        return $email;
    }

}
