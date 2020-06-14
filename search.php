<?php
/*************************************************************************
 * Filename     : search.php                                             *
 * Author       : A.L. KU                                                *
 * Description  : 查詢系統                                               *
 *************************************************************************/
require_once("include/config.inc");
$SEARCH = new search();

class search {
    function search() {
        global $CFG, $MEMBER, $db, $tpl;

        if($_REQUEST["term"]) {
            $sql = "SELECT cache_term FROM search_cache WHERE cache_id = " . $_REQUEST["term"];
            $rs = $db->query($sql);
            $row = $db->fetch_array($rs, 1);
            $this->frm = unserialize(base64_decode($row["cache_term"]));
            $this->frm["sc_id"] = $_REQUEST["term"];
        }
        foreach($_REQUEST as $key => $value) {
            if(is_array($value)) {
                $this->frm[$key] = $value;
            } else {
                $this->frm[$key] = trim($value);
                //$this->frm[$key] = get_magic_quotes_gpc() ? StripSlashes(trim($value)) : trim($value);
            }
        }

        $filename = "manage/page_control.txt";
        $handle = fopen($filename, "r");
        $contents = fread($handle, filesize($filename));
        $data = explode(", ", $contents);
        $page_total = explode("=", $data[0]);
        $per_row = explode("=", $data[1]);
        fclose($handle);
        $this->page_limit = $page_total[1];    //20;
        $this->page_td_column = $per_row[1];    //5;

        $this->db_alltables = $this->get_all_table();
        $this->gallery = "00_gallery_";
        $this->oneline_shiwen = "01_oneline_shiwen_";
        $this->xiaozhuan_dir = "chu/xiaozhuan_images/";

        $this->search_limit = ($MEMBER["validate"] == "Y") ? 0 : $CFG->search_limit;

        $tpl_file = $CFG->module_tpl[$CFG->module] ? "search_".$CFG->module_tpl[$CFG->module].".tpl" : "search.tpl";
        $tpl = new TemplatePower( "templates/".$tpl_file );
        $tpl->assignInclude( "HEADER", $CFG->tpl_header );
        $tpl->assignInclude( "FOOTER", $CFG->tpl_footer );
        $tpl->assignGlobal( "FRM_MODULE" , $CFG->module );
        $module_pic = (!empty($CFG->module_pic[$CFG->module])) ? $CFG->module_pic[$CFG->module] : $CFG->module_pic["search"];
        $tpl->assignGlobal( "MODULE_PIC" , $module_pic );
 //       $this->menu_bar["name"][] = "首頁";
 //       $this->menu_bar["url"][] = "page1.php";
        $tpl->prepare();

        switch ($CFG->module) {
            case "word":        // 單字文例查詢
                $this->menu_bar["name"][] = "四大豐碑查詢系統";
                $this->menu_bar["url"][] = "./search.php?module=word";
                $this->search_word();
                break;

            case "bs_full":     // 簡帛全文查詢
                $this->menu_bar["name"][] = "查詢系統";
                $this->menu_bar["url"][] = "search.php";
                $this->search_bsfull();
                break;

            case "catalog":     // 文獻目錄查詢
                $this->menu_bar["name"][] = "查詢系統";
                $this->menu_bar["url"][] = "search.php";
                $this->search_catalog();
                break;

            case "bs_index":    // 簡帛字表一覽
                $this->menu_bar["name"][] = "查詢系統";
                $this->menu_bar["url"][] = "search.php";
                $this->search_bsindex();
                break;

            default:
                $this->menu_bar["name"][] = "查詢系統";
                $this->menu_bar["url"][] = "";
                $this->truncate_table();
                break;
        }

        $tpl->assignGlobal( "MENU_LAYER" , $this->getMenuLayer() );
        $tpl->assignGlobal( "MEMBER_AREA" , $CFG->member_pic );
        $tpl->printToScreen();
    }


