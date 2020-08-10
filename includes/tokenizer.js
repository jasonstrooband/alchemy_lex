class Tokenizer {
  terminals_comments = {
    T_LINECOMMENT:        /#.+\r?/,
    T_BLOCKCOMMENT_OPEN:  /\/\*/,
    T_BLOCKCOMMENT_CLOSE: /\*\//
  };

  terminals_special = {
    T_GROUP_IDENTIFIER: /^[:;|]/,
    T_GROUP_NAME: /(?<=[:;|])[a-zA-Z0-9\_\-]+/,
    T_GROUP_OPEN_BRACKET: /\{/,
    //T_GROUP_CLOSE_BRACKET: /\}/,
    //T_GROUP_LINE_RANGE_NUMBER: /\d+\-\d+\:/,
    //T_GROUP_LINE_SINGLE_NUMBER: /\d+\:/,
    //T_GROUP_LINE_EQUAL_NUMBER: /\s+\:/,
    //T_GROUPCALL_OPEN_BRACKET: /\[/,
    //T_GROUPCALL_CLOSE_BRACKET: /\]/,
    //T_FUNCTIONCALL_OPEN_BRACKET: /\</,
    //T_FUNCTIONCALL_CLOSE_BRACKET: /\>/,
    //T_EXPRESSION_OPEN_BRACKET: /\(/,
    //T_EXPRESSION_CLOSE_BRACKET: /\)/
  };
  
  terminals_general = {
    T_NEWLINE: /\r/,
    T_WHITESPACE: /\s+/,
    //T_STRING: /\b.+?(?=[\(\<\>\[\]\r\n\~])/
    //"/\G([\.\,\;\:\?\!\'\"\-\_\/\~])/" => "T_PUNCTUATION",
    //"/\G([+-]?[0-9]*[.][0-9]+)/"       => "T_FLOAT",
    //"/\G(\d+)/"                        => "T_NUMBER",
    ////"/\G(?<=[\[\(])(\w+)/"             => "T_IDENTIFIER",
    //"/\G(\b.+?(?=[\(\<\>\[\]\r\n\~]))/"    => "T_STRING",
  }

  offset = 0;
  tokens = [];

  constructor(input) {
    // Merge each of the grouped terminals
    this.terminals = {
      ...this.terminals_comments,
      ...this.terminals_special,
      ...this.terminals_general
    }

    // Merge multiline strings
    input = this.applyMultiline(input);
    // Explode into a line array
    //input = input.match(/[^\r\n]+/g);
    input = input.split(/\n/g);
    console.log(JSON.stringify(input, null /*replacer function */, 4 /* space */))

    execution: {
      for (let num = 0; num < input.length; num++) {
        this.offset = 0;
        let line = input[num];
        //console.log("Line: " + (num+1) + " - " + JSON.stringify(line));
  
        while(this.offset < line.length){
          let result = '';
          try {
              result = this.match(line, num);
          } catch(error) {
            console.log("Error: " + error);
            break execution;
          }
          this.tokens.push(result);
          console.log('Add Offset: ' + result['value'].length)
          this.offset += result['value'].length;
          console.log('Line len: ' + line.length + ' - Offset: ' + this.offset);
        }
      }
      
      this.tokens.push({
        token: 'T_EOF',
        value: '',
        line: input.length,
        offset: 1
      });
    }

    //console.log(JSON.stringify(input, null /*replacer function */, 4 /* space */))
  }

  match(line, num) {
    let str = line.substr(this.offset);
    console.log(this.offset);

    console.log('Match: ' + JSON.stringify(str));

    for (const [key, value] of Object.entries(this.terminals)) {
      let regexp = new RegExp(value, 'g');
      regexp.lastIndex = this.offset;
      let match = regexp.exec(line);

      // Having trouble getting it to match T_WHITESPACE before T_GROUP_OPEN_BRACKET

      if(match) {
        console.log(match);
        console.log('Matched: ' + key);
        let token = {
          token: key,
          value: match[0],
          line: num + 1,
          offset: this.offset + 1
        }
        return token;
      }
    }

    throw "Tokenizer Error: Unable to tokenize line at " + (num + 1) + "-" + (this.offset + 1) + ". " + JSON.stringify(str);
  }

  applyMultiline(str){
    str = str.replace(/\_(?:\r|\n)\s+/g, '');
    return str;
  }

  get tokens() {
    return this.tokens;
  }
}