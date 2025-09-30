<?php
/**
 * Notification Hub Template
 * Handles rendering notifications with caching and AJAX loading
 */

$has_cached_notifications = Wpil_Notification::has_cached_data();
?>

<div class="actioncolumn" style="height: 100%;">
    <h2 class="dashheadings">Notification Hub</h2>
    <div class="actioncontent">
        <div id="wpil-notification-container">
            <?php if ($has_cached_notifications): ?>
                <?php
                // Render notifications immediately if cached
                $notifications = Wpil_Notification::get_notifications();
                include 'notification_list.php';
                ?>
            <?php else: ?>
                <!-- Loading placeholder when no cache -->
                <div id="wpil-notification-loading">
                    <div class="wpil-notification-placeholder">
                        <div class="wpil-notification-spinner">
                            <div class="wpil-loader"></div>
                        </div>
                        <p>Loading notifications...</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script type="text/javascript">
// Global function to set up notification click tracking
function setupNotificationClickTracking() {
    jQuery(document).ready(function($) {
        // Set up click tracking for notification links
        $('#wpil-notification-container .notification-item').each(function(index) {
            var $notification = $(this);
            var notificationId = 'notification_' + index; // Generate ID based on position
            
            // Log impression when notification is visible
            if (window.wpilTelemetry) {
                window.wpilTelemetry.logNotificationHubNotificationImpression(notificationId, index);
            }
            
            // Track clicks on notification items
            $notification.on('click', function() {
                if (window.wpilTelemetry) {
                    window.wpilTelemetry.logNotificationHubNotificationClicked(notificationId);
                }
            });
        });
    });
}

// Set up telemetry for cached notifications (immediately available)
<?php if ($has_cached_notifications): ?>
jQuery(document).ready(function($) {
    // Log notification hub loaded event for cached notifications
    if (window.wpilTelemetry) {
        var notificationCount = $('#wpil-notification-container .notification-item-wrapper').length;
        window.wpilTelemetry.logNotificationHubLoaded(notificationCount, 0); // Assuming no unread count for cached
    }
    
    // Set up click tracking for cached notifications
    setupNotificationClickTracking();
});
<?php endif; ?>
</script>

<?php if (!$has_cached_notifications): ?>
<script type="text/javascript">
jQuery(document).ready(function($) {
    // Load notifications via AJAX when no cache exists
    $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'wpil_load_notifications',
            nonce: '<?php echo wp_create_nonce('wpil_load_notifications'); ?>'
        },
        success: function(response) {
            if (response.success) {
                $('#wpil-notification-container').html(response.data.html);
                
                // Log notification hub loaded event
                if (window.wpilTelemetry && response.data.telemetry) {
                    window.wpilTelemetry.logNotificationHubLoaded(
                        response.data.telemetry.notification_count || 0,
                        response.data.telemetry.unread_count || 0
                    );
                }
                
                // Set up click tracking for notification items
                setupNotificationClickTracking();
            } else {
                $('#wpil-notification-loading').html('<p>Unable to load notifications.</p>');
            }
        },
        error: function() {
            $('#wpil-notification-loading').html('<p>Error loading notifications.</p>');
        }
    });
});
</script>
<?php endif; ?>

<style type="text/css">
/* Loading placeholder styles */
.wpil-notification-placeholder {
    text-align: center;
    padding: 40px 20px;
    color: #666;
}

.wpil-notification-spinner {
    margin-bottom: 15px;
}

.wpil-loader {
    border: 3px solid #f3f3f3;
    border-top: 3px solid #0073aa;
    border-radius: 50%;
    width: 30px;
    height: 30px;
    animation: wpil-spin 1s linear infinite;
    margin: 0 auto;
}

@keyframes wpil-spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Notification list styles */
.notification-list {
    margin-top: 20px;
}
.notification-item-wrapper {
    border-bottom: 1px solid #e0e0e0;
    padding: 20px 0;
}
.notification-item-wrapper:last-child {
    border-bottom: none;
}
.notification-item {
    display: flex;
    align-items: flex-start;
    text-decoration: none;
    color: inherit;
    transition: all 0.2s ease;
    gap: 15px;
}
.notification-item:hover {
    opacity: 0.8;
}
.notification-item-no-link {
    cursor: default;
}
.notification-item-no-link:hover {
    opacity: 1;
}
.notification-content {
    flex: 1;
}
.notification-cover-wrapper {
    width: 120px;
    height: 90px;
    border-radius: 6px;
    overflow: hidden;
    flex-shrink: 0;
}
.notification-cover-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.notification-content h4 {
    margin: 0 0 5px 0;
    font-size: 16px;
    font-weight: 600;
    color: #333;
}
.notification-content p {
    margin: 0;
    font-size: 14px;
    color: #666;
    line-height: 1.4;
}
</style>