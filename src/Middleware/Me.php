<?php
namespace CKIUM\Middleware;

class Me extends \Slim\Middleware {
	public function call()
	{
		$app = $this->app;
		$req = $app->request;
		$app->expires('+1 second');

		$token = $app->getCookie('token');
		if (!$token) {
			$token = $req->params('token');
		}

		if (!$token) {
			goto out;
		}

		$match = preg_match('/(.*):(.*)/', $token, $matches); //get the username and cookie key
		if (!$match) {
			goto clearCookie;
		}

		$unq = $matches[1];
		$key = hash('sha256', $matches[2]);

		$stmt = $app->db->prepare('SELECT unq FROM SessionKeys WHERE `unq`=:unq AND `key`=:key AND `expiration`>=NOW();');
		$stmt->bindValue(':unq', $unq, \PDO::PARAM_STR);
		$stmt->bindValue(':key', $key, \PDO::PARAM_STR);
		$stmt->execute();

		if (!$stmt->rowCount()) {
			goto clearCookie;
		}

		$me = \CKIUM\User::get_user($app, $unq);
		if (!$me) {
			goto out;
		}

		$req->me = $me;
		$req->token = $key;

		goto out;

		clearCookie:
			$app->deleteCookie('token');

		out:
			$this->next->call();
	}
}
