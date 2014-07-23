<?php 
/**
 *  A Wordpress Client for the Wolfnet API
 */


class Wolfnet_Api_Wp_Client
{
        
    /**
     * if set to true print extended information about the request and the response as well as the data
     * @var boolean
     */
    public $debug = false;

    /**
     * The hostname for the API server.
     * @var string
     */
    private $host = 'api.dev.wolfnet.com';

    /**
     * The version of the API to make requests of.
     * @var string
     */
    private $version = "1";

    /**
     * The temporary token retrieved after authorizing the with the Api key
     * @var string
     */
    private $apiToken; 

    /**
     * This property is a unique identifier for a value in the WordPress Transient API where
     * references to other transient values are stored.
     * must be less than 12 characters 
     * @var string
     */
    public $transientIndexKey = 'wnt_trans';


    /**
     * This method is the public interface for making requests of the API.
     * @param  string $key      The client's API key.
     * @param  string $resource The URI endpoint being requested from the API.
     * @param  string $method   The HTTP method the request should be submitted as.
     * @param  array  $data     Any query string or body data to be include with the request.
     * @param  array  $headers  Any header data to be included with the request.
     * @return [type]           Data returned by the API server.
     */
    public function sendRequest(
        $key, 
        $resource, 
        $method = "GET", 
        $data = array(), 
        $headers = array()
    )
    {
        return $this->rawRequest($key, $resource, $method, $data, $headers);

    }

    
    /**
     * [rawRequest description]
     * @param  string  $key      The client's API key.
     * @param  string  $resource The URI endpoint being requested from the API.
     * @param  string  $method   The HTTP method the request should be submitted as.
     * @param  array   $data     Any query string or body data to be include with the request.
     * @param  array   $headers  Any header data to be included with the request.
     * @param  boolean $skipAuth Should this request skip authentication with the API? This
     *                           parameter is used as part of the automatic authentication
     *                           process. The same function is used to perform authentication
     *                           but should not be authenticated itself.
     * @param  boolean $reAuth   Is this current function call an attempt to re-authenticate
     *                           after an initial failed attempt to retrieve data from the API.
     *                           This attempt will only be made once before throwing an exception.
     * @return array             A representation of the data that was returned successfully.
     *                              
     *                           
     */
    private function rawRequest(
        $key, 
        $resource, 
        $method = "GET", 
        $data = array(), 
        $headers = array(), 
        $skipAuth =  false,
        $reAuth = false
    )
    {
        $method = strtoupper($method);


        // Make sure the resource is valid.
        $err = $this->isValidResource($resource);
        if (is_wp_error($err))  return $err;

        // Make sure the method is valid.
        $err = $this->isValidMethod($method);
        if (is_wp_error($err))  return $err;

        // Make sure the data is valid.
        $err = $this->isValidData($data);
        if (is_wp_error($err))  return $err;

        // Retrieve a fully qualified URL for the request based on the requested resource.
        $fullUrl = $this->buildFullUrl($resource);

        // Unless told otherwise, attempt to retrieve an API token.
        if (!$skipAuth) {
            
            $api_token = $this->getApiToken( $key, $reAuth);
            if (is_wp_error($api_token))  return $api_token;
            $headers['api_token'] = $api_token;
        }


        $args = array(
                'method'   => $method,
                'headers'  => $headers,
        );

        //set up headers, body, and url data as needed
        switch ($method) {
            case 'GET':
                $fullUrl = add_query_arg($data, $fullUrl);
                break;
            case 'POST':

                $args['body'] = $data;
                break;
            case 'PUT':
                $args['headers']['Content-Type'] = "application/json";
                $args['body'] = json_encode($data);
                break;
        }

        if ($this->debug === true) {
            echo "<br>================================<br>The rawRequest() method says:<br>";
            echo "URL being requested is $fullUrl";
            echo "<pre>the args sent with the request are: \n";
            print_r($args);
            echo "</pre>";
        }
        

        $api_response = wp_remote_request($fullUrl, $args);

        if (is_wp_error($api_response)) {
            return $api_response;
        }

        if ($this->debug === true) {
            echo "<br><br>The raw responce from the API server is:<br>";
            echo "<pre>";
            print_r($api_response);
            echo "</pre>";
            echo "<br>================================<br>";
        }

        // build an array with useful representation of the response 
        $response = array(
            'requestUrl' => $fullUrl,
            'requestMethod' => $method,
            'requestData' => $data,
            'responseStatusCode' => $api_response['response']['code'],
            'responseData' => $api_response['body'],
            'timestamp' => time(),
            );

        // If the response type is JSON convert it to an array.
        // if the response content type contains the string 'application/json'
        if ( strpos($api_response['headers']['content-type'], 'application/json') == "application/json") {
            $response['responseData'] = json_decode( $response['responseData'], true ); 
        }

        if ($this->debug === true) {
            echo "rawRequest() is returning:<br>";
            echo "<pre>\n";
            print_r($response);
            echo "</pre>";
            echo "end rawRequest()<br>================================<br>";
        }

        return $response;

    }


