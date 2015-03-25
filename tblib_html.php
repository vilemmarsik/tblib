<?php

/**
  * HTML related functions
  * @package TBLib
  * @filesource
  * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License
  **/

/** shared utils */
require_once("tblib_common.php");

$embed=http('embed');
$root=( $embed ? 'http://'.$_SERVER['SERVER_NAME'] : '' );
$dir=( $embed ? $root.dirname($_SERVER['SCRIPT_NAME']).'/' : '' );

/**
  * Prints HTML page header.
  * @param string $title page title
  * @param string $id ID of the body element
  **/
function html_header($args=null)
{
	global $root;
	$defaults=array(
		'calendar'=>0,			# if to initialize the Epoch calendar
		'charset'=>'utf-8',		# page charset
		'css_screen'=>array(),		# CSSs used for media screen
		'css_print'=>array(),		# CSSs used for media print
		'css'=>array(),			# CSSs used for all media
		'default_css'=>true,		# if to include default CSS files
		'doctype'=>'<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">',
		'gs_sortable'=>true,		# include script for table sorting
		'jquery'=>false,		# include jquery
		'icon'=>null,			# page icon
		'icontype'=>'image/png',	# page icon MIME type
		'id'=>null,			# page body tag ID
		'lang'=>'en',			# page language
		'script'=>array(),		# javascripts to include
		'title'=>'',			# page title
		'xmlns'=>'http://www.w3.org/1999/xhtml',	# XML namespace
		'xmllang'=>'en',		# XML language
	);
	$args=args_defaults($args,$defaults);

	# if user specified single values for selected args, convert to arrays
	foreach(array('script','css','css_screen','css_print') as $key)
		$args[$key]=to_array($args[$key]);

	# Default CSS files
	if( $args['default_css'] )
	{
#		array_unshift( $args['css_screen'], $root.'/tblib/css/screen.css' );
		array_unshift( $args['css_screen'], $root.'/tblib/css/common.css' );
		array_unshift( $args['css_print'], $root.'/tblib/css/print.css' );
	}

	# Table sorting support
	if( $args['gs_sortable'] )
		$args['script'][]=$root.'/scripts/gs_sortable.js';

	# Jquery support
	if($args['jquery'])
	{
		$args['script'][]=$root.'/scripts/jquery.js';
	}

	# Calendar support part 1
	if( $args['calendar'] )
	{
		$args['script'][]=$root.'/epoch/epoch_classes.js';
		array_unshift( $args['css_screen'], $root.'/epoch/epoch_styles.css' );
	}

	# DOCTYPE, header start
	$r =$args['doctype']."\n";
	$r.='<html xmlns="'.$args['xmlns'].'" xml:lang="'.$args['xmllang'].'" lang="'.$args['lang'].'">'."\n";
	$r.="<head>\n";


	# JavaScript
	foreach( $args['script'] as $script )
		$r.="\t".'<script language="JavaScript" src="'.$script.'" type="text/javascript"></script>'."\n";

	# CSS
	foreach($args['css'] as $css)
		$r.="\t".'<link href="'.$css.'" rel="stylesheet" type="text/css"/>'."\n";
	foreach($args['css_screen'] as $css)
		$r.="\t".'<link href="'.$css.'" rel="stylesheet" type="text/css" media="screen"/>'."\n";
	foreach($args['css_print'] as $css)
		$r.="\t".'<link href="'.$css.'" rel="stylesheet" type="text/css" media="print"/>'."\n";

	# Icon
	if($args['icon'])
		$r.="\t".'<link rel="icon" type="'.$args['icontype'].'" href="'.$args['icon'].'" />'."\n";

	# Title
	if($args['title'])
		$r.="\t<title>".$args['title']."</title>\n";

	# Content-Type
	$r.="\t".'<meta http-equiv="Content-Type" content="text/html; charset='.$args['charset'].'"/>'."\n";

	# End of header
	$r.="</head>\n";
	$r.='<body'.( $args['id'] ? ' id="'.$args['id'].'"': '').">\n";
	return $r;
}

/** Closes the page, adds some statistics etc. */
function html_footer()
{
#	echo "<hr/>\n";
	$r =cache_info();
	$r.="</body>\n";
	$r.="</html>";
	return $r;
}

/** Redirects to login page when not yet logged in. */ 
function check_user(){
#	session_start();
	if( !isset($_SESSION['user']) ) {
		header("Location: login.php");
		exit;
	}
}

/** 
  * Reads a HTTP request variable or null.
  * @param string $name name of the request variable
  * @return string|array variable value or null
  **/
function http($name,$default=null)
{
	if( !isset($_REQUEST[$name]) )
		return $default;
	if( $_REQUEST[$name]=='null' )
		return null;
	return $_REQUEST[$name];
}

/**
  * Fetches all HTTP arguments at once, using http() with no default.
  * call: http_fetch_all('param1','param2',...);
  * @return global variables $param1, $param2, ... are set.
  **/
function http_fetch_all()
{
	$args = func_get_args();
	foreach( $args as $arg )
		$GLOBALS[$arg] = http($arg);
}

