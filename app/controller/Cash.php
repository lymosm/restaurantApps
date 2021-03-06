<?php
/**
 * 金钱相关
 */
namespace app\controller;

use app\BaseController;
use think\facade\View;
use app\model\User;
use think\response\Json;
use think\facade\Db;
use think\facade\Request;
use think\facade\Config;
 
class Cash extends BaseController
{
	public function __construct(\think\App $app)
	{
		parent::__construct($app, false);
	}
   
	/**
	 * 访问：http://doucc.com/index.php?s=index/get_product
	 * 提现
	 */
	public function cashout(){
		if(! $this->verify_token()){
			return json(['code' => 0, 'data' => '', 'msg' => 'token is invaild']);
		}
		$data = Db::table('dcc_user')
			->field('invite_code, mobile, id')
			->where('id', 1)->find();
		$invite_data = $this->getUserInvite();
		$ret = [
			'code' => 1,
			'data' => $data,
			'msg' => ''
		];
		return json($ret);
	}
	
	/**
	 * 付款
	 */
	public function unionpay(){
		$merid = Config::get('app.unionpay_merid');
		
		$userid = intval($this->decrypt(str_replace(' ', '+', Request::param('userid'))));
		$order_str = str_replace(' ', '+', Request::param('orderid'));
		$orderid = $this->decrypt($order_str);
		// $orderid = 'dcc202105300002'; // debug
		// 查出订单信息
		$order = $this->_getOrder($userid, $orderid);
		if(! $order || ! isset($order['total'])){
			echo '订单不存在';
			exit;
		}
		$total = $order['total'];
		$product_name = $order['name'];
		$time = date('YmdHis');
		$amt = $total * 100; // 分
		$params = array(
				
				//以下信息非特殊情况不需要改动
				'version' => \com\unionpay\acp\sdk\SDKConfig::getSDKConfig()->version,                 //版本号
				'encoding' => 'utf-8',				  //编码方式
				'txnType' => '01',				      //交易类型
				'txnSubType' => '01',				  //交易子类
				'bizType' => '000201',				  //业务类型
				'frontUrl' =>  \com\unionpay\acp\sdk\SDKConfig::getSDKConfig()->frontUrl,  //前台通知地址
				'backUrl' => \com\unionpay\acp\sdk\SDKConfig::getSDKConfig()->backUrl,	  //后台通知地址
				'signMethod' => \com\unionpay\acp\sdk\SDKConfig::getSDKConfig()->signMethod,	              //签名方法
				'channelType' => '08',	              //渠道类型，07-PC，08-手机
				'accessType' => '0',		          //接入类型
				'currencyCode' => '156',	          //交易币种，境内商户固定156
				
				//TODO 以下信息需要填写
				'merId' => $merid,		//商户代码，请改自己的测试商户号，此处默认取demo演示页面传递的参数
				'orderId' => $orderid,	//商户订单号，8-32位数字字母，不能含“-”或“_”，此处默认取demo演示页面传递的参数，可以自行定制规则
				'txnTime' => $time,	//订单发送时间，格式为YYYYMMDDhhmmss，取北京时间，此处默认取demo演示页面传递的参数
				'txnAmt' => $amt,	//交易金额，单位分，此处默认取demo演示页面传递的参数
				
				// 订单超时时间。
				// 超过此时间后，除网银交易外，其他交易银联系统会拒绝受理，提示超时。 跳转银行网银交易如果超时后交易成功，会自动退款，大约5个工作日金额返还到持卡人账户。
				// 此时间建议取支付时的北京时间加15分钟。
				// 超过超时时间调查询接口应答origRespCode不是A6或者00的就可以判断为失败。
				'payTimeout' => date('YmdHis', strtotime('+15 minutes')), 
		
				'riskRateInfo' =>'{commodityName=' . $product_name . '}',
		
				// 请求方保留域，
				// 透传字段，查询、通知、对账文件中均会原样出现，如有需要请启用并修改自己希望透传的数据。
				// 出现部分特殊字符时可能影响解析，请按下面建议的方式填写：
				// 1. 如果能确定内容不会出现&={}[]"'等符号时，可以直接填写数据，建议的方法如下。
				//    'reqReserved' =>'透传信息1|透传信息2|透传信息3',
				// 2. 内容可能出现&={}[]"'符号时：
				// 1) 如果需要对账文件里能显示，可将字符替换成全角＆＝｛｝【】“‘字符（自己写代码，此处不演示）；
				// 2) 如果对账文件没有显示要求，可做一下base64（如下）。
				//    注意控制数据长度，实际传输的数据长度不能超过1024位。
				//    查询、通知等接口解析时使用base64_decode解base64后再对数据做后续解析。
				//    'reqReserved' => base64_encode('任意格式的信息都可以'),
				
				//TODO 其他特殊用法请查看 special_use_purchase.php
			);
		
		\com\unionpay\acp\sdk\AcpService::sign ( $params );
		$uri = \com\unionpay\acp\sdk\SDKConfig::getSDKConfig()->frontTransUrl;
		$html_form = \com\unionpay\acp\sdk\AcpService::createAutoFormHtml( $params, $uri );
		echo $html_form;
		exit;
		// error_log(print_r($html_form, true), 3, '/Volumes/mac-disk/work/www/debug.log');
	}
	
