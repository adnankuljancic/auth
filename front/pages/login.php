<?php
// Include your config.php file
require_once __DIR__ . '/../../api/config_default.php';

// Access the data from config.php
$site_key = HCAPTCHA_SITE_KEY;

// Generate HTML output with the extracted data
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <script src='https://www.hCaptcha.com/1/api.js' async defer></script>
    <style>
        body {
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            padding: 2rem;
        }

        .card {
            width: 100%;
            max-width: 400px;
            margin: auto;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="card-body">
            <h1 class="card-title text-center mb-4">Login</h1>
            <form id="loginForm">
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <div class="text-center">
                    <button type="submit" class="btn btn-primary">Login</button>
                </div>
            </form>
            <div class="h-captcha text-center my-2" id="captchaContainer" style="display: none;" data-sitekey="<?php echo $site_key?>"></div>
            <div class="text-center mt-3">
                <p>Don't have an account? <a href="register.html">Register</a></p>
            </div>
            <div class="text-center mt-1">
                <p>Forgot password? <a href="recover-password.php">Recover</a></p>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Handle form submission
            $('#loginForm').submit(function(e) {
                e.preventDefault(); // Prevent default form submission

                // Collect form data
                var username = $('#username').val();
                var password = $('#password').val();
                var captchaResponse = grecaptcha.getResponse(); // Get the captcha response
                console.log(captchaResponse);

                // Create data object
                var data = {
                    "username": username,
                    "password": password,
                    "h-captcha-response": captchaResponse // Include captcha response in the data object
                };

                // Make AJAX request
                $.ajax({
                    url: '../../api/login',
                    type: 'POST',
                    data: JSON.stringify(data),
                    contentType: 'application/json',
                    dataType: 'json',
                    success: function(response) {
                        // Check for error message
                        if (response.hasOwnProperty('message')) {
                            alert(response.message);
                        } else if (response.hasOwnProperty('token')) {
                            // Save token to local storage
                            localStorage.setItem('token', response.token);

                            // Redirect to otp page
                            window.location.href = 'otp.html';
                        }    
                        if (response.hasOwnProperty('login_tries') && response.login_tries > 3) {
                            // Show the CAPTCHA div
                            $('#captchaContainer').show();
                        }
                    },
                    error: function(xhr, status, error) {
                        console.log(xhr.responseText);
                        alert('An error occurred while logging in.');
                    }
                });
            });
        });
    </script>
</body>
</html>
