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

use Swoole\Coroutine\MySQL as CoroutineMySQL;
use Swoole\Coroutine\MySQL\Statement;

/**
 * 自定义mysqli类
 */
class Mysqli 
{
    /**
     * 数据库配置参数
     */
    private $config;
    /**
     * [$options 当前查询参数]
     * @var array
     */
    protected $options = [];
    /**
     * [$lastPrepareQuery 最后执行的sql语句分析]
     * @var null
     */
    protected $lastPrepareQuery = null;
    /**
     * [$lastBindParams 最后执行需要绑定的参数]
     * @var array
     */
    protected $lastBindParams = [];
    /**
     * 当前参数绑定
     * @var array
     */
    protected $bind = [];
    /**
     * 当前数据表前缀
     * @var string
     */
    public $prefix = '';
    /**
     * 当前数据表名称（不含前缀）
     * @var string
     */
    public $name = '';
    /**
     * swoole 协程MYSQL客户端
     */
    protected $coroutineMysqlClient;
    /**
     * [$currentReconnectTimes 当前链接超时次数]
     * @var integer
     */
    private $currentReconnectTimes = 0;
    /**
     * [$startTransaction 事务锁]
     * @var boolean
     */
    private $startTransaction = false;

    /**
     * [__construct 构造函数]
     * @Author   szjcomo
     * @DateTime 2019-10-22
     * @param    Config     $config [description]
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->coroutineMysqlClient = new CoroutineMySQL();
        $this->prefix = $this->config->getPrefix();
    }

    /**
     * [resetDbStatus 重置数据库状态]
     * @Author   szjcomo
     * @DateTime 2019-10-22
     * @return   [type]     [description]
     */
    public function resetDbStatus()
    {
        $this->options = [];
        $this->bind = [];
        $this->name = '';
        $this->lastBindParams = [];
        $this->lastPrepareQuery = '';
        $this->currentReconnectTimes = 0;
        //if($this->startTransaction) $this->rollback();
    }

    /**
     * [disconnect 断开数据库链接]
     * @Author   szjcomo
     * @DateTime 2019-10-22
     * @return   [type]     [description]
     */
    public function disconnect()
    {
        if(!empty($this->coroutineMysqlClient)) $this->coroutineMysqlClient->close();
    }

    /**
     * [getMysqlClient 获取协程客户端]
     * @Author   szjcomo
     * @DateTime 2019-10-22
     * @return   [type]     [description]
     */
    public function getMysqlClient(): CoroutineMySQL
    {
        $this->connect();
        return $this->coroutineMysqlClient;
    }

    /**
     * [startTransaction 开启事务]
     * @Author   szjcomo
     * @DateTime 2019-10-22
     * @return bool 是否成功开启事务
     * @throws ConnectFail
     */
    public function startTrans(): bool 
    {
        if ($this->startTransaction) {
            return true;
        } else {
            $this->connect();
            $res = $this->coroutineMysqlClient->query('start transaction');
            if ($res) {
                $this->startTransaction = true;
            }
            return $res;
        }
    }

    /**
     * [commit 提交事务]
     * @Author   szjcomo
     * @DateTime 2019-10-22
     * @return bool 是否成功提交事务
     * @throws ConnectFail
     */
    public function commit(): bool 
    {
        if ($this->startTransaction) {
            $this->connect();
            $res = $this->coroutineMysqlClient->query('commit');
            if ($res) {
                $this->startTransaction = false;
            }
            return $res;
        } else {
            return true;
        }
    }

    /**
     * [rollback 回滚事务]
     * @Author   szjcomo
     * @DateTime 2019-10-22
     * @param bool $commit
     * @return array|bool
     * @throws ConnectFail
     */
    public function rollback($commit = true)
    {
        if ($this->startTransaction) {
            $this->connect();
            $res = $this->coroutineMysqlClient->query('rollback');
            if ($res && $commit) {
                $res = $this->commit();
                if ($res) {
                    $this->startTransaction = false;
                }
                return $res;
            } else {
                return $res;
            }
        } else {
            return true;
        }
    }

    /**
     * [connect 链接数据库]
     * @Author   szjcomo
     * @DateTime 2019-10-22
     * @return   [type]     [description]
     */
    public function connect()
    {
        if ($this->coroutineMysqlClient->connected) {
            return true;
        } else {
            try {
                $ret = $this->coroutineMysqlClient->connect($this->config->toArray());
                if ($ret) {
                    $this->currentReconnectTimes = 0;
                    return true;
                } else {
                    $errno = $this->coroutineMysqlClient->connect_errno;
                    $error = $this->coroutineMysqlClient->connect_error;
                    if($this->config->getMaxReconnectTimes() > $this->currentReconnectTimes){
                        $this->currentReconnectTimes++;
                        echo 'Start duplicate connection , This is the '.$this->currentReconnectTimes.' time'.PHP_EOL;
                        return $this->connect();
                    }
                    throw new \Exception("connect to {$this->config->getUser()}@{$this->config->getHost()} at port {$this->config->getPort()} fail: {$errno} {$error}");
                }
            } catch (\Throwable $throwable) {
                throw $throwable;
            }
        }
    }

    /**
     * [getResult 获取结果]
     * @Author   szjcomo
     * @DateTime 2019-10-22
     * @param    string     $sql [description]
     * @return   [type]          [description]
     */
    public function execResult()
    {
        try{
            if(isset($this->options['fetch_sql']) && $this->options['fetch_sql']){
                return $this->replacePlaceHolders($this->lastPrepareQuery,$this->lastBindParams);
            }
            if (!$this->coroutineMysqlClient->connected) $this->connect();
            $start_time = microtime(true);
            $smt = $this->coroutineMysqlClient->prepare($this->lastPrepareQuery,$this->config->getTimeout());
            if($smt === false) throw new \Exception($this->coroutineMysqlClient->error);
            $ret = $smt->execute($this->lastBindParams,$this->config->getTimeout());
            $end_time = microtime(true);
            $this->debug($start_time,$end_time);
            return $ret;
        } catch(\Throwable $err){
            throw $err;
        } finally{
            $this->resetDbStatus();
        }
    }

