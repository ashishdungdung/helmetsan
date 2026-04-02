/**
 * AI admin: Handles provider testing, healing reverts, and correction reviews.
 */
(function ($) {
    'use strict';

    $(function () {
        // --- 1. Test Provider ---
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

        // --- 2. Revert Heal ---
        $(document).on('click', '.hs-revert-heal', function (e) {
            e.preventDefault();
            var $btn = $(this);
            var id = $btn.data('id');

            if (!confirm('Are you sure you want to undo this AI repair? This will surgically restore the original values to the JSON file.')) {
                return;
            }

            $btn.prop('disabled', true).text('Undoing...');

            $.post(window.helmetsanAi.ajaxUrl, {
                action: 'helmetsan_ai_revert_heal',
                nonce: window.helmetsanAi.nonce,
                id: id
            })
            .done(function (r) {
                if (r.success) {
                    alert(r.data.message || 'Heal successfully reverted.');
                    location.reload();
                } else {
                    alert(r.data.message || 'Failed to revert heal.');
                    $btn.prop('disabled', false).text('Undo');
                }
            })
            .fail(function () {
                alert('Request failed.');
                $btn.prop('disabled', false).text('Undo');
            });
        });

        // --- 3. Review & Commit Correction ---
        $(document).on('click', '.hs-review-correction', function (e) {
            e.preventDefault();
            var $btn = $(this);
            var file = $btn.data('file');

            // Quick review fetch
            $.get(window.helmetsanAi.ajaxUrl, {
                action: 'helmetsan_ai_get_correction_diff',
                nonce: window.helmetsanAi.nonce,
                file: file
            })
            .done(function (r) {
                if (r.success) {
                    // Modern simple confirm logic with content preview
                    console.log('Target Correction Content:', r.data.content);
                    if (confirm('Reviewing staged fix for: ' + file + '\n\nClick OK to commit this correction to the master database.')) {
                        commitCorrection(file, $btn);
                    }
                } else {
                    alert(r.data.message || 'Failed to fetch correction.');
                }
            });
        });

        function commitCorrection(file, $btn) {
            $btn.prop('disabled', true).text('Committing...');

            $.post(window.helmetsanAi.ajaxUrl, {
                action: 'helmetsan_ai_commit_correction',
                nonce: window.helmetsanAi.nonce,
                file: file
            })
            .done(function (r) {
                if (r.success) {
                    alert(r.data.message || 'Correction committed.');
                    location.reload();
                } else {
                    // We know commit logic in AiAdmin.php currently returns error (placeholder)
                    alert(r.data.message || 'Failed to commit.');
                    $btn.prop('disabled', false).text('Review & Commit');
                }
            })
            .fail(function () {
                alert('Request failed.');
                $btn.prop('disabled', false).text('Review & Commit');
            });
        }
    });
})(jQuery);
