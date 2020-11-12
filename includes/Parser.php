<?php
class Parser {
  private $inComment = false;
  private $inExpression = false;
  private $parseOutput = false;
  private $current = false;
  private $tokens;

  // Old system
  private static $precedence = array(
    "="  => 1,
    "||" => 2,
    "&&" => 3,
    "<"  => 7, ">" => 7, "<=" => 7, ">=" => 7, "==" => 7, "!=" => 7,
    "+"  => 10, "-" => 10,
    "*"  => 20, "/" => 20, "%" => 20,
  );

  private static $operators = [
    '+' => ['precedence' => 0, 'associativity' => 'left'],
    '-' => ['precedence' => 0, 'associativity' => 'left'],
    '*' => ['precedence' => 1, 'associativity' => 'left'],
    '/' => ['precedence' => 1, 'associativity' => 'left'],
    '%' => ['precedence' => 1, 'associativity' => 'left'],
    '^' => ['precedence' => 2, 'associativity' => 'right'],
  ];

  public function __construct($tokens){
    //$this->tokens = static::removeEmpty($tokens);
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
      //Math tokens not inside an expression should bre treated like a string
      case 'T_MATH_ADDITION':
      case 'T_MATH_SUBTRACTION':
      case 'T_MATH_MULTIPLY':
      case 'T_MATH_DIVISION':
      case 'T_MATH_POWER':
      case 'T_MATH_EQUALS':
        $strCheck = '""' == substr($token['value'], 0, 1) . substr($token['value'], -1, 1);
        if($this->inExpression && !$strCheck){
          return array(
            'type'  => 'variable',
            'value' => $token['value']
          );
        } else {
          if(!$this->inComment){
            return array(
              'type'  => 'string',
              'value' => $token['value']
            );
          }
        }
        break;
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
      case 'T_EXPRESSION_OPEN_BRACKET':
        // TODO: May need expression depth var for recursive expressions
        if($this->inExpression) throw new Exception("Recursive expressions not yet supported");
        $this->inExpression = true;
        $this->current++;

        $rpn = $this->shuntingYard();

        //var_dump(implode(array_reverse($rpn)));
        
        return array(
          'type'  => 'expression',
          //'params' => $this->parseExpression($this->parseToken(), 0)
          'params' => null
        );
        break;
      case 'T_EXPRESSION_CLOSE_BRACKET':
        $this->inExpression = false;
        break;
      // Function call Expression
      case 'T_FUNCTIONCALL':
        return $this->parseEnclosedSubProgram('functioncall');
        break;
      // Function call Expression
      // Depracated
      case 'T_FUNCTIONCALL_OPEN_BRACKET':
        return $this->parseDelimSubProgram('functioncall');
        break;
      case 'T_VARIABLE_DECLARE':
        $varArray = explode(',', str_replace('%', '', $token['value']));
        return array(
          'type'  => 'declare_var',
          'params' => array(
            'name' => $varArray[0],
            'value' => $varArray[1]
          )
        );
        break;
      case 'T_VARIABLE_RENDER':
        return array(
          'type' => 'variable',
          'value' => str_replace('%', '', $token['value'])
        );
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
      case 'functioncall':
        // Strip surrounding brackets
        $value = substr($token['value'], 1, -1);
        $value = preg_split('@~@', $value, NULL, PREG_SPLIT_NO_EMPTY);
        $node['value'] = $value[0];
        if(count($value) != 1) {
          $params = preg_split('@,@', $value[1], NULL, PREG_SPLIT_NO_EMPTY);
          $paramsNodes = array();
          for($x = 0; $x < count($params); $x++){
            $paramsNodes[] = $params[$x];
          }
          $node['params'] = $paramsNodes;
        } else {
          $node['params'] = array();
        }
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
      case 'expression':
        $close = 'T_EXPRESSION_CLOSE_BRACKET';
        $delimiter = ')';
        break;
      case 'functioncall':
        $close = 'T_FUNCTIONCALL_CLOSE_BRACKET';
        $delimiter = '>';
        break;
      default:
        // Unknown expression type
        throw new Exception("Parse Error: Unknown expression '" . $type . "' at line " . $token['line'] . "-" . $token['offset']);
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

    return $node;
  }

  protected function parseExpression($left, $last_prec) {
    // Peek for next token
    $next_token = $this->peekNext();

    // If the next token is an operator
    if($this->isOp($next_token)){

      // Get the operator precedence for the next token and the token value
      $op_prec = $this->getPrecedence($next_token);
      $op_val = $next_token['value'];

      // If the next operator precedence is higher than the last precedence
      if($op_prec > $last_prec){
        // Advance to the next token
        $this->current += 2;

        // Construct the recursive binary tree
        switch($op_val){
          case '=':
            $expressionType = 'assign';
            break;
          case '+':
          case '-':
          case '*':
          case '/':
            $expressionType = 'binary';
            break;
          default:
            throw new Exception("Parse Error: Unknown operator '" . $op_val . "'");
            break;
        }
        
        return $this->parseExpression(array(
          "type" => $expressionType,
          "operator" => $op_val,
          "left" => $left,
          "right" => $this->parseExpression($this->parseToken(), $op_prec)
        ), $last_prec);
      }

    }
    // Not an operator just return the left value
    return $left;
  }

  protected function shuntingYard() {
    $tokens = $this->findAllTokensInExpression();
    $stack = new \SplStack();
    $output = new \SplQueue();

    foreach ($tokens as $token) {
      if (is_numeric($token)) {
        $output->enqueue($token);
      } elseif (isset(Parser::$operators[$token])) {
        $o1 = $token;
        while (Parser::has_operator($stack) && ($o2 = $stack->top()) && Parser::has_lower_precedence($o1, $o2)) {
          $output->enqueue($stack->pop());
        }
        $stack->push($o1);
      } elseif ('(' === $token) {
        $stack->push($token);
      } elseif (')' === $token) {
        while (count($stack) > 0 && '(' !== $stack->top()) {
          $output->enqueue($stack->pop());
        }

        if (count($stack) === 0) {
          throw new \InvalidArgumentException(sprintf('Mismatched parenthesis in input: %s', json_encode($tokens)));
        }

        // pop off '('
        $stack->pop();
      } else {
        throw new \InvalidArgumentException(sprintf('Invalid token: %s', $token));
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

  protected function findAllTokensInExpression() {
    $found = false;
    $depth = 0;
    $stack = array();

    while($found == false) {
      $token = $this->tokens[$this->current];

      switch($token['token']) {
        case 'T_NUMBER':
        case 'T_MATH_ADDITION':
        case 'T_MATH_SUBTRACTION':
        case 'T_MATH_MULTIPLY':
        case 'T_MATH_DIVISION':
        case 'T_MATH_POWER':
          $stack[] = $token['value'];
          break;
        case 'T_EXPRESSION_OPEN_BRACKET':
          $stack[] = $token['value'];
          $depth++;
          break;
        case 'T_EXPRESSION_CLOSE_BRACKET':
          $stack[] = $token['value'];
          $depth--;
          if($depth < 0) $found = true;
          break;
        // Ignore all whitespace in expressions
        case 'T_WHITESPACE':
          break;
        default:
          throw new Exception("Parse Error: Token not supported in expression: '" . $token['token'] . "'");
          break;
      }

      $this->current++;
    }

    array_pop($stack);

    return $stack;
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