    /*****************************************************************************
     * Function     : 單字文例查詢                                               *
     * 查詢選項列表 : 分類名稱: chu_cat.cat_parent, cat_name='' 歸屬 '其他' 類   *
     *                分類內容: chu_cat.cat_name (cat_name='' 以 cat_group代)    *
     *****************************************************************************/
    function search_word() {
        global $CFG, $db, $tpl;

        $this->menu_bar["name"][] = "單字文例查詢";
        $this->menu_bar["url"][] = "search.php?module=word";

        //** 單字文例查詢 -查詢結果, 圖版, 圖版及相關文例 顯示 **/
        if($this->frm["st"] == "rs" || $this->frm["st"] == "plate" || $this->frm["st"] == "ill") {
            $this->menu_bar["name"][] = "查詢【".$this->frm["sword"]."】概覽";
            $this->menu_bar["url"][] = "search.php?module=word&st=rs&term=".$this->frm["sc_id"];

            $bar_name = ($this->frm["sc_name"] == "all") ? "全部" : $this->frm["sc_name"];

            //-- 單字文例查詢::查詢結果::顯示圖版 -------------------------------------------------------------//
            if($this->frm["st"] == "plate") {
                $this->menu_bar["name"][] = "顯示 <font color=\"#CC0000\">".$bar_name."</font> 查詢結果圖版";
                $this->menu_bar["url"][] = "";
                $this->show_plates();
            }
            //-- 單字文例查詢::查詢結果::顯示圖版及相關文例 ---------------------------------------------------//
              elseif($this->frm["st"] == "ill") {
                $this->menu_bar["name"][] = "顯示 [".$bar_name."] 全部圖版及相關文例";
                $this->menu_bar["url"][] = "";

                $tpl->newBlock("B_SEARCH_WORD_ILLUSTRATE");
                $tpl->assign("DATA_SCNAME" , $this->frm["sc_name"]);
                $tpl->assign("DATA_CACHE_ID" , $this->frm["sc_id"]);
                $tpl->assign("DATA_SEARCH_WORD" , $this->frm["sword"]);

                $sql = "SELECT * FROM search_cache WHERE cache_id = ".$this->frm["sc_id"];
                $rs = $db->query($sql);
                $row = $db->fetch_array($rs, 1);
                $row["cache_data"] = unserialize(base64_decode($row["cache_data"]));
                unset($cache_data);
                if($this->frm["sc_name"] == "all") {
                    foreach($row["cache_data"] as $key => $value) {
                        if(is_array($value)) {
                            foreach($value as $k => $v) { $cache_data[] = $v; }
                        }
                    }
                } else {
                    $cache_data = $row["cache_data"][$this->frm["sc_name"]];
                }

                $total_num = sizeof($cache_data);
                $offset = $this->frm["offset"] ? $this->frm["offset"] : 0;
                $offse_min = $offset * $this->page_limit;
                $offse_max = $offse_min + $this->page_limit;
                $offse_max = ($offse_max > $total_num) ? $total_num : $offse_max;
                $tpl->assignGlobal("DATA_TOTAL_WORDS" , $total_num);

                // 楚簡帛字形
                $i = 0; $td_column = $this->page_td_column;
                $td_width = floor((100 * 6) / $td_column);   //無條件捨去 (Def:100*6)
                for($n=$offse_min; $n<$offse_max; $n++) {
                    $i++;

                    $gallery_dir = $this->gallery . preg_replace('/\d$/', '', $cache_data[$n]["cat_gen"]);
                    $img = "chu/".$gallery_dir."/02_thumbs/".$cache_data[$n]["imagename"];
                    $comment_title = $cache_data[$n]["shiwen_01"]."&nbsp;【".$cache_data[$n]["data_number"]."】";
                    $comment_liding = "comment.php?mod=show_liding&gid=".$cache_data[$n]["id"]."&cat_gen=".$cache_data[$n]["cat_gen"];

                    if($i % $td_column == 1) {
                        $data_plate = "";
                        $tpl->newBlock("LIST_ILLUSTRATE_WORD");
                        $data_plate .= "<tr>";
                    }

                    $data_plate .= "<td width=\"$td_width\" align=\"center\" nowrap><a href=\"$comment_liding\" title=\"$comment_title\" rel=\"gb_page_center[]\"><img border=\"0\" src=\"$img\"></a><br>".$cache_data[$n]["data_number"]."</td>";

                    if($i % $td_column == 0) {
                        $data_plate .= "</tr>\n";
                        $tpl->assign("DATA_ILLUSTRATE_PLATE" , $data_plate);
                    }

                    $word_shiwen = $cache_data[$n]["shiwen_01"];
                    $word_xiaozhuan = $cache_data[$n]["en_name"];
////r問題-page2013.jpg                    $gen = preg_replace('/\d$/', '', $cache_data[$n]["cat_gen"]);
                    $gen = $cache_data[$n]["cat_gen"];
                    $shiwen_title[$gen][$cache_data[$n]["title"]] = $cache_data[$n]["title"];
                    $shiwen_gen[$gen] = $gen;
                    $shiwen_folder[$gen] = $gallery_dir;
                }

                if($i % $td_column <> 0) {
                    $data_plate .= "</tr>\n";
                    $tpl->assign("DATA_ILLUSTRATE_PLATE" , $data_plate);
                }

                // 釋文
                $swrod = $this->frm["sword"];
                foreach($shiwen_gen as $gk => $gv) {
                    $difficult_dir = "chu/" . $shiwen_folder[$gk] . "/difficult_shiwen_pic";
                    $tb_name = $this->oneline_shiwen . $gk;

                    $sql = "SELECT * FROM ".$tb_name." WHERE context LIKE '%".$this->frm["sword"]."%' ORDER BY catid";   //原來是  ORDER BY title ，lo 改為 order by catid
                    $rs = $db->query($sql);
                    $num = $db->numRows($rs);
                    while($row = $db->fetch_array($rs, 1)) {
                        if($shiwen_title[$gk][$row["title"]] == $row["title"]) {
                            $context_exp = explode("。", $row["context"]);
                            foreach($context_exp as $key => $value) {
                                $swrod_gif = $swrod.".gif";
                                if(is_file($difficult_dir."/".$swrod_gif)) {
                                    $pat = "/$swrod_gif/";
                                    $rep = "<img src=\"".$difficult_dir."/".$swrod_gif."\" border=\"0\" height=\"20\" style=\"vertical-align:text-bottom;\" >";
                                } else {
                                    $pat = "/$swrod/";
                                    $rep = $swrod;
                                }
                                if(preg_match($pat, $value)) {
                                    if(preg_match("/.gif/", $value)) {
//                                        preg_match_all("/([a-z]+\-[0-9a-z]+.gif)/si", $value, $tt);
                                        preg_match_all("/([0-9a-z]+.gif)|([0-9a-z]+\-[0-9a-z]+\-[0-9a-z]+.gif)|([0-9a-z]+\-[0-9a-z]+.gif)/si", $value, $tt);
                                        foreach($tt[0] as $k => $v) {
                                            $img = $difficult_dir."/".$v;
                                            $value = str_replace($v, "<img src=\"$img\" width=\"16\" height=\"16\">", $value);
                                        }
                                    }
                                    $tpl->newBlock("LIST_WORD_ILLUSTRATE");
                                    $tpl->assign("DATA_ILLUSTRATE_TITLE" , "【".$row["title"]."】");
                                    $tpl->assign("DATA_ILLUSTRATE_CONTENT" , preg_replace($pat, $rep, $value) ."...<br>\n");
                                }
                            }
                        }
                    }
                }

                // 隸定, 
                $word_xiaozhuan_image = $this->xiaozhuan_dir . $word_xiaozhuan . ".gif";
                if(is_file($word_xiaozhuan_image)) {
                    $word_xiaozhuan_image = "<img src=\"".$word_xiaozhuan_image."\" border=\"0\" alt=\"".$word_xiaozhuan."\">";
                } else {
                    $word_xiaozhuan_image = "&nbsp;";
                }
                $tpl->assignGlobal("WORD_SHIWEN" , $word_shiwen);
                $tpl->assignGlobal("WORD_XIAOZHUAN" , $word_xiaozhuan_image);

                // 分頁
                $pagebar = $this->pagebar($this->page_limit, $total_num, $offset);
                $tpl->assignGlobal("SHOW_PAGEBAR", ($total_num > $this->page_limit) ? $pagebar : "");

            }
            //-- 單字文例查詢::查詢結果 ( $this->frm["st"] == "rs" ) ------------------------------------------//
              else {
                unset($search_data); unset($cat_name); unset($cat_num);

                foreach($this->frm[inq_cname] as $key => $value) {
                    $cat = explode("-", $value);
                    $cat_name[$cat[1]] = $cat[1];
                    $table[$cat[1]] = $cat[0];
                }
                $sql = "SELECT * FROM chu_cat WHERE catid IN (".implode(",", $cat_name).") ORDER BY cat_sort ASC, cat_group_en";
                $rs = $db->query($sql);
                while($row = $db->fetch_array($rs, 1)) {
                    $cat_name[$row["catid"]] = $row["cat_name"];
                    foreach($table as $key => $value) {
                        if($value == $row["cat_group_en"]) {
                            $cat_en[$value][$row["catid"]] = $row["cat_name"];
                            $cat_cn[$value] = ($row["cat_parent"] == "其他") ? $row["cat_group"] : $row["cat_parent"];

                        }
                    }
                }

                if(is_array($this->frm[inq_cgroup])) { $tmp_cgroup = explode(",", implode(",", $this->frm[inq_cgroup])); }
                $search_total = 0;
                foreach($cat_en as $key => $value) {
                    $tb_name = $this->gallery . $key;
                    if(in_array($tb_name, $this->db_alltables)) {   // 有存在的table才查詢
                        if(!$this->frm[inq_cgroup] || (is_array($this->frm[inq_cgroup]) && !in_array($key, $tmp_cgroup))) {  // 單選查詢
                            $str_cname = "'".implode("', '", $value)."'";
//# echo "<hr><font color='#003399'>".$cat_cn[$key]." => </font>";
                            $sql = "SELECT id, shiwen_01, shuowen_complete, radical, imagename, thumbname, "
                                  ."       data_number, title, cat_group, cat_name, comment_count "
                                  ."  FROM $tb_name "
                                  ." WHERE (shiwen_01 = '".$this->frm["sword"]."' OR shiwen_01 LIKE '".$this->frm["sword"]."-%') "
                                  ."   AND cat_name IN (".$str_cname.") ";
                            $rs = $db->query($sql);
                            $num = $db->numRows($rs);
//2009-10-09                            $search_total += $num;
//# echo "$tb_name => $num .......... <br><font color='#003399'>$sql</font><br>"; #藍
                            if($cat_cn[$key]) {
                                $tmp = $cat_cn[$key];
                                $cat_num[$cat_cn[$key]] += $num;
//# echo $cat_num[$cat_cn[$key]];
                            }
                            if($num > 0) {
                                while($row = $db->fetch_array($rs, 1)) {
                                    $search_total++;
                                    $sub_total[$cat_cn[$key]]["stotal"]++;
                                    $row["cat_gen"] = $key;
                                    // 未登入限制查詢筆數
                                    if($this->search_limit > 0) {
//2009-10-09                                        if(sizeof($search_data[$cat_cn[$key]]) < $this->search_limit) {
                                        if($search_total <= $this->search_limit) {
                                            $search_data[$cat_cn[$key]][] = $row;
                                        }
                                    } else {
                                        $search_data[$cat_cn[$key]][] = $row;
                                    }
                                }
                            } else {
                                if(!isset($sub_total[$cat_cn[$key]]["stotal"])) {
                                    $sub_total[$cat_cn[$key]]["stotal"] = 0;
                                }
                            }
                        } else {    // 全選查詢
                            if(is_array($this->frm[inq_cgroup])) {
//# echo "<hr><font color='#006600'>".$cat_cn[$key]." => </font>";
                                $sql = "SELECT id, shiwen_01, shuowen_complete, radical, imagename, thumbname, "
                                      ."       data_number, title, cat_group, cat_name, comment_count "
                                      ."  FROM $tb_name "
                                      ." WHERE (shiwen_01 = '".$this->frm["sword"]."' OR shiwen_01 LIKE '".$this->frm["sword"]."-%') ";
                                $rs = $db->query($sql);
                                $num = $db->numRows($rs);
//2009-10-09                                $search_total += $num;
//# echo "$tb_name => $num .......... <br><font color='#006600'>$sql</font><br>"; #綠
                                if($cat_cn[$key]) {
                                    $tmp = $cat_cn[$key];
                                    $cat_num[$cat_cn[$key]] += $num;
//# echo $cat_num[$cat_cn[$key]],",";
                                }
                                if($num > 0) {
                                    while($row = $db->fetch_array($rs, 1)) {
                                        $search_total++;
                                        $sub_total[$cat_cn[$key]]["stotal"]++;
                                        $row["cat_gen"] = $key;
                                        // 未登入限制查詢筆數
                                        if($this->search_limit > 0) {
//2009-10-09                                            if(sizeof($search_data[$cat_cn[$key]]) < $this->search_limit) {
                                            if($search_total <= $this->search_limit) {
                                                $search_data[$cat_cn[$key]][] = $row;
                                            }
                                        } else {
                                            $search_data[$cat_cn[$key]][] = $row;
                                        }
                                    }
                                } else {
                                    if(!isset($sub_total[$cat_cn[$key]]["stotal"])) {
                                        $sub_total[$cat_cn[$key]]["stotal"] = 0;
                                    }
                                }
                            }
                        }
                    } else {
                        $search_data[$cat_cn[$key]] = "";
                        if(!isset($sub_total[$cat_cn[$key]]["stotal"])) {
                            $sub_total[$cat_cn[$key]]["stotal"] = 0;
                        }
                    }
                }

                if($search_total > 0) {
                    if(!$this->frm["sc_id"]) {
                        $sql_ins = "INSERT INTO search_cache (cache_time, cache_word, cache_term, cache_data) VALUES "
                                  ." ('".date("YmdHis")."', '".$this->frm["sword"]."', '".base64_encode(serialize($this->frm))."', '".base64_encode(serialize($search_data))."')";
                        $db->query($sql_ins);
                        $this->frm["sc_id"] = $db->get_insert_id();
                    }

                    // 未登入限制查詢筆數
/*2009-10-09
                    if($this->search_limit > 0) {
                        $search_total = 0;
                        foreach($search_data as $key => $value) {
                            $search_total += is_array($value) ? sizeof($value) : 0;
                        }
                    }
*/
                    $tpl->newBlock("B_SEARCH_WORD_RESULT");
                    $tpl->assign("DATA_SEARCH_WORD" , $this->frm["sword"]);
                    $tpl->assign("DATA_SEARCH_TOTAL" , $search_total);
                    $tpl->assign("DATA_CACHE_ID" , $this->frm["sc_id"]);
                    $tpl->assign("DATA_SEARCH_TOTAL_LIMIT" , ($this->search_limit > 0 && $search_total > $this->search_limit) ? 1 : 0);

                    foreach($sub_total as $key => $value) {
                        $tpl->newBlock("B_SEARCH_WORD_DATA");
                        $tpl->assign("DATA_GROUP" , $key);
//                        $tpl->assign("DATA_SUB_TOTAL" , is_array($value) ? sizeof($value) : 0);
//                        $tpl->assign("DATA_DISPLAN_PLATE" , is_array($value) ? "" : "style='display:none;'");
                        $tpl->assign("DATA_SUB_TOTAL" , $sub_total[$key]["stotal"] ? $sub_total[$key]["stotal"] : 0);
///2009-11-17                        $tpl->assign("DATA_DISPLAN_PLATE" , ($this->search_limit > 0 || $sub_total[$key]["stotal"] == 0) ? "style='display:none;'" : "");
                        $tpl->assign("DATA_DISPLAN_PLATE" , ($sub_total[$key]["stotal"] == 0) ? "style='display:none;'" : "");  // 有查到就顯示 顯示圖版 的 "欄位/字"
                        if($this->search_limit > 0) {   // 非核可會員，不提供 顯示圖版 的 "連結"
                            $tpl->assign("DATA_DISPLAN_PLATE_LINK0", "style='display:none;'");
                            $tpl->assign("DATA_DISPLAN_PLATE_LINK1", "");
                        } else {
                            $tpl->assign("DATA_DISPLAN_PLATE_LINK0", "");
                            $tpl->assign("DATA_DISPLAN_PLATE_LINK1", "style='display:none;'");
                        }
                        $tpl->assign("DATA_SEARCH_TOTAL_LIMIT" , ($this->search_limit > 0 && $search_total > $this->search_limit) ? 1 : 0);
                    }
                } else {
                    $tpl->newBlock("B_SEARCH_WORD_RESULT");
                    $tpl->assign("DATA_SEARCH_WORD" , $this->frm["sword"]);
                    $tpl->assign("DATA_SEARCH_TOTAL" , $search_total);
                    $tpl->assign("DATA_DISPLAN_PLATE" , "style='display:none;'");
                }
            }

        }
        //** 單字文例查詢 -查詢 Form **/
          else {
            $tpl->newBlock("B_SEARCH_WORD_FROM");
            $td_column = 4;
            $td_width = floor(660 / $td_column);   //無條件捨去

            $sql = "SELECT * FROM chu_cat ORDER BY cat_sort ASC, cat_group_en";
            $rs = $db->query($sql);
            while($row = $db->fetch_array($rs, 1)) {
///                $data = !empty($row["cat_name"]) ? $row["cat_name"] : $row["cat_group"];
///                $group[$row["cat_parent"]][$row["catid"]] = $data;
                $group[$row["cat_parent"]][$row["catid"]] = $row["cat_name"];
                $group_en[$row["catid"]] = $row["cat_group_en"];
                $group_index[$row["cat_parent"]] = ($row["cat_parent"] == "其他") ? "other" : preg_replace('/\d/', '', $row["cat_group_en"]);
                $group_index_set[$row["cat_parent"]][$row["cat_group_en"]] = $row["cat_group_en"];
            }

            foreach($group as $gname => $garray) {
                $p_set = is_array($group_index_set[$gname]) ? implode(",", $group_index_set[$gname]) : $group_index_set[$gname];
                $tpl->newBlock("B_PARENT_LIST");
                $tpl->assign("DATA_PARENT_ID" , $group_index[$gname]);
                $tpl->assign("DATA_PARENT_NAME" , $gname);
                $tpl->assign("DATA_PARENT_VALUE" , $p_set);

                $td = 0;
                foreach($garray as $key => $value) {
                    $td++;
                    if($td == 1) $tpl->newBlock("B_GROUP_LIST");

                    $tpl->newBlock("B_GROUP_NAME");
                    $val = $group_en[$key]."-".$key;
                    if($gname == "其他") {
                        $gid = "other".$key;
                        $tmp_ck = "other";
                    } else {
                        $gid = preg_replace('/\d/', '', $group_en[$key]).$key;
                        $tmp_ck = $group_index[$gname];
                    }

                    $name = "<td width=\"$td_width\"><input type=\"checkbox\" name=\"inq_cname[]\" value=\"$val\" id=\"tb_$gid\" onClick=\"CheckParentSingle('$tmp_ck');\"> $value</td>";
                    $tpl->assign("DATA_GROUP_NAME" , $name);

                    if($td == $td_column) $td = 0;
                }
                if($td > 0 && $td < $td_column) {
                    $tmp = $td_column - $td;
                    $colspan = ($tmp > 1) ? "colspan=\"$tmp\"" : "";
                    $width = $td_width * $tmp;
                    $tpl->assign("ADD_TD" , "<td $colspan width=\"$width\">&nbsp;</td>");
                }
            }
        }
    }


