<?php
defined('BASEPATH') OR exit('No direct script access allowed');


class Backend extends CI_Controller {

  public function __construct()
        {
          parent::__construct();

          //autoryzacja na IP
/*
          if($_SERVER["REMOTE_ADDR"]!="" && $_SERVER["REMOTE_ADDR"]!="") {
            echo "<br/><br/><center><h2>unauthorized login</h2></center>";
            exit;
          }
*/
          if($this->session->userdata('isUserLoggedIn')){
          } else {
              redirect('users/login');
          }
        }

  public function index() {

    $this->load->model('product');
    $count_products = $this->product->get_products_stats_from_products_synchronization();

    $sidebar_variables['menu_active'] = 'dashboard';
    $widget_variables['count_products'] = $count_products[0]->count_auctions;

    $this->load->view('template/page/head.phtml');
    $this->load->view('template/page/top.phtml');
    $this->load->view('template/page/menu.phtml', $sidebar_variables);
    $this->load->view('template/page/widget.phtml', $widget_variables);
    if($this->session->userdata('isUserLoggedIn') && $this->session->userdata('group')=='admin'){
      $this->load->model('shop');
      $get_count_magento_temp = $this->shop->get_count_magento_temp();
      $widget_admin_variables['admin_count_magento_temp'] = $get_count_magento_temp;
      if($get_count_magento_temp==0) {
        $widget_admin_variables['admin_count_magento_temp_class'] = 'label-success';
      } else {
        $widget_admin_variables['admin_count_magento_temp_class'] = 'label-danger';
      }
      $widget_admin_variables['admin_price_alerts'] = '';
      $widget_admin_variables['$admin_price_alerts_class'] = '';
      $this->load->view('template/admin/widget.phtml', $widget_admin_variables);
    }
    //sample
    $this->load->view('template/index.phtml');
    //$this->load->view('template/index.html.bak');
    $this->load->view('template/page/footer.phtml');
  }

  public function admin_xml_price_changes(){
    $sidebar_variables['menu_active'] = 'dashboard';
    $data['table'] =array();
    $this->load->view('template/page/head.phtml');
    $this->load->view('template/page/top.phtml');
    $this->load->view('template/page/menu.phtml',$sidebar_variables);
    $this->load->view('template/admin/xml_price_changes.phtml', $data);
    $this->load->view('template/page/footer.phtml');
  }

  public function admin_price_alerts(){
    //sprawdzanie czy ceny sa w porzadku
    $sidebar_variables['menu_active'] = 'dashboard';
    $this->load->model('product');
    $data['table'] =  $this->product->get_products_price_under_buy();
    $this->load->view('template/page/head.phtml');
    $this->load->view('template/page/top.phtml');
    $this->load->view('template/page/menu.phtml',$sidebar_variables);
    $this->load->view('template/admin/admin_price_alerts.phtml', $data);
    $this->load->view('template/page/footer.phtml');
  }

  public function settings(){
    $sidebar_variables['menu_active'] = 'users';
    $this->load->view('template/page/head.phtml');
    $this->load->view('template/page/top.phtml');
    $this->load->view('template/page/menu.phtml',$sidebar_variables);
    $this->load->view('template/backend/settings.phtml');
    $this->load->view('template/page/footer.phtml');
  }

  public function disallow() {

    $sidebar_variables['menu_active'] = '';

    $this->load->view('template/page/head.phtml');
    $this->load->view('template/page/top.phtml');
    $this->load->view('template/page/menu.phtml', $sidebar_variables);
    $this->load->view('template/backend/disallow.phtml');
    $this->load->view('template/page/footer.phtml');
  }

  public function admin_magento_temp() {
    $this->load->model('shop');
    $table_magento_temp = $this->shop->get_magento_temp();
    $i=0;
    foreach($table_magento_temp as $_table_magento_temp) {
      $i++;
      $table[$i]['lp'] = $i;
      $table[$i]['entity_id'] = $_table_magento_temp->entity_id;
      $table[$i]['value'] = $_table_magento_temp->value;
      $table[$i]['type'] = $_table_magento_temp->type;
      $table[$i]['sku'] = $this->shop->get_sku_by_entity_id(1, $_table_magento_temp->entity_id);
    }
    //echo '<pre>';
    //print_r($table);

    $sidebar_variables['menu_active'] = '';
    $admin_magento_temp['table'] = $table;

    $this->load->view('template/page/head.phtml');
    $this->load->view('template/page/top.phtml');
    $this->load->view('template/page/menu.phtml', $sidebar_variables);
    $this->load->view('template/admin/admin_magento_temp.phtml', $admin_magento_temp);
    $this->load->view('template/page/footer.phtml');
  }
}
