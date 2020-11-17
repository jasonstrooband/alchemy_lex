<?php
class Parser {
  // Declare vars
  private $inComment = false;
  private $inExpression = false;
  private $parseOutput = false;
  private $current = false;
  private $tokens;
  private $tempPreFix;

  private $parameterWarn;
  private $overrideWarn;
  private $htmlWarn;
  
  // Operator precedence
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

    // Create ast base
    $ast = array(
      'type' => "program",
      'body' => array(),
    );
    
    // While there are tokens parseToken()
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

  // The main function for parsing a token
  protected function parseToken($setToken = null){

    // If a token was sent with the function then parse that token else the next token in the stream
    if($setToken != null) {
      $token = $setToken;
    } else {
      $token = $this->tokens[$this->current];
    }

    // What it the token
    switch($token['token']){
      // Ignore these tokens they do not need to be parsed - used elsewhere as context
      case 'T_LINECOMMENT': // Ignore all comments
      case 'T_GROUP_NAME': // Used in group expression don't parse
      case 'T_DOUBLE_NEWLINE':
      case 'T_GROUP_CLOSE_BRACKET': // Used in group expression don't parse
      case 'T_GROUPCALL_CLOSE_BRACKET': // Used in groupcall expression don't parse
      case 'T_FUNCTIONCALL_CLOSE_BRACKET': // Used in functioncall expression don't parse
        break;
      // Open comment block ignore all else till close block
      // TODO: Change token so as to not need inComment var
      case 'T_BLOCKCOMMENT_OPEN':
        $this->inComment = true;
        break;
      // Close the comment block
      case 'T_BLOCKCOMMENT_CLOSE':
        $this->inComment = false;
        break;
      // If not in a comment and parseOutput is true return the AST
      //Math tokens not inside an expression should bre treated like a string
      case 'T_COMMA':
      case 'T_STRING':
      case 'T_BRACKETS':
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
      // Numbers or floats return AST
      case 'T_NUMBER':
      case 'T_FLOAT':
        if(!$this->inComment){
          return array(
            'type'  => 'number',
            'value' => $token['value']
          );
        }
        break;
      // Group Sub Program
      case 'T_GROUP_IDENTIFIER':
        return $this->parseDelimSubProgram('group');
        break;
      // Line Sub Program
      case 'T_GROUP_LINE_SINGLE_NUMBER':
      case 'T_GROUP_LINE_RANGE_NUMBER':
      case 'T_GROUP_LINE_EQUAL_NUMBER':
        $this->parseOutput = true;
        return $this->parseDelimSubProgram('line');
        break;
      // Don't output outside of lines (unless told to)
      case 'T_NEWLINE':
        $this->parseOutput = false;
        break;
      // Group Call Sub Program
      // TODO: Group Call can have modifiers will need to change from enclosed to delim Sub Program
      case 'T_GROUPCALL':
        return $this->parseEnclosedSubProgram('groupcall');
        break;
      // Group call Sub Program - not used yet
      case 'T_GROUPCALL_OPEN_BRACKET':
        return $this->parseDelimSubProgram('groupcall');
        break;
      // Expression Sub Program - Variable manipulation and calculation
      case 'T_EXPRESSION_BOUNDARY':
        //TODO: Add error checking to check for invalid expressions like double binary symbols
        $this->inExpression = true;
        $this->current++;

        // Get all the tokens used in the expression
        $expressionTokens = $this->findAllTokensInExpression();
        // Convert to precedence format
        $postFix = $this->shuntingYard($expressionTokens);
        $preFix = $this->reverseShuntingYard($expressionTokens);
        // Create a tree from the PreFix format
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
      // Function call Sub Program
      case 'T_FUNCTIONCALL_OPEN_BRACKET':
        // Get the raw AST
        $functionASTRaw = $this->parseDelimSubProgram('functioncall');

        // Remove all params with a value of comma
        $functionASTRaw['params'] = array_filter( $functionASTRaw['params'], function($v) {
          if(!isset($v['value'])) return true;
          return $v['value'] != ',';
        });

        // Format into the correct array layout
        $functionASTFormatted =  array(
          'type'  => 'functioncall',
          'value'  => array_shift($functionASTRaw['params'])['value']
        );
        array_shift($functionASTRaw['params']);
        $functionASTFormatted['params'] = $functionASTRaw['params'];

        return $functionASTFormatted;
        break;
      // Declare a variable
      case 'T_VARIABLE_DECLARE':
        // Format the variable name
        $varArray = explode(',', str_replace('%', '', $token['value']));
        // Parse declare_var sub program to parse tokens following the declare var until the end of the line
        return array(
          'type'  => 'declare_var',
          'params' => array(
            'name' => $varArray[0],
            'value' => $this->parseDelimSubProgram('declare_var')
          )
        );
        break;
      // Returns a variable - doesn't store a value in parsing just a name
      case 'T_VARIABLE_RENDER':
        return array(
          'type' => 'variable',
          'value' => str_replace('%', '', $token['value'])
        );
        break;
      // Following tokens are unsupported and produce a warning or an error
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

  // Parse a completely enclosed token Sub Program
  // TODO: Remove when Group Call is moved to parseDelimSubProgram()
  protected function parseEnclosedSubProgram($type){
    // Get current toke
    $token = $this->tokens[$this->current];

    //Declare vars
    $node = array();
    $node['type'] = $type;

    switch($type){
      // Format Group Call AST
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
        // Unknown Sub Program Type
        throw new Exception("Parse Error: Unknown expression '" . $type . "' at line " . $token['line'] . "-" . $token['offset']);
        break;
    }

    return $node;
  }

  // Parse a sub program that has a start and an end token
  protected function parseDelimSubProgram($type){
    // Get current token
    $token = $this->tokens[$this->current];

    //Declare vars
    $close = '';
    $delimiter = '';
    $node = array();
    $node['type'] = $type;

    // Store when the Sub Program began
    $open = $token['line'] . "-" . $token['offset'];

    switch($type){
      case 'group':
        $close = 'T_DOUBLE_NEWLINE';
        $delimiter = '\r\r';
        $node['value'] = $token['value'];
        // Group name is in the next token - Add it to the node
        $node['name'] = $this->tokens[$this->current + 1]['value'];
        break;
      case 'line':
        $close = 'T_NEWLINE';

        // Calculate the ranges for the lines
        if($token['token'] == 'T_GROUP_LINE_EQUAL_NUMBER'){
          // Equal number group type doesn't have any range to calculate
        } else if($token['token'] == 'T_GROUP_LINE_SINGLE_NUMBER'){
          // Range is a single number just trim off the comma
          $node['range'] = rtrim($token['value'], ',');
        } else {
          // Range is a min and max number split by - and trim off the comma
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
        // Output in a variable declaration is parsed
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

    //Parse Sub Program parameters
    if(isset($close)){
      // While the current token does not match the close token
      while(!($token['token'] === $close)){
        // If it is the end of the file throw an error unless close is newline or double newline because some Sub Programs can end at EOF
        if($token['token'] == 'T_EOF'){
          if($close == 'T_NEWLINE' || $close == 'T_DOUBLE_NEWLINE') {
            return $node;
          }
          throw new Exception("Parse Error: Unexpected end of file, expected '" . $delimiter . "' - '" . $close . "' - Start at: " . $open);
        }
        // Advance the token to the next one - if it was the last token set current back to the last one
        $this->current++;
        if($this->current > count($this->tokens)-1) $this->current = count($this->tokens) - 1;
        $token = $this->tokens[$this->current];

        // Parse the current token as a parameter
        $param = Parser::parseToken();
        // If the parameter is not a boolean add to the params array
        if(gettype($param) !== 'boolean') $node['params'][] = $param;
      }
      $open = '';
    }
    
    // Reset vars
    $close = '';
    $delimiter = '';

    // if the Sub Program is declare_var stop parseOutput and return the node params only
    if($type == 'declare_var') {
      $this->parseOutput = false;
      return $node['params'];
    }

    return $node;
  }

  // Finds all the tokens in an expression until the expression boundary token
  protected function findAllTokensInExpression() {
    // Declare Vars
    $found = false;
    $stack = array();

    // While the expression boundary has not been found
    while($found == false) {
      // Get the current token
      $token = $this->tokens[$this->current];

      switch($token['token']) {
        // Tokens allowed in expression
        // TODO: Convert to an array of allowed tokens for efficiency
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
        // Found the end
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

      // Only advance the current token if the expression boundary has not been found
      if($found == false) $this->current++;
    }

    return $stack;
  }

  // Convert a list of token into a PostFix maths expression
  protected function shuntingYard($tokens) {
    // Declar Vars
    $stack = new \SplStack();
    $output = new \SplQueue();

    foreach ($tokens as $token) {
      // If token is an operand add it to the output stack
      //TODO: Change this if to a switch case statement
      if ($token['token'] == 'T_NUMBER' || $token['token'] == 'T_FLOAT' || $token['token'] == 'T_VARIABLE_RENDER' || $token['token'] == 'T_GROUPCALL'|| $token['token'] == 'T_STRING') {
        $output->enqueue($token['value']);
        // If token is an operator
      } elseif (isset(Parser::$operators[$token['value']])) {
        // Store the token for comparison
        $o1 = $token['value'];
        // While the op stack has an operator the top of the op stack has a lower precedence add the last of the op stack to the output stack
        while (Parser::has_operator($stack) && ($o2 = $stack->top()) && Parser::has_lower_precedence($o1, $o2)) {
          $output->enqueue($stack->pop());
        }
        // Add the stored operator to the op stack
        $stack->push($o1);
        // If token is a left parenthesis add it to the op stack
      } elseif ('(' === $token['value']) {
        $stack->push($token['value']);
        // If token is a right parenthesis
      } elseif (')' === $token['value']) {
        // While the op stack has operators && op stack top is not a left parenthesis add the last of the op stack to the output stack
        while (count($stack) > 0 && '(' !== $stack->top()) {
          $output->enqueue($stack->pop());
        }

        // If there is anything left in the op stack throw an error for mismatched parenthesis
        if (count($stack) === 0) {
          throw new \InvalidArgumentException(sprintf('Parse Error: Mismatched parenthesis in input: %s', json_encode($tokens)));
        }

        // Pop off '('
        $stack->pop();
      } else {
        // Throw an error because no token was matched
        throw new \InvalidArgumentException(sprintf('Parse Error: Invalid token, Cannot Shunt: %s', $token['token'] . ' - ' . $token['value']));
      }
    }

    // Add any remaining operators to the output stack
    while (Parser::has_operator($stack)) {
      $output->enqueue($stack->pop());
    }

    // If there is anything left in the op stack something went really wrong
    if (count($stack) > 0) {
      throw new \InvalidArgumentException(sprintf('Mismatched parenthesis or misplaced number in input: %s', json_encode($tokens)));
    }

    // Convert to normal array and return the output
    return iterator_to_array($output);
  }

  // Convert to PreFix maths expression
  protected function reverseShuntingYard($tokens) {
    // Reverse the tokens
    $tokens = array_reverse($tokens);

    // For each token flip close bracket to open bracket and open bracket to close bracket
    for($x = 0; $x < count($tokens); $x++) {
      if($tokens[$x]['token'] == 'T_CLOSE_BRACKET'){
        $tokens[$x]['token'] = 'T_OPEN_BRACKET';
        $tokens[$x]['value'] = '(';
      } else if($tokens[$x]['token'] == 'T_OPEN_BRACKET') {
        $tokens[$x]['token'] = 'T_CLOSE_BRACKET';
        $tokens[$x]['value'] = ')';
      }
    }

    // Convert to PostFix expression
    $tokens = $this->shuntingYard($tokens);
    // Reverse PostFix
    $tokens = array_reverse($tokens);
    return $tokens;
  }

  // COnvert PreFix expression to a tree of nodes
  protected function prefixToTree($preFix) {
    // Take off the first element
    $c = array_shift($preFix);
    // Store the prefix in a global variable
    $this->tempPreFix = $preFix;


    if(is_numeric($c)) {
      // If the element is a number return a number leaf
      return array(
        "type" => "number",
        "value" => $c
      );
      return $c;
    } elseif($c[0] == '%' && $c[strlen($c)-1] == '%') {
      // If the element is a variable render return a variable leaf
      return array(
        'type' => 'variable',
        'value' => str_replace('%', '', $c)
      );
    } elseif($c[0] == '[' && $c[strlen($c)-1] == ']') {
      // If the element is a gorup call return a groupcall leaf
      //TODO: Need a better way to create groupcall ast - filler method for now
      return array(
        "type" => "groupcall",
        "params" => array([
            "type" => "string",
            "value" => substr($c, 1, -1)
        ])
      );
    } elseif(isset(Parser::$operators[$c])) {
      // If the element is an operator
      // Recursively find left element with the stored PreFix
      $left = $this->prefixToTree($this->tempPreFix);
      // Recursively right left element with the stored PreFix
      $right = $this->prefixToTree($this->tempPreFix);

      switch($c) {
        // If the element is an assignment operator set the type to assign else binary
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

      // Return the node
      return array(
        "type" => $type,
        "operator" => $c,
        "left" => $left,
        "right" => $right
      );
    } elseif(is_string($c)) {
      // If the element is a string treat it as a variable and return the leaf
      // TODO: Need a way to determine that it is on the left of an assign operator else treat as an actual string
      return array(
        "type" => "variable",
        "value" => $c
      );
      return $c;
    } else {
      // Throw error element is unknown or not allowed
      throw new Exception("Parse Error: Cannot convert to tree node: " . $c);
    }
  }

  // Does the op stack have anymore operators
  protected static function has_operator(\SplStack $stack) {
    return count($stack) > 0 && ($top = $stack->top()) && isset(Parser::$operators[$top]);
  }

  // Is the precedence of o1 lower than o2
  function has_lower_precedence($o1, $o2) {
    $op1 = Parser::$operators[$o1];
    $op2 = Parser::$operators[$o2];
    return ('left' === $op1['associativity'] && $op1['precedence'] === $op2['precedence']) || $op1['precedence'] < $op2['precedence'];
  }

  // Remove all empty token types - T_WHITESPACE
  protected static function removeEmpty($tokens){
    $tokensFormatted = array();

    foreach($tokens as $token){
      if($token['token'] != 'T_WHITESPACE'){
        $tokensFormatted[] = $token;
      }
    }
    return $tokensFormatted;
  }

  // Return the previous token
  protected function peekPrevious($amount = 1){
    return $this->tokens[$this->current-$amount];
  }

  // Return the next token
  protected function peekNext($amount = 1){
    return $this->tokens[$this->current+$amount];
  }
}