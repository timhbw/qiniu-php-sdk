<?php
namespace Qiniu\Storage;

use Qiniu\Config;

/**
 * 主要涉及了资源上传接口的实现
 *
 * @link http://developer.qiniu.com/docs/v6/api/reference/up/
 */
final class UploadManager
{
    private $config;

    public function __construct(Config $config = null)
    {
        if ($config === null) {
            $config = new Config();
        }
        $this->config = $config;
    }

    /**
     * 上传二进制流到七牛
     *
     * @param string $upToken    上传凭证
     * @param string $key        上传文件名
     * @param string $data       上传二进制流
     * @param string $params     自定义变量，规格参考
     *                    http://developer.qiniu.com/docs/v6/api/overview/up/response/vars.html#xvar
     * @param string $mime 上传数据的mimeType
     *
     * @param string $fname
     * @return array    包含已上传文件的信息，类似：
     *                                              [
     *                                                  "hash" => "<Hash string>",
     *                                                  "key" => "<Key string>"
     *                                              ]
     */
    public function put(
        $upToken,
        $key,
        $data,
        $params = null,
        $mime = 'application/octet-stream',
        $fname = "default_filename"
    ) {
    
        $params = self::trimParams($params);
        return FormUploader::put(
            $upToken,
            $key,
            $data,
            $this->config,
            $params,
            $mime,
            $fname
        );
    }


    /**
     * 上传文件到七牛
     *
     * @param string $upToken    上传凭证
     * @param string $key        上传文件名
     * @param string $filePath   上传文件的路径
     * @param string $params     自定义变量，规格参考
     *                    http://developer.qiniu.com/docs/v6/api/overview/up/response/vars.html#xvar
     * @param string $mime       上传数据的mimeType
     * @param bool  $checkCrc   是否校验crc32
     *
     * @return array    包含已上传文件的信息，类似：
     *                                              [
     *                                                  "hash" => "<Hash string>",
     *                                                  "key" => "<Key string>"
     *                                              ]
     */
    public function putFile(
        $upToken,
        $key,
        $filePath,
        $params = null,
        $mime = 'application/octet-stream',
        $checkCrc = false
    ) {
    
        $file = fopen($filePath, 'rb');
        if ($file === false) {
            throw new \Exception("file can not open", 1);
        }
        $params = self::trimParams($params);
        $stat = fstat($file);
        $size = $stat['size'];
        if ($size <= Config::BLOCK_SIZE) {
            $data = fread($file, $size);
            fclose($file);
            if ($data === false) {
                throw new \Exception("file can not read", 1);
            }
            return FormUploader::put(
                $upToken,
                $key,
                $data,
                $this->config,
                $params,
                $mime,
                basename($filePath)
            );
        }

        $up = new ResumeUploader(
            $upToken,
            $key,
            $file,
            $size,
            $params,
            $mime,
            $this->config
        );
        $ret = $up->upload(basename($filePath));
        fclose($file);
        return $ret;
    }

    public static function trimParams($params)
    {
        if ($params === null) {
            return null;
        }
        $ret = array();
        foreach ($params as $k => $v) {
            $pos1 = strpos($k, 'x:');
            $pos2 = strpos($k, 'x-qn-meta-');
            if (($pos1 === 0 || $pos2 === 0) && !empty($v)) {
                $ret[$k] = $v;
            }
        }
        return $ret;
    }
}
