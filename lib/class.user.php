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

class user {

	private $dbfile = DB_FILE;
	private $db = null;

	public $id = null;
	public $username = null;
	public $groupname = null;
	public $lastlogin = null;
	public $cookietok = null;
	public $apikey = null;
	public $options = array(null);

	public function __construct($username=null,$password=null,$groupname=null) {
		// otherwise open db and proceed:
		$this->db = new db(DB_FILE);
		if (!$this->db->istable('users',$dbclose=false)) {
			$schema = array(
				array(
					'name' => 'username',
					'type' => 'TEXT'
				),
				array(
					'name' => 'password',
					'type' => 'TEXT'
				),
				array(
					'name' => 'groupname',
					'type' => 'TEXT'
				),
				array(
					'name' => 'enabled',
					'type' => 'INTEGER'
				),
				array(
					'name' => 'lastlogin',
					'type' => 'TEXT'
				),
				array(
					'name' => 'apikey',
					'type' => 'TEXT'
				)
			);
			$this->db->createtable('users',$schema);
			// create the admin user:
			$this->newuser('admin',ADMIN_1ST_PASS,'admins');
			// make cookie login table
			$schema = array(
				array(
					'name' => 'username',
					'type' => 'TEXT'
				),
				array(
					'name' => 'lastlogin',
					'type' => 'TEXT'
				),
				array(
					'name' => 'cookietok',
					'type' => 'TEXT'
				)
			);
			$this->db->createtable('cookie',$schema);
		}
		if (isset($password)) {
			if (isset($groupname)) {
				$this->newuser($username,$password,$groupname);
			} else {
				$this->newuser($username,$password);
			}
		}
		if ($username!=null) {
			$ret = $this->db->getdata('users',
				array('id','username','groupname','lastlogin','apikey'),
				array('username' => $username));
			if ($username === $ret['username']) {
				$this->id = $ret['id'];
				$this->username = $ret['username'];
				$this->groupname = $ret['groupname'];
				$this->lastlogin = $ret['lastlogin'];
				$this->apikey = $ret['apikey'];
			}
		}
	}

	public function isloggedin() {
		if (isset($_SESSION['user'])) {
			return true;
		} else {
			return false;
		}
	}
	
	public function isgroupmember($group) {
		if ($this->groupname === $group) {
			return true;
		} else {
			return false;
		}
	}

	public function login($password,$rememberme=false) {
		$ret = $this->db->getdata('users',array('password','enabled'),
			array('username' => $this->username));
		if (password_verify($password,$ret['password']) && 
		($ret['enabled'] === 1)) {
			date_default_timezone_set('UTC');
			$date = date("Y-m-d H:i:s", time());
			if ($rememberme) {
				$cookiehash = hash('sha256',rand());
				$d = array(
					'username' => $this->username,
					'lastlogin' => $date,
					'cookietok' => $cookiehash
				);
				$this->db->putdata('cookie',$d);
				$this->cookietok = $cookiehash;
				setcookie("username", $this->username, 
					time() + 60 * 60 * 24 * 30);
				setcookie("cookietok", $this->cookietok, 
					time() + 60 * 60 * 24 * 30);
			} else {
				$this->db->putdata('users',array('lastlogin' => $date),
					array('username' => $this->username));
			}
			$_SESSION['user'] = $this;
			$this->lastlogin = $date;
			$_SESSION['user']->loadoptions();
			$retval = true;
		} else {
			$retval = false;
		}
		return $retval;
	}

