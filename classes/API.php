<?php

class API extends \Basic\Basic {
	
	// test page
	public static function test() {
		
		$db = parent::getDb();

		$id = $db->select("SELECT * FROM users WHERE id = {?}", array(1));

		$res = array(
			'test' => $id
		);
		
		echo json_encode($res);

		// echo phpinfo();
		
	}


	// Выбрать статус пользователя
	public static function addUser() {

		$db = parent::getDb();
		$new_user = null;

		parent::search($_POST['search'], $_POST['vk_user_id']);

		$user = $db->select("SELECT * FROM users WHERE id = {?}", array($_POST['vk_user_id']));

		if (is_array($user) && count($user) > 0) {

			$new_user = false;

		} else {

			$db->query("INSERT IGNORE INTO users SET id = {?}", array($_POST['vk_user_id']));
			$new_user = true;

		}

		echo json_encode(
			array(
				'error' => false,
				'error_type' => null,
				'new_user' => $new_user
			)
		);
		
	}
	

	// Страница со списком подключенных сообществ организатора
	public static function connectGroup() {

		$db = parent::getDb();

		parent::search($_POST['search'], $_POST['vk_user_id']);
		parent::checkGroupAccess($_POST['vk_user_id'], $_POST['group_id'], $_POST['access_token']);

		echo json_encode(
			array(
				'error' => false,
				'error_type' => null
			)
		);

	}


	// Список все сообществ в котором пользователь админ
	public static function getConnectedGroups() {
		
		$db = parent::getDb();

		parent::search($_POST['search'], $_POST['vk_user_id']);

		// разбиваем строку на массив
		$groups = explode(",", $_POST['groups']);

		// берем ранее подключенные группы
		$connectedGroups = $db->select("SELECT group_id FROM connected_groups WHERE user_id = {?}",
		array($_POST['vk_user_id']));
		$count = count($connectedGroups);

		// удаляем юзера из базы с подключенными группами, если он уже не админ
		for ($i = 0; $i < $count; $i++) {

			$key = array_search($connectedGroups[$i]['group_id'], $groups);

			if ($key === false) {
				
				$db->query("DELETE FROM connected_groups WHERE group_id = {?} AND user_id = {?}",
				array($connectedGroups[$i]['group_id'], $_POST['vk_user_id']));

			}

		}

		// берем подключенные группы юзера
		$connectedGroups = $db->select("SELECT group_id FROM connected_groups WHERE user_id = {?}",
		array($_POST['vk_user_id']));

		$count = count($connectedGroups);
		$result = [];

		for ($i = 0; $i < $count; $i++) {
			array_push($result, $connectedGroups[$i]['group_id']);
		}

		echo json_encode(
			array(
				'error' => false,
				'error_type' => null,
				'connected_groups' => $result
			)
		);

	}


	// загрузка изображения в группу
	public static function loadImage() {

		$db = parent::getDb();

		parent::search($_POST['search'], $_POST['vk_user_id']);

		$error = false;
		$errorType = null;
		$image = null;
		$attachments = null;

		$check = getimagesize($_FILES["file"]["tmp_name"]);

		if ($check) {

			global $config;

			// получаем ссылку для загрузки картинки
			$request_params = array(
				'group_id' => $config['group_id'],
				'lang' => "ru", 
				'v' => '5.92',
				'access_token' => $config['group_token']
			);
			$get_params = http_build_query($request_params);
			$resultAPImethod = json_decode(file_get_contents('https://api.vk.com/method/photos.getWallUploadServer?'. $get_params), true);

			if (!$resultAPImethod['error']) {

				// загружаем картинку на ВК сервер
				$url = $resultAPImethod['response']['upload_url'];

				$file = ['file' => new \CurlFile($_FILES["file"]["tmp_name"], 'image/*', 'img.jpg')];

				$ch = curl_init();
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_SAFE_UPLOAD, true);
				curl_setopt($ch, CURLOPT_URL, $url);
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: multipart/form-data; charset=UTF-8'));
				curl_setopt($ch, CURLOPT_POSTFIELDS, $file);
				$result = curl_exec($ch);
				curl_close($ch);

				$photo = json_decode($result, true);
				
				$request_params = array(
					'group_id' => $config['group_id'],
					'lang' => "ru",
					'v' => '5.92',
					'access_token' => $config['group_token'],
					'server' => $photo['server'],
					'photo' => $photo['photo'],
					'hash' => $photo['hash']
				);
				$get_params = http_build_query($request_params);
				$resultAPImethod = json_decode(file_get_contents('https://api.vk.com/method/photos.saveWallPhoto?'. $get_params), true);

				$image = $resultAPImethod['response'][0]['sizes'];

				// на стену группы
				$attachments = 'photo'.$resultAPImethod['response'][0]['owner_id'].'_'.$resultAPImethod['response'][0]['id'];

				$request_params = array(
					'owner_id' => -$config['group_id'],
					'attachments' => $attachments,
					'access_token' => $config['group_token'],
					'from_group' => '1',
					'v' => '5.103'
				);
				$get_params = http_build_query($request_params);
				file_get_contents('https://api.vk.com/method/wall.post?'. $get_params);

				$request_params = array(
					'owner_id' => -$config['group_id'],
					'album_id' => 'wall',
					'access_token' => $config['group_token'],
					'rev' => true,
					'v' => '5.120'
				);
				$get_params = http_build_query($request_params);
				$response = json_decode(file_get_contents('https://api.vk.com/method/photos.get?'. $get_params), true);

				$attachments = 'photo-'.$config['group_id'].'_'.$response['response']['items'][0]['id'];

			} else {

				$error = true;
				$errorType = 5;

			}
			
		} else {

			$error = true;
			$errorType = 4;

		}
		
