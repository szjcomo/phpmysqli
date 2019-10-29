<?php
/**
 * |-----------------------------------------------------------------------------------
 * @Copyright (c) 2014-2018, http://www.sizhijie.com. All Rights Reserved.
 * @Website: www.sizhijie.com
 * @Version: 思智捷信息科技有限公司
 * @Author : szjcomo 
 * |-----------------------------------------------------------------------------------
 */

namespace szjcomo\mysqli;

use EasySwoole\Spl\SplBean;

class Config extends SplBean 
{
    protected $host;
    protected $user;
    protected $password;
    protected $database;//数据库
    protected $port = 3306;
    protected $timeout = 30;
    protected $charset = 'utf8';
    protected $prefix = '';
    protected $debug = false;
    protected $strict_type =  false; //开启严格模式，返回的字段将自动转为数字类型
    protected $fetch_mode = false;//开启fetch模式, 可与pdo一样使用fetch/fetchAll逐行
    protected $maxReconnectTimes = 3;


    public function getPrefix()
    {
        return $this->prefix;
    }

    public function setPrefix($prefix):void
    {
        $this->prefix = $prefix;
    }
    /**
     * [getDebug 获取是否开启debug模式]
     * @Author   szjcomo
     * @DateTime 2019-10-22
     * @return   [type]     [description]
     */
    Public function getDebug()
    {
        return $this->debug;
    }

    /**
     * [setDebug debug信息]
     * @Author   szjcomo
     * @DateTime 2019-10-22
     * @param    [type]     $debug [description]
     */
    public function setDebug($debug):void
    {
        $this->debug = $debug;
    }

    /**
     * @return mixed
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * @param mixed $host
     */
    public function setHost($host): void
    {
        $this->host = $host;
    }

    /**
     * @return mixed
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param mixed $user
     */
    public function setUser($user): void
    {
        $this->user = $user;
    }

    /**
     * @return mixed
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @param mixed $password
     */
    public function setPassword($password): void
    {
        $this->password = $password;
    }

    /**
     * @return mixed
     */
    public function getDatabase()
    {
        return $this->database;
    }

    /**
     * @param mixed $database
     */
    public function setDatabase($database): void
    {
        $this->database = $database;
    }

    /**
     * @return int
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * @param int $port
     */
    public function setPort(int $port): void
    {
        $this->port = $port;
    }

    /**
     * @return float
     */
    public function getTimeout(): float
    {
        return $this->timeout;
    }

    /**
     * @param float $timeout
     */
    public function setTimeout(float $timeout): void
    {
        $this->timeout = $timeout;
    }

    /**
     * @return string
     */
    public function getCharset(): string
    {
        return $this->charset;
    }

    /**
     * @param string $charset
     */
    public function setCharset(string $charset): void
    {
        $this->charset = $charset;
    }

    /**
     * @return bool
     */
    public function isStrictType(): bool
    {
        return $this->strict_type;
    }

    /**
     * @param bool $strict_type
     */
    public function setStrictType(bool $strict_type): void
    {
        $this->strict_type = $strict_type;
    }

    /**
     * @return bool
     */
    public function isFetchMode(): bool
    {
        return $this->fetch_mode;
    }

    /**
     * @param bool $fetch_mode
     */
    public function setFetchMode(bool $fetch_mode): void
    {
        $this->fetch_mode = $fetch_mode;
    }

    /**
     * @return int
     */
    public function getMaxReconnectTimes(): int
    {
        return $this->maxReconnectTimes;
    }

    /**
     * @param int $maxReconnectTimes
     */
    public function setMaxReconnectTimes(int $maxReconnectTimes): void
    {
        $this->maxReconnectTimes = $maxReconnectTimes;
    }
}