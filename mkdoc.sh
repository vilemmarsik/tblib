#!/bin/sh

phpdoc -t doc/tblib_api/ -o HTML:default:default -f tblib_db.php,tblib_html.php,tblib_common.php,tblib.php -dn TBLib

