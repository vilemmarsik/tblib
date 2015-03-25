<?php

/**
  * Library with functions used in both tblib_db.php and tblib_html.php .
  * @package TBLib
  * @filesource
  * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License
  **/

/** takes a hash, removes a key if exists, returns the value or null. */
function hash_get(&$hash,$key,$default=null,$remove=false)
{
	$val=$default;
	if( array_key_exists($key,$hash) )
	{
		$val=$hash[$key];
		if($remove)
			unset($hash[$key]);
	}
	return $val;
}

/** returns true when set and nonempty */
function set($a)
{	return isset($a) && ($a || is_numeric($a));	}


/** returns a hash containing keys and vals from $args if they exist there, and from $defaults otherwise */
function args_defaults($args,$defaults)
{
	if( is_null($args) ) return $defaults;
	foreach ( $defaults as $key=>$val )
		if( !isset( $args[$key] ) )
			$args[$key]=$val;
	return $args;
}

/** if the argument is not an array, converts it to an array having containing the argument */
function to_array($arg)
{	return ( is_array($arg) ? $arg : array($arg) );	}


$first=1; # number of the first page (0 or 1)

?>
