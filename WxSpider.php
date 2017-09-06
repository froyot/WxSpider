<?php
namespace app\utils\sogoWx;
use app\utils\network\Network;

/**
 * WxSpider is get wx pages fron sogo search
 */
class WxSpider{

    private $network;
    private $domain = "http://mp.weixin.qq.com";
    function __construct()
    {
        $this->network = new Network();
        $this->network->setOption(CURLOPT_HEADER, true);
        $headers = array();
        $headers[] = 'User-Agent:Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/60.0.3112.113 Safari/537.36';
        

        $this->network->setOption(CURLOPT_HTTPHEADER, $headers);


        $this->network->setOption(CURLOPT_COOKIEJAR, "./cookie.txt");
        $this->network->setOption(CURLOPT_COOKIEFILE, "./cookie.txt");
    }

    function __destruct()
    {
        file_put_contents("./cookie.txt", "");//删除cookie
        WxSpider::clearCache();//删除缓存
    }

    /**
     * 通过关键字获取公众账号列表
     * @param  string $keyword 关键字
     * @return [type]          [description]
     */
    public function getAccounts( $keyword )
    {
        $url = "http://weixin.sogou.com/weixin?type=1&query=$keyword&ie=utf8";
        $data = $this->getData($url);
        //
        //href="http://mp.weixin.qq.com/profile?src=3&timestamp=1504661757&ver=1&signature=HgstaX7vi-prrQBHI*KsOcYzLlL*3Bvd6633U06qIqp4n3nkpYJhG-k*8Ji7h91NCxdI5fIzNT04PgGyy9oSTw=="
        $pattern = "/account_name_\d+\"\shref=\"(.*?)\"/is";//获取公众账号的文字列表地址
        if(preg_match_all($pattern, $data, $matches))
        {

            foreach ($matches[1] as $key => $value)
            {
                $this->getPageList($value, 1);
                
            }
        }
        else
        {
            //访问频繁，需要冷静
            WxSpider::error('访问频繁，需要冷静 '.$data);
            die;

        }
    }

    /**
     * 获取公众号的文章列表
     * @param  [type] $openid [description]
     * @param  [type] $ext    [description]
     * @param  [type] $page   [description]
     * @return [type]         [description]
     */
    public function getPageList($url,$page)
    {
        $url = html_entity_decode($url);
        list($t1, $t2) = explode(' ', microtime());
        $time = (float)sprintf('%.0f',(floatval($t1)+floatval($t2))*1000);//毫秒时间戳

        
        $pattern = "/var\s+msgList\s+=\s+(\{[\s\S]*?\});/is";

        $data = $this->getData($url);

        if(preg_match($pattern, $data, $matchs))
        {
            
            if( isset($matchs[1])  )
            {
                $data = json_decode($matchs[1],true);
                $this->getItems($data['list']);
                
            }
        }
        else
        {
            //访问频繁，需要冷静
            WxSpider::error('文章列表获取错误 '.$data);
        }
    }

    /**
     * 从xml列表中解析文章
     * @param  [type] $data [description]
     * @return [type]       [description]
     */
    public function getItems($data)
    {

        if(!$data)
        {
            WxSpider::error('列表数据空 ');
             return;
        }

        //解析列表
        foreach($data as $item)
        {



            $extinfo = $item["app_msg_ext_info"];
            $common = $item['comm_msg_info'];
            $article = [
                'docid'=>$common['id'],
                'title'=>$extinfo['title'],
                'url'=>$this->domain. html_entity_decode($extinfo['content_url']),
                'imglink'=>$extinfo['cover'],
                'sourcename'=>$extinfo['author']
            ];

            if( !$this->getContent($article) )
            {
                WxSpider::error('获取内容错误 ');
                continue;
            }

            $this->createData($article);
            sleep(5);//等待，避免频率过快

        }
    }

    /**
     * 获取文章详情
     * @param  [type] &$item [description]
     * @return [type]        [description]
     */
    public function getContent(&$item)
    {

        if( !isset( $item['url'] ) )
        {
            WxSpider::error('没有url信息 ');
            return;
        }
        
        $data = $this->getData($item['url']);

        $pattern = "/js_content\">(.*?)<\/div>/is";
        if( preg_match($pattern, $data, $matchs) )
        {
            $str = str_replace("data-src", "src", $matchs[1]);
            $item['content'] = trim($str);
            return true;
        }

    }

    /**
     * 保存数据库
     * @param  [type] $item [description]
     * @return [type]       [description]
     */
    private function createData($item)
    {
        file_put_contents('./data', json_encode($item,JSON_UNESCAPED_UNICODE),FILE_APPEND);

        //to save data
    }

    private function getData($url)
    {
        set_time_limit(60);//每次请求，多加60秒等待时间，避免timeout
        $md5 = md5($url);
        $data = WxSpider::getCache($md5);
        if($data)
        {
            return unserialize($data);
        }
        $data = $this->network->get($url);
        WxSpider::setCache($md5, serialize($data));
        return $data;
    }

    public static function error($log)
    {
        $html = '';
          $array =debug_backtrace();
          //print_r($array);//信息很齐全
           unset($array[0]);
           foreach($array as $row)
            {
               $html .=$row['file'].':'.$row['line'].'行,调用方法:'.$row['function']."<p>";
            }
        echo $html;die;
    }

    public static function setCache($key, $data)
    {

        //to set file cache
        return file_put_contents('./cache/'.$key, $data);
    }

    public static function getCache($key)
    {
        //to get cache
        if(is_file('./cache/'.$key))
        {
            return file_get_contents('./cache/'.$key);
        }
        return '';
    }

    public static function clearCache()
    {

    }

}
