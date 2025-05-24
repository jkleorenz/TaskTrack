document.addEventListener('DOMContentLoaded', function() {
    // Only apply validation on register page
    if (window.location.pathname.includes('register.php')) {
        const form = document.querySelector('.form-auth');
        if (!form) return;

        const username = document.getElementById('username');
        const email = document.getElementById('email');
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');

        // Create validation status elements
        const fields = [
            { element: username, name: 'username' },
            { element: email, name: 'email' },
            { element: password, name: 'password' },
            { element: confirmPassword, name: 'confirm_password' }
        ];

        fields.forEach(field => {
            if (field.element) {
                const statusDiv = document.createElement('div');
                statusDiv.className = 'validation-status';
                statusDiv.id = `${field.name}-status`;
                field.element.parentNode.appendChild(statusDiv);
            }
        });

        // Validation functions
        function validateUsername(value) {
            const status = document.getElementById('username-status');
            if (value.length === 0) {
                setStatus(status, '', '');
                username.classList.remove('is-invalid', 'is-valid');
                return false;
            }
            if (value.length < 3) {
                setStatus(status, 'Username must be at least 3 characters', false);
                username.classList.add('is-invalid');
                username.classList.remove('is-valid');
                return false;
            }
            setStatus(status, 'Username is valid', true);
            username.classList.add('is-valid');
            username.classList.remove('is-invalid');
            return true;
        }

        function validateEmail(value) {
            const status = document.getElementById('email-status');
            if (value.length === 0) {
                setStatus(status, '', '');
                email.classList.remove('is-invalid', 'is-valid');
                return false;
            }
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                setStatus(status, 'Please enter a valid email address', false);
                email.classList.add('is-invalid');
                email.classList.remove('is-valid');
                return false;
            }
            setStatus(status, 'Email is valid', true);
            email.classList.add('is-valid');
            email.classList.remove('is-invalid');
            return true;
        }

        function validatePassword(value) {
            const status = document.getElementById('password-status');
            if (value.length === 0) {
                setStatus(status, '', '');
                password.classList.remove('is-invalid', 'is-valid');
                return false;
            }
            if (value.length < 6) {
                setStatus(status, 'Password must be at least 6 characters', false);
                password.classList.add('is-invalid');
                password.classList.remove('is-valid');
                return false;
            }
            setStatus(status, 'Password is valid', true);
            password.classList.add('is-valid');
            password.classList.remove('is-invalid');
            return true;
        }

        function validateConfirmPassword(value) {
            const status = document.getElementById('confirm_password-status');
            if (value.length === 0) {
                setStatus(status, '', '');
                confirmPassword.classList.remove('is-invalid', 'is-valid');
                return false;
            }
            if (value !== password.value) {
                setStatus(status, 'Passwords do not match', false);
                confirmPassword.classList.add('is-invalid');
                confirmPassword.classList.remove('is-valid');
                return false;
            }
            setStatus(status, 'Passwords match', true);
            confirmPassword.classList.add('is-valid');
            confirmPassword.classList.remove('is-invalid');
            return true;
        }

        function setStatus(element, message, isValid) {
            if (!element) return;
            
            element.innerHTML = `
                <span class="validation-message" style="color: ${isValid ? 'var(--success-color)' : 'var(--danger-color)'}">
                    ${message}
                    <i class="validation-icon ${isValid ? 'valid' : 'invalid'}"></i>
                </span>
            `;
        }

        // Add event listeners
        if (username) {
            username.addEventListener('input', (e) => validateUsername(e.target.value));
        }
        if (email) {
            email.addEventListener('input', (e) => validateEmail(e.target.value));
        }
        if (password) {
            password.addEventListener('input', (e) => {
                validatePassword(e.target.value);
                if (confirmPassword.value) {
                    validateConfirmPassword(confirmPassword.value);
                }
            });
        }
        if (confirmPassword) {
            confirmPassword.addEventListener('input', (e) => validateConfirmPassword(e.target.value));
        }

        // Form submission validation
        form.addEventListener('submit', function(e) {
            const isUsernameValid = validateUsername(username.value);
            const isEmailValid = validateEmail(email.value);
            const isPasswordValid = validatePassword(password.value);
            const isConfirmPasswordValid = validateConfirmPassword(confirmPassword.value);

            if (!isUsernameValid || !isEmailValid || !isPasswordValid || !isConfirmPasswordValid) {
                e.preventDefault();
            }
        });
    }
}); 