if ( isset( $_SESSION['user'] ) )
	$log=array( "logout.php", "Logout");
else
	$log=array("login.php", "Login");

/**
  * Prints the upper QADB navigation menu.
  * @param array destinations array of array( <URL>, <text>)
  **/
function nav_bar($destinations)
{
	$r =sprintf("<div id=\"subnav\" class=\"color4\">");
	$r.=sprintf("<div id=\"subnav-hdr\">");
	$items=array();
	while ( list($key, $dest) = each($destinations))
	{
		if( is_array($dest[0] ) )
		{
# TODO: use recursion, make it cleaner
			$a=$dest[1].' ';
			foreach($dest[0] as $l )
				$a.=sprintf('<a href="%s" target="_top">%s</a> ',$l[0],$l[1]);
			$items[]=html_span('item',$a);
		}
		else
			$items[]=sprintf('<a class="item" href="%s" target="_top">%s</a>', $dest[0], $dest[1]);
	}
	$r.=join('|',$items);
	$r.="</div>";
	$r.="</div><br/>\n";
	return $r;
}



/**
  * Dumps a 2D array $data to HTML. 
  * Adds different classes for even and odd rows. 
  * Prints header.<pre>
  *   $data		2D array with results
  *   $attrs['links'] 	1D array with URLs; if set, first column will have a link
  *   $attrs['callback']	when set, it can append a class to highlight a row
  *             the returned value can be '' or should start with a space
  *   $attrs['id']	ID for the table element
  *   $attrs['class']	class for the table element
  *   $attrs['sort']	format for javascript sorter
  *		'i' - integer (non-integer data are ignored)
  *		's' - string
  *		'd' - date
  *		'0' - do not sort
  *		see http://www.allmyscripts.com/Table_Sort/index.html for more
  *		NOTE: set table ID to use this feature
  *   $attrs['total']	true to print total row count</pre>
  **/
function html_table($data,$attrs=array())
{
		
	global $first;
	if( !$data || !count($data) )
		return;

	# parse named arguments
	$cols=count($data[0]);
	$callback = hash_get($attrs,'callback',null ,true);
	$id       = hash_get($attrs,'id'      ,null ,true);
	$class    = hash_get($attrs,'class'   ,'tbl',true);
	$sort     = hash_get($attrs,'sort'    ,null ,true);
	$total    = hash_get($attrs,'total'   ,false,true);
	$header   = hash_get($attrs,'header'  ,true ,true);
	$evenodd  = hash_get($attrs,'evenodd' ,true ,true);
	$pager    = hash_get($attrs,'pager'   ,null ,true);

	if( isset( $pager ) )
	{	# pager data
		$count  = hash_get($pager,'count' ,null   ,false);
		$page   = hash_get($pager,'page'  ,$first ,false);
		$rpp    = hash_get($pager,'rpp'   ,null   ,false);
		$prefix = hash_get($pager,'prefix',''     ,false);
		$what   = hash_get($pager,'what'  ,array(),false);
		$base   = hash_get($pager,'base'  ,basename($_SERVER['PHP_SELF']),false);
		$base_pager = form_to_url($base,$what,1).'&amp;'.$prefix.'page=';
	}
	$pages = ( isset($count) && isset($rpp) ? ceil($count/$rpp) : null );
	$print_page_selector = ( isset($base) && isset($page) && isset($pages) && ($pages>1 || $count>20) );

	# empty table
	if( count($data) - ($header ? 1:0) == 0 )
		return '<p class="nodata">No data.</p>';

	$r='';

	# upper pager
	if( $print_page_selector )
		$r.=html_page_selector($pages,$page,$base_pager);

	# output table
	$r.="<table class=\"$class\"".($id? " id=\"$id\"":"").">\n";

	# table body
	$tbody = false;
	foreach(array_keys($data) as $i)
	{	
		if( $i=='header' || ( is_numeric($i) && $i==0 && $header ))	{
			# header
			$r.="<thead><tr>";
			foreach(array_values($data[$i]) as $col)
				$r.='<th>'.$col.'</th>';
			$r.="</tr></thead>\n";
		}
		else	{
			# body
			if( !$tbody )	{
				$r.="<tbody>\n";
				$tbody=true;
			}
			$class='';
			if( $evenodd )
				$class=( (($i&1)==0 ? 'even':'odd').'row' );
			if( $callback )
				$class .= call_user_func_array( $callback, $data[$i] );
			$r.="\t<tr".($class ?  " class=\"$class\"" : '').">";
			foreach(array_keys($header ? $data[0] : $data[$i]) as $col)	{
				$r.='<td';
				$d=( isset($data[$i][$col]) ? $data[$i][$col] : '' );
				if( !is_array($data[$i][$col]) )
					$r.='>'.$d;
				else	{
					$text=hash_get($d,'text','',true);
					foreach( $d as $key=>$val )
						$r.=" $key=\"$val\"";
					$r.='>'.$text;
				}
				$r.='</td>';
#				$r.="<td>".(isset($data[$i][$col]) ? $data[$i][$col]:'').'</td>';
			}
			$r.="</tr>\n";
		}
	}
	$r.="</tbody>\n</table>\n";

	if( $id && $sort )
	{	# sort script
		$r.='<script type="text/javascript">'."\n<!--\n";
		$r.="var TSort_Data = new Array ('$id'";
		for($i=0; $i<strlen($sort); $i++)
			$r.=", '".($sort[$i]=='0' ? '':$sort[$i])."'";
		$r.=");\nvar TSort_Classes = new Array ('oddrow', 'evenrow');\n";
		$r.="tsRegister();\n-->\n</script>\n";
	}
	if($total) # summary
		$r.='<div class="total">Total : '.(isset($count) ? $count : count($data)-$header)." matching records.</div>\n";
	
	# lower pager and form
	if( $print_page_selector )
	{
		$r.=html_page_selector($pages,$page,$base_pager);
		$r.=html_pager_form($what,$base,$page,$rpp,$prefix);
	}

	return $r;
}

