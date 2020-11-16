<?php
class Parser {
  private $inComment = false;
  private $inExpression = false;
  private $parseOutput = false;
  private $current = false;
  private $tokens;
  private $tempPreFix;

  private $parameterWarn;
  private $overrideWarn;
  private $htmlWarn;
  
  private static $operators = [
    '+' => ['precedence' => 0, 'associativity' => 0],
    '-' => ['precedence' => 0, 'associativity' => 0],
    '*' => ['precedence' => 1, 'associativity' => 0],
    '/' => ['precedence' => 1, 'associativity' => 0],
    '%' => ['precedence' => 1, 'associativity' => 0],
    '^' => ['precedence' => 2, 'associativity' => 1],
    '=' => ['precedence' => -1, 'associativity' => 1],
    '+=' => ['precedence' => -1, 'associativity' => 1],
    '-=' => ['precedence' => -1, 'associativity' => 1],
    '*=' => ['precedence' => -1, 'associativity' => 1],
    '/=' => ['precedence' => -1, 'associativity' => 1],
  ];

  public function __construct($tokens){
    $this->tokens = $tokens;
    $this->current = 0;
    $ast = array(
      'type' => "program",
      'body' => array(),
    );
    $node = null;
    while($this->current < count($this->tokens)){
      try {
        $output = $this->parseToken();
        if($output === false) break;
        if(gettype($output) !== 'boolean') $ast['body'][] = $output;
      } catch(Exception $e) {
        print_error($e->getMessage());
        break;
      }
      $this->current++;
    }
    $this->output = $ast;
  }

