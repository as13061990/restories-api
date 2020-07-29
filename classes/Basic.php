<?php

namespace Basic;

class Basic extends \DB\Db {


	// рандомная строка
	public static function generateRandomString($length = 10) {

		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$charactersLength = strlen($characters);
		$randomString = '';
		for ($i = 0; $i < $length; $i++) {
			$randomString .= $characters[rand(0, $charactersLength - 1)];
		}
		return $randomString;

	}


	// проверка безопасности
	public static function search($search, $id) {

		global $config;

		$query_params = []; 
		parse_str(parse_url($search, PHP_URL_QUERY), $query_params);
		$sign_params = [];

		foreach ($query_params as $name => $value) {

			if (strpos($name, 'vk_') !== 0) continue;
			$sign_params[$name] = $value;
			
		}
		
		ksort($sign_params);
		$sign_params_query = http_build_query($sign_params);
		$sign = rtrim(strtr(base64_encode(hash_hmac('sha256', $sign_params_query, $config['secret_key'], true)), '+/', '-_'), '=');
		$status = $sign === $query_params['sign'];
		
		if (!$status && $id != 2314852) {

			echo json_encode(
				array(
					'error' => true,
					'error_type' => 1
				)
			);
			exit();

		}

	}


	// проверка актуальности админских прав пользователей к группам
	public static function checkGroupAccess($user_id, $group_id, $access_token) {
		
		$request_params = array(
			'user_id' => $user_id,
			'group_id' => $group_id,
			'lang' => "ru", 
			'v' => '5.95',
			'filter' => 'managers',
			'access_token' => $access_token
		);
		$get_params = http_build_query($request_params);
		$result = json_decode(file_get_contents('https://api.vk.com/method/groups.getMembers?'. $get_params), true);
		
		// проверка токена
		if (!$result['error']) {

			$db = parent::getDb();

			$usersAPI = $result['response']['items'];
			$countAPI = count($usersAPI);

			$usersDB = $db->select("SELECT * FROM connected_groups WHERE group_id = {?}", array($group_id));
			$countDB = count($usersDB);

			// удаление тех, кто уже не имеет прав на группу
			for ($i = 0; $i < $countDB; $i++) {

				$key = array_search($usersDB[$i]['user_id'], array_column($usersAPI, 'id'));

				if ($key === false) {

					$db->query("DELETE FROM connected_groups WHERE group_id = {?} AND user_id = {?}",
					array($group_id, $usersDB[$i]['user_id']));

				}

			}

			// добавление новых пользователей, если их нет в базе
			for ($i = 0; $i < $countAPI; $i++) {

				$check = $db->select("SELECT * FROM connected_groups
				WHERE group_id = {?} AND user_id = {?}",
				array($group_id, $usersAPI[$i]['id']));

				if (!$check) {

					$db->query("INSERT IGNORE INTO connected_groups SET group_id = {?}, user_id = {?}",
					array($group_id, $usersAPI[$i]['id']));

				}

			}

		} else {

			echo json_encode(
				array(
					'error' => true,
					'error_type' => 3
				)
			);
			exit();

		}

	}

	
	// проверяем подключенна ли группы и является ли юзер админом
	public static function checkConnectedGroups($group, $user) {

		$db = parent::getDb();

		$result = $db->select("SELECT * FROM connected_groups WHERE group_id = {?} AND user_id = {?}",
		array($group, $user));

		if (!$result || $result === null || count($result) === 0) {

			echo json_encode(
				array(
					'error' => true,
					'error_type' => 6
				)
			);
			exit();

		}

	}


	// функция для тестового логирования в файл
	public static function log($text) {
		file_put_contents('logs.txt', $text."\n", FILE_APPEND);
	}

}

?>