/**
  * HTML contingency table.
  * WARNING!! $data needs to be ordered by 'group_y' !! <pre>
  * $data	2D hashmap with results
  * $attrs['group_y']	Y axis attributes, !!! $data must be sorted by this !!!
  * $attrs['group_x']	X axis attributes
  * $attrs['header']	1 to consider $data[0] to be a header
  * $attrs['aggregate_fields']		fields to aggregate into one
  * $attrs['aggregate_name']		name of the resulting column
  * $attrs['aggregate_callback']	callback to aggregate fields into one
  * $attrs['aggregate_arg']		extra argument passed to aggregate_callback
  * $attrs['filter_callback']		callback that can filter out some rows
  * $attrs['filter_arg']		extra argument passed to filter_callback </pre>
  *
  **/
function html_groupped_table($data,$args=array())
{
	$args = args_defaults( $args, array(
		'group_y'=>array(),		# Y attributes; !!! NOTE: $data must be sorted by group_y !!!
		'group_x'=>array(),		# X attributes
		'header'=>1,			# 1 to consider $data[0] to be a header
		'aggregate_fields'=>array(),	# fields to aggregate into one
		'aggregate_name'=>'summary',	# name of the resulting column
		'aggregate_callback'=>null,	# callback to aggregate fields into one
		'aggregate_arg'=>null,		# extra argument passed to aggregate_callback
		'filter_callback'=>null,	# callback that can filter out some rows
		'filter_arg'=>null		# extra argument passed to filter_callback
	));
	# concatenated axis values, to be used as hash keys
	$keys=array( 'y'=>array(), 'x'=>array() );

	# groupping attributes
	$group=array( 'y'=>$args['group_y'], 'x'=>$args['group_x'], 'a'=>$args['aggregate_fields'] );

	# split the table data into parts
	foreach( $data as $num=>$row )	{
		$is_header = ( $num=='header' || ($num==0 && $args['header']) );

		# key is the concatenated value, we define it here and just reuse later
		$key=array();

		foreach( array('y','x','a') as $i )	{

			# remove X/Y/aggregate fields from data
			$val[$i] = array();
			foreach( $group[$i] as $gkey )	{
				if( isset($row[$gkey]) )	{
					$val[$i][$gkey] = $row[$gkey];
					unset($row[$gkey]);
				}
			}

			# for X and Y, concatenate & make a key
			if( $i!='a' )	{
				$key[$i] = join('|',$val[$i]);
				if( !$is_header && !isset($keys[$i][$key[$i]]) )
					$keys[$i][$key[$i]] = $val[$i];
			}
		}

		# now replace the original data row
		$data[$num] = array( 
			'y_key'=>$key['y'],
			'x_key'=>$key['x']
		);
		$data[$num]['data'] = $row;

		# aggregation is made here
		if( $args['aggregate_callback'] && count($args['aggregate_fields']) )	{

			# data are prepared by the aggregation callback
			$data[$num]['data'][$args['aggregate_name']] = call_user_func_array($args['aggregate_callback'],array_merge($val['a'],array($args['aggregate_arg'])));

			# original values go here, can be used e.g. by filtering
			$data[$num]['aggr'] = $val['a'];
		}
	}
#	print "keys[y]="; print_r($keys['y']);
#	print "keys[x]="; print_r($keys['x']);
#	print "data="; print_r($data);

	# let's sort the X axis in a natural order
	# TODO: some data types should be sorted differently
	uksort( $keys['x'], 'strnatcasecmp' );

	# keys for X / Y and their values (in hashes)
	$val_y = $keys['y'];
	$val_x = $keys['x'];

	# data column names
	$val_fields = array_keys($data[0]['data']);

#	print "data=";print_r($data);
#	print "val_y="; print_r($val_y);
#	print "val_x="; print_r($val_x);
#	print "val_fields=";print_r($val_fields);

	# let's start printing
	$ret='<table class="tbl"><tr>';

	# header can have 1 or 2 rows, 2 we have multiple data fields needing description
	$header_height = ( count($val_fields) > 1 ? 2 : 1 );
	foreach( $args['group_y'] as $y )
		$ret .= "<th rowspan=\"$header_height\">$y</th>";
	foreach( $val_x as $x )	{
		$ret.='<th colspan="'.(count($val_fields)).'">'.join('<br/>',$x)."</th>";
	}
	$ret.="</tr>\n";

	# 2nd header row, if we have it
	if( $header_height==2 )	{
		$ret .= '<tr>';
		foreach( $val_x as $x )
			foreach( $val_fields as $f )
				$ret .= "<th>$f</th>";
		$ret .= "</tr>\n";
	}
	$ret.="</tr>\n";

	# data have the same ordering as $val_y, we just scan $val_y and take coming values from $data
	$i=($args['header'] ? 1 : 0);
	$row=0;
	foreach( array_keys($val_y) as $y )	{
#		print "y=$y\n";

		# $rows[X][row] is the structure holding data for one row, i.e. one Y value
		$rows=array();

		# number of fields in $rows columns can be different, this is the maximum
		$max_depth=0;

		# read all values from $data as long as they match Y, copy them into $rows
		for( ; isset($data[$i]) && $data[$i]['y_key']==$y; $i++ )	{
#			print "row="; print_r($data[$i]);
			$x_key = $data[$i]['x_key'];
			$num = (isset($rows[$x_key]) ? count($rows[$x_key]) : 0);
			$rows[$x_key][$num] = $data[$i]['data'];
			$max_depth = max( $max_depth, $num+1 );
		}
#		print "rows="; print_r($rows);
#		print "max_depth=$max_depth\n";

		# filtering: drop the row if it does not match the custom conditions
		if( $args['filter_callback'] && !call_user_func($args['filter_callback'],$rows,$args['filter_arg']) )
			continue;

		# print the row as multiple rows with Y value spanned over all of them
		$trow = '<tr class="'.(++$row & 1 ? 'even':'odd').'row">';
		foreach($val_y[$y] as $y_part)
			$trow .= '<td rowspan="'.$max_depth.'">'.$y_part.'</td>';
		for( $j=0; $j<$max_depth; $j++ )	{
			foreach( array_keys($val_x) as $x_key )	{
				$field = ( isset($rows[$x_key]) && count($rows[$x_key]) > $j ? $rows[$x_key][$j] : '' );
#				print "field="; print_r($field);
				if( !is_array($field) )
					$field = array($field);
				foreach( $val_fields as $f )	{
					if( !isset($field[$f]) )
						$field[$f]='';

					# field can be either sting and printed directly, or hash holding td's attributes
					if( is_array($field[$f]) )	{
						$tdata = $field[$f]['text'];
						unset( $field[$f]['text'] );
						$trow .= '<td';
						foreach( $field[$f] as $key=>$val )
							$trow .= " $key=\"$val\"";
						$trow .= ">$tdata</td>";
					}
					else
						$trow .= '<td>' . (isset($field[$f]) ? $field[$f] : '') . '</td>';
				}
			}
		}
		$ret .= $trow . "</tr>\n";
	}
	return $ret."</table>\n";	
}