	public function login_cookie() {
		if (isset($_COOKIE['username']) && isset($_COOKIE['cookietok'])) {
			$retu = $this->db->getdata('users',
				array('username','enabled','lastlogin'),
				array('username' => $this->username));
			$ret = $this->db->getdata('cookie',
				array('username','lastlogin','cookietok'),
				array('username' => $this->username),$getsingle=false);
			foreach ($ret as $line) {
				if (($retu['enabled'] === 1) && 
					($line['cookietok'] === $_COOKIE['cookietok'])) {
					date_default_timezone_set('UTC');
					$date = date("Y-m-d H:i:s", time());
					$cookiehash = hash('sha256',rand());
					$d = array(
						'lastlogin' => $date,
						'cookietok' => $cookiehash
					);
					$this->db->putdata('cookie',$d,
						array('cookietok' => $line['cookietok']));
					$this->db->putdata('users',array('lastlogin' => $date),
						array('username' => $this->username));
					$_SESSION['user'] = $this;
					$this->lastlogin = $date;
					$this->cookietok = $cookiehash;
					setcookie("username", $this->username, 
						time() + 60 * 60 * 24 * 30);
					setcookie("cookietok", $this->cookietok, 
						time() + 60 * 60 * 24 * 30);
					$_SESSION['user']->loadoptions();
					return true;
				}
			}
		}
		return false;
	}

	public function logout() {
		session_unset();
		$this->db->deletedata('cookie',array('username' => $this->username));
		setcookie("username", "", time() - 60 * 60 * 24 * 30);
		setcookie("cookietok", "", time() - 60 * 60 * 24 * 30);
	}

	public function loadoptions() {
		// get user options:
		$this->options = array(
			'someoption' => new option()
		);
	}

	public function updatepassword($currentpass, $newpass) {
		$ret = $this->db->getdata('users',array('password'),
			array('username' => $this->username));
		if (password_verify($currentpass,$ret['password'])) {
			$newpass = password_hash($newpass,PASSWORD_DEFAULT);
			$this->db->putdata('users',array('password' => $newpass),
				array('username' => $this->username));
			$retval = true;
		} else {
			$retval = false;
		}
		return $retval;
	}

	public function updategroup($groupname) {
		$this->db->putdata('users',array('groupname' => $groupname),
			array('username' => $this->username));
		$retval = true;
		return $retval;
	}
	
	public function enable($val) {
		$this->db->putdata('users',array('enabled' => $val),
			array('username' => $this->username));
		$retval = true;
		return $retval;
	}

	public function resetuserpassword($username, $password) {
		$password = password_hash($password,PASSWORD_DEFAULT);
		$ret = $this->db->putdata('users',array('password' => $password),
			array('username' => $username));
		$retval = true;
		return $retval;
	}
	
	public function deleteuser($username) {
		$ret = $this->db->deletedata('users',array('username' => $username));
		$retval = true;
		return $retval;
	}

	private function newuser($username, $password, $groupname='users') {
		$password = password_hash($password,PASSWORD_DEFAULT);
		date_default_timezone_set('UTC');
		$date = date("Y-m-d H:i:s", time());
		$arg = array(
			'username' => $username,
			'password' => $password,
			'groupname' => $groupname,
			'enabled' => true,
			'lastlogin' => 'none'
		);
		$this->db->putdata('users',$arg);
	}

	public function allusers() {
		/*
		returns an array of:
			array(
				array(
					username => data
					groupname => data
					enabled => data
					lastlogin => data
				)
			);
		*/
		$arg = array('id','username','groupname','enabled','lastlogin');
		$ret = $this->db->getdata('users',$arg,$where=[],$getsingle=false);
		return $ret;
	}

}

class userController {

	private $user;

	public function __construct(user $user) {
		$this->user = $user;
	}

