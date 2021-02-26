jQuery(function() {
    var wc_tappay = {
        initialize: function () {
            // setup direct method api
            TPDirect.setupSDK(parseInt(tappay_params.app_id), tappay_params.app_key, tappay_params.server_type);

            TPDirect.card.onUpdate(function (e) {
                // if false !== e.hasError then pass
                var submitOrder = jQuery('button#place_order');
                var tappayChecked = jQuery('#payment_method_tappay').checked;

                if (true === tappayChecked && true === e.canGetPrime) {
                } else {
                }
            });

            this.setupForm();
        },

        requestToken: function () {
            TPDirect.card.getPrime(function (result) {
                if (0 !== result.status) {
                    console.log('Something wrong with getPrime');
                    return;
                }

                console.log('got prime: ' + result.card.prime);

                // got prime, send to backend
                wc_tappay.processPayment(result.card.prime);
            });

            return false;
        },

        processPayment: function (prime) {
            var checkoutForm = jQuery('form.woocommerce-checkout');
            checkoutForm.find('#tappay_prime').val(prime);
            checkoutForm.off('checkout_place_order', wc_tappay.requestToken);
            checkoutForm.submit();
            checkoutForm.on('checkout_place_order', wc_tappay.requestToken);
        },

        setupForm: function () {
            if (jQuery('#tappay_creditcard_container').length > 0) {
                var timer = setInterval(function () {
                    if (0 === jQuery('#tappay_creditcard_container').find('iframe').length) {
                        TPDirect.card.setup('#tappay_creditcard_container');
                    } else {
                        clearInterval(timer);
                    }
                }, 200);
            }
        }
    };

    // override default place order button
    jQuery('body').on('change', function (e) {
        wc_tappay.initialize();

        if ('checked' === jQuery('#payment_method_tappay').attr('checked')) {
            jQuery('form.woocommerce-checkout').on('checkout_place_order', wc_tappay.requestToken);
        } else {
            jQuery('form.woocommerce-checkout').off('checkout_place_order', wc_tappay.requestToken);
        }
    });
});
