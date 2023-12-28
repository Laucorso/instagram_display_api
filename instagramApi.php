<?php

Class instagramApi{

    private $_app_id = '***********';
    private $_app_secret = '********************';
    private $_redirect_url = 'https://instagram.clicktotravel.es/';
    private $_api_base_url = 'https://api.instagram.com/';
    private $_graph_base_url = 'https://graph.instagram.com/';
    private $_accessToken = '';
    private $userId = '';
    private $_accessTokenExpires = '';
    protected $_getCode = '';
    private $_db_ip = "******";
    private $_db_username = "*****";
    private $_db_password = "*****";
    private $_db_database = "instagram_test";

    public $authorizationUrl = '';
    public $hasUserAccessToken = false;

    function __construct($params) {

        $this->_getCode = $params['get_code'];
        $this->_setUserInstagramAccessToken($params);
        $this->_setAuthorizationUrl();

    }

    private function _setUserInstagramAccessToken( $params ){

        if  ( isset($params['access_token']) ){

            $this->_accessToken = $params['access_token'];
            $this->hasUserAccessToken = true;
            $this->userId = $params['user_id'];

        } elseif ( $params['get_code'] ){

            $userAccessTokenResponse = $this->getUserAccessToken();
            $this->_accessToken = $userAccessTokenResponse['access_token'];
            $this->hasUserAccessToken = true;
            $this->userId = $userAccessTokenResponse['user_id'];

            $longLivedAccessTokenResponse = $this->_getLongLivedUserToken();
            $this->_accessToken = $longLivedAccessTokenResponse['access_token'];
            $this->_accessTokenExpires = $longLivedAccessTokenResponse['expires_in'];

            if  ($this->_accessToken) {
                $mysqli = new mysqli($this->_db_ip, $this->_db_username, $this->_db_password, $this->_db_database);

                if  ($mysqli->connect_error) {
                    die("Conexión fallida: " . $mysqli->connect_error);
                }
                
                $deleteExistingToken = "DELETE FROM instagram_tokens WHERE app_id = '$this->_app_id'";
                $mysqli->query($deleteExistingToken);

                $query = "INSERT INTO instagram_tokens (access_token, app_id, user_id) 
                              VALUES ('$this->_accessToken', '$this->_app_id', '$this->userId')";

                $mysqli->query($query);

                if  ($mysqli->error) {
                    echo "Error al insertar datos: " . $mysqli->error . "\n";
                }

                $mysqli->close();
            }

        }
    }

    private function _getLongLivedUserToken(){
        $params = array(
            'endpoint_url'=>$this->_graph_base_url . 'access_token',
            'type'=>'GET',
            'url_params'=>array(
                'client_secret'=>$this->_app_secret,
                'grant_type'=>'ig_exchange_token',
            )
        );

        $response  = $this->_makeApiCall($params);
        return $response;
    }

    public function getUser(){
        $params = array(
            'endpoint_url'=>$this->_graph_base_url . 'me',
            'type'=>'GET',
            'url_params'=>array(
                'fields'=>'id,username,media_count, account_type',
            )
        );

        $response = $this->_makeApiCall( $params );
        return $response;
    }

    public function getUsersMedia(){

        $params = array(
            'endpoint_url'=>$this->_graph_base_url . $this->userId . '/media',
            'type'=>'GET',
            'url_params'=>array(
                'fields'=>'id,caption,media_type,media_url',
            )
        );

        $response = $this->_makeApiCall( $params );
        
        //Guardariamos en base de datos los post obtenidos de la consulta
        if  (isset($response['data']) && is_array($response['data'])) {

            $mysqli = new mysqli($this->_db_ip, $this->_db_username, $this->_db_password, $this->_db_database);

            if  ($mysqli->connect_error) {
                die("Conexión fallida: " . $mysqli->connect_error);
            }

            $deleteExistingPosts = "DELETE FROM instagram_posts WHERE app_id = '$this->_app_id'";
            $mysqli->query($deleteExistingPosts);

            foreach ($response['data'] as $post) {
                $post_id = $mysqli->real_escape_string(isset($post['id']) ? : '');
                $caption = $mysqli->real_escape_string(isset($post['caption']) ? : '');
                $media_type = $mysqli->real_escape_string($post['media_type']);

                $query = "INSERT INTO instagram_posts (post_id, caption, media_type, media_url, app_id) 
                          VALUES ('$post_id', '$caption', '$media_type', '".str_replace('\/', '/', $post['media_url'])."', '$this->_app_id')";

                $mysqli->query($query);

                if ($mysqli->error) {
                    echo "Error al insertar datos: " . $mysqli->error . "\n";
                }
            }

            $mysqli->close();
        }

        header('Content-Type: application/json');
        return json_encode($response, JSON_PRETTY_PRINT);
    }

    public function getAccessToken(){

        return $this->_accessToken;
    }

    public function getUserAccessTokenExpires(){

        return $this->_accessTokenExpires;
    }

    private function getUserAccessToken(){

        $params = array(
            'endpoint_url'=>$this->_api_base_url . 'oauth/access_token',
            'type'=>'POST',
            'url_params'=>array(
                'app_id'=> $this->_app_id,
                'app_secret'=>$this->_app_secret,
                'grant_type'=>'authorization_code',
                'redirect_uri'=>$this->_redirect_url,
                'code'=>$this->_getCode,
            )
        );
        $response = $this->_makeApiCall( $params );

        return $response;
    }

    private function _setAuthorizationUrl(){

        $getVars = array(
            'app_id'=> $this->_app_id,
            'redirect_uri'=>$this->_redirect_url,
            'scope'=>'user_profile,user_media',
            'response_type'=>'code',
        );

        $this->authorizationUrl = $this->_api_base_url . 'oauth/authorize?' . http_build_query( $getVars );

    }

    private function _makeApiCall( $params ){
        $ch = curl_init();
        $endpoint = $params['endpoint_url'];

        if ('POST' == $params['type']){

            curl_setopt( $ch, CURLOPT_POSTFIELDS, http_build_query($params['url_params']));
            curl_setopt( $ch, CURLOPT_POST, 1);

        } elseif ( 'GET' == $params['type'] ){
            $params['url_params']['access_token'] = $this->_accessToken;

            $endpoint .= '?' . http_build_query( $params['url_params'] );
        }

        curl_setopt( $ch, CURLOPT_URL, $endpoint);
        curl_setopt ( $ch, CURLOPT_SSL_VERIFYHOST, false );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        curl_close($ch);

        $responseArray = json_decode($response, true);

        if (isset($responseArray['error_type'])) {

            die('Error API Instagram: ' . $responseArray['error_message']);

        } else {
            return $responseArray;
        }
    }

}
?>
