<?php

/**
  * TBLib -  DB, and MySQL related functions, mainly for tables
  * @package TBLib
  * @filesource
  * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License
  **/


/** shared utils */
require_once('tblib_common.php');

/**
  * Function to process GROUP BY and ORDER BY column specs
  * @param $cols array of columns
  * @param $by_nr column number(s) counted from 1, negative to append ' DESC'
  * @param $by_name column names (or any random description, this is not validated)
  * @return array of selected columns
  **/
function _select_columns($cols,$by_nr,$by_name)
{
	$ret=array();
	if( $by_nr )	{
		if( !is_array($by_nr) )
			$by_nr=array($by_nr);
		foreach( $by_nr as $nr )
			$ret[]=$cols[abs($nr)-1].($nr<0 ? ' DESC':'');
	}
	if( $by_name )	{
		if( is_array($by_name) )
			$ret=array_merge($ret,$by_name);
		else
			$ret[]=$by_name;
	}
	return $ret;
}

/**
  * Common search code.
  * Builds a search query, executes, returns the results.
  * @param array $sel array of attributes to select
  * @param string $from string containing the join to search from
  * @param array &$attrs requested search values, 
  *   in form of array( 'key1,key1,...'=>array(val1,val2,...), ... ). 
  *   Specifying multiple values means that all combinations are made, and ORed.
  * @param array &$attrs_known array of array(<SQL condition>, <bind format letter>)
  * @return array 2D array with search results
  **/
function search_common( $sel, $from, &$attrs, &$attrs_known, &$pager=null )
{
	global $first;
	$header   = hash_get($attrs,'header',true,true);	# return table header ?
	$limit    = hash_get($attrs,'limit',-1,true);		# row nr. limit (array)
	$order_nr = hash_get($attrs,'order_nr',0,true);		# index of ORDER BY columns
	$order_by = hash_get($attrs,'order_by',null,true);	# columns for ORDER BY
	$page     = hash_get($attrs,'page',$first,true);	# page number
	$perpage  = hash_get($attrs,'perpage',null,true);	# rows per page
	$where    = hash_get($attrs,'where',null,true);		# starting WHERE clause
	$just_sql = hash_get($attrs,'just_sql',false,true);	# return SQL instead of results ?
	$group_nr = hash_get($attrs,'group_nr',null,true);	# index of GROUP BY columns
	$group_by = hash_get($attrs,'group_by',null,true);	# columns for GROUP BY
	$cnt      = hash_get($attrs,'count',null,true);		# just a COUNT query

	$select=is_array($sel) ? join(', ',$sel) : $sel;
	$sql="FROM $from WHERE 1";

	if( $where )
		$sql .= " AND $where";

	# ORDER BY / GROUP BY clauses
	$order = _select_columns($sel,$order_nr,$order_by);
	$group = _select_columns($sel,$group_nr,$group_by);
	
	# WHERE clause
	$args=array();
	$format='';
	foreach($attrs as $keys=>$vals)
	{	# AND from all the conditions received
		if( !set($vals) ) continue;
		if( is_array($vals) && ( count($vals)==0 || !set($vals[0]) ) )
			continue;

		$keys=explode(',',$keys);

		$sql_part=array();
		foreach( $keys as $key )
		{	# OR from all key=>val combinations

			# check correct calling
			if( !isset($attrs_known[$key]) )
				abort("search_common: unknown attribute : $key");

			$a=$attrs_known[$key];
			
			# sanitize one-value arrays
			if( !is_array($vals) )
				$vals = array($vals);

			foreach( $vals as $val )
			{
				$sql_part[] = $a[0];
				if( isset($a[1]) )	{
					$format    .= $a[1];
					$args[]    .= $val;
				}
			}
		}
		if( count($sql_part) == 1 )
			$sql .= " AND ".$sql_part[0];
		else if( count($sql_part) > 1 )
		{
			$sql .= " AND ( 0";
			foreach( $sql_part as $s )
				$sql .= " OR $s";
			$sql .= ")";
		}
	}
	if( count($group) )
		$sql .= ' GROUP BY '.join(',',$group);
	if( count($order) )
		$sql.=' ORDER BY '.join(',',$order);
#	print("SQL : $sql<br/>\n");
#	print("format : $format<br/>\n");
#	print("Arguments : ");
#	print_r($args);
	array_unshift($args, $format);
	
	# query count
	if( $cnt || !is_null($pager) )
	{	
#		print "<pre>";print_r($pager);print "</pre>\n";
		$args1 = $args;
		array_unshift($args1,"SELECT COUNT(*) $sql");
		if( $cnt && $just_sql )
			return $args1;
		$count = call_user_func_array('scalar_query', $args1);
		if( $cnt )
			return $count;

		$limit = limit_from_pager($pager,$count);
	}
	if( $limit==-1  )
		$limit = array(5000);
	
	# query data
	array_unshift($args, $header, $limit, "SELECT $select $sql" );
	return $just_sql ? $args : call_user_func_array('mhash_query', $args);
}

