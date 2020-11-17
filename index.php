<?php
  // TODO: Add error checking for equal share group type
  // TODO: Add error check for lines without a number range or single and not equal share group
  // TODO: Add error check for multiline to see if the next line is an output line
  // TODO: Add error checking for strings outside of groups
  // TODO: Add error checking for expressions outside of groups

  ini_set('xdebug.var_display_max_depth', -1);
  ini_set('xdebug.var_display_max_children', -1);

  $script_names = array_slice(scandir("./scripts"), 2);
  $scripts = getScripts();
  $status = array();

  // Included php classes
  require_once './includes/Tokenizer.php';
  require_once './includes/Parser.php';
  require_once './includes/Emitter.php';
  require_once './includes/Functions.php';

  //Displays an error message at the top of the pages - is called by Tokenizer, Parser and Emitter
  function print_error($msg){
    echo "<div style=background-color:red;color:white;font-size:26px;padding:10px;margin:10px;>";
    print_r(htmlspecialchars($msg));
    echo "</div>";
  }

  // Adds a message to the status stack
  function add_status($msg){
    global $status;
    $status[] = $msg;
  }

  // Print all the status messages to the top of the screen
  function print_status(){
    global $status;
    if(count($status)){
      echo "<div id=status style='background-color:lightgrey;color:#333;font-size:12px;padding:5px;margin:10px 0px;font-family:verdana;font-style:italic;'>";
      echo "<p style='font-size:16px;font-weight:bold;margin:5px 0 10px 0;'>Status</p>";
      print_r(implode('<br /><hr />', $status));
      echo "</div>";
    }
  }

  // Prints out a debug variable using print_r() function
  function pr($arr){
    print "<pre>";
    print_r($arr);
    print "</pre>";
  }

  // Prints out a debug variable using var_dump() function
  function vd($arr){
    print "<pre>";
    var_dump($arr);
    print "</pre>";
  }

  //Returns all the scripts in the scripts directory as a sorted list of filenames
  function getScripts(){
    $dir = "./scripts";
    $listings = array_slice(scandir($dir), 2);
    $listingsSorted = array();

    // Prevent empty elements
    if (count($listings) < 1) return 0;

    for($x = 0; $x < count($listings); $x++){
      if(is_dir($dir.'/'.$listings[$x])){
        $listingsSorted[$listings[$x]] = array_slice(scandir($dir.'/'.$listings[$x]), 2);
      }
    }

    return $listingsSorted;
  }

  // If no script has been selected load Tes-Basic else load the selected script contents
  if(!isset($_GET['script']) || $_GET['script'] == ''){
    $input = file_get_contents('./scripts/Tests/Test-Basic.txt', true);
  } else {
    $input = file_get_contents('./scripts/' . $_GET['script'], true);
  }

  // Start a timer, execute the included classes, display the execution time and display all the status messages
  $time_start = microtime(true); 
  $tokens = new Tokenizer($input);
  $ast = new Parser($tokens->output);
  $emitter = new Emitter($ast->output);
  print_status();
  echo 'Total execution time in seconds: ' . (microtime(true) - $time_start);

?><!DOCTYPE html>
<html lang="en">

<head>
  <meta name="description" content="Webpage description goes here"/>
  <meta charset="utf-8">
  <title>Script Parser</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="author" content="">
  <!--<link rel="stylesheet" href="css/style.css">-->
  <!--<script src="http://code.jquery.com/jquery-latest.min.js"></script>-->
  <style>
    .row {
      display: flex;
      width: 100%;
      margin-bottom: 50px;
    }
    .col {
      overflow: auto;
      padding: 0 10px;
      min-width: 25%;
    }

    .pre {
      font-family: monospace;
      white-space: pre;
    }
  </style>
</head>

<body>

  <div class="container">

    <a href="https://github.com/jasonstrooband/alchemy_lex/wiki">Wiki</a> - <a href="<?php echo $_SERVER['REQUEST_URI'] . '&debug'; ?>">Debug</a>

    <form id="script-changer" action="" method="get">

      <select name="script" id="script">
        <option <?php echo (!isset($_GET['script']) ? 'selected="selected"' : '') ?> value="">Choose one</option>
        <?php
        foreach($scripts as $script_cat_key => $script_names) { ?>
          <optgroup label="<?php echo $script_cat_key; ?>">
            <?php
              foreach($script_names as $name) {
                $script_value = $script_cat_key . '/' . $name;  ?>
                <option <?php echo (isset($_GET['script']) && urldecode($_GET['script']) == $script_value ? 'selected="selected"' : '') ?> value="<?php echo $script_value; ?>"><?php echo $name; ?></option>
              <?php } ?>
          </optgroup>
        <?php } ?>
      </select>
      <input type="submit" value="Go" id="submit" />
    </form>
    
    <h1>Generator</h1>
    <div class="pre"><?php echo (isset($emitter) ? $emitter->output : '') ?></div>

      <div class="row">
        <div class="col">
          <h2>Input</h2>
            <div class="pre row">
              <div style="padding-right:10px;"><?php
                $lineCount = count( explode(PHP_EOL, $input) );
                for ($i=0; $i < $lineCount; $i++) { 
                  echo $i + 1 . ":<br />";
                }
              ?></div>
              <div><?php echo htmlspecialchars($input) ?></div>
            </div>
        </div>
        <?php if(isset($_GET['debug'])) { ?>
          <div class="col">
            <h2>Tokens</h2>
            <div class="pre"><?php (isset($tokens) ? vd($tokens->output) : '') ?></div>
          </div>
          <div class="col">
            <h2>AST</h2>
            <div class="pre"><?php (isset($ast) ? vd($ast->output) : '') ?></div>
          </div>
          <div class="col">
            <h2>Emitter</h2>
            <div class="pre"><?php (isset($emitter) ? vd($emitter->output) : '') ?></div>
          </div>
        <?php } ?>
      </div>

  </div>
</body>
</html>