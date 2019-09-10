<?php
defined('BASEPATH') OR exit('No direct script access allowed');


class Shops extends CI_Controller {

  public function __construct()
        {
          parent::__construct();

          //autoryzacja na IP
/*
          if($_SERVER["REMOTE_ADDR"]!="193.33.1.197") {
            echo "<br/><br/><center><h2>unauthorized login</h2></center>";
            exit;
          }
*/
          if($this->session->userdata('isUserLoggedIn') && $this->session->userdata('firstname') && $this->session->userdata('lastname') && $this->session->userdata('group') && $this->session->userdata('level') && $this->session->userdata('email')) {
          }
          else {
            redirect('users/login');
          }
          $this->load->library('session');

        }

  public function index() {

  }


  public function apiLogin($shop) {
    if(isset($_GET['oauth_token'])) {
        $oauth_token = $_GET['oauth_token'];
        $oauth_verifier = $_GET['oauth_verifier'];
        $this->load->model('shop');
        $this->shop->apiLogin($shop,$oauth_token,$oauth_verifier);
    }
    header('Location: '. base_url('products/admin_shops'));
  }

  public function apiInitiate($shop) {
  }

  public function api_update_qty_by_xml($id) {
    $this->load->model('product');
    $this->product->api_update_qty_by_xml(1);
  }

  public function restore_kod_producenta() {
  //przypadkowo wywalilem attribute kod producenta i ta funkcja przesylam z powrotem kod producenta z products do magento
  $this->load->model('shop');
  $this->shop->restore_kod_producenta();
  }


}