function limit_from_pager(&$pager, $count)
{
	global $first;
	$limit = null;
	$pager['count'] = $count;
	if( isset($pager['page']) && isset($pager['rpp']) )	{
		$page = max( 0, min( $pager['page']-$first, ceil($count/$pager['rpp'])-1 ) );
		$limit = array( $page*$pager['rpp'], $pager['rpp'] );
		$pager['page'] = $page+$first;
	}
	return $limit;
}

##############################################################################
# tools to examine the database

/** Lists all available tables */
function list_tables($limit=null)
{	return matrix_query(0,$limit,"SHOW FULL TABLES WHERE Table_type='BASE TABLE'");	}

function get_create_table($table)
{
	# The sh*t called mysqli cannot do 'SHOW CREATE TABLE' using the same interface as prepared statements
	$result=uncached_query("SHOW CREATE TABLE $table");
	if( $result->num_rows > 0 )
	{
		$ret=$result->fetch_array();
		return $ret[1];
	}
	return null;
}

###############################################################################
# API for enum tables

/**  Lists enum tables, plus their columns for ID and name.
  *  Define your own, here is what we used: <pre>
$enums = array(
	'architectures'		=> array('archID','architecture'),
	'products'		=> array('productID','product'),
	'releases'		=> array('releaseID','`release`'),
	'kernel_branches'	=> array('branchID','branch'),
	'testsuites'		=> array('testsuiteID','testsuiteName'),
	'testcases'		=> array('testcaseID','testcaseName'),
	'bench_parts'		=> array('partID','part'),
	'rpm_basenames'		=> array('basenameID','basename'),
	'rpm_versions'		=> array('versionID','version'),
	'rpmConfig'		=> array('configID','md5sum')
	);
  * </pre>
  **/

/**  returns enum table's columns for ID and name, fails on unknown table */
function efields($tbl)
{
	if( !array_key_exists($tbl,$GLOBALS['enums']) )
		abort('Unknown enum tbl "'.$tbl.'"');
	return $GLOBALS['enums'][$tbl];
}

/**  returns enum table's ID column, fails on unknown table */
function eid($tbl)	{	$v=efields($tbl); return '`'.$v[0].'`';	}

/**  returns enum table's name column, fails on unknown table */
function ename($tbl)	{	$v=efields($tbl); return '`'.$v[1].'`';	}

$enum_cache=array();

/**  gets ID, returns name or null, caches the values */
function enum_get_val($tbl, $id)	
{	
	global $enum_cache;
	if( isset($enum_cache[$tbl]) && isset($enum_cache[$tbl][$id]) )
		$val=$enum_cache[$tbl][$id];
	else
	{
		$val=scalar_query('SELECT '.ename($tbl)." FROM `$tbl` WHERE ".eid($tbl).'=? LIMIT 1','i',$id);	
		$enum_cache[$tbl][$id]=$val;
	}
	return $val;
}

/**  gets array of IDs, returns array of names */
function enum_get_val_array($tbl, $ids)
{
	$ret=array();
	if( !is_array($ids) )
		$ids = array($ids);
	foreach( $ids as $id )
		$ret[]=enum_get_val($tbl,$id);
	return $ret;
}

/**  gets name, returns ID or null */
function enum_get_id($tbl, $name)	
{	return scalar_query('SELECT '.eid($tbl)." FROM `$tbl` WHERE ".ename($tbl)."=?",'s',$name);	}

