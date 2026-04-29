<?php

$ai_service_connected = (Wpil_Settings::get_linkwhisper_ai_active() && !empty(Wpil_Settings::get_linkwhisper_ai_user_id()));
$available_credits = 0;
$needed_credits = 0;
$enough_credits = true;
$shortfall = 0;
$credit_pct = 0;

$ai_error_active = (!empty($ai_setup_error) && is_array($ai_setup_error));
$ai_error_title = $ai_error_active && !empty($ai_setup_error['title']) ? $ai_setup_error['title'] : __('Processing Stopped', 'wpil');
$ai_error_text = $ai_error_active && !empty($ai_setup_error['text']) ? $ai_setup_error['text'] : __('An unexpected error stopped processing.', 'wpil');
$ai_error_code = $ai_error_active && !empty($ai_setup_error['code']) ? $ai_setup_error['code'] : '';
$ai_credit_error_active = ($ai_error_active && 'insufficient_credits' === $ai_error_code);
$credit_ui_disabled = (!$ai_service_connected || ($ai_error_active && !$ai_credit_error_active));
$credit_display_available = __('Counting...', 'wpil');
$credit_display_needed = __('Estimating...', 'wpil');
$credit_status_class = 'bg-gray-200 text-gray-500';
$credit_status_text = __('Checking credits', 'wpil');
$current_user = wp_get_current_user();
$checkout_user_email = Wpil_Settings::get_linkwhisper_ai_user_email();
if(empty($checkout_user_email) && !empty($current_user->user_email)){
    $checkout_user_email = $current_user->user_email;
}

if(!$ai_service_connected){
    $enough_credits = true;
    $shortfall = 0;
    $credit_pct = 0;
    $credit_display_available = number_format($available_credits);
    $credit_display_needed = __('N/A', 'wpil');
    $credit_status_class = 'bg-gray-200 text-gray-500';
    $credit_status_text = __('Not connected', 'wpil');
}elseif($ai_error_active && !$ai_credit_error_active){
    $credit_display_available = number_format($available_credits);
    $credit_display_needed = __('N/A', 'wpil');
    $credit_status_text = __('N/A', 'wpil');
}

?>
<script>
    var dashboardURL = "<?php echo admin_url('admin.php?page=link_whisper'); ?>";
