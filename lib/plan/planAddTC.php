<?php

////////////////////////////////////////////////////////////////////////////////
// @version $Id: planAddTC.php,v 1.5 2005/10/03 07:20:14 franciscom Exp $
// File:     planAddTC.php
// Author:   Chad Rosen
// Purpose:  This page manages the importation of test cases into testlink.
//
// 20051001 - fm - refactoring
// 20050926 - fm - removed name from category and component insert
// 20050807 - fm - removed deprecated: $_SESSION['project']
//
////////////////////////////////////////////////////////////////////////////////
require('../../config.inc.php');
require("../functions/common.php");
require("plan.inc.php");
require_once("../../lib/functions/lang_api.php");
testlinkInitPage();

// 20050807 - fm
$idPlan =  $_SESSION['testPlanId'];

//Defining the keyword variable which is received from the left frame
$keyword = isset($_REQUEST['key']) ? strings_stripSlashes($_REQUEST['key']) : 'NONE';
$compID=$_GET['data'];
$catID=$_GET['data'];

$smarty = new TLSmarty;
$smarty->assign('testPlanName', $_SESSION['testPlanName']);

if($keyword != 'NONE')
{
	$smarty->assign('key', $keyword);
}


if(isset($_POST['addTC'])) //If the user submits the import form
{
	$i = 0;
	//This loop goes through all of the $_POST variables and maps them to values
	foreach ($_POST as $key)
  {
		$newArray[$i] = $key;
		$i++;
	}

	for($i = 1; $i < count($newArray); $i++) //Loop through all of the $_POST / $newArray variables
	{
		//If we find a testcase that has been checked then grab the value after it and increment by two
		if($newArray[$i] == 'on') 
		{
			$tcid = $newArray[$i + 1]; //If we find the test case that has been 
			                           //passed through it's value is always the next item in the list
			                           
			           
			$sql_mgt = " SELECT MGTCAT.id AS mgtcatid, MGTCOMP.id AS mgtcompid, " .
			           "        MGTCAT.CATorder " .
			           " FROM mgtcomponent MGTCOMP, mgtcategory MGTCAT, mgttestcase MGTTC " .
			           " WHERE MGTCAT.compid = MGTCOMP.id " .
			           " AND   MGTTC.catid = MGTCAT.id " .
			           " AND   MGTTC.id=" . $tcid;                            
			$result = @do_mysql_query($sql_mgt);
			$mgtinfo = mysql_fetch_assoc($result); 
			
			//This next long set of code looks through the kenny side of the DB and checks to see if each of the
			//Components,categories, or TCs already exist. 
			//If one of the top level items exists the function skips down to the next level and checks there. 
			//Finally if no TCs exist it does nothing.
			
			//Determining if the component already exists for the project being added to
			//
			// 20050807 - fm - $idPlan
			$sqlCOMID = " SELECT mgtcompid,id AS compid FROM component " .
			            " WHERE mgtcompid=" . $mgtinfo['mgtcompid'] . 
			            " AND projid=" .  $idPlan;
			$resultCOMID = @do_mysql_query($sqlCOMID); 
			
			
			if(mysql_num_rows($resultCOMID) > 0) 
			{
				$rowResultCOMID = mysql_fetch_assoc($resultCOMID); 
				$sqlCATID = " SELECT mgtcatid,id AS catid from category " .
				            " WHERE mgtcatid=" . $mgtinfo['mgtcatid'] . 
				            " AND compid=" . $rowResultCOMID['compid'];
				$resultCATID = @do_mysql_query($sqlCATID);
				
				if(mysql_num_rows($resultCATID) > 0) 
				{
					$rowResultCATID = mysql_fetch_assoc($resultCATID);
					$sqlTCID = " SELECT mgttcid FROM testcase " .
					           " WHERE mgttcid=" . $tcid . 
					           " AND catid=" . $rowResultCATID['catid'];
					$resultTCID = @do_mysql_query($sqlTCID); 

					if( mysql_num_rows($resultTCID) == 0) 
					{
					  // the test case doesn't already exist
				    create_tc_from_mgttc($tcid, $rowResultCATID['catid']);
					}
				}
				else
				{
					//Add the category to the project
					$sqlAddCAT = " INSERT INTO category (mgtcatid,compid,CATorder) " .
					             " VALUES (" . $mgtinfo['mgtcatid']      . "," . 
					                           $rowResultCOMID['compid'] . "," . 
					                           $mgtinfo['CATorder'] . ")";
					$resultAddCAT = do_mysql_query($sqlAddCAT); 
					$addCATID =  mysql_insert_id(); 

					//Add the test case to the project
		      create_tc_from_mgttc($tcid, $addCATID);
				}
			}
			else
			{ 
				//Add the component to the project					
				//
				// 20050807 -fm - $idPlan
				$sqlAddCOM = "INSERT INTO component (mgtcompid,projid) " . 
				             " VALUES (" . $mgtinfo['mgtcompid'] . "," . $idPlan . ")";
				$resultAddCOM = do_mysql_query($sqlAddCOM); 
				$addCOMID =  mysql_insert_id();	 
			
				//Add the category to the project					
				$sqlAddCAT = " INSERT INTO category(mgtcatid,compid,CATorder) " .
				             " VALUES (" . $mgtinfo['mgtcatid'] ."," . 
				                           $addCOMID . "," . 
				                           $mgtinfo['CATorder'] . ")";
				$resultAddCAT = do_mysql_query($sqlAddCAT);
				$addCATID =  mysql_insert_id(); 

				//Add the test case to the project
		    create_tc_from_mgttc($tcid, $addCATID);
			}
			$i = $i + 1; //increment the counter plus an extra one to skip the testcase number
		}
	}

	// TODO: real verification
	$smarty->assign('action', 'updated');
	$smarty->assign('item', 'TestPlan');
	$smarty->assign('sqlResult', 'ok');
}


