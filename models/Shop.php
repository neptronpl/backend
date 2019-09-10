<?php
class Shop extends CI_Model {


        function __construct() {
          $this->productsTbl = 'products';
          $this->vatsTbl = 'products_shops';
          $this->magentoTempTbl = 'magento_temp';
          $this->synchronizationProductsTbl = 'products_synchronization';
          $this->shops = array('shops_array' => array(
                            1 => array(
                              'id' => '1',
                              'name' => 'nazwa_sklepu',
                              'verifySessionNameVats' => 'nazwa_sklepuSession',
                              'apiUrl' => 'https://adres_sklepu/api/rest',
                              'adminUrl' => 'https://adres_sklepu/admin',
                              'consumerKey' => '0000',
                              'consumerSecret' => '0000'
                          )),
          );

          $this->load->model('cron');
          $this->token_1 = $this->cron->get_session_token('token_1');
          $this->secret_1 = $this->cron->get_session_token('secret_1');
        }

        public function shops() {
        //aktualne xmle
            return $this->shops;
        }


        public function runApi($url, $data, $type, $shop) {
          switch($shop) {
            case 1:
              if(!isset($this->token_1)) {
                echo '<strong>Brak tokenu autoryzacji! Zaloguj do API!</strong><br/>';
              }
              $apiUrl = $this->shops['shops_array'][1]['apiUrl'];
              $token = $this->token_1;
              $secret = $this->secret_1;
            break;
          }

          $oauthClient = new OAuth($this->shops['shops_array'][$shop]['consumerKey'], $this->shops['shops_array'][$shop]['consumerSecret'], OAUTH_SIG_METHOD_HMACSHA1, OAUTH_AUTH_TYPE_AUTHORIZATION);
          $oauthClient->setToken($token, $secret);
          $oauthClient->fetch($apiUrl.'/'.$url, $data, $type, array('Content-Type' => 'application/json', 'Accept' => 'application/json'));
          $response = json_decode($oauthClient->getLastResponse());
          return $response;
        }


        public function apiLogin($shop, $get_oauth_token=null, $get_oauth_verifier=null) {
          $callbackUrl = base_url().'index.php/shops/apiLogin/'.$shop;

          switch($shop) {
            case 1:
            $accessTokenRequestUrl = 'https://nazwa_sklepu/oauth/token';
            $initiateurl = "https://nazwa_sklepu/oauth/initiate?oauth_callback=" . urlencode($callbackUrl);
            break;
          }

          //zamienić ify po skonczeniu i wylogowaniu
          if(!$_SESSION['verifySessionNameVats']) {
          //if(isset($_SESSION['verifySessionNameVats'])) {
            $_SESSION['verifySessionNameVats'] = $this->shops['shops_array'][$shop]['verifySessionNameVats'];
            $oauthClient = new OAuth($this->shops['shops_array'][$shop]['consumerKey'], $this->shops['shops_array'][$shop]['consumerSecret'], OAUTH_SIG_METHOD_HMACSHA1, OAUTH_AUTH_TYPE_URI);
            $requestToken = $oauthClient->getRequestToken($initiateurl);
          }
            if(!isset($_SESSION['oauth_token_'.$shop]) || !isset($_SESSION['oauth_token_secret_'.$shop])) {
              $_SESSION['oauth_token_'.$shop] = $requestToken['oauth_token'];
              $_SESSION['oauth_token_secret_'.$shop] = $requestToken['oauth_token_secret'];
              header('Location: '. $this->shops['shops_array'][$shop]['adminUrl'] .'oauth_authorize?oauth_token='.$_SESSION['oauth_token_'.$shop]);
              exit;
            }
            else if(isset($get_oauth_token) && isset($_SESSION['oauth_token_secret_'.$shop]) && isset($_SESSION['oauth_token_'.$shop]) && !isset($_SESSION['oauth_verifier_'.$shop])) {
              $oauthClient = new OAuth($this->shops['shops_array'][$shop]['consumerKey'], $this->shops['shops_array'][$shop]['consumerSecret'], OAUTH_SIG_METHOD_HMACSHA1, OAUTH_AUTH_TYPE_AUTHORIZATION);
              $oauthClient->setToken($get_oauth_token, $_SESSION['oauth_token_secret_'.$shop]);
              $accessToken = $oauthClient->getAccessToken($accessTokenRequestUrl);
              $this->load->model('cron');

              $_SESSION['token_'.$shop] = $accessToken['oauth_token'];
              $this->cron->update_session_token('token_'.$shop,$accessToken['oauth_token']);

              $_SESSION['secret_'.$shop] = $accessToken['oauth_token_secret'];
              $this->cron->update_session_token('secret_'.$shop,$accessToken['oauth_token_secret']);

              header('Location: ' . $callbackUrl);
              exit;
            }

            return $callbackUrl;


        }

