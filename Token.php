<?php

Class Token{

    private $_graph_base_url = 'https://graph.instagram.com/';
    private $_renew_token_url = 'https://graph.instagram.com/v13.0/refresh_access_token';
    private $_db_ip = "82.98.147.249";
    private $_db_username = "usr_insta";
    private $_db_password = "Du2l9j9,2;[6";
    private $_db_database = "instagram_test";
    private $_mysqli;

    function __construct() 
    {
        $this->_mysqli = new mysqli($this->_db_ip, $this->_db_username, $this->_db_password, $this->_db_database);

        if ($this->_mysqli->connect_error) {
            die("Conexión fallida: " . $this->_mysqli->connect_error);
        }
    }

    public function updateToken()
    {
    
        $result = getCurrentAccessTokens();

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $currentAccessToken = $row['access_token'];
                $appId = $row['app_id'];
                $userId = $row['user_id'];
    
                try {
                    $newAccessToken = $this->_renewAccessToken($currentAccessToken, $appId, $userId);
    
                    if($newAccessToken) $this->_updateInstagramPosts($userId, $appId, $newAccessToken);
    
                    //echo "Token renovado con éxito para la APP ID: $appId\n";
    
                } catch (Exception $e) {
    
                    throw new Exception('Error al renovar el token '. $e->getMessage() . '\n');

                }
            }
    
            $result->free();
    
        } else {
            echo "Error al obtener los tokens de la base de datos: " . $this->_mysqli->error . "\n";
        }
    }

    private function _renewAccessToken($currentAccessToken, $appId, $userId) {

        $params = array(
            'grant_type' => 'ig_refresh_token',
            'access_token' => $currentAccessToken,
        );
    
        $response = makeApiCall('GET', $this->_renew_token_url, $params);
    
        if (isset($response['access_token'])) {
        
            try {

                $this->_deleteExistingTokens($appId);
                $this->_createTokensAfterDeleting($response['access_token'], $appId, $userId);

            } catch (Exception $e) {

                throw new Exception('Error al actualizar la base de datos '. $e->getMessage() .'\n');

            }
            
        } else {

            throw new Exception('Error al renovar el token de acceso');
        }
    
        return $response['access_token'];
    }


    private function _updateInstagramPosts($userId, $appId, $newAccessToken)
    {

        $params = array(
            'url_params'=>array(
                'fields'=>'id,caption,media_type,media_url',
                'access_token'=>$newAccessToken,
            )
        );
    
        $response = $this->_makeApiCall( 'GET', $this->_graph_base_url . $userId . '/media',  $params['url_params'] );
    
        if  (isset($response['data']) && is_array($response['data'])) {
            
            try {

                $this->_deleteExistingPosts($appId);
                $this->_createPostsAfterDeleting($response['data']);

            } catch (Exception $e) {

                throw new Exception('Error al actualizar los posts '. $e->getMessage() .'\n');

            }

            $this->_mysqli->close();

        }
        
    } 

    private function _makeApiCall($method = null, $url = null, $params = array()) 
    {

        $ch = curl_init();
    
        if ( $method === 'POST' ) {
    
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    
        } elseif ( $method === 'GET' ){
            
            echo json_encode($params,true);
            $url .= '?' . http_build_query($params);
    
        }
    
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
        $response = curl_exec($ch);
        curl_close($ch);
    
        $responseArray = json_decode($response, true);
        
        if (isset($responseArray['error_type'])) {

            die('Error API Instagram: ' . $responseArray['error_message']);

        } else {

            return $responseArray;
        }    
    }

    private function deleteExistingPosts($appId)
    {

        $deleteExistingPosts = "DELETE FROM instagram_posts WHERE app_id = '$appId'";
        $this->_mysqli->query($deleteExistingPosts);

        if ($this->_mysqli->error) 
        {
            echo "Error al eliminar datos: " . $this->_mysqli->error . "\n";
        }
        
    }

    private function createPostsAfterDeleting($posts)
    {

        foreach ($posts as $post) {
            $post_id = $this->_mysqli->real_escape_string(isset($post['id']) ? : '');
            $caption = $this->_mysqli->real_escape_string(isset($post['caption']) ? : '');
            $media_type = $this->_mysqli->real_escape_string($post['media_type']);

            $query = "INSERT INTO instagram_posts (post_id, caption, media_type, media_url, app_id) 
                        VALUES ('$post_id', '$caption', '$media_type', '".str_replace('\/', '/', $post['media_url'])."', '$appId')";

            $this->_mysqli->query($query);

            if ($this->_mysqli->error) 
            {
                echo "Error al insertar datos: " . $this->_mysqli->error . "\n";
            }
        }

    }

    private function createTokensAfterDeleting($token, $appId, $userId)
    {
        $query = "INSERT INTO instagram_tokens (access_token, app_id, user_id) 
        VALUES ('$token', '$appId', '$userId')";
        $this->_mysqli->query($query);

        if  ($this->_mysqli->error) 
        {
            echo "Error al insertar datos: " . $this->_mysqli->error . "\n";
        }
    }

    private function getCurrentAccessTokens()
    {
        $query = "SELECT app_id, access_token, user_id FROM instagram_tokens";
        $result = $this->_mysqli->query($query);

        return $result;
    }

    private function deleteExistingTokens($appId)
    {
        $deleteExistingToken = "DELETE FROM instagram_tokens WHERE app_id = '$appId'";
        $this->_mysqli->query($deleteExistingToken);

        if ($this->_mysqli->error) 
        {
            echo "Error al eliminar los tokens existentes: " . $this->_mysqli->error . "\n";
        }
    }
}
?>