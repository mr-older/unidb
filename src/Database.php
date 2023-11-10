<?php

namespace UniDB;

class Database
{
	public $error, $db_link, $status = 0; // 0 = not connected, 1 = connected

	public function __construct($config)
	{
		if( empty($config['db_host']) ||
			empty($config['db_port']) ||
			empty($config['db_name']) ||
			empty($config['db_user'])
		) {
			$this->error = 'No or bad config';
			return false;
		}

		$this->host     = $config['db_host'];
		$this->port     = $config['db_port'];
		$this->database = $config['db_name'];
		$this->username = $config['db_user'];
		$this->password = $config['db_password'] ?? "";
	}

	public function connect()
	{
		$this->error = null;

		try {
			$this->db_link = new PDO("
				pgsql:host={$this->host};
				port={$this->port};
				dbname={$this->database};
				user={$this->username};
				password={$this->password}
			");
			$this->db_link->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$this->status = 1;
			return true;
		}
		catch(PDOException $e) {
			$this->error = 'Connection failed: '.$e->getMessage();
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

		try	{
			$stmt = $this->db_link->query('SELECT 1');
			return true;
		}
		catch(PDOException $e) {
			// $stmt->errorCode() may have details also
			$this->error = 'Request to DB failed on `execute`, disconnected: '.$e->getMessage();
			$this->disconnect();
			return false;
		}
	}

	public function query($query, $paramsValues, $return_data = false)
	{
		$this->error = null;
		$prefix = __FUNCTION__.": $query, ";

		if(empty($this->db_link)) {
			$this->error = "$prefix Bad or no PDO object given.";
			return false;
		}

		try {
			$stmt = $this->db_link->prepare($query);
		}
		catch(PDOException $e) {
			$this->error = "$prefix Request to DB failed on `prepare`: ".$e->getMessage();
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
					$this->error = "$prefix Not enough data types for values.";
					return false;
				}

				switch($types[$params_number - 1]) {
					case 'i': $type = PDO::PARAM_INT; break;
					case 's': $type = PDO::PARAM_STR; break;
					default : $type = PDO::PARAM_STR;
				}

				try {
					$stmt->bindValue($params_number, $value, $type);
				}
				catch(PDOException $e) {
					$this->error = "$prefix Request to DB failed on `bindValue`: ".$e->getMessage();
					return false;
				}

				$params_number++;
			}
		}

		try {
			$stmt->execute();
		}
		catch(PDOException $e) {
			$this->error = "$prefix Request to DB failed on `execute`: ".$e->getMessage();
			return false;
		}

		if($return_data) {
			$dataArray = $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

		if(!empty($dataArray)) {
			return $dataArray;
		}

		return true;
	}
}


