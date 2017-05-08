<?php
date_default_timezone_set('UTC');
require_once __DIR__ .'/../libs/csrf/csrfprotector.php';

if (intval(phpversion('tidy')) >= 7 && !class_exists('\PHPUnit_Framework_TestCase', true)) {
    class_alias('\PHPUnit\Framework\TestCase', '\PHPUnit_Framework_TestCase');
}

/**
 * Wrapper class for testing purpose
 */
class csrfp_wrapper extends csrfprotector
{
    /**
     * Function to provide wrapper methode to set the protected var, requestType
     */
    public static function changeRequestType($type)
    {
        self::$requestType = $type;
    }

    /**
     * Function to check for a string value anywhere within HTTP response headers
     * Returns true on first match of $needle in header names or values
     */
    public static function checkHeader($needle)
    {
        $haystack = xdebug_get_headers();
        foreach ($haystack as $key => $value) {
            if (strpos($value, $needle) !== false)
                return true;
        }
        return false;
    }

    /**
     * Function to return the string value of the last response header
     * identified by name $needle
     */
    public static function getHeaderValue($needle)
    {
        $haystack = xdebug_get_headers();
        foreach ($haystack as $key => $value) {
            if (strpos($value, $needle) === 0) {
                // Deliberately overwrite to accept the last rather than first match
                // as xdebug_get_headers() will accumulate all set headers
                list(,$hvalue) = explode(':', $value, 2);
            }
        }
        return $hvalue;
    } 
}

/**
 * helper methods
 */
class Helper {
    /**
     * Function to recusively delete a dir
     */
    public static function delTree($dir) { 
        $files = array_diff(scandir($dir), array('.','..')); 
        foreach ($files as $file) { 
            (is_dir("$dir/$file")) ? delTree("$dir/$file") : unlink("$dir/$file"); 
        } 
        return rmdir($dir); 
    }
}


/**
 * main test class
 */
class csrfp_test extends PHPUnit_Framework_TestCase
{
    /**
     * @var to hold current configurations
     */
    protected $config = array();

    /**
     * @var log directory for testing
     */
    private $logDir;

    /**
     * Function to be run before every test*() functions.
     */
    public function setUp()
    {
        $this->logDir = __DIR__ .'/logs';

        csrfprotector::$config['jsPath'] = '../js/csrfprotector.js';
        csrfprotector::$config['CSRFP_TOKEN'] = 'csrfp_token';
        csrfprotector::$config['secureCookie'] = false;
        csrfprotector::$config['logDirectory'] = '../test/logs';

        $_SERVER['REQUEST_URI'] = 'temp';       // For logging
        $_SERVER['REQUEST_SCHEME'] = 'http';    // For authorizePost
        $_SERVER['HTTP_HOST'] = 'test';         // For isUrlAllowed
        $_SERVER['PHP_SELF'] = '/index.php';     // For authorizePost
        $_POST[csrfprotector::$config['CSRFP_TOKEN']]
          = $_GET[csrfprotector::$config['CSRFP_TOKEN']] = '123';

        //token mismatch - leading to failed validation
        $_SESSION[csrfprotector::$config['CSRFP_TOKEN']] = array('abc');
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $_SERVER['HTTPS'] = null;

        $this->config = include(__DIR__ .'/config.test.php');

        // Create an instance of config file -- for testing
        $data = file_get_contents(__DIR__ .'/config.test.php');
        file_put_contents(__DIR__ .'/../libs/config.php', $data);

        if (!defined('__TESTING_CSRFP__')) define('__TESTING_CSRFP__', true);
    }

    /**
     * tearDown()
     */
    public function tearDown()
    {
        unlink(__DIR__ .'/../libs/config.php');
        if (is_dir(__DIR__ .'/logs'))
            Helper::delTree(__DIR__ .'/logs');
    }

    /**
     * Function to check refreshToken() functionality
     */
    public function testRefreshToken()
    {
        $val = $_COOKIE[csrfprotector::$config['CSRFP_TOKEN']] = '123abcd';
        $_SESSION[csrfprotector::$config['CSRFP_TOKEN']] = array('123abcd');
        csrfProtector::$config['tokenLength'] = 20;
        csrfProtector::refreshToken();

        $this->assertTrue(strcmp($val, $_SESSION[csrfprotector::$config['CSRFP_TOKEN']][1]) != 0);

        $this->assertTrue(csrfP_wrapper::checkHeader('Set-Cookie'));
        $this->assertTrue(csrfP_wrapper::checkHeader('csrfp_token'));
        $this->assertTrue(csrfp_wrapper::checkHeader($_SESSION[csrfprotector::$config['CSRFP_TOKEN']][1]));
    }

