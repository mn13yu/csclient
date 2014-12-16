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
    private $logf;    // file handler
    private $reqmethod;
    public function CSMemcached($prefix)
    {
        $this->prefix=$prefix."/";
        
        $page=file_get_contents("http://127.0.0.1:4001/v2/keys/proxy");
        if($page===false)
        {
            $this->fatalerror=2;
            $proxystr='[]';
        }       
        else
        {
            $obj=json_decode($page);
            $proxystr=$obj->node->value;
        }
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
        if(($this->logf=fopen("/root/myweb/log.txt","w"))===null)
        {
             $this->fatalerror=2;
        }
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
        $this->setOption(Memcached::OPT_COMPRESSION,false);
    }   
    public function prefixmulti($items)
    {
        $prefixitems=array();
        foreach($items as $key=>$value)
        {
            $prefixitems[$this->prefix.$key]=$value;
        }     
        return $prefixitems;
    }
    public function prefixkeys($keys)
    {
        $prefixkeys=array();
        foreach($keys as $key)
        {
            array_push($prefixkeys,$this->prefix.$key);
        } 
        return $prefixkeys;
    }
    public function deprefix($array)
    {
        $deprefix=array();
        foreach($array as $key=>$value)
        {
            $pa=explode("/",$key,3);
            $deprefix[$pa[1]]=$value;
        }
        return $deprefix;
    }
    public function log()
    {
        $status="";
        if($this->lastok==true) $status="success";
        else                    $status="failure";
        $message=date("H:i:s: ").time()." ".$this->reqmethod." ".$status." ".$this->proxyip." ".$this->proxyport."\n";
        fwrite($this->logf,$message);
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
         $this->reqmethod="set";
         
         if($this->lastok==true)
         {
              $result=parent::set($this->prefix.$key,$value,$expirition);
              $resultcode=parent::getResultCode();

              if(($resultcode==0)and($result!==null))
              {
                  $this->proxystatus[$this->proxyindex]=0;
                  $this->log();
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
                   $this->log();
                   return $result;
              }
              else
              {
                   $this->lastok=false;
                   $this->log();
                   return $result;
              }
               
    }
    public function setMulti($items,$expirition=0)
    {
         if($this->fatalerror!=0)  return false;
         if($this->setuped==false) $this->setup();        
         $this->reqmethod="stm";
         
         if($this->lastok==true)
         {
              $result=parent::setMulti($this->prefixmulti($items),$expirition);
              $resultcode=parent::getResultCode();

              if(($resultcode==0)and($result!==null))
              {
                  $this->proxystatus[$this->proxyindex]=0;
                  $this->log();
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
                   $result=parent::setMulti($this->prefixmulti($items),$expirition);
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
                   $this->log();
                   return $result;
              }
              else
              {
                   $this->lastok=false;
                   $this->log();
                   return $result;
              }
               
    }
    public function replace($key,$value,$expirition=0)
    {
         if($this->fatalerror!=0)  return false;
         if($this->setuped==false) $this->setup();        
         $this->reqmethod="rep";
         
         if($this->lastok==true)
         {
              $result=parent::replace($this->prefix.$key,$value,$expirition);
              $resultcode=parent::getResultCode();

              if((($resultcode==0)or($resultcode==14))and($result!==null))
              {
                  $this->proxystatus[$this->proxyindex]=0;
                  $this->log();
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
                   $result=parent::replace($this->prefix.$key,$value,$expirition);
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
                   $this->log();
                   return $result;
              }
              else
              {
                   $this->lastok=false;
                   $this->log();
                   return $result;
              }
               
    }
    public function get($key,$cache_cb=null,$cas_token=0)
    {
         if($this->fatalerror!=0)  return false;
         if($this->setuped==false) $this->setup();        
         $this->reqmethod="get";
         
         if($this->lastok==true)
         {
              $result=parent::get($this->prefix.$key,$cache_cb,0);
              $resultcode=parent::getResultCode();

              if((($resultcode==0)or($resultcode==16))and($result!==null))
              {
                  $this->proxystatus[$this->proxyindex]=0;
                  $this->log();
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
                   $this->log();
                   return $result;
              }
              else
              {
                   $this->lastok=false;
                   $this->log();
                   return $result;
              }
               
    }
    public function getMulti($keys,$cas_token=0,$flags=0)
    {
         if($this->fatalerror!=0)  return false;
         if($this->setuped==false) $this->setup();        
         $this->reqmethod="gtm";
         
         if($this->lastok==true)
         {
              $result=parent::getMulti($this->prefixkeys($keys),$cas_token,$flags);
              $resultcode=parent::getResultCode();

              if(($resultcode==0)and($result!==null))
              {
                  $this->proxystatus[$this->proxyindex]=0;
                  $this->log();
                  return $this->deprefix($result);                
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
                   $result=parent::getMulti($this->prefixkeys($keys),$cas_token,$flags);
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
                   $this->log();
                   return $this->deprefix($result);
              }
              else
              {
                   $this->lastok=false;
                   $this->log();
                   return $result;
              }
               
    }
    public function add($key,$value,$expirition=0)
    {
         if($this->fatalerror!=0)  return false;
         if($this->setuped==false) $this->setup();        
         $this->reqmethod="add";
         
         if($this->lastok==true)
         {
              $result=parent::add($this->prefix.$key,$value,$expirition);
              $resultcode=parent::getResultCode();

              if((($resultcode==0)or($resultcode==14))and($result!==null))
              {
                  $this->proxystatus[$this->proxyindex]=0;
                  $this->log();
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
                   $this->log();
                   return $result;
              }
              else
              {
                   $this->lastok=false;
                   $this->log();
                   return $result;
              }
               
    }
    public function delete($key,$time=0)
    {
         if($this->fatalerror!=0)  return false;
         if($this->setuped==false) $this->setup();        
         $this->reqmethod="del";
         
         if($this->lastok==true)
         {
              $result=parent::delete($this->prefix.$key,$time);
              $resultcode=parent::getResultCode();
              if((($resultcode==0)or($resultcode==16))and($result!==null))
              {
                  $this->proxystatus[$this->proxyindex]=0;
                  $this->log();
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
                   $this->log();
                   return $result;
              }
              else
              {
                   $this->lastok=false;
                   $this->log();
                   return $result;
              }
               
    }
    public function append($key,$value)
    {
         if($this->fatalerror!=0)  return false;
         if($this->setuped==false) $this->setup();        
         $this->reqmethod="app";
         
         if($this->lastok==true)
         {
              $result=parent::append($this->prefix.$key,$value);
              $resultcode=parent::getResultCode();

              if((($resultcode==0)or($resultcode==14))and($result!==null))
              {
                  $this->proxystatus[$this->proxyindex]=0;
                  $this->log();
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
                   $result=parent::append($this->prefix.$key,$value);
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
                   $this->log();
                   return $result;
              }
              else
              {
                   $this->lastok=false;
                   $this->log();
                   return $result;
              }
               
    }
    public function prepend($key,$value)
    {
         if($this->fatalerror!=0)  return false;
         if($this->setuped==false) $this->setup();        
         $this->reqmethod="pre";
         
         if($this->lastok==true)
         {
              $result=parent::prepend($this->prefix.$key,$value);
              $resultcode=parent::getResultCode();

              if((($resultcode==0)or($resultcode==14))and($result!==null))
              {
                  $this->proxystatus[$this->proxyindex]=0;
                  $this->log();
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
                   $result=parent::prepend($this->prefix.$key,$value);
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
                   $this->log();
                   return $result;
              }
              else
              {
                   $this->lastok=false;
                   $this->log();
                   return $result;
              }
               
    }
    public function decrement($key,$offset=1,$initial_value=0,$expiry=0)
    {
         if($this->fatalerror!=0)  return false;
         if($this->setuped==false) $this->setup();        
         $this->reqmethod="dec";
         
         if($this->lastok==true)
         {
              $result=parent::decrement($this->prefix.$key,$offset=1);//,$initial_value=0,$expiry=0);
              $resultcode=parent::getResultCode();

              if((($resultcode==0)or($resultcode==16))and($result!==null))
              {
                  $this->proxystatus[$this->proxyindex]=0;
                  $this->log();
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
                   $result=parent::decrement($this->prefix.$key,$offset=1);//,$initial_value=0,$expiry=0);
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
                   $this->log();
                   return $result;
              }
              else
              {
                   $this->lastok=false;
                   $this->log();
                   return $result;
              }
               
    }
    public function increment($key,$offset=1,$initial_value=0,$expiry=0)
    {
         if($this->fatalerror!=0)  return false;
         if($this->setuped==false) $this->setup();        
         $this->reqmethod="inc";
         
         if($this->lastok==true)
         {
              $result=parent::increment($this->prefix.$key,$offset=1);//,$initial_value=0,$expiry=0);
              $resultcode=parent::getResultCode();

              if((($resultcode==0)or($resultcode==16))and($result!==null))
              {
                  $this->proxystatus[$this->proxyindex]=0;
                  $this->log();
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
                   $result=parent::increment($this->prefix.$key,$offset=1);//,$initial_value=0,$expiry=0);
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
                   $this->log();
                   return $result;
              }
              else
              {
                   $this->lastok=false;
                   $this->log();
                   return $result;
              }
               
    }
    public function touch($key,$expirition=0)
    {
         if($this->fatalerror!=0)  return false;
         if($this->setuped==false) $this->setup();        
         $this->reqmethod="tou";
         
         if($this->lastok==true)
         {
              $result=parent::touch($this->prefix.$key,$expirition);
              $resultcode=parent::getResultCode();

              if((($resultcode==0)or($resultcode==16))and($result!==null))
              {
                  $this->proxystatus[$this->proxyindex]=0;
                  $this->log();
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
                   $result=parent::touch($this->prefix.$key,$expirition);
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
                   $this->log();
                   return $result;
              }
              else
              {
                   $this->lastok=false;
                   $this->log();
                   return $result;
              }
               
    }
}

/*
$csm=new CSMemcached("0001");
$csm->showproxyarray();
echo "1111111"."<br>";
var_dump($csm->setMulti(array("a"=>111,"b"=>222,"c"=>333)))."<br>";
$csm->showproxyarray();
echo $csm->getResultMessage()."<br>";
echo "<br>";

echo "22222","<br>";
var_dump($csm->getMulti(array("a","b","c")));
$csm->showproxyarray();
echo $csm->getResultMessage()."<br>";
echo "<br>";

echo "33333"."<br>";
var_dump($csm->get("b"))."<br>";
$csm->showproxyarray();
echo $csm->getResultMessage()."<br>";
echo "<br>";

echo "44444"."<br>";
var_dump($csm->get("c"))."<br>";
$csm->showproxyarray();
echo $csm->getResultMessage()."<br>";
echo "<br>";

echo "55555"."<br>";
var_dump($csm->get("foo2"))."<br>";
$csm->showproxyarray();
echo $csm->getResultMessage()."<br>";
echo "<br>";

echo "66666"."<br>";
var_dump($csm->set("foo2",8888))."<br>";
$csm->showproxyarray();
echo $csm->getResultMessage()."<br>";
echo "<br>";

echo "begin loop"."<br>";
for($i=0;$i<0;$i++)
{
    $csm->set("foo3",9999);
    sleep(1);
}

$csm->showservers();
*/
?>


