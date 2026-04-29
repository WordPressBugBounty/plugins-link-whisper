<style>
  #wpil-fix-modal.wpil-modal {
    position: fixed;
    inset: 0;
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
  }

  #wpil-fix-modal.hidden {
    display: none;
  }

  #wpil-fix-modal .wpil-modal-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(17, 24, 39, 0.55);
    backdrop-filter: blur(2px);
  }

  #wpil-fix-modal .wpil-modal-panel {
    position: relative;
    z-index: 1;
    width: min(700px, 100%);
    max-height: calc(100vh - 40px);
    overflow: auto;
    background: var(--white, #fff);
    border-radius: 16px;
    box-shadow: 0 16px 50px rgba(15, 23, 42, 0.25);
    padding: 22px 26px;
    animation: wpil-fix-modal-pop 160ms ease-out;
  }

  #wpil-fix-modal .wpil-modal-panel h3 {
    margin: 0 0 6px;
    font-size: 20px;
    font-weight: 800;
    color: var(--gray-900, #0f172a);
  }

  #wpil-fix-modal .wpil-modal-panel p {
    margin: 0 0 16px;
    color: var(--gray-600, #64748b);
  }

  #wpil-fix-modal .wpil-fix-head {
    display: block;
    margin-bottom: 16px;
  }

  #wpil-fix-modal .wpil-fix-kicker {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: #64748b;
    margin-bottom: 6px;
  }

  #wpil-fix-modal .wpil-fix-close {
    border: 1px solid #e2e8f0 !important;
    background: #fff !important;
    color: #64748b !important;
    width: 36px;
    height: 36px;
    border-radius: 10px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    line-height: 1;
    padding: 0;
    flex: 0 0 36px;
    position: absolute;
    top: 18px;
    right: 18px;
  }

  #wpil-fix-modal .wpil-fix-close:hover {
    color: #0f172a !important;
    background: #f8fafc !important;
  }

  #wpil-fix-modal .wpil-fix-credit-card {
    background: var(--gray-50, #f8fafc);
    border: 1px solid var(--gray-200, #e5e7eb);
    border-radius: 14px;
    padding: 16px;
    margin-bottom: 16px;
  }

  #wpil-fix-modal .wpil-fix-credit-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
  }

  #wpil-fix-modal .wpil-fix-credit-label {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--gray-500, #94a3b8);
  }

  #wpil-fix-modal .wpil-fix-credit-badge {
    font-size: 11px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 999px;
    background: #dcfce7;
    color: #166534;
  }

  #wpil-fix-modal .wpil-fix-credit-badge.is-bad {
    background: #fee2e2;
    color: #b91c1c;
  }

  #wpil-fix-modal .wpil-fix-credit-stats {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 16px;
  }

  #wpil-fix-modal .wpil-fix-credit-available {
    display: flex;
    align-items: baseline;
    gap: 10px;
  }

  #wpil-fix-modal .wpil-fix-credit-number {
    font-size: 28px;
    font-weight: 800;
    color: var(--gray-800, #1f2937);
  }

  #wpil-fix-modal .wpil-fix-credit-sub {
    font-size: 12px;
    color: var(--gray-500, #94a3b8);
  }

  #wpil-fix-modal .wpil-fix-credit-required {
    text-align: right;
  }

  #wpil-fix-modal .wpil-fix-credit-required .wpil-fix-credit-number {
    font-size: 18px;
  }

  #wpil-fix-modal .wpil-fix-credit-bar {
    margin-top: 10px;
    height: 6px;
    background: var(--gray-200, #e5e7eb);
    border-radius: 999px;
    overflow: hidden;
  }

  #wpil-fix-modal .wpil-fix-credit-bar-fill {
    height: 100%;
    width: 0%;
    background: linear-gradient(90deg, #7f5af0 0%, #2c6bff 100%);
    transition: width 0.2s ease-out;
  }

  #wpil-fix-modal .wpil-fix-credit-foot {
    margin-top: 8px;
    font-size: 12px;
    color: var(--gray-500, #94a3b8);
  }

  #wpil-fix-modal .wpil-fix-warning {
    margin-top: 12px;
    padding: 10px 12px;
    background: #fff1f2;
    border: 1px solid #fecdd3;
    color: #b91c1c;
    border-radius: 10px;
    font-size: 13px;
  }

  #wpil-fix-modal .wpil-fix-warning strong {
    font-weight: 800;
  }

  #wpil-fix-modal .wpil-fix-special-options {
    background: var(--gray-50, #f8fafc);
    border: 1px solid var(--gray-200, #e5e7eb);
    border-radius: 14px;
    padding: 14px 16px;
    margin: 0 0 16px;
  }

  #wpil-fix-modal .wpil-fix-special-options.is-hidden {
    display: none;
  }

  #wpil-fix-modal .wpil-fix-special-options-title {
    margin: 0 0 10px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--gray-500, #94a3b8);
  }

  #wpil-fix-modal .wpil-fix-special-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    margin-bottom: 10px;
    border-radius: 10px;
    padding: 6px 8px;
    transition: background-color .15s ease, box-shadow .15s ease;
    cursor: pointer;
  }

  #wpil-fix-modal .wpil-fix-special-row:hover {
    background: #eef2ff;
    box-shadow: inset 0 0 0 1px #dbeafe;
  }

  #wpil-fix-modal .wpil-fix-special-row:focus-within {
    background: #eff6ff;
    box-shadow: inset 0 0 0 2px #93c5fd;
  }

  #wpil-fix-modal .wpil-fix-special-row:last-child {
    margin-bottom: 0;
  }

  #wpil-fix-modal .wpil-fix-special-label {
    font-size: 13px;
    font-weight: 600;
    color: #334155;
  }

  #wpil-fix-modal .wpil-fix-special-toggle {
    width: 42px;
    height: 24px;
    border-radius: 999px;
    border: 1px solid #cbd5e1;
    background: #e2e8f0;
    appearance: none;
    position: relative;
    cursor: pointer;
    transition: background .18s ease;
  }

  #wpil-fix-modal .wpil-fix-special-toggle:hover {
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
  }

  #wpil-fix-modal .wpil-fix-special-toggle:focus-visible {
    outline: 0;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.28);
  }

  #wpil-fix-modal .wpil-fix-special-toggle:checked {
    background: #2563eb;
    border-color: #2563eb;
  }

  #wpil-fix-modal .wpil-fix-special-toggle:before {
    content: '';
    position: absolute;
    top: 2px;
    left: 2px;
    width: 18px;
    height: 18px;
    border-radius: 999px;
    background: #fff;
    transition: transform .18s ease;
  }

  #wpil-fix-modal .wpil-fix-special-toggle:checked:before {
    transform: translateX(18px);
    top: 5px;
    left: 6px;
  }

  #wpil-fix-modal .wpil-fix-special-post-type-wrap {
    display: none;
    margin-top: 6px;
  }

  #wpil-fix-modal .wpil-fix-special-post-type-wrap.is-active {
    display: block;
  }

  #wpil-fix-modal .wpil-fix-special-post-type-wrap select {
    width: 100%;
    min-height: 92px;
  }

  #wpil-fix-modal .wpil-fix-special-post-type-wrap .select2-container {
    width: 100% !important;
  }

  #wpil-fix-modal .wpil-fix-special-post-type-wrap .select2-selection--multiple {
    border: 1px solid #cbd5e1 !important;
    border-radius: 8px !important;
    min-height: 38px !important;
    padding: 2px 4px !important;
  }

  #wpil-fix-modal .wpil-fix-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 8px;
    padding-top: 10px;
    border-top: 1px solid var(--gray-200, #e5e7eb);
  }

  #wpil-fix-modal .wpil-fix-actions.hidden {
    display: none;
  }

  #wpil-fix-modal .wpil-fix-primary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 36px;
    background: linear-gradient(90deg, #7f5af0 0%, #2c6bff 100%);
    color: #fff;
    border: 0;
    padding: 8px 14px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    transition: opacity .15s ease, box-shadow .15s ease, transform .08s ease;
  }

  #wpil-fix-modal .wpil-fix-primary:hover {
    opacity: .95;
    box-shadow: 0 8px 18px rgba(44, 107, 255, 0.24);
  }

  #wpil-fix-modal .wpil-fix-primary:active {
    transform: translateY(1px);
  }

  #wpil-fix-modal .wpil-fix-primary:focus-visible {
    outline: 0;
    box-shadow: 0 0 0 3px rgba(44, 107, 255, 0.25);
  }

  #wpil-fix-modal .wpil-fix-secondary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 36px;
    background: #e5e7eb;
    color: #374151;
    border: 1px solid #d1d5db;
    padding: 8px 14px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    transition: background-color .15s ease, color .15s ease, box-shadow .15s ease;
  }

  #wpil-fix-modal .wpil-fix-secondary:hover {
    background: #d1d5db;
    color: #1f2937;
  }

  #wpil-fix-modal .wpil-fix-secondary:focus-visible {
    outline: 0;
    box-shadow: 0 0 0 3px rgba(148, 163, 184, 0.35);
  }

  #wpil-fix-modal .lw-gradient-bg {
    background: linear-gradient(90deg, #7f5af0 0%, #2c6bff 100%);
    color: #fff;
    border: 0;
    padding: 8px 14px;
    border-radius: 8px;
    font-weight: 700;
    min-height: 36px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: opacity .15s ease, box-shadow .15s ease, transform .08s ease;
  }

  #wpil-fix-modal .lw-gradient-bg:hover {
    opacity: .95;
    box-shadow: 0 8px 18px rgba(44, 107, 255, 0.24);
  }

  #wpil-fix-modal .lw-gradient-bg:active {
    transform: translateY(1px);
  }

  #wpil-fix-modal .wpil-fix-summary {
    background: var(--gray-50, #f8fafc);
    border: 1px solid var(--gray-200, #e5e7eb);
    border-radius: 14px;
    padding: 14px 16px 12px;
    margin: 0 0 16px;
    width: 100%;
    box-sizing: border-box;
  }

  #wpil-fix-modal .wpil-fix-summary-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
  }

  #wpil-fix-modal .wpil-fix-summary-pill {
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--gray-500, #94a3b8);
    opacity: 0.95;
  }

  #wpil-fix-modal .wpil-fix-summary-value {
    font-size: 12px;
    font-weight: 800;
    color: var(--gray-800, #1f2937);
    background: var(--white, #fff);
    border: 1px solid var(--gray-200, #e5e7eb);
    border-radius: 999px;
    padding: 4px 10px;
    line-height: 1;
    white-space: nowrap;
  }

  #wpil-fix-modal .wpil-fix-bullets {
    margin: 0;
    padding-left: 18px;
    color: var(--gray-700, #334155);
    font-size: 13px;
    line-height: 1.5;
    list-style: disc;
  }

  #wpil-fix-modal .wpil-fix-bullets li + li {
    margin-top: 6px;
  }

  #wpil-fix-modal .wpil-fix-note {
    margin-top: 10px;
    font-size: 12px;
    color: var(--gray-600, #64748b);
  }

  #wpil-fix-modal .wpil-fix-bullets li {
    margin: 0;
    padding: 0;
  }

  body.wpil-fix-modal-open {
    overflow: hidden;
  }

  @media (max-width: 680px) {
    #wpil-fix-modal.wpil-modal {
      padding: 12px;
    }

    #wpil-fix-modal .wpil-modal-panel {
      padding: 16px;
      border-radius: 12px;
      max-height: calc(100vh - 24px);
    }

    #wpil-fix-modal .wpil-fix-head {
      margin-bottom: 12px;
      padding-right: 44px;
    }

    #wpil-fix-modal .wpil-modal-panel h3 {
      font-size: 18px;
    }

    #wpil-fix-modal .wpil-fix-credit-stats {
      flex-direction: column;
      align-items: flex-start;
      gap: 8px;
    }

    #wpil-fix-modal .wpil-fix-credit-required {
      text-align: left;
    }

    #wpil-fix-modal .wpil-fix-actions {
      flex-direction: column-reverse;
      align-items: stretch;
    }

    #wpil-fix-modal .wpil-fix-actions button {
      width: 100%;
    }
  }

  @keyframes wpil-fix-modal-pop {
    from {
      transform: translateY(6px) scale(0.985);
      opacity: 0;
    }
    to {
      transform: translateY(0) scale(1);
      opacity: 1;
    }
  }
