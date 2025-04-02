jQuery(document).ready(function($) {
    const $queueButton = $('#qpfp-queue-button');
    const $dropdown = $('#qpfp-queue-dropdown');
    const $slotSelect = $('#qpfp-slot-select');
    const $confirmButton = $('#qpfp-confirm-queue');
    const $cancelButton = $('#qpfp-cancel-queue');

    // Show dropdown when queue button is clicked
    $queueButton.on('click', function() {
        $dropdown.show();
    });

    // Hide dropdown when cancel button is clicked
    $cancelButton.on('click', function() {
        $dropdown.hide();
        $slotSelect.val('');
    });

    // Handle queue confirmation
    $confirmButton.on('click', function() {
        const slotId = $slotSelect.val();
        if (!slotId) {
            alert(qpfpAdmin.i18n.selectSlot);
            return;
        }

        if (!confirm(qpfpAdmin.i18n.confirmQueue)) {
            return;
        }

        $.ajax({
            url: qpfpAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'qpfp_queue_post',
                nonce: qpfpAdmin.nonce,
                post_id: $('#post_ID').val(),
                slot_id: slotId
            },
            success: function(response) {
                if (response.success) {
                    alert(qpfpAdmin.i18n.queueSuccess);
                    $dropdown.hide();
                    $slotSelect.val('');
                } else {
                    alert(qpfpAdmin.i18n.queueError);
                }
            },
            error: function() {
                alert(qpfpAdmin.i18n.queueError);
            }
        });
    });

    // Close dropdown when clicking outside
    $(document).on('click', function(e) {
        if ($(e.target).closest('.qpfp-dropdown-content').length === 0) {
            $dropdown.hide();
        }
    });
}); 