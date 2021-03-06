<?php
//http://localhost/mod/
//http://localhost/mb/build.php
$extensionList["modelling"] = "extensionModelling";
$raw = array(); //required global variable
$config;
$dataset;
$dataset_qs;
$data;

$old_files = glob($html_path."/models/*.html");
foreach ($old_files as $ofile)
	{unlink ($ofile);}

if (is_file('../d3/common.php'))
  {require_once '../d3/common.php';}
else
  {require_once '../../d3-process-map/common.php';}

function extensionModelling ($d, $pd)
  {
  global $raw, $extraHTML, $html_path, $config, $dataset, $dataset_qs, $data;
  
  $files = array();  
  $codecaption = "The complete text details used to define one of the included models.";
  foreach ($d["file"] as $t)
    {$files = array_merge($files, glob("../models/*/${t}-triples.csv"));}

  $inputs = array();

  foreach ($files as $f)
    {
    $fname = basename($f, ".csv").PHP_EOL;
    if(preg_match("/^(.+)-triples$/", $fname, $m))
      {$tag = $m[1];}
    else
      {$tag = $f;}
      
    $fcontent = file($f);
    $codetitle = "The original \"$fname\" file used";
    if (isset($d["displaycode"]))
      {$extraHTML .= displayCode ($fcontent, $codetitle, "txt", $codecaption);
       $codetitle = false;}
    $inputs[$tag] = $fcontent;
    }

  $errors = array();	
  $raw = getRaw($inputs);
  $gcontent = modelLinks ($d["file"]);
  
  $d["content"] = positionExtraContent ($d["content"], $gcontent);
  
  $codeHTML = "";
  $codecaption = "The complete modelling files used to define the models created in this example.";

  foreach ($raw as $name => $selected)
    {		
    $pd["fluid"] = true;   
    
    $def = Mermaid_formatData ($selected);
    $html = Mermaid_displayModel($def, false, $pd["page"]);
			
    $myfile = fopen($html_path."models/mermaid_${name}.html", "w");
    fwrite($myfile, $html);
    fclose($myfile);			
		
    $D3_data = D3_formatData($selected);

    $loc = "data";
		
    if (!is_dir($loc."/${name}")) {
      mkdir($loc."/${name}");}
				
    if (!is_file($loc."/${name}/config.json")) {
      copy($loc."/config.json", $loc."/${name}/config.json");}
			
    $myfile = fopen($loc."/${name}/objects.json", "w");
    fwrite($myfile, "[\n");
    $ja = array();
    foreach ($D3_data as $nm => $a)
      {$ja[] = json_encode($a);}

    fwrite($myfile, implode(",\n", $ja));
    fwrite($myfile, "]");		
    fclose($myfile);
		
    $dataset = $name;
    $dataset_qs = "?dataset=$dataset";

    read_config(); //defines the content of the global variable $config
    $config['jsonUrl'] = "d3_${name}.json";
    $json = json_encode($config);
    $html = D3_displayModel ($title, $dataset, $json, $pd["page"]);
    $myfile = fopen($html_path."models/d3_${name}.html", "w");
    fwrite($myfile, $html);
    fclose($myfile);
				
    read_data(); //defines the content of the global variable $data
    $d3json = json_encode(array(
      'data'   => $data,
      'errors' => $errors));
	
    $myfile = fopen($html_path."models/d3_${name}.json", "w");
    fwrite($myfile, $d3json);
    fclose($myfile);
				
    $html = D3_displayList ($title, $dataset, $data);
    $myfile = fopen($html_path."models/d3_${name}_list.html", "w");
    fwrite($myfile, $html);
    fclose($myfile);
    }	

  return (array("d" => $d, "pd" => $pd));
  }

