<?php

namespace SendyPHP;

/**
 * Sendy Class
 */
class SendyPHP
{
    protected $installation_url;
    protected $api_key;
    protected $list_id;
    
    public function __construct(array $config)
    {
        //error checking
        $list_id = @$config['list_id'];
        $installation_url = @$config['installation_url'];
        $api_key = @$config['api_key'];
        
        if (!isset($list_id)) {
            throw new Exception("Required config parameter [list_id] is not set", 1);
        }
        
        if (!isset($installation_url)) {
            throw new Exception("Required config parameter [installation_url] is not set", 1);
        }
        
        if (!isset($api_key)) {
            throw new Exception("Required config parameter [api_key] is not set", 1);
        }

        $this->list_id = $list_id;
        $this->installation_url = $installation_url;
        $this->api_key = $api_key;
    }

    public function setListId($list_id)
    {
        if (!isset($list_id)) {
            throw new Exception("Required config parameter [list_id] is not set", 1);
        }
        
        $this->list_id = $list_id;
    }

    public function getListId()
    {
        return $this->list_id;
    }

    public function subscribe(array $values)
    {
        $type = 'subscribe';

        //Send the subscribe
        $result = strval($this->buildAndSend($type, $values));

        //Handle results
        switch ($result) {
            case '1':
                return array(
                    'status' => true,
                    'message' => 'Subscribed'
                    );
                break;

            case 'Already subscribed.':
                return array(
                    'status' => true,
                    'message' => 'Already subscribed.'
                    );
                break;
            
            default:
                return array(
                    'status' => false,
                    'message' => $result
                    );
                break;
        }



    }

    public function unsubscribe($email)
    {
        $type = 'unsubscribe';
        
        //Send the unsubscribe
        $result = strval($this->buildAndSend($type, array('email' => $email)));

        //Handle results
        switch ($result) {
            case '1':
                return array(
                    'status' => true,
                    'message' => 'Unsubscribed'
                    );
                break;
            
            default:
                return array(
                    'status' => false,
                    'message' => $result
                    );
                break;
        }

    }

    public function substatus($email)
    {
        $type = 'api/subscribers/subscription-status.php';
        
        //Send the request for status
        $result = $this->buildAndSend($type, array(
            'email' => $email,
            'api_key' => $this->api_key,
            'list_id' => $this->list_id
        ));

        //Handle the results
        switch ($result) {
            case 'Subscribed':
            case 'Unsubscribed':
            case 'Unconfirmed':
            case 'Bounced':
            case 'Soft bounced':
            case 'Complained':
                return array(
                    'status' => true,
                    'message' => $result
                    );
                break;
            
            default:
                return array(
                    'status' => false,
                    'message' => $result
                    );
                break;
        }

    }

    public function subcount($list = "")
    {
        $type = 'api/subscribers/active-subscriber-count.php';

        //handle exceptions
        if ($list== "" && $this->list_id == "") {
            throw new Exception("method [subcount] requires parameter [list] or [$this->list_id] to be set.", 1);
        }

        //if a list is passed in use it, otherwise use $this->list_id
        if ($list == "") {
            $list = $this->list_id;
        }

        //Send request for subcount
        $result = $this->buildAndSend($type, array(
            'api_key' => $this->api_key,
            'list_id' => $list
        ));
        
        //Handle the results
        if (is_int($result)) {
            return array(
                'status' => true,
                'message' => $result
            );
        }

        //Error
        return array(
            'status' => false,
            'message' => $result
        );

    }

    private function buildAndSend($type, array $values)
    {

        //error checking
        if (!isset($type)) {
            throw new Exception("Required config parameter [type] is not set", 1);
        }
        
        if (!isset($values)) {
            throw new Exception("Required config parameter [values] is not set", 1);
        }

        //Global options for return
        $return_options = array(
            'list' => $this->list_id,
            'boolean' => 'true'
        );

        //Merge the passed in values with the options for return
        $content = array_merge($values, $return_options);

        //build a query using the $content
        $postdata = http_build_query($content);

        $ch = curl_init($this->installation_url .'/'. $type);
        $redirects = 0;
        // Remove the need to have CURL_FOLLOWREDIRECTS
        $result = $this->curl_redirect_exec($ch, $redirects, true, 10, true, $postdata);
        curl_close($ch);

        return $result;
    }

    /**
     * @param cURL  $ch                     
     * @param int   $redirects              
     * @param bool  $curlopt_returntransfer 
     * @param int   $curlopt_maxredirs      
     * @param bool  $curlopt_header         
     * @param string $postdata
     * @return mixed
     */
    private function curl_redirect_exec($ch, &$redirects, $curlopt_returntransfer = false, $curlopt_maxredirs = 10, $curlopt_header = false, $postdata = '') {
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
        $data = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $exceeded_max_redirects = $curlopt_maxredirs > $redirects;
        $exist_more_redirects = false;
        if ($http_code == 301 || $http_code == 302) {
            if ($exceeded_max_redirects) {
                list($header) = explode("\r\n\r\n", $data, 2);
                $matches = array();
                preg_match('/(Location:|URI:)(.*?)\n/', $header, $matches);
                $url = trim(array_pop($matches));
                $url_parsed = parse_url($url);
                if (isset($url_parsed)) {
                    curl_setopt($ch, CURLOPT_URL, $url);
                    $redirects++;
                    return curl_redirect_exec($ch, $redirects, $curlopt_returntransfer, $curlopt_maxredirs, $curlopt_header);
                }
            }
            else {
                $exist_more_redirects = true;
            }
        }
        if ($data !== false) {
            if (!$curlopt_header)
                list(,$data) = explode("\r\n\r\n", $data, 2);
            if ($exist_more_redirects) return false;
            if ($curlopt_returntransfer) {
                return $data;
            }
            else {
                echo $data;
                if (curl_errno($ch) === 0) return true;
                else return false;
            }
        }
        else {
            return false;
        }
    }
}