/** Converts table's data to HTML entities when necessary. */
function table_htmlspecialchars(&$data)
{
	$cnti=count($data);
	for( $i=0; $i<$cnti; $i++ )
		foreach(array_keys($data[$i]) as $col)
			$data[$i][$col]=htmlspecialchars( $data[$i][$col]);
}

/** ads a column with checkboxes 
  * @param array &$data the table to modify
  * @param string $name checkbox name
  * @param string $base_index index of column containing the checkbox value
  * @param int $to_end zero inserts at beginning, nonzero at end
  * @param string $form_name name of the form, for javascript access
  * @param int $checked the initial state of the checkboxes (0/1, all the same)
  **/
function table_add_checkboxes(&$data,$name,$base_index,$to_end=0,$form_name='my_form',$checked=0)
{
	$d='<input type="checkbox" '.($checked ? 'checked="checked" ':'').'onclick="ToggleChecked(this);"/>';
	if( $to_end )
		$data[0][]=$d;
	else
		array_unshift($data[0],$d);
	for( $i=1; $i<count($data); $i++ )
	{
		$check = ( array_key_exists($checked,$data[$i]) ? $data[$i][$checked] : $checked );
		$d='<input type="checkbox" '.($check ? 'checked="checked" ':'').'name="'.$name.'" value="'.$data[$i][$base_index].'"/>';
		if( $to_end )
			$data[$i][]=$d;
		else
			array_unshift($data[$i],$d);
	}
?>
<script language="JavaScript" type="text/javascript">
<!-- 
function ToggleChecked(a) {
  for (var i = 0; i < document.<?php echo $form_name; ?>.elements.length; i++) {
    if(document.<?php echo $form_name; ?>.elements[i].type == 'checkbox'){
      document.<?php echo $form_name; ?>.elements[i].checked = !(document.<?php echo $form_name; ?>.elements[i].checked);
    }
  }
  a.checked = !(a.checked);
}
//-->
</script><?php
}


/**
  * Adds control icons to a table row
  * @param array &$row the row
  * @param $index_val the value to append to each URL
  * @param array $ctrls hash of ($text=>$url)
  * @param $index index of the column to append to $url
  * @see html_text_button(), make_user_controls_row(), make_admin_controls_row()
  **/
