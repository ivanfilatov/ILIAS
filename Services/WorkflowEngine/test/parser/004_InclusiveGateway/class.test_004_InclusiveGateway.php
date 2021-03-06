<?php
/* Copyright (c) 1998-2014 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * @author Maximilian Becker <mbecker@databay.de>
 * @version $Id$
 *
 * @ingroup Services/WorkflowEngine
 */
class test_004_InclusiveGateway extends PHPUnit_Framework_TestCase
{
	#region Helper
	public $base_path = './Services/WorkflowEngine/test/parser/';
	public $suite_path = '004_InclusiveGateway/';

	public function getTestInputFilename($test_name)
	{
		return $this->base_path . $this->suite_path  . $test_name . '.bpmn2';
	}

	public function getTestOutputFilename($test_name)
	{
		return $this->base_path . $this->suite_path  . $test_name . '_output.php';
	}

	public function getTestGoldsampleFilename($test_name)
	{
		return $this->base_path . $this->suite_path  . $test_name . '_goldsample.php';
	}

	public function setUp()
	{
		chdir( dirname( __FILE__ ) );
		chdir( '../../../../../' );

		require_once './Services/WorkflowEngine/classes/parser/class.ilBPMN2Parser.php';
	}

	public function test_WorkflowWithSimpleInclusiveGatewayShouldOutputAccordingly()
	{
		$test_name = 'InclusiveGateway_Simple';
		$xml = file_get_contents($this->getTestInputFilename($test_name));
		$parser = new ilBPMN2Parser();
		$parse_result = $parser->parseBPMN2XML($xml);

		file_put_contents($this->getTestOutputFilename($test_name), $parse_result);
		$return = exec('php -l ' . $this->getTestOutputFilename($test_name));

		$this->assertTrue(substr($return,0,25) == 'No syntax errors detected', 'Lint of output code failed.');

		$goldsample = file_get_contents($this->getTestGoldsampleFilename($test_name));
		$this->assertEquals($goldsample, $parse_result, 'Output does not match goldsample.');

		require_once $this->getTestOutputFilename($test_name);
		$process = new $test_name;
		$this->assertFalse($process->isActive());

		$process->startWorkflow();
		$all_triggered = true;
		foreach($process->getNodes() as $node)
		{
			/** @var ilNode $node*/
			foreach($node->getDetectors() as $detector)
			{
				/** @var ilSimpleDetector $detector */
				if(!$detector->getActivated())
				{
					$all_triggered = false;
				}
			}
			foreach($node->getEmitters() as $emitter)
			{
				/** @var ilActivationEmitter $emitter */
				if(!$emitter->getActivated())
				{
					$all_triggered = false;
				}
			}
		}
		$this->assertTrue($all_triggered);

		unlink($this->getTestOutputFilename($test_name));
	}

}