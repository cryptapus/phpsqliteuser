<?php
/*

This file is part of "phpsqliteuser".

"phpsqliteuser" is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

"phpsqliteuser" is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with "phpsqliteuser".  If not, see <http://www.gnu.org/licenses/>.

*/

/*
Settings:
*/
const DB_FILE = '/webserver/write/access/sqlite.db';
const ADMIN_1ST_PASS = 'admin';


$debug = false;

if ($debug) {
	ini_set('display_errors',1);
	error_reporting(E_ALL);
}

### Includes:
include('lib/class.db.php');
include('lib/class.user.php');
include('lib/class.option.php');

session_start();

include('lib/header.phtml');

$model='user';
$action='main';
// GET overrides POST
if (isset($_POST['model'])) $model=$_POST['model'];
if (isset($_POST['action'])) $action=$_POST['action'];
if (isset($_GET['model'])) $model=$_GET['model'];
if (isset($_GET['action'])) $action=$_GET['action'];

// Nav bars:
if (isset($_SESSION['user']) && $action != 'logout') {
	$v = new userView($_SESSION['user']);
	$v->navbar();
}

// attempt a cookie login:
if (isset($_COOKIE['username']) && !isset($_SESSION['user'])) {
	$u = new user($_COOKIE['username']);
	if ($u->login_cookie()) {
		if ($debug) {
			print('Login via cookie.');
		} else {
			header('Location: ./');
		}
	}
}

switch ($model) {

	case 'user':
		if (isset($_SESSION['user'])) {
			if ($_SESSION['user']->isloggedin()) {
				$u = $_SESSION['user'];
				$c = new userController($u);
				$c->action($action);
			}
		} elseif($action=='login') {
			$u = new user();
			$c = new userController($u);
			$c->action('login');
		} else {
			$u = new user();
			$v = new userView($u);
			$v->login();
		}
		break;
	
	case 'option':
		$u = $_SESSION['user']->username;
		$o = new option($u);
		$c = new optionController($o);
		$c->action($action);
		break;
	
	case 'none':
		break;

	default:
		print('Unknown model: '.$model.'<br/>');
		break;
}

include('lib/footer.phtml');
?>
