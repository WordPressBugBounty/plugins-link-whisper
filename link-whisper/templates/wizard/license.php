<?php
    $wizard_logo = !empty($logo) ? $logo : plugin_dir_url(__DIR__).'../images/lw-icon.png';
    $wizard_ai_connected = isset($wizard_ai_connected) ? (bool) $wizard_ai_connected : (Wpil_Settings::get_linkwhisper_ai_active() && !empty(Wpil_Settings::get_linkwhisper_ai_user_id()));
    $current_user = wp_get_current_user();
    $wizard_ai_email = sanitize_email((string) get_user_meta(get_current_user_id(), 'wpil_wizard_ai_user_email', true));
    $wizard_connected_email = $wizard_ai_connected ? sanitize_email((string) Wpil_Settings::get_linkwhisper_ai_user_email()) : '';

    if(empty($wizard_ai_email)){
        $wizard_ai_email = !empty($current_user->user_email) ? sanitize_email($current_user->user_email) : '';
    }

    if(empty($wizard_connected_email)){
        $wizard_connected_email = $wizard_ai_email;
    }

    $wizard_ai_activation_token = get_user_meta(get_current_user_id(), 'wpil_wizard_ai_activation_token', true);
    $wizard_ai_state = ($wizard_ai_connected) ? 'connected' : ((!empty($wizard_ai_activation_token)) ? 'verification_required' : 'ready');
    $wizard_ai_nonce = wp_create_nonce(get_current_user_id() . 'wpil_wizard_save_nonce');
