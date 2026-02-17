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
    }

    $(bindLogoPicker);
}(jQuery));