/** gets list of names, returns list of IDs, omits unknown names, removes known names from &$names */
function enum_map_to_ids($tbl, &$names, $wildcard=0)
{
	$ret=array();
	$c=count($names);
	for( $i=0; $i<$c; $i++ )
	{
		if( $wildcard )
		{
			$ids=enum_get_id_wildcard($tbl,$names[$i]);
			$ret=array_merge($ret,$ids);
			if( $ids ) unset($names[$i]);
		}
		else
		{
			$id=enum_get_id($tbl,$names[$i]);
			if( !is_null($id) )
			{
				$ret[]=$id;
				unset($names[$i]);
			}
		}
	}
	return $ret;
}

/**  gets LIKE pattern ('%' as wildcard), returns array of matches or empty array */
function enum_get_id_wildcard($tbl, $like, $limit=null)
{	return vector_query($limit,'SELECT '.eid($tbl)." FROM `$tbl` WHERE ".ename($tbl)." LIKE ?",'s',$like);	}

/**  lists all IDs */
function enum_list_id($tbl, $limit=null)
{	return vector_query($limit,'SELECT DISTINCT '.eid($tbl)." FROM `$tbl` ORDER BY ".eid($tbl));	}

/** 2D listing of ( id, val ), sorted by val */
function enum_list_id_val($tbl, $header=0, $limit=null)
{	return matrix_query($header,$limit,'SELECT '.eid($tbl).','.ename($tbl)." FROM `$tbl` ORDER BY ".ename($tbl));	}

function enum_list_id_val_hash($tbl, $header=0, $limit=null)
{	return mhash_query($header,$limit,'SELECT '.eid($tbl).','.ename($tbl)."FROM `$tbl` ORDER BY ".ename($tbl));	}

/** lists all vals, sorted */
function enum_list_val($tbl, $limit=null)
{	return vector_query($limit,'SELECT DISTINCT '.ename($tbl)." FROM `$tbl` ORDER BY ".ename($tbl));	}

/**  inserts a new name, returns its ID */
function enum_insert($tbl, $val)
{	return insert_query("INSERT INTO `$tbl`(".ename($tbl).") VALUES(?)",'s',$val);	}

/**  checks if a value exists, creates a new one when not, returns the ID */
function enum_get_id_or_insert($tbl,$val)
{
	$id=enum_get_id($tbl,$val);
	if( $id )
		return $id;
	return enum_insert($tbl,$val);
}

/**  deletes all rows with a given ID */
function enum_delete_id($tbl, $id)
{	
	global $enum_cache;
	unset($enum_cache[$tbl]);
	return update_query("DELETE FROM `$tbl` WHERE ".eid($tbl)."=?",'i',$id);	
}

/** deletes all rows with a given value */
function enum_delete_val($tbl, $val)
{	
	global $enum_cache;
	unset($enum_cache[$tbl]);
	return update_query("DELETE FROM `$tbl` WHERE ".ename($tbl)."=?",'s',$val);	
}

function enum_rename_id($tbl, $id, $newval)
{
	global $enum_cache;
	unset($enum_cache[$tbl]);
	return update_query("UPDATE `$tbl` SET ".ename($tbl)."=? WHERE ".eid($tbl)."=?",'si',$newval,$id);
}

/**  returns count of unique IDs */
function enum_count($tbl)
{	return scalar_query("SELECT COUNT(DISTINCT ".eid($tbl).") FROM `$tbl`");	}

/**  lists all names for a given ID */
function enum_duplicates($tbl, $id, $limit=null)
{	return vector_query($limit,"SELECT ".ename($tbl)." FROM `$tbl` WHERE ".eid($tbl)."=?",'i',$id);	}

/**  translates an array containing IDs to their string representation 
  * $tr format: ( column1=>table1, column2=>table2, ... )
  **/
function &enum_translate_row(&$row, $tr)
{
	foreach(array_keys($tr) as $col)
		if(isset($row[$col]))
			$row[$col]=enum_get_val($tr[$col],$row[$col]);
	return $row;
}

