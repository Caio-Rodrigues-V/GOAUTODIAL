<?php
/**
 * @file 		login.php
 * @brief 		login application
 * @copyright 	Copyright (c) 2020 GOautodial Inc. 
 * @author     	Christopher Lomuntad 
 * @author		Demian Lizandro A. Biscocho
 * @author		Ignacio Nieto Carvajal
 *
 * @par <b>License</b>:
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
**/

	error_reporting(E_ERROR | E_PARSE);

	require_once('./php/CRMDefaults.php');
	require_once('./php/UIHandler.php');
	require_once('./php/DbHandler.php');
	require_once('./php/LanguageHandler.php');
	require_once('./php/SessionHandler.php');
	$session_class = new \creamy\SessionHandler();		
	
	// force https protocol
	if ((empty($_SERVER["HTTPS"]) || $_SERVER["HTTPS"] != "on") && (empty($_SERVER["HTTP_X_FORWARDED_PROTO"]) || $_SERVER["HTTP_X_FORWARDED_PROTO"] != "https")) {
		if (isset($_SERVER["HTTP_HOST"]) && isset($_SERVER["REQUEST_URI"])) {
			header("Location: https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"]);
			exit();
		}
	}

	/*if (CRM_SESSION_DRIVER == 'database') {
		require_once('./php/SessionHandler.php');
		$session_class = new \creamy\SessionHandler();
	} else {
		session_start(); // Starting Session
	}*/
	
	$lh = \creamy\LanguageHandler::getInstance();
	$ui = \creamy\UIHandler::getInstance();
	$error = ''; // Variable To Store Error Message
	if (isset($_POST['submit'])) {
		if (empty($_POST['username']) || empty($_POST['password'])) {
			$error = $lh->translationFor("insert_valid_login_password");
		} else {
			$db = new \creamy\DbHandler();

			// Define $username and $password
			$username=$_POST['username'];
			$password=$_POST['password'];
			
			// To protect MySQL injection for Security purpose
			$username = stripslashes($username);
			$password = stripslashes($password);
			$username = $db->escape_string($username);
			$password = $db->escape_string($password);

			// Check password and redirect accordingly
			$result = null;
			if(filter_var($username, FILTER_VALIDATE_EMAIL)) {
		        // valid email address
				$result = $db->checkLoginByEmail($username, $password, $_SERVER['REMOTE_ADDR']);
		    }
		    else {
		        // not an email. User name?
				$result = $db->checkLoginByName($username, $password, $_SERVER['REMOTE_ADDR']);
		    }
			if ($result == NULL) { // login failed
				$error = $lh->translationFor("invalid_login_password");
			} else {
				$_SESSION["user"] = $username;
				$_SESSION["userid"] = $result["id"];
				$_SESSION["username"] = $result["name"];
				$_SESSION["userrole"] = $result["role"];
				$_SESSION["usergroup"] = $result["user_group"];
				$_SESSION["phone_login"] = $result["phone_login"];
				$_SESSION["phone_pass"] = $result["phone_pass"];
				$_SESSION["phone_this"] = $password;
                $_SESSION["ha1"] = $result["ha1"];
                $_SESSION["realm"] = $result["realm"];
                $_SESSION["bcrypt"] = $result["bcrypt"];
				$_SESSION["use_webrtc"] = $result["use_webrtc"];
				$_SESSION["password_hash"] = $result["password_hash"];
				
				if (!empty($result["avatar"])) {
					$_SESSION['avatar'] = $result["avatar"];
				} else { // random avatar.
					$_SESSION["avatar"] = CRM_DEFAULTS_USER_AVATAR;
				}

				if($_SESSION["userrole"] == CRM_DEFAULTS_USER_ROLE_ADMIN || $_SESSION["userrole"] == CRM_DEFAULTS_USER_ROLE_SUPERVISOR || $_SESSION["userrole"] == CRM_DEFAULTS_USER_ROLE_TEAMLEADER){
					header("location: index.php"); // Redirecting To Admin Dashboard
				}
				if($_SESSION["userrole"] == CRM_DEFAULTS_USER_ROLE_AGENT){
					header("location: agent.php"); // Redirecting to Agent Dashboard
				}

			}
		}
	}
	
	$uname = (isset($_GET['username'])) ? $_GET['username'] : '';
	$upass = (isset($_GET['password'])) ? $_GET['password'] : '';
	//https://github.com/goautodial/v4.0/issues/48
	//prevent xss
	$uname = htmlentities($uname);
	$upass = htmlentities($upass);
