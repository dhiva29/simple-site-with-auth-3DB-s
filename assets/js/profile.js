$(function () {
    const $profileForm = $('#profileForm');
    const $profileAlert = $('#profileAlert');
    const $logoutButton = $('#logoutButton');
    const $saveButton = $('#saveProfileButton');

    function showAlert(message, type) {
        $profileAlert.removeClass('d-none alert-success alert-danger').addClass(`alert-${type}`).text(message);
    }

    function setSaving(loading) {
        $saveButton.prop('disabled', loading);
        $saveButton.text(loading ? 'Saving...' : 'Save Profile');
    }

    function redirectToLogin() {
        window.location.href = 'login.html';
    }

    function renderInterests(interests) {
        const values = Array.isArray(interests) ? interests : [];
        const html = values.map(function (interest) {
            return `<span class="interest-pill">${$('<div>').text(interest).html()}</span>`;
        }).join('');
        $('#interestPreview').html(html || '<span class="text-muted">No interests added yet.</span>');
    }

    $.getJSON('php/profile.php')
        .done(function (response) {
            if (!(response && response.success && response.data && response.data.authenticated)) {
                redirectToLogin();
                return;
            }

            const user = response.data.user || {};
            const profile = response.data.profile || {};
            $('#profileUsername').text(user.username || '');
            $('#profileEmail').text(user.email || '');
            $('#name').val(profile.name || '');
            $('#age').val(profile.age || '');
            $('#bio').val(profile.bio || '');
            const interests = Array.isArray(profile.interests) ? profile.interests.join(', ') : '';
            $('#interests').val(interests);
            renderInterests(profile.interests || []);
        })
        .fail(function () {
            redirectToLogin();
        });

    $('#interests').on('input', function () {
        const interests = $(this).val().split(',').map(function (item) {
            return item.trim();
        }).filter(Boolean);
        renderInterests(interests);
    });

    $profileForm.on('submit', function (event) {
        event.preventDefault();
        $profileAlert.addClass('d-none').text('');
        setSaving(true);

        $.ajax({
            url: 'php/profile.php',
            method: 'POST',
            data: JSON.stringify({
                name: $('#name').val(),
                age: $('#age').val(),
                bio: $('#bio').val(),
                interests: $('#interests').val().split(',').map(function (item) {
                    return item.trim();
                }).filter(Boolean)
            }),
            processData: false,
            contentType: 'application/json; charset=utf-8',
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                showAlert(response.message || 'Profile updated.', 'success');
                renderInterests(response.data && response.data.profile ? response.data.profile.interests : []);
                return;
            }

            showAlert(response.message || 'Unable to save profile.', 'danger');
        }).fail(function (xhr) {
            if (xhr.status === 401) {
                redirectToLogin();
                return;
            }

            const response = xhr.responseJSON || {};
            const errors = response.data && response.data.errors ? response.data.errors : {};
            const message = response.message || 'Unable to save profile.';
            const errorText = Object.values(errors).filter(Boolean).join(' ');
            showAlert(errorText ? `${message} ${errorText}` : message, 'danger');
        }).always(function () {
            setSaving(false);
        });
    });

    $logoutButton.on('click', function () {
        $.ajax({
            url: 'php/profile.php',
            method: 'POST',
            data: JSON.stringify({ action: 'logout' }),
            processData: false,
            contentType: 'application/json; charset=utf-8',
            dataType: 'json'
        }).done(function () {
            redirectToLogin();
        }).fail(function () {
            redirectToLogin();
        });
    });
});
