class Tokenizer {
  terminals_comments = {
    T_LINECOMMENT:        /^#.+\r?/,
    T_BLOCKCOMMENT_OPEN:  /^\/\*/,
    T_BLOCKCOMMENT_CLOSE: /^\*\//
  };

  terminals_special = {
    T_GROUP_IDENTIFIER: /^[:;|]/
  };
  
  terminals_general = {
    T_NEWLINE: /^\r/,
    T_WHITESPACE: /^\s+/,
    T_STRING: /^\b.+?(?=[\(\<\>\[\]\r\n\~])/
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
    //console.log(JSON.stringify(input, null /*replacer function */, 4 /* space */))

    execution: {
      for (let num = 0; num < input.length; num++) {
        this.offset = 0;
        let line = input[num];
        console.log("Line: " + (num+1) + " - " + JSON.stringify(line));
  
        console.log(num);
  
        while(this.offset < line.length){
          let result = '';
          try {
              result = this.match(line, num);
          } catch(error) {
            console.log("Error: " + error);
            break execution;
          }
          this.tokens.push(result);
          this.offset += result['value'].length;
        }
      }
    }

    //console.log(JSON.stringify(input, null /*replacer function */, 4 /* space */))
  }

  match(line, num) {
    let str = line.substr(this.offset);

    for (const [key, value] of Object.entries(this.terminals)) {
      let match = str.match(value);

      if(match) {
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