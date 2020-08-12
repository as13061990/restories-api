<?php
header("Access-Control-Allow-Origin: *");

$config = include('config.php');

// include classes
require_once('classes/Db.php');
require_once('classes/Basic.php');
require_once('classes/API.php');
require_once('classes/RouterLite.php');

// routing
RouterLite::addRoute('/test', 'API/test');
RouterLite::addRoute('/addUser', 'API/addUser');
RouterLite::addRoute('/connectGroup', 'API/connectGroup');
RouterLite::addRoute('/getConnectedGroups', 'API/getConnectedGroups');
RouterLite::addRoute('/loadImage', 'API/loadImage');
RouterLite::addRoute('/addContest', 'API/addContest');
RouterLite::addRoute('/getContests', 'API/getContests');
RouterLite::addRoute('/getDataContest', 'API/getDataContest');
RouterLite::addRoute('/sendConditionStatus', 'API/sendConditionStatus');


RouterLite::addRoute('/notFound', 'API/notFound');
RouterLite::dispatch();

?>