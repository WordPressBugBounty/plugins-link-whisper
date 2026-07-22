<?php
    $money_page_nonce = wp_create_nonce(get_current_user_id() . 'wpil_pillar_content_nonce');
    $pillar_pages = Wpil_Maintenance::get_pillar_post_items();
    $pillar_ids_csv = implode(',', Wpil_Settings::get_money_page_pid_list(true));
?>
<div class="bg-gray-100 wpil-setup-wizard wrap wpil_styles wizard-moneypage wpil-wizard-page wpil-wizard-page-hidden">
    <div>
        <script src="https://unpkg.com/heroicons@2.0.18/dist/outline/index.js"></script>
        <style>
            /* Link Whisper Brand Gradient */
            .lw-gradient-bg {
                background: linear-gradient(90deg, #7F5AF0 0%, #2C6BFF 100%);
            }
            .lw-text-purple {
                color: #7F5AF0;
            }
        </style>
    </div>
    <div class="min-h-screen flex items-center justify-center p-6 antialiased font-sans">

        <div class="bg-white w-full max-w-4xl rounded-2xl shadow-xl overflow-visible">
            
            <div class="bg-gray-50 border-b border-gray-100 px-8 py-5 flex flex-col md:flex-row justify-between items-center text-sm font-medium text-gray-500">
                <div class="flex items-center space-x-2 text-green-500">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6">
                        <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" />
                    </svg>
                    <span>Connect AI</span>
                </div>
                <div class="hidden md:block h-px w-12 bg-green-200 mx-4"></div>
                <div class="flex items-center space-x-2 lw-text-purple">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6">
                        <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" />
                    </svg>
                    <span>Money Pages</span>
                </div>
                <div class="hidden md:block h-px w-12 bg-gray-300 mx-4"></div>
                
                <div class="flex items-center space-x-2 mt-3 md:mt-0">
                    <div class="w-6 h-6 rounded-full border-2 border-gray-300 flex items-center justify-center text-xs">3</div>
                    <span>Search Console</span>
                </div>
                <div class="hidden md:block h-px w-12 bg-gray-300 mx-4"></div>

                <div class="flex items-center space-x-2 mt-3 md:mt-0">
                    <div class="w-6 h-6 rounded-full border-2 border-gray-300 flex items-center justify-center text-xs">4</div>
                    <span>Scan Site</span>
                </div>
            </div>


            <div class="p-8 md:p-10">
                <div class="flex items-start mb-8">
                    <div class="w-16 h-16 rounded-xl flex items-center justify-center mb-6">
                        <img class="lw-logo w-16 h-16 mr-4 flex-shrink-0" src="<?php echo esc_url($logo); ?>" alt="Link Whisper" />
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 mb-3">Identify Money Pages</h1>
                        <p class="text-gray-600 leading-relaxed text-base">
                            "Money pages" are the content you want your visitors to land on to generate revenue. Select your top pages below, and Link Whisper will prioritize building internal links to them.
                        </p>
                    </div>
                </div>
                <!-- Search -->
                <div class="relative mb-8">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-gray-400">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                        </svg>
                    </div>

                    <input
                        id="wpil-pillars-search"
                        type="text"
                        class="w-full pl-11 pr-4 py-3.5 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all outline-none text-gray-800 placeholder-gray-400"
                        placeholder="Search for a post or page to add..."
                        autocomplete="off"
                    />

                    <!-- Results dropdown -->
                    <div id="wpil-pillars-results" class="hidden absolute z-50 mt-2 w-full bg-white border border-gray-200 rounded-xl shadow-xl overflow-hidden max-h-80 overflow-y-auto"></div>
                </div>

                <!-- Selected -->
                <div class="mb-8">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-sm font-bold text-gray-500 uppercase tracking-wider">
                        Your Selected Pages (<span data-pillars-count="1"><?php echo (!empty($pillar_pages) ? count($pillar_pages): 0); ?></span>)
                        </h2>

                        <!-- Hidden ids field -->
                        <input type="hidden" id="wpil_pillar_content_ids" value="<?php echo esc_attr($pillar_ids_csv); ?>" />
                    </div>

                    <ul id="wpil-pillars-selected" class="space-y-3">
                        <?php if(empty($pillar_pages)) { ?>
                        <li class="wpil-pillars-empty text-sm text-gray-500 bg-gray-50 border border-gray-100 rounded-xl p-4">
                        No money pages selected yet. Use the search box above to add some.
                        </li>
                        <?php } else { ?>
                            <?php foreach($pillar_pages as $item){ ?>
                                <li class="wpil-toggle-row flex items-center justify-between p-4 bg-white border border-gray-100 rounded-xl hover:shadow-md transition-shadow group" data-post-id="<?php echo esc_attr($item['id']); ?>" data-pid="<?php echo esc_attr($item['pid']); ?>">
                                    <div class="flex items-center overflow-hidden mr-4 min-w-0">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-gray-400 mr-3 flex-shrink-0">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 3.75h6l3.75 3.75V18a2.25 2.25 0 0 1-2.25 2.25h-7.5A2.25 2.25 0 0 1 6 18V6a2.25 2.25 0 0 1 2.25-2.25Z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.25 3.75V7.5A.75.75 0 0 0 15 8.25h3" />
                                        </svg>
                                    <div class="truncate min-w-0">
                                        <div class="text-gray-900 font-medium text-lg truncate"><?php echo esc_html($item['title']); ?></div>
                                        <div class="text-xs text-gray-500 mt-0.5 truncate"><?php $meta_type = !empty($item['real_type_name']) ? $item['real_type_name'] : $item['type']; echo esc_html($meta_type . ($meta_type && $item['status'] ? ' | ' : '') . $item['status']); ?><?php if(!empty($item['view'])){ ?> | <a href="<?php echo esc_url($item['view']); ?>" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:text-blue-700 hover:underline">View</a><?php } ?></div>
                                    </div>
                                    </div>
                                    <button type="button" class="wpil-pillars-remove text-gray-300 hover:text-red-500 p-2 rounded-full hover:bg-red-50 transition-colors focus:outline-none" title="Remove">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                        </svg>
                                    </button>
                                </li>
                            <?php } ?>
                        <?php } ?>
                    </ul>
                </div>

                <!-- Footer actions -->
                <div class="flex justify-end items-center gap-4 pt-6 border-t border-gray-100">
                    <div class="wpil-setup-maintenance-loading hidden text-sm text-gray-500">
                        Saving...
                    </div>
                    <button type="button" class="wpil-wizard-link bg-white text-gray-600 font-bold text-lg px-8 py-4 rounded-xl border border-gray-200 hover:border-gray-300 hover:text-gray-800 hover:bg-gray-50 transition-all focus:ring-4 focus:ring-gray-100 outline-none" data-wpil-wizard-link-id="<?php echo esc_attr($money_pages_next_page); ?>">
                        Skip for Now
                    </button>
                    <button id="wpil-pillars-save-next" type="button" data-wpil-nonce="<?php echo esc_attr($money_page_nonce); ?>" data-wpil-wizard-link-id="<?php echo $money_pages_next_page; ?>" class="lw-gradient-bg text-white font-bold text-lg px-10 py-4 rounded-xl shadow-lg hover:shadow-xl hover:opacity-95 transition-all focus:ring-4 focus:ring-purple-200 outline-none">
                        Save and Continue
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
