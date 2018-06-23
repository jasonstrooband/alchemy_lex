<?php

class Emitter {
  public $output = '';
  private $parent = 'program';
  private $startFound = false;
  private $fullAST;

  function __construct($ast){
    $this->fullAST = $ast['body'];

    if($ast['type'] == 'program'){
      try {
        $this->evaluate($ast['body']);
      } catch(Exception $e){
        print_error($e->getMessage());
      }
    } else {
      throw new Exception('AST top level must start as the program holding the script in the body');
    }
  }

  private function evaluate($ast){
    //var_dump($ast);
    if($this->parent == 'program' && $this->startFound == false){
      // Evaluate top level element
      for($x = 0; $x < count($ast); $x++){
        switch($ast[$x]['type']){
          case 'group':
            if(strtolower($ast[$x]['name']) == 'start'){
              $this->startFound = true;
              $this->evaluateGroup($ast[$x]);
            }
          break;
        default:
          throw new Exception('Unable to evaluate type: ' . $ast[$x]['type']);
          break;
        }
      }
      if($this->startFound == false) throw new Exception("Group 'Start not found");
      return true;
    }

    if($this->parent == 'group'){
      // Evaluate lines
      for($x = 0; $x < count($ast); $x++){
        switch($ast[$x]['type']){
          case 'string':
            $this->output .= $ast[$x]['value'];
            break;
          case 'groupcall':
            $found = false;
            for($y = 0; $y < count($this->fullAST); $y++){
              if(count($ast[$x]['params']) > 1) throw new Exception('Group calls cannot have more than 1 argument in this version!');
              if($ast[$x]['params'][0]['type'] != 'string') throw new Exception('The first argument for a group call must be the group name');

              if($this->fullAST[$y]['type'] == 'group' &&  $this->fullAST[$y]['name'] == $ast[$x]['params'][0]['value']){
                $this->evaluateGroup($this->fullAST[$y]);
                $found = true;
              }
            }
            if(!$found){
              throw new Exception('Cannot find group: ' . $ast[$x]['params'][0]['value']);
            }
          break;
          default:
            throw new Exception('Unable to evaluate type: ' . $ast[$x]['type']);
            break;
        }
      }
      $this->parent = 'program';
      return true;
    }

    throw new Exception('Unknown emitter evaluation parent: ' . $this->parent);
  }

  private function evaluateGroup($groupAST){
    switch($groupAST['value']){
      case ';':
        $group_min = 1;
        $group_max = 0;
        foreach($groupAST['params'] as $k => $line){
          if($line['range'] < 1) throw new Exception("Probability line number cannot be below 1 for group: " . $groupAST['name']);

          if($line['range'] != 1){
            // Convert shares to ranges
            $groupAST['params'][$k]['range_min'] = $group_max + 1;
            $groupAST['params'][$k]['range_max'] = $groupAST['params'][$k]['range_min'] + $line['range'] - 1;
            $group_max += $line['range'];
            unset($groupAST['params'][$k]['range']);
          } else {
            $groupAST['params'][$k]['range'] = $group_max + 1;
            $group_max++;
          }
        }
        break;
      case ':':
        $group_min = isset($groupAST['params'][0]['range_min']) ? $groupAST['params'][0]['range_min'] : $groupAST['params'][0]['range'];
        $group_max = $group_min;
        $line_last = $group_max;
        $lineProg = null;

        foreach($groupAST['params'] as $k => $line){

          if(isset($line['range_max'])){
            $line_num_min = $line['range_min'];
            $line_num_max = $line['range_max'];

            // If it is the first line you don't ned to error check if the number is too low
            if($k != 0){
              if($line_num_min <= $line_last) throw new Exception("Line number minimum range cannot be lower for group '" . $groupAST['name'] . "'");
              if($line_num_max <= $line_last) throw new Exception("Line number maximum range cannot be lower for group '" . $groupAST['name'] . "'");
            }
            if($line_num_min > ($line_last + 1)) throw new Exception("Line number min cannot be more than 1 higher than the last line number for group '" . $groupAST['name'] . "'");
            //if($line_num_max > ($line_last + 2)) throw new Exception("Line number max cannot be more than 1 higher than the last line number for group '" . $groupAST['name'] . "'");
            $group_max = $line_num_max;
          } else {
            $line_num = $line['range'];

            if($line_num < $line_last) throw new Exception("Line number cannot be lower for group '" . $groupAST['name'] . "'");
            if($line_num == $line_last && count($groupAST['params']) != 1 && $k > 0)throw new Exception("Line number cannot repeat for group '" . $groupAST['name'] . "'");
            if($line_num > ($line_last + 1)) throw new Exception("Line number cannot be more than 1 higher than the last line number for group '" . $groupAST['name'] . "'");
            $group_max = $line_num;
          }
          $line_last = $group_max;
        }
        break;
      case '|':
        $group_min = 1;
        $group_max = count($groupAST['params']);
        break;
      default:
        throw new Exception("Unknown group type '" . $groupAST['value'] . "' for group '"  . $groupAST['name']);
        break;
    }

    $rand = mt_rand($group_min, $group_max);

    if($groupAST['value'] == '|'){
      $lineProg = $rand - 1;
    } else {
      foreach($groupAST['params'] as $k => $line){
        if(isset($line['range_max'])){
          if($rand >= $line['range_min'] && $rand <= $line['range_max']) $lineProg = $k;
        } else {
          if($rand == $line['range']) $lineProg = $k;
        }
      }
    }

    if(!isset($lineProg)) throw new Exception("Unknown error, line not selected for group: " . $groupAST['name']);

    $this->parent = 'group';
    $this->evaluate($groupAST['params'][$lineProg]['params']);
  }
}