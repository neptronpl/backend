<?php
defined('BASEPATH') OR exit('No direct script access allowed');


class Products extends CI_Controller {

  public function __construct() {

    parent::__construct();
    /*
        //autoryzacja na IP
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


  }


  public function index() {

  }


  public function admin_protected() {
    //zabezpieczenie przed wglądem dla grupy innej niż admin
    if($this->session->userdata('isUserLoggedIn') && $this->session->userdata('group') == 'admin') {
    }
    else {
      redirect('backend/disallow');
    }
  }

  //MENU - Przeglądaj produkty
  public function overview() {
    $sidebar_variables['menu_active'] = 'products';
    $this->load->model('product');
    $this->product->update_products_stats_to_products_synchronization();
    $products['products_array'] = $this->product->get_products();
    $this->load->view('template/page/head.phtml');
    $this->load->view('template/page/top.phtml');
    $this->load->view('template/page/menu.phtml', $sidebar_variables);
    //sample
    $this->load->view('template/products/products.phtml', $products);
    $this->load->view('template/page/footer.phtml');
  }


  //Funkcja pobierająca dane dla tabeli w overview oraz obsługująca filtrowanie
  public function get_all_products() {
    $products = array();
    $i=0;
    $this->load->model('product');
    $data=array();
    //jeśli wysłany GET z filtrem
    if(isset($_GET['pq_filter'])) {

      $pq_filter = json_decode($_GET['pq_filter']);
      foreach($pq_filter->data as $data) {
        $dataIndx = $data->dataIndx;
        $value = $data->value;
        $filter_query[] = array('index' => $dataIndx, 'value' => $value);

      }

      $query = $this->product->get_products_filter_fixed($filter_query);
      foreach($query as $_query) {
        $products[$i] = array(
          'name' => $_query->name,
          'sku' => $_query->sku,
          'kod_dostawcy' => $_query->kod_dostawcy,
          'qty_xml' => $_query->qty_xml,
          'qty_vats' => $_query->qty_vats,
          'qty_damakaro' => $_query->qty_damakaro,
          'qty_allegro' => $_query->qty_allegro,
          'qty_cw' => $_query->qty_cw,
          'allegro_id' => $_query->allegro_id,
          'allegro_status' => $_query->allegro_status,
          'details' => $_query->id
        );
        $i++;
      }
    }
    /*
    ///zmienione 9.01.2019 bo nie sumowało filtrów

    if(isset($_GET['pq_filter'])) {

      $pq_filter = json_decode($_GET['pq_filter']);

      foreach($pq_filter->data as $data) {
        $dataIndx = $data->dataIndx;
        $condition = $data->condition;
        $value = $data->value;

        switch($condition) {
            case 'begin':

            break;
            case 'range':
              foreach($value as $_value) {
                $query = $this->product->get_products_filter($dataIndx, $_value);
                $products[$i] = array(
                  'name' => $query[0]->name,
                  'sku' => $query[0]->sku,
                  'kod_dostawcy' => $query[0]->kod_dostawcy,
                  'qty_xml' => $query[0]->qty_xml,
                  'qty_vats' => $query[0]->qty_vats,
                  'qty_damakaro' => $query[0]->qty_damakaro,
                  'qty_allegro' => $query[0]->qty_allegro,
                  'qty_cw' => $query[0]->qty_cw,
                  'allegro_id' => $query[0]->allegro_id,
                  'details' => $query[0]->id
                );
                $i++;
              }
            break;
        }
      }
    }
    */
    else {
      //Wszystkie produkty
      foreach($this->product->get_products() as $_products) {
        $products[$i] = array(
          'name' => $_products->name,
          'sku' => $_products->sku,
          'kod_dostawcy' => $_products->kod_dostawcy,
          'qty_xml' => $_products->qty_xml,
          'qty_vats' => $_products->qty_vats,
          'qty_damakaro' => $_products->qty_damakaro,
          'qty_allegro' => $_products->qty_allegro,
          'qty_cw' => $_products->qty_cw,
          'allegro_id' => $_products->allegro_id,
          'allegro_status' => $_products->allegro_status,
          'details' => $_products->id
        );
        $i++;
      }
    }

    echo "{\"data\":". json_encode($products) ." }" ;
  }


