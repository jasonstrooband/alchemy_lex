<?php

class Emitter {
  // Declare Vars
  public $output = '';
  private $parent = 'program';
  private $startFound = false;
  private $fullAST;
  private $expressionResult = false;
  private $scriptVariables = array();

  function __construct($ast){
    // Set the fullAST format
    $this->fullAST = $ast['body'];

    // If the ast type is program evaluate or catch/print errors else throw and error that the program must be evaluated from the program AST
    if($ast['type'] == 'program'){
      try {
        $this->evaluate($ast['body']);
      } catch(Exception $e){
        print_error($e->getMessage());
      }
    } else {
      throw new Exception('Emitter Error: AST top level must start as the program holding the script in the body');
    }
  }

  // Main AST evaluate function
  private function evaluate($ast, $return = false){
    // If the parent is pregram and start has not been found
    if($this->parent == 'program' && $this->startFound == false){
      // Evaluate top level node
      for($x = 0; $x < count($ast); $x++){
        switch($ast[$x]['type']){
          // Evaluate top level expression
          case 'expression':
            $this->evaluateExpression($ast[$x]['params']);
            break;
          // Evaluate top level declare variable
          case 'declare_var':
            $this->setVariable($ast[$x]['params']['name'], $ast[$x]['params']['value']);
            break;
          // Evaluate top level group
          case 'group':
            // If lowercase groupname is start evaluate it and sets tartFound to true else just ignore the group
            if(strtolower($ast[$x]['name']) == 'start'){
              $this->startFound = true;
              $this->output .= $this->evaluateGroup($ast[$x]);
            }
            break;
          default:
            // Any other top level AST is not allowed
            throw new Exception('Emitter Error: Unknown top level evaluation: ' . $ast[$x]['type']);
            break;
        }
      }
      // After evaluation and start is not found throw an error else return true
      if($this->startFound == false) throw new Exception("Emitter  Error: Group 'Start not found");
      return true;
    }

    // If the parent is a group evaluate the line output and set the parent to program, add the lione output to the global output or return the value if return is true
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

    // Unknown parent throw an error
    throw new Exception("Emitter Error: Unknown emitter evaluation parent: " . $this->parent);
  }

  // Evaluate a node - a line, expression or functioncall node is passed
  private function evaluateSingleLine($ast) {
    $lineOutput = '';
    // For every node in the AST
    for($x = 0; $x < count($ast); $x++){
      // If the AST loop goes out of range throw an error
      if(!isset($ast[$x]['type'])) {
        throw new Exception('Emitter Error: Out of range ast');
      }

      // What is the AST type
      switch($ast[$x]['type']){
        // A number or a string value is just added the the lineOutput
        case 'number':
        case 'string':
          $lineOutput .= $ast[$x]['value'];
          break;
        // A variable value is resolved and added to the output
        case 'variable':
          $lineOutput .= $this->getVariable($ast[$x]['value']);
          break;
        // A group call is resolved and added to the output
        case 'groupcall':
          $lineOutput .= $this->groupCall($ast[$x]);
        break;
        // A function call is resolved and added to the output
        case 'functioncall':
          // Resolve all the parameters of the functioncall before evaluating the function
          $tempParams = array();
          foreach($ast[$x]['params'] as $param){
            $tempParams[] = $this->evaluateSingleLine(array($param));
          }
          $ast[$x]['params'] = $tempParams;
          $lineOutput .= Functions::evaluateFunction($ast[$x]);
          break;
          // An expression is resolved and added to the output
        case 'expression':
          $lineOutput .= $this->evaluateExpression($ast[$x]['params']);
          break;
        default:
        // Throw an error because type is not valid
          throw new Exception("Emitter Error: Unable to evaluate type: " . $ast[$x]['type']);
          break;
      }
    }

    return $lineOutput;
  }

  // Get the return value of a group call
  private function groupCall($ast){
    // Group found is false for now
    $found = false;

    // For every top level element in fullAST
    for($y = 0; $y < count($this->fullAST); $y++){
      // Error check
      if(count($ast['params']) > 1) throw new Exception('Group calls cannot have more than 1 argument in this version!');
      // TODO: might not need this in the loop
      if($ast['params'][0]['type'] != 'string') throw new Exception('The first argument for a group call must be the group name');

      // If the group is found evaluate the group
      if($this->fullAST[$y]['type'] == 'group' &&  $this->fullAST[$y]['name'] == $ast['params'][0]['value']){
        $groupOutput = $this->evaluateGroup($this->fullAST[$y]);
        $found = true;
      }
    }
    // If the group was not found throw an error
    if(!$found){
      throw new Exception('Cannot find group: ' . $ast['params'][0]['value']);
    }

    return $groupOutput;
  }

