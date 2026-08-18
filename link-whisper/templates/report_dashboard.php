<div class="wrap wpil-report-page wpil_styles">
<?php
    // Error report codes
    $codes = Wpil_Dashboard::getAllErrorCodes();
    $codes = (!empty($codes)) ? '&codes=' . implode(',', $codes) : '';

    // Loading state used by wizard banners
    $loading = (isset($_GET['loading']) && !empty($_GET['loading']));

    // Stats
    $posts_crawled       = (int) Wpil_Dashboard::getPostCount();
    $site_post_count     = (int) Wpil_Dashboard::getPostCount(false);
    $orphanedCount       = (int) Wpil_Dashboard::getOrphanedPostsCount();
    $brokenLinksCount    = (int) Wpil_Dashboard::getBrokenLinksCount();
    $notfoundLinksCount  = (int) Wpil_Dashboard::get404LinksCount(); // not used in template yet

    // Link coverage percent
    $link_density = Wpil_Dashboard::get_percent_of_posts_hitting_link_targets();
    $link_coverage_percent = !empty($link_density['percent']) ? (float) $link_density['percent'] : 0.0;

    // Click stats
    $summary   = Wpil_Dashboard::get_click_traffic_stats();
    $clicks_30 = isset($summary['clicks_30']) ? (int) $summary['clicks_30'] : 0;
    $clicks_old = isset($summary['clicks_old']) ? (int) $summary['clicks_old'] : 0;

    $difference = $clicks_30 - $clicks_old;
    $percent_change = $clicks_old != 0
        ? round(($difference / $clicks_old) * 100, 2)
        : ($clicks_30 > 0 ? 100 : 0);

    $is_positive = ($difference >= 0);

    // External focus
    $external_link_emphasis = Wpil_Dashboard::get_external_link_distribution(1);
    $external_link_emphasis_percent = 0.0;
    if(!empty($external_link_emphasis) && isset($external_link_emphasis[0]->representation)){
        $external_link_emphasis_percent = (float) (round($external_link_emphasis[0]->representation, 2) * 100);
    }

    // Anchor length score (your old flow uses total/filtered)
    $anchor_word_counts = Wpil_Dashboard::getAnchorPostCounts();
    $anchor_length_percent = null;
    if(!empty($anchor_word_counts['total']) && !empty($anchor_word_counts['filtered'])){
        $anchor_length_percent = (float) (round($anchor_word_counts['filtered'] / $anchor_word_counts['total'], 2) * 100);
    }

    // Internal & External links
    $internal_links = Wpil_Dashboard::getInternalLinksCount();
    $external_links = Wpil_Dashboard::getExternalLinksCount();

    $internal_percent = round((($internal_links > 0) ? $internal_links / ($external_links + $internal_links): 0) * 100);
    $external_percent = round((($external_links > 0) ? $external_links / ($external_links + $internal_links): 0) * 100);

    // Link Quality Score
    $link_relatedness = Wpil_Dashboard::get_related_link_percentage();

    $relatedness_dash = max(0, min(100, $link_relatedness));
    $relatedness_dash_str = number_format($relatedness_dash, 0) . ', 100';

    // Links inserted
    $links_inserted = Wpil_Dashboard::get_tracked_link_insert_count();

    // Small helper: status badge classes
    function wpil_dash_badge($type, $value){
        $value = (float) $value;

        if($type === 'coverage'){
            if($value >= 80) return ['Good', 'bg-green-50 text-green-600'];
            if($value >= 60) return ['OK', 'bg-orange-50 text-orange-600'];
            return ['Needs Attention', 'bg-red-50 text-red-600'];
        }

        if($type === 'countup'){
            if($value >= 1) return ['Great Job!', 'bg-green-50 text-green-600'];
            if($value >= 0) return ['Getting Started', 'bg-orange-50 text-orange-600'];
            return ['Needs Attention', 'bg-red-50 text-red-600'];
        }

        // counts (orphaned, broken)
        if((int)$value <= 0){
            return ['Good', 'bg-green-50 text-green-600'];
        }

        return ['Needs Attention', 'bg-red-50 text-red-600'];
    }

    // Circular gauge for coverage card
    // Uses 36x36 circle path style like your HTML mockup
    $coverage_dash = max(0, min(100, $link_coverage_percent));
    $coverage_dash_str = number_format($coverage_dash, 0) . ', 100';

    // Existing outbound icon used in old action buttons. Keep if you want later.
    $link_icon = '<svg width="24" height="24" style="position: absolute; margin: 1px 0px 0 3px; height: 12px; width: 12px; fill: #ffffff; stroke: #ffffff; display:inline-block;" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><g transform="matrix(0.046875,0,0,0.046875,0.0234375,0.02343964)"><path d="M 473.563,227.063 407.5,161 262.75,305.75 c -25,25 -49.563,41 -74.5,16 -25,-25 -9,-49.5 16,-74.5 L 349,102.5 283.937,37.406 c -14.188,-14.188 -2,-37.906 19,-37.906 h 170.625 c 20.938,0 37.938,16.969 37.938,37.906 v 170.688 c 0,20.937 -23.687,33.187 -37.937,18.969 z M 63.5,447.5 h 320 V 259.313 l 64,64 V 447.5 c 0,35.375 -28.625,64 -64,64 h -320 c -35.375,0 -64,-28.625 -64,-64 v -320 c 0,-35.344 28.625,-64 64,-64 h 124.188 l 64,64 H 63.5 Z"></path></g></svg>';

    // Badges
    $coverage_badge = wpil_dash_badge('coverage', $link_coverage_percent);
    $orphan_badge   = wpil_dash_badge('count', $orphanedCount);
    $broken_badge   = wpil_dash_badge('count', $brokenLinksCount);
    $insert_badge   = wpil_dash_badge('countup', $links_inserted);

    // Tooltip markup is still WPIL custom, keep styles minimal and rely on existing plugin CSS

    // Site Health Score
    $health_metrics = [
        'posts_crawled'            => $posts_crawled,
        'site_post_count'          => $site_post_count,
        'broken_links'             => $brokenLinksCount,
        'orphaned_posts'           => $orphanedCount,
        'link_coverage_percent'    => $link_coverage_percent,
        'link_relatedness_percent' => $link_relatedness,
        'external_site_focus'      => $external_link_emphasis_percent,
    ];

    $health = Wpil_Dashboard::wpil_dash_site_health_score($health_metrics);
    $health_meta = Wpil_Dashboard::wpil_dash_site_health_meta($health['score']);
    $health_hint = Wpil_Dashboard::wpil_dash_site_health_hint($health);

    // Circle math for the SVG ring
    $health_radius = 40;
    $health_circumference = 2 * M_PI * $health_radius; // 251.2 ish
    $health_progress = max(0, min(100, (int) $health['score']));
    $health_dasharray = $health_circumference;
    $health_dashoffset = $health_circumference * (1 - ($health_progress / 100));

    // Recommended Actions
    // 1) URLs (match keys used by your generator)
    $admin_urls = [
        'broken_links'       => htmlspecialchars(admin_url('admin.php?page=link_whisper&type=error' . $codes)),
        'orphaned_posts'     => admin_url('admin.php?page=link_whisper&type=links&orphaned=1'),
        'link_density'       => admin_url('admin.php?page=link_whisper&type=links&link_density=1'),
        'link_relation'      => admin_url('admin.php?page=link_whisper&type=links&link_relation=1'),
        'domains_report'     => admin_url('admin.php?page=link_whisper&type=domains'),
        'anchor_suggestions' => '', // wire later
    ];

    // 2) Metrics
    $action_metrics = [
        'broken_links'            => $brokenLinksCount,
        'orphaned_posts'          => $orphanedCount,
        'anchor_length_percent'   => $anchor_length_percent,          // can be null
        'link_coverage_percent'   => $link_coverage_percent,
        'link_relatedness_percent'=> $link_relatedness,
        'external_site_focus'     => $external_link_emphasis_percent, // <-- you already compute this above
        'admin_urls'              => $admin_urls,
    ];

    // 3) Generate actions
    $recommended_actions = Wpil_Dashboard::wpil_dash_generate_recommended_actions($action_metrics);

    // 4) Serious count (enabled CTAs only)
    $serious = Wpil_Dashboard::wpil_dash_count_serious_actions($recommended_actions);

    // generate hints for the health report!
    $hint = Wpil_Dashboard::wpil_dash_health_hint_payload($health, $recommended_actions, $admin_urls);
    $hint_text = !empty($hint['text']) ? $hint['text'] : '';
    $hint_target = !empty($hint['target_action_id']) ? $hint['target_action_id'] : '';
    $hint_url = !empty($hint['fallback_url']) ? $hint['fallback_url'] : '#';
    $hint_key = !empty($health['top_drag']['key']) ? (string) $health['top_drag']['key'] : '';
    $hint_stat_map = [
        'broken_links'  => 'wpil-stat-broken-links',
        'orphaned_posts'=> 'wpil-stat-orphaned-posts',
        'link_coverage' => 'wpil-stat-link-coverage',
        'link_quality'  => 'wpil-stat-link-quality',
        'external_focus'=> 'wpil-stat-external-links',
    ];
    $hint_stat_id = (!empty($hint_key) && isset($hint_stat_map[$hint_key])) ? $hint_stat_map[$hint_key] : '';