</style>

<div id="wpil-fix-modal" class="wpil-modal hidden" aria-hidden="true">
  <div class="wpil-modal-backdrop" data-wpil-fix-cancel></div>

  <div class="wpil-modal-panel" role="dialog" aria-modal="true" aria-label="AI Fix Modal">
    <div class="wpil-fix-head">
      <div>
        <div class="wpil-fix-kicker">AI Fix</div>
        <h3>Link Whisper can fix this for you</h3>
        <p id="wpil-fix-description"></p>
        <div class="wpil-fix-summary" aria-label="Fix summary">
            <div class="wpil-fix-summary-row">
                <span class="wpil-fix-summary-pill" data-wpil-fix-meta-label>Items</span>
                <span style="display:none" class="wpil-fix-summary-value" data-wpil-fix-meta-value>0</span>
            </div>
            <ul class="wpil-fix-bullets" data-wpil-fix-bullets></ul>
            <div class="wpil-fix-note" data-wpil-fix-note></div>
        </div>
      </div>
      <button class="wpil-fix-close" type="button" aria-label="Close" data-wpil-fix-cancel>&times;</button>
    </div>

    <div class="wpil-fix-credit-card">
      <div class="wpil-fix-credit-head">
        <span class="wpil-fix-credit-label">AI Credits</span>
        <span class="wpil-fix-credit-badge" data-role="wpil-fix-status">Ready</span>
      </div>

      <div class="wpil-fix-credit-stats">
        <div class="wpil-fix-credit-available">
          <div class="wpil-fix-credit-number" data-wpil-fix-balance>0</div>
          <div class="wpil-fix-credit-sub">available</div>
        </div>

        <div class="wpil-fix-credit-required">
          <div class="wpil-fix-credit-sub">Required</div>
          <div class="wpil-fix-credit-number" data-wpil-fix-estimate>0</div>
        </div>
      </div>

      <div class="wpil-fix-credit-bar">
        <div class="wpil-fix-credit-bar-fill" data-role="wpil-fix-bar"></div>
      </div>

      <div class="wpil-fix-credit-foot">
        <span data-wpil-fix-estimate>0</span> credits required for this fix
      </div>

      <div id="wpil-fix-warning" class="wpil-fix-warning hidden">
        You need <strong><span data-wpil-fix-shortfall>0</span></strong> more credits to run this fix.
      </div>
    </div>

    <div class="wpil-fix-special-options is-hidden" data-wpil-fix-special-options>
      <div class="wpil-fix-special-options-title">Special Options</div>
      <div class="wpil-fix-special-row">
        <label class="wpil-fix-special-label" for="wpil-fix-opt-link-to-category-pages">Only Link To Category Pages</label>
        <input type="checkbox" id="wpil-fix-opt-link-to-category-pages" class="wpil-fix-special-toggle" data-wpil-fix-option="link_to_category_pages">
      </div>
      <div class="wpil-fix-special-row">
        <label class="wpil-fix-special-label" for="wpil-fix-opt-link-from-category-pages">Only Link From Category Pages</label>
        <input type="checkbox" id="wpil-fix-opt-link-from-category-pages" class="wpil-fix-special-toggle" data-wpil-fix-option="link_from_category_pages">
      </div>
      <div class="wpil-fix-special-row">
        <label class="wpil-fix-special-label" for="wpil-fix-opt-select-post-types">Select Linking Post Types</label>
        <input type="checkbox" id="wpil-fix-opt-select-post-types" class="wpil-fix-special-toggle" data-wpil-fix-option="select_post_types">
      </div>
      <div class="wpil-fix-special-post-type-wrap" data-wpil-fix-post-type-wrap>
        <?php $wpil_fix_post_types = Wpil_Settings::getPostTypeLabels(Wpil_Settings::getPostTypes()); ?>
        <select multiple class="wpil-suggestion-multiselect" data-wpil-fix-option="selected_post_types">
          <?php foreach($wpil_fix_post_types as $post_type => $label){ ?>
            <option value="<?php echo esc_attr($post_type); ?>"><?php echo esc_html(ucfirst($label)); ?></option>
          <?php } ?>
        </select>
      </div>
      <div class="wpil-fix-special-row" style="margin-top: 10px;">
        <label class="wpil-fix-special-label" for="wpil-fix-opt-same-category">Only Link Between Posts With Same Categories</label>
        <input type="checkbox" id="wpil-fix-opt-same-category" class="wpil-fix-special-toggle" data-wpil-fix-option="same_category">
      </div>
    </div>

    <div id="wpil-fix-actions-enough" class="wpil-fix-actions">
      <button class="wpil-fix-secondary" type="button" data-wpil-fix-cancel>Not Now</button>
      <button class="wpil-fix-primary" id="wpil-fix-begin" type="button">Fix With AI</button>
    </div>

    <div id="wpil-fix-actions-short" class="wpil-fix-actions hidden">
      <button class="wpil-fix-secondary" type="button" data-wpil-fix-cancel>Close</button>
      <button class="lw-credits-buy lw-gradient-bg" id="wpil-fix-buy" type="button" data-type="custom">
        Add Credits
      </button>
    </div>
  </div>
</div>
