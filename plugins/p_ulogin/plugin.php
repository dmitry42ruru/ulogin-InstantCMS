<?php

class p_ulogin extends cmsPlugin
{

    public function __construct()
    {

        parent::__construct();

        $this->info['plugin'] = 'p_ulogin';
        $this->info['title'] = '����������� Ulogin';
        $this->info['type'] = 'Auth';
        $this->info['description'] = '����������� � ������� ���������� �����';
        $this->info['author'] = 'cramen';
        $this->info['version'] = '0.2';

        $this->config['Providers'] = 'vkontakte,odnoklassniki,mailru,facebook';
        $this->config['Hidden'] = 'twitter,google,yandex,livejournal,openid';

        $this->events[] = 'ULOGIN_BUTTON';
        $this->events[] = 'ULOGIN_BUTTON_SMALL';
        $this->events[] = 'ULOGIN_AUTH';

    }

    public function install()
    {

        if (!ini_get('allow_url_fopen')) {
            $error = '<h4>������ ��������� �������</h4>';
            $error .= '<p>��� ������ ������� �� ����� �������� ��������� <b>allow_url_fopen</b> � php.ini ������ ���� ��������</p>';
            die($error);
        }

        $inDB = cmsDatabase::getInstance();

        if (!$inDB->isFieldExists('cms_users', 'ulogin_id')) {

            $inDB->query("ALTER TABLE `cms_users` ADD `ulogin_id` VARCHAR( 250 ) NULL, ADD INDEX ( `ulogin_id` )");

        }

        return parent::install();

    }

    public function upgrade()
    {

        return parent::upgrade();

    }

    public function execute($event, $item)
    {

        parent::execute();

        switch ($event) {
            case 'ULOGIN_BUTTON':
                $item = $this->showUloginButton();
                break;
            case 'ULOGIN_BUTTON_SMALL':
                $item['small'] = TRUE;
                $item['id'] = isset($item['id'])?$item['id']:'uLoginSmall';
                $item = $this->showUloginButton($item);
                break;
            case 'ULOGIN_AUTH':
                $item = $this->uloginAuth();
                break;
        }

        return true;

    }

    private function showUloginButton($item)
    {
        
        $small = (isset($item['small']) && $item['small'])?true:false;
        $id = isset($item['id'])?$item['id']:'uLogin';

        $token_url = urlencode(HOST . '/plugins/p_ulogin/auth.php');
        $providers = $this->config['Providers'];
        $hidden = $this->config['Hidden'];

        $html = '<div id="'.$id.'" x-ulogin-params="display='.($small?'small':'panel').
                '&fields=first_name,last_name,nickname,city,photo,photo_big,bdate,sex,email,network&providers='. $providers .
                '&hidden=' . $hidden . '&redirect_uri=' . $token_url . '"></div>';

        echo $html;

        return;

    }


    private function uloginAuth()
    {

        $inCore = cmsCore::getInstance();

        $token = $inCore->request('token', 'str', '');

        if (!$token) {
            exit;
        }

        $ulogin_token_url = 'http://ulogin.ru/token.php';
        $ulogin_token_url .= '?token=' . $token . '&host=' . $_SERVER['HTTP_HOST'];

        // ��������� �������
        $json_string = file_get_contents($ulogin_token_url);
        $profile = json_decode($json_string, true);

        foreach ($profile as $key => $el)
        {
            $profile[$key] = iconv('UTF-8', 'Windows-1251', $el);
        }

        if (empty($profile) || isset($profile['error'])) {
            exit;
        }

        $user_id = $this->getUserByIdentity($profile['identity']);

        if (!$user_id) {
            $user_id = $this->createUser($profile);
        }

        // ���� ������������ ��� ��� ��� ������� ������, ����������
        if ($user_id) {
            $this->loginUser($user_id);
        }

        // ���� ����������� �� �������, ���������� �� ��������� �� ������
        $inCore->redirect('/auth/error.html');
        exit;

    }