?>

<script>
    document.addEventListener('click', function(e) {
    var el = e.target.closest('[data-wpil-hint-action-id]');
    if (!el) return;

    var actionId = el.getAttribute('data-wpil-hint-action-id');
    var statId = el.getAttribute('data-wpil-hint-stat-id');

    var target = null;
    if (actionId) {
        target = document.getElementById('wpil-action-' + actionId);
    }
    if (!target && statId) {
        target = document.getElementById(statId);
    }
    if (!target) return; // no target, let normal link behavior happen

    e.preventDefault();

    target.scrollIntoView({ behavior: 'smooth', block: 'center' });

    // highlight
    target.classList.add('wpil-health-highlight');
    window.setTimeout(function() {
        target.classList.remove('wpil-health-highlight');
    }, 1800);
    });
</script>
<style>
    /* Keep brand helpers from your mock */
    .lw-gradient-bg { background: linear-gradient(90deg, #7F5AF0 0%, #2C6BFF 100%); }
    .lw-text-gradient { background: linear-gradient(90deg, #7F5AF0 0%, #2C6BFF 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }

    @keyframes fillBar { from { width: 0%; } }
    .animate-fill { animation: fillBar 1.5s ease-out forwards; }

    .wpil-health-highlight{
        box-shadow: 0 0 0 3px rgba(127, 90, 240, 0.35);
        border-color: rgba(127, 90, 240, 0.65) !important;
        transition: box-shadow 150ms ease, border-color 150ms ease;
    }

    .wpil-fix-progress-wrap{
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-left: 10px;
        vertical-align: middle;
        font-size: 11px;
        font-weight: 700;
        color: #2563eb;
    }
    .wpil-fix-progress-wrap.is-complete{
        color: #16a34a;
    }
    .wpil-fix-progress-ring{
        width: 16px;
        height: 16px;
        border-radius: 999px;
        background:
            conic-gradient(#3b82f6 var(--wpil-fix-progress, 0%), #e5e7eb 0);
        position: relative;
        display: inline-block;
    }
    .wpil-fix-progress-ring::after{
        content: '';
        position: absolute;
        inset: 3px;
        border-radius: 999px;
        background: #fff;
    }
    .wpil-fix-progress-link{
        cursor: pointer;
    }
</style>

<h1 class="wp-heading-inline wpil-is-tooltipped wpil-no-overlay wpil-no-scale" <?php echo Wpil_Toolbox::generate_tooltip_text('dashboard-intro'); ?>>Dashboard</h1>
<hr class="wp-header-end">

<div id="poststuff">
  <div id="post-body" class="metabox-holder">
    <div id="post-body-content" class="relative">

      <?php //include_once 'report_tabs_dashboard.php'; ?>

      <?php if($loading){ ?>
        <input type="hidden" class="wpil-wizard-loading-dashboard" value="1">
        <input type="hidden" class="wpil-wizard-inserting-autolinks" value="<?php echo get_option('wpil_wizard_import_autolink_rules', 0); ?>">
        <input type="hidden" class="wpil-wizard-loading-dashboard-nonce" value="<?php echo wp_create_nonce($user->ID . 'wpil_dashboard_loading_nonce'); ?>">

        <div class="syns_div wpil_report_need_prepare wpil-report-download-banner wpil-is-tooltipped wpil-no-scale" <?php echo Wpil_Toolbox::generate_tooltip_text('dashboard-report-loading-bar'); ?>>
          <div class="progress_panel relative">
            <div class="wpil-dashboard-processing-status absolute z-10 w-full">
              <div class="wpil-report-download-banner-process">
                <i class="dashicons dashicons-clock" style="width:40px;height:40px;color:#fff;margin:10px;font-size:40px;"></i>
                <span class="wpil-dashboard-processing-message" style="margin-left:5px; padding:18px 0 !important; position:absolute; font-size:20px; color:#fff;">Scanning Site</span>
              </div>
              <div class="wpil-report-download-banner-remaining-time" style="margin-left:5px; padding:18px 0 !important; position:absolute; font-size:20px; color:#fff; right:15px; top:0;">
                <span class="wpil-dashboard-processing-time-remaining" style="margin-right:10px;">Calculating Time Remaining</span>
                <span class="wpil-dashboard-processing-clock">--:--:--</span>
              </div>
            </div>
            <div class="progress_count" style="background-color:#2da7fd"><span class="wpil-loading-status"></span></div>
          </div>
        </div>

        <div class="syns_div wpil_report_need_prepare wpil-report-autolink-insert-banner wpil-is-tooltipped wpil-no-scale" style="display:none;" <?php echo Wpil_Toolbox::generate_tooltip_text('dashboard-report-loading-bar'); ?>>
          <div class="progress_panel relative">
            <div class="wpil-dashboard-processing-status absolute z-10 w-full">
              <div class="wpil-report-autolink-insert-process">
                <i class="dashicons dashicons-admin-links" style="width:40px;height:40px;color:#fff;margin:10px;font-size:40px;"></i>
                <span class="wpil-dashboard-processing-message" style="margin-left:5px; padding:18px 0 !important; position:absolute; font-size:20px; color:#fff;">Creating Links!</span>
              </div>
              <div class="wpil-report-autolink-insert-remaining-time" style="margin-left:5px; padding:18px 0 !important; position:absolute; font-size:20px; color:#fff; right:15px; top:0;">
                <span style="margin-right:10px;">Links Created:</span>
                <span class="wpil-dashboard-processing-autolinks-inserted">0</span>
              </div>
            </div>
            <div class="progress_count" style="width:100%; background-color:#b63ef8;"><span class="wpil-loading-status"></span></div>
          </div>
        </div>
      <?php } ?>

      <!-- Dashboard container -->
      <div class="mx-auto space-y-8 mt-4">

        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4" style="display:none">
          <div>
            <h2 class="text-3xl font-bold text-gray-900">Dashboard</h2>
            <p class="text-gray-500 text-sm mt-1">Overview of your site's internal linking structure.</p>
          </div>

          <div class="flex items-center space-x-4 justify-end">
            <div class="hidden md:flex flex-col items-end mr-2">
              <span class="text-xs font-bold text-gray-400 uppercase">AI Credits</span>
              <span class="text-sm font-bold text-[#7F5AF0]">
                <!--TO-IMPLEMENT: show real AI credits available -->
                <?php echo (int) number_format($credits); ?> Available
              </span>
            </div>

            <a style="display:none;" href="<?php echo admin_url('admin.php?page=link_whisper&type=links'); ?>"
               class="bg-white border border-gray-200 text-gray-700 font-medium px-4 py-2.5 rounded-xl shadow-sm hover:bg-gray-50 transition-colors flex items-center">
              <span>Reports</span>
              <!--TO-IMPLEMENT: if you want a dropdown, replace this with a menu -->
            </a>

            <a href="<?php echo admin_url('admin.php?page=link_whisper'); ?>"
               class="lw-gradient-bg text-white font-bold px-6 py-2.5 rounded-xl shadow-lg hover:shadow-xl hover:opacity-95 transition-all flex items-center">
              <span>Run New Scan</span>
              <!--TO-IMPLEMENT: wire to scan start action (wizard kickoff) -->
            </a>
          </div>
        </div>
        <div class="mx-auto space-y-8 mt-4">
            <!-- Tabs -->
            <div class="border-b flex justify-between border-gray-200">
                <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                    <a href="<?php echo admin_url('admin.php?page=link_whisper'); ?>"
                    class="border-[#7F5AF0] text-[#7F5AF0] whitespace-nowrap py-4 px-1 border-b-2 font-bold text-sm">Dashboard</a>

                    <a href="<?php echo admin_url('admin.php?page=link_whisper&type=links'); ?>"
                    class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">Links Report</a>

                    <a href="<?php echo admin_url('admin.php?page=link_whisper&type=domains'); ?>"
                    class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">Domains Report</a>

                    <a href="<?php echo admin_url('admin.php?page=link_whisper&type=clicks'); ?>"
                    class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">Clicks Report</a>

                    <a href="<?php echo htmlspecialchars(admin_url('admin.php?page=link_whisper&type=error' . $codes)); ?>"
                    class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">Broken Links Report</a>
                
                    <a href="<?php echo admin_url('admin.php?page=link_whisper&type=sitemaps'); ?>"
                    class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">Visual Sitemaps</a>
                </nav>
                <div class="flex items-center space-x-4 justify-end">
                    <div class="hidden md:flex flex-col items-end mr-2">
                        <span class="text-xs font-bold text-gray-400 uppercase">AI Credits</span>
                        <span class="text-sm font-bold text-[#7F5AF0]">
                            <!--TO-IMPLEMENT: show real AI credits available -->
                            <?php echo number_format($credits); ?> Available
                        </span>
                    </div>
                    <a href="<?php echo admin_url('admin.php?page=link_whisper'); ?>"
                    class="lw-gradient-bg text-white font-bold px-6 py-2.5 rounded-xl shadow-lg hover:shadow-xl hover:opacity-95 transition-all flex items-center">
                    <span>Run Link Scan</span>
                    <!--TO-IMPLEMENT: wire to scan start action (wizard kickoff) -->
                    </a>
                </div>
            </div>
        </div>

        <!-- Stat cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">

          <!-- Posts Crawled -->
          <a href="<?php echo admin_url('admin.php?page=link_whisper&type=links'); ?>" target="_blank"
             class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 flex flex-col justify-between h-32 relative overflow-hidden group hover:border-purple-200 transition-colors">
            <div class="absolute right-0 top-0 w-24 h-24 bg-purple-50 rounded-full -mr-8 -mt-8 opacity-50 group-hover:scale-110 transition-transform"></div>
            <h3 class="text-sm font-bold text-gray-400 uppercase tracking-wide z-10">Posts Crawled</h3>
            <div class="flex items-end justify-between z-10">
              <span class="text-4xl font-extrabold text-gray-900 wpil-report-stats-posts-crawled"><?php echo $posts_crawled; ?></span>
            </div>
          </a>

          <!-- Link Coverage -->
          <a href="<?php echo admin_url('admin.php?page=link_whisper&type=links&link_density=1'); ?>" target="_blank" id="wpil-stat-link-coverage"
             class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 flex flex-col justify-between h-32 relative overflow-hidden group hover:border-blue-200 transition-colors">
            <h3 class="text-sm font-bold text-gray-400 uppercase tracking-wide z-10 flex items-center gap-2">
              Link Coverage

              <span class="wpil-report-header-container cust-tooltipdash relative">
                <span
                  class="dashicons dashicons-editor-help wpil-tippy-tooltipped"
                  data-wpil-tooltip-placement="top"
                  data-wpil-tooltip-allowhtml="1"
                  data-wpil-tooltip-content="Link Coverage tells you how many pages are receiving internal links.<br><br>Recommended: at least 1 inbound internal link and 3 outbound internal links.">
                </span>
              </span>
            </h3>

            <div class="flex items-end justify-between z-10">
              <span class="text-4xl font-extrabold text-gray-900 wpil-report-stats-link-coverage">
                <?php echo rtrim(rtrim(number_format($link_coverage_percent, 2), '0'), '.'); ?>%
              </span>

              <div class="w-12 h-12 relative">
                <svg viewBox="0 0 36 36" class="w-12 h-12 transform -rotate-90">
                  <path class="text-gray-100"
                        d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                        fill="none" stroke="currentColor" stroke-width="4" />
                  <path class="<?php echo ($link_coverage_percent >= 60 ? 'text-orange-400' : 'text-red-400'); ?>"
                        stroke-dasharray="<?php echo esc_attr($coverage_dash_str); ?>"
                        d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                        fill="none" stroke="currentColor" stroke-width="4" />
                </svg>
              </div>
            </div>
          </a>

          <!-- Orphaned Posts -->
          <a href="<?php echo admin_url('admin.php?page=link_whisper&type=links&orphaned=1'); ?>" target="_blank" id="wpil-stat-orphaned-posts"
             class="bg-white rounded-2xl p-6 shadow-sm border <?php echo ($orphanedCount > 0) ? 'border-red-100' : 'border-gray-100'; ?> flex flex-col justify-between h-32 relative overflow-hidden">
            <div class="absolute right-0 top-0 w-24 h-24 <?php echo ($orphanedCount > 0) ? 'bg-red-50' : 'bg-gray-50'; ?> rounded-full -mr-8 -mt-8 opacity-50"></div>
            <h3 class="text-sm font-bold <?php echo ($orphanedCount > 0) ? 'text-red-400' : 'text-gray-400'; ?> uppercase tracking-wide z-10 flex items-center">
              Orphaned Posts
              <?php if($orphanedCount > 0){ ?>
                <span class="ml-2 w-2 h-2 bg-red-500 rounded-full animate-pulse"></span>
              <?php } ?>
            </h3>
            <div class="flex items-end justify-between z-10">
              <?php if($loading){ ?>
                <span class="text-4xl font-extrabold text-gray-900">
                  <span class="wpil-report-dashboard-loading la-ball-clip-rotate la-mid inline-block align-middle">
                    <span style="border-color: black; border-bottom-color: transparent;"></span>
                  </span>
                </span>
              <?php } else { ?>
                <span class="text-4xl font-extrabold text-gray-900"><?php echo $orphanedCount; ?></span>
              <?php } ?>
              <span class="text-xs font-bold px-2 py-1 rounded-lg <?php echo esc_attr($orphan_badge[1]); ?>">
                <?php echo esc_html($orphan_badge[0]); ?>
              </span>
            </div>
          </a>

          <!-- Broken Links -->
          <a href="<?php echo htmlspecialchars(admin_url('admin.php?page=link_whisper&type=error' . $codes)); ?>" target="_blank" id="wpil-stat-broken-links"
             class="bg-white rounded-2xl p-6 shadow-sm border <?php echo ($brokenLinksCount > 0) ? 'border-orange-100' : 'border-gray-100'; ?> flex flex-col justify-between h-32 relative overflow-hidden">
            <div class="absolute right-0 top-0 w-24 h-24 <?php echo ($brokenLinksCount > 0) ? 'bg-orange-50' : 'bg-gray-50'; ?> rounded-full -mr-8 -mt-8 opacity-50"></div>
            <h3 class="text-sm font-bold <?php echo ($brokenLinksCount > 0) ? 'text-orange-400' : 'text-gray-400'; ?> uppercase tracking-wide z-10 flex items-center">
              Broken Links
              <?php if($brokenLinksCount > 0){ ?>
                <span class="ml-2 w-2 h-2 bg-orange-500 rounded-full animate-pulse"></span>
              <?php } ?>
            </h3>
            <div class="flex items-end justify-between z-10">
              <span class="text-4xl font-extrabold text-gray-900"><?php echo $brokenLinksCount; ?></span>
              <span class="text-xs font-bold px-2 py-1 rounded-lg <?php echo esc_attr($broken_badge[1]); ?>">
                <?php echo esc_html($broken_badge[0]); ?>
              </span>
            </div>
          </a>

        </div>

        <!-- Mid row -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <!-- Site Health Score -->
            <div class="bg-white rounded-2xl p-8 shadow-sm border border-gray-100 flex flex-col justify-center items-center text-center relative">
                <h3 class="text-gray-500 font-bold mb-4">Site Health Score</h3>

                <div class="relative w-40 h-40 mb-4">
                    <svg class="w-full h-full" viewBox="0 0 100 100">
                    <circle class="text-gray-100 stroke-current" stroke-width="8" cx="50" cy="50" r="40" fill="transparent"></circle>

                    <circle
                        class="<?php echo esc_attr($health_meta['ring']); ?> stroke-current"
                        stroke-width="8"
                        stroke-linecap="round"
                        cx="50" cy="50" r="40"
                        fill="transparent"
                        style="stroke-dasharray: <?php echo esc_attr($health_dasharray); ?>; stroke-dashoffset: <?php echo esc_attr($health_dashoffset); ?>; transform: rotate(-90deg); transform-origin: 50% 50%;"
                    ></circle>
                    </svg>

                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                    <span class="text-4xl font-extrabold text-gray-900"><?php echo (int) $health['score']; ?></span>
                    <span class="text-xs text-gray-400 uppercase font-bold"><?php echo esc_html($health_meta['label']); ?></span>
                    </div>
                </div>

                <a href="<?php echo esc_url($hint_url); ?>" class="text-sm text-gray-600 px-4 inline-block hover:underline" data-wpil-hint-action-id="<?php echo esc_attr($hint_target); ?>" data-wpil-hint-stat-id="<?php echo esc_attr($hint_stat_id); ?>">
                    <?php echo esc_html($hint_text); ?>
                </a>
            </div>


          <!-- Link Distribution (map to existing stats that exist today) -->
          <div class="lg:col-span-2 bg-white rounded-2xl p-8 shadow-sm border border-gray-100">
            <div class="flex justify-between items-center mb-6">
              <h3 class="text-lg font-bold text-gray-900">Link Distribution</h3>
            </div>

            <div class="space-y-6">

              <!-- Clicks tracked -->
              <div>
                <div class="flex justify-between text-sm mb-2">
                  <span class="font-medium text-gray-600">Internal Links</span>
                  <span class="font-bold text-gray-900"><?php echo number_format($internal_links); ?></span>
                </div>
                <div class="w-full bg-gray-100 rounded-full h-2.5">
                  <!--TO-IMPLEMENT: decide what this bar represents, currently static -->
                  <div class="lw-gradient-bg h-2.5 rounded-full animate-fill" style="width: <?php echo (int) min(100, max(0, $internal_percent)); ?>%"></div>
                </div>
              </div>

              <!-- External Site Focus -->
              <div id="wpil-stat-external-links">
                <div class="flex justify-between text-sm mb-2">
                  <span class="font-medium text-gray-600">External Links</span>
                  <span class="font-bold text-gray-900"><?php echo number_format($external_links); ?></span>
                </div>
                <div class="w-full bg-gray-100 rounded-full h-2.5">
                  <div class="bg-blue-300 h-2.5 rounded-full animate-fill" style="width: <?php echo (int) min(100, max(0, $external_percent)); ?>%"></div>
                </div>
                <div class="mt-3" style="display: none;">
                  <a href="<?php echo admin_url('admin.php?page=link_whisper&type=domains&domain_focus=1'); ?>"
                     class="inline-flex items-center text-xs font-bold text-[#7F5AF0] hover:underline" target="_blank">
                    View Domains Report →
                  </a>
                </div>
              </div>

              <!-- Anchor Length Score -->
              <div>
                <div class="flex justify-between text-sm mb-2">
                  <span class="font-medium text-gray-600">Anchor Length Score</span>
                  <span class="font-bold text-gray-900">
                    <?php echo ($anchor_length_percent === null) ? 'Unknown' : ((int) $anchor_length_percent . '%'); ?>
                  </span>
                </div>
                <div class="w-full bg-gray-100 rounded-full h-2.5">
                  <div class="bg-yellow-400 h-2.5 rounded-full animate-fill"
                       style="width: <?php echo ($anchor_length_percent === null) ? 37 : (int) min(100, max(0, $anchor_length_percent)); ?>%"></div>
                </div>
              </div>

            </div>
          </div>

        </div>

        <!-- Third row -->
         <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">

          <!-- Clicks Tracked -->
          <a href="<?php echo admin_url('admin.php?page=link_whisper&type=clicks'); ?>" target="_blank"
             class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 flex flex-col justify-between h-32 relative overflow-hidden group hover:border-purple-200 transition-colors">
            <div class="absolute right-0 top-0 w-24 h-24 bg-purple-50 rounded-full -mr-8 -mt-8 opacity-50 group-hover:scale-110 transition-transform"></div>
            <h3 class="text-sm font-bold text-gray-400 uppercase tracking-wide z-10">Clicks Tracked</h3>
            <div class="flex items-end justify-between z-10">
              <span class="text-4xl font-extrabold text-gray-900 wpil-report-stats-posts-crawled"><?php echo $clicks_30; ?></span>
              <span class="text-green-500 text-xs font-bold bg-green-50 px-2 py-1 rounded-lg flex items-center">
                <?php if($is_positive){ ?>
                  <span class="mr-1">▲</span>
                <?php } else { ?>
                  <span class="mr-1">▼</span>
                <?php } ?>
                <?php echo ($is_positive ? '+' : '') . $percent_change; ?>%
              </span>
            </div>
          </a>

          <!-- Link Relation -->
          <a href="<?php echo admin_url('admin.php?page=link_whisper&type=links&link_relation=1'); ?>" target="_blank" id="wpil-stat-link-quality"
             class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 flex flex-col justify-between h-32 relative overflow-hidden group hover:border-blue-200 transition-colors">
            <h3 class="text-sm font-bold text-gray-400 uppercase tracking-wide z-10 flex items-center gap-2">
              Link Quality Score
              <span class="wpil-report-header-container cust-tooltipdash relative">
                <span
                  class="dashicons dashicons-editor-help wpil-tippy-tooltipped"
                  data-wpil-tooltip-placement="top"
                  data-wpil-tooltip-allowhtml="1"
                  data-wpil-tooltip-content="Link Quality Score measures how closely related your internal links are based on AI analysis.<br><br>Higher percentages indicate stronger topical relevance.">
                </span>
              </span>
            </h3>

            <div class="flex items-end justify-between z-10">
              <span class="text-4xl font-extrabold text-gray-900 wpil-report-stats-link-coverage">
                <?php echo rtrim(rtrim(number_format($link_relatedness, 2), '0'), '.'); ?>%
              </span>

              <div class="w-12 h-12 relative">
                <svg viewBox="0 0 36 36" class="w-12 h-12 transform -rotate-90">
                  <path class="text-gray-100"
                        d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                        fill="none" stroke="currentColor" stroke-width="4" />
                  <path class="<?php echo ($link_relatedness >= 60 ? 'text-orange-400' : 'text-red-400'); ?>"
                        stroke-dasharray="<?php echo esc_attr($relatedness_dash_str); ?>"
                        d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                        fill="none" stroke="currentColor" stroke-width="4" />
                </svg>
              </div>
            </div>
          </a>

          <!-- Links Inserted -->
          <div
             class="bg-white rounded-2xl p-6 shadow-sm border border-blue-100 flex flex-col justify-between h-32 relative overflow-hidden">
            <div class="absolute right-0 top-0 w-24 h-24 bg-blue-50 rounded-full -mr-8 -mt-8 opacity-50"></div>
            <h3 class="text-sm font-bold text-gray-400 uppercase tracking-wide z-10 flex items-center">
              Links Created
            </h3>
            <div class="flex items-end justify-between z-10">
                <span class="text-4xl font-extrabold text-gray-900"><?php echo $links_inserted; ?></span>
              <span class="text-xs font-bold px-2 py-1 rounded-lg <?php echo esc_attr($insert_badge[1]); ?>">
                <?php echo esc_html($insert_badge[0]); ?>
              </span>
            </div>
            </div>
        </div>

        <!-- Bottom row -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 pb-12">

            <!-- Recommended Actions -->
            <div class="lg:col-span-2 space-y-4">
            <h3 class="text-lg font-bold text-gray-900 mb-2 flex items-center">
                Recommended Actions
                <span class="ml-3 bg-red-100 text-red-600 text-xs font-bold px-2 py-0.5 rounded-full">
                    <?php echo (int) $serious; ?> Serious
                </span>
            </h3>

            <?php
                // 5) Render actions
                Wpil_Dashboard::wpil_dash_render_recommended_actions($recommended_actions);
            ?>
            </div><!-- /Recommended Actions -->

          <!-- Notification Hub -->
          <div class="space-y-4">
            <h3 class="text-lg font-bold text-gray-900 mb-2">Notification Hub</h3>

            <div class="bg-white p-5 rounded-xl border border-gray-200">
              <?php include 'notification_hub.php'; ?>
            </div>

            <!-- Optional promo card -->
            <div class="hidden bg-gradient-to-br from-purple-50 to-blue-50 p-5 rounded-xl border border-purple-100 relative overflow-hidden">
              <div class="flex items-start space-x-3 relative z-10">
                <div class="bg-white p-1.5 rounded-lg shadow-sm text-[#7F5AF0]">
                  <span class="font-black">⚡</span>
                </div>
                <div>
                  <h4 class="font-bold text-gray-900 text-sm">Running low on AI Credits?</h4>
                  <p class="text-xs text-gray-600 mt-1 leading-relaxed">Power up your content analysis with our new AI models.</p>
                  <a href="#" class="inline-block mt-3 text-xs font-bold text-[#7F5AF0] hover:underline">Get 50% Off Refill →</a>
                  <!--TO-IMPLEMENT: real link and logic -->
                </div>
              </div>
            </div>
          </div>

        </div>
      </div><!-- /max-w-7xl -->

</div>
</div>
</div>
<?php include_once 'fix-modal.php'; ?>
<?php include_once 'wizard/credits-modal.php'; ?>
</div>
<script>
  window.WPIL_AI_CREDITS = <?php echo (int) Wpil_AI::get_available_ai_credits(); ?>;
  window.WPIL_AI_FIX_NONCE = '<?php echo esc_js(wp_create_nonce('wpil_ai_fix_nonce')); ?>';
</script>
<script>
(function() {
  let lastFixContext = null;

  function wpilParseInt(val) {
    if (val === undefined || val === null) return 0;
    const s = String(val).replace(/,/g, '').trim();
    const n = parseInt(s, 10);
    return isNaN(n) ? 0 : n;
  }

  function wpilFormatInt(n) {
    return wpilParseInt(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
  }

  function setAll(selector, value) {
    document.querySelectorAll(selector).forEach(function(el) {
      el.textContent = value;
    });
  }

  function padShortfall(shortfall) {
    let padded = Math.ceil(shortfall * 1.25);
    padded = Math.round(padded / 100) * 100;
    if (padded < shortfall) padded += 100;
    if (padded < 500) padded = 500;
    return padded;
  }

  function openFixModal(ctx) {
    lastFixContext = ctx;

    const estimate = wpilParseInt(ctx.estimate);
    const balance = wpilParseInt(ctx.balance);

    document.getElementById('wpil-fix-description').textContent = ctx.description || '';
    setAll('[data-wpil-fix-estimate]', wpilFormatInt(estimate));
    setAll('[data-wpil-fix-balance]', wpilFormatInt(balance));

    const enoughCredits = (estimate <= 0) ? true : (balance >= estimate);

    const statusBadge = document.querySelector('[data-role="wpil-fix-status"]');
    if (statusBadge) {
      statusBadge.textContent = enoughCredits ? 'Ready' : 'Not enough credits';
      statusBadge.classList.toggle('is-bad', !enoughCredits);
    }

    const bar = document.querySelector('[data-role="wpil-fix-bar"]');
    if (bar) {
      const pct = (estimate > 0) ? Math.min(100, Math.round((balance / estimate) * 100)) : 100;
      bar.style.width = pct + '%';
    }

    document.getElementById('wpil-fix-warning').classList.toggle('hidden', enoughCredits);
    document.getElementById('wpil-fix-actions-enough').classList.toggle('hidden', !enoughCredits);
    document.getElementById('wpil-fix-actions-short').classList.toggle('hidden', enoughCredits);

    if (!enoughCredits) {
      const shortfall = Math.max(0, estimate - balance);
      const padded = padShortfall(shortfall);
      setAll('[data-wpil-fix-shortfall]', wpilFormatInt(padded));

      const buyBtn = document.getElementById('wpil-fix-buy');
      buyBtn.dataset.credits = padded;
      buyBtn.dataset.quantity = padded;
    }

    const modal = document.getElementById('wpil-fix-modal');
    modal.classList.remove('hidden');
    modal.setAttribute('aria-hidden', 'false');
  }

  function closeFixModal() {
    const modal = document.getElementById('wpil-fix-modal');
    modal.classList.add('hidden');
    modal.setAttribute('aria-hidden', 'true');
  }

  document.addEventListener('click', function(e) {
    const fixBtn = e.target.closest('[data-wpil-fix-type]');
    if (!fixBtn) return;

    e.preventDefault();

    const ctx = {
      type: fixBtn.dataset.wpilFixType,
      itemId: fixBtn.dataset.wpilFixItemId || '',
      estimate: parseInt(fixBtn.dataset.wpilFixEstimate, 10),
      description: fixBtn.dataset.wpilFixDescription || '',
      balance: window.WPIL_AI_CREDITS || 0
    };

    openFixModal(ctx);
  });

  document.querySelectorAll('[data-wpil-fix-cancel]').forEach(function(btn) {
    btn.addEventListener('click', closeFixModal);
  });

  document.getElementById('wpil-fix-begin')
    ?.addEventListener('click', function() {
      closeFixModal();

      // START FIX PROCESS HERE
      // You already have this pattern in scanning.php
      if(window.wpilAiFixRunner && typeof window.wpilAiFixRunner.start === 'function'){
        window.wpilAiFixRunner.start(lastFixContext);
      }
    });

  if (window.jQuery) {
    jQuery(document).on('lwcc:paid', function() {
      if (!lastFixContext) return;

      const buyBtn = document.getElementById('wpil-fix-buy');
      const purchase = wpilParseInt(buyBtn ? buyBtn.dataset.credits : 0);

      if (purchase > 0) {
        window.WPIL_AI_CREDITS = wpilParseInt(window.WPIL_AI_CREDITS) + purchase;
      }

      lastFixContext.balance = window.WPIL_AI_CREDITS || 0;
      openFixModal(lastFixContext);
    });
  }
})();
</script>