    /*****************************************************************************
     * Function     : 簡帛全文查詢                                               *
     * 查詢選項列表 : 分類名稱: chu_cat.cat_group_alias                          *
     *                分類內容: chu_cat.cat_name                                 *
     *****************************************************************************/
    function search_bsfull() {
        global $CFG, $MEMBER, $db, $tpl;

        $this->menu_bar["name"][] = "簡帛全文查詢";
        $this->menu_bar["url"][] = "search.php?module=bs_full";

        //** 簡帛全文查詢 -查詢結果顯示 **/
        if($this->frm["st"] == "view") {
            $sql_cat = "SELECT cat_group_alias, cat_name FROM chu_cat WHERE catid = ". $this->frm["gid"];
            $rs_cat = $db->query($sql_cat);
            $row_cat = $db->fetch_array($rs_cat, 1);
            $tpl->newBlock("B_SEARCH_BSFULL_DETAIL");
            $tpl->assign("DATA_GROUP_ALIAS" , $row_cat["cat_group_alias"]."：".$row_cat["cat_name"]);

            $this->menu_bar["name"][] = $row_cat["cat_group_alias"]."：".$row_cat["cat_name"];
            $this->menu_bar["url"][] = "";

            $gallery_dir = $this->gallery . preg_replace('/\d$/', '', $this->frm["gen"]);
            $difficult_dir = "chu/" . $gallery_dir . "/difficult_shiwen_pic";
            $tb_name = "01_oneline_shiwen_".$this->frm["gen"];
            if(in_array($tb_name, $this->db_alltables)) {   // 有存在的table才查詢
                $sql = "SELECT s.*, c.cat_group_alias FROM $tb_name AS s, chu_cat AS c "
                      ." WHERE s.catid = ". $this->frm["gid"] ." AND c.catid = s.catid ORDER BY s.title";
                if($this->search_limit > 0) {
                    $sql .= " LIMIT ".$this->search_limit;
                    if(!$MEMBER) {
                        $tpl->newBlock("B_SEARCH_BSFULL_LIMIT");
                    }
                }
                $rs = $db->query($sql);
                while($row = $db->fetch_array($rs, 1)) {
/*
                    if(is_file($difficult_dir."/".$cache_data[$n]["shiwen_01"])) {
                        $shiwen_01 = "<a href=\"$comment_liding\" title=\"$comment_title\" rel=\"gb_page_center[]\"><img src=\"".$difficult_dir."/".$cache_data[$n]["shiwen_01"]."\" border=\"0\" height=\"20\"></a>";
                    } else {
                        $shiwen_01 = "<a href=\"$comment_liding\" title=\"$comment_title\" rel=\"gb_page_center[]\"><span id=\"trans_context\">".$cache_data[$n]["shiwen_01"]."</span></a>";
                    }
*/
                    $pattern = "/([\\w\\-]+\\.gif)/i";
                    $replacement = "<img src=\"$difficult_dir/\$1\" border=\"0\" height=\"15\">";
                    $row["context"] = preg_replace($pattern, $replacement, $row["context"]);

                    $tpl->newBlock("B_SEARCH_BSFULL_LIST");
                    $tpl->assign("DATA_TITLE" , $row["title"]);
                    $tpl->assign("DATA_CONTEXT" , $row["context"]);
                }
            }
        }
        //** 簡帛全文查詢 -簡帛類別列表 **/
          else {
            $tpl->newBlock("B_SEARCH_BSFULL_FROM");
            $td_column = $this->page_td_column;
            $td_width = floor(780 / $td_column);   //無條件捨去

            $sql = "SELECT * FROM chu_cat ORDER BY cat_sort ASC, cat_group_en";
            $rs = $db->query($sql);
            while($row = $db->fetch_array($rs, 1)) {
                $group[$row["cat_group_alias"]][$row["catid"]] = $row["cat_name"];
                $group_en[$row["catid"]] = $row["cat_group_en"];
            }

            foreach($group as $alias => $garray) {
                $tpl->newBlock("B_ALIAS_LIST");
                $tpl->assign("DATA_ALIAS" , $alias);

                $td = 0;
                foreach($garray as $key => $value) {
                    $td++;
                    if($td == 1) $tpl->newBlock("B_GROUP_LIST");
                    $tpl->newBlock("B_GROUP_NAME");

                    $url = "?module=".$this->frm["module"]."&st=view&gid=".$key."&gen=".$group_en[$key];
                    $name = "<td width=\"$td_width\" height=\"30\"> <a href=\"$url\">$value</a></td>";
                    $tpl->assign("DATA_GROUP_NAME" , $name);

                    if($td == $td_column) $td = 0;
                }
                if($td > 0 && $td < $td_column) {
                    $tmp = $td_column - $td;
                    $colspan = ($tmp > 1) ? "colspan=\"$tmp\"" : "";
                    $width = $td_width * $tmp;
                    $tpl->assign("ADD_TD" , "<td $colspan width=\"$width\">&nbsp;</td>");
                }
            }
        }
    }


