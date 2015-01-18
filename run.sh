#!/bin/bash
WORKING_FOLDER="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
XML_FILE="config.xml"
LOG_FILE="backup.log"

php $WORKING_FOLDER/scripts/backup_main.php $WORKING_FOLDER/$XML_FILE $WORKING_FOLDER/$LOG_FILE 

# Options which override the config.xml
#=============
#DISPLAYCONFIG - only display the config, then quit	
#NOFOLDERS - don't backup local folders
#NOMYSQL - don't dump any MySQL Databases
#NOEMAIL - don't send an email report
#NODROPBOX - don't copy to DropBox
