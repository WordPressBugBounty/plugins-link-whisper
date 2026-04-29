<style>
/* ===== LW Credit Checkout Modal (WP-safe) ===== */
#lw-credit-checkout-modal{
  position: fixed;
  inset: 0;
  z-index: 999999; /* above WP admin */
  display: none;   /* toggled by JS */
}

/* optional: when open, lock page scroll */
body.lwcc-open{ overflow: hidden; }

#lw-credit-checkout-modal .lwcc-backdrop{
  position: absolute;
  inset: 0;
  background: rgba(15, 23, 42, 0.45); /* slate-ish */
  backdrop-filter: blur(2px);
}

#lw-credit-checkout-modal .lwcc-panel{
  position: relative;
  z-index: 1;
  width: min(980px, calc(100vw - 40px));
  max-height: calc(100vh - 40px);
  margin: 20px auto;
  background: #fff;
  border-radius: 16px;
  box-shadow: 0 20px 50px rgba(0,0,0,.25);
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

/* header area inside panel if you have one */
#lw-credit-checkout-modal .lwcc-panel-header{
  flex: 0 0 auto;
  padding: 14px 16px;
  border-bottom: 1px solid #e5e7eb;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

#lw-credit-checkout-modal .lwcc-close{
  appearance: none;
  border: 0;
  background: transparent;
  font-size: 22px;
  line-height: 1;
  cursor: pointer;
  color: #64748b;
}
#lw-credit-checkout-modal .lwcc-close:hover{ color: #0f172a; }

#lw-credit-checkout-modal .lwcc-body{
  flex: 1 1 auto;
  overflow: auto; /* scroll inside modal */
  padding: 18px;
}

/* two-column layout */
#lw-credit-checkout-modal .lwcc-grid{
  display: grid;
  grid-template-columns: 360px 1fr;
  gap: 18px;
  align-items: start;
}

@media (max-width: 900px){
  #lw-credit-checkout-modal .lwcc-grid{
    grid-template-columns: 1fr;
  }
}

/* cards */
#lw-credit-checkout-modal .lwcc-card{
  border: 1px solid #e5e7eb;
  border-radius: 14px;
  padding: 16px;
  background: #fff;
}

/* Fallback layout for non-wizard pages (no Tailwind) */
#lw-credit-checkout-modal .lwcc-shell{
  display: flex;
  flex-direction: column;
}
@media (min-width: 768px){
  #lw-credit-checkout-modal .lwcc-shell{ flex-direction: row; }
}
#lw-credit-checkout-modal .lwcc-left{
  background: #f8fafc;
  padding: 32px;
  border-right: 1px solid #e5e7eb;
  width: 100%;
}
@media (min-width: 768px){
  #lw-credit-checkout-modal .lwcc-left{ width: 33%; }
}
#lw-credit-checkout-modal .lwcc-right{
  padding: 32px;
  flex: 1;
  min-height: 420px;
  position: relative;
  background: #fff;
}
#lw-credit-checkout-modal .lwcc-icon-wrap{
  width: 48px;
  height: 48px;
  border-radius: 999px;
  background: #fee2e2;
  color: #ef4444;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 16px;
}
#lw-credit-checkout-modal .lwcc-icon-wrap svg{
  width: 24px;
  height: 24px;
}

/* Stripe container must be width-constrained */
#lw-credit-checkout-modal #lwcc-payment-element{
  width: 100%;
  max-width: 100%;
}

/* Stripe sometimes injects wide children. Force containment. */
#lw-credit-checkout-modal #lwcc-payment-element *{
  max-width: 100%;
  box-sizing: border-box;
}

/* Typography and spacing fallbacks for non-Tailwind screens */
#lw-credit-checkout-modal .lwcc-left h2{
  margin: 0 0 8px;
  font-size: 24px;
  line-height: 1.2;
  font-weight: 800;
  color: #0f172a;
}
#lw-credit-checkout-modal .lwcc-left p{
  margin: 0 0 24px;
  font-size: 14px;
  line-height: 1.6;
  color: #64748b;
}
#lw-credit-checkout-modal .lwcc-left .bg-white{
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 14px;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
}
#lw-credit-checkout-modal .lwcc-left .bg-white .uppercase{
  font-size: 11px;
  letter-spacing: .08em;
  text-transform: uppercase;
  color: #94a3b8;
  margin-bottom: 8px;
}
#lw-credit-checkout-modal .lwcc-left .flex.justify-between{
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  margin-bottom: 6px;
  font-size: 14px;
}
#lw-credit-checkout-modal .lwcc-left .text-gray-600{
  color: #475569;
}
#lw-credit-checkout-modal .lwcc-left .font-bold.text-gray-900{
  color: #0f172a;
  font-weight: 700;
}

#lw-credit-checkout-modal #lwcc-message{
  display: none;
  margin-bottom: 14px;
  border-radius: 12px;
  border: 1px solid #e5e7eb;
  padding: 10px 12px;
  font-size: 13px;
  font-weight: 600;
}
#lw-credit-checkout-modal #lwcc-message.is-info{
  display: block;
  color: #334155;
  background: #f8fafc;
  border-color: #e2e8f0;
}
#lw-credit-checkout-modal #lwcc-message.is-success{
  display: block;
  color: #166534;
  background: #ecfdf3;
  border-color: #bbf7d0;
}
#lw-credit-checkout-modal #lwcc-message.is-error{
  display: block;
  color: #b91c1c;
  background: #fef2f2;
  border-color: #fecaca;
}