</script>
<div class="bg-gray-100 wpil-setup-wizard wrap wpil_styles wizard-scanning wpil-wizard-page wpil-wizard-page-hidden">
    <input type="hidden" id="wpil-setup-wizard-ai-data" data-nonce="<?php echo wp_create_nonce(wp_get_current_user()->ID . 'wpil_download_ai_data'); ?>">
    <input type="hidden" class="wpil-wizard-reset-report-nonce" name="reset_data_nonce" value="<?php echo wp_create_nonce(get_current_user_id() . 'wpil_reset_report_data'); ?>">
    <input type="hidden" class="wpil-wizard-reset-target-keyword-nonce" value="<?php echo wp_create_nonce(get_current_user_id() . 'wpil_target_keyword'); ?>">
    <input type="hidden" id="wpil-setup-wizard-total-ai-processable-post-count" value="<?php echo (int) Wpil_AI::get_total_processable_posts(); ?>">
    <input type="hidden" id="wpil-wizard-approve-all" name="wpil_wizard_approve_all" value="0">
    <input type="hidden" id="wpil-wizard-enough-credits" value="">
    <input type="hidden" id="wpil-wizard-ai-service-connected" value="<?php echo $ai_service_connected ? '1' : '0'; ?>">
    <input type="hidden" id="wpil-link-mode" value="review">
    <input type="hidden" id="wpil-scanning-nonce" value="<?php echo wp_create_nonce(get_current_user_id() . 'wizard-scanning-nonce'); ?>">
    <input type="hidden" id="wpil-review-process-key" value="">
    <input type="hidden" id="wpil-ai-linking-enabled" value="0">
    <input type="hidden" id="wpil-ai-linking-running" value="0">
    <input type="hidden" id="wpil-ai-linking-complete" value="0">
    <div>
        <div id="wpil-wizard-error-banner"
            class="<?php echo $ai_error_active ? '' : 'hidden '; ?>mb-6 rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-800 shadow-sm text-center"
            data-error-code="<?php echo esc_attr($ai_error_code); ?>">
            <div class="flex items-start justify-between gap-4">
                <div class="flex-1">
                    <div class="font-semibold text-red-900" data-role="error-title"><?php echo esc_html($ai_error_title); ?></div>
                    <div class="mt-1 text-red-700" data-role="error-text"><?php echo wp_kses_post($ai_error_text); ?></div>
                </div>
                <button type="button" class="text-red-500 hover:text-red-700 text-xl leading-none" data-role="error-close" aria-label="Dismiss">×</button>
            </div>
        </div>
        <style>
            /* Brand Gradient */
            .lw-gradient-bg {
                background: linear-gradient(90deg, #7F5AF0 0%, #2C6BFF 100%);
            }
            .lw-gradient-text {
                background: linear-gradient(90deg, #7F5AF0 0%, #2C6BFF 100%);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
            }
            .lw-border-gradient {
                background: linear-gradient(white, white) padding-box,
                            linear-gradient(90deg, #7F5AF0 0%, #2C6BFF 100%) border-box;
                border: 2px solid transparent;
                border-radius: 0.75rem;
            }
            .lw-pause-bg{
                background-color: #f59e0b; /* amber-500 */
            }
            .lw-pause-bg-soft{
                background-color: #fef3c7; /* amber-100 */
            }
            .lw-pause-text{
                color: #b45309; /* amber-700 */
            }
            .lw-pause-line{
                background-color: #fde68a; /* amber-200 */
            }

            .lw-mini-toggle{
                margin-top: 1px;
                position: relative;
                width: 44px;
                height: 24px;
                display: inline-flex;
                align-items: center;
                cursor: pointer;
                user-select: none;
            }
            .lw-mini-toggle input{
            position:absolute;
            opacity:0;
            width:0;
            height:0;
            }
            .lw-mini-toggle__track{
            width: 44px;
            height: 24px;
            border-radius: 999px;
            background: #d1d5db; /* gray-300 */
            transition: background .15s ease;
            }
            .lw-mini-toggle__thumb{
            position:absolute;
            left: 3px;
            top: 3px;
            width: 18px;
            height: 18px;
            border-radius: 999px;
            background: #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,.15);
            transition: transform .15s ease;
            }
            .lw-mini-toggle input:checked + .lw-mini-toggle__track{
            background: #7F5AF0; /* LW purple */
            }
            .lw-mini-toggle input:checked ~ .lw-mini-toggle__thumb{
            transform: translateX(20px);
            }
            .wpil-radio-pill{
                position: relative;
                display: inline-flex;
                align-items: center;
                cursor: pointer;
                user-select: none;
            }
            .wpil-radio-pill input{
                position: absolute;
                opacity: 0;
                pointer-events: none;
            }
            .wpil-radio-pill__ui{
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 6px 12px;
                border-radius: 999px;
                border: 1px solid #e5e7eb;
                background: #fff;
                color: #64748b;
                font-weight: 600;
                font-size: 12px;
                transition: all .15s ease;
            }
            .wpil-radio-pill__dot{
                width: 10px;
                height: 10px;
                border-radius: 999px;
                border: 2px solid #cbd5f5;
                background: #fff;
                box-sizing: border-box;
                transition: all .15s ease;
            }
            .wpil-radio-pill input:checked + .wpil-radio-pill__ui{
                border-color: #7F5AF0;
                color: #4c1d95;
                box-shadow: 0 6px 14px rgba(127,90,240,.18);
                background: linear-gradient(180deg, #ffffff 0%, #f6f3ff 100%);
            }
            .wpil-radio-pill input:checked + .wpil-radio-pill__ui .wpil-radio-pill__dot{
                border-color: #7F5AF0;
                background: #7F5AF0;
                box-shadow: 0 0 0 3px rgba(127,90,240,.15);
            }
            .wpil-radio-pill:hover .wpil-radio-pill__ui{
                border-color: #c7d2fe;
                color: #6d28d9;
            }
            .wpil-radio-plain{
                display:flex;
                align-items:center;
                gap:8px;
                color:#475569;
                font-size:13px;
                font-weight:500;
                margin-bottom:6px;
                cursor:pointer;
            }
            /*.wpil-radio-plain input{
                width:14px;
                height:14px;
                accent-color:#7F5AF0;
            }*/
            .wpil-wizard-linking-mode-buttons .wpil-radio-plain:last-child{
                margin-bottom:0;
            }


            /* Animations */
            @keyframes pulse-ring {
                0% { transform: scale(0.8); box-shadow: 0 0 0 0 rgba(127, 90, 240, 0.7); }
                70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(127, 90, 240, 0); }
                100% { transform: scale(0.8); box-shadow: 0 0 0 0 rgba(127, 90, 240, 0); }
            }
            .animate-pulse-ring {
                animation: pulse-ring 2s infinite;
            }
            
            @keyframes pulse-ring-amber {
                0%   { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.6); }
                70%  { box-shadow: 0 0 0 10px rgba(245, 158, 11, 0); }
                100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0); }
            }

            .animate-pulse-ring-amber{
                animation: pulse-ring-amber 2.5s infinite;
            }

            @keyframes load-width {
                0% { width: 0; }
                100% { width: 65%; } /* Stopping at 65% to simulate work in progress */
            }
            .animate-loader {
                animation: load-width 2s ease-out forwards;
            }

            /* Toggle Switch Styling */
            .toggle-checkbox:checked {
                right: 0;
                border-color: #7F5AF0;
            }
            .toggle-checkbox:checked + .toggle-label {
                background-color: #7F5AF0;
            }
        </style>
    </div>
    <div class="min-h-screen flex items-center justify-center p-6 antialiased font-sans">
        <div class="bg-white w-full max-w-5xl rounded-2xl shadow-xl overflow-hidden">
            <div class="bg-gray-50 border-b border-gray-100 px-8 py-5 flex flex-col md:flex-row justify-between items-center text-sm font-medium text-gray-500">
                <div class="flex items-center space-x-2 text-green-500">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6">
                        <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" />
                    </svg>
                    <span>Connect AI</span>
                </div>
                <div class="hidden md:block h-px w-12 bg-green-200 mx-4"></div>
                <div class="flex items-center space-x-2 text-green-500">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6">
                        <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" />
                    </svg>
                    <span>Money Pages</span>
                </div>
                <div class="hidden md:block h-px w-12 bg-green-200 mx-4"></div>
                
                <div class="flex items-center space-x-2 mt-3 md:mt-0 text-green-500">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6">
                        <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" />
                    </svg>
                    <span>Search Console</span>
                </div>
                <div class="hidden md:block h-px w-12 bg-green-200 mx-4"></div>

                <div class="flex items-center space-x-2 lw-text-purple">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6">
                        <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" />
                    </svg>
                    <span>Scan Site</span>
                </div>
            </div>

            <div class="p-8 md:p-12">
                
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-12">
                    
                    <div class="lg:col-span-5 flex flex-col justify-between h-full">
                        <div>
                            <div class="flex items-center space-x-3 mb-4">
                                <svg data-role="processing-status-spinner" class="animate-spin h-6 w-6 text-[#7F5AF0]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <svg data-role="processing-status-check" class="hidden h-6 w-6 text-green-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                                <span data-role="processing-status-label" class="text-[#7F5AF0] font-semibold tracking-wide uppercase text-xs">Processing Data</span>
                            </div>

                            <h1 class="text-3xl font-bold text-gray-900 mb-4">Analyzing your content</h1>
                            <p class="text-gray-600 leading-relaxed mb-8 text-base">
                                <?php echo $wizard_ai_connected
                                    ? esc_html__('Link Whisper is currently crawling your site to be able to provide you with suggestions based on your GSC data and AI analysis.', 'wpil')
                                    : esc_html__('Link Whisper is currently crawling your site to build a strong understanding of the linking network and keyword relationships.', 'wpil'); ?>
                            </p>

                            <div class="bg-gray-50 border border-gray-100 rounded-xl p-5 mb-8 wpil-setup-wizard-credit-container<?php echo $credit_ui_disabled ? ' opacity-60 pointer-events-none' : ''; ?>">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">AI Credits</span>
                                    <span
                                    data-role="credit-status"
                                    class="text-xs px-2 py-0.5 rounded-full font-medium <?php echo esc_attr($credit_status_class); ?>"
                                    >
                                    <?php echo esc_html($credit_status_text); ?>
                                    </span>
                                </div>

                                <div class="flex items-end justify-between gap-4">
                                    <div class="flex items-end space-x-3">
                                    <div class="text-3xl font-bold text-gray-800">
                                        <span class="ai-credits-available"><?php echo esc_html($credit_display_available); ?></span>
                                    </div>
                                    <div class="text-sm text-gray-500 mb-1">available</div>
                                    </div>

                                    <div class="text-right text-sm text-gray-500">
                                    <div class="text-xs uppercase tracking-wider text-gray-400 font-semibold">Required</div>
                                    <div class="font-bold text-gray-700">
                                        <span class="ai-credits-needed"><?php echo esc_html($credit_display_needed); ?></span>
                                    </div>
                                    </div>
                                </div>

                                <div class="w-full bg-gray-200 rounded-full h-1.5 mt-3">
                                    <div class="lw-gradient-bg h-1.5 rounded-full transition-all duration-300" data-role="credits-bar" style="width: <?php echo (int) $credit_pct; ?>%"></div>
                                </div>

                                <div class="mt-2 text-xs text-gray-400">
                                    <span class="font-medium text-gray-600 ai-credits-needed"><?php echo esc_html($credit_display_needed); ?></span> credits required for this scan
                                </div>

                                <div class="mt-3 <?php echo ($enough_credits || !$ai_service_connected) ? 'hidden' : ''; ?>" data-role="credit-warning">
                                    <div class="text-sm text-red-700 font-semibold mb-2">
                                    You need<span style="display:none;" class="font-bold" data-role="credit-shortfall"> <?php echo number_format($shortfall); ?></span> more credits to complete this scan.
                                    </div>

                                    <button
                                    type="button"
                                    class="lw-credits-buy lw-gradient-bg text-white font-bold text-sm px-4 py-2 rounded-lg shadow hover:opacity-95 transition-all"
                                    data-type="custom"
                                    data-email="<?php echo esc_attr($checkout_user_email); ?>"
                                    data-credits="<?php echo esc_attr($shortfall); ?>"
                                    data-quantity="<?php echo esc_attr($shortfall); ?>"
                                    >
                                    Buy Credits
                                    </button>
                                </div>
                            </div>


                        </div>

                    </div>

                    <div class="lg:col-span-7 bg-gray-50 rounded-2xl p-8 border border-gray-100 relative overflow-hidden">
                        <div class="absolute top-0 right-0 -mr-16 -mt-16 w-64 h-64 rounded-full bg-gradient-to-br from-purple-100 to-blue-50 opacity-50 blur-3xl"></div>

                        <div class="relative z-10 space-y-8">
                            <div class="flex group" data-step="links">
                                <div class="flex flex-col items-center mr-6" data-role="icon">
                                    <!-- icon gets injected by JS -->
                                </div>
                                <div class="flex-1 pb-8 border-b border-gray-200 border-dashed">
                                    <div class="flex justify-between items-center mb-1">
                                    <h4 class="text-lg font-bold text-gray-800" data-role="title">Scanning Links</h4>
                                    <span class="text-sm font-bold text-gray-400" data-role="pct">Pending</span>
                                    </div>
                                    <p class="text-sm text-gray-500" data-role="desc">Waiting for scan to start.</p>
                                    <div class="mt-3 hidden" data-role="bar">
                                    <div class="w-full bg-white rounded-full h-2.5 border border-gray-100 overflow-hidden">
                                        <div class="lw-gradient-bg h-2.5 rounded-full" data-role="bar-fill" style="width:0%"></div>
                                    </div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex group" data-step="keywords">
                                <div class="flex flex-col items-center mr-6" data-role="icon">
                                    <!-- icon gets injected by JS -->
                                </div>
                                <div class="flex-1 pb-8 border-b border-gray-200 border-dashed">
                                    <div class="flex justify-between items-center mb-1">
                                    <h4 class="text-lg font-bold text-gray-800" data-role="title">Searching Keywords</h4>
                                    <span class="text-sm font-bold text-gray-400" data-role="pct">Pending</span>
                                    </div>
                                    <p class="text-sm text-gray-500" data-role="desc">Waiting for scan to start.</p>
                                    <div class="mt-3 hidden" data-role="bar">
                                    <div class="w-full bg-white rounded-full h-2.5 border border-gray-100 overflow-hidden">
                                        <div class="lw-gradient-bg h-2.5 rounded-full" data-role="bar-fill" style="width:0%"></div>
                                    </div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex group" data-step="ai_scan">
                                <div class="flex flex-col items-center mr-6" data-role="icon">
                                    <!-- icon gets injected by JS -->
                                </div>
                                <div class="flex-1 pb-8 border-b border-gray-200 border-dashed">
                                    <div class="flex justify-between items-center mb-1">
                                    <h4 class="text-lg font-bold text-gray-800" data-role="title">Scanning with AI</h4>
                                    <span class="text-sm font-bold text-gray-400" data-role="pct">Pending</span>
                                    </div>
                                    <p class="text-sm text-gray-500" data-role="desc">Waiting for scan to start.</p>
                                    <div class="mt-3 hidden" data-role="bar">
                                    <div class="w-full bg-white rounded-full h-2.5 border border-gray-100 overflow-hidden">
                                        <div class="lw-gradient-bg h-2.5 rounded-full" data-role="bar-fill" style="width:0%"></div>
                                    </div>
                                    </div>
                                    <div style="display:none;" class="mt-4 rounded-xl border border-blue-100 bg-blue-50 px-4 py-4 <?php echo $wizard_ai_connected ? 'hidden' : ''; ?>" data-role="wizard-ai-scan-prompt">
                                        <div class="flex flex-col items-start gap-3 md:flex-row md:items-center md:justify-between">
                                            <div>
                                                <div class="text-sm font-semibold text-slate-900">Connect to AI to accelerate your keyword discovery.</div>
                                                <div class="mt-1 text-sm text-slate-600">Link Whisper can keep scanning without AI, or you can connect now and let AI start mapping context right away.</div>
                                            </div>
                                            <button type="button" class="lw-ai-connect-button lw-gradient-bg text-white font-bold text-sm px-4 py-2 rounded-lg shadow hover:opacity-95 transition-all">
                                                Connect to AI
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex group" data-step="ai_linking" style="display:none;">
                                <div class="flex flex-col items-center mr-6" data-role="icon">
                                    <!-- icon gets injected by JS -->
                                </div>
                                <div class="flex-1 pb-8 border-b border-gray-200 border-dashed">
                                    <div class="flex justify-between items-center mb-1">
                                        <h4 class="text-lg font-bold text-gray-800" data-role="title">AI Link Suggestions</h4>
                                        <span class="text-sm font-bold text-gray-400" data-role="pct">Disabled</span>
                                    </div>
                                    <p class="text-sm text-gray-500" data-role="desc">AI link suggestions are not currently available. We’ll finish scanning without generating links.</p>
                                    <div class="mt-3 hidden" data-role="bar">
                                        <div class="w-full bg-white rounded-full h-2.5 border border-gray-100 overflow-hidden">
                                            <div class="lw-gradient-bg h-2.5 rounded-full" data-role="bar-fill" style="width:0%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-6 mt-6" style="display:none">
                    <a href="#" class="text-gray-400 hover:text-gray-600 font-medium text-sm transition-colors flex items-center" style="display:none">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 mr-1">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                        </svg>
                        Cancel Scan
                    </a>
                    
                    <button class="bg-gray-100 text-gray-400 font-bold text-lg px-8 py-3 rounded-xl cursor-not-allowed flex items-center" disabled>
                        <span>Finish Setup</span>
                    </button>
                </div>
            </div>
            <?php include 'credits-modal.php'; ?>
            <?php include 'buy-credits-notice.php'; ?>
        </div>

    </div>
    
</div>
