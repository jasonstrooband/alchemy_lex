<?php

class Tokenizer {
  // Comment Tokens
  protected static $_terminals_comments = array(
    "/\G(#.*?)(?=\r|\n|\r\n)/"  => "T_LINECOMMENT",
    // TODO update to have a complete encapsulating token
    "/\G(\/\*)/"                => "T_BLOCKCOMMENT_OPEN",
    "/\G(\*\/)/"                => "T_BLOCKCOMMENT_CLOSE",
  );
  // Other unrelated tokens
  protected static $_terminals_other = array(
    "/\G(?>(?<=^)|(?<=\n))(\@.*?)(?=\r|\n|\r\n)/"  => "T_PARAMETER",
    "/\G(?>(?<=^)|(?<=\n))(\/.*?)(?=\r|\n|\r\n)/"  => "T_OVERRIDE",
    "/\G(\<\/?[a-zA-Z0-9]+(?>\s\/)?\>)/"           => "T_HTML",
    "/\G(\<.*\=.*?\>)/"                            => "T_HTML_UNSUPPORTED",
  );
  // Group, Lines, Group Call and Function Call tokens
  protected static $_terminals_special = array(
    "/\G(?>(?<=^)|(?<=\n))([:;|])/"  => "T_GROUP_IDENTIFIER",
    "/\G(?<=^[:;|])(\w+(?:\h\w+)?)/" => "T_GROUP_NAME",
    "/\G(\d+\-\d+\,)/"               => "T_GROUP_LINE_RANGE_NUMBER",
    "/\G(\d+\,)/"                    => "T_GROUP_LINE_SINGLE_NUMBER",
    "/\G(\h+\,)/"                    => "T_GROUP_LINE_EQUAL_NUMBER",
    "/\G(\[.*?\])/"                  => "T_GROUPCALL",
    //"/\G(\[)/"                     => "T_GROUPCALL_OPEN_BRACKET",
    //"/\G(\])/"                     => "T_GROUPCALL_CLOSE_BRACKET",
    "/\G(\{)/"                       => "T_FUNCTIONCALL_OPEN_BRACKET",
    "/\G(\})/"                       => "T_FUNCTIONCALL_CLOSE_BRACKET",
    "/\G(\|)/"                       => "T_EXPRESSION_BOUNDARY",
  );
  // Variable tokens
  protected static $_terminals_variables = array(
    "/\G(?>(?<=^)|(?<=\n))(\%[a-zA-Z0-9_]+\%\,)/"  => "T_VARIABLE_DECLARE",
    "/\G(\%[a-zA-Z0-9_]+\%)/"                      => "T_VARIABLE_RENDER",
  );
  // Maths tokens
  protected static $_terminals_math = array(
    "/\G(\+\=)/" => "T_MATH_ADDITION_EQUALS",
    "/\G(\-\=)/" => "T_MATH_SUBTRACTION_EQUALS",
    "/\G(\*\=)/" => "T_MATH_MULTIPLY_EQUALS",
    "/\G(\/\=)/" => "T_MATH_DIVISION_EQUALS",
    "/\G(\+)/"   => "T_MATH_ADDITION",
    "/\G(\-)/"   => "T_MATH_SUBTRACTION",
    "/\G(\*)/"   => "T_MATH_MULTIPLY",
    "/\G(\/)/"   => "T_MATH_DIVISION",
    "/\G(\^)/"   => "T_MATH_POWER",
    "/\G(\=)/"   => "T_MATH_EQUALS",
  );
  //Output tokens - can be context specific
  protected static $_terminals_general = array(
    "/\G((?:[a-zA-Z\'\"]+\_*\d*\h*)+)/"    => "T_STRING",
    "/\G(\r|\n|\r\n)/"                     => "T_NEWLINE",
    "/\G(\h+)/"                            => "T_WHITESPACE",
    "/\G([\.\,\;\:\?\!\'\"\-\_\/\~])/"     => "T_PUNCTUATION",
    "/\G([+-]?[0-9]*[.][0-9]+)/"           => "T_FLOAT",
    "/\G(\d+)/"                            => "T_NUMBER",
    "/\G(\()/"                             => "T_OPEN_BRACKET",
    "/\G(\))/"                             => "T_CLOSE_BRACKET",
  );

  // Declare Variables
  protected static $_terminals = array();
  public $output;
  private $tokens;
  private $offset;

  public function __construct($source){
    // Merge token groups into one
    static::$_terminals = array_merge(
      static::$_terminals,
      static::$_terminals_comments,
      static::$_terminals_other,
      static::$_terminals_variables,
      static::$_terminals_special,
      static::$_terminals_math,
      static::$_terminals_general
    );

    // Remove all mutiline into single lines
    $source = $this->applyMultiline($source);

    // Separate the lines into an array
    $source = explode("\n", $source);

    // For every line in the script
    foreach($source as $number => $line){
      $this->offset = 0;

      // Match the next characters to a token and then update the offset
      while($this->offset < strlen($line)){
        try {
          $result = $this->match($line, $number);
        } catch(Exception $e) {
          print_error($e->getMessage());
          break;
        }
        $this->tokens[] = $result;
        $this->offset += strlen($result['value']);
      }
    }

    // Add a T_EOF to represent the end of the file
    $this->tokens[] = array(
      'token'  => 'T_EOF',
      'value'  => '',
      'line'   => count($source) + 1,
      'offset' => 1
    );

    $this->output = $this->tokens;
  }

  // Match the remaining characters on a line to a token
  protected function match($line, $number){
    $string = substr($line, $this->offset);

    foreach(static::$_terminals as $pattern => $name){
      if(preg_match($pattern, $line, $matches, 0, $this->offset)){

        // If there is two newlines in a row the the second newline becomes a double newline token
        if($name == 'T_NEWLINE') {
          $lastToken = $this->tokens[count($this->tokens)-1];

          if($lastToken['token'] == 'T_NEWLINE') {
            $name = 'T_DOUBLE_NEWLINE';
          }
        }

        return array(
          'token'  => $name,
          'value'  => $matches[1],
          'line'   => $number + 1,
          'offset' => $this->offset + 1
        );
      }
    }

    // If no match was made then there is an unexpected set of characters
    throw new Exception("Tokenizer Error: Unable to tokenize line at " . ($number + 1) . "-" . ($this->offset + 1) . ". " . json_encode($string));
  }

  // Remove multiline character and form single lines in place
  protected function applyMultiline($str){
    $str = preg_replace('/(?:\r|\n)\s+\_/', '', $str);
    return $str;
  }
}