    /**
     * [replacePlaceHolders 替换参数绑定占位符]
     * @Author   szjcomo
     * @DateTime 2019-10-23
     * @param string $str
     * @param array $values
     * @return bool|string
     */
    Private function replacePlaceHolders($str, $values)
    {
        $i = 0;
        $newStr = "";
        if (empty($values)) {
            return $str;
        }
        while ($pos = strpos($str, "?")) {
            $val = $values[$i++];
            $echoValue = $val;
            if (is_object($val)) {
                $echoValue = '[object]';
            } else if ($val === null) {
                $echoValue = 'NULL';
            }
            // 当值是字符串时 需要引号包裹
            if (is_string($val)) {
                $newStr .= substr($str, 0, $pos) . "'" . $echoValue . "'";
            } else {
                $newStr .= substr($str, 0, $pos) . $echoValue;
            }
            $str = substr($str, $pos + 1);
        }
        $newStr .= $str;
        return $newStr;
    }

    /**
     * [debug 开启打印sql语句信息]
     * @Author   szjcomo
     * @DateTime 2019-10-22
     * @return   [type]     [description]
     */
    protected function debug($start_time,$endTime)
    {
        $debug = $this->config->getDebug();
        if($debug) {
            $execstr = 'Executed ( %s ) ;Elapsed time:%01.2f ms'.PHP_EOL;
            echo sprintf($execstr,$this->lastPrepareQuery,($endTime - $start_time) * 1000);
        }
    }
    /**
     * [rawQuery 执行原始查询语句]
     * @Author   szjcomo
     * @DateTime 2019-10-22
     * @param string $query 需要执行的语句
     * @param array $bindParams 如使用参数绑定语法 请传入本参数
     * @return mixed 被执行语句的查询结果
     * @throws ConnectFail 链接失败时请外部捕获该异常进行处理
     * @throws PrepareQueryFail 如判断传入语句不合法请捕获此错误
     */
    public function rawQuery($query, array $bindParams = [])
    {
        $this->lastPrepareQuery = $query;
        $this->lastBindParams   = $bindParams;
        return $this->execResult();
    }

    /**
     * [select 数据查询]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @param    [type]     $data [description]
     * @return   [type]           [description]
     */
    public function select($fetch_sql = false)
    {
        if($fetch_sql === true) $this->fetchSql($fetch_sql);
        $this->parseOptions();
        $this->HandlerFinalSql(Builder::select($this));
        return $this->execResult();
    }

    /**
     * [HandlerFinalSql 生成最终的sql语句和参数]
     * @Author   szjcomo
     * @DateTime 2019-10-21
     * @param    string     $sql [description]
     * @return   [type]          [description]
     */
    public function HandlerFinalSql(string $sql,$that = null)
    {
        try{
            $tmparr = [];
            $params = [];
            preg_match_all("/ThinkBind_\d{1,3}_/",$sql,$tmparr);
            $finalSql = $sql;
            if(!empty($tmparr) && !empty($tmparr[0])){
                $finalSql = str_replace($tmparr[0],'?',$sql);
                foreach($tmparr[0] as $key=>$val){
                    if(!empty($that) && $that instanceof Mysqli){
                        $params[] = $that->bind[$val][0];
                    } else {
                        $params[] = $this->bind[$val][0];
                    }
                }
            }
            if(!empty($that) && $that instanceof Mysqli){
                $that->lastBindParams = $params;
                $that->lastPrepareQuery = $finalSql;
            } else {
                $this->lastBindParams = $params;
                $this->lastPrepareQuery = $finalSql;            
            }
            return $this;           
        } catch(\Throwable $err){
            throw new \Exception($err->getMessage());
        }
    }

    /**
     * [find 查询单条数据]
     * @Author   szjcomo
     * @DateTime 2019-10-16
     * @param    string     $tableName [description]
     * @return   [type]                [description]
     */
    public function find(bool $fetch_sql = false) 
    {
        if($fetch_sql === true) $this->fetchSql($fetch_sql);
        $this->limit(1);
        $this->parseOptions();
        $this->HandlerFinalSql(Builder::select($this));
        $result = $this->execResult();
        if(is_array($result) && !empty($result)) return $result[0];
        return $result;
    }
    /**
     * [insert 数据插入]
     * @Author   szjcomo
     * @DateTime 2019-10-16
     * @param    string     $tableName [description]
     * @param    array      $data      [description]
     * @return   [type]                [description]
     */
    public function insert(array $data = [], $replace = false, $getLastInsID = false, $sequence = null)
    {
        $this->parseOptions();
        $this->options['data'] = array_merge($this->options['data'], $data);
        $this->HandlerFinalSql(Builder::insert($this));
        $ret = $this->execResult();
        if(is_bool($ret)) {
            if($ret === true) return $this->coroutineMysqlClient->insert_id;
            throw new \Exception($this->coroutineMysqlClient->error);
        }
        return $ret;
    }

    /**
     * [insertAll 批量插入记录]
     * @Author   szjcomo
     * @DateTime 2019-10-21
     * @access public
     * @param  array     $dataSet 数据集
     * @param  boolean   $replace 是否replace
     * @param  integer   $limit   每次写入数据限制
     * @return integer|string
     */
    public function insertAll(array $dataSet = [], $replace = false, $limit = null)
    {
        $this->parseOptions();
        if (empty($dataSet)) {
            $dataSet = $this->options['data'];
        }
        if (empty($limit) && !empty($this->options['limit'])) {
            $limit = $this->options['limit'];
        }
        $this->HandlerFinalSql(Builder::insertAll($this,$dataSet,$replace,$limit));
        $result = $this->execResult();
        if(is_bool($result)) {
            if($result === true) return $this->coroutineMysqlClient->insert_id;
            throw new \Exception($this->coroutineMysqlClient->error);
        }
        return $result;
    }

