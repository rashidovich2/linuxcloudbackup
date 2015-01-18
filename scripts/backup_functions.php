<?php

function logger_backup($msg) {
	global $argv;
	$logfile   = $argv[2];
	$date = date('d.M.Y H:i:s'); 
	$log = $msg." | ".$date."\n";
        error_log($log,3,$logfile);
}

function folders_backup($backupSessionObj) {

	logger_backup("Begin folders_backup");

	$config      = $backupSessionObj->config;
	$folder_list = $backupSessionObj->folder_list;
	
	//loop thru folders
	foreach ($folder_list as $itemName => $value) {

		//Check if folder exists
		if ( !is_dir($value[PATH]) ) {

			_set_error_message($backupSessionObj, $value[PATH] . " is not a folder");
			$backupSessionObj->folder_list[$itemName][SUCCESS_YN] = N;

		} else {
			folders_backup_helper($backupSessionObj, $itemName, $value);
		}
		//end is dir
	}
	//end foreach

	logger_backup("End folders_backup");
}

function folders_backup_helper($backupSessionObj, $itemName, $value) {

	global $tar_args, $tar_ext;
	
	$config = $backupSessionObj->config;

	//Construct file name
        $filename = _build_file_name($config[ARCHIVE_LABEL], $itemName . "-dir", $config[NOW], $tar_ext);
	logger_backup($filename);
	
	//add file name to model
        $backupSessionObj->folder_list[$itemName][FILENAME] = $filename;

	// Construct Command
        $tar_command = $config[TAR_EXE] . $tar_args . $config[LOCAL_FOLDER] . $filename . " " . $value[PATH];

	if ( $value[FOLDER_EXCLUDE] != null || $value[FOLDER_EXCLUDE] != "" ) {
		$tar_command .= " --exclude=".$value[FOLDER_EXCLUDE];
	}

	//Run Command
        $result = _run_command($backupSessionObj, $tar_command);

	//populate size in model
        $backupSessionObj->folder_list[$itemName][SIZE] = _file_size($backupSessionObj, $config[LOCAL_FOLDER] . $filename);

	if ($backupSessionObj->folder_list[$itemName][SIZE] > 0 ) {
		array_push($backupSessionObj->sftp_zip_list, $filename);
	}
	
        //check result
        if (true === $result) {
	        $backupSessionObj->folder_list[$itemName][SUCCESS_YN] = Y;
	} else {
		$backupSessionObj->folder_list[$itemName][SUCCESS_YN] = N;
        } // end check run result

}

