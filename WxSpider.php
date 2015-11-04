<?php
namespace app\utils\sogoWx;
use app\utils\network\Network;

/**
 * WxSpider is get wx pages fron sogo search
 */
class WxSpider{

    private $network;
    function __construct()
    {
        $this->network = new Network();
        $this->network->setOption(CURLOPT_HEADER, true);
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

        $pattern = "/onclick=\"gotourl\(\'(.*?)\'/is";//获取公众账号的文字列表地址
        if(preg_match_all($pattern, $data, $matches))
        {
            foreach ($matches[1] as $key => $value)
            {
                $this->toNext = false;
                $pattern = "/openid=(.*?)&amp;ext=(.*)/i";//得到openid以及ext
                if(preg_match($pattern, $value,$infos))
                {
                    $this->getPageList($infos[1], $infos[2], 1);
                }
                else
                {
                    WxSpider::error('not get openid info '.$value);
                }
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
    public function getPageList($openid, $ext, $page)
    {

        $url = "http://weixin.sogou.com/gzhjs?cb=sogou.weixin.gzhcb&openid=%s&ext=%s&gzhArtKeyWord=&page=%d&t=%s";

        list($t1, $t2) = explode(' ', microtime());
        $time = (float)sprintf('%.0f',(floatval($t1)+floatval($t2))*1000);//毫秒时间戳

        $url = sprintf($url,$openid,$ext,$page,$time);
        $pattern = "/sogou\.weixin\.gzhcb\((\{.*?\]\})\)/is";
        $data = $this->getData($url);
        if(preg_match_all($pattern, $data, $matchs))
        {
            if( isset($matchs[1]) && isset($matchs[1][0]) )
            {
                $data = json_decode($matchs[1][0],true);

                $this->getItems($data['items']);
                if($data['totalPages'] > $data['page'])
                {
                    $this->getPageList($openid,$ext, $data['page']+1);
                }
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

        //解析列表xml
        foreach($data as $item)
        {

            $xml = simplexml_load_string(iconv('utf-8',"gb2312//IGNORE",$item),'SimpleXMLElement', LIBXML_NOCDATA);
            if(!$xml)
            {
                WxSpider::error('xml数据错误 ');
                continue;
            }
            if(!$xml->item->display)
            {
                WxSpider::error('xml数据错误 ');
                continue;
            }
            $xml = (array)$xml->item->display;

            $item = [];

            $item = [
                'docid'=>$xml['docid'],
                'title'=>$xml['title'],
                'url'=>$xml['url'],
                'imglink'=>$xml['imglink'],
                'contentSort'=>$xml['content168'],
                'sourcename'=>$xml['sourcename']
            ];
            if( !$this->getContent($item) )
            {
                WxSpider::error('获取内容错误 ');
                continue;
            }

            $this->createData($item);
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
        $url = "http://weixin.sogou.com".$item['url'];
        $data = $this->getData($url);

        $pattern = "/js_content\">(.*?)<\/div>/is";
        if( preg_match($pattern, $data, $matchs) )
        {
            $str = str_replace("data-src", "src", $matchs[1]);
            $item['content'] = $str;
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
        var_dump($item);die;
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
