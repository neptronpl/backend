<?php
class Cron extends CI_Model {
  public function __construct() {
    parent::__construct();
    $this->neptronSessionsTbl = 'neptron_sessions';
    $this->productsTbl = 'products';
    $this->productsXmlHistoryTbl = 'products_xml_history';
    $this->productsXmlPriceChangesTbl = 'products_xml_price_changes';
    $this->email_config = Array(
      'protocol'  => 'smtp',
      'smtp_host' => 'smtp-relay.gmail.com',
      'smtp_port' => '587',
      //'smtp_user' => '',
      //'smtp_pass' => '',
      'charset' => 'utf-8',

      'mailtype'  => 'html',
      "smtp_crypto"   =>"tls",
      'starttls'  => true,
      'newline'   => "\r\n"
     );
     $this->emailName = 'Stróż Backendu';
     $this->emailAdress = 'awizo@vats.pl';
  }

  public function clean_products_xml_history() {
    $date = date('d.m.Y', strtotime('-1 day'));
    //$this->db->select('xml_name');
    $this->db->where('xml_name not like "%16.xml" and xml_name not like "%17.xml" and date_xml = "'.$date.'" ');
    $this->db->delete($this->productsXmlHistoryTbl);
    //$this->db->limit('100');
    //$result = $this->db->get();
    //$result = $result->result();
    //echo '<pre>';
    //print_r($result);
  }

  public function get_session_token($name) {
    $this->db->select('value');
    $this->db->from($this->neptronSessionsTbl);
    $this->db->where(array('name' => $name));
    $token = $this->db->get();
    return $token->row()->value;
  }

  public function check_session_token($name) {
  //sprawdzenie czy token kiedyś był dodany
    $this->db->select('name');
    $this->db->from('neptron_sessions');
    $this->db->where(array('name' => $name));
    $query = $this->db->get();
    if($query->num_rows()) {
      return 1;
    }
    else {
      return 0;
    }
  }

  public function update_session_token($name,$token) {
  //Aktualizacja tokenów do API w db
    //sprawdzenie czy token był dodany w przeszłości
      if($this->check_session_token($name) == 1) {
        $this->db->where('name', $name);
        $this->db->update('neptron_sessions', array('value' => $token));
      } else {
        $this->db->insert('neptron_sessions', array('name' => $name, 'value' => $token));
      }
  }

  public function insert_sku_to_xml_price_changes() {
  //dodanie sku do products_xml_price_changes
          //$this->db->select('a.id, b.sentence as sentence, IF(sentence is NULL,a.name,b.sentence) as sentence_translated',false);
          //$this->db->from($this->industryTbl.' as a');
          //$this->db->where('a.active',1);
          //$this->db->join('locales as b', 'a.id = b.translate_id and b.lang ="'.$lang.'" and b.table_name = "'.$this->industryTbl.'"','LEFT');
          //$data = $this->db->get();
          //$result = $data->result();
    $this->db->select('sku, carrier_id, kod_dostawcy, date_add');
    $this->db->from($this->productsTbl.' as a');
    $this->db->where('a.sku not in (select b.sku from '.$this->productsXmlPriceChangesTbl.' b )');
    //$this->db->select('a.sku, c.price_net, c.price_gross');
    //$this->db->join($this->productsXmlHistoryTbl.' as c', 'a.carrier_id = c.carrier_id and a.kod_dostawcy = c.sku_deliver','LEFT');
    $result = $this->db->get();
    $result = $result->result();
    foreach($result as $_result) {
      $this->db->insert($this->productsXmlPriceChangesTbl, array('sku' => $_result->sku, 'carrier_id' => $_result->carrier_id, 'sku_deliver' => $_result->kod_dostawcy,  'date_add' => $_result->date_add, 'checked' => 0));
    }
    //echo '<pre>';
    //print_r($result);
    //exit;
  }
/*
  public function update_xml_price_changes_curret($sku, $carrier_id, $sku_deliver) {
//aktualne ceny w products
    $this->db->select('price_gross');
    $this->db->from($this->productsTbl);
    $this->db->where('kod_dostawcy', $sku_deliver);
    $this->db->where('carrier_id', $carrier_id);
    $result = $this->db->get();
    $result = $result->result();
    $price_net = $result[0]->price_gross;
    $price_gross = round($result[0]->price_gross/1.23,2);
    $result_price = array('price_net' => $price_net, 'price_gross' => $price_gross);
    return $result_price;
  }
*/

  public function add_reason_xml_price_changes($carrier_id, $sku_deliver, $reason) {
    $this->db->where('carrier_id', $carrier_id);
    $this->db->where('sku_deliver', $sku_deliver);
    $this->db->update($this->productsXmlPriceChangesTbl, array('checked' => 0, 'reason' => $reason));
  }


