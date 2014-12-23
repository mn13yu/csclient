<!DOCTYPE html>
<?php
class Sinamcp extends Memcached
{
    private $prefix = "";
    public $proxys = array();
    private $proxyCount = 0;
    private $index = -1;
    private $expiration = 20;
    private $path = "";
    private $error = 0;

    public function __construct($prefix, $expiration=20, $path="/tmp/cache.json")
    {
        $this->prefix = $prefix . "/";
        $this->expiration = $expiration;
        $this->path = $path;
        $proxyStr = $this->getProxyStr($expiration, $path);
        $this->initProxys( $proxyStr);
        parent::__construct();
        $this->setOption(Memcached::OPT_CONNECT_TIMEOUT,100);
        $this->setOption(Memcached::OPT_COMPRESSION,false);
        if($this->proxyCount >= 1)
        {
            $this->connectNext();
        }
    }
    public function initProxys( $proxyStr)
    {
        $proxyArray = json_decode( $proxyStr);
        for ($i = 0; $i<count($proxyArray); $i++)
        {
            $parts = explode(":", $proxyArray[$i], 3);
            array_push( $this->proxys, array("ip"=>$parts[0], "port"=>$parts[1]));
        }
        $this->proxyCount = count($this->proxys);
        if($this->proxyCount < 1)
        {
            $this->error = 2;
        }
    }
    public function getProxyStr($expiration, $path)
    {
        $proxyStr = "";
        if( $handle = fopen($path, "a+"))
        {
            $needUpdate = true;
            fseek($handle, 0);
            $contents = fread( $handle, 100000);
            if($contents != "")
            {
                $obj = json_decode($contents);
                if( (time() - $obj->updateTime) < $expiration )
                {
                    $needUpdate = false;
                    $proxyStr = $obj->proxyStr;
                }
                else
                {
                    fclose($handle);
                    $handle = fopen($path, "w");
                }
            }
            if($needUpdate == true)
            {
                $page = file_get_contents("http://127.0.0.1:4001/v2/keys/proxy");
                $obj = json_decode( $page );
                fseek($handle, 0);
                fwrite( $handle, json_encode( array("updateTime"=>time(), "proxyStr"=>$obj->node->value)));
                $proxyStr = $obj->node->value;
            }
            fclose($handle);
        }
        else
        {
            echo "can not open file:" . $path . "<br>";
            $this->error = 1;
        }
        return $proxyStr;
    }
    public function connectNext ()
    {
        $this->index = ($this->index + 1) % $this->proxyCount;
        parent::resetServerList();
        parent::addServer($this->proxys[$this->index]["ip"], $this->proxys[$this->index]["port"]);
    }
    public function prefixMulti($items)
    {
        $prefixItems = array();
        foreach($items as $key=>$value)
        {
            $prefixItems[$this->prefix . $key] = $value;
        }
        return $prefixItems;
    }
    public function prefixKeys($keys)
    {
        $prefixKeys = array();
        foreach($keys as $key)
        {
            array_push($prefixKeys, $this->prefix . $key);
        }
        return $prefixKeys;
    }
    public function dePrefix($array)
    {
        $dePrefix = array();
        foreach($array as $key=>$value)
        {
            $parts = explode ("/", $key, 3);
            $dePrefix[ $parts[1] ] = $value;
        }
        return $dePrefix;
    }
    public function set($key, $value, $expiration = 0)
    {
        if($this->error != 0)
        {
            return false;
        }
        for($i = 0; $i < $this->proxyCount; $i++)
        {
            $result = parent::set($this->prefix . $key, $value, $expiration);
            $code = parent::getResultCode();
            if( ($code == 0) and ($result !== null) )
            {
                break;
            }
            if($i < $this->proxyCount - 1)
            {
                $this->connectNext();
            }
        }
        return $result;
    }
    public function setMulti($items, $expiration = 0)
    {
        if($this->error != 0)
        {
            return false;
        }
        for($i = 0; $i < $this->proxyCount; $i++)
        {
            $result = parent::setMulti($this->prefixMulti($items), $expiration);
            $code = parent::getResultCode();
            if( ($code == 0) and ($result !== null) )
            {
                break;
            }
            if($i < $this->proxyCount - 1)
            {
                $this->connectNext();
            }
        }
        return $result;
    }
    public function replace($key, $value, $expiration = 0)
    {
        if($this->error != 0)
        {
            return false;
        }
        for($i=0; $i < $this->proxyCount; $i++)
        {
            $result = parent::replace($this->prefix . $key, $value, $expiration);
            $code = parent::getResultCode();
            if( (($code == 0) or ($code == 14)) and ($result !== null) )
            {
                break;
            }
            if($i < $this->proxyCount - 1)
            {
                $this->connectNext();
            }
        }
        return $result;
    }
    public function get($key, $cache_cb = null, &$cas_token = null)
    {
        if($this->error !=0)
        {
            return false;
        }
        for($i = 0; $i < $this->proxyCount; $i++)
        {
            $result = parent::get($this->prefix . $key);//, $cache_cb, $cas_token);
            $code = parent::getResultCode();
            if( (($code == 0) or ($code == 16)) and ($result !== null))
            {
                break;
            }
            if($i < $this->proxyCount - 1)
            {
                $this->connectNext();
            }
        }
        return $result;
    }
    public function getMulti($keys, &$cas_token=null, $flags = 0)
    {
        if($this->error != 0)
        {
            return false;
        }
        for($i = 0; $i < $this->proxyCount; $i++)
        {
            $result = parent::getMulti($this->prefixKeys($keys));//, $cas_token, $flags);
            $code = parent::getResultCode();
            if( ($code == 0) and ($result !== null))
            {
                return $this->dePrefix( $result );
            }
            if($i < $this->proxyCount - 1)
            {
                $this->connectNext();
            }
        }
        return $result;
    }
    public function add($key, $value, $expiration = 0)
    {
        if($this->error != 0)
        {
            return false;
        }
        for($i = 0; $i < $this->proxyCount; $i++)
        {
            $result = parent::add($this->prefix . $key, $value, $expiration);
            $code = parent::getResultCode();
            if( (($code == 0) or ($code == 14)) and ($result !== null) )
            {
                break;
            }
            if($i < $this->proxyCount - 1)
            {
                $this->connectNext();
            }
        }
        return $result;
    }
    public function delete($key, $time = 0)
    {
        if($this->error != 0)
        {
            return false;
        }
        for($i = 0; $i < $this->proxyCount; $i++)
        {
            $result = parent::delete($this->prefix . $key, $time);
            $code = parent::getResultCode();
            if( (($code == 0) or ($code == 16)) and ($result !== null) )
            {
                break;
            }
            if($i < $this->proxyCount - 1)
            {
                $this->connectNext();
            }
        }
        return $result;
    }
    public function append($key, $value)
    {
        if($this->error != 0)
        {
            return false;
        }
        for($i = 0; $i < $this->proxyCount; $i++)
        {
            $result = parent::append($this->prefix . $key, $value);
            $code = parent::getResultCode();
            if( (($code == 0) or ($code == 14)) and ($result !== null) )
            {
                break;
            }
            if($i < $this->proxyCount - 1)
            {
                $this->connectNext();
            }
        }
        return $result;
    }
    public function prepend($key, $value)
    {
        if($this->error != 0)
        {
            return false;
        }
        for($i = 0; $i < $this->proxyCount; $i++)
        {
            $result = parent::prepend($this->prefix . $key, $value);
            $code = parent::getResultCode();
            if( (($code == 0) or ($code == 14)) and ($result !== null) )
            {
                break;
            }
            if($i < $this->proxyCount - 1)
            {
                $this->connectNext();
            }
        }
        return $result;
    }
    public function decrement($key, $offset = 1, $initial_value = 0, $expiry = 0)
    {
        if($this->error != 0)
        {
            return false;
        }
        for($i = 0; $i < $this->proxyCount; $i++)
        {
            $result = parent::decrement($this->prefix . $key, $offset = 1);
            $code = parent::getResultCode();
            if( (($code == 0) or ($code == 16)) and ($result !== null) )
            {
                break;
            }
            if($i < $this->proxyCount - 1)
            {
                $this->connectNext();
            }
        }
        return $result;
    }
    public function increment($key, $offset = 1, $initial_value = 0, $expiry = 0)
    {
        if($this->error != 0)
        {
            return false;
        }
        for($i = 0; $i < $this->proxyCount; $i++)
        {
            $result = parent::increment($this->prefix . $key, $offset = 1);
            $code = parent::getResultCode();
            if( (($code == 0) or ($code ==16)) and ($result !== null) )
            {
                break;
            }
            if($i < $this->proxyCount - 1)
            {
                $this->connectNext();
            }
        }
        return $result;
    }
    public function touch($key, $expiration = 0)
    {
        if($this->error != 0)
        {
            return false;
        }
        for($i = 0; $i < $this->proxyCount; $i++)
        {
            $result = parent::touch($this->prefix . $key, $expiration);
            $code = parent::getResultCode();
            if( (($code ==0 ) or ($code == 16)) and ($result !== null) )
            {
                break;
            }
            if($i < $this->proxyCount - 1)
            {
                $this->connectNext();
            }
        }
        return $result;
    }
    public function getResultCode()
    {
        if($this->error != 0)
        {
            return 60;
        }
        else
        {
            return parent::getResultCode();
        }
    }
    public function getResultMessage()
    {
        if($this->error != 0)
        {
            return "NO PORXY AVAILABLE"; 
        }
        else
        {
            return parent::getResultMessage();
        }
    }
}