    private function loginUser($user_id)
    {

        $inCore = cmsCore::getInstance();
        $inDB = cmsDatabase::getInstance();

        if (isset($_SESSION['auth_back_url'])) {
            $back = $_SESSION['auth_back_url'];
            $is_sess_back = true;
            unset($_SESSION['auth_back_url']);
        } else {
            $is_sess_back = false;
        }

        $sql = "SELECT *
                   FROM cms_users
                   WHERE id = '{$user_id}'";

        $result = $inDB->query($sql);

        if ($inDB->num_rows($result) == 1) {

            $current_ip = $_SERVER['REMOTE_ADDR'];
            $user = $inDB->fetch_assoc($result);

            if (!cmsUser::isBanned($user['id'])) {

                $_SESSION['user'] = cmsUser::createUser($user);

                cmsCore::callEvent('USER_LOGIN', $_SESSION['user']);

            } else {
                $inDB->query("UPDATE cms_banlist SET ip = '$current_ip' WHERE user_id = " . $user['id'] . " AND status = 1");
            }

            $first_time_auth = ($user['logdate'] == '0000-00-00 00:00:00' || intval($user['logdate'] == 0));

            $inDB->query("UPDATE cms_users SET logdate = NOW(), last_ip = '$current_ip' WHERE id = " . $user['id']);

            $cfg = $inCore->loadComponentConfig('registration');

            if (!isset($cfg['auth_redirect'])) {
                $cfg['auth_redirect'] = 'index';
            }
            if (!isset($cfg['first_auth_redirect'])) {
                $cfg['first_auth_redirect'] = 'profile';
            }

            if (!$inCore->userIsAdmin($user['id']) && !$is_sess_back) {
                if ($first_time_auth) {
                    $cfg['auth_redirect'] = $cfg['first_auth_redirect'];
                }
                switch ($cfg['auth_redirect']) {
                    case 'none':
                        $url = $back;
                        break;
                    case 'index':
                        $url = '/';
                        break;
                    case 'profile':
                        $url = cmsUser::getProfileURL($user['login']);
                        break;
                    case 'editprofile':
                        $url = '/users/' . $user['id'] . '/editprofile.html';
                        break;
                }
            } else {
                $url = $back;
            }

            $inCore->redirect($url);
            exit;

        }

        return false;

    }

