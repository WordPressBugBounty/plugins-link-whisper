<?php
$filters = isset($panel_data['filters']) && is_array($panel_data['filters']) ? $panel_data['filters'] : array();
$rows = isset($panel_data['rows']) && is_array($panel_data['rows']) ? $panel_data['rows'] : array();
$total_count = isset($panel_data['total_count']) ? (int) $panel_data['total_count'] : 0;
$total_pages = isset($panel_data['total_pages']) ? max(1, (int) $panel_data['total_pages']) : 1;
$current_page = isset($panel_data['current_page']) ? max(1, (int) $panel_data['current_page']) : 1;
$per_page = isset($panel_data['per_page']) ? (int) $panel_data['per_page'] : 10;
$event_options = isset($filters['event_options']) && is_array($filters['event_options']) ? $filters['event_options'] : array();
$selected_events = isset($filters['events']) && is_array($filters['events']) ? array_map('strval', $filters['events']) : array();
$from = isset($filters['from']) ? (string) $filters['from'] : '';
$to = isset($filters['to']) ? (string) $filters['to'] : '';
$view = isset($filters['view']) ? (string) $filters['view'] : 'task';
$from_label = isset($filters['from_label']) ? (string) $filters['from_label'] : $from;
$to_label = isset($filters['to_label']) ? (string) $filters['to_label'] : $to;
$event_filter_label = ('task' === $view) ? __('AI Tasks', 'wpil') : __('AI Events', 'wpil');
$start_row = ($total_count > 0) ? (($current_page - 1) * $per_page) + 1 : 0;
$end_row = min($total_count, $current_page * $per_page);
?>
<div class="ai-usage-panel" data-wpil-ai-history-panel data-current-page="<?php echo esc_attr($current_page); ?>" data-current-view="<?php echo esc_attr($view); ?>">
  <div class="ai-usage-header">
    <div>
      <h3 class="ai-usage-title"><?php esc_html_e('AI Credit History', 'wpil'); ?></h3>
      <p class="ai-usage-subtitle"><?php echo esc_html(sprintf(__('Showing %1$s records from %2$s to %3$s.', 'wpil'), number_format_i18n($total_count), $from_label, $to_label)); ?></p>
    </div>

    <form class="ai-usage-filter-form" data-wpil-ai-history-form>
      <div class="ai-usage-filter-main">
        <div class="ai-usage-filter-row ai-usage-filter-row-top">
          <div class="ai-usage-field">
            <label for="ai-usage-from"><?php esc_html_e('From', 'wpil'); ?></label>
            <input id="ai-usage-from" type="date" name="ai_usage_from" value="<?php echo esc_attr($from); ?>">
          </div>
          <div class="ai-usage-field">
            <label for="ai-usage-to"><?php esc_html_e('To', 'wpil'); ?></label>
            <input id="ai-usage-to" type="date" name="ai_usage_to" value="<?php echo esc_attr($to); ?>">
          </div>
        </div>

        <div class="ai-usage-filter-row ai-usage-filter-row-events">
          <div class="ai-usage-field ai-usage-field-events">
            <label for="ai-usage-events"><?php echo esc_html($event_filter_label); ?></label>
            <select id="ai-usage-events" name="ai_usage_events[]" multiple size="4" data-wpil-ai-history-events>
              <?php foreach($event_options as $event_code => $event_label): ?>
                <option value="<?php echo esc_attr($event_code); ?>" <?php selected(in_array((string) $event_code, $selected_events, true)); ?>><?php echo esc_html($event_label); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="ai-usage-field ai-usage-field-view">
            <label for="ai-usage-view-toggle"><?php //esc_html_e('View', 'wpil'); ?></label>
            <label class="ai-usage-view-toggle" for="ai-usage-view-toggle">
              <span class="ai-usage-view-label"><?php esc_html_e('Individual Entries', 'wpil'); ?></span>
              <span class="ai-usage-view-switch">
                <input id="ai-usage-view-toggle" type="checkbox" data-wpil-ai-history-view-toggle <?php checked('task', $view); ?>>
                <span class="ai-usage-view-slider" aria-hidden="true"></span>
              </span>
              <span class="ai-usage-view-label"><?php esc_html_e('Task Totals', 'wpil'); ?></span>
            </label>
          </div>
      </div>

      <div class="ai-usage-actions">
        <button class="ai-usage-btn ai-usage-btn-primary" type="button" data-wpil-ai-history-apply><?php esc_html_e('Filter', 'wpil'); ?></button>
        <button class="ai-usage-btn" type="button" data-wpil-ai-history-export><?php esc_html_e('Export CSV', 'wpil'); ?></button>
        <button
          class="ai-usage-btn ai-usage-sync-btn"
          type="button"
          data-wpil-ai-history-sync
          data-tooltip="<?php echo esc_attr__('Click this if you don\'t see recent AI credit purchases showing up in the AI credit history.', 'wpil'); ?>"
          title="<?php echo esc_attr__('Click this if you don\'t see recent AI credit purchases showing up in the AI credit history.', 'wpil'); ?>"
          aria-label="<?php echo esc_attr__('Refresh recent AI credit purchases', 'wpil'); ?>">
          <span class="dashicons dashicons-update"></span>
        </button>
      </div>
    </form>
  </div>

  <div class="ai-usage-table-wrap">
    <?php if(!empty($rows)): ?>
    <table class="ai-usage-table">
      <thead>
        <tr>
          <th><?php esc_html_e('Date', 'wpil'); ?></th>
          <th><?php esc_html_e('AI Event', 'wpil'); ?></th>
          <th class="num"><?php esc_html_e('Credit change', 'wpil'); ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($rows as $usage_row): ?>
          <?php
            $process_time = !empty($usage_row['process_time']) ? (int) $usage_row['process_time'] : 0;
            $display_time = Wpil_Export::format_ai_credit_history_date($process_time);
            $process_name = Wpil_Export::get_ai_credit_history_event_name($usage_row);
            $credit_display = Wpil_Export::format_ai_credit_history_change($usage_row);
          ?>
          <tr>
            <td class="mono"><?php echo esc_html($display_time); ?></td>
            <td><?php echo esc_html($process_name); ?></td>
            <td class="num"><?php echo esc_html($credit_display); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
    <div class="ai-usage-empty"><?php esc_html_e('No AI credit records were found for this date range.', 'wpil'); ?></div>
    <?php endif; ?>
  </div>

  <div class="ai-usage-foot">
    <div class="ai-usage-count">
      <?php echo esc_html(sprintf(__('Showing %1$s-%2$s of %3$s', 'wpil'), number_format_i18n($start_row), number_format_i18n($end_row), number_format_i18n($total_count))); ?>
    </div>
    <div class="ai-usage-pagination">
      <?php if($current_page > 1): ?>
        <button class="ai-usage-btn" type="button" data-wpil-ai-history-page="<?php echo esc_attr(max(1, $current_page - 1)); ?>"><?php esc_html_e('Previous', 'wpil'); ?></button>
      <?php else: ?>
        <span class="ai-usage-btn ai-usage-btn-disabled"><?php esc_html_e('Previous', 'wpil'); ?></span>
      <?php endif; ?>
      <span class="ai-usage-page-label"><?php echo esc_html(sprintf(__('Page %1$s of %2$s', 'wpil'), number_format_i18n($current_page), number_format_i18n($total_pages))); ?></span>
      <?php if($current_page < $total_pages): ?>
        <button class="ai-usage-btn" type="button" data-wpil-ai-history-page="<?php echo esc_attr(min($total_pages, $current_page + 1)); ?>"><?php esc_html_e('Next', 'wpil'); ?></button>
      <?php else: ?>
        <span class="ai-usage-btn ai-usage-btn-disabled"><?php esc_html_e('Next', 'wpil'); ?></span>
      <?php endif; ?>
    </div>
  </div>
</div>
