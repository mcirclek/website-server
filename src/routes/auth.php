<?php

$app->group('/auth', function() use ($app) {
  $app->get(
    '(/)',
    function() use ($app) {
      $req = $app->request();
      json_output($req->me ? $req->me : (object) null);
    }
  );

  $app->post(
    '/login',
    validator(array(
      'unq' => validator_string(),
      'password' => validator_string(),
    )),
    function() use ($app) {
      $req = $app->request();
      $unq = $req->args['unq'];
      $pass = $req->args['password'];

      if (!\CKIUM\User::authenticate($app, $unq, $pass)) {
        $app->halt(401, 'Invalid username or password');
      }

      $user = \CKIUM\User::get_user($app, $unq);
      if (!$user) {
        //TODO error
        $app->halt(500, 'Could not look up user');
      }

      \CKIUM\User::startSession($app, $unq);

      json_output($user);
    }
  );


  $app->post('/logout', function() use ($app) {
    $req = $app->request();

    if (!$req->me) {
      goto out;
    }

    $key = $req->token;
    $stmt = $app->db->prepare('DELETE FROM SessionKeys WHERE `key`=:key;');
    $stmt->bindValue(':key', $key, \PDO::PARAM_STR);
    $stmt->execute();

    $app->setCookie('token', '', strtotime('-1 day'));

out:
    json_output((object) null);
  });
});
