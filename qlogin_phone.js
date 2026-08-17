/* Local phone picker integration for local_qlogin_shomokh. */
document.addEventListener('DOMContentLoaded', function() {
    var root = document.getElementById('qlogin-wrapper');
    var loginTab = document.getElementById('btn-login');
    var registerTab = document.getElementById('btn-register');

    function passwordIcon(hidden) {
        if (hidden) {
            return '<svg viewBox="0 0 24 24" aria-hidden="true">'
                + '<path d="M2.1 3.5 3.5 2.1l18.4 18.4-1.4 1.4-3.1-3.1A11.8 11.8 0 0 1 12 20C6.5 20 2.2 15.8.5 12c.8-1.8 2.2-3.8 4.2-5.3L2.1 3.5Zm4 4.6A10.8 10.8 0 0 0 2.8 12c1.7 3.1 5.1 6 9.2 6 1.4 0 2.7-.3 3.8-.8l-2-2A4 4 0 0 1 8.8 10l-2.7-1.9ZM12 4c5.5 0 9.8 4.2 11.5 8a14.3 14.3 0 0 1-3.4 4.7l-1.4-1.4a12 12 0 0 0 2.5-3.3C19.5 8.9 16.1 6 12 6c-.7 0-1.4.1-2 .3L8.4 4.7C9.5 4.2 10.7 4 12 4Zm0 4a4 4 0 0 1 4 4v.4l-4.4-4.4h.4Z"/>'
                + '</svg>';
        }
        return '<svg viewBox="0 0 24 24" aria-hidden="true">'
            + '<path d="M12 4C6.5 4 2.2 8.2.5 12 2.2 15.8 6.5 20 12 20s9.8-4.2 11.5-8C21.8 8.2 17.5 4 12 4Zm0 14c-4.1 0-7.5-2.9-9.2-6C4.5 8.9 7.9 6 12 6s7.5 2.9 9.2 6c-1.7 3.1-5.1 6-9.2 6Zm0-10a4 4 0 1 0 0 8 4 4 0 0 0 0-8Zm0 6a2 2 0 1 1 0-4 2 2 0 0 1 0 4Z"/>'
            + '</svg>';
    }

    function setupPasswordToggles() {
        if (!root) {
            return;
        }
        root.querySelectorAll('input[type="password"]').forEach(function(input) {
            if (input.parentElement && input.parentElement.classList.contains('qlogin-password-container')) {
                return;
            }
            var parent = input.parentElement;
            if (!parent) {
                return;
            }
            parent.classList.add('qlogin-password-container');
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'qlogin-password-toggle';
            button.setAttribute('aria-label', root.dataset.showPassword || 'Show password');
            button.setAttribute('aria-pressed', 'false');
            button.innerHTML = passwordIcon(false);
            button.addEventListener('click', function() {
                var visible = input.type === 'text';
                input.type = visible ? 'password' : 'text';
                button.setAttribute('aria-pressed', visible ? 'false' : 'true');
                button.setAttribute('aria-label', visible
                    ? (root.dataset.showPassword || 'Show password')
                    : (root.dataset.hidePassword || 'Hide password'));
                button.innerHTML = passwordIcon(!visible);
                input.focus();
            });
            parent.appendChild(button);
        });
    }

    function switchTab(tab) {
        var registering = tab === 'register';
        var actionInput = root.querySelector('input[name="action"]');
        loginTab.classList.toggle('active', !registering);
        registerTab.classList.toggle('active', registering);
        loginTab.setAttribute('aria-selected', registering ? 'false' : 'true');
        registerTab.setAttribute('aria-selected', registering ? 'true' : 'false');
        root.dataset.mode = registering ? 'register' : 'login';
        if (actionInput) {
            actionInput.value = registering ? 'register' : 'login';
        }
        ['fullname', 'email', 'phone', 'identifier'].forEach(function(name) {
            var fieldWrapper = document.getElementById('fitem_id_' + name);
            if (fieldWrapper) {
                fieldWrapper.classList.toggle('d-none', name === 'identifier' ? registering : !registering);
            }
        });
        var identifier = document.getElementById('id_identifier');
        var fullname = document.getElementById('id_fullname');
        var email = document.getElementById('id_email');
        var phone = document.getElementById('id_phone');
        var password = document.getElementById('id_password');
        var title = document.getElementById('form-title');
        var subtitle = document.getElementById('form-subtitle');
        var submit = document.getElementById('id_submitbutton');
        var forgot = document.getElementById('forgot-password-link');
        if (fullname) {
            fullname.required = registering;
        }
        if (email) {
            email.required = registering;
        }
        if (identifier) {
            identifier.required = !registering;
            identifier.autocomplete = 'username';
        }
        if (phone) {
            phone.required = registering;
            phone.autocomplete = 'tel';
        }
        if (password) {
            password.autocomplete = registering ? 'new-password' : 'current-password';
        }
        if (title) {
            title.textContent = registering ? root.dataset.registerTitle : root.dataset.loginTitle;
        }
        if (subtitle) {
            subtitle.textContent = registering ? root.dataset.registerSubtitle : root.dataset.loginSubtitle;
        }
        if (submit) {
            submit.value = registering ? root.dataset.registerButton : root.dataset.loginButton;
        }
        if (forgot) {
            forgot.classList.toggle('d-none', registering);
        }
        var focusTarget = registering ? phone : identifier;
        if (focusTarget && document.activeElement !== password) {
            focusTarget.focus();
        }
    }

    if (root && loginTab && registerTab) {
        loginTab.addEventListener('click', function(event) {
            event.preventDefault();
            switchTab('login');
        });
        registerTab.addEventListener('click', function(event) {
            event.preventDefault();
            switchTab('register');
        });
        switchTab(root.dataset.mode === 'register' ? 'register' : 'login');
    }

    setupPasswordToggles();

    var input = document.getElementById('id_phone');
    var form = input ? input.closest('form') : null;
    if (!input || !form || typeof window.intlTelInput !== 'function') {
        return;
    }
    var defaultCountry = root ? String(root.dataset.defaultCountry || 'sa').toLowerCase() : 'sa';
    if (!/^[a-z]{2}$/.test(defaultCountry)) {
        defaultCountry = 'sa';
    }
    if (window.intlTelInputGlobals && typeof window.intlTelInputGlobals.getCountryData === 'function') {
        var countryExists = window.intlTelInputGlobals.getCountryData().some(function(country) {
            return country.iso2 === defaultCountry;
        });
        if (!countryExists) {
            defaultCountry = 'sa';
        }
    }
    var picker = window.intlTelInput(input, {
        initialCountry: defaultCountry,
        nationalMode: true,
        separateDialCode: true,
        preferredCountries: [],
        customContainer: 'qlogin-phone-picker notranslate',
        utilsScript: M.cfg.wwwroot + '/local/qlogin_shomokh/vendor/intl-tel-input/build/js/utils.js'
    });
    var countryCodeInput = form.querySelector('input[name="phonecountrycode"]');

    function syncCountryCode() {
        var selected = picker.getSelectedCountryData();
        if (countryCodeInput) {
            countryCodeInput.value = selected && selected.dialCode ? String(selected.dialCode) : '';
        }
    }
    syncCountryCode();
    input.addEventListener('countrychange', syncCountryCode);

    // Browser translation can replace the picker's flag and dial-code elements
    // with one long country-name text node, which then overlaps the phone input.
    var container = input.closest('.iti');
    if (container) {
        container.classList.add('notranslate');
        container.setAttribute('translate', 'no');
        var countrylist = container.querySelector('.iti__country-list') || document.querySelector('.iti__country-list');
        if (countrylist) {
            countrylist.classList.add('notranslate');
            countrylist.setAttribute('translate', 'no');
        }
    }

    form.addEventListener('submit', function() {
        syncCountryCode();
        var selected = picker.getSelectedCountryData();
        var dialCode = selected && selected.dialCode ? String(selected.dialCode) : '';
        var raw = String(input.value || '').trim();
        var rawDigits = raw.replace(/\D/g, '');
        var hasInternationalPrefix = /^[\s\u200e\u200f\u202a-\u202e\u2066-\u2069]*(?:\+|00)/.test(raw);
        // A common paste is 966... while +966 is already shown separately.
        // Treat that as international before asking the picker to format it,
        // otherwise some browsers produce +966966....
        if (!hasInternationalPrefix && dialCode && rawDigits.indexOf(dialCode) === 0) {
            var nationalInterpretation = picker.getNumber();
            var nationalWasValid = typeof picker.isValidNumber === 'function' && picker.isValidNumber();
            picker.setNumber('+' + rawDigits);
            var pastedWasValid = typeof picker.isValidNumber !== 'function' || picker.isValidNumber();
            if (!pastedWasValid && nationalWasValid && nationalInterpretation) {
                picker.setNumber(nationalInterpretation);
            }
        }
        var international = picker.getNumber();
        if (international) {
            input.value = international;
        }
    });
});
