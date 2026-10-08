$(function() {
    function after_form_submitted(data, $form) {
        var $submitBtn = $form.find('input[type="submit"], button[type="submit"], input[type="button"], button[type="button"]');
        var origLabel = $submitBtn.data('orig_label');

        if (origLabel) {
            if ($submitBtn.is('input')) {
                $submitBtn.val(origLabel);
            } else {
                $submitBtn.text(origLabel);
            }
            $submitBtn.prop('disabled', false);
        }

        if (data && data.result === 'success') {
            $('#success_message').html(data.message || 'Your message has been sent successfully.').show();
            $('#error_message').hide();
            $form[0].reset();
        } else {
            var errorHtml = '<p>Sorry, there was an error processing your form:</p><ul>';
            if (data && data.errors) {
                $.each(data.errors, function(key, val) {
                    errorHtml += '<li>' + val + '</li>';
                });
            } else {
                errorHtml += '<li>An unexpected error occurred. Please try again.</li>';
            }
            errorHtml += '</ul>';

            $('#error_message').html(errorHtml).show();
            $('#success_message').hide();
        }
    }

    $('#contact_form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $submitBtn = $form.find('input[type="submit"], button[type="submit"]');

        if ($submitBtn.length) {
            var currentLabel = $submitBtn.is('input') ? $submitBtn.val() : $submitBtn.text();
            $submitBtn.data('orig_label', currentLabel);
            if ($submitBtn.is('input')) {
                $submitBtn.val('Sending...');
            } else {
                $submitBtn.text('Sending...');
            }
            $submitBtn.prop('disabled', true);
        }

        $.ajax({
            type: 'POST',
            url: 'handler.php',
            data: $form.serialize(),
            dataType: 'json',
            success: function(response) {
                after_form_submitted(response, $form);
            },
            error: function(xhr, status, error) {
                after_form_submitted({
                    result: 'error',
                    errors: { server: 'Server error (' + status + '). Please verify PHP MySQL configuration.' }
                }, $form);
            }
        });
    });
});
