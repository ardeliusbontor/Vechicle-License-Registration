(function () {
    function showToast(type, message) {
        if (window.AdminToast && typeof window.AdminToast.show === 'function') {
            window.AdminToast.show({ type: type, message: message });
        }
    }

    function normalizeText(value) {
        return String(value || '').trim();
    }

    function isEmail(value) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
    }

    function isPhone(value) {
        var digits = value.replace(/\D/g, '');
        return digits.length >= 7;
    }

    function blockSubmit(form, field, message) {
        showToast('warning', message);
        if (field && typeof field.focus === 'function') {
            field.focus();
        }
        return false;
    }

    function submitWithToast(form, message) {
        if (form.dataset.toastSubmitting === '1') {
            return;
        }

        form.dataset.toastSubmitting = '1';
        var submitButton = form.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.dataset.originalText = submitButton.innerHTML;
            submitButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Sending...';
        }

        showToast('info', message || 'Submitting...');

        setTimeout(function () {
            form.submit();
        }, 220);
    }

    function attachValidation(form, validator, successMessage) {
        if (!form || form.dataset.toastValidationAttached === '1') {
            return;
        }

        form.dataset.toastValidationAttached = '1';
        form.addEventListener('submit', function (event) {
            if (form.dataset.toastSubmitting === '1') {
                return;
            }

            var result = validator(form);
            if (!result.valid) {
                event.preventDefault();
                blockSubmit(form, result.field, result.message);
                return;
            }

            event.preventDefault();
            submitWithToast(form, successMessage);
        });
    }

    function validateLogin(form) {
        var username = form.querySelector('input[name="username"]');
        var password = form.querySelector('input[name="password"]');

        if (!username || normalizeText(username.value).length < 3) {
            return { valid: false, field: username, message: 'Enter a valid username (at least 3 characters).' };
        }

        if (!password || normalizeText(password.value).length < 4) {
            return { valid: false, field: password, message: 'Enter your password to continue.' };
        }

        return { valid: true };
    }

    function validateRegister(form) {
        var fullname = form.querySelector('input[name="fullname"]');
        var username = form.querySelector('input[name="username"]');
        var email = form.querySelector('input[name="email"]');
        var contact = form.querySelector('input[name="contact_number"]');
        var county = form.querySelector('select[name="county"]');
        var address = form.querySelector('textarea[name="address"]');
        var password = form.querySelector('input[name="password"]');
        var confirmPassword = form.querySelector('input[name="confirm_password"]');

        if (!fullname || normalizeText(fullname.value).length < 3) {
            return { valid: false, field: fullname, message: 'Please enter your full name.' };
        }

        if (!username || normalizeText(username.value).length < 3) {
            return { valid: false, field: username, message: 'Choose a username with at least 3 characters.' };
        }

        if (!email || !isEmail(normalizeText(email.value))) {
            return { valid: false, field: email, message: 'Please enter a valid email address.' };
        }

        if (!contact || !isPhone(normalizeText(contact.value))) {
            return { valid: false, field: contact, message: 'Please enter a valid contact number.' };
        }

        if (!county || normalizeText(county.value) === '') {
            return { valid: false, field: county, message: 'Please select your county.' };
        }

        if (!address || normalizeText(address.value).length < 8) {
            return { valid: false, field: address, message: 'Please enter your address.' };
        }

        if (!password || normalizeText(password.value).length < 6) {
            return { valid: false, field: password, message: 'Password must be at least 6 characters.' };
        }

        if (!confirmPassword || password.value !== confirmPassword.value) {
            return { valid: false, field: confirmPassword, message: 'Passwords do not match.' };
        }

        return { valid: true };
    }

    function validateApply(form) {
        var applicantName = form.querySelector('input[name="applicant_name"]');
        var applicantContact = form.querySelector('input[name="applicant_contact"]');
        var county = form.querySelector('select[name="county"]');
        var applicantAddress = form.querySelector('textarea[name="applicant_address"]');
        var plate = form.querySelector('input[name="plate_number"]');
        var make = form.querySelector('input[name="vehicle_make"]');
        var model = form.querySelector('input[name="vehicle_model"]');
        var year = form.querySelector('input[name="vehicle_year"]');
        var color = form.querySelector('input[name="vehicle_color"]');
        var ownerName = form.querySelector('input[name="owner_name"]');
        var ownerContact = form.querySelector('input[name="owner_contact"]');
        var yearMax = new Date().getFullYear() + 1;

        if (!applicantName || normalizeText(applicantName.value).length < 3) {
            return { valid: false, field: applicantName, message: 'Enter your full name.' };
        }

        if (!applicantContact || !isPhone(normalizeText(applicantContact.value))) {
            return { valid: false, field: applicantContact, message: 'Enter a valid applicant contact number.' };
        }

        if (!county || normalizeText(county.value) === '') {
            return { valid: false, field: county, message: 'Please select your county.' };
        }

        if (!applicantAddress || normalizeText(applicantAddress.value).length < 8) {
            return { valid: false, field: applicantAddress, message: 'Please provide your complete address.' };
        }

        if (!plate || !/^[A-Za-z0-9\-]{3,20}$/.test(normalizeText(plate.value))) {
            return { valid: false, field: plate, message: 'Enter a valid plate number (letters, numbers, dash).' };
        }

        if (!make || normalizeText(make.value).length < 2) {
            return { valid: false, field: make, message: 'Enter a valid vehicle make.' };
        }

        if (!model || normalizeText(model.value).length < 2) {
            return { valid: false, field: model, message: 'Enter a valid vehicle model.' };
        }

        if (year && normalizeText(year.value) !== '') {
            var parsedYear = Number(year.value);
            if (!Number.isFinite(parsedYear) || parsedYear < 1990 || parsedYear > yearMax) {
                return { valid: false, field: year, message: 'Enter a valid vehicle year.' };
            }
        }

        if (!color || normalizeText(color.value).length < 2) {
            return { valid: false, field: color, message: 'Enter the vehicle color.' };
        }

        if (!ownerName || normalizeText(ownerName.value).length < 3) {
            return { valid: false, field: ownerName, message: 'Enter the vehicle owner name.' };
        }

        if (!ownerContact || !isPhone(normalizeText(ownerContact.value))) {
            return { valid: false, field: ownerContact, message: 'Enter a valid owner contact number.' };
        }

        return { valid: true };
    }

    function validateRenew(form) {
        var plate = form.querySelector('input[name="plate_number"]');
        var applicantName = form.querySelector('input[name="applicant_name"]');

        if (!plate || normalizeText(plate.value).length < 3) {
            return { valid: false, field: plate, message: 'Enter your current plate number.' };
        }

        if (!applicantName || normalizeText(applicantName.value).length < 3) {
            return { valid: false, field: applicantName, message: 'Enter your full name.' };
        }

        return { valid: true };
    }

    function validateContact(form) {
        var fullName = form.querySelector('input[name="full_name"]');
        var email = form.querySelector('input[name="email"]');
        var subject = form.querySelector('input[name="subject"]');
        var message = form.querySelector('textarea[name="message"]');

        if (!fullName || normalizeText(fullName.value).length < 3) {
            return { valid: false, field: fullName, message: 'Please enter your full name.' };
        }

        if (!email || !isEmail(normalizeText(email.value))) {
            return { valid: false, field: email, message: 'Please enter a valid email address.' };
        }

        if (!subject || normalizeText(subject.value).length < 3) {
            return { valid: false, field: subject, message: 'Please enter a subject.' };
        }

        if (!message || normalizeText(message.value).length < 10) {
            return { valid: false, field: message, message: 'Message should be at least 10 characters.' };
        }

        return { valid: true };
    }

    function init() {
        var loginForm = document.querySelector('form[action="auth.php"]');
        attachValidation(loginForm, validateLogin, 'Signing in...');

        var registerForm = document.querySelector('input[name="confirm_password"]') ? document.querySelector('form[method="POST"]') : null;
        attachValidation(registerForm, validateRegister, 'Creating account...');

        var applyForm = document.querySelector('input[name="vehicle_make"]') ? document.querySelector('form[method="POST"]') : null;
        attachValidation(applyForm, validateApply, 'Submitting application...');

        var renewForm = document.querySelector('input[name="applicant_name"]') && document.querySelector('input[name="plate_number"]') && !document.querySelector('input[name="vehicle_make"]') ? document.querySelector('form[method="POST"]') : null;
        attachValidation(renewForm, validateRenew, 'Submitting renewal request...');

        var contactForm = document.querySelector('input[name="full_name"]') ? document.querySelector('form[method="POST"]') : null;
        attachValidation(contactForm, validateContact, 'Sending your message...');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
