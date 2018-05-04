<?php

/*
==============================================
 Multiple login supported
 Usage:
    'your_username' => 'your_password'
============================================== */
$LOGIN_INFORMATION = array(
  'admin' => 'admin654',
  'henshin' => 'hakutominami'
);

/*
==============================================
 General settings
============================================== */
define('USE_USERNAME', false);
define('LOGOUT_URL', 'http://www.google.com.my/');
define('TIMEOUT_MINUTES', 60);
define('TIMEOUT_CHECK_ACTIVITY', true);
define('ERR_MESSAGE', 'Incorrect!');

/*
==============================================
  Polymorphic settings
============================================== */
define('POLY_ON', true);          // true=enable all features, false=disable all features
define('POLY_NEWLINE', false);    // true=enable random multiline
define('POLY_SPACE', true);      // true=enable random white spaces if found any single space
define('POLY_CAPITAL', true);     // true=all character will be randomly in either upper or lower case
define('POLY_GARBAGE', true);    // true=add multi line of random html tag, comments, etc.; Limited to newline only

/*
==============================================
  Variable login seed
  Usage:
    define('LOGOUT', genStr("put_whatever_word_here_as_a_seed"));
============================================== */
define('F_PASSWORD', genStr("a_key_that_can_open_many_locks_is_called_masterkey"));
define('F_LOGIN', genStr("your_pant_was_here"));
define('F_SUBMIT', genStr("pizza_delivery"));

$timeout = (TIMEOUT_MINUTES == 0 ? 0 : time() + TIMEOUT_MINUTES * 60);

if(isset($_GET['logout'])) {
  setcookie("verify", '', $timeout, '/'); // clear password;
  header('Location: ' . LOGOUT_URL);
  exit();
}

if(!function_exists('showLoginPasswordProtect')) {
  function showLoginPasswordProtect($error_msg) {
    if( !empty($error_msg)) { $strErrorMessage = "<center><span style='color:red; font-weight:bold'>".$error_msg."</span></center><br>"; } else { $strErrorMessage = ""; }
    if (USE_USERNAME) { $strUsername = "<input required style='height:30px; width:300px' type='input' name='".F_LOGIN."'/>"; } else { $strUsername = ""; };
    
/*
==============================================
  You can modify the following login page layout
  according to your needs.
============================================== */
    $strBody = "<html>
<head>
<title>☺</title>
<link rel='icon' type='image/png' href='data:image/png;base64,iVBORw0KGgo=''>
</head>
<body>
<form method='post'><br>
".$strErrorMessage."<center>
".$strUsername."<input required style='height:30px; width:300px' type='password' name='[[F_PASSWORD]]'><button style='height:30px;' type='submit' name='[[F_SUBMIT]]'>▶</button>
</center>
</form>
</body>
</html>";
    echo polyEverything($strBody);
    die();
  }
}

if (isset($_POST[F_PASSWORD])) {
  $login = isset($_POST[F_LOGIN]) ? $_POST[F_LOGIN] : '';
  $pass = $_POST[F_PASSWORD];

  if (!USE_USERNAME && !in_array($pass, $LOGIN_INFORMATION) || (USE_USERNAME && ( !array_key_exists($login, $LOGIN_INFORMATION) || $LOGIN_INFORMATION[$login] != $pass ))) {
    showLoginPasswordProtect(ERR_MESSAGE);
  } else {
    setcookie("verify", md5($login.'%'.$pass), $timeout, '/');
    unset($_POST[F_LOGIN]);
    unset($_POST[F_PASSWORD]);
    unset($_POST[F_SUBMIT]);
  }

} else {
  if (!isset($_COOKIE['verify'])) {
    showLoginPasswordProtect("");
  }

  $found = false;
  foreach($LOGIN_INFORMATION as $key=>$val) {
    $lp = (USE_USERNAME ? $key : '') .'%'.$val;
    if ($_COOKIE['verify'] == md5($lp)) {
      $found = true;
      if (TIMEOUT_CHECK_ACTIVITY) {
        setcookie("verify", md5($lp), $timeout, '/');
      }
      break;
    }
  }

  if (!$found) {
    showLoginPasswordProtect("");
  }
}

