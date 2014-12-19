<!DOCTYPE html>

<?php


class Sinamcp extends Memcached 
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
    public $proxycachepath;
    public $proxycachename;
    public $eliminatetime;
    public $updateproxy;
    public function Sinamcp($prefix,$path="/tmp/sina/proxycache.txt",$eliminatetime=100)
    {
        $this->prefix=$prefix."/";
        $pathinfo=pathinfo($path);
        if(($pathinfo['dirname']==".")or($pathinfo['dirname']==""))
        {
            $this->proxycachepath=getcwd()."/";
        }
        elseif($pathinfo['dirname']=="/")
        {
            $this->proxycachepath="/";
        }
        else
        {
            $this->proxycachepath=$pathinfo['dirname']."/";
        }
        $this->proxycachename=$pathinfo['basename'];
        if(file_exists($this->proxycachepath.$this->proxycachename))
        {
              
              if((time()-filemtime($this->proxycachepath.$this->proxycachename))<$this->eliminatetime)
              {
                   $this->updateproxy=false;
              } 
              else
              {
                   $this->updateproxy=true;
              }
        }      
        else
        {
             if(!file_exists($this->proxycachepath))
             {
                 if(!mkdir($this->proxycachepath,0777))
                 {
                     echo "con not creat path:".$this->proxycachepalth."check your permission<br>";
                     exit;
                 }
             }
             $this->updateproxy=true;
        }
        
        if($this->updateproxy==true)
        {
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
             $handle=fopen($this->proxycachepath.$this->proxycachename,"w");
             if($handle===false)
             {
                echo "can not open file:".$this->proxycachepath.$this->proxycachename." .check your permission.<br>";
                exit;
             }
             fwrite($handle,$proxystr);
             fclose($handle);
             clearstatcache();
        }
        else
        {
             $handle=fopen($this->proxycachepath.$this->proxycachename,"r");
             if($handle===false)
             {
                echo "can not open file:".$this->proxycachepath.$this->proxycachename.".check your permission.<br>";
             }
             $proxystr=fread($handle,1000000);
             fclose($handle);
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

        if(($this->logf=fopen("log.txt","a"))===null)
        {
             $this->fatalerror=2;
        }
        parent::__construct();
    }
    public function setup()
    {
        $randomindex=rand(0,$this->proxyn-1);
        $pa=explode(":",$this->proxyarray[$randomindex],3);
        $this->proxyip=$pa[0];
        $this->proxyport=$pa[1];
        $this->proxyindex=$randomindex;

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
    public function get($key,$cache_cb=null,&$cas_token=null)
    {
         if($this->fatalerror!=0)  return false;
         if($this->setuped==false) $this->setup();        
         $this->reqmethod="get";
         
         if($this->lastok==true)
         {
              $result=parent::get($this->prefix.$key,$cache_cb,$cas_token);
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
                   $result=parent::get($this->prefix.$key,$cache_cb,$cas_token);
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
    public function getMulti($keys,&$cas_token=null,$flags=0)
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
                  if(count($cas_token)==0) {}
                  else $cas_token=$this->deprefix($cas_token);
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
                   if(count($cas_token)==0) {}
                   else $cas_token=$this->deprefix($cas_token);
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
?>


