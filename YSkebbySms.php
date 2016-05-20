<?php

/**
 * Simple Application Component that allows you to send SMSes using Clickatell Gateway easily.
 * Yii::app()->sms()->send(array('to'=>'+40746xxxxxx','message'=>'hello world!');
 * 
 * @link https://github.com/YetOpen/YSkebbySms
 * @author Lorenzo Milesi <maxxer@yetopen.it>
 * @version 0.1
 * @uses CURL
 * @uses Yii::log()
 * When a clickatell request fails, errors are loggeg with 'warning' trace and 'ext.clickatell' category
 * This class does not validate any SMS parameters, not even the phone no.
 */
class YSkebbySms extends \yii\base\Component {

    const CHARSET_UTF8 = 'UTF-8';
    const CHARSET_ISO = 'ISO-8859-1';

    const TYPE_CLASSIC = 'send_sms_classic';
    const TYPE_CLASSIC_PLUS = 'send_sms_classic_plus';
    const TYPE_BASIC = 'send_sms_basic';
    const TEST_PREFIX = 'test_';

    const CREDIT_TYPE_CREDIT = "credit_left";
    const CREDIT_TYPE_CLASSIC = "classic_sms";
    const CREDIT_TYPE_BASIC = "basic_sms";

    /** @var mixed Skebby action. */
    public $method = self::TYPE_CLASSIC;

    /** @var mixed Sender name. Must be max 11 chars. */
    public $sender_string = "YSkebbySms";

    /** @var mixed Sender number. Must be in international format without + sign or leading zeros. You must be allowed on
                      Skebby site to send using this number */
    public $sender_number = null;

    /** @var mixed Mobile phone no to send the SMS to. It can be an array with more numbers. */
    public $to;

    /** @var string The message to be send */
    public $message;

    /** @var string Your Clickatell username */
    public $username;

    /** @var string Your Clickatell password */
    public $password;

    /** @var boolean Whether to use https */
    public $ssl = false;

    /** @var boolean Whether to use test mode (not really sending the message, just testing the gateway) */
    public $test = false;

    // component level
    /** @var boolean Whether to print debug information on screen. Useful when debugging from shell */
    public $debug = false;

    protected $_url = "%s://gateway.skebby.it/api/send/smseasy/advanced/http.php";
    protected $_return;

    /* FIXME
    // rules() equivalent set of validations
    // See http://www.skebby.it/business/index/send-docs/
    protected $_rules = array (
            array ('recipients', 'required'),       
            // FIXME recipients can be an array!
            array ('recipients, sender_number', 'match', 'allowEmpty'=>true, 'pattern'=>'/^[1-9][0-9]+/'),
            array ('sender_string', 'match', 'allowEmpty'=>true, 'pattern'=>'/[a-zA-Z0-9\ .]/'),
            array ('charset', 'range','range'=>array(self::CHARSET_UTF8,self::CHARSET_ISO),
            array ('text', 'length', 'max'=>765),
            // FIXME use conditional validator to check if the user specified both sender_name and sender_number
        );
    }
    */

    /**
     * Sends the SMS request to Clickatell gateway. 
     * @param array $config A key=>value array to configure the message
     * @return bool Whether the action was successful
     */
    public function send($config) {
        // Runtime configuration
        foreach ($config as $k => $v) {
            $this->$k = $v;
        }

        $params = array(
            'username' => $this->username,
            'password' => $this->password,
            'method' => ($this->test?self::TEST_PREFIX:"").$this->method,
            'sender_string' => $this->sender_string,
            'recipients[]' => $this->to, // FIXME
            'text' => $this->message,
        );
        // Send the request
        $this->skebbyRequest($params);
        return $this->_return;
    }

    /**
     * Get Skebby credit (remaining SMSes)
     * @param string $type Type of credit to get. If missing returns an array with all available credits
     * @param array $config A key=>value array to configure the message
     * @return mixed If successful the number of remainig messages, bool FALSE on faluire
     */
    public function getCredit($type = NULL, $config = NULL) {
        // Runtime configuration
        if ($config != NULL) {
            foreach ($config as $k => $v) {
                $this->$k = $v;
            }
        }

        $params = array(
            'username' => $this->username,
            'password' => $this->password,
            'method' => "get_credit",
        );
        // Send the request
        if ($this->skebbyRequest($params) === FALSE)
            return FALSE;
        if ($type == NULL)
            return $this->_return;
        else
            return $this->_return[$type];
    }

    /**
     * Sends an request to Clickatell HTTP API. Error messages are logged.
     * @param string $method The method used. Common mehtods are send, ping and auth. For more check the Clickatell docs.
     * @param array $params Key=>Value array for POST data. Values must be URL-encoded.
     * @return mixed The returned information (without "OK: " status) or FALSE if it fails. 
     */
    protected function skebbyRequest($params) {
        if ($this->debug === true) {
            Yii::log('Skebby SMS request start: '.implode("|",$params), 'debug', 'ext.YSkebbySms'); //FIXME
        }
        
        $request = curl_init();
		curl_setopt($request,CURLOPT_CONNECTTIMEOUT,10);
		curl_setopt($request,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($request,CURLOPT_TIMEOUT,60);
		curl_setopt($request,CURLOPT_USERAGENT,'Generic Client');
        curl_setopt($request,CURLOPT_POST, count($params));
        curl_setopt($request,CURLOPT_POSTFIELDS, http_build_query($params));
        if ($this->ssl === true) {
		    curl_setopt($request,CURLOPT_URL,sprintf($this->_url,"https"));
            curl_setopt($request,CURLOPT_SSL_VERIFYHOST, 2);
        } else {
		    curl_setopt($request,CURLOPT_URL,sprintf($this->_url,"http"));
        }
        $response = curl_exec($request);

        if ($this->debug === true) {
            Yii::log('Skebby SMS reply:'.$response, 'debug', 'ext.YSkebbySms');
            var_dump($response);
        }

        // if the request fails
        if ($response === false) {
            Yii::log('Skebby SMS request failed: unknown network error', 'warning', 'ext.YSkebbySms');
            $this->_return ['message'] = Yii::t('ext.YSkebbySms','Unknown network error');
            $this->_return ['result'] = FALSE;
            return;
        }
        parse_str($response,$this->_return);

        if ($this->_return ['status'] == "failed") {
            $this->_return ['result'] = FALSE;
        } else {
            $this->_return ['result'] = TRUE;
        }

        return;
    }

}
