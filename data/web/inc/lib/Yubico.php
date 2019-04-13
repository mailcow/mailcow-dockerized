<?php
  /**
   * Class for verifying Yubico One-Time-Passcodes
   *
   * @category    Auth
   * @package     Auth_Yubico
   * @author      Simon Josefsson <simon@yubico.com>, Olov Danielson <olov@yubico.com>
   * @copyright   2007-2015 Yubico AB
   * @license     https://opensource.org/licenses/bsd-license.php New BSD License
   * @version     2.0
   * @link        https://www.yubico.com/
   */

require_once 'PEAR.php';

/**
 * Class for verifying Yubico One-Time-Passcodes
 *
 * Simple example:
 * <code>
 * require_once 'Auth/Yubico.php';
 * $otp = "ccbbddeertkrctjkkcglfndnlihhnvekchkcctif";
 *
 * # Generate a new id+key from https://api.yubico.com/get-api-key/
 * $yubi = new Auth_Yubico('42', 'FOOBAR=');
 * $auth = $yubi->verify($otp);
 * if (PEAR::isError($auth)) {
 *    print "<p>Authentication failed: " . $auth->getMessage();
 *    print "<p>Debug output from server: " . $yubi->getLastResponse();
 * } else {
 *    print "<p>You are authenticated!";
 * }
 * </code>
 */
class Auth_Yubico
{
	/**#@+
	 * @access private
	 */

	/**
	 * Yubico client ID
	 * @var string
	 */
	var $_id;

	/**
	 * Yubico client key
	 * @var string
	 */
	var $_key;

	/**
	 * URL part of validation server
	 * @var string
	 */
	var $_url;

	/**
	 * List with URL part of validation servers
	 * @var array
	 */
	var $_url_list;

	/**
	 * index to _url_list
	 * @var int
	 */
	var $_url_index;

	/**
	 * Last query to server
	 * @var string
	 */
	var $_lastquery;

	/**
	 * Response from server
	 * @var string
	 */
	var $_response;

	/**
	 * Flag whether to verify HTTPS server certificates or not.
	 * @var boolean
	 */
	var $_httpsverify;

	/**
	 * Constructor
	 *
	 * Sets up the object
	 * @param    string  $id     The client identity
	 * @param    string  $key    The client MAC key (optional)
	 * @param    boolean $https  noop
	 * @param    boolean $httpsverify  Flag whether to use verify HTTPS
	 *                                 server certificates (optional,
	 *                                 default true)
	 * @access public
	 */
	public function __construct($id, $key = '', $https = 0, $httpsverify = 1)
	{
		$this->_id =  $id;
		$this->_key = base64_decode($key);
		$this->_httpsverify = $httpsverify;
	}

	/**
	 * Specify to use a different URL part for verification.
	 * The default is "api.yubico.com/wsapi/verify".
	 *
	 * @param  string $url  New server URL part to use
	 * @access public
	 */
	function setURLpart($url)
	{
		$this->_url = $url;
	}

	/**
	 * Get next URL part from list to use for validation.
	 *
	 * @return mixed string with URL part of false if no more URLs in list
	 * @access public
	 */
	function getNextURLpart()
	{
	  if ($this->_url_list) $url_list=$this->_url_list;
	  else $url_list=array('https://api.yubico.com/wsapi/2.0/verify',
			       'https://api2.yubico.com/wsapi/2.0/verify',
			       'https://api3.yubico.com/wsapi/2.0/verify',
			       'https://api4.yubico.com/wsapi/2.0/verify',
			       'https://api5.yubico.com/wsapi/2.0/verify');

	  if ($this->_url_index>=count($url_list)) return false;
	  else return $url_list[$this->_url_index++];
	}

	/**
	 * Resets index to URL list
	 *
	 * @access public
	 */
	function URLreset()
	{
	  $this->_url_index=0;
	}

	/**
	 * Add another URLpart.
	 *
	 * @access public
	 */
	function addURLpart($URLpart) 
	{
	  $this->_url_list[]=$URLpart;
	}
	
	/**
	 * Return the last query sent to the server, if any.
	 *
	 * @return string  Request to server
	 * @access public
	 */
	function getLastQuery()
	{
		return $this->_lastquery;
	}

	/**
	 * Return the last data received from the server, if any.
	 *
	 * @return string  Output from server
	 * @access public
	 */
	function getLastResponse()
	{
		return $this->_response;
	}

