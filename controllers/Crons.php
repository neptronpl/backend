<?php
defined('BASEPATH') OR exit('No direct script access allowed');


class Crons extends CI_Controller {

  public function __construct()
        {
          parent::__construct();
          //unset($_SESSION['token_allegro']);
          //unset($_SESSION['verifySessionNameAllegro']);

        }

        public function mailtest() {



        }

  public function download_xml($id=null) {
    $this->load->model('product');
    $this->product->download_xml($id);
  }

  public function parse_xml() {
    $this->load->model('product');
    $this->product->parse_xml();
  }

  public function updateProductsByXml() {
    $this->load->model('product');
    $this->product->updateProductsByXml();
  }

  public function products_synchro_allegro() {
    $this->load->model('product');
    $this->load->model('allegro');
      //Allegro
      $this->allegro->allegroSynchro();
      $this->product->productsSynchro();
      $this->allegro->api_update_qty_from_xml_to_allegro();
  }

  public function products_magento_temp() {
    //wyczyszczenie poprzedniej i stworzenie bazy temp
    $this->load->model('product');
    $this->product->update_magento_temp(1);
  }

  public function products_update_magento() {
    $this->load->model('product');
    $this->product->updateProductsByMagentoVats(1);
  }

  public function products_synchro_shops() {
    $this->load->model('product');
    $this->product->api_update_qty_by_xml(1);
  }

  public function refresh_token() {
    $this->load->model('allegro');
    $this->allegro->refresh_token();
  }

  public function clean_products_xml_history() {
    //czyszczenie nadprogramowych wpisów z products_xml_history, zostaje tylko jeden wpis na produkt/dziennie
    $this->load->model('cron');
    $this->cron->clean_products_xml_history();
  }

  public function update_xml_price_changes() {
    //Sprawdzanie czy w XMLu zmieniły się ceny względem aktualnie zaakceptowanych
    $this->load->model('cron');
    $this->cron->insert_sku_to_xml_price_changes();
    $this->cron->update_xml_price_changes();
  }

  public function update_under_price() {
    //poprawa cen poniej ceny zakupu
    $this->load->model('cron');
    $this->cron->update_under_price();
  }

  public function parse_on_demand() {
    $this->load->model('product');
    $this->product->parse_xml();
  }

  public function get_incom_xml() {
    $carrier_id = 7;
    $date_xml = date('d').'.'.date('m').'.'.date('Y');
    $xml_name = $carrier_id.'-'.$date_xml.'.'.date('H').'.xml';
    $handle = fopen('../xml/'.$xml_name, 'w');
    $heading = "\xEF\xBB\xBF";
    $heading .= '<?xml version="1.0" encoding="UTF-8"?>'."\r\n";
    $heading .= "\t".'<xml>'."\r\n";
    $feed_line = $heading;
    fwrite($handle, $feed_line);
    $i=0;
    $wsdl = 'https://strona_hurtowni/NBWebServicePHP/service.svc?wsdl';
    $client = new \SoapClient($wsdl);
    $result = $client->GetTowaryInfoList(array('UserName'=>'','Password'=>''));
    //print_r($result);
    $baza = $result->GetTowaryInfoListResult->TowarLista->TowarInfoTypePHP;
    foreach ($baza as $key=>$value)
    {
      $product_data = array();
      $product_data['start']  = "\t\t".'<produkt>'."\r\n";
      $i++;
      $ean = $value->EAN;
      $description = '';
      $vat = $value->StawkaVAT;
      $mnoznikVAT= ($vat/100)+1;
      $category = $value->NazwaTypu;
      $images = '';
      $weight = '';
      $price_net = round($value->CenaNetto,2);
      $price_gross = round($price_net*$mnoznikVAT,2);
      $qty = round($value->Stan);
      $name = htmlspecialchars(str_replace('&','&amp;',$value->Nazwa));
      $sku_deliver = $value->Symbol;
      $sku_producer = $value->SymbolProducenta;
      $producer = str_replace('&','&amp;',$value->NazwaProducenta);

      $product_data['ean'] = "\t".'<ean>'.$ean.'</ean>'."\r\n";
      $product_data['nazwa_grupy_towarowej'] = "\t".'<nazwa_grupy_towarowej>'.$category.'</nazwa_grupy_towarowej>'."\r\n";
      $product_data['link_do_zdjecia_produktu'] = "\t".'<link_do_zdjecia_produktu>'.$images.'</link_do_zdjecia_produktu>'."\r\n";
      $product_data['cena'] = "\t".'<cena>'.$price_net.'</cena>'."\r\n";
      $product_data['cena_brutto'] = "\t".'<cena_brutto>'.$price_gross.'</cena_brutto>'."\r\n";
      $product_data['vat'] = "\t".'<vat>'.$vat.'</vat>'."\r\n";
      $product_data['stan_magazynowy'] = "\t".'<stan_magazynowy>'.$qty.'</stan_magazynowy>'."\r\n";
      $product_data['nazwa_produktu'] = "\t".'<nazwa_produktu>'.$name.'</nazwa_produktu>'."\r\n";
      $product_data['symbol_produktu'] = "\t".'<symbol_produktu>'.$sku_deliver.'</symbol_produktu>'."\r\n";
      $product_data['symbol_producenta'] = "\t".'<symbol_producenta>'.$sku_producer.'</symbol_producenta>'."\r\n";
      $product_data['nazwa_producenta'] = "\t".'<nazwa_producenta>'.$producer.'</nazwa_producenta>'."\r\n";

      //$this->product->insert_products_to_xml_history($carrier_id,$date_xml,$xml_name,$ean,$description,$vat,$category,$images,$weight,$price_net,$price_gross,$qty,$name,$sku_deliver,$sku_producer,$producer);
      $product_data['end'] = '</produkt>';

      foreach($product_data as $k=>$val){
        $product_data[$k] = $val;
      }

      $feed_line = implode("\t\t", $product_data)."\r\n";
      fwrite($handle, $feed_line);
      fflush($handle);

    }
    $footer = "\t".'</xml>'."\r\n";

    $feed_line = $footer;
    fwrite($handle, $feed_line);
    fclose($handle);
  }

  public function test() {

  //  $this->load->model('product');
      //Shops
//      $this->product->api_update_qty_by_xml(1);
    //echo '<pre>';
    //$result = $result->GetTowarParametryResult->TowarParametry->TowarParametrTypePHP;

    //$this->load->model('allegro');
    //$this->allegro->get_status_by_id(7796235331);

//    $this->load->model('shop');
    //$this->shop->update_price_by_entity(2297, 149.99, 1);
  }
}
