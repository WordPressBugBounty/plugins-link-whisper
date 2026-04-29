<!DOCTYPE html>
<html lang="en" class="bg-gray-100">
<head>
    <style>
        .lw-gradient-bg {
            background: linear-gradient(90deg, #7F5AF0 0%, #2C6BFF 100%);
        }
        .lw-focus-ring:focus {
            border-color: #7F5AF0;
            box-shadow: 0 0 0 4px rgba(127, 90, 240, 0.1);
            outline: none;
        }
        
        /* Spinner Animation */
        .spinner {
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top: 3px solid white;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
    <script>
        function processPayment() {
            const btn = document.getElementById('pay-btn');
            const btnText = document.getElementById('pay-text');
            const btnSpinner = document.getElementById('pay-spinner');
            
            // UI Loading State
            btn.classList.add('opacity-75', 'cursor-wait');
            btnText.innerText = 'Processing...';
            btnSpinner.classList.remove('hidden');
            
            // Simulate processing delay (2 seconds)
            setTimeout(() => {
                // In a real app, you would route to the Success state here
                alert("Payment Successful! Transitioning to Success State...");
                btn.classList.remove('opacity-75', 'cursor-wait');
                btnText.innerText = 'Pay $75.00';
                btnSpinner.classList.add('hidden');
            }, 2000);
        }

        // Simple formatter for card number aesthetics
        function formatCard(input) {
            let value = input.value.replace(/\D/g, '');
            value = value.match(/.{1,4}/g)?.join(' ') || value;
            input.value = value.substring(0, 19); // Limit length
        }
    </script>
</head>
<body class="min-h-screen flex items-center justify-center p-6 antialiased font-sans relative">

    <div class="relative w-full max-w-4xl bg-white rounded-2xl shadow-2xl overflow-hidden border border-gray-100 flex flex-col md:flex-row">
        
        <div class="bg-gray-50 p-8 md:w-1/3 border-r border-gray-100 flex flex-col">
            <h2 class="text-lg font-bold text-gray-900 mb-6">Order Summary</h2>
            
            <div class="bg-white rounded-xl p-4 border border-gray-200 shadow-sm mb-6 relative overflow-hidden">
                <div class="absolute top-0 right-0 w-16 h-16 bg-gradient-to-br from-purple-100 to-transparent -mr-8 -mt-8 rounded-full"></div>
                
                <div class="relative z-10">
                    <div class="text-xs font-bold text-[#7F5AF0] uppercase mb-1">Selected Plan</div>
                    <div class="text-xl font-bold text-gray-900">Power User</div>
                    <div class="text-sm text-gray-500 mb-3">50,000 Credits</div>
                    <div class="flex justify-between items-end border-t border-gray-100 pt-3">
                        <span class="text-sm text-gray-400">Total</span>
                        <span class="text-xl font-bold text-gray-800">$75.00</span>
                    </div>
                </div>
            </div>

            <div class="mt-auto">
                <div class="flex items-center space-x-2 text-gray-400 text-xs mb-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-green-500">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z" />
                    </svg>
                    <span>Secure SSL Connection</span>
                </div>
                <div class="text-xs text-gray-400 leading-tight">
                    Your payment is processed securely. We do not store your full card details.
                </div>
            </div>
        </div>

        <div class="p-8 md:w-2/3 bg-white">
            
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-2xl font-bold text-gray-900">Payment Details</h3>
                <button class="text-sm text-gray-400 hover:text-gray-600 flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 mr-1">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                    </svg>
                    Back to Plans
                </button>
            </div>

            <form onsubmit="event.preventDefault(); processPayment();">
                <div class="space-y-5">
                    
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1.5 ml-1">Name on Card</label>
                        <input type="text" placeholder="John Doe" class="w-full bg-gray-50 border border-gray-200 text-gray-900 rounded-xl px-4 py-3 lw-focus-ring transition-all placeholder-gray-400" required>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1.5 ml-1">Card Number</label>
                        <div class="relative">
                            <input type="text" placeholder="0000 0000 0000 0000" oninput="formatCard(this)" class="w-full bg-gray-50 border border-gray-200 text-gray-900 rounded-xl pl-12 pr-4 py-3 lw-focus-ring transition-all placeholder-gray-400 font-mono" maxlength="19" required>
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <svg class="h-6 w-6 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-5">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1.5 ml-1">Expiration</label>
                            <input type="text" placeholder="MM / YY" class="w-full bg-gray-50 border border-gray-200 text-gray-900 rounded-xl px-4 py-3 lw-focus-ring transition-all placeholder-gray-400 text-center" maxlength="5" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1.5 ml-1">CVC / Cvv</label>
                            <div class="relative">
                                <input type="text" placeholder="123" class="w-full bg-gray-50 border border-gray-200 text-gray-900 rounded-xl px-4 py-3 lw-focus-ring transition-all placeholder-gray-400 text-center" maxlength="3" required>
                                <svg xmlns="http://www.w3.org/2000/svg" class="absolute right-3 top-3.5 h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1.5 ml-1">Billing Zip / Postal Code</label>
                        <input type="text" placeholder="90210" class="w-1/2 bg-gray-50 border border-gray-200 text-gray-900 rounded-xl px-4 py-3 lw-focus-ring transition-all placeholder-gray-400" required>
                    </div>

                </div>

                <button id="pay-btn" type="submit" class="w-full mt-8 lw-gradient-bg text-white font-bold text-lg py-4 rounded-xl shadow-lg hover:shadow-xl hover:opacity-95 transition-all flex items-center justify-center relative">
                    <span id="pay-text">Pay $75.00</span>
                    <div id="pay-spinner" class="spinner hidden ml-3"></div>
                </button>
                
                <div class="mt-4 flex justify-center space-x-4 opacity-50 grayscale hover:grayscale-0 transition-all">
                   <div class="h-6 w-10 bg-gray-200 rounded"></div>
                   <div class="h-6 w-10 bg-gray-200 rounded"></div>
                   <div class="h-6 w-10 bg-gray-200 rounded"></div>
                </div>

            </form>

        </div>
    </div>

</body>
</html>