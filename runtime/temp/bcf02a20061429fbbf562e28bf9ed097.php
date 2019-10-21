<?php if (!defined('THINK_PATH')) exit(); /*a:4:{s:60:"U:\house\public/../application/index\view\user\register.html";i:1571629353;s:51:"U:\house\application\index\view\layout\default.html";i:1571627654;s:48:"U:\house\application\index\view\common\meta.html";i:1562338655;s:50:"U:\house\application\index\view\common\script.html";i:1562338655;}*/ ?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
<title><?php echo (isset($title) && ($title !== '')?$title:''); ?> – <?php echo __('The fastest framework based on ThinkPHP5 and Bootstrap'); ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
<meta name="renderer" content="webkit">

<?php if(isset($keywords)): ?>
<meta name="keywords" content="<?php echo $keywords; ?>">
<?php endif; if(isset($description)): ?>
<meta name="description" content="<?php echo $description; ?>">
<?php endif; ?>
<meta name="author" content="FastAdmin">

<link rel="shortcut icon" href="/assets/img/favicon.ico" />

<link href="/assets/css/frontend<?php echo \think\Config::get('app_debug')?'':'.min'; ?>.css?v=<?php echo \think\Config::get('site.version'); ?>" rel="stylesheet">

<!-- HTML5 shim, for IE6-8 support of HTML5 elements. All other JS at the end of file. -->
<!--[if lt IE 9]>
  <script src="/assets/js/html5shiv.js"></script>
  <script src="/assets/js/respond.min.js"></script>
<![endif]-->
<script type="text/javascript">
    var require = {
        config: <?php echo json_encode($config); ?>
    };
</script>
        <link href="/assets/css/user.css?v=<?php echo \think\Config::get('site.version'); ?>" rel="stylesheet">
		<style>
			body{background-color: transparent;}
		</style>
    </head>
	
    <body>


        <main class="content">
            <link rel="stylesheet" href="/assets/static/index/layuiadmin/layui/css/layui.css" media="all">
<!-- <link rel="stylesheet" href="/assets/static/index/layuiadmin/style/admin.css" media="all"> -->
<!-- <link rel="stylesheet" href="/assets/static/index/layuiadmin/style/login.css" media="all"> -->
<link href="/assets/static/index/login/css/reg.css" rel="stylesheet" type="text/css">
<div id="content-container" class="container">
<style>
	.input-group-addon{
		padding: 0;
		background-color: #fff;
		border: none;
	}
</style>
<div class="regbox"></div>
<div class="regmain">
<form name="form1" id="register-form" class="form-vertical" method="POST" action="">
	<input type="hidden" name="invite_user_id" value="0" />
	<input type="hidden" name="url" value="<?php echo $url; ?>" />
	<?php echo token(); ?>
	<div class="regtt">新用户注册</div>
	<div class="layadmin-user-login-box layadmin-user-login-body layui-form">
	  <div class="reginput">
		<input class="phone" id="mobile" type="text" name="mobile" data-rule="required;mobile" placeholder="请输入11位手机号码">
		<div class="codebox">
		  <input class="codeinput"  name="captcha" type="text" placeholder="请输你收到的验证码" data-rule="required;length(4);integer[+];remote(/api/validate/check_sms_correct.html, event=register, mobile:#mobile)">
		  <a class="send btn-captcha" href="javascript:;" id="LAY-user-getsmscode" id="LAY-user-getsmscode" data-url="<?php echo url('api/sms/send'); ?>" data-type="mobile" data-event="register">发送验证码 </a>
		</div>

		<input class="password" name="password" type="password" data-rule="required;password" placeholder="请输入密码">
		<!-- <input class="password2"  name="repass" type="password" lay-verify="required" placeholder="请再次输入密码"> -->
		<input class="company" name="username" type="text" data-rule="required" placeholder="请输入企业名称">

		<div class="codebox">
		  <input class="codeinput"  name="captcha1" type="text" data-rule="required;length(4)" placeholder="请输入验证码">
		  <span class="input-group-addon" href="javascript:;"><img src="<?php echo captcha_src(); ?>" width="140" height="36" onclick="this.src = '<?php echo captcha_src(); ?>?r=' + Math.random();"/> </span>
		</div>

		<button class="regbt"  type="submit">提交</button>
		<a class="login" href="<?php echo url('login'); ?>">已有账号？ 点此立即登录</a>
	  </div>
	</div>
</form>
</div>

    
</div>
        </main>

        

        <script src="/assets/js/require<?php echo \think\Config::get('app_debug')?'':'.min'; ?>.js" data-main="/assets/js/require-frontend<?php echo \think\Config::get('app_debug')?'':'.min'; ?>.js?v=<?php echo $site['version']; ?>"></script>

    </body>

</html>