    /**
     * test secure flag is set in the token cookie when requested
     */
    public function testSecureCookie()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION[csrfprotector::$config['CSRFP_TOKEN']] = array('123abcd');

        csrfprotector::$config['secureCookie'] = false;
        csrfprotector::refreshToken();
        $this->assertNotRegExp('/; secure/', csrfp_wrapper::getHeaderValue('Set-Cookie'));

        csrfprotector::$config['secureCookie'] = true;
        csrfprotector::refreshToken();
        $this->assertRegExp('/; secure/', csrfp_wrapper::getHeaderValue('Set-Cookie'));
    }

    /**
     * test authorise post -> log directory exception
     */
    public function testAuthorisePost_logdirException()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        csrfprotector::$config['logDirectory'] = 'unknown_location';

        try {
            csrfprotector::authorizePost();
        } catch (logDirectoryNotFoundException $ex) {
            $this->assertTrue(true);
            return;;
        }
        $this->fail('logDirectoryNotFoundException has not been raised.');
    }

    /**
     * test authorise post -> action = 403, forbidden
     */
    public function testAuthorisePost_failedAction_1()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        csrfprotector::$config['verifyGetFor'] = array('http://test/index*');
        csrfprotector::$config['logDirectory'] = '../log';
        csrfprotector::$config['failedAuthAction']['POST'] = 0;
        csrfprotector::$config['failedAuthAction']['GET'] = 0;

        //csrfprotector::authorizePost();
        $this->markTestSkipped('Cannot add tests as code exit here');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        csrfp_wrapper::changeRequestType('GET');
        //csrfprotector::authorizePost();

        $this->markTestSkipped('Cannot add tests as code exit here');
    }

    /**
     * test authorise post -> strip $_GET, $_POST
     */
    public function testAuthorisePost_failedAction_2()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        csrfprotector::$config['logDirectory'] = '../log';
        csrfprotector::$config['verifyGetFor'] = array('http://test/index*');
        csrfprotector::$config['failedAuthAction']['POST'] = 1;
        csrfprotector::$config['failedAuthAction']['GET'] = 1;

        $_POST = array('param1' => 1, 'param2' => 2);
        csrfprotector::authorizePost();
        $this->assertEmpty($_POST);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        csrfp_wrapper::changeRequestType('GET');
        $_GET = array('param1' => 1, 'param2' => 2);

        csrfprotector::authorizePost();
        $this->assertEmpty($_GET);
    }

    /**
     * test authorise post -> redirect
     */
    public function testAuthorisePost_failedAction_3()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        csrfprotector::$config['logDirectory'] = '../log';
        csrfprotector::$config['verifyGetFor'] = array('http://test/index*');
        csrfprotector::$config['errorRedirectionPage'] = 'http://test';
        csrfprotector::$config['failedAuthAction']['POST'] = 2;
        csrfprotector::$config['failedAuthAction']['GET'] = 2;

        //csrfprotector::authorizePost();
        $this->markTestSkipped('Cannot add tests as code exit here');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        csrfp_wrapper::changeRequestType('GET');
        //csrfprotector::authorizePost();
        $this->markTestSkipped('Cannot add tests as code exit here');
    }

    /**
     * test authorise post -> error message & exit
     */
    public function testAuthorisePost_failedAction_4()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        csrfprotector::$config['logDirectory'] = '../log';
        csrfprotector::$config['verifyGetFor'] = array('http://test/index*');
        csrfprotector::$config['customErrorMessage'] = 'custom error message';
        csrfprotector::$config['failedAuthAction']['POST'] = 3;
        csrfprotector::$config['failedAuthAction']['POST'] = 3;

        //csrfprotector::authorizePost();
        $this->markTestSkipped('Cannot add tests as code exit here');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        csrfp_wrapper::changeRequestType('GET');
        //csrfprotector::authorizePost();
        $this->markTestSkipped('Cannot add tests as code exit here');
    }

    /**
     * test authorise post -> 500 internal server error
     */
    public function testAuthorisePost_failedAction_5()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        csrfprotector::$config['logDirectory'] = '../log';
        csrfprotector::$config['verifyGetFor'] = array('http://test/index*');
        csrfprotector::$config['failedAuthAction']['POST'] = 4;
        csrfprotector::$config['failedAuthAction']['GET'] = 4;

        //csrfprotector::authorizePost();
        //$this->markTestSkipped('Cannot add tests as code exit here');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        csrfp_wrapper::changeRequestType('GET');
        //csrfprotector::authorizePost();
        //csrfp_wrapper::checkHeader('500');
        //$this->markTestSkipped('Cannot add tests as code exit here');
    }

    /**
     * test authorise post -> default action: strip $_GET, $_POST
     */
    public function testAuthorisePost_failedAction_6()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        csrfprotector::$config['logDirectory'] = '../log';
        csrfprotector::$config['verifyGetFor'] = array('http://test/index*');
        csrfprotector::$config['failedAuthAction']['POST'] = 10;
        csrfprotector::$config['failedAuthAction']['GET'] = 10;

        $_POST = array('param1' => 1, 'param2' => 2);
        csrfprotector::authorizePost();
        $this->assertEmpty($_POST);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        csrfp_wrapper::changeRequestType('GET');
        $_GET = array('param1' => 1, 'param2' => 2);

        csrfprotector::authorizePost();
        $this->assertEmpty($_GET);
    }

    /**
     * test authorise success
     */
    public function testAuthorisePost_success()
    {

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST[csrfprotector::$config['CSRFP_TOKEN']]
            = $_GET[csrfprotector::$config['CSRFP_TOKEN']]
            = $_SESSION[csrfprotector::$config['CSRFP_TOKEN']][0];
        $temp = $_SESSION[csrfprotector::$config['CSRFP_TOKEN']];

        csrfprotector::authorizePost(); //will create new session and cookies
        $this->assertFalse($temp == $_SESSION[csrfprotector::$config['CSRFP_TOKEN']][0]);
        $this->assertTrue(csrfp_wrapper::checkHeader('Set-Cookie'));
        $this->assertTrue(csrfp_wrapper::checkHeader('csrfp_token'));
        // $this->assertTrue(csrfp_wrapper::checkHeader($_SESSION[csrfprotector::$config['CSRFP_TOKEN']][0]));  // Combine these 3 later

        // For get method
        $_SERVER['REQUEST_METHOD'] = 'GET';
        csrfp_wrapper::changeRequestType('GET');
        $_POST[csrfprotector::$config['CSRFP_TOKEN']]
            = $_GET[csrfprotector::$config['CSRFP_TOKEN']]
            = $_SESSION[csrfprotector::$config['CSRFP_TOKEN']][0];
        $temp = $_SESSION[csrfprotector::$config['CSRFP_TOKEN']];

        csrfprotector::authorizePost(); //will create new session and cookies
        $this->assertFalse($temp == $_SESSION[csrfprotector::$config['CSRFP_TOKEN']]);
        $this->assertTrue(csrfp_wrapper::checkHeader('Set-Cookie'));
        $this->assertTrue(csrfp_wrapper::checkHeader('csrfp_token'));
        // $this->assertTrue(csrfp_wrapper::checkHeader($_SESSION[csrfprotector::$config['CSRFP_TOKEN']][0]));  // Combine these 3 later
    }

    /**
     * test for generateAuthToken()
     */
    public function testGenerateAuthToken()
    {
        csrfprotector::$config['tokenLength'] = 20;
        $token1 = csrfprotector::generateAuthToken();
        $token2 = csrfprotector::generateAuthToken();

        $this->assertFalse($token1 == $token2);
        $this->assertEquals(strlen($token1), 20);
        $this->assertRegExp('/^[a-z0-9]{20}$/', $token1);

        csrfprotector::$config['tokenLength'] = 128;
        $token = csrfprotector::generateAuthToken();
        $this->assertEquals(strlen($token), 128);
        $this->assertRegExp('/^[a-z0-9]{128}$/', $token);
    }

    /**
     * test ob_handler_function
     */
    public function testob_handler()
    {
        csrfprotector::$config['disabledJavascriptMessage'] = 'test message';
        csrfprotector::$config['jsUrl'] = 'http://localhost/test/csrf/js/csrfprotector.js';

        $testHTML = '<html>';
        $testHTML .= '<head><title>1</title>';
        $testHTML .= '<body onload="test()">';
        $testHTML .= '-- some static content --';
        $testHTML .= '-- some static content --';
        $testHTML .= '</body>';
        $testHTML .= '</head></html>';

        $modifiedHTML = csrfprotector::ob_handler($testHTML, 0);
        $inpLength = strlen($testHTML);
        $outLength = strlen($modifiedHTML);

        //Check if file has been modified
        $this->assertFalse($outLength == $inpLength);
        $this->assertTrue(strpos($modifiedHTML, '<noscript>') !== false);
        $this->assertTrue(strpos($modifiedHTML, '<script') !== false);

    }

    /**
     * test ob_handler_function for output filter
     */
    public function testob_handler_positioning()
    {
        csrfprotector::$config['disabledJavascriptMessage'] = 'test message';
        csrfprotector::$config['jsUrl'] = 'http://localhost/test/csrf/js/csrfprotector.js';

        $testHTML = '<html>';
        $testHTML .= '<head><title>1</title>';
        $testHTML .= '<body onload="test()">';
        $testHTML .= '-- some static content --';
        $testHTML .= '-- some static content --';
        $testHTML .= '</body>';
        $testHTML .= '</head></html>';

        $modifiedHTML = csrfprotector::ob_handler($testHTML, 0);

        $this->assertEquals(strpos($modifiedHTML, '<body') + 23, strpos($modifiedHTML, '<noscript'));
        // Check if content before </body> is </script> #todo
        //$this->markTestSkipped('todo, add appropriate test here');
    }

    /**
     * testing exception in logging function
     */
    public function testgetCurrentUrl()
    {
        $stub = new ReflectionClass('csrfprotector');
        $method = $stub->getMethod('getCurrentUrl');
        $method->setAccessible(true);
        $this->assertEquals($method->invoke(null, array()), "http://test/index.php");

        $tmp_request_scheme = $_SERVER['REQUEST_SCHEME'];
        unset($_SERVER['REQUEST_SCHEME']);

        // server-https is not set
        $this->assertEquals($method->invoke(null, array()), "http://test/index.php");

        $_SERVER['HTTPS'] = 'on';
        $this->assertEquals($method->invoke(null, array()), "https://test/index.php");
        unset($_SERVER['HTTPS']);

        $_SERVER['REQUEST_SCHEME'] = "https";
        $this->assertEquals($method->invoke(null, array()), "https://test/index.php");

        $_SERVER['REQUEST_SCHEME'] = $tmp_request_scheme;
    }

    /**
     * testing exception in logging function
     */
    public function testLoggingException()
    {
        $stub = new ReflectionClass('csrfprotector');
        $method = $stub->getMethod('logCSRFattack');
        $method->setAccessible(true);

        try {
            $method->invoke(null, array());
            $this->fail("logFileWriteError was not caught");
        } catch (Exception $ex) {
            // pass
            $this->assertTrue(true);
        }

        if (!is_dir($this->logDir))
            mkdir($this->logDir);
        $method->invoke(null, array());
        $this->assertTrue(file_exists($this->logDir ."/" .date("m-20y") .".log"));
    }

    /**
     * Tests isUrlAllowed() function for various urls and configuration
     */
    public function testisURLallowed()
    {
        csrfprotector::$config['verifyGetFor'] = array('http://test/delete*', 'https://test/*');

        $_SERVER['PHP_SELF'] = '/nodelete.php';
        $this->assertTrue(csrfprotector::isURLallowed());

        $_SERVER['PHP_SELF'] = '/index.php';
        $this->assertTrue(csrfprotector::isURLallowed('http://test/index.php'));

        $_SERVER['PHP_SELF'] = '/delete.php';
        $this->assertFalse(csrfprotector::isURLallowed('http://test/delete.php'));

        $_SERVER['PHP_SELF'] = '/delete_user.php';
        $this->assertFalse(csrfprotector::isURLallowed('http://test/delete_users.php'));

        $_SERVER['REQUEST_SCHEME'] = 'https';
        $_SERVER['PHP_SELF'] = '/index.php';
        $this->assertFalse(csrfprotector::isURLallowed('https://test/index.php'));

        $_SERVER['PHP_SELF'] = '/delete_user.php';
        $this->assertFalse(csrfprotector::isURLallowed('https://test/delete_users.php'));
    }

    /**
     * Test for exception thrown when env variable is set by mod_csrfprotector
     */
    public function testModCSRFPEnabledException()
    {
        putenv('mod_csrfp_enabled=true');
        $temp = $_COOKIE[csrfprotector::$config['CSRFP_TOKEN']] = 'abc';
        $_SESSION[csrfprotector::$config['CSRFP_TOKEN']] = array('abc');

        csrfProtector::$config = array();
        csrfProtector::init();

        // Assuming no config was added
        $this->assertTrue(count(csrfProtector::$config) == 0);
        
        // unset the env variable
        putenv('mod_csrfp_enabled');
    }

    /**
     * Test for exception thrown when init() method is called multiple times
     */
    public function testMultipleInitializeException()
    {
        csrfProtector::$config = array();
        $this->assertTrue(count(csrfProtector::$config) == 0);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        csrfProtector::init();

        $this->assertTrue(count(csrfProtector::$config) == 11);
        try {
            csrfProtector::init();
            $this->fail("alreadyInitializedException not raised");
        }  catch (alreadyInitializedException $ex) {
            // pass
            $this->assertTrue(true);
        } catch (Exception $ex) {
            $this->fail("exception other than alreadyInitializedException failed");            
        }
    }
}
