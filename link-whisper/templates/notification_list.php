<?php
/**
 * Notification List Template
 * Renders the actual notification items
 */

if (empty($notifications) || !isset($notifications['data']) || empty($notifications['data'])) {
    echo '<div class="wpil-no-notifications" style="text-align: center; padding: 40px 20px; color: #666;">
            <p>No notifications available at the moment.</p>
            <p><small>Check back later for updates and announcements.</small></p>
          </div>';
    return;
}
?>

<div class="notification-list" style="margin-top: 0;">
    <?php foreach ($notifications['data'] as $notification): ?>
        <?php
        $has_url = !empty($notification['action_url']);
        $image_url = !empty($notification['image']) ? $notification['image'] : '';
        $title = !empty($notification['title']) ? $notification['title'] : 'Notification';
        $description = !empty($notification['description']) ? $notification['description'] : '';
        ?>
        
        <div class="notification-item-wrapper">
            <?php if ($has_url): ?>
                <a href="<?php echo $notification['action_url']; ?>" class="notification-item" target="_blank">
            <?php else: ?>
                <div class="notification-item notification-item-no-link">
            <?php endif; ?>
            
                <?php if ($image_url): ?>
                <div class="notification-cover-wrapper">
                    <img src="<?php echo $image_url; ?>"
                         alt="<?php echo esc_attr($title); ?>"
                         class="notification-cover-image"
                         onerror="this.style.display='none'">
                </div>
                <?php endif; ?>
                
                <div class="notification-content">
                    <h4><?php echo $title; ?></h4>
                    <?php if ($description): ?>
                        <p><?php echo $description; ?></p>
                    <?php endif; ?>
                </div>
            
            <?php if ($has_url): ?>
                </a>
            <?php else: ?>
            </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>