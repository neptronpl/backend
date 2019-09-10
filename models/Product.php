<?php
class Product extends CI_Model {

        function __construct() {
          $this->productsTbl = 'products';
          $this->synchronizationProductsTbl = 'products_synchronization';
          $this->productsXmlHistoryTbl = 'products_xml_history';
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

        public function xml($id=null) {
        //aktualne xmle
        $xml['xml_carriers'][1] = [
            'name' => 'hurtownia_1',
            'id' => '1',
            'xml_url' => 'https://hurtownia_1/xml'
            ];
        $xml['xml_carriers'][2] = [
            'name' => 'hurtownia_2',
            'id' => '2',
            'xml_url' => 'https://hurtownia_2/xml'
            ];
        $xml['xml_carriers'][4] = [
            'name' => 'hurtownia_4',
            'id' => '4',
            'xml_url' => 'https://hurtownia_4/xml'
            ];
        $xml['xml_carriers'][6] = [
            'name' => 'hurtownia_6',
            'id' => '6',
            'xml_url' => 'https://hurtownia_7/xml'
            ];
        $xml['xml_carriers'][7] = [
            'name' => 'hurtownia_7',
            'id' => '7',
            'xml_url' => ''
            ];
        $xml['xml_carriers'][9] = [
            'name' => 'hurtownia_9',
            'id' => '9',
            'xml_url' => 'https://hurtownia_9/xml'
            ];
            $xml['xml_carriers'][8] = [
                'name' => 'hurtownia_8',
                'id' => '8',
                'xml_url' => ''
                ];
            if($id==null) {
                return $xml;
            } else {
              $result['xml_carriers'][$id]=$xml['xml_carriers'][$id];
              return $result;
            }

        }


        public function download_xml($id=null) {
        //Pobieranie plików xml
          $date = date('d.m.Y.H');
          $dir = '../xml/';

          $xml = $this->xml($id);
          echo '<br/><pre>';


          foreach($xml['xml_carriers'] as $_xml) {
            if($_xml['id']==7) {
            //jeśli incomm to pobierz przez SOAP bo nie ma otwartego linku do xmla
            //dodane w cronie pobieranie incommu
              continue;
            }

            print_r($_xml);

            $id = $_xml['id'];
            $xml_url = $_xml['xml_url'];
              $_file = $id.'-'.$date.'.xml';
              $file = $dir.$id.'-'.$date.'.xml';
              //czy plik istnieje lub czy byl juz wprowadzony do bazy danych?
              if(file_exists($file) || $this->check_xml_was_parsed($_file)==1) {
                echo 'Plik '.$id.' '. $date.' był już pobrany<br/>';
              } else {
              //jesli nie to pobierz
                echo 'Pobieranie: '. $file.'<br/>';
                $content_source = file_get_contents($xml_url);
                $content = str_replace(array("<g:","</g:"), array('<','</'), $content_source);
                file_put_contents($file, $content);
              }
          }
        }

        public function parse_xml() {
          $this->load->library("email", $this->email_config);
        //listowanie katalogu z XMLami, parsowanie ich i wprowadzenie do bazy

        //listowanie
        $dir = '../xml/';
        $files = scandir($dir);
        foreach($files as $_files) {
          if(strpos($_files, '.xml')) {
            $file = $dir.$_files;

            //sprawdzanie czy plik nie był już parsowany
            if($this->check_xml_was_parsed($_files)==0) {

              echo $this->check_xml_was_parsed($_files).'<br/>'.$_files.'<br/>';

              $file_xml = simplexml_load_file($file);

              //sprawdzenie jaka mape parsowania zastosowac
              $file_carrier_id = explode('-',$_files);
              $i=0;

              switch($file_carrier_id[0]) {
                case 1:
                //Allper
                if($file_xml->produkty==null) {
                  $reason = 'Coś nie tak z plikiem: '.$file;
                  $this->email->from($this->emailAdress, $this->emailName);
                  $this->email->to('biuro@neptron.pl');
                  $this->email->subject('XML nie działa');
                  $this->email->message($reason);
                  $this->email->send();
                  unlink($file);
                  exit;
                }
                  //glowna petla parsujaca najwazniejsze informacje o produkcie
                  //**
                  foreach($file_xml->produkty as $_file_xml) {

                    $carrier_id = $file_carrier_id[0];
                    $date_xml = substr($file_carrier_id[1],0,-7);
                    $xml_name = $_files;

                    $ean = $_file_xml->EAN;
                    //$description = $_file_xml->opis;
                    $description = '';
                    $vat = $_file_xml->vat;
                    $category = $_file_xml->WidoczneKategorie->kategorie->Sciezka;
                    $images = $_file_xml->zdjecia->Zdjecie->LinkOryginal;
                    $weight = '';
                    $price_net = $_file_xml->cena_netto;
                    $price_gross = $_file_xml->cena_brutto;
                    $qty = $_file_xml->stan;
                    $name = $_file_xml->nazwa;
                    $sku_deliver = $_file_xml->produkt_id;
                    $sku_producer = $_file_xml->kod;
                    $producer = $_file_xml->producent;

                    //filtr
                    //if($producer!='Moonlight') { continue; }

                    //echo 'carrier_id: <strong>'.$carrier_id.'</strong>, xml_name: <strong>'. $xml_name .'</strong>, name: <strong>'. $name .'</strong>, producer: <strong>'. $producer .'</strong>, sku_deliver: <strong>'. $sku_deliver .'</strong>, sku_producer: <strong>'. $sku_producer .'</strong>, ean: <strong>'.$ean.'</strong>, category: <strong>'. $category .'</strong> , description:  <strong>'. $description .'</strong>, images: <strong>'. $images .'</strong> , weight: <strong>'. $weight .'</strong>, qty: <strong>'. $qty .'</strong>, vat: <strong>'. $vat .'</strong>, price_net: <strong>'. $price_net .'</strong>, price_gross: <strong>'. $price_gross .'</strong>, date_xml: <strong>'. $date_xml .'</strong>';
                    //echo '<br/>';
                    $i++;

                    //zeby nie szlo za dlugo
                    /*
                    if($i==50) {
                      exit;
                    }
                    */
                    //
                    //wprowadzenie danych do bazy
                    $this->insert_products_to_xml_history($carrier_id,$date_xml,$xml_name,$price_net,$price_gross,$qty,$sku_deliver,$sku_producer);
                  }
                  //**
                  //
                  //print_r($file_xml);
                break;

                case 2:
                //Activeshop
                //glowna petla parsujaca najwazniejsze informacje o produkcie
                //**
                if($file_xml->item==null) {
                  $reason = 'Coś nie tak z plikiem: '.$file;
                  $this->email->from($this->emailAdress, $this->emailName);
                  $this->email->to('biuro@neptron.pl');
                  $this->email->subject('XML nie działa');
                  $this->email->message($reason);
                  $this->email->send();
                  unlink($file);
                  exit;
                }
                foreach($file_xml->item as $_file_xml) {
                  $carrier_id = $file_carrier_id[0];
                  $date_xml = substr($file_carrier_id[1],0,-7);
                  $xml_name = $_files;

                  $ean = '';
                  //$description = $_file_xml->description;
                  $description = '';
                  $vat = str_replace('%','',$_file_xml->tax);
                  $category = $_file_xml->category_subcategory;
                  $images = $_file_xml->images->image;
                  $weight = $_file_xml->weight;
                  $price_net = round($file_xml->price/$vat,2);
                  $price_gross = $_file_xml->price;
                  $qty = $_file_xml->qty;
                  $name = $_file_xml->name;
                  $sku_deliver = $_file_xml->sku;
                  $sku_producer = '';
                  $producer = $_file_xml->manufacturer;

                  //filtr
                  //if($producer!='Moonlight') { continue; }

                  //echo 'carrier_id: <strong>'.$carrier_id.'</strong>, xml_name: <strong>'. $xml_name .'</strong>, name: <strong>'. $name .'</strong>, producer: <strong>'. $producer .'</strong>, sku_deliver: <strong>'. $sku_deliver .'</strong>, sku_producer: <strong>'. $sku_producer .'</strong>, ean: <strong>'.$ean.'</strong>, category: <strong>'. $category .'</strong> , description:  <strong>'. $description .'</strong>, images: <strong>'. $images .'</strong> , weight: <strong>'. $weight .'</strong>, qty: <strong>'. $qty .'</strong>, vat: <strong>'. $vat .'</strong>, price_net: <strong>'. $price_net .'</strong>, price_gross: <strong>'. $price_gross .'</strong>, date_xml: <strong>'. $date_xml .'</strong>';
                  //echo '<br/>';
                  $i++;

                  //zeby nie szlo za dlugo
                  /*
                  if($i==50) {
                    exit;
                  }
                  */
                  //
                  //wprowadzenie danych do bazy
                  $this->insert_products_to_xml_history($carrier_id,$date_xml,$xml_name,$price_net,$price_gross,$qty,$sku_deliver,$sku_producer);
                }
                //**
                //
                //echo '<pre>';
                //print_r($file_xml);
                //echo '</pre>';
                break;

                case 4:
                //Kinghoff
                //glowna petla parsujaca najwazniejsze informacje o produkcie
                //**
                if($file_xml->item==null) {
                  $reason = 'Coś nie tak z plikiem: '.$file;
                  $this->email->from($this->emailAdress, $this->emailName);
                  $this->email->to('biuro@neptron.pl');
                  $this->email->subject('XML nie działa');
                  $this->email->message($reason);
                  $this->email->send();
                  unlink($file);
                  exit;
                }
                foreach($file_xml->item as $_file_xml) {
                  $carrier_id = $file_carrier_id[0];
                  $date_xml = substr($file_carrier_id[1],0,-7);
                  $xml_name = $_files;

                  $ean = $_file_xml->prod_ean;
                  //$description = $_file_xml->prod_desc;
                  $description = '';
                  $vat = $_file_xml->taxpercent;
                  $category = $_file_xml->cat_path;
                  $images = $_file_xml->prod_img->img;
                  $weight = $_file_xml->prod_weight/1000;
                  $price_net = $_file_xml->prod_tax_id;
                  $price_gross = $_file_xml->prod_price;
                  $qty = $_file_xml->prod_amount;
                  $name = $_file_xml->prod_name;
                  $sku_deliver = $_file_xml->prod_id;
                  $sku_producer = $_file_xml->prod_symbol;
                  $producer = $_file_xml->producent;

                  //filtr
                  //if($producer!='Moonlight') { continue; }

                  //echo 'carrier_id: <strong>'.$carrier_id.'</strong>, xml_name: <strong>'. $xml_name .'</strong>, name: <strong>'. $name .'</strong>, producer: <strong>'. $producer .'</strong>, sku_deliver: <strong>'. $sku_deliver .'</strong>, sku_producer: <strong>'. $sku_producer .'</strong>, ean: <strong>'.$ean.'</strong>, category: <strong>'. $category .'</strong> , description:  <strong>'. $description .'</strong>, images: <strong>'. $images .'</strong> , weight: <strong>'. $weight .'</strong>, qty: <strong>'. $qty .'</strong>, vat: <strong>'. $vat .'</strong>, price_net: <strong>'. $price_net .'</strong>, price_gross: <strong>'. $price_gross .'</strong>, date_xml: <strong>'. $date_xml .'</strong>';
                  //echo '<br/>';
                  $i++;

                  //zeby nie szlo za dlugo
                  /*
                  if($i==50) {
                    exit;
                  }
                  */
                  //
                  //wprowadzenie danych do bazy
                  $this->insert_products_to_xml_history($carrier_id,$date_xml,$xml_name,$price_net,$price_gross,$qty,$sku_deliver,$sku_producer);
                }
                //**
                //
                //echo '<pre>';
                //print_r($file_xml);
                //echo '</pre>';
                break;

                case 6:
                //Eltrox
                //glowna petla parsujaca najwazniejsze informacje o produkcie
                //**

                if($file_xml->item==null) {
                  $reason = 'Coś nie tak z plikiem: '.$file;
                  $this->email->from($this->emailAdress, $this->emailName);
                  $this->email->to('biuro@neptron.pl');
                  $this->email->subject('XML nie działa');
                  $this->email->message($reason);
                  $this->email->send();
                  unlink($file);
                  exit;
                }
                foreach($file_xml->item as $_file_xml) {
                  $carrier_id = $file_carrier_id[0];
                  $date_xml = substr($file_carrier_id[1],0,-7);
                  $xml_name = $_files;

                  $ean = $_file_xml->ean;
                  //$description = $_file_xml->description;
                  $description = '';
                  $vat = '23';
                  $category = $_file_xml->category;
                  $images = $_file_xml->images->img[0];
                  $weight = $_file_xml->weight;
                  $price_gross = $_file_xml->price;
                  $price_net = round($price_gross/1.23,2);
                  $qty = $_file_xml->qty;
                  $name = $_file_xml->name;
                  $sku_deliver = $_file_xml->id;
                  $sku_producer = '';
                  $producer = $_file_xml->brand;
                  $i++;

                  //zeby nie szlo za dlugo

                  //if($i==10) {
                    //exit;
                  //}

                  //
                  //wprowadzenie danych do bazy

                  $this->insert_products_to_xml_history($carrier_id,$date_xml,$xml_name,$price_net,$price_gross,$qty,$sku_deliver,$sku_producer);
                }


                break;


                case 7:
                //Incom
                //glowna petla parsujaca najwazniejsze informacje o produkcie
                //**
                if($file_xml->produkt==null) {
                  $reason = 'Coś nie tak z plikiem: '.$file;
                  $this->email->from($this->emailAdress, $this->emailName);
                  $this->email->to('biuro@neptron.pl');
                  $this->email->subject('XML nie działa');
                  $this->email->message($reason);
                  $this->email->send();
                  unlink($file);
                  exit;
                }
                foreach($file_xml->produkt as $_file_xml) {
                  $carrier_id = $file_carrier_id[0];
                  $date_xml = substr($file_carrier_id[1],0,-7);
                  $xml_name = $_files;

                  $ean = $_file_xml->ean;
                  //$description = $_file_xml->opis_produktu;
                  $description = '';
                  $vat = $_file_xml->vat;
                  $category = $_file_xml->nazwa_grupy_towarowej;
                  $images = $_file_xml->link_do_zdjecia_produktu;
                  $weight = 0;
                  $price_net = $_file_xml->cena;
                  //$price_gross = round($file_xml->cena*1.23,2);
                  $price_gross = $_file_xml->cena_brutto;
                  $qty = $_file_xml->stan_magazynowy;
                  $name = $_file_xml->nazwa_produktu;
                  $sku_deliver = $_file_xml->symbol_produktu;
                  $sku_producer = $_file_xml->symbol_producenta;
                  $producer = $_file_xml->nazwa_producenta;

                  //filtr
                  //if($qty<2) { continue; }

                  //echo 'carrier_id: <strong>'.$carrier_id.'</strong>, xml_name: <strong>'. $xml_name .'</strong>, name: <strong>'. $name .'</strong>, producer: <strong>'. $producer .'</strong>, sku_deliver: <strong>'. $sku_deliver .'</strong>, sku_producer: <strong>'. $sku_producer .'</strong>, ean: <strong>'.$ean.'</strong>, category: <strong>'. $category .'</strong> , description:  <strong>'. $description .'</strong>, images: <strong>'. $images .'</strong> , weight: <strong>'. $weight .'</strong>, qty: <strong>'. $qty .'</strong>, vat: <strong>'. $vat .'</strong>, price_net: <strong>'. $price_net .'</strong>, price_gross: <strong>'. $price_gross .'</strong>, date_xml: <strong>'. $date_xml .'</strong>';
                  //echo '<br/>';
                  $i++;

                  //zeby nie szlo za dlugo

                  //if($i==10) {
                    //exit;
                  //}

                  //
                  //wprowadzenie danych do bazy
                  $this->insert_products_to_xml_history($carrier_id,$date_xml,$xml_name,$price_net,$price_gross,$qty,$sku_deliver,$sku_producer);
                }
                break;

                case 9:
                //CoffeeDesk
                //glowna petla parsujaca najwazniejsze informacje o produkcie
                //**

                if($file_xml->product==null) {
                  $reason = 'Coś nie tak z plikiem: '.$file;
                  $this->email->from($this->emailAdress, $this->emailName);
                  $this->email->to('biuro@neptron.pl');
                  $this->email->subject('XML nie działa');
                  $this->email->message($reason);
                  $this->email->send();
                  unlink($file);
                  exit;
                }
                foreach($file_xml->product as $_file_xml) {
                  $carrier_id = $file_carrier_id[0];
                  $date_xml = substr($file_carrier_id[1],0,-7);
                  $xml_name = $_files;

                  $ean = $_file_xml->ean13;
                  //$description = $_file_xml->description;
                  $description = '';
                  $vat = $_file_xml->vat;
                  $category = $_file_xml->category.' '.$file_xml->categories;
                  $images = $_file_xml->imagesurl->imageurl[0];
                  $weight = $_file_xml->weight;
                  $price_gross = $_file_xml->price_reg;
                  $price_net = round($price_gross/1.23,2);
                  $qty = $_file_xml->quantity;
                  $name = $_file_xml->name;
                  $sku_deliver = $_file_xml->id;
                  $sku_producer = $_file_xml->code;
                  $producer = $_file_xml->brand;
                  $i++;

                  //zeby nie szlo za dlugo

                  //if($i==10) {
                    //exit;
                  //}

                  //
                  //wprowadzenie danych do bazy

                  $this->insert_products_to_xml_history($carrier_id,$date_xml,$xml_name,$price_net,$price_gross,$qty,$sku_deliver,$sku_producer);
                }


                break;

              }



            }


            //usuniecie pliku
            echo 'usuwam: '. $file.'<br/>';
            unlink($file);

          }

        }

        }



        public function get_products()
        {
        //pobranie wszystkich produktów
          $this->db->select('id, carrier_id, magento_entity_id, name, kod_dostawcy, kod_producenta, sku, qty_xml, qty_vats, qty_damakaro, qty_allegro, qty_cw, allegro_id, allegro_status');
          $this->db->from('products');
          //$this->db->join('products_allegro', 'products_allegro.sku = products.sku', 'left');
          $query = $this->db->get();
          return $query->result();
        }

        public function get_products_filter($row, $value)
        {
        //stara wersja funkcji do filtrowania wyników w products
          $query = $this->db->get_where('products', array($row => $value));
          return $query->result();
        }

        public function get_products_filter_fixed($filter_query) {
        //nowa wersja funkcji do filtrowania wyników w products
          //$count = count($filter_query);
          $this->db->select('*');
          $this->db->from('products');
          foreach($filter_query as $_filter_query) {
              $this->db->where_in($_filter_query['index'],$_filter_query['value']);
//print_r($_filter_query['value']);

          }

          $query = $this->db->get();
          $this->db->last_query();
          return $query->result();
        }

        public function get_product($id)
        {
          //pobranie danych o produkcie na podstawie jego id
          $this->db->select('*');
          $this->db->from('products');
          $this->db->where('id',$id);
          $query = $this->db->get();
          return $query->result();
        }

        public function get_product_allegro($allegro_id)
        {
          //pobranie danych o produkcie na podstawie allegro id
          $this->db->select('*');
          $this->db->from('products');
          $this->db->where('allegro_id',$allegro_id);
          $query = $this->db->get();
          return $query->result();
        }

        public function get_product_with_different_qty($shop)
        {
          //pobranie danych o produktach które mają inne qty xml != magento
          $this->db->select('*');
          $this->db->from('products');
          switch($shop) {
            case 1:
              //$this->db->where('qty_xml != ""');
              $this->db->where('qty_xml != qty_vats');
            break;
          }
          $this->db->limit(270);
          $query = $this->db->get();
          return $query->result();
        }

        public function get_products_price_under_buy()
        //pobranie produktw ktre maj nizsza cene niz cena zakupu
        {

          $date = date('d',strtotime('-1 day')).'.'.date('m').'.'.date('Y');
          $this->db->select('a.sku, a.magento_entity_id, a.price_gross as products_price_gross, b.price_gross as products_history_price_gross');
          $this->db->from('products as a');
          $this->db->join('products_xml_history as b', 'a.carrier_id = b.carrier_id and a.kod_dostawcy = b.sku_deliver','LEFT');
          $this->db->where('b.date_xml = "'.$date.'"');
          $this->db->where('(a.price_gross+11) < b.price_gross');
          $this->db->group_by('a.sku');
          //select a.sku, a.magento_entity_id, a.price_gross as products_price_gross, b.price_gross as products_history_price_gross from products a left join products_xml_history as b on a.carrier_id = b.carrier_id and a.kod_dostawcy = b.sku_deliver where (a.price_gross+11) < b.price_gross and b.date_xml = '16.08.2019'
          //
          $query = $this->db->get();
          var_dump($query->result());
          return $query->result();
        }

        public function get_products_stats_from_products_synchronization() {
          $query = $this->db->get_where($this->synchronizationProductsTbl, array('name' => 'All'));
          return $query->result();
        }

        public function get_product_deliverer_by_carier_id($carrier_id) {
          $xml = $this->xml();
          return $xml['xml_carriers'][$carrier_id]['name'];
        }


        public function get_xml_history_data($shop, $sku_deliver, $carrier_id) {
          //pobranie danych o produkcie na podstawie jego id
          $this->db->select('xml_name, qty, price_gross');
          $this->db->from('products_xml_history');
          $this->db->where('carrier_id',$carrier_id);
          $this->db->where('sku_deliver',$sku_deliver);
          $this->db->where('shop_id',$shop);
          $this->db->order_by('id','desc');
          $query = $this->db->get();
          return $query->result();
        }


        public function productsSynchro() {
        //synchronizacja danych w products

        //synchronizacja danych z allegro_products do products
          $this->load->model('allegro');
          $getObsAuctions = $this->allegro->get_obs_auctions_group_concat();
          foreach($getObsAuctions as $_getObsAuctions) {
            $sku = $_getObsAuctions->sku;
            $allegro_id = $_getObsAuctions->allegro_id;
            $qty_allegro = $_getObsAuctions->allegro_qty;
            $status_allegro = $_getObsAuctions->allegro_status;

            $this->update_allegro_id_by_sku($sku, $allegro_id);
            $this->update_allegro_qty_by_sku($sku, $qty_allegro);
            $this->update_allegro_status_by_sku($sku, $status_allegro);
          }

        }

        public function insert_products_to_xml_history($carrier_id,$date_xml,$xml_name,$price_net,$price_gross,$qty,$sku_deliver,$sku_producer) {
        //wprowadzenie informacji o produkcie z XML do products_xml_history
          $data['carrier_id'] = $carrier_id;
          $data['xml_name'] = $xml_name;
          $data['date_xml'] = $date_xml;
          $data['price_net'] = $price_net;
          $data['price_gross'] = $price_gross;
          $data['qty'] = (int)$qty;
          $data['sku_deliver'] = $sku_deliver;
          $data['sku_producer'] = $sku_producer;
          $this->db->insert($this->productsXmlHistoryTbl, $data);
        }

        public function update_cw_qty($id, $type) {
        //aktualizacja stanow central warehouse dla danego id

          switch($type) {
            case 'up':
              $this->db->set('qty_cw', '`qty_cw`+1', FALSE);
            break;
            case 'down':
              $this->db->set('qty_cw', '`qty_cw`-1', FALSE);
            break;
          }
          $this->db->where('id', $id);
          $this->db->update($this->productsTbl);
          redirect('products/detail/'.$id);
        }

        public function update_products_stats_to_products_synchronization() {
        //aktualizacja liczby produktów
          $productsActiveCount = count($this->get_products());
          $this->db->where('name', 'All');
          $this->db->update($this->synchronizationProductsTbl, array('count_auctions' => $productsActiveCount, 'last_synchronization_date' => date('Y-m-d H:i:s')));
        }


        public function update_allegro_id_by_sku($sku, $allegro_id) {
        //aktualizacja allegro_id w products
          $this->db->where('sku', $sku);
          $this->db->update($this->productsTbl, array('allegro_id' => $allegro_id));
        }


        public function update_allegro_qty_by_sku($sku, $qty_allegro) {
        //aktualizacja allegro_qty w products
          $this->db->where('sku', $sku);
          $this->db->update($this->productsTbl, array('qty_allegro' => $qty_allegro));
        }

        public function update_allegro_status_by_sku($sku, $status_allegro) {
        //aktualizacja allegro_status w products
          $this->db->where('sku', $sku);
          $this->db->update($this->productsTbl, array('allegro_status' => $status_allegro));
        }

        public function update_price_gross_by_sku($sku, $price_gross) {
        //aktualizacja ceny w products
          $this->db->where('sku', $sku);
          $this->db->update($this->productsTbl, array('price_gross' => $price_gross));
        }

        public function update_products_xml_qty($sku,$carrier_id,$sku_deliver) {
        //aktualizacja qty_xml w products na podstawie najswiezszego, dzisiejszego wpisu w products_xml_history
          $date = date('d').'.'.date('m').'.'.date('Y');

          $this->db->order_by('id','desc');
          $this->db->limit(1);
          $query = $this->db->get_where($this->productsXmlHistoryTbl, array('carrier_id' => $carrier_id, 'sku_deliver' => $sku_deliver, 'date_xml' => $date));
          $result = $query->result();
          if($result) {
            $qty = (int)$result[0]->qty;
            $this->db->where('sku', $sku);
            $this->db->update($this->productsTbl, array('qty_xml' => $qty));
          } else {
            $temp_xml_name = $carrier_id.'-'.$date.'.'.date('h').'.xml';
            $this->db->where('sku', $sku);
            $this->db->update($this->productsTbl, array('qty_xml' => 0));
            $this->insert_products_to_xml_history($carrier_id,$date,$temp_xml_name,'0','0','0',$sku_deliver,'0');
          }
        }

        public function update_products_magento($entity_id, $type) {
        //aktualizacja products na podstawie danych z tabeli magento_temp
        $this->load->model('shop');
          switch($type) {
            case 'qty':
            //aktualizacja qty_vats na podstawie danych z API
              $qty = $this->shop->get_value_from_temp($entity_id, $type);
              $this->db->set('qty_vats',(int)$qty);
            break;
          }

          $this->db->where('magento_entity_id', $entity_id);
          $this->db->update('products');

          $this->shop->delete_value_from_temp($entity_id, $type);

        }


        public function update_products_magento_qty($sku,$entity_id,$carrier_id,$shop,$qty) {
        //aktualizacja qty_vats na podstawie danych z API
        //wersja do aktualizacji pojedynczego rekordu bezposrednio przez api - malo wydajne przy duzych partiach

          switch($shop) {
            case 1:
              $this->db->set('qty_vats',(int)$qty);
            break;
          }
          $this->db->where('sku', $sku);
          $this->db->where('magento_entity_id', $entity_id);
          $this->db->where('carrier_id', $carrier_id);
          $this->db->update('products');
          //echo $sku .' - '. $entity_id.' - '. $qty .' - '. $shop .'<br>';
        }



        public function updateProductsByXml() {
        //synchronizacja danych z products_xml_history do products

        //pobieram wszystkie dodane produkty
          $products = $this->get_products();
          foreach($products as $_products) {
            $sku = $_products->sku;
            $carrier_id = $_products->carrier_id;
            $sku_deliver = $_products->kod_dostawcy;
          //jesli brakuje sku dostawcy to pomijam
            if(!$sku_deliver) { continue; }

            //pobieram dane tego produktu z ostatniej synchronizacji

            //****dodać zabezpieczenie przed wykonywaniem tej pętli jeśli już został zaktualizowany ostatni xml, moze jakaś zmienna z nazwą pliku xml który ostatnio był przetwarzany?****//

            //aktualizuje xml_qty na podstawie xml_history
            $this->update_products_xml_qty($sku,$carrier_id,$sku_deliver);


          }

        }

        public function update_magento_temp($shop) {
          $this->load->model('shop');

          //pobieram stocki z Vats.pl wcześniej czyszcząc poprzednie pobranie i wprowadzając nowe wpisy w magento_temp
          $this->truncate_table('magento_temp');
          $this->shop->get_qty_from_magento($shop);
        }

        public function updateProductsByMagentoVats($shop) {
        //synchronizacja danych z API VATS do products

        //pobieram wszystkie dodane produkty
          $products = $this->get_products();
          $i=0;
          foreach($products as $_products) {
            $i++;
            $sku = $_products->sku;
            $carrier_id = $_products->carrier_id;
            $entity_id = $_products->magento_entity_id;

            //jesli brakuje entity_id
            if(!$entity_id) { continue; }

            //aktualizuje qty
            //**
            //$qty = $this->shop->getQtyByEntityId($shop, $entity_id);
            //$this->update_products_magento_qty($sku,$entity_id,$carrier_id,$shop,$qty);
            //**
            $this->update_products_magento($entity_id, 'qty');

            //if($i==11) { break; }
          }

        }

        public function check_xml_was_parsed($file) {
        //sprawdzanie czy plik był już parsowany
          $query = $this->db->get_where($this->productsXmlHistoryTbl, array('xml_name' => $file));
          if($query->result()) {
            return 1;
          } else {
            return 0;
          }
        }

        public function truncate_table($table) {
          $this->db->from($table);
          $this->db->truncate();
        }

        public function get_qty_cw($sku,$entity_id) {
          $this->db->select('qty_cw');
          $this->db->from('products');
          $this->db->where('sku',$sku);
          $this->db->where('magento_entity_id',$entity_id);
          $query = $this->db->get();
          return $query->result()[0]->qty_cw;
        }

        public function api_update_qty_by_xml($shop) {
          //aktualizacja stanów magazynowych w magento na podstawie zmian pomiędzy qty_xml a qty_vats
          $this->load->model('shop');

          $get_product_with_different_qty = $this->get_product_with_different_qty($shop);

          foreach($get_product_with_different_qty as $_get_product_with_different_qty) {
            $sku = $_get_product_with_different_qty->sku;
            $entity_id = (int)$_get_product_with_different_qty->magento_entity_id;
            $qty = (int)$_get_product_with_different_qty->qty_xml;
            $carrier_id = $_get_product_with_different_qty->carrier_id;

            if($carrier_id==null){
              echo $sku.' brak carrier_id';
              exit;
            }
            if($qty==null){
              $qty = 0;
            }
            if($qty==0) {
              $qty=(int)$this->get_qty_cw($sku,$entity_id);
                if($qty>0) {
                  /*
                  Wyłączone bo spamowało skrzynke - działa

                  $this->load->library("email", $this->email_config);
                  $reason = 'Produktu o SKU: '.$sku.' nie ma w hurtowni natomiast powinniśmy mieć go u siebie '. $qty .' sztuki. Do weryfikacji bo jak się sprzeda a nie mamy to będzie źle.';
                  $this->email->from($this->emailAdress, $this->emailName);
                  $this->email->to('biuro@neptron.pl');
                  $this->email->subject('Alarm! Do weryfikacji stany magazynowe');
                  $this->email->message($reason);
                  $this->email->send();
                  */
              }
            }

            //echo $entity_id .' - '. $qty .' - <br/>';
            $this->shop->update_qty_by_entity($entity_id, $qty, $shop);
            $this->update_products_magento_qty($sku,$entity_id,$carrier_id,$shop,$qty);
          }
        }
}
?>
