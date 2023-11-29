$(document).ready(function () {
    $('#stat_data tbody').css({opacity: 0.1});

    $('#datetimes1').daterangepicker({
        timePicker: true,
        startDate: moment().subtract(2, 'days').format('YYYY-MM-DD HH:mm'),
        endDate: moment().format('YYYY-MM-DD HH:mm'),
        locale: {
        format: 'YYYY-MM-DD HH:mm'
        },
        autoApply: true,
        autoUpdateInput: true
    });

    // ajax
    $('#stat_data button').on('click', function () {
        $('tr#error').hide();
        $('#stat_data tbody').fadeTo('fast', 0.1);
        var btn = $(this);
        btn.prop('disabled', true);
        btn.text(btn.data('loading'));

        $.ajax({
            url: document.location.href,
            data: {
                getstat: 1,
                modop: 'custom',
                a: 'OutstandingDetails',
                start: $('#datetimes1').data('daterangepicker').startDate.format('YYYY-MM-DD HH:mm'),
                end: $('#datetimes1').data('daterangepicker').endDate.format('YYYY-MM-DD HH:mm'),
                tz_offset: function () {
                    var myDate = new Date();
                    offset = myDate.getTimezoneOffset();
                    return offset;
                }
            },
            error: function () {
                $('#stat_data tbody').hide();
                $('tr#error').show();
            },
            success: function (data) {
                data = JSON.parse(data);
                processData(data);
            }
        }).always(function () {
            btn.prop('disabled', false);
            btn.text(btn.data('normal'));
        });
    });
    $('#stat_data button').click();
});

function processData(data) {
    if (data) {
        for (i in data) {
            val = accounting.formatMoney(data[i], {symbol: data.currency_code, format: "%v %s"});
            $('#' + i).text(val);
        }
        $('#stat_data tbody').fadeTo('fast', 1);
    }
    else {
        $('#stat_data tbody').hide();
        $('tr#error').show();
    }
}