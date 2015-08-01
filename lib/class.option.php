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

class option {

	private $dbfile = DB_FILE;
	private $db = null;

	public $username = null;
	public $section = null;
	public $option = null;
	public $value = null;
	public $choices = array(null);	// first choice will be default
	public $postevalstr = null;

	public function __construct($username=null,$section=null,
		$option=null,$choices=array(null),$postevalstr=null) {
		$this->db = new db(DB_FILE);
		if (!$this->db->istable('option')) {
			$schema = array(
				array(
					'name' => 'username',
					'type' => 'TEXT'
				),
				array(
					'name' => 'section',
					'type' => 'TEXT'
				),
				array(
					'name' => 'option',
					'type' => 'TEXT'
				),
				array(
					'name' => 'value',
					'type' => 'TEXT'
				)
			);
			$this->db->createtable('option',$schema);
		}
		$this->username = $username;
		$this->section = $section;
		$this->option = $option;
		$this->choices = $choices;
		$this->postevalstr = $postevalstr;
		// "get" to fill the option if given:
		if ($option!=null) $this->getvalue($choices);
	}

	public function getvalue() {
		$where = array(
			'username' => $this->username,
			'section' => $this->section,
			'option' => $this->option
		);
		$ret = $this->db->getdata('option',array('value'),$where);
		if ($ret['value']=='') {
			$this->db->putdata('option',array(
					'username' => $this->username,
					'section' => $this->section,
					'option' => $this->option,
					'value' => $this->choices[0]
				));
			$ret = array('value' => $this->choices[0]);
		}
		$this->value = $ret['value'];
		return $ret['value'];
	}
	
	public function setvalue($value) {
		$where = array(
			'username' => $this->username,
			'section' => $this->section,
			'option' => $this->option
		);
		$ret = $this->db->getdata('option',array('value'),$where,
			$getsingle=true,$dbclose=false);
		if ($ret['value']=='') {
			$this->db->putdata('option',array(
					'username' => $this->username,
					'section' => $this->section,
					'option' => $this->option,
					'value' => $value
				));
		} else {
			$where = array(
				'username' => $this->username,
				'section' => $this->section,
				'option' => $this->option
			);
			$this->db->putdata('option',array('value' => $value),$where);
		}
		$this->value = $value;
		return;
	}

	public function posteval() {
		if ($this->postevalstr!=null) eval($this->postevalstr);
	}

	public function getuserlist() {
		$where = array(
			'username' => $this->username,
			'section' => 'user'
		);
		$ret = $this->db->getdata('option',array('option','value'),
			$where,$getsingle=false);
		return $ret;
	}
	
	public function getsectionlist() {
		$where = array(
			'username' => $this->username,
			'section' => $this->section
		);
		$ret = $this->db->getdata('option',array('option','value'),$where,
			$getsingle=false);
		return $ret;
	}

}

class optionController {
	
	private $option;

	public function __construct(option $option) {
		$this->option = $option;
	}

	public function action($action) {
		switch ($action) {

		case 'displayall':
			$v = new optionView($this->option);
			$v->displayuseroptions();
			break;

		case 'changeuseroption':
			$this->option = $_SESSION['user']->options[$_POST['option']];
			$this->option->setvalue($_POST['value']);
			$this->option->posteval();
			$_SESSION['user']->loadoptions();
			$v = new optionView($this->option);
			$v->displayuseroptions();
			break;

		default:
			die('Unknown action: '.$action);
			break;

		}
	}

}

class optionView {

	private $option;

	public function __construct(option $option) {
		$this->option = $option;
	}

	public function displayuseroptions() {
		?>
	
		<div id="option">
		<span>User Options:</span><br/>
		<table>
		<tr>
		<td>Section</td><td>Name</td><td>Current Value</td><td>Choices</td>
		</tr>
		<?php
		foreach ($_SESSION['user']->options as $o) {
			?>
			<tr>
			<td><?php echo($o->section) ?></td>
			<td><?php echo($o->option) ?></td>
			<td><?php echo($o->value) ?></td>
			<td>
			<form action="./" method="post">
			<input type="hidden" name="model" value="option">
			<input type="hidden" name="action" value="changeuseroption">
			<input type="hidden" name="option" value="<?php echo($o->option) 
				?>">
			<select name="value">
			<?php
			foreach ($o->choices as $choice) {
				?>
				<option value="<?php echo($choice) ?>"><?php 
					echo($choice) ?></option>
				<?php
			}
			?>
			</select>
			<input type="submit" value="Change"/>
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

}

?>