function mysql_backup($backupSessionObj) {

	logger_backup("Begin mysql_backup");

	global $tar_args, $tar_ext;

	$config = $backupSessionObj->config;

	if ($config[MYSQL] != Y) {
		logger_backup("Mysql Backups Not Configured");	
		logger_backup("End mysql_backup");
		return;
	}

	$mysql_connection = mysql_connect($config[MYSQL_HOST], $config[MYSQL_USERNAME], stripslashes($config[MYSQL_PASSWORD]));

	if (!$mysql_connection) {
		logger_backup("Could not connect to MySQL");
		logger_backup("End mysql_backup");
		return;
	} else {
		mysql_select_db("information_schema", $mysql_connection);
	}

	$sql = $config[MYSQL_QUERY];
	$dbname_res = mysql_query($sql);

	//loop thru dbs
	while ($dbname_obj = mysql_fetch_object($dbname_res)) {

		$dbname = $dbname_obj->Dname;
		if ($dbname == "information_schema") {
			continue;
		}
		$backupSessionObj->mysql_list[$dbname][DBNAME] = $dbname;

		//Construct SQL backup file name
		$filename = _build_file_name($config[ARCHIVE_LABEL], $dbname . "-mysql", $config[NOW], ".sql");
		$backupSessionObj->mysql_list[$dbname][FILENAME] = $filename;

		//Construct SQL the backup command
		$sql_command  = $config[MYSQL_EXE] . " --user=" . $config[MYSQL_USERNAME] . " --password=" . $config[MYSQL_PASSWORD] . " " . $dbname . " > "; 
		$sql_command .= $config[LOCAL_FOLDER] . $filename;

		//Run SQL Command
		logger_backup("dumping $dbname");
		$result = _run_command($backupSessionObj, $sql_command);

		//check result
		if (true === $result) {

			//execute zip
			logger_backup("zipping $dbname");
			$command         = $config[TAR_EXE]. $tar_args . $config[LOCAL_FOLDER] . $filename. $tar_ext ." ". $config[LOCAL_FOLDER] . $filename;
        		$result          = _run_command($backupSessionObj, $command);

			if (true === $result) {

				$backupSessionObj->mysql_list[$dbname][SUCCESS_YN] = Y;
				$zipname = $filename . $tar_ext;
		
				$backupSessionObj->mysql_list[$dbname][ZIPNAME] = $zipname;
				array_push($backupSessionObj->sftp_zip_list, $zipname);

				//populate size in model
				$backupSessionObj->mysql_list[$dbname][SIZE] = _file_size($backupSessionObj, $config[LOCAL_FOLDER] . $filename . $tar_ext);

				//remove file ONLY IF zip was successful
				@unlink($config[LOCAL_FOLDER] . $filename);

			} else {

				@unlink($config[LOCAL_FOLDER] . $filename . $tar_ext);

				logger_backup("zip failed, leaving pre-zipped file: " . $config[LOCAL_FOLDER] . $filename);

				$backupSessionObj->mysql_list[$dbname][SUCCESS_YN] = N;

			} // END if (true === $result)

		} else {

			$backupSessionObj->mysql_list[$dbname][SUCCESS_YN] = N;	

		} // END if (true === $result)

	} // END while

	//end while
	mysql_close($mysql_connection);

	logger_backup("End mysql_backup");
}

function dropbox_backup($backupSessionObj) {

	logger_backup("Begin dropbox_backup");

	$working_dir    = dirname(__FILE__);
	$config 	= $backupSessionObj->config;
	$local_folder   = $config[LOCAL_FOLDER];
	$archive_label 	= $config[ARCHIVE_LABEL];
	$now		= $config[NOW];

	$dest_folder	= "/".$archive_label."/".$now."/";
	$command_start  = "$working_dir/dropbox/dropbox_uploader.sh -f $working_dir/dropbox/dropbox_uploader.inc ";

	//make folders
	$command        = "$command_start mkdir $archive_label";
        $result         = _run_command($backupSessionObj, $command);

	$command 	= "$command_start mkdir $dest_folder";
	$result  	= _run_command($backupSessionObj, $command);

	for ($x = 0; $x < count($backupSessionObj->sftp_zip_list); $x++) {

		$file = $backupSessionObj->sftp_zip_list[$x];
		logger_backup("file to copy to dropbox: $file");

		// Build Command
		$command = "$command_start upload $local_folder$file $dest_folder";
		$result  = _run_command($backupSessionObj, $command);

		if ($result === false) {
			logger_backup("dropbox_backup::RunCommand Problem: $command");
		} else {
			$backupSessionObj->appendSFTPList($file);
		}
	}

	logger_backup("End dropbox_backup");
}

