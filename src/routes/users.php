<?php

$app->group('/users', function() use ($app) {
	$app->get(
		'/:unq',
		function ($unq) use ($app) {
			$req = $app->request();
			if (!$req->me || $req->me['unq'] != $unq) {
				$app->halt(400, 'Invalid user');
			}

			json_output($req->me);
		}
	);
});