        public function getQtyByEntityId($shop, $entity_id)
        //pobieranie qty na podstawie entity_id z danego sklepu
        {
          $query = $this->runApi('stockitems?filter[1][attribute]=product_id&filter[1][eq]='.$entity_id, array(), 'GET',$shop);
          $qty = $query[0]->qty;
          return $qty;
        }


        public function get_group_prices($cust_group) {
          $group_prices = array(
            0 => 'DETAL',
            1 => 'DETAL',
            2 => 'ALLEGRO',
            3 => 'VATS 1',
            4 => 'VATS 2',
            5 => 'VATS 3',
            6 => 'VATS 4'
          );
          return $group_prices[$cust_group];
        }


        public function get_group_prices_class($cust_group) {
          switch($cust_group) {
            case 0:
            case 1:
              $class = "label-success";
            break;
            case 2:
              $class = "label-warning";
            break;
            case 3:
            case 4:
            case 5:
            case 6:
              $class = "label-info";
            break;
            default:
              $class = "label-danger";
            break;
          }
          return $class;
        }


        public function get_products_magento_count($shop) {
          $this->db->select('count(*) as count');
          $this->db->from($this->productsTbl);
          $this->db->where(array('magento_entity_id > 0'));
          $token = $this->db->get();
          return $token->row()->count;
        }


        public function get_qty_from_magento($shop) {
          $products_count = $this->get_products_magento_count($shop);
          $page_limit = 65;
          $limit = ($products_count/$page_limit)+1;
          for($i=1;$i<=$limit;$i++) {
            $result = $this->runApi('stockitems?limit='.$page_limit.'&page='.$i, array(), 'GET',$shop);
            //pobieranie wszystkich aukcji
            foreach($result as $_result) {
              $this->insert_values_magento_temp($_result->product_id, $_result->qty, 'qty');
            }
          }
        }


        public function get_synchronization_info()
        {
          //pobranie daty ostatniej synchronizacji
          $this->db->select('last_synchronization_date, count_auctions');
          $this->db->from($this->synchronizationProductsTbl);
          $this->db->where(array('name' => 'Shops'));
          $query = $this->db->get();
          $result = $query->result();
          return $result[0];
        }

        public function get_value_from_temp($entity_id, $type) {
        //pobranie konkretnego entity_id z tablicy magento_temp
          $this->db->select('value');
          $this->db->from($this->magentoTempTbl);
          $this->db->where(array('entity_id' => $entity_id, 'type' => $type));
          $query = $this->db->get();
          $result = $query->result();
          if(isset($result[0])) {
            return $result[0]->value;
          }
           else {
             return 0;
           }
        }

        public function get_count_magento_temp() {
        //pobranie pozostałości z tablicy magento_temp. Jeśli coś tam zostaje tzn. że gdzies synchronizacja poszła źle
          $this->db->select('count(value) as count');
          $this->db->from($this->magentoTempTbl);
          $query = $this->db->get();
          $result = $query->result();
          return $result[0]->count;
        }

        public function get_magento_temp() {
          $this->db->select('entity_id, value, type');
          $this->db->from($this->magentoTempTbl);
          $query = $this->db->get();
          return $query->result();
        }