		echo json_encode(
			array(
				'error' => $error,
				'error_type' => $errorType,
				'image' => $image,
				'attachments' => $attachments
			)
		);
	}

	// добавление конкурса
	public static function addContest() {

		$db = parent::getDb();

		parent::search($_POST['search'], $_POST['vk_user_id']);

		$error = false;
		$errorType = null;
		$errorParameter = null;

		$settingsContest = json_decode($_POST['settingsContest'], true);
		$settingsStory = json_decode($_POST['settingsStory'], true);
		$settingsPublicWall = json_decode($_POST['settingsPublicWall'], true);

		// проверяем подключенна ли группы и является ли юзер админом
		parent::checkConnectedGroups($_POST['group_id'], $_POST['vk_user_id']);

		// проверка передаваемых параметров
		if (gettype($settingsContest['nameContest']) !== 'string' || strlen($settingsContest['nameContest']) < 10) {
			$error = true;
			$errorType = 7;
			$errorParameter = 'nameContest';
		}

		if (!filter_var($settingsContest['contestBanner'], FILTER_VALIDATE_URL)) {
			$error = true;
			$errorType = 7;
			$errorParameter = 'contestBanner';
		}

		if (!filter_var($settingsContest['directoryIcon'], FILTER_VALIDATE_URL)) {
			$error = true;
			$errorType = 7;
			$errorParameter = 'directoryIcon';
		}

		if (gettype($settingsContest['titleContest']) !== 'string' || strlen($settingsContest['titleContest']) < 10) {
			$error = true;
			$errorType = 7;
			$errorParameter = 'titleContest';
		}

		if (gettype($settingsContest['descriptionContest']) !== 'string' || strlen($settingsContest['descriptionContest']) < 100) {
			$error = true;
			$errorType = 7;
			$errorParameter = 'descriptionContest';
		}

		if (!is_array($settingsContest['namesPrizes'])) {
			$error = true;
			$errorType = 7;
			$errorParameter = 'namesPrizes';
		}

		if (is_array($settingsContest['namesPrizes'])) {

			$count = count($settingsContest['namesPrizes']);

			if ($count === 0) {

				$error = true;
				$errorType = 7;
				$errorParameter = 'namesPrizes';

			} else {

				for ($i = 0; $i < $count; $i++) {

					$namePrize = $settingsContest['namesPrizes'][$i];

					if (gettype($namePrize) !== 'string' || strlen($namePrize) < 1) {
						$error = true;
						$errorType = 7;
						$errorParameter = 'namePrize';
						break;
					}

				}

			}

		}

		if ($settingsContest['idTipic'] === '') {
			$error = true;
			$errorType = 7;
			$errorParameter = 'idTipic';
		} else $settingsContest['idTipic'] = (int) $settingsContest['idTipic'];
		
		if (gettype($settingsContest['conditionsPostStory']) !== 'boolean') {
			$error = true;
			$errorType = 7;
			$errorParameter = 'conditionsPostStory';
		}

		if (gettype($settingsContest['conditionsSubscribeToGroup']) !== 'boolean') {
			$error = true;
			$errorType = 7;
			$errorParameter = 'conditionsSubscribeToGroup';
		}

		if (!filter_var($settingsStory['backgroundStory'], FILTER_VALIDATE_URL)) {
			$error = true;
			$errorType = 7;
			$errorParameter = 'backgroundStory';
		}

		if ($settingsContest['conditionsPostWall'] && strpos($settingsPublicWall['backgroundWall'], 'photo') === false) {
			$error = true;
			$errorType = 7;
			$errorParameter = 'backgroundWall';
		}

		// если все параметры прошли проверку, то добавляем конкурс
		if (!$error) {

			$id = $db->query("INSERT IGNORE INTO contests SET 
			group_id = {?},
			user_id = {?},
			nameContest = {?},
			contestBanner = {?},
			directoryIcon = {?},
			timeEndContest = {?},
			dateEndContest = {?},
			titleContest = {?},
			descriptionContest = {?},
			idTipic = {?},
			conditionsPostStory = {?},
			conditionsPostWall = {?},
			conditionsSubscribeToGroup = {?},
			conditionsSubscribeToNotifications = {?},
			backgroundType = {?},
			backgroundStory = {?},
			movingBackground = {?},
			buttonStory = {?},
			degreeRotation = {?},
			stickerWidth = {?},
			valueVertical = {?},
			valueHorizontal = {?},
			valueAlignment = {?},
			textWall = {?},
			backgroundWall = {?}",
			array(
				$_POST['group_id'],
				$_POST['vk_user_id'],
				$settingsContest['nameContest'],
				$settingsContest['contestBanner'],
				$settingsContest['directoryIcon'],
				$settingsContest['timeEndContest'],
				$settingsContest['dateEndContest'],
				$settingsContest['titleContest'],
				$settingsContest['descriptionContest'],
				$settingsContest['idTipic'],
				$settingsContest['conditionsPostStory'],
				$settingsContest['conditionsPostWall'],
				$settingsContest['conditionsSubscribeToGroup'],
				$settingsContest['conditionsSubscribeToNotifications'],
				$settingsStory['backgroundType'],
				$settingsStory['backgroundStory'],
				$settingsStory['movingBackground'],
				$settingsStory['buttonStory'],
				$settingsStory['degreeRotation'],
				$settingsStory['stickerWidth'],
				$settingsStory['valueVertical'],
				$settingsStory['valueHorizontal'],
				$settingsStory['valueAlignment'],
				$settingsPublicWall['textWall'],
				$settingsPublicWall['backgroundWall']
			));

			$count = count($settingsContest['namesPrizes']);

			if ($settingsPublicWall['textWall'] === '' || $settingsPublicWall['textWall'] === null || !$settingsPublicWall['textWall']) {

				$text = 'Участвуй по ссылке: https://vk.com/app7486100#' . $id;
				$db->query("UPDATE contests SET textWall = {?} WHERE id = {?}", array($text, $id));

			} else {

				$text = $settingsPublicWall['textWall'] . ' \n Участвуй по ссылке: https://vk.com/app7486100#' . $id;
				$db->query("UPDATE contests SET textWall = {?} WHERE id = {?}", array($text, $id));

			}

			for ($i = 0; $i < $count; $i++) {

				$name = $settingsContest['namesPrizes'][$i];
				$db->query("INSERT IGNORE INTO names_prizes SET contest_id = {?}, name = {?}", array($id, $name));

			}

		}

		echo json_encode(
			array(
				'error' => $error,
				'error_type' => $errorType,
				'error_parameter' => $errorParameter
			)
		);
	}


	// конкурсы
	public static function getContests() {

		$db = parent::getDb();

		parent::search($_POST['search'], $_POST['vk_user_id']);

		$error = false;
		$errorType = null;
		$contests = array();
		$result = array();

		if ($_POST['type'] === 'group') {

			$result = $db->select("SELECT * FROM contests WHERE group_id = {?}", array($_POST['group_id']));

		} else if ($_POST['type'] === 'topic') {

			$result = $db->select("SELECT * FROM contests WHERE idTipic = {?}", array($_POST['topic_id']));

		} else if ($_POST['type'] === 'user') {

			$result = $db->select("SELECT contests.* FROM participants
			LEFT JOIN contests ON contests.id = participants.contest_id
			WHERE participants.user_id = {?} AND participants.done = {?}",
			array($_POST['user_id'], true));

		}
		

		$count = count($result);

		for ($i = 0; $i < $count; $i++) {

			$countParticipants = $db->select("SELECT COUNT(*) FROM participants WHERE contest_id = {?} AND done = {?}",
			array($result[$i]['id'], true))[0]['COUNT(*)'];

			$topPrize = $db->select("SELECT name FROM names_prizes WHERE contest_id = {?}",
			array($result[$i]['id']))[0]['name'];

			$active = true;
			$time = $result[$i]['dateEndContest'] . ' ' . $result[$i]['timeEndContest'];
			$date = strtotime($time);

			if (time() >= $date) $active = false;

			$contest = array(
				'idContest' => (int) $result[$i]['id'],
				'nameContest' => $result[$i]['nameContest'],
				'directoryIcon' => $result[$i]['directoryIcon'],
				'countParticipants' => (int) $countParticipants,
				'topPrize' => $topPrize,
				'activeContest' => $active
			);

			array_push($contests, $contest);

		}

		echo json_encode(
			array(
				'error' => $error,
				'error_type' => $errorType,
				'contests' => $contests
			)
		);
	}
	

	// страница не существует
	public static function getDataContest() {

		$db = parent::getDb();

		parent::search($_POST['search'], $_POST['vk_user_id']);

		$error = false;
		$errorType = null;
		$contest = array();

		$result = $db->select("SELECT * FROM contests WHERE id = {?}", array($_POST['contest_id']));

		if (is_array($result) && count($result) > 0) {

			$active = true;
			$time = $result[0]['dateEndContest'] . ' ' . $result[0]['timeEndContest'];
			$date = strtotime($time);

			if (time() >= $date) $active = false;

			$settingsContest = array(
				'nameContest' => $result[0]['nameContest'],
				'contestBanner' => $result[0]['contestBanner'],
				'directoryIcon' => $result[0]['directoryIcon'],
				'timeEndContest' => $result[0]['timeEndContest'],
				'dateEndContest' => $result[0]['dateEndContest'],
				'titleContest' => $result[0]['titleContest'],
				'descriptionContest' => $result[0]['descriptionContest'],
				'idTipic' => (int) $result[0]['idTipic'],
				'conditionsPostStory' => (boolean) $result[0]['conditionsPostStory'],
				'conditionsPostWall' => (boolean) $result[0]['conditionsPostWall'],
				'conditionsSubscribeToGroup' => (boolean) $result[0]['conditionsSubscribeToGroup'],
				'conditionsSubscribeToNotifications' => (boolean) $result[0]['conditionsSubscribeToNotifications']
			);

			// призы или победители
			if ($active) {

				$prizes = $db->select("SELECT name FROM names_prizes WHERE contest_id = {?}",
				array($_POST['contest_id']));

				$count = count($prizes);
				$namesPrizes = array();

				for ($i = 0; $i < $count; $i++) {
					array_push($namesPrizes, $prizes[$i]['name']);
				}

				$settingsContest['namesPrizes'] = $namesPrizes;

			} else {

				$settingsContest['winners'] = parent::getWinners($result[0]);

			}

			$settingsStory = array(
				'backgroundType' => $result[0]['backgroundType'],
				'backgroundStory' => $result[0]['backgroundStory'],
				'movingBackground' => (boolean) $result[0]['movingBackground'],
				'buttonStory' => $result[0]['buttonStory'],
				'degreeRotation' => (int) $result[0]['degreeRotation'],
				'stickerWidth' => (int) $result[0]['stickerWidth'],
				'valueVertical' => (int) $result[0]['valueVertical'],
				'valueHorizontal' => (int) $result[0]['valueHorizontal'],
				'valueAlignment' => $result[0]['valueAlignment']
			);

			$settingsPublicWall = array(
				'textWall' => $result[0]['textWall'],
				'backgroundWall' => $result[0]['backgroundWall']
			);

			$contest = array(
				'settingsContest' => $settingsContest,
				'settingsStory' => $settingsStory,
				'settingsPublicWall' => $settingsPublicWall,
				'active' => $active
			);


		} else {

			$error = true;
			$errorType = 8;

		}

		echo json_encode(
			array(
				'error' => $error,
				'error_type' => $errorType,
				'contest' => $contest
			)
		);
	}
	

	// статусы выполнения условий конкурсов
	public static function sendConditionStatus() {

		$db = parent::getDb();

		parent::search($_POST['search'], $_POST['vk_user_id']);

		$error = false;
		$errorType = null;

		if ($_POST['nameStatus'] === 'conditionStories' ||
			$_POST['nameStatus'] === 'conditionWall' ||
			$_POST['nameStatus'] === 'conditionSubscribeToGroup' ||
			$_POST['nameStatus'] === 'conditionSubscribeToNotifications'
			) {

			$contest = $db->select("SELECT * FROM contests WHERE id = {?}", array($_POST['idContest']));

			if (is_array($contest) && count($contest) > 0) {

				$participant = $db->select("SELECT * FROM participants WHERE contest_id = {?} AND user_id = {?}",
				array($_POST['idContest'], $_POST['vk_user_id']));

				// есть ли юзер в таблице участников
				if (count($participant) === 0) {

					$db->query("INSERT IGNORE INTO participants SET contest_id = {?}, user_id = {?}",
					array($_POST['idContest'], $_POST['vk_user_id']));

				}

				if ($_POST['nameStatus'] !== 'conditionWall') { 

					$status = $_POST['nameStatus'];
					$db->query("UPDATE participants SET $status = {?} WHERE contest_id = {?} AND user_id = {?}",
					array(true, $_POST['idContest'], $_POST['vk_user_id']));
				
					// проверка условий
					parent::checkContestCondition($contest[0], $_POST['vk_user_id']);

				} else {

					if ($_POST['valueStatus'] > 0) {
						
						$db->query("UPDATE participants SET conditionWall = {?} WHERE contest_id = {?} AND user_id = {?}",
						array($_POST['valueStatus'], $_POST['idContest'], $_POST['vk_user_id']));

						// проверка условий
						parent::checkContestCondition($contest[0], $_POST['vk_user_id']);

					} else {

						$error = true;
						$errorType = 10;
	
					}

				}

			} else {

				$error = true;
				$errorType = 8;
			
			}

		} else {

			$error = true;
			$errorType = 9;

		}

		echo json_encode(
			array(
				'error' => $error,
				'error_type' => $errorType
			)
		);

	}


	// статусы выполнения условий конкурсов
	public static function getConditionsStatuses() {
		
		$db = parent::getDb();

		parent::search($_POST['search'], $_POST['vk_user_id']);

		$error = false;
		$errorType = null;
		$conditionStatus = array(
			'conditionStories' => false,
			'conditionWall' => false,
			'conditionSubscribeToGroup' => false,
			'conditionSubscribeToNotifications' => false
		);

		$contest = $db->select("SELECT * FROM contests WHERE id = {?}", array($_POST['contest_id']));

		if (is_array($contest) && count($contest) > 0) {

			if (!(boolean) $contest[0]['conditionsPostStory']) $conditionStatus['conditionStories'] = null;
			if (!(boolean) $contest[0]['conditionsPostWall']) $conditionStatus['conditionWall'] = null;
			if (!(boolean) $contest[0]['conditionsSubscribeToGroup']) $conditionStatus['conditionSubscribeToGroup'] = null;
			if (!(boolean) $contest[0]['conditionsSubscribeToNotifications']) $conditionStatus['conditionSubscribeToNotifications'] = null;

			$user = $db->select("SELECT * FROM participants WHERE user_id = {?} AND contest_id = {?}",
			array($_POST['vk_user_id'], $_POST['contest_id']));

			if (is_array($user) && count($user) > 0) {

				if ((boolean) $contest[0]['conditionsPostStory']) {
					$conditionStatus['conditionStories'] = (boolean) $user[0]['conditionStories'];
				}

				if ((boolean) $contest[0]['conditionsPostWall']) {
					$conditionStatus['conditionWall'] = (boolean) $user[0]['conditionWall'];
				}
				
				if ((boolean) $contest[0]['conditionsSubscribeToGroup']) {
					$conditionStatus['conditionSubscribeToGroup'] = (boolean) $user[0]['conditionSubscribeToGroup'];
				}

				if ((boolean) $contest[0]['conditionsSubscribeToNotifications']) {
					$conditionStatus['conditionSubscribeToNotifications'] = (boolean) $user[0]['conditionSubscribeToNotifications'];
				}

			}

		} else {

			$error = true;
			$errorType = 8;
			
		}

		echo json_encode(
			array(
				'error' => $error,
				'error_type' => $errorType,
				'conditionStatus' => $conditionStatus
			)
		);

	}

	
	// получить идентификаторы победителей конкурсов
	public static function getContestWinners() {
		
		$db = parent::getDb();

		$error = false;
		$errorType = null;
		$winners = array();
		
		$contest = $db->select("SELECT * FROM contests WHERE id = {?}", array($_POST['idContest']));

		if (is_array($contest) && count($contest) > 0) {

			$active = true;
			$time = $contest[0]['dateEndContest'] . ' ' . $contest[0]['timeEndContest'];
			$date = strtotime($time);

			if (time() >= $date) $active = false;

			if (!$active) {

				$winners = parent::getWinners($contest[0]);

			} else {

				$error = true;
				$errorType = 11;

			}

		} else {

			$error = true;
			$errorType = 8;

		}

		echo json_encode(
			array(
				'error' => $error,
				'error_type' => $errorType,
				'winners' => $winners
			)
		);
	}
	
	// страница не существует
	public static function notFound() {
		
		echo json_encode(
			array(
				'error' => true,
				'error_type' => 2
			)
		);
		
	}
	
}