<?php
/*************************************************************************
 * Filename     : comment.php                                            *
 * Author       : A.L. KU                                                *
 * Description  : 評論                                                   *
 *************************************************************************/
include_once "include/config.inc";
$comment = new COMMENT();

class COMMENT {
    function COMMENT() {
        global $db, $CFG, $tpl;

        foreach($_REQUEST as $key => $value) {
            if(is_array($value)) {
                $this->frm[$key] = $value;
            } else {
// 2010-11-22                $this->frm[$key] = get_magic_quotes_gpc() ? StripSlashes(trim($value)) : trim($value);
                $this->frm[$key] = trim($value);
            }
        }
        $this->gallery_dir = "00_gallery_" . preg_replace('/\d$/', '', $this->frm["cat_gen"]);
        $this->gallery_table = "00_gallery_" . $this->frm["cat_gen"];

        $tpl = new TemplatePower("templates/comment.tpl" );
        $tpl->prepare();

        if(empty($this->frm["gid"]) || empty($this->frm["cat_gen"])) {
            $this->comment_error();
        } else {

            $sql = "SELECT * FROM ".$this->gallery_table." WHERE id = ".$this->frm["gid"];
            $rs = $db->query($sql);
            $row = $db->fetch_array($rs, 1);
            foreach($row as $key => $value) {
                $this->gallery_data[$key] = trim($value);
            }

            $tpl->newBlock("B_COMMENT_AREA");
            $tpl->assignGlobal("DATA_COUNT" , $this->gallery_data["comment_count"]);
            $tpl->assignGlobal("DATA_GID" , $this->frm["gid"]);
            $tpl->assignGlobal("DATA_CAT_GEN" , $this->frm["cat_gen"]);

            switch($this->frm["mod"]) {
                case "showimg":
                    $this->comment_hits();
                    $this->show_original_pic();
                    break;

                case "show_liding":
                    $this->show_liding();
                    break;

                case "show_jishi":
                    $this->show_jishi();
                    break;

                case "comment":
                    $this->comment_frm();
                    break;

                case "re_error":
                    $this->response_error();
                    break;

                default:
                    $this->comment_data();
                    break;
            }
        }

        $tpl->printToScreen();
    }


    function comment_data() {
        global $db, $tpl;

        $sql = "SELECT * FROM gallery_comment WHERE cm_gid = '".$this->frm["gid"]."' AND cm_group = '".$this->frm["cat_gen"]."' ORDER BY cm_indate DESC";
        $rs = $db->query($sql);
        $num = $db->numRows($rs);
        if($num > 0) {
            $tpl->newBlock("B_COMMENT_DATA");
            while($row = $db->fetch_array($rs, 1)) {
                $tpl->newBlock("L_COMMENT_DATA");
                $tpl->assign("DATA_NAME" , $row["cm_name"]);
                $tpl->assign("DATA_EMAIL" , $row["cm_email"]);
                $tpl->assign("DATA_CONTEXT" , nl2br($row["cm_context"]));
                $tpl->assign("DATA_INDATE" , $row["cm_indate"]);
            }
        } else {
            $tpl->newBlock("B_COMMENT_NULL");
        }
    }


    function show_original_pic() {
        global $tpl;

        $o_img = "chu/" .$this->gallery_dir. "/01_original_pictures/". $this->gallery_data["imagename"];
        $tpl->newBlock("B_SHOW_ORIGINAL_PIC");
        $tpl->assign("SHOW_IMAGE" , $o_img);
    }