	/**
	 * Parse input string into password, yubikey prefix,
	 * ciphertext, and OTP.
	 *
	 * @param  string    Input string to parse
	 * @param  string    Optional delimiter re-class, default is '[:]'
	 * @return array     Keyed array with fields
	 * @access public
	 */
	function parsePasswordOTP($str, $delim = '[:]')
	{
	  if (!preg_match("/^((.*)" . $delim . ")?" .
			  "(([cbdefghijklnrtuv]{0,16})" .
			  "([cbdefghijklnrtuv]{32}))$/i",
			  $str, $matches)) {
	    /* Dvorak? */
	    if (!preg_match("/^((.*)" . $delim . ")?" .
			    "(([jxe\.uidchtnbpygk]{0,16})" .
			    "([jxe\.uidchtnbpygk]{32}))$/i",
			    $str, $matches)) {
	      return false;
	    } else {
	      $ret['otp'] = strtr($matches[3], "jxe.uidchtnbpygk", "cbdefghijklnrtuv");
	    }
	  } else {
	    $ret['otp'] = $matches[3];
	  }
	  $ret['password'] = $matches[2];
	  $ret['prefix'] = $matches[4];
	  $ret['ciphertext'] = $matches[5];
	  return $ret;
	}

	/* TODO? Add functions to get parsed parts of server response? */

	/**
	 * Parse parameters from last response
	 *
	 * example: getParameters("timestamp", "sessioncounter", "sessionuse");
	 *
	 * @param  array @parameters  Array with strings representing
	 *                            parameters to parse
	 * @return array  parameter array from last response
	 * @access public
	 */
	function getParameters($parameters)
	{
	  if ($parameters == null) {
	    $parameters = array('timestamp', 'sessioncounter', 'sessionuse');
	  }
	  $param_array = array();
	  foreach ($parameters as $param) {
	    if(!preg_match("/" . $param . "=([0-9]+)/", $this->_response, $out)) {
	      return PEAR::raiseError('Could not parse parameter ' . $param . ' from response');
	    }
	    $param_array[$param]=$out[1];
	  }
	  return $param_array;
	}

	/**
	 * Verify Yubico OTP against multiple URLs
	 * Protocol specification 2.0 is used to construct validation requests
	 *
	 * @param string $token        Yubico OTP
	 * @param int $use_timestamp   1=>send request with &timestamp=1 to
	 *                             get timestamp and session information
	 *                             in the response
	 * @param boolean $wait_for_all  If true, wait until all
	 *                               servers responds (for debugging)
	 * @param string $sl           Sync level in percentage between 0
	 *                             and 100 or "fast" or "secure".
	 * @param int $timeout         Max number of seconds to wait
	 *                             for responses
	 * @return mixed               PEAR error on error, true otherwise
	 * @access public
	 */
	function verify($token, $use_timestamp=null, $wait_for_all=False,
			$sl=null, $timeout=null)
	{
	  /* Construct parameters string */
	  $ret = $this->parsePasswordOTP($token);
	  if (!$ret) {
	    return PEAR::raiseError('Could not parse Yubikey OTP');
	  }
	  $params = array('id'=>$this->_id, 
			  'otp'=>$ret['otp'],
			  'nonce'=>md5(uniqid(rand())));
	  /* Take care of protocol version 2 parameters */
	  if ($use_timestamp) $params['timestamp'] = 1;
	  if ($sl) $params['sl'] = $sl;
	  if ($timeout) $params['timeout'] = $timeout;
	  ksort($params);
	  $parameters = '';
	  foreach($params as $p=>$v) $parameters .= "&" . $p . "=" . $v;
	  $parameters = ltrim($parameters, "&");
	  
	  /* Generate signature. */
	  if($this->_key <> "") {
	    $signature = base64_encode(hash_hmac('sha1', $parameters,
						 $this->_key, true));
	    $signature = preg_replace('/\+/', '%2B', $signature);
	    $parameters .= '&h=' . $signature;
	  }

	  /* Generate and prepare request. */
	  $this->_lastquery=null;
	  $this->URLreset();
	  $mh = curl_multi_init();
	  $ch = array();
	  while($URLpart=$this->getNextURLpart()) 
	    {
	      $query = $URLpart . "?" . $parameters;

	      if ($this->_lastquery) { $this->_lastquery .= " "; }
	      $this->_lastquery .= $query;
	      
	      $handle = curl_init($query);
	      curl_setopt($handle, CURLOPT_USERAGENT, "PEAR Auth_Yubico");
	      curl_setopt($handle, CURLOPT_RETURNTRANSFER, 1);
	      if (!$this->_httpsverify) {
		curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 0);
	      }
	      curl_setopt($handle, CURLOPT_FAILONERROR, true);
	      /* If timeout is set, we better apply it here as well
	         in case the validation server fails to follow it. 
	      */ 
	      if ($timeout) curl_setopt($handle, CURLOPT_TIMEOUT, $timeout);
	      curl_multi_add_handle($mh, $handle);
	      
	      $ch[(int)$handle] = $handle;
	    }

