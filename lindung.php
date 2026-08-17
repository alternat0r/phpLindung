<?php

/*
==============================================
 Multiple login supported
 Usage:
    'your_username' => password_hash('your_password', PASSWORD_DEFAULT)

 Generate a hash for a new password with:
    php -r "echo password_hash('your_password', PASSWORD_DEFAULT), PHP_EOL;"
============================================== */
$LOGIN_INFORMATION = array(
  'admin'    => '$2y$10$FKqSOfd6ogvD.WYV48uNMeo5TU7A3iXtdXD3.dlIVvG8iT5qDjrVG', // admin654 - CHANGE ME
  'henshin'  => '$2y$10$XaTtZ98Azqn69qHmAd/N6urDELshxxPtW32dJ.WtdigfFPPDNZxV.'  // hakutominami - CHANGE ME
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
// Set to true only if you're behind a reverse proxy/load balancer you trust to set
// X-Forwarded-Proto/X-Forwarded-For honestly (otherwise these headers are
// client-controllable and unsafe to trust). Needed for the Secure cookie flag and
// rate-limiting-by-IP to work correctly when requests are relayed through a proxy.
define('TRUST_PROXY_HEADERS', false);

/*
==============================================
  Rate limiting on failed login attempts, keyed
  by client IP. State is file-based (no database
  required) so it stays a single-file drop-in.
============================================== */
define('RATE_LIMIT_ON', true);
define('RATE_LIMIT_MAX_ATTEMPTS', 5);        // failed attempts allowed per window
define('RATE_LIMIT_WINDOW_SECONDS', 300);    // window the attempts above are counted over
define('RATE_LIMIT_LOCKOUT_SECONDS', 300);   // lockout duration once the limit is hit
// Where attempt counters are stored. Defaults to the system temp dir so nothing needs
// to be writable inside the web root. On shared hosting where the temp dir isn't
// private to your account, point this at a directory only your app can read/write.
define('RATE_LIMIT_DIR', sys_get_temp_dir() . '/lindung_ratelimit_' . substr(hash('sha256', __FILE__), 0, 12));

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

/*
==============================================
  SECRET_KEY signs the session cookie (HMAC).
  Anyone who knows this value can forge a valid
  login session, so it MUST be changed to a
  random, private value before deployment - it
  is not safe to leave the default below.
  Generate one with:
    php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
============================================== */
define('SECRET_KEY', 'CHANGE_ME_TO_A_RANDOM_SECRET_BEFORE_DEPLOYING');

if (strpos(SECRET_KEY, 'CHANGE_ME') === 0 || strlen(SECRET_KEY) < 20) {
  die('lindung.php: SECRET_KEY looks like it is still the default placeholder (or too short to be a real secret). Generate a random value (e.g. `php -r "echo bin2hex(random_bytes(32));"`) and set it before using this script.');
}

define('COOKIE_NAME', hash('sha1', SECRET_KEY . '_cookie'));

$timeoutMinutesInt = (int) TIMEOUT_MINUTES;
$sessionExpireAt = ($timeoutMinutesInt === 0) ? 0 : time() + $timeoutMinutesInt * 60;
// Browser-side cookie lifetime; 0 = session cookie (expires when browser closes)
$cookieLifetime = $sessionExpireAt;

if (!function_exists('setSessionCookie')) {
  function setSessionCookie($value, $expire) {
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    if (!$secure && TRUST_PROXY_HEADERS && !empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
      $secure = strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https';
    }
    if (PHP_VERSION_ID >= 70300) {
      setcookie(COOKIE_NAME, $value, array(
        'expires' => $expire,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
      ));
    } else {
      // Pre-7.3 fallback: SameSite via the path-string trick.
      setcookie(COOKIE_NAME, $value, $expire, '/; SameSite=Lax', '', $secure, true);
    }
  }
}

if (!function_exists('base64UrlEncode')) {
  function base64UrlEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
  }
}

if (!function_exists('base64UrlDecode')) {
  function base64UrlDecode($data) {
    return base64_decode(strtr($data, '-_', '+/'));
  }
}

if (!function_exists('hash_equals')) {
  // Polyfill for PHP < 5.6, where hash_equals() doesn't exist yet.
  function hash_equals($known, $user) {
    if (!is_string($known) || !is_string($user) || strlen($known) !== strlen($user)) {
      return false;
    }
    $diff = 0;
    for ($i = 0; $i < strlen($known); $i++) {
      $diff |= ord($known[$i]) ^ ord($user[$i]);
    }
    return $diff === 0;
  }
}

/*
==============================================
  Session token format: identity.expireAt.hmac
  The HMAC covers identity+expireAt so a token
  cannot be replayed with a different identity
  or a forged/extended expiry, and expiry is
  enforced server-side (not left to the browser).
============================================== */
if (!function_exists('generateSessionToken')) {
  function generateSessionToken($identity, $expireAt) {
    $payload = base64UrlEncode($identity) . '.' . $expireAt;
    $sig = hash_hmac('sha256', $payload, SECRET_KEY);
    return $payload . '.' . $sig;
  }
}

