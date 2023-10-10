<?php
require_once 'BaseService.php';
require_once __DIR__ . "/../dao/UserDao.php";
require_once __DIR__ . '/../config_default.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
use OTPHP\TOTP;


class UserService extends BaseService
{
    public function __construct()
    {
        parent::__construct(new UserDao);
    }

    public function register($entity)
    {
        //full name validation

        $full_name = $entity['full_name'];

        if (empty($full_name) || trim($full_name) === '') {
            return ['message' => 'Full name is required'];
        }

        //username validation

        $username = $entity['username'];

        if(strlen($username)<=3) {
            return ['message' => 'Username has to be longer than 3 characters'];
        }

        if (!preg_match('/^[a-zA-Z0-9]+$/', $username)) {
            return ['message' => 'Username has to be alphanumeric (no special characters and no spaces)'];
        }

        $username_count =$this->dao->column_value_count($username, "username");

        if($username_count!=0) {
            return ['message' => 'Username must be unique'];
        }


        //password validation

        $password = $entity['password'];

        if (strlen($password)<=8) {
            return ['message' => 'Password must be longer than 8 characters'];
        }

        $hash = sha1($password);
        $hash_capital = strtoupper($hash);
        $first_5_characters = substr($hash_capital, 0, 5);
        $remaining_password = substr($hash_capital, 5, strlen($hash_capital));
        $response = file_get_contents(filename: "https://api.pwnedpasswords.com/range/".$first_5_characters);

        if(str_contains($response, $remaining_password)) {
            return ['message' => 'Password has been pwned. Please use another one.'];
        } 
        
        $entity['password'] = $hash;

        //email validation

        $email = $entity['email'];

        if (empty($email) || trim($email) === '') {
            return ['message' => 'Email is required'];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['message' => 'Invalid email format'];
        }

        $parts = explode('@', $email);

        $domain = $parts[1];

        if (!checkdnsrr($domain, 'MX')) {
            return ['message' => 'Invalid domain name'];
        }

        //phone number validation

        $phone = $entity['phone'];

        $phoneUtil = \libphonenumber\PhoneNumberUtil::getInstance();

        try {
            $phoneProto = $phoneUtil->parse($phone, null);
            if (!$phoneUtil->isValidNumber($phoneProto)) {
                return 'Invalid phone number';
            } 
        } catch (\libphonenumber\NumberParseException $e) {
            return 'Error: ' . $e->getMessage();
        }

        $phone_count =$this->dao->column_value_count($phone, "phone");

        if($phone_count!=0) {
            return ['message' => 'Phone number must be unique'];
        }

        $entity['otp_hash'] = $this->generate_otp_hash();
        
        $confirmation_token = $this->generate_token();
        $email_body = "Welcome to my SSSD website $full_name! Please confirm your email via: http://localhost/sssd-2023-20002445/project/front/pages/confirm.html?token=$confirmation_token";

        $this->send_email($email, $email_body);

        $entity['confirmation_token'] = $confirmation_token;

        return ['message' => 'success', 'user' => $this->dao->add($entity)];
    }
    
    public function send_email($email, $email_body) {
        $mail = new PHPMailer(true);
        
        try {
            //Server settings
            $mail->SMTPDebug = SMTP::DEBUG_SERVER;                      //Enable verbose debug output
            $mail->isSMTP();                                            //Send using SMTP
            $mail->Host       = SMTP_HOST;                  //Set the SMTP server to send through
            $mail->SMTPAuth   = true;                                   //Enable SMTP authentication
            $mail->Username   = SMTP_USERNAME;                     //SMTP username
            $mail->Password   = SMTP_PASSWORD;                               //SMTP password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;           //Enable implicit TLS encryption
            $mail->Port       = SMTP_PORT;                                    //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`

            //Recipients
            $mail->setFrom(SMTP_USERNAME, 'Mailer');     //Add a recipient
            $mail->addAddress($email);               //Name is optional
            $mail->addReplyTo(SMTP_USERNAME, 'Information');

            $mail->Subject = 'SSSD Confirmation email';
            $mail->Body    = $email_body;

            $mail->send();
        } catch (Exception $e) {
            return "Email confirmation message could not be sent. Please try again.";
        }
    }

    public function login ($entity) {
        

        if (empty($entity['username']) || empty($entity['password'])) {
            return ['message' => "Username and password are required $count"];
        }
        
        $existingUser = $this->dao->get_user_by_column('username', $entity['username']);
        
        if (!$existingUser) {
            return ['message' => 'Invalid username or password'];
        }
        
        if (sha1($entity['password']) !== $existingUser['password']) {
            $existingUser['login_tries'] = ++$existingUser['login_tries'];
            $this->dao->update($existingUser['id'], $existingUser);
            return ['message' => 'Invalid username or password', 'login_tries' => $existingUser['login_tries']];
        }

        if($existingUser['login_tries'] > 3) {
            if($entity['h-captcha-response']=='') {
                return ['message' => "Captcha is required!"];
            }
            $captcha_result = $this->check_captcha($entity['h-captcha-response']);
            if(!$captcha_result) {
                return ['message' => "Invalid Captcha!"];
            }
        }


        unset($entity['password']);
        $entity['full_name'] = $existingUser['full_name'];

        $jwt = JWT::encode($entity, 'secret1234', 'HS256');

        $existingUser['login_tries'] = 0;
        $this->dao->update($existingUser['id'], $existingUser);
        
        return ['token' => $jwt];
        
    }

