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

		if (gettype($settingsContest['titleContes']) !== 'string' || strlen($settingsContest['titleContes']) < 10) {
			$error = true;
			$errorType = 7;
			$errorParameter = 'titleContes';
		}

		if (gettype($settingsContest['descriptionContest']) !== 'string' || strlen($settingsContest['descriptionContest']) < 100) {
			$error = true;
			$errorType = 7;
			$errorParameter = 'titleContes';
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

		if (gettype($settingsContest['idTipic']) !== 'integer') {
			$error = true;
			$errorType = 7;
			$errorParameter = 'idTipic';
		}
		
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

		if (!filter_var($settingsPublicWall['backgroundWall'], FILTER_VALIDATE_URL)) {
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
			titleContes = {?},
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
				$settingsContest['titleContes'],
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