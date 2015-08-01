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

class db {

	private $filename = 0;
	private $db = 0;
	private $isopen = false;

	function __construct($filename) {
		$this->filename = $filename;
		if (!file_exists($filename)) die('Unable to find database file!');
	}

	public function isempty() {
		if (filesize($this->filename) == 0) {
			return true;
		} else {
			return false;
		}
	}

	public function istable($table,$dbclose=true) {
		/*
			returns true if table exists
		*/
		//$query = $this->db->query('SELECT 1 FROM userss LIMIT 1');
		$this->open();
		$query = $this->db->query('SELECT name FROM sqlite_master WHERE '.
			'type="table" AND name="'.$table.'"');
		$r = $query->fetchArray(SQLITE3_ASSOC);
		if ($dbclose) $this->close();
		if (!$r) {
			return false;
		} else {
			return true;
		}
	}

	public function createtable($tname,$tarray,$dbclose=true) {
		/*
		$tarray should look like:
			$tarray = array(
				array(
					"name" => "column1_name",
					"type" => "column1_type"
				),
				array(
					"name" => "column2_name",
					"type" => "column2_type"
				)
			);
		where column_type can be either:
			INTEGER  - integer value
			REAL     - floating point
			TEXT     - text string
		*/
		$this->open();
		$query = ('CREATE TABLE IF NOT EXISTS '.$tname.
			' (id INTEGER PRIMARY KEY AUTOINCREMENT, ');
		foreach ($tarray as $col) {
			$query = $query.$col['name'].' '.$col['type'].', ';
		}
		$query = rtrim(chop($query),',').');';
		$this->db->exec($query) or die('Unable to create database.');
		if ($dbclose) $this->close();
		return;
	}

	public function deletedata($tname,$where=[],$dbclose=true) {
		/*
		Deletes all rows where column that match $where:
		$where = array(
			'field1' => 'value1',
			'field2' => 'value2'
			);
		If $where is not supplied, all data in the table will be deleted.
		*/
		$this->open();
		$keys = array_keys($where);
		$query = 'DELETE FROM '.$tname.' ';
		for ($i=0; $i<sizeof($where); $i++) {
			if ($i==0) $query=$query.'WHERE ';
			$v = SQLite3::escapeString($where[$keys[$i]]);
			$query = $query.$keys[$i].'="'.$v.'" AND ';
		}
		$query = rtrim(chop($query),'AND').';';
		$this->db->exec($query);
		if ($dbclose) $this->close();
		return;
	}

	public function getdata($tname,$getval,$where=[],$getsingle=true,
		$dbclose=true) {
		/*
		Retrieves an array of values. $getval is a list of 
		returned values.
		example:
			$getval = array('groupname','password');
			$where = array(
				'id' => 'an ID',
				'username' => 'somename'
			);
			getdata('users',$where,$getval);
		will return an array:
			array(
				'groupname' => 'somegroupname',
				'password' => 'somepassword'
			);
		If $where is not supplied, all data in the table will be returned.
		If $getsingle is false, an array of arrays are returned
		*/
		$this->open();
		$k_where = array_keys($where);
		$query = 'SELECT ';
		foreach ($getval as $a) {
			$query = $query.$a.', ';
		}
		$query = rtrim(chop($query),',').' FROM '.$tname.' ';
		for ($i=0; $i<sizeof($where); $i++) {
			if ($i==0) $query=$query.'WHERE ';
			$v = SQLite3::escapeString($where[$k_where[$i]]);
			$query = $query.$k_where[$i].'="'.$v.'" AND ';
		}
		$query = rtrim(chop($query),'AND');
		$query = $query.';';
		$query = $this->db->query($query);
		if ($getsingle) {
			$ret = $query->fetchArray(SQLITE3_ASSOC);
		} else {
			$i=0;
			$ret = array();
			while ($res = $query->fetchArray(SQLITE3_ASSOC)) {
				foreach ($getval as $a) {
					$ret[$i][$a] = $res[$a];
				}
				$i++;
			}
		}
		if ($dbclose) $this->close();
		return $ret;
	}

