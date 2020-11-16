<?php

class Emitter {
  public $output = '';
  private $parent = 'program';
  private $startFound = false;
  private $fullAST;
  private $expressionResult = false;
  private $scriptVariables = array();

  function __construct($ast){
    $this->fullAST = $ast['body'];

    if($ast['type'] == 'program'){
      try {
        $this->evaluate($ast['body']);
      } catch(Exception $e){
        print_error($e->getMessage());
      }
    } else {
      throw new Exception('Emitter Error: AST top level must start as the program holding the script in the body');
    }
    //var_dump($this->scriptVariables);
  }

  private function evaluate($ast, $return = false){
    //if(isset($ast['type']) && $ast['type'] == "binary"){
    //  return $this->evaluateBinary($ast['operator'], $ast['left'], $ast['right']);
    //}
    if($this->parent == 'program' && $this->startFound == false){
      // Evaluate top level element
      for($x = 0; $x < count($ast); $x++){
        switch($ast[$x]['type']){
          case 'expression':
            $this->evaluateExpression($ast[$x]['params']);
            break;
          case 'declare_var':
            $this->setVariable($ast[$x]['params']['name'], $ast[$x]['params']['value']);
            break;
          case 'group':
            if(strtolower($ast[$x]['name']) == 'start'){
              $this->startFound = true;
              $this->output .= $this->evaluateGroup($ast[$x]);
            }
            break;
          default:
            throw new Exception('Emitter Error: Unknown top level evaluation: ' . $ast[$x]['type']);
            break;
        }
      }
      if($this->startFound == false) throw new Exception("Emitter  Error: Group 'Start not found");
      return true;
    }

    if($this->parent == 'group'){
      $groupOutput = '';
      // Evaluate lines
      $lineOutput = $this->evaluateSingleLine($ast);
      $this->parent = 'program';
      if($return == false) {
        $this->output .= $lineOutput;
      } else {
        return $lineOutput;
      }
      return true;
    }

    throw new Exception("Emitter Error: Unknown emitter evaluation parent: " . $this->parent);
  }

  private function evaluateSingleLine($ast) {
    $lineOutput = '';
    for($x = 0; $x < count($ast); $x++){
      if(!isset($ast[$x]['type'])) {
        //var_dump($ast[$x]);
        throw new Exception('Emitter Error: Out of range ast');
      }
      switch($ast[$x]['type']){
        case 'number':
        case 'string':
          $lineOutput .= $ast[$x]['value'];
          break;
        case 'variable':
          $lineOutput .= $this->getVariable($ast[$x]['value']);
          break;
        case 'groupcall':
          $lineOutput .= $this->groupCall($ast[$x]);
        break;
        case 'functioncall':
          $tempParams = array();
          foreach($ast[$x]['params'] as $param){
            $tempParams[] = $this->evaluateSingleLine(array($param));
          }
          $ast[$x]['params'] = $tempParams;
          $lineOutput .= Functions::evaluateFunction($ast[$x]);
          break;
        case 'expression':
          $lineOutput .= $this->evaluateExpression($ast[$x]['params']);
          break;
        default:
          throw new Exception("Emitter Error: Unable to evaluate type: " . $ast[$x]['type']);
          break;
      }
    }

    return $lineOutput;
  }

  private function groupCall($ast){
    $found = false;
    for($y = 0; $y < count($this->fullAST); $y++){
      if(count($ast['params']) > 1) throw new Exception('Group calls cannot have more than 1 argument in this version!');
      if($ast['params'][0]['type'] != 'string') throw new Exception('The first argument for a group call must be the group name');

      if($this->fullAST[$y]['type'] == 'group' &&  $this->fullAST[$y]['name'] == $ast['params'][0]['value']){
        $groupOutput = $this->evaluateGroup($this->fullAST[$y]);
        $found = true;
      }
    }
    if(!$found){
      throw new Exception('Cannot find group: ' . $ast['params'][0]['value']);
    }

    return $groupOutput;
  }

