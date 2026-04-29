<!DOCTYPE html>
<html lang="en" class="bg-gray-100">
<head>
    <style>
        .lw-gradient-bg {
            background: linear-gradient(90deg, #7F5AF0 0%, #2C6BFF 100%);
        }
        .lw-text-gradient {
            background: linear-gradient(90deg, #7F5AF0 0%, #2C6BFF 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .lw-border-gradient {
            border: 2px solid transparent;
            background-image: linear-gradient(white, white), linear-gradient(90deg, #7F5AF0 0%, #2C6BFF 100%);
            background-origin: border-box;
            background-clip: content-box, border-box;
        }
        
        /* Animations */
        @keyframes pulse-subtle {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.02); }
        }
        .animate-pulse-subtle {
            animation: pulse-subtle 3s infinite ease-in-out;
        }

        /* Blur Backdrop */
        .backdrop-blur-custom {
            backdrop-filter: blur(8px);
            background-color: rgba(255, 255, 255, 0.6);
        }
    </style>
    <script>
        // Simulation logic for the UX demonstration
        function selectPlan(plan) {
            // Hide pricing, show processing
            document.getElementById('pricing-grid').classList.add('hidden');
            document.getElementById('processing-state').classList.remove('hidden');
            
            // Simulate API call
            setTimeout(() => {
                document.getElementById('processing-state').classList.add('hidden');
                document.getElementById('success-state').classList.remove('hidden');
                
                // Update background numbers to show "refilled" state
                document.getElementById('bg-credits').innerText = "50,635";
                document.getElementById('bg-credits').classList.add('text-green-600');
            }, 2000);
        }

        function resumeScan() {
            // In a real app, this would close modal and resume
            alert("Credits added! Resuming scan...");
        }
    </script>
</head>
<body class="min-h-screen flex items-center justify-center p-6 antialiased font-sans overflow-hidden relative">

    <div class="absolute inset-0 z-0 flex flex-col items-center justify-center pointer-events-none select-none" aria-hidden="true">
        <div class="bg-white w-full max-w-5xl h-[600px] rounded-2xl shadow-xl border border-gray-100 p-12 opacity-40 filter blur-sm transform scale-[0.98]">
            <div class="space-y-8">
                <h1 class="text-3xl font-bold text-gray-900">Scanning Progress</h1>
                <div class="space-y-6">
                    <div>
                        <div class="flex justify-between mb-2"><span class="font-bold text-gray-700">Scanning Links</span><span>100%</span></div>
                        <div class="w-full bg-gray-200 rounded-full h-3"><div class="bg-green-500 h-3 rounded-full w-full"></div></div>
                    </div>
                    <div>
                        <div class="flex justify-between mb-2"><span class="font-bold text-gray-700">Processing Keywords</span><span class="text-[#7F5AF0]">Paused</span></div>
                        <div class="w-full bg-gray-200 rounded-full h-3"><div class="lw-gradient-bg h-3 rounded-full w-[78%]"></div></div>
                    </div>
                </div>
                 <div class="bg-red-50 border border-red-100 rounded-xl p-5 w-64">
                    <div class="text-xs font-semibold text-red-400 uppercase">Credits Available</div>
                    <div id="bg-credits" class="text-3xl font-bold text-red-500">0</div>
                </div>
            </div>
        </div>
    </div>

    <div class="relative z-10 w-full max-w-4xl bg-white rounded-2xl shadow-2xl overflow-hidden border border-gray-100 flex flex-col md:flex-row animate-up">
        
        <div class="bg-gray-50 p-8 md:w-1/3 border-r border-gray-100 flex flex-col justify-between">
            <div>
                <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center text-red-500 mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                    </svg>
                </div>
                <h2 class="text-xl font-bold text-gray-900 mb-2">Scan Paused</h2>
                <p class="text-sm text-gray-500 mb-6 leading-relaxed">
                    You've run out of AI credits mid-scan. Don't worry, your progress is saved.
                </p>

                <div class="bg-white rounded-xl p-4 border border-gray-200 shadow-sm">
                    <div class="text-xs font-bold text-gray-400 uppercase mb-3">Resource Analysis</div>
                    
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-gray-600">Credits needed</span>
                        <span class="font-bold text-gray-900">635</span>
                    </div>
                    <div class="flex justify-between text-sm mb-3">
                        <span class="text-gray-600">Your balance</span>
                        <span class="font-bold text-red-500">0</span>
                    </div>

                    <div class="w-full bg-red-100 rounded-full h-2 relative">
                        <div class="absolute top-0 left-0 h-2 bg-gray-300 rounded-l-full w-[0%]"></div> <div class="absolute -right-1 -top-1">
                            <span class="flex h-4 w-4">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-4 w-4 bg-red-500"></span>
                            </span>
                        </div>
                    </div>
                    <div class="mt-2 text-xs text-red-500 font-medium text-center">
                        Refill required to continue
                    </div>
                </div>
            </div>

            <div class="mt-8 text-xs text-gray-400 text-center">
                Secure 256-bit SSL Encrypted Payment
            </div>
        </div>

        <div class="p-8 md:w-2/3 bg-white relative min-h-[500px]">
            
            <div id="pricing-grid" class="h-full flex flex-col">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-2xl font-bold text-gray-900">Recharge your AI</h3>
                    <a href="#" class="text-sm text-gray-400 hover:text-gray-600">View enterprise plans</a>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 flex-1">
                    
                    <div class="border border-gray-200 rounded-xl p-5 hover:border-purple-300 cursor-pointer transition-all hover:shadow-md flex flex-col justify-between group" onclick="selectPlan('standard')">
                        <div>
                            <h4 class="font-bold text-gray-600 mb-1">Starter Pack</h4>
                            <div class="text-3xl font-bold text-gray-900 mb-1">5,000 <span class="text-sm font-normal text-gray-500">credits</span></div>
                            <div class="text-2xl font-medium text-gray-800">$15</div>
                        </div>
                        <div class="mt-4">
                            <ul class="text-sm text-gray-500 space-y-2 mb-4">
                                <li class="flex items-center"><svg class="w-4 h-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Enough for ~10 posts</li>
                                <li class="flex items-center"><svg class="w-4 h-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>No expiration</li>
                            </ul>
                            <button class="w-full py-2 rounded-lg border border-gray-300 text-gray-700 font-bold group-hover:border-[#7F5AF0] group-hover:text-[#7F5AF0] transition-colors">Select</button>
                        </div>
                    </div>

                    <div class="relative border-2 border-[#7F5AF0] rounded-xl p-5 shadow-lg flex flex-col justify-between cursor-pointer transform hover:scale-[1.02] transition-all bg-purple-50/20" onclick="selectPlan('pro')">
                        <div class="absolute -top-3 left-1/2 transform -translate-x-1/2 bg-[#7F5AF0] text-white text-xs font-bold px-3 py-1 rounded-full shadow-sm">
                            MOST POPULAR
                        </div>
                        <div>
                            <h4 class="font-bold text-[#7F5AF0] mb-1">Power User</h4>
                            <div class="text-3xl font-bold text-gray-900 mb-1">50,000 <span class="text-sm font-normal text-gray-500">credits</span></div>
                            <div class="text-2xl font-medium text-gray-800">$75 <span class="text-sm text-green-600 font-bold line-through ml-2 opacity-50">$150</span></div>
                        </div>
                        <div class="mt-4">
                            <div class="bg-green-100 text-green-800 text-xs font-bold px-2 py-1 rounded inline-block mb-3">Save 50%</div>
                            <ul class="text-sm text-gray-600 space-y-2 mb-4">
                                <li class="flex items-center"><svg class="w-4 h-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Enough for ~100 posts</li>
                                <li class="flex items-center"><svg class="w-4 h-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Priority Support</li>
                            </ul>
                            <button class="w-full py-2.5 rounded-lg lw-gradient-bg text-white font-bold shadow-md hover:opacity-90 transition-opacity">
                                Quick Buy
                            </button>
                        </div>
                    </div>

                </div>
                
                <div class="mt-6 pt-4 border-t border-gray-100 flex justify-between items-center text-sm">
                    <span class="text-gray-500">Payment method ending in <span class="text-gray-800 font-mono">•••• 4242</span></span>
                    <a href="#" class="text-[#7F5AF0] font-medium hover:underline">Change</a>
                </div>
            </div>

            <div id="processing-state" class="hidden h-full flex flex-col items-center justify-center text-center">
                <div class="w-16 h-16 mb-4">
                    <svg class="animate-spin text-[#7F5AF0]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" style="opacity: 0.25;"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-gray-900">Processing Payment...</h3>
                <p class="text-gray-500 mt-2">Connecting to secure gateway</p>
            </div>

            <div id="success-state" class="hidden h-full flex flex-col items-center justify-center text-center animate-slide-up">
                <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center text-green-500 mb-6 shadow-lg shadow-green-100">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-10 h-10">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                    </svg>
                </div>
                <h3 class="text-2xl font-bold text-gray-900 mb-2">Credits Added!</h3>
                <p class="text-gray-500 mb-8 max-w-xs mx-auto">
                    50,000 credits have been added to your account. Your scan is ready to resume.
                </p>
                
                <button onclick="resumeScan()" class="lw-gradient-bg text-white font-bold text-lg px-10 py-3.5 rounded-xl shadow-lg hover:shadow-xl hover:opacity-95 transition-all flex items-center">
                    <span>Resume Scan</span>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 ml-2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.348a1.125 1.125 0 010 1.971l-11.54 6.347a1.125 1.125 0 01-1.667-.985V5.653z" />
                    </svg>
                </button>
            </div>

        </div>
    </div>

</body>
</html>