  public function get_xml_price_changes_first($carrier_id, $sku_deliver, $date_xml) {
  //pobranie pierwszej ceny kiedy byl wprowadzany produkt
    $this->db->select('price_net, price_gross');
    $this->db->from($this->productsXmlHistoryTbl);
    $this->db->where('carrier_id',$carrier_id);
    $this->db->where('sku_deliver',$sku_deliver);
    $this->db->where('date_xml',$date_xml);
    $result = $this->db->get();
    $result = $result->result();
    if(isset($result[0])) {
      return $result[0];
    } else {
      $this->add_reason_xml_price_changes($carrier_id, $sku_deliver, 'Brak produktu w XML [price_net_first, price_gross_first] - możliwe, że produktu nie ma w ogóle lub date_xml jest złe ');
      return 0;
    }

  }

  public function update_xml_price_changes_first($sku, $carrier_id, $sku_deliver, $price_net_first, $price_gross_first) {
    //aktualizacja pierwszej ceny kiedy byl wprowadzany produkt
    $this->db->where('sku', $sku);
    $this->db->where('carrier_id', $carrier_id);
    $this->db->where('sku_deliver', $sku_deliver);
    $this->db->update($this->productsXmlPriceChangesTbl, array('price_net_first' => $price_net_first, 'price_gross_first' => $price_gross_first, 'checked' => 1));
  }

  public function update_under_price() {
    //aktualizacja pierwszej ceny kiedy byl wprowadzany produkt

    $this->load->model('shop');
    $this->load->model('product');
    $this->load->library("email", $this->email_config);

    foreach($this->product->get_products_price_under_buy() as $_product) {

      $magento_entity_id = $_product->magento_entity_id;
      $products_history_price_gross = $_product->products_history_price_gross;
      $sku = $_product->sku;

      $this->shop->update_price_by_entity($magento_entity_id, $products_history_price_gross, 1);
      $this->product->update_price_gross_by_sku($sku, $products_history_price_gross);
      $reason = 'Coś nie tak z cenami w produkcie: '.$sku .'<br/>Poprawiam detaliczna na '. $products_history_price_gross .' ale upewnij sie czy reszta cen hurtowych jest ok';
      $this->email->from($this->emailAdress, $this->emailName);
      $this->email->to('biuro@neptron.pl');
      $this->email->subject('Blad w cenach');
      $this->email->message($reason);
      $this->email->send();

    }

  }

  public function check_update_xml_price_changes_first($sku, $carrier_id, $sku_deliver) {
  //pobranie daty kiedy byl wprowadzany produkt i pobranie cen a nastepnie aktualizacja
    $this->db->select('a.date_add',false);
    $this->db->from($this->productsTbl.' as a');
    $this->db->where('a.carrier_id',$carrier_id);
    $this->db->where('a.kod_dostawcy',$sku_deliver);
    $this->db->where('a.sku',$sku);
    //$this->db->join('locales as b', 'a.id = b.translate_id and b.lang ="'.$lang.'" and b.table_name = "'.$this->industryTbl.'"','LEFT');
    $result = $this->db->get();
    $result = $result->result();
    $date_xml = date('d.m.Y',strtotime($result[0]->date_add));
    $price_first = $this->get_xml_price_changes_first($carrier_id, $sku_deliver, $date_xml);
    if($price_first){
      $price_net_first = $price_first->price_net;
      $price_gross_first = $price_first->price_gross;
      $this->update_xml_price_changes_first($sku, $carrier_id, $sku_deliver, $price_net_first, $price_gross_first);
    }
  }


  public function update_xml_price_changes() {
    //Sprawdzanie czy w XMLu zmieniły się ceny względem aktualnie zaakceptowanych
    $this->db->select('*');
    $this->db->from($this->productsXmlPriceChangesTbl);
    $result = $this->db->get();
    $result = $result->result();
    foreach ($result as $_result) {
      //$price_current = $this->update_xml_price_changes_first($sku, $carrier_id, $sku_deliver)
      $sku = $_result->sku;
      $carrier_id = $_result->carrier_id;
      $sku_deliver = $_result->sku_deliver;
      $price_net_first = $_result->price_net_first;
      $price_gross_first = $_result->price_gross_first;
      $checked = $_result->checked;
      if(($price_gross_first == 0 || $price_gross_first == 0) && $checked==0) {
        //jesli pierwsza cena nie ustawiona -> ustaw

        $this->check_update_xml_price_changes_first($sku, $carrier_id, $sku_deliver);
      }

    }

  }


}