    /*****************************************************************************
     * Function     : 簡帛字表一覽                                               *
     *****************************************************************************/
    function search_bsindex() {
        global $CFG, $db, $tpl;

        $this->menu_bar["name"][] = "簡帛字表一覽";
        $this->menu_bar["url"][] = "search.php?module=bs_index";

        $index_type_array = Array("wordnumber"=>"筆畫", "shuowen_complete"=>"說文卷次", "radical"=>"字典部首", "wordroot"=>"簡帛字根");

        //** 簡帛字表一覽 -顯示 **/
        if($this->frm["st"] == "rs_index") {

            if($this->frm["cgroup64"]) {
                $this->frm["inq_cgroup"] = unserialize(base64_decode($this->frm["cgroup64"]));
            } else {
                $this->frm["inq_cgroup"] = is_string($this->frm["inq_cgroup"]) ? explode("-", $this->frm["inq_cgroup"]) : $this->frm["inq_cgroup"];
            }

            $sql_cat = "SELECT * FROM chu_cat WHERE cat_group_en IN ('".implode("','", $this->frm["inq_cgroup"])."') GROUP BY cat_group_en";
            $rs_cat = $db->query($sql_cat);
            $bar_index = $title_index = "";
            $index_num = 0;
            while($row_cat = $db->fetch_array($rs_cat, 1)) {
                $index_num++;
                if($index_num <= 3) {
                    $bar_index .= "【".$row_cat["cat_group"]."】";
                }
                $title_index .= "【".$row_cat["cat_group"]."】";
//                $title_index .= ($index_num % 6 == 0) ? "<br>" : "";
            }
            $bar_index .= (sizeof($this->frm["inq_cgroup"]) > 1) ? "等" : "";
            $bar_index .= $index_type_array[$this->frm["sf"]]."索引";

            $this->menu_bar["name"][] = $bar_index;
            $this->menu_bar["url"][] = $this->frm["sword"] ? "search.php?module=bs_index&st=rs_index&sf=".$this->frm["sf"]."&cgroup64=".base64_encode(serialize($this->frm["inq_cgroup"])) : "";

            if(!empty($this->frm["sword"])) {   // 圖版顯示--------------------------------------------

                /* 楚典網站問題20091002-回覆(需要再調整之處20091013).doc */
                if(eregi("(.*)-(.*).gif", $this->frm["sword"])) {
                    $new_sword = eregi_replace("(.*)-(.*).gif", "\\1", $this->frm["sword"]);
                } else {
                    $new_sword = $this->frm["sword"];
                }

                $this->menu_bar["name"][] = "查詢單字【".$this->frm["sword"]."】的結果";
                $this->menu_bar["url"][] = "";

                if(!$this->frm["sc_id"]) {
                    unset($search_data);
                    $search_total = 0;
                    foreach($this->frm["inq_cgroup"] as $tmp => $key) {
                        $tb_name = $this->gallery . $key;
                        if(in_array($tb_name, $this->db_alltables)) {   // 有存在的table才查詢

                            $sql = "SELECT id, shiwen_01, shuowen_complete, radical, imagename, thumbname, "
                                  ."       data_number, title, cat_group, cat_name, comment_count "
                                  ."  FROM $tb_name "
                                  ." WHERE (shiwen_01 = '".$this->frm["sword"]."' OR shiwen_01 LIKE '".$new_sword."-%') ";
                            $rs = $db->query($sql);
                            $num = $db->numRows($rs);
                            $search_total += $num;

                            while($row = $db->fetch_array($rs, 1)) {
                                $row["cat_gen"] = $key;
                                // 未登入限制查詢筆數
                                if($this->search_limit > 0) {
                                    if(sizeof($search_data[$cat_cn[$key]]) < $this->search_limit) {
                                        $search_data[$cat_cn[$key]][] = $row;
                                    }
                                } else {
                                    $search_data[$cat_cn[$key]][] = $row;
                                }
                            }
                        }
                    }

                    if($search_total > 0) {
                        $sql_ins = "INSERT INTO search_cache (cache_time, cache_word, cache_term, cache_data) VALUES "
                                  ." ('".date("YmdHis")."', '".$this->frm["sword"]."', '".base64_encode(serialize($this->frm))."', '".base64_encode(serialize($search_data))."')";
                        $db->query($sql_ins);
                        $this->frm["sc_id"] = $db->get_insert_id();
                    }
                }

                $this->show_plates();

            } else {    // 索引顯示--------------------------------------------------------------------
                unset($tmp_shiwen_01);
                $search_total = 0;
                foreach($this->frm["inq_cgroup"] as $key => $value) {
                    $tb_name = $this->gallery . $value;
                    if(in_array($tb_name, $this->db_alltables)) {   // 有存在的table才查詢
//                        $sql = "SELECT ".$this->frm["sf"].", shiwen_01 FROM $tb_name GROUP BY shuowen_complete ORDER BY CAST(".$this->frm["sf"]." AS UNSIGNED)";
///2009-11-17                        $sql = "SELECT ".$this->frm["sf"].", shiwen_01 FROM $tb_name GROUP BY shiwen_01 ORDER BY CAST(".$this->frm["sf"]." AS UNSIGNED)";
                        //$sql = "SELECT g.".$this->frm["sf"].", g.shiwen_01, CASE s.id WHEN s.id THEN s.id ELSE 999999 END AS id FROM $tb_name AS g LEFT JOIN shuowen_9353_daxue_02 AS s ON g.shiwen_01=s.title GROUP BY g.shiwen_01 ORDER BY CAST(g.".$this->frm["sf"]." AS UNSIGNED), id ASC, g.shiwen_01 ASC";
                        if($this->frm["sf"] == "wordnumber") { //筆畫
                            $sql = "SELECT g.shiwen_01, "
                                  ."       CASE g.wordnumber WHEN 0 THEN 999999 ELSE g.wordnumber END AS wordnumber, "
                                  ."       CASE s.id WHEN s.id THEN s.id ELSE 999999 END AS id "
                                  ."  FROM $tb_name AS g "
                                  ."  LEFT JOIN shuowen_9353_daxue_02 AS s ON g.shiwen_01=s.title "
                                  ." GROUP BY g.shiwen_01 ORDER BY wordnumber, id, g.shiwen_01";
                        } elseif($this->frm["sf"] == "shuowen_complete") { //說文卷次**
                            $sql = "SELECT g.".$this->frm["sf"].", g.shiwen_01, "
                                  ."       CASE s.id WHEN s.id THEN s.id ELSE 999999 END AS id "
                                  ."  FROM $tb_name AS g "
                                  ."  LEFT JOIN shuowen_9353_daxue_02 AS s ON g.shiwen_01=s.title "
                                  ." GROUP BY g.shiwen_01 ORDER BY CAST(g.".$this->frm["sf"]." AS UNSIGNED) DESC, id, g.shiwen_01";

                        } elseif($this->frm["sf"] == "radical") { //字典部首
                            $sql = "SELECT g.".$this->frm["sf"].", g.shiwen_01,"
                                  ."       CASE r.kxzd_serial WHEN r.kxzd_serial THEN r.kxzd_serial ELSE '99-99' END AS kxzd_serial, "
                                  ."       CASE s.id WHEN s.id THEN s.id ELSE 999999 END AS id "
                                  ."  FROM $tb_name AS g "
                                  ."  LEFT JOIN kangxizidian_radical AS r ON r.kxzd_radical = g.radical "
                                  ."  LEFT JOIN shuowen_9353_daxue_02 AS s ON g.shiwen_01=s.title "
                                  ." GROUP BY g.shiwen_01 ORDER BY CAST(g.".$this->frm["sf"]." AS UNSIGNED), kxzd_serial, id ASC , g.shiwen_01 ASC ";
                        } elseif($this->frm["sf"] == "wordroot") {  //簡帛字根
/*
                            $sql = "SELECT g.shiwen_01, "
                                  ."       CASE g.wordroot WHEN '' THEN '999999' ELSE g.wordroot END AS wordroot, "
                                  ."       CASE s.id WHEN s.id THEN s.id ELSE 999999 END AS id "
                                  ."  FROM $tb_name AS g "
                                  ."  LEFT JOIN shuowen_9353_daxue_02 AS s ON g.shiwen_01=s.title "
                                  ." GROUP BY g.shiwen_01 ORDER BY wordroot, id, g.shiwen_01";
*/

                            $sql = "SELECT * FROM chu_root_list ORDER BY churoot_no";
                        }

                        if($this->search_limit > 0) { $sql .= " LIMIT ". ($this->search_limit + 10); }
//else  { $sql .= " LIMIT 50"; }
                        $rs = $db->query($sql);
                        $gallery_dir = $this->gallery . preg_replace('/\d$/', '', $value);
                        $difficult_dir = "chu/" . $gallery_dir . "/difficult_shiwen_pic";
                        while($row = $db->fetch_array($rs, 1)) {
                            if($this->frm["sf"] == "wordroot") {
                                $sql_gallery = "SELECT shiwen_01 FROM $tb_name WHERE wordroot LIKE '%".$row["churoot_radical"]."%'";
                                $rs_gallery = $db->query($sql_gallery);
                                $num_gallery = $db->numRows($rs_gallery);

                                if($num_gallery > 0) {
                                    while($row_gallery = $db->fetch_array($rs_gallery, 1)) {
                                        /*
                                        // 楚字典網站問題20091002.doc
                                        // 「umg-011010.gif」、「umg-011123.gif」，「-」之前的值如果相同，就表示它是同一個字。
                                        // 「=」或「_」之前的值如果相同，就表示它是同一個字。
                                        */
                                        $s_flag = 0;
//*2009-12-21 -簡帛字根不過濾
//*2009-12-21                                        $tmp = $row_gallery["shiwen_01"];
//*2009-12-21                                        $tmp = eregi_replace("(.*)-(.*).gif", "\\1", $tmp);
//*2009-12-21                                        $tmp = eregi_replace("(.*)[_,=]$", "\\1", $tmp);
//*2009-12-21                                        if(is_array($tmp_shiwen_01)) {
//*2009-12-21                                            if(!in_array($tmp, $tmp_shiwen_01)) {
//*2009-12-21                                                $s_flag = 1;
//*2009-12-21                                                $tmp_shiwen_01[] = $tmp;
//*2009-12-21                                            }
//*2009-12-21                                        } else {
//*2009-12-21                                            $s_flag = 1;
//*2009-12-21                                            $tmp_shiwen_01[] = $tmp;
//*2009-12-21                                        }

                                        // 2009-12-21 出現的資料逐筆計算至20筆
                                        $search_total++;
                                        if($this->search_limit > 0) {
                                            $s_flag = ($search_total > $this->search_limit) ? 0 : 1;
                                        } else {
                                            $s_flag = 1;
                                        }

                                        if($s_flag == 1) {
                                            if(is_file($difficult_dir."/".$row_gallery["shiwen_01"])) {
                                                $shiwen_01 = "<a onClick=\"checkShowFrm('".$row_gallery["shiwen_01"]."');\" style=\"cursor:pointer;color:#0077A3;\"><img src=\"".$difficult_dir."/".$row_gallery["shiwen_01"]."\" border=\"0\" height=\"20\"></a>";
                                            } else {
                                                $shiwen_01 = "<a onClick=\"checkShowFrm('".$row_gallery["shiwen_01"]."');\" style=\"cursor:pointer;color:#0077A3;\"><span id=\"trans_context\">".$row_gallery["shiwen_01"]."</span></a>";
                                            }

                                            $dkey = $row["churoot_radical"];
                                            $data[$dkey][$shiwen_01] = $shiwen_01;
                                        }
                                    }
                                }
                            } else {

                                /*
                                // 楚字典網站問題20091002.doc
                                // 「umg-011010.gif」、「umg-011123.gif」，「-」之前的值如果相同，就表示它是同一個字。
                                // 「=」或「_」之前的值如果相同，就表示它是同一個字。
                                */
                                $s_flag = 0;
                                $tmp = $row["shiwen_01"];
                                $tmp = eregi_replace("(.*)-(.*).gif", "\\1", $tmp);
                                $tmp = eregi_replace("(.*)[_,=]$", "\\1", $tmp);
                                if(is_array($tmp_shiwen_01)) {
                                    if(!in_array($tmp, $tmp_shiwen_01)) {
                                        $s_flag = 1;
                                        $tmp_shiwen_01[] = $tmp;
                                    }
                                } else {
                                    $s_flag = 1;
                                    $tmp_shiwen_01[] = $tmp;
                                }

                                // 2009-12-21 出現的資料逐筆計算至20筆
                                if($s_flag == 1) {
                                    $search_total++;
                                    if($this->search_limit > 0) {
                                        if($search_total > $this->search_limit) { $s_flag = 0; }
                                    }
                                }

                                if($s_flag == 1) {
                                    if(is_file($difficult_dir."/".$row["shiwen_01"])) {
                                        $shiwen_01 = "<a onClick=\"checkShowFrm('".$row["shiwen_01"]."');\" style=\"cursor:pointer;color:#0077A3;\"><img src=\"".$difficult_dir."/".$row["shiwen_01"]."\" border=\"0\" height=\"20\"></a>";
                                    } else {
                                        $shiwen_01 = "<a onClick=\"checkShowFrm('".$row["shiwen_01"]."');\" style=\"cursor:pointer;color:#0077A3;\"><span id=\"trans_context\">".$row["shiwen_01"]."</span></a>";
                                    }

                                    // 楚字典問題20091009.doc
                                    if($this->frm["sf"] == "shuowen_complete") {    //說文卷次
                                        $patterns = array('/●/', '/a\d+/', '/b\d+/', '/a/', '/b/', '/(\((其他字|合文|殘字)\))|\([^)]+\)/');
                                        $replacement = array('', 'a', 'b', '上', '下', '\1');
                                        $split_key = preg_split("/，/", $row[$this->frm["sf"]]);
                                        foreach($split_key as $sk => $sv) {
                                            if(!empty($sv)) {
                                                $dkey = preg_replace($patterns, $replacement, $sv);
                                                $data[$dkey][$shiwen_01] = $shiwen_01;
                                            }
                                        }
//                                        $data[$dkey][$shiwen_01] = $shiwen_01;
                                    } else {
                                        $dkey = $row[$this->frm["sf"]];
                                        $data[$dkey][$shiwen_01] = $shiwen_01;
                                    }
                                }





                            }
                        }





                    }
                }

                if(sizeof($data) > 0) {
                    $tpl->newBlock("B_SEARCH_BSINDEX_DETAIL");
                    $tpl->assign("DATA_GROUP_ENAME" , implode("-", $this->frm["inq_cgroup"]));
                    $tpl->assign("DATA_SEARCH_INDEX" , $this->frm["sf"]);
                    $tpl->assign("DATA_SEARCH_TITLE" , $title_index);
                    $tpl->assign("DATA_SEARCH_TYPE" , $index_type_array[$this->frm["sf"]]);

                    $td_column = 20;
                    $td_width = floor(760 / $td_column);   //無條件捨去
                    if($this->frm["sf"] == "shuowen_complete" || $this->frm["sf"] == "wordroot")  ksort($data);
                    foreach($data as $key => $value) {
                        $tpl->newBlock("B_SEARCH_BSINDEX_LIST");
                        $tmp_type = "";
                        if($this->frm["sf"] == "wordnumber") {
                            if($key == 999999) {
                                $key = "存疑字";
                            } else {
                                $tmp_type = " 畫";
                            }
                        } elseif($this->frm["sf"] == "wordroot") {
                            if($key == 999999) {
                                $key = "&nbsp;";
                            }
                        } elseif($this->frm["sf"] == "radical") {
                            $tmp_type = " 部";
                        }

                        $tpl->assign("DATA_INDEX_KEY" , $key);
                        $tpl->assign("DATA_INDEX_TYPE" , $tmp_type);

                        if(is_array($value)) {

                            $td = 0;
                            foreach($value as $k => $v) {
                                $td++;

                                if($td == 1) $tpl->newBlock("B_DATA_DETAIL_LIST");

                                $tpl->newBlock("DATA_SHIWEN_LIST");
                                $shiwen = "<td width=\"$td_width\" height=\"30\">".$v."</td>";
                                $tpl->assign("DATA_SHIWEN" , $shiwen);

                                if($td == $td_column) $td = 0;
                            }
                            if($td > 0 && $td < $td_column) {
                                $tmp = $td_column - $td;
                                $colspan = ($tmp > 1) ? "colspan=\"$tmp\"" : "";
                                $width = $td_width * $tmp;
                                $tpl->assign("ADD_TD" , "<td $colspan width=\"$width\">&nbsp;</td>");
                            }
                        }
                    }
                }

                if($this->search_limit > 0) {
                    if(!$MEMBER) {
                        $tpl->newBlock("B_SEARCH_BSFULL_LIMIT");
                    }
                }
            }
        }
        //** 簡帛字表一覽 -字表類別列表 **/
          else {
            $tpl->newBlock("B_SEARCH_BSINDEX_FROM");
            $td_column = $this->page_td_column;
            $td_width = floor(800 / $td_column);   //無條件捨去

            $sql = "SELECT * FROM chu_cat GROUP BY cat_group ORDER BY catid";
            $rs = $db->query($sql);

            $td = 0;
            while($row = $db->fetch_array($rs, 1)) {
                $td++;

                if($td == 1) $tpl->newBlock("B_GROUP_LIST");

                $tpl->newBlock("B_GROUP_NAME");
                $name = "<td width=\"$td_width\" height=\"30\"><input type=\"checkbox\" name=\"inq_cgroup[]\" value=\"".$row["cat_group_en"]."\"> ".$row["cat_group"]."</td>";
                $tpl->assign("DATA_GROUP_NAME" , $name);

                if($td == $td_column) $td = 0;
            }
            if($td > 0 && $td < $td_column) {
                $tmp = $td_column - $td;
                $colspan = ($tmp > 1) ? "colspan=\"$tmp\"" : "";
                $width = $td_width * $tmp;
                $tpl->assign("ADD_TD" , "<td $colspan width=\"$width\">&nbsp;</td>");
            }
        }
    }