	/**
	 * 付款
	 */
	public function unionpayApp(){
		$merid = Config::get('app.unionpay_merid');
		
		$userid = intval($this->decrypt(str_replace(' ', '+', Request::param('userid'))));
		$order_str = str_replace(' ', '+', Request::param('orderid'));
		$orderid = $this->decrypt($order_str);
		// $orderid = 'dcc202105300002'; // debug
		// 查出订单信息
		$order = $this->_getOrder($userid, $orderid);
		if(! $order || ! isset($order['total'])){
			echo '订单不存在';
			exit;
		}
		$total = $order['total'];
		$product_name = $order['name'];
		$time = date('YmdHis');
		$amt = $total * 100; // 分
		$params = array(
				
				//以下信息非特殊情况不需要改动
				'version' => \com\unionpay\acp\sdk\SDKConfig::getSDKConfig()->version,                 //版本号
				'encoding' => 'utf-8',				  //编码方式
				'txnType' => '01',				      //交易类型
				'txnSubType' => '01',				  //交易子类
				'bizType' => '000201',				  //业务类型
				'frontUrl' =>  'http://121.37.3.176/index.php?s=Cash/frontReceiveApp',  //前台通知地址
				'backUrl' => \com\unionpay\acp\sdk\SDKConfig::getSDKConfig()->backUrl,	  //后台通知地址
				'signMethod' => \com\unionpay\acp\sdk\SDKConfig::getSDKConfig()->signMethod,	              //签名方法
				'channelType' => '08',	              //渠道类型，07-PC，08-手机
				'accessType' => '0',		          //接入类型
				'currencyCode' => '156',	          //交易币种，境内商户固定156
				
				//TODO 以下信息需要填写
				'merId' => $merid,		//商户代码，请改自己的测试商户号，此处默认取demo演示页面传递的参数
				'orderId' => $orderid,	//商户订单号，8-32位数字字母，不能含“-”或“_”，此处默认取demo演示页面传递的参数，可以自行定制规则
				'txnTime' => $time,	//订单发送时间，格式为YYYYMMDDhhmmss，取北京时间，此处默认取demo演示页面传递的参数
				'txnAmt' => $amt,	//交易金额，单位分，此处默认取demo演示页面传递的参数
				
				// 订单超时时间。
				// 超过此时间后，除网银交易外，其他交易银联系统会拒绝受理，提示超时。 跳转银行网银交易如果超时后交易成功，会自动退款，大约5个工作日金额返还到持卡人账户。
				// 此时间建议取支付时的北京时间加15分钟。
				// 超过超时时间调查询接口应答origRespCode不是A6或者00的就可以判断为失败。
				'payTimeout' => date('YmdHis', strtotime('+15 minutes')), 
		
				'riskRateInfo' =>'{commodityName=' . $product_name . '}',
		
				// 请求方保留域，
				// 透传字段，查询、通知、对账文件中均会原样出现，如有需要请启用并修改自己希望透传的数据。
				// 出现部分特殊字符时可能影响解析，请按下面建议的方式填写：
				// 1. 如果能确定内容不会出现&={}[]"'等符号时，可以直接填写数据，建议的方法如下。
				//    'reqReserved' =>'透传信息1|透传信息2|透传信息3',
				// 2. 内容可能出现&={}[]"'符号时：
				// 1) 如果需要对账文件里能显示，可将字符替换成全角＆＝｛｝【】“‘字符（自己写代码，此处不演示）；
				// 2) 如果对账文件没有显示要求，可做一下base64（如下）。
				//    注意控制数据长度，实际传输的数据长度不能超过1024位。
				//    查询、通知等接口解析时使用base64_decode解base64后再对数据做后续解析。
				//    'reqReserved' => base64_encode('任意格式的信息都可以'),
				
				//TODO 其他特殊用法请查看 special_use_purchase.php
			);
		
		\com\unionpay\acp\sdk\AcpService::sign ( $params );
		$uri = \com\unionpay\acp\sdk\SDKConfig::getSDKConfig()->frontTransUrl;
		$html_form = \com\unionpay\acp\sdk\AcpService::createAutoFormHtml( $params, $uri );
		echo $html_form;
		exit;
		// error_log(print_r($html_form, true), 3, '/Volumes/mac-disk/work/www/debug.log');
	}
	