    private function createUser($profile)
    {

        $inCore = cmsCore::getInstance();
        $inDB = cmsDatabase::getInstance();

        $nickname = (isset($profile['nickname']) && $profile['nickname']) ? $profile['nickname'] : '';

        if (!$nickname) {
            if (isset($profile['first_name']) && $profile['first_name']) {
                $nickname = $profile['first_name'];
                if (isset($profile['last_name']) && $profile['last_name']) {
                    $nickname .= ' ' . $profile['last_name'];
                }
            }
        }

        if (!$nickname) {
            // �� ������� ������ ������
            $max = $inDB->get_fields('cms_users', 'id>0', 'id', 'id DESC');
            $nickname = 'user' . ($max['id'] + 1);
        }

        $login = $this->makeLogin($nickname);
        $pass_orig = substr(md5(session_id()), rand(0, 20), 8);
        $pass = md5($pass_orig);

        $city = $inDB->escape_string(isset($profile['city']) ? $profile['city'] : '');
        $email = $inDB->escape_string($profile['email']);

        $already = $inDB->get_fields('cms_users', "login='{$login}' AND is_deleted=0", 'login');
        $already_email = $inDB->get_field('cms_users', "email='{$email}' AND is_deleted=0", 'email');

        if ($already['login'] == $login) {
            $max = $inDB->get_fields('cms_users', 'id>0', 'id', 'id DESC');
            $login .= ($max['id'] + 1);
        }
        $login = $inDB->escape_string($login);
        
        if ($email && $already_email == $email){
            list($mailName,$mailDomain) = explode('@', $email);
            $email = $mailName.$profile['network'].'@'.$mailDomain;
        }
        

        /**
         *  ���� �������� 
         */
        if (isset($profile['bdate'])) 
        {
            list($day,$month,$year) = explode('.', $profile['bdate']);
            $birthdate = $year.'-'.$month.'-'.$day;
        }
        else
        {
            $birthdate = '';
        }
        $birthdate = $inDB->escape_string($birthdate);


        /**
         * ��� 
         */
        if (isset($profile['sex']) && $profile['sex']) 
        {
            $sex = $profile['sex']==2?'m':'f';
        }
        else
        {
            $sex = '';
        }
        
        
        $user_array = array(
            'login' => $login,
            'nickname' => $nickname
        );

        $sql = "INSERT INTO cms_users (login, nickname, password, regdate, ulogin_id, birthdate, email)
                VALUES ('$login', '$nickname', '$pass', NOW(), '" . $inDB->escape_string($profile["identity"]) . 
                "' , '".$birthdate."', '".$email."')";

        $inDB->query($sql);

        $user_id = $inDB->get_last_id('cms_users');

        
        // ������� ������� ������������
        if ($user_id) {

            $sql = "INSERT INTO cms_user_profiles (user_id, city, description, showmail, showbirth, showicq, karma, allow_who, gender)
                    VALUES ('{$user_id}', '{$city}', '', '0', '0', '1', '0', 'all' ,'".$sex."')";
            $inDB->query($sql);

            $user_array['id'] = $user_id;
            cmsCore::callEvent('USER_REGISTER', $user_array);

            $this->getAvatar($profile,$user_id);

            $this->sendGreetsMessage($user_id, $login, $pass_orig);

            return $user_id;

        }



        return false;

    }


    private function getUserByIdentity($identity)
    {

        $inDB = cmsDatabase::getInstance();

        return $inDB->get_field('cms_users', "ulogin_id='{$identity}'", 'id');

    }


    private function makeLogin($string)
    {

        $string = trim($string);
        $string = mb_strtolower($string, 'cp1251');
        $string = preg_replace('/[^a-zA-Z�-��-�0-9\-]/i', '', $string);

        while (strstr($string, '--')) {
            $string = str_replace('--', '-', $string);
        }

        $ru_en = array(
            '�' => 'a', '�' => 'b', '�' => 'v', '�' => 'g', '�' => 'd',
            '�' => 'e', '�' => 'yo', '�' => 'zh', '�' => 'z',
            '�' => 'i', '�' => 'i', '�' => 'k', '�' => 'l', '�' => 'm',
            '�' => 'n', '�' => 'o', '�' => 'p', '�' => 'r', '�' => 's',
            '�' => 't', '�' => 'u', '�' => 'f', '�' => 'h', '�' => 'c',
            '�' => 'ch', '�' => 'sh', '�' => 'sh', '�' => '', '�' => 'y',
            '�' => '', '�' => 'ye', '�' => 'yu', '�' => 'ja'
        );

        foreach ($ru_en as $ru => $en) {
            $string = preg_replace('/([' . $ru . ']+)/i', $en, $string);
        }

        if (!$string) {
            $string = 'untitled';
        }

        return $string;

    }

    private function sendGreetsMessage($user_id, $login, $pass)
    {

        $msg = "<div>������������. ���������� �� ����������� �� ����� �����.</div>";
        $msg .= "<div>���� ��������� ��� ����� �� ����:</div>";
        $msg .= "<strong>�����:</strong> {$login}<br />";
        $msg .= "<strong>������:</strong> {$pass}<br />";

        $msg .= '<div>�� ������ ������� �������, ������ � email � <a href="/users/' . $user_id . '/editprofile.html">����������</a> ������ �������</div>';

        cmsUser::sendMessage(USER_MASSMAIL, $user_id, $msg);

        return true;

    }

    private function getAvatar($profile,$id)
    {
        $inCore = cmsCore::getInstance();
        $inDB = cmsDatabase::getInstance();

        $profile_photo = $profile['photo'];
        if (isset($profile['photo_big']) &&  $profile['photo_big']!='http://ulogin.ru/img/photo_big.png')
            $profile_photo = $profile['photo_big'];


        if (!$profile_photo) return;

        $cfg = $inCore->loadComponentConfig('users');

        $tmpName = rand(100000,10000000).'.'.strtolower(substr($profile_photo,-3));


        $inCore->includeGraphics();

        $filecontents = file_get_contents($profile_photo);
        $uploaddir = PATH . '/images/users/avatars/';
        file_put_contents($uploaddir.$tmpName,$filecontents);


        $realfile = $tmpName;


        $path_parts = pathinfo($realfile);
        $ext = strtolower($path_parts['extension']);
        $realfile = md5($realfile . '-' . time()) . '.' . $ext;

            $filename = md5($realfile . '-' . $id . '-' . time()) . '.jpg';
            $uploadfile = $uploaddir . rand(100000,10000000).'.'.$path_parts['extension'];
            $uploadavatar = $uploaddir . $filename;
            $uploadthumb = $uploaddir . 'small/' . $filename;
            $source = $uploaddir.$tmpName;

        if (copy($source,$uploadfile)) {

            $sql = "SELECT imageurl FROM cms_user_profiles WHERE user_id = '$id'";
            $result = $inDB->query($sql);
            if ($inDB->num_rows($result)) {
                $old = $inDB->fetch_assoc($result);
                if ($old['imageurl'] && $old['imageurl'] != 'nopic.jpg') {
                    @unlink(PATH . '/images/users/avatars/' . $old['imageurl']);
                    @unlink(PATH . '/images/users/avatars/small/' . $old['imageurl']);
                }
            }

            //CREATE THUMBNAIL
            if (isset($cfg['smallw'])) {
                $smallw = $cfg['smallw'];
            } else {
                $smallw = 64;
            }
            if (isset($cfg['medw'])) {
                $medw = $cfg['medw'];
            } else {
                $medw = 200;
            }
            if (isset($cfg['medh'])) {
                $medh = $cfg['medh'];
            } else {
                $medh = 200;
            }

            @img_resize($uploadfile, $uploadavatar, $medw, $medh);
            @img_resize($uploadfile, $uploadthumb, $smallw, $smallw);

            //DELETE ORIGINAL
            @unlink($uploadfile);

            //MODIFY PROFILE
            $sql = "UPDATE cms_user_profiles
					SET imageurl = '$filename'
					WHERE user_id = '$id'
					LIMIT 1";
            $inDB->query($sql);

        }

        @unlink($source);
    }

}
