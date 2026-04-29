<?php
$dash_fix_nonce = wp_create_nonce('wpil_dash_fix_nonce');
$available_credits = isset($credits) ? (int) $credits : 0;
?>
<div id="wpil-fix-confirm-modal" style="display:none;" class="lw-screen-overlay">
    <div class="lw-modal-box bg-white rounded-2xl shadow-xl border border-gray-200" style="max-width:620px;width:calc(100% - 40px);margin:40px auto;padding:22px;position:relative;">
        <div class="flex items-start justify-between gap-4">
            <div>
                <div class="text-lg font-extrabold text-gray-900" id="wpil-fix-modal-title">Link Whisper AI can help you fix this.</div>
                <div class="text-sm text-gray-600 mt-1" id="wpil-fix-modal-subtitle"></div>
            </div>
            <button type="button" class="text-gray-400 hover:text-gray-700 font-bold" id="wpil-fix-modal-close" aria-label="Close">✕</button>
        </div>

        <div class="mt-5 rounded-xl border border-gray-200 bg-gray-50 p-4">
            <div class="text-sm font-bold text-gray-800">Estimated cost</div>
            <div class="mt-1 text-2xl font-extrabold text-gray-900">
                <span id="wpil-fix-est-credits">0</span> AI credits
            </div>
            <div class="mt-2 text-sm text-gray-600">
                Available: <span class="font-bold text-gray-800" id="wpil-fix-available-credits"><?php echo (int) $available_credits; ?></span>
                <span id="wpil-fix-shortfall-wrap" style="display:none;">
                    , Needed: <span class="font-bold text-red-600" id="wpil-fix-shortfall">0</span>
                </span>
            </div>

            <div id="wpil-fix-warning" class="mt-3 text-sm font-bold text-red-600" style="display:none;"></div>
        </div>

        <div class="mt-6 flex items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <a id="wpil-fix-review-btn" href="#" target="_blank"
                   class="text-sm font-bold text-gray-600 bg-gray-50 border border-gray-200 px-4 py-2 rounded-lg hover:bg-gray-100 hover:text-gray-900 transition-colors">
                    Review
                </a>

                <button id="wpil-fix-buy-btn" type="button"
                        class="text-sm font-bold text-white bg-purple-600 px-4 py-2 rounded-lg hover:bg-purple-700 shadow-md transition-colors"
                        style="display:none;">
                    Buy Credits
                </button>
            </div>

            <button id="wpil-fix-confirm-btn" type="button"
                    class="text-sm font-bold text-white bg-blue-600 px-5 py-2 rounded-lg hover:bg-blue-700 shadow-md transition-colors">
                Fix Now
            </button>
        </div>

        <input type="hidden" id="wpil-fix-nonce" value="<?php echo esc_attr($dash_fix_nonce); ?>">
        <input type="hidden" id="wpil-fix-type" value="">
        <input type="hidden" id="wpil-fix-item-id" value="">
    </div>
</div>
