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
      case 'dice':
        return self::dice($ast['params']);
        break;
      case 'isnumber':
        return self::isNumber($ast['params']);
        break;
      default:
        throw new Exception("Emitter Error: Function '" . $functionName . "' does not exist");
        break;
    }
    throw new Exception("Emitter Error: No return value found for function '" . $functionName . "'");
  }

  private static function abs($params) {
    self::checkFunctionParams('Abs', count($params), 1);
    return abs($params[0]);
  }

  private static function cap($params) {
    self::checkFunctionParams('Cap', count($params), 1);
    return ucfirst($params[0]);
  }

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

  private static function isNumber($params) {
    self::checkFunctionParams('IsNumber', count($params), 1);
    return (is_numeric($params[0]) ? '1' : '0');
  }
  
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