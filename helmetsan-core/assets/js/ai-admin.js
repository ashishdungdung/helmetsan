/**
 * AI admin: Test provider connection (AJAX).
 */
(function ($) {
    'use strict';

    $(function () {
        $(document).on('click', '.helmetsan-ai-test-btn', function () {
            var $btn = $(this);
            var providerId = $btn.data('provider-id');
            var $cell = $btn.closest('td.helmetsan-ai-test-cell');
            var $result = $cell.find('.helmetsan-ai-test-result');

            if (! providerId || ! window.helmetsanAi || ! window.helmetsanAi.nonce) {
                return;
            }

            $result.empty().append('<span class="helmetsan-ai-status" style="color:#666;">…</span>');
            $btn.prop('disabled', true);

            $.post(window.helmetsanAi.ajaxUrl, {
                action: 'helmetsan_ai_test_provider',
                nonce: window.helmetsanAi.nonce,
                provider_id: providerId
            })
                .done(function (r) {
                    $result.empty();
                    if (r.success) {
                        var msg = (r.data && r.data.message) ? r.data.message : 'Working';
                        $result.append('<span class="helmetsan-ai-status ok" style="color:#00a32a;">✓ ' + msg + '</span>');
                    } else {
                        var errMsg = (r.data && r.data.message) ? r.data.message : 'Test failed';
                        $result.append('<span class="helmetsan-ai-status fail" style="color:#d63638;">✗ ' + errMsg + '</span>');
                    }
                })
                .fail(function (xhr) {
                    var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message)
                        ? xhr.responseJSON.data.message
                        : (xhr.statusText || 'Request failed');
                    $result.empty().append('<span class="helmetsan-ai-status fail" style="color:#d63638;">✗ ' + msg + '</span>');
                })
                .always(function () {
                    $btn.prop('disabled', false);
                });
        });
    });
})(jQuery);
