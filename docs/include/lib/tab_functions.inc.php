<?php
/************************************************************************/
/* ATutor																*/
/************************************************************************/
/* Copyright (c) 2002-2004 by Greg Gay, Joel Kronenberg & Heidi Hazelton*/
/* Adaptive Technology Resource Centre / University of Toronto			*/
/* http://atutor.ca														*/
/*																		*/
/* This program is free software. You can redistribute it and/or		*/
/* modify it under the terms of the GNU General Public License			*/
/* as published by the Free Software Foundation.						*/
/************************************************************************/


function get_tabs() {
	//these are the _AT(x) variable names and their include file
	/* tabs[tab_id] = array(tab_name, file_name,                accesskey) */
	$tabs[0] = array('content',       'edit_tab.inc.php',       'n');
	$tabs[1] = array('properties',    'properties_tab.inc.php', 'p');
	$tabs[2] = array('keywords',      'keywords_tab.inc.php',   'k');
	$tabs[3] = array('glossary_terms','glossary.inc.php',       'g');
	$tabs[4] = array('preview',       'preview.inc.php',        'r');
	
	return $tabs;
}

function output_tabs($current_tab, $changes) { 
	$tabs = get_tabs();
	echo '<table cellspacing="0" cellpadding="0" width="90%" border="0" summary="" align="center"><tr>';

	echo '<td>&nbsp;</td>';
	$num_tabs = count($tabs);
	for ($i=0; $i < $num_tabs; $i++) {
		if ($current_tab == $i) {
			echo '<td class="etabself" width="20%" nowrap="nowrap">';
			if ($changes[$i]) {
				echo '<img src="images/changes_bullet.gif" alt="Unsaved changes made" height="12" width="15" />';
			}
			echo _AT($tabs[$i][0]).'</td>';
		} else {
			echo '<td class="etab" width="20%">';
			if ($changes[$i]) {
				echo '<img src="images/changes_bullet.gif" alt="Unsaved changes made" height="12" width="15" />';
			}
			echo '<input type="submit" name="button_'.$i.'" value="'._AT($tabs[$i][0]).'" title="'._AT($tabs[$i][0]).' - alt '.$tabs[$i][2].'" class="buttontab" accesskey="'.$tabs[$i][2].'" onmouseover="this.style.cursor=\'hand\';" /></td>';
		}	
		echo '<td>&nbsp;</td>';
	}	
	echo '</tr><table>';
}

// save all changes to the DB
function save_changes( ) {
	global $contentManager, $db;

	$_POST['pid']	= intval($_POST['pid']);
	$_POST['cid']	= intval($_POST['cid']);

	$_POST['title'] = trim($_POST['title']);
	$_POST['text']	= trim($_POST['text']);
	$_POST['formatting'] = intval($_POST['formatting']);
	$_POST['keywords']	= trim($_POST['keywords']);
	$_POST['new_ordering']	= intval($_POST['new_ordering']);

	if (!($release_date = generate_release_date())) {
		$errors[] = AT_ERROR_BAD_DATE;
	}

	if ($_POST['title'] == '') {
		$errors[] = AT_ERROR_NO_TITLE;
	}
		
	if (!isset($errors)) {
		if ($_POST['cid']) {
			/* editing an existing page */
			$err = $contentManager->editContent($_POST['cid'], $_POST['title'], $_POST['text'], $_POST['keywords'], $_POST['new_ordering'], $_POST['related'], $_POST['formatting'], $_POST['move'], $release_date);

			unset($_POST['move']);
			unset($_POST['new_ordering']);
		} else {
			/* insert new */
			$cid = $contentManager->addContent($_SESSION['course_id'],
												  $_POST['pid'],
												  $_POST['ordering'],
												  $_POST['title'],
												  $_POST['text'],
												  $_POST['keywords'],
												  $_POST['related'],
												  $_POST['formatting'],
												  $release_date);
			$_POST['cid'] = $cid;
			$_REQUEST['cid'] = $cid;
		}
	}

	/* insert glossary terms */
	if (is_array($_POST['glossary_defs']) && ($num_terms = count($_POST['glossary_defs']))) {
		global $glossary;

		foreach($_POST['glossary_defs'] as $w => $d) {
			if ($glossary[$w] && ($glossary[$w] != $d)) {
				$sql = "UPDATE ".TABLE_PREFIX."glossary SET definition='$d' WHERE word='$w' AND course_id=$_SESSION[course_id]";
				$result = mysql_query($sql, $db);
				$glossary[$w] = $d;
			} else if (!$glossary[$w]) {
				$sql = "INSERT INTO ".TABLE_PREFIX."glossary VALUES (0, $_SESSION[course_id], '$w', '$d', 0)";
				$result = mysql_query($sql, $db);
				$glossary[$w] = $d;
			}
		}
	}

	if (!isset($errors)) {
		header('Location: '.$_SERVER['PHP_SELF'].'?cid='.$_POST['cid'].SEP.'f='.AT_FEEDBACK_CONTENT_UPDATED.SEP.'tab='.$_POST['current_tab']);
		exit;
	} else {
		return $errors;
	}
}

