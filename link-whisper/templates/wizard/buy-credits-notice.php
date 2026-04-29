<style>
    /* ===== Out of Credits Modal (WP-safe, matches lwcc vibe) ===== */
#wpil-out-of-credits-modal{
  position: fixed;
  inset: 0;
  z-index: 999999;
}
body.lwoc-open{ overflow:hidden; }

#wpil-out-of-credits-modal .lwoc-backdrop{
  position:absolute;
  inset:0;
  background: rgba(15,23,42,.45);
  backdrop-filter: blur(2px);
}

#wpil-out-of-credits-modal .lwoc-panel{
  position: relative;
  z-index: 1;
  width: min(960px, calc(100vw - 40px));
  margin: 20px auto;
  max-height: calc(100vh - 40px);
  border-radius: 16px;
  box-shadow: 0 20px 50px rgba(0,0,0,.25);
  overflow: hidden;
  background: #fff;
  display: grid;
  grid-template-columns: 360px 1fr;
}

@media (max-width: 900px){
  #wpil-out-of-credits-modal .lwoc-panel{ grid-template-columns: 1fr; }
}

#wpil-out-of-credits-modal .lwoc-left{
  background:#f8fafc;
  border-right: 1px solid #e5e7eb;
  padding: 28px;
  display:flex;
  flex-direction:column;
  gap: 14px;
}

#wpil-out-of-credits-modal .lwoc-icon{
  width: 48px;
  height: 48px;
  border-radius: 999px;
  background: #fee2e2;
  color: #ef4444;
  display:flex;
  align-items:center;
  justify-content:center;
}

#wpil-out-of-credits-modal .lwoc-title{
  font-size: 20px;
  font-weight: 800;
  color:#0f172a;
  margin: 0;
}
#wpil-out-of-credits-modal .lwoc-subtitle{
  font-size: 13px;
  color:#64748b;
  line-height: 1.5;
  margin: 0;
}

#wpil-out-of-credits-modal .lwoc-metrics{
  background:#fff;
  border: 1px solid #e5e7eb;
  border-radius: 14px;
  padding: 14px;
  box-shadow: 0 6px 18px rgba(15,23,42,.06);
}

#wpil-out-of-credits-modal .lwoc-row{
  display:flex;
  justify-content:space-between;
  font-size: 13px;
  color:#475569;
  margin-bottom: 8px;
}
#wpil-out-of-credits-modal .lwoc-row strong{
  color:#0f172a;
  font-weight: 800;
}
#wpil-out-of-credits-modal .lwoc-bad{ color:#ef4444 !important; }

#wpil-out-of-credits-modal .lwoc-bar{
  position: relative;
  margin-top: 10px;
  height: 10px;
  border-radius: 999px;
  background:#fee2e2;
  overflow:hidden;
}
#wpil-out-of-credits-modal .lwoc-bar-fill{
  height: 100%;
  border-radius: 999px;
  background: linear-gradient(90deg, #7F5AF0 0%, #2C6BFF 100%);
}

#wpil-out-of-credits-modal .lwoc-dot{
  position:absolute;
  right: 6px;
  top: 50%;
  transform: translateY(-50%);
  width: 14px;
  height: 14px;
}
#wpil-out-of-credits-modal .lwoc-dot-ping{
  position:absolute;
  inset:0;
  border-radius:999px;
  background:#fb7185;
  opacity:.35;
  animation: lwoc-ping 1.6s infinite;
}
#wpil-out-of-credits-modal .lwoc-dot-core{
  position:absolute;
  inset:3px;
  border-radius:999px;
  background:#ef4444;
}

@keyframes lwoc-ping{
  0%{ transform: scale(1); opacity:.35; }
  70%{ transform: scale(2.2); opacity:0; }
  100%{ transform: scale(2.2); opacity:0; }
}

#wpil-out-of-credits-modal .lwoc-hint{
  margin-top: 8px;
  font-size: 12px;
  font-weight: 700;
  color:#ef4444;
  text-align:center;
}

