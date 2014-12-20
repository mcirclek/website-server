<?php
namespace CKIUM;

class User {
	static public function get_user($app, $unq)
	{
		$db = $app->db;

		$stmt = $db->prepare('SELECT first_name, last_name FROM PeopleInfo WHERE unq=:unq;');
		$stmt->bindValue(':unq', $unq, \PDO::PARAM_STR);
		$stmt->execute();

		$memberInfo = $stmt->fetch();

		$curMonth = date('m');
		$curYear = $app->current_year;
		$lastYear = $curYear - 1;
		$nextYear = $curYear + 1;

		$stmt = $db->prepare('SELECT year, userLevel, committeeID, committeeName, committeeLevel
			FROM PeopleYearly
			LEFT JOIN Committees USING(committeeID)
			WHERE unq=:unq AND (year=:year1 OR year=:year2 OR year=:year3)
			ORDER BY year DESC');
		$stmt->bindValue(':unq', $unq, \PDO::PARAM_STR);
		$stmt->bindValue(':year1', $lastYear, \PDO::PARAM_INT);
		$stmt->bindValue(':year2', $curYear, \PDO::PARAM_INT);
		$stmt->bindValue(':year3', $nextYear, \PDO::PARAM_INT);
		$stmt->execute();

		$accessLevel = 0;
		$committeeID = 0;
		$committeeName = '';
		$committeePosition = '';
		$years = array();

		while($row = $stmt->fetch()) {
			$years[$row['year']] = $row;
		}

		if($curMonth <= 3) {
			if($years[$curYear]) {
				$committeeID = $years[$curYear]['committeeID'];
				$committeeName = $years[$curYear]['committeeName'];
				$committeePosition = $years[$curYear]['committeeLevel'];
				$accessLevel = $years[$curYear]['userLevel'];
			}
			if($years[$nextYear]) {
				if($years[$nextYear]['userLevel'] > $accessLevel)
					$accessLevel = $years[$nextYear]['userLevel'];
			}
		} else if($curMonth <= 4) { //april
			if($years[$curYear]) {
				$accessLevel = $years[$curYear]['userLevel'];
				$committeeID = $years[$curYear]['committeeID'];
				$committeeName = $years[$curYear]['committeeName'];
				$committeePosition = $years[$curYear]['committeeLevel'];
			} else if($years[$lastYear]) {
				$accessLevel = $years[$lastYear]['userLevel'];
				$committeeID = $years[$lastYear]['committeeID'];
				$committeeName = $years[$lastYear]['committeeName'];
				$committeePosition = $years[$lastYear]['committeeLevel'];
			}

			if($years[$lastYear] && ($years[$lastYear]['userLevel'] > $accessLevel)) {
				$accessLevel = $years[$lastYear]['userLevel'];
			}
		} else if($curMonth <= 9) { //summer
			if($years[$curYear]) {
				$accessLevel = $years[$curYear]['userLevel'];
				$committeeID = $years[$curYear]['committeeID'];
				$committeeName = $years[$curYear]['committeeName'];
				$committeePosition = $years[$curYear]['committeeLevel'];
			} else if($years[$lastYear]) {
				$accessLevel = $years[$lastYear]['userLevel'];
			}
		} else {
			if($years[$curYear]) {
				$accessLevel = $years[$curYear]['userLevel'];
				$committeeID = $years[$curYear]['committeeID'];
				$committeeName = $years[$curYear]['committeeName'];
				$committeePosition = $years[$curYear]['committeeLevel'];
			}
		}

		//TODO add more and perfect this
		$out = array(
			'unq' => $unq,
			'access_level' => $accessLevel,
			'first_name' => $memberInfo['first_name'],
			'last_name' => $memberInfo['last_name'],
			'id_committee' => $committeeID,
			'committee_name' => $committeeName,
			'committee_position' => $committeePosition,
		);

		return $out;
	}

	static public function authenticate($app, $unq, $pw)
	{
		$stmt = $app->db->prepare('SELECT password, salt FROM PeopleInfo WHERE unq=:unq;');
		$stmt->bindValue(':unq', $unq, \PDO::PARAM_STR);
		$stmt->execute();
		$row = $stmt->fetch();
		if (!$row) {
			return false;
		}

		$hash = hash('sha256', $row['salt'] . hash('sha256', $pw));
		if ($hash == $row['password']) {
			return true;
		}

		return false;
	}

	static public function startSession($app, $unq)
	{
		//store cookie
		$plain = hash('sha256', uniqid(mt_rand(0, 9223372036854775807), true));
		$encrypt = hash('sha256', $plain);
		$expTime = strtotime('+1 year');
		$expSQL = date('Y-m-d H:i:s', $expTime);

		$query = 'INSERT INTO `SessionKeys` (`unq`, `key`, `expiration`) VALUES (:unq, :key, :expiration);';
		$stmt = $app->db->prepare($query);
		$stmt->bindValue(':unq', $unq, \PDO::PARAM_STR);
		$stmt->bindValue(':key', $encrypt, \PDO::PARAM_STR);
		$stmt->bindValue(':expiration', $expSQL, \PDO::PARAM_STR);
		$stmt->execute();

		$cookieString = $unq . ':' . $plain;
		$app->setCookie('token', $cookieString, $expTime);
		return $plain;
	}

	static public function makePW($pass)
	{
		$salt = md5(uniqid(rand(), true));
		$salt = substr($salt, 0, 3);
		$hash = hash('sha256', $salt . hash('sha256', $pass));
		return array('password' => $hash, 'salt' => $salt);
	}
}