if (!function_exists('verifySessionToken')) {
  function verifySessionToken($token) {
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
      return false;
    }
    list($encIdentity, $expireAt, $sig) = $parts;
    $payload = $encIdentity . '.' . $expireAt;
    $expectedSig = hash_hmac('sha256', $payload, SECRET_KEY);
    if (!hash_equals($expectedSig, $sig)) {
      return false;
    }
    $expireAt = (int) $expireAt;
    if ($expireAt !== 0 && time() > $expireAt) {
      return false;
    }
    $identity = base64UrlDecode($encIdentity);
    if ($identity === false || $identity === '') {
      return false;
    }
    return $identity;
  }
}

if (!function_exists('getClientIp')) {
  function getClientIp() {
    if (TRUST_PROXY_HEADERS && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
      $forwardedFor = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
      return trim($forwardedFor[0]);
    }
    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
  }
}

if (!function_exists('rateLimitFile')) {
  function rateLimitFile($ip) {
    return RATE_LIMIT_DIR . '/' . hash('sha256', SECRET_KEY . '|' . $ip) . '.json';
  }
}

if (!function_exists('rateLimitOpen')) {
  // Opens (creating if needed) the per-IP state file and returns the handle, or
  // false if storage isn't available - callers should fail open in that case
  // rather than let a storage problem lock everyone out of the site.
  function rateLimitOpen($ip) {
    if (!is_dir(RATE_LIMIT_DIR)) {
      @mkdir(RATE_LIMIT_DIR, 0700, true);
    }
    return @fopen(rateLimitFile($ip), 'c+');
  }
}

if (!function_exists('rateLimitRead')) {
  function rateLimitRead($fp) {
    rewind($fp);
    $raw = stream_get_contents($fp);
    $data = $raw ? json_decode($raw, true) : null;
    if (!is_array($data)) {
      $data = array('attempts' => 0, 'window_started_at' => time(), 'locked_until' => 0);
    }
    return $data;
  }
}