    public function confirm_email($token) {
        $user = $this->dao->get_user_by_column('confirmation_token', $token);
    
        if ($user) {
            $user['is_verified'] = 1;
            $user['confirmation_token'] = '';
            $this->dao->update($user['id'], $user);
            return ['message' => "Email has been verified!"];
        } else {
            // Token is not valid
            return ['message' => "Email verification failed!"];
        }
    }

    public function check_OTP_status($username) {
        $user = $this->dao->get_user_by_column('username', $username);
        
        if($user['is_otp_verified']==1) {
            return ['otp_verified' => true];
        } else {
            return ['otp_verified' => false, 'qr_code' => $this->generate_qrcode($username)];
        }
    }


    public function generate_qrcode($username)
    {
        $user = $this->dao->get_user_by_column('username', $username);
        $otp_hash = $user['otp_hash'];
        $otp = TOTP::createFromSecret($otp_hash);
        $otp->setLabel("SSSD Project");
        $grCodeUri = $otp->getQrCodeUri(
        'https://api.qrserver.com/v1/create-qr-code/?data=[DATA]&size=300x300&ecc=M',
        '[DATA]'
        );
        return $grCodeUri;
    }
    
    public function validate_otp($entity) 
    {
        $user = $this->dao->get_user_by_column('username', $entity['username']);
        $code = $entity['code'];
        $otp_hash = $user['otp_hash'];
        $otp = TOTP::createFromSecret($otp_hash); // create TOTP object from the secret.
        if($otp->verify($code)) {
            if($user['is_otp_verified']==false) {
                $user['is_otp_verified'] = true;
                $this->dao->update($user['id'], $user);
            }
            return ['message' => 'success'];
        } else {
            return ['message' => 'Please try again!'];
        }
    }

    public function send_sms($username) {
        $user = $this->dao->get_user_by_column('username', $username);
        $phone = $user['phone'];

        $ch = curl_init();

        $code = strval(rand(1000, 999999));

        $data = [
            'api_key' => TEXT_MESSAGE_API_KEY,
            'from' => '+38761419507', 
            'to' => $phone,
            'text' => $code
        ];

        $postData = http_build_query($data);

        curl_setopt($ch, CURLOPT_URL,"https://rest.nexmo.com/sms/json");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

        $credentials = base64_encode(TEXT_MESSAGE_API_KEY . ':' . TEXT_MESSAGE_SECRET);
        $headers = array(
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Basic ' . $credentials
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $server_output = curl_exec($ch);

        curl_close($ch);

        $user['sms_code'] = $code;
        $this->dao->update($user['id'], $user);

        return ['message'=>'Code sent.'];
    }

    public function check_sms_code($entity) {
        $user = $this->dao->get_user_by_column('username', $entity['username']);
        if($entity['sms_code']==$user['sms_code']) {
            $user['sms_code'] = '';
            $this->dao->update($user['id'], $user);
            return ['message'=>'success'];
        } else {
            return ['message'=>'Please try again.'];
        }
    }

    public function forgot_password($entity) {
        $user = $this->dao->get_user_by_column('email', $entity['email']);
        
        if($user['recovery_count']>5) {
            if (!isset($entity['h-captcha-response'])) {
                if($entity['h-captcha-response']=='') {
                    return ['message' => "Captcha is required!"];
                }
                $captcha_result = $this->check_captcha($entity['h-captcha-response']);
                if(!$captcha_result) {
                    return ['message' => "Invalid Captcha!"];
                }
            }
        }
        $token = $this->generate_token();
        $user['confirmation_token'] = $token;
        $email_body = "Click this link to change your password: http://localhost/sssd-2023-20002445/project/front/pages/change-password.html?token=$token";
        $this->send_email($user['email'], $email_body);
        $user['recovery_count'] = ++$user['recovery_count'];
        $this->dao->update($user['id'], $user);
        return ['message' => 'success'];
    }

    public function change_password($entity, $token) {
        $user = $this->dao->get_user_by_column('confirmation_token', $token);
    
        if ($user) {
            $user['confirmation_token'] = '';
            $user['password'] = sha1($entity['password']);
            $this->dao->update($user['id'], $user);
            $email_body = "Password has been changed!";
            $this->send_email($user['email'], $email_body);
            return ['message' => "Password has been changed!"];
        } else {
            // Token is not valid
            return ['message' => "Password change failed!"];
        }
    }

    public function generate_token() {
        return bin2hex(random_bytes(32));
    }

    public function generate_otp_hash() {
        $otp = TOTP::generate();
        return $otp -> getSecret();
    }

    function check_captcha($response){
        $data = array(
            'secret' => HCAPTCHA_SERVER_SECRET,
            'response' => $response
        );
        $verify = curl_init();
        curl_setopt($verify, CURLOPT_POST, true);
        curl_setopt($verify, CURLOPT_URL, "https://hcaptcha.com/siteverify");
        curl_setopt($verify, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($verify, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($verify);
        // var_dump($response);
        $responseData = json_decode($response);
        if($responseData->success) {
             return true;
        } 
        else {
             return $responseData;
        }
    }



}


?>