//If the user has selected a component
if($_GET['edit'] == 'component')
{
	$sqlCOM = "SELECT id, name FROM mgtcomponent WHERE id=" . $compID . " ORDER BY name";
	$resultCOM = do_mysql_query($sqlCOM);

	while($rowCOM = mysql_fetch_array($resultCOM))
	{
		$sqlCAT = "SELECT id, name FROM mgtcategory WHERE compid=" . $rowCOM[0] . " ORDER BY CATorder,id";
		$resultCAT = do_mysql_query($sqlCAT);
		$arrData = dispCategories($idPlan, $keyword, $resultCAT);
		
		$smarty->assign('nameCOM', $rowCOM[1]);
		$smarty->assign('arrData', $arrData);
		$smarty->display('planAddTC.tpl');
	}

}//If the user has selected a category
else if($_GET['edit'] == 'category')
{
  	//Query to grab all of the category information based on what was passed in by the user
  	$sqlCAT = "SELECT id, name FROM mgtcategory WHERE id=" . $catID . " ORDER BY CATorder,id";
  	$resultCAT = do_mysql_query($sqlCAT);
  	$arrData = dispCategories($idPlan, $keyword, $resultCAT);

	  $smarty->assign('arrData', $arrData);
	  $smarty->display('planAddTC.tpl');
}
else
{
	redirect($_SESSION['basehref'] .$g_rpath['help'].'/planAddTC.html');
}


// 20051001 - fm
function create_tc_from_mgttc($mgt_tcid, $catID)
{
	// Get management the test case info to be added
	$sql = " SELECT title,summary,steps,exresult,version,keywords,TCorder " .
	       " FROM mgttestcase WHERE id=" . $mgt_tcid;
	$result = do_mysql_query($sql);
	$myrow = mysql_fetch_assoc($result);

	$sql = " INSERT INTO testcase(title,mgttcid,catid,summary,steps,exresult,version,keywords,TCorder) ".
	       " VALUES ('" . mysql_escape_string($myrow['title']) . "'," . 
	                      mysql_escape_string($mgt_tcid) . "," . 
	                      mysql_escape_string($catID)  . "," .
	                      "'" . mysql_escape_string($myrow['summary']) . "'," .
	                      "'" . mysql_escape_string($myrow['steps']) . "'," . 
	                      "'" . mysql_escape_string($myrow['exresult']) . "'," . 
	                            mysql_escape_string($myrow['version']) . "," . 
	                      "'" . mysql_escape_string($myrow['keywords']) . "'," .
	                            mysql_escape_string($myrow['TCorder']) . ")";
	$result = do_mysql_query($sql); 
}


?>