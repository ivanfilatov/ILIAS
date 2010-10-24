<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once './Services/Export/classes/class.ilExportOptions.php';

/**
* XML parser for container structure
*
* @author Stefan Meyer <smeyer.ilias@gmx.de>
* @version $Id$
*
* @ingroup ServicesContainer
*/
class ilContainerXmlParser
{
	private $source = 0;
	private $mapping = null;
	private $xml = '';
	
	private $sxml = null;

	/**
	 * Constructor
	 */
	public function __construct(ilImportMapping $mapping,$xml = '')
	{
		$this->mapping = $mapping;
		$this->xml = $xml;
	}
	
	public function parse()
	{
		$this->sxml = simplexml_load_string($this->xml);
		
		foreach($this->sxml->Item as $item)
		{
			$this->initItem($item,$this->mapping->getTargetId());
		}
	}
	
	/**
	 * Init Item
	 * @param object $item
	 * @param object $a_parent_node
	 * @return 
	 */
	protected function initItem($item, $a_parent_node)
	{
		$title = (string) $item['Title'];
		$ref_id = (string) $item['RefId'];
		$obj_id = (string) $item['Id'];
		$type = (string) $item['Type'];

		$new_ref = $this->createObject($ref_id,$obj_id,$type,$title,$a_parent_node);	

		// Course item information		
		foreach($item->Timing as $timing)
		{
			$this->parseTiming($new_ref,$a_parent_node,$timing);
		}

		foreach($item->Item as $subitem)
		{
			$this->initItem($subitem, $new_ref);
		}
	}
	
	/**
	 * Parse timing info
	 * @param object $a_ref_id
	 * @param object $a_parent_id
	 * @param object $timing
	 * @return 
	 */
	protected function parseTiming($a_ref_id,$a_parent_id,$timing)
	{
		$type = (string) $timing['Type'];
		$visible = (string) $timing['Visible'];
		$changeable = (string) $timing['Changeable'];
		
		include_once './Modules/Course/classes/class.ilCourseItems.php';
		$crs_items = new ilCourseItems($a_parent_id,$a_parent_id);
		$crs_items->setTimingType($type);
		$crs_items->toggleVisible((bool) $visible);
		$crs_items->toggleChangeable((bool) $changeable);
		$crs_items->setItemId($a_ref_id);

		foreach($timing->children() as $sub)
		{
			switch((string) $sub->getName())
			{
				case 'Start':
					$dt = new ilDateTime((string) $sub,IL_CAL_DATETIME,ilTimeZone::UTC);
					$crs_items->setTimingStart($dt->get(IL_CAL_UNIX));
					break;
				
				case 'End':
					$dt = new ilDateTime((string) $sub,IL_CAL_DATETIME,ilTimeZone::UTC);
					$crs_items->setTimingEnd($dt->get(IL_CAL_UNIX));
					break;

				case 'SuggestionStart':
					$dt = new ilDateTime((string) $sub,IL_CAL_DATETIME,ilTimeZone::UTC);
					$crs_items->setSuggestionStart($dt->get(IL_CAL_UNIX));
					break;

				case 'SuggestionEnd':
					$dt = new ilDateTime((string) $sub,IL_CAL_DATETIME,ilTimeZone::UTC);
					$crs_items->setSuggestionEnd($dt->get(IL_CAL_UNIX));
					break;
				
				case 'EarliestStart':
					$dt = new ilDateTime((string) $sub,IL_CAL_DATETIME,ilTimeZone::UTC);
					$crs_items->setEarliestStart($dt->get(IL_CAL_UNIX));
					break;

				case 'LatestEnd':
					$dt = new ilDateTime((string) $sub,IL_CAL_DATETIME,ilTimeZone::UTC);
					$crs_items->setLatestEnd($dt->get(IL_CAL_UNIX));
					break;
			}
		}
		
		
		if($crs_items->getTimingStart())
		{
			$crs_items->update($a_ref_id);
		}
	}
	
	/**
	 * Create the objects
	 * @param object $ref_id
	 * @param object $obj_id
	 * @param object $type
	 * @param object $title
	 * @param object $parent_node
	 * @return 
	 */
	protected function createObject($ref_id,$obj_id,$type,$title,$parent_node)
	{
		global $objDefinition;

		$class_name = "ilObj".$objDefinition->getClassName($type);
		$location = $objDefinition->getLocation($type);

		include_once($location."/class.".$class_name.".php");
		$new = new $class_name();
		$new->setTitle($title);
		$new->create(true);
		$new->createReference();
		$new->putInTree($parent_node);
		$new->setPermissions($parent_node);
		
		$this->mapping->addMapping('Services/Container','objs', $obj_id, $new->getId());
		$this->mapping->addMapping('Services/Container','refs',$ref_id,$new->getRefId());
		
		if($type == 'svy')
		{
			$pool = $this->createSurveyPool($title,$parent_node);
			$this->mapping->addMapping('Services/Container', 'spl', $new->getId(), $pool->getRefId());
		}
		
		return $new->getRefId();
	}
	
	/**
	 * Create pool for survey
	 * @param object $title
	 * @param object $parent_node
	 * @return 
	 */
	protected function createSurveyPool($title,$parent_node)
	{
		global $objDefinition;

		$class_name = "ilObj".$objDefinition->getClassName('spl');
		$location = $objDefinition->getLocation('spl');

		include_once($location."/class.".$class_name.".php");
		$new = new $class_name();
		$new->setTitle($title);
		$new->create(true);
		$new->createReference();
		$new->putInTree($parent_node);
		$new->setPermissions($parent_node);
		
		return $new;
	}
}
?>