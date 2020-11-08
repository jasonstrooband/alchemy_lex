<?php

class Tokenizer {
  protected static $_terminals_comments = array(
    "/\G(#.*?)(?=\r|\n|\r\n)/"  => "T_LINECOMMENT",
    "/\G(\/\*)/"                => "T_BLOCKCOMMENT_OPEN",
    "/\G(\*\/)/"                => "T_BLOCKCOMMENT_CLOSE",
  );
  protected static $_terminals_special = array(
    "/\G([:;|])/"                    => "T_GROUP_IDENTIFIER",
    "/\G(?<=^[:;|])(\w+(?:\h\w+)?)/" => "T_GROUP_NAME",
    "/\G(\d+\-\d+\,)/"               => "T_GROUP_LINE_RANGE_NUMBER",
    "/\G(\d+\,)/"                    => "T_GROUP_LINE_SINGLE_NUMBER",
    "/\G(\h+\,)/"                    => "T_GROUP_LINE_EQUAL_NUMBER",
    "/\G(\[.*?\])/"                  => "T_GROUPCALL",
    //"/\G(\[)/"                     => "T_GROUPCALL_OPEN_BRACKET",
    //"/\G(\])/"                     => "T_GROUPCALL_CLOSE_BRACKET",
    "/\G(\{.*?\})/"                  => "T_FUNCTIONCALL",
    //"/\G(\<)/"                     => "T_FUNCTIONCALL_OPEN_BRACKET",
    //"/\G(\>)/"                     => "T_FUNCTIONCALL_CLOSE_BRACKET",
    //"/\G(\(.*?\))/"                => "T_EXPRESSION",
    "/\G(\()/"                       => "T_EXPRESSION_OPEN_BRACKET",
    "/\G(\))/"                       => "T_EXPRESSION_CLOSE_BRACKET",
  );
  protected static $_terminals_variables = array(
    "/\G(\%[a-zA-Z0-9_]+\%\,.*?)(?=\r|\n|\r\n)/"      => "T_VARIABLE_DECLARE",
    "/\G(\%[a-zA-Z0-9_]+\%)/"          => "T_VARIABLE_RENDER",
  );
  protected static $_terminals_math = array(
    "/\G(\+)/" => "T_MATH_ADDITION",
    "/\G(\-)/" => "T_MATH_SUBTRACTION",
    "/\G(\*)/" => "T_MATH_MULTIPLY",
    "/\G(\/)/" => "T_MATH_DIVISION",
    "/\G(\^)/" => "T_MATH_POWER",
    "/\G(\=)/" => "T_MATH_EQUALS",
  );
  protected static $_terminals_general = array(
    "/\G((?:[a-zA-Z\'\"]+\_*\d*\h*)+)/"    => "T_STRING",
    "/\G(\r|\n|\r\n)/"                     => "T_NEWLINE",
    "/\G(\h+)/"                            => "T_WHITESPACE",
    "/\G([\.\,\;\:\?\!\'\"\-\_\/\~])/"     => "T_PUNCTUATION",
    "/\G([+-]?[0-9]*[.][0-9]+)/"           => "T_FLOAT",
    "/\G(\d+)/"                            => "T_NUMBER",
    //"/\G(?<=[\[\(])(\w+)/"               => "T_IDENTIFIER",
    //"/\G(\b[a-zA-Z0-9\s]+\b)/"           => "T_STRING",
    //"/\G(\b.+?(?=[\(<[]))/"              => "T_STRING",
    //"/\G(\b.+?(?=[\(\<\>\[\]\r\n\~]))/"  => "T_STRING",
  );
  protected static $_terminals = array();

  public $output;
  private $tokens;
  private $offset;

  public function __construct($source){
    static::$_terminals = array_merge(
      static::$_terminals,
      static::$_terminals_comments,
      static::$_terminals_variables,
      static::$_terminals_special,
      static::$_terminals_math,
      static::$_terminals_general
    );

    //var_dump(json_encode($source));

    $source = $this->applyMultiline($source);

    // Separate the lines into an array
    $source = explode("\n", $source);

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

    throw new Exception("Tokenizer Error: Unable to tokenize line at " . ($number + 1) . "-" . ($this->offset + 1) . ". " . json_encode($string));
  }

  protected function applyMultiline($str){
    //echo "<pre>";
    //print_r(json_encode($str));
    //echo "</pre>";

    $str = preg_replace('/\_(?:\r|\n)\s+/', '', $str);

    //echo "<pre>";
    //print_r(json_encode($str));
    //echo "</pre>";

    return $str;
  }
}