        public function get_product_by_entity_id($shop, $entity_id) {
          //pobranie danych o produkcie na podstawie entity_id
          //**
          //przez filtr mamy mniej danych np. brak informacji o group_price dlatego zmieniłem na products/:id
          //**
          //$query = $this->runApi('products?filter[1][attribute]=entity_id&filter[1][eq]='.$entity_id, array(), 'GET',$shop);
          $query = $this->runApi('products/'.$entity_id, array(), 'GET',$shop);
          return $query;
        }


        public function get_sku_by_entity_id($shop, $entity_id) {
          $query = $this->runApi('products?filter[1][attribute]=entity_id&filter[1][eq]='.$entity_id, array(), 'GET',$shop);
          $sku = $query->$entity_id->sku;
          return $sku;
        }

        public function update_qty_by_entity($entity_id, $qty, $shop) {
        //aktualizacja qty w magento na podstawie entity_id
          if($qty!=0) {
            $dataput = '{
              "stock_data" : { "qty": '.$qty.', "is_in_stock": 1 }
            }';
          } else {
            $dataput = '{
              "stock_data" : { "qty": '.$qty.', "is_in_stock": 0 }
            }';
          }

          echo $entity_id .' '. $qty;
          try{
            $this->runApi('products/'.$entity_id, $dataput, 'PUT',$shop);
          } catch (OAuthException $e) {
            print_r($e->getMessage());
            echo "<br/>";
            print_r($e->lastResponse);
          }


          echo ' ok<br/>';
        }

        public function update_price_by_entity($entity_id, $price, $shop) {
        //aktualizacja ceny w magento na podstawie entity_id
          if($price!=0) {
            $dataput = '{
              "price" : "'.$price.'",
              "group_price":
                [
                  {
                    "website_id": "0",
                    "cust_group": "0",
                    "price": "'.$price.'"
                  },
                  {
                    "website_id": "0",
                    "cust_group": "1",
                    "price": "'.$price.'"
                  },
                  {
                    "website_id": "0",
                    "cust_group": "2",
                    "price": "'.$price.'"
                  },
                  {
                    "website_id": "0",
                    "cust_group": "3",
                    "price": "'.$price.'"
                  }
                  ,
                  {
                    "website_id": "0",
                    "cust_group": "4",
                    "price": "'.$price.'"
                  }
                  ,
                  {
                    "website_id": "0",
                    "cust_group": "5",
                    "price": "'.$price.'"
                  }
                  ,
                  {
                    "website_id": "0",
                    "cust_group": "6",
                    "price": "'.$price.'"
                  }
              ]
            }';
          } else {
            $dataput = '{
              "stock_data" : {"is_in_stock": 0 }
            }';
          }
          echo $entity_id .' '. $price;
          $this->runApi('products/'.$entity_id, $dataput, 'PUT',$shop);
          echo ' ok<br/>';
        }

        public function insert_values_magento_temp($entity_id, $values, $type) {
          $data['entity_id'] = $entity_id;
          $data['value'] = $values;
          $data['type'] = $type;
          $this->db->insert($this->magentoTempTbl, $data);
        }


        public function delete_value_from_temp($entity_id, $type) {
          $this->db->where(array('entity_id' => $entity_id, 'type' => $type));
          $this->db->delete($this->magentoTempTbl);
        }

        public function restore_kod_producenta() {
        //przypadkowo wywalilem attribute kod producenta i ta funkcja przesylam z powrotem kod producenta z products do magento
        $this->load->model('product');
        $products = $this->product->get_products();
        $shop = 1;
        foreach($products as $_products) {

          $entity_id = $_products->magento_entity_id;
          $kod_producenta = $_products->kod_producenta;

            $dataput = '{
              "kod_producenta": "'.$kod_producenta.'"
            }';
            //if($entity_id==2686) { continue; }
          echo $entity_id .' '. $kod_producenta;
          $this->runApi('products/'.$entity_id, $dataput, 'PUT',$shop);
          echo ' ok<br/>';


        }


      }
}
?>