  private function evaluateGroup($groupAST){
    switch($groupAST['value']){
      case ';':
        $group_min = 1;
        $group_max = 0;
        foreach($groupAST['params'] as $k => $line){
          if($line['range'] < 1) throw new Exception("Emitter Error: Probability line number cannot be below 1 for group: " . $groupAST['name']);

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
              if($line_num_min <= $line_last) throw new Exception("Emitter Error: Line number minimum range cannot be lower for group '" . $groupAST['name'] . "'");
            }
            if($line_num_min > ($line_last + 1)) throw new Exception("Emitter Error: Line number min cannot be more than 1 higher than the last line number for group '" . $groupAST['name'] . "'");
            $group_max = $line_num_max;
          } else {
            $line_num = $line['range'];

            if($line_num < $line_last) throw new Exception("Emitter Error: Line number cannot be lower for group '" . $groupAST['name'] . "'");
            if($line_num == $line_last && count($groupAST['params']) != 1 && $k > 0)throw new Exception("Emitter Error: Line number cannot repeat for group '" . $groupAST['name'] . "'");
            if($line_num > ($line_last + 1)) throw new Exception("Emitter Error: Line number cannot be more than 1 higher than the last line number for group '" . $groupAST['name'] . "'");
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
        throw new Exception("Emitter Error: Unknown group type '" . $groupAST['value'] . "' for group '"  . $groupAST['name']);
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

    if(!isset($lineProg)) throw new Exception("Emitter Error: Unknown error, line not selected for group: " . $groupAST['name']);

    $this->parent = 'group';
    return $this->evaluate($groupAST['params'][$lineProg]['params'], true);
  }

  private function evaluateExpression($ast) {
    $expressionOutput = '';
    switch($ast['type']){
      case 'assign':
        if($ast['left']['type'] != 'variable') throw new Exception("Emitter Error: Cannot assign to '". $ast['left']['type'] . "'");
        switch($ast['operator']) {
          case '=':
            $this->scriptVariables[$ast['left']['value']] = $this->evaluateExpression($ast['right']);
            break;
          case '+=':
            $this->scriptVariables[$ast['left']['value']] += $this->evaluateExpression($ast['right']);
            break;
          case '-=':
            $this->scriptVariables[$ast['left']['value']] -= $this->evaluateExpression($ast['right']);
            break;
          case '*=':
            $this->scriptVariables[$ast['left']['value']] *= $this->evaluateExpression($ast['right']);
            break;
          case '/=':
            $this->scriptVariables[$ast['left']['value']] /= $this->evaluateExpression($ast['right']);
            break;
          default:
          throw new Exception("Emitter Error: Unable to evaluate assign type: " . $ast['operator']);
            break;
        }
        //var_dump($this->scriptVariables);
        break;
      case 'binary':
        $expressionOutput .= $this->evaluateBinary($ast['operator'], $ast['left'], $ast['right']);
        break;
      case 'variable':
        $expressionOutput .= $this->getVariable($ast['value']);
        break;
      case 'number':
        $expressionOutput .= $ast['value'];
        break;
      case 'string':
        $expressionOutput .= substr($ast['value'], 1, -1);
        break;
      case 'groupcall':
        $expressionOutput .= $this->groupCall($ast);
        break;
      default:
        var_dump($ast);
        throw new Exception("Emitter Error: Unable to evaluate expression type: " . ($ast['type'] == null ? 'null' : $ast['type']));
        break;
    }

    return $expressionOutput;
  }

  private function evaluateBinary($operator, $left, $right){
    $left = $this->evaluateExpression($left);
    $right = $this->evaluateExpression($right);

    switch ($operator) {
      case "-":
      case "*":
      case "/":
      case "^":
        if(!is_numeric($left) || !is_numeric($right)){
          throw new Exception("Emitter Error: Cannot " . $operator . " a string");
        }
        break;
    }

    switch ($operator) {
      case "+":
        if(is_numeric($left) && is_numeric($right)) {
          return $left + $right;
        } else {
          return $left . $right;
        }
      case "-": return $left - $right;
      case "*": return $left * $right;
      // TODO: Add check to see if not dividing by 0
      case "/": return $left / $right;
      case "^": return pow($left, $right);
      default:
        throw new Exception("Emitter Error: Binary operator currently not supported or unknown: " . $operator);
        break;
    }
  }
  
  private function setVariable($name, $value) {
    if(is_array($value)) {
      $this->scriptVariables[$name] = $this->evaluateSingleLine($value);
    } else {
      $this->scriptVariables[$name] = $value;
    }
  }

  private function getVariable($renderVar) {
    if(array_key_exists($renderVar, $this->scriptVariables)) {
      if(is_array($this->scriptVariables[$renderVar])) {
        throw new Exception("Emitter Error: Variable set as an array cannot convert to string: " . $renderVar);
      } else {
        return $this->scriptVariables[$renderVar];
      }
    } else {
      throw new Exception("Emitter Error: Variable not declared: " . $renderVar);
    }
  }
}