    function show_plates() {
        global $db, $tpl;

        $tpl->newBlock("B_SEARCH_WORD_PLATE");
        $tpl->assign("DATA_SCNAME" , $this->frm["sc_name"]);
        $tpl->assign("DATA_CACHE_ID" , $this->frm["sc_id"]);
        $tpl->assign("DATA_SEARCH_WORD" , $this->frm["sword"]);

        $sql = "SELECT * FROM search_cache WHERE cache_id = ".$this->frm["sc_id"];
        $rs = $db->query($sql);
        $row = $db->fetch_array($rs, 1);
        $row["cache_data"] = unserialize(base64_decode($row["cache_data"]));
        unset($cache_data);
        if($this->frm["sc_name"] == "all") {
            foreach($row["cache_data"] as $key => $value) {
                if(is_array($value)) {
                    foreach($value as $k => $v) { $cache_data[] = $v; }
                }
            }
        } else {
            $cache_data = $row["cache_data"][$this->frm["sc_name"]];
        }
/*
foreach($cache_data as $key => $value) {
foreach($value as $k => $v) {
echo "$k=>$v<br>";
}
echo "<hr>";
}
id=>1151
shiwen_01=>王
shuowen_complete=>1上6
radical=>王
imagename=>02-05-02.gif
thumbname=>02-05-02.gif
data_number=>02-05-02
cat_group=>上博楚竹書1
cat_name=>緇衣
comment_count=>0
en_name=>wang2
cat_gen=>shangbo1
*/
        $total_num = sizeof($cache_data);
        $offset = $this->frm["offset"] ? $this->frm["offset"] : 0;
        $offse_min = $offset * $this->page_limit;
        $offse_max = $offse_min + $this->page_limit;
        $offse_max = ($offse_max > $total_num) ? $total_num : $offse_max;
        $tpl->assignGlobal("DATA_TOTAL_WORDS" , $total_num);

        $i = 0; $td_column = $this->page_td_column;
        $td_width = floor((170 * 5) / $td_column);   //無條件捨去 (Def:170*5)
        for($n=$offse_min; $n<$offse_max; $n++) {
            $i++;

            $gallery_dir = $this->gallery . preg_replace('/\d$/', '', $cache_data[$n]["cat_gen"]);
            $img = "chu/".$gallery_dir."/02_thumbs/".$cache_data[$n]["imagename"];
            $comment_img = "comment.php?mod=show_liding&gid=".$cache_data[$n]["id"]."&cat_gen=".$cache_data[$n]["cat_gen"];
            $comment_url = "comment.php?mod=data&gid=".$cache_data[$n]["id"]."&cat_gen=".$cache_data[$n]["cat_gen"];
            $comment_title = $cache_data[$n]["shiwen_01"]."&nbsp;【".$cache_data[$n]["data_number"]."】";
            $comment_liding = "comment.php?mod=show_liding&gid=".$cache_data[$n]["id"]."&cat_gen=".$cache_data[$n]["cat_gen"];

            if($i % $td_column == 1) {
                $data = "";
                $tpl->newBlock("SEARCH_WORD_PLATE_SHOW");
                $data .= "<tr>\n";
            }

            $plate_name = trim($cache_data[$n]["cat_name"]) ? $cache_data[$n]["cat_name"] : $cache_data[$n]["cat_group"];
            $difficult_dir = "chu/" . $gallery_dir . "/difficult_shiwen_pic";
            if(is_file($difficult_dir."/".$cache_data[$n]["shiwen_01"])) {
                $shiwen_01 = "<a href=\"$comment_liding\" title=\"$comment_title\" rel=\"gb_page_center[]\"><img src=\"".$difficult_dir."/".$cache_data[$n]["shiwen_01"]."\" border=\"0\" height=\"20\"></a>";
            } else {
                $shiwen_01 = "<a href=\"$comment_liding\" title=\"$comment_title\" rel=\"gb_page_center[]\"><span id=\"trans_context\">".$cache_data[$n]["shiwen_01"]."</span></a>";
            }

            $data .= "
              <td width=\"".$td_width."\" valign=\"top\">
                <table border=\"0\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" style=\"font-size:12px;\">
                  <tr>
                    <td align=\"center\" height=\"100\"><a href=\"$comment_img\" title=\"$comment_title\" rel=\"gb_page_center[]\"><img src=\"$img\" border=\"0\"></a></td>
                  </tr>
                  <tr>
                    <td height=110 style=\"padding:10px;\">
                      竹書：".$plate_name."<br>
                      簡號：".$cache_data[$n]["data_number"]."<br>
                      隸定：".$shiwen_01."<br>
                      部首：".$cache_data[$n]["radical"]."<br>
                      說文卷次：".$cache_data[$n]["shuowen_complete"]."<br>
                      線上評論：（ <a href=\"$comment_url\"  title=\"Comments :: $comment_title\" rel=\"gb_page_center[]\">".$cache_data[$n]["comment_count"]."</a> ）篇
                    </td>
                  </tr>
                </table>
              </td>\n";

            if($i % $td_column == 0) {
                $data .= "</tr>\n";
                $tpl->assign("DATA_WORD_PLATE" , $data);
            }
        }

        if($i % $td_column <> 0) {
            $data .= "</tr>\n";
            $tpl->assign("DATA_WORD_PLATE" , $data);
        }

        // 分頁
        $pagebar = $this->pagebar($this->page_limit, $total_num, $offset);
        $tpl->assignGlobal("SHOW_PAGEBAR", ($total_num > $this->page_limit) ? $pagebar : "");
    }


