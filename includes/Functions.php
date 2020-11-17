<?php
class Functions {
  public static function evaluateFunction($ast){
    $functionName = $ast['value'];

    switch(strtolower($functionName)){
      case 'abs':
        return self::abs($ast['params']);
        break;
      case 'cap':
        return self::cap($ast['params']);
        break;
      case 'capeachword':
        return self::capeachword($ast['params']);
        break;
      case 'color':
        return self::color($ast['params']);
        break;
      case 'dice':
        return self::dice($ast['params']);
        break;
      case 'isnumber':
        return self::isNumber($ast['params']);
        break;
      case 'status':
        return self::status($ast['params']);
        break;
      default:
        throw new Exception("Emitter Error: Function '" . $functionName . "' does not exist");
        break;
    }
    throw new Exception("Emitter Error: No return value found for function '" . $functionName . "'");
  }

  // ******************************************************************************************************************************** //

  // Flips a negative number to a positive
  private static function abs($params) {
    self::checkFunctionParams('Abs', count($params), 1);
    return abs($params[0]);
  }

  // Uppercase the first letter in a string
  private static function cap($params) {
    self::checkFunctionParams('Cap', count($params), 1);
    return ucfirst($params[0]);
  }

  // Uppercase the first letter of every word
  private static function capeachword($params) {
    self::checkFunctionParams('CapEachWord', count($params), 1);
    return ucwords($params[0]);
  }

  // Changes the text colour of a message
  private static function color($params) {
    self::checkFunctionParams('Color', count($params), 2);
    return "<span style=color:". strtolower($params[0]) . ";>" . $params[1] . "</span>";
  }

  // Rolls a dice in the format 2d6 for 2 6 side dice or d6 for one six sided dice
  private static function dice($params) {
    self::checkFunctionParams('Dice', count($params), 1, 0);
    $output = '';
    for ($i=0; $i < count($params); $i++) {
      $currentParam = $params[$i];
      if(preg_match('/(\d+)*d(\d+)/', $currentParam, $matches)) {}
      array_splice($matches, 0, 1);
      $dice = ($matches[0] == '' ? 1 : (int)$matches[0]);
      $sides = (int)$matches[1];
      $result = 0;
     
      for($x = 0; $x < $dice; $x++){
        $result += rand(1, $sides);
      }

      $output .= $result . ', ';
    }
    $output = trim($output);
    return $output;
  }

  // Returns true if the parameter is a number else false
  private static function isNumber($params) {
    self::checkFunctionParams('IsNumber', count($params), 1);
    return (is_numeric($params[0]) ? '1' : '0');
  }

  // Saves a status message for printing to the screen later
  private static function status($params) {
    self::checkFunctionParams('STatus', count($params), 1);
    add_status($params[0]);
    return '';
  }

  // ******************************************************************************************************************************** //
  
  // Check if the parameters supplied fall within the correct range
  private static function checkFunctionParams($functionName, $paramGiven, $paramMin = null, $paramMax = null) {
    $range = $paramMin . '-' . $paramMax;

    if($paramMax === null) {
      $paramMax = $paramMin;
      $range = $paramMin;
    }

    if($paramMax === 0) {
      if($paramGiven < $paramMin) {
        throw new Exception("Emitter Error: " . $functionName . " function expects " . $paramMin . " parameter, found " . $paramGiven);
      }
    } else {
      if($paramGiven < $paramMin || $paramGiven > $paramMax) {
        throw new Exception("Emitter Error: " . $functionName . " function expects " . $range . " parameter, found " . $paramGiven);
      }
    }
  }
}