    private function buildFullUrl( $resource )
    {
        // TODO: The environment configuration needs to be updated to be only a host name and not include protocol.
        // variables.apiHostName & arguments.resource;
        // ?? protocol  needed http://?
        return 'http://' .$this->host . $resource;

    }


    /**
     * This method validates that a provided resource string is formatted correctly.
     * @param  string  $resource The URI endpoint being requested from the API.
     * @return bool|WP_Error     True if ok. WP_Error on failure 
     */
    private function isValidResource($resource)
    {
        // TODO: Add more validation criteria.

        // If the resource does not start with a leading slash it is not valid.
        if (substr($resource, 0, 1 !== "/")) {
            return true;
        } else {
            return new WP_Error('badResource', __("The Resource does not start with a leading slash."));
        }

    }

    /**
     * This method validates that a given method string matches one that is supported by the API.
     * @param  string  $method    The HTTP method the request should be submitted as.
     * @return boolean|WP_Error   Is the method valid? true or WP_Error if not
     */
    private function isValidMethod( $method )
    {
        $valid = array('GET','POST','PUT','DELETE');
        if (in_array($method, $valid)) {
            return true;
        } else {
            return new WP_Error('badMethod', __("$method is not a valid http method"));
        }

    }


    /**
     * This method validates that the given data can be used with the API request.
     * @param  array   $data     Any query string or body data to be include with the request.
     * @return boolean|WP_Error  Is the data valid? true or WP_Error if not
     */
    private function isValidData( $data = array() )
    {
        $valid = true;

        // Ensure that only simple values are included in the data. ie. strings, numbers, and booleans.
        foreach  ($data as $key => $value) {
            if (!is_scalar($value)) {
                if (is_wp_error($valid)) { // if we already have error add a message to it
                    $valid->add('badData', __("invalid data type $key : $value"));
                } else {
                    $valid = new WP_Error('badData', __("invalid data type $key : $value"), $data);
                }
            }
        }

        return $valid;

    }


    private function getApiToken( $key, $force = false)
    {
        // Unless forced to do otherwise, attempt to retrieve the token from a cache.
        $transient_key = $this->transientIndexKey . $key;
        $token = get_transient( $transient_key );
        //$token = $force ? "" : $this->retrieveApiTokenDataFromCache($key);

        if ($this->debug === true) {
            echo "<br>================================<br>getApiToken()<br>the key is: $key<br>";
            echo "Token retrieved from transient is: |$token|<br>";
        }


        
        // If a token was not retrieved from the cache perform an API request to retrieve a new one.
        if ($token == "") {
            $data = array(
                'key' => $key,
                'v' => $this->version,
            );
            // echo "how about here?<br>";
            $auth_response = $this->rawRequest(
                $key,
                '/core/auth',
                "POST",
                $data,
                array(),
                true // Since we don't have a valid token we don't want to attempt to include it.
                );
            
            // check if valid response
            if (is_wp_error($auth_response))  return $auth_response;
                    
            // TODO: Validate that the response includes the data we need.
            $token = $auth_response['responseData']['data']['api_token'];

            if ($this->debug === true) {
                echo "Token retrieved from Api authentication is: |$token|<br>";
            }

            // time to live. when should this transient expire?
            // expiration time - time created - 5
            $ttl = ( strtotime($auth_response['responseData']['data']['expiration']) - strtotime($auth_response['responseData']['data']['date_created']) - 5 );

            // check if valid int greater than 0 less then #seconds in 7 days
            if (!is_int($ttl) && $ttl < 0 && $ttl > (60*60*24*7)) 
                $ttl = 60*60; // if we cant calculate then default to an hour 

            $transient_key = $this->transientIndexKey . $key;
            if ($this->debug === true) {
                echo "Setting Transient transient_key: |$transient_key|, token: |$token|, time until expire: |$ttl| seconds<br>";
            }
            set_transient( $transient_key, $token, $ttl );

        }
        if ($this->debug === true) {
            echo"end getApiToken()<br>================================<br>";
        }
        return $token;

    }


}

