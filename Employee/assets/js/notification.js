document.addEventListener('DOMContentLoaded', function() {
            const notificationBtn = document.getElementById('notificationBtn');
            const notificationPanel = document.getElementById('notificationPanel');
            const notificationBadge = document.getElementById('notificationBadge');
            
            notificationBtn.addEventListener('click', function() {
                // Toggle notification panel
                notificationPanel.classList.toggle('hidden');
                
                // Hide the notification badge when panel is opened
                if (notificationBadge) {
                    notificationBadge.style.display = 'none';
                    
                    // Make an AJAX request to mark notifications as viewed
                    fetch('?mark_viewed=1', {
                        method: 'GET',
                        credentials: 'same-origin'
                    });
                }
            });
            
            // Close notification panel when clicking outside
            document.addEventListener('click', function(event) {
                const isClickInside = notificationPanel.contains(event.target) || 
                                     event.target.closest('button') === notificationBtn;
                
                if (!isClickInside && !notificationPanel.classList.contains('hidden')) {
                    notificationPanel.classList.add('hidden');
                }
            });
        });