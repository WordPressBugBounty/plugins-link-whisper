<?php
/**
 * Custom CSV Linking Map – admin page template
 *
 * Variables available:
 *   $summary  – array from Wpil_CsvLinkMap::get_plan_summary(), or null if no plan.
 */

if (!defined('ABSPATH')) { exit; }

$has_plan     = !empty($summary) && !empty($summary['has_plan']);
$parse_active = !empty($summary['parse_status']) && $summary['parse_status'] === 'running';
$nonce        = wp_create_nonce('wpil_csv_link_map_nonce');
$ai_nonce     = wp_create_nonce(get_current_user_id() . 'wpil_ai_fix_nonce');
$process_key  = Wpil_CsvLinkMap::get_process_key();
$dashboard_url = admin_url('admin.php?page=link_whisper');
$template_rows = Wpil_CsvLinkMap::get_example_template_rows();
$template_filename = Wpil_CsvLinkMap::get_example_template_filename();
?>
<div class="wrap wpil_styles">

<div style="max-width:860px; margin:0 auto; padding:24px 0;">

    <!-- Page header -->
    <h1 style="font-size:1.6rem; font-weight:700; margin-bottom:4px;">Custom CSV Linking Map</h1>
    <p style="color:#666; margin-bottom:28px;">
        Upload a CSV that tells Link Whisper exactly which posts should link to which,
        then run the AI Linking engine over your custom plan.
    </p>

    <!-- ── CSV Format guide ─────────────────────────────────────────────── -->
    <div style="background:#f8f9fa; border:1px solid #ddd; border-radius:8px; padding:20px; margin-bottom:28px;">
        <h3 style="margin-top:0; font-size:1rem;">CSV Format</h3>
        <p style="margin-bottom:10px;">Your CSV must have a header row with (some of) these four column names:</p>
        <table style="border-collapse:collapse; width:100%; font-size:.88rem;">
            <thead>
                <tr style="background:#eee;">
                    <th style="text-align:left; padding:6px 10px; border:1px solid #ddd;">Column</th>
                    <th style="text-align:left; padding:6px 10px; border:1px solid #ddd;">Meaning</th>
                    <th style="text-align:left; padding:6px 10px; border:1px solid #ddd;">Blank?</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="padding:6px 10px; border:1px solid #ddd;"><strong>Inbound Link Posts</strong></td>
                    <td style="padding:6px 10px; border:1px solid #ddd;">The post that should <em>receive</em> inbound links (the target)</td>
                    <td style="padding:6px 10px; border:1px solid #ddd;">—</td>
                </tr>
                <tr style="background:#fafafa;">
                    <td style="padding:6px 10px; border:1px solid #ddd;"><strong>Inbound Source Post</strong></td>
                    <td style="padding:6px 10px; border:1px solid #ddd;">The post that should link <em>to</em> the Inbound target</td>
                    <td style="padding:6px 10px; border:1px solid #ddd;">LW finds sources automatically</td>
                </tr>
                <tr>
                    <td style="padding:6px 10px; border:1px solid #ddd;"><strong>Outbound Link Posts</strong></td>
                    <td style="padding:6px 10px; border:1px solid #ddd;">The post that should <em>contain</em> outbound links</td>
                    <td style="padding:6px 10px; border:1px solid #ddd;">—</td>
                </tr>
                <tr style="background:#fafafa;">
                    <td style="padding:6px 10px; border:1px solid #ddd;"><strong>Outbound Target Post</strong></td>
                    <td style="padding:6px 10px; border:1px solid #ddd;">The post the source should link <em>to</em></td>
                    <td style="padding:6px 10px; border:1px solid #ddd;">LW finds targets automatically</td>
                </tr>
            </tbody>
        </table>
        <p style="margin-top:12px; margin-bottom:12px; font-size:.85rem; color:#555;">
            Repeat a post URL on multiple rows to specify multiple inbound sources or outbound targets for it.
            A row may use inbound columns, outbound columns, or both.
        </p>
        <button type="button" id="wpil-csv-download-template" class="button button-secondary" style="font-size:.85rem;">
            &#8595; Download Example Template
        </button>
    </div>

    <!-- ── Upload form ──────────────────────────────────────────────────── -->
    <div style="background:#fff; border:1px solid #ddd; border-radius:8px; padding:24px; margin-bottom:28px;">
        <h3 style="margin-top:0; font-size:1rem;">
            <?php echo $has_plan ? 'Replace Current Plan' : 'Upload CSV'; ?>
        </h3>

        <div id="wpil-csv-upload-feedback" style="display:none; margin-bottom:14px; padding:10px 14px; border-radius:6px; font-size:.88rem;"></div>

        <form id="wpil-csv-upload-form" enctype="multipart/form-data">
            <?php wp_nonce_field('wpil_csv_link_map_nonce', '_wpnonce_csv', true, true); ?>
            <input type="hidden" name="action" value="wpil_csv_link_map_upload">
            <input type="hidden" name="nonce"  value="<?php echo esc_attr($nonce); ?>">

            <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                <input type="file" name="csv_file" id="wpil-csv-file-input" accept=".csv"
                       style="font-size:.9rem;">
                <button type="submit" id="wpil-csv-upload-btn"
                        class="button button-primary"
                        style="min-width:120px;"
                        disabled>
                    Upload &amp; Parse
                </button>
            </div>
        </form>
        <div id="wpil-csv-parse-progress" style="<?php echo $parse_active ? '' : 'display:none;'; ?> margin-top:14px; padding:12px 14px; border-radius:8px; border:1px solid #bfdbfe; background:#eff6ff;">
            <div style="display:flex; justify-content:space-between; gap:12px; margin-bottom:8px; font-size:.85rem; font-weight:700; color:#1d4ed8;">
                <span id="wpil-csv-progress-phase"><?php echo !empty($summary['parse_phase']) ? esc_html(ucwords(str_replace('_', ' ', $summary['parse_phase']))) : 'Parsing'; ?></span>
                <span id="wpil-csv-progress-percent"><?php echo !empty($summary['parse_progress']) ? (int) $summary['parse_progress'] : 0; ?>%</span>
            </div>
            <div style="height:8px; border-radius:999px; overflow:hidden; background:rgba(37,99,235,.15); margin-bottom:8px;">
                <div id="wpil-csv-progress-bar" style="height:100%; width:<?php echo !empty($summary['parse_progress']) ? (int) $summary['parse_progress'] : 0; ?>%; background:linear-gradient(90deg,#2563eb 0%,#60a5fa 100%);"></div>
            </div>
            <div id="wpil-csv-progress-copy" style="font-size:.88rem; color:#1e3a8a;"><?php echo !empty($summary['parse_message']) ? esc_html($summary['parse_message']) : 'Upload a CSV to begin building the preview map.'; ?></div>
        </div>
    </div>

    <!-- ── Current plan summary ─────────────────────────────────────────── -->
    <div id="wpil-csv-plan-summary" style="<?php echo $has_plan ? '' : 'display:none;'; ?>">
        <div style="background:#fff; border:1px solid #c3e6cb; border-radius:8px; padding:24px; margin-bottom:28px;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px;">
                <div>
                    <h3 style="margin-top:0; font-size:1rem; color:#155724;">Plan Uploaded</h3>
                    <div id="wpil-csv-stats" style="font-size:.9rem; color:#333;">
                        <?php if ($has_plan): ?>
                        <div style="display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:14px;">
                            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:14px;">
                                <div style="font-size:.72rem; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#64748b; margin-bottom:6px;">Source Posts</div>
                                <div style="font-size:1.5rem; font-weight:700; color:#0f172a;"><?php echo (int) $summary['source_posts_exact']; ?></div>
                                <div style="font-size:.82rem; color:#64748b;">Exact unique posts that may place links after preview building.</div>
                            </div>
                            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:14px;">
                                <div style="font-size:.72rem; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#64748b; margin-bottom:6px;">Link Targets</div>
                                <div style="font-size:1.5rem; font-weight:700; color:#0f172a;"><?php echo (int) $summary['target_posts_exact']; ?></div>
                                <div style="font-size:.82rem; color:#64748b;">This is the estimated number of posts that will get links pointed to them.</div>
                            </div>
                            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:14px;">
                                <div style="font-size:.72rem; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#64748b; margin-bottom:6px;">Potential Links</div>
                                <div style="font-size:1.5rem; font-weight:700; color:#0f172a;"><?php echo ((int) $summary['potential_links_min'] === 0 && (int) $summary['potential_links_max'] === 0) ? '0' : ((int) $summary['potential_links_min'] . '-' . (int) $summary['potential_links_max']); ?></div>
                                <div style="font-size:.82rem; color:#64748b;">This is the estimated number of links that this plan will generate.</div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <a href="<?php echo esc_url($dashboard_url); ?>" class="button button-primary">
                        Go to Dashboard to Run
                    </a>
                    <button type="button" id="wpil-csv-clear-btn" class="button button-secondary"
                            style="color:#b94a48;">
                        Clear Plan
                    </button>
                </div>
            </div>
        </div>

        <?php if ($has_plan && !empty($summary['parse_errors'])): ?>
        <div style="background:#fff3cd; border:1px solid #ffe69c; border-radius:8px; padding:18px 20px; margin-bottom:28px; color:#664d03;">
            <h3 style="margin:0 0 10px; font-size:1rem;">Import Warnings</h3>
            <div style="font-size:.88rem; line-height:1.5;">
                <?php foreach (array_slice($summary['parse_errors'], 0, 5) as $warning): ?>
                    <div><?php echo esc_html($warning); ?></div>
                <?php endforeach; ?>
                <?php if (count($summary['parse_errors']) > 5): ?>
                    <div>Plus <?php echo (int) (count($summary['parse_errors']) - 5); ?> more warning<?php echo count($summary['parse_errors']) - 5 !== 1 ? 's' : ''; ?>.</div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── No plan placeholder ──────────────────────────────────────────── -->
    <div id="wpil-csv-no-plan" style="<?php echo $has_plan ? 'display:none;' : ''; ?>">
        <div style="background:#f8f9fa; border:1px solid #ddd; border-radius:8px; padding:24px; text-align:center; color:#888;">
            No plan uploaded yet. Use the form above to get started.
        </div>
    </div>

