<?php
$connected_to_oai = !empty(Wpil_Settings::getOpenAIKey());
?>
<script>
    document.addEventListener("DOMContentLoaded", () => {
        const connectBtn = document.getElementById("wpil-connect-ai-button");
        if (!connectBtn) return;

        connectBtn.addEventListener("click", (e) => {
            e.preventDefault();

            const authUrl = connectBtn.href;

            const width = 600;
            const height = 700;
            const left = (window.screen.width / 2) - (width / 2);
            const top = (window.screen.height / 2) - (height / 2);

            const popup = window.open(authUrl, 'LinkWhisperAIConnect', `width=${width},height=${height},top=${top},left=${left}`);

            if (!popup) {
            alert("Popup blocked! Please allow popups for this site to connect AI.");
            return;
            }

            // Check every second if the popup has closed
            const interval = setInterval(() => {
            if (popup.closed) {
                clearInterval(interval);
                // Call a function to check if auth was completed (or just reload)
                //window.location.reload(true); // or make an AJAX call to confirm before reload
                jQuery('.wpil-wizard-start-scan').trigger('click');
            }
            }, 1000);
        });
    });
</script>
<div class="wpil-setup-wizard wrap wpil_styles wizard-connect-openai wpil-wizard-page wpil-wizard-page-hidden">
    <div id="wpil-setup-wizard-progress-loading"><div id="wpil-setup-wizard-progress-loading-bar" style="width:calc(100%/3 * 2.5)"></div></div>
    <div id="wpil-setup-wizard-progress">
        <div class="complete"><a href="<?php echo admin_url('admin.php?page=link_whisper_wizard&wpil_wizard=connect-gsc');?>" class="wpil-wizard-link" data-wpil-wizard-link-id="connect-gsc"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Connect to Google Search Console', 'wpil'); ?></a></div>
        <div class="complete"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Connect to AI', 'wpil'); ?></div>
        <div><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Complete Installation', 'wpil'); ?></div>
    </div>
    <div class="wpil-setup-wizard-content" style="/*height: 600px;*/">
        <a href="<?php echo admin_url();?>" class="wpil-wizard-exit-button">EXIT WIZARD</a>
        <?php if($connected_to_oai || Wpil_Settings::get_linkwhisper_ai_active()){ ?>
            <div id="wpil-setup-wizard-heading-container">
                <img src="<?php echo WP_INTERNAL_LINKING_PLUGIN_URL . '/images/lw-icon.png' ?>" width="128px" height="128px">
                <h1 id="wpil-setup-wizard-heading"><?php esc_html_e('Connected to AI!', 'wpil'); ?></h1>
                <p class="wpil-setup-wizard-sub-heading"><?php esc_html_e('Link Whisper uses AI to make smarter link suggestions.', 'wpil'); ?></p>
                <p class="wpil-setup-wizard-sub-heading"><?php esc_html_e('You\'re connected to AI and have full access to Link Whisper\'s AI Features!', 'wpil'); ?></p>
            </div>
            <div>
            </div>
            <div>
                <a href="<?php echo admin_url('admin.php?page=link_whisper_settings&wpil_wizard=run-setup');?>" class="wpil-wizard-link button-primary wpil-setup-wizard-main-button" data-wpil-wizard-link-id="run-setup" style="font-size: 20px;"><?php esc_html_e('Awesome! Let\'s run the setup!', 'wpil'); ?></a>
                <br><br>
            </div>
        <?php }else{?>
            <div id="wpil-setup-wizard-heading-container">
                <img src="<?php echo WP_INTERNAL_LINKING_PLUGIN_URL . '/images/lw-icon.png' ?>" width="128px" height="128px">
                <h1 id="wpil-setup-wizard-heading"><?php esc_html_e('Connect to Link Whisper AI?', 'wpil'); ?></h1>
                <p class="wpil-setup-wizard-sub-heading"><?php esc_html_e('Link Whisper uses AI to make smarter link suggestions.', 'wpil'); ?></p>
                <p class="wpil-setup-wizard-sub-heading"><?php esc_html_e('If you don\'t want to use AI, no worries, we\'ll still set it up using our original keyword-based methods.', 'wpil'); ?></p>
            </div>
            <div>
                <div>
                </div>
                <br>
                <div style="position:relative; display:inline-block;">
                    <a id="wpil-connect-ai-button" href="<?php echo esc_url(Wpil_AI::get_linkwhisper_ai_auth_url(admin_url('admin.php?page=link_whisper_ai_subscription&origin=wizard')))?>" style="margin-top:15px; user-select: none; text-align: center;" class="button-primary wpil-setup-wizard-main-button wpil-wizard-activate-oai-button_old" data-wpil-nonce="<?php echo wp_create_nonce(get_current_user_id() . 'wpil_wizard_save_nonce'); ?>"><?php esc_html_e('Connect!', 'wpil'); ?></a>
                    <div style="display:none;" class="wpil-setup-wizard-loading la-ball-clip-rotate la-md"><div></div></div>
                </div>
            </div>
            <br><br>
            <div>
                <a href="<?php echo admin_url('admin.php?page=link_whisper_settings&wpil_wizard=run-setup');?>" class="wpil-wizard-link wpil-wizard-start-scan" data-wpil-wizard-link-id="run-setup" style="font-size: 20px;"><?php esc_html_e('Not right now, maybe later', 'wpil'); ?></a>
            </div>
        <?php } ?>
    </div>
</div>