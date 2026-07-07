$(function () {
    const $form = $('#loginForm');
    const $alert = $('#loginAlert');
    const $button = $('#loginSubmit');

    function showAlert(message, type) {
        $alert.removeClass('d-none alert-success alert-danger').addClass(`alert-${type}`).text(message);
    }

    function setLoading(loading) {
        $button.prop('disabled', loading);
        $button.text(loading ? 'Signing in...' : 'Login');
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
            url: 'php/login.php',
            method: 'POST',
            data: JSON.stringify({
                identifier: $('#identifier').val(),
                password: $('#password').val()
            }),
            processData: false,
            contentType: 'application/json; charset=utf-8',
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                window.location.href = (response.data && response.data.redirect) ? response.data.redirect : 'profile.html';
                return;
            }

            showAlert(response.message || 'Login failed.', 'danger');
        }).fail(function (xhr) {
            const response = xhr.responseJSON || {};
            const errors = response.data && response.data.errors ? response.data.errors : {};
            const message = response.message || 'Login failed.';
            const errorText = Object.values(errors).filter(Boolean).join(' ');
            showAlert(errorText ? `${message} ${errorText}` : message, 'danger');
        }).always(function () {
            setLoading(false);
        });
    });
});