  protected function parseToken($setToken = null){
    if($setToken != null) {
      $token = $setToken;
    } else {
      $token = $this->tokens[$this->current];
    }
    switch($token['token']){
      // Ignore
      case 'T_LINECOMMENT': // Ignore all comments
      case 'T_GROUP_NAME': // Used in group expression don't parse
      //case 'T_GROUP_OPEN_BRACKET': // Used in group expression don't parse
      case 'T_DOUBLE_NEWLINE':
      case 'T_GROUP_CLOSE_BRACKET': // Used in group expression don't parse
      case 'T_GROUPCALL_CLOSE_BRACKET': // Used in groupcall expression don't parse
      case 'T_FUNCTIONCALL_CLOSE_BRACKET': // Used in functioncall expression don't parse
        break;
      // Open comment block ignore all else till close block
      case 'T_BLOCKCOMMENT_OPEN':
        $this->inComment = true;
        break;
      // Close the comment block
      case 'T_BLOCKCOMMENT_CLOSE':
        $this->inComment = false;
        break;
      // If not in a comment return the AST
      case 'T_COMMA':
      case 'T_STRING':
      case 'T_BRACKETS':
      //Math tokens not inside an expression should bre treated like a string
      case 'T_MATH_ADDITION':
      case 'T_MATH_SUBTRACTION':
      case 'T_MATH_MULTIPLY':
      case 'T_MATH_DIVISION':
      case 'T_MATH_POWER':
      case 'T_MATH_EQUALS':
      case 'T_WHITESPACE':
      case 'T_PUNCTUATION':
        if(!$this->inComment && $this->parseOutput){
          return array(
            'type'  => 'string',
            'value' => $token['value']
          );
        }
        break;
      case 'T_NUMBER':
      case 'T_FLOAT':
        if(!$this->inComment){
          return array(
            'type'  => 'number',
            'value' => $token['value']
          );
        }
        break;
      // Group Expression
      case 'T_GROUP_IDENTIFIER':
        return $this->parseDelimSubProgram('group');
        break;
      // Line expression
      case 'T_GROUP_LINE_SINGLE_NUMBER':
      case 'T_GROUP_LINE_RANGE_NUMBER':
      case 'T_GROUP_LINE_EQUAL_NUMBER':
        $this->parseOutput = true;

        return $this->parseDelimSubProgram('line');
        break;
      // After newline no more parsing content
      case 'T_NEWLINE':
        $this->parseOutput = false;
        break;
      // Group call Expression
      case 'T_GROUPCALL':
        return $this->parseEnclosedSubProgram('groupcall');
        break;
      // Group call Expression
      // Depracated
      case 'T_GROUPCALL_OPEN_BRACKET':
        return $this->parseDelimSubProgram('groupcall');
        break;
      // Group call Expression
      case 'T_EXPRESSION_BOUNDARY':
        $this->inExpression = true;
        $this->current++;

        //TODO: Add error checking to check for invalid expressions like double binary symbols
        $expressionTokens = $this->findAllTokensInExpression();
        $postFix = $this->shuntingYard($expressionTokens);
        $preFix = $this->reverseShuntingYard($expressionTokens);
        $expressionTree = $this->prefixToTree($preFix);

        //var_dump("PostFix: " . implode(' ', $postFix));
        //var_dump("PreFix: " . implode(' ', $preFix));
        //var_dump("Expression Tree: ");
        //var_dump($expressionTree);
        
        return array(
          'type'  => 'expression',
          'params' => $expressionTree
        );
        break;
      // Function call Expression
      case 'T_FUNCTIONCALL_OPEN_BRACKET':
        $functionASTRaw = $this->parseDelimSubProgram('functioncall');
        $functionASTRaw['params'] = array_filter( $functionASTRaw['params'], function($v) {
          if(!isset($v['value'])) return true;
          return $v['value'] != ',';
        });
        $functionASTFormatted =  array(
          'type'  => 'functioncall',
          'value'  => array_shift($functionASTRaw['params'])['value']
        );
        array_shift($functionASTRaw['params']);
        $functionASTFormatted['params'] = $functionASTRaw['params'];
        return $functionASTFormatted;
        break;
      case 'T_VARIABLE_DECLARE':
        $varArray = explode(',', str_replace('%', '', $token['value']));
        return array(
          'type'  => 'declare_var',
          'params' => array(
            'name' => $varArray[0],
            'value' => $this->parseDelimSubProgram('declare_var')
          )
        );
        break;
      case 'T_VARIABLE_RENDER':
        return array(
          'type' => 'variable',
          'value' => str_replace('%', '', $token['value'])
        );
        break;
      case 'T_PARAMETER':
        if(!$this->parameterWarn) add_status('Parameters are unavailable and may be added soon'); $this->parameterWarn = true;
        break;
      case 'T_OVERRIDE':
        if(!$this->overrideWarn) add_status('Overrides are unavailable and may be added soon'); $this->overrideWarn = true;
      break;
      case 'T_HTML':
        if(!$this->htmlWarn) add_status('HTML tags are unavailable and may be added soon'); $this->htmlWarn = true;
        break;
      case 'T_HTML_UNSUPPORTED':
        throw new Exception("Error Error: HTML with properties is not supported or unrecognised: '" . $token['value'] . "'");
        break;
      // End of Script
      case 'T_EOF':
        return false;
        break;
      default:
        // Unknown token found
        throw new Exception("Parse Error: Unknown token " . $token['token'] . " at line " . $token['line'] . "-" . $token['offset']);
        break;
    }

    return true;
  }
  protected function parseEnclosedSubProgram($type){
    $token = $this->tokens[$this->current];

    $node = array();
    $node['type'] = $type;

    switch($type){
      case 'groupcall':
        // Strip surrounding brackets
        $value = substr($token['value'], 1, -1);
        // Create child array token
        $tempToken = array(
          'token' => 'T_STRING',
          'value' => $value,
          'line' => $token['line'],
          'offset' => $token['offset']++
        );
        // Return ast from child token and assign to the parent node
        $param = Parser::parseToken($tempToken);
        $node['params'] = array($param);
        break;
      default:
        // Unknown expression type
        throw new Exception("Parse Error: Unknown expression '" . $type . "' at line " . $token['line'] . "-" . $token['offset']);
        break;
    }

    return $node;
  }