	private function _getOrder($userid, $orderid){
		$where = [
			'a.orderid' => $orderid,
			'a.userid' => $userid,
			'a.status' => 0
		];
		$order = Db::name('order')
			->alias('a')
			->leftJoin('order_detail b', 'a.id = b.orderid')
			->leftJoin('product c', 'b.productid = c.id')
			->field('a.total, c.name')
			->where($where)->find();
		return $order;
	}
	
	public function alipayReturn(){
		error_log(print_r('alipayReturn', true), 3, '/Volumes/mac-disk/work/www/debug.log');
		error_log(print_r($_POST, true), 3, '/Volumes/mac-disk/work/www/debug.log');
		error_log(print_r($_GET, true), 3, '/Volumes/mac-disk/work/www/debug.log');
		
		$alipay_path = BASE_PATH . '/extend/alipay/';
		require $alipay_path . 'config.php';
		require_once $alipay_path . 'wappay/service/AlipayTradeService.php';
		
		$arr = $_GET;
		$alipaySevice = new \AlipayTradeService($config); 
		$result = $alipaySevice->check($arr);
		
		/* 实际验证过程建议商户添加以下校验。
		1、商户需要验证该通知数据中的out_trade_no是否为商户系统中创建的订单号，
		2、判断total_amount是否确实为该订单的实际金额（即商户订单创建时的金额），
		3、校验通知中的seller_id（或者seller_email) 是否为out_trade_no这笔单据的对应的操作方（有的时候，一个商户可能有多个seller_id/seller_email）
		4、验证app_id是否为该商户本身。
		*/
		if($result) {//验证成功
			/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
			//请在这里加上商户的业务逻辑程序代码
			
			//——请根据您的业务逻辑来编写程序（以下代码仅作参考）——
		    //获取支付宝的通知返回参数，可参考技术文档中页面跳转同步通知参数列表
		
			//商户订单号
		
			$out_trade_no = htmlspecialchars($arr['out_trade_no']);
		
			//支付宝交易号
		
			$trade_no = htmlspecialchars($arr['trade_no']);
				
			echo "验证成功<br />外部订单号：".$out_trade_no;
		
			//——请根据您的业务逻辑来编写程序（以上代码仅作参考）——
			
			/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
		}
		else {
		    //验证失败
		    echo "验证失败";
		}
	}
	