    function getMenuLayer() {
        $last = sizeof($this->menu_bar["name"]) -1;
        foreach($this->menu_bar["url"] as $key => $value) {
            $layer[$key] = (!empty($value) && $key <> $last) ? "<a href=\"".$this->menu_bar["url"][$key]."\">".$this->menu_bar["name"][$key]."</a>" : "<font color=\"#996600\">".$this->menu_bar["name"][$key]."</font>";
        }
        return implode("&nbsp; &gt; &nbsp;", $layer);
    }


    function truncate_table() {
        global $db;

        $sql = "SHOW TABLE STATUS LIKE 'search_cache'";
        $rs = $db->query($sql);
        while($row = $db->fetch_array($rs, 1)) {
            $total = $row["Data_length"] + $row["Index_length"];
        }
        $check = $total / 1024876;  // 1024*1024

        if($check > 3) {
            $db->query("TRUNCATE TABLE `search_cache`");
        }
    }


    function get_all_table() {
        global $db, $db_name;

//        $sql = "SHOW TABLES FROM $db_name";
        $sql = "SHOW TABLES";
        $rs = $db->query($sql);
        while($row = $db->fetch_array($rs, 1)) {
            foreach($row as $key => $value) {
                $all_table[] = $value;
            }
        }
        return $all_table;
    }


