<?php
    namespace App\Models\framework;
    require_once app_path().'/Libraries/Facebook/FacebookSocial/autoload.php';
    use Facebook;
    use DB;
    use App\Usuario;

    /**
    * Esta clase dá alguma funcionalidade para a criacao dos usuario no banco de dados, a principais funcoes sao
    * 1) obter um link do fb para fazer login
    * 2) obter um link do fb para criacao dos usuarios
    * 3) criar usuario a partir dos dados do fb
    * 4) login usuario a partir dos dados do fb
    */
    class GestorUsuarios{
        var $fb;
        var $CRED_FACEBOOK_ID='1621160531481204';//o id do aplicativo no fb
        var $CRED_FACEBOOK_SECRET='4b225cd9cc43e15170628cafd781dfca';//a chave segreda do aplicativo
        var $CRED_FACEBOOK_DEFAULTGRAPHVERSION='v2.5';//nao sei mas presisamo disso

        function _construct(){

        }

        /**
        *   Esta funcao permite obter um link para fazer o login e redirigir para a acao de criar um novo usuario,
        *   Se nao esta cadastrado se cadastra e em as duas situacoes o usuario sera redirigido ao login o a reset password se é novo
        */
        function getFacebookLoginURLforRegistry(){
          session_start();
         $this->fb = new Facebook\Facebook([
              'app_id' => $this->CRED_FACEBOOK_ID,
              'app_secret' => $this->CRED_FACEBOOK_SECRET
            ]);
            $helper = $this->fb->getRedirectLoginHelper();

            $permissions = ['email','user_birthday','user_location']; // Optional permissions
            $loginUrl = $helper->getLoginUrl(action("UsuarioController@criarUsuarioFromFacebook"), $permissions);
            foreach ($_SESSION as $k=>$v) {
                if(strpos($k, "FBRLH_")!==FALSE) {
                    if(!setcookie($k, $v)) {
                        //escrever as cookies do facebook
                    } else {
                        $_COOKIE[$k]=$v;
                    }
                }
            }
            session_write_close();
            return $loginUrl;
        }

        /**
        *   obtem um link para fazer login no fb com uma redirecao para a pagina de login automatico
        */
        function getFacebookLoginURLforLogin(){
          session_start();
          //objecto fb inicializado
         $this->fb = new Facebook\Facebook([
              'app_id' => $this->CRED_FACEBOOK_ID,
              'app_secret' => $this->CRED_FACEBOOK_SECRET
            ]);
            $helper = $this->fb->getRedirectLoginHelper();

            $permissions = ['email','user_birthday','user_location']; // Optional permissions
            $loginUrl = $helper->getLoginUrl(action("AuthController@postLoginFromFacebook"), $permissions);
            foreach ($_SESSION as $k=>$v) {
                if(strpos($k, "FBRLH_")!==FALSE) {
                    if(!setcookie($k, $v)) {
                        //criar as cookis do usuario
                    } else {
                        $_COOKIE[$k]=$v;
                    }
                }
            }
            session_write_close();
            return $loginUrl;
        }
    /**
    *   a partir dos dados do facebook fazer login, se o usuario existe faz login, se nao cria e redirecciona para resetar a senha.
    */
    function loginUsuarioFromFacebook(){
        session_start();

            foreach ($_COOKIE as $k=>$v) {
                if(strpos($k, "FBRLH_")!==FALSE) {
                    $_SESSION[$k]=$v;
                }
            }

          $this->fb = new Facebook\Facebook([
          'app_id' => $this->CRED_FACEBOOK_ID,
          'app_secret' => $this->CRED_FACEBOOK_SECRET
          ]);

            $helper = $this->fb->getRedirectLoginHelper();
            try {
              $accessToken = $helper->getAccessToken();
            } catch(Facebook\Exceptions\FacebookResponseException $e) {
              exit;
            } catch(Facebook\Exceptions\FacebookSDKException $e) {
              exit;
            }

            if (! isset($accessToken)) {
              if ($helper->getError()) {
                header('HTTP/1.0 401 Unauthorized');
              } else {
                header('HTTP/1.0 400 Bad Request');
              }
              exit;
            }

            $oAuth2Client = $this->fb->getOAuth2Client();

            // Get the access token metadata from /debug_token
            $tokenMetadata = $oAuth2Client->debugToken($accessToken);
            // Validation (these will throw FacebookSDKException's when they fail)
            $tokenMetadata->validateAppId($this->CRED_FACEBOOK_ID);
            // If you know the user ID this access token belongs to, you can validate it here
            $tokenMetadata->validateExpiration();
            if (! $accessToken->isLongLived()) {
              // Exchanges a short-lived access token for a long-lived one
              try {
                $accessToken = $oAuth2Client->getLongLivedAccessToken($accessToken);
              } catch (Facebook\Exceptions\FacebookSDKException $e) {

                exit;
              }

            }

            $_SESSION['fb_access_token'] = (string) $accessToken;

            try {
              // Returns a `Facebook\FacebookResponse` object
              //get user info
              $response = $this->fb->get('/me?fields=id,name,email,first_name,last_name,middle_name,link,birthday,location,updated_time,verified', $accessToken);
            } catch(Facebook\Exceptions\FacebookResponseException $e) {
            //  echo 'Graph returned an error: ' . $e->getMessage();
              exit;
            } catch(Facebook\Exceptions\FacebookSDKException $e) {
           //   echo 'Facebook SDK returned an error: ' . $e->getMessage();
              exit;
            }
            //obtemos os dados do usuario
            $user = $response->getGraphUser();
            $usuario=new Usuario();
            $usuario->nome=$user['name'];
            $usuario->email=$user['email'];
            $usuario->senha=$user['email'].$user['name'];
            //check if exists
            if(Usuario::where('email', '=',  $usuario->email)->exists()){
               $usuario= Usuario::where('email', '=',  $usuario->email)->first();
                return array("usuario"=>$usuario,"goreset"=>0);
            }else{
                $usuario->remember_token="";
                $usuario->save();
                if(Usuario::where('nome', '=', $usuario->nome)->where('email', '=',  $usuario->email)->exists()){
                   $usuario= Usuario::where('nome', '=', $usuario->nome)->where('email', '=',  $usuario->email)->first();
                    return array("usuario"=>$usuario,"goreset"=>1);//array
                }else{
                    return null;
                }
            }

            session_write_close();
    }

    /**
    *   criar um novo usuario a partir dos dados no facebook.
    */
    function criarUsuarioFromFacebook(){

        session_start();

            foreach ($_COOKIE as $k=>$v) {
                if(strpos($k, "FBRLH_")!==FALSE) {
                    $_SESSION[$k]=$v;
                }
            }




            $this->fb = new Facebook\Facebook([
          'app_id' => $this->CRED_FACEBOOK_ID,
          'app_secret' => $this->CRED_FACEBOOK_SECRET
          ]);

            $helper = $this->fb->getRedirectLoginHelper();
            //echo $helper;
            try {
              $accessToken = $helper->getAccessToken();
            } catch(Facebook\Exceptions\FacebookResponseException $e) {

              exit;
            } catch(Facebook\Exceptions\FacebookSDKException $e) {
              exit;
            }

            if (! isset($accessToken)) {
              if ($helper->getError()) {
                header('HTTP/1.0 401 Unauthorized');
              } else {
                header('HTTP/1.0 400 Bad Request');
              }
              exit;
            }


            // The OAuth 2.0 client handler helps us manage access tokens
            $oAuth2Client = $this->fb->getOAuth2Client();

            // Get the access token metadata from /debug_token
            $tokenMetadata = $oAuth2Client->debugToken($accessToken);


            // Validation (these will throw FacebookSDKException's when they fail)
            $tokenMetadata->validateAppId($this->CRED_FACEBOOK_ID);
            // If you know the user ID this access token belongs to, you can validate it here
            //$tokenMetadata->validateUserId('123');
            $tokenMetadata->validateExpiration();

            if (! $accessToken->isLongLived()) {
              // Exchanges a short-lived access token for a long-lived one
              try {
             //   echo "entre";
                $accessToken = $oAuth2Client->getLongLivedAccessToken($accessToken);
              //  echo "passee";
              } catch (Facebook\Exceptions\FacebookSDKException $e) {
              //  echo "<p>Error getting long-lived access token: " . $helper->getMessage() . "</p>\n\n";
                exit;
              }

            }else{
              //  echo "no es long lived";
            }

            $_SESSION['fb_access_token'] = (string) $accessToken;

            try {
              // Returns a `Facebook\FacebookResponse` object
              //get user info
              $response = $this->fb->get('/me?fields=id,name,email,first_name,last_name,middle_name,link,birthday,location,updated_time,verified', $accessToken);
            } catch(Facebook\Exceptions\FacebookResponseException $e) {
            //  echo 'Graph returned an error: ' . $e->getMessage();
              exit;
            } catch(Facebook\Exceptions\FacebookSDKException $e) {
           //   echo 'Facebook SDK returned an error: ' . $e->getMessage();
              exit;
            }
            $user = $response->getGraphUser();

            /**
            *   verificar se o usuario já esta cadastrado
            */
            if(Usuario::where('nome', '=', $user['name'])->where('email', '=',  $user['email'])->exists()){
               return null;
            }

            /**
            *   criar usuario no banco
            */
            $usuario=new Usuario();
            $usuario->nome=$user['name'];
            $usuario->email=$user['email'];
            $usuario->senha=$user['email'].$user['name'];
            $usuario->remember_token="";
            $usuario->save();

            if(Usuario::where('nome', '=', $usuario->nome)->where('email', '=',  $usuario->email)->exists()){
               $usuario= Usuario::where('nome', '=', $usuario->nome)->where('email', '=',  $usuario->email)->first();
                return $usuario;
            }else{
                return null;
            }

            session_write_close();

        }

    }

?>