function make_controls_row($row,$index_val,$ctrls)
{
	$ctrl='';
	foreach( array_keys($ctrls) as $text )
		$ctrl.=' '.html_text_button($text,$ctrls[$text].$index_val);
	return $ctrl;
}

/**
  * Adds user control icons to a table row
  * @param array &$row the row
  * @param $index_val the value to append to each URL
  * @param array $ctrls hash of ($text=>$url)
  * @param $index index of the column to append to $url
  * @see html_text_button(), make_controls_row(), make_admin_controls_row()
  **/
function make_user_controls_row($row,$index_val,$ctrls)
{
	$ctrl='';
	foreach( array_keys($ctrls) as $text )
		$ctrl.=' '.html_user_button($text,$ctrls[$text].$index_val);
	return $ctrl;
}

/**
  * Adds admin control icons to a table row
  * @param array &$row the row
  * @param $index_val the value to append to each URL
  * @param array $ctrls hash of ($text=>$url)
  * @param $index index of the column to append to $url
  * @see html_text_button(), make_controls_row(), make_user_controls_row()
  **/
function make_admin_controls_row($row,$index_val,$ctrls)
{
	$ctrl='';
	foreach( array_keys($ctrls) as $text )
		$ctrl.=' '.html_admin_button($text,$ctrls[$text].$index_val);
	return $ctrl;
}


/**
  * Converts one table row to links.
  * @param array &$row the table row to output
  * @param array $orig original row values
  * @param array $l array of ($column1=>$base_1, ... )
  * @see table_translate(), make_controls_row(), enum_translate_row(), transform_url_row()
  * @return the converted row
  **/
function &make_links_row(&$row, $orig, $l)
{
	foreach(array_keys($l) as $col)
		if( isset($row[$col]) )
			$row[$col]=html_link($row[$col],htmlspecialchars($l[$col].$orig[$col]));;
	return $row;
}

/**
  * Replaces URL with a simple text inside a link.
  * @see table_translate(), make_controls_row(), enum_translate_row(), make_links_row()
  * @return the converted row
  **/
function &transform_url_row(&$row, $urls)
{
	foreach(array_keys($urls) as $col)
		if( isset($row[$col]) )
			$row[$col]=html_link(($urls[$col] ? $urls[$col] : $row[$col]), $row[$col]);
#	for( $i=0; $i<count($idx); $i++ )
#		if( isset($row[$idx[$i]]) )
#			$row[$idx[$i]]='<a href="'.$row[$idx[$i]].'">'.$text.'</a>';
	return $row;
}

/**
  * Generic HTML tag
  * $name is tag name, e.g. 'textarea'
  * $content is the text between opening and closing tag
  *    if none specified, a single xHTML tag will be created
  * $args are attributes in the form of name=>value
  **/
function html_tag($name,$content,$args)
{
	$attrs=array($name);
	foreach( $args as $key=>$val )
		if( !is_null($val) )
			$attrs[]="$key=\"".htmlspecialchars($val).'"';
	$a=join(' ',$attrs);
	return $content ? "<$a>$content</$name>" : "<$a/>";
}

/**
  * Prints a single HTML link.
  * @param string $text text to display
  * @param string $url URL to link to
  * @param string $tip optional tooltip
  **/
function html_link($text,$url,$tip=null,$class=null)
{
	return '<a href="'.$url.
		($tip ? '" title="'.$tip : '').
		($class ? '" class="'.$class : '').
		'">'.$text."</a>";
}

/**
  * Inserts the content into a span.
  **/
function html_span($class,$text,$title=null)
{	return '<span class="'.$class.'"'.($title ? " title=\"$title\"" : '').'>'.$text."</span>\n";	}

/**
  * Inserts the content into a div.
  **/
function html_div($class,$text)
{	return '<div class="'.$class.'">'.$text."</div>\n";	}


/**
  * Prints a clickable button.
  * @param string $text text of the control
  * @param string $url URL where the control points to
  * @param string $title the tooltip
  **/
function html_text_button($text,$url,$title=null)
{	return '<a href="'.htmlspecialchars($url).'" class="btn"'.($title ? " title=\"$title\"" : '').'>'.$text.'</a>';	}

/**
  * Prints a disabled button.
  * @param string $text text of the button
  * @param string $title the tooltip
  **/
function html_text_button_disabled($text,$title=null)
{	return html_span('btn_disabled',$text,$title);	}

/** Prints a message about failed operation. */
function html_error($msg, $deffered=false)
{	return html_message($msg, "error", $deffered);	}

/** Prints a message about successful operation. */
function html_success($msg, $deffered=false)
{	return html_message($msg, "success", $deffered);	}

