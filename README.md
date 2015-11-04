## sogo 微信文章抓取

### 目录列表

    \---|
        |---sample.php 使用实例
        |---WxSpider.php 数据抓取主要类
        |---network
                |--Network.php php curl 请求类
        |---cache 数据缓存目录，方便调试的时候多次被封


### 使用

```
require('./network/Network.php');
require('./WxSpider.php');

use app\utils\sogoWx\WxSpider;

$wxSpider = new WxSpider();
$wxSpider->getAccounts( '新华社');

```

数据保存部分写在WxSpider 的createData里面，逻辑自行实现。只提供一个抓取部分

#### 注意

sogo 如果多次请求是会被封的！！！应该是更具ip和客户端里植入cookie以及客户端信息
判断的。因为我php curl被封之后，我还是能通过浏览器访问。但是我到现在还不知道具
体通过什么方式判断是机器爬取，如果有人知道，请告诉我！！！！
