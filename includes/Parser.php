<?php
class Parser {
  private $inComment = false;
  private $inExpression = false;
  private $parseOutput = false;
  private $current = false;
  private $tokens;

  private static $precedence = array(
    "="  => 1,
    "||" => 2,
    "&&" => 3,
    "<"  => 7, ">" => 7, "<=" => 7, ">=" => 7, "==" => 7, "!=" => 7,
    "+"  => 10, "-" => 10,
    "*"  => 20, "/" => 20, "%" => 20,
  );

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

  protected function parseToken(){
    $token = $this->tokens[$this->current];
    switch($token['token']){
      // Ignore
      case 'T_LINECOMMENT': // Ignore all comments
      case 'T_GROUP_NAME': // Used in group expression don't parse
      case 'T_GROUP_OPEN_BRACKET': // Used in group expression don't parse
      case 'T_GROUP_CLOSE_BRACKET': // Used in group expression don't parse
      case 'T_GROUPCALL_CLOSE_BRACKET': // Used in groupcall expression don't parse
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
        if(!$this->inComment){
          return array(
            'type'  => 'string',
            'value' => $token['value']
          );
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
        return $this->parseSubProgram('group');
        break;
      // Line expression
      case 'T_GROUP_LINE_SINGLE_NUMBER':
      case 'T_GROUP_LINE_RANGE_NUMBER':
      case 'T_GROUP_LINE_EQUAL_NUMBER':
        $this->parseOutput = true;

        return $this->parseSubProgram('line');
        break;
      // After newline no more parsing content
      case 'T_NEWLINE':
        $this->parseOutput = false;
        break;
      // Group call Expression
      case 'T_GROUPCALL_OPEN_BRACKET':
        return $this->parseSubProgram('groupcall');
        break;
      // Group call Expression
      case 'T_EXPRESSION_OPEN_BRACKET':
        // TODO: May need expression depth var for recursive expressions
        if($this->inExpression) throw new Exception("Recursive expressions not yet supported");
        $this->inExpression = true;
        $this->current++;
        return $this->parseExpression($this->parseToken(), 0);
        break;
      case 'T_EXPRESSION_CLOSE_BRACKET':
        $this->inExpression = false;
        break;
      //case 'T_MATH_ADDITION':
      //case 'T_MATH_SUBTRACTION':
      //case 'T_MATH_MULTIPLY':
      //case 'T_MATH_DIVISION':
      //  break;
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

  protected function parseSubProgram($type){
    $token = $this->tokens[$this->current];
    $delimiter = '';

    $node = array();
    $node['type'] = $type;

    switch($type){
      case 'group':
        $close = 'T_GROUP_CLOSE_BRACKET';
        $delimiter = '}';
        $node['value'] = $token['value'];
        $node['name'] = $this->tokens[$this->current + 1]['value'];
        break;
      case 'line':
        $close = 'T_NEWLINE';

        if($token['token'] == 'T_GROUP_LINE_EQUAL_NUMBER'){
          // Do nothing, there is no range to calculate
        } else if($token['token'] == 'T_GROUP_LINE_SINGLE_NUMBER'){
          $node['range'] = rtrim($token['value'], ':');
        } else {
          $range = explode('-', $token['value']);
          $node['range_min'] = rtrim($range[0], ':');
          $node['range_max'] = rtrim($range[1], ':');
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
      default:
        // Unknown expression type
        throw new Exception("Parse Error: Unknown expression '" . $type . "' at line " . $token['line'] . "-" . $token['offset']);
        break;
    }

    $node['params'] = array();

    if(isset($close)){
      while(!($token['token'] === $close)){
        if($token['token'] == 'T_EOF'){
          throw new Exception("Parse Error: Unexpected end of file, expected '" . $delimiter . "' - '" . $close . "'");
        }
        $this->current++;
        $token = $this->tokens[$this->current];
        $param = Parser::parseToken();
        if(gettype($param) !== 'boolean') $node['params'][] = $param;
      }
    }

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
        return $this->parseExpression(array(
          "type" => "binary",
          "operator" => $op_val,
          "left" => $left,
          "right" => $this->parseExpression($this->parseToken(), $op_prec)
        ), $last_prec);
      }

    }
    // Not an operator just return the left value
    return $left;
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