	public function alipayNotify(){
		error_log(print_r('alipayNotify', true), 3, '/Volumes/mac-disk/work/www/debug.log');
		error_log(print_r($_POST, true), 3, '/Volumes/mac-disk/work/www/debug.log');
		error_log(print_r($_GET, true), 3, '/Volumes/mac-disk/work/www/debug.log');
		
		$alipay_path = BASE_PATH . '/extend/alipay/';
		require $alipay_path . 'config.php';
		require_once $alipay_path . 'wappay/service/AlipayTradeService.php';
		
		$arr = $_POST;
		$alipaySevice = new AlipayTradeService($config); 
		$alipaySevice->writeLog(var_export($_POST,true));
		$result = $alipaySevice->check($arr);
		
		/* 实际验证过程建议商户添加以下校验。
		1、商户需要验证该通知数据中的out_trade_no是否为商户系统中创建的订单号，
		2、判断total_amount是否确实为该订单的实际金额（即商户订单创建时的金额），
		3、校验通知中的seller_id（或者seller_email) 是否为out_trade_no这笔单据的对应的操作方（有的时候，一个商户可能有多个seller_id/seller_email）
		4、验证app_id是否为该商户本身。
		*/
		if($result) {//验证成功
			/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
			//请在这里加上商户的业务逻辑程序代
		
			
			//——请根据您的业务逻辑来编写程序（以下代码仅作参考）——
			
		    //获取支付宝的通知返回参数，可参考技术文档中服务器异步通知参数列表
			
			$merId = Request::param('merId');
			$orderId = Request::param('orderId');
			$respCode = Request::param('respCode');
			$respMsg = Request::param('respMsg');
			
			if(! isset ( $_POST ['signature'] )){
				echo '非法操作 code: 40001';
				exit;
			}
			$check = \com\unionpay\acp\sdk\AcpService::validate ( $_POST );
			if(! $check){
				echo '非法操作 code: 40002';
				exit;
			}
			
			if(Config::get('app.unionpay_merid') != $merId){
				echo '非法操作 code: 40003';
				exit;
			}
			
			if($respCode != '00' && $respMsg != 'success'){
				echo '非法操作 code: 40004';
				exit;
			}
			
			$date = date('Y-m-d H:i:s');
			// 修改订单状态
			$update_data = [
				'status' => 1,
				'updated_date' => $date
			];
			$update_where = [
				'orderid' => $orderId
			];

			// 更新订单付款状态
			$update_status = Db::name('order')
				->where($update_where)
				->update($update_data);
			if($update_status === false){
				echo '付款失败';
				exit;
			}
			$url = $this->url('/index.php?s=home');
			header('Location: ' . $url);
			// echo '付款成功<a href="">首页</a>';
			exit;
			//商户订单号
		
			$out_trade_no = $_POST['out_trade_no'];
		
			//支付宝交易号
			$trade_no = $_POST['trade_no'];
		
			//交易状态
			$trade_status = $_POST['trade_status'];
		
		
		    if($_POST['trade_status'] == 'TRADE_FINISHED') {
		
				//判断该笔订单是否在商户网站中已经做过处理
					//如果没有做过处理，根据订单号（out_trade_no）在商户网站的订单系统中查到该笔订单的详细，并执行商户的业务程序
					//请务必判断请求时的total_amount与通知时获取的total_fee为一致的
					//如果有做过处理，不执行商户的业务程序
						
				//注意：
				//退款日期超过可退款期限后（如三个月可退款），支付宝系统发送该交易状态通知
		    }
		    else if ($_POST['trade_status'] == 'TRADE_SUCCESS') {
				//判断该笔订单是否在商户网站中已经做过处理
					//如果没有做过处理，根据订单号（out_trade_no）在商户网站的订单系统中查到该笔订单的详细，并执行商户的业务程序
					//请务必判断请求时的total_amount与通知时获取的total_fee为一致的
					//如果有做过处理，不执行商户的业务程序			
				//注意：
				//付款完成后，支付宝系统发送该交易状态通知
		    }
			//——请根据您的业务逻辑来编写程序（以上代码仅作参考）——
		        
			echo "success";		//请不要修改或删除
				
		}else {
		    //验证失败
		    echo "fail";	//请不要修改或删除
		
		}
		
	}

