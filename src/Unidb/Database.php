<?php

namespace UniDB;

#
# Usage example 1: no data returned
#
# if($this->db->query("INSERT INTO table VALUES (?,?,?)", ['iss', $number, $string, $float]) === false) {
#   echo "{$this->db->error}\n";
# }
# where 'isd' = types of values in (?,?,?), variables passed are $number, $string, $float
#

#
# Usage example 2: fetched data is expected in return
#
# if(($data = $this->db->query("SELECT * FROM table WHERE id=?", ['i', $my_id], true)) === false) { echo "{$this->db->error}\n"; }
# var_dump($data)
#

class Database
{
	public $error, $db_link, $status = 0; // 0 = not connected, 1 = connected

	public function __construct($config)
	{
		if(	empty($config['host']) ||
			empty($config['port']) ||
			empty($config['name']) ||
			empty($config['user'])
		) {
			$this->error = "No or bad config";
			return false;
		}

		$this->driver	= $config['driver'] ?? "pgsql";
		$this->host     = $config['host'];
		$this->port     = $config['port'];
		$this->database = $config['name'];
		$this->username = $config['user'];
		$this->password = $config['password'] ?? "";
	}

	public function connect()
	{
		$this->error = null;

		try {
			$this->db_link = new \PDO("{$this->driver}:host={$this->host};
				port={$this->port};
				dbname={$this->database};
				user={$this->username};
				password={$this->password}
			");
			$this->db_link->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
			$this->status = 1;
			return true;
		}
		catch(\PDOException $e) {
			$this->error = "Connection failed: ".$e->getMessage();
			return false;
		}
	}

	public function disconnect()
	{
		$this->db_link = null;
		$this->status = 0;
		return true;
	}

	public function check()
	{
		$this->error = null;

		if(empty($this->db_link)) {
			return false;
		}

		try {
			$this->db_link->query("SELECT 1");
			return true;
		}
		catch(\PDOException $e) {
			// $stmt->errorCode() may have details also
			$this->error = "Request to DB failed on `execute`, disconnected: ".$e->getMessage();
			$this->disconnect();
			return false;
		}
	}

	public function query($query, $paramsValues, $return_data = false)
	{
		$this->error = null;
		$prefix = __FUNCTION__.": $query, ";

		if(empty($this->db_link)) {
			$this->error = "$prefix Bad or no PDO object given";
			return false;
		}

		if(empty($this->status) && $this->connect() == false) {
			return false;
		}

		try {
			$stmt = $this->db_link->prepare($query);
		}
		catch(\PDOException $e) {
			$this->error = "$prefix DB request failed on `prepare`: ".$e->getMessage();
			return false;
		}

		$params_number = 0;

		// Prepare array with params for DB request
		if(!empty($paramsValues[0])) {
			foreach($paramsValues as $key => $value) {
				if($params_number == 0) {
					$types = str_split($paramsValues[$key]);
					$params_number++;
					continue;
				}

				if(empty($types)) {
					$this->error = "$prefix No data types in request";
					return false;
				}

				$prefix.= " $key=>{$value} ";

				if(empty($types[$params_number - 1])) {
					$this->error = "$prefix Not enough data types for values";
					return false;
				}

				switch($types[$params_number - 1]) {
					case 'i': $type = \PDO::PARAM_INT; break;
					case 's': $type = \PDO::PARAM_STR; break;
					default : $type = \PDO::PARAM_STR;
				}

				try {
					$stmt->bindValue($params_number, $value, $type);
				}
				catch(\PDOException $e) {
					$this->error = "$prefix Request to DB failed on `bindValue`: ".$e->getMessage();
					return false;
				}

				$params_number++;
			}
		}

		try {
			$stmt->execute();
		}
		catch(\PDOException $e) {
			$this->error = "$prefix Request to DB failed on `execute`: ".$e->getMessage();
			return false;
		}

		if($return_data) {
			$dataArray = $stmt->fetchAll(\PDO::FETCH_ASSOC);
		}

		return (empty($dataArray) ? true : $dataArray);
	}
}