	public function action($action='main') {
		switch ($action) {

			case 'login':
				$rememberme = false;
				if (isset($_POST['remember_me'])) $rememberme = true;
				$user = new user($_POST['username']);
				$user->login($_POST['password'],$rememberme);
				if (isset($_SESSION['user'])) {
					header('Location: ./');
				} else {
					$user = new user();
					$v = new userView($user);
					$v->login(true);
				}
				break;
			
			case 'logout':
				$this->user->logout();
				$this->user = new user();
				$v = new userView($this->user);
				$v->login();
				break;

			case 'main':
				$v = new userView($this->user);
				$v->main();
				break;

			case 'updatepassword':
				if ($_POST['password1'] === $_POST['password2']) {
					$ret = $this->user->updatepassword(
						$_POST['currentpassword'],$_POST['password1']);
				} else {
					$ret = false;
				}
				$v = new userView($this->user);
				if ($ret) {
					$v->main();
				} else {
					$v->updatepassword(true);
				}
				break;
			
			case 'updatepassword_view':
				$v = new userView($this->user);
				$v->updatepassword();
				break;
			
			case 'changeuser':
				if ($this->user->isgroupmember('admins')) {
					$v = new userView($this->user);
					$v->changeuser();
				}
				break;

			case 'adduser':
				if ($this->user->isgroupmember('admins')) {
					$ret =  new user($_POST['username'],$_POST['password'],
						$_POST['groupname']);
				}
				$v = new userView($this->user);
				$v->changeuser();
				break;
			
			case 'adduser_view':
				if ($this->user->isgroupmember('admins')) {
					$v = new userView($this->user);
					$v->adduser();
				}
				break;
			
			case 'deleteuser':
				if ($this->user->isgroupmember('admins')) {
					$ret = $this->user->deleteuser($_POST['username']);
				}
				$v = new userView($this->user);
				$v->changeuser();
				break;
			
			case 'enableuser':
				if ($this->user->isgroupmember('admins')) {
					$user =  new User($_POST['username']);
					if ($_POST['enabled'] === 'enabled') $user->enable(true);
					if ($_POST['enabled'] === 'disabled') $user->enable(false);
				}
				$v = new userView($this->user);
				$v->changeuser();
				break;
			
			case 'changeusergroup':
				if ($this->user->isgroupmember('admins')) {
					$user =  new User($_POST['username']);
					$user->updategroup($_POST['groupname']);
				}
				$v = new userView($this->user);
				$v->changeuser();
				break;
			
			case 'changeuserpassword':
				if ($this->user->isgroupmember('admins')) {
					$this->user->resetuserpassword($_POST['username'],
						$_POST['password']);
				}
				$v = new userView($this->user);
				$v->changeuser();
				break;

			case 'changeuserpassword_view':
				if ($this->user->isgroupmember('admins')) {
					$v = new userView($this->user);
					$v->changeuserpassword($_POST['username']);
				}
				break;

			default:
				die('Unkown action: '.$action);
				break;
		}
	}

}

class userView {

	private $user;

	public function __construct(user $user) {
		$this->user = $user;
	}

	public function login($fail=false) {
		if ($fail) print("Sorry, wrong username or password.<br/>\n");
		?>

		<form id="login" action="./" method="post">
		<h2>Welcome</h2>
		<label for="username">Username</label>
		<input type="text" name="username" id="username" /><br/>
		<label for="password">Password</label>
		<input type="password" name="password" id="password" /><br/>
		<input type="checkbox" name="remember_me"/><span>Remember Me</span>
		<div id="formbutton">
		<input type="hidden" name="model" value="user">
		<input type="hidden" name="action" value="login">
		<input type="submit" value="Login" />
		</div>
		</form>

		<?php
	}

	public function main() {
		?>

		<div id="main">
		<?php
		if (isset($_SESSION['user'])) {
			print('<h2>Content goes here.</h2>');
		}
		?>
		</div>

		<?php
	}

	public function navbar() {
		?>
		<div id="slideout">
		<nav>
		<span>Menu:</span>
		<ul class="nav">
		<li><a href="./">Main</a></li>
		<li><span>Settings</span>
			<ul>
			<li><a href="./?model=option&action=displayall">Options</a>
			<li><a href="./?model=user&action=updatepassword_view"
				>Update Password</a></li>
			<?php
			if ($this->user->isgroupmember('admins')) {
			?>
			<li><span>Admin Settings</span>
			<ul>
			<li><a href="./?model=user&action=changeuser"
				>Users</a></li>
			<li><a href="./?model=user&action=adduser_view"
				>Add User</a></li>
			</ul></li>
			<?php
			}
			?>
			</ul></li>
		<li><a href="./?model=user&action=logout">Logout</a></li>
		</ul>
		</nav>
		</div>

		<?php
	}

