(function ($) {
    var $iframe;

    $(document)
        .on('click touchstart', '[data-print-url]', function (e) {
            e.preventDefault();

            if (!$iframe) {
                $iframe = $('<iframe style="display:none"></iframe>').appendTo($('body'));
            }

            $iframe.one('load', function () {
                $iframe[0].focus();
                $iframe[0].contentWindow.print();
            });

            $iframe.attr('src', $(this).data('printUrl'));
        })
        .on('click touchstart', '[data-print]', function (e) {
            e.preventDefault();

            window.print();
        });
}(window.jQuery || window.Zepto || window.Sprint));
