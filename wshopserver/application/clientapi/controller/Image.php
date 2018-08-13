<?php
namespace app\clientapi\controller;
use think\Controller;
use think\File;
use think\Log;
use think\Request;
use think\Loader;
use think\Config;
Loader::import('cos.cos-autoloader', EXTEND_PATH, '.php');
/**
 * 图片上传处理
 */
class Image extends Controller
{

    // 文件上传命名规则
    protected $rule = 'date';
    // 文件hash信息
    protected $hash = [];
    /**
     * 上传图片 上传目录在 public/upload  文件夹
     * @param file 文件
     * @return 相对地址
     */
    public function upload() {
        $file = Request::instance()->file('file');
        $info = $file->getInfo();
        $extend = substr(strrchr($info['name'], '.'), 1);
        $extendnew = $extend;
        $tempFile   = $info['tmp_name'];
        $filename = $this->tencent_upload($tempFile,$extendnew);
        return result(1, 'success',Config::get('paths.coshost').$filename);

        // 给定一个目录
//        $info = $file->move('upload');
//        if($info && $info->getPathname()) {
//            $filenamenew = $this->tencent_upload($filename,$result[2]);
//            return result(1, 'success','/'.$info->getPathname());
//        }
//        return result(0,'upload error');
    }
    /**
     * pc端uploadfive控件上传图片
     * @param file 文件
     * @return 相对地址
     */
    public function uploadfive() {
        $fileTypes = array('jpg', 'jpeg', 'gif', 'png');
        if (!empty($_FILES)) {
            $fileParts = pathinfo($_FILES['Filedata']['name']);
            if (in_array(strtolower($fileParts['extension']), $fileTypes)) {
                $extend = substr(strrchr($_FILES['Filedata']['name'], '.'), 1);
                $extendnew = $extend;
                $tempFile   = $_FILES['Filedata']['tmp_name'];
                $imginfo = getimagesize($tempFile,$extend);
                if($imginfo[0] == 50 && $imginfo[1] == 50){
                    $filename = $this->tencent_upload($tempFile,$extendnew);
                    return result(1, 'success',Config::get('paths.coshost').$filename);
//                    $file = new File($tempFile);
//                    $info = $file->moveuploadyfive('upload',true,true,$extend);
//                    if($info && $info->getPathname()) {
//                        return result(1, 'success','/'.$info->getPathname());
//                    }
                }else{
                    return result(0,'请上传50*50的图片');
                }

            }else{
                return result(0,'不支持此图片类型');
            }

            return result(0,'upload error');
        }
    }
    /**
     * b端base64方式上传图片
     * @param file 文件
     * @return 相对地址
     */
    function base64_upload($base64) {
        $base64_image = str_replace(' ', '+', $base64);
        //post的数据里面，加号会被替换为空格，需要重新替换回来，如果不是post的数据，则注释掉这一行
        if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64_image, $result)){
            //匹配成功
            if($result[2] == 'jpeg'){
                $image_name = uniqid().'.jpg';
                //纯粹是看jpeg不爽才替换的
            }else{
                $image_name = uniqid().'.'.$result[2];
            }
            //
            $path = rtrim('upload', DS) . DS;
            // 文件保存命名规则
            $saveName  = date('Ymd') . DS . md5(microtime(true));
            $filename = $path . $saveName.'.'.$result[2];

            // 检测目录
            if (false === $this->checkPath(dirname($filename))) {
                return false;
            }
            //
//            $image_file = "./upload/{$image_name}";
            //服务器文件存储路径
            if (file_put_contents($filename, base64_decode(str_replace($result[1], '', $base64_image)))){
                $filenamenew = $this->tencent_upload($filename,$result[2]);
//                return result(1, 'success',Config::get('paths.coshost').$filenamenew);

                return $filenamenew;
            }else{
                return false;
            }
        }else{
            return false;
        }
    }

    //util methods
    /**
     * 检查目录是否可写
     * @param  string   $path    目录
     * @return boolean
     */
    protected function checkPath($path)
    {
        if (is_dir($path)) {
            return true;
        }

        if (mkdir($path, 0755, true)) {
            return true;
        } else {
            $this->error = "目录 {$path} 创建失败！";
            return false;
        }
    }
    /**
     * [tencent_upload cos上传]
     */
    function tencent_upload($file,$extend)
    {
        $saveName  = md5(microtime(true));
        Log::info($saveName);
        Log::info($extend);
        $filename = $saveName.'.'.$extend;
        $cosClient = new \Qcloud\Cos\Client(array('region' => Config::get('paths.cosregion'),
            'credentials'=> array(
                'secretId'    => Config::get('paths.coskey'),
                'secretKey' => Config::get('paths.cossecret'))));

        try {
            $result = $cosClient->putObject(array(
                //bucket的命名规则为{name}-{appid} ，此处填写的存储桶名称必须为此格式
                'Bucket' => Config::get('paths.cosbucket'),
                'Key' => $filename,
                'Body' => file_get_contents($file)));
//            return $result['ObjectURL'];
            return $filename;
        } catch (\Exception $e) {
            echo "$e\n";
        }

//
//        $tengData = [/*             */];
//        $cosClient = new Client(
//            [
//                'region' => $tengData['region'],
//                'credentials'=> [
//                    'secretId'    => $tengData['secretid'],
//                    'secretKey' => $tengData['secretkey']]
//            ]);
//        try {
//            // 上传到本地服务器失败
//            $res = local_upload($file);
//            if($res !== false){
//                $path = $res['path'];
//                $name = $res['name'];
//                $filePath = $path.$name;
//            }else{
//                return ['code'=>-1,'上传本地失败'];
//            }
//            $result = $cosClient->putObject(
//                [
//                    //bucket的命名规则为{name}-{appid} ，此处填写的存储桶名称必须为此格式
//                    'Bucket' => $tengData['bucket'],
//                    'Key' => $filePath,
//                    'Body' => file_get_contents($filePath)
//                ]);
//            return ['code'=>1,'url'=>$url."/".$filePath ];
//        } catch (\Exception $e) {
//            return "$e\n";
//        }
    }
}