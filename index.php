<?php
namespace app;
require('./network/Network.php');
require('./WxSpider.php');

use app\utils\sogoWx\WxSpider;

$wxSpider = new WxSpider();
$wxSpider->getAccounts( '华德福');
?>