	public function changeuser() {
		?>

		<div id="form">
		<span>Change a user's settings:</span><br/>
		<table>
		<tr>
		<td>ID</td><td>Username</td><td>Group</td><td>Enabled</td>
		<td>Last Login</td><td>Enable/Disable</td><td>Change Group</td>
		<td>Change Password</td><td>Delete</td>
		</tr>
		<?php
		foreach ($this->user->allusers() as $user) {
			?>
			<tr>
			<td><?php echo($user['id']) ?></td>
			<td><?php echo($user['username']) ?></td>
			<td><?php echo($user['groupname']) ?></td>
			<td><?php echo($user['enabled']) ?></td>
			<td><?php echo($user['lastlogin']) ?></td>
			<td>
			<form action="./" method="post">
			<input type="hidden" name="model" value="user">
			<input type="hidden" name="action" value="enableuser">
			<input type="hidden" name="username" 
				value="<?php echo($user['username']) ?>">
			<select name="enabled">
				<?php if ($user['enabled'] == true) { ?>
					<option selected="selected" value="enabled">enabled</option>
					<option value="disabled">disabled</option>
				<?php } else { ?>
					<option value="enabled">enabled</option>
					<option selected="selected" 
						value="disabled">disabled</option>
				<?php } ?>
				</select>
			<input type="submit" value="Change"/>
			</form>
			</td>
			<td>
			<form action="./" method="post">
			<input type="hidden" name="model" value="user">
			<input type="hidden" name="action" value="changeusergroup">
			<input type="hidden" name="username" 
				value="<?php echo($user['username']) ?>">
			<select name="groupname">
				<?php if ($user['groupname'] === 'users') { ?>
					<option selected="selected" value="users">users</option>
					<option value="admins">admins</option>
				<?php } else { ?>
					<option value="users">users</option>
					<option selected="selected" value="admins">admins</option>
				<?php } ?>
			</select>
			<input type="submit" value="Change"/>
			</form>
			</td>
			<td>
			<form action="./" method="post">
			<input type="hidden" name="model" value="user">
			<input type="hidden" name="action" value="changeuserpassword_view">
			<input type="hidden" name="username" 
				value="<?php echo($user['username']) ?>">
			<input type="submit" value="Change"/>
			</form>
			</td>
			<td>
			<form action="./" method="post">
			<input type="hidden" name="model" value="user">
			<input type="hidden" name="action" value="deleteuser">
			<input type="hidden" name="username" 
				value="<?php echo($user['username']) ?>">
			<input type="submit" value="Delete"/>
			</form>
			</td>
			</tr>
			<?php
		}
		?>
		</table>
		</div>
		<?php
	}

	public function adduser() {
		?>

		<div id="form">
		<span>Add a user:</span><br/>
		<form action="./" method="post">
		<input type="hidden" name="model" value="user">
		<input type="hidden" name="action" value="adduser">
		<span>Username: </span><input type="text" name="username"><br/>
		<span>Password: </span><input type="password" name="password"><br/>
		<select name="groupname">
		<option selected="selected" value="users">users</option>
		<option value="admins">admins</option>
		</select>
		<input type="submit" value="Add"/>
		</form>
		</div>

		<?php
	}
	public function changeuserpassword($username) {
		?>

		<div id="form">
		<span>Change password for user "<?php echo($username) ?>":</span><br/>
		<form action="./" method="post">
		<input type="hidden" name="model" value="user">
		<input type="hidden" name="action" value="changeuserpassword">
		<input type="hidden" name="username" value="<?php echo($username) ?>">
		<span>Password: </span><input type="password" name="password"><br/>
		<input type="submit" value="Change"/>
		</form>
		</div>

		<?php
	}


	public function updatepassword($fail=false) {
		if ($fail) print("Sorry, wrong current password or new passwords do ".
			"not match.<br/>\n");
		?>

		<div id="form">
		<form action="./" method="post">
		<input type="hidden" name="model" value="user">
		<input type="hidden" name="action" value="updatepassword">
		<span>Current Password: </span><input type="password" 
			name="currentpassword"><br/>
		<span>New Password: </span><input type="password" name="password1"><br/>
		<span>New Password (repeat): </span><input type="password" 
			name="password2"><br/>
		<input type="submit" value="Change"/>
		</form>
		</div>

		<?php
	}

}

?>
