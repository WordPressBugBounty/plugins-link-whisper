<div class="wrap wpil-report-page wpil_styles wpil-lists wpil_post_links_count_update_page">
    <br>
    <a href="<?=esc_url(admin_url("admin.php?page=link_whisper"))?>" class="page-title-action"><?php esc_html_e('Return to Report', 'wpil'); ?></a>
    <h1 class='wp-heading-inline'>Updating links stats for <?=$post->type?> #<?=$post->id?>, `<?=esc_html($post->getTitle())?>`</h1>
    <p>
        <a href="<?=esc_url($post->getLinks()->edit)?>" target="_blank">[<?php esc_html_e('edit', 'wpil'); ?>]</a>
        <a href="<?=esc_url($post->getLinks()->view)?>" target="_blank">[<?php esc_html_e('view', 'wpil'); ?>]</a>
        <a href="<?=esc_url($post->getLinks()->export)?>" target="_blank">[<?php esc_html_e('export', 'wpil'); ?>]</a>
    </p>
    <h2><?php esc_html_e('Previous data:', 'wpil'); ?></h2>
    <p><?php esc_html_e('Date of previous analysis: ', 'wpil'); ?><?=!empty($prev_t) ? $prev_t : '- not set -'?></p>
    <ul>
        <li>
            <b><?php esc_html_e('Outbound internal links:', 'wpil'); ?></b> <?=$prev_count['outbound_internal']?>
        </li>
        <li>
            <b><?php esc_html_e('Inbound internal links:', 'wpil'); ?></b> <?=$prev_count['inbound_internal']?>
        </li>
        <li>
            <b><?php esc_html_e('Outbound external links:', 'wpil'); ?></b> <?=$prev_count['outbound_external']?>
        </li>
    </ul>

    <h2><?php esc_html_e('New data:', 'wpil'); ?></h2>
    <p><?php esc_html_e('Date of analysis: ', 'wpil'); ?><?=$new_time?></p>
    <p><?php esc_html_e('Time spent: ', 'wpil'); ?><?=number_format($time, 3)?> seconds</p>
    <ul>
        <li>
            <b><?php esc_html_e('Outbound internal links:', 'wpil'); ?></b> <?=$count['outbound_internal']?> (difference: <?=$count['outbound_internal'] - $prev_count['outbound_internal']?>)
        </li>
        <li>
            <b><?php esc_html_e('Inbound internal links:', 'wpil'); ?></b> <?=$count['inbound_internal']?> (difference: <?=$count['inbound_internal'] - $prev_count['inbound_internal']?>)
        </li>
        <li>
            <b><?php esc_html_e('Outbound external links:', 'wpil'); ?></b> <?=$count['outbound_external']?> (difference: <?=$count['outbound_external'] - $prev_count['outbound_external']?>)
        </li>
    </ul>

    <h3><?php echo sprintf(__('Outbound internal links %slink count: %s%s', 'wpil'), '(', $count['outbound_internal'], ')');?></h3>
    <ul>
        <?php foreach ($links_data['outbound_internal'] as $link) : ?>
            <li>
                <a href="<?=esc_url($link->url)?>" target="_blank" style="text-decoration: underline">
                    <?=esc_url($link->url)?><br> <b>[<?=esc_attr($link->anchor)?>]</b>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <h3><?php echo sprintf(__('Inbound internal links %slink count: %s%s', 'wpil'), '(', $count['inbound_internal'], ')');?></h3>
    <ul>
        <?php foreach ($links_data['inbound_internal'] as $link) : ?>
            <li>
                [<?=$link->post->id?>] <?=$link->post->getTitle()?> <b>[<?=esc_attr($link->anchor)?>]</b>
                <br>
                <a href="<?=esc_url($link->post->getLinks()->edit)?>" target="_blank">[<?php esc_html_e('edit', 'wpil'); ?>]</a>
                <a href="<?=esc_url($link->post->getLinks()->view)?>" target="_blank">[<?php esc_html_e('view', 'wpil'); ?>]</a>
                <br>
                <br>
            </li>
        <?php endforeach; ?>
    </ul>

    <h3><?php echo sprintf(__('Outbound external links %slink count: %s%s', 'wpil'), '(', $count['outbound_external'], ')');?></h3>
    <ul>
        <?php foreach ($links_data['outbound_external'] as $link) : ?>
            <li>
                <a href="<?=esc_url($link->url)?>" target="_blank" style="text-decoration: underline">
                    <?=esc_url($link->url)?>
                    <br>
                    <b>[<?=esc_attr($link->anchor)?>]</b>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
