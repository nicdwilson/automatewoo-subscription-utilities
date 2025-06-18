jQuery(document).ready(function($) {
    // Handle "Mark Resolved" button clicks
    $('.remove-overdue').on('click', function(e) {
        e.preventDefault();
        
        var subscriptionId = $(this).data('subscription-id');
        var button = $(this);
        
        if (confirm('Are you sure you want to mark this subscription as resolved? This will remove it from the overdue list.')) {
            button.prop('disabled', true).text('Processing...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'automatewoo_subscription_utilities_remove_overdue',
                    subscription_id: subscriptionId,
                    nonce: automatewoo_subscription_utilities.nonce
                },
                success: function(response) {
                    if (response.success) {
                        button.closest('tr').fadeOut(function() {
                            $(this).remove();
                            // Update count if it exists
                            var count = $('.count');
                            if (count.length) {
                                var currentCount = parseInt(count.text().replace(/[()]/g, ''));
                                if (currentCount > 1) {
                                    count.text('(' + (currentCount - 1) + ')');
                                } else {
                                    count.remove();
                                }
                            }
                        });
                    } else {
                        alert('Error: ' + (response.data || 'Unknown error occurred'));
                        button.prop('disabled', false).text('Mark Resolved');
                    }
                },
                error: function() {
                    alert('Error: Unable to process request');
                    button.prop('disabled', false).text('Mark Resolved');
                }
            });
        }
    });
    
    // Handle manual audit form submission
    $('form[action=""]').on('submit', function(e) {
        var submitButton = $(this).find('button[type="submit"]');
        submitButton.prop('disabled', true).text('Running Audit...');
        
        // Re-enable after a delay in case of errors
        setTimeout(function() {
            submitButton.prop('disabled', false).text('Run Manual Audit');
        }, 30000);
    });
}); 