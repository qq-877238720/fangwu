<?php 
namespace app\lib;

use think\facade\Env;

// 导入IDCardOCR模块的client
use TencentCloud\Ocr\V20181119\OcrClient;
// 导入要请求接口对应的Request类
use TencentCloud\Ocr\V20181119\Models\IDCardOCRRequest;
use TencentCloud\Ocr\V20181119\Models\IDCardOCRResponse;
use TencentCloud\Common\Exception\TencentCloudSDKException;
use TencentCloud\Common\Credential;
// 导入可选配置类
use TencentCloud\Common\Profile\ClientProfile;
use TencentCloud\Common\Profile\HttpProfile;

/**
 * 身份证腾讯接口封装
 */
class IDCardOCR
{

	/**
	 * 获取身份证信息
	 * @param  [图片路径]      $imageUrl [description]
	 * @param  [FRONT|BACK]   $CardSide [description]
	 * @param  [获取对应字段]   $Config   [description]
	 * @return [array]                [description]
	 */
	public static function getIDCard($imageUrl, $CardSide = 'FRONT', $Config = ['CropIdCard' => true])
	{

		if (!$imageUrl) {
			return false;
		}
		$uid = USER_ID;
		try {
			require_once Env::get('ROOT_PATH') . '/tencentcloud-sdk-php/TCloudAutoLoader.php';
		    // 实例化一个证书对象
		    $cred = new Credential("AKIDHTCFS5aADb5qrLnHh3exlo9cHOHOl2am", "wTU0uX0nFz3isdl1teXf5tjGtKXXiyYb");
		    $client = new OcrClient($cred, "ap-guangzhou");

		    $req = new IDCardOCRRequest();
		    // 图片路径
		    $req->ImageUrl = $imageUrl;
		    if (!empty($CardSide)) {
		    	// 正反面
		    	$req->CardSide = strtoupper($CardSide);
		    }
		    // 获取对应字段
		    $req->Config = json_encode($Config);

		    // 通过client对象调用想要访问的接口，需要传入请求对象
	    	$resp = $client->IDCardOCR($req);

		    // print_r(json_decode($resp->getAdvancedInfo(), true));
		    $base64 = json_decode($resp->getAdvancedInfo(), true)['IdCard'];
		    // var_dump($resp);
		    $img = base64_decode($base64);

		    $fileDir  = Env::get('ROOT_PATH') .'/public/static/images/idcard/' . $uid . '/' . date("Ymd");
	    	$fileName = md5(uniqid(microtime(true))).".jpg";
		    if (self::mkdirs($fileDir)) {

			    $fileUrl = $fileDir . '/' . $fileName;
			    file_put_contents($fileUrl, $img);

				// $a = file_put_contents(Env::get('ROOT_PATH') . '/test.jpg', $img);//返回的是字节数
				// print_r($a);

				$result = [
					'code' => 0,
					'msg'  => 'success',
				];

				// 国徽面
				if (strtoupper($CardSide) == 'BACK') {

					return array_merge($result, [
						'data' => [
							'authority' => $resp->getAuthority(),	// 发证机关
							'validDate' => $resp->getValidDate(),	// 证件有效期
							'imgUrl' => "/static/images/idcard/".$uid.'/'.date("Ymd").'/'.$fileName,	// 证件裁剪图
						]
					]);
				} else {
					// 人像面
					return array_merge($result, [
						'data' => [
							'name' => $resp->getName(),	// 姓名
							'sex' => $resp->getSex(),		// 性别
							'nation' => $resp->getNation(),	// 民族
							'birth' => $resp->getBirth(),	// 出生日期
							'address' => $resp->getAddress(),	// 地址
							'idNum' => $resp->getIdNum(),	// 身份证号
							'imgUrl' => "/static/images/idcard/".$uid.'/'.date("Ymd").'/'.$fileName,	// 证件裁剪图
						]
					]);
				}
		    }

		}
		catch(TencentCloudSDKException $e) {
			return [
				'code' => 1,
				'msg'  => $e->getMessage(),
				'data' => null
			];
		}
	}

	public static function mkdirs($dir, $mode = 0777)
	{
	    if (is_dir($dir) || @mkdir($dir, $mode)) return TRUE;
	    if (!self::mkdirs(dirname($dir), $mode)) return FALSE;
	    return @mkdir($dir, $mode);
	}
}
 ?>