#lw-credit-checkout-modal #lwcc-loader{
  width: 20px;
  height: 20px;
  border-radius: 999px;
  border: 2px solid rgba(15,23,42,.2);
  border-top-color: rgba(15,23,42,.7);
  animation: lwcc-spin .9s linear infinite;
}

#lw-credit-checkout-modal #lwcc-form{
  margin: 0;
}
#lw-credit-checkout-modal #lwcc-payment-element{
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 14px;
}
#lw-credit-checkout-modal #lwcc-submit{
  min-width: 110px;
  background: linear-gradient(90deg, #7F5AF0 0%, #2C6BFF 100%);
  color: #fff;
  border: 0;
  padding: 10px 18px;
  border-radius: 12px;
  font-weight: 700;
  font-size: 15px;
  line-height: 1.2;
  text-transform: none !important;
  letter-spacing: 0;
  cursor: pointer;
  display: inline-flex !important;
  align-items: center;
  justify-content: center;
  margin-left: auto;
  box-shadow: 0 8px 18px rgba(44, 107, 255, 0.22);
}
#lw-credit-checkout-modal #lwcc-submit[disabled]{
  opacity: .6;
  cursor: not-allowed;
}
#lw-credit-checkout-modal #lwcc-errors{
  margin-top: 10px;
  color: #be123c;
  font-size: 13px;
  font-weight: 600;
}

#lw-credit-checkout-modal .lwcc-submit-row{
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}

@keyframes lwcc-spin{
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

</style>
<!-- CREDIT PURCHASE MODAL -->
<div id="lw-credit-checkout-modal" class="lwcc-modal fixed inset-0 z-[999999] hidden">
  <!-- Backdrop (click outside to close) -->
  <div class="lwcc-backdrop absolute inset-0 bg-slate-900/50" data-lwcc-close="1"></div>

  <!-- Panel -->
  <div class="lwcc-panel relative mx-auto mt-[8vh] w-[min(960px,calc(100%-40px))]">
    <div class="lwcc-shell bg-white rounded-2xl shadow-2xl border border-gray-100 flex flex-col md:flex-row">
      
      <!-- Left column -->
      <div class="lwcc-left bg-gray-50 p-8 md:w-1/3 border-r border-gray-100 flex flex-col justify-between">
        <div>
          <div class="lwcc-icon-wrap w-12 h-12 bg-red-100 rounded-full flex items-center justify-center text-red-500 mb-4">
            <!-- warning icon -->
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
            </svg>
          </div>

          <h2 class="text-xl font-bold text-gray-900 mb-2">Add AI Credits</h2>
          <p class="text-sm text-gray-500 mb-6 leading-relaxed">
            Purchase credits to continue scanning. Your progress is saved.
          </p>

          <div class="bg-white rounded-xl p-4 border border-gray-200 shadow-sm">
            <div class="text-xs font-bold text-gray-400 uppercase mb-3">Purchase Summary</div>

            <div class="flex justify-between text-sm mb-1">
              <span class="text-gray-600">Credits</span>
              <span class="font-bold text-gray-900"><span id="lwcc-credits">—</span></span>
            </div>

            <div class="flex justify-between text-sm mb-3">
              <span class="text-gray-600">Total</span>
              <span class="font-bold text-gray-900"><span id="lwcc-price">$—</span></span>
            </div>

            <div class="mt-3 text-xs text-gray-400">
              Secure payment via Stripe
            </div>
          </div>
        </div>

        <div class="mt-8 text-xs text-gray-400 text-center">
          Secure 256-bit SSL Encrypted Payment
        </div>
      </div>

      <!-- Right column -->
      <div class="lwcc-right p-8 md:w-2/3 bg-white relative min-h-[420px]">
        <!-- close button -->
        <button type="button"
                style="display:none"
                class="absolute right-4 top-4 w-10 h-10 rounded-xl border border-gray-200 bg-white text-gray-500 hover:text-gray-700 hover:bg-gray-50"
                data-lwcc-close="1"
                aria-label="Close">
          &times;
        </button>

        <div id="lwcc-message" class="lwcc-message hidden mb-4 rounded-xl border px-4 py-3 text-sm font-semibold"></div>
        <div id="lwcc-loader" class="hidden mb-4 w-5 h-5 rounded-full border-2 border-slate-900/20 border-t-slate-900/70 animate-spin"></div>

        <form id="lwcc-form">
          <div id="lwcc-payment-element" class="border border-gray-200 rounded-xl p-4"></div>

          <div class="mt-5 flex justify-end lwcc-submit-row">
            <button type="submit" id="lwcc-submit" style="min-width: 100px" class="lw-gradient-bg text-white font-bold text-base px-6 py-3 rounded-xl shadow-lg hover:opacity-95 transition-all">Pay</button>
          </div>

          <div id="lwcc-errors" class="mt-3 text-sm font-semibold text-rose-700"></div>
        </form>
      </div>

    </div>
  </div>
</div>
