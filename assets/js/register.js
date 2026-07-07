$(function () {
    const $form = $('#registerForm');
    const $alert = $('#registerAlert');
    const $button = $('#registerSubmit');

    function showAlert(message, type) {
        $alert.removeClass('d-none alert-success alert-danger').addClass(`alert-${type}`).text(message);
    }

    function setLoading(loading) {
        $button.prop('disabled', loading);
        $button.text(loading ? 'Creating account...' : 'Register');
    }

    $.getJSON('php/login.php', function (response) {
        if (response && response.success && response.data && response.data.authenticated) {
            window.location.href = 'profile.html';
        }
    });

    $form.on('submit', function (event) {
        event.preventDefault();
        $alert.addClass('d-none').text('');
        setLoading(true);

        $.ajax({
            url: 'php/register.php',
            method: 'POST',
            data: JSON.stringify({
                username: $('#username').val(),
                email: $('#email').val(),
                password: $('#password').val(),
                confirm_password: $('#confirm_password').val()
            }),
            processData: false,
            contentType: 'application/json; charset=utf-8',
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                showAlert(response.message || 'Registration successful.', 'success');
                setTimeout(function () {
                    window.location.href = (response.data && response.data.redirect) ? response.data.redirect : 'login.html';
                }, 900);
                return;
            }

            showAlert(response.message || 'Registration failed.', 'danger');
        }).fail(function (xhr) {
            const response = xhr.responseJSON || {};
            const errors = response.data && response.data.errors ? response.data.errors : {};
            const message = response.message || 'Registration failed.';
            const errorText = Object.values(errors).filter(Boolean).join(' ');
            showAlert(errorText ? `${message} ${errorText}` : message, 'danger');
        }).always(function () {
            setLoading(false);
        });
    });
});