function generate_release_date($now = false) {
	if ($now) {
		$day  = date('d');
		$month= date('m');
		$year = date('Y');
		$hour = date('H');
		$min  = 0;
	} else {
		$day	= intval($_POST['day']);
		$month	= intval($_POST['month']);
		$year	= intval($_POST['year']);
		$hour	= intval($_POST['hour']);
		$min	= intval($_POST['min']);
	}

	if (!checkdate($month, $day, $year)) {
		return false;
	}

	if (strlen($month) == 1){
		$month = "0$month";
	}
	if (strlen($day) == 1){
		$day = "0$day";
	}
	if (strlen($hour) == 1){
		$hour = "0$hour";
	}
	if (strlen($min) == 1){
		$min = "0$min";
	}
	$release_date = "$year-$month-$day $hour:$min:00";
	
	return $release_date;
}

function check_for_changes($row) {
	global $contentManager, $cid, $glossary;

	$changes = array();

	if ($row && strcmp(stripslashes(trim($_POST['title'])), $row['title'])) {
		$changes[0] = true;
	} else if (!$row && $_POST['title']) {
		$changes[0] = true;
	}

	if ($row && strcmp(stripslashes(trim($_POST['text'])), $row['text'])) {
		$changes[0] = true;
	} else if (!$row && $_POST['text']) {
		$changes[0] = true;
	}

	/* formatting: */
	if ($row && strcmp(stripslashes(trim($_POST['formatting'])), $row['formatting'])) {
		$changes[0] = true;
	} else if (!$row && $_POST['formatting']) {
		$changes[0] = true;
	}

	/* release date: */
	if ($row && strcmp(generate_release_date(), $row['release_date'])) {
		$changes[1] = true;
	} else if (!$row && strcmp(generate_release_date(), generate_release_date(true))) {
		$changes[1] = true;
	}

	/* related content: */
	if (is_array($_POST['related']) && is_array($row_related = $contentManager->getRelatedContent($cid))) {
		$sum = array_sum(array_diff($_POST['related'], $row_related));
		if ($sum > 0) {
			$changes[1] = true;
		}
	}

	if (isset($_POST['move']) && ($_POST['move'] != -1) && ($_POST['move'] != $row['content_parent_id'])) {
		$changes[1] = true;
	}

	if ($row && isset($_POST['new_ordering']) && ($_POST['new_ordering'] != -1)) {
		$changes[1] = true;
		debug('e');
	} else if (!$row && isset($_POST['ordering']) && ($_POST['ordering'] != $_POST['old_ordering']) ) {
		$changes[1] = true;
	} 

	/* keywords */
	if ($row && strcmp(stripslashes(trim($_POST['keywords'])), $row['keywords'])) {
		$changes[2] = true;
	}  else if (!$row && $_POST['keywords']) {
		$changes[2] = true;
	}

	/* glossary */
	if (is_array($_POST['glossary_defs'])) {
		$diff = array_diff(array_keys($_POST['glossary_defs']), array_keys($glossary));
		if ($diff) {
			/* new terms added */
			$changes[3] = true;
		} else {
			/* check if added terms have changed */
			foreach ($_POST['glossary_defs'] as $w => $d) {
				if ($d != $glossary[$w]) {
					/* an existing term has been changed */
					$changes[3] = true;
					break;
				}
			}
		}

	}
	

	return $changes;

}

?>