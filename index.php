<!DOCTYPE html>

<?php


class CSMemcached extends Memcached 
{
    private $fatalerror;
    private $prefix;
    private $proxyip;
    private $proxyport;
    private $proxyarray;
    private $proxyn;
    private $proxystatus;
    private $proxyindex;
    private $lastok;
    private $haveadd;
    private $setuped;
    private $loopn;
    public function CSMemcached($prefix)
    {
        $this->prefix=$prefix."/";
        
        $proxystr='["127.0.0.1:9001","129.1.1.1:9001","127.0.0.1:9001"]';
        $this->proxyarray=json_decode($proxystr);
        $this->proxyn=count($this->proxyarray);
        if($this->proxyn>=1)
        {
            $this->proxystatus=array(0);
            for($i=0;$i<$this->proxyn-1;$i++)
            {
                array_push($this->proxystatus,0);
            }
            $this->proxyindex=0;
        }      
        else
        {
            $this->proxystatus=null;
            $this->proxyindex=null;
        }
        $this->lastok=false;
        $this->haveadd=false;
        $this->setuped=false;
        if($this->proxyn<1) $this->fatalerror=1;
        parent::__construct();
    }
    public function setup()
    {
        $pa=explode(":",$this->proxyarray[0],3);
        $this->proxyip=$pa[0];
        $this->proxyport=$pa[1];
        $this->proxyindex=0;

        parent::addServer($this->proxyip,$this->proxyport);
        $this->haveadd=true;
        $this->lastok=true;
        $this->setuped=true;       
        $this->setOption(Memcached::OPT_CONNECT_TIMEOUT,100);
    }   
    public function movetonextproxy()
    {
        $this->loopn--;
        if($this->loopn<0) return false;
        else
        {
            $this->proxyindex+=1;
            $this->proxyindex%=$this->proxyn;
            
            $pa=explode(":",$this->proxyarray[$this->proxyindex],3);
            $this->proxyip=$pa[0];
            $this->proxyport=$pa[1];           
            return true;
        }
    }
    public function getResultCode()
    {
        if($this->fatalerror==0)
        {
            return parent::getResultCode();
        }
        else
        {
            return 60;
        }
    }
    public function getResultMessage()
    {
        if($this->fatalerror==0)
        {
            return parent::getResultMessage();
        }
        else
        {
            return "NO PROXY AVAILIBAL";
        }

    }
    public function showproxyarray()
    {
        echo "<br>". "**********proxyarray:".$this->proxyn."***lastok:".$this->lastok."<br>";
        for($i=0;$i<$this->proxyn;$i++)
        {
            echo $this->proxyarray[$i]."    status: ".($this->proxystatus[$i])."<br>";
        }
    }              
    public function showservers()
    {
        echo "<br>". "******************servers**********"."<br>";
        var_dump(parent::getServerList());    
    }   