/**  runs enum_translate_row on every row in $matrix */
function &enum_translate_table(&$matrix, $tr, $skip_header=1)
{
	for( $i=($skip_header?1:0); $i<count($matrix); $i++ )
		enum_translate_row($matrix[$i], $tr);
	if( $skip_header )
		foreach(array_keys($tr) as $col)
			$matrix[0][$col]=preg_replace('/_?ID$/i','',$matrix[0][$col]);
	return $matrix;
}


###############################################################################
# common SQL wrappers for preparing, caching & executing SQL, and fetching data
# conventions:
# limit: array( $max_rows ) OR array( $first_row, $max_rows )
# query: string with the SQL query, optionally with '?' for bind parameters
# format: string with formats for each of the bind parameter types:
#   'i'=>integer, 'd'=>double, 's'=>string, 'b'=>blob (not supported yet)
#   example: 'iisi' for 3rd arg string and the other three integers
# param1,... paramN: bind parameters

$trans=false;

/**  
  * starts a transaction 
  * see abort(), commit(), rollback()
  **/
function transaction()
{
	global $is_pdo,$pdo;
	if( $is_pdo )
		$pdo->beginTransaction();
	else
		uncached_query('START TRANSACTION');	
	$GLOBALS['trans']=true;
}

/**  
  * commits a transaction 
  * see abort(), rollback(), transaction()
  **/
function commit()
{
	global $is_pdo,$pdo;
	if( $is_pdo )
		$pdo->commit();
	else
		uncached_query('COMMIT');	
	$GLOBALS['trans']=false;
}

/**
  * rollbacks a transaction 
  * see abort(), commit(), transaction()
  **/
function rollback()
{
	global $is_pdo,$pdo;
	if( $is_pdo )
		$pdo->rollBack();
	else
		uncached_query('ROLLBACK');
	$GLOBALS['trans']=false;
}

/**  
  * equivalent of die(), rollbacks the transaction before 
  * @param string $msg text to display
  * see commit(), rollback(), transaction()
  **/
function abort($msg)
{
	if($GLOBALS['trans'])
		rollback();
	die($msg);
}

/**
  * Runs a list of commands until the first failure
  * @param array $commands list of commands (strings or arrays containing arguments)
  * @return int number of commands successfully executed
  **/
function update_sequence($commands)
{
	for( $i=0; $i<count($commands); $i++ )	{
		$cmd = $commands[$i];
		if( !is_array($cmd) )
			$cmd = array($cmd);
#		print "<pre>\n";
#		print_r($cmd);
#		print "</pre>\n";
		$ret = call_user_func_array('update_query',$cmd);
		if( $ret<0 )
			return $i-1;
#		print "OK<br/>\n";
	}
	return count($commands);
}

/**
  * Executes a statement or fails, returns nr of affected rows.
  *  usage: update_query( $query, [$format, $param1, ...] )
  * @return int number of affected rows
  * @see matrix_query(), row_query(), scalar_query(), vector_query(), insert_query()
  **/
function update_query()
{
	$args=func_get_args();
	array_unshift($args,null);	// the LIMIT clause
	$statement=call_user_func_array('cached_query',$args);
	if( $statement ) 
		return ($GLOBALS['is_pdo'] ? $statement->rowCount() : $statement->affected_rows);
	return -1;
}

/**
  * Executes a statement or fails.
  * usage: insert_query( $query, [$format, $param1, ...] )
  * @return int last inserted ID or 0
  * @see matrix_query(), row_query(), scalar_query(), vector_query(), update_query()
  **/
function insert_query()
{
	global $is_pdo,$pdo;
	$args=func_get_args();
	array_unshift($args,null);	// the LIMIT clause
	$statement=call_user_func_array('cached_query',$args);
	if( $statement )
		return ($is_pdo ? $pdo->lastInsertId() : $statement->insert_id);
	return -1;
}

/**
  * Executes a query, returns first row first column, or null.
  * usage: scalar_query( $query, [$format, $param1, ...] )
  * @return scalar first row first column, or null
  * @see matrix_query(), row_query(), vector_query(), update_query(), insert_query()
  **/
function scalar_query()
{
	$ret=null;
	$args=func_get_args();
	array_unshift($args,null);	// the LIMIT clause
	$statement=call_user_func_array('cached_query',$args);
	if( !$statement )
		return null;
	if( $GLOBALS['is_pdo'] )	{
		$ret=$statement->fetchColumn();
	}
	else	{
		$statement->bind_result($col);
		if( $statement->num_rows > 0 )	{
			$statement->fetch();
			$ret=$col;
		}
	}
	return $ret;
}