/*
==============================================
  Used to generate sha256 based on current
  hour. Then, take out first 5 letter to be
  use as variable name. Otherwise, use default
  MD5 hash and take out 5 letter from it.
============================================== */
function genStr($salt) {
  if(isset($salt)) {
    $seed = hash("sha256", date("h:i") . $salt);
    $str = substr($seed, 0, 5);
  } else {
    $str = substr("bb457d93e8d9a131bf3917b2388ede11", 0, 5);
  }
  return $str;
}

/*
==============================================
  To generate random spacing
============================================== */
function genSpace($input) {
  if (POLY_ON == true) {
    if (POLY_SPACE == true) {
      $arr1 = str_split($input);
      $spc = " ";
      foreach($arr1 as $val) {
        if (strstr($val, " ")) {
          for($i=0; $i<rand(2, 9); $i++) {
            $spc .= $val." ";
          }
        } else {
            $spc .= $val;
          }
        }
      }
  }
  return $spc;
}

/*
==============================================
  To generate random string from given length.
============================================== */
function genString($length = 9) {
    return substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length/strlen($x)) )),1,$length);
}

/*
==============================================
  To generate random hidden input/form/comment.
============================================== */
function genGarbage($input) {
  $spc = "";
  $word[0] = "<input type=\"hidden\" value=\"".genString(rand(11, 99))."\">";
  $word[1] = "<div type=\"hidden\" value=\"".genString(rand(11, 99))."\"></div>";
  $word[2] = "<!-- ".genString(rand(11, 99))." -->";
  $word[3] = "<script> /* ".genString(rand(11, 99))." */ </script>";

  for($i=0; $i<rand(3, 8); $i++) {
    $spc .= $word[rand(0, 3)];
  }
  $arr1 = str_split($input);
  foreach($arr1 as $val) {
    if (strstr($val, "\n")) {
      for($i=0; $i<rand(3, 9); $i++) {
        $spc .= $val.$word[rand(0,3)];
      }
    } else {
      $spc .= $val;
    }
  }
  return $spc;
}

/*
==============================================
  Randomize all letter with upper or lower
  case.
============================================== */
function genCap($input) {
  $spc = "";
  if (POLY_ON == true) {
    if (POLY_CAPITAL == true) {
      $arr1 = str_split($input);
      foreach($arr1 as $val) {
        $num = rand(1, 10);
        if ($num > 5) {
          $spc .= strtoupper($val);
        } else {
          $spc .= strtolower($val);
      }
    }
  }
  }
  return $spc;
}

/*
==============================================
  Add random newline
============================================== */
function genNewline($input) {
  $spc = "";
  for($i=0; $i<rand(3, 7); $i++) {
    $spc .= "\n";
  }
  $arr1 = str_split($input);
  foreach($arr1 as $val) {
    if (strstr($val, "\n")) {
      for($i=0; $i<rand(3, 6); $i++) {
        $spc .= $val."\n";
      }
    } else {
      $spc .= $val;
    }
  }
  return $spc;
}

/*
==============================================
  This is where the polymorphic part take
  place depending on your settings.
============================================== */
function polyEverything($input) {
  if (POLY_ON == true) {
    if (POLY_CAPITAL == true) {
      $word = genCap($input);
      $finalWord = $word;
    }

    if (POLY_SPACE == true) {
      $word = genSpace($input);
      $finalWord = $word;
    }

    if (POLY_GARBAGE == true) {
      if(empty($finalWord)) {
        $finalWord = $input;
      }
      $word3 = genGarbage($finalWord);
      if (POLY_CAPITAL == true) {
        $finalWord = genCap($word3);
      } else {
        $finalWord = $word3;
      }
    }

    if (POLY_NEWLINE == true) {
      if(empty($finalWord)) {
        $finalWord = $input;
      }
      $word2 = genNewline($finalWord);
      $finalWord = $word2;
    }

    // Replace once again with custom variable
    if (POLY_CAPITAL == true) {
      $strFUpdate = str_ireplace("[[F_PASSWORD]]", F_PASSWORD, $finalWord);
      $strFUpdate = str_ireplace("[[F_SUBMIT]]", F_SUBMIT, $strFUpdate);
      $finalWord = $strFUpdate;
    }

    if(empty($finalWord)) {
      return $input;
    } else {
      return $finalWord;
    }
  
  } else {
    return $input;
  }
}

?>
