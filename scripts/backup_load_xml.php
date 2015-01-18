<?php

define('ARCHIVE_LABEL',			'archive_label');
define('LOCAL_FOLDER', 			'local_folder');
define('TAR_EXE',                       'tar_exe');

define('EMAIL_SERVER', 			'email_server');
define('EMAIL_TO', 			'email_to');
define('EMAIL_FROM', 			'email_from');
define('EMAIL_FROM_NAME',               'email_from_name');

define('EMAIL_AUTH', 			'email_auth');
define('EMAIL_SECURE',                  'email_secure');
define('EMAIL_PORT',                    'email_port');
define('EMAIL_USERNAME', 		'email_username');
define('EMAIL_PASSWORD', 		'email_password');

define('MYSQL',				'mysql');
define('MYSQL_HOST',			'mysql_host');
define('MYSQL_EXE',          		'mysql_exe');
define('MYSQL_USERNAME',     		'mysql_username');
define('MYSQL_PASSWORD',     		'mysql_password');
define('MYSQL_QUERY', 			'mysql_query');

define('NOW', 				'now');
define('COPY_DATE', 			'copy_date');
define('N',				'n');
define('Y',				'y');
define('DATE_FORMAT',			'dMy-Hi');
define('DELETE_DATE_FORMAT',		'dMy');
define('COPY_DATE_FORMAT',		'dMy');

define('PATH',				'path');
define('FILENAME',			'filename');
define('SIZE',				'size');
define('SUCCESS_YN',			'success_yn');
define('DBNAME',			'dbname');
define('FOLDER_SPLIT_YN',		'folder_split');
define('ZIPNAME',			'zipname');
define('FOLDER_EXCLUDE',                'folder_exclude');

define('FOLDER_SPLIT_EXCLUDE',		'folder_split_exclude');
define('EXCLUDE',			'exclude');


$defaut_folder_array 	= array(SIZE=>0, SUCCESS_YN=>null, PATH=>null, ZIPNAME=>null, FOLDER_EXCLUDE=>null);
$defaut_mysql_array  	= array(FILENAME=>null, SIZE=>0, SUCCESS_YN=>null, DBNAME=>null, ZIPNAME=>null);

function build_backup_session($options) {

	global $defaut_folder_array, $defaut_mysql_array;

	$xml_file = $options[1];

	//get xml configuration file
	$xmlOBJ = simplexml_load_file($xml_file);
		
	//init model
	$backupSessionObj = new BackupSession();
	$backupSessionObj->options = $options;
		
	//populate config
	$backupSessionObj->config 			= _populate_config_array($xmlOBJ);
	$backupSessionObj->config[NOW]			= date(DATE_FORMAT);
	$backupSessionObj->config[COPY_DATE]		= date(COPY_DATE_FORMAT);
	
	//populate folders
	$backupSessionObj->folder_list 			= _populate_folder_list_array($xmlOBJ, $defaut_folder_array);

	return $backupSessionObj;
}

function _populate_config_array($xmlOBJ) {
	
	//init result
	$result = array();
	
	$result[ARCHIVE_LABEL] 		= (string)$xmlOBJ->config->archive_label;

	$result[LOCAL_FOLDER] 		= (string)$xmlOBJ->config->local_folder;
	$result[TAR_EXE]                = (string)$xmlOBJ->config->tar_exe;

	$result[EMAIL_SERVER] 		= (string)$xmlOBJ->config->email_server;
	$result[EMAIL_TO] 		= (string)$xmlOBJ->config->email_to;
	$result[EMAIL_FROM] 		= (string)$xmlOBJ->config->email_from;
	$result[EMAIL_FROM_NAME]        = (string)$xmlOBJ->config->email_from_name;
	$result[EMAIL_AUTH] 		= (string)$xmlOBJ->config->email_auth;
	$result[EMAIL_SECURE]           = (string)$xmlOBJ->config->email_secure;
	$result[EMAIL_PORT]             = (string)$xmlOBJ->config->email_port;
	$result[EMAIL_USERNAME] 	= (string)$xmlOBJ->config->email_username;
	$result[EMAIL_PASSWORD] 	= (string)$xmlOBJ->config->email_password;

	$result[MYSQL] 			= (string)$xmlOBJ->config->mysql;
	$result[MYSQL_HOST]             = (string)$xmlOBJ->config->mysql_host;
	$result[MYSQL_EXE] 		= (string)$xmlOBJ->config->mysql_exe;
	$result[MYSQL_USERNAME] 	= (string)$xmlOBJ->config->mysql_username;
	$result[MYSQL_PASSWORD] 	= (string)$xmlOBJ->config->mysql_password;
	$result[MYSQL_QUERY]            = (string)$xmlOBJ->config->mysql_query;

	//return
	return $result;
}

function _populate_folder_list_array($xmlOBJ, $defaut_folder_array) {
	
	//init result
	$result = array();

	//get folders_list
	$folder_list = $xmlOBJ->folder_list->folder;

	foreach($folder_list as $folder) {

		if ( (string)$folder->folder_split == Y ) {

			$exclude_list = (array)$folder->folder_split_exclude;
			$excludeAry   = (array)$exclude_list['exclude'];

			if ( !is_array($excludeAry)) {
				$excludeAry = array();
			}

			$subfolders = _get_split_folders((string)$folder->folder_path);
	
			for ($x=0; $x < count($subfolders); $x++) {

				if ( !in_array($subfolders[$x][0], $excludeAry) ) {
					$defaut_folder_array[PATH] = $subfolders[$x][1];
					$defaut_folder_array[FOLDER_EXCLUDE] = (string)$folder->folder_exclude;
					$key                       = (string)$folder->folder_label;
					$key			  .= "-".$subfolders[$x][0];
					$result[$key]              = $defaut_folder_array; 
				}
			}

		} else {
			$defaut_folder_array[PATH] 		= (string)$folder->folder_path;
			$defaut_folder_array[FOLDER_EXCLUDE]    = (string)$folder->folder_exclude;
			$key                                    = (string)$folder->folder_label;
	                $result[$key]                           = $defaut_folder_array;
		}	
	}

	//return
	return $result;

}	

function _get_split_folders($path) {

	$result = array();

	if ( is_dir($path) ) {
	        if ( $handle = opendir($path) ) {
        	        while ( false !== ( $entry = readdir($handle) ) ) {
                	        if ($entry != "." && $entry != "..") {
                        	        $item = $path.$entry."/";
                                	if ( is_dir($item) ) {
						$sugar = array($entry, $item);
						array_push($result, $sugar);
                                	}
                        	}
                	}
                	closedir($handle);
        	} else {
			logger_backup("Failed to opendir : $path");
		}	

	} else {
		logger_backup("Path supplied in xml not a folder : $path");
	}
	return $result;
}

?>
