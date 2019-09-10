<?php
defined('BASEPATH') OR exit('No direct script access allowed');


class Wfirma extends CI_Controller {
   var $consumerKey = '0000';
   var $consumerSecret = '0000';
   var $baseUrl = '000';
public function index() {

}

public function authorize($callbackFunction)
{
  $this->load->library('session');
  $oAuth = new OAuth($this->consumerKey, $this->consumerSecret, OAUTH_SIG_METHOD_PLAINTEXT);

  $scope = 'invoices-read,invoices-write,contractors-read,contractors-write,goods-read,goods-write';
  $callback = $this->baseUrl.'synchronizer/codeigniter/public/index.php/wfirma/'.$callbackFunction;

  try {
     $tokenInfo = $oAuth->getRequestToken(
         'https://wfirma.pl/oauth/requestToken?scope=' . $scope,
         $callback,
         'GET'
     );

     $_SESSION['oauthSecret'] = $tokenInfo['oauth_token_secret'];
     header('Location: https://wfirma.pl/oauth/authorize?oauth_token=' . $tokenInfo['oauth_token']);
  } catch (OAuthException $exception) {
  }
}

private function authorize_ver($get_oauth_token, $get_oauth_verifier) {
  $oAuth = new OAuth($this->consumerKey, $this->consumerSecret, OAUTH_SIG_METHOD_PLAINTEXT);

if(isset($_SESSION['oauthSecret'])) {
$oAuth->setToken($get_oauth_token, $_SESSION['oauthSecret']);
  unset($_SESSION['oauthSecret']);
}
if(isset($get_oauth_verifier)&&!isset($_SESSION['oauth_token_secret'])&&!isset($_SESSION['oauth_token'])){
  try {
      $tokenInfo = $oAuth->getAccessToken(
          'https://wfirma.pl/oauth/accessToken?oauth_verifier=' . $_GET['oauth_verifier']
      );
  } catch (OAuthException $exception) {
      // błąd autoryzacji.
      return;
  }

  $_SESSION['oauth_token_secret'] = $tokenInfo['oauth_token_secret'];
  $_SESSION['oauth_token'] = $tokenInfo['oauth_token'];
}
}

public function logout() {
  $this->load->library('session');
  $this->session->sess_destroy();
}

public function checksession() {
  $this->load->library('session');
  //print_r($_SESSION);
}


public function authorizegettoken() {
  $this->load->library('session');

    $oAuth = new OAuth($this->consumerKey, $this->consumerSecret, OAUTH_SIG_METHOD_PLAINTEXT);

$data = '<?xml version="1.0" encoding="UTF-8"?>
<api>
    <goods>
        <good>
            <name>marchewka</name>
            <unit>szt.</unit>
            <count>1.00</count>
            <netto>14.00</netto>

            <vat>23</vat>
            <classification></classification>
            <description></description>
            <min>5</min>
            <secure>15</secure>
            <max>25</max>
        </good>
    </goods>
</api>';

    //echo $this->oauthRequest('goods/add', $data);


    if(isset($_SESSION['oauthSecret'])) {
    $oAuth->setToken($_GET['oauth_token'], $_SESSION['oauthSecret']);
      unset($_SESSION['oauthSecret']);
    }

    try {
        $tokenInfo = $oAuth->getAccessToken(
            'https://wfirma.pl/oauth/accessToken?oauth_verifier=' . $_GET['oauth_verifier']
        );
    } catch (OAuthException $exception) {
        // Wystąpił błąd podczas autoryzacji.

        return;
    }

    $_SESSION['oauth_token_secret'] = $tokenInfo['oauth_token_secret'];
    $_SESSION['oauth_token'] = $tokenInfo['oauth_token'];

}

private function oauthRequest($action, $data = []){
  $this->load->library('session');

    $oAuth = new OAuth($this->consumerKey, $this->consumerSecret, OAUTH_SIG_METHOD_PLAINTEXT);
    $oAuth->setToken($_SESSION['oauth_token'], $_SESSION['oauth_token_secret']);

    try {
      $oAuth->fetch(
                 'https://api2.wfirma.pl/' . $action,
                 !empty($data) ? $data : '',
                 OAUTH_HTTP_METHOD_POST
             );
    } catch (Exception $exception) {
        return false;
    }

    return $oAuth->getLastResponse();
}

public function add_sold_products() {
  $this->load->library('session');
  if((isset($_GET['oauth_token'])||isset($_GET['oauth_verifier']))&&isset($_SESSION['oauthSecret'])) {
    $this->authorize_ver($_GET['oauth_token'], $_GET['oauth_verifier']);
  } else {
    if(!isset($_SESSION['oauth_token_secret'])){
      $this->authorize('add_sold_products');
    }
  }

    echo $this->oauthRequest('goods/find', '');
}

}