/**
  * Executes a query, returns first column or empty array.
  * usage: vector_query( $limit, $query, [$format, $param1, ...] )
  * @return array first column or empty array
  * @see matrix_query(), row_query(), scalar_query(), update_query(), insert_query()
  **/
function vector_query()
{
	$ret=array();
	$args=func_get_args();
	$statement=call_user_func_array('cached_query',$args);
	if( !$statement )
		return null;
	if( $GLOBALS['is_pdo'] )	{
		while( $row=$statement->fetch(PDO::FETCH_NUM) )
			$ret[]=$row[0];
	}
	else	{
		$statement->bind_result($col);
		while($statement->fetch())
			$ret[]=$col;
	}
	return $ret;
}

/**
  * Executes a query, returns first row or empty array.
  * usage: row_query( $query, [$format, $param1, ...] )
  * @return array first row or empty array
  * @see matrix_query(), scalar_query(), vector_query(), update_query(), insert_query()
  **/ 
function row_query()
{
	$args=func_get_args();
	array_unshift($args,null);
	array_unshift($args,0);
	$data=call_user_func_array('mhash_query',$args);
	if($data && count($data)>0)
		return $data[0];
	return array();
}

/**
  * Executes a query, returns array of results or empty array.
  * Usage: $array = matrix_hash_query( $hash, $header, $limit, $query, $format, [ $param1 ... ] )
  *
  * $hash=0 - returns array of arrays; $hash=1 - returns array of hashes
  * header<>0 - a header returned as the first row; NOTE: excluded from LIMIT clause
  *
  * uses some hacks forcing mysqli to return array of results, which is what the s***t cannot do
  *
  * @return array 2D array of results
  * @see row_query(), scalar_query(), vector_query(), update_query(), insert_query()
  **/
function matrix_hash_query()
{
	global $is_pdo;
	$ret=array();
	$col_names=array();
	$idx=0;
	$args=func_get_args();
	$hash  =array_shift($args);
	$header=array_shift($args);
	$statement=call_user_func_array('cached_query',$args);
	if( !$statement )
		return null;
	$names=array();
	if( $is_pdo )	{
		for( $i=0; $meta=$statement->getColumnMeta($i); $i++ )
			$names[]=$meta['name'];
	}
	else	{
		$meta=$statement->result_metadata();
		while($column=$meta->fetch_field())
			$names[]=$column->name;
	}
	foreach( $names as $name )	{
		$replaced=str_replace(' ','_',$name);
		$col_names[]=$replaced;
		if( $header )
			if( $hash )
				$ret[0][$replaced]=$name;
			else
				$ret[0][]=$name;
		if( !$is_pdo )	{
			$bindVarArray[]=&$ret2[$replaced];
		}
	}
	if( !$is_pdo )
		call_user_func_array(array($statement,'bind_result'),$bindVarArray);
	while( $is_pdo ? 
		$ret2=$statement->fetch(PDO::FETCH_NUM) : 
		$statement->fetch() 
	)	{
		$row=array();
		$i=0;
		foreach( $ret2 as $val )
			if( $hash )
				$row[$col_names[$i++]]=$val;
			else
				$row[]=$val;
		$ret[]=$row;
	}
	return $ret;
}

/**
  * Executes a query, returns array of results or empty array.
  * Usage: $array = matrix_query( $header, $limit, $query, $format, [ $param1 ... ] )
  *
  * header<>0 - a header returned as the first row; NOTE: excluded from LIMIT clause
  *
  * @return array 2D array of results
  * @see row_query(), scalar_query(), vector_query(), update_query(), insert_query()
  **/
function matrix_query()
{
	$args=func_get_args();
	array_unshift($args,0);
	return call_user_func_array('matrix_hash_query',$args);
}

/**
  * Executes a query, returns array of results or empty array.
  * Usage: $arrayhash = mhash_query( $header, $limit, $query, $format, [ $param1 ... ] )
  *
  * header<>0 - a header returned as the first row; NOTE: excluded from LIMIT clause
  *
  * @return array of hashes with results
  * @see row_query(), scalar_query(), vector_query(), update_query(), insert_query()
  **/
