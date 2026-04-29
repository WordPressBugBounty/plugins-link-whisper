<div class="bg-gray-100 wpil-setup-wizard wrap wpil_styles wizard-connect-gsc wpil-wizard-page wpil-wizard-page-hidden">
    <div>
        <script src="https://unpkg.com/heroicons@2.0.18/dist/outline/index.js"></script>
        <style>
            /* Brand Gradient */
            .lw-gradient-bg {
                background: linear-gradient(90deg, #7F5AF0 0%, #2C6BFF 100%);
            }
            .lw-text-purple {
                color: #7F5AF0;
            }
            .lw-text-gradient {
                background: linear-gradient(90deg, #7F5AF0 0%, #2C6BFF 100%);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
            }
            /* Animation for the connection line */
            @keyframes dash {
                to {
                    stroke-dashoffset: -20;
                }
            }
            .animate-dash {
                stroke-dasharray: 5;
                animation: dash 1s linear infinite;
            }
        </style>
    </div>
    <div class="min-h-screen flex items-center justify-center p-6 antialiased font-sans">

        <div class="bg-white w-full max-w-4xl rounded-2xl shadow-xl overflow-hidden">
            
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
                
                <div class="flex items-center space-x-2 mt-3 md:mt-0 lw-text-purple">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6">
                        <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" />
                    </svg>
                    <span>Search Console</span>
                </div>
                <div class="hidden md:block h-px w-12 bg-gray-300 mx-4"></div>

                <div class="flex items-center space-x-2 mt-3 md:mt-0 text-gray-400">
                    <div class="w-6 h-6 rounded-full border-2 border-gray-300 flex items-center justify-center text-xs">3</div>
                    <span>Scan Site</span>
                </div>
            </div>

            <div class="p-8 md:p-12">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-12 items-center">
                    
                    <div>
                        <div class="mb-6">
                            <h1 class="text-3xl font-bold text-gray-900 mb-4">Connect Google Search Console</h1>
                            <p class="text-gray-600 leading-relaxed text-lg">
                                Supercharge Link Whisper by syncing your search data. We use this to find the most relevant internal linking opportunities based on keywords you actually rank for.
                            </p>
                        </div>

                        <ul class="space-y-4">
                            <li class="flex items-start">
                                <div class="bg-purple-100 p-1 rounded-full mr-3 mt-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-purple-600">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                </div>
                                <span class="text-gray-700 font-medium">Smarter Suggestions using real keyword data</span>
                            </li>
                            <li class="flex items-start">
                                <div class="bg-purple-100 p-1 rounded-full mr-3 mt-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-purple-600">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                </div>
                                <span class="text-gray-700 font-medium">See organic traffic data directly in WordPress</span>
                            </li>
                            <li class="flex items-start">
                                <div class="bg-purple-100 p-1 rounded-full mr-3 mt-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-purple-600">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                </div>
                                <span class="text-gray-700 font-medium">Identify broken links and 404 errors quickly</span>
                            </li>
                            <li class="flex items-start">
                                <div class="bg-purple-100 p-1 rounded-full mr-3 mt-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-purple-600">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                </div>
                                <span class="text-gray-700 font-medium">100% Free Integration</span>
                            </li>
                        </ul>
                    </div>

                    <div class="flex justify-center items-center">
                        <div class="relative bg-gray-50 rounded-2xl p-10 w-full flex items-center justify-between border border-gray-100 shadow-inner">
                            
                            <div class="flex flex-col items-center z-10">
                                <div class="w-20 h-20 bg-white rounded-xl shadow-md flex items-center justify-center border border-gray-200">
                                    <svg viewBox="0 0 48 48" class="w-10 h-10" xmlns="http://www.w3.org/2000/svg"><path fill="#4285F4" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#34A853" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#EA4335" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
                                </div>
                                <span class="text-xs font-semibold text-gray-500 mt-3">Google</span>
                            </div>

                            <div class="flex-1 px-4 relative flex items-center justify-center">
                                <svg class="w-full h-8" viewBox="0 0 100 20" xmlns="http://www.w3.org/2000/svg">
                                    <line x1="0" y1="10" x2="100" y2="10" stroke="#CBD5E1" stroke-width="2" stroke-dasharray="8 6" class="animate-dash" />
                                    <circle cx="50" cy="10" r="4" fill="white" stroke="#7F5AF0" stroke-width="2" />
                                    <!-- little arrow/triangle -->
                                    <path d="M52 10 L46 7 V13 Z" fill="#7F5AF0" />
                                </svg>
                            </div>

                            <div class="flex flex-col items-center z-10">
                                <div class="w-20 h-20 bg-white rounded-xl shadow-md flex items-center justify-center border border-gray-200 p-2.5">
                                    <img class="lw-logo" src="<?php echo esc_url($logo); ?>" alt="Link Whisper" />
                                </div>
                                <span class="text-xs font-semibold text-gray-500 mt-3">Link Whisper</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-8 mt-4 border-t border-gray-100">
                    <a href="#" class="wpil-wizard-link text-gray-400 hover:text-gray-600 font-medium text-sm transition-colors" data-wpil-wizard-link-id="scanning">
                        Skip this step
                    </a>
                    <button id="wpil-gsc-connect-btn" class="lw-gradient-bg <?php echo ($gsc_connected) ? 'opacity-65 cursor-not-allowed': '';?> text-white font-bold text-lg px-8 py-3.5 rounded-xl shadow-lg hover:shadow-xl hover:opacity-95 transition-all focus:ring-4 focus:ring-purple-200 outline-none flex items-center" onclick="window.location.href='<?php echo esc_url(Wpil_Settings::getGSCAuthUrl(true)); ?>'">
                        <?php if ($gsc_connected) { ?>
                        <span class="flex items-center gap-2">
                            <svg class="w-5 h-5 animate-spin" viewBox="0 0 24 24" fill="none">
                                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" opacity="0.25"></circle>
                                <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path>
                            </svg>
                            <span>Connected!</span>
                        </span>
                        <?php } else { ?>
                        <span>Search Console</span>
                        <?php } ?>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 ml-2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                        </svg>
                    </button>
                </div>
            </div>

        </div>

    </div>
</div>
