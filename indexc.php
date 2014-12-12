<!DOCTYPE html>

<?php
echo var_dump($m=new Memcached())."<br>";
echo var_dump($m=new Memcached())."<br>";
echo var_dump($m)."<br>";
echo var_dump($m->addserver("127.0.0.1",8001));
echo "<br>";
//var_dump($m->addserver("127.0.0.1",7000));
//echo "<br>";
//var_dump($m->addserver(0,1));
//echo "<br>";
//var_dump($m->addserver(0,1));
echo "<br>";
$servers=array(
         array("127.0.0.1",8001),
         array("127.0.0.1",7000)
);
//echo var_dump($m->addServers($servers))." ddd"."<br>";
$servers=$m->getServerList();
if(is_array($servers))
{
    foreach($servers as $server)
    {
        echo $server['host']."   ".$server['port']."<br>";
    }
}
$m->setOption(Memcached::OPT_COMPRESSION, false);
$m->setOption(Memcached::OPT_CONNECT_TIMEOUT,100);

echo "+++++++++++++++++++++++++++"."<br>";
$sa=array(
     "0001/a" => 123,
     "0001/b" => 234
);
echo var_dump($m->setMulti($sa),2)."<br>";
echo var_dump($m->getResultCode())."<br>";
echo var_dump($m->getResultMessage())."<br>";


echo "<br>";
echo var_dump($m->getDelayed(array("0001/a","0001/b")))."<br>";
echo var_dump($m->getResultCode())."<br>";
echo var_dump($m->getResultMessage())."<br>";

while($result=$m->fetch())
{
   var_dump($result);
}

echo "<br>";
echo "<br>";
echo var_dump($m->deleteMulti(array("0001/ac","0001/bc")))."<br>";
echo var_dump($m->getResultCode())."<br>";
echo var_dump($m->getResultMessage())."<br>";


echo "<br>";
echo var_dump($m->get("0001/a"))."<br>";
echo var_dump($m->getResultCode())."<br>";
echo var_dump($m->getResultMessage())."<br>";

/*
$fp = fsockopen("127.0.0.1",
 80, $errno, $errstr, 30);   
if (!$fp) 
{   
echo "$errstr ($errno)<br />\n";   
} else {   
$out = "GET / HTTP/1.1\r\n";   
$out .= "Host: 127.0.0.1\r\n";   
$out .= "Connection: Close\r\n\r\n";   
 
fwrite($fp, $out);   
while (!feof($fp)) {   
echo fgets($fp, 128);   
}   
fclose($fp);   
}  */
/*
   class MyMemcached
   {
      var $memcached;
      var $dsip;
      var $dsport;
      var $preid;
      public function MyMemcached($did)
      {
          echo init.$did;
          echo "<br>";
          $this->memcached=new Memcached();
          $this->preid=$did;
          ////////////////////////
          $this->dsip="127.0.0.1" ;
          $this->dsport=7000  ;
          ////////////////////////
          $this->memcached->addServer("127.0.0.1",7000);

          echo init.($this->preid);
          echo "<br>";
      }

      public function set($key,$value)
      {
          echo infuction;
          echo "<br>";
          echo ($this->preid).$key;
          echo "<br>";
          return $this->memcached->set(($this->preid).$key,$value);
      }
      public function get($key)
      {
          return $this->memcached->get(($this->preid).$key);
      }

   }

$m=new MyMemcached("0001/");
$m->set("test",1234555555577888777);
var_dump($m->get("test"));
echo $m->get("test");
echo ok;

//var_dump($m->memcached->get("0001/foo");

/*
$m=new Memcached();

$m->addServer('127.0.0.1',7000);

$preid="0001/";
$clinetkey="foo";
$key=$preid.$clinetkey;
$m->set($key,"wwww");
var_dump($m->get($key));
*/
/*
$m->add('akey',12345);
$val=$m->get('akey');
echo $val;
echo "<br>";

$m->replace('akey',555);
$val=$m->get('akey');
echo $val;
echo "<br>";

$val=$m->get('kk');
echo $val;
echo var_dump($val);
echo "<br>";
/*
 */
 //echo phpinfo();
 // $homepage = file_get_contents('http://127.0.0.1');
 // echo $homepage;
/*
  echo "<br>";
  $ipstr='["127.0.0.1:8001","127.0.0.1:8002"]';
  echo var_dump($ipstr);
  echo "<br>";
  $ob=json_decode($ipstr);
  echo var_dump($ob);
  echo "<br>";
  
  $query = "127.0.0.1:8001";

  $pa=explode(":",$query);
  
  echo $pa[0];
  echo "<br>";
  echo $pa[1];
   */
?>


