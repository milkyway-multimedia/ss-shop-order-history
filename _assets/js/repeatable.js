(function ($) {
    $(document)
        .on('click touchstart', '[data-confirm]', function (e) {
           if(!confirm($(this).data('confirm'))) {
               e.preventDefault();
           }
        });
}(window.jQuery || window.Zepto || window.Sprint));
