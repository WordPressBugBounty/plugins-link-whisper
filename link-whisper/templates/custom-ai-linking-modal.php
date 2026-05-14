<?php
if (!defined('ABSPATH')) { exit; }

$wpil_custom_link_map_manage_url = Wpil_CsvLinkMap::get_manage_url();
?>
<style>
  #wpil-custom-linking-modal.wpil-modal {
    position: fixed;
    inset: 0;
    z-index: 100001;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
  }

  #wpil-custom-linking-modal.hidden {
    display: none;
  }

  #wpil-custom-linking-modal .wpil-modal-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(17, 24, 39, 0.55);
    backdrop-filter: blur(2px);
  }

  #wpil-custom-linking-modal .wpil-modal-panel {
    position: relative;
    z-index: 1;
    width: min(760px, 100%);
    max-height: calc(100vh - 40px);
    overflow: auto;
    background: var(--white, #fff);
    border-radius: 16px;
    box-shadow: 0 16px 50px rgba(15, 23, 42, 0.25);
    padding: 22px 26px;
    animation: wpil-fix-modal-pop 160ms ease-out;
  }

  #wpil-custom-linking-modal .wpil-fix-close {
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
    position: absolute;
    top: 18px;
    right: 18px;
  }

  #wpil-custom-linking-modal .wpil-fix-close:hover {
    color: #0f172a !important;
    background: #f8fafc !important;
  }

  #wpil-custom-linking-modal .wpil-custom-head {
    margin-bottom: 16px;
    padding-right: 44px;
  }

  #wpil-custom-linking-modal .wpil-fix-kicker {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: #64748b;
    margin-bottom: 6px;
  }

  #wpil-custom-linking-modal h3 {
    margin: 0 0 6px;
    font-size: 20px;
    font-weight: 800;
    color: var(--gray-900, #0f172a);
  }

  #wpil-custom-linking-modal p {
    margin: 0;
    color: var(--gray-600, #64748b);
  }

  #wpil-custom-linking-modal .wpil-custom-stack {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  #wpil-custom-linking-modal .wpil-custom-card,
  #wpil-custom-linking-modal .wpil-fix-credit-card {
    background: var(--gray-50, #f8fafc);
    border: 1px solid var(--gray-200, #e5e7eb);
    border-radius: 14px;
    padding: 16px;
  }

  #wpil-custom-linking-modal .wpil-custom-card-title,
  #wpil-custom-linking-modal .wpil-fix-credit-label {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--gray-500, #94a3b8);
    margin-bottom: 10px;
  }

  #wpil-custom-linking-modal .wpil-custom-card-title-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 10px;
  }

  #wpil-custom-linking-modal .wpil-custom-card-title-row .wpil-custom-card-title {
    margin-bottom: 0;
  }

  #wpil-custom-linking-modal .wpil-custom-link {
    color: #2563eb;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
  }

  #wpil-custom-linking-modal .wpil-custom-link:hover {
    text-decoration: underline;
  }

  #wpil-custom-linking-modal .wpil-custom-template-actions,
  #wpil-custom-linking-modal .wpil-custom-upload-row {
    display: flex;
    flex-direction: column;
    align-items: baseline;
    gap: 12px;
    flex-wrap: wrap;
  }

  #wpil-custom-linking-modal .wpil-custom-file-label {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-height: 40px;
    padding: 0 14px;
    border-radius: 10px;
    border: 1px dashed #94a3b8;
    background: #fff;
    color: #334155;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
  }

  #wpil-custom-linking-modal .wpil-custom-file-label:hover {
    border-color: #2563eb;
    color: #1d4ed8;
  }

  #wpil-custom-linking-modal .wpil-custom-file-input {
    display: none;
  }

  #wpil-custom-linking-modal .wpil-custom-file-name {
    font-size: 12px;
    color: #64748b;
  }

  #wpil-custom-linking-modal .wpil-custom-feedback {
    display: none;
    padding: 10px 12px;
    border-radius: 10px;
    font-size: 13px;
    line-height: 1.45;
  }

  #wpil-custom-linking-modal .wpil-custom-feedback.is-success {
    display: block;
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    color: #166534;
  }

  #wpil-custom-linking-modal .wpil-custom-feedback.is-error {
    display: block;
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #b91c1c;
  }

  #wpil-custom-linking-modal .wpil-custom-progress {
    display: none;
    margin-top: 12px;
    padding: 12px;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 10px;
  }

  #wpil-custom-linking-modal .wpil-custom-progress.is-visible {
    display: block;
  }

  #wpil-custom-linking-modal .wpil-custom-progress-label {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 8px;
    font-size: 12px;
    color: #1d4ed8;
    font-weight: 700;
  }

  #wpil-custom-linking-modal .wpil-custom-progress-copy {
    font-size: 13px;
    color: #1e3a8a;
    line-height: 1.45;
  }

  #wpil-custom-linking-modal .wpil-custom-progress-bar {
    height: 8px;
    border-radius: 999px;
    background: rgba(37, 99, 235, 0.15);
    overflow: hidden;
    margin-bottom: 8px;
  }

  #wpil-custom-linking-modal .wpil-custom-progress-fill {
    width: 0%;
    height: 100%;
    background: linear-gradient(90deg, #2563eb 0%, #60a5fa 100%);
    transition: width 0.2s ease-out;
  }

  #wpil-custom-linking-modal .wpil-custom-summary-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
  }

  #wpil-custom-linking-modal .wpil-custom-stat {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 12px;
  }

  #wpil-custom-linking-modal .wpil-custom-stat strong {
    display: block;
    font-size: 20px;
    color: #0f172a;
    margin-bottom: 4px;
  }

  #wpil-custom-linking-modal .wpil-custom-stat-title {
    display: block;
    font-size: 12px;
    font-weight: 700;
    color: #334155;
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.06em;
  }

  #wpil-custom-linking-modal .wpil-custom-stat span {
    display: block;
    font-size: 12px;
    color: #64748b;
    line-height: 1.45;
  }

  #wpil-custom-linking-modal .wpil-custom-empty-state {
    font-size: 13px;
    color: #64748b;
    line-height: 1.5;
  }

  #wpil-custom-linking-modal .wpil-custom-warnings {
    background: #fffbeb;
    border-color: #fde68a;
  }

  #wpil-custom-linking-modal .wpil-custom-warning-list {
    margin: 0;
    padding-left: 18px;
    color: #92400e;
    font-size: 13px;
    line-height: 1.5;
  }

  #wpil-custom-linking-modal .wpil-custom-warning-list li + li {
    margin-top: 6px;
  }

  #wpil-custom-linking-modal .wpil-custom-link-mode {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
  }

  #wpil-custom-linking-modal .wpil-custom-mode-option {
    display: flex;
    cursor: pointer;
  }

  #wpil-custom-linking-modal .wpil-custom-mode-option input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
  }

  #wpil-custom-linking-modal .wpil-custom-mode-card {
    background: #fff;
    border: 1px solid #d1d5db;
    border-radius: 12px;
    padding: 14px;
    min-height: 100%;
    transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.08s ease;
  }

  #wpil-custom-linking-modal .wpil-custom-mode-option:hover .wpil-custom-mode-card {
    border-color: #93c5fd;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.08);
  }

  #wpil-custom-linking-modal .wpil-custom-mode-option input:checked + .wpil-custom-mode-card {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.14);
  }

  #wpil-custom-linking-modal .wpil-custom-mode-title {
    display: block;
    font-size: 14px;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 4px;
  }

  #wpil-custom-linking-modal .wpil-custom-mode-copy {
    display: block;
    font-size: 12px;
    color: #64748b;
    line-height: 1.5;
  }

  #wpil-custom-linking-modal .wpil-fix-credit-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
  }

  #wpil-custom-linking-modal .wpil-fix-credit-badge {
    font-size: 11px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 999px;
    background: #dcfce7;
    color: #166534;
  }

  #wpil-custom-linking-modal .wpil-fix-credit-badge.is-bad {
    background: #fee2e2;
    color: #b91c1c;
  }

  #wpil-custom-linking-modal .wpil-fix-credit-badge.is-muted {
    background: #e5e7eb;
    color: #475569;
  }

  #wpil-custom-linking-modal .wpil-fix-credit-stats {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 16px;
  }

  #wpil-custom-linking-modal .wpil-fix-credit-number {
    font-size: 28px;
    font-weight: 800;
    color: var(--gray-800, #1f2937);
  }

  #wpil-custom-linking-modal .wpil-fix-credit-sub {
    font-size: 12px;
    color: var(--gray-500, #94a3b8);
  }

  #wpil-custom-linking-modal .wpil-fix-credit-required {
    text-align: right;
  }

  #wpil-custom-linking-modal .wpil-fix-credit-required .wpil-fix-credit-number {
    font-size: 18px;
  }

  #wpil-custom-linking-modal .wpil-fix-credit-bar {
    margin-top: 10px;
    height: 6px;
    background: var(--gray-200, #e5e7eb);
    border-radius: 999px;
    overflow: hidden;
  }

  #wpil-custom-linking-modal .wpil-fix-credit-bar-fill {
    height: 100%;
    width: 0%;
    background: linear-gradient(90deg, #7f5af0 0%, #2c6bff 100%);
    transition: width 0.2s ease-out;
  }

  #wpil-custom-linking-modal .wpil-fix-credit-foot {
    margin-top: 8px;
    font-size: 12px;
    color: var(--gray-500, #94a3b8);
  }

  #wpil-custom-linking-modal .wpil-custom-credit-warning {
    display: none;
    margin-top: 12px;
    padding: 10px 12px;
    background: #fff1f2;
    border: 1px solid #fecdd3;
    color: #b91c1c;
    border-radius: 10px;
    font-size: 13px;
  }

  #wpil-custom-linking-modal .wpil-custom-credit-warning.is-visible {
    display: block;
  }

  #wpil-custom-linking-modal .wpil-fix-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 8px;
    padding-top: 10px;
    border-top: 1px solid var(--gray-200, #e5e7eb);
  }

  #wpil-custom-linking-modal .wpil-fix-primary,
  #wpil-custom-linking-modal .wpil-fix-secondary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 36px;
    padding: 8px 14px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    transition: opacity .15s ease, box-shadow .15s ease, transform .08s ease, background-color .15s ease, color .15s ease;
  }

  #wpil-custom-linking-modal .wpil-fix-primary {
    background: linear-gradient(90deg, #7f5af0 0%, #2c6bff 100%);
    color: #fff;
    border: 0;
  }

  #wpil-custom-linking-modal .wpil-fix-primary:hover {
    opacity: .95;
    box-shadow: 0 8px 18px rgba(44, 107, 255, 0.24);
  }

  #wpil-custom-linking-modal .wpil-fix-secondary {
    background: #e5e7eb;
    color: #374151;
    border: 1px solid #d1d5db;
  }

  #wpil-custom-linking-modal .wpil-fix-secondary:hover {
    background: #d1d5db;
    color: #1f2937;
  }

  #wpil-custom-linking-modal button[disabled] {
    cursor: not-allowed;
    opacity: 0.6;
    box-shadow: none !important;
  }

  #wpil-custom-linking-modal .hidden {
    display: none !important;
  }

  @media (max-width: 680px) {
    #wpil-custom-linking-modal.wpil-modal {
      padding: 12px;
    }

    #wpil-custom-linking-modal .wpil-modal-panel {
      padding: 16px;
      border-radius: 12px;
      max-height: calc(100vh - 24px);
    }

    #wpil-custom-linking-modal .wpil-custom-summary-grid,
    #wpil-custom-linking-modal .wpil-custom-link-mode {
      grid-template-columns: 1fr;
    }

    #wpil-custom-linking-modal .wpil-fix-credit-stats,
    #wpil-custom-linking-modal .wpil-fix-actions {
      flex-direction: column;
      align-items: stretch;
    }

    #wpil-custom-linking-modal .wpil-fix-credit-required {
      text-align: left;
    }

    #wpil-custom-linking-modal .wpil-fix-actions button,
    #wpil-custom-linking-modal .wpil-custom-template-actions a,
    #wpil-custom-linking-modal .wpil-custom-template-actions button,
    #wpil-custom-linking-modal .wpil-custom-upload-row button,
    #wpil-custom-linking-modal .wpil-custom-file-label {
      width: 100%;
    }
  }