  // Return the output of a group node
  private function evaluateGroup($groupAST){
    switch($groupAST['value']){
      // If group type is probability based on shares go through each line and convert to a group min and group max range
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
      // If the group is sequantial based go through each line and add up the group min and the group max
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
      // If the group is equal based set group min to 1 and group max to the number of lines
      case '|':
        $group_min = 1;
        $group_max = count($groupAST['params']);
        break;
      default:
        // Not a known group type throw an error
        throw new Exception("Emitter Error: Unknown group type '" . $groupAST['value'] . "' for group '"  . $groupAST['name']);
        break;
    }

    // Get a random number based on the range
    $rand = mt_rand($group_min, $group_max);

    // If the group type is equal based, picked line is rand-1
    if($groupAST['value'] == '|'){
      $lineProg = $rand - 1;
    } else {
      // Not equal group
      // For every line in the group
      foreach($groupAST['params'] as $k => $line){
        // If there is a max range
        if(isset($line['range_max'])){
          // If the line matches rand then pick that line
          if($rand >= $line['range_min'] && $rand <= $line['range_max']) $lineProg = $k;
        } else {
          // If rand is the same as the line range, pick that line
          if($rand == $line['range']) $lineProg = $k;
        }
      }
    }

    // Throw an error if a line was not picked
    if(!isset($lineProg)) throw new Exception("Emitter Error: Unknown error, line not selected for group: " . $groupAST['name']);

    // Reset parent to group, evalute the picked line and return the output
    $this->parent = 'group';
    return $this->evaluate($groupAST['params'][$lineProg]['params'], true);
  }

  // Evaluate an expression node
  private function evaluateExpression($ast) {
    $expressionOutput = '';
    // What is the expression node type
    switch($ast['type']){
      // Assignment operator
      case 'assign':
        // Must assign to a variable so else throw an error
        if($ast['left']['type'] != 'variable') throw new Exception("Emitter Error: Cannot assign to '". $ast['left']['type'] . "'");

        // What is the assignment operator
        switch($ast['operator']) {
          // Variable equals expression result
          case '=':
            $this->scriptVariables[$ast['left']['value']] = $this->evaluateExpression($ast['right']);
            break;
          // Add expression result to variable
          case '+=':
            $this->scriptVariables[$ast['left']['value']] += $this->evaluateExpression($ast['right']);
            break;
          // Minus expression result to variable
          case '-=':
            $this->scriptVariables[$ast['left']['value']] -= $this->evaluateExpression($ast['right']);
            break;
          // Multiply expression result to variable
          case '*=':
            $this->scriptVariables[$ast['left']['value']] *= $this->evaluateExpression($ast['right']);
            break;
          // Divide expression result to variable
          case '/=':
            $this->scriptVariables[$ast['left']['value']] /= $this->evaluateExpression($ast['right']);
            break;
          default:
            // Not an assignment operator
            throw new Exception("Emitter Error: Unable to evaluate assign type: " . $ast['operator']);
            break;
        }
        break;
      // Evaluate a binary operator result
      case 'binary':
        $expressionOutput .= $this->evaluateBinary($ast['operator'], $ast['left'], $ast['right']);
        break;
      // Resolve a variable value
      case 'variable':
        $expressionOutput .= $this->getVariable($ast['value']);
        break;
      // Add the number to the output
      case 'number':
        $expressionOutput .= $ast['value'];
        break;
        // Remove beginning and end of the string and add it to the output
      case 'string':
        $expressionOutput .= substr($ast['value'], 1, -1);
        break;
      // Resolve the group call
      case 'groupcall':
        $expressionOutput .= $this->groupCall($ast);
        break;
      default:
        // Not a known expression type, throw an error
        throw new Exception("Emitter Error: Unable to evaluate expression type: " . ($ast['type'] == null ? 'null' : $ast['type']));
        break;
    }

    return $expressionOutput;
  }

  // Evaluate a binary expression
  private function evaluateBinary($operator, $left, $right){
    // Evaluate the leftmost node
    $left = $this->evaluateExpression($left);
    // Evaluate the rightmost node
    $right = $this->evaluateExpression($right);

    // Only the plus operator can be used with strings
    switch ($operator) {
      case "+":
        break;
      case "-":
      case "*":
      case "/":
      case "^":
        if(!is_numeric($left) || !is_numeric($right)){
          throw new Exception("Emitter Error: Cannot " . $operator . " a string");
        }
        break;
      default:
      throw new Exception("Emitter Error: Unknown operator for string operations: " . $operator);
        break;
    }

    // Calculate binary operation of left and right
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
  
  // Set a variables value - if an array is passed evaluate it first then store it
  private function setVariable($name, $value) {
    if(is_array($value)) {
      $this->scriptVariables[$name] = $this->evaluateSingleLine($value);
    } else {
      $this->scriptVariables[$name] = $value;
    }
  }

  // Returns a variable by name - if an array was stored or the variable doesn't exist throw an error
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