/** Prints a message (success or error) */
function html_message($msg, $type=null, $deffered=false)
{
	if( $deffered )	{
		$_SESSION['message']=$msg;
		$_SESSION['mtype']=$type;
		return;
	}
	# Default the type if it is not provided
	if(!$type)	{
		$type = "error";
	}

	# A unique identifier for the message (so that there can potentially be more than one on a page)
	$id = mt_rand(100000, 999999);

	return "" .

		# The main dialog box
		'<div class="message ' . $type . '" id="message-' . $id . '">' .

			# The close button
			'<img src="/tblib/icons/close.png" class="close"
				id="message-close-' . $id . '" alt="Close this Message" title="Close this Message"
			/>' .

			# The message itself
			'<div class="text-main">' .
				htmlspecialchars($msg) .
			'</div>' .

		'</div>' .

		# Jquery effect
		'<script type="text/javascript">
			$("#message-close-' . $id . '").click(function()
			{
				$("#message-' . $id . '").fadeTo("slow", 0, function()
				{
					$(this).slideUp("slow")
				});
			});
		</script>' .

	"";
}

/** 
  * Prints message with result of an update operation.
  * @param int $n return value of the update operation
  * @param bool $is_insert true for insert - will consider $n as new ID
  **/
function update_result($n,$is_insert=false,$msg=null,$errmsg=null,$deffered=false)
{
	if( $n<0 ) {
		$err = isset ($errmsg) ? $errmsg : get_error ();
		print html_error($err, $deffered);
	} else {
		print html_success( $msg ? $msg : ($is_insert ? "Record inserted OK with ID=$n" : "$n row(s) processed OK"), $deffered );
	}
}

/* Constants for form elements */
define('SELECT_SINGLE',0);
define('SINGLE_SELECT',0);
define('SINGLESELECT',0);
define('SELECT_MULTI',1);
define('MULTI_SELECT',1);
define('MULTISELECT',1);
define('TEXT_ROW',2);
define('TEXTROW',2);
define('TEXT_AREA',3);
define('TEXTAREA',3);
define('CHECKBOX',4);
define('CHECK_BOX',4);
define('HIDDEN',5);
define('HR',6);
define('TEXT',7);
define('PASSWORD',8);
define('EMAIL',9);

/**
  * Returns HTML code for a common search form.
  * Usage: print html_search_form( $action, array( array($name, $values, $values_got, $type, [$label, [ $tooltip, [ $required, [ $pattern ]]]] ),...))
  *
  * $name the HTML name of the control
  *
  * $values is array of array( $id, $text )
  *
  * $values_got is the data got from HTTP request
  *
  * $type: 
  *    SINGLE_SELECT	single select
  *    MULTI_SELECT	multi select
  *    TEXT_ROW		text input (unused $values)
  *    TEXT_AREA	textarea
  *    CHECKBOX		checkbox
  *    HIDDEN		hidden
  *    HR		horizontal rule
  *    TEXT		text enclosed in div element
  *    PASSWORD		password field
  *    EMAIL		email field
  * $label is the text label to pring, $name by default
  * @param string $action URL where the form submit to
  * @param array $data array of array( $name, $values, $values_got, $type, [ $label, [ $tooltip, [ $required, [ $pattern ]]]] ) :
  **/
function html_search_form( $url, $data, $attrs=array() )
{
	$submit=hash_get($attrs,'submit','',false);
	$hr    =hash_get($attrs,'hr',true,false);
	$search=hash_get($attrs,'search',true,false);
	$form  =hash_get($attrs,'form',true,false);
	$div   =hash_get($attrs,'div','input',false);
	$method=hash_get($attrs,'method','get',false);
	$class =hash_get($attrs,'class','input',false);
	$id    =hash_get($attrs,'id','',false);
	$r='';
	if($form) $r.=sprintf('<form action="%s" class="%s" method="%s"%s>'."\n",$url,$class,$method,($id ? ' id="'.$id.'"':''));
	if($div ) $r.=sprintf('<div class="%s">'."\n", $div);
	foreach( $data as $d )
	{
		$cls = (isset($d[2]) && (is_numeric($d[2])||!empty($d[2])) ? 'set' : 'notset');
		$visible = ( $d[3]!=HR && $d[3]!=HIDDEN && $d[3]!=TEXT );
		$title = (isset ($d[5]) ? ' title="' . $d[5] . '"' : '');
		$required = (isset ($d[6]) && $d[6] ? ' required' : '');
		$pattern = (isset ($d[7]) ? ' pattern="' . $d[7] . '"' : '');
		if( !isset($d[4]) )
			$d[4]=$d[0];
		if( $visible )
			$r.="\t<div class=\"inputblock $cls\"><div class=\"inputtitle $cls\">".str_replace('_',' ',$d[4])."</div><div class=\"inputbody $cls\">";
		if( $d[3]==SINGLE_SELECT || $d[3]==MULTI_SELECT )
			$r.=base_select($d[0],$d[1],4,($d[3]==MULTI_SELECT) ? 'multiple="multiple"':'', $d[2], $cls);
		else if($d[3]==TEXT_ROW)
			$r.=sprintf('<input class="%s" type="text" name="%s"%s%s%s%s/>', $cls, $d[0],(set($d[2]) ? ' value="'.$d[2].'"':''), $required, $title, $pattern);
		else if($d[3]==TEXT_AREA)
			$r.=sprintf('<textarea class="%s" name="%s" rows="10" cols="80"%s%s>%s</textarea>',$cls,$d[0],$required,$title,$d[2]);
		else if ($d[3]==EMAIL)
			$r.=sprintf('<input class="%s" type="email" name="%s"%s%s%s/>', $cls, $d[0],(set($d[2]) ? ' value="'.$d[2].'"':''), $required, $title);
		else if($d[3]==CHECKBOX)
			$r.=sprintf('<input class="%s" type="checkbox" name="%s"%s%s%s/>', $cls, $d[0],($d[2] ? ' checked="checked"':''), $required, $title);
		else if($d[3]==HIDDEN && isset($d[2]) && $d[2]!='')
		{
			if( is_array($d[2]) )
				foreach( $d[2] as $e )
					$r.=sprintf('<input type="hidden" name="%s[]" value="%s"/>'."\n",$d[0],$e);
			else
				$r.=sprintf('<input type="hidden" name="%s" value="%s"/>'."\n",$d[0],$d[2]);
		} else if($d[3]==HR)
			$r.="\n<hr/>\n";
		else if($d[3]==TEXT)
			$r.='<div class="inputblock text notset">'.$d[4]."</div>\n";
		else if($d[3]==PASSWORD)
			$r.=sprintf('<input class="%s" type="password" name="%s"%s/>', $cls, $d[0], $required);
		if( $visible )
			$r.="</div></div>\n";
	}
	if( $search )
		$r.='<input type="hidden" name="search" value="1"/>'."\n";
	if($div) $r.="</div>\n";
	if( !$submit )
		$submit=array('');
	else if( !is_array($submit) )
		$submit=array($submit);
	foreach( $submit as $s )
		$r.='<input type="submit" class="btn submit"'.($s ? " value=\"$s\"":'').'/>'."\n";
	if($form) $r.="</form>\n";
	if( $hr )	$r.="<hr/>\n";
	return $r;
}

