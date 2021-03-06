<?php

class AccountModel extends BaseModel{

    public function login($email, $pwd, $mode='password', $token=''){
        if(empty($pwd))return false;
        if($mode == 'password'){
            $result = $this->where(array('email' => $email, 'password' => md5($pwd)))->find();
        }
        else if($mode == 'api'){
            $api_type = $email;
            $result = $this->where(array('api_'.$api_type.'_id' => $pwd))->find();
            if(!$result){
                // create an empty user
                // for wechat, try get user name from openapi
                $result = array(
                        'api_'.$api_type.'_id' => $pwd,
                        'api_'.$api_type.'_token' => $token
                    );
                if($api_type == 'wechat') {
                    $request_uri = "https://api.weixin.qq.com/sns/userinfo?access_token=$token&openid=$pwd&lang=zh_CN";
                    $api_userinfo = json_decode(file_get_contents($request_uri), true);
                    $result['name'] = $api_userinfo['nickname'];
                }
                $result['id'] = O('account')->add($result);
                $result['is_verified'] = 0;
                $_SESSION['login_user'] = $result;
                return true;
            }
            else{
                if(empty($result['name'])) {
                  $request_uri = "https://api.weixin.qq.com/sns/userinfo?access_token=$token&openid=$pwd&lang=zh_CN";
                  $api_userinfo = json_decode(file_get_contents($request_uri), true);
                  $result['name'] = $api_userinfo['nickname'];
                }
                $result['api_'.$api_type.'_token'] = $token;
                $this->save($result);
            }

        }

        // Login failed
        if(!$result || empty($result)){
            return false;
        }
        elseif($result['enabled'] == 0){
            return false;
        }
        else{
            //login successfully
            //fetch other user information
            $user_model = new UserModel();
            if($mode == 'api' && !empty($result['user_id'])) {
              $user_data = $user_model->find($result['user_id']);
            }
            else{
              $user_data = $user_model->where(array('account_id'=>$result['id']))->find();
            }
            if($user_data){
                $user_data['local_maps'] = O('local_map')->with('admin_id', $user_data['id'])->select();
                $_SESSION['login_user'] = array_merge($user_data, $result);
                $_SESSION['login_user']['id'] = $user_data['id'];
                $_SESSION['login_user']['user_id'] = $user_data['id'];
                $_SESSION['login_user']['name'] = $user_data['name'];
                if($user_data['is_checked']) {
                  $_SESSION['login_user']['is_verified'] = 1;
                }
                $this->query("update user set login_count=login_count+1 where id=$user_id");
            }
            else{
                $_SESSION['login_user'] = $result;
                $_SESSION['login_user']['id'] = 0;
            }
            // if password is blank, leave it as blank.
            if($_SESSION['login_user']['password'] != 'd41d8cd98f00b204e9800998ecf8427e'){
                $_SESSION['login_user']['password'] = '******';
            }
            else{
                $_SESSION['login_user']['password'] = '';
            }
            $_SESSION['login_user']['account_id'] = $result['id'];
            $_SESSION['login_user']['account_name'] = $result['name'];

            $this->where(array('id'=>$result['id']))->data(array('last_login'=>date('Y-m-d h:i:s')))->save();
            return true;
        }
    }

    public function add_user($post){
        if(!isset($post['email'])){
            return "电子邮件必填";
        }
        else if(!isset($post['name'])){
            return '姓名必填';
        }
        else if(!isset($post['password'])){
            return '密码必填';
        }
        $account_count = $this->where(array('email'=>$post['email']))->count();
        if($account_count > 1){
            return '该电子邮件已经被注册，请换一个电子邮件或者直接登录';
        }
        $id = $this->add(array(
                'name' => $post['name'],
                'password' => md5($post['password']),
                'email' => $post['email']
            ));
        $this->login($post['email'], $post['password']);
        return $id;
    }
}
