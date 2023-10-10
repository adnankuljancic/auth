<?php
// Include your config.php file
require_once __DIR__ . '/../../api/config_default.php';

// Access the data from config.php
$site_key = HCAPTCHA_SITE_KEY;

// Generate HTML output with the extracted data
?>
<!DOCTYPE html>
<html>
<head>
  <title>Account Recovery</title>
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
  <div class="container">
    <div class="row">
      <div class="col-md-6 offset-md-3">
        <div class="card mt-5">
          <div class="card-body">
            <h2 class="card-title text-center">Account Recovery</h2>
            <form id="recoveryForm">
              <div class="form-group">
                <label for="email">Email</label>
                <input type="email" class="form-control" id="email" placeholder="Enter your email">
              </div>
              <div class="form-group">
                <div id="captchaContainer" class="h-captcha text-center my-2" style="display: none;" data-sitekey="<?php echo $site_key ?>"></div>
              </div>
              <button type="submit" class="btn btn-primary">Recover</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src='https://www.hCaptcha.com/1/api.js' async defer></script>
  <script>
    $(document).ready(function() {
      // Handle form submission
      $('#recoveryForm').submit(function(e) {
        e.preventDefault(); // Prevent default form submission

        var email = $('#email').val();
        var captchaResponse = grecaptcha.getResponse(); // Get the captcha response

        var data = {
          'email': email,
          'h-captcha-response': captchaResponse
        };

        $.ajax({
          url: '../../api/recover',
          type: 'POST',
          data: JSON.stringify(data),
          contentType: 'application/json',
          dataType: 'json',
          success: function(response) {
            if (response.hasOwnProperty('message')) {
              if (response.message === 'Captcha is required') {
                $('#captchaContainer').show();
              } else {
                alert(response.message);
              }
            } else {
              window.location.href = 'index.html';
            }
          },
          error: function(xhr, status, error) {
            alert('An error occurred while recovering your account.');
          }
        });
      });
    });
  </script>
</body>
</html>