jQuery(document).ready(function ($) {
    var el = $('[id*=senpex_delivery]');
    var label;

    if (el.next('label').find('.amount').length) {
        label = el.next('label').find('.amount').text();
    } else {
        label = el.next('label').text();
    }

    if (label.match("[a-zA-Z]")) {
        el.attr('disabled',true);
    }

    if ((label.match("[a-zA-Z]")&& el.is(":checked")) || ($('[id*=senpex_delivery]').is(":checked")) && $('#payment_method_cod').length && $('#payment_method_cod').is(':checked')) {
        $('#place_order').attr('disabled',true).css('cursor','not-allowed');
    }

    $( document.body ).on( 'updated_cart_totals', function(){
        if ($('[id*=senpex_delivery]').next('label').find('.amount').length) {
            label = $('[id*=senpex_delivery]').next('label').find('.amount').text();
        } else {
            label = $('[id*=senpex_delivery]').next('label').text()
        }

        if (label.match("[a-zA-Z]")) {
            //$('[id*=senpex_delivery]').attr('disabled',true);
        }
        if ((label.match("[a-zA-Z]")&& $('[id*=senpex_delivery]').is(":checked")) || ($('[id*=senpex_delivery]').is(":checked")) && $('#payment_method_cod').length && $('#payment_method_cod').is(':checked')) {
            $('#place_order').attr('disabled',true).css('cursor','not-allowed');
        }else{
            $('#place_order').attr('disabled',false).css('cursor','pointer');
        }

    });
    $( document.body ).on( 'updated_checkout', function(){
        if ($('[id*=senpex_delivery]').next('label').find('.amount').length) {
            label = $('[id*=senpex_delivery]').next('label').find('.amount').text();
        } else {
            label = $('[id*=senpex_delivery]').next('label').text()
        }

        if (label.match("[a-zA-Z]")) {
            //$('[id*=senpex_delivery]').attr('disabled',true);
        }
        if ((label.match("[a-zA-Z]")&& $('[id*=senpex_delivery]').is(":checked")) || ($('[id*=senpex_delivery]').is(":checked")) && $('#payment_method_cod').length && $('#payment_method_cod').is(':checked')) {
            $('#place_order').attr('disabled',true).css('cursor','not-allowed');
        }else{
            $('#place_order').attr('disabled',false).css('cursor','pointer');
        }
    });


    $(document).ready(function() {
        $('form[name="checkout"] input[name="payment_method"]').eq(0).prop('checked', true).attr( 'checked', 'checked' );
        checkGateway();
    });

    $(document).on("change", "form[name='checkout'] input[name='payment_method']", function(){
        if ( 0 === $('form[name="checkout"] input[name="payment_method"]' ).filter( ':checked' ).size() ) {
            $(this).prop('checked', true).attr( 'checked', 'checked' );
        };
        checkGateway();
    });

    function checkGateway(){
        if((label.match("[a-zA-Z]")&& $('[id*=senpex_delivery]').is(":checked")) || ($('[id*=senpex_delivery]').is(":checked")) && $('#payment_method_cod').length && $("form[name='checkout'] input[name='payment_method']:checked").val() == 'cod'){
            $('#place_order').attr('disabled',true).css('cursor','not-allowed');
        }else{
            $('#place_order').attr('disabled',false).css('cursor','pointer');
        }
    };


    var typingTimer;                //timer identifier
    var typingNotesTimer;
    var doneTypingInterval = 1000;  //time in ms (5 seconds)

    $(document).on('keyup input', 'input#checkout_tip', function(e) {
        clearTimeout(typingTimer);
        //if ($('input#checkout_tip').val()) {
            typingTimer = setTimeout(doneTyping, doneTypingInterval);
        //}
    });
    function doneTyping () {
        checkout_tip = $('input#checkout_tip').val();
        $.ajax({
            type: 'POST',
            url: wc_checkout_params.ajax_url,
            data: {
                'action': 'woo_get_ajax_data',
                'checkout_tip': checkout_tip,
            },
            success: function (result) {
                $('input#checkout_tip').val('');
                $(document.body).trigger("update_checkout");
                if (result == '"0"') {
                    $('input#checkout_tip').val('');
                }
            },
            error: function(error){
            }
        });
    }


    $(document).on('keyup input', 'textarea#order_comments', function(e) {
        clearTimeout(typingNotesTimer);
        typingNotesTimer = setTimeout(doneNotesTyping, doneTypingInterval);
    });
    function doneNotesTyping () {
        checkout_note = $('textarea#order_comments').val();
        $.ajax({
            type: 'POST',
            url: wc_checkout_params.ajax_url,
            data: {
                'action': 'woo_get_ajax_data',
                'checkout_note': checkout_note,
            },
            success: function (result) {
                $(document.body).trigger("update_checkout");
                $('textarea#order_comments').val(result.slice(1,-1));
                if (result == '"0"') {
                    $('textarea#order_comments').val('');
                }
            },
            error: function(error){
            }
        });
    }


    $(document).on('change', '#delivery_time_frame', function(e) {
        var time_frame = $( "#delivery_time_frame option:selected" ).text();
        time = time_frame.split("–").shift();
        $.ajax({
            type: 'POST',
            url: wc_checkout_params.ajax_url,
            data: {
                'action': 'woo_get_ajax_data',
                'delivery_time': time,
            },
            success: function (result) {
                $(document.body).trigger("update_checkout");
            },
            error: function(error){
            }
        });
    });
});

jQuery(document).ajaxComplete(function() {
    options_l = jQuery('#delivery_time_frame option').length;
    if (options_l == 1 && (typeof time_done === 'undefined' || !time_done)) {
        time = jQuery( "#delivery_time_frame option:selected" ).text().split("–").shift();

        jQuery.ajax({
            type: 'POST',
            url: wc_checkout_params.ajax_url,
            data: {
                'action': 'woo_get_ajax_data',
                'delivery_time': time,
            },
            success: function (result) {
                time_done = true;
                jQuery('head').append('<script id="senpex_gen_script">time_done = true;</script>');
                jQuery(document.body).trigger("update_checkout");
            },
            error: function(error){
            }
        });

    }
});