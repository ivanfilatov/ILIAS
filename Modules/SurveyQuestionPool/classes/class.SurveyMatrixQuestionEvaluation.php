<?php
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once "Modules/SurveyQuestionPool/classes/class.SurveyQuestionEvaluation.php";

/**
 * Survey matrix evaluation 
 *
 * @author	Jörg Lützenkirchen <luetzenkirchen@leifos.com>
 * @ingroup ModulesSurveyQuestionPool
 */
class SurveyMatrixQuestionEvaluation extends SurveyQuestionEvaluation
{
	//
	// RESULTS	
	//
	
	public function getResults()
	{
		$results = array();

		$answers = $this->getAnswerData();

		// parse rows
		for ($r = 0; $r < $this->question->getRowCount(); $r++)
		{
			$row_results = new ilSurveyEvaluationResults($this->question);

			$this->parseResults(
				$row_results,
				(array)$answers[$r],
				$this->question->getColumns()
			);

			$results[] = array(
				$this->question->getRow($r)->title,
				$row_results
			);
		}

		return $results;
	}

    // CHANGES IN CORE *start*
    //
    // RESULTS
    //

    protected function parseResults(ilSurveyEvaluationResults $a_results, array $a_answers, SurveyCategories $a_categories = null)
    {
        parent::parseResults($a_results, $a_answers, $a_categories);

        if ($a_results->getMean() == null) {
            $total = $sum = 0;
            $answer_data = $this->getAnswerData();
            foreach ($answer_data as $answer_datum) {
                foreach ($answer_datum as $answers) {
                    foreach ($answers as $answer) {
                        $cat_a = $a_categories->getCategory($answer['value']);
                        if ($cat_a->neutral == 0) {
                            $sum += $cat_a->scale;
                            $total += 1;
                        }
                    }
                }
            }
            if ($total > 0) {
                $a_results->setMean(round($sum / $total, 2));
            } else {
                $a_results->setMean(0);
            }
        }
    }
    // CHANGES IN CORE *end*

	
	//
	// DETAILS
	//
	
	
	public function getGrid($a_results, $a_abs = true, $a_perc = true)
	{
		global $lng;
		
		$res = array(
			"cols" => array(),
			"rows" => array()
		);
		
		$tmp = $a_results;
		$tmp = array_shift($tmp);
		$vars = $tmp[1]->getVariables();
		if($vars)
		{
			foreach($vars as $var)
			{
				$res["cols"][] = $var->cat->title;
			}
			$res["cols"][] = 'μ'; // CHANGES IN CORE
            $res["cols"][] = 'σ'; // CHANGES IN CORE
		}

		$q_counter = 0;
		foreach($a_results as $results_row)
		{
            // CHANGES IN CORE *start*
            $arithmetic_nom = 0;
            $square_sum = 0;
            $arithmetic_denom = 0;
            // CHANGES IN CORE *end*

			#20363
			$parsed_row = array(
				++$q_counter.". ".$results_row[0]

			);

			$vars = $results_row[1]->getVariables();
			if($vars)
			{
				foreach($vars as $var)
				{
					$perc = $var->perc
						? sprintf("%.2f", $var->perc*100)."%"
						: "0%";
					
					if((bool)$a_abs && (bool)$a_perc)
					{
						$parsed_row[] = $var->abs." / ".$perc;							
					}
					else if((bool)$a_abs)
					{
						$parsed_row[] = $var->abs;
					}
					else
					{
						$parsed_row[] = $perc;
					}

                    // CHANGES IN CORE *start*
                    if ($var->cat->neutral == 0) {
                        $arithmetic_nom += $var->cat->scale * $var->abs;
                        $square_sum += $var->cat->scale * $var->cat->scale * $var->abs;
                        $arithmetic_denom += $var->abs;
                    }
                    // CHANGES IN CORE *end*
				}

                // CHANGES IN CORE *start*
                if ($arithmetic_denom > 1) {
                    $arithmetic_mean = $arithmetic_nom / $arithmetic_denom;
                    $standart_deviation = sqrt(($arithmetic_denom / ($arithmetic_denom - 1)) * ($square_sum / $arithmetic_denom - $arithmetic_mean * $arithmetic_mean));
                } elseif ($arithmetic_denom == 1) {
                    $arithmetic_mean = $arithmetic_nom;
                    $standart_deviation = 0;
                } else {
                    $arithmetic_mean = 0;
                    $standart_deviation = 0;
                }
                $parsed_row[] = sprintf("%.2f", $arithmetic_mean);
                $parsed_row[] = sprintf("%.2f", $standart_deviation);
                // CHANGES IN CORE *end*
			}
			
			$res["rows"][] = $parsed_row;
		}
		return $res;
	}
	
