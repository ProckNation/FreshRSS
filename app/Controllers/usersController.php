<?php

class FreshRSS_users_Controller extends Minz_ActionController {
	public function firstAction() {
		if (!$this->view->loginOk) {
			Minz_Error::error(
				403,
				array('error' => array(Minz_Translate::t('access_denied')))
			);
		}
	}

	public function idAction() {
		if (Minz_Request::isPost()) {
			$ok = true;
			$mail = Minz_Request::param('mail_login', false);
			$this->view->conf->_mail_login($mail);
			$ok &= $this->view->conf->save();

			Minz_Session::_param('mail', $this->view->conf->mail_login);

			//TODO: use $ok
			$notif = array(
				'type' => 'good',
				'content' => Minz_Translate::t('configuration_updated')
			);
			Minz_Session::_param('notification', $notif);

			Minz_Request::forward(array('c' => 'configure', 'a' => 'users'), true);
		}
	}

	public function authAction() {
		if (Minz_Request::isPost() && Minz_Configuration::isAdmin(Minz_Session::param('currentUser', '_'))) {
			$ok = true;
			$current_token = $this->view->conf->token;
			$token = Minz_Request::param('token', $current_token);
			$this->view->conf->_token($token);
			$ok &= $this->view->conf->save();

			Minz_Session::_param('mail', $this->view->conf->mail_login);

			$anon = Minz_Request::param('anon_access', false);
			$anon = ((bool)$anon) && ($anon !== 'no');
			$auth_type = Minz_Request::param('auth_type', 'none');
			if ($anon != Minz_Configuration::allowAnonymous() ||
				$auth_type != Minz_Configuration::authType()) {
				Minz_Configuration::_allowAnonymous($anon);
				Minz_Configuration::_authType($auth_type);
				$ok &= Minz_Configuration::writeFile();
			}

			$notif = array(
				'type' => $ok ? 'good' : 'bad',
				'content' => Minz_Translate::t($ok ? 'configuration_updated' : 'error_occurred')
			);
			Minz_Session::_param('notification', $notif);
		}
		Minz_Request::forward(array('c' => 'configure', 'a' => 'users'), true);
	}

	public function createAction() {
		if (Minz_Request::isPost() && Minz_Configuration::isAdmin(Minz_Session::param('currentUser', '_'))) {
			require_once(APP_PATH . '/sql.php');

			$new_user_language = Minz_Request::param('new_user_language', $this->view->conf->language);
			if (!in_array($new_user_language, $this->view->conf->availableLanguages())) {
				$new_user_language = $this->view->conf->language;
			}

			$new_user_name = Minz_Request::param('new_user_name');
			$ok = ctype_alnum($new_user_name);

			$new_user_email = filter_var($_POST['new_user_email'], FILTER_VALIDATE_EMAIL);
			if (empty($new_user_email)) {
				$new_user_email = '';
				$ok &= Minz_Configuration::authType() !== 'persona';
			}

			if ($ok) {
				$configPath = DATA_PATH . '/' . $new_user_name . '_user.php';
				$ok &= !file_exists($configPath);
			}
			if ($ok) {
				$config_array = array(
					'language' => $new_user_language,
					'mail_login' => $new_user_email,
				);
				$ok &= (file_put_contents($configPath, "<?php\n return " . var_export($config_array, true) . ';') !== false);
			}
			if ($ok) {
				$userDAO = new FreshRSS_UserDAO();
				$ok &= $userDAO->createUser($new_user_name);
			}

			$notif = array(
				'type' => $ok ? 'good' : 'bad',
				'content' => Minz_Translate::t($ok ? 'user_created' : 'error_occurred', $new_user_name)
			);
			Minz_Session::_param('notification', $notif);
		}
		Minz_Request::forward(array('c' => 'configure', 'a' => 'users'), true);
	}

	public function deleteAction() {
		if (Minz_Request::isPost() && Minz_Configuration::isAdmin(Minz_Session::param('currentUser', '_'))) {
			require_once(APP_PATH . '/sql.php');

			$username = Minz_Request::param('username');
			$ok = ctype_alnum($username);

			if ($ok) {
				$ok &= ($username !== Minz_Configuration::defaultUser());	//It is forbidden to delete the default user
			}
			if ($ok) {
				$configPath = DATA_PATH . '/' . $username . '_user.php';
				$ok &= file_exists($configPath);
			}
			if ($ok) {
				$userDAO = new FreshRSS_UserDAO();
				$ok &= $userDAO->deleteUser($username);
				$ok &= unlink($configPath);
			}
			$notif = array(
				'type' => $ok ? 'good' : 'bad',
				'content' => Minz_Translate::t($ok ? 'user_deleted' : 'error_occurred', $username)
			);
			Minz_Session::_param('notification', $notif);
		}
		Minz_Request::forward(array('c' => 'configure', 'a' => 'users'), true);
	}
}