	public function wxpayNotify(){
		error_log(print_r('wxpayNotify', true), 3, '/Volumes/mac-disk/work/www/debug.log');
		error_log(print_r($_POST, true), 3, '/Volumes/mac-disk/work/www/debug.log');
		error_log(print_r($_GET, true), 3, '/Volumes/mac-disk/work/www/debug.log');
		
		$wxpay_path = BASE_PATH . '/extend/WechatPay/';
		require $wxpay_path . 'config.php';
		
		$arr = $_POST;
		$alipaySevice = new AlipayTradeService($config); 
		$alipaySevice->writeLog(var_export($_POST,true));
		$result = $alipaySevice->check($arr);
		
		/* 实际验证过程建议商户添加以下校验。
		1、商户需要验证该通知数据中的out_trade_no是否为商户系统中创建的订单号，
		2、判断total_amount是否确实为该订单的实际金额（即商户订单创建时的金额），
		3、校验通知中的seller_id（或者seller_email) 是否为out_trade_no这笔单据的对应的操作方（有的时候，一个商户可能有多个seller_id/seller_email）
		4、验证app_id是否为该商户本身。
		*/
		if($result) {//验证成功
			/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
			//请在这里加上商户的业务逻辑程序代
		
			
			//——请根据您的业务逻辑来编写程序（以下代码仅作参考）——
			
		    //获取支付宝的通知返回参数，可参考技术文档中服务器异步通知参数列表

		    $merId = Request::param('merId');
			$orderId = Request::param('orderId');
			$respCode = Request::param('respCode');
			$respMsg = Request::param('respMsg');
			
			if(! isset ( $_POST ['signature'] )){
				echo '非法操作 code: 40001';
				exit;
			}
			$check = \com\unionpay\acp\sdk\AcpService::validate ( $_POST );
			if(! $check){
				echo '非法操作 code: 40002';
				exit;
			}
			
			if(Config::get('app.unionpay_merid') != $merId){
				echo '非法操作 code: 40003';
				exit;
			}
			
			if($respCode != '00' && $respMsg != 'success'){
				echo '非法操作 code: 40004';
				exit;
			}
			
			$date = date('Y-m-d H:i:s');
			// 修改订单状态
			$update_data = [
				'status' => 1,
				'updated_date' => $date
			];
			$update_where = [
				'orderid' => $orderId
			];

			// 更新订单付款状态
			$update_status = Db::name('order')
				->where($update_where)
				->update($update_data);
			if($update_status === false){
				echo '付款失败';
				exit;
			}
			$url = $this->url('/index.php?s=home');
			header('Location: ' . $url);
			// echo '付款成功<a href="">首页</a>';
			exit;
			
			//商户订单号
		
			$out_trade_no = $_POST['out_trade_no'];
		
			//支付宝交易号
			$trade_no = $_POST['trade_no'];
		
			//交易状态
			$trade_status = $_POST['trade_status'];
		
		
		    if($_POST['trade_status'] == 'TRADE_FINISHED') {
		
				//判断该笔订单是否在商户网站中已经做过处理
					//如果没有做过处理，根据订单号（out_trade_no）在商户网站的订单系统中查到该笔订单的详细，并执行商户的业务程序
					//请务必判断请求时的total_amount与通知时获取的total_fee为一致的
					//如果有做过处理，不执行商户的业务程序
						
				//注意：
				//退款日期超过可退款期限后（如三个月可退款），支付宝系统发送该交易状态通知
		    }
		    else if ($_POST['trade_status'] == 'TRADE_SUCCESS') {
				//判断该笔订单是否在商户网站中已经做过处理
					//如果没有做过处理，根据订单号（out_trade_no）在商户网站的订单系统中查到该笔订单的详细，并执行商户的业务程序
					//请务必判断请求时的total_amount与通知时获取的total_fee为一致的
					//如果有做过处理，不执行商户的业务程序			
				//注意：
				//付款完成后，支付宝系统发送该交易状态通知
		    }
			//——请根据您的业务逻辑来编写程序（以上代码仅作参考）——
		        
			echo "success";		//请不要修改或删除
				
		}else {
		    //验证失败
		    echo "fail";	//请不要修改或删除
		
		}
		
	}
	
