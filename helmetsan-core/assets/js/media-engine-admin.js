(function ($) {
    'use strict';

    function bindLogoPicker() {
        var frame = null;

        $(document).on('click', '.helmetsan-select-logo-media', function (e) {
            e.preventDefault();

            if (frame) {
                frame.open();
                return;
            }

            frame = wp.media({
                title: 'Select Logo',
                button: { text: 'Use this logo' },
                library: { type: 'image' },
                multiple: false
            });

            frame.on('select', function () {
                var selection = frame.state().get('selection').first();
                if (!selection) {
                    return;
                }
                var attachment = selection.toJSON();
                var url = attachment.url || '';
                var id = attachment.id || 0;

                $('#helmetsan_logo_url').val(url);
                $('#helmetsan_logo_provider').val('media-library');
                $('#helmetsan_logo_attachment_id').val(id);
            });

            frame.open();
        });

        $(document).on('click', '.helmetsan-clear-logo-media', function (e) {
            e.preventDefault();
            $('#helmetsan_logo_url').val('');
            $('#helmetsan_logo_provider').val('');
            $('#helmetsan_logo_attachment_id').val('');
        });

        $(document).on('click', '.helmetsan-copy-logo-url', function (e) {
            e.preventDefault();
            var url = $(this).data('url');
            if (!url) return;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(function () {
                    var $btn = $(e.target).closest('button');
                    var text = $btn.text();
                    $btn.text('Copied!');
                    setTimeout(function () { $btn.text(text); }, 2000);
                });
            } else {
                var $input = $('<input>').val(url).appendTo('body').select();
                document.execCommand('copy');
                $input.remove();
                var $btn = $(e.target).closest('button');
                var text = $btn.text();
                $btn.text('Copied!');
                setTimeout(function () { $btn.text(text); }, 2000);
            }
        });
    }

    $(bindLogoPicker);
}(jQuery));