    /**
     * [update 更新数据]
     * @Author   szjcomo
     * @DateTime 2019-10-16
     * @param    string     $tableName [description]
     * @param    array      $where     [description]
     * @return   [type]                [description]
     */
    public function update($data = [],array $where = [])
    {
        $this->parseOptions();
        $this->options['data'] = array_merge($this->options['data'], $data);
        if(!empty($where)) $this->where($where);
        $this->HandlerFinalSql(Builder::update($this));
        $result = $this->execResult();
        if(is_bool($result)) {
            if($result === true) return $this->coroutineMysqlClient->affected_rows;
            throw new \Exception($this->coroutineMysqlClient->error);
        }
        return $result;
    }
    /**
     * [delete 删除数据]
     * @Author   szjcomo
     * @DateTime 2019-10-16
     * @access public
     * @param  array $where 删除条件
     * @return querybuild
     */
    public function delete(array $where = [])
    {
        $this->parseOptions();
        if(!empty($where)) $this->where($where);
        $this->HandlerFinalSql(Builder::delete($this));
        $result = $this->execResult();
        if(is_bool($result)){
            if($result === true) return $this->coroutineMysqlClient->affected_rows;
            throw new \Exception($this->coroutineMysqlClient->error);
        }
        return $result;
    }
    /**
     * [column description]
     * @Author   szjcomo
     * @DateTime 2019-10-22
     * @param    [type]     $field [description]
     * @return   [type]            [description]
     */
    public function column(string $field,$indexField = '')
    {
        (is_string($indexField) && !empty($indexField))?$this->field($field.','.$indexField):$this->field($field);
        $this->parseOptions();
        $this->HandlerFinalSql(Builder::select($this));
        $result = $this->execResult();
        if(is_array($result)) return empty($indexField)?array_column($result,$field):array_column($result,$field,$indexField);
        return $result;
    }
    /**
     * [value 获取某个字段的值]
     * @Author   szjcomo
     * @DateTime 2019-10-22
     * @param    string     $field [description]
     * @param    integer    $limit [description]
     * @return   [type]            [description]
     */
    public function value(string $field)
    {
        $this->field("$field as retval");
        $result = $this->find();
        if(is_array($result)) return isset($result['retval'])?$result['retval']:null;
        return $result;
    }
    /**
     * [count 聚合查询]
     * @Author   szjcomo
     * @DateTime 2019-10-22
     * @access public
     * @param  string|Expression    $field        字段名
     * @return mixed
     */
    public function count(string $field = '*')
    {
       $result = $this->value("COUNT({$field})");
       return empty($result)?0:$result;
    }
    /**
     * [max 聚合-求最大值]
     * @Author   szjcomo
     * @DateTime 2019-10-23
     * @param string $field 字段名称
     * @return mixed
     * @throws \Exception
     */
    public function max(string $field)
    {
        $result = $this->value("MAX({$field})");
        return empty($result)?null:$result;
    }
    /**
     * [min 聚合-求最小值]
     * @Author   szjcomo
     * @DateTime 2019-10-23
     * @param string $field 字段名称
     * @return mixed
     * @throws \Exception
     */
    public function min(string $field)
    {
        $result = $this->value("MIN({$field})");
        return empty($result)?null:$result;
    }
    /**
     * [sum 聚合-求和]
     * @Author   szjcomo
     * @DateTime 2019-10-23
     * @param    string     $field [description]
     * @return   [type]            [description]
     */
    public function sum(string $field)
    {
        $result = $this->value("SUM({$field})");
        return empty($result)?0:$result;
    }

    /**
     * [sum 聚合-求平均值]
     * @Author   szjcomo
     * @DateTime 2019-10-23
     * @param    string     $field [description]
     * @return   [type]            [description]
     */
    public function avg(string $field)
    {
        $result = $this->value("AVG({$field})");
        return empty($result)?0:$result;
    }

    /**
     * [distinct 指定distinct查询]
     * @Author   szjcomo
     * @DateTime 2019-10-23
     * @access public
     * @param  string $distinct 是否唯一
     * @return $this
     */
    public function distinct($distinct)
    {
        $this->options['distinct'] = $distinct;
        return $this;
    }

    /**
     * [lock 指定查询lock]
     * @Author   szjcomo
     * @DateTime 2019-10-23
     * @access public
     * @param  bool|string $lock 是否lock
     * @return $this
     */
    public function lock($lock = false)
    {
        $this->options['lock']   = $lock;
        return $this;
    }

    /**
     * [fetchSql 获取执行的SQL语句]
     * @Author   szjcomo
     * @DateTime 2019-10-23
     * @access public
     * @param  boolean $fetch 是否返回sql
     * @return $this
     */
    public function fetchSql($fetch = true)
    {
        $this->options['fetch_sql'] = $fetch;
        return $this;
    }
    /**
     * [force 指定强制索引]
     * @Author   szjcomo
     * @DateTime 2019-10-23
     * @access public
     * @param  string $force 索引名称
     * @return $this
     */
    public function force($force)
    {
        $this->options['force'] = $force;
        return $this;
    }

    /*=========================开始执行操作分析================================*/


    /**
     * [getBind 获取绑定的参数 并清空]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access public
     * @param  bool $clear
     * @return array
     */
    public function getBind($clear = true)
    {
        $bind = $this->bind;
        if ($clear) {
            $this->bind = [];
        }
        return $bind;
    }