    public function set($key,$value,$expirition=0)
    {
         if($this->fatalerror!=0)  return false;
         if($this->setuped==false) $this->setup();        
         
         if($this->lastok==true)
         {
              $result=parent::set($this->prefix.$key,$value,$expirition);
              $resultcode=parent::getResultCode();

              if(($resultcode==0)and($result!==null))
              {
                  $this->proxystatus[$this->proxyindex]=0;
                  return $result;                
              }
              else
              {
                  $this->proxystatus[$this->proxyindex]++;
                  $this->proxystatus[$this->proxyindex]%=10000;
                  $this->loopn=$this->proxyn-1;                
              }
         }
         else
         {
              $this->loopn=$this->proxyn;
         } 
              $ok=false;
              while($this->movetonextproxy())
              {
                   parent::resetServerList();
                   parent::addServer($this->proxyip,$this->proxyport);
                   $result=parent::set($this->prefix.$key,$value,$expirition);
                   $resultcode=parent::getResultCode();
                   if(($resultcode==0)and($result!==null))
                   {
                       $ok=true;
                       $this->proxystatus[$this->proxyindex]=0;
                       break;
                   }                  
                   else
                   {
                        $this->proxystatus[$this->proxyindex]++;
                        $this->proxystatus[$this->proxyindex]%=10000; 
                   }                
              }
              if($ok==true)
              {
                   $this->lastok=true;
                   return $result;
              }
              else
              {
                   $this->lastok=false;
                   return false;
              }
               
    }
    public function get($key,$cache_cb=null,$cas_token=0)
    {
         if($this->fatalerror!=0)  return false;
         if($this->setuped==false) $this->setup();        
         
         if($this->lastok==true)
         {
              $result=parent::get($this->prefix.$key,$cache_cb,0);
              $resultcode=parent::getResultCode();
              if((($resultcode==0)or($resultcode==16))and($result!==null))
              {
                  $this->proxystatus[$this->proxyindex]=0;
                  return $result;                
              }
              else
              {
                  $this->proxystatus[$this->proxyindex]++;
                  $this->proxystatus[$this->proxyindex]%=10000;
                  $this->loopn=$this->proxyn-1;                
              }
         }
         else
         {
              $this->loopn=$this->proxyn;
         } 
              $ok=false;
              while($this->movetonextproxy())
              {
                   parent::resetServerList();
                   parent::addServer($this->proxyip,$this->proxyport);
                   $result=parent::get($this->prefix.$key,$cache_cb,0);
                   $resultcode=parent::getResultCode();
                   if((($resultcode==0)or($resultcode==16))and($result!==null))
                   {
                       $ok=true;
                       $this->proxystatus[$this->proxyindex]=0;
                       break;
                   }                  
                   else
                   {
                        $this->proxystatus[$this->proxyindex]++;
                        $this->proxystatus[$this->proxyindex]%=10000; 
                   }                
              }
              if($ok==true)
              {
                   $this->lastok=true;
                   return $result;
              }
              else
              {
                   $this->lastok=false;
                   return false;
              }
               
    }
    public function add($key,$value,$expirition=0)
    {
         if($this->fatalerror!=0)  return false;
         if($this->setuped==false) $this->setup();        
         
         if($this->lastok==true)
         {
              $result=parent::add($this->prefix.$key,$value,$expirition);
              $resultcode=parent::getResultCode();

              if((($resultcode==0)or($resultcode==14))and($result!==null))
              {
                  $this->proxystatus[$this->proxyindex]=0;
                  return $result;                
              }
              else
              {
                  $this->proxystatus[$this->proxyindex]++;
                  $this->proxystatus[$this->proxyindex]%=10000;
                  $this->loopn=$this->proxyn-1;                
              }
         }
         else
         {
              $this->loopn=$this->proxyn;
         } 
              $ok=false;
              while($this->movetonextproxy())
              {
                   parent::resetServerList();
                   parent::addServer($this->proxyip,$this->proxyport);
                   $result=parent::add($this->prefix.$key,$value,$expirition);
                   $resultcode=parent::getResultCode();
                   if((($resultcode==0)or($resultcode==14))and($result!==null))
                   {
                       $ok=true;
                       $this->proxystatus[$this->proxyindex]=0;
                       break;
                   }                  
                   else
                   {
                        $this->proxystatus[$this->proxyindex]++;
                        $this->proxystatus[$this->proxyindex]%=10000; 
                   }                
              }
              if($ok==true)
              {
                   $this->lastok=true;
                   return $result;
              }
              else
              {
                   $this->lastok=false;
                   return false;
              }
               
    }
    public function delete($key,$time=0)
    {
         if($this->fatalerror!=0)  return false;
         if($this->setuped==false) $this->setup();        
         
         if($this->lastok==true)
         {
              $result=parent::delete($this->prefix.$key,$time);
              $resultcode=parent::getResultCode();
              if((($resultcode==0)or($resultcode==16))and($result!==null))
              {
                  $this->proxystatus[$this->proxyindex]=0;
                  return $result;                
              }
              else
              {
                  $this->proxystatus[$this->proxyindex]++;
                  $this->proxystatus[$this->proxyindex]%=10000;
                  $this->loopn=$this->proxyn-1;                
              }
         }
         else
         {
              $this->loopn=$this->proxyn;
         } 
              $ok=false;
              while($this->movetonextproxy())
              {
                   parent::resetServerList();
                   parent::addServer($this->proxyip,$this->proxyport);
                   $result=parent::delete($this->prefix.$key,$time);
                   $resultcode=parent::getResultCode();
                   if((($resultcode==0)or($resultcode==16))and($result!==null))
                   {
                       $ok=true;
                       $this->proxystatus[$this->proxyindex]=0;
                       break;
                   }                  
                   else
                   {
                        $this->proxystatus[$this->proxyindex]++;
                        $this->proxystatus[$this->proxyindex]%=10000; 
                   }                
              }
              if($ok==true)
              {
                   $this->lastok=true;
                   return $result;
              }
              else
              {
                   $this->lastok=false;
                   return false;
              }
               
    }
}


$csm=new CSMemcached("0001");
$csm->showproxyarray();
echo "1111111"."<br>";
var_dump($csm->delete("ayya",0))."<br>";
$csm->showproxyarray();
echo $csm->getResultMessage()."<br>";
echo "<br>";

echo "22222","<br>";
var_dump($csm->add("addua",null,0))."<br>";
$csm->showproxyarray();
echo $csm->getResultMessage()."<br>";
echo "<br>";

echo "33333"."<br>";
var_dump($csm->set("foo2",8888))."<br>";
$csm->showproxyarray();
echo $csm->getResultMessage()."<br>";
echo "<br>";

echo "44444"."<br>";
var_dump($csm->get("foo2"))."<br>";
$csm->showproxyarray();
echo $csm->getResultMessage()."<br>";
echo "<br>";

echo "55555"."<br>";
var_dump($csm->set("foo2",8888))."<br>";
$csm->showproxyarray();
echo $csm->getResultMessage()."<br>";
echo "<br>";

echo "66666"."<br>";
var_dump($csm->set("foo2",8888))."<br>";
$csm->showproxyarray();
echo $csm->getResultMessage()."<br>";
echo "<br>";



$csm->showservers();

?>