function getRaw($data)
  {	
  $output = array();
	
  $no = 0;
  $bn = 0;
  $tn = 0;
  $ono = 0;
  $bnew = false;
  $bba = array();
  $bbano = 1;
 
  foreach ($data as $tag => $arr) 
    {
    $output[$tag]["model"] = $tag;
    $output[$tag]["comment"] = ucfirst ($tag)." Model";
    $output[$tag]["count"] = 0;

    foreach ($arr as $k => $line) 
      {
      if (preg_match("/^.+\t.+\t.+$/", $line, $m))
	{$trip = explode ("\t", $line);}
      else if (preg_match("/^.+[,].+[,].+$/", $line, $m))
	{$trip = explode (",", $line);}
      else
	{$trip = array($line);}
      
      $trip = array_map('trim', $trip);

      // Increment triple number
      $tn++;
	
      if(preg_match("/^[\/][\/][ ]Model[:][\s]*([a-zA-Z0-9 ]+)[\s]*[\/][\/](.+)$/", $line, $m))
	{$output[$tag]["comment"] = $m[2];}
		
      if (isset($trip[2]))
	{
	// Ignore comments and empty lines

	//All Blank Nodes need to be numbered to be unique
	if ($trip[0] == "_Blank Node" and $trip[1] == "crm:P2.has type" and !$bnew)
	  {$bn++;
	   $bnew=true;}
		
	// Ensure subsequent Blank Nodes are seen as new. 
	if ($trip[1] == "crm:P2.has type" AND $trip[0] != "_Blank Node")
	  {$bnew=false;}
								
	if ($trip[0] == "_Blank Node")
	  {$trip[0] = "_Blank Node-N".$bn;}
	else if (preg_match("/^_Blank Node[-]([0-9]+)$/", $trip[0], $m))
	  {$trip[0] = "_Blank Node-N".($bn-$m[1]);}
				
	// Current process is assuming that the subject and the object can not both be Blank Nodes
	if ($trip[2] == "_Blank Node")
	  {$trip[2] = "_Blank Node-N".$bn;
	   $bnew=false;}
	else if (preg_match("/^_Blank Node[-]([0-9]+)$/", $trip[2], $m))
	  {$trip[2] = "_Blank Node-N".($bn-$m[1]);}
										
	$trip[1] = $trip[1]."-N".$tn;
			
	$output[$tag]["triples"][] = $trip;
	$output[$tag]["count"]++;
	}
      else //Empty lines will force a new Blank node to be considered
	{$bnew=false;}
			
      if ($trip[0] == "// Stop") // For debugging
	{break;}
      }
    }	

  return ($output);
  }

function modelLinks ($mods)
  {
  global $raw;

  ob_start();

  echo "<div class=\"col-12 col-lg-12\"><table width=\"100%\"><tbody>";
		
  foreach ($mods as $nm)// => $a)
    {
    $count = $raw[$nm]["count"];
    $tag = $raw[$nm]["comment"];
			
    echo <<<END
      <tr>
				<td><p style="margin-bottom: 0px; font-size:1.25rem; font-weight:500;">$tag ($count - triples)</p></td>
				<td style="text-align:right;white-space: nowrap;">
					<div class="btn-group" role="group" aria-label="Basic example">
					<a class="btn btn-outline-primary" href="models/d3_${nm}.html" role="button">D3 Model</a>
					<a class="btn btn-outline-success" href="models/mermaid_${nm}.html" role="button">Mermaid Model</a>
          </div
        </td>
      </tr>
END;
		}

  echo "</tbody></table><br></div>";

  $html = ob_get_contents();
  ob_end_clean(); // Don't send output to client
        
  return ($html);
  }
  
