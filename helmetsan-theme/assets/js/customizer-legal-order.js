(function ($) {
    'use strict';

    function syncOrder($list) {
        var keys = [];
        $list.find('.hs-legal-sortable__item').each(function () {
            keys.push($(this).data('key'));
        });
        var value = keys.join(',');
        var $input = $list.closest('.customize-control').find('.hs-legal-sortable-input');
        $input.val(value).trigger('change');
    }

    $(function () {
        $('.hs-legal-sortable').each(function () {
            var $list = $(this);
            var $input = $list.closest('.customize-control').find('.hs-legal-sortable-input');
            var raw = ($input.val() || '').toString();
            if (raw) {
                var order = raw.split(',');
                order.forEach(function (key) {
                    var $item = $list.find('.hs-legal-sortable__item[data-key="' + key + '"]');
                    if ($item.length) {
                        $list.append($item);
                    }
                });
            }

            $list.sortable({
                axis: 'y',
                handle: '.hs-legal-sortable__handle',
                containment: 'parent',
                update: function () {
                    syncOrder($list);
                }
            });
        });
    });
})(jQuery);