?>
<div class="bg-gray-100 wpil-setup-wizard wrap wpil_styles wizard-license wpil-wizard-page wpil-wizard-page-hidden" data-ai-state="<?php echo esc_attr($wizard_ai_state); ?>">
    <input type="hidden" id="wpil-setup-wizard-ai-connected" value="<?php echo $wizard_ai_connected ? '1' : '0'; ?>">
    <input type="hidden" id="wpil-wizard-ai-auth-nonce" value="<?php echo esc_attr($wizard_ai_nonce); ?>">
    <div>
        <style>
            .lw-gradient-bg {
                background: linear-gradient(90deg, #7F5AF0 0%, #2C6BFF 100%);
            }
            .lw-text-gradient {
                background: linear-gradient(90deg, #7F5AF0 0%, #2C6BFF 100%);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
            }
            .wpil-wizard-ai-email-input{
                width: 100%;
                border: 1px solid #d1d5db;
                border-radius: 0.85rem;
                padding: 14px 16px;
                font-size: 16px;
                line-height: 1.4;
                color: #111827;
                box-shadow: inset 0 1px 2px rgba(0,0,0,.03);
            }
            .wpil-wizard-ai-email-input:focus{
                outline: none;
                border-color: #7F5AF0;
                box-shadow: 0 0 0 3px rgba(127,90,240,.12);
            }
            .wpil-wizard-ai-email-input.wpil-invalid{
                border-color: #dc2626;
                box-shadow: 0 0 0 3px rgba(220,38,38,.10);
            }
            .wpil-wizard-ai-card{
                background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
                border: 1px solid #e5e7eb;
                border-radius: 1rem;
                padding: 1.25rem;
            }
            .wpil-wizard-ai-spinner{
                width: 48px;
                height: 48px;
                border: 4px solid #e5e7eb;
                border-top-color: #7F5AF0;
                border-radius: 999px;
                animation: wpilWizardSpin 1s linear infinite;
                margin: 0 auto 1rem;
            }
            @keyframes wpilWizardSpin {
                to { transform: rotate(360deg); }
            }
        </style>
    </div>
    <div class="min-h-screen flex items-center justify-center p-6 antialiased font-sans">
        <div class="bg-white w-full max-w-5xl rounded-2xl shadow-xl overflow-hidden">
            <div class="bg-gray-50 border-b border-gray-100 px-8 py-5 flex flex-col md:flex-row justify-between items-center text-sm font-medium text-gray-500">
                <div class="flex items-center space-x-2 lw-text-gradient">
                    <div class="w-6 h-6 rounded-full border-2 border-[#7F5AF0] flex items-center justify-center text-xs text-[#7F5AF0]">1</div>
                    <span class="text-gray-900"><?php esc_html_e('Connect AI', 'wpil'); ?></span>
                </div>
                <div class="hidden md:block h-px w-12 bg-gray-300 mx-4"></div>

                <div class="flex items-center space-x-2 mt-3 md:mt-0 text-gray-400">
                    <div class="w-6 h-6 rounded-full border-2 border-gray-300 flex items-center justify-center text-xs">2</div>
                    <span><?php esc_html_e('Money Pages', 'wpil'); ?></span>
                </div>
                <div class="hidden md:block h-px w-12 bg-gray-300 mx-4"></div>

                <div class="flex items-center space-x-2 mt-3 md:mt-0 text-gray-400">
                    <div class="w-6 h-6 rounded-full border-2 border-gray-300 flex items-center justify-center text-xs">3</div>
                    <span><?php esc_html_e('Search Console', 'wpil'); ?></span>
                </div>
                <div class="hidden md:block h-px w-12 bg-gray-300 mx-4"></div>

                <div class="flex items-center space-x-2 mt-3 md:mt-0 text-gray-400">
                    <div class="w-6 h-6 rounded-full border-2 border-gray-300 flex items-center justify-center text-xs">4</div>
                    <span><?php esc_html_e('Scan Site', 'wpil'); ?></span>
                </div>
            </div>

            <div class="p-8 md:p-12">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-10 items-center">
                    <div style="margin-right: 40px;">
                        <div class="w-16 h-16 bg-white rounded-xl shadow-md border border-gray-100 flex items-center justify-center mb-6">
                            <img class="lw-logo" src="<?php echo esc_url($wizard_logo); ?>" alt="Link Whisper" />
                        </div>
                        <h1 class="text-3xl md:text-4xl font-extrabold text-gray-900 tracking-tight leading-tight">
                            <?php esc_html_e('Connect Link Whisper AI', 'wpil'); ?>
                        </h1>
                        <p class="mt-4 text-lg text-gray-600 leading-relaxed">
                            <?php esc_html_e('Connect your site to Link Whisper AI to unlock smarter suggestions and more contextually relevant links.', 'wpil'); ?>
                        </p>
                        <p class="mt-4 text-lg text-gray-600 leading-relaxed">
                            <?php esc_html_e('New users receive 250 free AI credits at signup!', 'wpil'); ?>
                        </p>
                        <div class="mt-8 space-y-4">
                            <div class="wpil-wizard-ai-card">
                                <div class="font-semibold text-gray-900 mb-2"><?php esc_html_e('What you get when you connect', 'wpil'); ?></div>
                                <ul class="text-gray-600 space-y-2">
                                    <li style="list-style: disc; margin: 0 0 0 20px;"><?php esc_html_e('Smarter internal link suggestions enhanced by Link Whisper AI.', 'wpil'); ?></li>
                                    <li style="list-style: disc; margin: 0 0 0 20px;"><?php esc_html_e('AI-powered analysis to show you where you have links going between unrelated posts.', 'wpil'); ?></li>
                                    <li style="list-style: disc; margin: 0 0 0 20px;"><?php esc_html_e('AI-powered Visual Sitemaps that help you identify topic clusters and new linking opportunities.', 'wpil'); ?></li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-gray-100 bg-gray-50 p-6 md:p-8 shadow-sm" style="margin-top:auto;">
                        <div data-wpil-ai-state="ready" class="<?php echo ('ready' === $wizard_ai_state) ? '' : 'hidden'; ?>">
                            <label class="block text-sm font-semibold text-gray-700 mb-2" for="wpil-wizard-ai-email"><?php esc_html_e('Email Address', 'wpil'); ?></label>
                            <input type="email" id="wpil-wizard-ai-email" class="wpil-wizard-ai-email-input" value="<?php echo esc_attr($wizard_ai_email); ?>" placeholder="<?php esc_attr_e('you@example.com', 'wpil'); ?>">
                            <p class="mt-2 text-sm text-gray-500"><?php esc_html_e('Please enter your email address to setup the AI connection.', 'wpil'); ?></p>
                            <div class="hidden mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" data-role="wizard-ai-error"></div>
                            <div class="mt-6">
                                <button type="button" class="wpil-wizard-connect-ai lw-gradient-bg text-white font-bold text-lg px-8 py-3.5 rounded-xl shadow-lg hover:shadow-xl hover:opacity-95 transition-all focus:ring-4 focus:ring-purple-200 outline-none w-full">
                                    <?php esc_html_e('Connect Link Whisper AI', 'wpil'); ?>
                                </button>
                            </div>
                            <div class="mt-3 text-center">
                                <button type="button" class="wpil-wizard-reset-ai-activation text-sm font-medium text-gray-500 hover:text-gray-700 transition-all">
                                    <?php esc_html_e('Clear email and start over', 'wpil'); ?>
                                </button>
                            </div>
                            <div class="mt-4 text-center">
                                <a href="<?php echo esc_url(admin_url('admin.php?page=link_whisper_wizard&wpil_wizard=moneypage')); ?>" class="wpil-wizard-link text-sm font-medium text-gray-500 hover:text-gray-700" data-wpil-wizard-link-id="moneypage"><?php esc_html_e('Skip for now and continue to Money Pages', 'wpil'); ?></a>
                            </div>
                        </div>

                        <div data-wpil-ai-state="verification_required" class="<?php echo ('verification_required' === $wizard_ai_state) ? '' : 'hidden'; ?> text-center">
                            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-blue-100 text-blue-600">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-8 h-8">
                                    <path d="M1.5 8.67v8.58a2.25 2.25 0 002.25 2.25h16.5a2.25 2.25 0 002.25-2.25V8.67l-8.69 5.216a3.75 3.75 0 01-3.622 0L1.5 8.67z" />
                                    <path d="M22.5 6.908V6.75A2.25 2.25 0 0020.25 4.5H3.75A2.25 2.25 0 001.5 6.75v.158l9.457 5.674a2.25 2.25 0 002.086 0L22.5 6.908z" />
                                </svg>
                            </div>
                            <h2 class="text-2xl font-bold text-gray-900"><?php esc_html_e('Check your email to continue', 'wpil'); ?></h2>
                            <p class="mt-3 text-gray-600"><?php esc_html_e('We sent a Link Whisper verification email to the address below. Verify the account there, then come back here and finish connecting your site.', 'wpil'); ?></p>
                            <p class="mt-4 text-sm text-gray-500">
                                <?php esc_html_e('Verification email:', 'wpil'); ?>
                                <span class="font-semibold text-gray-700" data-role="wizard-ai-verify-email"><?php echo esc_html($wizard_ai_email); ?></span>
                            </p>
                            <div class="hidden mt-4 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-700 text-left" data-role="wizard-ai-verification-message"></div>
                            <div class="mt-6">
                                <button type="button" class="wpil-wizard-confirm-ai-email lw-gradient-bg text-white font-bold text-lg px-8 py-3.5 rounded-xl shadow-lg hover:shadow-xl hover:opacity-95 transition-all focus:ring-4 focus:ring-purple-200 outline-none w-full">
                                    <?php esc_html_e("I've verified my email", 'wpil'); ?>
                                </button>
                            </div>
                            <div class="mt-3 text-center">
                                <button type="button" class="wpil-wizard-reset-ai-activation text-sm font-medium text-gray-500 hover:text-gray-700 transition-all">
                                    <?php esc_html_e('Use a different email instead', 'wpil'); ?>
                                </button>
                            </div>
                            <div class="mt-4 text-center">
                                <a href="<?php echo esc_url(admin_url('admin.php?page=link_whisper_wizard&wpil_wizard=moneypage')); ?>" class="wpil-wizard-link text-sm font-medium text-gray-500 hover:text-gray-700" data-wpil-wizard-link-id="moneypage"><?php esc_html_e('Skip for now and continue to Money Pages', 'wpil'); ?></a>
                            </div>
                        </div>

                        <div data-wpil-ai-state="connecting" class="hidden text-center">
                            <div class="wpil-wizard-ai-spinner"></div>
                            <h2 class="text-2xl font-bold text-gray-900"><?php esc_html_e('Finishing your AI connection', 'wpil'); ?></h2>
                            <p class="mt-3 text-gray-600" data-role="wizard-ai-connecting-text"><?php esc_html_e('We opened the secure Link Whisper popup. Complete the free activation there and we’ll keep watch here.', 'wpil'); ?></p>
                            <p class="mt-4 text-sm text-gray-500"><?php esc_html_e('If the popup did not appear, please allow popups for this site and try again.', 'wpil'); ?></p>
                            <div class="mt-6">
                                <a href="<?php echo esc_url(admin_url('admin.php?page=link_whisper_wizard&wpil_wizard=moneypage')); ?>" class="wpil-wizard-link text-sm font-medium text-gray-500 hover:text-gray-700" data-wpil-wizard-link-id="moneypage"><?php esc_html_e('Skip for now', 'wpil'); ?></a>
                            </div>
                            <div class="mt-3 text-center">
                                <button type="button" class="wpil-wizard-reset-ai-activation text-sm font-medium text-gray-500 hover:text-gray-700 transition-all">
                                    <?php esc_html_e('Clear this and start over', 'wpil'); ?>
                                </button>
                            </div>
                        </div>

                        <div data-wpil-ai-state="connected" class="<?php echo ('connected' === $wizard_ai_state) ? '' : 'hidden'; ?> text-center">
                            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-green-100 text-green-600">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-8 h-8">
                                    <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <h2 class="text-2xl font-bold text-gray-900"><?php esc_html_e('Link Whisper AI is connected', 'wpil'); ?></h2>
                            <p class="mt-3 text-gray-600"><?php esc_html_e('Your site is ready to use Link Whisper AI during setup.', 'wpil'); ?></p>
                            <p class="mt-2 text-sm text-gray-500">
                                <?php esc_html_e('Connected email:', 'wpil'); ?>
                                <span class="font-semibold text-gray-700" data-role="wizard-ai-connected-email"><?php echo esc_html($wizard_connected_email); ?></span>
                            </p>
                            <div class="mt-6">
                                <a href="<?php echo esc_url(admin_url('admin.php?page=link_whisper_wizard&wpil_wizard=moneypage')); ?>" class="wpil-wizard-link lw-gradient-bg inline-flex items-center justify-center text-white font-bold text-lg px-8 py-3.5 rounded-xl shadow-lg hover:shadow-xl hover:opacity-95 transition-all focus:ring-4 focus:ring-purple-200 outline-none" data-wpil-wizard-link-id="moneypage"><?php esc_html_e('Continue to Money Pages', 'wpil'); ?></a>
                            </div>
                        </div>
                    </div>
                    <div class="mt-5 text-center" style="grid-column: 1 / span 2;">
                        <span>AI usage and accounts are governed by our <a href="<?php echo esc_url(WPIL_STORE_URL . '/terms-of-service/');?>" style="color: #2e6afe;">terms</a> and <a href="<?php echo esc_url(WPIL_STORE_URL . '/privacy-policy/');?>" style="color: #2e6afe;">privacy policy</a></span><br>
                        <span>AI is not required to use Link Whisper.</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