# select box wrapper and base functions

/**
  * returns HTML code for a selector
  * @param string $name HTML name
  * @param int $size number of rows
  * @param string $multiple appendable multiple HTML clause
  * @param array|string|int $set preselected value(s)
  **/
function base_select($name, $args, $size, $multiple, $set=null, $class='notset')
{
	if( ! is_array($args) || empty($args) )
		return;		

	$row_number=count($args[0]);
	$sel_vals=array();
	if( !is_array($set) )
		$set=array($set);

	$r =sprintf('<select name="%s" class="%s" %s>'."\n", $name.($multiple ? '[]':''), $class, $multiple);
	for ( $i=0; $i < count($args); $i++ ){
		$sel=in_array($args[$i][0],$set);
		if( $sel )
			$sel_vals[]=$args[$i][1];
		$r.=sprintf("\t<option value=\"%s\"%s>%s</option>\n", $args[$i][0], ($sel ? ' selected="selected"':''),$args[$i][1] );
	}		
	$r.="</select>\n";
	if( $sel_vals )
		$r.= html_span('static', join(', ',$sel_vals) );
	return $r;
}

/**
  * Prints card-like structure that can be used to divide something into steps.
  * @param array $what array of array( <label>, <link> )
  * @param int $selected index of selected card
  * @param array $alt alternative cards, only displayed those who match $selected
  **/
function steps( $base, $what, $selected, $alt=array() )
{
	$r ='';
	if( isset($alt[$selected]) )
		$what[$selected] = $alt[$selected];
	foreach($what as $key=>$val)
	{
		if( ! is_array($val) )
			$val = array($val);
		if( !isset($val[1]) || $val[1] || ($key==$selected) )
			$tag=sprintf('<a href="%s%s">%s</a>',$base,$key,$val[0]);
		else
			$tag=$val[0];
		$r .=  html_span( ($key==$selected ? 'sel':'nosel'), $tag );
	}
	$r = html_div('steps', $r);
	$r.= html_div('steps2', '&nbsp;');
	$r.='<div id="card">'."\n";
	return $r;
}

/** 
  * Generates a token and stores it in the session.
  * A update request should send it via HTTP.
  * The versions in HTTP and session should match, or no update done.
  * After a successful update, the token should be invalidated.
  * This can prevent repeated DB updates on reload.
  * This version stores last tokens in a hash, and removes then upon read,
  * which allows you to have multiple tabs open.
  * @see token_read
  * @return int the generated token number
  **/
function token_generate()
{
#	print "<pre>";print_r($_SESSION['token']);print "</pre>\n";
	global $token_last;
	if( isset($token_last) )
		return $token_last;
	if( !isset($_SESSION['token']) )
		$_SESSION['token']=array('last'=>1000);
	$token=$_SESSION['token']['last']+1;
	unset($_SESSION['token']['last']);
	while(count($_SESSION['token'])>10)
		array_shift($_SESSION['token']);
	if($token>9999)
		$token=1000;
	$_SESSION['token']['t'.$token]=true;
	$_SESSION['token']['last']=$token;
	$token_last=$token;
	return $token;
}