function Mermaid_formatData ($selected)
  {
  ob_start();
  echo <<<END

graph LR

classDef crm stroke:#333333,fill:#DCDCDC,color:#333333,rx:5px,ry:5px;
classDef thing stroke:#2C5D98,fill:#D0E5FF,color:#2C5D98,rx:5px,ry:5px;
classDef event stroke:#6B9624,fill:#D0DDBB,color:#6B9624,rx:5px,ry:5px;
classDef oPID stroke:#2C5D98,fill:#2C5D98,color:white,rx:5px,ry:5px;
classDef ePID stroke:#6B9624,fill:#6B9624,color:white,rx:5px,ry:5px;
classDef aPID stroke:black,fill:#FFFF99,rx:20px,ry:20px;
classDef type stroke:red,fill:#B51511,color:white,rx:5px,ry:5px;
classDef name stroke:orange,fill:#FEF3BA,rx:20px,ry20px;
classDef literal stroke:black,fill:#FFB975,rx:2px,ry:2px,max-width:100px;
classDef classstyle stroke:black,fill:white;
classDef url stroke:#2C5D98,fill:white,color:#2C5D98,rx:5px,ry:5px;
classDef note stroke:#2C5D98,fill:#D8FDFF,color:#2C5D98,rx:5px,ry:5px;

END;
  $defTop = ob_get_contents();
  ob_end_clean(); // Don't send output to client	

  $defs = "";
  $defs .= "<div class=\"mermaid\">".$defTop;

  $things = array();
  $no = 0;
  $crm = 0;
  $objs = array();
  
  foreach ($selected["triples"] as $k => $t) 
    {
    // Ensure that all refs to crm classes are unique so the diagram
    // does not Overlap too much
    if(preg_match("/^(crm:E.+)$/", $t[2], $m))
      {$selected["triples"][$k][2] = $t[2]."-".$crm;
       $crm++;}
    // Remove excess white spaces from numbered properties
    if(preg_match("/^(.+)-N[0-9]+$/", $t[1], $m))
      {$selected["triples"][$k][1] = trim($m[1]);}

    $objs[$t[0]] = 1;
    }
		
  foreach ($selected["triples"] as $k => $t) 
    {
    // Format the displayed text, either wrapping or removing numbers
    // used to indicate separate instances of the same text/name
    if (count_chars($t[2]) > 60)
      {$use = wordwrap($t[2], 60, "<br/>", true);}
    else
      {$use = $t[2];}
			
    if(preg_match("/^(crm[:].+)[-][0-9]+$/", $use, $m))
      {$use = $m[1];}

    // Allow the user to force the formatting classes used for the
    // object and subject
    if(isset($t[3]))
      {$fcs = explode ("@@", $t[3]);}
    else
      {$fcs = array(false, false);}
								
    if (!isset($things[$t[0]]))
      {$things[$t[0]] = "O".$no;
       // Default objects to oPID class
       if (!$fcs[0] and !preg_match ("/^[a-zA-Z]+[:].+$/", $t[0], $m))
	{$fcs[0] = "oPID";}
       $defs .= Mermaid_defThing($t[0], $no, $fcs[0]);
       $no++;}
			 
    if (!isset($things[$t[2]]))
      {$things[$t[2]] = "O".$no;
       // Default objects to oPID class
       if (!$fcs[1] and !preg_match ("/^[a-zA-Z]+[:].+$/", $t[1], $m) and isset($objs[$t[2]]))
	{$fcs[1] = "oPID";}
       $defs .= Mermaid_defThing($t[2], $no, $fcs[1]);
       $no++;}		
					 					
    $defs .= $things[$t[0]]." -- ".$t[1]. " -->".$things[$t[2]].
      "[\"".$use."\"]\n";		
    }

  $defs .= ";</div>";
	
  return ($defs);
  }	