    /**
     * [parseView 视图查询处理]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access protected
     * @param  array   $options    查询参数
     * @return void
     */
    protected function parseView(&$options)
    {
        if (!isset($options['map'])) return;
        foreach (['AND', 'OR'] as $logic) {
            if (isset($options['where'][$logic])) {
                foreach ($options['where'][$logic] as $key => $val) {
                    if (array_key_exists($key, $options['map'])) {
                        array_shift($val);
                        array_unshift($val, $options['map'][$key]);
                        $options['where'][$logic][$options['map'][$key]] = $val;
                        unset($options['where'][$logic][$key]);
                    }
                }
            }
        }
        if (isset($options['order'])) {
            // 视图查询排序处理
            if (is_string($options['order'])) {
                $options['order'] = explode(',', $options['order']);
            }
            foreach ($options['order'] as $key => $val) {
                if (is_numeric($key) && is_string($val)) {
                    if (strpos($val, ' ')) {
                        list($field, $sort) = explode(' ', $val);
                        if (array_key_exists($field, $options['map'])) {
                            $options['order'][$options['map'][$field]] = $sort;
                            unset($options['order'][$key]);
                        }
                    } elseif (array_key_exists($val, $options['map'])) {
                        $options['order'][$options['map'][$val]] = 'asc';
                        unset($options['order'][$key]);
                    }
                } elseif (array_key_exists($key, $options['map'])) {
                    $options['order'][$options['map'][$key]] = $val;
                    unset($options['order'][$key]);
                }
            }
        }
    }

