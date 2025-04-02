(function(wp) {
    const { registerPlugin } = wp.plugins;
    const { PluginPostStatusInfo } = wp.editPost;
    const { Button, SelectControl } = wp.components;
    const { createElement, useState } = wp.element;
    const { useSelect, useDispatch } = wp.data;
    const apiFetch = wp.apiFetch;

    // Configure apiFetch with the REST nonce
    apiFetch.use(apiFetch.createNonceMiddleware(qpfpBlockEditor.restNonce));
    apiFetch.use(apiFetch.createRootURLMiddleware(qpfpBlockEditor.restUrl));

    const QueuePostButton = () => {
        const [showOptions, setShowOptions] = useState(false);
        const [showSlots, setShowSlots] = useState(false);
        const [selectedSlot, setSelectedSlot] = useState('');
        const [isLoading, setIsLoading] = useState(false);
        const [availableSlots, setAvailableSlots] = useState([]);

        const { getCurrentPost, getCurrentPostId } = useSelect(select => ({
            getCurrentPost: () => select('core/editor').getCurrentPost(),
            getCurrentPostId: () => select('core/editor').getCurrentPostId()
        }), []);

        const { savePost } = useDispatch('core/editor');

        const postStatus = getCurrentPost()?.status;

        // Don't show for published or scheduled posts
        if (postStatus === 'publish' || postStatus === 'future') {
            return null;
        }

        const handleQueueNext = async function() {
            if (!confirm(qpfpBlockEditor.i18n.confirmQueue)) {
                return;
            }

            setIsLoading(true);
            try {
                const response = await apiFetch({
                    path: 'wp/v2/qpfp/queue',
                    method: 'POST',
                    data: {
                        post_id: getCurrentPostId()
                    }
                });

                if (response.success) {
                    alert(qpfpBlockEditor.i18n.queueSuccess.replace('%s', response.scheduled_time));
                    savePost();
                }
            } catch (error) {
                console.error('Failed to queue post:', error);
                alert(qpfpBlockEditor.i18n.queueError);
            } finally {
                setIsLoading(false);
                setShowOptions(false);
            }
        };

        const handlePickSlot = async function() {
            setIsLoading(true);
            try {
                const slots = await apiFetch({ path: 'wp/v2/qpfp/slots' });
                if (slots && slots.length > 0) {
                    setAvailableSlots(slots.map(slot => ({
                        value: slot.id.toString(),
                        label: slot.label
                    })));
                    setShowSlots(true);
                } else {
                    alert(qpfpBlockEditor.i18n.noSlots);
                }
            } catch (error) {
                console.error('Failed to fetch slots:', error);
                alert(qpfpBlockEditor.i18n.noSlots);
            } finally {
                setIsLoading(false);
            }
        };

        const handleConfirmSlot = async function() {
            if (!selectedSlot) return;

            setIsLoading(true);
            try {
                const response = await apiFetch({
                    path: 'wp/v2/qpfp/queue',
                    method: 'POST',
                    data: {
                        post_id: getCurrentPostId(),
                        slot_id: selectedSlot
                    }
                });

                if (response.success) {
                    alert(qpfpBlockEditor.i18n.queueSuccess.replace('%s', response.scheduled_time));
                    savePost();
                }
            } catch (error) {
                console.error('Failed to queue post:', error);
                alert(qpfpBlockEditor.i18n.queueError);
            } finally {
                setIsLoading(false);
                setShowSlots(false);
                setShowOptions(false);
                setSelectedSlot('');
            }
        };

        const handleCancel = function() {
            setShowOptions(false);
            setShowSlots(false);
            setSelectedSlot('');
        };

        if (showSlots) {
            return createElement(
                PluginPostStatusInfo,
                {},
                createElement(
                    'div',
                    { className: 'qpfp-options-container' },
                    createElement(
                        SelectControl,
                        {
                            label: qpfpBlockEditor.i18n.selectSlot,
                            value: selectedSlot,
                            options: [
                                { label: qpfpBlockEditor.i18n.chooseSlot, value: '' },
                                ...availableSlots
                            ],
                            onChange: (value) => setSelectedSlot(value),
                            disabled: isLoading
                        }
                    ),
                    createElement(
                        Button,
                        {
                            isPrimary: true,
                            onClick: handleConfirmSlot,
                            disabled: !selectedSlot || isLoading,
                            isBusy: isLoading,
                            className: 'qpfp-button qpfp-button-primary'
                        },
                        qpfpBlockEditor.i18n.queue
                    ),
                    createElement(
                        Button,
                        {
                            isSecondary: true,
                            onClick: handleCancel,
                            className: 'qpfp-button qpfp-button-cancel'
                        },
                        qpfpBlockEditor.i18n.cancel
                    )
                )
            );
        }

        if (showOptions) {
            return createElement(
                PluginPostStatusInfo,
                {},
                createElement(
                    'div',
                    { className: 'qpfp-options-container' },
                    createElement(
                        Button,
                        {
                            isPrimary: true,
                            onClick: handleQueueNext,
                            disabled: isLoading,
                            isBusy: isLoading,
                            className: 'qpfp-button qpfp-button-primary'
                        },
                        qpfpBlockEditor.i18n.queueForNext
                    ),
                    createElement(
                        Button,
                        {
                            isPrimary: true,
                            onClick: handlePickSlot,
                            disabled: isLoading,
                            isBusy: isLoading,
                            className: 'qpfp-button qpfp-button-primary'
                        },
                        qpfpBlockEditor.i18n.pickSlot
                    ),
                    createElement(
                        Button,
                        {
                            isSecondary: true,
                            onClick: handleCancel,
                            className: 'qpfp-button qpfp-button-cancel'
                        },
                        qpfpBlockEditor.i18n.cancel
                    )
                )
            );
        }

        return createElement(
            PluginPostStatusInfo,
            {},
            createElement(
                Button,
                {
                    isPrimary: true,
                    onClick: function() { setShowOptions(true); },
                    className: 'qpfp-button qpfp-button-primary'
                },
                qpfpBlockEditor.i18n.queueButton
            )
        );
    };

    registerPlugin('queue-posts-for-publication', {
        render: QueuePostButton,
        icon: 'clock'
    });
})(window.wp);