function Mermaid_defThing ($var, $no, $fc=false)
	{	
	$diagCmatches = array(
		"aat[:].+" => "type",
		"wd[:].+" => "type",
		"ulan[:].+" => "type",
		"tgn[:].+" => "type",
		"ng[:].+" => "oPID",
		"ngo[:].+" => "oPID",
		"ngi[:].+" => "oPID",
		"_Blank.+" => "thing",
		"http.+" => "url",
		"crm[:]E.+" => "crm",
		"[\"].+[\"]" => "note"
		);
		 
	if ($fc) {$cls = $fc;}
	else {
		$cls = "literal";
		foreach ($diagCmatches as $k => $cur)
			{
			if(preg_match("/^".$k."$/", $var, $m))
				{$cls = $cur;
				 break;}}}	 
	$code  = "O".$no;
	$str = "\n$code(\"$var\")\nclass $code $cls;\n";
		 
	if(preg_match("/^http.+$/", $var, $m))
		{$str .= "click ".$code." \"$var\" \"Tooltip\"\n";}		
	else if(preg_match("/^ngo[:]([0-9A-Z]{3}[-].+)$/", $var, $m))
		{$str .= "click ".$code." \"http://data.ng-london.org.uk/resource/$m[1]\" \"Tooltip\"\n";}
	else if(preg_match("/^ng[:]([0-9A-Z]{4}[-].+)$/", $var, $m))
		{$str .= "click ".$code." \"http://data.ng-london.org.uk/$m[1]\" \"Tooltip\"\n";}
	else if(preg_match("/^aat[:](.+)$/", $var, $m))
		{$str .= "click ".$code." \"http://vocab.getty.edu/aat/$m[1]\" \"Tooltip\"\n";}
	else if(preg_match("/^tgn[:](.+)$/", $var, $m))
		{$str .= "click ".$code." \"http://vocab.getty.edu/tgn/$m[1]\" \"Tooltip\"\n";}
	else if(preg_match("/^ulan[:](.+)$/", $var, $m))
		{$str .= "click ".$code." \"http://vocab.getty.edu/ulan/$m[1]\" \"Tooltip\"\n";}
	else if(preg_match("/^wd[:](.+)$/", $var, $m))
		{$str .= "click ".$code." \"https://www.wikidata.org/wiki/$m[1]\" \"Tooltip\"\n";}
	
	return ($str);
	}


function Mermaid_displayModel($defs, $title="", $parent="models.html")
  {
  global $d3Path;
  ob_start();
  
echo <<<END

body
{
  #background-color: #fcfcfc;
}


.list
{
	left:80px;
}

g a 
			{color:inherit;}

.nav-button {
    position: absolute;
    top: 8px;
    left: 8px;
}

.btn {
    display: inline-block;
    padding: 6px 12px;
    margin-bottom: 0;
    font-size: 14px;
    font-weight: normal;
    line-height: 1.428571429;
    text-align: center;
    white-space: nowrap;
    vertical-align: middle;
    cursor: pointer;
    background-image: none;
    border: 1px solid transparent;
    border-radius: 4px;
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    -o-user-select: none;
    user-select: none;
}

.btn-default {
    color: #333333;
    background-color: #ffffff;
    border-color: #cccccc;
}

.btn-default:hover, .btn-default:focus, .btn-default:active, .btn-default.active, .open .dropdown-toggle.btn-default {
    color: #333333;
    background-color: #ebebeb;
    border-color: #adadad;
}

END;
  $styles = ob_get_contents();
  ob_end_clean(); // Don't send output to client	

  ob_start();
  echo <<<END
<!DOCTYPE html>
<!--[if lt IE 7]>      <html class="lt-ie9 lt-ie8 lt-ie7"> <![endif]-->
<!--[if IE 7]>         <html class="lt-ie9 lt-ie8"> <![endif]-->
<!--[if IE 8]>         <html class="lt-ie9"> <![endif]-->
<!--[if gt IE 8]><!--> <html> <!--<![endif]-->
    <head>
        <meta http-equiv="X-UA-Compatible" content="IE=Edge">
        <meta charset="utf-8">
        <title>$title</title>
        
        <!--- <link rel="stylesheet" href="../css/mermaid.css"> -->
    <style>			
		$styles
		</style>
  </head>
    <body>
    <div class="center-div">
        <div id="split-container">
            <a class="btn btn-default nav-button" id="nav-home" href="../">
                Home
            </a>            
            <a class="btn btn-default nav-button" style="left:80px;" id="nav-models" href="../$parent">
                Models
            </a>
            <div id="graph-container">
                <div id="graph">	$defs</div>
            </div>
        </div>
        </div>
        
	
  <script src="https://unpkg.com/mermaid@8.5.2/dist/mermaid.min.js"></script>
  <script>mermaid.initialize({startOnLoad:true, flowchart: { 
    curve: 'basis'
  }});</script>  
    </body>
</html>
END;

  $html = ob_get_contents();
  ob_end_clean(); // Don't send output to client	

  return ($html);
  }

