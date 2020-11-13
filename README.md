# Alchemy Lex

A lexical tool to take a premade script and output some randomness based on that script

## Description

Alchemy Lex is a lexical analyser that takes a predefined script and pours over it to configure the data ina a way that can be evaluated for a specific and random putput. This tool is designed in a way to be lightweight and powerful. A sister project will be underway shortly with a website that fully implements this project in a user friendly way.

## Getting Started

### Dependencies

* Must use a local (or remote) web server to run the tool e.g. wamp. This is to run the php still included (I hope to remove this need sometime soon).

### Installing

* Place the files inside the document root of your web server
* No modifications should be necessary

### Executing program

* Access the localhost of your web server and navigate to the sub folder for this project
* Select a script from the dropdown and hit go

>See [Wiki](https://github.com/jasonstrooband/alchemy_lex/wiki)  for more information on current features, roadmap or references.<br>
>The wiki pages may be out of date because of recent changes - expect updates soon.

## Author

Me, Jason S

## Version History

### V2a

`Under Development`

### V1a

The first alpha release and a proof of concept of some basic functionality

  - [Comments](https://github.com/jasonstrooband/alchemy_lex/wiki/Features-and-Roadmap#Comments)
	 - Line Comments
	 - Block Comments
 - [Basic Group Calls](https://github.com/jasonstrooband/alchemy_lex/wiki/Features-and-Roadmap#Group-Calls)
 - [Group Headings](https://github.com/jasonstrooband/alchemy_lex/wiki/Features-and-Roadmap#Groups)
	 - Sequential Group
	 - Probability Group
 - [Lines Roll](https://github.com/jasonstrooband/alchemy_lex/wiki/Features-and-Roadmap#Group-Entries)
	 - Equal Share
	 - Single Share
	 - Range Share
 - [Multiline](https://github.com/jasonstrooband/alchemy_lex/wiki/Features-and-Roadmap#Group-Entries)
 - [Maths Expressions with order of precedence](https://github.com/jasonstrooband/alchemy_lex/wiki/Features-and-Roadmap#Expressions)
	 - Addition
	 - Subtraction
	 - Multiplication
	 - Division
 - [Sample Functions](https://github.com/jasonstrooband/alchemy_lex/wiki/Features-and-Roadmap#Functions-Feature)
	 - Syntax:<x~param>
	 - Cap
	 - Dice

## License

This project is licensed under the GNU GPLv3 License

## Acknowledgments

This application has been heavily inspired by a nifty little tool called [Tablesmith](http://www.mythosa.net/p/tablesmith.html) by Bruce Gulke it's definately worth checking it out.