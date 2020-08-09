<?php
  $script_names = array_slice(scandir("./scripts"), 2);
  $scripts = getScripts();

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
    $scriptInput = file_get_contents('./scripts/Tests/Test-Basic.txt', true);
  } else {
    $scriptInput = file_get_contents('./scripts/' . $_GET['script'] . '.txt', true);
  }
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta name="description" content="Webpage description goes here"/>
  <meta charset="utf-8">
  <title>Script Parser</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="author" content="">
  <!--<link rel="stylesheet" href="css/style.css">-->
  <script src="includes/tokenizer.js"></script>
  <script src="http://code.jquery.com/jquery-latest.min.js"></script>
  <style>
    .row {
      width: 100%;
      clear: both;
      float: left;
      margin-bottom: 50px;
    }
    .col {
      display: inline-block;
      width: 25%;
      float: left;
      overflow: auto;
      padding: 0 10px;
      box-sizing: border-box;  
    }

    .pre {
      font-family: monospace;
      white-space: pre;
    }
  </style>
</head>

<body>

  <div class="container">

    <a href="./roadmap.html">Roadmap</a> - <a href="./features.html">Features</a>

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


    <div class="row">
      <h1>Generator</h1>

      <div class="col">
        <h2>Input</h2>
        <div id="input" class="pre"></div>
      </div>
      <div class="col">
        <h2>Tokens</h2>
        <div id="tokens" class="pre"></div>
      </div>
      <div class="col">
        <h2>AST</h2>
        <div id="ast" class="pre"></div>
      </div>
      <div class="col">
        <h2>Emitter</h2>
        <div id="emitter" class="pre"></div>
      </div>
    </div>

  </div>
</body>

<script>
  let scriptInput = <?php echo json_encode($scriptInput); ?>;

  //console.log(JSON.stringify(scriptInput, null /*replacer function */, 4 /* space */));
  
  Tokenizer = new Tokenizer(scriptInput);

  $('#input').html(scriptInput);
  $('#tokens').html((Tokenizer.tokens.length+1) + ' Tokens<br />' + JSON.stringify(Tokenizer.tokens, null /*replacer function */, 4 /* space */));

  //console.log(JSON.stringify(Tokenizer.terminals, null /*replacer function */, 4 /* space */))
</script>

</html>