	public function getTextAnswers($a_results)
	{
		$res = array();
		
		foreach($a_results as $results_row)
		{		
			$texts = $results_row[1]->getMappedTextAnswers();
			if($texts)
			{		
				$idx = $results_row[0];
				foreach($texts as $answers)
				{									
					foreach($answers as $answer)
					{
						$res[$idx][] = $answer;
					}
				}
			}
		}
		
		return $res;
	}
	
	public function getChart($a_results)
	{
		global $lng;
		
		include_once "Services/Chart/classes/class.ilChart.php";
		$chart = ilChart::getInstanceByType(ilChart::TYPE_GRID, $a_results[0][1]->getQuestion()->getId());				
		$chart->setXAxisToInteger(true);
		$chart->setStacked(true);
			
		$colors = $this->getChartColors();
		$chart->setColors($colors);

		// :TODO:
		//$chart->setsize($this->chart_width, $this->chart_height);
					
		$data = $labels = $legend = array();
		
		$row_idx = sizeof($a_results);

		$row_counter = 0;
		$text_shortened = false;
		foreach($a_results as $row)
		{
			$row_idx--;

			$row_title = $row[0];
			$row_results = $row[1];

			#20363
			$row_title = ++$row_counter.". ".$row_title;
			$labels[$row_idx] = ilUtil::shortenText($row_title, 50, true);
			if($labels[$row_idx] != $row_title) {
				$text_shortened = true;
			}
			//$labels[$row_idx] = wordwrap(ilUtil::shortenText($row_title, 50, true), 30, "<br />");
			
			$vars = $row_results->getVariables();
			if($vars)
			{								
				foreach($vars as $idx => $var)
				{																	
					if(!array_key_exists($idx, $data))
					{
						$data[$idx] = $chart->getDataInstance(ilChartGrid::DATA_BARS);
						$data[$idx]->setLabel($var->cat->title);
						$data[$idx]->setBarOptions(0.5, "center", true);
						$data[$idx]->setFill(1);
						
						$legend[] = array(
							$var->cat->title,
							$colors[$idx]
						);
					}
					
					$data[$idx]->addPoint($var->abs, $row_idx);											
				}				
			}
		}

		//Chart height depending on the number of questions. Not fixed anymore.
		$this->chart_height = count($a_results)*40;
		//Chart width 500px if one or + question string are longer than 60 char. Otherwise the default width still aplied.
		if($text_shortened) {
			$this->chart_width = 500;
		}
		$chart->setSize($this->chart_width, $this->chart_height);

		foreach($data as $var)
		{
			$chart->addData($var);			
		}
		
		$chart->setTicks(false, $labels, true);
		
		return array(
			$chart->getHTML(),
			$legend
		);
	}
	

	
	//
	// EXPORT
	//
	
	public function exportResults($a_results, $a_do_title, $a_do_label)
	{
		$question = $a_results[0][1]->getQuestion();
		
		$rows = array();		
		$row = array();
		
		if($a_do_title)
		{
			$row[] = $question->getTitle();
		}
		if($a_do_label)
		{
			$row[] = $question->label;
		}
		
		$row[] = $question->getQuestiontext();		
		$row[] = SurveyQuestion::_getQuestionTypeName($question->getQuestionType());
		
		$row[] = (int)$a_results[0][1]->getUsersAnswered();
		$row[] = (int)$a_results[0][1]->getUsersSkipped();
		$row[] = null;
		$row[] = null;
		$row[] = null;
		$row[] = null;
		$row[] = null;
		
		$rows[] = $row;		
		
		foreach($a_results as $row_result)
		{						
			$row_title = $row_result[0];
			$row_res = $row_result[1];
		
			$row = array();
			
			if($a_do_title)
			{
				$row[] = null;
			}
			if($a_do_label)
			{
				$row[] = null;
			}

			$row[] = $row_title;
			$row[] = null;

			$row[] = null;
			$row[] = null;
			
			// :TODO:
			$row[] = is_array($row_res->getModeValue())
				? implode(", ", $row_res->getModeValue())
				: $row_res->getModeValue();
			
			$row[] = $row_res->getModeValueAsText();
			$row[] = (int)$row_res->getModeNrOfSelections();

			// :TODO:
			$row[] = $row_res->getMedianAsText();

			$row[] = $row_res->getMean();
			
			$rows[] = $row;					
		}
		return $rows;		
	}
	