function mhash_query()
{
	$args=func_get_args();
	array_unshift($args,1);
	return call_user_func_array('matrix_hash_query',$args);
}

/**
  * Gets a SQL select, makes it to 'SELECT COUNT(*) FROM ( <original select> ) tbl'.
  * Usage: $count = count_query( $query, $format, [ $param1, ... ] )
  * @return count of rows for $select
  * @see scalar_query()
  **/
function count_query()
{
	$args=func_get_args();
	$args[0] = 'SELECT COUNT(*) FROM ('.$args[0].') tbl';
	return call_user_func_array('scalar_query',$args);
}

###############################################################################
# Statement LRU cache
# PHP sucks!
# 1. there is no API for prepared statements in the PHP mysql module, you need to use mysqli instead
# 2. in mysqli, you need to use mysqli_stmt class for prepared statements
# 3. mysqli_stmt class cannot return the data rows as arrays ( or even any normal data )
# 4. in order to return arrays of data from mysqli_stmt, you need to do ugly hacks

cache_clear();

function cache_clear()
{
	global $sql_cache,$starttime,$sql_cache_size,$sql_cache_hits,$sql_cache_misses,$sql_cache_replaces,$maxtqtime,$maxqsql;
	$sql_cache=array();
	$starttime=time();
	if( !isset($sql_cache_size) )
		$sql_cache_size=8;
	$sql_cache_hits=0;
	$sql_cache_misses=0;
	$sql_cache_replaces=0;
	$maxqtime=0;
	$maxqsql='';
}

/**
  * Prepares statements, stores them in a LRU cache.
  * Do not call directly.
  * @param string $query the statement to prepare
  * @return MySQLi_STMT the prepared statement
  * @see cached_query()
  **/
function cache_statement($query)
{
	global $mysqli,$pdo,$is_pdo,$sql_cache,$sql_cache_size,$sql_cache_hits,$sql_cache_misses,$sql_cache_replaces;
#	print "<pre>before:";print_r($sql_cache); print "</pre>\n";
	if( ! array_key_exists($query,$sql_cache) )
	{
		$sql_cache_misses++;
		if( count($sql_cache) >= $sql_cache_size )
		{
			$stmt=array_shift($sql_cache);
			if( !$is_pdo )
				mysqli_stmt_close($stmt);
			unset($stmt);
			$sql_cache_replaces++;
		}
		if( $is_pdo )
			$statement=$pdo->prepare($query);
		else
			$statement=$mysqli->prepare($query);
		if($statement) 
			$sql_cache[$query]=$statement;
		else
			if( $is_pdo || $mysqli->errno != 1142 )
				abort("Cannot prepare statement '$query': ".$mysqli->error);
			else	
				return null;
	}
	else
	{
		$sql_cache_hits++;
		$statement=$sql_cache[$query];
#		unset($sql_cache[$query]);
#		$sql_cache[$query]=$statement;
	}
//	print_r($sql_cache);
	return $statement;
}

/**
  * Prepares, caches, and executes a query/statement.
  * Do not call directly.
  * @return MySQLi_Result result object
  * @see cache_statement()
  **/
function cached_query()
{
	global $maxqsql,$maxqtime,$is_pdo,$pdo_error;
	$start=time();
	$args=func_get_args();
	$limit=array_shift($args);
	$query=array_shift($args);
	if(isset($limit) && is_array($limit) && count($limit)>0 && count($limit)<3)
	{	// adding the LIMIT clause
		if(!isset($args[0])) $args[0]='';
		$args[0].=str_repeat('i',count($limit));
		$query.=' LIMIT '.join(',',array_fill(0,count($limit),'?'));
		$args=array_merge($args,$limit);
	}
	$statement=cache_statement($query);
	if(!$statement)
		return null;
	if(count($args)>1)	{
		if( $is_pdo )	{
			for( $i=1; $i<=strlen($args[0]) && $i<count($args); $i++ )	{
				# type mapping: PDO does not know date, mysqli bool
				# we do not support blobs yet
				# so we only support 'int' and default to 'string' otherwise
				$type=( $args[0][$i-1]=='i' ? PDO::PARAM_INT : PDO::PARAM_STR );
				$statement->bindValue($i,$args[$i],$type);
			}
		}
		else	{
			foreach($args as $key => $value)
				$args[$key] = &$args[$key];
			call_user_func_array(array($statement,'bind_param'),$args);
		}
	}
	if( !$statement->execute() )	{
		if( $is_pdo && $statement->errorCode()==42000 )	{
			$pdo_error=join(' ',$statement->errorInfo());
			return null;
		}
		else
			abort($is_pdo ? join(' ',$statement->errorInfo()) : $statement->error);
	}

	if( $is_pdo )
		$pdo_error='';
	else
		$statement->store_result();
	$time=time()-$start;
	if($time>$maxqtime)
	{
		$maxqtime=$time;
		$maxqsql=join('|',$args);
		$maxqsql="$query $maxqsql";
	}
	return $statement;
}

