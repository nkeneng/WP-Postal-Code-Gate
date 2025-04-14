jQuery(document).ready(function($) {

    const popupOverlay = $('#pcg-popup-overlay');
    const postcodeForm = $('#pcg-form');
    const postcodeSubmit = $('#pcg-submit');
    const postcodeInput = $('#pcg-postcode');
    const messageDiv = $('#pcg-message');
    const loadingDiv = $('#pcg-loading');

    function showPopup() {
        if (popupOverlay.length) {
             popupOverlay.show().addClass('pcg-visible');
             $('body').css('overflow', 'hidden');
        }
    }

    function hidePopup() {
        popupOverlay.removeClass('pcg-visible');
        setTimeout(function() {
            popupOverlay.hide();
            $('body').css('overflow', '');
        }, 300);
    }

    if (document.cookie.indexOf('pcg_postcode_validated=yes') === -1) {
         showPopup();
    }

    postcodeForm.on('submit', function(e) {
        e.preventDefault();

        const postcode = postcodeInput.val().trim();
        messageDiv.hide().text('');
        loadingDiv.show();
        postcodeSubmit.prop('disabled', true);

        if (postcode === '') {
            messageDiv.text(pcg_vars.error_message_empty).show();
            loadingDiv.hide();
            postcodeSubmit.prop('disabled', false);
            return;
        }

        $.ajax({
            url: pcg_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'pcg_check_postcode',
                nonce: pcg_vars.nonce,
                postcode: postcode
            },
            success: function(response) {
                loadingDiv.hide();
                postcodeSubmit.prop('disabled', false);

                if (response.success) {
                    hidePopup();
                } else {
                    messageDiv.text(pcg_vars.error_message_invalid).show();
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                loadingDiv.hide();
                postcodeSubmit.prop('disabled', false);
                messageDiv.text('Erreur lors de la vérification: ' + textStatus).show();
                console.error("AJAX Error:", textStatus, errorThrown);
            }
        });
    });

});