function D3_formatData($selected)
	{
	$output = array();

	foreach ($selected["triples"] as $k => $trip) 
		{
		$dtrip = $trip;
		
		//Hide the unique numbers on the nodes from the display
		foreach ($dtrip as $j => $v)
			{if(preg_match("/^(.+)-N[0-9]+$/", $v, $m))
				{$dtrip[$j] = trim($m[1]);}}
		
		if (count_chars($dtrip[2]) > 60)
		 {$dtrip[2] = wordwrap($dtrip[2], 60, "\n", true);}
							
		if (!isset($output[$trip[0]]))
			{$output[$trip[0]] = D3_processNode($trip[0], $dtrip[0]);}
		if (!isset($output[$trip[1]]))
			{$output[$trip[1]] = D3_processNode($trip[1], $dtrip[1], 1);}
		if (!isset($output[$trip[2]]))
			{$output[$trip[2]] = D3_processNode($trip[2], $dtrip[2]);}

		$output[$trip[1]]["depends"][] = $trip[0];
		$output[$trip[2]]["depends"][] = $trip[1];
		}	
		
	return($output);
	}	

function D3_processNode ($v, $dv, $prop=false)
	{	
	$diagclasses = array(
		"crm:E22.Man-Made Object" => "object",
		"crm:E31.Document" => "object",
		"ng:Further Events" => "ePID",
		"ngo:002-0432-0000" => "ePID"
		);
	
	$diagCmatches = array(
		"aat[:].+" => "type",
		"tgn[:].+" => "type",
		"ulan[:].+" => "type",
		"wd[:].+" => "type",
		"ng[:].+" => "oPID",
		"ngo[:].+" => "oPID",
		"_Blank.+" => "oPID",
		"http.+" => "url",
		"crm[:]E5[.].+" => "event",
		"crm[:]E12[.].+" => "event",
		"crm[:]E.+" => "object",
		"[\"].+[\"]" => "note"
		);
	
	if(preg_match("/^([a-z]+)[:][^\/].+$/", $v, $m))
		{$g = $m[1];}
	else if(preg_match("/^http[s]{0,1}[:].+$/", $v, $m))
		{$g = "url";}
	else if(preg_match("/^_Blank.+$/", $v, $m))
		{$g = "bn";}
	else if(preg_match("/^Note.+$/", $v, $m))
		{$g = "note";}
	else
		{$g = "lit";}
		
	if ($prop) {
		if ($g == "note")
			{$cls = "note";}
		else
			{$cls = "property";}}
	else {
		if(isset($diagclasses[$v])) {$cls = $diagclasses[$v];}
		else {
			$cls = "literal";
			foreach ($diagCmatches as $k => $cur)
				{if(preg_match("/^".$k."$/", $v, $m))
					{$cls = $cur;
					break;}
				}}}
	if (!$prop) {$g = false;}
		
	$output = array(
		"type" => $cls,
		"name" => $v,
		"display" => $dv,
		"group" => $g,
		"depends" => array()
		);
	
	return ($output);		
	}
    
