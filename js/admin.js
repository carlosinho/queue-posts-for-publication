jQuery(document).ready(function($) {
    // State management
    let isLoading = false;
    let selectedSlot = '';
    let availableSlots = [];

    // Get existing container
    const $submitBox = $('#submitdiv');
    if (!$submitBox.length) return;

    // Check if post is already scheduled or published
    const status = $('#original_post_status').val();
    if (status === 'future' || status === 'publish') return;

    // Find the last misc-pub-section
    const $lastSection = $('.misc-pub-section:last');
    if (!$lastSection.length) return;

    // Create the queue section HTML
    const $queueSection = $('<div/>', {
        class: 'misc-pub-section misc-pub-queue'
    }).append(
        $('<span/>', {
            class: 'dashicons dashicons-clock'
        }),
        $('<span/>', {
            class: 'misc-pub-section-label',
            text: 'Queue for publication:'
        }),
        $('<a/>', {
            href: '#qpfp-queue',
            class: 'edit-qpfp-queue hide-if-no-js',
            role: 'button'
        }).append(
            $('<span/>', {
                'aria-hidden': 'true',
                text: 'Edit'
            }),
            $('<span/>', {
                class: 'screen-reader-text',
                text: 'Edit queue options'
            })
        ),
        $('<div/>', {
            id: 'qpfp-queue-select',
            class: 'hide-if-js',
            style: 'display: none;'
        }).append(
            $('<div/>', {
                class: 'qpfp-queue-options'
            }).append(
                $('<button/>', {
                    type: 'button',
                    class: 'button button-primary qpfp-queue-next',
                    text: qpfpAdmin.i18n.queueForNext || 'Queue for next slot'
                }),
                $('<button/>', {
                    type: 'button',
                    class: 'button qpfp-pick-slot',
                    text: qpfpAdmin.i18n.pickSlot || 'Pick a slot'
                }),
                $('<button/>', {
                    type: 'button',
                    class: 'button-link qpfp-cancel-inline',
                    text: 'Cancel'
                })
            ),
            $('<div/>', {
                class: 'qpfp-slot-options',
                style: 'display: none;'
            }).append(
                $('<select/>', {
                    id: 'qpfp-slot-select',
                    class: 'qpfp-slot-select'
                }),
                $('<div/>').append(
                    $('<button/>', {
                        type: 'button',
                        class: 'button button-primary qpfp-save-queue',
                        text: 'OK'
                    }),
                    $('<button/>', {
                        type: 'button',
                        class: 'button-link qpfp-cancel-queue',
                        text: 'Cancel'
                    })
                )
            )
        )
    );

    // Add the section after the last misc-pub-section
    $lastSection.after($queueSection);

    // Helper function to fetch available slots
    async function fetchSlots() {
        try {
            const response = await $.post(qpfpAdmin.ajaxUrl, {
                action: 'qpfp_get_slots',
                _ajax_nonce: qpfpAdmin.nonce
            });

            if (!response.success) {
                throw new Error(response.data || 'Failed to fetch slots');
            }

            availableSlots = response.data;
            
            if (!Array.isArray(availableSlots) || availableSlots.length === 0) {
                alert(qpfpAdmin.i18n.noSlots || 'No slots available.');
                return;
            }

            const $select = $('#qpfp-slot-select').empty();
            $select.append($('<option/>', {
                value: '',
                text: qpfpAdmin.i18n.chooseSlot || 'Choose a slot...'
            }));
            availableSlots.forEach(slot => {
                $select.append($('<option/>', {
                    value: slot.id,
                    text: slot.label
                }));
            });
        } catch (error) {
            console.error('Failed to fetch slots:', error);
            alert(qpfpAdmin.i18n.noSlots || 'No slots available.');
            hideQueueSelect();
        }
    }

    // Helper function to queue post
    async function queuePost(slotId = null) {
        if (!confirm(qpfpAdmin.i18n.confirmQueue || 'Are you sure you want to queue this post for publication?')) {
            return;
        }

        isLoading = true;
        updateLoadingState();

        try {
            const postId = $('#post_ID').val();
            const response = await $.post(qpfpAdmin.ajaxUrl, {
                action: 'qpfp_queue_post',
                _ajax_nonce: qpfpAdmin.nonce,
                post_id: postId,
                slot_id: slotId
            });

            if (!response.success) {
                throw new Error(response.data || 'Failed to queue post');
            }

            alert((qpfpAdmin.i18n.queueSuccess || 'Post scheduled for %s').replace('%s', response.data.scheduled_time));
            window.location.reload();
        } catch (error) {
            console.error('Failed to queue post:', error);
            alert(qpfpAdmin.i18n.queueError || 'Failed to queue post.');
        } finally {
            isLoading = false;
            updateLoadingState();
            hideQueueSelect();
        }
    }

    // Helper function to update loading state
    function updateLoadingState() {
        const $buttons = $('.qpfp-queue-options button, .qpfp-slot-options button');
        $buttons.prop('disabled', isLoading);
        if (isLoading) {
            $buttons.addClass('updating-message');
        } else {
            $buttons.removeClass('updating-message');
        }
    }

    // Helper function to show queue select
    function showQueueSelect() {
        $('.edit-qpfp-queue').hide();
        $('#qpfp-queue-select').slideDown();
        $('.qpfp-queue-options').show();
        $('.qpfp-slot-options').hide();
    }

    // Helper function to hide queue select
    function hideQueueSelect() {
        $('#qpfp-queue-select').slideUp(function() {
            $('.edit-qpfp-queue').show();
        });
        selectedSlot = '';
    }

    // Event Handlers
    $(document).on('click', '.edit-qpfp-queue', function(e) {
        e.preventDefault();
        showQueueSelect();
    });

    $(document).on('click', '.qpfp-queue-next', function(e) {
        e.preventDefault();
        queuePost();
    });

    $(document).on('click', '.qpfp-pick-slot', async function(e) {
        e.preventDefault();
        isLoading = true;
        updateLoadingState();
        await fetchSlots();
        isLoading = false;
        updateLoadingState();
        $('.qpfp-queue-options').hide();
        $('.qpfp-slot-options').show();
    });

    $(document).on('change', '#qpfp-slot-select', function() {
        selectedSlot = $(this).val();
        $('.qpfp-save-queue').prop('disabled', !selectedSlot);
    });

    $(document).on('click', '.qpfp-save-queue', function(e) {
        e.preventDefault();
        if (selectedSlot) {
            queuePost(selectedSlot);
        }
    });

    $(document).on('click', '.qpfp-cancel-queue', function(e) {
        e.preventDefault();
        hideQueueSelect();
    });

    // Add handler for inline cancel button
    $(document).on('click', '.qpfp-cancel-inline', function(e) {
        e.preventDefault();
        hideQueueSelect();
    });

    // Initialize save button state
    $('.qpfp-save-queue').prop('disabled', true);
});