    /**
     * [name 指定当前数据表名（不含前缀）]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access public
     * @param  string $name
     * @return $this
     */
    public function name($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * [table 指定当前操作的数据表]
     * @Author   szjcomo
     * @DateTime 2019-10-23
     * @access public
     * @param  mixed $table 表名
     * @return $this
     */
    public function table($table)
    {
        if (is_string($table)) {
            if (strpos($table, ')')) {
                // 子查询
            } elseif (strpos($table, ',')) {
                $tables = explode(',', $table);
                $table  = [];
                foreach ($tables as $item) {
                    list($item, $alias) = explode(' ', trim($item));
                    if ($alias) {
                        $this->alias([$item => $alias]);
                        $table[$item] = $alias;
                    } else {
                        $table[] = $item;
                    }
                }
            } elseif (strpos($table, ' ')) {
                list($table, $alias) = explode(' ', $table);
                $table = [$table => $alias];
                $this->alias($table);
            }
        } else {
            $tables = $table;
            $table  = [];
            foreach ($tables as $key => $val) {
                if (is_numeric($key)) {
                    $table[] = $val;
                } else {
                    $this->alias([$key => $val]);
                    $table[$key] = $val;
                }
            }
        }
        $this->options['table'] = $table;
        return $this;
    }

    /**
     * [buildSql 创建子查询SQL]
     * @Author   szjcomo
     * @DateTime 2019-10-23
     * @access public
     * @param  bool $sub
     * @return string
     * @throws Exception
     */
    public function buildSql($sub = true)
    {
        return $sub ? '( ' . $this->select(true). ' )' : $this->select(true);
    }

    /**
     * [alias 指定数据表别名]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access public
     * @param  array|string $alias 数据表别名
     * @return $this
     */
    public function alias($alias)
    {
        if (is_array($alias)) {
            foreach ($alias as $key => $val) {
                if (false !== strpos($key, '__')) {
                    $table = $this->parseSqlTable($key);
                } else {
                    $table = $key;
                }
                $this->options['alias'][$table] = $val;
            }
        } else {
            if (isset($this->options['table'])) {
                $table = is_array($this->options['table']) ? key($this->options['table']) : $this->options['table'];
                if (false !== strpos($table, '__')) {
                    $table = $this->parseSqlTable($table);
                }
            } else {
                $table = $this->getTable();
            }
            $this->options['alias'][$table] = $alias;
        }
        return $this;
    }

    /**
     * [parseSqlTable 将SQL语句中的__TABLE_NAME__字符串替换成带前缀的表名（小写）]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access public
     * @param  string $sql sql语句
     * @return string
     */
    public function parseSqlTable($sql)
    {
        if (false !== strpos($sql, '__')) {
            $sql = preg_replace_callback("/__([A-Z0-9_-]+)__/sU", function ($match) {
                return $this->prefix . strtolower($match[1]);
            }, $sql);
        }
        return $sql;
    }

    /**
     * [getTable 得到当前或者指定名称的数据表]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access public
     * @param  string $name
     * @return string
     */
    public function getTable($name = '')
    {
        if (empty($name) && isset($this->options['table'])) {
            return $this->options['table'];
        }
        $name = $name ?: $this->name;
        return $this->prefix . $name;
    }

    /**
     * 分析表达式（可用于查询或者写入操作）
     * @access protected
     * @return array
     */
    protected function parseOptions()
    {
        $options = $this->getOptions();
        // 获取数据表
        if (empty($options['table'])) {
            $options['table'] = $this->getTable();
        }
        if (!isset($options['where'])) {
            $options['where'] = [];
        } elseif (isset($options['view'])) {
            // 视图查询条件处理
            $this->parseView($options);
        }
        if (!isset($options['field'])) {
            $options['field'] = '*';
        }
        foreach (['data', 'order', 'join', 'union'] as $name) {
            if (!isset($options[$name])) {
                $options[$name] = [];
            }
        }
        if (!isset($options['strict'])) {
            //$options['strict'] = $this->getConfig('fields_strict');
            $options['strict'] = '';
        }
        foreach (['master', 'lock', 'fetch_pdo', 'fetch_sql', 'distinct'] as $name) {
            if (!isset($options[$name])) {
                $options[$name] = false;
            }
        }
        /*if (isset(static::$readMaster['*']) || (is_string($options['table']) && isset(static::$readMaster[$options['table']]))) {
            $options['master'] = true;
        }*/
        foreach (['group', 'having', 'limit', 'force', 'comment'] as $name) {
            if (!isset($options[$name])) {
                $options[$name] = '';
            }
        }
        if (isset($options['page'])) {
            // 根据页数计算limit
            list($page, $listRows) = $options['page'];
            $page                  = $page > 0 ? $page : 1;
            $listRows              = $listRows > 0 ? $listRows : (is_numeric($options['limit']) ? $options['limit'] : 20);
            $offset                = $listRows * ($page - 1);
            $options['limit']      = $offset . ',' . $listRows;
        }
        $this->options = $options;
        return $options;
    }

    /*=========================实现抽像类中的方法===============================*/

    /**
     * [getLastQuery 实现抽像类中的方法]
     * @Author   szjcomo
     * @DateTime 2019-10-17
     * @return   [type]     [description]
     */
    public function getLastQuery()
    {
        return $this->lastQuery;
    }
    /**
     * [getLastPrepareQuery 实现抽像类中的方法]
     * @Author   szjcomo
     * @DateTime 2019-10-17
     * @return   [type]     [description]
     */
    public function getLastPrepareQuery():?string
    {
        return $this->lastPrepareQuery;
    }
    /**
     * [getLastBindParams 实现抽像类中的方法]
     * @Author   szjcomo
     * @DateTime 2019-10-17
     * @return   [type]     [description]
     */
    public function getLastBindParams()
    {
        return $this->lastBindParams;
    }
    /**
     * [getLastQueryOptions 实现抽像类中的方法]
     * @Author   szjcomo
     * @DateTime 2019-10-17
     * @return   [type]     [description]
     */
    public function getLastQueryOptions():array
    {
        return $this->lastQueryOptions;
    }

    /**
     * [reset 实现抽像类中的方法]
     * @Author   szjcomo
     * @DateTime 2019-10-17
     * @return   [type]     [description]
     */
    public function reset()
    {
        //$this->
    }

    /*=========================其它辅助函数===============================*/

    /**
     * [raw 使用表达式设置数据]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access public
     * @param  mixed $value 表达式
     * @return Expression
     */
    public function raw($value)
    {
        return new Expression($value);
    }

    /**
     * [bind 参数绑定]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access public
     * @param  mixed   $value 绑定变量值
     * @param  integer $type  绑定类型
     * @param  string  $name  绑定名称
     * @return $this|string
     */
    public function bind($value, $type = \PDO::PARAM_STR, $name = null)
    {
        if (is_array($value)) {
            $this->bind = array_merge($this->bind, $value);
        } else {
            $name = $name ?: 'ThinkBind_' . (count($this->bind) + 1) . '_';
            $this->bind[$name] = [$value, $type];
            return $name;
        }
        return $this;
    }

    /**
     * [getOptions 获取当前的查询参数]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access public
     * @param  string $name 参数名
     * @return mixed
     */
    public function getOptions($name = '')
    {
        if ('' === $name) return $this->options;
        return isset($this->options[$name]) ? $this->options[$name] : null;
    }

    /**
     * [bindParams 参数绑定]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access public
     * @param  string $sql    绑定的sql表达式
     * @param  array  $bind   参数绑定
     * @return void
     */
    protected function bindParams(&$sql, array $bind = [])
    {
        foreach ($bind as $key => $value) {
            if (is_array($value)) {
                $name = $this->bind($value[0], $value[1], isset($value[2]) ? $value[2] : null);
            } else {
                $name = $this->bind($value);
            }
            if (is_numeric($key)) {
                $sql = substr_replace($sql, ':' . $name, strpos($sql, '?'), 1);
            } else {
                $sql = str_replace(':' . $key, ':' . $name, $sql);
            }
        }
    }

    /**
     * [via 设置当前字段添加的表别名]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access public
     * @param  string $via
     * @return $this
     */
    public function via($via = '')
    {
        $this->options['via'] = $via;
        return $this;
    }

    /*===============实现精简版where条件==========================*/

    /**
     * 指定表达式查询条件
     * @access public
     * @param  string $where  查询条件
     * @param  array  $bind   参数绑定
     * @param  string $logic  查询逻辑 and or xor
     * @return $this
     */
    public function whereRaw($where, $bind = [], $logic = 'AND')
    {
        if ($bind) $this->bindParams($where, $bind);
        $this->options['where'][$logic][] = $this->raw($where);
        return $this;
    }

    /**
     * 指定AND查询条件
     * @access public
     * @param  mixed $field     查询字段
     * @param  mixed $op        查询表达式
     * @param  mixed $condition 查询条件
     * @return $this
     */
    public function where($field, $op = null, $condition = null)
    {
        if(!empty($field)){
            $param = func_get_args();
            array_shift($param);
            return $this->parseWhereExp('AND', $field, $op, $condition, $param);            
        }
        return $this;
    }


    /**
     * [whereColumn 比较两个字段]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access public
     * @param  string|array $field1     查询字段
     * @param  string       $operator   比较操作符
     * @param  string       $field2     比较字段
     * @param  string       $logic      查询逻辑 and or xor
     * @return $this
     */
    public function whereColumn($field1, $operator = null, $field2 = null, $logic = 'AND')
    {
        if (is_array($field1)) {
            foreach ($field1 as $item) {
                $this->whereColumn($item[0], $item[1], isset($item[2]) ? $item[2] : null);
            }
            return $this;
        }
        if (is_null($field2)) {
            $field2   = $operator;
            $operator = '=';
        }
        return $this->parseWhereExp($logic, $field1, 'COLUMN', [$operator, $field2], [], true);
    }

    /**
     * [whereOr 指定OR查询条件]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access public
     * @param  mixed $field     查询字段
     * @param  mixed $op        查询表达式
     * @param  mixed $condition 查询条件
     * @return $this
     */
    public function whereOr($field, $op = null, $condition = null)
    {
        $param = func_get_args();
        array_shift($param);
        return $this->parseWhereExp('OR', $field, $op, $condition, $param);
    }

    /**
     * [whereXor 指定XOR查询条件]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access public
     * @param  mixed $field     查询字段
     * @param  mixed $op        查询表达式
     * @param  mixed $condition 查询条件
     * @return $this
     */
    public function whereXor($field, $op = null, $condition = null)
    {
        $param = func_get_args();
        array_shift($param);
        return $this->parseWhereExp('XOR', $field, $op, $condition, $param);
    }

    /**
     * [whereNull 指定Null查询条件]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access public
     * @param  mixed  $field 查询字段
     * @param  string $logic 查询逻辑 and or xor
     * @return $this
     */
    public function whereNull($field, $logic = 'AND')
    {
        return $this->parseWhereExp($logic, $field, 'NULL', null, [], true);
    }

    /**
     * [whereNotNull 指定NotNull查询条件]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access public
     * @param  mixed  $field 查询字段
     * @param  string $logic 查询逻辑 and or xor
     * @return $this
     */
    public function whereNotNull($field, $logic = 'AND')
    {
        return $this->parseWhereExp($logic, $field, 'NOTNULL', null, [], true);
    }

    /**
     * [whereExists 指定Exists查询条件]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access public
     * @param  mixed  $condition 查询条件
     * @param  string $logic     查询逻辑 and or xor
     * @return $this
     */
    public function whereExists($condition, $logic = 'AND')
    {
        if (is_string($condition)) $condition = $this->raw($condition);
        $this->options['where'][strtoupper($logic)][] = ['', 'EXISTS', $condition];
        return $this;
    }

    /**
     * [whereNotExists 指定NotExists查询条件]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access public
     * @param  mixed  $condition 查询条件
     * @param  string $logic     查询逻辑 and or xor
     * @return $this
     */
    public function whereNotExists($condition, $logic = 'AND')
    {
        if (is_string($condition)) $condition = $this->raw($condition);
        $this->options['where'][strtoupper($logic)][] = ['', 'NOT EXISTS', $condition];
        return $this;
    }

    /**
     * [whereIn 指定In查询条件]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access public
     * @param  mixed  $field     查询字段
     * @param  mixed  $condition 查询条件
     * @param  string $logic     查询逻辑 and or xor
     * @return $this
     */
    public function whereIn($field, $condition, $logic = 'AND')
    {
        return $this->parseWhereExp($logic, $field, 'IN', $condition, [], true);
    }

    /**
     * [whereNotIn 指定NotIn查询条件]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access public
     * @param  mixed  $field     查询字段
     * @param  mixed  $condition 查询条件
     * @param  string $logic     查询逻辑 and or xor
     * @return $this
     */
    public function whereNotIn($field, $condition, $logic = 'AND')
    {
        return $this->parseWhereExp($logic, $field, 'NOT IN', $condition, [], true);
    }
    /**
     * [whereLike 指定Like查询条件]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access public
     * @param  mixed  $field     查询字段
     * @param  mixed  $condition 查询条件
     * @param  string $logic     查询逻辑 and or xor
     * @return $this
     */
    public function whereLike($field, $condition, $logic = 'AND')
    {
        return $this->parseWhereExp($logic, $field, 'LIKE', $condition, [], true);
    }

    /**
     * [whereNotLike 指定NotLike查询条件]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access public
     * @param  mixed  $field     查询字段
     * @param  mixed  $condition 查询条件
     * @param  string $logic     查询逻辑 and or xor
     * @return $this
     */
    public function whereNotLike($field, $condition, $logic = 'AND')
    {
        return $this->parseWhereExp($logic, $field, 'NOT LIKE', $condition, [], true);
    }

    /**
     * [whereBetween 指定Between查询条件]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access public
     * @param  mixed  $field     查询字段
     * @param  mixed  $condition 查询条件
     * @param  string $logic     查询逻辑 and or xor
     * @return $this
     */
    public function whereBetween($field, $condition, $logic = 'AND')
    {
        return $this->parseWhereExp($logic, $field, 'BETWEEN', $condition, [], true);
    }

    /**
     * [whereNotBetween 指定NotBetween查询条件]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access public
     * @param  mixed  $field     查询字段
     * @param  mixed  $condition 查询条件
     * @param  string $logic     查询逻辑 and or xor
     * @return $this
     */
    public function whereNotBetween($field, $condition, $logic = 'AND')
    {
        return $this->parseWhereExp($logic, $field, 'NOT BETWEEN', $condition, [], true);
    }

    /**
     * 分析查询表达式
     * @access protected
     * @param  string   $logic     查询逻辑 and or xor
     * @param  mixed    $field     查询字段
     * @param  mixed    $op        查询表达式
     * @param  mixed    $condition 查询条件
     * @param  array    $param     查询参数
     * @param  bool     $strict    严格模式
     * @return $this
     */
    protected function parseWhereExp($logic, $field, $op, $condition, array $param = [], $strict = false)
    {
        $logic = strtoupper($logic);
        if ($field instanceof Where) {
            $this->options['where'][$logic] = $field->parse();
            return $this;
        }
        if ($field instanceof Expression) {
            return $this->whereRaw($field, is_array($op) ? $op : [], $logic);
        } elseif ($strict) {
            // 使用严格模式查询
            $where = [$field, $op, $condition, $logic];
        } elseif (is_array($field)) {
            // 解析数组批量查询
            return $this->parseArrayWhereItems($field, $logic);
        } elseif (is_string($field)) {
            if (preg_match('/[,=\<\'\"\(\s]/', $field)) {
                return $this->whereRaw($field, $op, $logic);
            }
            $where = $this->parseWhereItem($logic, $field, $op, $condition, $param);
        }
        if (!empty($where)) $this->options['where'][$logic][] = $where;
        return $this;
    }

    /**
     * 数组批量查询
     * @access protected
     * @param  array    $field     批量查询
     * @param  string   $logic     查询逻辑 and or xor
     * @return $this
     */
    protected function parseArrayWhereItems($field, $logic)
    {
        if (key($field) !== 0) {
            $where = [];
            foreach ($field as $key => $val) {
                if ($val instanceof Expression) {
                    $where[] = [$key, 'exp', $val];
                } elseif (is_null($val)) {
                    $where[] = [$key, 'NULL', ''];
                } else {
                    $where[] = [$key, is_array($val) ? 'IN' : '=', $val];
                }
            }
        } else {
            // 数组批量查询
            $where = $field;
        }
        if (!empty($where)) {
            $this->options['where'][$logic] = isset($this->options['where'][$logic]) ? array_merge($this->options['where'][$logic], $where) : $where;
        }
        return $this;
    }


    /**
     * 分析查询表达式
     * @access protected
     * @param  string   $logic     查询逻辑 and or xor
     * @param  mixed    $field     查询字段
     * @param  mixed    $op        查询表达式
     * @param  mixed    $condition 查询条件
     * @param  array    $param     查询参数
     * @return mixed
     */
    protected function parseWhereItem($logic, $field, $op, $condition, $param = [])
    {
        if (is_array($op)) {
            // 同一字段多条件查询
            array_unshift($param, $field);
            $where = $param;
            //dump($where);exit;
        } elseif ($field && is_null($condition)) {
            if (in_array(strtoupper($op), ['NULL', 'NOTNULL', 'NOT NULL'], true)) {
                // null查询
                $where = [$field, $op, ''];
            } elseif (in_array($op, ['=', 'eq', 'EQ', null], true)) {
                $where = [$field, 'NULL', ''];
            } elseif (in_array($op, ['<>', 'neq', 'NEQ'], true)) {
                $where = [$field, 'NOTNULL', ''];
            } else {
                // 字段相等查询
                $where = [$field, '=', $op];
            }
        } elseif (in_array(strtoupper($op), ['EXISTS', 'NOT EXISTS', 'NOTEXISTS'], true)) {
            $where = [$field, $op, is_string($condition) ? $this->raw($condition) : $condition];
        } else {
            $where = $field ? [$field, $op, $condition, isset($param[2]) ? $param[2] : null] : null;
        }
        return $where;
    }

    /**
     * [field 指定查询字段 支持字段排除和指定数据表]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access public
     * @param  mixed   $field
     * @param  boolean $except    是否排除
     * @param  string  $tableName 数据表名
     * @param  string  $prefix    字段前缀
     * @param  string  $alias     别名前缀
     * @return $this
     */
    public function field($field, $except = false, $tableName = '', $prefix = '', $alias = '')
    {
        if (empty($field)) return $this;
        if ($field instanceof Expression) {
            $this->options['field'][] = $field;
            return $this;
        }
        if (is_string($field)) {
            if (preg_match('/[\<\'\"\(]/', $field)) return $this->fieldRaw($field);
            $field = array_map('trim', explode(',', $field));
        }
        if (true === $field) $field  = ['*'];
        if ($tableName) {
            // 添加统一的前缀
            $prefix = $prefix ?: $tableName;
            foreach ($field as $key => &$val) {
                if (is_numeric($key) && $alias) {
                    $field[$prefix . '.' . $val] = $alias . $val;
                    unset($field[$key]);
                } elseif (is_numeric($key)) {
                    $val = $prefix . '.' . $val;
                }
            }
        }
        if (isset($this->options['field'])) $field = array_merge((array) $this->options['field'], $field);
        $this->options['field'] = array_unique($field);
        return $this;
    }

    /**
     * [fieldRaw 表达式方式指定查询字段]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access public
     * @param  string $field    字段名
     * @return $this
     */
    public function fieldRaw($field)
    {
        $this->options['field'][] = $this->raw($field);
        return $this;
    }

    /**
     * [data 设置数据]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access public
     * @param  mixed $field 字段名或者数据
     * @param  mixed $value 字段值
     * @return $this
     */
    public function data($field, $value = null)
    {
        if (is_array($field)) {
            $this->options['data'] = isset($this->options['data']) ? array_merge($this->options['data'], $field) : $field;
        } else {
            $this->options['data'][$field] = $value;
        }
        return $this;
    }

    /**
     * [order 指定排序 order('id','desc') 或者 order(['id'=>'desc','create_time'=>'desc'])]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access public
     * @param  string|array $field 排序字段
     * @param  string       $order 排序
     * @return $this
     */
    public function order($field, $order = null)
    {
        if (empty($field)) {
            return $this;
        } elseif ($field instanceof Expression) {
            $this->options['order'][] = $field;
            return $this;
        }
        if (is_string($field)) {
            if (!empty($this->options['via'])) {
                $field = $this->options['via'] . '.' . $field;
            }
            if (strpos($field, ',')) {
                $field = array_map('trim', explode(',', $field));
            } else {
                $field = empty($order) ? $field : [$field => $order];
            }
        } elseif (!empty($this->options['via'])) {
            foreach ($field as $key => $val) {
                if (is_numeric($key)) {
                    $field[$key] = $this->options['via'] . '.' . $val;
                } else {
                    $field[$this->options['via'] . '.' . $key] = $val;
                    unset($field[$key]);
                }
            }
        }
        if (!isset($this->options['order'])) {
            $this->options['order'] = [];
        }
        if (is_array($field)) {
            $this->options['order'] = array_merge($this->options['order'], $field);
        } else {
            $this->options['order'][] = $field;
        }
        return $this;
    }

    /**
     * [orderRaw 表达式方式指定Field排序]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access public
     * @param  string $field 排序字段
     * @param  array  $bind  参数绑定
     * @return $this
     */
    public function orderRaw($field, $bind = [])
    {
        if ($bind) {
            $this->bindParams($field, $bind);
        }
        $this->options['order'][] = $this->raw($field);
        return $this;
    }

    /**
     * [orderField 指定Field排序 order('id',[1,2,3],'desc')]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access public
     * @param  string|array $field 排序字段
     * @param  array        $values 排序值
     * @param  string       $order
     * @return $this
     */
    public function orderField($field, array $values, $order = '')
    {
        if (!empty($values)) {
            $values['sort'] = $order;
            $this->options['order'][$field] = $values;
        }
        return $this;
    }

    /**
     * 指定查询数量
     * @access public
     * @param  mixed $offset 起始位置
     * @param  mixed $length 查询数量
     * @return $this
     */
    public function limit($offset, $length = null)
    {
        if (is_null($length) && strpos($offset, ',')) {
            list($offset, $length) = explode(',', $offset);
        }
        $this->options['limit'] = intval($offset) . ($length ? ',' . intval($length) : '');
        return $this;
    }

    /**
     * [page 指定分页]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access public
     * @param  mixed $page     页数
     * @param  mixed $listRows 每页数量
     * @return $this
     */
    public function page($page, $listRows = null)
    {
        if (is_null($listRows) && strpos($page, ',')) {
            list($page, $listRows) = explode(',', $page);
        }
        $this->options['page'] = [intval($page), intval($listRows)];
        return $this;
    }

    /**
     * [group 指定group查询]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @param  string|array $group GROUP
     * @return $this
     */
    public function group($group)
    {
        $this->options['group'] = $group;
        return $this;
    }

    /**
     * [having 指定having查询]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access public
     * @param  string $having having
     * @return $this
     */
    public function having($having)
    {
        $this->options['having'] = $having;
        return $this;
    }

    /**
     * [union 查询SQL组装 union]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access public
     * @param  mixed   $union
     * @param  boolean $all
     * @return $this
     */
    public function union($union, $all = false)
    {
        if (empty($union)) return $this;
        $this->options['union']['type'] = $all ? 'UNION ALL' : 'UNION';
        if (is_array($union)) {
            $this->options['union'] = array_merge($this->options['union'], $union);
        } else {
            $this->options['union'][] = $union;
        }
        return $this;
    }

    /**
     * [unionAll 查询SQL组装 union all]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access public
     * @param  mixed   $union
     * @return $this
     */
    public function unionAll($union)
    {
        return $this->union($union, true);
    }

    /*===================join 查询======================*/

    /**
     * [join 查询SQL组装 join]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access public
     * @param  mixed  $join      关联的表名
     * @param  mixed  $condition 条件
     * @param  string $type      JOIN类型
     * @param  array  $bind      参数绑定
     * @return $this
     */
    public function join($join, $condition = null, $type = 'INNER', $bind = [])
    {
        if (empty($condition)) {
            // 如果为组数，则循环调用join
            foreach ($join as $key => $value) {
                if (is_array($value) && 2 <= count($value)) {
                    $this->join($value[0], $value[1], isset($value[2]) ? $value[2] : $type);
                }
            }
        } else {
            $table = $this->getJoinTable($join);
            if ($bind) {
                $this->bindParams($condition, $bind);
            }
            $this->options['join'][] = [$table, strtoupper($type), $condition];
        }

        return $this;
    }

    /**
     * 获取Join表名及别名 支持
     * ['prefix_table或者子查询'=>'alias'] 'table alias'
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access protected
     * @param  array|string $join
     * @param  string       $alias
     * @return string
     */
    protected function getJoinTable($join, &$alias = null)
    {
        if (is_array($join)) {
            $table = $join;
            $alias = array_shift($join);
        } else {
            $join = trim($join);
            if (false !== strpos($join, '(')) {
                // 使用子查询
                $table = $join;
            } else {
                $prefix = $this->prefix;
                if (strpos($join, ' ')) {
                    // 使用别名
                    list($table, $alias) = explode(' ', $join);
                } else {
                    $table = $join;
                    if (false === strpos($join, '.') && 0 !== strpos($join, '__')) {
                        $alias = $join;
                    }
                }
                if ($prefix && false === strpos($table, '.') && 0 !== strpos($table, $prefix) && 0 !== strpos($table, '__')) {
                    $table = $this->getTable($table);
                }
            }
            if (isset($alias) && $table != $alias) {
                $table = [$table => $alias];
            }
        }
        return $table;
    }

    /**
     * [leftJoin LEFT JOIN]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access public
     * @param  mixed  $join      关联的表名
     * @param  mixed  $condition 条件
     * @param  array  $bind      参数绑定
     * @return $this
     */
    public function leftJoin($join, $condition = null, $bind = [])
    {
        return $this->join($join, $condition, 'LEFT');
    }

    /**
     * [rightJoin RIGHT JOIN]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access public
     * @param  mixed  $join      关联的表名
     * @param  mixed  $condition 条件
     * @param  array  $bind      参数绑定
     * @return $this
     */
    public function rightJoin($join, $condition = null, $bind = [])
    {
        return $this->join($join, $condition, 'RIGHT');
    }

    /**
     * [fullJoin FULL JOIN]
     * @Author   szjcomo
     * @DateTime 2019-10-19
     * @access public
     * @param  mixed  $join      关联的表名
     * @param  mixed  $condition 条件
     * @param  array  $bind      参数绑定
     * @return $this
     */
    public function fullJoin($join, $condition = null, $bind = [])
    {
        return $this->join($join, $condition, 'FULL');
    }

    /**
     * [getConfig 获取数据库配置]
     * @author        szjcomo
     * @createTime 2019-11-12
     * @return     [type]     [description]
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * [__destruct 析构被调用时关闭当前链接并释放客户端对象]
     * @Author   szjcomo
     * @DateTime 2019-10-22
     */
    function __destruct()
    {
        if (isset($this->coroutineMysqlClient) && $this->coroutineMysqlClient->connected) {
            $this->coroutineMysqlClient->close();
        }
        $this->coroutineMysqlClient = null;
    }
}