</style>

<div id="wpil-custom-linking-modal" class="wpil-modal hidden" aria-hidden="true">
  <div class="wpil-modal-backdrop" data-wpil-custom-linking-close></div>

  <div class="wpil-modal-panel" role="dialog" aria-modal="true" aria-label="Custom AI Linking">
    <button class="wpil-fix-close" type="button" aria-label="Close" data-wpil-custom-linking-close>&times;</button>

    <div class="wpil-custom-head">
      <div class="wpil-fix-kicker">AI Fix</div>
      <h3>Custom AI Linking</h3>
      <p>Download the CSV template, map the relationships you want, upload it here, and then launch the AI runner from the dashboard.</p>
    </div>

    <div class="wpil-custom-stack">
      <div class="wpil-custom-card">
        <div class="wpil-custom-card-title-row">
          <div class="wpil-custom-card-title">Template</div>
          <a class="wpil-custom-link" style="display:none;" href="<?php echo esc_url($wpil_custom_link_map_manage_url); ?>" data-wpil-custom-linking-manage-page><?php esc_html_e('Manage on full page', 'wpil'); ?></a>
        </div>
        <p style="margin-bottom: 12px;">Use the template file as a guide for creating your linking plan.</p>
        <div class="wpil-custom-template-actions">
          <button type="button" class="wpil-fix-secondary" data-wpil-custom-linking-download><?php esc_html_e('Download Example Template', 'wpil'); ?></button>
        </div>
      </div>

      <div class="wpil-custom-card">
        <div class="wpil-custom-card-title">Upload CSV</div>
        <div class="wpil-custom-feedback" data-wpil-custom-linking-feedback></div>
        <form data-wpil-custom-linking-upload-form>
          <div class="wpil-custom-upload-row">
            <div>
                <label class="wpil-custom-file-label" for="wpil-custom-linking-file-input">
                <span class="dashicons dashicons-upload" aria-hidden="true"></span>
                <span><?php esc_html_e('Choose CSV File', 'wpil'); ?></span>
                </label>
                <input class="wpil-custom-file-input" type="file" id="wpil-custom-linking-file-input" accept=".csv" data-wpil-custom-linking-file>
                <span class="wpil-custom-file-name" data-wpil-custom-linking-file-name><?php esc_html_e('No file selected yet.', 'wpil'); ?></span>
            </div>
            <button type="submit" class="wpil-fix-primary" data-wpil-custom-linking-upload disabled><?php esc_html_e('Upload and Parse', 'wpil'); ?></button>
          </div>
        </form>
        <div class="wpil-custom-progress" data-wpil-custom-linking-progress>
          <div class="wpil-custom-progress-label">
            <span data-wpil-custom-linking-progress-phase><?php esc_html_e('Parsing', 'wpil'); ?></span>
            <span data-wpil-custom-linking-progress-percent>0%</span>
          </div>
          <div class="wpil-custom-progress-bar">
            <div class="wpil-custom-progress-fill" data-wpil-custom-linking-progress-fill></div>
          </div>
          <div class="wpil-custom-progress-copy" data-wpil-custom-linking-progress-copy><?php esc_html_e('Upload a CSV to begin building the preview map.', 'wpil'); ?></div>
        </div>
      </div>

      <div class="wpil-custom-card" data-wpil-custom-linking-summary-card>
        <div class="wpil-custom-card-title">Current Plan</div>
        <div class="wpil-custom-summary-grid">
          <div class="wpil-custom-stat">
            <span class="wpil-custom-stat-title"><?php esc_html_e('Source Posts', 'wpil'); ?></span>
            <strong data-wpil-custom-stat="source_posts_exact">0</strong>
            <span data-wpil-custom-copy="source_posts_exact"><?php esc_html_e('Exact unique posts that may place links after preview building.', 'wpil'); ?></span>
          </div>
          <div class="wpil-custom-stat">
            <span class="wpil-custom-stat-title"><?php esc_html_e('Link Targets', 'wpil'); ?></span>
            <strong data-wpil-custom-stat="target_posts_exact">0</strong>
            <span data-wpil-custom-copy="target_posts_exact"><?php esc_html_e('This is the estimated number of posts that will get links pointed to them.', 'wpil'); ?></span>
          </div>
          <div class="wpil-custom-stat">
            <span class="wpil-custom-stat-title"><?php esc_html_e('Potential Links', 'wpil'); ?></span>
            <strong data-wpil-custom-stat="potential_links_range">0</strong>
            <span data-wpil-custom-copy="potential_links_range"><?php esc_html_e('This is the estimated number of links that this plan will generate.', 'wpil'); ?></span>
          </div>
        </div>
        <p class="wpil-custom-empty-state" style="margin-top: 12px;" data-wpil-custom-linking-summary-empty><?php esc_html_e('No custom CSV plan has been uploaded yet.', 'wpil'); ?></p>
      </div>

      <div class="wpil-custom-card wpil-custom-warnings hidden" data-wpil-custom-linking-warnings-card>
        <div class="wpil-custom-card-title">Import Warnings</div>
        <ul class="wpil-custom-warning-list" data-wpil-custom-linking-warnings></ul>
      </div>

      <div class="wpil-custom-card">
        <div class="wpil-custom-card-title">Insertion Mode</div>
        <div class="wpil-custom-link-mode">
          <label class="wpil-custom-mode-option">
            <input type="radio" name="wpil-custom-ai-linking-mode" value="review" checked>
            <span class="wpil-custom-mode-card">
              <span class="wpil-custom-mode-title"><?php esc_html_e('Review before inserting', 'wpil'); ?></span>
              <span class="wpil-custom-mode-copy"><?php esc_html_e('Generate the suggestions, then approve or reject them before links are added.', 'wpil'); ?></span>
            </span>
          </label>
          <label class="wpil-custom-mode-option">
            <input type="radio" name="wpil-custom-ai-linking-mode" value="auto">
            <span class="wpil-custom-mode-card">
              <span class="wpil-custom-mode-title"><?php esc_html_e('Insert automatically', 'wpil'); ?></span>
              <span class="wpil-custom-mode-copy"><?php esc_html_e('Run the custom plan straight through and let Link Whisper insert approved links automatically.', 'wpil'); ?></span>
            </span>
          </label>
        </div>
      </div>

      <div class="wpil-fix-credit-card">
        <div class="wpil-fix-credit-head">
          <span class="wpil-fix-credit-label"><?php esc_html_e('AI Credits', 'wpil'); ?></span>
          <span class="wpil-fix-credit-badge is-muted" data-wpil-custom-credit-status><?php esc_html_e('Upload Required', 'wpil'); ?></span>
        </div>

        <div class="wpil-fix-credit-stats">
          <div>
            <div class="wpil-fix-credit-number" data-wpil-custom-credit-balance>0</div>
            <div class="wpil-fix-credit-sub"><?php esc_html_e('available', 'wpil'); ?></div>
          </div>

          <div class="wpil-fix-credit-required">
            <div class="wpil-fix-credit-sub"><?php esc_html_e('Estimated Credits Needed', 'wpil'); ?></div>
            <div class="wpil-fix-credit-number" data-wpil-custom-credit-estimate>0</div>
          </div>
        </div>

        <div class="wpil-fix-credit-bar">
          <div class="wpil-fix-credit-bar-fill" data-wpil-custom-credit-bar></div>
        </div>

        <div class="wpil-fix-credit-foot">
          <span data-wpil-custom-credit-foot><?php esc_html_e('Upload a CSV plan to calculate the credit estimate.', 'wpil'); ?></span>
        </div>

        <div class="wpil-custom-credit-warning" data-wpil-custom-credit-warning></div>
      </div>
    </div>

    <div class="wpil-fix-actions">
      <button class="wpil-fix-secondary" type="button" data-wpil-custom-linking-clear><?php esc_html_e('Clear Plan', 'wpil'); ?></button>
      <button class="wpil-fix-secondary" type="button" data-wpil-custom-linking-close><?php esc_html_e('Close', 'wpil'); ?></button>
      <button class="wpil-fix-primary" type="button" data-wpil-custom-linking-start><?php esc_html_e('Start Custom AI Linking', 'wpil'); ?></button>
    </div>
  </div>
</div>