    // 分頁-每頁筆數，總筆數，連結名稱，目前頁數
    function pagebar($limit, $total, $offset) {
      $pages = intval($total / $limit);   ##頁數

      if ($total % $limit) {    ##有餘數就加一頁
        $pages = $pages + 1;    ##頁數
      }

      $ppages = intval($pages / 10);    ##一次只顯示十頁

      if ($pages % 10) {
        $ppages = $ppages+ 1;   ##共幾個十頁
      }

      $boffset = intval((($offset+10) / 10));   ##目前在第幾個十頁

      if ($total > 0) $links = "|"; else  $links = "";

      for ($i = ($boffset-1)*10 ; $i < ($boffset*10) and $i < $pages ; $i++) {    ##控制一次顯示十面
        $j = ($i+1);
        if ($offset == $i) {
          $links = $links . "&nbsp;&nbsp;"."<b><font color=\"#000000\">".$j."</font></b>";
        } else {
          $links = $links . "&nbsp;&nbsp;<a href = \"javascript:goPage($i);\">".$j."</a>";
        }
        $links .= "&nbsp;&nbsp;|";
      }

      if ($boffset > 1) {   ##前十頁
        $i = ($boffset - 1)*10 -1;
        $links = " <a href = \"javascript:goPage($i);\"><font face=\"Courier New\"><<</font>上10頁</a>&nbsp;&nbsp;". $links;
      }

      if ($boffset < $ppages) {   ##後十頁
        $i = $boffset * 10;
        $links = $links. "&nbsp;&nbsp;<a href = \"javascript:goPage($i);\">下10頁<font face=\"Courier New\">>></font></a>";
      }

      //$links = "<p style=\"margin-top:25px;color:#0033CC;\"><font style=\"font-size:9pt;font-family:Arial;\">$links</font></p>";
      return $links;
    }
}
?>