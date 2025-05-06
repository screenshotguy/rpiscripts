<?php
/**
 * Authentication handling
 */
class Auth {
    private $username;
    private $password;
    private $login_error = '';

    public function __construct($username, $password) {
        $this->username = $username;
        $this->password = $password;

        // Handle logout
        if (isset($_GET['logout'])) {
            session_unset();
            session_destroy();
            header("Location: {$_SERVER['PHP_SELF']}");
            exit;
        }

        // Handle login
        if (isset($_POST['login_username']) && isset($_POST['login_password'])) {
            if ($_POST['login_username'] === $this->username && $_POST['login_password'] === $this->password) {
                $_SESSION['logged_in'] = true;
                header("Location: {$_SERVER['PHP_SELF']}");
                exit;
            } else {
                $this->login_error = "Invalid username or password.";
            }
        }
    }

    public function isLoggedIn() {
        return !empty($_SESSION['logged_in']);
    }

    public function getLoginError() {
        return $this->login_error;
    }
}
?>
