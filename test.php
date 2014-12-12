<?php
$m=new Memcached();
$m->addServer("127.0.0.1",7001);
var_dump($m->add("0001/a",999));
echo "result code: ".$m->getResultCode()."<br>";
echo "result message: ".$m->getResultMessage()."<br>";
?>
