<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Allegro extends CI_Controller {
  var $verifySessionName = 'AllegroSession';
  var $sellerId = '000000';
  var $clientId = '000000';
  var $baseUrl = 'https://www.strona_backendu';

  public function __construct() {
    parent::__construct();
    $this->load->model('cron');
    $this->token_0 = $this->cron->get_session_token('token_0');
  }

  private function verifySession() {
    //sprawdzenie czy nie ma innej sesji
    if(!isset($_SESSION['verifySessionNameAllegro'])) {
      header('Location: '.$this->baseUrl.'/public/allegro/login');
    }
    //zabezpieczenie przed inna grupa niz admin
    if($this->session->userdata('isUserLoggedIn') && $this->session->userdata('group') == 'admin') {
    }
    else {
      redirect('backend/disallow');
    }
  }

  private function newOfferData($name, $category) {
    //inicjacja wystawienia aukcji
    $this->verifySession();
    $this->executeApi('https://api.allegro.pl/sale/offers');
  }

  public function newOffer() {
    $this->verifySession();
    echo '<h1>Wystawianie aukcji</h1>';
    echo '<form action="newOffer" method="post">';
      echo '<label>SKU produktu, który chcesz wystawić</label> ';
      echo '<input type="text" name="sku"></input>';
      echo '<button type="submit">Dalej</button>';
    echo '</form>';
  }

  public function login() {
      echo '<a href="https://allegro.pl/auth/oauth/authorize?response_type=code&client_id='.$this->clientId.'&redirect_uri='.$this->baseUrl.'/public/allegro/apiResponse">Zaloguj</a>';
  }

  public function categoriesParameters($id=null) {
    //parametry, atrybuty
    //$this->load->library('session');
    $this->verifySession();
    $this->executeApi('https://api.allegro.pl/sale/categories/'.$id.'/parameters');
  }

  public function getSaleOffers() {
    $this->verifySession();
    $this->executeApi('https://api.allegro.pl/sale/offers?limit=5');
  }

  public function categories($id=null) {
    //kategorie
    //$this->load->library('session');
    $this->verifySession();
    $this->executeApi('https://api.allegro.pl/sale/categories?parent.id='.$id);
  }

  public function serviceConditionsReturnPolicies() {
    //polityka zwrotów
    // 602455bf-8aac-43322-93232-2450cd018de4
    //$this->load->library('session');
    $this->verifySession();
    $this->executeApi('https://api.allegro.pl/after-sales-service-conditions/return-policies?seller.id='.$this->sellerId);
  }

  public function serviceConditionsImpliedWarranties() {
    //warunki reklamacji
    // 33f8b369-ddf8-4030b-93336-81fe84df14b2
    //$this->load->library('session');
    $this->verifySession();
    $this->executeApi('https://api.allegro.pl/after-sales-service-conditions/implied-warranties?seller.id='.$this->sellerId);
  }

  public function serviceConditionsWarranties() {
    //gwarancje
    // 1df11d73-b2af-46e5-90bd-5cf1deb83381
    //$this->load->library('session');
    $this->verifySession();
    $this->executeApi('https://api.allegro.pl/after-sales-service-conditions/warranties?seller.id='.$this->sellerId);
  }

  public function shippingRates() {
    //cenniki dostaw
    //Listopad 2017:
    // f74ee630f-29b3-45a0-bc63a-32cffdeec288
    //$this->load->library('session');
    $this->verifySession();
    $this->executeApi('https://api.allegro.pl/sale/shipping-rates?seller.id='.$this->sellerId);
  }

  public function test() {
    //$this->load->library('session');
    $this->verifySession();
    print_r($_SESSION);
  }

  public function apiResponse() {
    $this->load->model('cron');

    echo '<a href="'.$this->baseUrl.'/public/allegro/login">Zaloguj</a> | <a href="'.$this->baseUrl.'/public/allegro/test">Test</a>';


  if(isset($_GET['code'])) {
    $code = $_GET['code'];
    $url = 'https://allegro.pl/auth/oauth/token?grant_type=authorization_code&code='.$code.'&redirect_uri='.$this0->baseUrl.'/public/allegro/apiResponse';
    $basepass = base64_encode('000:000');

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,$url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, "000:000");
    $result = curl_exec($ch);
    curl_close($ch);
    $result_decode = json_decode($result);

    $_SESSION['token_0'] = $result_decode->{'access_token'};
    $_SESSION['verifySessionNameAllegro'] = $this->verifySessionName;
    $this->cron->update_session_token('token_0',$result_decode->{'access_token'});
    $this->cron->update_session_token('code_0',$result_decode->{'refresh_token'});
    //var_dump($result_decode);
  }
  else {
  }

  }

  public function executeApi($url) {
    //$this->load->library('session');
    $ch = curl_init();
    $headr = array();
      $headr[] = 'Authorization: Bearer '.$this->token_0;
      $headr[] = 'Accept: application/vnd.allegro.public.v1+json';
    curl_setopt($ch, CURLOPT_HTTPHEADER,$headr);
    curl_setopt($ch, CURLOPT_URL,$url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
  }

  public function executeApiPost($url, $fields) {
    $ch = curl_init();
    $headr = array();
      $headr[] = 'Authorization: Bearer '. $this->token_0;
      $headr[] = 'Accept: application/vnd.allegro.public.v1+json';
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS,$fields);
      curl_setopt($ch, CURLOPT_HTTPHEADER,$headr);
      curl_setopt($ch, CURLOPT_URL,$url);
      var_dump(curl_exec($ch));
    curl_close($ch);
  }

  public function logout() {
    $this->session->sess_destroy();
  }

}
