<?php

class Tokenizer {
  protected static $_terminals_comments = array(
    "/\G(#.+)/"  => "T_LINECOMMENT",
    "/\G(\/\*)/" => "T_BLOCKCOMMENT_OPEN",
    "/\G(\*\/)/" => "T_BLOCKCOMMENT_CLOSE",
  );
  protected static $_terminals_special = array(
    "/\G([:;])/"                   => "T_GROUP_IDENTIFIER",
    "/\G(?<=^[:;])(\w+(?:\s\w+)?)/" => "T_GROUP_NAME",
    "/\G(\{)/"                     => "T_GROUP_OPEN_BRACKET",
    "/\G(\})/"                     => "T_GROUP_CLOSE_BRACKET",
    "/\G(\d+\-\d+\:)/"             => "T_GROUP_LINE_RANGE_NUMBER",
    "/\G(\d+\:)/"                  => "T_GROUP_LINE_SINGLE_NUMBER",
    "/\G(\[)/"                     => "T_GROUPCALL_OPEN_BRACKET",
    "/\G(\])/"                     => "T_GROUPCALL_CLOSE_BRACKET",
    "/\G(\()/"                     => "T_EXPRESSION_OPEN_BRACKET",
    "/\G(\))/"                     => "T_EXPRESSION_CLOSE_BRACKET",
    "/\G(\~)/"                     => "T_FUNCTION_SEPARATOR",
  );
  protected static $_terminals_math = array(
    "/\G(\+)/" => "T_MATH_ADDITION",
    "/\G(\-)/" => "T_MATH_SUBTRACTION",
    "/\G(\*)/" => "T_MATH_MULTIPLY",
    "/\G(\/)/" => "T_MATH_DIVISION",
  );
  protected static $_terminals_general = array(
    "/\G(\s*[a-zA-Z].*?)(?:[\[\]\(\)\r\n])/" => "T_STRING",
    "/\G(\r)/"                               => "T_NEWLINE",
    "/\G(\s+)/"                              => "T_WHITESPACE",
    "/\G([\.\,\;\:\?\!\'\"\-\_\/])/"         => "T_PUNCTUATION",
    "/\G([+-]?[0-9]*[.][0-9]+)/"             => "T_FLOAT",
    "/\G(\d+)/"                              => "T_NUMBER",
    "/\G(\-)/"                               => "T_DASH",
    "/\G(\,)/"                               => "T_COMMA",
    "/\G(\.)/"                               => "T_DOT",
    "/\G(?<=[\[\(])(\w+)/"                   => "T_IDENTIFIER",
  );
  protected static $_terminals = array();

  public $output;
  private $tokens;
  private $offset;

  public function __construct($source){
    static::$_terminals = array_merge(
      static::$_terminals,
      static::$_terminals_comments,
      static::$_terminals_special,
      static::$_terminals_math,
      static::$_terminals_general
    );

    foreach($source as $number => $line){
      $this->offset = 0;

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
    $this->tokens[] = array(
      'token'  => 'T_EOF',
      'value'  => '',
      'line'   => count($source) + 1,
      'offset' => 1
    );

    $this->output = $this->tokens;
  }

  protected function match($line, $number){
    $string = substr($line, $this->offset);

    foreach(static::$_terminals as $pattern => $name){
      if(preg_match($pattern, $line, $matches, 0, $this->offset)){

        return array(
          'token'  => $name,
          'value'  => $matches[1],
          'line'   => $number + 1,
          'offset' => $this->offset + 1
        );
      }
    }

    throw new Exception("Unable to tokenize line at " . ($number + 1) . "-" . ($this->offset + 1) . ". " . json_encode($string));
  }
}