	public function wxpay(){
		$wxpay_path = BASE_PATH . '/extend/WechatPay/';
		require $wxpay_path . 'config.php';
		
		// 工厂方法构造一个实例
		$instance = \WeChatPay\Builder::factory([
		    // 商户号
		    'mchid' => $config['mchid'],
		    // 商户证书序列号
		    'serial' => 'XXXXXXXXXX',
		    // 商户API私钥 PEM格式的文本字符串或者文件resource
		    'privateKey' => \WeChatPay\Util\PemUtil::loadPrivateKey('/path/to/mch/apiclient_key.pem'),
		    'certs' => [
		        // 可由内置的平台证书下载器 `./bin/CertificateDownloader.php` 生成
		        'YYYYYYYYYY' => \WeChatPay\Util\PemUtil::loadCertificate('/path/to/wechatpay/cert.pem')
		    ],
		    // APIv2密钥(32字节)--不使用APIv2可选
		    'secret' => 'ZZZZZZZZZZ',
		    'merchant' => [// --不使用APIv2可选
		        // 商户证书 文件路径 --不使用APIv2可选
		        'cert' => '/path/to/mch/apiclient_cert.pem',
		        // 商户API私钥 文件路径 --不使用APIv2可选
		        'key' => '/path/to/mch/apiclient_key.pem',
		    ],
		]);
		error_log(print_r($instance, true) . "\r\n", 3, '/Volumes/mac-disk/work/www/debug.log');
		
		try {
			// $instance->v3->pay->transactions->h5->post
		    $resp = $instance->v3->pay->transactions->native->post(['json' => [
		        'mchid' => '1900006XXX',
		        'out_trade_no' => 'native12177525012014070332333',
		        'appid' => 'wxdace645e0bc2cXXX',
		        'description' => 'Image形象店-深圳腾大-QQ公仔',
		        'notify_url' => 'https://weixin.qq.com/',
		        'amount' => [
		            'total' => 1,
		            'currency' => 'CNY'
		        ],
		    ]]);
		
		    echo $resp->getStatusCode() . ' ' . $resp->getReasonPhrase(), PHP_EOL;
		    echo $resp->getBody(), PHP_EOL;
		} catch (Exception $e) {
		    // 进行错误处理
		    echo $e->getMessage(), PHP_EOL;
		    if ($e instanceof \Psr\Http\Message\ResponseInterface && $e->hasResponse()) {
		        echo $e->getResponse()->getStatusCode() . ' ' . $e->getResponse()->getReasonPhrase(), PHP_EOL;
		        echo $e->getResponse()->getBody();
		    }
		}

	}
	