  protected function parseDelimSubProgram($type){
    $token = $this->tokens[$this->current];
    $close = '';
    $delimiter = '';

    $node = array();
    $node['type'] = $type;

    $open = $token['line'] . "-" . $token['offset'];

    switch($type){
      case 'group':
        $close = 'T_DOUBLE_NEWLINE';
        $delimiter = '}';
        $node['value'] = $token['value'];
        $node['name'] = $this->tokens[$this->current + 1]['value'];
        //var_dump($node);
        break;
      case 'line':
        $close = 'T_NEWLINE';

        if($token['token'] == 'T_GROUP_LINE_EQUAL_NUMBER'){
          // Do nothing, there is no range to calculate
        } else if($token['token'] == 'T_GROUP_LINE_SINGLE_NUMBER'){
          $node['range'] = rtrim($token['value'], ',');
        } else {
          $range = explode('-', $token['value']);
          $node['range_min'] = rtrim($range[0], ',');
          $node['range_max'] = rtrim($range[1], ',');
        }

        break;
      case 'groupcall':
        $close = 'T_GROUPCALL_CLOSE_BRACKET';
        $delimiter = ']';
        break;
      case 'functioncall':
        $close = 'T_FUNCTIONCALL_CLOSE_BRACKET';
        $delimiter = '}';
        break;
      case 'declare_var':
        $this->parseOutput = true;
        $close = 'T_NEWLINE';
        $delimiter = '\n';
        break;
      default:
        // Unknown expression type
        throw new Exception("Parse Error: Unknown sub program '" . $type . "' at line " . $token['line'] . "-" . $token['offset']);
        break;
    }

    $node['params'] = array();

    if(isset($close)){
      while(!($token['token'] === $close)){
        //var_dump($close . ' - ' . $token['token']);
        if($token['token'] == 'T_EOF'){
          if($close == 'T_NEWLINE' || $close == 'T_DOUBLE_NEWLINE') {
            return $node;
          }
          throw new Exception("Parse Error: Unexpected end of file, expected '" . $delimiter . "' - '" . $close . "' - Start at: " . $open);
        }
        $this->current++;
        if($this->current > count($this->tokens)-1) $this->current = count($this->tokens) - 1;

        $token = $this->tokens[$this->current];
        $param = Parser::parseToken();
        if(gettype($param) !== 'boolean') $node['params'][] = $param;
      }
      $open = '';
    }
    
    $close = '';
    $delimiter = '';

    if($type == 'declare_var') {
      $this->parseOutput = false;
      return $node['params'];
    }

    return $node;
  }

  protected function findAllTokensInExpression() {
    $found = false;
    $stack = array();

    while($found == false) {
      $token = $this->tokens[$this->current];

      switch($token['token']) {
        case 'T_NUMBER':
        case 'T_FLOAT':
        case 'T_STRING':
        case 'T_MATH_ADDITION_EQUALS':
        case 'T_MATH_SUBTRACTION_EQUALS':
        case 'T_MATH_MULTIPLY_EQUALS':
        case 'T_MATH_DIVISION_EQUALS':
        case 'T_MATH_ADDITION':
        case 'T_MATH_SUBTRACTION':
        case 'T_MATH_MULTIPLY':
        case 'T_MATH_DIVISION':
        case 'T_MATH_POWER':
        case 'T_MATH_EQUALS':
        case 'T_OPEN_BRACKET':
        case 'T_CLOSE_BRACKET':
        case 'T_VARIABLE_RENDER':
        case 'T_GROUPCALL':
          $stack[] = $token;
          break;
        case 'T_EXPRESSION_BOUNDARY':
          $found = true;
          break;
        // Ignore all whitespace in expressions
        case 'T_WHITESPACE':
          break;
        default:
          throw new Exception("Parse Error: Token not supported in expression: '" . $token['token'] . "' on line " . $token['line']);
          break;
      }

      if($found == false) $this->current++;
    }

    return $stack;
  }

  protected function shuntingYard($tokens) {
    $stack = new \SplStack();
    $output = new \SplQueue();

    foreach ($tokens as $token) {
      // If token is a operand
      //TODO: Change this if to a switch case statement
      if ($token['token'] == 'T_NUMBER' || $token['token'] == 'T_FLOAT' || $token['token'] == 'T_VARIABLE_RENDER' || $token['token'] == 'T_GROUPCALL'|| $token['token'] == 'T_STRING') {
        $output->enqueue($token['value']);
        // If token is an operator
      } elseif (isset(Parser::$operators[$token['value']])) {
        $o1 = $token['value'];
        while (Parser::has_operator($stack) && ($o2 = $stack->top()) && Parser::has_lower_precedence($o1, $o2)) {
          $output->enqueue($stack->pop());
        }
        $stack->push($o1);
        // If token is a left parenthesis
      } elseif ('(' === $token['value']) {
        $stack->push($token['value']);
        // If token is a right parenthesis
      } elseif (')' === $token['value']) {
        while (count($stack) > 0 && '(' !== $stack->top()) {
          $output->enqueue($stack->pop());
        }

        if (count($stack) === 0) {
          throw new \InvalidArgumentException(sprintf('Parse Error: Mismatched parenthesis in input: %s', json_encode($tokens)));
        }

        // pop off '('
        $stack->pop();
      } else {
        throw new \InvalidArgumentException(sprintf('Parse Error: Invalid token, Cannot Shunt: %s', $token['token'] . ' - ' . $token['value']));
      }
    }

    while (Parser::has_operator($stack)) {
      $output->enqueue($stack->pop());
    }

    if (count($stack) > 0) {
      throw new \InvalidArgumentException(sprintf('Mismatched parenthesis or misplaced number in input: %s', json_encode($tokens)));
    }

    return iterator_to_array($output);
  }

