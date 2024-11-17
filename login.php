<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login and Signup</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>

<body>
    <div class="container">
        <div class="form-container">
            <div class="toggle-buttons">
                <button id="loginBtn" class="toggle-btn active">Login</button>
                <button id="signupBtn" class="toggle-btn">Signup</button>
            </div>
            <div class="form-content">
                <form id="loginForm" class="form active" onsubmit="return handleLogin(event)">
                    <div class="input-group">
                        <input type="text" name="username" placeholder="Username or Email" required autocomplete="username">
                        <i class="fas fa-user input-icon"></i>
                    </div>
                    <div class="input-group">
                        <input type="password" name="password" id="loginPassword" placeholder="Password" required>
                        <i class="fas fa-eye toggle-password" onclick="togglePassword('loginPassword', this)"></i>
                    </div>
                    <label class="remember-me">
                        <input type="checkbox" name="remember"> Remember me
                    </label>
                    <a href="#" class="forgot-password">Forgot password?</a>
                    <button type="submit">Login</button>
                    <div id="loginMessage" class="message"></div>
                    <div id="error-message" class="alert alert-danger" style="display: none;"></div>
                </form>

                <form id="signupForm" class="form">
                    <div class="input-group">
                        <input type="text" name="username" placeholder="Username" required>
                    </div>
                    <div class="input-group">
                        <input type="email" name="email" placeholder="Email" required>
                    </div>
                    <div class="input-group">
                        <input type="password" name="password" id="signupPassword" placeholder="Create password" required>
                        <i class="fas fa-eye toggle-password" onclick="togglePassword('signupPassword', this)"></i>
                    </div>
                    <button type="submit">Signup</button>
                    <div id="registerMessage" class="message"></div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function togglePassword(inputId, icon) {
            const input = document.getElementById(inputId);
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const loginBtn = document.getElementById('loginBtn');
            const signupBtn = document.getElementById('signupBtn');
            const loginForm = document.getElementById('loginForm');
            const signupForm = document.getElementById('signupForm');

            // Toggle functionality
            loginBtn.addEventListener('click', () => {
                loginBtn.classList.add('active');
                signupBtn.classList.remove('active');
                loginForm.classList.add('active');
                signupForm.classList.remove('active');
            });

            signupBtn.addEventListener('click', () => {
                signupBtn.classList.add('active');
                loginBtn.classList.remove('active');
                signupForm.classList.add('active');
                loginForm.classList.remove('active');
            });

            // Form submission handling
            const BASE_URL = '/Contact_book_app';

            loginForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = new FormData(loginForm);
                const data = {
                    username: formData.get('username'),
                    password: formData.get('password'),
                    remember: formData.get('remember') ? true : false
                };

                const messageElement = document.getElementById('loginMessage');
                messageElement.textContent = ''; // Clear previous messages

                try {
                    const response = await fetch(`${BASE_URL}/auth/login.php`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(data)
                    });

                    const result = await response.json();
                    console.log('Login response:', result); // Debug log

                    if (result.success) {
                        window.location.href = `${BASE_URL}/index.php`; // Use BASE_URL
                    } else {
                        messageElement.textContent = result.error || 'Login failed';
                        messageElement.style.color = 'red';
                    }
                } catch (error) {
                    console.error('Error:', error);
                    messageElement.textContent = 'An error occurred during login';
                    messageElement.style.color = 'red';
                }
            });

            signupForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = new FormData(signupForm);
                const data = {
                    username: formData.get('username'),
                    email: formData.get('email'),
                    password: formData.get('password')
                };

                try {
                    const response = await fetch(`${BASE_URL}/auth/register.php`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(data)
                    });
                    const result = await response.json();

                    if (result.success) {
                        document.getElementById('registerMessage').textContent = 'Registration successful!';
                        signupForm.reset();
                        loginBtn.click();
                    } else {
                        document.getElementById('registerMessage').textContent = result.error;
                    }
                } catch (error) {
                    console.error('Error:', error);
                    document.getElementById('registerMessage').textContent = 'Registration failed';
                }
            });
        });
    </script>
</body>

</html>