#wpil-out-of-credits-modal .lwoc-note{
  border: 1px solid #e5e7eb;
  border-radius: 14px;
  padding: 12px 14px;
  background:#fff;
}
#wpil-out-of-credits-modal .lwoc-note-title{
  font-size: 12px;
  font-weight: 900;
  color:#334155;
  text-transform: uppercase;
  letter-spacing: .04em;
  margin-bottom: 6px;
}
#wpil-out-of-credits-modal .lwoc-note-body{
  font-size: 12px;
  color:#64748b;
  line-height: 1.45;
}

#wpil-out-of-credits-modal .lwoc-foot{
  margin-top: auto;
  font-size: 11px;
  color:#94a3b8;
  text-align:center;
}

#wpil-out-of-credits-modal .lwoc-right{
  padding: 28px;
  display:flex;
  flex-direction:column;
  gap: 16px;
  min-height: 280px;
  position: relative;
}

#wpil-out-of-credits-modal .lwoc-right-head{
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap: 12px;
}

#wpil-out-of-credits-modal .lwoc-right-title{
  font-size: 20px;
  font-weight: 900;
  color:#0f172a;
}
#wpil-out-of-credits-modal .lwoc-right-sub{
  margin-top: 6px;
  font-size: 13px;
  color:#64748b;
}

#wpil-out-of-credits-modal .lwoc-close{
  appearance:none;
  border:0;
  background:transparent;
  font-size: 24px;
  line-height: 1;
  cursor:pointer;
  color:#64748b;
}
#wpil-out-of-credits-modal .lwoc-close:hover{ color:#0f172a; }

#wpil-out-of-credits-modal .lwoc-actions{
  margin-top: auto;
  display:flex;
  flex-direction:column;
  gap: 10px;
}

#wpil-out-of-credits-modal .lwoc-buy{
  width: 100%;
  border: 0;
  color: #fff;
  font-weight: 900;
  font-size: 16px;
  padding: 12px 14px;
  border-radius: 14px;
  box-shadow: 0 12px 30px rgba(127,90,240,.22);
  cursor:pointer;
}
#wpil-out-of-credits-modal .lwoc-buy:active{ transform: scale(.99); }

#wpil-out-of-credits-modal .lwoc-nope{
  width: 100%;
  border: 1px solid #e5e7eb;
  background:#fff;
  color:#475569;
  font-weight: 800;
  font-size: 14px;
  padding: 11px 14px;
  border-radius: 14px;
  cursor:pointer;
}
#wpil-out-of-credits-modal .lwoc-nope:hover{
  background:#f8fafc;
  color:#0f172a;
}

#wpil-out-of-credits-modal .lwoc-small{
  font-size: 12px;
  color:#94a3b8;
}

/* Right-side hero block */
#wpil-out-of-credits-modal .lwoc-hero{
  border: 1px solid #e5e7eb;
  border-radius: 16px;
  background: #f8fafc;
  padding: 16px;
  display: grid;
  grid-template-columns: 1.2fr .8fr;
  gap: 14px;
}

@media (max-width: 900px){
  #wpil-out-of-credits-modal .lwoc-hero{ grid-template-columns: 1fr; }
}

#wpil-out-of-credits-modal .lwoc-hero-graphic{
  height: 180px;
  border-radius: 14px;
  background: #ffffff;
  border: 1px solid #e5e7eb;
  overflow: hidden;
  display:flex;
  align-items:center;
  justify-content:center;
}

#wpil-out-of-credits-modal .lwoc-hero-copy{
  display:flex;
  flex-direction:column;
  justify-content:center;
}

#wpil-out-of-credits-modal .lwoc-hero-kicker{
  font-size: 12px;
  font-weight: 900;
  color:#334155;
  text-transform: uppercase;
  letter-spacing: .04em;
  margin-bottom: 8px;
}

#wpil-out-of-credits-modal .lwoc-hero-list{
  list-style: none;
  padding: 0;
  margin: 0;
  font-size: 13px;
  color:#475569;
  line-height: 1.5;
  display:flex;
  flex-direction:column;
  gap: 10px;
}

#wpil-out-of-credits-modal .lwoc-hero-list li{
    white-space: nowrap;
}