	public function alipay(){
		$alipay_path = BASE_PATH . '/extend/alipay/';
		require_once $alipay_path . 'wappay/service/AlipayTradeService.php';
		require_once $alipay_path . 'wappay/buildermodel/AlipayTradeWapPayContentBuilder.php';
		require $alipay_path . 'config.php';
		if (!empty($_POST['WIDout_trade_no'])&& trim($_POST['WIDout_trade_no'])!=""){
		    //商户订单号，商户网站订单系统中唯一订单号，必填
		    $out_trade_no = $_POST['WIDout_trade_no'];
		
		    //订单名称，必填
		    $subject = $_POST['WIDsubject'];
		
		    //付款金额，必填
		    $total_amount = $_POST['WIDtotal_amount'];
		
		    //商品描述，可空
		    $body = $_POST['WIDbody'];
		
		    //超时时间
		    $timeout_express="1m";
		
		    $payRequestBuilder = new \AlipayTradeWapPayContentBuilder();
		    $payRequestBuilder->setBody($body);
		    $payRequestBuilder->setSubject($subject);
		    $payRequestBuilder->setOutTradeNo($out_trade_no);
		    $payRequestBuilder->setTotalAmount($total_amount);
		    $payRequestBuilder->setTimeExpress($timeout_express);
		    $payResponse = new \AlipayTradeService($config);
			
		    $result=$payResponse->wapPay($payRequestBuilder, $config['return_url'], $config['notify_url']);
		// error_log(print_r($result, true) . "\r\n", 3, '/Volumes/mac-disk/work/www/debug.log');
			return ;
		}
		
		// View::assign('invite_code', $invite_code);
		return View::fetch('alipay');
	}
	
	public function aliOrder(){
		$ret = [
			'code' => 0,
			'data' => '',
			'msg' => ''
		];
		
		$alipay_path = BASE_PATH . '/extend/alipay/';
		require_once $alipay_path . 'aop/AopClient.php';
		require $alipay_path . 'config.php';
		
		$order_obj = new Order($this->app);
		$order = $order_obj->createOrder(true);
		
		if(! $order || ! (is_array($order)) || ! isset($order['orderid'])){
			$ret['msg'] = '创建订单失败';
			return json($ret);
		}
		
		$order_info = [
			'app_id' => $config['app_id'],
			'method' => 'alipay.trade.wap.pay',
			'format' => 'JSON',
			'charset' => $config['charset'],
			'sign_type' => $config['sign_type'],
			'version' => "1.0",
			'return_url' => $config['return_url'],
			'notify_url' => $config['notify_url'],
			'timestamp' => date('Y-m-d H:i:s'),
			// 'sign' => $sign,
			'biz_content' => json_encode([
				'subject' => $order['name'],
				'out_trade_no' => $order['orderid'],
				'total_amount' => $order['total'],
				'product_code' =>  'QUICK_WAP_PAY',
				// quit_url: 'https://imgbed.cn/static/alipay-return.html'
			])
		];
		$aop = new \AopClient();
		$aop->rsaPrivateKey = $config['merchant_private_key'];
		$order_info['sign']	= $aop->rsaSign($order_info, 'RSA2');
		$order_info['biz_content'] = json_decode($order_info['biz_content'], true);
		$ret['data'] = $order_info;
		$ret['code'] = 1;
		return json($ret);
	}
	
	public function wxOrder(){
		$ret = [
			'code' => 0,
			'data' => '',
			'msg' => ''
		];

		$wxpay_path = BASE_PATH . '/extend/WechatPay/';
		require $wxpay_path . 'config.php';
		
		$order_obj = new Order($this->app);
		$order = $order_obj->createOrder(true);
		
		if(! $order || ! (is_array($order)) || ! isset($order['orderid'])){
			$ret['msg'] = '创建订单失败';
			return json($ret);
		}
		
		
		$order_info = [
			'appid' => $config['app_id'],
			/*
			'partnerid' => '',
			'prepayid' => '',
			'timestamp' => time(),
			'noncestr' => '',
			*/
			'mchid' => ''，
			'description' => $order['name'],
			'out_trade_no' => $order['orderid'],
			'notify_url' => '',
			'amount' => [
				'total' => $order['total']
			],
			'scene_info' => [
				'payer_client_ip' => '',
				'h5_info' => [
					'type' => 'IOS' // iOS, Android, Wap
				]
			]
		];
		$aop = new \AopClient();
		$aop->rsaPrivateKey = $config['merchant_private_key'];
		$order_info['sign']	= $aop->rsaSign($order_info, 'RSA2');
		$order_info['biz_content'] = json_decode($order_info['biz_content'], true);
		$ret['data'] = $order_info;
		$ret['code'] = 1;
		return json($ret);
	}
	