  //MENU - Admin -> Produkty -> Allegro
  public function admin_allegro() {
    //unset($_SESSION['token_0']);
    //unset($_SESSION['verifySessionNameAllegro']);
    $this->admin_protected();

    $this->load->model('allegro');
    $this->load->model('product');

      //naciśnięty przycisk synchronizacji danych
      if($this->input->post('synchroAllegro')){
        //dodawanie nowego wpisu do tablicy z synchronizacją po kliknięciu 'synchronizuj dane'
        /*
        $data = array(
          'name' => 'test'
        );
        $this->allegro->allegroAddToSynchro($data);
        */
        //synchronizacja allegro
        $this->allegro->allegroSynchro();
        //synchronizacja products
        $this->product->productsSynchro();
        $this->product->update_products_stats_to_products_synchronization();
      }

    $synchronizationInfo = $this->allegro->get_synchronization_info();

    $sidebar_variables['menu_active'] = 'admin_products_allegro';

    $allegro['get_obs_auctions'] = $this->allegro->get_obs_auctions();
    $allegro['get_not_obs_auctions'] = $this->allegro->get_not_obs_auctions();
    $allegro['countActiveAuctions'] = $synchronizationInfo->count_auctions;
    $allegro['lastSynchronizationTime'] = $synchronizationInfo->last_synchronization_date;

    $this->load->view('template/page/head.phtml');
    $this->load->view('template/page/top.phtml');
    $this->load->view('template/page/menu.phtml', $sidebar_variables);

    $this->load->view('template/products/admin/allegro.phtml', $allegro);
    //$this->load->view('template/index.html.bak', $allegro);
    $this->load->view('template/page/footer.phtml');

  }


  //MENU - Admin -> Produkty -> XML
  public function admin_xml() {
    $this->admin_protected();
    $this->load->model('product');
    $xml = $this->product->xml();
    $sidebar_variables['menu_active'] = 'admin_products_xml';

    //naciśnięty przycisk synchronizacji danych
    if($this->input->post('synchroXml')){
      $this->product->download_xml();
      $this->product->parse_xml();
      $this->product->updateProductsByXml();
    }


    $this->load->view('template/page/head.phtml');
    $this->load->view('template/page/top.phtml');
    $this->load->view('template/page/menu.phtml', $sidebar_variables);

    $this->load->view('template/products/admin/xml.phtml', $xml);
    $this->load->view('template/page/footer.phtml');
  }


  //MENU - Admin -> Produkty -> Sklepy
  public function admin_shops() {
    //unset($_SESSION['oauth_token_1']);
    //unset($_SESSION['oauth_token_secret_1']);
    //unset($_SESSION['token_1']);
    //unset($_SESSION['secret_1']);
    //unset($_SESSION['verifySessionNameVats']);
    $this->admin_protected();
    $this->load->model('shop');
    $this->load->model('product');
      //naciśnięty przycisk synchronizacji danych
      if($this->input->post('loginVats')){
        //logowanie do API
        $this->shop->apiLogin(1);
      }
      if($this->input->post('synchroVats')){
        //synchronizacja products z magento VATS
        $this->product->updateProductsByMagentoVats(1);
      }

    $shops = $this->shop->shops();
    $synchronizationInfo = $this->shop->get_synchronization_info();

    $sidebar_variables['menu_active'] = 'admin_products_shops';

    $this->load->view('template/page/head.phtml');
    $this->load->view('template/page/top.phtml');
    $this->load->view('template/page/menu.phtml', $sidebar_variables);

    $this->load->view('template/products/admin/shops.phtml', $shops);
    //$this->load->view('template/index.html.bak', $allegro);
    $this->load->view('template/page/footer.phtml');
  }

  public function update_cw_qty($id, $type=null) {
    $this->load->model('product');
    $this->product->update_cw_qty($id, $type);
  }

  public function detail($id, $type=null) {
    $this->load->model('shop');
    $this->load->model('product');
    $i=0;
    $sidebar_variables['menu_active'] = 'products';
    if($type=='allegro') {
        $product_detail = $this->product->get_product_allegro($id);
    } else {
        $product_detail = $this->product->get_product($id);
    }

    $api = $this->shop->get_product_by_entity_id($product_detail[0]->shop_id, $product_detail[0]->magento_entity_id);
    foreach($api->group_price as $_group_price) {
      $cust_group = $_group_price->cust_group;
      $price = $_group_price->price;
      $name = $this->shop->get_group_prices($cust_group);
      $class = $this->shop->get_group_prices_class($cust_group);

      if($cust_group==0) { continue; }
      $group_price[$i]['cust_group'] = $cust_group;
      $group_price[$i]['name'] = $name;
      $group_price[$i]['price'] = round($price,2);
      $group_price[$i]['class'] = $class;

      $i++;
    }

    $product['product'] = $product_detail[0];
    $product['deliverer'] = $this->product->get_product_deliverer_by_carier_id($product_detail[0]->carrier_id);
    $product['group_price'] = $group_price;
    $product['xml_history'] = $this->product->get_xml_history_data($product_detail[0]->shop_id, $product_detail[0]->kod_dostawcy, $product_detail[0]->carrier_id);
    $this->load->view('template/page/head.phtml');
    $this->load->view('template/page/top.phtml');
    $this->load->view('template/page/menu.phtml', $sidebar_variables);
    $this->load->view('template/products/detail.phtml', $product);
    $this->load->view('template/page/footer.phtml');
  }

}