/**
  * Prints HTML info about the SQL statement cache.
  * @see cache_statement
  * @see cached_query()
  **/
function cache_info()
{
	global $sql_cache,$sql_cache_size,$sql_cache_hits,$sql_cache_misses,$sql_cache_replaces,$starttime,$maxqtime,$maxqsql;
	$time=time()-$starttime;
	$show=array('max size',$sql_cache_size,'act size',count($sql_cache),'hits',$sql_cache_hits,'misses',$sql_cache_misses,'replaces',$sql_cache_replaces, 'runtime', date('i:s',$time), 'slowest query time', date('i:s',$maxqtime), 'slowest query', $maxqsql);
	$r ='<div class="sqlinfo"><span class="header">SQL cache info</span>'."\n";
	for( $i=0; $i<count($show); $i+=2 )
		$r.="\t".'<span class="sqlitem"><span class="header">'.$show[$i].'</span><span class="data">'.$show[$i+1]."</span></span>\n";
	$r.="</div>\n";
	return $r;
}

/**  returns error description */
function get_error()
{
	global $mysqli,$is_pdo,$pdo_error;
	if( $is_pdo )
		return $pdo_error;
	else
		return $mysqli->error;	
}

/**
  * Runs a SQL without preparing and caching.
  * Useful for transactions & structure changes, 
  * the MySQL protocol cannot run them as prepared statements.
  * Do not use for queries - the connection might get blocked.
  * @param string $sql the SQL statement
  * @return mixed true/false, number of affected rows, etc.
  */
function uncached_query($sql)
{
	global $mysqli,$pdo,$is_pdo;
	if( $is_pdo )
		return $pdo->execute($sql);
	else
		return $mysqli->query($sql);	
}

###############################################################################
# MySQL connect stuff

/** 
  * Connects to the database
  * @global MySQLi resulting DB object
  * @global string hostname
  * @global string username
  * @global string password
  * @global string database
  **/
function connect_to_mydb()
{
	global $mysqli,$mysqlhost,$mysqluser,$mysqlpasswd,$mysqldb,$mysqlcharset,$pdo,$is_pdo;
	if ( !isset($mysqlcharset)) {
		$mysqlcharset = 'UTF8';
	}
	if( !isset($mysqlhost) || !isset($mysqluser) || !isset($mysqldb) )	{
		require_once('myconnect.inc.php');
	}
#	print("host=$mysqlhost,user=$mysqluser,pwd=$mysqlpasswd,db=$mysqldb");
	if( $is_pdo )	{
		try {
			$pdo=new PDO("mysql:dbname=$mysqldb;host=$mysqlhost",$mysqluser,$mysqlpasswd);
			$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
			$pdo->exec("SET NAMES $mysqlcharset");
			return $pdo;
		} catch( Exception $e ) {
			return null;
		}
	}
	else {
		$mysqli=@new mysqli($mysqlhost,$mysqluser,$mysqlpasswd,$mysqldb);
		if( mysqli_connect_error() )
			return null;
		$mysqli->query("SET NAMES UTF8");
		return $mysqli;
	}
}

function disconnect_from_mydb()
{
	global $mysqli,$pdo,$is_pdo;
	if( $is_pdo )	{
# PDO still has no disconnect after years - see https://bugs.php.net/bug.php?id=62065
		$pdo = null;
	}
	else	{
		$mysqli->close();
		$mysqli = null;
	}
	cache_clear();
}

