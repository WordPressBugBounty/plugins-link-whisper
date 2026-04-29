<?php
    $logo = plugin_dir_url(__DIR__).'../images/lw-icon.png';
    $wizard_ai_connected = (Wpil_Settings::get_linkwhisper_ai_active() && !empty(Wpil_Settings::get_linkwhisper_ai_user_id())) && !empty(Wpil_Settings::get_linkwhisper_ai_token());
    $license_active = $wizard_ai_connected;
    $wizard_ai_email = Wpil_Settings::get_linkwhisper_ai_user_email();
    $ai_setup_error = [];

    // if the user has authed GSC, check the status
    $gsc_connected = false;
    if(Wpil_Settings::HasGSCCredentials()){
        Wpil_SearchConsole::refresh_auth_token();
        $gsc_connected = (Wpil_SearchConsole::is_authenticated() && !empty(get_option('wpil_gsc_app_authorized', false)));
    }

    $money_pages_next_page = ($gsc_connected) ? 'scanning': 'connect-gsc';
?>
<div class="bg-gray-100 wpil-setup-wizard wrap wpil_styles wizard-welcome wpil-wizard-page">
    <div>
        <style>
            .wpil-wizard-page .notice{
                display:none !important;
            }
            .lw-gradient-bg {
                background: linear-gradient(90deg, #7F5AF0 0%, #2C6BFF 100%);
            }
            .lw-text-gradient {
                background: linear-gradient(90deg, #7F5AF0 0%, #2C6BFF 100%);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
            }
            
            /* Floating Animation for the Hero Graphic */
            @keyframes float {
                0% { transform: translateY(0px); }
                50% { transform: translateY(-10px); }
                100% { transform: translateY(0px); }
            }
            .animate-float {
                animation: float 4s ease-in-out infinite;
            }

            /* Avatar stacking */
            .avatar-stack:hover { z-index: 10; transform: translateY(-2px); }

            .wpil-setup-wizard{
                background: #f0f0f1;
                height: calc(100% + 42px);
                width: 100%;
                /*display: flex;
                justify-content: center;*/
                /*text-align: center;*/

                /* temp, remove later */
                position: absolute;
                top: -42px;
                left: -182px;
                z-index: 99999;
                height: calc(100% + 42px);
                min-height: 100vh !important;
                width: calc(100vw);
                width: calc(100% + 182px);
                overflow-x: hidden !important;
            }

            .wpil-setup-wizard.wpil-wizard-page-hidden{
                display: none;
            }
        </style>
    </div>
    <div class="min-h-screen flex items-center justify-center p-6 antialiased font-sans">

        <div class="bg-white w-full max-w-5xl rounded-2xl shadow-xl overflow-hidden flex flex-col min-h-[600px]">
            
            <div class="flex-1 flex flex-col md:flex-row p-8 md:p-16 items-center">
                
                <div class="md:w-1/2 space-y-8 z-10">
                    
                    <div class="w-16 h-16 bg-white rounded-xl shadow-md border border-gray-100 flex items-center justify-center mb-6">
                        <img class="lw-logo" src="<?php echo esc_url($logo); ?>" alt="Link Whisper" />
                    </div>

                    <div>
                        <h1 class="text-4xl md:text-5xl font-extrabold text-gray-900 tracking-tight leading-tight">
                            Welcome to <br>
                            <span class="lw-text-gradient">Link Whisper</span>
                        </h1>
                        <p class="mt-4 text-lg text-gray-600 leading-relaxed max-w-md text-base">
                            Let's supercharge your site structure. You are minutes away from smarter internal linking and better rankings.
                        </p>
                    </div>

                    <div class="space-y-4">
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center text-green-600">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941" />
                                </svg>
                            </div>
                            <span class="font-medium text-gray-700">Boost Google Rankings</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-600">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
                                </svg>
                            </div>
                            <span class="font-medium text-gray-700">Build a Deeper Site Network</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 rounded-full bg-purple-100 flex items-center justify-center text-purple-600">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z" />
                                </svg>
                            </div>
                            <span class="font-medium text-gray-700">Optional AI-Powered Suggestions</span>
                        </div>
                    </div>

                    <div class="pt-6 border-t border-gray-100">
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">Trusted by 40,000+ Smart Site Owners</p>
                        <div class="flex items-center -space-x-2">
                            <img class="w-8 h-8 rounded-full border-2 border-white avatar-stack" src="https://i.pravatar.cc/100?img=1" alt="User">
                            <img class="w-8 h-8 rounded-full border-2 border-white avatar-stack" src="https://i.pravatar.cc/100?img=12" alt="User">
                            <img class="w-8 h-8 rounded-full border-2 border-white avatar-stack" src="https://i.pravatar.cc/100?img=33" alt="User">
                            <img class="w-8 h-8 rounded-full border-2 border-white avatar-stack" src="https://i.pravatar.cc/100?img=47" alt="User">
                            <div class="w-8 h-8 rounded-full border-2 border-white bg-gray-100 flex items-center justify-center text-[10px] font-bold text-gray-500 z-0">
                                +40k
                            </div>
                            <div class="flex items-center ml-4">
                                <div class="flex text-yellow-400">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="md:w-1/2 mt-12 md:mt-0 relative flex justify-center items-center">
                    <div class="relative w-80 h-80 animate-float">
                        <div class="absolute top-0 right-0 w-64 h-64 bg-purple-100 rounded-full mix-blend-multiply filter blur-xl opacity-70 animate-blob"></div>
                        <div class="absolute top-0 -left-4 w-64 h-64 bg-blue-100 rounded-full mix-blend-multiply filter blur-xl opacity-70 animate-blob animation-delay-2000"></div>
                        
                        <div class="relative z-10 bg-white/80 backdrop-blur-md border border-white p-6 rounded-2xl shadow-2xl transform rotate-3">
                            <div class="flex items-center space-x-3 mb-4">
                                <div class="h-3 w-3 rounded-full bg-red-400"></div>
                                <div class="h-3 w-3 rounded-full bg-yellow-400"></div>
                                <div class="h-3 w-3 rounded-full bg-green-400"></div>
                            </div>
                            <div class="space-y-3">
                                <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                                <div class="h-4 bg-gray-200 rounded w-1/2"></div>
                                <div class="flex items-center space-x-2 p-3 bg-purple-50 rounded-lg border border-purple-100 mt-4">
                                    <div class="w-6 h-6 rounded-full bg-purple-200 flex items-center justify-center">
                                        <svg class="w-3 h-3 text-[#7F5AF0]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg>
                                    </div>
                                    <div>
                                        <div class="h-2 bg-purple-200 rounded w-24 mb-1"></div>
                                        <div class="h-2 bg-purple-100 rounded w-16"></div>
                                    </div>
                                    <div class="ml-auto text-green-500">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="absolute -top-6 right-10 bg-white p-3 rounded-xl shadow-lg border border-gray-100 transform -rotate-6 animate-bounce" style="animation-duration: 3s;">
                            <span class="text-xs font-bold text-gray-500">Keywords Found</span>
                            <div class="text-xl font-bold text-[#7F5AF0]">1,204</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-gray-50 border-t border-gray-100 px-8 py-6 flex flex-col md:flex-row justify-between items-center">
                
                <div class="flex items-center space-x-6 text-sm font-medium order-2 md:order-1 mt-4 md:mt-0">
                    <div class="flex items-center space-x-2 <?php echo $wizard_ai_connected ? 'text-green-500' : 'text-gray-800'; ?>">
                        <?php if($wizard_ai_connected) { ?>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6">
                            <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" />
                        </svg>
                        <?php }else{ ?>
                        <div class="w-6 h-6 rounded-full bg-white border border-gray-300 text-gray-500 flex items-center justify-center text-xs">1</div>
                        <?php } ?>
                        <span>Connect AI</span>
                    </div>
                    <div class="h-px w-8 bg-gray-300"></div>
                    <div class="flex items-center space-x-2 text-gray-400">
                        <div class="w-6 h-6 rounded-full border-gray-200 border flex items-center justify-center text-xs">2</div>
                        <span>Money Pages</span>
                    </div>
                    <div class="h-px w-8 bg-gray-200"></div>

                    <div class="flex items-center space-x-2 text-gray-400">
                        <div class="w-6 h-6 rounded-full border border-gray-200 flex items-center justify-center text-xs">3</div>
                        <span>Search Console</span>
                    </div>
                    <div class="h-px w-8 bg-gray-200"></div>

                    <div class="flex items-center space-x-2 text-gray-400">
                        <div class="w-6 h-6 rounded-full border border-gray-200 flex items-center justify-center text-xs">4</div>
                        <span>Scan Site</span>
                    </div>
                </div>

                <button class="wpil-wizard-link order-1 md:order-2 lw-gradient-bg text-white font-bold text-lg px-12 py-3.5 rounded-xl shadow-lg hover:shadow-xl hover:opacity-95 transition-all focus:ring-4 focus:ring-purple-200 outline-none flex items-center transform hover:scale-[1.02]" data-wpil-wizard-link-id="<?php echo (!$wizard_ai_connected) ? 'license': 'moneypage';?>">
                    <span>Get Started</span>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 ml-2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                    </svg>
                </button>
            </div>

        </div>

    </div>
</div>
