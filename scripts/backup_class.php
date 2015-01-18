<?php

class BackupSession {
	
	public $errors;
	public $error_messages;
	public $total_size;
	public $folder_list;
	public $mysql_list;
	public $config;
	public $messages;
	public $options;

	public $sftp_list;
	public $sftp_zip_list;

	public $dropbox_list;

	public function __construct() {
		$this->total_size = 0;
		$this->errors = 0;
		$this->error_messages = array();
		$this->folder_list = array();
		$this->mysql_list = array();
		$this->config = array();
		$this->sftp_zip_list = array();
		$this->messages = "";
		$this->options = array();
		$this->dropbox_list = array();
	}

	public function getOptions() {
		$result = "";
		for ($i=0; $i < count($this->options); $i++) {
			$result .= 	$this->options[$i]." ";
		}		
		return $result;
	}

	public function appendRemoteList($name) {
		$this->remote_list .= $name."\n";
	}

	public function appendSFTPList($name) {
		$this->sftp_list .= $name."\n";
		array_push($this->dropbox_list, $name);
	}

	public function appendMessages($message) {
		$this->messages .= $message."\n";
	}

	public function appendErrors($message) {
		array_push($this->error_messages, $message);
		$this->errors = $this->errors + 1;	
	}	

	public function appendSize($size) {
		$this->total_size = bcadd($this->total_size,$size);
	}

	public function displayConfig() {
		$this->displayFolderSummary();
		$db = $this->displayDatabases();
		$dbs = $db[2];
		sort($dbs, SORT_STRING);
		for ($x=0; $x<count($dbs); $x++) {
			echo $dbs[$x]."\n";;		
		}
	}

	public function displayDatabases() {

		$config = $this->config;
		$result = array();
		$dblist = array();

	        if ($config[MYSQL] == "y") {

        		$mysql_connection = mysql_connect($config[MYSQL_HOST], $config[MYSQL_USERNAME], stripslashes($config[MYSQL_PASSWORD]));

	        	if (!$mysql_connection) {
				array_push($result,"MySQL-Fail"); 	
        		} else {
				array_push($result,"MySQL-Success");
                		mysql_select_db("information_schema", $mysql_connection);
	        	}

        		$sql = $config[MYSQL_QUERY];
			array_push($result, $sql);

	        	$dbname_res = mysql_query($sql);
			while ($obj = mysql_fetch_object($dbname_res) ) {
				if ( $obj->Dname != "information_schema" ) { 
					array_push($dblist, $obj->Dname);
				}	
			}

		}
		array_push($result, $dblist);

		return $result;
	}
	
	public function displayFolderSummary() {

		//loop thru folders
        	foreach ($this->folder_list as $itemName => $value) {
			echo $value[PATH]."\n";				
        	}
        	//end foreach
	}
}

?>
