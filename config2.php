<?php
//session_cache_expire(300);
//session_start();

//if (!get_magic_quotes_gpc()) $_POST = array_map('addslashes',$_POST);
function filter($output){
	if (is_array($output)) return $output;
	$search = array('/([\xa1-\xf9][\xa1-\xf9][\x5c])/','/([\xa1-\xf9][\x5c])[\x5c]/');
	$replace = array('\1\\','\1');
	return(htmlspecialchars(preg_replace($search,$replace,$output)));
}

function filter_slash($output){
	if (is_array($output)) return $output;
	$search = array('/([\xa1-\xf9][\xa1-\xf9][\x5c])/','/([\xa1-\xf9][\x5c])[\x5c]/');
	$replace = array('\1\\','\1');
	return preg_replace($search,$replace,$output);
}
ini_set("upload_max_filesize","512M");
set_time_limit(3600);
?>