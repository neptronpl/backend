<?php
class Allegro extends CI_Model {


        function __construct() {
          $this->productsTbl = 'products';
          $this->allegroTbl = 'products_allegro';
          $this->allegroTempTbl = 'allegro_temp';
          $this->synchronizationProductsTbl = 'products_synchronization';
          $this->load->model('cron');
          $this->token_0 = $this->cron->get_session_token('token_0');
          $this->code_0 = $this->cron->get_session_token('code_0');
        }

        public function gen_uuid() {

          return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
              // 32 bits for "time_low"
              mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),

              // 16 bits for "time_mid"
              mt_rand( 0, 0xffff ),

              // 16 bits for "time_hi_and_version",
              // four most significant bits holds version number 4
              mt_rand( 0, 0x0fff ) | 0x4000,

              // 16 bits, 8 bits for "clk_seq_hi_res",
              // 8 bits for "clk_seq_low",
              // two most significant bits holds zero and one for variant DCE1.1
              mt_rand( 0, 0x3fff ) | 0x8000,

              // 48 bits for "node"
              mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
          );

        }

        public function get_obs_auctions()
        {
          //pobieranie synchronizowanych aukcji od nowych linijek
          $this->db->select('sku, name, allegro_id, allegro_status, last_update');
          $this->db->from($this->allegroTbl);
          $query = $this->db->get();
          return $query->result();
        }


        public function get_not_obs_auctions()
        {
          //pobieranie synchronizowanych aukcji od nowych linijek
          $this->db->select('name, allegro_id, last_update');
          $this->db->from($this->allegroTempTbl);
          $this->db->where(array('status' => 'ACTIVE'));
          $this->db->where('allegro_id not in (select allegro_id from products_allegro)');
          $query = $this->db->get();
          return $query->result();
        }

        public function get_obs_auctions_group_concat()
        {
          //pobieranie synchronizowanych aukcji z grupowaniem
          $this->db->select('sku, name, group_concat(allegro_id) as allegro_id, sum(allegro_qty) as allegro_qty, allegro_status');
          $this->db->from($this->allegroTbl);
          $this->db->group_by('sku');
          $query = $this->db->get();
          return $query->result();
        }

        public function get_synchronization_info()
        {
          //pobranie daty ostatniej synchronizacji
          $this->db->select('last_synchronization_date, count_auctions');
          $this->db->from($this->synchronizationProductsTbl);
          $this->db->where(array('name' => 'Allegro'));
          $query = $this->db->get();
          $result = $query->result();
          return $result[0];
        }

        public function allegroAddToSynchro($data = array()) {
          //dodawanie do tablicy z synchronizacją
          $insert = $this->db->insert($this->synchronizationProductsTbl, $data);
        }

        public function updateAllegroTemp($auctionActiveCount) {
        //pobranie wszystkich aukcji i zapisanie w bazie allegro_temp, wszystko w paczkach po 40 szt
        echo '<br/><br/><br/><br/><br/><br/><pre>';
          $page_limit = 40;
          $limit = $auctionActiveCount/$page_limit;
          for($i=0;$i<=$limit;$i++) {
            //pobieranie wszystkich aukcji
            $offset = $page_limit*$i;
            $result_plain = $this->executeApi('https://api.allegro.pl/sale/offers?limit='.$page_limit.'&offset='.$offset);
            $result_json_decode = json_decode($result_plain);
            //print_r($result_json_decode->offers);
            //exit;
            foreach($result_json_decode->offers as $_offers) {
              $allegro_id = $_offers->id;
              $name = $_offers->name;
              $status = $_offers->publication->status;
              $price_gross = $_offers->sellingMode->price->amount;
              $price_net = $price_gross/1.23;
              $qty = $_offers->stock->available;
              $sold = $_offers->stock->sold;
              $this->update_data_by_allegro_id($allegro_id, $name, $status, $price_gross, $price_net, $qty, $sold);
            }
          }
        echo '<br/><br/><br/><br/><br/><br/></pre>';
        }

        public function allegroSynchro() {
          //synchronizacja

          //pobranie 'obserwowanych' aukcji
          $getObsAuctions = $this->get_obs_auctions();
          foreach($getObsAuctions as $_getObsAuctions) {
            $sku = $_getObsAuctions->sku;
            $allegro_id = $_getObsAuctions->allegro_id;
            $qty = $this->get_qty_by_id($allegro_id);
            $status = $this->get_status_by_id($allegro_id);

            //aktualizacja wpisów o aukcji - products_allegro
            $this->update_qty_by_id($allegro_id, $sku, $qty);
            $this->update_status_by_id($allegro_id, $sku, $status);
          }

          //aktualizacja wpisów o ostatniej aktualizacji
          $auction_list_active = json_decode($this->get_sale_offers());
          $auctionActiveCount = $auction_list_active->count;


          //aktualizacja tablicy allegro_temp gdzie są pobrane wszystkie aktywne aukcje
          $auction_list = json_decode($this->get_sale_all_offers());
          $auctionCount = $auction_list->count;
          $this->updateAllegroTemp($auctionCount);

          $this->db->where('name', 'allegro');
          $this->db->update($this->synchronizationProductsTbl, array('count_auctions' => $auctionActiveCount, 'last_synchronization_date' => date('Y-m-d H:i:s')));
        }

        public function executeApi($url) {
          //GET
          $ch = curl_init();
          $headr = array();
            $headr[] = 'Authorization: Bearer '.$this->token_0;
            $headr[] = 'Content-Type: application/vnd.allegro.public.v1+json';
            $headr[] = 'Accept: application/vnd.allegro.public.v1+json';
          curl_setopt($ch, CURLOPT_HTTPHEADER,$headr);
          curl_setopt($ch, CURLOPT_URL,$url);
          curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
          $result = curl_exec($ch);
          curl_close($ch);

          if(isset(json_decode($result)->error)) {
            $email_config = Array(
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
            $this->load->library("email", $email_config);

            print_r($result);
              $name = 'Stróż Backendu';
              $email = 'awizo@vats.pl';
              $this->email->from($email, $name);
              $this->email->to('biuro@neptron.pl');
              $this->email->subject('Synchro Allegro nie działa');
              $this->email->message($result);
              $this->email->send();
            exit;
          } else {
            return $result;
          }
        }

        public function executeApiPut($url, $data) {
          //PUT

          $ch = curl_init();
          $headr = array();
            $headr[] = 'Authorization: Bearer '.$this->token_0;
            $headr[] = 'Content-Type: application/vnd.allegro.public.v1+json';
            $headr[] = 'accept: application/vnd.allegro.public.v1+json';
          curl_setopt($ch, CURLOPT_HTTPHEADER,$headr);
          curl_setopt($ch, CURLOPT_URL,$url);
          curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
          //curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
          curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
          $result = curl_exec($ch);
          curl_close($ch);
echo $url.'<br/>';
echo $data;
echo '<br/>';
echo '<br/>';
echo $result;

          return $result;

        }

        public function get_sale_offers() {
        //tylko aktywne aukcje
          $result = $this->executeApi('https://api.allegro.pl/sale/offers?limit=5&publication.status=ACTIVE');
          return $result;
        }

        public function get_sale_all_offers() {
        //wszystkie aukcje
          $result = $this->executeApi('https://api.allegro.pl/sale/offers?limit=5');
          return $result;
        }

        public function get_qty_by_id($allegro_id) {
          $result_plain = $this->executeApi('https://api.allegro.pl/sale/offers/'.$allegro_id);
          $result_json_decode = json_decode($result_plain);
          return $result_json_decode->stock->available;
        }

        public function get_status_by_id($allegro_id) {
          $result_plain = $this->executeApi('https://api.allegro.pl/sale/offers/'.$allegro_id);
          $result_json_decode = json_decode($result_plain);
          return $result_json_decode->publication->status;
        }

        public function put_sale_offer_quantity_change_commands($id, $qty) {
        //aktualizacja liczby przedmiotów dla aukcji
          if($qty>0) {
            $data = '{
                      "modification":{
                      "changeType":"FIXED",
                      "value":"'.$qty.'"
                     },
                     "offerCriteria":[
                       {
                         "offers":[
                           {
                             "id":"'.$id.'"
                           }
                         ],
                         "type":"CONTAINS_OFFERS"
                       }
                      ]
                     }';
            $result = $this->executeApiPut('https://api.allegro.pl/sale/offer-quantity-change-commands/'.$this->gen_uuid(), $data);
            return $result;
          } else if($qty==0){
            //jesli qty = 0 to zakoncz aukcje
            $data = '
              {
                "publication": {
                    "action": "END"
                },
                "offerCriteria": [
                    {
                        "offers":[

                            {
                                "id": "'. $id .'"
                            }
                        ],
                        "type": "CONTAINS_OFFERS"

                    }
                ]
              }
                    ';
            $result = $this->executeApiPut('https://api.allegro.pl/sale/offer-publication-commands/'.$this->gen_uuid(), $data);
            return $result;
          }
        }

        public function check_allegro_id_in_allegro_temp($allegro_id) {
        //czy ta aukcja jest już zaimportowana do allegro_temp?
          $check_exist = $this->db->select('id')->from($this->allegroTempTbl)->where('allegro_id',$allegro_id)->get();
          if($check_exist->num_rows() > 0) {
            return 1;
          } else {
            return 0;
          }
        }

        public function check_allegro_id_in_products_allegro($allegro_id) {
        //czy ta aukcja jest dodana do products_allegro?
          $check_exist = $this->db->select('id')->from($this->allegroTbl)->where('allegro_id',$allegro_id)->get();
          if($check_exist->num_rows() > 0) {
            return 1;
          } else {
            return 0;
          }
        }

        public function update_status_by_id($allegro_id, $sku, $status) {
        //aktualizacja w products_allegro informacji o statusie aukcji
          $this->db->where('allegro_id', $allegro_id);
          $this->db->where('sku', $sku);
          $this->db->update($this->allegroTbl, array('allegro_status' => $status));
        }

        public function update_qty_by_id($allegro_id, $sku, $qty) {
          $this->db->where('allegro_id', $allegro_id);
          $this->db->where('sku', $sku);
          $this->db->update($this->allegroTbl, array('allegro_qty' => $qty));
        }


        public function update_data_by_allegro_id($allegro_id, $name, $status, $price_gross, $price_net, $qty, $sold) {
          $data['allegro_id'] = $allegro_id;
          $data['name'] = $name;
          $data['observed'] = $this->check_allegro_id_in_products_allegro($allegro_id);
          $data['status'] = $status;
          $data['price_gross'] = $price_gross;
          $data['price_net'] = $price_net;
          $data['qty'] = $qty;
          $data['sold'] = $sold;

          if($this->check_allegro_id_in_allegro_temp($allegro_id)==0) {
          //jesli nie ma dodanego tego allegro_id to dodaj nowy wpis
          $insert = $this->db->insert($this->allegroTempTbl, $data);
          }
          else {
          //jesli jest juz dodane to allegro_id to zaaktualizuj wpis
            $this->db->where('allegro_id', $allegro_id);
            $this->db->update($this->allegroTempTbl, $data);
          }
        }

        public function refresh_token()
        {
          //POST
          $basepass = base64_encode('000:000');
          $ch = curl_init();
          $headr = array();
            $headr[] = 'Authorization: Basic '. $basepass;
            $headr[] = 'Accept: application/vnd.allegro.public.v1+json';
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER,$headr);
            curl_setopt($ch, CURLOPT_URL,'https://allegro.pl/auth/oauth/token?grant_type=refresh_token&refresh_token='.$this->code_0.'&redirect_uri=http://strona_backendu/public/allegro/apiResponse');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
            $result = curl_exec($ch);
            $result_decode = json_decode($result);
            curl_close($ch);
            $this->cron->update_session_token('code_0',$result_decode->refresh_token);
            $this->cron->update_session_token('token_0',$result_decode->access_token);

        }

        public function api_update_qty_from_xml_to_allegro() {
          //automayczne synchro stocków z xml do allegro na podstawie różnicy qty_allegro i sumy qty_cw + qty_xml
          //pobranie aukcji do updateu qty w allegro
          $this->db->select('allegro_id, qty_allegro, qty_xml, qty_cw');
          $this->db->from($this->productsTbl);
          $this->db->where('allegro_id > 1 and qty_allegro > (qty_xml+qty_cw)');
          $query = $this->db->get();
          echo '<pre>';


          foreach($query->result() as $result) {
            $allegro_id = $result->allegro_id;
            $qty = $result->qty_xml+$result->qty_cw;
            $id = $result->allegro_id;
            $qty_actual = $result->qty_allegro;

            if(strrpos($allegro_id,',')) {
              $allegro_id_array = explode(',',$allegro_id);
              foreach($allegro_id_array as $array) {
                echo '<br/>'.$array .': '. $qty.' - '. $qty_actual .'<br/>';
                $this->put_sale_offer_quantity_change_commands($array, $qty);
              }
              continue;
            }

              echo '<br/>'.$id .': '. $qty.' - '. $qty_actual .'<br/>';
              //echo $result->allegro_id.' | '. $result->qty_xml .'+'. $result->qty_cw .' | '. $result->qty_allegro .'<br/>';
              //aktualizacja qty w allegro
              $this->put_sale_offer_quantity_change_commands($id, $qty);

              //aktualizacja allegro_qty w products
          }
          $this->allegro->allegroSynchro();
        }
}
?>
