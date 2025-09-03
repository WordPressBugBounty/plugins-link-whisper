<div class="wrap wpil-report-page wpil_styles">
    <h1 class="wp-heading-inline wpil-is-tooltipped wpil-no-overlay wpil-no-scale" data-wpil-tooltip-read-time="2500" <?php echo Wpil_Toolbox::generate_tooltip_text('domain-report-intro'); ?>>Domains Report</h1>
    <hr class="wp-header-end">
    <div id="poststuff">
        <div id="post-body" class="metabox-holder">
            <div id="post-body-content" style="position: relative;">
                <input id="wpil-object-cache-flush-nonce" type="hidden" value="<?php echo wp_create_nonce('wpil-flush-object-cache'); ?>" />
                <?php include_once 'report_tabs.php'; ?>
                <div id="report_domains">
                    <div>
                        <?php echo $report_description; ?>
                        <div class="wpil-filter-wrapper">
                            <button id="wpil-filter-toggle" class="wpil-hamburger-toggle" type="button" aria-expanded="false" aria-controls="wpil-filter-panel">
                                <svg class="wpil-hamburger-icon" viewBox="0 0 100 100" width="30" height="30">
                                    <path class="line top" d="M 20,30 H 80" />
                                    <path class="line middle" d="M 20,50 H 80" />
                                    <path class="line bottom" d="M 20,70 H 80" />
                                </svg>
                            </button>
                            <div id="wpil-filter-panel" class="wpil-report-search-form-wrapper" hidden>
                                <script>
                                    document.addEventListener('DOMContentLoaded', function () {
                                        const toggleBtn = document.getElementById('wpil-filter-toggle');
                                        const panel = document.getElementById('wpil-filter-panel');

                                        toggleBtn.addEventListener('click', function () {
                                            const isOpen = !panel.hasAttribute('hidden');
                                            panel.toggleAttribute('hidden');
                                            toggleBtn.setAttribute('aria-expanded', String(!isOpen));
                                            toggleBtn.classList.toggle('open', !isOpen);
                                        });
                                    });
                                </script>
                                <form class="wpil-report-search-form-inner">
                                    <input type="hidden" name="page" value="link_whisper" />
                                    <input type="hidden" name="type" value="domains" />
                                    <?php $table->search_box('Search', 'search'); ?>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="wpil-is-tooltipped wpil-no-scale wpil-tooltip-target-child wpil-tooltip-target.wp-list-table wpil-tooltip-no-position" data-wpil-tooltip-read-time="4500" <?php echo Wpil_Toolbox::generate_tooltip_text('domain-report-table'); ?>>
                        <?php $table->display(); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