	public function getUserSpecificVariableTitles(array &$a_title_row, array &$a_title_row2, $a_do_title, $a_do_label)
	{		
		global $lng;
		
		for ($i = 0; $i < $this->question->getRowCount(); $i++)
		{
			// create row title according label, add 'other column'
			$row = $this->question->getRow($i);
			
			if($a_do_title && $a_do_label)
			{
				$a_title_row[] = $row->title;
				$a_title_row2[] = $row->label;

				if($this->question->getSubtype() == 0)
				{
					$a_title_row[] = $row->title;        // see #20646
					$a_title_row2[] = $row->label;        // see #20646
				}

				if ($row->other)
				{
					$a_title_row[] = $row->title;
					$a_title_row2[] = $lng->txt('other');
				}
			}
			else if($a_do_title)
			{
				$a_title_row[] = $row->title;
				$a_title_row2[] = "";

				if($this->question->getSubtype() == 0)
				{
					$a_title_row[] = $row->title;        // see #20646
					$a_title_row2[] = "";                // see #20646
				}

				if ($row->other)
				{
					$a_title_row[] = $row->title;
					$a_title_row2[] = $lng->txt('other');
				}
			}
			else
			{
				$a_title_row[] = $row->label;
				$a_title_row2[] = "";

				if($this->question->getSubtype() == 0)
				{
					$a_title_row[] = $row->label;        // see #20646
					$a_title_row2[] = "";                // see #20646
				}

				if ($row->other)
				{
					$a_title_row[] = $row->label;
					$a_title_row2[] = $lng->txt('other');
				}
			}
			
			// mc
			if($this->question->getSubtype() == 1)
			{				
				for($index = 0; $index < $this->question->getColumnCount(); $index++)
				{
					$col = $this->question->getColumn($index);
					
					$a_title_row[] = $col->title." [".$col->scale."]";
					$a_title_row2[] = "";					
				}
			}
		}		
	}
	
	public function addUserSpecificResults(array &$a_row, $a_user_id, $a_results)
	{		
		$answer_map = array();
		foreach($a_results as $row_results)
		{
			$row_title = $row_results[0];
			$row_result = $row_results[1];
			
			$answers = $row_result->getUserResults($a_user_id);
			if($answers !== null)
			{
				foreach($answers as $answer)
				{
					// mc
					if($this->question->getSubtype() == 1)
					{
						$answer_map[$row_title."|".$answer[2]] = $answer[2];
					}
					else
					{
						$answer_map[$row_title] = $answer[3];
						$answer_map[$row_title."|scale"] = $answer[2];		// see #20646
					}
					if($answer[1])
					{
						$answer_map[$row_title."|txt"] = $answer[1];
					}
				}				
			}			
		}
		
		if(!sizeof($answer_map))
		{
			$a_row[] = $this->getSkippedValue();
		}
		else
		{
			$a_row[] = "";
		}
		
		for ($i = 0; $i < $this->question->getRowCount(); $i++)
		{			
			$row = $this->question->getRow($i);
			$row_title = $row->title;
			
			$a_row[] = $answer_map[$row_title];
			if($this->question->getSubtype() == 0)
			{
				$a_row[] = $answer_map[$row_title . "|scale"];    // see #20646
			}
			
			if($row->other)
			{
				$a_row[] = $answer_map[$row_title."|txt"];				
			}
			
			// mc
			if($this->question->getSubtype() == 1)
			{
				for($index = 0; $index < $this->question->getColumnCount(); $index++)
				{
					$col = $this->question->getColumn($index);										
					$a_row[] = $answer_map[$row_title."|".$col->scale];
				}
			}
		}		
	}
}
