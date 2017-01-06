<?php
/* Copyright (c) 1998-2010 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once("./Services/Table/classes/class.ilTable2GUI.php");
include_once("./Modules/Exercise/classes/class.ilExAssignment.php");
include_once("./Modules/Exercise/classes/class.ilExAssignmentMemberStatus.php");
include_once("./Modules/Exercise/classes/class.ilExAssignmentTeam.php");
include_once("./Modules/Exercise/classes/class.ilExSubmission.php");

/**
* Exercise member table
*
* @author Alex Killing <alex.killing@gmx.de>
* @version $Id$
*
* @ingroup ModulesExercise
*/
class ilExercisePersonalMemberTableGUI extends ilTable2GUI
{
	protected $exc;
	protected $ass;
	protected $exc_id;
	protected $ass_id;
	protected $sent_col;
	protected $selected = array();
	protected $teams = array();
	
	/**
	* Constructor
	*/
	function __construct($a_parent_obj, $a_parent_cmd, $a_exc, $a_ass)
	{
		global $ilCtrl, $lng, $ilUser;
		
		$this->exc = $a_exc;
		$this->exc_id = $this->exc->getId();
		$this->ass = $a_ass;
		$this->ass_id = $this->ass->getId();
		$this->setId("exc_mem_".$this->ass_id);
		
		
		include_once("./Modules/Exercise/classes/class.ilFSStorageExercise.php");
		$this->storage = new ilFSStorageExercise($this->exc_id, $this->ass_id);
		include_once("./Modules/Exercise/classes/class.ilExAssignment.php");
		
		parent::__construct($a_parent_obj, $a_parent_cmd);
		
		$this->setTitle($lng->txt("exc_assignment").": ".$this->ass->getTitle());
		$this->setTopCommands(false);
		//$this->setLimit(9999);
		
		$personal_access = $this->exc->getPersonalAccessNames();
		$data = $this->ass->getMemberListData();
		foreach($data as $user_id => $user_row)
		{
			if(isset($personal_access[$user_id]) && $personal_access[$user_id] == $ilUser->getLastname()." ".$ilUser->getFirstname())
			{continue;}
			else
			{unset($data[$user_id]);}
		}
		
		$this->setData($data);
		
		$this->addColumn("", "", "1", true);

		$this->selected = $this->getSelectedColumns();				
		if(in_array("image", $this->selected))
		{
			$this->addColumn($this->lng->txt("image"), "", "1");
		}
		$this->addColumn($this->lng->txt("name"), "name");
		if(in_array("login", $this->selected))
		{
			$this->addColumn($this->lng->txt("login"), "login");
		}
		
		$this->addColumn($this->lng->txt("exc_submission"), "submission");
		
		$this->setDefaultOrderField("name");
		$this->setDefaultOrderDirection("asc");
		
		$this->setEnableHeader(true);
		$this->setRowTemplate("tpl.exc_members_personal_row.html", "Modules/Exercise");
		//$this->disable("footer");
		$this->setEnableTitle(true);
		
		include_once "Services/Form/classes/class.ilPropertyFormGUI.php";
		include_once "Services/UIComponent/Overlay/classes/class.ilOverlayGUI.php";
		$this->overlay_tpl = new ilTemplate("tpl.exc_learner_comment_overlay.html", true, true, "Modules/Exercise");
	}
	
	function getSelectableColumns()
	{
		$columns = array();
		
		$columns["image"] = array(
				"txt" => $this->lng->txt("image"),
				"default" => true
			);
		
		$columns["login"] = array(
				"txt" => $this->lng->txt("login"),
				"default" => true
			);
		
		return $columns;
	}
	
	/**
	* Fill table row
	*/
	protected function fillRow($member)
	{
		global $lng, $ilCtrl;
		
		$ilCtrl->setParameter($this->parent_obj, "ass_id", $this->ass_id);
		$ilCtrl->setParameter($this->parent_obj, "member_id", $member["usr_id"]);

		include_once "./Services/Object/classes/class.ilObjectFactory.php";		
		$member_id = $member["usr_id"];

		if(!($mem_obj = ilObjectFactory::getInstanceByObjId($member_id,false)))
		{
			return;
		}
		
		$has_no_team_yet = (substr($member["team_id"], 0, 3) == "nty");
		$member_status = $this->ass->getMemberStatus($member_id);	
		
		$submission = new ilExSubmission($this->ass, $member_id);			
		$file_info = $submission->getDownloadedFilesInfoForTableGUIS($this->parent_obj, $this->parent_cmd);
			
		// name and login
		$this->tpl->setVariable("TXT_NAME",
			$member["name"]);
		
		if(in_array("login", $this->selected))
		{
			$this->tpl->setVariable("TXT_LOGIN",
				"[".$member["login"]."]");
		}
		
		if(in_array("image", $this->selected))
		{
			// image
			$this->tpl->setVariable("USR_IMAGE",
				$mem_obj->getPersonalPicturePath("xxsmall"));
			$this->tpl->setVariable("USR_ALT", $lng->txt("personal_picture"));
		}
		
		$this->tpl->setVariable("VAL_LAST_SUBMISSION", $file_info["last_submission"]["value"]);
		$this->tpl->setVariable("TXT_LAST_SUBMISSION", $file_info["last_submission"]["txt"]);

		$this->tpl->setVariable("TXT_SUBMITTED_FILES", $file_info["files"]["txt"]);
		$this->tpl->setVariable("VAL_SUBMITTED_FILES", $file_info["files"]["count"]);

		if($file_info["files"]["download_url"])
		{
			$this->tpl->setCurrentBlock("download_link");
			$this->tpl->setVariable("LINK_DOWNLOAD", $file_info["files"]["download_url"]);
			$this->tpl->setVariable("TXT_DOWNLOAD", $file_info["files"]["download_txt"]);		
			$this->tpl->parseCurrentBlock();
		}

		if($file_info["files"]["download_new_url"])
		{
			$this->tpl->setCurrentBlock("download_link");
			$this->tpl->setVariable("LINK_NEW_DOWNLOAD", $file_info["files"]["download_new_url"]);
			$this->tpl->setVariable("TXT_NEW_DOWNLOAD", $file_info["files"]["download_new_txt"]);		
			$this->tpl->parseCurrentBlock();
		}
		
		$ilCtrl->setParameter($this->parent_obj, "ass_id", $this->ass_id); // #17140
		$ilCtrl->setParameter($this->parent_obj, "member_id", "");
	}

	public function render()
	{
		global $ilCtrl;
		
		$url = $ilCtrl->getLinkTarget($this->getParentObject(), "saveCommentForLearners", "", true, false);		
		$this->overlay_tpl->setVariable("AJAX_URL", $url);
		
		return parent::render().
			$this->overlay_tpl->get();
	}
}
?>