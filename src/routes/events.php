<?php

$JOIN_MAP = array(
	'eSUC' => 'LEFT JOIN
	           (SELECT COUNT(*) AS suCount, Sum(CASE WHEN \'yes\'=drive THEN 1 END) AS driverCount, eventID FROM EventSignups GROUP BY eventID)
		   as eSUC USING (eventID)',
	'Com' => 'LEFT JOIN Committees AS Com ON(eInfo.committeeID=Com.committeeID)',
	'slInfo' => 'LEFT JOIN PeopleInfo AS slInfo ON eInfo.unqLeader = slInfo.unq',
	'crInfo' => 'LEFT JOIN PeopleInfo AS crInfo ON eInfo.unqCreator = crInfo.unq',
	'chairYearly' => 'LEFT JOIN PeopleYearly AS chairYearly ON(eInfo.committeeID=chairYearly.committeeID AND chairYearly.committeeLevel=\'Chair\' AND chairYearly.year=IF(MONTH(NOW()) < 4, YEAR(NOW()) - 1, YEAR(NOW())))',
	'chairInfo' => 'LEFT JOIN PeopleInfo AS chairInfo ON chairYearly.unq=chairInfo.unq',

);
function get_event_sql_parameters($fields)
{
	global $JOIN_MAP;
	$sql_fields = array();
	$sql_joins = array();

	$sql_fields[] = 'eInfo.eventID';

	foreach ($fields as $field) {
		if ($field == 'start') {
			$sql_fields[] = 'eInfo.date';
			$sql_fields[] = 'eInfo.startTime';
		} else if ($field == 'end') {
			$sql_fields[] = 'eInfo.date';
			$sql_fields[] = 'eInfo.endTime';
		} else if (in_array($field, array('suCount', 'driverCount'))) {
			$sql_fields[] = 'eSUC.' . $field;
			$sql_joins[] = 'eSUC';
		} else if (in_array($field, array('committeeName'))) {
			$sql_fields[] = 'Com.' . $field;
			$sql_joins[] = 'Com';
		} else if (in_array($field, array('leader_first_name', 'leader_last_name', 'leader_email'))) {
			$field_name = substr($field, 7);
			$sql_fields[] = 'slInfo.' . $field_name . ' AS ' . $field;
			$sql_joins[] = 'slInfo';
		} else if (in_array($field, array('creator_first_name', 'creator_last_name', 'creator_email'))) {
			$field_name = substr($field, 8);
			$sql_fields[] = 'crInfo.' . $field_name . ' AS ' . $field;
			$sql_joins[] = 'crInfo';
		} else if (in_array($field, array('chair_first_name', 'chair_last_name', 'chair_email'))) {
			$field_name = substr($field, 6);
			$sql_fields[] = 'chairInfo.' . $field_name . ' AS ' . $field;
			$sql_joins[] = 'chairYearly';
			$sql_joins[] = 'chairInfo';
		} else {
			$sql_fields[] = 'eInfo.' . $field;
		}
	}

	$sql_joins = array_map(function ($join) { return $JOIN_MAP[$join]; }, array_unique($sql_joins));

	return array(array_unique($sql_fields), $sql_joins);
}

function get_event_query($fields)
{
	list($sql_fields, $sql_joins) = get_event_sql_parameters($fields);

	return 'SELECT ' . implode(',', $sql_fields) .
	       ' FROM EventInfo AS eInfo ' . implode(' ', $sql_joins) . ' ';
}

$app->group('/events', function() use ($app) {
	$EVENT_FIELDS = array(
		'eventID',

		'name',
		'description',
		'start',
		'end',

		'meetingPlace',
		'location',
		'unqLeader',
		'unqCreator',
		'driver',
		'primaryType',
		'secondaryType',
		'status',
		'hoursSubmitted',
		'committeeID',
		'color',
		'maxSignup',
		'organization',
		'imageID',

		'suCount',
		'driverCount',

		'committeeName',

		'leader_first_name',
		'leader_last_name',
		'leader_email',

		'creator_first_name',
		'creator_last_name',
		'creator_email',

		'chair_first_name',
		'chair_last_name',
		'chair_email',
	);

	$EVENT_FIELDS_BASIC = array(
		'eventID',
		'name',
		'description',
		'start',
		'end',
		'meetingPlace',
		'location',
		'primaryType',
		'secondaryType',
		'status',
	);

	$app->get(
		'(/)',
		validator(array(
			'filter' => validator_discrete(array('meetings', 'all'), true),
			'start' => validator_date(true),
			'end' => validator_date(true),
			'fields' => validator_list($EVENT_FIELDS, true),
		)),
		function() use ($app, $EVENT_FIELDS_BASIC) {
			$req = $app->request();
			$start = $req->args['start'];
			$end = $req->args['end'];
			$fields = $req->args['fields'];
			if (is_null($start)) {
				$start = new \DateTime('first day of this month 00:00:00');
			}
			if (is_null($end)) {
				$end = new \DateTime('first day of next month 00:00:00');
			}
			if (is_null($fields)) {
				$fields = $EVENT_FIELDS_BASIC;
			}

			$query = get_event_query($fields);
			$query .= ' WHERE eInfo.date>=:start AND eInfo.date<:end' .
				' ORDER BY eInfo.date, eInfo.priority DESC, eInfo.startTime;';
			$stmt = $app->db->prepare($query);
			$stmt->bindValue(':start', $start->format('Y-m-d'), \PDO::PARAM_STR);
			$stmt->bindValue(':end', $end->format('Y-m-d'), \PDO::PARAM_STR);
			$stmt->execute();

			$out = array();
			while ($row = $stmt->fetch()) {
				foreach (array('start', 'end') as $col) {
					if (in_array($col, $fields)) {
						$time = new \DateTime($row['date'] . ' ' . $row[$col . 'Time']);
						$row[$col] = $time->format(\DateTime::ISO8601);
					}
				}
				unset($row['date']);
				unset($row['startTime']);
				unset($row['endTime']);

				foreach (array('driver', 'hoursSubmitted') as $col) {
					if (!in_array($col, $fields)) {
						continue;
					}

					if ($row[$col] == 'yes') {
						$row[$col] = true;
					} else if ($row[$col] == 'no') {
						$row[$col] = false;
					}
				}

				foreach (array('eventID', 'suCount', 'driverCount', 'committeeID') as $col) {
					if (!in_array($col, $fields)) {
						continue;
					}
					$row[$col] = intval($row[$col]);
				}

				$out[] = $row;
			}

			json_output($out);
		}
	);


	$app->get(
		'/:id',
		validator(array(
			'fields' => validator_list($EVENT_FIELDS, true),
		)),
		function($eventID) use ($app, $EVENT_FIELDS_BASIC) {
			$req = $app->request();
			$fields = $req->args['fields'];
			if (is_null($fields)) {
				$fields = $EVENT_FIELDS_BASIC;
			}
			if (!is_numeric($eventID)) {
				$app->halt(400, 'Invalid event id');
			}

			$query = get_event_query($fields);
			$query .= ' WHERE eInfo.eventID=:eventID;';
			$stmt = $app->db->prepare($query);
			$stmt->bindValue(':eventID', $eventID, \PDO::PARAM_INT);
			$stmt->execute();

			if (!$stmt->rowCount()) {
				$app->halt(404, 'Event does not exist');
			}

			$out = $stmt->fetch();

			json_output($out);
		}
	);
});