/** 
  * Checks if a token exists in the session, removes when found.
  * @see token_generate()
  * @param int $token the token from HTTP session
  * @return bool true if the token was in the session
  **/
function token_read($token)
{
	if(!isset($_SESSION['token']['t'.$token]))
		return false;
	unset($_SESSION['token']['t'.$token]);
	return true;
}

/**
  * Wrapper over preg_split.
  * Returns null for empty $field.
  **/
function field_split($field, $regexp='/[\s,;:|]+/')
{	return set($field) ? preg_split($regexp,$field) : null;	}

/**
  * Rounds $num to $valid_digits valid digits.
  **/
function sanitize_number($num,$valid_digits=5)
{
	if( !is_numeric($num) || is_nan($num) )
		return $num;
	if( $num==0 )
		return '0';
	$log=floor(log10(abs($num)));
	return round($num,$valid_digits-$log-1);
}

function __selector_element($url,$text,$active,$highlight)
{
	if( !$active )
		return html_span('btn_disabled'.($highlight ? ' highlight':''),$text);
	return html_link($text,$url,null,'btn');
}


/** prints a paging selector
  * @param int $count total pages count
  * @param int $page number of current page (from $first)
  * @param string $base base URL for links
  * @param int $middle_width number of marks in the middle
  **/
function html_page_selector($count,$page,$base,$middle_width=2)
{
	if( $count<2 ) return;
	global $first;
	$page -= $first;
	$skip = html_span('sel_skip','..');
	$parts = array();
	$parts[] = __selector_element($base.($page-1+$first),'&larr;',($page>0),0);
	$parts[] = __selector_element($base.$first,$first,($page!=0),1);
	$from = max(2,min($page-$middle_width,$count-3-2*$middle_width));
	$to = min($from+2*$middle_width,$count-1);
	$parts[] = ( $from>2 ? $skip : __selector_element($base.($first+1),$first+1,($page!=1),1));
	for( $i=$from; $i<=$to; $i++ )
		$parts[] = __selector_element($base.($i+$first),$i+$first,($page!=$i),1);
	if( $to+1<$count )
		$parts[] = ( $to+3<$count ? $skip : __selector_element($base.($to+1+$first),$to+1+$first,($page!=$to+1),1) );
	if( $to+2<$count )
		$parts[] = __selector_element($base.($count-1+$first),$count-1+$first,($page+1!=$count),1);
	$parts[] = __selector_element($base.($page+1+$first),'&rarr;',($page+1<$count),0);
	return html_div('pagesel',"\n\t".join("\n\t",$parts)."\n");
}

function form_to_url($base,$what,$search=0)
{
#	print "<pre>"; print_r($what); print "</pre>\n";
	$a = array();
	for( $i=0; $i<count($what); $i++ )
	{
		if( isset($what[$i][2]) && $what[$i][2]!='' )
		{
			if( is_array($what[$i][2]) )
				foreach( $what[$i][2] as $b )
					$a[] = $what[$i][0] . '[]' . '=' . $b;
			else
				$a[] = $what[$i][0] . '='. $what[$i][2];
		}
	}

	if( $search )
		$a[] = 'search=1';
	
	$append = ( count($a) ? join('&amp;',$a) : '' );
	$q = (strchr($base,'?') ? '&amp;' : '?');
	return ( empty($base) ? $append : "$base$q$append" );
}

# NOTE: may set a cookie, call before generating any HTML output
function pager_fill_from_http(&$pager=array(),$prefix='')
{
	global $first;
	$r = $prefix.'rpp_cookie';
	$pager['prefix']=$prefix;
	$pager['base']=basename($_SERVER['PHP_SELF']);
	$pager['page']=http($prefix.'page',$first);
	$rpp_default = (isset($_COOKIE[$r]) ? $_COOKIE[$r] : 20);
	$pager['rpp' ]=http($prefix.'rpp',$rpp_default)+0;
	if( !isset($_COOKIE[$r]) || $_COOKIE[$r]!=$pager['rpp'] )
		setcookie( $r, $pager['rpp'] );
#	print "<pre>";print_r($pager);print "</pre>\n";
	return $pager;
}

function html_pager_form($what,$base,$page,$rpp,$prefix)
{
	for( $i=0; $i<count($what); $i++ )
		$what[$i][3] = HIDDEN;
	$what[] = array($prefix.'page','',$page,TEXT_ROW,'Page');
	$what[] = array($prefix.'rpp','',$rpp,TEXT_ROW,'Display rows');
	return html_search_form($base,$what,array('submit'=>'Set'));
}

function html_confirm($text,$fields,$yes_url,$no_url=null)	{
	$no_url=( $no_url ? $no_url : $yes_url );
	$what=array();
	foreach( $fields as $key=>$val )
		$what[]=array($key,'',$val,HIDDEN);
	$what[]=array('wtoken','',token_generate(),HIDDEN);
	$ret=html_div('message',$text)."\n";
	$ret.=html_div('input', 
		html_search_form($yes_url,$what,array('submit'=>'Yes','hr'=>false))."\n".
		html_text_button('No',$no_url)
	);
	return html_div('confirm',$ret);
	
}

?>
