#!/bin/bash
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
rm -f $DIR/dropbox_uploader.inc
$DIR/dropbox_uploader.sh -f $DIR/dropbox_uploader.inc

