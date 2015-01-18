<?php
	$tar_args  = " czf ";
	$tar_ext   = ".tgz";

	set_time_limit(0);
	ignore_user_abort(true);
	set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__));

	//Backup Code
	require_once("backup_load_xml.php");
	require_once("backup_functions.php");
	require_once("backup_class.php");
	require_once("backup_email.php");
	logger_backup("Backup Started");

	define('DISPLAYCONFIG',		'DISPLAYCONFIG');	
	define('NOFOLDERS', 		'NOFOLDERS');
	define('NOMYSQL',		'NOMYSQL');
	define('NOEMAIL', 		'NOEMAIL');
	define('NODROPBOX', 		'NODROPBOX');

	//construct model
	$backupSessionObj = build_backup_session($argv);

	//exec DISPLAYCONFIG
	if (in_array(DISPLAYCONFIG, $argv)) {
		$backupSessionObj->displayConfig();
		die();	 
	}

	// folders_backup
	if (in_array(NOFOLDERS, $argv)) {
		logger_backup("no folders backups"); 
	}else {
		folders_backup($backupSessionObj);
	}	

	// mysql_backup
    	if (in_array(NOMYSQL, $argv)) {
		logger_backup("no mysql backups");
    	} else {
        	mysql_backup($backupSessionObj);
    	}

	// dropbox_backup
    	if (in_array(NODROPBOX, $argv)) {
		logger_backup("no DROPBOX copy");
    	} else {
        	dropbox_backup($backupSessionObj);
    	}

	// email_results
	if (in_array(NOEMAIL, $argv)) {
		logger_backup("no reporting required");
	} else {	
		email_results($backupSessionObj);
	}	

	logger_backup("Backup Ended");
?>