function email_results($backupSessionObj) {
	
	logger_backup("BEGIN email_results");

	$config = $backupSessionObj->config;
	
	$folder_list = $backupSessionObj->folder_list;
	$mysql_list = $backupSessionObj->mysql_list;
	$errors = $backupSessionObj->errors;
	$error_messages = $backupSessionObj->error_messages;
	$total_size = $backupSessionObj->total_size;
	$options = $backupSessionObj->getOptions();
	$sftp_list = $backupSessionObj->sftp_list;
	$dropbox_list = $backupSessionObj->dropbox_list;
	
	$now = $config[NOW];
	$archive_label = $config[ARCHIVE_LABEL];
	
	$message = "<TABLE>";
	$message .= "<TR><TD colspan='7'><H1>Backup Report</H1></TD></TR>";
	$message .= "<TR><TD colspan='7'>Archive Label: $archive_label </TD></TR>";
	$message .= "<TR><TD colspan='7'>Started: $now </TD></TR>";
	$message .= "<TR><TD colspan='7'>Completed: " . date(DATE_FORMAT) . " </TD></TR>";
	$message .= "<TR><TD colspan='7'>Errors: " . $errors . " </TD></TR>";
	$message .= "<TR><TD colspan='7'>Options: " . $options . " </TD></TR>";

	if (stripos($options,NOFOLDERS)===false) {
		if ( count($folder_list) > 0 ) {		
			$message .= "<TR><TD colspan='7'><br></TD></TR>";
			$message .= "<TR><TD colspan='4'><b>Folders:</b> </TD></TR>";
			$message .= "<TR>";
			$message .= "<TD>PATH</TD>";
			$message .= "<TD>SUCCESS_YN</TD>";
			$message .= "<TD>BYTE</TD>";
			$message .= "<TD>KILOBYTE</TD>";
			$message .= "<TD>MEGABYTE</TD>";
			$message .= "<TD>GIGABYTE</TD>";
			$message .= "<TD>FILENAME</TD>";
			$message .= "</TR>";
			foreach ($folder_list as $key => $value) {
				$message .= "<TR>";
				$message .= "<TD>".$value[PATH]."</TD>";
				$message .= "<TD>".$value[SUCCESS_YN]."</TD>";
				$message .= "<TD>".$value[SIZE]."</TD>";
				$this_size = bcdiv($value[SIZE],1024,4);	
				$message .= "<TD>".$this_size."</TD>";
				$this_size = bcdiv( bcdiv($value[SIZE],1024) ,1024,4);			
	                        $message .= "<TD>".$this_size."</TD>";
				$this_size = bcdiv( bcdiv(bcdiv($value[SIZE],1024),1024) ,1024,4);	
                	        $message .= "<TD>".$this_size."</TD>";
				$message .= "<TD>".$value[FILENAME]."</TD>";
				$message .= "</TR>";
			}
		}
	}

	if (stripos($options,NOMYSQL)===false) {
		if ( count($mysql_list) > 0 ) {
			$message .= "<TR><TD colspan='7'><br></TD></TR>";
			$message .= "<TR><TD colspan='4'><b>Mysql Databases:</b> </TD></TR>";
			$message .= "<TR>";
			$message .= "<TD>DBNAME</TD>";
			$message .= "<TD>SUCCESS_YN</TD>";
			$message .= "<TD>BYTES</TD>";
			$message .= "<TD>KILOBYTE</TD>";
	                $message .= "<TD>MEGABYTE</TD>";
        	        $message .= "<TD>GIGABYTE</TD>";
			$message .= "<TD>FILENAME</TD>";
			$message .= "</TR>";
			foreach ($mysql_list as $key => $value) {
				$message .= "<TR>";
				$message .= "<TD>".$value[DBNAME]."</TD>";
				$message .= "<TD>".$value[SUCCESS_YN]."</TD>";
				$message .= "<TD>".$value[SIZE]."</TD>";
				$this_size = bcdiv($value[SIZE],1024,4);
        	                $message .= "<TD>".$this_size."</TD>";
                	        $this_size = bcdiv( bcdiv($value[SIZE],1024) ,1024,4);
	                        $message .= "<TD>".$this_size."</TD>";
        	                $this_size = bcdiv( bcdiv(bcdiv($value[SIZE],1024),1024) ,1024,4);
                	        $message .= "<TD>".$this_size."</TD>";
				$message .= "<TD>".$value[FILENAME]."</TD>";
				$message .= "</TR>";
			}
		}
	}

	$message .= "<TR><TD colspan='7'><br></TD></TR>";	

	$message .= "<TR><TD colspan='7'><b>Total Size:</b></TD></TR>";
        $message .= "<TR><TD colspan='7'>Bytes: $total_size </TD></TR>";

	$this_size = bcdiv($total_size,1024,4);
        $message .= "<TR><TD colspan='7'>KiloBytes: $this_size </TD></TR>";

	$this_size = bcdiv( bcdiv($total_size,1024) ,1024,4);
        $message .= "<TR><TD colspan='7'>MegaBytes: $this_size </TD></TR>";

	$this_size = bcdiv( bcdiv(bcdiv($total_size,1024),1024) ,1024,4);
        $message .= "<TR><TD colspan='7'>GigaBytes: $this_size </TD></TR>";

	if (count($error_messages) > 0) {
		$message .= "<TR><TD colspan='7'><br></TD></TR>";
		$message .= "<TR><TD colspan='4'><b>Error Messages: </b></TD></TR>";
		for ($i = 0; $i < count($error_messages); $i++) {
			$message .= "<TR><TD colspan='7'>". $error_messages[$i] ."</TD></TR>";
		}
	}

	if (stripos($options,NODROPBOX)===false) {
		if ( count($dropbox_list) > 0 ) {
			$message .= "<TR><TD colspan='7'><br></TD></TR>";
			$message .= "<TR><TD colspan='4'><b>Files copied to DropBox</b> </TD></TR>";
			for ($i = 0; $i < count($dropbox_list); $i++) {
                		$message .= "<TR><TD colspan='7'>". $dropbox_list[$i] ."</TD></TR>";
                	}
		}
	}
	
	$message .= "</TABLE>";

	//Email auth
	if ($config[EMAIL_AUTH] == Y) {
		$email_auth = true;
	} else {
		$email_auth = false;
	}

	$email_subject = "Backup Report for $archive_label, Size: $this_size GB, Errors:" . $errors;  
	backup_email_send(
		$config[EMAIL_TO], 
		$config[EMAIL_FROM], 
		$config[EMAIL_FROM_NAME],
		$email_subject, 
		$message, 
		$config[EMAIL_USERNAME], 
		$config[EMAIL_PASSWORD],
		$config[EMAIL_SERVER],
		$email_auth,
		$config[EMAIL_PORT],
		$config[EMAIL_SECURE],
		true
	);
	
	logger_backup("Email Subject:" . $email_subject);
	logger_backup("BEGIN email_results");
}


