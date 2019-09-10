<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * User Management class created by CodexWorld
 */
class Users extends CI_Controller {

    function __construct() {
        parent::__construct();

        $this->load->library('form_validation');
        $this->load->model('user');
    }


    public function account(){
        $data = array();
        if($this->session->userdata('isUserLoggedIn')){
          $sidebar_variables['menu_active'] = 'users';
          $this->load->view('template/page/head.phtml');
          $this->load->view('template/page/top.phtml');
          $this->load->view('template/page/menu.phtml',$sidebar_variables);
          $data['user'] = $this->user->getRows(array('id'=>$this->session->userdata('userId')));
          $this->load->view('template/users/account', $data);
          $this->load->view('template/page/footer.phtml');
        }else{
            redirect('users/login');
        }
    }


    public function login(){
      if(!$this->session->userdata('isUserLoggedIn')){
        $this->load->view('template/page/head.phtml');
        $this->load->view('template/page/top.phtml');
          $data = array();
          if($this->session->userdata('success_msg')){
              $data['success_msg'] = $this->session->userdata('success_msg');
              $this->session->unset_userdata('success_msg');
          }
          if($this->session->userdata('error_msg')){
              $data['error_msg'] = $this->session->userdata('error_msg');
              $this->session->unset_userdata('error_msg');
          }
          if($this->input->post('loginSubmit')){
              $this->form_validation->set_rules('email', 'Email', 'required|valid_email');
              $this->form_validation->set_rules('password', 'password', 'required');
              if ($this->form_validation->run() == true) {
                  $con['returnType'] = 'single';
                  $con['conditions'] = array(
                      'email'=>$this->input->post('email'),
                      'password' => md5($this->input->post('password')),
                      'status' => '1'
                  );
                  $checkLogin = $this->user->getRows($con);
                  if($checkLogin){
                      $this->session->set_userdata('isUserLoggedIn',TRUE);
                      $this->session->set_userdata('userId',$checkLogin['id']);
                      $this->session->set_userdata('firstname',$checkLogin['firstname']);
                      $this->session->set_userdata('lastname',$checkLogin['lastname']);
                      $this->session->set_userdata('email',$checkLogin['email']);
                      $this->session->set_userdata('group',$checkLogin['group']);
                      $this->session->set_userdata('level',$checkLogin['level']);
                      redirect('backend/index');
                  }else{
                      $data['error_msg'] = 'Błędny login lub hasło.';
                  }
              }
          }
          //load the view

        $this->load->view('template/users/login.phtml', $data);
        $this->load->view('template/page/footer.phtml');
      }
      else {
        redirect('backend/index');
      }
    }


    public function change_password($id){
      if($this->session->userdata('isUserLoggedIn')){
        if($this->session->userdata('isUserLoggedIn') && $this->session->userdata('group') == 'admin' && $this->session->userdata('level') == '1') {
        }
        else {
          redirect('backend/disallow');
        }
        $sidebar_variables['menu_active'] = 'admin_user_registration';
        $this->load->view('template/page/head.phtml');
        $this->load->view('template/page/top.phtml');
        $this->load->view('template/page/menu.phtml', $sidebar_variables);
        $data['id'] = $id;
        $userData = array();
        if($this->input->post('passchangeSubmit')){
            $this->form_validation->set_rules('password', 'password', 'required');

          }
          $userData = array(
              'password' => md5($this->input->post('password'))
            );

          if($this->form_validation->run() == true){

              $this->db->set($userData);
              $this->db->where('id', $id);
              $this->db->update('neptron_users');
            }
            $this->load->view('template/users/change_password.phtml', $data);
            $this->load->view('template/page/footer.phtml');
      }
    }


    public function registration(){
      if($this->session->userdata('isUserLoggedIn')){
        if($this->session->userdata('isUserLoggedIn') && $this->session->userdata('group') == 'admin' && $this->session->userdata('level') == '1') {
        }
        else {
          redirect('backend/disallow');
        }
        $sidebar_variables['menu_active'] = 'admin_user_registration';
        $this->load->view('template/page/head.phtml');
        $this->load->view('template/page/top.phtml');
        $this->load->view('template/page/menu.phtml', $sidebar_variables);
        $data = array();
        $userData = array();
        if($this->input->post('regisSubmit')){
            $this->form_validation->set_rules('firstname', 'Firstname', 'required');
            $this->form_validation->set_rules('lastname', 'Lastname', 'required');
            $this->form_validation->set_rules('email', 'Email', 'required|valid_email|callback_email_check');
            $this->form_validation->set_rules('password', 'password', 'required');
            $this->form_validation->set_rules('conf_password', 'confirm password', 'required|matches[password]');
            $this->form_validation->set_rules('group', 'Group', 'required');
            $this->form_validation->set_rules('level', 'Level', 'required');

            $userData = array(
                'firstname' => strip_tags($this->input->post('firstname')),
                'lastname' => strip_tags($this->input->post('lastname')),
                'email' => strip_tags($this->input->post('email')),
                'password' => md5($this->input->post('password')),
                'phone' => strip_tags($this->input->post('phone')),
                'group' => $this->input->post('group'),
                'level' => $this->input->post('level')
            );

            if($this->form_validation->run() == true){
                $insert = $this->user->insert($userData);
                if($insert){
                    $this->session->set_userdata('success_msg', 'Użytkownik dodany.');

                }else{
                    $data['error_msg'] = 'Wystąpił problem.';
                }
            }
        }
        $data['user'] = $userData;
        $data['registered_users'] = $this->user->getRows();
        $this->load->view('template/users/registration.phtml', $data);
        $this->load->view('template/page/footer.phtml');
      }
      else {
        redirect('users/login/');
      }
    }


    public function logout(){
        $this->session->unset_userdata('isUserLoggedIn');
        $this->session->unset_userdata('userId');
        $this->session->sess_destroy();
        redirect('users/login/');
    }


    public function email_check($str){
        $con['returnType'] = 'count';
        $con['conditions'] = array('email'=>$str);
        $checkEmail = $this->user->getRows($con);
        if($checkEmail > 0){
            $this->form_validation->set_message('email_check', 'The given email already exists.');
            return FALSE;
        } else {
            return TRUE;
        }
    }
}