	  /* Execute and read request. */
	  $this->_response=null;
	  $replay=False;
	  $valid=False;
	  do {
	    /* Let curl do its work. */
	    while (($mrc = curl_multi_exec($mh, $active))
		   == CURLM_CALL_MULTI_PERFORM)
	      ;

	    while ($info = curl_multi_info_read($mh)) {
	      if ($info['result'] == CURLE_OK) {

		/* We have a complete response from one server. */

		$str = curl_multi_getcontent($info['handle']);
		$cinfo = curl_getinfo ($info['handle']);
		
		if ($wait_for_all) { # Better debug info
		  $this->_response .= 'URL=' . $cinfo['url'] ."\n"
		    . $str . "\n";
		}

		if (preg_match("/status=([a-zA-Z0-9_]+)/", $str, $out)) {
		  $status = $out[1];

		  /* 
		   * There are 3 cases.
		   *
		   * 1. OTP or Nonce values doesn't match - ignore
		   * response.
		   *
		   * 2. We have a HMAC key.  If signature is invalid -
		   * ignore response.  Return if status=OK or
		   * status=REPLAYED_OTP.
		   *
		   * 3. Return if status=OK or status=REPLAYED_OTP.
		   */
		  if (!preg_match("/otp=".$params['otp']."/", $str) ||
		      !preg_match("/nonce=".$params['nonce']."/", $str)) {
		    /* Case 1. Ignore response. */
		  } 
		  elseif ($this->_key <> "") {
		    /* Case 2. Verify signature first */
		    $rows = explode("\r\n", trim($str));
		    $response=array();
			foreach ($rows as $key => $val) {
		      /* = is also used in BASE64 encoding so we only replace the first = by # which is not used in BASE64 */
		      $val = preg_replace('/=/', '#', $val, 1);
		      $row = explode("#", $val);
		      $response[$row[0]] = $row[1];
		    }
		    
		    $parameters=array('nonce','otp', 'sessioncounter', 'sessionuse', 'sl', 'status', 't', 'timeout', 'timestamp');
		    sort($parameters);
		    $check=Null;
		    foreach ($parameters as $param) {
		      if (array_key_exists($param, $response)) {
			if ($check) $check = $check . '&';
			$check = $check . $param . '=' . $response[$param];
		      }
		    }

		    $checksignature =
		      base64_encode(hash_hmac('sha1', utf8_encode($check),
					      $this->_key, true));

		    if($response['h'] == $checksignature) {
		      if ($status == 'REPLAYED_OTP') {
			if (!$wait_for_all) { $this->_response = $str; }
			$replay=True;
		      } 
		      if ($status == 'OK') {
			if (!$wait_for_all) { $this->_response = $str; }
			$valid=True;
		      }
		    }
		  } else {
		    /* Case 3. We check the status directly */
		    if ($status == 'REPLAYED_OTP') {
		      if (!$wait_for_all) { $this->_response = $str; }
		      $replay=True;
		    } 
		    if ($status == 'OK') {
		      if (!$wait_for_all) { $this->_response = $str; }
		      $valid=True;
		    }
		  }
		}
		if (!$wait_for_all && ($valid || $replay)) 
		  {
		    /* We have status=OK or status=REPLAYED_OTP, return. */
		    foreach ($ch as $h) {
		      curl_multi_remove_handle($mh, $h);
		      curl_close($h);
		    }
		    curl_multi_close($mh);
		    if ($replay) return PEAR::raiseError('REPLAYED_OTP');
		    if ($valid) return true;
		    return PEAR::raiseError($status);
		  }
		
		curl_multi_remove_handle($mh, $info['handle']);
		curl_close($info['handle']);
		unset ($ch[(int)$info['handle']]);
	      }
	      curl_multi_select($mh);
	    }
	  } while ($active);

	  /* Typically this is only reached for wait_for_all=true or
	   * when the timeout is reached and there is no
	   * OK/REPLAYED_REQUEST answer (think firewall).
	   */

	  foreach ($ch as $h) {
	    curl_multi_remove_handle ($mh, $h);
	    curl_close ($h);
	  }
	  curl_multi_close ($mh);
	  
	  if ($replay) return PEAR::raiseError('REPLAYED_OTP');
	  if ($valid) return true;
	  return PEAR::raiseError('NO_VALID_ANSWER');
	}
}
?>