function mysql_foreign_keys($header,$limit,$count=array(),$table=null,$column=null,$table_ref=null,$column_ref=null)	{
	global $mysqldb;
	$fields = array(
		# array( value, column, alias ),
		array($mysqldb,'constraint_schema',null), # should be always in condition
		array($table,'table_name','`table`'),
		array($column,'column_name','`column`'),
		array($table_ref,'referenced_table_name','table_ref'),
		array($column_ref,'referenced_column_name','column_ref'),
	);
	$sel=array('constraint_name' . ($count ? '':' AS name'));
	$where=array('1');
	$args=array();
	foreach( $fields as $f )	{
		if( $f[0]==null )
			$sel[] = $f[1] . (!$count && $f[2] ? ' AS '.$f[2] : '');
		else	{
			$where[] = $f[1].'=?';
			$args[] = $f[0];
		}
	}
	$select = join(',',$sel);
	if( $count )
		$select .= ',COUNT('.(count($sel) ? 'DISTINCT '.join(',',$sel) : '*').') AS count';
	$sql = "SELECT $select FROM information_schema.key_column_usage WHERE ".join(' AND ',$where);
	if( $count && count($sel))
		$sql .= ' GROUP BY '.join(',',$sel);
	print "<pre>\n";
	print_r($sel);
	print_r($select);
	print_r($where);
	print_r($args);
	print_r($sql);
	print "</pre>\n";
	$format = str_repeat('s',count($where)-1);
	$call=array_merge(array($header,$limit,$sql,$format),$args);
	return call_user_func_array('mhash_query',$call);
}

/**
  * Lists foreign keys
  **/
function mysql_foreign_keys_list_all()	{
	global $enums,$mysqldb;
	$tables="'".join("','",array_keys($enums))."'";
	return mhash_query(1,null,"SELECT referenced_table_name AS `table`, GROUP_CONCAT(table_name,'.',column_name SEPARATOR ' ') AS reference FROM information_schema.key_column_usage WHERE referenced_table_name IN($tables) AND table_schema=? AND referenced_table_schema=? GROUP BY referenced_table_name",'ss',$mysqldb,$mysqldb);
}

/**
  * Lists tables and columns referencing a table
  * When $usage=1, prints a statistics instead
  **/
function mysql_foreign_keys_list($tbl,$usage=0,$header=1,$limit=array(5000))	{
	global $mysqldb;
	$data=mhash_query($header,$limit,"SELECT table_name AS `table`,column_name AS `column` FROM information_schema.key_column_usage WHERE referenced_table_name=? AND table_schema=? AND referenced_table_schema=?",'sss',$tbl,$mysqldb,$mysqldb);
	if( !$usage )
		return $data;
	$eid=eid($tbl);
	$ename=ename($tbl);

	$c=array();
	$sub=array();
	for( $i=($header ? 1:0); $i<count($data); $i++ )	{
		$t=$data[$i]['table'];
		$a=$data[$i]['column'];
		$ci='c'.$i;
		$c[$ci] = "$ci AS '$t<br/>$a'";
		$sub[] = "(SELECT COUNT(*) FROM `$t` WHERE t.$eid=`$t`.`$a`) AS $ci";
	}
	$fields = "$eid,$ename,".join(',',array_values($c)).','.join('+',array_keys($c)).' AS total';
	$sql = "SELECT $fields FROM ( SELECT t.$eid,t.$ename,".join(',',$sub)." FROM `$tbl` t ) x";
	$sql .= ' ORDER BY total DESC';
#	print "<br/><pre>SQL=$sql</pre><br/>\n";
	return mhash_query($header,$limit,$sql);
}

/**
  * returns tables/columns that reference input $table/$field with a FK.
  **/
function mysql_referers($header,$table,$field)
{
	global $mysqldb;
	return mhash_query(1,array(),"SELECT table_name AS `table`,column_name AS `column` FROM information_schema.key_column_usage WHERE referenced_table_name=? AND referenced_column_name=? AND table_schema=? AND referenced_table_schema=?",'ssss',$table,$field,$mysqldb,$mysqldb);
}

?>