  protected function reverseShuntingYard($tokens) {
    $tokens = array_reverse($tokens);

    for($x = 0; $x < count($tokens); $x++) {
      if($tokens[$x]['token'] == 'T_CLOSE_BRACKET'){
        $tokens[$x]['token'] = 'T_OPEN_BRACKET';
        $tokens[$x]['value'] = '(';
      } else if($tokens[$x]['token'] == 'T_OPEN_BRACKET') {
        $tokens[$x]['token'] = 'T_CLOSE_BRACKET';
        $tokens[$x]['value'] = ')';
      }
    }
    $tokens = $this->shuntingYard($tokens);
    $tokens = array_reverse($tokens);
    return $tokens;
  }

  protected function prefixToTree($preFix) {
    //var_dump("Current Prefix");
    //var_dump($preFix);
    
    $c = array_shift($preFix);
    $this->tempPreFix = $preFix;
    //var_dump("Parse: " . $c);

    if(is_numeric($c)) {
      //var_dump('Operand');
      return array(
        "type" => "number",
        "value" => $c
      );
      return $c;
    } elseif($c[0] == '%' && $c[strlen($c)-1] == '%') {
      return array(
        'type' => 'variable',
        'value' => str_replace('%', '', $c)
      );
    } elseif($c[0] == '[' && $c[strlen($c)-1] == ']') {
      //TODO: Need a better way to create groupcall ast - filler method for now
      return array(
        "type" => "groupcall",
        "params" => array([
            "type" => "string",
            "value" => substr($c, 1, -1)
        ])
      );
    } elseif(isset(Parser::$operators[$c])) {
      //var_dump('Operator');
      //var_dump('Go Left');
      $left = $this->prefixToTree($this->tempPreFix);
      //var_dump('Go Right');
      $right = $this->prefixToTree($this->tempPreFix);
      switch($c) {
        case '=':
        case '+=':
        case '-=':
        case '*=':
        case '/=':
          $type = 'assign';
          break;
        default:
        $type = 'binary';
          break;
      }
      return array(
        "type" => $type,
        "operator" => $c,
        "left" => $left,
        "right" => $right
      );
    } elseif(is_string($c)) {
      //var_dump('Operand');
      return array(
        "type" => "variable",
        "value" => $c
      );
      return $c;
    } else {
      throw new Exception("Parse Error: Cannot convert to tree node: " . $c);
    }
  }

  protected static function has_operator(\SplStack $stack) {
    return count($stack) > 0 && ($top = $stack->top()) && isset(Parser::$operators[$top]);
  }

  function has_lower_precedence($o1, $o2) {
    $op1 = Parser::$operators[$o1];
    $op2 = Parser::$operators[$o2];
    return ('left' === $op1['associativity'] && $op1['precedence'] === $op2['precedence']) || $op1['precedence'] < $op2['precedence'];
  }

  protected static function removeEmpty($tokens){
    $tokensFormatted = array();

    foreach($tokens as $token){
      if($token['token'] != 'T_WHITESPACE'){
        $tokensFormatted[] = $token;
      }
    }
    return $tokensFormatted;
  }

  protected static function isOp($token){
    $operators = array(
      'T_MATH_ADDITION',
      'T_MATH_SUBTRACTION',
      'T_MATH_MULTIPLY',
      'T_MATH_DIVISION',
      'T_MATH_POWER',
      'T_MATH_EQUALS',
    );
    return in_array($token['token'], $operators);
  }

  protected static function getPrecedence($token){
    if(isset(Parser::$precedence[$token['value']])){
      return Parser::$precedence[$token['value']];
    } else {
      throw new Exception("Parse Error: Precedence not found for '" . $token['token'] . "' - '" . $token['value'] . "'");
    }
  }

  protected function peekPrevious($amount = 1){
    return $this->tokens[$this->current-$amount];
  }

  protected function peekNext($amount = 1){
    return $this->tokens[$this->current+$amount];
  }
}