#wpil-out-of-credits-modal .lwoc-dotmini{
  display:inline-block;
  width: 8px;
  height: 8px;
  border-radius: 999px;
  margin-right: 10px;
  background: linear-gradient(90deg, #7F5AF0 0%, #2C6BFF 100%);
  box-shadow: 0 0 0 4px rgba(127,90,240,.10);
}
</style>
<script>
    jQuery(function($){
  var $oc = $('#wpil-out-of-credits-modal');

  function wpilFormatInt(n){
    try { return Number(n || 0).toLocaleString(); } catch(e){ return String(n || 0); }
  }

  function padShortfall(shortfall){
    var padded = Math.ceil(shortfall * 1.10);
    padded = Math.round(padded / 100) * 100;
    if(padded < shortfall) padded += 100;
    return padded;
  }

  function openOutOfCreditsModal(opts){
    opts = opts || {};
    if($oc.is(':visible') || $('#lw-credit-checkout-modal').is(':visible')){
      return;
    }

    var needed = Number(opts.needed || 0);
    var available = Number(opts.available || 0);

    var shortfall = Math.max(0, needed - available);
    var padded = padShortfall(shortfall);

    // Fill text
    $oc.find('[data-role="lwoc-needed"]').text(wpilFormatInt(needed));
    $oc.find('[data-role="lwoc-available"]').text(wpilFormatInt(available));
    $oc.find('[data-role="lwoc-shortfall"]').text(wpilFormatInt(padded));

    // Bar = available/needed capped at 100
    var pct = (needed > 0) ? Math.min(100, Math.round((available / needed) * 100)) : 0;
    $oc.find('[data-role="lwoc-bar"]').css('width', pct + '%');

    // stash what we want to buy
    $oc.data('lwoc-credits', padded);

    $('body').addClass('lwoc-open');
    $oc.show().attr('aria-hidden','false');
  }

  function closeOutOfCreditsModal(){
    $('body').removeClass('lwoc-open');
    $oc.hide().attr('aria-hidden','true');
  }

  // close handlers
  $oc.on('click', '[data-lwoc-close]', function(){
    closeOutOfCreditsModal();
  });

  // Buy -> open existing checkout modal + sync credits on its buy button
  $oc.on('click', '[data-role="lwoc-buy"]', function(){
    var credits = Number($oc.data('lwoc-credits') || 0);

    // Keep your existing buy button data in sync (so your lwcc JS picks it up)
    $('.lw-credits-buy')
      .attr('data-credits', credits)
      .attr('data-quantity', credits);

    closeOutOfCreditsModal();

    // Trigger whatever you already use to open lwcc.
    // If you have a function/event, use it. Otherwise, simulate click:
    $('.lw-credits-buy').first().trigger('click');
  });

  // Expose a global trigger so your scan code can pop it
  window.wpilOpenOutOfCredits = openOutOfCreditsModal;

  /*
    Example usage wherever you detect insufficient credits:

    wpilOpenOutOfCredits({
      needed: needed_credits,
      available: available_credits
    });
  */
});