function _set_error_message($backupSessionObj, $message) {
	$backupSessionObj->appendErrors($message);
}

function _file_size($backupSessionObj, $path_file) {
	$size = 0;

	if (file_exists($path_file)) {
		$size = filesize($path_file);
	}
	$backupSessionObj->appendSize($size);
	return $size;
}

function _report_string_pad($text, $required_len, $padder, $pad_type) {
	$text_len = strlen($text);
	$result = $text;
	
	if ($text_len < $required_len) {
		$result = str_pad($text, $required_len, $padder, $pad_type);
	} else {
		$result = substr($text, (-1 * $required_len));	
	}
	return $result;
}

function _run_command($backupSessionObj, $command) {

	$returnArry = execCommand($command);

	if ( $returnArry[0] != true ) {

		$message = "ERROR: ";
		for ( $x=0; $x < count($returnArry[2]); $x++ ) { 	
			$message .= $returnArry[2][$x]."\n";
		}
		_set_error_message($backupSessionObj, $message);
		
		$result = false;

	} else {
		$result = true;
	}

	return $result;
}

function execCommand($cmd) {

        $returnArry = array();
        $out[0]     = $cmd;

        exec("$cmd 2>&1", $out, $result);

        if ( $result != 0 ) {
                $success = false;
        } else {
                $success = true;
        }

        array_push($returnArry, $success);
        array_push($returnArry, $result);
        array_push($returnArry, $out);

        return $returnArry;
}

function _build_file_name($archiveLabel, $itemName, $dateStr, $extn) {
	//$filename = $archiveLabel . "-" . $itemName . "-" . $dateStr . $extn;
        $filename = $dateStr . "-" . $archiveLabel . "-" . $itemName .  $extn;
	return $filename;
}

?>
