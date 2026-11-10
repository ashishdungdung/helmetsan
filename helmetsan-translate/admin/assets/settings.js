(function($) {
    'use strict';

    $(document).ready(function() {
        // Tab Switching
        $('.hs-tabs a').on('click', function(e) {
            e.preventDefault();
            const target = $(this).attr('href');
            $('.hs-tabs a').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            $('.hs-tab-content').removeClass('hs-tab-active');
            $(target).addClass('hs-tab-active');
        });

        // Toggle Mask on Password fields
        $('.hs-toggle-mask').on('click', function() {
            const input = $(this).siblings('.hs-api-key-input');
            const icon = $(this).find('.dashicons');
            if (input.attr('type') === 'password') {
                input.attr('type', 'text');
                icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
            } else {
                input.attr('type', 'password');
                icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
            }
        });

        // Fetch Models AJAX
        $('.hs-fetch-models-btn').on('click', function(e) {
            e.preventDefault();
            const btn = $(this);
            const provider = btn.data('provider');
            const card = btn.closest('.hs-provider-card');
            const keyInput = card.find('.hs-api-key-input').val();
            const select = card.find('.hs-model-select');
            const originalText = btn.html();

            btn.prop('disabled', true).html('<span class="dashicons dashicons-update hs-spin"></span> Fetching...');

            $.ajax({
                url: hsTranslateData.ajax_url,
                type: 'POST',
                data: {
                    action: 'hs_fetch_provider_models',
                    nonce: hsTranslateData.nonce,
                    provider: provider,
                    api_key: keyInput
                },
                success: function(resp) {
                    btn.prop('disabled', false).html(originalText);
                    if (resp.success && resp.data.models) {
                        select.empty();
                        resp.data.models.forEach(function(m) {
                            select.append($('<option>', { value: m, text: m }));
                        });
                        alert(`Successfully fetched ${resp.data.count} models for ${provider}!`);
                    } else {
                        alert('Error fetching models: ' + (resp.data.message || 'Unknown error'));
                    }
                },
                error: function(xhr, status, err) {
                    btn.prop('disabled', false).html(originalText);
                    alert('Network error fetching models: ' + err);
                }
            });
        });

        // Test Connection AJAX
        $('.hs-test-conn-btn').on('click', function(e) {
            e.preventDefault();
            const btn = $(this);
            const provider = btn.data('provider');
            const card = btn.closest('.hs-provider-card');
            const statusEl = card.find('.hs-conn-status');

            statusEl.removeClass('hs-status-idle hs-status-online hs-status-error')
                    .addClass('hs-status-testing')
                    .text('Pinging...');

            btn.prop('disabled', true);

            $.ajax({
                url: hsTranslateData.ajax_url,
                type: 'POST',
                data: {
                    action: 'hs_test_provider_connection',
                    nonce: hsTranslateData.nonce,
                    provider: provider
                },
                success: function(resp) {
                    btn.prop('disabled', false);
                    if (resp.success) {
                        statusEl.removeClass('hs-status-testing')
                                .addClass('hs-status-online')
                                .text(`🟢 Online (${resp.data.latency}ms)`);
                    } else {
                        statusEl.removeClass('hs-status-testing')
                                .addClass('hs-status-error')
                                .text(`🔴 Error (${resp.data.latency || 0}ms)`);
                        alert(resp.data.message || 'Connection test failed');
                    }
                },
                error: function() {
                    btn.prop('disabled', false);
                    statusEl.removeClass('hs-status-testing')
                            .addClass('hs-status-error')
                            .text('🔴 Network Failed');
                }
            });
        });
    });
})(jQuery);