	public function putdata($tname,$putval,$where=[],$dbclose=true) {
		/*
		Updates a single row where $cname=$val. $valarray is a list of 
		returned values. Will return an array of values.
		example:
			$putval = array(
					'groupname' => 'group2',
					'password' => 'somepass'
				);
			$where = array(
				'id' => 'an ID',
				'username' => 'somename'
			);
			updatedata('users', $valarray, 'username', 'admin');
		Note that $where is optional. If not supplied, a new row is inserted.
		*/
		if (sizeof($where)>0) {
			$ret = $this->updatedata($tname,$putval,$where,$dbclose);
		} else {
			$ret = $this->insertrow($tname,$putval,$dbclose);
		}
		return $ret;
	}

	/*** Private Functions ***/

	private function open() {
		if (!$this->isopen) {
			$this->db = new SQLite3($this->filename);
			$this->isopen = true;
			return true;
		}
		return false;
	}

	private function close() {
		if ($this->isopen) {
			$this->db->close();
			$this->isopen = false;
			return true;
		}
		return false;
	}

	private function insertrow($tname,$putval,$dbclose=true) {
		/*
		$rarray is an array of the form:
			$putval = array(
				"column1_name" => "value1",
				"column2_name" => "value2"
			)
		*/
		$this->open();
		$k_putval = array_keys($putval);
		$query = 'INSERT INTO '.$tname.' (';
		foreach ($k_putval as $key) {
			$query = $query.$key.", ";
		}
		$query = rtrim(chop($query),',').') VALUES (';
		for ($i=0; $i<sizeof($k_putval); $i++) {
			$query = $query."?, ";
		}
		$query = rtrim(chop($query),',').');';
		$query = $this->db->prepare($query);
		for ($i=0; $i<sizeof($k_putval); $i++) {
			$val = $putval[$k_putval[$i]];
			$query->bindValue($i+1,$val,$this->getdatatype($val));
		}
		$ret = $query->execute() or die('Could not insert row into database.');
		if ($dbclose) $this->close();
		return $ret;
	}
	
	private function updatedata($tname,$putval,$where,$dbclose=true) {
		/*
		Updates a single row where $cname=$val. $valarray is a list of 
		returned values. Will return an array of values.
		example:
			$putval = array(
					'groupname' => 'group2',
					'password' => 'somepass'
				);
			$where = array(
				'id' => 'an ID',
				'username' => 'somename'
			);
			updatedata('users', $valarray, 'username', 'admin');
		*/
		$this->open();
		$k_putval = array_keys($putval);
		$k_where = array_keys($where);
		$query = 'UPDATE '.$tname.' SET ';
		foreach ($k_putval as $key) {
			$query = $query.'"'.$key.'"=?, ';
		}
		$query = rtrim(chop($query),',').' WHERE ';
		foreach ($k_where as $key) {
			$v = SQLite3::escapeString($where[$key]);
			$query = $query.$key.'="'.$v.'" AND ';
		}
		$query = rtrim(chop($query),'AND');
		$query = $this->db->prepare($query);
		for ($i=0; $i<sizeof($k_putval); $i++) {
			$val = $putval[$k_putval[$i]];
			$query->bindValue($i+1,$val,$this->getdatatype($val));
		}
		$ret = $query->execute() or die('Could not update row in database.');
		if ($dbclose) $this->close();
		return $ret;
	}
	
	private function getdatatype($val) {
		switch (gettype($val)) {
			case 'double':
				$type = SQLITE3_FLOAT;
				break;
			case 'integer':
				$type = SQLITE3_INTEGER;
				break;
			case 'boolean':
				$type = SQLITE3_INTEGER;
				break;
			case 'NULL':
				$type = SQLITE3_NULL;
				break;
			case 'string':
				$type = SQLITE3_TEXT;
				break;
			default: 
				die('Unknown type: '.gettype($val).' for value - '.$val);
		}
		return $type;
	}

}

?>