</div><!-- /.wrap inner -->
</div><!-- /.wrap -->

<script>
(function($){
    var nonce = <?php echo json_encode($nonce); ?>;
    var templateRows = <?php echo wp_json_encode($template_rows); ?>;
    var templateFilename = <?php echo wp_json_encode($template_filename); ?>;
    return;

    // ── Upload handler ────────────────────────────────────────────────────
    $('#wpil-csv-upload-form').on('submit', function(e){
        e.preventDefault();

        var file = $('#wpil-csv-file-input')[0].files[0];
        if(!file){
            showFeedback('Please choose a CSV file.', 'error');
            return;
        }

        var btn = $('#wpil-csv-upload-btn');
        btn.prop('disabled', true).text('Uploading…');

        var fd = new FormData();
        fd.append('action',   'wpil_csv_link_map_upload');
        fd.append('nonce',    nonce);
        fd.append('csv_file', file);

        $.ajax({
            url:         ajaxurl,
            type:        'POST',
            data:        fd,
            processData: false,
            contentType: false,
            success: function(resp){
                btn.prop('disabled', false).text('Upload & Parse');
                if(resp.success){
                    var d = resp.data;
                    showFeedback(
                        'Parsed ' + d.total_rows + ' relationships (' +
                        d.inbound_targets + ' inbound target' + (d.inbound_targets !== 1 ? 's' : '') + ', ' +
                        d.outbound_sources + ' outbound source' + (d.outbound_sources !== 1 ? 's' : '') + ').' +
                        (d.parse_errors && d.parse_errors.length
                            ? ' Warnings: ' + d.parse_errors.slice(0,3).join('; ')
                            : ''),
                        'success'
                    );
                    updateSummary(d);
                    $('#wpil-csv-plan-summary').show();
                    $('#wpil-csv-no-plan').hide();
                } else {
                    showFeedback(resp.data && resp.data.message ? resp.data.message : 'Upload failed.', 'error');
                }
            },
            error: function(){
                btn.prop('disabled', false).text('Upload & Parse');
                showFeedback('Network error — please try again.', 'error');
            }
        });
    });

    // ── Clear handler ─────────────────────────────────────────────────────
    $('#wpil-csv-clear-btn').on('click', function(){
        if(!confirm('Clear the current plan and all queued processing data?')){ return; }

        var btn = $(this);
        btn.prop('disabled', true).text('Clearing…');

        $.post(ajaxurl, {action: 'wpil_csv_link_map_clear', nonce: nonce}, function(resp){
            btn.prop('disabled', false).text('Clear Plan');
            if(resp.success){
                $('#wpil-csv-plan-summary').hide();
                $('#wpil-csv-no-plan').show();
                showFeedback('Plan cleared.', 'success');
            } else {
                showFeedback(resp.data && resp.data.message ? resp.data.message : 'Clear failed.', 'error');
            }
        });
    });

    // ── Template download ─────────────────────────────────────────────────
    $('#wpil-csv-download-template').on('click', function(){
        var siteUrl = <?php echo json_encode(rtrim(home_url(), '/')); ?>;
        var rows = [
            ['Inbound Link Posts', 'Inbound Source Post', 'Outbound Link Posts', 'Outbound Target Post'],
            // Inbound: specific source tells LW exactly which post should link to the target
            [siteUrl + '/target-post/', siteUrl + '/source-post/', '', ''],
            // Inbound: blank source → LW finds the best source automatically
            [siteUrl + '/another-target/', '', '', ''],
            // Outbound: specific target tells LW exactly where the source should link to
            ['', '', siteUrl + '/source-post/', siteUrl + '/destination-post/'],
            // Outbound: blank target → LW finds the best destination automatically
            ['', '', siteUrl + '/another-source/', ''],
            // Both columns on one row — LW handles both directions for this pair
            [siteUrl + '/pillar-post/', siteUrl + '/supporting-post/', siteUrl + '/supporting-post/', siteUrl + '/pillar-post/'],
        ];

        var csv = templateRows.map(function(r){
            return r.map(function(cell){
                // wrap in quotes if the cell contains a comma or quote
                if(cell.indexOf(',') !== -1 || cell.indexOf('"') !== -1){
                    return '"' + cell.replace(/"/g, '""') + '"';
                }
                return cell;
            }).join(',');
        }).join('\r\n');

        var blob = new Blob([csv], {type: 'text/csv;charset=utf-8;'});
        var url  = URL.createObjectURL(blob);
        var a    = document.createElement('a');
        a.href     = url;
        a.download = templateFilename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    });

    // ── Helpers ───────────────────────────────────────────────────────────
    function showFeedback(msg, type){
        var $el = $('#wpil-csv-upload-feedback');
        var bg  = (type === 'success') ? '#d4edda' : '#f8d7da';
        var co  = (type === 'success') ? '#155724' : '#721c24';
        var br  = (type === 'success') ? '#c3e6cb' : '#f5c6cb';
        $el.css({background: bg, color: co, border: '1px solid ' + br}).html(msg).show();
    }

    function updateSummary(d){
        var html =
            '<div style="display:flex;gap:32px;flex-wrap:wrap;">' +
            '<div><strong>' + d.inbound_targets + '</strong> inbound target' + (d.inbound_targets !== 1 ? 's' : '') +
            ' <span style="color:#888;font-size:.82rem;margin-left:4px;">(' + d.inbound_specified + ' specified source' + (d.inbound_specified !== 1 ? 's' : '') + ', ' + d.inbound_auto + ' auto)</span></div>' +
            '<div><strong>' + d.outbound_sources + '</strong> outbound source' + (d.outbound_sources !== 1 ? 's' : '') +
            ' <span style="color:#888;font-size:.82rem;margin-left:4px;">(' + d.outbound_specified + ' specified target' + (d.outbound_specified !== 1 ? 's' : '') + ', ' + d.outbound_auto + ' auto)</span></div>' +
            '<div><strong>' + d.total_rows + '</strong> total relationship' + (d.total_rows !== 1 ? 's' : '') + '</div>' +
            '</div>';
        $('#wpil-csv-stats').html(html);
    }
}(jQuery));
</script>
<script>
(function($){
    var nonce = <?php echo json_encode($nonce); ?>;
    var initialStatus = <?php echo wp_json_encode($summary ? $summary : []); ?>;
    var parsePollTimer = null;

    function stopPolling(){
        if(parsePollTimer){
            window.clearTimeout(parsePollTimer);
            parsePollTimer = null;
        }
    }

    function showFeedback(msg, type){
        var $el = $('#wpil-csv-upload-feedback');
        var bg  = (type === 'success') ? '#d4edda' : '#f8d7da';
        var co  = (type === 'success') ? '#155724' : '#721c24';
        var br  = (type === 'success') ? '#c3e6cb' : '#f5c6cb';
        $el.css({background: bg, color: co, border: '1px solid ' + br}).html(msg).show();
    }

    function escapeHtml(text){
        return String(text || '').replace(/[&<>"']/g, function(char){
            switch(char){
                case '&': return '&amp;';
                case '<': return '&lt;';
                case '>': return '&gt;';
                case '"': return '&quot;';
                case '\'': return '&#039;';
                default: return char;
            }
        });
    }

    function statCard(title, value, copy){
        return '<div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:14px;">' +
            '<div style="font-size:.72rem; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#64748b; margin-bottom:6px;">' + title + '</div>' +
            '<div style="font-size:1.5rem; font-weight:700; color:#0f172a;">' + value + '</div>' +
            '<div style="font-size:.82rem; color:#64748b;">' + copy + '</div>' +
        '</div>';
    }

    function syncUploadButtonState(){
        var file = $('#wpil-csv-file-input')[0].files[0];
        var status = window.WPIL_CSV_LINK_MAP_STATUS || {};
        $('#wpil-csv-upload-btn').prop('disabled', !file || status.parse_status === 'running');
    }

    function renderStatus(data){
        data = data || {};
        window.WPIL_CSV_LINK_MAP_STATUS = data;

        var parseActive = data.parse_status === 'running';
        var parseComplete = data.parse_status === 'complete';

        $('#wpil-csv-plan-summary').toggle(!!data.has_plan);
        $('#wpil-csv-no-plan').toggle(!data.has_plan && !parseActive);
        $('#wpil-csv-parse-progress').toggle(parseActive);
        $('#wpil-csv-progress-phase').text(((data.parse_phase || 'parsing') + '').replace(/_/g, ' ').replace(/\b\w/g, function(char){ return char.toUpperCase(); }));
        $('#wpil-csv-progress-percent').text((parseInt(data.parse_progress, 10) || 0) + '%');
        $('#wpil-csv-progress-bar').css('width', (parseInt(data.parse_progress, 10) || 0) + '%');
        $('#wpil-csv-progress-copy').text(data.parse_message || 'Upload a CSV to begin building the preview map.');

        $('#wpil-csv-stats').html(
            '<div style="display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:14px;">' +
                statCard('Source Posts', parseComplete ? escapeHtml(String(parseInt(data.source_posts_exact, 10) || 0)) : '...', parseComplete ? 'Exact unique posts that may place links after preview building.' : 'Calculated after the preview map finishes building.') +
                statCard('Link Targets', parseComplete ? escapeHtml(String(parseInt(data.target_posts_exact, 10) || 0)) : '...', parseComplete ? 'This is the estimated number of posts that will get links pointed to them.' : 'Calculated after the preview map finishes building.') +
                statCard('Potential Links', parseComplete ? (((parseInt(data.potential_links_min, 10) || 0) === 0 && (parseInt(data.potential_links_max, 10) || 0) === 0) ? '0' : (escapeHtml(String(parseInt(data.potential_links_min, 10) || 0)) + '-' + escapeHtml(String(parseInt(data.potential_links_max, 10) || 0)))) : '...', parseComplete ? 'This is the estimated number of links that this plan will generate.' : 'The final range is calculated from each completed preview relation.') +
            '</div>'
        );

        syncUploadButtonState();

        if(parseActive){
            stopPolling();
            parsePollTimer = window.setTimeout(function(){
                $.post(ajaxurl, {action: 'wpil_csv_link_map_parse_step', nonce: nonce}, function(resp){
                    if(resp && resp.success && resp.data){
                        renderStatus(resp.data);
                        return;
                    }

                    stopPolling();
                    showFeedback(resp && resp.data && resp.data.message ? resp.data.message : 'Unable to continue building the preview map.', 'error');
                }).fail(function(){
                    stopPolling();
                    showFeedback('Unable to continue building the preview map.', 'error');
                });
            }, 900);
        }else{
            stopPolling();
        }
    }

    $('#wpil-csv-file-input').off('change').on('change', syncUploadButtonState);

    $('#wpil-csv-upload-form').off('submit').on('submit', function(e){
        e.preventDefault();

        var file = $('#wpil-csv-file-input')[0].files[0];
        if(!file){
            showFeedback('Please choose a CSV file.', 'error');
            return;
        }

        var btn = $('#wpil-csv-upload-btn');
        btn.prop('disabled', true).text('Uploading...');

        var fd = new FormData();
        fd.append('action', 'wpil_csv_link_map_upload');
        fd.append('nonce', nonce);
        fd.append('csv_file', file);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false
        }).done(function(resp){
            if(resp && resp.success && resp.data){
                renderStatus(resp.data);
                showFeedback('Upload received. Link Whisper is now parsing the CSV and building the preview map.', 'success');
                return;
            }

            showFeedback(resp && resp.data && resp.data.message ? resp.data.message : 'Upload failed.', 'error');
        }).fail(function(){
            showFeedback('Network error. Please try again.', 'error');
        }).always(function(){
            btn.prop('disabled', false).text('Upload & Parse');
            syncUploadButtonState();
        });
    });

    $('#wpil-csv-clear-btn').off('click').on('click', function(){
        if(!confirm('Clear the current plan and all queued processing data?')){ return; }

        var btn = $(this);
        btn.prop('disabled', true).text('Clearing...');

        $.post(ajaxurl, {action: 'wpil_csv_link_map_clear', nonce: nonce}, function(resp){
            btn.prop('disabled', false).text('Clear Plan');
            if(resp && resp.success){
                stopPolling();
                renderStatus({
                    has_plan: false,
                    parse_status: 'idle',
                    parse_progress: 0,
                    parse_message: '',
                    parse_errors: [],
                    source_posts_exact: 0,
                    target_posts_exact: 0,
                    potential_links_min: 0,
                    potential_links_max: 0
                });
                showFeedback('Plan cleared.', 'success');
            } else {
                showFeedback(resp && resp.data && resp.data.message ? resp.data.message : 'Clear failed.', 'error');
            }
        });
    });

    renderStatus(initialStatus);
}(jQuery));
</script>
