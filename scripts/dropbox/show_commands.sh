#!/bin/bash
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
$DIR/dropbox_uploader.sh -f $DIR/dropbox_uploader.inc