function D3_displayList ($title, $dataset, $data)
	{
  global $default_scripts;
  
	$dstr = "";
	foreach ($data as $obj) {
    $id = get_id_string($obj['name']);
    $dstr .= "<div class=\"docs\" id=\"$id\">$obj[docs]</div>\n";
		}

  $css = array(
    $default_scripts["css-scripts"]["bootstrap"],
    "../css/d3_style.css",
    "../css/d3_print.css"
    );
  
	ob_start();
	echo <<<END
<!DOCTYPE html>
<!--[if lt IE 7]>      <html class="lt-ie9 lt-ie8 lt-ie7"> <![endif]-->
<!--[if IE 7]>         <html class="lt-ie9 lt-ie8"> <![endif]-->
<!--[if IE 8]>         <html class="lt-ie9"> <![endif]-->
<!--[if gt IE 8]><!--> <html> <!--<![endif]-->
    <head>
        <meta http-equiv="X-UA-Compatible" content="IE=Edge">
        <meta charset="utf-8">
        <title>$title</title>
        <link rel="stylesheet" href="$css[0]">
        <link rel="stylesheet" href="$css[1]">
        <link rel="stylesheet" href="$css[2]">
    </head>
    <body>
				<a class="btn btn-default nav-button" id="nav-home" href="../">
                Home
            </a>            
            <a class="btn btn-default nav-button" style="left:80px;" id="nav-models" href="../models.html">
                Models
            </a>
            <a class="btn btn-default nav-button"  style="left:160px;"  id="nav-graph" href="d3_${dataset}.html">
                View Graph
            </a>
        <div id="docs-list">
					$dstr
        </div>
    </body>
</html>

END;
	$html = ob_get_contents();
	ob_end_clean(); // Don't send output to client	
		 
	return ($html);
	}	
		
function D3_displayModel ($title, $dataset, $json, $parent="models.html")
	{
  global $default_scripts;
    
  $css = array(
    $default_scripts["css-scripts"]["bootstrap"],
    "../css/d3_style.css",
    "../css/d3_svg.css"
    );

  $js = array(
    $default_scripts["js-scripts"]["jquery"],
    "https://d3js.org/d3.v3.js",
    "https://www.unpkg.com/colorbrewer@1.3.0/index.js",
    "../js/d3_geometry.js",
    "../js/d3_script_v2.0.js"
    );
  
	ob_start();
	echo <<<END
<!DOCTYPE html>
<!--[if lt IE 7]>      <html class="lt-ie9 lt-ie8 lt-ie7"> <![endif]-->
<!--[if IE 7]>         <html class="lt-ie9 lt-ie8"> <![endif]-->
<!--[if IE 8]>         <html class="lt-ie9"> <![endif]-->
<!--[if gt IE 8]><!--> <html> <!--<![endif]-->
    <head>
        <meta http-equiv="X-UA-Compatible" content="IE=Edge">
        <meta charset="utf-8">
        <title>$title</title>
        <link rel="stylesheet" href="$css[0]">
        <link rel="stylesheet" href="$css[1]">
        <link rel="stylesheet" href="$css[2]">
    <style>
			body
{
  #background-color: #fcfcfc;
}
.center-div
{
  position: absolute;
  margin: auto;
  top: 0;
  right: 0;
  bottom: 0;
  left: 0;
  width: 100%;
  height: 100%;
  #background-color: #ccc;
  border-radius: 3px;
}

.list
{
	left:80px;
}
    </style>
    </head>
    <body>
        <!--[if lt IE 9]>
        <div class="unsupported-browser">
            This website does not fully support your browser.  Please get a
            better browser (Firefox or <a href="/chrome/">Chrome</a>, or if you
            must use Internet Explorer, make it version 9 or greater).
        </div>
        <![endif]-->
        <div class="center-div">
        <div id="split-container">
            <a class="btn btn-default nav-button" id="nav-home" href="../">
                Home
            </a>            
            <a class="btn btn-default nav-button" style="left:80px;" id="nav-models" href="../$parent">
                Models
            </a>
            <a class="btn btn-default nav-button"  style="left:160px;"  id="nav-list" href="d3_${dataset}_list.html">
                View list
            </a>
            <div id="graph-container">
                <div id="graph"></div>
            </div>
            <div id="docs-container">
                <a id="docs-close" href="#">&times;</a>
                <div id="docs" class="docs"></div>
            </div>
        </div>
        </div>



        <script src="$js[0]"></script>
        <script src="$js[1]"></script>
        <script src="$js[2]"></script>
        <script src="$js[3]"></script>
        <script>
            var config = $json;
        </script>
        <script src="$js[4]"></script>
    </body>
</html>

END;
	$html = ob_get_contents();
	ob_end_clean(); // Don't send output to client	
	
	 
	return ($html);
	}	
?>
