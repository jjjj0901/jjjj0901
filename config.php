<?php
$mysql_host = "localhost";
$mysql_user = "root";
$mysql_pw = "123";
$mysql_db = "oes40620318l";

@mysql_connect($mysql_host,$mysql_user,$mysql_pw) or die("系統超載中....請稍後再試");
@mysql_selectdb($mysql_db);
@mysql_query("set character_set_client='big5'") or die(mysql_error());
@mysql_query("set character_set_connection='big5'") or die(mysql_error());
@mysql_query("set character_set_results='big5'") or die(mysql_error());

function show($showdata){
	$showdata = str_replace("\r","",$showdata);
	$showdata = str_replace("\n","<br>",$showdata);
	return($showdata);
}

function show_html($showdata){
    if(!eregi("<br />", $showdata)) {
        $showdata = preg_replace("/\r\n|\n/", "<br />", $showdata);
    } 
	return $showdata;
}
$grade_array = Array("一", "二", "三", "四", "大碩合開", "碩博班", "不分年級");   // 年級
$domain_array = Array("國文教學領域", "經學史學領域", "語言文字領域", "哲學領域", "文學領域");  // 學門領域
?>