    function show_liding() {
        global $tpl;

        $o_img = "chu/" .$this->gallery_dir. "/01_original_pictures/". $this->gallery_data["imagename"];

        $difficult_dir = "chu/" . $this->gallery_dir . "/difficult_shiwen_pic";
        if(is_file($difficult_dir."/".$this->gallery_data["shiwen_01"])) {
            $shiwen_01 = "<img src=\"".$difficult_dir."/".$this->gallery_data["shiwen_01"]."\" border=\"0\" height=\"20\">";
        } else {
            $shiwen_01 = "<span id=\"trans_context\">".$this->gallery_data["shiwen_01"]."</span>";
        }
        if(is_file($difficult_dir."/".$this->gallery_data["shiwen_02"])) {
            $shiwen_02 = "<img src=\"".$difficult_dir."/".$this->gallery_data["shiwen_02"]."\" border=\"0\" height=\"20\">";
        } else {
            $shiwen_02 = "<span id=\"trans_context\">".$this->gallery_data["shiwen_02"]."</span>";
        }

        $tpl->newBlock("B_LIDING_DATA");
        $tpl->assign("DATA_IMAGE" , $o_img);
        $tpl->assign("DATA_DATA_NUMBER" , $this->gallery_data["data_number"]);
        $tpl->assign("DATA_SOURCE_01" , $this->gallery_data["source_01"]);
        $tpl->assign("DATA_SOURCE_02" , $this->gallery_data["source_02"]);
        $tpl->assign("DATA_SHIWEN_01" , $shiwen_01);
        $tpl->assign("DATA_SHIWEN_02" , $shiwen_02);
        $tpl->assign("DATA_SHIWEN_OTHER" , $this->gallery_data["shiwen_other"]);        
        $tpl->assign("DATA_SOURCE_01_DISPLAY" , ($this->gallery_data["source_01"]) ? "" : "style=\"display:none;\"");
        $tpl->assign("DATA_SOURCE_02_DISPLAY" , ($this->gallery_data["source_02"]) ? "" : "style=\"display:none;\"");
        $tpl->assign("DATA_SHIWEN_OTHER_DISPLAY" , ($this->gallery_data["shiwen_other"]) ? "" : "style=\"display:none;\"");
    }


    function show_jishi() {
        global $tpl;

        $o_img = "chu/" .$this->gallery_dir. "/01_original_pictures/". $this->gallery_data["imagename"];
        $tpl->newBlock("B_JISHI_DATA");
        if(empty($this->gallery_data["jishi"])) {
            $tpl->assign("DATA_JISHI_NULL" , "");
            $tpl->assign("DATA_JISHI_DISPLAY" , "style=\"display:none;\"");
        } else {
            $tpl->assign("DATA_JISHI_NULL" , "style=\"display:none;\"");
            $tpl->assign("DATA_JISHI_DISPLAY" , "");
        }
        $tpl->assign("DATA_JISHI" , $this->gallery_data["jishi"]);
    }


    function comment_frm() {
        global $db, $tpl;

        if($this->frm["act"] == "add") {
            $tpl->newBlock("B_COMMENT_FORM");
        } elseif($this->frm["act"] == "publish") {
            $tpl->newBlock("B_COMMENT_PUBLISH");
            $sql = "INSERT INTO gallery_comment (cm_gid, cm_group, cm_name, cm_email, cm_context, cm_indate) VALUES ('".$this->frm["gid"]."', '".$this->frm["cat_gen"]."', '".$this->frm["post_name"]."', '".$this->frm["post_mail"]."', '".$this->frm["post_context"]."', NOW())";
            if($db->query($sql)) {
                $tpl->assign("COMMENT_PUBLISH_SUCCESS" , "感謝您發表的評論!!");

                $db->query("UPDATE ".$this->gallery_table." SET comment_count = comment_count + 1 WHERE id = ".$this->frm["gid"]);
                $rs = $db->query("SELECT comment_count FROM ".$this->gallery_table." WHERE id = ".$this->frm["gid"]);
                $row = $db->fetch_array($rs, 1);
                $tpl->assignGlobal("DATA_COUNT" , $row["comment_count"]);
            } else {
                $tpl->assign("COMMENT_PUBLISH_FAIL" , "很遺憾，目前系統無法處理您的資料。<br><br>請稍後再發表您的評論!!");
            }
        }
    }


    function response_error() {
        global $db, $tpl;

        $sql = "UPDATE ".$this->gallery_table." SET response_error = 'Y' WHERE id = ".$this->frm["gid"];
        $rs = $db->query($sql);
        $tpl->newBlock("B_RESPONSE_ERROR");
    }


    function comment_hits() {
        global $db;

        $sql = "UPDATE ".$this->gallery_table." SET hits = hits + 1 WHERE id = ".$this->frm["gid"];
        $rs = $db->query($sql);
    }


    function comment_error() {
        global $tpl;

        $tpl->newBlock("B_COMMENT_ERROR");
    }
}
?>