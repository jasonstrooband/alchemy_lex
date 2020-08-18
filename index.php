<?php
  // TODO: Write in comments for fields
  // TODO: Add expressions with math support and precedence
  // TODO: Add error checking for equal share group type
  // TODO: Add error check for lines without a number range or single and not equal share group
  // TODO: Add error check for multiline to see if the next line is an output line
  // TODO: Add error checking for strings outside of groups
  // TODO: Add error checking for expressions outside of groups

  ini_set('xdebug.var_display_max_depth', -1);
  ini_set('xdebug.var_display_max_children', -1);

  $script_names = array_slice(scandir("./scripts"), 2);

  $scripts = getScripts();

  require_once './includes/Tokenizer.php';
  require_once './includes/Parser.php';
  require_once './includes/Emitter.php';

  function print_error($msg){
    echo "<div style=;background-color:red;color:white;font-size:26px;padding:10px;margin:10px;>";
    print_r($msg);
    echo "</div>";
  }

  function pr($arr){
    print "<pre>";
    print_r($arr);
    print "</pre>";
  }
  function vd($arr){
    print "<pre>";
    var_dump($arr);
    print "</pre>";
  }

  function getScripts(){
    $dir = "./scripts";
    $listings = array_slice(scandir($dir), 2);

    // prevent empty ordered elements
    if (count($listings) < 1)
      return 0;
  
    $listingsSorted = array();

    for($x = 0; $x < count($listings); $x++){
      if(is_dir($dir.'/'.$listings[$x])){
        $listingsSorted[$listings[$x]] = array_slice(scandir($dir.'/'.$listings[$x]), 2);
      }
    }

    return $listingsSorted;
  }

  if(!isset($_GET['script']) || $_GET['script'] == ''){
    $input = file_get_contents('./scripts/Tests/Test-Basic.txt', true);
  } else {
    $input = file_get_contents('./scripts/' . $_GET['script'] . '.txt', true);
  }
  // At start of script
  $time_start = microtime(true); 
  $tokens = new Tokenizer($input);
  $ast = new Parser($tokens->output);
  $emitter = new Emitter($ast->output);
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
    }

    .pre {
      font-family: monospace;
      white-space: pre;
    }
  </style>
</head>

<body>

  <div class="container">

    <a href="https://github.com/jasonstrooband/alchemy_lex/wiki/Features-and-Roadmap">Features and Roadmap</a> - <a href="<?php echo $_SERVER['REQUEST_URI'] . '&debug'; ?>">Debug</a>

    <form id="script-changer" action="" method="get">

      <select name="script" id="script">
        <option <?php echo (!isset($_GET['script']) ? 'selected="selected"' : '') ?> value="">Choose one</option>
        <?php
        foreach($scripts as $script_cat_key => $script_names) { ?>
          <optgroup label="<?php echo $script_cat_key; ?>">
            <?php
              foreach($script_names as $name) {
                $script_name = basename($name, '.txt');
                $script_value = $script_cat_key . '/' . $script_name;
                //echo $script_value;
                //echo urldecode($_GET['script']); ?>
                <option <?php echo (isset($_GET['script']) && urldecode($_GET['script']) == $script_value ? 'selected="selected"' : '') ?> value="<?php echo $script_value; ?>"><?php echo $script_name; ?></option>
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
            <div class="pre"><?php (isset($tokens) ? var_dump($tokens->output) : '') ?></div>
          </div>
          <div class="col">
            <h2>AST</h2>
            <div class="pre"><?php (isset($ast) ? var_dump($ast->output) : '') ?></div>
          </div>
          <div class="col">
            <h2>Emitter</h2>
            <div class="pre"><?php (isset($emitter) ? var_dump($emitter->output) : '') ?></div>
          </div>
        <?php } ?>
      </div>

  </div>
</body>
</html>