</script>
<!-- OUT OF CREDITS MODAL -->
<div id="wpil-out-of-credits-modal" class="lwoc-modal" aria-hidden="true" style="display:none;">
  <div class="lwoc-backdrop" data-lwoc-close="1"></div>

  <div class="lwoc-panel" role="dialog" aria-modal="true" aria-label="Out of AI Credits">
    <div class="lwoc-left">
      <div class="lwoc-icon">
        <!-- warning icon -->
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
        </svg>
      </div>

      <h2 class="lwoc-title">Scan Paused</h2>
      <p class="lwoc-subtitle">
        You’re out of AI credits. Your progress is saved. Add credits to resume AI linking.
      </p>

      <div class="lwoc-metrics">
        <div class="lwoc-row">
          <span>Credits needed</span>
          <strong data-role="lwoc-needed">—</strong>
        </div>
        <div class="lwoc-row">
          <span>Your balance</span>
          <strong class="lwoc-bad" data-role="lwoc-available">—</strong>
        </div>
        <div class="lwoc-row">
          <span>Shortfall</span>
          <strong data-role="lwoc-shortfall">—</strong>
        </div>

        <div class="lwoc-bar">
          <div class="lwoc-bar-fill" data-role="lwoc-bar" style="width:0%"></div>

          <!-- little zazz ping dot -->
          <div class="lwoc-dot" aria-hidden="true">
            <span class="lwoc-dot-ping"></span>
            <span class="lwoc-dot-core"></span>
          </div>
        </div>

        <div class="lwoc-hint">Refill required to continue</div>
      </div>

      <div class="lwoc-note">
        <div class="lwoc-note-title">Why AI helps</div>
        <div class="lwoc-note-body">
          AI finds natural, context-aware internal links using your content plus GSC signals,
          saving hours of manual review.
        </div>
      </div>

      <div class="lwoc-foot">
        Secure payment via Stripe · Progress stays saved
      </div>
    </div>

    <div class="lwoc-right">
      <div class="lwoc-right-head">
        <div><!--lol-spacer--></div>
        <div>
          <div class="lwoc-right-title">Add credits to keep going</div>
          <div class="lwoc-right-sub">We’ll open the secure checkout for you.</div>
        </div>
        <button type="button" class="lwoc-close" data-lwoc-close="1" aria-label="Close">×</button>
      </div>
    <div class="lwoc-hero">
        <div class="lwoc-hero-graphic" aria-hidden="true">
            <!-- Minimal AI-linking illustration (inline SVG) -->
            <svg viewBox="0 0 520 240" width="100%" height="100%" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <linearGradient id="lwocG" x1="0" y1="0" x2="1" y2="0">
                <stop offset="0" stop-color="#7F5AF0"/>
                <stop offset="1" stop-color="#2C6BFF"/>
                </linearGradient>
                <filter id="lwocSoft" x="-30%" y="-30%" width="160%" height="160%">
                <feGaussianBlur stdDeviation="10" result="b"/>
                <feColorMatrix in="b" type="matrix"
                    values="1 0 0 0 0
                            0 1 0 0 0
                            0 0 1 0 0
                            0 0 0 .20 0" />
                <feBlend in="SourceGraphic" mode="normal"/>
                </filter>
            </defs>

            <!-- soft blobs -->
            <circle cx="120" cy="90" r="78" fill="url(#lwocG)" opacity=".10"/>
            <circle cx="410" cy="70" r="62" fill="url(#lwocG)" opacity=".08"/>
            <circle cx="390" cy="170" r="90" fill="url(#lwocG)" opacity=".06"/>

            <!-- connecting path -->
            <path d="M120 140 C 190 90, 240 160, 300 120 S 410 90, 450 130"
                    fill="none" stroke="url(#lwocG)" stroke-width="6" stroke-linecap="round" opacity=".9" filter="url(#lwocSoft)"/>

            <!-- nodes -->
            <g>
                <circle cx="120" cy="140" r="14" fill="#ffffff" stroke="url(#lwocG)" stroke-width="4"/>
                <circle cx="300" cy="120" r="14" fill="#ffffff" stroke="url(#lwocG)" stroke-width="4"/>
                <circle cx="450" cy="130" r="14" fill="#ffffff" stroke="url(#lwocG)" stroke-width="4"/>
            </g>

            <!-- subtle “AI spark” -->
            <g opacity=".9">
                <path d="M260 58 l8 14 14 8-14 8-8 14-8-14-14-8 14-8z" fill="url(#lwocG)"/>
            </g>

            <!-- tiny dashed hints -->
            <path d="M120 140 L300 120" stroke="#94a3b8" stroke-width="2" stroke-dasharray="5 7" opacity=".35"/>
            <path d="M300 120 L450 130" stroke="#94a3b8" stroke-width="2" stroke-dasharray="5 7" opacity=".35"/>
            </svg>
        </div>

        <div class="lwoc-hero-copy">
            <div class="lwoc-hero-kicker">What credits power</div>
            <ul class="lwoc-hero-list">
            <li><span class="lwoc-dotmini"></span>Context-aware internal linking</li>
            <li><span class="lwoc-dotmini"></span>High-value page identification</li>
            <li><span class="lwoc-dotmini"></span>Skipping hours of manual linking!</li>
            </ul>
        </div>
    </div>



      <div class="lwoc-actions">
        <button type="button" class="lw-gradient-bg lwoc-buy" data-role="lwoc-buy">
          Buy Credits & Resume
        </button>

        <button type="button" class="lwoc-nope" data-lwoc-close="1">
          No thanks (continue without AI)
        </button>
      </div>

      <div class="lwoc-small" style="display: none;">
        Tip: we recommend purchasing a little extra as a buffer.
      </div>
    </div>
  </div>
</div>
