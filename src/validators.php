<?php

function validator($args)
{
	global $app;
	return function() use ($app, $args)
	{
		$req = $app->request();
		$incoming = array();
		if (is_array($req->getBody())) {
			$incoming = $req->getBody();
		} else if ($req->isGet()) {
			$incoming = $req->get();
		} else if ($req->isPut()) {
			$incoming = $req->put();
		} else if ($req->isPost()) {
			$incoming = $req->post();
		}

		$req->args = array();

		try {
			foreach ($args as $name => $validator) {
				if (!array_key_exists($name, $incoming)) {
					if ($validator['optional']) {
						$req->args[$name] = NULL;
					} else {
						throw new Exception('Parameter does not exist');
					}
				} else {
					$req->args[$name] = $validator['validator']($incoming[$name]);
				}
			}
		} catch (Exception $e) {
			$app->halt(400, 'Invalid argument');
		}
	};
}

function validator_string($optional = NULL)
{
	if (is_null($optional))
		$optional = false;

	return array('optional' => $optional, 'validator' =>
		function($value)
		{
			if (is_string($value)) {
				return $value;
			}

			throw new Exception('Could not validate string');
		}
	);
}

//function validator_number($optional = false, $min = -INF, $max = INF, $integer = false)
function validator_number($min = NULL, $max = NULL, $integer = NULL, $optional = NULL)
{
	if (is_null($min))
		$min = -INF;
	if (is_null($max))
		$max = INF;
	if (is_null($integer))
		$integer = false;
	if (is_null($optional))
		$optional = false;

	return array('optional' => $optional, 'validator' =>
		function($value) use ($min, $max, $integer)
		{
			if (!is_numeric($value)) {
				throw new Exception('Could not validate number');
			}

			if ($integer) {
				$value = intval($value, 10);
			} else {
				$value = floatval($value);
			}

			if (is_numeric($value) && $value >= $min && $value <= $max) {
				return $value;
			}

			throw new Exception('Could not validate number');
		}
	);
}

function validator_integer($min = NULL, $max = NULL, $optional = NULL)
{
	return validator_number($min, $max, true, $optional);
}

function validator_discrete($values, $optional = NULL)
{
	if (is_null($optional))
		$optional = false;

	return array('optional' => $optional, 'validator' =>
		function($value) {
			if (in_array($value, $values)) {
				return $value;
			}

			throw new Exception('Could not validate discrete value');
		}
	);
}

function validator_date($optional = false)
{
	return array('optional' => $optional, 'validator' =>
		function($value) {
			return new \DateTime($value);
		}
	);
}

function validator_list($values, $optional = false)
{
	return array('optional' => $optional, 'validator' =>
		function($value) use ($values) {
			if (is_string($value)) {
				$value = array_map('trim', explode(',', $value));
			}

			if (!is_array($value)) {
				throw new Exception('Invalid format for list');
			}

			$out = array();
			foreach ($value as $item) {
				if (!in_array($item, $values)) {
					throw new Exception('Invalid item');
				}
				$out[] = $item;
			}
			return array_unique($out);
		}
	);
}