?>
<!DOCTYPE html>
<html lang="pt-BR">
        <meta charset="UTF-8">
        <title>DIALog DDM - Acesso à Plataforma</title>
        <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>
        
        <link rel="icon" type="image/png" sizes="32x32" href="img/brand/favicon-32.png">
        <link rel="apple-touch-icon" sizes="180x180" href="img/brand/favicon-180.png">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
        <link href="css/bootstrap.min.css" rel="stylesheet" type="text/css" />
        <link href="css/dialog_ddm.css" rel="stylesheet" type="text/css" />

        <script src="js/jquery.min.js"></script>
        <script src="js/bootstrap.min.js" type="text/javascript"></script>

        <style>
            body.login-page {
                background: #F8F9FB !important;
                font-family: 'Inter', -apple-system, sans-serif !important;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0;
                padding: 20px;
            }
            .login-card {
                background: #FFFFFF;
                border: 1px solid #EAECF0;
                border-radius: 12px;
                padding: 40px 36px;
                width: 100%;
                max-width: 440px;
                box-shadow: 0 4px 6px -2px rgba(16, 24, 40, 0.05), 0 10px 15px -3px rgba(16, 24, 40, 0.08);
            }
            .login-brand-header {
                text-align: center;
                margin-bottom: 28px;
            }
            .login-brand-header img {
                height: 48px;
                width: auto;
                margin-bottom: 12px;
            }
            .login-title {
                font-size: 20px;
                font-weight: 700;
                color: #101828;
                margin: 0 0 6px 0;
                letter-spacing: -0.3px;
            }
            .login-subtitle {
                font-size: 13.5px;
                color: #667085;
                margin: 0;
            }
            .login-footer {
                text-align: center;
                margin-top: 24px;
                font-size: 12px;
                color: #98A2B3;
            }
        </style>
    </head>
    <body class="login-page">
        <div class="login-card">
            <div class="login-brand-header">
                <img src="img/brand/logo_horizontal_color.svg" alt="DIALog DDM" />
                <h1 class="login-title">Acesse sua Conta</h1>
                <p class="login-subtitle">Plataforma de Voz com Inteligência Artificial</p>
            </div>

            <form action="" method="post">
                <div class="ddm-form-group">
                    <label class="ddm-form-label">Usuário ou E-mail:</label>
                    <input type="text" name="username" class="ddm-input" placeholder="seu.usuario" value="<?=$uname?>" required autofocus />
                </div>

                <div class="ddm-form-group">
                    <label class="ddm-form-label">Senha:</label>
                    <input type="password" name="password" class="ddm-input" placeholder="••••••••" value="<?=$upass?>" required />
                </div>

                <?php if (!empty($error)): ?>
                    <div style="background:#FEF3F2; color:#B42318; border:1px solid #FECDCA; border-radius:8px; padding:10px 12px; font-size:13px; margin-bottom:16px;">
                        <?=$error?>
                    </div>
                <?php endif; ?>

                <button type="submit" name="submit" class="ddm-btn ddm-btn-primary" style="width:100%; height:42px; font-size:14px; margin-top:8px;">
                    Acessar Plataforma
                </button>
            </form>

            <div class="login-footer">
                DIALog DDM &bull; Grupo DDM &bull; Voice AI & Telephony
            </div>
        </div>
    </body>
</html>
