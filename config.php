<?php
$mysql_host = "localhost";
$mysql_user = "root";
$mysql_pw = "123";
$mysql_db = "oes40620318l";

@mysql_connect($mysql_host,$mysql_user,$mysql_pw) or die("�t�ζW����....�еy��A��");
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
$grade_array = Array("�@", "�G", "�T", "�|", "�j�ӦX�}", "�ӳկZ", "�����~��");   // �~��
$domain_array = Array("���оǻ��", "�g�ǥv�ǻ��", "�y����r���", "���ǻ��", "��ǻ��");  // �Ǫ����
?>
