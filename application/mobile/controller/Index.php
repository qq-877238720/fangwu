<?php
namespace app\mobile\controller;
use fast\Random;

class Index extends Controller
{

    public function index()
    {
        $id = $this->request->param('id');
        $uid = $this->request->param('uid');
        $this->assign('id',$id);
        $this->assign('uid',$uid);
        return view();
    }

    public function upload()
    {
        $id = $this->request->param('id');
        $uid = $this->request->param('uid');
        $tag = $this->request->param('tag');
        // Config::set('default_return_type', 'json');
        $file = $this->request->file('file');
        if (empty($file)) {
            $msg = ['code'=>0,'data'=>[],'msg'=>'为获取到上传文件，请重新上传'];
            return json_encode($msg);
        }

        //判断是否已经存在附件
        $sha1 = $file->hash();
        // if($sha1){
        //     $msg = ['code'=>0,'data'=>[],'msg'=>'该文件已经存在，请检查'];
        //     return json_encode($msg);
        // }
        $extparam = $this->request->post();

        $upload = [
                /**
                 * 上传地址,默认是本地上传
                 */
                'uploadurl' => 'ajax/upload',
                /**
                 * CDN地址
                 */
                'cdnurl'    => '',
                /**
                 * 文件保存格式
                 */
                'savekey'   => '/uploads/{year}{mon}{day}/{filemd5}{.suffix}',
                /**
                 * 最大可上传大小
                 */
                'maxsize'   => '20mb',
                /**
                 * 可上传的文件类型
                 */
                'mimetype'  => 'jpg,png,jpeg',
                /**
                 * 是否支持批量上传
                 */
                'multiple'  => false,
            ];

        preg_match('/(\d+)(\w+)/', $upload['maxsize'], $matches);
        $type = strtolower($matches[2]);
        $typeDict = ['b' => 0, 'k' => 1, 'kb' => 1, 'm' => 2, 'mb' => 2, 'gb' => 3, 'g' => 3];
        $size = (int)$upload['maxsize'] * pow(1024, isset($typeDict[$type]) ? $typeDict[$type] : 0);
        $fileInfo = $file->getInfo();
        $suffix = strtolower(pathinfo($fileInfo['name'], PATHINFO_EXTENSION));
        $suffix = $suffix ? $suffix : 'file';

        $mimetypeArr = explode(',', strtolower($upload['mimetype']));
        $typeArr = explode('/', $fileInfo['type']);

        //验证文件后缀
        if ($upload['mimetype'] !== '*' &&
            (
                !in_array($suffix, $mimetypeArr)
                || (stripos($typeArr[0] . '/', $upload['mimetype']) !== false && (!in_array($fileInfo['type'], $mimetypeArr) && !in_array($typeArr[0] . '/*', $mimetypeArr)))
            )
        ) {
            // $this->error(__('Uploaded file format is limited'));
            $msg = ['code'=>0,'data'=>[],'msg'=>'上载的文件格式有限'];
            return json_encode($msg);
        }
        $replaceArr = [
            '{year}'     => date("Y"),
            '{mon}'      => date("m"),
            '{day}'      => date("d"),
            '{hour}'     => date("H"),
            '{min}'      => date("i"),
            '{sec}'      => date("s"),
            '{random}'   => Random::alnum(16),
            '{random32}' => Random::alnum(32),
            '{filename}' => $suffix ? substr($fileInfo['name'], 0, strripos($fileInfo['name'], '.')) : $fileInfo['name'],
            '{suffix}'   => $suffix,
            '{.suffix}'  => $suffix ? '.' . $suffix : '',
            '{filemd5}'  => md5_file($fileInfo['tmp_name']),
        ];
        $savekey = $upload['savekey'];
        $savekey = str_replace(array_keys($replaceArr), array_values($replaceArr), $savekey);

        $uploadDir = substr($savekey, 0, strripos($savekey, '/') + 1);
        $fileName = substr($savekey, strripos($savekey, '/') + 1);
        //
        $splInfo = $file->validate(['size' => $size])->move( './' . $uploadDir, $fileName);
        if ($splInfo) {
            $imagewidth = $imageheight = 0;
            if (in_array($suffix, ['gif', 'jpg', 'jpeg', 'bmp', 'png', 'swf'])) {
                $imgInfo = getimagesize($splInfo->getPathname());
                $imagewidth = isset($imgInfo[0]) ? $imgInfo[0] : $imagewidth;
                $imageheight = isset($imgInfo[1]) ? $imgInfo[1] : $imageheight;
            }
            $params = array(
                'admin_id'    => $id,
                'user_id'     => $uid,
                'filesize'    => $fileInfo['size'],
                'imagewidth'  => $imagewidth,
                'imageheight' => $imageheight,
                'imagetype'   => $suffix,
                'imageframes' => 0,
                'mimetype'    => $fileInfo['type'],
                'url'         => $uploadDir . $splInfo->getSaveName(),
                'uploadtime'  => time(),
                'storage'     => 'local',
                'sha1'        => $sha1,
                'extparam'    => json_encode($extparam),
                'tag'    => $tag,
            );
            // $res = Db::table('attachment')->insert($params);
            $attachment = model("attachment");
            $attachment->data(array_filter($params));
            $attachment->save();
            // \think\Hook::listen("upload_after", $attachment);
            // $this->success(__('Upload successful'), null, [
            //     'url' => $uploadDir . $splInfo->getSaveName()
            // ]);
            $msg = ['code'=>1,'data'=>['src'=>$uploadDir . $splInfo->getSaveName()],'msg'=>"上传成功"];
            return json_encode($msg);
        } else {
            // 上传失败获取错误信息
            // $this->error($file->getError());
            $msg = ['code'=>0,'data'=>'','msg'=>$file->getError()];
            return json_encode($msg);
        }
    }
}
