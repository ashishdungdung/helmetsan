/**
 * AI admin: Handles provider testing, healing reverts, and premium correction reviews.
 */
(function ($) {
    'use strict';

    $(function () {
        var $overlay = null;

        /**
         * 1. Initialize Modal Structure
         */
        function ensureModal() {
            if ($overlay) return;

            $overlay = $('<div class="hs-modal-overlay">' +
                '<div class="hs-modal-container">' +
                '<div class="hs-modal-header"><h3 class="hs-modal-title">Review Correction</h3><button class="hs-modal-close">&times;</button></div>' +
                '<div class="hs-modal-body">' +
                '<div class="hs-diff-viewport">' +
                '<div class="hs-diff-pane"><div class="hs-diff-label">Original Master</div><div class="hs-diff-content" id="hs-diff-orig"></div></div>' +
                '<div class="hs-diff-pane"><div class="hs-diff-label">AI Suggestion</div><div class="hs-diff-content" id="hs-diff-new"></div></div>' +
                '</div>' +
                '<div class="hs-edit-area"><label>Manual Overwrite (JSON)</label><textarea id="hs-edit-textarea"></textarea></div>' +
                '</div>' +
                '<div class="hs-modal-footer">' +
                '<button class="button hs-toggle-edit">Edit Fix</button>' +
                '<button class="button hs-modal-cancel">Cancel</button>' +
                '<button class="button button-primary hs-modal-commit">Commit to Master</button>' +
                '</div>' +
                '</div>' +
                '</div>').appendTo('body');

            // Close events
            $overlay.on('click', '.hs-modal-close, .hs-modal-cancel', function() {
                hideModal();
            });

            // Toggle Edit
            $overlay.on('click', '.hs-toggle-edit', function() {
                var $area = $('.hs-edit-area');
                $area.toggleClass('is-active');
                if ($area.hasClass('is-active')) {
                    $(this).text('View Diff');
                    $('.hs-diff-viewport').hide();
                } else {
                    $(this).text('Edit Fix');
                    $('.hs-diff-viewport').show();
                }
            });
        }

        function showModal() {
            ensureModal();
            $overlay.addClass('is-visible');
            $('body').addClass('hs-modal-open');
        }

        function hideModal() {
            if ($overlay) $overlay.removeClass('is-visible');
            $('body').removeClass('hs-modal-open');
            $('.hs-edit-area').removeClass('is-active');
            $('.hs-diff-viewport').show();
            $('.hs-toggle-edit').text('Edit Fix');
        }

        // --- AJAX: Test Provider ---
        $(document).on('click', '.helmetsan-ai-test-btn', function () {
            var $btn = $(this);
            var providerId = $btn.data('provider-id');
            var $result = $btn.closest('td').find('.helmetsan-ai-test-result');

            $result.empty().append('<span class="helmetsan-ai-status" style="color:#666;">…</span>');
            $btn.prop('disabled', true);

            $.post(window.helmetsanAi.ajaxUrl, {
                action: 'helmetsan_ai_test_provider',
                nonce: window.helmetsanAi.nonce,
                provider_id: providerId
            }).done(function (r) {
                $result.empty();
                if (r.success) {
                    $result.append('<span class="helmetsan-ai-status ok" style="color:#00a32a;">✓ Working</span>');
                } else {
                    $result.append('<span class="helmetsan-ai-status fail" style="color:#d63638;">✗ ' + (r.data.message || 'Failed') + '</span>');
                }
            }).always(function() { $btn.prop('disabled', false); });
        });

        // --- AJAX: Review & Commit ---
        $(document).on('click', '.hs-review-correction', function (e) {
            e.preventDefault();
            var $btn = $(this);
            var file = $btn.data('file');

            $btn.prop('disabled', true).text('Loading...');

            $.get(window.helmetsanAi.ajaxUrl, {
                action: 'helmetsan_ai_get_correction_diff',
                nonce: window.helmetsanAi.nonce,
                file: file
            })
            .done(function (r) {
                if (r.success) {
                    renderDiff(r.data.original, r.data.corrected);
                    $('#hs-edit-textarea').val(JSON.stringify(r.data.corrected, null, 4));
                    $('.hs-modal-commit').data('file', file);
                    showModal();
                } else {
                    alert(r.data.message || 'Failed to fetch.');
                }
            })
            .always(function() { $btn.prop('disabled', false).text('Review & Commit'); });
        });

        function renderDiff(orig, corrected) {
            // Simple render for now - using JSON stringification to highlight changes
            // In a full implementation, we'd use a field-by-field compare.
            var origStr = JSON.stringify(orig, null, 4);
            var newStr = JSON.stringify(corrected, null, 4);

            $('#hs-diff-orig').html('<pre>' + escapeHtml(origStr) + '</pre>');
            $('#hs-diff-new').html('<pre>' + highlightChanges(orig, corrected) + '</pre>');
        }

        function highlightChanges(orig, corrected) {
            var output = '';
            var newKeys = Object.keys(corrected);
            
            output += '{\n';
            newKeys.forEach(function(key, i) {
                var oldVal = JSON.stringify(orig[key]);
                var newVal = JSON.stringify(corrected[key], null, 4);
                var isModified = oldVal !== JSON.stringify(corrected[key]);
                
                var line = '    "' + key + '": ' + newVal;
                if (isModified) {
                    output += '<span class="hs-diff-add">' + escapeHtml(line) + '</span>';
                } else {
                    output += escapeHtml(line);
                }
                if (i < newKeys.length - 1) output += ',';
                output += '\n';
            });
            output += '}';
            return output;
        }

        $(document).on('click', '.hs-modal-commit', function() {
            var $btn = $(this);
            var file = $btn.data('file');
            var customContent = $('.hs-edit-area').hasClass('is-active') ? $('#hs-edit-textarea').val() : null;

            $btn.prop('disabled', true).text('Committing...');

            $.post(window.helmetsanAi.ajaxUrl, {
                action: 'helmetsan_ai_commit_correction',
                nonce: window.helmetsanAi.nonce,
                file: file,
                content: customContent
            })
            .done(function (r) {
                if (r.success) {
                    hideModal();
                    location.reload();
                } else {
                    alert(r.data.message || 'Commit failed.');
                    $btn.prop('disabled', false).text('Commit to Master');
                }
            });
        });

        // --- AJAX: Sync Certification ---
        $(document).on('click', '.hs-sync-cert', function (e) {
            e.preventDefault();
            var $btn = $(this);
            var id = $btn.data('id');

            $btn.prop('disabled', true).text('Syncing...');

            $.post(window.helmetsanAi.ajaxUrl, {
                action: 'helmetsan_ai_sync_certification',
                nonce: window.helmetsanAi.nonce,
                id: id
            })
            .done(function (r) {
                if (r.success) {
                    location.reload();
                } else {
                    alert(r.data.message || 'Sync failed.');
                    $btn.prop('disabled', false).text('Sync & Create');
                }
            });
        });

        // --- AJAX: Generate Alternatives ---
        $(document).on('click', '.hs-generate-alternatives', function (e) {
            e.preventDefault();
            var $btn = $(this);
            var id = $btn.data('id');

            $btn.prop('disabled', true).text('Mapping...');

            $.post(window.helmetsanAi.ajaxUrl, {
                action: 'helmetsan_ai_generate_alternatives',
                nonce: window.helmetsanAi.nonce,
                id: id
            })
            .done(function (r) {
                if (r.success) {
                    alert(r.data.message || 'Alternatives mapped successfully.');
                    location.reload();
                } else {
                    alert(r.data.message || 'Mapping failed.');
                    $btn.prop('disabled', false).text('Generate Alternatives');
                }
            });
        });

        function escapeHtml(text) {
            if (!text) return '';
            return text.replace(/&/g, "&amp;")
                       .replace(/</g, "&lt;")
                       .replace(/>/g, "&gt;")
                       .replace(/"/g, "&quot;")
                       .replace(/'/g, "&#039;");
        }
    });

})(jQuery);
Query);
