jQuery(document).ready(function($) {
    // Check if we're on mobile
    if (window.innerWidth < 783) { // WordPress mobile breakpoint
        $('.qpfp-calendar').hide();
        $('.qpfp-list-view').show();
        $('#qpfp-toggle-view').hide();
    } else {
        $('.qpfp-list-view').hide();
        $('#qpfp-toggle-view').show().text(qpfpAdmin.i18n.showListView);
    }
    
    $('#qpfp-toggle-view').on('click', function() {
        var $calendar = $('.qpfp-calendar');
        var $listView = $('.qpfp-list-view');
        var $button = $(this);
        
        if ($listView.is(':visible')) {
            $listView.hide();
            $calendar.show();
            $button.text(qpfpAdmin.i18n.showListView);
        } else {
            $listView.show();
            $calendar.hide();
            $button.text(qpfpAdmin.i18n.showCalendarView);
        }
    });
}); 