if (!function_exists('rateLimitSecondsRemaining')) {
  // Returns seconds until the lockout for this IP clears, or 0 if it isn't locked out.
  function rateLimitSecondsRemaining($ip) {
    if (!RATE_LIMIT_ON) {
      return 0;
    }
    $fp = rateLimitOpen($ip);
    if (!$fp) {
      return 0;
    }
    flock($fp, LOCK_SH);
    $data = rateLimitRead($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    $remaining = $data['locked_until'] - time();
    return $remaining > 0 ? $remaining : 0;
  }
}

if (!function_exists('rateLimitRecordFailure')) {
  function rateLimitRecordFailure($ip) {
    if (!RATE_LIMIT_ON) {
      return;
    }
    $fp = rateLimitOpen($ip);
    if (!$fp) {
      return;
    }
    flock($fp, LOCK_EX);
    $data = rateLimitRead($fp);
    $now = time();

    if ($now - $data['window_started_at'] > RATE_LIMIT_WINDOW_SECONDS) {
      $data['attempts'] = 0;
      $data['window_started_at'] = $now;
    }

    $data['attempts']++;
    if ($data['attempts'] >= RATE_LIMIT_MAX_ATTEMPTS) {
      $data['locked_until'] = $now + RATE_LIMIT_LOCKOUT_SECONDS;
      $data['attempts'] = 0;
      $data['window_started_at'] = $now;
    }

    rewind($fp);
    ftruncate($fp, 0);
    fwrite($fp, json_encode($data));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
  }
}

if (!function_exists('rateLimitReset')) {
  function rateLimitReset($ip) {
    if (!RATE_LIMIT_ON) {
      return;
    }
    @unlink(rateLimitFile($ip));
  }
}

if (isset($_GET['logout'])) {
  setSessionCookie('', time() - 3600);
  header('Location: ' . LOGOUT_URL);
  exit();
}

if (!function_exists('showLoginPasswordProtect')) {
  function showLoginPasswordProtect($error_msg) {
    // Prevent the login form from being framed (clickjacking) or MIME-sniffed.
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');

    $inputStyle = "width:100%; height:44px; margin:0 0 12px; padding:0 14px; border:1px solid #d9dbe3; border-radius:10px; font-size:15px; color:#20222b; background:#fbfbfd; box-sizing:border-box; outline:none;";
    $buttonStyle = "width:100%; height:44px; border:none; border-radius:10px; background:#4c4fe0; color:#ffffff; font-size:15px; font-weight:600; letter-spacing:0.02em; cursor:pointer;";

    if( !empty($error_msg)) { $strErrorMessage = "<div style='background:#fdecec; color:#c0392b; font-size:13px; font-weight:600; padding:10px 14px; border-radius:10px; margin:0 0 16px; text-align:center;'>".$error_msg."</div>"; } else { $strErrorMessage = ""; }
    if (USE_USERNAME) { $strUsername = "<input required style='".$inputStyle."' type='text' name='".F_LOGIN."' placeholder='Username'/>"; } else { $strUsername = ""; };

/*
==============================================
  You can modify the following login page layout
  according to your needs.
============================================== */
    $strBody = "<html>
<head>
<meta name='viewport' content='width=device-width, initial-scale=1'>
<title>☺</title>
<link rel='icon' type='image/png' href='data:image/png;base64,iVBORw0KGgo=''>
</head>
<body style='margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center; background:#f0f1f6; font-family:system-ui, -apple-system, sans-serif;'>
<div style='width:100%; max-width:320px; margin:0 20px; padding:36px 30px; background:#ffffff; border-radius:16px; box-shadow:0 12px 32px rgba(30,32,60,0.10); box-sizing:border-box; text-align:center;'>
<div style='font-size:30px; line-height:1; margin:0 0 20px;'>☺</div>
".$strErrorMessage."<form method='post'>
".$strUsername."<input required style='".$inputStyle."' type='password' name='[[F_PASSWORD]]' placeholder='Password'>
<button style='".$buttonStyle."' type='submit' name='[[F_SUBMIT]]'>▶</button>
</form>
</div>
</body>
</html>";
    echo polyEverything($strBody);
    die();
  }
}

if (isset($_POST[F_PASSWORD])) {
  $clientIp = getClientIp();
  $lockoutRemaining = rateLimitSecondsRemaining($clientIp);

  if ($lockoutRemaining > 0) {
    $waitMinutes = (int) ceil($lockoutRemaining / 60);
    showLoginPasswordProtect('Too many attempts. Try again in ' . $waitMinutes . ' minute' . ($waitMinutes === 1 ? '' : 's') . '.');
  }

  $login = isset($_POST[F_LOGIN]) ? $_POST[F_LOGIN] : '';
  $pass = $_POST[F_PASSWORD];

  $identity = null;
  foreach ($LOGIN_INFORMATION as $key => $hash) {
    if (USE_USERNAME && $key !== $login) {
      continue;
    }
    if (password_verify($pass, $hash)) {
      $identity = $key;
      break;
    }
  }

  if ($identity === null) {
    rateLimitRecordFailure($clientIp);
    showLoginPasswordProtect(ERR_MESSAGE);
  } else {
    rateLimitReset($clientIp);
    setSessionCookie(generateSessionToken($identity, $sessionExpireAt), $cookieLifetime);
    unset($_POST[F_LOGIN]);
    unset($_POST[F_PASSWORD]);
    unset($_POST[F_SUBMIT]);
  }

} else {
  if (!isset($_COOKIE[COOKIE_NAME])) {
    showLoginPasswordProtect("");
  }

  $identity = verifySessionToken($_COOKIE[COOKIE_NAME]);
  $found = ($identity !== false && array_key_exists($identity, $LOGIN_INFORMATION));

  if ($found && TIMEOUT_CHECK_ACTIVITY) {
    setSessionCookie(generateSessionToken($identity, $sessionExpireAt), $cookieLifetime);
  }

  if (!$found) {
    showLoginPasswordProtect("");
  }
}

/*
==============================================
  Used to generate sha256 based on current
  hour. Then, take out first 5 letter to be
  use as variable name. Otherwise, use random
  MD5 hash and take out 5 letter from it.
============================================== */
function genStr($salt) {
  if(isset($salt)) {
    $seed = hash("sha256", date("H") . $salt);
    $str = substr($seed, 0, 5);
  } else {
    $str = substr(hash("md5", rand(99, 999)), 0, 5);
  }
  return $str;
}

/*
==============================================
  To generate random spacing
============================================== */
function genSpace($input) {
  $spc = "";
  if (POLY_ON == true) {
    if (POLY_SPACE == true) {
      $arr1 = str_split($input);
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
  if (empty($spc)) {
    $spc = $input;
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
  if (empty($spc)) {
    $spc = $input;
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
  $finalWord = $input;

  if (POLY_ON == true) {
    if (POLY_CAPITAL == true) {
      $finalWord = genCap($finalWord);
    }

    if (POLY_SPACE == true) {
      $finalWord = genSpace($finalWord);
    }

    if (POLY_GARBAGE == true) {
      $word3 = genGarbage($finalWord);
      if (POLY_CAPITAL == true) {
        $finalWord = genCap($word3);
      } else {
        $finalWord = $word3;
      }
    }

    if (POLY_NEWLINE == true) {
      $finalWord = genNewline($finalWord);
    }
  }

  // Placeholders must always be substituted, regardless of POLY_ON/POLY_CAPITAL,
  // otherwise the login form ships with a literal "[[F_PASSWORD]]" field name
  // and the login POST handler can never match it.
  $finalWord = str_ireplace("[[F_PASSWORD]]", F_PASSWORD, $finalWord);
  $finalWord = str_ireplace("[[F_SUBMIT]]", F_SUBMIT, $finalWord);

  return empty($finalWord) ? $input : $finalWord;
}

?>