	/**
	 * 付款回调
	 */
	public function frontReceive(){
		// error_log(print_r('frontReceive', true), 3, '/Volumes/mac-disk/work/www/debug.log');
		// error_log(print_r($_POST, true), 3, '/Volumes/mac-disk/work/www/debug.log');
		// error_log(print_r($_GET, true), 3, '/Volumes/mac-disk/work/www/debug.log');
		$merId = Request::param('merId');
		$orderId = Request::param('orderId');
		$respCode = Request::param('respCode');
		$respMsg = Request::param('respMsg');
		
		if(! isset ( $_POST ['signature'] )){
			echo '非法操作 code: 40001';
			exit;
		}
		$check = \com\unionpay\acp\sdk\AcpService::validate ( $_POST );
		if(! $check){
			echo '非法操作 code: 40002';
			exit;
		}
		
		if(Config::get('app.unionpay_merid') != $merId){
			echo '非法操作 code: 40003';
			exit;
		}
		
		if($respCode != '00' && $respMsg != 'success'){
			echo '非法操作 code: 40004';
			exit;
		}
		
		$date = date('Y-m-d H:i:s');
		// 修改订单状态
		$update_data = [
			'status' => 1,
			'updated_date' => $date
		];
		$update_where = [
			'orderid' => $orderId
		];
		$update_status = Db::name('order')
			->where($update_where)
			->update($update_data);
		if($update_status === false){
			echo '付款失败';
			exit;
		}
		$url = $this->url('/index.php?s=home');
		header('Location: ' . $url);
		// echo '付款成功<a href="">首页</a>';
		exit;
	}
	
	/**
	 * 付款回调
	 */
	public function frontReceiveApp(){
		// error_log(print_r('frontReceive', true), 3, '/Volumes/mac-disk/work/www/debug.log');
		// error_log(print_r($_POST, true), 3, '/Volumes/mac-disk/work/www/debug.log');
		// error_log(print_r($_GET, true), 3, '/Volumes/mac-disk/work/www/debug.log');
		$merId = Request::param('merId');
		$orderId = Request::param('orderId');
		$respCode = Request::param('respCode');
		$respMsg = Request::param('respMsg');
		
		if(! isset ( $_POST ['signature'] )){
			echo '非法操作 code: 40001';
			exit;
		}
		$check = \com\unionpay\acp\sdk\AcpService::validate ( $_POST );
		if(! $check){
			echo '非法操作 code: 40002';
			exit;
		}
		
		if(Config::get('app.unionpay_merid') != $merId){
			echo '非法操作 code: 40003';
			exit;
		}
		
		if($respCode != '00' && $respMsg != 'success'){
			echo '非法操作 code: 40004';
			exit;
		}
		
		$date = date('Y-m-d H:i:s');
		// 修改订单状态
		$update_data = [
			'status' => 1,
			'updated_date' => $date
		];
		$update_where = [
			'orderid' => $orderId
		];
		$update_status = Db::name('order')
			->where($update_where)
			->update($update_data);
		if($update_status === false){
			echo '付款失败';
			exit;
		}
		$url = $this->url('/index.php?s=home/apppay');
		header('Location: ' . $url);
		// echo '付款成功<a href="">首页</a>';
		exit;
	}
	
	public function backReceive(){
		error_log(print_r('frontReceive', true), 3, '/Volumes/mac-disk/work/www/debug.log');
		error_log(print_r($_POST, true), 3, '/Volumes/mac-disk/work/www/debug.log');
		error_log(print_r($_GET, true), 3, '/Volumes/mac-disk/work/www/debug.log');
		
	}
}
